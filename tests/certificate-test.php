<?php
/**
 * Certificates, the capital behind them, and the public page that verifies them.
 *
 * Three assertions carry this harness, and all three are about what must NOT happen:
 *
 * 1. **An issued certificate does not change.** Somebody is holding the paper. §6
 *    issues one, then records more capital, and asserts the stored snapshot is
 *    byte-identical while the live reader moves.
 * 2. **A correction is a new version, not an edit.** V1 keeps rendering and names V2.
 * 3. **The verification page leaks nothing.** It is public, the numbers are sequential,
 *    and anything it shows is therefore shown to everybody. §10 asserts the amount, the
 *    mobile, the NID and the investor's full name are all absent from it.
 *
 * Every figure is a fixture this harness created or a delta (§13.38).
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );
bhela_bm_install_roles();

/* ---------- fixtures ---------- */

$ct_made    = array();
$ct_seq_was = get_option( 'bhela_bm_doc_seq', array() );

function ct_investor( $name, $mobile = '01700000001', $nid = '1990123456789' ) {
	$id = wp_insert_post( array(
		'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => $name,
	) );
	update_post_meta( $id, '_bhela_inv_status', 'active' );
	update_post_meta( $id, '_bhela_inv_code', 'ZZC-' . $id );
	update_post_meta( $id, '_bhela_inv_father', 'ZZ Father Of ' . $name );
	update_post_meta( $id, '_bhela_inv_mobile', $mobile );
	update_post_meta( $id, '_bhela_inv_nid', $nid );
	$GLOBALS['ct_made'][] = (int) $id;
	return (int) $id;
}

function ct_investment( $investor, $principal = 500000, $activate = true ) {
	$agr = bhela_bm_agreement_add( array( 'investor' => $investor, 'date' => '2024-06-25', 'parties' => 'ZZ parties' ) );
	$GLOBALS['ct_made'][] = (int) $agr;

	$id = bhela_bm_investment_add( array(
		'investor' => $investor, 'date' => '2024-07-01', 'start' => '2024-07-01',
		'maturity' => '2025-06-30', 'type' => 'term', 'method' => 'fixed_annual',
		'rate' => '12', 'frequency' => 'monthly', 'agreement' => $agr,
	) );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	$GLOBALS['ct_made'][] = (int) $id;

	$cap = bhela_bm_capital_add( array(
		'investor' => $investor, 'investment' => $id, 'date' => '2024-07-01',
		'amount' => $principal, 'method' => 'Bank transfer', 'ref' => 'ZZ-REC-1',
	) );
	if ( ! is_wp_error( $cap ) ) {
		$GLOBALS['ct_made'][] = (int) $cap;
	}
	if ( $activate ) {
		bhela_bm_investment_transition( $id, 'active' );
	}
	return (int) $id;
}

$ct_a   = ct_investor( 'ZZ Cert Rahim' );
$ct_inv = ct_investment( $ct_a );

echo "=== 1. capital, grouped and named ===\n";

$ct_y = bhela_bm_capital_years( $ct_a );
ok( 500000 === $ct_y['dated'], 'the dated capital is what was received', (string) $ct_y['dated'] );
ok( 1 === count( $ct_y['years'] ), 'grouped into one year', (string) count( $ct_y['years'] ) );
ok( 500000 === bhela_bm_investment_principal( $ct_inv ), 'and it is the investment\'s principal' );

echo "\n=== 2. a capital row is locked from birth ===\n";

$ct_cap = $ct_y['rows'][0]['id'];
update_post_meta( $ct_cap, '_bhela_cap_amount', 999 );
ok( 500000 === (int) get_post_meta( $ct_cap, '_bhela_cap_amount', true ), 'update_post_meta is refused' );

// The probe that matters (§13.49): add_post_meta on an ABSENT key fires a different
// filter, and a lock missing add_post_metadata passes every other test.
delete_post_meta( $ct_cap, '_bhela_cap_absent' );
add_post_meta( $ct_cap, '_bhela_cap_absent', 'forged' );
ok( '' === get_post_meta( $ct_cap, '_bhela_cap_absent', true ), 'add_post_meta on an absent key is refused' );

// And §13.55: delete_post_meta_by_key() names no post at all.
delete_post_meta_by_key( '_bhela_cap_amount' );
ok( 500000 === (int) get_post_meta( $ct_cap, '_bhela_cap_amount', true ), 'a blanket delete of the key is refused' );

wp_delete_post( $ct_cap, true );
ok( 'bhela_capital' === get_post_type( $ct_cap ), 'hard delete is refused' );

echo "\n=== 3. an agreement is locked too, but can still be superseded ===\n";

$ct_agr = bhela_bm_agreements( $ct_a )[0]['id'];
update_post_meta( $ct_agr, '_bhela_agr_date', '1999-01-01' );
ok( '2024-06-25' === get_post_meta( $ct_agr, '_bhela_agr_date', true ), 'the signed date cannot be rewritten' );
ok( true === bhela_bm_agreement_status( $ct_agr, 'superseded', 'ZZ replaced' ), 'but its status can move' );
bhela_bm_agreement_status( $ct_agr, 'signed', 'ZZ back' );

echo "\n=== 4. preview is pure, and the commit writes exactly it ===\n";

function ct_count_certs() {
	return count( get_posts( array(
		'post_type' => 'bhela_cert', 'post_status' => 'any', 'posts_per_page' => -1,
		'fields' => 'ids', 'no_found_rows' => true,
	) ) );
}
$ct_before = ct_count_certs();
$ct_prev   = bhela_bm_cert_preview( 'investment', $ct_inv );
ok( is_array( $ct_prev ), 'the investment preview resolves', is_wp_error( $ct_prev ) ? $ct_prev->get_error_message() : '' );
ok( $ct_before === ct_count_certs(), 'and writes nothing' );

$ct_cert = bhela_bm_cert_issue( array( 'type' => 'investment', 'investment' => $ct_inv ) );
ok( ! is_wp_error( $ct_cert ), 'issuing succeeds', is_wp_error( $ct_cert ) ? $ct_cert->get_error_message() : '' );
$GLOBALS['ct_made'][] = (int) $ct_cert;

$ct_data = bhela_bm_cert_data( $ct_cert );
ok( $ct_prev === $ct_data['snapshot'], 'the stored snapshot equals the preview, field for field' );
ok( 0 === strpos( $ct_data['number'], 'BHELA-IC-' ), 'the number uses the client\'s format', $ct_data['number'] );
ok( '-V1' === substr( $ct_data['number'], -3 ), 'and it is version 1', $ct_data['number'] );

echo "\n=== 5. the certificate carries no secret field ===\n";

$ct_json = wp_json_encode( $ct_data['snapshot'] );
ok( false === strpos( $ct_json, '1990123456789' ), 'the NID is not in the snapshot' );
ok( false === strpos( $ct_json, '01700000001' ), 'nor is the mobile number' );
ok( false !== strpos( $ct_json, 'ZZ Father Of' ), 'the father\'s name, which the form asks for, is' );

echo "\n=== 6. an issued certificate does not move ===\n";

$ct_snap_before = bhela_bm_cert_data( $ct_cert )['snapshot'];
$ct_extra       = bhela_bm_capital_add( array(
	'investor' => $ct_a, 'investment' => $ct_inv, 'date' => '2024-09-01', 'amount' => 250000,
) );
$GLOBALS['ct_made'][] = (int) $ct_extra;

ok( 750000 === bhela_bm_investment_principal( $ct_inv ), 'the live principal moves', (string) bhela_bm_investment_principal( $ct_inv ) );
ok( $ct_snap_before === bhela_bm_cert_data( $ct_cert )['snapshot'],
	'and the issued certificate does NOT — this is the whole point of it' );

echo "\n=== 7. a correction is a new version ===\n";

$ct_v2 = bhela_bm_cert_issue( array(
	'type' => 'investment', 'investment' => $ct_inv,
	'replaces' => $ct_cert, 'reason' => 'ZZ second receipt was missing',
) );
ok( ! is_wp_error( $ct_v2 ), 'the corrected certificate issues', is_wp_error( $ct_v2 ) ? $ct_v2->get_error_message() : '' );
$GLOBALS['ct_made'][] = (int) $ct_v2;

$ct_new = bhela_bm_cert_data( $ct_v2 );
$ct_old = bhela_bm_cert_data( $ct_cert );
ok( $ct_new['base'] === $ct_old['base'], 'it keeps the same base number', $ct_new['base'] );
ok( 2 === $ct_new['version'] && '-V2' === substr( $ct_new['number'], -3 ), 'as version 2', $ct_new['number'] );
ok( 750000 === $ct_new['snapshot']['principal'], 'and states the corrected figure', (string) $ct_new['snapshot']['principal'] );
ok( (int) $ct_old['superseded'] === (int) $ct_v2, 'V1 points at V2' );
ok( $ct_old['snapshot'] === $ct_snap_before, 'while V1\'s own figures are untouched' );

echo "\n=== 8. a profit certificate needs approved profit ===\n";

$ct_none = bhela_bm_cert_preview( 'profit', $ct_inv );
ok( is_wp_error( $ct_none ) && 'nothing_posted' === $ct_none->get_error_code(),
	'with nothing approved it refuses rather than printing a zero' );

$ct_r   = bhela_bm_investment( $ct_inv );
$ct_acc = bhela_bm_profit_accrue( $ct_r, '2024-08-31' );
foreach ( $ct_acc['periods'] as $ct_p ) {
	bhela_bm_profit_post( $ct_r, $ct_p );
}
$ct_pc = bhela_bm_cert_issue( array( 'type' => 'profit', 'investment' => $ct_inv ) );
ok( ! is_wp_error( $ct_pc ), 'once approved, it issues', is_wp_error( $ct_pc ) ? $ct_pc->get_error_message() : '' );
$GLOBALS['ct_made'][] = (int) $ct_pc;

$ct_ps = bhela_bm_cert_data( $ct_pc )['snapshot'];
ok( 0 === strpos( bhela_bm_cert_data( $ct_pc )['number'], 'BHELA-PC-' ), 'in the PC series' );
ok( $ct_ps['gross'] > 0, 'with a gross figure', (string) $ct_ps['gross'] );
ok( $ct_ps['net'] === $ct_ps['gross'] + $ct_ps['adjustments'], 'net is gross plus adjustments' );
ok( $ct_ps['due'] === max( 0, $ct_ps['net'] - $ct_ps['paid'] ), 'and due is what is left' );
ok( 'UNPAID' === $ct_ps['status']['en'], 'nothing paid yet reads UNPAID', $ct_ps['status']['en'] );

echo "\n=== 9. a draft investment cannot be certified ===\n";

$ct_b     = ct_investor( 'ZZ Cert Draft' );
$ct_draft = ct_investment( $ct_b, 300000, false );
$ct_no    = bhela_bm_cert_preview( 'investment', $ct_draft );
ok( is_wp_error( $ct_no ) && 'not_active' === $ct_no->get_error_code(),
	'terms nobody has agreed to are not certified' );

echo "\n=== 10. the verification page proves it is genuine, and says nothing else ===\n";

$ct_num = bhela_bm_cert_data( $ct_v2 )['number'];
$ct_v   = bhela_bm_verify_lookup( $ct_num );
ok( $ct_v['found'] && 'valid' === $ct_v['status'], 'a current certificate verifies as valid' );
ok( $ct_v['number'] === $ct_num, 'by its own number' );
ok( 'ZZ Cert Rahim' !== $ct_v['name'], 'the investor\'s name is masked, not printed', $ct_v['name'] );
ok( false !== strpos( $ct_v['name'], '*' ), 'and the mask is visible', $ct_v['name'] );

$ct_vjson = wp_json_encode( $ct_v );
foreach ( array(
	'750000'        => 'the amount',
	'01700000001'   => 'the mobile number',
	'1990123456789' => 'the NID',
	'ZZ Cert Rahim' => 'the full name',
) as $ct_needle => $ct_what ) {
	ok( false === strpos( $ct_vjson, $ct_needle ), 'the verification result omits ' . $ct_what );
}

$ct_old_v = bhela_bm_verify_lookup( bhela_bm_cert_data( $ct_cert )['number'] );
ok( 'superseded' === $ct_old_v['status'], 'a replaced certificate says so rather than 404ing' );
ok( $ct_old_v['replaced_by'] === $ct_num, 'and names the version that replaced it' );

$ct_junk = bhela_bm_verify_lookup( 'BHELA-IC-1999-9999-V1' );
ok( ! $ct_junk['found'] && 'unknown' === $ct_junk['status'], 'an unknown number is simply not found' );

echo "\n=== 11. the print link, end to end ===\n";

$ct_url = bhela_bm_cert_url( $ct_v2 );
$ct_get = wp_remote_get( $ct_url, array( 'timeout' => 20 ) );
ok( ! is_wp_error( $ct_get ) && 200 === (int) wp_remote_retrieve_response_code( $ct_get ),
	'the link with the right key renders',
	is_wp_error( $ct_get ) ? $ct_get->get_error_message() : (string) wp_remote_retrieve_response_code( $ct_get ) );

$ct_body = is_wp_error( $ct_get ) ? '' : wp_remote_retrieve_body( $ct_get );
ok( false !== strpos( $ct_body, $ct_num ), 'the page carries its own number' );
ok( false !== strpos( $ct_body, 'NBR' ), 'and says it is not an NBR tax certificate' );
ok( false !== strpos( $ct_body, '<svg' ), 'the verification QR is drawn inline' );
ok( false === strpos( $ct_body, '1990123456789' ), 'the NID never reaches the page' );

$ct_bad = wp_remote_get(
	add_query_arg( array( 'bhela_cert' => (int) $ct_v2, 'key' => 'wrong' ), home_url( '/' ) ),
	array( 'timeout' => 20 )
);
ok( ! is_wp_error( $ct_bad ) && 403 === (int) wp_remote_retrieve_response_code( $ct_bad ),
	'a wrong key is refused',
	is_wp_error( $ct_bad ) ? $ct_bad->get_error_message() : (string) wp_remote_retrieve_response_code( $ct_bad ) );

echo "\n=== 12. a certificate from the previous model still renders ===\n";

// Four of these were already in the wild when BHELA moved from the share model to
// fixed-return investments, and the rewrite changed the snapshot's shape. A document
// that throws instead of rendering breaks 13.75 more completely than a wrong figure
// would, so the old shape keeps its own renderer. This fixture is a v1 snapshot.
$ct_old_id = wp_insert_post( array(
	'post_type'   => 'bhela_cert',
	'post_status' => 'publish',
	'post_title'  => 'BHL-PFT-2099-0001 — ZZ Legacy Holder',
) );
$GLOBALS['ct_made'][] = (int) $ct_old_id;
foreach ( array(
	'number'   => 'BHL-PFT-2099-0001',
	'type'     => 'profit',
	'investor' => $ct_a,
	'issued'   => '2026-09-20',
	'by'       => 1,
	'snapshot' => array(
		'type'         => 'profit',
		'type_label'   => 'সিজনভিত্তিক লাভ সনদ',
		'type_en'      => 'Season-wise Profit Certificate',
		'investor'     => $ct_a,
		'name'         => 'ZZ Legacy Holder',
		'shares'       => 25,
		'total_shares' => 125,
		'share_pct'    => 20,
		'from'         => '2026-05-01',
		'to'           => '2026-10-31',
		'season_label' => 'ZZ Legacy Season',
		'pot'          => array( 'distributable' => 265230 ),
		'entitlement'  => 37132,
		'declared'     => 0,
		'paid'         => 0,
		'adjustments'  => 0,
		'balance'      => 0,
	),
) as $ct_k => $ct_v ) {
	bhela_bm_val_meta_write( $ct_old_id, '_bhela_crt_' . $ct_k, $ct_v );
}

$ct_legacy = bhela_bm_cert_data( $ct_old_id );
ok( is_array( $ct_legacy ), 'an old certificate still reads' );
ok( 'certificate-legacy.php' === bhela_bm_cert_template( $ct_legacy ),
	'and is routed to its own renderer rather than the new one',
	bhela_bm_cert_template( $ct_legacy ) );
ok( 'certificate-profit.php' === bhela_bm_cert_template( bhela_bm_cert_data( $ct_pc ) ),
	'while a current one still uses the current template' );

$ct_legacy_get = wp_remote_get( bhela_bm_cert_url( $ct_old_id ), array( 'timeout' => 20 ) );
ok( ! is_wp_error( $ct_legacy_get ) && 200 === (int) wp_remote_retrieve_response_code( $ct_legacy_get ),
	'it renders rather than erroring',
	is_wp_error( $ct_legacy_get ) ? $ct_legacy_get->get_error_message() : (string) wp_remote_retrieve_response_code( $ct_legacy_get ) );
$ct_legacy_body = is_wp_error( $ct_legacy_get ) ? '' : wp_remote_retrieve_body( $ct_legacy_get );
ok( false !== strpos( $ct_legacy_body, '265,230' ), 'with the figures exactly as they were issued' );
ok( false !== strpos( $ct_legacy_body, 'BHL-PFT-2099-0001' ), 'under its original number' );

echo "\n=== 13. the capabilities are in two hands ===\n";

bhela_bm_install_roles();
ok( 13 === BHELA_BM_ROLES_VERSION, 'the roles version moved with the new caps', (string) BHELA_BM_ROLES_VERSION );
ok( get_role( 'bhela_investor_relations' )->has_cap( 'bhela_investor_capital' ),
	'Investor Relations records capital — it is register work' );
ok( ! get_role( 'bhela_investor_relations' )->has_cap( 'bhela_investor_cert' ),
	'but does not sign the certificate built from it' );
ok( get_role( 'bhela_manager' )->has_cap( 'bhela_investor_cert' ), 'a Manager signs' );
ok( ! get_role( 'bhela_investor' )->has_cap( 'bhela_investor_cert' ),
	'and an investor holds neither — the portal reads, it never issues' );

/* ---------- cleanup ---------- */
wp_set_current_user( 0 );
wp_set_current_user( 1 );
global $wpdb;
foreach ( $GLOBALS['ct_made'] as $ct_id ) {
	if ( 'bhela_investor' === get_post_type( $ct_id ) ) {
		foreach ( $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_bhela_led_investor' AND meta_value=%d",
			$ct_id
		) ) as $ct_led ) {
			bhela_test_delete( (int) $ct_led );
		}
	}
	bhela_test_delete( (int) $ct_id );
}
// Numbers are never reused, so a harness that did not restore this would burn part of
// the real series on every run — see §13.78.
update_option( 'bhela_bm_doc_seq', $ct_seq_was, false );

bhela_test_done();
