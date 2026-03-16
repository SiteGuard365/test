<?php
defined( 'ABSPATH' ) || exit;

$allowed_tabs = array( 'health', 'maintenance', 'storage', 'logs' );
$active_tab   = WCPI_Security::text( $_GET['tab'] ?? 'health' );
$active_tab   = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'health';
$snapshot_url = wp_nonce_url( admin_url( 'admin-post.php?action=wcpi_download_diagnostics_snapshot' ), 'wcpi_download_diagnostics_snapshot', '_wcpi_nonce' );
?>
<div class="wrap wcpi-shell" id="wcpi-diagnostics-page">
    <div class="wcpi-page-head">
        <div>
            <h1><?php esc_html_e( 'Diagnostics', WCPI_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'System health, maintenance actions, storage visibility, and logs grouped into cleaner operational tabs.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
    </div>

    <section class="wcpi-tabs-shell wcpi-tabs-shell-soft" id="wcpi-diagnostics-tabs" data-auto-tabs="yes" data-query-key="tab" data-default-tab="health" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
        <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Diagnostics sections', WCPI_TEXT_DOMAIN ); ?>">
            <button type="button" class="wcpi-tab-button" data-tab-target="health" role="tab"><span class="dashicons dashicons-heart"></span><span><?php esc_html_e( 'Health', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="maintenance" role="tab"><span class="dashicons dashicons-admin-tools"></span><span><?php esc_html_e( 'Maintenance', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="storage" role="tab"><span class="dashicons dashicons-database"></span><span><?php esc_html_e( 'Storage', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="logs" role="tab"><span class="dashicons dashicons-list-view"></span><span><?php esc_html_e( 'Logs', WCPI_TEXT_DOMAIN ); ?></span></button>
        </div>

        <div class="wcpi-tab-panels">
            <section class="wcpi-tab-panel" data-tab-panel="health" aria-hidden="true">
                <div class="wcpi-stat-grid wcpi-stat-grid-3">
                    <?php foreach ( $health_cards as $card ) : ?>
                        <section class="wcpi-stat-card <?php echo esc_attr( 'healthy' === $card['status'] ? 'wcpi-card-primary' : 'wcpi-card-secondary' ); ?>">
                            <span class="wcpi-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
                            <strong class="wcpi-stat-value"><?php echo esc_html( $card['value'] ); ?></strong>
                            <span class="wcpi-badge wcpi-badge-<?php echo esc_attr( 'healthy' === $card['status'] ? 'success' : ( 'error' === $card['status'] ? 'danger' : 'warning' ) ); ?>"><?php echo esc_html( ucfirst( $card['status'] ) ); ?></span>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Folder Status', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Writable-state visibility for uploads, exports, cache, logs, and backups.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <div class="wcpi-budget-list">
                            <?php foreach ( $folders as $key => $folder ) : ?>
                                <div class="wcpi-budget-row">
                                    <div><strong><?php echo esc_html( ucfirst( $key ) ); ?></strong><small><?php echo esc_html( $folder['path'] ); ?></small></div>
                                    <span class="wcpi-badge wcpi-badge-<?php echo esc_attr( $folder['writable'] ? 'success' : 'danger' ); ?>"><?php echo esc_html( $folder['writable'] ? __( 'Writable', WCPI_TEXT_DOMAIN ) : __( 'Blocked', WCPI_TEXT_DOMAIN ) ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Operational Status', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Recent rebuild, sync, recurring-template, and log volume visibility.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <div class="wcpi-budget-list">
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Last Analytics Rebuild', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $last_rebuild_status ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Last Sync', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $last_sync_status ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Recurring Templates', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( number_format_i18n( $recurring_count ) ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Recent Logs', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( number_format_i18n( $log_count ) ); ?></span></div>
                        </div>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="maintenance" aria-hidden="true">
                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Maintenance Tools', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Grouped actions for cleanup, rebuilds, backups, and optimization.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcpi-maintenance-grid wcpi-maintenance-grid-rich">
                            <input type="hidden" name="action" value="wcpi_maintenance">
                            <?php WCPI_Security::nonce_field( 'wcpi_maintenance' ); ?>
                            <button name="maintenance_action" value="clear_cache" class="button wcpi-maintenance-button"><span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Clear Cache', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="clear_exports" class="button wcpi-maintenance-button"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Clear Exports', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="clear_logs" class="button wcpi-maintenance-button"><span class="dashicons dashicons-dismiss"></span><?php esc_html_e( 'Clear Logs', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="rebuild_last_30" class="button button-primary wcpi-maintenance-button"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Rebuild Last 30 Days', WCPI_TEXT_DOMAIN ); ?></button>
                            <input type="date" name="from">
                            <input type="date" name="to">
                            <button name="maintenance_action" value="rebuild_selected" class="button wcpi-maintenance-button"><span class="dashicons dashicons-calendar-alt"></span><?php esc_html_e( 'Rebuild Selected Range', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="create_backup" class="button wcpi-maintenance-button"><span class="dashicons dashicons-cloud-upload"></span><?php esc_html_e( 'Create Backup Snapshot', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="optimize_tables" class="button wcpi-maintenance-button"><span class="dashicons dashicons-database-view"></span><?php esc_html_e( 'Optimize Tables', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="regenerate_upload_structure" class="button wcpi-maintenance-button"><span class="dashicons dashicons-portfolio"></span><?php esc_html_e( 'Regenerate Upload Structure', WCPI_TEXT_DOMAIN ); ?></button>
                            <button name="maintenance_action" value="resync_recurring_entries" class="button wcpi-maintenance-button"><span class="dashicons dashicons-controls-repeat"></span><?php esc_html_e( 'Re-sync Recurring Entries', WCPI_TEXT_DOMAIN ); ?></button>
                            <a class="button wcpi-maintenance-link" href="<?php echo esc_url( $snapshot_url ); ?>"><span class="dashicons dashicons-media-document"></span><?php esc_html_e( 'Download Diagnostics Snapshot', WCPI_TEXT_DOMAIN ); ?></a>
                        </form>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Quick Guidance', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'A few safe operations to keep analytics stable and folders clean.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <div class="wcpi-stack">
                            <article class="wcpi-focus-card"><strong><?php esc_html_e( 'Rebuild after cost imports', WCPI_TEXT_DOMAIN ); ?></strong><p><?php esc_html_e( 'Use range rebuilds after large product-cost or expense updates so daily summaries stay accurate.', WCPI_TEXT_DOMAIN ); ?></p></article>
                            <article class="wcpi-focus-card"><strong><?php esc_html_e( 'Download snapshots before major cleanup', WCPI_TEXT_DOMAIN ); ?></strong><p><?php esc_html_e( 'The diagnostics snapshot is a lightweight JSON export for support and internal audits.', WCPI_TEXT_DOMAIN ); ?></p></article>
                            <article class="wcpi-focus-card"><strong><?php esc_html_e( 'Re-sync recurring entries after template edits', WCPI_TEXT_DOMAIN ); ?></strong><p><?php esc_html_e( 'This ensures recurring templates have generated all expected expense rows.', WCPI_TEXT_DOMAIN ); ?></p></article>
                        </div>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="storage" aria-hidden="true">
                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Storage Metrics', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Current storage footprint for cache, exports, logs, and backups.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <div class="wcpi-budget-list">
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Cache Size', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['cache'] ) ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Exports Size', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['exports'] ) ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Logs Size', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['logs'] ) ); ?></span></div>
                            <div class="wcpi-budget-row"><strong><?php esc_html_e( 'Backups Size', WCPI_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( WCPI_Helpers::human_bytes( $storage['backups'] ) ); ?></span></div>
                        </div>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Table Health', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Table existence and row volume for the full data model.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                        <div class="wcpi-table-wrap">
                            <table class="widefat wcpi-table">
                                <thead><tr><th><?php esc_html_e( 'Key', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Table', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Exists', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Rows', WCPI_TEXT_DOMAIN ); ?></th></tr></thead>
                                <tbody><?php foreach ( $tables as $key => $table ) : ?><tr><td><?php echo esc_html( $key ); ?></td><td><code><?php echo esc_html( $table['name'] ); ?></code></td><td><span class="wcpi-badge wcpi-badge-<?php echo esc_attr( $table['exists'] ? 'success' : 'danger' ); ?>"><?php echo esc_html( $table['exists'] ? __( 'Healthy', WCPI_TEXT_DOMAIN ) : __( 'Missing', WCPI_TEXT_DOMAIN ) ); ?></span></td><td><?php echo esc_html( number_format_i18n( $table['rows'] ) ); ?></td></tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="logs" aria-hidden="true">
                <section class="wcpi-panel">
                    <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Recent Logs', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Latest diagnostic entries with level, context, and message visibility.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                    <div class="wcpi-table-wrap">
                        <table class="widefat wcpi-table">
                            <thead><tr><th><?php esc_html_e( 'Date', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Level', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Context', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Message', WCPI_TEXT_DOMAIN ); ?></th></tr></thead>
                            <tbody>
                                <?php if ( empty( $logs ) ) : ?>
                                    <tr><td colspan="4"><div class="wcpi-empty-inline"><?php esc_html_e( 'No logs available.', WCPI_TEXT_DOMAIN ); ?></div></td></tr>
                                <?php else : ?>
                                    <?php foreach ( $logs as $log ) : ?>
                                        <tr><td><?php echo esc_html( $log['created_at'] ); ?></td><td><span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( strtoupper( $log['log_level'] ) ); ?></span></td><td><span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( $log['context'] ); ?></span></td><td><?php echo esc_html( $log['message'] ); ?></td></tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </section>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
</div>
