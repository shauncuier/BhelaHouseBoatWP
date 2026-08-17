<?php
/** Dev helper: OTP send/verify, the submission gate, and the cost guards. No real SMS is sent. */

// wp_die() would otherwise pick the default handler, which calls die() and
// takes the harness with it on the first wp_send_json.
define( 'DOING_AJAX', true );
require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );
class Bhela_Die extends \Exception {}
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Bhela_Die( 'die' ); }; } );

/** Run an AJAX handler and capture its JSON without letting wp_die kill us. */
function call( $fn, $post ) {
	$_POST = $post;
	$_POST['nonce'] = wp_create_nonce( 'bhela_bm_booking' );
	$_REQUEST = $_POST;
	ob_start();
	try { $fn(); } catch ( \Throwable $e ) {}
	return json_decode( ob_get_clean(), true );
}

$PHONE = '01712345678';
$KEY   = bhela_bm_otp_key( $PHONE );

/* Intercept every outbound send so the tests cost nothing and hit no network. */
$GLOBALS['sent']     = array();
$GLOBALS['sms_fail'] = false;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'bulksmsbd' ) ) { return $pre; }
	$GLOBALS['sent'][] = array( 'channel' => 'sms', 'url' => $url );
	$body = $GLOBALS['sms_fail']
		? '{"response_code":1007,"error_message":"Balance Insufficient"}'
		: '{"response_code":202,"success_message":"Activation Successful"}';
	return array( 'response' => array( 'code' => 200 ), 'body' => $body );
}, 10, 3 );
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$GLOBALS['sent'][] = array( 'channel' => 'email', 'to' => $atts['to'], 'body' => $atts['message'] );
	return true;
}, 10, 2 );

/*
 * The harness owns the gateway settings it tests.
 *
 * It used to read whatever the live site had, and every send goes through
 * bhela_bm_send_sms(), which returns false immediately when `sms_enabled` is off
 * — and the submission gate is skipped entirely when `otp_enabled` is off. On a
 * site with SMS switched off (a fresh checkout, or any site not yet using it)
 * that failed eighteen checks about parsing, throttling and the server-side gate,
 * none of which have anything to do with the owner's configuration. A suite that
 * is permanently red is a suite people stop reading.
 *
 * Every outbound call is intercepted above, so nothing here reaches a network and
 * no SMS is ever sent. Restored at the end.
 */
$otp_restore = get_option( 'bhela_bm_settings', array() );
update_option( 'bhela_bm_settings', wp_parse_args( array(
	'otp_enabled'   => 1,
	'sms_enabled'   => 1,
	'sms_provider'  => 'bulksmsbd',
	'sms_api_key'   => 'ZZTESTKEY-not-a-real-credential',
	'sms_sender_id' => 'ZZTEST',
), $otp_restore ) );

ok( bhela_bm_otp_on(), 'the harness has OTP switched on for itself' );

function reset_state( $phone ) {
	delete_transient( bhela_bm_otp_key( $phone ) );
	delete_transient( bhela_bm_otp_ok_key( $phone ) );
	delete_transient( 'bhela_bm_otpday_' . md5( $phone ) );
	delete_transient( 'bhela_bm_otpip_' . md5( '' ) );
	$GLOBALS['sent'] = array();
}

echo "=== 1. gateway response parsing (the bug that made OTP untrustworthy) ===\n";
$GLOBALS['sms_fail'] = false;
ok( true === bhela_bm_send_sms( $PHONE, 'Your BHELA OTP is 1111' ), 'response_code 202 counts as sent' );
ok( false !== strpos( $GLOBALS['sent'][0]['url'] ?? '', 'type=text' ), 'request carries type=text' );
$GLOBALS['sent'] = array();
$GLOBALS['sms_fail'] = true;
ok( false === bhela_bm_send_sms( $PHONE, 'x' ), 'HTTP 200 + response_code 1007 counts as FAILED' );
ok( false !== strpos( bhela_bm_sms_gateway_error( 1007 ), 'Balance insufficient' ), 'error code is translated' );

echo "\n=== 2. happy path ===\n";
$GLOBALS['sms_fail'] = false;
reset_state( $PHONE );
$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
ok( ! empty( $res['success'] ), 'code sent' );
ok( 'sms' === ( $res['data']['channel'] ?? '' ), 'channel = sms' );

$raw   = wp_json_encode( $res );
$state = get_transient( $KEY );
ok( ! empty( $state['hash'] ), 'only a hash is stored', substr( (string) ( $state['hash'] ?? '' ), 0, 12 ) . '…' );
// Recover the code from the intercepted SMS, the way a guest reads their phone.
preg_match( '/OTP is (\d{4})/', urldecode( $GLOBALS['sent'][0]['url'] ), $m );
$code = $m[1] ?? '';
ok( 4 === strlen( $code ), 'a 4-digit code was sent', $code );
ok( false === strpos( $raw, $code ), 'the code is NOT in the response body' );

$res = call( 'bhela_bm_otp_ajax_verify', array( 'phone' => $PHONE, 'code' => $code ) );
ok( ! empty( $res['success'] ), 'correct code verifies' );
ok( bhela_bm_otp_verified( $PHONE ), 'number is marked verified' );
ok( false === get_transient( $KEY ), 'the code is consumed' );

echo "\n=== 3. the submission gate is server-side ===\n";
$base = array(
	'name' => 'Harness Guest', 'date' => '2026-08-15',
	'cabins' => wp_json_encode( array( array( 'adults' => 2, 'c48' => 0, 'c04' => 0 ) ) ),
);
$r = bhela_bm_process_submission( array_merge( $base, array( 'phone' => $PHONE ) ) );
ok( ! is_wp_error( $r ), 'verified number is accepted', is_wp_error( $r ) ? $r->get_error_message() : 'booking ' . ( $r['invoice_no'] ?? '' ) );
if ( ! is_wp_error( $r ) && ! empty( $r['booking_id'] ) ) {
	$stamp = bhela_bm_otp_record( $r['booking_id'] );
	ok( ! empty( $stamp['channel'] ), 'booking is stamped with the channel', $stamp['channel'] ?? '' );
	wp_delete_post( $r['booking_id'], true );
}

$other = '01812345678';
reset_state( $other );
$r = bhela_bm_process_submission( array_merge( $base, array( 'phone' => $other ) ) );
ok( is_wp_error( $r ) && 'unverified_phone' === $r->get_error_code(), 'UNVERIFIED number is rejected even with a valid request' );

echo "\n=== 4. changing the number invalidates the proof ===\n";
ok( bhela_bm_otp_verified( $PHONE ), 'original still verified' );
ok( ! bhela_bm_otp_verified( '01999888777' ), 'a different number is not' );

echo "\n=== 5. wrong codes ===\n";
reset_state( $PHONE );
call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
preg_match( '/OTP is (\d{4})/', urldecode( $GLOBALS['sent'][0]['url'] ), $m );
$good = $m[1];
$bad  = sprintf( '%04d', ( (int) $good + 1 ) % 10000 );
$res  = call( 'bhela_bm_otp_ajax_verify', array( 'phone' => $PHONE, 'code' => $bad ) );
ok( empty( $res['success'] ), 'wrong code rejected' );
ok( 4 === ( $res['data']['left'] ?? -1 ), 'attempts remaining reported', (string) ( $res['data']['left'] ?? '' ) );
for ( $i = 0; $i < 4; $i++ ) { $res = call( 'bhela_bm_otp_ajax_verify', array( 'phone' => $PHONE, 'code' => $bad ) ); }
ok( ! empty( $res['data']['expired'] ), 'code destroyed after 5 wrong tries' );
$res = call( 'bhela_bm_otp_ajax_verify', array( 'phone' => $PHONE, 'code' => $good ) );
ok( empty( $res['success'] ), 'the real code no longer works either' );

echo "\n=== 6. cost guards ===\n";
reset_state( $PHONE );
call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
ok( empty( $res['success'] ) && ! empty( $res['data']['retry'] ), 'resend inside the cooldown is refused', 'retry in ' . ( $res['data']['retry'] ?? '?' ) . 's' );

reset_state( $PHONE );
set_transient( 'bhela_bm_otpday_' . md5( $PHONE ), BHELA_BM_OTP_MAX_SENDS, DAY_IN_SECONDS );
$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
ok( empty( $res['success'] ), 'daily cap blocks further sends' );
delete_transient( 'bhela_bm_otpday_' . md5( $PHONE ) );

echo "\n=== 7. email fallback ===\n";
$GLOBALS['sms_fail'] = true;
reset_state( $PHONE );
$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE, 'email' => 'guest@example.invalid' ) );
ok( ! empty( $res['success'] ) && 'email' === ( $res['data']['channel'] ?? '' ), 'falls back to email when SMS fails' );
$mail = end( $GLOBALS['sent'] );
ok( 'email' === $mail['channel'] && 'guest@example.invalid' === $mail['to'], 'mail went to the address given' );

reset_state( $PHONE );
$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $PHONE ) );
ok( ! empty( $res['data']['need_email'] ), 'asks for an email when SMS fails and none was given' );
$GLOBALS['sms_fail'] = false;

echo "\n=== 8. message stays one SMS part ===\n";
$msg = bhela_bm_otp_message( '1234' );
ok( 'Your BHELA OTP is 1234' === $msg, 'exact format requested', $msg );
$gsm = "@£\$¥èéùìòÇØøÅåÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà\n\r";
$bad = array();
foreach ( preg_split( '//u', $msg, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) { if ( false === mb_strpos( $gsm, $ch ) ) { $bad[] = $ch; } }
ok( ! $bad, 'GSM-7 clean (1 part, not 2)', $bad ? implode( ' ', $bad ) : mb_strlen( $msg ) . ' chars' );
ok( 'BHELA - The Haor Exclusive' === bhela_bm_otp_gsm_safe( 'BHELA – The Haor Exclusive' ), 'en-dash folded to ASCII' );
ok( 'BHELA' === bhela_bm_otp_gsm_safe( 'ভেলা BHELA' ), 'Bangla stripped from the brand', bhela_bm_otp_gsm_safe( 'ভেলা BHELA' ) );

echo "\n=== 9. bad input ===\n";
foreach ( array( '', 'abc', '12345' ) as $junk ) {
	$res = call( 'bhela_bm_otp_ajax_send', array( 'phone' => $junk ) );
	ok( empty( $res['success'] ), sprintf( "junk phone '%s' refused", $junk ) );
}

echo "\n=== cleanup ===\n";
reset_state( $PHONE );
reset_state( $other );
// Put the owner's settings back. Leaving the dummy key behind would switch SMS
// and OTP on for a live site, and a booking form demanding a code it cannot send
// would stop guests booking at all.
if ( $otp_restore ) {
	update_option( 'bhela_bm_settings', $otp_restore );
} else {
	delete_option( 'bhela_bm_settings' );
}
$otp_after = bhela_bm_get_settings();
ok( ( $otp_restore['sms_api_key'] ?? '' ) === $otp_after['sms_api_key'], 'the real API key setting is restored' );
ok( (int) ( $otp_restore['otp_enabled'] ?? 0 ) === (int) $otp_after['otp_enabled'], 'and OTP is back as it was', (string) $otp_after['otp_enabled'] );

bhela_test_done();
