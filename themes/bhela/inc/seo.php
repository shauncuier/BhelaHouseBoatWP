<?php
/**
 * Lightweight SEO layer — meta description, Open Graph/Twitter cards,
 * correct language attribute, archive canonicals, sitemap in robots.txt.
 *
 * Deliberately minimal: steps aside automatically if a full SEO plugin
 * (Yoast / Rank Math) is ever activated.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when a dedicated SEO plugin owns the head output. */
function bhela_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
}

/** The meta/OG description for the current request. */
function bhela_seo_description() {
	if ( is_front_page() ) {
		return 'টাঙ্গুয়ার হাওরের প্রিমিয়াম ফ্যামিলি হাউসবোট — ৬টি AC কেবিন, দেশি খাবার, ২ দিন ১ রাতের অল-ইনক্লুসিভ প্যাকেজ। Premium houseboat tours on Tanguar Haor, Sunamganj.';
	}
	if ( is_singular( 'post' ) ) {
		$excerpt = wp_strip_all_tags( get_the_excerpt() );
		return mb_strlen( $excerpt ) > 155 ? mb_substr( $excerpt, 0, 152 ) . '…' : $excerpt;
	}
	if ( is_home() ) {
		return 'হাওর জার্নাল — টাঙ্গুয়ার হাওর ভ্রমণের গাইড, টিপস আর ভেলার খবর। Travel guides and tips for Tanguar Haor houseboat trips.';
	}
	if ( is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->description ) ) {
			return wp_strip_all_tags( $term->description );
		}
		return $term ? sprintf( '%s — এই বিষয়ে %dটি লেখা। ভেলা হাউসবোটের হাওর জার্নাল।', $term->name, (int) $term->count ) : '';
	}
	if ( is_page() ) {
		$map = array(
			'cabins'   => '৬টি বড় ফ্যামিলি কেবিন — AC, Attached Washroom, Infinity Glass Window। জনপ্রতি রেট ও কেবিন ভাড়া দেখুন। Cabin rates for BHELA houseboat, Tanguar Haor.',
			'schedule' => 'ভেলা হাউসবোটের আপকামিং ট্রিপ সিডিউল ও কেবিন খালি আছে কিনা দেখুন। Weekday ট্রিপে ২০% পর্যন্ত ছাড়। Trip schedule and availability.',
			'food'     => '২ দিন ১ রাতে ৬ বেলা দেশি খাবার — হাওরের তাজা মাছ, দেশি মুরগি-হাঁস, ভর্তা, BBQ। BHELA houseboat food menu.',
			'gallery'  => 'ভেলা হাউসবোট আর টাঙ্গুয়ার হাওরের ছবি — কেবিন, রুফটপ, খাবার আর হাওরের রূপ। Photo gallery.',
			'faq'      => 'টাঙ্গুয়ার হাওর ও ভেলা হাউসবোট নিয়ে সব সাধারণ প্রশ্নের উত্তর — রেট, বুকিং, নিরাপত্তা, ক্যানসেলেশন। FAQ.',
			'book-now' => '২ মিনিটে ভেলা হাউসবোট বুক করুন — তারিখ দিন, সাথে সাথে রেট দেখুন। ৫০% অগ্রিমে বুকিং Confirmed। Book your Tanguar Haor houseboat trip online.',
			'policies' => 'ভেলা হাউসবোটের বুকিং, পেমেন্ট, রিফান্ড ও রিসিডিউল নীতিমালা। Booking, payment and refund policies.',
			'blog'     => 'হাওর জার্নাল — টাঙ্গুয়ার হাওর ভ্রমণের গাইড, টিপস আর ভেলার খবর।',
		);
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		$excerpt = wp_strip_all_tags( get_the_excerpt() );
		return $excerpt ? mb_substr( $excerpt, 0, 155 ) : get_bloginfo( 'description' );
	}
	return '';
}

/** The OG image for the current request. */
function bhela_seo_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( null, 'bhela-wide' );
		if ( $img ) {
			return $img;
		}
	}
	return bhela_img( 'hero', 'hero/hero-haor.jpg' );
}

/** Canonical URL for the current request (paged-aware). */
function bhela_seo_current_url() {
	global $wp;
	$url = home_url( add_query_arg( array(), $wp->request ) );
	return trailingslashit( $url );
}

/** Meta description + Open Graph + Twitter card. */
function bhela_seo_head() {
	if ( bhela_seo_plugin_active() ) {
		return;
	}
	$desc  = bhela_seo_description();
	$title = wp_get_document_title();
	$image = bhela_seo_image();
	$url   = is_singular() ? get_permalink() : bhela_seo_current_url();
	$type  = is_singular( 'post' ) ? 'article' : 'website';

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	echo '<meta property="og:locale" content="bn_BD">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	// Canonical for archives — WP core only outputs it on singular views.
	if ( ( is_home() && ! is_front_page() ) || is_category() || is_tag() ) {
		echo '<link rel="canonical" href="' . esc_url( bhela_seo_current_url() ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'bhela_seo_head', 1 );

/** Content is Bangla — say so to search engines and screen readers. */
function bhela_seo_language_attributes( $output ) {
	return 'lang="bn-BD"';
}
add_filter( 'language_attributes', 'bhela_seo_language_attributes' );

/** Point crawlers at the core sitemap (core already adds it when sitemaps are on). */
function bhela_seo_robots( $output ) {
	if ( false === stripos( $output, 'sitemap:' ) ) {
		$output .= "\nSitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
	}
	return $output;
}
add_filter( 'robots_txt', 'bhela_seo_robots' );

/* ---------- JSON-LD @graph ---------- */

/** Social profile URLs set in the Customizer (placeholder defaults excluded). */
function bhela_seo_social_profiles() {
	$out = array();
	foreach ( bhela_social_links() as $net ) {
		$out[] = esc_url_raw( $net['url'] );
	}
	return $out;
}

/** Average star rating + count from published guest reviews. */
function bhela_seo_review_stats() {
	$ids = get_posts( array(
		'post_type'      => 'bhela_review',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	if ( ! $ids ) {
		return null;
	}
	$sum = 0;
	foreach ( $ids as $id ) {
		$sum += (int) ( get_post_meta( $id, '_bhela_rating', true ) ?: 5 );
	}
	return array(
		'value' => round( $sum / count( $ids ), 1 ),
		'count' => count( $ids ),
	);
}

/** One connected JSON-LD graph: Organization → WebSite → WebPage (+ business / article / breadcrumb). */
function bhela_seo_schema() {
	if ( bhela_seo_plugin_active() ) {
		return;
	}
	$home     = home_url( '/' );
	$org_id   = $home . '#organization';
	$site_id  = $home . '#website';
	$logo     = get_template_directory_uri() . '/assets/images/logo.png';
	$hero     = bhela_img( 'hero', 'hero/hero-haor.jpg' );
	$page_url = is_singular() ? get_permalink() : bhela_seo_current_url();
	$page_id  = $page_url . '#webpage';
	$desc     = bhela_seo_description();
	$social   = bhela_seo_social_profiles();

	// Company.
	$org = array(
		'@type'         => 'Organization',
		'@id'           => $org_id,
		'name'          => 'BHELA – The Haor Exclusive',
		'alternateName' => 'ভেলা হাউসবোট',
		'url'           => $home,
		'logo'          => array( '@type' => 'ImageObject', '@id' => $home . '#logo', 'url' => $logo ),
		'image'         => array( '@id' => $home . '#logo' ),
		'email'         => bhela_contact( 'email' ),
		'telephone'     => bhela_contact( 'phone_1' ),
		'address'       => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => 'Anwarpur Ghat, Tahirpur',
			'addressLocality' => 'Tahirpur',
			'addressRegion'   => 'Sunamganj',
			'addressCountry'  => 'BD',
		),
		'contactPoint'  => array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'reservations',
			'telephone'         => bhela_contact( 'phone_1' ),
			'availableLanguage' => array( 'bn', 'en' ),
		),
	);
	if ( $social ) {
		$org['sameAs'] = $social;
	}

	// Site.
	$website = array(
		'@type'      => 'WebSite',
		'@id'        => $site_id,
		'url'        => $home,
		'name'       => 'BHELA – The Haor Exclusive',
		'publisher'  => array( '@id' => $org_id ),
		'inLanguage' => 'bn-BD',
	);

	// Current page.
	$webpage = array(
		'@type'      => is_home() || is_archive() ? 'CollectionPage' : 'WebPage',
		'@id'        => $page_id,
		'url'        => $page_url,
		'name'       => wp_get_document_title(),
		'isPartOf'   => array( '@id' => $site_id ),
		'inLanguage' => 'bn-BD',
	);
	if ( $desc ) {
		$webpage['description'] = $desc;
	}

	$graph = array( $org, $website );

	// Breadcrumb (everywhere except the front page).
	if ( ! is_front_page() ) {
		$crumbs = array( array( 'name' => 'হোম', 'url' => $home ) );
		if ( is_singular( 'post' ) ) {
			$blog = get_option( 'page_for_posts' );
			if ( $blog ) {
				$crumbs[] = array( 'name' => 'হাওর জার্নাল', 'url' => get_permalink( $blog ) );
			}
			$crumbs[] = array( 'name' => get_the_title(), 'url' => $page_url );
		} elseif ( is_category() || is_tag() ) {
			$blog = get_option( 'page_for_posts' );
			if ( $blog ) {
				$crumbs[] = array( 'name' => 'হাওর জার্নাল', 'url' => get_permalink( $blog ) );
			}
			$crumbs[] = array( 'name' => single_term_title( '', false ), 'url' => $page_url );
		} else {
			$crumbs[] = array( 'name' => is_home() ? 'হাওর জার্নাল' : get_the_title( get_queried_object_id() ), 'url' => $page_url );
		}
		$items = array();
		foreach ( $crumbs as $i => $c ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $c['name'],
				'item'     => $c['url'],
			);
		}
		$breadcrumb_id       = $page_url . '#breadcrumb';
		$graph[]             = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $breadcrumb_id,
			'itemListElement' => $items,
		);
		$webpage['breadcrumb'] = array( '@id' => $breadcrumb_id );
	}

	// Front page: the houseboat business itself.
	if ( is_front_page() ) {
		$price_range = '৳6,400–৳13,000';
		if ( function_exists( 'bhela_bm_get_rates' ) ) {
			$all = array();
			foreach ( bhela_bm_get_rates() as $r ) {
				$all[] = (int) $r['weekday'];
				$all[] = (int) $r['regular'];
			}
			if ( $all ) {
				$price_range = '৳' . number_format( min( $all ) ) . '–৳' . number_format( max( $all ) );
			}
		}
		$business = array(
			'@type'              => array( 'TouristAttraction', 'LodgingBusiness' ),
			'@id'                => $home . '#business',
			'name'               => 'BHELA – The Haor Exclusive',
			'description'        => 'Premium family & group friendly AC houseboat on Tanguar Haor, Sunamganj, Bangladesh. 2 days 1 night all-inclusive packages.',
			'url'                => $home,
			'image'              => $hero,
			'priceRange'         => $price_range,
			'currenciesAccepted' => 'BDT',
			'paymentAccepted'    => 'bKash, Nagad, Bank Transfer, Cash',
			'telephone'          => bhela_contact( 'phone_1' ),
			'email'              => bhela_contact( 'email' ),
			'parentOrganization' => array( '@id' => $org_id ),
			'address'            => $org['address'],
			'geo'                => array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => '25.09',
				'longitude' => '91.10',
			),
			'amenityFeature'     => array(
				array( '@type' => 'LocationFeatureSpecification', 'name' => 'Air Conditioning', 'value' => true ),
				array( '@type' => 'LocationFeatureSpecification', 'name' => 'Attached Washroom', 'value' => true ),
				array( '@type' => 'LocationFeatureSpecification', 'name' => 'All-inclusive Meals', 'value' => true ),
				array( '@type' => 'LocationFeatureSpecification', 'name' => 'Rooftop Deck', 'value' => true ),
			),
		);
		if ( $social ) {
			$business['sameAs'] = $social;
		}
		$stats = bhela_seo_review_stats();
		if ( $stats ) {
			$business['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $stats['value'],
				'reviewCount' => $stats['count'],
				'bestRating'  => 5,
			);
		}
		$graph[]          = $business;
		$webpage['about'] = array( '@id' => $home . '#business' );
	}

	// Single post: BlogPosting tied to the org.
	if ( is_singular( 'post' ) ) {
		$post_id = get_queried_object_id();
		$cats    = get_the_category( $post_id );
		$article = array(
			'@type'            => 'BlogPosting',
			'@id'              => $page_url . '#article',
			'headline'         => get_the_title( $post_id ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'url'              => $page_url,
			'inLanguage'       => 'bn-BD',
			'author'           => array( '@id' => $org_id ),
			'publisher'        => array( '@id' => $org_id ),
			'wordCount'        => preg_match_all( '/\S+/u', wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ),
		);
		if ( $desc ) {
			$article['description'] = $desc;
		}
		if ( $cats ) {
			$article['articleSection'] = $cats[0]->name;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$article['image'] = get_the_post_thumbnail_url( $post_id, 'bhela-wide' );
		}
		$graph[] = $article;
	}

	$graph[] = $webpage;

	echo '<script type="application/ld+json">' . wp_json_encode(
		array( '@context' => 'https://schema.org', '@graph' => $graph ),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . '</script>' . "\n";
}
add_action( 'wp_head', 'bhela_seo_schema', 2 );

/* ---------- Tracking (Analytics) ---------- */

/**
 * Customizer: Appearance → Customize → BHELA Tracking.
 * GA4 + Facebook Pixel by ID (owner pastes just the ID, we render the
 * official snippet) — no code edits ever needed. Free-form head/body/footer
 * code lives in the dedicated "BHELA Custom Code" panel (inc/custom-code.php).
 */
function bhela_customize_tracking( $wp_customize ) {
	$wp_customize->add_section( 'bhela_tracking', array(
		'title'       => 'BHELA Tracking (Analytics)',
		'priority'    => 33,
		'description' => 'Google Analytics ও Facebook Pixel-এর শুধু ID বসান — কোড নিজে যুক্ত হবে।',
	) );

	$wp_customize->add_setting( 'bhela_ga4_id', array( 'sanitize_callback' => 'bhela_sanitize_ga4_id' ) );
	$wp_customize->add_control( 'bhela_ga4_id', array(
		'label'       => 'Google Analytics 4 — Measurement ID',
		'description' => 'যেমন: G-XXXXXXXXXX (Google Tag ID GT- বা Ads AW- ও চলবে)',
		'section'     => 'bhela_tracking',
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'bhela_fb_pixel_id', array( 'sanitize_callback' => 'bhela_sanitize_pixel_id' ) );
	$wp_customize->add_control( 'bhela_fb_pixel_id', array(
		'label'       => 'Facebook (Meta) Pixel ID',
		'description' => 'শুধু সংখ্যাটা, যেমন: 1234567890123456',
		'section'     => 'bhela_tracking',
		'type'        => 'text',
	) );
}
add_action( 'customize_register', 'bhela_customize_tracking' );

function bhela_sanitize_ga4_id( $value ) {
	$value = strtoupper( trim( $value ) );
	return preg_match( '/^(G|GT|AW)-[A-Z0-9]{4,}$/', $value ) ? $value : '';
}

function bhela_sanitize_pixel_id( $value ) {
	return preg_replace( '/[^0-9]/', '', $value );
}

/** Output GA4 + Pixel. Admins are excluded so their visits don't pollute stats. */
function bhela_tracking_head() {
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}
	$ga4 = get_theme_mod( 'bhela_ga4_id', '' );
	if ( $ga4 ) {
		?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4 ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4 ); ?>');
</script>
		<?php
	}
	$pixel = get_theme_mod( 'bhela_fb_pixel_id', '' );
	if ( $pixel ) {
		?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js( $pixel ); ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel ); ?>&ev=PageView&noscript=1"/></noscript>
		<?php
	}
}
add_action( 'wp_head', 'bhela_tracking_head', 20 );

/** Preconnect to the font origins — shaves a round-trip off first paint. */
function bhela_seo_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'bhela_seo_resource_hints', 10, 2 );
