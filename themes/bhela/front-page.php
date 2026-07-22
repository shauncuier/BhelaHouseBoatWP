<?php
/**
 * Front page — Midnight Monsoon homepage.
 *
 * @package Bhela
 */

// Gutenberg override: if the front page has its own block content, render that
// instead of the default design — making the homepage fully editable.
$front_id = (int) get_option( 'page_on_front' );
$bhela_front_elementor = function_exists( 'bhela_is_elementor_page' ) && bhela_is_elementor_page( $front_id );
if ( $front_id && is_page( $front_id ) && ( $bhela_front_elementor || trim( get_post_field( 'post_content', $front_id ) ) ) ) {
	get_header();
	if ( $bhela_front_elementor ) {
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		get_footer();
		return;
	}
	echo '<div class="bhela-gb-home"><div class="container"><div class="entry-content">';
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	echo '</div></div></div>';
	get_footer();
	return;
}

get_header();
$img = get_template_directory_uri() . '/assets/images';
?>

<!-- HERO -->
<section class="hero">
	<div class="hero__bg"><img src="<?php echo esc_url( bhela_img( 'hero', 'hero/hero-haor.jpg' ) ); ?>" alt="টাঙ্গুয়ার হাওরে ভেলা হাউসবোট" fetchpriority="high" decoding="async" class="skip-lazy" data-no-lazy="1"></div>
	<div class="container hero__inner">
		<div>
			<span class="hero__kicker"><?php echo bhela_home_text( 'hero_kicker', '🌧️ টাঙ্গুয়ার হাওর · প্রিমিয়াম হাউসবোট' ); ?></span>
			<h1 class="hero__title"><?php echo wp_kses( bhela_home_text( 'hero_title', 'ভেলার আকর্ষণ|ভেলা নয়, *হাওর!*' ), array( 'br' => array(), 'em' => array() ) ); ?></h1>
			<p class="hero__sub"><?php echo bhela_home_text( 'hero_sub', 'মাত্র ৬টি ফ্যামিলি কেবিন, AC ও Attached Washroom, দেশি খাবার আর অথৈ জলরাশি — ২ দিন ১ রাতের সম্পূর্ণ প্যাকেজে হাওরের সেরা অভিজ্ঞতা।' ); ?></p>
			<div class="hero__actions">
				<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">তারিখ দেখে বুক করুন</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( bhela_page_url( 'cabins' ) ); ?>">কেবিন ও রেট</a>
			</div>
			<div class="hero__stats">
				<div class="hero__stat"><strong>★ ৪.৯</strong><span>গেস্ট রেটিং</span></div>
				<div class="hero__stat"><strong>৬টি</strong><span>ফ্যামিলি কেবিন</span></div>
				<div class="hero__stat"><strong>২দিন ১রাত</strong><span>অল-ইনক্লুসিভ</span></div>
				<div class="hero__stat"><strong>-২০%</strong><span>Weekday অফার</span></div>
			</div>
		</div>

		<div class="hero-card" id="quick-estimate">
			<h3>⚡ 2 মিনিটে Available ট্রিপ ডেট ও রেট দেখে হাওর ট্রিপ বুক করুন</h3>
			<label for="qe-date">ভ্রমণের তারিখ</label>
			<input type="date" id="qe-date" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
			<label for="qe-cabin">কোন ধরণের কেবিন</label>
			<select id="qe-cabin">
				<option value="">— বাছাই করুন —</option>
				<?php foreach ( bhela_cabins() as $key => $c ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $c['name'] ); ?> (<?php echo esc_html( $c['sharing'] ); ?> জন শেয়ারিং)</option>
				<?php endforeach; ?>
			</select>
			<label for="qe-guests">মোট অতিথি</label>
			<select id="qe-guests">
				<?php for ( $i = 1; $i <= 40; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, 4 ); ?>><?php echo esc_html( $i ); ?> জন</option>
				<?php endfor; ?>
			</select>
			<div class="hero-card__result" id="qe-result" hidden>
				<span id="qe-meta">—</span>
				<strong id="qe-total">—</strong>
			</div>
			<a class="btn btn--cta" id="qe-book" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুকিং করুন →</a>
		</div>
	</div>
	<span class="hero__scroll">Scroll ▾</span>
</section>

<!-- TRUST STRIP -->
<div class="trust">
	<div class="container trust__inner">
		<span class="trust__item"><span class="ic">🛟</span> Life Jacket ও প্রশিক্ষিত ক্রু</span>
		<span class="trust__item"><span class="ic">❄️</span> AC + Attached Washroom</span>
		<span class="trust__item"><span class="ic">👨‍👩‍👧‍👦</span> অপরিচিতদের সাথে কেবিন শেয়ার নয়</span>
		<span class="trust__item"><span class="ic">🔌</span> ২৪ ঘণ্টা বিদ্যুৎ</span>
		<span class="trust__item"><span class="ic">🍛</span> আনলিমিটেড চা-কফি</span>
	</div>
</div>

<!-- CABINS -->
<section class="section" id="cabins">
	<div class="container">
		<span class="eyebrow reveal">কেবিন ও রেট</span>
		<h2 class="section-title reveal">যেভাবে থাকবেন, সেভাবেই রেট</h2>
		<p class="section-lead reveal">এক কেবিনে যত বেশি সদস্য, জনপ্রতি খরচ তত কম। সব রেটে থাকা + সকল খাবার + হাওর ভ্রমণ অন্তর্ভুক্ত।</p>
		<div class="cabins-grid">
			<?php foreach ( bhela_cabins() as $key => $c ) : ?>
				<article class="cabin-card reveal">
					<div class="cabin-card__media">
						<img src="<?php echo esc_url( $c['img'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" loading="lazy">
						<?php if ( $c['badge'] ) : ?><span class="cabin-card__badge"><?php echo esc_html( $c['badge'] ); ?></span><?php endif; ?>
					</div>
					<div class="cabin-card__body">
						<h3 class="cabin-card__title"><?php echo esc_html( $c['name'] ); ?></h3>
						<div class="cabin-card__meta"><span>👥 <?php echo esc_html( $c['sharing'] ); ?> জন শেয়ারিং</span><span>❄️ AC</span><span>🚿 Washroom</span></div>
						<p style="font-size:.92rem;color:var(--text-soft)"><?php echo esc_html( $c['bn'] ); ?></p>
						<div class="cabin-card__price">
							<span class="now"><?php echo esc_html( bhela_money( $c['weekday'] ) ); ?></span>
							<span class="was"><?php echo esc_html( bhela_money( $c['regular'] ) ); ?></span>
							<span class="per">জনপ্রতি · Weekday</span>
							<span class="save-chip">সাশ্রয় <?php echo esc_html( bhela_money( $c['regular'] - $c['weekday'] ) ); ?></span>
						</div>
						<div class="cabin-card__cta"><a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">এই কেবিন বুক করুন</a></div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- FOOD -->
<section class="section section--sand" id="food">
	<div class="container split">
		<div class="split__media reveal">
			<img src="<?php echo esc_url( bhela_img( 'food', 'food/food-spread.jpg', 'bhela-card' ) ); ?>" alt="ভেলার দেশি খাবার" loading="lazy">
		</div>
		<div class="reveal">
			<span class="eyebrow">খাবারের আয়োজন</span>
			<h2 class="section-title">হাওরের তাজা মাছ,<br>দেশি স্বাদের ভরপুর আয়োজন</h2>
			<p class="section-lead">২ দিন ১ রাতে ৬ বেলা খাবার — ভুনা খিচুড়ি থেকে দেশি হাঁস, আকনি থেকে BBQ (বিশেষ প্যাকেজে)।</p>
			<ul class="checklist">
				<li>হাওরের বড় তাজা মাছ</li>
				<li>দেশি মুরগি ও হাঁস</li>
				<li>বাহারি ভর্তা ও শুটকি</li>
				<li>Welcome Drinks</li>
				<li>সিজনাল ফল</li>
				<li>আনলিমিটেড চা-কফি</li>
			</ul>
			<a class="btn btn--ghost-dark" href="<?php echo esc_url( bhela_page_url( 'food' ) ); ?>">সম্পূর্ণ মেনু দেখুন</a>
		</div>
	</div>
</section>

<!-- EXPERIENCE / SPOTS -->
<section class="section section--dark has-wave-top" id="experience">
	<div class="container">
		<span class="eyebrow reveal">ভ্রমণ অভিজ্ঞতা</span>
		<h2 class="section-title reveal">এক ট্রিপে হাওরের সেরা ৭টি স্পট</h2>
		<p class="section-lead reveal">আবহাওয়া ও প্রশাসনিক অনুমতির ভিত্তিতে প্রতিটি ট্রিপে ঘুরিয়ে দেখানো হয়।</p>
		<div class="spots-grid">
			<?php
			$spots = array(
				array( 'টাঙ্গুয়ার হাওর', 'অথৈ জলরাজ্য', 'spots/spot-1.jpg' ),
				array( 'নীলাদ্রি লেক', 'নীল জলের রাজ্য', 'spots/spot-2.jpg' ),
				array( 'জাদুকাটা নদী', 'স্বচ্ছ জলের নদী', 'spots/spot-3.jpg' ),
				array( 'বারিক্কা টিলা', 'মেঘালয়ের ভিউ', 'spots/spot-4.jpg' ),
				array( 'ওয়াচ টাওয়ার', 'হাওরের প্যানোরামা', 'spots/spot-5.jpg' ),
				array( 'শিমুল বাগান', 'মৌসুমভেদে', 'spots/spot-6.jpg' ),
				array( 'খরচার হাওর', 'সূর্যাস্তের স্পট', 'spots/spot-7.jpg' ),
			);
			foreach ( $spots as $sn => $s ) :
				?>
				<a class="spot reveal" href="<?php echo esc_url( bhela_page_url( 'gallery' ) ); ?>">
					<img src="<?php echo esc_url( bhela_img( 'spot_' . ( $sn + 1 ), $s[2], 'bhela-card' ) ); ?>" alt="<?php echo esc_attr( $s[0] ); ?>" loading="lazy">
					<span class="spot__label"><strong><?php echo esc_html( $s[0] ); ?></strong><span><?php echo esc_html( $s[1] ); ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- WHY BHELA -->
<section class="section" id="why">
	<div class="container split">
		<div class="reveal">
			<span class="eyebrow">কেন ভেলা</span>
			<h2 class="section-title">শুধু বোট নয়,<br>সম্পূর্ণ প্রিমিয়াম এক্সপেরিয়েন্স</h2>
			<p class="section-lead">টাঙ্গুয়ার হাওরে অসংখ্য নৌযান আছে — ভেলা তৈরি হয়েছে আরাম, নিরাপত্তা আর মানসম্মত সেবাকে কেন্দ্র করে।</p>
			<ul class="checklist">
				<li>Infinity Glass Window</li>
				<li>Rooftop Lounge ও Dining</li>
				<li>Side Passage Design</li>
				<li>Family Privacy নিশ্চিত</li>
				<li>Corporate ও Full Boat বুকিং</li>
				<li>Full Moon 🌕 স্পেশাল ট্রিপ</li>
			</ul>
			<div style="display:flex;gap:1rem;flex-wrap:wrap">
				<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুকিং করুন</a>
				<a class="btn btn--ghost-dark" href="<?php echo esc_url( bhela_wa_link( 'আসসালামু আলাইকুম। Full Boat / Corporate বুকিং সম্পর্কে জানতে চাই।' ) ); ?>" target="_blank" rel="noopener">Corporate কোটেশন</a>
			</div>
		</div>
		<div class="split__media reveal">
			<img src="<?php echo esc_url( bhela_img( 'rooftop', 'boat/rooftop-1.jpg', 'bhela-card' ) ); ?>" alt="ভেলার রুফটপ লাউঞ্জ" loading="lazy">
		</div>
	</div>
</section>

<!-- REVIEWS -->
<section class="section section--dark has-wave-top" id="reviews">
	<div class="container">
		<div class="center">
			<span class="eyebrow reveal">অতিথিদের কথা</span>
			<h2 class="section-title reveal">যারা ঘুরে এসেছেন</h2>
		</div>
		<div class="reviews-grid" style="margin-top:2.5rem">
			<?php
			$bhela_reviews = function_exists( 'bhela_bm_get_reviews' ) ? bhela_bm_get_reviews( 3 ) : array();
			if ( ! $bhela_reviews ) {
				$bhela_reviews = array(
					array( 'name' => 'রাশেদুল ইসলাম', 'text' => 'পরিবার নিয়ে গিয়েছিলাম — কেবিন, খাবার, ক্রুদের ব্যবহার সবকিছু এক কথায় অসাধারণ। বাচ্চাদের নিয়ে এত নিরাপদ লেগেছে!', 'rating' => 5, 'subtitle' => 'Family Trip · Dhaka' ),
					array( 'name' => 'সাবরিনা আক্তার', 'text' => 'অফিসের ২৮ জনের টিম নিয়ে Full Boat নিয়েছিলাম। রুফটপে টিম আড্ডা আর হাওরের সূর্যাস্ত — best team retreat ever!', 'rating' => 5, 'subtitle' => 'Corporate Tour' ),
					array( 'name' => 'তানভীর হাসান', 'text' => 'Weekday অফারে বন্ধুরা মিলে গিয়েছিলাম। এই দামে AC কেবিন, এত খাবার আর ৭টা স্পট — টাঙ্গুয়ায় এর চেয়ে ভালো ডিল নেই।', 'rating' => 5, 'subtitle' => 'Friends Group' ),
				);
			}
			foreach ( $bhela_reviews as $r ) :
				?>
				<div class="review reveal">
					<div class="review__stars"><?php echo esc_html( str_repeat( '★', max( 1, (int) $r['rating'] ) ) ); ?></div>
					<p>"<?php echo esc_html( $r['text'] ); ?>"</p>
					<div class="review__who">
						<span class="avatar"><?php echo esc_html( mb_substr( $r['name'], 0, 1 ) ); ?></span>
						<div><strong><?php echo esc_html( $r['name'] ); ?></strong><span><?php echo esc_html( $r['subtitle'] ); ?></span></div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- FAQ TEASER -->
<section class="section" id="faq-teaser">
	<div class="container">
		<div class="center">
			<span class="eyebrow reveal">সাধারণ প্রশ্ন</span>
			<h2 class="section-title reveal">বুকিংয়ের আগে যা জানা দরকার</h2>
		</div>
		<div class="faq-list" style="margin-top:2.2rem">
			<details class="faq-item reveal"><summary>জনপ্রতি রেট কীভাবে নির্ধারণ হয়?</summary><div class="faq-item__body">এক কেবিনে কতজন থাকবেন তার ভিত্তিতে। যত বেশি সদস্য একই কেবিনে, জনপ্রতি খরচ তত কম। Weekday ট্রিপে ২০% পর্যন্ত ছাড়।</div></details>
			<details class="faq-item reveal"><summary>বুকিং কনফার্ম করতে কত টাকা অগ্রিম দিতে হয়?</summary><div class="faq-item__body">মোট প্যাকেজ মূল্যের ৫০% অগ্রিম (bKash/Nagad/Bank)। বাকি ৫০% অনবোর্ড হওয়ার সময়। অগ্রিম পাওয়ার পরই বুকিং Confirmed হয়।</div></details>
			<details class="faq-item reveal"><summary>অপরিচিত কারও সাথে কেবিন শেয়ার করতে হবে?</summary><div class="faq-item__body">না। Privacy, Security ও Family Comfort-এর জন্য শুধুমাত্র নিজের গ্রুপের মধ্যেই কেবিন শেয়ারিং হয়।</div></details>
			<details class="faq-item reveal"><summary>শিশুদের জন্য চার্জ কত?</summary><div class="faq-item__body">০–৪ বছর সম্পূর্ণ ফ্রি, ৪–৮ বছর ফিক্সড ৳৫,০০০ (Weekday ছাড় প্রযোজ্য নয়), ৯ বছর বা তার বেশি হলে পূর্ণ চার্জ।</div></details>
		</div>
		<p class="center" style="margin-top:1.6rem"><a class="btn btn--ghost-dark" href="<?php echo esc_url( bhela_page_url( 'faq' ) ); ?>">সবগুলো প্রশ্ন দেখুন (৬০+)</a></p>
	</div>
</section>

<!-- CTA -->
<section class="section" style="padding-top:0">
	<div class="container">
		<div class="cta-banner reveal">
			<h2>এই বর্ষায় হাওর ডাকছে</h2>
			<p>তারিখ আর অতিথি সংখ্যা জানান — ২ মিনিটে রেটসহ বিস্তারিত পেয়ে যাবেন WhatsApp-এ।</p>
			<div class="btn-row">
				<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">অনলাইনে বুক করুন</a>
				<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">WhatsApp-এ কথা বলুন</a>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
