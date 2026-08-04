<?php
/**
 * Plugin Name: BHELA Booking Engine
 * Description: Complete booking engine for BHELA – The Haor Exclusive: cabin pricing (weekday/holiday), booking statuses, invoices with secure customer links, and email notifications.
 * Version: 2.21.0
 * Author: 3s-Soft
 * Author URI: https://3s-soft.com
 * License: GPLv2 or later
 * Text Domain: bhela-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_BM_VERSION', '2.21.0' );
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
		'ops_manager'      => 'Uttam',              // named on the invoice footer
		'support_whatsapp' => '+8801781720957',     // booking-support number
		'advance_percent'  => 50,
		'child_fee'        => 5000, // flat charge per 4–8 year old, any day type
		'date_chips'       => 5,    // how many upcoming trips show as quick-pick chips (0 = hide)
		'weekend_days'     => array( 5, 6 ), // date('w'): 5 = Friday, 6 = Saturday.
		// {advance}/{advance_pct}/{due} are filled per booking, so the terms can
		// never contradict the figures printed above them.
		'invoice_note'     => "বুকিং নিশ্চিত করতে {advance} ({advance_pct}%) অগ্রিম প্রদান করতে হবে। বাকি টাকা অনবোর্ড হওয়ার সময় পরিশোধযোগ্য। ২১+ দিন আগে বাতিলে অগ্রিমের ৫০% ফেরতযোগ্য; ৭ দিনের কম সময়ে কোনো রিফান্ড প্রযোজ্য নয়।",

		// Email notifications.
		'email_enabled'            => 1, // master switch for all emails
		'email_admin_new'          => 1, // notify owner on a new booking
		'email_customer_request'   => 1, // customer "request received" email
		'email_customer_confirmed' => 1, // customer "confirmed" email
		'email_customer_completed' => 1, // customer thank-you + review invite
		'notify_email'             => '', // admin recipient (blank → business email)
		'email_from_name'          => '', // From name (blank → business_name)
		'email_reply_to'           => '', // Reply-To (blank → business email)

		// SMS notifications (provider-agnostic — configure any BD gateway).
		'sms_enabled'        => 0,
		// Per-message switches, mirroring the email ones. Default on so enabling
		// the master switch behaves exactly as it did before these existed.
		'sms_admin_new'           => 1, // new booking → owner
		'sms_customer_request'    => 1, // new booking → customer
		'sms_customer_confirmed'  => 1, // status change → customer
		'sms_customer_completed'  => 1, // trip completed → customer (review invite)
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
		'sms_tpl_completed'  => "প্রিয় {name}, ভেলার সাথে ভ্রমণের জন্য ধন্যবাদ! আপনার মতামত জানান: {review_link} — BHELA",

		// Guest review submissions.
		'review_max_photos'  => 5,   // photos per review
		'review_max_mb'      => 5,   // megabytes per photo
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

/**
 * Holiday dates, as a Y-m-d list.
 *
 * The Trip Calendar's "ছুটি" checkbox is the single source of truth. A holiday
 * only matters for a date the boat actually sails on, so a second list in
 * Settings only gave the owner two places to forget. This also feeds the
 * client-side price preview, keeping JS and PHP on the same day type.
 */
function bhela_bm_holiday_dates() {
	if ( ! function_exists( 'bhela_bm_get_trips' ) ) {
		return array();
	}
	$dates = array();
	foreach ( bhela_bm_get_trips() as $t ) {
		if ( ! empty( $t['holiday'] ) && ! empty( $t['date'] ) ) {
			$dates[] = $t['date'];
		}
	}
	return $dates;
}

/**
 * One-time move of the old Settings holidays textarea onto the trip rows.
 *
 * Any listed date that matches a departure gets its "ছুটি" box ticked, so
 * pricing does not silently change under the owner; dates with no trip never
 * affected a booking, so they are simply dropped with the setting.
 */
function bhela_bm_migrate_holidays() {
	$settings = get_option( 'bhela_bm_settings', array() );
	if ( ! is_array( $settings ) || ! array_key_exists( 'holidays', $settings ) ) {
		return;
	}
	$old = array_filter( array_map( 'trim', explode( "\n", (string) $settings['holidays'] ) ) );

	$trips  = get_option( 'bhela_bm_trips', null );
	$moved  = 0;
	if ( is_array( $trips ) && $old ) {
		foreach ( $trips as $i => $t ) {
			if ( empty( $t['holiday'] ) && in_array( $t['date'] ?? '', $old, true ) ) {
				$trips[ $i ]['holiday'] = true;
				$moved++;
			}
		}
		if ( $moved ) {
			update_option( 'bhela_bm_trips', $trips );
		}
	}

	unset( $settings['holidays'] );
	update_option( 'bhela_bm_settings', $settings );

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'settings', sprintf(
			'Holiday list moved from Settings to the Trip Calendar — "Holiday" ticked on %d trip(s).',
			(int) $moved
		) );
	}
}
add_action( 'admin_init', 'bhela_bm_migrate_holidays' );

/**
 * One-time backfill of `_bhela_cabin_count` for bookings created before that
 * meta existed. The availability engine already falls back to the cabins JSON
 * (or 1), so this only makes the stored count explicit and future-proof.
 */
function bhela_bm_backfill_cabin_count() {
	if ( get_option( 'bhela_bm_cabincount_backfilled' ) ) {
		return;
	}
	$ids = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		if ( '' !== get_post_meta( $id, '_bhela_cabin_count', true ) ) {
			continue;
		}
		$rows = json_decode( (string) get_post_meta( $id, '_bhela_cabins_json', true ), true );
		update_post_meta( $id, '_bhela_cabin_count', is_array( $rows ) && $rows ? count( $rows ) : 1 );
	}
	update_option( 'bhela_bm_cabincount_backfilled', 1, false );
}
add_action( 'admin_init', 'bhela_bm_backfill_cabin_count' );

/** Day type for a Y-m-d date: 'holiday' | 'weekend' | 'weekday'. */
function bhela_bm_day_type( $date ) {
	$settings = bhela_bm_get_settings();
	$ts       = strtotime( $date );
	if ( ! $ts ) {
		return 'weekend';
	}
	if ( in_array( date( 'Y-m-d', $ts ), bhela_bm_holiday_dates(), true ) ) {
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

/**
 * International display form: "+880 1703-284728".
 *
 * The same number reaches the invoice in three shapes — a guest types
 * 01703284728, Settings holds 01891-562461, and the WhatsApp field holds
 * +8801781720957 — which made one page show three formats. Anything that is not
 * a valid BD mobile (a landline, a free-text note) is returned untouched rather
 * than mangled.
 */
function bhela_bm_phone_intl( $raw ) {
	$local = bhela_bm_normalize_mobile( $raw );
	if ( '' === $local ) {
		return trim( (string) $raw );
	}
	return '+880 ' . substr( $local, 1, 4 ) . '-' . substr( $local, 5 );
}

/** tel: href for a number in any format, or '' when it is not dialable. */
function bhela_bm_phone_href( $raw ) {
	$local = bhela_bm_normalize_mobile( $raw );
	return $local ? 'tel:+880' . substr( $local, 1 ) : '';
}

/**
 * wa.me link for a number in any format.
 *
 * @param string $raw  Number in any shape.
 * @param string $text Optional prefilled message.
 */
function bhela_bm_wa_url( $raw, $text = '' ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $raw );
	if ( '' === $digits ) {
		return '';
	}
	if ( 0 !== strpos( $digits, '880' ) ) {
		$local  = bhela_bm_normalize_mobile( $digits );
		$digits = $local ? '880' . substr( $local, 1 ) : $digits;
	}
	return 'https://wa.me/' . $digits . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
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
		return new WP_Error( 'bad_cabin', __( 'Unknown cabin type.', 'bhela-booking' ) );
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

/**
 * The advance expressed as a percentage of the booking total.
 *
 * Derived from the stored amount, NOT from the advance_percent setting: the
 * admin sets each booking's advance freely (a ৳60,000 trip may be taken on
 * ৳40,000 or ৳20,000 — it is their call), so the label has to follow the money
 * or it will contradict the figure printed beside it. Returns 0 when there is
 * no total or no advance.
 *
 * Rounding to a whole number used to make the label contradict the money it sits
 * beside: ৳5,000 of ৳32,000 is 15.625%, which rounded to "16%" — but 16% of
 * ৳32,000 is ৳5,120. So this keeps two decimals and trims them away only when
 * they are meaningless, giving "15.63" but a clean "50".
 *
 * @param int|string $advance Amount taken as advance.
 * @param int|string $total   Booking total.
 * @return string Percentage for display, 0-100, without a trailing "%".
 */
function bhela_bm_advance_pct( $advance, $total ) {
	$advance = (int) $advance;
	$total   = (int) $total;
	if ( $total <= 0 || $advance <= 0 ) {
		return '0';
	}
	// number_format() always emits the decimal point, and that point stops the
	// first rtrim() from eating a whole number's own zeros (100.00 → 100, not 1).
	return rtrim( rtrim( number_format( $advance / $total * 100, 2, '.', '' ), '0' ), '.' );
}

/**
 * Fill {placeholders} in the invoice note with this booking's real figures.
 *
 * The note used to state a flat "৫০% অগ্রিম" in prose. Since the advance became
 * a per-booking amount the owner sets freely, that could sit directly beneath an
 * "Advance (33%)" line and contradict it. Writing the note with placeholders
 * keeps the terms and the numbers above them in agreement.
 *
 * @param string $note    Raw note from settings.
 * @param array  $invoice The $invoice array built in includes/invoice.php.
 * @return string
 */
function bhela_bm_render_invoice_note( $note, $invoice ) {
	$total   = (int) ( $invoice['total'] ?? 0 );
	$advance = (int) ( $invoice['advance'] ?? 0 );
	$paid    = (int) ( $invoice['paid'] ?? 0 );
	$bn      = function_exists( 'bhela_bm_bn_num' ) ? 'bhela_bm_bn_num' : 'strval';

	return strtr( (string) $note, array(
		'{total}'       => bhela_bm_money( $total ),
		'{advance}'     => bhela_bm_money( $advance ),
		'{advance_pct}' => $bn( bhela_bm_advance_pct( $advance, $total ) ),
		'{paid}'        => bhela_bm_money( $paid ),
		'{due}'         => bhela_bm_money( max( 0, $total - $paid ) ),
	) );
}

/* =========================================================
 * BOOKING STATUSES
 * ========================================================= */

/**
 * Status labels for the admin and for SMS — English only.
 *
 * The wp-admin side of this plugin is English throughout. Keeping SMS on the
 * same map matters for a second reason: messages are billed per segment and
 * Bengali forces Unicode encoding (70 characters a part instead of 160), so a
 * bilingual status label silently inflates the cost of every message sent.
 * Guest-facing Bengali lives in bhela_bm_status_bn().
 */
function bhela_bm_statuses() {
	return array(
		'pending'      => __( 'Pending', 'bhela-booking' ),
		'advance_paid' => __( 'Advance Paid', 'bhela-booking' ),
		'confirmed'    => __( 'Confirmed', 'bhela-booking' ),
		'completed'    => __( 'Completed', 'bhela-booking' ),
		'cancelled'    => __( 'Cancelled', 'bhela-booking' ),
	);
}

/**
 * Bengali status labels for guest-facing output (the public booking tracker).
 *
 * @param string $status Status key.
 * @return string Bengali label, or the raw key if unknown.
 */
function bhela_bm_status_bn( $status ) {
	$map = array(
		'pending'      => 'নতুন রিকোয়েস্ট',
		'advance_paid' => 'অগ্রিম পরিশোধিত',
		'confirmed'    => 'নিশ্চিত',
		'completed'    => 'সম্পন্ন',
		'cancelled'    => 'বাতিল',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

function bhela_bm_status_color( $status ) {
	// Drives the invoice badge, the bookings list pill and the dashboard counters,
	// so the same status reads the same colour everywhere: money still owed warms
	// towards amber, an agreed booking is blue, a finished one green.
	$map = array(
		'pending'      => '#b45309', // amber — nothing paid yet
		'advance_paid' => '#ca8a04', // yellow — part paid
		'confirmed'    => '#1d4ed8', // blue — locked in
		'completed'    => '#1a7f37', // green — done and settled
		'cancelled'    => '#3c434a', // dark grey — closed
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : '#3c434a';
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
		// Own capability type, not 'post'. Booking staff must be able to run
		// reservations without edit_posts, which would also hand them the
		// site's pages, posts and every other plugin's content.
		'capability_type'    => array( 'bhela_booking', 'bhela_bookings' ),
		'map_meta_cap'       => true,
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

require_once BHELA_BM_PATH . 'includes/log.php';
require_once BHELA_BM_PATH . 'includes/frontend.php';
require_once BHELA_BM_PATH . 'includes/invoice.php';
require_once BHELA_BM_PATH . 'includes/emails.php';
require_once BHELA_BM_PATH . 'includes/sms.php';
require_once BHELA_BM_PATH . 'includes/trips.php';
require_once BHELA_BM_PATH . 'includes/reviews.php';
require_once BHELA_BM_PATH . 'includes/gallery.php';
require_once BHELA_BM_PATH . 'includes/spots.php';
if ( is_admin() ) {
	require_once BHELA_BM_PATH . 'includes/guide.php';
}
if ( is_admin() ) {
	require_once BHELA_BM_PATH . 'includes/roles.php';
	require_once BHELA_BM_PATH . 'includes/admin.php';
	require_once BHELA_BM_PATH . 'includes/dashboard.php';
	require_once BHELA_BM_PATH . 'includes/reports.php';
	require_once BHELA_BM_PATH . 'includes/costs.php';
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
	// Staff roles. The module is admin-only, so on a front-end activation path
	// it may not be loaded yet.
	if ( ! function_exists( 'bhela_bm_install_roles' ) ) {
		require_once BHELA_BM_PATH . 'includes/roles.php';
	}
	bhela_bm_install_roles();
	update_option( 'bhela_bm_roles_version', BHELA_BM_ROLES_VERSION );
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
