<?php
/**
 * Run every headless harness and summarise.
 *
 *   php tests/run.php              # everything
 *   php tests/run.php security ui  # only harnesses whose name contains these
 *
 * Why a runner rather than a shell loop: the harnesses need four PHP
 * extensions that the LocalWP CLI binary does not load by default, and getting
 * that wrong does not look like an error. Without curl the SMS balance check
 * reports "no working transports" and reads as a broken gateway; without
 * mbstring the OTP harness stops mid-run after printing a screen of passes.
 * Both cost real time to diagnose. This file works out what is missing and
 * re-launches each harness with exactly what it needs, so `php tests/run.php`
 * is correct no matter which PHP you happen to have on PATH.
 *
 * Each harness runs in its own process. They register post types, install
 * roles and create fixtures; sharing one process would let one harness's
 * global state decide another's result.
 *
 * @package BhelaBooking
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Command line only.\n" );
}

$required = array( 'mysqli', 'openssl', 'curl', 'mbstring' );
$missing  = array_values( array_filter( $required, fn( $e ) => ! extension_loaded( $e ) ) );

// Windows keeps the DLLs in ext/ beside the binary; on Linux/macOS the ini
// value is already right.
$ext_dir = ini_get( 'extension_dir' );
if ( $missing && ( ! $ext_dir || ! is_dir( $ext_dir ) ) ) {
	$guess   = dirname( PHP_BINARY ) . DIRECTORY_SEPARATOR . 'ext';
	$ext_dir = is_dir( $guess ) ? $guess : $ext_dir;
}

$flags = array();
if ( $missing ) {
	if ( $ext_dir && is_dir( $ext_dir ) ) {
		$flags[] = '-d';
		$flags[] = 'extension_dir=' . $ext_dir;
	}
	foreach ( $missing as $ext ) {
		$flags[] = '-d';
		$flags[] = 'extension=' . $ext;
	}
}

$filters = array_slice( $argv, 1 );
$files   = glob( __DIR__ . '/*-test.php' );

// A harness may be a Python file when PHP is the wrong tool for it — the
// version check also runs from a PostToolUse hook, in a shell where neither
// php nor jq exists. Skipped with a note if python is absent rather than
// silently dropped, since a check nobody notices missing is worse than none.
$python = null;
foreach ( array( 'python3', 'python' ) as $candidate ) {
	$probe = shell_exec( escapeshellarg( $candidate ) . ' --version 2>&1' );
	if ( $probe && preg_match( '/^Python 3/', trim( $probe ) ) ) {
		$python = $candidate;
		break;
	}
}
$py_files = glob( __DIR__ . '/*-test.py' );
if ( $python ) {
	$files = array_merge( $files, $py_files );
} elseif ( $py_files ) {
	printf( "  SKIPPED (no python3 on PATH): %s\n\n", implode( ', ', array_map( 'basename', $py_files ) ) );
}

sort( $files );
if ( $filters ) {
	$files = array_values( array_filter( $files, function ( $f ) use ( $filters ) {
		foreach ( $filters as $needle ) {
			if ( false !== stripos( basename( $f ), $needle ) ) {
				return true;
			}
		}
		return false;
	} ) );
}

if ( ! $files ) {
	fwrite( STDERR, "No harnesses matched.\n" );
	exit( 1 );
}

printf( "PHP %s · %s\n", PHP_VERSION, PHP_BINARY );
printf( "extensions loaded on demand: %s\n\n", $missing ? implode( ', ', $missing ) : 'none needed' );

// Clear anything a previously crashed run left in the database, so a stale
// fixture cannot be counted twice — thirteen leftover cost sheets once turned
// "13 approved trips" into 26 and failed eight assertions that were fine.
$sweep = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . implode( ' ', array_map( 'escapeshellarg', $flags ) )
	. ' ' . escapeshellarg( __DIR__ . '/sweep.php' ) . ' 2>&1' );
if ( $sweep && ! preg_match( '/^0 fixture/m', $sweep ) ) {
	echo trim( $sweep ) . "\n\n";
}

$results = array();
$started = microtime( true );

foreach ( $files as $file ) {
	$is_py = '.py' === substr( $file, -3 );
	$name  = basename( $file, $is_py ? '.py' : '.php' );
	$cmd   = $is_py
		? escapeshellarg( $python ) . ' ' . escapeshellarg( $file )
		: escapeshellarg( PHP_BINARY ) . ' '
			. implode( ' ', array_map( 'escapeshellarg', $flags ) ) . ' '
			. escapeshellarg( $file );

	$t0 = microtime( true );
	exec( $cmd . ' 2>&1', $out, $code );
	$secs = microtime( true ) - $t0;

	$results[] = array(
		'name' => $name,
		'code' => $code,
		'secs' => $secs,
		'out'  => $out,
		// The count comes from the harness's own summary line.
		'note' => trim( (string) preg_replace( '/.*?(ALL CHECKS PASSED|\*\*\* .*? \*\*\*).*/s', '$1', implode( "\n", $out ) ) ),
	);
	printf( "  %-4s %-18s %5.1fs  %s\n", 0 === $code ? 'ok' : 'FAIL', $name, $secs,
		0 === $code ? '' : '↓ output below' );
	$out = array();
}

$failed = array_values( array_filter( $results, fn( $r ) => 0 !== $r['code'] ) );

foreach ( $failed as $r ) {
	printf( "\n%s\n%s\n%s\n", str_repeat( '=', 66 ), $r['name'], str_repeat( '=', 66 ) );
	// Only the interesting lines: failures, section headings and the summary.
	foreach ( $r['out'] as $line ) {
		if ( preg_match( '/\[FAIL\]|^===|FAILURE|FATAL|DIED EARLY|Fatal error|Warning:|Notice:/', $line ) ) {
			echo $line . "\n";
		}
	}
}

printf(
	"\n%s — %d of %d harnesses passed in %.1fs\n",
	$failed ? 'FAILED' : 'PASSED',
	count( $results ) - count( $failed ),
	count( $results ),
	microtime( true ) - $started
);

exit( $failed ? 1 : 0 );
