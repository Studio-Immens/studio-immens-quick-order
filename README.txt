=== Studio Immens Quick Order ===
Contributors: innovazioneweb
Tags: woocommerce ordering, restaurant ordering, table management, food ordering, menu ordering, quick checkout, woocommerce
Donate link: https://studioimmens.com/donate
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: woocommerce

Streamline your restaurant's online ordering with WooCommerce. Product menus, table management, QR codes, and real-time order monitoring.

== Description ==

Studio Immens Quick Order transforms your WooCommerce store into a complete restaurant ordering system. Customers browse a visual menu, customize products (ingredients, variants, temperatures), and place orders directly to your kitchen.

= Key Features =

* **Interactive Product Menu** - Display WooCommerce products grouped by category in a card-based grid with images, prices, stock status, and filters
* **Table Management** - Visual grid of restaurant tables with color-coded status (free, occupied, paying). Assign orders to tables, manage running tabs, process payments
* **QR Code Generation** - Generate QR codes for menus or tables via shortcodes `[siqo_qr_code]` and `[siqo_qr_code_content]`
* **Custom Product Taxonomies** - Enable ingredients, allergens, temperatures, stickers, and macro categories for fine-grained product customization
* **Product Variations** - Full support for variable products with attribute selection, ingredient checkboxes, temperature choices
* **Real-Time Order Polling** - New orders appear automatically with configurable audio notifications
* **User Roles** - Worker, Supervisor, Manager role-based access for staff
* **Advanced Product Popup** - Gallery images, descriptions, ingredients, allergens, and variants in a modal
* **Order Filtering** - Filter orders by type (table, pickup, delivery) and status
* **Checkout Integration** - Pickup/delivery date and time selection at checkout
* **Customizable CSS** - 38 CSS variables for colors, borders, shadows, and animations via admin style page
* **Multiple Ordering Modes** - Card-based grid or compact list view
* **Translation Ready** - .pot file included, Italian and English translations bundled

= How It Works =

1. Create your WooCommerce products and assign them to categories
2. Use the `[SIQO_front_order_ajax_section]` shortcode to display the customer menu
3. Use the `[SIQO_manage_tables_section]` shortcode for staff table management
4. Configure settings, styles, and taxonomies in Quick Order > Settings
5. Staff monitor incoming orders in real-time with audio alerts

== Installation ==

1. Upload `studio-immens-quick-order` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Quick Order > Settings` and click "CREATE or UPDATE pages"
4. The plugin will create the necessary pages automatically
5. Alternatively, use the shortcodes on any page:

`[SIQO_front_order_ajax_section]` - Customer menu ordering
`[SIQO_manage_tables_section]` - Staff table management

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. Studio Immens Quick Order requires WooCommerce to be installed and active.

= Can I manage tables with the free version? =

Yes. The free version includes full table management with visual grid, color-coded status, order assignment, and payment processing.

= Is it mobile-friendly? =

Yes. The interface is fully responsive and works on smartphones, tablets, and desktops.

= Can customers customize products? =

Yes. You can enable custom taxonomies for ingredients, allergens, temperatures, and stickers. Customers can select/deselect ingredients and choose preparation options.

= Does it support variable products? =

Yes. Full support for WooCommerce variable products with attribute selection and price adjustments.

= How do I print receipts? =

The free version integrates with SI Print Receipts plugin for receipt printing. The PRO version adds automatic printing to kitchen thermal printers.

= Can I customize the appearance? =

Yes. Use the Quick Order > Style page to customize 38 CSS variables including colors, borders, shadows, and animations.

= Is the plugin translated? =

Yes. English and Italian translations are included. The plugin is translation-ready with a .pot file.

== Screenshots ==

1. Customer menu ordering interface with product cards
2. Table management grid with color-coded status
3. Product customization popup with ingredients and variants
4. Settings page with taxonomy configuration
5. Style customization page with CSS variable controls

== Changelog ==

= 1.2.0 =
* Added a plan editor with multiple floors, free table dragging and per floor layouts
* Added zones with background image, tint color and drag or resize on the map
* Added table shape (rect, square, round) and seat count in the table inspector
* Added zoom, fit and canvas growth so tables and zones always stay reachable
* Added a manage panel to create, rename and delete floors and zones, moving tables when needed
* Added keyboard support for moving tables and switching floors

= 1.1.8 =
* Added per-table keys so each QR code opens the menu with its own table preselected
* Added table sessions with expiry, order limits and automatic closing on payment
* Added first-order approval: the first order of a session waits for an operator before printing
* Deferred printing until approval, then printed through the configured print rules
* Added the order author badge on station cards and documents
* Added "Chiudi tutti" to close every active table session at once, with the closed count

= 1.1.7 =
* Open the active staff navigation group by default so "Tavoli" is visible on /gestione/ without hovering "Sala"

= 1.1.6 =
* Added the internal non-fiscal receipt and pre-bill engine with archive and reprint
* Added 80/58 mm receipt layouts, receipt settings and optional auto-print after payment
* Fixed receipt totals to include line taxes, fees and shipping
* Linked merged-table receipts to every order included in the document
* Fixed table opening and closing persistence in the custom tables schema
* Fixed an authentication bypass in the table order AJAX handler
* Restricted table payment and reset endpoints to authenticated staff
* Prevented empty table records for takeaway orders
* Improved the default receipt printer selector

= 1.1.5 =
* Redesigned the customer Quick Menu with search, categories, favorites and a responsive order drawer
* Renamed the customer page to /quick-menu/ and redirected legacy /flash-orders-ajax/ links

= 1.1.4 =
* Added coupon and customer discount selection to the table payment modal
* Added live discount quote before completing the payment
* Fixed pay modal line totals to include product quantity
* Fixed customer search colors inside the payment modal
* Fixed staff navigation dropdown hover gap
* Raised the table history sheet above the payment footer

= 1.1.3 =
* Added table management and station orders as native views inside /gestione/
* Made the management status bar static with corrected stacking for menus and table modals
* Updated dashboard table links to open the embedded table view
* Fixed table history order chips not opening order details and hid the broken story total

= 1.1.2 =
* Added frontend users and Quick Order role management to /gestione/
* Added WooCommerce coupon management to /gestione/
* Made catering events fully manageable from the frontend
* Updated the admin dashboard catering link to the frontend module
* Renamed the catering module and post type labels to "Eventi e Catering"
* Redesigned the table payment modal with customer details, item totals and a mobile layout
* Fixed the table card opening the payment modal on page load and restored the order creation panel

= 1.1.1 =
* Fixed the table management page fatal caused by the missing FOcheck() helper in Pro
* Added the catering management module and panel
* Cleaned the management bar and WordPress admin menu, removed legacy links and duplicate entries
* Normalized catering, table and taxonomy labels and admin placement

= 1.1.0 =
* Added the Quick Order management shell at /gestione/ with worker, supervisor and manager access levels
* Added the customer brand bar, account actions, fullscreen mode and validated table selection
* Reorganized settings by restaurant, brand, pages, catalog, inventory, printers, receipts, reservations, staff, advanced and license
* Added legacy settings redirects and license-aware module visibility
* Added the management dashboard with live orders, revenue, tables, low stock, reservations and staff-on-shift cards
* Preserved existing options, pages and custom tables during the upgrade

= 1.0.10 =
* Fixed the last inline handlers that still used the old FO_/fo_ names: the page settings close button, the macro-category history column controls, and the double-click status change on order story rows
* Added the missing public page open/close/toggle helpers so the settings page no longer depends on admin-only JavaScript

= 1.0.9 =
* Fixed 50 inline handlers that still called the pre-rename function names, so the admin settings navigation, the grid-view slider, the header/footer toggle, the order story buttons, the tab keyboard and quantity parsing, favourites, variants, customer search and the "AVVIA ORDINE" button work again
* Restored the handlers that had been renamed on the JavaScript side only (SIQO_ / siqo_ namespace) while the templates kept calling the old FO_ / fo_ names

= 1.0.8 =
* Fixed the operator roles: manager now implies supervisor and worker, multi-role accounts are handled, and the form on the user profile finally saves the role checkboxes it shows
* Fixed the table management page throwing a fatal error on screen because it called a renamed settings helper, and removed a leftover diagnostic dump
* Added an FO_price() shim so the Pro statistics panel can render prices again
* QR codes are generated in a folder created on demand and named after a hash of the content instead of the raw URL

= 1.0.7 =
* Fixed the QR list on the main page throwing errors: the QRgenerate directory is empty in the package (git cannot track empty folders), so the scan failed. The folder is now created on demand and shipped with the plugin.


= 1.0.6 =
* Fixed the custom style colours being reset: the helper meant to remove empty rows deleted every saved CSS value, so opening the Style page wiped the customisations
* Fixed the style activator deleting the default rows it had just written


= 1.0.5 =
* Restored the admin styling: the markup was renamed to the SIQO_ classes while the stylesheet still targeted the old FO_ ones, so the settings, style and main pages rendered unstyled.
* Registered the SIQO_ variant of the QR cloud, link and section styles.


= 1.0.4 =
* Fixed nonce hidden fields rendered with the legacy _fononce_ prefix, which broke order submission, table actions and post management
* Registered the SIQO_ AJAX action names actually used by the JavaScript (ordering, tables, posts and admin actions)
* Aligned admin AJAX action names and nonce selectors
* Removed an unnecessary remote request to ipinfo.io on every page load


= 1.0.3 =
* Fixed fatal error on the customer ordering page (undefined function SIQO_get_connection_info)
* Hardened AJAX nonce verification (reject request when nonce is missing or invalid)
* Aligned front-end AJAX action names with their registered handlers
* Fixed plugin activation and login issues caused by a UTF-8 BOM in PHP files


= 1.0.0 =
* Initial release
* Product menu with card and list views
* Table management with visual grid
* QR code generation via shortcodes
* Custom product taxonomies (ingredients, allergens, temperatures, stickers, macro categories)
* Real-time order polling with audio notifications
* User roles (Worker, Supervisor, Manager)
* CSS variable customization (38 properties)
* Pickup/delivery checkout integration
* Translation-ready with Italian and English

== Upgrade Notice ==


= 1.0.7 =
Removes PHP warnings on the Quick Order main page. Recommended update.


= 1.0.6 =
Keeps the custom style colours saved instead of resetting them. Recommended update.


= 1.0.5 =
Restores the missing admin styling on the settings, style and main pages. Recommended update.


= 1.0.4 =
Fixes AJAX nonce/action mismatches that blocked ordering and table actions, and removes a slow remote request on page load. Recommended update.


= 1.0.3 =
Fixes a fatal error on the customer ordering page and hardens AJAX security. Recommended update.


= 1.0.0 =
Initial release.
