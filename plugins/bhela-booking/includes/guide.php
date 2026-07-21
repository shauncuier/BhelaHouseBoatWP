<?php
/**
 * No-coder management guide — a friendly Bangla control panel inside wp-admin.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_guide_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		'Quick Guide',
		'🎯 Quick Guide',
		'edit_posts',
		'bhela-bm-guide',
		'bhela_bm_guide_page',
		0
	);
}
add_action( 'admin_menu', 'bhela_bm_guide_menu' );

function bhela_bm_guide_page() {
	$cards = array(
		array(
			'icon'  => '📊',
			'title' => 'ড্যাশবোর্ড — সব এক নজরে',
			'steps' => array(
				'বাঁ মেনুতে Bookings → 📊 Dashboard',
				'কতগুলো বুকিং, কত আয়, আসন্ন ট্রিপ ও সাম্প্রতিক কাজ এক পাতায়',
				'"Quick Actions" থেকে যেকোনো কাজ এক ক্লিকে শুরু করুন',
				'"Setup Checklist"-এ ⬜ থাকলে সেটি ঠিক করে নিন',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-dashboard' ),
			'btn'   => 'ড্যাশবোর্ড খুলুন',
		),
		array(
			'icon'  => '📋',
			'title' => 'নতুন বুকিং দেখুন ও কনফার্ম করুন',
			'steps' => array(
				'নতুন রিকোয়েস্ট এলে ইমেইলে জানতে পারবেন',
				'All Bookings → নাম-এ ক্লিক করুন',
				'অগ্রিম পেলে Paid Amount ও TXN ID লিখুন',
				'Status → Confirmed করে Update চাপুন — কাস্টমার অটো ইমেইল পাবে',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
			'btn'   => 'বুকিং লিস্ট খুলুন',
		),
		array(
			'icon'  => '🧾',
			'title' => 'ইনভয়েস প্রিন্ট / পাঠান',
			'steps' => array(
				'বুকিং খুলুন → ডানপাশে "View / Print Invoice"',
				'Print চেপে PDF সেভ করুন',
				'অথবা লিংকটি কপি করে WhatsApp-এ পাঠান',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
			'btn'   => 'বুকিং লিস্ট খুলুন',
		),
		array(
			'icon'  => '📅',
			'title' => 'ট্রিপের তারিখ ম্যানেজ করুন',
			'steps' => array(
				'"Add Trip" চেপে Start Date দিন',
				'End Date খালি রাখলে ২ দিন ১ রাত ধরা হয় — Full Boat/লম্বা ট্রিপে বাড়িয়ে দিন',
				'Label ও Days নিজে থেকেই বসে যায়',
				'ছুটির দিন হলে "ছুটি" টিক দিন — রেগুলার রেট বসবে (উইকডে ছাড় থাকবে না)',
				'সিট ভরলে Status → Filling Fast/Booked; মুছতে Delete টিক; শেষে Save',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-trips' ),
			'btn'   => 'ট্রিপ ক্যালেন্ডার খুলুন',
		),
		array(
			'icon'  => '💰',
			'title' => 'রেট ও পেমেন্ট নম্বর',
			'steps' => array(
				'কেবিন রেট বদলাতে টেবিলে নতুন দাম লিখুন',
				'শিশু (৪–৮) ফি ও অগ্রিমের শতাংশ এখানেই',
				'bKash/Nagad নম্বর ও QR ছবির লিংক এখানেই',
				'ছুটির দিন এখন ট্রিপ ক্যালেন্ডারে "ছুটি" টিক দিয়ে ঠিক হয়',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings' ),
			'btn'   => 'সেটিংস খুলুন',
		),
		array(
			'icon'  => '🖼️',
			'title' => 'গ্যালারির ছবি যোগ / বদল করুন',
			'steps' => array(
				'একসাথে অনেক ছবি: Bookings → 🖼️ Bulk Upload → "ছবি বাছাই করুন"',
				'একটা একটা করে: 🖼️ Gallery → নতুন ছবি → Featured Image দিন',
				'ক্যাপশন = Title, ক্যাটাগরি ও ক্রম (Order) ঠিক করুন',
				'পেমেন্ট QR ছবি Media-তে আপলোড করে URL Settings-এ পেস্ট করুন',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-gallery-bulk' ),
			'btn'   => 'একসাথে ছবি যোগ করুন',
		),
		array(
			'icon'  => '⭐',
			'title' => 'গেস্ট রিভিউ যোগ / বদল করুন',
			'steps' => array(
				'বাঁ মেনুতে All Reviews → Add New',
				'Title-এ অতিথির নাম, নিচে রিভিউ লেখা',
				'ডানপাশে স্টার রেটিং ও Trip Type দিন',
				'Publish চাপলেই হোমপেজে দেখাবে',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_review' ),
			'btn'   => 'রিভিউ ম্যানেজ করুন',
		),
		array(
			'icon'  => '📋',
			'title' => 'কাজ ঠিকমতো হলো কিনা দেখুন',
			'steps' => array(
				'Bookings → Activity Log',
				'বুকিং, ইমেইল, SMS, ট্রিপ ও সেটিংসের সব রেকর্ড এখানে',
				'✅ মানে সফল, ❌ মানে সমস্যা — উপরে নতুনটা',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-log' ),
			'btn'   => 'অ্যাক্টিভিটি লগ খুলুন',
		),
		array(
			'icon'  => '✏️',
			'title' => 'পেজের লেখা বদলান',
			'steps' => array(
				'Pages → যে পেজ বদলাবেন সেটি Edit করুন',
				'Elementor থাকলে "Edit with Elementor" — টেনে টেনে ডিজাইন',
				'হোমপেজের মূল লেখা: Appearance → Customize → BHELA Homepage',
			),
			'link'  => admin_url( 'edit.php?post_type=page' ),
			'btn'   => 'পেজ লিস্ট খুলুন',
		),
		array(
			'icon'  => '📞',
			'title' => 'ফোন / WhatsApp নম্বর বদলান',
			'steps' => array(
				'Bookings → Settings-এ নম্বর বদলান',
				'পুরো ওয়েবসাইট, ফর্ম, ইনভয়েস — সব জায়গায় অটো আপডেট',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings' ),
			'btn'   => 'সেটিংস খুলুন',
		),
	);
	?>
	<div class="wrap">
		<h1 style="font-size:1.8em">🛶 ভেলা ওয়েবসাইট — সহজ ম্যানেজমেন্ট গাইড</h1>
		<p style="font-size:14px;max-width:720px">কোনো কোডিং লাগবে না। নিচের যেকোনো কাজের বাটনে ক্লিক করুন, ধাপগুলো অনুসরণ করুন। কোথাও আটকে গেলে ডেভেলপারকে জানান: <a href="https://3s-soft.com" target="_blank">3s-Soft</a></p>
		<div style="column-width:300px;column-gap:14px;max-width:1280px;margin-top:16px">
			<?php foreach ( $cards as $c ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-left:5px solid #137A74;border-radius:10px;padding:14px 16px;break-inside:avoid;-webkit-column-break-inside:avoid;margin-bottom:14px">
					<h2 style="margin:0 0 8px;font-size:15px"><?php echo esc_html( $c['icon'] . ' ' . $c['title'] ); ?></h2>
					<ol style="margin:0 0 12px;padding-left:20px;font-size:13px;line-height:1.6">
						<?php foreach ( $c['steps'] as $s ) : ?>
							<li style="margin-bottom:2px"><?php echo esc_html( $s ); ?></li>
						<?php endforeach; ?>
					</ol>
					<a class="button button-primary" href="<?php echo esc_url( $c['link'] ); ?>"><?php echo esc_html( $c['btn'] ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
