<?php
/**
 * Guest reviews: CPT + admin fields + shortcode + theme helper, plus the
 * post-trip submission flow.
 *
 * Once a booking is marked Completed the guest is emailed and texted a private,
 * token-signed link (same scheme as the invoice) where they can leave a rating,
 * a few words and some photos. Everything they send arrives as `pending` and is
 * invisible on the site until an admin publishes it — the three public read
 * paths all filter on post_status = publish, so moderation needs no extra gate.
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
			'add_new_item'  => 'Add New Review',
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
		'supports'           => array( 'title', 'editor', 'thumbnail' ),
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
	<p><strong>Title = guest name</strong>. The main editor content is the review text.</p>
	<p><label for="bhela_rating"><strong>Rating (stars)</strong></label><br>
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
		'cb'        => $columns['cb'],
		'title'     => 'Guest Name',
		'rvstatus'  => 'Status',
		'rating'    => 'Rating',
		'photos'    => 'Photos',
		'subtitle'  => 'Trip Type',
		'date'      => 'Date',
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
	if ( 'rvstatus' === $column ) {
		$pending = 'pending' === get_post_status( $post_id );
		$guest   = 'guest' === get_post_meta( $post_id, '_bhela_review_source', true );
		printf(
			'<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-weight:600;font-size:11px;color:#fff;background:%s">%s</span>%s',
			$pending ? '#b45309' : '#1a7f37',
			$pending ? esc_html__( 'Awaiting approval', 'bhela-booking' ) : esc_html__( 'Published', 'bhela-booking' ),
			$guest ? '<br><span style="font-size:11px;color:#646970">' . esc_html__( 'from guest', 'bhela-booking' ) . '</span>' : ''
		);
	}
	if ( 'photos' === $column ) {
		$photos = bhela_bm_review_photos( $post_id );
		if ( ! $photos ) {
			echo '—';
			return;
		}
		foreach ( array_slice( $photos, 0, 4 ) as $p ) {
			printf(
				'<a href="%s" target="_blank"><img src="%s" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:5px;margin:0 3px 3px 0"></a>',
				esc_url( $p['full'] ), esc_url( $p['thumb'] )
			);
		}
		if ( count( $photos ) > 4 ) {
			printf( '<span style="font-size:11px;color:#646970">+%d</span>', count( $photos ) - 4 );
		}
	}
}
add_action( 'manage_bhela_review_posts_custom_column', 'bhela_bm_review_column_content', 10, 2 );

/** One-click Approve on the reviews list. */
function bhela_bm_review_row_actions( $actions, $post ) {
	if ( 'bhela_review' !== $post->post_type || 'pending' !== $post->post_status ) {
		return $actions;
	}
	if ( ! current_user_can( 'publish_post', $post->ID ) ) {
		return $actions;
	}
	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=bhela_bm_review_approve&review=' . $post->ID ),
		'bhela_bm_review_approve_' . $post->ID
	);
	return array_merge(
		array( 'bhela_approve' => '<a href="' . esc_url( $url ) . '" style="color:#1a7f37;font-weight:600">' . esc_html__( '✓ Approve', 'bhela-booking' ) . '</a>' ),
		$actions
	);
}
add_filter( 'post_row_actions', 'bhela_bm_review_row_actions', 10, 2 );

function bhela_bm_review_approve() {
	$review_id = (int) ( $_GET['review'] ?? 0 );
	check_admin_referer( 'bhela_bm_review_approve_' . $review_id );
	if ( ! current_user_can( 'publish_post', $review_id ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ) );
	}
	$post = get_post( $review_id );
	if ( $post && 'bhela_review' === $post->post_type ) {
		wp_update_post( array( 'ID' => $review_id, 'post_status' => 'publish' ) );
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'settings', sprintf( 'Review published — %s', get_the_title( $review_id ) ) );
		}
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=bhela_review' ) );
	exit;
}
add_action( 'admin_post_bhela_bm_review_approve', 'bhela_bm_review_approve' );

/** How many reviews are waiting. */
function bhela_bm_reviews_pending_count() {
	$counts = wp_count_posts( 'bhela_review' );
	return isset( $counts->pending ) ? (int) $counts->pending : 0;
}

/** Pending bubble on the Reviews submenu, so a waiting review is never missed. */
function bhela_bm_reviews_menu_bubble() {
	global $submenu;
	$parent  = 'edit.php?post_type=bhela_booking';
	$pending = bhela_bm_reviews_pending_count();
	if ( ! $pending || empty( $submenu[ $parent ] ) ) {
		return;
	}
	foreach ( $submenu[ $parent ] as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=bhela_review' === $item[2] ) {
			$submenu[ $parent ][ $i ][0] .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$pending
			);
			break;
		}
	}
}
add_action( 'admin_menu', 'bhela_bm_reviews_menu_bubble', 1000 );

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
			'id'       => (int) $p->ID,
			'name'     => get_the_title( $p ),
			'text'     => wp_strip_all_tags( $p->post_content ),
			'rating'   => (int) ( get_post_meta( $p->ID, '_bhela_rating', true ) ?: 5 ),
			'subtitle' => get_post_meta( $p->ID, '_bhela_subtitle', true ),
			'photos'   => bhela_bm_review_photos( $p->ID ),
		);
	}
	return $out;
}

/**
 * Photos attached to a review, newest-attached last.
 *
 * @param int $review_id Review post ID.
 * @return array List of { thumb, full, alt }.
 */
function bhela_bm_review_photos( $review_id ) {
	$ids = get_post_meta( $review_id, '_bhela_review_photos', true );
	if ( ! is_array( $ids ) || ! $ids ) {
		return array();
	}
	$out = array();
	foreach ( $ids as $att_id ) {
		$thumb = wp_get_attachment_image_url( (int) $att_id, 'medium' );
		if ( ! $thumb ) {
			continue; // attachment deleted from the media library
		}
		$out[] = array(
			'thumb' => $thumb,
			'full'  => wp_get_attachment_image_url( (int) $att_id, 'large' ),
			'alt'   => get_the_title( $review_id ),
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
		if ( ! empty( $r['photos'] ) ) {
			echo '<div class="bhela-review-card__photos">';
			foreach ( $r['photos'] as $ph ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener"><img src="%s" alt="%s" loading="lazy"></a>',
					esc_url( $ph['full'] ), esc_url( $ph['thumb'] ), esc_attr( $ph['alt'] )
				);
			}
			echo '</div>';
		}
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

/* =========================================================
 * GUEST SUBMISSION — private link, form, upload, moderation
 * ========================================================= */

/**
 * Secret key for a booking's review link. Same construction as the invoice key
 * (includes/invoice.php): a full-length wp_hash over the booking id and its
 * post_date, so the token cannot be guessed and is stable for the booking.
 */
function bhela_bm_review_key( $booking_id ) {
	return wp_hash( 'bhela-review-' . $booking_id . get_post_field( 'post_date', $booking_id ) );
}

/** Private review URL — safe to email/text to the guest. */
function bhela_bm_review_url( $booking_id ) {
	return add_query_arg( array(
		'bhela_review_form' => (int) $booking_id,
		'key'               => bhela_bm_review_key( $booking_id ),
	), home_url( '/' ) );
}

/** The review a booking already has, or 0. */
function bhela_bm_review_for_booking( $booking_id ) {
	$found = get_posts( array(
		'post_type'        => 'bhela_review',
		'post_status'      => array( 'pending', 'publish', 'draft' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'meta_key'         => '_bhela_booking_id',
		'meta_value'       => (int) $booking_id,
		'no_found_rows'    => true,
		'suppress_filters' => false,
	) );
	return $found ? (int) $found[0] : 0;
}

/** Upload caps, clamped so a bad setting can never widen them silently. */
function bhela_bm_review_limits() {
	$s = bhela_bm_get_settings();
	return array(
		'photos' => min( 10, max( 0, (int) ( $s['review_max_photos'] ?? 5 ) ) ),
		'bytes'  => min( 20, max( 1, (int) ( $s['review_max_mb'] ?? 5 ) ) ) * MB_IN_BYTES,
	);
}

/** Image types a guest may upload. Deliberately narrow. */
function bhela_bm_review_mimes() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	);
}

/**
 * Render the review form when the private link is visited.
 *
 * Mirrors bhela_bm_maybe_render_invoice(): validate, include a template, exit.
 */
function bhela_bm_maybe_render_review_form() {
	if ( empty( $_GET['bhela_review_form'] ) ) {
		return;
	}
	$booking_id = (int) $_GET['bhela_review_form'];
	$post       = get_post( $booking_id );

	if ( ! $post || 'bhela_booking' !== $post->post_type ) {
		wp_die( esc_html__( 'Review link not found.', 'bhela-booking' ), 404 );
	}

	$key_ok   = isset( $_GET['key'] ) && hash_equals( bhela_bm_review_key( $booking_id ), (string) $_GET['key'] );
	$admin_ok = current_user_can( 'edit_post', $booking_id );
	if ( ! $key_ok && ! $admin_ok ) {
		wp_die( esc_html__( 'This review link is not valid.', 'bhela-booking' ), 403 );
	}

	// Only a finished trip can be reviewed.
	$status = get_post_meta( $booking_id, '_bhela_status', true );
	if ( 'completed' !== $status && ! $admin_ok ) {
		wp_die( esc_html__( 'This trip is not finished yet, so it cannot be reviewed.', 'bhela-booking' ), 403 );
	}

	$settings   = bhela_bm_get_settings();
	$limits     = bhela_bm_review_limits();
	$existing   = bhela_bm_review_for_booking( $booking_id );
	$submitted  = ! empty( $_GET['submitted'] );
	$error      = isset( $_GET['review_error'] ) ? sanitize_text_field( wp_unslash( $_GET['review_error'] ) ) : '';
	$guest_name = get_the_title( $booking_id );
	$invoice_no = get_post_meta( $booking_id, '_bhela_invoice_no', true );

	include BHELA_BM_PATH . 'templates/review-form.php';
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_review_form' );

/** Bounce back to the form with a message. */
function bhela_bm_review_redirect( $booking_id, $args = array() ) {
	wp_safe_redirect( add_query_arg( $args, bhela_bm_review_url( $booking_id ) ) );
	exit;
}

/**
 * Store the guest's photos.
 *
 * This is the only place the plugin accepts a file from someone who is not
 * logged in, so every file clears four gates before it is kept: the count cap,
 * the size cap, a real-content type check (wp_check_filetype_and_ext looks at
 * the bytes, not the extension), and getimagesize(). media_handle_upload() is
 * then given an explicit mime allow-list so even a miss above cannot land a
 * non-image.
 *
 * @param int $review_id Review to attach to.
 * @return int Number of photos stored.
 */
function bhela_bm_review_handle_photos( $review_id ) {
	$limits = bhela_bm_review_limits();
	if ( $limits['photos'] < 1 || empty( $_FILES['review_photos']['name'] ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$files  = $_FILES['review_photos'];
	$count  = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
	$mimes  = bhela_bm_review_mimes();
	$stored = array();

	for ( $i = 0; $i < $count && count( $stored ) < $limits['photos']; $i++ ) {
		if ( ! isset( $files['error'][ $i ] ) || UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
			continue; // includes UPLOAD_ERR_NO_FILE for empty slots
		}
		if ( (int) $files['size'][ $i ] > $limits['bytes'] || ! is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
			continue;
		}
		$check = wp_check_filetype_and_ext( $files['tmp_name'][ $i ], $files['name'][ $i ], $mimes );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], $mimes, true ) ) {
			continue;
		}
		if ( ! @getimagesize( $files['tmp_name'][ $i ] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a non-image simply fails.
			continue;
		}

		// media_handle_upload reads $_FILES[$key] as a single file, so present
		// this one entry on its own.
		$_FILES['bhela_review_photo'] = array(
			'name'     => $files['name'][ $i ],
			'type'     => $check['type'],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);
		$att_id = media_handle_upload( 'bhela_review_photo', $review_id, array(), array(
			'test_form' => false,
			'mimes'     => $mimes,
		) );
		unset( $_FILES['bhela_review_photo'] );

		if ( ! is_wp_error( $att_id ) && $att_id ) {
			$stored[] = (int) $att_id;
		}
	}

	if ( $stored ) {
		update_post_meta( $review_id, '_bhela_review_photos', $stored );
		set_post_thumbnail( $review_id, $stored[0] );
	}
	return count( $stored );
}

/** Handle the guest's submitted review. */
function bhela_bm_review_submit() {
	$booking_id = (int) ( $_POST['booking_id'] ?? 0 );
	$token      = isset( $_POST['key'] ) ? (string) wp_unslash( $_POST['key'] ) : '';

	// Honeypot: bots fill every field. Real guests never see this one.
	if ( ! empty( $_POST['bhela_bm_hp'] ) ) {
		wp_die( esc_html__( 'Sorry, that could not be accepted.', 'bhela-booking' ), 400 );
	}

	// Per-IP throttle — this endpoint writes posts and accepts files.
	$ip   = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key  = 'bhela_bm_review_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		wp_die( esc_html__( 'Too many attempts — please try again later.', 'bhela-booking' ), 429 );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	check_admin_referer( 'bhela_bm_review_submit' );

	// The nonce proves the form was ours; the token proves it is this guest's
	// booking. Both are required.
	$post = get_post( $booking_id );
	if ( ! $post || 'bhela_booking' !== $post->post_type
		|| ! hash_equals( bhela_bm_review_key( $booking_id ), $token ) ) {
		wp_die( esc_html__( 'This review link is not valid.', 'bhela-booking' ), 403 );
	}
	if ( 'completed' !== get_post_meta( $booking_id, '_bhela_status', true ) ) {
		wp_die( esc_html__( 'This trip is not finished yet, so it cannot be reviewed.', 'bhela-booking' ), 403 );
	}
	if ( bhela_bm_review_for_booking( $booking_id ) ) {
		bhela_bm_review_redirect( $booking_id, array( 'submitted' => 1 ) );
	}

	$rating   = min( 5, max( 1, (int) ( $_POST['rating'] ?? 5 ) ) );
	$text     = sanitize_textarea_field( wp_unslash( $_POST['review_text'] ?? '' ) );
	$name     = sanitize_text_field( wp_unslash( $_POST['guest_name'] ?? '' ) );
	$subtitle = sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? '' ) );

	if ( '' === trim( $text ) ) {
		bhela_bm_review_redirect( $booking_id, array( 'review_error' => 'empty' ) );
	}
	if ( '' === trim( $name ) ) {
		$name = get_the_title( $booking_id );
	}

	$review_id = wp_insert_post( array(
		'post_type'    => 'bhela_review',
		'post_title'   => $name,
		'post_content' => $text,
		'post_status'  => 'pending', // never visible until an admin publishes
	), true );

	if ( is_wp_error( $review_id ) || ! $review_id ) {
		bhela_bm_review_redirect( $booking_id, array( 'review_error' => 'failed' ) );
	}

	update_post_meta( $review_id, '_bhela_rating', $rating );
	update_post_meta( $review_id, '_bhela_subtitle', $subtitle );
	update_post_meta( $review_id, '_bhela_booking_id', $booking_id );
	update_post_meta( $review_id, '_bhela_review_source', 'guest' );

	$photos = bhela_bm_review_handle_photos( $review_id );

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'booking', sprintf(
			'Guest review received for %s — %d★, %d photo(s). Awaiting approval.',
			get_post_meta( $booking_id, '_bhela_invoice_no', true ), $rating, $photos
		) );
	}
	bhela_bm_review_notify_owner( $review_id, $booking_id, $rating, $photos );

	bhela_bm_review_redirect( $booking_id, array( 'submitted' => 1 ) );
}
add_action( 'admin_post_nopriv_bhela_bm_review_submit', 'bhela_bm_review_submit' );
add_action( 'admin_post_bhela_bm_review_submit', 'bhela_bm_review_submit' );

/** Tell the owner a review is waiting — pending ones are easy to miss. */
function bhela_bm_review_notify_owner( $review_id, $booking_id, $rating, $photos ) {
	$s = bhela_bm_get_settings();
	if ( empty( $s['email_enabled'] ) ) {
		return;
	}
	$to = $s['notify_email'] ? $s['notify_email'] : $s['email'];
	if ( ! $to || ! is_email( $to ) ) {
		return;
	}
	$invoice = get_post_meta( $booking_id, '_bhela_invoice_no', true );
	$body    = sprintf(
		"A guest has left a review and it is waiting for your approval.\n\nBooking: %s\nGuest: %s\nRating: %d/5\nPhotos: %d\n\nRead and publish it here:\n%s\n\nIt stays hidden on the website until you publish it.",
		$invoice,
		get_the_title( $review_id ),
		$rating,
		$photos,
		admin_url( 'post.php?post=' . (int) $review_id . '&action=edit' )
	);
	$sent = wp_mail( $to, sprintf( '⭐ New guest review awaiting approval — %s', $invoice ), $body );
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( $sent ? 'email' : 'error',
			sprintf( 'Review-awaiting-approval email %s — %s', $sent ? 'sent' : 'failed', $to ), $sent );
	}
}
