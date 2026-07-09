<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 * @package    Studio_Immens_Quick_Order
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}siqo_meta" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}siqo_tables" );

// Delete all options with SIQO prefix
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'siqo\_%'" );
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
