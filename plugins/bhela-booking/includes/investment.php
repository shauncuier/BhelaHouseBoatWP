<?php
/**
 * The Investment Record — the entity the whole document chain hangs off.
 *
 * Until now an investor's money was two undated scalars plus, since the capital module,
 * a set of dated contributions. Neither is an *investment*: nothing recorded the term,
 * the rate, the profit method, the payment frequency or the agreement behind it, so no
 * certificate could state what was actually agreed and no engine could work out what was
 * owed. This is that record.
 *
 * **BHELA moved from an equity model to a fixed-return one, and that was the owner's
 * decision.** The share register, the valuation and the distribution engine stay exactly
 * where they are and stay readable — financial records are not deleted here (§3.7) — but
 * with `inv_model` set to `fixed` they no longer decide what an investor is owed. This
 * record does. The consequence worth saying out loud, because it changes the books
 * rather than a screen: a fixed return is a COST, owed whether or not the month traded
 * well, so it is deducted in bhela_bm_statement_data() alongside payroll and commission.
 *
 * Four rules, each with a precedent:
 *
 * 1. **It locks the moment it leaves draft.** A draft is somebody working; anything else
 *    is money taken against agreed terms. Same shape as a cost sheet's status lock, and
 *    `_bhela_ivm_status` stays writable so it can be legitimately reopened (§13.40).
 * 2. **The principal is never typed.** It is the sum of the `bhela_capital` rows that
 *    point at this investment, so the receipt, the certificate and the ledger cannot
 *    disagree about how much arrived. Two editable places holding one number is §13.44.
 * 3. **Nothing is defaulted.** No rate, no term, no method. Activation refuses a blank
 *    rather than assuming twelve percent — a guessed rate would end up on a document
 *    somebody files with a bank.
 * 4. **The duration is derived** from start and maturity, never stored beside them
 *    (§13.8). Change the maturity and the duration follows instead of going stale.
 *
 * Loaded on EVERY request: the portal and the certificate both read investments on a
 * front-end URL.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The post type
 * ========================================================= */

function bhela_bm_register_investment_cpt() {
	register_post_type( 'bhela_investment', array(
		'labels' => array(
			'name'          => __( 'Investments', 'bhela-booking' ),
			'singular_name' => __( 'Investment', 'bhela-booking' ),
		),
		// Same posture as the investor record it belongs to. It names a person and an
		// amount of money and must never be addressable from the front end.
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_rest'        => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'rewrite'             => false,
	) );
}
add_action( 'init', 'bhela_bm_register_investment_cpt' );

/* =========================================================
 * The vocabulary
 * ========================================================= */

/**
 * Which model decides what an investor is owed.
 *
 * `shares`  — the original equity model: monthly distribution of business profit, split
 *             by shareholding. Still the code that ran every committed run on record.
 * `fixed`   — an Investment Record with its own agreed terms.
 *
 * Both write `profit` ledger rows, so **they must never both be live**: an investor who
 * held shares and an investment would be paid twice, once by each engine, and both rows
 * would look individually correct. bhela_bm_dist_commit() refuses while this is `fixed`.
 */
function bhela_bm_investor_model() {
	$s = bhela_bm_get_settings();
	return 'fixed' === ( $s['inv_model'] ?? '' ) ? 'fixed' : 'shares';
}

/**
 * Investment types the owner has defined.
 *
 * A slug is FROZEN once used, exactly as an income head is: every saved investment hangs
 * its method and its certificate wording off it. Editing the label is fine; reusing a
 * slug for a different product is not.
 */
function bhela_bm_investment_types() {
	$saved = get_option( 'bhela_bm_investment_types', array() );
	if ( ! is_array( $saved ) || ! $saved ) {
		// Shipped so the screen is usable on day one. Deliberately plain names: the
		// legal characterisation of each product is §25's question, not this file's.
		return array(
			'term'   => array( 'label' => __( 'নির্দিষ্ট মেয়াদি বিনিয়োগ', 'bhela-booking' ), 'method' => 'fixed_annual' ),
			'shared' => array( 'label' => __( 'লাভ-বণ্টন ভিত্তিক', 'bhela-booking' ), 'method' => 'profit_share' ),
		);
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || ! is_array( $row ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'label'  => sanitize_text_field( $row['label'] ?? $slug ),
			'method' => sanitize_key( $row['method'] ?? '' ),
		);
	}
	return $out;
}

/**
 * How often profit falls due, and how many months that is.
 *
 * `maturity` is one period covering the whole term — the engine reads `months` of 0 as
 * "the whole thing at the end" rather than as an error.
 */
function bhela_bm_investment_freqs() {
	return array(
		'monthly'   => array( 'label' => __( 'মাসিক', 'bhela-booking' ), 'months' => 1 ),
		'quarterly' => array( 'label' => __( 'ত্রৈমাসিক', 'bhela-booking' ), 'months' => 3 ),
		'half'      => array( 'label' => __( 'ষাণ্মাসিক', 'bhela-booking' ), 'months' => 6 ),
		'annual'    => array( 'label' => __( 'বার্ষিক', 'bhela-booking' ), 'months' => 12 ),
		'maturity'  => array( 'label' => __( 'মেয়াদ শেষে', 'bhela-booking' ), 'months' => 0 ),
	);
}

/** draft → active → matured → closed, plus the two ways out. */
function bhela_bm_investment_states() {
	return array(
		'draft'     => array( 'label' => __( 'খসড়া', 'bhela-booking' ), 'tone' => 'neutral' ),
		'active'    => array( 'label' => __( 'সক্রিয়', 'bhela-booking' ), 'tone' => 'good' ),
		'matured'   => array( 'label' => __( 'মেয়াদ পূর্ণ', 'bhela-booking' ), 'tone' => 'progress' ),
		'closed'    => array( 'label' => __( 'সমাপ্ত', 'bhela-booking' ), 'tone' => 'neutral' ),
		'cancelled' => array( 'label' => __( 'বাতিল', 'bhela-booking' ), 'tone' => 'danger' ),
	);
}

/**
 * Which state may follow which, and who may do it.
 *
 * `draft → active` is the one that matters: it is the point where terms stop being
 * editable and money starts being owed, so it carries every guard in
 * bhela_bm_investment_activate().
 */
function bhela_bm_investment_transitions() {
	return array(
		'draft'   => array( 'active' => 'bhela_investor_approve', 'cancelled' => 'edit_bhela_investors' ),
		'active'  => array( 'matured' => 'bhela_investor_approve', 'draft' => 'bhela_investor_approve' ),
		'matured' => array( 'closed' => 'bhela_investor_approve', 'active' => 'bhela_investor_approve' ),
		'closed'  => array(),
		'cancelled' => array( 'draft' => 'bhela_investor_approve' ),
	);
}

/* =========================================================
 * The lock
 * ========================================================= */

/**
 * Anything that is not a draft. Read directly — this is consulted on every request.
 *
 * An ABSENT status counts as a draft, and that is not a convenience. A record a
 * moment old has no status meta yet, so a predicate written as `'draft' !== $status`
 * locks it against its own first write — every field then silently failed to save and
 * the record came out blank. The write order below puts the status in first as well,
 * so this is belt and braces rather than the only thing holding it up.
 */
function bhela_bm_investment_locked( $id ) {
	if ( 'bhela_investment' !== get_post_type( $id ) ) {
		return false;
	}
	$status = (string) get_post_meta( $id, '_bhela_ivm_status', true );
	return '' !== $status && 'draft' !== $status;
}

/* =========================================================
 * Numbering
 * ========================================================= */

function bhela_bm_investment_code_exists( $number ) {
	return bhela_bm_series_taken( 'bhela_investment', '_bhela_ivm_code', $number );
}

/** BHELA-IN-2026-0001. */
function bhela_bm_investment_code() {
	return bhela_bm_series_number( 'IN', 'bhela_bm_investment_code_exists' );
}

/* =========================================================
 * Writing
 * ========================================================= */

/** The editable terms, sanitised. Shared by add and save so they cannot drift apart. */
function bhela_bm_investment_clean( $args ) {
	$rate = isset( $args['rate'] ) ? (string) $args['rate'] : '';
	return array(
		'investor'  => (int) ( $args['investor'] ?? 0 ),
		'date'      => bhela_bm_report_date( $args['date'] ?? '' ),
		'start'     => bhela_bm_report_date( $args['start'] ?? '' ),
		'maturity'  => bhela_bm_report_date( $args['maturity'] ?? '' ),
		'type'      => sanitize_key( $args['type'] ?? '' ),
		'method'    => sanitize_key( $args['method'] ?? '' ),
		// Basis points, so 12% is 1200 and nothing is ever a float. A rate typed as
		// "12.5" survives exactly; a rate held as a float would not.
		'rate_bp'   => max( 0, (int) round( (float) preg_replace( '/[^0-9.\-]/', '', $rate ) * 100 ) ),
		'frequency' => sanitize_key( $args['frequency'] ?? '' ),
		'agreement' => (int) ( $args['agreement'] ?? 0 ),
		'note'      => sanitize_textarea_field( $args['note'] ?? '' ),
	);
}

/**
 * Open a new investment. Always a draft — nothing is owed by creating one.
 *
 * @return int|WP_Error
 */
function bhela_bm_investment_add( $args ) {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		return new WP_Error( 'denied', __( 'বিনিয়োগ রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	$clean = bhela_bm_investment_clean( $args );
	if ( ! $clean['investor'] || 'bhela_investor' !== get_post_type( $clean['investor'] ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( 'exited' === bhela_bm_investor_status( $clean['investor'] ) ) {
		return new WP_Error( 'exited', __( 'এই বিনিয়োগকারী প্রত্যাহৃত।', 'bhela-booking' ) );
	}

	$code = bhela_bm_investment_code();
	$id   = wp_insert_post( array(
		'post_type'   => 'bhela_investment',
		'post_status' => 'publish',
		'post_title'  => $code . ' — ' . get_the_title( $clean['investor'] ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	// Status first: until it says draft, bhela_bm_investment_locked() has nothing to
	// read, and a lock that cannot tell a new record from an active one refuses the
	// record's own creation.
	update_post_meta( $id, '_bhela_ivm_status', 'draft' );
	update_post_meta( $id, '_bhela_ivm_code', $code );
	update_post_meta( $id, '_bhela_ivm_by', get_current_user_id() );
	update_post_meta( $id, '_bhela_ivm_at', current_time( 'mysql' ) );
	foreach ( $clean as $k => $v ) {
		update_post_meta( $id, '_bhela_ivm_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'investment_add',
		'object_type' => 'investment',
		'object_id'   => (int) $id,
		'object_ref'  => $code,
		'field'       => 'status',
		'new_value'   => 'draft',
		'reason'      => $clean['note'],
	) );

	return (int) $id;
}

/** Edit the terms. Draft only — see the file header. */
function bhela_bm_investment_save( $id, $args ) {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		return new WP_Error( 'denied', __( 'বিনিয়োগ রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( 'bhela_investment' !== get_post_type( $id ) ) {
		return new WP_Error( 'not_found', __( 'বিনিয়োগ পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( bhela_bm_investment_locked( $id ) ) {
		return new WP_Error(
			'locked',
			__( 'সক্রিয় বিনিয়োগের শর্ত বদলানো যায় না। আগে খসড়ায় ফেরত আনুন — তার রেকর্ড থাকবে।', 'bhela-booking' )
		);
	}

	$clean = bhela_bm_investment_clean( $args );
	$old   = bhela_bm_investment( $id );
	foreach ( $clean as $k => $v ) {
		if ( 'investor' === $k && ! $v ) {
			continue;                      // never blanks the owner by omission
		}
		update_post_meta( $id, '_bhela_ivm_' . $k, $v );
	}

	foreach ( array( 'rate_bp', 'maturity', 'method', 'frequency' ) as $watch ) {
		if ( (string) ( $old[ $watch ] ?? '' ) !== (string) $clean[ $watch ] ) {
			bhela_bm_audit( array(
				'channel'     => 'investor',
				'action'      => 'investment_edit',
				'object_type' => 'investment',
				'object_id'   => (int) $id,
				'object_ref'  => $old['code'],
				'field'       => $watch,
				'old_value'   => (string) ( $old[ $watch ] ?? '' ),
				'new_value'   => (string) $clean[ $watch ],
			) );
		}
	}
	return true;
}

/**
 * Everything standing between a draft and money being owed.
 *
 * Returned as a list rather than a bool so the screen can say which one is missing.
 * Nothing here is defaulted: a blank rate refuses instead of assuming a number.
 *
 * @return string[] Empty when it may be activated.
 */
function bhela_bm_investment_blockers( $id ) {
	$r   = bhela_bm_investment( $id );
	$out = array();
	if ( ! $r ) {
		return array( __( 'রেকর্ড পাওয়া যায়নি।', 'bhela-booking' ) );
	}

	if ( $r['principal'] <= 0 ) {
		// The principal is the sum of the capital rows pointing here, so this reads
		// "no receipt has been recorded", which is exactly when it should refuse.
		$out[] = __( 'এই বিনিয়োগের বিপরীতে কোনো মূলধন রেকর্ড নেই — আগে প্রাপ্তি (capital) এন্ট্রি করুন।', 'bhela-booking' );
	}
	if ( '' === $r['start'] || '' === $r['maturity'] ) {
		$out[] = __( 'শুরু ও মেয়াদ শেষের তারিখ দুটোই লাগবে।', 'bhela-booking' );
	} elseif ( $r['maturity'] <= $r['start'] ) {
		$out[] = __( 'মেয়াদ শেষের তারিখ শুরুর পরে হতে হবে।', 'bhela-booking' );
	}
	if ( '' === $r['method'] ) {
		$out[] = __( 'লাভ হিসাবের পদ্ধতি নির্বাচন করুন।', 'bhela-booking' );
	}
	if ( '' === $r['frequency'] ) {
		$out[] = __( 'লাভ পরিশোধের সময়সূচি নির্বাচন করুন।', 'bhela-booking' );
	}
	// Every method except the day-based one measures in whole months, so a term shorter
	// than one month accrues nothing at all. Refusing is better than activating an
	// investment that will quietly earn zero for its whole life.
	if ( 'day_based' !== $r['method'] && $r['start'] && $r['maturity']
		&& $r['maturity'] > $r['start'] && $r['months'] < 1 ) {
		$out[] = __( 'এক মাসের কম মেয়াদে মাসভিত্তিক পদ্ধতিতে কোনো লাভ হিসাব হয় না — দিনভিত্তিক পদ্ধতি বেছে নিন।', 'bhela-booking' );
	}
	// Every method needs a rate, including profit_share — there the rate IS the
	// investor's agreed share of distributable profit, which is the brief's own
	// `Distributable Profit × Investor Share %`. Taking that share from the share
	// register instead would quietly tie a fixed-return agreement back to an equity
	// model the business has moved away from. A missing rate is refused, never filled in.
	if ( $r['rate_bp'] <= 0 ) {
		$out[] = __( 'হার (rate) লিখুন — অনুমান করে বসানো হবে না।', 'bhela-booking' );
	}
	if ( ! $r['agreement'] || 'bhela_agreement' !== get_post_type( $r['agreement'] ) ) {
		$out[] = __( 'স্বাক্ষরিত চুক্তির রেফারেন্স যুক্ত করুন — সনদে এটিই উদ্ধৃত হবে।', 'bhela-booking' );
	}
	return $out;
}

/**
 * Move between states. The only writer of `_bhela_ivm_status`.
 *
 * @return true|WP_Error
 */
function bhela_bm_investment_transition( $id, $to, $reason = '' ) {
	$r = bhela_bm_investment( $id );
	if ( ! $r ) {
		return new WP_Error( 'not_found', __( 'বিনিয়োগ পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	$to    = sanitize_key( $to );
	$moves = bhela_bm_investment_transitions();
	$allow = $moves[ $r['status'] ] ?? array();
	if ( ! isset( $allow[ $to ] ) ) {
		return new WP_Error( 'bad_state', __( 'এই অবস্থা থেকে ওখানে যাওয়া যায় না।', 'bhela-booking' ) );
	}
	if ( ! current_user_can( $allow[ $to ] ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( 'active' === $to ) {
		$blockers = bhela_bm_investment_blockers( $id );
		if ( $blockers ) {
			return new WP_Error( 'incomplete', implode( ' ', $blockers ) );
		}
	}
	// Reopening to draft unlocks the terms, so it needs a reason on the record — the
	// same bargain the cost sheet's unlock makes.
	if ( 'draft' === $to && '' === trim( (string) $reason ) ) {
		return new WP_Error( 'no_reason', __( 'খসড়ায় ফেরত আনার কারণ লিখুন।', 'bhela-booking' ) );
	}

	// Status first — it is the open key. The stamp that follows lands on a record the
	// lock now covers, so it goes through the lock's own window rather than round it.
	update_post_meta( $id, '_bhela_ivm_status', $to );
	bhela_bm_val_meta_write( $id, '_bhela_ivm_' . $to . '_by', get_current_user_id() );
	bhela_bm_val_meta_write( $id, '_bhela_ivm_' . $to . '_at', current_time( 'mysql' ) );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'investment_' . $to,
		'object_type' => 'investment',
		'object_id'   => (int) $id,
		'object_ref'  => $r['code'],
		'field'       => 'status',
		'old_value'   => $r['status'],
		'new_value'   => $to,
		'reason'      => sanitize_textarea_field( $reason ),
	) );
	return true;
}

/* =========================================================
 * Reading
 * ========================================================= */

/**
 * A date `$n` months on, clamped to the target month's length.
 *
 * PHP's own `+1 month` OVERFLOWS: `strtotime( '2024-01-31 +1 month' )` is 2 March,
 * because 31 February is normalised forward. Stepping a schedule with it made the first
 * monthly period of an investment starting on the 31st run 31 Jan → 1 Mar — February
 * swallowed whole — and every later period drift to the 2nd of the month permanently.
 * Anchoring to the start date and clamping the day is what keeps 31 Jan → 29 Feb →
 * 31 Mar, with no drift and no month skipped.
 */
function bhela_bm_month_step( $date, $n ) {
	$d     = new DateTimeImmutable( $date );
	$day   = (int) $d->format( 'j' );
	$first = $d->modify( 'first day of this month' )->modify( sprintf( '%+d month', (int) $n ) );
	return $first->setDate(
		(int) $first->format( 'Y' ),
		(int) $first->format( 'n' ),
		min( $day, (int) $first->format( 't' ) )
	)->format( 'Y-m-d' );
}

/**
 * How many WHOLE months a term runs. Derived, never stored (§13.8).
 *
 * Exact rather than approximate: the largest `n` whose n-month anniversary of the start
 * still falls inside the term. A term written 01 Jul 2026 → 30 Jun 2027 is twelve
 * months because 01 Jul 2027 is exactly the day after it ends.
 *
 * The first version rounded up whenever the leftover day count reached 27, which read
 * the twelve-month case correctly and then also priced a 28-DAY term as a full month —
 * about 10% more profit than was earned. A term shorter than one payment period now
 * returns 0 and `bhela_bm_investment_blockers()` refuses to activate it on a
 * month-based method, which is the honest answer: use the day-based method for that.
 */
function bhela_bm_investment_months( $start, $maturity ) {
	$start    = bhela_bm_report_date( $start );
	$maturity = bhela_bm_report_date( $maturity );
	if ( '' === $start || '' === $maturity || $maturity <= $start ) {
		return 0;
	}
	// The day AFTER the term ends, so a term closing the day before its anniversary
	// counts as whole.
	$ends = gmdate( 'Y-m-d', strtotime( $maturity . ' +1 day' ) );
	$n    = 0;
	while ( $n < 1200 && bhela_bm_month_step( $start, $n + 1 ) <= $ends ) {
		$n++;
	}
	return $n;
}

/**
 * What actually arrived against this investment.
 *
 * The sum of the capital rows pointing here, voided rows excluded. This is the ONLY
 * definition of the principal: a typed one could disagree with the receipts, and the
 * certificate quotes it.
 */
function bhela_bm_investment_principal( $id ) {
	$total = 0;
	foreach ( bhela_bm_capital_rows_for_investment( $id ) as $row ) {
		$total += $row['amount'];
	}
	return $total;
}

/** One investment, or null. */
function bhela_bm_investment( $id ) {
	if ( 'bhela_investment' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_ivm_' . $k, true );
	};
	$investor  = (int) $m( 'investor' );
	$start     = (string) $m( 'start' );
	$maturity  = (string) $m( 'maturity' );
	$status    = (string) $m( 'status' );
	$states    = bhela_bm_investment_states();
	$agreement = (int) $m( 'agreement' );

	return array(
		'id'            => (int) $id,
		'code'          => (string) $m( 'code' ),
		'investor'      => $investor,
		'name'          => $investor ? get_the_title( $investor ) : '',
		'date'          => (string) $m( 'date' ),
		'start'         => $start,
		'maturity'      => $maturity,
		'months'        => bhela_bm_investment_months( $start, $maturity ),
		'type'          => (string) $m( 'type' ),
		'method'        => (string) $m( 'method' ),
		'rate_bp'       => (int) $m( 'rate_bp' ),
		'rate'          => (int) $m( 'rate_bp' ) / 100,
		'frequency'     => (string) $m( 'frequency' ),
		'agreement'     => $agreement,
		'agreement_ref' => $agreement ? (string) get_post_meta( $agreement, '_bhela_agr_ref', true ) : '',
		'principal'     => bhela_bm_investment_principal( $id ),
		'note'          => (string) $m( 'note' ),
		'status'        => isset( $states[ $status ] ) ? $status : 'draft',
		'status_label'  => $states[ $status ]['label'] ?? $status,
		'locked'        => bhela_bm_investment_locked( $id ),
		'by'            => (int) $m( 'by' ),
		'at'            => (string) $m( 'at' ),
	);
}

/** How many investments one listing reads. Filterable. */
function bhela_bm_investment_limit() {
	return (int) apply_filters( 'bhela_bm_investment_limit', 500 );
}

/**
 * Investments, newest first.
 *
 * @param int    $investor Investor post id, or 0 for everybody.
 * @param string $status   One state, 'live' for anything still accruing, or '' for all.
 * @return array[]
 */
function bhela_bm_investments( $investor = 0, $status = '' ) {
	$query = array(
		'post_type'      => 'bhela_investment',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_investment_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	);
	if ( $investor ) {
		$query['meta_key']   = '_bhela_ivm_investor';
		$query['meta_value'] = (int) $investor;
	}

	$status = sanitize_key( $status );
	$out    = array();
	foreach ( get_posts( $query ) as $id ) {
		$r = bhela_bm_investment( $id );
		if ( ! $r ) {
			continue;
		}
		if ( 'live' === $status && ! in_array( $r['status'], array( 'active', 'matured' ), true ) ) {
			continue;
		}
		if ( $status && 'live' !== $status && $r['status'] !== $status ) {
			continue;
		}
		$out[] = $r;
	}
	return $out;
}

/** The investment behind a code, or null. Used by the verification page and the CSV. */
function bhela_bm_investment_by_code( $code ) {
	$hit = get_posts( array(
		'post_type'      => 'bhela_investment',
		'post_status'    => 'publish',
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_ivm_code',
		'meta_value'     => (string) $code,
	) );
	// Two records on one code refuse rather than resolve — the same rule the mobile
	// lookup follows, and for the same reason: picking one decides whose money it is.
	return 1 === count( $hit ) ? bhela_bm_investment( (int) $hit[0] ) : null;
}
