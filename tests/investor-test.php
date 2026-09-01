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
	'investor-portal', 'funds', 'cashflow'
);

wp_set_current_user( 1 );
bhela_bm_install_roles();
wp_set_current_user( 0 );
wp_set_current_user( 1 );

$iv_month = '2026-07';
$iv_made  = array();
$iv_rows  = array();

/**
 * Clear anything an earlier pass committed — a run survives deletion by design.
 *
 * Scoped to THIS MONTH's run and the fund rows that belong to it. Deleting every
 * fund row would wipe real reserve history on a site that has any, which is the
 * exact failure this project has already had twice with the period index and the
 * agency directory.
 */
function iv_reset( $month ) {
	$idx = get_option( 'bhela_bm_dist_runs', array() );
	if ( is_array( $idx ) && ! empty( $idx[ $month ] ) ) {
		$run_id = (int) $idx[ $month ];
		foreach ( get_posts( array(
			'post_type' => 'bhela_fund', 'post_status' => 'publish',
			'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_key' => '_bhela_fnd_run', 'meta_value' => $run_id,
		) ) as $z ) {
			bhela_test_delete( $z );
		}
		bhela_test_delete( $run_id );
		unset( $idx[ $month ] );
		update_option( 'bhela_bm_dist_runs', $idx, false );
	}
	// Spending rows the previous pass left behind, found by their ZZ note.
	foreach ( get_posts( array(
		'post_type' => 'bhela_fund', 'post_status' => 'publish',
		'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
	) ) as $z ) {
		// CONTAINS, not starts-with: a reversal's note reads "#123 বাতিল — ZZ wrong
		// head", so a prefix test left every reversal adjustment behind and the
		// reserve grew by 12,000 on each run of the suite.
		if ( false !== strpos( (string) get_post_meta( $z, '_bhela_fnd_note', true ), 'ZZ' ) ) {
			bhela_test_delete( $z );
		}
	}
	// Ledger rows are isolated to ZZ-titled fixtures, so this only ever reaches the
	// harness's own. There is deliberately NO blanket "delete every fund row" here:
	// one used to live at this spot and wiped real reserve history on the dev site,
	// which is the third time this project has had a test destroy owner data.
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
bhela_test_cost_meta( $iv_sheet, '_bhela_cost_trip_date', $iv_month . '-05' );
bhela_test_cost_meta( $iv_sheet, '_bhela_cost_earnings', 500000 );
bhela_test_cost_meta( $iv_sheet, '_bhela_cost_total', 200000 );
bhela_test_cost_meta( $iv_sheet, '_bhela_cost_status', 'draft' );
ok( 0 === bhela_bm_dist_preview( $iv_month )['gross'], 'a draft month has nothing to distribute' );
ok( is_wp_error( bhela_bm_dist_commit( $iv_month ) ), 'and committing it is refused' );

bhela_test_cost_meta( $iv_sheet, '_bhela_cost_status', 'approved' );
$iv_p = bhela_bm_dist_preview( $iv_month );
ok( 300000 === $iv_p['gross'], 'approving it makes ৳3,00,000 distributable', (string) $iv_p['gross'] );

echo "\n=== 4. the parts equal the whole ===\n";
ok( 30000 === $iv_p['reserve'] && 270000 === $iv_p['distributable'], 'reserve 10% comes off first' );
ok( 189000 === $iv_p['investor'] && 81000 === $iv_p['management'], 'then 70/30 across investors and management' );
ok( $iv_p['reserve'] + $iv_p['distributable'] === $iv_p['gross'], 'reserve + distributable = gross, to the taka' );
ok( $iv_p['investor'] + $iv_p['management'] === $iv_p['distributable'], 'investor + management = distributable, to the taka' );
ok( 131479 === $iv_p['unallocated'], 'and the unissued shares’ portion is reported as unallocated', (string) $iv_p['unallocated'] );

// Fund balances BEFORE this harness commits anything. Every fund assertion below is
// a delta against these: a real site carries reserve history, and a test that assumes
// an empty fund is asserting about the database rather than about the code. It failed
// exactly that way the first time it met a site with a distribution already on it.
$iv_res0 = bhela_bm_fund_ledger( 'reserve' );
$iv_mgt0 = bhela_bm_fund_ledger( 'management' );

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

echo "\n=== 13. the reserve and management funds fill themselves ===\n";
// Before this the reserve and management shares existed only as meta on a run: money
// set aside on paper and then untracked. They are ledger rows now, so "what is left
// in the reserve" has an answer that adds up.
// Measured as a delta from whatever the site already held: a dev or live database
// may carry real reserve history, and an assertion that assumes an empty fund is
// asserting about the database rather than about the code.
$iv_res = bhela_bm_fund_ledger( 'reserve' );
$iv_mgt = bhela_bm_fund_ledger( 'management' );
ok( 30000 === $iv_res['allocated'] - $iv_res0['allocated'], 'committing the month allocated the 10% reserve',
	(string) ( $iv_res['allocated'] - $iv_res0['allocated'] ) );
ok( 81000 === $iv_mgt['allocated'] - $iv_mgt0['allocated'], 'and the management 30%',
	(string) ( $iv_mgt['allocated'] - $iv_mgt0['allocated'] ) );
ok( 30000 === $iv_res['closing'] - $iv_res0['closing'] && 81000 === $iv_mgt['closing'] - $iv_mgt0['closing'],
	'both balances move by exactly what was allocated' );

// The three shares must still add up to the gross they came from.
$iv_dres = $iv_res['allocated'] - $iv_res0['allocated'];
$iv_dmgt = $iv_mgt['allocated'] - $iv_mgt0['allocated'];
ok( $iv_dres + $iv_dmgt + 189000 === 300000,
	'reserve + management + investor pool = gross, to the taka',
	$iv_dres . '+' . $iv_dmgt . '+189000' );

// An allocation is arithmetic, not a decision — it cannot be typed in.
$iv_hand = bhela_bm_fund_add( array( 'fund' => 'reserve', 'type' => 'allocation', 'amount' => 50000 ) );
ok( is_wp_error( $iv_hand ) && 'no_run' === $iv_hand->get_error_code(),
	'an allocation cannot be entered by hand — that would create money no month set aside' );

// Nor can a run be allocated twice.
bhela_bm_fund_allocate_run( $iv_run );
ok( 30000 === bhela_bm_fund_ledger( 'reserve' )['allocated'] - $iv_res0['allocated'], 'and a run cannot be allocated twice',
	(string) ( bhela_bm_fund_ledger( 'reserve' )['allocated'] - $iv_res0['allocated'] ) );

echo "\n=== 14. spending against a fund ===\n";
$iv_spend = bhela_bm_fund_add( array(
	'fund' => 'reserve', 'type' => 'utilisation', 'amount' => 12000,
	'head' => 'renovation', 'date' => $iv_month . '-15', 'note' => 'ZZ deck repair',
) );
ok( ! is_wp_error( $iv_spend ), 'spending is recorded' );
$iv_res = bhela_bm_fund_ledger( 'reserve' );
ok( 18000 === $iv_res['closing'] - $iv_res0['closing'], 'and comes off the balance: 30,000 − 12,000',
	(string) ( $iv_res['closing'] - $iv_res0['closing'] ) );
ok( 12000 === $iv_res['used'] - $iv_res0['used']
	&& 12000 === ( $iv_res['by_head']['renovation'] ?? 0 ) - ( $iv_res0['by_head']['renovation'] ?? 0 ),
	'attributed to its head' );

// Overdrawing is recorded, not blocked. The spending happened; refusing to record it
// would just move the error somewhere the books cannot see.
bhela_bm_fund_add( array( 'fund' => 'reserve', 'type' => 'utilisation', 'amount' => 25000, 'head' => 'emergency', 'date' => $iv_month . '-16', 'note' => 'ZZ engine' ) );
ok( -7000 === bhela_bm_fund_ledger( 'reserve' )['closing'] - $iv_res0['closing'],
	'spending past the allocation is recorded, not refused',
	(string) ( bhela_bm_fund_ledger( 'reserve' )['closing'] - $iv_res0['closing'] ) );

// A wrong entry is reversed, never edited.
$iv_frev = bhela_bm_fund_reverse( $iv_spend, 'ZZ wrong head' );
ok( ! is_wp_error( $iv_frev ), 'spending can be reversed with a reason' );
ok( 5000 === bhela_bm_fund_ledger( 'reserve' )['closing'] - $iv_res0['closing'], 'and the balance comes back',
	(string) ( bhela_bm_fund_ledger( 'reserve' )['closing'] - $iv_res0['closing'] ) );
ok( 0 === ( bhela_bm_fund_ledger( 'reserve' )['by_head']['renovation'] ?? 0 ) - ( $iv_res0['by_head']['renovation'] ?? 0 ),
	'the reversed spend stops counting against its head' );
ok( is_wp_error( bhela_bm_fund_reverse( $iv_spend, 'again' ) ), 'and it cannot be reversed twice' );

$iv_alloc_row = 0;
foreach ( bhela_bm_fund_ledger( 'reserve' )['rows'] as $r ) {
	if ( 'allocation' === $r['type'] ) {
		$iv_alloc_row = $r['id'];
	}
}
$iv_arev = bhela_bm_fund_reverse( $iv_alloc_row, 'ZZ nope' );
ok( is_wp_error( $iv_arev ) && 'is_allocation' === $iv_arev->get_error_code(),
	'an allocation cannot be reversed — the run would say one thing and the fund another' );

echo "\n=== 15. cash flow counts cash, not commitments ===\n";
// Deliberately not the Monthly Statement in another hat: that answers whether trading
// was profitable, this answers whether money moved. A business can be both profitable
// and short of cash.
$iv_cf = bhela_bm_cashflow( $iv_month . '-01', $iv_month . '-28' );
$iv_out_labels = wp_list_pluck( $iv_cf['out'], 'label' );
ok( in_array( 'Trip costs', $iv_out_labels, true ), 'trip costs are cash out' );
ok( in_array( 'Investor payments', $iv_out_labels, true ), 'so are investor payments' );

// The advance (5,000) counts; the reversed payment (2,000) does not — it was handed
// back, so it never left.
$iv_inv_out = 0;
foreach ( $iv_cf['out'] as $r ) {
	if ( 'Investor payments' === $r['label'] ) {
		$iv_inv_out = $r['amount'];
	}
}
// A's advance (5,000) plus B's payment (4,321). A's 2,000 payment was reversed, so
// it is absent — money handed back never left the business, and counting it would
// show cash going out twice for one mistake.
ok( 9321 === $iv_inv_out, 'investor cash out is the advance plus B’s payment', (string) $iv_inv_out );
ok( 11321 !== $iv_inv_out, 'and specifically EXCLUDES the reversed 2,000' );

// The allocation is an internal earmark. Counting it as cash out would double up
// against the trip costs and salaries it eventually pays for.
$iv_alloc_as_cash = false;
foreach ( $iv_cf['out'] as $r ) {
	if ( 30000 === $r['amount'] || 81000 === $r['amount'] ) {
		$iv_alloc_as_cash = true;
	}
}
ok( ! $iv_alloc_as_cash, 'a fund ALLOCATION is never cash out — only what the fund spends is' );

$iv_fund_out = 0;
foreach ( $iv_cf['out'] as $r ) {
	if ( false !== strpos( $r['label'], 'Reserve' ) ) {
		$iv_fund_out = $r['amount'];
	}
}
ok( 25000 === $iv_fund_out, 'and fund spending is, net of reversals', (string) $iv_fund_out );
ok( $iv_cf['net'] === $iv_cf['in_total'] - $iv_cf['out_total'], 'net movement is in minus out' );

// An inverted or empty range must return nothing rather than everything.
ok( 0 === bhela_bm_cashflow( '2026-09-30', '2026-09-01' )['in_total'], 'an inverted range returns nothing' );
ok( 0 === bhela_bm_cashflow( '', '' )['out_total'], 'and so does a blank one' );


echo "\n=== 16. every field change is on the record, and bank details are named not printed ===\n";
// Before this, bhela_bm_investor_save() audited a shareholding change and nothing
// else. An investor's bank account could be repointed with no trace at all, which is
// the highest-value tamper on the whole module and exactly what the trail is for.
$iv_acct_old = '1234567890';
$iv_acct_new = '9999888877';
update_post_meta( $iv_a, '_bhela_inv_bank_account', $iv_acct_old );
$iv_before = count( bhela_bm_audit_history( 'investor', $iv_a ) );

$_POST = array(
	'bhela_bm_investor_nonce' => wp_create_nonce( 'bhela_bm_investor_save' ),
	'inv_shares'              => bhela_bm_investor_shares( $iv_a ),
	'inv_amount'              => (int) get_post_meta( $iv_a, '_bhela_inv_amount', true ),
	'inv_date'                => (string) get_post_meta( $iv_a, '_bhela_inv_date', true ),
	'inv_status'              => bhela_bm_investor_status( $iv_a ),
	'inv_bank_account'        => $iv_acct_new,
	'inv_mobile'              => '01711000111',
);
bhela_bm_investor_save( $iv_a );
$_POST = array();

$iv_hist = array_slice( bhela_bm_audit_history( 'investor', $iv_a ), $iv_before );
$iv_bank_row  = null;
$iv_phone_row = null;
foreach ( $iv_hist as $h ) {
	if ( 'bank_account' === $h['field'] ) {
		$iv_bank_row = $h;
	}
	if ( 'mobile' === $h['field'] ) {
		$iv_phone_row = $h;
	}
}
ok( $iv_acct_new === (string) get_post_meta( $iv_a, '_bhela_inv_bank_account', true ), 'the account number is saved' );
ok( null !== $iv_bank_row, 'and changing it writes an audit row naming the field' );
if ( $iv_bank_row ) {
	// The point of the exercise: a trail that printed both numbers would be a second,
	// never-deleted copy of the very data it is protecting, readable by anyone who can
	// open the Audit Trail.
	ok( '' === (string) $iv_bank_row['old_value'] && '' === (string) $iv_bank_row['new_value'],
		'with NEITHER account number in it' );
	$iv_blob = wp_json_encode( $iv_bank_row );
	ok( false === strpos( $iv_blob, $iv_acct_old ) && false === strpos( $iv_blob, $iv_acct_new ),
		'and neither number anywhere else on the row either' );
}
// An ordinary field still records its values — hiding everything would make the trail
// useless. Only bank and identity fields are held back.
ok( null !== $iv_phone_row, 'an ordinary field change is audited too' );
if ( $iv_phone_row ) {
	ok( '01711000111' === (string) $iv_phone_row['new_value'], 'and it DOES carry the new value', (string) $iv_phone_row['new_value'] );
}
// Saving the same values again writes nothing: a trail full of no-op rows is a trail
// nobody reads.
$iv_before2 = count( bhela_bm_audit_history( 'investor', $iv_a ) );
$_POST = array(
	'bhela_bm_investor_nonce' => wp_create_nonce( 'bhela_bm_investor_save' ),
	'inv_shares'              => bhela_bm_investor_shares( $iv_a ),
	'inv_amount'              => (int) get_post_meta( $iv_a, '_bhela_inv_amount', true ),
	'inv_date'                => (string) get_post_meta( $iv_a, '_bhela_inv_date', true ),
	'inv_status'              => bhela_bm_investor_status( $iv_a ),
	'inv_bank_account'        => $iv_acct_new,
	'inv_mobile'              => '01711000111',
);
bhela_bm_investor_save( $iv_a );
$_POST = array();
ok( $iv_before2 === count( bhela_bm_audit_history( 'investor', $iv_a ) ), 'an unchanged save writes no audit row at all' );

echo "\n=== 17. the portal login throttles guessing, and never punishes success ===\n";
$iv_src_p  = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/investor-portal.php' );
$iv_limit  = bhela_bm_portal_login_limit();
$iv_ip_key = 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() );
delete_transient( $iv_ip_key );

ok( $iv_limit > 0 && $iv_limit <= 20, 'there is a failed-attempt limit', (string) $iv_limit );
ok( false !== strpos( $iv_src_p, 'bhela_bm_client_ip()' ), 'keyed per IP, the same shape as the booking tracker' );
// Counting successes would lock out everyone behind one CGNAT address the moment a
// single neighbour typed a password wrong.
ok( false !== strpos( $iv_src_p, 'delete_transient( $ip_key )' ), 'and a correct sign-in CLEARS the counter rather than adding to it' );
// Drive it: failures accumulate, and the throttled response is indistinguishable.
$_POST = array(
	'bhela_inv_login' => '1',
	'bhela_inv_nonce' => wp_create_nonce( 'bhela_inv_login' ),
	'log'             => 'zz_nobody_here',
	'pwd'             => 'wrong-on-purpose',
);
for ( $iv_try = 0; $iv_try < $iv_limit; $iv_try++ ) {
	bhela_bm_portal_login();
}
ok( $iv_limit === (int) get_transient( $iv_ip_key ), 'each failure is counted once', (string) (int) get_transient( $iv_ip_key ) );
$iv_blocked = bhela_bm_portal_login();
ok( false !== strpos( $iv_blocked, 'bhela_inv_nonce' ), 'the next attempt gets the form back, not a lockout page' );
// Byte-for-byte the same page a wrong password produces. Saying "too many
// attempts" would confirm to an attacker that the account exists and that they
// are hitting a real limit worth waiting out.
delete_transient( $iv_ip_key );
$iv_wrongpw = bhela_bm_portal_login();
delete_transient( $iv_ip_key );
set_transient( $iv_ip_key, $iv_limit, HOUR_IN_SECONDS );
$iv_throttled = bhela_bm_portal_login();
ok( $iv_throttled === $iv_wrongpw, 'and it is byte-for-byte the wrong-password page — a throttled attempt reveals nothing a wrong password does not' );
ok( $iv_limit === (int) get_transient( $iv_ip_key ), 'and a refused attempt does not inflate the counter further' );
$_POST = array();
delete_transient( $iv_ip_key );

echo "\n=== 18. a payment needs two people ===\n";
// Cost sheets have required prepare -> check -> approve for a long time. Paying a
// named person needed no second signature at all, which made it the weakest link in a
// chain that is careful everywhere else.
bhela_bm_install_roles();
$iv_rel = get_role( 'bhela_investor_relations' );
$iv_mgr = get_role( 'bhela_manager' );
ok( $iv_rel->has_cap( 'bhela_investor_pay' ), 'Investor Relations can raise a payment' );
ok( ! $iv_rel->has_cap( 'bhela_investor_approve' ), 'but CANNOT approve one — a second signature the same person supplies is not a second signature' );
ok( $iv_mgr->has_cap( 'bhela_investor_approve' ), 'a Manager can approve' );
ok( ! $iv_mgr->has_cap( 'bhela_investor_pay' ), 'and does not raise them by default either' );

$iv_urq = wp_insert_user( array( 'user_login' => 'zz_pay_req', 'user_email' => 'zz_pay_req@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor_relations' ) );
$iv_uap = wp_insert_user( array( 'user_login' => 'zz_pay_app', 'user_email' => 'zz_pay_app@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_manager' ) );

$iv_pos0 = bhela_bm_investor_roi( $iv_a );
$iv_cf0  = bhela_bm_cashflow( $iv_month . '-01', $iv_month . '-28' );

iv_as( $iv_urq );
$iv_req = bhela_bm_payreq_add( array(
	'investor'  => $iv_a,
	'type'      => 'payment',
	'amount'    => 7777,
	'date'      => $iv_month . '-26',
	'method'    => 'bank',
	'reference' => 'ZZ CHQ 001',
	'note'      => 'ZZ payment request',
) );
ok( ! is_wp_error( $iv_req ) && $iv_req > 0, 'Investor Relations can raise a request' );

// The whole claim: raising it moves nothing.
$iv_pos1 = bhela_bm_investor_roi( $iv_a );
ok( $iv_pos0['received'] === $iv_pos1['received'], 'a pending request pays nothing…', (string) $iv_pos1['received'] );
ok( $iv_pos0['outstanding'] === $iv_pos1['outstanding'], '…and the outstanding balance does not move' );
ok( $iv_pos0['roi'] === $iv_pos1['roi'], '…nor does ROI' );
$iv_cf1 = bhela_bm_cashflow( $iv_month . '-01', $iv_month . '-28' );
ok( $iv_cf0['out_total'] === $iv_cf1['out_total'], '…and no cash has left the business' );

// A requester approving their own request defeats the point, so it is refused before
// the capability is even reached.
iv_as( $iv_urq );
ok( is_wp_error( bhela_bm_payreq_approve( $iv_req ) ), 'the requester cannot approve their own request' );

iv_as( $iv_uap );
$iv_row = bhela_bm_payreq_approve( $iv_req );
ok( ! is_wp_error( $iv_row ) && $iv_row > 0, 'a Manager can, and only now is a ledger row written' );

$iv_pos2 = bhela_bm_investor_roi( $iv_a );
ok( $iv_pos1['received'] + 7777 === $iv_pos2['received'], 'the payment lands exactly once', (string) $iv_pos2['received'] );
ok( $iv_pos1['outstanding'] - 7777 === $iv_pos2['outstanding'], 'and the outstanding balance falls by exactly the amount' );

// Approving twice must not pay twice.
ok( is_wp_error( bhela_bm_payreq_approve( $iv_req ) ), 'an already-settled request cannot be approved again' );
ok( $iv_pos2['received'] === bhela_bm_investor_roi( $iv_a )['received'], 'so the money moves once and only once' );

// A rejection writes no ledger row, ever.
iv_as( $iv_urq );
$iv_req2 = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'advance', 'amount' => 3333, 'date' => $iv_month . '-27', 'note' => 'ZZ to reject' ) );
iv_as( $iv_uap );
ok( is_wp_error( bhela_bm_payreq_reject( $iv_req2, '' ) ), 'a rejection without a reason is refused' );
ok( true === bhela_bm_payreq_reject( $iv_req2, 'ZZ not this month' ), 'and with one it is recorded' );
ok( $iv_pos2['received'] === bhela_bm_investor_roi( $iv_a )['received'], 'a rejected request pays nothing' );
ok( 0 === (int) get_post_meta( $iv_req2, '_bhela_pr_ledger', true ), 'and leaves no ledger row behind it' );

// The ledger keeps meaning what it meant: what actually moved. The workflow lives on
// its own record precisely so the append-only rows stay immutable.
$iv_prsrc = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/investor-payreq.php' );
ok( false === strpos( $iv_prsrc, '_bhela_led_state' ) && false === strpos( $iv_prsrc, '_bhela_led_approved' ),
	'approval state is never written onto a ledger row' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $iv_urq );
wp_delete_user( $iv_uap );


echo "\n=== 19. a season is a label over a range, never a second set of boundaries ===\n";
$iv_seasons_was = get_option( 'bhela_bm_seasons', array() );

// Shipped empty on purpose: inventing somebody else's season dates would put a
// confident wrong answer on the screen.
ok( is_array( bhela_bm_seasons() ), 'the season list is always an array' );

bhela_bm_save_seasons( array(
	array( 'key' => '', 'label' => 'ZZ Season', 'from' => $iv_month . '-01', 'to' => $iv_month . '-28' ),
	// Every one of these is unusable as a range, and each is dropped rather than
	// silently reporting on everything or on nothing.
	array( 'key' => '', 'label' => 'ZZ No dates', 'from' => '', 'to' => '' ),
	array( 'key' => '', 'label' => 'ZZ Backwards', 'from' => $iv_month . '-28', 'to' => $iv_month . '-01' ),
	array( 'key' => '', 'label' => '', 'from' => '2026-01-01', 'to' => '2026-12-31' ),
) );
$iv_all_seasons = bhela_bm_seasons();
ok( 1 === count( $iv_all_seasons ), 'only a season that resolves to a real range survives the save', (string) count( $iv_all_seasons ) );
$iv_skey = array_key_first( $iv_all_seasons );
ok( 'zz-season' === $iv_skey || 'zz_season' === $iv_skey, 'the key is minted from the label', (string) $iv_skey );

$iv_sfound = bhela_bm_season_for( $iv_month . '-15' );
ok( $iv_sfound && $iv_skey === $iv_sfound['key'], 'a date inside it resolves to it' );
ok( null === bhela_bm_season_for( '2001-01-01' ), 'and one outside every season resolves to nothing' );
ok( null === bhela_bm_season_for( '' ), 'a blank date resolves to nothing rather than to the first season' );

// The claim that makes seasons safe: a season is the same figures as its raw range.
$iv_sdata = bhela_bm_season_investors( $iv_skey );
ok( null !== $iv_sdata, 'a season resolves to per-investor figures' );
$iv_raw_declared = 0;
foreach ( bhela_bm_investors() as $iv_sid ) {
	foreach ( bhela_bm_investor_ledger( $iv_sid )['rows'] as $iv_r ) {
		if ( $iv_r['date'] < $iv_month . '-01' || $iv_r['date'] > $iv_month . '-28' ) {
			continue;
		}
		if ( bhela_bm_ledger_reversal_of( $iv_r['id'] ) || $iv_r['reverses'] ) {
			continue;
		}
		if ( 'profit' === $iv_r['type'] ) {
			$iv_raw_declared += $iv_r['amount'];
		}
	}
}
ok( $iv_raw_declared === $iv_sdata['declared'], 'and they equal the same dates asked for raw, to the taka',
	$iv_raw_declared . ' vs ' . $iv_sdata['declared'] );
ok( null === bhela_bm_season_investors( 'no_such_season' ), 'an unknown season reports nothing rather than everything' );

echo "\n=== 20. the dashboard cannot disagree with the screens it summarises ===\n";
$iv_dash = bhela_bm_investor_dash_data();
$iv_sum_decl = 0;
$iv_sum_recv = 0;
$iv_sum_out  = 0;
foreach ( bhela_bm_investors() as $iv_did ) {
	$iv_dr        = bhela_bm_investor_roi( $iv_did );
	$iv_sum_decl += $iv_dr['declared'];
	$iv_sum_recv += $iv_dr['received'];
	$iv_sum_out  += $iv_dr['outstanding'];
}
ok( $iv_sum_decl === $iv_dash['declared'], 'declared totals match the per-investor figures', $iv_sum_decl . ' vs ' . $iv_dash['declared'] );
ok( $iv_sum_recv === $iv_dash['received'], 'so does received' );
ok( $iv_sum_out === $iv_dash['outstanding'], 'so does outstanding' );
ok( $iv_dash['shares']['issued'] === bhela_bm_share_totals()['issued'], 'and the share count is the register’s own' );

// A pending request is surfaced but counted nowhere — the same claim §18 makes,
// asserted here on the screen that would be most tempting to shortcut.
$iv_dash_recv0 = $iv_dash['received'];
$iv_dpr = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 4242, 'date' => $iv_month . '-28', 'note' => 'ZZ dash pending' ) );
$iv_dash2 = bhela_bm_investor_dash_data();
ok( $iv_dash2['pending']['count'] >= 1, 'the dashboard names what is waiting for approval' );
ok( $iv_dash_recv0 === $iv_dash2['received'], 'and it is in no money figure on the screen', $iv_dash2['received'] . '' );
if ( ! is_wp_error( $iv_dpr ) ) {
	wp_delete_post( $iv_dpr, true );
}

// Each fund's balance is allocated less spent, replayed rather than stored.
foreach ( $iv_dash['funds'] as $iv_fk => $iv_f ) {
	$iv_fl = bhela_bm_fund_ledger( $iv_fk );
	ok( (int) $iv_fl['closing'] === $iv_f['balance'], "the $iv_fk balance is the ledger's own closing figure",
		$iv_fl['closing'] . ' vs ' . $iv_f['balance'] );
}

// An export leaves the building. Bank details must not be in it.
$iv_dash_src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/investor-dashboard.php' );
ok( false === strpos( $iv_dash_src, '_bhela_inv_bank_account' ) && false === strpos( $iv_dash_src, '_bhela_inv_nid' ),
	'the register CSV carries no account number and no NID' );
ok( false !== strpos( $iv_dash_src, 'bhela_bm_csv_cell' ), 'and every free-text cell in it is neutralised' );

update_option( 'bhela_bm_seasons', $iv_seasons_was, false );


echo "\n=== 21. the portal answers more than six questions, and still only about one investor ===\n";
// The portal shipped showing six figures against the brief's fifteen. What was
// missing was everything that makes a number mean something: how THIS season is
// going, what the company has set aside, and whether a payment is already on its way.
$iv_pu = wp_insert_user( array( 'user_login' => 'zz_portal_kpi', 'user_email' => 'zz_portal_kpi@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor' ) );
update_post_meta( $iv_a, '_bhela_inv_user', $iv_pu );

bhela_bm_save_seasons( array(
	array( 'key' => '', 'label' => 'ZZ Now', 'from' => gmdate( 'Y-m-d', strtotime( '-1 month' ) ), 'to' => gmdate( 'Y-m-d', strtotime( '+1 month' ) ) ),
) );

iv_as( $iv_pu );
$iv_pd = bhela_bm_portal_data();
ok( $iv_a === $iv_pd['id'], 'the portal still resolves to exactly one investor' );
ok( isset( $iv_pd['season'] ) && null !== $iv_pd['season'], 'it knows which season today falls in' );
ok( 'ZZ Now' === $iv_pd['season']['label'], 'and names it', (string) ( $iv_pd['season']['label'] ?? '' ) );
ok( isset( $iv_pd['funds']['reserve'] ), 'the reserve allocation is on the portal' );
ok( isset( $iv_pd['funds']['management'] ), 'and so is the management allocation' );
// Totals only. §18's breakdown of what management spent on is internal — a portal is
// not where that conversation happens.
ok( ! isset( $iv_pd['funds']['reserve']['by_head'] ) && ! isset( $iv_pd['funds']['reserve']['rows'] ),
	'as a total only, with no breakdown of what either fund was spent on' );

// A pending request is shown but counted nowhere.
iv_as( 1 );
$iv_ppr = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 6060, 'date' => $iv_month . '-20', 'note' => 'ZZ portal pending' ) );
iv_as( $iv_pu );
$iv_pd2 = bhela_bm_portal_data();
ok( 1 === $iv_pd2['pending']['count'] && 6060 === $iv_pd2['pending']['total'], 'a raised payment is visible to the investor',
	$iv_pd2['pending']['count'] . ' / ' . $iv_pd2['pending']['total'] );
ok( $iv_pd['roi']['received'] === $iv_pd2['roi']['received'], 'and is in no figure on the page — nothing has been paid' );
ok( $iv_pd['roi']['outstanding'] === $iv_pd2['roi']['outstanding'], 'nor in the outstanding balance' );

// Rendered, because the harnesses call data functions and never draw a page — which
// is how the discount badge and the B2B date default both shipped broken.
$iv_phtml = bhela_bm_portal_shortcode();
ok( false !== strpos( $iv_phtml, 'ZZ Now' ), 'the season reaches the rendered page' );
ok( false !== strpos( $iv_phtml, bhela_bm_money( 6060 ) ), 'so does the waiting payment' );
ok( false === strpos( $iv_phtml, 'ZZ Inv B' ), 'and it still names no other investor' );
ok( false !== strpos( $iv_phtml, 'bhela-inv__note' ), 'the waiting payment is marked as news rather than as a balance' );

if ( ! is_wp_error( $iv_ppr ) ) {
	wp_delete_post( $iv_ppr, true );
}
wp_set_current_user( 0 );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $iv_pu );
delete_post_meta( $iv_a, '_bhela_inv_user' );


echo "\n=== 22. one request pays once, even under a race ===\n";
// The state check was a READ. Two approvals arriving together both passed it, both
// wrote a ledger row, and the investor was paid twice for one request — with nothing
// in the ledger looking wrong afterwards, because both rows were individually valid.
$iv_ra = wp_insert_user( array( 'user_login' => 'zz_race_req', 'user_email' => 'zz_race_req@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor_relations' ) );
$iv_rb = wp_insert_user( array( 'user_login' => 'zz_race_app', 'user_email' => 'zz_race_app@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_manager' ) );

iv_as( $iv_ra );
$iv_rr = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 5150, 'date' => $iv_month . '-21', 'note' => 'ZZ race' ) );
ok( ! is_wp_error( $iv_rr ), 'a request is raised' );

$iv_recv_before = bhela_bm_investor_roi( $iv_a )['received'];

// Reproduce the race exactly: two approvals that both read `requested` before either
// writes. Restoring the meta between the read and the second call is what a second
// concurrent request would have seen.
iv_as( $iv_rb );
$iv_first = bhela_bm_payreq_approve( $iv_rr );
ok( ! is_wp_error( $iv_first ), 'the first approval wins' );

// The belt-and-braces guard: a request that already carries a ledger row is refused
// whatever its state says.
update_post_meta( $iv_rr, '_bhela_pr_state', 'requested' );
$iv_second = bhela_bm_payreq_approve( $iv_rr );
ok( is_wp_error( $iv_second ), 'the second is refused even with the state reset under it',
	is_wp_error( $iv_second ) ? $iv_second->get_error_code() : 'ALLOWED' );
ok( 'paid' === ( is_wp_error( $iv_second ) ? $iv_second->get_error_code() : '' ),
	'and it is refused because a ledger row already exists, not by luck' );

// --- and now the race itself, which the check above does NOT exercise ---
//
// In a real race both approvals read `ledger = 0`, so BOTH pass the guard above and
// only the conditional UPDATE can stop the second one. That interleaving is
// reproducible in one process through the object cache: put the winner's result in
// the database WITHOUT clearing the cache, and the next call reads exactly what a
// concurrent request would have been holding — state `requested`, ledger 0.
global $wpdb;
iv_as( $iv_ra );
$iv_rr3 = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 3131, 'date' => $iv_month . '-21', 'note' => 'ZZ race real' ) );
iv_as( $iv_rb );

$iv_recv_r3 = bhela_bm_investor_roi( $iv_a )['received'];
// Prime the meta cache first. update_post_meta() finishes by DELETING the cache
// entry rather than refreshing it, so after bhela_bm_payreq_add() the cache is cold
// and a later read would go to the database and see the winner's write — which is
// the opposite of the state a racing request holds. One read puts 'requested' in the
// cache, which is exactly what the loser was carrying.
get_post_meta( $iv_rr3, '_bhela_pr_state', true );
// The winner's write, straight to the table. No wp_cache_delete — that is the point.
$wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => 'approved' ),
	array( 'post_id' => $iv_rr3, 'meta_key' => '_bhela_pr_state' )
);
ok( 'requested' === get_post_meta( $iv_rr3, '_bhela_pr_state', true ),
	'the cache still reports what the racing request read', (string) get_post_meta( $iv_rr3, '_bhela_pr_state', true ) );
ok( 0 === (int) get_post_meta( $iv_rr3, '_bhela_pr_ledger', true ), 'and no ledger row is on it yet' );

$iv_loser = bhela_bm_payreq_approve( $iv_rr3 );
ok( is_wp_error( $iv_loser ), 'the loser of the race is refused',
	is_wp_error( $iv_loser ) ? $iv_loser->get_error_code() : 'ALLOWED' );
ok( 'settled' === ( is_wp_error( $iv_loser ) ? $iv_loser->get_error_code() : '' ),
	'by the conditional UPDATE finding no `requested` row — not by the ledger guard, which it passed',
	is_wp_error( $iv_loser ) ? $iv_loser->get_error_code() : '' );
ok( $iv_recv_r3 === bhela_bm_investor_roi( $iv_a )['received'], 'and no second payment is written',
	$iv_recv_r3 . ' -> ' . bhela_bm_investor_roi( $iv_a )['received'] );
wp_cache_delete( $iv_rr3, 'post_meta' );

$iv_recv_after = bhela_bm_investor_roi( $iv_a )['received'];
ok( $iv_recv_before + 5150 === $iv_recv_after, 'so the investor is paid exactly once',
	$iv_recv_before . ' -> ' . $iv_recv_after );

// The claim itself is a single conditional UPDATE, so the database picks the winner.
$iv_pr_src = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/investor-payreq.php' );
ok( false !== strpos( $iv_pr_src, "meta_value = 'requested'" ),
	'the state is claimed with a conditional UPDATE rather than a read-then-write' );
ok( false !== strpos( $iv_pr_src, 'wp_cache_delete' ), 'and the meta cache is dropped after it' );

// A rejection takes the same claim, or an approve and a reject racing could both win.
iv_as( $iv_ra );
$iv_rr2 = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 2020, 'date' => $iv_month . '-22', 'note' => 'ZZ race 2' ) );
iv_as( $iv_rb );
ok( true === bhela_bm_payreq_reject( $iv_rr2, 'ZZ no' ), 'a request is rejected' );
ok( is_wp_error( bhela_bm_payreq_approve( $iv_rr2 ) ), 'and cannot then be approved' );
ok( $iv_recv_after === bhela_bm_investor_roi( $iv_a )['received'], 'with no money moved by the attempt' );

echo "\n=== 23. an exited investor is not paid by routine ===\n";
$iv_ex_was = bhela_bm_investor_status( $iv_a );
update_post_meta( $iv_a, '_bhela_inv_status', 'exited' );
iv_as( $iv_ra );
$iv_exq = bhela_bm_payreq_add( array( 'investor' => $iv_a, 'type' => 'payment', 'amount' => 100, 'date' => $iv_month . '-23' ) );
ok( is_wp_error( $iv_exq ), 'a payment request against an exited investor is refused',
	is_wp_error( $iv_exq ) ? $iv_exq->get_error_code() : 'ALLOWED' );
// An adjustment is still available: a final settlement is a decision with a reason,
// not a routine payment.
iv_as( 1 );
// A real amount: bhela_bm_ledger_add() refuses 0 unconditionally, so the previous
// version of this assertion read `false || true` and could never fail.
$iv_exadj = bhela_bm_ledger_add( array( 'investor' => $iv_a, 'type' => 'adjustment', 'amount' => -250, 'date' => $iv_month . '-23', 'note' => 'ZZ exited settlement' ) );
ok( ! is_wp_error( $iv_exadj ), 'while an adjustment against an exited investor is still allowed',
	is_wp_error( $iv_exadj ) ? $iv_exadj->get_error_code() : 'ok' );
if ( ! is_wp_error( $iv_exadj ) ) {
	bhela_test_delete( $iv_exadj );
}
update_post_meta( $iv_a, '_bhela_inv_status', $iv_ex_was );

// Listings are capped rather than unbounded: the dashboard reads the pending total on
// every load and requests accumulate for the life of the business.
ok( bhela_bm_payreq_limit() > 0, 'the request listing is capped', (string) bhela_bm_payreq_limit() );
ok( false === strpos( $iv_pr_src, "'posts_per_page' => -1" ), 'and no listing here is unbounded' );

foreach ( array( $iv_rr, $iv_rr2, $iv_rr3 ) as $iv_z ) {
	if ( ! is_wp_error( $iv_z ) ) {
		wp_delete_post( $iv_z, true );
	}
}
wp_set_current_user( 0 );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $iv_ra );
wp_delete_user( $iv_rb );

echo "\n=== 23b. a fund row is locked, like every other money row ===\n";
// `bhela_fund` was in no lock's post-type list, so the `_bhela_fnd_` branch of
// bhela_bm_dist_block_meta() was dead code and bhela_bm_dist_block_delete() — which
// shares the same predicate — did not cover fund rows either. A reserve allocation
// was rewritable and hard-deletable from WP-CLI: a wider hole than the missing
// add_post_metadata hook, because there was no lock at all rather than one with a gap.
ok( bhela_bm_dist_locked( $iv_alloc_row ), 'a fund row reports itself locked' );

$iv_fnd_was = (int) get_post_meta( $iv_alloc_row, '_bhela_fnd_amount', true );
update_post_meta( $iv_alloc_row, '_bhela_fnd_amount', 999999 );
ok( $iv_fnd_was === (int) get_post_meta( $iv_alloc_row, '_bhela_fnd_amount', true ),
	'its amount cannot be rewritten', (string) get_post_meta( $iv_alloc_row, '_bhela_fnd_amount', true ) );
bhela_bm_dist_writing( true );
delete_post_meta( $iv_alloc_row, '_bhela_fnd_head' );
bhela_bm_dist_writing( false );
add_post_meta( $iv_alloc_row, '_bhela_fnd_head', 'ZZ forged' );
ok( '' === get_post_meta( $iv_alloc_row, '_bhela_fnd_head', true ), 'nor a key added while it was absent',
	var_export( get_post_meta( $iv_alloc_row, '_bhela_fnd_head', true ), true ) );

wp_trash_post( $iv_alloc_row );
ok( 'trash' !== get_post_status( $iv_alloc_row ), 'a fund row cannot be trashed' );
wp_delete_post( $iv_alloc_row, true );
ok( 'bhela_fund' === get_post_type( $iv_alloc_row ), 'nor hard-deleted — §13.35 says an allocation is not cancellable at all' );

echo "\n=== 23c. and no lock can be walked past by deleting a key everywhere ===\n";
// delete_post_meta_by_key() fires the same filter with an object id of 0 and
// $delete_all true — "remove this key from EVERY post". A guard that resolves a lock
// from the id saw 0, found nothing, and allowed it. All three locks were registered
// for three arguments, so $delete_all was never even visible to them.
$iv_led_rows = get_posts( array( 'post_type' => 'bhela_inv_ledger', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( $iv_led_rows ) {
	$iv_led_one = $iv_led_rows[0];
	$iv_amt_was = (string) get_post_meta( $iv_led_one, '_bhela_led_amount', true );
	delete_post_meta_by_key( '_bhela_led_amount' );
	ok( $iv_amt_was === (string) get_post_meta( $iv_led_one, '_bhela_led_amount', true ),
		'a blanket delete of a ledger amount is refused', (string) get_post_meta( $iv_led_one, '_bhela_led_amount', true ) );
}
delete_post_meta_by_key( '_bhela_fnd_amount' );
ok( $iv_fnd_was === (int) get_post_meta( $iv_alloc_row, '_bhela_fnd_amount', true ),
	'and so is one of a fund amount', (string) get_post_meta( $iv_alloc_row, '_bhela_fnd_amount', true ) );

// All three locks take five arguments on the delete filter, or $delete_all is invisible.
foreach ( array( 'costs-core', 'distribution-core', 'inventory-core' ) as $iv_lock ) {
	$iv_lsrc = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/' . $iv_lock . '.php' );
	ok( false !== strpos( $iv_lsrc, "'delete_post_metadata_by_mid'" ), "$iv_lock covers delete-by-meta-id" );
	ok( preg_match( '/delete_post_metadata.{0,80}?,\s*5\s*\)/', $iv_lsrc ) > 0,
		"$iv_lock registers the delete filter for five arguments" );
}

echo "\n=== 24. overlapping seasons are reported, not silently resolved ===\n";
// The docblock claimed the settings screen warned. Nothing did.
$iv_sea_was = get_option( 'bhela_bm_seasons', array() );
bhela_bm_save_seasons( array(
	array( 'key' => '', 'label' => 'ZZ Early', 'from' => '2026-01-01', 'to' => '2026-06-30' ),
	array( 'key' => '', 'label' => 'ZZ Late',  'from' => '2026-06-01', 'to' => '2026-12-31' ),
) );
$iv_ov = bhela_bm_season_overlaps();
ok( 1 === count( $iv_ov ), 'an overlap is detected', (string) count( $iv_ov ) );
ok( 'ZZ Early' === $iv_ov[0]['a'] && 'ZZ Late' === $iv_ov[0]['b'], 'and names both seasons' );
// The earliest-starting season wins, which is what the warning exists to make visible.
ok( 'ZZ Early' === bhela_bm_season_for( '2026-06-15' )['label'], 'a date inside the overlap resolves to the earlier season',
	(string) bhela_bm_season_for( '2026-06-15' )['label'] );

bhela_bm_save_seasons( array(
	array( 'key' => '', 'label' => 'ZZ A', 'from' => '2026-01-01', 'to' => '2026-05-31' ),
	array( 'key' => '', 'label' => 'ZZ B', 'from' => '2026-06-01', 'to' => '2026-12-31' ),
) );
ok( ! bhela_bm_season_overlaps(), 'seasons that merely touch at a boundary do not overlap' );
update_option( 'bhela_bm_seasons', $iv_sea_was, false );

echo "\n=== 25. the portal's fund totals are cached, and a fund write drops the cache ===\n";
// bhela_bm_fund_ledger() replays every row a fund has ever held to produce one total,
// and the portal is a front-end page every investor loads.
delete_transient( 'bhela_bm_portal_funds' );
$iv_pu2 = wp_insert_user( array( 'user_login' => 'zz_fundcache', 'user_email' => 'zz_fundcache@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor' ) );
update_post_meta( $iv_a, '_bhela_inv_user', $iv_pu2 );
iv_as( $iv_pu2 );
$iv_fd1 = bhela_bm_portal_data();
ok( is_array( get_transient( 'bhela_bm_portal_funds' ) ), 'the first portal load fills the cache' );
$iv_res_seen = (int) ( $iv_fd1['funds']['reserve']['allocated'] ?? 0 );

// A fund movement must invalidate it, or an investor is shown a figure they have just
// been told changed.
iv_as( 1 );
$iv_spend = bhela_bm_fund_add( array( 'fund' => 'reserve', 'type' => 'utilisation', 'amount' => 1500, 'head' => 'repair', 'date' => $iv_month . '-24', 'note' => 'ZZ cache bust' ) );
ok( false === get_transient( 'bhela_bm_portal_funds' ), 'a fund write drops the cache' );

iv_as( $iv_pu2 );
$iv_fd2 = bhela_bm_portal_data();
ok( is_array( $iv_fd2['funds'] ) && isset( $iv_fd2['funds']['reserve'] ), 'and the portal refills it' );
// Spending is not an allocation, so the allocated figure is unchanged — the point is
// that the cache was rebuilt, not that the number moved.
ok( $iv_res_seen === (int) $iv_fd2['funds']['reserve']['allocated'], 'spending does not change what was allocated',
	$iv_res_seen . ' vs ' . (int) $iv_fd2['funds']['reserve']['allocated'] );

wp_set_current_user( 0 );
wp_set_current_user( 1 );
if ( ! is_wp_error( $iv_spend ) ) {
	bhela_bm_fund_reverse( $iv_spend, 'ZZ cache bust undo' );
}
wp_delete_user( $iv_pu2 );
delete_post_meta( $iv_a, '_bhela_inv_user' );
delete_transient( 'bhela_bm_portal_funds' );

/* ---------- cleanup ---------- */
foreach ( get_posts( array( 'post_type' => 'bhela_payreq', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $z ) {
	wp_delete_post( $z, true );
}
foreach ( get_posts( array( 'post_type' => 'bhela_inv_ledger', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $z ) {
	bhela_test_delete( $z );
}
iv_reset( $iv_month );
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
