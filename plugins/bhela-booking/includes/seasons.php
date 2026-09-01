<?php
/**
 * Seasons — a name for a date range, and nothing more than that.
 *
 * The haor year is not the calendar year. The boat runs hard through the monsoon and
 * sits still either side of it, so "how did the season go" is the question the owner
 * actually asks, and until now the only answers available were twelve months or one
 * calendar year — neither of which is a season.
 *
 * **A season is a label over a from/to, never a second source for period boundaries.**
 * The Monthly Statement and the Yearly Report keep computing exactly what they compute
 * today; a season simply hands them a range. That constraint is the whole design: the
 * moment a season could define its own month boundaries there would be two answers to
 * "what did July make", and the wrong one would be the one on screen.
 *
 * Shipped empty on purpose. Inventing dates for somebody else's season would put a
 * confident, wrong range in front of them — the screens say so and ask for one.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The owner's seasons, oldest first.
 *
 * @return array key => array{key:string,label:string,from:string,to:string}
 */
function bhela_bm_seasons() {
	$saved = get_option( 'bhela_bm_seasons', array() );
	if ( ! is_array( $saved ) ) {
		return array();
	}
	$out = array();
	foreach ( $saved as $key => $row ) {
		$key   = sanitize_key( $key );
		$label = sanitize_text_field( is_array( $row ) ? ( $row['label'] ?? '' ) : '' );
		$from  = bhela_bm_report_date( is_array( $row ) ? ( $row['from'] ?? '' ) : '' );
		$to    = bhela_bm_report_date( is_array( $row ) ? ( $row['to'] ?? '' ) : '' );
		// A season with no dates cannot resolve to a range, so it is not a season —
		// it would silently report on everything or on nothing.
		if ( '' === $key || '' === $label || '' === $from || '' === $to || $to < $from ) {
			continue;
		}
		$out[ $key ] = array( 'key' => $key, 'label' => $label, 'from' => $from, 'to' => $to );
	}
	uasort( $out, function ( $a, $b ) {
		return strcmp( $a['from'], $b['from'] );
	} );
	return $out;
}

/** One season, or null. */
function bhela_bm_season( $key ) {
	$all = bhela_bm_seasons();
	return $all[ sanitize_key( $key ) ] ?? null;
}

/**
 * Overlapping season pairs, as labels.
 *
 * Overlaps are the owner's business, not an error: a two-day overlap at a boundary is
 * a normal thing to have typed and not worth refusing a save over. But an overlap
 * does change what `bhela_bm_season_for()` answers — the earliest-starting season
 * wins, silently — so the settings screen says so. This function exists because the
 * docblock below used to claim the screen warned when nothing did.
 *
 * @return array[] Each entry: array{a:string,b:string} of the two labels.
 */
function bhela_bm_season_overlaps() {
	$all  = array_values( bhela_bm_seasons() );
	$hits = array();
	$n    = count( $all );
	for ( $i = 0; $i < $n; $i++ ) {
		for ( $j = $i + 1; $j < $n; $j++ ) {
			// Ordered by `from`, so $all[$i] starts no later than $all[$j].
			if ( $all[ $j ]['from'] <= $all[ $i ]['to'] ) {
				$hits[] = array( 'a' => $all[ $i ]['label'], 'b' => $all[ $j ]['label'] );
			}
		}
	}
	return $hits;
}

/**
 * The season a date falls in, or null.
 *
 * Overlapping seasons are the owner's business; the earliest-starting match wins,
 * and `bhela_bm_season_overlaps()` is what lets the settings screen say so rather
 * than leaving the choice invisible.
 */
function bhela_bm_season_for( $date ) {
	$date = bhela_bm_report_date( $date );
	if ( '' === $date ) {
		return null;
	}
	foreach ( bhela_bm_seasons() as $s ) {
		if ( $date >= $s['from'] && $date <= $s['to'] ) {
			return $s;
		}
	}
	return null;
}

/**
 * Save the owner's season list.
 *
 * @param array $posted Raw `seasons` input.
 */
function bhela_bm_save_seasons( $posted ) {
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
		$key = sanitize_key( $row['key'] ?? '' );
		if ( '' === $key ) {
			$key = sanitize_key( substr( sanitize_title( $label ), 0, 32 ) );
			$key = $key ? $key : 'season';
		}
		$base = $key;
		$n    = 2;
		while ( isset( $seen[ $key ] ) ) {
			$key = $base . '_' . $n;
			$n++;
		}
		$seen[ $key ] = true;
		$out[ $key ]  = array(
			'label' => $label,
			'from'  => bhela_bm_report_date( $row['from'] ?? '' ),
			'to'    => bhela_bm_report_date( $row['to'] ?? '' ),
		);
	}
	update_option( 'bhela_bm_seasons', $out, false );
}

/**
 * A season's performance, per investor.
 *
 * Every figure here is replayed from stores that already exist. `declared` comes from
 * the ledger rows the distribution runs wrote, so a season is genuinely the sum of the
 * months in it rather than a separate calculation that could disagree with them.
 *
 * @param string $key Season key.
 * @return array|null
 */
function bhela_bm_season_investors( $key ) {
	$season = bhela_bm_season( $key );
	if ( ! $season ) {
		return null;
	}
	$out = array(
		'season' => $season,
		'rows'   => array(),
		'declared' => 0, 'paid' => 0, 'outstanding' => 0,
	);

	foreach ( bhela_bm_investors() as $id ) {
		$declared = 0;
		$paid     = 0;
		foreach ( bhela_bm_investor_ledger( $id )['rows'] as $r ) {
			if ( $r['date'] < $season['from'] || $r['date'] > $season['to'] ) {
				continue;
			}
			// A reversed row never happened, exactly as it does not in the position.
			if ( bhela_bm_ledger_reversal_of( $r['id'] ) || $r['reverses'] ) {
				continue;
			}
			if ( 'profit' === $r['type'] ) {
				$declared += $r['amount'];
			} elseif ( in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
				$paid += $r['amount'];
			}
		}
		if ( 0 === $declared && 0 === $paid ) {
			continue;
		}
		$out['rows'][] = array(
			'investor'    => (int) $id,
			'name'        => get_the_title( $id ),
			'shares'      => bhela_bm_investor_shares( $id ),
			'declared'    => $declared,
			'paid'        => $paid,
			// Within a season this is what was declared in it less what was paid in
			// it. It is deliberately NOT the investor's lifetime outstanding, which
			// is a different question with a different answer on the report screen.
			'outstanding' => $declared - $paid,
		);
		$out['declared']    += $declared;
		$out['paid']        += $paid;
		$out['outstanding'] += $declared - $paid;
	}

	usort( $out['rows'], function ( $a, $b ) {
		return $b['declared'] <=> $a['declared'];
	} );
	return $out;
}
