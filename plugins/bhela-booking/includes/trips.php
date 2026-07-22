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

/** Bangla weekday names, indexed by date('w') — 0 = Sunday. */
function bhela_bm_bn_weekdays() {
	return array( 'রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি' );
}

/**
 * Trip end date. Trips are 2 days 1 night, so the default is the day after
 * the start; an explicitly stored end always wins.
 */
function bhela_bm_trip_end( $trip ) {
	if ( ! empty( $trip['end'] ) ) {
		return $trip['end'];
	}
	$ts = strtotime( $trip['date'] ?? '' );
	return $ts ? gmdate( 'Y-m-d', strtotime( '+1 day', $ts ) ) : '';
}

/**
 * Display label built from the two dates, collapsing whatever the dates share:
 *   same month  → "2 – 3 Aug 2026"
 *   same year   → "31 Jul – 1 Aug 2026"
 *   crosses year→ "31 Dec 2026 – 1 Jan 2027"
 */
function bhela_bm_trip_label( $start, $end ) {
	$s = strtotime( $start );
	$e = strtotime( $end );
	if ( ! $s ) {
		return '';
	}
	if ( ! $e || $e <= $s ) {
		return gmdate( 'j M Y', $s );
	}
	if ( gmdate( 'Y', $s ) !== gmdate( 'Y', $e ) ) {
		return gmdate( 'j M Y', $s ) . ' – ' . gmdate( 'j M Y', $e );
	}
	if ( gmdate( 'm', $s ) !== gmdate( 'm', $e ) ) {
		return gmdate( 'j M', $s ) . ' – ' . gmdate( 'j M Y', $e );
	}
	return gmdate( 'j', $s ) . ' – ' . gmdate( 'j M Y', $e );
}

/** Nights covered by the trip. A same-day trip is 0 nights. */
function bhela_bm_trip_nights( $start, $end ) {
	$s = strtotime( $start );
	$e = strtotime( $end );
	if ( ! $s || ! $e || $e <= $s ) {
		return 1; // standard 2D1N when the end is missing or invalid
	}
	return max( 0, (int) round( ( $e - $s ) / DAY_IN_SECONDS ) );
}

/**
 * Human duration, e.g. "২ দিন ১ রাত" or "৪ দিন ৩ রাত". Full-boat charters can
 * run longer than the standard package, so this is derived, never hardcoded.
 */
function bhela_bm_trip_duration( $start, $end ) {
	$nights = bhela_bm_trip_nights( $start, $end );
	$bn     = function ( $n ) {
		return function_exists( 'bhela_bm_bn_num' ) ? bhela_bm_bn_num( $n ) : $n;
	};
	if ( $nights < 1 ) {
		return 'দিনে দিনে';
	}
	return sprintf( '%s দিন %s রাত', $bn( $nights + 1 ), $bn( $nights ) );
}

/** True when the trip is the standard 2 days 1 night package. */
function bhela_bm_trip_is_standard( $start, $end ) {
	return 1 === bhela_bm_trip_nights( $start, $end );
}

/** Bangla day pair for the trip, e.g. "শুক্র – শনি". */
function bhela_bm_trip_days( $start, $end ) {
	$s = strtotime( $start );
	$e = strtotime( $end );
	if ( ! $s ) {
		return '';
	}
	$names = bhela_bm_bn_weekdays();
	$from  = $names[ (int) gmdate( 'w', $s ) ];
	if ( ! $e || $e <= $s ) {
		return $from;
	}
	return $from . ' – ' . $names[ (int) gmdate( 'w', $e ) ];
}

/** Get trips sorted by date, with end/label/days filled in for older rows. */
function bhela_bm_get_trips() {
	$trips = get_option( 'bhela_bm_trips', null );
	if ( ! is_array( $trips ) ) {
		$trips = bhela_bm_default_trips();
	}
	foreach ( $trips as $i => $t ) {
		$trips[ $i ]['end'] = bhela_bm_trip_end( $t );
		if ( empty( $t['label'] ) ) {
			$trips[ $i ]['label'] = bhela_bm_trip_label( $t['date'] ?? '', $trips[ $i ]['end'] );
		}
		if ( empty( $t['days'] ) ) {
			$trips[ $i ]['days'] = bhela_bm_trip_days( $t['date'] ?? '', $trips[ $i ]['end'] );
		}
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
 *
 * Effective booked = the larger of the owner's manual hold (the calendar's
 * "Booked Cabins" field) and the live count of real bookings that hold a seat
 * (advance paid / confirmed). So confirming a booking reduces availability
 * automatically — nobody has to edit the calendar — which keeps every manager
 * and the public schedule on the same live number, while an owner's manual
 * full-boat hold is still honoured. A full boat forces status 'booked'.
 *
 * @return array{ total:int, booked:int, manual:int, counted:int, available:int, trip:array|null, status:string }
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
	$manual  = $trip ? max( 0, (int) ( $trip['booked'] ?? 0 ) ) : 0;
	$counted = min( $total, (int) bhela_bm_counted_booked_cabins( $date ) );
	$booked  = min( $total, max( $manual, $counted ) );
	$status  = $trip ? $trip['status'] : 'unknown';
	if ( $booked >= $total ) {
		$status = 'booked';
	}
	return array(
		'total'     => $total,
		'booked'    => $booked,
		'manual'    => $manual,
		'counted'   => $counted,
		'available' => max( 0, $total - $booked ),
		'trip'      => $trip,
		'status'    => $status,
	);
}

/**
 * Cabins consumed by real bookings (advance_paid/confirmed) on a date.
 * Memoised per request — availability is asked for the same date several
 * times while rendering the schedule, and this saves the repeat queries.
 */
function bhela_bm_counted_booked_cabins( $date ) {
	static $cache = array();
	if ( array_key_exists( $date, $cache ) ) {
		return $cache[ $date ];
	}
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
	$cache[ $date ] = $cabins;
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
		$ends     = (array) ( $_POST['trip_end'] ?? array() );
		$labels   = (array) ( $_POST['trip_label'] ?? array() );
		$days     = (array) ( $_POST['trip_days'] ?? array() );
		$notes    = (array) ( $_POST['trip_note'] ?? array() );
		$statuses = (array) ( $_POST['trip_status'] ?? array() );
		$bookeds  = (array) ( $_POST['trip_booked'] ?? array() );
		$holidays = (array) ( $_POST['trip_holiday'] ?? array() );
		$deletes  = (array) ( $_POST['trip_delete'] ?? array() );
		foreach ( $dates as $i => $date ) {
			$date = sanitize_text_field( $date );
			if ( ! $date || ! empty( $deletes[ $i ] ) ) {
				continue;
			}
			$status = sanitize_key( $statuses[ $i ] ?? 'available' );

			// End date defaults to the night after the start (2D1N).
			$end = sanitize_text_field( $ends[ $i ] ?? '' );
			if ( ! $end || $end < $date ) {
				$end = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $date ) ) );
			}
			// Label and days are generated from the dates unless the owner typed
			// something of their own — a blank field always means "regenerate".
			$label = sanitize_text_field( $labels[ $i ] ?? '' );
			$daybn = sanitize_text_field( $days[ $i ] ?? '' );
			if ( '' === $label ) {
				$label = bhela_bm_trip_label( $date, $end );
			}
			if ( '' === $daybn ) {
				$daybn = bhela_bm_trip_days( $date, $end );
			}

			$trips[] = array(
				'date'    => $date,
				'end'     => $end,
				'label'   => $label,
				'days'    => $daybn,
				'note'    => sanitize_text_field( $notes[ $i ] ?? '' ),
				'holiday' => ! empty( $holidays[ $i ] ),
				'status'  => array_key_exists( $status, bhela_bm_trip_statuses() ) ? $status : 'available',
				'booked'  => min( bhela_bm_max_cabins(), max( 0, (int) ( $bookeds[ $i ] ?? 0 ) ) ),
			);
		}
		// Record what changed before writing, so a departure date that disappears
		// can always be traced back to the save that removed it. Report the
		// human labels the owner actually sees, not raw ISO dates.
		$before       = bhela_bm_get_trips();
		$before_dates = wp_list_pluck( $before, 'date' );
		$after_dates  = wp_list_pluck( $trips, 'date' );
		$removed      = array_values( array_diff( $before_dates, $after_dates ) );
		$added        = array_values( array_diff( $after_dates, $before_dates ) );

		$label_of = array();
		foreach ( array_merge( $before, $trips ) as $row ) {
			$label_of[ $row['date'] ] = $row['label'] ? $row['label'] : $row['date'];
		}
		$names = function ( $dates ) use ( $label_of ) {
			$out = array();
			foreach ( $dates as $d ) {
				$out[] = $label_of[ $d ] ?? $d;
			}
			return implode( ', ', $out );
		};

		update_option( 'bhela_bm_trips', $trips );

		if ( function_exists( 'bhela_bm_log' ) ) {
			$total = bhela_bm_bn_num( count( $after_dates ) );
			if ( $removed && $added ) {
				$msg = sprintf( '%sটি তারিখ মুছে ফেলা হয়েছে (%s), %sটি যোগ হয়েছে (%s)। এখন মোট %sটি ট্রিপ।',
					bhela_bm_bn_num( count( $removed ) ), $names( $removed ),
					bhela_bm_bn_num( count( $added ) ), $names( $added ), $total );
			} elseif ( $removed ) {
				$msg = sprintf( '%sটি তারিখ মুছে ফেলা হয়েছে — %s। এখন মোট %sটি ট্রিপ।',
					bhela_bm_bn_num( count( $removed ) ), $names( $removed ), $total );
			} elseif ( $added ) {
				$msg = sprintf( '%sটি নতুন তারিখ যোগ হয়েছে — %s। এখন মোট %sটি ট্রিপ।',
					bhela_bm_bn_num( count( $added ) ), $names( $added ), $total );
			} else {
				$msg = sprintf( 'ক্যালেন্ডার আপডেট করা হয়েছে — মোট %sটি ট্রিপ (তারিখ অপরিবর্তিত)।', $total );
			}
			bhela_bm_log( 'trips', $msg );
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Trip calendar saved.', 'bhela-booking' ) . '</p></div>';
		if ( $removed ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf(
				/* translators: 1: number of removed dates, 2: comma separated trip labels */
				__( '⚠️ %1$sটি তারিখ মুছে ফেলা হয়েছে: %2$s', 'bhela-booking' ),
				bhela_bm_bn_num( count( $removed ) ),
				$names( $removed )
			) ) . '</p></div>';
		}
	}

	$trips    = bhela_bm_get_trips();
	$statuses = bhela_bm_trip_statuses();
	?>
	<div class="wrap">
		<h1>📅 <?php esc_html_e( 'BHELA Trip Calendar', 'bhela-booking' ); ?></h1>
		<p><?php esc_html_e( 'Manage departure dates here — the website schedule page and booking calendar update automatically. Empty date = row ignored.', 'bhela-booking' ); ?></p>
		<p class="description"><?php esc_html_e( '“ছুটি” টিক দিলে ওই ট্রিপে ২০% উইকডে ছাড় থাকবে না — রেগুলার রেট ধরা হবে এবং সাইটে “ছুটির দিন” দেখাবে।', 'bhela-booking' ); ?></p>
		<p class="description"><?php esc_html_e( 'Booked Cabins স্বয়ংক্রিয়: কোনো বুকিং Advance Paid বা Confirmed হলে ওই তারিখের খালি কেবিন নিজে থেকেই কমে যায় — আলাদা করে এখানে বসাতে হয় না, সব ম্যানেজার ও ওয়েবসাইট একই লাইভ সংখ্যা দেখে। Booked Cabins ঘরটি শুধু ম্যানুয়াল হোল্ড (সর্বনিম্ন) — যেমন ফোন বুকিং বা পুরো বোট ব্লক করতে।', 'bhela-booking' ); ?></p>
		<p class="description"><?php esc_html_e( 'End Date খালি রাখলে ২ দিন ১ রাত ধরা হয়। Full Boat বা লম্বা ট্রিপে End Date বাড়িয়ে দিন — লেবেল, দিন ও সময়কাল নিজে থেকেই ঠিক হবে। Start Date বদলালে ট্রিপের দৈর্ঘ্য ঠিক থাকে।', 'bhela-booking' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'bhela_bm_trips', 'bhela_bm_trips_nonce' ); ?>
			<style>
				/* The label/days fields hold long Bangla strings — give them room and
				   let the table scroll instead of squeezing the text out of sight. */
				#bhela-trips-wrap { overflow-x: auto; }
				#bhela-trips-table { min-width: 1220px; }
				#bhela-trips-table input[type="text"] { width: 100%; min-width: 150px; }
				#bhela-trips-table tr.is-past { opacity: .6; }
				#bhela-trips-table .bhela-trip-dur { display: block; margin-top: 4px; font-size: 11px; color: #646970; }
				#bhela-trips-table .bhela-hol { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
				#bhela-trips-table .bhela-hol span { font-size: 11px; color: #b45309; }
				#bhela-trips-table .bhela-trip-dur.is-long { color: #b45309; font-weight: 600; }
				#bhela-trips-table .bhela-past-tag {
					display: inline-block; margin-top: 4px; padding: 1px 8px; border-radius: 999px;
					background: #f0f0f1; color: #646970; font-size: 11px; white-space: nowrap;
				}
					#bhela-trips-table .bhela-avail-cell { white-space: nowrap; }
					#bhela-trips-table .bhela-hold { display: flex; align-items: center; gap: 6px; }
					#bhela-trips-table .bhela-hold input[type="number"] { width: 56px; min-width: 56px; text-align: center; }
					#bhela-trips-table .bhela-hold__cap { font-size: 11px; color: #787c82; }
					#bhela-trips-table .bhela-avail-pill {
						display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px;
						font-size: 11px; font-weight: 600; color: #fff; white-space: nowrap;
					}
					#bhela-trips-table .bhela-avail-pill.is-open { background: #1a7f37; }
					#bhela-trips-table .bhela-avail-pill.is-full { background: #b32d2e; }
					#bhela-trips-table .bhela-avail-note { display: block; margin-top: 3px; font-size: 11px; color: #787c82; }
			</style>
			<div id="bhela-trips-wrap">
			<table class="widefat striped" id="bhela-trips-table">
				<thead><tr>
					<th style="width:140px"><?php esc_html_e( 'Start Date', 'bhela-booking' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'End Date', 'bhela-booking' ); ?></th>
					<th style="width:20%"><?php esc_html_e( 'Display Label', 'bhela-booking' ); ?></th>
					<th style="width:20%"><?php esc_html_e( 'Days (Bangla)', 'bhela-booking' ); ?></th>
					<th style="width:16%"><?php esc_html_e( 'Note', 'bhela-booking' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'ছুটি', 'bhela-booking' ); ?></th>
					<th style="width:170px"><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Booked Cabins', 'bhela-booking' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Delete', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				$bm_today_admin = current_time( 'Y-m-d' );
				foreach ( $trips as $i => $t ) :
					$bm_is_past = ! empty( $t['date'] ) && $t['date'] < $bm_today_admin;
					?>
					<tr class="<?php echo $bm_is_past ? 'is-past' : ''; ?>">
						<td><input type="date" class="bhela-trip-start" name="trip_date[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $t['date'] ); ?>">
							<?php if ( $bm_is_past ) : ?>
								<span class="bhela-past-tag"><?php esc_html_e( 'চলে গেছে — সাইটে দেখাচ্ছে না', 'bhela-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td><input type="date" class="bhela-trip-end" name="trip_end[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( bhela_bm_trip_end( $t ) ); ?>">
							<span class="bhela-trip-dur"><?php echo esc_html( bhela_bm_trip_duration( $t['date'], bhela_bm_trip_end( $t ) ) ); ?></span>
						</td>
						<td><input type="text" style="width:100%" class="bhela-trip-label" name="trip_label[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $t['label'] ); ?>"></td>
						<td><input type="text" style="width:100%" class="bhela-trip-days" name="trip_days[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $t['days'] ); ?>"></td>
						<td><input type="text" style="width:100%" name="trip_note[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $t['note'] ); ?>"></td>
						<td style="text-align:center">
							<label class="bhela-hol"><input type="checkbox" name="trip_holiday[<?php echo esc_attr( $i ); ?>]" value="1" <?php checked( ! empty( $t['holiday'] ) ); ?>> <span><?php esc_html_e( 'ছুটি', 'bhela-booking' ); ?></span></label>
						</td>
						<td><select name="trip_status[<?php echo esc_attr( $i ); ?>]">
							<?php foreach ( $statuses as $key => $st ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $t['status'], $key ); ?>><?php echo esc_html( $st['label'] ); ?></option>
							<?php endforeach; ?>
						</select></td>
						<td class="bhela-avail-cell">
							<?php
							$bm_av   = bhela_bm_trip_availability( $t['date'] );
							$bm_full = (int) $bm_av['available'] <= 0;
							?>
							<div class="bhela-hold">
								<input type="number" name="trip_booked[<?php echo esc_attr( $i ); ?>]" min="0" max="<?php echo esc_attr( bhela_bm_max_cabins() ); ?>" value="<?php echo esc_attr( (int) ( $t['booked'] ?? 0 ) ); ?>" title="<?php esc_attr_e( 'ম্যানুয়াল হোল্ড (সর্বনিম্ন) — যেমন ফোন/ফুল বোট বুকিং', 'bhela-booking' ); ?>">
								<span class="bhela-hold__cap"><?php esc_html_e( 'হোল্ড', 'bhela-booking' ); ?></span>
							</div>
							<span class="bhela-avail-pill <?php echo $bm_full ? 'is-full' : 'is-open'; ?>">
								<?php echo $bm_full
									? sprintf( esc_html__( 'বুকড %1$d/%2$d', 'bhela-booking' ), (int) $bm_av['booked'], (int) $bm_av['total'] )
									: sprintf( esc_html__( 'খালি %1$d/%2$d', 'bhela-booking' ), (int) $bm_av['available'], (int) $bm_av['total'] ); ?>
							</span>
							<?php if ( (int) $bm_av['counted'] > 0 ) : ?>
								<span class="bhela-avail-note"><?php printf( esc_html__( 'বুকিং: %d', 'bhela-booking' ), (int) $bm_av['counted'] ); ?></span>
							<?php endif; ?>
						</td>
						<td style="text-align:center"><input type="checkbox" name="trip_delete[<?php echo esc_attr( $i ); ?>]" value="1"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p>
				<button type="button" class="button" id="bhela-add-trip">➕ <?php esc_html_e( 'Add Trip', 'bhela-booking' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Trip Calendar', 'bhela-booking' ); ?></button>
			</p>
		</form>
	</div>
	<script>
	(function () {
		var BN_DAYS = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];
		var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		function parse(v) {
			if (!v) { return null; }
			var p = v.split('-');
			if (p.length !== 3) { return null; }
			var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
			return isNaN(d) ? null : d;
		}

		/* Mirrors bhela_bm_trip_label()/bhela_bm_trip_days() in PHP — the server
		   regenerates on save too, so the two must agree. */
		function label(s, e) {
			if (!s) { return ''; }
			if (!e || e <= s) { return s.getUTCDate() + ' ' + MONTHS[s.getUTCMonth()] + ' ' + s.getUTCFullYear(); }
			if (s.getUTCFullYear() !== e.getUTCFullYear()) {
				return s.getUTCDate() + ' ' + MONTHS[s.getUTCMonth()] + ' ' + s.getUTCFullYear() +
					' – ' + e.getUTCDate() + ' ' + MONTHS[e.getUTCMonth()] + ' ' + e.getUTCFullYear();
			}
			if (s.getUTCMonth() !== e.getUTCMonth()) {
				return s.getUTCDate() + ' ' + MONTHS[s.getUTCMonth()] +
					' – ' + e.getUTCDate() + ' ' + MONTHS[e.getUTCMonth()] + ' ' + e.getUTCFullYear();
			}
			return s.getUTCDate() + ' – ' + e.getUTCDate() + ' ' + MONTHS[e.getUTCMonth()] + ' ' + e.getUTCFullYear();
		}

		function days(s, e) {
			if (!s) { return ''; }
			if (!e || e <= s) { return BN_DAYS[s.getUTCDay()]; }
			return BN_DAYS[s.getUTCDay()] + ' – ' + BN_DAYS[e.getUTCDay()];
		}

		function addDays(d, n) {
			return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate() + n));
		}
		function nextDay(d) { return addDays(d, 1); }
		function iso(d) {
			return d.getUTCFullYear() + '-' +
				String(d.getUTCMonth() + 1).padStart(2, '0') + '-' +
				String(d.getUTCDate()).padStart(2, '0');
		}

		/* Refill label/days from the dates, unless the owner typed their own text. */
		function nights(s, e) {
			if (!s || !e || e <= s) { return 1; }
			return Math.max(0, Math.round((e - s) / 86400000));
		}
		function bn(n) {
			return String(n).replace(/[0-9]/g, function (d) { return '০১২৩৪৫৬৭৮৯'[+d]; });
		}
		function durationText(n) {
			return n < 1 ? 'দিনে দিনে' : bn(n + 1) + ' দিন ' + bn(n) + ' রাত';
		}

		/* `movedStart` shifts the end by the same number of days, so a 4-day
		   full-boat charter stays 4 days when its departure is rescheduled. */
		function sync(row, movedStart) {
			var startEl = row.querySelector('.bhela-trip-start');
			var endEl = row.querySelector('.bhela-trip-end');
			var labelEl = row.querySelector('.bhela-trip-label');
			var daysEl = row.querySelector('.bhela-trip-days');
			var durEl = row.querySelector('.bhela-trip-dur');
			if (!startEl || !labelEl || !daysEl) { return; }

			var s = parse(startEl.value);
			if (!s) { return; }

			if (endEl) {
				var e0 = parse(endEl.value);
				if (!endEl.value) {
					endEl.value = iso(nextDay(s));           // default 2D1N
				} else if (movedStart && row._nights != null) {
					endEl.value = iso(addDays(s, row._nights)); // keep the length
				} else if (e0 && e0 < s) {
					endEl.value = iso(nextDay(s));           // end before start is not a trip
				}
			}
			var e = endEl ? parse(endEl.value) : null;
			row._nights = nights(s, e);

			if (labelEl.dataset.touched !== '1') { labelEl.value = label(s, e); }
			if (daysEl.dataset.touched !== '1') { daysEl.value = days(s, e); }
			if (durEl) {
				durEl.textContent = durationText(row._nights);
				durEl.classList.toggle('is-long', row._nights !== 1);
			}
		}

		function bind(row) {
			// Remember the current length so moving the start can preserve it.
			var s0 = parse((row.querySelector('.bhela-trip-start') || {}).value);
			var e0 = parse((row.querySelector('.bhela-trip-end') || {}).value);
			row._nights = s0 ? nights(s0, e0) : 1;

			var startEl = row.querySelector('.bhela-trip-start');
			if (startEl) { startEl.addEventListener('change', function () { sync(row, true); }); }
			var endEl = row.querySelector('.bhela-trip-end');
			if (endEl) { endEl.addEventListener('change', function () { sync(row, false); }); }
			// Typing in label/days marks them as owner-owned; clearing hands
			// control back to the generator.
			['.bhela-trip-label', '.bhela-trip-days'].forEach(function (sel) {
				var el = row.querySelector(sel);
				if (!el) { return; }
				el.addEventListener('input', function () {
					el.dataset.touched = el.value.trim() ? '1' : '';
				});
			});
		}

		document.querySelectorAll('#bhela-trips-table tbody tr').forEach(bind);

		document.getElementById('bhela-add-trip').addEventListener('click', function () {
			var tbody = document.querySelector('#bhela-trips-table tbody');
			var row = document.createElement('tr');
			// Every field carries an explicit index: a checkbox only posts when it
			// is ticked, so bare [] names would shift the parallel arrays out of
			// alignment as soon as one row is marked holiday and another is not.
			var i = tbody.rows.length;
			row.innerHTML = '<td><input type="date" class="bhela-trip-start" name="trip_date[' + i + ']"></td>' +
				'<td><input type="date" class="bhela-trip-end" name="trip_end[' + i + ']"><span class="bhela-trip-dur"></span></td>' +
				'<td><input type="text" style="width:100%" class="bhela-trip-label" name="trip_label[' + i + ']" placeholder="তারিখ দিলে নিজে থেকেই বসবে"></td>' +
				'<td><input type="text" style="width:100%" class="bhela-trip-days" name="trip_days[' + i + ']" placeholder="শুক্র – শনি"></td>' +
				'<td><input type="text" style="width:100%" name="trip_note[' + i + ']"></td>' +
				'<td style="text-align:center"><label class="bhela-hol"><input type="checkbox" name="trip_holiday[' + i + ']" value="1"> <span>ছুটি</span></label></td>' +
				'<td><select name="trip_status[' + i + ']"><option value="available">Available</option><option value="filling">Filling Fast</option><option value="booked">Booked</option></select></td>' +
				'<td><input type="number" name="trip_booked[' + i + ']" min="0" max="6" value="0" style="width:64px"> / 6</td>' +
				'<td style="text-align:center"><input type="checkbox" name="trip_delete[' + i + ']" value="1"></td>';
			tbody.appendChild(row);
			bind(row);
		});
	})();
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
				<?php
				$trip_end = bhela_bm_trip_end( $t );
				if ( ! bhela_bm_trip_is_standard( $t['date'], $trip_end ) ) :
					?>
					<span class="bhela-trip__type bhela-trip__type--long">🌙 <?php echo esc_html( bhela_bm_trip_duration( $t['date'], $trip_end ) ); ?></span>
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
