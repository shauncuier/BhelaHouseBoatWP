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
		$lines[] = 'Per Person: ' . bhela_bm_money( $m( '_bhela_per_person' ) ) . ' (' . $m( '_bhela_day_type' ) . ')';
		$lines[] = 'Total: ' . bhela_bm_money( $m( '_bhela_total' ) );
		$lines[] = 'Advance (50%): ' . bhela_bm_money( $m( '_bhela_advance' ) );
		$lines[] = 'Paid: ' . bhela_bm_money( $m( '_bhela_paid_amount' ) );
	}
	if ( $m( '_bhela_message' ) ) {
		$lines[] = 'Note: ' . $m( '_bhela_message' );
	}
	return implode( "\n", $lines );
}

/** Notify site admin of a new booking request (plain text — functional). */
function bhela_bm_email_admin_new( $booking_id ) {
	$settings = bhela_bm_get_settings();
	$to       = $settings['email'] ? $settings['email'] : get_option( 'admin_email' );
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
	$paid       = (int) $m( '_bhela_paid_amount' );
	$due        = max( 0, $total - $paid );
	$inv_url    = bhela_bm_invoice_url( $booking_id );
	$wa_num     = preg_replace( '/[^0-9]/', '', $settings['whatsapp'] );
	$wa_url     = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( 'আসসালামু আলাইকুম। আমার বুকিং নম্বর: ' . $invoice_no );

	if ( 'confirmed' === $type ) {
		$banner_bg   = '#1a7f37';
		$banner_text = '✅ বুকিং কনফার্মড!';
		$intro       = 'প্রিয় ' . esc_html( $name ) . ', আপনার ভেলা হাউসবোট বুকিং নিশ্চিত হয়েছে! 🎉 নিচে আপনার ট্রিপের বিস্তারিত দেওয়া হলো।';
	} else {
		$banner_bg   = '#b45309';
		$banner_text = '🛶 বুকিং রিকোয়েস্ট গৃহীত';
		$intro       = 'প্রিয় ' . esc_html( $name ) . ', আপনার বুকিং রিকোয়েস্ট আমরা পেয়েছি। আমাদের টিম শীঘ্রই ফোন/WhatsApp-এ যোগাযোগ করবে। <strong>অগ্রিম (৫০%) পরিশোধের পর বুকিং Confirmed হবে।</strong>';
	}

	$rows  = bhela_bm_email_row( 'বুকিং নম্বর', $invoice_no, true );
	$rows .= bhela_bm_email_row( 'ভ্রমণের তারিখ', $m( '_bhela_travel_date' ) . ' (২ দিন ১ রাত)' );
	$rows .= bhela_bm_email_row( 'কেবিন', $m( '_bhela_cabin_type' ) );
	$rows .= bhela_bm_email_row( 'অতিথি', $m( '_bhela_guests' ) . ' জন' );
	if ( $total ) {
		$rows .= bhela_bm_email_row( 'জনপ্রতি', bhela_bm_money( $m( '_bhela_per_person' ) ) );
		$rows .= bhela_bm_email_row( 'মোট', bhela_bm_money( $total ), true );
		$rows .= bhela_bm_email_row( 'অগ্রিম (৫০%)', bhela_bm_money( $advance ), true, '#E5601F' );
		$rows .= bhela_bm_email_row( 'পরিশোধিত', bhela_bm_money( $paid ), false, '#1a7f37' );
		$rows .= bhela_bm_email_row( 'বাকি', bhela_bm_money( $due ), true, '#b32d2e' );
	}

	$boarding = ( 'confirmed' === $type )
		? '<p style="margin:18px 0 0;padding:14px 16px;background:#EBF7EF;border-radius:10px;font-size:13.5px;color:#14532d;line-height:1.8;">📌 <strong>রিপোর্টিং:</strong> Anwarpur Ghat — নির্ধারিত সময় ফোনে জানানো হবে।<br>💵 বাকি ৫০% অনবোর্ড হওয়ার সময় পরিশোধযোগ্য।</p>'
		: '';

	ob_start();
	?>
<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#EEF2F1;font-family:'Hind Siliguri','Segoe UI',Tahoma,sans-serif;">
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
						✉️ <?php echo esc_html( $settings['email'] ); ?> &nbsp;·&nbsp; 📍 <?php echo esc_html( $settings['address'] ); ?>
					</div>
					<div style="font-size:12px;color:#F5C97B;margin-top:10px;font-style:italic;">"<?php echo esc_html( $settings['business_tagline'] ); ?>"</div>
				</td></tr>
			</table>
			<div style="font-size:11px;color:#8aa19f;margin-top:14px;">© <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $settings['business_name'] ); ?></div>
		</td></tr>
	</table>
</body>
</html>
	<?php
	return ob_get_clean();
}

/** Customer email (branded HTML). $type: 'request' | 'confirmed'. */
function bhela_bm_email_customer( $booking_id, $type = 'request' ) {
	$email = get_post_meta( $booking_id, '_bhela_email', true );
	if ( ! $email || ! is_email( $email ) ) {
		return false;
	}
	$settings   = bhela_bm_get_settings();
	$invoice_no = get_post_meta( $booking_id, '_bhela_invoice_no', true );

	$subject = ( 'confirmed' === $type )
		? sprintf( '✅ BHELA Booking Confirmed — %s', $invoice_no )
		: sprintf( '🛶 BHELA Booking Request Received — %s', $invoice_no );

	$body    = bhela_bm_email_customer_html( $booking_id, $type );
	$from    = $settings['email'] ? $settings['email'] : get_option( 'admin_email' );
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $settings['business_name'] . ' <' . $from . '>',
	);

	return wp_mail( $email, $subject, $body, $headers );
}
