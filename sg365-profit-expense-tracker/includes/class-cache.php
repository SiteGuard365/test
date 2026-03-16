<?php
/**
 * Simple cache abstraction.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Cache {

    /**
     * Get cache.
     *
     * @param string $key Key.
     * @param mixed  $default Default.
     * @return mixed
     */
    public static function get( string $key, $default = false ) {
        $value = get_transient( 'wcpi_' . md5( $key ) );
        return false === $value ? $default : $value;
    }

    /**
     * Set cache.
     *
     * @param string $key Key.
     * @param mixed  $value Value.
     * @param int    $expiration Expiration.
     * @return void
     */
    public static function set( string $key, $value, int $expiration = HOUR_IN_SECONDS ): void {
        set_transient( 'wcpi_' . md5( $key ), $value, $expiration );
    }

    /**
     * Delete cache.
     *
     * @param string $key Key.
     * @return void
     */
    public static function delete( string $key ): void {
        delete_transient( 'wcpi_' . md5( $key ) );
    }

    /**
     * Flush plugin cache.
     *
     * @return void
     */
    public static function flush_group(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcpi_%' OR option_name LIKE '_transient_timeout_wcpi_%'"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
