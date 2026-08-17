<?php
/** Dev helper: SMS balance fetch, cache, low-credit threshold, and the dashboard card. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'dashboard' );
wp_set_current_user( 1 );
delete_transient( 'bhela_bm_sms_balance' );

// The live settings decide whether section 1 can run, and nothing else. Every
// other section used to read them too, which meant this harness could only pass
// on a site that happened to have a working gateway configured — so a fresh
// checkout, or a site with SMS switched off, failed seven checks that were about
// caching and masking and had nothing to do with a gateway. The suite was then
// permanently red, which is the state that teaches people to stop reading it.
$live = bhela_bm_get_settings();
$has_real_gateway = ! empty( $live['sms_api_key'] ) && 'bulksmsbd' === ( $live['sms_provider'] ?? '' );

echo "=== 1. live fetch from the gateway ===\n";
if ( ! $has_real_gateway ) {
	printf( "  SKIPPED — no BulkSMSBD key is configured on this site, so there is no gateway to ask.\n" );
	printf( "  Sections 2-7 below configure a dummy gateway and run entirely offline.\n" );
} else {
	$b = bhela_bm_sms_balance( true );
	printf( "  balance=%s at=%s error=%s\n", var_export( $b['balance'], true ), $b['at'], $b['error'] ?: '(none)' );

	// A gateway that answers and says no — a bad key, a rejected sender ID — is a
	// genuine failure and must go red. Not being able to reach it at all is a fact
	// about the network, and failing a release on that would train everyone to
	// ignore a red suite.
	$offline = $b['error'] && preg_match( '/could not resolve|couldn.t resolve|timed out|no working transports|connection (refused|timed out)|resolve host|network is unreachable/i', $b['error'] );
	if ( $offline ) {
		printf( "  SKIPPED — the gateway is unreachable from here (%s).\n", $b['error'] );
	} else {
		ok( is_float( $b['balance'] ) && $b['balance'] > 0, 'a real balance came back' );
		ok( '' === $b['error'], 'no error — the gateway answered and accepted the key', $b['error'] );
	}
}

// From here on the harness owns its own gateway: a real provider so the balance
// URL exists, and a key that is a known string so "the key never leaks" has
// something to actually look for. Restored at the end.
//
// The old assertions searched for $s['sms_api_key'], which is '' on an
// unconfigured site — and strpos( $haystack, '' ) returns 0, not false, so
// `false === strpos(...)` failed. Worse, on a site that DID have a key those
// checks passed without the masking ever being exercised here.
$restore = get_option( 'bhela_bm_settings', array() );
$fake    = wp_parse_args( array(
	'sms_enabled'     => 1,
	'sms_provider'    => 'bulksmsbd',
	'sms_api_key'     => 'ZZTESTKEY-not-a-real-credential',
	'sms_sender_id'   => 'ZZTEST',
	'sms_low_balance' => 100,
), $restore );
update_option( 'bhela_bm_settings', $fake );
$s = bhela_bm_get_settings();
delete_transient( 'bhela_bm_sms_balance' );

// Nothing below may leave the machine. Answer the balance call locally.
$balance_mock = function ( $pre, $args, $url ) {
	// Stand aside if something earlier in the chain already answered. Section 5
	// installs a failure mock at a lower priority, and a mock that overrode it
	// would quietly turn that test's gateway error back into a success — which is
	// exactly what happened, and it made the failure path look broken when it was
	// the double-mock that was wrong.
	// WordPress seeds this filter with false, so "false" means nobody has answered
	// yet — anything else is a response another callback already supplied.
	if ( false !== $pre || false === strpos( $url, 'getBalanceApi' ) ) {
		return $pre;
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => '{"balance":466.4}' );
};
add_filter( 'pre_http_request', $balance_mock, 10, 3 );

ok( 466.4 === bhela_bm_sms_balance( true )['balance'], 'a configured gateway is parsed', 'mocked 466.40' );
ok( '' !== bhela_bm_sms_balance_url(), 'and it has a balance URL to call' );

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
// A real needle now, so this assertion can actually fail. It could not before:
// the key was '' and strpos( $haystack, '' ) is 0, never false.
ok( '' !== $s['sms_api_key'], 'the test has a key to look for', $s['sms_api_key'] );
ok( false === strpos( (string) wp_json_encode( $cached ), $s['sms_api_key'] ), 'key is not in the cached payload' );

echo "\n=== 4. low-credit threshold ===\n";
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

echo "\n=== cleanup ===\n";
remove_filter( 'pre_http_request', $balance_mock, 10 );
delete_transient( 'bhela_bm_sms_balance' );
// Put the owner's real settings back. This harness rewrote them to test with,
// and leaving a dummy key behind would switch SMS on for a live site.
if ( $restore ) {
	update_option( 'bhela_bm_settings', $restore );
} else {
	delete_option( 'bhela_bm_settings' );
}
$after = bhela_bm_get_settings();
ok( ( $restore['sms_api_key'] ?? '' ) === $after['sms_api_key'], 'the real API key setting is restored' );
ok( (int) ( $restore['sms_enabled'] ?? 0 ) === (int) $after['sms_enabled'], 'and the SMS switch is back as it was', (string) $after['sms_enabled'] );

bhela_test_done();
