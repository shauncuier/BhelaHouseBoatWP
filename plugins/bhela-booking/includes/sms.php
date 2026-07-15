<?php
/**
 * SMS notifications — provider-agnostic HTTP sender.
 *
 * Works with any Bangladesh gateway (BulkSMSBD preset, or a fully custom
 * URL/param mapping). Fires on new bookings (admin + customer) and on status
 * changes (customer). All sends are best-effort: a gateway failure is logged
 * and never blocks the booking or the email.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Normalise a BD mobile number to 8801XXXXXXXXX (digits only). */
function bhela_bm_sms_number( $raw ) {
	$digits = preg_replace( '/[^0-9]/', '', (string) $raw );
	if ( '' === $digits ) {
		return '';
	}
	if ( 0 === strpos( $digits, '880' ) ) {
		return $digits;
	}
	if ( 0 === strpos( $digits, '0' ) ) {
		return '88' . $digits;               // 01712… → 8801712…
	}
	if ( 11 === strlen( $digits ) ) {
		return '880' . $digits;              // rare 1712… form
	}
	return $digits;
}

/** Fill {placeholders} in a template from a booking's stored data. */
function bhela_bm_render_sms( $template, $booking_id ) {
	$m        = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};
	$statuses = bhela_bm_statuses();
	$status   = $m( '_bhela_status' ) ? $m( '_bhela_status' ) : 'pending';
	$total    = (int) $m( '_bhela_total' );
	$paid     = (int) $m( '_bhela_paid_amount' );

	$map = array(
		'{name}'    => get_the_title( $booking_id ),
		'{phone}'   => $m( '_bhela_phone' ),
		'{invoice}' => $m( '_bhela_invoice_no' ),
		'{date}'    => $m( '_bhela_travel_date' ),
		'{cabin}'   => $m( '_bhela_cabin_type' ),
		'{guests}'  => (int) $m( '_bhela_guests' ),
		'{total}'   => bhela_bm_money( $total ),
		'{advance}' => bhela_bm_money( (int) $m( '_bhela_advance' ) ),
		'{due}'     => bhela_bm_money( max( 0, $total - $paid ) ),
		'{status}'  => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status,
	);
	return strtr( (string) $template, $map );
}

/**
 * Send one SMS. Returns true on a 2xx gateway response.
 *
 * @param string $number  Recipient (any format — normalised here).
 * @param string $message Text body.
 */
function bhela_bm_send_sms( $number, $message ) {
	$s = bhela_bm_get_settings();
	if ( empty( $s['sms_enabled'] ) ) {
		return false;
	}
	$to = bhela_bm_sms_number( $number );
	if ( '' === $to || '' === trim( (string) $message ) ) {
		return false;
	}

	$url    = esc_url_raw( $s['sms_api_url'] );
	$method = ( 'POST' === strtoupper( $s['sms_method'] ) ) ? 'POST' : 'GET';
	$params = array(
		$s['sms_param_key']     => $s['sms_api_key'],
		$s['sms_param_sender']  => $s['sms_sender_id'],
		$s['sms_param_number']  => $to,
		$s['sms_param_message'] => $message,
	);

	$args = array( 'timeout' => 15 );
	if ( ! empty( $s['sms_auth_header'] ) ) {
		$parts = explode( ':', $s['sms_auth_header'], 2 );
		if ( 2 === count( $parts ) ) {
			$args['headers'] = array( trim( $parts[0] ) => trim( $parts[1] ) );
		}
	}

	if ( 'GET' === $method ) {
		$response = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $params ), $url ), $args );
	} elseif ( ! empty( $s['sms_json'] ) ) {
		$args['headers']              = array_merge( $args['headers'] ?? array(), array( 'Content-Type' => 'application/json' ) );
		$args['body']                 = wp_json_encode( $params );
		$response                     = wp_remote_post( $url, $args );
	} else {
		$args['body'] = $params;
		$response     = wp_remote_post( $url, $args );
	}

	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$body = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );

	// Store the last result for the settings status line — never the API key.
	update_option( 'bhela_bm_sms_last', array(
		'time'   => current_time( 'mysql' ),
		'to'     => $to,
		'code'   => $code,
		'body'   => is_string( $body ) ? mb_substr( wp_strip_all_tags( $body ), 0, 300 ) : '',
	), false );

	return $code >= 200 && $code < 300;
}

/** Admin recipient — explicit SMS number, else business Phone 1. */
function bhela_bm_sms_admin_number() {
	$s = bhela_bm_get_settings();
	return ! empty( $s['sms_admin_number'] ) ? $s['sms_admin_number'] : $s['phone_1'];
}

/* ---------- Triggers ---------- */

/** New booking → customer + admin. Called from the submission processor. */
function bhela_bm_sms_on_new_booking( $booking_id ) {
	$s     = bhela_bm_get_settings();
	$phone = get_post_meta( $booking_id, '_bhela_phone', true );
	if ( $phone ) {
		bhela_bm_send_sms( $phone, bhela_bm_render_sms( $s['sms_tpl_new'], $booking_id ) );
	}
	bhela_bm_send_sms( bhela_bm_sms_admin_number(), bhela_bm_render_sms( $s['sms_tpl_admin'], $booking_id ) );
}

/** Status change → customer. Called from the booking save handler. */
function bhela_bm_sms_on_status_change( $booking_id, $new_status, $old_status ) {
	if ( $new_status === $old_status ) {
		return;
	}
	$s     = bhela_bm_get_settings();
	$phone = get_post_meta( $booking_id, '_bhela_phone', true );
	if ( $phone ) {
		bhela_bm_send_sms( $phone, bhela_bm_render_sms( $s['sms_tpl_confirmed'], $booking_id ) );
	}
}

/* ---------- Admin: test-send endpoint ---------- */

function bhela_bm_sms_test_send() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_sms_test' );
	$to  = bhela_bm_sms_admin_number();
	$msg = 'BHELA SMS test ✅ — ' . current_time( 'H:i' );
	// Force-enable for the test so an unsaved-but-configured gateway can be tried.
	add_filter( 'option_bhela_bm_settings', 'bhela_bm_sms_force_enable_filter' );
	$ok = bhela_bm_send_sms( $to, $msg );
	remove_filter( 'option_bhela_bm_settings', 'bhela_bm_sms_force_enable_filter' );
	$last = get_option( 'bhela_bm_sms_last', array() );
	set_transient( 'bhela_bm_sms_test_result', array(
		'ok'   => $ok,
		'to'   => $to,
		'code' => $last['code'] ?? 0,
		'body' => $last['body'] ?? '',
	), 60 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings#bhela-sms' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_sms_test', 'bhela_bm_sms_test_send' );

/** Temporarily flip sms_enabled on for the manual test send. */
function bhela_bm_sms_force_enable_filter( $value ) {
	if ( is_array( $value ) ) {
		$value['sms_enabled'] = 1;
	}
	return $value;
}
