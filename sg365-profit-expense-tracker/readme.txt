=== SG365 Profit & Expense Tracker for WooCommerce ===
Contributors: openai
Tags: woocommerce, profit, analytics, expenses, cost of goods
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track real WooCommerce profit, product costs, business expenses, daily summaries, profitability reports, and exports.

== Description ==

SG365 Profit & Expense Tracker for WooCommerce helps merchants move beyond revenue-only reporting by introducing:

* product cost tracking for simple and variable products
* expense management with receipt attachment support and recurring templates
* precomputed daily summaries for faster admin dashboards
* profit, margin, refund, and previous-period analytics
* category profitability, smart recommendations, and missing-cost audits
* profit goals, budget-vs-actual tracking, and category margin heatmaps
* product health scoring, trend intelligence, and performance highlights
* cost and price impact simulators plus dashboard business annotations
* CSV, PDF, and Excel export tools
* basic scheduled email reports for weekly or monthly summaries
* diagnostics, maintenance, logging, and retention settings
* secure custom tables and protected upload storage

== Installation ==

1. Upload the plugin zip from **Plugins > Add New > Upload Plugin**.
2. Activate the plugin.
3. Make sure WooCommerce is active.
4. Visit **WooCommerce > Profit Dashboard**.
5. Configure settings under **WooCommerce > Settings > Profit Intelligence**.

== Frequently Asked Questions ==

= Does this slow down the storefront? =

No heavy analytics are run on public frontend pages. The plugin uses admin-only reports, custom tables, and precomputed summaries.

= Does it support variations? =

Yes. Variation costs override parent product costs when present.

= Can I remove all plugin data on uninstall? =

Yes. Enable the uninstall cleanup setting in the plugin settings page first.

== Changelog ==

= 1.4.3 =
* Refined the Overview header to more closely match the provided expected design with tighter action buttons, meta ribbon polish, and cleaner control spacing
* Added real header note actions with popup view, quick remove from active surfaces, and immediate end-note confirmation
* Improved active note responsiveness so 1, 2, 3, or 4 notes keep a balanced premium layout without breaking the header design
* Added dashboard-note status handling for active, removed, and ended notes while preserving existing note records

= 1.4.2 =
* Reworked the Overview header to closely match the provided reference layout with a lighter SaaS shell, top action buttons, meta ribbon, and structured control card
* Refined active note cards inside the header area so the planning alerts visually follow the uploaded design direction more closely
* Improved Overview header labels for range, comparison, currency, and refreshed time without changing analytics data

= 1.4.1 =
* Refined the Overview page into a closer big-company SaaS shell with quick action tiles, lighter premium hierarchy, and tighter grouped tabs
* Added Overview quick-action modals for AJAX expense entry and AJAX dashboard notes without leaving the analytics screen
* Added richer dashboard note rules with category, color theme, effective date, expiry date, optional email delivery, and active-note visibility on the WordPress dashboard
* Improved planning surfaces so active notes refresh into Overview automatically while keeping existing analytics data and calculations intact

= 1.4.0 =
* Added a shorter premium SaaS tabbed redesign for Overview, Reports, Expenses, Diagnostics, Settings, and Export
* Upgraded Overview and Reports into grouped executive, products, recommendations, categories, and planning workspaces
* Improved Product Costs with richer table detail, live margin visibility, and clearer variation-gap and row-state feedback
* Improved diagnostics maintenance with grouped tools, icons, upload regeneration, recurring re-sync, and downloadable diagnostics snapshots
* Improved AJAX interactions for dashboard notes, product costs, expenses, and budget updates while preserving business logic

= 1.3.0 =
* Complete premium SaaS-style admin redesign
* Improved dashboard hierarchy and analytics presentation
* Added richer hover states, card interactions, and premium table styling
* Redesigned Product Costs, Expenses, Reports, Export, Diagnostics, and Settings pages
* Added Excel export support
* Improved AJAX workflows for expenses and product costs
* Improved currency consistency across analytics and reports
* Improved notes visibility on dashboard
* Enhanced overall admin UX to a large-company quality standard

= 1.2.2 =
* Updated the admin menu label to Profit/Expense
* Refreshed package version metadata for the 1.2.2 release

= 1.2.1 =
* Declared compatibility with supported WooCommerce feature flags including HPOS-related order storage support
* Refreshed plugin package version metadata and release archive

= 1.2.0 =
* Added profit goals and progress tracking
* Added expense budget vs actual tracking
* Added product health score analytics
* Added best and worst performance highlights
* Added profit trend intelligence insights
* Added cost change impact simulator
* Added price change impact simulator
* Added business annotations and dashboard notes with tags
* Added category margin heatmap
* Added scheduled email reports

= 1.1.0 =
* Added previous-period comparison for key analytics metrics
* Added smart recommendations for cost gaps, margin issues, losses, and refund increases
* Added missing cost audit improvements with variation gap and completeness tracking
* Added category profitability reporting with quantity, revenue, cost, profit, and margin insights
* Added recurring expense template management with generated expense support
* Added executive summary PDF export and expanded report export options
* Improved diagnostics with storage, sync, rebuild, and recurring template health details
* Improved product cost, expense, and reporting workflows for admin users

= 1.0.0 =
* Initial commercial-ready release
* Product costs, expenses, reports, exports, diagnostics, and settings
* Daily summary engine and WooCommerce order sync hooks
