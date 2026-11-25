<?php

/**
 * Database abstraction for SIQO meta and tables storage.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SIQO_Meta_DB {

    protected static $table_meta = '';
    protected static $table_tables = '';

    /**
     * Initialize table names.
     */
    public static function init() {
        global $wpdb;
        self::$table_meta = $wpdb->prefix . 'siqo_meta';
        self::$table_tables = $wpdb->prefix . 'siqo_tables';
    }

    /**
     * Create the siqo_meta table.
     */
    public static function create_meta_table( $version = STUDIO_IMMENS_QUICK_ORDER_META_VERSION ) {
        update_option( 'siqo_meta_table', STUDIO_IMMENS_QUICK_ORDER_META_VERSION );
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $table_name = $wpdb->prefix . 'siqo_meta';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            meta_key varchar(255),
            meta_value text,
            assoc_id varchar(255),
            assoc_tb varchar(255),
            PRIMARY KEY (id),
            KEY meta_key (meta_key),
            KEY assoc_id (assoc_id),
            KEY assoc_tb (assoc_tb)
        ) " . $wpdb->get_charset_collate() . ";";

        dbDelta( $sql );

        $meta_table = get_option( 'siqo_meta_table' );
        if ( $meta_table != $version ) {
            update_option( 'siqo_meta_table', $version );
        }
    }

    /**
     * Get meta value or rows.
     */
    public static function get_meta( $meta_key, $type = 'var' ) {
        global $wpdb;
        $cache_key = 'siqo_meta_' . md5( $meta_key . '_' . $type );
        $cached = wp_cache_get( $cache_key, 'siqo_meta' );
        if ( $cached !== false ) {
            return $cached;
        }
        $table = $wpdb->prefix . 'siqo_meta';
        if ( $type == 'var' ) {
            $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE meta_key = %s", [ $table, $meta_key ] ) );
        } elseif ( $type == 'all' ) {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE meta_key = %s ORDER BY id", [ $table, $meta_key ] ) );
        } else {
            $result = $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM %i WHERE meta_key = %s", [ $table, $meta_key ] ), $type );
        }
        wp_cache_set( $cache_key, $result, 'siqo_meta' );
        return $result;
    }

    /**
     * Get meta by assoc_id.
     */
    public static function get_meta_by_assoc_id( $assoc_id, $type = 'var' ) {
        global $wpdb;
        $cache_key = 'siqo_meta_assoc_' . md5( $assoc_id . '_' . $type );
        $cached = wp_cache_get( $cache_key, 'siqo_meta' );
        if ( $cached !== false ) {
            return $cached;
        }
        $table = $wpdb->prefix . 'siqo_meta';
        if ( $type == 'var' ) {
            $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE assoc_id = %s", [ $table, $assoc_id ] ) );
        } else {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE assoc_id = %s", [ $table, $assoc_id ] ), $type );
        }
        wp_cache_set( $cache_key, $result, 'siqo_meta' );
        return $result;
    }

    /**
     * Get meta by assoc_tb.
     */
    public static function get_meta_by_assoc_tb( $assoc_tb, $type = 'var' ) {
        global $wpdb;
        $cache_key = 'siqo_meta_assoctb_' . md5( $assoc_tb . '_' . $type );
        $cached = wp_cache_get( $cache_key, 'siqo_meta' );
        if ( $cached !== false ) {
            return $cached;
        }
        $table = $wpdb->prefix . 'siqo_meta';
        if ( $type == 'var' ) {
            $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE assoc_tb = %s", [ $table, $assoc_tb ] ) );
        } else {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE assoc_tb = %s", [ $table, $assoc_tb ] ), $type );
        }
        wp_cache_set( $cache_key, $result, 'siqo_meta' );
        return $result;
    }

    /**
     * Get meta by id.
     */
    public static function get_meta_by_id( $id, $type = 'var' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        if ( $type == 'var' ) {
            return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE id = %s", [ $table, $id ] ) );
        } else {
            return $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM %i WHERE id = %s", [ $table, $id ] ), $type );
        }
    }

    /**
     * Insert meta.
     */
    public static function insert_meta( $meta_key, $meta_value, $assoc_id = null, $assoc_tb = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        return $wpdb->insert( $table, array(
            'meta_key'   => $meta_key,
            'meta_value' => $meta_value,
            'assoc_id'   => $assoc_id,
            'assoc_tb'   => $assoc_tb,
        ) );
    }

    /**
     * Update or insert meta.
     */
    public static function update_meta( $meta_key, $meta_value, $assoc_id = null, $assoc_tb = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        $meta = self::get_meta( $meta_key );

        if ( $meta != null ) {
            $result = $wpdb->update( $table, array(
                'meta_value' => $meta_value,
                'assoc_id'   => $assoc_id,
                'assoc_tb'   => $assoc_tb,
            ), array( 'meta_key' => $meta_key ) );
        } else {
            $result = $wpdb->insert( $table, array(
                'meta_key'   => $meta_key,
                'meta_value' => $meta_value,
                'assoc_id'   => $assoc_id,
                'assoc_tb'   => $assoc_tb,
            ) );
        }
        wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_var' ), 'siqo_meta' );
        wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_all' ), 'siqo_meta' );
        return $result;
    }

    /**
     * Delete meta by key.
     */
    public static function delete_meta( $meta_key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        $result = $wpdb->delete( $table, array( 'meta_key' => $meta_key ) );
        wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_var' ), 'siqo_meta' );
        wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_all' ), 'siqo_meta' );
        return $result;
    }

    /**
     * Delete meta by id.
     */
    public static function delete_meta_by_id( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siqo_meta';
        $result = $wpdb->delete( $table, array( 'id' => $id ) );
        wp_cache_flush_group( 'siqo_meta' );
        return $result;
    }
}

SIQO_Meta_DB::init();
