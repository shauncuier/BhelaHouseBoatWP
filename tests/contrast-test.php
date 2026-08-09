<?php
/**
 * Every text-on-background pair in the admin design system, measured against
 * WCAG AA (4.5:1 for body text).
 *
 * Reads the token values out of admin.css rather than restating them, so
 * editing a colour there is what this checks — a copy here would only ever
 * prove the copy was consistent with itself.
 *
 * Pure string work: no WordPress, no database.
 */
$css = file_get_contents( dirname( __DIR__ ) . '/plugins/bhela-booking/assets/admin.css' );
if ( ! $css ) {
	fwrite( STDERR, "admin.css not found.
" );
	exit( 1 );
}

function lum( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$c = array();
	foreach ( array( 0, 2, 4 ) as $i ) {
		$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$c[]   = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio( $a, $b ) {
	$la = lum( $a );
	$lb = lum( $b );
	return round( ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 ), 2 );
}

// Pull the token values straight out of the file so this cannot drift from it.
preg_match_all( '/^\s*(--bha-[a-z-]+)\s*:\s*(#[0-9a-fA-F]{3,6})/m', $css, $m, PREG_SET_ORDER );
$t = array();
foreach ( $m as $row ) {
	$t[ $row[1] ] = $row[2];
}

$pairs = array(
	// Soft pills: ink on its own tint.
	'pill neutral'   => array( $t['--bha-neutral-ink'], $t['--bha-neutral-bg'] ),
	'pill progress'  => array( $t['--bha-progress-ink'], $t['--bha-progress-bg'] ),
	'pill good'      => array( $t['--bha-good-ink'], $t['--bha-good-bg'] ),
	'pill attention' => array( $t['--bha-attention-ink'], $t['--bha-attention-bg'] ),
	'pill danger'    => array( $t['--bha-danger-ink'], $t['--bha-danger-bg'] ),
	// Solid pills: white on the tone.
	'solid neutral'   => array( '#ffffff', $t['--bha-neutral'] ),
	'solid progress'  => array( '#ffffff', $t['--bha-progress'] ),
	'solid good'      => array( '#ffffff', $t['--bha-good'] ),
	'solid attention' => array( '#ffffff', $t['--bha-attention'] ),
	'solid danger'    => array( '#ffffff', $t['--bha-danger'] ),
	// Header band: white over each end of the gradient.
	'band left'  => array( '#ffffff', $t['--bha-ink'] ),
	'band right' => array( '#ffffff', $t['--bha-teal'] ),
	// Figures on a card, and the muted label beside them.
	'text on card'    => array( $t['--bha-text'], $t['--bha-surface'] ),
	'muted on card'   => array( $t['--bha-muted'], $t['--bha-surface'] ),
	'muted on canvas' => array( $t['--bha-muted'], $t['--bha-canvas'] ),
	'good on card'    => array( $t['--bha-good'], $t['--bha-surface'] ),
	'danger on card'  => array( $t['--bha-danger'], $t['--bha-surface'] ),
	'attention/card'  => array( $t['--bha-attention'], $t['--bha-surface'] ),
	// Callouts.
	'callout good'      => array( $t['--bha-good-ink'], $t['--bha-good-bg'] ),
	'callout attention' => array( $t['--bha-attention-ink'], $t['--bha-attention-bg'] ),
	// Settings tab strip.
	'tab idle'   => array( '#9fd8d2', '#0d3339' ),
	'tab active' => array( '#ffffff', '#0d3339' ),
);

$fail = 0;
foreach ( $pairs as $name => list( $fg, $bg ) ) {
	$r    = ratio( $fg, $bg );
	$pass = $r >= 4.5;
	if ( ! $pass ) {
		$fail++;
	}
	printf( "  [%s] %-18s %s on %s = %.2f:1\n", $pass ? 'AA ' : 'FAIL', $name, $fg, $bg, $r );
}
printf(
	"
%s (%d pairs)
",
	$fail ? sprintf( '*** %d PAIR(S) BELOW AA ***', $fail ) : 'ALL CHECKS PASSED - every pair meets AA (4.5:1)',
	count( $pairs )
);
exit( $fail ? 1 : 0 );
