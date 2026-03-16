<?php
/**
 * Structured logger.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Logger {

    /**
     * Write log entry.
     *
     * @param string $level Level.
     * @param string $context Context.
     * @param string $message Message.
     * @param array  $metadata Metadata.
     * @return void
     */
    public static function log( string $level, string $context, string $message, array $metadata = array() ): void {
        if ( 'yes' !== WCPI_Settings_Manager::get( 'general', 'enable_logs', 'no' ) ) {
            return;
        }

        global $wpdb;
        $table = WCPI_DB::table( 'logs' );

        $wpdb->insert(
            $table,
            array(
                'log_level'     => sanitize_key( $level ),
                'context'       => sanitize_text_field( $context ),
                'message'       => sanitize_text_field( $message ),
                'metadata_json' => wp_json_encode( $metadata ),
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        $base  = WCPI_Helpers::upload_base();
        $dir   = $base['dir'] . 'logs/';
        $file  = $dir . 'wcpi-' . gmdate( 'Y-m-d' ) . '.log';
        $line  = '[' . gmdate( 'c' ) . '] ' . strtoupper( $level ) . ' ' . $context . ': ' . $message . ' ' . wp_json_encode( $metadata ) . PHP_EOL;
        @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
}
