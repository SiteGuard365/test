<?php
/**
 * CSV export.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Export_CSV {

    /**
     * Generate CSV file.
     *
     * @param string $report Report key.
     * @param string $from From.
     * @param string $to To.
     * @param bool   $compare Include comparison.
     * @return array<string, string>|WP_Error
     */
    public static function generate( string $report, string $from, string $to, bool $compare = false ) {
        $rows = self::build_rows( $report, $from, $to, $compare );
        if ( empty( $rows ) ) {
            return new WP_Error( 'wcpi_empty_export', __( 'No data available for export.', WCPI_TEXT_DOMAIN ) );
        }

        $handle = fopen( 'php://temp', 'r+' );
        foreach ( $rows as $row ) {
            fputcsv( $handle, $row );
        }
        rewind( $handle );
        $contents = stream_get_contents( $handle );
        fclose( $handle );

        $filename = WCPI_Filesystem::secure_filename( 'wcpi-' . $report, 'csv' );
        $file     = WCPI_Filesystem::write_file( 'exports', $filename, $contents );

        if ( ! is_wp_error( $file ) ) {
            self::log_export( 'csv', $report, $from, $to, $filename, $file );
        }

        return $file;
    }

    /**
     * Build report rows.
     *
     * @param string $report Report.
     * @param string $from From.
     * @param string $to To.
     * @param bool   $compare Include comparison data.
     * @return array<int, array<int, mixed>>
     */
    public static function build_rows( string $report, string $from, string $to, bool $compare = false ): array {
        $dashboard = WCPI_Analytics_Engine::dashboard( $from, $to, $compare );
        $currency  = WCPI_Helpers::currency_code();

        switch ( $report ) {
            case 'executive_summary':
                $rows = array(
                    array( 'Metric', 'Value' ),
                    array( 'Selected Period', $dashboard['range_label'] ),
                    array( 'Currency', $currency ),
                    array( 'Revenue', $dashboard['revenue'] ),
                    array( 'Net Profit', $dashboard['net_profit'] ),
                    array( 'Expenses', $dashboard['expenses'] ),
                    array( 'Margin %', $dashboard['margin_percent'] ),
                    array( 'Orders', $dashboard['orders_count'] ),
                    array( 'Refunds', $dashboard['refunds_total'] ),
                    array( 'Average Order Value', $dashboard['aov'] ),
                    array( 'Units Sold', $dashboard['units_sold'] ),
                    array( 'Top Profitable Product', $dashboard['top_products'][0]['name'] ?? '' ),
                    array( 'Low Margin Product', $dashboard['low_margin'][0]['name'] ?? '' ),
                );
                if ( $compare && ! empty( $dashboard['comparison'] ) ) {
                    foreach ( $dashboard['comparison'] as $metric => $row ) {
                        $rows[] = array( 'Change vs previous - ' . $metric, $row['percentage'] );
                    }
                }
                $rows[] = array( '', '' );
                $rows[] = array( 'Goal', 'Actual / Target' );
                foreach ( $dashboard['goals'] as $goal ) {
                    $rows[] = array( $goal['label'], $goal['actual'] . ' / ' . $goal['goal'] . ' (' . $goal['progress'] . '%)' );
                }
                $rows[] = array( '', '' );
                $rows[] = array( 'Category', 'Profit' );
                foreach ( array_slice( $dashboard['category_profit'], 0, 5 ) as $category ) {
                    $rows[] = array( $category['category'], $category['profit'] );
                }
                $rows[] = array( '', '' );
                foreach ( $dashboard['trend_intelligence'] as $trend ) {
                    $rows[] = array( 'Trend', $trend['title'] . ' - ' . $trend['body'] );
                }
                $rows[] = array( '', '' );
                foreach ( $dashboard['recommendations'] as $recommendation ) {
                    $rows[] = array( 'Recommendation', $recommendation['title'] . ' - ' . $recommendation['body'] );
                }
                return $rows;

            case 'dashboard_summary':
                $rows = array(
                    array( 'Currency', $currency ),
                    array( 'From', $from ),
                    array( 'To', $to ),
                    array( 'Revenue', $dashboard['revenue'] ),
                    array( 'Expenses', $dashboard['expenses'] ),
                    array( 'Net Profit', $dashboard['net_profit'] ),
                    array( 'Orders Count', $dashboard['orders_count'] ),
                    array( 'Units Sold', $dashboard['units_sold'] ),
                    array( 'AOV', $dashboard['aov'] ),
                    array( 'Margin %', $dashboard['margin_percent'] ),
                );
                if ( $compare && ! empty( $dashboard['comparison'] ) ) {
                    foreach ( $dashboard['comparison'] as $metric => $row ) {
                        $rows[] = array( $metric . ' Change %', $row['percentage'] );
                    }
                }
                foreach ( $dashboard['performance_highlights'] as $key => $item ) {
                    if ( empty( $item ) ) {
                        continue;
                    }

                    $value = is_array( $item )
                        ? ( $item['name'] ?? $item['category'] ?? $item['summary_date'] ?? '' )
                        : $item;
                    $rows[] = array( ucwords( str_replace( '_', ' ', $key ) ), $value );
                }
                return $rows;

            case 'category_profit':
                $rows = array( array( 'Currency', $currency ), array(), array( 'Category', 'Qty Sold', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Heatmap Band' ) );
                foreach ( $dashboard['category_profit'] as $item ) {
                    $rows[] = array( $item['category'], $item['qty_sold'], $item['revenue'], $item['cost'], $item['profit'], $item['margin_percent'], $item['heatmap_band'] ?? '' );
                }
                return $rows;

            case 'product_profit':
                $rows = array( array( 'Currency', $currency ), array(), array( 'Product', 'SKU', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Health Score', 'Health Label' ) );
                foreach ( WCPI_Report_Query::product_profitability( $from, $to, 200, 'profit_desc' ) as $item ) {
                    $rows[] = array(
                        $item['name'],
                        $item['sku'],
                        $item['quantity_sold'],
                        $item['revenue'],
                        $item['cost'],
                        $item['profit'],
                        $item['margin_percent'],
                        $item['health_score'],
                        $item['health_label'] ?? '',
                    );
                }
                return $rows;

            case 'expense_report':
                $query = WCPI_Expense_Manager::query(
                    array(
                        'from'     => $from,
                        'to'       => $to,
                        'per_page' => 5000,
                        'paged'    => 1,
                    )
                );
                $rows = array( array( 'Currency', $currency ), array(), array( 'Name', 'Category', 'Amount', 'Date', 'Source', 'Notes' ) );
                foreach ( $query['items'] as $item ) {
                    $rows[] = array(
                        $item['expense_name'],
                        $item['category'],
                        $item['amount'],
                        $item['expense_date'],
                        $item['source_type'],
                        $item['notes'],
                    );
                }
                $rows[] = array();
                $rows[] = array( 'Budget Category', 'Actual', 'Budget', 'Variance', 'Status' );
                foreach ( $dashboard['budget_vs_actual'] as $item ) {
                    $rows[] = array(
                        $item['display_name'] ?? $item['category'],
                        $item['total'],
                        $item['budget'],
                        $item['variance'],
                        $item['status'] ?? '',
                    );
                }
                return $rows;

            case 'daily_summary':
            default:
                $rows = array(
                    array( 'Currency', $currency ),
                    array(),
                    array( 'Date', 'Orders', 'Items Sold', 'Revenue', 'Discounts', 'Refunds', 'Shipping', 'Tax', 'Product Cost', 'Expenses', 'Net Profit', 'Margin %' ),
                );
                foreach ( WCPI_Report_Query::summaries( $from, $to ) as $row ) {
                    $rows[] = array(
                        $row['summary_date'],
                        $row['orders_count'],
                        $row['items_sold'],
                        $row['gross_revenue'],
                        $row['discounts_total'],
                        $row['refunds_total'],
                        $row['shipping_collected'],
                        $row['tax_collected'],
                        $row['product_cost_total'],
                        $row['expenses_total'],
                        $row['net_profit'],
                        $row['margin_percent'],
                    );
                }
                return $rows;
        }
    }

    /**
     * Log export in history table.
     *
     * @param string               $type Type.
     * @param string               $report Report.
     * @param string               $from From.
     * @param string               $to To.
     * @param string               $filename Filename.
     * @param array<string,string> $file File.
     * @return void
     */
    public static function log_export( string $type, string $report, string $from, string $to, string $filename, array $file ): void {
        global $wpdb;
        $wpdb->insert(
            WCPI_DB::table( 'export_history' ),
            array(
                'export_type' => $type,
                'report_key'  => $report,
                'date_from'   => $from,
                'date_to'     => $to,
                'file_name'   => $filename,
                'file_url'    => $file['url'],
                'file_path'   => $file['path'],
                'file_size'   => file_exists( $file['path'] ) ? filesize( $file['path'] ) : 0,
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );
    }
}
