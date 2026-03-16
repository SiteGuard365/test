<?php
/**
 * Security helpers.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Security {

    /**
     * Check capability.
     *
     * @return void
     */
    public static function verify_access(): void {
        if ( ! current_user_can( WCPI_Helpers::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to access this resource.', WCPI_TEXT_DOMAIN ) );
        }
    }

    /**
     * Nonce field.
     *
     * @param string $action Action.
     * @return void
     */
    public static function nonce_field( string $action ): void {
        wp_nonce_field( $action, '_wcpi_nonce' );
    }

    /**
     * Verify nonce from request.
     *
     * @param string $action Action.
     * @return void
     */
    public static function verify_nonce( string $action ): void {
        $nonce = isset( $_REQUEST['_wcpi_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wcpi_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Security check failed.', WCPI_TEXT_DOMAIN ) );
        }
    }

    /**
     * Sanitize decimal.
     *
     * @param mixed $value Value.
     * @return float
     */
    public static function decimal( $value ): float {
        return round( (float) wc_format_decimal( wp_unslash( (string) $value ) ), 6 );
    }

    /**
     * Sanitize plain text.
     *
     * @param mixed $value Value.
     * @return string
     */
    public static function text( $value ): string {
        return sanitize_text_field( wp_unslash( (string) $value ) );
    }

    /**
     * Sanitize textarea.
     *
     * @param mixed $value Value.
     * @return string
     */
    public static function textarea( $value ): string {
        return sanitize_textarea_field( wp_unslash( (string) $value ) );
    }

    /**
     * Sanitize Y-m-d date.
     *
     * @param mixed $value Value.
     * @return string
     */
    public static function date( $value ): string {
        $value = sanitize_text_field( wp_unslash( (string) $value ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return gmdate( 'Y-m-d' );
        }
        return $value;
    }
}
