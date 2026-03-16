<?php
/**
 * Uninstall cleanup.
 *
 * @package WCPI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$settings_table = $wpdb->prefix . 'wcpi_settings_meta';
$delete_data    = 'no';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table ) ) === $settings_table ) {
    $delete_data = (string) $wpdb->get_var(
        $wpdb->prepare( "SELECT setting_value FROM {$settings_table} WHERE setting_key = %s LIMIT 1", 'delete_on_uninstall' )
    );
}

if ( 'yes' !== $delete_data ) {
    return;
}

$tables = array(
    $wpdb->prefix . 'wcpi_product_costs',
    $wpdb->prefix . 'wcpi_cost_history',
    $wpdb->prefix . 'wcpi_expenses',
    $wpdb->prefix . 'wcpi_recurring_expenses',
    $wpdb->prefix . 'wcpi_expense_budgets',
    $wpdb->prefix . 'wcpi_dashboard_notes',
    $wpdb->prefix . 'wcpi_daily_summary',
    $wpdb->prefix . 'wcpi_analytics_aggregates',
    $wpdb->prefix . 'wcpi_settings_meta',
    $wpdb->prefix . 'wcpi_logs',
    $wpdb->prefix . 'wcpi_export_history',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'wcpi_db_version' );

$uploads = wp_upload_dir();
$base    = trailingslashit( $uploads['basedir'] ) . 'wc-profit-intelligence';

if ( is_dir( $base ) ) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $iterator as $file ) {
        if ( $file->isDir() ) {
            rmdir( $file->getRealPath() ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rmdir
        } else {
            unlink( $file->getRealPath() ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
        }
    }

    rmdir( $base ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rmdir
}
