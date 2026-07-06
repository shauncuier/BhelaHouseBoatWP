<?php
/**
 * Template Part: Hero Section
 *
 * @package BhelaHouseboat
 */

$hero_image = bhela_get_option( 'hero_image', BHELA_URI . '/assets/images/hero/hero-haor.jpg' );
$tagline = bhela_get_option( 'hero_tagline', 'ভেলার আকর্ষণ ভেলা নয়, হাওর!' );
$subtitle = bhela_get_option( 'hero_subtitle', 'Where Nature, Comfort & Memories Meet' );
?>

<section class="hero" id="hero">
    <!-- Background Image -->
    <div class="hero__bg">
        <img src="<?php echo esc_url( $hero_image ); ?>" alt="টাঙ্গুয়ার হাওর — BHELA Houseboat" width="1920" height="1080">
    </div>

    <!-- Gradient Overlay -->
    <div class="hero__overlay"></div>

    <!-- Content -->
    <div class="hero__content">
        <img src="<?php echo BHELA_URI; ?>/assets/images/logo.png" alt="BHELA — ভেলা" class="hero__logo" width="140" height="140">

        <h1 class="hero__tagline"><?php echo esc_html( $tagline ); ?></h1>

        <p class="hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>

        <p class="hero__desc">
            টাঙ্গুয়ার হাওরের প্রিমিয়াম ফ্যামিলি হাউসবোট — ৬টি AC কেবিন, দেশীয় খাবার, Rooftop Lounge<br>
            ২ দিন ১ রাত · জনপ্রতি ৳৬,৪০০ থেকে
        </p>

        <div class="hero__ctas">
            <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>" class="btn btn--primary btn--lg" id="hero-book-btn">
                <span class="btn__icon">📅</span> তারিখ দেখুন / Book Now
            </a>
            <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--glass btn--lg" target="_blank" rel="noopener" id="hero-whatsapp-btn">
                <span class="btn__icon">💬</span> WhatsApp এ জানুন
            </a>
        </div>
    </div>

    <!-- Scroll Hint -->
    <div class="hero__scroll-hint" aria-hidden="true">
        <span></span>
        নিচে স্ক্রল করুন
    </div>
</section>
