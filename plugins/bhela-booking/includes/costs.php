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
		// Registered under Bookings and moved to Accounts on `admin_menu` by
		// bhela_bm_menu_move_cpts(). It cannot be registered under Accounts
		// directly: `init` runs before the current user resolves, so this value can
		// never depend on who is asking, and the Accounts menu only exists for
		// someone who can use it.
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

/*
 * There was a standalone Cost Sheets menu here, for a preparer with no Bookings
 * to nest under. The Accounts menu in includes/menu.php now serves everyone.
 */

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

/**
 * The expense heads as shipped, in spreadsheet order.
 *
 * Slug => label. The slug is what a sheet actually stores, so it must never
 * change once released: renaming a head is a label edit, not a key change.
 *
 * @return array
 */
function bhela_bm_cost_head_defaults() {
	return array(
		'engine_fuel'    => 'Engine Fuel (Diesel)',
		'electricity'    => 'Electricity Bill',
		'groceries'      => 'Groceries (Rice, Spices Etc)',
		'meat'           => 'Meat (Duck/Chicken/Beef)',
		'fish'           => 'Fish',
		'kitchen_market' => 'Kitchen Market (Vegetables)',
		'gas'            => 'Gas Bill',
		'staff_convency' => 'Staff Convency',
		'jetty_charge'   => 'Jetty Charge (Docking)',
		'water'          => 'Water',
		'fruits'         => 'Fruits',
		'dry_fish'       => 'Dry Fish',
		'local_bill'     => 'Transgender/Local Bill',
		'laundry'        => 'Laundry',
		'ice'            => 'Ice',
		'movement'       => 'Movement Bill',
		'guest_see_off'  => 'Guest see off cost',
		'repair_minor'   => 'Repair Bill (Minor)',
		'b2b_partner'    => 'B2B Partner',
		'staff_bill'     => 'Staff Bill',
		'others'         => 'Others (Any Bill & Purchase)',
	);
}

/**
 * The heads in force — the owner's list if they have edited it, else the
 * shipped one.
 *
 * Stored as slug => array{ label, retired }. Retired heads stay in the list so
 * an approved sheet that used one still renders its label; they are simply not
 * offered on new sheets. Deleting outright would silently blank a figure on a
 * month the owner has already closed.
 *
 * @param bool $include_retired Include heads no longer offered on new sheets.
 * @return array slug => label
 */
function bhela_bm_cost_heads( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_cost_heads', null );
	if ( ! is_array( $saved ) || ! $saved ) {
		return bhela_bm_cost_head_defaults();
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $slug ] = $label;
	}
	return $out ? $out : bhela_bm_cost_head_defaults();
}

/**
 * Head slugs that appear on at least one saved sheet.
 *
 * Drives the "in use" column, so the owner can see which heads carry history
 * before retiring one.
 *
 * @return string[]
 */
function bhela_bm_cost_heads_in_use() {
	$ids  = get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$used = array();
	foreach ( $ids as $id ) {
		foreach ( bhela_bm_cost_stored_lines( $id ) as $key => $row ) {
			if ( (int) ( $row['p1'] ?? 0 ) + (int) ( $row['p2'] ?? 0 ) + (int) ( $row['p3'] ?? 0 ) > 0 ) {
				$used[ $key ] = true;
			}
		}
	}
	return array_keys( $used );
}

/**
 * Save the owner's head list.
 *
 * A slug is minted once from the first label and then frozen — renaming must
 * not change the key, or every sheet that used the head would lose its figure.
 *
 * @param array $posted Raw `cost_heads` input.
 */
function bhela_bm_save_cost_heads( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;                       // a blank row is a deletion
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( sanitize_title( $label ) );
			$slug = $slug ? $slug : 'head';
		}
		// Never collide with an existing key: that would merge two heads' money.
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;

		$out[ $slug ] = array(
			'label'   => $label,
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_option( 'bhela_bm_cost_heads', $out );
	}
}

/** Drop the override so the shipped head list applies again. */
function bhela_bm_reset_cost_heads() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_reset_cost_heads' );
	delete_option( 'bhela_bm_cost_heads' );
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'settings', 'Trip cost heads reset to the shipped list.' );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : bhela_bm_admin_url( 'bhela-bm-settings' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_reset_cost_heads', 'bhela_bm_reset_cost_heads' );

/**
 * Back-compat shim: the old flat list of labels.
 *
 * @deprecated Use bhela_bm_cost_heads(). Kept so anything still calling this
 *             keeps working, including the first version of the test harness.
 * @return string[]
 */
function bhela_bm_cost_items() {
	return array_values( bhela_bm_cost_heads() );
}

/**
 * Validate a travel date, in the one format the meta is stored in.
 *
 * Kept as a named function because the cost sheet's callers read better for it, but
 * there is no second implementation any more: it forwards to the core validator, so
 * every screen accepts exactly the same input. The duplicate that used to live here
 * behind a function_exists() guard was working around bhela_bm_report_date() being
 * parked in reports.php; it is in core now.
 *
 * @param mixed $value Raw request value.
 * @return string Valid Y-m-d date, or ''.
 */
function bhela_bm_cost_date( $value ) {
	return bhela_bm_report_date( $value );
}

/** How many spare, preparer-labelled rows to keep available. A minimum, not a cap. */
function bhela_bm_cost_extra_rows() {
	return 5;
}

/**
 * Ceiling on one-off rows per sheet, so a crafted POST cannot grow unbounded.
 *
 * 30, not 15: July's 15–16 trip already used fourteen one-off rows in a single
 * sheet (chair, motor, fans, burnish mistri, silencer screw…), which a cap of
 * 15 would have sat one row away from refusing.
 */
function bhela_bm_cost_max_custom_rows() {
	return 30;
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

/**
 * Workflow states, in order, with the pill each one renders as.
 *
 * The weight tracks the workflow rather than repeating the colour: the three
 * states still in motion are soft, and only Approved — the one that locks the
 * sheet — is filled.
 */
function bhela_bm_cost_statuses() {
	return array(
		'draft'    => array( 'label' => 'Draft', 'tone' => 'neutral', 'solid' => false ),
		'prepared' => array( 'label' => 'Prepared', 'tone' => 'attention', 'solid' => false ),
		'checked'  => array( 'label' => 'Checked', 'tone' => 'progress', 'solid' => false ),
		'approved' => array( 'label' => 'Approved', 'tone' => 'good', 'solid' => true ),
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
 * Sheets with no trip date.
 *
 * Every report — the Monthly Statement, the Yearly Report, the expense mix —
 * selects on `_bhela_cost_trip_date`. A sheet without one therefore belongs to
 * no month and no year: its money is recorded and then never counted anywhere,
 * silently. This is how the screens find them so they can say so.
 *
 * @return array List of array{id,title,status,total}.
 */
function bhela_bm_cost_undated() {
	$ids = get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		// Two ways to have no date: the key was never written, or it was
		// written empty by a save that left the field blank.
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_bhela_cost_trip_date', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_bhela_cost_trip_date', 'value' => '', 'compare' => '=' ),
		),
	) );

	$out = array();
	foreach ( $ids as $id ) {
		$out[] = array(
			'id'     => $id,
			'title'  => get_the_title( $id ),
			'status' => bhela_bm_cost_status( $id ),
			'total'  => (int) get_post_meta( $id, '_bhela_cost_total', true ),
		);
	}
	return $out;
}

/**
 * Has this sheet's earnings figure gone stale since it was written?
 *
 * Earnings are captured when the sheet is saved. If a booking on that date is
 * later cancelled, refunded or added, the sheet keeps the old number and the
 * statement keeps reporting it — a trip that lost a ৳26,000 booking still
 * shows the money.
 *
 * Deliberately reports rather than corrects. An approved sheet is signed off,
 * and silently rewriting a figure three people put their name to would be
 * worse than showing it is out of date; the fix is to unlock, adjust and
 * re-approve, which the workflow already supports.
 *
 * A sheet whose earnings were typed by hand is left alone — a manual figure is
 * a decision, not a cached value. That is what `_bhela_cost_earnings_auto`
 * distinguishes: it is what the bookings said at the moment of saving.
 *
 * @param int $post_id Cost sheet.
 * @return array{stale:bool,stored:int,live:int,diff:int}
 */
function bhela_bm_cost_earnings_drift( $post_id ) {
	$stored = (int) get_post_meta( $post_id, '_bhela_cost_earnings', true );
	$auto   = get_post_meta( $post_id, '_bhela_cost_earnings_auto', true );
	$out    = array( 'stale' => false, 'stored' => $stored, 'live' => $stored, 'diff' => 0 );

	// No auto figure recorded (a sheet saved before this existed), or the
	// stored value was overridden by hand — either way, nothing to compare.
	if ( '' === $auto || (int) $auto !== $stored ) {
		return $out;
	}
	$date = (string) get_post_meta( $post_id, '_bhela_cost_trip_date', true );
	if ( '' === $date ) {
		return $out;
	}

	$live         = (int) bhela_bm_cost_booking_earnings( $date )['total'];
	$out['live']  = $live;
	$out['diff']  = $live - $stored;
	$out['stale'] = $live !== $stored;
	return $out;
}

/**
 * Has the B2B commission moved since this sheet last filled it in?
 *
 * The same contract as bhela_bm_cost_earnings_drift() above, and deliberately the
 * same shape: it REPORTS, it does not correct. An approved sheet has three names on
 * it, and rewriting a figure they signed off would be worse than showing it is out
 * of date — the fix is to unlock, adjust and re-approve, which the workflow already
 * supports.
 *
 * A line typed over by hand is left alone entirely: `_bhela_cost_b2b_auto` is what
 * the bookings said when the sheet was saved, so a stored value that no longer
 * matches it was somebody's decision, not a stale cache.
 *
 * @param int $post_id Cost sheet.
 * @return array{stale:bool,stored:int,live:int,diff:int}
 */
function bhela_bm_cost_b2b_drift( $post_id ) {
	$lines  = bhela_bm_cost_stored_lines( $post_id );
	$stored = (int) ( $lines['b2b_partner']['p1'] ?? 0 );
	$auto   = get_post_meta( $post_id, '_bhela_cost_b2b_auto', true );
	$out    = array( 'stale' => false, 'stored' => $stored, 'live' => $stored, 'diff' => 0 );

	if ( '' === $auto || (int) $auto !== $stored ) {
		return $out;                        // typed by hand, or never auto-filled
	}
	$date = (string) get_post_meta( $post_id, '_bhela_cost_trip_date', true );
	if ( '' === $date || ! function_exists( 'bhela_bm_commission_rows' ) ) {
		return $out;
	}
	$live         = (int) bhela_bm_commission_rows( $date, $date )['total'];
	$out['live']  = $live;
	$out['diff']  = $live - $stored;
	$out['stale'] = $live !== $stored;
	return $out;
}

/**
 * May this sheet be approved?
 *
 * Approval is the point of no return: the sheet locks, and from then on it is
 * what the statement and the yearly report read. Both select on the trip date,
 * so approving an undated sheet would file a final, uneditable record into no
 * month at all.
 *
 * A predicate rather than an inline check, because three places need the same
 * answer — the transition that enforces it, the sidebar that greys the button,
 * and the test that proves it.
 *
 * @param int $post_id Cost sheet.
 * @return bool
 */
function bhela_bm_cost_can_approve( $post_id ) {
	return '' !== trim( (string) get_post_meta( $post_id, '_bhela_cost_trip_date', true ) );
}

/**
 * Read the stored lines, converting anything written in the old shape.
 *
 * Sheets used to store a positional array whose labels came from a hardcoded
 * list. Once the owner can rename, reorder or retire a head, position stops
 * meaning anything — so rows are now keyed by head slug. Old data is mapped
 * index → slug through the shipped list on read; the next save writes it back
 * in the new shape, so there is no migration script and nothing to run.
 *
 * @param int $post_id Cost sheet ID.
 * @return array slug|custom key => array{ label, p1, p2, p3, remark }
 */
function bhela_bm_cost_stored_lines( $post_id ) {
	$saved = $post_id ? json_decode( (string) get_post_meta( $post_id, '_bhela_cost_lines', true ), true ) : array();
	if ( ! is_array( $saved ) || ! $saved ) {
		return array();
	}

	// A list (0,1,2…) is the old positional format; a map is already keyed.
	$is_positional = array_keys( $saved ) === range( 0, count( $saved ) - 1 );
	if ( ! $is_positional ) {
		return $saved;
	}

	$slugs = array_keys( bhela_bm_cost_head_defaults() );
	$out   = array();
	foreach ( $saved as $i => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		// Rows past the shipped heads were the free-text extras.
		$key         = $slugs[ $i ] ?? 'custom_' . $i;
		$out[ $key ] = array(
			'label'  => (string) ( $row['label'] ?? ( $slugs[ $i ] ?? '' ) ),
			'p1'     => (int) ( $row['p1'] ?? 0 ),
			'p2'     => (int) ( $row['p2'] ?? 0 ),
			'p3'     => (int) ( $row['p3'] ?? 0 ),
			'remark' => (string) ( $row['remark'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Every row to render for a sheet: the heads in force, plus whatever else this
 * particular sheet already holds, plus blanks to type into.
 *
 * A head retired after this sheet was approved still appears — with its saved
 * label — because the figure is real and the month is closed.
 *
 * @param int $post_id Cost sheet ID (0 for a brand-new sheet).
 * @return array<int, array{key:string,label:string,fixed:bool,p1:int,p2:int,p3:int,remark:string,sub:int}>
 */
function bhela_bm_cost_lines( $post_id = 0 ) {
	$stored = bhela_bm_cost_stored_lines( $post_id );
	$heads  = bhela_bm_cost_heads();
	$rows   = array();

	foreach ( $heads as $slug => $label ) {
		$row    = $stored[ $slug ] ?? array();
		$rows[] = bhela_bm_cost_row( $slug, $label, $row, true );
		unset( $stored[ $slug ] );
	}

	// Anything left is either a retired head or a one-off the preparer typed.
	$retired = bhela_bm_cost_heads( true );
	foreach ( $stored as $key => $row ) {
		$label  = (string) ( $row['label'] ?? '' );
		$fixed  = isset( $retired[ $key ] );
		$rows[] = bhela_bm_cost_row( $key, $fixed ? $retired[ $key ] : $label, $row, $fixed );
	}

	// Blank rows to type into, always a few spare. July's first real sheet used
	// four one-off rows (Spoon, Pencil Battary, Electric Materials, Cold
	// Drinks), so three was already one short.
	$blanks = 0;
	foreach ( $rows as $r ) {
		if ( ! $r['fixed'] && 0 === $r['sub'] && '' === $r['label'] ) {
			$blanks++;
		}
	}
	for ( $i = $blanks; $i < bhela_bm_cost_extra_rows(); $i++ ) {
		$rows[] = bhela_bm_cost_row( 'new_' . $i, '', array(), false );
	}

	return $rows;
}

/** Shape one render-ready row. */
function bhela_bm_cost_row( $key, $label, $row, $fixed ) {
	$p1 = (int) ( $row['p1'] ?? 0 );
	$p2 = (int) ( $row['p2'] ?? 0 );
	$p3 = (int) ( $row['p3'] ?? 0 );
	return array(
		'key'    => (string) $key,
		'label'  => (string) $label,
		'fixed'  => (bool) $fixed,
		'p1'     => $p1,
		'p2'     => $p2,
		'p3'     => $p3,
		'remark' => (string) ( $row['remark'] ?? '' ),
		'sub'    => $p1 + $p2 + $p3,
	);
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
			// An em dash reads as "nothing to see". A missing date is the one
			// thing that keeps the sheet out of every report, so it gets a pill.
			echo $d
				? esc_html( mysql2date( 'j M Y', $d ) )
				: bhela_bm_status_pill( __( 'No trip date', 'bhela-booking' ), 'attention' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
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
				'<span class="%s">%s</span>',
				esc_attr( $p < 0 ? 'bha-owed' : 'bha-settled' ),
				esc_html( bhela_bm_money( $p ) )
			);
			break;
		case 'cstatus':
			$def = bhela_bm_cost_statuses()[ bhela_bm_cost_status( $post_id ) ];
			echo bhela_bm_status_pill( $def['label'], $def['tone'], ! empty( $def['solid'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
			break;
		case 'signoff':
			foreach ( array( 'prepared' => 'Prepared', 'checked' => 'Checked', 'approved' => 'Approved' ) as $key => $label ) {
				$by = (int) get_post_meta( $post_id, '_bhela_cost_' . $key . '_by', true );
				if ( ! $by ) {
					continue;
				}
				$u = get_userdata( $by );
				printf(
					'<span class="bha-sub">%s: <strong>%s</strong></span>',
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

	// An undated sheet cannot be approved — see bhela_bm_cost_can_approve().
	// Say so here rather than only refusing after the click.
	$dated = bhela_bm_cost_can_approve( $post->ID );
	?>
	<?php if ( isset( $_GET['bhela_cost_msg'] ) && 'nodate' === $_GET['bhela_cost_msg'] ) : ?>
		<p class="bha-callout bha-callout--attention bha-callout--lead">
			<?php esc_html_e( 'Not approved — this sheet has no trip date, so it would belong to no month. Set the Trip Date above, save, then approve.', 'bhela-booking' ); ?>
		</p>
	<?php endif; ?>
	<p style="margin-top:0">
		<?php
		echo bhela_bm_status_pill( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
			$def['label'],
			$def['tone'],
			! empty( $def['solid'] )
		);
		?>
	</p>

	<div class="bha-panel" style="padding:0;border:0">
		<table>
			<?php foreach ( array( 'prepared' => 'Prepared by', 'checked' => 'Checked by', 'approved' => 'Approved by' ) as $key => $label ) :
				$by = (int) get_post_meta( $post->ID, '_bhela_cost_' . $key . '_by', true );
				$at = get_post_meta( $post->ID, '_bhela_cost_' . $key . '_at', true );
				$u  = $by ? get_userdata( $by ) : null;
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td class="bha-num">
						<?php if ( $u ) : ?>
							<strong><?php echo esc_html( $u->display_name ); ?></strong>
							<span class="bha-sub"><?php echo esc_html( mysql2date( 'j M, g:i a', $at ) ); ?></span>
						<?php else : ?>
							<span style="color:#a7aaad">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>

	<?php if ( $note ) : ?>
		<p class="bha-callout bha-callout--attention"><?php echo esc_html( $note ); ?></p>
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
		<?php if ( $dated ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( $action( 'approve' ) ); ?>"><?php esc_html_e( '✓ Approve', 'bhela-booking' ); ?></a></p>
		<?php else : ?>
			<p><button type="button" class="button button-primary" disabled><?php esc_html_e( '✓ Approve', 'bhela-booking' ); ?></button></p>
			<p class="bha-callout bha-callout--attention" style="margin:0 0 10px">
				<?php esc_html_e( 'Set a Trip Date first. Approving locks the sheet, and without a date it would count towards no month and no year.', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( $action( 'return' ) ); ?>"><?php esc_html_e( '↩ Return to preparer', 'bhela-booking' ); ?></a></p>
	<?php endif; ?>

	<?php if ( 'approved' === $status ) : ?>
		<p class="bha-callout bha-callout--good" style="margin:0 0 10px">🔒 <?php esc_html_e( 'Locked. Figures can no longer be edited.', 'bhela-booking' ); ?></p>
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
	<div class="bha-sheet" id="bhela-cs">
		<?php if ( $locked ) : ?>
			<p class="bha-callout bha-callout--good bha-callout--lead">🔒 <?php esc_html_e( 'This sheet is approved and locked. An administrator must unlock it before anything can change.', 'bhela-booking' ); ?></p>
		<?php endif; ?>

		<div class="bha-grid">
			<div class="bha-field bha-field--caps">
				<label for="bhela_cost_trip_date"><?php esc_html_e( 'Trip Date', 'bhela-booking' ); ?></label>
				<input type="date" id="bhela_cost_trip_date" name="bhela_cost_trip_date" value="<?php echo esc_attr( $date ); ?>"<?php echo $ro; ?>>
				<?php if ( $trip_dates && ! $locked ) : ?>
					<select id="bhela_cost_trip_pick">
						<option value=""><?php esc_html_e( '— pick from Trip Calendar —', 'bhela-booking' ); ?></option>
						<?php foreach ( $trip_dates as $d => $lab ) : ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $date === $d ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>
			<?php foreach ( bhela_bm_cost_header_fields() as $key => $f ) : ?>
				<div class="bha-field bha-field--caps">
					<label for="bhela_cost_h_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
					<input type="<?php echo esc_attr( $f['type'] ); ?>" id="bhela_cost_h_<?php echo esc_attr( $key ); ?>"
						name="bhela_cost_header[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $header[ $key ] ?? '' ); ?>"<?php echo $ro; ?>>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="bha-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:34px">SL</th>
					<th><?php esc_html_e( 'Particulars', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '1st Payment', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '2nd Payment', 'bhela-booking' ); ?></th>
					<th style="width:120px"><?php esc_html_e( '3rd Payment', 'bhela-booking' ); ?></th>
					<th class="bha-num" style="width:110px"><?php esc_html_e( 'Sub-Total', 'bhela-booking' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Remark', 'bhela-booking' ); ?></th>
				</tr>
			</thead>
			<tbody id="bhela-cs-rows">
				<?php foreach ( $lines as $i => $l ) : $k = $l['key']; ?>
					<tr>
						<td class="bha-sheet__sl"><?php echo esc_html( $i + 1 ); ?></td>
						<td>
							<?php if ( $l['fixed'] ) : ?>
								<?php echo esc_html( $l['label'] ); ?>
							<?php else : ?>
								<input type="text" name="bhela_cost_lines[<?php echo esc_attr( $k ); ?>][label]"
									value="<?php echo esc_attr( $l['label'] ); ?>" placeholder="<?php esc_attr_e( 'Other item…', 'bhela-booking' ); ?>"<?php echo $ro; ?>>
							<?php endif; ?>
						</td>
						<?php foreach ( array( 'p1', 'p2', 'p3' ) as $p ) : ?>
							<td><input type="number" min="0" step="1" data-amount
								name="bhela_cost_lines[<?php echo esc_attr( $k ); ?>][<?php echo esc_attr( $p ); ?>]"
								value="<?php echo esc_attr( $l[ $p ] ?: '' ); ?>"<?php echo $ro; ?>></td>
						<?php endforeach; ?>
						<td class="bha-num" data-sub><?php echo esc_html( bhela_bm_money( $l['sub'] ) ); ?></td>
						<td><input type="text" name="bhela_cost_lines[<?php echo esc_attr( $k ); ?>][remark]"
							value="<?php echo esc_attr( $l['remark'] ); ?>"<?php echo $ro; ?>></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<?php if ( ! $locked ) : ?>
			<p class="bha-sheet__add">
				<button type="button" class="button" id="bhela-cs-add">+ <?php esc_html_e( 'Add row', 'bhela-booking' ); ?></button>
				<span class="bha-note"><?php esc_html_e( 'For a one-off this trip. To change the standing list of heads, use Settings.', 'bhela-booking' ); ?></span>
			</p>
		<?php endif; ?>

		<div class="bha-cards" style="margin-top:18px">
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Total Cost for This Trip', 'bhela-booking' ); ?></span>
				<span class="bha-card__value" id="bhela-cs-total"><?php echo esc_html( bhela_bm_money( $total ) ); ?></span>
			</div>
			<div class="bha-card" style="flex-basis:280px">
				<span class="bha-card__label"><?php esc_html_e( 'Total Earnings from This Trip', 'bhela-booking' ); ?></span>
				<input type="number" step="1" class="bha-card__input" id="bhela-cs-earn" name="bhela_cost_earnings" value="<?php echo esc_attr( $earnings ); ?>"<?php echo $ro; ?>>
				<p class="bha-note" id="bhela-cs-hint">
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
				<p class="bha-callout bha-callout--attention" id="bhela-cs-warn"<?php echo ( $locked || $earnings === (int) $book['total'] ) ? ' hidden' : ''; ?>>
					<?php esc_html_e( 'This differs from the booking total.', 'bhela-booking' ); ?>
					<a href="#" id="bhela-cs-reset" data-v="<?php echo esc_attr( $book['total'] ); ?>"><?php esc_html_e( 'Use booking figure', 'bhela-booking' ); ?></a>
				</p>
			</div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Total Profit from This Trip', 'bhela-booking' ); ?></span>
				<span class="bha-card__value <?php echo $profit < 0 ? 'is-danger' : 'is-good'; ?>" id="bhela-cs-profit"><?php echo esc_html( bhela_bm_money( $profit ) ); ?></span>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var wrap = document.getElementById('bhela-cs');
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

		// Mirrors bhela_bm_money(): the sign goes before the symbol, so a trip
		// that lost money reads "-৳5,000" here and on the saved sheet alike.
		var money = function (n) {
			var v = Math.round(n);
			return (v < 0 ? '-' : '') + '৳' +
				Math.abs(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		};

		// Live preview only — every figure is summed again server-side on save,
		// so a tampered or stale DOM value can never reach the database.
		function recalc() {
			var total = 0;
			wrap.querySelectorAll('tbody tr').forEach(function (tr) {
				var sub = 0;
				tr.querySelectorAll('[data-amount]').forEach(function (inp) {
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
				// Tone classes, not a hex — so a loss reads the same red here as
				// it does in the list table and on the statement.
				pEl.classList.toggle('is-danger', (earn - total) < 0);
				pEl.classList.toggle('is-good', (earn - total) >= 0);
			}
			// Surface the mismatch live, not only on the next page load.
			if (warn && reset && !locked) {
				warn.hidden = earn === (parseInt(reset.dataset.v, 10) || 0);
			}
		}

		wrap.addEventListener('input', function (e) {
			if (e.target.hasAttribute('data-amount') || e.target.id === 'bhela-cs-earn') recalc();
		});

		if (reset) {
			reset.addEventListener('click', function (e) {
				e.preventDefault();
				if (earnEl) earnEl.value = reset.dataset.v;
				recalc();
			});
		}

		/* Add a one-off row. The key only has to be unique within this POST —
		   the server keeps it verbatim for anything it does not recognise as a
		   head, which is what keeps the amount attached to its label. */
		var addBtn = document.getElementById('bhela-cs-add');
		if (addBtn) {
			var maxCustom = <?php echo (int) bhela_bm_cost_max_custom_rows(); ?>;
			addBtn.addEventListener('click', function () {
				var body = document.getElementById('bhela-cs-rows');
				var custom = body.querySelectorAll('input[name$="[label]"]').length;
				if (custom >= maxCustom) {
					addBtn.disabled = true;
					return;
				}
				var key = 'custom_' + Date.now().toString(36);
				var tr = document.createElement('tr');
				tr.innerHTML =
					'<td class="bha-sheet__sl"></td>' +
					'<td><input type="text" name="bhela_cost_lines[' + key + '][label]" placeholder="<?php echo esc_js( __( 'Other item…', 'bhela-booking' ) ); ?>"></td>' +
					['p1', 'p2', 'p3'].map(function (p) {
						return '<td><input type="number" min="0" step="1" data-amount name="bhela_cost_lines[' + key + '][' + p + ']"></td>';
					}).join('') +
					'<td class="bha-num" data-sub>৳0</td>' +
					'<td><input type="text" name="bhela_cost_lines[' + key + '][remark]"></td>';
				body.appendChild(tr);
				renumber();
				tr.querySelector('input').focus();
			});
		}

		function renumber() {
			var n = 0;
			document.querySelectorAll('#bhela-cs-rows .bha-sheet__sl').forEach(function (td) {
				n += 1;
				td.textContent = n;
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

	// Rows arrive keyed by head slug (or a custom key for a one-off). A known
	// head always takes its label from the head list, so renaming a head can
	// never rewrite what a saved sheet says it spent money on.
	$heads  = bhela_bm_cost_heads( true );
	$rows   = isset( $_POST['bhela_cost_lines'] ) && is_array( $_POST['bhela_cost_lines'] ) ? wp_unslash( $_POST['bhela_cost_lines'] ) : array();
	$lines  = array();
	$total  = 0;
	$custom = 0;

	foreach ( $rows as $key => $r ) {
		$key = sanitize_key( $key );
		if ( '' === $key || ! is_array( $r ) ) {
			continue;
		}
		$known = isset( $heads[ $key ] );
		$label = $known ? $heads[ $key ] : sanitize_text_field( $r['label'] ?? '' );

		$p1 = max( 0, (int) ( $r['p1'] ?? 0 ) );
		$p2 = max( 0, (int) ( $r['p2'] ?? 0 ) );
		$p3 = max( 0, (int) ( $r['p3'] ?? 0 ) );
		$remark = sanitize_text_field( $r['remark'] ?? '' );

		// Store only rows that say something, so a re-save produces the same
		// record rather than accumulating zeros:
		//   • a known head with no money and no remark renders from the head
		//     list anyway, so there is nothing to keep;
		//   • an unlabelled one-off row is simply a blank the preparer skipped.
		// A zero with a remark survives — that is someone explaining the zero —
		// and so does a one-off that has been named but not yet priced.
		if ( 0 === $p1 + $p2 + $p3 && '' === $remark && ( $known || '' === $label ) ) {
			continue;
		}
		if ( ! $known ) {
			$custom++;
			if ( $custom > bhela_bm_cost_max_custom_rows() ) {
				continue;
			}
		}

		$lines[ $key ] = array(
			'label'  => $label,
			'p1'     => $p1,
			'p2'     => $p2,
			'p3'     => $p3,
			'remark' => $remark,
		);
		$total += $p1 + $p2 + $p3;
	}
	// JSON_FORCE_OBJECT: an all-numeric-key map would otherwise encode as a
	// list and be misread as the old positional format on the next load.
	update_post_meta( $post_id, '_bhela_cost_lines', wp_json_encode( $lines, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	update_post_meta( $post_id, '_bhela_cost_total', $total );

	// The B2B Partner line fills itself from the commissions on that date's
	// bookings, so the figure is entered once — on the booking — and cannot be
	// counted twice by also being typed here.
	//
	// Same contract as earnings below it: `_bhela_cost_b2b_auto` records what the
	// bookings said at save time, so bhela_bm_cost_b2b_drift() can tell a value it
	// filled in from one somebody typed over. A typed figure is a decision and is
	// left alone; a filled one is refreshed while the sheet is still editable.
	$b2b_live = function_exists( 'bhela_bm_commission_rows' )
		? (int) bhela_bm_commission_rows( $date, $date )['total']
		: 0;
	$b2b_was  = get_post_meta( $post_id, '_bhela_cost_b2b_auto', true );
	$b2b_line = (int) ( $lines['b2b_partner']['p1'] ?? 0 );
	// Untouched by hand if it still equals what we last filled in — or if the line
	// is empty on a sheet that has never carried one.
	if ( '' === $b2b_was || (int) $b2b_was === $b2b_line ) {
		if ( $b2b_live !== $b2b_line ) {
			if ( ! isset( $lines['b2b_partner'] ) || ! is_array( $lines['b2b_partner'] ) ) {
				$lines['b2b_partner'] = array( 'p1' => 0, 'p2' => 0, 'p3' => 0, 'remark' => '' );
			}
			$total -= $b2b_line;
			$lines['b2b_partner']['p1'] = $b2b_live;
			$total += $b2b_live;
			update_post_meta( $post_id, '_bhela_cost_lines', wp_json_encode( $lines, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
			update_post_meta( $post_id, '_bhela_cost_total', $total );
		}
		update_post_meta( $post_id, '_bhela_cost_b2b_auto', $b2b_live );
	}

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

	// Approval is the point of no return: the sheet locks, and from then on it
	// is what the statement and the yearly report read. Without a trip date it
	// belongs to no month, so approving it would file a final, uneditable sheet
	// into nowhere. Refuse, and send the preparer back to the field to fix.
	if ( 'approved' === $t['to'] && ! bhela_bm_cost_can_approve( $sheet_id ) ) {
		wp_safe_redirect( add_query_arg( 'bhela_cost_msg', 'nodate', get_edit_post_link( $sheet_id, 'raw' ) ) );
		exit;
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
	<link rel="stylesheet" href="<?php echo esc_url( BHELA_BM_URL . 'assets/admin.css?ver=' . BHELA_BM_VERSION ); ?>">
</head>
<body class="bhela-admin bha-doc" onload="window.print()">
	<button class="bha-noprint" onclick="window.print()" style="float:right">Print</button>
	<h1><?php echo esc_html( $s['business_name'] ); ?> — Trip-wise Cost Sheet</h1>
	<div class="bha-doc__sub">
		<?php echo $date ? esc_html( mysql2date( 'j F Y', $date ) ) : '—'; ?>
		· <?php echo esc_html( bhela_bm_cost_statuses()[ $status ]['label'] ); ?>
	</div>

	<div class="bha-doc__facts">
		<div><span>Trip Date</span><?php echo $date ? esc_html( mysql2date( 'j M Y', $date ) ) : '—'; ?></div>
		<?php foreach ( bhela_bm_cost_header_fields() as $key => $f ) : ?>
			<div><span><?php echo esc_html( $f['label'] ); ?></span><?php echo esc_html( $header[ $key ] ?? '—' ); ?></div>
		<?php endforeach; ?>
	</div>

	<table>
		<tr><th style="width:28px">SL</th><th>Particulars</th><th class="bha-num">1st</th><th class="bha-num">2nd</th><th class="bha-num">3rd</th><th class="bha-num">Sub-Total</th><th>Remark</th></tr>
		<?php foreach ( $lines as $i => $l ) : ?>
			<?php if ( ! $l['fixed'] && ! $l['label'] && ! $l['sub'] ) { continue; } ?>
			<tr>
				<td><?php echo esc_html( $i + 1 ); ?></td>
				<td><?php echo esc_html( $l['label'] ); ?></td>
				<td class="bha-num"><?php echo $l['p1'] ? esc_html( bhela_bm_money( $l['p1'] ) ) : ''; ?></td>
				<td class="bha-num"><?php echo $l['p2'] ? esc_html( bhela_bm_money( $l['p2'] ) ) : ''; ?></td>
				<td class="bha-num"><?php echo $l['p3'] ? esc_html( bhela_bm_money( $l['p3'] ) ) : ''; ?></td>
				<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $l['sub'] ) ); ?></strong></td>
				<td><?php echo esc_html( $l['remark'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		<tr><td colspan="5"><strong>Total Cost for this Trip</strong></td><td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $total ) ); ?></strong></td><td></td></tr>
	</table>

	<div class="bha-doc__sum">
		<div><span>Total Earnings from This Trip</span><b><?php echo esc_html( bhela_bm_money( $earn ) ); ?></b></div>
		<div><span>Total Cost for This Trip</span><b><?php echo esc_html( bhela_bm_money( $total ) ); ?></b></div>
		<div><span>Total Profit from This Trip</span><b><?php echo esc_html( bhela_bm_money( $earn - $total ) ); ?></b></div>
	</div>

	<div class="bha-doc__sign">
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
