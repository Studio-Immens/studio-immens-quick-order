<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Fired during plugin activation
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
class SIQO_Activator {

	/**
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		if ( class_exists( 'SIQO_Meta_DB' ) ) {
			SIQO_Meta_DB::create_meta_table();
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			set_transient( 'siqo_woocommerce_missing', 1, 60 );
			return;
		}

		if ( function_exists( 'SIQO_create_all_necessary_settings' ) ) {
			SIQO_create_all_necessary_settings();
		}

		if ( function_exists( 'SIQO_init_css_activator' ) ) {
			SIQO_init_css_activator();
		}

		if ( function_exists( 'SIQO_create_all_necessary_pages' ) ) {
			SIQO_create_all_necessary_pages();
		}

		if ( class_exists( 'SIQO_Management' ) ) {
			SIQO_Management::install();
		}

		if ( function_exists( 'SIQO_admin_autorole' ) ) {
			SIQO_admin_autorole();
		}
	}
}
