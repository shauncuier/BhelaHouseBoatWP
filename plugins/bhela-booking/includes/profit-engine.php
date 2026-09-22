<?php
/**
 * What an investment earns, period by period.
 *
 * The client's brief is explicit that the formula must NOT be hard-coded (§7): different
 * investment types are agreed on different bases, and a system with one formula baked in
 * quietly misstates every investment written on another one. So this is a registry —
 * four methods, each a small pure function, each declaring which fields it needs — and
 * the investment record names which one it was written on.
 *
 * Three properties the rest of the plugin depends on:
 *
 * 1. **The schedule is derived, never stored** (§13.8). Periods come from the start,
 *    the maturity and the payment frequency. Change the maturity and the schedule
 *    follows; a stored schedule would sit there being wrong.
 * 2. **The periods sum to the term, exactly.** Twelve periods each rounded on their own
 *    do not add up to the year: 12.5% on ৳5,00,000 is ৳62,500, and twelve rounded
 *    ৳5,208 is ৳62,496. So the term total is computed once and split across the periods
 *    by `bhela_bm_split_by_shares()`, the same largest-remainder rule §13.30 settled for
 *    the distribution. Losing four taka a year is how a ledger stops reconciling.
 * 3. **Posting is idempotent.** Each period carries a key — `{code}:{from}:{to}` — onto
 *    the ledger row's `ref`, and posting refuses when a row already carries it. Running
 *    a month twice is the most ordinary mistake there is, and it must not pay twice.
 *
 * What this file does NOT do is decide that money is owed. `bhela_bm_profit_accrue()`
 * is a reading; `bhela_bm_profit_post()` writes the ledger row and is called only by the
 * approval on the Profit Calculation screen (the brief's §17). Nothing accrues into
 * anybody's balance until a person approves it.
 *
 * Loaded on EVERY request: the portal shows an investor their own schedule.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * THE METHODS
 * ========================================================= */

/**
 * Day-count basis for the day-based method.
 *
 * 365 or 360, because both are used in practice and they give different answers —
 * ৳5,00,000 at 12% for 90 days is ৳14,795 on 365 and ৳15,000 on 360. The certificate
 * prints which one was used, so nobody has to reverse-engineer it from the figure.
 */
function bhela_bm_profit_day_basis() {
	$s = bhela_bm_get_settings();
	return 360 === (int) ( $s['inv_day_basis'] ?? 0 ) ? 360 : 365;
}

/**
 * The calculation methods, keyed by the slug an investment stores.
 *
 * A slug is FROZEN once an investment is active — every posted row and every issued
 * certificate hangs off it. `rate` is what the rate field MEANS for that method, which
 * is why the screen labels it from here rather than saying "rate" four times.
 */
function bhela_bm_profit_methods() {
	return array(
		'fixed_annual' => array(
			'label'   => __( 'নির্দিষ্ট বার্ষিক হার', 'bhela-booking' ),
			'en'      => __( 'Fixed annual rate', 'bhela-booking' ),
			'rate'    => __( 'বার্ষিক হার %', 'bhela-booking' ),
			'formula' => __( 'মূলধন × বার্ষিক হার × মেয়াদ (মাস) ÷ ১২', 'bhela-booking' ),
		),
		'monthly_rate' => array(
			'label'   => __( 'মাসিক হার', 'bhela-booking' ),
			'en'      => __( 'Monthly rate', 'bhela-booking' ),
			'rate'    => __( 'মাসিক হার %', 'bhela-booking' ),
			'formula' => __( 'মূলধন × মাসিক হার × মাস', 'bhela-booking' ),
		),
		'day_based'    => array(
			'label'   => __( 'দিনভিত্তিক', 'bhela-booking' ),
			'en'      => __( 'Day-based', 'bhela-booking' ),
			'rate'    => __( 'বার্ষিক হার %', 'bhela-booking' ),
			'formula' => __( 'মূলধন × বার্ষিক হার × প্রকৃত দিন ÷ দিন-ভিত্তি', 'bhela-booking' ),
		),
		'profit_share' => array(
			'label'   => __( 'লাভ-বণ্টন', 'bhela-booking' ),
			'en'      => __( 'Profit sharing', 'bhela-booking' ),
			'rate'    => __( 'বিনিয়োগকারীর অংশ %', 'bhela-booking' ),
			'formula' => __( 'বণ্টনযোগ্য লাভ × বিনিয়োগকারীর অংশ', 'bhela-booking' ),
		),
	);
}

/**
 * What BHELA declared distributable inside a window, and the months it cannot answer for.
 *
 * A distribution run is monthly and a term is not, so a period's pot is the sum of the
 * runs inside it. A run belongs to the window when the **first day of its month** falls
 * inside it; a boundary that cuts a month in half names that month as partial and
 * apportions NOTHING. Splitting a month's profit across a boundary would be an
 * invention — nothing anywhere records which half of July a trip's profit belonged to.
 *
 * Moved here from includes/certificates.php: the profit engine needs it for the
 * `profit_share` method and the certificate needs it for its face, which is two callers
 * and therefore §13.22's rule about a shared helper parked in one screen's file.
 *
 * @return array
 */
function bhela_bm_distributable_pot( $from, $to ) {
	$out = array(
		'distributable' => 0,
		'investor_pot'  => 0,
		'reserve'       => 0,
		'gross'         => 0,
		'runs'          => array(),
		'missing'       => array(),
		'partial'       => array(),
	);
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	if ( '' === $from || '' === $to || $to < $from ) {
		return $out;
	}

	$index  = (array) get_option( 'bhela_bm_dist_runs', array() );
	$cursor = substr( $from, 0, 7 ) . '-01';
	$guard  = 0;

	while ( $cursor <= $to && $guard++ < 240 ) {
		$month = substr( $cursor, 0, 7 );
		$first = $month . '-01';

		if ( $first >= $from ) {
			$run_id = isset( $index[ $month ] ) ? (int) $index[ $month ] : 0;
			if ( $run_id && get_post( $run_id ) && function_exists( 'bhela_bm_dist_data' ) ) {
				$d                     = bhela_bm_dist_data( $run_id );
				$out['runs'][]         = $d;
				$out['distributable'] += $d['distributable'];
				$out['investor_pot']  += $d['investor'];
				$out['reserve']       += $d['reserve'];
				$out['gross']         += $d['gross'];
			} else {
				$out['missing'][] = $month;
			}
		} else {
			// The window starts mid-month. That month's run covers days outside it, so
			// it is named and not counted.
			$out['partial'][] = $month;
		}

		$cursor = gmdate( 'Y-m-d', strtotime( $first . ' +1 month' ) );
	}

	// A window ending mid-month has the same problem at the other end.
	$last_first = substr( $to, 0, 7 ) . '-01';
	$last_end   = gmdate( 'Y-m-t', strtotime( $last_first ) );
	if ( $to < $last_end && ! in_array( substr( $to, 0, 7 ), $out['partial'], true ) ) {
		$out['partial'][] = substr( $to, 0, 7 );
	}

	return $out;
}

/**
 * What one investment earns over its WHOLE term, before it is split into periods.
 *
 * Pure. The term total is the anchor: periods are carved out of it rather than summed
 * into it, which is what keeps twelve months adding up to the year (see the file
 * header). Returns 0 for anything it cannot compute rather than guessing.
 *
 * @param array $inv A bhela_bm_investment() record.
 * @return int Taka.
 */
function bhela_bm_profit_term_total( $inv ) {
	$principal = (int) ( $inv['principal'] ?? 0 );
	$rate      = (int) ( $inv['rate_bp'] ?? 0 ) / 10000;   // basis points → a fraction
	$months    = (int) ( $inv['months'] ?? 0 );

	if ( $principal <= 0 || $rate <= 0 || $months <= 0 ) {
		return 0;
	}

	// Money that arrived part-way through the term earns only from when it arrived.
	// This path engages only when the principal genuinely varies over the term; a
	// single receipt at the start takes the formula below exactly as it always has.
	$held = bhela_bm_profit_held( $inv );
	if ( $held ) {
		return (int) round( $held['unit'] * $held['weighted'] / $held['weight_sum'] );
	}

	switch ( (string) ( $inv['method'] ?? '' ) ) {
		case 'fixed_annual':
			return (int) round( $principal * $rate * $months / 12 );

		case 'monthly_rate':
			return (int) round( $principal * $rate * $months );

		case 'day_based':
			$days = bhela_bm_profit_days( $inv['start'], $inv['maturity'] );
			return (int) round( $principal * $rate * $days / bhela_bm_profit_day_basis() );

		case 'profit_share':
			// Not a function of the principal at all: it is a share of what the business
			// actually declared. A term with no committed distribution in it earns
			// nothing here, and the screen says which months are missing rather than
			// quietly returning a smaller number.
			$pot = bhela_bm_distributable_pot( $inv['start'], $inv['maturity'] );
			return (int) round( $pot['investor_pot'] * $rate );
	}
	return 0;
}

/** Inclusive day count between two dates. */
function bhela_bm_profit_days( $from, $to ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	if ( '' === $from || '' === $to || $to < $from ) {
		return 0;
	}
	$a = new DateTimeImmutable( $from );
	$b = new DateTimeImmutable( $to );
	return (int) $a->diff( $b )->days + 1;
}

/* =========================================================
 * THE SCHEDULE
 * ========================================================= */

/** The reference a posted row carries, and the thing that makes posting idempotent. */
function bhela_bm_profit_period_key( $code, $from, $to ) {
	return $code . ':' . $from . ':' . $to;
}

/**
 * Every period of an investment's term, with what each one earns.
 *
 * PURE — it writes nothing and it is what the Profit Calculation screen renders, so what
 * is approved on screen is exactly what gets posted. Same contract
 * `bhela_bm_dist_preview()` has with its commit.
 *
 * @param array $inv A bhela_bm_investment() record.
 * @return array[] from · to · days · months · amount · key · due
 */
/**
 * The period boundaries alone — shared by the schedule and by the held-principal
 * weighting, so the two can never disagree about where a period begins.
 *
 * @return array[] Each `from`, `to`.
 */
function bhela_bm_profit_periods( $inv ) {
	$start    = (string) ( $inv['start'] ?? '' );
	$maturity = (string) ( $inv['maturity'] ?? '' );
	if ( '' === $start || '' === $maturity || $maturity <= $start ) {
		return array();
	}

	$freqs = bhela_bm_investment_freqs();
	$step  = (int) ( $freqs[ $inv['frequency'] ?? '' ]['months'] ?? 0 );

	$periods = array();
	if ( $step < 1 ) {
		// Paid at maturity: one period covering the whole term.
		$periods[] = array( 'from' => $start, 'to' => $maturity );
	} else {
		// Every boundary is measured from the START, not from the previous one. Adding
		// a month to the previous cursor compounds PHP's month-overflow: an investment
		// beginning on the 31st lost February and then drifted a day further every
		// period. bhela_bm_month_step() clamps instead of overflowing.
		$guard = 0;
		for ( $k = 0; $k < 600; $k++ ) {
			$from = bhela_bm_month_step( $start, $k * $step );
			if ( $from > $maturity ) {
				break;
			}
			$next = bhela_bm_month_step( $start, ( $k + 1 ) * $step );
			$end  = gmdate( 'Y-m-d', strtotime( $next . ' -1 day' ) );
			if ( $end >= $maturity ) {
				$end = $maturity;             // the last period always closes on maturity
			}
			$periods[] = array( 'from' => $from, 'to' => $end );
			if ( $end >= $maturity ) {
				break;
			}
			$guard++;
		}
	}
	return $periods;
}

/** How much each period weighs: days for the day-based method, whole months otherwise. */
function bhela_bm_profit_period_weight( $inv, $p ) {
	return ( 'day_based' === ( $inv['method'] ?? '' ) )
		? bhela_bm_profit_days( $p['from'], $p['to'] )
		: max( 1, bhela_bm_investment_months( $p['from'], $p['to'] ) );
}

/**
 * The principal actually held in each period — or null when it never varies.
 *
 * The first version priced every period on the investment's CURRENT principal. So a
 * ৳1,00,000 top-up received on 1 October re-priced July, August and September as
 * though the money had been there all along: periods already approved and paid at
 * ৳5,000 re-displayed as ৳6,000, the term total rose by what three months of money
 * the business never held would have earned, and the investor was owed it.
 *
 * A receipt counts in full for every period that starts on or after the day it
 * arrived, pro rata by days for the period it arrives in, and not at all before. A
 * receipt dated before the term counts from the start — the agreement is what starts
 * the clock. Returns null when every period holds the same principal, so the ordinary
 * single-receipt investment keeps its original arithmetic to the taka.
 *
 * @return array{eff:float[],w:int[],weighted:float,weight_sum:int,unit:float}|null
 */
function bhela_bm_profit_held( $inv ) {
	$method = (string) ( $inv['method'] ?? '' );
	$id     = (int) ( $inv['id'] ?? 0 );
	if ( 'profit_share' === $method || ! $id || ! function_exists( 'bhela_bm_capital_rows_for_investment' ) ) {
		return null;
	}
	$rows = bhela_bm_capital_rows_for_investment( $id );
	if ( ! $rows ) {
		return null;
	}
	$periods = bhela_bm_profit_periods( $inv );
	if ( ! $periods ) {
		return null;
	}

	$start = (string) $inv['start'];
	$eff   = array();
	$w     = array();
	foreach ( $periods as $i => $p ) {
		$len  = max( 1, bhela_bm_profit_days( $p['from'], $p['to'] ) );
		$held = 0.0;
		foreach ( $rows as $r ) {
			$d = (string) $r['date'];
			$d = ( '' === $d || $d < $start ) ? $start : $d;
			if ( $d <= $p['from'] ) {
				$held += (int) $r['amount'];
			} elseif ( $d <= $p['to'] ) {
				$held += (int) $r['amount'] * bhela_bm_profit_days( $d, $p['to'] ) / $len;
			}
		}
		$eff[ $i ] = $held;
		$w[ $i ]   = bhela_bm_profit_period_weight( $inv, $p );
	}

	// Constant principal: hand back to the original formula.
	if ( count( array_unique( array_map( 'strval', $eff ) ) ) === 1 && (float) reset( $eff ) === (float) (int) ( $inv['principal'] ?? 0 ) ) {
		return null;
	}

	$rate   = (int) ( $inv['rate_bp'] ?? 0 ) / 10000;
	$months = (int) ( $inv['months'] ?? 0 );
	switch ( $method ) {
		case 'fixed_annual':
			$unit = $rate * $months / 12;
			break;
		case 'monthly_rate':
			$unit = $rate * $months;
			break;
		case 'day_based':
			$unit = $rate * bhela_bm_profit_days( $inv['start'], $inv['maturity'] ) / bhela_bm_profit_day_basis();
			break;
		default:
			return null;
	}

	$weighted = 0.0;
	foreach ( $eff as $i => $e ) {
		$weighted += $e * $w[ $i ];
	}
	return array(
		'eff'        => $eff,
		'w'          => $w,
		'weighted'   => $weighted,
		'weight_sum' => max( 1, array_sum( $w ) ),
		'unit'       => $unit,
	);
}

function bhela_bm_profit_schedule( $inv ) {
	$periods = bhela_bm_profit_periods( $inv );
	if ( ! $periods ) {
		return array();
	}

	// The term total, carved up. Weighted by days for the day-based method and by month
	// count for the rest, so an uneven final period gets its honest share rather than a
	// full one.
	$total   = bhela_bm_profit_term_total( $inv );
	$weights = array();
	foreach ( $periods as $i => $p ) {
		$weights[ $i ] = bhela_bm_profit_period_weight( $inv, $p );
	}
	// Where the principal varied, each period's share is its weight times the money
	// actually held in it — still carved by largest remainder, so the periods sum to
	// the term total to the taka (§13.30). Weights are in paisa so a pro-rata part of
	// a period is not truncated away by the integer split.
	$held  = bhela_bm_profit_held( $inv );
	$carve = $weights;
	if ( $held ) {
		foreach ( $carve as $i => $wt ) {
			$carve[ $i ] = (int) round( $held['eff'][ $i ] * $wt * 100 );
		}
	}
	$split = bhela_bm_split_by_shares( $total, $carve, array_sum( $carve ) );

	$out = array();
	foreach ( $periods as $i => $p ) {
		$out[] = array(
			'index'  => $i + 1,
			'from'   => $p['from'],
			'to'     => $p['to'],
			'days'   => bhela_bm_profit_days( $p['from'], $p['to'] ),
			'months' => $weights[ $i ],
			'amount' => (int) ( $split[ $i ] ?? 0 ),
			'key'    => bhela_bm_profit_period_key( (string) ( $inv['code'] ?? '' ), $p['from'], $p['to'] ),
			// A period is due once its last day has passed. Accruing tomorrow's profit
			// today would put money on a statement the business has not yet earned.
			'due'    => $p['to'] <= current_time( 'Y-m-d' ),
		);
	}
	return $out;
}

/**
 * Has this period already been posted to the ledger?
 *
 * Reads the ledger by the period key on `ref`. A reversed row still counts as posted —
 * reversing a wrong accrual and re-posting the same period would leave two rows and one
 * contra, which nets correctly but reads as though the investor was paid twice.
 *
 * @return int Ledger row id, or 0.
 */
function bhela_bm_profit_posted( $key ) {
	$hit = get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_led_ref',
		'meta_value'     => (string) $key,
	) );
	return $hit ? (int) $hit[0] : 0;
}

/**
 * One investment's schedule with each period's posted state attached.
 *
 * @param array  $inv  A bhela_bm_investment() record.
 * @param string $upto Only periods ending on or before this date. Blank means today.
 * @return array
 */
function bhela_bm_profit_accrue( $inv, $upto = '' ) {
	$upto = bhela_bm_report_date( $upto );
	$upto = '' === $upto ? current_time( 'Y-m-d' ) : $upto;

	$out = array(
		'investment' => (int) ( $inv['id'] ?? 0 ),
		'code'       => (string) ( $inv['code'] ?? '' ),
		'periods'    => array(),
		'due'        => 0,
		'posted'     => 0,
		'unposted'   => 0,
		'term_total' => bhela_bm_profit_term_total( $inv ),
	);

	foreach ( bhela_bm_profit_schedule( $inv ) as $p ) {
		if ( $p['to'] > $upto ) {
			continue;
		}
		$p['row'] = bhela_bm_profit_posted( $p['key'] );
		// A posted period is a fact in the ledger, and the screen shows THAT figure —
		// never a recomputation. Terms can move under a posted period (a back-dated
		// receipt, a reopened record); when they do, `computed` keeps the new
		// arithmetic and `drift` says by how much, so somebody can decide on an
		// adjustment instead of the page quietly restating what was paid.
		$p['computed'] = $p['amount'];
		$p['drift']    = 0;
		if ( $p['row'] ) {
			$posted_row  = bhela_bm_ledger_row( $p['row'] );
			$p['amount'] = $posted_row ? (int) $posted_row['amount'] : $p['amount'];
			$p['drift']  = $p['computed'] - $p['amount'];
		}
		$out['periods'][]  = $p;
		$out['due']       += $p['amount'];
		if ( $p['row'] ) {
			$out['posted'] += $p['amount'];
		} else {
			$out['unposted'] += $p['amount'];
		}
	}
	return $out;
}

/* =========================================================
 * POSTING
 * ========================================================= */

/**
 * Write one period's profit to the ledger. The ONLY thing here that writes money.
 *
 * Called by the approval on the Profit Calculation screen, never by a reader. It refuses
 * a period that is already posted, a period that has not ended, and an investment that
 * is not active — the three ways the same money could otherwise be owed twice.
 *
 * @param array $inv    A bhela_bm_investment() record.
 * @param array $period One row from bhela_bm_profit_schedule().
 * @return int|WP_Error Ledger row id.
 */
function bhela_bm_profit_post( $inv, $period ) {
	if ( ! current_user_can( 'bhela_investor_profit' ) ) {
		return new WP_Error( 'denied', __( 'লাভ অনুমোদনের অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( empty( $inv['id'] ) || 'active' !== ( $inv['status'] ?? '' ) ) {
		return new WP_Error( 'not_active', __( 'সক্রিয় নয় এমন বিনিয়োগে লাভ যোগ করা যাবে না।', 'bhela-booking' ) );
	}
	$key = (string) ( $period['key'] ?? '' );
	if ( '' === $key ) {
		return new WP_Error( 'bad_period', __( 'সময়কাল সঠিক নয়।', 'bhela-booking' ) );
	}
	if ( empty( $period['due'] ) ) {
		return new WP_Error( 'not_due', __( 'এই সময়কাল এখনো শেষ হয়নি।', 'bhela-booking' ) );
	}
	if ( (int) ( $period['amount'] ?? 0 ) <= 0 ) {
		return new WP_Error( 'zero', __( 'এই সময়কালে হিসাব করার মতো লাভ নেই।', 'bhela-booking' ) );
	}
	// The idempotency check, and the reason the screen can be refreshed safely.
	$already = bhela_bm_profit_posted( $key );
	if ( $already ) {
		return new WP_Error( 'already', sprintf(
			/* translators: %s: the period */
			__( 'এই সময়কাল (%s) আগেই হিসাবে যোগ করা হয়েছে।', 'bhela-booking' ),
			$period['from'] . ' — ' . $period['to']
		) );
	}

	return bhela_bm_ledger_add( array(
		'investor' => (int) $inv['investor'],
		'type'     => 'profit',
		'amount'   => (int) $period['amount'],
		// Dated the last day of the period it belongs to, not the day somebody pressed
		// the button — otherwise a month approved late lands in the wrong window and
		// every season figure that reads the ledger moves with it.
		'date'     => (string) $period['to'],
		'ref'      => $key,
		'method'   => (string) $inv['method'],
		'note'     => sprintf(
			/* translators: 1: investment code, 2: period start, 3: period end */
			__( '%1$s — %2$s থেকে %3$s', 'bhela-booking' ),
			(string) $inv['code'],
			(string) $period['from'],
			(string) $period['to']
		),
	) );
}

/**
 * Investment code => post id, for the codes ASKED ABOUT and no others.
 *
 * It used to build the whole map by listing every investment up to
 * `bhela_bm_investment_limit()` — 500 — and that cap was load-bearing in the worst way:
 * an investment past it resolved to nothing, its accrued profit was quietly dropped from
 * `bhela_bm_profit_accrued()`, and the Monthly Statement then reported a gross profit
 * HIGHER than the truth with no warning anywhere. A cap on a listing is a paging
 * decision; a cap on a figure is a wrong figure (§13.40's reason, one layer down).
 *
 * Resolving only the codes present on the rows removes the cap entirely, because the
 * question is now bounded by the window rather than by how many investments exist. It
 * stays inside WP_Query rather than dropping to SQL so `posts_where` — and with it the
 * harnesses' post-type isolation — still applies (§13.66).
 *
 * @param string[] $codes Investment codes seen on the ledger rows.
 * @return array<string,int>
 */
function bhela_bm_investment_code_map( $codes = null ) {
	if ( is_array( $codes ) && ! $codes ) {
		return array();
	}

	$args = array(
		'post_type'      => 'bhela_investment',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	if ( is_array( $codes ) ) {
		$args['meta_query'] = array(
			array(
				'key'     => '_bhela_ivm_code',
				'value'   => array_values( array_unique( $codes ) ),
				'compare' => 'IN',
			),
		);
	}

	$ids = get_posts( $args );
	if ( function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $ids, false, true );   // one meta read for the lot
	}

	$map = array();
	foreach ( $ids as $id ) {
		$code = (string) get_post_meta( $id, '_bhela_ivm_code', true );
		if ( '' !== $code ) {
			$map[ $code ] = (int) $id;
		}
	}
	return $map;
}

/**
 * Which of these ledger rows have been reversed — asked once, not once per row.
 *
 * `bhela_bm_ledger_reversal_of()` is a query, and the accrual reader called it for every
 * profit row in the window.
 *
 * @param int[] $ids Ledger row ids.
 * @return array<int,true> Keyed by the id of each row that has a contra row against it.
 */
function bhela_bm_ledger_reversed_set( $ids ) {
	$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	if ( ! $ids ) {
		return array();
	}

	$out = array();
	foreach ( get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_bhela_led_reverses', 'value' => $ids, 'compare' => 'IN' ),
		),
	) ) as $row ) {
		$target = (int) get_post_meta( $row, '_bhela_led_reverses', true );
		if ( $target ) {
			$out[ $target ] = true;
		}
	}
	return $out;
}

/**
 * Everything this engine has posted inside a window — the financing cost of the month.
 *
 * This is what the Monthly Statement deducts, and two things about it are load-bearing:
 *
 * 1. **Only rows THIS engine posted count.** A `profit` row can also come from a
 *    committed share distribution or from the settlement importer, and those are not a
 *    financing cost — the first is a split of profit already counted, the second is
 *    history being typed in. Counting them would deduct the same money twice and would
 *    have moved every month the business has already closed. Rows are matched by
 *    resolving the period key on `ref` back to a real Investment Record, which is
 *    exactly the set this engine wrote and nothing else.
 * 2. **Only POSTED periods count.** An accrual nobody has approved is a calculation,
 *    not a liability. Read from the ledger rather than recomputed, so the statement and
 *    the investor's own balance can never disagree.
 *
 * @return array{total:int,rows:array[]}
 */
function bhela_bm_profit_accrued( $from, $to ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	$out  = array( 'total' => 0, 'rows' => array() );
	if ( '' === $from || '' === $to ) {
		return $out;
	}
	// The window is asked for directly. Walking `bhela_bm_investors()` and reading each
	// one's WHOLE ledger to keep a month of it cost a query per investor plus one per
	// profit row — and the Monthly Statement calls this once, the Yearly Report twelve
	// times. The work now scales with the rows in the window, not with how many investors
	// exist or how long they have been on the register.
	$ids = get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_led_date',
		'orderby'        => array( 'meta_value' => 'ASC', 'ID' => 'ASC' ),
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_bhela_led_type', 'value' => 'profit' ),
			array(
				'key'     => '_bhela_led_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'CHAR',      // Y-m-d sorts and compares correctly as text
			),
		),
	) );
	if ( ! $ids ) {
		return $out;
	}
	if ( function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $ids, false, true );
	}

	$rows = array();
	foreach ( $ids as $id ) {
		$r = bhela_bm_ledger_row( $id );
		// A contra row is not an accrual; it is the cancellation of one.
		if ( $r && ! $r['reverses'] ) {
			$rows[] = $r;
		}
	}

	$seen = array();
	foreach ( $rows as $r ) {
		$code = strtok( (string) $r['ref'], ':' );
		if ( $code ) {
			$seen[ $code ] = true;
		}
	}
	$codes = bhela_bm_investment_code_map( array_keys( $seen ) );
	if ( ! $codes ) {
		return $out;
	}
	$reversed = bhela_bm_ledger_reversed_set( wp_list_pluck( $rows, 'id' ) );

	foreach ( $rows as $r ) {
		// A reversed accrual never happened, exactly as it does not in the balance.
		if ( isset( $reversed[ $r['id'] ] ) ) {
			continue;
		}
		$code = strtok( (string) $r['ref'], ':' );
		if ( ! $code || ! isset( $codes[ $code ] ) ) {
			continue;                        // not ours: a distribution, or an import
		}
		$out['total']  += $r['amount'];
		$out['rows'][]  = array(
			'investor'   => (int) $r['investor'],
			'name'       => get_the_title( (int) $r['investor'] ),
			'investment' => $codes[ $code ],
			'code'       => $code,
			'date'       => $r['date'],
			'amount'     => $r['amount'],
			'ref'        => $r['ref'],
		);
	}
	return $out;
}
