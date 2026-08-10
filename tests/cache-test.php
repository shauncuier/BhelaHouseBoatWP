<?php
/** Dev helper: the front end must survive a full-page cache. */

// wp_send_json() calls wp_die(); without this the default handler kills the run.
define( 'DOING_AJAX', true );
require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );

class Bhela_Cache_Die extends \Exception {}
// Echo the message before unwinding. WordPress's own AJAX die handler writes
// the body — "-1" for a failed referer check — and that byte is exactly what
// the client keys on, so a handler that swallowed it would test nothing.
add_filter( 'wp_die_ajax_handler', function () {
	return function ( $message ) {
		if ( is_scalar( $message ) ) {
			echo $message; // phpcs:ignore WordPress.Security.EscapeOutput -- reproducing the wire body.
		}
		throw new Bhela_Cache_Die( 'die' );
	};
} );

/** Run an AJAX action and return the decoded response. */
function bhela_cache_call( $action, $post = array() ) {
	$_POST         = $post;
	$_REQUEST      = $post;
	$_POST['action'] = $action;
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_' . $action );
	} catch ( Bhela_Cache_Die $e ) {
		// expected: wp_send_json finished the request
	}
	$raw = trim( (string) ob_get_clean() );
	return array( 'raw' => $raw, 'json' => json_decode( $raw, true ) );
}

echo "=== 1. a fresh nonce can be fetched without one ===\n";
// This is the whole point: cached HTML must not have to carry a time-limited
// value, so the endpoint that issues one cannot itself require one.
$r = bhela_cache_call( 'bhela_bm_nonce' );
ok( is_array( $r['json'] ) && ! empty( $r['json']['success'] ), 'the endpoint answers', $r['raw'] ? substr( $r['raw'], 0, 60 ) : '(empty)' );
$fresh = $r['json']['data']['nonce'] ?? '';
ok( '' !== $fresh, 'it returns a nonce', $fresh );
ok( false !== wp_verify_nonce( $fresh, 'bhela_bm_booking' ), 'and the nonce it returns actually verifies' );

echo "\n=== 2. it is registered for logged-out visitors ===\n";
// Guests are the only people who see a cached page.
ok( has_action( 'wp_ajax_nopriv_bhela_bm_nonce' ), 'nopriv handler registered' );
ok( has_action( 'wp_ajax_bhela_bm_nonce' ), 'logged-in handler registered too' );

echo "\n=== 3. the response is never itself cacheable ===\n";
$src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/frontend.php' );
$fn  = substr( $src, strpos( $src, 'function bhela_bm_ajax_nonce' ) );
$fn  = substr( $fn, 0, strpos( $fn, "\n}" ) );
ok( false !== strpos( $fn, 'nocache_headers()' ), 'the nonce endpoint sends nocache headers' );

echo "\n=== 4. a stale nonce is rejected, so the retry path is real ===\n";
// If an expired nonce were accepted there would be nothing to fix. Prove the
// server does refuse one, and that the refusal is the bare -1 the client
// keys on rather than a JSON error — the two are handled differently.
$out = bhela_cache_call( 'bhela_bm_availability', array( 'nonce' => 'expired-rubbish', 'date' => '2026-07-15' ) );
ok( '-1' === $out['raw'] || '0' === $out['raw'], 'a bad nonce dies with the bare -1 the client detects', $out['raw'] );
// "-1" is *valid* JSON — it decodes to the number -1, not to null. So a client
// cannot spot a dead nonce by waiting for JSON.parse to throw; it has to
// compare the raw body first. That is why postWithNonce() reads text and tests
// the string before parsing, and this assertion pins the reason down.
ok( -1 === $out['json'] || 0 === $out['json'],
	'…which decodes as a bare number, not an object — hence the string check in the client',
	var_export( $out['json'], true ) );
ok( ! is_array( $out['json'] ), '…and never as a payload that could be mistaken for a real answer' );

echo "\n=== 5. a genuine refusal stays JSON, so it is not retried ===\n";
// Retrying a real refusal would re-submit the booking and burn the throttle
// twice. The client only retries the -1 above; this proves the two differ.
$good = wp_create_nonce( 'bhela_bm_booking' );
$out  = bhela_cache_call( 'bhela_bm_availability', array( 'nonce' => $good, 'date' => '' ) );
ok( is_array( $out['json'] ), 'a missing date comes back as JSON, not -1', substr( $out['raw'], 0, 60 ) );
ok( isset( $out['json']['success'] ) && false === $out['json']['success'], '…and reports failure in the payload' );

echo "\n=== 6. no nonce is baked into what the client ships ===\n";
$js = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/assets/booking.js' );
ok( false === strpos( $js, "params.append('nonce', bhelaBM.nonce)" ),
	'no call sends the nonce printed into the page' );
ok( substr_count( $js, 'postWithNonce(params' ) >= 4,
	'every AJAX call goes through the refreshing helper', (string) substr_count( $js, 'postWithNonce(params' ) );
ok( false !== strpos( $js, 'refreshNonce();' ), 'and a fresh one is fetched on load' );

// The helper must read the body as text — .json() would throw on the -1 and the
// retry could never happen.
ok( false !== strpos( $js, 'return r.text();' ), 'the helper reads text so it can see a -1' );
// Exactly one place may parse a raw response itself: the nonce fetch.
ok( 1 === substr_count( $js, 'return r.json();' ),
	'nothing double-parses an already-decoded response', (string) substr_count( $js, 'return r.json();' ) );

bhela_test_done();
