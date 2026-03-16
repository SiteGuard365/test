<?php
defined( 'ABSPATH' ) || exit;
$audit            = WCPI_Report_Query::missing_cost_audit();
$margin_threshold = (float) WCPI_Settings_Manager::get( 'general', 'margin_threshold', 20 );
?>
<div class="wrap wcpi-shell" id="wcpi-product-costs-page">
    <div class="wcpi-page-head">
        <div>
            <h1><?php esc_html_e( 'Product Costs', WCPI_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'A premium cost workspace for completeness tracking, live total calculation, margin hygiene, and smoother bulk updates.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
        <div class="wcpi-head-meta">
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-reports&tab=products' ) ); ?>"><?php esc_html_e( 'Open Reports', WCPI_TEXT_DOMAIN ); ?></a>
        </div>
    </div>

    <div class="wcpi-stat-grid wcpi-stat-grid-6">
        <section class="wcpi-stat-card wcpi-card-primary"><span class="wcpi-stat-label"><?php esc_html_e( 'Products With Costs', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value wcpi-audit-products-with-costs"><?php echo esc_html( number_format_i18n( (int) $audit['products_with_costs'] ) ); ?></strong></section>
        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Missing Costs', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value wcpi-audit-missing-products"><?php echo esc_html( number_format_i18n( (int) $audit['missing_products'] ) ); ?></strong></section>
        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Variation Gaps', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value wcpi-audit-variation-gaps"><?php echo esc_html( number_format_i18n( (int) ( $audit['variation_missing'] ?? 0 ) ) ); ?></strong></section>
        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Average Margin Threshold', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo esc_html( number_format_i18n( $margin_threshold, 1 ) . '%' ); ?></strong></section>
        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Last Cost Update', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo esc_html( ! empty( $audit['last_cost_update'] ) ? $audit['last_cost_update'] : __( 'Not available', WCPI_TEXT_DOMAIN ) ); ?></strong></section>
        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Cost Completeness', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value wcpi-audit-completeness"><?php echo esc_html( number_format_i18n( (float) $audit['completeness_percent'], 1 ) . '%' ); ?></strong><div class="wcpi-progress"><span class="wcpi-audit-progress" style="width:<?php echo esc_attr( max( 4, (float) $audit['completeness_percent'] ) ); ?>%"></span></div></section>
    </div>

    <form method="get" class="wcpi-toolbar-card">
        <input type="hidden" name="page" value="wcpi-product-costs">
        <div class="wcpi-filter-grid">
            <label><span><?php esc_html_e( 'Search Products', WCPI_TEXT_DOMAIN ); ?></span><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"></label>
            <label><span><?php esc_html_e( 'Product Type', WCPI_TEXT_DOMAIN ); ?></span><select name="product_type"><option value=""><?php esc_html_e( 'All', WCPI_TEXT_DOMAIN ); ?></option><option value="simple" <?php selected( $type, 'simple' ); ?>><?php esc_html_e( 'Simple', WCPI_TEXT_DOMAIN ); ?></option><option value="variable" <?php selected( $type, 'variable' ); ?>><?php esc_html_e( 'Variable', WCPI_TEXT_DOMAIN ); ?></option></select></label>
            <label><span><?php esc_html_e( 'Status', WCPI_TEXT_DOMAIN ); ?></span><select name="status_filter"><option value=""><?php esc_html_e( 'All statuses', WCPI_TEXT_DOMAIN ); ?></option><option value="healthy" <?php selected( $status_filter, 'healthy' ); ?>><?php esc_html_e( 'Healthy', WCPI_TEXT_DOMAIN ); ?></option><option value="missing" <?php selected( $status_filter, 'missing' ); ?>><?php esc_html_e( 'Missing Cost', WCPI_TEXT_DOMAIN ); ?></option><option value="warning" <?php selected( $status_filter, 'warning' ); ?>><?php esc_html_e( 'Zero Cost', WCPI_TEXT_DOMAIN ); ?></option><option value="incomplete" <?php selected( $status_filter, 'incomplete' ); ?>><?php esc_html_e( 'Incomplete Row', WCPI_TEXT_DOMAIN ); ?></option></select></label>
            <label><span><?php esc_html_e( 'Cost Completeness', WCPI_TEXT_DOMAIN ); ?></span><select name="missing_cost_filter"><option value=""><?php esc_html_e( 'All rows', WCPI_TEXT_DOMAIN ); ?></option><option value="missing_only" <?php selected( $missing_filter, 'missing_only' ); ?>><?php esc_html_e( 'Needs attention only', WCPI_TEXT_DOMAIN ); ?></option><option value="healthy_only" <?php selected( $missing_filter, 'healthy_only' ); ?>><?php esc_html_e( 'Healthy only', WCPI_TEXT_DOMAIN ); ?></option></select></label>
            <label class="wcpi-toggle"><span><?php esc_html_e( 'Low Margin Only', WCPI_TEXT_DOMAIN ); ?></span><input type="checkbox" name="low_margin_only" value="yes" <?php checked( $low_margin_only ); ?>></label>
            <div class="wcpi-toolbar-actions wcpi-span-full">
                <button class="button button-primary"><?php esc_html_e( 'Apply Filters', WCPI_TEXT_DOMAIN ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-product-costs' ) ); ?>"><?php esc_html_e( 'Reset', WCPI_TEXT_DOMAIN ); ?></a>
            </div>
        </div>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wcpi-product-costs-form">
        <input type="hidden" name="action" value="wcpi_save_product_costs_page">
        <?php WCPI_Security::nonce_field( 'wcpi_save_product_costs_page' ); ?>

        <section class="wcpi-panel">
            <div class="wcpi-workspace-head">
                <div>
                    <h2><?php esc_html_e( 'Bulk Actions Workspace', WCPI_TEXT_DOMAIN ); ?></h2>
                    <p class="wcpi-panel-note"><?php esc_html_e( 'Update cost components, review live totals and estimated margin, then save visible rows with AJAX.', WCPI_TEXT_DOMAIN ); ?></p>
                </div>
                <div class="wcpi-inline-tools">
                    <select name="bulk_action">
                        <option value=""><?php esc_html_e( 'No bulk action', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="packaging"><?php esc_html_e( 'Apply packaging cost', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="handling"><?php esc_html_e( 'Apply handling cost', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="increase_fixed"><?php esc_html_e( 'Increase total by amount', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="decrease_fixed"><?php esc_html_e( 'Decrease total by amount', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="increase_percent"><?php esc_html_e( 'Increase total by %', WCPI_TEXT_DOMAIN ); ?></option>
                        <option value="decrease_percent"><?php esc_html_e( 'Decrease total by %', WCPI_TEXT_DOMAIN ); ?></option>
                    </select>
                    <input type="number" step="0.000001" name="bulk_value" placeholder="<?php esc_attr_e( 'Value', WCPI_TEXT_DOMAIN ); ?>">
                    <button class="button button-primary" id="wcpi-save-visible-costs-ajax"><?php esc_html_e( 'Save Visible Costs', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>

            <div class="wcpi-table-wrap">
                <table class="widefat wcpi-table wcpi-table-sticky">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Product', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'SKU', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Type', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Catalog Price', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Status', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Cost Price', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Packaging', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Handling', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Extra', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Total', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Est. Margin %', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Last Updated', WCPI_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Row State', WCPI_TEXT_DOMAIN ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $products ) ) : ?>
                            <tr><td colspan="13"><div class="wcpi-empty-inline"><?php esc_html_e( 'No products found for this filter.', WCPI_TEXT_DOMAIN ); ?></div></td></tr>
                        <?php else : ?>
                            <?php foreach ( $products as $product ) : ?>
                                <?php
                                $row            = WCPI_Product_Cost_Manager::get_cost_row( $product->get_id(), 0 );
                                $parts_total    = (float) $row['cost_price'] + (float) $row['packaging_cost'] + (float) $row['handling_cost'] + (float) $row['extra_cost'];
                                $status         = empty( $row['id'] ) ? 'missing' : ( abs( (float) $row['total_unit_cost'] - $parts_total ) > 0.0001 ? 'incomplete' : ( (float) $row['total_unit_cost'] <= 0 ? 'warning' : 'healthy' ) );
                                $price          = (float) $product->get_price();
                                $margin         = $price > 0 ? ( ( $price - (float) $row['total_unit_cost'] ) / $price ) * 100 : 0;
                                $low_margin     = $price > 0 && (float) $row['total_unit_cost'] > 0 && $margin < $margin_threshold;
                                $variation_gap  = false;
                                if ( $product->is_type( 'variable' ) ) {
                                    foreach ( $product->get_children() as $variation_id ) {
                                        $variation_row = WCPI_Product_Cost_Manager::get_cost_row( $product->get_id(), (int) $variation_id );
                                        if ( empty( $variation_row['id'] ) || (float) $variation_row['total_unit_cost'] <= 0 ) {
                                            $variation_gap = true;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr class="wcpi-cost-row wcpi-row-<?php echo esc_attr( $status ); ?>" data-price="<?php echo esc_attr( $price ); ?>" data-margin-threshold="<?php echo esc_attr( $margin_threshold ); ?>" data-has-row="<?php echo ! empty( $row['id'] ) ? '1' : '0'; ?>" data-variation-gap="<?php echo $variation_gap ? '1' : '0'; ?>">
                                    <td>
                                        <strong><?php echo esc_html( $product->get_name() ); ?></strong>
                                        <input type="hidden" name="product_id[]" value="<?php echo esc_attr( $product->get_id() ); ?>">
                                        <input type="hidden" name="variation_id[]" value="0">
                                    </td>
                                    <td><?php echo esc_html( $product->get_sku() ); ?></td>
                                    <td><span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( ucfirst( $product->get_type() ) ); ?></span></td>
                                    <td><?php echo wp_kses_post( WCPI_Helpers::format_price( $price ) ); ?></td>
                                    <td><div class="wcpi-status-stack"><?php echo wp_kses_post( ( 'healthy' === $status ? '<span class="wcpi-badge wcpi-badge-success">' . esc_html__( 'Healthy', WCPI_TEXT_DOMAIN ) . '</span>' : ( 'warning' === $status ? '<span class="wcpi-badge wcpi-badge-warning">' . esc_html__( 'Zero Cost', WCPI_TEXT_DOMAIN ) . '</span>' : ( 'incomplete' === $status ? '<span class="wcpi-badge wcpi-badge-warning">' . esc_html__( 'Incomplete Row', WCPI_TEXT_DOMAIN ) . '</span>' : '<span class="wcpi-badge wcpi-badge-danger">' . esc_html__( 'Missing Cost', WCPI_TEXT_DOMAIN ) . '</span>' ) ) ) . ( $low_margin ? '<span class="wcpi-badge wcpi-badge-warning">' . esc_html__( 'Low Margin', WCPI_TEXT_DOMAIN ) . '</span>' : '' ) . ( $variation_gap ? '<span class="wcpi-badge wcpi-badge-neutral">' . esc_html__( 'Variation Gap', WCPI_TEXT_DOMAIN ) . '</span>' : '' ) ); ?></div></td>
                                    <td><input class="wcpi-cost-input wcpi-cost-part" type="number" step="0.000001" name="cost_price[]" value="<?php echo esc_attr( $row['cost_price'] ); ?>"></td>
                                    <td><input class="wcpi-cost-input wcpi-cost-part" type="number" step="0.000001" name="packaging_cost[]" value="<?php echo esc_attr( $row['packaging_cost'] ); ?>"></td>
                                    <td><input class="wcpi-cost-input wcpi-cost-part" type="number" step="0.000001" name="handling_cost[]" value="<?php echo esc_attr( $row['handling_cost'] ); ?>"></td>
                                    <td><input class="wcpi-cost-input wcpi-cost-part" type="number" step="0.000001" name="extra_cost[]" value="<?php echo esc_attr( $row['extra_cost'] ); ?>"></td>
                                    <td><input class="wcpi-cost-input wcpi-total-cost" type="number" step="0.000001" name="total_unit_cost[]" value="<?php echo esc_attr( $row['total_unit_cost'] ); ?>" readonly></td>
                                    <td><strong class="wcpi-est-margin"><?php echo esc_html( $price > 0 && (float) $row['total_unit_cost'] > 0 ? number_format_i18n( $margin, 1 ) . '%' : '--' ); ?></strong></td>
                                    <td><?php echo esc_html( ! empty( $row['updated_at'] ) ? $row['updated_at'] : __( 'Never', WCPI_TEXT_DOMAIN ) ); ?></td>
                                    <td><span class="wcpi-badge wcpi-badge-neutral wcpi-row-state"><?php esc_html_e( 'Saved', WCPI_TEXT_DOMAIN ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'paged' => '%#%' ) ), 'format' => '', 'current' => $page, 'total' => $total_pages, 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ) ); ?></div></div>
            <?php endif; ?>
        </section>
    </form>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
</div>
