<?php
/**
 * A QR encoder, because the plugin has no dependencies and this needed none.
 *
 * Every certificate carries a QR pointing at its verification page (the brief's §20).
 * Three ways to get one, and two of them are worse than writing this:
 *
 * - A remote image service (`api.qrserver.com` and friends) would send **the certificate
 *   number of every document BHELA issues** to a third party, and would leave a blank
 *   square on any printed page produced offline or behind a blocked CDN. A verification
 *   mark that silently disappears is worse than none.
 * - A vendored library would be the first third-party code in this plugin, and it would
 *   be carried for one 40-character URL.
 *
 * So: byte mode, error-correction level **M**, versions 1–6 (up to 106 bytes, and the
 * URLs are about 45). Level M recovers from roughly 15% damage, which is the usual
 * choice for something printed and then photographed. Versions past 6 are deliberately
 * unsupported — they are the ones needing the version-information blocks, and supporting
 * a size nothing here produces would be untested code on a page that must not fail.
 *
 * Output is **inline SVG**: it prints true black on true white at any size, has no file
 * on disk to serve or clean up, and survives being saved to PDF. Raster output would
 * hit `print-color-adjust: economy` and can lose a scanner its contrast margin, which
 * is the same trap the invoice's payment QRs already documented.
 *
 * Verified by round trip rather than by inspection: `tests/qr-test.php` encodes a set of
 * strings, renders the matrices, and decodes them again with an independent decoder. An
 * encoder that "looks right" and does not scan is the whole risk here.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * GF(256) — the arithmetic Reed-Solomon needs
 * ========================================================= */

/**
 * Exponent and log tables for GF(256) with primitive polynomial 0x11D.
 *
 * Built once per request. The exp table is doubled so a product's exponent never has to
 * be reduced modulo 255 at the call site.
 */
function bhela_bm_qr_gf() {
	static $tables = null;
	if ( null !== $tables ) {
		return $tables;
	}
	$exp = array_fill( 0, 512, 0 );
	$log = array_fill( 0, 256, 0 );
	$x   = 1;
	for ( $i = 0; $i < 255; $i++ ) {
		$exp[ $i ]   = $x;
		$log[ $x ]   = $i;
		$x         <<= 1;
		if ( $x & 0x100 ) {
			$x ^= 0x11D;
		}
	}
	for ( $i = 255; $i < 512; $i++ ) {
		$exp[ $i ] = $exp[ $i - 255 ];
	}
	$tables = array( 'exp' => $exp, 'log' => $log );
	return $tables;
}

/** Multiply two field elements. Zero is absorbing — the log table has no entry for it. */
function bhela_bm_qr_mul( $a, $b ) {
	if ( 0 === $a || 0 === $b ) {
		return 0;
	}
	$gf = bhela_bm_qr_gf();
	return $gf['exp'][ $gf['log'][ $a ] + $gf['log'][ $b ] ];
}

/**
 * The generator polynomial for `$n` error-correction codewords.
 *
 * (x - a^0)(x - a^1)…(x - a^(n-1)), expanded in the field.
 */
function bhela_bm_qr_rs_generator( $n ) {
	$gf   = bhela_bm_qr_gf();
	$poly = array( 1 );
	for ( $i = 0; $i < $n; $i++ ) {
		$next = array_fill( 0, count( $poly ) + 1, 0 );
		foreach ( $poly as $j => $coef ) {
			// Index 0 is the LEADING coefficient, so multiplying by x shifts a term
			// UP an index and the a^i term lands one further along. Writing these two
			// the other way round builds the polynomial backwards — it still looks
			// like a generator, and every code it produces is undecodable.
			$next[ $j ]     ^= $coef;
			$next[ $j + 1 ] ^= bhela_bm_qr_mul( $coef, $gf['exp'][ $i ] );
		}
		$poly = $next;
	}
	return $poly;
}

/** The `$n` error-correction codewords for one block of data. */
function bhela_bm_qr_rs( $data, $n ) {
	$gen = bhela_bm_qr_rs_generator( $n );
	$rem = array_fill( 0, $n, 0 );
	foreach ( $data as $byte ) {
		$factor = $byte ^ $rem[0];
		array_shift( $rem );
		$rem[] = 0;
		foreach ( $gen as $i => $coef ) {
			if ( $i > 0 ) {
				$rem[ $i - 1 ] ^= bhela_bm_qr_mul( $coef, $factor );
			}
		}
	}
	return $rem;
}

/* =========================================================
 * The version tables (level M only)
 * ========================================================= */

/**
 * Per version: byte capacity, EC codewords per block, and the block layout.
 *
 * `blocks` is a list of [count, data codewords]. Level M is the only level supported,
 * so there is one table rather than four — an unused table is a table nothing checks.
 */
function bhela_bm_qr_specs() {
	return array(
		1 => array( 'cap' => 14,  'ec' => 10, 'blocks' => array( array( 1, 16 ) ), 'align' => array() ),
		2 => array( 'cap' => 26,  'ec' => 16, 'blocks' => array( array( 1, 28 ) ), 'align' => array( 6, 18 ) ),
		3 => array( 'cap' => 42,  'ec' => 26, 'blocks' => array( array( 1, 44 ) ), 'align' => array( 6, 22 ) ),
		4 => array( 'cap' => 62,  'ec' => 18, 'blocks' => array( array( 2, 32 ) ), 'align' => array( 6, 26 ) ),
		5 => array( 'cap' => 84,  'ec' => 24, 'blocks' => array( array( 2, 43 ) ), 'align' => array( 6, 30 ) ),
		6 => array( 'cap' => 106, 'ec' => 16, 'blocks' => array( array( 4, 27 ) ), 'align' => array( 6, 34 ) ),
	);
}

/* =========================================================
 * Encoding
 * ========================================================= */

/**
 * The full codeword stream — data blocks interleaved with their EC blocks.
 *
 * @return int[]|null Null when the text does not fit in version 6.
 */
function bhela_bm_qr_codewords( $text, $version ) {
	$specs = bhela_bm_qr_specs();
	$spec  = $specs[ $version ];
	$bytes = array_values( unpack( 'C*', $text ) );
	if ( count( $bytes ) > $spec['cap'] ) {
		return null;
	}

	// Mode indicator 0100 (byte) + an 8-bit length, which is the field width for every
	// version this encoder supports.
	$bits = '0100' . str_pad( decbin( count( $bytes ) ), 8, '0', STR_PAD_LEFT );
	foreach ( $bytes as $b ) {
		$bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
	}

	$total_data = 0;
	foreach ( $spec['blocks'] as $group ) {
		$total_data += $group[0] * $group[1];
	}
	$capacity_bits = $total_data * 8;

	// Terminator, then pad to a byte boundary, then the alternating pad bytes.
	$bits .= str_repeat( '0', min( 4, $capacity_bits - strlen( $bits ) ) );
	if ( strlen( $bits ) % 8 ) {
		$bits .= str_repeat( '0', 8 - ( strlen( $bits ) % 8 ) );
	}
	$pad = array( 0xEC, 0x11 );
	$i   = 0;
	while ( strlen( $bits ) < $capacity_bits ) {
		$bits .= str_pad( decbin( $pad[ $i % 2 ] ), 8, '0', STR_PAD_LEFT );
		$i++;
	}

	$stream = array();
	foreach ( str_split( $bits, 8 ) as $byte ) {
		$stream[] = bindec( $byte );
	}

	// Split into blocks, compute EC for each, then interleave — data codeword 0 of every
	// block, then codeword 1 of every block, and the same again for the EC codewords.
	$data_blocks = array();
	$ec_blocks   = array();
	$offset      = 0;
	foreach ( $spec['blocks'] as $group ) {
		for ( $b = 0; $b < $group[0]; $b++ ) {
			$block         = array_slice( $stream, $offset, $group[1] );
			$offset       += $group[1];
			$data_blocks[] = $block;
			$ec_blocks[]   = bhela_bm_qr_rs( $block, $spec['ec'] );
		}
	}

	$out = array();
	$max = 0;
	foreach ( $data_blocks as $block ) {
		$max = max( $max, count( $block ) );
	}
	for ( $i = 0; $i < $max; $i++ ) {
		foreach ( $data_blocks as $block ) {
			if ( isset( $block[ $i ] ) ) {
				$out[] = $block[ $i ];
			}
		}
	}
	for ( $i = 0; $i < $spec['ec']; $i++ ) {
		foreach ( $ec_blocks as $block ) {
			if ( isset( $block[ $i ] ) ) {
				$out[] = $block[ $i ];
			}
		}
	}
	return $out;
}

/* =========================================================
 * The matrix
 * ========================================================= */

/** Finder, separator, timing, alignment and the one dark module. */
function bhela_bm_qr_place_function_patterns( &$m, &$fixed, $size, $align ) {
	$finder = function ( $row, $col ) use ( &$m, &$fixed, $size ) {
		for ( $r = -1; $r <= 7; $r++ ) {
			for ( $c = -1; $c <= 7; $c++ ) {
				$rr = $row + $r;
				$cc = $col + $c;
				if ( $rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size ) {
					continue;
				}
				$on = ( $r >= 0 && $r <= 6 && ( 0 === $r || 6 === $r ) )
					|| ( $c >= 0 && $c <= 6 && ( 0 === $c || 6 === $c ) )
					|| ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 );
				$in = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
				$m[ $rr ][ $cc ]     = ( $in && $on ) ? 1 : 0;
				$fixed[ $rr ][ $cc ] = true;
			}
		}
	};
	$finder( 0, 0 );
	$finder( 0, $size - 7 );
	$finder( $size - 7, 0 );

	for ( $i = 8; $i < $size - 8; $i++ ) {
		$bit                = ( 0 === $i % 2 ) ? 1 : 0;
		$m[6][ $i ]         = $bit;
		$fixed[6][ $i ]     = true;
		$m[ $i ][6]         = $bit;
		$fixed[ $i ][6]     = true;
	}

	foreach ( $align as $r ) {
		foreach ( $align as $c ) {
			// The three that would sit on a finder are skipped, which is what the
			// centres table means rather than a special case.
			if ( ( 6 === $r && 6 === $c )
				|| ( 6 === $r && $c === $size - 7 )
				|| ( $r === $size - 7 && 6 === $c ) ) {
				continue;
			}
			for ( $dr = -2; $dr <= 2; $dr++ ) {
				for ( $dc = -2; $dc <= 2; $dc++ ) {
					$on                             = ( 2 === max( abs( $dr ), abs( $dc ) ) || ( 0 === $dr && 0 === $dc ) );
					$m[ $r + $dr ][ $c + $dc ]      = $on ? 1 : 0;
					$fixed[ $r + $dr ][ $c + $dc ]  = true;
				}
			}
		}
	}

	// The dark module, always at (4 * version + 9, 8).
	$m[ $size - 8 ][8]     = 1;
	$fixed[ $size - 8 ][8] = true;

	// Reserve the two format-information strips.
	for ( $i = 0; $i < 9; $i++ ) {
		if ( ! isset( $fixed[8][ $i ] ) || ! $fixed[8][ $i ] ) {
			$fixed[8][ $i ] = true;
			$m[8][ $i ]     = 0;
		}
		if ( ! isset( $fixed[ $i ][8] ) || ! $fixed[ $i ][8] ) {
			$fixed[ $i ][8] = true;
			$m[ $i ][8]     = 0;
		}
	}
	// The second copy is EIGHT modules along row 8 and SEVEN up column 8 — not eight
	// and eight. The extra one would be (size-8, 8), which is the dark module: reserving
	// it here quietly overwrote it with a light module a few lines after it was set.
	// Some decoders tolerate that; the specification does not, and a code that only
	// scans on forgiving readers is worse than one that fails everywhere.
	for ( $i = 0; $i < 8; $i++ ) {
		$fixed[8][ $size - 1 - $i ] = true;
		$m[8][ $size - 1 - $i ]     = 0;
	}
	for ( $i = 0; $i < 7; $i++ ) {
		$fixed[ $size - 1 - $i ][8] = true;
		$m[ $size - 1 - $i ][8]     = 0;
	}
}

/** Zigzag the codeword bits into every module the function patterns left alone. */
function bhela_bm_qr_place_data( &$m, $fixed, $size, $codewords ) {
	$bits = '';
	foreach ( $codewords as $cw ) {
		$bits .= str_pad( decbin( $cw ), 8, '0', STR_PAD_LEFT );
	}
	$i  = 0;
	$up = true;
	for ( $col = $size - 1; $col > 0; $col -= 2 ) {
		// Column 6 is the vertical timing pattern; the pair of columns shifts past it.
		if ( 6 === $col ) {
			$col--;
		}
		for ( $n = 0; $n < $size; $n++ ) {
			$row = $up ? ( $size - 1 - $n ) : $n;
			foreach ( array( $col, $col - 1 ) as $c ) {
				if ( ! empty( $fixed[ $row ][ $c ] ) ) {
					continue;
				}
				$m[ $row ][ $c ] = ( $i < strlen( $bits ) ) ? (int) $bits[ $i ] : 0;
				$i++;
			}
		}
		$up = ! $up;
	}
}

/** The eight mask conditions, by index. */
function bhela_bm_qr_mask_bit( $mask, $row, $col ) {
	switch ( $mask ) {
		case 0: return 0 === ( $row + $col ) % 2;
		case 1: return 0 === $row % 2;
		case 2: return 0 === $col % 3;
		case 3: return 0 === ( $row + $col ) % 3;
		case 4: return 0 === ( intdiv( $row, 2 ) + intdiv( $col, 3 ) ) % 2;
		case 5: return 0 === ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 );
		case 6: return 0 === ( ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2;
		case 7: return 0 === ( ( ( $row + $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2;
	}
	return false;
}

/** The four penalty rules, as the specification states them. */
function bhela_bm_qr_penalty( $m, $size ) {
	$score = 0;

	// 1 — runs of five or more identical modules, in both directions.
	for ( $pass = 0; $pass < 2; $pass++ ) {
		for ( $a = 0; $a < $size; $a++ ) {
			$run  = 1;
			$prev = -1;
			for ( $b = 0; $b < $size; $b++ ) {
				$v = $pass ? $m[ $b ][ $a ] : $m[ $a ][ $b ];
				if ( $v === $prev ) {
					$run++;
				} else {
					if ( $run >= 5 ) {
						$score += 3 + ( $run - 5 );
					}
					$run  = 1;
					$prev = $v;
				}
			}
			if ( $run >= 5 ) {
				$score += 3 + ( $run - 5 );
			}
		}
	}

	// 2 — every 2x2 block of one colour.
	for ( $r = 0; $r < $size - 1; $r++ ) {
		for ( $c = 0; $c < $size - 1; $c++ ) {
			$v = $m[ $r ][ $c ];
			if ( $v === $m[ $r ][ $c + 1 ] && $v === $m[ $r + 1 ][ $c ] && $v === $m[ $r + 1 ][ $c + 1 ] ) {
				$score += 3;
			}
		}
	}

	// 3 — the finder-like sequence, which a scanner could mistake for a finder.
	$a = array( 1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0 );
	$b = array( 0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1 );
	for ( $pass = 0; $pass < 2; $pass++ ) {
		for ( $i = 0; $i < $size; $i++ ) {
			$line = array();
			for ( $j = 0; $j < $size; $j++ ) {
				$line[] = $pass ? $m[ $j ][ $i ] : $m[ $i ][ $j ];
			}
			for ( $j = 0; $j + 11 <= $size; $j++ ) {
				$slice = array_slice( $line, $j, 11 );
				if ( $slice === $a || $slice === $b ) {
					$score += 40;
				}
			}
		}
	}

	// 4 — how far the dark proportion strays from half.
	$dark = 0;
	foreach ( $m as $row ) {
		$dark += array_sum( $row );
	}
	$pct    = ( $dark * 100 ) / ( $size * $size );
	$score += 10 * (int) floor( abs( $pct - 50 ) / 5 );

	return $score;
}

/** The 15-bit format string: EC level M (00) plus the mask, BCH-protected and XORed. */
function bhela_bm_qr_format_bits( $mask ) {
	$data = ( 0b00 << 3 ) | $mask;
	$rem  = $data << 10;
	for ( $i = 14; $i >= 10; $i-- ) {
		if ( $rem & ( 1 << $i ) ) {
			$rem ^= 0x537 << ( $i - 10 );
		}
	}
	return ( ( ( $data << 10 ) | $rem ) ^ 0x5412 ) & 0x7FFF;
}

/** Write the format bits into both of their reserved strips. */
function bhela_bm_qr_place_format( &$m, $size, $mask ) {
	$bits = bhela_bm_qr_format_bits( $mask );
	for ( $i = 0; $i < 15; $i++ ) {
		$bit = ( $bits >> $i ) & 1;
		// Copy one: down the left of the top-left finder, then along its bottom.
		if ( $i < 6 ) {
			$m[ $i ][8] = $bit;
		} elseif ( 6 === $i ) {
			$m[7][8] = $bit;
		} elseif ( 7 === $i ) {
			$m[8][8] = $bit;
		} elseif ( 8 === $i ) {
			$m[8][7] = $bit;
		} else {
			$m[8][ 14 - $i ] = $bit;
		}
		// Copy two: along the top-right, then up from the bottom-left.
		if ( $i < 8 ) {
			$m[8][ $size - 1 - $i ] = $bit;
		} else {
			$m[ $size - 15 + $i ][8] = $bit;
		}
	}
}

/**
 * The module matrix for a string, or null when it will not fit.
 *
 * @param string $text Up to 106 bytes.
 * @return array<int,array<int,int>>|null
 */
function bhela_bm_qr_matrix( $text ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return null;
	}

	$codewords = null;
	$version   = 0;
	foreach ( bhela_bm_qr_specs() as $v => $spec ) {
		$try = bhela_bm_qr_codewords( $text, $v );
		if ( null !== $try ) {
			$codewords = $try;
			$version   = $v;
			break;
		}
	}
	if ( null === $codewords ) {
		return null;                       // longer than version 6 at level M holds
	}

	$specs = bhela_bm_qr_specs();
	$size  = 17 + ( 4 * $version );
	$blank = array_fill( 0, $size, array_fill( 0, $size, 0 ) );
	$fixed = array_fill( 0, $size, array_fill( 0, $size, false ) );

	$m = $blank;
	bhela_bm_qr_place_function_patterns( $m, $fixed, $size, $specs[ $version ]['align'] );
	bhela_bm_qr_place_data( $m, $fixed, $size, $codewords );

	// Try all eight masks and keep the one the specification's penalty rules prefer.
	$best       = null;
	$best_score = PHP_INT_MAX;
	for ( $mask = 0; $mask < 8; $mask++ ) {
		$candidate = $m;
		for ( $r = 0; $r < $size; $r++ ) {
			for ( $c = 0; $c < $size; $c++ ) {
				if ( empty( $fixed[ $r ][ $c ] ) && bhela_bm_qr_mask_bit( $mask, $r, $c ) ) {
					$candidate[ $r ][ $c ] ^= 1;
				}
			}
		}
		bhela_bm_qr_place_format( $candidate, $size, $mask );
		$score = bhela_bm_qr_penalty( $candidate, $size );
		if ( $score < $best_score ) {
			$best_score = $score;
			$best       = $candidate;
		}
	}
	return $best;
}

/**
 * The QR as inline SVG, quiet zone included.
 *
 * One `<path>` of rectangles rather than one element per module: a version-6 code is
 * 1,681 modules, and 1,681 elements is a page a browser thinks twice about printing.
 *
 * @param string $text  What to encode.
 * @param int    $px    Rendered width in CSS pixels.
 * @param string $label Accessible name for the image.
 * @return string Empty when the text will not fit.
 */
function bhela_bm_qr_svg( $text, $px = 120, $label = '' ) {
	$m = bhela_bm_qr_matrix( $text );
	if ( ! $m ) {
		return '';
	}
	$size  = count( $m );
	$quiet = 4;                            // the specification's minimum, and scanners rely on it
	$span  = $size + ( 2 * $quiet );

	$path = '';
	foreach ( $m as $r => $row ) {
		foreach ( $row as $c => $on ) {
			if ( $on ) {
				$path .= sprintf( 'M%d %dh1v1h-1z', $c + $quiet, $r + $quiet );
			}
		}
	}

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" role="img" aria-label="%3$s" shape-rendering="crispEdges">'
			. '<rect width="%1$d" height="%1$d" fill="#fff"/><path d="%4$s" fill="#000"/></svg>',
		(int) $span,
		(int) $px,
		esc_attr( '' === $label ? __( 'Verification QR code', 'bhela-booking' ) : $label ),
		$path
	);
}
