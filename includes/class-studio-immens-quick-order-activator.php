<?php

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
class Studio_Immens_Quick_Order_Activator {

	/**
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		SIQO_create_meta_table();

		SIQO_create_all_necessary_settings();

		SIQO_init_css_activator();

		SIQO_create_all_necessary_pages();

		if ( class_exists( 'SIQO_Management' ) ) {
			SIQO_Management::install();
		}

		SIQO_admin_autorole();
	}
}
