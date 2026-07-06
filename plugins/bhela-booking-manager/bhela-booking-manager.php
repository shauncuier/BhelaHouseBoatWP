<?php
/**
 * Plugin Name: BHELA Booking Manager
 * Description: Captures and stores tour booking inquiries for the BHELA Houseboat custom WordPress theme.
 * Version: 1.0.0
 * Author: 3s-Soft
 * Author URI: https://3s-soft.com
 * License: GPLv2 or later
 * Text Domain: bhela-booking-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Register Booking Custom Post Type
 */
function bhela_register_booking_cpt() {
    $labels = array(
        'name'               => _x( 'Bookings', 'post type general name', 'bhela-booking-manager' ),
        'singular_name'      => _x( 'Booking', 'post type singular name', 'bhela-booking-manager' ),
        'menu_name'          => _x( 'Bookings', 'admin menu', 'bhela-booking-manager' ),
        'name_admin_bar'     => _x( 'Booking', 'add new on admin bar', 'bhela-booking-manager' ),
        'add_new'            => _x( 'Add New', 'booking', 'bhela-booking-manager' ),
        'add_new_item'       => __( 'Add New Booking', 'bhela-booking-manager' ),
        'new_item'           => __( 'New Booking', 'bhela-booking-manager' ),
        'edit_item'          => __( 'View/Edit Booking', 'bhela-booking-manager' ),
        'view_item'          => __( 'View Booking', 'bhela-booking-manager' ),
        'all_items'          => __( 'All Bookings', 'bhela-booking-manager' ),
        'search_items'       => __( 'Search Bookings', 'bhela-booking-manager' ),
        'not_found'          => __( 'No bookings found.', 'bhela-booking-manager' ),
        'not_found_in_trash' => __( 'No bookings found in Trash.', 'bhela-booking-manager' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false, // Keep private from frontend queries
        'publicly_queryable' => false,
        'show_ui'            => true,  // Show in admin interface
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-calendar-alt',
        'supports'           => array( 'title' ) // Customer Name is the title
    );

    register_post_type( 'bhela_booking', $args );
}
add_action( 'init', 'bhela_register_booking_cpt' );

/**
 * Add Custom Admin Columns for Bookings List
 */
function bhela_booking_table_columns( $columns ) {
    $new_columns = array(
        'cb'            => $columns['cb'],
        'title'         => __( 'Name', 'bhela-booking-manager' ),
        'phone'         => __( 'Phone', 'bhela-booking-manager' ),
        'cabin'         => __( 'Cabin Class', 'bhela-booking-manager' ),
        'travel_date'   => __( 'Travel Date', 'bhela-booking-manager' ),
        'guests'        => __( 'Guests', 'bhela-booking-manager' ),
        'date'          => __( 'Submission Date', 'bhela-booking-manager' )
    );
    return $new_columns;
}
add_filter( 'manage_bhela_booking_posts_columns', 'bhela_booking_table_columns' );

/**
 * Output Column Content
 */
function bhela_booking_table_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'phone':
            $phone = get_post_meta( $post_id, '_bhela_phone', true );
            echo esc_html( $phone ? $phone : '—' );
            break;
        case 'cabin':
            $cabin = get_post_meta( $post_id, '_bhela_cabin_type', true );
            echo esc_html( $cabin ? $cabin : '—' );
            break;
        case 'travel_date':
            $date = get_post_meta( $post_id, '_bhela_travel_date', true );
            echo esc_html( $date ? $date : '—' );
            break;
        case 'guests':
            $guests = get_post_meta( $post_id, '_bhela_guests', true );
            echo esc_html( $guests ? $guests : '—' );
            break;
    }
}
add_action( 'manage_bhela_booking_posts_custom_column', 'bhela_booking_table_column_content', 10, 2 );

/**
 * Register Sortable Columns
 */
function bhela_booking_sortable_columns( $columns ) {
    $columns['travel_date'] = 'travel_date';
    return $columns;
}
add_filter( 'manage_edit-bhela_booking_sortable_columns', 'bhela_booking_sortable_columns' );

/**
 * Add Meta Box for Booking Detail View
 */
function bhela_booking_add_meta_box() {
    add_meta_box(
        'bhela_booking_details',
        __( 'Booking details', 'bhela-booking-manager' ),
        'bhela_booking_details_callback',
        'bhela_booking',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'bhela_booking_add_meta_box' );

/**
 * Display Meta Box Content
 */
function bhela_booking_details_callback( $post ) {
    // Retrieve values
    $phone       = get_post_meta( $post->ID, '_bhela_phone', true );
    $travel_date = get_post_meta( $post->ID, '_bhela_travel_date', true );
    $cabin_type  = get_post_meta( $post->ID, '_bhela_cabin_type', true );
    $guests      = get_post_meta( $post->ID, '_bhela_guests', true );
    $message     = get_post_meta( $post->ID, '_bhela_message', true );

    // Inline CSS for clean styling in WordPress admin panel
    ?>
    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e( 'Customer Name', 'bhela-booking-manager' ); ?></label></th>
            <td><strong><?php echo esc_html( $post->post_title ); ?></strong></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Phone Number', 'bhela-booking-manager' ); ?></label></th>
            <td><a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Selected Cabin', 'bhela-booking-manager' ); ?></label></th>
            <td><?php echo esc_html( $cabin_type ); ?></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Travel Date', 'bhela-booking-manager' ); ?></label></th>
            <td><strong><?php echo esc_html( $travel_date ); ?></strong></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Total Guests', 'bhela-booking-manager' ); ?></label></th>
            <td><?php echo esc_html( $guests ); ?></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Message / Notes', 'bhela-booking-manager' ); ?></label></th>
            <td><p style="white-space: pre-wrap; background: #f6f7f7; padding: 12px; border-radius: 4px; border: 1px solid #ccd0d4;"><?php echo esc_html( $message ? $message : 'No custom message.' ); ?></p></td>
        </tr>
    </table>
    <?php
}

/**
 * Handle Frontend AJAX Booking Submission
 */
function bhela_ajax_submit_booking() {
    // Check request signature
    if ( empty( $_POST['bhela_name'] ) || empty( $_POST['bhela_phone'] ) || empty( $_POST['bhela_date'] ) ) {
        wp_send_json_error( array( 'message' => __( 'অনুগ্রহ করে সবগুলি আবশ্যকীয় তথ্য পূরণ করুন।', 'bhela-booking-manager' ) ) );
    }

    // Sanitize values
    $name        = sanitize_text_field( $_POST['bhela_name'] );
    $phone       = sanitize_text_field( $_POST['bhela_phone'] );
    $travel_date = sanitize_text_field( $_POST['bhela_date'] );
    $cabin_type  = sanitize_text_field( $_POST['bhela_cabin'] );
    $guests      = intval( $_POST['bhela_guests'] );
    $message     = sanitize_textarea_field( $_POST['bhela_message'] );

    // Insert Post into bhela_booking CPT
    $post_id = wp_insert_post( array(
        'post_title'  => $name,
        'post_type'   => 'bhela_booking',
        'post_status' => 'publish'
    ) );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'দুঃখিত, তথ্য সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking-manager' ) ) );
    }

    // Save Meta Data
    update_post_meta( $post_id, '_bhela_phone', $phone );
    update_post_meta( $post_id, '_bhela_travel_date', $travel_date );
    update_post_meta( $post_id, '_bhela_cabin_type', $cabin_type );
    update_post_meta( $post_id, '_bhela_guests', $guests );
    update_post_meta( $post_id, '_bhela_message', $message );

    // Send Email to Admin
    $admin_email = get_option( 'admin_email' );
    $subject = sprintf( __( 'BHELA: New Tour Inquiry from %s', 'bhela-booking-manager' ), $name );
    $body  = "You have received a new booking inquiry on BHELA Houseboat:\n\n";
    $body .= "Customer Name: $name\n";
    $body .= "Phone: $phone\n";
    $body .= "Travel Date: $travel_date\n";
    $body .= "Cabin: $cabin_type\n";
    $body .= "Guests: $guests\n\n";
    $body .= "Message:\n$message\n\n";
    $body .= "Manage this booking in Admin:\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";
    
    wp_mail( $admin_email, $subject, $body );

    // Format WhatsApp Deep Link (fast booking redirection)
    // Customizer value fallback to +8801793395556
    $whatsapp_num = get_theme_mod( 'bhela_whatsapp', '+8801793395556' );
    $whatsapp_num = preg_replace( '/[^0-9]/', '', $whatsapp_num ); // Only digits

    $wa_text = sprintf(
        "আসসালামু আলাইকুম। আমি ভেলা হাউসবোট বুকিং করতে চাই।\n\nনাম: %s\nমোবাইল: %s\nতারিখ: %s\nকেবিন: %s\nঅতিথি: %d জন\n\nবিশেষ নোট: %s",
        $name, $phone, $travel_date, $cabin_type, $guests, $message
    );
    $whatsapp_url = 'https://wa.me/' . $whatsapp_num . '?text=' . urlencode( $wa_text );

    wp_send_json_success( array(
        'message'      => __( 'আপনার বুকিং রিকোয়েস্ট সফলভাবে জমা হয়েছে। দ্রুত বুকিং কনফার্ম করতে নিচের বাটনটি চেপে সরাসরি আমাদের সাথে WhatsApp-এ যোগাযোগ করুন!', 'bhela-booking-manager' ),
        'whatsapp_url' => $whatsapp_url
    ) );
}
add_action( 'wp_ajax_bhela_submit_booking', 'bhela_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_bhela_submit_booking', 'bhela_ajax_submit_booking' );

/**
 * Enqueue Frontend Variables for AJAX Submission
 */
function bhela_booking_enqueue_scripts() {
    wp_localize_script( 'bhela-main', 'bhela_booking_vars', array(
        'ajax_url' => admin_url( 'admin-ajax.php' )
    ) );
}
add_action( 'wp_enqueue_scripts', 'bhela_booking_enqueue_scripts', 20 );
