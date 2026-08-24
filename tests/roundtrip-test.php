<?php
/** Dev helper: save-handler round trip on the new keyed rows, incl. the custom-row cap. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs' );
function by_label( $lines, $label ) { foreach ( $lines as $l ) { if ( $l['label'] === $label ) return $l; } return null; }

wp_set_current_user( 1 );
$sheet = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ roundtrip' ) );

function save( $sheet, $lines ) {
	$_POST = array(
		'bhela_bm_cost_nonce'  => wp_create_nonce( 'bhela_bm_cost_save' ),
		'bhela_cost_trip_date' => '2026-07-01',
		'bhela_cost_header'    => array( 'trip_id' => 'WJ3-2601', 'total_guest' => '25' ),
		'bhela_cost_lines'     => $lines,
		'bhela_cost_earnings'  => '142400',
	);
	bhela_bm_cost_save( $sheet, get_post( $sheet ) );
	$_POST = array();
}

echo "=== 1. keyed save round-trips ===\n";
save( $sheet, array(
	'engine_fuel'   => array( 'p1' => 15600, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'groceries'     => array( 'p1' => 16095, 'p2' => 290, 'p3' => 0, 'remark' => '' ),
	'staff_bill'    => array( 'p1' => 19500, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_spoon'  => array( 'label' => 'Spoon', 'p1' => 250, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_batt'   => array( 'label' => 'Pencil Battary', 'p1' => 100, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_elec'   => array( 'label' => 'Electric Materials', 'p1' => 1260, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_cold'   => array( 'label' => 'Cold Drinks', 'p1' => 470, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'blank_one'     => array( 'label' => '', 'p1' => 0, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
) );
$total = (int) get_post_meta( $sheet, '_bhela_cost_total', true );
ok( 53565 === $total, 'total = 15600+16385+19500+250+100+1260+470', (string) $total );

$stored = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
ok( ! isset( $stored[0] ), 'stored as a KEYED map, not positional', implode( ',', array_slice( array_keys( $stored ), 0, 4 ) ) );
ok( ! array_key_exists( 'blank_one', $stored ), 'untouched blank row was dropped, not accumulated' );
ok( 4 === count( array_filter( array_keys( $stored ), function ( $k ) { return 0 === strpos( $k, 'custom_' ); } ) ), 'all four July one-offs kept' );

$lines = bhela_bm_cost_lines( $sheet );
ok( 250 === ( by_label( $lines, 'Spoon' )['sub'] ?? 0 ), 'Spoon renders back' );
ok( 1260 === ( by_label( $lines, 'Electric Materials' )['sub'] ?? 0 ), 'Electric Materials renders back' );

echo "\n=== 2. saving again is stable (no drift, no duplicates) ===\n";
$before = get_post_meta( $sheet, '_bhela_cost_lines', true );
$again  = array();
foreach ( bhela_bm_cost_lines( $sheet ) as $l ) {
	$again[ $l['key'] ] = array( 'label' => $l['label'], 'p1' => $l['p1'], 'p2' => $l['p2'], 'p3' => $l['p3'], 'remark' => $l['remark'] );
}
save( $sheet, $again );
ok( 53565 === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'total identical after re-save' );
$after = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
ok( 7 === count( $after ), 'still 7 rows, no blank accumulation', (string) count( $after ) );

echo "\n=== 3. custom-row cap holds against a crafted POST ===\n";
$flood = array( 'engine_fuel' => array( 'p1' => 100 ) );
for ( $i = 0; $i < 60; $i++ ) {
	$flood[ 'x_' . $i ] = array( 'label' => 'Junk ' . $i, 'p1' => 1 );
}
save( $sheet, $flood );
$after = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
$customs = count( array_filter( array_keys( $after ), function ( $k ) { return 0 === strpos( $k, 'x_' ); } ) );
ok( $customs <= bhela_bm_cost_max_custom_rows(), 'capped at ' . bhela_bm_cost_max_custom_rows(), (string) $customs );

echo "\n=== 3b. the B2B line fills itself, and is not double counted ===\n";
// The commission is entered once, on the booking. The cost sheet's B2B Partner line
// fills from it — so the same 3,000 cannot be deducted once by the sheet and again
// by the statement reading the bookings.
bhela_bm_save_agencies( array(
	array( 'id' => '', 'name' => 'ZZ RT Agency', 'phone' => '', 'email' => '', 'rate' => 10 ),
) );
$rt_ag = '';
foreach ( bhela_bm_agencies() as $aid => $arow ) {
	if ( 'ZZ RT Agency' === $arow['name'] ) {
		$rt_ag = $aid;
	}
}
$rt_book = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZ rt b2b' ) );
update_post_meta( $rt_book, '_bhela_travel_date', '2026-07-01' );
update_post_meta( $rt_book, '_bhela_status', 'confirmed' );
update_post_meta( $rt_book, '_bhela_total', 30000 );
update_post_meta( $rt_book, '_bhela_agency', $rt_ag );
update_post_meta( $rt_book, '_bhela_commission', 3000 );

update_post_meta( $sheet, '_bhela_cost_status', 'draft' );   // §4 below locks it again
save( $sheet, array( 'engine_fuel' => array( 'p1' => 5000 ) ) );
$rt_lines = bhela_bm_cost_stored_lines( $sheet );
ok( 3000 === (int) ( $rt_lines['b2b_partner']['p1'] ?? 0 ), 'the B2B line filled itself from the booking',
	(string) ( $rt_lines['b2b_partner']['p1'] ?? 0 ) );
ok( 8000 === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'and is included in the sheet total once',
	get_post_meta( $sheet, '_bhela_cost_total', true ) );

// Move the commission underneath the sheet: it must REPORT, never silently rewrite.
// Same contract as the earnings drift, and for the same reason — three people sign
// off a sheet, and changing a figure they approved without saying so is worse than
// showing it is out of date.
update_post_meta( $rt_book, '_bhela_commission', 4200 );
$rt_drift = bhela_bm_cost_b2b_drift( $sheet );
ok( $rt_drift['stale'], 'a changed commission is reported as stale' );
ok( 3000 === (int) $rt_drift['stored'] && 4200 === (int) $rt_drift['live'], 'with both figures named',
	$rt_drift['stored'] . ' → ' . $rt_drift['live'] );
ok( 3000 === (int) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'], 'and the sheet is NOT rewritten behind the owner' );

// A figure typed over by hand is a decision, not a cache — leave it alone entirely.
save( $sheet, array( 'engine_fuel' => array( 'p1' => 5000 ), 'b2b_partner' => array( 'p1' => 9999 ) ) );
ok( 9999 === (int) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'], 'a hand-typed B2B figure survives a save',
	(string) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'] );
ok( ! bhela_bm_cost_b2b_drift( $sheet )['stale'], 'and is not reported stale — it was never auto-filled' );

bhela_test_delete( $rt_book );
// The agency directory is NOT deleted here. It is owner-built data with live
// referral tokens in it, and a delete_option() on the way out took a real partner
// with it once already. bhela_test_owner_options() in the bootstrap snapshots and
// restores it, so this harness can write to it freely and leave nothing behind.

echo "\n=== 4. approved sheet still refuses writes ===\n";
update_post_meta( $sheet, '_bhela_cost_status', 'approved' );
$locked_total = (int) get_post_meta( $sheet, '_bhela_cost_total', true );
save( $sheet, array( 'engine_fuel' => array( 'p1' => 999999 ) ) );
ok( $locked_total === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'locked sheet unchanged' );

bhela_test_delete( $sheet );
bhela_test_done();
