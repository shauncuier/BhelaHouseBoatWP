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
bhela_test_modules( 'roles', 'costs', 'expenses', 'salary' );

$removed = 0;
foreach ( array( 'bhela_cost', 'bhela_expense', 'bhela_salary', 'bhela_booking', 'bhela_review' ) as $type ) {
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
		wp_delete_post( $post->ID, true );
		$removed++;
		printf( "  removed %s #%d %s\n", $type, $post->ID, $post->post_title );
	}
}

printf( "%d fixture(s) swept\n", $removed );
bhela_test_done();
