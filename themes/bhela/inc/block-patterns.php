<?php
/**
 * BHELA block patterns & block styles for Gutenberg.
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register button block styles. */
function bhela_block_styles() {
	register_block_style( 'core/button', array(
		'name'  => 'bhela-whatsapp',
		'label' => 'WhatsApp (সবুজ)',
	) );
	register_block_style( 'core/button', array(
		'name'  => 'bhela-ghost',
		'label' => 'Outline (ঘোস্ট)',
	) );
}
add_action( 'init', 'bhela_block_styles' );

/** Front-end CSS for block styles + block tweaks. */
function bhela_block_frontend_css() {
	$css = '
	.is-style-bhela-whatsapp .wp-block-button__link{background:#25D366!important;color:#fff!important;border-radius:999px}
	.is-style-bhela-whatsapp .wp-block-button__link:hover{background:#1eb457!important}
	.is-style-bhela-ghost .wp-block-button__link{background:transparent!important;color:var(--ink)!important;border:2px solid var(--ink);border-radius:999px}
	.is-style-bhela-ghost .wp-block-button__link:hover{background:var(--ink)!important;color:#fff!important}
	.entry-content .wp-block-button__link{border-radius:999px;font-weight:700}
	.wp-block-details{background:#fff;border:1px solid var(--line);border-radius:12px;padding:.9rem 1.3rem;margin-bottom:.8rem}
	.wp-block-details summary{font-weight:700;cursor:pointer}
	.bhela-gb-home{padding-top:76px}
	';
	wp_add_inline_style( 'bhela-style', $css );
}
add_action( 'wp_enqueue_scripts', 'bhela_block_frontend_css', 30 );

/** Register BHELA pattern category + patterns. */
function bhela_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'bhela', array( 'label' => '🛶 BHELA' ) );

	$wa = 'https://wa.me/8801891562461?text=' . rawurlencode( 'আসসালামু আলাইকুম। আমি ভেলা হাউসবোট সম্পর্কে জানতে চাই।' );

	/* 1. CTA Banner */
	register_block_pattern( 'bhela/cta-banner', array(
		'title'      => 'BHELA — CTA ব্যানার',
		'categories' => array( 'bhela' ),
		'content'    => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"3.5rem","bottom":"3.5rem","left":"2rem","right":"2rem"}},"border":{"radius":"20px"}},"gradient":"teal-ink","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-teal-ink-gradient-background has-background" style="border-radius:20px;padding-top:3.5rem;padding-right:2rem;padding-bottom:3.5rem;padding-left:2rem"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">এই বর্ষায় হাওর ডাকছে 🌧️</h2>
<!-- /wp:heading --><!-- wp:paragraph {"align":"center","textColor":"sand"} -->
<p class="has-text-align-center has-sand-color has-text-color">তারিখ আর অতিথি সংখ্যা জানান — ২ মিনিটে রেটসহ বিস্তারিত পেয়ে যাবেন।</p>
<!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"gradient":"sunset"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-sunset-gradient-background has-background wp-element-button" href="/book-now/">অনলাইনে বুক করুন</a></div>
<!-- /wp:button --><!-- wp:button {"className":"is-style-bhela-whatsapp"} -->
<div class="wp-block-button is-style-bhela-whatsapp"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $wa ) . '">💬 WhatsApp</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
	) );

	/* 2. Price table */
	register_block_pattern( 'bhela/price-table', array(
		'title'      => 'BHELA — কেবিন প্রাইস টেবিল',
		'categories' => array( 'bhela' ),
		'content'    => '<!-- wp:heading -->
<h2 class="wp-block-heading">💰 প্যাকেজ রেট (২ দিন ১ রাত, জনপ্রতি)</h2>
<!-- /wp:heading --><!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>কেবিন</th><th>শেয়ারিং</th><th>Regular/Holiday</th><th>Weekday (−২০%)</th></tr></thead><tbody><tr><td>🟢 Budget Friendly</td><td>৬ জন</td><td>৳৮,০০০</td><td>৳৬,৪০০</td></tr><tr><td>🔵 Comfort</td><td>৫ জন</td><td>৳৯,০০০</td><td>৳৭,২০০</td></tr><tr><td>🟡 Double Deluxe</td><td>৪ জন</td><td>৳১০,০০০</td><td>৳৮,০০০</td></tr><tr><td>🟣 Luxury Triple</td><td>৩ জন</td><td>৳১২,০০০</td><td>৳৯,৬০০</td></tr><tr><td>🔴 Exclusive Couple</td><td>২ জন</td><td>৳১৩,০০০</td><td>৳১০,৪০০</td></tr></tbody></table></figure>
<!-- /wp:table -->',
	) );

	/* 3. Trust strip */
	register_block_pattern( 'bhela/trust-strip', array(
		'title'      => 'BHELA — Trust স্ট্রিপ',
		'categories' => array( 'bhela' ),
		'content'    => '<!-- wp:columns {"style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem"}},"border":{"radius":"16px"}},"backgroundColor":"sand"} -->
<div class="wp-block-columns has-sand-background-color has-background" style="border-radius:16px;padding-top:1.5rem;padding-bottom:1.5rem"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>🛟 Life Jacket ও প্রশিক্ষিত ক্রু</strong></p><!-- /wp:paragraph --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>❄️ AC + Attached Washroom</strong></p><!-- /wp:paragraph --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>👨‍👩‍👧‍👦 Family Privacy</strong></p><!-- /wp:paragraph --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>🍛 আনলিমিটেড চা-কফি</strong></p><!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
	) );

	/* 4. FAQ item */
	register_block_pattern( 'bhela/faq-item', array(
		'title'      => 'BHELA — FAQ আইটেম',
		'categories' => array( 'bhela' ),
		'content'    => '<!-- wp:details -->
<details class="wp-block-details"><summary>বুকিং কনফার্ম করতে কত টাকা অগ্রিম দিতে হয়?</summary><!-- wp:paragraph -->
<p>মোট প্যাকেজ মূল্যের ৫০% অগ্রিম (bKash/Nagad/Bank)। বাকি ৫০% অনবোর্ড হওয়ার সময়।</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->',
	) );

	/* 5. Contact CTA */
	register_block_pattern( 'bhela/contact-cta', array(
		'title'      => 'BHELA — যোগাযোগ বাটন',
		'categories' => array( 'bhela' ),
		'content'    => '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"gradient":"sunset"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-sunset-gradient-background has-background wp-element-button" href="tel:01891562461">📞 01891-562461</a></div>
<!-- /wp:button --><!-- wp:button {"className":"is-style-bhela-whatsapp"} -->
<div class="wp-block-button is-style-bhela-whatsapp"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $wa ) . '">💬 WhatsApp-এ বুক করুন</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->',
	) );
}
add_action( 'init', 'bhela_block_patterns' );
