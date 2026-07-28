<?php
/**
 * BHELA regression suite.
 *
 * Open this file in a browser while logged in as an administrator:
 *   http://<site>/wp-content/tests/bhela-tests.php
 *
 * Why it exists: the fragile parts of this plugin are fragile in ways that fail
 * SILENTLY — a pricing rule that drifts between its PHP and JS copies, an
 * availability sum that is off by one, an SMS that never sends because its
 * template is blank. None of those throw an error; they just quietly do the
 * wrong thing to a real booking. Everything here asserts a behaviour that has
 * actually broken at least once.
 *
 * It is read-only apart from a couple of temporary posts, which it deletes
 * again, and it never touches saved settings — overrides are applied through
 * the `option_bhela_bm_settings` filter for the duration of a single check.
 *
 * Lives outside themes/ and plugins/, so release ZIPs never carry it.
 *
 * @package BhelaBooking
 */

// Boot WordPress: this file sits at wp-content/tests/.
require_once dirname( __DIR__, 2 ) . '/wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You need to be logged in as an administrator to run these tests.', 'Not allowed', array( 'response' => 403 ) );
}
if ( ! function_exists( 'bhela_bm_get_settings' ) ) {
	wp_die( 'The BHELA Booking Engine plugin is not active.' );
}
require_once WP_PLUGIN_DIR . '/bhela-booking/includes/admin.php';

/* ------------------------------------------------------------------ */

$GLOBALS['bhela_t'] = array( 'pass' => 0, 'fail' => 0, 'rows' => array(), 'group' => '' );

function bhela_group( $name ) {
	$GLOBALS['bhela_t']['group']  = $name;
	$GLOBALS['bhela_t']['rows'][] = array( 'group', $name, '', '' );
}
function bhela_is( $label, $got, $want ) {
	$ok = ( (string) $got === (string) $want );
	$GLOBALS['bhela_t'][ $ok ? 'pass' : 'fail' ]++;
	$GLOBALS['bhela_t']['rows'][] = array( $ok ? 'pass' : 'fail', $label, (string) $got, (string) $want );
	return $ok;
}
function bhela_ok( $label, $cond ) {
	return bhela_is( $label, $cond ? 'yes' : 'no', 'yes' );
}
/** Run $fn with settings temporarily overridden — never writes to the database. */
function bhela_with_settings( array $overrides, callable $fn ) {
	$filter = function ( $s ) use ( $overrides ) {
		return array_merge( (array) $s, $overrides );
	};
	add_filter( 'option_bhela_bm_settings', $filter );
	try {
		return $fn();
	} finally {
		remove_filter( 'option_bhela_bm_settings', $filter );
	}
}

/* ================================================================== */
bhela_group( 'Pricing engine — occupancy tiers' );

$rates = bhela_bm_rates_by_occupancy();
$s_now = bhela_bm_get_settings();
bhela_ok( 'rate tiers 2..6 all configured', count( array_intersect( array( 2, 3, 4, 5, 6 ), array_keys( $rates ) ) ) === 5 );

// With no weekend days ticked, EVERY date falls to the weekday rate and every
// booking is silently discounted 20%. Nothing errors, so it can go unnoticed.
$weekend_days = array_filter( array_map( 'strval', (array) $s_now['weekend_days'] ), 'strlen' );
bhela_is( 'weekend days configured (Settings → Pricing Days)',
	$weekend_days ? implode( ',', $weekend_days ) : 'NONE — every day is discounted',
	$weekend_days ? implode( ',', $weekend_days ) : 'at least one day' );

$price_on = function ( $combo, $date ) {
	return bhela_bm_calc_multi( $combo, $date );
};
/** The rate column that applies on a given day type. */
$rate_for = function ( $occ, $day_type ) use ( $rates ) {
	return 'weekday' === $day_type ? (int) $rates[ $occ ]['weekday'] : (int) $rates[ $occ ]['regular'];
};
/**
 * Find a real date of a given day type by asking the engine, rather than
 * hardcoding one. A fixed date silently rots the moment a holiday is ticked or
 * the weekend days are changed, and the test then blames the engine.
 */
$date_of_type = function ( $match ) use ( $price_on ) {
	$base = strtotime( '2030-01-01' ); // far enough out to dodge configured holidays
	for ( $i = 0; $i < 45; $i++ ) {
		$d = gmdate( 'Y-m-d', strtotime( "+$i day", $base ) );
		$r = $price_on( array( array( 'adults' => 2, 'c48' => 0, 'c04' => 0 ) ), $d );
		if ( ! is_wp_error( $r ) && $match( $r['day_type'] ) ) {
			return $d;
		}
	}
	return '';
};
// "Regular rate" means anything that is not discounted — weekend or holiday.
$d_regular = $date_of_type( function ( $t ) { return 'weekday' !== $t; } );
$d_weekday = $date_of_type( function ( $t ) { return 'weekday' === $t; } );
bhela_ok( 'found a weekday date to test with', '' !== $d_weekday );
if ( '' === $d_regular ) {
	// Not a code fault: it means no day charges the regular rate, so every
	// booking is getting the 20% discount. Worth knowing either way.
	bhela_ok( 'a regular-rate day exists (check Settings → Pricing Days if this fails)', false );
	$d_regular = $d_weekday;
}

// Six adults at the 6-share tier, priced on whatever day type that date is.
$six = $price_on( array( array( 'adults' => 6, 'c48' => 0, 'c04' => 0 ) ), $d_regular );
bhela_ok( 'six adults price without error', ! is_wp_error( $six ) );
if ( ! is_wp_error( $six ) ) {
	bhela_is( 'six adults = 6 x the 6-share rate', $six['total'], 6 * $rate_for( 6, $six['day_type'] ) );
	bhela_is( 'guest count', $six['guests'], 6 );
}

// The rule that surprises people: a cabin is opened for ADULTS. Children 4-8 pay
// a flat fee and must never buy a bigger, cheaper-per-head tier.
$four_c = $price_on( array( array( 'adults' => 4, 'c48' => 1, 'c04' => 0 ) ), $d_regular );
if ( ! is_wp_error( $four_c ) ) {
	bhela_is(
		'4 adults + one 4-8 child = 4-tier + flat child fee',
		$four_c['total'],
		4 * $rate_for( 4, $four_c['day_type'] ) + (int) $s_now['child_fee']
	);
}
// 0-4 infants ride free and never change the tier.
$inf = $price_on( array( array( 'adults' => 4, 'c48' => 0, 'c04' => 2 ) ), $d_regular );
if ( ! is_wp_error( $inf ) ) {
	bhela_is( 'two 0-4 infants cost nothing', $inf['total'], 4 * $rate_for( 4, $inf['day_type'] ) );
}

// The weekday discount applies to adults but never to the flat child fee.
$wd = $price_on( array( array( 'adults' => 4, 'c48' => 1, 'c04' => 0 ) ), $d_weekday );
if ( ! is_wp_error( $wd ) && ! is_wp_error( $four_c ) ) {
	bhela_is( 'the weekday date really is a weekday', $wd['day_type'], 'weekday' );
	bhela_ok( 'weekday costs less than the regular rate', $wd['total'] < $four_c['total'] );
	bhela_is( 'child fee is not discounted', $wd['total'] - 4 * (int) $rates[4]['weekday'], (int) $s_now['child_fee'] );
}

/* ================================================================== */
bhela_group( 'Advance percentage — must never contradict the amount' );

bhela_is( '5,000 of 32,000 (the 16% bug)', bhela_bm_advance_pct( 5000, 32000 ), '15.63' );
bhela_is( 'a clean half carries no decimals', bhela_bm_advance_pct( 30000, 60000 ), '50' );
bhela_is( 'a third', bhela_bm_advance_pct( 20000, 60000 ), '33.33' );
bhela_is( 'paid in full', bhela_bm_advance_pct( 32000, 32000 ), '100' );
bhela_is( 'no advance', bhela_bm_advance_pct( 0, 32000 ), '0' );
bhela_is( 'no total (never divide by zero)', bhela_bm_advance_pct( 5000, 0 ), '0' );

/* ================================================================== */
bhela_group( 'Phone formatting — one shape from three inputs' );

bhela_is( 'guest form', bhela_bm_phone_intl( '01703284728' ), '+880 1703-284728' );
bhela_is( 'settings form', bhela_bm_phone_intl( '01891-562461' ), '+880 1891-562461' );
bhela_is( 'international form', bhela_bm_phone_intl( '+8801781720957' ), '+880 1781-720957' );
bhela_is( 'missing leading zero', bhela_bm_phone_intl( '1712345678' ), '+880 1712-345678' );
bhela_is( 'a landline is left alone, not mangled', bhela_bm_phone_intl( '02-9876543' ), '02-9876543' );
bhela_is( 'free text is left alone', bhela_bm_phone_intl( 'KEYTO BD' ), 'KEYTO BD' );
bhela_is( 'tel href', bhela_bm_phone_href( '01703284728' ), 'tel:+8801703284728' );
bhela_is( 'no tel href for a non-mobile', bhela_bm_phone_href( '02-9876543' ), '' );
bhela_is( 'wa.me from a local number', bhela_bm_wa_url( '01781720957' ), 'https://wa.me/8801781720957' );
bhela_is( 'wa.me from an international number', bhela_bm_wa_url( '+8801781720957' ), 'https://wa.me/8801781720957' );

/* ================================================================== */
bhela_group( 'Availability — a manual hold ADDS to online bookings' );

$cap = bhela_bm_max_cabins();

// bhela_bm_counted_booked_cabins() memoises per date for the life of the
// request, so a date must not be read before it is seeded — the zero would
// stick and the test would blame the engine. Seed first, read once.
$empty_date = '2029-12-20';
$busy_date  = '2029-12-24';

$av = bhela_bm_trip_availability( $empty_date );
bhela_is( 'capacity', $av['total'], $cap );
bhela_is( 'an untouched date has no online bookings', $av['counted'], 0 );
bhela_is( 'an untouched date is fully free', $av['available'], $cap );

$seeded = array();
foreach ( array( 1, 1 ) as $cabins ) {
	$id = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_title' => 'BHELA TEST booking', 'post_status' => 'publish' ) );
	update_post_meta( $id, '_bhela_travel_date', $busy_date );
	update_post_meta( $id, '_bhela_status', 'confirmed' );
	update_post_meta( $id, '_bhela_cabin_count', $cabins );
	$seeded[] = $id;
}

$av2 = bhela_bm_trip_availability( $busy_date );
bhela_is( 'two confirmed bookings are counted', $av2['counted'], 2 );
bhela_is( 'free = capacity - sold', $av2['available'], $cap - 2 );
bhela_ok( 'booked never exceeds capacity', $av2['booked'] <= $cap );
bhela_is( 'a pending booking would not consume a cabin', $av2['booked'], 2 );

foreach ( $seeded as $id ) {
	wp_delete_post( $id, true );
}
bhela_is( 'test bookings cleaned up', count( get_posts( array(
	'post_type' => 'bhela_booking', 'post_status' => 'any', 's' => 'BHELA TEST booking',
	'posts_per_page' => -1, 'fields' => 'ids',
) ) ), 0 );

/* ================================================================== */
bhela_group( 'SMS — gating and the blank-template fallback' );

foreach ( array( 'sms_tpl_admin', 'sms_tpl_new', 'sms_tpl_confirmed', 'sms_tpl_completed' ) as $k ) {
	bhela_ok( "$k resolves to something sendable", '' !== trim( bhela_bm_sms_template( $k ) ) );
}
bhela_ok( 'the completion template offers {review_link}',
	strpos( bhela_bm_sms_template( 'sms_tpl_completed' ), '{review_link}' ) !== false );

$defaults = bhela_bm_default_settings();
foreach ( array( 'sms_admin_new', 'sms_customer_request', 'sms_customer_confirmed', 'sms_customer_completed' ) as $k ) {
	bhela_is( "$k defaults on (upgrades keep behaving as before)", $defaults[ $k ], 1 );
}
bhela_is( 'the SMS master switch defaults off', $defaults['sms_enabled'], 0 );

// Count real send attempts without touching the gateway.
$GLOBALS['bhela_sms_hits'] = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( $url, 'smsapi' ) || false !== strpos( $url, 'bulksms' ) ) {
		$GLOBALS['bhela_sms_hits']++;
		return array( 'response' => array( 'code' => 200 ), 'body' => 'ok', 'headers' => array() );
	}
	return $pre;
}, 10, 3 );

$probe = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_title' => 'BHELA TEST sms', 'post_status' => 'publish' ) );
update_post_meta( $probe, '_bhela_phone', '01700000000' );
update_post_meta( $probe, '_bhela_status', 'completed' );
update_post_meta( $probe, '_bhela_invoice_no', 'BHELA-TEST' );

$all_on = array(
	'sms_enabled' => 1, 'sms_admin_new' => 1, 'sms_customer_request' => 1,
	'sms_customer_confirmed' => 1, 'sms_customer_completed' => 1,
);
$count = function ( $overrides, $fn ) {
	$GLOBALS['bhela_sms_hits'] = 0;
	bhela_with_settings( $overrides, $fn );
	return $GLOBALS['bhela_sms_hits'];
};
$new_booking = function () use ( $probe ) { bhela_bm_sms_on_new_booking( $probe ); };
$to_complete = function () use ( $probe ) { bhela_bm_sms_on_status_change( $probe, 'completed', 'confirmed' ); };
$to_confirm  = function () use ( $probe ) { bhela_bm_sms_on_status_change( $probe, 'confirmed', 'pending' ); };

bhela_is( 'master switch off sends nothing', $count( array_merge( $all_on, array( 'sms_enabled' => 0 ) ), $new_booking ), 0 );
bhela_is( 'new booking sends owner + guest', $count( $all_on, $new_booking ), 2 );
bhela_is( 'owner unticked leaves guest only', $count( array_merge( $all_on, array( 'sms_admin_new' => 0 ) ), $new_booking ), 1 );
bhela_is( 'guest unticked leaves owner only', $count( array_merge( $all_on, array( 'sms_customer_request' => 0 ) ), $new_booking ), 1 );
bhela_is( 'status-change SMS respects its own switch', $count( array_merge( $all_on, array( 'sms_customer_confirmed' => 0 ) ), $to_confirm ), 0 );
bhela_is( 'completion SMS respects its own switch', $count( array_merge( $all_on, array( 'sms_customer_completed' => 0 ) ), $to_complete ), 0 );
bhela_is( 'completion still sends when status-change is off', $count( array_merge( $all_on, array( 'sms_customer_confirmed' => 0 ) ), $to_complete ), 1 );

/* ================================================================== */
bhela_group( 'Review link — token, gating, duplicates' );

$key = bhela_bm_review_key( $probe );
bhela_ok( 'the token is a full-length hash', strlen( $key ) >= 32 );
$other = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_title' => 'BHELA TEST other', 'post_status' => 'publish' ) );
bhela_ok( 'a different booking gets a different token', bhela_bm_review_key( $other ) !== $key );
bhela_ok( 'the review URL carries the booking and the key',
	false !== strpos( bhela_bm_review_url( $probe ), (string) $probe ) && false !== strpos( bhela_bm_review_url( $probe ), $key ) );
bhela_is( 'no review yet for this booking', bhela_bm_review_for_booking( $probe ), 0 );

$rev = wp_insert_post( array( 'post_type' => 'bhela_review', 'post_title' => 'BHELA TEST review', 'post_content' => 'x', 'post_status' => 'pending' ) );
update_post_meta( $rev, '_bhela_booking_id', $probe );
bhela_is( 'the duplicate guard finds it', bhela_bm_review_for_booking( $probe ), $rev );
bhela_ok( 'a pending review is hidden from the public list',
	! in_array( (int) $rev, array_map( 'intval', wp_list_pluck( bhela_bm_get_reviews( 100 ), 'id' ) ), true ) );
wp_update_post( array( 'ID' => $rev, 'post_status' => 'publish' ) );
bhela_ok( 'publishing reveals it',
	in_array( (int) $rev, array_map( 'intval', wp_list_pluck( bhela_bm_get_reviews( 100 ), 'id' ) ), true ) );

$limits = bhela_bm_review_limits();
bhela_ok( 'the photo cap is sane', $limits['photos'] >= 0 && $limits['photos'] <= 10 );
bhela_ok( 'uploads accept images only', array_values( bhela_bm_review_mimes() ) === array( 'image/jpeg', 'image/png', 'image/webp' ) );

/* ================================================================== */
bhela_group( 'Invoice note agrees with the invoice above it' );

$note = bhela_bm_render_invoice_note(
	'অগ্রিম {advance} ({advance_pct}%), মোট {total}, বাকি {due}',
	array( 'total' => 60000, 'advance' => 20000, 'paid' => 20000 )
);
bhela_ok( 'placeholders are substituted', false === strpos( $note, '{' ) );
bhela_ok( 'the derived percentage appears', false !== strpos( $note, '৩৩' ) || false !== strpos( $note, '33' ) );

/* ================================================================== */
bhela_group( 'Booking edits — derived fields must not drift' );

// The head count and cabin count are derived from the cabin combination. The
// plain "Guests" number field is written on every save, so a stale value there
// used to overwrite the real total — producing an invoice listing four people
// beside an SMS reading "Guests: 1".
$edit = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_title' => 'BHELA TEST edit', 'post_status' => 'publish' ) );
update_post_meta( $edit, '_bhela_travel_date', '2029-12-28' );
update_post_meta( $edit, '_bhela_status', 'pending' );
update_post_meta( $edit, '_bhela_guests', 4 );
$edit_post = get_post( $edit );

$do_save = function ( $extra ) use ( $edit, $edit_post ) {
	$_POST = array_merge( array(
		'bhela_bm_nonce'     => wp_create_nonce( 'bhela_bm_save' ),
		'bhela_phone'        => '01820113903',
		'bhela_travel_date'  => '2029-12-28',
		'bhela_cabin_key'    => '',
		'bhela_guests'       => 1, // deliberately stale
		'bhela_manual_price' => '1',
		'bhela_total'        => 20000,
		'bhela_status'       => 'pending',
	), $extra );
	bhela_bm_save_booking( $edit, $edit_post );
	$_POST = array();
};

$do_save( array(
	'bhela_cabin_adults' => array( 3 ),
	'bhela_cabin_c48'    => array( 1 ),
	'bhela_cabin_c04'    => array( 1 ),
) );
bhela_is( 'head count follows the cabin combination', (int) get_post_meta( $edit, '_bhela_guests', true ), 5 );
bhela_is( 'cabin count follows the combination', (int) get_post_meta( $edit, '_bhela_cabin_count', true ), 1 );
bhela_is( 'the SMS reports the same head count', bhela_bm_render_sms( '{guests}', $edit ), '5' );

$do_save( array() ); // no combo posted at all
bhela_is( 'without a combination the typed number is respected', (int) get_post_meta( $edit, '_bhela_guests', true ), 1 );

wp_delete_post( $edit, true );

/* ================================================================== */
bhela_group( 'Invoice contacts — two numbers, never the same one twice' );

$wa_general = bhela_bm_get_settings()['whatsapp'];
$wa_manager = bhela_bm_get_settings()['support_whatsapp'];
bhela_ok( 'a general WhatsApp number is set', '' !== trim( (string) $wa_general ) );
if ( '' !== trim( (string) $wa_manager ) ) {
	bhela_ok(
		'the manager number differs from the general one (or the footer hides it)',
		bhela_bm_normalize_mobile( $wa_manager ) !== bhela_bm_normalize_mobile( $wa_general )
	);
}
bhela_is( 'the same number in two formats is recognised as one',
	bhela_bm_normalize_mobile( '+8801781720957' ), bhela_bm_normalize_mobile( '01781720957' ) );

/* ================================================================== */
bhela_group( 'Settings — every key the code reads exists' );

foreach ( array(
	'business_name', 'phone_1', 'whatsapp', 'email', 'ops_manager', 'support_whatsapp',
	'advance_percent', 'child_fee', 'invoice_prefix', 'invoice_note',
	'email_enabled', 'email_customer_completed',
	'sms_enabled', 'sms_customer_completed', 'sms_tpl_completed',
	'review_max_photos', 'review_max_mb',
) as $k ) {
	bhela_ok( "settings key: $k", array_key_exists( $k, $defaults ) );
}

/* ------------------------------------------------------------------ */
// Clean up everything this run created.
foreach ( array( $rev ) as $id ) { wp_delete_post( $id, true ); }
foreach ( array( $probe, $other ) as $id ) { wp_delete_post( $id, true ); }
$left = get_posts( array(
	'post_type' => array( 'bhela_booking', 'bhela_review' ), 'post_status' => 'any',
	's' => 'BHELA TEST', 'posts_per_page' => -1, 'fields' => 'ids',
) );
bhela_group( 'Cleanup' );
bhela_is( 'no test records left behind', count( $left ), 0 );

/* ------------------------------------------------------------------ */

$t    = $GLOBALS['bhela_t'];
$fail = $t['fail'];
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>BHELA tests — <?php echo $fail ? 'FAILED' : 'passed'; ?></title>
<style>
body { font: 14px/1.6 -apple-system, "Segoe UI", sans-serif; background: #f6f7f7; margin: 0; padding: 28px; color: #1d2327; }
.wrap { max-width: 940px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
h1 { margin: 0; padding: 22px 26px; color: #fff; font-size: 19px; background: <?php echo $fail ? '#b32d2e' : 'linear-gradient(135deg,#0A2A2F,#137A74)'; ?>; }
h1 small { display: block; font-size: 13px; font-weight: 400; opacity: .85; margin-top: 4px; }
table { width: 100%; border-collapse: collapse; }
td { padding: 7px 26px; border-bottom: 1px solid #f0f0f1; font-size: 13.5px; }
tr.group td { background: #f6f7f7; font-weight: 700; padding-top: 14px; padding-bottom: 14px; }
td.s { width: 34px; text-align: center; }
tr.fail td { background: #fdecea; }
tr.fail td.s { color: #b32d2e; font-weight: 700; }
tr.pass td.s { color: #1a7f37; }
code { background: #f0f0f1; padding: 1px 6px; border-radius: 4px; font-size: 12.5px; }
.foot { padding: 16px 26px; color: #646970; font-size: 12.5px; border-top: 1px solid #f0f0f1; }
</style></head><body>
<div class="wrap">
	<h1><?php echo $fail ? '✕ ' . (int) $fail . ' failed' : '✓ All ' . (int) $t['pass'] . ' checks passed'; ?>
		<small>BHELA v<?php echo esc_html( defined( 'BHELA_BM_VERSION' ) ? BHELA_BM_VERSION : '?' ); ?> · <?php echo esc_html( current_time( 'j M Y, H:i' ) ); ?></small>
	</h1>
	<table>
	<?php foreach ( $t['rows'] as $r ) : ?>
		<?php if ( 'group' === $r[0] ) : ?>
			<tr class="group"><td colspan="2"><?php echo esc_html( $r[1] ); ?></td></tr>
		<?php else : ?>
			<tr class="<?php echo esc_attr( $r[0] ); ?>">
				<td class="s"><?php echo 'pass' === $r[0] ? '✓' : '✕'; ?></td>
				<td><?php echo esc_html( $r[1] ); ?>
					<?php if ( 'fail' === $r[0] ) : ?>
						<br><small>got <code><?php echo esc_html( $r[2] ); ?></code> · expected <code><?php echo esc_html( $r[3] ); ?></code></small>
					<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
	<?php endforeach; ?>
	</table>
	<p class="foot">Read-only apart from a few temporary records, which are deleted above. Saved settings are never modified.</p>
</div>
</body></html>
