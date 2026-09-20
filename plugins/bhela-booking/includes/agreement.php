<?php
/**
 * The Investment Agreement — a reference and a signed file, never generated wording.
 *
 * The client's brief is explicit about this and it is the right instruction: whether
 * BHELA's arrangement is a share, a partnership interest, a deposit or a profit-sharing
 * contract decides what the document must say, and that is a question for their legal
 * and accounting advisers rather than for code (§25 of the brief). Generating contract
 * text from a template nobody has approved would produce a document that looks official
 * and binds nobody.
 *
 * So this module does two things and refuses the third:
 *
 * - It **records** an agreement: reference, date, parties, the signed scan.
 * - It lets an investment and a certificate **cite** that reference.
 * - It does **not** compose agreement wording. When BHELA's approved template exists,
 *   filling it from these fields is a small addition; inventing it is not.
 *
 * The file goes through `bhela_bm_investor_upload()`, so it inherits every rule §13.69
 * settled: the mime type is checked by CONTENT against an explicit list, the stored name
 * is random because `wp-content/uploads` is served straight off disk with no capability
 * check, and a signed agreement is exactly the kind of document that must not be
 * guessable from a URL.
 *
 * Locked from the moment it exists. An agreement that could be edited after an
 * investment cites it is not much of a reference.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_register_agreement_cpt() {
	register_post_type( 'bhela_agreement', array(
		'labels' => array(
			'name'          => __( 'Agreements', 'bhela-booking' ),
			'singular_name' => __( 'Agreement', 'bhela-booking' ),
		),
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
add_action( 'init', 'bhela_bm_register_agreement_cpt' );

/** Signed and in force, or superseded by a later one. Never deleted. */
function bhela_bm_agreement_states() {
	return array(
		'signed'     => array( 'label' => __( 'স্বাক্ষরিত', 'bhela-booking' ), 'tone' => 'good' ),
		'superseded' => array( 'label' => __( 'প্রতিস্থাপিত', 'bhela-booking' ), 'tone' => 'neutral' ),
		'ended'      => array( 'label' => __( 'সমাপ্ত', 'bhela-booking' ), 'tone' => 'neutral' ),
	);
}

function bhela_bm_agreement_ref_exists( $number ) {
	return bhela_bm_series_taken( 'bhela_agreement', '_bhela_agr_ref', $number );
}

/** BHELA-AGR-2026-0001. */
function bhela_bm_agreement_ref() {
	return bhela_bm_series_number( 'AGR', 'bhela_bm_agreement_ref_exists' );
}

/** How many agreements one listing reads. Filterable. */
function bhela_bm_agreement_limit() {
	return (int) apply_filters( 'bhela_bm_agreement_limit', 500 );
}

/**
 * Record a signed agreement.
 *
 * @param array $args investor, date, parties, file, note.
 * @return int|WP_Error
 */
function bhela_bm_agreement_add( $args ) {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		return new WP_Error( 'denied', __( 'চুক্তি রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}

	$investor = (int) ( $args['investor'] ?? 0 );
	$date     = bhela_bm_report_date( $args['date'] ?? '' );

	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( '' === $date ) {
		// The date an agreement was signed is the one fact a reference is worth
		// nothing without, so it is refused rather than defaulted to today.
		return new WP_Error( 'no_date', __( 'চুক্তির তারিখ লিখুন।', 'bhela-booking' ) );
	}

	$ref = bhela_bm_agreement_ref();
	$id  = wp_insert_post( array(
		'post_type'   => 'bhela_agreement',
		'post_status' => 'publish',
		'post_title'  => $ref . ' — ' . get_the_title( $investor ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	// Locked from birth, so even the first write goes through the lock's own window.
	foreach ( array(
		'ref'      => $ref,
		'investor' => $investor,
		'date'     => $date,
		'parties'  => sanitize_text_field( $args['parties'] ?? '' ),
		'file'     => esc_url_raw( $args['file'] ?? '' ),
		'note'     => sanitize_textarea_field( $args['note'] ?? '' ),
		'by'       => get_current_user_id(),
		'at'       => current_time( 'mysql' ),
	) as $k => $v ) {
		bhela_bm_val_meta_write( $id, '_bhela_agr_' . $k, $v );
	}
	// Outside the lock, because an agreement can legitimately be superseded later.
	update_post_meta( $id, '_bhela_agr_status', 'signed' );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'agreement_add',
		'object_type' => 'agreement',
		'object_id'   => (int) $id,
		'object_ref'  => $ref,
		'field'       => 'date',
		'new_value'   => $date,
		'reason'      => sanitize_textarea_field( $args['note'] ?? '' ),
	) );

	return (int) $id;
}

/** Mark an agreement superseded or ended. The file and the reference stay. */
function bhela_bm_agreement_status( $id, $status, $reason = '' ) {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		return new WP_Error( 'denied', __( 'চুক্তি রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( 'bhela_agreement' !== get_post_type( $id ) ) {
		return new WP_Error( 'not_found', __( 'চুক্তি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	$status = sanitize_key( $status );
	$states = bhela_bm_agreement_states();
	if ( ! isset( $states[ $status ] ) ) {
		return new WP_Error( 'bad_state', __( 'এই অবস্থা সঠিক নয়।', 'bhela-booking' ) );
	}

	$old = (string) get_post_meta( $id, '_bhela_agr_status', true );
	update_post_meta( $id, '_bhela_agr_status', $status );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'agreement_status',
		'object_type' => 'agreement',
		'object_id'   => (int) $id,
		'object_ref'  => (string) get_post_meta( $id, '_bhela_agr_ref', true ),
		'field'       => 'status',
		'old_value'   => $old,
		'new_value'   => $status,
		'reason'      => sanitize_textarea_field( $reason ),
	) );
	return true;
}

/** One agreement, or null. */
function bhela_bm_agreement( $id ) {
	if ( 'bhela_agreement' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_agr_' . $k, true );
	};
	$investor = (int) $m( 'investor' );
	$status   = (string) $m( 'status' );
	$states   = bhela_bm_agreement_states();

	return array(
		'id'           => (int) $id,
		'ref'          => (string) $m( 'ref' ),
		'investor'     => $investor,
		'name'         => $investor ? get_the_title( $investor ) : '',
		'date'         => (string) $m( 'date' ),
		'parties'      => (string) $m( 'parties' ),
		'file'         => (string) $m( 'file' ),
		'note'         => (string) $m( 'note' ),
		'status'       => isset( $states[ $status ] ) ? $status : 'signed',
		'status_label' => $states[ $status ]['label'] ?? $status,
		'by'           => (int) $m( 'by' ),
		'at'           => (string) $m( 'at' ),
	);
}

/**
 * Agreements, newest first.
 *
 * @param int $investor Investor post id, or 0 for everybody.
 * @return array[]
 */
function bhela_bm_agreements( $investor = 0 ) {
	$query = array(
		'post_type'      => 'bhela_agreement',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_agreement_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	);
	if ( $investor ) {
		$query['meta_key']   = '_bhela_agr_investor';
		$query['meta_value'] = (int) $investor;
	}
	$out = array();
	foreach ( get_posts( $query ) as $id ) {
		$r = bhela_bm_agreement( $id );
		if ( $r ) {
			$out[] = $r;
		}
	}
	return $out;
}
