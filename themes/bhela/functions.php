<?php
/**
 * BHELA theme — setup, assets, customizer, helpers, auto page setup.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_VERSION', '2.2.0' );

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
}
add_action( 'after_setup_theme', 'bhela_setup' );

/* ---------- Assets ---------- */

function bhela_assets() {
	wp_enqueue_style(
		'bhela-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Hind+Siliguri:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'bhela-style', get_stylesheet_uri(), array( 'bhela-fonts' ), BHELA_VERSION );
	wp_enqueue_script( 'bhela-theme', get_template_directory_uri() . '/assets/js/theme.js', array(), BHELA_VERSION, true );

	// Rates for the hero quick-estimator (from booking plugin if active).
	$rates = function_exists( 'bhela_bm_get_rates' ) ? bhela_bm_get_rates() : array();
	$set   = function_exists( 'bhela_bm_get_settings' ) ? bhela_bm_get_settings() : array();
	wp_localize_script( 'bhela-theme', 'bhelaTheme', array(
		'rates'       => $rates,
		'weekendDays' => isset( $set['weekend_days'] ) ? array_map( 'intval', (array) $set['weekend_days'] ) : array( 5, 6 ),
		'holidays'    => isset( $set['holidays'] ) ? array_values( array_filter( array_map( 'trim', explode( "\n", $set['holidays'] ) ) ) ) : array(),
		'whatsapp'    => preg_replace( '/[^0-9]/', '', bhela_contact( 'whatsapp' ) ),
		'bookingUrl'  => bhela_page_url( 'book-now' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bhela_assets' );

/* ---------- Contact helpers (Customizer with plugin fallback) ---------- */

function bhela_contact( $key ) {
	$defaults = array(
		'phone_1'  => '01891-562461',
		'phone_2'  => '01614-182769',
		'whatsapp' => '+8801891562461',
		'email'    => 'infobhela@gmail.com',
		'facebook' => 'https://www.facebook.com/',
		'address'  => 'Anwarpur Ghat, Tahirpur, Sunamganj',
	);
	if ( function_exists( 'bhela_bm_get_settings' ) ) {
		$s = bhela_bm_get_settings();
		foreach ( array( 'phone_1', 'phone_2', 'whatsapp', 'email', 'address' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$defaults[ $k ] = $s[ $k ];
			}
		}
	}
	return get_theme_mod( 'bhela_' . $key, $defaults[ $key ] ?? '' );
}

function bhela_wa_link( $text = '' ) {
	$num = preg_replace( '/[^0-9]/', '', bhela_contact( 'whatsapp' ) );
	$msg = $text ? $text : 'আসসালামু আলাইকুম। আমি ভেলা হাউসবোট সম্পর্কে জানতে চাই।';
	return 'https://wa.me/' . $num . '?text=' . rawurlencode( $msg );
}

/** URL of an auto-created page by slug (fallback: home). */
function bhela_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' );
}

function bhela_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'bhela_contact', array( 'title' => 'BHELA Contact', 'priority' => 30 ) );
	$fields = array(
		'phone_1'  => 'Phone 1',
		'phone_2'  => 'Phone 2',
		'whatsapp' => 'WhatsApp Number',
		'email'    => 'Email',
		'facebook' => 'Facebook URL',
		'address'  => 'Address',
	);
	foreach ( $fields as $key => $label ) {
		$wp_customize->add_setting( 'bhela_' . $key, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'bhela_' . $key, array( 'label' => $label, 'section' => 'bhela_contact', 'type' => 'text' ) );
	}
}
add_action( 'customize_register', 'bhela_customize_register' );

/* ---------- Schema (LocalBusiness + TouristTrip) ---------- */

function bhela_schema() {
	if ( ! is_front_page() ) {
		return;
	}
	$schema = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'TouristAttraction',
		'name'      => 'BHELA – The Haor Exclusive',
		'description' => 'Premium family & group friendly AC houseboat on Tanguar Haor, Sunamganj, Bangladesh. 2 days 1 night all-inclusive packages.',
		'url'       => home_url( '/' ),
		'telephone' => bhela_contact( 'phone_1' ),
		'email'     => bhela_contact( 'email' ),
		'address'   => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => 'Anwarpur Ghat, Tahirpur',
			'addressRegion'   => 'Sunamganj',
			'addressCountry'  => 'BD',
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'bhela_schema' );

/* ---------- Cabin data for templates (falls back if plugin inactive) ---------- */

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
		$out[ $key ] = array_merge( $row, $names[ $key ] ?? array( 'name' => $row['label'], 'bn' => '', 'badge' => '' ) );
		$out[ $key ]['img'] = get_template_directory_uri() . '/assets/images/' . ( $images[ $key ] ?? 'hero/hero-haor.jpg' );
	}
	return $out;
}

function bhela_money( $n ) {
	return '৳' . number_format( (float) $n );
}

/* ---------- Auto-create pages + menu on theme activation ---------- */

function bhela_auto_setup() {
	$pages = array(
		'cabins'    => array( 'title' => 'কেবিন ও রেট', 'template' => 'page-templates/template-cabins.php' ),
		'schedule'  => array( 'title' => 'ট্রিপ সিডিউল', 'template' => 'page-templates/template-schedule.php' ),
		'food'      => array( 'title' => 'খাবার মেনু', 'template' => 'page-templates/template-food.php' ),
		'gallery'   => array( 'title' => 'গ্যালারি', 'template' => 'page-templates/template-gallery.php' ),
		'faq'       => array( 'title' => 'সাধারণ প্রশ্ন (FAQ)', 'template' => 'page-templates/template-faq.php' ),
		'book-now'  => array( 'title' => 'বুক করুন', 'template' => 'page-templates/template-booking.php' ),
		'policies'  => array( 'title' => 'বুকিং নীতিমালা', 'template' => 'page-templates/template-policy.php' ),
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

	// Primary menu.
	$menu = wp_get_nav_menu_object( 'BHELA Primary' );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( 'BHELA Primary' );
		$order   = array( 'cabins', 'schedule', 'food', 'gallery', 'faq' );
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
	}
}
add_action( 'after_switch_theme', 'bhela_auto_setup' );

/* ---------- Fallback menu ---------- */

function bhela_fallback_menu() {
	$items = array(
		'cabins'   => 'কেবিন ও রেট',
		'schedule' => 'সিডিউল',
		'food'     => 'খাবার',
		'gallery'  => 'গ্যালারি',
		'faq'      => 'FAQ',
	);
	echo '<ul class="site-nav__menu" id="site-menu">';
	foreach ( $items as $slug => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( bhela_page_url( $slug ) ), esc_html( $label ) );
	}
	printf( '<li><a class="btn btn--cta site-nav__book" href="%s">বুক করুন</a></li>', esc_url( bhela_page_url( 'book-now' ) ) );
	echo '</ul>';
}
