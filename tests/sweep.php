<?php
/**
 * Delete fixtures left behind by a harness that died before its cleanup ran.
 *
 * Every harness prefixes the posts it creates with ZZ and removes them at the
 * end. A crash skips that, and the leftovers are not inert: a stale set of
 * approved cost sheets is counted by the next run, which is how "13 approved
 * trips this month" once became 26 and failed eight correct assertions.
 *
 * run.php calls this before each pass. Safe to run by hand at any time.
 *
 * @package BhelaBooking
 */

define( 'BHELA_TEST_QUIET', true );
require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'roles', 'costs', 'expenses', 'salary', 'audit', 'inventory-core', 'inventory' );

$removed = 0;
foreach ( array( 'bhela_cost', 'bhela_expense', 'bhela_salary', 'bhela_booking', 'bhela_review', 'bhela_inv_item', 'bhela_inv_period' ) as $type ) {
	$posts = get_posts( array(
		'post_type'      => $type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	) );
	foreach ( $posts as $post ) {
		if ( 0 !== strpos( $post->post_title, 'ZZ' ) ) {
			continue;
		}
		bhela_test_delete( $post->ID );
		$removed++;
		printf( "  removed %s #%d %s\n", $type, $post->ID, $post->post_title );
	}
}

// The register keeps a month => post-ID index and an ID counter in options. A
// swept period leaves both pointing at nothing, and the next run would then carry
// a previous run's opening balances forward into its own fixtures — which looks
// exactly like a broken carry-forward. Rebuild rather than trust them.
if ( function_exists( 'bhela_bm_inv_period_reindex' ) ) {
	$index = get_option( 'bhela_bm_inv_periods', array() );
	$live  = array();
	foreach ( (array) $index as $month => $id ) {
		if ( get_post( $id ) ) {
			$live[ $month ] = (int) $id;
		}
	}
	if ( $live !== (array) $index ) {
		update_option( 'bhela_bm_inv_periods', $live, false );
		printf( "  rebuilt the stock period index (%d stale entr%s dropped)\n",
			count( (array) $index ) - count( $live ),
			1 === count( (array) $index ) - count( $live ) ? 'y' : 'ies'
		);
	}
}

printf( "%d fixture(s) swept\n", $removed );
bhela_test_done();
