<?php
/**
 * What BHELA is worth, and therefore what a share is worth.
 *
 * The investor module has always tracked share COUNTS and profit. It had no idea what
 * the business was worth, so an investor's return was only half-reported: profit
 * distribution was visible and capital appreciation was not. A 10-share holding bought
 * for ৳10,00,000 is worth ৳14,78,260 once BHELA is valued at ৳1.70 Cr, and that
 * ৳4,78,260 appeared nowhere.
 *
 * **The share count never changes to reflect growth. The valuation changes, and the
 * value per share follows.**
 *
 *     Current Share Value  = Approved Valuation ÷ Total Shares
 *     Holding Value        = Shares × Current Share Value
 *     Capital Appreciation = Holding Value − What They Paid In
 *
 * 115 shares stay 115 shares while the business grows. New shares are issued only when
 * new money comes in, at the approved valuation of the day — see
 * `includes/share-issue.php`, which is the other half of this and the reason a
 * valuation has to be a signed record rather than a number in a settings box.
 *
 * **Two things this module deliberately does not do.**
 *
 * It does not touch a single existing figure. `bhela_bm_investor_roi()` keeps returning
 * exactly what it returned before — `investment`, `received`, `roi` and the rest all
 * mean what they meant — and appreciation is reported alongside, never folded in. Two
 * kinds of money in one total is how a dashboard tells an investor they have received
 * money that is still in the boat (§13.44's rule, applied to a new pair of figures).
 *
 * And it invents nothing. With no approved valuation on record `bhela_bm_share_value()`
 * returns the historic issue price, so every screen on a site that never uses this
 * feature reads exactly as it does today.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * THE RECORD
 * ========================================================= */

function bhela_bm_register_valuation_cpt() {
	register_post_type( 'bhela_valuation', array(
		'labels'              => array( 'name' => __( 'Valuations', 'bhela-booking' ) ),
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
add_action( 'init', 'bhela_bm_register_valuation_cpt' );

/** draft | approved. */
function bhela_bm_valuation_states() {
	return array(
		'draft'    => array( 'label' => __( 'Draft — not yet approved', 'bhela-booking' ), 'tone' => 'attention' ),
		'approved' => array( 'label' => __( 'Approved', 'bhela-booking' ), 'tone' => 'good' ),
		''         => array( 'label' => __( 'Unreadable — contact the office', 'bhela-booking' ), 'tone' => 'neutral' ),
	);
}

/**
 * Record a valuation. Draft — it decides nothing until somebody approves it.
 *
 * @param array $args date, total, basis, doc, note.
 * @return int|WP_Error
 */
function bhela_bm_valuation_add( $args ) {
	if ( ! current_user_can( 'bhela_investor_valuation' ) ) {
		return new WP_Error( 'denied', __( 'মূল্যায়ন রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	$total = (int) ( $args['total'] ?? 0 );
	$date  = bhela_bm_report_date( $args['date'] ?? '' );
	if ( $total <= 0 ) {
		return new WP_Error( 'bad_total', __( 'মোট মূল্যায়ন শূন্যের বেশি হতে হবে।', 'bhela-booking' ) );
	}
	if ( '' === $date ) {
		return new WP_Error( 'bad_date', __( 'মূল্যায়নের তারিখ দিন।', 'bhela-booking' ) );
	}

	// The share count is SNAPSHOTTED, not read live on every render. It is a historical
	// fact: a share issue six months from now must not retroactively change what this
	// valuation said a share was worth on this date. The per-share value stays derived
	// from the two stored figures (§13.8 — a derived figure that gets cached is a
	// figure that goes stale), but its divisor is frozen here.
	$cfg    = bhela_bm_share_config();
	$shares = max( 1, (int) $cfg['total_shares'] );

	$id = wp_insert_post( array(
		'post_type'   => 'bhela_valuation',
		'post_status' => 'publish',
		'post_title'  => sprintf(
			/* translators: 1: date, 2: total valuation */
			__( 'Valuation %1$s — %2$s', 'bhela-booking' ),
			$date,
			bhela_bm_money( $total )
		),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	foreach ( array(
		'date'   => $date,
		'total'  => $total,
		'shares' => $shares,
		'basis'  => sanitize_textarea_field( $args['basis'] ?? '' ),
		'doc'    => esc_url_raw( $args['doc'] ?? '' ),
		'note'   => sanitize_textarea_field( $args['note'] ?? '' ),
		'status' => 'draft',
		'by'     => get_current_user_id(),
		'at'     => current_time( 'mysql' ),
	) as $k => $v ) {
		update_post_meta( $id, '_bhela_val_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'valuation_draft',
		'object_type' => 'valuation',
		'object_id'   => $id,
		'object_ref'  => $date,
		'field'       => 'total',
		'new_value'   => (string) $total,
		'reason'      => sanitize_textarea_field( $args['basis'] ?? '' ),
	) );

	return $id;
}

/** One valuation, or null. */
function bhela_bm_valuation( $id ) {
	if ( 'bhela_valuation' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_val_' . $k, true );
	};
	$total  = (int) $m( 'total' );
	$shares = max( 1, (int) $m( 'shares' ) );
	return array(
		'id'          => (int) $id,
		'date'        => (string) $m( 'date' ),
		'total'       => $total,
		'shares'      => $shares,
		// Derived, every read. Integer taka: a share price in paisa is a precision the
		// business does not transact in, and the whole-taka figure is the one that goes
		// on a statement.
		'per_share'   => (int) round( $total / $shares ),
		'basis'       => (string) $m( 'basis' ),
		'doc'         => (string) $m( 'doc' ),
		'note'        => (string) $m( 'note' ),
		'status'      => (string) $m( 'status' ),
		'by'          => (int) $m( 'by' ),
		'at'          => (string) $m( 'at' ),
		'approved_by' => (int) $m( 'approved_by' ),
		'approved_at' => (string) $m( 'approved_at' ),
	);
}

/**
 * Approve a valuation, and only now does it decide anything.
 *
 * Requires `bhela_investor_approve` and refuses the person who recorded it — the same
 * second signature `bhela_bm_payreq_approve()` requires, and for a stronger reason: a
 * payment request moves one investor's money once, while a valuation changes the
 * reported holding value of every investor at once and is what the next share issue is
 * priced from.
 *
 * @return true|WP_Error
 */
function bhela_bm_valuation_approve( $id ) {
	if ( ! current_user_can( 'bhela_investor_approve' ) ) {
		return new WP_Error( 'denied', __( 'অনুমোদনের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$v = bhela_bm_valuation( $id );
	if ( ! $v ) {
		return new WP_Error( 'no_val', __( 'এই মূল্যায়ন পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'draft' !== $v['status'] ) {
		return new WP_Error( 'settled', __( 'এই মূল্যায়ন আগেই অনুমোদিত হয়েছে।', 'bhela-booking' ) );
	}
	if ( (int) $v['by'] === get_current_user_id() ) {
		return new WP_Error(
			'same_person',
			__( 'যিনি মূল্যায়ন রেকর্ড করেছেন তিনি নিজে অনুমোদন করতে পারবেন না।', 'bhela-booking' )
		);
	}

	// Claimed with a single conditional UPDATE, for the reason §13.50 gives: a
	// read-then-write state check lets two approvals both pass, and here that would
	// leave two approved valuations on the same date with nothing to choose between
	// them. The database picks the winner.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->postmeta} SET meta_value = 'approved'
		 WHERE post_id = %d AND meta_key = '_bhela_val_status' AND meta_value = 'draft'",
		$id
	) );
	wp_cache_delete( $id, 'post_meta' );
	if ( false === $claimed ) {
		return new WP_Error( 'db', __( 'মূল্যায়নটি লক করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}
	if ( (int) $claimed < 1 ) {
		return new WP_Error( 'settled', __( 'এই মূল্যায়ন আগেই অনুমোদিত হয়েছে।', 'bhela-booking' ) );
	}

	// Written through the window: the record is locked the instant the status flipped.
	bhela_bm_val_meta_write( $id, '_bhela_val_approved_by', get_current_user_id() );
	bhela_bm_val_meta_write( $id, '_bhela_val_approved_at', current_time( 'mysql' ) );

	bhela_bm_valuation_current( true );   // what is in force just changed

	bhela_bm_audit( array(
		'channel'      => 'investor',
		'action'       => 'valuation_approve',
		'object_type'  => 'valuation',
		'object_id'    => $id,
		'object_ref'   => $v['date'],
		'field'        => 'status',
		'old_value'    => 'draft',
		'new_value'    => 'approved',
		'approval_ref' => (string) $v['per_share'],
	) );
	return true;
}

/**
 * Reopen an approved valuation.
 *
 * A lock that cannot be lifted is a trap (§13.40), and a valuation is a judgement that
 * can turn out to have been wrong. Reopening is loud: it needs a reason, it is audited,
 * and while it is a draft every screen falls back to the previous approved valuation —
 * so the figures move, visibly, rather than sitting on a number nobody stands behind.
 *
 * @return true|WP_Error
 */
function bhela_bm_valuation_reopen( $id, $reason ) {
	if ( ! current_user_can( 'bhela_investor_approve' ) ) {
		return new WP_Error( 'denied', __( 'অনুমোদনের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$v = bhela_bm_valuation( $id );
	if ( ! $v ) {
		return new WP_Error( 'no_val', __( 'এই মূল্যায়ন পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'approved' !== $v['status'] ) {
		return new WP_Error( 'not_approved', __( 'এটি অনুমোদিত নয়।', 'bhela-booking' ) );
	}
	if ( '' === trim( (string) $reason ) ) {
		return new WP_Error( 'no_reason', __( 'কারণ লিখুন।', 'bhela-booking' ) );
	}
	// A valuation a share issue was priced from cannot be reopened: the issue is
	// immutable and its price would then trace back to a figure that no longer says
	// what it said. Record a NEW valuation instead — the history is the point.
	$used = bhela_bm_valuation_used_by_issue( $id );
	if ( $used ) {
		return new WP_Error(
			'in_use',
			sprintf(
				/* translators: %d: share issue id */
				__( 'শেয়ার ইস্যু #%d এই মূল্যায়ন থেকে হিসাব করা হয়েছে, তাই এটি আর পরিবর্তন করা যাবে না। নতুন মূল্যায়ন রেকর্ড করুন।', 'bhela-booking' ),
				$used
			)
		);
	}

	update_post_meta( $id, '_bhela_val_status', 'draft' );
	bhela_bm_valuation_current( true );   // and again — the screens fall back now

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'valuation_reopen',
		'object_type' => 'valuation',
		'object_id'   => $id,
		'object_ref'  => $v['date'],
		'field'       => 'status',
		'old_value'   => 'approved',
		'new_value'   => 'draft',
		'reason'      => sanitize_textarea_field( $reason ),
	) );
	return true;
}

/* =========================================================
 * THE READERS
 * ========================================================= */

/** How many valuations one listing will read. Filterable. */
function bhela_bm_valuation_limit() {
	return (int) apply_filters( 'bhela_bm_valuation_limit', 200 );
}

/**
 * Every valuation, newest date first, with growth against its predecessor.
 *
 * @param bool $approved_only Drop drafts.
 * @return array
 */
function bhela_bm_valuation_history( $approved_only = false ) {
	$ids = get_posts( array(
		'post_type'      => 'bhela_valuation',
		'post_status'    => 'publish',
		// Capped, and the cap is logged when it bites rather than passed over — a
		// history that quietly stops at row 200 looks complete. Only the LISTING is
		// capped; nothing sums this list, and `bhela_bm_valuation_current()` wants the
		// newest row, which is always in the first page.
		'posts_per_page' => bhela_bm_valuation_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_val_date',
		'orderby'        => array( 'meta_value' => 'DESC', 'ID' => 'DESC' ),
	) );

	if ( count( $ids ) >= bhela_bm_valuation_limit() && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'investor', sprintf(
			/* translators: %d: the cap */
			'Valuation history hit its cap of %d records — older valuations are not listed.',
			bhela_bm_valuation_limit()
		) );
	}

	$rows = array();
	foreach ( $ids as $id ) {
		$v = bhela_bm_valuation( $id );
		if ( ! $v ) {
			continue;
		}
		if ( $approved_only && 'approved' !== $v['status'] ) {
			continue;
		}
		$rows[] = $v;
	}

	// Growth is measured against the previous APPROVED valuation, whatever drafts sit
	// between them — a draft is nobody's baseline. The first record on the books
	// compares against the initial equity value in settings, which is what
	// `inv_total_investment` is for; it had no reader at all before this.
	$cfg     = bhela_bm_share_config();
	$approved = array_values( array_filter( $rows, function ( $r ) {
		return 'approved' === $r['status'];
	} ) );

	foreach ( $rows as $i => $row ) {
		$prev = null;
		foreach ( $approved as $cand ) {
			if ( $cand['id'] === $row['id'] ) {
				continue;
			}
			if ( $cand['date'] < $row['date'] || ( $cand['date'] === $row['date'] && $cand['id'] < $row['id'] ) ) {
				$prev = $cand;
				break;      // the list is date-descending, so the first match is nearest
			}
		}
		$base = $prev ? $prev['total'] : (int) $cfg['total_investment'];
		$rows[ $i ]['prev_id']    = $prev ? $prev['id'] : 0;
		$rows[ $i ]['prev_total'] = $base;
		$rows[ $i ]['growth']     = $base > 0
			? round( ( $row['total'] - $base ) / $base * 100, 2 )
			: 0.0;
	}
	return $rows;
}

/**
 * The valuation in force — the most recent APPROVED one, or null.
 *
 * Cached per request because five surfaces ask for it and each would otherwise run the
 * same query. Not cached across requests: an approval must show up immediately, and a
 * transient here would mean an investor seeing a stale holding value on the one day it
 * matters.
 *
 * @param bool $reset Drop the cache — after an approval in the same request, and in
 *                    the harnesses, which approve and then immediately assert.
 */
function bhela_bm_valuation_current( $reset = false ) {
	static $cache = null;
	if ( $reset ) {
		// Reset and re-read, rather than returning null: `$v = bhela_bm_valuation_current(
		// true )` reads as "give me the current one, freshly" and silently yielded
		// nothing.
		$cache = null;
	}
	if ( null !== $cache ) {
		return $cache ? $cache : null;
	}
	$approved = bhela_bm_valuation_history( true );
	$cache    = $approved ? $approved[0] : false;
	return $cache ? $cache : null;
}

/**
 * What one share is worth today, in whole taka.
 *
 * **The fallback is the whole compatibility story.** With no approved valuation on
 * record this returns `inv_per_share` — the historic issue price, ৳1,00,000 — so a site
 * that never records a valuation shows exactly the figures it showed before this
 * feature existed. Nothing is invented from an absence.
 *
 * @param array|null $valuation Pass one to price against a specific valuation.
 * @return int
 */
function bhela_bm_share_value( $valuation = null ) {
	$v = null === $valuation ? bhela_bm_valuation_current() : $valuation;
	if ( $v && ! empty( $v['per_share'] ) ) {
		return (int) $v['per_share'];
	}
	$cfg = bhela_bm_share_config();
	return (int) $cfg['per_share'];
}

/**
 * One investor's capital position — cost basis, current value, and the gap.
 *
 * Deliberately separate from `bhela_bm_investor_roi()`, which answers a different
 * question: that one is about cash that has moved, this one is about value that has
 * not. Keeping them apart is what stops a screen adding an unrealised gain to money
 * already in somebody's hand and calling the sum a return.
 *
 * @param int        $id        Investor.
 * @param array|null $valuation Optional explicit valuation.
 * @return array
 */
function bhela_bm_investor_holding( $id, $valuation = null ) {
	$v      = null === $valuation ? bhela_bm_valuation_current() : $valuation;
	$shares = bhela_bm_investor_shares( $id );
	$basis  = bhela_bm_investor_amount( $id );
	$price  = bhela_bm_share_value( $v );
	$value  = $shares * $price;

	return array(
		'shares'       => $shares,
		'pct'          => bhela_bm_investor_share_pct( $id ),
		// What they paid in — the figure every existing screen already calls "Invested".
		'basis'        => $basis,
		'share_value'  => $price,
		'holding'      => $value,
		// Can be negative, and is shown as such. A valuation that fell is the single
		// most important thing this screen can tell somebody.
		'appreciation' => $value - $basis,
		'appr_pct'     => $basis > 0 ? round( ( $value - $basis ) / $basis * 100, 2 ) : 0.0,
		// Whether any of this rests on a real valuation or on the issue-price fallback.
		// Every surface checks this before it claims a current value.
		'valued'       => (bool) $v,
		'valuation'    => $v ? $v['id'] : 0,
		'as_at'        => $v ? $v['date'] : '',
	);
}

/**
 * Every investor's capital position, plus the totals. One valuation read, not N.
 *
 * **The holdings do not add up to the valuation, and the two reasons are named here
 * rather than left to be discovered.** A share price is a whole number of taka, so
 * `shares × per_share` loses the remainder — ৳1.70 Cr over 115 shares is ৳1,47,826.09
 * a share, and 115 × ৳1,47,826 is ten taka short of the valuation. And unissued shares
 * belong to nobody, so their portion is not in anybody's holding either.
 *
 * Rounding per share rather than allocating by largest remainder is deliberate: an
 * investor can check `10 × ৳1,47,826` on a calculator and get the number on their
 * statement, which is worth more than a total that reconciles to the taka. The gap is
 * reported so nothing looks unexplained.
 */
function bhela_bm_holding_totals() {
	$v   = bhela_bm_valuation_current();
	$out = array(
		'valued'       => (bool) $v,
		'as_at'        => $v ? $v['date'] : '',
		'total'        => $v ? $v['total'] : 0,
		'share_value'  => bhela_bm_share_value( $v ),
		'basis'        => 0,
		'holding'      => 0,
		'appreciation' => 0,
		'unissued'     => 0,
		'rounding'     => 0,
		'stale'        => false,
		'issued_since' => 0,
		'held'         => 0,
		'shares'       => 0,
		'rows'         => array(),
	);
	$held = 0;
	foreach ( bhela_bm_investors() as $id ) {
		$h = bhela_bm_investor_holding( $id, $v );
		$held                += $h['shares'];
		$out['basis']        += $h['basis'];
		$out['holding']      += $h['holding'];
		$out['appreciation'] += $h['appreciation'];
		$out['rows'][ $id ]   = $h;
	}

	$cfg = bhela_bm_share_config();
	$out['held']   = $held;
	$out['shares'] = (int) $cfg['total_shares'];

	// **The reconciliation only means anything while the valuation's own share count
	// still matches the register's.** A valuation is PRE-MONEY: issue shares after it
	// and the business is worth the post-money figure, which no record yet states. So
	// rather than compute a remainder against a total that has moved — which produced a
	// nonsense "rounding" of minus ten lakh — say the valuation is out of date and ask
	// for a new one. That is the honest answer and it is also the useful one.
	$out['stale'] = $v && (int) $v['shares'] !== (int) $cfg['total_shares'];
	$out['issued_since'] = $out['stale'] ? (int) $cfg['total_shares'] - (int) $v['shares'] : 0;

	if ( $v && ! $out['stale'] ) {
		$gap             = max( 0, (int) $cfg['total_shares'] - $held );
		$out['unissued'] = $gap * $out['share_value'];
		// Whatever is left once the issued and the unissued shares are priced. Small by
		// construction — at most one taka per share.
		$out['rounding'] = (int) $v['total'] - $out['holding'] - $out['unissued'];
	}
	return $out;
}
