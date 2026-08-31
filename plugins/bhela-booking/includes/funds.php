<?php
/**
 * The reserve and management funds.
 *
 * Both are the same shape — a pot that receives an allocation from every distribution
 * and pays out against it — so they are one module with two funds rather than two
 * near-identical files that would drift apart the first time one gained a feature.
 *
 *     opening + allocations − utilisation = closing
 *
 * Until now the reserve and management figures existed only as meta on a distribution
 * run: the money was set aside on paper and then untracked. These are real ledger
 * rows, so "what is left in the reserve" has an answer that adds up.
 *
 * **Allocations are written by the distribution and cannot be entered by hand.** The
 * reserve exists because a percentage was taken off a month's profit; typing one in
 * would create money the business never earned. Utilisation is the opposite — it is
 * somebody spending, so it is entered, and reversed with a contra row when wrong.
 *
 * The balance is replayed from the rows every read, never stored — the same rule as
 * the investor ledger, and for the same reason: a cached figure that drifts from its
 * own transactions cannot be reconciled by anyone.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The two funds. A registry, so a third is a data change. */
function bhela_bm_funds() {
	return array(
		'reserve'    => array(
			'label' => __( 'Reserve Fund', 'bhela-booking' ),
			'blurb' => __( 'Off-season running costs, renovation, maintenance, upgrades and emergency repair. Fed by the reserve percentage taken off each month before profit is shared.', 'bhela-booking' ),
			'heads' => array(
				'offseason'  => __( 'Off-season operating cost', 'bhela-booking' ),
				'renovation' => __( 'Renovation', 'bhela-booking' ),
				'maintenance' => __( 'Maintenance', 'bhela-booking' ),
				'upgrade'    => __( 'Upgrade', 'bhela-booking' ),
				'emergency'  => __( 'Emergency repair', 'bhela-booking' ),
				'asset'      => __( 'Asset replacement', 'bhela-booking' ),
				'compliance' => __( 'Government / compliance', 'bhela-booking' ),
				'other'      => __( 'Other', 'bhela-booking' ),
			),
		),
		'management' => array(
			'label' => __( 'Management Fund', 'bhela-booking' ),
			'blurb' => __( 'The management share of distributable profit, and what it is spent on. Investors see the total allocated; the breakdown stays internal.', 'bhela-booking' ),
			'heads' => array(
				'salary'     => __( 'Management salary', 'bhela-booking' ),
				'admin'      => __( 'Administration', 'bhela-booking' ),
				'office'     => __( 'Office expense', 'bhela-booking' ),
				'marketing'  => __( 'Marketing management', 'bhela-booking' ),
				'accounting' => __( 'Accounting', 'bhela-booking' ),
				'technology' => __( 'Technology', 'bhela-booking' ),
				'comms'      => __( 'Communication', 'bhela-booking' ),
				'travel'     => __( 'Travel', 'bhela-booking' ),
				'fees'       => __( 'Professional fees', 'bhela-booking' ),
				'other'      => __( 'Other', 'bhela-booking' ),
			),
		),
	);
}

function bhela_bm_fund_exists( $fund ) {
	return isset( bhela_bm_funds()[ $fund ] );
}

/** Register the fund ledger. Locked by distribution-core.php alongside the rest. */
function bhela_bm_register_fund_cpt() {
	register_post_type( 'bhela_fund', array(
		'labels'              => array( 'name' => __( 'Fund Ledger', 'bhela-booking' ) ),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'bhela_bm_register_fund_cpt' );

/**
 * Add a row.
 *
 * @param array $args fund, type (allocation|utilisation|adjustment), amount, date,
 *                    head, ref, run, note, reverses.
 * @return int|WP_Error
 */
function bhela_bm_fund_add( $args ) {
	$fund   = sanitize_key( $args['fund'] ?? '' );
	$type   = sanitize_key( $args['type'] ?? '' );
	$amount = (int) ( $args['amount'] ?? 0 );

	if ( ! bhela_bm_fund_exists( $fund ) ) {
		return new WP_Error( 'no_fund', __( 'তহবিল নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( ! in_array( $type, array( 'allocation', 'utilisation', 'adjustment' ), true ) ) {
		return new WP_Error( 'bad_type', __( 'লেনদেনের ধরন সঠিক নয়।', 'bhela-booking' ) );
	}
	// An allocation comes from a distribution run, never from a person. Typing one in
	// would create reserve money that no month actually set aside.
	if ( 'allocation' === $type && empty( $args['run'] ) ) {
		return new WP_Error( 'no_run', __( 'বরাদ্দ শুধু বণ্টন থেকেই আসে।', 'bhela-booking' ) );
	}
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
		'post_type'   => 'bhela_fund',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s %s %d', $fund, $type, $amount ),
	), true );
	if ( is_wp_error( $row ) ) {
		return $row;
	}

	$meta = array(
		'fund'     => $fund,
		'type'     => $type,
		'amount'   => $amount,
		'date'     => $date,
		'head'     => sanitize_key( $args['head'] ?? '' ),
		'ref'      => sanitize_text_field( $args['ref'] ?? '' ),
		'run'      => (int) ( $args['run'] ?? 0 ),
		'note'     => sanitize_textarea_field( $args['note'] ?? '' ),
		'doc'      => esc_url_raw( $args['doc'] ?? '' ),
		'reverses' => (int) ( $args['reverses'] ?? 0 ),
		'by'       => get_current_user_id(),
		'at'       => current_time( 'mysql' ),
	);
	foreach ( $meta as $k => $v ) {
		bhela_bm_dist_meta_write( $row, '_bhela_fnd_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => $fund . '_' . $type,
		'object_type' => 'fund',
		'object_id'   => $row,
		'object_ref'  => $fund,
		'field'       => 'amount',
		'new_value'   => (string) $amount,
		'reason'      => $meta['note'],
	) );

	return $row;
}

/** One row, with its signed effect. */
function bhela_bm_fund_row( $id ) {
	if ( 'bhela_fund' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_fnd_' . $k, true );
	};
	$type   = (string) $m( 'type' );
	$amount = (int) $m( 'amount' );
	// Allocation adds, utilisation takes away, an adjustment carries its own sign.
	$signed = 'utilisation' === $type ? -abs( $amount ) : ( 'adjustment' === $type ? $amount : abs( $amount ) );

	return array(
		'id'       => (int) $id,
		'fund'     => (string) $m( 'fund' ),
		'type'     => $type,
		'amount'   => $amount,
		'signed'   => $signed,
		'date'     => (string) $m( 'date' ),
		'head'     => (string) $m( 'head' ),
		'ref'      => (string) $m( 'ref' ),
		'run'      => (int) $m( 'run' ),
		'note'     => (string) $m( 'note' ),
		'doc'      => (string) $m( 'doc' ),
		'reverses' => (int) $m( 'reverses' ),
		'by'       => (int) $m( 'by' ),
		'at'       => (string) $m( 'at' ),
	);
}

/** The reversal of a fund row, if one exists. */
function bhela_bm_fund_reversal_of( $row_id ) {
	$hit = get_posts( array(
		'post_type'      => 'bhela_fund',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_fnd_reverses',
		'meta_value'     => (int) $row_id,
	) );
	return $hit ? (int) $hit[0] : 0;
}

/** Reverse a utilisation. An allocation cannot be reversed — reverse the run instead. */
function bhela_bm_fund_reverse( $row_id, $reason ) {
	$r = bhela_bm_fund_row( $row_id );
	if ( ! $r ) {
		return new WP_Error( 'no_row', __( 'এই এন্ট্রি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'allocation' === $r['type'] ) {
		// An allocation is the arithmetic of a committed month. Cancelling it here
		// would leave the run saying one thing and the fund another.
		return new WP_Error( 'is_allocation', __( 'বরাদ্দ বাতিল করা যায় না — এটি একটি সম্পন্ন বণ্টনের অংশ।', 'bhela-booking' ) );
	}
	if ( '' === trim( (string) $reason ) ) {
		return new WP_Error( 'no_reason', __( 'কারণ লিখুন।', 'bhela-booking' ) );
	}
	if ( bhela_bm_fund_reversal_of( $row_id ) ) {
		return new WP_Error( 'already', __( 'এই এন্ট্রি আগেই বাতিল করা হয়েছে।', 'bhela-booking' ) );
	}
	return bhela_bm_fund_add( array(
		'fund'     => $r['fund'],
		'type'     => 'adjustment',
		'amount'   => -1 * $r['signed'],
		'head'     => $r['head'],
		'reverses' => $r['id'],
		'note'     => sprintf(
			/* translators: 1: row id, 2: reason */
			__( '#%1$d বাতিল — %2$s', 'bhela-booking' ),
			$r['id'],
			sanitize_textarea_field( $reason )
		),
	) );
}

/**
 * A fund's movements and its balance.
 *
 * Rows outside the window still move the balance; they are simply not listed, which
 * is what makes `opening` mean anything on a statement that starts mid-year.
 *
 * @return array{rows:array,opening:int,closing:int,allocated:int,used:int,by_head:array}
 */
function bhela_bm_fund_ledger( $fund, $from = '', $to = '' ) {
	$out = array(
		'rows' => array(), 'opening' => 0, 'closing' => 0,
		'allocated' => 0, 'used' => 0, 'by_head' => array(),
	);
	if ( ! bhela_bm_fund_exists( $fund ) ) {
		return $out;
	}
	$ids = get_posts( array(
		'post_type'      => 'bhela_fund',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_fnd_date',
		'orderby'        => array( 'meta_value' => 'ASC', 'ID' => 'ASC' ),
		'meta_query'     => array(
			array( 'key' => '_bhela_fnd_fund', 'value' => $fund ),
		),
	) );

	$from    = bhela_bm_report_date( $from );
	$to      = bhela_bm_report_date( $to );
	$balance = 0;

	foreach ( $ids as $id ) {
		$r = bhela_bm_fund_row( $id );
		if ( ! $r ) {
			continue;
		}
		$balance += $r['signed'];
		if ( $from && $r['date'] < $from ) {
			$out['opening'] = $balance;
			continue;
		}
		if ( $to && $r['date'] > $to ) {
			continue;
		}
		$r['balance'] = $balance;
		$out['rows'][] = $r;

		if ( 'allocation' === $r['type'] ) {
			$out['allocated'] += $r['amount'];
		} elseif ( 'utilisation' === $r['type'] && ! bhela_bm_fund_reversal_of( $r['id'] ) ) {
			$out['used'] += $r['amount'];
			$head          = $r['head'] ? $r['head'] : 'other';
			$out['by_head'][ $head ] = ( $out['by_head'][ $head ] ?? 0 ) + $r['amount'];
		}
	}
	arsort( $out['by_head'] );
	$out['closing'] = $balance;
	return $out;
}

/** A fund's balance right now. */
function bhela_bm_fund_balance( $fund ) {
	return bhela_bm_fund_ledger( $fund )['closing'];
}

/**
 * Record the reserve and management allocations for a committed distribution.
 *
 * Called from bhela_bm_dist_commit(). Idempotent by construction: a month can only
 * commit once, and each row carries its run id so a second attempt is visible rather
 * than silent.
 */
function bhela_bm_fund_allocate_run( $run_id ) {
	$d = bhela_bm_dist_data( $run_id );
	if ( ! $d['month'] ) {
		return;
	}
	$existing = get_posts( array(
		'post_type'      => 'bhela_fund',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_fnd_run',
		'meta_value'     => (int) $run_id,
	) );
	if ( $existing ) {
		return;   // already allocated; never allocate a run twice
	}

	foreach ( array( 'reserve' => $d['reserve'], 'management' => $d['management'] ) as $fund => $amount ) {
		if ( $amount <= 0 ) {
			continue;
		}
		bhela_bm_fund_add( array(
			'fund'   => $fund,
			'type'   => 'allocation',
			'amount' => (int) $amount,
			'date'   => $d['month'] . '-01',
			'ref'    => $d['month'],
			'run'    => (int) $run_id,
			'note'   => sprintf(
				/* translators: %s: month */
				__( '%s মাসের বণ্টন থেকে বরাদ্দ', 'bhela-booking' ),
				$d['month']
			),
		) );
	}
}
