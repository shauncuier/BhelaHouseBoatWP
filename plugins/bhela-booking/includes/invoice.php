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

	// This page carries a guest's name, phone, email and payment details behind
	// nothing but a query string. Most BD hosting runs a page cache (LiteSpeed,
	// WP Rocket) that keys on the URL and, depending on its configuration, may
	// ignore query args entirely — which would let it serve one guest's invoice
	// to the next visitor. Say no-store explicitly rather than trusting that.
	// The template also carries a robots meta tag; the header covers the case
	// where a crawler reads headers but not the body.
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$invoice = bhela_bm_invoice_data( $booking_id );

	include BHELA_BM_PATH . 'templates/invoice.php';
	exit;
}

/**
 * Everything the printable template needs, for one booking.
 *
 * Split out of bhela_bm_maybe_render_invoice() so the invoice can be rendered
 * without a request that ends in exit() — the render path is the only place the
 * PAID stamp and the day-type label can actually be proved.
 *
 * @param int $booking_id Booking post ID.
 * @return array
 */
function bhela_bm_invoice_data( $booking_id ) {
	$settings = bhela_bm_get_settings();
	$m        = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};

	return array(
		'settings'    => $settings,
		'booking_id'  => $booking_id,
		'invoice_no'  => $m( '_bhela_invoice_no' ),
		'created'     => get_post_field( 'post_date', $booking_id ),
		'name'        => get_the_title( $booking_id ),
		'phone'       => $m( '_bhela_phone' ),
		'email'       => $m( '_bhela_email' ),
		// The guest's own address, not the business one in the header. It was added
		// to the booking, the admin screen, the public form and the confirmation
		// message in v2.30.0 and missed here, so the invoice printed no address at
		// all while every other surface had one.
		'address'     => $m( '_bhela_address' ),
		'travel_date' => $m( '_bhela_travel_date' ),
		'cabin'       => $m( '_bhela_cabin_type' ),
		'guests'      => (int) $m( '_bhela_guests' ),
		// Derived from the travel date, never read raw: the stored meta is a cache
		// and it has been stale in production. See bhela_bm_booking_day_type().
		'day_type'    => bhela_bm_booking_day_type( $booking_id ),
		'per_person'  => (int) $m( '_bhela_per_person' ),
		'lines'       => is_array( json_decode( (string) $m( '_bhela_lines' ), true ) ) ? json_decode( (string) $m( '_bhela_lines' ), true ) : array(),
		'total'       => (int) $m( '_bhela_total' ),
		'base_price'  => (int) $m( '_bhela_base_price' ),
		'advance'     => (int) $m( '_bhela_advance' ),
		'paid'        => (int) $m( '_bhela_paid_amount' ),
		'pay_method'  => $m( '_bhela_pay_method' ),
		'txn_id'      => $m( '_bhela_txn_id' ),
		'status'      => $m( '_bhela_status' ) ? $m( '_bhela_status' ) : 'pending',
		'message'     => $m( '_bhela_message' ),
	);
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_invoice' );
