<?php
/**
 * Template Name: BHELA — Trip Spots
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$bhela_map = get_theme_mod( 'bhela_img_trip_map', '' );
?>
<section class="page-hero"><div class="container">
	<h1>হাওর ট্রিপ ম্যাপ</h1>
	<p>২ দিন ১ রাতের অসাধারণ এক ভ্রমণ — Anwarpur Ghat থেকে শুরু, ৯টি স্পট ঘুরে আবার Anwarpur Ghat-এ ফিরে আসা।</p>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>

<?php if ( $bhela_map ) : ?>
<section class="section"><div class="container">
	<figure class="spot-map reveal">
		<img src="<?php echo esc_url( $bhela_map ); ?>" alt="ভেলার হাওর ট্রিপ ম্যাপ — Anwarpur Ghat, Tanguar Haor, Watch Tower, Tekerghat, Niladri Lake ও আরও স্পট" loading="lazy">
	</figure>
</div></section>
<?php endif; ?>

<?php
// Prefer the manageable Spots CPT (plugin); fall back to the theme's built-in
// list if the plugin is not active. Group by type so the two are clearly split.
if ( function_exists( 'bhela_bm_get_spots' ) ) {
	$bhela_included = bhela_bm_get_spots( 'included' );
	$bhela_optional = bhela_bm_get_spots( 'optional' );
} else {
	$bhela_included = array_map( function ( $s ) {
		return array( 'en' => $s['en'], 'bn' => $s['bn'], 'desc' => $s['desc'], 'img' => bhela_img( $s['img'], $s['file'], 'bhela-card' ) );
	}, bhela_spots() );
	$bhela_optional = array();
}

/**
 * Render one spot grid.
 * @param array  $spots  Rows of { en, bn, desc, img }.
 * @param string $badge  '' | 'included' | 'optional'.
 */
$bhela_render_spots = function ( $spots, $badge ) {
	if ( ! $spots ) {
		return;
	}
	echo '<ol class="spots-route">';
	$n = 0;
	foreach ( $spots as $s ) {
		$n++;
		?>
		<li class="spot-card reveal">
			<div class="spot-card__media">
				<?php if ( ! empty( $s['img'] ) ) : ?>
					<img src="<?php echo esc_url( $s['img'] ); ?>" alt="<?php echo esc_attr( $s['en'] . ' — ' . $s['bn'] ); ?>" loading="lazy">
				<?php else : ?>
					<span class="spot-card__noimg">📍</span>
				<?php endif; ?>
				<span class="spot-card__num"><?php echo esc_html( $n ); ?></span>
				<?php if ( 'optional' === $badge ) : ?>
					<span class="spot-tag spot-tag--optional">💠 ঐচ্ছিক · নিজ খরচে</span>
				<?php elseif ( 'included' === $badge ) : ?>
					<span class="spot-tag spot-tag--included">✅ প্যাকেজে</span>
				<?php endif; ?>
			</div>
			<div class="spot-card__body">
				<h3 class="spot-card__title"><?php echo esc_html( $s['en'] ); ?></h3>
				<?php if ( ! empty( $s['bn'] ) ) : ?><span class="spot-card__bn"><?php echo esc_html( $s['bn'] ); ?></span><?php endif; ?>
				<?php if ( ! empty( $s['desc'] ) ) : ?><p class="spot-card__desc"><?php echo esc_html( $s['desc'] ); ?></p><?php endif; ?>
			</div>
		</li>
		<?php
	}
	echo '</ol>';
};
?>

<section class="section<?php echo $bhela_map ? ' section--sand' : ''; ?>"><div class="container">
	<span class="eyebrow reveal">ভ্রমণ রুট</span>
	<h2 class="section-title reveal">এক ট্রিপে হাওরের সেরা স্পটগুলো</h2>
	<p class="section-lead reveal">দুই ধরনের স্পট — কিছু আমাদের প্যাকেজেই অন্তর্ভুক্ত, আর কিছু ঐচ্ছিক (চাইলে অতিথি নিজ খরচে ঘুরে আসতে পারেন)। আবহাওয়া ও প্রশাসনিক অনুমতির ভিত্তিতে ঘোরানো হয়।</p>

	<div class="spots-legend reveal">
		<span class="spots-legend__item"><span class="dot dot--in"></span> প্যাকেজে অন্তর্ভুক্ত — আমরা নিয়ে যাই</span>
		<span class="spots-legend__item"><span class="dot dot--opt"></span> ঐচ্ছিক — অতিথি নিজ খরচে যেতে পারেন</span>
	</div>

	<?php if ( $bhela_included ) : ?>
		<h3 class="spots-group reveal">✅ প্যাকেজে অন্তর্ভুক্ত</h3>
		<?php $bhela_render_spots( $bhela_included, 'included' ); ?>
	<?php endif; ?>

	<?php if ( $bhela_optional ) : ?>
		<h3 class="spots-group spots-group--optional reveal">💠 ঐচ্ছিক — নিজ খরচে</h3>
		<p class="section-lead reveal" style="margin-bottom:1.4rem">এই স্পটগুলো প্যাকেজের বাইরে। আগ্রহ থাকলে জানান — স্থানীয় যাতায়াত ও খরচ অতিথির নিজের।</p>
		<?php $bhela_render_spots( $bhela_optional, 'optional' ); ?>
	<?php endif; ?>

	<div class="sched-cta reveal">
		<div>
			<h3>এই রুটে বুক করতে চান?</h3>
			<p>তারিখ বেছে নিন, জনপ্রতি রেট ও কেবিন খালি আছে কিনা দেখুন — কয়েক ক্লিকেই বুকিং রিকোয়েস্ট পাঠান।</p>
		</div>
		<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">তারিখ দেখে বুক করুন →</a>
	</div>
</div></section>
<?php get_footer(); ?>
