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
// This file is allowed to rewrite the stock period index — see
// bhela_test_guard_period_index(), which otherwise restores it on shutdown and
// would quietly undo the repair below.
define( 'BHELA_TEST_INDEX_WRITER', true );
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
	global $wpdb;
	$index = get_option( 'bhela_bm_inv_periods', array() );

	// Pruning stale entries is not enough when the index is GONE but period posts
	// remain — an empty index cannot be pruned back into existence, and every real
	// month then reads as "not opened yet" while a fresh one is mintable on top of it.
	// That is the state a harness interrupted before its restore leaves behind.
	//
	// Rebuilt with $wpdb rather than bhela_bm_inv_period_reindex(), because reindex
	// reads through get_posts() and bhela_test_isolate() has scoped that to
	// `post_title LIKE 'ZZ%'` by the time this file runs — so the plugin's own helper
	// would recover only the fixtures and none of the real months.
	if ( ! $index ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			"SELECT p.ID, m.meta_value AS month
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_bhela_inv_period_month'
			  WHERE p.post_type = 'bhela_inv_period' AND m.meta_value <> ''
			  ORDER BY p.ID ASC",
			ARRAY_A
		);
		$rebuilt = array();
		foreach ( $rows as $row ) {
			// First ID wins, matching the plugin: a later duplicate is not the sheet.
			if ( ! isset( $rebuilt[ $row['month'] ] ) ) {
				$rebuilt[ $row['month'] ] = (int) $row['ID'];
			}
		}
		if ( $rebuilt ) {
			update_option( 'bhela_bm_inv_periods', $rebuilt, false );
			printf( "  rebuilt the stock period index from scratch (%d month(s) recovered: %s)\n",
				count( $rebuilt ),
				implode( ', ', array_keys( $rebuilt ) )
			);
		}
	} else {
		$live = array();
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
}

printf( "%d fixture(s) swept\n", $removed );
bhela_test_done();
