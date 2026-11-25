<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 *
 * @link              https://studioimmens.com
 * @since             1.0.0
 * @package           Studio_Immens_Quick_Order
 *
 * @wordpress-plugin
 * Plugin Name:       Studio Immens Quick Order
 * Plugin URI:        https://github.com/Studio-Immens/studio-immens-quick-order
 * Description:       Manage restaurant orders, tables, kitchen stations, QR menus and table sessions from WooCommerce.
 * Version:           1.2.5
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
 * Main plugin file.
 */
define( 'SIQO_PLUGIN_FILE', __FILE__ );

/**
 * Currently plugin version.
 * Start at version 1.0.0
 */
define( 'STUDIO_IMMENS_QUICK_ORDER_VERSION', '1.2.5' );

/**
 * Currently database version for siqo_meta table.
 * Start at version 1.0.0
 */
define( 'STUDIO_IMMENS_QUICK_ORDER_META_VERSION', '1.0.0' );

if ( ! function_exists( 'siqo_add_inline_js' ) ) {
	/**
	 * Enqueue a small inline script through the WordPress script API.
	 *
	 * @param string $js     JavaScript code.
	 * @param string $handle Script handle.
	 */
	function siqo_add_inline_js( $js, $handle = 'siqo-inline' ) {
		if ( '' === trim( (string) $js ) ) {
			return;
		}
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, false, array(), STUDIO_IMMENS_QUICK_ORDER_VERSION, true );
		}
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $js, 'after' );
	}
}

if ( ! function_exists( 'siqo_print_inline_style' ) ) {
	/**
	 * Print an inline `<style>` element.
	 *
	 * WordPress ships wp_print_inline_script_tag() but has no equivalent for
	 * styles, so calling wp_print_inline_style_tag() is an undefined function.
	 * Some templates render a full HTML document (printed receipts, admin
	 * panels) where wp_add_inline_style() would be too late to be printed, so
	 * the stylesheet has to be emitted in place.
	 *
	 * @param string $css CSS code.
	 */
	function siqo_print_inline_style( $css ) {
		$css = (string) $css;

		if ( '' === trim( $css ) ) {
			return;
		}

		// Inside a <style> element only "</style" can leave the element: an HTML
		// parser treats everything else as CSS text, so neutralizing that
		// sequence is enough (and CSS is printed raw, exactly like core does
		// with wp_add_inline_style()).
		$css = preg_replace( '#</\s*style#i', '/style', $css );

		printf( '<style>%s</style>' . "\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS must not be HTML-escaped.
	}
}

if ( ! function_exists( 'siqo_add_inline_css' ) ) {
	/**
	 * Enqueue a small inline stylesheet through the WordPress style API.
	 *
	 * @param string $css    CSS code.
	 * @param string $handle Style handle.
	 */
	function siqo_add_inline_css( $css, $handle = 'siqo-inline' ) {
		if ( '' === trim( (string) $css ) ) {
			return;
		}
		if ( did_action( 'wp_head' ) ) {
			siqo_print_inline_style( $css );
			return;
		}
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, false, array(), STUDIO_IMMENS_QUICK_ORDER_VERSION );
		}
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}
}

if ( ! function_exists( 'siqo_ajax_capability_guard' ) ) {
	/**
	 * Enforce capabilities for privileged AJAX endpoints before their handlers run.
	 */
	function siqo_ajax_capability_guard() {
		$action = preg_replace( '/^wp_ajax_(?:nopriv_)?/', '', (string) current_action() );
		$guards = array(
			'siqo_flash_list_order_ajax'             => 'manage_woocommerce',
			'SIQO_flash_tab_order_ajax'              => 'manage_woocommerce',
			'FO_flash_tab_clear_table'               => 'manage_woocommerce',
			'FO_flash_tab_clear_all_tables'          => 'manage_woocommerce',
			'FO_flash_tab_pay_ajax'                  => 'manage_woocommerce',
			'SIQO_pay_coupon_quote'                  => 'manage_woocommerce',
			'SIQO_get_order_info_button_tab_string'  => 'manage_woocommerce',
			'FO_check_for_orders'                    => 'manage_woocommerce',
			'SIQO_check_for_orders'                  => 'manage_woocommerce',
			'FO_ajax_change_order_status'            => 'manage_woocommerce',
			'SIQO_ajax_change_order_status'          => 'manage_woocommerce',
			'SIQO_station_orders'                    => 'manage_woocommerce',
			'SIQO_station_update_status'             => 'manage_woocommerce',
			'siqo_order_approve'                     => 'manage_woocommerce',
			'siqo_order_reject'                      => 'manage_woocommerce',
			'siqo_table_open'                        => 'manage_tables',
			'FO_insert_product_ajax'                 => 'manage_options',
			'FO_insert_macro_cat_ajax'               => 'manage_options',
			'FO_delete_macro_cat_ajax'               => 'manage_options',
			'FO_insert_post_ajax'                    => 'manage_options',
			'FO_update_post_macro_cat_ajax'          => 'manage_options',
			'FO_remove_post_macro_cat_ajax'          => 'manage_options',
			'FO_update_post_ajax'                    => 'manage_options',
			'FO_delete_post_ajax'                    => 'manage_options',
			'FO_trash_post_ajax'                     => 'manage_options',
			'siqo_delete_QR_images'                  => 'manage_options',
			'siqo_save_variation_custom_field'       => 'edit_products',
			'siqo_save_tax_image'                    => 'manage_product_terms',
			'siqo_save_post_extra_field'             => 'edit_posts',
			'siqo_save_pickup_delivery'              => 'edit_shop_orders',
		);
		if ( ! isset( $guards[ $action ] ) ) {
			return;
		}
		$capability = $guards[ $action ];
		$allowed    = current_user_can( $capability );
		if ( ! $allowed && 'manage_tables' === $capability && class_exists( 'SIQO_Management' ) ) {
			$allowed = SIQO_Management::can( $capability );
		}
		if ( ! $allowed ) {
			wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
		}
	}
	foreach ( array(
		'siqo_flash_list_order_ajax',
		'SIQO_flash_tab_order_ajax',
		'FO_flash_tab_clear_table',
		'FO_flash_tab_clear_all_tables',
		'FO_flash_tab_pay_ajax',
		'SIQO_pay_coupon_quote',
		'SIQO_get_order_info_button_tab_string',
		'FO_check_for_orders',
		'SIQO_check_for_orders',
		'FO_ajax_change_order_status',
		'SIQO_ajax_change_order_status',
		'SIQO_station_orders',
		'SIQO_station_update_status',
		'siqo_order_approve',
		'siqo_order_reject',
		'siqo_table_open',
		'FO_insert_product_ajax',
		'FO_insert_macro_cat_ajax',
		'FO_delete_macro_cat_ajax',
		'FO_insert_post_ajax',
		'FO_update_post_macro_cat_ajax',
		'FO_remove_post_macro_cat_ajax',
		'FO_update_post_ajax',
		'FO_delete_post_ajax',
		'FO_trash_post_ajax',
		'siqo_delete_QR_images',
		'siqo_save_variation_custom_field',
		'siqo_save_tax_image',
		'siqo_save_post_extra_field',
		'siqo_save_pickup_delivery',
	) as $siqo_guarded_action ) {
		add_action( 'wp_ajax_' . $siqo_guarded_action, 'siqo_ajax_capability_guard', 0 );
		add_action( 'wp_ajax_nopriv_' . $siqo_guarded_action, 'siqo_ajax_capability_guard', 0 );
	}
}

if ( ! function_exists( 'siqo_json_post' ) ) {
	/**
	 * Read a JSON POST field and return a validated array.
	 *
	 * @param string $key POST field name.
	 * @return array
	 */
	function siqo_json_post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller checks the AJAX nonce before calling this helper.
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- JSON is decoded and each field is sanitized by the caller.
		$raw = wp_unslash( $_POST[ $key ] );
		if ( is_array( $raw ) ) {
			return array_map( 'sanitize_text_field', $raw );
		}
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}

if ( ! function_exists( 'siqo_posted_array' ) ) {
	/**
	 * Read and recursively sanitize an array POST field.
	 *
	 * @param string $key POST field name.
	 * @return array
	 */
	function siqo_posted_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller checks the AJAX nonce before calling this helper.
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Sanitized immediately below.
		$raw = wp_unslash( $_POST[ $key ] );
		if ( function_exists( 'SIQO_recursive_sanitize_text_field' ) ) {
			return (array) SIQO_recursive_sanitize_text_field( $raw );
		}
		return (array) map_deep( $raw, 'sanitize_text_field' );
	}
}

if ( ! function_exists( 'siqo_str_contains' ) ) {
	/**
	 * PHP 7.4-compatible replacement for siqo_str_contains().
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	function siqo_str_contains( $haystack, $needle ) {
		return '' === (string) $needle || false !== strpos( (string) $haystack, (string) $needle );
	}
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-studio-immens-quick-order-activator.php
 */
function siqo_activate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-studio-immens-quick-order-activator.php';
	SIQO_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-studio-immens-quick-order-deactivator.php
 */
function siqo_deactivate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-studio-immens-quick-order-deactivator.php';
	SIQO_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'siqo_activate_plugin' );
register_deactivation_hook( __FILE__, 'siqo_deactivate_plugin' );

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
function siqo_run_plugin() {

	$plugin = new Studio_Immens_Quick_Order();
	$plugin->run();

}
siqo_run_plugin();

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
}, 10, 2 );
