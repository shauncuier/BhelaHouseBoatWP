<?php
/**
 * Shared admin UI — the components every BHELA screen is built from.
 *
 * These live in their own file rather than admin.php because every screen
 * module calls them, and burying them in the booking admin would make each
 * one depend on that file loading first. Their styling is assets/admin.css.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The banner every BHELA screen opens with.
 *
 * Screens used to hand-roll an `<h1>`, a lead paragraph and their own spacing,
 * which is how sixteen pages ended up with fourteen class prefixes between
 * them. Calling this instead means a screen declares what it is, not how it
 * looks.
 *
 * The `.wp-header-end` marker is not decorative: without it WordPress's
 * common.js relocates every admin notice to just after the first `<h1>` it
 * finds inside `.wrap` — which is inside the coloured band.
 *
 * @param string $icon    Leading emoji, matching the one in the menu.
 * @param string $title   Screen title. Escaped here.
 * @param string $lead    One sentence on what the screen is for. Escaped here.
 * @param string $actions Ready-escaped HTML for the right-hand button group.
 * @param string $class   Extra class — 'bha-head--attached' squares the bottom
 *                        corners for a screen whose tab strip joins the band.
 */
function bhela_bm_screen_header( $icon, $title, $lead = '', $actions = '', $class = '' ) {
	?>
	<div class="bha-head <?php echo esc_attr( $class ); ?>">
		<?php if ( $icon ) : ?>
			<span class="bha-head__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
		<?php endif; ?>
		<div class="bha-head__text">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $lead ) : ?>
				<p class="bha-head__lead"><?php echo esc_html( $lead ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $actions ) : ?>
			<div class="bha-head__actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes. ?></div>
		<?php endif; ?>
	</div>
	<hr class="wp-header-end">
	<?php
}

/**
 * A status pill, identical wherever status is shown.
 *
 * Two things carry meaning. The tone is one of five named semantics, so
 * "settled" is the same green on a cost sheet as on a booking. The weight is
 * the second axis: solid means the state has landed and nothing more is
 * expected, soft means it is still moving. That is what lets five tones cover
 * every vocabulary in the plugin without inventing a sixth hue.
 *
 * @param string $label Text inside the pill.
 * @param string $tone  neutral | progress | good | attention | danger.
 * @param bool   $solid Filled rather than tinted.
 * @return string Escaped HTML.
 */
function bhela_bm_status_pill( $label, $tone = 'neutral', $solid = false ) {
	$tones = array( 'neutral', 'progress', 'good', 'attention', 'danger' );
	if ( ! in_array( $tone, $tones, true ) ) {
		$tone = 'neutral';
	}
	return sprintf(
		'<span class="bha-pill bha-pill--%1$s%2$s">%3$s</span>',
		esc_attr( $tone ),
		$solid ? ' is-solid' : '',
		esc_html( $label )
	);
}

/**
 * Booking status → pill tone and weight.
 *
 * Reads as a progression: waiting (soft amber) → part paid (soft blue) →
 * locked in (solid blue) → done (solid green) → closed (soft grey).
 *
 * @param string $status Status key.
 * @return array{0:string,1:bool} Tone, and whether it is a landed state.
 */
function bhela_bm_status_tone( $status ) {
	$map = array(
		'pending'      => array( 'attention', false ),
		'advance_paid' => array( 'progress', false ),
		'confirmed'    => array( 'progress', true ),
		'completed'    => array( 'good', true ),
		'cancelled'    => array( 'neutral', false ),
	);
	return $map[ $status ] ?? array( 'neutral', false );
}

/** Convenience: the pill for a booking status, label and tone resolved together. */
function bhela_bm_booking_pill( $status ) {
	$statuses = bhela_bm_statuses();
	list( $tone, $solid ) = bhela_bm_status_tone( $status );
	return bhela_bm_status_pill( $statuses[ $status ] ?? $status, $tone, $solid );
}
