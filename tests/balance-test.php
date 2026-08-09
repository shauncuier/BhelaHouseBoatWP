<?php
/** Dev helper: SMS balance fetch, cache, low-credit threshold, and the dashboard card. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'dashboard' );
wp_set_current_user( 1 );
delete_transient( 'bhela_bm_sms_balance' );

echo "=== 1. live fetch from the gateway ===\n";
$b = bhela_bm_sms_balance( true );
printf( "  balance=%s at=%s error=%s\n", var_export( $b['balance'], true ), $b['at'], $b['error'] ?: '(none)' );
ok( is_float( $b['balance'] ) && $b['balance'] > 0, 'a real balance came back' );
ok( '' === $b['error'], 'no error' );

echo "\n=== 2. cached, not re-fetched ===\n";
$hits = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$hits ) {
	if ( false !== strpos( $url, 'getBalanceApi' ) ) { $hits++; }
	return $pre;
}, 10, 3 );
bhela_bm_sms_balance();
bhela_bm_sms_balance();
ok( 0 === $hits, 'repeat reads hit the cache, not the network', "$hits http calls" );

echo "\n=== 3. the API key is never exposed ===\n";
$cached = get_transient( 'bhela_bm_sms_balance' );
$s      = bhela_bm_get_settings();
ok( false === strpos( wp_json_encode( $cached ), $s['sms_api_key'] ), 'key is not in the cached payload' );

echo "\n=== 4. low-credit threshold ===\n";
$s['sms_low_balance'] = 100;
update_option( 'bhela_bm_settings', $s );
ok( true === bhela_bm_sms_balance_low( 50.0 ), '50 is low' );
ok( true === bhela_bm_sms_balance_low( 100.0 ), '100 (exactly the threshold) is low' );
ok( false === bhela_bm_sms_balance_low( 466.4 ), '466.40 is fine' );
ok( false === bhela_bm_sms_balance_low( null ), 'unknown is not treated as low' );

echo "\n=== 5. gateway failure degrades quietly ===\n";
delete_transient( 'bhela_bm_sms_balance' );
$fail = function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'getBalanceApi' ) ) { return $pre; }
	return array( 'response' => array( 'code' => 200 ), 'body' => '{"response_code":1011}' );
};
add_filter( 'pre_http_request', $fail, 5, 3 );
$b = bhela_bm_sms_balance( true );
ok( null === $b['balance'] && '' !== $b['error'], 'a gateway error is reported, not faked', $b['error'] );
ok( false !== strpos( $b['error'], '1011' ), 'the gateway code is surfaced' );
remove_filter( 'pre_http_request', $fail, 5 );
delete_transient( 'bhela_bm_sms_balance' );

echo "\n=== 6. hidden when the provider has no balance API ===\n";
$orig = $s['sms_provider'];
$s['sms_provider'] = 'custom';
update_option( 'bhela_bm_settings', $s );
ok( '' === bhela_bm_sms_balance_url(), 'no balance URL for a custom gateway' );
ok( null === bhela_bm_sms_balance( true )['balance'], 'no balance reported' );
$s['sms_provider'] = $orig;
update_option( 'bhela_bm_settings', $s );
delete_transient( 'bhela_bm_sms_balance' );

echo "\n=== 7. dashboard renders the card ===\n";
ob_start();
bhela_bm_dashboard_page();
$html = ob_get_clean();
printf( "  rendered %d bytes\n", strlen( $html ) );
foreach ( array( 'Fatal error', 'Warning:', 'Notice:', 'Deprecated:' ) as $bad ) {
	ok( false === strpos( $html, $bad ), "no '$bad'" );
}
ok( false !== strpos( $html, 'SMS credit' ), 'card is present' );
ok( false !== strpos( $html, 'bhela_bm_sms_balance' ), 'refresh link is present' );
ok( false === strpos( $html, $s['sms_api_key'] ), 'the API key is NOT in the page HTML' );

bhela_test_done();
