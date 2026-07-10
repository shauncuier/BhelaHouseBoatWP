<?php
/**
 * Template Name: BHELA — Cabins & Rates
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<h1>কেবিন ও রেট</h1>
	<p>৬টি বড় ফ্যামিলি কেবিন — AC, Attached Washroom, Infinity Glass Window। এক কেবিনে যত বেশি সদস্য, জনপ্রতি খরচ তত কম।</p>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>


<section class="section"><div class="container">
	<div class="cabins-grid">
		<?php foreach ( bhela_cabins() as $key => $c ) : ?>
			<article class="cabin-card reveal">
				<div class="cabin-card__media">
					<img src="<?php echo esc_url( $c['img'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" loading="lazy">
					<?php if ( $c['badge'] ) : ?><span class="cabin-card__badge"><?php echo esc_html( $c['badge'] ); ?></span><?php endif; ?>
				</div>
				<div class="cabin-card__body">
					<h3 class="cabin-card__title"><?php echo esc_html( $c['name'] ); ?></h3>
					<div class="cabin-card__meta"><span>👥 <?php echo esc_html( $c['sharing'] ); ?> জন</span><span>❄️ AC</span><span>🚿 Washroom</span></div>
					<p style="font-size:.92rem;color:var(--text-soft)"><?php echo esc_html( $c['bn'] ); ?></p>
					<table style="width:100%;font-size:.92rem;margin:.8rem 0;border-collapse:collapse">
						<tr><td style="padding:.3rem 0;color:var(--text-soft)">Weekend/Holiday</td><td style="text-align:right;font-weight:700"><?php echo esc_html( bhela_money( $c['regular'] ) ); ?>/জন</td></tr>
						<tr><td style="padding:.3rem 0;color:var(--text-soft)">Weekday 🔥</td><td style="text-align:right;font-weight:700;color:var(--primary)"><?php echo esc_html( bhela_money( $c['weekday'] ) ); ?>/জন</td></tr>
					</table>
					<div class="cabin-card__cta"><a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুক করুন</a></div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="entry-content" style="margin-top:3.5rem">
		<h2>✅ প্যাকেজে যা অন্তর্ভুক্ত</h2>
		<ul class="checklist" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
			<li>২ দিন ১ রাত আবাসন</li><li>২টি Breakfast, ২টি Lunch, ১টি Dinner</li><li>Evening Snacks ও চা-কফি</li>
			<li>Welcome Drinks</li><li>৭টি স্পট ভ্রমণ</li><li>গাইড ও ২৪ ঘণ্টা স্টাফ সাপোর্ট</li>
			<li>Life Jacket ও নিরাপত্তা</li><li>বিশুদ্ধ পানীয় জল</li>
		</ul>
		<h2>❌ প্যাকেজের বাইরে</h2>
		<p>কিছু স্পটের Entry Fee, ছোট নৌকার ভাড়া (যেখানে হাউসবোট যেতে পারে না), ব্যক্তিগত খরচ।</p>
		<h2>👶 শিশু নীতিমালা</h2>
		<p>০–৪ বছর: সম্পূর্ণ ফ্রি · ৪–৮ বছর: ৫০% চার্জ · ৯+ বছর: পূর্ণ চার্জ</p>
		<h2>🏢 Corporate ও Full Boat</h2>
		<p>Full Boat Reservation-এ পুরো বোট (সর্বোচ্চ ৪০ জন) শুধু আপনার গ্রুপের। Custom Menu, Team Building, Meeting Setup — <a href="<?php echo esc_url( bhela_wa_link( 'Full Boat / Corporate বুকিং সম্পর্কে জানতে চাই।' ) ); ?>" target="_blank" rel="noopener">WhatsApp-এ কোটেশন নিন</a>।</p>
	</div>
</div></section>
<?php get_footer(); ?>
