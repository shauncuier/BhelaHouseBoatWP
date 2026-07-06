<?php
/**
 * BHELA Houseboat Theme Functions
 * 
 * @package BhelaHouseboat
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BHELA_VERSION', '1.0.0' );
define( 'BHELA_DIR', get_template_directory() );
define( 'BHELA_URI', get_template_directory_uri() );

/**
 * Theme Setup
 */
function bhela_theme_setup() {
    // Add title tag support
    add_theme_support( 'title-tag' );

    // Post thumbnails
    add_theme_support( 'post-thumbnails' );
    add_image_size( 'bhela-hero', 1920, 1080, true );
    add_image_size( 'bhela-card', 800, 600, true );
    add_image_size( 'bhela-gallery', 1200, 900, true );
    add_image_size( 'bhela-cabin', 600, 450, true );

    // Custom logo
    add_theme_support( 'custom-logo', array(
        'height'      => 200,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ) );

    // HTML5 support
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    // Register navigation menus
    register_nav_menus( array(
        'primary'   => __( 'Primary Navigation', 'bhelahouseboat' ),
        'footer'    => __( 'Footer Navigation', 'bhelahouseboat' ),
        'mobile'    => __( 'Mobile Navigation', 'bhelahouseboat' ),
    ) );

    // Editor styles
    add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'bhela_theme_setup' );

/**
 * Enqueue Styles & Scripts
 */
function bhela_enqueue_assets() {
    // Google Fonts
    wp_enqueue_style(
        'bhela-google-fonts',
        'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap',
        array(),
        null
    );

    // Theme CSS
    wp_enqueue_style( 'bhela-variables', BHELA_URI . '/assets/css/variables.css', array(), BHELA_VERSION );
    wp_enqueue_style( 'bhela-style', get_stylesheet_uri(), array( 'bhela-variables' ), BHELA_VERSION );
    wp_enqueue_style( 'bhela-components', BHELA_URI . '/assets/css/components.css', array( 'bhela-style' ), BHELA_VERSION );
    wp_enqueue_style( 'bhela-pages', BHELA_URI . '/assets/css/pages.css', array( 'bhela-components' ), BHELA_VERSION );
    wp_enqueue_style( 'bhela-responsive', BHELA_URI . '/assets/css/responsive.css', array( 'bhela-pages' ), BHELA_VERSION );

    // Theme JS
    wp_enqueue_script( 'bhela-smooth-scroll', BHELA_URI . '/assets/js/smooth-scroll.js', array(), BHELA_VERSION, true );
    wp_enqueue_script( 'bhela-main', BHELA_URI . '/assets/js/main.js', array(), BHELA_VERSION, true );

    // Conditional scripts
    if ( is_front_page() || is_page_template( 'page-templates/template-cabins.php' ) ) {
        wp_enqueue_script( 'bhela-price-estimator', BHELA_URI . '/assets/js/price-estimator.js', array(), BHELA_VERSION, true );
    }

    if ( is_front_page() || is_page_template( 'page-templates/template-faq.php' ) ) {
        wp_enqueue_script( 'bhela-faq', BHELA_URI . '/assets/js/faq.js', array(), BHELA_VERSION, true );
    }

    if ( is_page_template( 'page-templates/template-gallery.php' ) ) {
        wp_enqueue_script( 'bhela-gallery', BHELA_URI . '/assets/js/gallery.js', array(), BHELA_VERSION, true );
    }

    // Localize script data
    wp_localize_script( 'bhela-main', 'bhelaData', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'themeUrl'  => BHELA_URI,
        'whatsapp'  => bhela_get_option( 'whatsapp_primary', '+8801793395556' ),
        'phone'     => bhela_get_option( 'phone_primary', '01891-562461' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'bhela_enqueue_assets' );

/**
 * Register Sidebars / Widget Areas
 */
function bhela_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Footer Column 1', 'bhelahouseboat' ),
        'id'            => 'footer-1',
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget__title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 2', 'bhelahouseboat' ),
        'id'            => 'footer-2',
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget__title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 3', 'bhelahouseboat' ),
        'id'            => 'footer-3',
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget__title">',
        'after_title'   => '</h4>',
    ) );
}
add_action( 'widgets_init', 'bhela_widgets_init' );

/**
 * Custom Post Types
 */
require_once BHELA_DIR . '/inc/custom-post-types.php';

/**
 * Customizer Settings
 */
require_once BHELA_DIR . '/inc/customizer.php';

/**
 * Schema / Structured Data
 */
require_once BHELA_DIR . '/inc/schema.php';

/**
 * Helper: Get theme option with default
 */
function bhela_get_option( $key, $default = '' ) {
    return get_theme_mod( 'bhela_' . $key, $default );
}

/**
 * Helper: WhatsApp deep link with pre-filled message
 */
function bhela_whatsapp_link( $message = '' ) {
    $number = bhela_get_option( 'whatsapp_primary', '+8801793395556' );
    $number = preg_replace( '/[^0-9+]/', '', $number );
    
    if ( empty( $message ) ) {
        $message = 'আসসালামু আলাইকুম, আমি ভেলা হাউসবোটে ট্রিপ বুক করতে চাই। অনুগ্রহ করে বিস্তারিত জানাবেন।';
    }
    
    return 'https://wa.me/' . ltrim( $number, '+' ) . '?text=' . rawurlencode( $message );
}

/**
 * Helper: Phone link
 */
function bhela_phone_link( $number = '' ) {
    if ( empty( $number ) ) {
        $number = bhela_get_option( 'phone_primary', '01891562461' );
    }
    return 'tel:+88' . preg_replace( '/[^0-9]/', '', $number );
}

/**
 * Helper: Get cabin data
 */
function bhela_get_cabins() {
    return array(
        array(
            'name'      => 'Budget Friendly',
            'name_bn'   => 'বাজেট ফ্রেন্ডলি',
            'icon'      => '🟢',
            'color'     => 'var(--cabin-budget)',
            'sharing'   => 6,
            'holiday'   => 8000,
            'weekday'   => 6400,
            'amenities' => array( 'AC/Non-AC', 'Attached Washroom', 'Double Bed', 'Glass Window' ),
            'desc'      => 'বড় পরিবার বা বন্ধুদের জন্য সবচেয়ে সাশ্রয়ী — ৬ জনের শেয়ারিংয়ে আরাম ও সুবিধা দুটোই।',
        ),
        array(
            'name'      => 'Comfort Adjustment',
            'name_bn'   => 'কমফোর্ট এডজাস্টমেন্ট',
            'icon'      => '🔵',
            'color'     => 'var(--cabin-comfort)',
            'sharing'   => 5,
            'holiday'   => 9000,
            'weekday'   => 7200,
            'amenities' => array( 'AC/Non-AC', 'Attached Washroom', 'Double Bed', 'Glass Window' ),
            'desc'      => 'একটু বেশি জায়গা, একটু বেশি আরাম — ৫ জনের পারফেক্ট ব্যালেন্স।',
        ),
        array(
            'name'      => 'Double Deluxe',
            'name_bn'   => 'ডাবল ডিলাক্স',
            'icon'      => '🟡',
            'color'     => 'var(--cabin-deluxe)',
            'sharing'   => 4,
            'holiday'   => 10000,
            'weekday'   => 8000,
            'amenities' => array( 'AC', 'Attached Washroom', 'Double Bed', 'Infinity Glass Window', 'Extra Space' ),
            'desc'      => 'পরিবারের জন্য আদর্শ — ৪ জনের প্রশস্ত কেবিনে প্রিমিয়াম সুবিধা।',
        ),
        array(
            'name'      => 'Luxury Triple',
            'name_bn'   => 'লাক্সারি ট্রিপল',
            'icon'      => '🟣',
            'color'     => 'var(--cabin-luxury)',
            'sharing'   => 3,
            'holiday'   => 12000,
            'weekday'   => 9600,
            'amenities' => array( 'AC', 'Attached Washroom', 'Double Bed', 'Infinity Glass Window', 'Premium Bedding' ),
            'desc'      => 'মাত্র ৩ জনে একটি বড় কেবিন — সর্বোচ্চ Privacy ও Comfort।',
        ),
        array(
            'name'      => 'Exclusive Couple',
            'name_bn'   => 'এক্সক্লুসিভ কাপল',
            'icon'      => '🔴',
            'color'     => 'var(--cabin-exclusive)',
            'sharing'   => 2,
            'holiday'   => 13000,
            'weekday'   => 10400,
            'amenities' => array( 'AC', 'Attached Washroom', 'King Bed', 'Infinity Glass Window', 'Premium Bedding', 'Private Setting' ),
            'desc'      => 'দুজনের জন্য সম্পূর্ণ Private কেবিন — Anniversary, Honeymoon বা বিশেষ মুহূর্তের জন্য।',
        ),
    );
}

/**
 * Helper: Get trip spots
 */
function bhela_get_spots() {
    return array(
        array(
            'name'    => 'টাঙ্গুয়ার হাওর',
            'name_en' => 'Tanguar Haor',
            'desc'    => 'বাংলাদেশের দ্বিতীয় বৃহত্তম মিঠাপানির জলাভূমি — UNESCO Ramsar Site',
            'icon'    => '🌊',
        ),
        array(
            'name'    => 'যাদুকাটা নদী',
            'name_en' => 'Jadukata River',
            'desc'    => 'স্ফটিকস্বচ্ছ পানি ও মেঘালয়ের পাহাড়ের পাদদেশে অসাধারণ দৃশ্য',
            'icon'    => '💎',
        ),
        array(
            'name'    => 'বারিক্কা টিলা',
            'name_en' => 'Barikka Tila',
            'desc'    => 'হাওরের মাঝে সবুজ টিলা — বর্ষায় চারপাশ জলে ঘেরা অপরূপ দৃশ্য',
            'icon'    => '⛰️',
        ),
        array(
            'name'    => 'নীলাদ্রি লেক',
            'name_en' => 'Niladri Lake',
            'desc'    => 'পাথরের ফাঁকে নীল-সবুজ পানি — বাংলাদেশের অন্যতম সুন্দর স্পট',
            'icon'    => '🏞️',
        ),
        array(
            'name'    => 'ওয়াচ টাওয়ার',
            'name_en' => 'Watch Tower',
            'desc'    => 'টাঙ্গুয়ার হাওরের বিস্তৃত জলরাজ্যের ৩৬০° ভিউ',
            'icon'    => '🗼',
        ),
        array(
            'name'    => 'শিমুল বাগান',
            'name_en' => 'Shimul Bagan',
            'desc'    => 'শীতে লাল শিমুল ফুলে ছেয়ে যায় — অসাধারণ ফটোগ্রাফি স্পট (মৌসুমভেদে)',
            'icon'    => '🌺',
        ),
        array(
            'name'    => 'টেকেরঘাট',
            'name_en' => 'Tekerghat',
            'desc'    => 'মেঘালয় সীমান্ত এলাকা — পাহাড়, ঝর্ণা ও প্রকৃতির অপূর্ব মিলন',
            'icon'    => '🏔️',
        ),
    );
}

/**
 * Helper: Get FAQ data
 */
function bhela_get_faqs() {
    return array(
        'হাওর সম্পর্কে' => array(
            array(
                'q' => 'টাঙ্গুয়ার হাওর কোথায় অবস্থিত?',
                'a' => 'টাঙ্গুয়ার হাওর সুনামগঞ্জ জেলার তাহিরপুর ও ধর্মপাশা উপজেলায় অবস্থিত। এটি বাংলাদেশের দ্বিতীয় বৃহত্তম মিঠাপানির জলাভূমি এবং UNESCO Ramsar Site হিসেবে স্বীকৃত।',
            ),
            array(
                'q' => 'টাঙ্গুয়ার হাওর ভ্রমণের সেরা সময় কখন?',
                'a' => 'জুন থেকে সেপ্টেম্বর (বর্ষাকাল) সবচেয়ে সুন্দর সময় — হাওর বিশাল জলরাজ্যে পরিণত হয়। জাদুকাটা নদী, নীলাদ্রি লেক, বারিক্কা টিলা সবচেয়ে সুন্দর দেখায় এই সময়ে।',
            ),
        ),
        'ভেলা সম্পর্কে' => array(
            array(
                'q' => 'ভেলা কেন আলাদা অন্যান্য হাউসবোট থেকে?',
                'a' => 'ভেলায় আছে বড় Family Cabin, AC ও Non-AC সুবিধা, প্রতি কেবিনে Attached Washroom, Infinity Style Glass Window, প্রশস্ত Rooftop Lounge, প্রশিক্ষিত স্টাফ, দেশীয় প্রিমিয়াম খাবার এবং সর্বোচ্চ Privacy ও নিরাপত্তা। অপরিচিত গ্রুপের সাথে কেবিন শেয়ার করা হয় না।',
            ),
            array(
                'q' => 'ভেলায় মোট কতজন যেতে পারে?',
                'a' => 'ভেলায় ৬টি বড় Family Cabin আছে। প্রতি কেবিনে ৪-৬ জন থাকতে পারে। সর্বোচ্চ ক্যাপাসিটি ৪০ জন (লবি সহ)।',
            ),
            array(
                'q' => 'বোটে কি AC আছে?',
                'a' => 'হ্যাঁ, সকল কেবিনে AC ও Non-AC উভয় অপশন আছে। আপনার পছন্দ অনুযায়ী বুকিংয়ের সময় জানান।',
            ),
        ),
        'রেট ও প্যাকেজ' => array(
            array(
                'q' => 'জনপ্রতি খরচ কত?',
                'a' => '২ দিন ১ রাতের প্যাকেজে জনপ্রতি রেট শুরু ৳৮,০০০ (৬ জন শেয়ারিং, Budget Friendly) থেকে ৳১৩,০০০ (২ জন, Exclusive Couple) পর্যন্ত। Weekday-তে সকল প্যাকেজে ২০% পর্যন্ত ছাড়!',
            ),
            array(
                'q' => 'জনপ্রতি খরচ কমানোর উপায় কী?',
                'a' => 'বড় গ্রুপে যান, Full Boat Reservation নিন, Weekday-তে ট্রিপ করুন, অথবা কেবিনে বেশি সদস্য থাকুন — প্রতিটিতে জনপ্রতি খরচ কমে আসে।',
            ),
            array(
                'q' => 'প্যাকেজে কী কী অন্তর্ভুক্ত?',
                'a' => '২ দিন ১ রাত আবাসন, ২ Breakfast, ২ Lunch, ১ Dinner, Evening Snacks, চা-কফি, Welcome Drinks, উল্লেখিত স্পট ভ্রমণ, গাইড, ২৪ ঘণ্টা স্টাফ সাপোর্ট, Life Jacket ও নিরাপত্তা সরঞ্জাম, বিশুদ্ধ পানীয় জল।',
            ),
            array(
                'q' => 'বাচ্চাদের জন্য চার্জ কত?',
                'a' => '০-৪ বছর: সম্পূর্ণ ফ্রি (আলাদা মিল/বেড ছাড়া)। ৪-৮ বছর: ৫০% চার্জ। ৯+ বছর: পূর্ণ চার্জ।',
            ),
        ),
        'বুকিং ও পেমেন্ট' => array(
            array(
                'q' => 'কিভাবে বুকিং করব?',
                'a' => 'WhatsApp-এ (+8801793395556) তারিখ, গ্রুপ সাইজ ও পছন্দের কেবিন জানান। ৫০% Advance দিলে বুকিং কনফার্ম হবে। বুকিং প্রসেস: তারিখ নির্বাচন → সদস্য সংখ্যা → কেবিন নির্বাচন → ৫০% অগ্রিম → Confirmation → ভ্রমণ।',
            ),
            array(
                'q' => 'Payment কিভাবে করব?',
                'a' => 'bKash, Nagad অথবা Bank Transfer-এ পেমেন্ট করতে পারবেন। মোট মূল্যের ৫০% Advance বুকিংয়ের সময় এবং বাকি ৫০% Check-in-এ।',
            ),
        ),
        'বাতিলকরণ ও ফেরত' => array(
            array(
                'q' => 'বুকিং বাতিল করলে কি রিফান্ড পাবো?',
                'a' => '২১+ দিন আগে বাতিলে Advance-এর ৫০% Refund। ৮-২০ দিন আগে Cash Refund নেই তবে Future Credit/Reschedule বিবেচনাযোগ্য। ৭ দিনের কমে কোনো Refund নেই। No Show-তে সম্পূর্ণ বাতিল।',
            ),
            array(
                'q' => 'Reschedule করা যায়?',
                'a' => 'ট্রিপের কমপক্ষে ৭ দিন আগে লিখিতভাবে জানালে একবার Reschedule সম্ভব (সিট/তারিখ প্রাপ্যতা সাপেক্ষে)। Peak Season/Holiday-তে সীমিত।',
            ),
        ),
        'নিরাপত্তা' => array(
            array(
                'q' => 'ভেলা কি নিরাপদ?',
                'a' => 'হ্যাঁ, সম্পূর্ণ নিরাপদ। Life Jacket সরবরাহ করা হয়, প্রশিক্ষিত ও অভিজ্ঞ Crew আছে, বোট নিরাপদ গতিতে চলে, এবং রাতে নিরাপদে থাকা যায়।',
            ),
            array(
                'q' => 'বৃষ্টি হলে কি ট্রিপ বাতিল হবে?',
                'a' => 'সাধারণ বৃষ্টিতে ট্রিপ হবে — বর্ষায় বৃষ্টিই হাওরের আসল সৌন্দর্য! অতিরিক্ত খারাপ আবহাওয়া বা প্রাকৃতিক দুর্যোগে নিরাপত্তা বিবেচনায় সিদ্ধান্ত নেওয়া হয়।',
            ),
        ),
    );
}

/**
 * Custom excerpt length
 */
function bhela_excerpt_length( $length ) {
    return 25;
}
add_filter( 'excerpt_length', 'bhela_excerpt_length' );

/**
 * Remove WordPress version from head
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Add preconnect for Google Fonts
 */
function bhela_resource_hints( $urls, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $urls[] = array(
            'href' => 'https://fonts.googleapis.com',
            'crossorigin' => true,
        );
        $urls[] = array(
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => true,
        );
    }
    return $urls;
}
add_filter( 'wp_resource_hints', 'bhela_resource_hints', 10, 2 );

/**
 * Add body classes
 */
function bhela_body_classes( $classes ) {
    if ( is_front_page() ) {
        $classes[] = 'is-front-page';
    }
    if ( is_page_template() ) {
        $classes[] = 'has-template';
    }
    return $classes;
}
add_filter( 'body_class', 'bhela_body_classes' );
