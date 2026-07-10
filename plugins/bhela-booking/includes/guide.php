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
		'সহজ গাইড',
		'🎯 সহজ গাইড',
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
			'icon'  => '📋',
			'title' => 'নতুন বুকিং দেখুন ও কনফার্ম করুন',
			'steps' => array(
				'নতুন রিকোয়েস্ট এলে ইমেইলে জানতে পারবেন',
				'Bookings → নাম-এ ক্লিক করুন',
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
				'নতুন মাসে "Add Trip" চেপে তারিখ যোগ করুন',
				'সিট ভরে গেলে Status → Filling Fast বা Booked করুন',
				'Save চাপলেই ওয়েবসাইটে সাথে সাথে আপডেট',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-trips' ),
			'btn'   => 'ট্রিপ ক্যালেন্ডার খুলুন',
		),
		array(
			'icon'  => '💰',
			'title' => 'রেট, ছুটির দিন ও পেমেন্ট নম্বর',
			'steps' => array(
				'কেবিন রেট বদলাতে নিচের টেবিলে নতুন দাম লিখুন',
				'সরকারি ছুটি যোগ করুন (এক লাইনে একটি তারিখ)',
				'bKash/Nagad নম্বর ও QR ছবির লিংক এখানেই',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings' ),
			'btn'   => 'সেটিংস খুলুন',
		),
		array(
			'icon'  => '🖼️',
			'title' => 'ছবি যোগ / বদল করুন',
			'steps' => array(
				'Media → Add New → ছবি আপলোড করুন',
				'পেমেন্ট QR ছবি আপলোড করে File URL কপি করুন',
				'Settings-এ QR URL পেস্ট করলে ইনভয়েসে দেখাবে',
			),
			'link'  => admin_url( 'upload.php' ),
			'btn'   => 'মিডিয়া লাইব্রেরি',
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
			'icon'  => '⭐',
			'title' => 'গেস্ট রিভিউ যোগ / বদল করুন',
			'steps' => array(
				'Bookings → Reviews → Add New',
				'Title-এ অতিথির নাম, নিচে রিভিউ লেখা',
				'ডানপাশে স্টার রেটিং ও Trip Type দিন',
				'Publish চাপলেই হোমপেজে দেখাবে',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_review' ),
			'btn'   => 'রিভিউ ম্যানেজ করুন',
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
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;max-width:1200px;margin-top:16px">
			<?php foreach ( $cards as $c ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-left:5px solid #137A74;border-radius:10px;padding:18px 20px">
					<h2 style="margin:0 0 10px;font-size:15px"><?php echo esc_html( $c['icon'] . ' ' . $c['title'] ); ?></h2>
					<ol style="margin:0 0 14px;padding-left:20px;font-size:13px;line-height:1.9">
						<?php foreach ( $c['steps'] as $s ) : ?>
							<li><?php echo esc_html( $s ); ?></li>
						<?php endforeach; ?>
					</ol>
					<a class="button button-primary" href="<?php echo esc_url( $c['link'] ); ?>"><?php echo esc_html( $c['btn'] ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
