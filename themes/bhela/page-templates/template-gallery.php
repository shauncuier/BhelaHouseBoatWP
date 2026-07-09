<?php
/**
 * Template Name: BHELA — Gallery
 *
 * @package Bhela
 */

get_header();

$dir  = get_template_directory() . '/assets/images';
$uri  = get_template_directory_uri() . '/assets/images';
$imgs = array();
foreach ( array( 'hero', 'boat', 'cabins', 'spots', 'food' ) as $folder ) {
	foreach ( (array) glob( $dir . '/' . $folder . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE ) as $file ) {
		$imgs[] = $uri . '/' . $folder . '/' . basename( $file );
	}
}
?>
<section class="page-hero"><div class="container">
	<h1>গ্যালারি</h1>
	<p>ভেলা আর টাঙ্গুয়ার হাওরের মুহূর্তগুলো — কেবিন, রুফটপ, খাবার আর হাওরের রূপ।</p>
</div></section>

<section class="section"><div class="container">
	<?php if ( $imgs ) : ?>
		<div class="gallery-grid">
			<?php foreach ( $imgs as $src ) : ?>
				<a href="<?php echo esc_url( $src ); ?>"><img src="<?php echo esc_url( $src ); ?>" alt="BHELA হাউসবোট ও টাঙ্গুয়ার হাওর" loading="lazy"></a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p>ছবি শীঘ্রই যুক্ত হচ্ছে…</p>
	<?php endif; ?>
	<p class="center" style="margin-top:2.4rem"><a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">নিজের চোখে দেখতে বুক করুন</a></p>
</div></section>
<?php get_footer(); ?>
