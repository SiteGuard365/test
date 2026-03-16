<?php
/**
 * Export page.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Export_Page {

    /**
     * Render.
     *
     * @return void
     */
    public static function render(): void {
        WCPI_Security::verify_access();
        $recent_exports = WCPI_Report_Query::recent_exports( 12 );
        include WCPI_PLUGIN_DIR . 'admin/views/export.php';
    }
}
