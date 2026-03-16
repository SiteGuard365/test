<?php
/**
 * Plugin Name: SG365 Profit & Expense Tracker for WooCommerce
 * Plugin URI:  https://siteguard365.com/
 * Description: Track real store profit, product costs, and business expenses directly inside WooCommerce.
 * Version:     1.4.5
 * Author:      Site Guard 365
 * Author URI:  https://siteguard365.com/
 * Text Domain: sg365-profit-expense-tracker
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPI_VERSION', '1.4.5' );
define( 'WCPI_PLUGIN_FILE', __FILE__ );
define( 'WCPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCPI_TEXT_DOMAIN', 'sg365-profit-expense-tracker' );

require_once WCPI_PLUGIN_DIR . 'includes/class-loader.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-db.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-helpers.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-security.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-filesystem.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-logger.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-cache.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-installer.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-activator.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-cron.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-backup.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-ajax.php';
require_once WCPI_PLUGIN_DIR . 'includes/class-rest.php';

require_once WCPI_PLUGIN_DIR . 'modules/settings/class-settings-manager.php';
require_once WCPI_PLUGIN_DIR . 'modules/product-costs/class-cost-history.php';
require_once WCPI_PLUGIN_DIR . 'modules/product-costs/class-product-cost-manager.php';
require_once WCPI_PLUGIN_DIR . 'modules/expenses/class-expense-manager.php';
require_once WCPI_PLUGIN_DIR . 'modules/expenses/class-recurring-expense-manager.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-profit-calculator.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-aggregates.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-report-query.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-daily-summary.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-product-health-scorer.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-analytics-engine.php';
require_once WCPI_PLUGIN_DIR . 'modules/analytics/class-insights-engine.php';
require_once WCPI_PLUGIN_DIR . 'modules/exports/class-export-csv.php';
require_once WCPI_PLUGIN_DIR . 'modules/exports/class-export-pdf.php';
require_once WCPI_PLUGIN_DIR . 'modules/exports/class-export-xlsx.php';
require_once WCPI_PLUGIN_DIR . 'modules/exports/class-email-reporter.php';
require_once WCPI_PLUGIN_DIR . 'modules/woocommerce/class-refund-handler.php';
require_once WCPI_PLUGIN_DIR . 'modules/woocommerce/class-order-sync.php';
require_once WCPI_PLUGIN_DIR . 'modules/woocommerce/class-hooks.php';

require_once WCPI_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-dashboard-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-products-cost-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-expenses-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-reports-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-export-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-diagnostics-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-settings-page.php';
require_once WCPI_PLUGIN_DIR . 'admin/class-admin.php';

register_activation_hook( __FILE__, array( 'WCPI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCPI_Deactivator', 'deactivate' ) );

final class WCPI_Plugin {

    /**
     * Loader.
     *
     * @var WCPI_Loader
     */
    private WCPI_Loader $loader;

    /**
     * Singleton.
     *
     * @var WCPI_Plugin|null
     */
    private static ?WCPI_Plugin $instance = null;

    /**
     * Get instance.
     *
     * @return WCPI_Plugin
     */
    public static function instance(): WCPI_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->loader = new WCPI_Loader();
        $this->load_textdomain();
        $this->maybe_flag_woocommerce_notice();
        $this->declare_woocommerce_compatibility();

        WCPI_Filesystem::ensure_base_structure();
        WCPI_Cron::register();
        add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );

        ( new WCPI_Settings_Manager() )->register();
        ( new WCPI_Product_Cost_Manager() )->register();
        ( new WCPI_Expense_Manager() )->register();
        ( new WCPI_Recurring_Expense_Manager() )->register();
        ( new WCPI_Order_Sync() )->register();
        ( new WCPI_Hooks() )->register();
        ( new WCPI_Ajax() )->register();
        ( new WCPI_REST() )->register();

        if ( is_admin() ) {
            ( new WCPI_Admin() )->register();
        }
    }

    /**
     * Load translations.
     *
     * @return void
     */
    private function load_textdomain(): void {
        add_action(
            'plugins_loaded',
            static function () {
                load_plugin_textdomain( WCPI_TEXT_DOMAIN, false, dirname( WCPI_PLUGIN_BASENAME ) . '/languages' );
            }
        );
    }

    /**
     * Flag notice if WooCommerce missing.
     *
     * @return void
     */
    private function maybe_flag_woocommerce_notice(): void {
        add_action(
            'admin_notices',
            static function () {
                if ( WCPI_Helpers::is_woocommerce_active() ) {
                    return;
                }
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'SG365 Profit & Expense Tracker for WooCommerce requires WooCommerce to be installed and active. The plugin will stay inactive until WooCommerce is available.', WCPI_TEXT_DOMAIN ) . '</p></div>';
            }
        );
    }

    /**
     * Declare compatibility with supported WooCommerce features.
     *
     * @return void
     */
    private function declare_woocommerce_compatibility(): void {
        add_action(
            'before_woocommerce_init',
            static function () {
                if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    return;
                }

                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCPI_PLUGIN_FILE, true );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', WCPI_PLUGIN_FILE, true );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCPI_PLUGIN_FILE, true );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'remote_logging', WCPI_PLUGIN_FILE, true );
            }
        );
    }

    /**
     * Upgrade database schema if the plugin version changed.
     *
     * @return void
     */
    public function maybe_upgrade_schema(): void {
        if ( get_option( 'wcpi_db_version' ) !== WCPI_VERSION ) {
            WCPI_Installer::install();
        }
    }
}

WCPI_Plugin::instance();
