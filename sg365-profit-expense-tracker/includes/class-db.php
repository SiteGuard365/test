<?php
/**
 * Database helpers.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_DB {

    /**
     * Table map.
     *
     * @return array<string, string>
     */
    public static function tables(): array {
        global $wpdb;

        return array(
            'product_costs'         => $wpdb->prefix . 'wcpi_product_costs',
            'cost_history'          => $wpdb->prefix . 'wcpi_cost_history',
            'expenses'              => $wpdb->prefix . 'wcpi_expenses',
            'recurring_expenses'    => $wpdb->prefix . 'wcpi_recurring_expenses',
            'expense_budgets'       => $wpdb->prefix . 'wcpi_expense_budgets',
            'dashboard_notes'       => $wpdb->prefix . 'wcpi_dashboard_notes',
            'daily_summary'         => $wpdb->prefix . 'wcpi_daily_summary',
            'analytics_aggregates'  => $wpdb->prefix . 'wcpi_analytics_aggregates',
            'settings_meta'         => $wpdb->prefix . 'wcpi_settings_meta',
            'logs'                  => $wpdb->prefix . 'wcpi_logs',
            'export_history'        => $wpdb->prefix . 'wcpi_export_history',
        );
    }

    /**
     * Single table.
     *
     * @param string $key Key.
     * @return string
     */
    public static function table( string $key ): string {
        $tables = self::tables();
        return $tables[ $key ] ?? '';
    }
}
