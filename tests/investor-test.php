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

/** Clear anything an earlier pass committed — a run survives deletion by design. */
function iv_reset( $month ) {
	$idx = get_option( 'bhela_bm_dist_runs', array() );
	if ( is_array( $idx ) && ! empty( $idx[ $month ] ) ) {
		bhela_test_delete( (int) $idx[ $month ] );
		unset( $idx[ $month ] );
		update_option( 'bhela_bm_dist_runs', $idx, false );
	}
	foreach ( array( 'bhela_inv_ledger', 'bhela_fund' ) as $type ) {
		foreach ( get_posts( array(
			'post_type' => $type, 'post_status' => 'publish',
			'posts_per_page' => -1, 'fields' => 'ids',
		) ) as $z ) {
			bhela_test_delete( $z );
		}
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

echo "\n=== 13. the reserve and management funds fill themselves ===\n";
// Before this the reserve and management shares existed only as meta on a run: money
// set aside on paper and then untracked. They are ledger rows now, so "what is left
// in the reserve" has an answer that adds up.
$iv_res = bhela_bm_fund_ledger( 'reserve' );
$iv_mgt = bhela_bm_fund_ledger( 'management' );
ok( 30000 === $iv_res['allocated'], 'committing the month allocated the 10% reserve', (string) $iv_res['allocated'] );
ok( 81000 === $iv_mgt['allocated'], 'and the management 30%', (string) $iv_mgt['allocated'] );
ok( 30000 === $iv_res['closing'] && 81000 === $iv_mgt['closing'], 'both balances start at what was allocated' );

// The three shares must still add up to the gross they came from.
ok( $iv_res['allocated'] + $iv_mgt['allocated'] + 189000 === 300000,
	'reserve + management + investor pool = gross, to the taka',
	$iv_res['allocated'] . '+' . $iv_mgt['allocated'] . '+189000' );

// An allocation is arithmetic, not a decision — it cannot be typed in.
$iv_hand = bhela_bm_fund_add( array( 'fund' => 'reserve', 'type' => 'allocation', 'amount' => 50000 ) );
ok( is_wp_error( $iv_hand ) && 'no_run' === $iv_hand->get_error_code(),
	'an allocation cannot be entered by hand — that would create money no month set aside' );

// Nor can a run be allocated twice.
bhela_bm_fund_allocate_run( $iv_run );
ok( 30000 === bhela_bm_fund_ledger( 'reserve' )['allocated'], 'and a run cannot be allocated twice',
	(string) bhela_bm_fund_ledger( 'reserve' )['allocated'] );

echo "\n=== 14. spending against a fund ===\n";
$iv_spend = bhela_bm_fund_add( array(
	'fund' => 'reserve', 'type' => 'utilisation', 'amount' => 12000,
	'head' => 'renovation', 'date' => $iv_month . '-15', 'note' => 'ZZ deck repair',
) );
ok( ! is_wp_error( $iv_spend ), 'spending is recorded' );
$iv_res = bhela_bm_fund_ledger( 'reserve' );
ok( 18000 === $iv_res['closing'], 'and comes off the balance: 30,000 − 12,000', (string) $iv_res['closing'] );
ok( 12000 === $iv_res['used'] && 12000 === ( $iv_res['by_head']['renovation'] ?? 0 ), 'attributed to its head' );

// Overdrawing is recorded, not blocked. The spending happened; refusing to record it
// would just move the error somewhere the books cannot see.
bhela_bm_fund_add( array( 'fund' => 'reserve', 'type' => 'utilisation', 'amount' => 25000, 'head' => 'emergency', 'date' => $iv_month . '-16', 'note' => 'ZZ engine' ) );
ok( -7000 === bhela_bm_fund_ledger( 'reserve' )['closing'], 'an overdrawn fund goes negative rather than refusing the entry',
	(string) bhela_bm_fund_ledger( 'reserve' )['closing'] );

// A wrong entry is reversed, never edited.
$iv_frev = bhela_bm_fund_reverse( $iv_spend, 'ZZ wrong head' );
ok( ! is_wp_error( $iv_frev ), 'spending can be reversed with a reason' );
ok( 5000 === bhela_bm_fund_ledger( 'reserve' )['closing'], 'and the balance comes back', (string) bhela_bm_fund_ledger( 'reserve' )['closing'] );
ok( 0 === ( bhela_bm_fund_ledger( 'reserve' )['by_head']['renovation'] ?? 0 ), 'the reversed spend stops counting against its head' );
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

/* ---------- cleanup ---------- */
foreach ( array( 'bhela_inv_ledger', 'bhela_fund' ) as $z_type ) {
	foreach ( get_posts( array( 'post_type' => $z_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $z ) {
		bhela_test_delete( $z );
	}
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
