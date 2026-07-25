<?php
/**
 * Trip Spots: a self-serve CPT for the trip route/map page.
 *
 * Each spot = a photo (featured image) + English title + Bangla name + a one
 * line description + a Type (Included in the package / Optional — visited at the
 * guest's own cost) + drag order. Mirrors the Gallery/Reviews CPT pattern so the
 * owner manages everything in wp-admin with no code.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- CPT ---------- */

function bhela_bm_register_spot_cpt() {
	register_post_type( 'bhela_spot', array(
		'labels' => array(
			'name'         => 'Spots',
			'singular_name'=> 'Spot',
			'menu_name'    => '🗺️ Spots',
			'add_new'      => 'Add Spot',
			'add_new_item' => 'Add New Spot',
			'edit_item'    => 'Edit Spot',
			'all_items'    => '🗺️ Spots',
			'not_found'    => 'No spots yet.',
		),
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=bhela_booking',
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		// Title + Featured Image (the photo) + Order (page-attributes). No editor:
		// the short description lives in a tidy meta box below.
		'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
	) );
}
add_action( 'init', 'bhela_bm_register_spot_cpt' );

/** The two spot types → label + colour. */
function bhela_bm_spot_types() {
	return array(
		'included' => array( 'label' => 'প্যাকেজে অন্তর্ভুক্ত', 'short' => 'Included', 'color' => '#1a7f37' ),
		'optional' => array( 'label' => 'ঐচ্ছিক — নিজ খরচে', 'short' => 'Optional', 'color' => '#b45309' ),
	);
}

/* ---------- Meta box (Bangla name, description, type) ---------- */

function bhela_bm_spot_metabox() {
	add_meta_box( 'bhela_spot_details', 'Spot Details', 'bhela_bm_spot_metabox_cb', 'bhela_spot', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_spot_metabox' );

function bhela_bm_spot_metabox_cb( $post ) {
	wp_nonce_field( 'bhela_bm_spot_save', 'bhela_bm_spot_nonce' );
	$bn   = get_post_meta( $post->ID, '_bhela_spot_bn', true );
	$desc = get_post_meta( $post->ID, '_bhela_spot_desc', true );
	$type = get_post_meta( $post->ID, '_bhela_spot_type', true ) ?: 'included';
	?>
	<p><label><strong><?php esc_html_e( 'বাংলা নাম', 'bhela-booking' ); ?></strong><br>
		<input type="text" name="bhela_spot_bn" value="<?php echo esc_attr( $bn ); ?>" style="width:100%" placeholder="যেমন টাঙ্গুয়ার হাওর"></label></p>
	<p><label><strong><?php esc_html_e( 'বিবরণ (এক লাইন)', 'bhela-booking' ); ?></strong><br>
		<textarea name="bhela_spot_desc" rows="2" style="width:100%" placeholder="স্পট সম্পর্কে সংক্ষেপে..."><?php echo esc_textarea( $desc ); ?></textarea></label></p>
	<p><strong><?php esc_html_e( 'ধরন', 'bhela-booking' ); ?></strong><br>
		<label style="margin-right:16px"><input type="radio" name="bhela_spot_type" value="included" <?php checked( $type, 'included' ); ?>> ✅ <?php esc_html_e( 'প্যাকেজে অন্তর্ভুক্ত (আমরা নিয়ে যাই)', 'bhela-booking' ); ?></label>
		<label><input type="radio" name="bhela_spot_type" value="optional" <?php checked( $type, 'optional' ); ?>> 💠 <?php esc_html_e( 'ঐচ্ছিক — অতিথি নিজ খরচে যেতে পারেন', 'bhela-booking' ); ?></label>
	</p>
	<p class="description"><?php esc_html_e( 'ডানপাশের Featured Image = স্পটের ছবি · Page Attributes → Order = ক্রম (ছোট আগে)।', 'bhela-booking' ); ?></p>
	<?php
}

function bhela_bm_spot_save( $post_id ) {
	if ( ! isset( $_POST['bhela_bm_spot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_spot_nonce'] ) ), 'bhela_bm_spot_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_bhela_spot_bn', sanitize_text_field( wp_unslash( $_POST['bhela_spot_bn'] ?? '' ) ) );
	update_post_meta( $post_id, '_bhela_spot_desc', sanitize_textarea_field( wp_unslash( $_POST['bhela_spot_desc'] ?? '' ) ) );
	$type = ( 'optional' === ( $_POST['bhela_spot_type'] ?? '' ) ) ? 'optional' : 'included';
	update_post_meta( $post_id, '_bhela_spot_type', $type );
}
add_action( 'save_post_bhela_spot', 'bhela_bm_spot_save' );

/* ---------- Admin list columns ---------- */

function bhela_bm_spot_columns( $columns ) {
	return array(
		'cb'    => $columns['cb'],
		'thumb' => 'ছবি',
		'title' => 'Spot (EN)',
		'spot_bn'   => 'বাংলা নাম',
		'spot_type' => 'ধরন',
		'order' => 'ক্রম',
	);
}
add_filter( 'manage_bhela_spot_posts_columns', 'bhela_bm_spot_columns' );

function bhela_bm_spot_column_content( $column, $post_id ) {
	if ( 'thumb' === $column ) {
		echo has_post_thumbnail( $post_id )
			? get_the_post_thumbnail( $post_id, array( 70, 52 ), array( 'style' => 'width:70px;height:52px;object-fit:cover;border-radius:6px;' ) )
			: '<span style="color:#b32d2e">⚠️ নেই</span>';
	} elseif ( 'spot_bn' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_bhela_spot_bn', true ) ?: '—' );
	} elseif ( 'spot_type' === $column ) {
		$t     = get_post_meta( $post_id, '_bhela_spot_type', true ) ?: 'included';
		$types = bhela_bm_spot_types();
		$m     = $types[ $t ] ?? $types['included'];
		printf( '<span style="display:inline-block;padding:2px 9px;border-radius:11px;font-size:11px;font-weight:600;color:#fff;background:%s">%s</span>',
			esc_attr( $m['color'] ), esc_html( $m['label'] ) );
	} elseif ( 'order' === $column ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}
add_action( 'manage_bhela_spot_posts_custom_column', 'bhela_bm_spot_column_content', 10, 2 );

function bhela_bm_spot_admin_order( $query ) {
	global $pagenow;
	if ( is_admin() && 'edit.php' === $pagenow && $query->is_main_query()
		&& 'bhela_spot' === ( $_GET['post_type'] ?? '' ) && ! isset( $_GET['orderby'] ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_spot_admin_order' );

/* ---------- Data helper (for the theme) ---------- */

/**
 * Published spots in order, normalised for rendering. Optional $type filter
 * ('included' | 'optional'). Falls back to a bundled theme image per spot when
 * no featured image is set yet.
 */
function bhela_bm_get_spots( $type = null ) {
	$posts = get_posts( array(
		'post_type'      => 'bhela_spot',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
	) );
	$out = array();
	foreach ( $posts as $p ) {
		$t = get_post_meta( $p->ID, '_bhela_spot_type', true ) ?: 'included';
		if ( $type && $t !== $type ) {
			continue;
		}
		$img = get_the_post_thumbnail_url( $p->ID, 'bhela-card' );
		if ( ! $img ) {
			$fb  = get_post_meta( $p->ID, '_bhela_spot_fallback', true );
			$img = $fb ? get_template_directory_uri() . '/assets/images/' . ltrim( $fb, '/' ) : '';
		}
		$out[] = array(
			'id'   => $p->ID,
			'en'   => $p->post_title,
			'bn'   => get_post_meta( $p->ID, '_bhela_spot_bn', true ),
			'desc' => get_post_meta( $p->ID, '_bhela_spot_desc', true ),
			'type' => $t,
			'img'  => $img,
		);
	}
	return $out;
}

/* ---------- One-time seed ---------- */

/** Default spots (researched Tanguar Haor route + the current 9). */
function bhela_bm_spot_seed_data() {
	return array(
		// Included — the boat goes there as part of the package.
		array( 'en' => 'Anwarpur Ghat', 'bn' => 'আনোয়ারপুর ঘাট', 'desc' => 'যাত্রা শুরু ও শেষ পয়েন্ট — Tahirpur, Sunamganj।', 'type' => 'included', 'file' => 'hero/hero-haor.jpg' ),
		array( 'en' => 'Tanguar Haor', 'bn' => 'টাঙ্গুয়ার হাওর', 'desc' => 'অথৈ জলরাজ্য — রামসার সাইট, ট্রিপের মূল আকর্ষণ।', 'type' => 'included', 'file' => 'spots/spot-1.jpg' ),
		array( 'en' => 'Hijol-Karach Submerged Forest', 'bn' => 'হিজল-করচ জলাবন', 'desc' => 'পানিতে ডুবে থাকা বন — নৌকা থেকে ঘুরে দেখা।', 'type' => 'included', 'file' => 'spots/spot-2.jpg' ),
		array( 'en' => 'Watch Tower', 'bn' => 'ওয়াচ টাওয়ার', 'desc' => 'উপর থেকে হাওরের ৩৬০° প্যানোরামা।', 'type' => 'included', 'file' => 'spots/spot-5.jpg' ),
		array( 'en' => 'Khurchar Haor', 'bn' => 'খরচার হাওর', 'desc' => 'সূর্যাস্তের অপূর্ব দৃশ্য, শান্ত জলরাশি।', 'type' => 'included', 'file' => 'spots/spot-7.jpg' ),
		// Optional — land detours the guest may visit at their own cost.
		array( 'en' => 'Tekerghat', 'bn' => 'টেকেরঘাট', 'desc' => 'পরিত্যক্ত চুনাপাথর খনি এলাকা।', 'type' => 'optional', 'file' => 'spots/spot-3.jpg' ),
		array( 'en' => 'Niladri Lake (Shaheed Siraj Lake)', 'bn' => 'নীলাদ্রি লেক', 'desc' => 'নীল জলের হ্রদ — গোসল ও ছবির স্পট।', 'type' => 'optional', 'file' => 'spots/spot-2.jpg' ),
		array( 'en' => 'Jadukata River', 'bn' => 'যাদুকাটা নদী', 'desc' => 'স্বচ্ছ জলের পাহাড়ি নদী, রূপালি বালুচর।', 'type' => 'optional', 'file' => 'spots/spot-3.jpg' ),
		array( 'en' => 'Shimul Bagan', 'bn' => 'শিমুল বাগান', 'desc' => 'এশিয়ার অন্যতম বড় শিমুল বাগান (মৌসুমি)।', 'type' => 'optional', 'file' => 'spots/spot-6.jpg' ),
		array( 'en' => 'Barikka Tila', 'bn' => 'বারিক্কা টিলা', 'desc' => 'সীমান্তের টিলা থেকে মেঘালয়ের পাহাড়ি ভিউ।', 'type' => 'optional', 'file' => 'spots/spot-4.jpg' ),
		array( 'en' => 'Zero Point (Meghalaya Border)', 'bn' => 'জিরো পয়েন্ট (মেঘালয় সীমান্ত)', 'desc' => 'সীমান্তঘেঁষা পাহাড়ি দৃশ্য।', 'type' => 'optional', 'file' => 'spots/spot-4.jpg' ),
		array( 'en' => 'Lakma Chhara', 'bn' => 'লাকমা ছড়া', 'desc' => 'পাথুরে ঝর্ণাধারা (মৌসুমি)।', 'type' => 'optional', 'file' => 'spots/spot-3.jpg' ),
	);
}

/** Seed the default spots once (no images imported — a bundled fallback is
 *  stored so the page looks complete; the owner uploads real photos later). */
function bhela_bm_seed_spots() {
	if ( get_option( 'bhela_bm_spots_seeded' ) || ! post_type_exists( 'bhela_spot' ) ) {
		return;
	}
	// Don't seed if the owner already added spots.
	$existing = get_posts( array( 'post_type' => 'bhela_spot', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		update_option( 'bhela_bm_spots_seeded', 1 );
		return;
	}
	$order = 0;
	foreach ( bhela_bm_spot_seed_data() as $s ) {
		$order += 10;
		$id = wp_insert_post( array(
			'post_type'   => 'bhela_spot',
			'post_status' => 'publish',
			'post_title'  => $s['en'],
			'menu_order'  => $order,
		) );
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		update_post_meta( $id, '_bhela_spot_bn', $s['bn'] );
		update_post_meta( $id, '_bhela_spot_desc', $s['desc'] );
		update_post_meta( $id, '_bhela_spot_type', $s['type'] );
		update_post_meta( $id, '_bhela_spot_fallback', $s['file'] );
	}
	update_option( 'bhela_bm_spots_seeded', 1 );
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'settings', 'ট্রিপ স্পট সিস্টেম চালু — ১২টি ডিফল্ট স্পট যোগ হয়েছে (Included/Optional আলাদা)।' );
	}
}
add_action( 'admin_init', 'bhela_bm_seed_spots' );
