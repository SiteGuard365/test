<?php
/**
 * Admin bootstrap.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Admin {

    /**
     * Register admin features.
     *
     * @return void
     */
    public function register(): void {
        ( new WCPI_Admin_Menu() )->register();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'admin_post_wcpi_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_wcpi_generate_export', array( $this, 'generate_export' ) );
        add_action( 'admin_post_wcpi_maintenance', array( $this, 'maintenance_action' ) );
        add_action( 'admin_post_wcpi_download_diagnostics_snapshot', array( $this, 'download_diagnostics_snapshot' ) );
        add_action( 'admin_post_wcpi_save_product_costs_page', array( $this, 'save_product_costs_page' ) );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Hook.
     * @return void
     */
    public function enqueue_assets( string $hook_suffix ): void {
        $is_wcpi_page      = false !== strpos( $hook_suffix, 'wcpi' ) || false !== strpos( $hook_suffix, 'product' );
        $is_dashboard_page = 'index.php' === $hook_suffix;

        if ( ! $is_wcpi_page && ! $is_dashboard_page ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'wcpi-admin', WCPI_PLUGIN_URL . 'assets/css/admin.css', array(), WCPI_VERSION );

        if ( ! $is_wcpi_page ) {
            return;
        }

        wp_enqueue_style( 'wcpi-dashboard', WCPI_PLUGIN_URL . 'assets/css/dashboard.css', array( 'wcpi-admin' ), WCPI_VERSION );
        wp_enqueue_script( 'wcpi-admin', WCPI_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WCPI_VERSION, true );
        wp_enqueue_script( 'wcpi-dashboard', WCPI_PLUGIN_URL . 'assets/js/dashboard.js', array( 'jquery', 'wcpi-admin' ), WCPI_VERSION, true );
        wp_enqueue_script( 'wcpi-reports', WCPI_PLUGIN_URL . 'assets/js/reports.js', array( 'jquery', 'wcpi-admin' ), WCPI_VERSION, true );

        wp_localize_script(
            'wcpi-admin',
            'wcpiAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'postUrl'   => admin_url( 'admin-post.php' ),
                'adminBase' => admin_url( 'admin.php' ),
                'nonce'     => wp_create_nonce( 'wcpi_admin_nonce' ),
                'currency'  => WCPI_Helpers::currency_context(),
                'brandUrl'  => WCPI_Helpers::site_guard_365_url(),
                'noteCategories' => WCPI_Helpers::dashboard_note_categories(),
                'noteColors'     => WCPI_Helpers::dashboard_note_colors(),
                'pages'     => array(
                    'dashboard'    => admin_url( 'admin.php?page=wcpi-dashboard' ),
                    'reports'      => admin_url( 'admin.php?page=wcpi-reports' ),
                    'expenses'     => admin_url( 'admin.php?page=wcpi-expenses' ),
                    'productCosts' => admin_url( 'admin.php?page=wcpi-product-costs' ),
                    'export'       => admin_url( 'admin.php?page=wcpi-export' ),
                    'diagnostics'  => admin_url( 'admin.php?page=wcpi-diagnostics' ),
                    'settings'     => admin_url( 'admin.php?page=wcpi-settings' ),
                ),
                'nonces'    => array(
                    'deleteExpense'        => wp_create_nonce( 'wcpi_delete_expense' ),
                    'downloadDiagnostics'  => wp_create_nonce( 'wcpi_download_diagnostics_snapshot' ),
                ),
                'i18n'      => array(
                    'loading'      => __( 'Loading...', WCPI_TEXT_DOMAIN ),
                    'error'        => __( 'Something went wrong.', WCPI_TEXT_DOMAIN ),
                    'noData'       => __( 'No data available for this section yet.', WCPI_TEXT_DOMAIN ),
                    'confirmClear' => __( 'Are you sure you want to run this maintenance action?', WCPI_TEXT_DOMAIN ),
                    'saved'        => __( 'Saved successfully.', WCPI_TEXT_DOMAIN ),
                    'saving'       => __( 'Saving...', WCPI_TEXT_DOMAIN ),
                    'downloadReady'=> __( 'Export ready.', WCPI_TEXT_DOMAIN ),
                    'savedState'   => __( 'Saved', WCPI_TEXT_DOMAIN ),
                    'unsavedState' => __( 'Unsaved', WCPI_TEXT_DOMAIN ),
                    'healthy'      => __( 'Healthy', WCPI_TEXT_DOMAIN ),
                    'zeroCost'     => __( 'Zero Cost', WCPI_TEXT_DOMAIN ),
                    'missingCost'  => __( 'Missing Cost', WCPI_TEXT_DOMAIN ),
                    'incompleteRow'=> __( 'Incomplete Row', WCPI_TEXT_DOMAIN ),
                    'lowMargin'    => __( 'Low Margin', WCPI_TEXT_DOMAIN ),
                    'variationGap' => __( 'Variation Gap', WCPI_TEXT_DOMAIN ),
                    'close'        => __( 'Close', WCPI_TEXT_DOMAIN ),
                    'cancel'       => __( 'Cancel', WCPI_TEXT_DOMAIN ),
                    'confirm'      => __( 'Confirm', WCPI_TEXT_DOMAIN ),
                    'addAnother'   => __( 'Add New', WCPI_TEXT_DOMAIN ),
                    'noteEmailSent'=> __( 'Note email sent.', WCPI_TEXT_DOMAIN ),
                    'noteRemoved'  => __( 'Note removed from active dashboard surfaces.', WCPI_TEXT_DOMAIN ),
                    'noteEnded'    => __( 'Note ended immediately.', WCPI_TEXT_DOMAIN ),
                    'viewNote'     => __( 'View', WCPI_TEXT_DOMAIN ),
                    'removeNote'   => __( 'Remove', WCPI_TEXT_DOMAIN ),
                    'endNote'      => __( 'End Note', WCPI_TEXT_DOMAIN ),
                    'confirmEndNoteTitle' => __( 'End note now?', WCPI_TEXT_DOMAIN ),
                    'confirmEndNoteBody'  => __( 'This will stop the note immediately even if its expiry date is in the future.', WCPI_TEXT_DOMAIN ),
                    'confirmRemoveNoteTitle' => __( 'Remove note from active surfaces?', WCPI_TEXT_DOMAIN ),
                    'confirmRemoveNoteBody'  => __( 'This hides the note from the Overview header and the WordPress dashboard without deleting its history.', WCPI_TEXT_DOMAIN ),
                    'activeStatus'  => __( 'Active', WCPI_TEXT_DOMAIN ),
                    'removedStatus' => __( 'Removed', WCPI_TEXT_DOMAIN ),
                    'endedStatus'   => __( 'Ended', WCPI_TEXT_DOMAIN ),
                ),
            )
        );
    }

    /**
     * Register WordPress dashboard widget.
     *
     * @return void
     */
    public function register_dashboard_widget(): void {
        if ( ! current_user_can( WCPI_Helpers::capability() ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'wcpi_dashboard_notes_widget',
            __( 'SG365 Planning Notes', WCPI_TEXT_DOMAIN ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render active notes on the core WordPress dashboard.
     *
     * @return void
     */
    public function render_dashboard_widget(): void {
        $notes = WCPI_Recurring_Expense_Manager::active_notes( 6 );

        echo '<div class="wcpi-shell wcpi-dashboard-widget">';
        echo '<div class="wcpi-dashboard-widget-head">';
        echo '<div><strong>' . esc_html__( 'Active planning notes', WCPI_TEXT_DOMAIN ) . '</strong><p>' . esc_html__( 'Notes that are effective today also appear in your Profit Intelligence overview.', WCPI_TEXT_DOMAIN ) . '</p></div>';
        echo '<a class="button button-primary button-small" href="' . esc_url( admin_url( 'admin.php?page=wcpi-dashboard&tab=planning' ) ) . '">' . esc_html__( 'Open Overview', WCPI_TEXT_DOMAIN ) . '</a>';
        echo '</div>';

        if ( empty( $notes ) ) {
            echo '<div class="wcpi-empty-inline">' . esc_html__( 'No active notes for today.', WCPI_TEXT_DOMAIN ) . '</div>';
            echo '</div>';
            return;
        }

        echo '<div class="wcpi-dashboard-widget-notes">';
        foreach ( $notes as $note ) {
            echo '<article class="wcpi-note wcpi-note-theme-' . esc_attr( $note['note_color'] ?? 'blue' ) . '">';
            echo '<div class="wcpi-note-head"><strong>' . esc_html( $note['note_title'] ?? '' ) . '</strong><span class="wcpi-badge wcpi-badge-neutral">' . esc_html( $note['category_label'] ?? __( 'Update', WCPI_TEXT_DOMAIN ) ) . '</span></div>';
            echo '<small>' . esc_html( $note['date_range_label'] ?? '' ) . '</small>';
            echo '<p>' . esc_html( $note['note_body'] ?? '' ) . '</p>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Save settings.
     *
     * @return void
     */
    public function save_settings(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_settings' );
        $tab = WCPI_Security::text( $_POST['return_tab'] ?? 'general' );

        $group    = 'general';
        $defaults = WCPI_Settings_Manager::editable_defaults()[ $group ];

        foreach ( $defaults as $key => $default_value ) {
            if ( is_numeric( $default_value ) ) {
                $value = WCPI_Security::decimal( $_POST[ $key ] ?? $default_value );
            } else {
                $value = WCPI_Security::text( $_POST[ $key ] ?? 'no' );
                if ( in_array( $default_value, array( 'yes', 'no' ), true ) ) {
                    $value = 'yes' === $value ? 'yes' : 'no';
                }
            }

            if ( 'email_reports_recipient' === $key ) {
                $value = sanitize_email( wp_unslash( $_POST[ $key ] ?? '' ) );
            }

            if ( 'email_reports_type' === $key && ! isset( WCPI_Helpers::email_report_types()[ $value ] ) ) {
                $value = 'executive_summary';
            }

            if ( 'email_reports_frequency' === $key && ! isset( WCPI_Helpers::email_report_frequencies()[ $value ] ) ) {
                $value = 'weekly';
            }

            WCPI_Settings_Manager::set( $group, $key, $value );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-settings&updated=1&tab=' . rawurlencode( $tab ) ) );
        exit;
    }

    /**
     * Generate export.
     *
     * @return void
     */
    public function generate_export(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_generate_export' );

        $report  = WCPI_Security::text( $_POST['report'] ?? 'daily_summary' );
        $format  = WCPI_Security::text( $_POST['format'] ?? 'csv' );
        $preset  = WCPI_Security::text( $_POST['preset'] ?? 'last_30_days' );
        $from    = WCPI_Security::date( $_POST['from'] ?? '' );
        $to      = WCPI_Security::date( $_POST['to'] ?? '' );
        $range   = WCPI_Helpers::resolve_date_range( $preset, $from, $to );
        $compare = 'yes' === WCPI_Security::text( $_POST['compare_previous'] ?? 'no' );
        $tab     = WCPI_Security::text( $_POST['return_tab'] ?? 'generate' );

        if ( 'pdf' === $format ) {
            $export = WCPI_Export_PDF::generate( $report, $range['from'], $range['to'], $compare );
        } elseif ( 'xlsx' === $format ) {
            $export = WCPI_Export_XLSX::generate( $report, $range['from'], $range['to'], $compare );
        } else {
            $export = WCPI_Export_CSV::generate( $report, $range['from'], $range['to'], $compare );
        }

        $url = '';
        if ( ! is_wp_error( $export ) ) {
            $url = rawurlencode( $export['url'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-export&generated=1&tab=' . rawurlencode( $tab ) . '&file=' . $url ) );
        exit;
    }

    /**
     * Maintenance.
     *
     * @return void
     */
    public function maintenance_action(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_maintenance' );

        $action = WCPI_Security::text( $_POST['maintenance_action'] ?? '' );

        switch ( $action ) {
            case 'clear_cache':
                WCPI_Cache::flush_group();
                WCPI_Aggregates::purge();
                break;
            case 'clear_exports':
                WCPI_Filesystem::cleanup_old_files( 'exports', 0 );
                break;
            case 'clear_logs':
                WCPI_Filesystem::cleanup_old_files( 'logs', 0 );
                global $wpdb;
                $wpdb->query( 'TRUNCATE TABLE ' . WCPI_DB::table( 'logs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                break;
            case 'rebuild_last_30':
                $range = WCPI_Helpers::resolve_date_range( 'last_30_days' );
                WCPI_Daily_Summary::rebuild_range( $range['from'], $range['to'] );
                break;
            case 'rebuild_selected':
                $from = WCPI_Security::date( $_POST['from'] ?? gmdate( 'Y-m-d' ) );
                $to   = WCPI_Security::date( $_POST['to'] ?? gmdate( 'Y-m-d' ) );
                WCPI_Daily_Summary::rebuild_range( $from, $to );
                break;
            case 'create_backup':
                WCPI_Backup::create_snapshot();
                break;
            case 'regenerate_upload_structure':
                WCPI_Filesystem::ensure_base_structure();
                break;
            case 'resync_recurring_entries':
                WCPI_Recurring_Expense_Manager::sync_generated_entries();
                WCPI_Aggregates::purge();
                break;
            case 'optimize_tables':
                global $wpdb;
                foreach ( WCPI_DB::tables() as $table ) {
                    $wpdb->query( "OPTIMIZE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
                }
                break;
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wcpi-diagnostics&updated=1' ) );
        exit;
    }

    /**
     * Download a diagnostics snapshot as JSON.
     *
     * @return void
     */
    public function download_diagnostics_snapshot(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_download_diagnostics_snapshot' );

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

        $base  = WCPI_Helpers::upload_base();
        $paths = array(
            'uploads' => $base['dir'],
            'exports' => trailingslashit( $base['dir'] ) . 'exports/',
            'cache'   => trailingslashit( $base['dir'] ) . 'cache/',
            'logs'    => trailingslashit( $base['dir'] ) . 'logs/',
            'backups' => trailingslashit( $base['dir'] ) . 'backups/',
        );
        $folders = array();
        foreach ( $paths as $key => $path ) {
            $folders[ $key ] = array(
                'path'     => $path,
                'exists'   => is_dir( $path ),
                'writable' => is_dir( $path ) && wp_is_writable( $path ),
            );
        }

        $snapshot = array(
            'plugin'          => array(
                'name'      => 'SG365 Profit & Expense Tracker for WooCommerce',
                'version'   => WCPI_VERSION,
                'generated' => current_time( 'mysql' ),
            ),
            'storage'         => WCPI_Report_Query::storage_metrics(),
            'tables'          => $tables,
            'folders'         => $folders,
            'settings'        => array(
                'logs_enabled'       => WCPI_Settings_Manager::get( 'general', 'enable_logs', 'no' ),
                'backups_enabled'    => WCPI_Settings_Manager::get( 'general', 'backup_enabled', 'no' ),
                'recurring_enabled'  => WCPI_Settings_Manager::get( 'general', 'recurring_expenses_enabled', 'yes' ),
                'default_range'      => WCPI_Settings_Manager::get( 'general', 'default_dashboard_range', 'last_30_days' ),
                'currency_display'   => WCPI_Settings_Manager::get( 'general', 'currency_display', get_option( 'woocommerce_currency', 'USD' ) ),
            ),
            'last_status'     => array(
                'last_sync_status'             => WCPI_Settings_Manager::get( 'general', 'last_sync_status', '' ),
                'last_analytics_rebuild_at'    => WCPI_Settings_Manager::get( 'general', 'last_analytics_rebuild_at', '' ),
                'last_analytics_rebuild_status'=> WCPI_Settings_Manager::get( 'general', 'last_analytics_rebuild_status', '' ),
            ),
        );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( 'wcpi-diagnostics-' . gmdate( 'Ymd-His' ) . '.json' ) );
        echo wp_json_encode( $snapshot, JSON_PRETTY_PRINT );
        exit;
    }

    /**
     * Save costs from list page.
     *
     * @return void
     */
    public function save_product_costs_page(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_product_costs_page' );

        self::save_product_cost_rows(
            array(
                'product_id'      => (array) ( $_POST['product_id'] ?? array() ),
                'variation_id'    => (array) ( $_POST['variation_id'] ?? array() ),
                'cost_price'      => (array) ( $_POST['cost_price'] ?? array() ),
                'packaging_cost'  => (array) ( $_POST['packaging_cost'] ?? array() ),
                'handling_cost'   => (array) ( $_POST['handling_cost'] ?? array() ),
                'extra_cost'      => (array) ( $_POST['extra_cost'] ?? array() ),
                'total_unit_cost' => (array) ( $_POST['total_unit_cost'] ?? array() ),
            ),
            WCPI_Security::text( $_POST['bulk_action'] ?? '' ),
            WCPI_Security::decimal( $_POST['bulk_value'] ?? 0 )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-product-costs&updated=1' ) );
        exit;
    }

    /**
     * Save multiple product cost rows.
     *
     * @param array<string, array<int, mixed>> $rows Submitted rows.
     * @param string                           $bulk_mode Bulk mode.
     * @param float                            $bulk_value Bulk value.
     * @return int
     */
    public static function save_product_cost_rows( array $rows, string $bulk_mode = '', float $bulk_value = 0.0 ): int {
        $product_ids = array_map( 'absint', (array) ( $rows['product_id'] ?? array() ) );
        $updated     = 0;

        foreach ( $product_ids as $index => $product_id ) {
            if ( $product_id <= 0 ) {
                continue;
            }

            $payload = array(
                'product_id'      => $product_id,
                'variation_id'    => absint( $rows['variation_id'][ $index ] ?? 0 ),
                'cost_price'      => WCPI_Security::decimal( $rows['cost_price'][ $index ] ?? 0 ),
                'packaging_cost'  => WCPI_Security::decimal( $rows['packaging_cost'][ $index ] ?? 0 ),
                'handling_cost'   => WCPI_Security::decimal( $rows['handling_cost'][ $index ] ?? 0 ),
                'extra_cost'      => WCPI_Security::decimal( $rows['extra_cost'][ $index ] ?? 0 ),
                'total_unit_cost' => WCPI_Security::decimal( $rows['total_unit_cost'][ $index ] ?? 0 ),
            );

            if ( 'packaging' === $bulk_mode ) {
                $payload['packaging_cost'] = $bulk_value;
            } elseif ( 'handling' === $bulk_mode ) {
                $payload['handling_cost'] = $bulk_value;
            } elseif ( 'increase_fixed' === $bulk_mode ) {
                $payload['total_unit_cost'] += $bulk_value;
            } elseif ( 'decrease_fixed' === $bulk_mode ) {
                $payload['total_unit_cost'] = max( 0, $payload['total_unit_cost'] - $bulk_value );
            } elseif ( 'increase_percent' === $bulk_mode ) {
                $payload['total_unit_cost'] += $payload['total_unit_cost'] * ( $bulk_value / 100 );
            } elseif ( 'decrease_percent' === $bulk_mode ) {
                $payload['total_unit_cost'] = max( 0, $payload['total_unit_cost'] - ( $payload['total_unit_cost'] * ( $bulk_value / 100 ) ) );
            }

            if ( empty( $payload['total_unit_cost'] ) ) {
                $payload['total_unit_cost'] = $payload['cost_price'] + $payload['packaging_cost'] + $payload['handling_cost'] + $payload['extra_cost'];
            }

            WCPI_Product_Cost_Manager::upsert_cost( $payload, 'List table update' );
            ++$updated;
        }

        return $updated;
    }
}
