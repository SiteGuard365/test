<?php
/**
 * Profit calculator.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Profit_Calculator {

    /**
     * Calculate one order metrics.
     *
     * @param WC_Order $order Order.
     * @return array<string, float|int>
     */
    public static function order_metrics( WC_Order $order ): array {
        $include_tax      = 'yes' === WCPI_Settings_Manager::get( 'general', 'include_tax', 'no' );
        $include_shipping = 'yes' === WCPI_Settings_Manager::get( 'general', 'include_shipping', 'yes' );

        $gross_revenue   = 0.0;
        $discounts_total = 0.0;
        $refunds_total   = (float) $order->get_total_refunded();
        $shipping_total  = (float) $order->get_shipping_total();
        $tax_total       = (float) $order->get_total_tax();
        $product_cost    = 0.0;
        $items_sold      = 0;

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $qty          = (int) $item->get_quantity();
            $product_id   = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();
            $line_total   = (float) $item->get_total();
            $line_tax     = (float) $item->get_total_tax();
            $subtotal     = (float) $item->get_subtotal();
            $subtotal_tax = (float) $item->get_subtotal_tax();

            $gross_revenue   += $line_total + ( $include_tax ? $line_tax : 0 );
            $discounts_total += max( 0, ( $subtotal + ( $include_tax ? $subtotal_tax : 0 ) ) - ( $line_total + ( $include_tax ? $line_tax : 0 ) ) );
            $product_cost    += self::item_cost( $product_id, $variation_id, $qty, $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null );
            $items_sold      += $qty;
        }

        if ( $include_shipping ) {
            $gross_revenue += $shipping_total;
        }

        $gross_profit   = $gross_revenue - $product_cost;
        $net_profit     = $gross_profit - $refunds_total;
        $margin_percent = $gross_revenue > 0 ? ( $net_profit / $gross_revenue ) * 100 : 0;

        return array(
            'orders_count'       => 1,
            'items_sold'         => $items_sold,
            'gross_revenue'      => round( $gross_revenue, 6 ),
            'discounts_total'    => round( $discounts_total, 6 ),
            'refunds_total'      => round( $refunds_total, 6 ),
            'shipping_collected' => round( $shipping_total, 6 ),
            'tax_collected'      => round( $tax_total, 6 ),
            'product_cost_total' => round( $product_cost, 6 ),
            'gross_profit'       => round( $gross_profit, 6 ),
            'net_profit'         => round( $net_profit, 6 ),
            'margin_percent'     => round( $margin_percent, 4 ),
        );
    }

    /**
     * Item cost.
     *
     * @param int         $product_id Product.
     * @param int         $variation_id Variation.
     * @param int         $qty Qty.
     * @param string|null $order_date Date.
     * @return float
     */
    public static function item_cost( int $product_id, int $variation_id, int $qty, ?string $order_date = null ): float {
        $unit_cost = WCPI_Cost_History::get_effective_cost( $product_id, $variation_id, $order_date );
        return round( $unit_cost * $qty, 6 );
    }
}
