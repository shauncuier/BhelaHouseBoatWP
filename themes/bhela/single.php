<?php
/**
 * Single post template — হাওর জার্নাল article.
 *
 * @package Bhela
 */

get_header();
while ( have_posts() ) :
	the_post();
	$bhela_cats = get_the_category();
	?>
	<section class="page-hero"><div class="container">
		<div class="post-hero-meta">
			<?php if ( $bhela_cats ) : ?>
				<a class="tag" href="<?php echo esc_url( get_category_link( $bhela_cats[0] ) ); ?>"><?php echo esc_html( $bhela_cats[0]->name ); ?></a>
			<?php endif; ?>
			<span><?php echo esc_html( get_the_date() ); ?></span>
			<span aria-hidden="true">·</span>
			<span><?php echo esc_html( bhela_reading_time() ); ?></span>
		</div>
		<h1><?php the_title(); ?></h1>
	</div></section>

	<section class="section"><div class="container">
		<div class="entry-content">
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'bhela-wide', array( 'style' => 'border-radius:20px;margin-bottom:1.6rem' ) );
			}
			the_content();

			$bhela_tags = get_the_tags();
			if ( $bhela_tags ) :
				?>
				<div class="post-tags">
					<?php foreach ( $bhela_tags as $bhela_tag ) : ?>
						<a class="tag tag--weekend" href="<?php echo esc_url( get_tag_link( $bhela_tag ) ); ?>">#<?php echo esc_html( $bhela_tag->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php
		$bhela_prev = get_previous_post();
		$bhela_next = get_next_post();
		if ( $bhela_prev || $bhela_next ) :
			?>
			<nav class="post-nav" aria-label="<?php esc_attr_e( 'পোস্ট নেভিগেশন', 'bhela' ); ?>">
				<?php if ( $bhela_prev ) : ?>
					<a class="prev" href="<?php echo esc_url( get_permalink( $bhela_prev ) ); ?>">
						<small>← আগের লেখা</small>
						<strong><?php echo esc_html( get_the_title( $bhela_prev ) ); ?></strong>
					</a>
				<?php else : ?>
					<span></span>
				<?php endif; ?>
				<?php if ( $bhela_next ) : ?>
					<a class="next" href="<?php echo esc_url( get_permalink( $bhela_next ) ); ?>">
						<small>পরের লেখা →</small>
						<strong><?php echo esc_html( get_the_title( $bhela_next ) ); ?></strong>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

		<?php
		$bhela_related = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 3,
			'post__not_in'   => array( get_the_ID() ),
			'cat'            => $bhela_cats ? (int) $bhela_cats[0]->term_id : 0,
			'orderby'        => 'rand',
			'no_found_rows'  => true,
		) );
		if ( $bhela_related->have_posts() ) :
			?>
			<div class="related-posts">
				<h2>আরও পড়ুন</h2>
				<div class="posts-grid">
					<?php
					while ( $bhela_related->have_posts() ) :
						$bhela_related->the_post();
						?>
						<article class="post-card">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'bhela-card' ); ?></a>
							<?php endif; ?>
							<div class="post-card__body">
								<div class="post-card__meta">
									<span><?php echo esc_html( get_the_date() ); ?></span>
									<span aria-hidden="true">·</span>
									<span><?php echo esc_html( bhela_reading_time() ); ?></span>
								</div>
								<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<a class="post-card__more" href="<?php the_permalink(); ?>">পড়ুন →</a>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
			</div>
			<?php
			wp_reset_postdata();
		endif;
		?>

		<div class="cta-banner" style="margin-top:3.5rem">
			<h2>হাওর নিজে দেখতে চান?</h2>
			<p>২ দিন ১ রাতের অল-ইনক্লুসিভ প্যাকেজ — থাকা, খাওয়া আর হাওরের সেরা ৭টি স্পট।</p>
			<div class="btn-row">
				<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>">বুক করুন</a>
				<a class="btn btn--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">WhatsApp-এ কথা বলুন</a>
			</div>
		</div>
	</div></section>
	<?php
endwhile;
get_footer();
