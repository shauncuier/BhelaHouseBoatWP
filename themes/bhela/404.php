<?php
/**
 * 404 template.
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<h1>৪০৪ — পেজটি হাওরে হারিয়ে গেছে 🛶</h1>
	<p>আপনি যে পেজটি খুঁজছেন সেটি নেই। হোমপেজে ফিরে যান অথবা সরাসরি বুক করুন।</p>
</div></section>
<section class="section"><div class="container center">
	<a class="btn btn--cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">হোমপেজ</a>
	<a class="btn btn--ghost-dark" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>" style="margin-left:.6rem">বুক করুন</a>
</div></section>
<?php get_footer(); ?>
