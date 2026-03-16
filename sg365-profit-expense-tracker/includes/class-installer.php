<?php
/**
 * Installation and schema.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Installer {

    /**
     * Run installer.
     *
     * @return void
     */
    public static function install(): void {
        self::create_tables();
        WCPI_Filesystem::ensure_base_structure();
        WCPI_Settings_Manager::seed_defaults();
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    public static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $tables  = WCPI_DB::tables();

        $sql = array();

        $sql[] = "CREATE TABLE {$tables['product_costs']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            cost_price DECIMAL(19,6) NOT NULL DEFAULT 0,
            packaging_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            handling_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            extra_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            total_unit_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT '',
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY updated_at (updated_at),
            UNIQUE KEY product_variation_unique (product_id, variation_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['cost_history']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            old_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            new_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
            change_reason VARCHAR(255) NOT NULL DEFAULT '',
            changed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            effective_date DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY effective_date (effective_date)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['expenses']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_name VARCHAR(190) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'miscellaneous',
            amount DECIMAL(19,6) NOT NULL DEFAULT 0,
            expense_date DATE NOT NULL,
            source_type VARCHAR(30) NOT NULL DEFAULT 'manual',
            source_ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes LONGTEXT NULL,
            attachment_path TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY expense_date (expense_date),
            KEY source_type (source_type),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['recurring_expenses']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'miscellaneous',
            amount DECIMAL(19,6) NOT NULL DEFAULT 0,
            frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            notes LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_generated_on DATE NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY frequency (frequency),
            KEY start_date (start_date),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['expense_budgets']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category VARCHAR(60) NOT NULL,
            period_key VARCHAR(20) NOT NULL DEFAULT 'monthly',
            budget_amount DECIMAL(19,6) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY category_period (category, period_key)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['dashboard_notes']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            note_title VARCHAR(190) NOT NULL,
            note_body LONGTEXT NOT NULL,
            note_tag VARCHAR(80) NULL,
            note_category VARCHAR(40) NOT NULL DEFAULT 'update',
            note_color VARCHAR(30) NOT NULL DEFAULT 'blue',
            note_status VARCHAR(20) NOT NULL DEFAULT 'active',
            date_from DATE NULL,
            date_to DATE NULL,
            email_enabled TINYINT(1) NOT NULL DEFAULT 0,
            email_recipient VARCHAR(190) NULL,
            hidden_at DATETIME NULL,
            hidden_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ended_at DATETIME NULL,
            ended_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY note_tag (note_tag),
            KEY note_category (note_category),
            KEY note_status (note_status),
            KEY date_from (date_from),
            KEY date_to (date_to),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['daily_summary']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            summary_date DATE NOT NULL,
            orders_count INT UNSIGNED NOT NULL DEFAULT 0,
            items_sold INT UNSIGNED NOT NULL DEFAULT 0,
            gross_revenue DECIMAL(19,6) NOT NULL DEFAULT 0,
            discounts_total DECIMAL(19,6) NOT NULL DEFAULT 0,
            refunds_total DECIMAL(19,6) NOT NULL DEFAULT 0,
            shipping_collected DECIMAL(19,6) NOT NULL DEFAULT 0,
            tax_collected DECIMAL(19,6) NOT NULL DEFAULT 0,
            product_cost_total DECIMAL(19,6) NOT NULL DEFAULT 0,
            expenses_total DECIMAL(19,6) NOT NULL DEFAULT 0,
            net_profit DECIMAL(19,6) NOT NULL DEFAULT 0,
            margin_percent DECIMAL(9,4) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY summary_date (summary_date),
            KEY updated_at (updated_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['analytics_aggregates']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            aggregate_key VARCHAR(190) NOT NULL,
            aggregate_type VARCHAR(60) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            payload_json LONGTEXT NULL,
            last_generated_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY aggregate_key (aggregate_key),
            KEY aggregate_type (aggregate_type),
            KEY date_from (date_from),
            KEY date_to (date_to),
            KEY expires_at (expires_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['settings_meta']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_group VARCHAR(80) NOT NULL,
            setting_key VARCHAR(120) NOT NULL,
            setting_value LONGTEXT NULL,
            autoload_flag TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY setting_group (setting_group),
            UNIQUE KEY setting_key (setting_key)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['logs']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            log_level VARCHAR(20) NOT NULL,
            context VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY log_level (log_level),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$tables['export_history']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            export_type VARCHAR(20) NOT NULL,
            report_key VARCHAR(60) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_url TEXT NULL,
            file_path TEXT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY export_type (export_type),
            KEY report_key (report_key),
            KEY created_at (created_at)
        ) $charset;";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( 'wcpi_db_version', WCPI_VERSION, false );
    }
}
