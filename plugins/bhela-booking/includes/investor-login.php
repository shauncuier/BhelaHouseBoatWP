<?php
/**
 * Passwordless sign-in for the investor portal — the phone is the credential.
 *
 * An investor is not a member of staff. They sign in a few times a year, from a
 * phone, and a password set once and never used again is a password they will lose —
 * so the office ends up resetting it by hand over WhatsApp, which is a worse
 * authentication story than the one this replaces. The number BHELA already holds on
 * the investor record is the thing they demonstrably still control.
 *
 * So: type the mobile number, receive a code, type the code, you are in.
 *
 *     phone typed  →  record looked up  →  code sent to the RECORD's number
 *                  →  code proved       →  wp_set_auth_cookie()
 *
 * Four rules carry the whole security model, and none of them is optional:
 *
 * 1. **The number is looked up; it is never trusted.** A challenge only exists when
 *    the number resolves to exactly one investor record that already has a linked
 *    login. There is no path from "I typed a number" to an account.
 * 2. **The fallback address comes from the record, never from the request.** Letting
 *    a visitor supply the email to fall back to would turn "I know an investor's
 *    phone number" into "I receive their sign-in code". The registration form may ask
 *    for an address, because an application is worth nothing until a person approves
 *    it; a login may not.
 * 3. **A known number and an unknown one produce identical pages.** Same wording,
 *    same masked number — built from what was typed, not from the record — same
 *    everything. Otherwise this form is a free tool for testing which phone numbers
 *    belong to BHELA's shareholders. Same discipline as the throttled response in the
 *    old password form, CLAUDE.md §13.43.
 * 4. **A code can only ever sign in a pure portal account.** If an investor record
 *    were linked to an administrator's user — which the Portal Login box does not
 *    stop, and which is exactly how an owner who is also a shareholder would set
 *    themselves up — then possession of one phone number would be possession of
 *    wp-admin. bhela_bm_otp_login_allowed() therefore refuses any account holding a
 *    role or a capability beyond the portal's own. Staff sign in at wp-login.php,
 *    with a password.
 *
 * The challenge itself lives server-side. The browser carries an opaque random id and
 * nothing else — no phone number, no investor id, no user id — so there is nothing in
 * the form for anybody to swap between the two steps.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * RULES
 * ========================================================= */

const BHELA_BM_CHAL_TTL       = 600;  // a code is good for 10 minutes
const BHELA_BM_CHAL_COOLDOWN  = 60;   // seconds between codes to one number
const BHELA_BM_CHAL_MAX_SENDS = 6;    // codes per number per day
const BHELA_BM_CHAL_MAX_TRIES = 5;    // wrong guesses before the code dies
const BHELA_BM_CHAL_IP_SENDS  = 20;   // codes one address may cause in an hour

/** Six digits, not four. This code opens a door to money; the booking one confirms a number. */
function bhela_bm_chal_code() {
	return (string) wp_rand( 100000, 999999 );
}

/** Keyed hash. A transient is readable by anything with database access. */
function bhela_bm_chal_hash( $code ) {
	return hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) );
}

/** Transient key for one challenge. The purpose is in the key, so a signup code cannot answer a login. */
function bhela_bm_chal_key( $purpose, $id ) {
	return 'bhela_bm_chal_' . md5( $purpose . '|' . $id );
}

/**
 * "01712****78" — built from what the visitor typed, never from the record.
 *
 * Deriving it from the record would make the confirmation page differ between a known
 * number and an unknown one, which is the enumeration leak this whole flow avoids.
 */
function bhela_bm_chal_mask( $phone ) {
	$phone = (string) $phone;
	if ( strlen( $phone ) < 11 ) {
		return $phone;
	}
	return substr( $phone, 0, 5 ) . '****' . substr( $phone, -2 );
}

/* =========================================================
 * Sending a code
 * ========================================================= */

/**
 * Start a challenge and deliver the code.
 *
 * @param string $purpose 'login' or 'signup' — part of the transient key.
 * @param string $phone   Mobile the code is sent to.
 * @param array  $payload What proving the code establishes. Stored server-side; the
 *                        caller gets it back from bhela_bm_chal_verify() and nowhere
 *                        else, so the browser never carries it.
 * @param string $email   Fallback address. For a login this MUST come from the
 *                        investor record and never from the request — rule 2 above.
 * @param bool   $deliver False builds a DECOY: every rate limit consumed, a real
 *                        challenge id handed back, and nothing sent anywhere. See
 *                        bhela_bm_login_step_phone() for why an unknown number has to
 *                        produce a challenge too.
 * @return array|WP_Error { id, channel, cooldown }
 */
function bhela_bm_chal_start( $purpose, $phone, $payload = array(), $email = '', $deliver = true ) {
	$phone = bhela_bm_normalize_mobile( $phone );
	if ( ! $phone ) {
		return new WP_Error( 'phone', __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু।', 'bhela-booking' ) );
	}

	// Every send is billable and points at a stranger's handset. Three ceilings: one
	// address per hour, one number per day, and a gap between codes to one number.
	$ip_key  = 'bhela_bm_chalip_' . md5( bhela_bm_client_ip() );
	$ip_hits = (int) get_transient( $ip_key );
	if ( $ip_hits >= BHELA_BM_CHAL_IP_SENDS ) {
		return new WP_Error( 'ip', __( 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}
	$cool_key = 'bhela_bm_chalcool_' . md5( $phone );
	if ( get_transient( $cool_key ) ) {
		return new WP_Error( 'cooldown', __( 'একটু অপেক্ষা করুন, তারপর আবার কোড চান।', 'bhela-booking' ) );
	}
	$day_key = 'bhela_bm_chalday_' . md5( $phone );
	$sends   = (int) get_transient( $day_key );
	if ( $sends >= BHELA_BM_CHAL_MAX_SENDS ) {
		return new WP_Error( 'day', __( 'এই নম্বরে আজ অনেকবার কোড পাঠানো হয়েছে। BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' ) );
	}

	// A decoy's "code" is a random NON-NUMERIC string. bhela_bm_chal_verify() strips
	// everything but digits before comparing, so no input a person can type will ever
	// match it — a decoy cannot be guessed open, not even at 1 in 900,000.
	$code    = $deliver ? bhela_bm_chal_code() : wp_generate_password( 40, true, true );
	$channel = '';
	$text    = sprintf( 'Your %s code is %s', bhela_bm_otp_brand(), $code );

	if ( ! $deliver ) {
		$channel = 'none';
	} elseif ( function_exists( 'bhela_bm_send_sms' ) && bhela_bm_send_sms( $phone, $text ) ) {
		$channel = 'sms';
	} elseif ( $email && is_email( $email ) ) {
		// The gateway is off, out of balance or unreachable. Falling back keeps an
		// investor able to sign in through an outage; which channel carried it is
		// recorded, because a code delivered by email proves an address, not a handset.
		$sent = wp_mail(
			$email,
			bhela_bm_otp_brand() . ' — ' . __( 'আপনার কোড', 'bhela-booking' ),
			sprintf(
				/* translators: 1: the code, 2: minutes until it expires */
				__( "আপনার কোড: %1\$s\n\nএটি %2\$d মিনিট পর্যন্ত কার্যকর। কোডটি কারও সাথে শেয়ার করবেন না — BHELA কখনো এই কোড জানতে চাইবে না।", 'bhela-booking' ),
				$code,
				(int) ( BHELA_BM_CHAL_TTL / 60 )
			)
		);
		if ( $sent ) {
			$channel = 'email';
		}
	}

	if ( ! $channel ) {
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', sprintf( 'Portal: could not deliver a %s code to %s by SMS or email.', $purpose, $phone ), false );
		}
		// Rate limits are NOT consumed by a failure nobody caused — a dead gateway
		// must not also lock the number out for the day.
		return new WP_Error( 'send', __( 'কোড পাঠানো যায়নি। একটু পর আবার চেষ্টা করুন, বা BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' ) );
	}

	$id = wp_generate_password( 32, false );
	set_transient( bhela_bm_chal_key( $purpose, $id ), array(
		'hash'    => bhela_bm_chal_hash( $code ),
		'tries'   => 0,
		'phone'   => $phone,
		'channel' => $channel,
		'payload' => $payload,
		'at'      => time(),
	), BHELA_BM_CHAL_TTL );

	set_transient( $cool_key, 1, BHELA_BM_CHAL_COOLDOWN );
	set_transient( $day_key, $sends + 1, DAY_IN_SECONDS );
	set_transient( $ip_key, $ip_hits + 1, HOUR_IN_SECONDS );

	if ( $deliver && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'sms' === $channel ? 'sms' : 'email', sprintf( 'Portal %s code sent by %s to %s.', $purpose, $channel, $phone ) );
	}

	// The code itself never leaves this function.
	return array( 'id' => $id, 'channel' => $channel, 'cooldown' => BHELA_BM_CHAL_COOLDOWN );
}

/**
 * Prove a code. Single use — a correct answer destroys the challenge.
 *
 * @return array|WP_Error The payload the challenge was started with.
 */
function bhela_bm_chal_verify( $purpose, $id, $code ) {
	$id   = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
	$code = preg_replace( '/[^0-9]/', '', (string) $code );
	if ( ! $id || ! $code ) {
		return new WP_Error( 'code', __( 'কোডটি দিন।', 'bhela-booking' ) );
	}

	$key   = bhela_bm_chal_key( $purpose, $id );
	$state = get_transient( $key );
	if ( ! is_array( $state ) || empty( $state['hash'] ) ) {
		return new WP_Error( 'expired', __( 'কোডের মেয়াদ শেষ। নতুন কোড নিন।', 'bhela-booking' ) );
	}

	$tries = (int) ( $state['tries'] ?? 0 );
	if ( ! hash_equals( (string) $state['hash'], bhela_bm_chal_hash( $code ) ) ) {
		$state['tries'] = $tries + 1;
		// Burn the code on the attempt that exhausts the allowance, not on the one
		// after it — same reasoning as the booking OTP: leaving it alive for one more
		// request is both a wider guessing window and a worse message.
		if ( $state['tries'] >= BHELA_BM_CHAL_MAX_TRIES ) {
			delete_transient( $key );
			return new WP_Error( 'expired', __( 'অনেকবার ভুল হয়েছে। নতুন কোড নিন।', 'bhela-booking' ) );
		}
		// The original expiry is kept rather than extended on a wrong guess.
		set_transient( $key, $state, BHELA_BM_CHAL_TTL );
		return new WP_Error( 'wrong', sprintf(
			/* translators: %d: attempts left */
			__( 'কোড মেলেনি। আর %d বার চেষ্টা করা যাবে।', 'bhela-booking' ),
			max( 0, BHELA_BM_CHAL_MAX_TRIES - $state['tries'] )
		) );
	}

	delete_transient( $key );
	return array(
		'phone'   => (string) ( $state['phone'] ?? '' ),
		'channel' => (string) ( $state['channel'] ?? '' ),
		'payload' => is_array( $state['payload'] ?? null ) ? $state['payload'] : array(),
	);
}

/* =========================================================
 * Finding the record behind a number
 * ========================================================= */

/**
 * Version of the normalised-mobile index. Bump to force a rebuild.
 *
 * `_bhela_inv_mobile` is free text — "+880 1712-345678", "01712 345678", whatever the
 * office typed off the onboarding form. A meta_query cannot match any of those against
 * the digits a visitor types on a phone, so a second key holds the normalised form and
 * the lookup uses that one.
 */
const BHELA_BM_MOBILE_INDEX = 1;

/** Write — or clear — one record's normalised-mobile index. */
function bhela_bm_investor_index_mobile( $id ) {
	$n = bhela_bm_normalize_mobile( get_post_meta( $id, '_bhela_inv_mobile', true ) );
	if ( $n ) {
		update_post_meta( $id, '_bhela_inv_mobile_n', $n );
	} else {
		delete_post_meta( $id, '_bhela_inv_mobile_n' );
	}
	return $n;
}

/** Rebuild the whole index. Cheap — this is a register of dozens, not millions. */
function bhela_bm_investor_mobile_index_build() {
	foreach ( bhela_bm_investors() as $id ) {
		bhela_bm_investor_index_mobile( $id );
	}
	update_option( 'bhela_bm_inv_mobile_idx', BHELA_BM_MOBILE_INDEX );
}

/**
 * Build the index if it has never been built.
 *
 * On admin_init like the audit table's schema check, so in practice it is long done
 * before any investor signs in. The lookup calls it as well: an index that does not
 * exist yet does not fail loudly, it silently turns every sign-in into "no such
 * number" — which is the worst kind of bug to diagnose from a phone call.
 */
function bhela_bm_investor_mobile_index_check() {
	if ( (int) get_option( 'bhela_bm_inv_mobile_idx', 0 ) >= BHELA_BM_MOBILE_INDEX ) {
		return;
	}
	bhela_bm_investor_mobile_index_build();
}
add_action( 'admin_init', 'bhela_bm_investor_mobile_index_check', 5 );

/**
 * The investor record for a mobile number, or 0.
 *
 * Two records carrying one number is refused rather than resolved, for the reason
 * bhela_bm_current_investor() refuses two records on one login: picking whichever
 * sorted first would decide whose money somebody sees.
 */
function bhela_bm_investor_by_mobile( $raw ) {
	$phone = bhela_bm_normalize_mobile( $raw );
	if ( ! $phone ) {
		return 0;
	}
	bhela_bm_investor_mobile_index_check();

	$hit = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_inv_mobile_n',
		'meta_value'     => $phone,
	) );
	if ( 1 !== count( $hit ) ) {
		if ( count( $hit ) > 1 && function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', sprintf( 'Investor portal: mobile %s is on %d records — sign-in refused.', $phone, count( $hit ) ) );
		}
		return 0;
	}
	return (int) $hit[0];
}

/**
 * Investors with a portal login whose number no code could ever reach.
 *
 * A landline, a foreign number, or a typo in the eleven digits leaves the record with
 * no index entry — and passwordless sign-in then fails for that person with a message
 * that says nothing is wrong. The Registrations screen names them instead.
 */
function bhela_bm_investor_unreachable() {
	$out = array();
	foreach ( bhela_bm_investors() as $id ) {
		if ( ! bhela_bm_investor_user( $id ) ) {
			continue;   // no login yet: nothing to be locked out of
		}
		if ( ! bhela_bm_normalize_mobile( get_post_meta( $id, '_bhela_inv_mobile', true ) ) ) {
			$out[] = (int) $id;
		}
	}
	return $out;
}

/* =========================================================
 * Who a code may sign in
 * ========================================================= */

/**
 * Capabilities that disqualify an account from code sign-in.
 *
 * Checked as capabilities and not only as roles, because a capability can be granted
 * to one user directly and would then be invisible to a role check.
 */
function bhela_bm_otp_login_forbidden_caps() {
	return array(
		'manage_options', 'edit_posts', 'edit_pages', 'publish_posts', 'upload_files',
		'create_users', 'list_users', 'edit_theme_options', 'install_plugins',
		'moderate_comments', 'edit_others_posts',
		'bhela_investors_view', 'bhela_investor_pay', 'bhela_investor_approve',
		'bhela_investor_valuation', 'bhela_investor_signup', 'bhela_dist_run',
		'bhela_view_reports', 'bhela_view_statement', 'bhela_inv_view',
		'edit_bhela_bookings', 'edit_bhela_investors', 'read_private_bhela_investors',
	);
}

/**
 * May this account be signed in by a code sent to a phone?
 *
 * Only a pure portal account. Rule 4 in the file header: an investor record linked to
 * a staff or administrator login would otherwise turn one phone number into wp-admin,
 * and an owner who is also a shareholder is a completely ordinary thing to be.
 */
function bhela_bm_otp_login_allowed( $user ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}
	$allowed = apply_filters( 'bhela_bm_otp_login_roles', array( 'bhela_investor', 'subscriber' ) );
	foreach ( (array) $user->roles as $role ) {
		if ( ! in_array( $role, (array) $allowed, true ) ) {
			return false;
		}
	}
	foreach ( bhela_bm_otp_login_forbidden_caps() as $cap ) {
		if ( user_can( $user, $cap ) ) {
			return false;
		}
	}
	return true;
}

/* =========================================================
 * The two steps
 * ========================================================= */

/**
 * State handed from the template_redirect handler to the shortcode.
 *
 * A static rather than a transient or a query argument: it belongs to this one
 * request, and a masked number in a URL is a masked number in a server log.
 */
function bhela_bm_portal_state( $set = null ) {
	static $state = array();
	if ( is_array( $set ) ) {
		$state = $set;
	}
	return $state;
}

/**
 * Handled on template_redirect, not inside the shortcode.
 *
 * A successful sign-in sets a cookie and redirects, and both need headers that have
 * not been sent yet. Doing it from the_content works right up until a theme flushes
 * early, at which point the investor is signed in and looking at the login form.
 */
function bhela_bm_login_handle() {
	if ( is_user_logged_in() || empty( $_POST['bhela_inv_step'] ) ) {
		return;
	}
	$step = sanitize_key( wp_unslash( $_POST['bhela_inv_step'] ) );
	if ( ! in_array( $step, array( 'phone', 'code' ), true ) ) {
		return;
	}
	if ( empty( $_POST['bhela_inv_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_inv_nonce'] ) ), 'bhela_inv_login' ) ) {
		return;
	}
	if ( ! empty( $_POST['bhela_bm_hp'] ) ) {
		return;   // honeypot: answer nothing at all
	}

	$ip_key = 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() );
	$tries  = (int) get_transient( $ip_key );

	// Over the limit, the request goes BLIND: it is answered exactly as an unknown
	// number or a wrong code would be, rather than getting a page of its own. A
	// "too many attempts" screen confirms there is something worth waiting out, and
	// a throttle that announces itself is a throttle an attacker can pace around —
	// CLAUDE.md §13.43. Nothing is looked up and nothing is sent while blind.
	$blind = ( $tries >= bhela_bm_portal_login_limit() );

	if ( 'phone' === $step ) {
		bhela_bm_login_step_phone( $ip_key, $tries, $blind );
		return;
	}
	if ( bhela_bm_login_step_code( $ip_key, $tries, $blind ) ) {
		wp_safe_redirect( bhela_bm_portal_url() );
		exit;
	}
}
add_action( 'template_redirect', 'bhela_bm_login_handle' );

/** Step one: a number is typed. Whether it is known changes nothing on screen. */
function bhela_bm_login_step_phone( $ip_key, $tries, $blind = false ) {
	$typed = bhela_bm_normalize_mobile( wp_unslash( $_POST['bhela_inv_phone'] ?? '' ) );
	if ( ! $typed ) {
		bhela_bm_portal_state( array(
			'step' => 'phone',
			'err'  => __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু।', 'bhela-booking' ),
		) );
		return;
	}

	$investor = $blind ? 0 : bhela_bm_investor_by_mobile( $typed );
	$user     = $investor ? get_userdata( bhela_bm_investor_user( $investor ) ) : null;
	$known    = ( $investor && $user && bhela_bm_otp_login_allowed( $user ) );

	// An unknown number opens a DECOY challenge rather than skipping to the same
	// screen with an empty hidden field. The first version of this did skip it, and
	// the page then differed — `bhela_inv_chal` was populated for a number on the
	// register and empty for one that was not, which is a free tool for testing which
	// phone numbers belong to BHELA's shareholders. Every rate limit is consumed
	// either way, so hammering the number space costs exactly what guessing a code
	// costs, and step two answers a decoy with the ordinary wrong-code message.
	//
	// Two things this deliberately does NOT equalise, both stated rather than hidden:
	// response TIME (a real send waits on the gateway; adding a matching delay to
	// every unknown number is a denial-of-service tool pointed at ourselves), and the
	// delivery-failure message, which is about the site rather than the number and
	// only appears when SMS and email are both down.
	$email = '';
	if ( $known ) {
		// The fallback address is the record's, never the request's — rule 2.
		$email = sanitize_email( (string) get_post_meta( $investor, '_bhela_inv_email', true ) );
		if ( ! $email || ! is_email( $email ) ) {
			$email = $user->user_email;
		}
	}
	$chal = bhela_bm_chal_start(
		'login',
		$typed,
		$known ? array( 'user' => (int) $user->ID, 'investor' => $investor ) : array(),
		$email,
		$known
	);
	if ( is_wp_error( $chal ) ) {
		// Rate limits and gateway trouble are reported honestly: none of these
		// messages says whether the number is on the register, and a visitor left on a
		// silent screen would ring the office, which is worse for everybody.
		bhela_bm_portal_state( array( 'step' => 'phone', 'err' => $chal->get_error_message() ) );
		return;
	}
	if ( ! $known ) {
		set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );
	}

	bhela_bm_portal_state( array(
		'step' => 'code',
		'chal' => $chal['id'],
		'mask' => bhela_bm_chal_mask( $typed ),
	) );
}

/**
 * Step two: the code is typed. The only place in the plugin that sets an auth cookie.
 *
 * Returns true when the visitor is now signed in, and the caller redirects. It does
 * NOT redirect or exit itself: a function that ends the process cannot be driven by a
 * test, and this is the one function here where the success path is the thing most
 * worth testing.
 *
 * @return bool True when an auth cookie was set.
 */
function bhela_bm_login_step_code( $ip_key, $tries, $blind = false ) {
	$chal = sanitize_text_field( wp_unslash( $_POST['bhela_inv_chal'] ?? '' ) );
	$code = sanitize_text_field( wp_unslash( $_POST['bhela_inv_code'] ?? '' ) );
	$mask = sanitize_text_field( wp_unslash( $_POST['bhela_inv_mask'] ?? '' ) );

	$ok = $blind
		// Refused without being looked at, and answered as an ordinary first wrong
		// guess — the count is the one a first wrong guess would report, so the page
		// does not give away that this request was never checked at all.
		? new WP_Error( 'wrong', sprintf(
			/* translators: %d: attempts left */
			__( 'কোড মেলেনি। আর %d বার চেষ্টা করা যাবে।', 'bhela-booking' ),
			BHELA_BM_CHAL_MAX_TRIES - 1
		) )
		: ( $chal
			? bhela_bm_chal_verify( 'login', $chal, $code )
			: new WP_Error( 'expired', __( 'কোডের মেয়াদ শেষ। নতুন কোড নিন।', 'bhela-booking' ) ) );

	if ( is_wp_error( $ok ) ) {
		if ( ! $blind ) {
			set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );
		}
		bhela_bm_portal_state( array(
			'step' => 'expired' === $ok->get_error_code() ? 'phone' : 'code',
			'chal' => $chal,
			'mask' => $mask,
			'err'  => $ok->get_error_message(),
		) );
		return false;
	}

	$user_id  = (int) ( $ok['payload']['user'] ?? 0 );
	$investor = (int) ( $ok['payload']['investor'] ?? 0 );
	$user     = $user_id ? get_userdata( $user_id ) : null;

	// Everything is checked AGAIN at the moment of sign-in. Ten minutes is long enough
	// for a record to be unlinked, a role to be widened or an account to be deleted,
	// and the check that mattered was made before any of that happened.
	if ( ! $user || ! bhela_bm_otp_login_allowed( $user )
		|| ! $investor || bhela_bm_investor_user( $investor ) !== $user_id ) {
		set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );
		bhela_bm_portal_state( array(
			'step' => 'phone',
			'err'  => __( 'এই মুহূর্তে সাইন ইন করা যাচ্ছে না। BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' ),
		) );
		return false;
	}

	delete_transient( $ip_key );

	$remember = ! empty( $_POST['rememberme'] );
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, $remember, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	if ( function_exists( 'bhela_bm_audit' ) ) {
		bhela_bm_audit( array(
			'channel'     => 'investor',
			'action'      => 'portal_login',
			'object_type' => 'investor',
			'object_id'   => $investor,
			'object_ref'  => get_the_title( $investor ),
			'field'       => 'channel',
			'new_value'   => (string) $ok['channel'],
			'reason'      => __( 'Signed in with a one-time code.', 'bhela-booking' ),
		) );
	}

	return true;
}

/* =========================================================
 * The form
 * ========================================================= */

/** The sign-in form — step one, or step two once a code is out. */
function bhela_bm_portal_login_form( $err = '' ) {
	$state = bhela_bm_portal_state();
	$step  = $state['step'] ?? 'phone';
	$err   = $err ? $err : ( $state['err'] ?? '' );

	ob_start();
	?>
	<div class="bhela-inv bhela-inv--login">
		<div class="bhela-inv__card">
			<h2><?php esc_html_e( 'বিনিয়োগকারী লগইন', 'bhela-booking' ); ?></h2>
			<?php if ( $err ) : ?>
				<p class="bhela-inv__err"><?php echo esc_html( $err ); ?></p>
			<?php endif; ?>

			<?php if ( 'code' === $step ) : ?>
				<p class="bhela-inv__muted"><?php
					printf(
						/* translators: %s: masked mobile number */
						esc_html__( '%s নম্বরে একটি ৬ সংখ্যার কোড পাঠানো হয়েছে। SMS না এলে আপনার নিবন্ধিত ইমেইল দেখুন।', 'bhela-booking' ),
						'<strong>' . esc_html( $state['mask'] ?? '' ) . '</strong>'
					);
				?></p>
				<form method="post">
					<?php wp_nonce_field( 'bhela_inv_login', 'bhela_inv_nonce' ); ?>
					<input type="hidden" name="bhela_inv_step" value="code">
					<input type="hidden" name="bhela_inv_chal" value="<?php echo esc_attr( $state['chal'] ?? '' ); ?>">
					<input type="hidden" name="bhela_inv_mask" value="<?php echo esc_attr( $state['mask'] ?? '' ); ?>">
					<p class="bhela-inv__hp"><input type="text" name="bhela_bm_hp" value="" tabindex="-1" autocomplete="off"></p>
					<label><?php esc_html_e( 'কোড', 'bhela-booking' ); ?>
						<input type="text" name="bhela_inv_code" inputmode="numeric" autocomplete="one-time-code"
							pattern="[0-9]*" maxlength="6" required autofocus></label>
					<label class="bhela-inv__check"><input type="checkbox" name="rememberme" value="1">
						<?php esc_html_e( 'এই ডিভাইসে মনে রাখুন', 'bhela-booking' ); ?></label>
					<button type="submit" class="bhela-inv__btn"><?php esc_html_e( 'সাইন ইন', 'bhela-booking' ); ?></button>
				</form>
				<form method="post" class="bhela-inv__again">
					<?php wp_nonce_field( 'bhela_inv_login', 'bhela_inv_nonce' ); ?>
					<input type="hidden" name="bhela_inv_step" value="phone">
					<button type="submit" class="bhela-inv__link"><?php esc_html_e( '← অন্য নম্বর দিয়ে চেষ্টা করুন', 'bhela-booking' ); ?></button>
				</form>
			<?php else : ?>
				<p class="bhela-inv__muted"><?php esc_html_e( 'পাসওয়ার্ড লাগবে না। BHELA-র খাতায় আপনার যে মোবাইল নম্বরটি আছে সেটি দিন — সেখানে একটি কোড পাঠানো হবে।', 'bhela-booking' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'bhela_inv_login', 'bhela_inv_nonce' ); ?>
					<input type="hidden" name="bhela_inv_step" value="phone">
					<p class="bhela-inv__hp"><input type="text" name="bhela_bm_hp" value="" tabindex="-1" autocomplete="off"></p>
					<label><?php esc_html_e( 'মোবাইল নম্বর', 'bhela-booking' ); ?>
						<input type="text" name="bhela_inv_phone" inputmode="numeric" autocomplete="tel"
							placeholder="01XXXXXXXXX" maxlength="20" required></label>
					<button type="submit" class="bhela-inv__btn"><?php esc_html_e( 'কোড পাঠান', 'bhela-booking' ); ?></button>
				</form>
				<p class="bhela-inv__muted">
					<?php esc_html_e( 'এখনো নিবন্ধন করেননি?', 'bhela-booking' ); ?>
					<a href="<?php echo esc_url( bhela_bm_signup_url() ); ?>"><?php esc_html_e( 'বিনিয়োগকারী নিবন্ধন করুন', 'bhela-booking' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
