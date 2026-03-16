<?php
/**
 * Admin menu.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Admin_Menu {

    /**
     * Register admin pages.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'menu' ), 60 );
    }

    /**
     * Menu callback.
     *
     * @return void
     */
    public function menu(): void {
        $capability  = WCPI_Helpers::capability();
        $parent_slug = 'wcpi-dashboard';
        $menu_title  = __( 'Profit/Expense', WCPI_TEXT_DOMAIN );
        $page_title  = __( 'SG365 Profit Intelligence', WCPI_TEXT_DOMAIN );
        $icon        = 'dashicons-chart-area';
        $position    = 56;

        add_menu_page(
            $page_title,
            $menu_title,
            $capability,
            $parent_slug,
            array( 'WCPI_Dashboard_Page', 'render' ),
            $icon,
            $position
        );

        add_submenu_page( $parent_slug, __( 'Profit Overview', WCPI_TEXT_DOMAIN ), __( 'Overview', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-dashboard', array( 'WCPI_Dashboard_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Product Costs', WCPI_TEXT_DOMAIN ), __( 'Product Costs', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-product-costs', array( 'WCPI_Products_Cost_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Expenses', WCPI_TEXT_DOMAIN ), __( 'Expenses', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-expenses', array( 'WCPI_Expenses_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Reports', WCPI_TEXT_DOMAIN ), __( 'Reports', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-reports', array( 'WCPI_Reports_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Export', WCPI_TEXT_DOMAIN ), __( 'Export', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-export', array( 'WCPI_Export_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Diagnostics', WCPI_TEXT_DOMAIN ), __( 'Diagnostics', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-diagnostics', array( 'WCPI_Diagnostics_Page', 'render' ) );
        add_submenu_page( $parent_slug, __( 'Settings', WCPI_TEXT_DOMAIN ), __( 'Settings', WCPI_TEXT_DOMAIN ), $capability, 'wcpi-settings', array( 'WCPI_Settings_Page', 'render' ) );
    }
}
