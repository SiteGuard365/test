<?php
/**
 * Lightweight loader class for future extension.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Loader {

    /**
     * Register an action.
     *
     * @param string   $hook Hook.
     * @param object   $component Component.
     * @param string   $callback Callback.
     * @param int      $priority Priority.
     * @param int      $accepted_args Accepted args.
     * @return void
     */
    public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        add_action( $hook, array( $component, $callback ), $priority, $accepted_args );
    }

    /**
     * Register a filter.
     *
     * @param string   $hook Hook.
     * @param object   $component Component.
     * @param string   $callback Callback.
     * @param int      $priority Priority.
     * @param int      $accepted_args Accepted args.
     * @return void
     */
    public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        add_filter( $hook, array( $component, $callback ), $priority, $accepted_args );
    }
}
