# Quick Order — Management Suite Design

- **Status:** Proposed for review
- **Date:** 2026-11-14
- **Product:** Studio Immens Quick Order (Free + Pro)
- **Scope:** Brand system, shared shell, operational dashboard, restaurant service, inventory, printers, non-fiscal receipts, reservations, staff, reports, customer ordering page
- **Deployment model:** One restaurant per WordPress installation

## 1. Context

Quick Order currently provides table management, a preparation-station screen, a customer ordering page, a Pro warehouse with product stock movements, and Pro print rules. These surfaces were built at different times and do not share a navigation shell, design system, or operational model.

The current customer page at `/flash-orders-ajax/` loads but is visually broken: the QR block dominates the page, theme and plugin navigation overlap, panels collide, and product rows fragment on desktop. The admin settings page is a monolithic form with duplicated styles. Pro warehouse and print rules are separate submenus that do not form a coherent management surface.

The product must remain Quick Order-branded and reusable for any restaurant. Natilla Bistrot is a preset and a reference implementation, not a hardcoded identity.

### Baseline

- Free plugin live version: `1.0.9.3`, patched with the station include.
- Local Free plugin main file: WIP `1.0.10`, not to be deployed wholesale.
- Pro plugin: `1.0.8`.
- Existing frontend pages: `/manage-tables/`, `/ordini-reparti/`, `/flash-orders-ajax/`.
- Existing Pro modules: `SIQO_Warehouse`, `SIQO_Print_Rules`.
- Existing access model: user meta flags for `worker`, `supervisor`, and `manager`, with hierarchical resolution.

## 2. Goals

1. Establish Quick Order as the product brand, with a per-restaurant profile that customizes logo, palette, typography, copy, and customer-facing identity.
2. Replace fragmented plugin pages with one coherent shell, one top status bar, and one navigation model.
3. Provide a protected frontend operational dashboard at `/gestione/` for live service, inventory, printers, receipts, reservations, staff, and reports.
4. Redesign `/flash-orders-ajax/` as a fast, responsive customer ordering application that does not inherit theme chrome.
5. Preserve and reorganize every existing setting without data loss.
6. Introduce a complete inventory model with WooCommerce product stock and a separate ingredient ledger, linked by recipes.
7. Support network ESC/POS, browser/PDF, and USB/PrintNode printers with reliable queues, routing, and reprints.
8. Keep the free plugin useful on its own and place advanced operational modules in Pro.

## 3. Non-goals

- Fiscal receipts, VAT fiscal devices, or certified cash-register integration.
- SaaS multi-tenancy or multiple independent restaurants in one installation.
- Payroll, legal attendance compliance, or accounting.
- Public online reservation booking in the first release.
- Replacing WooCommerce as the product catalog or sellable-stock system.
- Redesigning the Quick Order logo mark; the existing mark is retained.

## 4. Decisions

| Area | Decision |
|---|---|
| Product brand | Quick Order is the product brand. Restaurant branding overrides customer-facing identity. |
| Brand placement | Quick Order in admin and staff surfaces; restaurant brand on customer surfaces; optional small Quick Order credit. |
| Installation model | One restaurant per WordPress installation. |
| Dashboard location | Protected frontend dashboard at `/gestione/`, not another wp-admin page. |
| Admin shell | Compact Quick Order bar in wp-admin; no fullscreen. |
| Staff shell | Sticky top bar with navigation and fullscreen. |
| Customer shell | Restaurant-branded bar with table context, search, fullscreen, and cart; no staff navigation. |
| Theme chrome | All plugin app pages hide the theme header and footer. |
| Warehouse | WooCommerce is the source of truth for sellable product stock; ingredients use a plugin-owned ledger; recipes connect the two. |
| Receipts | Kitchen/bar tickets and non-fiscal customer receipts only. |
| Printers | Network ESC/POS, browser/PDF, and USB/PrintNode through adapters. |
| Roles | Existing SIQO hierarchy: manager > supervisor > worker, with module visibility configurable by role. |
| Settings | Preserve and reorganize all existing options; advanced fields move under “Avanzate”. |
| Architecture | Modular core in Free, advanced modules in Pro, registered through a shared module registry. |
| Delivery | Four phases; Phase 1 is the first usable release. |

## 5. Architecture

### 5.1 Plugin split

**Free plugin**

- `SIQO_Shell`: detects plugin app pages, selects shell mode, renders the top bar, enqueues shell assets, and hides theme chrome where configured.
- `SIQO_Brand`: resolves the Quick Order default profile and the restaurant profile; exposes tokens, logo, copy, and preset data.
- `SIQO_Module_Registry`: registers modules and their navigation entries, capabilities, routes, and assets.
- `SIQO_Settings`: settings registry, sections, fields, validation, import/export, and legacy URL redirects.
- `SIQO_Dashboard`: `/gestione/` page, dashboard home, and live service.
- `SIQO_Customer_App`: `/flash-orders-ajax/` template, product search, cart, and order submission.
- Existing table, station, and order logic remains and is integrated behind the shell.

**Pro plugin**

- `SIQO_Pro_Inventory`: product stock integration, ingredients, recipes, suppliers, lots, movements, stocktakes, costing.
- `SIQO_Pro_Printers`: printer registry, adapters, routing, queue, test print.
- `SIQO_Pro_Receipts`: ticket and receipt templates, archive, reprint, retention.
- `SIQO_Pro_Reservations`: internal reservation calendar and table assignment.
- `SIQO_Pro_Staff`: staff directory, shifts, clock-in/out, timesheet export.
- `SIQO_Pro_Reports`: historical service, product, inventory, and staff reports.

Pro modules register through the Free module registry. If Pro is absent or unlicensed, Free still renders its shell, settings, customer app, and base dashboard. Pro navigation entries are hidden or shown as inactive with an explanation.

### 5.2 Module contract

Each module provides:

- a stable identifier and translated label;
- the capability required to view it;
- one or more navigation entries with order, icon, and optional badge;
- a render callback for its dashboard view;
- REST or AJAX routes with permission callbacks;
- asset handles and dependencies;
- optional dashboard cards and alerts;
- an activation check that reports why the module is unavailable.

Modules must not query another module's tables directly. Cross-module reads go through service methods. A module must be disableable without causing fatal errors or broken navigation.

### 5.3 App page detection

App pages are resolved in this order:

1. Explicit page IDs stored in the `siqo_app_pages` option, mapped to shell mode (`staff`, `customer`, `admin-preview`).
2. Pages containing a Quick Order app shortcode.
3. No shell for unrelated pages.

The shell uses a plugin template via `template_include` for app pages. If the option is disabled, the shortcode renders inside the theme as a compatibility fallback. A body class identifies the shell mode and restaurant preset.

### 5.4 Page and route map

| Surface | Route | Shortcode or entry | Shell |
|---|---|---|---|
| Operational dashboard | `/gestione/` | `[SIQO_management_dashboard]` | Staff |
| POS / tables | `/manage-tables/` | `[SIQO_manage_tables_section]` | Staff |
| Preparation stations | `/ordini-reparti/` | `[SIQO_station_orders_section]` | Staff |
| Customer ordering | `/flash-orders-ajax/` | `[SIQO_front_order_ajax_section]` | Customer |
| Admin overview | `admin.php?page=si-quick-order` | Admin page | Admin compact |
| Admin settings | `admin.php?page=si-quick-order_settings` | Admin page | Admin compact |
| Admin tools | `admin.php?page=si-quick-order_tools` | Admin page | Admin compact |

Legacy admin URLs redirect to the new locations:

- `si-quick-order_style` → Impostazioni → Brand.
- `siqo-warehouse` → Impostazioni → Magazzino, with a link to the dashboard inventory view.
- `siqo-print-rules` → Impostazioni → Stampanti.
- If the related Pro module is not installed or not licensed, the redirect lands on Impostazioni → Licenza with an explanatory notice.

Redirects preserve capability checks and do not expose settings to unauthorized users.

### 5.5 Roles and capabilities

The existing SIQO user-meta hierarchy remains the source of role identity. A capability resolver maps it to module capabilities:

| Capability | Worker | Supervisor | Manager |
|---|---|---|---|
| `siqo_view_dashboard` | yes | yes | yes |
| `siqo_manage_service` | yes | yes | yes |
| `siqo_manage_inventory` | no | yes | yes |
| `siqo_manage_printers` | no | yes | yes |
| `siqo_manage_receipts` | no | yes | yes |
| `siqo_manage_reservations` | no | yes | yes |
| `siqo_view_reports` | no | yes | yes |
| `siqo_manage_staff` | no | no | yes |
| `siqo_manage_settings` | no | no | yes |

`manage_woocommerce` and `manage_options` retain their existing override behavior where already used. Workers cannot view costs, supplier data, settings, or other employees' shifts. The resolver is filterable so a site can restrict modules further without changing module code.

## 6. Brand System

### 6.1 Quick Order default

The default profile uses the existing Quick Order mark and a warm, high-contrast product identity:

- ink `#171512`
- surface `#F7F2E8`
- card `#FFFDF8`
- accent `#E0A32E`
- danger `#B23A2E`
- success `#4F6B4A`
- border `#E4DACB`
- radius `14px`

UI text uses a system sans stack for performance. Display type is reserved for restaurant-branded surfaces and can be overridden by the restaurant profile. The Quick Order wordmark is text plus the existing bolt mark; the logo is not redrawn in this project.

### 6.2 Restaurant profile

A restaurant profile contains:

- restaurant name, short name, legal name, address, phone, email, website;
- logo, compact icon, cover image, login background;
- palette tokens: primary, accent, surface, card, text, muted, success, warning, danger, border;
- typography: display family and UI family, loaded only when configured;
- radius, density, and motion preferences;
- light/dark behavior;
- customer copy: welcome text, closed message, order confirmation, footer note;
- receipt identity: header, footer, logo, optional QR;
- Quick Order credit visibility.

Profiles are stored as a named preset. The site has one active profile. Presets can be exported and imported as JSON. `Quick Order` and `Natilla` ship as presets; Natilla uses cream `#F6EFE6`, terracotta `#A9472F`, olive `#5E6B4F`, and ink `#241F1A`.

### 6.3 Theme modes

- Light: default for customer surfaces and front-of-house.
- Dark: optimized for kitchen, bar, and warehouse; low glare, high contrast, larger numeric emphasis.
- Dark mode can be selected per section by role and overridden by the user.
- The customer page defaults to light; the restaurant profile may choose dark.
- Print previews and printed documents are always light.

### 6.4 Brand boundaries

- Admin and staff: Quick Order mark plus restaurant name; no restaurant logo replaces the product mark in the primary position.
- Customer: restaurant logo and name dominate; Quick Order appears only as a small “Powered by Quick Order” credit, enabled by default and removable by the restaurant.
- The customer page never shows staff navigation, warehouse data, or internal order controls.

## 7. Shell and Status Bar

### 7.1 Staff shell

Desktop:

- height 64px, sticky at the top;
- left: Quick Order mark, restaurant name, current section;
- center: Dashboard, Sala, Magazzino, Stampanti, Scontrini; additional modules under “Altro”;
- right: service status, notifications, user menu, fullscreen;
- active item has a clear indicator and accessible current state.

Mobile:

- height 56px;
- logo, current section, menu button, fullscreen in the overflow menu;
- bottom tab bar for Dashboard, Sala, Magazzino, and Altro;
- safe-area padding on iOS;
- no horizontal overflow at 320px.

Fullscreen:

- requests browser fullscreen on the shell root;
- falls back to a CSS focus mode if the API is denied;
- keeps a visible exit control and supports `Esc`;
- remembers the preference per user;
- never hides critical alerts or the current section.

### 7.2 Admin bar

- compact bar below the WordPress admin bar, height 48px;
- Quick Order mark, restaurant name, Panoramica, Impostazioni, Gestione, Strumenti;
- no fullscreen;
- license and module status on the right;
- hidden on unrelated admin screens.

### 7.3 Customer bar

- restaurant logo and name;
- table badge when known;
- search field on desktop, expandable icon on mobile;
- fullscreen;
- cart button with item count and total;
- account action: login, account, or logout;
- service status;
- no staff links, no dashboard links, no admin links.

### 7.4 Navigation rules

- The current section is always visible.
- Modules the user cannot access are not rendered.
- A module that is inactive is not shown as a dead link; it is omitted or shown in settings only.
- The bar is keyboard navigable and traps no focus.
- The bar does not duplicate the theme navigation because app pages hide theme chrome.

## 8. Dashboard and Live Service

### 8.1 Entry and access

- `/gestione/` is created on activation or schema migration if missing, with the dashboard shortcode.
- The page is editable but must retain the shortcode for the dashboard to render.
- Access requires login and an authorized SIQO role.
- Direct access without permission returns a branded access-denied screen with a login or return link, not a blank page.
- “Apri tavolo” links to `/manage-tables/` with the table preselected; the dashboard does not duplicate the full POS.

### 8.2 Dashboard home

Cards and actions:

- covers and open tables;
- orders waiting, preparing, and ready;
- average service time;
- current service revenue;
- low-stock products and ingredients;
- printers offline or jobs failed;
- reservations for the current service, when enabled;
- shortcuts to Sala, Magazzino, Stampanti, Scontrini.

Alerts are actionable:

- order exceeding its target time;
- table without an update;
- product or ingredient unavailable;
- failed print job;
- reservation not assigned or arriving;
- stock count overdue.

### 8.3 Sala

- table map or table grid with states: free, open, waiting, preparing, ready, to be paid;
- list and detail of orders with table, number, items, station status, total, and age;
- filters by preparation point, status, and search by table or order number;
- actions: open table, update station status, print ticket, flag an issue;
- live refresh every 5 seconds, paused when the tab is hidden;
- manual refresh and “last updated” indicator.

### 8.4 Service statistics

Phase 1 shows the current day and current service:

- covers;
- average ticket;
- top products;
- preparation time by station;
- revenue by payment method.

Charts are lightweight and accessible. A table export is available. Historical comparisons and custom date ranges belong to the Reports module.

### 8.5 States

- loading skeletons that preserve layout;
- useful empty states with a next action;
- error state with retry and last successful update;
- permission denied with explanation;
- service closed with opening hours and next service;
- offline banner when polling fails repeatedly.

## 9. Inventory

### 9.1 Two-level model

**Product level**

- WooCommerce owns sellable stock.
- The dashboard reads `WC_Product::get_stock_quantity()` and stock status.
- Manual adjustments call `wc_update_product_stock()` and then record a movement.
- The plugin never maintains a second independent product balance.
- Existing `SIQO_Warehouse::get_stock()` behavior is reimplemented behind the same public method name using the reconciliation layer; old movement history remains available for audit.
- `SIQO_Warehouse::deduct_order()` is converted to a mirror recorder and unhooked from the product-stock path to avoid double deduction.

**Ingredient level**

- The plugin owns ingredient stock.
- Each ingredient has a canonical unit and optional conversions.
- Stock changes through receipts, adjustments, stocktakes, recipe deductions, and reversals.
- Negative stock is blocked by default; an authorized manager can allow it with a warning and an audit note.

### 9.2 Recipes

- A recipe links a product or variation to one or more ingredients.
- Each line has quantity, unit, waste percentage, and optional note.
- Recipe yield supports batches.
- A product can have no recipe; it is flagged as “recipe missing” if inventory tracking is enabled for its category.
- Modifiers and extras can have their own recipe lines in a later increment; the data model reserves a modifier reference.

### 9.3 Order deduction

- Trigger is configurable: payment complete, processing, or completed; default is processing.
- Deduction is idempotent per order and per item using order meta.
- WooCommerce reduces product stock according to its own settings; the plugin records a mirror movement only.
- Ingredient quantities are converted to canonical units and deducted once.
- Cancellation and refund behavior is configurable: no restock, restock products only, or restock products and ingredients.
- A failed deduction creates an alert and a retry action; it never silently loses the movement.

### 9.4 Suppliers and lots

- Suppliers have contact data, notes, and active status.
- Receiving stock creates a lot with lot code, quantity, expiry date, supplier, and receiving reference.
- Expiry tracking is optional per ingredient.
- FEFO is used when consuming lots; if lots are disabled, the ingredient ledger is used directly.
- Expiry alerts use a configurable window, default 7 days.
- Stocktake compares counted quantity with ledger quantity and creates an adjustment with a reason.

### 9.5 Costing

- Ingredient cost uses a configurable method: latest receipt cost or weighted average.
- Recipe cost is the sum of ingredient costs plus waste.
- Food cost percentage is shown only when a selling price exists; no price is invented.
- Cost changes are historical; editing a supplier price does not rewrite past receipts.

### 9.6 Inventory interface

- Panoramica: low stock, expiring lots, negative balances, missing recipes, unmapped products.
- Prodotti: Woo stock, thresholds, movements, adjustment, history.
- Ingredienti: identity, unit, cost, supplier, stock, lots, threshold.
- Ricette: product-to-ingredient editor with search, quantity, unit, waste, and theoretical cost.
- Fornitori: directory and linked ingredients.
- Lotti: lot list, expiry, remaining quantity, supplier.
- Movimenti: unified ledger with filters, reference, user, and before/after.
- Inventario: stocktake sessions and adjustments.
- Impostazioni: units, negative stock policy, deduction trigger, restock policy, expiry window, cost method.

### 9.7 Inventory stages

- Stage A: WooCommerce product stock and coherent movements.
- Stage B: ingredients, recipes, order deduction, reversals.
- Stage C: suppliers, lots, expiry, stocktake.
- Stage D: costing, food cost, reorder suggestions.

Delivery Phase 1 includes Stage A only. Phase 2 includes Stage B. Phase 3 includes Stages C and D.

## 10. Printers and Receipts

### 10.1 Printer registry

Each printer has:

- name and station;
- connection type: `network`, `browser`, `usb_printnode`;
- network host and port, default 9100;
- PrintNode device ID;
- paper width 58 or 80 mm;
- ESC/POS profile or browser template;
- copies;
- active status;
- last status, last seen, last error;
- optional location note.

### 10.2 Adapters

- Network ESC/POS: server-side socket to a configured host and port; test connection and test print; no arbitrary URLs.
- Browser/PDF: renders a print-friendly document in an iframe or new window; works without server printing.
- USB/PrintNode: sends to the configured PrintNode device; credentials stored encrypted and masked.

Adapters share an interface with `validate()`, `send()`, and `status()` behavior. Failures return a normalized error code and a safe message. The UI never exposes credentials.

### 10.3 Routing

Routing rules can target:

- preparation point;
- product category;
- product;
- order type;
- fallback printer.

Rules are ordered and first match wins. A preview shows which printer receives a sample order. A printer can be disabled without deleting its rules.

### 10.4 Print jobs

- Every print creates a persistent job with document type, order, printer, payload hash, status, attempts, and timestamps.
- Jobs are idempotent; repeated events do not duplicate a ticket.
- Offline printers leave jobs queued; retries use backoff and stop after a configurable limit.
- Failed jobs create dashboard alerts and can be retried or cancelled.
- Manual reprint creates a new job linked to the original.

### 10.5 Documents

**Comanda**

- station-grouped items;
- table, order number, time, quantities, notes, allergens, modifiers;
- prices optional and hidden by default;
- configurable header, footer, logo, QR, and font size.

**Ricevuta non fiscale**

- customer receipt with items, quantities, prices, totals, coperto, payment method;
- no fiscal value, no fiscal numbering, no VAT device integration;
- archive, preview, reprint, and audit;
- configurable retention, default 90 days, with “keep indefinitely” as an option.

### 10.6 Printers interface

- Panoramica: online/offline, failed jobs, last documents.
- Stampanti: registry and status.
- Routing: rule matrix and preview.
- Template: visual settings with live preview.
- Coda: queued, failed, printed; retry and cancel.
- Archivio: receipts by order, date, and status.

Phase 1 includes the adapter layer, network, browser/PDF, USB/PrintNode, the queue, comande, and base receipts. USB/PrintNode is enabled once a device is configured; hardware validation is required before production sign-off.

## 11. Settings

### 11.1 Admin structure

Menu:

- Panoramica;
- Impostazioni;
- Strumenti.

Settings layout:

- left section navigation;
- right content with cards;
- sticky save bar;
- unsaved-changes warning;
- global settings search;
- live preview for brand, bar, customer page, and receipt.

Sections:

1. Ristorante e brand.
2. Pagine e accessi.
3. Catalogo.
4. Magazzino.
5. Stampanti.
6. Scontrini.
7. Prenotazioni.
8. Personale.
9. Avanzate.
10. Licenza.

Sections for modules that are not installed are hidden. Sections for installed but unlicensed Pro modules remain visible only to managers, with the license state and a link to activate the module; operational fields are disabled until the module is active.

### 11.2 Preservation rule

Every existing option key remains readable. The redesign may change labels, grouping, and field order, but not delete values. A migration map is maintained with the code. Unknown legacy keys are shown in Avanzate, not discarded.

Existing taxonomy managers for zones, ingredients, allergens, temperatures, stickers, and statuses remain available under Catalogo. Their behavior is preserved, including create, update, and delete rules.

### 11.3 Field behavior

- Inline help and examples.
- Units shown next to numeric fields.
- Conditional fields appear only when the parent option is enabled.
- Validation before save with field-level errors.
- Defaults can be restored per section.
- Import/export JSON for the full configuration.
- Reset requires confirmation and creates a backup of the previous configuration.
- Settings changes are logged with user, time, and changed keys; values that are secrets are redacted.

### 11.4 Diagnostics

Strumenti shows:

- PHP, WordPress, WooCommerce, Free, and Pro versions;
- database schema versions;
- app page mapping and shell mode;
- license status;
- HPOS compatibility;
- cron events and overdue jobs;
- printer status and queue depth;
- stock reconciliation status;
- cache and asset versions.

## 12. Customer App

### 12.1 Layout

- Dedicated shell with no theme header or footer.
- Top bar: restaurant logo and name, table badge, search, fullscreen, service status.
- Desktop: category rail, three-column product grid, sticky cart panel.
- Tablet: two-column grid, collapsible cart.
- Mobile: horizontal categories, two-column grid, bottom cart bar, cart sheet.
- No giant QR. The table arrives from a validated query parameter or a compact selector. Generated QR codes remain in the dashboard and printed materials.

### 12.2 Product interaction

- Product card: image, name, short description, allergens, price, availability, add/stepper.
- Variants open a modal with required selections and price updates.
- Sold-out products are visibly disabled and cannot be added.
- Search is instant, debounced, and keyboard accessible.
- Images lazy-load with fixed aspect ratios to avoid layout shift.

### 12.3 Cart and checkout

- Cart lines with quantity, notes, variant, and line total.
- Covers field when required by settings.
- Table selection only when not supplied by QR. A table ID from the URL must exist and be active; otherwise the customer sees the selector with an explanatory message.
- Login is required to submit; browsing remains possible.
- Submission is disabled while sending and guarded against double submit.
- Confirmation shows order number, table, status, and next action.
- Errors preserve the cart and explain whether the issue is login, stock, service closure, or network.

### 12.4 States and quality

- skeleton loading;
- empty menu;
- service closed;
- product unavailable;
- cart empty;
- submitting;
- success;
- error with retry.
- 44px touch targets, AA contrast, visible focus, reduced-motion support.
- Tested at 360, 390, 768, 1024, and 1440px.
- CSS is scoped under `siqo-customer` and does not load the admin settings stylesheet.

## 13. Data Model

All tables use the WordPress prefix and a schema version option. Timestamps are stored in UTC and displayed in the site timezone. Quantities use `DECIMAL(12,3)`.

### 13.1 Free/core tables

Existing tables remain:

- `siqo_warehouse_movements`;
- `siqo_warehouse_thresholds`.

### 13.2 Inventory

- `siqo_ingredients`: id, name, slug, category, canonical_unit, stock, low_stock, reorder_point, cost, cost_method, supplier_id, expiry_tracking, active, created_at, updated_at.
- `siqo_ingredient_lots`: id, ingredient_id, lot_code, quantity_received, quantity_remaining, expiry_date, supplier_id, received_at, reference, status.
- `siqo_recipes`: id, product_id, variation_id, ingredient_id, quantity, unit, waste_pct, modifier_ref, active, created_at, updated_at.
- `siqo_suppliers`: id, name, contact_name, email, phone, notes, active, created_at, updated_at.
- `siqo_stock_movements`: id, item_type, item_id, lot_id, type, quantity, stock_before, stock_after, reference_type, reference_id, note, created_by, created_at.
- `siqo_stocktakes`: id, status, started_at, closed_at, created_by, note.
- `siqo_stocktake_lines`: id, stocktake_id, item_type, item_id, expected, counted, difference, note.

Indexes: item type/id, lot, reference, created_at, expiry_date, active.

### 13.3 Printers and receipts

- `siqo_printers`: id, name, station, connection, host, port, device_id, paper_width, profile, copies, active, last_status, last_seen, last_error, created_at, updated_at.
- `siqo_print_rules`: id, target_type, target_id, printer_id, priority, active.
- `siqo_print_jobs`: id, printer_id, order_id, document_type, template_id, payload_hash, payload, status, attempts, last_error, scheduled_at, printed_at, created_by, created_at.
- `siqo_receipts`: id, order_id, job_id, document_type, reference, snapshot, created_by, created_at, reprinted_from.
- Print templates use a `siqo_print_template` custom post type with meta for document type, paper width, header, footer, logo, and options.

### 13.4 Reservations and staff

- `siqo_reservations`: id, service_date, service_time, duration_minutes, party_size, customer_name, phone, email, table_id, status, source, notes, created_by, created_at, updated_at.
- `siqo_shifts`: id, user_id, role, start_at, end_at, status, notes, created_by, created_at, updated_at.
- `siqo_time_entries`: id, user_id, shift_id, clock_in, clock_out, note.

Reservation statuses: pending, confirmed, seated, cancelled, no-show. Default slot is 15 minutes, default duration 90 minutes, both configurable. Internal entry only in Phase 4; public booking is not included.

### 13.5 Relationships

- Ingredient → supplier (optional).
- Ingredient → lots (optional).
- Product/variation → recipe lines → ingredients.
- Order → stock movements via reference.
- Order → print jobs → receipts.
- Table → reservations.
- User → shifts and time entries.

Foreign keys are not enforced, following WordPress practice. Service methods enforce integrity and deletion rules. Movements and receipts are never hard-deleted by normal UI actions.

## 14. Data Flows

### 14.1 Product stock adjustment

1. Authorized user submits a quantity and reason.
2. Service validates capability, nonce, product, and quantity.
3. `wc_update_product_stock()` updates WooCommerce.
4. Service records a movement with before and after values.
5. Dashboard refreshes and logs the action.

### 14.2 Order and ingredient deduction

1. Order reaches the configured trigger status.
2. Service checks the idempotency flag on the order.
3. Product movements are mirrored, not re-applied.
4. Each order item resolves its recipe and ingredient quantities.
5. Quantities are converted to canonical units.
6. Ingredient ledger and lots are decremented with FEFO.
7. The flag is set and the result is logged.
8. Failure creates an alert and leaves the order retryable.

### 14.3 Print flow

1. Event selects a document template and routing rule.
2. Renderer builds a payload and hash.
3. Queue creates or reuses an idempotent job.
4. Adapter sends the job.
5. Success stores status and receipt snapshot; failure schedules a retry and alerts.

### 14.4 Live dashboard polling

1. Dashboard requests a summary and active orders.
2. Endpoint validates session, nonce, and capability.
3. Service reads cached summary and current order data.
4. Response includes `updated_at` and minimal deltas.
5. Client pauses polling when hidden and resumes on focus.

## 15. Security

- Every admin action checks capability and nonce.
- Every REST route has a `permission_callback`.
- All SQL uses `$wpdb->prepare()` with placeholders; table names are allowlisted.
- All output is escaped by context.
- Settings secrets are masked in UI and logs.
- PrintNode credentials are encrypted with a site-specific key and never returned in API responses.
- Network printing only accepts configured hosts and ports; redirects are disabled; arbitrary URLs are rejected.
- File uploads go through the media library; SVG is sanitized or rejected.
- Rate limits apply to print actions, stock adjustments, and customer search.
- Audit entries record sensitive changes without storing payment or fiscal data.
- The customer app never exposes order internals, staff data, costs, or inventory levels.

## 16. Migration and Compatibility

- New schema uses `siqo_suite_db_version`; each table is created or upgraded with `dbDelta`.
- Existing options are not renamed without a read-compatible alias.
- Existing pages and shortcodes continue to work.
- Existing admin URLs redirect to the new sections.
- Existing `SIQO_Warehouse` public methods gain compatibility wrappers.
- Legacy movements are migrated to the unified ledger with `item_type=product`.
- Product opening balances are initialized from WooCommerce stock. If WooCommerce does not manage stock, the product is marked “not tracked” instead of assuming zero.
- Old tables remain for one major release and are read-only; removal requires an explicit maintenance action.
- Existing Pro print rules are imported into the printer registry when the referenced printer can be resolved; unresolved rules are preserved and flagged for manual remapping.
- If WooPrint printers exist, the migration offers an import step; if not, the printer section starts with an empty-state setup wizard.
- HPOS remains supported; order access uses WooCommerce CRUD APIs.
- Rollback: module feature flags can disable new dashboard modules; the previous shortcode templates remain available for one release.

## 17. Performance

- Dashboard summary is cached for 5 seconds and invalidated by order or stock events.
- Active order queries are limited and indexed.
- Customer product queries are cached per session and invalidated by stock or price changes.
- Images use responsive sizes and lazy loading.
- Shell CSS and JS are versioned and loaded only on app pages.
- No global admin CSS is loaded on customer pages.
- Target: dashboard interactive under 2 seconds with 500 products and 50 open orders on the production host.
- Polling stops when the tab is hidden and backs off on repeated failures.

## 18. Accessibility and Responsiveness

- WCAG 2.2 AA target for contrast, focus, labels, and error identification.
- Keyboard navigation for bar, tabs, modals, cart, and tables.
- Visible focus rings that work on light and dark themes.
- Touch targets at least 44×44px.
- `prefers-reduced-motion` respected for transitions and skeletons.
- No information conveyed by color alone; statuses include text or icons.
- Tested at 320, 360, 390, 768, 1024, 1280, and 1440px.
- Fullscreen mode remains operable with keyboard and screen readers.

## 19. Testing

### 19.1 Static and integration

- `php -l` on all changed files.
- WordPress Plugin Check and WPCS where available.
- PHPUnit integration tests for stock ledger, recipe conversion, idempotency, refunds, routing, and settings migration.
- Database migration test from the current live schema and from a fresh install.
- Role and capability matrix test.

### 19.2 End-to-end

- Customer: browse, search, variant, add, login, table selection, submit, confirmation, sold-out, closed service.
- Dashboard: access by role, live refresh, open table, station update, alerts, offline state.
- Inventory: product adjustment, movement history, recipe deduction, cancellation restock, stocktake.
- Printers: test print, routing preview, queue, offline retry, reprint, receipt archive.
- Settings: preserve legacy values, import/export, validation, redirects, diagnostics.
- Responsive and accessibility checks at target widths.

### 19.3 Hardware

- Network ESC/POS test on the production printer model.
- USB/PrintNode test with the actual device or a staging device.
- Browser/PDF test in Chrome and Safari.
- Paper width and character encoding verified for Italian accented characters.

### 19.4 Performance

- Dashboard with 500 products and 50 open orders.
- Customer menu with the full Natilla catalog.
- Print queue with 100 pending and 20 failed jobs.
- Cache enabled and disabled.

## 20. Delivery Phases

### Phase 1 — Foundation and first usable release

- Quick Order brand, restaurant profile, presets.
- Shared shell, status bar, fullscreen, admin compact bar.
- `/gestione/` dashboard home, Sala, live service, Phase 1 statistics.
- `/flash-orders-ajax/` redesign.
- Settings redesign with all existing options preserved.
- Product stock integration and reconciliation (Inventory Stage A).
- Printer registry, network, browser/PDF, and USB/PrintNode adapters, queue, routing, comande, base receipts.
- Roles, diagnostics, import/export, feature flags.
- Automated tests for Phase 1.

### Phase 2 — Ingredients and recipes

- Ingredient catalog, units, conversions, suppliers.
- Recipe editor and theoretical cost.
- Order deduction, cancellation/refund policy, alerts.
- Low-stock and missing-recipe views.

### Phase 3 — Lots and stock control

- Lots, expiry, FEFO.
- Receiving, stocktakes, adjustments.
- Costing and food cost.
- Reorder suggestions.

### Phase 4 — Reservations, staff, reports

- Internal reservation calendar and table assignment.
- Staff directory, shifts, clock-in/out, timesheet export.
- Historical service, product, inventory, and staff reports.
- Export and scheduled summaries.

Each phase receives its own implementation spec before work begins. This document defines the shared contracts and Phase 1 scope.

## 21. Acceptance Criteria

### Phase 1

1. Every app page renders the correct shell mode; customer pages show no staff navigation; admin pages show no fullscreen.
2. A restaurant profile changes logo, name, colors, and customer copy without code changes; Quick Order branding remains visible in staff/admin.
3. `/flash-orders-ajax/` has no theme header/footer, no giant QR, no overlapping navigation, and no horizontal overflow at 360, 390, 768, 1024, and 1440px.
4. A customer can browse, search, select a table, add items, log in, submit an order, and see confirmation.
5. `/gestione/` enforces roles, shows live tables and orders, refreshes every 5 seconds, and handles offline and empty states.
6. Product stock displayed in the dashboard matches WooCommerce; manual adjustments update WooCommerce and create exactly one movement.
7. Placing an order does not double-deduct product stock.
8. A test print succeeds on network, browser, and configured USB/PrintNode adapters; an offline printer queues and retries; a failed job is visible and retryable.
9. Existing settings values survive the redesign; legacy admin URLs redirect; no PHP notices or warnings appear on settings screens.
10. All new endpoints verify capability and nonce; SQL is prepared; output is escaped.
11. Shell and customer assets are not loaded on unrelated pages.
12. Plugin Check reports no new errors attributable to the changes.

### Phase 2

1. Ingredient quantities convert correctly across supported units.
2. An order deducts each ingredient once at the configured trigger.
3. Cancellation or refund follows the configured restock policy exactly once.
4. Low-stock and missing-recipe alerts are accurate.

### Phase 3

1. Receiving creates a lot and increases the ledger.
2. FEFO consumes the earliest expiring lot first.
3. Expiry alerts respect the configured window.
4. A stocktake creates a traceable adjustment and reconciles the ledger.

### Phase 4

1. A reservation cannot double-book a table beyond capacity.
2. Shift and time entries are visible by role and exportable.
3. Reports match WooCommerce totals for the same period.

## 22. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Scope is large and Phase 1 is still broad | Build vertical slices with feature flags; merge shell and flash first, then dashboard, stock, printers. |
| Product stock diverges between WooCommerce and legacy ledger | Reconcile from WooCommerce, mark untracked products, keep legacy movements read-only, add a reconciliation report. |
| Printer hardware unavailable during development | Adapter mocks and queue tests; on-site validation before release. |
| Pro license not active in test | Generate a staging license; base Free behavior must remain testable without Pro. |
| wp-admin access blocked by security layers | Restore a QA account or use a staging clone with the same plugins; do not weaken production security. |
| Theme and plugin CSS collide | Dedicated app template, scoped CSS, no settings stylesheet on customer pages, cache purge procedure. |
| Existing data is inconsistent or incomplete | Dry-run migration, backups, opening-balance report, no destructive deletion. |
| Fiscal expectations exceed scope | Explicit non-fiscal labeling and a documented integration boundary. |
| Mobile use in kitchen is harsh | Dark mode, large targets, high contrast, offline states, no reliance on hover. |
| Duplicate print or stock events | Idempotency keys and movement/job uniqueness checks. |

## 23. Assumptions

- One restaurant per installation.
- The restaurant uses WooCommerce for products and prices.
- Pro modules are licensed for production use.
- Reservations are entered internally; public booking is out of scope.
- Staff shifts are planning and simple time tracking, not payroll.
- Receipts are non-fiscal.
- The existing Quick Order logo is retained.
- Natilla is a preset and reference implementation, not a hardcoded brand.
- The production site remains Italian; the plugin keeps its existing translation domain and English strings.

## 24. Open Questions Resolved

The following were decided during design and are not open:

- Dashboard location: frontend `/gestione/`.
- Warehouse model: WooCommerce product stock plus plugin ingredient ledger and recipes.
- Receipts: comande and non-fiscal receipts.
- Printers: network, browser/PDF, and USB/PrintNode.
- Restaurant section: live service, reservations, staff/shifts, service statistics.
- Permissions: existing SIQO roles.
- Settings: preserve all existing options and reorganize.
- Admin bar: compact, no fullscreen.
- Customer page: restaurant brand, no staff navigation, search and fullscreen only.
- Brand: Quick Order product brand, restaurant customization per install.
- Theme: light front-of-house, dark kitchen/bar/warehouse.
- Architecture: modular Free core plus Pro modules.
- First release: shell, live service, existing product stock, printers, receipts, customer redesign.

## 25. Next Step

After this spec is reviewed and approved, create the implementation plan for Phase 1. Phase 2–4 receive separate specs and plans before their implementation.

