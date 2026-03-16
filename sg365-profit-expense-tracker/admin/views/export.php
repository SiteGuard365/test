<?php
defined( 'ABSPATH' ) || exit;

$allowed_tabs = array( 'generate', 'history' );
$active_tab   = WCPI_Security::text( $_GET['tab'] ?? 'generate' );
$active_tab   = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'generate';
?>
<div class="wrap wcpi-shell" id="wcpi-export-page">
    <div class="wcpi-page-head">
        <div>
            <h1><?php esc_html_e( 'Export', WCPI_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'Generate CSV, PDF, and Excel exports from a cleaner premium export center.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
    </div>

    <section class="wcpi-tabs-shell wcpi-tabs-shell-soft" id="wcpi-export-tabs" data-auto-tabs="yes" data-query-key="tab" data-default-tab="generate" data-active-tab="<?php echo esc_attr( $active_tab ); ?>" data-sync-input="#wcpi-export-return-tab">
        <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Export sections', WCPI_TEXT_DOMAIN ); ?>">
            <button type="button" class="wcpi-tab-button" data-tab-target="generate" role="tab"><span class="dashicons dashicons-download"></span><span><?php esc_html_e( 'Generate', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="history" role="tab"><span class="dashicons dashicons-media-spreadsheet"></span><span><?php esc_html_e( 'History', WCPI_TEXT_DOMAIN ); ?></span></button>
        </div>

        <div class="wcpi-tab-panels">
            <section class="wcpi-tab-panel" data-tab-panel="generate" aria-hidden="true">
                <section class="wcpi-panel">
                    <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Generate Export', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Choose the report preset, format, and date window, then generate a polished business export.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcpi-form-grid">
                        <input type="hidden" name="action" value="wcpi_generate_export">
                        <input type="hidden" name="return_tab" id="wcpi-export-return-tab" value="<?php echo esc_attr( $active_tab ); ?>">
                        <?php WCPI_Security::nonce_field( 'wcpi_generate_export' ); ?>
                        <label><span><?php esc_html_e( 'Export Preset', WCPI_TEXT_DOMAIN ); ?></span><select name="report"><option value="executive_summary"><?php esc_html_e( 'Executive Summary', WCPI_TEXT_DOMAIN ); ?></option><option value="dashboard_summary"><?php esc_html_e( 'Profit Dashboard', WCPI_TEXT_DOMAIN ); ?></option><option value="product_profit"><?php esc_html_e( 'Product Profitability Report', WCPI_TEXT_DOMAIN ); ?></option><option value="expense_report"><?php esc_html_e( 'Expense Statement', WCPI_TEXT_DOMAIN ); ?></option><option value="daily_summary"><?php esc_html_e( 'Daily Summary', WCPI_TEXT_DOMAIN ); ?></option><option value="category_profit"><?php esc_html_e( 'Category Profit Report', WCPI_TEXT_DOMAIN ); ?></option></select></label>
                        <label><span><?php esc_html_e( 'Format', WCPI_TEXT_DOMAIN ); ?></span><select name="format"><option value="csv"><?php esc_html_e( 'CSV', WCPI_TEXT_DOMAIN ); ?></option><option value="pdf"><?php esc_html_e( 'PDF', WCPI_TEXT_DOMAIN ); ?></option><option value="xlsx"><?php esc_html_e( 'Excel / XLSX', WCPI_TEXT_DOMAIN ); ?></option></select></label>
                        <label><span><?php esc_html_e( 'Date Preset', WCPI_TEXT_DOMAIN ); ?></span><select name="preset"><?php foreach ( WCPI_Helpers::date_presets() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'From', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="from"></label>
                        <label><span><?php esc_html_e( 'To', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="to"></label>
                        <label class="wcpi-toggle"><span><?php esc_html_e( 'Compare Previous Period', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="compare_previous" value="yes" <?php checked( WCPI_Settings_Manager::get( 'general', 'compare_previous_default', 'yes' ), 'yes' ); ?>></label>
                        <div class="wcpi-span-2"><button class="button button-primary"><?php esc_html_e( 'Generate Export', WCPI_TEXT_DOMAIN ); ?></button></div>
                    </form>
                    <?php if ( ! empty( $_GET['generated'] ) && ! empty( $_GET['file'] ) ) : ?>
                        <p><a class="button" href="<?php echo esc_url( rawurldecode( sanitize_text_field( wp_unslash( $_GET['file'] ) ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download Latest Export', WCPI_TEXT_DOMAIN ); ?></a></p>
                    <?php endif; ?>
                </section>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="history" aria-hidden="true">
                <section class="wcpi-panel">
                    <div class="wcpi-panel-head"><div><h2><?php esc_html_e( 'Recent Exports', WCPI_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Recent generated files with report type, format, timestamp, file size, and direct download access.', WCPI_TEXT_DOMAIN ); ?></p></div></div>
                    <?php if ( empty( $recent_exports ) ) : ?>
                        <div class="wcpi-empty-state"><span class="dashicons dashicons-media-document"></span><h3><?php esc_html_e( 'No exports yet', WCPI_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Generate your first CSV, PDF, or Excel export to populate this archive.', WCPI_TEXT_DOMAIN ); ?></p></div>
                    <?php else : ?>
                        <div class="wcpi-export-list">
                            <?php foreach ( $recent_exports as $item ) : ?>
                                <article class="wcpi-export-card">
                                    <div>
                                        <strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $item['report_key'] ) ) ); ?></strong>
                                        <small><?php echo esc_html( strtoupper( $item['export_type'] ) . ' | ' . $item['created_at'] ); ?></small>
                                    </div>
                                    <div class="wcpi-inline-tools">
                                        <span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( WCPI_Helpers::human_bytes( (int) $item['file_size'] ) ); ?></span>
                                        <a class="button button-small" href="<?php echo esc_url( $item['file_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download', WCPI_TEXT_DOMAIN ); ?></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        </div>
    </section>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
</div>
