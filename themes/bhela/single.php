<?php
/**
 * Single post template.
 *
 * @package Bhela
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero"><div class="container">
		<h1><?php the_title(); ?></h1>
		<p><?php echo esc_html( get_the_date() ); ?></p>
	</div></section>
	<section class="section"><div class="container">
		<div class="entry-content">
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'bhela-wide', array( 'style' => 'border-radius:20px;margin-bottom:1.6rem' ) );
			}
			the_content();
			?>
		</div>
	</div></section>
	<?php
endwhile;
get_footer();
