<?php
/**
 * Rules-based recommendations and comparison helpers.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Insights_Engine {

    /**
     * Compare summary values.
     *
     * @param float $current Current value.
     * @param float $previous Previous value.
     * @return array<string, mixed>
     */
    public static function compare( float $current, float $previous ): array {
        $delta = $current - $previous;
        $percentage = 0.0;

        if ( abs( $previous ) > 0.000001 ) {
            $percentage = ( $delta / $previous ) * 100;
        } elseif ( abs( $current ) > 0.000001 ) {
            $percentage = 100.0;
        }

        return array(
            'current'    => $current,
            'previous'   => $previous,
            'delta'      => $delta,
            'percentage' => $percentage,
            'direction'  => $delta >= 0 ? 'up' : 'down',
            'tone'       => $delta >= 0 ? 'positive' : 'negative',
        );
    }

    /**
     * Build recommendations from dashboard payload.
     *
     * @param array<string, mixed> $payload Dashboard data.
     * @return array<int, array<string, string>>
     */
    public static function recommendations( array $payload ): array {
        $recommendations = array();
        $audit           = $payload['missing_cost_audit'] ?? array();
        $threshold       = (float) WCPI_Settings_Manager::get( 'general', 'margin_threshold', 20 );
        $missing_limit   = (int) WCPI_Settings_Manager::get( 'general', 'missing_cost_warning_threshold', 1 );
        $negative_count  = count( $payload['loss_products'] ?? array() );

        if ( ! empty( $audit['missing_products'] ) && (int) $audit['missing_products'] >= $missing_limit ) {
            $recommendations[] = self::item(
                'alert',
                sprintf( __( '%d products are missing costs', WCPI_TEXT_DOMAIN ), (int) $audit['missing_products'] ),
                __( 'Missing product cost data will understate product cost and overstate profit. Review the cost audit to restore accuracy.', WCPI_TEXT_DOMAIN ),
                __( 'Review Missing Costs', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-product-costs' )
            );
        }

        if ( ! empty( $audit['zero_cost_products'] ) ) {
            $recommendations[] = self::item(
                'warning',
                sprintf( __( '%d products have zero cost', WCPI_TEXT_DOMAIN ), (int) $audit['zero_cost_products'] ),
                __( 'Zero-cost items are usually incomplete cost records. Add base, packaging, or handling costs to improve margin reporting.', WCPI_TEXT_DOMAIN ),
                __( 'Review Product Costs', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-product-costs' )
            );
        }

        if ( ! empty( $audit['variation_missing'] ) ) {
            $recommendations[] = self::item(
                'warning',
                sprintf( __( '%d variations are missing cost rows', WCPI_TEXT_DOMAIN ), (int) $audit['variation_missing'] ),
                __( 'Variation-specific costs improve profit accuracy when options have different cost structures. Add missing variation rows where needed.', WCPI_TEXT_DOMAIN ),
                __( 'Review Variations', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-product-costs' )
            );
        }

        if ( ! empty( $audit['incomplete_rows'] ) ) {
            $recommendations[] = self::item(
                'info',
                sprintf( __( '%d cost rows need review', WCPI_TEXT_DOMAIN ), (int) $audit['incomplete_rows'] ),
                __( 'Some saved totals do not match their component costs. Review those rows to keep audits and exports consistent.', WCPI_TEXT_DOMAIN ),
                __( 'Open Product Costs', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-product-costs' )
            );
        }

        if ( empty( $payload['expenses'] ) ) {
            $recommendations[] = self::item(
                'warning',
                __( 'No expenses recorded for this period', WCPI_TEXT_DOMAIN ),
                __( 'Store-level profit is incomplete until operating expenses are tracked. Add manual or recurring expenses to see true net profit.', WCPI_TEXT_DOMAIN ),
                __( 'Add Expenses', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-expenses' )
            );
        }

        if ( ! empty( $payload['comparison']['net_profit']['delta'] ) && $payload['comparison']['net_profit']['delta'] < 0 ) {
            $recommendations[] = self::item(
                'alert',
                __( 'Net profit dropped versus the previous period', WCPI_TEXT_DOMAIN ),
                __( 'Profit is lower than the immediately preceding period. Review product mix, expense changes, and refund spikes for the selected dates.', WCPI_TEXT_DOMAIN )
            );
        }

        if ( ! empty( $payload['comparison']['margin_percent']['delta'] ) && $payload['comparison']['margin_percent']['delta'] < -5 ) {
            $recommendations[] = self::item(
                'alert',
                __( 'Margin dropped significantly versus the previous period', WCPI_TEXT_DOMAIN ),
                __( 'Revenue can grow while profit quality declines. Review low-margin products, refunds, and expense changes for the selected range.', WCPI_TEXT_DOMAIN )
            );
        }

        $top_seller = $payload['most_sold_products'][0] ?? array();
        if ( ! empty( $top_seller ) && isset( $top_seller['margin_percent'] ) && (float) $top_seller['margin_percent'] < $threshold ) {
            $recommendations[] = self::item(
                'warning',
                __( 'Top seller margin is below target', WCPI_TEXT_DOMAIN ),
                __( 'Your highest-volume product is selling, but below the configured healthy margin threshold. Review pricing or cost structure.', WCPI_TEXT_DOMAIN )
            );
        }

        if ( $negative_count > 0 ) {
            $recommendations[] = self::item(
                'alert',
                sprintf( __( '%d products are selling at a loss', WCPI_TEXT_DOMAIN ), $negative_count ),
                __( 'Negative-profit products are dragging down store profitability. Review pricing, sourcing, or bundled costs for those items.', WCPI_TEXT_DOMAIN )
            );
        }

        if ( ! empty( $payload['comparison']['refunds_total']['delta'] ) && $payload['comparison']['refunds_total']['delta'] > 0 ) {
            $recommendations[] = self::item(
                'warning',
                __( 'Refunds increased compared to the previous period', WCPI_TEXT_DOMAIN ),
                __( 'Rising refunds can quickly erode profit. Review refund-heavy orders and low-margin products in this range.', WCPI_TEXT_DOMAIN )
            );
        }

        foreach ( array_slice( $payload['budget_vs_actual'] ?? array(), 0, 3 ) as $budget_row ) {
            if ( (float) ( $budget_row['variance'] ?? 0 ) <= 0 ) {
                continue;
            }

            $recommendations[] = self::item(
                'warning',
                sprintf( __( '%s is over budget', WCPI_TEXT_DOMAIN ), $budget_row['display_name'] ?? ucfirst( (string) $budget_row['category'] ) ),
                sprintf(
                    __( 'This category is over budget by %s in the selected period.', WCPI_TEXT_DOMAIN ),
                    wp_strip_all_tags( WCPI_Helpers::format_price( (float) $budget_row['variance'] ) )
                ),
                __( 'Review Expenses', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-expenses' )
            );
            break;
        }

        if ( empty( WCPI_Recurring_Expense_Manager::all() ) ) {
            $recommendations[] = self::item(
                'info',
                __( 'No recurring expenses configured', WCPI_TEXT_DOMAIN ),
                __( 'Set up rent, salaries, subscriptions, and utilities as recurring expenses to keep future profit reporting consistent.', WCPI_TEXT_DOMAIN ),
                __( 'Add Recurring Expense', WCPI_TEXT_DOMAIN ),
                admin_url( 'admin.php?page=wcpi-expenses' )
            );
        }

        return $recommendations;
    }

    /**
     * Build trend intelligence cards.
     *
     * @param array<string, mixed>      $payload Current payload.
     * @param array<string, mixed>      $previous Previous payload.
     * @param array<int, array<string, mixed>> $rows Daily summary rows.
     * @return array<int, array<string, string>>
     */
    public static function trend_intelligence( array $payload, array $previous, array $rows ): array {
        $items = array();
        $profit_compare = self::compare( (float) $payload['net_profit'], (float) $previous['net_profit'] );
        $margin_compare = self::compare( (float) $payload['margin_percent'], (float) $previous['margin_percent'] );
        $expense_compare = self::compare( (float) $payload['expenses'], (float) $previous['expenses'] );
        $revenue_compare = self::compare( (float) $payload['revenue'], (float) $previous['revenue'] );
        $refund_compare = self::compare( (float) $payload['refunds_total'], (float) $previous['refunds_total'] );

        if ( abs( (float) $profit_compare['percentage'] ) >= 5 ) {
            $items[] = self::item(
                $profit_compare['delta'] >= 0 ? 'success' : 'alert',
                $profit_compare['delta'] >= 0 ? __( 'Profit trend rising', WCPI_TEXT_DOMAIN ) : __( 'Profit trend declining', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Profit changed by %s%% compared to the previous period.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $profit_compare['percentage'], 1 )
                )
            );
        }

        if ( abs( (float) $margin_compare['percentage'] ) >= 3 ) {
            $items[] = self::item(
                $margin_compare['delta'] >= 0 ? 'success' : 'warning',
                $margin_compare['delta'] >= 0 ? __( 'Margin improving', WCPI_TEXT_DOMAIN ) : __( 'Margin weakening', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Margin moved by %s%% versus the previous period.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $margin_compare['percentage'], 1 )
                )
            );
        }

        if ( (float) $expense_compare['percentage'] > (float) $revenue_compare['percentage'] && (float) $expense_compare['delta'] > 0 ) {
            $items[] = self::item(
                'warning',
                __( 'Expenses are rising faster than revenue', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Expenses grew %1$s%% while revenue grew %2$s%% in the selected comparison.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $expense_compare['percentage'], 1 ),
                    number_format_i18n( (float) $revenue_compare['percentage'], 1 )
                )
            );
        }

        if ( (float) $refund_compare['delta'] > 0 ) {
            $items[] = self::item(
                'warning',
                __( 'Refund pressure is increasing', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Refunds increased by %s%% compared to the previous period.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $refund_compare['percentage'], 1 )
                )
            );
        }

        $profit_direction = self::daily_direction( $rows, 'net_profit' );
        if ( 'up' === $profit_direction['direction'] && $profit_direction['change'] >= 5 ) {
            $items[] = self::item(
                'success',
                __( 'Profit momentum improved inside the period', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Average daily profit in the second half of the period was %s%% higher than the first half.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $profit_direction['change'], 1 )
                )
            );
        } elseif ( 'down' === $profit_direction['direction'] && $profit_direction['change'] >= 5 ) {
            $items[] = self::item(
                'warning',
                __( 'Profit momentum softened inside the period', WCPI_TEXT_DOMAIN ),
                sprintf(
                    __( 'Average daily profit in the second half of the period was %s%% lower than the first half.', WCPI_TEXT_DOMAIN ),
                    number_format_i18n( (float) $profit_direction['change'], 1 )
                )
            );
        }

        return array_slice( $items, 0, 6 );
    }

    /**
     * Standardize recommendation payload shape.
     *
     * @param string      $type Type.
     * @param string      $title Title.
     * @param string      $body Body.
     * @param string|null $cta_label CTA label.
     * @param string|null $cta_url CTA URL.
     * @return array<string, string>
     */
    private static function item( string $type, string $title, string $body, ?string $cta_label = null, ?string $cta_url = null ): array {
        return array(
            'type'      => $type,
            'severity'  => $type,
            'title'     => $title,
            'body'      => $body,
            'cta_label' => (string) $cta_label,
            'cta_url'   => (string) $cta_url,
        );
    }

    /**
     * Compare first half vs second half averages for a daily metric.
     *
     * @param array<int, array<string, mixed>> $rows Rows.
     * @param string                           $metric Metric key.
     * @return array<string, mixed>
     */
    private static function daily_direction( array $rows, string $metric ): array {
        $count = count( $rows );
        if ( $count < 4 ) {
            return array(
                'direction' => 'flat',
                'change'    => 0.0,
            );
        }

        $half = (int) ceil( $count / 2 );
        $first = array_slice( $rows, 0, $half );
        $second = array_slice( $rows, $half );
        $first_avg = self::average_metric( $first, $metric );
        $second_avg = self::average_metric( $second, $metric );

        if ( abs( $first_avg ) < 0.000001 ) {
            if ( abs( $second_avg ) < 0.000001 ) {
                return array(
                    'direction' => 'flat',
                    'change'    => 0.0,
                );
            }

            return array(
                'direction' => $second_avg >= 0 ? 'up' : 'down',
                'change'    => 100.0,
            );
        }

        $percentage = abs( ( ( $second_avg - $first_avg ) / $first_avg ) * 100 );

        return array(
            'direction' => $second_avg >= $first_avg ? 'up' : 'down',
            'change'    => $percentage,
        );
    }

    /**
     * Average one metric over daily rows.
     *
     * @param array<int, array<string, mixed>> $rows Rows.
     * @param string                           $metric Metric key.
     * @return float
     */
    private static function average_metric( array $rows, string $metric ): float {
        if ( empty( $rows ) ) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ( $rows as $row ) {
            $sum += (float) ( $row[ $metric ] ?? 0 );
        }

        return $sum / count( $rows );
    }
}
