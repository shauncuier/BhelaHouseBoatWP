<?php
/**
 * Theme footer.
 *
 * @package Bhela
 */
?>
</main>
<footer class="site-footer">
	<div class="container">
		<div class="footer-grid">
			<div class="footer-brand">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.png' ); ?>" alt="BHELA logo">
				<p>টাঙ্গুয়ার হাওরের প্রিমিয়াম ফ্যামিলি ও গ্রুপ ফ্রেন্ডলি AC হাউসবোট। থাকা, খাওয়া, হাওর ভ্রমণ — সব এক প্যাকেজে।</p>
				<p class="footer-tagline">"ভেলার আকর্ষণ ভেলা নয়, হাওর!"</p>
			</div>
			<div class="footer-col">
				<h4>এক্সপ্লোর</h4>
				<ul>
					<li><a href="<?php echo esc_url( bhela_page_url( 'cabins' ) ); ?>">কেবিন ও রেট</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'schedule' ) ); ?>">ট্রিপ সিডিউল</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'food' ) ); ?>">খাবার মেনু</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'gallery' ) ); ?>">গ্যালারি</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'faq' ) ); ?>">সাধারণ প্রশ্ন</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h4>নীতিমালা</h4>
				<ul>
					<li><a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">বুকিং ও পেমেন্ট</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">ক্যানসেলেশন ও রিফান্ড</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">রিসিডিউল</a></li>
					<li><a href="<?php echo esc_url( bhela_page_url( 'policies' ) ); ?>">শিশু নীতিমালা</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h4>যোগাযোগ</h4>
				<ul>
					<li>📱 <a href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>"><?php echo esc_html( bhela_contact( 'phone_1' ) ); ?></a></li>
					<li>📱 <a href="tel:<?php echo esc_attr( bhela_contact( 'phone_2' ) ); ?>"><?php echo esc_html( bhela_contact( 'phone_2' ) ); ?></a></li>
					<li>💬 <a href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">WhatsApp: <?php echo esc_html( bhela_contact( 'whatsapp' ) ); ?></a></li>
					<li>✉️ <a href="mailto:<?php echo esc_attr( bhela_contact( 'email' ) ); ?>"><?php echo esc_html( bhela_contact( 'email' ) ); ?></a></li>
					<li>📍 <?php echo esc_html( bhela_contact( 'address' ) ); ?></li>
				</ul>
			</div>
		</div>
		<div class="footer-bottom">
			<span>© <?php echo esc_html( date( 'Y' ) ); ?> BHELA – The Haor Exclusive. All rights reserved.</span>
			<span>"Where Nature, Comfort &amp; Memories Meet"</span>
			<span class="footer-credit">Developed by <a href="https://3s-soft.com" target="_blank" rel="noopener">3s-Soft</a></span>
		</div>
	</div>
</footer>

<a class="wa-float" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
	<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C9.4 3 4 8.3 4 14.9c0 2.6.8 5 2.3 7L4 29l7.3-2.2c1.9 1 3.9 1.5 4.7 1.5 6.6 0 12-5.3 12-11.9S22.6 3 16 3zm6.7 16.9c-.3.8-1.7 1.6-2.3 1.6-.6.1-1.4.1-2.2-.2-.5-.2-1.2-.4-2-.8-3.6-1.5-5.9-5.1-6.1-5.4-.2-.2-1.4-1.9-1.4-3.6s.9-2.6 1.2-2.9c.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6.3.8 1 2.6 1.1 2.8.1.2.2.4 0 .7-.1.2-.2.4-.4.6l-.6.7c-.2.2-.4.4-.2.8s1 1.7 2.2 2.7c1.5 1.3 2.8 1.7 3.2 1.9.4.2.6.2.9-.1.2-.3 1-1.2 1.3-1.6.3-.4.5-.3.9-.2.4.1 2.3 1.1 2.7 1.3.4.2.6.3.7.5.1.1.1.9-.2 1.7z"/></svg>
</a>

<div class="mobile-bar">
	<a class="mobile-bar__icon mobile-bar__icon--call" href="tel:<?php echo esc_attr( bhela_contact( 'phone_1' ) ); ?>" aria-label="<?php esc_attr_e( 'কল করুন', 'bhela' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
	</a>
	<a class="mobile-bar__icon mobile-bar__icon--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
		<svg viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3C9.4 3 4 8.3 4 14.9c0 2.6.8 5 2.3 7L4 29l7.3-2.2c1.9 1 3.9 1.5 4.7 1.5 6.6 0 12-5.3 12-11.9S22.6 3 16 3zm6.7 16.9c-.3.8-1.7 1.6-2.3 1.6-.6.1-1.4.1-2.2-.2-.5-.2-1.2-.4-2-.8-3.6-1.5-5.9-5.1-6.1-5.4-.2-.2-1.4-1.9-1.4-3.6s.9-2.6 1.2-2.9c.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6.3.8 1 2.6 1.1 2.8.1.2.2.4 0 .7-.1.2-.2.4-.4.6l-.6.7c-.2.2-.4.4-.2.8s1 1.7 2.2 2.7c1.5 1.3 2.8 1.7 3.2 1.9.4.2.6.2.9-.1.2-.3 1-1.2 1.3-1.6.3-.4.5-.3.9-.2.4.1 2.3 1.1 2.7 1.3.4.2.6.3.7.5.1.1.1.9-.2 1.7z"/></svg>
	</a>
	<a class="mobile-bar__book" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুক করুন</a>
</div>

<?php wp_footer(); ?>
</body>
</html>
