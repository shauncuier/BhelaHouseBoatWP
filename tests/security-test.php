<?php
/** Dev helper: regression tests for the security audit's two findings. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'invoice' );
wp_set_current_user( 1 );

echo "=== 1. staff roles hold no core capability beyond `read` ===\n";
// Anything outside this list, on a BHELA role, is privilege the plugin has no
// business handing out.
$allowed_core = array( 'read' );
foreach ( array_keys( bhela_bm_roles() ) as $slug ) {
	$role  = get_role( $slug );
	$extra = array();
	// Plugin caps are either bhela_-prefixed extras (bhela_view_reports) or
	// post-type primitives with bhela in the middle (edit_others_bhela_costs).
	foreach ( array_keys( array_filter( (array) $role->capabilities ) ) as $cap ) {
		if ( false !== strpos( $cap, 'bhela' ) || in_array( $cap, $allowed_core, true ) ) {
			continue;
		}
		$extra[] = $cap;
	}
	ok( ! $extra, sprintf( '%-22s no stray core caps', $slug ), implode( ', ', $extra ) );
}

echo "\n=== 2. the specific caps that must never appear ===\n";
$forbidden = array( 'upload_files', 'edit_posts', 'edit_pages', 'manage_options', 'list_users',
	'promote_users', 'edit_users', 'activate_plugins', 'edit_theme_options', 'unfiltered_html',
	'unfiltered_upload', 'import', 'export', 'manage_categories' );
foreach ( array_keys( bhela_bm_roles() ) as $slug ) {
	$role = get_role( $slug );
	$has  = array_values( array_filter( $forbidden, fn( $c ) => $role->has_cap( $c ) ) );
	ok( ! $has, sprintf( '%-22s clean', $slug ), implode( ', ', $has ) );
}

echo "\n=== 3. a legacy role keeps its upload_files until the sync runs ===\n";
$victim = get_role( 'bhela_cost_preparer' );
$victim->add_cap( 'upload_files' );          // what v2.22 and earlier left behind
ok( get_role( 'bhela_cost_preparer' )->has_cap( 'upload_files' ), 'legacy state reproduced' );
bhela_bm_install_roles();
ok( ! get_role( 'bhela_cost_preparer' )->has_cap( 'upload_files' ), 'sync takes it back' );
ok( get_role( 'bhela_cost_preparer' )->has_cap( 'read' ), '…and `read` survives, so wp-admin still opens' );
ok( get_role( 'bhela_cost_preparer' )->has_cap( 'edit_bhela_costs' ), '…and the role still works' );

echo "\n=== 4. the role sync re-runs on upgrade ===\n";
ok( 10 === BHELA_BM_ROLES_VERSION, 'BHELA_BM_ROLES_VERSION bumped', (string) BHELA_BM_ROLES_VERSION );
update_option( 'bhela_bm_roles_version', 6 );
get_role( 'bhela_manager' )->add_cap( 'upload_files' );
bhela_bm_maybe_install_roles();
ok( ! get_role( 'bhela_manager' )->has_cap( 'upload_files' ), 'an existing site is cleaned automatically' );
ok( (int) get_option( 'bhela_bm_roles_version' ) === BHELA_BM_ROLES_VERSION, 'version stamped forward' );

echo "\n=== 5. the Team screen cannot grant a core capability ===\n";
$granted = array();
foreach ( bhela_bm_permissions() as $key => $perm ) {
	foreach ( $perm['caps'] as $cap ) {
		if ( 0 !== strpos( $cap, 'bhela_' ) && ! preg_match( '/_bhela_(booking|cost|expense|salar|inv)/', $cap ) ) {
			$granted[] = "$key => $cap";
		}
	}
}
ok( ! $granted, 'every grantable capability is one of ours', implode( ', ', $granted ) );
// A crafted POST asking for manage_options must be discarded, not stored.
$evil = bhela_bm_normalise_perms( array( 'manage_options', 'edit_users', 'reports', 'bookings_edit', '../../etc' ) );
ok( ! in_array( 'manage_options', $evil, true ) && ! in_array( 'edit_users', $evil, true ),
	'unknown keys dropped by normalise', implode( ',', $evil ) );
ok( in_array( 'reports', $evil, true ), 'legitimate keys survive' );

echo "\n=== 6. invoice link ===\n";
$b = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZSEC guest' ) );
update_post_meta( $b, '_bhela_phone', '01711111111' );
update_post_meta( $b, '_bhela_total', 60000 );
$key = bhela_bm_invoice_key( $b );
ok( strlen( $key ) >= 32, 'key is a full-length wp_hash', strlen( $key ) . ' chars' );
ok( $key !== bhela_bm_invoice_key( $b + 1 ), 'key is per booking' );
$src = file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/invoice.php' );
ok( false !== strpos( $src, 'hash_equals(' ), 'timing-safe comparison' );
$pos_guard = strpos( $src, 'You are not allowed to view this invoice' );
$pos_cache = strpos( $src, 'nocache_headers()' );
ok( false !== $pos_cache, 'nocache_headers() present' );
ok( $pos_cache > $pos_guard, '…and it runs after the authorisation check, before any output' );
ok( false !== strpos( $src, 'X-Robots-Tag' ), 'noindex sent as a header, not only a meta tag' );

echo "\n=== 7. no entry point lost its nonce or capability check ===\n";
$plugin = '';
foreach ( glob( WP_PLUGIN_DIR . '/bhela-booking/includes/*.php' ) as $f ) {
	$plugin .= file_get_contents( $f );
}
preg_match_all( "/add_action\(\s*'(wp_ajax_(?!nopriv)[a-z_]+|admin_post_(?!nopriv)[a-z_]+)'\s*,\s*'([a-z_]+)'/", $plugin, $m, PREG_SET_ORDER );
// Endpoints a logged-out visitor is meant to reach. Everything not listed here
// must carry a nonce and a capability check, so adding a public endpoint is a
// deliberate edit to this line rather than something that slips through.
//
// bhela_bm_ajax_nonce is the one that needs justifying: it issues a booking
// nonce and cannot itself demand one, or a visitor on a cached page could
// never obtain a valid one. It grants nothing new — the same nonce is already
// printed into the public booking page — and it reads no data and writes none.
//
// bhela_bm_ajax_coupon_check is public because a guest applies a coupon before they
// have any account, but it is the one that most resembles an oracle: it answers
// "is this code valid" to anyone who asks. It therefore checks a nonce, throttles
// FAILED attempts per IP, and refuses an unknown code with a generic message so it
// cannot be used to enumerate the coupon list. It reads no guest data and writes
// nothing — redeeming is a separate function, reached only when a booking is made.
$public = array( 'bhela_bm_ajax_submit', 'bhela_bm_ajax_availability', 'bhela_bm_ajax_track',
	'bhela_bm_otp_ajax_send', 'bhela_bm_otp_ajax_verify', 'bhela_bm_review_submit',
	'bhela_bm_ajax_coupon_check', 'bhela_bm_ajax_nonce' );
// A separate, narrower exemption. Being public is not a reason to skip the
// nonce — every other endpoint above still checks one. Only an endpoint whose
// job is to *hand out* the nonce can be excused from presenting one, and there
// is exactly one of those.
$nonceless = array( 'bhela_bm_ajax_nonce' );

$bad = array();
foreach ( $m as $row ) {
	$fn = $row[2];
	$i  = strpos( $plugin, "function $fn(" );
	if ( false === $i ) {
		continue;
	}
	$body = substr( $plugin, $i, 2200 );
	if ( ! in_array( $fn, $nonceless, true )
		&& ! preg_match( '/check_ajax_referer|check_admin_referer|wp_verify_nonce/', $body ) ) {
		$bad[] = "$fn: no nonce";
	}
	if ( ! in_array( $fn, $public, true ) && ! preg_match( '/current_user_can/', $body ) ) {
		$bad[] = "$fn: no capability check";
	}
}
// The exemption must stay a single, deliberate hole.
ok( 1 === count( $nonceless ), 'exactly one endpoint is excused from presenting a nonce',
	implode( ', ', $nonceless ) );
ok( ! $bad, sprintf( 'all %d privileged endpoints guarded', count( $m ) ), implode( ' | ', $bad ) );

echo "\n=== 7b. client IP behind a proxy ===\n";
// Bangladesh mobile networks are CGNAT and CDNs are common, so REMOTE_ADDR is
// shared by thousands of people. Every throttle keys on this; getting it wrong
// locks real customers out of booking.
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
ok( '203.0.113.9' === bhela_bm_client_ip(), 'with no proxy configured it is REMOTE_ADDR', bhela_bm_client_ip() );

// A forged header must be ignored when the request did not come from a proxy
// we named — otherwise every limit is one header away from being bypassed.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';
ok( '203.0.113.9' === bhela_bm_client_ip(), 'an unsolicited X-Forwarded-For is ignored', bhela_bm_client_ip() );

ok( bhela_bm_ip_in_list( '198.51.100.7', array( '198.51.100.0/24' ) ), 'CIDR match' );
ok( ! bhela_bm_ip_in_list( '198.51.101.7', array( '198.51.100.0/24' ) ), 'CIDR non-match' );
ok( bhela_bm_ip_in_list( '203.0.113.9', array( '203.0.113.9' ) ), 'exact match' );
ok( ! bhela_bm_ip_in_list( 'not-an-ip', array( '203.0.113.9' ) ), 'garbage is not a match' );
ok( ! bhela_bm_ip_in_list( '203.0.113.9', array( '2001:db8::/32' ) ), 'v4 does not match a v6 range' );

// Every throttle must go through the helper, or one of them keeps the bug.
$throttled = '';
foreach ( array( 'frontend', 'otp', 'reviews' ) as $mod ) {
	$throttled .= (string) file_get_contents( WP_PLUGIN_DIR . "/bhela-booking/includes/$mod.php" );
}
ok( false === strpos( $throttled, 'REMOTE_ADDR' ), 'no throttle reads REMOTE_ADDR directly any more' );
ok( substr_count( $throttled, 'bhela_bm_client_ip()' ) >= 5, 'all five throttles use the helper',
	(string) substr_count( $throttled, 'bhela_bm_client_ip()' ) );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

echo "\n=== 8. no secret is committed ===\n";
$tracked = shell_exec( 'git -C "' . ABSPATH . '/wp-content" ls-files plugins/bhela-booking themes/bhela' );
$hits = array();
foreach ( array_filter( explode( "\n", (string) $tracked ) ) as $rel ) {
	$path = '' . ABSPATH . '/wp-content/' . trim( $rel );
	if ( ! is_file( $path ) ) {
		continue;
	}
	$body = file_get_contents( $path );
	if ( preg_match( '/X8c3aovvzpY9stPqQfMg|8809648909787/', $body ) ) {
		$hits[] = $rel;
	}
}
ok( ! $hits, 'the SMS gateway credentials are in no tracked file', implode( ', ', $hits ) );
ok( false === strpos( (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/bhela-booking.php' ), 'sms_api_key\' => \'' ),
	'no API key baked into the defaults' );

echo "\n=== cleanup ===\n";
wp_delete_post( $b, true );
bhela_bm_install_roles();
ok( true, 'done' );

bhela_test_done();
