<?php
/**
 * Deactivation behavior.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Deactivator {

    /**
     * Deactivate.
     *
     * @return void
     */
    public static function deactivate(): void {
        WCPI_Cron::unschedule();
        WCPI_Cache::flush_group();
    }
}
