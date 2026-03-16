<?php
defined( 'ABSPATH' ) || exit;

$allowed_tabs = array( 'general', 'calculations', 'files-backups', 'goals-reports', 'cleanup' );
$active_tab   = WCPI_Security::text( $_GET['tab'] ?? 'general' );
$active_tab   = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'general';
?>
<div class="wrap wcpi-shell" id="wcpi-settings-page">
    <div class="wcpi-page-head">
        <div>
            <h1><?php esc_html_e( 'Settings', WCPI_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'Large-company style settings with grouped controls, better toggles, and clearer file, goal, and cleanup management.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcpi-settings-form">
        <input type="hidden" name="action" value="wcpi_save_settings">
        <input type="hidden" name="return_tab" id="wcpi-settings-return-tab" value="<?php echo esc_attr( $active_tab ); ?>">
        <?php WCPI_Security::nonce_field( 'wcpi_save_settings' ); ?>

        <section class="wcpi-tabs-shell wcpi-tabs-shell-soft" id="wcpi-settings-tabs" data-auto-tabs="yes" data-query-key="tab" data-default-tab="general" data-active-tab="<?php echo esc_attr( $active_tab ); ?>" data-sync-input="#wcpi-settings-return-tab">
            <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', WCPI_TEXT_DOMAIN ); ?>">
                <button type="button" class="wcpi-tab-button" data-tab-target="general" role="tab"><span class="dashicons dashicons-admin-generic"></span><span><?php esc_html_e( 'General', WCPI_TEXT_DOMAIN ); ?></span></button>
                <button type="button" class="wcpi-tab-button" data-tab-target="calculations" role="tab"><span class="dashicons dashicons-calculator"></span><span><?php esc_html_e( 'Calculations', WCPI_TEXT_DOMAIN ); ?></span></button>
                <button type="button" class="wcpi-tab-button" data-tab-target="files-backups" role="tab"><span class="dashicons dashicons-database"></span><span><?php esc_html_e( 'Files & Backups', WCPI_TEXT_DOMAIN ); ?></span></button>
                <button type="button" class="wcpi-tab-button" data-tab-target="goals-reports" role="tab"><span class="dashicons dashicons-chart-line"></span><span><?php esc_html_e( 'Goals & Reports', WCPI_TEXT_DOMAIN ); ?></span></button>
                <button type="button" class="wcpi-tab-button" data-tab-target="cleanup" role="tab"><span class="dashicons dashicons-trash"></span><span><?php esc_html_e( 'Cleanup', WCPI_TEXT_DOMAIN ); ?></span></button>
            </div>

            <div class="wcpi-tab-panels">
                <section class="wcpi-tab-panel" data-tab-panel="general" aria-hidden="true">
                    <div class="wcpi-settings-grid">
                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'General', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Core defaults for dashboard behavior, recurring expenses, and currency display.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label><span><?php esc_html_e( 'Currency Display Preference', WCPI_TEXT_DOMAIN ); ?></span><input type="text" name="currency_display" value="<?php echo esc_attr( $settings['currency_display'] ?? get_option( 'woocommerce_currency', 'USD' ) ); ?>"></label>
                            <label><span><?php esc_html_e( 'Default Dashboard Range', WCPI_TEXT_DOMAIN ); ?></span><select name="default_dashboard_range"><?php foreach ( WCPI_Helpers::date_presets() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['default_dashboard_range'] ?? 'last_30_days', $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Compare Previous Period by Default', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="compare_previous_default" value="yes" <?php checked( $settings['compare_previous_default'] ?? 'yes', 'yes' ); ?>></label>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Enable Recurring Expenses', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="recurring_expenses_enabled" value="yes" <?php checked( $settings['recurring_expenses_enabled'] ?? 'yes', 'yes' ); ?>></label>
                        </section>

                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Logging & Cache', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Retention controls for plugin cache, diagnostics logging, and admin maintenance traces.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Enable Logs', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="enable_logs" value="yes" <?php checked( $settings['enable_logs'] ?? 'no', 'yes' ); ?>></label>
                            <label><span><?php esc_html_e( 'Log Retention Days', WCPI_TEXT_DOMAIN ); ?></span><input type="number" min="1" name="log_retention_days" value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>"></label>
                            <label><span><?php esc_html_e( 'Cache Retention Days', WCPI_TEXT_DOMAIN ); ?></span><input type="number" min="1" name="cache_retention_days" value="<?php echo esc_attr( $settings['cache_retention_days'] ?? 7 ); ?>"></label>
                        </section>
                    </div>
                </section>

                <section class="wcpi-tab-panel" data-tab-panel="calculations" aria-hidden="true">
                    <div class="wcpi-settings-grid">
                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Calculation Rules', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Decide how tax, shipping, thresholds, and missing cost warnings affect analytics output.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Include Tax in Revenue', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="include_tax" value="yes" <?php checked( $settings['include_tax'] ?? 'no', 'yes' ); ?>></label>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Include Shipping in Revenue', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="include_shipping" value="yes" <?php checked( $settings['include_shipping'] ?? 'yes', 'yes' ); ?>></label>
                            <label><span><?php esc_html_e( 'Margin Threshold (%)', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.1" name="margin_threshold" value="<?php echo esc_attr( $settings['margin_threshold'] ?? 20 ); ?>"></label>
                            <label><span><?php esc_html_e( 'Missing Cost Warning Threshold', WCPI_TEXT_DOMAIN ); ?></span><input type="number" min="1" name="missing_cost_warning_threshold" value="<?php echo esc_attr( $settings['missing_cost_warning_threshold'] ?? 1 ); ?>"></label>
                        </section>
                    </div>
                </section>

                <section class="wcpi-tab-panel" data-tab-panel="files-backups" aria-hidden="true">
                    <div class="wcpi-settings-grid">
                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Exports & Files', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Export retention, current storage footprint, and file housekeeping visibility.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label><span><?php esc_html_e( 'Export Retention Days', WCPI_TEXT_DOMAIN ); ?></span><input type="number" min="1" name="export_retention_days" value="<?php echo esc_attr( $settings['export_retention_days'] ?? 14 ); ?>"></label>
                            <div class="wcpi-budget-list">
                                <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Exports Folder', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['exports'] ) ); ?></span></div>
                                <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Logs Folder', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['logs'] ) ); ?></span></div>
                            </div>
                        </section>

                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Backups', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Retention and enablement controls for backup snapshots and safer maintenance workflows.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Enable Backups', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="backup_enabled" value="yes" <?php checked( $settings['backup_enabled'] ?? 'no', 'yes' ); ?>></label>
                            <label><span><?php esc_html_e( 'Backup Retention Limit', WCPI_TEXT_DOMAIN ); ?></span><input type="number" min="1" name="backup_retention_limit" value="<?php echo esc_attr( $settings['backup_retention_limit'] ?? 5 ); ?>"></label>
                        </section>
                    </div>
                </section>

                <section class="wcpi-tab-panel" data-tab-panel="goals-reports" aria-hidden="true">
                    <div class="wcpi-settings-grid">
                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Profit Goals', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Monthly business targets that power goal cards and progress indicators across the plugin.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label><span><?php esc_html_e( 'Monthly Revenue Goal', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.000001" name="monthly_revenue_goal" value="<?php echo esc_attr( $settings['monthly_revenue_goal'] ?? 0 ); ?>"></label>
                            <label><span><?php esc_html_e( 'Monthly Profit Goal', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.000001" name="monthly_profit_goal" value="<?php echo esc_attr( $settings['monthly_profit_goal'] ?? 0 ); ?>"></label>
                            <label><span><?php esc_html_e( 'Monthly Margin Goal (%)', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.1" name="monthly_margin_goal" value="<?php echo esc_attr( $settings['monthly_margin_goal'] ?? 0 ); ?>"></label>
                        </section>

                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Scheduled Email Reports', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Control the global scheduled email recipient, report type, and delivery cadence.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Enable Scheduled Reports', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="email_reports_enabled" value="yes" <?php checked( $settings['email_reports_enabled'] ?? 'no', 'yes' ); ?>></label>
                            <label><span><?php esc_html_e( 'Recipient Email', WCPI_TEXT_DOMAIN ); ?></span><input type="email" name="email_reports_recipient" value="<?php echo esc_attr( $settings['email_reports_recipient'] ?? get_option( 'admin_email', '' ) ); ?>"></label>
                            <label><span><?php esc_html_e( 'Report Type', WCPI_TEXT_DOMAIN ); ?></span><select name="email_reports_type"><?php foreach ( WCPI_Helpers::email_report_types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['email_reports_type'] ?? 'executive_summary', $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <label><span><?php esc_html_e( 'Frequency', WCPI_TEXT_DOMAIN ); ?></span><select name="email_reports_frequency"><?php foreach ( WCPI_Helpers::email_report_frequencies() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['email_reports_frequency'] ?? 'weekly', $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <p class="wcpi-panel-note"><?php esc_html_e( 'Weekly reports send on Monday. Monthly reports send on the first day of the month during daily maintenance.', WCPI_TEXT_DOMAIN ); ?></p>
                        </section>
                    </div>
                </section>

                <section class="wcpi-tab-panel" data-tab-panel="cleanup" aria-hidden="true">
                    <div class="wcpi-settings-grid">
                        <section class="wcpi-panel">
                            <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Cleanup', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Choose whether plugin data should be removed automatically during uninstall.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                            <label class="wcpi-toggle"><span><?php esc_html_e( 'Delete Plugin Data on Uninstall', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="delete_on_uninstall" value="yes" <?php checked( $settings['delete_on_uninstall'] ?? 'no', 'yes' ); ?>></label>
                        </section>
                    </div>
                </section>
            </div>
        </section>

        <div class="wcpi-settings-submit">
            <button class="button button-primary"><?php esc_html_e( 'Save Settings', WCPI_TEXT_DOMAIN ); ?></button>
            <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
        </div>
    </form>
</div>
