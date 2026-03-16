<?php
defined( 'ABSPATH' ) || exit;

$compare_enabled = ! empty( $data['comparison_enabled'] );
$allowed_tabs    = array( 'executive', 'products', 'categories', 'planning' );
$active_tab      = WCPI_Security::text( $_GET['tab'] ?? 'executive' );
$active_tab      = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'executive';
$bootstrap       = array(
    'overview'            => $data,
    'top_products'        => $data['top_products'],
    'low_margin'          => $data['low_margin'],
    'high_revenue'        => $data['highest_revenue'],
    'most_sold'           => $data['most_sold_products'],
    'summary_rows'        => WCPI_Report_Query::summaries( $data['from'], $data['to'] ),
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
?>
<div class="wrap wcpi-shell wcpi-shell-hero" id="wcpi-reports-page">
    <section class="wcpi-hero wcpi-hero-indigo">
        <div class="wcpi-hero-head">
            <div>
                <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Analytics Center', WCPI_TEXT_DOMAIN ); ?></p>
                <h1><?php esc_html_e( 'Reports', WCPI_TEXT_DOMAIN ); ?></h1>
                <p><?php esc_html_e( 'Executive analysis, product breakdowns, category intelligence, and planning tools grouped into faster SaaS-style views.', WCPI_TEXT_DOMAIN ); ?></p>
            </div>
            <div class="wcpi-hero-meta">
                <span class="wcpi-hero-chip" id="wcpi-reports-range-label"><?php echo esc_html( $data['range_label'] ?? '' ); ?></span>
                <a class="button wcpi-hero-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-export&tab=generate' ) ); ?>"><?php esc_html_e( 'Export', WCPI_TEXT_DOMAIN ); ?></a>
            </div>
        </div>

        <div class="wcpi-hero-toolbar">
            <div class="wcpi-topbar-grid">
                <label>
                    <span><?php esc_html_e( 'Preset', WCPI_TEXT_DOMAIN ); ?></span>
                    <select id="reports-preset">
                        <?php foreach ( WCPI_Helpers::date_presets() as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $preset, $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e( 'From', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="date" id="reports-from" value="<?php echo esc_attr( $data['from'] ); ?>">
                </label>
                <label>
                    <span><?php esc_html_e( 'To', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="date" id="reports-to" value="<?php echo esc_attr( $data['to'] ); ?>">
                </label>
                <label class="wcpi-toggle wcpi-toggle-light">
                    <span><?php esc_html_e( 'Compare Previous Period', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="checkbox" id="reports-compare" <?php checked( $compare_enabled ); ?>>
                </label>
                <div class="wcpi-toolbar-actions">
                    <button class="button button-primary" id="reports-apply"><?php esc_html_e( 'Apply', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>
    </section>

    <section class="wcpi-tabs-shell wcpi-tabs-shell-hero" id="wcpi-reports-tabs" data-query-key="tab" data-default-tab="executive" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
        <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Report sections', WCPI_TEXT_DOMAIN ); ?>">
            <button type="button" class="wcpi-tab-button" data-tab-target="executive" role="tab" aria-selected="false">
                <span class="dashicons dashicons-chart-area"></span>
                <span><?php esc_html_e( 'Executive', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="products" role="tab" aria-selected="false">
                <span class="dashicons dashicons-products"></span>
                <span><?php esc_html_e( 'Products', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="categories" role="tab" aria-selected="false">
                <span class="dashicons dashicons-chart-pie"></span>
                <span><?php esc_html_e( 'Categories', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="planning" role="tab" aria-selected="false">
                <span class="dashicons dashicons-admin-tools"></span>
                <span><?php esc_html_e( 'Planning', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
        </div>

        <div class="wcpi-tab-panels">
            <section class="wcpi-tab-panel" data-tab-panel="executive" aria-hidden="true">
                <div id="wcpi-report-executive"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="products" aria-hidden="true">
                <div id="wcpi-report-products"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="categories" aria-hidden="true">
                <div id="wcpi-report-categories-panel"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="planning" aria-hidden="true">
                <div id="wcpi-report-planning"></div>
            </section>
        </div>
    </section>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
    <script>window.wcpiReportsBootstrap = <?php echo wp_json_encode( $bootstrap ); ?>;</script>
</div>
