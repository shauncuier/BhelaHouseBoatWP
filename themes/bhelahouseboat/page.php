<?php
/**
 * Page Template (Generic)
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">
    <div class="page-hero">
        <div class="container">
            <h1><?php the_title(); ?></h1>
        </div>
    </div>

    <div class="section">
        <div class="container container--narrow">
            <div class="policy-content">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php the_content(); ?>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
