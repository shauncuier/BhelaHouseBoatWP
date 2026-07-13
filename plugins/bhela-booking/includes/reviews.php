<?php
/**
 * Guest reviews: CPT + admin fields + shortcode + theme helper.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- CPT ---------- */

function bhela_bm_register_reviews_cpt() {
	register_post_type( 'bhela_review', array(
		'labels' => array(
			'name'          => 'Reviews',
			'singular_name' => 'Review',
			'menu_name'     => '⭐ Reviews',
			'add_new_item'  => 'Add New Review (নতুন রিভিউ)',
			'edit_item'     => 'Edit Review',
			'all_items'     => 'All Reviews',
			'not_found'     => 'No reviews yet.',
		),
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=bhela_booking',
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		'supports'           => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'bhela_bm_register_reviews_cpt' );

/* ---------- Meta box: rating + subtitle ---------- */

function bhela_bm_review_meta_box() {
	add_meta_box( 'bhela_review_details', 'Review Details', 'bhela_bm_review_meta_cb', 'bhela_review', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_review_meta_box' );

function bhela_bm_review_meta_cb( $post ) {
	wp_nonce_field( 'bhela_bm_review_save', 'bhela_bm_review_nonce' );
	$rating   = get_post_meta( $post->ID, '_bhela_rating', true );
	$rating   = $rating ? (int) $rating : 5;
	$subtitle = get_post_meta( $post->ID, '_bhela_subtitle', true );
	?>
	<p><strong>Title = অতিথির নাম</strong>, মূল লেখা = রিভিউ টেক্সট।</p>
	<p><label for="bhela_rating"><strong>Rating (স্টার)</strong></label><br>
	<select name="bhela_rating" id="bhela_rating" style="width:100%">
		<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
			<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>><?php echo esc_html( str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ) ); ?></option>
		<?php endfor; ?>
	</select></p>
	<p><label for="bhela_subtitle"><strong>Trip Type / City</strong></label><br>
	<input type="text" name="bhela_subtitle" id="bhela_subtitle" style="width:100%" value="<?php echo esc_attr( $subtitle ); ?>" placeholder="Family Trip · Dhaka"></p>
	<?php
}

function bhela_bm_review_save( $post_id, $post ) {
	if ( 'bhela_review' !== $post->post_type ) {
		return;
	}
	if ( ! isset( $_POST['bhela_bm_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_review_nonce'] ) ), 'bhela_bm_review_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_bhela_rating', min( 5, max( 1, (int) ( $_POST['bhela_rating'] ?? 5 ) ) ) );
	update_post_meta( $post_id, '_bhela_subtitle', sanitize_text_field( $_POST['bhela_subtitle'] ?? '' ) );
}
add_action( 'save_post', 'bhela_bm_review_save', 10, 2 );

/* ---------- Admin columns ---------- */

function bhela_bm_review_columns( $columns ) {
	return array(
		'cb'       => $columns['cb'],
		'title'    => 'Guest Name',
		'rating'   => 'Rating',
		'subtitle' => 'Trip Type',
		'date'     => 'Date',
	);
}
add_filter( 'manage_bhela_review_posts_columns', 'bhela_bm_review_columns' );

function bhela_bm_review_column_content( $column, $post_id ) {
	if ( 'rating' === $column ) {
		$r = (int) get_post_meta( $post_id, '_bhela_rating', true );
		echo '<span style="color:#dba617;font-size:14px">' . esc_html( str_repeat( '★', $r ? $r : 5 ) ) . '</span>';
	}
	if ( 'subtitle' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_bhela_subtitle', true ) ?: '—' );
	}
}
add_action( 'manage_bhela_review_posts_custom_column', 'bhela_bm_review_column_content', 10, 2 );

/* ---------- Data helper (used by theme) ---------- */

function bhela_bm_get_reviews( $limit = 6 ) {
	$q = new WP_Query( array(
		'post_type'      => 'bhela_review',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		$out[] = array(
			'name'     => get_the_title( $p ),
			'text'     => wp_strip_all_tags( $p->post_content ),
			'rating'   => (int) ( get_post_meta( $p->ID, '_bhela_rating', true ) ?: 5 ),
			'subtitle' => get_post_meta( $p->ID, '_bhela_subtitle', true ),
		);
	}
	return $out;
}

/* ---------- Seed 3 sample reviews once ---------- */

function bhela_bm_seed_reviews() {
	if ( get_option( 'bhela_bm_reviews_seeded' ) ) {
		return;
	}
	$samples = array(
		array( 'রাশেদুল ইসলাম', 'পরিবার নিয়ে গিয়েছিলাম — কেবিন, খাবার, ক্রুদের ব্যবহার সবকিছু এক কথায় অসাধারণ। বাচ্চাদের নিয়ে এত নিরাপদ লেগেছে!', 'Family Trip · Dhaka' ),
		array( 'সাবরিনা আক্তার', 'অফিসের ২৮ জনের টিম নিয়ে Full Boat নিয়েছিলাম। রুফটপে টিম আড্ডা আর হাওরের সূর্যাস্ত — best team retreat ever!', 'Corporate Tour' ),
		array( 'তানভীর হাসান', 'Weekday অফারে বন্ধুরা মিলে গিয়েছিলাম। এই দামে AC কেবিন, এত খাবার আর ৭টা স্পট — টাঙ্গুয়ায় এর চেয়ে ভালো ডিল নেই।', 'Friends Group' ),
	);
	foreach ( $samples as $s ) {
		$id = wp_insert_post( array(
			'post_title'   => $s[0],
			'post_content' => $s[1],
			'post_type'    => 'bhela_review',
			'post_status'  => 'publish',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_bhela_rating', 5 );
			update_post_meta( $id, '_bhela_subtitle', $s[2] );
		}
	}
	update_option( 'bhela_bm_reviews_seeded', 1 );
}
add_action( 'admin_init', 'bhela_bm_seed_reviews' );

/* ---------- Shortcode: [bhela_reviews] ---------- */

function bhela_bm_reviews_shortcode( $atts ) {
	$atts    = shortcode_atts( array( 'limit' => 6 ), $atts );
	$reviews = bhela_bm_get_reviews( (int) $atts['limit'] );
	if ( ! $reviews ) {
		return '';
	}
	ob_start();
	echo '<div class="bhela-reviews-grid">';
	foreach ( $reviews as $r ) {
		echo '<div class="bhela-review-card">';
		echo '<div class="bhela-review-card__stars">' . esc_html( str_repeat( '★', $r['rating'] ) ) . '</div>';
		echo '<p>"' . esc_html( $r['text'] ) . '"</p>';
		echo '<div class="bhela-review-card__who"><span class="bhela-review-card__avatar">' . esc_html( mb_substr( $r['name'], 0, 1 ) ) . '</span><div><strong>' . esc_html( $r['name'] ) . '</strong>';
		if ( $r['subtitle'] ) {
			echo '<span>' . esc_html( $r['subtitle'] ) . '</span>';
		}
		echo '</div></div></div>';
	}
	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'bhela_reviews', 'bhela_bm_reviews_shortcode' );
