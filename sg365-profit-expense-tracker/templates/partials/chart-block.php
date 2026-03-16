<?php
/**
 * Chart block partial.
 *
 * @var string $title
 * @var string $canvas_id
 * @var string $height
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcpi-card wcpi-chart-card">
    <div class="wcpi-card-header">
        <h2><?php echo esc_html( $title ); ?></h2>
    </div>
    <div class="wcpi-chart-wrap" style="height: <?php echo esc_attr( $height ?? '320px' ); ?>;">
        <canvas id="<?php echo esc_attr( $canvas_id ); ?>"></canvas>
    </div>
</div>
