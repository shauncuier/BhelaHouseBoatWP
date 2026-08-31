<?php
/**
 * Investor shares, profit distribution and the ledger.
 *
 * This is the money that gets paid to named people on the strength of what the
 * software says, so the assertions here are about arithmetic that must not drift:
 * the parts summing to the whole, a locked month staying locked, and one investor
 * never being able to read another.
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules(
	'ui', 'roles', 'log', 'audit', 'distribution-core', 'investors',
	'investor-ledger', 'costs', 'expenses', 'salary', 'agencies', 'statement', 'distribution',
	'investor-portal'
);

wp_set_current_user( 1 );
bhela_bm_install_roles();
wp_set_current_user( 0 );
wp_set_current_user( 1 );

$iv_month = '2026-07';
$iv_made  = array();
$iv_rows  = array();

/** Clear anything an earlier pass committed — a run survives deletion by design. */
function iv_reset( $month ) {
	$idx = get_option( 'bhela_bm_dist_runs', array() );
	if ( is_array( $idx ) && ! empty( $idx[ $month ] ) ) {
		bhela_test_delete( (int) $idx[ $month ] );
		unset( $idx[ $month ] );
		update_option( 'bhela_bm_dist_runs', $idx, false );
	}
	foreach ( get_posts( array(
		'post_type' => 'bhela_inv_ledger', 'post_status' => 'publish',
		'posts_per_page' => -1, 'fields' => 'ids',
	) ) as $z ) {
		bhela_test_delete( $z );
	}
}
iv_reset( $iv_month );

function iv_investor( $name, $shares ) {
	$id = wp_insert_post( array(
		'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => $name,
	) );
	update_post_meta( $id, '_bhela_inv_shares', $shares );
	update_post_meta( $id, '_bhela_inv_amount', $shares * 100000 );
	update_post_meta( $id, '_bhela_inv_status', 'active' );
	return (int) $id;
}

echo "\n=== 1. a share is worth the same to everyone ===\n";
// Percentages are measured against the CONFIGURED total, not the issued one. With
// 35 of 115 issued, three holders own 30% of the boat between them — not 100% of it.
$iv_a = iv_investor( 'ZZ Inv A', 10 );
$iv_b = iv_investor( 'ZZ Inv B', 5 );
$iv_c = iv_investor( 'ZZ Inv C', 20 );
$iv_made = array( $iv_a, $iv_b, $iv_c );

ok( 8.695652 === bhela_bm_investor_share_pct( $iv_a ), '10 of 115 shares is 8.695652%', (string) bhela_bm_investor_share_pct( $iv_a ) );
ok( 17.391304 === bhela_bm_investor_share_pct( $iv_c ), '20 of 115 shares is 17.391304%', (string) bhela_bm_investor_share_pct( $iv_c ) );
ok( 1000000 === bhela_bm_investor_amount( $iv_a ), 'and 10 shares is a ৳10,00,000 investment' );

$iv_t = bhela_bm_share_totals();
ok( 35 === $iv_t['issued'] && 115 === $iv_t['configured'], 'issued and configured are both reported', $iv_t['issued'] . '/' . $iv_t['configured'] );
ok( -80 === $iv_t['gap'] && $iv_t['under'] && ! $iv_t['over'], 'the shortfall is reported, not silently absorbed', (string) $iv_t['gap'] );

echo "\n=== 2. the split loses nothing, ever ===\n";
// Round 115 holdings independently and the total lands a few taka off the pool. Those
// taka have to go somewhere; a ledger that loses ৳7 a month stops reconciling inside
// a season and nobody notices until they add up a year.
$iv_all = array();
for ( $i = 1; $i <= 115; $i++ ) {
	$iv_all[ $i ] = 1;
}
$iv_bad = array();
foreach ( array( 63000, 100000, 1, 114, 999983, 7 ) as $iv_pot ) {
	$sum = array_sum( bhela_bm_split_by_shares( $iv_pot, $iv_all, 115 ) );
	if ( $sum !== $iv_pot ) {
		$iv_bad[] = $iv_pot . '→' . $sum;
	}
}
ok( ! $iv_bad, 'a fully issued pot is distributed to the last taka, at every size', implode( ' ', $iv_bad ) );
ok( 0 === array_sum( bhela_bm_split_by_shares( 0, $iv_all, 115 ) ), 'nothing to split distributes nothing' );

// Unissued shares keep their share of the pool rather than inflating the holders.
$iv_part = bhela_bm_split_by_shares( 189000, array( $iv_a => 10, $iv_b => 5, $iv_c => 20 ), 115 );
ok( 57521 === array_sum( $iv_part ), '35 of 115 shares take 35/115 of the pool, not all of it', (string) array_sum( $iv_part ) );
ok( 32869 === $iv_part[ $iv_c ] && 16435 === $iv_part[ $iv_a ] && 8217 === $iv_part[ $iv_b ],
	'and each holder gets exactly what their shares are worth' );

$iv_x = bhela_bm_split_by_shares( 63000, $iv_all, 115 );
ok( $iv_x === bhela_bm_split_by_shares( 63000, $iv_all, 115 ), 'the same inputs always give the same rounding' );
ok( ! array_filter( $iv_x, function ( $v ) { return $v < 0; } ), 'and nobody is ever allocated a negative amount' );

echo "\n=== 3. an unapproved cost sheet pays nobody ===\n";
// The existing prepare → check → approve chain is the gate on investor money. No
// second approval workflow was invented; this asserts the first one still holds.
$iv_sheet = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ investor sheet' ) );
update_post_meta( $iv_sheet, '_bhela_cost_trip_date', $iv_month . '-05' );
update_post_meta( $iv_sheet, '_bhela_cost_earnings', 500000 );
update_post_meta( $iv_sheet, '_bhela_cost_total', 200000 );
update_post_meta( $iv_sheet, '_bhela_cost_status', 'draft' );
ok( 0 === bhela_bm_dist_preview( $iv_month )['gross'], 'a draft month has nothing to distribute' );
ok( is_wp_error( bhela_bm_dist_commit( $iv_month ) ), 'and committing it is refused' );

update_post_meta( $iv_sheet, '_bhela_cost_status', 'approved' );
$iv_p = bhela_bm_dist_preview( $iv_month );
ok( 300000 === $iv_p['gross'], 'approving it makes ৳3,00,000 distributable', (string) $iv_p['gross'] );

echo "\n=== 4. the parts equal the whole ===\n";
ok( 30000 === $iv_p['reserve'] && 270000 === $iv_p['distributable'], 'reserve 10% comes off first' );
ok( 189000 === $iv_p['investor'] && 81000 === $iv_p['management'], 'then 70/30 across investors and management' );
ok( $iv_p['reserve'] + $iv_p['distributable'] === $iv_p['gross'], 'reserve + distributable = gross, to the taka' );
ok( $iv_p['investor'] + $iv_p['management'] === $iv_p['distributable'], 'investor + management = distributable, to the taka' );
ok( 131479 === $iv_p['unallocated'], 'and the unissued shares’ portion is reported as unallocated', (string) $iv_p['unallocated'] );

echo "\n=== 5. a committed month stays committed ===\n";
$iv_run = bhela_bm_dist_commit( $iv_month );
ok( ! is_wp_error( $iv_run ), 'the month commits', is_wp_error( $iv_run ) ? $iv_run->get_error_message() : (string) $iv_run );

$iv_again = bhela_bm_dist_commit( $iv_month );
ok( is_wp_error( $iv_again ) && 'already' === $iv_again->get_error_code(), 'a second run is refused — double paying is the failure mode' );

// Refused for administrators too. Reopening has to be a deliberate reversal, because
// "I deleted it" leaves no record of why the figures changed.
ok( ! wp_delete_post( $iv_run, true ) && get_post( $iv_run ), 'and the run cannot be deleted, even by an administrator' );
$iv_before = get_post_meta( $iv_run, '_bhela_dist_investor', true );
update_post_meta( $iv_run, '_bhela_dist_investor', 1 );
ok( $iv_before === get_post_meta( $iv_run, '_bhela_dist_investor', true ), 'nor can its figures be edited behind the guard' );

echo "\n=== 6. the balance is replayed, never stored ===\n";
$iv_pos = bhela_bm_investor_position( $iv_a );
ok( 16435 === $iv_pos['profit'] && 16435 === $iv_pos['outstanding'], 'declared profit is owed in full until paid' );

bhela_bm_ledger_add( array( 'investor' => $iv_a, 'type' => 'advance', 'amount' => 5000, 'date' => $iv_month . '-02', 'note' => 'ZZ advance' ) );
$iv_pos = bhela_bm_investor_position( $iv_a );
ok( 5000 === $iv_pos['received'] && 11435 === $iv_pos['outstanding'],
	'an advance needs no special arithmetic — it is a payment made early', (string) $iv_pos['outstanding'] );

$iv_pay = bhela_bm_ledger_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 2000, 'date' => $iv_month . '-20', 'note' => 'ZZ payment' ) );
$iv_pos = bhela_bm_investor_position( $iv_a );
ok( 7000 === $iv_pos['received'] && 9435 === $iv_pos['outstanding'], 'and a payment reduces what is left' );

$iv_led = bhela_bm_investor_ledger( $iv_a );
ok( $iv_led['closing'] === $iv_pos['outstanding'], 'the ledger and the position agree, because both replay the same rows' );

echo "\n=== 7. a mistake is reversed, not erased ===\n";
ok( is_wp_error( bhela_bm_ledger_reverse( $iv_pay, '' ) ), 'a reversal without a reason is refused' );
$iv_rev = bhela_bm_ledger_reverse( $iv_pay, 'ZZ entered twice' );
ok( ! is_wp_error( $iv_rev ), 'a reversal with one is accepted' );
ok( get_post( $iv_pay ), 'the original row is still there — the trail shows the error AND the fix' );

$iv_pos = bhela_bm_investor_position( $iv_a );
ok( 11435 === $iv_pos['outstanding'], 'the balance goes back to where it was', (string) $iv_pos['outstanding'] );
// ROI is computed from money received. A reversed payment that still counted would
// leave the investor's return permanently overstated.
ok( 5000 === $iv_pos['received'], 'and the reversed payment stops counting as received', (string) $iv_pos['received'] );
ok( is_wp_error( bhela_bm_ledger_reverse( $iv_pay, 'again' ) ), 'reversing the same row twice is refused' );

echo "\n=== 8. ROI reports money received, not money promised ===\n";
$iv_roi = bhela_bm_investor_roi( $iv_a );
ok( 1000000 === $iv_roi['investment'], 'against the amount invested' );
ok( 0.5 === $iv_roi['roi'], '৳5,000 received on ৳10,00,000 is 0.5%', (string) $iv_roi['roi'] );
ok( $iv_roi['roi_declared'] > $iv_roi['roi'], 'declared ROI is reported separately, and is higher while money is outstanding' );

echo "\n=== 9. one investor can never read another ===\n";
// The portal scopes every query by the viewer's own record. This asserts the data
// layer keeps investors apart even when asked directly for someone else's id.
$iv_a_led = bhela_bm_investor_ledger( $iv_a );
$iv_b_led = bhela_bm_investor_ledger( $iv_b );
$iv_leak  = array();
foreach ( $iv_a_led['rows'] as $r ) {
	if ( (int) $r['investor'] !== $iv_a ) {
		$iv_leak[] = $r['id'];
	}
}
ok( ! $iv_leak, 'a ledger contains only its own investor’s rows', implode( ',', $iv_leak ) );
ok( bhela_bm_investor_position( $iv_b )['received'] !== $iv_pos['received'] || 0 === bhela_bm_investor_position( $iv_b )['received'],
	'and B’s position is B’s, not A’s' );

$iv_role = get_role( 'bhela_investor' );
ok( $iv_role && ! $iv_role->has_cap( 'bhela_investors_view' ), 'the investor role cannot view the investor register' );
ok( $iv_role && ! $iv_role->has_cap( 'edit_posts' ) && ! $iv_role->has_cap( 'bhela_view_statement' ),
	'and holds no admin capability at all — the portal checks the record, not a role' );

echo "\n=== 10. every movement is in the audit trail ===\n";
global $wpdb;
$iv_audit = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . bhela_bm_audit_table() . ' WHERE channel = %s',
	'investor'
) );
ok( $iv_audit >= 5, 'the distribution and every ledger row are recorded', (string) $iv_audit );

echo "\n=== 11. the portal shows one investor their own position, and nothing else ===\n";
// This is the assertion that matters. Every role before this one belonged to somebody
// who works for BHELA; an investor is an outsider with a real login and a financial
// interest in figures they must not be able to edit or compare with anyone else's.
$iv_ua = wp_insert_user( array( 'user_login' => 'zz_inv_a', 'user_email' => 'zz_inv_a@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor' ) );
$iv_ub = wp_insert_user( array( 'user_login' => 'zz_inv_b', 'user_email' => 'zz_inv_b@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor' ) );
update_post_meta( $iv_a, '_bhela_inv_user', $iv_ua );
update_post_meta( $iv_b, '_bhela_inv_user', $iv_ub );

// Give B a movement of its own, so "A cannot see B" is a claim with content.
bhela_bm_ledger_add( array( 'investor' => $iv_b, 'type' => 'payment', 'amount' => 4321, 'date' => $iv_month . '-25', 'note' => 'ZZ B only' ) );

/** Resolve as a given user, from a cold cache — bhela_bm_current_investor() memoises. */
function iv_as( $user_id ) {
	// Via 0 first: wp_set_current_user() returns the cached user when the id has not
	// changed (§13.15). The resolver's own cache is keyed by user id, so switching
	// accounts inside one request resolves correctly without any reset here — which
	// is the property this helper exists to exercise.
	wp_set_current_user( 0 );
	clean_user_cache( $user_id );
	wp_set_current_user( $user_id );
	return $user_id;
}

// The resolver takes a USER and returns a RECORD. There is no parameter for "which
// investor", so there is no id in a URL to tamper with.
$iv_ref = new ReflectionFunction( 'bhela_bm_current_investor' );
ok( 0 === $iv_ref->getNumberOfParameters(), 'the resolver accepts no investor id at all — there is nothing to tamper with' );

$iv_src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/investor-portal.php' );
ok( false === strpos( $iv_src, "\$_GET['investor']" ) && false === strpos( $iv_src, "\$_REQUEST['investor']" ),
	'and the portal never reads an investor id from the request' );
ok( false !== strpos( $iv_src, 'get_current_user_id()' ), 'it resolves from the logged-in user' );

// bhela_bm_portal_data() takes no argument either.
$iv_pref = new ReflectionFunction( 'bhela_bm_portal_data' );
ok( 0 === $iv_pref->getNumberOfParameters(), 'and the data function cannot be asked about somebody else' );

// A duplicate link must refuse rather than pick a winner: whichever sorted first
// would decide whose money a person sees.
update_post_meta( $iv_c, '_bhela_inv_user', $iv_ua );
$iv_dupe = get_posts( array(
	'post_type' => 'bhela_investor', 'post_status' => array( 'publish', 'private', 'draft' ),
	'posts_per_page' => 5, 'fields' => 'ids', 'no_found_rows' => true,
	'meta_key' => '_bhela_inv_user', 'meta_value' => $iv_ua,
) );
ok( 2 === count( $iv_dupe ), 'two records can end up claiming one login…' );
ok( false !== strpos( $iv_src, 'count( $hit ) !== 1' ), '…and the resolver refuses rather than guessing which' );
delete_post_meta( $iv_c, '_bhela_inv_user' );

// The role itself grants nothing. The portal checks the record, not a capability.
$iv_urole = get_role( 'bhela_investor' );
foreach ( array( 'bhela_investors_view', 'bhela_view_statement', 'edit_posts', 'read_private_bhela_investors', 'bhela_investor_pay' ) as $iv_cap ) {
	ok( ! $iv_urole->has_cap( $iv_cap ), "the investor role does not hold $iv_cap" );
}
ok( $iv_urole->has_cap( 'read' ), 'it holds only `read`, which is what lets it log in at all' );

// wp-admin is closed to a pure investor login, on top of the empty role.
ok( false !== strpos( $iv_src, "wp_safe_redirect( bhela_bm_portal_url() )" ), 'an investor reaching wp-admin is redirected out' );
ok( false !== strpos( $iv_src, "wp_doing_ajax()" ), 'without breaking admin-ajax for logged-in front-end features' );

// And the portal writes nothing, ever.
ok( false === strpos( $iv_src, 'bhela_bm_ledger_add' ) && false === strpos( $iv_src, 'update_post_meta' ),
	'the portal is read-only — a disputed figure is corrected by the office, with a trail' );

echo "\n=== 12. the rendered portal contains one investor’s data only ===\n";
iv_as( $iv_ua );
ok( $iv_a === bhela_bm_current_investor(), 'A resolves to A’s record', (string) bhela_bm_current_investor() );

// Render it the way a visitor gets it, and check B is nowhere in the output.
$iv_html = bhela_bm_portal_shortcode();
ok( false !== strpos( $iv_html, 'ZZ Inv A' ), 'A’s portal names A' );
ok( false === strpos( $iv_html, 'ZZ Inv B' ), 'and never names B' );
ok( false === strpos( $iv_html, 'ZZ B only' ), 'nor carries B’s transactions' );
ok( false === strpos( $iv_html, '4,321' ) && false === strpos( $iv_html, '4321' ), 'nor B’s figures' );
ok( false !== strpos( $iv_html, bhela_bm_money( 16435 ) ), 'while showing A’s own declared profit' );

// A logged-out visitor gets the sign-in form, not a hint that records exist.
wp_set_current_user( 0 );
$iv_out = bhela_bm_portal_shortcode();
ok( false === strpos( $iv_out, 'ZZ Inv A' ) && false === strpos( $iv_out, 'ZZ Inv B' ),
	'a logged-out visitor is shown no investor at all' );
ok( false !== strpos( $iv_out, 'bhela_inv_nonce' ), 'just a nonce-protected sign-in form' );

// A logged-in user who is not an investor sees nothing either.
iv_as( 1 );
ok( 0 === bhela_bm_current_investor(), 'an administrator is not silently treated as an investor' );

// Switching back must resolve to A again, not to whatever was cached. The resolver
// memoises per user id precisely so a mid-request user change cannot hand somebody
// the previous viewer's record.
iv_as( $iv_ua );
ok( $iv_a === bhela_bm_current_investor(), 'and switching users mid-request resolves correctly each time' );
iv_as( $iv_ub );
ok( $iv_b === bhela_bm_current_investor(), 'B resolves to B, immediately after A' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $iv_ua );
wp_delete_user( $iv_ub );

/* ---------- cleanup ---------- */
foreach ( get_posts( array( 'post_type' => 'bhela_inv_ledger', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $z ) {
	bhela_test_delete( $z );
}
if ( ! is_wp_error( $iv_run ) ) {
	bhela_test_delete( $iv_run );
	$iv_idx = get_option( 'bhela_bm_dist_runs', array() );
	unset( $iv_idx[ $iv_month ] );
	update_option( 'bhela_bm_dist_runs', $iv_idx, false );
}
foreach ( $iv_made as $z ) {
	wp_delete_post( $z, true );
}
bhela_test_delete( $iv_sheet );

bhela_test_done();
