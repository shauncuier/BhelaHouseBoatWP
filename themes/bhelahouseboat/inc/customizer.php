<?php
/**
 * Customizer Settings for BHELA Houseboat
 *
 * @package BhelaHouseboat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function bhela_customize_register( $wp_customize ) {

    // =============================================
    // BHELA Contact Information Panel
    // =============================================
    $wp_customize->add_panel( 'bhela_panel', array(
        'title'    => __( 'BHELA Settings', 'bhelahouseboat' ),
        'priority' => 30,
    ) );

    // --- Contact Section ---
    $wp_customize->add_section( 'bhela_contact', array(
        'title' => __( 'Contact Information', 'bhelahouseboat' ),
        'panel' => 'bhela_panel',
    ) );

    // Phone Primary
    $wp_customize->add_setting( 'bhela_phone_primary', array(
        'default'           => '01891-562461',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_phone_primary', array(
        'label'   => __( 'Primary Phone', 'bhelahouseboat' ),
        'section' => 'bhela_contact',
        'type'    => 'text',
    ) );

    // Phone Secondary
    $wp_customize->add_setting( 'bhela_phone_secondary', array(
        'default'           => '01614-182769',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_phone_secondary', array(
        'label'   => __( 'Secondary Phone', 'bhelahouseboat' ),
        'section' => 'bhela_contact',
        'type'    => 'text',
    ) );

    // WhatsApp Primary
    $wp_customize->add_setting( 'bhela_whatsapp_primary', array(
        'default'           => '+8801793395556',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_whatsapp_primary', array(
        'label'   => __( 'WhatsApp Primary', 'bhelahouseboat' ),
        'section' => 'bhela_contact',
        'type'    => 'text',
    ) );

    // WhatsApp Secondary
    $wp_customize->add_setting( 'bhela_whatsapp_secondary', array(
        'default'           => '+8801781720957',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_whatsapp_secondary', array(
        'label'   => __( 'WhatsApp Secondary', 'bhelahouseboat' ),
        'section' => 'bhela_contact',
        'type'    => 'text',
    ) );

    // Email
    $wp_customize->add_setting( 'bhela_email', array(
        'default'           => 'infobhela@gmail.com',
        'sanitize_callback' => 'sanitize_email',
    ) );
    $wp_customize->add_control( 'bhela_email', array(
        'label'   => __( 'Email Address', 'bhelahouseboat' ),
        'section' => 'bhela_contact',
        'type'    => 'email',
    ) );

    // --- Social Media Section ---
    $wp_customize->add_section( 'bhela_social', array(
        'title' => __( 'Social Media', 'bhelahouseboat' ),
        'panel' => 'bhela_panel',
    ) );

    $socials = array(
        'facebook'  => __( 'Facebook Page URL', 'bhelahouseboat' ),
        'instagram' => __( 'Instagram URL', 'bhelahouseboat' ),
        'youtube'   => __( 'YouTube Channel URL', 'bhelahouseboat' ),
        'tiktok'    => __( 'TikTok URL', 'bhelahouseboat' ),
    );

    foreach ( $socials as $key => $label ) {
        $wp_customize->add_setting( 'bhela_social_' . $key, array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( 'bhela_social_' . $key, array(
            'label'   => $label,
            'section' => 'bhela_social',
            'type'    => 'url',
        ) );
    }

    // --- Hero Section ---
    $wp_customize->add_section( 'bhela_hero', array(
        'title' => __( 'Hero Section', 'bhelahouseboat' ),
        'panel' => 'bhela_panel',
    ) );

    // Hero Tagline
    $wp_customize->add_setting( 'bhela_hero_tagline', array(
        'default'           => 'ভেলার আকর্ষণ ভেলা নয়, হাওর!',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_hero_tagline', array(
        'label'   => __( 'Hero Tagline', 'bhelahouseboat' ),
        'section' => 'bhela_hero',
        'type'    => 'text',
    ) );

    // Hero Subtitle
    $wp_customize->add_setting( 'bhela_hero_subtitle', array(
        'default'           => 'Where Nature, Comfort & Memories Meet',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_hero_subtitle', array(
        'label'   => __( 'Hero Subtitle (English)', 'bhelahouseboat' ),
        'section' => 'bhela_hero',
        'type'    => 'text',
    ) );

    // Hero Background Image
    $wp_customize->add_setting( 'bhela_hero_image', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'bhela_hero_image', array(
        'label'   => __( 'Hero Background Image', 'bhelahouseboat' ),
        'section' => 'bhela_hero',
    ) ) );

    // --- Booking Section ---
    $wp_customize->add_section( 'bhela_booking', array(
        'title' => __( 'Booking Settings', 'bhelahouseboat' ),
        'panel' => 'bhela_panel',
    ) );

    // Booking CTA Text
    $wp_customize->add_setting( 'bhela_booking_cta', array(
        'default'           => 'আপনার তারিখ ও অতিথি সংখ্যা জানান — WhatsApp এ রেট পান ২ মিনিটে',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_booking_cta', array(
        'label'   => __( 'Booking CTA Text', 'bhelahouseboat' ),
        'section' => 'bhela_booking',
        'type'    => 'textarea',
    ) );

    // Payment methods note
    $wp_customize->add_setting( 'bhela_payment_methods', array(
        'default'           => 'bKash · Nagad · Bank Transfer',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'bhela_payment_methods', array(
        'label'   => __( 'Payment Methods Text', 'bhelahouseboat' ),
        'section' => 'bhela_booking',
        'type'    => 'text',
    ) );
}
add_action( 'customize_register', 'bhela_customize_register' );
