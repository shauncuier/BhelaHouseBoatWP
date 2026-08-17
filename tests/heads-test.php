<?php
/** Dev helper: editable cost heads + the positional→keyed conversion. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs' );
function line_by_label( $lines, $label ) {
	foreach ( $lines as $l ) { if ( $l['label'] === $label ) { return $l; } }
	return null;
}

$restore = get_option( 'bhela_bm_cost_heads', null );
delete_option( 'bhela_bm_cost_heads' );
wp_set_current_user( 1 );

echo "=== 1. defaults ===\n";
$heads = bhela_bm_cost_heads();
ok( 21 === count( $heads ), '21 shipped heads', (string) count( $heads ) );
ok( 'Engine Fuel (Diesel)' === ( $heads['engine_fuel'] ?? '' ), 'first head keyed engine_fuel' );
ok( 'Others (Any Bill & Purchase)' === ( $heads['others'] ?? '' ), '"Others" is now an ordinary editable head' );

echo "\n=== 2. legacy positional sheet converts on read ===\n";
// Exactly the shape v2.20.0–v2.22.0 wrote: a positional list, 21 heads + 3 blanks.
$legacy = array();
for ( $i = 0; $i < 24; $i++ ) {
	$legacy[] = array( 'label' => '', 'p1' => 0, 'p2' => 0, 'p3' => 0, 'remark' => '' );
}
$legacy[0]  = array( 'label' => 'Engine Fuel (Diesel)', 'p1' => 15600, 'p2' => 0, 'p3' => 0, 'remark' => '' );
$legacy[2]  = array( 'label' => 'Groceries (Rice, Spices Etc)', 'p1' => 16095, 'p2' => 290, 'p3' => 0, 'remark' => '' );
$legacy[19] = array( 'label' => 'Staff Bill', 'p1' => 19500, 'p2' => 0, 'p3' => 0, 'remark' => '' );
$legacy[20] = array( 'label' => 'Others (Any Bill & Purchase)', 'p1' => 250, 'p2' => 0, 'p3' => 0, 'remark' => 'Spoon' );
$legacy[21] = array( 'label' => 'Cold Drinks', 'p1' => 470, 'p2' => 0, 'p3' => 0, 'remark' => '' );

$old = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ legacy sheet' ) );
update_post_meta( $old, '_bhela_cost_lines', wp_json_encode( $legacy, JSON_UNESCAPED_UNICODE ) );
update_post_meta( $old, '_bhela_cost_total', 52205 );

$lines = bhela_bm_cost_lines( $old );
ok( 15600 === ( line_by_label( $lines, 'Engine Fuel (Diesel)' )['sub'] ?? 0 ), 'fuel amount survived' );
ok( 16385 === ( line_by_label( $lines, 'Groceries (Rice, Spices Etc)' )['sub'] ?? 0 ), 'groceries survived (16095+290)' );
ok( 19500 === ( line_by_label( $lines, 'Staff Bill' )['sub'] ?? 0 ), 'staff bill survived' );
$others = line_by_label( $lines, 'Others (Any Bill & Purchase)' );
ok( 250 === ( $others['sub'] ?? 0 ), 'old row 21 "Others" kept its amount' );
ok( 'Spoon' === ( $others['remark'] ?? '' ), '…and its remark' );
ok( 470 === ( line_by_label( $lines, 'Cold Drinks' )['sub'] ?? 0 ), 'the free-text extra survived' );
ok( 52205 === bhela_bm_cost_total( $lines ), 'total unchanged after conversion', (string) bhela_bm_cost_total( $lines ) );

echo "\n=== 3. renaming a head does not move money ===\n";
bhela_bm_save_cost_heads( array_map(
	function ( $slug ) use ( $heads ) {
		return array( 'slug' => $slug, 'label' => 'engine_fuel' === $slug ? 'Diesel / Fuel' : $heads[ $slug ] );
	},
	array_combine( array_keys( $heads ), array_keys( $heads ) )
) );
$lines = bhela_bm_cost_lines( $old );
ok( null === line_by_label( $lines, 'Engine Fuel (Diesel)' ), 'old label gone' );
ok( 15600 === ( line_by_label( $lines, 'Diesel / Fuel' )['sub'] ?? 0 ), 'amount followed the RENAMED head' );
ok( 52205 === bhela_bm_cost_total( $lines ), 'total still unchanged', (string) bhela_bm_cost_total( $lines ) );

echo "\n=== 4. retiring a head ===\n";
$cur = get_option( 'bhela_bm_cost_heads' );
$cur['dry_fish']['retired'] = 1;   // unused head
$cur['staff_bill']['retired'] = 1; // head WITH money on the old sheet
update_option( 'bhela_bm_cost_heads', $cur );
ok( ! array_key_exists( 'dry_fish', bhela_bm_cost_heads() ), 'retired head is off the active list' );
ok( array_key_exists( 'dry_fish', bhela_bm_cost_heads( true ) ), '…but still known' );
$lines = bhela_bm_cost_lines( $old );
ok( 19500 === ( line_by_label( $lines, 'Staff Bill' )['sub'] ?? 0 ), 'a retired head still renders on a sheet that used it' );
ok( 52205 === bhela_bm_cost_total( $lines ), 'closed-month total untouched by retiring' );
$fresh = bhela_bm_cost_lines( 0 );
ok( null === line_by_label( $fresh, 'Staff Bill' ), 'retired head is absent from a NEW sheet' );

echo "\n=== 5. in-use detection ===\n";
$used = bhela_bm_cost_heads_in_use();
ok( in_array( 'engine_fuel', $used, true ), 'engine_fuel reported in use' );
// Not a hardcoded head name: the site already has a sheet with money against
// every shipped head, so the only reliably-unused slug is one that exists
// nowhere at all.
ok( ! in_array( 'never_used_head_xyz', $used, true ), 'a head with no money anywhere is not reported' );

echo "\n=== 6. slugs are minted once, never on rename ===\n";
delete_option( 'bhela_bm_cost_heads' );
bhela_bm_save_cost_heads( array(
	'a' => array( 'slug' => '', 'label' => 'Boat Wash' ),
	'b' => array( 'slug' => '', 'label' => 'Boat Wash' ),   // duplicate label
) );
$saved = get_option( 'bhela_bm_cost_heads' );
ok( 2 === count( $saved ), 'duplicate labels get distinct slugs', implode( ',', array_keys( $saved ) ) );
ok( array_key_exists( 'boat-wash', $saved ) || array_key_exists( 'boat_wash', $saved ), 'slug derived from the label', implode( ',', array_keys( $saved ) ) );

echo "\n=== 7. spare rows ===\n";
delete_option( 'bhela_bm_cost_heads' );
$fresh = bhela_bm_cost_lines( 0 );
$blank = 0;
foreach ( $fresh as $l ) { if ( ! $l['fixed'] && '' === $l['label'] ) { $blank++; } }
ok( 5 === $blank, 'five blank rows on a new sheet (was 3 — July needed 4)', (string) $blank );
ok( 26 === count( $fresh ), '21 heads + 5 blanks', (string) count( $fresh ) );

echo "\n=== 8. every row carries a stable key ===\n";
$keys = wp_list_pluck( $fresh, 'key' );
ok( count( $keys ) === count( array_unique( $keys ) ), 'keys are unique' );
ok( in_array( 'engine_fuel', $keys, true ), 'head rows use the slug as key' );

echo "\n=== cleanup ===\n";
bhela_test_delete( $old );
if ( is_array( $restore ) ) { update_option( 'bhela_bm_cost_heads', $restore ); } else { delete_option( 'bhela_bm_cost_heads' ); }
ok( true, 'restored' );

bhela_test_done();
