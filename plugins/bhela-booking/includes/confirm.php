<?php
/**
 * The booking confirmation message.
 *
 * Staff were typing this out by hand in WhatsApp, per booking — guest name, phone,
 * dates, room, cost, advance, due, all retyped from the admin screen next to it.
 * Every figure in it already existed in the booking record, so the only thing hand
 * typing added was the chance of sending somebody the wrong Due amount.
 *
 * This builds the same message from the booking and puts it on the clipboard.
 *
 * The template is a setting rather than a constant, so the wording can change
 * without a developer, and it is filled by bhela_bm_render_sms() rather than by a
 * second placeholder engine — one list of {tokens} to keep correct, shared with
 * the SMS templates.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The default confirmation template.
 *
 * Kept close to the wording the owner already sends, because guests are used to
 * it and it reads well in WhatsApp. Four deliberate changes from the hand-typed
 * version:
 *
 *   - One date format throughout. The original mixed `01-09-2026` with
 *     `22,August, 2026` in the same message.
 *   - The system's booking number, so the guest and the office quote the same
 *     reference. The hand-written scheme was a separate series that matched
 *     nothing in the admin.
 *   - The secure invoice link, which the platform already generates and the
 *     message was not using.
 *   - The cancellation term, which is on the invoice but was missing here — the
 *     one place a guest reads before paying.
 */
function bhela_bm_confirm_default_template() {
	return "🌊 BHELA – The Haor Exclusive\n"
		. "📌 Booking Confirmation\n\n"
		. "Confirmation No.: {invoice}\n\n"
		. "👤 Guest: {name}\n"
		. "📞 Contact: {phone}\n"
		. "📍 Address: {address}\n"
		. "✍️ Booking By: {booked_by}\n\n"
		. "📍 Boarding Ghat: {boarding}\n"
		. "🗓️ Check-in: {checkin} ({checkin_time})\n"
		. "🗓️ Check-out: {checkout} ({checkout_time})\n"
		. "🛶 Package: {package}\n\n"
		. "👥 Guests: {guests}\n"
		. "🛏️ Room Type: {room_type}\n"
		. "🏠 Room No: {room}\n\n"
		. "💰 Package Cost: {total}\n"
		. "✅ Advance Paid: {paid} {pay_method}\n"
		. "💳 Due Amount: {due}\n\n"
		. "🧾 Invoice: {invoice_link}\n\n"
		. "📅 Issued On: {issued_on}\n"
		. "✍️ Issued By: {issued_by}\n\n"
		. "Note:\n{notes}\n\n"
		. "⚠️ ৭ দিনের কম সময়ে বাতিলে কোনো রিফান্ড প্রযোজ্য নয়।\n\n"
		. "📞 Booking Support: {support_whatsapp}\n"
		. "👨‍💼 Operation Manager: {ops_manager}\n\n"
		. "ধন্যবাদ — BHELA – The Haor Exclusive";
}

/**
 * The standing service notes, as an array of lines.
 *
 * Two consumers want the same source in different shapes — the WhatsApp message
 * wants asterisks, the invoice wants a <ul> — so the split lives here once and each
 * formats it. A second copy of "explode the textarea and drop the blanks" is how
 * the two would end up disagreeing about a trailing empty line.
 *
 * @return string[]
 */
function bhela_bm_confirm_note_lines() {
	$raw = (string) ( bhela_bm_get_settings()['confirm_notes'] ?? '' );
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
}

/** The standard notes as a plain-text bullet list, for the WhatsApp message. */
function bhela_bm_confirm_notes() {
	$lines = bhela_bm_confirm_note_lines();
	return $lines ? '* ' . implode( "\n* ", $lines ) : '';
}

/**
 * The finished confirmation message for a booking.
 *
 * Blank fields are dropped rather than printed as an empty label. A line reading
 * "📍 Address:" with nothing after it looks like the system lost something; a
 * message without an address line just does not mention one. Address, room number
 * and the two staff names are all genuinely optional.
 *
 * @param int $booking_id Booking post ID.
 * @return string
 */
function bhela_bm_confirm_text( $booking_id ) {
	$s   = bhela_bm_get_settings();
	$tpl = (string) ( $s['confirm_template'] ?? '' );
	if ( '' === trim( $tpl ) ) {
		$tpl = bhela_bm_confirm_default_template();
	}

	$text = bhela_bm_render_sms( $tpl, $booking_id );

	// Drop any line whose value came out empty. Matched on "label: <nothing>" so a
	// line that is only a label survives — "Note:" heads a list and must stay.
	$out = array();
	foreach ( preg_split( '/\n/', $text ) as $line ) {
		if ( preg_match( '/^\s*[^\s:]*[\p{L}\p{N}\s\-\.\/]*:\s*$/u', $line ) && ! preg_match( '/^\s*(Note|নোট)\s*:/u', $line ) ) {
			continue;
		}
		$out[] = rtrim( $line );
	}
	// Collapse the runs of blank lines those removals leave behind.
	$text = preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $out ) );

	/**
	 * Filter the finished confirmation message.
	 *
	 * @param string $text       The message.
	 * @param int    $booking_id Booking post ID.
	 */
	return apply_filters( 'bhela_bm_confirm_text', trim( $text ), $booking_id );
}
