<?php
/**
 * Template Name: Cabins & Rates
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
$cabins = bhela_get_cabins();
?>

<main id="main-content" role="main">

    <!-- Page Hero -->
    <div class="page-hero">
        <div class="container">
            <h1>কেবিন ও রেট</h1>
            <p>৫ ধরনের কেবিন — বাজেট থেকে এক্সক্লুসিভ। আপনার পরিবার ও বাজেট অনুযায়ী বেছে নিন।</p>
        </div>
    </div>

    <!-- Cabin Cards -->
    <section class="section" id="all-cabins">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">২ দিন ১ রাত প্যাকেজ</span>
                <h2 class="section__title">সব খরচ অন্তর্ভুক্ত — থাকা, খাওয়া, ঘোরা</h2>
            </div>

            <?php get_template_part( 'template-parts/cabin-cards' ); ?>
        </div>
    </section>

    <!-- Child Policy -->
    <section class="section section--alt" id="child-policy">
        <div class="container container--narrow">
            <div class="child-policy reveal">
                <h3 class="child-policy__title">👶 শিশু নীতি (Child Policy)</h3>
                <div class="child-policy__grid">
                    <div class="child-policy__item">
                        <div class="child-policy__age">০ – ৪ বছর</div>
                        <div class="child-policy__charge">সম্পূর্ণ ফ্রি</div>
                        <small style="color: var(--color-text-muted);">(আলাদা বেড/মিল ছাড়া)</small>
                    </div>
                    <div class="child-policy__item">
                        <div class="child-policy__age">৪ – ৮ বছর</div>
                        <div class="child-policy__charge">৫০% চার্জ</div>
                    </div>
                    <div class="child-policy__item">
                        <div class="child-policy__age">৯+ বছর</div>
                        <div class="child-policy__charge">পূর্ণ চার্জ</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Includes / Excludes -->
    <section class="section" id="includes-excludes">
        <div class="container container--narrow">
            <div class="grid grid--2">
                <div class="reveal">
                    <h3 style="color: var(--color-success);">✅ প্যাকেজে অন্তর্ভুক্ত</h3>
                    <div class="policy-content">
                        <ul>
                            <li>২ দিন ১ রাত আবাসন</li>
                            <li>২ Breakfast + ২ Lunch + ১ Dinner</li>
                            <li>Evening Snacks</li>
                            <li>আনলিমিটেড চা-কফি</li>
                            <li>Welcome Drinks</li>
                            <li>উল্লেখিত স্পট ভ্রমণ</li>
                            <li>গাইড সার্ভিস</li>
                            <li>২৪ ঘণ্টা স্টাফ সাপোর্ট</li>
                            <li>Life Jacket ও নিরাপত্তা সরঞ্জাম</li>
                            <li>বিশুদ্ধ পানীয় জল</li>
                        </ul>
                    </div>
                </div>
                <div class="reveal reveal--delay-2">
                    <h3 style="color: var(--color-error);">❌ অন্তর্ভুক্ত নয়</h3>
                    <div class="policy-content">
                        <ul style="list-style: none;">
                            <li style="padding-left: 0;">❌ সুনামগঞ্জ পর্যন্ত ব্যক্তিগত যাতায়াত</li>
                            <li style="padding-left: 0;">❌ বিশেষ খাবারের অর্ডার</li>
                            <li style="padding-left: 0;">❌ ব্যক্তিগত খরচ</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Price Estimator -->
    <section class="section section--sand" id="calculator">
        <div class="container container--narrow">
            <div class="section__header reveal">
                <span class="section__subtitle">খরচ হিসাব</span>
                <h2 class="section__title">আপনার ট্রিপের খরচ জেনে নিন</h2>
            </div>
            <?php get_template_part( 'template-parts/price-estimator' ); ?>
        </div>
    </section>

    <!-- CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
