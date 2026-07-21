<?php
/**
 * Template Name: BHELA — Trip Schedule
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<h1>ট্রিপ সিডিউল</h1>
	<p>প্রতিটি ট্রিপ ২ দিন ১ রাত — Anwarpur Ghat থেকে যাত্রা শুরু ও শেষ। Weekday ট্রিপে ২০% পর্যন্ত ছাড়।</p>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>

<section class="section"><div class="container">
	<span class="eyebrow reveal">আপকামিং ডিপারচার</span>
	<h2 class="section-title reveal">📅 কোন তারিখে যাবেন?</h2>
	<p class="section-lead reveal">তারিখ বেছে নিন — জনপ্রতি রেট ও কতটি কেবিন খালি সবই এখানে। Weekday ট্রিপে ২০% ছাড়, তাই খরচ অনেক কম পড়ে।</p>

	<?php if ( shortcode_exists( 'bhela_trip_calendar' ) ) : ?>
		<div class="reveal"><?php echo do_shortcode( '[bhela_trip_calendar]' ); ?></div>
	<?php else : ?>
		<?php // The schedule lives in the booking plugin; nothing to show without it. ?>
		<p class="reveal">সিডিউল দেখতে সমস্যা হচ্ছে — <a href="<?php echo esc_url( bhela_wa_link( 'আমি ট্রিপের তারিখ জানতে চাই।' ) ); ?>" target="_blank" rel="noopener">WhatsApp-এ জিজ্ঞেস করুন</a>, আমরা সাথে সাথে জানিয়ে দেব।</p>
	<?php endif; ?>

	<div class="sched-cta reveal">
		<div>
			<h3>অন্য তারিখ বা পুরো বোট চান?</h3>
			<p>Full Boat Reservation-এ নিজের মতো সিডিউল সম্ভব — কর্পোরেট, ফ্যামিলি বা বড় গ্রুপের জন্য কাস্টম কোট।</p>
		</div>
		<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link( 'আমি কাস্টম তারিখে ট্রিপ করতে চাই।' ) ); ?>" target="_blank" rel="noopener">💬 WhatsApp-এ জিজ্ঞেস করুন</a>
	</div>
</div></section>
<?php get_footer(); ?>
