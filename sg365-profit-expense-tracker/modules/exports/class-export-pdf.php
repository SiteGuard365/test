<?php
/**
 * Executive PDF exporter.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Export_PDF {

    /**
     * Generate PDF.
     *
     * @param string $report Report.
     * @param string $from From.
     * @param string $to To.
     * @param bool   $compare Include previous period.
     * @return array<string, string>|WP_Error
     */
    public static function generate( string $report, string $from, string $to, bool $compare = false ) {
        $dashboard = WCPI_Analytics_Engine::dashboard( $from, $to, $compare );
        $lines     = self::build_lines( $report, $dashboard );
        if ( empty( $lines ) ) {
            return new WP_Error( 'wcpi_empty_export', __( 'No data available for export.', WCPI_TEXT_DOMAIN ) );
        }

        $pdf      = self::build_pdf( $lines );
        $filename = WCPI_Filesystem::secure_filename( 'wcpi-' . $report, 'pdf' );
        $file     = WCPI_Filesystem::write_file( 'exports', $filename, $pdf );

        if ( ! is_wp_error( $file ) ) {
            WCPI_Export_CSV::log_export( 'pdf', $report, $from, $to, $filename, $file );
        }

        return $file;
    }

    /**
     * Build report lines.
     *
     * @param string               $report Report key.
     * @param array<string, mixed> $dashboard Dashboard payload.
     * @return array<int, string>
     */
    private static function build_lines( string $report, array $dashboard ): array {
        $title = 'executive_summary' === $report ? __( 'Executive Summary', WCPI_TEXT_DOMAIN ) : ucwords( str_replace( '_', ' ', $report ) );
        $lines = array(
            'SG365 Profit & Expense Tracker',
            $title,
            'Period: ' . $dashboard['range_label'],
            '',
            'Key Metrics',
            'Revenue: ' . self::price( (float) $dashboard['revenue'] ),
            'Net Profit: ' . self::price( (float) $dashboard['net_profit'] ),
            'Expenses: ' . self::price( (float) $dashboard['expenses'] ),
            'Margin: ' . number_format_i18n( (float) $dashboard['margin_percent'], 1 ) . '%',
            'Orders: ' . number_format_i18n( (int) $dashboard['orders_count'] ),
            'Refunds: ' . self::price( (float) $dashboard['refunds_total'] ),
            'Average Order Value: ' . self::price( (float) $dashboard['aov'] ),
            'Units Sold: ' . number_format_i18n( (int) $dashboard['units_sold'] ),
        );

        if ( ! empty( $dashboard['comparison'] ) ) {
            $lines[] = '';
            $lines[] = 'Comparison vs Previous Period';
            $comparison_map = array(
                'Revenue' => 'revenue',
                'Net Profit' => 'net_profit',
                'Expenses' => 'expenses',
                'Margin %' => 'margin_percent',
                'Orders' => 'orders_count',
                'Refunds' => 'refunds_total',
                'AOV' => 'aov',
                'Units Sold' => 'units_sold',
            );

            foreach ( $comparison_map as $label => $key ) {
                if ( empty( $dashboard['comparison'][ $key ] ) ) {
                    continue;
                }

                $trend   = $dashboard['comparison'][ $key ];
                $prefix  = $trend['percentage'] >= 0 ? '+' : '';
                $lines[] = sprintf(
                    '%1$s: %2$s%3$s%%',
                    $label,
                    $prefix,
                    number_format_i18n( (float) $trend['percentage'], 1 )
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Goals';
        foreach ( $dashboard['goals'] as $goal ) {
            $lines[] = sprintf(
                '%1$s | Actual %2$s | Target %3$s | Progress %4$s%%',
                $goal['label'],
                'percent' === $goal['format'] ? number_format_i18n( (float) $goal['actual'], 1 ) . '%' : self::price( (float) $goal['actual'] ),
                'percent' === $goal['format'] ? number_format_i18n( (float) $goal['goal'], 1 ) . '%' : self::price( (float) $goal['goal'] ),
                number_format_i18n( (float) $goal['progress'], 1 )
            );
        }

        $lines[] = '';
        $lines[] = 'Performance Highlights';
        foreach ( array(
            'Best Sales Day' => $dashboard['performance_highlights']['best_sales_day']['summary_date'] ?? '',
            'Highest Profit Day' => $dashboard['performance_highlights']['highest_profit_day']['summary_date'] ?? '',
            'Top Product' => $dashboard['performance_highlights']['top_product']['name'] ?? '',
            'Worst Margin Product' => $dashboard['performance_highlights']['worst_margin_product']['name'] ?? '',
            'Top Category' => $dashboard['performance_highlights']['top_category']['category'] ?? '',
        ) as $label => $value ) {
            if ( '' !== $value ) {
                $lines[] = $label . ': ' . $value;
            }
        }

        $lines[] = '';
        $lines[] = 'Top Profitable Products';
        foreach ( array_slice( $dashboard['top_products'], 0, 5 ) as $product ) {
            $lines[] = sprintf(
                '%1$s | Profit %2$s | Margin %3$s%% | Health %4$s',
                $product['name'],
                self::price( (float) $product['profit'] ),
                number_format_i18n( (float) $product['margin_percent'], 1 ),
                $product['health_label'] ?? $product['health_score']
            );
        }

        $lines[] = '';
        $lines[] = 'Low Margin Products';
        foreach ( array_slice( $dashboard['low_margin'], 0, 5 ) as $product ) {
            $lines[] = sprintf(
                '%1$s | Margin %2$s%% | Profit %3$s',
                $product['name'],
                number_format_i18n( (float) $product['margin_percent'], 1 ),
                self::price( (float) $product['profit'] )
            );
        }

        $lines[] = '';
        $lines[] = 'Category Profitability';
        foreach ( array_slice( $dashboard['category_profit'], 0, 5 ) as $category ) {
            $lines[] = sprintf(
                '%1$s | Qty %2$s | Revenue %3$s | Profit %4$s | Margin %5$s%%',
                $category['category'],
                number_format_i18n( (float) $category['qty_sold'], 2 ),
                self::price( (float) $category['revenue'] ),
                self::price( (float) $category['profit'] ),
                number_format_i18n( (float) $category['margin_percent'], 1 )
            );
        }

        $lines[] = '';
        $lines[] = 'Expense Budget vs Actual';
        foreach ( array_slice( $dashboard['budget_vs_actual'], 0, 5 ) as $budget_row ) {
            $lines[] = sprintf(
                '%1$s | Actual %2$s | Budget %3$s | Variance %4$s',
                $budget_row['display_name'] ?? $budget_row['category'],
                self::price( (float) $budget_row['total'] ),
                self::price( (float) $budget_row['budget'] ),
                self::price( (float) $budget_row['variance'] )
            );
        }

        $lines[] = '';
        $lines[] = 'Recommendation Summary';
        if ( empty( $dashboard['recommendations'] ) ) {
            $lines[] = 'No active recommendations for this range.';
        } else {
            foreach ( array_slice( $dashboard['recommendations'], 0, 6 ) as $recommendation ) {
                $lines[] = strtoupper( $recommendation['type'] ?? $recommendation['severity'] ?? 'info' ) . ': ' . $recommendation['title'];
                $lines[] = $recommendation['body'];
            }
        }

        if ( ! empty( $dashboard['trend_intelligence'] ) ) {
            $lines[] = '';
            $lines[] = 'Trend Intelligence';
            foreach ( array_slice( $dashboard['trend_intelligence'], 0, 5 ) as $trend ) {
                $lines[] = strtoupper( $trend['type'] ?? 'info' ) . ': ' . $trend['title'];
                $lines[] = $trend['body'];
            }
        }

        return self::wrap_lines( $lines );
    }

    /**
     * Wrap long lines for the PDF text stream.
     *
     * @param array<int, string> $lines Raw lines.
     * @return array<int, string>
     */
    private static function wrap_lines( array $lines ): array {
        $wrapped = array();

        foreach ( $lines as $line ) {
            if ( '' === $line ) {
                $wrapped[] = '';
                continue;
            }

            $parts = wordwrap( wp_strip_all_tags( $line ), 88, "\n", true );
            foreach ( explode( "\n", $parts ) as $part ) {
                $wrapped[] = $part;
            }
        }

        return $wrapped;
    }

    /**
     * Build a valid multipage PDF from text lines.
     *
     * @param array<int, string> $lines Lines.
     * @return string
     */
    private static function build_pdf( array $lines ): string {
        $safe_lines = array_map( array( __CLASS__, 'escape_pdf_line' ), $lines );
        $chunks     = array_chunk( $safe_lines, 42 );
        $objects    = array();
        $page_ids   = array();
        $font_id    = 0;

        $objects[] = '';
        $objects[] = '';

        foreach ( $chunks as $chunk ) {
            $stream   = self::page_stream( $chunk );
            $page_id  = count( $objects ) + 1;
            $font_id  = $page_id + 1;
            $stream_id = $page_id + 2;

            $page_ids[] = $page_id;
            $objects[]  = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$font_id} 0 R >> >> /Contents {$stream_id} 0 R >>";
            $objects[]  = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
            $objects[]  = "<< /Length " . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[1] = '<< /Type /Pages /Kids [' . implode( ' ', array_map( static fn( $id ) => $id . ' 0 R', $page_ids ) ) . '] /Count ' . count( $page_ids ) . ' >>';

        $pdf     = "%PDF-1.4\n";
        $offsets = array( 0 );

        foreach ( $objects as $index => $object ) {
            $offsets[] = strlen( $pdf );
            $pdf      .= ( $index + 1 ) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen( $pdf );
        $pdf .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ( $i = 1; $i <= count( $objects ); $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
        }

        $pdf .= 'trailer' . "\n<< /Root 1 0 R /Size " . ( count( $objects ) + 1 ) . " >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * Build a page content stream.
     *
     * @param array<int, string> $lines Lines for the page.
     * @return string
     */
    private static function page_stream( array $lines ): string {
        $stream = "BT\n/F1 11 Tf\n50 760 Td\n";

        foreach ( $lines as $index => $line ) {
            if ( 0 === $index ) {
                $stream .= '(' . $line . ") Tj\n";
                continue;
            }

            $stream .= "0 -16 Td\n(" . $line . ") Tj\n";
        }

        $stream .= "ET";
        return $stream;
    }

    /**
     * Escape a line for PDF stream output.
     *
     * @param string $line Raw line.
     * @return string
     */
    private static function escape_pdf_line( string $line ): string {
        $line = substr( preg_replace( '/[^\x20-\x7E]/', '', $line ), 0, 120 );
        return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $line );
    }

    /**
     * Format price without HTML.
     *
     * @param float $amount Amount.
     * @return string
     */
    private static function price( float $amount ): string {
        return wp_strip_all_tags( WCPI_Helpers::format_price( $amount ) );
    }
}
