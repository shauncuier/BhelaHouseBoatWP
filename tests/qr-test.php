<?php
/**
 * The QR encoder, pinned to matrices that were decoded by an independent decoder.
 *
 * PHP cannot read a QR code back, so "it looks like a QR code" is all a pure-PHP test
 * could ever assert — and the first version of this encoder looked exactly like one
 * while producing codes no scanner could read. The generator polynomial was being built
 * with its coefficients reversed, which is invisible in the output and fatal in it.
 *
 * So the matrices below were generated, rendered to images, and **decoded back to their
 * original strings with OpenCV** before their hashes were written down here. This
 * harness asserts the encoder still produces exactly those matrices. If a change is
 * deliberate, re-run the round trip and update the hashes — do not simply move them,
 * because the hash is standing in for "a scanner can read this".
 *
 * The structural checks below are the cheap second line: they catch a matrix that is the
 * wrong size or has lost a finder pattern, which is the class of mistake that would make
 * every hash wrong at once and tell you nothing about why.
 */

require __DIR__ . '/bootstrap.php';

/**
 * Matrices verified by round trip on 2026-09-20 with cv2.QRCodeDetector.
 * text => [ version, sha1 of the concatenated rows ]
 */
$qr_vectors = array(
	'https://bhela.com/verify/BHELA-PC-2027-0001-V1'      => array( 4, 'd915fce543cd1e72309f99956e87dea8eb5d09b0' ),
	'https://bhela.com/verify/BHELA-IC-2026-0001-V1'      => array( 4, 'ec97fd7c1d43e59a827c65eb838a2d108d347f67' ),
	'A'                                                   => array( 1, '444d3cd9b5a3fc15d7b2816edba96777efb85194' ),
	'https://bhela.local/verify/BHELA-PC-2027-0099-V12'   => array( 4, '485ac558e6fc2d79c2c6639cb8e155aa99eaf95b' ),
	'http://example.org/verify/BHELA-IC-2026-0042-V3?x=1' => array( 4, '284928297e18c0d4f9d227b745bed029fb5cb297' ),
);

echo "=== 1. the verified matrices, unchanged ===\n";

foreach ( $qr_vectors as $qr_text => $qr_expect ) {
	$qr_m = bhela_bm_qr_matrix( $qr_text );
	ok( is_array( $qr_m ), 'encodes: ' . substr( $qr_text, 0, 34 ) );
	if ( ! is_array( $qr_m ) ) {
		continue;
	}
	$qr_flat = '';
	foreach ( $qr_m as $qr_row ) {
		$qr_flat .= implode( '', $qr_row );
	}
	$qr_version = ( count( $qr_m ) - 17 ) / 4;
	ok( (int) $qr_version === $qr_expect[0], '  picks version ' . $qr_expect[0], (string) $qr_version );
	ok( sha1( $qr_flat ) === $qr_expect[1], '  and matches the decoded-verified matrix', substr( sha1( $qr_flat ), 0, 12 ) );
}

echo "\n=== 2. version 6 is the ceiling, and it refuses past it ===\n";

ok( is_array( bhela_bm_qr_matrix( str_repeat( 'X', 106 ) ) ), '106 bytes still fits version 6' );
ok( null === bhela_bm_qr_matrix( str_repeat( 'X', 107 ) ),
	'107 refuses rather than producing something unreadable' );
ok( null === bhela_bm_qr_matrix( '' ), 'and an empty string encodes nothing' );

echo "\n=== 3. the structure a scanner looks for ===\n";

$qr_m    = bhela_bm_qr_matrix( 'https://bhela.com/verify/BHELA-IC-2026-0001-V1' );
$qr_size = count( $qr_m );

// Three finder patterns: dark ring, light ring, dark 3x3 core.
$qr_finder_ok = true;
foreach ( array( array( 0, 0 ), array( 0, $qr_size - 7 ), array( $qr_size - 7, 0 ) ) as $qr_at ) {
	list( $qr_r0, $qr_c0 ) = $qr_at;
	for ( $r = 0; $r < 7; $r++ ) {
		for ( $c = 0; $c < 7; $c++ ) {
			$edge = ( 0 === $r || 6 === $r || 0 === $c || 6 === $c );
			$core = ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 );
			$want = ( $edge || $core ) ? 1 : 0;
			if ( (int) $qr_m[ $qr_r0 + $r ][ $qr_c0 + $c ] !== $want ) {
				$qr_finder_ok = false;
			}
		}
	}
}
ok( $qr_finder_ok, 'all three finder patterns are intact' );

$qr_timing_ok = true;
for ( $i = 8; $i < $qr_size - 8; $i++ ) {
	$want = ( 0 === $i % 2 ) ? 1 : 0;
	if ( (int) $qr_m[6][ $i ] !== $want || (int) $qr_m[ $i ][6] !== $want ) {
		$qr_timing_ok = false;
	}
}
ok( $qr_timing_ok, 'both timing patterns alternate' );
ok( 1 === (int) $qr_m[ $qr_size - 8 ][8], 'the dark module is where the specification puts it' );

echo "\n=== 4. the SVG it renders ===\n";

$qr_svg = bhela_bm_qr_svg( 'https://bhela.com/verify/BHELA-IC-2026-0001-V1', 120 );
ok( 0 === strpos( $qr_svg, '<svg' ), 'renders an inline SVG' );
ok( false !== strpos( $qr_svg, 'viewBox="0 0 41 41"' ),
	'sized to the matrix plus a four-module quiet zone, which scanners need' );
ok( false !== strpos( $qr_svg, 'fill="#fff"' ) && false !== strpos( $qr_svg, 'fill="#000"' ),
	'true black on true white — never a theme colour' );
ok( false === strpos( $qr_svg, 'http' ) || false === strpos( $qr_svg, '<image' ),
	'and fetches nothing from anywhere' );
ok( '' === bhela_bm_qr_svg( str_repeat( 'X', 500 ) ), 'too long renders nothing rather than a broken square' );

bhela_test_done();
