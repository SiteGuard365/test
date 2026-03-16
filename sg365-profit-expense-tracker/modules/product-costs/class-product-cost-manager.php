<?php
/**
 * Product cost manager.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Product_Cost_Manager {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_product_costs' ), 20, 2 );
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_costs' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_inline_admin' ) );
    }

    /**
     * Add cost metabox.
     *
     * @return void
     */
    public function add_product_meta_box(): void {
        add_meta_box(
            'wcpi-product-costs',
            __( 'Profit Intelligence: Product Costs', WCPI_TEXT_DOMAIN ),
            array( $this, 'render_product_meta_box' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Enqueue inline admin script.
     *
     * @return void
     */
    public function enqueue_inline_admin(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        wp_add_inline_script(
            'jquery-core',
            'jQuery(function($){$(document).on("input",".wcpi-cost-part",function(){var box=$(this).closest(".wcpi-cost-box,.woocommerce_variation");var sum=0;box.find(".wcpi-cost-part").each(function(){sum+=parseFloat($(this).val()||0);});box.find(".wcpi-total-cost").val(sum.toFixed(6));});});'
        );
    }

    /**
     * Render meta box.
     *
     * @param WP_Post $post Post.
     * @return void
     */
    public function render_product_meta_box( WP_Post $post ): void {
        $data = self::get_cost_row( (int) $post->ID, 0 );
        WCPI_Security::nonce_field( 'wcpi_save_product_costs' );
        ?>
        <div class="wcpi-cost-box">
            <p>
                <label for="wcpi_cost_price"><?php esc_html_e( 'Cost Price', WCPI_TEXT_DOMAIN ); ?></label>
                <input class="widefat wcpi-cost-part" type="number" step="0.000001" min="0" name="wcpi_cost_price" id="wcpi_cost_price" value="<?php echo esc_attr( $data['cost_price'] ); ?>">
            </p>
            <p>
                <label for="wcpi_packaging_cost"><?php esc_html_e( 'Packaging Cost', WCPI_TEXT_DOMAIN ); ?></label>
                <input class="widefat wcpi-cost-part" type="number" step="0.000001" min="0" name="wcpi_packaging_cost" id="wcpi_packaging_cost" value="<?php echo esc_attr( $data['packaging_cost'] ); ?>">
            </p>
            <p>
                <label for="wcpi_handling_cost"><?php esc_html_e( 'Handling Cost', WCPI_TEXT_DOMAIN ); ?></label>
                <input class="widefat wcpi-cost-part" type="number" step="0.000001" min="0" name="wcpi_handling_cost" id="wcpi_handling_cost" value="<?php echo esc_attr( $data['handling_cost'] ); ?>">
            </p>
            <p>
                <label for="wcpi_extra_cost"><?php esc_html_e( 'Extra Cost', WCPI_TEXT_DOMAIN ); ?></label>
                <input class="widefat wcpi-cost-part" type="number" step="0.000001" min="0" name="wcpi_extra_cost" id="wcpi_extra_cost" value="<?php echo esc_attr( $data['extra_cost'] ); ?>">
            </p>
            <p>
                <label for="wcpi_total_unit_cost"><?php esc_html_e( 'Total Unit Cost', WCPI_TEXT_DOMAIN ); ?></label>
                <input class="widefat wcpi-total-cost" type="number" step="0.000001" min="0" name="wcpi_total_unit_cost" id="wcpi_total_unit_cost" value="<?php echo esc_attr( $data['total_unit_cost'] ); ?>">
            </p>
        </div>
        <?php
    }

    /**
     * Save simple product costs.
     *
     * @param int     $post_id Post id.
     * @param WP_Post $post Post.
     * @return void
     */
    public function save_product_costs( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || 'product' !== $post->post_type ) {
            return;
        }

        if ( ! isset( $_POST['_wcpi_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wcpi_nonce'] ) ), 'wcpi_save_product_costs' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        $payload = array(
            'product_id'      => $post_id,
            'variation_id'    => 0,
            'cost_price'      => WCPI_Security::decimal( $_POST['wcpi_cost_price'] ?? 0 ),
            'packaging_cost'  => WCPI_Security::decimal( $_POST['wcpi_packaging_cost'] ?? 0 ),
            'handling_cost'   => WCPI_Security::decimal( $_POST['wcpi_handling_cost'] ?? 0 ),
            'extra_cost'      => WCPI_Security::decimal( $_POST['wcpi_extra_cost'] ?? 0 ),
            'total_unit_cost' => WCPI_Security::decimal( $_POST['wcpi_total_unit_cost'] ?? 0 ),
        );

        if ( empty( $payload['total_unit_cost'] ) ) {
            $payload['total_unit_cost'] = $payload['cost_price'] + $payload['packaging_cost'] + $payload['handling_cost'] + $payload['extra_cost'];
        }

        self::upsert_cost( $payload, 'Manual product edit' );
    }

    /**
     * Render variation fields.
     *
     * @param int     $loop Loop.
     * @param array   $variation_data Data.
     * @param WP_Post $variation Variation.
     * @return void
     */
    public function render_variation_fields( $loop, $variation_data, $variation ): void {
        $data = self::get_cost_row( (int) $variation->post_parent, (int) $variation->ID );
        ?>
        <div class="form-row form-row-full">
            <label><?php esc_html_e( 'WCPI Cost Price', WCPI_TEXT_DOMAIN ); ?></label>
            <input class="short wcpi-cost-part" type="number" step="0.000001" name="wcpi_variation_cost_price[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $data['cost_price'] ); ?>">
        </div>
        <div class="form-row form-row-full">
            <label><?php esc_html_e( 'WCPI Packaging Cost', WCPI_TEXT_DOMAIN ); ?></label>
            <input class="short wcpi-cost-part" type="number" step="0.000001" name="wcpi_variation_packaging_cost[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $data['packaging_cost'] ); ?>">
        </div>
        <div class="form-row form-row-full">
            <label><?php esc_html_e( 'WCPI Handling Cost', WCPI_TEXT_DOMAIN ); ?></label>
            <input class="short wcpi-cost-part" type="number" step="0.000001" name="wcpi_variation_handling_cost[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $data['handling_cost'] ); ?>">
        </div>
        <div class="form-row form-row-full">
            <label><?php esc_html_e( 'WCPI Extra Cost', WCPI_TEXT_DOMAIN ); ?></label>
            <input class="short wcpi-cost-part" type="number" step="0.000001" name="wcpi_variation_extra_cost[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $data['extra_cost'] ); ?>">
        </div>
        <div class="form-row form-row-full">
            <label><?php esc_html_e( 'WCPI Total Unit Cost', WCPI_TEXT_DOMAIN ); ?></label>
            <input class="short wcpi-total-cost" type="number" step="0.000001" name="wcpi_variation_total_unit_cost[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $data['total_unit_cost'] ); ?>">
            <p class="description"><?php esc_html_e( 'Variation cost overrides parent product cost when set.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
        <?php
    }

    /**
     * Save variation costs.
     *
     * @param int $variation_id Variation id.
     * @param int $index Index.
     * @return void
     */
    public function save_variation_costs( int $variation_id, int $index ): void {
        $product_id = (int) wp_get_post_parent_id( $variation_id );
        $payload    = array(
            'product_id'      => $product_id,
            'variation_id'    => $variation_id,
            'cost_price'      => WCPI_Security::decimal( $_POST['wcpi_variation_cost_price'][ $variation_id ] ?? 0 ),
            'packaging_cost'  => WCPI_Security::decimal( $_POST['wcpi_variation_packaging_cost'][ $variation_id ] ?? 0 ),
            'handling_cost'   => WCPI_Security::decimal( $_POST['wcpi_variation_handling_cost'][ $variation_id ] ?? 0 ),
            'extra_cost'      => WCPI_Security::decimal( $_POST['wcpi_variation_extra_cost'][ $variation_id ] ?? 0 ),
            'total_unit_cost' => WCPI_Security::decimal( $_POST['wcpi_variation_total_unit_cost'][ $variation_id ] ?? 0 ),
        );

        if ( empty( $payload['total_unit_cost'] ) ) {
            $payload['total_unit_cost'] = $payload['cost_price'] + $payload['packaging_cost'] + $payload['handling_cost'] + $payload['extra_cost'];
        }

        self::upsert_cost( $payload, 'Variation edit' );
    }

    /**
     * Upsert cost record.
     *
     * @param array<string, mixed> $payload Data.
     * @param string               $reason Reason.
     * @return void
     */
    public static function upsert_cost( array $payload, string $reason = '' ): void {
        global $wpdb;
        $table = WCPI_DB::table( 'product_costs' );

        $existing = self::get_cost_row( (int) $payload['product_id'], (int) $payload['variation_id'] );

        $payload['currency']   = get_option( 'woocommerce_currency', 'USD' );
        $payload['updated_by'] = get_current_user_id();
        $payload['updated_at'] = current_time( 'mysql', true );

        if ( empty( $existing['id'] ) ) {
            $payload['created_at'] = current_time( 'mysql', true );

            $wpdb->insert(
                $table,
                $payload,
                array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s' )
            );
        } else {
            $wpdb->update(
                $table,
                $payload,
                array( 'id' => (int) $existing['id'] ),
                array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s' ),
                array( '%d' )
            );
        }

        WCPI_Cost_History::add(
            (int) $payload['product_id'],
            (int) $payload['variation_id'],
            (float) $existing['total_unit_cost'],
            (float) $payload['total_unit_cost'],
            $reason
        );

        WCPI_Cache::flush_group();
    }

    /**
     * Get cost row.
     *
     * @param int $product_id Product id.
     * @param int $variation_id Variation id.
     * @return array<string, mixed>
     */
    public static function get_cost_row( int $product_id, int $variation_id = 0 ): array {
        global $wpdb;
        $table = WCPI_DB::table( 'product_costs' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d AND variation_id = %d LIMIT 1",
                $product_id,
                $variation_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return array(
                'id'             => 0,
                'product_id'     => $product_id,
                'variation_id'   => $variation_id,
                'cost_price'     => 0,
                'packaging_cost' => 0,
                'handling_cost'  => 0,
                'extra_cost'     => 0,
                'total_unit_cost'=> 0,
            );
        }

        return $row;
    }

    /**
     * Get unit cost with variation override.
     *
     * @param int $product_id Product.
     * @param int $variation_id Variation.
     * @return float
     */
    public static function get_unit_cost( int $product_id, int $variation_id = 0 ): float {
        if ( $variation_id > 0 ) {
            $variation = self::get_cost_row( $product_id, $variation_id );
            if ( ! empty( $variation['id'] ) ) {
                return (float) $variation['total_unit_cost'];
            }
        }

        $product = self::get_cost_row( $product_id, 0 );
        return (float) $product['total_unit_cost'];
    }
}
