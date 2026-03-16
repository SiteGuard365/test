<?php
/**
 * Filters partial.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcpi-card wcpi-filter-bar">
    <label>
        <span><?php esc_html_e( 'Preset', WCPI_TEXT_DOMAIN ); ?></span>
        <select id="<?php echo esc_attr( $context ); ?>-preset">
            <?php foreach ( WCPI_Helpers::date_presets() as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_preset ?? 'last_30_days', $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span><?php esc_html_e( 'From', WCPI_TEXT_DOMAIN ); ?></span>
        <input type="date" id="<?php echo esc_attr( $context ); ?>-from" value="<?php echo esc_attr( $default_from ?? '' ); ?>">
    </label>
    <label>
        <span><?php esc_html_e( 'To', WCPI_TEXT_DOMAIN ); ?></span>
        <input type="date" id="<?php echo esc_attr( $context ); ?>-to" value="<?php echo esc_attr( $default_to ?? '' ); ?>">
    </label>
    <button class="button button-primary" id="<?php echo esc_attr( $context ); ?>-apply"><?php esc_html_e( 'Apply', WCPI_TEXT_DOMAIN ); ?></button>
</div>
