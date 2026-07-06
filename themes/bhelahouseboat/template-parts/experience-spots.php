<?php
/**
 * Template Part: Experience Spots
 *
 * @package BhelaHouseboat
 */

$spots = bhela_get_spots();
?>

<div class="grid grid--4" id="spots-grid">
    <?php foreach ( $spots as $index => $spot ) : ?>
        <div class="spot-card reveal reveal--delay-<?php echo ( $index % 4 ) + 1; ?>">
            <img src="<?php echo BHELA_URI; ?>/assets/images/spots/spot-<?php echo $index + 1; ?>.jpg" alt="<?php echo esc_attr( $spot['name'] ); ?>" class="spot-card__image" loading="lazy" width="400" height="533">
            <div class="spot-card__overlay">
                <span class="spot-card__icon"><?php echo $spot['icon']; ?></span>
                <h3 class="spot-card__name"><?php echo esc_html( $spot['name'] ); ?></h3>
                <p class="spot-card__desc"><?php echo esc_html( $spot['desc'] ); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
