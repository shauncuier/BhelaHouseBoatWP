<?php
/**
 * Cash flow — money in, money out, over a date range.
 *
 * Deliberately NOT the Monthly Statement in a different hat. The statement answers
 * "did we trade profitably", which is an accrual question: an approved cost sheet
 * counts whether or not the supplier has been paid. This answers "where did the cash
 * go", which is a different question with a different answer, and a business can be
 * profitable and out of cash at the same time.
 *
 * Every figure is read from a store that already exists — bookings, cost sheets,
 * expenses, salary sheets, the investor ledger and the fund ledger. Nothing here has
 * its own entry screen, because a cash-flow line somebody types in by hand is a
 * fourth place for the same number to disagree with itself.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cash in and out for a range.
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return array
 */
function bhela_bm_cashflow( $from, $to ) {
	$out = array(
		'from' => $from, 'to' => $to,
		'in'   => array(), 'out' => array(),
		'in_total' => 0, 'out_total' => 0, 'net' => 0,
	);
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	if ( '' === $from || '' === $to || $to < $from ) {
		return $out;
	}

	/* ---------- Cash in: what guests actually paid ---------- */
	// Keyed on the PAYMENT date where one is recorded, falling back to the travel
	// date. Cash flow is about when money moved, so a booking paid in June for an
	// October trip is June's cash — using the travel date would report it four
	// months late and make every month's closing balance wrong.
	$paid = 0;
	$ids  = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		$amount = (int) get_post_meta( $id, '_bhela_paid_amount', true );
		if ( $amount <= 0 ) {
			continue;
		}
		if ( 'cancelled' === get_post_meta( $id, '_bhela_status', true ) ) {
			continue;
		}
		$when = bhela_bm_report_date( get_post_meta( $id, '_bhela_pay_date', true ) );
		if ( '' === $when ) {
			$when = bhela_bm_report_date( get_post_meta( $id, '_bhela_travel_date', true ) );
		}
		if ( '' === $when || $when < $from || $when > $to ) {
			continue;
		}
		$paid += $amount;
	}
	$out['in'][] = array( 'label' => __( 'Guest payments received', 'bhela-booking' ), 'amount' => $paid );

	/* ---------- Cash out ---------- */
	// Trip costs, from approved sheets only — a draft is a proposal, not a payment.
	$trip = 0;
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
		$trip += (int) get_post_meta( $id, '_bhela_cost_total', true );
	}
	$out['out'][] = array( 'label' => __( 'Trip costs', 'bhela-booking' ), 'amount' => $trip );

	$exp = function_exists( 'bhela_bm_expense_rows' ) ? bhela_bm_expense_rows( $from, $to ) : array( 'total' => 0 );
	$out['out'][] = array( 'label' => __( 'Other expenses', 'bhela-booking' ), 'amount' => (int) ( $exp['total'] ?? 0 ) );

	// Payroll, month by month across the range, from SAVED sheets only (§13.10).
	$salary = 0;
	if ( function_exists( 'bhela_bm_salary_month_total' ) ) {
		$cursor = substr( $from, 0, 7 );
		$last   = substr( $to, 0, 7 );
		$guard  = 0;
		while ( $cursor <= $last && $guard++ < 120 ) {
			$salary += (int) bhela_bm_salary_month_total( $cursor );
			$cursor  = gmdate( 'Y-m', strtotime( $cursor . '-01 +1 month' ) );
		}
	}
	$out['out'][] = array( 'label' => __( 'Staff salary', 'bhela-booking' ), 'amount' => $salary );

	// What actually left the business for investors — payments and advances, not
	// profit declared. Declared profit is a liability, not a cash movement.
	$to_investors = 0;
	foreach ( get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_led_date',
		'meta_query'     => array(
			array( 'key' => '_bhela_led_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
		),
	) ) as $id ) {
		$r = bhela_bm_ledger_row( $id );
		if ( ! $r || ! in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
			continue;
		}
		if ( bhela_bm_ledger_reversal_of( $r['id'] ) ) {
			continue;   // handed back; it never left
		}
		$to_investors += $r['amount'];
	}
	$out['out'][] = array( 'label' => __( 'Investor payments', 'bhela-booking' ), 'amount' => $to_investors );

	// Fund spending. The ALLOCATION is not cash out — it is an internal earmark, and
	// counting it would double-count against the trip costs it eventually pays for.
	foreach ( bhela_bm_funds() as $key => $fund ) {
		$led = bhela_bm_fund_ledger( $key, $from, $to );
		$out['out'][] = array(
			/* translators: %s: fund name */
			'label'  => sprintf( __( '%s spending', 'bhela-booking' ), $fund['label'] ),
			'amount' => (int) $led['used'],
		);
	}

	foreach ( $out['in'] as $r ) {
		$out['in_total'] += $r['amount'];
	}
	foreach ( $out['out'] as $r ) {
		$out['out_total'] += $r['amount'];
	}
	$out['net'] = $out['in_total'] - $out['out_total'];
	return $out;
}
