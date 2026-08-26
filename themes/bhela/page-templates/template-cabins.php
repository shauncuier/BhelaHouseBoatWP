<?php
/**
 * Template Name: BHELA — Cabins & Rates
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
					<?php
					// The offer, if one is running. Rates are read through
					// bhela_bm_offer_rate() so this card can never quote a price the
					// booking form would not charge; the percentage is DERIVED, because
					// a hardcoded one beside a live figure becomes a lie the day the
					// offer changes.
					$cab_offer = function_exists( 'bhela_bm_offer' ) ? bhela_bm_offer() : array( 'active' => false, 'label' => '' );
					$cab_wknd  = function_exists( 'bhela_bm_offer_rate' ) ? bhela_bm_offer_rate( $c, 'weekend' ) : (int) $c['regular'];
					$cab_wkday = function_exists( 'bhela_bm_offer_rate' ) ? bhela_bm_offer_rate( $c, 'weekday' ) : (int) $c['weekday'];
					$cab_pct   = function ( $now, $was ) {
						return $was > 0 ? (int) round( ( 1 - $now / $was ) * 100 ) : 0;
					};
					$cab_on    = ! empty( $cab_offer['active'] ) && ( $cab_wknd < (int) $c['regular'] || $cab_wkday < (int) $c['weekday'] );
					?>
					<table class="cabin-rate-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'দিন', 'bhela' ); ?></th>
								<?php if ( $cab_on ) : ?>
									<th><?php esc_html_e( 'রেট', 'bhela' ); ?></th>
									<th><?php esc_html_e( 'অফার', 'bhela' ); ?></th>
								<?php else : ?>
									<th colspan="2"><?php esc_html_e( 'জনপ্রতি', 'bhela' ); ?></th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>Weekend/Holiday</td>
								<?php if ( $cab_on ) : ?>
									<td class="was"><s><?php echo esc_html( bhela_money( $c['regular'] ) ); ?></s></td>
									<td class="now"><?php echo esc_html( bhela_money( $cab_wknd ) ); ?></td>
								<?php else : ?>
									<td class="now" colspan="2"><?php echo esc_html( bhela_money( $c['regular'] ) ); ?></td>
								<?php endif; ?>
							</tr>
							<tr>
								<td>Weekday <span class="off"><?php echo esc_html( '−' . $cab_pct( $cab_wkday, (int) $c['regular'] ) . '%' ); ?></span></td>
								<?php if ( $cab_on ) : ?>
									<td class="was"><s><?php echo esc_html( bhela_money( $c['weekday'] ) ); ?></s></td>
									<td class="now is-accent"><?php echo esc_html( bhela_money( $cab_wkday ) ); ?></td>
								<?php else : ?>
									<td class="now is-accent" colspan="2"><?php echo esc_html( bhela_money( $c['weekday'] ) ); ?></td>
								<?php endif; ?>
							</tr>
						</tbody>
						<tfoot>
							<tr><td colspan="3"><?php esc_html_e( 'জনপ্রতি · ২ দিন ১ রাত', 'bhela' ); ?></td></tr>
						</tfoot>
					</table>
					<?php if ( $cab_on ) : ?>
						<?php // Bordered, not filled: the offer badge must survive a print. ?>
						<p style="margin:-.4rem 0 .8rem;display:inline-block;padding:.2rem .6rem;border:1px solid var(--cta,#FF7A3D);border-radius:999px;font-size:.78rem;font-weight:700;letter-spacing:.04em;color:var(--cta,#FF7A3D)">
							🎉 <?php echo esc_html( $cab_offer['label'] ); ?>
						</p>
					<?php endif; ?>
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
		<p>রেট কেন এক বোট থেকে আরেক বোটে আলাদা হয়, আর বুকিংয়ের আগে কী কী যাচাই করবেন — বিস্তারিত আছে <a href="<?php echo esc_url( bhela_page_url( 'booking-guide' ) ); ?>#rate-factors">বুকিং গাইডে</a>।</p>
		<h2>❌ প্যাকেজের বাইরে</h2>
		<p>কিছু স্পটের Entry Fee, ছোট নৌকার ভাড়া (যেখানে হাউসবোট যেতে পারে না), ব্যক্তিগত খরচ।</p>
		<h2>👶 শিশু নীতিমালা</h2>
		<p>০–৪ বছর: সম্পূর্ণ ফ্রি · ৪–৮ বছর: ফিক্সড ৳৫,০০০ · ৯+ বছর: পূর্ণ চার্জ</p>
		<h2>🏢 Corporate ও Full Boat</h2>
		<p>Full Boat Reservation-এ পুরো বোট (সর্বোচ্চ ৪০ জন) শুধু আপনার গ্রুপের। Custom Menu, Team Building, Meeting Setup — <a href="<?php echo esc_url( bhela_wa_link( 'Full Boat / Corporate বুকিং সম্পর্কে জানতে চাই।' ) ); ?>" target="_blank" rel="noopener">WhatsApp-এ কোটেশন নিন</a>।</p>
	</div>
</div></section>
<?php get_footer(); ?>
