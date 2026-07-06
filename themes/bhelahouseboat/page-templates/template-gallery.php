<?php
/**
 * Template Name: Gallery
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>গ্যালারি</h1>
            <p>ভেলা হাউসবোট ও টাঙ্গুয়ার হাওরের অসাধারণ মুহূর্তসমূহ</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="gallery-grid" id="gallery-grid">
                <?php
                $gallery_images = array(
                    array( 'file' => 'boat/exterior-1.jpg', 'alt' => 'ভেলা হাউসবোট — Exterior' ),
                    array( 'file' => 'boat/cabin-1.jpg', 'alt' => 'প্রশস্ত Family Cabin' ),
                    array( 'file' => 'boat/rooftop-1.jpg', 'alt' => 'Rooftop Lounge' ),
                    array( 'file' => 'spots/spot-1.jpg', 'alt' => 'টাঙ্গুয়ার হাওর' ),
                    array( 'file' => 'spots/spot-2.jpg', 'alt' => 'জাদুকাটা নদী' ),
                    array( 'file' => 'spots/spot-3.jpg', 'alt' => 'বারিক্কা টিলা' ),
                    array( 'file' => 'spots/spot-4.jpg', 'alt' => 'নীলাদ্রি লেক' ),
                    array( 'file' => 'spots/spot-5.jpg', 'alt' => 'ওয়াচ টাওয়ার' ),
                    array( 'file' => 'food/food-spread.jpg', 'alt' => 'দেশীয় প্রিমিয়াম খাবার' ),
                    array( 'file' => 'spots/spot-6.jpg', 'alt' => 'শিমুল বাগান' ),
                    array( 'file' => 'spots/spot-7.jpg', 'alt' => 'টেকেরঘাট' ),
                    array( 'file' => 'hero/hero-haor.jpg', 'alt' => 'হাওরের সূর্যাস্ত' ),
                );

                foreach ( $gallery_images as $img ) :
                ?>
                    <div class="gallery-item reveal">
                        <img src="<?php echo BHELA_URI; ?>/assets/images/<?php echo $img['file']; ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy" width="400" height="300">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
