<?php
/** Dev helper: rebuild July 2026 from the PDFs and check the statement matches the owner's. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs', 'expenses', 'statement' );
wp_set_current_user( 1 );
$made = array();

// The 13 trips exactly as the Monthly Statement PDF lists them.
$trips = array(
	array( '2026-07-01', 'WJ3-2601', 25, 142400, 71285 ),
	array( '2026-07-03', 'J3-2602',  25, 151700, 75225 ),
	array( '2026-07-05', 'J3-2603',  27, 148000, 72608 ),
	array( '2026-07-08', 'J3-2604',  25, 144900, 135731 ),
	array( '2026-07-10', 'WJ3-2605', 27, 178000, 75015 ),
	array( '2026-07-12', 'J3-2606',  32, 164500, 85350 ),
	array( '2026-07-15', 'J3-2607',  26, 140000, 100189 ),
	array( '2026-07-17', 'WJ3-2608', 26, 150000, 79241 ),
	array( '2026-07-19', 'J3-2609',  17, 84500,  63534 ),
	array( '2026-07-21', 'J3-2610',  30, 150000, 72545 ),
	array( '2026-07-24', 'WJ3-2611', 29, 187000, 81490 ),
	array( '2026-07-26', 'J3-2612',  22, 132000, 68757 ),
	array( '2026-07-29', 'J3-2613',  24, 149500, 106627 ),
);
foreach ( $trips as $t ) {
	list( $date, $tid, $guests, $earn, $cost ) = $t;
	$id = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZJ ' . $tid ) );
	$made[] = $id;
	update_post_meta( $id, '_bhela_cost_trip_date', $date );
	update_post_meta( $id, '_bhela_cost_header', wp_json_encode( array( 'trip_id' => $tid, 'total_guest' => (string) $guests ) ) );
	update_post_meta( $id, '_bhela_cost_earnings', $earn );
	update_post_meta( $id, '_bhela_cost_total', $cost );
	update_post_meta( $id, '_bhela_cost_status', 'approved' );
}

// What the Monthly Statement actually deducts. Note this is NOT every line in
// the marketing & renovation report: that report also lists the raw renovation
// spend (৳79,460 on 5 July, ৳40,795 in June), while the statement takes the
// ৳250,000 *adjustment* drawn from it — the report's own last row says
// "Adjusted from Renovation Report". Deducting both would double-count.
$expenses = array(
	array( '2026-07-10', 'boosting',   15326 ),
	array( '2026-07-20', 'boosting',   14923 ),
	array( '2026-07-31', 'boosting',   16440 ),
	array( '2026-07-30', 'website',    40000 ),
	array( '2026-07-30', 'renovation', 250000 ),
);
foreach ( $expenses as $e ) {
	$id = wp_insert_post( array( 'post_type' => 'bhela_expense', 'post_status' => 'publish', 'post_title' => 'ZZJ exp' ) );
	$made[] = $id;
	update_post_meta( $id, '_bhela_exp_date', $e[0] );
	update_post_meta( $id, '_bhela_exp_type', $e[1] );
	update_post_meta( $id, '_bhela_exp_amount', $e[2] );
}

echo "=== July 2026 rebuilt from the PDFs ===\n";
$d = bhela_bm_statement_data( '2026-07' );
printf( "  trips=%d guests=%d earnings=%d cost=%d profit=%d expenses=%d gross=%d\n",
	count( $d['trips'] ), $d['guests'], $d['earnings'], $d['cost'], $d['profit'], $d['expenses']['total'], $d['gross'] );

ok( 13 === count( $d['trips'] ), '13 trips' );
ok( 335 === $d['guests'], '335 guests', (string) $d['guests'] );
ok( 1922500 === $d['earnings'], 'revenue = 1,922,500', number_format( $d['earnings'] ) );
ok( 1087597 === $d['cost'], 'trip cost = 1,087,597', number_format( $d['cost'] ) );
ok( 834903 === $d['profit'], 'trip profit = 834,903', number_format( $d['profit'] ) );

echo "\n=== deductions come from the expense log, grouped by type ===\n";
foreach ( $d['expenses']['by_type'] as $slug => $amt ) { printf( "    %-12s %s\n", $slug, number_format( $amt ) ); }
$boosting = $d['expenses']['by_type']['boosting'] ?? 0;
$website  = $d['expenses']['by_type']['website'] ?? 0;
ok( 46689 === $boosting, 'boosting = 46,689 (the real July ad spend)', number_format( $boosting ) );
ok( 86689 === $boosting + $website, 'boosting + website = 86,689, the PDF Digital Marketing figure', number_format( $boosting + $website ) );

echo "\n=== unapproved sheets are excluded and flagged ===\n";
$draft = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZJ draft' ) );
$made[] = $draft;
update_post_meta( $draft, '_bhela_cost_trip_date', '2026-07-31' );
update_post_meta( $draft, '_bhela_cost_earnings', 999999 );
update_post_meta( $draft, '_bhela_cost_total', 111111 );
update_post_meta( $draft, '_bhela_cost_status', 'prepared' );
$d2 = bhela_bm_statement_data( '2026-07' );
ok( 1922500 === $d2['earnings'], 'a prepared sheet does NOT move the month', number_format( $d2['earnings'] ) );
ok( 1 === count( $d2['pending'] ), 'it is reported as pending instead' );
bhela_test_delete( $draft );

echo "\n=== a new expense type appears with no code change ===\n";
update_option( 'bhela_bm_expense_types', array(
	'boosting'   => array( 'label' => 'Boosting' ),
	'renovation' => array( 'label' => 'Renovation' ),
	'website'    => array( 'label' => 'Website' ),
	'other'      => array( 'label' => 'Other' ),
	'legal'      => array( 'label' => 'Legal & Licence' ),
) );
$id = wp_insert_post( array( 'post_type' => 'bhela_expense', 'post_status' => 'publish', 'post_title' => 'ZZJ legal' ) );
$made[] = $id;
update_post_meta( $id, '_bhela_exp_date', '2026-07-15' );
update_post_meta( $id, '_bhela_exp_type', 'legal' );
update_post_meta( $id, '_bhela_exp_amount', 5000 );
$d3 = bhela_bm_statement_data( '2026-07' );
ok( isset( $d3['expenses']['by_type']['legal'] ), 'new type became its own deduction row' );
ok( $d3['gross'] === $d['gross'] - 5000, 'and it reduced gross profit', number_format( $d3['gross'] ) );
delete_option( 'bhela_bm_expense_types' );
bhela_test_delete( $id );

echo "\n=== the owner's bottom line, reproduced ===\n";
$d = bhela_bm_statement_data( '2026-07' );
ok( 336689 === $d['expenses']['total'], 'deductions = 336,689 (ads 46,689 + web 40,000 + renovation 250,000)', number_format( $d['expenses']['total'] ) );
ok( 498214 === $d['gross'], 'GROSS PROFIT = 498,214 — matches the printed statement', number_format( $d['gross'] ) );
ok( abs( $d['cost_pp'] - 4251.60 ) < 0.01, 'cost per person = 4,251.60', (string) $d['cost_pp'] );
ok( abs( $d['profit_pp'] - 1487.21 ) < 0.01, 'profit per person = 1,487.21', (string) $d['profit_pp'] );

echo "\n=== permissions ===\n";
// A role the owner has customised keeps their choices — a new permission in a
// later release must NOT widen it silently. So assert the contract both ways.
$saved_override = get_option( 'bhela_bm_role_perms', array() );
delete_option( 'bhela_bm_role_perms' );
bhela_bm_install_roles();
foreach ( array( 'bhela_manager' => true, 'bhela_booking_staff' => false, 'bhela_cost_preparer' => false ) as $role => $want ) {
	$r = get_role( $role );
	ok( $r && $r->has_cap( 'bhela_view_statement' ) === $want, sprintf( 'default: %s %s see the statement', $role, $want ? 'CAN' : 'CANNOT' ) );
	ok( $r && $r->has_cap( 'edit_bhela_expenses' ) === $want, sprintf( 'default: %s %s record expenses', $role, $want ? 'CAN' : 'CANNOT' ) );
}
ok( get_role( 'administrator' )->has_cap( 'bhela_view_statement' ), 'administrator can' );

if ( $saved_override ) {
	update_option( 'bhela_bm_role_perms', $saved_override );
	bhela_bm_install_roles();
	ok( ! get_role( 'bhela_manager' )->has_cap( 'bhela_view_statement' ),
		'a customised role does NOT silently gain a new permission' );
}

echo "\n=== screen renders ===\n";
$_GET['month'] = '2026-07';
ob_start(); bhela_bm_statement_page(); $html = ob_get_clean();
foreach ( array( 'Fatal error', 'Warning:', 'Notice:' ) as $bad ) { ok( false === strpos( $html, $bad ), "no '$bad'" ); }
ok( false !== strpos( $html, 'Gross Profit' ), 'renders the gross profit row' );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) { bhela_test_delete( $id ); }
ok( 0 === count( bhela_bm_statement_data( '2026-07' )['trips'] ), 'test data removed' );

bhela_test_done();
