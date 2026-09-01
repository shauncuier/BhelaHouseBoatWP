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
		'state'     => (string) $m( 'state' ) ?: 'requested',
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
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	);
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	$out = array();
	foreach ( get_posts( $args ) as $id ) {
		$r = bhela_bm_payreq( $id );
		if ( $r ) {
			$out[] = $r;
		}
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
		return $row;
	}

	update_post_meta( $id, '_bhela_pr_state', 'approved' );
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
	update_post_meta( $id, '_bhela_pr_state', 'rejected' );
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

/** What is waiting on somebody, across every investor. */
function bhela_bm_payreq_pending_total() {
	$out = array( 'count' => 0, 'total' => 0 );
	foreach ( bhela_bm_payreqs( 'requested' ) as $r ) {
		$out['count']++;
		$out['total'] += $r['amount'];
	}
	return $out;
}
