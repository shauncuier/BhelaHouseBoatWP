<?php
/**
 * Frontend: multi-cabin booking form + AJAX submission + availability check.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Assets ---------- */

function bhela_bm_enqueue_assets() {
	wp_register_style( 'bhela-bm-booking', BHELA_BM_URL . 'assets/booking.css', array(), BHELA_BM_VERSION );
	wp_register_script( 'bhela-bm-booking', BHELA_BM_URL . 'assets/booking.js', array(), BHELA_BM_VERSION, true );

	$settings = bhela_bm_get_settings();
	$rates    = array();
	foreach ( bhela_bm_get_rates() as $key => $row ) {
		$rates[ $key ] = array(
			'label'   => $row['label'],
			'sharing' => (int) $row['sharing'],
			'regular' => (int) $row['regular'],
			'weekday' => (int) $row['weekday'],
		);
	}
	wp_localize_script( 'bhela-bm-booking', 'bhelaBM', array(
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => wp_create_nonce( 'bhela_bm_booking' ),
		'rates'          => $rates,
		'weekendDays'    => array_map( 'intval', (array) $settings['weekend_days'] ),
		'holidays'       => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $settings['holidays'] ) ) ) ),
		'advancePercent' => (int) $settings['advance_percent'],
		'childPercent'   => 50,
		'whatsapp'       => preg_replace( '/[^0-9]/', '', $settings['whatsapp'] ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bhela_bm_enqueue_assets', 20 );

/* ---------- Shortcode: [bhela_booking_form] ---------- */

function bhela_bm_booking_form_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	wp_enqueue_script( 'bhela-bm-booking' );

	ob_start();
	?>
	<div class="bhela-bm-form-wrap" id="bhela-booking">
		<form class="bhela-bm-form" id="bhela-bm-form" novalidate>
			<div class="bhela-bm-layout">
				<div class="bhela-bm-main">
					<fieldset class="bhela-bm-step">
						<legend><span class="bhela-bm-step__num">১</span> তারিখ ও কেবিন বাছাই করুন</legend>
						<div class="bhela-bm-grid">
							<p class="bhela-bm-field">
								<label for="bm-date">ভ্রমণের তারিখ *</label>
								<input type="date" id="bm-date" name="date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
							</p>
							<p class="bhela-bm-field" style="align-self:end">
								<button type="button" class="bhela-bm-avail-btn" id="bm-check-avail">🔍 Availability চেক করুন</button>
							</p>
						</div>
						<div class="bhela-bm-avail" id="bm-avail-result" hidden></div>

						<div class="bhela-bm-cabins-head">
							<label>কেবিন সাজান <span>(গ্রুপ যেভাবে ভাগ হবে — একাধিক কেবিন যোগ করা যাবে)</span></label>
						</div>
						<div id="bm-cabin-rows"></div>
						<button type="button" class="bhela-bm-addcabin" id="bm-add-cabin">➕ আরেকটি কেবিন যোগ করুন</button>
						<p class="bhela-bm-childnote">👶 শিশু নীতিমালা: ০–৪ বছর ফ্রি · ৪–৮ বছর ৫০% · ৯+ বছর পূর্ণ রেট (বড় হিসেবে দিন)</p>
					</fieldset>

					<fieldset class="bhela-bm-step">
						<legend><span class="bhela-bm-step__num">২</span> আপনার তথ্য</legend>
						<div class="bhela-bm-grid">
							<p class="bhela-bm-field">
								<label for="bm-name">আপনার নাম *</label>
								<input type="text" id="bm-name" name="name" required autocomplete="name" placeholder="পুরো নাম">
							</p>
							<p class="bhela-bm-field">
								<label for="bm-phone">মোবাইল নম্বর *</label>
								<input type="tel" id="bm-phone" name="phone" required placeholder="01XXXXXXXXX" autocomplete="tel" inputmode="tel">
							</p>
						</div>
						<p class="bhela-bm-field">
							<label for="bm-email">ইমেইল <span>(ইনভয়েস পেতে — ঐচ্ছিক)</span></label>
							<input type="email" id="bm-email" name="email" autocomplete="email" placeholder="you@email.com">
						</p>
						<p class="bhela-bm-field">
							<label for="bm-message">বিশেষ নোট <span>(ঐচ্ছিক)</span></label>
							<textarea id="bm-message" name="message" rows="3" placeholder="যেমন: Full Boat Reservation, Corporate Tour, BBQ..."></textarea>
						</p>
					</fieldset>

					<div class="bhela-bm-response" id="bhela-bm-response" role="status" aria-live="polite"></div>
				</div>

				<aside class="bhela-bm-side">
					<div class="bhela-bm-summary">
						<h3>🧾 আপনার বুকিং</h3>
						<div class="bhela-bm-price" id="bhela-bm-price" hidden>
							<div id="bm-breakdown"></div>
							<div class="bhela-bm-price__row"><span>দিনের ধরন</span><strong id="bm-daytype">—</strong></div>
							<div class="bhela-bm-price__row"><span>মোট অতিথি</span><strong id="bm-guests-echo">—</strong></div>
							<div class="bhela-bm-price__row"><span>মোট</span><strong id="bm-total">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--save" id="bm-savings-row" hidden><span>আপনার সাশ্রয় 🎉</span><strong id="bm-savings">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--advance"><span>অগ্রিম (৫০%)</span><strong id="bm-advance">—</strong></div>
						</div>
						<p class="bhela-bm-empty" id="bhela-bm-empty">তারিখ ও কেবিন বাছাই করলে এখানে বিস্তারিত হিসাব দেখা যাবে।</p>
						<button type="submit" class="bhela-bm-submit" id="bhela-bm-submit"><span class="bhela-bm-submit__label">বুকিং রিকোয়েস্ট পাঠান →</span></button>
						<p class="bhela-bm-note">অগ্রিম পাওয়ার পরই বুকিং Confirmed হয়। আমাদের টিম ফোন/WhatsApp-এ যোগাযোগ করবে।</p>
						<ul class="bhela-bm-trust">
							<li>🛟 Life Jacket ও প্রশিক্ষিত ক্রু</li>
							<li>🔒 তথ্য সম্পূর্ণ গোপনীয়</li>
							<li>💳 bKash · Nagad · Bank</li>
						</ul>
					</div>
				</aside>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'bhela_booking_form', 'bhela_bm_booking_form_shortcode' );

/* ---------- Multi-cabin price calculation (server-side, authoritative) ---------- */

/**
 * $cabins: array of ['type' => key, 'adults' => n, 'c48' => n, 'c04' => n].
 * Returns array|WP_Error.
 */
function bhela_bm_calc_multi( $cabins, $date ) {
	$rates    = bhela_bm_get_rates();
	$settings = bhela_bm_get_settings();
	$day_type = bhela_bm_day_type( $date );

	$total   = 0;
	$regular_total = 0;
	$guests  = 0;
	$lines   = array();

	foreach ( (array) $cabins as $c ) {
		$type = sanitize_key( $c['type'] ?? '' );
		if ( ! isset( $rates[ $type ] ) ) {
			continue;
		}
		$adults = max( 0, (int) ( $c['adults'] ?? 0 ) );
		$c48    = max( 0, (int) ( $c['c48'] ?? 0 ) );
		$c04    = max( 0, (int) ( $c['c04'] ?? 0 ) );
		if ( $adults + $c48 + $c04 < 1 ) {
			continue;
		}
		$row  = $rates[ $type ];
		$rate = ( 'weekday' === $day_type ) ? (int) $row['weekday'] : (int) $row['regular'];
		$line_total   = $adults * $rate + (int) ceil( $c48 * $rate * 0.5 );
		$line_regular = $adults * (int) $row['regular'] + (int) ceil( $c48 * (int) $row['regular'] * 0.5 );

		$total         += $line_total;
		$regular_total += $line_regular;
		$guests        += $adults + $c48 + $c04;

		$who = $adults . ' বড়';
		if ( $c48 ) {
			$who .= ' + ' . $c48 . ' শিশু(৪–৮)';
		}
		if ( $c04 ) {
			$who .= ' + ' . $c04 . ' শিশু(০–৪)';
		}
		$lines[] = array(
			'label' => $row['label'],
			'who'   => $who,
			'total' => $line_total,
		);
	}

	if ( ! $lines ) {
		return new WP_Error( 'no_cabins', __( 'অন্তত একটি কেবিনে অতিথি সংখ্যা দিন।', 'bhela-booking' ) );
	}

	$advance = (int) ceil( $total * ( (float) $settings['advance_percent'] / 100 ) );

	return array(
		'day_type' => $day_type,
		'lines'    => $lines,
		'guests'   => $guests,
		'total'    => $total,
		'savings'  => max( 0, $regular_total - $total ),
		'advance'  => $advance,
		'due'      => $total - $advance,
	);
}

/* ---------- Submission processor ---------- */

function bhela_bm_process_submission( $data ) {
	$name    = sanitize_text_field( $data['name'] ?? '' );
	$phone   = sanitize_text_field( $data['phone'] ?? '' );
	$email   = sanitize_email( $data['email'] ?? '' );
	$date    = sanitize_text_field( $data['date'] ?? '' );
	$message = sanitize_textarea_field( $data['message'] ?? '' );
	$cabins  = json_decode( wp_unslash( $data['cabins'] ?? '' ), true );

	if ( ! $name || ! $phone || ! $date ) {
		return new WP_Error( 'missing', __( 'অনুগ্রহ করে নাম, মোবাইল নম্বর ও তারিখ পূরণ করুন।', 'bhela-booking' ) );
	}

	$price = is_array( $cabins ) ? bhela_bm_calc_multi( $cabins, $date ) : new WP_Error( 'no_cabins', 'no cabins' );
	if ( is_wp_error( $price ) ) {
		return new WP_Error( 'cabins', __( 'অন্তত একটি কেবিনে অতিথি সংখ্যা দিন।', 'bhela-booking' ) );
	}

	$post_id = wp_insert_post( array(
		'post_title'  => $name,
		'post_type'   => 'bhela_booking',
		'post_status' => 'publish',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save', __( 'দুঃখিত, তথ্য সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}

	$invoice_no = bhela_bm_next_invoice_number();

	// Human-readable cabin summary.
	$summary_parts = array();
	foreach ( $price['lines'] as $l ) {
		$summary_parts[] = $l['label'] . ' (' . $l['who'] . ')';
	}
	$cabin_summary = implode( ' + ', $summary_parts );

	update_post_meta( $post_id, '_bhela_phone', $phone );
	update_post_meta( $post_id, '_bhela_email', $email );
	update_post_meta( $post_id, '_bhela_travel_date', $date );
	update_post_meta( $post_id, '_bhela_cabin_type', $cabin_summary );
	update_post_meta( $post_id, '_bhela_cabins_json', wp_json_encode( $cabins, JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $post_id, '_bhela_guests', $price['guests'] );
	update_post_meta( $post_id, '_bhela_message', $message );
	update_post_meta( $post_id, '_bhela_status', 'pending' );
	update_post_meta( $post_id, '_bhela_invoice_no', $invoice_no );
	update_post_meta( $post_id, '_bhela_paid_amount', 0 );
	update_post_meta( $post_id, '_bhela_day_type', $price['day_type'] );
	update_post_meta( $post_id, '_bhela_per_person', 0 );
	update_post_meta( $post_id, '_bhela_total', $price['total'] );
	update_post_meta( $post_id, '_bhela_advance', $price['advance'] );
	update_post_meta( $post_id, '_bhela_manual_price', '1' ); // preserve multi-cabin total on admin save

	bhela_bm_email_admin_new( $post_id );
	if ( $email ) {
		bhela_bm_email_customer( $post_id, 'request' );
	}

	$settings = bhela_bm_get_settings();
	$wa_num   = preg_replace( '/[^0-9]/', '', $settings['whatsapp'] );
	$wa_text  = sprintf(
		"আসসালামু আলাইকুম। আমি ভেলা হাউসবোট বুকিং করতে চাই।\n\n🧾 Booking No: %s\nনাম: %s\nমোবাইল: %s\nতারিখ: %s\nকেবিন: %s\nঅতিথি: %d জন\nমোট: %s | অগ্রিম (৫০%%): %s",
		$invoice_no,
		$name,
		$phone,
		$date,
		$cabin_summary,
		$price['guests'],
		bhela_bm_money( $price['total'] ),
		bhela_bm_money( $price['advance'] )
	);

	return array(
		'booking_id'   => $post_id,
		'invoice_no'   => $invoice_no,
		'whatsapp_url' => 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_text ),
		'invoice_url'  => bhela_bm_invoice_url( $post_id ),
	);
}

/** AJAX: submit booking. */
function bhela_bm_ajax_submit() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );
	$result = bhela_bm_process_submission( wp_unslash( $_POST ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	$msg = sprintf(
		__( 'ধন্যবাদ! আপনার বুকিং রিকোয়েস্ট (No: %s) জমা হয়েছে। আমাদের টিম দ্রুত যোগাযোগ করবে।', 'bhela-booking' ),
		$result['invoice_no']
	);
	wp_send_json_success( array_merge( $result, array( 'message' => $msg ) ) );
}
add_action( 'wp_ajax_bhela_bm_submit', 'bhela_bm_ajax_submit' );
add_action( 'wp_ajax_nopriv_bhela_bm_submit', 'bhela_bm_ajax_submit' );

/** AJAX: room availability check (from Trip Calendar). */
function bhela_bm_ajax_availability() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );
	$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
	if ( ! $date ) {
		wp_send_json_error( array( 'message' => 'আগে তারিখ বাছাই করুন।' ) );
	}
	$trips    = function_exists( 'bhela_bm_get_trips' ) ? bhela_bm_get_trips() : array();
	$statuses = function_exists( 'bhela_bm_trip_statuses' ) ? bhela_bm_trip_statuses() : array();
	foreach ( $trips as $t ) {
		if ( $t['date'] === $date ) {
			$st = $statuses[ $t['status'] ] ?? null;
			wp_send_json_success( array(
				'status' => $t['status'],
				'label'  => $st ? $st['short'] : $t['status'],
				'color'  => $st ? $st['color'] : '#1a7f37',
				'trip'   => $t['label'],
				'note'   => $t['note'],
			) );
		}
	}
	wp_send_json_success( array(
		'status' => 'unknown',
		'label'  => 'এই তারিখে নির্ধারিত গ্রুপ ট্রিপ নেই',
		'color'  => '#996800',
		'trip'   => '',
		'note'   => 'Full Boat/কাস্টম ট্রিপের জন্য WhatsApp-এ যোগাযোগ করুন — রিকোয়েস্ট পাঠালে আমরা কনফার্ম করব।',
	) );
}
add_action( 'wp_ajax_bhela_bm_availability', 'bhela_bm_ajax_availability' );
add_action( 'wp_ajax_nopriv_bhela_bm_availability', 'bhela_bm_ajax_availability' );
