<?php
/**
 * Contact form — [bhela_contact_form] shortcode + AJAX handler.
 *
 * Messages are emailed to the owner (the booking plugin's notification address
 * when available, otherwise the site admin). Nothing is stored in the database,
 * so there is no extra personal data at rest.
 *
 * Security mirrors the booking form: nonce check, hidden honeypot field and a
 * per-IP throttle, because every submission sends mail.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where contact messages are delivered. */
function bhela_contact_recipient() {
	if ( function_exists( 'bhela_bm_get_settings' ) ) {
		$s = bhela_bm_get_settings();
		if ( ! empty( $s['notify_email'] ) && is_email( $s['notify_email'] ) ) {
			return $s['notify_email'];
		}
		if ( ! empty( $s['email'] ) && is_email( $s['email'] ) ) {
			return $s['email'];
		}
	}
	$themed = bhela_contact( 'email' );
	return is_email( $themed ) ? $themed : get_option( 'admin_email' );
}

/** Subject options offered in the form. */
function bhela_contact_subjects() {
	return array(
		'booking'   => 'বুকিং সংক্রান্ত',
		'group'     => 'গ্রুপ / ফুল বোট রিজার্ভেশন',
		'corporate' => 'কর্পোরেট / ইভেন্ট',
		'feedback'  => 'মতামত / অভিযোগ',
		'other'     => 'অন্যান্য',
	);
}

/** [bhela_contact_form] */
function bhela_contact_form_shortcode() {
	wp_enqueue_script( 'bhela-theme' );
	wp_localize_script( 'bhela-theme', 'bhelaContact', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'bhela_contact' ),
	) );

	ob_start();
	?>
	<form class="bhela-contact-form" id="bhela-contact-form" novalidate>
		<div class="bhela-contact-form__hp" aria-hidden="true">
			<label>Leave this field empty<input type="text" name="bhela_hp" tabindex="-1" autocomplete="off"></label>
		</div>
		<div class="bhela-contact-form__grid">
			<p class="bhela-contact-form__field">
				<label for="bc-name"><?php esc_html_e( 'আপনার নাম', 'bhela' ); ?> *</label>
				<input type="text" id="bc-name" name="name" required maxlength="120" autocomplete="name">
			</p>
			<p class="bhela-contact-form__field">
				<label for="bc-phone"><?php esc_html_e( 'ফোন নম্বর', 'bhela' ); ?> *</label>
				<input type="tel" id="bc-phone" name="phone" required maxlength="30" autocomplete="tel" placeholder="01XXXXXXXXX">
			</p>
			<p class="bhela-contact-form__field">
				<label for="bc-email"><?php esc_html_e( 'ইমেইল', 'bhela' ); ?> <span><?php esc_html_e( '(ঐচ্ছিক)', 'bhela' ); ?></span></label>
				<input type="email" id="bc-email" name="email" maxlength="120" autocomplete="email">
			</p>
			<p class="bhela-contact-form__field">
				<label for="bc-subject"><?php esc_html_e( 'বিষয়', 'bhela' ); ?></label>
				<select id="bc-subject" name="subject">
					<?php foreach ( bhela_contact_subjects() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<p class="bhela-contact-form__field">
			<label for="bc-message"><?php esc_html_e( 'আপনার বার্তা', 'bhela' ); ?> *</label>
			<textarea id="bc-message" name="message" rows="5" required maxlength="2000" placeholder="<?php esc_attr_e( 'কতজন যাবেন, কোন তারিখে — সংক্ষেপে লিখুন', 'bhela' ); ?>"></textarea>
		</p>
		<div class="bhela-contact-form__foot">
			<button type="submit" class="btn btn--cta" id="bc-submit"><?php esc_html_e( 'বার্তা পাঠান', 'bhela' ); ?> →</button>
			<span class="bhela-contact-form__note"><?php esc_html_e( 'সাধারণত ২৪ ঘণ্টার মধ্যে উত্তর দেওয়া হয়।', 'bhela' ); ?></span>
		</div>
		<div class="bhela-contact-form__msg" id="bc-msg" role="status" hidden></div>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'bhela_contact_form', 'bhela_contact_form_shortcode' );

/** AJAX: handle a contact submission. */
function bhela_contact_submit() {
	check_ajax_referer( 'bhela_contact', 'nonce' );

	// Honeypot — bots fill every field; real visitors never see this one.
	if ( ! empty( $_POST['bhela_hp'] ) ) {
		wp_send_json_error( array( 'message' => __( 'দুঃখিত, বার্তাটি পাঠানো যায়নি।', 'bhela' ) ) );
	}

	// Per-IP throttle: each submission sends an email.
	$ip   = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key  = 'bhela_contact_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		wp_send_json_error( array( 'message' => __( 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।', 'bhela' ) ) );
	}

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$subject = sanitize_key( wp_unslash( $_POST['subject'] ?? 'other' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

	if ( '' === $name || '' === $phone || '' === $message ) {
		wp_send_json_error( array( 'message' => __( 'নাম, ফোন ও বার্তা লিখুন।', 'bhela' ) ) );
	}
	if ( $email && ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'ইমেইল ঠিকানাটি সঠিক নয়।', 'bhela' ) ) );
	}

	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	$subjects   = bhela_contact_subjects();
	$subj_label = $subjects[ $subject ] ?? $subjects['other'];

	$body  = '<h2>নতুন বার্তা — ' . esc_html( $subj_label ) . '</h2>';
	$body .= '<p><strong>নাম:</strong> ' . esc_html( $name ) . '</p>';
	$body .= '<p><strong>ফোন:</strong> ' . esc_html( $phone ) . '</p>';
	if ( $email ) {
		$body .= '<p><strong>ইমেইল:</strong> ' . esc_html( $email ) . '</p>';
	}
	$body .= '<p><strong>বার্তা:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
	$body .= '<hr><p style="color:#5E7472;font-size:12px">' . esc_html( get_bloginfo( 'name' ) ) . ' — ' . esc_html( home_url( '/' ) ) . '</p>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( $email ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}

	$sent = wp_mail(
		bhela_contact_recipient(),
		sprintf( '[যোগাযোগ] %s — %s', $subj_label, $name ),
		$body,
		$headers
	);

	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => __( 'বার্তা পাঠানো যায়নি — সরাসরি ফোন বা WhatsApp-এ যোগাযোগ করুন।', 'bhela' ) ) );
	}

	wp_send_json_success( array(
		'message' => __( 'ধন্যবাদ! আপনার বার্তা পৌঁছেছে — আমরা দ্রুত যোগাযোগ করব।', 'bhela' ),
	) );
}
add_action( 'wp_ajax_bhela_contact_submit', 'bhela_contact_submit' );
add_action( 'wp_ajax_nopriv_bhela_contact_submit', 'bhela_contact_submit' );
