<?php
/**
 * Template Part: FAQ Accordion (Homepage preview — top questions only)
 *
 * @package BhelaHouseboat
 */

$faqs = bhela_get_faqs();
$count = 0;
$max_display = 6; // Show only 6 on homepage
?>

<div class="faq-list" id="faq-list" itemscope itemtype="https://schema.org/FAQPage">
    <?php foreach ( $faqs as $category => $questions ) : ?>
        <?php foreach ( $questions as $faq ) : ?>
            <?php if ( $count >= $max_display && ! is_page_template( 'page-templates/template-faq.php' ) ) break 2; ?>

            <div class="faq-item reveal" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                <button class="faq-question" aria-expanded="false" id="faq-q-<?php echo $count; ?>">
                    <span itemprop="name"><?php echo esc_html( $faq['q'] ); ?></span>
                    <span class="faq-question__icon" aria-hidden="true">▼</span>
                </button>
                <div class="faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer" id="faq-a-<?php echo $count; ?>">
                    <div class="faq-answer__inner" itemprop="text">
                        <?php echo esc_html( $faq['a'] ); ?>
                    </div>
                </div>
            </div>

            <?php $count++; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
