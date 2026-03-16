<?php
/**
 * Daily summary engine.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Daily_Summary {

    /**
     * Rebuild single date.
     *
     * @param string $date Date Y-m-d.
     * @return void
     */
    public static function rebuild_date( string $date ): void {
        global $wpdb;
        $table = WCPI_DB::table( 'daily_summary' );

        $metrics = WCPI_Analytics_Engine::calculate_date( $date );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE summary_date = %s", $date )
        );

        $payload = array(
            'summary_date'       => $date,
            'orders_count'       => (int) $metrics['orders_count'],
            'items_sold'         => (int) $metrics['items_sold'],
            'gross_revenue'      => $metrics['gross_revenue'],
            'discounts_total'    => $metrics['discounts_total'],
            'refunds_total'      => $metrics['refunds_total'],
            'shipping_collected' => $metrics['shipping_collected'],
            'tax_collected'      => $metrics['tax_collected'],
            'product_cost_total' => $metrics['product_cost_total'],
            'expenses_total'     => $metrics['expenses_total'],
            'net_profit'         => $metrics['net_profit'],
            'margin_percent'     => $metrics['margin_percent'],
            'updated_at'         => current_time( 'mysql', true ),
        );

        if ( $existing_id ) {
            $wpdb->update(
                $table,
                $payload,
                array( 'id' => (int) $existing_id ),
                array( '%s', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                $payload,
                array( '%s', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' )
            );
        }
    }

    /**
     * Rebuild date range.
     *
     * @param string $from From.
     * @param string $to To.
     * @return void
     */
    public static function rebuild_range( string $from, string $to ): void {
        $start = new DateTimeImmutable( $from );
        $end   = new DateTimeImmutable( $to );

        if ( $start > $end ) {
            $swap  = $start;
            $start = $end;
            $end   = $swap;
            $from  = $start->format( 'Y-m-d' );
            $to    = $end->format( 'Y-m-d' );
        }

        while ( $start <= $end ) {
            self::rebuild_date( $start->format( 'Y-m-d' ) );
            $start = $start->modify( '+1 day' );
        }

        WCPI_Settings_Manager::set(
            'general',
            'last_analytics_rebuild_status',
            sprintf(
                /* translators: 1: start date 2: end date */
                __( 'Rebuilt summaries for %1$s to %2$s', WCPI_TEXT_DOMAIN ),
                $from,
                $to
            )
        );
        WCPI_Settings_Manager::set( 'general', 'last_analytics_rebuild_at', current_time( 'mysql', true ) );
        WCPI_Aggregates::purge();
        WCPI_Cache::flush_group();
    }
}
