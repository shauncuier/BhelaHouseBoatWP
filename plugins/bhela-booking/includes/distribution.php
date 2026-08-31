<?php
/**
 * Turning a month's trading into money owed to investors.
 *
 * The chain, and the important thing about it is how little of it is new:
 *
 *   approved Cost Sheets  →  bhela_bm_statement_data( $month )['gross']
 *          ↓  reserve %
 *          ↓  investor pool %  /  management %
 *          ↓  split by shareholding
 *      investor ledger
 *
 * Everything above the first arrow already existed. The Monthly Statement is the ONLY
 * input, which means the cost sheet's prepare → check → approve chain is already the
 * gate on investor money: **an unapproved sheet pays nobody**, without a second
 * approval workflow being invented here.
 *
 * Two rules carry the weight.
 *
 * **Preview and commit are separate, and the screen shows the preview.** The
 * arithmetic lives in one function; the admin sees exactly what will be written
 * before it is written. Recomputing it a second way for display is how a screen and
 * a ledger start disagreeing.
 *
 * **A committed run locks its month.** Ledger rows are money. Re-running would either
 * double-pay or need silent deletion, so the month is closed the way a stock period
 * is closed — see bhela_bm_dist_locked() in distribution-core.php, which loads on
 * every request and not just in wp-admin.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Percentages in force, clamped. */
function bhela_bm_dist_config() {
	$s = bhela_bm_get_settings();
	$r = max( 0, min( 90, (int) ( $s['inv_reserve_pct'] ?? 0 ) ) );
	$i = max( 0, min( 100, (int) ( $s['inv_investor_pct'] ?? 70 ) ) );
	return array(
		'reserve'    => $r,
		'investor'   => $i,
		// Derived, never stored separately: two numbers that must add to 100 will
		// eventually be edited one at a time and stop adding to 100.
		'management' => 100 - $i,
	);
}

/**
 * What a month WOULD distribute. Pure — writes nothing, reads nothing it owns.
 *
 * @param string   $month       YYYY-MM.
 * @param int|null $reserve_pct Override, or null for the configured default.
 * @return array
 */
function bhela_bm_dist_preview( $month, $reserve_pct = null ) {
	$cfg   = bhela_bm_dist_config();
	$res   = null === $reserve_pct ? $cfg['reserve'] : max( 0, min( 90, (int) $reserve_pct ) );
	$month = preg_match( '/^\d{4}-\d{2}$/', (string) $month ) ? $month : '';

	$out = array(
		'month'        => $month,
		'gross'        => 0,
		'reserve_pct'  => $res,
		'reserve'      => 0,
		'distributable' => 0,
		'investor_pct' => $cfg['investor'],
		'investor'     => 0,
		'management'   => 0,
		'rows'         => array(),
		'trips'        => 0,
		'shares'       => bhela_bm_share_totals(),
		'committed'    => false,
	);
	if ( '' === $month || ! function_exists( 'bhela_bm_statement_data' ) ) {
		return $out;
	}

	$st = bhela_bm_statement_data( $month );
	// `gross` is already trip profit less expenses, payroll and partner commission.
	// A loss distributes nothing: there is no negative to hand out, and carrying it
	// forward is a decision for a person, not a default.
	$out['gross'] = max( 0, (int) ( $st['gross'] ?? 0 ) );
	$out['trips'] = is_array( $st['trips'] ?? null ) ? count( $st['trips'] ) : 0;

	$out['reserve']       = (int) round( $out['gross'] * $res / 100 );
	$out['distributable'] = $out['gross'] - $out['reserve'];
	$out['investor']      = (int) round( $out['distributable'] * $cfg['investor'] / 100 );
	// Management takes the remainder rather than its own percentage of the total, so
	// investor + management always equals distributable to the taka even when the
	// two percentages round in the same direction.
	$out['management']    = $out['distributable'] - $out['investor'];

	$holders = array();
	foreach ( bhela_bm_investors() as $id ) {
		$shares = bhela_bm_investor_shares( $id );
		if ( $shares > 0 && 'exited' !== bhela_bm_investor_status( $id ) ) {
			$holders[ $id ] = $shares;
		}
	}
	$split = bhela_bm_split_by_shares( $out['investor'], $holders );

	foreach ( $split as $id => $amount ) {
		$out['rows'][] = array(
			'investor' => (int) $id,
			'name'     => get_the_title( $id ),
			'shares'   => $holders[ $id ],
			'pct'      => bhela_bm_investor_share_pct( $id ),
			'amount'   => (int) $amount,
		);
	}
	usort( $out['rows'], function ( $a, $b ) {
		return $b['amount'] <=> $a['amount'];
	} );

	// What actually got allocated. When no shares are issued this is 0 while the pool
	// is not, and the screen must say so rather than implying the money went out.
	$out['allocated']   = array_sum( wp_list_pluck( $out['rows'], 'amount' ) );
	$out['unallocated'] = $out['investor'] - $out['allocated'];
	$out['committed']   = (bool) bhela_bm_dist_run( $month );

	return $out;
}

/** The committed run for a month, or null. */
function bhela_bm_dist_run( $month ) {
	$month = preg_match( '/^\d{4}-\d{2}$/', (string) $month ) ? $month : '';
	if ( '' === $month ) {
		return null;
	}
	$index = get_option( 'bhela_bm_dist_runs', array() );
	if ( ! is_array( $index ) || empty( $index[ $month ] ) ) {
		return null;
	}
	$id = (int) $index[ $month ];
	return get_post( $id ) ? $id : null;
}

/**
 * Commit a month: write the run, and one ledger row per investor.
 *
 * @param string   $month       YYYY-MM.
 * @param int|null $reserve_pct Override.
 * @param string   $note        Free text recorded on the run.
 * @return int|WP_Error Run post id.
 */
function bhela_bm_dist_commit( $month, $reserve_pct = null, $note = '' ) {
	if ( ! current_user_can( 'bhela_dist_run' ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$month = preg_match( '/^\d{4}-\d{2}$/', (string) $month ) ? $month : '';
	if ( '' === $month ) {
		return new WP_Error( 'bad_month', __( 'মাস নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( bhela_bm_dist_run( $month ) ) {
		return new WP_Error( 'already', __( 'এই মাসের বণ্টন আগেই সম্পন্ন হয়েছে।', 'bhela-booking' ) );
	}

	$p = bhela_bm_dist_preview( $month, $reserve_pct );
	if ( $p['gross'] <= 0 ) {
		return new WP_Error( 'no_profit', __( 'এই মাসে অনুমোদিত ট্রিপ থেকে বণ্টনযোগ্য লাভ নেই।', 'bhela-booking' ) );
	}
	$totals = bhela_bm_share_totals();
	if ( $totals['over'] ) {
		// Over-issued shares mean the percentages already add to more than 100. Paying
		// out on them hands out money that does not exist.
		return new WP_Error( 'over_issued', sprintf(
			/* translators: 1: issued shares, 2: configured shares */
			__( 'ইস্যুকৃত শেয়ার (%1$d) কনফিগার করা মোটের (%2$d) চেয়ে বেশি — আগে ঠিক করুন।', 'bhela-booking' ),
			$totals['issued'],
			$totals['configured']
		) );
	}

	// The mutex is the index, not the post: add_option() fails if the key exists, so
	// two simultaneous commits cannot both mint a run. Same trick as
	// bhela_bm_inv_period_id().
	$claim = 'bhela_bm_dist_claim_' . $month;
	if ( ! add_option( $claim, time(), '', false ) ) {
		return new WP_Error( 'busy', __( 'এই মাসের বণ্টন এই মুহূর্তে চলছে।', 'bhela-booking' ) );
	}

	$run = wp_insert_post( array(
		'post_type'   => 'bhela_dist',
		'post_status' => 'publish',
		'post_title'  => sprintf( 'Distribution %s', $month ),
	), true );
	if ( is_wp_error( $run ) ) {
		delete_option( $claim );
		return $run;
	}

	bhela_bm_dist_meta_write( $run, '_bhela_dist_month', $month );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_gross', $p['gross'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_reserve_pct', $p['reserve_pct'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_reserve', $p['reserve'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_distributable', $p['distributable'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_investor_pct', $p['investor_pct'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_investor', $p['investor'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_management', $p['management'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_trips', $p['trips'] );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_note', sanitize_textarea_field( $note ) );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_by', get_current_user_id() );
	bhela_bm_dist_meta_write( $run, '_bhela_dist_at', current_time( 'mysql' ) );

	foreach ( $p['rows'] as $row ) {
		if ( $row['amount'] <= 0 ) {
			continue;
		}
		bhela_bm_ledger_add( array(
			'investor' => $row['investor'],
			'type'     => 'profit',
			'amount'   => $row['amount'],
			'date'     => $month . '-01',
			'ref'      => $month,
			'run'      => $run,
			'note'     => sprintf(
				/* translators: 1: month, 2: shares */
				__( '%1$s মাসের লাভ — %2$d শেয়ার', 'bhela-booking' ),
				$month,
				$row['shares']
			),
		) );
	}

	// The reserve and management shares become real fund rows here. Before this they
	// existed only as meta on the run — money set aside on paper and then untracked,
	// so "what is left in the reserve" had no answer that added up.
	if ( function_exists( 'bhela_bm_fund_allocate_run' ) ) {
		bhela_bm_fund_allocate_run( $run );
	}

	$index           = get_option( 'bhela_bm_dist_runs', array() );
	$index           = is_array( $index ) ? $index : array();
	$index[ $month ] = $run;
	update_option( 'bhela_bm_dist_runs', $index, false );
	delete_option( $claim );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'distribute',
		'object_type' => 'dist',
		'object_id'   => $run,
		'object_ref'  => $month,
		'field'       => 'investor_pool',
		'new_value'   => (string) $p['investor'],
		'reason'      => sprintf(
			'Gross %d, reserve %d%% = %d, investor %d%% = %d, management = %d, across %d investors.',
			$p['gross'], $p['reserve_pct'], $p['reserve'],
			$p['investor_pct'], $p['investor'], $p['management'], count( $p['rows'] )
		),
	) );

	return $run;
}

/** Everything stored on a committed run. */
function bhela_bm_dist_data( $run_id ) {
	$m = function ( $k ) use ( $run_id ) {
		return get_post_meta( $run_id, '_bhela_dist_' . $k, true );
	};
	return array(
		'id'            => (int) $run_id,
		'month'         => (string) $m( 'month' ),
		'gross'         => (int) $m( 'gross' ),
		'reserve_pct'   => (int) $m( 'reserve_pct' ),
		'reserve'       => (int) $m( 'reserve' ),
		'distributable' => (int) $m( 'distributable' ),
		'investor_pct'  => (int) $m( 'investor_pct' ),
		'investor'      => (int) $m( 'investor' ),
		'management'    => (int) $m( 'management' ),
		'trips'         => (int) $m( 'trips' ),
		'note'          => (string) $m( 'note' ),
		'by'            => (int) $m( 'by' ),
		'at'            => (string) $m( 'at' ),
	);
}
