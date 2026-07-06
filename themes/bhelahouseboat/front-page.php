<?php
/**
 * Template: Front Page (Homepage)
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <!-- Hero Section -->
    <?php get_template_part( 'template-parts/hero' ); ?>

    <!-- Trust Bar -->
    <?php get_template_part( 'template-parts/trust-bar' ); ?>

    <!-- Boat Teaser Section -->
    <section class="section" id="boat-teaser">
        <div class="container">
            <div class="boat-teaser reveal">
                <div class="boat-teaser__images">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/exterior-1.jpg" alt="ভেলা হাউসবোট — Tanguar Haor" loading="lazy">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/cabin-1.jpg" alt="ভেলার প্রশস্ত Family Cabin" loading="lazy">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/rooftop-1.jpg" alt="ভেলার Rooftop Lounge" loading="lazy">
                </div>
                <div class="boat-teaser__info">
                    <div class="boat-teaser__scarcity">
                        🔥 মাত্র ৬টি কেবিন · সর্বোচ্চ ৪০ জন
                    </div>
                    <span class="section__subtitle">ভেলা পরিচিতি</span>
                    <h2>টাঙ্গুয়ার হাওরের প্রিমিয়াম ফ্যামিলি হাউসবোট</h2>
                    <p>ভেলা শুধু একটি হাউসবোট নয় — Family, Friends, Corporate & Group Tourism Experience। থাকা, খাওয়া, ঘোরা, আড্ডা, গান, নিরাপদ রাত্রিযাপন — সব একসাথে।</p>

                    <div class="boat-teaser__features">
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🛏️</span>
                            <div class="boat-teaser__feature-text">
                                ৬টি Family Cabin
                                <span>ডাবল বেড + অতিরিক্ত ব্যবস্থা</span>
                            </div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">❄️</span>
                            <div class="boat-teaser__feature-text">
                                AC ও Non-AC
                                <span>আপনার পছন্দ অনুযায়ী</span>
                            </div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🚿</span>
                            <div class="boat-teaser__feature-text">
                                Attached Washroom
                                <span>প্রতিটি কেবিনে</span>
                            </div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🌅</span>
                            <div class="boat-teaser__feature-text">
                                Rooftop Lounge
                                <span>আড্ডা ও ডাইনিং</span>
                            </div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🔒</span>
                            <div class="boat-teaser__feature-text">
                                Full Privacy
                                <span>অপরিচিতদের সাথে শেয়ার নয়</span>
                            </div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">⚡</span>
                            <div class="boat-teaser__feature-text">
                                ২৪ ঘণ্টা বিদ্যুৎ
                                <span>চার্জিং সুবিধা সহ</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: var(--space-xl);">
                        <a href="<?php echo esc_url( home_url( '/the-boat/' ) ); ?>" class="btn btn--outline">
                            ভেলা সম্পর্কে বিস্তারিত →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Wave Divider -->
    <div class="wave-divider wave-divider--top">
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,30 C200,60 400,0 600,30 C800,60 1000,0 1200,30 L1200,60 L0,60 Z" fill="var(--color-sand)"/>
        </svg>
    </div>

    <!-- Cabin & Rate Cards -->
    <section class="section section--sand" id="cabins">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">কেবিন ও রেট</span>
                <h2 class="section__title">আপনার পছন্দের কেবিন বেছে নিন</h2>
                <p class="section__desc">৫ ধরনের কেবিন — Budget Friendly থেকে Exclusive Couple পর্যন্ত। Weekday-তে সকল প্যাকেজে ২০% ছাড়!</p>
            </div>

            <?php get_template_part( 'template-parts/cabin-cards' ); ?>

            <div style="text-align: center; margin-top: var(--space-2xl);">
                <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>" class="btn btn--primary btn--lg">
                    সব কেবিন ও রেট দেখুন →
                </a>
            </div>
        </div>
    </section>

    <!-- Wave Divider -->
    <div class="wave-divider wave-divider--bottom">
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,0 L0,30 C200,0 400,60 600,30 C800,0 1000,60 1200,30 L1200,0 Z" fill="var(--color-sand)"/>
        </svg>
    </div>

    <!-- Experience Spots -->
    <section class="section" id="experience">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">ভ্রমণ অভিজ্ঞতা</span>
                <h2 class="section__title">যেখানে যেখানে যাবেন</h2>
                <p class="section__desc">টাঙ্গুয়ার হাওর থেকে নীলাদ্রি লেক, জাদুকাটা নদী — প্রতিটি স্পটই একটি অবিস্মরণীয় অভিজ্ঞতা।</p>
            </div>

            <?php get_template_part( 'template-parts/experience-spots' ); ?>
        </div>
    </section>

    <!-- Food Highlight -->
    <?php get_template_part( 'template-parts/food-highlight' ); ?>

    <!-- Price Estimator -->
    <section class="section section--alt" id="price-calculator">
        <div class="container container--narrow">
            <div class="section__header reveal">
                <span class="section__subtitle">খরচ হিসাব করুন</span>
                <h2 class="section__title">আপনার ট্রিপের খরচ জেনে নিন</h2>
            </div>

            <?php get_template_part( 'template-parts/price-estimator' ); ?>
        </div>
    </section>

    <!-- FAQ Top 6 -->
    <section class="section" id="faq-preview">
        <div class="container container--narrow">
            <div class="section__header reveal">
                <span class="section__subtitle">জিজ্ঞাসা</span>
                <h2 class="section__title">সচরাচর জিজ্ঞাসিত প্রশ্ন</h2>
            </div>

            <?php get_template_part( 'template-parts/faq-accordion' ); ?>

            <div style="text-align: center; margin-top: var(--space-2xl);">
                <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>" class="btn btn--outline">
                    সব প্রশ্ন দেখুন (৬০+) →
                </a>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
