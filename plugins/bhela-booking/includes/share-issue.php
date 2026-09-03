<?php
/**
 * A new investor buys in — and everybody's percentage moves, honestly.
 *
 * Without a recorded valuation, the only price anybody could quote was the original
 * "1 share = ৳1 lakh". Selling a share at ৳1,00,000 when it is worth ৳1,47,826 hands
 * the buyer 47% of a share for nothing, and it comes out of the existing holders'
 * pockets. That is the dilution this module exists to prevent, and the way it prevents
 * it is by making the price come from an approved valuation rather than from memory.
 *
 * The arithmetic, exactly as the owner set it out:
 *
 *     Price per share    = Pre-money valuation ÷ shares before
 *     Shares issued      = a WHOLE number, chosen by the operator
 *     Amount raised      = shares issued × price per share
 *     Post-money         = pre-money + amount raised
 *     Shares after       = shares before + shares issued
 *
 * **Nobody's share count changes and nobody's holding value changes.** A 10-share
 * holder goes from 10/115 (8.696%) to 10/122 (8.197%) while their holding stays at
 * 10 × ৳1,47,826 = ৳14,78,260 — because the business gained cash exactly equal to what
 * the new shares are worth. Percentage falls, value does not. That is what pricing a
 * round at fair value MEANS, and it is the sentence to reach for when somebody asks why
 * their percentage went down.
 *
 * **Whole shares only.** ৳10,00,000 at ৳1,47,826 is 6.765 shares, and the screen says
 * so rather than rounding quietly: issue 6 and raise ৳8,86,956, or issue 7 and raise
 * ৳10,34,782. The money follows the share count. A rounded share count against
 * unrounded money is how a register stops adding up.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_register_share_issue_cpt() {
	register_post_type( 'bhela_share_issue', array(
		'labels'              => array( 'name' => __( 'Share Issues', 'bhela-booking' ) ),
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
add_action( 'init', 'bhela_bm_register_share_issue_cpt' );

/* =========================================================
 * THE CALCULATOR — pure, so the screen can show it before anything is written
 * ========================================================= */

/**
 * What a round WOULD do. Writes nothing.
 *
 * Pure, in the same way `bhela_bm_dist_preview()` is pure and for the same reason: the
 * screen shows this and the commit writes exactly it, so what somebody approved is what
 * happened.
 *
 * @param int $shares Whole shares to issue. 0 to price by money instead.
 * @param int $target Target investment, used only to suggest a share count.
 * @return array
 */
function bhela_bm_share_issue_preview( $shares = 0, $target = 0 ) {
	$v      = bhela_bm_valuation_current();
	$cfg    = bhela_bm_share_config();
	$before = max( 0, (int) $cfg['total_shares'] );
	$price  = bhela_bm_share_value( $v );

	$out = array(
		'valued'       => (bool) $v,
		'valuation'    => $v ? $v['id'] : 0,
		'as_at'        => $v ? $v['date'] : '',
		// Pre-money is what the business is worth BEFORE the new money arrives. With no
		// approved valuation this falls back to shares × the issue price, which is the
		// honest reading of "we have never valued it": the book value.
		'pre_money'    => $v ? (int) $v['total'] : $before * $price,
		'price'        => $price,
		'before'       => $before,
		'shares'       => 0,
		'amount'       => 0,
		'post_money'   => 0,
		'after'        => $before,
		'target'       => max( 0, (int) $target ),
		'exact'        => 0.0,
		'suggest_down' => 0,
		'suggest_up'   => 0,
	);

	// Pricing by money: say what the exact share count would be, and offer the two
	// whole numbers either side of it rather than picking one.
	if ( $target > 0 && $price > 0 ) {
		$out['exact']        = round( $target / $price, 3 );
		$out['suggest_down'] = (int) floor( $target / $price );
		$out['suggest_up']   = (int) ceil( $target / $price );
	}

	$shares = max( 0, (int) $shares );
	if ( $shares > 0 ) {
		$out['shares']     = $shares;
		$out['amount']     = $shares * $price;
		$out['after']      = $before + $shares;
		$out['post_money'] = $out['pre_money'] + $out['amount'];
	}
	return $out;
}

/**
 * What a named holder's position looks like either side of a round.
 *
 * Exists so the screen can show the dilution to the people it happens to, before it
 * happens. A percentage falling is alarming read on its own and unremarkable read
 * beside an unchanged holding value.
 */
function bhela_bm_share_issue_effect( $preview ) {
	$rows = array();
	if ( empty( $preview['shares'] ) || $preview['after'] <= 0 || $preview['before'] <= 0 ) {
		return $rows;
	}
	foreach ( bhela_bm_investors() as $id ) {
		$held = bhela_bm_investor_shares( $id );
		if ( $held <= 0 ) {
			continue;
		}
		$rows[] = array(
			'investor' => (int) $id,
			'name'     => get_the_title( $id ),
			'shares'   => $held,
			'pct_before' => round( $held / $preview['before'] * 100, 4 ),
			'pct_after'  => round( $held / $preview['after'] * 100, 4 ),
			// The reassuring half, and the reason the round is fair.
			'value'      => $held * $preview['price'],
		);
	}
	usort( $rows, function ( $a, $b ) {
		return $b['shares'] <=> $a['shares'];
	} );
	return $rows;
}

/* =========================================================
 * THE COMMIT
 * ========================================================= */

/**
 * Commit a round: record it, raise the configured share total, credit the investor.
 *
 * @param array $args investor, shares, date, note. Priced from the current valuation.
 * @return int|WP_Error The issue record id.
 */
function bhela_bm_share_issue_commit( $args ) {
	if ( ! current_user_can( 'bhela_investor_approve' ) ) {
		// Deliberately the APPROVE capability, not the edit one. Issuing shares moves
		// every existing holder's ownership percentage; it is not a register edit.
		return new WP_Error( 'denied', __( 'শেয়ার ইস্যু করার অনুমতি নেই।', 'bhela-booking' ) );
	}

	$investor = (int) ( $args['investor'] ?? 0 );
	$shares   = (int) ( $args['shares'] ?? 0 );
	$date     = bhela_bm_report_date( $args['date'] ?? '' );

	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( $shares < 1 ) {
		return new WP_Error( 'bad_shares', __( 'কমপক্ষে ১টি শেয়ার ইস্যু করতে হবে।', 'bhela-booking' ) );
	}
	if ( 'exited' === bhela_bm_investor_status( $investor ) ) {
		return new WP_Error( 'exited', __( 'এই বিনিয়োগকারী প্রত্যাহৃত।', 'bhela-booking' ) );
	}

	$p = bhela_bm_share_issue_preview( $shares );
	if ( ! $p['valued'] ) {
		// The whole point of the module. Issuing at the historic price after the
		// business has grown is the dilution this exists to prevent, so it is refused
		// rather than warned about.
		return new WP_Error(
			'no_valuation',
			__( 'অনুমোদিত মূল্যায়ন ছাড়া শেয়ার ইস্যু করা যাবে না — প্রথমে বর্তমান মূল্যায়ন রেকর্ড ও অনুমোদন করুন।', 'bhela-booking' )
		);
	}
	if ( $p['price'] < 1 ) {
		return new WP_Error( 'bad_price', __( 'শেয়ার প্রতি মূল্য বের করা যায়নি।', 'bhela-booking' ) );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'bhela_share_issue',
		'post_status' => 'publish',
		'post_title'  => sprintf(
			/* translators: 1: shares, 2: investor, 3: amount */
			__( '%1$d shares to %2$s — %3$s', 'bhela-booking' ),
			$shares,
			get_the_title( $investor ),
			bhela_bm_money( $p['amount'] )
		),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	// Written through the lock's own window: this record is immutable from the moment
	// it exists, so even its first write needs the pen.
	foreach ( array(
		'date'         => $date ? $date : current_time( 'Y-m-d' ),
		'investor'     => $investor,
		'valuation'    => $p['valuation'],
		'pre_money'    => $p['pre_money'],
		'price'        => $p['price'],
		'shares'       => $shares,
		'amount'       => $p['amount'],
		'post_money'   => $p['post_money'],
		'shares_before' => $p['before'],
		'shares_after' => $p['after'],
		'note'         => sanitize_textarea_field( $args['note'] ?? '' ),
		'by'           => get_current_user_id(),
		'at'           => current_time( 'mysql' ),
	) as $k => $v ) {
		bhela_bm_val_meta_write( $id, '_bhela_iss_' . $k, $v );
	}

	// Raise the configured total. THIS IS THE ONLY SANCTIONED WRITER of
	// inv_total_shares — see bhela_bm_share_issue_drift(), which reports when the
	// setting and the issue history disagree. Every percentage in the plugin divides by
	// this number, so the dilution lands everywhere at once and correctly.
	//
	// Re-read immediately before writing, and abort if it moved. Two commits arriving
	// together would otherwise both read 115 and both write 122: fourteen shares
	// issued, seven recorded in the total. Drift would report it afterwards, but a
	// register that has to be repaired by hand is a worse outcome than a round that
	// refuses and can simply be run again.
	$fresh = get_option( 'bhela_bm_settings', array() );
	$now   = isset( $fresh['inv_total_shares'] ) ? (int) $fresh['inv_total_shares'] : (int) $p['before'];
	if ( $now !== (int) $p['before'] ) {
		// The record is already locked, so this needs the plugin's own delete path —
		// wp_delete_post() alone is refused and would leave an orphan that
		// bhela_bm_share_issue_drift() then counts as a real round.
		bhela_bm_val_delete( $id );
		return new WP_Error(
			'moved',
			__( 'শেয়ার সংখ্যা এর মধ্যে পরিবর্তিত হয়েছে। পাতাটি রিফ্রেশ করে আবার হিসাব করুন।', 'bhela-booking' )
		);
	}
	// Only the key this owns is written back. bhela_bm_get_settings() merges the
	// defaults in, so writing THAT would freeze every current default as an explicit
	// stored value and a later change to bhela_bm_default_settings() would never reach
	// this site.
	$fresh['inv_total_shares'] = $p['after'];
	update_option( 'bhela_bm_settings', $fresh );

	// And credit the investor with the shares they bought, plus the money they paid.
	// The amount is ADDED to what they had already put in: an existing holder topping
	// up has one cost basis across both rounds, and that basis is what appreciation is
	// measured from.
	//
	// **Read the basis BEFORE raising the share count.** bhela_bm_investor_amount()
	// falls back to shares x the issue price when no amount was ever recorded, so
	// reading it afterwards prices the shares just issued at the OLD price and adds
	// them again — a new investor's basis came out 7 x 100,000 too high.
	$prior_basis = bhela_bm_investor_amount( $investor );
	update_post_meta( $investor, '_bhela_inv_shares', bhela_bm_investor_shares( $investor ) + $shares );
	update_post_meta( $investor, '_bhela_inv_amount', $prior_basis + $p['amount'] );

	bhela_bm_audit( array(
		'channel'      => 'investor',
		'action'       => 'share_issue',
		'object_type'  => 'share_issue',
		'object_id'    => $id,
		'object_ref'   => get_the_title( $investor ),
		'field'        => 'shares',
		'old_value'    => (string) $p['before'],
		'new_value'    => (string) $p['after'],
		'approval_ref' => (string) $p['amount'],
		'reason'       => sanitize_textarea_field( $args['note'] ?? '' ),
	) );

	return $id;
}

/** One committed issue, or null. */
function bhela_bm_share_issue( $id ) {
	if ( 'bhela_share_issue' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_iss_' . $k, true );
	};
	return array(
		'id'            => (int) $id,
		'date'          => (string) $m( 'date' ),
		'investor'      => (int) $m( 'investor' ),
		'valuation'     => (int) $m( 'valuation' ),
		'pre_money'     => (int) $m( 'pre_money' ),
		'price'         => (int) $m( 'price' ),
		'shares'        => (int) $m( 'shares' ),
		'amount'        => (int) $m( 'amount' ),
		'post_money'    => (int) $m( 'post_money' ),
		'shares_before' => (int) $m( 'shares_before' ),
		'shares_after'  => (int) $m( 'shares_after' ),
		'note'          => (string) $m( 'note' ),
		'by'            => (int) $m( 'by' ),
		'at'            => (string) $m( 'at' ),
	);
}

/** How many issues one listing will read. Filterable. */
function bhela_bm_share_issue_limit() {
	return (int) apply_filters( 'bhela_bm_share_issue_limit', 200 );
}

/**
 * Every committed issue, newest first.
 *
 * Capped, so this is a LISTING and nothing that has to add up may sum it — see
 * `bhela_bm_share_issue_drift()`, which counts in SQL for exactly that reason.
 */
function bhela_bm_share_issues() {
	$out = array();
	$ids = get_posts( array(
		'post_type'      => 'bhela_share_issue',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_share_issue_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	) );
	foreach ( $ids as $id ) {
		$r = bhela_bm_share_issue( $id );
		if ( $r ) {
			$out[] = $r;
		}
	}
	if ( count( $ids ) >= bhela_bm_share_issue_limit() && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'investor', sprintf(
			/* translators: %d: the cap */
			'Share issue listing hit its cap of %d — older rounds are not listed.',
			bhela_bm_share_issue_limit()
		) );
	}
	return $out;
}

/**
 * Valuation id => the issue priced from it. Built once.
 *
 * The screen renders one row per valuation and asks "was an issue priced from this?"
 * for each, which through the single-lookup version below meant a full query and a
 * meta read per row. This is the map; the lookup reads it.
 *
 * @return array<int,int>
 */
function bhela_bm_share_issue_valuation_map() {
	$map = array();
	foreach ( bhela_bm_share_issues() as $r ) {
		if ( $r['valuation'] && ! isset( $map[ $r['valuation'] ] ) ) {
			$map[ (int) $r['valuation'] ] = (int) $r['id'];
		}
	}
	return $map;
}

/**
 * Is this valuation the basis of a committed issue? Returns the issue id, or 0.
 *
 * @param int        $valuation_id Valuation.
 * @param array|null $map          Pass a prebuilt map when calling this in a loop.
 */
function bhela_bm_valuation_used_by_issue( $valuation_id, $map = null ) {
	$map = null === $map ? bhela_bm_share_issue_valuation_map() : $map;
	return isset( $map[ (int) $valuation_id ] ) ? (int) $map[ (int) $valuation_id ] : 0;
}

/**
 * Does the configured share total agree with the issue history?
 *
 * **Reports, never corrects** — the same contract as `bhela_bm_share_totals()` and the
 * cost sheet's earnings drift. If somebody edits the setting by hand, or an issue is
 * committed on a site whose total was already wrong, a person decides which figure is
 * right. Silently rewriting the setting to make it agree would erase the evidence that
 * they ever disagreed, and this number is the divisor under every percentage and every
 * distribution.
 *
 * @return array{expected:int,configured:int,gap:int,drift:bool,initial:int,issued:int}
 */
function bhela_bm_share_issue_drift() {
	global $wpdb;
	$cfg = bhela_bm_share_config();

	// Counted and summed in SQL, NOT by adding up bhela_bm_share_issues(), which is a
	// capped listing. Summing a truncated list would understate the shares issued and
	// so report drift on a register that is perfectly correct — the same failure
	// bhela_bm_payreq_pending_total() had, where a capped listing understated money
	// owed (§13.6 of the review that found it).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$agg = $wpdb->get_row(
		"SELECT COUNT(*) AS n, COALESCE( SUM( sh.meta_value + 0 ), 0 ) AS issued
		 FROM {$wpdb->postmeta} sh
		 INNER JOIN {$wpdb->posts} p ON p.ID = sh.post_id
		   AND p.post_type = 'bhela_share_issue' AND p.post_status = 'publish'
		 WHERE sh.meta_key = '_bhela_iss_shares'",
		ARRAY_A
	);
	$count  = (int) ( $agg['n'] ?? 0 );
	$issued = (int) ( $agg['issued'] ?? 0 );

	// The baseline is the share count the OLDEST issue recorded as "before" — the only
	// place the pre-issue total survives once the setting has moved. Read directly so a
	// listing cap cannot hide it either.
	$initial = (int) $cfg['total_shares'];
	if ( $count > 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$initial = (int) $wpdb->get_var(
			"SELECT bf.meta_value + 0
			 FROM {$wpdb->postmeta} bf
			 INNER JOIN {$wpdb->posts} p ON p.ID = bf.post_id
			   AND p.post_type = 'bhela_share_issue' AND p.post_status = 'publish'
			 WHERE bf.meta_key = '_bhela_iss_shares_before'
			 ORDER BY bf.post_id ASC LIMIT 1"
		);
	}
	$expected = $initial + $issued;

	return array(
		'initial'    => $initial,
		'issued'     => $issued,
		'rounds'     => $count,
		'expected'   => $expected,
		'configured' => (int) $cfg['total_shares'],
		'gap'        => (int) $cfg['total_shares'] - $expected,
		'drift'      => $count > 0 && (int) $cfg['total_shares'] !== $expected,
	);
}
