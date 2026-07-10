<?php
/**
 * Email notifications (admin + customer).
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain-text booking summary. */
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

/** Notify site admin of a new booking request. */
function bhela_bm_email_admin_new( $booking_id ) {
	$settings = bhela_bm_get_settings();
	$to       = $settings['email'] ? $settings['email'] : get_option( 'admin_email' );
	$subject  = sprintf( 'BHELA: New Booking Request — %s (%s)', get_the_title( $booking_id ), get_post_meta( $booking_id, '_bhela_invoice_no', true ) );
	$body     = "নতুন বুকিং রিকোয়েস্ট এসেছে:\n\n" . bhela_bm_booking_summary_text( $booking_id );
	$body    .= "\n\nAdmin: " . admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
	$body    .= "\nInvoice: " . bhela_bm_invoice_url( $booking_id );
	wp_mail( $to, $subject, $body );
}

/** Customer email. $type: 'request' | 'confirmed'. */
function bhela_bm_email_customer( $booking_id, $type = 'request' ) {
	$email = get_post_meta( $booking_id, '_bhela_email', true );
	if ( ! $email || ! is_email( $email ) ) {
		return false;
	}
	$settings   = bhela_bm_get_settings();
	$invoice_no = get_post_meta( $booking_id, '_bhela_invoice_no', true );
	$name       = get_the_title( $booking_id );

	if ( 'confirmed' === $type ) {
		$subject = sprintf( '✅ BHELA Booking Confirmed — %s', $invoice_no );
		$intro   = "প্রিয় {$name},\n\nআপনার ভেলা হাউসবোট বুকিং নিশ্চিত (Confirmed) হয়েছে! 🎉\n\n";
		$outro   = "\n\n📌 রিপোর্টিং: Anwarpur Ghat (নির্ধারিত সময় ফোনে জানানো হবে)\nবাকি ৫০% অনবোর্ড হওয়ার সময় পরিশোধযোগ্য।";
	} else {
		$subject = sprintf( '🛶 BHELA Booking Request Received — %s', $invoice_no );
		$intro   = "প্রিয় {$name},\n\nআপনার বুকিং রিকোয়েস্ট আমরা পেয়েছি। আমাদের টিম শীঘ্রই ফোন/WhatsApp-এ যোগাযোগ করবে।\nঅগ্রিম (৫০%) পরিশোধের পর বুকিং Confirmed হবে।\n\n";
		$outro   = '';
	}

	$body  = $intro . bhela_bm_booking_summary_text( $booking_id );
	$body .= "\n\n🧾 আপনার ইনভয়েস দেখুন/প্রিন্ট করুন:\n" . bhela_bm_invoice_url( $booking_id );
	$body .= $outro;
	$body .= "\n\n💳 পেমেন্ট:\nBangla QR (bKash/Bank App): {$settings['bkash_number']}\nNagad: {$settings['nagad_number']}";
	$body .= "\nপেমেন্ট QR কোড ইনভয়েসে দেওয়া আছে — স্ক্যান করে পেমেন্ট করুন এবং Transaction ID টি WhatsApp-এ পাঠান।";
	$body .= "\n📞 {$settings['phone_1']}, {$settings['phone_2']} | WhatsApp: {$settings['whatsapp']}";
	$body .= "\n\n— {$settings['business_name']}\n\"{$settings['business_tagline']}\"";

	$headers = array( 'From: ' . $settings['business_name'] . ' <' . ( $settings['email'] ? $settings['email'] : get_option( 'admin_email' ) ) . '>' );

	return wp_mail( $email, $subject, $body, $headers );
}
