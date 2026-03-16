<?php
/**
 * Miscellaneous WooCommerce hooks.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Hooks {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_filter( 'plugin_action_links_' . WCPI_PLUGIN_BASENAME, array( $this, 'plugin_links' ) );
    }

    /**
     * Add plugin action links.
     *
     * @param array<int, string> $links Links.
     * @return array<int, string>
     */
    public function plugin_links( array $links ): array {
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wcpi-dashboard' ) ) . '">' . esc_html__( 'Dashboard', WCPI_TEXT_DOMAIN ) . '</a>';
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wcpi-settings' ) ) . '">' . esc_html__( 'Settings', WCPI_TEXT_DOMAIN ) . '</a>';
        return $links;
    }
}
