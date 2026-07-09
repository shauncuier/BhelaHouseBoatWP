<?php
/**
 * Template Name: BHELA — Trip Schedule
 *
 * @package Bhela
 */

get_header();

$trips = array(
	array( '31 Jul – 1 Aug 2026', 'শুক্র – শনি', 'weekend', 'Weekend' ),
	array( '2 – 3 Aug 2026', 'রবি – সোম', 'weekday', 'Weekday −20%' ),
	array( '4 – 5 Aug 2026', 'মঙ্গল – বুধ', 'holiday', '৫ আগস্ট ছুটি' ),
	array( '7 – 8 Aug 2026', 'শুক্র – শনি', 'weekend', 'Weekend' ),
	array( '9 – 10 Aug 2026', 'রবি – সোম', 'weekday', 'Weekday −20%' ),
	array( '11 – 12 Aug 2026', 'মঙ্গল – বুধ', 'holiday', '১২ আগস্ট ছুটি' ),
	array( '14 – 15 Aug 2026', 'শুক্র – শনি', 'weekend', 'Weekend' ),
	array( '16 – 17 Aug 2026', 'রবি – সোম', 'weekday', 'Weekday −20%' ),
	array( '18 – 19 Aug 2026', 'মঙ্গল – বুধ', 'weekday', 'Weekday −20%' ),
	array( '21 – 22 Aug 2026', 'শুক্র – শনি', 'weekend', 'Weekend' ),
	array( '23 – 24 Aug 2026', 'রবি – সোম', 'weekday', 'Weekday −20%' ),
	array( '25 – 26 Aug 2026', 'মঙ্গল – বুধ', 'holiday', '২৬ আগস্ট ছুটি' ),
	array( '28 – 29 Aug 2026', 'শুক্র – শনি', 'weekend', 'Weekend' ),
);
?>
<section class="page-hero"><div class="container">
	<h1>ট্রিপ সিডিউল</h1>
	<p>প্রতিটি ট্রিপ ২ দিন ১ রাত — Anwarpur Ghat থেকে যাত্রা শুরু ও শেষ। Weekday ট্রিপে ২০% পর্যন্ত ছাড়।</p>
</div></section>

<section class="section"><div class="container">
	<h2 class="section-title reveal">📅 আগস্ট ২০২৬</h2>
	<div style="overflow-x:auto" class="reveal">
		<table class="sched-table">
			<thead><tr><th>তারিখ</th><th>দিন</th><th>ধরন</th><th></th></tr></thead>
			<tbody>
				<?php foreach ( $trips as $t ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $t[0] ); ?></strong></td>
						<td><?php echo esc_html( $t[1] ); ?></td>
						<td><span class="tag tag--<?php echo esc_attr( $t[2] ); ?>"><?php echo esc_html( $t[3] ); ?></span></td>
						<td style="text-align:right"><a class="btn btn--cta" style="padding:.5rem 1.3rem;min-height:38px;font-size:.9rem" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুক করুন</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p style="margin-top:1.6rem;color:var(--text-soft)">অন্য মাসের তারিখ বা কাস্টম ডেট চান? <a href="<?php echo esc_url( bhela_wa_link( 'আমি কাস্টম তারিখে ট্রিপ করতে চাই।' ) ); ?>" target="_blank" rel="noopener">WhatsApp-এ জিজ্ঞেস করুন</a> — Full Boat Reservation-এ নিজের মতো সিডিউল সম্ভব।</p>
</div></section>
<?php get_footer(); ?>
