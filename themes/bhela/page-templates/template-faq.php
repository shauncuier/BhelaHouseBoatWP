<?php
/**
 * Template Name: BHELA — FAQ
 *
 * @package Bhela
 */

get_header();

$faqs = array(
	'🌿 টাঙ্গুয়ার হাওর সম্পর্কে' => array(
		array( 'টাঙ্গুয়ার হাওর কোথায়?', 'সুনামগঞ্জ জেলার তাহিরপুর ও ধর্মপাশা উপজেলায় অবস্থিত বাংলাদেশের অন্যতম বৃহৎ মিঠা পানির জলাভূমি।' ),
		array( 'হাওর ভ্রমণের সেরা সময় কখন?', 'জুন থেকে সেপ্টেম্বর — বর্ষাকাল। এ সময় পুরো হাওর বিশাল জলরাশিতে পরিণত হয় এবং জাদুকাটা, নীলাদ্রি, বারিক্কা টিলা ও মেঘালয়ের পাহাড় সবচেয়ে সুন্দর রূপে দেখা যায়।' ),
		array( 'কোন কোন জায়গায় ঘুরানো হয়?', 'টাঙ্গুয়ার হাওর, নীলাদ্রি লেক, জাদুকাটা নদী, বারিক্কা টিলা, ওয়াচ টাওয়ার, খরচার হাওর এবং শিমুল বাগান (মৌসুমভেদে)।' ),
	),
	'🛶 ভেলা সম্পর্কে' => array(
		array( 'ভেলা কী?', 'BHELA একটি Premium Family & Group Friendly Houseboat — থাকা, খাওয়া ও দর্শনীয় স্থান ভ্রমণ একসাথে প্যাকেজ আকারে।' ),
		array( 'ভেলায় কয়টি কেবিন আছে?', '৬টি বড় Family Cabin। প্রতিটিতে ২–৬ জন পর্যন্ত থাকা যায়। লবিসহ সর্বোচ্চ ধারণক্ষমতা ৪০ জন।' ),
		array( 'কেবিনে কী কী সুবিধা আছে?', 'AC, Attached Washroom, Double Bed, Infinity Glass Window, পর্যাপ্ত আলো ও ২৪ ঘণ্টা বিদ্যুৎ।' ),
		array( 'ভেলা অন্য হাউসবোট থেকে কেন আলাদা?', 'বড় কেবিন, AC/Non-AC, Attached Washroom, প্রশিক্ষিত স্টাফ, দেশীয় খাবার, Rooftop Lounge এবং Family Privacy — আমরা শুধু ভ্রমণ নয়, নিরাপদ ও মানসম্মত অভিজ্ঞতা দিই।' ),
	),
	'💰 প্যাকেজ ও রেট' => array(
		array( 'জনপ্রতি রেট কীভাবে নির্ধারণ হয়?', 'এক কেবিনে কতজন থাকবেন তার ভিত্তিতে। যত বেশি সদস্য একই কেবিনে থাকবেন, জনপ্রতি খরচ তত কম হবে।' ),
		array( 'Weekday Offer আছে?', 'হ্যাঁ। Weekend ও সরকারি ছুটি ব্যতীত নির্দিষ্ট ট্রিপে ২০% পর্যন্ত ডিসকাউন্ট।' ),
		array( 'রেটে কী কী অন্তর্ভুক্ত?', 'থাকা, সকল খাবার, হাউসবোট ভ্রমণ ও নির্ধারিত দর্শনীয় স্থান।' ),
		array( 'কোন খরচ অন্তর্ভুক্ত নয়?', 'কিছু স্পটের Entry Fee, Local Boat Charge (যেখানে হাউসবোট যেতে পারে না) এবং ব্যক্তিগত খরচ।' ),
		array( 'বড় গ্রুপ হলে বিশেষ ডিসকাউন্ট পাওয়া যায়?', 'হ্যাঁ। Full Boat Reservation, Corporate Team, Educational Tour ও বড় গ্রুপের জন্য কাস্টম রেট ও ডিসকাউন্ট আছে।' ),
	),
	'📝 বুকিং ও পেমেন্ট' => array(
		array( 'কীভাবে বুকিং করবো?', '১) তারিখ নির্বাচন → ২) প্যাকেজ/কেবিন নির্বাচন → ৩) ৫০% Advance প্রদান → ৪) Confirmation → ৫) ভ্রমণ।' ),
		array( 'কত টাকা Advance দিতে হয়?', 'মোট প্যাকেজ মূল্যের ৫০%। বাকি ৫০% অনবোর্ড হওয়ার সময়।' ),
		array( 'Payment Method কী কী?', 'bKash, Nagad ও Bank Transfer।' ),
		array( 'WhatsApp-এ সরাসরি বুকিং করা যায়?', 'হ্যাঁ। তারিখ, গ্রুপ সাইজ ও প্যাকেজ কনফার্ম করে অগ্রিম দিলেই বুকিং সম্পন্ন হয়।' ),
		array( 'Booking Confirm হলে কী কী তথ্য পাঠানো হয়?', 'Booking Confirmation, Trip Date, Reporting Time, Boarding Location, Payment Status, প্রয়োজনীয় নির্দেশনা ও জরুরি নম্বর।' ),
	),
	'❌ ক্যানসেলেশন ও রিসিডিউল' => array(
		array( 'Booking Cancel করলে Refund পাবো?', '২১+ দিন আগে জানালে Advance-এর ৫০% Refund। ৮–২০ দিনে Cash Refund নেই (Future Credit/Reschedule বিবেচনায়)। ৭ দিনের কম সময়ে কোনো Refund নেই।' ),
		array( 'তারিখ পরিবর্তন করা যাবে?', 'ট্রিপের কমপক্ষে ৭ দিন আগে লিখিতভাবে জানালে শর্তসাপেক্ষে একবার Reschedule সম্ভব (সিট ও তারিখ প্রাপ্যতা অনুযায়ী)।' ),
		array( 'খারাপ আবহাওয়ায় কী হবে?', 'সাধারণ বৃষ্টিতে ট্রিপ হয়। প্রাকৃতিক দুর্যোগ বা নিষেধাজ্ঞায় বিকল্প তারিখে Reschedule-এর সুযোগ দেওয়া হয়।' ),
	),
	'👨‍👩‍👧‍👦 পরিবার ও নিরাপত্তা' => array(
		array( 'অপরিচিত কারও সাথে কেবিন শেয়ার করতে হবে?', 'না। Privacy, Security ও Family Comfort-এর জন্য শুধুমাত্র নিজের গ্রুপের মধ্যে শেয়ারিং।' ),
		array( 'শিশুদের জন্য কী Policy?', '০–৪ বছর ফ্রি, ৪–৮ বছর ফিক্সড ৳৫,০০০ (Weekday ছাড় প্রযোজ্য নয়), ৯+ বছর পূর্ণ চার্জ।' ),
		array( 'Life Jacket ও প্রশিক্ষিত স্টাফ আছে?', 'হ্যাঁ, উভয়ই আছে। রাতেও নিরাপদ — বোট নিরাপদ গতিতে পরিচালিত হয়।' ),
		array( 'মেডিকেল জরুরি অবস্থায় কী হবে?', 'টিম প্রাথমিক সহায়তা দেবে; প্রয়োজনে নিকটস্থ স্বাস্থ্যকেন্দ্রের সহায়তা নেওয়া হবে।' ),
	),
	'📸 অন্যান্য' => array(
		array( 'কী কী সঙ্গে আনতে হবে?', 'NID কপি, প্রয়োজনীয় ওষুধ, আরামদায়ক পোশাক (বর্ষায় রেইনকোট), গ্রিপযুক্ত জুতা, সানস্ক্রিন, টুপি, সানগ্লাস, পাওয়ার ব্যাংক।' ),
		array( 'মোবাইল নেটওয়ার্ক ও চার্জিং আছে?', 'অধিকাংশ এলাকায় নেটওয়ার্ক পাওয়া যায় (কিছু স্থানে দুর্বল)। বোটে চার্জিং সুবিধা আছে।' ),
		array( 'Drone ও Camera নেওয়া যাবে?', 'হ্যাঁ, সীমান্ত ও নিরাপত্তা বিধি মেনে।' ),
		array( 'Full Moon ট্রিপ কী?', 'পূর্ণিমার রাতে হাওরের জলে চাঁদের আলো — বিশেষ অভিজ্ঞতা। আবহাওয়া অনুকূলে থাকলে এটি হাওর ভ্রমণের অন্যতম আকর্ষণ।' ),
		array( 'Alcohol বা Loud Sound অনুমোদিত?', 'না। অন্য অতিথি, স্থানীয় জনগণ ও পরিবেশের কথা বিবেচনায় উভয়ই নিষিদ্ধ।' ),
	),
);

// FAQPage schema.
$schema_items = array();
foreach ( $faqs as $group ) {
	foreach ( $group as $qa ) {
		$schema_items[] = array(
			'@type'          => 'Question',
			'name'           => $qa[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
		);
	}
}
?>
<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema_items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<section class="page-hero"><div class="container">
	<h1>সাধারণ প্রশ্ন (FAQ)</h1>
	<p>বুকিংয়ের আগে যা যা জানা দরকার — সব প্রশ্নের উত্তর এক জায়গায়।</p>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>


<section class="section"><div class="container">
	<?php foreach ( $faqs as $group => $items ) : ?>
		<h2 style="font-size:1.4rem;margin:2.2rem 0 1rem" class="reveal"><?php echo esc_html( $group ); ?></h2>
		<div class="faq-list" style="margin-inline:0;max-width:100%">
			<?php foreach ( $items as $qa ) : ?>
				<details class="faq-item reveal"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq-item__body"><?php echo esc_html( $qa[1] ); ?></div></details>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<div class="cta-banner reveal" style="margin-top:3rem">
		<h2>আরও প্রশ্ন আছে?</h2>
		<p>সরাসরি আমাদের সাথে কথা বলুন — আমরা সবসময় পাশে আছি।</p>
		<div class="btn-row">
			<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
			<a class="btn btn--ghost" href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>">📞 <?php echo esc_html( bhela_contact( 'phone_1' ) ); ?></a>
		</div>
	</div>
</div></section>
<?php get_footer(); ?>
