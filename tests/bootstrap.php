<?php
/**
 * Shared bootstrap for the headless (CLI) regression harnesses.
 *
 * The browser suite next door (bhela-tests.php) runs inside a logged-in
 * WordPress request. These harnesses run from the command line instead, which
 * is what makes them usable from a script, a hook or a release check — but it
 * means each one has to boot WordPress itself, and that is where the sharp
 * edges are. This file owns all of them so a test file contains nothing but
 * assertions:
 *
 *   1. Finding wp-load.php without hardcoding anyone's home directory.
 *   2. Reaching the database. LocalWP serves MySQL on a per-site TCP port and
 *      wp-config.php says 'localhost', which the CLI cannot resolve to the
 *      socket the web server uses. DB_HOST is defined here, before wp-config
 *      is read, from the port LocalWP records for this site.
 *   3. Failing loudly. A harness that dies half way — a missing PHP extension
 *      will do it — used to print a page of PASS lines and no summary, which
 *      reads exactly like success. Nothing may exit 0 unless it says so.
 *
 * @package BhelaBooking
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "These harnesses are command-line only. For the browser suite, open bhela-tests.php.\n" );
}

/**
 * The MySQL host:port to reach this site's database from the CLI.
 *
 * Order of preference: an explicit override, then whatever LocalWP has
 * recorded for the site that owns this checkout, then plain localhost for a
 * normal server where wp-config's own value already works.
 *
 * @param string $wp_root Absolute path to the WordPress root.
 * @return string
 */
function bhela_test_db_host( $wp_root ) {
	$env = getenv( 'BHELA_TEST_DB_HOST' );
	if ( $env ) {
		return $env;
	}

	$home = getenv( 'USERPROFILE' ) ?: getenv( 'HOME' );
	$candidates = array(
		getenv( 'APPDATA' ) . '/Local/sites.json',                       // Windows
		$home . '/Library/Application Support/Local/sites.json',         // macOS
		$home . '/.config/Local/sites.json',                             // Linux
	);

	// The site whose recorded path contains this checkout is ours. Comparing
	// paths rather than trusting the site name means a renamed or duplicated
	// site still resolves correctly.
	$root = strtolower( str_replace( '\\', '/', realpath( $wp_root ) ?: $wp_root ) );
	foreach ( $candidates as $file ) {
		if ( ! $file || ! is_file( $file ) ) {
			continue;
		}
		$sites = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $sites ) ) {
			continue;
		}
		foreach ( $sites as $site ) {
			$path = (string) ( $site['path'] ?? '' );
			if ( '' === $path ) {
				continue;
			}
			// LocalWP stores paths tilde-relative to the home directory.
			$path = str_replace( '\\', '/', $path );
			if ( 0 === strpos( $path, '~' ) ) {
				$path = str_replace( '\\', '/', (string) $home ) . substr( $path, 1 );
			}
			// Compare on a directory boundary. A plain prefix test matches the
			// wrong site whenever one name starts with another — "bhela" and
			// "bhela-house-boat" live side by side here, and the first one wins
			// a prefix test while pointing at a different database.
			$path = rtrim( strtolower( $path ), '/' ) . '/';
			if ( 0 !== strpos( rtrim( $root, '/' ) . '/', $path ) ) {
				continue;
			}
			foreach ( array( 'mariadb', 'mysql' ) as $service ) {
				$port = $site['services'][ $service ]['ports']['MYSQL'][0] ?? 0;
				if ( $port ) {
					return '127.0.0.1:' . (int) $port;
				}
			}
		}
	}

	return 'localhost';
}

/* ---------- Boot ---------- */

// tests/ sits directly under wp-content, so the WordPress root is two up.
$bhela_wp_root = dirname( __DIR__, 2 );
if ( ! is_file( $bhela_wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Could not find wp-load.php above " . __DIR__ . "\n" );
	exit( 1 );
}

// Must be defined before wp-config.php runs; its own define() then no-ops.
define( 'DB_HOST', bhela_test_db_host( $bhela_wp_root ) );
define( 'WP_USE_THEMES', false );
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$GLOBALS['fails']    = 0;
$GLOBALS['checks']   = 0;
$GLOBALS['finished'] = false;

// Registered BEFORE WordPress boots, and this ordering is load-bearing. When
// the database was unreachable, wp-load.php called wp_die() and exited 0 — so
// with the handler registered afterwards it never ran, every harness "passed",
// and the runner reported 9 of 9 green against a database it had never
// reached. A suite that goes green when nothing ran is worse than no suite.
bhela_test_register_shutdown();

require_once $bhela_wp_root . '/wp-load.php';

// wp-load stops at the front end. Harnesses that render an admin screen need
// the pieces admin-header.php would normally have pulled in — set_current_screen()
// and add_meta_box() among them.
foreach ( array( 'plugin', 'class-wp-screen', 'screen', 'template', 'misc' ) as $bhela_admin_inc ) {
	$bhela_file = ABSPATH . "wp-admin/includes/$bhela_admin_inc.php";
	if ( is_file( $bhela_file ) ) {
		require_once $bhela_file;
	}
}

if ( ! function_exists( 'bhela_bm_get_settings' ) ) {
	fwrite( STDERR, "The BHELA Booking Engine plugin is not active.\n" );
	exit( 1 );
}

/* ---------- Assertions ---------- */

/**
 * One assertion.
 *
 * @param bool   $cond  What must be true.
 * @param string $label What it means, in plain words.
 * @param string $extra The actual value, when knowing it helps.
 */
function ok( $cond, $label, $extra = '' ) {
	$GLOBALS['checks']++;
	if ( ! $cond ) {
		$GLOBALS['fails']++;
	}
	printf( "  [%s] %s%s\n", $cond ? 'PASS' : 'FAIL', $label, '' !== $extra ? "  ($extra)" : '' );
}

/** Call at the end of every harness. Its absence is itself a failure. */
function bhela_test_done() {
	$GLOBALS['finished'] = true;
}

/**
 * Load plugin modules and register the post types they define.
 *
 * @param string ...$modules File names under includes/, without .php.
 */
function bhela_test_modules( ...$modules ) {
	foreach ( $modules as $m ) {
		require_once WP_PLUGIN_DIR . "/bhela-booking/includes/$m.php";
	}
	foreach ( array(
		'bhela_bm_register_cpt',
		'bhela_bm_register_cost_cpt',
		'bhela_bm_register_expense_cpt',
		'bhela_bm_register_salary_cpt',
	) as $fn ) {
		if ( function_exists( $fn ) ) {
			$fn();
		}
	}
	if ( function_exists( 'bhela_bm_install_roles' ) ) {
		bhela_bm_install_roles();
	}
}

/**
 * The exit code is the contract with run.php.
 *
 * Three ways a harness can end, and only one of them is a pass:
 *
 *  - It reached bhela_test_done(). Exit code is the failure count.
 *  - It hit a fatal. Reported as such.
 *  - It stopped somewhere in between. The OTP harness did exactly this when
 *    mbstring was missing — 27 passes, then silence — and WordPress does it
 *    too when the database is unreachable, by calling wp_die() and exiting
 *    zero. Both are deaths, not passes.
 */
function bhela_test_register_shutdown() {
	register_shutdown_function( function () {
		$err = error_get_last();
		if ( $err && in_array( $err['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ), true ) ) {
			printf( "\n*** FATAL: %s in %s:%d ***\n", $err['message'], $err['file'], $err['line'] );
			exit( 1 );
		}
		if ( empty( $GLOBALS['finished'] ) ) {
			print( "\n*** DIED EARLY — no summary reached. ***\n" );
			print( "    Usual causes: the site is not running (start it in LocalWP),\n" );
			print( "    or a PHP extension is missing. Run through run.php, which loads them.\n" );
			exit( 1 );
		}
		// Utilities (sweep.php) boot the same way but assert nothing, so a
		// "0 checks" summary from them would only be noise.
		if ( defined( 'BHELA_TEST_QUIET' ) && BHELA_TEST_QUIET ) {
			exit( $GLOBALS['fails'] ? 1 : 0 );
		}
		printf(
			"\n%s (%d checks)\n",
			$GLOBALS['fails'] ? sprintf( '*** %d FAILURE(S) ***', $GLOBALS['fails'] ) : 'ALL CHECKS PASSED',
			$GLOBALS['checks']
		);
		exit( $GLOBALS['fails'] ? 1 : 0 );
	} );
}
