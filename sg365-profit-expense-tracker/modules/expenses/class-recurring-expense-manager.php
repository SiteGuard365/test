<?php
/**
 * Recurring expense manager.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Recurring_Expense_Manager {

    /**
     * Register actions.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_post_wcpi_save_recurring_expense', array( $this, 'handle_save' ) );
        add_action( 'admin_post_wcpi_toggle_recurring_expense', array( $this, 'handle_toggle' ) );
        add_action( 'admin_post_wcpi_delete_recurring_expense', array( $this, 'handle_delete' ) );
        add_action( 'admin_post_wcpi_save_expense_budget', array( $this, 'save_budget' ) );
        add_action( 'admin_post_wcpi_save_dashboard_note', array( $this, 'save_note' ) );
    }

    /**
     * Save template.
     *
     * @return void
     */
    public function handle_save(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_recurring_expense' );

        global $wpdb;
        $table = WCPI_DB::table( 'recurring_expenses' );
        $id    = absint( $_POST['template_id'] ?? 0 );
        $current = $id > 0 ? self::get( $id ) : null;

        $data = array(
            'name'               => WCPI_Security::text( $_POST['name'] ?? '' ),
            'category'           => sanitize_key( $_POST['category'] ?? 'miscellaneous' ),
            'amount'             => WCPI_Security::decimal( $_POST['amount'] ?? 0 ),
            'frequency'          => WCPI_Security::text( $_POST['frequency'] ?? 'monthly' ),
            'start_date'         => WCPI_Security::date( $_POST['start_date'] ?? gmdate( 'Y-m-d' ) ),
            'end_date'           => ! empty( $_POST['end_date'] ) ? WCPI_Security::date( $_POST['end_date'] ) : null,
            'notes'              => WCPI_Security::textarea( $_POST['notes'] ?? '' ),
            'status'             => 'inactive' === WCPI_Security::text( $_POST['status'] ?? 'active' ) ? 'inactive' : 'active',
            'created_by'         => (int) ( $current['created_by'] ?? get_current_user_id() ),
            'updated_at'         => current_time( 'mysql', true ),
        );

        if ( ! in_array( $data['frequency'], self::allowed_frequencies(), true ) ) {
            $data['frequency'] = 'monthly';
        }

        if ( ! array_key_exists( $data['category'], WCPI_Helpers::expense_categories() ) ) {
            $data['category'] = 'miscellaneous';
        }

        if ( ! empty( $data['end_date'] ) && $data['end_date'] < $data['start_date'] ) {
            $data['end_date'] = $data['start_date'];
        }

        if ( $id > 0 ) {
            $wpdb->update(
                $table,
                $data,
                array( 'id' => $id ),
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $data['created_at'] = current_time( 'mysql', true );
            $wpdb->insert(
                $table,
                $data,
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
            );
            $id = (int) $wpdb->insert_id;
        }

        if ( 'active' === $data['status'] ) {
            self::generate_entries_for_template( $id );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&recurring_updated=1' ) );
        exit;
    }

    /**
     * Toggle active state.
     *
     * @return void
     */
    public function handle_toggle(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_toggle_recurring_expense' );

        global $wpdb;
        $id      = absint( $_GET['template_id'] ?? 0 );
        $table   = WCPI_DB::table( 'recurring_expenses' );
        $current = self::get( $id );

        if ( $current ) {
            $next_status = 'active' === $current['status'] ? 'inactive' : 'active';
            $wpdb->update(
                $table,
                array(
                    'status'     => $next_status,
                    'updated_at' => current_time( 'mysql', true ),
                ),
                array( 'id' => $id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( 'active' === $next_status ) {
                self::generate_entries_for_template( $id );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&recurring_updated=1' ) );
        exit;
    }

    /**
     * Delete template and preserve existing generated rows.
     *
     * @return void
     */
    public function handle_delete(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_delete_recurring_expense' );

        global $wpdb;
        $wpdb->delete(
            WCPI_DB::table( 'recurring_expenses' ),
            array( 'id' => absint( $_GET['template_id'] ?? 0 ) ),
            array( '%d' )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&recurring_deleted=1' ) );
        exit;
    }

    /**
     * Save category budget.
     *
     * @return void
     */
    public function save_budget(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_expense_budget' );
        self::upsert_budget(
            sanitize_key( $_POST['category'] ?? 'miscellaneous' ),
            WCPI_Security::decimal( $_POST['budget_amount'] ?? 0 )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-expenses&budget_updated=1' ) );
        exit;
    }

    /**
     * Save dashboard note.
     *
     * @return void
     */
    public function save_note(): void {
        WCPI_Security::verify_access();
        WCPI_Security::verify_nonce( 'wcpi_save_dashboard_note' );
        self::create_note( $_POST );

        wp_safe_redirect( admin_url( 'admin.php?page=wcpi-dashboard&note_saved=1' ) );
        exit;
    }

    /**
     * Sync all active templates up to today.
     *
     * @return void
     */
    public static function sync_generated_entries(): void {
        if ( 'yes' !== WCPI_Settings_Manager::get( 'general', 'recurring_expenses_enabled', 'yes' ) ) {
            return;
        }

        global $wpdb;
        $templates = $wpdb->get_results(
            "SELECT id FROM " . WCPI_DB::table( 'recurring_expenses' ) . " WHERE status = 'active'",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        foreach ( $templates as $template ) {
            self::generate_entries_for_template( (int) $template['id'] );
        }
    }

    /**
     * Generate instances for a template.
     *
     * @param int $template_id Template id.
     * @return void
     */
    public static function generate_entries_for_template( int $template_id ): void {
        if ( 'yes' !== WCPI_Settings_Manager::get( 'general', 'recurring_expenses_enabled', 'yes' ) ) {
            return;
        }

        $template = self::get( $template_id );
        if ( empty( $template ) || 'active' !== $template['status'] ) {
            return;
        }

        global $wpdb;
        $expenses_table = WCPI_DB::table( 'expenses' );
        $start          = new DateTimeImmutable( $template['start_date'], wp_timezone() );
        $limit_date     = ! empty( $template['end_date'] ) ? new DateTimeImmutable( $template['end_date'], wp_timezone() ) : new DateTimeImmutable( 'today', wp_timezone() );
        $today          = new DateTimeImmutable( 'today', wp_timezone() );

        if ( $limit_date > $today ) {
            $limit_date = $today;
        }

        $inserted_dates = array();

        for ( $cursor = $start; $cursor->getTimestamp() <= $limit_date->getTimestamp(); $cursor = self::next_date( $cursor, $template['frequency'] ) ) {
            $date = $cursor->format( 'Y-m-d' );
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$expenses_table} WHERE source_type = 'recurring' AND source_ref_id = %d AND expense_date = %s LIMIT 1",
                    $template_id,
                    $date
                )
            );

            if ( $exists ) {
                continue;
            }

            $wpdb->insert(
                $expenses_table,
                array(
                    'expense_name'    => $template['name'],
                    'category'        => $template['category'],
                    'amount'          => $template['amount'],
                    'expense_date'    => $date,
                    'source_type'     => 'recurring',
                    'source_ref_id'   => $template_id,
                    'notes'           => $template['notes'],
                    'attachment_path' => '',
                    'created_by'      => (int) $template['created_by'],
                    'created_at'      => current_time( 'mysql', true ),
                    'updated_at'      => current_time( 'mysql', true ),
                ),
                array( '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
            );
            $inserted_dates[] = $date;
        }

        $wpdb->update(
            WCPI_DB::table( 'recurring_expenses' ),
            array(
                'last_generated_on' => $limit_date->format( 'Y-m-d' ),
                'updated_at'        => current_time( 'mysql', true ),
            ),
            array( 'id' => $template_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( ! empty( $inserted_dates ) ) {
            foreach ( array_unique( $inserted_dates ) as $date ) {
                WCPI_Daily_Summary::rebuild_date( $date );
            }
            WCPI_Aggregates::purge();
            WCPI_Cache::flush_group();
        }
    }

    /**
     * Get templates.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . WCPI_DB::table( 'recurring_expenses' ) . ' ORDER BY updated_at DESC',
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Save or replace a monthly budget.
     *
     * @param string $category Category key.
     * @param float  $amount Budget amount.
     * @return void
     */
    public static function upsert_budget( string $category, float $amount ): void {
        global $wpdb;
        $table = WCPI_DB::table( 'expense_budgets' );

        if ( ! array_key_exists( $category, WCPI_Helpers::expense_categories() ) ) {
            $category = 'miscellaneous';
        }

        $wpdb->replace(
            $table,
            array(
                'category'      => $category,
                'period_key'    => 'monthly',
                'budget_amount' => $amount,
                'created_at'    => current_time( 'mysql', true ),
                'updated_at'    => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%f', '%s', '%s' )
        );

        WCPI_Aggregates::purge();
        WCPI_Cache::flush_group();
    }

    /**
     * Create a dashboard note.
     *
     * @param array<string, mixed> $request Note payload.
     * @return array<string, mixed>
     */
    public static function create_note( array $request ): array {
        global $wpdb;
        $table      = WCPI_DB::table( 'dashboard_notes' );
        $categories = WCPI_Helpers::dashboard_note_categories();
        $colors     = WCPI_Helpers::dashboard_note_colors();
        $date_from  = ! empty( $request['date_from'] ) ? WCPI_Security::date( $request['date_from'] ) : wp_date( 'Y-m-d' );
        $date_to    = ! empty( $request['date_to'] ) ? WCPI_Security::date( $request['date_to'] ) : null;
        $category   = sanitize_key( $request['note_category'] ?? 'update' );
        $color      = sanitize_key( $request['note_color'] ?? 'blue' );

        if ( ! isset( $categories[ $category ] ) ) {
            $category = 'update';
        }

        if ( ! isset( $colors[ $color ] ) ) {
            $color = 'blue';
        }

        if ( ! empty( $date_to ) && $date_to < $date_from ) {
            $date_to = $date_from;
        }

        $email_enabled = 'yes' === WCPI_Security::text( $request['email_enabled'] ?? 'no' ) ? 1 : 0;
        $current_user  = wp_get_current_user();
        $default_email = $current_user instanceof WP_User && ! empty( $current_user->user_email ) ? $current_user->user_email : get_option( 'admin_email', '' );
        $email_to      = $email_enabled ? sanitize_email( $request['email_recipient'] ?? $default_email ) : '';

        $data = array(
            'note_title'      => WCPI_Security::text( $request['note_title'] ?? '' ),
            'note_body'       => WCPI_Security::textarea( $request['note_body'] ?? '' ),
            'note_tag'        => WCPI_Security::text( $request['note_tag'] ?? '' ),
            'note_category'   => $category,
            'note_color'      => $color,
            'note_status'     => 'active',
            'date_from'       => $date_from,
            'date_to'         => $date_to,
            'email_enabled'   => $email_enabled,
            'email_recipient' => $email_to,
            'hidden_at'       => null,
            'hidden_by'       => 0,
            'ended_at'        => null,
            'ended_by'        => 0,
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time( 'mysql', true ),
            'updated_at'      => current_time( 'mysql', true ),
        );

        $wpdb->insert(
            $table,
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        $data['id'] = (int) $wpdb->insert_id;
        $data       = self::normalize_note_row( $data );
        $data['email_sent'] = self::maybe_send_note_email( $data );

        WCPI_Cache::flush_group();

        return $data;
    }

    /**
     * Get dashboard note by id.
     *
     * @param int $id Note id.
     * @return array<string, mixed>|null
     */
    public static function get_note( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WCPI_DB::table( 'dashboard_notes' ) . ' WHERE id = %d', $id ),
            ARRAY_A
        );

        if ( empty( $row ) ) {
            return null;
        }

        return self::normalize_note_row( $row );
    }

    /**
     * Hide a note from active dashboard surfaces without deleting history.
     *
     * @param int $id Note id.
     * @return array<string, mixed>|WP_Error
     */
    public static function hide_note( int $id ) {
        return self::update_note_status( $id, 'hidden' );
    }

    /**
     * End a note immediately even if its expiry date is later.
     *
     * @param int $id Note id.
     * @return array<string, mixed>|WP_Error
     */
    public static function end_note( int $id ) {
        return self::update_note_status( $id, 'ended' );
    }

    /**
     * Get template by id.
     *
     * @param int $id Template id.
     * @return array<string, mixed>|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WCPI_DB::table( 'recurring_expenses' ) . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Get budget map.
     *
     * @return array<string, float>
     */
    public static function budgets(): array {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT category, budget_amount FROM ' . WCPI_DB::table( 'expense_budgets' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $budgets = array();
        foreach ( $rows as $row ) {
            $budgets[ $row['category'] ] = (float) $row['budget_amount'];
        }
        return $budgets;
    }

    /**
     * Recent notes.
     *
     * @param int         $limit Limit.
     * @param string|null $from Range start.
     * @param string|null $to Range end.
     * @return array<int, array<string, mixed>>
     */
    public static function notes( int $limit = 5, ?string $from = null, ?string $to = null ): array {
        global $wpdb;
        $table = WCPI_DB::table( 'dashboard_notes' );

        if ( ! empty( $from ) && ! empty( $to ) ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE
                        ( date_from IS NULL OR date_from <= %s )
                        AND ( date_to IS NULL OR date_to >= %s )
                    ORDER BY COALESCE(date_from, DATE(created_at)) DESC, updated_at DESC
                    LIMIT %d",
                    $to,
                    $from,
                    $limit
                ),
                ARRAY_A
            );

            return array_map( array( __CLASS__, 'normalize_note_row' ), $rows );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY COALESCE(date_from, DATE(created_at)) DESC, updated_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return array_map( array( __CLASS__, 'normalize_note_row' ), $rows );
    }

    /**
     * Notes effective on a specific day.
     *
     * @param int         $limit Limit.
     * @param string|null $date  Reference date.
     * @return array<int, array<string, mixed>>
     */
    public static function active_notes( int $limit = 5, ?string $date = null ): array {
        global $wpdb;
        $table          = WCPI_DB::table( 'dashboard_notes' );
        $reference_date = $date ? WCPI_Security::date( $date ) : wp_date( 'Y-m-d' );
        $rows           = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE
                    note_status = 'active'
                    AND
                    ( date_from IS NULL OR date_from <= %s )
                    AND ( date_to IS NULL OR date_to >= %s )
                ORDER BY COALESCE(date_from, DATE(created_at)) DESC, updated_at DESC
                LIMIT %d",
                $reference_date,
                $reference_date,
                $limit
            ),
            ARRAY_A
        );

        return array_map( array( __CLASS__, 'normalize_note_row' ), $rows );
    }

    /**
     * Add computed labels and rule flags to a note row.
     *
     * @param array<string, mixed> $row Note row.
     * @return array<string, mixed>
     */
    private static function normalize_note_row( array $row ): array {
        $categories = WCPI_Helpers::dashboard_note_categories();
        $colors     = WCPI_Helpers::dashboard_note_colors();
        $today      = wp_date( 'Y-m-d' );
        $date_from  = ! empty( $row['date_from'] ) ? (string) $row['date_from'] : '';
        $date_to    = ! empty( $row['date_to'] ) ? (string) $row['date_to'] : '';
        $category   = sanitize_key( $row['note_category'] ?? 'update' );
        $color      = sanitize_key( $row['note_color'] ?? 'blue' );
        $status     = sanitize_key( $row['note_status'] ?? 'active' );
        $statuses   = self::note_status_labels();

        if ( ! isset( $categories[ $category ] ) ) {
            $category = 'update';
        }

        if ( ! isset( $colors[ $color ] ) ) {
            $color = 'blue';
        }

        if ( ! isset( $statuses[ $status ] ) ) {
            $status = 'active';
        }

        $row['note_category']    = $category;
        $row['note_color']       = $color;
        $row['note_status']      = $status;
        $row['status_label']     = $statuses[ $status ];
        $row['status_tone']      = 'ended' === $status ? 'warning' : ( 'hidden' === $status ? 'neutral' : 'success' );
        $row['category_label']   = $categories[ $category ];
        $row['color_label']      = $colors[ $color ];
        $row['email_enabled']    = ! empty( $row['email_enabled'] ) ? 1 : 0;
        $row['email_recipient']  = sanitize_email( (string) ( $row['email_recipient'] ?? '' ) );
        $row['is_active']        = 'active' === $status && ( empty( $date_from ) || $date_from <= $today ) && ( empty( $date_to ) || $date_to >= $today );
        $row['date_range_label'] = ! empty( $date_from ) ? WCPI_Helpers::date_label( $date_from ) . ( ! empty( $date_to ) ? ' - ' . WCPI_Helpers::date_label( $date_to ) : '' ) : __( 'Effective immediately', WCPI_TEXT_DOMAIN );

        return $row;
    }

    /**
     * Change dashboard note status.
     *
     * @param int    $id     Note id.
     * @param string $status Target status.
     * @return array<string, mixed>|WP_Error
     */
    private static function update_note_status( int $id, string $status ) {
        global $wpdb;

        $allowed = self::note_status_labels();
        if ( ! isset( $allowed[ $status ] ) || 'active' === $status ) {
            return new WP_Error( 'wcpi_invalid_note_status', __( 'Invalid note action.', WCPI_TEXT_DOMAIN ) );
        }

        $current = self::get_note( $id );
        if ( empty( $current ) ) {
            return new WP_Error( 'wcpi_note_not_found', __( 'The selected note could not be found.', WCPI_TEXT_DOMAIN ) );
        }

        if ( $status === $current['note_status'] ) {
            return $current;
        }

        $payload = array(
            'note_status' => $status,
            'updated_at'  => current_time( 'mysql', true ),
        );
        $formats = array( '%s', '%s' );

        if ( 'hidden' === $status ) {
            $payload['hidden_at'] = current_time( 'mysql', true );
            $payload['hidden_by'] = get_current_user_id();
            $formats[]            = '%s';
            $formats[]            = '%d';
        }

        if ( 'ended' === $status ) {
            $today               = wp_date( 'Y-m-d' );
            $payload['ended_at'] = current_time( 'mysql', true );
            $payload['ended_by'] = get_current_user_id();
            $payload['date_to']  = empty( $current['date_to'] ) || (string) $current['date_to'] > $today ? $today : (string) $current['date_to'];
            $formats[]           = '%s';
            $formats[]           = '%d';
            $formats[]           = '%s';
        }

        $wpdb->update(
            WCPI_DB::table( 'dashboard_notes' ),
            $payload,
            array( 'id' => $id ),
            $formats,
            array( '%d' )
        );

        WCPI_Cache::flush_group();

        return self::get_note( $id ) ?: new WP_Error( 'wcpi_note_not_found', __( 'The selected note could not be found.', WCPI_TEXT_DOMAIN ) );
    }

    /**
     * Note status labels.
     *
     * @return array<string, string>
     */
    private static function note_status_labels(): array {
        return array(
            'active' => __( 'Active', WCPI_TEXT_DOMAIN ),
            'hidden' => __( 'Removed', WCPI_TEXT_DOMAIN ),
            'ended'  => __( 'Ended', WCPI_TEXT_DOMAIN ),
        );
    }

    /**
     * Send an optional note email when the note becomes active immediately.
     *
     * @param array<string, mixed> $note Note payload.
     * @return bool
     */
    private static function maybe_send_note_email( array $note ): bool {
        if ( empty( $note['email_enabled'] ) || empty( $note['email_recipient'] ) || empty( $note['is_active'] ) ) {
            return false;
        }

        $subject = sprintf(
            /* translators: %s: note title */
            __( 'Dashboard note: %s', WCPI_TEXT_DOMAIN ),
            $note['note_title']
        );

        $message = implode(
            "\n\n",
            array_filter(
                array(
                    $note['note_title'],
                    ! empty( $note['category_label'] ) ? sprintf(
                        /* translators: %s: category label */
                        __( 'Category: %s', WCPI_TEXT_DOMAIN ),
                        $note['category_label']
                    ) : '',
                    ! empty( $note['date_range_label'] ) ? sprintf(
                        /* translators: %s: date range */
                        __( 'Effective: %s', WCPI_TEXT_DOMAIN ),
                        $note['date_range_label']
                    ) : '',
                    (string) ( $note['note_body'] ?? '' ),
                    admin_url( 'admin.php?page=wcpi-dashboard&tab=planning' ),
                )
            )
        );

        return (bool) wp_mail( $note['email_recipient'], $subject, $message );
    }

    /**
     * Get next occurrence date.
     *
     * @param DateTimeImmutable $date Current date.
     * @param string            $frequency Frequency key.
     * @return DateTimeImmutable
     */
    private static function next_date( DateTimeImmutable $date, string $frequency ): DateTimeImmutable {
        switch ( $frequency ) {
            case 'daily':
                return $date->modify( '+1 day' );
            case 'weekly':
                return $date->modify( '+1 week' );
            case 'quarterly':
                return $date->modify( '+3 months' );
            case 'yearly':
                return $date->modify( '+1 year' );
            case 'monthly':
            default:
                return $date->modify( '+1 month' );
        }
    }

    /**
     * Allowed recurring cadence keys.
     *
     * @return array<int, string>
     */
    public static function allowed_frequencies(): array {
        return array( 'daily', 'weekly', 'monthly', 'quarterly', 'yearly' );
    }
}
