<?php
/**
 * The approved cost sheet lock.
 *
 * `bhela_bm_cost_save()` has always refused to write to an approved sheet, and that
 * covered the metabox. It covered nothing else. CLAUDE.md §13.9 said as much when the
 * stock lock was built — the cost sheet still left four gaps open: a direct
 * `update_post_meta()`, trash, hard delete, and quick edit.
 *
 * That was a documented shortcoming when an approved sheet only fed a report. It is
 * not one any more. The investor chain reads approved sheets and nothing else:
 * `bhela_bm_dist_preview()` takes its gross from `bhela_bm_statement_data()`, which
 * sums approved sheets, and a committed distribution is immutable by design. So an
 * approved sheet that can be deleted from WP-CLI leaves money declared owed to named
 * people against a trip the books can no longer show — the run says one thing and the
 * sheets underneath it say another, with nothing to reconcile them.
 *
 * Loaded on EVERY request, not behind `is_admin()`, for the reason §13.9 gives:
 * `wp_delete_post()` from WP-CLI or cron never reaches an admin-only guard.
 *
 * **An approved sheet cannot be deleted, by anyone, including an administrator.** That
 * is deliberate and matches the closed stock month and the committed distribution:
 * unlock it first, which the workflow already supports and which leaves a record of
 * who unlocked it and when. "I deleted it" leaves none.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this an approved cost sheet?
 *
 * Deliberately reads the meta directly rather than calling `bhela_bm_cost_status()`:
 * this file loads on every request and `includes/costs.php` is admin-only, so the
 * helper is not there to call on a front-end or WP-CLI request — which is precisely
 * where the gaps this closes were reachable from.
 */
function bhela_bm_cost_locked( $post_id ) {
	if ( 'bhela_cost' !== get_post_type( $post_id ) ) {
		return false;
	}
	return 'approved' === get_post_meta( $post_id, '_bhela_cost_status', true );
}

/** Refuse deletion and trashing of an approved sheet — administrators included. */
function bhela_bm_cost_block_delete( $check, $post, $force = false ) {
	if ( $post && bhela_bm_cost_locked( $post->ID ) ) {
		return false;   // short-circuits wp_delete_post() / wp_trash_post()
	}
	return $check;
}
add_filter( 'pre_delete_post', 'bhela_bm_cost_block_delete', 10, 3 );
add_filter( 'pre_trash_post', 'bhela_bm_cost_block_delete', 10, 2 );

/**
 * Refuse writes to the meta that carries the money.
 *
 * `_bhela_cost_status` is deliberately NOT covered: unlocking is how an approved sheet
 * is legitimately reopened, and a guard that blocked the status change would make the
 * lock permanent rather than reversible. Everything that holds a figure is covered.
 */
function bhela_bm_cost_locked_keys() {
	return array(
		'_bhela_cost_lines',
		'_bhela_cost_total',
		'_bhela_cost_earnings',
		'_bhela_cost_earnings_auto',
		'_bhela_cost_income',
		'_bhela_cost_income_remark',
		'_bhela_cost_header',
		'_bhela_cost_trip_date',
		'_bhela_cost_b2b_auto',
	);
}

function bhela_bm_cost_block_meta( $check, $object_id, $meta_key ) {
	// This filter runs on EVERY meta write in the whole site, so the cheapest
	// possible rejection comes first. Calling bhela_bm_cost_locked_keys() up front
	// allocated a nine-element array on every one of them.
	$key = (string) $meta_key;
	if ( 0 !== strpos( $key, '_bhela_cost_' ) ) {
		return $check;
	}
	if ( in_array( $key, bhela_bm_cost_locked_keys(), true )
		&& bhela_bm_cost_locked( $object_id ) && ! bhela_bm_cost_writing() ) {
		return false;
	}
	return $check;
}
// All THREE hooks, and the third one is the one that matters.
//
// `update_post_meta()` on a key that does not exist yet is caught by the update
// filter — WordPress fires it before it checks existence. `add_post_meta()` is not:
// it fires `add_post_metadata` and nothing else. Shipping only the first two left a
// hole that was narrow but live, because a locked key is only reachable this way
// while it is ABSENT — and `_bhela_cost_income` is absent on every sheet approved
// before income heads existed. That is exactly the meta Trip P&L and Revenue by
// Source read, so an approved sheet's revenue breakdown was forgeable from WP-CLI.
//
// inventory-core.php had all three from the start. This file and
// distribution-core.php were written from a shortened reading of it.
add_filter( 'add_post_metadata', 'bhela_bm_cost_block_meta', 10, 3 );
add_filter( 'update_post_metadata', 'bhela_bm_cost_block_meta', 10, 3 );
// Five arguments, not three: the fifth is $delete_all, and the guard below needs it.
add_filter( 'delete_post_metadata', 'bhela_bm_cost_block_meta', 10, 5 );

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
function bhela_bm_cost_block_delete_all( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
	if ( $delete_all && in_array( (string) $meta_key, bhela_bm_cost_locked_keys(), true ) && ! bhela_bm_cost_writing() ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata', 'bhela_bm_cost_block_delete_all', 9, 5 );

/**
 * And `delete_metadata_by_mid()`, which addresses a meta row by its own id.
 *
 * A different function with a different filter, reachable from the REST API and from
 * `wp_delete_metadata_by_mid()`. It never mentions a post, so nothing above sees it;
 * the row has to be resolved back to its post before the same question can be asked.
 */
function bhela_bm_cost_block_meta_by_mid( $check, $meta_id ) {
	$row = get_metadata_by_mid( 'post', $meta_id );
	if ( $row && bhela_bm_cost_block_meta( null, $row->post_id, $row->meta_key ) === false ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata_by_mid', 'bhela_bm_cost_block_meta_by_mid', 10, 2 );
add_filter( 'update_post_metadata_by_mid', 'bhela_bm_cost_block_meta_by_mid', 10, 2 );

/** Whether a legitimate writer currently holds the pen. */
function bhela_bm_cost_writing( $set = null ) {
	static $open = false;
	if ( null !== $set ) {
		$open = (bool) $set;
	}
	return $open;
}

/**
 * The one legitimate way to write locked meta.
 *
 * Nothing in the plugin needs this today — the save handler already returns early on
 * an approved sheet, so it never reaches the guard. It exists because a migration or
 * a repair script eventually will, and the alternative is somebody removing the
 * filter, which is how a lock stops being one.
 */
function bhela_bm_cost_meta_write( $post_id, $key, $value ) {
	bhela_bm_cost_writing( true );
	$r = update_post_meta( $post_id, $key, $value );
	bhela_bm_cost_writing( false );
	return $r;
}

/**
 * Keep quick edit off an approved sheet.
 *
 * The meta guard above already stops the figures moving, but quick edit offers the
 * row as editable and then silently changes nothing, which reads as a bug. Saying no
 * is clearer than appearing to work.
 */
function bhela_bm_cost_block_quickedit( $actions, $post ) {
	if ( $post && bhela_bm_cost_locked( $post->ID ) ) {
		unset( $actions['inline hide-if-no-js'], $actions['trash'], $actions['delete'] );
	}
	return $actions;
}
add_filter( 'post_row_actions', 'bhela_bm_cost_block_quickedit', 10, 2 );
