<?php
/**
 * Frontend: booking form shortcode + AJAX submission.
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
		'whatsapp'       => preg_replace( '/[^0-9]/', '', $settings['whatsapp'] ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bhela_bm_enqueue_assets', 20 );

/* ---------- Shortcode: [bhela_booking_form] ---------- */

function bhela_bm_booking_form_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	wp_enqueue_script( 'bhela-bm-booking' );

	$rates = bhela_bm_get_rates();
	ob_start();
	?>
	<div class="bhela-bm-form-wrap" id="bhela-booking">
		<form class="bhela-bm-form" id="bhela-bm-form" novalidate>
			<div class="bhela-bm-layout">
				<div class="bhela-bm-main">
					<fieldset class="bhela-bm-step">
						<legend><span class="bhela-bm-step__num">১</span> ট্রিপ বাছাই করুন</legend>
						<div class="bhela-bm-grid">
							<p class="bhela-bm-field">
								<label for="bm-date">ভ্রমণের তারিখ *</label>
								<input type="date" id="bm-date" name="date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
							</p>
							<p class="bhela-bm-field">
								<label for="bm-guests">অতিথি সংখ্যা *</label>
								<select id="bm-guests" name="guests" required>
									<?php for ( $i = 1; $i <= 40; $i++ ) : ?>
										<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, 4 ); ?>><?php echo esc_html( $i ); ?> জন</option>
									<?php endfor; ?>
								</select>
							</p>
						</div>
						<p class="bhela-bm-field">
							<label for="bm-cabin">কেবিন *</label>
							<select id="bm-cabin" name="cabin" required>
								<option value="">— কেবিন বাছাই করুন —</option>
								<?php foreach ( $rates as $key => $row ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" data-sharing="<?php echo esc_attr( $row['sharing'] ); ?>">
										<?php echo esc_html( $row['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>
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
							<textarea id="bm-message" name="message" rows="3" placeholder="যেমন: Full Boat Reservation, Corporate Tour, BBQ, শিশুসহ..."></textarea>
						</p>
					</fieldset>

					<div class="bhela-bm-response" id="bhela-bm-response" role="status" aria-live="polite"></div>
				</div>

				<aside class="bhela-bm-side">
					<div class="bhela-bm-summary">
						<h3>🧾 আপনার বুকিং</h3>
						<div class="bhela-bm-price" id="bhela-bm-price" hidden>
							<div class="bhela-bm-price__row"><span>দিনের ধরন</span><strong id="bm-daytype">—</strong></div>
							<div class="bhela-bm-price__row"><span>জনপ্রতি</span><strong id="bm-per">—</strong></div>
							<div class="bhela-bm-price__row"><span>মোট (<span id="bm-guests-echo">—</span> জন)</span><strong id="bm-total">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--save" id="bm-savings-row" hidden><span>আপনার সাশ্রয় 🎉</span><strong id="bm-savings">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--advance"><span>অগ্রিম (৫০%)</span><strong id="bm-advance">—</strong></div>
						</div>
						<p class="bhela-bm-empty" id="bhela-bm-empty">তারিখ, কেবিন ও অতিথি বাছাই করলে এখানে রেট দেখা যাবে।</p>
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

/* ---------- Submission processor ---------- */

function bhela_bm_process_submission( $data ) {
	$name    = sanitize_text_field( $data['name'] ?? '' );
	$phone   = sanitize_text_field( $data['phone'] ?? '' );
	$email   = sanitize_email( $data['email'] ?? '' );
	$date    = sanitize_text_field( $data['date'] ?? '' );
	$cabin   = sanitize_text_field( $data['cabin'] ?? '' );
	$guests  = max( 1, (int) ( $data['guests'] ?? 1 ) );
	$message = sanitize_textarea_field( $data['message'] ?? '' );

	if ( ! $name || ! $phone || ! $date ) {
		return new WP_Error( 'missing', __( 'অনুগ্রহ করে নাম, মোবাইল নম্বর ও তারিখ পূরণ করুন।', 'bhela-booking' ) );
	}

	$cabin_key = bhela_bm_match_cabin( $cabin );
	$price     = $cabin_key ? bhela_bm_calc_price( $cabin_key, $guests, $date ) : null;

	$post_id = wp_insert_post( array(
		'post_title'  => $name,
		'post_type'   => 'bhela_booking',
		'post_status' => 'publish',
	), true );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save', __( 'দুঃখিত, তথ্য সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}

	$invoice_no = bhela_bm_next_invoice_number();
	$rates      = bhela_bm_get_rates();

	update_post_meta( $post_id, '_bhela_phone', $phone );
	update_post_meta( $post_id, '_bhela_email', $email );
	update_post_meta( $post_id, '_bhela_travel_date', $date );
	update_post_meta( $post_id, '_bhela_cabin_type', $cabin_key ? $rates[ $cabin_key ]['label'] : $cabin );
	update_post_meta( $post_id, '_bhela_cabin_key', $cabin_key );
	update_post_meta( $post_id, '_bhela_guests', $guests );
	update_post_meta( $post_id, '_bhela_message', $message );
	update_post_meta( $post_id, '_bhela_status', 'pending' );
	update_post_meta( $post_id, '_bhela_invoice_no', $invoice_no );
	update_post_meta( $post_id, '_bhela_paid_amount', 0 );

	if ( $price && ! is_wp_error( $price ) ) {
		update_post_meta( $post_id, '_bhela_day_type', $price['day_type'] );
		update_post_meta( $post_id, '_bhela_per_person', $price['per_person'] );
		update_post_meta( $post_id, '_bhela_total', $price['total'] );
		update_post_meta( $post_id, '_bhela_advance', $price['advance'] );
	}

	bhela_bm_email_admin_new( $post_id );
	if ( $email ) {
		bhela_bm_email_customer( $post_id, 'request' );
	}

	$settings = bhela_bm_get_settings();
	$wa_num   = preg_replace( '/[^0-9]/', '', $settings['whatsapp'] );
	$wa_text  = sprintf(
		"আসসালামু আলাইকুম। আমি ভেলা হাউসবোট বুকিং করতে চাই।\n\n🧾 Booking No: %s\nনাম: %s\nমোবাইল: %s\nতারিখ: %s\nকেবিন: %s\nঅতিথি: %d জন%s",
		$invoice_no,
		$name,
		$phone,
		$date,
		$cabin_key ? $rates[ $cabin_key ]['label'] : $cabin,
		$guests,
		( $price && ! is_wp_error( $price ) ) ? sprintf( "\nমোট: %s | অগ্রিম (৫০%%): %s", bhela_bm_money( $price['total'] ), bhela_bm_money( $price['advance'] ) ) : ''
	);

	return array(
		'booking_id'   => $post_id,
		'invoice_no'   => $invoice_no,
		'price'        => ( $price && ! is_wp_error( $price ) ) ? $price : null,
		'whatsapp_url' => 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_text ),
		'invoice_url'  => bhela_bm_invoice_url( $post_id ),
	);
}

/** AJAX endpoint. */
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
