<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
class Studio_Immens_Quick_Order_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'studio-immens-quick-order',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
