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
	$status   = $m( '_bhela_status' ) ? $m( '_bhela_status' ) : 'pending';
	$total    = (int) $m( '_bhela_total' );
	$s        = bhela_bm_get_settings();
	// Through bhela_bm_balance(), not `max( 0, $total - $paid )` as this used to be.
	// That was a second copy of the arithmetic every other guest-facing surface
	// already shares, and a text message quoting a different Due from the invoice
	// beside it is the one discrepancy a guest is guaranteed to notice.
	$bal      = bhela_bm_balance( $total, $m( '_bhela_paid_amount' ) );
	$stay     = bhela_bm_booking_stay( $booking_id );
	$pay_key  = (string) $m( '_bhela_pay_method' );
	$pay_label = '' !== $pay_key ? ( bhela_bm_pay_methods()[ $pay_key ] ?? '' ) : '';
	$fmt      = function ( $ymd ) {
		return $ymd ? mysql2date( 'j F Y', $ymd ) : '';
	};

	$map = array(
		'{name}'    => get_the_title( $booking_id ),
		'{phone}'   => $m( '_bhela_phone' ),
		'{invoice}' => $m( '_bhela_invoice_no' ),
		'{date}'    => $m( '_bhela_travel_date' ),
		'{cabin}'   => $m( '_bhela_cabin_type' ),
		'{guests}'  => (int) $m( '_bhela_guests' ),
		'{total}'   => bhela_bm_money( $total ),
		'{advance}' => bhela_bm_money( (int) $m( '_bhela_advance' ) ),
		'{paid}'    => bhela_bm_money( $bal['paid'] ),
		'{due}'     => bhela_bm_money( $bal['due'] ),
		'{status}'  => bhela_bm_statuses()[ $status ] ?? $status,

		// The stay. Dates are derived from the travel date on every read, so moving
		// a booking moves both ends of it — see bhela_bm_booking_stay().
		'{checkin}'       => $fmt( $stay['in'] ),
		'{checkout}'      => $fmt( $stay['out'] ),
		'{checkin_time}'  => $stay['in_time'],
		'{checkout_time}' => $stay['out_time'],

		// Logistics. Each falls back to the setting, so a booking only carries what
		// actually differs from the norm.
		'{boarding}'   => $m( '_bhela_boarding' ) ?: ( $s['boarding_ghat'] ?? '' ),
		'{package}'    => $s['package_label'] ?? '',
		// Available for an operator who wants it in their template. Deliberately NOT
		// in bhela_bm_confirm_default_template() — a blank confirm_template means
		// "use the shipped default", so editing that default would never reach a site
		// that has already saved its settings once.
		'{vessel_reg}' => $s['vessel_reg'] ?? '',
		'{room}'       => $m( '_bhela_room_no' ),
		'{room_type}'  => $m( '_bhela_cabin_type' ),
		'{address}'    => $m( '_bhela_address' ),
		// Carries its own brackets so the template needs no conditional: an unset
		// method must print nothing, not "(—)", which reads as a lost value.
		'{pay_method}' => $pay_label ? '(' . $pay_label . ')' : '',

		// B2B. Deliberately NOT in the shipped confirmation template: that message
		// goes to the guest, and the commission is between BHELA and the partner.
		// They exist so an AGENCY-facing template can carry them, which is then a
		// settings edit rather than a code change.
		'{agency}'     => function_exists( 'bhela_bm_booking_agency_name' ) ? bhela_bm_booking_agency_name( $booking_id ) : '',
		'{agency_ref}' => $m( '_bhela_agency_ref' ),
		'{commission}' => (int) $m( '_bhela_commission' ) > 0 ? bhela_bm_money( (int) $m( '_bhela_commission' ) ) : '',

		// Who handled it, and when it went out.
		'{booked_by}' => $m( '_bhela_booked_by' ),
		'{issued_by}' => $m( '_bhela_issued_by' ),
		'{issued_on}' => mysql2date( 'j F Y', current_time( 'mysql' ) ),

		'{ops_manager}'      => $s['ops_manager'] ?? '',
		'{support_whatsapp}' => $s['support_whatsapp'] ?: ( $s['whatsapp'] ?? '' ),
		'{notes}'            => bhela_bm_confirm_notes(),
		'{invoice_link}'     => function_exists( 'bhela_bm_invoice_url' ) ? bhela_bm_invoice_url( $booking_id ) : '',

		// Private review link — only meaningful in the completion template.
		'{review_link}' => function_exists( 'bhela_bm_review_url' ) ? bhela_bm_review_url( $booking_id ) : '',
	);
	return strtr( (string) $template, $map );
}

/**
 * SSRF guard for the outbound gateway call: require HTTPS and a public host.
 * Blocks localhost, link-local and private/reserved IP ranges so the API key
 * can never be posted to an internal service.
 */
function bhela_bm_sms_url_is_safe( $url ) {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
		return false;
	}
	$host = trim( $parts['host'], '[]' ); // unwrap IPv6 literals like [::1]
	$ip   = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
	// If the host resolves to an IP, it must be public. (An unresolvable name
	// returns the host unchanged, which fails FILTER_VALIDATE_IP → allowed as a
	// normal DNS name; WordPress' own request filters still apply.)
	if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
	return true;
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
		// Say why. A silent return here reads exactly like a gateway failure.
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'sms', '' === $to
				? 'SMS not sent — no valid mobile number.'
				: 'SMS not sent — the message template is empty.', false );
		}
		return false;
	}

	$url = esc_url_raw( $s['sms_api_url'] );
	// SSRF guard: the gateway URL is admin-set, but refuse anything that is not
	// an external HTTPS endpoint so a mis-set/compromised value can't make the
	// server call an internal host (e.g. cloud metadata) with the API key.
	if ( ! bhela_bm_sms_url_is_safe( $url ) ) {
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', 'SMS পাঠানো যায়নি — গেটওয়ে URL নিরাপদ নয় (HTTPS ও পাবলিক হোস্ট লাগবে)।', false );
		}
		return false;
	}
	$method = ( 'POST' === strtoupper( $s['sms_method'] ) ) ? 'POST' : 'GET';
	$params = array(
		$s['sms_param_key']     => $s['sms_api_key'],
		$s['sms_param_sender']  => $s['sms_sender_id'],
		$s['sms_param_number']  => $to,
		$s['sms_param_message'] => $message,
	);
	// BulkSMSBD requires type=text; without it the gateway rejects the send.
	if ( 'bulksmsbd' === ( $s['sms_provider'] ?? '' ) ) {
		$params['type'] = 'text';
	}

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

	$ok     = $code >= 200 && $code < 300;
	$detail = '';

	// HTTP 200 is not the same as "sent". BulkSMSBD answers 200 for failures
	// too and puts the real verdict in the body as response_code — 202 means
	// accepted, anything else is a rejection (bad number, no balance, sender
	// ID not approved…). Trusting the HTTP status alone reported success for
	// messages that never left the building, which matters most for OTP: the
	// guest waits for a code that is not coming and the email fallback never
	// fires. Gateways without this field are unaffected.
	if ( $ok && is_string( $body ) ) {
		$json = json_decode( $body, true );
		if ( is_array( $json ) && isset( $json['response_code'] ) ) {
			$rc = (int) $json['response_code'];
			if ( 202 !== $rc ) {
				$ok     = false;
				$detail = bhela_bm_sms_gateway_error( $rc );
			}
		}
	}

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log(
			$ok ? 'sms' : 'error',
			sprintf( 'SMS %s — %s (HTTP %d)%s',
				$ok ? 'পাঠানো হয়েছে' : 'পাঠানো যায়নি',
				$to,
				$code,
				$ok ? '' : ' — ' . ( $detail ? $detail : mb_substr( wp_strip_all_tags( (string) $body ), 0, 120 ) )
			),
			$ok
		);
	}
	return $ok;
}

/**
 * Turn a BulkSMSBD response_code into something the owner can act on.
 *
 * @param int $rc Gateway response code.
 * @return string
 */
function bhela_bm_sms_gateway_error( $rc ) {
	$map = array(
		1001 => 'Invalid number',
		1002 => 'Sender ID not correct or disabled',
		1003 => 'Required fields missing',
		1005 => 'Gateway internal error',
		1006 => 'Balance validity not available',
		1007 => 'Balance insufficient — top up the SMS account',
		1011 => 'User ID not found',
		1012 => 'Masking SMS must be sent in Bangla',
		1013 => 'Sender ID has no gateway for this API key',
		1014 => 'Sender type not found for this API key',
		1015 => 'Sender ID has no valid gateway for this API key',
		1031 => 'Account not verified',
		1032 => 'Server IP is not whitelisted at the gateway',
	);
	return sprintf( '%s (code %d)', $map[ $rc ] ?? 'Gateway rejected the message', $rc );
}

/* =========================================================
 * CREDIT BALANCE
 * ========================================================= */

/**
 * The gateway's balance endpoint, derived from the send URL.
 *
 * Only BulkSMSBD is known to publish one, and its balance API sits beside the
 * send API on the same host. Other gateways return an empty string and the
 * balance UI simply does not appear.
 */
function bhela_bm_sms_balance_url() {
	$s = bhela_bm_get_settings();
	if ( 'bulksmsbd' !== ( $s['sms_provider'] ?? '' ) || empty( $s['sms_api_key'] ) ) {
		return '';
	}
	return 'https://bulksmsbd.net/api/getBalanceApi';
}

/**
 * Remaining SMS credit.
 *
 * Cached for 15 minutes: the dashboard is opened all day and this is an
 * external HTTP call on every page load otherwise. `$force` is for the manual
 * refresh link.
 *
 * @param bool $force Skip the cache.
 * @return array{balance:?float,at:string,error:string}
 */
function bhela_bm_sms_balance( $force = false ) {
	$none = array( 'balance' => null, 'at' => '', 'error' => '' );
	$url  = bhela_bm_sms_balance_url();
	if ( ! $url ) {
		return $none;
	}
	if ( ! $force ) {
		$cached = get_transient( 'bhela_bm_sms_balance' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$s = bhela_bm_get_settings();
	if ( ! bhela_bm_sms_url_is_safe( $url ) ) {
		return array_merge( $none, array( 'error' => __( 'Balance URL is not safe to call.', 'bhela-booking' ) ) );
	}

	$response = wp_remote_get(
		add_query_arg( array( 'api_key' => rawurlencode( $s['sms_api_key'] ) ), $url ),
		array( 'timeout' => 12 )
	);

	$out = $none;
	if ( is_wp_error( $response ) ) {
		$out['error'] = $response->get_error_message();
	} else {
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $json ) && isset( $json['balance'] ) ) {
			$out['balance'] = round( (float) $json['balance'], 2 );
		} elseif ( is_array( $json ) && isset( $json['response_code'] ) ) {
			$out['error'] = bhela_bm_sms_gateway_error( (int) $json['response_code'] );
		} else {
			$out['error'] = __( 'The gateway did not return a balance.', 'bhela-booking' );
		}
	}
	$out['at'] = current_time( 'mysql' );

	// Cache failures briefly too, so a dead gateway cannot stall every
	// dashboard load with a 12-second timeout.
	set_transient( 'bhela_bm_sms_balance', $out, null === $out['balance'] ? 2 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	return $out;
}

/** Is the credit low enough to act on? */
function bhela_bm_sms_balance_low( $balance ) {
	if ( null === $balance ) {
		return false;
	}
	$s = bhela_bm_get_settings();
	return $balance <= (float) ( $s['sms_low_balance'] ?? 100 );
}

/** Manual refresh from the dashboard / settings screen. */
function bhela_bm_sms_balance_refresh() {
	if ( ! current_user_can( 'bhela_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_sms_balance' );
	bhela_bm_sms_balance( true );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : bhela_bm_admin_url( 'bhela-bm-dashboard' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_sms_balance', 'bhela_bm_sms_balance_refresh' );

/** Admin recipient — explicit SMS number, else business Phone 1. */
function bhela_bm_sms_admin_number() {
	$s = bhela_bm_get_settings();
	return ! empty( $s['sms_admin_number'] ) ? $s['sms_admin_number'] : $s['phone_1'];
}

/**
 * A message template, falling back to the packaged default when the stored one
 * is blank.
 *
 * A blank template used to mean "send nothing", silently — bhela_bm_send_sms()
 * drops empty messages, so a template cleared by accident disabled that
 * notification with no trace. Whether a message sends is now the checkbox's job,
 * so a blank box simply means "use the wording we shipped".
 *
 * @param string $key sms_tpl_* settings key.
 */
function bhela_bm_sms_template( $key ) {
	$s = bhela_bm_get_settings();
	if ( ! empty( $s[ $key ] ) && '' !== trim( $s[ $key ] ) ) {
		return $s[ $key ];
	}
	$defaults = bhela_bm_default_settings();
	return $defaults[ $key ] ?? '';
}

/* ---------- Triggers ---------- */

/** New booking → customer + admin. Called from the submission processor. */
function bhela_bm_sms_on_new_booking( $booking_id ) {
	$s     = bhela_bm_get_settings();
	$phone = get_post_meta( $booking_id, '_bhela_phone', true );
	if ( $phone && ! empty( $s['sms_customer_request'] ) ) {
		bhela_bm_send_sms( $phone, bhela_bm_render_sms( bhela_bm_sms_template( 'sms_tpl_new' ), $booking_id ) );
	}
	if ( ! empty( $s['sms_admin_new'] ) ) {
		bhela_bm_send_sms( bhela_bm_sms_admin_number(), bhela_bm_render_sms( bhela_bm_sms_template( 'sms_tpl_admin' ), $booking_id ) );
	}
}

/** Status change → customer. Called from the booking save handler. */
function bhela_bm_sms_on_status_change( $booking_id, $new_status, $old_status ) {
	if ( $new_status === $old_status ) {
		return;
	}
	$s     = bhela_bm_get_settings();
	$phone = get_post_meta( $booking_id, '_bhela_phone', true );
	if ( ! $phone ) {
		return;
	}
	// A finished trip gets the thank-you + review link instead of the generic
	// status line — same single message, so this costs nothing extra. Each has
	// its own switch, so the owner can pay for one and not the other.
	$completed = ( 'completed' === $new_status );
	$gate      = $completed ? 'sms_customer_completed' : 'sms_customer_confirmed';
	if ( empty( $s[ $gate ] ) ) {
		return;
	}
	$tpl = bhela_bm_sms_template( $completed ? 'sms_tpl_completed' : 'sms_tpl_confirmed' );
	bhela_bm_send_sms( $phone, bhela_bm_render_sms( $tpl, $booking_id ) );
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
	wp_safe_redirect( bhela_bm_admin_url( 'bhela-bm-settings' ) . '#bhela-sms' );
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
