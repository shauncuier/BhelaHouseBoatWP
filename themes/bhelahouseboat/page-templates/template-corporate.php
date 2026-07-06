<?php
/**
 * Template Name: Corporate
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <!-- Corporate Hero -->
    <div class="corporate-hero">
        <div class="container">
            <span class="section__subtitle" style="color: var(--color-cta-light);">For Organizations</span>
            <h1>Corporate & Full Boat Reservation</h1>
            <p style="opacity: 0.9; max-width: 600px; margin: 0 auto var(--space-2xl);">
                Team Building, Retreat, Family Day, বা পুরো বোট একসাথে — কাস্টম প্যাকেজে ভেলা শুধু আপনাদের!
            </p>

            <div class="corporate-features">
                <div class="corporate-feature reveal">
                    <div class="corporate-feature__icon">🏢</div>
                    <h4 class="corporate-feature__title">Team Building</h4>
                    <p class="corporate-feature__desc">হাওরের প্রকৃতিতে টিম বন্ডিং — একটু ভিন্নভাবে, একটু বিশেষভাবে।</p>
                </div>
                <div class="corporate-feature reveal reveal--delay-1">
                    <div class="corporate-feature__icon">🎉</div>
                    <h4 class="corporate-feature__title">Private Celebration</h4>
                    <p class="corporate-feature__desc">Birthday, Anniversary, Reunion — পুরো বোট শুধু আপনাদের গ্রুপের জন্য।</p>
                </div>
                <div class="corporate-feature reveal reveal--delay-2">
                    <div class="corporate-feature__icon">💼</div>
                    <h4 class="corporate-feature__title">Custom Package</h4>
                    <p class="corporate-feature__desc">আপনার বাজেট ও চাহিদা অনুযায়ী সম্পূর্ণ কাস্টম প্যাকেজ — Menu, Duration, Itinerary সব।</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Benefits -->
    <section class="section">
        <div class="container container--narrow">
            <div class="section__header reveal">
                <span class="section__subtitle">Full Boat Reservation</span>
                <h2 class="section__title">পুরো বোট বুকিং এর সুবিধা</h2>
            </div>

            <div class="policy-content reveal">
                <ul>
                    <li>সম্পূর্ণ Privacy — পুরো বোট শুধু আপনাদের গ্রুপের</li>
                    <li>সকল ৬টি কেবিন — সর্বোচ্চ ৪০ জন</li>
                    <li>কাস্টম মেনু — আপনার পছন্দের খাবার</li>
                    <li>কাস্টম Itinerary — আপনার সময় অনুযায়ী</li>
                    <li>বিশেষ ডিসকাউন্ট — গ্রুপ সাইজ অনুযায়ী</li>
                    <li>Dedicated Trip Coordinator</li>
                </ul>
            </div>

            <div style="text-align: center; margin-top: var(--space-2xl);">
                <a href="<?php echo esc_url( bhela_whatsapp_link( 'আমি Full Boat / Corporate Reservation এর জন্য Quote চাই। আমাদের গ্রুপ সাইজ: ____ জন, তারিখ: ____' ) ); ?>" class="btn btn--whatsapp btn--lg" target="_blank" rel="noopener">
                    <span class="btn__icon">💬</span> Get a Custom Quote
                </a>
                <p style="color: var(--color-text-muted); margin-top: var(--space-md); font-size: var(--font-size-sm);">
                    WhatsApp এ গ্রুপ সাইজ ও তারিখ জানান — ২৪ ঘণ্টায় কাস্টম কোটেশন পাবেন
                </p>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
