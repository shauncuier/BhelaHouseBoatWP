<?php
/**
 * Coupon codes.
 *
 * Modelled on the agency directory in agencies.php, which already solves this shape:
 * an owner-editable list with frozen keys and a retired flag, so a code that has been
 * withdrawn still reads correctly on the booking that used it.
 *
 * Two rules carry most of the weight here.
 *
 * **Checking a coupon must never redeem it.** bhela_bm_coupon_check() and
 * bhela_bm_coupon_redeem() are separate functions for exactly that reason: a guest
 * who presses Apply twice, reloads the page, or changes their dates would otherwise
 * burn a use each time, and a "first 20 bookings" coupon would be exhausted by
 * people who never booked.
 *
 * **The offer and a coupon never combine.** bhela_bm_discount() takes the larger of
 * the two, once. Stacking a 40% coupon onto a 30% offer sells at 58% off rack, which
 * is not a price anybody agreed to charge.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise a code the way it is stored and compared.
 *
 * Uppercased and stripped to A-Z0-9 so "save10", " Save10 " and "SAVE-10" cannot
 * become three different coupons — a guest reading a code off a leaflet types what
 * they see, not what was stored.
 *
 * @param mixed $code Raw code.
 * @return string
 */
function bhela_bm_coupon_code( $code ) {
	$code = is_string( $code ) ? $code : '';
	return substr( preg_replace( '/[^A-Z0-9]/', '', strtoupper( trim( $code ) ) ), 0, 24 );
}

/**
 * The coupon list.
 *
 * @param bool $include_retired Include codes no longer offered.
 * @return array code => array{type,value,label,expires,max_uses,uses,once_per_phone,retired}
 */
function bhela_bm_coupons( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_coupons', array() );
	if ( ! is_array( $saved ) ) {
		return array();
	}
	$out = array();
	foreach ( $saved as $code => $row ) {
		$code = bhela_bm_coupon_code( $code );
		if ( '' === $code || ! is_array( $row ) ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$type = ( 'amount' === ( $row['type'] ?? 'pct' ) ) ? 'amount' : 'pct';
		$out[ $code ] = array(
			'type'           => $type,
			// A percentage is capped at 90 for the same reason the offer is: a typo
			// of 100 should not be able to give the boat away.
			'value'          => 'pct' === $type
				? max( 0, min( 90, (int) ( $row['value'] ?? 0 ) ) )
				: max( 0, (int) ( $row['value'] ?? 0 ) ),
			'label'          => (string) ( $row['label'] ?? '' ),
			'expires'        => (string) ( $row['expires'] ?? '' ),
			'max_uses'       => max( 0, (int) ( $row['max_uses'] ?? 0 ) ),   // 0 = unlimited
			'uses'           => max( 0, (int) ( $row['uses'] ?? 0 ) ),
			'once_per_phone' => ! empty( $row['once_per_phone'] ),
			'retired'        => ! empty( $row['retired'] ),
			// Hashed, never plain. This is a uniqueness check, not a contact list —
			// a coupon record has no business holding guests' phone numbers.
			'_used_by'       => array_values( array_filter( (array) ( $row['_used_by'] ?? array() ), 'is_string' ) ),
		);
	}
	return $out;
}

/** One coupon, retired included — history has to stay readable. */
function bhela_bm_coupon( $code ) {
	$code = bhela_bm_coupon_code( $code );
	return '' === $code ? null : ( bhela_bm_coupons( true )[ $code ] ?? null );
}

/**
 * Save the list from the settings screen.
 *
 * `uses` and `_used_by` are carried across from the stored record and never taken
 * from the form. They are a ledger of what has already happened; an admin opening the
 * settings page and pressing Save must not be able to reset a coupon's usage by
 * accident, and the form has no business posting them at all.
 */
function bhela_bm_save_coupons( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$existing = bhela_bm_coupons( true );
	$out      = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$code = bhela_bm_coupon_code( $row['code'] ?? '' );
		if ( '' === $code ) {
			continue;               // a blank code deletes the row
		}
		$type = ( 'amount' === ( $row['type'] ?? 'pct' ) ) ? 'amount' : 'pct';
		$val  = (int) ( $row['value'] ?? 0 );

		$out[ $code ] = array(
			'type'           => $type,
			'value'          => 'pct' === $type ? max( 0, min( 90, $val ) ) : max( 0, $val ),
			'label'          => sanitize_text_field( $row['label'] ?? '' ),
			'expires'        => bhela_bm_report_date( $row['expires'] ?? '' ),
			'max_uses'       => max( 0, (int) ( $row['max_uses'] ?? 0 ) ),
			'once_per_phone' => ! empty( $row['once_per_phone'] ) ? 1 : 0,
			'retired'        => ! empty( $row['retired'] ) ? 1 : 0,
			'uses'           => (int) ( $existing[ $code ]['uses'] ?? 0 ),
			'_used_by'       => (array) ( $existing[ $code ]['_used_by'] ?? array() ),
		);
	}
	update_option( 'bhela_bm_coupons', $out );
}

/** How a phone number is recorded against a coupon. Hashed, never stored plain. */
function bhela_bm_coupon_phone_key( $phone ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
	return '' === $digits ? '' : wp_hash( 'coupon-phone|' . $digits );
}

/**
 * Can this code be used, and what is it worth on this total?
 *
 * **Read-only.** Nothing here writes; see bhela_bm_coupon_redeem().
 *
 * @param string $code       Raw code as typed.
 * @param int    $total      The rack total the discount applies to.
 * @param string $date       Travel date, for expiry.
 * @param string $phone      Guest mobile, for the once-per-phone rule.
 * @return array{ok:bool,reason:string,message:string,discount:int,label:string,code:string}
 */
function bhela_bm_coupon_check( $code, $total, $date = '', $phone = '' ) {
	$code = bhela_bm_coupon_code( $code );
	$no   = function ( $reason, $message ) use ( $code ) {
		return array( 'ok' => false, 'reason' => $reason, 'message' => $message,
			'discount' => 0, 'label' => '', 'code' => $code );
	};

	if ( '' === $code ) {
		return $no( 'empty', __( 'কুপন কোড লিখুন।', 'bhela-booking' ) );
	}
	$c = bhela_bm_coupons( true )[ $code ] ?? null;

	// An unknown code gets a generic refusal on purpose. A specific one ("no such
	// coupon" vs "expired") turns this endpoint into a way to enumerate the list.
	// A code that DOES exist gets a real reason, because that is a guest holding a
	// genuine coupon who needs to know why it will not apply.
	$generic = __( 'এই কুপন কোডটি এখানে প্রযোজ্য নয়।', 'bhela-booking' );
	if ( ! $c || $c['retired'] ) {
		return $no( 'unknown', $generic );
	}
	if ( $c['value'] <= 0 ) {
		return $no( 'unknown', $generic );
	}
	if ( $c['expires'] ) {
		$day = bhela_bm_report_date( $date );
		if ( $day && $day > $c['expires'] ) {
			return $no( 'expired', __( 'এই কুপনের মেয়াদ শেষ হয়ে গেছে।', 'bhela-booking' ) );
		}
	}
	if ( $c['max_uses'] > 0 && $c['uses'] >= $c['max_uses'] ) {
		return $no( 'exhausted', __( 'এই কুপনটি আর অবশিষ্ট নেই।', 'bhela-booking' ) );
	}
	if ( $c['once_per_phone'] && $phone ) {
		$key = bhela_bm_coupon_phone_key( $phone );
		if ( $key && in_array( $key, $c['_used_by'], true ) ) {
			return $no( 'used', __( 'এই নম্বরে কুপনটি একবার ব্যবহার হয়ে গেছে।', 'bhela-booking' ) );
		}
	}

	$total    = max( 0, (int) $total );
	$discount = 'pct' === $c['type']
		? (int) round( $total * $c['value'] / 100 )
		: (int) $c['value'];
	// A fixed coupon larger than the booking floors the total at zero rather than
	// going negative — a ৳5,000 coupon on a ৳3,000 booking is free, not a refund.
	$discount = max( 0, min( $discount, $total ) );

	return array(
		'ok'       => $discount > 0,
		'reason'   => $discount > 0 ? '' : 'no_value',
		'message'  => $discount > 0 ? '' : $generic,
		'discount' => $discount,
		'label'    => '' !== $c['label'] ? $c['label'] : $code,
		'code'     => $code,
	);
}

/**
 * Record that a coupon was actually used. The ONLY writer to `uses`.
 *
 * Called once, when the booking is created — never from the check endpoint.
 *
 * It re-reads the option immediately before writing rather than trusting a value
 * fetched earlier in the request. Two guests submitting on a coupon's last use is a
 * real race, and on a six-cabin boat a re-read is the proportionate answer; a
 * database-level lock would be more machinery than the problem deserves.
 *
 * @return bool Whether the redemption was recorded.
 */
function bhela_bm_coupon_redeem( $code, $phone = '', $booking_id = 0 ) {
	$code = bhela_bm_coupon_code( $code );
	if ( '' === $code ) {
		return false;
	}
	$all = get_option( 'bhela_bm_coupons', array() );
	if ( ! is_array( $all ) || ! isset( $all[ $code ] ) ) {
		return false;
	}
	$row = $all[ $code ];
	$max = max( 0, (int) ( $row['max_uses'] ?? 0 ) );
	$now = max( 0, (int) ( $row['uses'] ?? 0 ) );
	if ( $max > 0 && $now >= $max ) {
		return false;               // someone took the last one first
	}

	$row['uses'] = $now + 1;
	$key         = bhela_bm_coupon_phone_key( $phone );
	if ( $key ) {
		$used = (array) ( $row['_used_by'] ?? array() );
		if ( ! in_array( $key, $used, true ) ) {
			$used[] = $key;
		}
		$row['_used_by'] = $used;
	}
	$all[ $code ] = $row;
	update_option( 'bhela_bm_coupons', $all );

	if ( $booking_id && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( sprintf( 'Coupon %s redeemed on booking #%d (use %d)', $code, $booking_id, $row['uses'] ) );
	}
	return true;
}

/**
 * Which discount applies — the automatic offer, or a coupon, never both.
 *
 * The offer changes a per-person RATE; a coupon comes off a TOTAL. "Best wins" only
 * means anything once both are expressed in taka, which is why this compares
 * `rack - offer` against the coupon's value on the rack total.
 *
 * Measuring the coupon against RACK rather than against the already-discounted total
 * is what keeps the two from compounding. It also makes the comparison fair: both
 * numbers answer the same question — how much would this take off the full price.
 *
 * @param int    $rack   Total at regular rates, no discount.
 * @param int    $offer  Total with the automatic offer applied.
 * @param string $date   Travel date.
 * @param string $coupon Raw code, or ''.
 * @param string $phone  Guest mobile, for the once-per-phone rule.
 * @return array{source:string,amount:int,label:string,total:int,coupon:string,pct:int}
 */
function bhela_bm_discount( $rack, $offer, $date = '', $coupon = '', $phone = '' ) {
	$rack  = max( 0, (int) $rack );
	$offer = max( 0, (int) $offer );

	// The gap between rack and the priced total. On a weekday with no promotion this
	// is just the standing weekday rate, which is NOT a discount anybody is offering
	// — but it is still the number a coupon has to beat, because it is what the guest
	// would otherwise pay. So it is the comparison baseline either way, and only
	// *named* as an offer when a promotion is actually running.
	$off_amount = max( 0, $rack - $offer );
	$off        = bhela_bm_offer( $date );
	$promo      = ! empty( $off['active'] ) && $off_amount > 0;

	$out = array(
		'source' => $promo ? 'offer' : '',
		'amount' => $promo ? $off_amount : 0,
		'label'  => $promo ? $off['label'] : '',
		'pct'    => $promo ? (int) $off['pct'] : 0,
		'total'  => $offer,
		'coupon' => '',
	);

	$code = bhela_bm_coupon_code( $coupon );
	if ( '' === $code ) {
		return $out;
	}
	$chk = bhela_bm_coupon_check( $code, $rack, $date, $phone );
	if ( ! $chk['ok'] || $chk['discount'] <= $off_amount ) {
		// The coupon is invalid, or simply smaller than the promotion already
		// running. Either way the guest keeps the better price — the coupon is not
		// "rejected", it is just not the thing saving them money.
		$out['coupon'] = $code;
		return $out;
	}

	return array(
		'source' => 'coupon',
		'amount' => $chk['discount'],
		'label'  => $chk['label'],
		'pct'    => $rack > 0 ? (int) round( $chk['discount'] * 100 / $rack ) : 0,
		'total'  => max( 0, $rack - $chk['discount'] ),
		'coupon' => $code,
	);
}
