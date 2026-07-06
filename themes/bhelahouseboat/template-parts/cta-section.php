<?php
/**
 * Template Part: CTA Section (Final call-to-action)
 *
 * @package BhelaHouseboat
 */

$cta_text = bhela_get_option( 'booking_cta', 'আপনার তারিখ ও অতিথি সংখ্যা জানান — WhatsApp এ রেট পান ২ মিনিটে' );
?>

<section class="cta-section" id="cta-section">
    <div class="container">
        <h2 class="cta-section__title reveal">হাওরের ডাক শুনুন, ভেলায় উঠুন!</h2>
        <p class="cta-section__desc reveal"><?php echo esc_html( $cta_text ); ?></p>

        <div class="cta-section__buttons reveal">
            <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--whatsapp btn--lg" target="_blank" rel="noopener" id="cta-whatsapp-btn">
                <span class="btn__icon">💬</span> WhatsApp এ মেসেজ করুন
            </a>
            <a href="<?php echo esc_url( bhela_phone_link() ); ?>" class="btn btn--secondary btn--lg" id="cta-call-btn">
                <span class="btn__icon">📞</span> কল করুন — 01891-562461
            </a>
        </div>
    </div>
</section>
