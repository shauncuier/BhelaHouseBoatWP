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

/**
 * Is this post a committed distribution run, a ledger row, or a fund movement?
 *
 * `bhela_fund` was missing from this list from the day the funds shipped, which made
 * the `_bhela_fnd_` branch of bhela_bm_dist_block_meta() below dead code and left
 * `bhela_bm_dist_block_delete()` — which shares this predicate — not covering fund
 * rows either. So a reserve allocation was freely rewritable and **hard-deletable**
 * from WP-CLI or cron: a wider hole than the add_post_meta() one, because there was
 * no lock at all rather than a lock with a gap.
 *
 * The intent was never in doubt. `bhela_bm_fund_add()` writes every one of its meta
 * keys through `bhela_bm_dist_meta_write()`, which exists only to lift this guard —
 * it was holding a pen for a lock that did not fire.
 *
 * It matters because the reserve balance is replayed from these rows, and it is what
 * the investor portal shows and what cash flow reads. Deleting an allocation leaves
 * the committed run saying one thing and the fund another, with nothing to reconcile
 * them — and §13.35 is explicit that an allocation is the arithmetic of a committed
 * month and must not be cancellable at all.
 */
function bhela_bm_dist_locked( $post_id ) {
	$type = get_post_type( $post_id );
	return in_array( $type, array( 'bhela_dist', 'bhela_inv_ledger', 'bhela_fund' ), true );
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
add_filter( 'delete_post_metadata', 'bhela_bm_dist_block_meta', 10, 5 );

/** The locked key prefixes, so the two guards below ask the same question. */
function bhela_bm_dist_locked_prefixes() {
	return array( '_bhela_dist_', '_bhela_led_', '_bhela_fnd_' );
}

/**
 * `delete_post_meta_by_key()` walks past a lock that only checks one post.
 *
 * `delete_metadata()` in delete-all mode fires this same filter with `$object_id`
 * of 0 and `$delete_all` true, meaning "remove this key from EVERY post". A guard
 * that resolves a post type from the id then sees 0, finds no post, and allows it —
 * so one call could strip a locked key from every locked record at once. Worse, it
 * was non-deterministic: on an admin screen where the global $post happened to be a
 * locked record, `get_post_type( 0 )` fell back to it and the call was refused.
 *
 * The filter has taken five arguments since WP 3.1; all three locks were registered
 * with three, so `$delete_all` was never even visible to them.
 */
function bhela_bm_dist_block_delete_all( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
	if ( ! $delete_all || bhela_bm_dist_writing() ) {
		return $check;
	}
	foreach ( bhela_bm_dist_locked_prefixes() as $prefix ) {
		if ( 0 === strpos( (string) $meta_key, $prefix ) ) {
			return false;
		}
	}
	return $check;
}
add_filter( 'delete_post_metadata', 'bhela_bm_dist_block_delete_all', 9, 5 );

/**
 * And `delete_metadata_by_mid()`, which addresses a meta row by its own id.
 *
 * A different function with a different filter, reachable from the REST API and from
 * `wp_delete_metadata_by_mid()`. It never mentions a post, so nothing above sees it;
 * the row has to be resolved back to its post before the same question can be asked.
 */
function bhela_bm_dist_block_meta_by_mid( $check, $meta_id ) {
	$row = get_metadata_by_mid( 'post', $meta_id );
	if ( $row && bhela_bm_dist_block_meta( null, $row->post_id, $row->meta_key ) === false ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata_by_mid', 'bhela_bm_dist_block_meta_by_mid', 10, 2 );
add_filter( 'update_post_metadata_by_mid', 'bhela_bm_dist_block_meta_by_mid', 10, 2 );

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
