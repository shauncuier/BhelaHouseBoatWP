<?php
/**
 * Default page template. Elementor-built pages render full-width
 * without the theme hero/content constraints.
 *
 * @package Bhela
 */

get_header();
while ( have_posts() ) :
	the_post();

	if ( function_exists( 'bhela_is_elementor_page' ) && bhela_is_elementor_page( get_the_ID() ) ) :
		// Elementor owns the layout — output edge-to-edge.
		the_content();
	else :
		?>
		<section class="page-hero"><div class="container"><h1><?php the_title(); ?></h1></div></section>
		<section class="section"><div class="container">
			<div class="entry-content"><?php the_content(); ?></div>
		</div></section>
		<?php
	endif;
endwhile;
get_footer();
