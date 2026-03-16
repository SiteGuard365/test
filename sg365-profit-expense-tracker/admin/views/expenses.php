<?php
defined( 'ABSPATH' ) || exit;

$edit = $edit ?: array(
    'id' => 0,
    'expense_name' => '',
    'category' => 'miscellaneous',
    'amount' => '',
    'expense_date' => gmdate( 'Y-m-d' ),
    'notes' => '',
    'attachment_path' => '',
);

$template_edit = $template_edit ?: array(
    'id' => 0,
    'name' => '',
    'category' => 'miscellaneous',
    'amount' => '',
    'frequency' => 'monthly',
    'start_date' => gmdate( 'Y-m-d' ),
    'end_date' => '',
    'notes' => '',
    'status' => 'active',
);

$allowed_tabs = array( 'overview', 'add-expense', 'recurring', 'ledger' );
$active_tab   = WCPI_Security::text( $_GET['tab'] ?? '' );
if ( ! $active_tab ) {
    if ( ! empty( $template_edit['id'] ) || isset( $_GET['recurring_updated'] ) || isset( $_GET['recurring_deleted'] ) ) {
        $active_tab = 'recurring';
    } elseif ( ! empty( $edit['id'] ) || isset( $_GET['updated'] ) ) {
        $active_tab = 'add-expense';
    } elseif ( ! empty( $filters['search'] ) || ! empty( $filters['category'] ) || ! empty( $filters['source_type'] ) || ! empty( $filters['from'] ) || ! empty( $filters['to'] ) || isset( $_GET['deleted'] ) || $filters['paged'] > 1 ) {
        $active_tab = 'ledger';
    } else {
        $active_tab = 'overview';
    }
}
$active_tab = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'overview';
$currency   = WCPI_Helpers::currency_context();
?>
<div class="wrap wcpi-shell" id="wcpi-expenses-page">
    <div class="wcpi-page-head">
        <div>
            <h1><?php esc_html_e( 'Expenses', WCPI_TEXT_DOMAIN ); ?></h1>
            <p><?php esc_html_e( 'Manage expenses, recurring templates, and your ledger in shorter SaaS-style workspaces instead of one long page.', WCPI_TEXT_DOMAIN ); ?></p>
        </div>
        <div class="wcpi-head-meta">
            <span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( WCPI_Helpers::range_label( $filters['from'] ?: gmdate( 'Y-m-01' ), $filters['to'] ?: gmdate( 'Y-m-d' ) ) ); ?></span>
            <span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( $currency['code'] ); ?></span>
        </div>
    </div>

    <section class="wcpi-tabs-shell wcpi-tabs-shell-soft" id="wcpi-expenses-tabs" data-auto-tabs="yes" data-query-key="tab" data-default-tab="overview" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
        <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Expense sections', WCPI_TEXT_DOMAIN ); ?>">
            <button type="button" class="wcpi-tab-button" data-tab-target="overview" role="tab"><span class="dashicons dashicons-chart-bar"></span><span><?php esc_html_e( 'Overview', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="add-expense" role="tab"><span class="dashicons dashicons-plus-alt2"></span><span><?php esc_html_e( 'Add Expense', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="recurring" role="tab"><span class="dashicons dashicons-backup"></span><span><?php esc_html_e( 'Recurring', WCPI_TEXT_DOMAIN ); ?></span></button>
            <button type="button" class="wcpi-tab-button" data-tab-target="ledger" role="tab"><span class="dashicons dashicons-media-spreadsheet"></span><span><?php esc_html_e( 'Ledger', WCPI_TEXT_DOMAIN ); ?></span></button>
        </div>

        <div class="wcpi-tab-panels">
            <section class="wcpi-tab-panel" data-tab-panel="overview" aria-hidden="true">
                <div id="wcpi-expenses-summary">
                    <div class="wcpi-stat-grid wcpi-stat-grid-5">
                        <section class="wcpi-stat-card wcpi-card-primary"><span class="wcpi-stat-label"><?php esc_html_e( 'This Month Expenses', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $summary['this_period_total'] ) ); ?></strong></section>
                        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Largest Category', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo esc_html( $summary['largest_category_label'] ?? '' ); ?></strong></section>
                        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Last Added Expense', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo esc_html( $summary['latest']['expense_name'] ?? __( 'No expenses yet', WCPI_TEXT_DOMAIN ) ); ?></strong></section>
                        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Recurring Total', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $summary['recurring_total'] ) ); ?></strong></section>
                        <section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label"><?php esc_html_e( 'Expense Entries', WCPI_TEXT_DOMAIN ); ?></span><strong class="wcpi-stat-value"><?php echo esc_html( number_format_i18n( (int) $summary['entries'] ) ); ?></strong></section>
                    </div>
                </div>

                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php esc_html_e( 'Category Snapshot', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'A quick scan of top expense categories and current budget pressure.', WCPI_TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                        <div class="wcpi-budget-list">
                            <?php foreach ( array_slice( $summary['breakdown'], 0, 6 ) as $row ) : ?>
                                <div class="wcpi-budget-row">
                                    <div>
                                        <strong><?php echo esc_html( $row['display_name'] ?? ( WCPI_Helpers::expense_categories()[ $row['category'] ] ?? $row['category'] ) ); ?></strong>
                                        <small><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $row['total'] ) ); ?></small>
                                    </div>
                                    <span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( strtoupper( substr( (string) ( $row['category'] ?? '' ), 0, 3 ) ) ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php esc_html_e( 'Budget Snapshot', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'The most important over-budget categories are surfaced first.', WCPI_TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                        <div class="wcpi-budget-list">
                            <?php if ( empty( $summary['budget_vs_actual'] ) ) : ?>
                                <div class="wcpi-empty-inline"><?php esc_html_e( 'No budget entries yet.', WCPI_TEXT_DOMAIN ); ?></div>
                            <?php else : ?>
                                <?php foreach ( array_slice( $summary['budget_vs_actual'], 0, 6 ) as $row ) : ?>
                                    <div class="wcpi-budget-row">
                                        <div>
                                            <strong><?php echo esc_html( $row['display_name'] ?? ( WCPI_Helpers::expense_categories()[ $row['category'] ] ?? $row['category'] ) ); ?></strong>
                                            <small><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $row['total'] ) ); ?> / <?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $row['budget'] ) ); ?></small>
                                        </div>
                                        <span class="wcpi-badge wcpi-badge-<?php echo esc_attr( (float) $row['variance'] > 0 ? 'warning' : 'success' ); ?>">
                                            <?php echo esc_html( (float) $row['variance'] > 0 ? __( 'Over', WCPI_TEXT_DOMAIN ) : __( 'Under', WCPI_TEXT_DOMAIN ) ); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="add-expense" aria-hidden="true">
                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php echo $edit['id'] ? esc_html__( 'Edit Expense', WCPI_TEXT_DOMAIN ) : esc_html__( 'Add Expense', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php echo esc_html( sprintf( __( 'All amounts use %s formatting and save through AJAX for a faster admin flow.', WCPI_TEXT_DOMAIN ), $currency['code'] ) ); ?></p>
                            </div>
                        </div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="wcpi-form-grid" id="wcpi-expense-form">
                            <input type="hidden" name="action" value="wcpi_save_expense">
                            <input type="hidden" name="expense_id" value="<?php echo esc_attr( $edit['id'] ); ?>">
                            <input type="hidden" name="existing_attachment" value="<?php echo esc_attr( $edit['attachment_path'] ); ?>">
                            <?php WCPI_Security::nonce_field( 'wcpi_save_expense' ); ?>
                            <label><span><?php esc_html_e( 'Expense Name', WCPI_TEXT_DOMAIN ); ?></span><input type="text" name="expense_name" value="<?php echo esc_attr( $edit['expense_name'] ); ?>" required></label>
                            <label><span><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></span><select name="category"><?php foreach ( WCPI_Helpers::expense_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <div class="wcpi-chip-row wcpi-span-2"><?php foreach ( array_slice( WCPI_Helpers::expense_categories(), 0, 6, true ) as $key => $label ) : ?><button type="button" class="button wcpi-category-chip" data-category="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button><?php endforeach; ?></div>
                            <label><span><?php esc_html_e( 'Amount', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.000001" min="0" name="amount" value="<?php echo esc_attr( $edit['amount'] ); ?>" required></label>
                            <label><span><?php esc_html_e( 'Expense Date', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="expense_date" value="<?php echo esc_attr( $edit['expense_date'] ); ?>" required></label>
                            <label class="wcpi-span-2"><span><?php esc_html_e( 'Notes', WCPI_TEXT_DOMAIN ); ?></span><textarea name="notes" rows="4"><?php echo esc_textarea( $edit['notes'] ); ?></textarea></label>
                            <label class="wcpi-span-2"><span><?php esc_html_e( 'Receipt Attachment', WCPI_TEXT_DOMAIN ); ?></span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"><small><?php echo ! empty( $edit['attachment_path'] ) ? esc_html( basename( $edit['attachment_path'] ) ) : esc_html__( 'Upload a receipt or invoice for audit traceability.', WCPI_TEXT_DOMAIN ); ?></small></label>
                            <div class="wcpi-span-2"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save Expense', WCPI_TEXT_DOMAIN ); ?></button></div>
                        </form>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php esc_html_e( 'Category Totals and Budgets', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'Review category spend and save monthly budgets without leaving the add-expense workspace.', WCPI_TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                        <div id="wcpi-expenses-budget-panel">
                            <?php if ( empty( $summary['budget_vs_actual'] ) ) : ?>
                                <div class="wcpi-empty-state"><span class="dashicons dashicons-chart-bar"></span><h3><?php esc_html_e( 'No expense data yet', WCPI_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Start recording business costs to see category trends and budget tracking.', WCPI_TEXT_DOMAIN ); ?></p></div>
                            <?php else : ?>
                                <div class="wcpi-budget-list">
                                    <?php foreach ( $summary['budget_vs_actual'] as $row ) : ?>
                                        <div class="wcpi-budget-row">
                                            <div><strong><?php echo esc_html( $row['display_name'] ?? ( WCPI_Helpers::expense_categories()[ $row['category'] ] ?? $row['category'] ) ); ?></strong><small><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $row['total'] ) ); ?> / <?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $row['budget'] ) ); ?></small></div>
                                            <span class="wcpi-badge wcpi-badge-<?php echo esc_attr( (float) $row['variance'] <= 0 ? 'success' : 'warning' ); ?>">
                                                <?php echo esc_html( (float) $row['variance'] > 0 ? __( 'Over', WCPI_TEXT_DOMAIN ) : __( 'Under', WCPI_TEXT_DOMAIN ) ); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcpi-inline-form" id="wcpi-budget-form">
                            <input type="hidden" name="action" value="wcpi_save_expense_budget">
                            <?php WCPI_Security::nonce_field( 'wcpi_save_expense_budget' ); ?>
                            <select name="category"><?php foreach ( WCPI_Helpers::expense_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                            <input type="number" step="0.000001" name="budget_amount" placeholder="<?php esc_attr_e( 'Monthly budget amount', WCPI_TEXT_DOMAIN ); ?>">
                            <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Budget', WCPI_TEXT_DOMAIN ); ?></button>
                        </form>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="recurring" aria-hidden="true">
                <div class="wcpi-grid wcpi-grid-2">
                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php echo ! empty( $template_edit['id'] ) ? esc_html__( 'Edit Recurring Expense', WCPI_TEXT_DOMAIN ) : esc_html__( 'Recurring Expenses Manager', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'Build recurring templates for rent, software, salaries, and other predictable operating costs.', WCPI_TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcpi-form-grid">
                            <input type="hidden" name="action" value="wcpi_save_recurring_expense">
                            <input type="hidden" name="template_id" value="<?php echo esc_attr( $template_edit['id'] ); ?>">
                            <?php WCPI_Security::nonce_field( 'wcpi_save_recurring_expense' ); ?>
                            <label><span><?php esc_html_e( 'Name', WCPI_TEXT_DOMAIN ); ?></span><input type="text" name="name" value="<?php echo esc_attr( $template_edit['name'] ); ?>" required></label>
                            <label><span><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></span><select name="category"><?php foreach ( WCPI_Helpers::expense_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $template_edit['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <label><span><?php esc_html_e( 'Amount', WCPI_TEXT_DOMAIN ); ?></span><input type="number" step="0.000001" name="amount" value="<?php echo esc_attr( $template_edit['amount'] ); ?>" required></label>
                            <label><span><?php esc_html_e( 'Frequency', WCPI_TEXT_DOMAIN ); ?></span><select name="frequency"><?php foreach ( WCPI_Recurring_Expense_Manager::allowed_frequencies() as $frequency ) : ?><option value="<?php echo esc_attr( $frequency ); ?>" <?php selected( $template_edit['frequency'], $frequency ); ?>><?php echo esc_html( ucfirst( $frequency ) ); ?></option><?php endforeach; ?></select></label>
                            <label><span><?php esc_html_e( 'Start Date', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="start_date" value="<?php echo esc_attr( $template_edit['start_date'] ); ?>" required></label>
                            <label><span><?php esc_html_e( 'End Date', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="end_date" value="<?php echo esc_attr( $template_edit['end_date'] ); ?>"></label>
                            <label><span><?php esc_html_e( 'Status', WCPI_TEXT_DOMAIN ); ?></span><select name="status"><option value="active" <?php selected( $template_edit['status'], 'active' ); ?>><?php esc_html_e( 'Active', WCPI_TEXT_DOMAIN ); ?></option><option value="inactive" <?php selected( $template_edit['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', WCPI_TEXT_DOMAIN ); ?></option></select></label>
                            <label class="wcpi-span-2"><span><?php esc_html_e( 'Notes', WCPI_TEXT_DOMAIN ); ?></span><textarea name="notes" rows="3"><?php echo esc_textarea( $template_edit['notes'] ); ?></textarea></label>
                            <div class="wcpi-span-2"><button class="button button-primary"><?php echo ! empty( $template_edit['id'] ) ? esc_html__( 'Update Recurring Template', WCPI_TEXT_DOMAIN ) : esc_html__( 'Save Recurring Template', WCPI_TEXT_DOMAIN ); ?></button></div>
                        </form>
                    </section>

                    <section class="wcpi-panel">
                        <div class="wcpi-panel-head">
                            <div>
                                <h2><?php esc_html_e( 'Recurring Templates', WCPI_TEXT_DOMAIN ); ?></h2>
                                <p><?php esc_html_e( 'Status badges and direct actions keep recurring expense management cleaner.', WCPI_TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                        <?php if ( empty( $templates ) ) : ?>
                            <div class="wcpi-empty-state"><span class="dashicons dashicons-backup"></span><h3><?php esc_html_e( 'No recurring expenses yet', WCPI_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Add rent, salaries, software, or utilities so they appear automatically in profit reporting.', WCPI_TEXT_DOMAIN ); ?></p></div>
                        <?php else : ?>
                            <div class="wcpi-template-list">
                                <?php foreach ( $templates as $template ) : ?>
                                    <article class="wcpi-template-card">
                                        <div>
                                            <strong><?php echo esc_html( $template['name'] ); ?></strong>
                                            <small><?php echo esc_html( ucfirst( $template['frequency'] ) ); ?> - <?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $template['amount'] ) ); ?></small>
                                        </div>
                                        <div class="wcpi-inline-tools">
                                            <span class="wcpi-badge wcpi-badge-<?php echo esc_attr( 'active' === $template['status'] ? 'success' : 'neutral' ); ?>"><?php echo esc_html( ucfirst( $template['status'] ) ); ?></span>
                                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-expenses&tab=recurring&edit_template=' . absint( $template['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', WCPI_TEXT_DOMAIN ); ?></a>
                                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcpi_toggle_recurring_expense&template_id=' . absint( $template['id'] ) ), 'wcpi_toggle_recurring_expense', '_wcpi_nonce' ) ); ?>"><?php esc_html_e( 'Toggle', WCPI_TEXT_DOMAIN ); ?></a>
                                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcpi_delete_recurring_expense&template_id=' . absint( $template['id'] ) ), 'wcpi_delete_recurring_expense', '_wcpi_nonce' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this recurring template? Existing generated expenses will stay for historical reporting.', WCPI_TEXT_DOMAIN ) ); ?>');"><?php esc_html_e( 'Delete', WCPI_TEXT_DOMAIN ); ?></a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>

            <section class="wcpi-tab-panel" data-tab-panel="ledger" aria-hidden="true">
                <form method="get" class="wcpi-toolbar-card">
                    <input type="hidden" name="page" value="wcpi-expenses">
                    <input type="hidden" name="tab" value="ledger">
                    <input type="hidden" name="paged" id="wcpi-expense-filter-paged" value="<?php echo esc_attr( $filters['paged'] ); ?>">
                    <div class="wcpi-filter-grid">
                        <label><span><?php esc_html_e( 'Search', WCPI_TEXT_DOMAIN ); ?></span><input type="search" name="s" id="wcpi-expense-filter-search" value="<?php echo esc_attr( $filters['search'] ); ?>"></label>
                        <label><span><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></span><select name="category" id="wcpi-expense-filter-category"><option value=""><?php esc_html_e( 'All', WCPI_TEXT_DOMAIN ); ?></option><?php foreach ( WCPI_Helpers::expense_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Source', WCPI_TEXT_DOMAIN ); ?></span><select name="source_type" id="wcpi-expense-filter-source"><option value=""><?php esc_html_e( 'All', WCPI_TEXT_DOMAIN ); ?></option><option value="manual" <?php selected( $filters['source_type'], 'manual' ); ?>><?php esc_html_e( 'Manual', WCPI_TEXT_DOMAIN ); ?></option><option value="recurring" <?php selected( $filters['source_type'], 'recurring' ); ?>><?php esc_html_e( 'Recurring', WCPI_TEXT_DOMAIN ); ?></option></select></label>
                        <label><span><?php esc_html_e( 'From', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="from" id="wcpi-expense-filter-from" value="<?php echo esc_attr( $filters['from'] ); ?>"></label>
                        <label><span><?php esc_html_e( 'To', WCPI_TEXT_DOMAIN ); ?></span><input type="date" name="to" id="wcpi-expense-filter-to" value="<?php echo esc_attr( $filters['to'] ); ?>"></label>
                        <div class="wcpi-toolbar-actions wcpi-span-full">
                            <button class="button button-primary"><?php esc_html_e( 'Filter', WCPI_TEXT_DOMAIN ); ?></button>
                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-expenses&tab=ledger' ) ); ?>"><?php esc_html_e( 'Reset', WCPI_TEXT_DOMAIN ); ?></a>
                        </div>
                    </div>
                </form>

                <section class="wcpi-panel">
                    <div class="wcpi-panel-head">
                        <div>
                            <h2><?php esc_html_e( 'Expense Entries', WCPI_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Your expense ledger with category, source, amount, date, and attachment visibility.', WCPI_TEXT_DOMAIN ); ?></p>
                        </div>
                    </div>
                    <div id="wcpi-expenses-table">
                        <div class="wcpi-table-wrap">
                            <table class="widefat wcpi-table wcpi-table-sticky">
                                <thead><tr><th><?php esc_html_e( 'Name', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Source', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Amount', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Date', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Attachment', WCPI_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Actions', WCPI_TEXT_DOMAIN ); ?></th></tr></thead>
                                <tbody>
                                    <?php if ( empty( $query['items'] ) ) : ?>
                                        <tr><td colspan="7"><div class="wcpi-empty-state"><span class="dashicons dashicons-money-alt"></span><h3><?php esc_html_e( 'No expenses added yet', WCPI_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Start recording business costs to see true store profit.', WCPI_TEXT_DOMAIN ); ?></p></div></td></tr>
                                    <?php else : ?>
                                        <?php foreach ( $query['items'] as $item ) : ?>
                                            <tr>
                                                <td><?php echo esc_html( $item['expense_name'] ); ?><br><small><?php echo esc_html( wp_trim_words( $item['notes'], 14 ) ); ?></small></td>
                                                <td><?php echo esc_html( $item['category_label'] ?? ( WCPI_Helpers::expense_categories()[ $item['category'] ] ?? $item['category'] ) ); ?></td>
                                                <td><span class="wcpi-badge wcpi-badge-neutral"><?php echo esc_html( $item['source_label'] ?? WCPI_Helpers::expense_source_label( $item['source_type'] ) ); ?></span></td>
                                                <td><?php echo wp_kses_post( WCPI_Helpers::format_price( (float) $item['amount'] ) ); ?></td>
                                                <td><?php echo esc_html( $item['expense_date'] ); ?></td>
                                                <td><?php echo ! empty( $item['attachment_path'] ) ? '<span>' . esc_html__( 'Uploaded', WCPI_TEXT_DOMAIN ) . '</span>' : '<span class="wcpi-muted">' . esc_html__( 'None', WCPI_TEXT_DOMAIN ) . '</span>'; ?></td>
                                                <td><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-expenses&tab=add-expense&edit_expense=' . absint( $item['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', WCPI_TEXT_DOMAIN ); ?></a> <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcpi_delete_expense&expense_id=' . absint( $item['id'] ) ), 'wcpi_delete_expense', '_wcpi_nonce' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this expense?', WCPI_TEXT_DOMAIN ) ); ?>');"><?php esc_html_e( 'Delete', WCPI_TEXT_DOMAIN ); ?></a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ( $query['total_pages'] > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'paged' => '%#%', 'tab' => 'ledger' ) ), 'format' => '', 'current' => $filters['paged'], 'total' => $query['total_pages'], 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ) ); ?></div></div><?php endif; ?>
                </section>
            </section>
        </div>
    </section>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
</div>
