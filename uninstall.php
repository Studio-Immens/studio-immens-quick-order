<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 * @package    Studio_Immens_Quick_Order
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Variables are local to this uninstall script.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Uninstall must remove custom tables and rows.
// phpcs:disable WordPress.DB.SlowDBQuery -- Uninstall meta cleanup is intentional.

global $wpdb;

if ( 'yes' !== get_option( 'siqo_delete_data_on_uninstall' ) ) {
	return;
}

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}siqo_meta" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}siqo_tables" );

// Delete all options with SIQO prefix
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'siqo\_%' AND option_name NOT LIKE 'siqo\_inventory\_%' AND option_name NOT LIKE 'siqo\_printers\_%' AND option_name NOT LIKE 'siqo\_receipts\_%' AND option_name NOT LIKE 'siqo\_reservations\_%' AND option_name NOT LIKE 'siqo\_staff\_%' AND option_name NOT LIKE 'siqo\_warehouse\_%' AND option_name NOT LIKE 'siqo\_license\_%' AND option_name NOT LIKE 'siqo\_print\_rules%' AND option_name NOT IN ('siqo_instance_id','siqo_api_secret','siqo_forced_version','siqo_printnode_key','siqo_delete_data_on_uninstall','_siqo_license_token','_siqo_last_sync')" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'siqo_setting\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flash\_order\_%'" );

// Delete plugin-created pages
$pages = get_posts( array(
	'post_type'      => 'page',
	'posts_per_page' => -1,
	'meta_key'       => '_siqo_page',
	'post_status'    => 'any',
) );
foreach ( $pages as $page ) {
	wp_delete_post( $page->ID, true );
}

// Delete custom post types
$cpts = array( 'siqo_catering', 'siqo_tables' );
foreach ( $cpts as $cpt ) {
	$posts = get_posts( array(
		'post_type'      => $cpt,
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );
	foreach ( $posts as $post ) {
		wp_delete_post( $post->ID, true );
	}
}

// Clean user meta
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'siqo\_user\_role\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'flash\_order\_user\_role\_%'" );
