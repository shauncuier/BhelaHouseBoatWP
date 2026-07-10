<?php
/**
 * Theme header.
 *
 * @package Bhela
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#content"><?php esc_html_e( 'মূল কনটেন্টে যান', 'bhela' ); ?></a>

<nav class="site-nav" id="site-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'bhela' ); ?>">
	<div class="container site-nav__inner">
		<a class="site-nav__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.png' ); ?>" alt="BHELA logo">
			<span class="site-nav__brand-text">
				<strong>BHELA</strong>
				<span>The Haor Exclusive</span>
			</span>
		</a>

		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'site-nav__menu',
				'menu_id'        => 'site-menu',
				'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s<li><a class="btn btn--cta site-nav__book" href="' . esc_url( bhela_page_url( 'book-now' ) ) . '">বুক করুন</a></li></ul>',
			) );
		} else {
			bhela_fallback_menu();
		}
		?>

		<button class="site-nav__toggle" id="nav-toggle" aria-expanded="false" aria-controls="site-menu" aria-label="<?php esc_attr_e( 'Menu', 'bhela' ); ?>">
			<span></span><span></span><span></span>
		</button>
	</div>
</nav>
<main id="content">

