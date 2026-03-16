<?php
/**
 * Internal REST routes for future extension.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_REST {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    /**
     * Register endpoints.
     *
     * @return void
     */
    public function routes(): void {
        register_rest_route(
            'wcpi/v1',
            '/summary',
            array(
                'methods'             => 'GET',
                'permission_callback' => static function () {
                    return current_user_can( WCPI_Helpers::capability() );
                },
                'callback'            => array( $this, 'summary' ),
                'args'                => array(
                    'from' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'to'   => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * REST summary response.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function summary( WP_REST_Request $request ): WP_REST_Response {
        $from = WCPI_Security::date( $request->get_param( 'from' ) ?: gmdate( 'Y-m-01' ) );
        $to   = WCPI_Security::date( $request->get_param( 'to' ) ?: gmdate( 'Y-m-d' ) );

        return new WP_REST_Response( WCPI_Analytics_Engine::dashboard( $from, $to ) );
    }
}
