<?php
/**
 * Analytics report SQL.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Report_Query {

    /**
     * Get product profitability rows.
     *
     * @param string $from From.
     * @param string $to To.
     * @param int    $limit Limit.
     * @param string $sort Sort key.
     * @return array<int, array<string, mixed>>
     */
    public static function product_profitability( string $from, string $to, int $limit = 20, string $sort = 'profit_desc' ): array {
        global $wpdb;

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $stats_table  = $wpdb->prefix . 'wc_order_stats';
        $costs_table  = WCPI_DB::table( 'product_costs' );

        if ( ! WCPI_Helpers::is_woocommerce_active() || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup_table ) ) !== $lookup_table ) {
            return array();
        }

        $revenue_expr = 'SUM(opl.product_net_revenue)';
        if ( 'yes' === WCPI_Settings_Manager::get( 'general', 'include_tax', 'no' ) ) {
            $revenue_expr = 'SUM(opl.product_net_revenue + opl.tax_amount)';
        }
        $sort_map = array(
            'profit_desc'   => 'profit DESC',
            'profit_asc'    => 'profit ASC',
            'margin_asc'    => 'margin_percent ASC',
            'revenue_desc'  => 'revenue DESC',
            'qty_desc'      => 'quantity_sold DESC',
        );
        $order_by = $sort_map[ $sort ] ?? 'profit DESC';
        $limit_sql = $limit > 0 ? ' LIMIT %d' : '';

        $sql = "
            SELECT
                opl.product_id,
                opl.variation_id,
                SUM(opl.product_qty) AS quantity_sold,
                COUNT(DISTINCT opl.order_id) AS orders_count,
                COUNT(DISTINCT DATE(os.date_created)) AS active_selling_days,
                {$revenue_expr} AS revenue,
                COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0) AS unit_cost,
                SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0) AS cost,
                ({$revenue_expr} - (SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) AS profit,
                CASE
                    WHEN {$revenue_expr} > 0 THEN (({$revenue_expr} - (SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) / {$revenue_expr}) * 100
                    ELSE 0
                END AS margin_percent
            FROM {$lookup_table} opl
            INNER JOIN {$stats_table} os ON os.order_id = opl.order_id
            LEFT JOIN {$costs_table} vc ON vc.product_id = opl.product_id AND vc.variation_id = opl.variation_id
            LEFT JOIN {$costs_table} pc ON pc.product_id = opl.product_id AND pc.variation_id = 0
            WHERE DATE(os.date_created) BETWEEN %s AND %s
                AND os.status IN ('wc-processing','wc-completed','wc-on-hold')
            GROUP BY opl.product_id, opl.variation_id, unit_cost
            ORDER BY {$order_by}
        " . $limit_sql;

        $prepared = $limit > 0 ? $wpdb->prepare( $sql, $from, $to, $limit ) : $wpdb->prepare( $sql, $from, $to );
        $rows     = $wpdb->get_results( $prepared, ARRAY_A );
        $context  = array(
            'max_revenue' => 0.0,
            'max_quantity'=> 0.0,
            'period_days' => WCPI_Helpers::range_days( $from, $to ),
        );

        foreach ( $rows as $row ) {
            $context['max_revenue'] = max( $context['max_revenue'], (float) $row['revenue'] );
            $context['max_quantity'] = max( $context['max_quantity'], (float) $row['quantity_sold'] );
        }

        foreach ( $rows as &$row ) {
            $product_id   = (int) $row['product_id'];
            $variation_id = (int) $row['variation_id'];
            $entity_id    = (int) ( $variation_id ?: $product_id );
            $product      = wc_get_product( $entity_id );
            $cost_status  = self::entity_cost_status( $product_id, $variation_id );
            $health       = WCPI_Product_Health_Scorer::score_row(
                array_merge(
                    $row,
                    array(
                        'cost_status'  => $cost_status,
                        'refund_ratio' => self::estimate_refund_ratio( $row ),
                    )
                ),
                $context
            );
            $row['name']                = $product ? $product->get_name() : __( 'Unknown product', WCPI_TEXT_DOMAIN );
            $row['sku']                 = $product ? $product->get_sku() : '';
            $row['catalog_price']       = $product ? (float) $product->get_price() : 0.0;
            $row['refund_ratio']        = self::estimate_refund_ratio( $row );
            $row['health_score']        = $health['score'];
            $row['health_label']        = $health['label'];
            $row['health_factors']      = $health['factors'];
            $row['cost_status']         = $cost_status;
            $row['avg_selling_price']   = (float) $row['quantity_sold'] > 0 ? (float) $row['revenue'] / (float) $row['quantity_sold'] : 0;
        }

        return $rows;
    }

    /**
     * Expense breakdown by category.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<int, array<string, mixed>>
     */
    public static function expense_breakdown( string $from, string $to ): array {
        global $wpdb;
        $table = WCPI_DB::table( 'expenses' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, SUM(amount) AS total
                FROM {$table}
                WHERE expense_date BETWEEN %s AND %s
                GROUP BY category
                ORDER BY total DESC",
                $from,
                $to
            ),
            ARRAY_A
        );
    }

    /**
     * Expense breakdown with budget comparison.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<int, array<string, mixed>>
     */
    public static function expense_budget_vs_actual( string $from, string $to ): array {
        $rows        = self::expense_breakdown( $from, $to );
        $budgets     = WCPI_Recurring_Expense_Manager::budgets();
        $all_rows     = array();
        $seen         = array();

        foreach ( $rows as &$row ) {
            $budget = WCPI_Helpers::prorate_monthly_amount( (float) ( $budgets[ $row['category'] ] ?? 0 ), $from, $to );
            $row['budget']           = $budget;
            $row['variance']         = (float) $row['total'] - $budget;
            $row['variance_percent'] = $budget > 0 ? ( ( (float) $row['total'] - $budget ) / $budget ) * 100 : 0;
            $row['status']           = (float) $row['variance'] > 0 ? 'over' : 'under';
            $row['display_name']     = WCPI_Helpers::expense_categories()[ $row['category'] ] ?? ucfirst( $row['category'] );
            $all_rows[]              = $row;
            $seen[ $row['category'] ] = true;
        }

        foreach ( $budgets as $category => $monthly_budget ) {
            if ( isset( $seen[ $category ] ) ) {
                continue;
            }

            $budget = WCPI_Helpers::prorate_monthly_amount( (float) $monthly_budget, $from, $to );
            $all_rows[] = array(
                'category'         => $category,
                'display_name'     => WCPI_Helpers::expense_categories()[ $category ] ?? ucfirst( $category ),
                'total'            => 0.0,
                'budget'           => $budget,
                'variance'         => 0.0 - $budget,
                'variance_percent' => $budget > 0 ? -100.0 : 0.0,
                'status'           => 'under',
            );
        }

        usort(
            $all_rows,
            static function ( array $a, array $b ): int {
                return abs( (float) $b['variance'] ) <=> abs( (float) $a['variance'] );
            }
        );

        return $all_rows;
    }

    /**
     * Summary rows by date.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<int, array<string, mixed>>
     */
    public static function summaries( string $from, string $to ): array {
        global $wpdb;
        $table = WCPI_DB::table( 'daily_summary' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE summary_date BETWEEN %s AND %s ORDER BY summary_date ASC",
                $from,
                $to
            ),
            ARRAY_A
        );
    }

    /**
     * Cost completeness audit.
     *
     * @return array<string, mixed>
     */
    public static function missing_cost_audit(): array {
        if ( ! WCPI_Helpers::is_woocommerce_active() ) {
            return array(
                'total_products' => 0,
                'products_with_costs' => 0,
                'missing_products' => 0,
                'zero_cost_products' => 0,
                'variation_missing' => 0,
                'variations_missing' => 0,
                'incomplete_rows' => 0,
                'outdated_cost_products' => 0,
                'completeness_percent' => 0,
                'last_cost_update' => null,
                'review_url' => admin_url( 'admin.php?page=wcpi-product-costs' ),
            );
        }

        $products = wc_get_products(
            array(
                'status' => array( 'publish', 'private' ),
                'limit'  => -1,
                'return' => 'objects',
            )
        );

        $missing           = 0;
        $zero              = 0;
        $variation_missing = 0;
        $incomplete_rows   = 0;
        $updated           = null;
        $total             = 0;
        $with_cost         = 0;

        foreach ( $products as $product ) {
            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            ++$total;
            $row = WCPI_Product_Cost_Manager::get_cost_row( $product->get_id(), 0 );
            if ( empty( $row['id'] ) ) {
                ++$missing;
            } else {
                ++$with_cost;

                if ( (float) $row['total_unit_cost'] <= 0 ) {
                    ++$zero;
                }

                if ( self::is_cost_row_incomplete( $row ) ) {
                    ++$incomplete_rows;
                }

                if ( ! empty( $row['updated_at'] ) && ( null === $updated || strtotime( $row['updated_at'] ) > strtotime( $updated ) ) ) {
                    $updated = $row['updated_at'];
                }
            }

            if ( $product->is_type( 'variable' ) ) {
                foreach ( $product->get_children() as $variation_id ) {
                    $variation_row = WCPI_Product_Cost_Manager::get_cost_row( $product->get_id(), (int) $variation_id );

                    if ( empty( $variation_row['id'] ) ) {
                        ++$variation_missing;
                        continue;
                    }

                    if ( self::is_cost_row_incomplete( $variation_row ) ) {
                        ++$incomplete_rows;
                    }

                    if ( ! empty( $variation_row['updated_at'] ) && ( null === $updated || strtotime( $variation_row['updated_at'] ) > strtotime( $updated ) ) ) {
                        $updated = $variation_row['updated_at'];
                    }
                }
            }
        }

        return array(
            'total_products'        => $total,
            'products_with_costs'   => $with_cost,
            'missing_products'      => $missing,
            'zero_cost_products'    => $zero,
            'variation_missing'     => $variation_missing,
            'variations_missing'    => $variation_missing,
            'incomplete_rows'       => $incomplete_rows,
            'outdated_cost_products'=> 0,
            'completeness_percent'  => $total > 0 ? ( $with_cost / $total ) * 100 : 0,
            'last_cost_update'      => $updated,
            'review_url'            => admin_url( 'admin.php?page=wcpi-product-costs' ),
        );
    }

    /**
     * Profitability by category.
     *
     * @param string $from From.
     * @param string $to To.
     * @return array<int, array<string, mixed>>
     */
    public static function category_profitability( string $from, string $to ): array {
        if ( ! WCPI_Helpers::is_woocommerce_active() ) {
            return array();
        }

        $products   = self::product_profitability( $from, $to, 0, 'profit_desc' );
        $categories = array();

        foreach ( $products as $row ) {
            $product = wc_get_product( (int) ( $row['variation_id'] ?: $row['product_id'] ) );
            if ( ! $product ) {
                continue;
            }

            $terms = get_the_terms( $product->get_parent_id() ?: $product->get_id(), 'product_cat' );
            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                $terms = array(
                    (object) array(
                        'name' => __( 'Uncategorized', WCPI_TEXT_DOMAIN ),
                    ),
                );
            }

            $term_count = max( 1, count( $terms ) );
            $revenue    = (float) $row['revenue'] / $term_count;
            $cost       = (float) $row['cost'] / $term_count;
            $profit     = (float) $row['profit'] / $term_count;
            $qty_sold   = (float) $row['quantity_sold'] / $term_count;

            foreach ( $terms as $term ) {
                $key = $term->name;
                if ( empty( $categories[ $key ] ) ) {
                    $categories[ $key ] = array(
                        'category' => $key,
                        'qty_sold' => 0.0,
                        'revenue'  => 0.0,
                        'cost'     => 0.0,
                        'profit'   => 0.0,
                    );
                }
                $categories[ $key ]['qty_sold'] += $qty_sold;
                $categories[ $key ]['revenue']  += $revenue;
                $categories[ $key ]['cost']     += $cost;
                $categories[ $key ]['profit']   += $profit;
            }
        }

        foreach ( $categories as &$category ) {
            $category['margin_percent'] = $category['revenue'] > 0 ? ( $category['profit'] / $category['revenue'] ) * 100 : 0;
            $category['heatmap_band']   = WCPI_Helpers::heatmap_band( (float) $category['margin_percent'] );
        }

        uasort(
            $categories,
            static function ( array $a, array $b ): int {
                return $b['profit'] <=> $a['profit'];
            }
        );

        return array_values( $categories );
    }

    /**
     * Recent export history.
     *
     * @param int $limit Limit.
     * @return array<int, array<string, mixed>>
     */
    public static function recent_exports( int $limit = 10 ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . WCPI_DB::table( 'export_history' ) . ' ORDER BY created_at DESC LIMIT %d', $limit ),
            ARRAY_A
        );
    }

    /**
     * Dashboard file storage metrics.
     *
     * @return array<string, int>
     */
    public static function storage_metrics(): array {
        $base = WCPI_Helpers::upload_base();
        return array(
            'cache'   => WCPI_Helpers::directory_size( $base['dir'] . 'cache' ),
            'exports' => WCPI_Helpers::directory_size( $base['dir'] . 'exports' ),
            'logs'    => WCPI_Helpers::directory_size( $base['dir'] . 'logs' ),
            'backups' => WCPI_Helpers::directory_size( $base['dir'] . 'backups' ),
        );
    }

    /**
     * Resolve missing/zero/incomplete cost status for a product entity.
     *
     * @param int $product_id Product id.
     * @param int $variation_id Variation id.
     * @return string
     */
    private static function entity_cost_status( int $product_id, int $variation_id = 0 ): string {
        $row = WCPI_Product_Cost_Manager::get_cost_row( $product_id, $variation_id );

        if ( empty( $row['id'] ) ) {
            return 'missing';
        }

        if ( self::is_cost_row_incomplete( $row ) ) {
            return 'incomplete';
        }

        if ( (float) $row['total_unit_cost'] <= 0 ) {
            return 'zero';
        }

        return 'healthy';
    }

    /**
     * Determine whether a cost row is structurally incomplete.
     *
     * @param array<string, mixed> $row Cost row.
     * @return bool
     */
    private static function is_cost_row_incomplete( array $row ): bool {
        $parts_total = (float) $row['cost_price'] + (float) $row['packaging_cost'] + (float) $row['handling_cost'] + (float) $row['extra_cost'];
        return abs( (float) $row['total_unit_cost'] - $parts_total ) > 0.0001;
    }

    /**
     * Notes for the current reporting window.
     *
     * @param string $from From.
     * @param string $to To.
     * @param int    $limit Limit.
     * @return array<int, array<string, mixed>>
     */
    public static function report_notes( string $from, string $to, int $limit = 10 ): array {
        return WCPI_Recurring_Expense_Manager::notes( $limit, $from, $to );
    }

    /**
     * Search products and variations for the simulator.
     *
     * @param string $term Search term.
     * @param int    $limit Limit.
     * @return array<int, array<string, mixed>>
     */
    public static function search_products( string $term, int $limit = 15 ): array {
        if ( strlen( $term ) < 2 ) {
            return array();
        }

        $query = new WP_Query(
            array(
                'post_type'      => array( 'product', 'product_variation' ),
                'post_status'    => array( 'publish', 'private' ),
                'posts_per_page' => $limit,
                's'              => $term,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $results = array();
        foreach ( $query->posts as $post_id ) {
            $product = wc_get_product( $post_id );
            if ( ! $product ) {
                continue;
            }

            $entity_id   = (int) $product->get_id();
            $product_id  = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : $entity_id;
            $variation_id = $product->is_type( 'variation' ) ? $entity_id : 0;
            $label       = $product->get_name();

            if ( $variation_id > 0 ) {
                $parent = wc_get_product( $product_id );
                if ( $parent ) {
                    $label = $parent->get_name() . ' - ' . wc_get_formatted_variation( $product, true, false, true );
                }
            }

            $results[] = array(
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'label'        => wp_strip_all_tags( $label ),
                'sku'          => $product->get_sku(),
                'price'        => (float) $product->get_price(),
            );
        }

        return $results;
    }

    /**
     * Baseline data for product-level simulators.
     *
     * @param int    $product_id Product id.
     * @param int    $variation_id Variation id.
     * @param string $from From.
     * @param string $to To.
     * @return array<string, mixed>
     */
    public static function simulator_baseline( int $product_id, int $variation_id, string $from, string $to ): array {
        $match = self::product_profitability_entity( $from, $to, $product_id, $variation_id );

        $entity_id   = $variation_id > 0 ? $variation_id : $product_id;
        $product     = wc_get_product( $entity_id );
        $days        = WCPI_Helpers::range_days( $from, $to );
        $units       = (float) ( $match['quantity_sold'] ?? 0 );
        $monthly_units = $days > 0 ? $units * ( 30 / $days ) : 0.0;
        $current_cost  = WCPI_Product_Cost_Manager::get_unit_cost( $product_id, $variation_id );
        $current_price = $product ? (float) $product->get_price() : (float) ( $match['avg_selling_price'] ?? 0 );

        return array(
            'product_id'         => $product_id,
            'variation_id'       => $variation_id,
            'product_name'       => $product ? $product->get_name() : __( 'Unknown product', WCPI_TEXT_DOMAIN ),
            'current_cost'       => $current_cost,
            'current_price'      => $current_price,
            'quantity_sold'      => $units,
            'monthly_units'      => $monthly_units,
            'historical_revenue' => (float) ( $match['revenue'] ?? 0 ),
            'historical_profit'  => (float) ( $match['profit'] ?? 0 ),
            'historical_margin'  => (float) ( $match['margin_percent'] ?? 0 ),
            'avg_selling_price'  => (float) ( $match['avg_selling_price'] ?? $current_price ),
            'range_days'         => $days,
        );
    }

    /**
     * Fetch profitability for a single entity.
     *
     * @param string $from From.
     * @param string $to To.
     * @param int    $product_id Product id.
     * @param int    $variation_id Variation id.
     * @return array<string, mixed>|null
     */
    private static function product_profitability_entity( string $from, string $to, int $product_id, int $variation_id ): ?array {
        global $wpdb;

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $stats_table  = $wpdb->prefix . 'wc_order_stats';
        $costs_table  = WCPI_DB::table( 'product_costs' );

        if ( ! WCPI_Helpers::is_woocommerce_active() || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup_table ) ) !== $lookup_table ) {
            return null;
        }

        $revenue_expr = 'SUM(opl.product_net_revenue)';
        if ( 'yes' === WCPI_Settings_Manager::get( 'general', 'include_tax', 'no' ) ) {
            $revenue_expr = 'SUM(opl.product_net_revenue + opl.tax_amount)';
        }

        if ( $variation_id > 0 ) {
            $where = $wpdb->prepare( 'opl.product_id = %d AND opl.variation_id = %d', $product_id, $variation_id );
            $sql = "
                SELECT
                    opl.product_id,
                    opl.variation_id,
                    SUM(opl.product_qty) AS quantity_sold,
                    COUNT(DISTINCT opl.order_id) AS orders_count,
                    COUNT(DISTINCT DATE(os.date_created)) AS active_selling_days,
                    {$revenue_expr} AS revenue,
                    COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0) AS unit_cost,
                    SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0) AS cost,
                    ({$revenue_expr} - (SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) AS profit,
                    CASE
                        WHEN {$revenue_expr} > 0 THEN (({$revenue_expr} - (SUM(opl.product_qty) * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) / {$revenue_expr}) * 100
                        ELSE 0
                    END AS margin_percent
                FROM {$lookup_table} opl
                INNER JOIN {$stats_table} os ON os.order_id = opl.order_id
                LEFT JOIN {$costs_table} vc ON vc.product_id = opl.product_id AND vc.variation_id = opl.variation_id
                LEFT JOIN {$costs_table} pc ON pc.product_id = opl.product_id AND pc.variation_id = 0
                WHERE DATE(os.date_created) BETWEEN %s AND %s
                    AND os.status IN ('wc-processing','wc-completed','wc-on-hold')
                    AND {$where}
                GROUP BY opl.product_id, opl.variation_id, unit_cost
                LIMIT 1
            ";
        } else {
            $where = $wpdb->prepare( 'opl.product_id = %d', $product_id );
            $sql = "
                SELECT
                    opl.product_id,
                    0 AS variation_id,
                    SUM(opl.product_qty) AS quantity_sold,
                    COUNT(DISTINCT opl.order_id) AS orders_count,
                    COUNT(DISTINCT DATE(os.date_created)) AS active_selling_days,
                    {$revenue_expr} AS revenue,
                    COALESCE(pc.total_unit_cost, 0) AS unit_cost,
                    SUM(opl.product_qty * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0)) AS cost,
                    ({$revenue_expr} - SUM(opl.product_qty * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) AS profit,
                    CASE
                        WHEN {$revenue_expr} > 0 THEN (({$revenue_expr} - SUM(opl.product_qty * COALESCE(vc.total_unit_cost, pc.total_unit_cost, 0))) / {$revenue_expr}) * 100
                        ELSE 0
                    END AS margin_percent
                FROM {$lookup_table} opl
                INNER JOIN {$stats_table} os ON os.order_id = opl.order_id
                LEFT JOIN {$costs_table} vc ON vc.product_id = opl.product_id AND vc.variation_id = opl.variation_id
                LEFT JOIN {$costs_table} pc ON pc.product_id = opl.product_id AND pc.variation_id = 0
                WHERE DATE(os.date_created) BETWEEN %s AND %s
                    AND os.status IN ('wc-processing','wc-completed','wc-on-hold')
                    AND {$where}
                GROUP BY opl.product_id, unit_cost
                LIMIT 1
            ";
        }

        $row = $wpdb->get_row( $wpdb->prepare( $sql, $from, $to ), ARRAY_A );

        if ( empty( $row ) ) {
            return null;
        }

        $product     = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
        $cost_status = self::entity_cost_status( $product_id, $variation_id );
        $row['name'] = $product ? $product->get_name() : __( 'Unknown product', WCPI_TEXT_DOMAIN );
        $row['sku']  = $product ? $product->get_sku() : '';
        $row['catalog_price'] = $product ? (float) $product->get_price() : 0.0;
        $row['refund_ratio']  = self::estimate_refund_ratio( $row );
        $health = WCPI_Product_Health_Scorer::score_row(
            array_merge(
                $row,
                array(
                    'cost_status' => $cost_status,
                )
            ),
            array(
                'max_revenue' => (float) $row['revenue'],
                'max_quantity'=> (float) $row['quantity_sold'],
                'period_days' => WCPI_Helpers::range_days( $from, $to ),
            )
        );
        $row['cost_status']       = $cost_status;
        $row['health_score']      = $health['score'];
        $row['health_label']      = $health['label'];
        $row['health_factors']    = $health['factors'];
        $row['avg_selling_price'] = (float) $row['quantity_sold'] > 0 ? (float) $row['revenue'] / (float) $row['quantity_sold'] : 0;

        return $row;
    }

    /**
     * Estimate refund pressure using realized revenue versus configured price.
     *
     * @param array<string, mixed> $row Product row.
     * @return float
     */
    private static function estimate_refund_ratio( array $row ): float {
        $avg_price   = max( 0.0, (float) ( $row['catalog_price'] ?? $row['avg_selling_price'] ?? 0 ) );
        $units       = max( 0.0, (float) ( $row['quantity_sold'] ?? 0 ) );
        $gross_value = $avg_price * $units;

        if ( $gross_value <= 0 ) {
            return 0.0;
        }

        $net_revenue = max( 0.0, (float) ( $row['revenue'] ?? 0 ) );
        $ratio       = max( 0.0, min( 100.0, ( 1 - ( $net_revenue / $gross_value ) ) * 100 ) );

        return round( $ratio, 2 );
    }
}
