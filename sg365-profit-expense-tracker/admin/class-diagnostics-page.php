<?php
/**
 * Diagnostics page.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Diagnostics_Page {

    /**
     * Render.
     *
     * @return void
     */
    public static function render(): void {
        WCPI_Security::verify_access();

        global $wpdb;
        $tables = array();
        foreach ( WCPI_DB::tables() as $key => $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
            $tables[ $key ] = array(
                'name'   => $table,
                'exists' => $exists,
                'rows'   => $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            );
        }

        $base = WCPI_Helpers::upload_base();
        $storage = WCPI_Report_Query::storage_metrics();
        $paths = array(
            'uploads' => $base['dir'],
            'exports' => $base['dir'] . 'exports/',
            'cache'   => $base['dir'] . 'cache/',
            'logs'    => $base['dir'] . 'logs/',
            'backups' => $base['dir'] . 'backups/',
        );
        $folders = array();
        foreach ( $paths as $key => $path ) {
            $folders[ $key ] = array(
                'path'     => $path,
                'exists'   => is_dir( $path ),
                'writable' => is_dir( $path ) && wp_is_writable( $path ),
            );
        }

        $log_page = max( 1, absint( $_GET['log_paged'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $log_page - 1 ) * $per_page;
        $logs     = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . WCPI_DB::table( 'logs' ) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        $log_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . WCPI_DB::table( 'logs' ) );
        $recurring_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . WCPI_DB::table( 'recurring_expenses' ) );
        $last_sync_status = WCPI_Settings_Manager::get( 'general', 'last_sync_status', __( 'No sync logged yet.', WCPI_TEXT_DOMAIN ) );
        $last_rebuild_status = WCPI_Settings_Manager::get( 'general', 'last_analytics_rebuild_status', __( 'No rebuild recorded yet.', WCPI_TEXT_DOMAIN ) );
        $last_rebuild_at = WCPI_Settings_Manager::get( 'general', 'last_analytics_rebuild_at', '' );
        $missing_tables = array_filter(
            $tables,
            static function ( array $table ): bool {
                return empty( $table['exists'] );
            }
        );
        $health_cards = array(
            array( 'label' => __( 'Database Status', WCPI_TEXT_DOMAIN ), 'value' => empty( $missing_tables ) ? __( 'Healthy', WCPI_TEXT_DOMAIN ) : __( 'Missing Tables', WCPI_TEXT_DOMAIN ), 'status' => empty( $missing_tables ) ? 'healthy' : 'error' ),
            array( 'label' => __( 'Uploads Folder', WCPI_TEXT_DOMAIN ), 'value' => $folders['uploads']['writable'] ? __( 'Writable', WCPI_TEXT_DOMAIN ) : __( 'Blocked', WCPI_TEXT_DOMAIN ), 'status' => $folders['uploads']['writable'] ? 'healthy' : 'error' ),
            array( 'label' => __( 'Exports Folder', WCPI_TEXT_DOMAIN ), 'value' => $folders['exports']['writable'] ? __( 'Writable', WCPI_TEXT_DOMAIN ) : __( 'Blocked', WCPI_TEXT_DOMAIN ), 'status' => $folders['exports']['writable'] ? 'healthy' : 'error' ),
            array( 'label' => __( 'Cache Status', WCPI_TEXT_DOMAIN ), 'value' => $folders['cache']['writable'] ? WCPI_Helpers::human_bytes( $storage['cache'] ) : __( 'Unavailable', WCPI_TEXT_DOMAIN ), 'status' => $folders['cache']['writable'] ? 'healthy' : 'error' ),
            array( 'label' => __( 'Logging Status', WCPI_TEXT_DOMAIN ), 'value' => 'yes' === WCPI_Settings_Manager::get( 'general', 'enable_logs', 'no' ) ? __( 'Enabled', WCPI_TEXT_DOMAIN ) : __( 'Disabled', WCPI_TEXT_DOMAIN ), 'status' => 'yes' === WCPI_Settings_Manager::get( 'general', 'enable_logs', 'no' ) ? 'healthy' : 'warning' ),
            array( 'label' => __( 'Last Sync Status', WCPI_TEXT_DOMAIN ), 'value' => $last_sync_status, 'status' => __( 'No sync logged yet.', WCPI_TEXT_DOMAIN ) === $last_sync_status ? 'warning' : 'healthy' ),
            array( 'label' => __( 'Analytics Rebuild', WCPI_TEXT_DOMAIN ), 'value' => $last_rebuild_at ? $last_rebuild_at : __( 'Not run yet', WCPI_TEXT_DOMAIN ), 'status' => $last_rebuild_at ? 'healthy' : 'warning' ),
            array( 'label' => __( 'Recurring Templates', WCPI_TEXT_DOMAIN ), 'value' => number_format_i18n( $recurring_count ), 'status' => $recurring_count > 0 ? 'healthy' : 'warning' ),
            array( 'label' => __( 'Backup Status', WCPI_TEXT_DOMAIN ), 'value' => 'yes' === WCPI_Settings_Manager::get( 'general', 'backup_enabled', 'no' ) ? __( 'Enabled', WCPI_TEXT_DOMAIN ) : __( 'Disabled', WCPI_TEXT_DOMAIN ), 'status' => 'yes' === WCPI_Settings_Manager::get( 'general', 'backup_enabled', 'no' ) ? 'healthy' : 'warning' ),
        );

        include WCPI_PLUGIN_DIR . 'admin/views/diagnostics.php';
    }
}
