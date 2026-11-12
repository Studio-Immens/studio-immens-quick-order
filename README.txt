=== Studio Immens Quick Order ===
Contributors: innovazioneweb
Tags: woocommerce ordering, restaurant ordering, table management, food ordering, menu ordering, quick checkout, woocommerce
Donate link: https://studioimmens.com/donate
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3
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

= 1.0.3 =
Fixes a fatal error on the customer ordering page and hardens AJAX security. Recommended update.

= 1.0.0 =
Initial release.
