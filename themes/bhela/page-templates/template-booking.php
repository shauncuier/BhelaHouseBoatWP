<?php
/**
 * Template Name: BHELA — Book Now
 *
 * @package Bhela
 */

get_header();
?>
<style>
	/* Book Now page — scoped helpers (loads only on this template) */
	.bookpage-hero .book-stats { display: flex; flex-wrap: wrap; gap: 1.4rem 2.4rem; margin-top: 1.6rem; }
	.bookpage-hero .book-stat strong { display: block; font-family: var(--font-display); font-size: 1.6rem; color: var(--gold); line-height: 1.1; }
	.bookpage-hero .book-stat span { font-size: .82rem; color: var(--text-inverse, #DCEBE9); opacity: .8; }
	.book-form-section { padding-top: 2.4rem; }
	.book-process { display: grid; grid-template-columns: repeat(3, 1fr); gap: .9rem; margin-top: 1.6rem; }
	@media (max-width: 760px) { .book-process { grid-template-columns: 1fr; } }
	.book-pstep { display: flex; gap: .9rem; align-items: flex-start; background: var(--white, #fff); border: 1px solid var(--line, #e4ddce); border-radius: 14px; padding: 1.1rem 1.2rem; box-shadow: var(--shadow-sm, 0 6px 22px rgba(10,42,47,.06)); }
	.book-pstep__n { flex: none; width: 34px; height: 34px; border-radius: 10px; background: var(--sand, #F4EDE1); color: var(--primary, #137A74); font-family: var(--font-display); font-weight: 800; display: flex; align-items: center; justify-content: center; }
	.book-pstep b { display: block; color: var(--ink, #0A2A2F); font-size: .98rem; margin-bottom: .15rem; }
	.book-pstep span { font-size: .86rem; color: var(--text-soft, #5E7472); line-height: 1.5; }
	.book-trust-strip { margin-top: 1.6rem; background: var(--ink, #0A2A2F); border-radius: 16px; padding: 1.2rem 1.4rem; display: flex; flex-wrap: wrap; gap: 1rem 1.8rem; justify-content: center; }
	.book-trust-strip span { color: var(--text-inverse, #DCEBE9); font-size: .9rem; display: flex; gap: .45rem; align-items: center; }
</style>

<section class="page-hero bookpage-hero"><div class="container">
	<h1>২ মিনিটে বুক করুন, সাথে সাথে রেট দেখুন</h1>
	<p>তারিখ ও অতিথি সংখ্যা দিন — সিস্টেম নিজেই সেরা কেবিন কম্বিনেশন ও দাম বেছে দেবে। ৫০% অগ্রিমে বুকিং Confirmed।</p>
	<div class="bookpage-hero__actions" style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.4rem">
		<a class="btn btn--cta" href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>">📞 <?php echo esc_html( bhela_contact( 'phone_1' ) ); ?></a>
		<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">💬 WhatsApp-এ সরাসরি বুক করুন</a>
	</div>
	<div class="book-stats">
		<div class="book-stat"><strong>★ ৪.৯</strong><span>গেস্ট রেটিং</span></div>
		<div class="book-stat"><strong>৬টি</strong><span>ফ্যামিলি কেবিন</span></div>
		<div class="book-stat"><strong>২দিন ১রাত</strong><span>অল-ইনক্লুসিভ</span></div>
		<div class="book-stat"><strong>−২০%</strong><span>Weekday অফার</span></div>
	</div>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>

<section class="section book-form-section"><div class="container">
	<?php
	if ( shortcode_exists( 'bhela_booking_form' ) ) {
		echo do_shortcode( '[bhela_booking_form]' );
	} else {
		?>
		<div class="cta-banner">
			<h2>বুকিং ইঞ্জিন সক্রিয় নয়</h2>
			<p>"BHELA Booking Engine" প্লাগইনটি Activate করুন — অথবা সরাসরি WhatsApp-এ বুক করুন।</p>
			<div class="btn-row"><a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">💬 WhatsApp-এ বুক করুন</a></div>
		</div>
		<?php
	}
	?>
</div></section>

<section class="section section--sand"><div class="container">
	<span class="eyebrow">কীভাবে কাজ করে</span>
	<h2 class="section-title">বুকিং প্রসেস — ৬টি সহজ ধাপ</h2>
	<div class="book-process">
		<div class="book-pstep"><div class="book-pstep__n">১</div><div><b>তারিখ বাছাই</b><span>Availability সাথে সাথে দেখুন</span></div></div>
		<div class="book-pstep"><div class="book-pstep__n">২</div><div><b>অতিথি সংখ্যা</b><span>সেরা কেবিন প্ল্যান অটো-বাছাই (চাইলে নিজেও সাজান)</span></div></div>
		<div class="book-pstep"><div class="book-pstep__n">৩</div><div><b>তথ্য দিন</b><span>নাম ও মোবাইল নম্বর</span></div></div>
		<div class="book-pstep"><div class="book-pstep__n">৪</div><div><b>৫০% অগ্রিম</b><span>bKash / Nagad / Bank</span></div></div>
		<div class="book-pstep"><div class="book-pstep__n">৫</div><div><b>Confirmation</b><span>ইনভয়েস ও প্রয়োজনীয় নির্দেশনা</span></div></div>
		<div class="book-pstep"><div class="book-pstep__n">৬</div><div><b>যাত্রা শুরু</b><span>নির্ধারিত দিনে Anwarpur Ghat থেকে</span></div></div>
	</div>

	<div class="book-trust-strip">
		<span>🛟 Life Jacket ও প্রশিক্ষিত ক্রু</span>
		<span>❄️ AC + Attached Washroom</span>
		<span>🔌 ২৪ ঘণ্টা বিদ্যুৎ</span>
		<span>👨‍👩‍👧‍👦 অপরিচিতদের সাথে শেয়ার নয়</span>
	</div>

	<div class="entry-content" style="margin-top:2.4rem">
		<h2>📞 অন্যান্য যোগাযোগ</h2>
		<p>
			📱 <a href="tel:<?php echo esc_attr( bhela_contact( 'phone_2' ) ); ?>"><?php echo esc_html( bhela_contact( 'phone_2' ) ); ?></a> ·
			✉️ <a href="mailto:<?php echo esc_attr( bhela_contact( 'email' ) ); ?>"><?php echo esc_html( bhela_contact( 'email' ) ); ?></a>
		</p>
		<p style="font-size:.9rem;color:var(--text-soft)">বুকিং সম্পন্ন করার মাধ্যমে আপনি আমাদের <a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">বুকিং নীতিমালায়</a> সম্মতি প্রদান করছেন।</p>
	</div>
</div></section>
<?php get_footer(); ?>
