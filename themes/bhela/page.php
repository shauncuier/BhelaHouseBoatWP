<?php
/**
 * Default page template.
 *
 * @package Bhela
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero"><div class="container"><h1><?php the_title(); ?></h1></div></section>
	<section class="section"><div class="container">
		<div class="entry-content"><?php the_content(); ?></div>
	</div></section>
	<?php
endwhile;
get_footer();
