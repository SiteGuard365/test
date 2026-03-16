<?php
/**
 * Aggregate cache store.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Aggregates {

    /**
     * Get cached payload.
     *
     * @param string $type Type.
     * @param string $key Key.
     * @param string $from From.
     * @param string $to To.
     * @return array<string, mixed>|null
     */
    public static function get( string $type, string $key, string $from, string $to ): ?array {
        global $wpdb;
        $table = WCPI_DB::table( 'analytics_aggregates' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT payload_json FROM {$table}
                WHERE aggregate_type = %s AND aggregate_key = %s AND date_from = %s AND date_to = %s AND expires_at >= %s
                ORDER BY id DESC LIMIT 1",
                $type,
                $key,
                $from,
                $to,
                current_time( 'mysql', true )
            ),
            ARRAY_A
        );

        if ( empty( $row['payload_json'] ) ) {
            return null;
        }

        $decoded = json_decode( $row['payload_json'], true );
        return is_array( $decoded ) ? $decoded : null;
    }

    /**
     * Save payload.
     *
     * @param string $type Type.
     * @param string $key Key.
     * @param string $from From.
     * @param string $to To.
     * @param array  $payload Payload.
     * @param int    $ttl TTL seconds.
     * @return void
     */
    public static function set( string $type, string $key, string $from, string $to, array $payload, int $ttl = 1800 ): void {
        global $wpdb;
        $table = WCPI_DB::table( 'analytics_aggregates' );

        $wpdb->insert(
            $table,
            array(
                'aggregate_key'    => $key,
                'aggregate_type'   => $type,
                'date_from'        => $from,
                'date_to'          => $to,
                'payload_json'     => wp_json_encode( $payload ),
                'last_generated_at'=> current_time( 'mysql', true ),
                'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Cleanup.
     *
     * @return void
     */
    public static function cleanup(): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . WCPI_DB::table( 'analytics_aggregates' ) . ' WHERE expires_at < %s',
                current_time( 'mysql', true )
            )
        );
    }

    /**
     * Purge everything.
     *
     * @return void
     */
    public static function purge(): void {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . WCPI_DB::table( 'analytics_aggregates' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    }
}
