<?php
/** Dev helper: the Yearly Report's rollup, year shapes, and agreement with the months. */
require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs', 'expenses', 'statement', 'yearly' );

wp_set_current_user( 1 );
$made = array();

echo "\n=== 1. year shapes ===\n";
$fin = bhela_bm_yearly_months( '2026', 'financial' );
ok( '2026-07' === $fin[0] && '2027-06' === $fin[11], 'financial year runs Jul 2026 → Jun 2027', $fin[0] . ' … ' . $fin[11] );
ok( 12 === count( $fin ), 'twelve months' );
$cal = bhela_bm_yearly_months( '2026', 'calendar' );
ok( '2026-01' === $cal[0] && '2026-12' === $cal[11], 'calendar year runs Jan → Dec 2026', $cal[0] . ' … ' . $cal[11] );
ok( '2026–27' === bhela_bm_yearly_label( '2026', 'financial' ), 'financial year is labelled 2026–27', bhela_bm_yearly_label( '2026', 'financial' ) );
ok( '2026' === bhela_bm_yearly_label( '2026', 'calendar' ), 'calendar year is labelled 2026' );
ok( '' === bhela_bm_yearly_year( '20x6' ) && '2026' === bhela_bm_yearly_year( '2026' ), 'year input is validated' );

echo "\n=== 2. a year with three trading months ===\n";
// Two approved trips in July, one in September, one still unapproved in August.
$spec = array(
	array( '2026-07-10', 'approved', 120000, 70000, 24 ),
	array( '2026-07-24', 'approved', 150000, 80000, 30 ),
	array( '2026-08-14', 'checked',  100000, 60000, 20 ),  // not approved — must be excluded
	array( '2026-09-05', 'approved',  90000, 55000, 18 ),
);
foreach ( $spec as $i => $row ) {
	list( $date, $status, $earn, $cost, $guests ) = $row;
	$id = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZY sheet ' . $i ) );
	$made[] = $id;
	update_post_meta( $id, '_bhela_cost_trip_date', $date );
	update_post_meta( $id, '_bhela_cost_status', $status );
	update_post_meta( $id, '_bhela_cost_earnings', $earn );
	update_post_meta( $id, '_bhela_cost_total', $cost );
	update_post_meta( $id, '_bhela_cost_header', wp_json_encode( array( 'total_guest' => (string) $guests ) ) );
}
// One expense in July.
$exp = wp_insert_post( array( 'post_type' => 'bhela_expense', 'post_status' => 'publish', 'post_title' => 'ZZY expense' ) );
$made[] = $exp;
update_post_meta( $exp, '_bhela_exp_date', '2026-07-19' );
update_post_meta( $exp, '_bhela_exp_amount', 40000 );
update_post_meta( $exp, '_bhela_exp_type', 'marketing' );

$d = bhela_bm_yearly_data( '2026', 'financial' );
$t = $d['totals'];
printf( "  trips=%d guests=%d earnings=%s cost=%s expenses=%s gross=%s\n",
	$t['trips'], $t['guests'], bhela_bm_money( $t['earnings'] ),
	bhela_bm_money( $t['cost'] ), bhela_bm_money( $t['expenses'] ), bhela_bm_money( $t['gross'] ) );

ok( 3 === $t['trips'], '3 approved trips counted, the unapproved one excluded', (string) $t['trips'] );
ok( 72 === $t['guests'], 'guests 24+30+18 = 72', (string) $t['guests'] );
ok( 360000 === $t['earnings'], 'earnings 120k+150k+90k = 360,000', bhela_bm_money( $t['earnings'] ) );
ok( 205000 === $t['cost'], 'trip cost 70k+80k+55k = 205,000', bhela_bm_money( $t['cost'] ) );
ok( 155000 === $t['profit'], 'trip profit = 155,000', bhela_bm_money( $t['profit'] ) );
ok( 40000 === $t['expenses'], 'expenses = 40,000', bhela_bm_money( $t['expenses'] ) );
ok( 115000 === $t['gross'], 'gross = 155,000 − 40,000 = 115,000', bhela_bm_money( $t['gross'] ) );
ok( 1 === $d['pending'], 'the unapproved sheet is reported, not silently dropped', (string) $d['pending'] );

echo "\n=== 3. the year agrees with the months it summarises ===\n";
// The whole reason the rollup calls bhela_bm_statement_data() per month.
$sum = 0;
foreach ( bhela_bm_yearly_months( '2026', 'financial' ) as $key ) {
	$sum += (int) bhela_bm_statement_data( $key )['gross'];
}
ok( $sum === $t['gross'], 'summing the twelve statements gives the year total', bhela_bm_money( $sum ) );
$july = null;
foreach ( $d['months'] as $m ) {
	if ( '2026-07' === $m['key'] ) { $july = $m; }
}
ok( $july && 2 === $july['trips'], 'July shows its 2 trips' );
ok( $july && 80000 === $july['gross'], 'July gross = 120,000 profit − 40,000 expenses = 80,000', bhela_bm_money( $july['gross'] ) );

echo "\n=== 4. derived figures ===\n";
ok( abs( $t['margin'] - 31.9 ) < 0.05, 'margin = 115,000 / 360,000 = 31.9%', (string) $t['margin'] );
ok( abs( $t['cost_pp'] - round( 245000 / 72, 2 ) ) < 0.01, 'cost per guest includes expenses', (string) $t['cost_pp'] );
ok( abs( $t['profit_pp'] - round( 115000 / 72, 2 ) ) < 0.01, 'profit per guest', (string) $t['profit_pp'] );

echo "\n=== 4b. a loss reads as a loss ===\n";
// bhela_bm_money() used to concatenate the symbol onto number_format(), so a
// negative came out "৳-215,200" — the sign landing after the symbol. Every
// screen that can show a loss was affected, the monthly statement included.
ok( '-৳215,200' === bhela_bm_money( -215200 ), 'sign goes before the symbol', bhela_bm_money( -215200 ) );
ok( '৳215,200' === bhela_bm_money( 215200 ), 'a positive is unchanged', bhela_bm_money( 215200 ) );
ok( '৳0' === bhela_bm_money( 0 ), 'zero carries no sign', bhela_bm_money( 0 ) );
ok( '-৳1,318' === bhela_bm_money( -1318.4 ), 'a negative float rounds without stranding the sign', bhela_bm_money( -1318.4 ) );
ok( false === strpos( bhela_bm_money( -5000 ), '৳-' ), 'the old shape never comes back' );

echo "\n=== 5. best and worst skip months with no trips ===\n";
ok( $d['best'] && '2026-07' === $d['best']['key'], 'best month is July', $d['best']['key'] ?? '—' );
ok( $d['worst'] && '2026-09' === $d['worst']['key'], 'worst TRADING month is September, not an empty one', $d['worst']['key'] ?? '—' );

echo "\n=== 6. the calendar year sees a different slice ===\n";
$cal = bhela_bm_yearly_data( '2026', 'calendar' );
ok( 3 === $cal['totals']['trips'], 'Jan–Dec 2026 also holds all three trips', (string) $cal['totals']['trips'] );
$cal27 = bhela_bm_yearly_data( '2027', 'calendar' );
ok( 0 === $cal27['totals']['trips'], '2027 is empty', (string) $cal27['totals']['trips'] );
ok( 0 === $cal27['totals']['gross'] && null === $cal27['best'], 'an empty year totals zero and names no best month' );

echo "\n=== 7. the year picker offers the years that exist ===\n";
$avail = bhela_bm_yearly_available( 'financial' );
ok( in_array( 2026, $avail, true ), '2026 offered', implode( ', ', $avail ) );
ok( count( $avail ) === count( array_unique( $avail ) ), 'no duplicates' );
ok( $avail === array_values( array_reverse( array_unique( array_reverse( $avail ) ) ) ), 'newest first' );

echo "\n=== 8. the screen renders ===\n";
$_GET = array( 'page' => 'bhela-bm-yearly', 'year' => '2026', 'mode' => 'financial' );
set_current_screen( 'bhela_booking_page_bhela-bm-yearly' );
ob_start();
bhela_bm_yearly_page();
$html = ob_get_clean();
foreach ( array( 'Fatal error', 'Warning:', 'Notice:', 'Deprecated:', '<style>' ) as $bad ) {
	ok( false === strpos( $html, $bad ), "no '$bad'" );
}
ok( false !== strpos( $html, 'bha-head' ), 'uses the shared screen header' );
ok( false !== strpos( $html, 'YEAR TOTAL' ), 'renders the total row' );
ok( false !== strpos( $html, '৳' ), 'money goes through bhela_bm_money()' );
preg_match_all( '/\b\d{1,3}(,\d{3})+\b/u', $html, $plain );
$bare = array_filter( $plain[0], fn( $n ) => false === strpos( $html, '৳' . $n ) );
ok( ! $bare, 'every grouped figure carries the symbol', implode( ' ', array_slice( $bare, 0, 4 ) ) );

echo "\n=== 8b. a sheet with no trip date ===\n";
// Live sheet #257 held ৳3,318,506 with no date: counted by nothing, flagged by
// nothing. Every report selects on the trip date, so it belonged to no month
// and no year and no screen could ever have shown it.
$un = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZY undated' ) );
$made[] = $un;
update_post_meta( $un, '_bhela_cost_status', 'checked' );
update_post_meta( $un, '_bhela_cost_total', 3318506 );
update_post_meta( $un, '_bhela_cost_earnings', 0 );

ok( in_array( $un, wp_list_pluck( bhela_bm_cost_undated(), 'id' ), true ), 'bhela_bm_cost_undated() finds it' );

// Its money must appear in no year, under either year shape. The three
// approved fixtures above contribute ৳205,000 to FY2026 and CY2026 and nothing
// elsewhere; the undated sheet must not move any of those figures.
$expected = array(
	'financial' => array( '2025' => 0, '2026' => 205000, '2027' => 0 ),
	'calendar'  => array( '2025' => 0, '2026' => 205000, '2027' => 0 ),
);
$leaked = array();
foreach ( $expected as $mode => $years ) {
	foreach ( $years as $y => $want ) {
		$got = (int) bhela_bm_yearly_data( (string) $y, $mode )['totals']['cost'];
		if ( $got !== $want ) {
			$leaked[] = sprintf( '%s %s: %d not %d', $mode, $y, $got, $want );
		}
	}
}
ok( ! $leaked, 'its ৳3,318,506 reaches no year, in either year shape', implode( ' | ', $leaked ) );

// The approval guard. Tested through the predicate both the transition and the
// sidebar call — invoking the transition itself would exit() and take the
// harness with it.
ok( ! bhela_bm_cost_can_approve( $un ), 'an undated sheet cannot be approved' );
update_post_meta( $un, '_bhela_cost_trip_date', '   ' );
ok( ! bhela_bm_cost_can_approve( $un ), 'whitespace is not a date either' );

// With a real date the guard lifts, the flag clears, and the money lands.
update_post_meta( $un, '_bhela_cost_trip_date', '2026-07-15' );
update_post_meta( $un, '_bhela_cost_status', 'approved' );
ok( bhela_bm_cost_can_approve( $un ), 'dating it allows approval — the guard is about the date, not a block' );
ok( ! in_array( $un, wp_list_pluck( bhela_bm_cost_undated(), 'id' ), true ), 'and clears the undated flag' );
ok( 205000 + 3318506 === (int) bhela_bm_yearly_data( '2026', 'financial' )['totals']['cost'],
	'its cost now counts towards July 2026',
	bhela_bm_money( bhela_bm_yearly_data( '2026', 'financial' )['totals']['cost'] ) );

echo "\n=== 8c. earnings that went stale after sign-off ===\n";
// Earnings are captured when the sheet is saved. Cancel a booking afterwards
// and the approved sheet keeps the old figure, which the statement keeps
// reporting — a trip that lost money still shows it, with nothing to say so.
$st = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZY stale' ) );
$made[] = $st;
update_post_meta( $st, '_bhela_cost_trip_date', '2026-10-10' );
update_post_meta( $st, '_bhela_cost_status', 'approved' );
update_post_meta( $st, '_bhela_cost_total', 50000 );
// The booking that existed when the sheet was signed off.
$bk = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZY guest' ) );
$made[] = $bk;
update_post_meta( $bk, '_bhela_travel_date', '2026-10-10' );
update_post_meta( $bk, '_bhela_status', 'confirmed' );
update_post_meta( $bk, '_bhela_total', 200000 );

// Saved from that booking, and never edited by hand.
update_post_meta( $st, '_bhela_cost_earnings', 200000 );
update_post_meta( $st, '_bhela_cost_earnings_auto', 200000 );

ok( ! bhela_bm_cost_earnings_drift( $st )['stale'], 'no drift while the bookings still agree' );

// The guest cancels after sign-off. Cancelled bookings stop counting, so the
// trip is now worth 174,000 while the signed sheet still says 200,000.
update_post_meta( $bk, '_bhela_status', 'cancelled' );
$bk2 = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZY guest two' ) );
$made[] = $bk2;
update_post_meta( $bk2, '_bhela_travel_date', '2026-10-10' );
update_post_meta( $bk2, '_bhela_status', 'confirmed' );
update_post_meta( $bk2, '_bhela_total', 174000 );

$d1 = bhela_bm_cost_earnings_drift( $st );
ok( $d1['stale'], 'drift detected once the bookings no longer match the signed figure' );
ok( 200000 === $d1['stored'], 'the signed figure is reported unchanged', bhela_bm_money( $d1['stored'] ) );
ok( 174000 === $d1['live'], 'alongside what the bookings now say', bhela_bm_money( $d1['live'] ) );

// A hand-typed figure is a decision, not a cache — it must never be flagged.
update_post_meta( $st, '_bhela_cost_earnings', 190000 );
ok( ! bhela_bm_cost_earnings_drift( $st )['stale'], 'a manually overridden figure is left alone' );
update_post_meta( $st, '_bhela_cost_earnings', 200000 );

// The statement reports it without altering the total it counts.
$oct = bhela_bm_statement_data( '2026-10' );
ok( 1 === count( $oct['stale'] ), 'the statement lists it', (string) count( $oct['stale'] ) );
ok( 200000 === (int) $oct['earnings'], 'and still counts the signed figure, not the live one', bhela_bm_money( $oct['earnings'] ) );

// The yearly rollup inherits the count, since it is twelve statements.
ok( 1 === bhela_bm_yearly_data( '2026', 'financial' )['stale'], 'the yearly report carries it through' );


echo "\n=== 9. permissions ===\n";
ok( get_role( 'bhela_manager' )->has_cap( 'bhela_view_statement' ), 'a manager with the statement permission can open it' );
ok( ! get_role( 'bhela_booking_staff' )->has_cap( 'bhela_view_statement' ), 'booking staff cannot' );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) { wp_delete_post( $id, true ); }
ok( 0 === bhela_bm_yearly_data( '2026', 'financial' )['totals']['trips'], 'fixtures removed' );

bhela_test_done();
