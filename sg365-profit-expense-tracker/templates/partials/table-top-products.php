<?php
/**
 * Top products partial.
 *
 * @var array $rows
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="widefat striped wcpi-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Product', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'SKU', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Qty', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Revenue', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Cost', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Profit', WCPI_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Margin %', WCPI_TEXT_DOMAIN ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $rows ) ) : ?>
            <tr><td colspan="7"><?php esc_html_e( 'No data available.', WCPI_TEXT_DOMAIN ); ?></td></tr>
        <?php else : ?>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row['name'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $row['sku'] ?? '' ); ?></td>
                    <td><?php echo esc_html( (string) ( $row['quantity_sold'] ?? 0 ) ); ?></td>
                    <td><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) ( $row['revenue'] ?? 0 ) ) ); ?></td>
                    <td><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) ( $row['cost'] ?? 0 ) ) ); ?></td>
                    <td class="<?php echo ( (float) ( $row['profit'] ?? 0 ) >= 0 ) ? 'wcpi-positive' : 'wcpi-negative'; ?>"><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) ( $row['profit'] ?? 0 ) ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) ( $row['margin_percent'] ?? 0 ), 2 ) . '%' ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
