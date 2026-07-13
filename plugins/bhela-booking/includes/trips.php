<?php
/**
 * Trip calendar: admin-managed departure dates with status.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Default trips (August 2026 schedule). */
function bhela_bm_default_trips() {
	return array(
		array( 'date' => '2026-07-31', 'label' => '31 Jul – 1 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-02', 'label' => '2 – 3 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-04', 'label' => '4 – 5 / 5 – 6 Aug 2026', 'days' => 'মঙ্গল – বুধ / বুধ – বৃহস্পতি', 'note' => '৫ আগস্ট ছুটি', 'status' => 'available' ),
		array( 'date' => '2026-08-07', 'label' => '7 – 8 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-09', 'label' => '9 – 10 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-11', 'label' => '11 – 12 / 12 – 13 Aug 2026', 'days' => 'মঙ্গল – বুধ / বুধ – বৃহস্পতি', 'note' => '১২ আগস্ট ছুটি', 'status' => 'available' ),
		array( 'date' => '2026-08-14', 'label' => '14 – 15 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-16', 'label' => '16 – 17 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-18', 'label' => '18 – 19 Aug 2026', 'days' => 'মঙ্গল – বুধ', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-21', 'label' => '21 – 22 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-23', 'label' => '23 – 24 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available' ),
		array( 'date' => '2026-08-25', 'label' => '25 – 26 / 26 – 27 Aug 2026', 'days' => 'মঙ্গল – বুধ / বুধ – বৃহস্পতি', 'note' => '২৬ আগস্ট ছুটি', 'status' => 'available' ),
		array( 'date' => '2026-08-28', 'label' => '28 – 29 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available' ),
	);
}

/** Get trips sorted by date. */
function bhela_bm_get_trips() {
	$trips = get_option( 'bhela_bm_trips', null );
	if ( ! is_array( $trips ) ) {
		$trips = bhela_bm_default_trips();
	}
	usort( $trips, function ( $a, $b ) {
		return strcmp( $a['date'], $b['date'] );
	} );
	return $trips;
}

function bhela_bm_trip_statuses() {
	return array(
		'available' => array( 'label' => 'Available (সিট আছে)', 'short' => 'Available ✅', 'color' => '#1a7f37' ),
		'filling'   => array( 'label' => 'Filling Fast (দ্রুত পূরণ হচ্ছে)', 'short' => 'Filling Fast 🔥', 'color' => '#b45309' ),
		'booked'    => array( 'label' => 'Booked (বুকড)', 'short' => 'Booked ❌', 'color' => '#b32d2e' ),
	);
}

/* ---------- Admin page: Trip Calendar ---------- */

function bhela_bm_trips_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Trip Calendar', 'bhela-booking' ),
		__( 'Trip Calendar', 'bhela-booking' ),
		'manage_options',
		'bhela-bm-trips',
		'bhela_bm_trips_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_trips_menu' );

function bhela_bm_trips_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['bhela_bm_trips_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_trips_nonce'] ) ), 'bhela_bm_trips' ) ) {
		$trips = array();
		$dates    = (array) ( $_POST['trip_date'] ?? array() );
		$labels   = (array) ( $_POST['trip_label'] ?? array() );
		$days     = (array) ( $_POST['trip_days'] ?? array() );
		$notes    = (array) ( $_POST['trip_note'] ?? array() );
		$statuses = (array) ( $_POST['trip_status'] ?? array() );
		$deletes  = (array) ( $_POST['trip_delete'] ?? array() );
		foreach ( $dates as $i => $date ) {
			$date = sanitize_text_field( $date );
			if ( ! $date || ! empty( $deletes[ $i ] ) ) {
				continue;
			}
			$status = sanitize_key( $statuses[ $i ] ?? 'available' );
			$trips[] = array(
				'date'   => $date,
				'label'  => sanitize_text_field( $labels[ $i ] ?? '' ),
				'days'   => sanitize_text_field( $days[ $i ] ?? '' ),
				'note'   => sanitize_text_field( $notes[ $i ] ?? '' ),
				'status' => array_key_exists( $status, bhela_bm_trip_statuses() ) ? $status : 'available',
			);
		}
		update_option( 'bhela_bm_trips', $trips );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Trip calendar saved.', 'bhela-booking' ) . '</p></div>';
	}

	$trips    = bhela_bm_get_trips();
	$statuses = bhela_bm_trip_statuses();
	?>
	<div class="wrap">
		<h1>📅 <?php esc_html_e( 'BHELA Trip Calendar', 'bhela-booking' ); ?></h1>
		<p><?php esc_html_e( 'Manage departure dates here — the website schedule page and booking calendar update automatically. Empty date = row ignored.', 'bhela-booking' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'bhela_bm_trips', 'bhela_bm_trips_nonce' ); ?>
			<table class="widefat striped" id="bhela-trips-table" style="max-width:1100px">
				<thead><tr>
					<th style="width:140px"><?php esc_html_e( 'Start Date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Display Label', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Days (Bangla)', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Note', 'bhela-booking' ); ?></th>
					<th style="width:170px"><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Delete', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $trips as $i => $t ) : ?>
					<tr>
						<td><input type="date" name="trip_date[]" value="<?php echo esc_attr( $t['date'] ); ?>"></td>
						<td><input type="text" style="width:100%" name="trip_label[]" value="<?php echo esc_attr( $t['label'] ); ?>"></td>
						<td><input type="text" style="width:100%" name="trip_days[]" value="<?php echo esc_attr( $t['days'] ); ?>"></td>
						<td><input type="text" style="width:100%" name="trip_note[]" value="<?php echo esc_attr( $t['note'] ); ?>"></td>
						<td><select name="trip_status[]">
							<?php foreach ( $statuses as $key => $st ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $t['status'], $key ); ?>><?php echo esc_html( $st['label'] ); ?></option>
							<?php endforeach; ?>
						</select></td>
						<td style="text-align:center"><input type="checkbox" name="trip_delete[<?php echo esc_attr( $i ); ?>]" value="1"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="bhela-add-trip">➕ <?php esc_html_e( 'Add Trip', 'bhela-booking' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Trip Calendar', 'bhela-booking' ); ?></button>
			</p>
		</form>
	</div>
	<script>
	document.getElementById('bhela-add-trip').addEventListener('click', function () {
		var tbody = document.querySelector('#bhela-trips-table tbody');
		var row = document.createElement('tr');
		row.innerHTML = '<td><input type="date" name="trip_date[]"></td>' +
			'<td><input type="text" style="width:100%" name="trip_label[]" placeholder="e.g. 4 – 5 Sep 2026"></td>' +
			'<td><input type="text" style="width:100%" name="trip_days[]" placeholder="শুক্র – শনি"></td>' +
			'<td><input type="text" style="width:100%" name="trip_note[]"></td>' +
			'<td><select name="trip_status[]"><option value="available">Available</option><option value="filling">Filling Fast</option><option value="booked">Booked</option></select></td>' +
			'<td style="text-align:center">—</td>';
		tbody.appendChild(row);
	});
	</script>
	<?php
}

/* ---------- Shortcode: [bhela_trip_calendar] ---------- */

function bhela_bm_trip_calendar_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	$trips    = bhela_bm_get_trips();
	$statuses = bhela_bm_trip_statuses();
	if ( ! $trips ) {
		return '<p>শীঘ্রই নতুন ট্রিপ ঘোষণা করা হবে।</p>';
	}
	$book_page = get_page_by_path( 'book-now' );
	$book_url  = $book_page ? get_permalink( $book_page ) : home_url( '/' );

	ob_start();
	echo '<div class="bhela-trips">';
	foreach ( $trips as $t ) {
		$st       = $statuses[ $t['status'] ] ?? $statuses['available'];
		$day_type = function_exists( 'bhela_bm_day_type' ) ? bhela_bm_day_type( $t['date'] ) : 'weekend';
		$type_tag = ( 'weekday' === $day_type ) ? '<span class="bhela-trip__type bhela-trip__type--weekday">Weekday −20% 🔥</span>' : ( 'holiday' === $day_type ? '<span class="bhela-trip__type bhela-trip__type--holiday">ছুটির দিন</span>' : '<span class="bhela-trip__type">Weekend</span>' );
		echo '<div class="bhela-trip bhela-trip--' . esc_attr( $t['status'] ) . '">';
		echo '<div class="bhela-trip__date"><strong>' . esc_html( $t['label'] ? $t['label'] : $t['date'] ) . '</strong>';
		if ( $t['days'] ) {
			echo '<span>' . esc_html( $t['days'] ) . '</span>';
		}
		echo '</div>';
		echo '<div class="bhela-trip__meta">' . $type_tag;
		if ( $t['note'] ) {
			echo '<span class="bhela-trip__note">' . esc_html( $t['note'] ) . '</span>';
		}
		echo '</div>';
		echo '<span class="bhela-trip__status" style="color:' . esc_attr( $st['color'] ) . '">' . esc_html( $st['short'] ) . '</span>';
		if ( 'booked' === $t['status'] ) {
			echo '<span class="bhela-trip__cta bhela-trip__cta--off">বুকড</span>';
		} else {
			echo '<a class="bhela-trip__cta" href="' . esc_url( add_query_arg( 'date', $t['date'], $book_url ) ) . '">বুক করুন →</a>';
		}
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'bhela_trip_calendar', 'bhela_bm_trip_calendar_shortcode' );
