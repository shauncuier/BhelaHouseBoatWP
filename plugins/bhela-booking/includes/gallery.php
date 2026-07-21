<?php
/**
 * Photo gallery: CPT + category taxonomy + one-click import + shortcode.
 *
 * The featured image IS the photo, so the owner gets WordPress's own media
 * picker and srcset for free — no custom media JS anywhere in this plugin.
 * The post title is the caption and the native "Order" field sets the order.
 *
 * Lives in the plugin (not the theme) so the photos survive a theme switch,
 * matching where reviews and trips already live.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- CPT + taxonomy ---------- */

function bhela_bm_register_gallery_cpt() {
	register_post_type( 'bhela_gallery', array(
		'labels' => array(
			'name'          => 'Gallery',
			'singular_name' => 'Photo',
			'menu_name'     => '🖼️ Gallery',
			'add_new'       => 'Add Photo',
			'add_new_item'  => 'Add New Photo',
			'edit_item'     => 'Edit Photo',
			'all_items'     => '🖼️ Gallery',
			'not_found'     => 'No photos added yet.',
		),
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=bhela_booking',
		'rewrite'            => false,
		'capability_type'    => 'post',
		'has_archive'        => false,
		// No 'editor' on purpose: the owner gets the classic screen with just
		// Title, Featured Image, Order and the category checkboxes.
		'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
	) );

	// Hierarchical purely for the UI: it renders checkboxes instead of the
	// free-text tag box, which would invite duplicate Bangla terms from typos.
	register_taxonomy( 'bhela_gallery_cat', 'bhela_gallery', array(
		'labels' => array(
			'name'          => 'ক্যাটাগরি',
			'singular_name' => 'ক্যাটাগরি',
			'add_new_item'  => 'নতুন ক্যাটাগরি',
			'all_items'     => 'সব ক্যাটাগরি',
		),
		'hierarchical'      => true,
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'rewrite'           => false,
	) );
}
add_action( 'init', 'bhela_bm_register_gallery_cpt' );

/** Seed the starter categories once (cheap — no file I/O). */
function bhela_bm_seed_gallery_terms() {
	if ( get_option( 'bhela_bm_gallery_terms_seeded' ) ) {
		return;
	}
	foreach ( array( 'কেবিন', 'খাবার', 'হাওর', 'রুফটপ' ) as $term ) {
		if ( ! term_exists( $term, 'bhela_gallery_cat' ) ) {
			wp_insert_term( $term, 'bhela_gallery_cat' );
		}
	}
	update_option( 'bhela_bm_gallery_terms_seeded', 1 );
}
add_action( 'admin_init', 'bhela_bm_seed_gallery_terms' );

/* ---------- Admin list columns ---------- */

function bhela_bm_gallery_columns( $columns ) {
	return array(
		'cb'        => $columns['cb'],
		'thumb'     => 'ছবি',
		'title'     => 'ক্যাপশন',
		'taxonomy-bhela_gallery_cat' => 'ক্যাটাগরি',
		'order'     => 'ক্রম',
		'date'      => $columns['date'],
	);
}
add_filter( 'manage_bhela_gallery_posts_columns', 'bhela_bm_gallery_columns' );

function bhela_bm_gallery_column_content( $column, $post_id ) {
	if ( 'thumb' === $column ) {
		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( 80, 80 ), array( 'style' => 'width:80px;height:80px;object-fit:cover;border-radius:6px;' ) );
		} else {
			echo '<span style="color:#b32d2e">⚠️ ছবি নেই</span>';
		}
	} elseif ( 'order' === $column ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}
add_action( 'manage_bhela_gallery_posts_custom_column', 'bhela_bm_gallery_column_content', 10, 2 );

/** Sort the admin list the same way the front end does. */
function bhela_bm_gallery_admin_order( $query ) {
	global $pagenow;
	if ( is_admin() && 'edit.php' === $pagenow && $query->is_main_query()
		&& 'bhela_gallery' === ( $_GET['post_type'] ?? '' ) && ! isset( $_GET['orderby'] ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_gallery_admin_order' );

/* ---------- Data helper ---------- */

/**
 * Published photos, normalised for rendering. Kept free of any theme
 * dependency so the theme can consume it (or not) without coupling.
 */
function bhela_bm_get_gallery( $limit = -1 ) {
	$posts = get_posts( array(
		'post_type'      => 'bhela_gallery',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	) );

	$out = array();
	foreach ( $posts as $p ) {
		$thumb_id = get_post_thumbnail_id( $p->ID );
		if ( ! $thumb_id ) {
			continue; // A photo without an image is not a photo.
		}
		$full = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( ! $full ) {
			continue;
		}
		$alt  = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
		$cats = array();
		foreach ( (array) get_the_terms( $p->ID, 'bhela_gallery_cat' ) as $term ) {
			if ( $term instanceof WP_Term ) {
				$cats[] = $term->slug;
			}
		}
		$out[] = array(
			'id'      => $p->ID,
			'full'    => $full,
			// medium_large (768w) is a SOFT crop, so the natural aspect ratio
			// survives — that is what makes the masonry columns work.
			'thumb'   => wp_get_attachment_image( $thumb_id, 'medium_large', false, array(
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => $alt ? $alt : $p->post_title,
			) ),
			'caption' => $p->post_title,
			'cats'    => $cats,
		);
	}
	return $out;
}

/** Categories actually used by published photos, in seeded order. */
function bhela_bm_gallery_terms_in_use() {
	$terms = get_terms( array(
		'taxonomy'   => 'bhela_gallery_cat',
		'hide_empty' => true,
		'orderby'    => 'term_id', // Preserves seed order; Bangla alphabetical is arbitrary.
		'order'      => 'ASC',
	) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/* ---------- Shortcode ---------- */

function bhela_bm_gallery_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'limit' => -1 ), $atts, 'bhela_gallery' );
	$items = bhela_bm_get_gallery( (int) $atts['limit'] );
	if ( ! $items ) {
		return ''; // Empty string lets the theme fall back to its bundled images.
	}
	$terms = bhela_bm_gallery_terms_in_use();

	ob_start();

	// Tabs only earn their space when there is something to filter between.
	if ( count( $terms ) >= 2 ) : ?>
		<div class="bhela-gallery-filter" role="group" aria-label="<?php esc_attr_e( 'ক্যাটাগরি ফিল্টার', 'bhela-booking' ); ?>">
			<button type="button" class="bhela-gallery-filter__btn is-active" data-filter="*" aria-pressed="true"><?php esc_html_e( 'সব', 'bhela-booking' ); ?></button>
			<?php foreach ( $terms as $term ) : ?>
				<button type="button" class="bhela-gallery-filter__btn" data-filter="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $term->name ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="bhela-gallery">
		<?php foreach ( $items as $item ) : ?>
			<a class="bhela-gallery__item"
				href="<?php echo esc_url( $item['full'] ); ?>"
				data-cats="<?php echo esc_attr( implode( ' ', $item['cats'] ) ); ?>"
				data-caption="<?php echo esc_attr( $item['caption'] ); ?>">
				<?php echo $item['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?>
				<?php if ( $item['caption'] ) : ?>
					<span class="bhela-gallery__caption"><?php echo esc_html( $item['caption'] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'bhela_gallery', 'bhela_bm_gallery_shortcode' );

/* ---------- One-time import of the bundled theme photos ---------- */

/** The theme images offered for import, as relative paths. */
function bhela_bm_gallery_seed_files() {
	$base  = get_template_directory() . '/assets/images';
	$found = array();
	foreach ( array( 'hero', 'boat', 'cabins', 'spots', 'food' ) as $folder ) {
		foreach ( (array) glob( $base . '/' . $folder . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE ) as $file ) {
			$found[] = $folder . '/' . basename( $file );
		}
	}
	return $found;
}

/**
 * Copy a bundled theme image into the media library and return its attachment
 * ID. Idempotent by seed key, so re-running never duplicates an attachment.
 *
 * Ported from the theme's bhela_set_seed_thumbnail() rather than calling it:
 * a plugin should not depend on a theme function, and that helper returns
 * nothing and skips when a thumbnail already exists.
 */
function bhela_bm_import_theme_image( $rel ) {
	// Reuse a previously imported attachment if we already have one.
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_bhela_gallery_seed_key',
		'meta_value'     => $rel,
	) );
	if ( $existing ) {
		return (int) $existing[0];
	}

	$src = get_template_directory() . '/assets/images/' . $rel;
	if ( ! file_exists( $src ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}
	$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
	$dest     = trailingslashit( $uploads['path'] ) . $filename;
	if ( ! @copy( $src, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- failure is handled.
		return 0;
	}

	$filetype = wp_check_filetype( $dest, null );
	$att_id   = wp_insert_attachment( array(
		'post_mime_type' => $filetype['type'],
		'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $dest );
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return 0;
	}
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $dest ) );
	update_post_meta( $att_id, '_bhela_gallery_seed_key', $rel );
	return (int) $att_id;
}

/** Map a source folder to one of the seeded categories. */
function bhela_bm_gallery_folder_term( $rel ) {
	$folder = strtok( $rel, '/' );
	$map    = array(
		'cabins' => 'কেবিন',
		'food'   => 'খাবার',
		'spots'  => 'হাওর',
		'hero'   => 'হাওর',
		'boat'   => 'রুফটপ',
	);
	return $map[ $folder ] ?? '';
}

/**
 * A readable starting caption for an imported photo, e.g. "কেবিন ২".
 * Without this the admin list is 17 rows of "(no title)" and the images
 * carry no alt text — the owner is expected to refine these.
 */
function bhela_bm_gallery_default_title( $rel, $n ) {
	$folder = strtok( $rel, '/' );
	$labels = array(
		'cabins' => 'কেবিন',
		'food'   => 'খাবার',
		'spots'  => 'হাওরের দৃশ্য',
		'hero'   => 'টাঙ্গুয়ার হাওর',
		'boat'   => 'হাউসবোট',
	);
	$label = $labels[ $folder ] ?? 'ভেলা';
	return $n > 1 ? $label . ' ' . number_format_i18n( $n ) : $label;
}

/**
 * Import handler. Idempotent by seed key on the gallery post, so pressing the
 * button twice — or reactivating the plugin — creates nothing new.
 */
function bhela_bm_gallery_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'অনুমতি নেই।', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_gallery_import' );

	bhela_bm_seed_gallery_terms();

	$added = 0;
	$seen  = array(); // per-folder counter, so captions read "কেবিন ১, কেবিন ২…"
	foreach ( bhela_bm_gallery_seed_files() as $i => $rel ) {
		$folder          = strtok( $rel, '/' );
		$seen[ $folder ] = ( $seen[ $folder ] ?? 0 ) + 1;
		$dupe = get_posts( array(
			'post_type'      => 'bhela_gallery',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_bhela_gallery_seed_key',
			'meta_value'     => $rel,
		) );
		if ( $dupe ) {
			continue;
		}
		$att_id = bhela_bm_import_theme_image( $rel );
		if ( ! $att_id ) {
			continue;
		}
		$post_id = wp_insert_post( array(
			'post_type'   => 'bhela_gallery',
			'post_status' => 'publish',
			'post_title'  => bhela_bm_gallery_default_title( $rel, $seen[ $folder ] ),
			'menu_order'  => $i,
		) );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}
		set_post_thumbnail( $post_id, $att_id );
		update_post_meta( $post_id, '_bhela_gallery_seed_key', $rel );

		$term_name = bhela_bm_gallery_folder_term( $rel );
		if ( $term_name ) {
			$term = get_term_by( 'name', $term_name, 'bhela_gallery_cat' );
			if ( $term ) {
				wp_set_object_terms( $post_id, (int) $term->term_id, 'bhela_gallery_cat' );
			}
		}
		$added++;
	}

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'gallery', sprintf( 'থিমের ছবি ইমপোর্ট — %d টি নতুন ছবি যোগ হয়েছে', $added ) );
	}
	wp_safe_redirect( add_query_arg(
		array( 'post_type' => 'bhela_gallery', 'bhela_imported' => $added ),
		admin_url( 'edit.php' )
	) );
	exit;
}
add_action( 'admin_post_bhela_bm_gallery_import', 'bhela_bm_gallery_import' );

/* ---------- Bulk upload (native media picker, multi-select) ---------- */

/**
 * Adding photos one row at a time is the slow path. This page opens the
 * WordPress media frame in multi-select mode — the owner drags a whole trip's
 * worth of photos in, picks them all, and each becomes its own gallery post
 * with the chosen category, in the picked order, after the current last photo.
 */
function bhela_bm_gallery_bulk_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Bulk Photo Upload', 'bhela-booking' ),
		__( '🖼️ Bulk Upload', 'bhela-booking' ),
		'manage_options',
		'bhela-bm-gallery-bulk',
		'bhela_bm_gallery_bulk_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_gallery_bulk_menu' );

function bhela_bm_gallery_bulk_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// The media frame lives on this screen, so it must have the media JS.
	wp_enqueue_media();

	$terms   = get_terms( array( 'taxonomy' => 'bhela_gallery_cat', 'hide_empty' => false, 'orderby' => 'term_id' ) );
	$terms   = is_wp_error( $terms ) ? array() : $terms;
	$added   = isset( $_GET['bhela_bulk_added'] ) ? (int) $_GET['bhela_bulk_added'] : -1;
	$gallery = add_query_arg( array( 'post_type' => 'bhela_gallery' ), admin_url( 'edit.php' ) );
	?>
	<div class="wrap">
		<h1>🖼️ <?php esc_html_e( 'ছবি একসাথে যোগ করুন', 'bhela-booking' ); ?></h1>
		<p><?php esc_html_e( 'একসাথে অনেকগুলো ছবি বাছাই করুন — প্রতিটি ছবি আলাদা করে গ্যালারিতে যোগ হবে। ক্যাপশন ও ক্রম পরে গ্যালারি থেকে বদলানো যাবে।', 'bhela-booking' ); ?></p>

		<?php if ( $added >= 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( sprintf( __( '✅ %s টি ছবি গ্যালারিতে যোগ হয়েছে।', 'bhela-booking' ), function_exists( 'bhela_bm_bn_num' ) ? bhela_bm_bn_num( $added ) : $added ) ); ?>
				&nbsp;<a href="<?php echo esc_url( $gallery ); ?>"><?php esc_html_e( 'গ্যালারি দেখুন →', 'bhela-booking' ); ?></a>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bhela-bulk-form">
			<input type="hidden" name="action" value="bhela_bm_gallery_bulk">
			<?php wp_nonce_field( 'bhela_bm_gallery_bulk' ); ?>
			<input type="hidden" name="attachment_ids" id="bhela-bulk-ids" value="">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'ক্যাটাগরি', 'bhela-booking' ); ?></th>
					<td>
						<?php if ( $terms ) : ?>
							<?php foreach ( $terms as $term ) : ?>
								<label style="margin-right:16px;display:inline-block"><input type="checkbox" name="bulk_cats[]" value="<?php echo esc_attr( $term->term_id ); ?>"> <?php echo esc_html( $term->name ); ?></label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'বাছাই করা সব ছবিতে এই ক্যাটাগরি বসবে (ইচ্ছা হলে খালি রাখুন)।', 'bhela-booking' ); ?></p>
						<?php else : ?>
							<em><?php esc_html_e( 'এখনো কোনো ক্যাটাগরি নেই।', 'bhela-booking' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-secondary" id="bhela-bulk-pick"><?php esc_html_e( '📁 ছবি বাছাই করুন', 'bhela-booking' ); ?></button>
				<span id="bhela-bulk-count" style="margin-left:10px;color:#646970"></span>
			</p>

			<div id="bhela-bulk-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0"></div>

			<p>
				<button type="submit" class="button button-primary" id="bhela-bulk-submit" disabled><?php esc_html_e( 'গ্যালারিতে যোগ করুন', 'bhela-booking' ); ?></button>
			</p>
		</form>

		<script>
		( function () {
			var pick    = document.getElementById( 'bhela-bulk-pick' );
			var idsEl   = document.getElementById( 'bhela-bulk-ids' );
			var countEl = document.getElementById( 'bhela-bulk-count' );
			var preview = document.getElementById( 'bhela-bulk-preview' );
			var submit  = document.getElementById( 'bhela-bulk-submit' );
			var frame;

			pick.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! window.wp || ! wp.media ) { return; }
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title:    <?php echo wp_json_encode( __( 'গ্যালারির জন্য ছবি বাছাই করুন', 'bhela-booking' ) ); ?>,
					button:   { text: <?php echo wp_json_encode( __( 'এইগুলো ব্যবহার করুন', 'bhela-booking' ) ); ?> },
					library:  { type: 'image' },
					multiple: 'add'
				} );
				frame.on( 'select', function () {
					var items = frame.state().get( 'selection' ).toJSON();
					var ids   = [];
					preview.innerHTML = '';
					items.forEach( function ( a ) {
						ids.push( a.id );
						var src = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
						var img = document.createElement( 'img' );
						img.src = src;
						img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #dcdcde';
						preview.appendChild( img );
					} );
					idsEl.value    = ids.join( ',' );
					countEl.textContent = ids.length ? ( <?php echo wp_json_encode( __( 'বাছাই হয়েছে: ', 'bhela-booking' ) ); ?> + ids.length ) : '';
					submit.disabled = ids.length === 0;
				} );
				frame.open();
			} );
		} )();
		</script>
	</div>
	<?php
}

/** Create one gallery post per picked attachment, in order, after the last. */
function bhela_bm_gallery_bulk_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'অনুমতি নেই।', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_gallery_bulk' );

	$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $_POST['attachment_ids'] ?? '' ) ) ) );
	$cats = array_filter( array_map( 'absint', (array) ( $_POST['bulk_cats'] ?? array() ) ) );

	// Continue the order after the current last photo, so bulk adds append.
	$last  = get_posts( array(
		'post_type'      => 'bhela_gallery',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'orderby'        => 'menu_order',
		'order'          => 'DESC',
		'fields'         => 'ids',
	) );
	$order = $last ? (int) get_post_field( 'menu_order', $last[0] ) : 0;

	$added = 0;
	foreach ( $ids as $att_id ) {
		if ( 'attachment' !== get_post_type( $att_id ) || 0 !== strpos( (string) get_post_mime_type( $att_id ), 'image/' ) ) {
			continue; // only real image attachments
		}
		$order++;
		$title   = get_the_title( $att_id );
		$post_id = wp_insert_post( array(
			'post_type'   => 'bhela_gallery',
			'post_status' => 'publish',
			'post_title'  => $title ? $title : __( 'ভেলা', 'bhela-booking' ),
			'menu_order'  => $order,
		) );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}
		set_post_thumbnail( $post_id, $att_id );
		if ( $cats ) {
			wp_set_object_terms( $post_id, $cats, 'bhela_gallery_cat' );
		}
		$added++;
	}

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'gallery', sprintf(
			'বাল্ক আপলোড — %s টি ছবি গ্যালারিতে যোগ হয়েছে।',
			function_exists( 'bhela_bm_bn_num' ) ? bhela_bm_bn_num( $added ) : $added
		) );
	}
	wp_safe_redirect( add_query_arg(
		array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-gallery-bulk', 'bhela_bulk_added' => $added ),
		admin_url( 'edit.php' )
	) );
	exit;
}
add_action( 'admin_post_bhela_bm_gallery_bulk', 'bhela_bm_gallery_bulk_handler' );

/** Offer the import on the gallery list while it is still empty. */
function bhela_bm_gallery_import_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-bhela_gallery' !== $screen->id ) {
		return;
	}

	if ( isset( $_GET['bhela_imported'] ) ) {
		$n = (int) $_GET['bhela_imported'];
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			$n
				? esc_html( sprintf( '✅ %d টি ছবি ইমপোর্ট হয়েছে।', $n ) )
				: esc_html__( 'সব ছবি আগে থেকেই ইমপোর্ট করা আছে — নতুন কিছু যোগ হয়নি।', 'bhela-booking' )
		);
	}

	// Always surface the fast path: bulk-add many photos at once.
	printf(
		'<div class="notice notice-info"><p>%s <a class="button button-primary" href="%s" style="margin-left:6px">%s</a></p></div>',
		esc_html__( 'একসাথে অনেকগুলো ছবি যোগ করতে চান?', 'bhela-booking' ),
		esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-gallery-bulk' ), admin_url( 'edit.php' ) ) ),
		esc_html__( '🖼️ ছবি একসাথে যোগ', 'bhela-booking' )
	);

	$count = wp_count_posts( 'bhela_gallery' );
	if ( ( (int) $count->publish + (int) $count->draft ) > 0 ) {
		return;
	}
	$files = count( bhela_bm_gallery_seed_files() );
	if ( ! $files ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p><strong><?php esc_html_e( 'গ্যালারিতে এখনো কোনো ছবি নেই।', 'bhela-booking' ); ?></strong>
			<?php echo esc_html( sprintf( 'থিমের সাথে দেওয়া %d টি ছবি এক ক্লিকে যোগ করে নিতে পারেন — এরপর ক্যাপশন, ক্যাটাগরি ও ক্রম নিজের মতো বদলাতে পারবেন।', $files ) ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
			<input type="hidden" name="action" value="bhela_bm_gallery_import">
			<?php wp_nonce_field( 'bhela_bm_gallery_import' ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( '🖼️ ছবিগুলো ইমপোর্ট করুন', 'bhela-booking' ); ?></button>
		</form>
	</div>
	<?php
}
add_action( 'admin_notices', 'bhela_bm_gallery_import_notice' );
