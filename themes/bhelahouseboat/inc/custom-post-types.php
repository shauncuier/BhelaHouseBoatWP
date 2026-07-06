<?php
/**
 * Custom Post Types for BHELA Houseboat
 *
 * @package BhelaHouseboat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register Custom Post Types
 */
function bhela_register_post_types() {

    // Reviews CPT
    register_post_type( 'bhela_review', array(
        'labels' => array(
            'name'               => __( 'Reviews', 'bhelahouseboat' ),
            'singular_name'      => __( 'Review', 'bhelahouseboat' ),
            'add_new'            => __( 'Add New Review', 'bhelahouseboat' ),
            'add_new_item'       => __( 'Add New Review', 'bhelahouseboat' ),
            'edit_item'          => __( 'Edit Review', 'bhelahouseboat' ),
            'view_item'          => __( 'View Review', 'bhelahouseboat' ),
            'all_items'          => __( 'All Reviews', 'bhelahouseboat' ),
            'search_items'       => __( 'Search Reviews', 'bhelahouseboat' ),
            'not_found'          => __( 'No reviews found.', 'bhelahouseboat' ),
            'not_found_in_trash' => __( 'No reviews found in Trash.', 'bhelahouseboat' ),
            'menu_name'          => __( 'Guest Reviews', 'bhelahouseboat' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-star-filled',
        'supports'     => array( 'title', 'editor', 'thumbnail' ),
        'has_archive'  => false,
    ) );

    // Trip Schedule CPT
    register_post_type( 'bhela_trip', array(
        'labels' => array(
            'name'               => __( 'Trip Dates', 'bhelahouseboat' ),
            'singular_name'      => __( 'Trip Date', 'bhelahouseboat' ),
            'add_new'            => __( 'Add Trip Date', 'bhelahouseboat' ),
            'add_new_item'       => __( 'Add New Trip Date', 'bhelahouseboat' ),
            'edit_item'          => __( 'Edit Trip Date', 'bhelahouseboat' ),
            'view_item'          => __( 'View Trip Date', 'bhelahouseboat' ),
            'all_items'          => __( 'All Trip Dates', 'bhelahouseboat' ),
            'search_items'       => __( 'Search Trip Dates', 'bhelahouseboat' ),
            'not_found'          => __( 'No trip dates found.', 'bhelahouseboat' ),
            'menu_name'          => __( 'Trip Schedule', 'bhelahouseboat' ),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-calendar-alt',
        'supports'     => array( 'title' ),
        'has_archive'  => false,
    ) );
}
add_action( 'init', 'bhela_register_post_types' );

/**
 * Add meta boxes for Review
 */
function bhela_review_meta_boxes() {
    add_meta_box(
        'bhela_review_details',
        __( 'Review Details', 'bhelahouseboat' ),
        'bhela_review_meta_box_callback',
        'bhela_review',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'bhela_review_meta_boxes' );

function bhela_review_meta_box_callback( $post ) {
    wp_nonce_field( 'bhela_review_meta', 'bhela_review_meta_nonce' );

    $reviewer_name = get_post_meta( $post->ID, '_bhela_reviewer_name', true );
    $reviewer_location = get_post_meta( $post->ID, '_bhela_reviewer_location', true );
    $rating = get_post_meta( $post->ID, '_bhela_rating', true );
    $trip_date = get_post_meta( $post->ID, '_bhela_trip_date', true );

    ?>
    <p>
        <label for="bhela_reviewer_name"><strong><?php _e( 'Reviewer Name:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="text" id="bhela_reviewer_name" name="bhela_reviewer_name" value="<?php echo esc_attr( $reviewer_name ); ?>" class="widefat">
    </p>
    <p>
        <label for="bhela_reviewer_location"><strong><?php _e( 'Location:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="text" id="bhela_reviewer_location" name="bhela_reviewer_location" value="<?php echo esc_attr( $reviewer_location ); ?>" class="widefat" placeholder="e.g. Dhaka, Bangladesh">
    </p>
    <p>
        <label for="bhela_rating"><strong><?php _e( 'Rating (1-5):', 'bhelahouseboat' ); ?></strong></label><br>
        <select id="bhela_rating" name="bhela_rating">
            <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                <option value="<?php echo $i; ?>" <?php selected( $rating, $i ); ?>><?php echo $i; ?> ⭐</option>
            <?php endfor; ?>
        </select>
    </p>
    <p>
        <label for="bhela_trip_date"><strong><?php _e( 'Trip Date:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="date" id="bhela_trip_date" name="bhela_trip_date" value="<?php echo esc_attr( $trip_date ); ?>">
    </p>
    <?php
}

function bhela_save_review_meta( $post_id ) {
    if ( ! isset( $_POST['bhela_review_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bhela_review_meta_nonce'], 'bhela_review_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array( 'bhela_reviewer_name', 'bhela_reviewer_location', 'bhela_rating', 'bhela_trip_date' );
    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }
}
add_action( 'save_post_bhela_review', 'bhela_save_review_meta' );

/**
 * Add meta boxes for Trip Schedule
 */
function bhela_trip_meta_boxes() {
    add_meta_box(
        'bhela_trip_details',
        __( 'Trip Details', 'bhelahouseboat' ),
        'bhela_trip_meta_box_callback',
        'bhela_trip',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'bhela_trip_meta_boxes' );

function bhela_trip_meta_box_callback( $post ) {
    wp_nonce_field( 'bhela_trip_meta', 'bhela_trip_meta_nonce' );

    $start_date = get_post_meta( $post->ID, '_bhela_start_date', true );
    $end_date = get_post_meta( $post->ID, '_bhela_end_date', true );
    $status = get_post_meta( $post->ID, '_bhela_trip_status', true );
    $day_type = get_post_meta( $post->ID, '_bhela_day_type', true );
    $note = get_post_meta( $post->ID, '_bhela_trip_note', true );

    ?>
    <p>
        <label for="bhela_start_date"><strong><?php _e( 'Start Date:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="date" id="bhela_start_date" name="bhela_start_date" value="<?php echo esc_attr( $start_date ); ?>">
    </p>
    <p>
        <label for="bhela_end_date"><strong><?php _e( 'End Date:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="date" id="bhela_end_date" name="bhela_end_date" value="<?php echo esc_attr( $end_date ); ?>">
    </p>
    <p>
        <label for="bhela_day_type"><strong><?php _e( 'Day Type:', 'bhelahouseboat' ); ?></strong></label><br>
        <select id="bhela_day_type" name="bhela_day_type">
            <option value="weekday" <?php selected( $day_type, 'weekday' ); ?>>Weekday</option>
            <option value="weekend" <?php selected( $day_type, 'weekend' ); ?>>Weekend</option>
            <option value="holiday" <?php selected( $day_type, 'holiday' ); ?>>Holiday</option>
        </select>
    </p>
    <p>
        <label for="bhela_trip_status"><strong><?php _e( 'Status:', 'bhelahouseboat' ); ?></strong></label><br>
        <select id="bhela_trip_status" name="bhela_trip_status">
            <option value="available" <?php selected( $status, 'available' ); ?>>Available</option>
            <option value="filling" <?php selected( $status, 'filling' ); ?>>Filling Fast</option>
            <option value="booked" <?php selected( $status, 'booked' ); ?>>Fully Booked</option>
        </select>
    </p>
    <p>
        <label for="bhela_trip_note"><strong><?php _e( 'Note:', 'bhelahouseboat' ); ?></strong></label><br>
        <input type="text" id="bhela_trip_note" name="bhela_trip_note" value="<?php echo esc_attr( $note ); ?>" class="widefat" placeholder="e.g. Full Moon Trip 🌕">
    </p>
    <?php
}

function bhela_save_trip_meta( $post_id ) {
    if ( ! isset( $_POST['bhela_trip_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bhela_trip_meta_nonce'], 'bhela_trip_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array( 'bhela_start_date', 'bhela_end_date', 'bhela_trip_status', 'bhela_day_type', 'bhela_trip_note' );
    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }
}
add_action( 'save_post_bhela_trip', 'bhela_save_trip_meta' );
