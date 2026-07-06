<?php
/**
 * Header Template
 *
 * @package BhelaHouseboat
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo is_front_page() ? 'BHELA – টাঙ্গুয়ার হাওরের সবচেয়ে পরিবারবান্ধব প্রিমিয়াম AC হাউসবোট। ৬টি Family Cabin, Attached Washroom, Rooftop Lounge, দেশীয় প্রিমিয়াম খাবার। ২ দিন ১ রাত — জনপ্রতি ৳৬,৪০০ থেকে।' : get_the_excerpt(); ?>">
    <meta name="theme-color" content="#0E6E6B">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo is_front_page() ? 'BHELA – The Haor Exclusive | Premium Houseboat, Tanguar Haor' : wp_title( '|', false, 'right' ) . get_bloginfo( 'name' ); ?>">
    <meta property="og:description" content="টাঙ্গুয়ার হাওরের প্রিমিয়াম ফ্যামিলি হাউসবোট — AC কেবিন, Rooftop Lounge, দেশীয় খাবার। ২ দিন ১ রাত প্যাকেজ।">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="bn_BD">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Site Header -->
<header class="site-header <?php echo is_front_page() ? 'site-header--transparent' : 'site-header--scrolled'; ?>" id="site-header" role="banner">
    <div class="container">
        <!-- Logo -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo" aria-label="BHELA Houseboat - Home">
            <img src="<?php echo BHELA_URI; ?>/assets/images/logo.png" alt="BHELA Logo" class="site-logo__img" width="50" height="50">
            <div class="site-logo__text">
                ভেলা
                <span>The Haor Exclusive</span>
            </div>
        </a>

        <!-- Primary Navigation -->
        <nav class="nav-primary" id="nav-primary" aria-label="Primary Navigation">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php echo is_front_page() ? 'class="active"' : ''; ?>>হোম</a>
            <a href="<?php echo esc_url( home_url( '/the-boat/' ) ); ?>">ভেলা পরিচিতি</a>
            <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>">কেবিন ও রেট</a>
            <a href="<?php echo esc_url( home_url( '/trip-schedule/' ) ); ?>">সিডিউল</a>
            <a href="<?php echo esc_url( home_url( '/experience/' ) ); ?>">অভিজ্ঞতা</a>
            <a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>">গ্যালারি</a>
            <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a>
        </nav>

        <!-- CTA Buttons -->
        <div class="nav-cta">
            <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--whatsapp btn--sm" target="_blank" rel="noopener" id="nav-whatsapp-btn">
                <span class="btn__icon">💬</span> WhatsApp
            </a>
        </div>

        <!-- Mobile Toggle -->
        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle Navigation" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>

<!-- Mobile Navigation Panel -->
<nav class="nav-mobile" id="nav-mobile" aria-label="Mobile Navigation">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">🏠 হোম</a>
    <a href="<?php echo esc_url( home_url( '/the-boat/' ) ); ?>">🛶 ভেলা পরিচিতি</a>
    <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>">🛏️ কেবিন ও রেট</a>
    <a href="<?php echo esc_url( home_url( '/trip-schedule/' ) ); ?>">📅 সিডিউল</a>
    <a href="<?php echo esc_url( home_url( '/experience/' ) ); ?>">🌊 অভিজ্ঞতা</a>
    <a href="<?php echo esc_url( home_url( '/food-menu/' ) ); ?>">🍛 খাবার</a>
    <a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>">📸 গ্যালারি</a>
    <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">❓ FAQ</a>
    <a href="<?php echo esc_url( home_url( '/corporate/' ) ); ?>">🏢 Corporate</a>
    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">📞 যোগাযোগ</a>

    <div class="nav-mobile__cta">
        <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--whatsapp btn--full" target="_blank" rel="noopener">
            <span class="btn__icon">💬</span> WhatsApp এ মেসেজ করুন
        </a>
        <a href="<?php echo esc_url( bhela_phone_link() ); ?>" class="btn btn--secondary btn--full">
            <span class="btn__icon">📞</span> কল করুন
        </a>
    </div>
</nav>

<!-- Mobile Nav Overlay -->
<div class="nav-overlay" id="nav-overlay"></div>
