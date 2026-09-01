<?php
/**
 * The distribution lock, and the ledger post type.
 *
 * Loaded on EVERY request, not just in wp-admin, for the same reason
 * inventory-core.php is (CLAUDE.md §13.9): `wp_delete_post()` from WP-CLI or cron
 * never passes through an `is_admin()` block, and a committed distribution that can
 * be deleted from the command line is not committed.
 *
 * A committed run and its ledger rows are money that has been declared owed to named
 * people. Deleting either would silently un-owe it, leaving payments in the system
 * against profit that no longer exists. The correction for a wrong run is a REVERSAL
 * — contra rows that show both the error and the fix — not an erasure.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The run and ledger post types. Registered here so the guards below always apply. */
function bhela_bm_register_dist_cpts() {
	$common = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
	);

	register_post_type( 'bhela_dist', array_merge( $common, array(
		'labels'       => array( 'name' => __( 'Distributions', 'bhela-booking' ) ),
		'show_ui'      => false,   // driven entirely by the Distribution screen
	) ) );

	// One row per money event. Never edited, never deleted — only added to.
	register_post_type( 'bhela_inv_ledger', array_merge( $common, array(
		'labels'       => array( 'name' => __( 'Investor Ledger', 'bhela-booking' ) ),
		'show_ui'      => false,
	) ) );
}
add_action( 'init', 'bhela_bm_register_dist_cpts' );

/** Is this post a committed distribution run or a ledger row? */
function bhela_bm_dist_locked( $post_id ) {
	$type = get_post_type( $post_id );
	return in_array( $type, array( 'bhela_dist', 'bhela_inv_ledger' ), true );
}

/**
 * Refuse deletion outright — administrators included.
 *
 * That is deliberate and matches the closed stock month. An administrator who really
 * must remove a run has to reverse it first, which leaves a record of why. "I deleted
 * it" leaves none, and this is the one dataset where the question a year later is
 * always "who decided that, and when".
 */
function bhela_bm_dist_block_delete( $check, $post, $force = false ) {
	if ( $post && bhela_bm_dist_locked( $post->ID ) ) {
		return false;   // short-circuits wp_delete_post() / wp_trash_post()
	}
	return $check;
}
add_filter( 'pre_delete_post', 'bhela_bm_dist_block_delete', 10, 3 );
add_filter( 'pre_trash_post', 'bhela_bm_dist_block_delete', 10, 2 );

/**
 * Refuse edits to the meta that carries the money.
 *
 * bhela_bm_ledger_add() and bhela_bm_dist_commit() write through
 * bhela_bm_dist_meta_write(), which lifts this guard for exactly one call. Anything
 * else — a stray update_post_meta(), quick edit, an import — is dropped.
 */
function bhela_bm_dist_block_meta( $check, $object_id, $meta_key ) {
	if ( 0 === strpos( (string) $meta_key, '_bhela_dist_' ) || 0 === strpos( (string) $meta_key, '_bhela_led_' )
		|| 0 === strpos( (string) $meta_key, '_bhela_fnd_' ) ) {
		if ( bhela_bm_dist_locked( $object_id ) && ! bhela_bm_dist_writing() ) {
			return false;
		}
	}
	return $check;
}
// `add_post_metadata` was missing here too, and this is the ledger — an absent key
// on a row could be created from outside the one writer. `update_post_meta()` is
// caught either way because WordPress fires the update filter before checking
// whether the key exists; `add_post_meta()` fires only this one.
add_filter( 'add_post_metadata', 'bhela_bm_dist_block_meta', 10, 3 );
add_filter( 'update_post_metadata', 'bhela_bm_dist_block_meta', 10, 3 );
add_filter( 'delete_post_metadata', 'bhela_bm_dist_block_meta', 10, 3 );

/** Whether a legitimate writer currently holds the pen. */
function bhela_bm_dist_writing( $set = null ) {
	static $open = false;
	if ( null !== $set ) {
		$open = (bool) $set;
	}
	return $open;
}

/** The one legitimate way to write locked meta. */
function bhela_bm_dist_meta_write( $post_id, $key, $value ) {
	bhela_bm_dist_writing( true );
	$r = update_post_meta( $post_id, $key, $value );
	bhela_bm_dist_writing( false );
	return $r;
}
