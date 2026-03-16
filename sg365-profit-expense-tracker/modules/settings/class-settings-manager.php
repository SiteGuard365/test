<?php
/**
 * Settings manager.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Settings_Manager {

    /**
     * Defaults.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array {
        return array(
            'general' => array(
                'currency_display'         => get_option( 'woocommerce_currency', 'USD' ),
                'include_tax'              => 'no',
                'include_shipping'         => 'yes',
                'default_dashboard_range'  => 'last_30_days',
                'compare_previous_default' => 'yes',
                'recurring_expenses_enabled' => 'yes',
                'margin_threshold'         => 20,
                'missing_cost_warning_threshold' => 1,
                'enable_logs'              => 'no',
                'log_retention_days'       => 30,
                'cache_retention_days'     => 7,
                'export_retention_days'    => 14,
                'backup_enabled'           => 'no',
                'backup_retention_limit'   => 5,
                'monthly_revenue_goal'     => 0,
                'monthly_profit_goal'      => 0,
                'monthly_margin_goal'      => 0,
                'email_reports_enabled'    => 'no',
                'email_reports_recipient'  => get_option( 'admin_email', '' ),
                'email_reports_type'       => 'executive_summary',
                'email_reports_frequency'  => 'weekly',
                'delete_on_uninstall'      => 'no',
                'last_sync_status'         => '',
                'last_analytics_rebuild_status' => '',
                'last_analytics_rebuild_at' => '',
                'email_reports_last_sent_at' => '',
            ),
        );
    }

    /**
     * Editable defaults exposed on settings page.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function editable_defaults(): array {
        $defaults = self::defaults();

        unset(
            $defaults['general']['last_sync_status'],
            $defaults['general']['last_analytics_rebuild_status'],
            $defaults['general']['last_analytics_rebuild_at'],
            $defaults['general']['email_reports_last_sent_at']
        );

        return $defaults;
    }

    /**
     * Register settings hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
    }

    /**
     * Ensure defaults exist.
     *
     * @return void
     */
    public static function seed_defaults(): void {
        global $wpdb;
        $table = WCPI_DB::table( 'settings_meta' );

        foreach ( self::defaults() as $group => $settings ) {
            foreach ( $settings as $key => $value ) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key )
                );

                if ( $exists ) {
                    continue;
                }

                $wpdb->insert(
                    $table,
                    array(
                        'setting_group' => $group,
                        'setting_key'   => $key,
                        'setting_value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
                        'autoload_flag' => 0,
                        'updated_at'    => current_time( 'mysql', true ),
                    ),
                    array( '%s', '%s', '%s', '%d', '%s' )
                );
            }
        }
    }

    /**
     * Get a setting.
     *
     * @param string $group Group.
     * @param string $key Key.
     * @param mixed  $default Default.
     * @return mixed
     */
    public static function get( string $group, string $key, $default = '' ) {
        global $wpdb;
        $table = WCPI_DB::table( 'settings_meta' );

        $value = $wpdb->get_var(
            $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_group = %s AND setting_key = %s LIMIT 1", $group, $key )
        );

        if ( null === $value ) {
            return $default;
        }

        if ( is_numeric( $value ) && ! str_contains( (string) $value, '.' ) ) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * Set a setting.
     *
     * @param string $group Group.
     * @param string $key Key.
     * @param mixed  $value Value.
     * @param int    $autoload Autoload.
     * @return void
     */
    public static function set( string $group, string $key, $value, int $autoload = 0 ): void {
        global $wpdb;
        $table = WCPI_DB::table( 'settings_meta' );

        $wpdb->replace(
            $table,
            array(
                'id'            => $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) ),
                'setting_group' => $group,
                'setting_key'   => $key,
                'setting_value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
                'autoload_flag' => $autoload,
                'updated_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Get all group settings.
     *
     * @param string $group Group.
     * @return array<string, string>
     */
    public static function group( string $group ): array {
        global $wpdb;
        $table = WCPI_DB::table( 'settings_meta' );

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT setting_key, setting_value FROM {$table} WHERE setting_group = %s", $group ),
            ARRAY_A
        );

        $settings = array();
        foreach ( $rows as $row ) {
            $settings[ $row['setting_key'] ] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Upgrade/install missing settings.
     *
     * @return void
     */
    public function maybe_upgrade(): void {
        self::seed_defaults();
    }
}
