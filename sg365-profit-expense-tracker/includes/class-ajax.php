<?php
/**
 * AJAX endpoints.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Ajax {

    /**
     * Register.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'wp_ajax_wcpi_dashboard_data', array( $this, 'dashboard_data' ) );
        add_action( 'wp_ajax_wcpi_reports_data', array( $this, 'reports_data' ) );
        add_action( 'wp_ajax_wcpi_rebuild_summaries', array( $this, 'rebuild_summaries' ) );
        add_action( 'wp_ajax_wcpi_run_diagnostics', array( $this, 'run_diagnostics' ) );
        add_action( 'wp_ajax_wcpi_search_products', array( $this, 'search_products' ) );
        add_action( 'wp_ajax_wcpi_simulate_cost_change', array( $this, 'simulate_cost_change' ) );
        add_action( 'wp_ajax_wcpi_simulate_price_change', array( $this, 'simulate_price_change' ) );
        add_action( 'wp_ajax_wcpi_save_expense', array( $this, 'save_expense' ) );
        add_action( 'wp_ajax_wcpi_save_expense_budget', array( $this, 'save_expense_budget' ) );
        add_action( 'wp_ajax_wcpi_save_dashboard_note', array( $this, 'save_dashboard_note' ) );
        add_action( 'wp_ajax_wcpi_hide_dashboard_note', array( $this, 'hide_dashboard_note' ) );
        add_action( 'wp_ajax_wcpi_end_dashboard_note', array( $this, 'end_dashboard_note' ) );
        add_action( 'wp_ajax_wcpi_save_product_costs', array( $this, 'save_product_costs' ) );
    }

    /**
     * Dashboard data.
     *
     * @return void
     */
    public function dashboard_data(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $preset = WCPI_Security::text( $_POST['preset'] ?? WCPI_Settings_Manager::get( 'general', 'default_dashboard_range', 'last_30_days' ) );
        $from   = WCPI_Security::date( $_POST['from'] ?? '' );
        $to     = WCPI_Security::date( $_POST['to'] ?? '' );
        $compare = 'yes' === WCPI_Security::text( $_POST['compare'] ?? WCPI_Settings_Manager::get( 'general', 'compare_previous_default', 'yes' ) );
        $range  = WCPI_Helpers::resolve_date_range( $preset, $from, $to );

        wp_send_json_success( WCPI_Analytics_Engine::dashboard( $range['from'], $range['to'], $compare ) );
    }

    /**
     * Reports data.
     *
     * @return void
     */
    public function reports_data(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $preset = WCPI_Security::text( $_POST['preset'] ?? 'last_30_days' );
        $from   = WCPI_Security::date( $_POST['from'] ?? '' );
        $to     = WCPI_Security::date( $_POST['to'] ?? '' );
        $compare = 'yes' === WCPI_Security::text( $_POST['compare'] ?? WCPI_Settings_Manager::get( 'general', 'compare_previous_default', 'yes' ) );
        $range  = WCPI_Helpers::resolve_date_range( $preset, $from, $to );
        $data   = WCPI_Analytics_Engine::dashboard( $range['from'], $range['to'], $compare );

        $response = array(
            'overview'            => $data,
            'top_products'        => $data['top_products'],
            'low_margin'          => $data['low_margin'],
            'high_revenue'        => $data['highest_revenue'],
            'most_sold'           => $data['most_sold_products'],
            'summary_rows'        => WCPI_Report_Query::summaries( $range['from'], $range['to'] ),
            'categories'          => $data['category_profit'],
            'category_heatmap'    => $data['category_margin_heatmap'],
            'recommendations'     => $data['recommendations'],
            'cost_audit'          => $data['missing_cost_audit'],
            'budget_vs_actual'    => $data['budget_vs_actual'],
            'goals'               => $data['goals'],
            'performance_highlights' => $data['performance_highlights'],
            'trend_intelligence'  => $data['trend_intelligence'],
            'report_notes'        => $data['report_notes'],
        );

        wp_send_json_success( $response );
    }

    /**
     * Rebuild summaries.
     *
     * @return void
     */
    public function rebuild_summaries(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $from = WCPI_Security::date( $_POST['from'] ?? gmdate( 'Y-m-d' ) );
        $to   = WCPI_Security::date( $_POST['to'] ?? gmdate( 'Y-m-d' ) );

        WCPI_Daily_Summary::rebuild_range( $from, $to );

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: 1: from date 2: to date */
                    __( 'Summaries rebuilt for %1$s to %2$s.', WCPI_TEXT_DOMAIN ),
                    $from,
                    $to
                ),
            )
        );
    }

    /**
     * Diagnostics.
     *
     * @return void
     */
    public function run_diagnostics(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        global $wpdb;
        $results = array();
        foreach ( WCPI_DB::tables() as $key => $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
            $results[ $key ] = array(
                'table'  => $table,
                'exists' => $exists,
                'rows'   => $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            );
        }

        $base = WCPI_Helpers::upload_base();
        $results['filesystem'] = array(
            'dir'      => $base['dir'],
            'writable' => is_dir( $base['dir'] ) && wp_is_writable( $base['dir'] ),
            'storage'  => WCPI_Report_Query::storage_metrics(),
        );

        wp_send_json_success( $results );
    }

    /**
     * Product search for simulator selectors.
     *
     * @return void
     */
    public function search_products(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $term = WCPI_Security::text( $_POST['term'] ?? '' );
        wp_send_json_success( WCPI_Report_Query::search_products( $term ) );
    }

    /**
     * Cost change simulator.
     *
     * @return void
     */
    public function simulate_cost_change(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $from         = WCPI_Security::date( $_POST['from'] ?? gmdate( 'Y-m-01' ) );
        $to           = WCPI_Security::date( $_POST['to'] ?? gmdate( 'Y-m-d' ) );
        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $new_cost     = WCPI_Security::decimal( $_POST['new_cost'] ?? 0 );

        wp_send_json_success( WCPI_Analytics_Engine::simulate_cost_change( $product_id, $variation_id, $new_cost, $from, $to ) );
    }

    /**
     * Price change simulator.
     *
     * @return void
     */
    public function simulate_price_change(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $from         = WCPI_Security::date( $_POST['from'] ?? gmdate( 'Y-m-01' ) );
        $to           = WCPI_Security::date( $_POST['to'] ?? gmdate( 'Y-m-d' ) );
        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $new_price    = WCPI_Security::decimal( $_POST['new_price'] ?? 0 );

        wp_send_json_success( WCPI_Analytics_Engine::simulate_price_change( $product_id, $variation_id, $new_price, $from, $to ) );
    }

    /**
     * Save expense via AJAX.
     *
     * @return void
     */
    public function save_expense(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $saved = WCPI_Expense_Manager::save_expense( $_POST, $_FILES );
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
        }

        $filters = array(
            'category'    => WCPI_Security::text( $_POST['filter_category'] ?? '' ),
            'source_type' => WCPI_Security::text( $_POST['filter_source_type'] ?? '' ),
            'search'      => WCPI_Security::text( $_POST['filter_search'] ?? '' ),
            'from'        => WCPI_Security::text( $_POST['filter_from'] ?? '' ),
            'to'          => WCPI_Security::text( $_POST['filter_to'] ?? '' ),
            'paged'       => max( 1, absint( $_POST['filter_paged'] ?? 1 ) ),
            'per_page'    => 20,
        );
        $default_range = WCPI_Helpers::resolve_date_range( 'this_month' );

        wp_send_json_success(
            array(
                'message' => __( 'Expense saved.', WCPI_TEXT_DOMAIN ),
                'expense' => $saved,
                'summary' => WCPI_Expense_Manager::summary( $filters['from'] ?: $default_range['from'], $filters['to'] ?: $default_range['to'] ),
                'query'   => WCPI_Expense_Manager::query( $filters ),
            )
        );
    }

    /**
     * Save budget via AJAX.
     *
     * @return void
     */
    public function save_expense_budget(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $category = sanitize_key( $_POST['category'] ?? 'miscellaneous' );
        $amount   = WCPI_Security::decimal( $_POST['budget_amount'] ?? 0 );
        WCPI_Recurring_Expense_Manager::upsert_budget( $category, $amount );

        $range = WCPI_Helpers::resolve_date_range( 'this_month' );

        wp_send_json_success(
            array(
                'message' => __( 'Budget saved.', WCPI_TEXT_DOMAIN ),
                'summary' => WCPI_Expense_Manager::summary( $range['from'], $range['to'] ),
            )
        );
    }

    /**
     * Save dashboard note via AJAX.
     *
     * @return void
     */
    public function save_dashboard_note(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $note = WCPI_Recurring_Expense_Manager::create_note( $_POST );

        wp_send_json_success(
            array(
                'message' => __( 'Note saved.', WCPI_TEXT_DOMAIN ),
                'note'    => $note,
                'notes'   => WCPI_Recurring_Expense_Manager::notes( 10 ),
                'active_notes' => WCPI_Recurring_Expense_Manager::active_notes( 10 ),
            )
        );
    }

    /**
     * Hide a dashboard note from active surfaces.
     *
     * @return void
     */
    public function hide_dashboard_note(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $note = WCPI_Recurring_Expense_Manager::hide_note( absint( $_POST['note_id'] ?? 0 ) );
        if ( is_wp_error( $note ) ) {
            wp_send_json_error( array( 'message' => $note->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'message'      => __( 'Note removed from active dashboard surfaces.', WCPI_TEXT_DOMAIN ),
                'note'         => $note,
                'notes'        => WCPI_Recurring_Expense_Manager::notes( 10 ),
                'active_notes' => WCPI_Recurring_Expense_Manager::active_notes( 10 ),
            )
        );
    }

    /**
     * End a dashboard note immediately.
     *
     * @return void
     */
    public function end_dashboard_note(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $note = WCPI_Recurring_Expense_Manager::end_note( absint( $_POST['note_id'] ?? 0 ) );
        if ( is_wp_error( $note ) ) {
            wp_send_json_error( array( 'message' => $note->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'message'      => __( 'Note ended immediately.', WCPI_TEXT_DOMAIN ),
                'note'         => $note,
                'notes'        => WCPI_Recurring_Expense_Manager::notes( 10 ),
                'active_notes' => WCPI_Recurring_Expense_Manager::active_notes( 10 ),
            )
        );
    }

    /**
     * Save visible product costs via AJAX.
     *
     * @return void
     */
    public function save_product_costs(): void {
        WCPI_Security::verify_access();
        check_ajax_referer( 'wcpi_admin_nonce', 'nonce' );

        $updated = WCPI_Admin::save_product_cost_rows(
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

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: %d: updated product rows. */
                    __( 'Saved %d product cost rows.', WCPI_TEXT_DOMAIN ),
                    $updated
                ),
                'audit'   => WCPI_Report_Query::missing_cost_audit(),
            )
        );
    }
}
