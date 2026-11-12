<?php

/**
 *
 * @link              https://studioimmens.com
 * @since             1.0.0
 * @package           Studio_Immens_Quick_Order
 *
 * @wordpress-plugin
 * Plugin Name:       Studio Immens Quick Order
 * Plugin URI:        https://studioimmens.com/quick-order
 * Description:       Studio Immens Quick Order is the ultimate solution for streamlining restaurant order management on your WooCommerce-powered website. With this powerful plugin, you can supercharge your restaurant's online ordering process.
 * Version:           1.0.7
 * Author:            Studio Immens
 * Author URI:        https://studioimmens.com
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       studio-immens-quick-order
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register with WP Consent API to declare support and follow guidelines.
 */
add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

/**
 * Currently plugin version.
 * Start at version 1.0.0
 */
define( 'STUDIO_IMMENS_QUICK_ORDER_VERSION', '1.0.7' );

/**
 * Currently database version for siqo_meta table.
 * Start at version 1.0.0
 */
define( 'STUDIO_IMMENS_QUICK_ORDER_META_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-studio-immens-quick-order-activator.php
 */
function activate_studio_immens_quick_order() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-studio-immens-quick-order-activator.php';
	Studio_Immens_Quick_Order_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-studio-immens-quick-order-deactivator.php
 */
function deactivate_studio_immens_quick_order() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-studio-immens-quick-order-deactivator.php';
	Studio_Immens_Quick_Order_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_studio_immens_quick_order' );
register_deactivation_hook( __FILE__, 'deactivate_studio_immens_quick_order' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-studio-immens-quick-order.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_studio_immens_quick_order() {

	$plugin = new Studio_Immens_Quick_Order();
	$plugin->run();

}
run_studio_immens_quick_order();

// === LINK RAPIDI NELLA PAGINA PLUGIN ===
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $prepend = array(
        'dashboard' => '<a href="' . esc_url(admin_url('admin.php?page=si-quick-order')) . '">' . esc_html__('Dashboard', 'studio-immens-quick-order') . '</a>',
    );
    $append = array(
        'settings' => '<a href="' . esc_url(admin_url('admin.php?page=si-quick-order_settings')) . '">' . esc_html__('Settings', 'studio-immens-quick-order') . '</a>',
    );
    return array_merge($prepend, $links, $append);
});

add_filter('plugin_row_meta', function($meta, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $meta[] = '<a href="https://docs.studioimmens.com/studio-immens-quick-order/" target="_blank" rel="noopener">' . esc_html__('Documentazione', 'studio-immens-quick-order') . '</a>';
    }
    return $meta;
}, 10, 2);
