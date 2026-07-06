<?php
/**
 * Template Name: FAQ
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
$faqs = bhela_get_faqs();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>জিজ্ঞাসা (FAQ)</h1>
            <p>ভেলা হাউসবোট সম্পর্কে সচরাচর জিজ্ঞাসিত প্রশ্ন ও উত্তর</p>
        </div>
    </div>

    <section class="section">
        <div class="container container--narrow">

            <?php $count = 0; ?>
            <?php foreach ( $faqs as $category => $questions ) : ?>
                <div class="faq-category reveal">
                    <h2 class="faq-category__title"><?php echo esc_html( $category ); ?></h2>

                    <?php foreach ( $questions as $faq ) : ?>
                        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                            <button class="faq-question" aria-expanded="false" id="faq-full-q-<?php echo $count; ?>">
                                <span itemprop="name"><?php echo esc_html( $faq['q'] ); ?></span>
                                <span class="faq-question__icon" aria-hidden="true">▼</span>
                            </button>
                            <div class="faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer" id="faq-full-a-<?php echo $count; ?>">
                                <div class="faq-answer__inner" itemprop="text">
                                    <?php echo esc_html( $faq['a'] ); ?>
                                </div>
                            </div>
                        </div>
                        <?php $count++; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-section__title reveal">আরো প্রশ্ন আছে?</h2>
            <p class="cta-section__desc reveal">WhatsApp এ সরাসরি জিজ্ঞাসা করুন — ২ মিনিটে উত্তর পাবেন</p>
            <div class="cta-section__buttons reveal">
                <a href="<?php echo esc_url( bhela_whatsapp_link( 'আমার একটি প্রশ্ন আছে ভেলা হাউসবোট সম্পর্কে।' ) ); ?>" class="btn btn--whatsapp btn--lg" target="_blank" rel="noopener">
                    <span class="btn__icon">💬</span> WhatsApp এ জিজ্ঞাসা করুন
                </a>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
