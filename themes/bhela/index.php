<?php
/**
 * Main fallback template.
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<h1><?php echo is_home() ? esc_html__( 'ব্লগ', 'bhela' ) : wp_kses_post( get_the_archive_title() ); ?></h1>
</div></section>

<section class="section"><div class="container">
	<?php if ( have_posts() ) : ?>
		<div class="posts-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article class="post-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'bhela-card' ); ?></a>
					<?php endif; ?>
					<div class="post-card__body">
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<a href="<?php the_permalink(); ?>">পড়ুন →</a>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<div style="margin-top:2rem"><?php the_posts_pagination(); ?></div>
	<?php else : ?>
		<p><?php esc_html_e( 'কোনো কনটেন্ট পাওয়া যায়নি।', 'bhela' ); ?></p>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>
