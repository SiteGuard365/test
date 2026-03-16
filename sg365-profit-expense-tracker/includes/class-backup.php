<?php
/**
 * Backup snapshot utility.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Backup {

    /**
     * Create encrypted snapshot.
     *
     * @return array<string, string>|WP_Error
     */
    public static function create_snapshot() {
        if ( 'yes' !== WCPI_Settings_Manager::get( 'general', 'backup_enabled', 'no' ) ) {
            return new WP_Error( 'wcpi_backup_disabled', __( 'Backups are disabled in settings.', WCPI_TEXT_DOMAIN ) );
        }

        global $wpdb;
        $payload = array(
            'generated_at' => gmdate( 'c' ),
            'tables'       => array(),
        );

        foreach ( WCPI_DB::tables() as $key => $table ) {
            $payload['tables'][ $key ] = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        }

        $json = wp_json_encode( $payload );
        $key  = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv   = random_bytes( 16 );
        $data = openssl_encrypt( $json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $data ) {
            return new WP_Error( 'wcpi_backup_error', __( 'Could not encrypt snapshot.', WCPI_TEXT_DOMAIN ) );
        }

        $blob     = base64_encode( $iv . $data );
        $filename = WCPI_Filesystem::secure_filename( 'wcpi-backup', 'snapshot' );
        $result   = WCPI_Filesystem::write_file( 'backups', $filename, $blob );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        self::enforce_retention();
        return $result;
    }

    /**
     * Enforce backup retention.
     *
     * @return void
     */
    public static function enforce_retention(): void {
        $limit = max( 1, absint( WCPI_Settings_Manager::get( 'general', 'backup_retention_limit', 5 ) ) );
        $base  = WCPI_Helpers::upload_base();
        $dir   = trailingslashit( $base['dir'] ) . 'backups/';
        $files = glob( $dir . '*.snapshot' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob

        if ( ! is_array( $files ) || count( $files ) <= $limit ) {
            return;
        }

        usort(
            $files,
            static function ( $a, $b ) {
                return filemtime( $b ) <=> filemtime( $a );
            }
        );

        foreach ( array_slice( $files, $limit ) as $file ) {
            @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
        }
    }
}
