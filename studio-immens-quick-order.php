<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 *
 * @link              https://studioimmens.com
 * @since             1.0.0
 * @package           SIQO_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Studio Immens Quick Order
 * Plugin URI:        https://github.com/Studio-Immens/studio-immens-quick-order
 * Description:       Manage restaurant orders, tables, kitchen stations, QR menus and table sessions from WooCommerce.
 * Version:           1.2.7
 * Requires at least: 6.5
 * Requires PHP:      7.4
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
define( 'STUDIO_IMMENS_QUICK_ORDER_VERSION', '1.2.7' );

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

if ( ! function_exists( 'siqo_allowed_html' ) ) {
	/**
	 * Allowed HTML for internal renderers, including admin form controls.
	 *
	 * @return array
	 */
	function siqo_allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		$common  = array(
			'class' => true,
			'id'    => true,
			'style' => true,
			'title' => true,
			'role'  => true,
			'aria-*' => true,
			'data-*' => true,
		);
		$allowed['form']     = array_merge( $common, array( 'method' => true, 'action' => true, 'enctype' => true, 'novalidate' => true ) );
		$allowed['input']    = array_merge( $common, array( 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'checked' => true, 'disabled' => true, 'required' => true, 'readonly' => true, 'min' => true, 'max' => true, 'step' => true, 'autocomplete' => true, 'inputmode' => true, 'pattern' => true ) );
		$allowed['select']   = array_merge( $common, array( 'name' => true, 'disabled' => true, 'required' => true ) );
		$allowed['option']   = array_merge( $common, array( 'value' => true, 'selected' => true, 'disabled' => true ) );
		$allowed['textarea'] = array_merge( $common, array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'required' => true, 'readonly' => true ) );
		$allowed['label']    = array_merge( $common, array( 'for' => true ) );
		$allowed['button']   = array_merge( $common, array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true ) );
		$allowed['fieldset'] = $common;
		$allowed['legend']   = $common;
		$allowed['svg']      = array_merge( $common, array( 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'xmlns' => true ) );
		$allowed['path']     = array_merge( $common, array( 'd' => true, 'fill' => true, 'stroke' => true ) );
		return $allowed;
	}
}

if ( ! function_exists( 'siqo_sanitize_css' ) ) {
	/**
	 * Remove constructs that must not appear in inline CSS.
	 *
	 * @param string $css CSS code.
	 * @return string
	 */
	function siqo_sanitize_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		return str_ireplace( array( '@import', 'expression(', 'javascript:', '</style', '<style' ), '', $css );
	}
}

if ( ! function_exists( 'siqo_print_inline_style_tag' ) ) {
	/**
	 * Print inline CSS with a compatibility fallback for older WordPress cores.
	 *
	 * @param string $css CSS code.
	 */
	function siqo_print_inline_style_tag( $css ) {
		$css = siqo_sanitize_css( $css );
		if ( '' === trim( (string) $css ) ) {
			return;
		}
		if ( function_exists( 'wp_print_inline_style_tag' ) ) {
			wp_print_inline_style_tag( $css );
			return;
		}
		$handle = 'siqo-inline-style';
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, false, array(), STUDIO_IMMENS_QUICK_ORDER_VERSION );
		}
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
		wp_print_styles( $handle );
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
		$css = siqo_sanitize_css( $css );
		if ( '' === trim( (string) $css ) ) {
			return;
		}
		if ( did_action( 'wp_head' ) ) {
			siqo_print_inline_style_tag( $css );
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
			'SIQO_flash_tab_clear_table'               => 'manage_woocommerce',
			'SIQO_flash_tab_clear_all_tables'          => 'manage_woocommerce',
			'SIQO_flash_tab_pay_ajax'                  => 'manage_woocommerce',
			'SIQO_pay_coupon_quote'                  => 'manage_woocommerce',
			'SIQO_get_order_info_button_tab_string'  => 'manage_woocommerce',
			'SIQO_check_for_orders'                    => 'manage_woocommerce',
			'SIQO_check_for_orders'                  => 'manage_woocommerce',
			'SIQO_ajax_change_order_status'            => 'manage_woocommerce',
			'SIQO_ajax_change_order_status'          => 'manage_woocommerce',
			'SIQO_station_orders'                    => 'manage_woocommerce',
			'SIQO_station_update_status'             => 'manage_woocommerce',
			'siqo_order_approve'                     => 'manage_woocommerce',
			'siqo_order_reject'                      => 'manage_woocommerce',
			'siqo_table_open'                        => 'manage_tables',
			'SIQO_insert_product_ajax'                 => 'manage_options',
			'SIQO_insert_macro_cat_ajax'               => 'manage_options',
			'SIQO_delete_macro_cat_ajax'               => 'manage_options',
			'SIQO_insert_post_ajax'                    => 'manage_options',
			'SIQO_update_post_macro_cat_ajax'          => 'manage_options',
			'SIQO_remove_post_macro_cat_ajax'          => 'manage_options',
			'SIQO_update_post_ajax'                    => 'manage_options',
			'SIQO_delete_post_ajax'                    => 'manage_options',
			'SIQO_trash_post_ajax'                     => 'manage_options',
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
		'SIQO_flash_tab_clear_table',
		'SIQO_flash_tab_clear_all_tables',
		'SIQO_flash_tab_pay_ajax',
		'SIQO_pay_coupon_quote',
		'SIQO_get_order_info_button_tab_string',
		'SIQO_check_for_orders',
		'SIQO_check_for_orders',
		'SIQO_ajax_change_order_status',
		'SIQO_ajax_change_order_status',
		'SIQO_station_orders',
		'SIQO_station_update_status',
		'siqo_order_approve',
		'siqo_order_reject',
		'siqo_table_open',
		'SIQO_insert_product_ajax',
		'SIQO_insert_macro_cat_ajax',
		'SIQO_delete_macro_cat_ajax',
		'SIQO_insert_post_ajax',
		'SIQO_update_post_macro_cat_ajax',
		'SIQO_remove_post_macro_cat_ajax',
		'SIQO_update_post_ajax',
		'SIQO_delete_post_ajax',
		'SIQO_trash_post_ajax',
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
		return is_array( $decoded ) ? map_deep( $decoded, 'sanitize_text_field' ) : array();
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

	$plugin = new SIQO_Plugin();
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
