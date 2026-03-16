<?php
/**
 * Cron jobs.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Cron {

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action( 'wcpi_daily_maintenance', array( __CLASS__, 'daily_maintenance' ) );
    }

    /**
     * Schedule cron.
     *
     * @return void
     */
    public static function schedule(): void {
        if ( ! wp_next_scheduled( 'wcpi_daily_maintenance' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wcpi_daily_maintenance' );
        }
    }

    /**
     * Unschedule.
     *
     * @return void
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( 'wcpi_daily_maintenance' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wcpi_daily_maintenance' );
        }
    }

    /**
     * Daily cleanup.
     *
     * @return void
     */
    public static function daily_maintenance(): void {
        $cache_days  = (int) WCPI_Settings_Manager::get( 'general', 'cache_retention_days', 7 );
        $export_days = (int) WCPI_Settings_Manager::get( 'general', 'export_retention_days', 14 );
        $log_days    = (int) WCPI_Settings_Manager::get( 'general', 'log_retention_days', 30 );

        WCPI_Filesystem::cleanup_old_files( 'cache', $cache_days );
        WCPI_Filesystem::cleanup_old_files( 'exports', $export_days );
        WCPI_Filesystem::cleanup_old_files( 'logs', $log_days );
        WCPI_Aggregates::cleanup();
        WCPI_Recurring_Expense_Manager::sync_generated_entries();
        WCPI_Email_Reporter::maybe_send_scheduled_report();
        WCPI_Logger::log( 'info', 'cron', 'Daily maintenance executed.' );
    }
}
