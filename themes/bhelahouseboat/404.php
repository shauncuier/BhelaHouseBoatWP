<?php
/**
 * 404 Template
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">
    <div class="page-hero">
        <div class="container">
            <h1>৪০৪ — পেজ পাওয়া যায়নি</h1>
            <p>আপনি যে পেজটি খুঁজছেন সেটি এখানে নেই। হয়তো হাওরের ঢেউয়ে ভেসে গেছে! 🌊</p>
        </div>
    </div>

    <div class="section">
        <div class="container" style="text-align: center;">
            <div style="font-size: 80px; margin-bottom: var(--space-xl);">🛶</div>
            <h2>ভেলায় ফিরে আসুন</h2>
            <p style="color: var(--color-text-light); margin-bottom: var(--space-2xl); max-width: 500px; margin-left: auto; margin-right: auto;">
                নিচের লিংক থেকে আপনার প্রয়োজনীয় পেজে যেতে পারেন, অথবা WhatsApp এ সরাসরি যোগাযোগ করুন।
            </p>

            <div class="flex flex--center flex--gap-lg" style="flex-wrap: wrap; justify-content: center;">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary btn--lg">
                    🏠 হোমপেজে যান
                </a>
                <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--whatsapp btn--lg" target="_blank" rel="noopener">
                    💬 WhatsApp
                </a>
            </div>

            <div style="margin-top: var(--space-3xl);">
                <h3 style="margin-bottom: var(--space-lg);">জনপ্রিয় পেজসমূহ</h3>
                <div class="grid grid--3" style="max-width: 700px; margin: 0 auto;">
                    <a href="<?php echo esc_url( home_url( '/cabins/' ) ); ?>" class="card" style="text-align: center; padding: var(--space-lg);">
                        <div style="font-size: 32px; margin-bottom: var(--space-sm);">🛏️</div>
                        কেবিন ও রেট
                    </a>
                    <a href="<?php echo esc_url( home_url( '/trip-schedule/' ) ); ?>" class="card" style="text-align: center; padding: var(--space-lg);">
                        <div style="font-size: 32px; margin-bottom: var(--space-sm);">📅</div>
                        ট্রিপ সিডিউল
                    </a>
                    <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>" class="card" style="text-align: center; padding: var(--space-lg);">
                        <div style="font-size: 32px; margin-bottom: var(--space-sm);">❓</div>
                        FAQ
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
