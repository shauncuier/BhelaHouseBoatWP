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

/**
 * Cabin availability for a date.
 * 'booked' comes from the trip's admin-managed count; a full boat
 * (booked >= total) forces effective status 'booked' regardless of the
 * manual status dropdown.
 *
 * @return array{ total:int, booked:int, available:int, trip:array|null, status:string }
 */
function bhela_bm_trip_availability( $date ) {
	$total = bhela_bm_max_cabins();
	$trip  = null;
	foreach ( bhela_bm_get_trips() as $t ) {
		if ( $t['date'] === $date ) {
			$trip = $t;
			break;
		}
	}
	$booked = $trip ? min( $total, max( 0, (int) ( $trip['booked'] ?? 0 ) ) ) : 0;
	$status = $trip ? $trip['status'] : 'unknown';
	if ( $booked >= $total ) {
		$status = 'booked';
	}
	return array(
		'total'     => $total,
		'booked'    => $booked,
		'available' => max( 0, $total - $booked ),
		'trip'      => $trip,
		'status'    => $status,
	);
}

/** Cabins consumed by real bookings (advance_paid/confirmed) on a date — admin reconciliation hint. */
function bhela_bm_counted_booked_cabins( $date ) {
	$q = new WP_Query( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_bhela_travel_date', 'value' => $date, 'compare' => '=' ),
			array( 'key' => '_bhela_status', 'value' => array( 'advance_paid', 'confirmed' ), 'compare' => 'IN' ),
		),
	) );
	$cabins = 0;
	foreach ( $q->posts as $id ) {
		$rows    = json_decode( (string) get_post_meta( $id, '_bhela_cabins_json', true ), true );
		$cabins += is_array( $rows ) && $rows ? count( $rows ) : 1;
	}
	return $cabins;
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
		$bookeds  = (array) ( $_POST['trip_booked'] ?? array() );
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
				'booked' => min( bhela_bm_max_cabins(), max( 0, (int) ( $bookeds[ $i ] ?? 0 ) ) ),
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
					<th style="width:130px"><?php esc_html_e( 'Booked Cabins', 'bhela-booking' ); ?></th>
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
						<td>
							<input type="number" name="trip_booked[]" min="0" max="<?php echo esc_attr( bhela_bm_max_cabins() ); ?>" value="<?php echo esc_attr( (int) ( $t['booked'] ?? 0 ) ); ?>" style="width:64px"> / <?php echo esc_html( bhela_bm_max_cabins() ); ?>
							<br><small style="color:#666"><?php printf( esc_html__( 'বুকিং থেকে: %d', 'bhela-booking' ), (int) bhela_bm_counted_booked_cabins( $t['date'] ) ); ?></small>
						</td>
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
			'<td><input type="number" name="trip_booked[]" min="0" max="6" value="0" style="width:64px"> / 6</td>' +
			'<td style="text-align:center">—</td>';
		tbody.appendChild(row);
	});
	</script>
	<?php
}

/* ---------- Shortcode: [bhela_trip_calendar] ---------- */

/**
 * The cheapest per-person rate for a day type — the largest sharing tier is
 * always the lowest per head, so it is the honest "from" price.
 * Returns array( 'now' => int, 'was' => int|0 ); 'was' is set only when the
 * day is discounted, so the saving can be shown struck through.
 */
function bhela_bm_trip_from_price( $day_type ) {
	if ( ! function_exists( 'bhela_bm_rates_by_occupancy' ) ) {
		return array( 'now' => 0, 'was' => 0 );
	}
	$map = bhela_bm_rates_by_occupancy();
	if ( ! $map ) {
		return array( 'now' => 0, 'was' => 0 );
	}
	$row = $map[ max( array_keys( $map ) ) ]; // biggest cabin = cheapest per head
	if ( 'weekday' === $day_type ) {
		return array( 'now' => (int) $row['weekday'], 'was' => (int) $row['regular'] );
	}
	return array( 'now' => (int) $row['regular'], 'was' => 0 );
}

/** Styled "nothing to show" card, shared by the empty and all-past cases. */
function bhela_bm_trips_empty_html() {
	$wa = function_exists( 'bhela_wa_link' ) ? bhela_wa_link() : '';
	ob_start();
	?>
	<div class="bhela-trips-empty">
		<p><strong><?php esc_html_e( 'শীঘ্রই নতুন ট্রিপ ঘোষণা করা হবে।', 'bhela-booking' ); ?></strong></p>
		<p><?php esc_html_e( 'আপনার পছন্দের তারিখ বলুন — আমরা জানিয়ে দেব কখন যাওয়া যাবে।', 'bhela-booking' ); ?></p>
		<?php if ( $wa ) : ?>
			<a class="bhela-trip__cta" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">💬 <?php esc_html_e( 'WhatsApp-এ জিজ্ঞেস করুন', 'bhela-booking' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function bhela_bm_trip_calendar_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	$trips    = bhela_bm_get_trips();
	$statuses = bhela_bm_trip_statuses();
	if ( ! $trips ) {
		return bhela_bm_trips_empty_html();
	}
	$book_page = get_page_by_path( 'book-now' );
	$book_url  = $book_page ? get_permalink( $book_page ) : home_url( '/' );

	// Departed trips must not be bookable — the booking form already filters
	// them the same way (includes/frontend.php).
	$today    = current_time( 'Y-m-d' );
	$upcoming = array();
	foreach ( $trips as $t ) {
		if ( empty( $t['date'] ) || $t['date'] < $today ) {
			continue;
		}
		$upcoming[] = $t;
	}
	if ( ! $upcoming ) {
		return bhela_bm_trips_empty_html();
	}

	ob_start();
	echo '<div class="bhela-trips">';

	$current_month = '';
	$first         = true;
	foreach ( $upcoming as $t ) {
		$ts    = strtotime( $t['date'] );
		$month = date( 'Y-m', $ts );
		if ( $month !== $current_month ) {
			$current_month = $month;
			echo '<h3 class="bhela-trips__month">' . esc_html( date_i18n( 'F Y', $ts ) ) . '</h3>';
		}

		$avail       = bhela_bm_trip_availability( $t['date'] );
		$t['status'] = $avail['status']; // full boat forces 'booked'
		$st          = $statuses[ $t['status'] ] ?? $statuses['available'];
		$day_type    = function_exists( 'bhela_bm_day_type' ) ? bhela_bm_day_type( $t['date'] ) : 'weekend';
		$low         = 'booked' !== $t['status'] && $avail['available'] > 0 && $avail['available'] <= 2;

		$classes = array( 'bhela-trip', 'bhela-trip--' . $t['status'] );
		if ( $first && 'booked' !== $t['status'] ) {
			$classes[] = 'bhela-trip--next';
		}
		if ( $low ) {
			$classes[] = 'bhela-trip--low';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $first && 'booked' !== $t['status'] ) : ?>
				<span class="bhela-trip__ribbon"><?php esc_html_e( 'পরবর্তী ট্রিপ', 'bhela-booking' ); ?></span>
			<?php endif; ?>

			<div class="bhela-trip__date">
				<strong><?php echo esc_html( $t['label'] ? $t['label'] : $t['date'] ); ?></strong>
				<?php if ( $t['days'] ) : ?>
					<span><?php echo esc_html( $t['days'] ); ?></span>
				<?php endif; ?>
			</div>

			<div class="bhela-trip__meta">
				<?php if ( 'weekday' === $day_type ) : ?>
					<span class="bhela-trip__type bhela-trip__type--weekday"><?php esc_html_e( 'Weekday −20% 🔥', 'bhela-booking' ); ?></span>
				<?php elseif ( 'holiday' === $day_type ) : ?>
					<span class="bhela-trip__type bhela-trip__type--holiday"><?php esc_html_e( 'ছুটির দিন', 'bhela-booking' ); ?></span>
				<?php else : ?>
					<span class="bhela-trip__type"><?php esc_html_e( 'Weekend', 'bhela-booking' ); ?></span>
				<?php endif; ?>
				<?php if ( $t['note'] ) : ?>
					<span class="bhela-trip__note"><?php echo esc_html( $t['note'] ); ?></span>
				<?php endif; ?>
			</div>

			<?php
			$price = bhela_bm_trip_from_price( $day_type );
			if ( $price['now'] ) :
				?>
				<div class="bhela-trip__price">
					<span class="bhela-trip__price-label"><?php esc_html_e( 'জনপ্রতি', 'bhela-booking' ); ?></span>
					<strong><?php echo esc_html( bhela_bm_money( $price['now'] ) ); ?></strong>
					<?php if ( $price['was'] ) : ?>
						<s><?php echo esc_html( bhela_bm_money( $price['was'] ) ); ?></s>
					<?php endif; ?>
					<span class="bhela-trip__price-from"><?php esc_html_e( 'থেকে', 'bhela-booking' ); ?></span>
				</div>
			<?php endif; ?>

			<div class="bhela-trip__foot">
				<div class="bhela-trip__avail">
					<span class="bhela-trip__status bhela-trip__status--<?php echo esc_attr( $t['status'] ); ?>"><?php echo esc_html( $st['short'] ); ?></span>
					<?php if ( 'booked' !== $t['status'] ) : ?>
						<span class="bhela-trip__cabins<?php echo $low ? ' bhela-trip__cabins--low' : ''; ?>">
							<?php
							echo esc_html( $low
								? sprintf( __( '🔥 শেষ %dটি কেবিন!', 'bhela-booking' ), $avail['available'] )
								: sprintf( __( '🛏️ %1$d/%2$dটি কেবিন খালি', 'bhela-booking' ), $avail['available'], $avail['total'] )
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<?php if ( 'booked' === $t['status'] ) : ?>
					<span class="bhela-trip__cta bhela-trip__cta--off"><?php esc_html_e( 'বুকড', 'bhela-booking' ); ?></span>
				<?php else : ?>
					<a class="bhela-trip__cta" href="<?php echo esc_url( add_query_arg( 'date', $t['date'], $book_url ) ); ?>"><?php esc_html_e( 'বুক করুন →', 'bhela-booking' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$first = false;
	}

	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'bhela_trip_calendar', 'bhela_bm_trip_calendar_shortcode' );
