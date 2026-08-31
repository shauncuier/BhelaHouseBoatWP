<?php
/**
 * The investor ledger: every taka owed, paid or adjusted, as an append-only list.
 *
 * **The balance is replayed from the rows, never stored.** Same discipline as
 * bhela_bm_booking_stay() and the day type (CLAUDE.md §13.8): a cached figure that
 * drifts from the rows underneath it is worse than no figure, because it looks
 * authoritative. Here it would be worse still — a stored balance that disagrees with
 * its own transactions cannot be reconciled by anyone, and there is no way to tell
 * afterwards which of the two was right.
 *
 * **Nothing is ever edited or deleted.** A mistake is corrected with a contra row
 * that names the row it reverses, so the trail shows the error AND the fix. That is
 * what §31 of the brief asks for, and it is the only version of "audit" that survives
 * someone disagreeing with the numbers a year later.
 *
 * Four row types, and the sign convention is the whole model:
 *
 *   profit      + owed to the investor
 *   advance     − paid before the profit was declared
 *   payment     − paid against declared profit
 *   adjustment  ± a correction, always with a reason
 *
 * Outstanding is simply the sum. An advance needs no special arithmetic (§13 of the
 * brief) — it is a payment that happened early, and replaying the rows in date order
 * produces the right answer on its own.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Row types and how each moves the balance. */
function bhela_bm_ledger_types() {
	return array(
		'profit'     => array( 'label' => __( 'Profit', 'bhela-booking' ),     'sign' => 1 ),
		'advance'    => array( 'label' => __( 'Advance', 'bhela-booking' ),    'sign' => -1 ),
		'payment'    => array( 'label' => __( 'Payment', 'bhela-booking' ),    'sign' => -1 ),
		'adjustment' => array( 'label' => __( 'Adjustment', 'bhela-booking' ), 'sign' => 1 ),
	);
}

/**
 * Append one row. The ONLY writer.
 *
 * @param array $args investor, type, amount, date, ref, run, note, method, reverses.
 * @return int|WP_Error Row id.
 */
function bhela_bm_ledger_add( $args ) {
	$types    = bhela_bm_ledger_types();
	$investor = (int) ( $args['investor'] ?? 0 );
	$type     = (string) ( $args['type'] ?? '' );
	$amount   = (int) ( $args['amount'] ?? 0 );

	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'bad_type', __( 'লেনদেনের ধরন সঠিক নয়।', 'bhela-booking' ) );
	}
	// An adjustment may be negative; nothing else may. A negative payment is a
	// refund typed into the wrong box, and it would quietly increase what is owed.
	if ( 'adjustment' !== $type && $amount <= 0 ) {
		return new WP_Error( 'bad_amount', __( 'পরিমাণ শূন্যের বেশি হতে হবে।', 'bhela-booking' ) );
	}
	if ( 0 === $amount ) {
		return new WP_Error( 'bad_amount', __( 'পরিমাণ শূন্য হতে পারে না।', 'bhela-booking' ) );
	}

	$date = bhela_bm_report_date( $args['date'] ?? '' );
	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}

	$row = wp_insert_post( array(
		'post_type'   => 'bhela_inv_ledger',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s %s %d', get_the_title( $investor ), $type, $amount ),
	), true );
	if ( is_wp_error( $row ) ) {
		return $row;
	}

	$meta = array(
		'investor' => $investor,
		'type'     => $type,
		'amount'   => $amount,
		'date'     => $date,
		'ref'      => sanitize_text_field( $args['ref'] ?? '' ),
		'run'      => (int) ( $args['run'] ?? 0 ),
		'method'   => sanitize_text_field( $args['method'] ?? '' ),
		'note'     => sanitize_textarea_field( $args['note'] ?? '' ),
		'reverses' => (int) ( $args['reverses'] ?? 0 ),
		'by'       => get_current_user_id(),
		'at'       => current_time( 'mysql' ),
	);
	foreach ( $meta as $k => $v ) {
		bhela_bm_dist_meta_write( $row, '_bhela_led_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => $type,
		'object_type' => 'ledger',
		'object_id'   => $row,
		'object_ref'  => (string) $investor,
		'field'       => 'amount',
		'new_value'   => (string) $amount,
		'reason'      => $meta['note'],
	) );

	return $row;
}

/**
 * Reverse a row with a contra entry.
 *
 * The original stays exactly as it was. Two rows that cancel is the record of what
 * happened; one edited row is a record of nothing.
 */
function bhela_bm_ledger_reverse( $row_id, $reason ) {
	$r = bhela_bm_ledger_row( $row_id );
	if ( ! $r ) {
		return new WP_Error( 'no_row', __( 'এই এন্ট্রি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( '' === trim( (string) $reason ) ) {
		return new WP_Error( 'no_reason', __( 'কারণ লিখুন — বিপরীত এন্ট্রি কারণ ছাড়া রাখা যায় না।', 'bhela-booking' ) );
	}
	if ( bhela_bm_ledger_reversal_of( $row_id ) ) {
		return new WP_Error( 'already', __( 'এই এন্ট্রি আগেই বাতিল করা হয়েছে।', 'bhela-booking' ) );
	}
	return bhela_bm_ledger_add( array(
		'investor' => $r['investor'],
		'type'     => 'adjustment',
		// Flip the effect: whatever this row did to the balance, undo exactly that.
		'amount'   => -1 * $r['signed'],
		'date'     => current_time( 'Y-m-d' ),
		'ref'      => $r['ref'],
		'reverses' => $r['id'],
		'note'     => sprintf(
			/* translators: 1: original row id, 2: reason */
			__( '#%1$d বাতিল — %2$s', 'bhela-booking' ),
			$r['id'],
			sanitize_textarea_field( $reason )
		),
	) );
}

/** The reversal row for an entry, if one exists. */
function bhela_bm_ledger_reversal_of( $row_id ) {
	$hit = get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_led_reverses',
		'meta_value'     => (int) $row_id,
	) );
	return $hit ? (int) $hit[0] : 0;
}

/** One row, with its signed effect on the balance already worked out. */
function bhela_bm_ledger_row( $id ) {
	if ( 'bhela_inv_ledger' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_led_' . $k, true );
	};
	$type   = (string) $m( 'type' );
	$types  = bhela_bm_ledger_types();
	$amount = (int) $m( 'amount' );
	$sign   = isset( $types[ $type ] ) ? (int) $types[ $type ]['sign'] : 1;

	return array(
		'id'       => (int) $id,
		'investor' => (int) $m( 'investor' ),
		'type'     => $type,
		'label'    => $types[ $type ]['label'] ?? $type,
		'amount'   => $amount,
		// An adjustment carries its own sign; everything else takes the type's.
		'signed'   => 'adjustment' === $type ? $amount : $sign * abs( $amount ),
		'date'     => (string) $m( 'date' ),
		'ref'      => (string) $m( 'ref' ),
		'run'      => (int) $m( 'run' ),
		'method'   => (string) $m( 'method' ),
		'note'     => (string) $m( 'note' ),
		'reverses' => (int) $m( 'reverses' ),
		'by'       => (int) $m( 'by' ),
		'at'       => (string) $m( 'at' ),
	);
}

/**
 * One investor's rows, oldest first, with a running balance.
 *
 * @return array{rows:array,opening:int,closing:int}
 */
function bhela_bm_investor_ledger( $investor_id, $from = '', $to = '' ) {
	$ids = get_posts( array(
		'post_type'      => 'bhela_inv_ledger',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_led_date',
		'orderby'        => array( 'meta_value' => 'ASC', 'ID' => 'ASC' ),
		'meta_query'     => array(
			array( 'key' => '_bhela_led_investor', 'value' => (int) $investor_id ),
		),
	) );

	$from    = bhela_bm_report_date( $from );
	$to      = bhela_bm_report_date( $to );
	$rows    = array();
	$balance = 0;
	$opening = 0;

	foreach ( $ids as $id ) {
		$r = bhela_bm_ledger_row( $id );
		if ( ! $r ) {
			continue;
		}
		$balance += $r['signed'];

		// Rows before the window still move the balance — they just are not listed.
		// A statement that starts at zero mid-year is not a statement.
		if ( $from && $r['date'] < $from ) {
			$opening = $balance;
			continue;
		}
		if ( $to && $r['date'] > $to ) {
			continue;
		}
		$r['balance'] = $balance;
		$rows[]       = $r;
	}

	return array( 'rows' => $rows, 'opening' => $opening, 'closing' => $balance );
}

/**
 * Where an investor stands. Everything replayed, nothing cached.
 *
 * @return array{profit:int,advance:int,payment:int,adjustment:int,received:int,outstanding:int,last_payment:string}
 */
function bhela_bm_investor_position( $investor_id ) {
	$out = array(
		'profit' => 0, 'advance' => 0, 'payment' => 0, 'adjustment' => 0,
		'received' => 0, 'outstanding' => 0, 'last_payment' => '', 'rows' => 0,
	);
	$led = bhela_bm_investor_ledger( $investor_id );

	// Which payment/advance rows have been reversed. A reversal is an adjustment, so
	// it corrects the BALANCE on its own — but `received` counts payment rows, and
	// without this it would keep counting money that was handed back. ROI is computed
	// from `received`, so the investor's return would stay inflated by a payment that
	// no longer exists.
	$reversed = array();
	foreach ( $led['rows'] as $r ) {
		if ( $r['reverses'] ) {
			$reversed[ $r['reverses'] ] = true;
		}
	}

	foreach ( $led['rows'] as $r ) {
		$out['rows']++;
		$undone = isset( $reversed[ $r['id'] ] );
		switch ( $r['type'] ) {
			case 'profit':
				$out['profit'] += $r['amount'];
				break;
			case 'advance':
				if ( ! $undone ) {
					$out['advance'] += $r['amount'];
				}
				break;
			case 'payment':
				if ( ! $undone ) {
					$out['payment'] += $r['amount'];
				}
				break;
			case 'adjustment':
				$out['adjustment'] += $r['signed'];
				break;
		}
		if ( ! $undone && in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
			if ( '' === $out['last_payment'] || $r['date'] > $out['last_payment'] ) {
				$out['last_payment'] = $r['date'];
			}
		}
	}
	// An advance IS money received; the distinction is when, not whether.
	$out['received']    = $out['advance'] + $out['payment'];
	$out['outstanding'] = $led['closing'];
	return $out;
}

/**
 * Return on investment.
 *
 * Measured on money actually RECEIVED, not profit declared. Profit an investor has
 * not been paid is a claim, not a return, and reporting it as ROI would overstate
 * performance for as long as payment is outstanding.
 *
 * @return array{investment:int,received:int,declared:int,roi:float,roi_declared:float}
 */
function bhela_bm_investor_roi( $investor_id ) {
	$pos   = bhela_bm_investor_position( $investor_id );
	$given = bhela_bm_investor_amount( $investor_id );
	return array(
		'investment'   => $given,
		'received'     => $pos['received'],
		'declared'     => $pos['profit'],
		'outstanding'  => $pos['outstanding'],
		'roi'          => $given > 0 ? round( $pos['received'] / $given * 100, 2 ) : 0.0,
		'roi_declared' => $given > 0 ? round( $pos['profit'] / $given * 100, 2 ) : 0.0,
	);
}
