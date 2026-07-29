<?php
/**
 * Template Name: BHELA — Booking Guide
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Houseboat categories available on Tanguar Haor. 'bhela' marks the tier BHELA operates in.
$types = array(
	array(
		'name'    => 'Budget Houseboat',
		'cabin'   => 'সাধারণ কেবিন বা ওপেন স্লিপিং স্পেস',
		'wash'    => 'শেয়ারড ওয়াশরুম',
		'power'   => 'সীমিত বিদ্যুৎ',
		'for'     => 'কম বাজেটের ছোট গ্রুপ',
		'bhela'   => false,
	),
	array(
		'name'    => 'Standard / Mid-range',
		'cabin'   => 'আলাদা কেবিন (Non-AC)',
		'wash'    => 'কিছু ক্ষেত্রে Attached',
		'power'   => 'জেনারেটর সুবিধা',
		'for'     => 'পরিবার ও বন্ধুদের গ্রুপ',
		'bhela'   => false,
	),
	array(
		'name'    => 'Premium AC Houseboat',
		'cabin'   => 'সম্পূর্ণ AC Family Cabin',
		'wash'    => 'Attached Washroom',
		'power'   => '২৪ ঘণ্টা বিদ্যুৎ + Generator Backup',
		'for'     => 'Family, Corporate ও Premium Group Tour',
		'bhela'   => false,
	),
	array(
		'name'    => 'Luxury Houseboat',
		'cabin'   => 'বড় লাউঞ্জ, প্রিমিয়াম ইন্টেরিয়র ও সম্পূর্ণ AC Family Cabin',
		'wash'    => 'Attached Washroom',
		'power'   => '২৪ ঘণ্টা বিদ্যুৎ + Generator Backup',
		'for'     => 'Luxury Experience, Family, Corporate Retreat ও Special Event',
		'bhela'   => true,
	),
);

// The 8-point pre-booking checklist. Each entry: emoji, question, list of things to verify.
$checks = array(
	array(
		'🦺',
		'নিরাপত্তা ব্যবস্থা ঠিক আছে কি না কীভাবে যাচাই করবো?',
		array(
			'যাত্রীসংখ্যা অনুযায়ী পর্যাপ্ত Life Jacket',
			'Fire Extinguisher ও First Aid Box',
			'প্রশিক্ষিত Captain ও Crew',
			'জরুরি অবস্থায় যোগাযোগের নম্বর ও পরিকল্পনা',
		),
	),
	array(
		'🛏️',
		'কেবিনের মান কীভাবে বুঝবো?',
		array(
			'AC না Non-AC — এবং AC কতক্ষণ চালু থাকে',
			'Attached Bathroom আছে কি না',
			'বিছানা ও বিছানার চাদর পরিষ্কার কি না',
			'পর্যাপ্ত আলো ও বায়ু চলাচল',
		),
	),
	array(
		'🔌',
		'বিদ্যুৎ ব্যবস্থা সম্পর্কে কী জেনে নেবো?',
		array(
			'Generator Backup আছে কি না',
			'মোবাইল চার্জিং সুবিধা',
			'রাতে বিদ্যুৎ বন্ধ হয়ে যায় কি না',
		),
	),
	array(
		'🍽️',
		'খাবার নিয়ে কী কী জিজ্ঞেস করবো?',
		array(
			'ট্রিপে কয় বেলা প্রধান খাবার ও কয় বেলা Snacks',
			'বিশুদ্ধ পানির ব্যবস্থা',
			'মেনুর বৈচিত্র্য ও পরিবর্তনের সুযোগ',
			'বিশেষ খাদ্য চাহিদা (শিশু, অসুস্থ, নির্দিষ্ট খাবার) পূরণ হয় কি না',
		),
	),
	array(
		'🗺️',
		'ভ্রমণ রুটে কোন কোন জায়গা থাকে?',
		array(
			'টাঙ্গুয়ার হাওর ও ওয়াচ টাওয়ার',
			'যাদুকাটা নদী ও বারিক্কা টিলা',
			'নীলাদ্রি লেক ও খরচার হাওর',
			'শিমুল বাগান (মৌসুমভেদে)',
			'কোন স্পট প্যাকেজে অন্তর্ভুক্ত আর কোনটি Optional — লিখিতভাবে জেনে নিন',
		),
	),
	array(
		'👨‍🍳',
		'Crew ও Hospitality কেন গুরুত্বপূর্ণ?',
		array(
			'অভিজ্ঞ Captain — হাওরের পথ ও আবহাওয়া চেনেন',
			'দক্ষ Chef',
			'আন্তরিক Service Staff',
			'ট্রিপ চলাকালীন Guest Support',
		),
	),
	array(
		'📸',
		'বাস্তব ছবি ও Review কীভাবে যাচাই করবো?',
		array(
			'সাম্প্রতিক ছবি চেয়ে নিন — পুরোনো বা এডিট করা ছবি নয়',
			'বোটের ভিডিও চাইতে পারেন',
			'পূর্ববর্তী অতিথিদের Review পড়ুন',
		),
	),
	array(
		'📝',
		'Booking Policy-তে কী কী দেখে নেবো?',
		array(
			'কত শতাংশ Advance দিতে হবে',
			'Cancellation Policy',
			'Refund Policy',
			'লিখিত Booking Confirmation পাওয়া যায় কি না',
		),
	),
);

// 13 factors that move the package rate.
$factors = array(
	'Houseboat-এর ক্যাটাগরি (Budget / Standard / Premium / Luxury)',
	'AC না Non-AC',
	'কেবিনের সংখ্যা ও আকার',
	'Attached Washroom আছে কি না',
	'মোট যাত্রীর সংখ্যা — গ্রুপ বড় হলে জনপ্রতি খরচ কমে',
	'Weekday নাকি Weekend',
	'সরকারি ছুটি বা Holiday Season',
	'খাবারের মান ও সংখ্যা',
	'Trip Duration (১ দিন / ২ দিন ১ রাত / আরও বেশি)',
	'Generator ও বিদ্যুৎ সুবিধা',
	'নিরাপত্তা ব্যবস্থা',
	'Crew ও Service Quality',
	'ভ্রমণ রুট ও অতিরিক্ত সুবিধা (Wi-Fi, BBQ, Kayak, Photography ইত্যাদি)',
);

// What a suspiciously cheap package usually leaves out.
$risks = array(
	'পর্যাপ্ত নিরাপত্তা সরঞ্জাম',
	'পরিষ্কার কেবিন ও বিছানা',
	'মানসম্মত খাবার',
	'নির্ভরযোগ্য Generator',
	'অভিজ্ঞ Captain',
	'আন্তরিক Service',
	'স্বচ্ছ Booking Policy',
);

// BHELA's own feature list.
$bhela_features = array(
	'Premium Full AC Family Cabin',
	'Attached Washroom',
	'Infinity Style Glass Window',
	'বড় লাউঞ্জ',
	'Rooftop Lounge',
	'Open Dining Space',
	'Wi-Fi Service',
	'২৪ ঘণ্টা বিদ্যুৎ',
	'প্রশিক্ষিত Crew',
	'নিরাপত্তা সরঞ্জাম',
	'৫ বেলা প্রধান খাবার ও ৪ বেলা Snacks',
	'Family & Group Friendly পরিবেশ',
	'স্বচ্ছ Booking Policy',
);

// FAQPage schema from the checklist — each check is a real guest question.
$schema_faq = array();
foreach ( $checks as $c ) {
	$schema_faq[] = array(
		'@type'          => 'Question',
		'name'           => $c[1],
		'acceptedAnswer' => array( '@type' => 'Answer', 'text' => implode( '। ', $c[2] ) . '।' ),
	);
}
$schema_graph = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'            => 'Article',
			'headline'         => 'টাঙ্গুয়ার হাওরে হাউসবোট বুকিং গাইড',
			'description'      => 'টাঙ্গুয়ার হাওরে Houseboat বুক করার আগে নিরাপত্তা, কেবিন, বিদ্যুৎ, খাবার, রুট, Crew ও Booking Policy যাচাইয়ের সম্পূর্ণ গাইড।',
			'inLanguage'       => 'bn-BD',
			'mainEntityOfPage' => get_permalink(),
			'author'           => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
			'publisher'        => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
		),
		array( '@type' => 'FAQPage', 'mainEntity' => $schema_faq ),
	),
);
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema_graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<section class="page-hero"><div class="container">
	<h1>টাঙ্গুয়ার হাওরে হাউসবোট বুকিং গাইড</h1>
	<p>বুকিংয়ের আগে যা যা যাচাই করা জরুরি — নিরাপত্তা, কেবিন, খাবার, রুট আর রেট। শুধু কম দাম নয়, পুরো অভিজ্ঞতা দেখে সিদ্ধান্ত নিন।</p>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>


<section class="section"><div class="container">
	<div class="entry-content" style="margin-bottom:2.6rem">
		<p>টাঙ্গুয়ার হাওর বাংলাদেশের অন্যতম বৃহৎ জলাভূমি এবং প্রকৃতিপ্রেমীদের কাছে অনন্য এক গন্তব্য। বর্তমানে এখানে বিভিন্ন ধরনের Houseboat পরিচালিত হচ্ছে — এবং তাদের সুবিধা, সেবার মান ও রেটে উল্লেখযোগ্য পার্থক্য রয়েছে। একটি নিরাপদ ও আরামদায়ক ভ্রমণের জন্য নিচের বিষয়গুলো মিলিয়ে সিদ্ধান্ত নিন।</p>
	</div>

	<h2 class="section-title reveal">🛥️ টাঙ্গুয়ার হাওরে কী কী ধরনের Houseboat আছে?</h2>
	<p class="section-lead reveal">প্রতিটি ক্যাটাগরির সুবিধা ও উপযোগিতা আলাদা। নিজের গ্রুপ ও বাজেট অনুযায়ী মিলিয়ে নিন।</p>

	<div class="guide-table-wrap reveal">
		<table class="sched-table guide-table">
			<thead>
				<tr>
					<th>ক্যাটাগরি</th>
					<th>কেবিন</th>
					<th>ওয়াশরুম</th>
					<th>বিদ্যুৎ</th>
					<th>উপযোগী কার জন্য</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $types as $t ) : ?>
				<tr<?php echo $t['bhela'] ? ' class="guide-table__row--bhela"' : ''; ?>>
					<td>
						<strong><?php echo esc_html( $t['name'] ); ?></strong>
						<?php if ( $t['bhela'] ) : ?>
							<span class="tag tag--weekend guide-badge">ভেলা এই ক্যাটাগরিতে</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $t['cabin'] ); ?></td>
					<td><?php echo esc_html( $t['wash'] ); ?></td>
					<td><?php echo esc_html( $t['power'] ); ?></td>
					<td><?php echo esc_html( $t['for'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="guide-note">ভেলার কেবিন, ধারণক্ষমতা ও জনপ্রতি রেট দেখতে <a href="<?php echo esc_url( bhela_page_url( 'cabins' ) ); ?>">কেবিন ও রেট</a> পেজে যান।</p>
</div></section>


<section class="section section--sand"><div class="container">
	<h2 class="section-title reveal">✅ বুকিং করার আগে ৮টি বিষয় যাচাই করুন</h2>
	<p class="section-lead reveal">যেকোনো Houseboat বুক করার আগে এই প্রশ্নগুলো করুন — উত্তর স্পষ্ট না হলে সময় নিন।</p>

	<div class="faq-list" style="margin-inline:0;max-width:100%">
		<?php foreach ( $checks as $i => $c ) : ?>
			<details class="faq-item reveal"<?php echo 0 === $i ? ' open' : ''; ?>>
				<summary><?php echo esc_html( $c[0] . ' ' . $c[1] ); ?></summary>
				<div class="faq-item__body">
					<ul class="guide-points">
						<?php foreach ( $c[2] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
	<p class="guide-note">ভেলার বুকিং, পেমেন্ট, রিফান্ড ও রিসিডিউল শর্ত পুরোটা আছে <a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">বুকিং নীতিমালা</a> পেজে।</p>
</div></section>


<section class="section" id="rate-factors"><div class="container">
	<h2 class="section-title reveal">💰 Houseboat-এর রেট কেন ভিন্ন হয়?</h2>
	<p class="section-lead reveal">একই তারিখে দুই বোটের রেট আলাদা হওয়া স্বাভাবিক। সাধারণত নিচের বিষয়গুলো রেট নির্ধারণ করে।</p>

	<ul class="checklist reveal">
		<?php foreach ( $factors as $factor ) : ?>
			<li><?php echo esc_html( $factor ); ?></li>
		<?php endforeach; ?>
	</ul>

	<div class="entry-content" style="margin-inline:0">
		<p>ভেলায় Weekend ও সরকারি ছুটি ছাড়া নির্দিষ্ট ট্রিপে <strong>২০% পর্যন্ত Weekday ছাড়</strong> প্রযোজ্য। কোন তারিখে কোন রেট চলছে তা দেখতে <a href="<?php echo esc_url( bhela_page_url( 'schedule' ) ); ?>">ট্রিপ সিডিউল</a> দেখুন, আর জনপ্রতি হিসাব করতে <a href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুকিং পেজের লাইভ ক্যালকুলেটর</a> ব্যবহার করুন।</p>
	</div>

	<div class="guide-warn reveal">
		<h3>⚠️ শুধু কম দাম দেখে সিদ্ধান্ত নেবেন না</h3>
		<p>অনেক কম মূল্যের অফারে নিচের গুরুত্বপূর্ণ বিষয়গুলো অনুপস্থিত থাকতে পারে:</p>
		<ul class="guide-points">
			<?php foreach ( $risks as $risk ) : ?>
				<li><?php echo esc_html( $risk ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="guide-warn__note">শুরুতে কিছু টাকা কম লাগলেও পুরো ভ্রমণের অভিজ্ঞতা ক্ষতিগ্রস্ত হতে পারে।</p>
	</div>
</div></section>


<section class="section section--sand"><div class="container">
	<h2 class="section-title reveal">🛶 কেন BHELA – The Haor Exclusive?</h2>
	<p class="section-lead reveal">ভেলা তাদের জন্য, যারা শুধু একটি Houseboat নয় — একটি নিরাপদ, আরামদায়ক ও মানসম্মত Haor Experience খুঁজছেন।</p>

	<ul class="checklist reveal">
		<?php foreach ( $bhela_features as $feature ) : ?>
			<li><?php echo esc_html( $feature ); ?></li>
		<?php endforeach; ?>
	</ul>

	<blockquote class="guide-quote reveal">
		<?php echo wp_kses_post( 'ভেলার আকর্ষণ শুধু Houseboat নয়, টাঙ্গুয়ার হাওরের অপরূপ প্রকৃতি।<br><strong>ভেলায় আমরা সেবা দিই — বাকিটা হাওর নিজেই করে।</strong>' ); ?>
	</blockquote>

	<div class="cta-banner reveal" style="margin-top:3rem">
		<h2>গাইড পড়া শেষ? এবার তারিখ দেখে নিন</h2>
		<p>তারিখ দিলেই সাথে সাথে রেট দেখতে পাবেন। প্রশ্ন থাকলে সরাসরি WhatsApp-এ লিখুন — আমরা সবসময় পাশে আছি।</p>
		<div class="btn-row">
			<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">🗓️ বুক করুন</a>
			<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link( 'বুকিং গাইড পড়ে যোগাযোগ করছি। ট্রিপ সম্পর্কে জানতে চাই।' ) ); ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
			<a class="btn btn--ghost" href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>">📞 <?php echo esc_html( bhela_contact( 'phone_1' ) ); ?></a>
		</div>
		<p style="margin:1.6rem auto 0;font-size:.9rem">আরও প্রশ্ন? <a href="<?php echo esc_url( bhela_page_url( 'faq' ) ); ?>" style="color:var(--gold)">সাধারণ প্রশ্ন (FAQ)</a> দেখুন।</p>
	</div>
</div></section>
<?php get_footer(); ?>
