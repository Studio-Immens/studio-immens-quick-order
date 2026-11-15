# Quick Order Management Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the Quick Order management suite across dashboard, customer ordering, inventory, printers, receipts, reservations, staff and reports.

**Architecture:** Free plugin owns the branded shell, `/gestione/`, access model, settings and compatibility layer. Pro plugin owns operational modules and registers them through `siqo_management_modules`; every module renders inside the shared shell. WooCommerce remains the source of truth for product stock, while Pro owns ingredient, recipe, lot and stocktake ledgers.

**Tech Stack:** WordPress, WooCommerce, PHP 8.2+, WordPress Options API, custom `$wpdb` tables, `admin-post.php`, `admin-ajax.php`, vanilla JS, CSS.

**Spec:** `docs/superpowers/specs/2026-11-14-quick-order-management-suite-design.md`

## Global Constraints

- All PHP files start with `defined( 'ABSPATH' ) || exit;`.
- No new comments in PHP, JS or CSS.
- Escape output with `esc_html()`, `esc_attr()`, `esc_url()` or `wp_kses_post()`.
- Sanitize input with `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()` or `wp_unslash()`.
- Verify nonces on every write path and check `SIQO_Management::can()` or `manage_options`.
- Use `$wpdb->prepare()` for every query with dynamic values; allowlist table names.
- Customer pages never expose staff navigation.
- Admin bar is compact and never fullscreen; staff and customer bars expose fullscreen.
- Italian UI copy; text domain `studio-immens-quick-order` in Free and `studio-immens-quick-order-pro` in Pro.
- No fiscal receipt logic, RT integration or VAT calculation.
- Product stock is read from WooCommerce; ingredient stock is read from the Pro ledger.
- Every new feature must degrade cleanly when WooCommerce or Pro is inactive.

---

### Task 1: Core shell, roles and `/gestione/`

**Files:**
- Create: `includes/class-siqo-management.php`
- Create: `public/template/management-dashboard.php`
- Create: `public/css/siqo-management.css`
- Create: `public/js/siqo-management.js`
- Modify: `includes/class-studio-immens-quick-order.php`
- Modify: `includes/class-studio-immens-quick-order-activator.php`
- Test: `php -l` on every changed PHP file.

**Interfaces:**
- Produces: `SIQO_Management::init()`, `SIQO_Management::can( string $capability ): bool`, `SIQO_Management::role_level(): string`, `SIQO_Management::render_bar( string $context, array $args = array() ): void`, `SIQO_Management::render_shell_start( array $args ): void`, `SIQO_Management::render_shell_end(): void`, `SIQO_Management::get_modules(): array`, `SIQO_Management::ensure_page(): void`.

- [ ] **Step 1: Create the class with capability mapping and page creation.**

```php
class SIQO_Management {
    const PAGE_SLUG = 'gestione';
    const PAGE_VERSION = '1.0.0';
    public static function init() {}
    public static function can( $capability ) { return true; }
    public static function role_level() { return 'worker'; }
    public static function ensure_page() {}
    public static function render_bar( $context, $args = array() ) {}
    public static function render_shell_start( $args = array() ) {}
    public static function render_shell_end() {}
    public static function get_modules() { return array(); }
}
```

- [ ] **Step 2: Register hooks and shortcode.**

```php
add_action( 'init', array( __CLASS__, 'ensure_page' ), 21 );
add_shortcode( 'SIQO_management_dashboard', array( __CLASS__, 'render_dashboard' ) );
add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
add_action( 'wp_ajax_siqo_management_dashboard', array( __CLASS__, 'ajax_dashboard' ) );
add_action( 'wp_ajax_siqo_management_settings', array( __CLASS__, 'ajax_settings' ) );
```

- [ ] **Step 3: Render a full-height shell with brand, nav, context title and fullscreen control.**

Required DOM: `#siqo-app`, `.siqo-bar`, `.siqo-bar-brand`, `.siqo-bar-nav`, `.siqo-bar-actions`, `.siqo-main`.

- [ ] **Step 4: Add `/gestione/` page creation and migration.**

Use `get_page_by_path( 'gestione' )`, `wp_insert_post()` when missing, `wp_update_post()` when shortcode is missing, and `update_option( 'siqo_management_page_id', $id )`.

- [ ] **Step 5: Add capability checks.**

Manager: `manage_options`, `siqo_manage_settings`, all module caps; supervisor: service, inventory, printers, receipts, reservations, staff, reports; worker: service and dashboard only. Administrator always manager.

- [ ] **Step 6: Run lint.**

Run: `php -l includes/class-siqo-management.php` and `php -l includes/class-studio-immens-quick-order.php`.
Expected: `No syntax errors detected`.

### Task 2: Dashboard overview and live data

**Files:**
- Modify: `includes/class-siqo-management.php`
- Create: `public/template/management-dashboard.php`
- Create: `public/js/siqo-management.js`
- Test: `php -l includes/class-siqo-management.php`

**Interfaces:**
- Consumes: `SIQO_Management::render_shell_start()`, `SIQO_Management::render_bar()`, `SIQO_Management::get_modules()`.
- Produces: `SIQO_Management::get_live_stats(): array`, `SIQO_Management::get_live_orders( int $limit = 12 ): array`, `SIQO_Management::render_dashboard(): string`, `SIQO_Management::ajax_dashboard(): void`.

- [ ] **Step 1: Add live stats with WooCommerce guards.**

Return keys `orders_today`, `revenue_today`, `open_tables`, `low_stock`, `reservations_today`, `staff_on_shift`. Use `wc_get_orders()` with `date_created` range and statuses `processing`, `completed`, `on-hold`.

- [ ] **Step 2: Add recent orders.**

Return arrays with `id`, `number`, `table`, `status`, `total`, `items`, `time`, `url`.

- [ ] **Step 3: Render cards, quick actions and recent order table.**

Cards: incasso, ordini, tavoli aperti, scorte basse, prenotazioni, personale. Actions: apri tavolo, ordini reparti, magazzino, prenotazioni, personale, statistiche.

- [ ] **Step 4: Add AJAX refresh.**

Action `siqo_management_dashboard`, nonce `siqo_management`, capability `siqo_view_dashboard`. Return `wp_send_json_success()` with stats and HTML rows.

- [ ] **Step 5: Poll every 20 seconds only when the tab is visible.**

- [ ] **Step 6: Lint and verify no direct `$_GET`/`$_POST` reads without sanitization.**

### Task 3: Customer experience

**Files:**
- Modify: `public/template/front-order-ajax.php`
- Modify: `public/studio-immens-quick-order-public.php`
- Create: `public/css/siqo-customer.css`
- Test: `php -l public/template/front-order-ajax.php`

**Interfaces:**
- Consumes: `SIQO_Management::render_bar( 'customer', $args )`.
- Produces: customer bar with restaurant name, validated table badge, account link, fullscreen, search and cart.

- [ ] **Step 1: Validate `tab_id` against `siqo_tables` and active status.**

If invalid, render the table selector and an explanatory message.

- [ ] **Step 2: Prepend the customer bar inside `.FOmniContent`.**

Pass `restaurant_name`, `logo_url`, `table_id`, `table_name`, `account_url`, `cart_count`.

- [ ] **Step 3: Add account/login/logout action.**

Use `is_user_logged_in()`, `wp_login_url( get_permalink() )` and `wp_logout_url( get_permalink() )`.

- [ ] **Step 4: Add customer CSS for bar, product grid, sticky cart and mobile layout.**

- [ ] **Step 5: Lint and verify staff links are absent from customer markup.**

### Task 4: Reorganized settings and legacy redirects

**Files:**
- Create: `admin/pages/management-settings.php`
- Modify: `admin/studio-immens-quick-order-admin.php`
- Modify: `includes/class-siqo-management.php`
- Create: `admin/css/siqo-management-admin.css`
- Test: `php -l admin/pages/management-settings.php`

**Interfaces:**
- Produces: `SIQO_Management::render_settings_page(): void`, `SIQO_Management::handle_settings_save(): void`, `SIQO_Management::get_brand(): array`, `SIQO_Management::get_brand_css(): string`.

- [ ] **Step 1: Add a new settings page callback with sections.**

Sections: Ristorante e brand, Pagine e accessi, Catalogo, Magazzino, Stampanti, Scontrini, Prenotazioni, Personale, Avanzate, Licenza.

- [ ] **Step 2: Add brand fields.**

Keys: `siqo_restaurant_name`, `siqo_restaurant_logo_url`, `siqo_brand_primary`, `siqo_brand_accent`, `siqo_brand_theme`, `siqo_quick_order_credit`, `siqo_receipt_header`, `siqo_receipt_footer`.

- [ ] **Step 3: Save on `admin_init` with nonce `siqo_management_settings` and capability `manage_options`.**

- [ ] **Step 4: Add a Legacy settings submenu that includes the original `general-settings.php`.**

- [ ] **Step 5: Redirect `si-quick-order_style` to `section=brand` and Pro legacy pages to their sections.**

- [ ] **Step 6: Inject brand CSS variables on frontend and admin shell.**

- [ ] **Step 7: Lint and verify every legacy option remains readable.**

### Task 5: Inventory ledger and recipes

**Files:**
- Create: `studio-immens-quick-order-pro/includes/class-siqo-inventory.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro-activator.php`
- Test: `php -l studio-immens-quick-order-pro/includes/class-siqo-inventory.php`

**Interfaces:**
- Produces: `SIQO_Inventory::init()`, `SIQO_Inventory::create_tables()`, `SIQO_Inventory::get_ingredients()`, `SIQO_Inventory::save_ingredient( array $data )`, `SIQO_Inventory::adjust_ingredient( int $id, float $qty, string $type, string $reference, string $note )`, `SIQO_Inventory::get_recipes()`, `SIQO_Inventory::save_recipe( array $data )`, `SIQO_Inventory::deduct_order( int $order_id )`, `SIQO_Inventory::get_low_stock()`, `SIQO_Inventory::get_stats()`.

- [ ] **Step 1: Create tables.**

`siqo_ingredients`, `siqo_recipes`, `siqo_recipe_items`, `siqo_suppliers`, `siqo_lots`, `siqo_inventory_movements`, `siqo_stocktakes`, `siqo_stocktake_items`. Use `dbDelta()`, `$wpdb->get_charset_collate()` and a version option `siqo_inventory_db_version`.

- [ ] **Step 2: Add CRUD for ingredients, recipes, suppliers and lots.**

All writes use allowlisted formats and `$wpdb->prepare()` where dynamic.

- [ ] **Step 3: Add idempotent order deduction.**

Mark `_siqo_inventory_deducted` on the order; for each line item, find recipe by product or variation, subtract `qty * recipe_item.qty`, write a movement, and stop on missing recipe.

- [ ] **Step 4: Add low-stock and stats queries.**

- [ ] **Step 5: Hook deduction on `siqo_submit_order_ajax`, `woocommerce_order_status_processing` and `woocommerce_order_status_completed`.**

- [ ] **Step 6: Lint and verify no double deduction path without the order meta guard.**

### Task 6: Inventory UI and stocktakes

**Files:**
- Modify: `studio-immens-quick-order-pro/includes/class-siqo-inventory.php`
- Create: `studio-immens-quick-order-pro/public/template/inventory-panel.php`
- Create: `studio-immens-quick-order-pro/public/css/siqo-inventory.css`
- Test: `php -l` on changed PHP files.

**Interfaces:**
- Produces: `SIQO_Inventory::render_panel(): string`, `SIQO_Inventory::handle_post(): void`, `SIQO_Inventory::get_stocktake( int $id )`, `SIQO_Inventory::complete_stocktake( int $id )`.

- [ ] **Step 1: Render tabs: Panoramica, Ingredienti, Ricette, Lotti, Fornitori, Inventario, Movimenti.**

- [ ] **Step 2: Add create/edit forms for ingredient, recipe, lot and supplier.**

- [ ] **Step 3: Add stocktake creation from current expected quantities and completion.**

On completion, write adjustment movements for each difference and set status `completed`.

- [ ] **Step 4: Add filters, search, pagination and empty states.**

- [ ] **Step 5: Register `admin_post_siqo_inventory_save` and `admin_post_siqo_inventory_delete` with nonce and `siqo_manage_inventory`.**

- [ ] **Step 6: Lint and verify redirects preserve the active tab.**

### Task 7: Printer registry, queue and network adapter

**Files:**
- Create: `studio-immens-quick-order-pro/includes/class-siqo-printers.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro-activator.php`
- Test: `php -l studio-immens-quick-order-pro/includes/class-siqo-printers.php`

**Interfaces:**
- Produces: `SIQO_Printers::init()`, `SIQO_Printers::create_tables()`, `SIQO_Printers::get_printers()`, `SIQO_Printers::save_printer( array $data )`, `SIQO_Printers::enqueue( int $order_id, string $type, array $printer_ids )`, `SIQO_Printers::process_queue()`, `SIQO_Printers::send_to_network( array $printer, string $payload )`, `SIQO_Printers::send_to_printnode( array $printer, string $payload )`, `SIQO_Printers::render_panel(): string`.

- [ ] **Step 1: Store printers in `siqo_printers` option and queue in `siqo_print_queue`.**

Types: `network`, `browser`, `printnode`. Network fields: host, port. PrintNode fields: api_key, printer_id. Common: name, station, active, auto_receipt, copies.

- [ ] **Step 2: Add queue state machine.**

Statuses: `pending`, `processing`, `printed`, `failed`. Attempts and last_error.

- [ ] **Step 3: Add network ESC/POS adapter.**

Use `fsockopen()` with 3-second timeout, write receipt text, close socket, return true/false.

- [ ] **Step 4: Add PrintNode adapter.**

POST JSON to `https://api.printnode.com/printjobs` with Basic auth and `printerId`, `title`, `contentType: raw_base64`, `content`.

- [ ] **Step 5: Add WP-Cron every minute and a manual process button.**

- [ ] **Step 6: Add printer UI with test print and queue table.**

- [ ] **Step 7: Lint and verify no API key is rendered unmasked outside its edit field.**

### Task 8: Non-fiscal receipts and browser printing

**Files:**
- Create: `studio-immens-quick-order-pro/includes/class-siqo-receipts.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Modify: `studio-immens-quick-order-pro/includes/class-siqo-printers.php`
- Test: `php -l studio-immens-quick-order-pro/includes/class-siqo-receipts.php`

**Interfaces:**
- Produces: `SIQO_Receipts::render_text( int $order_id, string $type ): string`, `SIQO_Receipts::render_html( int $order_id, string $type ): string`, `SIQO_Receipts::handle_print(): void`, `SIQO_Receipts::get_receipt_types(): array`.

- [ ] **Step 1: Render comanda and ricevuta.**

Include restaurant name, order number, table, date, items with qty/notes, total, footer, and no fiscal fields.

- [ ] **Step 2: Add `admin_post_siqo_receipt` with nonce, capability and order ownership checks.**

Support `format=html` and `format=text`; `html` uses `window.print()`.

- [ ] **Step 3: Queue receipts when orders reach processing.**

Use active `auto_receipt` printers; if print rules match, restrict to those printers.

- [ ] **Step 4: Add reprint buttons in dashboard and order rows.**

- [ ] **Step 5: Lint and verify output escapes every order-derived value.**

### Task 9: Reservations

**Files:**
- Create: `studio-immens-quick-order-pro/includes/class-siqo-reservations.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro-activator.php`
- Test: `php -l studio-immens-quick-order-pro/includes/class-siqo-reservations.php`

**Interfaces:**
- Produces: `SIQO_Reservations::init()`, `SIQO_Reservations::create_tables()`, `SIQO_Reservations::get_reservations( array $filters )`, `SIQO_Reservations::save( array $data )`, `SIQO_Reservations::update_status( int $id, string $status )`, `SIQO_Reservations::render_panel(): string`, `SIQO_Reservations::get_today_count(): int`.

- [ ] **Step 1: Create `siqo_reservations` with customer, party, date/time, table, status, notes and timestamps.**

- [ ] **Step 2: Add CRUD and status transitions.**

Statuses: `pending`, `confirmed`, `seated`, `cancelled`, `no_show`.

- [ ] **Step 3: Add dashboard panel with date filter, form and list.**

- [ ] **Step 4: Add `admin_post_siqo_reservation_save` and `admin_post_siqo_reservation_status`.**

- [ ] **Step 5: Lint and validate date/time and party size.**

### Task 10: Staff, shifts and reports

**Files:**
- Create: `studio-immens-quick-order-pro/includes/class-siqo-staff.php`
- Create: `studio-immens-quick-order-pro/includes/class-siqo-reports.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro-activator.php`
- Test: `php -l` on changed PHP files.

**Interfaces:**
- Produces: `SIQO_Staff::create_tables()`, `SIQO_Staff::clock_in( int $user_id )`, `SIQO_Staff::clock_out( int $user_id )`, `SIQO_Staff::get_current_shift( int $user_id )`, `SIQO_Staff::get_shifts( array $filters )`, `SIQO_Staff::render_panel(): string`, `SIQO_Reports::get_sales( string $from, string $to ): array`, `SIQO_Reports::get_top_products( string $from, string $to, int $limit ): array`, `SIQO_Reports::get_hourly( string $from, string $to ): array`, `SIQO_Reports::render_panel(): string`.

- [ ] **Step 1: Create `siqo_shifts` with user, role, clock in/out, note and timestamps.**

- [ ] **Step 2: Add clock in/out with one open shift per user.**

- [ ] **Step 3: Add staff panel with current shift and recent shifts.**

- [ ] **Step 4: Add sales, top products and hourly reports from `wc_get_orders()`.**

- [ ] **Step 5: Add report panel with date range and empty states.**

- [ ] **Step 6: Lint and verify report queries cap at 1000 orders.**

### Task 11: Module registration, navigation and dashboard integration

**Files:**
- Modify: `includes/class-siqo-management.php`
- Modify: `includes/class-studio-immens-quick-order.php`
- Modify: `studio-immens-quick-order-pro/includes/class-studio-immens-quick-order-pro.php`
- Test: `php -l` on changed PHP files.

**Interfaces:**
- Consumes: Pro module classes.
- Produces: module nav entries and dashboard panels.

- [ ] **Step 1: Add default Free modules.**

Dashboard, Tavoli, Reparti, Impostazioni.

- [ ] **Step 2: Add Pro modules only when class and license exist.**

Magazzino, Stampanti, Scontrini, Prenotazioni, Personale, Statistiche.

- [ ] **Step 3: Render the active module view from `?view=`.**

Allowlist views: `dashboard`, `inventory`, `printers`, `receipts`, `reservations`, `staff`, `reports`.

- [ ] **Step 4: Add dashboard cards fed by module stats.**

- [ ] **Step 5: Verify no module renders without capability.**

### Task 12: Migration, compatibility and release verification

**Files:**
- Modify: `uninstall.php` in both plugins
- Modify: `studio-immens-quick-order.php`
- Modify: `studio-immens-quick-order-pro/studio-immens-quick-order-pro.php`
- Modify: `README.txt` in both plugins
- Test: `php -l` on every PHP file; `wp plugin list` when WP-CLI is available.

- [ ] **Step 1: Preserve all existing options and tables.**

Do not delete legacy options on upgrade; only remove new tables/options on uninstall when the corresponding "delete data" option is enabled.

- [ ] **Step 2: Add page migration and legacy redirects.**

- [ ] **Step 3: Bump versions to `1.1.0` Free and `1.1.0` Pro.**

- [ ] **Step 4: Add readme changelog entries.**

- [ ] **Step 5: Run syntax checks on all changed files.**

- [ ] **Step 6: Run the acceptance checklist.**

Acceptance:
- `/gestione/` exists, renders shell and denies unauthorized users.
- Customer page has restaurant brand, validated table, account action, fullscreen and cart.
- Admin bar is compact; staff and customer bars have fullscreen.
- Settings preserve legacy values and save brand.
- Inventory CRUD, stocktake, order deduction and low-stock stats work.
- Printer queue, test print, network/PrintNode adapters and browser receipt work.
- Reservations, shifts and reports render with empty and populated states.
- No fiscal receipt logic exists.
- No PHP syntax errors.

## Execution Handoff

User explicitly requested continuous execution across all phases. Execute inline, continue through failures, and do not stop between phases. Report only after all tasks are implemented and verified.
