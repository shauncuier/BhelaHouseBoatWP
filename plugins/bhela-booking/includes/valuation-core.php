<?php
/**
 * The lock on an approved valuation, a committed share issue, a capital row and a
 * certificate.
 *
 * All four records decide money that is reported to named people. An approved valuation is
 * what every investor's holding value is computed from; a committed share issue is the
 * arithmetic of a completed transaction that moved everybody's ownership percentage. So
 * both are immutable, and for the same reason a committed distribution is: "I changed
 * it" leaves no record of why the figures moved, and an investor who was shown one
 * number last month is entitled to an explanation rather than a different number.
 *
 * They differ in WHEN they lock, which is why they share a file but not a predicate:
 *
 * - A **valuation** locks on state, like a cost sheet. `draft` is somebody working;
 *   `approved` is signed off. `_bhela_val_status` stays writable so it can be reopened
 *   — a lock that cannot be lifted is a trap (§13.40).
 * - A **share issue**, a **capital row** and a **certificate** lock from birth, like a
 *   distribution run. There is no draft: the screen calculates freely and nothing is
 *   written until Commit, at which point the record is already history — the share
 *   total has moved, or the certificate has been handed over.
 *
 * They share one file rather than growing a lock each because a lock has two lists,
 * hooks and post types, and every gap this plugin has shipped came from writing the
 * second copy from a shortened reading of the first (§13.49, §13.54, §13.55).
 *
 * Loaded on EVERY request, not behind `is_admin()`, for the reason §13.9 gives:
 * `wp_delete_post()` from WP-CLI or cron never reaches an admin-only guard. All the
 * hooks §13.49 and §13.55 catalogue are here — three metadata filters, the priority-9
 * `$delete_all` variant, and both `_by_mid` filters — because a lock registered for
 * three arguments cannot see `$delete_all`, and asserting the hook list proves nothing
 * about the post-type list (§13.54).
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * WHAT IS LOCKED
 * ========================================================= */

/**
 * Is this an approved valuation, or any committed share issue?
 *
 * Reads the status meta directly rather than calling a helper, because this file loads
 * on every request while `includes/valuation.php` is admin-only — and a front-end or
 * WP-CLI request is precisely where the gaps this closes are reachable from.
 */
function bhela_bm_val_locked( $post_id ) {
	$type = get_post_type( $post_id );
	// Locked from birth, all three of them, for one reason: each is the record behind
	// a figure somebody has already been shown. A share issue moved everybody's
	// percentage; a capital row and a certificate are the evidence under a document an
	// investor is holding. None has a draft state — nothing is written until Commit,
	// and after Commit it is history.
	if ( in_array( $type, array( 'bhela_share_issue', 'bhela_capital', 'bhela_cert', 'bhela_agreement' ), true ) ) {
		return true;
	}
	// The two that lock on STATE rather than from birth, for the same reason a cost
	// sheet does: a draft is somebody working, and everything after it is a figure
	// somebody has been shown. Both keep their status key writable below, so reopening
	// stays possible and stays audited.
	if ( 'bhela_investment' === $type ) {
		return 'draft' !== (string) get_post_meta( $post_id, '_bhela_ivm_status', true );
	}
	if ( 'bhela_valuation' !== $type ) {
		return false;
	}
	return 'approved' === get_post_meta( $post_id, '_bhela_val_status', true );
}

/**
 * The meta a locked record refuses.
 *
 * `_bhela_val_status` is deliberately absent: reopening an approved valuation is how it
 * is legitimately corrected, and the reopen is audited. Everything that carries a
 * figure is covered.
 */
function bhela_bm_val_locked_prefixes() {
	return array( '_bhela_val_', '_bhela_iss_', '_bhela_cap_', '_bhela_crt_', '_bhela_ivm_', '_bhela_agr_' );
}

/**
 * Keys a locked record must still be able to write, and why each one.
 *
 * Every entry here is a lock that would otherwise be a trap (§13.40):
 *
 * - `_bhela_val_status`  — reopening an approved valuation is how it is corrected.
 * - `_bhela_cap_void*`   — a mistyped capital row is VOIDED, never deleted, so the
 *                          void flag and its reason have to be writable after the fact.
 * - `_bhela_crt_super*`  — a superseded certificate has to be able to say which
 *                          certificate replaced it.
 *
 * Every one of them is audited by its own writer. Nothing that carries a figure is on
 * this list.
 */
function bhela_bm_val_open_keys() {
	return array(
		'_bhela_val_status',
		'_bhela_cap_void',
		'_bhela_cap_void_reason',
		'_bhela_cap_void_by',
		'_bhela_crt_super',
		'_bhela_crt_super_reason',
		// An investment's own state, so it can be activated and legitimately reopened;
		// and an agreement's, so a later one can supersede it. Both are the writable
		// hinge that stops the lock being a trap, and both are audited by their writer.
		'_bhela_ivm_status',
		'_bhela_agr_status',
	);
}

/** Whether a key is one this lock owns, minus the few bhela_bm_val_open_keys() names. */
function bhela_bm_val_owns_key( $meta_key ) {
	$key = (string) $meta_key;
	if ( in_array( $key, bhela_bm_val_open_keys(), true ) ) {
		return false;
	}
	foreach ( bhela_bm_val_locked_prefixes() as $prefix ) {
		if ( 0 === strpos( $key, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================
 * THE GUARDS
 * ========================================================= */

/** Refuse deletion and trashing — administrators included. */
function bhela_bm_val_block_delete( $check, $post, $force = false ) {
	if ( $post && bhela_bm_val_locked( $post->ID ) ) {
		return false;
	}
	return $check;
}
add_filter( 'pre_delete_post', 'bhela_bm_val_block_delete', 10, 3 );
add_filter( 'pre_trash_post', 'bhela_bm_val_block_delete', 10, 2 );

/** Refuse writes to the meta that carries the figures. */
function bhela_bm_val_block_meta( $check, $object_id, $meta_key ) {
	// Cheapest possible rejection first: this filter fires on every meta write in the
	// whole site, so a foreign key must cost one strpos and nothing else.
	if ( ! bhela_bm_val_owns_key( $meta_key ) ) {
		return $check;
	}
	if ( bhela_bm_val_locked( $object_id ) && ! bhela_bm_val_writing() ) {
		return false;
	}
	return $check;
}
add_filter( 'add_post_metadata', 'bhela_bm_val_block_meta', 10, 3 );
add_filter( 'update_post_metadata', 'bhela_bm_val_block_meta', 10, 3 );
// Five arguments, not three: the fifth is $delete_all, which the guard below needs.
add_filter( 'delete_post_metadata', 'bhela_bm_val_block_meta', 10, 5 );

/**
 * `delete_post_meta_by_key()` never names a post.
 *
 * It fires the same filter with `$object_id` of 0 and `$delete_all` true — "remove this
 * key from EVERY post" — so a guard that resolves a record from the id finds nothing
 * and allows it. There is no "which record" to ask about, so the answer is the safe
 * one: a blanket delete of a key this module owns is refused outright.
 */
function bhela_bm_val_block_delete_all( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
	if ( $delete_all && bhela_bm_val_owns_key( $meta_key ) && ! bhela_bm_val_writing() ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata', 'bhela_bm_val_block_delete_all', 9, 5 );

/**
 * And `delete_metadata_by_mid()`, which addresses a meta row by its own id.
 *
 * A different function with a different filter, reachable from the REST API. It never
 * mentions a post, so the row has to be resolved back to one before the same question
 * can be asked.
 */
function bhela_bm_val_block_meta_by_mid( $check, $meta_id ) {
	$row = get_metadata_by_mid( 'post', $meta_id );
	if ( $row && false === bhela_bm_val_block_meta( null, $row->post_id, $row->meta_key ) ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata_by_mid', 'bhela_bm_val_block_meta_by_mid', 10, 2 );
add_filter( 'update_post_metadata_by_mid', 'bhela_bm_val_block_meta_by_mid', 10, 2 );

/* =========================================================
 * THE WRITE WINDOW
 * ========================================================= */

/** Whether a legitimate writer currently holds the pen. */
function bhela_bm_val_writing( $set = null ) {
	static $open = false;
	if ( null !== $set ) {
		$open = (bool) $set;
	}
	return $open;
}

/**
 * The one legitimate way to write locked meta.
 *
 * Used by `bhela_bm_share_issue_commit()`, which writes a record that is locked from
 * the moment it exists — so unlike the cost sheet's equivalent this one has a caller
 * from day one. Function-local static, so the window is open for exactly one
 * `update_post_meta()` call and cannot leak past it.
 */
function bhela_bm_val_meta_write( $post_id, $key, $value ) {
	bhela_bm_val_writing( true );
	$r = update_post_meta( $post_id, $key, $value );
	bhela_bm_val_writing( false );
	return $r;
}

/**
 * The one legitimate way to delete a locked record.
 *
 * A share issue is locked from birth, which means `bhela_bm_share_issue_commit()`
 * cannot clean up after itself when it aborts half way — the record it inserted a
 * moment ago is already immutable, and the abort left an orphan that the drift check
 * then counted. The delete filters do not consult the write window (a lock that a flag
 * could lift is not much of a lock), so this removes them for exactly one call and
 * puts them straight back.
 *
 * Deliberately NOT a general-purpose escape hatch: the only caller is the abort path,
 * which is deleting a record nothing else has seen.
 */
function bhela_bm_val_delete( $post_id ) {
	bhela_bm_val_writing( true );
	remove_filter( 'pre_delete_post', 'bhela_bm_val_block_delete', 10 );
	remove_filter( 'pre_trash_post', 'bhela_bm_val_block_delete', 10 );
	$r = wp_delete_post( $post_id, true );
	add_filter( 'pre_delete_post', 'bhela_bm_val_block_delete', 10, 3 );
	add_filter( 'pre_trash_post', 'bhela_bm_val_block_delete', 10, 2 );
	bhela_bm_val_writing( false );
	return $r;
}
