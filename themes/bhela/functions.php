<?php
/**
 * BHELA theme — setup, assets, customizer, helpers, auto setup, Gutenberg.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_VERSION', '2.15.0' );

/* ---------- Setup ---------- */

function bhela_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 96, 'width' => 96, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'bhela' ),
		'footer'  => __( 'Footer Menu', 'bhela' ),
	) );

	add_image_size( 'bhela-card', 800, 600, true );
	add_image_size( 'bhela-wide', 1600, 900, true );

	// Gutenberg support.
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'bhela_setup' );

require_once get_template_directory() . '/inc/block-patterns.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/custom-code.php';
require_once get_template_directory() . '/inc/contact.php';

/* ---------- Assets ---------- */

function bhela_assets() {
	wp_enqueue_style(
		'bhela-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'bhela-style', get_stylesheet_uri(), array( 'bhela-fonts' ), BHELA_VERSION );
	wp_enqueue_script( 'bhela-theme', get_template_directory_uri() . '/assets/js/theme.js', array(), BHELA_VERSION, true );

	$rates = function_exists( 'bhela_bm_get_rates' ) ? bhela_bm_get_rates() : array();
	$set   = function_exists( 'bhela_bm_get_settings' ) ? bhela_bm_get_settings() : array();
	wp_localize_script( 'bhela-theme', 'bhelaTheme', array(
		'rates'       => $rates,
		'weekendDays' => isset( $set['weekend_days'] ) ? array_map( 'intval', (array) $set['weekend_days'] ) : array( 5, 6 ),
		'holidays'    => function_exists( 'bhela_bm_holiday_dates' ) ? bhela_bm_holiday_dates() : array(),
		'whatsapp'    => preg_replace( '/[^0-9]/', '', bhela_contact( 'whatsapp' ) ),
		'bookingUrl'  => bhela_page_url( 'book-now' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bhela_assets' );

/* ---------- Contact helpers ---------- */

function bhela_contact( $key ) {
	$defaults = array(
		'phone_1'  => '01891-562461',
		'phone_2'  => '01614-182769',
		'whatsapp' => '+8801891562461',
		'email'    => 'infobhela@gmail.com',
		'facebook'  => 'https://www.facebook.com/',
		'instagram' => '',
		'tiktok'    => '',
		'linkedin'  => '',
		'youtube'   => '',
		'x'         => '',
		'threads'   => '',
		'messenger' => '',
		'address'   => 'Anwarpur Ghat, Tahirpur, Sunamganj',
	);
	if ( function_exists( 'bhela_bm_get_settings' ) ) {
		$s = bhela_bm_get_settings();
		foreach ( array( 'phone_1', 'phone_2', 'whatsapp', 'email', 'address' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$defaults[ $k ] = $s[ $k ];
			}
		}
	}
	return get_theme_mod( 'bhela_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

function bhela_wa_link( $text = '' ) {
	$num = preg_replace( '/[^0-9]/', '', bhela_contact( 'whatsapp' ) );
	$msg = $text ? $text : 'আসসালামু আলাইকুম। আমি ভেলা হাউসবোট সম্পর্কে জানতে চাই।';
	return 'https://wa.me/' . $num . '?text=' . rawurlencode( $msg );
}

/**
 * Social networks with their brand icons. Only networks that have a URL set in
 * Appearance → Customize → BHELA Contact are returned, so the footer never
 * shows a dead link. Icons are inline SVG — no external requests, no icon font.
 */
function bhela_social_networks() {
	return array(
		'facebook'  => array(
			'label' => 'Facebook',
			'icon'  => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z',
		),
		'instagram' => array(
			'label' => 'Instagram',
			'icon'  => 'M12 0C8.74 0 8.33.01 7.05.07 5.77.13 4.9.33 4.14.63c-.79.31-1.46.72-2.13 1.38C1.35 2.68.94 3.35.63 4.14.33 4.9.13 5.77.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.28.26 2.15.56 2.91.31.79.72 1.46 1.38 2.13.67.66 1.34 1.07 2.13 1.38.76.3 1.63.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.28-.06 2.15-.26 2.91-.56.79-.31 1.46-.72 2.13-1.38.66-.67 1.07-1.34 1.38-2.13.3-.76.5-1.63.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.28-.26-2.15-.56-2.91-.31-.79-.72-1.46-1.38-2.13C21.32 1.35 20.65.94 19.86.63c-.76-.3-1.63-.5-2.91-.56C15.67.01 15.26 0 12 0Zm0 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07Zm0 3.67a6.17 6.17 0 1 0 0 12.34 6.17 6.17 0 0 0 0-12.34ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z',
		),
		'tiktok'    => array(
			'label' => 'TikTok',
			'icon'  => 'M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07Z',
		),
		'linkedin'  => array(
			'label' => 'LinkedIn',
			'icon'  => 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13Zm1.78 13.02H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z',
		),
		'youtube'   => array(
			'label' => 'YouTube',
			'icon'  => 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.08 0 12 0 12s0 3.92.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.92 24 12 24 12s0-3.92-.5-5.81ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z',
		),
		'x'         => array(
			'label' => 'X',
			'icon'  => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41Z',
		),
		'threads'   => array(
			'label' => 'Threads',
			'icon'  => 'M16.01 11.13c-.09-.04-.18-.08-.27-.12-.16-2.9-1.74-4.56-4.4-4.58h-.04c-1.59 0-2.91.68-3.73 1.91l1.46 1c.61-.93 1.57-1.13 2.27-1.13h.02c.87.01 1.53.26 1.96.75.31.36.52.85.63 1.47a11.4 11.4 0 0 0-2.5-.12c-2.52.15-4.14 1.62-4.03 3.66.05 1.04.57 1.93 1.46 2.5.75.49 1.72.73 2.73.68 1.33-.07 2.38-.58 3.11-1.51.55-.71.9-1.62 1.06-2.78.65.39 1.13.91 1.4 1.54.45 1.06.48 2.81-.94 4.23-1.25 1.24-2.75 1.78-5.01 1.8-2.51-.02-4.4-.82-5.63-2.39-1.15-1.47-1.75-3.59-1.77-6.31.02-2.72.62-4.84 1.77-6.31C6.79 3.55 8.68 2.75 11.19 2.73c2.53.02 4.45.82 5.72 2.39.62.77 1.09 1.73 1.4 2.86l1.71-.46c-.37-1.38-.96-2.58-1.75-3.57C16.65 1.94 14.28.92 11.2.9h-.01C8.11.92 5.77 1.95 4.22 3.96 2.84 5.75 2.13 8.24 2.11 11.36v.01c.02 3.12.73 5.61 2.11 7.4 1.55 2.01 3.89 3.04 6.97 3.06h.01c2.74-.02 4.67-.74 6.26-2.32 2.08-2.07 2.02-4.67 1.33-6.27-.49-1.14-1.43-2.07-2.72-2.68Zm-4.59 4.53c-1.12.06-2.28-.44-2.34-1.53-.04-.81.58-1.71 2.41-1.82.21-.01.42-.02.62-.02.66 0 1.28.06 1.84.19-.21 2.61-1.43 3.12-2.53 3.18Z',
		),
	);
}

/** Only the networks the owner has actually filled in. */
function bhela_social_links() {
	$out = array();
	foreach ( bhela_social_networks() as $key => $net ) {
		$url = trim( (string) bhela_contact( $key ) );
		// Must be a real link. Skips empties and placeholders like "#" or "-"
		// so the footer never shows an icon that goes nowhere.
		if ( ! preg_match( '#^https?://.+\..+#i', $url ) ) {
			continue;
		}
		// Skip the bare facebook.com default (no page set yet).
		if ( in_array( untrailingslashit( $url ), array( 'https://www.facebook.com', 'https://facebook.com' ), true ) ) {
			continue;
		}
		$net['url']  = $url;
		$out[ $key ] = $net;
	}
	return $out;
}

/** Render the footer social icon row. */
function bhela_social_icons( $class = 'social-icons' ) {
	$links = bhela_social_links();
	if ( ! $links ) {
		return;
	}
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $links as $key => $net ) {
		printf(
			'<li><a class="social-icons__link social-icons__link--%1$s" href="%2$s" target="_blank" rel="noopener" aria-label="%3$s" title="%3$s">'
				. '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"><path d="%4$s"/></svg>'
				. '</a></li>',
			esc_attr( $key ),
			esc_url( $net['url'] ),
			esc_attr( $net['label'] ),
			esc_attr( $net['icon'] )
		);
	}
	echo '</ul>';
}

function bhela_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' );
}

function bhela_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'bhela_contact', array( 'title' => 'BHELA Contact', 'priority' => 30 ) );
	$fields = array(
		'phone_1'   => 'Phone 1',
		'phone_2'   => 'Phone 2',
		'whatsapp'  => 'WhatsApp Number',
		'email'     => 'Email',
		'facebook'  => 'Facebook URL',
		'instagram' => 'Instagram URL',
		'tiktok'    => 'TikTok URL',
		'linkedin'  => 'LinkedIn URL',
		'youtube'   => 'YouTube URL',
		'x'         => 'X (Twitter) URL',
		'threads'   => 'Threads URL',
		'messenger' => 'Messenger link (m.me/yourpage)',
		'address'   => 'Address',
	);
	foreach ( $fields as $key => $label ) {
		$wp_customize->add_setting( 'bhela_' . $key, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'bhela_' . $key, array( 'label' => $label, 'section' => 'bhela_contact', 'type' => 'text' ) );
	}
}
add_action( 'customize_register', 'bhela_customize_register' );

/** Homepage texts — editable without code (Appearance → Customize → BHELA Homepage). */
function bhela_customize_homepage( $wp_customize ) {
	$wp_customize->add_section( 'bhela_home', array( 'title' => 'BHELA Homepage', 'priority' => 31 ) );
	$fields = array(
		'hero_kicker' => array( 'label' => 'Hero Top Badge', 'default' => '🌧️ টাঙ্গুয়ার হাওর · প্রিমিয়াম হাউসবোট' ),
		'hero_title'  => array( 'label' => 'Hero Title (use | for line break, *word* = gold)', 'default' => 'ভেলার আকর্ষণ|ভেলা নয়, *হাওর!*' ),
		'hero_sub'    => array( 'label' => 'Hero Subtitle', 'default' => 'মাত্র ৬টি ফ্যামিলি কেবিন, AC ও Attached Washroom, দেশি খাবার আর অথৈ জলরাশি — ২ দিন ১ রাতের সম্পূর্ণ প্যাকেজে হাওরের সেরা অভিজ্ঞতা।' ),
	);
	foreach ( $fields as $key => $f ) {
		$wp_customize->add_setting( 'bhela_home_' . $key, array( 'default' => $f['default'], 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'bhela_home_' . $key, array( 'label' => $f['label'], 'section' => 'bhela_home', 'type' => 'hero_sub' === $key ? 'textarea' : 'text' ) );
	}
}
add_action( 'customize_register', 'bhela_customize_homepage' );

/** Theme image: Customizer upload wins, else bundled theme asset. */
function bhela_img( $key, $fallback_relpath ) {
	$custom = get_theme_mod( 'bhela_img_' . $key, '' );
	if ( $custom ) {
		return $custom;
	}
	return get_template_directory_uri() . '/assets/images/' . $fallback_relpath;
}

/** Image slots (key => label + bundled fallback). */
function bhela_image_slots() {
	return array(
		'hero'         => array( 'label' => 'Hero Background (হোমপেজ বড় ছবি)', 'file' => 'hero/hero-haor.jpg' ),
		'food'         => array( 'label' => 'Food Section (খাবারের ছবি)', 'file' => 'food/food-spread.jpg' ),
		'rooftop'      => array( 'label' => 'Why BHELA (রুফটপ ছবি)', 'file' => 'boat/rooftop-1.jpg' ),
		'cabin_budget' => array( 'label' => 'Cabin — Budget Friendly', 'file' => 'cabins/cabin-1.jpg' ),
		'cabin_comfort'=> array( 'label' => 'Cabin — Comfort', 'file' => 'cabins/cabin-2.jpg' ),
		'cabin_deluxe' => array( 'label' => 'Cabin — Double Deluxe', 'file' => 'cabins/cabin-3.jpg' ),
		'cabin_luxury' => array( 'label' => 'Cabin — Luxury Triple', 'file' => 'cabins/cabin-4.jpg' ),
		'cabin_couple' => array( 'label' => 'Cabin — Exclusive Couple', 'file' => 'cabins/cabin-5.jpg' ),
		'spot_1'       => array( 'label' => 'Spot — টাঙ্গুয়ার হাওর', 'file' => 'spots/spot-1.jpg' ),
		'spot_2'       => array( 'label' => 'Spot — নীলাদ্রি লেক', 'file' => 'spots/spot-2.jpg' ),
		'spot_3'       => array( 'label' => 'Spot — জাদুকাটা নদী', 'file' => 'spots/spot-3.jpg' ),
		'spot_4'       => array( 'label' => 'Spot — বারিক্কা টিলা', 'file' => 'spots/spot-4.jpg' ),
		'spot_5'       => array( 'label' => 'Spot — ওয়াচ টাওয়ার', 'file' => 'spots/spot-5.jpg' ),
		'spot_6'       => array( 'label' => 'Spot — শিমুল বাগান', 'file' => 'spots/spot-6.jpg' ),
		'spot_7'       => array( 'label' => 'Spot — খরচার হাওর', 'file' => 'spots/spot-7.jpg' ),
	);
}

/** Customizer: image upload controls (Appearance → Customize → BHELA Images). */
function bhela_customize_images( $wp_customize ) {
	$wp_customize->add_section( 'bhela_images', array( 'title' => 'BHELA Images (ছবি বদলান)', 'priority' => 32 ) );
	foreach ( bhela_image_slots() as $key => $slot ) {
		$wp_customize->add_setting( 'bhela_img_' . $key, array( 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'bhela_img_' . $key, array(
			'label'       => $slot['label'],
			'section'     => 'bhela_images',
			'description' => 'খালি রাখলে ডিফল্ট ছবি দেখাবে',
		) ) );
	}
}
add_action( 'customize_register', 'bhela_customize_images' );

/** Render hero title mod: | -> <br>, *text* -> gold em. */
function bhela_home_text( $key, $default = '' ) {
	$v = get_theme_mod( 'bhela_home_' . $key, $default );
	if ( 'hero_title' === $key ) {
		$v = esc_html( $v );
		$v = str_replace( '|', '<br>', $v );
		$v = preg_replace( '/\*(.+?)\*/u', '<em>$1</em>', $v );
		return $v;
	}
	return esc_html( $v );
}

/* ---------- Schema ---------- */
/* All JSON-LD (Organization, WebSite, LocalBusiness, Breadcrumb, BlogPosting)
   lives in inc/seo.php as a single connected @graph. */

/* ---------- Cabin data ---------- */

function bhela_cabins() {
	$images = array(
		'budget'  => 'cabins/cabin-1.jpg',
		'comfort' => 'cabins/cabin-2.jpg',
		'deluxe'  => 'cabins/cabin-3.jpg',
		'luxury'  => 'cabins/cabin-4.jpg',
		'couple'  => 'cabins/cabin-5.jpg',
	);
	$names = array(
		'budget'  => array( 'name' => 'Budget Friendly', 'bn' => 'বন্ধুদের বড় গ্রুপের জন্য সেরা ভ্যালু', 'badge' => 'Best Value' ),
		'comfort' => array( 'name' => 'Comfort', 'bn' => 'আরাম ও বাজেটের ব্যালান্স', 'badge' => '' ),
		'deluxe'  => array( 'name' => 'Double Deluxe', 'bn' => 'ফ্যামিলির জন্য জনপ্রিয়', 'badge' => 'Popular' ),
		'luxury'  => array( 'name' => 'Luxury Triple', 'bn' => 'প্রিমিয়াম স্পেস ও প্রাইভেসি', 'badge' => '' ),
		'couple'  => array( 'name' => 'Exclusive Couple', 'bn' => 'কাপলদের জন্য এক্সক্লুসিভ', 'badge' => 'Couple 💛' ),
	);
	$rates = function_exists( 'bhela_bm_get_rates' ) ? bhela_bm_get_rates() : array(
		'budget'  => array( 'label' => 'Budget Friendly Cabin', 'sharing' => 6, 'regular' => 8000, 'weekday' => 6400 ),
		'comfort' => array( 'label' => 'Comfort Cabin', 'sharing' => 5, 'regular' => 9000, 'weekday' => 7200 ),
		'deluxe'  => array( 'label' => 'Double Deluxe Cabin', 'sharing' => 4, 'regular' => 10000, 'weekday' => 8000 ),
		'luxury'  => array( 'label' => 'Luxury Triple Cabin', 'sharing' => 3, 'regular' => 12000, 'weekday' => 9600 ),
		'couple'  => array( 'label' => 'Exclusive Couple Cabin', 'sharing' => 2, 'regular' => 13000, 'weekday' => 10400 ),
	);
	$out = array();
	foreach ( $rates as $key => $row ) {
		$extra              = isset( $names[ $key ] ) ? $names[ $key ] : array( 'name' => $row['label'], 'bn' => '', 'badge' => '' );
		$out[ $key ]        = array_merge( $row, $extra );
		$out[ $key ]['img'] = bhela_img( 'cabin_' . $key, isset( $images[ $key ] ) ? $images[ $key ] : 'hero/hero-haor.jpg' );
	}
	return $out;
}

function bhela_money( $n ) {
	return '৳' . number_format( (float) $n );
}

/* ---------- Auto setup on theme activation ---------- */

function bhela_auto_setup() {
	// 1) Auto-activate the BHELA Booking Engine plugin if installed.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$booking_plugin = 'bhela-booking/bhela-booking.php';
	if ( file_exists( WP_PLUGIN_DIR . '/' . $booking_plugin ) && ! is_plugin_active( $booking_plugin ) ) {
		activate_plugin( $booking_plugin );
	}

	// 2) Pretty permalinks.
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// 3) Create pages with templates.
	$pages = array(
		'cabins'   => array( 'title' => 'কেবিন ও রেট', 'template' => 'page-templates/template-cabins.php' ),
		'schedule' => array( 'title' => 'ট্রিপ সিডিউল', 'template' => 'page-templates/template-schedule.php' ),
		'food'     => array( 'title' => 'খাবার মেনু', 'template' => 'page-templates/template-food.php' ),
		'gallery'  => array( 'title' => 'গ্যালারি', 'template' => 'page-templates/template-gallery.php' ),
		'faq'      => array( 'title' => 'সাধারণ প্রশ্ন (FAQ)', 'template' => 'page-templates/template-faq.php' ),
		'book-now' => array( 'title' => 'বুক করুন', 'template' => 'page-templates/template-booking.php' ),
		'policies' => array( 'title' => 'বুকিং নীতিমালা', 'template' => 'page-templates/template-policy.php' ),
		'contact'  => array( 'title' => 'যোগাযোগ', 'template' => 'page-templates/template-contact.php' ),
	);

	$menu_items = array();
	foreach ( $pages as $slug => $info ) {
		$existing = get_page_by_path( $slug );
		if ( ! $existing ) {
			$id = wp_insert_post( array(
				'post_title'  => $info['title'],
				'post_name'   => $slug,
				'post_type'   => 'page',
				'post_status' => 'publish',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_page_template', $info['template'] );
				$menu_items[ $slug ] = $id;
			}
		} else {
			update_post_meta( $existing->ID, '_wp_page_template', $info['template'] );
			$menu_items[ $slug ] = $existing->ID;
		}
	}

	// 4) Front page.
	$home = get_page_by_path( 'home' );
	if ( ! $home ) {
		$home_id = wp_insert_post( array(
			'post_title'  => 'হোম',
			'post_name'   => 'home',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );
	} else {
		$home_id = $home->ID;
	}
	if ( $home_id && ! is_wp_error( $home_id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $home_id );
	}

	// 4b) Blog page (posts index) + seed categories.
	$blog = get_page_by_path( 'blog' );
	if ( ! $blog ) {
		$blog_id = wp_insert_post( array(
			'post_title'  => 'ব্লগ',
			'post_name'   => 'blog',
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );
	} else {
		$blog_id = $blog->ID;
	}
	if ( $blog_id && ! is_wp_error( $blog_id ) ) {
		update_option( 'page_for_posts', (int) $blog_id );
		$menu_items['blog'] = (int) $blog_id;
	}
	$cat_ids = array();
	foreach ( array( 'travel-guide' => 'ভ্রমণ গাইড', 'haor-news' => 'হাওরের খবর', 'tips' => 'টিপস' ) as $slug => $name ) {
		$term = term_exists( $slug, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
		$cat_ids[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}

	// 4c) Seed sample blog posts (idempotent — skips any that already exist).
	bhela_seed_blog_posts( $cat_ids );

	// 5) Primary menu.
	$menu = wp_get_nav_menu_object( 'BHELA Primary' );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( 'BHELA Primary' );
		$order   = array( 'cabins', 'schedule', 'food', 'gallery', 'faq', 'blog', 'contact' );
		foreach ( $order as $slug ) {
			if ( isset( $menu_items[ $slug ] ) ) {
				wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-object-id' => $menu_items[ $slug ],
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				) );
			}
		}
		$locations            = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	} else {
		// Menu already exists (upgrade path): append any page added in a later
		// release that is not in the menu yet.
		$ensure   = array( 'blog' => 'ব্লগ', 'contact' => 'যোগাযোগ' );
		$existing = array();
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $mi ) {
			$existing[] = (int) $mi->object_id;
		}
		foreach ( $ensure as $slug => $title ) {
			if ( ! isset( $menu_items[ $slug ] ) || in_array( (int) $menu_items[ $slug ], $existing, true ) ) {
				continue;
			}
			wp_update_nav_menu_item( $menu->term_id, 0, array(
				'menu-item-object-id' => (int) $menu_items[ $slug ],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-title'     => $title,
			) );
		}
	}

	// 6) Flush rewrite rules.
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'bhela_auto_setup' );

/**
 * Re-run provisioning once per released version (covers file-only upgrades where
 * the theme is not re-activated). Everything in bhela_auto_setup() is idempotent.
 */
function bhela_maybe_provision() {
	// admin_init also fires on admin-ajax.php (incl. unauthenticated nopriv
	// AJAX) and cron — never run heavy provisioning in those contexts. Require
	// a real, capable admin request.
	if ( wp_doing_ajax() || wp_doing_cron() || ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_option( 'bhela_provisioned_version' ) === BHELA_VERSION ) {
		return;
	}
	bhela_auto_setup();
	update_option( 'bhela_provisioned_version', BHELA_VERSION );
}
add_action( 'admin_init', 'bhela_maybe_provision' );

/**
 * Seed the sample blog posts (হাওর জার্নাল) so a fresh release ships with
 * ready-to-read content. Idempotent: any post whose slug already exists is left
 * untouched. Featured images are copied from bundled theme assets.
 *
 * @param array $cat_ids slug => term_id map from bhela_auto_setup().
 */
function bhela_seed_blog_posts( $cat_ids ) {
	$posts = array(
		array(
			'slug'  => 'best-time-tanguar-haor',
			'title' => 'টাঙ্গুয়ার হাওর ভ্রমণের সেরা সময় কখন?',
			'cat'   => 'travel-guide',
			'tags'  => array( 'টাঙ্গুয়ার হাওর', 'বর্ষাকাল', 'ভ্রমণ পরিকল্পনা' ),
			'img'   => 'hero/hero-haor.jpg',
			'body'  => "<p>টাঙ্গুয়ার হাওরের রূপ ঋতুভেদে সম্পূর্ণ বদলে যায়। কোন সময়ে গেলে কী পাবেন — এক নজরে দেখে নিন।</p>\n<h2>🌧️ বর্ষাকাল (জুন–সেপ্টেম্বর) — সেরা সময়</h2>\n<p>এ সময় পুরো হাওর অথৈ জলরাশিতে পরিণত হয়। জাদুকাটা নদীর স্বচ্ছ জল, নীলাদ্রি লেকের নীল আর মেঘালয়ের পাহাড় — সব সবচেয়ে সুন্দর রূপে ধরা দেয়। হাউসবোট ভ্রমণের জন্য এটিই আদর্শ মৌসুম।</p>\n<h2>🍂 শরৎ–হেমন্ত (অক্টোবর–নভেম্বর)</h2>\n<p>জল কমতে শুরু করে, তবে ভিড় কম থাকে। শান্ত পরিবেশে ঘুরতে চাইলে ভালো বিকল্প।</p>\n<h2>🌕 ফুল মুন ট্রিপ</h2>\n<p>পূর্ণিমার রাতে হাওরের জলে চাঁদের আলো — অনেকের কাছে এটিই হাওর ভ্রমণের সেরা অভিজ্ঞতা। ভেলায় প্রতি পূর্ণিমায় স্পেশাল ট্রিপ থাকে।</p>\n<h2>💡 টিপস</h2>\n<ul>\n<li>Weekday ট্রিপে ২০% পর্যন্ত ছাড় — ভিড়ও কম</li>\n<li>ছুটির দিনের ট্রিপ অন্তত ২–৩ সপ্তাহ আগে বুক করুন</li>\n<li>বর্ষায় রেইনকোট আর গ্রিপযুক্ত জুতা নিতে ভুলবেন না</li>\n</ul>",
		),
		array(
			'slug'  => 'haor-packing-list',
			'title' => 'হাওর ট্রিপে কী কী নেবেন — সম্পূর্ণ প্যাকিং লিস্ট',
			'cat'   => 'tips',
			'tags'  => array( 'প্যাকিং', 'টিপস', 'প্রস্তুতি' ),
			'img'   => 'boat/rooftop-1.jpg',
			'body'  => "<p>২ দিন ১ রাতের হাউসবোট ট্রিপে বেশি জিনিস লাগে না — কিন্তু কয়েকটা জিনিস না নিলে ভুগতে হয়। আমাদের অভিজ্ঞতা থেকে সাজানো লিস্ট।</p>\n<h2>📄 অবশ্যই নেবেন</h2>\n<ul>\n<li>NID / জন্মনিবন্ধনের কপি (প্রত্যেক অতিথির)</li>\n<li>প্রয়োজনীয় ওষুধ</li>\n<li>পাওয়ার ব্যাংক (বোটে চার্জিং আছে, তবুও)</li>\n</ul>\n<h2>👕 পোশাক</h2>\n<ul>\n<li>আরামদায়ক সুতির পোশাক ২–৩ সেট</li>\n<li>বর্ষায়: রেইনকোট বা ছাতা</li>\n<li>গ্রিপযুক্ত স্যান্ডেল/জুতা — ঘাট আর টিলায় কাজে দেবে</li>\n</ul>\n<h2>🕶️ রোদ-বৃষ্টির জন্য</h2>\n<ul>\n<li>সানস্ক্রিন, টুপি, সানগ্লাস</li>\n<li>মশা নিরোধক ক্রিম</li>\n</ul>\n<h2>📷 চাইলে</h2>\n<ul>\n<li>ক্যামেরা / ড্রোন (সীমান্ত বিধি মেনে)</li>\n<li>বাইনোকুলার — অতিথি পাখির মৌসুমে দারুণ</li>\n</ul>\n<p>বাকি সব — থাকা, ৬ বেলা খাবার, লাইফ জ্যাকেট, বিশুদ্ধ পানি — ভেলায় অন্তর্ভুক্ত।</p>",
		),
		array(
			'slug'  => 'family-safety-houseboat',
			'title' => 'পরিবার নিয়ে হাউসবোটে — নিরাপত্তার যত প্রশ্ন',
			'cat'   => 'travel-guide',
			'tags'  => array( 'ফ্যামিলি ট্রিপ', 'নিরাপত্তা', 'শিশু' ),
			'img'   => 'cabins/cabin-3.jpg',
			'body'  => "<p>\"বাচ্চা নিয়ে হাউসবোটে যাওয়া কি নিরাপদ?\" — এটাই আমাদের সবচেয়ে বেশি শোনা প্রশ্ন। সোজা উত্তর: হ্যাঁ, সঠিক ব্যবস্থা থাকলে।</p>\n<h2>🛟 লাইফ জ্যাকেট ও প্রশিক্ষিত ক্রু</h2>\n<p>ভেলায় প্রতিটি অতিথির জন্য লাইফ জ্যাকেট আছে, শিশুদের সাইজসহ। ক্রুরা প্রশিক্ষিত — বোট চলে নিরাপদ গতিতে, রাতেও।</p>\n<h2>👨‍👩‍👧‍👦 প্রাইভেসি</h2>\n<p>অপরিচিত কারও সাথে কেবিন শেয়ার করতে হয় না — শুধুমাত্র নিজের গ্রুপের মধ্যেই শেয়ারিং। ৬টি ফ্যামিলি কেবিনের প্রতিটিতে Attached Washroom।</p>\n<h2>👶 শিশুদের রেট</h2>\n<ul>\n<li>০–৪ বছর: সম্পূর্ণ ফ্রি</li>\n<li>৪–৮ বছর: ফিক্সড ৳৫,০০০</li>\n<li>৯+ বছর: পূর্ণ রেট</li>\n</ul>\n<h2>🍚 খাবার</h2>\n<p>দেশি খাবার — ভুনা খিচুড়ি, হাওরের তাজা মাছ, দেশি মুরগি। বাচ্চাদের উপযোগী কম-ঝাল ব্যবস্থাও আগে জানালে করা যায়।</p>\n<h2>⚕️ জরুরি অবস্থায়</h2>\n<p>টিম প্রাথমিক চিকিৎসা দিতে পারে; প্রয়োজনে নিকটস্থ স্বাস্থ্যকেন্দ্রে নেওয়ার ব্যবস্থা আছে।</p>",
		),
	);

	foreach ( $posts as $p ) {
		if ( get_page_by_path( $p['slug'], OBJECT, 'post' ) ) {
			continue; // already seeded
		}
		$cat = isset( $cat_ids[ $p['cat'] ] ) ? array( (int) $cat_ids[ $p['cat'] ] ) : array();
		$pid = wp_insert_post( array(
			'post_title'    => $p['title'],
			'post_name'     => $p['slug'],
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_content'  => $p['body'],
			'post_category' => $cat,
		) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			continue;
		}
		wp_set_post_tags( $pid, $p['tags'] );
		bhela_set_seed_thumbnail( $pid, $p['img'] );
	}
}

/** Attach a bundled theme image as a post's featured image. */
function bhela_set_seed_thumbnail( $post_id, $rel ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return;
	}
	$src = get_template_directory() . '/assets/images/' . $rel;
	if ( ! file_exists( $src ) ) {
		return;
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$upload = wp_upload_dir();
	$dest   = trailingslashit( $upload['path'] ) . 'bhela-seed-' . basename( $rel );
	if ( ! @copy( $src, $dest ) ) {
		return;
	}
	$type = wp_check_filetype( $dest );
	$att  = wp_insert_attachment( array(
		'post_mime_type' => $type['type'],
		'post_title'     => sanitize_file_name( basename( $rel ) ),
		'post_status'    => 'inherit',
	), $dest, $post_id );
	if ( is_wp_error( $att ) || ! $att ) {
		return;
	}
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $dest ) );
	set_post_thumbnail( $post_id, $att );
}

/* ---------- Gutenberg content region for page templates ---------- */

function bhela_page_editor_content() {
	if ( ! have_posts() ) {
		return;
	}
	while ( have_posts() ) {
		the_post();
		$content = trim( get_the_content() );
		if ( $content ) {
			echo '<section class="section" style="padding-bottom:0"><div class="container"><div class="entry-content">';
			the_content();
			echo '</div></div></section>';
		}
	}
	rewind_posts();
}

/* ---------- Blog (হাওর জার্নাল) ---------- */

/** Estimated reading time, e.g. "৫ মিনিট পড়া". Unicode-safe for Bangla. */
function bhela_reading_time( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$text    = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	$words   = $text ? count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ) : 0;
	$mins    = max( 1, (int) ceil( $words / 200 ) );
	/* translators: %s: minutes. */
	return sprintf( __( '%s মিনিট পড়া', 'bhela' ), number_format_i18n( $mins ) );
}

/** Article JSON-LD on single posts (mirrors bhela_schema()). */
/* Article JSON-LD moved into the @graph in inc/seo.php (BlogPosting node). */

// Comments are intentionally disabled site-wide — the blog funnels readers to
// WhatsApp/booking instead, and a small operator shouldn't moderate spam.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

/* ---------- Fallback menu ---------- */

function bhela_fallback_menu() {
	$items = array(
		'cabins'   => 'কেবিন ও রেট',
		'schedule' => 'সিডিউল',
		'food'     => 'খাবার',
		'gallery'  => 'গ্যালারি',
		'faq'      => 'FAQ',
		'blog'     => 'ব্লগ',
	);
	echo '<ul class="site-nav__menu" id="site-menu">';
	foreach ( $items as $slug => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( bhela_page_url( $slug ) ), esc_html( $label ) );
	}
	printf( '<li><a class="btn btn--cta site-nav__book" href="%s">বুক করুন</a></li>', esc_url( bhela_page_url( 'book-now' ) ) );
	echo '</ul>';
}

/* ---------- Elementor compatibility ---------- */

// Content width (Elementor reads this for default widths).
if ( ! isset( $GLOBALS['content_width'] ) ) {
	$GLOBALS['content_width'] = 1200;
}

/** Is this page built with Elementor? */
function bhela_is_elementor_page( $post_id = 0 ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return false;
	}
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}
	return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/** Elementor Pro Theme Builder: allow header/footer/single/archive overrides. */
function bhela_register_elementor_locations( $elementor_theme_manager ) {
	$elementor_theme_manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'bhela_register_elementor_locations' );

/** Default kit hint: keep Elementor defaults aligned with BHELA fonts/colors. */
function bhela_elementor_body_class( $classes ) {
	if ( bhela_is_elementor_page() ) {
		$classes[] = 'bhela-elementor';
	}
	// Booking page: the booking form has its own sticky price/action bar on
	// mobile, so the generic Call/WhatsApp/Book bar is hidden there (CSS) to
	// avoid a duplicate, overlapping bottom bar.
	if ( is_page_template( 'page-templates/template-booking.php' ) ) {
		$classes[] = 'bhela-book-page';
	}
	return $classes;
}
add_filter( 'body_class', 'bhela_elementor_body_class' );
