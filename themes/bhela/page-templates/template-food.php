<?php
/**
 * Template Name: BHELA — Food Menu
 *
 * @package Bhela
 */

get_header();

$day1 = array(
	'🥤 Welcome Drinks'   => array( 'ওয়েলকাম জুস', 'বিশুদ্ধ পানীয় পানি' ),
	'🍛 সকালের নাস্তা'      => array( 'ভুনা খিচুড়ি', 'ডিম কারি', 'সালাদ', 'পেঁয়াজ-মরিচ ভর্তা', 'চাটনি' ),
	'☕ সকালের স্ন্যাকস'     => array( 'চা / কফি', 'বেকারি আইটেম', 'সিজনাল ফল' ),
	'🍽️ দুপুরের খাবার'      => array( 'সাদা ভাত', 'হাওরের বড় মাছ', 'দেশি মুরগির ঝাল কারি', 'আলু ভর্তা', 'মাছ ভর্তা', 'শুটকি ভর্তা', 'ডাল', 'মৌসুমি সবজি', 'সালাদ' ),
	'🍝 বিকেলের নাস্তা'     => array( 'পাস্তা', 'চা / কফি' ),
	'🌙 রাতের খাবার'        => array( 'সাদা ভাত', 'দেশি হাঁসের মাংস', 'ধনিয়াপাতা ভর্তা', 'শুটকি ভর্তা', 'ডাল', 'মৌসুমি সবজি', 'সালাদ' ),
);
$day2 = array(
	'🍛 সকালের নাস্তা'   => array( 'হাওরের সুস্বাদু আকনি', 'বেগুন ভাজা', 'ডিম ভাজা', 'পেঁয়াজ-মরিচ ভর্তা', 'চাটনি', 'সালাদ' ),
	'☕ সকালের স্ন্যাকস'  => array( 'চা / কফি', 'বেকারি আইটেম' ),
	'🍽️ দুপুরের খাবার'   => array( 'সাদা ভাত', 'দেশি মুরগি রোস্ট', 'হাওরের পাঁচ মিশালি মাছ', 'আলু ভর্তা', 'মিক্সড সবজি', 'ডাল', 'সালাদ' ),
	'🍜 বিকেলের নাস্তা'  => array( 'নুডলস', 'চা / কফি', 'সিজনাল ফল' ),
);
?>
<section class="page-hero"><div class="container">
	<h1>খাবার মেনু</h1>
	<p>২ দিন ১ রাতের স্ট্যান্ডার্ড ফুড মেনু — দেশীয় স্বাদের সমৃদ্ধ খাবার, হাওরের ঐতিহ্যবাহী রান্না এবং আন্তরিক আতিথেয়তা।</p>
</div></section>

<section class="section"><div class="container">
	<div class="menu-day reveal">
		<h3>🌿 প্রথম দিন</h3>
		<div class="menu-meals">
			<?php foreach ( $day1 as $meal => $items ) : ?>
				<div class="menu-meal"><h4><?php echo esc_html( $meal ); ?></h4><ul>
					<?php foreach ( $items as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="menu-day reveal">
		<h3>🌅 দ্বিতীয় দিন</h3>
		<div class="menu-meals">
			<?php foreach ( $day2 as $meal => $items ) : ?>
				<div class="menu-meal"><h4><?php echo esc_html( $meal ); ?></h4><ul>
					<?php foreach ( $items as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="menu-day reveal" style="background:linear-gradient(135deg,var(--ink),var(--ink-2))">
		<h3 style="color:#fff;border-color:rgba(255,255,255,.2)">⭐ ভেলার খাবারের বিশেষ আকর্ষণ</h3>
		<ul class="checklist" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
			<li>Welcome Drinks</li><li>হাওরের তাজা মাছ</li><li>দেশি মুরগি ও হাঁস</li>
			<li>BBQ আয়োজন (বিশেষ প্যাকেজে)</li><li>বিভিন্ন ভর্তা ও শুটকি</li><li>মৌসুমি সবজি ও ডাল</li>
			<li>সিজনাল ফল</li><li>সারাদিন আনলিমিটেড চা-কফি</li>
		</ul>
	</div>

	<p style="color:var(--text-soft);font-size:.92rem"><strong>বি.দ্র.:</strong> মৌসুম, আবহাওয়া, বাজারে উপকরণের প্রাপ্যতা এবং অতিথির বিশেষ অনুরোধ অনুযায়ী মেনুতে সামান্য পরিবর্তন হতে পারে। তবে খাবারের মান, পরিমাণ ও আতিথেয়তায় কোনো আপস করা হয় না। নিরামিষ প্রয়োজন হলে আগে জানালে ব্যবস্থা করা যায়।</p>
	<p class="center" style="margin-top:2rem"><a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুকিং করুন</a></p>
</div></section>
<?php get_footer(); ?>
