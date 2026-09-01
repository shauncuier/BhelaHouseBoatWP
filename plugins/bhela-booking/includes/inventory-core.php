<?php
/**
 * Inventory & Asset Register — post types and the lock.
 *
 * Split from inventory.php and loaded on EVERY request, not just wp-admin,
 * because a lock that only exists in wp-admin is not a lock. wp_delete_post()
 * called from WP-CLI, a cron job or another plugin never reaches an is_admin()
 * block; if the guards lived with the screens, a closed month would be deletable
 * from the command line.
 *
 * This file therefore depends on NOTHING. bhela_bm_inv_is_locked() reads raw meta
 * rather than calling bhela_bm_inv_status() in inventory.php, and every richer
 * reading delegates to core rather than the reverse. Please keep it that way — a
 * tidy-up that "moves the helpers together" reintroduces the hole.
 *
 * The four ways a locked record could otherwise still be written are all closed
 * here. The cost sheet, which this is modelled on, only closes the first:
 *
 *   1. its own save handler        (costs.php:1182 does this)
 *   2. direct update/add/delete_post_meta   (costs.php does NOT)
 *   3. deletion and trash                   (costs.php does NOT)
 *   4. quick-edit / wp_update_post          (costs.php does NOT)
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * POST TYPES
 * ========================================================= */

/**
 * The master register: one record per physical thing, kept for its whole life.
 *
 * One post type for both inventory and assets, separated by a `_bhela_inv_kind`
 * meta. Everything except the two monthly reports is identical for the two —
 * the category/location lists, ID minting, the importer, attachments, the audit
 * rows — so a second post type would double all of it, and double the thirteen
 * capability primitives in both bhela_bm_admin_caps() and bhela_bm_owned_caps(),
 * to buy a difference in two report templates.
 */
function bhela_bm_register_inv_item_cpt() {
	register_post_type( 'bhela_inv_item', array(
		'labels'              => array(
			'name'               => __( '📦 Item Register', 'bhela-booking' ),
			'singular_name'      => __( 'Item', 'bhela-booking' ),
			'menu_name'          => __( '📦 Item Register', 'bhela-booking' ),
			'add_new_item'       => __( 'Add New Item', 'bhela-booking' ),
			'edit_item'          => __( 'Edit Item', 'bhela-booking' ),
			'search_items'       => __( 'Search items', 'bhela-booking' ),
			'not_found'          => __( 'No items yet. Import the register, or add one by hand.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		// Unconditionally nested, like the cost sheet: init runs before the
		// current user resolves, so the menu cannot be decided per role here.
		// A storekeeper gets a standalone top-level entry from inventory.php.
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'rewrite'             => false,
		'capability_type'     => array( 'bhela_inv_item', 'bhela_inv_items' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'menu_icon'           => 'dashicons-archive',
		'supports'            => array( 'title', 'thumbnail' ),
	) );
}
add_action( 'init', 'bhela_bm_register_inv_item_cpt' );

/**
 * One post per month, holding that month's movement for every item.
 *
 * Deliberately hostile to the two things that would break the register's
 * invariants:
 *
 *   - `create_posts => do_not_allow` — a period may only be created by the
 *     "Open month" button, which goes through bhela_bm_inv_period_id() and its
 *     mutex. Nothing else can mint one, so there can never be two records for
 *     one month. (The cost sheet has no such guard, and nothing stops two sheets
 *     on the same date today.)
 *   - `show_in_menu => false` — a list screen would offer Add New and Trash,
 *     which are exactly the two operations the invariants forbid.
 */
function bhela_bm_register_inv_period_cpt() {
	register_post_type( 'bhela_inv_period', array(
		'labels'              => array(
			'name'          => __( 'Monthly Sheets', 'bhela-booking' ),
			'singular_name' => __( 'Monthly Sheet', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_rest'        => false,
		'rewrite'             => false,
		'capability_type'     => array( 'bhela_inv_period', 'bhela_inv_periods' ),
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'supports'            => array( 'title' ),
	) );
}
add_action( 'init', 'bhela_bm_register_inv_period_cpt' );

/* =========================================================
 * THE LOCK
 * ========================================================= */

/**
 * Is this monthly sheet closed?
 *
 * Reads the raw meta on purpose — see the file header. `closed` is the only
 * locked state; `reopened` is deliberately NOT locked, because reopening exists
 * precisely to allow a correction.
 *
 * @param int $post_id Period post ID.
 * @return bool
 */
function bhela_bm_inv_is_locked( $post_id ) {
	if ( ! $post_id || 'bhela_inv_period' !== get_post_type( $post_id ) ) {
		return false;
	}
	return 'closed' === get_post_meta( $post_id, '_bhela_inv_status', true );
}

/**
 * The one deliberate hole in the meta lock, opened and shut around the plugin's
 * own writes.
 *
 * Closing a month has to write `_bhela_inv_status = closed` — and the moment it
 * does, the record is locked, so the transition handler's own remaining writes
 * (the who/when stamps) would be blocked by the guard below. This request-scoped
 * flag is how those writes get through.
 *
 * It is a real hole, so it is narrow and loud: set immediately before a
 * transition's writes and cleared in a `finally`, never set by anything else, and
 * tests/inventory-test.php asserts it reads false both before and after every
 * transition.
 *
 * @param bool|null $set Pass true/false to set, null to read.
 * @return bool
 */
function bhela_bm_inv_unlocking( $set = null ) {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/**
 * Gap 2 — refuse a direct meta write on a closed sheet.
 *
 * Returning a non-null value from update_post_metadata short-circuits
 * update_metadata() and is returned to the caller, so nothing is written. This is
 * not advisory: it is the difference between a lock and a disabled input. The cost
 * sheet has had the same treatment since v2.37.0 — see includes/costs-core.php,
 * which was written from this file.
 *
 * Scoped to `_bhela_inv_*` so core's own writes (`_edit_lock`, `_thumbnail_id`)
 * are untouched — a locked sheet still has to be openable in the editor.
 *
 * @param mixed  $check     Short-circuit value.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return mixed false to block, $check to allow.
 */
function bhela_bm_inv_block_meta( $check, $object_id, $meta_key ) {
	if ( 0 !== strpos( (string) $meta_key, '_bhela_inv_' ) ) {
		return $check;
	}
	if ( bhela_bm_inv_unlocking() ) {
		return $check;
	}
	if ( ! bhela_bm_inv_is_locked( $object_id ) ) {
		return $check;
	}
	return false;
}
add_filter( 'update_post_metadata', 'bhela_bm_inv_block_meta', 10, 3 );
add_filter( 'add_post_metadata', 'bhela_bm_inv_block_meta', 10, 3 );
// Five arguments, not three: the fifth is $delete_all, which the guard below needs.
add_filter( 'delete_post_metadata', 'bhela_bm_inv_block_meta', 10, 5 );

/**
 * Gap 2b — `delete_post_meta_by_key()` never names a post.
 *
 * `delete_metadata()` in delete-all mode fires the same filter with `$object_id` of
 * 0 and `$delete_all` true, meaning "remove this key from EVERY post". The guard
 * above resolves a lock from the id, sees 0, finds no sheet and allows it — so one
 * call could strip `_bhela_inv_close` from every closed month at once. It was also
 * non-deterministic: on an admin screen where the global $post happened to be a
 * closed sheet, `get_post_type( 0 )` fell back to it and the call was refused.
 *
 * There is no "which sheet" to ask about here, so the answer is the safe one: a
 * blanket delete of a key this module owns is refused outright.
 */
function bhela_bm_inv_block_delete_all( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
	if ( $delete_all && 0 === strpos( (string) $meta_key, '_bhela_inv_' ) && ! bhela_bm_inv_unlocking() ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata', 'bhela_bm_inv_block_delete_all', 9, 5 );

/**
 * Gap 2c — and `delete_metadata_by_mid()`, which addresses a meta row by its own id.
 *
 * A different function with a different filter, reachable from the REST API and from
 * `wp_delete_metadata_by_mid()`. It never mentions a post, so the row has to be
 * resolved back to one before the same question can be asked.
 */
function bhela_bm_inv_block_meta_by_mid( $check, $meta_id ) {
	$row = get_metadata_by_mid( 'post', $meta_id );
	if ( $row && false === bhela_bm_inv_block_meta( null, $row->post_id, $row->meta_key ) ) {
		return false;
	}
	return $check;
}
add_filter( 'delete_post_metadata_by_mid', 'bhela_bm_inv_block_meta_by_mid', 10, 2 );
add_filter( 'update_post_metadata_by_mid', 'bhela_bm_inv_block_meta_by_mid', 10, 2 );

/**
 * Gap 3a — deny the delete capability on a closed sheet, and on an item that
 * appears in one.
 *
 * This is the first place in the plugin where an ADMINISTRATOR is denied
 * something, and it is intentional: a register whose administrator can delete a
 * closed month is not a register. The documented route is to reopen the month
 * first, which is itself an audited act.
 *
 * @param string[] $caps    Required capabilities.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    $args[0] is the object ID.
 * @return string[]
 */
function bhela_bm_inv_deny_delete( $caps, $cap, $user_id, $args ) {
	$watched = array( 'delete_post', 'delete_bhela_inv_period', 'delete_bhela_inv_item' );
	if ( ! in_array( $cap, $watched, true ) || empty( $args[0] ) ) {
		return $caps;
	}
	$id   = (int) $args[0];
	$type = get_post_type( $id );

	if ( 'bhela_inv_period' === $type && bhela_bm_inv_is_locked( $id ) ) {
		return array( 'do_not_allow' );
	}
	// An item referenced by a closed month carries history that a report still
	// renders, so it stops being deletable. Stamped at close time rather than
	// derived, so this stays one meta read instead of a scan of every period.
	if ( 'bhela_inv_item' === $type && get_post_meta( $id, '_bhela_inv_locked', true ) ) {
		return array( 'do_not_allow' );
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'bhela_bm_inv_deny_delete', 10, 4 );

/**
 * Gap 3b — the same refusal for callers that check no capability at all.
 *
 * wp_delete_post() and wp_trash_post() do not consult map_meta_cap, so the filter
 * above never sees a WP-CLI deletion, a cron job, or another plugin's cleanup.
 * These two hooks are the only thing standing in the way, which is why both are
 * needed rather than either.
 *
 * @param int $post_id Post about to be removed.
 */
function bhela_bm_inv_guard_delete( $post_id ) {
	$type = get_post_type( $post_id );

	$locked = ( 'bhela_inv_period' === $type && bhela_bm_inv_is_locked( $post_id ) )
		|| ( 'bhela_inv_item' === $type && get_post_meta( $post_id, '_bhela_inv_locked', true ) );

	if ( ! $locked ) {
		return;
	}
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'error', sprintf( 'Refused to delete %s #%d — it belongs to a closed month.', $type, (int) $post_id ), false );
	}
	wp_die(
		esc_html__( 'This record belongs to a closed month and cannot be deleted. Reopen the month first — that is recorded in the audit trail.', 'bhela-booking' ),
		esc_html__( 'Closed month', 'bhela-booking' ),
		array( 'response' => 403 )
	);
}
add_action( 'before_delete_post', 'bhela_bm_inv_guard_delete' );
add_action( 'wp_trash_post', 'bhela_bm_inv_guard_delete' );

/**
 * The same two guards for approved cost sheets.
 *
 * Not part of the inventory register, but the identical hole: costs.php enforces
 * its lock only inside its own save handler, so an approved sheet — the input to
 * a signed monthly statement — can still be trashed today by anyone holding
 * delete_bhela_costs. Four lines here close it.
 */
function bhela_bm_cost_deny_delete( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'delete_post', 'delete_bhela_cost' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}
	if ( 'bhela_cost' !== get_post_type( $args[0] ) ) {
		return $caps;
	}
	return 'approved' === get_post_meta( (int) $args[0], '_bhela_cost_status', true )
		? array( 'do_not_allow' )
		: $caps;
}
add_filter( 'map_meta_cap', 'bhela_bm_cost_deny_delete', 10, 4 );

/** @see bhela_bm_cost_deny_delete() */
function bhela_bm_cost_guard_delete( $post_id ) {
	if ( 'bhela_cost' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( 'approved' !== get_post_meta( $post_id, '_bhela_cost_status', true ) ) {
		return;
	}
	wp_die(
		esc_html__( 'This cost sheet is approved and cannot be deleted. Unlock it first.', 'bhela-booking' ),
		esc_html__( 'Approved sheet', 'bhela-booking' ),
		array( 'response' => 403 )
	);
}
add_action( 'before_delete_post', 'bhela_bm_cost_guard_delete' );
add_action( 'wp_trash_post', 'bhela_bm_cost_guard_delete' );

/**
 * Gap 4 — keep the post row itself intact on a closed sheet.
 *
 * The meta filter above covers the figures, but the title, status and date are
 * post columns, reachable through quick-edit or a bare wp_update_post(). A
 * period's title IS its month, so letting it change would detach the record from
 * the index that guarantees uniqueness.
 *
 * @param array $data    Sanitised post data about to be written.
 * @param array $postarr Raw post array, including ID.
 * @return array
 */
function bhela_bm_inv_lock_post_row( $data, $postarr ) {
	$id = (int) ( $postarr['ID'] ?? 0 );
	if ( ! $id || 'bhela_inv_period' !== ( $data['post_type'] ?? '' ) ) {
		return $data;
	}
	if ( bhela_bm_inv_unlocking() || ! bhela_bm_inv_is_locked( $id ) ) {
		return $data;
	}
	$stored = get_post( $id );
	if ( ! $stored ) {
		return $data;
	}
	$data['post_title']  = $stored->post_title;
	$data['post_status'] = $stored->post_status;
	$data['post_date']   = $stored->post_date;
	$data['post_name']   = $stored->post_name;
	return $data;
}
add_filter( 'wp_insert_post_data', 'bhela_bm_inv_lock_post_row', 10, 2 );

/**
 * No inline edit on a closed sheet or a locked item — the row action would offer
 * fields the guards above then silently refuse.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    The row's post.
 * @return array
 */
function bhela_bm_inv_row_actions( $actions, $post ) {
	if ( 'bhela_inv_period' === $post->post_type && bhela_bm_inv_is_locked( $post->ID ) ) {
		unset( $actions['inline hide-if-no-js'], $actions['trash'] );
	}
	if ( 'bhela_inv_item' === $post->post_type && get_post_meta( $post->ID, '_bhela_inv_locked', true ) ) {
		unset( $actions['trash'] );
	}
	return $actions;
}
add_filter( 'post_row_actions', 'bhela_bm_inv_row_actions', 10, 2 );

/* =========================================================
 * ATTACHMENT UPLOADS
 * ========================================================= */

/**
 * Is the plugin's own upload handler running right now?
 *
 * @param bool|null $set Pass true/false to set, null to read.
 * @return bool
 */
function bhela_bm_inv_uploading( $set = null ) {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/**
 * Grant `upload_files` for the duration of one upload, and no longer.
 *
 * upload_files was deliberately revoked from every plugin role (see
 * bhela_bm_base_caps()) because holding it hands the user the entire Media
 * Library — including the invoice QR images. Attaching a purchase bill to an item
 * must not undo that.
 *
 * So the capability is never stored on any role. It is granted here, only while
 * the plugin's own handler is between its validation gates and
 * media_handle_upload(), and only to someone who already holds bhela_inv_attach.
 * The handler never opens a media frame, so the user can add a file and still
 * cannot browse anyone else's.
 *
 * security-test.php inspects stored role capabilities, so it still passes — and
 * should: the concern it encodes is "the whole Media Library", which this does
 * not open.
 *
 * @param array $allcaps The user's capabilities.
 * @return array
 */
function bhela_bm_inv_grant_upload( $allcaps ) {
	if ( ! bhela_bm_inv_uploading() ) {
		return $allcaps;
	}
	if ( empty( $allcaps['bhela_inv_attach'] ) ) {
		return $allcaps;
	}
	$allcaps['upload_files'] = true;
	return $allcaps;
}
add_filter( 'user_has_cap', 'bhela_bm_inv_grant_upload' );

/**
 * An attachment parented to an item follows that item's permissions.
 *
 * Without this, the runtime grant above would also let an attach holder edit or
 * delete attachments belonging to items they cannot touch — the grant is on
 * upload_files, but WordPress derives attachment editing from the post caps.
 *
 * @param string[] $caps    Required capabilities.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    $args[0] is the attachment ID.
 * @return string[]
 */
function bhela_bm_inv_attachment_caps( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}
	$att = get_post( (int) $args[0] );
	if ( ! $att || 'attachment' !== $att->post_type || ! $att->post_parent ) {
		return $caps;
	}
	if ( 'bhela_inv_item' !== get_post_type( $att->post_parent ) ) {
		return $caps;
	}
	// Defer entirely to the parent item: whoever may edit the item may manage its
	// bills and photos, and nobody else may.
	return map_meta_cap( $cap, $user_id, $att->post_parent );
}
add_filter( 'map_meta_cap', 'bhela_bm_inv_attachment_caps', 10, 4 );
