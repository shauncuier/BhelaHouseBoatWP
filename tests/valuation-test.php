<?php
/**
 * Valuation, capital appreciation, and the new-investor round.
 *
 * The arithmetic here is the owner's own, from the brief that specified the feature,
 * and it is asserted to the taka: ৳1.15 Cr over 115 shares is ৳1,00,000 a share;
 * ৳1.50 Cr is ৳1,30,435; ৳1.70 Cr is ৳1,47,826. A 10-share holding bought at
 * ৳10,00,000 is worth ৳14,78,260 at the last of those, a gain of ৳4,78,260.
 *
 * Every figure is a DELTA or an absolute the harness itself created (§13.38): the dev
 * site carries demo investors and a real distribution, and an assertion about the
 * database rather than the code breaks the moment somebody adds a record.
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );

/* ---------- scope ----------
 *
 * A valuation and a share issue are titled by the plugin from their own figures, not
 * from a fixture's name, so `bhela_test_isolate()`'s `post_title LIKE 'ZZ%'` cannot
 * see them and they are deliberately left out of it (§13.37). The consequence bit:
 * section 1 asserts what a site with NO approved valuation reads like, and the dev
 * site has had a real ৳1.70 Cr valuation approved on it since — so the harness was
 * measuring the database rather than the code, exactly the failure §13.38 and §13.66
 * are about.
 *
 * So this harness keeps a scope of its own. Every record it creates is registered on
 * `save_post`, and everything else is filtered out of both post types — which means
 * section 1 runs against genuinely nothing, on any site, however much real data it
 * carries.
 */
$GLOBALS['vt_own_ids'] = array();
$GLOBALS['vt_scope']   = true;

foreach ( array( 'bhela_valuation', 'bhela_share_issue' ) as $vt_type ) {
	add_action( "save_post_{$vt_type}", function ( $post_id ) {
		$GLOBALS['vt_own_ids'][] = (int) $post_id;
	} );
}

// get_posts() sets suppress_filters => true, which skips posts_where entirely — the
// plugin reads through get_posts(), so a posts_where filter alone silently does
// nothing. pre_get_posts fires either way. Same shape as the bootstrap's isolation.
add_action( 'pre_get_posts', function ( $query ) {
	if ( array_intersect( (array) $query->get( 'post_type' ), array( 'bhela_valuation', 'bhela_share_issue' ) ) ) {
		$query->set( 'suppress_filters', false );
	}
} );
add_filter( 'posts_where', function ( $where, $query ) {
	global $wpdb;
	if ( empty( $GLOBALS['vt_scope'] ) ) {
		return $where;
	}
	if ( ! array_intersect( (array) $query->get( 'post_type' ), array( 'bhela_valuation', 'bhela_share_issue' ) ) ) {
		return $where;
	}
	$ids = array_map( 'intval', (array) $GLOBALS['vt_own_ids'] );
	return $where . " AND {$wpdb->posts}.ID IN (" . ( $ids ? implode( ',', $ids ) : '0' ) . ')';
}, 10, 2 );

/* ---------- fixtures ---------- */

$vt_settings_was = get_option( 'bhela_bm_settings', array() );

/** Put the share structure in a known state — §13.32: a harness states what it asserts against. */
function vt_configure( $shares, $per_share, $initial ) {
	$s = bhela_bm_get_settings();
	$s['inv_total_shares']     = $shares;
	$s['inv_per_share']        = $per_share;
	$s['inv_total_investment'] = $initial;
	update_option( 'bhela_bm_settings', $s );
	bhela_bm_valuation_current( true );
}

/** A valuation, approved by somebody other than its author. */
function vt_valuation( $total, $date, $author, $approver ) {
	iv_as_user( $author );
	$id = bhela_bm_valuation_add( array( 'total' => $total, 'date' => $date, 'basis' => 'ZZ test' ) );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	iv_as_user( $approver );
	$ok = bhela_bm_valuation_approve( $id );
	return is_wp_error( $ok ) ? $ok : $id;
}

/** §13.15 — via 0 first, or the cached user keeps the old capabilities. */
function iv_as_user( $user_id ) {
	wp_set_current_user( 0 );
	clean_user_cache( $user_id );
	wp_set_current_user( $user_id );
	return $user_id;
}


/**
 * Move the share total once, from inside the commit.
 *
 * Hooked on the issue record's own `save_post`, which fires after the record is
 * inserted and BEFORE the commit re-reads inv_total_shares — exactly the window a
 * second concurrent commit would occupy. Fires once, so what is measured is the abort
 * and not the helper.
 */
function vt_race_once( $post_id ) {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	$raw  = get_option( 'bhela_bm_settings', array() );
	$raw['inv_total_shares'] = (int) $raw['inv_total_shares'] + 1;
	update_option( 'bhela_bm_settings', $raw );
}

bhela_bm_install_roles();
$vt_prep = wp_insert_user( array( 'user_login' => 'zz_val_prep', 'user_email' => 'zz_val_prep@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor_relations' ) );
$vt_appr = wp_insert_user( array( 'user_login' => 'zz_val_appr', 'user_email' => 'zz_val_appr@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_manager' ) );

vt_configure( 115, 100000, 11500000 );

$vt_a = wp_insert_post( array( 'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => 'ZZ Val A' ) );
update_post_meta( $vt_a, '_bhela_inv_shares', 10 );
update_post_meta( $vt_a, '_bhela_inv_amount', 1000000 );
update_post_meta( $vt_a, '_bhela_inv_status', 'active' );

echo "=== 1. with no valuation approved, nothing changes ===\n";
// The assertion that lets this ship: a site that never records a valuation reads
// exactly as it did before the feature existed.
ok( 100000 === bhela_bm_share_value(), 'the share value falls back to the original issue price', (string) bhela_bm_share_value() );
ok( null === bhela_bm_valuation_current(), 'and there is no valuation in force' );

$vt_h0 = bhela_bm_investor_holding( $vt_a );
ok( ! $vt_h0['valued'], 'a holding reports that it rests on the fallback' );
ok( 1000000 === $vt_h0['basis'], 'the cost basis is what they paid in', (string) $vt_h0['basis'] );
ok( 1000000 === $vt_h0['holding'], 'holding value equals the basis at the issue price', (string) $vt_h0['holding'] );
ok( 0 === $vt_h0['appreciation'], 'so appreciation is zero rather than invented' );

echo "\n=== 2. the owner's own arithmetic, to the taka ===\n";
$vt_v150 = vt_valuation( 15000000, '2026-06-30', $vt_prep, $vt_appr );
ok( ! is_wp_error( $vt_v150 ), 'a valuation is recorded and approved',
	is_wp_error( $vt_v150 ) ? $vt_v150->get_error_code() : 'ok' );
bhela_bm_valuation_current( true );
ok( 130435 === bhela_bm_share_value(), '৳1.50 Cr ÷ 115 = ৳1,30,435', (string) bhela_bm_share_value() );

$vt_v170 = vt_valuation( 17000000, '2026-09-30', $vt_prep, $vt_appr );
bhela_bm_valuation_current( true );
ok( 147826 === bhela_bm_share_value(), '৳1.70 Cr ÷ 115 = ৳1,47,826', (string) bhela_bm_share_value() );

$vt_h = bhela_bm_investor_holding( $vt_a );
ok( $vt_h['valued'], 'the holding now rests on a real valuation' );
ok( 10 === $vt_h['shares'], 'the share count did NOT change as the business grew' );
ok( 1478260 === $vt_h['holding'], '10 shares × ৳1,47,826 = ৳14,78,260', (string) $vt_h['holding'] );
ok( 478260 === $vt_h['appreciation'], 'a gain of ৳4,78,260 on a ৳10,00,000 basis', (string) $vt_h['appreciation'] );
ok( 47.83 === $vt_h['appr_pct'], 'which is +47.83%', (string) $vt_h['appr_pct'] );
ok( 8.695652 === $vt_h['pct'], 'and ownership is unchanged at 10 of 115', (string) $vt_h['pct'] );

echo "\n=== 3. growth is measured against the previous APPROVED valuation ===\n";
$vt_hist = bhela_bm_valuation_history();
$vt_by_id = array();
foreach ( $vt_hist as $r ) {
	$vt_by_id[ $r['id'] ] = $r;
}
ok( 13.33 === $vt_by_id[ $vt_v170 ]['growth'], '৳1.50 Cr → ৳1.70 Cr is +13.33%', (string) $vt_by_id[ $vt_v170 ]['growth'] );
ok( 30.43 === $vt_by_id[ $vt_v150 ]['growth'], 'and the first one grew 30.43% over the initial ৳1.15 Cr',
	(string) $vt_by_id[ $vt_v150 ]['growth'] );
ok( $vt_v150 === $vt_by_id[ $vt_v170 ]['prev_id'], 'each row names the valuation it is measured against' );

echo "\n=== 4. a draft decides nothing, and reaches no investor ===\n";
iv_as_user( $vt_prep );
$vt_draft = bhela_bm_valuation_add( array( 'total' => 99000000, 'date' => '2026-10-31', 'basis' => 'ZZ draft' ) );
ok( ! is_wp_error( $vt_draft ), 'a draft is recorded' );
bhela_bm_valuation_current( true );
ok( 147826 === bhela_bm_share_value(), 'and the share value does not move', (string) bhela_bm_share_value() );
ok( $vt_v170 === bhela_bm_valuation_current()['id'], 'the approved one is still in force' );

// Two separate refusals, and the order matters. Investor Relations does not hold the
// approve capability at all, so it is stopped before the second-signature rule is even
// reached.
iv_as_user( $vt_prep );
$vt_self = bhela_bm_valuation_approve( $vt_draft );
ok( is_wp_error( $vt_self ) && 'denied' === $vt_self->get_error_code(),
	'Investor Relations cannot approve a valuation at all',
	is_wp_error( $vt_self ) ? $vt_self->get_error_code() : 'ALLOWED' );

// And somebody who holds BOTH capabilities still cannot approve their own figure —
// the administrator, who is the only account that can reach this rule, and therefore
// the only one on whom it can be tested. (A Manager approves but does not record, and
// Investor Relations records but does not approve: the roles already keep the two
// hands apart, and this is the belt under that.)
iv_as_user( 1 );
$vt_own = bhela_bm_valuation_add( array( 'total' => 16000000, 'date' => '2026-11-30', 'basis' => 'ZZ own' ) );
ok( ! is_wp_error( $vt_own ), 'an approver can record a valuation' );
$vt_own_appr = bhela_bm_valuation_approve( $vt_own );
ok( is_wp_error( $vt_own_appr ) && 'same_person' === $vt_own_appr->get_error_code(),
	'but not approve the one they recorded themselves',
	is_wp_error( $vt_own_appr ) ? $vt_own_appr->get_error_code() : 'ALLOWED' );

// Investor Relations raises valuations but does not release them.
$vt_rel = get_role( 'bhela_investor_relations' );
ok( $vt_rel->has_cap( 'bhela_investor_valuation' ), 'Investor Relations can record a valuation' );
ok( ! $vt_rel->has_cap( 'bhela_investor_approve' ), 'but cannot approve one' );

echo "\n=== 5. an approved valuation is locked ===\n";
iv_as_user( 1 );
$vt_total_was = (int) get_post_meta( $vt_v170, '_bhela_val_total', true );
update_post_meta( $vt_v170, '_bhela_val_total', 99999999 );
ok( $vt_total_was === (int) get_post_meta( $vt_v170, '_bhela_val_total', true ), 'its total cannot be rewritten',
	(string) get_post_meta( $vt_v170, '_bhela_val_total', true ) );

// add_post_meta on an ABSENT key is the probe that catches §13.49's gap.
bhela_bm_val_writing( true );
delete_post_meta( $vt_v170, '_bhela_val_doc' );
bhela_bm_val_writing( false );
add_post_meta( $vt_v170, '_bhela_val_doc', 'https://zz.example/forged' );
ok( '' === get_post_meta( $vt_v170, '_bhela_val_doc', true ), 'nor a key added while it was absent',
	var_export( get_post_meta( $vt_v170, '_bhela_val_doc', true ), true ) );

delete_post_meta_by_key( '_bhela_val_total' );
ok( $vt_total_was === (int) get_post_meta( $vt_v170, '_bhela_val_total', true ),
	'a blanket delete across every post is refused too (§13.55)' );

wp_trash_post( $vt_v170 );
ok( 'trash' !== get_post_status( $vt_v170 ), 'it cannot be trashed' );
wp_delete_post( $vt_v170, true );
ok( 'bhela_valuation' === get_post_type( $vt_v170 ), 'nor hard-deleted' );

// A draft is NOT locked — it is somebody still working.
update_post_meta( $vt_draft, '_bhela_val_total', 88000000 );
ok( 88000000 === (int) get_post_meta( $vt_draft, '_bhela_val_total', true ), 'while a draft stays editable',
	(string) get_post_meta( $vt_draft, '_bhela_val_total', true ) );

echo "\n=== 6. the round: whole shares, and honest dilution ===\n";
// ৳10,00,000 at ৳1,47,826 is 6.765 shares. A share cannot be split, so the screen
// offers 6 or 7 rather than rounding behind the operator's back.
$vt_p = bhela_bm_share_issue_preview( 0, 1000000 );
ok( 6.765 === $vt_p['exact'], '৳10,00,000 buys 6.765 shares', (string) $vt_p['exact'] );
ok( 6 === $vt_p['suggest_down'] && 7 === $vt_p['suggest_up'], 'and the two whole numbers either side are offered' );

$vt_p7 = bhela_bm_share_issue_preview( 7 );
ok( 1034782 === $vt_p7['amount'], '7 shares costs ৳10,34,782', (string) $vt_p7['amount'] );
ok( 17000000 === $vt_p7['pre_money'], 'pre-money is the approved valuation', (string) $vt_p7['pre_money'] );
ok( 18034782 === $vt_p7['post_money'], 'post-money is pre-money plus what was raised', (string) $vt_p7['post_money'] );
ok( 122 === $vt_p7['after'], '115 + 7 = 122 shares', (string) $vt_p7['after'] );
ok( $vt_p7['post_money'] === $vt_p7['pre_money'] + $vt_p7['amount'], 'and the two agree to the taka' );

// What it does to a holder, before it happens.
$vt_eff = bhela_bm_share_issue_effect( $vt_p7 );
$vt_mine = null;
foreach ( $vt_eff as $row ) {
	if ( (int) $row['investor'] === (int) $vt_a ) {
		$vt_mine = $row;
	}
}
ok( null !== $vt_mine, 'the existing holder appears in the effect table' );
if ( $vt_mine ) {
	ok( 8.6957 === $vt_mine['pct_before'], 'ownership before is 8.6957%', (string) $vt_mine['pct_before'] );
	ok( 8.1967 === $vt_mine['pct_after'], 'and after is 8.1967% — that is the dilution', (string) $vt_mine['pct_after'] );
	ok( 1478260 === $vt_mine['value'], 'while the holding VALUE is unchanged', (string) $vt_mine['value'] );
}

// The drift reader counts in SQL (§13.66) so a capped listing cannot understate the
// money — which also means it is the ONE figure in this harness that sees the whole
// site, scope filter or not. Baseline it before committing, and assert the change.
$vt_drift0 = bhela_bm_share_issue_drift();

echo "\n=== 7. committing the round ===\n";
$vt_b = wp_insert_post( array( 'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => 'ZZ Val B' ) );
update_post_meta( $vt_b, '_bhela_inv_status', 'active' );

// It takes the APPROVE capability: issuing shares moves everybody's percentage.
iv_as_user( $vt_prep );
$vt_denied = bhela_bm_share_issue_commit( array( 'investor' => $vt_b, 'shares' => 7 ) );
ok( is_wp_error( $vt_denied ) && 'denied' === $vt_denied->get_error_code(),
	'Investor Relations cannot issue shares', is_wp_error( $vt_denied ) ? $vt_denied->get_error_code() : 'ALLOWED' );

iv_as_user( $vt_appr );
$vt_issue = bhela_bm_share_issue_commit( array( 'investor' => $vt_b, 'shares' => 7, 'date' => '2026-10-01', 'note' => 'ZZ round' ) );
ok( ! is_wp_error( $vt_issue ), 'a manager can', is_wp_error( $vt_issue ) ? $vt_issue->get_error_message() : 'ok' );

$vt_cfg = bhela_bm_share_config();
ok( 122 === (int) $vt_cfg['total_shares'], 'the configured share total moved 115 → 122', (string) $vt_cfg['total_shares'] );
ok( 7 === bhela_bm_investor_shares( $vt_b ), 'the new investor holds 7 shares' );
ok( 1034782 === bhela_bm_investor_amount( $vt_b ), 'and their cost basis is what they paid', (string) bhela_bm_investor_amount( $vt_b ) );

// The existing holder: percentage down, share count and value untouched.
$vt_h2 = bhela_bm_investor_holding( $vt_a );
ok( 10 === $vt_h2['shares'], 'the existing holder still holds 10 shares' );
ok( 8.196721 === $vt_h2['pct'], 'their ownership fell to 8.196721%', (string) $vt_h2['pct'] );
ok( 1478260 === $vt_h2['holding'], 'and their holding value did not move at all', (string) $vt_h2['holding'] );
ok( 478260 === $vt_h2['appreciation'], 'nor did their appreciation' );

// The record is immutable.
update_post_meta( $vt_issue, '_bhela_iss_shares', 999 );
ok( 7 === (int) get_post_meta( $vt_issue, '_bhela_iss_shares', true ), 'a committed issue cannot be edited',
	(string) get_post_meta( $vt_issue, '_bhela_iss_shares', true ) );
wp_delete_post( $vt_issue, true );
ok( 'bhela_share_issue' === get_post_type( $vt_issue ), 'nor deleted' );

// And the valuation it was priced from can no longer be reopened.
iv_as_user( $vt_appr );
$vt_reopen = bhela_bm_valuation_reopen( $vt_v170, 'ZZ nope' );
ok( is_wp_error( $vt_reopen ) && 'in_use' === $vt_reopen->get_error_code(),
	'a valuation a share issue was priced from is frozen',
	is_wp_error( $vt_reopen ) ? $vt_reopen->get_error_code() : 'ALLOWED' );

echo "\n=== 8. drift is reported, never corrected ===\n";
// DELTAS, not absolutes — §13.38, and §13.66 for this function specifically.
// bhela_bm_share_issue_drift() counts and sums in SQL so a capped listing cannot
// understate the money, and raw SQL never sees `posts_where`: it reads every round the
// site has ever run, including real ones an owner committed in wp-admin. Asserting
// `expected === configured` here therefore breaks the first time somebody uses the
// feature for real, which is exactly what happened — a genuine 10-share round on the
// dev site made `expected` 132 against a configured 122, and the harness called its
// own fixture arithmetic a bug.
$vt_drift = bhela_bm_share_issue_drift();
ok( 7 === $vt_drift['issued'] - $vt_drift0['issued'],
	'the committed round added exactly its 7 shares to the issue history',
	( $vt_drift['issued'] - $vt_drift0['issued'] ) . ' of 7' );
ok( 7 === $vt_drift['expected'] - $vt_drift0['expected'],
	'and moved the total the history expects by the same 7' );
ok( 1 === $vt_drift['rounds'] - $vt_drift0['rounds'], 'one round, counted once' );

// Now put the configured total where the history says it should be, and the reader
// should report no discrepancy at all. Setting it from `expected` rather than from a
// literal is the whole point: the harness cannot know what rounds a real site has run.
$vt_expected_was = $vt_drift['expected'];
vt_configure( $vt_expected_was, 100000, 11500000 );
$vt_drift1 = bhela_bm_share_issue_drift();
ok( ! $vt_drift1['drift'], 'a configured total matching the history reports no drift' );
ok( 0 === $vt_drift1['gap'], 'with no gap', (string) $vt_drift1['gap'] );
vt_configure( $vt_expected_was + 78, 100000, 11500000 );   // somebody edits by hand
$vt_drift2 = bhela_bm_share_issue_drift();
ok( $vt_drift2['drift'], 'a hand-edited total is reported as a discrepancy' );
ok( 78 === $vt_drift2['gap'], 'with the gap named', (string) $vt_drift2['gap'] );
ok( $vt_expected_was === $vt_drift2['expected'], 'and the figure the history expects is unmoved' );
ok( $vt_expected_was + 78 === (int) bhela_bm_share_config()['total_shares'], 'and nothing is silently corrected' );
vt_configure( $vt_expected_was, 100000, 11500000 );

echo "\n=== 9. capital value is never folded into cash ===\n";
$vt_roi = bhela_bm_investor_roi( $vt_a );
ok( 1000000 === $vt_roi['investment'], 'ROI still reports what was paid in, not the holding value',
	(string) $vt_roi['investment'] );
ok( 0 === $vt_roi['received'], 'appreciation is not counted as money received', (string) $vt_roi['received'] );
ok( 0.0 === $vt_roi['roi'], 'nor does it inflate ROI', (string) $vt_roi['roi'] );

$vt_dash = bhela_bm_investor_dash_data();
ok( isset( $vt_dash['capital'] ), 'the dashboard carries a capital block' );
ok( $vt_dash['capital']['holding'] !== $vt_dash['received'], 'kept apart from what has been paid out' );
$vt_cf = bhela_bm_cashflow( '2026-09-01', '2026-10-31' );
$vt_cf_labels = array_merge( wp_list_pluck( $vt_cf['in'], 'label' ), wp_list_pluck( $vt_cf['out'], 'label' ) );
foreach ( $vt_cf_labels as $vt_l ) {
	ok( false === stripos( $vt_l, 'appreciation' ) && false === stripos( $vt_l, 'valuation' ),
		"cash flow carries no valuation line ($vt_l)" );
}

echo "\n=== 10. the export neutralises its text cells ===\n";
$vt_src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/valuation-admin.php' );
ok( false !== strpos( $vt_src, 'bhela_bm_csv_cell' ), 'the valuation CSV neutralises its text cells' );
ok( "'=cmd|' /C calc'!A0" === bhela_bm_csv_cell( '=cmd|\' /C calc\'!A0' ), 'and the neutraliser still works' );

// All three lock hooks, plus the two §13.55 routes.
$vt_core = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/valuation-core.php' );
foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata', 'delete_post_metadata_by_mid' ) as $vt_hook ) {
	ok( false !== strpos( $vt_core, "'$vt_hook'" ), "valuation-core.php filters $vt_hook" );
}
ok( preg_match( '/delete_post_metadata.{0,80}?,\s*5\s*\)/', $vt_core ) > 0,
	'and registers the delete filter for five arguments' );


echo "\n=== 11. the fixes from the code review ===\n";

// --- the drift figures come from SQL, not from summing a capped listing ---
// bhela_bm_share_issues() is capped, so summing it would understate the shares issued
// past the cap and report drift on a register that is perfectly correct — the same
// failure bhela_bm_payreq_pending_total() had.
$vt_iss_src = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/share-issue.php' );
ok( false !== strpos( $vt_iss_src, 'SELECT COUNT(*)' ), 'drift counts in SQL rather than over a listing' );
ok( false === strpos( $vt_iss_src, "'posts_per_page' => -1" ), 'and no listing here is unbounded' );

// Drive it: the SQL must agree with the listing while the listing is not truncated.
// This is the one assertion that compares the two readings, so it has to see the same
// data — the harness's own scope filter narrows the listing and not the raw SQL, which
// would make them disagree for a reason that has nothing to do with the cap.
$GLOBALS['vt_scope'] = false;
$vt_d = bhela_bm_share_issue_drift();
$vt_manual = 0;
foreach ( bhela_bm_share_issues() as $vt_r ) {
	$vt_manual += $vt_r['shares'];
}
$GLOBALS['vt_scope'] = true;
ok( $vt_manual === $vt_d['issued'], 'the SQL sum agrees with the rows below the cap',
	$vt_manual . ' vs ' . $vt_d['issued'] );
ok( $vt_d['rounds'] >= 1, 'and the round is counted', (string) $vt_d['rounds'] );

// --- a second issue cannot race the first ---
// The commit re-reads inv_total_shares immediately before writing and aborts if it
// moved. Reproduced by moving it under the caller between preview and commit, which is
// what a concurrent commit would have done.
iv_as_user( $vt_appr );
$vt_c = wp_insert_post( array( 'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => 'ZZ Val C' ) );
update_post_meta( $vt_c, '_bhela_inv_status', 'active' );

$vt_before_race = (int) bhela_bm_share_config()['total_shares'];
add_action( 'save_post_bhela_share_issue', 'vt_race_once', 1 );
$vt_raced = bhela_bm_share_issue_commit( array( 'investor' => $vt_c, 'shares' => 2, 'date' => '2026-10-05' ) );
remove_action( 'save_post_bhela_share_issue', 'vt_race_once', 1 );

ok( is_wp_error( $vt_raced ), 'a round whose share total moved underneath it is refused',
	is_wp_error( $vt_raced ) ? $vt_raced->get_error_code() : 'ALLOWED' );
ok( 0 === count( get_posts( array(
	'post_type' => 'bhela_share_issue', 'post_status' => 'publish', 'fields' => 'ids',
	'posts_per_page' => 5, 'meta_key' => '_bhela_iss_investor', 'meta_value' => $vt_c,
) ) ), 'and leaves no half-written issue record behind it' );
ok( 0 === bhela_bm_investor_shares( $vt_c ), 'nor credits the investor with shares' );

// Put the total back where the race helper left it.
$vt_fix = get_option( 'bhela_bm_settings', array() );
$vt_fix['inv_total_shares'] = $vt_before_race;
update_option( 'bhela_bm_settings', $vt_fix );

// A valuation is PRE-money. §7 issued shares against this one, so it no longer
// describes the business, and the screen must say so rather than reconcile against a
// share count that has moved — which is what produced a nonsense "rounding" of minus
// ten lakh before this was fixed.
$vt_tot = bhela_bm_holding_totals();
ok( $vt_tot['stale'], 'a valuation with shares issued after it is reported as out of date' );
// Against the valuation's OWN snapshot, not against a literal 7: on a site that has
// run real rounds the configured total is higher than this harness's fixtures, and the
// figure the screen has to print is the gap the valuation is actually behind by.
$vt_since = (int) bhela_bm_share_config()['total_shares'] - 115;
ok( $vt_since === $vt_tot['issued_since'],
	'naming how many shares were issued since it was signed off',
	$vt_tot['issued_since'] . ' of ' . $vt_since );
ok( 0 === $vt_tot['rounding'] && 0 === $vt_tot['unissued'],
	'and no reconciliation is attempted against a total that has moved' );

// Record the post-money valuation and the reconciliation comes back.
$vt_post_total = $vt_tot['held'] * $vt_tot['share_value']
	+ ( (int) bhela_bm_share_config()['total_shares'] - $vt_tot['held'] ) * $vt_tot['share_value'];
$vt_post = vt_valuation( $vt_post_total, '2026-12-31', $vt_prep, $vt_appr );
ok( ! is_wp_error( $vt_post ), 'a post-money valuation is recorded',
	is_wp_error( $vt_post ) ? $vt_post->get_error_code() : 'ok' );
bhela_bm_valuation_current( true );
$vt_tot2 = bhela_bm_holding_totals();
ok( ! $vt_tot2['stale'], 'which is no longer stale' );
ok(
	$vt_tot2['total'] === $vt_tot2['holding'] + $vt_tot2['unissued'] + $vt_tot2['rounding'],
	'issued + unissued + rounding equals the valuation exactly',
	$vt_tot2['holding'] . ' + ' . $vt_tot2['unissued'] . ' + ' . $vt_tot2['rounding'] . ' vs ' . $vt_tot2['total']
);
ok( abs( $vt_tot2['rounding'] ) <= (int) bhela_bm_share_config()['total_shares'],
	'and the rounding remainder is under one taka per share', (string) $vt_tot2['rounding'] );

// --- the reset hands back the fresh value, not null ---
ok( null !== bhela_bm_valuation_current( true ), 'resetting the cache returns the refreshed valuation' );

// --- the settings write touches only the key it owns ---
// bhela_bm_get_settings() merges the defaults, so writing that back would freeze every
// current default as an explicit stored value.
ok( false !== strpos( $vt_iss_src, "get_option( 'bhela_bm_settings', array() )" ),
	'the commit writes back the stored option, not the merged defaults' );

wp_delete_post( $vt_c, true );

/* ---------- cleanup ---------- */
foreach ( array( $vt_v150, $vt_v170, $vt_draft, $vt_own, $vt_post, $vt_issue ) as $vt_z ) {
	if ( $vt_z && ! is_wp_error( $vt_z ) ) {
		bhela_test_delete( $vt_z );
	}
}
wp_delete_post( $vt_a, true );
wp_delete_post( $vt_b, true );
wp_set_current_user( 0 );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vt_prep );
wp_delete_user( $vt_appr );
update_option( 'bhela_bm_settings', $vt_settings_was );

bhela_test_done();
