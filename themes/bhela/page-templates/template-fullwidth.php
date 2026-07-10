<?php
/**
 * Template Name: BHELA — Full Width (Elementor)
 * Template Post Type: page
 *
 * Edge-to-edge canvas with the theme header & footer.
 * Perfect for Elementor sections; the fixed nav overlays the top
 * just like the homepage hero (start with a tall dark section).
 *
 * @package Bhela
 */

get_header();
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
get_footer();
