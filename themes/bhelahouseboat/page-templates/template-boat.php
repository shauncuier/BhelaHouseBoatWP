<?php
/**
 * Template Name: The Boat
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>ভেলা পরিচিতি</h1>
            <p>BHELA – The Haor Exclusive — টাঙ্গুয়ার হাওরের প্রিমিয়াম পরিবারবান্ধব হাউসবোট</p>
        </div>
    </div>

    <!-- Boat Overview -->
    <section class="section">
        <div class="container">
            <div class="boat-teaser reveal">
                <div class="boat-teaser__images">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/exterior-1.jpg" alt="ভেলা হাউসবোট — বাইরের দৃশ্য" loading="lazy">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/cabin-1.jpg" alt="ভেলার AC কেবিন" loading="lazy">
                    <img src="<?php echo BHELA_URI; ?>/assets/images/boat/rooftop-1.jpg" alt="ভেলার Rooftop Lounge" loading="lazy">
                </div>
                <div class="boat-teaser__info">
                    <span class="section__subtitle">ভেলা কেন আলাদা?</span>
                    <h2>Family, Friends, Corporate — সবার জন্য</h2>
                    <p>ভেলা টাঙ্গুয়ার হাওরের অন্যতম প্রিমিয়াম হাউসবোট। থাকা, খাওয়া, ঘোরা, আড্ডা — সব একসাথে, সর্বোচ্চ নিরাপত্তা ও Privacy-সহ।</p>
                    <p>অপরিচিত গ্রুপের সাথে কেবিন শেয়ার করা হয় না — আপনার কেবিন শুধু আপনার।</p>

                    <div class="boat-teaser__features">
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🛏️</span>
                            <div class="boat-teaser__feature-text">৬টি বড় Family Cabin<span>ডাবল বেড + Extra Bedding</span></div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">❄️</span>
                            <div class="boat-teaser__feature-text">AC ও Non-AC<span>দুটি অপশনই আছে</span></div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🚿</span>
                            <div class="boat-teaser__feature-text">Attached Washroom<span>প্রতিটি কেবিনে</span></div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🪟</span>
                            <div class="boat-teaser__feature-text">Infinity Glass Window<span>হাওরের ভিউ কেবিন থেকে</span></div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">🌅</span>
                            <div class="boat-teaser__feature-text">Rooftop Lounge<span>আড্ডা, খাওয়া, সানসেট</span></div>
                        </div>
                        <div class="boat-teaser__feature">
                            <span class="boat-teaser__feature-icon">👨‍👩‍👧‍👦</span>
                            <div class="boat-teaser__feature-text">সর্বোচ্চ ৪০ জন<span>মাত্র ৬টি কেবিনে</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Safety Features -->
    <section class="section section--alt">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">নিরাপত্তা</span>
                <h2 class="section__title">সম্পূর্ণ নিরাপদ ভ্রমণ</h2>
            </div>

            <div class="grid grid--4">
                <div class="card reveal">
                    <div class="card__body" style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: var(--space-md);">🦺</div>
                        <h4 class="card__title">Life Jacket</h4>
                        <p class="card__desc">সকল যাত্রীর জন্য Life Jacket সরবরাহ করা হয়।</p>
                    </div>
                </div>
                <div class="card reveal reveal--delay-1">
                    <div class="card__body" style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: var(--space-md);">👨‍✈️</div>
                        <h4 class="card__title">Trained Crew</h4>
                        <p class="card__desc">প্রশিক্ষিত ও অভিজ্ঞ নাবিক ও স্টাফ টিম।</p>
                    </div>
                </div>
                <div class="card reveal reveal--delay-2">
                    <div class="card__body" style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: var(--space-md);">🔒</div>
                        <h4 class="card__title">Privacy</h4>
                        <p class="card__desc">অপরিচিতদের সাথে কেবিন শেয়ার করা হয় না।</p>
                    </div>
                </div>
                <div class="card reveal reveal--delay-3">
                    <div class="card__body" style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: var(--space-md);">⚡</div>
                        <h4 class="card__title">২৪h বিদ্যুৎ</h4>
                        <p class="card__desc">চার্জিং পয়েন্ট ও ফ্যান/লাইট সারারাত।</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
