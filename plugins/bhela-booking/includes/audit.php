<?php
/**
 * The audit trail — an append-only record of who changed what, from what, to what.
 *
 * This is deliberately NOT the Activity Log next door. log.php answers "did that
 * email go out?" and is built for it: a 300-entry ring buffer in one option, with
 * a Clear button. Both of those are correct for diagnostics and disqualifying for
 * audit — an inventory register whose history is capped at 300 events and wipeable
 * in one click is not a register.
 *
 * So this is a separate store with different properties:
 *
 *   - A real table, not an option. Indexed filtering on (object_type, object_id),
 *     actor and date, which a meta_query on a CPT cannot do, and outside the post
 *     machinery that could delete it.
 *   - Structured. `old_value` and `new_value` are columns, not prose baked into a
 *     sentence — which is the whole thing log.php cannot do.
 *   - Append-only. There is exactly one SQL writer in this file and it only ever
 *     INSERTs. No DELETE, no UPDATE, no TRUNCATE, no DROP anywhere in the plugin,
 *     no uninstall.php, no register_uninstall_hook, and no Clear button on the
 *     viewer. tests/inventory-test.php asserts every one of those at source level.
 *   - Never pruned. ~20k rows a year at ~300 bytes is about 6 MB/year; a register
 *     whose trail expires is not one. The CSV export is how you archive it.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema version. Bump when the CREATE TABLE below changes so existing sites
 * re-run dbDelta once against the new definition.
 */
define( 'BHELA_BM_AUDIT_DB', 1 );

/** The one table this plugin owns. */
function bhela_bm_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'bhela_bm_audit';
}

/**
 * Channels — the same registry-as-data shape as bhela_bm_log_types(), and for the
 * same reason: the key doubles as the CSS class suffix (.bha-tag--{key}).
 *
 * These are channels, not statuses: "Lists" is not better or worse than "Import".
 */
function bhela_bm_audit_channels() {
	return array(
		'inv'    => array( 'label' => __( 'Items', 'bhela-booking' ) ),
		'period' => array( 'label' => __( 'Monthly Sheets', 'bhela-booking' ) ),
		'lists'  => array( 'label' => __( 'Categories & Locations', 'bhela-booking' ) ),
		'import' => array( 'label' => __( 'Imports', 'bhela-booking' ) ),
		'investor' => array( 'label' => __( 'Investors', 'bhela-booking' ) ),
		// 'period' next door is the monthly STOCK sheet, so this one is named for
		// what it is rather than sharing the word "sheets" with it.
		'cost'   => array( 'label' => __( 'Cost Sheets', 'bhela-booking' ) ),
	);
}

/**
 * Create or update the table.
 *
 * `object_ref` and `actor_login` are denormalised on purpose. The whole point of
 * an audit row is that it still means something after the post or the user is
 * gone — 'BHELA-KIT-0001' and 'uttam' stay readable where a dangling ID does not.
 * `actor_id` is kept as well, for joining while the user still exists.
 */
function bhela_bm_audit_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = bhela_bm_audit_table();
	$collate = $wpdb->get_charset_collate();

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, dbDelta.
	dbDelta(
		"CREATE TABLE $table (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at   DATETIME        NOT NULL,
			created_gmt  DATETIME        NOT NULL,
			actor_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			actor_login  VARCHAR(60)     NOT NULL DEFAULT '',
			channel      VARCHAR(32)     NOT NULL DEFAULT '',
			action       VARCHAR(40)     NOT NULL DEFAULT '',
			object_type  VARCHAR(32)     NOT NULL DEFAULT '',
			object_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			object_ref   VARCHAR(64)     NOT NULL DEFAULT '',
			field        VARCHAR(40)     NOT NULL DEFAULT '',
			old_value    TEXT            NULL,
			new_value    TEXT            NULL,
			reason       TEXT            NULL,
			approval_ref VARCHAR(64)     NOT NULL DEFAULT '',
			ip           VARCHAR(45)     NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY object (object_type, object_id),
			KEY ref (object_ref),
			KEY actor (actor_id),
			KEY created (created_at),
			KEY chan (channel, created_at)
		) $collate"
	);
	// phpcs:enable

	update_option( 'bhela_bm_audit_db', BHELA_BM_AUDIT_DB, false );
}

/**
 * Install on demand.
 *
 * Same gate as bhela_bm_maybe_install_roles() — a version option compared on
 * admin_init at priority 5 — so no activation hook is needed and a site that
 * updated by copying files still gets the table.
 */
function bhela_bm_audit_maybe_install() {
	if ( (int) get_option( 'bhela_bm_audit_db', 0 ) >= BHELA_BM_AUDIT_DB ) {
		return;
	}
	bhela_bm_audit_install();
}
add_action( 'admin_init', 'bhela_bm_audit_maybe_install', 5 );

/** True when the table is present. Cheap enough to call before a write. */
function bhela_bm_audit_ready() {
	static $ready = null;
	if ( null !== $ready ) {
		return $ready;
	}
	global $wpdb;
	$table = bhela_bm_audit_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$ready = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	return $ready;
}

/**
 * How many rows the register has recorded. Shown on the viewer so growth is
 * visible, since nothing ever prunes it.
 */
function bhela_bm_audit_count() {
	global $wpdb;
	if ( ! bhela_bm_audit_ready() ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . bhela_bm_audit_table() );
}

/**
 * The per-request row budget.
 *
 * A monthly sheet POST can legitimately change a few hundred fields. A crafted
 * one could claim to change every field of every line, so the writer stops at
 * this many rows and records the truncation as its own row — a silent stop would
 * leave a trail that lies about being complete.
 */
function bhela_bm_audit_max_per_request() {
	return 2000;
}

/**
 * Record one change. The ONLY function in the plugin that writes to this table,
 * and it only ever inserts.
 *
 * Called defensively everywhere (`if ( function_exists( 'bhela_bm_audit' ) )`),
 * per house convention, so a module loaded alone in a test harness still runs.
 *
 * @param array $args {
 *     @type string     $channel      Key of bhela_bm_audit_channels().
 *     @type string     $action       create|update|close|reopen|adjust|import|delete.
 *     @type string     $object_type  inv_item|inv_period|inv_line|inv_list.
 *     @type int        $object_id    Post ID where there is one.
 *     @type string     $object_ref   Human handle: 'BHELA-KIT-0001', '2026-07'.
 *     @type string     $field        Which field changed.
 *     @type mixed      $old_value    Value before. Null when there was none.
 *     @type mixed      $new_value    Value after.
 *     @type string     $reason       Why, when the change carries one.
 *     @type string     $approval_ref Who authorised it: 'close:2026-07:#3'.
 * }
 * @return int Insert ID, or 0 when nothing was written.
 */
function bhela_bm_audit( $args ) {
	global $wpdb;

	if ( ! bhela_bm_audit_ready() ) {
		return 0;
	}

	// The budget is per request, not per call, so one flooded POST cannot bury
	// the months either side of it.
	static $written = 0;
	$max = bhela_bm_audit_max_per_request();
	if ( $written >= $max ) {
		return 0;
	}
	$written++;

	$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;

	$row = array(
		'created_at'   => current_time( 'mysql' ),
		'created_gmt'  => current_time( 'mysql', true ),
		'actor_id'     => ( $user && $user->ID ) ? (int) $user->ID : 0,
		'actor_login'  => ( $user && $user->ID ) ? (string) $user->user_login : '',
		'channel'      => sanitize_key( $args['channel'] ?? '' ),
		'action'       => sanitize_key( $args['action'] ?? '' ),
		'object_type'  => sanitize_key( $args['object_type'] ?? '' ),
		'object_id'    => (int) ( $args['object_id'] ?? 0 ),
		'object_ref'   => substr( (string) ( $args['object_ref'] ?? '' ), 0, 64 ),
		'field'        => substr( (string) ( $args['field'] ?? '' ), 0, 40 ),
		'old_value'    => isset( $args['old_value'] ) ? (string) $args['old_value'] : null,
		'new_value'    => isset( $args['new_value'] ) ? (string) $args['new_value'] : null,
		'reason'       => isset( $args['reason'] ) && '' !== $args['reason'] ? (string) $args['reason'] : null,
		'approval_ref' => substr( (string) ( $args['approval_ref'] ?? '' ), 0, 64 ),
		'ip'           => function_exists( 'bhela_bm_client_ip' ) ? substr( (string) bhela_bm_client_ip(), 0, 45 ) : '',
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( bhela_bm_audit_table(), $row );
	$id = (int) $wpdb->insert_id;

	// One more than the budget: say so, in the trail itself.
	if ( $written === $max ) {
		$written++; // stop this branch re-entering
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( bhela_bm_audit_table(), array_merge( $row, array(
			'action'       => 'truncated',
			'field'        => '',
			'old_value'    => null,
			'new_value'    => (string) $max,
			'reason'       => 'Row budget for one request reached — later changes in this save were not recorded individually.',
			'approval_ref' => '',
		) ) );
	}

	return $id;
}

/**
 * Read rows back, newest first.
 *
 * Always paged. There is no unbounded read of this table anywhere — it is the one
 * store in the plugin guaranteed to grow forever.
 *
 * @param array $args channel, object_type, object_id, object_ref, actor_id, from, to, per_page, page.
 * @return array{rows:array,total:int}
 */
function bhela_bm_audit_query( $args = array() ) {
	global $wpdb;
	if ( ! bhela_bm_audit_ready() ) {
		return array( 'rows' => array(), 'total' => 0 );
	}

	$table = bhela_bm_audit_table();
	$where = array( '1=1' );
	$prep  = array();

	foreach ( array( 'channel', 'object_type', 'object_ref' ) as $key ) {
		if ( ! empty( $args[ $key ] ) ) {
			$where[] = "$key = %s";
			$prep[]  = (string) $args[ $key ];
		}
	}
	foreach ( array( 'object_id', 'actor_id' ) as $key ) {
		if ( ! empty( $args[ $key ] ) ) {
			$where[] = "$key = %d";
			$prep[]  = (int) $args[ $key ];
		}
	}
	if ( ! empty( $args['from'] ) ) {
		$where[] = 'created_at >= %s';
		$prep[]  = $args['from'] . ' 00:00:00';
	}
	if ( ! empty( $args['to'] ) ) {
		$where[] = 'created_at <= %s';
		$prep[]  = $args['to'] . ' 23:59:59';
	}

	$sql   = implode( ' AND ', $where );
	$per   = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$total = (int) $wpdb->get_var(
		$prep ? $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $sql", $prep )
		      : "SELECT COUNT(*) FROM $table WHERE $sql"
	);
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE $sql ORDER BY id DESC LIMIT %d OFFSET %d",
			array_merge( $prep, array( $per, ( $page - 1 ) * $per ) )
		),
		ARRAY_A
	);
	// phpcs:enable

	return array( 'rows' => is_array( $rows ) ? $rows : array(), 'total' => $total );
}

/**
 * Every row for one object, oldest first — the "history of this item" reading.
 *
 * @param string $type Object type.
 * @param int    $id   Object ID.
 * @return array
 */
function bhela_bm_audit_history( $type, $id, $limit = 200 ) {
	global $wpdb;
	if ( ! bhela_bm_audit_ready() ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . bhela_bm_audit_table() . ' WHERE object_type = %s AND object_id = %d ORDER BY id ASC LIMIT %d',
		$type,
		(int) $id,
		max( 1, min( 500, (int) $limit ) )
	), ARRAY_A );
	return is_array( $rows ) ? $rows : array();
}

/* =========================================================
 * THE VIEWER
 *
 * Deliberately unlike the Activity Log next door in exactly one way: there is no
 * Clear button, and no admin_post action that could become one. That difference is
 * the whole reason this store exists, so both screens say which is which.
 * ========================================================= */

/** Menu entry. */
function bhela_bm_audit_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'store' ),
		__( 'Audit Trail', 'bhela-booking' ),
		'🔩 ' . __( 'Audit Trail', 'bhela-booking' ),
		'bhela_inv_audit',
		'bhela-bm-audit',
		'bhela_bm_audit_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_audit_menu' );

/** Filters currently applied, sanitised. */
function bhela_bm_audit_filters() {
	return array(
		'channel'    => sanitize_key( $_GET['channel'] ?? '' ),
		'object_ref' => sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) ),
		'actor_id'   => (int) ( $_GET['actor'] ?? 0 ),
		'from'       => bhela_bm_audit_date( $_GET['from'] ?? '' ),
		'to'         => bhela_bm_audit_date( $_GET['to'] ?? '' ),
	);
}

/** A date only in the exact storage format, and only if it is a real one. */
function bhela_bm_audit_date( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/** The Audit Trail screen. */
function bhela_bm_audit_page() {
	if ( ! current_user_can( 'bhela_inv_audit' ) ) {
		return;
	}
	$filters = bhela_bm_audit_filters();
	$page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per     = 50;
	$data    = bhela_bm_audit_query( array_merge( $filters, array( 'page' => $page, 'per_page' => $per ) ) );
	$total   = (int) $data['total'];
	$pages   = max( 1, (int) ceil( $total / $per ) );
	$chans   = bhela_bm_audit_channels();

	$csv = wp_nonce_url(
		add_query_arg( array_merge( array( 'action' => 'bhela_bm_audit_csv' ), array_filter( $filters ) ), admin_url( 'admin-post.php' ) ),
		'bhela_bm_audit_csv'
	);
	$actions = '<a class="button" href="' . esc_url( $csv ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>';

	$base = add_query_arg( array_merge(
		array( 'page' => 'bhela-bm-audit' ),
		array_filter( array( 'ref' => $filters['object_ref'], 'from' => $filters['from'], 'to' => $filters['to'] ) )
	), admin_url( 'edit.php' ) );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🔩',
			__( 'Audit Trail', 'bhela-booking' ),
			__( 'Who changed which figure, what it was before, and why. Nothing here is ever edited or deleted — this is the register\'s memory, not a log you tidy up.', 'bhela-booking' ),
			$actions
		);
		?>

		<div class="bha-panel bha-buttons">
			<a class="button <?php echo '' === $filters['channel'] ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Everything', 'bhela-booking' ); ?></a>
			<?php foreach ( $chans as $key => $def ) : ?>
				<a class="button <?php echo $filters['channel'] === $key ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'channel', $key, $base ) ); ?>"><?php echo esc_html( $def['label'] ); ?></a>
			<?php endforeach; ?>
		</div>

		<div class="bha-bar">
			<form method="get">
				<?php // No post_type: this page is a child of admin.php now, not edit.php.
				// Leaving it in would send the filter to the Posts list, silently. ?>
				<input type="hidden" name="page" value="bhela-bm-audit">
				<input type="hidden" name="channel" value="<?php echo esc_attr( $filters['channel'] ); ?>">
				<div class="bha-field">
					<label for="bhela-audit-ref"><?php esc_html_e( 'Item ID or month', 'bhela-booking' ); ?></label>
					<input type="text" id="bhela-audit-ref" name="ref" value="<?php echo esc_attr( $filters['object_ref'] ); ?>" placeholder="BHELA-KIT-0001">
				</div>
				<div class="bha-field">
					<label for="bhela-audit-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-audit-from" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-audit-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-audit-to" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>">
				</div>
				<div class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'bhela-booking' ); ?></button></div>
			</form>
		</div>

		<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'When', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'What', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Which record', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Was', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Became', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Who', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $data['rows'] ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Nothing recorded yet under this filter.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $data['rows'] as $r ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'j M Y H:i', $r['created_at'] ) ); ?></td>
						<td><span class="bha-pill bha-pill--tag bha-tag--<?php echo esc_attr( $r['channel'] ); ?>"><?php echo esc_html( $chans[ $r['channel'] ]['label'] ?? $r['channel'] ); ?></span></td>
						<td><?php echo esc_html( $r['action'] ); ?>
							<?php if ( '' !== $r['field'] ) : ?><br><span class="bha-sub"><?php echo esc_html( $r['field'] ); ?></span><?php endif; ?></td>
						<td><?php echo esc_html( $r['object_ref'] ? $r['object_ref'] : ( $r['object_type'] . ' #' . $r['object_id'] ) ); ?>
							<?php if ( ! empty( $r['reason'] ) ) : ?><br><span class="bha-sub"><?php echo esc_html( $r['reason'] ); ?></span><?php endif; ?></td>
						<td class="bha-num"><?php echo esc_html( ( null === $r['old_value'] || '' === $r['old_value'] ) ? '—' : $r['old_value'] ); ?></td>
						<td class="bha-num"><strong><?php echo esc_html( ( null === $r['new_value'] || '' === $r['new_value'] ) ? '—' : $r['new_value'] ); ?></strong></td>
						<td><?php echo esc_html( $r['actor_login'] ? $r['actor_login'] : '—' ); ?>
							<?php if ( ! empty( $r['approval_ref'] ) ) : ?><br><span class="bha-sub"><?php echo esc_html( $r['approval_ref'] ); ?></span><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $pages > 1 ) : ?>
			<p class="bha-buttons">
				<?php if ( $page > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'channel' => $filters['channel'], 'paged' => $page - 1 ), $base ) ); ?>">‹ <?php esc_html_e( 'Newer', 'bhela-booking' ); ?></a>
				<?php endif; ?>
				<?php if ( $page < $pages ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'channel' => $filters['channel'], 'paged' => $page + 1 ), $base ) ); ?>"><?php esc_html_e( 'Older', 'bhela-booking' ); ?> ›</a>
				<?php endif; ?>
				<span class="bha-note"><?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'bhela-booking' ),
						(int) $page,
						(int) $pages
					);
				?></span>
			</p>
		<?php endif; ?>

		<p class="bha-note"><?php
			printf(
				/* translators: %s: number of recorded events */
				wp_kses_post( __( '%s events recorded. Nothing is ever removed, and there is deliberately no button here that could remove one — download the CSV if you want a copy off-site. The Activity Log is the other screen: that one is for "did that email go out", it is capped, and it can be cleared.', 'bhela-booking' ) ),
				'<span class="bha-plain">' . esc_html( number_format_i18n( bhela_bm_audit_count() ) ) . '</span>'
			);
		?></p>
	</div>
	<?php
}

/** Stream the filtered trail as CSV. */
function bhela_bm_audit_csv() {
	if ( ! current_user_can( 'bhela_inv_audit' ) ) {
		wp_die( esc_html__( 'You are not allowed to export the audit trail.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_audit_csv' );

	// Capped rather than unbounded: this is the one table guaranteed to grow
	// forever, and a full export of a decade is not a page request.
	$data = bhela_bm_audit_query( array_merge( bhela_bm_audit_filters(), array( 'page' => 1, 'per_page' => 200 ) ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-audit-' . current_time( 'Y-m-d' ) . '.csv"' );

	$fh = fopen( 'php://output', 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );
	fputcsv( $fh, array( 'When', 'Channel', 'Action', 'Record type', 'Record', 'Field', 'Was', 'Became', 'Reason', 'Approval', 'Who', 'IP' ) );

	$guard = function_exists( 'bhela_bm_csv_cell' ) ? 'bhela_bm_csv_cell' : 'strval';
	foreach ( $data['rows'] as $r ) {
		fputcsv( $fh, array(
			$r['created_at'],
			$guard( $r['channel'] ),
			$guard( $r['action'] ),
			$guard( $r['object_type'] ),
			$guard( $r['object_ref'] ),
			$guard( $r['field'] ),
			$guard( (string) $r['old_value'] ),
			$guard( (string) $r['new_value'] ),
			$guard( (string) $r['reason'] ),
			$guard( $r['approval_ref'] ),
			$guard( $r['actor_login'] ),
			$guard( $r['ip'] ),
		) );
	}
	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_audit_csv', 'bhela_bm_audit_csv' );
