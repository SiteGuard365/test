<?php
/**
 * Product health scoring.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Product_Health_Scorer {

    /**
     * Score one product row.
     *
     * @param array<string, mixed> $row Product profitability row.
     * @param array<string, mixed> $context Range context.
     * @return array<string, mixed>
     */
    public static function score_row( array $row, array $context = array() ): array {
        $max_revenue   = max( 1.0, (float) ( $context['max_revenue'] ?? 1 ) );
        $max_quantity  = max( 1.0, (float) ( $context['max_quantity'] ?? 1 ) );
        $period_days   = max( 1, (int) ( $context['period_days'] ?? 30 ) );
        $margin_target = (float) WCPI_Settings_Manager::get( 'general', 'margin_threshold', 20 );

        $revenue_ratio      = min( 1, (float) $row['revenue'] / $max_revenue );
        $quantity_ratio     = min( 1, (float) $row['quantity_sold'] / $max_quantity );
        $active_days_ratio  = min( 1, (float) ( $row['active_selling_days'] ?? 0 ) / $period_days );
        $refund_ratio       = max( 0, (float) ( $row['refund_ratio'] ?? 0 ) );
        $margin_percent     = (float) $row['margin_percent'];
        $cost_status        = (string) ( $row['cost_status'] ?? 'missing' );

        $factors = array(
            'revenue_strength'  => round( 20 * $revenue_ratio, 1 ),
            'margin_strength'   => self::margin_points( $margin_percent, $margin_target ),
            'cost_completeness' => self::cost_points( $cost_status ),
            'refund_ratio'      => self::refund_points( $refund_ratio ),
            'sales_consistency' => round( 20 * $active_days_ratio, 1 ),
            'sales_volume'      => round( 15 * $quantity_ratio, 1 ),
        );

        $score = (int) round( array_sum( $factors ) );
        $score = max( 0, min( 100, $score ) );

        return array(
            'score'   => $score,
            'label'   => WCPI_Helpers::health_label( $score ),
            'factors' => $factors,
        );
    }

    /**
     * Margin contribution.
     *
     * @param float $margin Margin.
     * @param float $target Target.
     * @return float
     */
    private static function margin_points( float $margin, float $target ): float {
        if ( $margin >= $target + 10 ) {
            return 20.0;
        }

        if ( $margin >= $target ) {
            return 16.0;
        }

        if ( $margin >= max( 0, $target - 10 ) ) {
            return 10.0;
        }

        if ( $margin > 0 ) {
            return 5.0;
        }

        return 0.0;
    }

    /**
     * Cost completeness contribution.
     *
     * @param string $status Cost status.
     * @return float
     */
    private static function cost_points( string $status ): float {
        switch ( $status ) {
            case 'healthy':
                return 15.0;
            case 'incomplete':
                return 8.0;
            case 'zero':
                return 4.0;
            case 'missing':
            default:
                return 0.0;
        }
    }

    /**
     * Refund ratio contribution.
     *
     * @param float $ratio Ratio percent.
     * @return float
     */
    private static function refund_points( float $ratio ): float {
        if ( $ratio <= 2 ) {
            return 10.0;
        }

        if ( $ratio <= 5 ) {
            return 8.0;
        }

        if ( $ratio <= 10 ) {
            return 5.0;
        }

        if ( $ratio <= 20 ) {
            return 2.0;
        }

        return 0.0;
    }
}
