<?php
/**
 * BHELA Custom Code — paste snippets into <head>, right after <body>, or before
 * </body> without touching theme files.
 *
 * Appearance → Customize → BHELA Custom Code. Three boxes:
 *   • Header   → printed in <head>            (meta verification, fonts, styles, pixels)
 *   • Body top → printed right after <body>   (GTM noscript, chat widgets)
 *   • Footer   → printed before </body>       (scripts that should load last)
 *
 * Raw HTML/JS injection is powerful, so saving is restricted to users who have
 * the `unfiltered_html` capability (administrators on a single site). The code
 * is output for every visitor — verification metas and widgets must be present
 * for search engines and guests alike.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The three code slots: mod key => [label, description]. */
function bhela_code_slots() {
	return array(
		'bhela_code_head' => array(
			'label' => 'Header Code (&lt;head&gt;)',
			'desc'  => 'সাইট ভেরিফিকেশন meta, স্টাইল বা স্ক্রিপ্ট — হুবহু &lt;head&gt;-এ বসবে।',
		),
		'bhela_code_body' => array(
			'label' => 'Body Top Code (after &lt;body&gt;)',
			'desc'  => 'Google Tag Manager noscript, চ্যাট উইজেট — &lt;body&gt; শুরুতেই বসবে।',
		),
		'bhela_code_footer' => array(
			'label' => 'Footer Code (before &lt;/body&gt;)',
			'desc'  => 'যেসব স্ক্রিপ্ট সবার শেষে লোড হওয়া দরকার — পেজের শেষে বসবে।',
		),
	);
}

/** Customizer: Appearance → Customize → BHELA Custom Code. */
function bhela_custom_code_customizer( $wp_customize ) {
	$wp_customize->add_section( 'bhela_code', array(
		'title'       => 'BHELA Custom Code',
		'priority'    => 34,
		'description' => 'যেকোনো কোড &lt;head&gt;, &lt;body&gt; শুরু বা পেজের শেষে যোগ করুন — থিম ফাইল ছোঁয়া ছাড়াই। শুধু অ্যাডমিন সেভ করতে পারবেন।',
	) );

	foreach ( bhela_code_slots() as $key => $slot ) {
		$wp_customize->add_setting( $key, array(
			'type'              => 'theme_mod',
			'capability'        => 'unfiltered_html',
			'sanitize_callback' => 'bhela_sanitize_custom_code',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $key, array(
			'label'       => $slot['label'],
			'description' => $slot['desc'],
			'section'     => 'bhela_code',
			'type'        => 'textarea',
			'input_attrs' => array( 'rows' => 6, 'style' => 'font-family:monospace' ),
		) );
	}
}
add_action( 'customize_register', 'bhela_custom_code_customizer' );

/**
 * Raw code is script injection by definition — only users allowed to post
 * unfiltered HTML may save it. Everyone else's input is discarded.
 */
function bhela_sanitize_custom_code( $value ) {
	return current_user_can( 'unfiltered_html' ) ? $value : '';
}

/** Read one code slot; the header slot falls back to the legacy tracking field. */
function bhela_get_custom_code( $key ) {
	$code = (string) get_theme_mod( $key, '' );
	if ( '' === $code && 'bhela_code_head' === $key ) {
		$code = (string) get_theme_mod( 'bhela_head_code', '' ); // migrated from BHELA Tracking.
	}
	return $code;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw
// output; saving is gated to the unfiltered_html capability in the sanitizer.

/** <head> — late so it sits after the theme's own meta/schema. */
function bhela_output_head_code() {
	$code = bhela_get_custom_code( 'bhela_code_head' );
	if ( '' !== trim( $code ) ) {
		echo "\n" . $code . "\n";
	}
}
add_action( 'wp_head', 'bhela_output_head_code', 99 );

/** Right after <body>. */
function bhela_output_body_code() {
	$code = bhela_get_custom_code( 'bhela_code_body' );
	if ( '' !== trim( $code ) ) {
		echo "\n" . $code . "\n";
	}
}
add_action( 'wp_body_open', 'bhela_output_body_code', 5 );

/** Before </body>. */
function bhela_output_footer_code() {
	$code = bhela_get_custom_code( 'bhela_code_footer' );
	if ( '' !== trim( $code ) ) {
		echo "\n" . $code . "\n";
	}
}
add_action( 'wp_footer', 'bhela_output_footer_code', 99 );

// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
