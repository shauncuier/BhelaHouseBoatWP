<?php
/**
 * Trip income heads — what a trip earned, broken down.
 *
 * A cost sheet has always carried one `_bhela_cost_earnings` figure, so "what did
 * food earn us last season" had no answer anywhere in the system. The costs side has
 * had owner-editable heads since the beginning; the income side had one box.
 *
 * The rule that keeps this from becoming a second set of books:
 *
 *     **When any head is filled, the sheet's earnings ARE the sum of the heads.**
 *
 * Not "beside", not "reconciled against" — the same figure, derived. The earnings box
 * stops being typeable the moment a head carries a value, because two editable places
 * holding one number is how they start to disagree. The booking auto-fill seeds Cabin
 * booking rather than sitting next to it, and `_bhela_cost_income_auto` distinguishes
 * a typed figure from a cached one exactly as `_bhela_cost_earnings_auto` does.
 *
 * A sheet with no heads filled behaves precisely as it did before, which is what lets
 * this ship without changing the value of a single already-approved sheet.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shipped list, from the owner's own trip sheet.
 *
 * `cabin` is first and is the one the booking figure seeds — every other head is
 * money taken on board that no booking record knows about.
 *
 * @return array slug => label
 */
function bhela_bm_income_head_defaults() {
	return array(
		'cabin'         => __( 'Cabin booking', 'bhela-booking' ),
		'food'          => __( 'Food', 'bhela-booking' ),
		'bbq'           => __( 'BBQ', 'bhela-booking' ),
		'extra_guest'   => __( 'Extra guest', 'bhela-booking' ),
		'extra_service' => __( 'Extra service', 'bhela-booking' ),
		'transport'     => __( 'Transportation', 'bhela-booking' ),
		'special'       => __( 'Special service', 'bhela-booking' ),
		'other'         => __( 'Other income', 'bhela-booking' ),
	);
}

/**
 * The income heads in force — the owner's list if they have edited it.
 *
 * Same shape and the same retirement rule as bhela_bm_cost_heads(): a retired head
 * stays resolvable so a sheet that used it still renders its label, it is simply not
 * offered on new sheets. Deleting outright would blank a figure on a month somebody
 * has already approved.
 *
 * @param bool $include_retired Include heads no longer offered on new sheets.
 * @return array slug => label
 */
function bhela_bm_income_heads( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_income_heads', null );
	if ( ! is_array( $saved ) || ! $saved ) {
		return bhela_bm_income_head_defaults();
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $slug ] = $label;
	}
	return $out ? $out : bhela_bm_income_head_defaults();
}

/**
 * Save the owner's income-head list.
 *
 * A slug is minted once from the first label and then frozen — renaming must not
 * change the key, or every sheet that used the head loses its figure. Lifted from
 * bhela_bm_save_cost_heads() deliberately: two lists behaving differently is a
 * surprise nobody needs.
 *
 * @param array $posted Raw `income_heads` input.
 */
function bhela_bm_save_income_heads( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;                       // a blank row is a deletion
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( substr( sanitize_title( $label ), 0, 32 ) );
			$slug = $slug ? $slug : 'head';
		}
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;
		$out[ $slug ]  = array(
			'label'   => $label,
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_option( 'bhela_bm_income_heads', $out, false );
	}
}

/** Head slugs carrying a figure on at least one saved sheet. Drives the "in use" column. */
function bhela_bm_income_heads_in_use() {
	$used = array();
	foreach ( get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) ) as $id ) {
		foreach ( bhela_bm_cost_income( $id ) as $slug => $amount ) {
			if ( $amount > 0 ) {
				$used[ $slug ] = true;
			}
		}
	}
	return array_keys( $used );
}

/**
 * One sheet's income, slug => taka.
 *
 * Returns only what is stored. A sheet saved before this existed returns an empty
 * array, which is what makes `bhela_bm_cost_income_total()` return 0 and every
 * already-approved sheet keep the earnings figure it was approved with.
 *
 * @param int $post_id Cost sheet.
 * @return array slug => int
 */
function bhela_bm_cost_income( $post_id ) {
	$raw = json_decode( (string) get_post_meta( $post_id, '_bhela_cost_income', true ), true );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $slug => $amount ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			continue;
		}
		$out[ $slug ] = max( 0, (int) $amount );
	}
	return $out;
}

/** The sum of a sheet's income heads. Zero means the sheet does not use them. */
function bhela_bm_cost_income_total( $post_id ) {
	return array_sum( bhela_bm_cost_income( $post_id ) );
}

/**
 * Read an income block off a submitted cost sheet.
 *
 * Kept out of the save handler so it is testable on its own, and so the rule that
 * earnings equal the sum lives in one readable place.
 *
 * @param array $posted Raw `bhela_cost_income` input.
 * @return array{lines:array<string,int>,total:int}
 */
function bhela_bm_income_read_post( $posted ) {
	$lines = array();
	if ( is_array( $posted ) ) {
		$heads = bhela_bm_income_heads( true );
		foreach ( $posted as $slug => $amount ) {
			$slug = sanitize_key( $slug );
			// Only a head the owner's list knows about. An unrecognised key is a
			// tampered or stale field, and silently keeping it would put money on
			// the sheet under a label nothing can render.
			if ( '' === $slug || ! isset( $heads[ $slug ] ) ) {
				continue;
			}
			$value = max( 0, (int) $amount );
			if ( $value > 0 ) {
				$lines[ $slug ] = $value;
			}
		}
	}
	return array( 'lines' => $lines, 'total' => array_sum( $lines ) );
}

/**
 * Income by head across a date range, from APPROVED sheets only.
 *
 * Approved only, for the same reason the Monthly Statement counts approved sheets: a
 * draft is a proposal. A revenue report that moved every time somebody opened a sheet
 * and typed in it would not be a report.
 *
 * The `unsplit` figure is the honest part — earnings on approved sheets that carry no
 * head breakdown at all. Folding those into "Other income" would invent a source;
 * leaving them out would make the total disagree with the statement. So they are
 * carried separately and named.
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return array{rows:array,total:int,unsplit:int,earnings:int,trips:int,from:string,to:string}
 */
function bhela_bm_income_rows( $from, $to ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	$out  = array(
		'rows' => array(), 'total' => 0, 'unsplit' => 0,
		'earnings' => 0, 'trips' => 0, 'from' => $from, 'to' => $to,
	);
	if ( '' === $from || '' === $to || $to < $from ) {
		return $out;
	}

	$heads = bhela_bm_income_heads( true );
	$sum   = array();

	foreach ( get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_bhela_cost_trip_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
		),
	) ) as $id ) {
		if ( 'approved' !== get_post_meta( $id, '_bhela_cost_status', true ) ) {
			continue;
		}
		$out['trips']++;
		$out['earnings'] += (int) get_post_meta( $id, '_bhela_cost_earnings', true );

		$income = bhela_bm_cost_income( $id );
		if ( ! $income ) {
			$out['unsplit'] += (int) get_post_meta( $id, '_bhela_cost_earnings', true );
			continue;
		}
		foreach ( $income as $slug => $amount ) {
			$sum[ $slug ] = ( $sum[ $slug ] ?? 0 ) + $amount;
		}
	}

	arsort( $sum );
	foreach ( $sum as $slug => $amount ) {
		$out['rows'][] = array(
			'slug'   => $slug,
			'label'  => $heads[ $slug ] ?? $slug,
			'amount' => $amount,
		);
		$out['total'] += $amount;
	}
	return $out;
}
