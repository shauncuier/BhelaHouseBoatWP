<?php
/**
 * Footer Template
 *
 * @package BhelaHouseboat
 */
?>

<!-- Floating WhatsApp Button -->
<div class="whatsapp-float" id="whatsapp-float">
    <span class="whatsapp-float__tooltip">WhatsApp এ বুকিং করুন</span>
    <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="whatsapp-float__btn" target="_blank" rel="noopener" aria-label="WhatsApp এ যোগাযোগ করুন" id="whatsapp-float-btn">
        💬
    </a>
</div>

<!-- Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-brand">
                <img src="<?php echo BHELA_URI; ?>/assets/images/logo.png" alt="BHELA Logo" class="footer-brand__logo" width="60" height="60">
                <p class="footer-brand__tagline">
                    ভেলায় আমরা সেবা দিই, বাকিটা হাওর নিজেই করে!<br>
                    <em>Where Nature, Comfort & Memories Meet</em>
                </p>

                <div class="footer-brand__contacts">
                    <a href="<?php echo esc_url( bhela_phone_link( '01891562461' ) ); ?>" class="footer-brand__contact">
                        📱 01891-562461
                    </a>
                    <a href="<?php echo esc_url( bhela_phone_link( '01614182769' ) ); ?>" class="footer-brand__contact">
                        📱 01614-182769
                    </a>
                    <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="footer-brand__contact" target="_blank" rel="noopener">
                        📲 WhatsApp: +8801793395556
                    </a>
                    <a href="mailto:infobhela@gmail.com" class="footer-brand__contact">
                        📧 infobhela@gmail.com
                    </a>
                </div>

                <!-- Social Links -->
                <div class="footer-social">
                    <?php if ( $fb = bhela_get_option( 'social_facebook' ) ) : ?>
                        <a href="<?php echo esc_url( $fb ); ?>" target="_blank" rel="noopener" aria-label="Facebook">📘</a>
                    <?php else : ?>
                        <a href="#" aria-label="Facebook">📘</a>
                    <?php endif; ?>
                    <?php if ( $ig = bhela_get_option( 'social_instagram' ) ) : ?>
                        <a href="<?php echo esc_url( $ig ); ?>" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
                    <?php else : ?>
                        <a href="#" aria-label="Instagram">📷</a>
                    <?php endif; ?>
                    <?php if ( $yt = bhela_get_option( 'social_youtube' ) ) : ?>
                        <a href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener" aria-label="YouTube">🎬</a>
                    <?php else : ?>
                        <a href="#" aria-label="YouTube">🎬</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="footer-widget__title">দ্রুত লিংক</h4>
                <div class="footer-links">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">হোম</a>
                    <a href="<?php echo esc_url( home_url( '/the-boat/' ) ); ?>">ভেলা পরিচিতি</a>
                    <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>">কেবিন ও রেট</a>
                    <a href="<?php echo esc_url( home_url( '/trip-schedule/' ) ); ?>">ট্রিপ সিডিউল</a>
                    <a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>">গ্যালারি</a>
                    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">যোগাযোগ</a>
                </div>
            </div>

            <!-- Experience -->
            <div>
                <h4 class="footer-widget__title">অভিজ্ঞতা</h4>
                <div class="footer-links">
                    <a href="<?php echo esc_url( home_url( '/experience/' ) ); ?>">ভ্রমণ স্পট</a>
                    <a href="<?php echo esc_url( home_url( '/food-menu/' ) ); ?>">খাবার মেনু</a>
                    <a href="<?php echo esc_url( home_url( '/corporate/' ) ); ?>">Corporate ট্যুর</a>
                    <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">জিজ্ঞাসা (FAQ)</a>
                </div>
            </div>

            <!-- Policies -->
            <div>
                <h4 class="footer-widget__title">নীতিমালা</h4>
                <div class="footer-links">
                    <a href="<?php echo esc_url( home_url( '/policies/' ) ); ?>">Booking & Payment</a>
                    <a href="<?php echo esc_url( home_url( '/policies/' ) ); ?>">Cancellation & Refund</a>
                    <a href="<?php echo esc_url( home_url( '/policies/' ) ); ?>">Rescheduling</a>
                    <a href="<?php echo esc_url( home_url( '/policies/' ) ); ?>">Child Policy</a>
                    <a href="<?php echo esc_url( home_url( '/policies/' ) ); ?>">Weather Policy</a>
                </div>

                <!-- Payment Methods -->
                <div class="footer-payment">
                    <div class="footer-payment__icons">
                        <span class="footer-payment__icon">bKash</span>
                        <span class="footer-payment__icon">Nagad</span>
                        <span class="footer-payment__icon">Bank</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>
                © <?php echo date( 'Y' ); ?> BHELA – The Haor Exclusive. All rights reserved.
                | Developed by <a href="https://3s-soft.com" target="_blank" rel="noopener">3s-Soft</a>
            </p>
        </div>
    </div>
</footer>

<!-- Sticky Mobile Bar -->
<div class="mobile-bar" id="mobile-bar">
    <div class="mobile-bar__grid">
        <a href="<?php echo esc_url( bhela_phone_link() ); ?>" class="mobile-bar__btn mobile-bar__btn--call" id="mobile-call-btn">
            <span class="mobile-bar__btn-icon">📞</span>
            কল করুন
        </a>
        <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="mobile-bar__btn mobile-bar__btn--whatsapp" target="_blank" rel="noopener" id="mobile-whatsapp-btn">
            <span class="mobile-bar__btn-icon">💬</span>
            WhatsApp
        </a>
        <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>" class="mobile-bar__btn mobile-bar__btn--book" id="mobile-book-btn">
            <span class="mobile-bar__btn-icon">📅</span>
            Book Now
        </a>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
