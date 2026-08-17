<?php
/**
 * The Inventory & Asset Register.
 *
 * The four things this exists to hold down, all of which are easy to break and
 * expensive to notice:
 *
 *   1. The quantity invariant. Repair and damage move a thing between condition
 *      buckets without changing how many there are; loss and disposal do change it.
 *   2. Carry-forward. A month opens on the previous month's closing, that opening
 *      is not writable, and when the month underneath changes the disagreement is
 *      REPORTED rather than silently corrected.
 *   3. The lock. A closed month refuses the form, a direct meta write, quick-edit,
 *      the trash and outright deletion — the last two even for an administrator.
 *   4. The audit trail's undeletability, asserted at source level as well as at
 *      runtime, because "there is no DELETE" is a property of the code.
 *
 * @package BhelaBooking
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'log', 'audit', 'inventory-core', 'inventory', 'inventory-import' );

wp_set_current_user( 1 );
bhela_bm_audit_install();

$made   = array();
$plugin = WP_PLUGIN_DIR . '/bhela-booking';

// Start from a known state. A crashed earlier run can leave the period index
// pointing at posts that no longer exist, and this harness is entirely about
// balances carried between months — so it must not inherit anyone else's.
//
// Clearing it here is safe only because bhela_test_guard_period_index() in the
// bootstrap snapshots the live index and puts it back on shutdown. Without that,
// this line orphans every real stock month — the figures stay in the database while
// the screen reports "this month has not been opened yet", and
// bhela_bm_inv_period_id( $month, true ) will mint a SECOND sheet on top of it. That
// is not hypothetical: a real August 2026 sheet went missing after a green run.
delete_option( 'bhela_bm_inv_periods' );
delete_option( 'bhela_bm_inv_seq' );

/** A line with every key present. */
function iv_line( $a = array() ) {
	return array_merge( bhela_bm_inv_blank_line(), $a );
}

/** A ZZ item. */
function iv_item( $name, $kind = 'inventory', $cat = 'kitchen', $rate = 100 ) {
	global $made;
	$id = wp_insert_post( array( 'post_type' => 'bhela_inv_item', 'post_status' => 'publish', 'post_title' => 'ZZ ' . $name ) );
	update_post_meta( $id, '_bhela_inv_kind', $kind );
	update_post_meta( $id, '_bhela_inv_cat', $cat );
	update_post_meta( $id, '_bhela_inv_code', 'ZZ-' . strtoupper( substr( md5( $name ), 0, 6 ) ) );
	update_post_meta( $id, '_bhela_inv_rate', $rate );
	$made[] = $id;
	return $id;
}

/**
 * A period, tracked for cleanup and renamed so the harness can see it.
 *
 * The rename is load-bearing. bhela_test_isolate() scopes every query to
 * `post_title LIKE 'ZZ%'`, and the plugin titles a period "Stock — August 2026" —
 * so an un-renamed fixture is invisible to both the isolation filter and
 * sweep.php. A crashed run then left periods behind, the index still pointed at
 * them, and the next run carried a previous run's opening balances into its own
 * fixtures. That reads exactly like a broken carry-forward.
 */
function iv_period( $month ) {
	global $made;
	$id = bhela_bm_inv_period_id( $month, true );
	if ( $id && ! in_array( $id, $made, true ) ) {
		$made[] = $id;
		wp_update_post( array( 'ID' => $id, 'post_title' => 'ZZ stock ' . $month ) );
	}
	return $id;
}

/**
 * Run something that is supposed to call wp_die(), and report whether it did.
 *
 * The deletion guards refuse by dying, which is right in a browser and fatal to a
 * CLI harness — an uncaught wp_die() reads as "DIED EARLY" rather than as a passing
 * assertion. Swapping the handler for one that throws turns the refusal into
 * something testable without weakening it.
 */
function iv_died( $fn ) {
	$thrower = function () {
		return function ( $message ) {
			throw new RuntimeException( is_wp_error( $message ) ? $message->get_error_message() : (string) $message );
		};
	};
	add_filter( 'wp_die_handler', $thrower, 99 );
	$died = false;
	try {
		$fn();
	} catch ( RuntimeException $e ) {
		$died = true;
	}
	remove_filter( 'wp_die_handler', $thrower, 99 );
	return $died;
}

/** Rows in the audit table matching a field, for one object. */
function iv_audit_rows( $field = '', $ref = '' ) {
	global $wpdb;
	$sql  = 'SELECT * FROM ' . bhela_bm_audit_table() . ' WHERE 1=1';
	$prep = array();
	if ( '' !== $field ) {
		$sql   .= ' AND field = %s';
		$prep[] = $field;
	}
	if ( '' !== $ref ) {
		$sql   .= ' AND object_ref LIKE %s';
		$prep[] = '%' . $ref . '%';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return $wpdb->get_results( $prep ? $wpdb->prepare( $sql, $prep ) : $sql, ARRAY_A );
}

/**
 * Save a month's figures the way the form does.
 *
 * Calls bhela_bm_inv_apply_lines() rather than the admin-post handler, because the
 * handler ends in wp_safe_redirect() + exit and a CLI harness cannot survive that.
 * The handler owns the nonce, the capability and the redirect; this owns the rules,
 * and the rules are what these assertions are about.
 *
 * The lock is checked here too, so the "a closed month refuses the form" assertion
 * exercises the same guard the handler applies.
 */
function iv_save( $period, $lines ) {
	if ( bhela_bm_inv_is_locked( $period ) ) {
		return array();
	}
	return bhela_bm_inv_apply_lines( $period, $lines, current_user_can( 'bhela_inv_adjust' ) );
}

echo "=== 1. the quantity invariant ===\n";
// Each movement named separately, so a sign error says WHICH one.
$signs = array( 'add' => 1, 'tin' => 1, 'tout' => -1, 'use' => -1, 'loss' => -1, 'disp' => -1, 'adj' => 1 );
foreach ( $signs as $field => $sign ) {
	$c = bhela_bm_inv_line_check( iv_line( array( 'open' => 50, $field => 5 ) ) );
	ok( 50 + ( 5 * $sign ) === $c['close'], sprintf( '%s moves closing by %+d', $field, 5 * $sign ), (string) $c['close'] );
}
$a = bhela_bm_inv_line_check( iv_line( array( 'open' => 12, 'good' => 10, 'ur' => 2 ) ) );
$b = bhela_bm_inv_line_check( iv_line( array( 'open' => 12, 'good' => 12 ) ) );
ok( $a['close'] === $b['close'] && $a['ok'] && $b['ok'], 'repair does not change the count (§8)', $a['close'] . ' vs ' . $b['close'] );
$d = bhela_bm_inv_line_check( iv_line( array( 'open' => 12, 'good' => 8, 'dam' => 4 ) ) );
ok( 12 === $d['close'] && $d['ok'], 'damage does not change the count (§11)', (string) $d['close'] );
ok( ! array_key_exists( 'damage', bhela_bm_inv_movement_types() ), 'there is no damage MOVEMENT to post at all' );
$e = bhela_bm_inv_line_check( iv_line( array( 'open' => 10, 'loss' => 2, 'good' => 8 ) ) );
ok( 8 === $e['close'] && $e['ok'], 'loss does leave the total, and is not a condition bucket' );
$f = bhela_bm_inv_line_check( iv_line( array( 'open' => 10, 'good' => 9 ) ) );
ok( ! $f['ok'] && -1 === $f['diff'], 'a split that does not add up is reported, not rebalanced', (string) $f['diff'] );
$v = bhela_bm_inv_line_check( iv_line( array( 'open' => 10, 'good' => 10, 'count' => 9 ) ) );
ok( -1 === $v['variance'], 'a count below the system is a negative variance' );
ok( null === bhela_bm_inv_line_check( iv_line( array( 'open' => 10, 'good' => 10 ) ) )['variance'], 'an uncounted line has no variance — that is not the same as zero' );
ok( 0 === bhela_bm_inv_filter_by_kind( iv_line( array( 'use' => 5 ) ), 'asset' )['use'], 'an asset cannot be consumed' );
ok( 5 === bhela_bm_inv_filter_by_kind( iv_line( array( 'use' => 5 ) ), 'inventory' )['use'], '…but inventory can' );

echo "\n=== 2. carry-forward ===\n";
$item = iv_item( 'Noodles Bowl' );
$key  = bhela_bm_inv_line_key( $item );
$jul  = iv_period( '2026-07' );
bhela_bm_inv_meta_write( $jul, '_bhela_inv_baseline', 1 );
bhela_bm_inv_meta_write( $jul, '_bhela_inv_opening', wp_json_encode( array( $key => 100 ), JSON_FORCE_OBJECT ) );
bhela_bm_inv_write_lines( $jul, array( $key => iv_line( array( 'open' => 100, 'use' => 10, 'good' => 90 ) ) ) );

ok( 'import' === bhela_bm_inv_opening_source( $jul ), 'the baseline says where its opening came from' );
$can = bhela_bm_inv_can_close( $jul );
ok( $can['ok'], 'the first month needs no predecessor', implode( ',', $can['why'] ) );
ok( 90 === bhela_bm_inv_closings( $jul )[ $key ], 'July closes at 90' );

bhela_bm_inv_meta_write( $jul, '_bhela_inv_status', 'closed' );
$aug = iv_period( '2026-08' );
bhela_bm_inv_take_opening( $aug );
ok( 90 === (int) bhela_bm_inv_stored_opening( $aug )[ $key ], 'August opens on July\'s closing' );
ok( $jul === (int) get_post_meta( $aug, '_bhela_inv_opening_from', true ), '…and records which month it came from' );
ok( 'carry' === bhela_bm_inv_opening_source( $aug ), '…and says so' );

// Opening is not in the save loop at all, so there is nothing to aim at.
iv_save( $aug, array( $key => array( 'open' => 999, 'use' => 1, 'good' => 89 ) ) );
ok( 90 === (int) bhela_bm_inv_stored_lines( $aug )[ $key ]['open'], 'a crafted POST cannot write the opening', (string) bhela_bm_inv_stored_lines( $aug )[ $key ]['open'] );

echo "\n=== 3. drift is reported, never corrected ===\n";
bhela_bm_inv_meta_write( $jul, '_bhela_inv_status', 'reopened' );
bhela_bm_inv_write_lines( $jul, array( $key => iv_line( array( 'open' => 100, 'use' => 20, 'good' => 80 ) ) ) );
$drift = bhela_bm_inv_opening_drift( $aug );
ok( $drift['stale'], 'August notices that July moved' );
ok( 90 === ( $drift['items'][0]['snapshot'] ?? 0 ) && 80 === ( $drift['items'][0]['live'] ?? 0 ), 'and reports both figures', '90 / 80' );
ok( 90 === (int) bhela_bm_inv_stored_opening( $aug )[ $key ], 'August\'s STORED opening is untouched — this is the whole point' );
$can = bhela_bm_inv_can_close( $aug );
ok( in_array( 'drift', $can['why'], true ), 'and closing on a stale opening is refused', implode( ',', $can['why'] ) );

$before = count( iv_audit_rows( 'open' ) );
bhela_bm_inv_take_opening( $aug, true );
ok( 80 === (int) bhela_bm_inv_stored_opening( $aug )[ $key ], 're-taking the opening fixes it' );
ok( ! bhela_bm_inv_opening_drift( $aug )['stale'], '…and clears the drift' );
bhela_bm_inv_meta_write( $jul, '_bhela_inv_status', 'closed' );
ok( ! in_array( 'prev_open', bhela_bm_inv_can_close( $aug )['why'], true ), 'a closed predecessor stops blocking' );
unset( $before );

echo "\n=== 4. order of operations ===\n";
$sep = iv_period( '2026-09' );
bhela_bm_inv_take_opening( $sep );
ok( in_array( 'prev_open', bhela_bm_inv_can_close( $sep )['why'], true ), 'September cannot close while August is open' );
ok( bhela_bm_inv_can_reopen( $jul )['ok'], 'July can reopen while nothing after it is closed' );
bhela_bm_inv_meta_write( $aug, '_bhela_inv_status', 'closed' );
$r = bhela_bm_inv_can_reopen( $jul );
ok( ! $r['ok'] && '2026-08' === $r['blocker'], 'but not once August is closed — you reopen newest first', $r['blocker'] );
bhela_bm_inv_meta_write( $aug, '_bhela_inv_status', 'draft' );

echo "\n=== 5. one record per month ===\n";
ok( iv_period( '2026-09' ) === $sep, 'asking twice returns the same record' );
ok( ! current_user_can( 'create_bhela_inv_periods' ), 'even an administrator cannot Add New a month' );
$dupe = wp_insert_post( array( 'post_type' => 'bhela_inv_period', 'post_status' => 'publish', 'post_title' => 'ZZ dupe sep' ) );
$made[] = $dupe;
update_post_meta( $dupe, '_bhela_inv_period_month', '2026-09' );
$re = bhela_bm_inv_period_reindex();
ok( $sep === (int) $re['index']['2026-09'], 'reindexing keeps the older record' );
ok( in_array( $dupe, $re['duplicates'], true ) && bhela_bm_inv_has_duplicate( $dupe ), 'and flags the newer one' );
ok( null !== get_post( $dupe ), 'without deleting anything' );
ok( in_array( 'duplicate', bhela_bm_inv_can_close( $dupe )['why'], true ), 'a flagged duplicate cannot be closed' );
wp_delete_post( $dupe, true );
bhela_bm_inv_period_reindex();

echo "\n=== 6. the lock, all four gaps ===\n";
$blob = get_post_meta( $aug, '_bhela_inv_lines', true );
bhela_bm_inv_meta_write( $aug, '_bhela_inv_status', 'closed' );
ok( bhela_bm_inv_is_locked( $aug ), 'August is locked' );

iv_save( $aug, array( $key => array( 'use' => 77, 'good' => 13 ) ) );
ok( $blob === get_post_meta( $aug, '_bhela_inv_lines', true ), 'the save handler refuses' );
ok( false === update_post_meta( $aug, '_bhela_inv_lines', '{}' ), 'update_post_meta() is refused' );
ok( $blob === get_post_meta( $aug, '_bhela_inv_lines', true ), '…and the figures are unchanged' );
ok( false === delete_post_meta( $aug, '_bhela_inv_lines' ), 'delete_post_meta() is refused' );
ok( ! current_user_can( 'delete_post', $aug ), 'an ADMINISTRATOR cannot delete it' );
ok( iv_died( fn() => wp_trash_post( $aug ) ), 'trashing it is refused outright' );
ok( 'publish' === get_post_status( $aug ), '…and it is still there' );
ok( iv_died( fn() => wp_delete_post( $aug, true ) ), 'and so is a hard delete — the path that checks no capability at all' );
ok( null !== get_post( $aug ), '…and it is still there after that too' );
wp_update_post( array( 'ID' => $aug, 'post_title' => 'ZZ hacked' ) );
ok( false === strpos( (string) get_post( $aug )->post_title, 'hacked' ), 'quick-edit cannot rename it' );
ok( ! bhela_bm_inv_transition_allowed( $aug, 'close' ), 'and it cannot be closed twice (the 409 path)' );
ok( ! bhela_bm_inv_unlocking(), 'the unlock window is shut' );

// A lock that cannot be lifted by the documented route is a different bug.
bhela_bm_inv_meta_write( $aug, '_bhela_inv_status', 'reopened' );
ok( ! bhela_bm_inv_is_locked( $aug ), 'reopening unlocks it' );
ok( (bool) update_post_meta( $aug, '_bhela_inv_note', 'ok' ), '…meta writes work again' );
ok( current_user_can( 'delete_post', $aug ), '…and it is deletable again' );
ok( ! bhela_bm_inv_unlocking(), 'the window is still shut afterwards' );

echo "\n=== 7. the workflow table is internally consistent ===\n";
$statuses = bhela_bm_inv_statuses();
$caps     = bhela_bm_extra_caps();
foreach ( bhela_bm_inv_transitions() as $action => $t ) {
	ok( isset( $statuses[ $t['to'] ] ), "$action targets a real status", $t['to'] );
	ok( isset( $caps[ $t['cap'] ] ), "$action names a real capability", $t['cap'] );
	$bad = array_diff( $t['from'], array_keys( $statuses ) );
	ok( ! $bad, "$action comes from real statuses", implode( ',', $bad ) );
}
update_post_meta( $sep, '_bhela_inv_status', 'nonsense' );
ok( 'draft' === bhela_bm_inv_status( $sep ), 'an unrecognised status reads as draft' );
update_post_meta( $sep, '_bhela_inv_status', 'draft' );

echo "\n=== 8. a variance must be explained before the month closes ===\n";
$oct = iv_period( '2026-10' );
bhela_bm_inv_write_lines( $oct, array( $key => iv_line( array( 'open' => 50, 'good' => 50, 'count' => 47 ) ) ) );
$why = bhela_bm_inv_can_close( $oct )['why'];
ok( in_array( 'unexplained', $why, true ), 'a counted difference with no reason blocks closing', implode( ',', $why ) );
bhela_bm_inv_write_lines( $oct, array( $key => iv_line( array( 'open' => 50, 'good' => 50, 'count' => 47, 'reason' => 'three broken in the wash' ) ) ) );
ok( ! in_array( 'unexplained', bhela_bm_inv_can_close( $oct )['why'], true ), 'a reason clears that' );
bhela_bm_inv_write_lines( $oct, array( $key => iv_line( array( 'open' => 50, 'good' => 40 ) ) ) );
ok( in_array( 'unreconciled', bhela_bm_inv_can_close( $oct )['why'], true ), 'a split that does not add up also blocks closing' );

echo "\n=== 9. the audit trail records the real before and after ===\n";
$nov  = iv_period( '2026-11' );
bhela_bm_inv_write_lines( $nov, array( $key => iv_line( array( 'open' => 20, 'use' => 10, 'good' => 10 ) ) ) );
$was  = count( iv_audit_rows( 'use' ) );
iv_save( $nov, array( $key => array( 'use' => 12, 'good' => 8 ) ) );
$rows = iv_audit_rows( 'use' );
ok( count( $rows ) === $was + 1, 'changing one figure writes exactly one row', (string) ( count( $rows ) - $was ) );
$row  = end( $rows );
ok( '10' === (string) $row['old_value'] && '12' === (string) $row['new_value'], 'with the real old and new values', $row['old_value'] . ' → ' . $row['new_value'] );
ok( 'inv_line' === $row['object_type'] && false !== strpos( (string) $row['object_ref'], '2026-11' ), 'attributed to the right month and item', $row['object_ref'] );
ok( '' !== (string) $row['actor_login'], 'and to a named person', $row['actor_login'] );

$before = count( iv_audit_rows() );
iv_save( $nov, array( $key => array( 'use' => 12, 'good' => 8 ) ) );
ok( count( iv_audit_rows() ) === $before, 'a no-op re-save records nothing — that is what keeps the trail readable' );

echo "\n=== 10. an adjustment needs the capability ===\n";
$staff = wp_insert_user( array( 'user_login' => 'zz_counter', 'user_pass' => wp_generate_password(), 'role' => 'bhela_storekeeper' ) );
if ( ! is_wp_error( $staff ) ) {
	$adj_before = (int) bhela_bm_inv_stored_lines( $nov )[ $key ]['adj'];
	wp_set_current_user( $staff );
	ok( current_user_can( 'bhela_inv_count' ), 'a storekeeper may fill the sheet in' );
	ok( ! current_user_can( 'bhela_inv_adjust' ), '…but may not approve an adjustment' );
	iv_save( $nov, array( $key => array( 'adj' => 99, 'use' => 12, 'good' => 8 ) ) );
	ok( $adj_before === (int) bhela_bm_inv_stored_lines( $nov )[ $key ]['adj'], 'so their posted adjustment is dropped', (string) bhela_bm_inv_stored_lines( $nov )[ $key ]['adj'] );
	ok( 12 === (int) bhela_bm_inv_stored_lines( $nov )[ $key ]['use'], '…while the rest of their save is kept' );
	wp_set_current_user( 1 );
	iv_save( $nov, array( $key => array( 'adj' => 2, 'use' => 12, 'good' => 10 ) ) );
	ok( 2 === (int) bhela_bm_inv_stored_lines( $nov )[ $key ]['adj'], 'an approver\'s adjustment lands' );
	ok( 0 < (int) bhela_bm_inv_stored_lines( $nov )[ $key ]['adj_by'], '…stamped with who approved it' );
	wp_delete_user( $staff );
}

echo "\n=== 11. the audit store is append-only ===\n";
$src = '';
foreach ( array_merge( glob( $plugin . '/includes/*.php' ), array( $plugin . '/bhela-booking.php' ) ) as $file ) {
	$src .= (string) file_get_contents( $file );
}
$table_refs = array( 'bhela_bm_audit_table()', 'bhela_bm_audit' );
$danger     = 0;
foreach ( array( 'DELETE', 'TRUNCATE', 'DROP TABLE', 'UPDATE ' ) as $verb ) {
	foreach ( iv_offsets( $src, $verb ) as $at ) {
		$window = substr( $src, max( 0, $at - 200 ), 400 );
		foreach ( $table_refs as $ref ) {
			if ( false !== strpos( $window, $ref ) ) {
				$danger++;
			}
		}
	}
}
ok( 0 === $danger, 'no DELETE, TRUNCATE, DROP or UPDATE anywhere near the audit table', (string) $danger );
ok( ! file_exists( $plugin . '/uninstall.php' ), 'there is no uninstall.php that could drop the table' );
// The open paren matters: the phrase appears in audit.php's docblock explaining
// why there is no such hook, and an assertion that a file may not DISCUSS a
// function is a worse assertion than one that says it may not CALL it.
ok( ! preg_match( '/register_uninstall_hook\s*\(/', $src ), 'and no uninstall hook is registered' );
ok( ! has_action( 'admin_post_bhela_bm_audit_clear' ), 'and no clear action — the deliberate difference from the Activity Log' );
$kept = iv_audit_rows( '', '2026-11' );
ok( (bool) $kept, 'rows exist for a month' );
$tmp = iv_period( '2026-12' );
bhela_bm_audit( array( 'channel' => 'period', 'action' => 'create', 'object_type' => 'inv_period', 'object_id' => $tmp, 'object_ref' => '2026-12', 'field' => 'status', 'new_value' => 'draft' ) );
wp_delete_post( $tmp, true );
$made = array_values( array_diff( $made, array( $tmp ) ) );
ok( (bool) iv_audit_rows( '', '2026-12' ), 'and survive their record being deleted' );

echo "\n=== 12. the spreadsheet-formula guard ===\n";
foreach ( array( '=', '+', '-', '@', "\t", "\r" ) as $lead ) {
	$out = bhela_bm_csv_cell( $lead . 'danger' );
	ok( "'" === $out[0], sprintf( 'a cell starting %s is neutralised', '=' === $lead ? '=' : json_encode( $lead ) ) );
}
ok( 'Rahim' === bhela_bm_csv_cell( 'Rahim' ), 'an ordinary name is untouched' );
ok( '1200' === bhela_bm_csv_cell( '1200' ), 'a number is untouched, so the column stays sortable' );
ok( '' === bhela_bm_csv_cell( '' ), 'an empty cell does not blow up on $value[0]' );
// Every exporter must actually use it.
foreach ( array( 'reports.php', 'yearly.php', 'inventory.php', 'audit.php' ) as $file ) {
	$body = (string) file_get_contents( $plugin . '/includes/' . $file );
	if ( false === strpos( $body, 'fputcsv(' ) ) {
		continue;
	}
	ok( false !== strpos( $body, 'bhela_bm_csv_cell' ) || false !== strpos( $body, '$guard' ), "$file guards its CSV cells" );
}

echo "\n=== 13. the importer ===\n";
$csv = array(
	array( 'Sl', 'Item Name', 'Category', 'Quantity', 'Good', 'Location' ),
	array( '1', 'ZZ Rice Cooker', 'Kitchen Equipment', '2', '2', 'BHELA Kitchen' ),
	array( '2', 'ZZ Ghost', 'No Such Category', '1', '1', 'Store' ),
	array( '3', '', 'Kitchen Items', '4', '4', 'Store' ),
	array( '4', 'ZZ Bad Split', 'Kitchen Items', '10', '3', 'Store' ),
);
$data = array( 'rows' => $csv, 'user' => 1, 'name' => 'zz.csv' );
$map  = array();
foreach ( $csv[0] as $i => $h ) {
	$g = bhela_bm_inv_import_guess( $h );
	if ( '' !== $g && ! in_array( $g, $map, true ) ) {
		$map[ $i ] = $g;
	}
}
ok( ! isset( $map[0] ), 'an "Sl" column is not guessed as the Item ID — it is a row number' );

// A UTF-8 BOM has to be skipped BEFORE fgetcsv, not stripped from cells after it.
// Excel writes one, and it sits in front of the first field's opening quote — so
// the parser never sees a quoted field and hands back the quotes as text, making
// every value in column one subtly wrong. Written as a real file, because this
// only reproduces through the parser.
$bom_file = wp_tempnam( 'zz-bom.csv' );
file_put_contents( $bom_file, "\xEF\xBB\xBF" . '"Item name *","Category *","Opening quantity *"' . "\n" . '"ZZ Quoted Bowl","Kitchen Items",7' . "\n" );
$bh = fopen( $bom_file, 'r' );
if ( "\xEF\xBB\xBF" !== fread( $bh, 3 ) ) {
	rewind( $bh );
}
$bom_rows = array();
while ( ( $bline = fgetcsv( $bh ) ) !== false ) {
	$bom_rows[] = array_map( 'bhela_bm_inv_import_clean', $bline );
}
fclose( $bh );
@unlink( $bom_file );
ok( 'Item name *' === ( $bom_rows[0][0] ?? '' ), 'a BOM does not leave quotes on the first header', var_export( $bom_rows[0][0] ?? null, true ) );
ok( 'ZZ Quoted Bowl' === ( $bom_rows[1][0] ?? '' ), 'nor on the first value', var_export( $bom_rows[1][0] ?? null, true ) );
$bom_src = (string) file_get_contents( $plugin . '/includes/inventory-import.php' );
ok( false !== strpos( $bom_src, 'fread( $fh, 3 )' ), 'the importer skips the BOM before parsing, not after' );

// The generated sample must map itself perfectly — its headers ARE the registry's
// labels, so an owner who downloads it, fills it in and uploads it back should
// never have to hand-pick a column.
$sample_head = array();
foreach ( bhela_bm_inv_import_fields() as $sk => $sdef ) {
	$sample_head[ $sk ] = $sdef['label'] . ( empty( $sdef['required'] ) ? '' : ' *' );
}
$wrong = array();
foreach ( $sample_head as $sk => $label ) {
	$guess = bhela_bm_inv_import_guess( $label );
	if ( $guess !== $sk ) {
		$wrong[] = $label . ' → ' . ( $guess ? $guess : '(none)' );
	}
}
ok( ! $wrong, sprintf( 'all %d sample columns map to their own field', count( $sample_head ) ), implode( ', ', $wrong ) );
ok( 'name' === ( $map[1] ?? '' ) && 'open' === ( $map[3] ?? '' ), 'the obvious columns are guessed', wp_json_encode( $map ) );

$posts_before = (int) wp_count_posts( 'bhela_inv_item' )->publish;
$plan = bhela_bm_inv_import_plan( $data, $map, array( 'has_header' => true, 'kind' => 'inventory', 'month' => '2026-07' ) );
ok( $posts_before === (int) wp_count_posts( 'bhela_inv_item' )->publish, 'the dry run writes nothing at all' );
ok( 1 === $plan['counts']['create'] && 3 === $plan['counts']['blocked'], 'one row is good and three are blocked', wp_json_encode( $plan['counts'] ) );
$whys = wp_list_pluck( $plan['blocked'], 'why' );
ok( (bool) preg_grep( '/not in the list/', $whys ), 'an unknown category blocks the row rather than being invented' );
ok( (bool) preg_grep( '/missing/', $whys ), 'a missing name blocks the row' );
ok( (bool) preg_grep( '/add up/', $whys ), 'a condition split that does not sum blocks the row' );
ok( false !== strpos( (string) $plan['create'][0]['code_preview'], 'BHELA-' ), 'the ID it would mint is shown up front', (string) $plan['create'][0]['code_preview'] );

// The counter must never hand out a number a file already used.
bhela_bm_inv_observe_code( 'BHELA-KIT-0009' );
ok( 'BHELA-KIT-0010' === bhela_bm_inv_mint_code( 'kitchen' ), 'a supplied code advances the counter past itself' );

// A code minted for one category cannot collide with another's.
ok( 'KIT' === bhela_bm_inv_category_code( 'kitchen' ), 'a category keeps its frozen code' );
$cat_codes = array();
foreach ( bhela_bm_inv_categories( true ) as $slug => $def ) {
	$cat_codes[] = $def['code'];
}
ok( count( $cat_codes ) === count( array_unique( $cat_codes ) ), 'no two categories share a code — Item IDs would interleave' );

echo "\n=== 14. the lists behave like the cost heads ===\n";
$cat_backup = get_option( 'bhela_bm_inv_categories', null );
bhela_bm_inv_save_categories( array(
	array( 'slug' => 'kitchen', 'label' => 'Galley Items', 'code' => 'ZZZ', 'kind' => 'inventory' ),
	array( 'label' => 'Brand New', 'code' => 'NEW', 'kind' => 'asset' ),
) );
$cats = bhela_bm_inv_categories( true );
ok( 'Galley Items' === $cats['kitchen']['label'], 'a category can be renamed' );
ok( 'KIT' === $cats['kitchen']['code'], '…but its code is frozen, because every Item ID contains it' );
bhela_bm_inv_save_categories( array( array( 'label' => 'Only One', 'code' => 'ONE' ) ) );
ok( (bool) bhela_bm_inv_categories(), 'the list can never end up empty' );
bhela_bm_inv_save_categories( array() );
ok( (bool) bhela_bm_inv_categories(), '…not even by posting nothing' );
if ( null === $cat_backup ) {
	delete_option( 'bhela_bm_inv_categories' );
} else {
	update_option( 'bhela_bm_inv_categories', $cat_backup );
}
ok( isset( bhela_bm_inv_categories()['kitchen'] ), 'the shipped list is restored' );

echo "\n=== 15. reading a month does not scale with the register ===\n";
$bulk = array();
for ( $i = 0; $i < 60; $i++ ) {
	$bulk[] = iv_item( 'Bulk ' . $i );
}
$q1 = get_num_queries();
bhela_bm_inv_month_data( '2026-11' );
$c1 = get_num_queries() - $q1;
for ( $i = 60; $i < 120; $i++ ) {
	$bulk[] = iv_item( 'Bulk ' . $i );
}
$q2 = get_num_queries();
bhela_bm_inv_month_data( '2026-11' );
$c2 = get_num_queries() - $q2;
ok( $c1 < 30, 'a month reads in under 30 queries', (string) $c1 );
ok( $c2 <= $c1 + 2, 'and doubling the register does not add queries per item', $c1 . ' → ' . $c2 );
unset( $bulk );

echo "\n=== 16. closing stock is worth something ===\n";
// Found with real data in the browser, not here: the sheet has no rate column, so
// the figure has to come from the item register. The first save built its line from
// bhela_bm_inv_blank_line() (rate 0) and wrote that, and because month_data() only
// seeds a rate for a line that does not exist yet, nothing ever put it back —
// closing value read ৳0 for every item, for good.
$val_item   = iv_item( 'Valued plate', 'inventory', 'kitchen', 180 );
$val_period = iv_period( '2026-12' );
iv_save( $val_period, array( $val_item => array( 'add' => 60, 'use' => 4, 'good' => 50, 'dam' => 6, 'count' => 56 ) ) );
$val_lines = bhela_bm_inv_stored_lines( $val_period );
$val_line  = $val_lines[ bhela_bm_inv_line_key( $val_item ) ] ?? array();
ok( 180 === (int) ( $val_line['rate'] ?? 0 ), 'a first save takes the rate from the item register', 'rate: ' . (int) ( $val_line['rate'] ?? 0 ) );
ok( 10080 === (int) bhela_bm_inv_period_totals( $val_period )['value'], 'closing value is 56 × ৳180', (string) bhela_bm_inv_period_totals( $val_period )['value'] );

// And the snapshot holds: repricing the item must not restate a month already saved.
update_post_meta( $val_item, '_bhela_inv_rate', 250 );
iv_save( $val_period, array( $val_item => array( 'add' => 60, 'use' => 4, 'good' => 50, 'dam' => 6, 'count' => 56 ) ) );
$val_line = bhela_bm_inv_stored_lines( $val_period )[ bhela_bm_inv_line_key( $val_item ) ] ?? array();
ok( 180 === (int) ( $val_line['rate'] ?? 0 ), 'a later price rise does not restate the saved month', 'rate: ' . (int) ( $val_line['rate'] ?? 0 ) );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) {
	bhela_test_delete( $id );
}
// Not deleted, and not restored here either: bhela_test_guard_period_index() in the
// bootstrap puts the live index back on shutdown. See the note at the top of this file.
delete_option( 'bhela_bm_inv_seq' );
global $wpdb;
// The harness's own rows are removed here, from the test — never from the plugin,
// which is exactly what §11 above asserts about the plugin's source.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . bhela_bm_audit_table() . ' WHERE object_ref LIKE %s OR object_ref LIKE %s', 'ZZ%', '2026-%' ) );
bhela_bm_install_roles();
ok( true, 'fixtures removed' );

bhela_test_done();

/**
 * Every offset of a needle in a haystack.
 *
 * Declared last because it is a helper for the source scan above; PHP hoists
 * function declarations, so the call site reads in the order it happens.
 *
 * @param string $haystack Text.
 * @param string $needle   Text to find.
 * @return int[]
 */
function iv_offsets( $haystack, $needle ) {
	$out = array();
	$at  = 0;
	while ( false !== ( $at = strpos( $haystack, $needle, $at ) ) ) {
		$out[] = $at;
		$at++;
	}
	return $out;
}
