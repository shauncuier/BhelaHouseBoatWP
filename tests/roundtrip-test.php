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

echo "\n=== 4. approved sheet still refuses writes ===\n";
update_post_meta( $sheet, '_bhela_cost_status', 'approved' );
$locked_total = (int) get_post_meta( $sheet, '_bhela_cost_total', true );
save( $sheet, array( 'engine_fuel' => array( 'p1' => 999999 ) ) );
ok( $locked_total === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'locked sheet unchanged' );

bhela_test_delete( $sheet );
bhela_test_done();
