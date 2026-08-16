<?php
/**
 * Email notifications — branded HTML for customers, plain text for admin.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain-text booking summary (admin email + fallback). */
function bhela_bm_booking_summary_text( $booking_id ) {
	$m = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};
	$lines   = array();
	$lines[] = 'Booking / Invoice No: ' . $m( '_bhela_invoice_no' );
	$lines[] = 'Name: ' . get_the_title( $booking_id );
	$lines[] = 'Phone: ' . $m( '_bhela_phone' );
	if ( $m( '_bhela_email' ) ) {
		$lines[] = 'Email: ' . $m( '_bhela_email' );
	}
	$lines[] = 'Travel Date: ' . $m( '_bhela_travel_date' );
	$lines[] = 'Cabin: ' . $m( '_bhela_cabin_type' );
	$lines[] = 'Guests: ' . $m( '_bhela_guests' );
	if ( $m( '_bhela_total' ) ) {
		$lines[] = 'Per Person: ' . bhela_bm_money( $m( '_bhela_per_person' ) ) . ' (' . bhela_bm_booking_day_type( $booking_id ) . ')';
		$lines[] = 'Total: ' . bhela_bm_money( $m( '_bhela_total' ) );
		$lines[] = 'Advance (' . bhela_bm_advance_pct( $m( '_bhela_advance' ), $m( '_bhela_total' ) ) . '%): ' . bhela_bm_money( $m( '_bhela_advance' ) );
		$lines[] = 'Paid: ' . bhela_bm_money( $m( '_bhela_paid_amount' ) );
	}
	if ( $m( '_bhela_full_boat' ) ) {
		$lines[] = '★ FULL BOAT — custom quote requested';
	}
	if ( $m( '_bhela_requested_price' ) ) {
		$lines[] = '★ Requested price / budget: ' . bhela_bm_money( $m( '_bhela_requested_price' ) );
	}
	if ( $m( '_bhela_discount_msg' ) ) {
		$lines[] = 'Discount note: ' . $m( '_bhela_discount_msg' );
	}
	if ( $m( '_bhela_message' ) ) {
		$lines[] = 'Note: ' . $m( '_bhela_message' );
	}
	return implode( "\n", $lines );
}

/** Admin: send a test email to the owner notification address. */
function bhela_bm_email_test_send() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_email_test' );
	$s   = bhela_bm_get_settings();
	$to  = $s['notify_email'] ? $s['notify_email'] : ( $s['email'] ? $s['email'] : get_option( 'admin_email' ) );
	$ok  = wp_mail(
		$to,
		'BHELA — Test Email ✅',
		"This is a test from BHELA Booking settings.\nIf you received this, email notifications work.",
		array( 'From: ' . ( $s['email_from_name'] ? $s['email_from_name'] : $s['business_name'] ) . ' <' . ( $s['email'] ? $s['email'] : get_option( 'admin_email' ) ) . '>' )
	);
	set_transient( 'bhela_bm_email_test_result', array( 'ok' => (bool) $ok, 'to' => $to ), 60 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings#bhela-email' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_email_test', 'bhela_bm_email_test_send' );

/** Notify site admin of a new booking request (plain text — functional). */
function bhela_bm_email_admin_new( $booking_id ) {
	$settings = bhela_bm_get_settings();
	if ( empty( $settings['email_enabled'] ) || empty( $settings['email_admin_new'] ) ) {
		return false;
	}
	$to = $settings['notify_email'] ? $settings['notify_email'] : ( $settings['email'] ? $settings['email'] : get_option( 'admin_email' ) );
	$subject  = sprintf( 'BHELA: New Booking Request — %s (%s)', get_the_title( $booking_id ), get_post_meta( $booking_id, '_bhela_invoice_no', true ) );
	$body     = "নতুন বুকিং রিকোয়েস্ট এসেছে:\n\n" . bhela_bm_booking_summary_text( $booking_id );
	$body    .= "\n\nAdmin: " . admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
	$body    .= "\nInvoice: " . bhela_bm_invoice_url( $booking_id );
	wp_mail( $to, $subject, $body );
}

/** One row of the HTML summary table. */
function bhela_bm_email_row( $label, $value, $strong = false, $color = '' ) {
	$style_v = 'padding:9px 0;text-align:right;font-size:14px;color:' . ( $color ? $color : '#0A2A2F' ) . ';' . ( $strong ? 'font-weight:700;font-size:16px;' : '' );
	return '<tr><td style="padding:9px 0;font-size:13.5px;color:#5E7472;border-bottom:1px solid #EEF2F1;">' . esc_html( $label ) . '</td>'
		. '<td style="border-bottom:1px solid #EEF2F1;' . $style_v . '">' . esc_html( $value ) . '</td></tr>';
}

/** Email-safe button. */
function bhela_bm_email_btn( $url, $text, $bg ) {
	return '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;background:' . $bg . ';color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 26px;border-radius:999px;margin:4px 6px 4px 0;">' . $text . '</a>';
}

/** Build the branded HTML customer email. $type: 'request' | 'confirmed'. */
function bhela_bm_email_customer_html( $booking_id, $type ) {
	$settings = bhela_bm_get_settings();
	$m        = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};
	$name       = get_the_title( $booking_id );
	$invoice_no = $m( '_bhela_invoice_no' );
	$total      = (int) $m( '_bhela_total' );
	$advance    = (int) $m( '_bhela_advance' );
	// Shared with the invoice deliberately: this email and that page are read side
	// by side, and two copies of the same arithmetic is how they drift apart.
	$bal        = bhela_bm_balance( $total, $m( '_bhela_paid_amount' ) );
	$paid       = $bal['paid'];
	$due        = $bal['due'];
	$inv_url    = bhela_bm_invoice_url( $booking_id );
	$wa_url     = bhela_bm_wa_url( $settings['whatsapp'], 'আসসালামু আলাইকুম। আমার বুকিং নম্বর: ' . $invoice_no );

	if ( 'completed' === $type ) {
		$banner_bg   = '#0E6E6B';
		$banner_text = '🙏 ধন্যবাদ!';
		$intro       = 'প্রিয় ' . esc_html( $name ) . ', ভেলার সাথে হাওর ভ্রমণের জন্য ধন্যবাদ! 🛶 আপনার অভিজ্ঞতা আমাদের কাছে অনেক মূল্যবান — নিচের বাটনে ক্লিক করে কয়েক মিনিটেই একটি রিভিউ ও ট্রিপের ছবি শেয়ার করতে পারেন।';
	} elseif ( 'confirmed' === $type ) {
		$banner_bg   = '#1a7f37';
		$banner_text = '✅ বুকিং কনফার্মড!';
		$intro       = 'প্রিয় ' . esc_html( $name ) . ', আপনার ভেলা হাউসবোট বুকিং নিশ্চিত হয়েছে! 🎉 নিচে আপনার ট্রিপের বিস্তারিত দেওয়া হলো।';
	} else {
		$banner_bg   = '#b45309';
		$banner_text = '🛶 বুকিং রিকোয়েস্ট গৃহীত';
		$intro       = 'প্রিয় ' . esc_html( $name ) . ', আপনার বুকিং রিকোয়েস্ট আমরা পেয়েছি। আমাদের টিম শীঘ্রই ফোন/WhatsApp-এ যোগাযোগ করবে। <strong>অগ্রিম পরিশোধের পর বুকিং Confirmed হবে।</strong>';
	}

	$rows  = bhela_bm_email_row( 'বুকিং নম্বর', $invoice_no, true );
	$rows .= bhela_bm_email_row( 'ভ্রমণের তারিখ', $m( '_bhela_travel_date' ) . ' (২ দিন ১ রাত)' );
	$booking_lines = json_decode( (string) $m( '_bhela_lines' ), true );
	if ( is_array( $booking_lines ) && $booking_lines ) {
		foreach ( $booking_lines as $bl ) {
			$rate_txt = isset( $bl['rate'] ) ? bhela_bm_money( $bl['rate'] ) . '/জন' . ( ! empty( $bl['c48'] ) ? ' (শিশু ৪–৮: ' . bhela_bm_money( (int) bhela_bm_get_settings()['child_fee'] ) . '/জন)' : '' ) : '';
			$rows    .= bhela_bm_email_row( $bl['label'], $bl['who'] . ( $rate_txt ? ' — ' . $rate_txt : '' ) . ' = ' . bhela_bm_money( (int) ( $bl['total'] ?? 0 ) ) );
		}
	} else {
		$rows .= bhela_bm_email_row( 'কেবিন', $m( '_bhela_cabin_type' ) );
	}
	$rows .= bhela_bm_email_row( 'অতিথি', $m( '_bhela_guests' ) . ' জন' );
	if ( $total ) {
		if ( (int) $m( '_bhela_per_person' ) > 0 ) {
			$rows .= bhela_bm_email_row( 'জনপ্রতি', bhela_bm_money( $m( '_bhela_per_person' ) ) );
		}
		$rows .= bhela_bm_email_row( 'মোট', bhela_bm_money( $total ), true );
		$rows .= bhela_bm_email_row( 'অগ্রিম (' . bhela_bm_advance_pct( $advance, $total ) . '%)', bhela_bm_money( $advance ), true, '#E5601F' );
		$rows .= bhela_bm_email_row( 'পরিশোধিত', bhela_bm_money( $paid ), false, '#1a7f37' );
		$rows .= bhela_bm_email_row( 'বাকি', bhela_bm_money( $due ), true, '#b32d2e' );
	}

	$boarding = ( 'confirmed' === $type )
		? '<p style="margin:18px 0 0;padding:14px 16px;background:#EBF7EF;border-radius:10px;font-size:13.5px;color:#14532d;line-height:1.8;">📌 <strong>রিপোর্টিং:</strong> Anwarpur Ghat — নির্ধারিত সময় ফোনে জানানো হবে।<br>💵 বাকি টাকা অনবোর্ড হওয়ার সময় পরিশোধযোগ্য।</p>'
		: '';

	ob_start();
	?>
<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<?php // 'Nirmala UI' before 'Segoe UI': mail clients strip webfonts, so the two
      // Bengali faces above are only available if the reader happens to have
      // them installed. Without a Bengali-capable fallback the stack lands on
      // Segoe UI, which has no ৳ — and every taka figure in the mail gets a
      // substituted, undersized symbol. Nirmala UI ships with Windows. ?>
<body style="margin:0;padding:0;background:#EEF2F1;font-family:'Noto Sans Bengali','Hind Siliguri','Nirmala UI','Segoe UI',Tahoma,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF2F1;padding:24px 12px;">
		<tr><td align="center">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
				<!-- Header -->
				<tr><td style="background:#0A2A2F;padding:28px 32px;text-align:center;">
					<div style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:1px;">🛶 BHELA</div>
					<div style="font-size:12px;color:#6FC7BF;letter-spacing:2px;text-transform:uppercase;margin-top:4px;">The Haor Exclusive</div>
				</td></tr>
				<!-- Status banner -->
				<tr><td style="background:<?php echo esc_attr( $banner_bg ); ?>;padding:12px;text-align:center;color:#ffffff;font-weight:700;font-size:16px;">
					<?php echo $banner_text; ?>
				</td></tr>
				<!-- Body -->
				<tr><td style="padding:28px 32px;">
					<p style="margin:0 0 18px;font-size:14.5px;color:#22403E;line-height:1.9;"><?php echo $intro; ?></p>
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF8F2;border-radius:12px;padding:6px 18px;">
						<?php echo $rows; ?>
					</table>
					<?php echo $boarding; ?>
					<div style="text-align:center;margin:24px 0 6px;">
						<?php if ( 'completed' === $type && function_exists( 'bhela_bm_review_url' ) ) : ?>
							<?php echo bhela_bm_email_btn( bhela_bm_review_url( $booking_id ), '⭐ রিভিউ ও ছবি দিন', '#E5601F' ); ?>
						<?php endif; ?>
						<?php echo bhela_bm_email_btn( $inv_url, '🧾 ইনভয়েস দেখুন / প্রিন্ট করুন', '#137A74' ); ?>
						<?php echo bhela_bm_email_btn( $wa_url, '💬 WhatsApp-এ যোগাযোগ', '#25D366' ); ?>
					</div>
					<!-- Payment info -->
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background:#F4F7F6;border-radius:12px;">
						<tr><td style="padding:16px 20px;font-size:13px;color:#22403E;line-height:2;">
							<strong style="color:#137A74;">💳 পেমেন্ট মাধ্যম</strong><br>
							Bangla QR (bKash/Bank App): <strong><?php echo esc_html( $settings['bkash_number'] ); ?></strong><br>
							Nagad: <strong><?php echo esc_html( $settings['nagad_number'] ); ?></strong><br>
							<span style="color:#5E7472;">পেমেন্ট QR কোড ইনভয়েসে দেওয়া আছে — স্ক্যান করে পেমেন্ট করুন এবং Transaction ID টি WhatsApp-এ পাঠান।</span>
						</td></tr>
					</table>
				</td></tr>
				<!-- Footer -->
				<tr><td style="background:#0A2A2F;padding:20px 32px;text-align:center;">
					<div style="font-size:13px;color:#DCEBE9;line-height:2;">
						📞 <?php echo esc_html( $settings['phone_1'] ); ?>, <?php echo esc_html( $settings['phone_2'] ); ?><br>
						✉️ <?php echo esc_html( $settings['email'] ); ?> &nbsp;·&nbsp; 🌐 <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#DCEBE9;text-decoration:none;"><?php echo esc_html( preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ); ?></a><br>
						📍 <?php echo esc_html( $settings['address'] ); ?>
					</div>
					<div style="font-size:12px;color:#F5C97B;margin-top:10px;font-style:italic;">"<?php echo esc_html( $settings['business_tagline'] ); ?>"</div>
				</td></tr>
			</table>
			<div style="font-size:11px;color:#8aa19f;margin-top:14px;">© <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $settings['business_name'] ); ?>
				&nbsp;·&nbsp; Designed &amp; developed by <a href="https://3s-soft.com" target="_blank" rel="noopener" style="color:#8aa19f;">3s-Soft</a>
			</div>
		</td></tr>
	</table>
</body>
</html>
	<?php
	return ob_get_clean();
}

/**
 * Per-type email definition: which setting gates it, its subject, and the log
 * word. A map rather than a ternary chain — the old binary form quietly funnelled
 * every unrecognised type into the "request" bucket, so a new type could not be
 * added without it silently mailing the wrong thing.
 *
 * @param string $type request | confirmed | completed.
 */
function bhela_bm_email_customer_types( $type ) {
	$map = array(
		'request'   => array(
			'gate'    => 'email_customer_request',
			'subject' => '🛶 BHELA Booking Request Received — %s',
			'log'     => 'Request',
		),
		'confirmed' => array(
			'gate'    => 'email_customer_confirmed',
			'subject' => '✅ BHELA Booking Confirmed — %s',
			'log'     => 'Confirmation',
		),
		'completed' => array(
			'gate'    => 'email_customer_completed',
			'subject' => '🙏 Thank you for travelling with BHELA — %s',
			'log'     => 'Thank-you',
		),
	);
	return $map[ $type ] ?? $map['request'];
}

/** Customer email (branded HTML). $type: 'request' | 'confirmed' | 'completed'. */
function bhela_bm_email_customer( $booking_id, $type = 'request' ) {
	$def        = bhela_bm_email_customer_types( $type );
	$settings   = bhela_bm_get_settings();
	$invoice_no = get_post_meta( $booking_id, '_bhela_invoice_no', true );

	// Say why nothing was sent. A silent return here is indistinguishable from a
	// delivery failure, which sends the owner hunting for a bug that isn't there.
	$skip = function ( $why ) use ( $def, $invoice_no ) {
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'email', sprintf( '%s email not sent (%s) — %s', $def['log'], $why, $invoice_no ), false );
		}
		return false;
	};

	$email = get_post_meta( $booking_id, '_bhela_email', true );
	if ( ! $email || ! is_email( $email ) ) {
		return $skip( __( 'no customer email on this booking', 'bhela-booking' ) );
	}
	if ( empty( $settings['email_enabled'] ) ) {
		return $skip( __( 'Settings → "Enable emails" is off', 'bhela-booking' ) );
	}
	if ( empty( $settings[ $def['gate'] ] ) ) {
		return $skip( __( 'this notification is switched off in Settings', 'bhela-booking' ) );
	}
	$subject = sprintf( $def['subject'], $invoice_no );

	$body      = bhela_bm_email_customer_html( $booking_id, $type );
	$from      = sanitize_email( $settings['email'] ? $settings['email'] : get_option( 'admin_email' ) );
	$from_name = sanitize_text_field( $settings['email_from_name'] ? $settings['email_from_name'] : $settings['business_name'] );
	$reply_to  = sanitize_email( $settings['email_reply_to'] ? $settings['email_reply_to'] : $from );
	$headers   = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $from_name . ' <' . $from . '>',
	);
	if ( $reply_to ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	$sent = wp_mail( $email, $subject, $body, $headers );
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log(
			$sent ? 'email' : 'error',
			sprintf( '%s email %s — %s (%s)', $def['log'],
				$sent ? 'sent' : 'failed', $email, $invoice_no ),
			$sent
		);
	}
	return $sent;
}
