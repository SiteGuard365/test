<?php
/**
 * Scheduled email reports.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Email_Reporter {

    /**
     * Send the scheduled report if due.
     *
     * @return void
     */
    public static function maybe_send_scheduled_report(): void {
        if ( 'yes' !== WCPI_Settings_Manager::get( 'general', 'email_reports_enabled', 'no' ) ) {
            return;
        }

        $recipient = sanitize_email( WCPI_Settings_Manager::get( 'general', 'email_reports_recipient', '' ) );
        $type      = WCPI_Settings_Manager::get( 'general', 'email_reports_type', 'executive_summary' );
        $frequency = WCPI_Settings_Manager::get( 'general', 'email_reports_frequency', 'weekly' );

        if ( empty( $recipient ) || ! is_email( $recipient ) ) {
            WCPI_Logger::log( 'warning', 'email_reports', 'Scheduled email skipped because recipient is invalid.' );
            return;
        }

        if ( ! isset( WCPI_Helpers::email_report_types()[ $type ] ) || ! isset( WCPI_Helpers::email_report_frequencies()[ $frequency ] ) ) {
            WCPI_Logger::log( 'warning', 'email_reports', 'Scheduled email skipped because report settings are incomplete.' );
            return;
        }

        $today       = new DateTimeImmutable( 'now', wp_timezone() );
        $last_sent_at = WCPI_Settings_Manager::get( 'general', 'email_reports_last_sent_at', '' );

        if ( ! self::is_due_today( $frequency, $today ) || self::already_sent_for_window( $frequency, $last_sent_at, $today ) ) {
            return;
        }

        $range     = self::reporting_range( $frequency, $today );
        $dashboard = WCPI_Analytics_Engine::dashboard( $range['from'], $range['to'], true );
        $subject   = sprintf(
            '%s - %s',
            WCPI_Helpers::email_report_types()[ $type ],
            $dashboard['range_label']
        );
        $headers   = array( 'Content-Type: text/html; charset=UTF-8' );
        $body      = self::build_html( $type, $dashboard );
        $sent      = wp_mail( $recipient, $subject, $body, $headers );

        if ( $sent ) {
            WCPI_Settings_Manager::set( 'general', 'email_reports_last_sent_at', $today->format( 'Y-m-d H:i:s' ) );
            WCPI_Logger::log(
                'info',
                'email_reports',
                'Scheduled report sent.',
                array(
                    'recipient' => $recipient,
                    'type'      => $type,
                    'frequency' => $frequency,
                    'from'      => $range['from'],
                    'to'        => $range['to'],
                )
            );
            return;
        }

        WCPI_Logger::log(
            'error',
            'email_reports',
            'Scheduled report failed to send.',
            array(
                'recipient' => $recipient,
                'type'      => $type,
                'frequency' => $frequency,
            )
        );
    }

    /**
     * Determine if a report is due today.
     *
     * @param string            $frequency Frequency.
     * @param DateTimeImmutable $today Current local date.
     * @return bool
     */
    private static function is_due_today( string $frequency, DateTimeImmutable $today ): bool {
        if ( 'weekly' === $frequency ) {
            return '1' === $today->format( 'N' );
        }

        if ( 'monthly' === $frequency ) {
            return '1' === $today->format( 'j' );
        }

        return false;
    }

    /**
     * Dedupe sends inside the current reporting window.
     *
     * @param string            $frequency Frequency.
     * @param string            $last_sent_at Last sent timestamp.
     * @param DateTimeImmutable $today Current local date.
     * @return bool
     */
    private static function already_sent_for_window( string $frequency, string $last_sent_at, DateTimeImmutable $today ): bool {
        if ( empty( $last_sent_at ) ) {
            return false;
        }

        try {
            $last_sent = new DateTimeImmutable( $last_sent_at, wp_timezone() );
        } catch ( Exception $exception ) {
            return false;
        }

        if ( 'weekly' === $frequency ) {
            return $last_sent->format( 'oW' ) === $today->format( 'oW' );
        }

        if ( 'monthly' === $frequency ) {
            return $last_sent->format( 'Ym' ) === $today->format( 'Ym' );
        }

        return false;
    }

    /**
     * Reporting range for the scheduled send.
     *
     * @param string            $frequency Frequency.
     * @param DateTimeImmutable $today Current local date.
     * @return array<string, string>
     */
    private static function reporting_range( string $frequency, DateTimeImmutable $today ): array {
        if ( 'monthly' === $frequency ) {
            $from = $today->modify( 'first day of last month' )->format( 'Y-m-d' );
            $to   = $today->modify( 'last day of last month' )->format( 'Y-m-d' );

            return array(
                'from' => $from,
                'to'   => $to,
            );
        }

        return array(
            'from' => $today->modify( '-7 days' )->format( 'Y-m-d' ),
            'to'   => $today->modify( '-1 day' )->format( 'Y-m-d' ),
        );
    }

    /**
     * Build the HTML message body.
     *
     * @param string               $type Report type.
     * @param array<string, mixed> $dashboard Dashboard payload.
     * @return string
     */
    private static function build_html( string $type, array $dashboard ): string {
        $metrics = array(
            __( 'Revenue', WCPI_TEXT_DOMAIN ) => wp_strip_all_tags( WCPI_Helpers::format_price( (float) $dashboard['revenue'] ) ),
            __( 'Net Profit', WCPI_TEXT_DOMAIN ) => wp_strip_all_tags( WCPI_Helpers::format_price( (float) $dashboard['net_profit'] ) ),
            __( 'Expenses', WCPI_TEXT_DOMAIN ) => wp_strip_all_tags( WCPI_Helpers::format_price( (float) $dashboard['expenses'] ) ),
            __( 'Margin', WCPI_TEXT_DOMAIN ) => number_format_i18n( (float) $dashboard['margin_percent'], 1 ) . '%',
            __( 'Orders', WCPI_TEXT_DOMAIN ) => number_format_i18n( (int) $dashboard['orders_count'] ),
            __( 'Refunds', WCPI_TEXT_DOMAIN ) => wp_strip_all_tags( WCPI_Helpers::format_price( (float) $dashboard['refunds_total'] ) ),
        );

        $html  = '<div style="font-family:Arial,sans-serif;max-width:760px;margin:0 auto;color:#0f172a;">';
        $html .= '<h1 style="margin-bottom:4px;">SG365 Profit &amp; Expense Tracker</h1>';
        $html .= '<p style="margin-top:0;color:#475569;">' . esc_html( WCPI_Helpers::email_report_types()[ $type ] ) . ' | ' . esc_html( $dashboard['range_label'] ) . '</p>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';

        foreach ( $metrics as $label => $value ) {
            $html .= '<tr>';
            $html .= '<td style="padding:10px;border:1px solid #e2e8f0;font-weight:600;">' . esc_html( $label ) . '</td>';
            $html .= '<td style="padding:10px;border:1px solid #e2e8f0;">' . esc_html( $value ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        if ( 'expense_report' === $type ) {
            $html .= '<h2>' . esc_html__( 'Expense Budget vs Actual', WCPI_TEXT_DOMAIN ) . '</h2><ul>';
            foreach ( array_slice( $dashboard['budget_vs_actual'] ?? array(), 0, 6 ) as $row ) {
                $html .= '<li>' . esc_html( $row['display_name'] ?? $row['category'] ) . ': '
                    . esc_html( wp_strip_all_tags( WCPI_Helpers::format_price( (float) $row['total'] ) ) )
                    . ' / '
                    . esc_html( wp_strip_all_tags( WCPI_Helpers::format_price( (float) $row['budget'] ) ) )
                    . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<h2>' . esc_html__( 'Highlights', WCPI_TEXT_DOMAIN ) . '</h2><ul>';
            $html .= self::highlight_list_items( $dashboard['performance_highlights'] ?? array() );
            $html .= '</ul>';
        }

        $html .= '<h2>' . esc_html__( 'Recommendations', WCPI_TEXT_DOMAIN ) . '</h2><ul>';
        foreach ( array_slice( $dashboard['recommendations'] ?? array(), 0, 5 ) as $item ) {
            $html .= '<li><strong>' . esc_html( $item['title'] ) . ':</strong> ' . esc_html( $item['body'] ) . '</li>';
        }
        $html .= '</ul>';

        if ( ! empty( $dashboard['trend_intelligence'] ) ) {
            $html .= '<h2>' . esc_html__( 'Trend Intelligence', WCPI_TEXT_DOMAIN ) . '</h2><ul>';
            foreach ( array_slice( $dashboard['trend_intelligence'], 0, 4 ) as $item ) {
                $html .= '<li><strong>' . esc_html( $item['title'] ) . ':</strong> ' . esc_html( $item['body'] ) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Highlight list items.
     *
     * @param array<string, mixed> $highlights Highlights.
     * @return string
     */
    private static function highlight_list_items( array $highlights ): string {
        $items = array();

        if ( ! empty( $highlights['best_sales_day']['summary_date'] ) ) {
            $items[] = sprintf(
                '%1$s: %2$s (%3$s)',
                __( 'Best Sales Day', WCPI_TEXT_DOMAIN ),
                WCPI_Helpers::date_label( $highlights['best_sales_day']['summary_date'] ),
                wp_strip_all_tags( WCPI_Helpers::format_price( (float) $highlights['best_sales_day']['gross_revenue'] ) )
            );
        }

        if ( ! empty( $highlights['highest_profit_day']['summary_date'] ) ) {
            $items[] = sprintf(
                '%1$s: %2$s (%3$s)',
                __( 'Highest Profit Day', WCPI_TEXT_DOMAIN ),
                WCPI_Helpers::date_label( $highlights['highest_profit_day']['summary_date'] ),
                wp_strip_all_tags( WCPI_Helpers::format_price( (float) $highlights['highest_profit_day']['net_profit'] ) )
            );
        }

        if ( ! empty( $highlights['top_product']['name'] ) ) {
            $items[] = sprintf(
                '%1$s: %2$s',
                __( 'Top Product', WCPI_TEXT_DOMAIN ),
                $highlights['top_product']['name']
            );
        }

        if ( ! empty( $highlights['top_category']['category'] ) ) {
            $items[] = sprintf(
                '%1$s: %2$s',
                __( 'Top Category', WCPI_TEXT_DOMAIN ),
                $highlights['top_category']['category']
            );
        }

        if ( empty( $items ) ) {
            $items[] = __( 'No highlights available for this period.', WCPI_TEXT_DOMAIN );
        }

        return '<li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li>';
    }
}
