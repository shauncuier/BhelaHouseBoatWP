<?php
/**
 * Office locations — the branches, and who to ask for at each.
 *
 * The site has always carried one address: the boarding ghat a trip leaves from. That
 * is where a guest goes on the day, and it is not where the business sits. Three
 * offices take bookings in person, each with its own contact person, and none of them
 * appeared anywhere — so a guest in Chattogram had a mobile number and no idea there
 * was a desk two miles away.
 *
 * **The three shipped here are a DEFAULT, not markup.** An office moves, a contact
 * person changes, a branch opens; every one of those would be a developer task if this
 * lived in the page template. So the list is owner-editable in Settings → Business,
 * exactly like the seasons and the cost heads, and the defaults are what a site that
 * has never touched the screen shows.
 *
 * Two decisions worth knowing about:
 *
 * 1. **This file loads on EVERY request, not just wp-admin.** `seasons.php` and
 *    `income.php` are required inside `bhela-booking.php`'s `is_admin()` block — the
 *    indentation there hides it, and the consequence is that `bhela_bm_seasons()` does
 *    not exist on the front end at all, which is why the investor portal guards its
 *    one call to it with `function_exists()`. Offices are read by a page template, so
 *    the same placement would leave the contact page blank for ever with no error
 *    anywhere to explain it.
 * 2. **Zero offices is a saveable state.** bhela_bm_save_offices() writes the option
 *    unconditionally, the way bhela_bm_save_seasons() does and the way the heads lists
 *    deliberately do not — an office that closes has to be removable. That is why the
 *    getter distinguishes "never configured" from "emptied on purpose": the defaults
 *    come back only when nothing has ever been saved.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The offices as the owner supplied them.
 *
 * Addresses keep their real line breaks — these are postal addresses, and running
 * "House-676, Level-1, Flat-A2, West Shewrapara, Mirpur, Dhaka-1216" onto one line is
 * how a guest misreads a flat number as part of a road name.
 *
 * @return array key => array{name:string,address:string,contact_person:string,mobile:string}
 */
function bhela_bm_office_defaults() {
	return array(
		'dhaka'      => array(
			'name'           => __( 'Dhaka Office', 'bhela-booking' ),
			'address'        => "House-676, Level-1, Flat-A2,\nWest Shewrapara, Mirpur, Dhaka-1216\n(Near Metro Station)",
			'contact_person' => 'Uttam Saha',
			'mobile'         => '+880 1781-720957',
		),
		'mymensingh' => array(
			'name'           => __( 'Mymensingh Office', 'bhela-booking' ),
			'address'        => "Al Nur Bostraloy, Wapda Mor,\nKewatkhali, Mymensingh Sadar,\nMymensingh-2201, Bangladesh",
			'contact_person' => 'Md. Abdullah-Al-Nur (Rony)',
			'mobile'         => '+880 1716-665640',
		),
		'chattogram' => array(
			'name'           => __( 'Chattogram Office', 'bhela-booking' ),
			'address'        => "Karnafuly Cruise Line, Sholashahar 2nd Gate,\nIn Front of Karnafuly Complex,\nChattogram, Bangladesh",
			'contact_person' => 'Shohrab Uddin Russel',
			'mobile'         => '+880 1310-930600',
		),
	);
}

/**
 * The offices in force, in the order the owner listed them.
 *
 * `null` means the screen has never been saved, so the shipped three are what to show.
 * An empty ARRAY is different and is respected: the owner blanked every row and meant
 * it. Conflating the two would resurrect a closed office on every page load, which is
 * the same restraint bhela_bm_provision_portal_pages() shows about a deleted page.
 *
 * @return array key => array{key:string,name:string,address:string,contact_person:string,mobile:string}
 */
function bhela_bm_offices() {
	$saved = get_option( 'bhela_bm_offices', null );
	if ( null === $saved || ! is_array( $saved ) ) {
		$saved = bhela_bm_office_defaults();
	}

	$out = array();
	foreach ( $saved as $key => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$key  = sanitize_key( $key );
		$name = sanitize_text_field( $row['name'] ?? '' );
		// An office with no name cannot be rendered and cannot be asked for. It is
		// also how a row is deleted, so it is dropped rather than reported.
		if ( '' === $key || '' === $name ) {
			continue;
		}
		$out[ $key ] = array(
			'key'            => $key,
			'name'           => $name,
			// sanitize_textarea_field(), not sanitize_text_field(): the second one
			// flattens the newlines and the address becomes one run-on line.
			'address'        => sanitize_textarea_field( $row['address'] ?? '' ),
			'contact_person' => sanitize_text_field( $row['contact_person'] ?? '' ),
			'mobile'         => sanitize_text_field( $row['mobile'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Save the owner's office list.
 *
 * Lifted from bhela_bm_save_seasons() deliberately — two owner-editable lists behaving
 * differently is a surprise nobody needs. A blank name deletes the row; a slug is
 * minted from the name once and then travels in a hidden input, so renaming an office
 * does not orphan it.
 *
 * @param array $posted Raw `offices` input, already unslashed by the caller.
 */
function bhela_bm_save_offices( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = sanitize_text_field( $row['name'] ?? '' );
		if ( '' === $name ) {
			continue;                       // a blank name is a deletion
		}
		$key = sanitize_key( $row['key'] ?? '' );
		if ( '' === $key ) {
			$key = sanitize_key( substr( sanitize_title( $name ), 0, 32 ) );
			$key = $key ? $key : 'office';
		}
		$base = $key;
		$n    = 2;
		while ( isset( $seen[ $key ] ) ) {
			$key = $base . '_' . $n;
			$n++;
		}
		$seen[ $key ] = true;
		$out[ $key ]  = array(
			'name'           => $name,
			'address'        => sanitize_textarea_field( $row['address'] ?? '' ),
			'contact_person' => sanitize_text_field( $row['contact_person'] ?? '' ),
			'mobile'         => sanitize_text_field( $row['mobile'] ?? '' ),
		);
	}
	// Unconditional, even when $out is empty — see the file header. The heads lists
	// guard this call because a business must always have at least one income head;
	// an office that has closed is the opposite case.
	update_option( 'bhela_bm_offices', $out, false );
}

/**
 * An office's mobile as a `tel:` href.
 *
 * Digits and one leading `+`. The visible label keeps the spaces and dashes the owner
 * typed, because "+880 1781-720957" is how a person reads a number back to somebody
 * and "+8801781720957" is not.
 *
 * @param string $mobile As typed.
 * @return string Empty when there is nothing dialable.
 */
function bhela_bm_office_tel( $mobile ) {
	$tel = preg_replace( '/[^0-9+]/', '', (string) $mobile );
	$tel = preg_replace( '/(?!^)\+/', '', (string) $tel );   // a plus is only ever leading
	return preg_match( '/[0-9]/', (string) $tel ) ? $tel : '';
}
