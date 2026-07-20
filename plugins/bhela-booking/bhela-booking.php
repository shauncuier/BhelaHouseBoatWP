<?php
/**
 * Plugin Name: BHELA Booking Engine
 * Description: Complete booking engine for BHELA – The Haor Exclusive: cabin pricing (weekday/holiday), booking statuses, invoices with secure customer links, and email notifications.
 * Version: 2.7.0
 * Author: 3s-Soft
 * Author URI: https://3s-soft.com
 * License: GPLv2 or later
 * Text Domain: bhela-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_BM_VERSION', '2.7.0' );
define( 'BHELA_BM_PATH', plugin_dir_path( __FILE__ ) );
define( 'BHELA_BM_URL', plugin_dir_url( __FILE__ ) );

/* =========================================================
 * SETTINGS & DEFAULTS
 * ========================================================= */

function bhela_bm_default_settings() {
	return array(
		'business_name'    => 'BHELA – The Haor Exclusive',
		'business_tagline' => 'Where Nature, Comfort & Memories Meet',
		'address'          => 'Anwarpur Ghat, Tahirpur, Sunamganj, Bangladesh',
		'phone_1'          => '01891-562461',
		'phone_2'          => '01614-182769',
		'whatsapp'         => '+8801891562461',
		'email'            => 'infobhela@gmail.com',
		'bkash_number'     => '01703-284728 (Bangla QR — bKash/Bank App)',
		'nagad_number'     => '01684-498885 (KEYTO BD)',
		'bank_details'     => '',
		'nagad_qr'         => '',
		'bangla_qr'        => '',
		'invoice_prefix'   => 'BH',
		'advance_percent'  => 50,
		'child_fee'        => 5000, // flat charge per 4–8 year old, any day type
		'weekend_days'     => array( 5, 6 ), // date('w'): 5 = Friday, 6 = Saturday.
		'holidays'         => "2026-08-05\n2026-08-12\n2026-08-26",
		'invoice_note'     => "বুকিং নিশ্চিত করতে মোট মূল্যের ৫০% অগ্রিম প্রদান করতে হবে। বাকি ৫০% অনবোর্ড হওয়ার সময় পরিশোধযোগ্য। ২১+ দিন আগে বাতিলে অগ্রিমের ৫০% ফেরতযোগ্য; ৭ দিনের কম সময়ে কোনো রিফান্ড প্রযোজ্য নয়।",

		// Email notifications.
		'email_enabled'            => 1, // master switch for all emails
		'email_admin_new'          => 1, // notify owner on a new booking
		'email_customer_request'   => 1, // customer "request received" email
		'email_customer_confirmed' => 1, // customer "confirmed" email
		'notify_email'             => '', // admin recipient (blank → business email)
		'email_from_name'          => '', // From name (blank → business_name)
		'email_reply_to'           => '', // Reply-To (blank → business email)

		// SMS notifications (provider-agnostic — configure any BD gateway).
		'sms_enabled'        => 0,
		'sms_provider'       => 'bulksmsbd', // 'bulksmsbd' | 'custom'
		'sms_api_url'        => 'https://bulksmsbd.net/api/smsapi',
		'sms_method'         => 'GET',       // GET | POST
		'sms_json'           => 0,           // POST body as JSON instead of form
		'sms_api_key'        => '',
		'sms_sender_id'      => '',
		'sms_param_number'   => 'number',
		'sms_param_message'  => 'message',
		'sms_param_key'      => 'api_key',
		'sms_param_sender'   => 'senderid',
		'sms_auth_header'    => '',           // optional "Authorization: Bearer …"
		'sms_admin_number'   => '',           // blank → falls back to phone_1
		'sms_tpl_admin'      => "নতুন বুকিং! {invoice} — {name}, {phone}, {date}, {guests} জন, মোট {total}।",
		'sms_tpl_new'        => "প্রিয় {name}, ভেলা হাউসবোটে আপনার বুকিং রিকোয়েস্ট ({invoice}) পেয়েছি। তারিখ {date}। আমরা শীঘ্রই যোগাযোগ করব। — BHELA",
		'sms_tpl_confirmed'  => "প্রিয় {name}, আপনার বুকিং {invoice} এখন: {status}। তারিখ {date}। বাকি {due}। ধন্যবাদ — BHELA",
	);
}

function bhela_bm_get_settings() {
	return wp_parse_args( get_option( 'bhela_bm_settings', array() ), bhela_bm_default_settings() );
}

/** Cabin classes & per-person rates (2D1N). */
function bhela_bm_default_rates() {
	return array(
		'budget'  => array( 'label' => 'Budget Friendly Cabin (৬ জন শেয়ারিং)',    'sharing' => 6, 'regular' => 8000,  'weekday' => 6400 ),
		'comfort' => array( 'label' => 'Comfort Adjustment Cabin (৫ জন শেয়ারিং)', 'sharing' => 5, 'regular' => 9000,  'weekday' => 7200 ),
		'deluxe'  => array( 'label' => 'Double Deluxe Cabin (৪ জন শেয়ারিং)',      'sharing' => 4, 'regular' => 10000, 'weekday' => 8000 ),
		'luxury'  => array( 'label' => 'Luxury Triple Cabin (৩ জন শেয়ারিং)',      'sharing' => 3, 'regular' => 12000, 'weekday' => 9600 ),
		'couple'  => array( 'label' => 'Exclusive Couple Cabin (২ জন শেয়ারিং)',   'sharing' => 2, 'regular' => 13000, 'weekday' => 10400 ),
	);
}

function bhela_bm_get_rates() {
	$saved    = get_option( 'bhela_bm_rates', array() );
	$defaults = bhela_bm_default_rates();
	foreach ( $defaults as $key => $row ) {
		if ( isset( $saved[ $key ] ) ) {
			$defaults[ $key ] = wp_parse_args( $saved[ $key ], $row );
		}
	}
	return $defaults;
}

/**
 * Rate rows indexed by cabin occupancy (people sharing) — 2..6.
 * The per-person rate is decided by how many people share a cabin.
 */
function bhela_bm_rates_by_occupancy() {
	$map = array();
	foreach ( bhela_bm_get_rates() as $key => $row ) {
		$occ = (int) $row['sharing'];
		$row['key'] = $key;
		$map[ $occ ] = $row;
	}
	return $map;
}

/** Boat physical capacity. */
function bhela_bm_max_cabins() {
	return 6;
}
function bhela_bm_max_guests() {
	$occ = bhela_bm_rates_by_occupancy();
	$max = $occ ? max( array_keys( $occ ) ) : 6;
	return bhela_bm_max_cabins() * (int) $max; // 6 × 6 = 36
}

/**
 * The rate row for a given cabin occupancy (falls back to the nearest larger,
 * then nearest smaller, tier if an exact one is not configured).
 */
function bhela_bm_rate_for_occupancy( $occ ) {
	$map = bhela_bm_rates_by_occupancy();
	if ( isset( $map[ $occ ] ) ) {
		return $map[ $occ ];
	}
	$keys = array_keys( $map );
	sort( $keys );
	foreach ( $keys as $k ) {
		if ( $k >= $occ ) {
			return $map[ $k ];
		}
	}
	return $map[ end( $keys ) ];
}

/* =========================================================
 * PRICING ENGINE
 * ========================================================= */

/** Day type for a Y-m-d date: 'holiday' | 'weekend' | 'weekday'. */
function bhela_bm_day_type( $date ) {
	$settings = bhela_bm_get_settings();
	$ts       = strtotime( $date );
	if ( ! $ts ) {
		return 'weekend';
	}
	$holidays = array_filter( array_map( 'trim', explode( "\n", (string) $settings['holidays'] ) ) );
	if ( in_array( date( 'Y-m-d', $ts ), $holidays, true ) ) {
		return 'holiday';
	}
	if ( in_array( (int) date( 'w', $ts ), array_map( 'intval', (array) $settings['weekend_days'] ), true ) ) {
		return 'weekend';
	}
	return 'weekday';
}

/**
 * Normalise a Bangladeshi mobile number to local 11-digit form (01XXXXXXXXX).
 *
 * Accepts what guests actually type: 01712345678, 8801712345678,
 * +880 1712-345678, with spaces, dashes or brackets. Returns '' when the
 * number is not a valid BD mobile, so callers can reject it — the phone is
 * the only reliable way to reach a guest about their booking.
 */
function bhela_bm_normalize_mobile( $raw ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $raw );
	if ( '' === $digits ) {
		return '';
	}
	// Strip the country code in either written form.
	if ( 0 === strpos( $digits, '880' ) ) {
		$digits = substr( $digits, 3 );
	} elseif ( 0 === strpos( $digits, '00880' ) ) {
		$digits = substr( $digits, 5 );
	}
	// Guests sometimes drop the leading zero (1712345678).
	if ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
		$digits = '0' . $digits;
	}
	// Operators in use: 013–019.
	return preg_match( '/^01[3-9][0-9]{8}$/', $digits ) ? $digits : '';
}

/** True when the value is a usable BD mobile number. */
function bhela_bm_is_mobile( $raw ) {
	return '' !== bhela_bm_normalize_mobile( $raw );
}

/** Match a cabin key or label text to a rates key. */
function bhela_bm_match_cabin( $input ) {
	$rates = bhela_bm_get_rates();
	$input = trim( (string) $input );
	if ( isset( $rates[ $input ] ) ) {
		return $input;
	}
	foreach ( $rates as $key => $row ) {
		if ( $input && ( false !== mb_stripos( $row['label'], $input ) || false !== mb_stripos( $input, $row['label'] ) ) ) {
			return $key;
		}
		$first = strtolower( strtok( $row['label'], ' ' ) );
		if ( $first && false !== stripos( $input, $first ) ) {
			return $key;
		}
	}
	return '';
}

/** Calculate price for cabin/guests/date. Returns array|WP_Error. */
function bhela_bm_calc_price( $cabin_key, $guests, $date ) {
	$rates    = bhela_bm_get_rates();
	$settings = bhela_bm_get_settings();
	$guests   = max( 1, (int) $guests );

	if ( ! isset( $rates[ $cabin_key ] ) ) {
		return new WP_Error( 'bad_cabin', __( 'অজানা কেবিন টাইপ।', 'bhela-booking' ) );
	}
	$row      = $rates[ $cabin_key ];
	$day_type = bhela_bm_day_type( $date );
	$per      = ( 'weekday' === $day_type ) ? (int) $row['weekday'] : (int) $row['regular'];
	$total    = $per * $guests;
	$advance  = (int) ceil( $total * ( (float) $settings['advance_percent'] / 100 ) );

	return array(
		'cabin_key'   => $cabin_key,
		'cabin_label' => $row['label'],
		'guests'      => $guests,
		'day_type'    => $day_type,
		'per_person'  => $per,
		'total'       => $total,
		'advance'     => $advance,
		'due'         => $total - $advance,
	);
}

function bhela_bm_money( $amount ) {
	return '৳' . number_format( (float) $amount );
}

/* =========================================================
 * BOOKING STATUSES
 * ========================================================= */

function bhela_bm_statuses() {
	return array(
		'pending'      => __( 'Pending (নতুন রিকোয়েস্ট)', 'bhela-booking' ),
		'advance_paid' => __( 'Advance Paid (অগ্রিম পরিশোধিত)', 'bhela-booking' ),
		'confirmed'    => __( 'Confirmed (নিশ্চিত)', 'bhela-booking' ),
		'completed'    => __( 'Completed (সম্পন্ন)', 'bhela-booking' ),
		'cancelled'    => __( 'Cancelled (বাতিল)', 'bhela-booking' ),
	);
}

function bhela_bm_status_color( $status ) {
	$map = array(
		'pending'      => '#996800',
		'advance_paid' => '#0E6E6B',
		'confirmed'    => '#1a7f37',
		'completed'    => '#555d66',
		'cancelled'    => '#b32d2e',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : '#555d66';
}

/* =========================================================
 * CUSTOM POST TYPE
 * ========================================================= */

function bhela_bm_register_cpt() {
	register_post_type( 'bhela_booking', array(
		'labels' => array(
			'name'          => __( 'Bookings', 'bhela-booking' ),
			'singular_name' => __( 'Booking', 'bhela-booking' ),
			'menu_name'     => __( 'Bookings', 'bhela-booking' ),
			'add_new_item'  => __( 'Add New Booking', 'bhela-booking' ),
			'edit_item'     => __( 'View/Edit Booking', 'bhela-booking' ),
			'all_items'     => __( 'All Bookings', 'bhela-booking' ),
			'search_items'  => __( 'Search Bookings', 'bhela-booking' ),
			'not_found'     => __( 'No bookings found.', 'bhela-booking' ),
		),
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		'menu_position'      => 26,
		'menu_icon'          => 'dashicons-calendar-alt',
		'supports'           => array( 'title' ),
	) );
}
add_action( 'init', 'bhela_bm_register_cpt' );

/* =========================================================
 * MODULES
 * ========================================================= */

require_once BHELA_BM_PATH . 'includes/frontend.php';
require_once BHELA_BM_PATH . 'includes/invoice.php';
require_once BHELA_BM_PATH . 'includes/emails.php';
require_once BHELA_BM_PATH . 'includes/sms.php';
require_once BHELA_BM_PATH . 'includes/trips.php';
require_once BHELA_BM_PATH . 'includes/reviews.php';
if ( is_admin() ) {
	require_once BHELA_BM_PATH . 'includes/guide.php';
}
if ( is_admin() ) {
	require_once BHELA_BM_PATH . 'includes/admin.php';
}

/* =========================================================
 * ACTIVATION
 * ========================================================= */

function bhela_bm_activate() {
	if ( false === get_option( 'bhela_bm_settings', false ) ) {
		add_option( 'bhela_bm_settings', bhela_bm_default_settings() );
	}
	if ( false === get_option( 'bhela_bm_rates', false ) ) {
		add_option( 'bhela_bm_rates', bhela_bm_default_rates() );
	}
	if ( false === get_option( 'bhela_bm_invoice_counter', false ) ) {
		add_option( 'bhela_bm_invoice_counter', 0 );
	}
}
register_activation_hook( __FILE__, 'bhela_bm_activate' );

/* =========================================================
 * SETTINGS UPGRADE (one-time migrations for saved options)
 * ========================================================= */

function bhela_bm_maybe_upgrade() {
	$ver = (int) get_option( 'bhela_bm_settings_version', 0 );
	if ( $ver >= 2 ) {
		return;
	}
	$s = get_option( 'bhela_bm_settings', array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	// v2: new payment numbers + main WhatsApp CTA.
	$s['bkash_number'] = '01703-284728 (Bangla QR — bKash/Bank App)';
	$s['nagad_number'] = '01684-498885 (KEYTO BD)';
	$s['whatsapp']     = '+8801891562461';
	update_option( 'bhela_bm_settings', $s );
	update_option( 'bhela_bm_settings_version', 2 );
}
add_action( 'plugins_loaded', 'bhela_bm_maybe_upgrade' );
