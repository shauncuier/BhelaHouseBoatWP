<?php
/**
 * Main fallback template — blog index (হাওর জার্নাল) and archives.
 *
 * @package Bhela
 */

get_header();
?>
<section class="page-hero"><div class="container">
	<?php if ( is_home() ) : ?>
		<h1>হাওর জার্নাল</h1>
		<p>টাঙ্গুয়ার হাওর ভ্রমণের গাইড, টিপস আর ভেলার খবর — বুকিংয়ের আগে যা জানলে ট্রিপ আরও সুন্দর হয়।</p>
	<?php elseif ( is_search() ) : ?>
		<h1><?php printf( esc_html__( 'খোঁজার ফলাফল: %s', 'bhela' ), esc_html( get_search_query() ) ); ?></h1>
	<?php else : ?>
		<h1><?php echo wp_kses_post( get_the_archive_title() ); ?></h1>
		<?php the_archive_description( '<p>', '</p>' ); ?>
	<?php endif; ?>
</div></section>

<section class="section"><div class="container">
	<?php if ( have_posts() ) : ?>
		<div class="posts-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$bhela_cats = get_the_category();
				?>
				<article class="post-card reveal">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'bhela-card' ); ?></a>
					<?php endif; ?>
					<div class="post-card__body">
						<div class="post-card__meta">
							<?php if ( $bhela_cats ) : ?>
								<a class="tag tag--weekend" href="<?php echo esc_url( get_category_link( $bhela_cats[0] ) ); ?>"><?php echo esc_html( $bhela_cats[0]->name ); ?></a>
							<?php endif; ?>
							<span><?php echo esc_html( get_the_date() ); ?></span>
							<span aria-hidden="true">·</span>
							<span><?php echo esc_html( bhela_reading_time() ); ?></span>
						</div>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<a class="post-card__more" href="<?php the_permalink(); ?>">পড়ুন →</a>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<div class="bhela-pagination"><?php the_posts_pagination( array( 'prev_text' => '←', 'next_text' => '→', 'mid_size' => 1 ) ); ?></div>
	<?php else : ?>
		<p><?php esc_html_e( 'কোনো কনটেন্ট পাওয়া যায়নি।', 'bhela' ); ?></p>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>
