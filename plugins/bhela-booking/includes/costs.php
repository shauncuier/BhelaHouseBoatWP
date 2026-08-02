<?php
/**
 * Trip cost sheet — per-trip operating expenses with a prepare/check/approve chain.
 *
 * Replaces the "BHELA's Trip Cost Sheet" spreadsheet. Two things the sheet could
 * not do live here: earnings are read from the bookings the site already holds
 * instead of being re-keyed by hand, and the three signature lines are real
 * gates — a sheet cannot reach Approved without passing through a checker, and
 * once approved it is locked to everyone but an administrator.
 *
 * The roles and capabilities this module checks against live in includes/roles.php.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * POST TYPE
 * ========================================================= */

function bhela_bm_register_cost_cpt() {
	register_post_type( 'bhela_cost', array(
		'labels' => array(
			'name'          => __( 'Cost Sheets', 'bhela-booking' ),
			'singular_name' => __( 'Cost Sheet', 'bhela-booking' ),
			'menu_name'     => __( '🧾 Cost Sheets', 'bhela-booking' ),
			'add_new'       => __( 'Add Cost Sheet', 'bhela-booking' ),
			'add_new_item'  => __( 'New Trip Cost Sheet', 'bhela-booking' ),
			'edit_item'     => __( 'Trip Cost Sheet', 'bhela-booking' ),
			'all_items'     => __( '🧾 Cost Sheets', 'bhela-booking' ),
			'search_items'  => __( 'Search Cost Sheets', 'bhela-booking' ),
			'not_found'     => __( 'No cost sheets yet.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		// Always nested. Deciding this with current_user_can() would make the
		// registration depend on who is asking — and `init` runs before the
		// current user is reliably resolved in every context, so the menu came
		// out wrong. Cost-only staff get a top-level entry from
		// bhela_bm_cost_standalone_menu() instead, on `admin_menu`, where
		// capability checks are trustworthy.
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'rewrite'             => false,
		'capability_type'     => array( 'bhela_cost', 'bhela_costs' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'menu_icon'           => 'dashicons-media-spreadsheet',
		'supports'            => array( 'title' ),
	) );
}
add_action( 'init', 'bhela_bm_register_cost_cpt' );

/**
 * Cost sheets live under Bookings, which cost-only staff cannot see — so for
 * them the whole screen would be unreachable. Give that case its own top-level
 * menu item. Runs on `admin_menu`, where the current user is settled.
 */
function bhela_bm_cost_standalone_menu() {
	if ( current_user_can( 'edit_bhela_bookings' ) || ! current_user_can( 'edit_bhela_costs' ) ) {
		return;
	}
	// Detach it from Bookings first. WordPress promotes a parent menu whenever
	// any of its children is visible, so leaving it nested would show these
	// users a "Bookings" menu they cannot otherwise use — with Cost Sheets as
	// its only item, duplicating the standalone entry added below.
	remove_submenu_page( 'edit.php?post_type=bhela_booking', 'edit.php?post_type=bhela_cost' );
	add_menu_page(
		__( 'Cost Sheets', 'bhela-booking' ),
		__( '🧾 Cost Sheets', 'bhela-booking' ),
		'edit_bhela_costs',
		'edit.php?post_type=bhela_cost',
		'',
		'dashicons-media-spreadsheet',
		26
	);
}
// Priority 20: core's _add_post_type_submenus() runs at 10, so the nested entry
// has to exist before this can remove it.
add_action( 'admin_menu', 'bhela_bm_cost_standalone_menu', 20 );

/**
 * Cost-only staff have no booking capability, so wp-admin's Dashboard is a dead
 * end for them. Send them straight to the only screen they can use.
 */
function bhela_bm_cost_role_redirect() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	global $pagenow;
	if ( 'index.php' !== $pagenow ) {
		return;
	}
	if ( current_user_can( 'edit_bhela_bookings' ) || ! current_user_can( 'edit_bhela_costs' ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=bhela_cost' ) );
	exit;
}
add_action( 'admin_init', 'bhela_bm_cost_role_redirect' );

/* =========================================================
 * SHEET SHAPE
 * ========================================================= */

/** The 21 expense heads from the spreadsheet, in its order. */
function bhela_bm_cost_items() {
	return array(
		'Engine Fuel (Diesel)',
		'Electricity Bill',
		'Groceries (Rice, Spices Etc)',
		'Meat (Duck/Chicken/Beef)',
		'Fish',
		'Kitchen Market (Vegetables)',
		'Gas Bill',
		'Staff Convency',
		'Jetty Charge (Docking)',
		'Water',
		'Fruits',
		'Dry Fish',
		'Local Bill',
		'Laundry',
		'Ice',
		'Movement Bill',
		'Guest see off cost',
		'Repair Bill (Minor)',
		'B2B Partner',
		'Staff Bill',
		'Others (Any Bill & Purchase)',
	);
}

/**
 * Validate a travel date, in the one format the meta is stored in.
 *
 * Delegates to the trip report's validator when that module is loaded so both
 * screens accept exactly the same input; falls back to its own check because
 * costs.php must not depend on load order.
 *
 * @param mixed $value Raw request value.
 * @return string Valid Y-m-d date, or ''.
 */
function bhela_bm_cost_date( $value ) {
	if ( function_exists( 'bhela_bm_report_date' ) ) {
		return bhela_bm_report_date( $value );
	}
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/** How many blank, preparer-labelled rows sit under the fixed heads. */
function bhela_bm_cost_extra_rows() {
	return 3;
}

/** Header fields, mirroring the top block of the spreadsheet. */
function bhela_bm_cost_header_fields() {
	return array(
		'trip_id'        => array( 'label' => 'Trip ID', 'type' => 'text' ),
		'duration'       => array( 'label' => 'Trip Duration', 'type' => 'text' ),
		'check_in'       => array( 'label' => 'Check In', 'type' => 'text' ),
		'check_out'      => array( 'label' => 'Check Out', 'type' => 'text' ),
		'total_guest'    => array( 'label' => 'Total Guest', 'type' => 'number' ),
		'onboard_people' => array( 'label' => 'Total Onboarding People', 'type' => 'number' ),
		'guest_type'     => array( 'label' => 'Guest Type', 'type' => 'text' ),
		'reserve'        => array( 'label' => 'Reserve', 'type' => 'text' ),
	);
}

/** Workflow states, in order, with the colour used for the status pill. */
function bhela_bm_cost_statuses() {
	return array(
		'draft'    => array( 'label' => 'Draft', 'color' => '#787c82' ),
		'prepared' => array( 'label' => 'Prepared', 'color' => '#b45309' ),
		'checked'  => array( 'label' => 'Checked', 'color' => '#1d4ed8' ),
		'approved' => array( 'label' => 'Approved', 'color' => '#1a7f37' ),
	);
}

function bhela_bm_cost_status( $post_id ) {
	$s = get_post_meta( $post_id, '_bhela_cost_status', true );
	return array_key_exists( $s, bhela_bm_cost_statuses() ) ? $s : 'draft';
}

/** An approved sheet is frozen for everyone except an administrator unlocking it. */
function bhela_bm_cost_is_locked( $post_id ) {
	return 'approved' === bhela_bm_cost_status( $post_id );
}

/**
 * The stored line rows, padded out to the full fixed + extra row set.
 *
 * Always returns every row so the edit form and the totals agree even for a
 * sheet saved before a head was added to bhela_bm_cost_items().
 *
 * @param int $post_id Cost sheet ID (0 for a brand-new sheet).
 * @return array<int, array{label:string,fixed:bool,p1:int,p2:int,p3:int,remark:string,sub:int}>
 */
function bhela_bm_cost_lines( $post_id = 0 ) {
	$saved = $post_id ? json_decode( (string) get_post_meta( $post_id, '_bhela_cost_lines', true ), true ) : array();
	$saved = is_array( $saved ) ? $saved : array();

	$rows  = array();
	$fixed = bhela_bm_cost_items();
	$count = count( $fixed ) + bhela_bm_cost_extra_rows();

	for ( $i = 0; $i < $count; $i++ ) {
		$row  = isset( $saved[ $i ] ) && is_array( $saved[ $i ] ) ? $saved[ $i ] : array();
		$is_f = $i < count( $fixed );
		$p1   = (int) ( $row['p1'] ?? 0 );
		$p2   = (int) ( $row['p2'] ?? 0 );
		$p3   = (int) ( $row['p3'] ?? 0 );
		$rows[] = array(
			// A fixed head always takes its label from code, so renaming one in
			// bhela_bm_cost_items() updates every past sheet too.
			'label'  => $is_f ? $fixed[ $i ] : (string) ( $row['label'] ?? '' ),
			'fixed'  => $is_f,
			'p1'     => $p1,
			'p2'     => $p2,
			'p3'     => $p3,
			'remark' => (string) ( $row['remark'] ?? '' ),
			'sub'    => $p1 + $p2 + $p3,
		);
	}
	return $rows;
}

/** Grand total of every line's sub-total. */
function bhela_bm_cost_total( $lines ) {
	$sum = 0;
	foreach ( $lines as $l ) {
		$sum += (int) $l['sub'];
	}
	return $sum;
}

/**
 * What the bookings say this trip earned.
 *
 * Reuses the trip report's query rather than writing a second one, so the cost
 * sheet and the Trip Report can never disagree about a date.
 *
 * @param string $date Y-m-d travel date.
 * @return array{total:int,paid:int,due:int,bookings:int}
 */
function bhela_bm_cost_booking_earnings( $date ) {
	$empty = array( 'total' => 0, 'paid' => 0, 'due' => 0, 'bookings' => 0 );
	if ( ! $date || ! function_exists( 'bhela_bm_report_rows' ) ) {
		return $empty;
	}
	$data = bhela_bm_report_rows( $date, $date, false );
	$t    = $data['totals'];
	return array(
		'total'    => (int) $t['total'],
		'paid'     => (int) $t['paid'],
		'due'      => (int) $t['due'],
		'bookings' => (int) $t['bookings'],
	);
}

/**
 * Trip calendar details for a date, if that date is a scheduled trip.
 *
 * @param string $date Y-m-d.
 * @return array{label:string,days:string,end:string}
 */
function bhela_bm_cost_trip_info( $date ) {
	$out = array( 'label' => '', 'days' => '', 'end' => '' );
	if ( ! $date || ! function_exists( 'bhela_bm_get_trips' ) ) {
		return $out;
	}
	foreach ( bhela_bm_get_trips() as $t ) {
		if ( ( $t['date'] ?? '' ) === $date ) {
			return array(
				'label' => (string) ( $t['label'] ?? '' ),
				'days'  => (string) ( $t['days'] ?? '' ),
				'end'   => (string) ( $t['end'] ?? '' ),
			);
		}
	}
	return $out;
}

/**
 * Everything the sheet can derive from a travel date, as JSON.
 *
 * The figures are computed server-side on page load too, but that only helps a
 * sheet that already has a date saved. Choosing a date on a new sheet used to
 * show nothing until after a save — this is what makes it fill immediately.
 */
function bhela_bm_cost_lookup() {
	check_ajax_referer( 'bhela_bm_cost_lookup' );
	if ( ! current_user_can( 'edit_bhela_costs' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bhela-booking' ) ), 403 );
	}

	$date = bhela_bm_cost_date( wp_unslash( $_GET['date'] ?? '' ) );
	if ( ! $date ) {
		wp_send_json_success( array(
			'date'  => '',
			'money' => array( 'bookings' => 0, 'total' => 0, 'paid' => 0, 'due' => 0 ),
			'hint'  => __( 'Pick a trip date to pull its bookings.', 'bhela-booking' ),
		) );
	}

	$data = bhela_bm_report_rows( $date, $date, false );
	$t    = $data['totals'];
	$trip = bhela_bm_cost_trip_info( $date );

	wp_send_json_success( array(
		'date'      => $date,
		'money'     => array(
			'bookings' => (int) $t['bookings'],
			'total'    => (int) $t['total'],
			'paid'     => (int) $t['paid'],
			'due'      => (int) $t['due'],
		),
		'guests'    => (int) $t['guests'],
		'cabins'    => (int) $t['cabins'],
		'duration'  => $trip['days'],
		'check_in'  => $date,
		'check_out' => $trip['end'] ? $trip['end'] : $date,
		'title'     => $trip['label'] ? $trip['label'] : mysql2date( 'j M Y', $date ),
		'hint'      => sprintf(
			/* translators: 1: bookings count, 2: total, 3: collected, 4: outstanding */
			__( '%1$d booking(s) on this date · invoiced %2$s · collected %3$s · due %4$s', 'bhela-booking' ),
			(int) $t['bookings'],
			bhela_bm_money( $t['total'] ),
			bhela_bm_money( $t['paid'] ),
			bhela_bm_money( $t['due'] )
		),
	) );
}
add_action( 'wp_ajax_bhela_bm_cost_lookup', 'bhela_bm_cost_lookup' );

/* =========================================================
 * LIST TABLE
 * ========================================================= */

function bhela_bm_cost_columns( $columns ) {
	return array(
		'cb'        => $columns['cb'] ?? '',
		'title'     => __( 'Trip', 'bhela-booking' ),
		'trip_date' => __( 'Trip Date', 'bhela-booking' ),
		'cost'      => __( 'Total Cost', 'bhela-booking' ),
		'earnings'  => __( 'Earnings', 'bhela-booking' ),
		'profit'    => __( 'Profit', 'bhela-booking' ),
		'cstatus'   => __( 'Status', 'bhela-booking' ),
		'signoff'   => __( 'Sign-off', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_cost_posts_columns', 'bhela_bm_cost_columns' );

function bhela_bm_cost_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'trip_date':
			$d = get_post_meta( $post_id, '_bhela_cost_trip_date', true );
			echo $d ? esc_html( mysql2date( 'j M Y', $d ) ) : '—';
			break;
		case 'cost':
			echo esc_html( bhela_bm_money( (int) get_post_meta( $post_id, '_bhela_cost_total', true ) ) );
			break;
		case 'earnings':
			echo esc_html( bhela_bm_money( (int) get_post_meta( $post_id, '_bhela_cost_earnings', true ) ) );
			break;
		case 'profit':
			$p = (int) get_post_meta( $post_id, '_bhela_cost_earnings', true ) - (int) get_post_meta( $post_id, '_bhela_cost_total', true );
			printf(
				'<strong style="color:%s">%s</strong>',
				esc_attr( $p < 0 ? '#b32d2e' : '#1a7f37' ),
				esc_html( bhela_bm_money( $p ) )
			);
			break;
		case 'cstatus':
			$s   = bhela_bm_cost_status( $post_id );
			$def = bhela_bm_cost_statuses()[ $s ];
			printf(
				'<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-weight:600;color:#fff;background:%s;font-size:11px;">%s</span>',
				esc_attr( $def['color'] ),
				esc_html( $def['label'] )
			);
			break;
		case 'signoff':
			foreach ( array( 'prepared' => 'Prepared', 'checked' => 'Checked', 'approved' => 'Approved' ) as $key => $label ) {
				$by = (int) get_post_meta( $post_id, '_bhela_cost_' . $key . '_by', true );
				if ( ! $by ) {
					continue;
				}
				$u = get_userdata( $by );
				printf(
					'<div style="font-size:11px;color:#50575e">%s: <strong>%s</strong></div>',
					esc_html( $label ),
					esc_html( $u ? $u->display_name : '#' . $by )
				);
			}
			break;
	}
}
add_action( 'manage_bhela_cost_posts_custom_column', 'bhela_bm_cost_column_content', 10, 2 );

function bhela_bm_cost_sortable( $columns ) {
	$columns['trip_date'] = 'trip_date';
	return $columns;
}
add_filter( 'manage_edit-bhela_cost_sortable_columns', 'bhela_bm_cost_sortable' );

/** Status filter on the list table. */
function bhela_bm_cost_status_filter() {
	global $typenow;
	if ( 'bhela_cost' !== $typenow ) {
		return;
	}
	$current = isset( $_GET['bhela_cost_status'] ) ? sanitize_key( $_GET['bhela_cost_status'] ) : '';
	echo '<select name="bhela_cost_status"><option value="">' . esc_html__( 'All statuses', 'bhela-booking' ) . '</option>';
	foreach ( bhela_bm_cost_statuses() as $key => $def ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $def['label'] ) );
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'bhela_bm_cost_status_filter' );

function bhela_bm_cost_status_filter_query( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query()
		|| 'bhela_cost' !== ( $_GET['post_type'] ?? '' ) ) {
		return;
	}
	if ( ! empty( $_GET['bhela_cost_status'] ) ) {
		$query->set( 'meta_key', '_bhela_cost_status' );
		$query->set( 'meta_value', sanitize_key( $_GET['bhela_cost_status'] ) );
	} elseif ( 'trip_date' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', '_bhela_cost_trip_date' );
		$query->set( 'orderby', 'meta_value' );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_cost_status_filter_query' );

/* =========================================================
 * EDIT SCREEN
 * ========================================================= */

function bhela_bm_cost_meta_boxes() {
	add_meta_box( 'bhela_cost_sheet', __( 'Trip Cost Sheet', 'bhela-booking' ), 'bhela_bm_cost_sheet_cb', 'bhela_cost', 'normal', 'high' );
	add_meta_box( 'bhela_cost_workflow', __( 'Approval', 'bhela-booking' ), 'bhela_bm_cost_workflow_cb', 'bhela_cost', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_cost_meta_boxes' );

/** The approval sidebar: current state, who signed what, and the next action. */
function bhela_bm_cost_workflow_cb( $post ) {
	$status   = bhela_bm_cost_status( $post->ID );
	$statuses = bhela_bm_cost_statuses();
	$def      = $statuses[ $status ];
	$note     = get_post_meta( $post->ID, '_bhela_cost_note', true );

	$action = function ( $do ) use ( $post ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=bhela_bm_cost_transition&do=' . $do . '&sheet=' . $post->ID ),
			'bhela_bm_cost_transition_' . $post->ID
		);
	};
	?>
	<p style="margin-top:0">
		<span style="display:inline-block;padding:3px 12px;border-radius:12px;font-weight:600;color:#fff;background:<?php echo esc_attr( $def['color'] ); ?>"><?php echo esc_html( $def['label'] ); ?></span>
	</p>

	<table style="width:100%;font-size:12px;border-collapse:collapse">
		<?php foreach ( array( 'prepared' => 'Prepared by', 'checked' => 'Checked by', 'approved' => 'Approved by' ) as $key => $label ) :
			$by = (int) get_post_meta( $post->ID, '_bhela_cost_' . $key . '_by', true );
			$at = get_post_meta( $post->ID, '_bhela_cost_' . $key . '_at', true );
			$u  = $by ? get_userdata( $by ) : null;
			?>
			<tr>
				<td style="padding:5px 0;color:#50575e"><?php echo esc_html( $label ); ?></td>
				<td style="padding:5px 0;text-align:right">
					<?php if ( $u ) : ?>
						<strong><?php echo esc_html( $u->display_name ); ?></strong><br>
						<span style="color:#787c82"><?php echo esc_html( mysql2date( 'j M, g:i a', $at ) ); ?></span>
					<?php else : ?>
						<span style="color:#a7aaad">—</span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>

	<?php if ( $note ) : ?>
		<p style="background:#fcf9e8;border-left:3px solid #b45309;padding:8px 10px;font-size:12px;margin:12px 0 0">
			<?php echo esc_html( $note ); ?>
		</p>
	<?php endif; ?>

	<hr style="margin:14px 0">

	<?php if ( 'draft' === $status && current_user_can( 'bhela_cost_prepare' ) ) : ?>
		<p><a class="button button-primary" href="<?php echo esc_url( $action( 'submit' ) ); ?>"><?php esc_html_e( 'Submit for check', 'bhela-booking' ); ?></a></p>
		<p style="color:#787c82;font-size:11px;margin:0"><?php esc_html_e( 'Save your figures first — submitting does not save the form.', 'bhela-booking' ); ?></p>
	<?php endif; ?>

	<?php if ( 'prepared' === $status && current_user_can( 'bhela_cost_check' ) ) : ?>
		<p><a class="button button-primary" href="<?php echo esc_url( $action( 'check' ) ); ?>"><?php esc_html_e( '✓ Mark as checked', 'bhela-booking' ); ?></a></p>
		<p><a class="button" href="<?php echo esc_url( $action( 'return' ) ); ?>"><?php esc_html_e( '↩ Return to preparer', 'bhela-booking' ); ?></a></p>
	<?php endif; ?>

	<?php if ( 'checked' === $status && current_user_can( 'bhela_cost_approve' ) ) : ?>
		<p><a class="button button-primary" href="<?php echo esc_url( $action( 'approve' ) ); ?>"><?php esc_html_e( '✓ Approve', 'bhela-booking' ); ?></a></p>
		<p><a class="button" href="<?php echo esc_url( $action( 'return' ) ); ?>"><?php esc_html_e( '↩ Return to preparer', 'bhela-booking' ); ?></a></p>
	<?php endif; ?>

	<?php if ( 'approved' === $status ) : ?>
		<p style="color:#1a7f37;font-weight:600;margin:0 0 10px">🔒 <?php esc_html_e( 'Locked. Figures can no longer be edited.', 'bhela-booking' ); ?></p>
		<?php if ( current_user_can( 'bhela_cost_approve' ) ) : ?>
			<p><a class="button" href="<?php echo esc_url( $action( 'unlock' ) ); ?>"
				onclick="return confirm('<?php echo esc_js( __( 'Unlock this sheet? It goes back to Prepared and the check and approval are cleared.', 'bhela-booking' ) ); ?>')"><?php esc_html_e( '🔓 Unlock for editing', 'bhela-booking' ); ?></a></p>
		<?php endif; ?>
	<?php endif; ?>

	<p style="margin:14px 0 0"><a class="button" href="<?php echo esc_url( add_query_arg( 'bhela_print', '1' ) ); ?>" target="_blank">🖨️ <?php esc_html_e( 'Print view', 'bhela-booking' ); ?></a></p>
	<?php
}

/** The sheet itself: header block, expense rows, totals. */
function bhela_bm_cost_sheet_cb( $post ) {
	wp_nonce_field( 'bhela_bm_cost_save', 'bhela_bm_cost_nonce' );

	$locked  = bhela_bm_cost_is_locked( $post->ID );
	$date    = get_post_meta( $post->ID, '_bhela_cost_trip_date', true );
	$header  = json_decode( (string) get_post_meta( $post->ID, '_bhela_cost_header', true ), true );
	$header  = is_array( $header ) ? $header : array();
	$lines   = bhela_bm_cost_lines( $post->ID );
	$total   = bhela_bm_cost_total( $lines );
	$book    = bhela_bm_cost_booking_earnings( $date );

	// A sheet that has never been saved shows the booking figure straight away,
	// so the preparer starts from the real number instead of a zero.
	$stored_earn = get_post_meta( $post->ID, '_bhela_cost_earnings', true );
	$earnings    = '' === $stored_earn ? $book['total'] : (int) $stored_earn;
	$profit      = $earnings - $total;
	$ro          = $locked ? ' readonly disabled' : '';

	$trip_dates = array();
	if ( function_exists( 'bhela_bm_get_trips' ) ) {
		foreach ( bhela_bm_get_trips() as $trip ) {
			if ( ! empty( $trip['date'] ) ) {
				$trip_dates[ $trip['date'] ] = $trip['label'] ? $trip['label'] : $trip['date'];
			}
		}
	}
	?>
	<style>
		.bhela-cs input[type=number] { width: 100%; text-align: right; }
		.bhela-cs input[type=text] { width: 100%; }
		.bhela-cs__head { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
		.bhela-cs__f { display: flex; flex-direction: column; gap: 4px; }
		.bhela-cs__f label { font-size: 11px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .04em; }
		.bhela-cs table.widefat td, .bhela-cs table.widefat th { padding: 6px 8px; }
		.bhela-cs__sub { text-align: right; font-weight: 700; white-space: nowrap; }
		.bhela-cs__sum { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
		.bhela-cs__sum > div { flex: 1; min-width: 160px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 14px; }
		.bhela-cs__sum span { display: block; color: #50575e; font-size: 12px; }
		.bhela-cs__sum b { font-size: 21px; }
		.bhela-cs__hint { color: #787c82; font-size: 12px; margin: 6px 0 0; }
		.bhela-cs__warn { background: #fcf9e8; border-left: 3px solid #b45309; padding: 8px 10px; font-size: 12px; margin: 8px 0 0; }
		.bhela-cs__locked { background: #edfaef; border-left: 3px solid #1a7f37; padding: 10px 12px; margin: 0 0 16px; font-weight: 600; }
	</style>

	<div class="bhela-cs">
		<?php if ( $locked ) : ?>
			<p class="bhela-cs__locked">🔒 <?php esc_html_e( 'This sheet is approved and locked. An administrator must unlock it before anything can change.', 'bhela-booking' ); ?></p>
		<?php endif; ?>

		<div class="bhela-cs__head">
			<div class="bhela-cs__f">
				<label for="bhela_cost_trip_date"><?php esc_html_e( 'Trip Date', 'bhela-booking' ); ?></label>
				<input type="date" id="bhela_cost_trip_date" name="bhela_cost_trip_date" value="<?php echo esc_attr( $date ); ?>"<?php echo $ro; ?>>
				<?php if ( $trip_dates && ! $locked ) : ?>
					<select id="bhela_cost_trip_pick" style="margin-top:4px">
						<option value=""><?php esc_html_e( '— pick from Trip Calendar —', 'bhela-booking' ); ?></option>
						<?php foreach ( $trip_dates as $d => $lab ) : ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $date === $d ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>
			<?php foreach ( bhela_bm_cost_header_fields() as $key => $f ) : ?>
				<div class="bhela-cs__f">
					<label for="bhela_cost_h_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
					<input type="<?php echo esc_attr( $f['type'] ); ?>" id="bhela_cost_h_<?php echo esc_attr( $key ); ?>"
						name="bhela_cost_header[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $header[ $key ] ?? '' ); ?>"<?php echo $ro; ?>>
				</div>
			<?php endforeach; ?>
		</div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:34px">SL</th>
					<th><?php esc_html_e( 'Particulars', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '1st Payment', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '2nd Payment', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '3rd Payment', 'bhela-booking' ); ?></th>
					<th style="width:110px;text-align:right"><?php esc_html_e( 'Sub-Total', 'bhela-booking' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Remark', 'bhela-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $lines as $i => $l ) : ?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td>
							<?php if ( $l['fixed'] ) : ?>
								<?php echo esc_html( $l['label'] ); ?>
								<input type="hidden" name="bhela_cost_lines[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $l['label'] ); ?>">
							<?php else : ?>
								<input type="text" name="bhela_cost_lines[<?php echo esc_attr( $i ); ?>][label]"
									value="<?php echo esc_attr( $l['label'] ); ?>" placeholder="<?php esc_attr_e( 'Other item…', 'bhela-booking' ); ?>"<?php echo $ro; ?>>
							<?php endif; ?>
						</td>
						<?php foreach ( array( 'p1', 'p2', 'p3' ) as $p ) : ?>
							<td><input type="number" min="0" step="1" class="bhela-cs__p"
								name="bhela_cost_lines[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $p ); ?>]"
								value="<?php echo esc_attr( $l[ $p ] ?: '' ); ?>"<?php echo $ro; ?>></td>
						<?php endforeach; ?>
						<td class="bhela-cs__sub" data-sub><?php echo esc_html( bhela_bm_money( $l['sub'] ) ); ?></td>
						<td><input type="text" name="bhela_cost_lines[<?php echo esc_attr( $i ); ?>][remark]"
							value="<?php echo esc_attr( $l['remark'] ); ?>"<?php echo $ro; ?>></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="bhela-cs__sum">
			<div>
				<span><?php esc_html_e( 'Total Cost for This Trip', 'bhela-booking' ); ?></span>
				<b id="bhela-cs-total"><?php echo esc_html( bhela_bm_money( $total ) ); ?></b>
			</div>
			<div>
				<span><?php esc_html_e( 'Total Earnings from This Trip', 'bhela-booking' ); ?></span>
				<input type="number" step="1" id="bhela-cs-earn" name="bhela_cost_earnings" value="<?php echo esc_attr( $earnings ); ?>" style="font-size:19px;font-weight:700"<?php echo $ro; ?>>
				<p class="bhela-cs__hint" id="bhela-cs-hint">
					<?php
					if ( ! $date ) {
						esc_html_e( 'Pick a trip date to pull its bookings.', 'bhela-booking' );
					} else {
						printf(
							/* translators: 1: bookings count, 2: total, 3: collected, 4: outstanding */
							esc_html__( '%1$d booking(s) on this date · invoiced %2$s · collected %3$s · due %4$s', 'bhela-booking' ),
							(int) $book['bookings'],
							esc_html( bhela_bm_money( $book['total'] ) ),
							esc_html( bhela_bm_money( $book['paid'] ) ),
							esc_html( bhela_bm_money( $book['due'] ) )
						);
					}
					?>
				</p>
				<p class="bhela-cs__warn" id="bhela-cs-warn"<?php echo ( $locked || $earnings === (int) $book['total'] ) ? ' hidden' : ''; ?>>
					<?php esc_html_e( 'This differs from the booking total.', 'bhela-booking' ); ?>
					<a href="#" id="bhela-cs-reset" data-v="<?php echo esc_attr( $book['total'] ); ?>"><?php esc_html_e( 'Use booking figure', 'bhela-booking' ); ?></a>
				</p>
			</div>
			<div>
				<span><?php esc_html_e( 'Total Profit from This Trip', 'bhela-booking' ); ?></span>
				<b id="bhela-cs-profit" style="color:<?php echo $profit < 0 ? '#b32d2e' : '#1a7f37'; ?>"><?php echo esc_html( bhela_bm_money( $profit ) ); ?></b>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var wrap = document.querySelector('.bhela-cs');
		if (!wrap) return;

		var dateEl = document.getElementById('bhela_cost_trip_date');
		var pick   = document.getElementById('bhela_cost_trip_pick');
		var hint   = document.getElementById('bhela-cs-hint');
		var warn   = document.getElementById('bhela-cs-warn');
		var reset  = document.getElementById('bhela-cs-reset');
		var earnEl = document.getElementById('bhela-cs-earn');
		var ajax   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var lookupNonce = <?php echo wp_json_encode( wp_create_nonce( 'bhela_bm_cost_lookup' ) ); ?>;
		var locked = <?php echo $locked ? 'true' : 'false'; ?>;

		/* Pull everything the date implies — booking money, guest count, trip
		   dates — the moment it changes. Without this the figures only appeared
		   after a save, which read as "it doesn't fetch anything". */
		function lookup(date) {
			if (locked) return;
			var url = ajax + '?action=bhela_bm_cost_lookup&_wpnonce=' +
				encodeURIComponent(lookupNonce) + '&date=' + encodeURIComponent(date || '');
			fetch(url, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) return;
					var d = res.data;
					if (hint) hint.textContent = d.hint;
					if (earnEl) earnEl.value = d.money.total;
					if (reset) reset.dataset.v = d.money.total;
					if (warn) warn.hidden = true;

					var guests = document.getElementById('bhela_cost_h_total_guest');
					if (guests) guests.value = d.guests || '';

					// Descriptive fields are only filled when still blank, so a
					// value typed by hand is never overwritten by a date change.
					[['bhela_cost_h_duration', d.duration],
					 ['bhela_cost_h_check_in', d.check_in],
					 ['bhela_cost_h_check_out', d.check_out]].forEach(function (pair) {
						var el = document.getElementById(pair[0]);
						if (el && !el.value && pair[1]) el.value = pair[1];
					});

					recalc();
				})
				.catch(function () {
					if (hint) hint.textContent = <?php echo wp_json_encode( __( 'Could not read the bookings for that date. Save and reload to try again.', 'bhela-booking' ) ); ?>;
				});
		}

		if (pick) {
			pick.addEventListener('change', function () {
				if (!pick.value) return;
				dateEl.value = pick.value;
				lookup(pick.value);
			});
		}
		if (dateEl) {
			dateEl.addEventListener('change', function () { lookup(dateEl.value); });
		}

		var money = function (n) {
			return '৳' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		};

		// Live preview only — every figure is summed again server-side on save,
		// so a tampered or stale DOM value can never reach the database.
		function recalc() {
			var total = 0;
			wrap.querySelectorAll('tbody tr').forEach(function (tr) {
				var sub = 0;
				tr.querySelectorAll('.bhela-cs__p').forEach(function (inp) {
					sub += parseInt(inp.value, 10) || 0;
				});
				var cell = tr.querySelector('[data-sub]');
				if (cell) cell.textContent = money(sub);
				total += sub;
			});
			document.getElementById('bhela-cs-total').textContent = money(total);
			var earn = parseInt((earnEl || {}).value, 10) || 0;
			var pEl = document.getElementById('bhela-cs-profit');
			if (pEl) {
				pEl.textContent = money(earn - total);
				pEl.style.color = (earn - total) < 0 ? '#b32d2e' : '#1a7f37';
			}
			// Surface the mismatch live, not only on the next page load.
			if (warn && reset && !locked) {
				warn.hidden = earn === (parseInt(reset.dataset.v, 10) || 0);
			}
		}

		wrap.addEventListener('input', function (e) {
			if (e.target.classList.contains('bhela-cs__p') || e.target.id === 'bhela-cs-earn') recalc();
		});

		if (reset) {
			reset.addEventListener('click', function (e) {
				e.preventDefault();
				if (earnEl) earnEl.value = reset.dataset.v;
				recalc();
			});
		}

		// Deliberately no fetch on load: the server already rendered the stored
		// figures, and re-deriving here would overwrite an earnings value the
		// preparer entered by hand for outside income.
	})();
	</script>
	<?php
}

/* =========================================================
 * SAVE
 * ========================================================= */

function bhela_bm_cost_save( $post_id, $post ) {
	if ( 'bhela_cost' !== $post->post_type ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['bhela_bm_cost_nonce'] ) || ! wp_verify_nonce( $_POST['bhela_bm_cost_nonce'], 'bhela_bm_cost_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// The lock is enforced here, not just in the UI: disabled inputs are a
	// display hint, and a crafted POST would otherwise walk straight past them.
	if ( bhela_bm_cost_is_locked( $post_id ) ) {
		return;
	}

	$date = bhela_bm_cost_date( wp_unslash( $_POST['bhela_cost_trip_date'] ?? '' ) );
	update_post_meta( $post_id, '_bhela_cost_trip_date', $date );

	$header = array();
	$posted = isset( $_POST['bhela_cost_header'] ) && is_array( $_POST['bhela_cost_header'] ) ? wp_unslash( $_POST['bhela_cost_header'] ) : array();
	foreach ( bhela_bm_cost_header_fields() as $key => $f ) {
		$val = $posted[ $key ] ?? '';
		$header[ $key ] = 'number' === $f['type'] ? (string) max( 0, (int) $val ) : sanitize_text_field( $val );
	}
	update_post_meta( $post_id, '_bhela_cost_header', wp_json_encode( $header, JSON_UNESCAPED_UNICODE ) );

	$fixed  = bhela_bm_cost_items();
	$rows   = isset( $_POST['bhela_cost_lines'] ) && is_array( $_POST['bhela_cost_lines'] ) ? wp_unslash( $_POST['bhela_cost_lines'] ) : array();
	$lines  = array();
	$count  = count( $fixed ) + bhela_bm_cost_extra_rows();
	$total  = 0;
	for ( $i = 0; $i < $count; $i++ ) {
		$r  = is_array( $rows[ $i ] ?? null ) ? $rows[ $i ] : array();
		$p1 = max( 0, (int) ( $r['p1'] ?? 0 ) );
		$p2 = max( 0, (int) ( $r['p2'] ?? 0 ) );
		$p3 = max( 0, (int) ( $r['p3'] ?? 0 ) );
		$lines[] = array(
			'label'  => $i < count( $fixed ) ? $fixed[ $i ] : sanitize_text_field( $r['label'] ?? '' ),
			'p1'     => $p1,
			'p2'     => $p2,
			'p3'     => $p3,
			'remark' => sanitize_text_field( $r['remark'] ?? '' ),
		);
		$total += $p1 + $p2 + $p3;
	}
	update_post_meta( $post_id, '_bhela_cost_lines', wp_json_encode( $lines, JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $post_id, '_bhela_cost_total', $total );

	$book = bhela_bm_cost_booking_earnings( $date );
	update_post_meta( $post_id, '_bhela_cost_earnings_auto', $book['total'] );
	$earn = isset( $_POST['bhela_cost_earnings'] ) && '' !== $_POST['bhela_cost_earnings']
		? max( 0, (int) $_POST['bhela_cost_earnings'] )
		: $book['total'];
	update_post_meta( $post_id, '_bhela_cost_earnings', $earn );

	if ( '' === get_post_meta( $post_id, '_bhela_cost_status', true ) ) {
		update_post_meta( $post_id, '_bhela_cost_status', 'draft' );
	}

	// An untitled sheet is unusable in a list of thirty, so name it after the trip.
	if ( $date && ( '' === $post->post_title || __( 'Auto Draft' ) === $post->post_title ) ) {
		remove_action( 'save_post', 'bhela_bm_cost_save', 10 );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => mysql2date( 'j M Y', $date ) . ' — Trip Cost' ) );
		add_action( 'save_post', 'bhela_bm_cost_save', 10, 2 );
	}
}
add_action( 'save_post', 'bhela_bm_cost_save', 10, 2 );

/* =========================================================
 * WORKFLOW TRANSITIONS
 * ========================================================= */

/**
 * Which capability each transition needs, and what state it must start from.
 *
 * Keeping this as data rather than a chain of ifs means a state can never be
 * reached by a route nobody wrote down — an unknown $do simply has no entry.
 */
function bhela_bm_cost_transitions() {
	return array(
		'submit'  => array( 'from' => array( 'draft' ), 'to' => 'prepared', 'cap' => 'bhela_cost_prepare', 'log' => 'submitted for check' ),
		'check'   => array( 'from' => array( 'prepared' ), 'to' => 'checked', 'cap' => 'bhela_cost_check', 'log' => 'marked as checked' ),
		'approve' => array( 'from' => array( 'checked' ), 'to' => 'approved', 'cap' => 'bhela_cost_approve', 'log' => 'approved' ),
		'return'  => array( 'from' => array( 'prepared', 'checked' ), 'to' => 'draft', 'cap' => 'bhela_cost_check', 'log' => 'returned to preparer' ),
		'unlock'  => array( 'from' => array( 'approved' ), 'to' => 'prepared', 'cap' => 'bhela_cost_approve', 'log' => 'unlocked for editing' ),
	);
}

function bhela_bm_cost_transition() {
	$sheet_id = (int) ( $_GET['sheet'] ?? 0 );
	$do       = sanitize_key( $_GET['do'] ?? '' );
	check_admin_referer( 'bhela_bm_cost_transition_' . $sheet_id );

	$map = bhela_bm_cost_transitions();
	$t   = $map[ $do ] ?? null;
	$post = get_post( $sheet_id );

	if ( ! $t || ! $post || 'bhela_cost' !== $post->post_type ) {
		wp_die( esc_html__( 'Unknown cost sheet action.', 'bhela-booking' ), 400 );
	}
	if ( ! current_user_can( $t['cap'] ) || ! current_user_can( 'edit_post', $sheet_id ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'bhela-booking' ), 403 );
	}
	$status = bhela_bm_cost_status( $sheet_id );
	if ( ! in_array( $status, $t['from'], true ) ) {
		wp_die( esc_html__( 'This sheet is no longer in a state where that action applies.', 'bhela-booking' ), 409 );
	}

	update_post_meta( $sheet_id, '_bhela_cost_status', $t['to'] );

	$user = wp_get_current_user();
	$now  = current_time( 'mysql' );

	if ( 'submit' === $do ) {
		update_post_meta( $sheet_id, '_bhela_cost_prepared_by', $user->ID );
		update_post_meta( $sheet_id, '_bhela_cost_prepared_at', $now );
		delete_post_meta( $sheet_id, '_bhela_cost_note' );
	} elseif ( 'check' === $do ) {
		update_post_meta( $sheet_id, '_bhela_cost_checked_by', $user->ID );
		update_post_meta( $sheet_id, '_bhela_cost_checked_at', $now );
	} elseif ( 'approve' === $do ) {
		update_post_meta( $sheet_id, '_bhela_cost_approved_by', $user->ID );
		update_post_meta( $sheet_id, '_bhela_cost_approved_at', $now );
	} elseif ( 'return' === $do ) {
		// A returned sheet has not been checked or approved any more — clearing
		// the stamps stops a stale signature riding along on the next round.
		foreach ( array( 'checked', 'approved' ) as $step ) {
			delete_post_meta( $sheet_id, '_bhela_cost_' . $step . '_by' );
			delete_post_meta( $sheet_id, '_bhela_cost_' . $step . '_at' );
		}
		update_post_meta( $sheet_id, '_bhela_cost_note', sprintf(
			/* translators: 1: user name, 2: date */
			__( 'Returned by %1$s on %2$s — figures need another look.', 'bhela-booking' ),
			$user->display_name,
			mysql2date( 'j M Y, g:i a', $now )
		) );
	} elseif ( 'unlock' === $do ) {
		foreach ( array( 'checked', 'approved' ) as $step ) {
			delete_post_meta( $sheet_id, '_bhela_cost_' . $step . '_by' );
			delete_post_meta( $sheet_id, '_bhela_cost_' . $step . '_at' );
		}
		update_post_meta( $sheet_id, '_bhela_cost_note', sprintf(
			/* translators: 1: user name, 2: date */
			__( 'Unlocked by %1$s on %2$s — must be checked and approved again.', 'bhela-booking' ),
			$user->display_name,
			mysql2date( 'j M Y, g:i a', $now )
		) );
	}

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'cost', sprintf(
			'Cost sheet %s — %s by %s',
			get_the_title( $sheet_id ),
			$t['log'],
			$user->display_name
		) );
	}

	wp_safe_redirect( get_edit_post_link( $sheet_id, 'raw' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_cost_transition', 'bhela_bm_cost_transition' );

/* =========================================================
 * PRINT VIEW
 * ========================================================= */

/**
 * A standalone printable sheet, opened from the edit screen.
 *
 * Rendered on admin_init before wp-admin draws anything, so the page is the
 * sheet and nothing else — no CSS fight with the admin chrome.
 */
function bhela_bm_cost_print() {
	if ( empty( $_GET['bhela_print'] ) || ! is_admin() ) {
		return;
	}
	$post_id = (int) ( $_GET['post'] ?? 0 );
	$post    = get_post( $post_id );
	if ( ! $post || 'bhela_cost' !== $post->post_type ) {
		return;
	}
	if ( ! current_user_can( 'read_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to view this sheet.', 'bhela-booking' ), 403 );
	}

	$s      = bhela_bm_get_settings();
	$date   = get_post_meta( $post_id, '_bhela_cost_trip_date', true );
	$header = json_decode( (string) get_post_meta( $post_id, '_bhela_cost_header', true ), true );
	$header = is_array( $header ) ? $header : array();
	$lines  = bhela_bm_cost_lines( $post_id );
	$total  = bhela_bm_cost_total( $lines );
	$earn   = (int) get_post_meta( $post_id, '_bhela_cost_earnings', true );
	$status = bhela_bm_cost_status( $post_id );

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php echo esc_html( get_the_title( $post_id ) ); ?></title>
	<style>
		body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; margin: 24px; font-size: 12px; }
		h1 { font-size: 18px; margin: 0 0 2px; }
		.sub { color: #555; margin-bottom: 16px; }
		table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
		th, td { border: 1px solid #999; padding: 5px 7px; text-align: left; }
		th { background: #f0f0f0; }
		td.n { text-align: right; white-space: nowrap; }
		.head { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
		.head div { border: 1px solid #ccc; padding: 5px 7px; }
		.head span { display: block; font-size: 10px; color: #666; text-transform: uppercase; }
		.sum { display: flex; gap: 10px; margin: 16px 0 26px; }
		.sum div { flex: 1; border: 1px solid #999; padding: 8px 10px; }
		.sum b { font-size: 16px; display: block; }
		.sign { display: flex; gap: 24px; margin-top: 34px; }
		.sign div { flex: 1; border-top: 1px solid #333; padding-top: 6px; font-size: 11px; }
		@media print { body { margin: 0; } .noprint { display: none; } }
	</style>
</head>
<body onload="window.print()">
	<button class="noprint" onclick="window.print()" style="float:right">Print</button>
	<h1><?php echo esc_html( $s['business_name'] ); ?> — Trip-wise Cost Sheet</h1>
	<div class="sub">
		<?php echo $date ? esc_html( mysql2date( 'j F Y', $date ) ) : '—'; ?>
		· <?php echo esc_html( bhela_bm_cost_statuses()[ $status ]['label'] ); ?>
	</div>

	<div class="head">
		<div><span>Trip Date</span><?php echo $date ? esc_html( mysql2date( 'j M Y', $date ) ) : '—'; ?></div>
		<?php foreach ( bhela_bm_cost_header_fields() as $key => $f ) : ?>
			<div><span><?php echo esc_html( $f['label'] ); ?></span><?php echo esc_html( $header[ $key ] ?? '—' ); ?></div>
		<?php endforeach; ?>
	</div>

	<table>
		<tr><th style="width:28px">SL</th><th>Particulars</th><th class="n">1st</th><th class="n">2nd</th><th class="n">3rd</th><th class="n">Sub-Total</th><th>Remark</th></tr>
		<?php foreach ( $lines as $i => $l ) : ?>
			<?php if ( ! $l['fixed'] && ! $l['label'] && ! $l['sub'] ) { continue; } ?>
			<tr>
				<td><?php echo esc_html( $i + 1 ); ?></td>
				<td><?php echo esc_html( $l['label'] ); ?></td>
				<td class="n"><?php echo $l['p1'] ? esc_html( number_format( $l['p1'] ) ) : ''; ?></td>
				<td class="n"><?php echo $l['p2'] ? esc_html( number_format( $l['p2'] ) ) : ''; ?></td>
				<td class="n"><?php echo $l['p3'] ? esc_html( number_format( $l['p3'] ) ) : ''; ?></td>
				<td class="n"><strong><?php echo esc_html( number_format( $l['sub'] ) ); ?></strong></td>
				<td><?php echo esc_html( $l['remark'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		<tr><td colspan="5"><strong>Total Cost for this Trip</strong></td><td class="n"><strong><?php echo esc_html( number_format( $total ) ); ?></strong></td><td></td></tr>
	</table>

	<div class="sum">
		<div><span>Total Earnings from This Trip</span><b><?php echo esc_html( bhela_bm_money( $earn ) ); ?></b></div>
		<div><span>Total Cost for This Trip</span><b><?php echo esc_html( bhela_bm_money( $total ) ); ?></b></div>
		<div><span>Total Profit from This Trip</span><b><?php echo esc_html( bhela_bm_money( $earn - $total ) ); ?></b></div>
	</div>

	<div class="sign">
		<?php foreach ( array( 'prepared' => 'Prepared by', 'checked' => 'Checked by', 'approved' => 'Approved by' ) as $key => $label ) :
			$by = (int) get_post_meta( $post_id, '_bhela_cost_' . $key . '_by', true );
			$at = get_post_meta( $post_id, '_bhela_cost_' . $key . '_at', true );
			$u  = $by ? get_userdata( $by ) : null;
			?>
			<div>
				<strong><?php echo esc_html( $label ); ?>:</strong>
				<?php echo $u ? esc_html( $u->display_name ) : '—'; ?>
				<?php if ( $at ) : ?><br><span style="color:#666"><?php echo esc_html( mysql2date( 'j M Y, g:i a', $at ) ); ?></span><?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</body>
</html>
	<?php
	exit;
}
add_action( 'admin_init', 'bhela_bm_cost_print' );
