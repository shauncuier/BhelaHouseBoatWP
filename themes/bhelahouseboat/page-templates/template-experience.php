<?php
/**
 * Template Name: Experience
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
$spots = bhela_get_spots();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>ভ্রমণ অভিজ্ঞতা</h1>
            <p>টাঙ্গুয়ার হাওর থেকে জাদুকাটা নদী — ২ দিন ১ রাতে অসাধারণ ৭টি স্পট</p>
        </div>
    </div>

    <!-- Spots Grid -->
    <section class="section">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">ভ্রমণ স্পট</span>
                <h2 class="section__title">যেখানে যেখানে যাবেন</h2>
                <p class="section__desc">ভেলায় ২ দিন ১ রাতের ট্রিপে এই স্পটগুলো ভ্রমণ করা হয় (আবহাওয়া ও সময় সাপেক্ষে):</p>
            </div>

            <?php get_template_part( 'template-parts/experience-spots' ); ?>
        </div>
    </section>

    <!-- Itinerary -->
    <section class="section section--alt" id="itinerary">
        <div class="container container--narrow">
            <div class="section__header reveal">
                <span class="section__subtitle">ভ্রমণসূচি</span>
                <h2 class="section__title">২ দিন ১ রাত — সম্পূর্ণ Itinerary</h2>
            </div>

            <div class="itinerary reveal">
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ১ — সকাল</span>
                    <h4 class="itinerary__title">🚌 সুনামগঞ্জ থেকে তাহিরপুর</h4>
                    <p class="itinerary__desc">সুনামগঞ্জ শহর থেকে তাহিরপুর ঘাটে পৌঁছান। সেখান থেকে ভেলায় ওঠা, Welcome Drinks, ফ্রেশ হওয়া।</p>
                </div>
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ১ — দুপুর</span>
                    <h4 class="itinerary__title">🌊 টাঙ্গুয়ার হাওর ও জাদুকাটা নদী</h4>
                    <p class="itinerary__desc">বিশাল হাওর পাড়ি দিয়ে জাদুকাটা নদীর স্বচ্ছ পানিতে নামা। দুপুরের খাবার বোটেই। পরে নীলাদ্রি লেক, বারিক্কা টিলা।</p>
                </div>
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ১ — সন্ধ্যা</span>
                    <h4 class="itinerary__title">🌅 Rooftop এ সানসেট ও আড্ডা</h4>
                    <p class="itinerary__desc">Rooftop Lounge-এ বসে সানসেট দেখা, Evening Snacks, চা-কফি, গান-আড্ডা। রাতের খাবার।</p>
                </div>
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ১ — রাত</span>
                    <h4 class="itinerary__title">🌙 হাওরের নিরব রাত</h4>
                    <p class="itinerary__desc">তারাভরা আকাশের নিচে হাওরের মধ্যে রাত্রিযাপন — AC কেবিনে আরামে ঘুম।</p>
                </div>
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ২ — ভোর</span>
                    <h4 class="itinerary__title">🌄 সূর্যোদয় ও ওয়াচ টাওয়ার</h4>
                    <p class="itinerary__desc">ভোরে উঠে হাওরের সূর্যোদয় দেখা। ওয়াচ টাওয়ার থেকে ৩৬০° ভিউ। সকালের নাস্তা।</p>
                </div>
                <div class="itinerary__item">
                    <span class="itinerary__time">দিন ২ — দুপুর</span>
                    <h4 class="itinerary__title">🏞️ টেকেরঘাট ও শেষ ভ্রমণ</h4>
                    <p class="itinerary__desc">বাকি স্পট ভ্রমণ, দুপুরের খাবার। তাহিরপুর ঘাটে ফেরত। স্মৃতি ও ছবিতে ভরপুর একটি ট্রিপ শেষ!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
