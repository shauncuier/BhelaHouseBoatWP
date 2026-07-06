<?php
/**
 * Template Name: Trip Schedule
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>ট্রিপ সিডিউল</h1>
            <p>আসন্ন ট্রিপের তারিখ ও স্ট্যাটাস — এখনই বুক করুন!</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="section__header reveal">
                <span class="section__subtitle">আসন্ন ট্রিপ</span>
                <h2 class="section__title">তারিখ নির্বাচন করুন</h2>
                <p class="section__desc">Weekday-তে ২০% পর্যন্ত ছাড়! 🎉 সিট সীমিত — তাড়াতাড়ি বুক করুন।</p>
            </div>

            <?php
            // Try to fetch from CPT first
            $trips = new WP_Query( array(
                'post_type'      => 'bhela_trip',
                'posts_per_page' => 12,
                'meta_key'       => '_bhela_start_date',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_bhela_start_date',
                        'value'   => date( 'Y-m-d' ),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ),
                ),
            ) );

            if ( $trips->have_posts() ) :
            ?>
                <div class="schedule-grid">
                    <?php while ( $trips->have_posts() ) : $trips->the_post(); 
                        $start = get_post_meta( get_the_ID(), '_bhela_start_date', true );
                        $end = get_post_meta( get_the_ID(), '_bhela_end_date', true );
                        $status = get_post_meta( get_the_ID(), '_bhela_trip_status', true );
                        $day_type = get_post_meta( get_the_ID(), '_bhela_day_type', true );
                        $note = get_post_meta( get_the_ID(), '_bhela_trip_note', true );
                    ?>
                        <div class="schedule-card schedule-card--<?php echo esc_attr( $day_type ); ?> reveal">
                            <div class="schedule-card__dates">
                                <?php echo date_i18n( 'd M Y', strtotime( $start ) ); ?> → <?php echo date_i18n( 'd M Y', strtotime( $end ) ); ?>
                            </div>
                            <div class="schedule-card__day">
                                <?php echo get_the_title(); ?>
                            </div>
                            <div class="schedule-card__badges">
                                <span class="badge badge--<?php echo esc_attr( $day_type ); ?>">
                                    <?php echo ucfirst( $day_type ); ?>
                                </span>
                                <span class="badge badge--<?php echo esc_attr( $status ); ?>">
                                    <?php 
                                    $status_labels = array( 'available' => '✅ Available', 'filling' => '🔥 Filling Fast', 'booked' => '❌ Fully Booked' );
                                    echo $status_labels[ $status ] ?? $status;
                                    ?>
                                </span>
                                <?php if ( $note ) : ?>
                                    <span class="badge badge--fullmoon"><?php echo esc_html( $note ); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( $status !== 'booked' ) : ?>
                                <a href="<?php echo esc_url( bhela_whatsapp_link( 'আমি ' . date_i18n( 'd M Y', strtotime( $start ) ) . ' তারিখের ট্রিপ বুক করতে চাই।' ) ); ?>" class="btn btn--whatsapp btn--sm" target="_blank" rel="noopener" style="margin-top: auto;">
                                    💬 বুক করুন
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

            <?php else : ?>

                <!-- Placeholder schedule when no CPT posts exist -->
                <div class="schedule-grid">
                    <div class="schedule-card schedule-card--weekend reveal">
                        <div class="schedule-card__dates">Coming Soon</div>
                        <div class="schedule-card__day">ট্রিপের তারিখ শীঘ্রই আপডেট হবে</div>
                        <div class="schedule-card__badges">
                            <span class="badge badge--available">✅ Available</span>
                        </div>
                        <a href="<?php echo esc_url( bhela_whatsapp_link( 'আমি আসন্ন ট্রিপের তারিখ জানতে চাই।' ) ); ?>" class="btn btn--whatsapp btn--sm" target="_blank" rel="noopener" style="margin-top: auto;">
                            💬 তারিখ জানুন
                        </a>
                    </div>
                </div>

                <div style="text-align: center; margin-top: var(--space-2xl);">
                    <p style="color: var(--color-text-muted);">আসন্ন ট্রিপের তারিখ জানতে WhatsApp এ যোগাযোগ করুন</p>
                </div>

            <?php endif; ?>
        </div>
    </section>

    <!-- CTA -->
    <?php get_template_part( 'template-parts/cta-section' ); ?>

</main>

<?php get_footer(); ?>
