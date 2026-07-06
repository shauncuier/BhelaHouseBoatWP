<?php
/**
 * Single Post Template
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">
    <div class="page-hero">
        <div class="container">
            <h1><?php the_title(); ?></h1>
            <p><?php echo get_the_date( 'F j, Y' ); ?></p>
        </div>
    </div>

    <div class="section">
        <div class="container container--narrow">
            <article class="policy-content">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <img src="<?php echo get_the_post_thumbnail_url( get_the_ID(), 'bhela-hero' ); ?>" alt="<?php the_title_attribute(); ?>" style="border-radius: var(--radius-lg); margin-bottom: var(--space-xl);">
                    <?php endif; ?>
                    <?php the_content(); ?>
                <?php endwhile; ?>
            </article>
        </div>
    </div>
</main>

<?php get_footer(); ?>
