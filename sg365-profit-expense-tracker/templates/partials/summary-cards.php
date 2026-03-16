<?php
/**
 * Summary cards partial.
 *
 * @var array $cards
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcpi-grid wcpi-summary-grid">
    <?php foreach ( $cards as $card ) : ?>
        <div class="wcpi-card wcpi-summary-card <?php echo esc_attr( $card['class'] ?? '' ); ?>">
            <span class="wcpi-label"><?php echo esc_html( $card['label'] ); ?></span>
            <strong class="wcpi-value"><?php echo wp_kses_post( $card['value'] ); ?></strong>
            <?php if ( ! empty( $card['hint'] ) ) : ?>
                <span class="wcpi-hint"><?php echo esc_html( $card['hint'] ); ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
