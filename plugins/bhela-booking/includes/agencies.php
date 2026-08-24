<?php
/**
 * B2B travel agencies, and the commission a booking owes one.
 *
 * A partner books on a guest's behalf and keeps a commission — ৳3,000 on a ৳30,000
 * trip. Two things follow from that, and they pull in opposite directions:
 *
 *   1. The guest must never see it. It is a commercial arrangement between BHELA
 *      and the agency, and it is not part of what the guest bought.
 *   2. BHELA must account for it, per agency. "How much did we pay Travel Compass
 *      this season" is a question the business has to be able to answer.
 *
 * So the commission lives on the booking, is deducted in the accounts, and is kept
 * off every guest-facing surface. booking-test asserts that last part, because it
 * is a rule about what must NOT appear and those rot silently.
 *
 * The directory is modelled on the staff roster in salary.php, which already solves
 * this shape: an owner-editable list with stable ids and a retired flag, so an old
 * booking still names who brought it after a partner stops trading.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The agency directory.
 *
 * @param bool $include_retired Include partners no longer trading.
 * @return array id => array{name,phone,email,rate,retired}
 */
function bhela_bm_agencies( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_agencies', array() );
	if ( ! is_array( $saved ) ) {
		return array();
	}
	$out = array();
	foreach ( $saved as $id => $row ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_array( $row ) || '' === ( $row['name'] ?? '' ) ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $id ] = array(
			'name'    => (string) $row['name'],
			'phone'   => (string) ( $row['phone'] ?? '' ),
			'email'   => (string) ( $row['email'] ?? '' ),
			// Percent. Used only to suggest an amount — see bhela_bm_agency_commission().
			'rate'    => max( 0, min( 100, (float) ( $row['rate'] ?? 0 ) ) ),
			'token'   => (string) ( $row['token'] ?? '' ),
			'retired' => ! empty( $row['retired'] ),
		);
	}
	return $out;
}

/**
 * Save the directory.
 *
 * Like the staff roster and the cost heads, an id is minted once from the name and
 * then frozen — it is the key a saved booking refers back to. Renaming an agency
 * must not orphan every booking it brought.
 */
function bhela_bm_save_agencies( $posted ) {
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
			continue;                       // a blank name deletes the row
		}
		$id = sanitize_key( $row['id'] ?? '' );
		if ( '' === $id ) {
			$id = sanitize_key( sanitize_title( $name ) ) ?: 'agency';
		}
		$base = $id;
		$n    = 2;
		while ( isset( $seen[ $id ] ) ) {
			$id = $base . '_' . $n;
			$n++;
		}
		$seen[ $id ] = true;

		// The referral token. Minted once and then carried, so an existing link keeps
		// working across every save of this screen. "regen" asks for a new one.
		$token = sanitize_text_field( $row['token'] ?? '' );
		if ( '' === $token || ! empty( $row['regen'] ) ) {
			$token = bhela_bm_agency_new_token();
		}

		$out[ $id ] = array(
			'name'    => $name,
			'phone'   => sanitize_text_field( $row['phone'] ?? '' ),
			'email'   => sanitize_email( $row['email'] ?? '' ),
			'rate'    => max( 0, min( 100, (float) ( $row['rate'] ?? 0 ) ) ),
			'token'   => $token,
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	update_option( 'bhela_bm_agencies', $out );
}

/**
 * One agency by id, retired ones included.
 *
 * Retired deliberately: a booking from two seasons ago still has to be able to
 * print who brought it, and a partner who stopped trading is exactly the case
 * where the name matters most.
 *
 * @param string $id Agency id.
 * @return array|null
 */
function bhela_bm_agency( $id ) {
	$id = sanitize_key( (string) $id );
	return '' === $id ? null : ( bhela_bm_agencies( true )[ $id ] ?? null );
}

/** The agency name for a booking, or '' for a direct booking. */
function bhela_bm_booking_agency_name( $booking_id ) {
	$a = bhela_bm_agency( get_post_meta( $booking_id, '_bhela_agency', true ) );
	return $a ? $a['name'] : '';
}

/**
 * What an agency's standard rate works out to on a given total.
 *
 * A suggestion only. The stored figure is the AMOUNT, not the percentage, because
 * it is a number somebody agreed with a partner — the same reason `_bhela_advance`
 * is kept exactly as typed and never recomputed when the price changes. A deal
 * struck at ৳3,500 on a ৳30,000 trip stays ৳3,500 when the total is corrected to
 * ৳32,000; it does not quietly become ৳3,200.
 *
 * @param string $agency_id Agency id.
 * @param int    $total     Booking total.
 * @return int Amount in taka, rounded.
 */
function bhela_bm_agency_commission( $agency_id, $total ) {
	$a = bhela_bm_agency( $agency_id );
	if ( ! $a || $a['rate'] <= 0 || $total <= 0 ) {
		return 0;
	}
	return (int) round( $total * $a['rate'] / 100 );
}

/**
 * Total commission owed on bookings travelling in a date range, by agency.
 *
 * Cancelled bookings are excluded: no trip, no commission. This is what the trip
 * cost sheet's B2B Partner line and the Monthly Statement both read, so the figure
 * is computed once here rather than summed independently in two places.
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return array{total:int,by_agency:array<string,array{name:string,total:int,bookings:int}>}
 */
function bhela_bm_commission_rows( $from, $to ) {
	$out = array( 'total' => 0, 'by_agency' => array() );

	$ids = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => '_bhela_travel_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );

	foreach ( $ids as $id ) {
		if ( 'cancelled' === get_post_meta( $id, '_bhela_status', true ) ) {
			continue;
		}
		$amount = (int) get_post_meta( $id, '_bhela_commission', true );
		if ( $amount <= 0 ) {
			continue;
		}
		// A referral attributes and SUGGESTS; it does not spend. Until a person
		// confirms it, the commission is absent from here — and therefore from the
		// Monthly Statement and the cost sheet's B2B line, since both read this one
		// function. That is the whole control: without it, appending ?ref= to a URL
		// would move money on its own, and the partner best placed to abuse that is
		// the one who owns the link.
		if ( 'unconfirmed' === get_post_meta( $id, '_bhela_referral', true ) ) {
			continue;
		}
		$agency_id = sanitize_key( (string) get_post_meta( $id, '_bhela_agency', true ) );
		// A commission with no agency named still counts against the month — the
		// money left the business either way. It is grouped under a clear label so
		// it reads as a gap to fill rather than as a partner called "".
		$key  = '' !== $agency_id ? $agency_id : '_none';
		$name = '' !== $agency_id
			? ( bhela_bm_agency( $agency_id )['name'] ?? $agency_id )
			: __( '(no agency named)', 'bhela-booking' );

		if ( ! isset( $out['by_agency'][ $key ] ) ) {
			$out['by_agency'][ $key ] = array( 'name' => $name, 'total' => 0, 'bookings' => 0 );
		}
		$out['by_agency'][ $key ]['total'] += $amount;
		$out['by_agency'][ $key ]['bookings']++;
		$out['total'] += $amount;
	}

	uasort( $out['by_agency'], function ( $a, $b ) {
		return $b['total'] <=> $a['total'];
	} );
	return $out;
}

/* =========================================================
 * REFERRAL LINKS
 * ========================================================= */

/** How long a followed referral is remembered. */
function bhela_bm_ref_days() {
	/**
	 * Filter the referral attribution window, in days.
	 *
	 * @param int $days Default 30.
	 */
	return (int) apply_filters( 'bhela_bm_ref_days', 30 );
}

/** The cookie a followed referral leaves behind. */
function bhela_bm_ref_cookie() {
	return 'bhela_ref';
}

/**
 * A fresh referral token.
 *
 * Random and STORED, rather than derived with wp_hash() the way an invoice or a
 * review key is. Those hang off a post id and are right to derive. This one is not:
 * an agency id is frozen by design, so a hash over it could never change — and a
 * partner whose link leaks, or who leaves on bad terms, needs a new link while
 * keeping the bookings they already brought. Only a stored token can be rotated.
 */
function bhela_bm_agency_new_token() {
	return strtolower( wp_generate_password( 12, false, false ) );
}

/** The public referral link for an agency, or '' if it has no token yet. */
function bhela_bm_agency_ref_url( $agency_id ) {
	$a = bhela_bm_agency( $agency_id );
	return ( $a && '' !== $a['token'] ) ? add_query_arg( 'ref', rawurlencode( $a['token'] ), home_url( '/' ) ) : '';
}

/**
 * Which agency a token belongs to.
 *
 * Retired partners are deliberately NOT resolved: the link of a partner who has
 * stopped trading must stop attributing, which is half of what retiring one means.
 * Their existing bookings still name them, because bhela_bm_agency() does include
 * retired rows.
 *
 * @param string $token
 * @return string Agency id, or '' when nothing matches.
 */
function bhela_bm_agency_by_token( $token ) {
	$token = sanitize_text_field( (string) $token );
	if ( '' === $token ) {
		return '';
	}
	foreach ( bhela_bm_agencies() as $id => $a ) {
		// hash_equals for the same reason the invoice key uses it: this decides
		// money, and a timing-safe compare costs nothing here.
		if ( '' !== $a['token'] && hash_equals( $a['token'], $token ) ) {
			return $id;
		}
	}
	return '';
}

/**
 * Catch ?ref=, remember it, and redirect to the clean URL.
 *
 * The redirect is not cosmetic. docs/CACHING.md documents running a full-page cache,
 * and a cached page never executes PHP — so a handler that only set a cookie in
 * place would work in testing and silently stop working in production, which is the
 * exact failure that file was written about. A URL carrying an unknown query string
 * misses the cache, so this request runs PHP, sets the cookie and redirects; the
 * clean URL is then served from cache as normal.
 *
 * It also keeps a guest from passing the link on with somebody else's referral tag
 * still attached.
 *
 * Last link wins: this simply overwrites whatever was there.
 */
function bhela_bm_capture_referral() {
	if ( is_admin() || wp_doing_ajax() || empty( $_GET['ref'] ) ) {
		return;
	}
	$agency_id = bhela_bm_agency_by_token( wp_unslash( $_GET['ref'] ) );

	// An unknown or retired token still redirects. Leaving ?ref= in the address bar
	// would let a guest pass on a link that attributes nothing, and looks broken.
	if ( '' !== $agency_id ) {
		$a = bhela_bm_agency( $agency_id );
		setcookie(
			bhela_bm_ref_cookie(),
			$a['token'],
			array(
				'expires'  => time() + ( bhela_bm_ref_days() * DAY_IN_SECONDS ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	$clean = remove_query_arg( 'ref' );
	wp_safe_redirect( $clean, 302 );
	exit;
}
add_action( 'template_redirect', 'bhela_bm_capture_referral', 5 );

/**
 * The agency a visitor arrived through, from their cookie.
 *
 * @return string Agency id, or '' when there is none.
 */
function bhela_bm_referred_agency() {
	$cookie = bhela_bm_ref_cookie();
	return isset( $_COOKIE[ $cookie ] ) ? bhela_bm_agency_by_token( wp_unslash( $_COOKIE[ $cookie ] ) ) : '';
}
