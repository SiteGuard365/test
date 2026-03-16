<?php
/**
 * Expense manager.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Expense_Manager {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_post_wcpi_save_expense', array( $this, 'handle_save' ) );
        add_action( 'admin_post_wcpi_delete_expense', array( $this, 'handle_delete' ) );
    }

    /**
     * Save expense.
     *
     * @return void
     */
    public function handle_save(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_expense' );
        self::save_expense( $_POST, $_FILES );
        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&updated=1' ) );
        exit;
    }

    /**
     * Delete expense.
     *
     * @return void
     */
    public function handle_delete(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_delete_expense' );
        global $wpdb;
        $table = WCPI_DB::table( 'expenses' );
        $id    = absint( $_GET['expense_id'] ?? 0 );

        $expense_date = $wpdb->get_var( $wpdb->prepare( "SELECT expense_date FROM {$table} WHERE id = %d", $id ) );
        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( $expense_date ) {
            WCPI_Daily_Summary::rebuild_range( $expense_date, $expense_date );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&deleted=1' ) );
        exit;
    }

    /**
     * Query expenses.
     *
     * @param array<string, mixed> $args Filters.
     * @return array<string, mixed>
     */
    public static function query( array $args = array() ): array {
        WCPI_Recurring_Expense_Manager::sync_generated_entries();

        global $wpdb;
        $table    = WCPI_DB::table( 'expenses' );
        $page     = max( 1, absint( $args['paged'] ?? 1 ) );
        $per_page = max( 1, absint( $args['per_page'] ?? 20 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $where    = array( '1=1' );
        $values   = array();

        if ( ! empty( $args['category'] ) ) {
            $where[]  = 'category = %s';
            $values[] = sanitize_key( $args['category'] );
        }
        if ( ! empty( $args['source_type'] ) ) {
            $where[]  = 'source_type = %s';
            $values[] = sanitize_key( $args['source_type'] );
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(expense_name LIKE %s OR notes LIKE %s)';
            $term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $term;
            $values[] = $term;
        }
        if ( ! empty( $args['from'] ) ) {
            $where[]  = 'expense_date >= %s';
            $values[] = $args['from'];
        }
        if ( ! empty( $args['to'] ) ) {
            $where[]  = 'expense_date <= %s';
            $values[] = $args['to'];
        }

        $sql_where  = implode( ' AND ', $where );
        $rows_sql   = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY expense_date DESC, id DESC LIMIT %d OFFSET %d";
        $count_sql  = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
        $sum_sql    = "SELECT category, SUM(amount) AS total FROM {$table} WHERE {$sql_where} GROUP BY category ORDER BY total DESC";
        $source_sql = "SELECT source_type, SUM(amount) AS total FROM {$table} WHERE {$sql_where} GROUP BY source_type";

        $rows_params = array_merge( $values, array( $per_page, $offset ) );
        $items       = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$rows_params ), ARRAY_A );

        if ( ! empty( $values ) ) {
            $total_items  = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) );
            $category_sum = $wpdb->get_results( $wpdb->prepare( $sum_sql, ...$values ), ARRAY_A );
            $source_sum   = $wpdb->get_results( $wpdb->prepare( $source_sql, ...$values ), ARRAY_A );
        } else {
            $total_items  = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            $category_sum = $wpdb->get_results( $sum_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            $source_sum   = $wpdb->get_results( $source_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        }

        foreach ( $items as &$item ) {
            $item['category_label'] = WCPI_Helpers::expense_categories()[ $item['category'] ] ?? $item['category'];
            $item['source_label']   = WCPI_Helpers::expense_source_label( $item['source_type'] );
        }

        return array(
            'items'        => $items,
            'total_items'  => $total_items,
            'total_pages'  => (int) ceil( $total_items / $per_page ),
            'category_sum' => $category_sum,
            'source_sum'   => $source_sum,
        );
    }

    /**
     * Get expense by id.
     *
     * @param int $id Expense id.
     * @return array<string, mixed>|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WCPI_DB::table( 'expenses' ) . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Page summary metrics.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<string, mixed>
     */
    public static function summary( string $from, string $to ): array {
        $query          = self::query(
            array(
                'from'     => $from,
                'to'       => $to,
                'paged'    => 1,
                'per_page' => 200,
            )
        );
        $breakdown      = WCPI_Report_Query::expense_breakdown( $from, $to );
        $largest        = $breakdown[0] ?? array( 'category' => 'miscellaneous', 'total' => 0 );
        $latest         = $query['items'][0] ?? null;
        $recurring_total= 0.0;

        foreach ( $query['source_sum'] as $source_row ) {
            if ( 'recurring' === $source_row['source_type'] ) {
                $recurring_total = (float) $source_row['total'];
            }
        }

        return array(
            'this_period_total' => array_sum( array_map( static fn( $row ) => (float) $row['total'], $query['category_sum'] ) ),
            'largest_category'  => $largest,
            'largest_category_label' => WCPI_Helpers::expense_categories()[ $largest['category'] ] ?? ucfirst( $largest['category'] ),
            'latest'            => $latest,
            'recurring_total'   => $recurring_total,
            'entries'           => (int) $query['total_items'],
            'breakdown'         => $breakdown,
            'budget_vs_actual'  => WCPI_Report_Query::expense_budget_vs_actual( $from, $to ),
        );
    }

    /**
     * Save or update an expense row.
     *
     * @param array<string, mixed> $request Request payload.
     * @param array<string, mixed> $files Uploaded files.
     * @return array<string, mixed>|WP_Error
     */
    public static function save_expense( array $request, array $files = array() ) {
        global $wpdb;
        $table = WCPI_DB::table( 'expenses' );
        $id    = absint( $request['expense_id'] ?? 0 );

        $attachment_path = WCPI_Helpers::get( $request, 'existing_attachment', '' );
        if ( ! empty( $files['attachment']['name'] ) ) {
            $upload = self::handle_receipt_upload_from_request( $files );
            if ( is_wp_error( $upload ) ) {
                return $upload;
            }
            $attachment_path = $upload['file'];
        }

        $data = array(
            'expense_name'    => WCPI_Security::text( $request['expense_name'] ?? '' ),
            'category'        => sanitize_key( $request['category'] ?? 'miscellaneous' ),
            'amount'          => WCPI_Security::decimal( $request['amount'] ?? 0 ),
            'expense_date'    => WCPI_Security::date( $request['expense_date'] ?? gmdate( 'Y-m-d' ) ),
            'source_type'     => 'manual',
            'source_ref_id'   => 0,
            'notes'           => WCPI_Security::textarea( $request['notes'] ?? '' ),
            'attachment_path' => sanitize_text_field( $attachment_path ),
            'created_by'      => get_current_user_id(),
            'updated_at'      => current_time( 'mysql', true ),
        );

        if ( ! array_key_exists( $data['category'], WCPI_Helpers::expense_categories() ) ) {
            $data['category'] = 'miscellaneous';
        }

        if ( $id > 0 ) {
            $wpdb->update(
                $table,
                $data,
                array( 'id' => $id ),
                array( '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $data['created_at'] = current_time( 'mysql', true );
            $wpdb->insert(
                $table,
                $data,
                array( '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
            );
            $id = (int) $wpdb->insert_id;
        }

        WCPI_Daily_Summary::rebuild_range( $data['expense_date'], $data['expense_date'] );
        WCPI_Aggregates::purge();
        WCPI_Cache::flush_group();

        return self::get( $id ) ?: array_merge( $data, array( 'id' => $id ) );
    }

    /**
     * Handle receipt upload.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function handle_receipt_upload() {
        return self::handle_receipt_upload_from_request( $_FILES );
    }

    /**
     * Handle receipt upload from a request payload.
     *
     * @param array<string, mixed> $files Uploaded file bag.
     * @return array<string, mixed>|WP_Error
     */
    private static function handle_receipt_upload_from_request( array $files ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WCPI_Filesystem::ensure_base_structure();

        $overrides = array(
            'test_form' => false,
            'mimes'     => array(
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'pdf'  => 'application/pdf',
            ),
            'unique_filename_callback' => static function ( $dir, $name, $ext ) {
                return WCPI_Filesystem::secure_filename( 'receipt', $ext );
            },
        );

        return wp_handle_upload( $files['attachment'], $overrides );
    }
}
