<?php
/**
 * Investors, shares, and the arithmetic that turns one into the other.
 *
 * BHELA is funded by shares of a fixed value — 115 × ৳1,00,000 by default, and
 * changeable in Settings because a second boat or a fresh round would otherwise mean
 * editing PHP. Everything downstream (the distribution engine, the ledger, ROI) reads
 * its percentages from here, so this file is the only place that decides what a share
 * is worth.
 *
 * An investor is a CUSTOM POST TYPE rather than an option array like the agency
 * directory. That is a deliberate departure: an agency is four fields, while an
 * investor carries ~25 plus a nominee, KYC scans and a signed agreement. A CPT gives
 * attachments, capability mapping and a real editing screen; cramming that into a
 * serialised option would mean re-implementing all three badly.
 *
 * It is `private` with no REST exposure, exactly like `bhela_booking` (CLAUDE.md
 * §3.5). An investor record holds a bank account, an NID and a home address. It must
 * never be addressable from the front end.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The post type
 * ========================================================= */

function bhela_bm_register_investor_cpt() {
	register_post_type( 'bhela_investor', array(
		'labels' => array(
			'name'          => __( 'Investors', 'bhela-booking' ),
			'singular_name' => __( 'Investor', 'bhela-booking' ),
			'menu_name'     => __( '👤 Investors', 'bhela-booking' ),
			'add_new'       => __( 'Add Investor', 'bhela-booking' ),
			'add_new_item'  => __( 'New Investor', 'bhela-booking' ),
			'edit_item'     => __( 'Investor', 'bhela-booking' ),
			'all_items'     => __( '👤 Investors', 'bhela-booking' ),
			'search_items'  => __( 'Search Investors', 'bhela-booking' ),
			'not_found'     => __( 'No investors yet.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		// Registered under Bookings and moved to Investors on `admin_menu`, for the
		// reason set out in menu.php: `init` runs before the current user resolves,
		// so show_in_menu can never depend on who is asking.
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'menu_icon'           => 'dashicons-groups',
	) );
}
add_action( 'init', 'bhela_bm_register_investor_cpt' );

/* =========================================================
 * Share configuration
 * ========================================================= */

/**
 * The share structure, from settings.
 *
 * @return array{total_investment:int,total_shares:int,per_share:int}
 */
function bhela_bm_share_config() {
	$s = bhela_bm_get_settings();
	return array(
		'total_investment' => max( 0, (int) ( $s['inv_total_investment'] ?? 0 ) ),
		'total_shares'     => max( 0, (int) ( $s['inv_total_shares'] ?? 0 ) ),
		'per_share'        => max( 0, (int) ( $s['inv_per_share'] ?? 0 ) ),
	);
}

/** Every investor, active first. Retired ones still resolve — history must read. */
function bhela_bm_investors( $include_inactive = true ) {
	$ids = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! $include_inactive && 'active' !== bhela_bm_investor_status( $id ) ) {
			continue;
		}
		$out[] = (int) $id;
	}
	return $out;
}

/** active | exited | suspended. Anything unrecognised reads as active. */
function bhela_bm_investor_status( $id ) {
	$s = (string) get_post_meta( $id, '_bhela_inv_status', true );
	return in_array( $s, array( 'exited', 'suspended' ), true ) ? $s : 'active';
}

/** Shares held. */
function bhela_bm_investor_shares( $id ) {
	return max( 0, (int) get_post_meta( $id, '_bhela_inv_shares', true ) );
}

/** What was actually paid in. Falls back to shares × per-share when not recorded. */
function bhela_bm_investor_amount( $id ) {
	$amount = (int) get_post_meta( $id, '_bhela_inv_amount', true );
	if ( $amount > 0 ) {
		return $amount;
	}
	$cfg = bhela_bm_share_config();
	return bhela_bm_investor_shares( $id ) * $cfg['per_share'];
}

/**
 * Share percentage, against the CONFIGURED total rather than the issued one.
 *
 * Using the configured total is the honest choice: if only 90 of 115 shares are
 * issued, the holders of those 90 own 78% of the boat between them and the remaining
 * 22% is unallocated — it does not belong to them. Dividing by the issued count
 * instead would quietly inflate every holder to fill the gap and pay out money the
 * business never earned on their behalf.
 *
 * bhela_bm_share_totals() surfaces that gap so somebody can decide what to do about
 * it. This function does not decide for them.
 */
function bhela_bm_investor_share_pct( $id ) {
	$cfg = bhela_bm_share_config();
	if ( $cfg['total_shares'] <= 0 ) {
		return 0.0;
	}
	return round( bhela_bm_investor_shares( $id ) / $cfg['total_shares'] * 100, 6 );
}

/**
 * Issued versus configured — and the gap between them.
 *
 * **Reports, never corrects.** Same contract as bhela_bm_inv_opening_drift() and the
 * cost sheet's earnings drift: when the books disagree with the configuration, a
 * person decides which is wrong. Silently rescaling the percentages to make the
 * numbers look tidy would hide a share issue nobody recorded.
 *
 * @return array{issued:int,configured:int,gap:int,over:bool,under:bool,pct_issued:float,investors:int}
 */
function bhela_bm_share_totals() {
	$cfg    = bhela_bm_share_config();
	$issued = 0;
	$people = 0;
	foreach ( bhela_bm_investors() as $id ) {
		$held = bhela_bm_investor_shares( $id );
		if ( $held > 0 ) {
			$people++;
		}
		$issued += $held;
	}
	$gap = $issued - $cfg['total_shares'];
	return array(
		'issued'     => $issued,
		'configured' => $cfg['total_shares'],
		'gap'        => $gap,
		'over'       => $gap > 0,
		'under'      => $gap < 0,
		'pct_issued' => $cfg['total_shares'] > 0 ? round( $issued / $cfg['total_shares'] * 100, 4 ) : 0.0,
		'investors'  => $people,
	);
}

/* =========================================================
 * Splitting money across shareholders
 * ========================================================= */

/**
 * Split a pot across investors by shareholding, losing nothing.
 *
 * **The parts must sum to the whole, exactly.** ৳63,000 over 115 shares is ৳547.826
 * a share; round each of 115 holdings independently and the total lands a few taka
 * off the pool. Those taka have to go somewhere, and "somewhere" cannot be nowhere —
 * a ledger that loses ৳7 every month stops reconciling within a season, and the
 * error is invisible until someone adds up a year.
 *
 * So: floor every allocation, then hand the remaining whole taka out one at a time,
 * largest fractional part first. Ties break on the larger holding, then on the lower
 * post id, so the same inputs always produce the same output — a distribution that
 * shuffles its own rounding between previews is not one anybody can check.
 *
 * **The divisor is the CONFIGURED share total, not the sum of the holders.** That
 * distinction only shows when the two disagree, which is exactly when it matters. If
 * 35 of 115 shares are issued and the pool is split across the 35, each of those
 * shares earns 3.3× what a share is worth — the holders quietly absorb the profit on
 * 80 shares nobody paid for. Dividing by 115 instead pays each share what a share is
 * owed and leaves the rest undistributed, which `unallocated` then reports.
 *
 * It also keeps this function honest with bhela_bm_investor_share_pct(), which has
 * always divided by the configured total. Two functions disagreeing about what a
 * share is worth is precisely the silent contradiction this codebase keeps getting
 * bitten by.
 *
 * @param int      $pot     Amount to split, in taka.
 * @param array    $holders id => shares. Zero-share holders are dropped.
 * @param int|null $divisor Shares the pot is measured against. Defaults to the
 *                          configured total; pass the holder sum only when the pot
 *                          genuinely belongs to those holders alone.
 * @return array id => amount. Sums to $pot when the holders cover the divisor.
 */
function bhela_bm_split_by_shares( $pot, $holders, $divisor = null ) {
	$pot = (int) $pot;
	$out = array();

	$holders = array_filter( array_map( 'intval', (array) $holders ) );
	if ( null === $divisor ) {
		$cfg     = bhela_bm_share_config();
		$divisor = $cfg['total_shares'];
	}
	$divisor = (int) $divisor;
	$held    = array_sum( $holders );
	// More issued than configured is a data error, not a licence to over-pay: fall
	// back to the issued sum so nobody receives more than 100% between them.
	$total = ( $divisor > 0 && $held <= $divisor ) ? $divisor : $held;
	if ( $pot <= 0 || $total <= 0 ) {
		foreach ( $holders as $id => $shares ) {
			$out[ $id ] = 0;
		}
		return $out;
	}

	$rema = array();
	$sum  = 0;
	foreach ( $holders as $id => $shares ) {
		$exact      = $pot * $shares / $total;
		$whole      = (int) floor( $exact );
		$out[ $id ] = $whole;
		$sum       += $whole;
		$rema[ $id ] = $exact - $whole;
	}

	// Only the holders' own portion is theirs to round up into. With 35 of 115 shares
	// issued the pot's remaining 80/115 is undistributed, not a rounding remainder.
	$due  = (int) floor( $pot * $held / $total );
	$left = max( 0, min( $due, $pot ) - $sum );
	if ( $left > 0 ) {
		$order = array_keys( $holders );
		usort( $order, function ( $a, $b ) use ( $rema, $holders ) {
			if ( $rema[ $a ] !== $rema[ $b ] ) {
				return $rema[ $b ] <=> $rema[ $a ];   // biggest fraction first
			}
			if ( $holders[ $a ] !== $holders[ $b ] ) {
				return $holders[ $b ] <=> $holders[ $a ]; // then the larger holding
			}
			return $a <=> $b;                          // then stable by id
		} );
		foreach ( array_slice( $order, 0, $left ) as $id ) {
			$out[ $id ]++;
		}
	}

	return $out;
}
