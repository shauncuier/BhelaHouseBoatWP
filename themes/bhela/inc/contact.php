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

/**
 * Normalise a Bangladeshi mobile to 01XXXXXXXXX, or '' if it is not valid.
 * Defers to the booking plugin when active so both forms behave identically;
 * the fallback keeps the contact form working with the theme alone.
 */
function bhela_normalize_mobile( $raw ) {
	if ( function_exists( 'bhela_bm_normalize_mobile' ) ) {
		return bhela_bm_normalize_mobile( $raw );
	}
	$digits = preg_replace( '/[^0-9]/', '', (string) $raw );
	if ( 0 === strpos( $digits, '880' ) ) {
		$digits = substr( $digits, 3 );
	} elseif ( 0 === strpos( $digits, '00880' ) ) {
		$digits = substr( $digits, 5 );
	}
	if ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
		$digits = '0' . $digits;
	}
	return preg_match( '/^01[3-9][0-9]{8}$/', $digits ) ? $digits : '';
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

/** One label/value row of the summary table. */
function bhela_contact_email_row( $label, $value, $is_link = '' ) {
	$val = $is_link
		? '<a href="' . esc_url( $is_link ) . '" style="color:#137A74;text-decoration:none;font-weight:700;">' . esc_html( $value ) . '</a>'
		: esc_html( $value );
	return '<tr>'
		. '<td style="padding:10px 0;font-size:13.5px;color:#5E7472;border-bottom:1px solid #EEF2F1;white-space:nowrap;">' . esc_html( $label ) . '</td>'
		. '<td style="padding:10px 0;text-align:right;font-size:14.5px;color:#0A2A2F;border-bottom:1px solid #EEF2F1;">' . $val . '</td>'
		. '</tr>';
}

/** Email-safe pill button. */
function bhela_contact_email_btn( $url, $text, $bg ) {
	return '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;background:' . esc_attr( $bg )
		. ';color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 24px;border-radius:999px;margin:4px 5px 4px 0;">'
		. $text . '</a>';
}

/**
 * Branded HTML notification for the owner. Matches the booking engine's email
 * design so every message from the site looks like one family, and leads with
 * the actions the owner actually takes: call, WhatsApp, or reply.
 */
function bhela_contact_email_html( $name, $phone, $email, $subj_label, $message ) {
	$site   = get_bloginfo( 'name' );
	$wa_num = '880' . substr( $phone, 1 ); // phone is already normalised to 01XXXXXXXXX
	$when   = date_i18n( 'j M Y · g:i a', current_time( 'timestamp' ) );

	$rows  = bhela_contact_email_row( 'নাম', $name );
	$rows .= bhela_contact_email_row( 'মোবাইল', $phone, 'tel:' . $phone );
	if ( $email ) {
		$rows .= bhela_contact_email_row( 'ইমেইল', $email, 'mailto:' . $email );
	}
	$rows .= bhela_contact_email_row( 'বিষয়', $subj_label );
	$rows .= bhela_contact_email_row( 'সময়', $when );

	$buttons  = bhela_contact_email_btn( 'tel:' . $phone, '📞 কল করুন', '#137A74' );
	$buttons .= bhela_contact_email_btn( 'https://wa.me/' . $wa_num, '💬 WhatsApp', '#25D366' );
	if ( $email ) {
		$buttons .= bhela_contact_email_btn( 'mailto:' . $email, '✉️ রিপ্লাই', '#FF7A3D' );
	}

	ob_start();
	?>
<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#EEF2F1;font-family:'Noto Sans Bengali','Hind Siliguri','Segoe UI',Tahoma,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF2F1;padding:24px 12px;">
		<tr><td align="center">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">

				<tr><td style="background:#0A2A2F;padding:26px 32px;text-align:center;">
					<div style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:1px;">🛶 BHELA</div>
					<div style="font-size:12px;color:#6FC7BF;letter-spacing:2px;text-transform:uppercase;margin-top:4px;">The Haor Exclusive</div>
				</td></tr>

				<tr><td style="background:#137A74;padding:12px;text-align:center;color:#ffffff;font-weight:700;font-size:16px;">
					📨 নতুন যোগাযোগ বার্তা — <?php echo esc_html( $subj_label ); ?>
				</td></tr>

				<tr><td style="padding:26px 32px;">
					<p style="margin:0 0 18px;font-size:14.5px;color:#22403E;line-height:1.9;">
						ওয়েবসাইটের যোগাযোগ ফর্ম থেকে <strong><?php echo esc_html( $name ); ?></strong> একটি বার্তা পাঠিয়েছেন।
					</p>

					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF8F2;border-radius:12px;padding:4px 18px;">
						<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput -- rows are escaped in the row helper. ?>
					</table>

					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background:#F4F7F6;border-radius:12px;border-left:4px solid #FF7A3D;">
						<tr><td style="padding:16px 20px;">
							<div style="font-size:12.5px;color:#5E7472;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">বার্তা</div>
							<div style="font-size:14.5px;color:#22403E;line-height:1.9;"><?php echo nl2br( esc_html( $message ) ); ?></div>
						</td></tr>
					</table>

					<div style="text-align:center;margin:24px 0 4px;">
						<?php echo $buttons; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped helpers. ?>
					</div>
					<p style="margin:14px 0 0;text-align:center;font-size:12.5px;color:#5E7472;">
						এই ইমেইলে সরাসরি Reply করলে বার্তাটি অতিথির কাছে যাবে।
					</p>
				</td></tr>

				<tr><td style="background:#0A2A2F;padding:18px 32px;text-align:center;">
					<div style="font-size:12.5px;color:#DCEBE9;line-height:1.9;">
						<?php echo esc_html( $site ); ?><br>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#6FC7BF;text-decoration:none;"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a>
					</div>
				</td></tr>

			</table>
			<div style="font-size:11px;color:#8aa19f;margin-top:14px;">© <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $site ); ?></div>
		</td></tr>
	</table>
</body>
</html>
	<?php
	return ob_get_clean();
}

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

	// Must be a real BD mobile — it is how we call the guest back.
	$normalized = bhela_normalize_mobile( $phone );
	if ( '' === $normalized ) {
		wp_send_json_error( array( 'message' => __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু (যেমন ০১৭১২৩৪৫৬৭৮)।', 'bhela' ) ) );
	}
	$phone = $normalized;

	if ( $email && ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'ইমেইল ঠিকানাটি সঠিক নয়।', 'bhela' ) ) );
	}

	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	$subjects   = bhela_contact_subjects();
	$subj_label = $subjects[ $subject ] ?? $subjects['other'];
	$body       = bhela_contact_email_html( $name, $phone, $email, $subj_label, $message );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( $email ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}

	$sent = wp_mail(
		bhela_contact_recipient(),
		sprintf( '📨 %s — %s (%s)', $subj_label, $name, $phone ),
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
