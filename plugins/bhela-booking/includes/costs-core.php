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
	if ( in_array( (string) $meta_key, bhela_bm_cost_locked_keys(), true )
		&& bhela_bm_cost_locked( $object_id ) && ! bhela_bm_cost_writing() ) {
		return false;
	}
	return $check;
}
add_filter( 'update_post_metadata', 'bhela_bm_cost_block_meta', 10, 3 );
add_filter( 'delete_post_metadata', 'bhela_bm_cost_block_meta', 10, 3 );

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
