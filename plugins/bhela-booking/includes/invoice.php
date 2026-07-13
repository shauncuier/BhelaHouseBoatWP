<?php
/**
 * Invoice system: numbering, secure links, printable rendering.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generate next invoice number, e.g. BH-2026-0007 */
function bhela_bm_next_invoice_number() {
	$settings = bhela_bm_get_settings();
	$counter  = (int) get_option( 'bhela_bm_invoice_counter', 0 ) + 1;
	update_option( 'bhela_bm_invoice_counter', $counter );
	return sprintf( '%s-%s-%04d', $settings['invoice_prefix'], date( 'Y' ), $counter );
}

/** Secret key for a booking's public invoice link (full 128-bit wp_hash). */
function bhela_bm_invoice_key( $booking_id ) {
	return wp_hash( 'bhela-invoice-' . $booking_id . get_post_field( 'post_date', $booking_id ) );
}

/** Public (secret) invoice URL — safe to send to the customer. */
function bhela_bm_invoice_url( $booking_id ) {
	return add_query_arg( array(
		'bhela_invoice' => (int) $booking_id,
		'key'           => bhela_bm_invoice_key( $booking_id ),
	), home_url( '/' ) );
}

/** Render the invoice when the link is visited. */
function bhela_bm_maybe_render_invoice() {
	if ( empty( $_GET['bhela_invoice'] ) ) {
		return;
	}
	$booking_id = (int) $_GET['bhela_invoice'];
	$post       = get_post( $booking_id );

	if ( ! $post || 'bhela_booking' !== $post->post_type ) {
		wp_die( esc_html__( 'Invoice not found.', 'bhela-booking' ), 404 );
	}

	$key_ok   = isset( $_GET['key'] ) && hash_equals( bhela_bm_invoice_key( $booking_id ), (string) $_GET['key'] );
	$admin_ok = current_user_can( 'edit_post', $booking_id );

	if ( ! $key_ok && ! $admin_ok ) {
		wp_die( esc_html__( 'You are not allowed to view this invoice.', 'bhela-booking' ), 403 );
	}

	$settings = bhela_bm_get_settings();
	$m        = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};

	$invoice = array(
		'settings'    => $settings,
		'booking_id'  => $booking_id,
		'invoice_no'  => $m( '_bhela_invoice_no' ),
		'created'     => get_post_field( 'post_date', $booking_id ),
		'name'        => get_the_title( $booking_id ),
		'phone'       => $m( '_bhela_phone' ),
		'email'       => $m( '_bhela_email' ),
		'travel_date' => $m( '_bhela_travel_date' ),
		'cabin'       => $m( '_bhela_cabin_type' ),
		'guests'      => (int) $m( '_bhela_guests' ),
		'day_type'    => $m( '_bhela_day_type' ),
		'per_person'  => (int) $m( '_bhela_per_person' ),
		'total'       => (int) $m( '_bhela_total' ),
		'advance'     => (int) $m( '_bhela_advance' ),
		'paid'        => (int) $m( '_bhela_paid_amount' ),
		'pay_method'  => $m( '_bhela_pay_method' ),
		'txn_id'      => $m( '_bhela_txn_id' ),
		'status'      => $m( '_bhela_status' ) ? $m( '_bhela_status' ) : 'pending',
		'message'     => $m( '_bhela_message' ),
	);

	include BHELA_BM_PATH . 'templates/invoice.php';
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_invoice' );
