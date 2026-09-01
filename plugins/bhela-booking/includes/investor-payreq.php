<?php
/**
 * Investor payment requests — the second signature before money moves.
 *
 * Cost sheets already require prepare → check → approve before a taka counts toward
 * anything. Paying a named person needed no second signature at all, which made it the
 * weakest link in a chain that is otherwise careful everywhere else.
 *
 * **This is a separate record, not a status on a ledger row.** The ledger is
 * append-only and its rows are immutable by design — that is the single property that
 * makes it worth trusting a year later. Bolting mutable approval state onto a row
 * would destroy it. So a request carries the workflow, and a ledger row is written
 * once, at the moment of approval:
 *
 *     requested  →  approved  →  ledger row written
 *          ↘  rejected (no row, ever)
 *
 * The ledger therefore keeps meaning exactly what it meant before: what actually
 * moved. A pending request appears on the investor screen and the dashboard as
 * awaiting approval, and in no balance, no position and no ROI — because nothing has
 * been paid yet.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_register_payreq_cpt() {
	register_post_type( 'bhela_payreq', array(
		'labels'              => array( 'name' => __( 'Payment Requests', 'bhela-booking' ) ),
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
add_action( 'init', 'bhela_bm_register_payreq_cpt' );

/** requested | approved | rejected. */
function bhela_bm_payreq_states() {
	return array(
		'requested' => array( 'label' => __( 'Awaiting approval', 'bhela-booking' ), 'tone' => 'attention' ),
		'approved'  => array( 'label' => __( 'Approved & paid', 'bhela-booking' ),   'tone' => 'good' ),
		'rejected'  => array( 'label' => __( 'Rejected', 'bhela-booking' ),          'tone' => 'danger' ),
		// A record with no state meta at all. It cannot be claimed and so cannot be
		// approved or rejected; saying so is better than rendering a blank pill or
		// falling back to a state that implies it is actionable.
		''          => array( 'label' => __( 'Unreadable — contact the office', 'bhela-booking' ), 'tone' => 'neutral' ),
	);
}

/**
 * Raise a request. Writes no ledger row and moves no money.
 *
 * @param array $args investor, type (payment|advance), amount, date, method,
 *                    reference, doc, note.
 * @return int|WP_Error
 */
function bhela_bm_payreq_add( $args ) {
	if ( ! current_user_can( 'bhela_investor_pay' ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$investor = (int) ( $args['investor'] ?? 0 );
	$type     = (string) ( $args['type'] ?? '' );
	$amount   = (int) ( $args['amount'] ?? 0 );

	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	// An exited investor has been settled and has left. A payment raised against one
	// is either a mistake or a final settlement that belongs on the record as an
	// adjustment, with a reason attached — not as a routine payment nobody questions.
	if ( 'exited' === bhela_bm_investor_status( $investor ) ) {
		return new WP_Error(
			'exited',
			__( 'এই বিনিয়োগকারী প্রত্যাহৃত। প্রয়োজনে সমন্বয় (adjustment) হিসেবে কারণ লিখে রেকর্ড করুন।', 'bhela-booking' )
		);
	}
	// Only money OUT goes through approval. A profit row comes from a distribution and
	// an adjustment is a correction with its own reversal trail; neither is somebody
	// deciding to hand over cash.
	if ( ! in_array( $type, array( 'payment', 'advance' ), true ) ) {
		return new WP_Error( 'bad_type', __( 'ধরন সঠিক নয়।', 'bhela-booking' ) );
	}
	if ( $amount <= 0 ) {
		return new WP_Error( 'bad_amount', __( 'পরিমাণ শূন্যের বেশি হতে হবে।', 'bhela-booking' ) );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'bhela_payreq',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s %s %d', get_the_title( $investor ), $type, $amount ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$date = bhela_bm_report_date( $args['date'] ?? '' );
	foreach ( array(
		'investor'  => $investor,
		'type'      => $type,
		'amount'    => $amount,
		'date'      => $date ? $date : current_time( 'Y-m-d' ),
		'method'    => sanitize_text_field( $args['method'] ?? '' ),
		'reference' => sanitize_text_field( $args['reference'] ?? '' ),
		'doc'       => esc_url_raw( $args['doc'] ?? '' ),
		'note'      => sanitize_textarea_field( $args['note'] ?? '' ),
		'state'     => 'requested',
		'by'        => get_current_user_id(),
		'at'        => current_time( 'mysql' ),
		'ledger'    => 0,
	) as $k => $v ) {
		update_post_meta( $id, '_bhela_pr_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'pay_request',
		'object_type' => 'payreq',
		'object_id'   => $id,
		'object_ref'  => get_the_title( $investor ),
		'field'       => 'amount',
		'new_value'   => (string) $amount,
		'reason'      => sanitize_textarea_field( $args['note'] ?? '' ),
	) );

	return $id;
}

/** How many requests a single listing will read. Filterable. */
function bhela_bm_payreq_limit() {
	return (int) apply_filters( 'bhela_bm_payreq_limit', 500 );
}

/** One request. */
function bhela_bm_payreq( $id ) {
	if ( 'bhela_payreq' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_pr_' . $k, true );
	};
	return array(
		'id'        => (int) $id,
		'investor'  => (int) $m( 'investor' ),
		'type'      => (string) $m( 'type' ),
		'amount'    => (int) $m( 'amount' ),
		'date'      => (string) $m( 'date' ),
		'method'    => (string) $m( 'method' ),
		'reference' => (string) $m( 'reference' ),
		'doc'       => (string) $m( 'doc' ),
		'note'      => (string) $m( 'note' ),
		// NOT defaulted to 'requested': the conditional UPDATE that claims a request
		// can only match a row that exists, so a record with no state meta would read
		// as claimable and never be claimable — permanently stuck. '' says so, and
		// every caller treats it as unsettled-but-not-actionable.
		'state'     => (string) $m( 'state' ),
		'by'        => (int) $m( 'by' ),
		'at'        => (string) $m( 'at' ),
		'decided_by' => (int) $m( 'decided_by' ),
		'decided_at' => (string) $m( 'decided_at' ),
		'reason'    => (string) $m( 'reason' ),
		'ledger'    => (int) $m( 'ledger' ),
	);
}

/**
 * Requests, newest first.
 *
 * @param string $state    Limit to one state, or '' for all.
 * @param int    $investor Limit to one investor, or 0 for all.
 */
function bhela_bm_payreqs( $state = '', $investor = 0 ) {
	$meta = array();
	if ( $state ) {
		$meta[] = array( 'key' => '_bhela_pr_state', 'value' => $state );
	}
	if ( $investor ) {
		$meta[] = array( 'key' => '_bhela_pr_investor', 'value' => (int) $investor );
	}
	$args = array(
		'post_type'      => 'bhela_payreq',
		'post_status'    => 'publish',
		// Capped rather than -1. The dashboard reads the pending total on every load,
		// and requests accumulate for the life of the business. 500 is far above any
		// real backlog, and hitting it is logged rather than passed over in silence.
		//
		// The CAP IS FOR THE LISTING ONLY. bhela_bm_payreq_pending_total() counts in
		// SQL instead, because a truncated total understates money owed — which is a
		// worse failure than a slow screen, and exactly what capping this was meant
		// to avoid rather than cause.
		'posts_per_page' => bhela_bm_payreq_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	);
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	$out = array();
	$ids = get_posts( $args );
	foreach ( $ids as $id ) {
		$r = bhela_bm_payreq( $id );
		if ( $r ) {
			$out[] = $r;
		}
	}
	if ( count( $ids ) >= bhela_bm_payreq_limit() && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'investor', sprintf(
			/* translators: %d: the cap */
			'Payment request listing hit its cap of %d — older requests are not shown.',
			bhela_bm_payreq_limit()
		) );
	}
	return $out;
}

/**
 * Approve a request — and only now write the ledger row.
 *
 * @return int|WP_Error The ledger row id.
 */
function bhela_bm_payreq_approve( $id ) {
	if ( ! current_user_can( 'bhela_investor_approve' ) ) {
		return new WP_Error( 'denied', __( 'অনুমোদনের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$r = bhela_bm_payreq( $id );
	if ( ! $r ) {
		return new WP_Error( 'no_req', __( 'এই অনুরোধ পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'requested' !== $r['state'] ) {
		return new WP_Error( 'settled', __( 'এই অনুরোধ আগেই নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}
	// The person who raised it cannot be the person who approves it. A second
	// signature the same hand can supply is not a second signature.
	if ( (int) $r['by'] === get_current_user_id() ) {
		return new WP_Error( 'same_person', __( 'যিনি অনুরোধ করেছেন তিনি নিজে অনুমোদন করতে পারবেন না।', 'bhela-booking' ) );
	}
	// Belt to the braces on the state check above: a request that already carries a
	// ledger row has been paid, whatever its state says.
	if ( $r['ledger'] > 0 ) {
		return new WP_Error( 'paid', __( 'এই অনুরোধের বিপরীতে পেমেন্ট ইতিমধ্যে রেকর্ড হয়েছে।', 'bhela-booking' ) );
	}

	// **Claim the request before writing anything.**
	//
	// The `'requested' !== $r['state']` check above is a read, and two approvals
	// arriving together both pass it — then both write a ledger row, and the investor
	// is paid twice for one request. Nothing in the ledger would look wrong
	// afterwards, because both rows are individually valid.
	//
	// This is a single conditional UPDATE, so the database decides the winner: the
	// row is locked for the duration and only one statement can find `requested`
	// there. `rows_affected` of 1 means we hold the claim. It is the same discipline
	// as the `add_option()` mutex behind one-distribution-per-month — the difference
	// is that post meta has no unique index to lean on, so the condition goes in the
	// WHERE clause instead.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->postmeta} SET meta_value = 'approved'
		 WHERE post_id = %d AND meta_key = '_bhela_pr_state' AND meta_value = 'requested'",
		$id
	) );
	// The UPDATE went round the meta API, so the cached value is now stale.
	wp_cache_delete( $id, 'post_meta' );
	// false is a database error, not a lost race. Reporting it as "already settled"
	// would send somebody looking for an approval that never happened.
	if ( false === $claimed ) {
		return new WP_Error( 'db', __( 'অনুরোধটি লক করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}
	// > 0 rather than === 1: a duplicated meta row would update two and strand the
	// request as approved with no payment behind it and no rollback.
	if ( (int) $claimed < 1 ) {
		return new WP_Error( 'settled', __( 'এই অনুরোধ আগেই নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}

	$row = bhela_bm_ledger_add( array(
		'investor' => $r['investor'],
		'type'     => $r['type'],
		'amount'   => $r['amount'],
		'date'     => $r['date'],
		'method'   => $r['method'],
		'note'     => $r['note'] ? $r['note'] : sprintf(
			/* translators: %d: request id */
			__( 'পেমেন্ট অনুরোধ #%d অনুমোদিত', 'bhela-booking' ),
			$r['id']
		),
	) );
	if ( is_wp_error( $row ) ) {
		// Hand the claim back. Leaving it 'approved' with no ledger row would show
		// the investor a payment that never happened and could never be reversed,
		// because there is nothing to reverse.
		update_post_meta( $id, '_bhela_pr_state', 'requested' );
		// And say so in the trail. An approval that was attempted and did not stick
		// is exactly the kind of thing somebody asks about later, and the trail is
		// the store that still has an answer.
		bhela_bm_audit( array(
			'channel'     => 'investor',
			'action'      => 'pay_approve_failed',
			'object_type' => 'payreq',
			'object_id'   => $id,
			'object_ref'  => get_the_title( $r['investor'] ),
			'field'       => 'state',
			'old_value'   => 'approved',
			'new_value'   => 'requested',
			'reason'      => $row->get_error_message(),
		) );
		return $row;
	}

	update_post_meta( $id, '_bhela_pr_decided_by', get_current_user_id() );
	update_post_meta( $id, '_bhela_pr_decided_at', current_time( 'mysql' ) );
	update_post_meta( $id, '_bhela_pr_ledger', (int) $row );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'pay_approve',
		'object_type' => 'payreq',
		'object_id'   => $id,
		'object_ref'  => get_the_title( $r['investor'] ),
		'field'       => 'state',
		'old_value'   => 'requested',
		'new_value'   => 'approved',
		'approval_ref' => (string) $row,
	) );

	return (int) $row;
}

/** Reject a request. No ledger row is ever written. */
function bhela_bm_payreq_reject( $id, $reason ) {
	if ( ! current_user_can( 'bhela_investor_approve' ) ) {
		return new WP_Error( 'denied', __( 'অনুমোদনের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$r = bhela_bm_payreq( $id );
	if ( ! $r ) {
		return new WP_Error( 'no_req', __( 'এই অনুরোধ পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'requested' !== $r['state'] ) {
		return new WP_Error( 'settled', __( 'এই অনুরোধ আগেই নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}
	if ( '' === trim( (string) $reason ) ) {
		return new WP_Error( 'no_reason', __( 'কারণ লিখুন।', 'bhela-booking' ) );
	}
	// The same conditional claim the approval takes, so an approve and a reject
	// arriving together cannot both succeed and leave a paid-but-rejected request.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->postmeta} SET meta_value = 'rejected'
		 WHERE post_id = %d AND meta_key = '_bhela_pr_state' AND meta_value = 'requested'",
		$id
	) );
	wp_cache_delete( $id, 'post_meta' );
	if ( false === $claimed ) {
		return new WP_Error( 'db', __( 'অনুরোধটি লক করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}
	if ( (int) $claimed < 1 ) {
		return new WP_Error( 'settled', __( 'এই অনুরোধ আগেই নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}
	update_post_meta( $id, '_bhela_pr_decided_by', get_current_user_id() );
	update_post_meta( $id, '_bhela_pr_decided_at', current_time( 'mysql' ) );
	update_post_meta( $id, '_bhela_pr_reason', sanitize_textarea_field( $reason ) );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'pay_reject',
		'object_type' => 'payreq',
		'object_id'   => $id,
		'object_ref'  => get_the_title( $r['investor'] ),
		'field'       => 'state',
		'old_value'   => 'requested',
		'new_value'   => 'rejected',
		'reason'      => sanitize_textarea_field( $reason ),
	) );
	return true;
}

/**
 * What is waiting on somebody, across every investor.
 *
 * Counted in SQL rather than by summing bhela_bm_payreqs(), which is capped: past the
 * cap that summing would quietly UNDERSTATE the money owed, and this figure is the
 * headline on the dashboard. It is also cheaper — two aggregates against the meta
 * table instead of 500 get_post_meta() round trips on every admin page load.
 */
function bhela_bm_payreq_pending_total() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row(
		"SELECT COUNT(*) AS n, COALESCE( SUM( amt.meta_value + 0 ), 0 ) AS total
		 FROM {$wpdb->postmeta} st
		 INNER JOIN {$wpdb->posts} p ON p.ID = st.post_id AND p.post_status = 'publish'
		 LEFT JOIN {$wpdb->postmeta} amt ON amt.post_id = st.post_id AND amt.meta_key = '_bhela_pr_amount'
		 WHERE st.meta_key = '_bhela_pr_state' AND st.meta_value = 'requested'",
		ARRAY_A
	);
	return array(
		'count' => (int) ( $row['n'] ?? 0 ),
		'total' => (int) ( $row['total'] ?? 0 ),
	);
}
