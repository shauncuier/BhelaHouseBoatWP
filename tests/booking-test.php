<?php
/**
 * The booking save handler and the guest-facing invoice.
 *
 * Three things this pins down, all of which shipped broken:
 *
 *   1. The paid-in-full predicate. It has to say yes when the money is in and no
 *      when there is no price yet — a full-boat quote sits at ৳0 until an admin
 *      prices it, and 0 − 0 = 0 would otherwise stamp an enquiry PAID.
 *   2. Full Boat, created from wp-admin. It takes every cabin, so the cabin count
 *      the capacity guard reads has to be 6 no matter what the combination table
 *      shows, and a leftover "Recalculate" tick must not reprice it.
 *   3. The day type. `_bhela_day_type` was a cache that went stale: a booking
 *      moved to 3 August 2026, a Monday, kept printing "Weekend".
 *
 * @package BhelaBooking
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'log', 'trips', 'invoice', 'emails', 'admin' );

wp_set_current_user( 1 );

$monday = '2026-08-03'; // The production date from the bug report.
$friday = '2026-08-07';
$made   = array();

/** A ZZ-prefixed booking, so bhela_test_isolate() can see it and nothing else can. */
function bk_new( $title ) {
	global $made;
	$id = wp_insert_post( array(
		'post_type'   => 'bhela_booking',
		'post_status' => 'publish',
		'post_title'  => 'ZZ ' . $title,
	) );
	$made[] = $id;
	return $id;
}

/** Post to bhela_bm_save_booking() the way wp-admin does, nonce and all. */
function bk_save( $id, $post = array() ) {
	$_POST = array_merge( array(
		'bhela_bm_nonce'    => wp_create_nonce( 'bhela_bm_save' ),
		'bhela_phone'       => '01712345678',
		'bhela_travel_date' => '2026-08-03',
		'bhela_guests'      => 2,
		'bhela_paid_amount' => 0,
		'bhela_status'      => 'pending',
	), $post );
	bhela_bm_save_booking( $id, get_post( $id ) );
	$_POST = array();
}

/** Render the real template, not a copy of it. */
function bk_invoice_html( $id ) {
	$invoice = bhela_bm_invoice_data( $id );
	ob_start();
	include WP_PLUGIN_DIR . '/bhela-booking/templates/invoice.php';
	return (string) ob_get_clean();
}

/** A priced, settled or part-paid booking, without going through the save handler. */
function bk_money( $id, $total, $paid ) {
	update_post_meta( $id, '_bhela_total', $total );
	update_post_meta( $id, '_bhela_base_price', $total );
	update_post_meta( $id, '_bhela_paid_amount', $paid );
	update_post_meta( $id, '_bhela_advance', (int) round( $total / 2 ) );
	update_post_meta( $id, '_bhela_invoice_no', 'ZZ-INV-' . $id );
}

function bk_meta( $id, $key ) {
	return get_post_meta( $id, '_bhela_' . $key, true );
}

/**
 * A booking's status, read the way the plugin reads it.
 *
 * When the capacity guard blocks a save it leaves `_bhela_status` alone — which
 * on a booking that has never been saved successfully means the meta is absent,
 * not the string 'pending'. Every reader applies the same default.
 */
function bk_status( $id ) {
	return bk_meta( $id, 'status' ) ?: 'pending';
}

echo "=== 1. the balance helper ===\n";
$b = bhela_bm_balance( 45000, 45000 );
ok( true === $b['settled'], 'paid exactly = settled' );
ok( 0 === $b['due'], 'nothing due' );
$b = bhela_bm_balance( 45000, 50000 );
ok( true === $b['settled'], 'an overpayment is still settled' );
ok( 0 === $b['due'], 'and still clamped at zero', (string) $b['due'] );
$b = bhela_bm_balance( 45000, 44999 );
ok( false === $b['settled'], 'one taka short is not settled' );
ok( 1 === $b['due'], 'that taka is still owed' );
// The reason the predicate is not `$due === 0`: an unpriced full-boat quote.
ok( false === bhela_bm_balance( 0, 0 )['settled'], 'a ৳0 booking is NOT settled' );
ok( false === bhela_bm_balance( 0, 5000 )['settled'], 'money against a ৳0 record is not paid-in-full' );
ok( 0 === bhela_bm_balance( '45000', '45000' )['due'], 'string meta is cast, not concatenated' );

echo "\n=== 2. the invoice prints the stamp only when it should ===\n";
$paid_id = bk_new( 'settled' );
update_post_meta( $paid_id, '_bhela_travel_date', $monday );
bk_money( $paid_id, 45000, 45000 );
// Matched on the markup, never on the bare class name: the <style> block in the
// same document names every one of these selectors, so a plain strpos() for
// "paid-stamp" is true on every invoice ever rendered.
$stamp   = 'class="paid-stamp"';
$settled = 'class="row grand due is-settled"';

$html = bk_invoice_html( $paid_id );
ok( false !== strpos( $html, $stamp ), 'settled invoice carries the stamp' );
ok( false !== strpos( $html, 'সম্পূর্ণ পরিশোধিত' ), 'in Bangla as well as English' );
ok( false !== strpos( $html, $settled ), 'Balance Due drops the alarm colour' );

bk_money( $paid_id, 45000, 20000 );
$html = bk_invoice_html( $paid_id );
ok( false === strpos( $html, $stamp ), 'part-paid invoice has no stamp' );
ok( false !== strpos( $html, '25,000' ), 'and states what is left', '৳25,000' );
ok( false === strpos( $html, $settled ), 'Balance Due stays orange' );

$quote_id = bk_new( 'unpriced quote' );
update_post_meta( $quote_id, '_bhela_travel_date', $monday );
bk_money( $quote_id, 0, 0 );
ok( false === strpos( bk_invoice_html( $quote_id ), $stamp ), 'an unpriced ৳0 quote is never stamped PAID' );

// Print safety, asserted on the stylesheet: browsers default to
// print-color-adjust:economy, which drops every background. A filled badge would
// print as nothing at all, so the stamp must be an outline.
$tpl = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/templates/invoice.php' );
ok( (bool) preg_match( '/\.paid-stamp\s*\{[^}]*border:\s*3px solid/', $tpl ), 'the stamp is drawn with a border' );
ok( ! preg_match( '/\.paid-stamp\s*\{[^}]*background/', $tpl ), 'and has no fill to lose in print' );
ok( ! preg_match( '/\.paid-stamp__\w+\s*\{[^}]*background/', $tpl ), 'nor do its parts' );

echo "\n=== 3. Full Boat, created in wp-admin ===\n";
$fb = bk_new( 'fullboat' );
// Deliberately hostile: no manual-price tick, a single two-adult cabin row, AND a
// Recalculate tick left over. Every one of those would have wrecked the booking.
bk_save( $fb, array(
	'bhela_full_boat'     => '1',
	'bhela_total'         => 300000,
	'bhela_cabin_adults'  => array( 2 ),
	'bhela_cabin_c48'     => array( 0 ),
	'bhela_cabin_c04'     => array( 0 ),
	'bhela_combo_recalc'  => '1',
) );
ok( '1' === bk_meta( $fb, 'full_boat' ), 'the flag is stored' );
ok( bhela_bm_max_cabins() === (int) bk_meta( $fb, 'cabin_count' ), 'it takes every cabin, not the one row shown', bk_meta( $fb, 'cabin_count' ) );
ok( bhela_bm_full_boat_label() === bk_meta( $fb, 'cabin_type' ), 'labelled as a whole boat, not as the combination' );
ok( 300000 === (int) bk_meta( $fb, 'total' ), 'the agreed price survived the Recalculate tick', bk_meta( $fb, 'total' ) );
ok( '1' === bk_meta( $fb, 'manual_price' ), 'manual pricing is forced, though it was never posted' );
ok( 0 === (int) bk_meta( $fb, 'per_person' ), 'a lump sum has no per-person rate' );
ok( 2 === (int) bk_meta( $fb, 'guests' ), 'the typed head count survived', bk_meta( $fb, 'guests' ) );
ok( bhela_bm_max_cabins() === (int) bhela_bm_booking_cabin_count( $fb ), 'and that is what the capacity guard will read' );
ok( '' === bk_meta( $fb, 'cabins_json' ), 'the combination branch never ran' );

bk_save( $fb, array( 'bhela_full_boat' => '1', 'bhela_total' => 300000, 'bhela_guests' => 999 ) );
ok( bhela_bm_max_guests() === (int) bk_meta( $fb, 'guests' ), 'a crafted head count is clamped to capacity', bk_meta( $fb, 'guests' ) );

// One string, not two: the label the admin stored is the one the form produces.
$fe = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/frontend.php' );
ok( ! preg_match( '/Full Boat — কাস্টম কোট/u', $fe ), 'the form no longer holds its own copy of the label' );

// The combination table has to be genuinely disabled, not merely dimmed. Dimming
// left its number inputs live, so max="6" still ran through HTML5 constraint
// validation and refused to submit the page — a full-boat booking could not be
// saved at all once someone typed a head count into the Adults cell.
$ad_src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/admin.php' );
preg_match( '/function syncFullBoat\(\).*?\n\t\t\}/s', $ad_src, $sync );
$sync_body = $sync[0] ?? '';
ok( '' !== $sync_body, 'the Full Boat sync function is where it was' );
ok( false !== strpos( $sync_body, "querySelectorAll('input, button')" ), 'it reaches every control in the table' );
ok( (bool) preg_match( '/\.disabled = on/', $sync_body ), 'and disables them, so constraint validation skips them' );
ok( false !== strpos( $sync_body, 'offNote.hidden' ), 'and says where the head count goes instead' );

// Render it, so a PHP error in the new callout cannot ship silently.
require_once WP_PLUGIN_DIR . '/bhela-booking/includes/admin.php';
$mb_id = bk_new( 'metabox render' );
update_post_meta( $mb_id, '_bhela_full_boat', '1' );
ob_start();
bhela_bm_details_metabox( get_post( $mb_id ) );
$mb = (string) ob_get_clean();
ok( false !== strpos( $mb, 'name="bhela_full_boat"' ), 'the metabox renders the Full Boat control' );
ok( false !== strpos( $mb, 'checked=\'checked\'' ) || false !== strpos( $mb, 'checked="checked"' ), 'ticked when the flag is set' );
ok( false !== strpos( $mb, 'id="bhela-combo-off-note"' ), 'and carries the locked-combination note' );
ok( false === strpos( $mb, '<style' ), 'with no inline style block' );

echo "\n=== 3b. an unpriced Full Boat is flagged, a priced one is not ===\n";
$fb0 = bk_new( 'fullboat unpriced' );
bk_save( $fb0, array( 'bhela_full_boat' => '1', 'bhela_total' => 0 ) );
ok( (bool) get_transient( 'bhela_bm_fb_warn_' . $fb0 ), 'saving one with no price warns' );
delete_transient( 'bhela_bm_fb_warn_' . $fb0 );
bk_save( $fb0, array( 'bhela_full_boat' => '1', 'bhela_total' => 250000 ) );
ok( ! get_transient( 'bhela_bm_fb_warn_' . $fb0 ), 'pricing it silences the warning' );

echo "\n=== 3c. unticking restores per-cabin pricing ===\n";
bk_save( $fb, array(
	'bhela_cabin_adults' => array( 4 ),
	'bhela_cabin_c48'    => array( 0 ),
	'bhela_cabin_c04'    => array( 0 ),
	'bhela_combo_recalc' => '1',
) );
ok( '' === bk_meta( $fb, 'full_boat' ), 'the flag clears with no separate un-tick code' );
ok( 1 === (int) bk_meta( $fb, 'cabin_count' ), 'the footprint returns to the real row count' );
$expect = bhela_bm_calc_multi( array( array( 'adults' => 4, 'c48' => 0, 'c04' => 0 ) ), $monday );
ok( ! is_wp_error( $expect ) && (int) $expect['total'] === (int) bk_meta( $fb, 'total' ),
	'and the occupancy engine prices it again', bk_meta( $fb, 'total' ) );
ok( bhela_bm_full_boat_label() !== bk_meta( $fb, 'cabin_type' ), 'the boat label is gone' );

echo "\n=== 4. a Full Boat still needs all six cabins free ===\n";
$neighbour = bk_new( 'neighbour' );
update_post_meta( $neighbour, '_bhela_travel_date', $monday );
update_post_meta( $neighbour, '_bhela_cabin_count', 1 );
update_post_meta( $neighbour, '_bhela_status', 'confirmed' );

$fb2 = bk_new( 'fullboat contested' );
bk_save( $fb2, array( 'bhela_full_boat' => '1', 'bhela_total' => 300000, 'bhela_status' => 'confirmed' ) );
ok( 'pending' === bk_status( $fb2 ), 'confirming onto a date with a cabin sold is blocked', bk_status( $fb2 ) );
$cap = (string) get_transient( 'bhela_bm_cap_err_' . $fb2 );
ok( '' !== $cap && false !== strpos( $cap, '6' ), 'and says six cabins are needed', $cap );
delete_transient( 'bhela_bm_cap_err_' . $fb2 );

bk_save( $fb2, array( 'bhela_full_boat' => '1', 'bhela_total' => 300000, 'bhela_status' => 'confirmed', 'bhela_overbook' => '1' ) );
ok( 'confirmed' === bk_status( $fb2 ), 'Overbook forces it through', bk_status( $fb2 ) );

// The growth arm: already confirmed, same date, same status — only the footprint
// changes, which the original two-armed guard would have waved past.
$grow = bk_new( 'grower' );
bk_save( $grow, array( 'bhela_total' => 45000, 'bhela_status' => 'confirmed', 'bhela_overbook' => '1' ) );
delete_transient( 'bhela_bm_cap_err_' . $grow );
bk_save( $grow, array( 'bhela_full_boat' => '1', 'bhela_total' => 300000, 'bhela_status' => 'confirmed' ) );
ok( (bool) get_transient( 'bhela_bm_cap_err_' . $grow ), 'growing a confirmed booking to six cabins is checked' );
delete_transient( 'bhela_bm_cap_err_' . $grow );

// A date of its own, because $fb2 and $grow are now confirmed onto $monday and
// between them hold more than the boat has. 10 August 2026 is also a Monday.
$fb3 = bk_new( 'fullboat clear date' );
bk_save( $fb3, array(
	'bhela_travel_date' => '2026-08-10',
	'bhela_full_boat'   => '1',
	'bhela_total'       => 300000,
	'bhela_status'      => 'confirmed',
) );
ok( 'confirmed' === bk_status( $fb3 ), 'six needed against six free passes cleanly', bk_status( $fb3 ) );

echo "\n=== 5. the day type comes from the date, not from stale meta ===\n";
// Sanity first, so a settings change cannot make the rest pass for a wrong reason.
ok( '1' === date( 'w', strtotime( $monday ) ), $monday . ' really is a Monday' );
$wknd = bhela_bm_sanitize_weekend_days( bhela_bm_get_settings()['weekend_days'] );
ok( ! in_array( 1, $wknd, true ), 'and Monday is not configured as a weekend', implode( ',', $wknd ) );

// The production fixture, reproduced exactly: a date that was moved, a poisoned
// cache, and both reprice guards permanently shut.
$stale = bk_new( 'stale daytype' );
update_post_meta( $stale, '_bhela_travel_date', $monday );
update_post_meta( $stale, '_bhela_day_type', 'weekend' );
update_post_meta( $stale, '_bhela_manual_price', '1' );
update_post_meta( $stale, '_bhela_cabin_key', '' );
bk_money( $stale, 45000, 0 );
ok( 'weekday' === bhela_bm_booking_day_type( $stale ), 'the helper ignores the poisoned cache' );
ok( 'weekday' === bhela_bm_invoice_data( $stale )['day_type'], 'so does the invoice data' );
$html = bk_invoice_html( $stale );
ok( false !== strpos( $html, 'Weekday (২০% ছাড়)' ), 'the rendered invoice says Weekday' );
ok( false === strpos( $html, 'Weekend' ), 'and never says Weekend' );
ok( false !== strpos( bhela_bm_booking_summary_text( $stale ), '(weekday)' ), 'the admin email agrees' );

// The stored copy is repaired on save even though NEITHER reprice branch ran.
$before_total = (int) bk_meta( $stale, 'total' );
bk_save( $stale, array( 'bhela_manual_price' => '1', 'bhela_total' => $before_total ) );
ok( 'weekday' === bk_meta( $stale, 'day_type' ), 'a plain save repairs the cache' );
ok( $before_total === (int) bk_meta( $stale, 'total' ), 'the label moved, the price did not', bk_meta( $stale, 'total' ) );

update_post_meta( $stale, '_bhela_travel_date', $friday );
ok( 'weekend' === bhela_bm_booking_day_type( $stale ), 'a Friday still reads weekend' );

// Holiday still wins, and is still checked before the weekend.
$trips_backup = get_option( 'bhela_bm_trips' );
update_option( 'bhela_bm_trips', array( array( 'date' => $monday, 'label' => 'ZZ holiday', 'holiday' => 1 ) ) );
update_post_meta( $stale, '_bhela_travel_date', $monday );
ok( 'holiday' === bhela_bm_booking_day_type( $stale ), 'a holiday outranks the weekday rule' );
if ( false === $trips_backup ) {
	delete_option( 'bhela_bm_trips' );
} else {
	update_option( 'bhela_bm_trips', $trips_backup );
}

// A date in the wrong shape falls back to the stored label rather than being
// quietly accepted — createFromFormat() would have taken 2026-8-3 happily.
update_post_meta( $stale, '_bhela_travel_date', '2026-8-3' );
update_post_meta( $stale, '_bhela_day_type', 'holiday' );
ok( 'holiday' === bhela_bm_booking_day_type( $stale ), 'a malformed date falls back to what was stored' );

// And a date nothing can parse falls back too, instead of inheriting the
// pricing-safe 'weekend' the raw engine answers with when strtotime() fails.
update_post_meta( $stale, '_bhela_travel_date', 'not-a-date' );
ok( 'holiday' === bhela_bm_booking_day_type( $stale ), 'an unparseable date falls back as well' );
ok( 'weekend' === bhela_bm_day_type( 'not-a-date' ), 'which is the point — the raw engine says weekend', bhela_bm_day_type( 'not-a-date' ) );

update_post_meta( $stale, '_bhela_travel_date', '' );
update_post_meta( $stale, '_bhela_day_type', '' );
ok( '' === bhela_bm_booking_day_type( $stale ), 'nothing known reads as nothing, not as a guess' );
ok( false === strpos( bk_invoice_html( $stale ), '(Weekend)' ), 'and the invoice prints no parenthetical' );

// Nothing may read the raw meta as a primary source any more.
$em = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/emails.php' );
$iv = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/invoice.php' );
ok( ! preg_match( "/\\\$m\(\s*'_bhela_day_type'/", $em ), 'the email reads it through the helper' );
ok( ! preg_match( "/'day_type'\s*=>\s*\\\$m\(/", $iv ), 'so does the invoice' );

echo "\n=== 6. weekend days are whitelisted to 0–6 ===\n";
// Sunday is 0, so array_filter() with no callback would have thrown it away every
// time it was ticked. That is the trap this asserts against.
ok( array( 0 ) === bhela_bm_sanitize_weekend_days( array( '0' ) ), 'Sunday survives' );
ok( array( 5, 6 ) === bhela_bm_sanitize_weekend_days( array( '5', '6', '7', '-1', 'x' ) ), 'out of range is dropped' );
ok( array( 5, 6 ) === bhela_bm_sanitize_weekend_days( array( '5', '5', '6' ) ), 'duplicates collapse' );
ok( array() === bhela_bm_sanitize_weekend_days( '' ), 'a non-array is handled without a notice' );
ok( (bool) array_filter( array( 0 ), 'strlen' ), 'a Sunday-only list does not trip the "none ticked" warning' );
$ad = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/admin.php' );
ok( (bool) preg_match( '/bhela_pricing_days_present.*bhela_bm_sanitize_weekend_days/s', $ad ),
	'and the marker still gates the write' );

foreach ( $made as $id ) {
	delete_transient( 'bhela_bm_fb_warn_' . $id );
	delete_transient( 'bhela_bm_cap_err_' . $id );
	delete_transient( 'bhela_combo_err_' . $id );
	wp_delete_post( $id, true );
}
bhela_test_done();
