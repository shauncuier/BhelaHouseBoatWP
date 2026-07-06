<?php
/**
 * Template Part: Cabin Cards
 *
 * @package BhelaHouseboat
 */

$cabins = bhela_get_cabins();
?>

<div class="grid grid--3" id="cabin-cards-grid">
    <?php foreach ( $cabins as $index => $cabin ) : ?>
        <div class="cabin-card reveal reveal--delay-<?php echo ( $index % 4 ) + 1; ?>">
            <!-- Tier Badge -->
            <span class="cabin-card__tier" style="background: <?php echo $cabin['color']; ?>;">
                <?php echo $cabin['icon']; ?> <?php echo esc_html( $cabin['name'] ); ?>
            </span>

            <!-- Weekday Badge -->
            <span class="cabin-card__weekday-badge">−২০% Weekday</span>

            <!-- Image -->
            <img src="<?php echo BHELA_URI; ?>/assets/images/cabins/cabin-<?php echo $index + 1; ?>.jpg" alt="<?php echo esc_attr( $cabin['name_bn'] ); ?> কেবিন" class="cabin-card__image" loading="lazy" width="800" height="500">

            <div class="cabin-card__body">
                <h3 class="cabin-card__name"><?php echo esc_html( $cabin['name_bn'] ); ?></h3>

                <div class="cabin-card__sharing">
                    <?php for ( $i = 0; $i < $cabin['sharing']; $i++ ) : ?>👤<?php endfor; ?>
                    <span>× <?php echo $cabin['sharing']; ?> জন/কেবিন</span>
                </div>

                <!-- Amenities -->
                <div class="cabin-card__amenities">
                    <?php foreach ( $cabin['amenities'] as $amenity ) : ?>
                        <span class="cabin-card__amenity">✓ <?php echo esc_html( $amenity ); ?></span>
                    <?php endforeach; ?>
                </div>

                <p class="cabin-card__desc"><?php echo esc_html( $cabin['desc'] ); ?></p>

                <!-- Prices -->
                <div class="cabin-card__prices">
                    <div>
                        <div class="cabin-card__price-label">Holiday / Weekend</div>
                        <div class="cabin-card__price-value">
                            ৳<?php echo number_format( $cabin['holiday'] ); ?>
                            <span class="cabin-card__price-per">/জন</span>
                        </div>
                    </div>
                    <div>
                        <div class="cabin-card__price-label">Weekday ✨</div>
                        <div class="cabin-card__price-value cabin-card__price-value--weekday">
                            ৳<?php echo number_format( $cabin['weekday'] ); ?>
                            <span class="cabin-card__price-per">/জন</span>
                        </div>
                    </div>
                </div>

                <a href="<?php echo esc_url( bhela_whatsapp_link( 'আমি ' . $cabin['name_bn'] . ' কেবিন (' . $cabin['sharing'] . ' জন) বুক করতে চাই।' ) ); ?>" class="btn btn--whatsapp btn--full" target="_blank" rel="noopener">
                    <span class="btn__icon">💬</span> এই কেবিন বুক করুন
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
