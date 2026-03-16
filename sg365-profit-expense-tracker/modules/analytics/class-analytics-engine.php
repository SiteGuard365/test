<?php
/**
 * Analytics engine.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Analytics_Engine {

    /**
     * Calculate one date.
     *
     * @param string $date Date.
     * @return array<string, float|int>
     */
    public static function calculate_date( string $date ): array {
        $orders = self::get_orders_for_date( $date );

        $totals = array(
            'orders_count'       => 0,
            'items_sold'         => 0,
            'gross_revenue'      => 0.0,
            'discounts_total'    => 0.0,
            'refunds_total'      => 0.0,
            'shipping_collected' => 0.0,
            'tax_collected'      => 0.0,
            'product_cost_total' => 0.0,
            'expenses_total'     => (float) self::get_expenses_total( $date, $date ),
            'net_profit'         => 0.0,
            'margin_percent'     => 0.0,
        );

        foreach ( $orders as $order ) {
            $metrics = WCPI_Profit_Calculator::order_metrics( $order );
            foreach ( $metrics as $key => $value ) {
                if ( isset( $totals[ $key ] ) ) {
                    $totals[ $key ] += $value;
                }
            }
        }

        $totals['net_profit']     = $totals['gross_revenue'] - $totals['product_cost_total'] - $totals['expenses_total'] - $totals['refunds_total'];
        $totals['margin_percent'] = $totals['gross_revenue'] > 0 ? ( $totals['net_profit'] / $totals['gross_revenue'] ) * 100 : 0;

        return $totals;
    }

    /**
     * Dashboard payload.
     *
     * @param string $from From.
     * @param string $to To.
     * @param bool   $with_compare Include previous period comparison.
     * @return array<string, mixed>
     */
    public static function dashboard( string $from, string $to, bool $with_compare = true ): array {
        WCPI_Recurring_Expense_Manager::sync_generated_entries();

        $cache_key = 'dashboard_' . WCPI_VERSION . '_' . $from . '_' . $to . '_' . ( $with_compare ? 'cmp' : 'base' );
        $cached    = WCPI_Aggregates::get( 'dashboard', $cache_key, $from, $to );
        if ( $cached ) {
            return $cached;
        }

        $rows = WCPI_Report_Query::summaries( $from, $to );
        if ( empty( $rows ) ) {
            WCPI_Daily_Summary::rebuild_range( $from, $to );
            $rows = WCPI_Report_Query::summaries( $from, $to );
        }

        $products = WCPI_Report_Query::product_profitability( $from, $to, 10, 'profit_desc' );
        $losses   = WCPI_Report_Query::product_profitability( $from, $to, 50, 'profit_asc' );
        $low      = WCPI_Report_Query::product_profitability( $from, $to, 10, 'margin_asc' );
        $revenue  = WCPI_Report_Query::product_profitability( $from, $to, 10, 'revenue_desc' );
        $sold     = WCPI_Report_Query::product_profitability( $from, $to, 10, 'qty_desc' );

        $payload = array(
            'from'                => $from,
            'to'                  => $to,
            'range_label'         => WCPI_Helpers::range_label( $from, $to ),
            'revenue'             => 0.0,
            'expenses'            => 0.0,
            'net_profit'          => 0.0,
            'orders_count'        => 0,
            'units_sold'          => 0,
            'product_cost'        => 0.0,
            'discounts_total'     => 0.0,
            'refunds_total'       => 0.0,
            'aov'                 => 0.0,
            'margin_percent'      => 0.0,
            'gross_profit'        => 0.0,
            'chart_labels'        => array(),
            'chart_revenue'       => array(),
            'chart_profit'        => array(),
            'chart_margin'        => array(),
            'chart_orders'        => array(),
            'expense_breakdown'   => WCPI_Report_Query::expense_breakdown( $from, $to ),
            'budget_vs_actual'    => WCPI_Report_Query::expense_budget_vs_actual( $from, $to ),
            'top_products'        => $products,
            'low_margin'          => $low,
            'highest_revenue'     => $revenue,
            'most_sold_products'  => $sold,
            'loss_products'       => array_values(
                array_filter(
                    $losses,
                    static function ( array $row ): bool {
                        return (float) $row['profit'] < 0;
                    }
                )
            ),
            'category_profit'     => WCPI_Report_Query::category_profitability( $from, $to ),
            'category_margin_heatmap' => array(),
            'missing_cost_audit'  => WCPI_Report_Query::missing_cost_audit(),
            'notes'               => WCPI_Report_Query::report_notes( $from, $to, 5 ),
            'report_notes'        => WCPI_Report_Query::report_notes( $from, $to, 10 ),
            'active_notes'        => WCPI_Recurring_Expense_Manager::active_notes( 8 ),
            'comparison'          => array(),
            'previous_period'     => array(),
            'comparison_enabled'  => $with_compare,
            'performance_highlights' => array(),
            'trend_intelligence'  => array(),
        );

        $best_sales_day   = null;
        $best_profit_day  = null;
        $worst_margin_day = null;
        $highest_refund   = null;

        foreach ( $rows as $row ) {
            $payload['revenue']         += (float) $row['gross_revenue'];
            $payload['expenses']        += (float) $row['expenses_total'];
            $payload['net_profit']      += (float) $row['net_profit'];
            $payload['orders_count']    += (int) $row['orders_count'];
            $payload['units_sold']      += (int) $row['items_sold'];
            $payload['product_cost']    += (float) $row['product_cost_total'];
            $payload['refunds_total']   += (float) $row['refunds_total'];
            $payload['discounts_total'] += (float) $row['discounts_total'];
            $payload['chart_labels'][]  = WCPI_Helpers::date_label( $row['summary_date'] );
            $payload['chart_revenue'][] = (float) $row['gross_revenue'];
            $payload['chart_profit'][]  = (float) $row['net_profit'];
            $payload['chart_margin'][]  = (float) $row['margin_percent'];
            $payload['chart_orders'][]  = (int) $row['orders_count'];

            $best_sales_day   = self::compare_day_row( $best_sales_day, $row, 'gross_revenue', 'max' );
            $best_profit_day  = self::compare_day_row( $best_profit_day, $row, 'net_profit', 'max' );
            $worst_margin_day = self::compare_day_row( $worst_margin_day, $row, 'margin_percent', 'min' );
            $highest_refund   = self::compare_day_row( $highest_refund, $row, 'refunds_total', 'max' );
        }

        $payload['gross_profit']   = $payload['revenue'] - $payload['product_cost'];
        $payload['aov']            = $payload['orders_count'] > 0 ? $payload['revenue'] / $payload['orders_count'] : 0;
        $payload['margin_percent'] = $payload['revenue'] > 0 ? ( $payload['net_profit'] / $payload['revenue'] ) * 100 : 0;
        $payload['waterfall']      = array(
            'Revenue'      => $payload['revenue'],
            'Discounts'    => $payload['discounts_total'],
            'Refunds'      => $payload['refunds_total'],
            'Product Cost' => $payload['product_cost'],
            'Expenses'     => $payload['expenses'],
            'Net Profit'   => $payload['net_profit'],
        );
        $payload['highlights']     = array(
            'best_sales_day'   => $best_sales_day,
            'best_profit_day'  => $best_profit_day,
            'worst_margin_day' => $worst_margin_day,
            'highest_refund'   => $highest_refund,
            'strongest_category' => $payload['category_profit'][0] ?? null,
        );
        $payload['performance_highlights'] = array(
            'best_sales_day'      => $best_sales_day,
            'highest_profit_day'  => $best_profit_day,
            'worst_margin_day'    => $worst_margin_day,
            'highest_refund_day'  => $highest_refund,
            'top_category'        => $payload['category_profit'][0] ?? null,
            'top_product'         => $products[0] ?? null,
            'worst_margin_product'=> $low[0] ?? null,
        );
        $payload['category_margin_heatmap'] = array_map(
            static function ( array $row ): array {
                $row['band_label'] = ucfirst( (string) ( $row['heatmap_band'] ?? 'watch' ) );
                return $row;
            },
            $payload['category_profit']
        );
        $payload['executive_insights'] = array(
            array(
                'label' => __( 'Best Day', WCPI_TEXT_DOMAIN ),
                'value' => ! empty( $best_sales_day['summary_date'] ) ? WCPI_Helpers::date_label( $best_sales_day['summary_date'] ) : __( 'No data', WCPI_TEXT_DOMAIN ),
                'meta'  => ! empty( $best_sales_day['gross_revenue'] ) ? WCPI_Helpers::format_price( (float) $best_sales_day['gross_revenue'] ) : __( 'No revenue yet', WCPI_TEXT_DOMAIN ),
            ),
            array(
                'label' => __( 'Top Product', WCPI_TEXT_DOMAIN ),
                'value' => $products[0]['name'] ?? __( 'No sales data', WCPI_TEXT_DOMAIN ),
                'meta'  => ! empty( $products[0]['profit'] ) ? WCPI_Helpers::format_price( (float) $products[0]['profit'] ) : __( 'Profit leader unavailable', WCPI_TEXT_DOMAIN ),
            ),
            array(
                'label' => __( 'Lowest Margin Product', WCPI_TEXT_DOMAIN ),
                'value' => $low[0]['name'] ?? __( 'No products yet', WCPI_TEXT_DOMAIN ),
                'meta'  => isset( $low[0]['margin_percent'] ) ? number_format_i18n( (float) $low[0]['margin_percent'], 1 ) . '%' : __( 'Margin data unavailable', WCPI_TEXT_DOMAIN ),
            ),
            array(
                'label' => __( 'Missing Costs', WCPI_TEXT_DOMAIN ),
                'value' => number_format_i18n( (int) $payload['missing_cost_audit']['missing_products'] ),
                'meta'  => __( 'Products still need cost data', WCPI_TEXT_DOMAIN ),
            ),
            array(
                'label' => __( 'Category Leader', WCPI_TEXT_DOMAIN ),
                'value' => $payload['category_profit'][0]['category'] ?? __( 'No category data', WCPI_TEXT_DOMAIN ),
                'meta'  => ! empty( $payload['category_profit'][0]['profit'] ) ? WCPI_Helpers::format_price( (float) $payload['category_profit'][0]['profit'] ) : __( 'No profit leader yet', WCPI_TEXT_DOMAIN ),
            ),
            array(
                'label' => __( 'Expense Tracking', WCPI_TEXT_DOMAIN ),
                'value' => $payload['expenses'] > 0 ? __( 'Active', WCPI_TEXT_DOMAIN ) : __( 'Needs attention', WCPI_TEXT_DOMAIN ),
                'meta'  => $payload['expenses'] > 0 ? __( 'Operating expenses are included', WCPI_TEXT_DOMAIN ) : __( 'No expenses were recorded in this range', WCPI_TEXT_DOMAIN ),
            ),
        );

        if ( $with_compare ) {
            $previous = WCPI_Helpers::previous_period( $from, $to );
            $previous_payload = self::dashboard( $previous['from'], $previous['to'], false );
            $payload['previous_period'] = $previous;
            $payload['comparison'] = array(
                'revenue'        => WCPI_Insights_Engine::compare( (float) $payload['revenue'], (float) $previous_payload['revenue'] ),
                'net_profit'     => WCPI_Insights_Engine::compare( (float) $payload['net_profit'], (float) $previous_payload['net_profit'] ),
                'expenses'       => WCPI_Insights_Engine::compare( (float) $payload['expenses'], (float) $previous_payload['expenses'] ),
                'margin_percent' => WCPI_Insights_Engine::compare( (float) $payload['margin_percent'], (float) $previous_payload['margin_percent'] ),
                'orders_count'   => WCPI_Insights_Engine::compare( (float) $payload['orders_count'], (float) $previous_payload['orders_count'] ),
                'refunds_total'  => WCPI_Insights_Engine::compare( (float) $payload['refunds_total'], (float) $previous_payload['refunds_total'] ),
                'aov'            => WCPI_Insights_Engine::compare( (float) $payload['aov'], (float) $previous_payload['aov'] ),
                'units_sold'     => WCPI_Insights_Engine::compare( (float) $payload['units_sold'], (float) $previous_payload['units_sold'] ),
            );
            foreach ( array( 'expenses', 'refunds_total' ) as $inverse_metric ) {
                if ( isset( $payload['comparison'][ $inverse_metric ] ) ) {
                    $payload['comparison'][ $inverse_metric ]['tone'] = $payload['comparison'][ $inverse_metric ]['delta'] > 0 ? 'negative' : 'positive';
                }
            }
            $payload['trend_intelligence'] = WCPI_Insights_Engine::trend_intelligence( $payload, $previous_payload, $rows );
        }

        $payload['recommendations'] = WCPI_Insights_Engine::recommendations( $payload );
        $payload['goals'] = self::goal_progress(
            $from,
            $to,
            $payload['revenue'],
            $payload['net_profit'],
            $payload['margin_percent']
        );

        WCPI_Aggregates::set( 'dashboard', $cache_key, $from, $to, $payload, 900 );
        return $payload;
    }

    /**
     * Expenses total in range.
     *
     * @param string $from From.
     * @param string $to To.
     * @return float
     */
    public static function get_expenses_total( string $from, string $to ): float {
        global $wpdb;
        return (float) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT SUM(amount) FROM ' . WCPI_DB::table( 'expenses' ) . ' WHERE expense_date BETWEEN %s AND %s',
                $from,
                $to
            )
        );
    }

    /**
     * Fetch orders for a local date.
     *
     * @param string $date Date Y-m-d.
     * @return array<int, WC_Order>
     */
    private static function get_orders_for_date( string $date ): array {
        $order_ids = wc_get_orders(
            array(
                'limit'        => -1,
                'return'       => 'ids',
                'status'       => array( 'processing', 'completed', 'on-hold' ),
                'date_created' => $date . '...' . $date,
            )
        );

        $orders = array();
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order instanceof WC_Order ) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Compare daily summary candidates.
     *
     * @param array<string, mixed>|null $current Current best row.
     * @param array<string, mixed>      $candidate Candidate row.
     * @param string                    $metric Metric.
     * @param string                    $mode Mode.
     * @return array<string, mixed>
     */
    private static function compare_day_row( ?array $current, array $candidate, string $metric, string $mode ): array {
        if ( null === $current ) {
            return $candidate;
        }

        $current_value   = (float) $current[ $metric ];
        $candidate_value = (float) $candidate[ $metric ];

        if ( ( 'max' === $mode && $candidate_value > $current_value ) || ( 'min' === $mode && $candidate_value < $current_value ) ) {
            return $candidate;
        }

        return $current;
    }

    /**
     * Build goal progress payload.
     *
     * @param string $from From.
     * @param string $to To.
     * @param float  $revenue Revenue.
     * @param float  $profit Profit.
     * @param float  $margin Margin.
     * @return array<int, array<string, mixed>>
     */
    private static function goal_progress( string $from, string $to, float $revenue, float $profit, float $margin ): array {
        $goals = array(
            array(
                'label'   => __( 'Revenue Goal', WCPI_TEXT_DOMAIN ),
                'goal'    => WCPI_Helpers::prorate_monthly_amount( (float) WCPI_Settings_Manager::get( 'general', 'monthly_revenue_goal', 0 ), $from, $to ),
                'actual'  => $revenue,
                'format'  => 'price',
            ),
            array(
                'label'   => __( 'Profit Goal', WCPI_TEXT_DOMAIN ),
                'goal'    => WCPI_Helpers::prorate_monthly_amount( (float) WCPI_Settings_Manager::get( 'general', 'monthly_profit_goal', 0 ), $from, $to ),
                'actual'  => $profit,
                'format'  => 'price',
            ),
            array(
                'label'   => __( 'Margin Goal', WCPI_TEXT_DOMAIN ),
                'goal'    => (float) WCPI_Settings_Manager::get( 'general', 'monthly_margin_goal', 0 ),
                'actual'  => $margin,
                'format'  => 'percent',
            ),
        );

        foreach ( $goals as &$goal ) {
            $goal['progress'] = WCPI_Helpers::progress_percent( (float) $goal['actual'], (float) $goal['goal'] );
            $goal['remaining']= max( 0, (float) $goal['goal'] - (float) $goal['actual'] );
            $goal['status']   = $goal['progress'] >= 100 ? __( 'On track', WCPI_TEXT_DOMAIN ) : __( 'Behind', WCPI_TEXT_DOMAIN );
        }

        return $goals;
    }

    /**
     * Simulate a cost change without saving it.
     *
     * @param int    $product_id Product id.
     * @param int    $variation_id Variation id.
     * @param float  $new_cost New cost.
     * @param string $from From.
     * @param string $to To.
     * @return array<string, mixed>
     */
    public static function simulate_cost_change( int $product_id, int $variation_id, float $new_cost, string $from, string $to ): array {
        $baseline       = WCPI_Report_Query::simulator_baseline( $product_id, $variation_id, $from, $to );
        $current_price  = (float) $baseline['avg_selling_price'] > 0 ? (float) $baseline['avg_selling_price'] : (float) $baseline['current_price'];
        $current_cost   = (float) $baseline['current_cost'];
        $monthly_units  = (float) $baseline['monthly_units'];
        $current_margin = $current_price > 0 ? ( ( $current_price - $current_cost ) / $current_price ) * 100 : 0;
        $new_margin     = $current_price > 0 ? ( ( $current_price - $new_cost ) / $current_price ) * 100 : 0;
        $profit_change  = ( $current_cost - $new_cost ) * $monthly_units;

        return array(
            'baseline'                => $baseline,
            'scenario_type'           => 'cost',
            'new_cost'                => $new_cost,
            'current_margin'          => $current_margin,
            'projected_margin'        => $new_margin,
            'estimated_monthly_delta' => $profit_change,
            'summary'                 => sprintf(
                /* translators: 1: delta cost 2: projected monthly profit change */
                __( 'Changing unit cost by %1$s changes estimated monthly profit by %2$s.', WCPI_TEXT_DOMAIN ),
                wp_strip_all_tags( WCPI_Helpers::format_price( $new_cost - $current_cost ) ),
                wp_strip_all_tags( WCPI_Helpers::format_price( $profit_change ) )
            ),
        );
    }

    /**
     * Simulate a price change without saving it.
     *
     * @param int    $product_id Product id.
     * @param int    $variation_id Variation id.
     * @param float  $new_price New price.
     * @param string $from From.
     * @param string $to To.
     * @return array<string, mixed>
     */
    public static function simulate_price_change( int $product_id, int $variation_id, float $new_price, string $from, string $to ): array {
        $baseline       = WCPI_Report_Query::simulator_baseline( $product_id, $variation_id, $from, $to );
        $current_price  = (float) $baseline['current_price'] > 0 ? (float) $baseline['current_price'] : (float) $baseline['avg_selling_price'];
        $current_cost   = (float) $baseline['current_cost'];
        $monthly_units  = (float) $baseline['monthly_units'];
        $current_margin = $current_price > 0 ? ( ( $current_price - $current_cost ) / $current_price ) * 100 : 0;
        $new_margin     = $new_price > 0 ? ( ( $new_price - $current_cost ) / $new_price ) * 100 : 0;
        $profit_change  = ( $new_price - $current_price ) * $monthly_units;

        return array(
            'baseline'                => $baseline,
            'scenario_type'           => 'price',
            'new_price'               => $new_price,
            'current_margin'          => $current_margin,
            'projected_margin'        => $new_margin,
            'estimated_monthly_delta' => $profit_change,
            'summary'                 => sprintf(
                /* translators: 1: delta price 2: projected monthly profit change */
                __( 'Changing unit price by %1$s changes estimated monthly profit by %2$s.', WCPI_TEXT_DOMAIN ),
                wp_strip_all_tags( WCPI_Helpers::format_price( $new_price - $current_price ) ),
                wp_strip_all_tags( WCPI_Helpers::format_price( $profit_change ) )
            ),
        );
    }
}
