<?php
/**
 * Expenses page.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Expenses_Page {

    /**
     * Render page.
     *
     * @return void
     */
    public static function render(): void {
        WCPI_Security::verify_access();
        $range = WCPI_Helpers::resolve_date_range( 'this_month' );

        $filters = array(
            'category' => WCPI_Security::text( $_GET['category'] ?? '' ),
            'source_type' => WCPI_Security::text( $_GET['source_type'] ?? '' ),
            'search'   => WCPI_Security::text( $_GET['s'] ?? '' ),
            'from'     => WCPI_Security::text( $_GET['from'] ?? '' ),
            'to'       => WCPI_Security::text( $_GET['to'] ?? '' ),
            'paged'    => max( 1, absint( $_GET['paged'] ?? 1 ) ),
            'per_page' => 20,
        );

        $query = WCPI_Expense_Manager::query( $filters );
        $edit  = absint( $_GET['edit_expense'] ?? 0 ) ? WCPI_Expense_Manager::get( absint( $_GET['edit_expense'] ) ) : null;
        $template_edit = absint( $_GET['edit_template'] ?? 0 ) ? WCPI_Recurring_Expense_Manager::get( absint( $_GET['edit_template'] ) ) : null;
        $summary       = WCPI_Expense_Manager::summary( $filters['from'] ?: $range['from'], $filters['to'] ?: $range['to'] );
        $templates     = WCPI_Recurring_Expense_Manager::all();
        $budgets       = WCPI_Recurring_Expense_Manager::budgets();

        include WCPI_PLUGIN_DIR . 'admin/views/expenses.php';
    }
}
