<?php
/**
 * Template Name: BHELA — Book Now
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<h1>বুক করুন</h1>
	<p>তারিখ, কেবিন ও অতিথি সংখ্যা দিন — সাথে সাথে রেট দেখুন, বুকিং রিকোয়েস্ট পাঠান। ৫০% অগ্রিমে বুকিং Confirmed।</p>
	<div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.4rem">
		<a class="btn btn--cta" href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>">📞 <?php echo esc_html( bhela_contact( 'phone_1' ) ); ?></a>
		<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">💬 WhatsApp-এ সরাসরি বুক করুন</a>
	</div>
</div></section>

<?php bhela_page_editor_content(); // Gutenberg-editable region ?>

<section class="section"><div class="container">
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

	<div class="entry-content" style="margin-top:3.5rem">
		<h2>📝 বুকিং প্রসেস</h2>
		<ol>
			<li>ভ্রমণের তারিখ নির্বাচন করুন ও Availability চেক করুন</li>
			<li>শুধু অতিথি সংখ্যা দিন — সিস্টেম স্বয়ংক্রিয়ভাবে উপযুক্ত কেবিন ও রেট বাছাই করবে (চাইলে নিজেও কেবিন সাজাতে পারবেন)</li>
			<li>নাম ও মোবাইল নম্বর দিয়ে রিকোয়েস্ট পাঠান</li>
			<li><strong>৫০% অগ্রিম</strong> প্রদান করুন (bKash / Nagad / Bank)</li>
			<li>Booking Confirmation ও ইনভয়েস গ্রহণ করুন</li>
			<li>নির্ধারিত দিনে Anwarpur Ghat থেকে ভ্রমণ শুরু</li>
		</ol>
		<h2>📞 অন্যান্য যোগাযোগ</h2>
		<p>
			📱 <a href="tel:<?php echo esc_attr( bhela_contact( 'phone_2' ) ); ?>"><?php echo esc_html( bhela_contact( 'phone_2' ) ); ?></a> ·
			✉️ <a href="mailto:<?php echo esc_attr( bhela_contact( 'email' ) ); ?>"><?php echo esc_html( bhela_contact( 'email' ) ); ?></a>
		</p>
		<p style="font-size:.9rem;color:var(--text-soft)">বুকিং সম্পন্ন করার মাধ্যমে আপনি আমাদের <a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">বুকিং নীতিমালায়</a> সম্মতি প্রদান করছেন।</p>
	</div>
</div></section>
<?php get_footer(); ?>
