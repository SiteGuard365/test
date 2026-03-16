<?php
defined( 'ABSPATH' ) || exit;

$compare_enabled   = ! empty( $data['comparison_enabled'] );
$compare_label     = ! empty( $data['previous_period']['days'] ) ? sprintf( __( 'vs previous %s days', WCPI_TEXT_DOMAIN ), $data['previous_period']['days'] ) : __( 'Comparison disabled', WCPI_TEXT_DOMAIN );
$allowed_tabs      = array( 'executive', 'recommendations', 'products', 'planning' );
$active_tab        = WCPI_Security::text( $_GET['tab'] ?? 'executive' );
$active_tab        = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'executive';
$expense_categories = WCPI_Helpers::expense_categories();
$note_categories   = WCPI_Helpers::dashboard_note_categories();
$note_colors       = WCPI_Helpers::dashboard_note_colors();
$currency          = WCPI_Helpers::currency_context();
$active_notes      = $data['active_notes'] ?? array();
$active_note_count = count( $active_notes );
$current_user      = wp_get_current_user();
$default_email     = $current_user instanceof WP_User && ! empty( $current_user->user_email ) ? $current_user->user_email : get_option( 'admin_email', '' );
$same_year_range   = wp_date( 'Y', strtotime( $data['from'] ) ) === wp_date( 'Y', strtotime( $data['to'] ) );
$header_range_label = $same_year_range
    ? wp_date( 'M j', strtotime( $data['from'] ) ) . ' - ' . wp_date( 'M j, Y', strtotime( $data['to'] ) )
    : wp_date( 'M j, Y', strtotime( $data['from'] ) ) . ' - ' . wp_date( 'M j, Y', strtotime( $data['to'] ) );
$header_compare_label = ! empty( $data['previous_period']['days'] ) ? sprintf( __( 'vs prev. %s days', WCPI_TEXT_DOMAIN ), $data['previous_period']['days'] ) : __( 'Compare off', WCPI_TEXT_DOMAIN );
$header_refreshed_label = sprintf( __( 'Refreshed: %s', WCPI_TEXT_DOMAIN ), current_time( 'M j, Y, g:i:s A' ) );
?>
<div class="wrap wcpi-shell wcpi-shell-hero" id="wcpi-dashboard-page">
    <section class="wcpi-hero wcpi-overview-hero wcpi-overview-hero-light">
        <div class="wcpi-overview-head">
            <div class="wcpi-overview-copy">
                <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Profit Intelligence', WCPI_TEXT_DOMAIN ); ?></p>
                <h1><?php esc_html_e( 'Overview', WCPI_TEXT_DOMAIN ); ?></h1>
                <p><?php esc_html_e( 'Actionable insights with real-time profitability tracking, sales trends, and advanced planning analytics.', WCPI_TEXT_DOMAIN ); ?></p>
            </div>

            <div class="wcpi-overview-apps">
                <button type="button" class="wcpi-app-tile wcpi-app-tile-primary" data-wcpi-modal-open="expense">
                    <span class="wcpi-app-icon dashicons dashicons-money-alt"></span>
                    <span class="wcpi-app-copy">
                        <strong><?php esc_html_e( 'Add Expense', WCPI_TEXT_DOMAIN ); ?></strong>
                    </span>
                </button>
                <button type="button" class="wcpi-app-tile" data-wcpi-modal-open="note">
                    <span class="wcpi-app-icon dashicons dashicons-edit-page"></span>
                    <span class="wcpi-app-copy">
                        <strong><?php esc_html_e( 'Add Note', WCPI_TEXT_DOMAIN ); ?></strong>
                    </span>
                </button>
                <a class="wcpi-app-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-reports&tab=executive' ) ); ?>">
                    <span class="wcpi-app-icon dashicons dashicons-chart-line"></span>
                    <span class="wcpi-app-copy">
                        <strong><?php esc_html_e( 'Reports', WCPI_TEXT_DOMAIN ); ?></strong>
                    </span>
                </a>
                <a class="wcpi-app-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-export&tab=generate' ) ); ?>">
                    <span class="wcpi-app-icon dashicons dashicons-download"></span>
                    <span class="wcpi-app-copy">
                        <strong><?php esc_html_e( 'Export', WCPI_TEXT_DOMAIN ); ?></strong>
                    </span>
                </a>
                <a class="wcpi-app-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-settings&tab=general' ) ); ?>">
                    <span class="wcpi-app-icon dashicons dashicons-admin-generic"></span>
                    <span class="wcpi-app-copy">
                        <strong><?php esc_html_e( 'Settings', WCPI_TEXT_DOMAIN ); ?></strong>
                    </span>
                </a>
            </div>
        </div>

        <div class="wcpi-overview-meta-bar">
            <span class="wcpi-overview-meta-item"><span class="dashicons dashicons-calendar-alt"></span><span id="wcpi-dashboard-range-label"><?php echo esc_html( $header_range_label ); ?></span></span>
            <span class="wcpi-overview-meta-divider"></span>
            <span class="wcpi-overview-meta-item"><span class="wcpi-overview-meta-symbol" aria-hidden="true">%</span><span id="wcpi-dashboard-compare-label"><?php echo esc_html( $header_compare_label ); ?></span></span>
            <span class="wcpi-overview-meta-divider"></span>
            <span class="wcpi-overview-meta-item"><span class="wcpi-currency-pill"><?php echo esc_html( $currency['code'] ); ?></span><span><?php echo esc_html( $currency['code'] ); ?></span></span>
            <span class="wcpi-overview-meta-divider"></span>
            <span class="wcpi-overview-meta-item"><span class="dashicons dashicons-clock"></span><span id="wcpi-dashboard-last-updated"><?php echo esc_html( $header_refreshed_label ); ?></span></span>
        </div>

        <div class="wcpi-overview-controls-card">
            <div class="wcpi-overview-controls-grid">
                <label class="wcpi-overview-field">
                    <span><?php esc_html_e( 'Preset', WCPI_TEXT_DOMAIN ); ?></span>
                    <select id="dashboard-preset">
                        <?php foreach ( WCPI_Helpers::date_presets() as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( WCPI_Security::text( $_GET['preset'] ?? WCPI_Settings_Manager::get( 'general', 'default_dashboard_range', 'last_30_days' ) ), $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="wcpi-overview-field wcpi-overview-field-wide">
                    <span><?php esc_html_e( 'Preset', WCPI_TEXT_DOMAIN ); ?></span>
                    <div class="wcpi-overview-segmented">
                        <button class="button wcpi-range-chip" data-preset="last_7_days">7D</button>
                        <button class="button wcpi-range-chip" data-preset="last_30_days">30D</button>
                        <button class="button wcpi-range-chip" data-preset="this_month">MTD</button>
                        <button class="button wcpi-range-chip" data-preset="this_quarter">QTD</button>
                        <button class="button wcpi-range-chip" data-preset="this_year">YTD</button>
                    </div>
                </div>

                <label class="wcpi-overview-field">
                    <span><?php esc_html_e( 'From', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="date" id="dashboard-from" value="<?php echo esc_attr( $data['from'] ); ?>">
                </label>

                <label class="wcpi-overview-field">
                    <span><?php esc_html_e( 'To', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="date" id="dashboard-to" value="<?php echo esc_attr( $data['to'] ); ?>">
                </label>

                <label class="wcpi-overview-field wcpi-toggle wcpi-toggle-light">
                    <span><?php esc_html_e( 'Compare Previous Period', WCPI_TEXT_DOMAIN ); ?></span>
                    <input type="checkbox" id="dashboard-compare" <?php checked( $compare_enabled ); ?>>
                </label>

                <div class="wcpi-toolbar-actions wcpi-overview-actions">
                    <button class="button button-primary" id="dashboard-apply"><?php esc_html_e( 'Apply', WCPI_TEXT_DOMAIN ); ?></button>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wcpi-dashboard' ) ); ?>"><?php esc_html_e( 'Reset', WCPI_TEXT_DOMAIN ); ?></a>
                    <button class="button" id="wcpi-rebuild-trigger"><?php esc_html_e( 'Refresh', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>

        <div class="wcpi-active-notes-strip wcpi-active-notes-count-<?php echo esc_attr( max( 1, min( 4, $active_note_count ) ) ); ?>" id="wcpi-dashboard-active-notes"<?php echo empty( $active_notes ) ? ' hidden' : ''; ?>>
            <?php foreach ( array_slice( $active_notes, 0, 4 ) as $note ) : ?>
                <article
                    class="wcpi-active-note wcpi-note-theme-<?php echo esc_attr( $note['note_color'] ?? 'blue' ); ?>"
                    data-note-id="<?php echo esc_attr( $note['id'] ?? 0 ); ?>"
                    data-note-status="<?php echo esc_attr( $note['note_status'] ?? 'active' ); ?>"
                >
                    <div class="wcpi-active-note-top">
                        <strong><?php echo esc_html( $note['note_title'] ?? '' ); ?></strong>
                        <button
                            type="button"
                            class="wcpi-note-menu"
                            aria-label="<?php esc_attr_e( 'Note actions', WCPI_TEXT_DOMAIN ); ?>"
                            data-note="<?php echo esc_attr( rawurlencode( wp_json_encode( $note ) ) ); ?>"
                        ><span class="dashicons dashicons-ellipsis"></span></button>
                    </div>
                    <p><?php echo esc_html( $note['note_body'] ?? '' ); ?></p>
                    <div class="wcpi-active-note-footer">
                        <small><?php echo esc_html( $note['date_range_label'] ?? '' ); ?></small>
                        <span class="wcpi-active-note-chip"><?php echo esc_html( $note['category_label'] ?? __( 'Update', WCPI_TEXT_DOMAIN ) ); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="wcpi-note-actions-popover" id="wcpi-note-actions-popover" hidden>
            <button type="button" class="wcpi-note-action-item" data-wcpi-note-action="view">
                <span class="dashicons dashicons-visibility"></span>
                <span><?php esc_html_e( 'View', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-note-action-item" data-wcpi-note-action="remove">
                <span class="dashicons dashicons-hidden"></span>
                <span><?php esc_html_e( 'Remove', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-note-action-item wcpi-note-action-danger" data-wcpi-note-action="end">
                <span class="dashicons dashicons-no-alt"></span>
                <span><?php esc_html_e( 'End Note', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
        </div>
    </section>

    <section class="wcpi-tabs-shell wcpi-tabs-shell-hero" id="wcpi-dashboard-tabs" data-query-key="tab" data-default-tab="executive" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
        <div class="wcpi-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Overview sections', WCPI_TEXT_DOMAIN ); ?>">
            <button type="button" class="wcpi-tab-button" data-tab-target="executive" role="tab" aria-selected="false">
                <span class="dashicons dashicons-chart-area"></span>
                <span><?php esc_html_e( 'Executive', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="recommendations" role="tab" aria-selected="false">
                <span class="dashicons dashicons-lightbulb"></span>
                <span><?php esc_html_e( 'Recommendations', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="products" role="tab" aria-selected="false">
                <span class="dashicons dashicons-products"></span>
                <span><?php esc_html_e( 'Products', WCPI_TEXT_DOMAIN ); ?></span>
            </button>
            <button type="button" class="wcpi-tab-button" data-tab-target="planning" role="tab" aria-selected="false">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php esc_html_e( 'Planning', WCPI_TEXT_DOMAIN ); ?></span>
                <em class="wcpi-tab-badge" id="wcpi-dashboard-notes-count"><?php echo esc_html( count( $active_notes ) ); ?></em>
            </button>
        </div>

        <div class="wcpi-tab-panels">
            <section class="wcpi-tab-panel" data-tab-panel="executive" aria-hidden="true">
                <div id="wcpi-dashboard-executive"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="recommendations" aria-hidden="true">
                <div id="wcpi-dashboard-recommendations"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="products" aria-hidden="true">
                <div id="wcpi-dashboard-products"></div>
            </section>
            <section class="wcpi-tab-panel" data-tab-panel="planning" aria-hidden="true">
                <div id="wcpi-dashboard-planning"></div>
            </section>
        </div>
    </section>

    <section class="wcpi-modal" data-wcpi-modal="expense" hidden>
        <div class="wcpi-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wcpi-expense-modal-title">
            <button type="button" class="wcpi-modal-close" data-wcpi-modal-close="expense" aria-label="<?php esc_attr_e( 'Close', WCPI_TEXT_DOMAIN ); ?>">&times;</button>
            <div class="wcpi-modal-view" data-modal-view="form">
                <div class="wcpi-modal-head">
                    <div>
                        <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Quick Action', WCPI_TEXT_DOMAIN ); ?></p>
                        <h2 id="wcpi-expense-modal-title"><?php esc_html_e( 'Add Expense', WCPI_TEXT_DOMAIN ); ?></h2>
                        <p><?php echo esc_html( sprintf( __( 'Save an expense without leaving Overview. Amounts use %s formatting and update analytics through AJAX.', WCPI_TEXT_DOMAIN ), $currency['code'] ) ); ?></p>
                    </div>
                </div>

                <form class="wcpi-form-grid wcpi-expense-ajax-form" id="wcpi-quick-expense-form" enctype="multipart/form-data" data-wcpi-modal-form="expense">
                    <input type="hidden" name="expense_id" value="0">
                    <input type="hidden" name="existing_attachment" value="">
                    <label>
                        <span><?php esc_html_e( 'Expense Name', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="text" name="expense_name" required>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></span>
                        <select name="category">
                            <?php foreach ( $expense_categories as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="wcpi-chip-row wcpi-span-2">
                        <?php foreach ( array_slice( $expense_categories, 0, 6, true ) as $key => $label ) : ?>
                            <button type="button" class="button wcpi-category-chip" data-category="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <label>
                        <span><?php esc_html_e( 'Amount', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="number" step="0.000001" min="0" name="amount" required>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Expense Date', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="date" name="expense_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required>
                    </label>
                    <label class="wcpi-span-2">
                        <span><?php esc_html_e( 'Notes', WCPI_TEXT_DOMAIN ); ?></span>
                        <textarea name="notes" rows="4" placeholder="<?php esc_attr_e( 'Optional details for the ledger, receipt context, or supplier reason.', WCPI_TEXT_DOMAIN ); ?>"></textarea>
                    </label>
                    <label class="wcpi-span-2">
                        <span><?php esc_html_e( 'Receipt Attachment', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                    <div class="wcpi-modal-actions wcpi-span-2">
                        <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Expense', WCPI_TEXT_DOMAIN ); ?></button>
                        <button class="button" type="button" data-wcpi-modal-close="expense"><?php esc_html_e( 'Cancel', WCPI_TEXT_DOMAIN ); ?></button>
                    </div>
                </form>
            </div>
            <div class="wcpi-modal-view wcpi-modal-success" data-modal-view="success" hidden>
                <div class="wcpi-modal-success-icon dashicons dashicons-yes-alt"></div>
                <h3><?php esc_html_e( 'Expense saved', WCPI_TEXT_DOMAIN ); ?></h3>
                <p class="wcpi-modal-success-message"><?php esc_html_e( 'Your expense has been added and dashboard totals will refresh automatically.', WCPI_TEXT_DOMAIN ); ?></p>
                <div class="wcpi-modal-actions">
                    <button class="button button-primary" type="button" data-wcpi-modal-reset="expense"><?php esc_html_e( 'Add New', WCPI_TEXT_DOMAIN ); ?></button>
                    <button class="button" type="button" data-wcpi-modal-close="expense"><?php esc_html_e( 'Close', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>
    </section>

    <section class="wcpi-modal" data-wcpi-modal="note" hidden>
        <div class="wcpi-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wcpi-note-modal-title">
            <button type="button" class="wcpi-modal-close" data-wcpi-modal-close="note" aria-label="<?php esc_attr_e( 'Close', WCPI_TEXT_DOMAIN ); ?>">&times;</button>
            <div class="wcpi-modal-view" data-modal-view="form">
                <div class="wcpi-modal-head">
                    <div>
                        <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Quick Action', WCPI_TEXT_DOMAIN ); ?></p>
                        <h2 id="wcpi-note-modal-title"><?php esc_html_e( 'Add Dashboard Note', WCPI_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Create a planning note with an effective date, expiry rule, optional email, and color-coded category so teams notice it faster.', WCPI_TEXT_DOMAIN ); ?></p>
                    </div>
                </div>

                <form class="wcpi-form-grid wcpi-note-ajax-form" id="wcpi-quick-note-form" data-wcpi-modal-form="note">
                    <label>
                        <span><?php esc_html_e( 'Title', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="text" name="note_title" required>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Tag', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="text" name="note_tag" placeholder="<?php esc_attr_e( 'promo, supplier, audit', WCPI_TEXT_DOMAIN ); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Category', WCPI_TEXT_DOMAIN ); ?></span>
                        <select name="note_category">
                            <?php foreach ( $note_categories as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Background Color', WCPI_TEXT_DOMAIN ); ?></span>
                        <select name="note_color">
                            <?php foreach ( $note_colors as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Effective From', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="date" name="date_from" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Expires On', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="date" name="date_to">
                    </label>
                    <label class="wcpi-toggle">
                        <span><?php esc_html_e( 'Send Email Notification', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="checkbox" name="email_enabled" value="yes">
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Recipient Email', WCPI_TEXT_DOMAIN ); ?></span>
                        <input type="email" name="email_recipient" value="<?php echo esc_attr( $default_email ); ?>" placeholder="<?php echo esc_attr( $default_email ); ?>">
                    </label>
                    <label class="wcpi-span-2">
                        <span><?php esc_html_e( 'Note', WCPI_TEXT_DOMAIN ); ?></span>
                        <textarea name="note_body" rows="5" required placeholder="<?php esc_attr_e( 'Explain the update, action, or reminder that should stay visible while this note is active.', WCPI_TEXT_DOMAIN ); ?>"></textarea>
                    </label>
                    <p class="wcpi-field-note wcpi-span-2"><?php esc_html_e( 'Active notes appear on this overview page and the main WordPress dashboard while the effective date window is valid.', WCPI_TEXT_DOMAIN ); ?></p>
                    <div class="wcpi-modal-actions wcpi-span-2">
                        <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Note', WCPI_TEXT_DOMAIN ); ?></button>
                        <button class="button" type="button" data-wcpi-modal-close="note"><?php esc_html_e( 'Cancel', WCPI_TEXT_DOMAIN ); ?></button>
                    </div>
                </form>
            </div>
            <div class="wcpi-modal-view wcpi-modal-success" data-modal-view="success" hidden>
                <div class="wcpi-modal-success-icon dashicons dashicons-edit-large"></div>
                <h3><?php esc_html_e( 'Dashboard note saved', WCPI_TEXT_DOMAIN ); ?></h3>
                <p class="wcpi-modal-success-message"><?php esc_html_e( 'The note is now available in planning surfaces and active-note areas.', WCPI_TEXT_DOMAIN ); ?></p>
                <div class="wcpi-modal-actions">
                    <button class="button button-primary" type="button" data-wcpi-modal-reset="note"><?php esc_html_e( 'Add New', WCPI_TEXT_DOMAIN ); ?></button>
                    <button class="button" type="button" data-wcpi-modal-close="note"><?php esc_html_e( 'Close', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>
    </section>

    <section class="wcpi-modal" data-wcpi-modal="note-view" hidden>
        <div class="wcpi-modal-dialog wcpi-modal-dialog-medium" role="dialog" aria-modal="true" aria-labelledby="wcpi-note-view-title">
            <button type="button" class="wcpi-modal-close" data-wcpi-modal-close="note-view" aria-label="<?php esc_attr_e( 'Close', WCPI_TEXT_DOMAIN ); ?>">&times;</button>
            <div class="wcpi-modal-view" data-modal-view="view">
                <div class="wcpi-modal-head">
                    <div>
                        <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Dashboard Note', WCPI_TEXT_DOMAIN ); ?></p>
                        <h2 id="wcpi-note-view-title"><?php esc_html_e( 'Note details', WCPI_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Read the full planning message, category, dates, and email rule without leaving Overview.', WCPI_TEXT_DOMAIN ); ?></p>
                    </div>
                </div>
                <div class="wcpi-note-detail">
                    <div class="wcpi-note-detail-top">
                        <strong id="wcpi-note-detail-heading"><?php esc_html_e( 'Dashboard note', WCPI_TEXT_DOMAIN ); ?></strong>
                        <div class="wcpi-inline-tools" id="wcpi-note-detail-badges"></div>
                    </div>
                    <div class="wcpi-footnote-grid wcpi-note-detail-meta">
                        <div>
                            <span class="wcpi-field-label"><?php esc_html_e( 'Effective Window', WCPI_TEXT_DOMAIN ); ?></span>
                            <p id="wcpi-note-detail-dates">-</p>
                        </div>
                        <div>
                            <span class="wcpi-field-label"><?php esc_html_e( 'Email Rule', WCPI_TEXT_DOMAIN ); ?></span>
                            <p id="wcpi-note-detail-email">-</p>
                        </div>
                    </div>
                    <div class="wcpi-note-detail-body" id="wcpi-note-detail-body"></div>
                </div>
                <div class="wcpi-modal-actions">
                    <button class="button" type="button" data-wcpi-modal-close="note-view"><?php esc_html_e( 'Close', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>
    </section>

    <section class="wcpi-modal" data-wcpi-modal="note-confirm" hidden>
        <div class="wcpi-modal-dialog wcpi-modal-dialog-small" role="dialog" aria-modal="true" aria-labelledby="wcpi-note-confirm-title">
            <button type="button" class="wcpi-modal-close" data-wcpi-modal-close="note-confirm" aria-label="<?php esc_attr_e( 'Close', WCPI_TEXT_DOMAIN ); ?>">&times;</button>
            <div class="wcpi-modal-view" data-modal-view="confirm">
                <div class="wcpi-modal-head">
                    <div>
                        <p class="wcpi-hero-eyebrow"><?php esc_html_e( 'Confirmation', WCPI_TEXT_DOMAIN ); ?></p>
                        <h2 id="wcpi-note-confirm-title"><?php esc_html_e( 'Confirm note action', WCPI_TEXT_DOMAIN ); ?></h2>
                        <p id="wcpi-note-confirm-text"><?php esc_html_e( 'Please confirm this note action.', WCPI_TEXT_DOMAIN ); ?></p>
                    </div>
                </div>
                <div class="wcpi-modal-actions">
                    <button class="button button-primary" type="button" id="wcpi-note-confirm-submit"><?php esc_html_e( 'Confirm', WCPI_TEXT_DOMAIN ); ?></button>
                    <button class="button" type="button" data-wcpi-modal-close="note-confirm"><?php esc_html_e( 'Cancel', WCPI_TEXT_DOMAIN ); ?></button>
                </div>
            </div>
        </div>
    </section>

    <div class="wcpi-page-footer"><?php echo wp_kses_post( WCPI_Helpers::brand_footer() ); ?></div>
    <script>window.wcpiDashboardBootstrap = <?php echo wp_json_encode( $data ); ?>;</script>
</div>
