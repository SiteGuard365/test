<?php
/**
 * Reports page.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Reports_Page {

    /**
     * Render.
     *
     * @return void
     */
    public static function render(): void {
        WCPI_Security::verify_access();
        $preset  = WCPI_Security::text( $_GET['preset'] ?? WCPI_Settings_Manager::get( 'general', 'default_dashboard_range', 'last_30_days' ) );
        $from    = WCPI_Security::text( $_GET['from'] ?? '' );
        $to      = WCPI_Security::text( $_GET['to'] ?? '' );
        $compare = 'yes' === WCPI_Security::text( $_GET['compare'] ?? WCPI_Settings_Manager::get( 'general', 'compare_previous_default', 'yes' ) );
        $range   = WCPI_Helpers::resolve_date_range( $preset, $from, $to );
        $data    = WCPI_Analytics_Engine::dashboard( $range['from'], $range['to'], $compare );
        include WCPI_PLUGIN_DIR . 'admin/views/reports.php';
    }
}
