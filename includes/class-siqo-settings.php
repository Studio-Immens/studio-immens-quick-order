<?php

/**
 * Settings management via WordPress Options API.
 *
 * @link       https://studioimmens.com
 * @since      1.1.0
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SIQO_Settings {

    const OPTION_PREFIX = 'siqo_setting_';

    /**
     * Get a setting value. Tries wp_options first, falls back to siqo_meta table.
     */
    public static function get( $meta_key, $default = '' ) {
        $option_key = self::OPTION_PREFIX . $meta_key;
        $value = get_option( $option_key, null );
        if ( $value !== null ) {
            return $value;
        }
        $legacy = self::get_from_meta_table( $meta_key );
        if ( $legacy !== null ) {
            update_option( $option_key, $legacy );
            return $legacy;
        }
        return $default;
    }

    /**
     * Update a setting value. Writes to both wp_options and legacy table.
     */
    public static function set( $meta_key, $meta_value, $assoc_id = 'setting' ) {
        $option_key = self::OPTION_PREFIX . $meta_key;
        update_option( $option_key, $meta_value );
        if ( function_exists( 'SIQO_update_meta' ) ) {
            SIQO_update_meta( $meta_key, $meta_value, $assoc_id );
        }
    }

    /**
     * Delete a setting.
     */
    public static function delete( $meta_key ) {
        $option_key = self::OPTION_PREFIX . $meta_key;
        delete_option( $option_key );
        if ( function_exists( 'SIQO_delete_meta' ) ) {
            SIQO_delete_meta( $meta_key );
        }
    }

    /**
     * Get style CSS variable.
     */
    public static function get_style( $var_name, $default = '' ) {
        return self::get( $var_name, $default );
    }

    /**
     * Set style CSS variable.
     */
    public static function set_style( $var_name, $value ) {
        self::set( $var_name, $value, 'style_css' );
    }

    /**
     * Fallback: read from legacy siqo_meta table.
     */
    private static function get_from_meta_table( $meta_key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM %i WHERE meta_key = %s LIMIT 1",
            [ $table, $meta_key ]
        ) );
        return $result;
    }
}
