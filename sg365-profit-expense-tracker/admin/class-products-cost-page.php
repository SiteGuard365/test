<?php
/**
 * Product costs page.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Products_Cost_Page {

    /**
     * Render page.
     *
     * @return void
     */
    public static function render(): void {
        WCPI_Security::verify_access();
        $page            = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page        = 20;
        $search          = WCPI_Security::text( $_GET['s'] ?? '' );
        $type            = WCPI_Security::text( $_GET['product_type'] ?? '' );
        $status_filter   = WCPI_Security::text( $_GET['status_filter'] ?? '' );
        $missing_filter  = WCPI_Security::text( $_GET['missing_cost_filter'] ?? '' );
        $low_margin_only = 'yes' === WCPI_Security::text( $_GET['low_margin_only'] ?? '' );

        $args = array(
            'status' => array( 'publish', 'private' ),
            'limit'  => -1,
            'page'   => 1,
            'return' => 'objects',
        );

        if ( $search ) {
            $args['search'] = '*' . $search . '*';
        }

        if ( $type ) {
            $args['type'] = $type;
        }

        $all_products = wc_get_products( $args );
        $products     = array_values(
            array_filter(
                $all_products,
                static function ( $product ) use ( $status_filter, $missing_filter, $low_margin_only ): bool {
                    if ( ! $product instanceof WC_Product ) {
                        return false;
                    }

                    $status = self::product_cost_status( $product );
                    if ( '' !== $status_filter && $status_filter !== $status ) {
                        return false;
                    }

                    if ( 'missing_only' === $missing_filter && ! in_array( $status, array( 'missing', 'warning', 'incomplete' ), true ) ) {
                        return false;
                    }

                    if ( 'healthy_only' === $missing_filter && 'healthy' !== $status ) {
                        return false;
                    }

                    if ( $low_margin_only && ! self::is_low_margin_product( $product ) ) {
                        return false;
                    }

                    return true;
                }
            )
        );

        $total_count = count( $products );
        $total_pages  = (int) max( 1, ceil( $total_count / $per_page ) );
        $offset       = ( $page - 1 ) * $per_page;
        $products     = array_slice( $products, $offset, $per_page );

        include WCPI_PLUGIN_DIR . 'admin/views/product-costs.php';
    }

    /**
     * Product cost status.
     *
     * @param WC_Product $product Product.
     * @return string
     */
    private static function product_cost_status( WC_Product $product ): string {
        $row = WCPI_Product_Cost_Manager::get_cost_row( $product->get_id(), 0 );

        if ( empty( $row['id'] ) ) {
            return 'missing';
        }

        $parts_total = (float) $row['cost_price'] + (float) $row['packaging_cost'] + (float) $row['handling_cost'] + (float) $row['extra_cost'];
        if ( abs( (float) $row['total_unit_cost'] - $parts_total ) > 0.0001 ) {
            return 'incomplete';
        }

        if ( (float) $row['total_unit_cost'] <= 0 ) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Whether product margin is below configured threshold.
     *
     * @param WC_Product $product Product.
     * @return bool
     */
    private static function is_low_margin_product( WC_Product $product ): bool {
        $price     = (float) $product->get_price();
        $unit_cost = WCPI_Product_Cost_Manager::get_unit_cost( $product->get_id(), 0 );

        if ( $price <= 0 || $unit_cost <= 0 ) {
            return false;
        }

        $margin = ( ( $price - $unit_cost ) / $price ) * 100;

        return $margin < (float) WCPI_Settings_Manager::get( 'general', 'margin_threshold', 20 );
    }
}
