<?php
/**
 * Cost history model.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Cost_History {

    /**
     * Insert history row.
     *
     * @param int    $product_id Product.
     * @param int    $variation_id Variation.
     * @param float  $old_cost Old.
     * @param float  $new_cost New.
     * @param string $reason Reason.
     * @return void
     */
    public static function add( int $product_id, int $variation_id, float $old_cost, float $new_cost, string $reason = '' ): void {
        global $wpdb;

        if ( abs( $old_cost - $new_cost ) < 0.000001 ) {
            return;
        }

        $wpdb->insert(
            WCPI_DB::table( 'cost_history' ),
            array(
                'product_id'    => $product_id,
                'variation_id'  => $variation_id,
                'old_cost'      => $old_cost,
                'new_cost'      => $new_cost,
                'change_reason' => $reason,
                'changed_by'    => get_current_user_id(),
                'effective_date'=> current_time( 'mysql', true ),
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%d', '%f', '%f', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Get applicable unit cost for a date.
     *
     * @param int         $product_id Product.
     * @param int         $variation_id Variation.
     * @param string|null $date Date Y-m-d H:i:s UTC.
     * @return float
     */
    public static function get_effective_cost( int $product_id, int $variation_id = 0, ?string $date = null ): float {
        global $wpdb;

        $date = $date ?: current_time( 'mysql', true );
        $table = WCPI_DB::table( 'cost_history' );

        if ( $variation_id > 0 ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT new_cost FROM {$table}
                    WHERE product_id = %d AND variation_id = %d AND effective_date <= %s
                    ORDER BY effective_date DESC, id DESC LIMIT 1",
                    $product_id,
                    $variation_id,
                    $date
                )
            );
            if ( $row ) {
                return (float) $row->new_cost;
            }
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT new_cost FROM {$table}
                WHERE product_id = %d AND variation_id = 0 AND effective_date <= %s
                ORDER BY effective_date DESC, id DESC LIMIT 1",
                $product_id,
                $date
            )
        );

        if ( $row ) {
            return (float) $row->new_cost;
        }

        return WCPI_Product_Cost_Manager::get_unit_cost( $product_id, $variation_id );
    }
}
