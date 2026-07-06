<?php
/**
 * Index Template (Fallback)
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">
    <div class="page-hero">
        <div class="container">
            <h1><?php echo is_home() ? 'ব্লগ' : 'পেজ'; ?></h1>
        </div>
    </div>

    <div class="section">
        <div class="container">
            <?php if ( have_posts() ) : ?>
                <div class="grid grid--2">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <article class="card reveal">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <img src="<?php echo get_the_post_thumbnail_url( get_the_ID(), 'bhela-card' ); ?>" alt="<?php the_title_attribute(); ?>" class="card__image" loading="lazy">
                            <?php endif; ?>
                            <div class="card__body">
                                <h3 class="card__title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>
                                <p class="card__desc"><?php the_excerpt(); ?></p>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p>কোনো পোস্ট পাওয়া যায়নি।</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>
