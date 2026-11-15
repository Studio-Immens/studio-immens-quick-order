<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/public
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Studio_Immens_Quick_Order_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/studio-immens-quick-order-public.css', array(), $this->version, 'all' );

		if ( $this->is_operator_page() ) {
			$path = plugin_dir_path( __FILE__ ) . 'css/siqo-operator.css';
			$ver  = file_exists( $path ) ? filemtime( $path ) : $this->version;

			wp_enqueue_style( 'siqo-operator', plugin_dir_url( __FILE__ ) . 'css/siqo-operator.css', array( $this->plugin_name ), $ver, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/studio-immens-quick-order-public.js', array( 'jquery', 'wp-api' ), $this->version, true );

		if ( $this->is_operator_page() ) {
			$path = plugin_dir_path( __FILE__ ) . 'js/siqo-operator.js';
			$ver  = file_exists( $path ) ? filemtime( $path ) : $this->version;

			wp_enqueue_script( 'siqo-operator', plugin_dir_url( __FILE__ ) . 'js/siqo-operator.js', array( $this->plugin_name ), $ver, true );
		}
	}

	private function is_operator_page() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		return $post && has_shortcode( $post->post_content, 'SIQO_manage_tables_section' );
	}
}
