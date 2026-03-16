<?php
/**
 * Order synchronization.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Order_Sync {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'woocommerce_new_order', array( $this, 'sync_order_by_id' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order_by_id' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order_by_id' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'sync_order_by_id' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'sync_order_by_id' ) );
        add_action( 'woocommerce_refund_created', array( $this, 'sync_refund' ), 10, 2 );
        add_action( 'save_post_product', array( $this, 'clear_product_analytics' ), 30, 1 );
        add_action( 'save_post_product_variation', array( $this, 'clear_product_analytics' ), 30, 1 );
    }

    /**
     * Sync order by id.
     *
     * @param int $order_id Order.
     * @return void
     */
    public function sync_order_by_id( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        foreach ( WCPI_Refund_Handler::impacted_dates( $order ) as $date ) {
            WCPI_Daily_Summary::rebuild_date( $date );
        }

        WCPI_Settings_Manager::set( 'general', 'last_sync_status', 'Order #' . $order_id . ' synced on ' . current_time( 'mysql' ) );
        WCPI_Logger::log( 'info', 'order_sync', 'Order synced.', array( 'order_id' => $order_id ) );
        WCPI_Aggregates::purge();
    }

    /**
     * Sync refund event.
     *
     * @param int $refund_id Refund.
     * @param array $args Args.
     * @return void
     */
    public function sync_refund( int $refund_id, array $args ): void {
        $refund = wc_get_order( $refund_id );
        if ( ! $refund instanceof WC_Order_Refund ) {
            return;
        }

        $parent_id = $refund->get_parent_id();
        if ( $parent_id ) {
            $this->sync_order_by_id( $parent_id );
        }
    }

    /**
     * Clear analytics cache on product changes.
     *
     * @param int $post_id Product.
     * @return void
     */
    public function clear_product_analytics( int $post_id ): void {
        if ( $post_id > 0 ) {
            WCPI_Aggregates::purge();
            WCPI_Cache::flush_group();
        }
    }
}
