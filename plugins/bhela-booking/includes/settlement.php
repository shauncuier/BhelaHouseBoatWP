<?php
/**
 * Settlement — who is ahead, who is behind, and in which direction.
 *
 * Profit has been paid out unevenly: some investors got nothing, some less than their
 * share, some more. So the money runs BOTH ways — BHELA owes some people, and some
 * people owe BHELA — and until this screen existed nothing said so.
 *
 * **No new arithmetic lives here.** The ledger has always known the answer: profit is a
 * `+1` row, payment and advance are `-1` (bhela_bm_ledger_types()), and
 * bhela_bm_investor_ledger() sums them with no floor, so an overpaid investor already
 * produced a negative closing balance. A second way of computing money would be a
 * second source of truth for it — §13.44's rule about two editable places holding one
 * number, applied to a reader. This file only windows that sum and splits it by
 * direction.
 *
 * Three things it gets right that the screens it replaces did not:
 *
 * 1. **The two directions are never netted.** One investor owed ৳50,000 and another
 *    owing ৳50,000 are not ৳0 — they are two separate debts that two different
 *    conversations settle. `bhela_bm_investor_dash_data()` was summing signed balances
 *    into one figure, which reads as "nothing outstanding" on a register where two
 *    people are out of pocket.
 * 2. **An investor with no declared profit is not an overpaid one.** If a period was
 *    never distributed in the system, everybody paid in it has `declared = 0` and would
 *    otherwise be reported as owing BHELA everything they received. That is a confident
 *    wrong number in front of the owner, so the state is named `undeclared` and is
 *    counted into NEITHER total.
 * 3. **A reversal is honoured wherever it sits.** The reversed-row set is built from the
 *    investor's WHOLE ledger, not from the window — a payment made inside the window and
 *    reversed after it is still a payment that never happened.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every investor's position inside a date window.
 *
 * Blank dates mean EVERY date. A sentinel range like `2000-01-01` reads as "no filter"
 * and is not one — it silently drops anything older, which is §13.24 and §13.51, twice
 * bitten already.
 *
 * The balance is `Σ signed` over the window, which is the ledger's own running sum
 * restricted to a period — so over an open window it equals `closing` exactly, and the
 * settlement can never disagree with the statement. The displayed parts satisfy
 *
 *     balance = declared + adjustments − paid
 *
 * exactly, which is why `paid` excludes a reversed row while `adjustments` excludes the
 * contra row that reversed it: counting both would cancel twice.
 *
 * @param string $from Y-m-d, or '' for no lower bound.
 * @param string $to   Y-m-d, or '' for no upper bound.
 * @return array
 */
function bhela_bm_settlement( $from = '', $to = '' ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );

	$out = array(
		'from'             => $from,
		'to'               => $to,
		'rows'             => array(),
		'declared'         => 0,
		'paid'             => 0,
		'adjustments'      => 0,
		// The two totals the whole feature exists to keep apart.
		'owed_to_investor' => 0,
		'owed_to_bhela'    => 0,
		// Carried because the dashboard has always shown a net figure. It is labelled
		// as a net wherever it appears and is never shown on its own.
		'net'              => 0,
		'count_owed'       => 0,
		'count_owes'       => 0,
		'count_settled'    => 0,
		'count_undeclared' => 0,
		'undeclared'       => 0,
	);

	foreach ( bhela_bm_investors() as $id ) {
		$row = bhela_bm_settlement_investor( $id, $from, $to );
		if ( null === $row ) {
			continue;                        // nothing happened to this investor here
		}
		$out['rows'][]      = $row;
		$out['declared']    += $row['declared'];
		$out['paid']        += $row['paid'];
		$out['adjustments'] += $row['adjustments'];
		$out['net']         += $row['balance'];

		switch ( $row['state'] ) {
			case 'undeclared':
				// Deliberately in NEITHER total — see the file header.
				$out['count_undeclared']++;
				$out['undeclared'] += $row['paid'];
				break;
			case 'owed':
				$out['owed_to_investor'] += $row['balance'];
				$out['count_owed']++;
				break;
			case 'owes':
				$out['owed_to_bhela'] += abs( $row['balance'] );
				$out['count_owes']++;
				break;
			default:
				$out['count_settled']++;
		}
	}

	// Biggest debt first, in both directions, so the conversations to have are at the
	// top of the screen rather than sorted by a name nobody is looking for.
	usort( $out['rows'], function ( $a, $b ) {
		return abs( $b['balance'] ) <=> abs( $a['balance'] );
	} );

	return $out;
}

/**
 * One investor's position inside the window, or null when nothing happened.
 *
 * @param int    $investor Investor post id.
 * @param string $from     Y-m-d or ''.
 * @param string $to       Y-m-d or ''.
 * @return array|null
 */
function bhela_bm_settlement_investor( $investor, $from = '', $to = '' ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );

	// The WHOLE ledger, then windowed here. Two reasons it is not read windowed:
	// a reversal written after the window still undoes a payment inside it, and
	// bhela_bm_ledger_reversal_of() is a query per row (§13.52) where the rows
	// already in hand answer the same question for free.
	$all      = bhela_bm_investor_ledger( $investor );
	$reversed = array();
	foreach ( $all['rows'] as $r ) {
		if ( $r['reverses'] ) {
			$reversed[ $r['reverses'] ] = true;
		}
	}

	$declared = 0;
	$paid     = 0;
	$adjust   = 0;
	$balance  = 0;
	$rows     = 0;

	foreach ( $all['rows'] as $r ) {
		if ( $from && $r['date'] < $from ) {
			continue;
		}
		if ( $to && $r['date'] > $to ) {
			continue;
		}
		$rows++;
		// The balance takes every row, reversed or not: a reversal is a contra row, so
		// the sum corrects itself. This is the same figure `closing` reports.
		$balance += $r['signed'];

		$undone = isset( $reversed[ $r['id'] ] );
		if ( 'profit' === $r['type'] ) {
			if ( ! $undone ) {
				$declared += $r['amount'];
			}
		} elseif ( in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
			if ( ! $undone ) {
				$paid += $r['amount'];
			}
		} elseif ( 'adjustment' === $r['type'] && ! $r['reverses'] ) {
			// A reversal IS an adjustment row. It is left out here because the row it
			// undoes has already been left out of `paid` — counting both cancels twice
			// and breaks `balance = declared + adjustments − paid`.
			$adjust += $r['signed'];
		}
	}

	if ( ! $rows ) {
		return null;
	}

	// Paid something, declared nothing. Not overpaid — undeclared. See the header.
	if ( 0 === $declared && $paid > 0 ) {
		$state = 'undeclared';
	} elseif ( $balance > 0 ) {
		$state = 'owed';
	} elseif ( $balance < 0 ) {
		$state = 'owes';
	} else {
		$state = 'settled';
	}

	return array(
		'investor'    => (int) $investor,
		'name'        => get_the_title( $investor ),
		'code'        => (string) get_post_meta( $investor, '_bhela_inv_code', true ),
		'shares'      => bhela_bm_investor_shares( $investor ),
		'declared'    => $declared,
		'paid'        => $paid,
		'adjustments' => $adjust,
		'balance'     => $balance,
		'state'       => $state,
	);
}

/**
 * The four states, in the words the office and the investor read.
 *
 * `owed` and `owes` are deliberately phrased from BHELA's side on the admin screens and
 * from the investor's side in the portal, because "outstanding" on its own has caused
 * exactly the confusion this feature is fixing.
 */
function bhela_bm_settlement_states() {
	return array(
		'owed'       => array(
			'label' => __( 'ভেলা দেবে', 'bhela-booking' ),
			'tone'  => 'attention',
		),
		'owes'       => array(
			'label' => __( 'ভেলা পাবে', 'bhela-booking' ),
			'tone'  => 'progress',
		),
		'settled'    => array(
			'label' => __( 'মিটে গেছে', 'bhela-booking' ),
			'tone'  => 'good',
		),
		'undeclared' => array(
			'label' => __( 'লাভ ঘোষণা হয়নি', 'bhela-booking' ),
			'tone'  => 'neutral',
		),
	);
}

/**
 * Resolve the screen's period filter to a from/to pair.
 *
 * A season is the 3–4 month stretch the owner actually thinks in, and the list already
 * exists — bhela_bm_seasons(). Anything a season does not cover takes a free range.
 * Blank means every date, and the caller must not substitute a sentinel.
 *
 * @param string $season Season key, or ''.
 * @param string $from   Y-m-d or ''.
 * @param string $to     Y-m-d or ''.
 * @return array{from:string,to:string,season:string,label:string}
 */
function bhela_bm_settlement_window( $season = '', $from = '', $to = '' ) {
	$season = sanitize_key( $season );
	if ( $season && function_exists( 'bhela_bm_season' ) ) {
		$s = bhela_bm_season( $season );
		if ( $s ) {
			return array(
				'from'   => $s['from'],
				'to'     => $s['to'],
				'season' => $season,
				'label'  => $s['label'],
			);
		}
	}
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	$label = ( $from || $to )
		? trim( ( $from ? mysql2date( 'j M Y', $from ) : __( 'শুরু থেকে', 'bhela-booking' ) )
			. ' — ' . ( $to ? mysql2date( 'j M Y', $to ) : __( 'আজ পর্যন্ত', 'bhela-booking' ) ) )
		: __( 'সব সময়', 'bhela-booking' );

	return array( 'from' => $from, 'to' => $to, 'season' => '', 'label' => $label );
}
