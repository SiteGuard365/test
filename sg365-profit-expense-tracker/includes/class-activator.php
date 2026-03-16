<?php
/**
 * Activation behavior.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Activator {

    /**
     * Activate plugin.
     *
     * @return void
     */
    public static function activate(): void {
        if ( ! WCPI_Helpers::is_woocommerce_active() ) {
            set_transient( 'wcpi_wc_missing', '1', MINUTE_IN_SECONDS * 5 );
            return;
        }

        WCPI_Installer::install();
        WCPI_Cron::schedule();
        delete_transient( 'wcpi_wc_missing' );
    }
}
