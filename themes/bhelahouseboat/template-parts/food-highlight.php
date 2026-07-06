<?php
/**
 * Template Part: Food Highlight
 *
 * @package BhelaHouseboat
 */
?>

<section class="section" id="food-section">
    <div class="container">
        <div class="food-highlight reveal">
            <div class="food-highlight__bg">
                <img src="<?php echo BHELA_URI; ?>/assets/images/food/food-spread.jpg" alt="ভেলার দেশীয় খাবার" loading="lazy" width="1200" height="450">
            </div>
            <div class="food-highlight__overlay"></div>

            <div class="food-highlight__content">
                <span class="section__subtitle" style="color: var(--color-cta-light);">স্বাদের ভেলা</span>
                <h2 class="food-highlight__title">হাওরের তাজা মাছ, দেশি হাঁস, আনলিমিটেড চা-কফি</h2>
                <p>২ দিন ১ রাতে ২ Breakfast, ২ Lunch, ১ Dinner, Evening Snacks — সব তাজা দেশীয় উপাদানে তৈরি।</p>

                <div class="food-highlight__list">
                    <span class="food-highlight__item">🐟 হাওরের বড় মাছ</span>
                    <span class="food-highlight__item">🦆 দেশি হাঁসের মাংস</span>
                    <span class="food-highlight__item">🍗 দেশি মুরগি</span>
                    <span class="food-highlight__item">🌶️ ভর্তা-শুটকি</span>
                    <span class="food-highlight__item">🍚 ভুনা খিচুড়ি</span>
                    <span class="food-highlight__item">☕ আনলিমিটেড চা-কফি</span>
                    <span class="food-highlight__item">🍹 Welcome Drinks</span>
                    <span class="food-highlight__item">🍌 সিজনাল ফল</span>
                </div>

                <a href="<?php echo esc_url( home_url( '/food-menu/' ) ); ?>" class="btn btn--secondary">
                    সম্পূর্ণ মেনু দেখুন →
                </a>
            </div>
        </div>
    </div>
</section>
