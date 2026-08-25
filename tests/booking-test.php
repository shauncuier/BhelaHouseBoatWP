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
bhela_test_modules( 'ui', 'roles', 'log', 'trips', 'invoice', 'emails', 'admin', 'b2b-report' );

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

echo "\n=== 3d. a Full Boat from the FRONT END arrives priced ===\n";
// It used to arrive at ৳0 and wait for a hand quote, so the guest saw no number at
// all and the booking sat unpriced. It now carries the standard whole-boat rate —
// every cabin at maximum occupancy — which an admin adjusts after negotiating.
$fb_plan = bhela_bm_full_boat_plan();
ok( bhela_bm_max_cabins() === count( $fb_plan ), 'the plan is every cabin', (string) count( $fb_plan ) );
$fb_occ   = bhela_bm_rates_by_occupancy();
$fb_size  = $fb_occ ? max( array_keys( $fb_occ ) ) : 6;
$fb_heads = array_sum( wp_list_pluck( $fb_plan, 'adults' ) );
ok( bhela_bm_max_guests() === $fb_heads, 'filled to capacity — ' . count( $fb_plan ) . ' × ' . $fb_size . ' = ' . bhela_bm_max_guests(), (string) $fb_heads );
ok( 0 === array_sum( wp_list_pluck( $fb_plan, 'c48' ) ) + array_sum( wp_list_pluck( $fb_plan, 'c04' ) ),
	'no children assumed — a child fee nobody asked for would inflate every quote' );

// Priced through the one engine, so weekday/holiday and the advance % apply as
// they do anywhere else. These are the figures the booking form must also show.
$fb_wknd = bhela_bm_full_boat_price( '2026-08-21' );   // Friday
$fb_week = bhela_bm_full_boat_price( '2026-08-18' );   // Tuesday
ok( ! is_wp_error( $fb_wknd ) && ! is_wp_error( $fb_week ), 'both day types price without error' );
$fb_row    = bhela_bm_rate_for_occupancy( $fb_size );
$fb_rate_w = (int) $fb_row['regular'];
$fb_rate_d = (int) $fb_row['weekday'];
ok( bhela_bm_max_guests() * (int) $fb_rate_w === (int) $fb_wknd['total'],
	'weekend total is ' . bhela_bm_max_guests() . ' × ' . $fb_rate_w, bhela_bm_money( $fb_wknd['total'] ) );
ok( bhela_bm_max_guests() * (int) $fb_rate_d === (int) $fb_week['total'],
	'weekday total is ' . bhela_bm_max_guests() . ' × ' . $fb_rate_d, bhela_bm_money( $fb_week['total'] ) );
ok( (int) $fb_week['total'] < (int) $fb_wknd['total'], 'the weekday discount still applies to a whole boat' );

// The JS must reach the same number, and it computes it the same way rather than
// from a literal — MAX_CABINS × MAX_CAP × occRate(MAX_CAP, dt). A divergence here
// shows the guest one price and stores another.
$fb_js = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/assets/booking.js' );
ok( false !== strpos( $fb_js, 'MAX_CABINS * MAX_CAP * occRate(MAX_CAP, dt)' ),
	'booking.js prices the boat from the same plan, not a hardcoded figure' );
ok( false !== strpos( $fb_js, 'function renderFullBoat' ), 'and paints it as one line, not six identical cabins' );

// Submitted for real: the whole-boat branch of the submission handler.
$fb_fe = bhela_bm_process_submission( array(
	'name'       => 'ZZ Full Boat guest',
	'phone'      => '01700000009',
	'date'       => '2026-08-21',
	'full_boat'  => '1',
	'cabins'     => wp_json_encode( array() ),
) );
if ( is_wp_error( $fb_fe ) ) {
	ok( false, 'a front-end Full Boat submits', $fb_fe->get_error_message() );
} else {
	$fb_id  = (int) $fb_fe['booking_id'];
	$made[] = $fb_id;
	ok( (int) $fb_wknd['total'] === (int) get_post_meta( $fb_id, '_bhela_total', true ),
		'it stores the standard rate, not ৳0', get_post_meta( $fb_id, '_bhela_total', true ) );
	ok( (int) get_post_meta( $fb_id, '_bhela_advance', true ) > 0, 'and therefore an advance to ask for',
		get_post_meta( $fb_id, '_bhela_advance', true ) );
	ok( bhela_bm_max_guests() === (int) get_post_meta( $fb_id, '_bhela_guests', true ),
		'guests is the boat capacity', get_post_meta( $fb_id, '_bhela_guests', true ) );
	ok( '' === (string) get_post_meta( $fb_id, '_bhela_lines', true ) || '[]' === (string) get_post_meta( $fb_id, '_bhela_lines', true ),
		'no per-cabin breakdown — six identical rows is not what was sold',
		(string) get_post_meta( $fb_id, '_bhela_lines', true ) );
	// A priced boat is not a paid one; only a payment settles it.
	$fb_bal = bhela_bm_balance( get_post_meta( $fb_id, '_bhela_total', true ), 0 );
	ok( ! $fb_bal['settled'], 'priced but unpaid — no PAID stamp' );
}

echo "\n=== 3e. the confirmation message ===\n";
// Staff were typing this out by hand per booking. Everything in it already existed
// in the record, so hand typing only added the chance of quoting a wrong Due.
$cf = bk_new( 'confirm guest' );
bk_save( $cf, array(
	'bhela_travel_date' => $friday,
	'bhela_phone'       => '01712345678',
	'bhela_guests'      => 4,
	'bhela_address'     => 'Sunamganj',
	'bhela_room_no'     => '02',
	'bhela_booked_by'   => 'Nishat Kaiser',
	'bhela_issued_by'   => 'Nishat Kaiser',
	'bhela_pay_method'  => 'bkash',
	'bhela_paid_amount' => 4000,
) );
bk_money( $cf, 33000, 4000 );
update_post_meta( $cf, '_bhela_pay_method', 'bkash' );
$cf_text = bhela_bm_confirm_text( $cf );

// The failure that matters: a token the renderer does not know stays on screen as
// literal `{curly}` text and goes out to a guest that way.
preg_match_all( '/\{[a-z_]+\}/', $cf_text, $cf_left );
ok( ! $cf_left[0], 'every placeholder resolved', implode( ' ', $cf_left[0] ) );

ok( false !== strpos( $cf_text, (string) bk_meta( $cf, 'invoice_no' ) ), 'carries the system booking number' );
ok( false !== strpos( $cf_text, 'Sunamganj' ), 'address' );
ok( false !== strpos( $cf_text, 'Nishat Kaiser' ), 'staff names' );
ok( false !== strpos( $cf_text, 'bKash' ), 'payment method as a label, not a slug' );
ok( false !== strpos( $cf_text, bhela_bm_invoice_url( $cf ) ), 'the secure invoice link' );

// The message and the invoice are read side by side. A different Due on each is
// the one discrepancy a guest is guaranteed to notice, so both come from
// bhela_bm_balance() rather than from two copies of the subtraction.
$cf_bal = bhela_bm_balance( 33000, 4000 );
ok( false !== strpos( $cf_text, bhela_bm_money( $cf_bal['due'] ) ), 'Due matches bhela_bm_balance()', bhela_bm_money( $cf_bal['due'] ) );

// Dates are derived on every read. A stored copy is what went stale in production
// and printed "Weekend" against a Monday — see CLAUDE.md §13.8.
$cf_stay = bhela_bm_booking_stay( $cf );
ok( $friday === $cf_stay['in'], 'check-in is the travel date', $cf_stay['in'] );
ok( gmdate( 'Y-m-d', strtotime( $friday . ' +1 day' ) ) === $cf_stay['out'], 'check-out is the day after', $cf_stay['out'] );
bk_save( $cf, array( 'bhela_travel_date' => $monday, 'bhela_paid_amount' => 4000 ) );
$cf_moved = bhela_bm_booking_stay( $cf );
ok( $monday === $cf_moved['in'] && gmdate( 'Y-m-d', strtotime( $monday . ' +1 day' ) ) === $cf_moved['out'],
	'moving the travel date moves both ends', $cf_moved['in'] . ' → ' . $cf_moved['out'] );

// A blank optional field must drop its line, not print a dangling label.
bk_save( $cf, array( 'bhela_travel_date' => $monday, 'bhela_address' => '', 'bhela_room_no' => '', 'bhela_paid_amount' => 4000 ) );
$cf_bare = bhela_bm_confirm_text( $cf );
ok( ! preg_match( '/^\s*📍 Address:\s*$/mu', $cf_bare ), 'an empty address drops its line' );
ok( false !== strpos( $cf_bare, 'Note:' ), 'but a label that heads a list survives' );

// Boarding falls back to the setting, and a booking overrides it.
$cf_s = bhela_bm_get_settings();
ok( false !== strpos( $cf_bare, $cf_s['boarding_ghat'] ), 'boarding falls back to the setting', $cf_s['boarding_ghat'] );
update_post_meta( $cf, '_bhela_boarding', 'ZZ Tekerghat Ghat' );
ok( false !== strpos( bhela_bm_confirm_text( $cf ), 'ZZ Tekerghat Ghat' ), 'and a booking can override it' );

// The booking form renders `address`, and booking.js copies an explicit list of
// field names into the request rather than the whole form — so adding an input is
// not enough to submit it. The field shipped once with the value silently dropped
// between the guest typing it and the server: the input was there, the booking
// stored ''. Both ends are pinned here because neither alone would have caught it.
$cf_form = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/frontend.php' );
$cf_js   = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/assets/booking.js' );
ok( false !== strpos( $cf_form, 'name="address"' ), 'the form renders an address input' );
ok( (bool) preg_match( "/\[[^\]]*'address'[^\]]*\]\.forEach/", $cf_js ), 'and booking.js actually submits it' );

// Proof end to end, through the real submission handler.
$cf_sub = bhela_bm_process_submission( array(
	'name'    => 'ZZ Address guest',
	'phone'   => '01700000011',
	'address' => 'Sunamganj',
	'date'    => $friday,
	'cabins'  => wp_json_encode( array( array( 'adults' => 2, 'c48' => 0, 'c04' => 0 ) ) ),
) );
if ( is_wp_error( $cf_sub ) ) {
	ok( false, 'a front-end booking stores the address', $cf_sub->get_error_message() );
} else {
	$made[] = (int) $cf_sub['booking_id'];
	ok( 'Sunamganj' === get_post_meta( (int) $cf_sub['booking_id'], '_bhela_address', true ),
		'a front-end booking stores the address', get_post_meta( (int) $cf_sub['booking_id'], '_bhela_address', true ) );
}

// The service notes appear on the invoice as well as in the message, from the one
// setting — an invoice promising 24-hour AC while the confirmation says 16-18 is
// exactly the contradiction a guest brings up at check-in.
$cf_lines = bhela_bm_confirm_note_lines();
ok( $cf_lines, 'there are service notes to print', implode( ' | ', $cf_lines ) );
$cf_html = bk_invoice_html( $cf );
foreach ( $cf_lines as $cf_line ) {
	ok( false !== strpos( $cf_html, esc_html( $cf_line ) ), 'the invoice prints: ' . $cf_line );
	ok( false !== strpos( $cf_text, $cf_line ), 'and so does the confirmation message: ' . $cf_line );
}
// Bordered, not filled: browsers drop background colours when printing, so a
// tinted panel prints as nothing at all. Same rule as the PAID stamp above.
ok( (bool) preg_match( '/\.svc-note\s*\{[^}]*border:/', $cf_html ), 'the service-note block is drawn with a border' );
ok( ! preg_match( '/\.svc-note\s*\{[^}]*background/', $cf_html ), 'and has no fill to lose in print' );

// The strings these settings replaced were hardcoded into the guest-facing files,
// which is why changing the boarding ghat used to be a code edit.
$cf_inv = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/templates/invoice.php' );
$cf_eml = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/emails.php' );
ok( false === strpos( $cf_inv, 'Anwarpur Ghat' ), 'the invoice no longer hardcodes the ghat' );
ok( false === strpos( $cf_eml, 'Anwarpur Ghat' ), 'nor does the customer email' );
ok( false === strpos( $cf_inv, '২ দিন ১ রাত' ), 'nor the package label' );
ok( false === strpos( $cf_eml, '২ দিন ১ রাত' ), 'nor does the email' );

echo "\n=== 3f. B2B commission never reaches the guest ===\n";
// The commission is between BHELA and the partner. It is deducted in the accounts
// and must not appear on anything the guest reads. This is a rule about what must
// NOT be there, which is exactly the kind that rots silently — nothing breaks when
// it leaks, it just quietly shows a guest what BHELA pays an agency.
bhela_bm_save_agencies( array(
	array( 'id' => '', 'name' => 'ZZ Travel Compass', 'phone' => '01700000022', 'email' => 'zz@example.invalid', 'rate' => 10 ),
) );
$ag_id = '';
foreach ( bhela_bm_agencies() as $aid => $arow ) {
	if ( 'ZZ Travel Compass' === $arow['name'] ) {
		$ag_id = $aid;
	}
}
ok( '' !== $ag_id, 'the agency saved and has an id', $ag_id );
ok( 3000 === bhela_bm_agency_commission( $ag_id, 30000 ), '10% of 30,000 suggests 3,000',
	(string) bhela_bm_agency_commission( $ag_id, 30000 ) );

$b2b = bk_new( 'b2b guest' );
bk_save( $b2b, array(
	'bhela_travel_date' => $friday,
	'bhela_agency'      => $ag_id,
	'bhela_commission'  => 3500,          // negotiated, not the suggested 3,000
	'bhela_agency_ref'  => 'TC-99',
	'bhela_paid_amount' => 10000,
) );
bk_money( $b2b, 30000, 10000 );
update_post_meta( $b2b, '_bhela_agency', $ag_id );
update_post_meta( $b2b, '_bhela_commission', 3500 );

ok( 3500 === (int) bk_meta( $b2b, 'commission' ), 'the agreed amount is stored, not the suggestion', bk_meta( $b2b, 'commission' ) );
ok( 'ZZ Travel Compass' === bhela_bm_booking_agency_name( $b2b ), 'and the agency resolves by id' );

// The three guest-facing surfaces.
$b2b_inv  = bk_invoice_html( $b2b );
$b2b_conf = bhela_bm_confirm_text( $b2b );
$b2b_mail = bhela_bm_email_customer_html( $b2b, 'confirmed' );
foreach ( array( 'invoice' => $b2b_inv, 'confirmation message' => $b2b_conf, 'customer email' => $b2b_mail ) as $where => $doc ) {
	ok( false === strpos( $doc, '3,500' ) && false === strpos( $doc, '3500' ),
		"the commission is absent from the $where" );
	ok( false === strpos( $doc, 'ZZ Travel Compass' ), "and so is the agency name — $where" );
	ok( false === strpos( $doc, 'TC-99' ), "and the agency reference — $where" );
}
// The guest still sees their own full price. The commission comes out of BHELA's
// side, not the guest's — an invoice quietly reduced by 3,500 would be wrong.
ok( false !== strpos( $b2b_inv, '30,000' ), 'the guest is still billed the full 30,000' );

// The placeholders exist for an agency-facing message, but are not in the shipped
// guest template — adding them is a settings edit, not a code change.
ok( false !== strpos( bhela_bm_render_sms( '{agency}|{commission}|{agency_ref}', $b2b ), 'ZZ Travel Compass' ),
	'an agency-facing template CAN carry them' );
ok( false === strpos( bhela_bm_confirm_default_template(), '{commission}' ),
	'but the shipped guest template does not' );

// The invoice prints the guest's address. It was added to the booking, the admin,
// the public form and the confirmation message in v2.30.0 and missed here, so the
// invoice showed no address at all while every other surface had one.
$addr_id = bk_new( 'address on invoice' );
bk_save( $addr_id, array( 'bhela_travel_date' => $friday, 'bhela_address' => 'Dhaka' ) );
bk_money( $addr_id, 30000, 0 );
ok( false !== strpos( bk_invoice_html( $addr_id ), 'Dhaka' ), 'the invoice prints the guest address' );
bk_save( $addr_id, array( 'bhela_travel_date' => $friday, 'bhela_address' => '' ) );
$addr_html = bk_invoice_html( $addr_id );
ok( false === strpos( $addr_html, 'Dhaka' ), 'and drops it when there is none' );
ok( false !== strpos( $addr_html, 'Bill To' ), 'without breaking the Bill To block' );

echo "\n=== 3j. the vessel registration prints, and vanishes cleanly ===\n";
// The boat's government registration (M.B BHELA (M-01-5520)) goes on the invoice
// header, the footer identity badge and the booking page. Printed BARE on the
// invoice — no invented label — so there is nothing to assert about wording, only
// about presence and absence.
//
// Writing to bhela_bm_settings is safe because bhela_test_owner_options() now
// snapshots it. It did NOT until this test needed it, which is the wrong order to
// find that out: a harness that cleared it would have wiped every phone number,
// payment detail and gateway key the owner has ever typed.
$reg_before = get_option( 'bhela_bm_settings' );
$reg_id     = bk_new( 'vessel reg' );
bk_save( $reg_id, array( 'bhela_travel_date' => $friday ) );
bk_money( $reg_id, 30000, 0 );

$reg_s = bhela_bm_get_settings();
$reg_s['vessel_reg'] = 'ZZ M.B TEST (M-99-0001)';
update_option( 'bhela_bm_settings', $reg_s );

$reg_html = bk_invoice_html( $reg_id );
ok( false !== strpos( $reg_html, 'ZZ M.B TEST (M-99-0001)' ), 'the invoice header prints the registration' );
ok( false !== strpos( bhela_bm_render_sms( '[{vessel_reg}]', $reg_id ), '[ZZ M.B TEST (M-99-0001)]' ),
	'and {vessel_reg} resolves in a message template' );

// The absence case is the one that rots unnoticed. A blank value must remove the
// line, not print an empty element — on the invoice that shows as a gap in the teal
// header, and in the footer as a bordered badge with nothing inside it.
$reg_s['vessel_reg'] = '';
update_option( 'bhela_bm_settings', $reg_s );
$blank_html = bk_invoice_html( $reg_id );
ok( false === strpos( $blank_html, 'M-99-0001' ), 'a blank setting drops it from the invoice' );
ok( false === strpos( $blank_html, '<p></p>' ), 'and leaves no empty paragraph behind' );
ok( false !== strpos( $blank_html, 'INVOICE' ), 'the header itself still renders' );
ok( '[]' === bhela_bm_render_sms( '[{vessel_reg}]', $reg_id ),
	'and the placeholder resolves to nothing, not to the literal token',
	bhela_bm_render_sms( '[{vessel_reg}]', $reg_id ) );

// Deliberately NOT in the shipped confirmation message. The placeholder exists so
// the owner can add it themselves. A blank confirm_template means "use the shipped
// default", so a later edit to that default would never reach a site that has saved
// its settings — which makes an unasked-for change here both wrong and unfixable.
ok( false === strpos( bhela_bm_confirm_default_template(), '{vessel_reg}' ),
	'the shipped confirmation template still does not carry it' );

// One source of truth. The theme bridge defaults to EMPTY rather than hardcoding the
// number the way it hardcodes the address: a stale registration misrepresents the
// boat, whereas a missing one is merely missing.
$reg_fn = (string) file_get_contents( get_template_directory() . '/functions.php' );
ok( (bool) preg_match( "/'vessel_reg' => '',/", $reg_fn ),
	'the theme fallback is empty, never a hardcoded second copy' );
ok( false !== strpos( $reg_fn, "'address', 'vessel_reg' )" ),
	'while still reading the real value from the plugin settings' );
$reg_foot  = (string) file_get_contents( get_template_directory() . '/footer.php' );
$reg_prints = substr_count( $reg_foot, "esc_html( bhela_contact( 'vessel_reg' ) )" );
ok( 1 === $reg_prints, 'and the footer prints it exactly once, in the identity badge', (string) $reg_prints );

if ( null === $reg_before ) {
	delete_option( 'bhela_bm_settings' );
} else {
	update_option( 'bhela_bm_settings', $reg_before );
}
ok( bhela_bm_get_settings()['vessel_reg'] === ( $reg_before['vessel_reg'] ?? bhela_bm_default_settings()['vessel_reg'] ),
	'the real setting is put back exactly as it was' );
echo "\n=== 3k. the payment QR codes stay scannable ===\n";
// The QR images are owner-pasted URLs, so the template cannot control what shape
// arrives. It used to size them with `object-fit: cover`, which CROPS anything that
// is not square — and a QR missing a corner has lost a finder pattern and simply
// stops scanning. Nothing errors; the guest just cannot pay.
$qr_tpl = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/templates/invoice.php' );
ok( false !== strpos( $qr_tpl, 'object-fit:contain' ), 'QR images letterbox rather than crop' );
ok( false === strpos( $qr_tpl, 'object-fit:cover' ), 'and nothing in the invoice crops an image any more' );
ok( (bool) preg_match( '/\.pay-qrs figure \{[^}]*break-inside:avoid/', $qr_tpl ),
	'a QR cannot be split across a page break' );
ok( (bool) preg_match( '/@media print \{(?:[^}]|\}(?!\s*\n\t\<))*print-color-adjust:exact/s', $qr_tpl )
	|| false !== strpos( $qr_tpl, 'print-color-adjust:exact' ),
	'and prints as true black, not economy-mode grey' );

// Both codes are configured, and the block only renders when at least one is.
$qr_s = bhela_bm_get_settings();
ok( '' !== (string) ( $qr_s['bangla_qr'] ?? '' ) && '' !== (string) ( $qr_s['nagad_qr'] ?? '' ),
	'both QR images are set in settings' );
$qr_id = bk_new( 'qr on invoice' );
bk_save( $qr_id, array( 'bhela_travel_date' => $friday ) );
bk_money( $qr_id, 30000, 0 );
$qr_html = bk_invoice_html( $qr_id );
ok( false !== strpos( $qr_html, 'pay-qrs' ), 'and the invoice renders the QR block' );
ok( 2 === substr_count( $qr_html, '<figure>' ), 'with both codes, not one', (string) substr_count( $qr_html, '<figure>' ) );
echo "\n=== 3g. the commission is counted exactly once ===\n";
$b2b_rows = bhela_bm_commission_rows( $friday, $friday );
ok( 3500 === (int) $b2b_rows['total'], 'the day totals 3,500', (string) $b2b_rows['total'] );
ok( isset( $b2b_rows['by_agency'][ $ag_id ] ), 'attributed to the agency' );
ok( 1 === (int) $b2b_rows['by_agency'][ $ag_id ]['bookings'], 'one booking' );

// A cancelled booking owes nobody a commission — no trip, no sale.
bk_save( $b2b, array( 'bhela_travel_date' => $friday, 'bhela_status' => 'cancelled', 'bhela_agency' => $ag_id, 'bhela_commission' => 3500 ) );
ok( 0 === (int) bhela_bm_commission_rows( $friday, $friday )['total'], 'a cancelled booking owes no commission',
	(string) bhela_bm_commission_rows( $friday, $friday )['total'] );
bk_save( $b2b, array( 'bhela_travel_date' => $friday, 'bhela_status' => 'confirmed', 'bhela_agency' => $ag_id, 'bhela_commission' => 3500 ) );

echo "\n=== 3h. referral links ===\n";
$ref_tok = bhela_bm_agencies()[ $ag_id ]['token'] ?? '';
ok( '' !== $ref_tok, 'the agency has a referral token', $ref_tok );
ok( $ag_id === bhela_bm_agency_by_token( $ref_tok ), 'the token resolves to its agency' );
ok( '' === bhela_bm_agency_by_token( 'nonsense' ), 'an unknown token resolves to nothing' );
ok( false !== strpos( bhela_bm_agency_ref_url( $ag_id ), 'ref=' . $ref_tok ), 'the link carries it',
	bhela_bm_agency_ref_url( $ag_id ) );

// Rotation. A partner whose link leaks needs a new one WITHOUT losing the bookings
// they already brought — which is why the token is stored and random rather than a
// wp_hash() over the frozen agency id, where it could never change.
bhela_bm_save_agencies( array(
	array( 'id' => $ag_id, 'name' => 'ZZ Travel Compass', 'rate' => 10, 'token' => $ref_tok, 'regen' => 1 ),
) );
$ref_new = bhela_bm_agencies()[ $ag_id ]['token'] ?? '';
ok( $ref_new !== $ref_tok, 'regenerating mints a different token' );
ok( '' === bhela_bm_agency_by_token( $ref_tok ), 'and the old link stops attributing' );
ok( $ag_id === bhela_bm_agency_by_token( $ref_new ), 'while the new one works' );

// An ordinary save must NOT rotate it, or every settings save would break every
// live link an agency is already advertising.
bhela_bm_save_agencies( array(
	array( 'id' => $ag_id, 'name' => 'ZZ Travel Compass', 'rate' => 10, 'token' => $ref_new ),
) );
ok( $ref_new === ( bhela_bm_agencies()[ $ag_id ]['token'] ?? '' ), 'a normal save keeps the link alive' );

// A retired partner's link stops attributing, which is half of what retiring means.
bhela_bm_save_agencies( array(
	array( 'id' => $ag_id, 'name' => 'ZZ Travel Compass', 'rate' => 10, 'token' => $ref_new, 'retired' => 1 ),
) );
ok( '' === bhela_bm_agency_by_token( $ref_new ), 'a retired agency stops attributing' );
ok( null !== bhela_bm_agency( $ag_id ), 'but still names the bookings it already brought' );
bhela_bm_save_agencies( array(
	array( 'id' => $ag_id, 'name' => 'ZZ Travel Compass', 'rate' => 10, 'token' => $ref_new ),
) );

echo "\n=== 3i. a referral suggests, and waits ===\n";
// The control that matters: appending ?ref= to a URL must not move money on its own.
$_COOKIE[ bhela_bm_ref_cookie() ] = $ref_new;
$ref_sub = bhela_bm_process_submission( array(
	'name'   => 'ZZ Referred guest',
	'phone'  => '01700000033',
	'date'   => $friday,
	'cabins' => wp_json_encode( array( array( 'adults' => 2, 'c48' => 0, 'c04' => 0 ) ) ),
) );
unset( $_COOKIE[ bhela_bm_ref_cookie() ] );

if ( is_wp_error( $ref_sub ) ) {
	ok( false, 'a referred booking submits', $ref_sub->get_error_message() );
} else {
	$ref_id = (int) $ref_sub['booking_id'];
	$made[] = $ref_id;
	$ref_total = (int) get_post_meta( $ref_id, '_bhela_total', true );
	ok( $ag_id === get_post_meta( $ref_id, '_bhela_agency', true ), 'the booking is attributed to the agency' );
	ok( bhela_bm_agency_commission( $ag_id, $ref_total ) === (int) get_post_meta( $ref_id, '_bhela_commission', true ),
		'with the commission suggested from the rate', get_post_meta( $ref_id, '_bhela_commission', true ) );
	ok( 'unconfirmed' === get_post_meta( $ref_id, '_bhela_referral', true ), 'and held as unconfirmed' );

	// Absent from the accounts until a person agrees. Because the statement and the
	// cost sheet both read bhela_bm_commission_rows(), one gate covers both.
	$ref_before = (int) bhela_bm_commission_rows( $friday, $friday )['total'];
	update_post_meta( $ref_id, '_bhela_referral', 'confirmed' );
	$ref_after = (int) bhela_bm_commission_rows( $friday, $friday )['total'];
	ok( $ref_after - $ref_before === (int) get_post_meta( $ref_id, '_bhela_commission', true ),
		'it counts only once confirmed', $ref_before . ' → ' . $ref_after );
	update_post_meta( $ref_id, '_bhela_referral', 'unconfirmed' );
	ok( $ref_before === (int) bhela_bm_commission_rows( $friday, $friday )['total'],
		'and stops counting again if unconfirmed' );

	// A booking that arrived by referral is still a guest booking: nothing leaks.
	ok( false === strpos( bk_invoice_html( $ref_id ), 'ZZ Travel Compass' ), 'the invoice still names no agency' );
	ok( false === strpos( bhela_bm_confirm_text( $ref_id ), 'ZZ Travel Compass' ), 'nor does the confirmation message' );
}

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

echo "\n=== 7. the B2B report shows what the accounts deliberately hide ===\n";
// bhela_bm_commission_rows() answers "what does the month owe", so it drops cancelled
// bookings and unconfirmed referrals on purpose. The B2B screen has to show exactly
// those — a referral waiting on a person is the main reason to open it. Two readings
// of the same data, so this pins them against each other rather than trusting either.
bhela_bm_save_agencies( array(
	array( 'id' => '', 'name' => 'ZZ B2B Alpha', 'phone' => '', 'email' => '', 'rate' => 10 ),
	array( 'id' => '', 'name' => 'ZZ B2B Beta',  'phone' => '', 'email' => '', 'rate' => 5 ),
) );
$b2b_ids = array();
foreach ( bhela_bm_agencies() as $aid => $arow ) {
	if ( 0 === strpos( $arow['name'], 'ZZ B2B ' ) ) {
		$b2b_ids[ $arow['name'] ] = $aid;
	}
}
$alpha = $b2b_ids['ZZ B2B Alpha'];
$beta  = $b2b_ids['ZZ B2B Beta'];

/** @return int booking id */
function zz_b2b( $agency, $total, $comm, $referral, $status = 'confirmed' ) {
	$id = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZ b2b guest' ) );
	update_post_meta( $id, '_bhela_travel_date', '2026-09-10' );
	update_post_meta( $id, '_bhela_status', $status );
	update_post_meta( $id, '_bhela_total', $total );
	update_post_meta( $id, '_bhela_guests', 4 );
	update_post_meta( $id, '_bhela_agency', $agency );
	update_post_meta( $id, '_bhela_commission', $comm );
	if ( '' !== $referral ) {
		update_post_meta( $id, '_bhela_referral', $referral );
	}
	return $id;
}

$b_hand    = zz_b2b( $alpha, 30000, 3000, '' );                        // staff entered it
$b_pending = zz_b2b( $alpha, 26000, 2600, 'unconfirmed' );             // came by link, unconfirmed
$b_conf    = zz_b2b( $beta,  40000, 2000, 'confirmed' );               // came by link, confirmed
$b_cancel  = zz_b2b( $beta,  50000, 2500, 'confirmed', 'cancelled' );  // no trip, no commission
$b_direct  = zz_b2b( '',     20000, 0,    '' );                        // not a B2B booking at all

$b2b = bhela_bm_b2b_rows( '2026-09-01', '2026-09-30' );
$bt  = $b2b['totals'];

ok( 4 === $bt['bookings'], 'the four agency bookings are listed, the direct one is not', (string) $bt['bookings'] );
$listed = array_column( $b2b['rows'], 'id' );
ok( ! in_array( $b_direct, $listed, true ), 'a booking with no agency and no commission never appears' );
ok( in_array( $b_pending, $listed, true ), 'AND the unconfirmed referral IS listed — hiding it is what the accounts do' );
ok( in_array( $b_cancel, $listed, true ), 'so is the cancelled one, which the statement also drops' );

ok( 5000 === $bt['commission'], 'owed = 3000 hand + 2000 confirmed; pending and cancelled excluded', (string) $bt['commission'] );
ok( 2600 === $bt['pending'] && 1 === $bt['pending_n'], 'the waiting figure is counted separately, not mixed in',
	$bt['pending'] . ' / ' . $bt['pending_n'] );

// The screen's "owed" and the statement's deduction are two implementations of one
// rule. If they ever disagree, one of the two is wrong and nobody would notice.
$rows_fn = bhela_bm_commission_rows( '2026-09-01', '2026-09-30' );
ok( (int) $rows_fn['total'] === $bt['commission'], 'and it agrees to the taka with what the statement deducts',
	$rows_fn['total'] . ' vs ' . $bt['commission'] );

echo "\n=== 7b. filtering by one agency ===\n";
$only = bhela_bm_b2b_rows( '2026-09-01', '2026-09-30', $alpha );
ok( 2 === $only['totals']['bookings'], 'Alpha has two bookings', (string) $only['totals']['bookings'] );
ok( 3000 === $only['totals']['commission'] && 2600 === $only['totals']['pending'], 'with its own owed and waiting figures',
	$only['totals']['commission'] . ' / ' . $only['totals']['pending'] );
$names = array_unique( array_column( $only['rows'], 'agency_id' ) );
ok( array( $alpha ) === array_values( $names ), 'and nothing from Beta leaks in' );

ok( 'unconfirmed' === $b2b['rows'][0]['referral'], 'the waiting row sorts to the top, where it gets acted on',
	$b2b['rows'][0]['referral'] );

$per = bhela_bm_b2b_by_agency( $b2b['rows'] );
ok( 3000 === $per[ $alpha ]['commission'] && 2000 === $per[ $beta ]['commission'], 'the per-agency subtotals split correctly',
	$per[ $alpha ]['commission'] . ' / ' . $per[ $beta ]['commission'] );

echo "\n=== 7c. confirming moves it, on both readings at once ===\n";
update_post_meta( $b_pending, '_bhela_referral', 'confirmed' );
$after   = bhela_bm_b2b_rows( '2026-09-01', '2026-09-30' );
$after_s = bhela_bm_commission_rows( '2026-09-01', '2026-09-30' );
ok( 7600 === $after['totals']['commission'], 'owed rises by exactly the 2600 that was waiting',
	(string) $after['totals']['commission'] );
ok( 0 === $after['totals']['pending'] && 0 === $after['totals']['pending_n'], 'and nothing is left waiting' );
ok( (int) $after_s['total'] === $after['totals']['commission'], 'the statement moved by the same amount, in the same step',
	$after_s['total'] . ' vs ' . $after['totals']['commission'] );

echo "\n=== 7d. the date default must not hide the report's own subject ===\n";
// This screen shipped defaulting to the current calendar month, filtered by TRAVEL
// date. A referral is taken now for a trip months away, so confirming one changed
// nothing anybody could see — three agency bookings, one of them visible, and no
// error to explain the other two. The fixtures above all travel in September, which
// is exactly the case that was invisible.
$r_all = bhela_bm_b2b_range( '', '' );
ok( $r_all['all'], 'blank dates mean every date, not "this month"' );
$seen_all = bhela_bm_b2b_rows( $r_all['from'], $r_all['to'] );
$seen_ids = array_column( $seen_all['rows'], 'id' );
ok( in_array( $b_conf, $seen_ids, true ), 'a confirmed referral travelling in a future month IS in the default view' );
ok( in_array( $b_pending, $seen_ids, true ), 'and so is one still waiting' );

// One end given is an open-ended range, not a licence to invent the other.
$r_open = bhela_bm_b2b_range( '2026-09-01', '' );
ok( ! $r_open['all'] && '2026-09-01' === $r_open['from'] && $r_open['to'] > '2099-01-01',
	'a start with no end stays open at the end', $r_open['from'] . '..' . $r_open['to'] );
$r_inv = bhela_bm_b2b_range( '2026-09-30', '2026-09-01' );
ok( $r_inv['from'] === $r_inv['to'], 'an inverted range collapses to one day rather than returning nothing' );
ok( '' === bhela_bm_b2b_range( 'nonsense', '' )['from'] || $r_all['all'], 'a malformed date does not become a filter' );

// The waiting count is deliberately computed outside the filter: a date window is
// the operator's choice, and a choice must not hide the thing needing action.
update_post_meta( $b_conf, '_bhela_referral', 'unconfirmed' );   // two waiting now
$p_all = bhela_bm_b2b_pending_all();
ok( 2 === $p_all['count'], 'referrals waiting are counted across every date', (string) $p_all['count'] );
$narrow = bhela_bm_b2b_rows( '2026-01-01', '2026-01-31' );
ok( 0 === $narrow['totals']['pending_n'] && 2 === $p_all['count'],
	'so a January filter shows none in range while the banner still says two' );
update_post_meta( $b_conf, '_bhela_referral', 'confirmed' );

// A cancelled trip owes nobody, so it is not waiting on anybody either.
update_post_meta( $b_cancel, '_bhela_referral', 'unconfirmed' );
ok( 1 === bhela_bm_b2b_pending_all()['count'], 'a cancelled booking is not counted as waiting',
	(string) bhela_bm_b2b_pending_all()['count'] );
update_post_meta( $b_cancel, '_bhela_referral', 'confirmed' );

foreach ( array( $b_hand, $b_pending, $b_conf, $b_cancel, $b_direct ) as $zz ) {
	bhela_test_delete( $zz );
}
// The agency directory is left alone — owner-built data with live referral tokens in
// it. bhela_test_owner_options() restores it; a delete_option() here took a real
// partner with it once already.

foreach ( $made as $id ) {
	delete_transient( 'bhela_bm_fb_warn_' . $id );
	delete_transient( 'bhela_bm_cap_err_' . $id );
	delete_transient( 'bhela_combo_err_' . $id );
	wp_delete_post( $id, true );
}
bhela_test_done();
