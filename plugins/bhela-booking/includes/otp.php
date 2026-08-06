<?php
/**
 * Mobile number verification for the booking form.
 *
 * Anyone can type any number into a booking. The manager then works the trip
 * report by phone, so a typo or an invented number costs a call and, on a full
 * trip, a held cabin. This proves the guest is holding the phone before the
 * booking is accepted.
 *
 * SMS is the primary channel; email is the fallback when the gateway fails, so
 * a dead gateway or an empty balance does not stop bookings outright.
 *
 * Nothing here is on until `otp_enabled` is switched on in Settings — an
 * unconfigured site behaves exactly as before.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * RULES
 * ========================================================= */

const BHELA_BM_OTP_TTL       = 600;  // a code is good for 10 minutes
const BHELA_BM_OTP_OK_TTL    = 1800; // a verified number stays verified for 30
const BHELA_BM_OTP_COOLDOWN  = 60;   // seconds between sends to one number
const BHELA_BM_OTP_MAX_SENDS = 5;    // sends per number per day
const BHELA_BM_OTP_MAX_TRIES = 5;    // wrong guesses before the code dies

/** Is number verification switched on? */
function bhela_bm_otp_on() {
	$s = bhela_bm_get_settings();
	return ! empty( $s['otp_enabled'] );
}

/** Transient key for a pending code. */
function bhela_bm_otp_key( $phone ) {
	return 'bhela_bm_otp_' . md5( (string) $phone );
}

/** Transient key for "this number has been proven". */
function bhela_bm_otp_ok_key( $phone ) {
	return 'bhela_bm_otpok_' . md5( (string) $phone );
}

/**
 * Strip a string down to characters the GSM-7 alphabet can carry.
 *
 * One character outside GSM-7 flips the whole SMS to Unicode, which drops the
 * segment size from 160 characters to 70 — every OTP would then cost two parts
 * instead of one. The business name is exactly this trap: "BHELA – The Haor
 * Exclusive" contains an en-dash (U+2013), which is why the brand used here is
 * its own setting rather than a reuse of `business_name`.
 *
 * @param string $text Raw text.
 * @return string GSM-7 safe text.
 */
function bhela_bm_otp_gsm_safe( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	// Fold the punctuation people actually paste in, then drop the rest.
	$text = strtr( $text, array(
		'–' => '-', '—' => '-', '‑' => '-',
		'“' => '"', '”' => '"', '‘' => "'", '’' => "'",
		'…' => '...', '৳' => 'Tk',
	) );
	$text = preg_replace( '/[^A-Za-z0-9 @£$¥èéùìòÇØøÅåÆæßÉ!"#%&\'()*+,\-.\/:;<=>?_]/u', '', $text );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/** The brand used in the OTP text — short, ASCII, never the full business name. */
function bhela_bm_otp_brand() {
	$s     = bhela_bm_get_settings();
	$brand = bhela_bm_otp_gsm_safe( $s['otp_brand'] ?? '' );
	return $brand ? $brand : 'BHELA';
}

/** The message, in the format the owner specified. */
function bhela_bm_otp_message( $code ) {
	return sprintf( 'Your %s OTP is %s', bhela_bm_otp_brand(), $code );
}

/**
 * Store a code as a keyed hash — never in the clear.
 *
 * A transient is readable by anything with database access, and the code is
 * short enough to brute-force from a plain value.
 */
function bhela_bm_otp_hash( $code ) {
	return hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) );
}

/** Has this number been proven recently? */
function bhela_bm_otp_verified( $phone ) {
	$phone = bhela_bm_normalize_mobile( $phone );
	if ( ! $phone ) {
		return false;
	}
	return (bool) get_transient( bhela_bm_otp_ok_key( $phone ) );
}

/* =========================================================
 * SEND
 * ========================================================= */

/**
 * AJAX: send a code to the number on the form.
 *
 * Answers `need_email` rather than an error when SMS fails and no address is
 * on hand — that is a prompt for the form, not a failure.
 */
function bhela_bm_otp_ajax_send() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );

	if ( ! bhela_bm_otp_on() ) {
		wp_send_json_error( array( 'message' => __( 'নম্বর যাচাই এখন বন্ধ আছে।', 'bhela-booking' ) ) );
	}
	if ( ! empty( $_POST['bhela_bm_hp'] ) ) {
		wp_send_json_error( array( 'message' => __( 'দুঃখিত, রিকোয়েস্টটি গ্রহণ করা যায়নি।', 'bhela-booking' ) ) );
	}

	$phone = bhela_bm_normalize_mobile( wp_unslash( $_POST['phone'] ?? '' ) );
	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু।', 'bhela-booking' ) ) );
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	// Per-IP ceiling, matching the other public endpoints.
	$ip     = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$ip_key = 'bhela_bm_otpip_' . md5( $ip );
	$ip_hits = (int) get_transient( $ip_key );
	if ( $ip_hits >= 20 ) {
		wp_send_json_error( array( 'message' => __( 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।', 'bhela-booking' ) ) );
	}

	$state = get_transient( bhela_bm_otp_key( $phone ) );
	$state = is_array( $state ) ? $state : array();

	// Per-number cooldown and daily cap. Every send is billable, and an open
	// send endpoint is an SMS-bombing tool pointed at a stranger's phone.
	$last = (int) ( $state['last'] ?? 0 );
	if ( $last && ( time() - $last ) < BHELA_BM_OTP_COOLDOWN ) {
		wp_send_json_error( array(
			'message' => __( 'একটু অপেক্ষা করুন, তারপর আবার কোড চান।', 'bhela-booking' ),
			'retry'   => BHELA_BM_OTP_COOLDOWN - ( time() - $last ),
		) );
	}
	$sends_key = 'bhela_bm_otpday_' . md5( $phone );
	$sends     = (int) get_transient( $sends_key );
	if ( $sends >= BHELA_BM_OTP_MAX_SENDS ) {
		wp_send_json_error( array( 'message' => __( 'এই নম্বরে আজ অনেকবার কোড পাঠানো হয়েছে। সরাসরি WhatsApp-এ যোগাযোগ করুন।', 'bhela-booking' ) ) );
	}

	$code    = (string) wp_rand( 1000, 9999 );
	$message = bhela_bm_otp_message( $code );
	$channel = '';

	if ( function_exists( 'bhela_bm_send_sms' ) && bhela_bm_send_sms( $phone, $message ) ) {
		$channel = 'sms';
	} elseif ( $email && is_email( $email ) ) {
		// Fallback: the gateway is off, out of balance or unreachable.
		$sent = wp_mail(
			$email,
			bhela_bm_otp_brand() . ' — ' . __( 'আপনার ভেরিফিকেশন কোড', 'bhela-booking' ),
			sprintf(
				/* translators: 1: the code, 2: minutes until it expires */
				__( "আপনার কোড: %1\$s\n\nএটি %2\$d মিনিট পর্যন্ত কার্যকর। কোডটি কারও সাথে শেয়ার করবেন না।", 'bhela-booking' ),
				$code,
				(int) ( BHELA_BM_OTP_TTL / 60 )
			)
		);
		if ( $sent ) {
			$channel = 'email';
		}
	} else {
		// Nothing to fall back to — ask for an address instead of failing.
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'sms', sprintf( 'OTP: SMS unavailable for %s and no email on the form.', $phone ), false );
		}
		wp_send_json_error( array(
			'need_email' => true,
			'message'    => __( 'এই মুহূর্তে SMS পাঠানো যাচ্ছে না। ইমেইল দিন — কোড সেখানে পাঠাব।', 'bhela-booking' ),
		) );
	}

	if ( ! $channel ) {
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', sprintf( 'OTP: could not deliver a code to %s by SMS or email.', $phone ), false );
		}
		wp_send_json_error( array( 'message' => __( 'কোড পাঠানো যায়নি। একটু পর আবার চেষ্টা করুন, বা WhatsApp-এ যোগাযোগ করুন।', 'bhela-booking' ) ) );
	}

	set_transient( bhela_bm_otp_key( $phone ), array(
		'hash'    => bhela_bm_otp_hash( $code ),
		'tries'   => 0,
		'last'    => time(),
		'channel' => $channel,
	), BHELA_BM_OTP_TTL );
	set_transient( $sends_key, $sends + 1, DAY_IN_SECONDS );
	set_transient( $ip_key, $ip_hits + 1, HOUR_IN_SECONDS );

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'sms' === $channel ? 'sms' : 'email', sprintf( 'OTP sent by %s to %s.', $channel, $phone ) );
	}

	// The code itself is never in the response.
	wp_send_json_success( array(
		'channel'  => $channel,
		'cooldown' => BHELA_BM_OTP_COOLDOWN,
		'message'  => 'sms' === $channel
			? __( 'কোড SMS-এ পাঠানো হয়েছে।', 'bhela-booking' )
			: __( 'SMS পাঠানো যায়নি — কোড আপনার ইমেইলে পাঠানো হয়েছে।', 'bhela-booking' ),
	) );
}
add_action( 'wp_ajax_bhela_bm_otp_send', 'bhela_bm_otp_ajax_send' );
add_action( 'wp_ajax_nopriv_bhela_bm_otp_send', 'bhela_bm_otp_ajax_send' );

/* =========================================================
 * VERIFY
 * ========================================================= */

function bhela_bm_otp_ajax_verify() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );

	if ( ! bhela_bm_otp_on() ) {
		wp_send_json_error( array( 'message' => __( 'নম্বর যাচাই এখন বন্ধ আছে।', 'bhela-booking' ) ) );
	}

	$phone = bhela_bm_normalize_mobile( wp_unslash( $_POST['phone'] ?? '' ) );
	$code  = preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['code'] ?? '' ) );
	if ( ! $phone || ! $code ) {
		wp_send_json_error( array( 'message' => __( 'কোডটি দিন।', 'bhela-booking' ) ) );
	}

	$state = get_transient( bhela_bm_otp_key( $phone ) );
	if ( ! is_array( $state ) || empty( $state['hash'] ) ) {
		wp_send_json_error( array( 'message' => __( 'কোডের মেয়াদ শেষ। নতুন কোড নিন।', 'bhela-booking' ), 'expired' => true ) );
	}

	$tries = (int) ( $state['tries'] ?? 0 );
	if ( $tries >= BHELA_BM_OTP_MAX_TRIES ) {
		delete_transient( bhela_bm_otp_key( $phone ) );
		wp_send_json_error( array( 'message' => __( 'অনেকবার ভুল হয়েছে। নতুন কোড নিন।', 'bhela-booking' ), 'expired' => true ) );
	}

	if ( ! hash_equals( (string) $state['hash'], bhela_bm_otp_hash( $code ) ) ) {
		$state['tries'] = $tries + 1;

		// Burn the code on the attempt that exhausts the allowance, not on the
		// one after it. Leaving it alive for one more request is both a wider
		// guessing window and a worse message — "wrong code, 0 attempts left"
		// with no way forward.
		if ( $state['tries'] >= BHELA_BM_OTP_MAX_TRIES ) {
			delete_transient( bhela_bm_otp_key( $phone ) );
			wp_send_json_error( array(
				'message' => __( 'অনেকবার ভুল হয়েছে। নতুন কোড নিন।', 'bhela-booking' ),
				'expired' => true,
			) );
		}

		// Keep the original expiry rather than extending it on a wrong guess.
		set_transient( bhela_bm_otp_key( $phone ), $state, BHELA_BM_OTP_TTL );
		wp_send_json_error( array(
			'message' => __( 'কোড মেলেনি। আবার চেষ্টা করুন।', 'bhela-booking' ),
			'left'    => max( 0, BHELA_BM_OTP_MAX_TRIES - $state['tries'] ),
		) );
	}

	delete_transient( bhela_bm_otp_key( $phone ) );
	set_transient( bhela_bm_otp_ok_key( $phone ), array(
		'channel' => $state['channel'] ?? '',
		'at'      => current_time( 'mysql' ),
	), BHELA_BM_OTP_OK_TTL );

	wp_send_json_success( array( 'message' => __( 'নম্বর যাচাই সম্পন্ন ✅', 'bhela-booking' ) ) );
}
add_action( 'wp_ajax_bhela_bm_otp_verify', 'bhela_bm_otp_ajax_verify' );
add_action( 'wp_ajax_nopriv_bhela_bm_otp_verify', 'bhela_bm_otp_ajax_verify' );

/* =========================================================
 * RECORD
 * ========================================================= */

/**
 * Stamp a booking with how its number was proven.
 *
 * Called from the submission processor. Bookings made before this existed, and
 * any the owner types into wp-admin, simply carry no stamp — the admin badge
 * shows that rather than pretending they were checked.
 *
 * @param int    $post_id Booking ID.
 * @param string $phone   Normalised mobile.
 */
function bhela_bm_otp_stamp( $post_id, $phone ) {
	$ok = get_transient( bhela_bm_otp_ok_key( $phone ) );
	if ( ! is_array( $ok ) ) {
		return;
	}
	update_post_meta( $post_id, '_bhela_phone_verified', array(
		'channel' => $ok['channel'] ?? '',
		'at'      => $ok['at'] ?? current_time( 'mysql' ),
	) );
}

/** How a booking's number was proven, or an empty array. */
function bhela_bm_otp_record( $post_id ) {
	$v = get_post_meta( $post_id, '_bhela_phone_verified', true );
	return is_array( $v ) ? $v : array();
}
