<?php
/**
 * Generic helpers.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Helpers {

    /**
     * Whether WooCommerce is active.
     *
     * @return bool
     */
    public static function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Standard capability.
     *
     * @return string
     */
    public static function capability(): string {
        return 'manage_woocommerce';
    }

    /**
     * Allowed expense categories.
     *
     * @return array<string, string>
     */
    public static function expense_categories(): array {
        return array(
            'advertising'   => __( 'Advertising', WCPI_TEXT_DOMAIN ),
            'shipping'      => __( 'Shipping', WCPI_TEXT_DOMAIN ),
            'packaging'     => __( 'Packaging', WCPI_TEXT_DOMAIN ),
            'salaries'      => __( 'Salaries', WCPI_TEXT_DOMAIN ),
            'warehouse'     => __( 'Warehouse', WCPI_TEXT_DOMAIN ),
            'software'      => __( 'Software', WCPI_TEXT_DOMAIN ),
            'tax_adjustment'=> __( 'Tax Adjustment', WCPI_TEXT_DOMAIN ),
            'utilities'     => __( 'Utilities', WCPI_TEXT_DOMAIN ),
            'rent'          => __( 'Rent', WCPI_TEXT_DOMAIN ),
            'miscellaneous' => __( 'Miscellaneous', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Allowed dashboard note categories.
     *
     * @return array<string, string>
     */
    public static function dashboard_note_categories(): array {
        return array(
            'update'   => __( 'Update', WCPI_TEXT_DOMAIN ),
            'alert'    => __( 'Alert', WCPI_TEXT_DOMAIN ),
            'campaign' => __( 'Campaign', WCPI_TEXT_DOMAIN ),
            'reminder' => __( 'Reminder', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Allowed dashboard note color themes.
     *
     * @return array<string, string>
     */
    public static function dashboard_note_colors(): array {
        return array(
            'blue'    => __( 'Blue', WCPI_TEXT_DOMAIN ),
            'emerald' => __( 'Emerald', WCPI_TEXT_DOMAIN ),
            'amber'   => __( 'Amber', WCPI_TEXT_DOMAIN ),
            'rose'    => __( 'Rose', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Get upload URL or path.
     *
     * @return array<string, string>
     */
    public static function upload_base(): array {
        $uploads = wp_upload_dir();
        $basedir = trailingslashit( $uploads['basedir'] ) . 'wc-profit-intelligence/';
        $baseurl = trailingslashit( $uploads['baseurl'] ) . 'wc-profit-intelligence/';

        return array(
            'dir' => $basedir,
            'url' => $baseurl,
        );
    }

    /**
     * Date presets.
     *
     * @return array<string, string>
     */
    public static function date_presets(): array {
        return array(
            'today'       => __( 'Today', WCPI_TEXT_DOMAIN ),
            'yesterday'   => __( 'Yesterday', WCPI_TEXT_DOMAIN ),
            'last_7_days' => __( 'Last 7 Days', WCPI_TEXT_DOMAIN ),
            'last_30_days'=> __( 'Last 30 Days', WCPI_TEXT_DOMAIN ),
            'this_month'  => __( 'Month to Date', WCPI_TEXT_DOMAIN ),
            'this_quarter'=> __( 'Quarter to Date', WCPI_TEXT_DOMAIN ),
            'this_year'   => __( 'Year to Date', WCPI_TEXT_DOMAIN ),
            'custom'      => __( 'Custom Range', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Resolve date range.
     *
     * @param string      $preset Preset.
     * @param string|null $from From.
     * @param string|null $to To.
     * @return array<string, string>
     */
    public static function resolve_date_range( string $preset = 'last_30_days', ?string $from = null, ?string $to = null ): array {
        $timezone = wp_timezone();
        $today    = new DateTimeImmutable( 'now', $timezone );
        $start    = $today->setTime( 0, 0, 0 );
        $end      = $today->setTime( 23, 59, 59 );

        switch ( $preset ) {
            case 'today':
                break;
            case 'yesterday':
                $start = $start->modify( '-1 day' );
                $end   = $end->modify( '-1 day' );
                break;
            case 'last_7_days':
                $start = $start->modify( '-6 days' );
                break;
            case 'this_month':
                $start = $start->modify( 'first day of this month' );
                break;
            case 'this_quarter':
                $month         = (int) $today->format( 'n' );
                $quarter_start = (int) ( floor( ( $month - 1 ) / 3 ) * 3 ) + 1;
                $start         = $today->setDate( (int) $today->format( 'Y' ), $quarter_start, 1 )->setTime( 0, 0, 0 );
                break;
            case 'this_year':
                $start = $today->setDate( (int) $today->format( 'Y' ), 1, 1 )->setTime( 0, 0, 0 );
                break;
            case 'custom':
                if ( $from ) {
                    $start = new DateTimeImmutable( $from . ' 00:00:00', $timezone );
                }
                if ( $to ) {
                    $end = new DateTimeImmutable( $to . ' 23:59:59', $timezone );
                }
                break;
            case 'last_30_days':
            default:
                $start = $start->modify( '-29 days' );
                break;
        }

        return array(
            'from' => $start->format( 'Y-m-d' ),
            'to'   => $end->format( 'Y-m-d' ),
        );
    }

    /**
     * Previous period for range.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<string, string>
     */
    public static function previous_period( string $from, string $to ): array {
        $timezone = wp_timezone();
        $start    = new DateTimeImmutable( $from . ' 00:00:00', $timezone );
        $end      = new DateTimeImmutable( $to . ' 23:59:59', $timezone );
        $days     = (int) $start->diff( $end )->days + 1;

        return array(
            'from' => $start->modify( '-' . $days . ' days' )->format( 'Y-m-d' ),
            'to'   => $end->modify( '-' . $days . ' days' )->format( 'Y-m-d' ),
            'days' => (string) $days,
        );
    }

    /**
     * Currency symbol.
     *
     * @return string
     */
    public static function currency_symbol(): string {
        if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
            return get_woocommerce_currency_symbol( self::currency_code() );
        }
        return '$';
    }

    /**
     * Preferred currency code.
     *
     * @return string
     */
    public static function currency_code(): string {
        $configured = WCPI_Settings_Manager::get( 'general', 'currency_display', get_option( 'woocommerce_currency', 'USD' ) );
        return is_string( $configured ) && '' !== $configured ? $configured : get_option( 'woocommerce_currency', 'USD' );
    }

    /**
     * Currency context for JS and exports.
     *
     * @return array<string, mixed>
     */
    public static function currency_context(): array {
        $code = self::currency_code();

        return array(
            'code'          => $code,
            'symbol'        => self::currency_symbol(),
            'position'      => function_exists( 'get_woocommerce_price_format' ) ? get_woocommerce_price_format() : '%1$s%2$s',
            'decimals'      => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
            'decimal_sep'   => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.',
            'thousand_sep'  => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',',
        );
    }

    /**
     * Format price.
     *
     * @param float $amount Amount.
     * @return string
     */
    public static function format_price( float $amount ): string {
        if ( function_exists( 'wc_price' ) ) {
            return wp_kses_post(
                wc_price(
                    $amount,
                    array(
                        'currency' => self::currency_code(),
                    )
                )
            );
        }
        return esc_html( self::currency_symbol() . number_format_i18n( $amount, 2 ) );
    }

    /**
     * Site Guard 365 website URL.
     *
     * @return string
     */
    public static function site_guard_365_url(): string {
        return 'https://siteguard365.com/';
    }

    /**
     * Shared subtle footer branding.
     *
     * @return string
     */
    public static function brand_footer(): string {
        return sprintf(
            /* translators: %s: Site Guard 365 link. */
            __( 'Built by %s for store teams that want clearer profit intelligence.', WCPI_TEXT_DOMAIN ),
            '<a href="' . esc_url( self::site_guard_365_url() ) . '" target="_blank" rel="noopener">Site Guard 365</a>'
        );
    }

    /**
     * Safe array get.
     *
     * @param array<mixed> $array Array.
     * @param string|int   $key Key.
     * @param mixed        $default Default.
     * @return mixed
     */
    public static function get( array $array, $key, $default = '' ) {
        return $array[ $key ] ?? $default;
    }

    /**
     * Human bytes.
     *
     * @param int $bytes Size.
     * @return string
     */
    public static function human_bytes( int $bytes ): string {
        return size_format( $bytes );
    }

    /**
     * Format compact date label.
     *
     * @param string $date Date.
     * @return string
     */
    public static function date_label( string $date ): string {
        return wp_date( get_option( 'date_format', 'M j, Y' ), strtotime( $date ) );
    }

    /**
     * Human range label.
     *
     * @param string $from From.
     * @param string $to To.
     * @return string
     */
    public static function range_label( string $from, string $to ): string {
        return self::date_label( $from ) . ' - ' . self::date_label( $to );
    }

    /**
     * Inclusive day count for a range.
     *
     * @param string $from From.
     * @param string $to To.
     * @return int
     */
    public static function range_days( string $from, string $to ): int {
        $timezone = wp_timezone();
        $start    = new DateTimeImmutable( $from . ' 00:00:00', $timezone );
        $end      = new DateTimeImmutable( $to . ' 00:00:00', $timezone );
        return max( 1, (int) $start->diff( $end )->days + 1 );
    }

    /**
     * Prorate a monthly amount across a selected range.
     *
     * @param float  $monthly_amount Monthly amount.
     * @param string $from From.
     * @param string $to To.
     * @return float
     */
    public static function prorate_monthly_amount( float $monthly_amount, string $from, string $to ): float {
        if ( $monthly_amount <= 0 ) {
            return 0.0;
        }

        $timezone = wp_timezone();
        $cursor   = new DateTimeImmutable( gmdate( 'Y-m-01', strtotime( $from ) ), $timezone );
        $end      = new DateTimeImmutable( $to . ' 00:00:00', $timezone );
        $range_start = new DateTimeImmutable( $from . ' 00:00:00', $timezone );
        $total    = 0.0;

        while ( $cursor <= $end ) {
            $month_start  = $cursor->modify( 'first day of this month' );
            $month_end    = $cursor->modify( 'last day of this month' );
            $overlap_from = $month_start > $range_start ? $month_start : $range_start;
            $overlap_to   = $month_end > $end ? $end : $month_end;

            if ( $overlap_to >= $overlap_from ) {
                $overlap_days = (int) $overlap_from->diff( $overlap_to )->days + 1;
                $days_in_month = (int) $month_start->format( 't' );
                $total += $monthly_amount * ( $overlap_days / $days_in_month );
            }

            $cursor = $cursor->modify( 'first day of next month' );
        }

        return round( $total, 6 );
    }

    /**
     * Format trend helper.
     *
     * @param array<string, mixed> $comparison Comparison data.
     * @return string
     */
    public static function trend_text( array $comparison ): string {
        $prefix = $comparison['percentage'] >= 0 ? '+' : '';
        return $prefix . number_format_i18n( (float) $comparison['percentage'], 1 ) . '%';
    }

    /**
     * Convert trend direction to arrow.
     *
     * @param string $direction Direction.
     * @return string
     */
    public static function trend_arrow( string $direction ): string {
        return 'down' === $direction ? '&darr;' : '&uarr;';
    }

    /**
     * Filesystem directory size.
     *
     * @param string $dir Directory.
     * @return int
     */
    public static function directory_size( string $dir ): int {
        if ( ! is_dir( $dir ) ) {
            return 0;
        }

        $size = 0;
        foreach ( glob( trailingslashit( $dir ) . '*' ) as $item ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
            $size += is_file( $item ) ? filesize( $item ) : self::directory_size( $item );
        }
        return $size;
    }

    /**
     * Build progress percentage.
     *
     * @param float $actual Actual.
     * @param float $goal Goal.
     * @return float
     */
    public static function progress_percent( float $actual, float $goal ): float {
        if ( $goal <= 0 ) {
            return 0.0;
        }
        return min( 100, max( 0, ( $actual / $goal ) * 100 ) );
    }

    /**
     * Product health label.
     *
     * @param int $score Score.
     * @return string
     */
    public static function health_label( int $score ): string {
        if ( $score >= 75 ) {
            return __( 'Healthy', WCPI_TEXT_DOMAIN );
        }

        if ( $score >= 50 ) {
            return __( 'Watch', WCPI_TEXT_DOMAIN );
        }

        return __( 'Needs Attention', WCPI_TEXT_DOMAIN );
    }

    /**
     * Margin heatmap band.
     *
     * @param float $margin Margin percent.
     * @return string
     */
    public static function heatmap_band( float $margin ): string {
        if ( $margin >= 30 ) {
            return 'healthy';
        }

        if ( $margin >= 15 ) {
            return 'watch';
        }

        return 'risk';
    }

    /**
     * Report type labels.
     *
     * @return array<string, string>
     */
    public static function email_report_types(): array {
        return array(
            'executive_summary' => __( 'Executive Summary', WCPI_TEXT_DOMAIN ),
            'dashboard_summary' => __( 'Profit Dashboard', WCPI_TEXT_DOMAIN ),
            'expense_report'    => __( 'Expense Report', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Scheduled report frequencies.
     *
     * @return array<string, string>
     */
    public static function email_report_frequencies(): array {
        return array(
            'weekly'  => __( 'Weekly', WCPI_TEXT_DOMAIN ),
            'monthly' => __( 'Monthly', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Expense source label.
     *
     * @param string $source Source key.
     * @return string
     */
    public static function expense_source_label( string $source ): string {
        $map = array(
            'manual'    => __( 'Manual', WCPI_TEXT_DOMAIN ),
            'recurring' => __( 'Recurring', WCPI_TEXT_DOMAIN ),
            'imported'  => __( 'Imported', WCPI_TEXT_DOMAIN ),
        );
        return $map[ $source ] ?? ucfirst( $source );
    }
}
