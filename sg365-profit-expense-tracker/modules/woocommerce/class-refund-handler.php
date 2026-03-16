<?php
/**
 * Refund helper.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Refund_Handler {

    /**
     * Get impacted dates for an order/refund event.
     *
     * @param WC_Order|WC_Order_Refund $order Order.
     * @return array<int, string>
     */
    public static function impacted_dates( $order ): array {
        $dates = array();

        if ( method_exists( $order, 'get_date_created' ) && $order->get_date_created() ) {
            $dates[] = $order->get_date_created()->date_i18n( 'Y-m-d' );
        }

        if ( method_exists( $order, 'get_date_modified' ) && $order->get_date_modified() ) {
            $dates[] = $order->get_date_modified()->date_i18n( 'Y-m-d' );
        }

        return array_values( array_unique( array_filter( $dates ) ) );
    }
}
