<?php
/**
 * Plugin Name: BHELA Booking Engine
 * Description: Complete booking engine for BHELA – The Haor Exclusive: cabin pricing (weekday/holiday), booking statuses, invoices with secure customer links, and email notifications.
 * Version: 2.38.0
 * Author: 3s-Soft
 * Author URI: https://3s-soft.com
 * License: GPLv2 or later
 * Text Domain: bhela-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_BM_VERSION', '2.38.0' );
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
		// The vessel's government registration, exactly as issued. Printed BARE on
		// every surface — no invented label like "Govt. Reg:", which would risk
		// naming the wrong kind of document. "M.B" is Motor Boat.
		'vessel_reg'       => 'M.B BHELA (M-01-5520)',
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

		// Trip logistics. These were hardcoded into the invoice template and the
		// customer email — changing the boarding ghat or the package length meant
		// editing PHP, in four places, one of which nobody would have found.
		'boarding_ghat'  => 'Anwarpur Ghat',
		'checkin_time'   => '8:00 AM – 10:00 AM',
		'checkout_time'  => '5:00 PM – 7:00 PM',
		'package_label'  => '২ দিন ১ রাত',
		// One note per line. Printed on the confirmation message and the invoice.
		//
		// Worded as a commitment with its limits attached, not as a bare number. "AC
		// Service: 16–18 Hours" invites the guest to ask why it is not 24, at night,
		// on the boat; saying up front that the generator stops for fuel and
		// maintenance answers that before it becomes a complaint.
		'confirm_notes'  => "AC Service: Available for approximately 16–18 hours throughout the trip, subject to scheduled generator breaks for fuel, maintenance, and operational requirements.\n"
			. "Electricity: 24-hour electricity supply is available throughout the trip, subject to normal generator operation.",
		// Blank means "use bhela_bm_confirm_default_template()". Storing the default
		// here instead would freeze today's wording into the database, so a later
		// improvement to the shipped text would never reach a site that had saved
		// its settings once.
		'confirm_template' => '',
		// A promotional layer OVER the rates, never a rewrite of them. Editing
		// bhela_bm_rates to run a promotion destroys the rack rate: when the offer ends
		// there is nothing to restore from, and no "was ৳40,000" to strike through.
		// Both percentages come off `regular` — see bhela_bm_offer_rate().
		'offer_on'      => 0,
		'offer_label'   => '',   // badge text; blank falls back to a generic OFFER
		'offer_regular' => 0,    // % off regular, weekends + holidays
		'offer_weekday' => 0,    // % off regular, weekdays
		'offer_from'    => '',   // travel date; blank = open-ended
		'offer_to'      => '',
		// Investor share structure and the profit split. Configurable because a second
		// boat or a fresh round would otherwise mean editing PHP — the brief is explicit
		// that none of this may be hardcoded.
		'inv_total_investment' => 11500000,
		'inv_total_shares'     => 115,
		'inv_per_share'        => 100000,
		'inv_reserve_pct'      => 10,   // off-season, renovation, maintenance
		'inv_investor_pct'     => 70,   // management takes the remainder, never its own %
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
		'sms_low_balance'    => 100,          // warn on the dashboard at or below this credit
		'sms_tpl_admin'      => "নতুন বুকিং! {invoice} — {name}, {phone}, {date}, {guests} জন, মোট {total}।",
		'sms_tpl_new'        => "প্রিয় {name}, ভেলা হাউসবোটে আপনার বুকিং রিকোয়েস্ট ({invoice}) পেয়েছি। তারিখ {date}। আমরা শীঘ্রই যোগাযোগ করব। — BHELA",
		'sms_tpl_confirmed'  => "প্রিয় {name}, আপনার বুকিং {invoice} এখন: {status}। তারিখ {date}। বাকি {due}। ধন্যবাদ — BHELA",
		'sms_tpl_completed'  => "প্রিয় {name}, ভেলার সাথে ভ্রমণের জন্য ধন্যবাদ! আপনার মতামত জানান: {review_link} — BHELA",

		// Mobile verification (OTP) on the booking form. Off until an SMS
		// gateway is configured — with none, every booking would fall back to
		// email and guests without an address could not book at all.
		'otp_enabled' => 0,
		// Deliberately NOT business_name: that contains an en-dash, which is
		// outside GSM-7 and would double the cost of every OTP sent.
		'otp_brand'   => 'BHELA',

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
 * The `_bhela_cabin_type` label a whole-boat booking carries.
 *
 * Extracted so wp-admin and the booking form cannot drift. This string is what
 * the invoice, both emails, the SMS {cabin} placeholder and the Trip Report all
 * print; a second hand-written copy in the save handler would have shown a guest
 * one wording on the invoice and another in the text message.
 */
function bhela_bm_full_boat_label() {
	return sprintf(
		/* translators: 1: total cabins, 2: total guests */
		__( 'Full Boat — পুরো বোট (%1$d কেবিন / %2$d জন)', 'bhela-booking' ),
		bhela_bm_max_cabins(),
		bhela_bm_max_guests()
	);
}

/**
 * The cabin plan a whole-boat booking is priced from: every cabin, filled.
 *
 * A Full Boat request used to arrive at ৳0 and wait for someone to quote it by
 * hand, which meant the guest saw no number at all and the booking sat unpriced.
 * It now arrives priced at the standard rate for a full boat — every cabin at its
 * maximum occupancy, so 6 × 6 = 36 people — and an admin adjusts it after
 * negotiating. A starting figure that is right most of the time beats a blank.
 *
 * Occupancy comes from the rate table rather than a literal 6, so adding or
 * removing a cabin tier moves this with it. Feeding the plan through
 * bhela_bm_calc_multi() rather than multiplying here is deliberate: weekday
 * discounts, holidays and the advance percentage then apply exactly as they do to
 * any other booking, and there is only one pricing engine to keep correct.
 *
 * @return array Cabin rows, shaped for bhela_bm_calc_multi().
 */
function bhela_bm_full_boat_plan() {
	$occ  = bhela_bm_rates_by_occupancy();
	$size = $occ ? max( array_keys( $occ ) ) : 6;
	return array_fill(
		0,
		bhela_bm_max_cabins(),
		array( 'adults' => (int) $size, 'c48' => 0, 'c04' => 0 )
	);
}

/**
 * What a whole boat costs on a given date, at the standard rate.
 *
 * The one answer both the booking form and the server give, so the figure a guest
 * is shown is the figure that gets stored.
 *
 * @param string $date Y-m-d.
 * @return array|WP_Error The same shape bhela_bm_calc_multi() returns.
 */
function bhela_bm_full_boat_price( $date ) {
	return bhela_bm_calc_multi( bhela_bm_full_boat_plan(), $date );
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
 * Is the promotional offer running for this travel date, and at what percentage?
 *
 * One place decides it, because the engine, the admin preview, the invoice and both
 * JavaScript mirrors all have to agree. A blank end date is open-ended, the same
 * shape bhela_bm_b2b_range() settled on.
 *
 * Matched on the TRAVEL date rather than the booking date: every rate, holiday and
 * report in this system is keyed on when the trip happens, and mixing the two is how
 * a guest gets quoted one price and charged another.
 *
 * @param string $date Travel date, Y-m-d.
 * @return array{active:bool,pct:int,label:string,day_type:string}
 */
function bhela_bm_offer( $date = '' ) {
	$s   = bhela_bm_get_settings();
	$dt  = $date ? bhela_bm_day_type( $date ) : 'weekend';
	$out = array( 'active' => false, 'pct' => 0, 'pct_regular' => 0, 'pct_weekday' => 0,
		'label' => '', 'day_type' => $dt );

	if ( empty( $s['offer_on'] ) ) {
		return $out;
	}
	$day  = bhela_bm_report_date( $date );
	$from = bhela_bm_report_date( $s['offer_from'] ?? '' );
	$to   = bhela_bm_report_date( $s['offer_to'] ?? '' );
	if ( $day && $from && $day < $from ) {
		return $out;
	}
	if ( $day && $to && $day > $to ) {
		return $out;
	}

	// Weekends and holidays share a percentage: both are peak days priced at the
	// regular rate, and the owner set one number for "not a weekday".
	$reg_pct = max( 0, min( 90, (int) ( $s['offer_regular'] ?? 0 ) ) );
	$wk_pct  = max( 0, min( 90, (int) ( $s['offer_weekday'] ?? 0 ) ) );
	if ( $reg_pct <= 0 && $wk_pct <= 0 ) {
		return $out;
	}

	// BOTH percentages are returned, not just the one for $date's own day type.
	// Callers that have no date — the cabins page and the settings preview, which
	// show a weekend AND a weekday column — asked for 'weekday' and silently got the
	// weekend figure back, so the cabins page advertised a 20% weekday discount while
	// the booking form charged 30%.
	$out['active']      = true;
	$out['pct_regular'] = $reg_pct;
	$out['pct_weekday'] = $wk_pct;
	$out['pct']         = ( 'weekday' === $dt ) ? $wk_pct : $reg_pct;
	$out['label']       = trim( (string) ( $s['offer_label'] ?? '' ) );
	if ( '' === $out['label'] ) {
		$out['label'] = __( 'OFFER', 'bhela-booking' );
	}
	return $out;
}

/**
 * The per-person rate actually charged, offer included.
 *
 * **Returns min( standing rate, offer rate ), and that clamp is the whole point.**
 * The offer is computed off `regular`, while the weekday rate is ALREADY 20% off
 * regular — so any weekday percentage below 20 produces a number HIGHER than the
 * standing weekday rate. A 10% "offer" would quietly raise weekday Deluxe from
 * ৳8,000 to ৳9,000.
 *
 * An offer that increases a price is the worst thing this feature could do, so it is
 * made structurally impossible here rather than left for the owner to spot.
 *
 * @param array  $row      A rate row: {regular, weekday, ...}.
 * @param string $day_type weekday | weekend | holiday.
 * @param string $date     Travel date, for the offer window.
 * @return int Rate in taka.
 */
function bhela_bm_offer_rate( $row, $day_type, $date = '' ) {
	$standing = ( 'weekday' === $day_type ) ? (int) $row['weekday'] : (int) $row['regular'];
	$offer    = bhela_bm_offer( $date );
	if ( ! $offer['active'] ) {
		return $standing;
	}
	// Keyed on the $day_type argument, NOT on whatever day $date falls on. The two
	// can legitimately differ: a rate table shows a weekend and a weekday column at
	// once, with no single date behind either.
	$pct = ( 'weekday' === $day_type ) ? (int) $offer['pct_weekday'] : (int) $offer['pct_regular'];
	if ( $pct <= 0 ) {
		return $standing;
	}
	$discounted = (int) round( (int) $row['regular'] * ( 100 - $pct ) / 100 );
	return min( $standing, $discounted );
}

/**
 * A booking's day type, derived at read time from its travel date.
 *
 * `_bhela_day_type` is a cached derivation and it went stale. It is rewritten
 * only by the two repricing branches of bhela_bm_save_booking() — and a booking
 * taken online can enter neither: bhela_bm_process_submission() stamps
 * `_bhela_manual_price = '1'` and never writes `_bhela_cabin_key`, so both
 * conditions guarding the single-cabin reprice are permanently true, and the
 * combination branch needs a checkbox nobody ticks. Moving the Travel Date on
 * such a booking rewrote `_bhela_travel_date` and left the old label behind:
 * 3 August 2026, a Monday, printed "Weekend" on a guest's invoice.
 *
 * Deriving from the date makes the label self-correcting on every read — for the
 * records already in the database as much as for new ones. The stored meta is
 * kept only as the fallback for a booking whose date is missing or malformed,
 * where bhela_bm_day_type() would answer 'weekend' purely because strtotime()
 * failed.
 *
 * @param int $booking_id Booking post ID.
 * @return string 'holiday'|'weekend'|'weekday', or '' when nothing is known.
 */
function bhela_bm_booking_day_type( $booking_id ) {
	$date  = (string) get_post_meta( $booking_id, '_bhela_travel_date', true );
	$valid = DateTime::createFromFormat( 'Y-m-d', $date );
	// The round-trip comparison is what rejects the shapes createFromFormat()
	// accepts loosely — 2026-8-3, 2026-02-31 — rather than quietly answering for
	// a different day. Same guard as bhela_bm_process_submission().
	if ( $valid && $valid->format( 'Y-m-d' ) === $date ) {
		return bhela_bm_day_type( $date );
	}
	return (string) get_post_meta( $booking_id, '_bhela_day_type', true );
}

/**
 * How a guest paid, key => label.
 *
 * The booking edit screen and the Trip Report each had their own copy of this
 * list, and the confirmation message needed a third. One list, so a method added
 * here appears everywhere rather than in whichever screen was remembered.
 * Expenses keep a separate list on purpose — that is money going out, and its
 * methods are owner-editable.
 */
function bhela_bm_pay_methods() {
	return array(
		''      => '—',
		'bkash' => 'bKash',
		'nagad' => 'Nagad',
		'bank'  => 'Bank Transfer',
		'cash'  => 'Cash',
	);
}

/**
 * The stay: check-in and check-out, with their time windows.
 *
 * Check-in is the travel date and check-out the day after — the package is two
 * days, one night. Both are DERIVED on every read rather than stored, for exactly
 * the reason the day type above is: a stored copy goes stale the moment someone
 * moves the travel date, and nothing in the save path is guaranteed to run. A
 * wrong check-out date on a confirmation message is worse than a wrong label,
 * because the guest plans a journey home around it.
 *
 * The time windows are settings, not per booking — the boat leaves when it leaves.
 *
 * @param int $booking_id Booking post ID.
 * @return array{in:string,out:string,in_time:string,out_time:string,nights:int}
 *               `in`/`out` are Y-m-d, or '' when the travel date is missing or
 *               malformed. Callers must handle '' rather than printing it.
 */
function bhela_bm_booking_stay( $booking_id ) {
	$s   = bhela_bm_get_settings();
	$out = array(
		'in'       => '',
		'out'      => '',
		'in_time'  => (string) ( $s['checkin_time'] ?? '' ),
		'out_time' => (string) ( $s['checkout_time'] ?? '' ),
		'nights'   => 1,
	);

	$date  = (string) get_post_meta( $booking_id, '_bhela_travel_date', true );
	$valid = DateTime::createFromFormat( 'Y-m-d', $date );
	// Same round-trip guard as bhela_bm_booking_day_type(): createFromFormat()
	// accepts 2026-02-31 and rolls it into March, which would put check-out on a
	// day the boat is not sailing.
	if ( ! $valid || $valid->format( 'Y-m-d' ) !== $date ) {
		return $out;
	}

	$out['in'] = $date;
	$valid->modify( '+' . $out['nights'] . ' day' );
	$out['out'] = $valid->format( 'Y-m-d' );
	return $out;
}

/**
 * The weekend-day list, as date('w') numbers.
 *
 * Whitelisted to 0–6 because nothing else can ever match date('w'), and an
 * out-of-range value fails silently: it simply never matches, so that day drops
 * off the regular rate and quietly bills at the 20% weekday discount.
 *
 * Two traps this sidesteps, both of which look like the obvious one-liner:
 * array_filter() with no callback would throw Sunday away every single time it
 * was ticked, because Sunday is 0. And mapping intval() over the input before
 * range-checking it turns anything non-numeric INTO Sunday — 'x' becomes 0,
 * which is inside the range and would silently tick Sunday as a weekend. So the
 * numeric test comes first, and the loop is written out rather than chained.
 *
 * @param mixed $raw Whatever the form posted.
 * @return int[] Unique day numbers, 0–6, in the order given.
 */
function bhela_bm_sanitize_weekend_days( $raw ) {
	$days = array();
	foreach ( (array) $raw as $d ) {
		if ( ! is_numeric( $d ) ) {
			continue;
		}
		$n = (int) $d;
		if ( $n >= 0 && $n <= 6 && ! in_array( $n, $days, true ) ) {
			$days[] = $n;
		}
	}
	return $days;
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

/**
 * A taka figure, formatted for display.
 *
 * The sign goes before the symbol. Concatenating number_format() onto the
 * symbol put it after — a loss printed as "৳-215,200", which reads as a
 * currency called "৳-" before it reads as negative money. Every screen that
 * can show a loss was affected: the monthly statement, the yearly report, a
 * cost sheet whose trip lost money, and the profit column of the sheet list.
 *
 * @param int|float|string $amount Taka. Negative is a loss.
 * @return string e.g. "৳1,490,000" or "-৳215,200".
 */
function bhela_bm_money( $amount ) {
	$amount = (float) $amount;
	return ( $amount < 0 ? '-' : '' ) . '৳' . number_format( abs( $amount ) );
}

/**
 * Neutralise a value that a spreadsheet would execute.
 *
 * Excel and LibreOffice treat a cell beginning =, +, - or @ as a formula, so a
 * supplier named `=cmd|' /c calc'!A1` becomes code the moment the owner opens
 * an export. Tab and CR are in the list because both are formula leaders once
 * the cell is re-parsed.
 *
 * A leading apostrophe is the documented escape and every spreadsheet strips it
 * again on import, so the value still reads correctly. Numbers are untouched, so
 * a money or count column stays sortable — which is why this is not a blanket
 * quote.
 *
 * Lives here rather than in a module so every export has it regardless of load
 * order. Apply it to every free-text cell; never to a figure.
 *
 * @param int|float|string $value Raw cell value.
 * @return string Safe to hand to fputcsv().
 */
function bhela_bm_csv_cell( $value ) {
	$value = (string) $value;
	if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
		$value = "'" . $value;
	}
	return $value;
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
 * What a booking still owes, and whether that balance is settled.
 *
 * Five places computed max(0, total − paid) on their own, and they already
 * disagreed in one respect: the Trip Report keeps the sign, because an
 * overpayment is something the owner needs to see, while every guest-facing
 * surface clamps at zero. This owns the clamped reading and the one question
 * none of them could answer: is the balance actually settled?
 *
 * "Settled" deliberately requires a positive total. A full-boat custom-quote
 * request sits at ৳0 until an admin prices it, and 0 − 0 = 0 would otherwise
 * stamp an unpriced enquiry PAID. And >= rather than ===, so a guest who rounded
 * up — ৳50,000 against a ৳45,000 trip — is settled too, not one taka short of it.
 *
 * @param int|string $total Booking total.
 * @param int|string $paid  Amount received.
 * @return array{total:int,paid:int,due:int,settled:bool}
 */
function bhela_bm_balance( $total, $paid ) {
	$total = (int) $total;
	$paid  = (int) $paid;
	return array(
		'total'   => $total,
		'paid'    => $paid,
		'due'     => max( 0, $total - $paid ),
		'settled' => ( $total > 0 && $paid >= $total ),
	);
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
require_once BHELA_BM_PATH . 'includes/audit.php';
// Post types and lock guards load on EVERY request, not just wp-admin. A closed
// month that can still be deleted from WP-CLI or a cron job is not closed, and
// wp_delete_post() from either never reaches an is_admin() block — so the guards
// cannot live in inventory.php with the screens. This file therefore depends on
// nothing above it.
require_once BHELA_BM_PATH . 'includes/inventory-core.php';
// Loaded unconditionally for the same reason inventory-core.php is: a committed
// distribution must not be deletable from WP-CLI or cron.
require_once BHELA_BM_PATH . 'includes/distribution-core.php';
require_once BHELA_BM_PATH . 'includes/valuation-core.php';
// And again for the approved cost sheet. It used to be only a report, so the
// metabox guard was enough; the investor distribution now reads approved sheets
// and nothing else, so a sheet deletable from WP-CLI leaves declared profit
// standing against a trip the books can no longer show.
require_once BHELA_BM_PATH . 'includes/costs-core.php';
require_once BHELA_BM_PATH . 'includes/frontend.php';
require_once BHELA_BM_PATH . 'includes/invoice.php';
require_once BHELA_BM_PATH . 'includes/emails.php';
require_once BHELA_BM_PATH . 'includes/sms.php';
// Loaded outside the is_admin() block below, and it has to be: the {notes} token it
// supplies is available to every SMS template, and an SMS goes out from the public
// booking form where nothing in wp-admin is loaded.
require_once BHELA_BM_PATH . 'includes/confirm.php';
/**
 * Accept a date only in the exact format the meta is stored in, and only if it is
 * a real calendar date. `_bhela_travel_date` is a plain Y-m-d string, so a malformed
 * value would silently match nothing (or everything) rather than error.
 *
 * It lives in core rather than in reports.php because four modules now validate a
 * date this way — the trip report, the cost sheet, the B2B report and its CSV. Two
 * of them had already worked around its absence: costs.php carried a duplicate
 * behind a function_exists() guard, and b2b-report.php simply fataled when the
 * report module happened not to be loaded. A shared helper parked in one screen's
 * file is a load-order accident waiting to happen.
 *
 * @param mixed $value Raw request value.
 * @return string Valid Y-m-d date, or '' when it is not one.
 */
function bhela_bm_report_date( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

require_once BHELA_BM_PATH . 'includes/agencies.php';
require_once BHELA_BM_PATH . 'includes/coupons.php';
require_once BHELA_BM_PATH . 'includes/investors.php';
require_once BHELA_BM_PATH . 'includes/investor-ledger.php';
require_once BHELA_BM_PATH . 'includes/distribution.php';
require_once BHELA_BM_PATH . 'includes/funds.php';
require_once BHELA_BM_PATH . 'includes/valuation.php';
require_once BHELA_BM_PATH . 'includes/share-issue.php';
require_once BHELA_BM_PATH . 'includes/valuation-admin.php';
require_once BHELA_BM_PATH . 'includes/investor-dashboard.php';
require_once BHELA_BM_PATH . 'includes/investor-payreq.php';
require_once BHELA_BM_PATH . 'includes/cashflow.php';
require_once BHELA_BM_PATH . 'includes/investor-portal.php';
require_once BHELA_BM_PATH . 'includes/otp.php';
require_once BHELA_BM_PATH . 'includes/trips.php';
require_once BHELA_BM_PATH . 'includes/reviews.php';
require_once BHELA_BM_PATH . 'includes/gallery.php';
require_once BHELA_BM_PATH . 'includes/spots.php';
if ( is_admin() ) {
	require_once BHELA_BM_PATH . 'includes/guide.php';
}
if ( is_admin() ) {
	// The menu registry comes first: every module below asks it which parent its
	// screen belongs under, so it has to exist before they register anything.
	require_once BHELA_BM_PATH . 'includes/menu.php';
	// The shared UI components come next — every screen below draws on them.
	require_once BHELA_BM_PATH . 'includes/ui.php';
	require_once BHELA_BM_PATH . 'includes/roles.php';
	require_once BHELA_BM_PATH . 'includes/admin.php';
	require_once BHELA_BM_PATH . 'includes/investor-admin.php';
	require_once BHELA_BM_PATH . 'includes/dashboard.php';
	require_once BHELA_BM_PATH . 'includes/reports.php';
	require_once BHELA_BM_PATH . 'includes/costs.php';
require_once BHELA_BM_PATH . 'includes/income.php';
require_once BHELA_BM_PATH . 'includes/trip-report.php';
require_once BHELA_BM_PATH . 'includes/seasons.php';
	require_once BHELA_BM_PATH . 'includes/expenses.php';
	require_once BHELA_BM_PATH . 'includes/statement.php';
	require_once BHELA_BM_PATH . 'includes/b2b-report.php';
	require_once BHELA_BM_PATH . 'includes/yearly.php';
	require_once BHELA_BM_PATH . 'includes/salary.php';
	require_once BHELA_BM_PATH . 'includes/inventory.php';
	require_once BHELA_BM_PATH . 'includes/inventory-import.php';
}

/* =========================================================
 * CLIENT IDENTITY (rate limiting)
 * ========================================================= */

/**
 * The visitor's IP, as far as it can be trusted.
 *
 * Every throttle in this plugin keys on this — booking submits, availability
 * lookups, the tracker, OTP sends, review submissions — so getting it wrong
 * breaks in both directions at once:
 *
 *   Behind a CDN (Cloudflare and BunnyCDN are both common in Bangladesh),
 *   REMOTE_ADDR is the edge node. Every visitor shares one value, so the first
 *   handful of bookings exhaust the quota and the form closes for everybody.
 *
 *   Behind CGNAT, which is how most BD mobile networks hand out addresses,
 *   thousands of subscribers share an address. Five OTP sends and the whole
 *   carrier is locked out.
 *
 * X-Forwarded-For fixes both, but only when something trustworthy sets it —
 * otherwise a client simply forges the header and every limit becomes
 * bypassable. So the proxy must be named explicitly, in wp-config.php:
 *
 *     define( 'BHELA_TRUSTED_PROXIES', array( '203.0.113.10', '198.51.100.0/24' ) );
 *
 * With nothing configured this returns REMOTE_ADDR, exactly as before.
 *
 * @return string IP address, or '' when there is none.
 */
function bhela_bm_client_ip() {
	$clean  = function ( $ip ) {
		$ip = trim( (string) $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	};
	$remote = $clean( $_SERVER['REMOTE_ADDR'] ?? '' );

	$trusted = defined( 'BHELA_TRUSTED_PROXIES' ) ? (array) BHELA_TRUSTED_PROXIES : array();
	if ( ! $remote || ! $trusted || empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return $remote;
	}
	if ( ! bhela_bm_ip_in_list( $remote, $trusted ) ) {
		// The request did not come from a proxy we named, so its forwarding
		// header is just user input.
		return $remote;
	}

	// Leftmost entry is the original client. Everything after it is a hop.
	$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$first = $clean( $parts[0] );
	return $first ?: $remote;
}

/**
 * Is an IP one of these addresses or CIDR ranges?
 *
 * @param string $ip   Address to test.
 * @param array  $list Addresses and/or CIDR blocks.
 * @return bool
 */
function bhela_bm_ip_in_list( $ip, $list ) {
	$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- invalid input is simply not a match.
	if ( false === $packed ) {
		return false;
	}
	foreach ( $list as $entry ) {
		$entry = trim( (string) $entry );
		if ( false === strpos( $entry, '/' ) ) {
			if ( $entry === $ip ) {
				return true;
			}
			continue;
		}
		list( $subnet, $bits ) = array_pad( explode( '/', $entry, 2 ), 2, '' );
		$net = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- as above.
		if ( false === $net || strlen( $net ) !== strlen( $packed ) ) {
			continue;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( $bytes && 0 !== substr_compare( $packed, $net, 0, $bytes ) ) {
			continue;
		}
		if ( $rem ) {
			$mask = chr( 0xFF << ( 8 - $rem ) & 0xFF );
			if ( ( $packed[ $bytes ] & $mask ) !== ( $net[ $bytes ] & $mask ) ) {
				continue;
			}
		}
		return true;
	}
	return false;
}

/* =========================================================
 * ADMIN STYLESHEET
 * ========================================================= */

if ( ! function_exists( 'bhela_bm_is_plugin_screen' ) ) {
	/**
	 * Is the screen we are rendering one of ours?
	 *
	 * Two families: the custom post types, which all carry the `bhela_` prefix,
	 * and the standalone pages, which all use a `bhela-bm-` slug. Nothing else in
	 * wp-admin matches either, so this stays true as screens are added.
	 *
	 * @return bool
	 */
	function bhela_bm_is_plugin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		if ( isset( $screen->post_type ) && 0 === strpos( (string) $screen->post_type, 'bhela_' ) ) {
			return true;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		return 0 === strpos( $page, 'bhela-bm-' );
	}
}

/**
 * Load the design system, and only where it is used.
 *
 * It used to be ~298 lines of inline `<style>` spread over nine files: shipped
 * on every render, never cached, and impossible to change without editing a
 * template. As a real stylesheet it caches against BHELA_BM_VERSION, and the
 * screen check keeps it off Posts, Media, Plugins and the WP dashboard.
 */
function bhela_bm_admin_styles() {
	if ( ! bhela_bm_is_plugin_screen() ) {
		return;
	}
	wp_enqueue_style(
		'bhela-bm-admin',
		BHELA_BM_URL . 'assets/admin.css',
		array(),
		BHELA_BM_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'bhela_bm_admin_styles' );

/**
 * Scope the stylesheet by marking the body, not the page wrapper.
 *
 * The cost sheet and the salary sheet are meta boxes: their markup is emitted
 * by WordPress inside `#poststuff`, well outside any container the plugin
 * writes. A class on `<body>` is the only anchor that reaches every one of our
 * screens — full pages, list tables and editors alike.
 *
 * @param string $classes Space-separated body classes.
 * @return string
 */
function bhela_bm_admin_body_class( $classes ) {
	if ( bhela_bm_is_plugin_screen() ) {
		$classes .= ' bhela-admin';
	}
	return $classes;
}
add_filter( 'admin_body_class', 'bhela_bm_admin_body_class' );

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
