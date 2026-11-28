<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 *
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/public
 * @author     Studio Immens <info@studioimmens.com>
 */

// flash_order

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom plugin tables require direct queries.
// phpcs:disable WordPress.DB.SlowDBQuery -- Meta queries use indexed custom keys.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters.

//function SIQO_add_endpoints() {
//	global $wp_rewrite;
//  //Author page
//	add_rewrite_endpoint( 'flash-orders', EP_ALL );
//	add_rewrite_endpoint( 'manage-orders', EP_ALL );
//	add_rewrite_endpoint( 'manage-tables', EP_ALL );
//    $wp_rewrite->flush_rules();
//    flush_rewrite_rules();
//}
//add_action( 'init', 'SIQO_add_endpoints' );

if ( ! function_exists( 'siqo_is_woocommerce_active' ) ) {
	function siqo_is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( in_array( 'woocommerce/woocommerce.php', $active, true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			if ( isset( $network['woocommerce/woocommerce.php'] ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! siqo_is_woocommerce_active() ) {
	add_action( 'admin_notices', 'SIQO_admin_notice_woocommerce_plugin_error' );
	return;
}

function SIQO_admin_notice_woocommerce_plugin_error() {
?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Studio Immens Quick Order requires WooCommerce to be installed and active.', 'studio-immens-quick-order' ); ?></p>
	</div>
<?php
}

if ( SIQO_get_meta('siqo_show_admin_bar_all') == 'no' ) {
	// if ( !current_user_can('manage_options', get_current_user_id()) ) {
		add_filter( 'show_admin_bar', '__return_false' );
	// }
}


// SIQO_create_all_necessary_settings();
function SIQO_create_all_necessary_settings(){
	$settings = array(
		'siqo_show_admin_bar_all' => 'no',
		'siqo_pickup_delivery_checkout' => 'yes',
		'siqo_notify_audio' => esc_url( plugins_url( 'includes/audio/siqo-chime-soft.mp3', SIQO_PLUGIN_FILE ) ),
		'siqo_default_grid_view' => 'no',
		'siqo_order_summary' => 'side_page',
		'siqo_front_name' => 'TAVOLO',
		'siqo_front_surname' => '',
		'fotimer_advanced' => 'yes',
		'fotimer_advanced_factor' => '0.25',
		'flash_orders_table_visualize_id' => 'yes',
		'flash_orders_table_visualize_name' => 'yes',
		'flash_orders_table_visualize_image' => 'yes',
		'flash_orders_table_visualize_warehouse' => 'yes',
		'flash_orders_table_visualize_note' => 'yes',
		'flash_orders_table_visualize_actions' => 'yes',
		'orders_limit_view' => '200',
		'orders_date_created' => '240',
		'ajax_refresh_order_seconds' => '2000',
		'manage_orders_table_visualize_id' => 'yes',
		'manage_orders_table_visualize_name' => 'yes',
		'manage_orders_table_visualize_products' => 'yes',
		'manage_orders_table_visualize_info' => 'yes',
		'manage_orders_table_visualize_status' => 'yes',
		'manage_orders_table_visualize_actions' => 'yes',
		'manage_orders_table_visualize_totals' => 'yes',
		'product_ingredients_tax' => 'yes',
		'show_product_ingredients_tax' => 'no',
		'product_allergens_tax' => 'yes',
		'show_product_allergens_tax' => 'no',
		'product_temperature_tax' => 'yes',
		'show_product_temperature_tax' => 'no',
		'product_sticker_tax' => 'yes',
		'product_macro_categories_tax' => 'yes',
		'product_ingredients_images' => 'yes',
		'product_allergens_images' => 'yes',
		'product_tags_images' => 'yes',
		'posts_category_images' => 'yes',
		'posts_tag_images' => 'yes',
	);
	foreach ( $settings as $key => $value ) {
		SIQO_update_meta( $key, $value, 'setting' );
		SIQO_Settings::set( $key, $value );
	}
}

// function SIQO_custom_mime_types( $mimes ) {
// 	// New allowed mime types.
// 	$mimes['webp']  = 'image/webp';
// 	return $mimes;
// }
// add_filter( 'upload_mimes', 'SIQO_custom_mime_types' );

function SIQO_clear_meta_database( $meta_key ){
	$database_val = SIQO_get_meta( $meta_key, 'all' );
	if ( empty( $database_val ) ) {
		return;
	}
	foreach ( $database_val as $value ) {
		if ( '' === $value->meta_value ) {
			SIQO_delete_meta_by_id( $value->id );
		}
	}
}
// add_action('plugins_loaded', 'SIQO_create_all_necessary_pages');
// SIQO_create_all_necessary_pages();
function SIQO_create_all_necessary_pages(){
	// Controlli SEMPRE eseguiti, non solo nel ramo AJAX: prima bastava omettere
	// "from=ajax" perché qualsiasi utente loggato riscrivesse le pagine del plugin.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'SIQO_manage_pages' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	$is_ajax = isset( $_POST['from'] ) && sanitize_text_field( wp_unslash( $_POST['from'] ) ) === 'ajax';
	$response = array();
	// SIQO_create_page( 'flash-orders','<!-- wp:shortcode -->[FO_front_order_section]<!-- /wp:shortcode -->', '1.0.1' );
	// $response['flash-orders'] = '1.0.1';
	SIQO_create_page( 'quick-menu','<!-- wp:shortcode -->[SIQO_front_order_ajax_section]<!-- /wp:shortcode -->', '1.0.3' );
		$response['quick-menu'] = '1.0.3';
	// SIQO_create_page( 'manage-orders','<!-- wp:shortcode -->[SIQO_manage_order_section]<!-- /wp:shortcode -->', '1.0.1');
		// $response['manage-orders'] = '1.0.1';
	SIQO_create_page( 'manage-tables','<!-- wp:shortcode -->[SIQO_manage_tables_section]<!-- /wp:shortcode -->', '1.0.2' );
		$response['manage-tables'] = '1.0.2';


	if (isset($_POST['from'])&& sanitize_text_field(wp_unslash($_POST['from'])) == 'ajax') {
		wp_send_json(array(
	    	'response' 	=> $response,
	    	// 'time_exec' => $time,
			// 'post' => $_POST,
		));
		die();
	}
}
add_action('wp_ajax_SIQO_create_all_necessary_pages', 'SIQO_create_all_necessary_pages');

function SIQO_migrate_customer_page_slug() {
	if ( get_option( 'siqo_customer_page_slug_migrated' ) ) {
		return;
	}

	$old_page = get_page_by_path( 'flash-orders-ajax' );
	if ( $old_page && ! get_page_by_path( 'quick-menu' ) ) {
		wp_update_post( array(
			'ID'           => $old_page->ID,
			'post_name'    => 'quick-menu',
			'post_title'   => 'Quick Menu',
			'post_content' => '<!-- wp:shortcode -->[SIQO_front_order_ajax_section]<!-- /wp:shortcode -->',
		) );

		SIQO_delete_meta( 'page_id_flash-orders-ajax' );
		SIQO_delete_meta( 'page_version_flash-orders-ajax' );
		SIQO_delete_meta( 'last_update_flash-orders-ajax' );
		SIQO_update_meta( 'page_id_quick-menu', $old_page->ID, 'page_id' );
		SIQO_update_meta( 'page_version_quick-menu', '1.0.3', 'page_id_' . $old_page->ID );
	}

	update_option( 'siqo_customer_page_slug_migrated', 1, false );
}
add_action( 'init', 'SIQO_migrate_customer_page_slug', 5 );

function SIQO_redirect_legacy_customer_slug() {
	if ( is_admin() ) {
		return;
	}

	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( ! $request_path ) {
		return;
	}

	$legacy_path = wp_parse_url( home_url( '/flash-orders-ajax/' ), PHP_URL_PATH );
	if ( untrailingslashit( $request_path ) !== untrailingslashit( $legacy_path ) ) {
		return;
	}

	$page = get_page_by_path( 'quick-menu' );
	if ( ! $page ) {
		return;
	}

	$target = get_permalink( $page );
	if ( ! empty( $_GET ) ) {
		$query_args = array();
		foreach ( $_GET as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$query_args[ sanitize_key( wp_unslash( $key ) ) ] = sanitize_text_field( wp_unslash( $value ) );
		}
		$target = add_query_arg( $query_args, $target );
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'SIQO_redirect_legacy_customer_slug', 1 );

function SIQO_create_page( $page_name, $content = '', $version = '1.0.0' ){
	$fo_page_id = SIQO_get_meta('page_id_'.$page_name);
	$fo_version = SIQO_get_meta('page_version_'.$page_name);

	$query_page_name = new WP_Query(  array(
	        'post_type'              => 'page',
	        'title'                  => $page_name,
	        'post_status'            => 'all',
	        'posts_per_page'         => 1,
	        'no_found_rows'          => true,
	    )
	);
	if ( get_post( $fo_page_id ) != null && !version_compare( $version, $fo_version, ">" ) ) {
	    return;
	}
	if( !SIQOcheck($fo_page_id) ) {
		if( $query_page_name ) {
			// add_action( 'init', function() use ($page_name, $content, $version ) {
			    $page_id = wp_insert_post( array(
			        'comment_status' => 'close',
			        'ping_status'    => 'close',
			        'post_author'    => get_current_user_id(),
			        'post_title'     => $page_name,
			        'post_name'      => strtolower(str_replace(' ', '-', trim($page_name))),
			        'post_status'    => 'publish',
			        'post_content'   => $content,
			        'post_type'      => 'page'
			        //'post_parent'    => ''
			        )
			    );
			    SIQO_update_meta( 'last_update_'.$page_name, current_datetime()->format('Y-m-d H:i:s'), 'page');
	    		SIQO_update_meta('page_version_'.$page_name, $version, 'page_id_'.$page_id );
	    		SIQO_update_meta('page_id_'.$page_name, $page_id, 'page_id');
			// });
	    }
	} else{
		if ( version_compare( $version, $fo_version, ">" ) ) {
		    // add_action( 'init', function() use ($fo_page_id, $content, $version ) {
			    wp_update_post( array(
				    	'ID' => $fo_page_id,
				    	'post_content'=> $content,
				    )
				);
			// });
			$query_page_name = new WP_Query( array(
				'post_type'              => 'page',
				'title'                  => $page_name,
				// 'post_title'			 => $page_name,
				'post_status'            => 'all',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'orderby'                => 'post_date ID',
				'order'                  => 'ASC',
				)
			);

		    SIQO_update_meta( 'last_update_'.$page_name, current_datetime()->format('Y-m-d H:i:s'), 'page');
		    SIQO_update_meta('page_version_'.$page_name, $version, 'page_id_'.$query_page_name->ID );
		    SIQO_update_meta('page_id_'.$page_name, $query_page_name->ID, 'page_id');
		}
	}
}

/**
 * Recursive sanitation for an array
 *
 * @param $array
 *
 * @return mixed
 */
function SIQO_recursive_sanitize_text_field($array) {
    if ( ! is_array( $array ) ) {
        return sanitize_text_field( (string) $array );
    }
    foreach ( $array as $key => $value ) {
        if ( is_array( $value ) ) {
            // Ricorsione sul prefisso corretto: la vecchia chiamata senza SIQO_
            // puntava a una funzione inesistente e mandava in fatal il form ordini.
            $array[ $key ] = SIQO_recursive_sanitize_text_field( $value );
        }
        else {
            // Riassegnazione necessaria: prima il valore sanitizzato finiva in una
            // variabile locale e l'array tornava invariato (no-op).
            $array[ $key ] = sanitize_text_field( (string) $value );
        }
    }
    return $array;
}

function SIQO_delete_page(){
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
    }
    if ( !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SIQO_manage_pages' ) ) {
        wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
    }
    $response = array();
    $page_name = (isset($_POST['page'])) ? sanitize_text_field(wp_unslash($_POST['page'])):'';
    $fo_page_id = absint( SIQO_get_meta('page_id_'.$page_name) );

    if ( $fo_page_id && 'page' === get_post_type( $fo_page_id ) && current_user_can( 'delete_page', $fo_page_id ) ) {
        wp_delete_post( $fo_page_id );
        SIQO_delete_meta('page_version_'.$page_name );
        SIQO_delete_meta('page_id_'.$page_name );
        $response['deleted'] = 'deleted page -> '.$page_name;
    }

    wp_send_json(array(
        'response'  => $response,
    ));
    die();
}
add_action('wp_ajax_SIQO_delete_page', 'SIQO_delete_page');


function SIQO_display_post_states( $post_states, $post ) {
	$pages = SIQO_get_meta_by_assoc_id('page_id','OBJECT');
    foreach ( $pages as $key => $value ) {
        if ( $value->meta_value == $post->ID ) {
            $post_states[ 'fo_page_' . $value->meta_key ] = sprintf( 'FO page' );
        }
    }
    return $post_states;
}
add_filter( 'display_post_states', 'SIQO_display_post_states', 10, 2 );

// function SIQO_add_role(){
// 	add_role('worker', 'Worker', array());
// }
// add_action( 'init', 'SIQO_add_role' );

function SIQO_get_page_id_by_title( $title, $post_type = 'post' ) {
	// $page = get_page_by_title( $title, OBJECT, $post_type ); //deprecated function
	$page = get_posts(
	    array(
	        'post_type'              => 'page',
	        'title'                  => $post_type,
	        'post_status'            => 'all',
	        'numberposts'            => 1,
	        'update_post_term_cache' => false,
	        'update_post_meta_cache' => false,
	        'orderby'                => 'post_date ID',
	        'order'                  => 'ASC',
	    )
	);
	return $page->ID;
}








function SIQO_user_meta_form($user) {
	if( !current_user_can('manage_options') ){return;};
	$nonce = wp_create_nonce( 'siqo_user_meta' );

	$user_id = $user->ID;

	$worker_checked = ( isset(get_user_meta($user_id, 'siqo_user_role_worker')[0]) ) ? get_user_meta($user_id, 'siqo_user_role_worker')[0] : false;
	$supervisor_checked = ( isset(get_user_meta($user_id, 'siqo_user_role_supervisor')[0]) ) ? get_user_meta($user_id, 'siqo_user_role_supervisor')[0] : false;
	$manager_checked = ( isset(get_user_meta($user_id, 'siqo_user_role_manager')[0]) ) ? get_user_meta($user_id, 'siqo_user_role_manager')[0] : false;

	$worker_checked = ( $worker_checked == 'on' ) ? 'checked="checked"' : '';
	$supervisor_checked = ( $supervisor_checked == 'on' ) ? 'checked="checked"' : '';
	$manager_checked = ( $manager_checked == 'on' ) ? 'checked="checked"' : '';
?>
<div style="width:100%;height:70px;"></div>
<input type="hidden" id="_siqononce_user_meta" name="_siqononce_user_meta" value="<?php echo esc_attr($nonce); ?>" />
<h2>Quick Order</h2>
	<table class="form-table">
		<tr>
			<th>
				<label for="flash_order_user_role_worker"><?php echo esc_html__( 'Aggiungi il ruolo worker', 'studio-immens-quick-order' );?></label>
			</th>
			<td>
				<input type="checkbox" name="flash_order_user_role_worker" id="flash_order_user_role_worker" <?php echo esc_attr($worker_checked);?>>
				<span class="description">
					<?php echo esc_html__( 'Aggiungi il ruolo (worker) all\'utente', 'studio-immens-quick-order' ); ?>
				</span>
			</td>
		</tr>

		<tr>
			<th>
				<label for="flash_order_user_role_supervisor"><?php echo esc_html__( 'Aggiungi il ruolo supervisor', 'studio-immens-quick-order' );?></label>
			</th>
			<td>
				<input type="checkbox" name="flash_order_user_role_supervisor" id="flash_order_user_role_supervisor" <?php echo esc_attr($supervisor_checked);?>>
				<span class="description">
					<?php echo esc_html__( 'Aggiungi il ruolo (supervisor) all\'utente', 'studio-immens-quick-order' ); ?>
				</span>
			</td>
		</tr>

		<tr>
			<th>
				<label for="flash_order_user_role_manager"><?php echo esc_html__( 'Aggiungi il ruolo manager', 'studio-immens-quick-order' );?></label>
			</th>
			<td>
				<input type="checkbox" name="flash_order_user_role_manager" id="flash_order_user_role_manager" <?php echo esc_attr($manager_checked);?>>
				<span class="description">
					<?php echo esc_html__( 'Aggiungi il ruolo (manager) all\'utente', 'studio-immens-quick-order' ); ?>
				</span>
			</td>
		</tr>

	</table>
<div style="width:100%;height:70px;"></div>
<?php
}

add_action('show_user_profile', 'SIQO_user_meta_form'); // editing your own profile
add_action('edit_user_profile', 'SIQO_user_meta_form'); // editing another user
add_action('user_new_form', 'SIQO_user_meta_form'); // creating a new user

function SIQO_user_meta_save($userId) {
	if (!current_user_can('edit_user', $userId)) {
		return;
	}
	if ( !isset($_POST['_siqononce_user_meta']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_user_meta'])), 'siqo_user_meta' ) ) {
		return;
	}
	foreach ( array( 'worker', 'supervisor', 'manager' ) as $siqo_role ) {
		// I campi del form usano il prefisso flash_order_user_role_*, il meta siqo_user_role_*.
		$siqo_field = 'flash_order_user_role_' . $siqo_role;
		$siqo_alias = 'siqo_user_role_' . $siqo_role;
		$siqo_on    = isset( $_POST[ $siqo_field ] ) || isset( $_POST[ $siqo_alias ] );
		update_user_meta( $userId, $siqo_alias, $siqo_on ? 'on' : '' );
	}
}
add_action('personal_options_update', 'SIQO_user_meta_save');
add_action('edit_user_profile_update', 'SIQO_user_meta_save');
add_action('user_register', 'SIQO_user_meta_save');





function SIQO_view_table_in_grid(){
$checked =  ( SIQO_get_meta('siqo_default_grid_view') == 'yes' ) ? 'checked' : '';
$style = ( SIQO_get_meta('siqo_default_grid_view') == 'yes' ) ? 'display:block' : 'display:none';
?>
	<p title="<?php echo esc_html__( 'visualizza i risultati in griglia', 'studio-immens-quick-order' );?>">
		<?php echo esc_html__( 'Grid View', 'studio-immens-quick-order' );?>
	</p>

	<input type="checkbox" class="toggleView" onclick="jQuery(this).next().toggle();" <?php echo esc_attr($checked); ?>>
	<input type="range" min="10" max="100" value="50" class="FOslider GridRange" oninput="SIQO_grid_adjust(this.value)" style="<?php echo esc_attr($style); ?>">
<?php
}



function SIQOecho( $var = '' ){
	if ( $var != '' && $var != null ) {
		echo esc_attr($var);
	} else{
		echo '';
	}
}
function SIQOcheck( $var = '' ){
	if ( $var != '' && $var != null && $var != false ) {
		return true;
	} else{
		return false;
	}
}
function siqo_get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function SIQO_head_needed(){ ?>
	<!-- <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" /> -->
	<input type="hidden" class="fo_toggle_header_footer" name="fo_toggle_header_footer" value="true">
	<input type="hidden" class="fo_home_url" name="fo_home_url" value="<?php echo esc_url( get_home_url() ); ?>">
	<?php
}
add_action( 'wp_head', 'SIQO_head_needed' );

function siqo_header_style() {
	$css    = ':root{';
	$styles = SIQO_get_meta_by_assoc_id( 'style_css', 'OBJECT' );
	if ( is_array( $styles ) ) {
		foreach ( $styles as $style ) {
			$key   = $style->meta_key;
			$value = $style->meta_value;
			$css  .= esc_attr( $key ) . ': ' . esc_attr( $value ) . '; ';

			// Alias storico: il CSS pubblico usa --fo-* mentre le
			// impostazioni salvano --siqo-*. Teniamo allineate entrambe
			// le famiglie di variabili.
			if ( 0 === strpos( $key, '--siqo-' ) ) {
				$css .= esc_attr( '--fo-' . substr( $key, 7 ) ) . ': ' . esc_attr( $value ) . '; ';
			}
		}
	}
	$css .= '}';
	siqo_print_inline_style_tag( $css );
}
add_action( 'wp_head', 'siqo_header_style' );
add_action( 'admin_head', 'siqo_header_style' );

function siqo_template_changer() {
	// global $wp;
	// if (isset($wp->query_vars['pagename'])) {
	// 	if ( $wp->query_vars['pagename'] == 'flash-orders' ) {
	// 		include_once('template/front-order.php');
	// 	} elseif ( $wp->query_vars['pagename'] == 'manage-orders' ) {
	// 		include_once('template/manage-order.php');
	// 	} elseif ( $wp->query_vars['pagename'] == 'manage-tables' ) {
	// 		include_once('template/manage-tables.php');
	// 	} elseif ( $wp->query_vars['pagename'] == 'manage-restaurant' ) {
	// 		include_once('template/manage-restaurant.php');
	// 	} else{}
	// }
}
function SIQO_init_page_maker(){
	$pages = SIQO_get_meta_by_assoc_id('page','OBJECT');
	if ( isset( $_SERVER["REDIRECT_URL"]) ) {
	foreach( $pages as $page ) {
		// SIQO_debug($page->meta_key);
			if ( siqo_str_contains( sanitize_text_field(wp_unslash($_SERVER["REDIRECT_URL"])), $page->meta_key ) ) {
				?>

 				<?php
				// add_action( 'loop_end', 'fo_template_changer' );
				break;
			}
		}
	} // SIQO_debug($_SERVER["REDIRECT_URL"]);
}
// add_action( 'wp_body_open', 'FO_init_page_maker' );


add_action( 'woocommerce_account_content', 'SIQO_add_special_pages_navigations' );

function SIQO_add_special_pages_navigations(){
	$user = wp_get_current_user();

	$flags = SIQO_get_user_role_flags( $user->ID );
	$worker_checked     = $flags['worker'] ? 'on' : '';
	$supervisor_checked = $flags['supervisor'] ? 'on' : '';
	$manager_checked    = $flags['manager'] ? 'on' : '';

	if ( $worker_checked == 'on' ) {
		$fo_pages = SIQO_get_meta_by_assoc_id('page_id','OBJECT');
		?>
		<div style="display:contents;">
			<nav id="FOSpecialPagesNavigation" style=""> <strong><?php esc_html_e( 'Pagine Speciali:', 'studio-immens-quick-order' );?> </strong>
				<?php foreach ($fo_pages as $key => $value) {
					$post = get_post($value->meta_value);
					if ( !$worker_checked && in_array( substr($value->meta_key, 8), array( 'flash-orders-ajax', 'quick-menu' ), true ) ) { continue; }
					if ( !$worker_checked && substr($value->meta_key, 8) == 'flash-orders' ) { continue; }

					if ( !$supervisor_checked && substr($value->meta_key, 8) == 'manage-tables' ) { continue; }
					if ( !$supervisor_checked && substr($value->meta_key, 8) == 'manage-orders' ) { continue; }
					if ( !$supervisor_checked && substr($value->meta_key, 8) == 'warehouse' ) { continue; }

					if ( !$manager_checked && substr($value->meta_key, 8) == 'manage-restaurant' ) { continue; }
					?>
					<?php if (get_post_status($value->meta_value) == 'publish') {?>
						<a href="<?php echo esc_url(get_post_permalink($value->meta_value));?>">
							<?php echo esc_attr(get_the_title($value->meta_value)); ?>
						</a>
					<?php } ?>
				<?php } ?>
			</nav>
		</div>
	<?php }
}



// add_action('plugins_loaded', 'SIQO_admin_autorole');
function SIQO_admin_autorole(){
	$user_query = get_users( array( 'role' => 'Administrator' ) );
	foreach ($user_query as $key => $value) {
		// SIQO_debug($value->data->ID);
		update_user_meta( $value->data->ID, 'siqo_user_role_manager', 'on');
	}
}







include_once('template/front-order-ajax.php');
include_once('template/manage-tables.php');
include_once('station-orders.php');

add_shortcode( 'SIQO_front_order_ajax_section', 'SIQO_front_order_ajax_section' );
function SIQO_front_order_ajax_section(){
	ob_start();
	SIQO_front_order_ajax();
	return ob_get_clean();
}

// add_shortcode( 'SIQO_manage_order_section', 'SIQO_manage_order_section' );
// function SIQO_manage_order_section(){
// 	if (!is_admin()) {
// 		if ( SIQO_access_autorization() ){
// 			SIQO_add_special_pages_navigations();
// 			SIQO_pages_header_controls();
// 		}
// 		include_once('template/manage-order.php');
// 	}
// }

add_shortcode( 'SIQO_manage_tables_section', 'SIQO_manage_tables_section' );
function SIQO_manage_tables_section(){
	// if (!is_admin()) {
		// SIQO_access_denied();
		ob_start();
		if ( SIQO_access_autorization() ){
			SIQO_pages_header_controls();
			SIQO_manage_table();
		}
		return ob_get_clean();
	// }
}









function SIQO_pages_header_controls(){
	SIQO_Management::render_bar( 'staff' );
}

// SIQO_debug( FOW_get_warehouse_by_assoc_id_tax(14,'woo_product','OBJECT') );



if ( ! function_exists('SIQO_Catering_cpt') ) {
	// Register Custom Post Type
	function SIQO_Catering_cpt() {
		$labels = array(
			'name'                  => esc_html__( 'Eventi e Catering', 'studio-immens-quick-order' ),
			'singular_name'         => esc_html__( 'Evento catering', 'studio-immens-quick-order' ),
			'menu_name'             => esc_html__( 'Eventi e Catering', 'studio-immens-quick-order' ),
			'name_admin_bar'        => esc_html__( 'Eventi e Catering', 'studio-immens-quick-order' ),
			'archives'              => esc_html__( 'Archivio catering', 'studio-immens-quick-order' ),
			'attributes'            => esc_html__( 'Attributi evento', 'studio-immens-quick-order' ),
			'parent_item_colon'     => esc_html__( 'Evento principale:', 'studio-immens-quick-order' ),
			'all_items'             => esc_html__( 'Tutti gli eventi', 'studio-immens-quick-order' ),
			'add_new_item'          => esc_html__( 'Aggiungi evento catering', 'studio-immens-quick-order' ),
			'add_new'               => esc_html__( 'Aggiungi nuovo', 'studio-immens-quick-order' ),
			'new_item'              => esc_html__( 'Nuovo evento catering', 'studio-immens-quick-order' ),
			'edit_item'             => esc_html__( 'Modifica evento catering', 'studio-immens-quick-order' ),
			'update_item'           => esc_html__( 'Aggiorna evento catering', 'studio-immens-quick-order' ),
			'view_item'             => esc_html__( 'Visualizza evento catering', 'studio-immens-quick-order' ),
			'view_items'            => esc_html__( 'Visualizza eventi catering', 'studio-immens-quick-order' ),
			'search_items'          => esc_html__( 'Cerca eventi catering', 'studio-immens-quick-order' ),
			'not_found'             => esc_html__( 'Nessun evento trovato', 'studio-immens-quick-order' ),
			'not_found_in_trash'    => esc_html__( 'Nessun evento nel cestino', 'studio-immens-quick-order' ),
			'featured_image'        => esc_html__( 'Immagine in evidenza', 'studio-immens-quick-order' ),
			'set_featured_image'    => esc_html__( 'Imposta immagine in evidenza', 'studio-immens-quick-order' ),
			'remove_featured_image' => esc_html__( 'Rimuovi immagine in evidenza', 'studio-immens-quick-order' ),
			'use_featured_image'    => esc_html__( 'Usa come immagine in evidenza', 'studio-immens-quick-order' ),
			'insert_into_item'      => esc_html__( 'Inserisci nellÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢evento', 'studio-immens-quick-order' ),
			'uploaded_to_this_item' => esc_html__( 'Caricato per questo evento', 'studio-immens-quick-order' ),
			'items_list'            => esc_html__( 'Elenco eventi catering', 'studio-immens-quick-order' ),
			'items_list_navigation' => esc_html__( 'Navigazione elenco eventi', 'studio-immens-quick-order' ),
			'filter_items_list'     => esc_html__( 'Filtra eventi catering', 'studio-immens-quick-order' ),
		);
		$args = array(
			'label'                 => esc_html__( 'Eventi e Catering', 'studio-immens-quick-order' ),
			'description'           => esc_html__( 'Eventi catering, banchetti e ordinazioni speciali.', 'studio-immens-quick-order' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'author', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
			'taxonomies'            => array(),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'si-quick-order',
			'show_in_rest'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-food',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => true,
			'capability_type'       => 'page',
			// 'register_meta_box_cb' 	=> 'fo_catering_section_data',
		);
		register_post_type( 'siqo_catering', $args );
	}
	add_action( 'init', 'SIQO_Catering_cpt', 0 );
}




// Funzione per aggiungere la metabox personalizzata
function SIQO_add_data_metabox() {
    add_meta_box(
        'SIQO_add_data_metabox',    // ID univoco della metabox
        esc_html__('Data di Consegna: ', 'studio-immens-quick-order'), // Titolo della metabox
        'SIQO_show_data_metabox',   // Callback per visualizzare il contenuto della metabox
        'siqo_catering',               // Screen (CPT) dove visualizzare la metabox
        'normal',                 // Contesto della metabox (normal, side, ecc.)
        'high'                    // PrioritÃƒÆ’Ã‚Â  della metabox (high, low)
    );
}
add_action('add_meta_boxes', 'SIQO_add_data_metabox');

function SIQO_show_data_metabox($post) {
	$fo_delivery_date = get_post_meta($post->ID, 'fo_delivery_date', true);
	echo '<label for="fo_delivery_date">'.esc_html__(' Data di Consegna: ', 'studio-immens-quick-order').'</label>';
	echo '<input type="datetime-local" id="fo_delivery_date" name="fo_delivery_date" value="' . esc_attr($fo_delivery_date) . '" size="25" />';
	$nonce = wp_create_nonce( 'fo_data_metabox' );
	echo '<input type="hidden" id="_fononce" name="_fononce" value="'.esc_attr($nonce).'" />';
}

function SIQO_save_data_metabox($post_id) {
	if (!isset($_POST['_fononce'])) {
		return;
	}
	if ( !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_fononce'])), 'fo_data_metabox' ) ) {
		return $post_id;
	}
	$fo_delivery_date = (isset($_POST['fo_delivery_date']) ? sanitize_text_field(wp_unslash($_POST['fo_delivery_date'])) : '');
	update_post_meta($post_id, 'fo_delivery_date', $fo_delivery_date);
}
add_action('save_post', 'SIQO_save_data_metabox');







// Register Custom Taxonomy
function SIQO_custom_taxonomy_ingredients() {
	$labels = array(
		'name'                       => esc_html__( 'siqo_ingredients', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Ingrediente', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Ingrediente', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutti gli Ingredienti', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Genitore dell\'Ingrediente', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Genitore dell\'Ingrediente:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuovo Ingrediente', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi Nuovo Ingrediente', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Edita Ingrediente', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna Ingrediente', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza Ingrediente', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa gli Ingredienti con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o Rimuovi Ingredienti', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli fra i piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Ingredienti Popolari', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca Ingredienti', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessun risultato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessun Ingrediente', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'lista ingredienti', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'navigazione lista ingredienti', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> true,
		'show_in_nav_menus' => true,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_ingredients', array( 'product', 'product_variation','siqo_flash_product'  ), $args );
}
if (SIQO_get_meta('product_ingredients_tax') == 'yes') {
	add_action( 'init', 'SIQO_custom_taxonomy_ingredients', 0 );
	if (SIQO_get_meta('show_product_ingredients_tax') == 'yes') {
		add_action( 'woocommerce_product_meta_end', 'SIQO_show_ingredients_tax_to_product_page' );
	}
}

function SIQO_show_ingredients_tax_to_product_page(){
 echo get_the_term_list( get_the_ID(), 'siqo_ingredients', '<span class="FO_front_tax tagged_as wp-block-post-terms">Ingredienti: ', ', ', '</span>' );
}

// Register Custom Taxonomy
function SIQO_custom_taxonomy_allergens() {
	$labels = array(
		'name'                       => esc_html__( 'siqo_allergens', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Allergene', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Allergene', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutti gli Allergeni', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Genitore dell\'Allergene', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Genitore dell\'Allergene:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuovo Allergene', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi Nuovo Allergene', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Edita Allergene', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna Allergene', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza Allergene', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa gli Allergeni con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o Rimuovi Allergeni', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli fra i piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Allergeni Popolari', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca Allergeni', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessun risultato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessun Allergene', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'lista Allergeni', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'navigazione lista Allergeni', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            	=> $labels,
		'hierarchical'      	=> true,
		'public'            	=> true,
		'show_ui'           	=> true,
		'show_admin_column' 	=> true,
		'show_in_menu' 			=> true,
		'show_in_nav_menus' 	=> true,
		'show_in_quick_edit'	=> true,
		'show_tagcloud'     	=> true,
		'show_in_rest'      	=> true,
	);
	register_taxonomy( 'siqo_allergens', array( 'product', 'product_variation','siqo_flash_product' ), $args );
}
if (SIQO_get_meta('product_allergens_tax') == 'yes') {
	add_action( 'init', 'SIQO_custom_taxonomy_allergens', 0 );
	if (SIQO_get_meta('show_product_allergens_tax') == 'yes') {
		add_action( 'woocommerce_product_meta_end', 'SIQO_show_allergeni_tax_to_product_page' );
	}
}

function SIQO_show_allergeni_tax_to_product_page(){
 echo get_the_term_list( get_the_ID(), 'siqo_allergens', '<span class="FO_front_tax tagged_as wp-block-post-terms">Allergeni: ', ', ', '</span>' );
}

// Register Custom Taxonomy
function SIQO_custom_taxonomy_temperature() {
	$labels = array(
		'name'                       => esc_html__( 'siqo_temperatures', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Temperatura', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Temperatura', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutte le Temperature', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Genitore della Temperatura', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Genitore della Temperatura:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuova Temperatura', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi Nuova Temperatura', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Edita Temperatura', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna Temperatura', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza Temperatura', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa le Temperature con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o Rimuovi Temperature', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli fra i piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Temperature Popolari', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca Temperature', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessun risultato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessuna Temperatura', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'lista Temperature', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'navigazione lista Temperature', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> true,
		'show_in_nav_menus' => true,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_temperatures', array( 'product', 'product_variation','siqo_flash_product' ), $args );
}
if (SIQO_get_meta('product_temperature_tax') == 'yes') {
	add_action( 'init', 'SIQO_custom_taxonomy_temperature', 0 );
	// if (SIQO_get_meta('show_product_temperature_tax') == 'yes') {
	// 	add_action( 'woocommerce_product_meta_end', 'SIQO_show_temperature_tax_to_product_page' );
	// }
}

function SIQO_show_temperature_tax_to_product_page(){
 echo get_the_term_list( get_the_ID(), 'siqo_temperatures', '<span class="tagged_as">Temperature: ', ', ', '</span>' );
}


// Register Custom Taxonomy
function SIQO_custom_taxonomy_sticker() {
	$labels = array(
		'name'                       => esc_html__( 'siqo_stickers', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'siqo_stickers', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'siqo_stickers', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutti gli Sticker', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Genitore dello Sticker', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Genitore dello Sticker:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuovo Sticker', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi Nuovo Sticker', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Edita Sticker', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna Sticker', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza Sticker', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa gli Sticker con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o Rimuovi Sticker', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli fra i piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Sticker Popolari', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca Sticker', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessun risultato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessuno Sticker', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'lista Sticker', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'navigazione lista Sticker', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> true,
		'show_in_nav_menus' => true,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_stickers', array( 'product', 'product_variation','siqo_flash_product' ), $args );
}
if (SIQO_get_meta('product_sticker_tax') == 'yes') {
	add_action( 'init', 'SIQO_custom_taxonomy_sticker', 0 );
	// if (SIQO_get_meta('show_product_sticker_tax') == 'yes') {
	// 	add_action( 'woocommerce_product_meta_end', 'SIQO_show_sticker_tax_to_product_page' );
	// }
}

function SIQO_show_sticker_tax_to_product_page(){
 echo get_the_term_list( get_the_ID(), 'siqo_stickers', '<span class="tagged_as">Sticker: ', ', ', '</span>' );
}






// Register Custom Taxonomy
function SIQO_custom_taxonomy_macro_categories() {
	$labels = array(
		'name'                       => esc_html__( 'Macro Categorie', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Macro Categoria', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Macro Categorie', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutte le Macro Categorie', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Genitore delle Macro Categorie', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Genitore della Macro Categoria:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuova Macro Categorie', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi Nuova Macro Categoria', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Edita Macro Categoria', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna Macro Categoria', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza Macro Categoria', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa le Macro Categorie con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o Rimuovi Macro Categoria', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli fra le piÃƒÆ’Ã‚Â¹ usate', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Macro Categorie Popolari', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca Macro Categoria', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessun risultato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessuna Macro Categoria', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'lista Macro Categorie', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'navigazione lista Macro Categorie', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> true,
		'show_in_nav_menus' => true,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_macro_categories', array( 'product','siqo_flash_product' ), $args );
}
if (SIQO_get_meta('product_macro_categories_tax') == 'yes') {
	add_action( 'init', 'SIQO_custom_taxonomy_macro_categories', 0 );

	if (SIQO_get_meta('show_product_macro_cat_tax') == 'yes') {
		add_action( 'woocommerce_product_meta_end', 'SIQO_show_macro_cat_tax_to_product_page' );
	}
}
function SIQO_show_macro_cat_tax_to_product_page(){
 echo get_the_term_list( get_the_ID(), 'siqo_stickers', '<span class="tagged_as">Sticker: ', ', ', '</span>' );
}




// Register Custom Post Type
function SIQOP_tables_cpt() {

	$labels = array(
		'name'                  => esc_html__( 'Tavoli', 'studio-immens-quick-order' ),
		'singular_name'         => esc_html__( 'Tavolo', 'studio-immens-quick-order' ),
		'menu_name'             => esc_html__( 'Tavoli', 'studio-immens-quick-order' ),
		'name_admin_bar'        => esc_html__( 'Tavolo', 'studio-immens-quick-order' ),
		'archives'              => esc_html__( 'Archivio tavoli', 'studio-immens-quick-order' ),
		'attributes'            => esc_html__( 'Attributi tavolo', 'studio-immens-quick-order' ),
		'parent_item_colon'     => esc_html__( 'Tavolo principale:', 'studio-immens-quick-order' ),
		'all_items'             => esc_html__( 'Tutti i tavoli', 'studio-immens-quick-order' ),
		'add_new_item'          => esc_html__( 'Aggiungi nuovo tavolo', 'studio-immens-quick-order' ),
		'add_new'               => esc_html__( 'Aggiungi nuovo', 'studio-immens-quick-order' ),
		'new_item'              => esc_html__( 'Nuovo tavolo', 'studio-immens-quick-order' ),
		'edit_item'             => esc_html__( 'Modifica tavolo', 'studio-immens-quick-order' ),
		'update_item'           => esc_html__( 'Aggiorna tavolo', 'studio-immens-quick-order' ),
		'view_item'             => esc_html__( 'Visualizza tavolo', 'studio-immens-quick-order' ),
		'view_items'            => esc_html__( 'Visualizza tavoli', 'studio-immens-quick-order' ),
		'search_items'          => esc_html__( 'Cerca tavolo', 'studio-immens-quick-order' ),
		'not_found'             => esc_html__( 'Nessun tavolo', 'studio-immens-quick-order' ),
		'not_found_in_trash'    => esc_html__( 'Nessun tavolo nel cestino', 'studio-immens-quick-order' ),
		'featured_image'        => esc_html__( 'Immagine in evidenza', 'studio-immens-quick-order' ),
		'set_featured_image'    => esc_html__( 'Imposta immagine in evidenza', 'studio-immens-quick-order' ),
		'remove_featured_image' => esc_html__( 'Rimuovi immagine in evidenza', 'studio-immens-quick-order' ),
		'use_featured_image'    => esc_html__( 'Usa come immagine in evidenza', 'studio-immens-quick-order' ),
		'insert_into_item'      => esc_html__( 'Inserisci nel tavolo', 'studio-immens-quick-order' ),
		'uploaded_to_this_item' => esc_html__( 'Caricato per questo tavolo', 'studio-immens-quick-order' ),
		'items_list'            => esc_html__( 'Elenco tavoli', 'studio-immens-quick-order' ),
		'items_list_navigation' => esc_html__( 'Navigazione elenco tavoli', 'studio-immens-quick-order' ),
		'filter_items_list'     => esc_html__( 'Filtra tavoli', 'studio-immens-quick-order' ),
	);
	$args = array(
		'label'                 => esc_html__( 'Tavolo', 'studio-immens-quick-order' ),
		'description'           => esc_html__( 'Tavoli e zone del ristorante: bancone, cortile, gazebo e altre postazioni.', 'studio-immens-quick-order' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
		'taxonomies'            => array(),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => 'si-quick-order',
		'show_in_rest'          => true,
		'menu_position'         => 21,
		'menu_icon'             => 'dashicons-food',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
	);
	register_post_type( 'siqo_tables', $args );
}
add_action( 'init', 'SIQOP_tables_cpt', 0 );



// Register Custom Taxonomy
function SIQOP_custom_taxonomy_status() {
	$labels = array(
		'name'                       => esc_html__( 'Stati tavolo', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Stato tavolo', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Stati tavolo', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutti gli stati', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Stato principale', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Stato principale:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuovo stato tavolo', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi stato tavolo', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Modifica stato tavolo', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna stato tavolo', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza stato tavolo', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa gli stati con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o rimuovi stati', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli tra i piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Stati piÃƒÆ’Ã‚Â¹ usati', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca stato tavolo', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessuno stato trovato', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessuno stato', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'Elenco stati tavolo', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'Navigazione elenco stati', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> 'edit.php?post_type=siqo_tables',
		'show_in_nav_menus' => false,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_table_status', array( 'siqo_tables' ), $args );
}
// if (SIQO_get_meta('product_sticker_tax') == 'yes') {
	add_action( 'init', 'SIQOP_custom_taxonomy_status', 0 );
// }


// Register Custom Taxonomy
function SIQOP_custom_taxonomy_zone() {
	$labels = array(
		'name'                       => esc_html__( 'Zone', 'studio-immens-quick-order' ),
		'singular_name'              => esc_html__( 'Zona', 'studio-immens-quick-order' ),
		'menu_name'                  => esc_html__( 'Zone', 'studio-immens-quick-order' ),
		'all_items'                  => esc_html__( 'Tutte le zone', 'studio-immens-quick-order' ),
		'parent_item'                => esc_html__( 'Zona principale', 'studio-immens-quick-order' ),
		'parent_item_colon'          => esc_html__( 'Zona principale:', 'studio-immens-quick-order' ),
		'new_item_name'              => esc_html__( 'Nuova zona', 'studio-immens-quick-order' ),
		'add_new_item'               => esc_html__( 'Aggiungi zona', 'studio-immens-quick-order' ),
		'edit_item'                  => esc_html__( 'Modifica zona', 'studio-immens-quick-order' ),
		'update_item'                => esc_html__( 'Aggiorna zona', 'studio-immens-quick-order' ),
		'view_item'                  => esc_html__( 'Visualizza zona', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => esc_html__( 'Separa le zone con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => esc_html__( 'Aggiungi o rimuovi zone', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => esc_html__( 'Scegli tra le piÃƒÆ’Ã‚Â¹ usate', 'studio-immens-quick-order' ),
		'popular_items'              => esc_html__( 'Zone piÃƒÆ’Ã‚Â¹ usate', 'studio-immens-quick-order' ),
		'search_items'               => esc_html__( 'Cerca zona', 'studio-immens-quick-order' ),
		'not_found'                  => esc_html__( 'Nessuna zona trovata', 'studio-immens-quick-order' ),
		'no_terms'                   => esc_html__( 'Nessuna zona', 'studio-immens-quick-order' ),
		'items_list'                 => esc_html__( 'Elenco zone', 'studio-immens-quick-order' ),
		'items_list_navigation'      => esc_html__( 'Navigazione elenco zone', 'studio-immens-quick-order' ),
	);
	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_menu' 		=> 'edit.php?post_type=siqo_tables',
		'show_in_nav_menus' => false,
		'show_in_quick_edit'=> true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
	);
	register_taxonomy( 'siqo_zones', array( 'siqo_tables' ), $args );
}
// if (SIQO_get_meta('product_sticker_tax') == 'yes') {
	add_action( 'init', 'SIQOP_custom_taxonomy_zone', 0 );
// }
















function SIQO_get_pages_impost($page_id){
$settings = SIQO_get_meta( 'setting_page_id'.$page_id );
if ( isset($settings) && $settings != null ) {
	$settings = json_decode($settings);
}
// SIQO_debug($settings);
$page = get_post($page_id);
?>
<div class="GenericDetailSection" fo_page_id="<?php echo esc_attr($page_id);?>" style="display:none;">
	<div class="GenericClose" onclick="SIQO_hide_pages_rapid_impost(<?php echo '`'.esc_attr($page_id).'`'?>)">
		X<div style="margin-left:auto;"><?php esc_html_e('CHIUDI' , 'studio-immens-quick-order');?></div>
	</div>
	<div class="GenericDetailHead">

		<strong>
			<?php echo esc_attr($page->post_title);?>
		</strong>

		<strong> - </strong>

		<strong>
			<?php esc_html_e('id pagina: ' , 'studio-immens-quick-order');?>
			<?php echo esc_attr($page_id);?>
		</strong>

		<div style="margin-left:auto;">
			<?php esc_html_e('status della pagina: ' , 'studio-immens-quick-order');?>
			<?php echo esc_attr($page->post_status);?>
		</div>

	</div>

	<div class="GenericDetailBody">

		<div class="GenericDetailBlock">

			<div>
				<?php esc_html_e('Mostra o Nascondi header al caricamento della pagina' , 'studio-immens-quick-order');?>
				<div class="GenericRadioSec">
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showHead]" value="yes" <?php if(isset($settings->showHead)&&$settings->showHead=='yes'){echo 'checked';}?>>
						<?php esc_html_e('Mostra' , 'studio-immens-quick-order');?>
					</div>
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showHead]" value="no" <?php if(isset($settings->showHead)&&$settings->showHead=='no'){echo 'checked';}?>>
						<?php esc_html_e('Nascondi' , 'studio-immens-quick-order');?>
					</div>
				</div>
			</div>

			<div>
				<?php esc_html_e('Mostra o Nascondi footer al caricamento della pagina' , 'studio-immens-quick-order');?>
				<div class="GenericRadioSec">
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showFoot]" value="yes" <?php if(isset($settings->showFoot)&&$settings->showFoot=='yes'){echo 'checked';}?>>
						<?php esc_html_e('Mostra' , 'studio-immens-quick-order');?>
					</div>
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showFoot]" value="no" <?php if(isset($settings->showFoot)&&$settings->showFoot=='no'){echo 'checked';}?>>
						<?php esc_html_e('Nascondi' , 'studio-immens-quick-order');?>
					</div>
				</div>
			</div>

			<div>
				<?php esc_html_e('Mostra o Nascondi QR code della pagina in testa' , 'studio-immens-quick-order');?>
				<div class="GenericRadioSec">
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showQR]" value="yes" <?php if(isset($settings->showQR)&&$settings->showQR=='yes'){echo 'checked';}?>>
						<?php esc_html_e('Mostra' , 'studio-immens-quick-order');?>
					</div>
					<div>
						<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][showQR]" value="no" <?php if(isset($settings->showQR)&&$settings->showQR=='no'){echo 'checked';}?>>
						<?php esc_html_e('Nascondi' , 'studio-immens-quick-order');?>
					</div>
				</div>
			</div>

		</div>

		<div class="GenericDetailBlock">

			<strong> <?php esc_html_e('Modifica i colori globali della pagina' ,'studio-immens-quick-order');?> </strong>
			<div class="GenericRadioSec">
				<div>
					<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][OverColor]" value="yes" <?php if(isset($settings->OverColor)&&$settings->OverColor=='yes'){echo 'checked';}?>>
					<?php esc_html_e('Si' , 'studio-immens-quick-order');?>
				</div>
				<div>
					<input type="radio" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][OverColor]" value="no" <?php if(isset($settings->OverColor)&&$settings->OverColor=='no'){echo 'checked';}?>>
					<?php esc_html_e('No' , 'studio-immens-quick-order');?>
				</div>
			</div>
			<div>
				<?php esc_html_e('Estendi l\'influenza ad altri selettori css' , 'studio-immens-quick-order');?>
				<input type="text" name="<?php echo 'PGSett[setting_page_id'.esc_attr($page_id);?>][EXTCss]" value="<?php if(isset($settings->EXTCss) && $settings->EXTCss!=''){echo esc_attr($settings->EXTCss);}else{echo 'body, header, footer';}?>">
			</div>

			<div>
				<?php SIQO_setting_color( array(
						'default' => '#00000f',
						'name' => 'PGSett[setting_page_id'.$page_id.'][BGColor]',
						'title' => esc_html__('Colore Sfondo ' , 'studio-immens-quick-order'),
						'setting' => (isset($settings->BGColor))?esc_attr($settings->BGColor):'',
				) ) ?>
			</div>
			<div>
				<?php SIQO_setting_color( array(
						'default' => '#ffffff',
						'name' => 'PGSett[setting_page_id'.$page_id.'][TextColor]',
						'title' => esc_html__('Colore Testo ' , 'studio-immens-quick-order'),
						'setting' => (isset($settings->TextColor))?esc_attr($settings->TextColor):'',
				) ) ?>
			</div>

		</div>

	</div>

	<div class="GenericDetailFoot">
		<button type="submit" name="update" value="update" class="SIQObutton pointer" form="general" style="margin: 0px 0px 0px auto;"> <?php esc_html_e( 'SALVA', 'studio-immens-quick-order' ); ?> </button>

	</div>

</div>
	<div class="GenericBackGroundSection" fo_page_id="<?php echo esc_attr($page_id);?>" style="display:none;"></div>
<?php
}







function SIQO_setting_color( $args = array() ){
    $args['default'] = (isset($args['default']) && $args['default']!='')?$args['default']:'';
    $args['id'] = (isset($args['id']) && $args['id']!='')?$args['id']:'';
    $args['name'] = (isset($args['name']) && $args['name']!='')?$args['name']:'';
    $args['title'] = (isset($args['title']) && $args['title']!='')?$args['title']:'';
    $set_val = (isset($args['setting']) && $args['setting']!='')?$args['setting']:'';

    if ( $set_val != null && $set_val != '') {
    	$value = $set_val;
    } else { $value = $args['default']; }
    ?>
    <div class="SIQOsettingStyle" id="<?php echo esc_attr($args['id']);?>"style="background-color:<?php echo esc_attr($value);?>">
        <p title="<?php echo esc_attr($args['title']);?>"> <?php echo esc_attr($args['title']);?>
            <input type="color" name="<?php echo esc_attr($args['name']);?>" value="<?php echo esc_attr($value);?>" oninput="jQuery(this).parent().parent().css('backgroundColor',this.value);">
            <span class="dashicons dashicons-image-rotate pointer" onclick="SIQO_restore_prev_value( this, <?php echo "'".esc_attr($args['default'])."'";?> )" title="<?php esc_html_e('Ripristina il valore di default', 'studio-immens-quick-order' );?>"></span>
        </p>
    </div>
    <?php siqo_add_inline_js( <<<'JS'

	function SIQO_restore_prev_value( target, value ){
			target.previousElementSibling.value = value;
			jQuery(target).closest('.SIQOsettingStyle').css('backgroundColor',value);
		}
JS
); ?>
    <?php
}









// add_filter( 'woocommerce_single_variations_taxonomies', 'SIQO_add_custom_taxonomies', 10, 1 );
// function SIQO_add_custom_taxonomies( $taxonomies ) {
//    $taxonomies[] = 'siqo_ingredients';
//    $taxonomies[] = 'siqo_allergens';
//    return $taxonomies;
// }






add_action( 'woocommerce_variation_options_pricing', 'SIQO_add_custom_field_to_variations', 10, 3 );
function SIQO_add_custom_field_to_variations( $loop, $variation_data, $variation ) {
echo '<div class="fo-vari-tabs-container">';
	// if (SIQO_get_meta('product_price_to_add') == 'yes') {
		// SIQO_price_to_add_variation( $loop, $variation_data, $variation );
	// }
    $nonce = wp_create_nonce( 'SIQO_custom_field_to_variations_nonce' );
    echo '<input type="hidden" id="_fononce" name="_fononce" value="'.esc_attr($nonce).'" />';

	if (SIQO_get_meta('product_ingredients_tax') == 'yes') {
		SIQO_tax_field_variation( 'siqo_ingredients', $loop, $variation_data, $variation );
	}
	if (SIQO_get_meta('product_allergens_tax') == 'yes') {
		SIQO_tax_field_variation( 'siqo_allergens', $loop, $variation_data, $variation );
	}
	if (SIQO_get_meta('product_temperature_tax') == 'yes') {
		SIQO_tax_field_variation( 'siqo_temperatures', $loop, $variation_data, $variation );
	}
	if (SIQO_get_meta('product_sticker_tax') == 'yes') {
		SIQO_tax_field_variation( 'siqo_stickers', $loop, $variation_data, $variation );
	}
echo '</div>';
}
// function SIQO_price_to_add_variation( $loop, $variation_data, $variation ){
// 	$Price = $variation_data['price_to_add'][0];
// // SIQO_debug($Tax);
// 	echo '<div class="variable_pricing" style="width:100%;">';
// 		echo '<p class="form-field form-row form-row-first">';
// 			echo '<label >'.esc_html__('Prezzo Aggiunta','studio-immens-quick-order').'</label>';
// 			echo '<input type="text" class="short wc_input_price" name="price_to_add['.$loop.']" value="'.$Price.'" placeholder="'.esc_html__('Il Prezzo da aggiungere al prezzo di listino').'">';
// 		echo '</p>';
// 	echo '</div>';
// }

function SIQO_tax_field_variation( $taxonomy, $loop, $variation_data, $variation ){
	$Tax = json_decode($variation_data[$taxonomy][0]);
	$fo_check = '';
// SIQO_debug($variation_data);
	echo '<div class="fo-vari-tabs-panel">';
		echo '<div class="fo-vari-title">'.esc_attr($taxonomy).'</div>';
		echo '<ul class="categorychecklist form-no-clear">';
	$terms = get_terms( array('taxonomy'=>$taxonomy,'hide_empty'=>false) );
	foreach ($terms as $key => $value) {
		$fo_check = ( SIQO_in_array( $value->slug, $Tax ) )? 'checked="checked"': '';
		echo '<li>';
			echo '<label class="selectit">';
				echo '<input name="'.esc_attr($taxonomy).'['.esc_attr($variation->ID).']['.esc_attr($key).']" type="checkbox" value="'.esc_attr($value->slug).'" '.esc_attr($fo_check).'>';
					echo esc_attr($value->slug);
			echo '</label>';
		echo '</li>';
	}
		echo '</ul>';
	echo '</div>';
}
function SIQO_in_array( $search_for, $haystack = array() ){
	foreach ($haystack as $k => $v) {
		if ( $v == $search_for ) {
			$return = true;
			break;
		} else{ $return = false; }
	}
	return $return;
}

add_action( 'woocommerce_save_product_variation', 'SIQO_save_custom_field_variations', 10, 2 );
add_action( 'woocommerce_update_product_variation', 'SIQO_update_custom_field_variations', 10, 2 );
function SIQO_update_custom_field_variations( $variation_id, $product ){
	if ( !isset($_POST['_siqononce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_siqononce'])), 'SIQO_custom_field_to_variations_nonce' ) ) {
		return $variation_id;
	}
   // if ( isset( $_POST['price_to_add'][$variation_id] ) ) {
   // 	   	$price_to_add = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['price_to_add'][$variation_id]));
// 		update_post_meta( $variation_id, 'price_to_add',  wp_json_encode($price_to_add) );
   // }
   if ( isset( $_POST['siqo_ingredients'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Ingredienti = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_ingredients'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_ingredients', wp_json_encode( $Ingredienti ) );
   }
   if ( isset( $_POST['siqo_allergens'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Allergeni = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_allergens'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_allergens',  wp_json_encode($Allergeni) );
   }
   if ( isset( $_POST['siqo_temperatures'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Temperature = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_temperatures'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_temperatures',  wp_json_encode($Temperature) );
   }
   if ( isset( $_POST['siqo_stickers'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Sticker = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_stickers'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_stickers',  wp_json_encode($Sticker) );
   }
}
function SIQO_save_custom_field_variations( $variation_id, $i ) {
	if ( !isset($_POST['_siqononce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_siqononce'])), 'SIQO_custom_field_to_variations_nonce' ) ) {
		return $variation_id;
	}
   if ( isset( $_POST['siqo_ingredients'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Ingredienti = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_ingredients'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_ingredients', wp_json_encode( $Ingredienti ) );
   }
   if ( isset( $_POST['siqo_allergens'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Allergeni = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_allergens'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_allergens',  wp_json_encode($Allergeni) );
   }
   if ( isset( $_POST['siqo_temperatures'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Temperature = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_temperatures'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_temperatures',  wp_json_encode($Temperature) );
   }
   if ( isset( $_POST['siqo_stickers'][$variation_id] ) ) {
   	   	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursive sanitizer is applied immediately.`r`n   	   	$Sticker = SIQO_recursive_sanitize_text_field(wp_unslash($_POST['siqo_stickers'][$variation_id]));
		update_post_meta( $variation_id, 'siqo_stickers',  wp_json_encode($Sticker) );
   }
}



// -----------------------------------------
// 3. Store custom field value into variation data
// add_filter( 'woocommerce_available_variation', 'SIQO_add_custom_field_variation_data' );
function SIQO_add_custom_field_variation_data( $variations ) {
	$variations['price_to_add'] = '<div class="woocommerce_custom_field">price_to_add: <span>' . get_post_meta( $variations[ 'price_to_add' ], 'price_to_add', true ) . '</span></div>';
   $variations['siqo_ingredients'] = '<div class="woocommerce_custom_field">Ingredienti: <span>' . get_post_meta( $variations[ 'variation_id' ], 'siqo_ingredients', true ) . '</span></div>';
   $variations['siqo_allergens'] = '<div class="woocommerce_custom_field">Allergeni: <span>' . get_post_meta( $variations[ 'variation_id' ], 'siqo_allergens', true ) . '</span></div>';
   $variations['siqo_temperatures'] = '<div class="woocommerce_custom_field">Temperature: <span>' . get_post_meta( $variations[ 'variation_id' ], 'siqo_temperatures', true ) . '</span></div>';
    $variations['siqo_stickers'] = '<div class="woocommerce_custom_field">Sticker: <span>' . get_post_meta( $variations[ 'variation_id' ], 'siqo_stickers', true ) . '</span></div>';
   return $variations;
}





/*

function SIQO_wc_attribute_price_to_add( $taxonomy ) {
	// SIQO_debug( $_GET['tag_ID'] );
	// get_term_by('name',$taxonomy);
	$id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
	$value = $id ? get_term_meta( $id, 'price_to_add',true ) : '';

	?>
		<tr class="form-field" style="margin: 10px 0px;">
			<th scope="row" valign="top">
				<label for="price_to_add"><?php esc_html_e('Prezzo Aggiunta','studio-immens-quick-order'); ?></label>
			</th>
			<td>
				<input name="price_to_add" id="price_to_add" type="text" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_html_e('Il Prezzo da aggiungere al prezzo di listino');?>" style="width:100%;margin: 10px 0px;"/>
			</td>
		</tr>
	<?php
}
function SIQO_save_wc_attribute_price_to_add( $term_id ) {
    if( isset( $_POST['price_to_add'] ) && ! empty( $_POST['price_to_add'] ) ) {
        update_term_meta( $term_id, 'price_to_add', $_POST['price_to_add'] );
    } else {
        // delete_term_meta( $term_id, 'price_to_add' );
    }
}
*/

// function SIQO_price_to_add_column_content( $content, $column_name, $term_id ) {
// 	$price_to_add = get_term_meta($term_id, 'price_to_add', true);
// 	// if ( $column_name == 'price_to_add' ) {
// 	// 	echo '<div>'.$price_to_add.'</div>';
// 	// }
// 	switch ( $column_name ) {
//         case 'price_to_add' :
//         echo '<div>'.$price_to_add.'</div>';
//     break;
// 	}
// }
// function SIQO_add_new_price_to_add_taxonomy_columns( $columns ) {
//     $columns['price_to_add'] = esc_html__( 'Aggiunta', 'studio-immens-quick-order' );
//     return $columns;
// }
// if (isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
// 	add_action( 'add_tag_form_fields', 'SIQO_wc_attribute_price_to_add' );
// 	add_action( 'edit_tag_form_fields', 'SIQO_wc_attribute_price_to_add' );

// 	if (isset($_GET['taxonomy']) && $_GET['taxonomy'] != '') {
// 		// add_filter( 'manage_'.$_GET['taxonomy'].'_custom_column', 'SIQO_price_to_add_column_content', 10, 3 );
// 		// add_filter( 'manage_edit-'.$_GET['taxonomy'].'_columns', 'SIQO_add_new_price_to_add_taxonomy_columns', 10, 3 );
// 		add_action( 'created_'.$_GET['taxonomy'], 'SIQO_save_wc_attribute_price_to_add' );
// 		add_action( 'edit_'.$_GET['taxonomy'], 'SIQO_save_wc_attribute_price_to_add' );
// 	}

// }

// $term = get_queried_object();
// $attr_id = wc_attribute_taxonomy_id_by_name( $term->taxonomy );
// get_term_meta( $term_id, 'price_to_add' );









function SIQO_taxonomy_add_custom_field() {
	$nonce = wp_create_nonce( 'SIQO_tax_image_nonce' );
	// $placeholder_image = get_option( 'woocommerce_placeholder_image', 0 );
	// SIQO_debug($placeholder_image);
    ?>
    <div class="form-field term-image-wrap">
        <label for="cat-image"><?php esc_html_e( 'Image', 'studio-immens-quick-order' ); ?></label>
        <p><a href="#" class="aw_upload_image_button button button-secondary"><?php esc_html_e('Carica Immagine', 'studio-immens-quick-order' ); ?></a></p>
        <img width="250px" src="" id="tax-img-src" style="padding:10px;margin:10px;" /><?php  ?>
        <input type="text" name="taxonomy_image" id="tax-image" size="40" />
        <input type="hidden" id="_fononce_tax_image" name="_fononce_tax_image" value="<?php echo esc_attr($nonce);?>"/>
    </div>
    <?php
}
function SIQO_taxonomy_edit_custom_field($term) {
    $image = get_term_meta($term->term_id, 'taxonomy_image', true);
    $nonce = wp_create_nonce( 'SIQO_tax_image_nonce' );
    ?>
    <tr class="form-field term-image-wrap">
        <th scope="row"><label for="taxonomy_image"><?php esc_html_e( 'Image', 'studio-immens-quick-order' ); ?></label></th>
        <td>
            <p><a href="#" class="aw_upload_image_button button button-secondary"><?php esc_html_e('Carica Immagine', 'studio-immens-quick-order' ); ?></a></p><br/>
            <img width="250px" src="<?php echo esc_attr($image); ?>" id="tax-img-src" style="padding:10px;margin:10px;" /><?php  ?>
            <input type="text" name="taxonomy_image" id="tax-image" placeholder="image url" value="<?php echo esc_attr($image); ?>" size="40" />
            <input type="hidden" id="_fononce_tax_image" name="_fononce_tax_image" value="<?php echo esc_attr($nonce);?>"/>
        </td>
    </tr>
    <?php
    // SIQO_debug($image );
}
function SIQO_taxonomy_column_content( $content, $column_name, $term_id ) {
    $image = get_term_meta($term_id, 'taxonomy_image', true);
    if(!SIQOcheck($image)){
    	$image = esc_url( get_home_url() ).'/wp-content/plugins/woocommerce/assets/images/placeholder.png';
    }
        switch ( $column_name ) {
        case 'image' :
        echo '<img style="max-width:80px;max-height:60px" src="'.esc_attr($image).'" />';
        break;
    }
}
function SIQO_add_new_taxonomy_columns( $columns ) {
    $columns['image'] = esc_html__( 'Immagine', 'studio-immens-quick-order' );
    return $columns;
}
function SIQO_save_taxonomy_custom_meta_field( $term_id ) {
	if (!isset($_POST['_siqononce_tax_image']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_siqononce_tax_image'])), 'SIQO_tax_image_nonce' ) ) {
		return $term_id;
	}
    if ( isset( $_POST['taxonomy_image'] ) ) {
        update_term_meta($term_id, 'taxonomy_image', sanitize_text_field(wp_unslash($_POST['taxonomy_image'])));
    }
}
function SIQO_taxonomy_add_meta_image( $taxonomy ){
	add_action( 'edited_'.$taxonomy, 'SIQO_save_taxonomy_custom_meta_field', 10, 2 );
	add_action( 'create_'.$taxonomy, 'SIQO_save_taxonomy_custom_meta_field', 10, 2 );
	add_action( $taxonomy.'_add_form_fields', 'SIQO_taxonomy_add_custom_field', 10, 2 );
	add_action( $taxonomy.'_edit_form_fields', 'SIQO_taxonomy_edit_custom_field', 10, 2 );
	add_filter( 'manage_'.$taxonomy.'_custom_column', 'SIQO_taxonomy_column_content', 10, 3 );
	add_filter( 'manage_edit-'.$taxonomy.'_columns', 'SIQO_add_new_taxonomy_columns', 10, 3 );
}
function SIQO_add_image_custom_field_to_tax(){
	if ( SIQO_get_meta('product_ingredients_tax') == 'yes' ) {
		if ( SIQO_get_meta('product_ingredients_images') == 'yes' ) {
			SIQO_taxonomy_add_meta_image('siqo_ingredients');
		}
	}
	if ( SIQO_get_meta('product_allergens_tax') == 'yes' ) {
		if ( SIQO_get_meta('product_allergens_images') == 'yes' ) {
			SIQO_taxonomy_add_meta_image('siqo_allergens');
		}
	}
	if ( SIQO_get_meta('product_temperature_tax') == 'yes' ) {
		if ( SIQO_get_meta('product_temperature_images') == 'yes' ) {
			SIQO_taxonomy_add_meta_image('siqo_temperatures');
		}
	}
	if ( SIQO_get_meta('product_sticker_tax') == 'yes' ) {
		if ( SIQO_get_meta('product_sticker_images') == 'yes' ) {
			SIQO_taxonomy_add_meta_image('siqo_stickers');
		}
	}
	if ( SIQO_get_meta('product_macro_categories_tax') == 'yes' ) {
		if ( SIQO_get_meta('product_macro_categories_images') == 'yes' ) {
			SIQO_taxonomy_add_meta_image('siqo_macro_categories');
		}
	}
	if ( SIQO_get_meta('product_tags_images') == 'yes' ) {
		SIQO_taxonomy_add_meta_image('product_tag');
	}
	if ( SIQO_get_meta('posts_category_images') == 'yes' ) {
		SIQO_taxonomy_add_meta_image('category');
	}
	if ( SIQO_get_meta('posts_tag_images') == 'yes' ) {
		SIQO_taxonomy_add_meta_image('post_tag');
	}
}
add_action( 'init', 'SIQO_add_image_custom_field_to_tax' );








/**
 *
 * ---------- Utility functions ----------
 *
 *
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */

/**
 * Normalizza un valore di user meta ruolo in booleano.
 * Accetta 'on', '1', true, 'yes' (storicamente i ruoli venivano salvati come 'on'
 * oppure come false/'' quando la checkbox era deselezionata).
 *
 * @param mixed $value Valore grezzo dello user meta.
 * @return bool
 */
function SIQO_role_meta_is_on( $value ) {
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
		return false;
	}
	return in_array( strtolower( (string) $value ), array( 'on', '1', 'yes', 'true' ), true );
}

/**
 * Restituisce i ruoli Quick Order dell'utente (se non specificato, l'utente corrente).
 * I ruoli sono gerarchici: manager implica supervisor e worker, supervisor implica worker.
 *
 * @param int $user_id ID utente, 0 per l'utente corrente.
 * @return array{worker:bool,supervisor:bool,manager:bool}
 */
function SIQO_get_user_role_flags( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	$worker     = $user_id ? SIQO_role_meta_is_on( get_user_meta( $user_id, 'siqo_user_role_worker', true ) ) : false;
	$supervisor = $user_id ? SIQO_role_meta_is_on( get_user_meta( $user_id, 'siqo_user_role_supervisor', true ) ) : false;
	$manager    = $user_id ? SIQO_role_meta_is_on( get_user_meta( $user_id, 'siqo_user_role_manager', true ) ) : false;

	if ( $manager ) {
		$supervisor = true;
	}
	if ( $supervisor ) {
		$worker = true;
	}

	return array(
		'worker'     => $worker,
		'supervisor' => $supervisor,
		'manager'    => $manager,
	);
}

function SIQO_access_autorization( $role = 'worker',  $mode = true ){
	$flags = SIQO_get_user_role_flags();

	if ( ! $flags['worker'] ) {
		return false;
	}

	if ( $mode ) {
		// Gerarchia completa: manager > supervisor > worker.
		switch ( $role ) {
			case 'manager':
				return $flags['manager'];
			case 'supervisor':
				return $flags['supervisor'];
			case 'worker':
			default:
				return $flags['worker'];
		}
	}

	// Modalita' stretta: richiede esattamente il ruolo indicato, senza ereditarieta'.
	return isset( $flags[ $role ] ) ? (bool) $flags[ $role ] : false;
}
function SIQO_access_autorization_level(){
	$flags = SIQO_get_user_role_flags();

	$autorization = 0;
	if ( $flags['worker'] ) { $autorization += 1; }
	if ( $flags['supervisor'] ) { $autorization += 1; }
	if ( $flags['manager'] ) { $autorization += 1; }

	return $autorization;
}


function SIQO_access_denied(){
	$autorization = SIQO_access_autorization();
	if ( !$autorization ) { ?>
	<div style="width:calc(100% - 60px);margin:20px;padding:10px;">
		<div class="title">
			<?php esc_html_e( 'NON Hai il permesso per accedere a questa pagina!', 'studio-immens-quick-order' ); ?>
			<a href="<?php echo esc_url(get_permalink( get_option('woocommerce_myaccount_page_id') )); ?>"><?php esc_html_e( ' Clicca qui per Accedere ', 'studio-immens-quick-order' ); ?> </a>
		</div>
	</div>
	<?php exit; }
}

function SIQO_soft_access_denied(){
	$user = wp_get_current_user();

	if ( !$user->ID ) { ?>
		<div id="SIQOSetting_UserLoggedIn" style="display:none!important;">0</div>
	<div class="siqo-access-notice" style="width:calc(100% - 60px);margin:20px;padding:10px;">
		<div class="title">
			<?php esc_html_e( 'Per poter effettuare ordini su questa pagina, ÃƒÆ’Ã‚Â¨ necessario effettuare l\'accesso.', 'studio-immens-quick-order' ); ?>
			<a href="<?php echo esc_url(get_permalink( get_option('woocommerce_myaccount_page_id') )); ?>"><?php esc_html_e( ' Clicca qui per Accedere ', 'studio-immens-quick-order' ); ?> </a>
		</div>
	</div>

	<div class="SIQO_logIn_popUp" style="display:none;">
		<div class="siqo_short_form">
			<?php
			if ( SIQO_get_meta('custom_shortcode_login')!='' ) {
				$login_short = SIQO_get_meta('custom_shortcode_login');
			} else{
				$login_short = '[woocommerce_my_account]';
			}
			echo do_shortcode( $login_short );
			?>
		</div>
		<div class="foBackground"></div>
	</div>

	<?php } else{ ?>
		<div id="SIQOSetting_UserLoggedIn" style="display:none!important;">100000</div>
	<?php }
}



function SIQO_get_qr_dir() {
	$uploads = wp_upload_dir();
	$base    = ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) )
		? $uploads['basedir']
		: plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/QRgenerate/';
	$qr_dir  = trailingslashit( $base ) . 'siqo-qr/';
	if ( ! is_dir( $qr_dir ) ) {
		wp_mkdir_p( $qr_dir );
	}
	return trailingslashit( $qr_dir );
}

function SIQO_get_qr_url() {
	$uploads = wp_upload_dir();
	$base    = ( empty( $uploads['error'] ) && ! empty( $uploads['baseurl'] ) )
		? $uploads['baseurl']
		: plugin_dir_url( dirname( __FILE__ ) ) . 'includes/phpqrcode/QRgenerate/';
	return trailingslashit( $base ) . 'siqo-qr/';
}

function SIQO_create_post_QR_code() {
	// The file responsible for defining qr code generator library
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';

	$atts = array(
		'size'    	=> '150', // Default size.
		'post_id' 	=> get_the_ID(), // Default post ID.
	);
	$post_id = absint( $atts['post_id'] );
	$size    = absint( $atts['size'] );

	$url = esc_url_raw( trailingslashit( get_site_url() ) . '?p=' . $post_id );

	$qr_dir = SIQO_get_qr_dir();
	// $qr_dir   = content_url() . 'uploads/';
	$filename = $qr_dir . 'post_' . $post_id .'('.$size.').png';

	// Check if the file exists.
	if ( ! file_exists( $filename ) ) {
		\SIQO\QuickOrder\PhpQrCode\QRcode::png( $url, $filename, 'L', 10, 2 ); // Create the QR code.
	}
	// Return the image tag.
	$qr_url = esc_url_raw( SIQO_get_qr_url() . 'post_' . $post_id .'('.$size.').png' );

	$return = '<div class="siqo_QR_pages">';
	$return .= '<strong style="width:100%;">'.esc_html__('Link per Questa Pagina:' , 'studio-immens-quick-order').'</strong>';
	$return .= '<strong style="margin:20px;">'.esc_html( get_home_url() ).'?p='.absint( $post_id ).'</strong>';
	$return .= '<img src="'.esc_url($qr_url).'" height="'.esc_attr($size).'" width="'.esc_attr($size).'" alt="">';
	$return .= '</div>';
	return $return;
}



function SIQO_create_post_QR_code_shortcode( $atts ) {
// The file responsible for defining qr code generator library
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';
// Extract shortcode attributes.
	$atts = shortcode_atts(
		array(
			'size'    	=> '150', // Default size.
			'post_id' 	=> get_the_ID(), // Default post ID.
		),
		$atts,
		'fo_qr_code' // Shortcode tag.
	);
	$post_id = absint( $atts['post_id'] );
	$size    = absint( $atts['size'] );

	$url = esc_url_raw( trailingslashit( get_site_url() ) . '?p=' . $post_id );

	$qr_dir = SIQO_get_qr_dir();
	$filename = $qr_dir . 'post_' . $post_id .'('.$size.').png';
// Check if the file exists.
	if ( ! file_exists( $filename ) ) {
		\SIQO\QuickOrder\PhpQrCode\QRcode::png( $url, $filename, 'L', 10, 2 ); // Create the QR code.
	}
// Return the image tag.
	$qr_url = esc_url_raw( SIQO_get_qr_url() . 'post_' . $post_id .'('.$size.').png' );
	return '<img src="'.esc_url( $qr_url ).'" height="'.absint( $size ).'" width="'.absint( $size ).'" alt="">';
}
add_shortcode( 'siqo_qr_code', 'SIQO_create_post_QR_code_shortcode' );

add_shortcode( 'siqo_table_qr', 'SIQO_table_qr_shortcode' );
function SIQO_table_qr_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'table' => 0, 'size' => 200 ),
		$atts,
		'siqo_table_qr'
	);
	$table_id = absint( $atts['table'] );
	if ( ! $table_id || 'siqo_tables' !== get_post_type( $table_id ) || 'publish' !== get_post_status( $table_id ) ) {
		return '';
	}
	$size = min( 600, max( 80, absint( $atts['size'] ) ) );
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';
	$url      = SIQO_Table_Sessions::table_url( $table_id );
	$qr_dir   = SIQO_get_qr_dir();
	$hash     = substr( md5( $url ), 0, 8 );
	$filename = $qr_dir . 'table_' . $table_id . '_' . $size . '_' . $hash . '.png';
	$scale    = max( 1, min( 10, (int) round( $size / 20 ) ) );
	if ( ! file_exists( $filename ) ) {
		\SIQO\QuickOrder\PhpQrCode\QRcode::png( $url, $filename, 'L', $scale, 2 );
	}
	$qr_url = esc_url_raw( SIQO_get_qr_url() . 'table_' . $table_id . '_' . $size . '_' . $hash . '.png' );
	return '<img src="' . esc_url( $qr_url ) . '" width="' . absint( $size ) . '" height="' . absint( $size ) . '" alt="' . esc_attr( get_the_title( $table_id ) ) . '">';
}

function SIQO_create_any_QR_code( $atts ) {
// The file responsible for defining qr code generator library
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';
// Extract shortcode attributes.
	$atts = shortcode_atts(
		array(
			'size'    	=> '150', // Default size.
			'content' 	=> get_home_url(), // Default post ID.
		),
		$atts,
		'fo_qr_code_content' // Shortcode tag.
	);

	$size 	 = absint( $atts['size'] );
	$content = sanitize_text_field( $atts['content'] );
	$name 	 = md5(sanitize_text_field( $atts['content'] ));

	$qr_dir = SIQO_get_qr_dir();
	$filename = $qr_dir.'any-'.$name.'('.$size.').png';
// Check if the file exists.
	if ( ! file_exists( $filename ) ) {
		\SIQO\QuickOrder\PhpQrCode\QRcode::png( $content, $filename, 'L', 10, 2 ); // Create the QR code.
	}
// Return the image tag.
	$qr_url = esc_url_raw( SIQO_get_qr_url() . 'any-'.$name.'('.$size.').png' );
	return '<img src="'.esc_url( $qr_url ).'" height="'.absint( $size ).'" width="'.absint( $size ).'" alt="">';
}
add_shortcode( 'siqo_qr_code_content', 'SIQO_create_any_QR_code' );

function SIQO_create_any_QR_code_ajax() {
	if ( !isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'SIQO_main_page' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';

	$size = (isset($_POST['size']) && sanitize_text_field(wp_unslash($_POST['size'])) != '')? absint( wp_unslash( $_POST['size'] ) ): 150;
	if ( $size <= 0 ) {
		$size = 150;
	}
	$content = (isset($_POST['content']) && sanitize_text_field(wp_unslash($_POST['content'])) != '')? sanitize_text_field(wp_unslash($_POST['content'])): get_home_url();

	$qr_dir = SIQO_get_qr_dir();
	$qr_name = md5( $content );
	$filename = $qr_dir.'any-'.$qr_name.'('.$size.').png';
// Check if the file exists.
	if ( ! file_exists( $filename ) ) {
		\SIQO\QuickOrder\PhpQrCode\QRcode::png( $content, $filename, 'L', 10, 2 ); // Create the QR code.
	}
	$qr_url = SIQO_get_qr_url() . 'any-'.$qr_name.'('.$size.').png';

	wp_send_json(array(
		'size' 		=> esc_attr($size),
		'content' 	=> esc_attr($content),
		'qr_url' 	=> esc_url_raw($qr_url),
	));
	die();
}
add_action('wp_ajax_siqo_create_any_QR_code_ajax', 'SIQO_create_any_QR_code_ajax');


function SIQO_delete_QR_images(){
	if ( !isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'SIQO_main_page' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
    if(isset($_POST['del_path']) && sanitize_text_field(wp_unslash($_POST['del_path'])) != '') {
        $del_path = sanitize_text_field(wp_unslash($_POST['del_path']));
        $qr_dir = SIQO_get_qr_dir();
        $real_path = realpath($del_path);
        $real_qr_dir = realpath($qr_dir);
        if ($real_path && $real_qr_dir && strpos($real_path, rtrim($real_qr_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 && file_exists($real_path)) {
            wp_delete_file( $real_path );
        }
    }

    wp_send_json(array(
		'del_path' 	=> isset($_POST['del_path']) ? sanitize_text_field(wp_unslash($_POST['del_path'])) : '',
	));
	die();
}
add_action('wp_ajax_siqo_delete_QR_images', 'SIQO_delete_QR_images');




function SIQO_hover_message( $message = '', $args = array() ){
	$default_args = array(
		'callback' 	=> '',
		'action' 	=> ''
	);
	$action 	= ( isset($args['action']) ) ? $args['action'] : $default_args['action'];
	$callback 	= ( isset($args['callback']) ) ? $args['callback'] : $default_args['callback'];
?>
<div class="hoverMessage" style="display:none;" onclick="jQuery(this).hide()">
	<?php
	echo esc_attr($message);
	if ( $callback != '' ) {
		add_action('SIQO_hover_message', $callback );
	}
	do_action( 'SIQO_hover_message' );
	?>
	<?php siqo_add_inline_js( <<<'JS'

		jQuery(document).ready(function($) {
		    "use strict";
		    jQuery('.hoverMessage').appendTo('body');
		    jQuery('.hoverMessage').show();
		    jQuery('.hoverMessage').css('opacity','0.95');
		});
JS
); ?>
</div>
<?php
}

function SIQO_loading_message(){
	?>
	<div class="FOloadingMessage" style="display:none;">
		<span style="animation: fospin 1s infinite;font-size:150px;width:150px;height:150px;margin-top:20vh;" class="dashicons dashicons-update"></span>
<!-- dashicons-update-alt -->
<!-- dashicons-hourglass -->
	</div>
<?php
}
add_action( 'wp_body_open', 'SIQO_loading_message' );


function siqo_load_dashicons(){
   wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'siqo_load_dashicons', 999);


function SIQO_init_css_activator(){
	$default_css = SIQO_default_css();
	foreach ($default_css as $key => $value) {
		SIQO_update_meta( $key, $value, 'style_css' );
		SIQO_clear_meta_database( $key );
	}
}

function SIQO_default_css(){
	// $data_css = (array)json_decode( SIQO_get_meta('default_css') );
	$default_css = array(
		'--siqo-main-color' 		=> '#354357',
		'--siqo-scnd-color' 		=> '#715b3e',
		'--siqo-high-color' 		=> '#3a5a2d',
		'--siqo-invert-color' 	=> '#0091ff',
		'--siqo-link-color' 		=> '#c2c2c2',
		'--siqo-selection-color' 	=> '#ffc4b1',
		'--siqo-error-color' 		=> '#f50000',

		'--siqo-bg-color' 		=> '#505050',
		'--siqo-bg2-color' 		=> '#404040',
		'--siqo-bg3-color' 		=> '#303030',
		'--siqo-bg4-color' 		=> '#202020',
		'--siqo-bg5-color' 		=> '#101010',
		'--siqo-bg6-color' 		=> '#000000',

		'--siqo-text-color' 		=> '#ffffff',
		'--siqo-text-color-inv' 	=> '#000000',
		'--siqo-text-shadow' 		=> ' 2px 0 #000, -2px 0 #000, 0 2px #000, 0 -2px #000, 1px 1px #000, -1px -1px #000, 1px -1px #000, -1px 1px #000;',
		'--siqo-main-border' 		=> '1px solid #715b3e',

		'--siqo-main-tran' 		=> '0.6s',
		'--siqo-scnd-tran' 		=> '0.3s',

		'--siqo-pending' 		=> '#00f5d0',
		'--siqo-processing' 	=> '#f5d400',
		'--siqo-failed' 		=> '#f50000',
		'--siqo-cancelled' 	=> '#f50000',
		'--siqo-refunded' 	=> '#f50000',
		'--siqo-completed' 	=> '#279900',
		'--siqo-modified' 	=> '#f55600',

		'--siqo-tab-1' 	=> '#279900',
		'--siqo-tab-2' 	=> '#f50000',
		'--siqo-tab-3' 	=> '#9300f5',
		'--siqo-tab-4' 	=> '#f5d400',
		'--siqo-tab-5' 	=> '#1a63f4',
		'--siqo-tab-6' 	=> '#00f5d0',

		'--siqo-timer-1' 	=> '#279900',
		'--siqo-timer-2' 	=> '#f5d400',
		'--siqo-timer-3' 	=> '#f50000'
	);
	SIQO_update_meta( 'default_css', wp_json_encode( $default_css ) );
	return $default_css;
}
function SIQO_customized_css(){
	$default_css = SIQO_default_css();
	$style = array(
		'--siqo-main-color' 		=> ( SIQO_get_meta( '--siqo-main-color' ) != null ) ? SIQO_get_meta( '--siqo-main-color' ) : $default_css['--siqo-main-color'],
		'--siqo-scnd-color' 		=> ( SIQO_get_meta( '--siqo-scnd-color' ) != null ) ? SIQO_get_meta( '--siqo-scnd-color' ) : $default_css['--siqo-scnd-color'],
		'--siqo-high-color' 		=> ( SIQO_get_meta( '--siqo-high-color' ) != null ) ? SIQO_get_meta( '--siqo-high-color' ) : $default_css['--siqo-high-color'],
		'--siqo-invert-color' 	=> ( SIQO_get_meta( '--siqo-invert-color' ) != null ) ? SIQO_get_meta( '--siqo-invert-color' ) : $default_css['--siqo-invert-color'],
		'--siqo-link-color' 		=> ( SIQO_get_meta( '--siqo-link-color' ) != null ) ? SIQO_get_meta( '--siqo-link-color' ) : $default_css['--siqo-link-color'],
		'--siqo-selection-color' 	=> ( SIQO_get_meta( '--siqo-selection-color' ) != null ) ? SIQO_get_meta( '--siqo-selection-color' ) : $default_css['--siqo-selection-color'],
		'--siqo-bg-color' 		=> ( SIQO_get_meta( '--siqo-bg-color' ) != null ) ? SIQO_get_meta( '--siqo-bg-color' ) : $default_css['--siqo-bg-color'],
		'--siqo-bg2-color' 		=> ( SIQO_get_meta( '--siqo-bg2-color' ) != null ) ? SIQO_get_meta( '--siqo-bg2-color' ) : $default_css['--siqo-bg2-color'],
		'--siqo-bg3-color' 		=> ( SIQO_get_meta( '--siqo-bg3-color' ) != null ) ? SIQO_get_meta( '--siqo-bg3-color' ) : $default_css['--siqo-bg3-color'],
		'--siqo-bg4-color' 		=> ( SIQO_get_meta( '--siqo-bg4-color' ) != null ) ? SIQO_get_meta( '--siqo-bg4-color' ) : $default_css['--siqo-bg4-color'],
		'--siqo-bg5-color' 		=> ( SIQO_get_meta( '--siqo-bg5-color' ) != null ) ? SIQO_get_meta( '--siqo-bg5-color' ) : $default_css['--siqo-bg5-color'],
		'--siqo-bg6-color' 		=> ( SIQO_get_meta( '--siqo-bg6-color' ) != null ) ? SIQO_get_meta( '--siqo-bg6-color' ) : $default_css['--siqo-bg6-color'],
		'--siqo-text-color' 		=> ( SIQO_get_meta( '--siqo-text-color' ) != null ) ? SIQO_get_meta( '--siqo-text-color' ) : $default_css['--siqo-text-color'],
		'--siqo-text-color-inv' 	=> ( SIQO_get_meta( '--siqo-text-color-inv' ) != null ) ? SIQO_get_meta( '--siqo-text-color-inv' ) : $default_css['--siqo-text-color-inv'],
		'--siqo-text-shadow' 		=> ( SIQO_get_meta( '--siqo-text-shadow' ) != null ) ? SIQO_get_meta( '--siqo-text-shadow' ) : $default_css['--siqo-text-shadow'],
		'--siqo-main-border' 		=> ( SIQO_get_meta( '--siqo-main-border' ) != null ) ? SIQO_get_meta( '--siqo-main-border' ) : $default_css['--siqo-main-border'],
		'--siqo-main-tran' 		=> ( SIQO_get_meta( '--siqo-main-tran' ) != null ) ? SIQO_get_meta( '--siqo-main-tran' ) : $default_css['--siqo-main-tran'],
		'--siqo-scnd-tran' 		=> ( SIQO_get_meta( '--siqo-scnd-tran' ) != null ) ? SIQO_get_meta( '--siqo-scnd-tran' ) : $default_css['--siqo-scnd-tran'],

		'--siqo-pending' 		=> ( SIQO_get_meta( '--siqo-pending' ) != null ) ? SIQO_get_meta( '--siqo-pending' ) : $default_css['--siqo-pending'],
		'--siqo-processing' 	=> ( SIQO_get_meta( '--siqo-processing' ) != null ) ? SIQO_get_meta( '--siqo-processing' ) : $default_css['--siqo-processing'],
		'--siqo-failed' 		=> ( SIQO_get_meta( '--siqo-failed' ) != null ) ? SIQO_get_meta( '--siqo-failed' ) : $default_css['--siqo-failed'],
		'--siqo-cancelled' 	=> ( SIQO_get_meta( '--siqo-cancelled' ) != null ) ? SIQO_get_meta( '--siqo-cancelled' ) : $default_css['--siqo-cancelled'],
		'--siqo-refunded' 	=> ( SIQO_get_meta( '--siqo-refunded' ) != null ) ? SIQO_get_meta( '--siqo-refunded' ) : $default_css['--siqo-refunded'],
		'--siqo-completed' 	=> ( SIQO_get_meta( '--siqo-completed' ) != null ) ? SIQO_get_meta( '--siqo-completed' ) : $default_css['--siqo-completed'],

		'--siqo-modified' 	=> ( SIQO_get_meta( '--siqo-modified' ) != null ) ? SIQO_get_meta( '--siqo-modified' ) : $default_css['--siqo-modified'],

		'--siqo-tab-1' 	=> ( SIQO_get_meta( '--siqo-tab-1' ) != null ) ? SIQO_get_meta( '--siqo-tab-1' ) : $default_css['--siqo-tab-1'],
		'--siqo-tab-2' 	=> ( SIQO_get_meta( '--siqo-tab-2' ) != null ) ? SIQO_get_meta( '--siqo-tab-2' ) : $default_css['--siqo-tab-2'],
		'--siqo-tab-3' 	=> ( SIQO_get_meta( '--siqo-tab-3' ) != null ) ? SIQO_get_meta( '--siqo-tab-3' ) : $default_css['--siqo-tab-3'],
		'--siqo-tab-4' 	=> ( SIQO_get_meta( '--siqo-tab-4' ) != null ) ? SIQO_get_meta( '--siqo-tab-4' ) : $default_css['--siqo-tab-4'],
		'--siqo-tab-5' 	=> ( SIQO_get_meta( '--siqo-tab-5' ) != null ) ? SIQO_get_meta( '--siqo-tab-5' ) : $default_css['--siqo-tab-5'],
		'--siqo-tab-6' 	=> ( SIQO_get_meta( '--siqo-tab-6' ) != null ) ? SIQO_get_meta( '--siqo-tab-6' ) : $default_css['--siqo-tab-6'],


		'--siqo-timer-1' 	=> ( SIQO_get_meta( '--siqo-timer-1' ) != null ) ? SIQO_get_meta( '--siqo-timer-1' ) : $default_css['--siqo-timer-1'],
		'--siqo-timer-2' 	=> ( SIQO_get_meta( '--siqo-timer-2' ) != null ) ? SIQO_get_meta( '--siqo-timer-2' ) : $default_css['--siqo-timer-2'],
		'--siqo-timer-3' 	=> ( SIQO_get_meta( '--siqo-timer-3' ) != null ) ? SIQO_get_meta( '--siqo-timer-3' ) : $default_css['--siqo-timer-3']
	);
		return $style;
}
/**
 * Fired during plugin activation.
 *
 * This function create flash order meta table, called 'siqo_meta' in the database.
 *
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_create_meta_table( $version = STUDIO_IMMENS_QUICK_ORDER_META_VERSION ){
	$legacy_version = get_option( 'flash_order_meta_table' );
	if ( false !== $legacy_version ) {
		update_option( 'siqo_meta_table', $legacy_version );
		delete_option( 'flash_order_meta_table' );
	}
	update_option( 'siqo_meta_table', STUDIO_IMMENS_QUICK_ORDER_META_VERSION );
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $table_name = $wpdb->prefix . "siqo_meta";  //get the database table prefix to create my new table

    $sql = "CREATE TABLE $table_name (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      meta_key varchar(255),
      meta_value text,
      assoc_id varchar(255),
      assoc_tb varchar(255),
      PRIMARY KEY  (id),
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
 * This function retrieve flash order meta_value or entire row from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_get_meta( $meta_key, $type = 'var' ){
  global $wpdb;
  $cache_key = 'siqo_meta_' . md5( $meta_key . '_' . $type );
  $cached = wp_cache_get( $cache_key, 'siqo_meta' );
  if ( $cached !== false ) {
    return $cached;
  }
  $table = $wpdb->prefix . "siqo_meta";
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
 * This function retrieve flash order meta_value or entire results from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_get_meta_by_assoc_id( $assoc_id, $type = 'var' ){
  global $wpdb;
  $cache_key = 'siqo_meta_assoc_' . md5( $assoc_id . '_' . $type );
  $cached = wp_cache_get( $cache_key, 'siqo_meta' );
  if ( $cached !== false ) {
    return $cached;
  }
  $table = $wpdb->prefix . "siqo_meta";
  if ( $type == 'var' ) {
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE assoc_id = %s", [ $table, $assoc_id ]) );
  } else {
    $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE assoc_id = %s", [ $table, $assoc_id ] ), $type );
  }
  wp_cache_set( $cache_key, $result, 'siqo_meta' );
  return $result;
}
/**
 * This function retrieve flash order meta_value or entire results from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_get_meta_by_assoc_tb( $assoc_tb, $type = 'var' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  if ( $type == 'var' ) {
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE assoc_tb = %s", [ $table, $assoc_tb ] ) );
  } else {
    $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE assoc_tb = %s", [ $table, $assoc_tb ] ), $type );
  }
  return $result;
}
/**
 * This function retrieve flash order meta_value or entire row from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_get_meta_by_id( $id, $type = 'var' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  if ( $type == 'var' ) {
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM %i WHERE id = %s", [ $table, $id ] ) );
  } else {
    $result = $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM %i WHERE id = %s", [ $table, $id ] ), $type );
  }
  return $result;
}
/**
 * This function insert meta row in the table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_insert_meta( $meta_key, $meta_value, $assoc_id = null, $assoc_tb = null ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  $result = $wpdb->insert( $table, array( 'meta_key'=>$meta_key, 'meta_value'=>$meta_value, 'assoc_id'=>$assoc_id,'assoc_tb'=>$assoc_tb ) );
  return $result;
}
/**
 * This function update meta row in the table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_update_meta( $meta_key, $meta_value, $assoc_id = null, $assoc_tb = null ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  $meta = SIQO_get_meta( $meta_key );

  if ( $meta != null ) {
    $result = $wpdb->update( $table, array( 'meta_value'=>$meta_value, 'assoc_id'=>$assoc_id,'assoc_tb'=>$assoc_tb ), array( 'meta_key'=>$meta_key ) );
  } else {
    $result = $wpdb->insert( $table, array( 'meta_key'=>$meta_key, 'meta_value'=>$meta_value, 'assoc_id'=>$assoc_id,'assoc_tb'=>$assoc_tb ) );
  }
  wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_var' ), 'siqo_meta' );
  wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_all' ), 'siqo_meta' );
  return $result;
}
/**
 * This function delete meta row from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_delete_meta( $meta_key ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  $result = $wpdb->delete( $table, array( 'meta_key'=>$meta_key ) );
  wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_var' ), 'siqo_meta' );
  wp_cache_delete( 'siqo_meta_' . md5( $meta_key . '_all' ), 'siqo_meta' );
  return $result;
}
/**
 * This function delete meta row from table 'siqo_meta'
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
function SIQO_delete_meta_by_id( $id ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_meta";
  $result = $wpdb->delete( $table, array( 'id'=>$id ) );
  wp_cache_flush_group( 'siqo_meta' );
  return $result;
}

function SIQO_get_table_by_table_number_status_last( $table_number, $status = 0, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_number = %s AND status > %d ORDER BY last_update DESC", $table, $table_number, $status ), $type );
	return $result;
}

function SIQO_get_table_by_table_id_status_last( $table_id, $status = 0, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_id = %s AND status > %d ORDER BY last_update DESC", $table, $table_id, $status ), $type );
	return $result;
}

function SIQO_get_all_tables_by_status_last( $status = 0, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status > %d AND status < 10 ORDER BY last_update DESC", $table, $status ), $type );
	return $result;
}


/**
 * Fired during plugin activation.
 *
 * This function create flash order table, called 'siqo_tables' in the database.
 *
 * @since      1.0.0
 * @package    SIQO_Plugin
 * @subpackage SIQO_Plugin/includes
 * @author     Mauro Arnone - Studio Immens <info@studioimmens.com>
 */
// SIQOP_create_table(STUDIO_IMMENS_QUICK_ORDER_TABLE_VERSION);
function SIQOP_create_table( $version = STUDIO_IMMENS_QUICK_ORDER_TABLE_VERSION ){
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $table_name = $wpdb->prefix . "siqo_tables";

    $sql = "CREATE TABLE $table_name (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      table_number varchar(75),
      table_id varchar(75),
      start_time datetime,
      orders varchar(200),
      status varchar(75),
      prev_status varchar(75),
      end_time datetime,
      totals varchar(10),
      info text,
      receipt text,
      other varchar(255),
      last_update datetime,
      PRIMARY KEY  (id),
      KEY table_number (table_number),
      KEY orders (orders),
      KEY status (status),
      KEY prev_status (prev_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    dbDelta( $sql );

  $meta_table = get_option( 'siqo_tables' );
  if ( $meta_table != $version ) {
    update_option( 'siqo_tables', $version );
  }
}
function SIQOP_get_table_by_id( $id, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  if ( $type == 'var' ) {
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT table_number FROM %i WHERE id = %s", $table, $id ) );
  } else {
    $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %s", $table, $id ), $type );
  }
  return $result;
}

function SIQOP_get_active_tables( $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  	$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status > 0", $table ), $type );
  return $result;
}

function SIQOP_set_inactive_tables_from_date( $type = 'OBJECT' ){
	global $wpdb;
	$table = $wpdb->prefix . "siqo_tables";
	$now = new DateTime();
	$result = SIQOP_get_active_tables();
	$limit_date_expiry = intval( SIQO_get_meta('siqo_limit_date_expiry') ) * 3600;

	$date_to_compare = gmdate('Y-m-d H:i:s', time() - $limit_date_expiry );

// SIQO_debug( $limit_date_expiry );
// SIQO_debug( gmdate('Y-m-d H:i:s', time() - $limit_date_expiry ) );
// SIQO_debug( $result[0]->last_update );

	foreach ($result as $key => $value) {
		if ( $value->last_update <= $date_to_compare ) {
			// echo " string ";
			// $result = $wpdb->update( $table, array( 'siqo_table_status'=>'0', 'prev_status'=>$value->status, 'end_time'=>$now->format('Y-m-d H:i:s'), 'last_update'=>$now->format('Y-m-d H:i:s') ), array( 'id'=>$value->id ) );
		}
	}
	// return $result;
}
function SIQOP_insert_table( $args ){
  global $wpdb;
  $now = new DateTime();
  $default_args = array(
  	'table_number' 	=> 0,
  	'table_id' 		=> 0,
  	'start_time' 	=> wp_date('Y-m-d H:i:s'),
  	'orders' 		=> '',
  	'siqo_table_status' 		=> 1,
  	'prev_status' 	=> '',
  	'end_time' 		=> '',
  	'totals' 		=> '',
  	'info' 			=> '',
  	'receipt' 		=> '',
  	'other' 		=> '',
  	'last_update' 	=> wp_date('Y-m-d H:i:s')
  	);
  $final = wp_parse_args( $args, $default_args );
  $final['orders'] = wp_json_encode( (array)$final['orders'] );

  $table = $wpdb->prefix . "siqo_tables";
  $result = $wpdb->insert( $table, array( 'table_number'=>$final['table_number'], 'table_id'=>$final['table_id'], 'start_time'=>$final['start_time'], 'orders'=>$final['orders'], 'status'=>$final['siqo_table_status'], 'prev_status'=>$final['prev_status'], 'end_time'=>$final['end_time'], 'totals'=>$final['totals'], 'info'=>$final['info'], 'receipt'=>$final['receipt'], 'other'=>$final['other'], 'last_update'=>$final['last_update'] ) );
  return $result;
}
function SIQOP_update_table( $id, $args, $replace_orders = false ){
  global $wpdb;
  	$table = $wpdb->prefix . "siqo_tables";
  	$now = new DateTime();
	$meta = SIQOP_get_table_by_id( $id, 'OBJECT' );
	// $default_args = array(
	// 'table_number' 	=> 0,
	// 'table_id' 		=> 0,
	// 'orders' 		=> '',
	// 'siqo_table_status' 		=> 1,
	// 'end_time' 		=> '',
	// 'totals' 		=> 0,
	// 'info' 			=> '',
	// 'receipt' 		=> '',
	// 'other' 		=> '',
	// 'last_update' 	=> ''
	// );
  // $final_args = wp_parse_args( $args, $default_args );
	$final = wp_parse_args( $args, (array) $meta );
	if ( isset( $args['siqo_table_status'] ) ) {
		$final['siqo_table_status'] = $args['siqo_table_status'];
	} elseif ( ! isset( $final['siqo_table_status'] ) ) {
		$final['siqo_table_status'] = isset( $final['status'] ) ? $final['status'] : 1;
	}
	if ( $final['siqo_table_status'] == 10 ) {
		$final['end_time'] = wp_date('Y-m-d H:i:s');
	}
	$meta_order = ( $meta->orders != '' ) ? json_decode( $meta->orders ) : array();
	$args_order = ( isset( $args['orders'] ) && $args['orders'] != '' ) ? json_decode( $args['orders'] ) : array();

	if ( $replace_orders ) {
		$merged_orders = array_values( array_unique( (array) $args_order ) );
	} else {
		$merged_orders = array_values( array_unique( array_merge( (array) $meta_order, (array) $args_order ) ) );
	}
	$final['orders'] = wp_json_encode( $merged_orders );
	if ( ! empty( $merged_orders ) ) {
		$orders_total = 0;
		foreach ( $merged_orders as $merged_order_id ) {
			$merged_order = wc_get_order( $merged_order_id );
			if ( $merged_order ) {
				$orders_total += (float) $merged_order->get_total();
			}
		}
		$final['totals'] = (string) $orders_total;
	}

		$result = $wpdb->update( $table, array(
			'table_number'=> $final['table_number'],
			'table_id'	=> $final['table_id'],
			'orders'	=> $final['orders'],
			'status'	=> $final['siqo_table_status'],
			'prev_status'=> $meta->status,
			'end_time'	=> $final['end_time'],
			'totals'	=> $final['totals'],
			'info'		=> $final['info'],
			'receipt'	=> $final['receipt'],
			'other'		=> $final['other'],
			'last_update'=> wp_date('Y-m-d H:i:s')
		), array( 'id'	=> $id ) );

  return $result;
}

function SIQOP_get_all_active_tables( $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  // $date = wp_date('Y-m-d H:i:s');
	$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status < 10", $table ), $type );
	return $result;
}

function SIQOP_get_table_by_table_number_status_negative( $table_number, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_number = %s AND status > 0", $table, $table_number ), $type );
	return $result;
}
function SIQOP_get_table_by_table_number_status( $table_number, $status = 0, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_number = %s AND status > %d", $table, $table_number, $status ), $type );
	return $result;
}

function SIQOP_get_table_by_table_number_status_last( $table_number, $status = 0, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_number = %s AND status > %d ORDER BY last_update DESC", $table, $table_number, $status ), $type );
	return $result;
}

function SIQOP_get_table_by_table_number_end_time( $table_number, $date = '', $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  	$now = new DateTime();
	$date = ( $date == '' ) ? $now->format('Y-m-d H:i:s') : '';
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_number = %s AND end_time < %s", $table, $table_number, $date ), $type );
	return $result;
}

function SIQOP_update_table_from_id( $id, $args ){
  // global $wpdb;
  	// $table = $wpdb->prefix . "siqo_tables";
  	$meta = SIQOP_get_table_by_id( $id, 'OBJECT' );
  if ( $meta != null ) {
    $result = SIQOP_update_table( $id, $args );
  } else {
    $result = SIQOP_insert_table( $args );
  }
  return $result;
}
function SIQOP_update_table_from_table_id( $table_id, $args, $replace_orders = false ){
  // global $wpdb;
  	// $table = $wpdb->prefix . "siqo_tables";
  	$meta = SIQOP_get_table_by_table_id_last( $table_id, 'OBJECT' );

  if ( $meta != null && $meta->status != 10 ) {
    $result = SIQOP_update_table( $meta->id, $args, $replace_orders );
  } else {
    $result = SIQOP_insert_table( $args );
  }
  return $result;
}
function SIQOP_update_table_from_table_number_status( $table_number, $args = array() ){
  // global $wpdb;
  	// $table = $wpdb->prefix . "siqo_tables";
  	$meta = SIQOP_get_table_by_table_number_status( $table_number );
  	// FOP_debug($meta, 'meta');
  if ( $meta != null ) {
    $result = SIQOP_update_table( $meta->id, $args );
  } else {
    $result = SIQOP_insert_table( $args );
  }
  return $result;
}
function SIQOP_delete_table_by_id( $id ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  $result = $wpdb->delete( $table, array( 'id'=>$id ) );
  return $result;
}








// add_action( 'init', 'SIQO_get_category_by_slug', 999, 1 );
function SIQO_get_category_by_slug( $value = '' ){
    $term = get_term_by( 'slug', $value, 'category' );
    return $term;
}


function SIQO_product_to_table_loop( $object ){
	foreach ( $object as $key => $value) {
		?>
	<thead siqotargetcat="<?php echo esc_attr($key);?>" onclick="jQuery(this).parent().find(`tbody tr[focategories='<?php echo esc_attr($key);?>']`).toggle();">
		<tr class="title" style="font-weight:800;font-size:30px;">
			<th style="background-color: var(--siqo-main-color)!important;">
			<?php echo esc_attr($key); ?>
			<span class="dashicons dashicons-arrow-down"></span>
			</th>
		</tr>
	</thead>

	<?php
		foreach( $value as $product ){
			SIQO_product_to_table( $product, $key );
		}
	}
}
function SIQO_product_to_table( $product, $cat_slug ){
	$id = $product->get_id();
	if ( !$product->get_parent_id() ) {
		$category = $product->get_category_ids()[0];
	} else {
		$category = wc_get_product( $product->get_parent_id() )->get_category_ids()[0];
	}
	// SIQO_debug( $cat_slug );
	$visualize = SIQO_get_flash_orders_visualize_settings();
?>
	<tr focategories="<?php echo esc_attr($cat_slug); ?>" focatid="<?php echo esc_attr($category); ?>" id="<?php echo 'prod-'.esc_attr($id); ?>" style="transition:var(--siqo-main-tran);position:relative;" foid="<?php echo esc_attr($id); ?>" foname="<?php echo esc_attr($product->get_name()); ?>">
		<?php if ( isset($visualize['id']) && $visualize['id'] ) { ?>

			<td style="width:50px;"><?php echo esc_attr($product->get_id()); ?></td>
		<?php } ?>
		<?php if ( isset($visualize['name']) && $visualize['name'] ) { ?>
			<td class="target_search"> <?php echo esc_attr($product->get_name()); ?></td>
		<?php } ?>
		<?php if ( isset($visualize['image']) && $visualize['image'] ) { ?>
			<td class="siqopermalink relative">

					<?php
					echo wp_kses_post($product->get_image());
					$ware_color = ($product->get_stock_quantity()>0||$product->get_stock_quantity()=='') ? 'green' : 'red';
					$ware_text = ($product->get_stock_quantity() != '' ) ? esc_html__('Non Disponibile' , 'studio-immens-quick-order') : esc_html__('Disponibile' , 'studio-immens-quick-order');
					?>
				<!-- </a> -->
				<div class="fowarehouse">
					<img color="<?php echo esc_attr($ware_color);?>" src="<?php echo esc_url( plugins_url( 'includes/img/sphere4.webp', SIQO_PLUGIN_FILE ) )?>"/>
					<p><?php echo esc_attr($ware_text);?></p>
				</div>
			</td>
		<?php } ?>
		<?php if ( isset($visualize['warehouse']) && $visualize['warehouse'] ) { ?>
		<td class="foquantity"> <?php echo ($product->get_stock_quantity() != '' ) ? esc_attr($product->get_stock_quantity()) : esc_html__('Disponibile' , 'studio-immens-quick-order'); ?> </td>
		<?php } ?>
		<?php if ( isset($visualize['note']) && $visualize['note'] ) { ?>
		<td class="fonote"> <textarea type="text" name="<?php echo '[note]['.esc_attr($id).']';?>" style="width:calc(100% - 20px);" placeholder="<?php echo esc_html__('Note del prodotto...' , 'studio-immens-quick-order'); ?>"></textarea>
			<button onclick="jQuery(this).next().slideToggle();return false;"> <?php echo esc_html__( 'MODIFICA INGREDIENTI', 'studio-immens-quick-order' ); ?> </button>
			<div id="FOeditIngTab" class="FOEditProdTab" style="display:none;overflow:auto;">
				<div onclick="jQuery(this).parent().slideToggle()" class="FOCloseProdTab"><?php echo esc_html__('CHIUDI','studio-immens-quick-order');?></div>
				<?php
				$Ingredienti = get_the_terms($product->get_id(), 'siqo_ingredients');
				if ( $Ingredienti != null ) {
					foreach( $Ingredienti as $key => $value ){ ?>
						<div class="FOIngredProdTab" modified="native">
							<input type="checkbox" name="[Ingredienti][<?php echo esc_attr($product->get_id());?>][<?php echo esc_attr($value->term_id);?>]" value="<?php echo esc_attr($value->name);?>" checked="checked" onclick="FOcheckIng(this)" nat-ing="<?php echo count($Ingredienti);?>" modified="native"><?php echo esc_attr($value->name); ?>
						</div>
					<?php }
				}
				?>
				<input type="search" name="[searched]" class="FOSearchIngred" onkeyup="FOSearchIngred(this)" placeholder="<?php echo esc_html__( 'Cerca ingredienti da aggiungere...', 'studio-immens-quick-order' ); ?>">
				<div class="FOIngredProdSec">
					<?php
						$terms = get_terms( array('taxonomy'=>'siqo_ingredients','hide_empty'=>false) );
						foreach ($terms as $k => $v) {
							// if ( in_array( $v, $Ingredienti, true ) ) { continue; }
							 ?>
								<div id="FOselSearchIng" class="FOIngredProdTab" style="display:none;">
									<input type="checkbox" name="[Ingredienti][<?php echo esc_attr($product->get_id());?>][<?php echo  esc_attr($v->term_id);?>]" value="<?php echo esc_attr($v->name);?>" onclick="FOaddIng(this)" modified="modified"><?php echo esc_attr($v->name); ?>
								</div>
							<?php
						}
					?>
				</div>
			</div>
		</td>
		<?php } ?>
		<?php if ( isset($visualize['actions']) && $visualize['actions'] ) { ?>
		<td class="foquantity"><div class="prod_input">
				<div class="spinner-button" onclick="jQuery(this).next().val(parseInt(jQuery(this).next().val()) - 1)">-</div>
				<input class="prod_input" type="number"name="[<?php echo esc_attr($id);?>]"value="1"min="1"style="width:30px;">
				<div class="spinner-button" onclick="jQuery(this).prev().val(parseInt(jQuery(this).prev().val()) + 1)">+</div>
			</div>
			<button class="FOAddBut" onclick="SIQO_Add_Item_to_Order(this);return false;"> <?php echo esc_html__( 'AGGIUNGI', 'studio-immens-quick-order' ); ?> </button>
		</td>
		<?php } ?>

	</tr>

	<?php
}
function SIQO_products_for_div_loop( $object ){
	foreach ( $object as $key => $value) {
		$term = get_term_by('name', $key, 'product_cat');
		$term_meta = (SIQOcheck($term))? get_term_meta($term->term_id):false;
		$image = ( isset($term_meta['thumbnail_id']) )? wp_get_attachment_image_src($term_meta['thumbnail_id'][0] ):array('0');
		$image_url = ( ! empty( $image[0] ) && '0' !== (string) $image[0] ) ? $image[0] : '';
		$cat_id = 'cat-' . sanitize_title( $key );
		?>
	<section class="siqo-menu-section" id="<?php echo esc_attr( $cat_id ); ?>" data-category="<?php echo esc_attr( $key ); ?>">
		<header class="siqo-menu-section-head">
			<?php if ( $image_url ) { ?>
				<img class="siqo-menu-section-thumb" src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
			<?php } ?>
			<div class="siqo-menu-section-title">
				<p class="siqo-eyebrow"><?php esc_html_e( 'Categoria', 'studio-immens-quick-order' ); ?></p>
				<h2><?php echo esc_html( $key ); ?></h2>
			</div>
			<span class="siqo-menu-count"><?php echo esc_html( count( $value ) ); ?></span>
		</header>

		<div class="siqo-menu-grid">
			<?php
			foreach( $value as $product ){
				SIQO_product_to_div_loop( $product, $key );
			}
			?>
		</div>
	</section>
	<?php
	}
}
function SIQO_product_to_div_loop( $product, $cat_slug = 'search' ){
	$id = $product->get_id();
	$role = SIQO_access_autorization();
	if ( !$product->get_parent_id() ) {
		$category = $product->get_category_ids()[0];
	} else {
		$category = wc_get_product( $product->get_parent_id() )->get_category_ids()[0];
	}
	$ware_color = ($product->get_stock_quantity()>0||$product->get_stock_quantity()=='') ? 'green' : 'red';
	$ware_text = ($product->get_stock_quantity()>0||$product->get_stock_quantity()=='') ? esc_html__('Disponibile' , 'studio-immens-quick-order') : esc_html__('Non Disponibile' , 'studio-immens-quick-order');
	$fo_ware = ($product->get_stock_quantity()>0||$product->get_stock_quantity()=='') ? '1' : '0';

	$variations = $product->get_children();
// SIQOcheck($variations);
	// SIQO_debug( SIQOcheck($variations) );
	if ( SIQOcheck($variations) ) {
		foreach ($variations as $key => $value) {
			$prod_vari = wc_get_product( $value );
		}
	}
	$vari_arr = ( SIQOcheck($variations) ) ? wp_json_encode($variations) : '';
	$Ingredienti = get_the_terms($product->get_id(), 'siqo_ingredients');
	$count_ing = (is_array($Ingredienti))? count($Ingredienti): 0;

	$meta_data = get_post_meta( $product->get_id() );
	$Sticker = get_the_terms($product->get_id(), 'siqo_stickers');
	$sticker = (isset($meta_data['siqo_stickers']) && $meta_data['siqo_stickers'] != null ) ? $meta_data['siqo_stickers'] : array();

	$sticker = array_merge( (array)$Sticker, (array)$sticker );
	// $sticker = ( $Sticker ) ? $Sticker : $sticker;
	$t_array = array();
	foreach ((array)get_the_terms( $id, 'product_cat' ) as $key => $value) {
		if (!SIQOcheck($value)) { continue; }
		$t_array[$key] = $value->term_id;
	} $fo_tax_cat = wp_json_encode( $t_array );

	$t_array = array();
	foreach ((array)get_the_terms( $id, 'product_tag' ) as $key => $value) {
		if (!SIQOcheck($value)) { continue; }
		$t_array[$key] = $value->term_id;
	} $fo_tax_tag = wp_json_encode( $t_array );

	$t_array = array();
	foreach ((array)get_the_terms( $id, 'siqo_ingredients' ) as $key => $value) {
		if (!SIQOcheck($value)) { continue; }
		$t_array[$key] = $value->term_id;
	} $fo_tax_ing = wp_json_encode( $t_array );

	$t_array = array();
	foreach ((array)get_the_terms( $id, 'siqo_allergens' ) as $key => $value) {
		if (!SIQOcheck($value)) { continue; }
		$t_array[$key] = $value->term_id;
	} $fo_tax_allerg = wp_json_encode( $t_array );

	//$fo_gallery = wp_json_encode( $product->get_gallery_image_ids() );

$fo_gallery = array();
	foreach ($product->get_gallery_image_ids() as $key => $value) {
		$fo_gallery[$key] = wp_get_attachment_url( $value );
	}
	$fo_gallery = wp_json_encode( $fo_gallery );

	if ($cat_slug == 'search') {
		$fo_menu_prod = 'fo_search_prod';
	} else{
		$fo_menu_prod = 'fo_menu_prod';
	}

?>
	<div class="foProdCard" foware="<?php echo esc_attr($fo_ware);?>" focategories="<?php echo esc_attr($cat_slug);?>" focatid="<?php echo esc_attr($category);?>" id="<?php echo 'prod-'.esc_attr($id);?>" style="transition:var(--siqo-main-tran);position:relative;" foid="<?php echo esc_attr($id);?>" foname="<?php echo esc_attr($product->get_name());?>" fovariations="<?php echo esc_attr($vari_arr);?>" nat-ing="<?php echo esc_attr($count_ing);?>" fo_gallery="<?php echo esc_attr($fo_gallery);?>" fo_description="<?php echo esc_attr($product->get_description());?>" fo_tot="<?php echo esc_attr(SIQO_price($product->get_price()));?>" fo_tax_cat="<?php echo esc_attr($fo_tax_cat);?>" fo_tax_tag="<?php echo esc_attr($fo_tax_tag);?>" fo_tax_ing="<?php echo esc_attr($fo_tax_ing);?>" fo_tax_allerg="<?php echo esc_attr($fo_tax_allerg);?>" fo_menu_prod="<?php echo esc_attr($fo_menu_prod);?>" foindex="0">

		<input fo_tab_target="id" type="hidden" name="[id][<?php echo esc_attr($id);?>]" value="1">
		<input fo_tab_target="qty" type="hidden" name="[qty][<?php echo esc_attr($id);?>]" value="1">
		<input fo_tab_target="price" type="hidden" name="[price][<?php echo esc_attr($id);?>]" value="<?php echo esc_attr($product->get_price());?>" regularprice="<?php echo esc_attr($product->get_price());?>" price_added="">
		<input fo_tab_target="note" type="hidden" name="[note][<?php echo esc_attr($id);?>]" value="">


		<div class="foProdCardHead">
			<?php echo wp_kses( $product->get_image(array( 'auto', 'auto' ), array( 'foprod'=>'foprod', 'onclick'=>'SIQO_Advanced_Prod_Card(this)', 'class'=>'SIQO_prod_img_menu' )), siqo_allowed_html() ); ?>
			<div class="foname target_search" onclick="SIQO_Advanced_Prod_Card(this)"><?php echo esc_attr($product->get_name());?></div>
			<?php
				// SIQO_get_product_temperature_options($product);
			?>
			<div class="foheart" onclick="SIQO_add_prod_to_favorites(jQuery(this).closest('.foProdCard'));">
				<span class="dashicons dashicons-heart"></span>
			</div>

<!-- <div class="fovariantag_container" onclick="SIQO_Advanced_Prod_Card(this)">
</div> -->

<?php if ( !empty( $product->get_children() ) ) { ?>
	<div class="fovariantcont" style="display:none;">
		<?php esc_html_e('Seleziona la variante: ','studio-immens-quick-order');

		$product_attr = $product->get_attributes();
		foreach ($product_attr as $k => $v) {
			$attr_key = $k;
			$attr_options = array();
			if (siqo_str_contains($k, 'pa_')) {
				$k = str_replace('pa_', '', $k);
				$attr_term = 'pa_'.$k;
				if ( taxonomy_exists( $attr_key ) ) {
					$terms = wc_get_product_terms( $product->get_id(), $attr_key, array( 'fields' => 'all' ) );
					foreach ( $terms as $term ) {
						$attr_options[] = array(
							'value' => $term->slug,
							'label' => $term->name,
							'price_to_add' => siqo_get_string_between( $term->description, '[', ']' ),
						);
					}
				}
			} else{
				$attr = explode( ' | ', $product->get_attribute( $k ) );
				foreach ($attr as $opt) {
					$attr_options[] = array(
						'value' => $opt,
						'label' => $opt,
						'price_to_add' => '0.0',
					);
				}
			}
			?>
			<div class="fovariant" fovariant="<?php echo esc_attr($attr_key);?>">
				<strong><?php echo esc_attr($k);?></strong>

				<select class="fo_button_thin" foadvariant="<?php echo esc_attr($attr_key);?>" fo_prod_id="<?php echo esc_attr($product->get_id());?>" fo_price="<?php echo esc_attr($product->get_price());?>" fo_regular_price="<?php echo esc_attr($product->get_price());?>" onchange="SIQO_variant_set(this);" name="[Variante][<?php echo esc_attr($product->get_id());?>][<?php echo esc_attr($attr_key);?>]" value="">

						<option value=""><?php esc_html_e(' - Seleziona - ','studio-immens-quick-order');?></option>
					<?php foreach ($attr_options as $opt) {
						$price_to_add = ( $opt['price_to_add'] !== '' && $opt['price_to_add'] !== false ) ? $opt['price_to_add'] : '0.0';
						?>
						<option value="<?php echo esc_attr($opt['value']);?>" fovariant="<?php echo esc_attr($attr_key);?>" fo_price_to_add="<?php echo esc_attr($price_to_add);?>"><?php echo esc_html($opt['label']);?></option>
					<?php } ?>
				</select>
			</div>
		<?php } ?>
	</div>
<?php } ?>

<div class="fosticker">
	<?php foreach ($sticker as $key => $value) { ?>
		<?php if ( $value == 'fo-vegan' ) { ?>
			<div class="siqo-sticker-icon" title="<?php esc_attr_e( 'Vegan', 'studio-immens-quick-order' ); ?>">
				<span class="dashicons dashicons-carrot" aria-hidden="true"></span>
			</div>
		<?php } ?>
		<?php if ( $value == 'fo-veget' ) { ?>
			<div class="siqo-sticker-icon" title="<?php esc_attr_e( 'Vegetarian', 'studio-immens-quick-order' ); ?>">
				<span class="dashicons dashicons-leaf" aria-hidden="true"></span>
			</div>
		<?php } ?>
		<?php if ( $value == 'fo-bio' ) { ?>
			<div class="siqo-sticker-icon" title="<?php esc_attr_e( 'Organic', 'studio-immens-quick-order' ); ?>">
				<span class="dashicons dashicons-heart" aria-hidden="true"></span>
			</div>
		<?php } ?>
		<?php if ( $value == 'fo-spicy' ) { ?>
			<div class="siqo-sticker-icon" title="<?php esc_attr_e( 'Spicy', 'studio-immens-quick-order' ); ?>">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			</div>
		<?php } ?>

		<?php if ( $value!='fo-vegan' && $value!='fo-veget' && $value!='fo-bio' && $value!='fo-spicy' ) {
			if ( !is_object($value) ) { continue; }
			$sticker_img = get_term_meta($value->term_id, 'taxonomy_image');
			$stick_disp  = ( ! empty( $sticker_img[0] ) ) ? 'none' : '';
			?>
			<div>
				<p style="display:<?php echo esc_attr($stick_disp);?>" onclick="jQuery(this).toggle()" title="<?php echo esc_attr($value->description);?>"><?php echo esc_attr($value->name);?></p>
				<?php if ( ! empty( $sticker_img[0] ) ) : ?>
					<img width="50" height="50" src="<?php echo esc_url( $sticker_img[0] ); ?>" alt="<?php echo esc_attr( $value->name ); ?>" onclick="jQuery(this).prev().toggle()">
				<?php else : ?>
					<span class="siqo-sticker-icon dashicons dashicons-tag" aria-hidden="true" title="<?php echo esc_attr( $value->name ); ?>"></span>
				<?php endif; ?>
			</div>
		<?php } ?>
	<?php } ?>
</div>
<!-- clip-path: polygon(0% 0%, 75% 0%, 100% 50%, 75% 100%, 0% 100%); -->

			<div class="fowarehouse">
				<span class="siqo-warehouse-icon dashicons dashicons-location" style="color:<?php echo esc_attr($ware_color);?>;" aria-hidden="true"></span>
				<p><?php echo esc_attr($ware_text);?></p>
			</div>

			<?php if (SIQOcheck($variations)) {?>
				<div class="foadd" onclick="SIQO_Advanced_Prod_Card(this)">
					<span class="dashicons dashicons-plus"></span>
					<input type="hidden" name="[<?php echo esc_attr($id);?>]" value="1">
				</div>
			<?php } else{ ?>
				<div class="foadd" onclick="SIQO_ajax_selectVarBefore_Add_Item_to_Order(this);">
					<span class="dashicons dashicons-plus"></span>
					<input type="hidden" name="[<?php echo esc_attr($id);?>]" value="1">
				</div>
			<?php } ?>
		</div>

		<div class="foProdCardFoot">
			<span class="siqo-prod-price"><?php echo wp_kses_post( SIQO_price( $product->get_price() ) ); ?></span>
		</div>
	</div>
	<?php
}

function SIQO_Advanced_Prod_Card(){
	/*
	<!-- <input type="search" name="searched" class="FOSearchIngred fo_ajax_search_ing" onkeyup="FOSearchIngred(this)" placeholder="<?php echo esc_html__( 'Cerca ingredienti da aggiungere...', 'studio-immens-quick-order' ); ?>">
<div class="FOIngredProdSec fo_ajax_ing_sect">
	<?php
		$terms = get_terms( array('taxonomy'=>'siqo_ingredients','hide_empty'=>false) );
		// SIQO_debug($terms);
		foreach ($terms as $k => $v) {
			$tax_img = get_term_meta($v->term_id, 'taxonomy_image');
			 ?>
				<div id="FOselSearchIng" class="FOIngredProdTab" style="display:none;">
				<?php if ( $tax_img != null && SIQOcheck($tax_img[0]) ) { ?>
					<img class="fotax_image" width="30" height="30" src="<?php echo esc_attr($tax_img[0]);?>">
				<?php } ?>
					<p class="fo_ing_text"><?php echo esc_attr($v->name); ?></p>
					<input type="checkbox" name="[Ingredienti][<?php echo esc_attr($product->get_id());?>][<?php echo  esc_attr($v->term_id);?>]" value="<?php echo esc_attr($v->name);?>" onclick="FOaddIng(this);FOcheckCardModified(this)" modified="modified">
				</div>
			<?php
		}
	?>
</div> -->
*/
	?>

	<div class="Advanced_Card" style="display:none;">
	<div class="Advanced_Card_Close" onclick="SIQO_Advanced_Prod_Card_hide(jQuery(this))" style="padding: 5px;">
		<span class="dashicons dashicons-no" style="font-size:40px;display:contents;"></span>
	</div>
	<div class="Advanced_Card_header">
		<div class="fowarehouse fixware" foware="" style="display:contents;">
			<span class="siqo-warehouse-icon dashicons dashicons-location" aria-hidden="true"></span><?php  ?>
		</div>
		<p class="AC_ID" style="width:80px;"></p>
		<p class="AC_Name" style="max-width:calc(100% - 250px);"></p>

		<div class="Advanced_Card_Close fo_close"onclick="SIQO_Advanced_Prod_Card_hide(jQuery(this).parent())"><?php esc_html_e('CHIUDI','studio-immens-quick-order');?></div>
	</div>

	<div class="Advanced_Card_gallery">
		<p class="FO_no_images" style="display:none;"><?php esc_html_e( 'Nessuna immagine di galleria', 'studio-immens-quick-order' );?></p>
	</div>

	<div class="Advanced_Card_column">

		<div class="focol AC_description"> </div>

		<div class="focol AC_tax">

		<?php
			$product_cat = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false,));
			if ( !empty( $product_cat ) ) {
			?><span class="fo_adv_tax AC_tax_cat"><strong><?php esc_html_e('Categorie:','studio-immens-quick-order');?></strong></span>
			<?php
				echo wp_kses( SIQO_get_tax_cloud( $product_cat ), siqo_allowed_html() );
			}
			$product_tag = get_terms(array('taxonomy'=>'product_tag','hide_empty'=>false,));
			if ( !empty( $product_tag ) ) {
				?><span class="fo_adv_tax AC_tax_tag"><strong><?php esc_html_e('Tag:','studio-immens-quick-order');?></strong></span>
			<?php
				echo wp_kses( SIQO_get_tax_cloud( $product_tag ), siqo_allowed_html() );
			}
			$Ingredienti = get_terms(array('taxonomy'=>'siqo_ingredients','hide_empty'=>false,));
			if ( !empty( $Ingredienti ) ) {
				?><span class="fo_adv_tax AC_tax_ing"><strong><?php esc_html_e('Ingredienti:','studio-immens-quick-order');?></strong></span>
			<?php
				echo wp_kses( SIQO_get_tax_cloud( $Ingredienti ), siqo_allowed_html() );
			}
			$Allergeni = get_terms(array('taxonomy'=>'siqo_allergens','hide_empty'=>false,));
			if ( !empty( $Allergeni ) ) {
				?><span class="fo_adv_tax AC_tax_allerg"><strong><?php esc_html_e('Allergeni:','studio-immens-quick-order');?></strong></span>
			<?php
				echo wp_kses( SIQO_get_tax_cloud( $Allergeni ), siqo_allowed_html() );
			}
		?>
		</div>

	</div>

		<div class="Advanced_Card_footer">

			<input type="hidden" name="AC_foindex" class="AC_foindex">

			<p class="fo_adv_tot AC_tot"></p>

			<div class="AC_Variant">

			</div>

			<div class="foadd_fix foscale" style="scale:0.8;bottom:25px;right:-10px;" onclick="SIQO_ajax_selectVarAfter_Add_Item_to_Order(this);SIQO_Advanced_Prod_Card_hide();"> +
			</div>

		</div>
	</div>
	<div class="Advanced_Card_background" style="display:none;"></div>

	<?php
}


function SIQO_list_view_selector(){
	?>
	<div class="focathead SIQO_list_view_selector">
		<select onchange="jQuery('.foProdCard').attr('foview',jQuery(this).val())" foview="normal">
			<option value="list">
				<span class="dashicons dashicons-list-view"> List View </span>
			</option>
			<option value="small">
				<span class="dashicons dashicons-grid-view"> Small View </span>
			</option>
			<option value="normal" selected>
				<span class="dashicons dashicons-format-image"> Normal View </span>
			</option>
		</select>
	</div>
	<?php
}






function SIQO_get_tax_cloud( $terms = array() ){
	$return = '';
	foreach ($terms as $key => $value) {
		if ($value == null|| !SIQOcheck($value)) { continue; }
		$tax_img = get_term_meta($value->term_id, 'taxonomy_image');
		$return .= '<div class="FOAdvIngredProdTab" fo_tax_id="'.esc_attr($value->term_id).'" style="display:none;">';
		$return .= '<p>'.esc_attr($value->name).'</p>';
		if ( SIQOcheck($tax_img) && isset($tax_img[0]) && $tax_img[0] != '' ) {
			$return .= '<img class="fotax_image"width="30"height="30"src="'.esc_attr($tax_img[0]).'">';
		}
		$return .= '</div>';
	}
	return $return;
}

function SIQO_get_product_temperature_options( $product ){
	if ( SIQO_get_meta('product_temperature_tax') == 'yes') {
		$Temperature = get_the_terms($product->get_id(), 'siqo_temperatures');
		// $terms = get_terms( array('taxonomy'=>'siqo_temperatures','hide_empty'=>false) );
		if ( $Temperature != null ) {
	?>
		<div class="fothermo foboxclick">
			<img color="" width="50" height="50" class="FOthermometer" src="<?php echo esc_url( plugins_url( 'includes/img/thermometer2.webp', SIQO_PLUGIN_FILE ) )?>" onclick="jQuery(this).parent().parent().find('.fotemp_card').slideToggle();"/><?php  ?>
				<img class="fotax_image_temp" width="30" height="30" src="" onclick="jQuery(this).parent().parent().find('.fotemp_card').slideToggle();" style="display:none;"><?php  ?>
		</div>

		<div id="FOeditIngTab" class="FOEditProdTab fo_ajax_edit_tab fotemp_card" style="display:none;overflow:auto;">
			<div onclick="jQuery(this).parent().slideToggle()" class="FOCloseProdTab fo_ajax_edit_tab_close"><?php echo esc_html__('CHIUDI','studio-immens-quick-order');?></div>
	<!-- <div class="fobox FOeditTemp FOIngredProdTab" style="display:none;"> -->
			<p><?php esc_html_e( 'Che temperatura gradisci? ', 'studio-immens-quick-order' ); ?></p>
			<input type="hidden" name="[Temperature][<?php echo esc_attr($product->get_id());?>]">
			<select onchange="SIQO_show_temp(this)">
				<option color="" value=""><?php esc_html_e( '-scegli-', 'studio-immens-quick-order' ); ?></option>
			<?php foreach ($Temperature as $k => $v) { ?>
				<?php $tax_img = get_term_meta($v->term_id, 'taxonomy_image');
				$color = 'white';
				$color = ( siqo_str_contains( substr($tax_img[0], strrpos($tax_img[0], '/', 0) ), 'hot' ) )? 'red': $color;
				$color = ( siqo_str_contains( substr($tax_img[0], strrpos($tax_img[0], '/', 0) ), 'cold' ) )? 'blue': $color;
				?>
				<option img="<?php echo esc_attr($tax_img[0]);?>" color="<?php echo esc_attr($color);?>" value="<?php echo esc_attr($v->name);?>">
					<?php echo esc_attr($v->name);?>
				</option>
			<?php } ?>
			</select>
		</div>
<?php
		}
	}
}
function SIQO_get_products_for_loop( $args = array() ){
	$object = array();
	// $final_products = array();
	// $products = array();
	// $default_args = array(
	//     'siqo_table_status'            => 'publish', // array( 'draft', 'pending', 'private', 'publish' )
	//     // 'category'          => get_term($category_id, 'product_cat')->slug,
	//     'tag'               => array(),
	//     'limit'             => -1,  // -1 for unlimited
	//     'offset'            => null,
	//     'page'              => 1,
	//     'include'           => array(),
	//     'exclude'           => array(),
	//     'orderby'           => 'menu_order',
	//     'taxonomy'			=> 'product_cat',
	//     'order'             => 'DESC',
	//     'return'            => 'objects',
	//     'paginate'          => false,
	//     'shipping_class'    => array()
	// );
	// $args = wp_parse_args( $args, $default_args );
// $products = wc_get_products( $args );
	// $cat_args = array(
	//    'number'     => false,
	//    'include' => 'all',
	//    'hide_empty' => true, //can be 1, '1' too
	//    'orderby'   => 'title',
	//    'order'     => 'ASC',
	// );
	// $products_categories = get_terms( 'product_cat', $cat_args );

	$products_categories = new WP_Term_Query( array(
		'taxonomy'	=> 'product_cat',
		'include' 	=> array(),
		'hide_empty' => true,
		'orderby'   => 'title',
		'order'     => 'ASC',
	) );
	if ( count($products_categories->terms) > 0 ){
		foreach ( $products_categories->terms as $products_category ) {
			$temp_args = array(
				'category' 			=> $products_category->slug,
				'orderby' 			=> 'title',
				'order'             => 'DESC',
				'limit'             => 200,
			    'offset'            => null,
			    'page'              => 1,
			    'return'            => 'objects',
	    		'paginate'          => false,
			);
			$args = wp_parse_args( $temp_args, $args );
			$temp_products = wc_get_products( $args );
			if ( count($temp_products) > 0 ) {
				$object[$products_category->slug] = $temp_products;
			}
		}
	}
	return $object;
}

function SIQO_get_products_macro_for_loop( $args = array() ){
	$object = array();
	$final_products = array();
	$products = array();
	$default_args = array(
	    // 'category'          => get_term($category_id, 'product_cat')->slug,
	    'tag'               => array(),
	    'limit'             => 200,
	    'offset'            => null,
	    'page'              => 1,
	    'include'           => array(),
	    // 'exclude'           => array(),
	    'orderby'           => 'menu_order',
	    'taxonomy'			=> 'product_cat',
	    'order'             => 'DESC',
	    'return'            => 'objects',
	    'paginate'          => false,
	    'shipping_class'    => array()
	);
	$args = wp_parse_args( $args, $default_args );
// $products = wc_get_products( $args );
	// $macro_cat_args = array(
	//    'number'     => false,
	//    'include' => 'all',
	//    'hide_empty' => true, //can be 1, '1' too
	//    'orderby'   => 'title',
	//    'order'     => 'ASC',
	// );
	// $products_macro_categories = get_terms( 'siqo_macro_categories', $macro_cat_args );

	$products_macro_categories = new WP_Term_Query( array(
		'taxonomy'	=> 'siqo_macro_categories',
		'include' 	=> array(),
		'hide_empty' => true, //can be 1, '1' too
		'orderby'   => 'title',
		'order'     => 'ASC',
	) );

	// $cat_args = array(
	//    'number'     => false,
	//    'include' => 'all',
	//    'hide_empty' => true, //can be 1, '1' too
	//    'orderby'   => 'title',
	//    'order'     => 'ASC',
	// );
	// $products_categories = get_terms( 'product_cat', $cat_args );

	$products_categories = new WP_Term_Query( array(
		'taxonomy'	=> 'product_cat',
		'include' 	=> array(),
		'hide_empty' => true, //can be 1, '1' too
		'orderby'   => 'title',
		'order'     => 'ASC',
	) );
// SIQO_debug($products_categories);
	if ( count($products_categories->terms) > 0 ){
		foreach ($products_macro_categories->terms as $key => $value) {
			foreach ( $products_categories->terms as $products_category ) {
				$temp_args = array(
					'category' 			=> $products_category->slug,
					'orderby' 			=> 'title',
					'order'             => 'DESC',
					'limit'             => 200,
				    'offset'            => null,
				    'page'              => 1,
				    'return'            => 'objects',
		    		'paginate'          => false,
				);
				$temp_products = wc_get_products( $temp_args );
				if ( count($temp_products) > 0 ) {
					$object[$value->slug][$products_category->slug] = $temp_products;
				}
			}
		}
	}
	return $object;
}

function siqo_flash_orders_ordination() {
	//global $woocommerce, $post;
	// $flash_order_front_name = ( SIQO_get_meta('siqo_front_name' ) != '' ) ? SIQO_get_meta('siqo_front_name') : esc_html__('TAVOLO','studio-immens-quick-order');
	// $_POST = SIQO_recursive_sanitize_text_field($_POST);
	if ( !isset($_POST['_siqononce_front_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_front_order'])), 'SIQO_front_order' ) ) {
		return;
	}
	$user = wp_get_current_user();
	$order = new WC_Order();

	$table_name_cpt = ( isset($_POST['table_name_cpt']) ) ? sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) : esc_html__('Tavolo','studio-immens-quick-order');
	$address = array(
	    'address_1' => $table_name_cpt,
	    'address_2'  => (isset($_POST['table_name']))?sanitize_text_field(wp_unslash($_POST['table_name'])):'',
	    'company'  => (isset($_POST['table_surname']))?sanitize_text_field(wp_unslash($_POST['table_surname'])):''
	);
	$order->set_address( $address, 'shipping' );
	$addressBilling = array(
		'first_name' 	=> $user->user_firstname,
		'last_name' 	=> $user->user_lastname,
		'email'      	=> $user->user_email,
		'phone'      	=> get_user_meta( $user->ID, 'billing_phone', true ),
		// 'address_1'  	=> $user->get_billing_address_1(),
		// 'address_2'  	=> $user->get_billing_address_2(),
		// 'city'       	=> $user->get_billing_city(),
		// 'state'      	=> $user->get_billing_state(),
		// 'postcode'   	=> $user->get_billing_postcode(),
		// 'country'    	=> $user->get_billing_country()
	);
	$order->set_address( $addressBilling, 'billing' );
	// $products = array();
	$i = 0;
	// SIQO_debug( $_POST );
foreach ( siqo_posted_array( 'foindex' ) as $K => $E ) {

	$order->update_meta_data( '{'.$K.'}info-', wp_json_encode( $E ) );

	foreach ( $E as $pos => $item) {
		if ( is_numeric($pos) ) {
			$order->update_meta_data( '{'.$K.'}index-'.$pos, $item );
			$order->add_product( wc_get_product( $pos ), $item );
		} $i++;
	}
	if (isset($E['note'])) {
		$order->update_meta_data( '{'.$K.'}prod-'.sanitize_text_field(key($E['note'])).'_note-', sanitize_text_field( array_values( $E['note'] )[0] ) );
	}
	if (isset($E['siqo_ingredients'])) {
		foreach ( $E['siqo_ingredients'] as $index => $element) {
			foreach( $element as $ing_key => $ing_val ){
				$order->update_meta_data( '{'.$K.'}prod-'.$index.'_ingredient-'.$ing_key, $ing_key.' | '.$ing_val );
			}
		}
	}
	if (isset($E['Variante'])) {
		foreach ( $E['Variante'] as $index => $element) {
			foreach( $element as $var_key => $var_val ){
				$order->update_meta_data( '{'.$K.'}prod-'.$index.'_variant-'.$var_key, $var_key.' | '.$var_val );
			}
		}
	}
	if (isset($E['siqo_temperatures'])) {
		foreach ( $E['siqo_temperatures'] as $index => $element) {
			$order->update_meta_data( '{'.$K.'}prod-'.$index.'_temperature-'.$index, $index.' | '.$element );
		}
	}
}
	// foreach ($products as $k => $v) {
	  // $order->update_meta_data( 'Product-'.$k, $v );
	  // $order->add_product( wc_get_product( $k ), $v );
	// }

	if (isset($_POST['table_name_cpt'])) {
		$order->update_meta_data( 'Table_cpt', sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) );
	}
	if (isset($_POST['table_name'])) {
		$order->update_meta_data( 'Table', sanitize_text_field(wp_unslash($_POST['table_name'])) );
	}
	if (isset($_POST['order_note'])) {
		$order->update_meta_data( 'order_note', sanitize_text_field(wp_unslash($_POST['order_note'])) );
		$order->add_order_note( sanitize_text_field(wp_unslash($_POST['order_note'])) );
	}
	if (isset($_POST['table_surname'])) {
		$order->update_meta_data( 'table_surname', sanitize_text_field(wp_unslash($_POST['table_surname'])) );
	}

	$order->update_meta_data( 'delivery_type', 'table' );

	SIQO_update_meta( 'siqo_last_woocommerce_order', wp_json_encode( $order ) );
	// SIQO_update_meta( 'siqo_last_woocommerce_order_products', wp_json_encode( $products ) );

	$order->set_payment_method('cod');//set_prop( 'payment_method', 'cod' );


	$order->set_created_via( 'FO page' );

	$order->calculate_totals();

	$order->save();

	$table = $order->id;
	do_action( 'siqo_front_flash_order', $table );
}






function SIQO_submit_order_ajax() {

	if (!isset($_POST['_siqononce_front_order_ajax']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_siqononce_front_order_ajax'])), 'SIQO_front_order_ajax' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	} else{
		// $_POST['user_id'] = '';
		$_POST = SIQO_recursive_sanitize_text_field( wp_unslash( $_POST ) );
		$_POST['created_via'] = 'menu_ajax';
		$_POST['customer_id'] = get_current_user_id();
		$_POST['user_id'] = get_current_user_id();
	}


	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_POST is recursively sanitized immediately above.
	SIQO_flash_tab_order_ajax( $_POST, false, true );

	/*
	// $_POST = SIQO_recursive_sanitize_text_field($_POST);

	$user = wp_get_current_user();
	$order = new WC_Order();
	$table_name_cpt = ( isset($_POST['table_name_cpt']) ) ? sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) : esc_html__('Tavolo','studio-immens-quick-order');
	$address = array(
	    'address_1' => $table_name_cpt,
	    'address_2'  => (isset($_POST['table_name']))?sanitize_text_field(wp_unslash($_POST['table_name'])):'',
	    'company'  => (isset($_POST['table_surname']))?sanitize_text_field(wp_unslash($_POST['table_surname'])):''
	);
	$order->set_address( $address, 'shipping' );
	$addressBilling = array(
		'first_name' 	=> $user->user_firstname,
		'last_name' 	=> $user->user_lastname,
		'email'      	=> $user->user_email,
		'phone'      	=> get_user_meta( $user->ID, 'billing_phone', true ),
		// 'address_1'  	=> $user->get_billing_address_1(),
		// 'address_2'  	=> $user->get_billing_address_2(),
		// 'city'       	=> $user->get_billing_city(),
		// 'state'      	=> $user->get_billing_state(),
		// 'postcode'   	=> $user->get_billing_postcode(),
		// 'country'    	=> $user->get_billing_country()
	);
	$order->set_address( $addressBilling, 'billing' );
$info_string = array();

if (isset($_POST['foserialmap'])) {
	foreach ( siqo_posted_array( 'foserialmap' ) as $K => $E ) {
		foreach ( $E as $pos => $item) {
			if ( isset($item['id']) ) {
				$order->update_meta_data( '{'.substr($pos,1).'}index-'.sanitize_text_field(wp_unslash(key($item['id']))), sanitize_text_field(wp_unslash( array_values( $item['id'] )[0] )) );
				$product = wc_get_product(sanitize_text_field(wp_unslash(key($item['id']))));
				$order->add_product( $product, sanitize_text_field(wp_unslash( array_values( $item['id'] )[0] )) );
				$info_string[sanitize_text_field(wp_unslash(key($item['id'])))] = sanitize_text_field(wp_unslash(array_values($item['id'])[0]));
			}
			if (isset($item['note'])) {
				$order->update_meta_data( '{'.substr($pos,1).'}prod-'.sanitize_text_field(wp_unslash(key($item['note']))).'_note-', sanitize_text_field(wp_unslash( array_values( $item['note'] )[0] )) );
				$info_string['note'][sanitize_text_field(wp_unslash(key($item['note'])))] = sanitize_text_field(wp_unslash(array_values($item['note'])[0]));
			}
			if (isset($item['siqo_ingredients'])) {
				foreach ( $item['siqo_ingredients'] as $index => $element) {
					foreach( $element as $ing_key => $ing_val ){
						$info_string['siqo_ingredients'][$index][$ing_key] = $ing_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_ingredient-'.$ing_key, $ing_key.' | '.$ing_val );
					}
				}
			}
			if (isset($item['Variante'])) {
				foreach ( $item['Variante'] as $index => $element) {
					foreach( $element as $var_key => $var_val ){
						$info_string['Variante'][$index][$var_key] = $var_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_variant-'.$var_key, $var_key.' | '.$var_val );
					}
				}
			}
			if (isset($item['siqo_temperatures'])) {
				foreach ( $item['siqo_temperatures'] as $index => $element) {
					$info_string['siqo_temperatures'][$index] = $element;
					$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_temperature-'.$index, $index.' | '.$element );
				}
			}
			if (isset($item['searched'])) {
				$info_string['searched'] = sanitize_text_field(wp_unslash( array_values( $item['searched'] )[0] ));
			}
			$order->update_meta_data( '{'.substr($pos,1).'}info-', wp_json_encode($info_string) );
		}
	}
}
	if (isset($_POST['table_name_cpt'])) {
		$order->update_meta_data( 'Table_cpt', sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) );
	}

	if (isset($_POST['order_note'])) {
		$order->update_meta_data( 'order_note', sanitize_text_field(wp_unslash($_POST['order_note'])) );
		$order->add_order_note( sanitize_text_field(wp_unslash($_POST['order_note'])) );
	}
	if (isset($_POST['table_surname'])) {
		$order->update_meta_data( 'table_surname', sanitize_text_field(wp_unslash($_POST['table_surname'])) );
	}
	$order->update_meta_data( 'delivery_type', 'table' );
	// SIQO_update_meta( 'siqo_last_woocommerce_order', wp_json_encode( $order ) );
	// SIQO_update_meta( 'siqo_last_woocommerce_order_products', wp_json_encode( $products ) );
	$order->set_payment_method('cod');//set_prop( 'payment_method', 'cod' );
	$order->set_created_via( 'ajax' );


$order->calculate_totals();

	$order->save();

	$order_id = $order->get_id();
do_action( 'siqo_submit_order_ajax', $order_id );
	// $table = $order->id;
    wp_send_json(array(
    	// 'response' 	=> $response,
    	// 'time_exec' => $time,
		// 'loop_array' => $loop_array
	));
	die();
	*/
}
add_action('wp_ajax_siqo_submit_order_ajax', 'SIQO_submit_order_ajax');
add_action('wp_ajax_nopriv_siqo_submit_order_ajax', 'SIQO_submit_order_ajax');






function SIQO_flash_list_order_ajax(){
	if (!isset($_POST['_siqononce_flash_list_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_flash_list_order'])), 'SIQO_flash_list_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	$user = wp_get_current_user();
	$order = new WC_Order();
	$table_name_cpt = ( isset($_POST['table_name_cpt']) ) ? sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) : esc_html__('TAVOLO','studio-immens-quick-order');
	$address = array(
	    'address_1' => $table_name_cpt,
	    'address_2'  => (isset($_POST['table_name']))?sanitize_text_field(wp_unslash($_POST['table_name'])):'',
	    'company'  => (isset($_POST['table_surname']))?sanitize_text_field(wp_unslash($_POST['table_surname'])):''
	);
	$order->set_address( $address, 'shipping' );
	$addressBilling = array(
		'first_name' 	=> $user->user_firstname,
		'last_name' 	=> $user->user_lastname,
		'email'      	=> $user->user_email,
		'phone'      	=> get_user_meta( $user->ID, 'billing_phone', true ),
	);
	$order->set_address( $addressBilling, 'billing' );
	$info_string = array();
	$total_fee = 0.0;

if (isset($_POST['foserialmap'])) {
$order->update_meta_data( 'foserialmap', sanitize_text_field( wp_json_encode( siqo_posted_array( 'foserialmap' ) ) ) );
	foreach ( siqo_posted_array( 'foserialmap' ) as $K => $E ) {
		foreach ( $E as $pos => $item) {
			if ( isset($item['id']) ) {
				$id_values   = array_values( (array) $item['id'] );
				$product_key = sanitize_text_field( (string) key( $item['id'] ) );
				$product_id  = absint( $product_key );
				$product_qty = isset( $id_values[0] ) ? absint( $id_values[0] ) : 0;
				$product     = $product_id ? wc_get_product( $product_id ) : false;
				if ( ! $product || $product_qty < 1 ) {
					continue;
				}
				$order->update_meta_data( '{'.substr($pos,1).'}index-'.$product_key, $product_qty );
				$order->add_product( $product, $product_qty );
				$info_string[ $product_key ] = $product_qty;
			}
			if (isset($item['note'])) {
				$order->update_meta_data( '{'.substr($pos,1).'}prod-'.sanitize_text_field(wp_unslash(key($item['note']))).'_note-', sanitize_text_field(wp_unslash( array_values( $item['note'] )[0] )) );
				$info_string['note'][sanitize_text_field(wp_unslash(key($item['note'])))] = sanitize_text_field(wp_unslash(array_values($item['note'])[0]));
			}
			if (isset($item['siqo_ingredients'])) {
				foreach ( $item['siqo_ingredients'] as $index => $element) {
					if ( ! is_array( $element ) ) {
						continue;
					}
					foreach( $element as $ing_key => $ing_val ){
						$ing_key = sanitize_text_field( (string) $ing_key );
						$ing_val = is_scalar( $ing_val ) ? sanitize_text_field( (string) $ing_val ) : '';
						$info_string['siqo_ingredients'][$index][$ing_key] = $ing_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_ingredient-'.$ing_key, $ing_key.' | '.$ing_val );
					}
				}
			}
			if (isset($item['Variante'])) {
				foreach ( $item['Variante'] as $index => $element) {
					if ( ! is_array( $element ) ) {
						continue;
					}
					foreach( $element as $var_key => $var_val ){
						$var_key = sanitize_text_field( (string) $var_key );
						$var_val = is_scalar( $var_val ) ? sanitize_text_field( (string) $var_val ) : '';
						$info_string['Variante'][$index][$var_key] = $var_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_variant-'.$var_key, $var_key.' | '.$var_val );
					}
				}
			}
			if (isset($item['siqo_temperatures'])) {
				foreach ( $item['siqo_temperatures'] as $index => $element) {
					$element = is_scalar( $element ) ? sanitize_text_field( (string) $element ) : '';
					$info_string['siqo_temperatures'][$index] = $element;
					$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_temperature-'.$index, $index.' | '.$element );
				}
			}
			if (isset($item['searched'])) {
				$info_string['searched'] = sanitize_text_field(wp_unslash( array_values( $item['searched'] )[0] ));
			}
			if (isset($item['sconto'])) {
				// Lo sconto arriva dal form pubblico: va limitato al valore dei prodotti
				// dell'ordine, altrimenti il cliente può inviare un importo arbitrario
				// e rendere il totale negativo (frode economica).
				$siqo_sconto_raw = floatval( sanitize_text_field( wp_unslash( array_values( $item['sconto'] )[0] ) ) );
				if ( $siqo_sconto_raw < 0 ) {
					$siqo_sconto_raw = 0;
				}
				$siqo_order_cap = (float) $order->get_subtotal();
				$siqo_sconto     = min( $siqo_sconto_raw, max( 0, $siqo_order_cap ) );
				$order->update_meta_data( '{'.substr($pos,1).'}sconto-', wc_format_decimal( $siqo_sconto ) );
				$info_string[sanitize_text_field(key($item['sconto']))] = wc_format_decimal( $siqo_sconto );
				$total_fee = floatval($total_fee) + $siqo_sconto;
			}

			if (isset($item['prod_generic'])) {
				foreach ( $item['prod_generic'] as $ind => $ele) {
					$ind = sanitize_text_field( (string) $ind );
					$ele = is_scalar( $ele ) ? sanitize_text_field( (string) $ele ) : '';
					$info_string['prod_generic'][$ind] = $ele;
					$order->update_meta_data( '{'.substr($pos,1).'}prod_generic-'.$ind, $ind.' | '.$ele );

					$prod_generic_fee = new WC_Order_Item_Fee();
					$prod_generic_fee->set_name($ind);
				  	$prod_generic_fee->set_total( $ele );

				  	$order->add_item($prod_generic_fee);
				}
			}
			$order->update_meta_data( '{'.substr($pos,1).'}info-', wp_json_encode($info_string) );
		}
	}
}
	if ( 0 === count( $order->get_items() ) ) {
		wp_send_json_error( array( 'message' => __( 'Ordine senza articoli validi.', 'studio-immens-quick-order' ) ), 400 );
	}

	if (isset($_POST['table_name_cpt'])) {
		$order->update_meta_data( 'Table_cpt', sanitize_text_field(wp_unslash($_POST['table_name_cpt'])) );
	}
	if (isset($_POST['table_name'])) {
		$order->update_meta_data( 'Table', sanitize_text_field(wp_unslash($_POST['table_name'])) );
	}
	if (isset($_POST['order_note'])) {
		$order->update_meta_data( 'order_note', sanitize_text_field(wp_unslash($_POST['order_note'])) );
		$order->add_order_note( sanitize_text_field(wp_unslash($_POST['order_note'])) );
	}
	if (isset($_POST['table_surname'])) {
		$order->update_meta_data( 'table_surname', sanitize_text_field(wp_unslash($_POST['table_surname'])) );
	}
	$order->update_meta_data( 'delivery_type', 'table' );
	// SIQO_update_meta( 'siqo_last_woocommerce_order', wp_json_encode( $order ) );
	// SIQO_update_meta( 'siqo_last_woocommerce_order_products', wp_json_encode( $products ) );
	$order->set_payment_method('cod');
	$order->set_created_via( 'lista_ajax' );


if ( $total_fee > 0.0 ) {
	$fee = new WC_Order_Item_Fee();
	$fee->set_name('Discount');		//Give the Fee a name e.g. Discount
  	$fee->set_total( $total_fee );	//Set the Fee $total_fee
  	$order->add_item($fee);			//Add to the Order
}


  	$order->calculate_totals();		//Recalculate the totals. IMPORTANT!

	$order->save();

	$order_id = $order->get_id();

	if ( isset( $_POST['table_name_cpt'] ) && '' !== trim( $table_name_cpt ) ) {
		$table_id = absint( SIQO_get_page_id_by_title( $table_name_cpt, 'siqo_tables' ) );
		if ( $table_id ) {
			SIQO_update_meta( 'status_table_'.$table_id, 1, 'table_status' );
			if (function_exists('SIQOP_update_table_from_table_id')) {
				SIQOP_update_table_from_table_id( $table_id ,array(
					'table_number'=>$table_name_cpt,
					'table_id'=>$table_id,
					'siqo_table_status' => 1,
					'orders' => $order_id,
					'totals' => (string)$order->get_total(),
				) );
			}
		}
	}

do_action( 'SIQO_flash_list_order_ajax', $order_id );
	// $table = $order->id;
    wp_send_json(array(
    	// 'response' 	=> $response,
    	// 'time_exec' => $time,
		// 'loop_array' => $loop_array
	));
	die();
}
add_action('wp_ajax_siqo_flash_list_order_ajax', 'SIQO_flash_list_order_ajax');









function SIQO_flash_list_order(){
	$args = array(
		'orderby' 			=> 'title',
		'order'             => 'ASC',
		'post_type' 		=> array('product', 'product_variation'),
		'type' 				=> array('simple', 'variation'),
		'limit'             => 200,
	    'offset'            => null,
	    'page'              => 1,
	    'return'            => 'objects',
		'paginate'          => false,
	);
	$list_products = wc_get_products( $args );

	// $parent_product = wc_get_product(22998);
	// $args = array(
	//     'attribute_billing-period' => 'Yearly',
	//     'attribute_subscription-type' => 'Both'
	// );
	// $product_variation = $parent_product->get_matching_variation($args);
	$nonce = wp_create_nonce( 'SIQO_flash_list_order' );
    echo '<input type="hidden" id="_siqononce_flash_list_order" name="_siqononce_flash_list_order" value="'.esc_attr($nonce).'" />';
	?>
	<div class="SIQO_flash_list_order_container">
			<div class="FOloadingCardPublic" style="display:none;">
				<span style="animation: fospin 1s infinite;font-size:120px;width:120px;height:120px;" class="dashicons dashicons-update"></span>
			</div>
		<div class="FO_order_list_summary">

		</div>
		<div class="FONum_products" style="display:none;">0</div>
<!-- products list -->
		<div id="FO_order_list_search" class="FO_order_list_search">
			<input type="search" onkeyup="SIQO_search_for_target(this,'.FO_target_search');jQuery('.FO_order_list').show()" onclick="SIQO_search_for_target(this,'.FO_target_search')" class="focatsearchall FO_list_height" placeholder="<?php esc_html_e('Cerca prodotto...','studio-immens-quick-order' );?>">
			<div class="FO_clear_input" onclick="jQuery(this).prev().val('');jQuery(this).next().find('.FO_target_search').hide();">X</div>
			<div class="FO_order_list" style="display:none;">
			<?php
				foreach ($list_products as $key => $value) {
					$name = $value->get_name();

					$price = ($value->get_price()!='')?$value->get_price():'0';
					$short_title = (get_post_meta($value->get_id(),'short_title'))?get_post_meta($value->get_id(),'short_title')[0]:$value->get_name();
					$slang_title = (get_post_meta($value->get_id(),'slang_title'))?get_post_meta($value->get_id(),'slang_title')[0]:$short_title;
				// $name = $slang_title;
					$class = ($value->get_type() == 'variation')? 'FO_list_variation': '';
				?>
				<div class="FO_target_search FO_list_prod_summ <?php echo esc_attr($class);?>" foid="<?php echo esc_attr($value->get_id());?>" foname="<?php echo esc_attr($name);?>" fonameshort="<?php echo esc_attr($short_title);?>" fonameslug="<?php echo esc_attr($slang_title);?>" siqoprice="<?php echo esc_attr($price);?>" style="display:none;" onclick="SIQO_order_list(this);">
					<div class="foname">
						<?php
						echo esc_attr($slang_title);
						if ( $value->get_type() == 'variation' ) {
							echo "  |->  ".wp_kses_data(implode(", ", $value->get_attributes() ));
							// foreach ( $value->get_attributes() as $k => $attribute) {
							// 	echo $attribute.', ';
							// }
						}
						?>
					</div>
					<div class="FO_list_price" style="display:none;"><?php echo esc_attr(SIQO_price($value->get_price()));?></div>
					<div class="FO_clear_target" style="display:none;">X</div>
					<input type="hidden" name="[id][<?php echo esc_attr($value->get_id());?>]" value="1">
				</div>
			<?php } ?>

			</div>
			<?php siqo_add_inline_js( <<<'JS'

				function SIQO_list_order_height(){
					var formVal = jQuery("#FO_list_height").height();
					jQuery(".FO_order_list").css("top", formVal );
				}
				window.addEventListener('scroll', SIQO_list_order_height);
				window.addEventListener('resize', SIQO_list_order_height);
				SIQO_list_order_height();

				jQuery( window ).on( "click", function() {
					if (!jQuery(".FO_order_list, .FO_target_search, .FO_list_height").is(":focus") ) {
						jQuery(".FO_order_list").hide();
					}
					// if (!jQuery(".FO_order_list, .FO_list_height").is(":focus") ) {
					// 	jQuery(".FO_order_list").hide();
					// }
				});
JS
); ?>


			<div class="FO_table_input" style="border:1px solid #ffffff6b;">
<!-- Prodotto Generico -->
				<input type="text" value="<?php esc_html_e( 'Prodotto Generico', 'studio-immens-quick-order');?>" style="flex-basis:100%;" onkeyup="jQuery(this).parent().find('.final_input').attr('name','[prod_generic]['+jQuery(this).val()+']');jQuery(this).parent().find('.foname').text(jQuery(this).val())">

				<div class="spinner-button-big" onclick="SIQO_add_value_to_input(-1.00,'.fo_target_discount_prod')">-1</div>
				<div class="spinner-button-big" onclick="jQuery(this).next().val(parseFloat(parseFloat(jQuery(this).next().val()) - 0.10).toFixed(2))">-</div>
					<input id="discount" class="fo_target_discount_prod" type="number" name="discount" value="0">
				<div class="spinner-button-big" onclick="jQuery(this).prev().val(parseFloat(parseFloat(jQuery(this).prev().val()) + 0.10).toFixed(2))">+</div>
				<div class="spinner-button-big" onclick="SIQO_add_value_to_input(1.00,'.fo_target_discount_prod')">+1</div>
				<div class="FO_list_prod_summ" style="display:none;" siqoprice="">
					<div class="foname"><?php esc_html_e( 'Prodotto Generico', 'studio-immens-quick-order');?></div>
					<input class="final_input" type="hidden" name="[prod_generic][Prodotto Generico]" value="">
					<div class="FO_list_price"></div>
					<div class="FO_clear_target" onclick="SIQO_order_list_remove(jQuery(this).parent());jQuery(this).parent().remove();">X</div>
				</div>
				<div class="spinner-button-big" style="font-size:15px!important;" onclick="SIQO_order_discount_list(this,'.fo_target_discount_prod');">AGGIUNGI</div>
			</div>

		</div>
<!-- Tavolo -->
		<div class="FO_order_list_search">
			<?php
			$fo_front_surname = (!in_array(SIQO_get_meta('siqo_front_surname'), array(null, ''), true)) ? explode(",", SIQO_get_meta('siqo_front_surname')): '';
			if (post_type_exists( 'siqo_tables' )) {
				$tavoli = get_posts( array(
					'numberposts' => 100,
					'orderby'     => 'title',
					'order'       => 'ASC',
					'post_type'   => 'siqo_tables',
				) );
			}
			?>
			<div class="FO_table_input">
				<select name="table_name_cpt" class="fo-select" style="flex-basis:100%">
					<option value=""><?php esc_html_e('Seleziona Tavolo','studio-immens-quick-order');?></option>
					<?php foreach ($tavoli as $key => $value) { ?>
						<option value="<?php echo esc_attr($value->post_title);?>"><?php echo esc_attr($value->post_title);?></option>
					<?php } ?>
				</select>
				<span style="flex-basis:100%">
					<?php echo ' - '.esc_html__( 'oppure impostalo','studio-immens-quick-order').' - ';?>
				</span>
				<div class="spinner-button-big" onclick="jQuery(this).next().val(parseInt(jQuery(this).next().val()) - 1)">-</div>
				<input id="table_name" type="number" name="table_name" value="0">
				<div class="spinner-button-big" onclick="jQuery(this).prev().val(parseInt(jQuery(this).prev().val()) + 1)">+</div>
				<select name="table_surname" class="fo-select">
					<option value=""><?php esc_html_e('- zona -','studio-immens-quick-order');?></option>
					<?php foreach ($fo_front_surname as $key => $value) { ?>
						<option value="<?php echo esc_attr($value);?>"><?php echo esc_attr($value);?></option>
					<?php } ?>
				</select>
			</div>
		</div>
<!-- prezzo / sconto -->
		<div class="FO_order_list_search">

			<div class="FO_table_input">
				<span style="flex-basis:100%;">
					<?php echo ' - '.esc_html__( 'prezzo / sconto','studio-immens-quick-order').' - ';?>
				</span>
				<div class="spinner-button-big" onclick="SIQO_add_value_to_input(-1.00,'.fo_target_discount')">-1</div>
				<div class="spinner-button-big" onclick="jQuery(this).next().val(parseFloat(parseFloat(jQuery(this).next().val()) - 0.10).toFixed(2))">-</div>
					<input id="discount" class="fo_target_discount" type="number" name="discount"  value="0">
				<div class="spinner-button-big" onclick="jQuery(this).prev().val(parseFloat(parseFloat(jQuery(this).prev().val()) + 0.10).toFixed(2))">+</div>
				<div class="spinner-button-big" onclick="SIQO_add_value_to_input(1.00,'.fo_target_discount')">+1</div>
				<div class="FO_list_prod_summ" style="display:none;" siqoprice="">
					<div class="foname"><?php echo '- prezzo / sconto -';?></div>
					<input class="final_input" type="hidden" name="[sconto][]" value="">
					<div class="FO_list_price"></div>
					<div class="FO_clear_target" onclick="SIQO_order_list_remove(jQuery(this).parent());jQuery(this).parent().remove();">X</div>
				</div>
				<div class="spinner-button-big" style="font-size:15px!important;" onclick="SIQO_order_discount_list(this,'.fo_target_discount');">AGGIUNGI</div>
			</div>

		</div>

		<textarea name="order_note" placeholder="Note..." style="margin:5px;"></textarea>
		<div class="FO_summ_container">
			<div class="FO_summ_tot" style="flex-basis:100%">
				<span>0</span> <?php echo esc_attr(get_woocommerce_currency_symbol());?>
			</div>
			<div class="FO_order_avvia" style="flex-basis:100%" onclick="SIQO_order_list_ajax(this)">
				AVVIA
			</div>
		</div>
	</div>

	<?php
}












function SIQO_flash_tab_order_ajax( $poste = '', $clear = false, $verified = false ){
	if ( ! $verified ) {
		if ( !isset($_POST['_siqononce_flash_tab_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_flash_tab_order'])), 'SIQO_flash_tab_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
		}
	}
	$check_for_pay = false;
	if (SIQOcheck($poste) && is_array($poste) ) {
		$_POST = $poste;
		$check_for_pay = true;
	}

	if ( ! $verified && ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	$requested_table_id = isset( $_POST['table_id'] ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
	if ( $verified && $requested_table_id && 'siqo_tables' !== get_post_type( $requested_table_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Tavolo non valido.', 'studio-immens-quick-order' ) ), 400 );
	}

	$siqo_is_operator = ( ! $verified || $clear ) && class_exists( 'SIQO_Management' ) && SIQO_Management::can( 'manage_tables' );
	$siqo_source      = $siqo_is_operator ? 'operator' : 'customer_session';
	$siqo_reserved    = null;

	if ( ! $siqo_is_operator ) {
		if ( ! $requested_table_id || 'siqo_tables' !== get_post_type( $requested_table_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Seleziona un tavolo attivo prima di ordinare.', 'studio-immens-quick-order' ) ), 400 );
		}

		$siqo_table_key     = isset( $_POST['t'] ) ? sanitize_text_field( wp_unslash( $_POST['t'] ) ) : '';
		$siqo_key_table_id  = ( $siqo_table_key && class_exists( 'SIQO_Table_Sessions' ) ) ? (int) SIQO_Table_Sessions::find_table_by_key( $siqo_table_key ) : 0;
		if ( ! $siqo_table_key || ! $siqo_key_table_id || $siqo_key_table_id !== (int) $requested_table_id ) {
			wp_send_json_error( array( 'message' => __( 'Tavolo non valido: scansiona il QR del tavolo.', 'studio-immens-quick-order' ) ), 403 );
		}
	}

	if ( $clear && $requested_table_id ) {
		global $wpdb;
		$last_table = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}siqo_tables WHERE table_id = %s ORDER BY last_update DESC, id DESC LIMIT 1", $requested_table_id ) );
		if ( $last_table && (int) $last_table->status === 10 ) {
			wp_send_json_error( array( 'message' => __( 'Il tavolo risulta giÃƒÆ’Ã‚Â  chiuso.', 'studio-immens-quick-order' ) ), 409 );
		}
	}

	$order = new WC_Order();

	$user_id = (isset($_POST['user_id'])) ? sanitize_text_field(wp_unslash($_POST['user_id'])):'';
	$created_via = (isset($_POST['created_via'])) ? sanitize_text_field(wp_unslash($_POST['created_via'])):'tab_ajax';
	$user = wp_get_current_user();

	$order->update_meta_data( 'created_via', $created_via );
	$order->update_meta_data( 'user_id', $user_id );

	// if (isset($_POST['S_customers']) && $_POST['S_customers'] != null ) {
	// 	foreach ($_POST['S_customers'] as $S_key => $S_value) {
	// 		if (!isset($S_value)){continue;}
	// 		foreach ($S_value as $key => $value) {
	// 			if (!isset($value)){continue;}
	// 			foreach ($value as $k => $v) {
	// 	// wp_send_json(array(
	// 	// 	'v' => $v,
	// 	// ));
	// 	// die();
	// 				if ( isset($S_key['customer_ID'][$k]) ) {
	// 					$order->update_meta_data( '{customer_ID}'.$k, $v );
	// 					update_post_meta($order->id, '_customer_user', $v);
	// 					// $order->set_customer_id( $v );
	// 				} else{
	// 					// $username = (isset($key['customer_name'][$k]))?$key['customer_name'][$k]:'';
	// 					// $customer_mail = (isset($key['customer_mail'][$k]))?$key['customer_mail'][$k]:'';
	// 					// $customer_phone = (isset($key['customer_phone'][$k]))?$key['customer_phone'][$k]:'';

	// 					// wp_create_user( $username, '1234', $customer_mail );
	// 				}
	// 				// $order->update_meta_data( '{customer_ind_'.$c_index.'}'.$k, array_values($v)[0] );
	// 				// if ($k == 'customer_ID') {
	// 				// 	$order->set_customer_id(array_values($v)[0]);
	// 				// }
	// 			}
	// 		}
	// 	}
	// }
	$table_name_cpt = (isset($_POST['table_name_cpt'])) ? sanitize_text_field(wp_unslash($_POST['table_name_cpt'])):'';
	$order->update_meta_data( 'Table_cpt', $table_name_cpt );

	$address = array(
		'first_name'=> $user->user_firstname,
		'last_name' => $user->user_lastname,
		'email'     => $user->user_email,
		'phone'     => get_user_meta( $user->ID, 'billing_phone', true ),
	    'address_1' => $table_name_cpt,
	    'address_2' => '', //sanitize_text_field($_POST['table_name']),
	    'company'   => '', //sanitize_text_field($_POST['table_surname'])
	);
	$order->set_address( $address, 'shipping' );
	$addressBilling = array(
		'first_name'=> $user->user_firstname,
		'last_name' => $user->user_lastname,
		'email'     => $user->user_email,
		'phone'     => get_user_meta( $user->ID, 'billing_phone', true ),
	);
	$order->set_address( $addressBilling, 'billing' );

	$info_string = array(); $vari_arr = array();
	$price_array = array(); $prod_args = array();
	$siqo_order_items = array();
$siqo_serialmap = isset( $_POST['foserialmap'] ) ? wp_json_encode( siqo_posted_array( 'foserialmap' ) ) : '';
$order->update_meta_data( 'foserialmap', sanitize_text_field( $siqo_serialmap ) );
$total_fee = 0.0;
$pre_vari_arr = array();
$pre_price_array = array();
if ( isset( $_POST['foserialmap'] ) ) {
	foreach ( siqo_posted_array( 'foserialmap' ) as $pre_E ) {
		if ( ! is_array( $pre_E ) ) { continue; }
		foreach ( $pre_E as $pre_ele ) {
			if ( ! is_array( $pre_ele ) ) { continue; }
			if ( isset( $pre_ele['Variante'] ) ) {
				foreach ( $pre_ele['Variante'] as $pre_index => $pre_element ) {
					if ( ! is_array( $pre_element ) ) { continue; }
					foreach ( $pre_element as $pre_var_key => $pre_var_val ) {
						$pre_vari_arr[ $pre_index ][ 'attribute_' . $pre_var_key ] = $pre_var_val;
					}
				}
			}
			if ( isset( $pre_ele['price'] ) ) {
				foreach ( $pre_ele['price'] as $pre_index => $pre_element ) {
					if ( $pre_element == null || $pre_element == '' ) { $pre_element = 0; }
					$pre_price_array[ $pre_index ] = $pre_element;
				}
			}
		}
	}
}
$vari_arr = $pre_vari_arr;
$price_array = $pre_price_array;
if (isset($_POST['foserialmap'])) {
	foreach ( siqo_posted_array( 'foserialmap' ) as $K => $E ) {
		foreach ($E as $ind => $ele) {
			if (isset($ele['Variante'])) {
				foreach ( $ele['Variante'] as $index => $element) {
					foreach( $element as $var_key => $var_val ){
						$vari_arr[$index]['attribute_'.$var_key] = $var_val;
					}
				}
			}
		}
		foreach ($E as $ind => $ele) {
			if (isset($ele['price'])) {
				foreach ( $ele['price'] as $index => $element) {

					if ($element==null||$element=='') {
						$element = 0;
					}
					$price_array[$index] = $element;
				}
			}
		}
		$price_array = $price_array;

		foreach ( $E as $pos => $item) {
			if ( isset($item['price']) ) {
				$order->update_meta_data( '{'.substr($pos,1).'}price-'.sanitize_text_field(key($item['price'])), sanitize_text_field( array_values( $item['price'] )[0] ) );
				$info_string[sanitize_text_field(key($item['price']))] = sanitize_text_field(array_values($item['price'])[0]);
			}
			if ( isset($item['id']) ) {
				$product_key = sanitize_text_field( (string) key($item['id']) );
				$product_id  = absint( $product_key );
				$product     = $product_id ? wc_get_product( $product_id ) : false;
				if ( ! $product ) {
					continue;
				}
				$order->update_meta_data( '{'.substr($pos,1).'}index-'.$product_key, sanitize_text_field( array_values( $item['id'] )[0] ) );

					$total = isset( $price_array[ $product_id ] ) ? (float) $price_array[ $product_id ] : (float) $product->get_price();
					$meta_total = $total;

				if ( isset( $vari_arr[ $product_key ] ) ) {
					$product_variation = $product->get_matching_variation( $vari_arr[ $product_key ] );
					$product = $product_variation ? wc_get_product( $product_variation ) : false;
				}
				$p_qty = (int) sanitize_text_field( array_values( $item['id'] )[0] );
				if ( ! $product ) {
					continue;
				}
				if ( $verified && ( ! $product->is_purchasable() || ! $product->is_in_stock() ) ) {
					continue;
				}
				if ( $verified && $p_qty < 1 ) {
					continue;
				}
				if ( $verified && $p_qty > 0 ) {
					$max_qty = max( 1, (int) apply_filters( 'siqo_max_order_item_quantity', 999 ) );
					$p_qty   = min( $p_qty, $max_qty );
					if ( $product->managing_stock() ) {
						$stock_qty = $product->get_stock_quantity();
						if ( null !== $stock_qty ) {
							$p_qty = min( $p_qty, max( 0, (int) $stock_qty ) );
						}
					}
					if ( $p_qty < 1 ) {
						continue;
					}
				}
				if ( $verified ) {
					$total = null;
					$meta_total = $product ? $product->get_price() : 0;
				}
					$order->update_meta_data( '{'.substr($pos,1).'}'.sanitize_text_field(key($item['id'])), $meta_total );
				// if ( $p_qty > 1 ) {
				// 	$total = $total * $p_qty;
				// 	$total = number_format((float)$total, 2, '.', '');
				// }
				$order_item = $order->add_product( $product, $p_qty, ( null === $total ) ? array() : array('total'=>$total) );
				if ( $order_item && is_numeric( $order_item ) ) {
					$order_item = $order->get_item( (int) $order_item );
				}
				if ( $order_item instanceof WC_Order_Item ) {
					$siqo_order_items[ (string) $product_key ] = $order_item;
				}
				$info_string[sanitize_text_field(key($item['id']))] = sanitize_text_field(array_values($item['id'])[0]);
			}
			if (isset($item['item_note'])) {
				$note_key  = sanitize_text_field( (string) key($item['item_note']) );
				$item_note = sanitize_text_field( (string) array_values( $item['item_note'] )[0] );
				if ( '' !== $item_note && isset( $siqo_order_items[ $note_key ] ) ) {
					$siqo_order_items[ $note_key ]->add_meta_data( '_siqo_item_note', $item_note );
					$siqo_order_items[ $note_key ]->save();
				}
				$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$note_key.'_modification-', $item_note );
				$info_string['modification'][ $note_key ] = $item_note;
			}
			if (isset($item['note'])) {
				$order->update_meta_data('{'.substr($pos,1).'}prod-'.sanitize_text_field(key($item['note'])).'_note-',sanitize_text_field(array_values($item['note'])[0]));
				$info_string['note'][sanitize_text_field(key($item['note']))] = sanitize_text_field(array_values($item['note'])[0]);
			}
			if (isset($item['siqo_ingredients'])) {
				foreach ( $item['siqo_ingredients'] as $index => $element) {
					if ( ! is_array( $element ) ) { continue; }
					foreach( $element as $ing_key => $ing_val ){
						$ing_key = sanitize_text_field( (string) $ing_key );
						$ing_val = is_scalar( $ing_val ) ? sanitize_text_field( (string) $ing_val ) : '';
						$info_string['siqo_ingredients'][$index][$ing_key] = $ing_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_ingredient-'.$ing_key, $ing_key.' | '.$ing_val );
					}
				}
			}
			if (isset($item['Variante'])) {
				foreach ( $item['Variante'] as $index => $element) {
					if ( ! is_array( $element ) ) { continue; }
					foreach( $element as $var_key => $var_val ){
						$var_key = sanitize_text_field( (string) $var_key );
						$var_val = is_scalar( $var_val ) ? sanitize_text_field( (string) $var_val ) : '';
						$info_string['Variante'][$index][$var_key] = $var_val;
						$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_variant-'.$var_key, $var_key.' | '.$var_val );
					}
				}
			}
			if (isset($item['siqo_temperatures'])) {
				foreach ( $item['siqo_temperatures'] as $index => $element) {
					$element = is_scalar( $element ) ? sanitize_text_field( (string) $element ) : '';
					$info_string['siqo_temperatures'][$index] = $element;
					$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$index.'_temperature-'.$index, $index.' | '.$element );
				}
			}
			if (isset($item['Temperature'])) {
				$temp_key = sanitize_text_field( (string) array_key_first( $item['Temperature'] ) );
				$temp_val = sanitize_text_field( (string) array_values( $item['Temperature'] )[0] );
				if ( '' !== $temp_key && '' !== $temp_val ) {
					$info_string['siqo_temperatures'][$temp_key] = $temp_val;
					$order->update_meta_data( '{'.substr($pos,1).'}prod-'.$temp_key.'_temperature-'.$temp_key, $temp_key.' | '.$temp_val );
				}
			}
			if (isset($item['searched'])) {
				$info_string['searched'] = sanitize_text_field( array_values( $item['searched'] )[0] );
			}
			if ( ! $verified && isset($item['sconto']) ) {
				// Stesso limite del percorso cliente: nessuno sconto oltre il subtotale.
				$siqo_sconto_raw = floatval( sanitize_text_field( array_values( $item['sconto'] )[0] ) );
				if ( $siqo_sconto_raw < 0 ) {
					$siqo_sconto_raw = 0;
				}
				$siqo_order_cap = (float) $order->get_subtotal();
				$siqo_sconto     = min( $siqo_sconto_raw, max( 0, $siqo_order_cap ) );
				$order->update_meta_data( '{'.substr($pos,1).'}sconto-', wc_format_decimal( $siqo_sconto ) );
				$info_string[sanitize_text_field(key($item['sconto']))] = wc_format_decimal( $siqo_sconto );
				$total_fee = floatval($total_fee) + $siqo_sconto;
			}

			if ( ! $verified && isset($item['prod_generic']) ) {
				foreach ( $item['prod_generic'] as $ind => $ele) {
					$ind = sanitize_text_field( (string) $ind );
					$ele = is_scalar( $ele ) ? sanitize_text_field( (string) $ele ) : '';
					$info_string['prod_generic'][$ind] = $ele;
					$order->update_meta_data( '{'.substr($pos,1).'}prod_generic-'.$ind, $ind.' | '.$ele );
					$prod_generic_fee = new WC_Order_Item_Fee();
					$prod_generic_fee->set_name($ind);
				  	// $qty = $E[$pos]['qty'][$ind];
				  	// $tot = $ele * $qty;
				  	$prod_generic_fee->set_total( $ele );

				  	$order->add_item($prod_generic_fee);
				}
			}
			if ( isset($item['orders']) ) {
				foreach ( $item['orders'] as $index => $element) {
					$info_string['orders'][$index] = $element;
					$order->update_meta_data( '{'.substr($pos,1).'}order-'.$index, $element );
					// $old_order = new WC_Order($element);
					$old_order = wc_get_order($element);
					if (!empty($old_order)) {
						// $old_order->update_status( 'completed' );
					}
				}
			}
			$order->update_meta_data( '{'.substr($pos,1).'}info-', wp_json_encode($info_string) );
		}
	}
}

	if (isset($_POST['table_name'])) {
		$order->update_meta_data( 'Table', sanitize_text_field(wp_unslash($_POST['table_name'])) );
	}
	if (isset($_POST['order_note'])) {
		$order->update_meta_data( 'order_note', sanitize_text_field(wp_unslash($_POST['order_note'])) );
		$order->add_order_note( sanitize_text_field(wp_unslash($_POST['order_note'])) );
	}
	if (isset($_POST['table_surname'])) {
		$order->update_meta_data( 'table_surname', sanitize_text_field(wp_unslash($_POST['table_surname'])) );
	}

	$order->update_meta_data( 'delivery_type', 'table' );

	$order->set_payment_method('cod');
	$order->set_created_via( $created_via );

	$siqo_customer_email = ( isset( $_POST['customer_email'] ) ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
	if ( $siqo_customer_email ) {
		$order->set_billing_email( $siqo_customer_email );
	}

	$siqo_customer_id = ( isset( $_POST['customer_id'] ) ) ? absint( $_POST['customer_id'] ) : 0;
	if ( $siqo_customer_id ) {
		$order->set_customer_id( $siqo_customer_id );
	}

if ($total_fee > 0.0 ) {
	$fee = new WC_Order_Item_Fee();
	$fee->set_name('Sconto');		//Give the Fee a name e.g. Discount
  	$fee->set_total( $total_fee );	//Set the Fee $total_fee
  	$order->add_item($fee);			//Add to the Order
}

if ( ! $verified && $order->get_items()!=null) {
	foreach ($order->get_items() as $key => $item) {
		$lookup_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		if ( isset( $price_array[ $lookup_id ] ) ) {
			$tot = $price_array[ $lookup_id ];
		} elseif ( isset( $price_array[ $item->get_product_id() ] ) ) {
			$tot = $price_array[ $item->get_product_id() ];
		} else {
			$tot = $item->get_total();
		}
		if ((int)$item->get_quantity()>1) {
			$tot = (float)$tot * (float)$item->get_quantity();
		}
		// $qty = $item->get_quantity();
	    wc_update_order_item_meta($item->get_id(),'_line_total',$tot); //_line_total discount_amount coupon_amount
	    wc_update_order_item_meta($item->get_id(),'_line_subtotal',$tot); //_line_total discount_amount coupon_amount
		$item->save_meta_data();
		$item->save();
	}
}

	$siqo_coupon_codes = siqo_posted_array( 'coupon_codes' );
	foreach ( $siqo_coupon_codes as $siqo_coupon_code ) {
		$siqo_coupon_code = sanitize_text_field( $siqo_coupon_code );
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			$siqo_coupon_code = wc_format_coupon_code( $siqo_coupon_code );
		}
		if ( '' === $siqo_coupon_code ) {
			continue;
		}
		try {
			$order->apply_coupon( $siqo_coupon_code );
		} catch ( Exception $e ) {
			continue;
		}
	}

	$siqo_line_total = 0;
	foreach ( $order->get_items() as $siqo_item ) {
		$siqo_line_total += (float) $siqo_item->get_total();
	}
	if ( $clear && ( 0 === count( $order->get_items() ) || $siqo_line_total <= 0 ) ) {
		wp_send_json_error( array( 'message' => __( 'Impossibile chiudere il tavolo: ordine senza articoli o con totale zero.', 'studio-immens-quick-order' ) ), 400 );
	}
	if ( $verified && 0 === count( $order->get_items() ) ) {
		wp_send_json_error( array( 'message' => __( 'Ordine senza articoli validi.', 'studio-immens-quick-order' ) ), 400 );
	}
	if ( ! $siqo_is_operator ) {
		$siqo_reserved = SIQO_Table_Sessions::reserve_order( $requested_table_id );
		if ( is_wp_error( $siqo_reserved ) ) {
			wp_send_json_error( array( 'message' => $siqo_reserved->get_error_message() ), 403 );
		}
	}
	try {
		$order->calculate_totals();	//Recalculate the totals. IMPORTANT!
		$order->save();
	} catch ( Exception $siqo_create_error ) {
		if ( $siqo_reserved && ! is_wp_error( $siqo_reserved ) ) {
			SIQO_Table_Sessions::release_order( (int) $siqo_reserved->id );
		}
		wp_send_json_error( array( 'message' => __( 'Impossibile creare l\'ordine. Riprova.', 'studio-immens-quick-order' ) ), 500 );
	}

	$order_id = $order->get_id();

	$siqo_session  = $siqo_reserved ? $siqo_reserved : SIQO_Table_Sessions::get_active_for_table( $requested_table_id );
	$siqo_approval = 'auto_approved';
	if ( 'customer_session' === $siqo_source && $siqo_reserved && (int) $siqo_reserved->orders_count === 1 ) {
		$siqo_approval = 'pending';
	}
	$order->update_meta_data( '_siqo_inserted_by', get_current_user_id() );
	$order->update_meta_data( '_siqo_order_source', $siqo_source );
	$order->update_meta_data( '_siqo_session_id', $siqo_session ? (int) $siqo_session->id : 0 );
	$order->update_meta_data( '_siqo_approval', $siqo_approval );
	$order->save();

	if ( 'pending' === $siqo_approval && $siqo_session ) {
		global $wpdb;
		$siqo_transitioned = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'awaiting_approval' WHERE id = %d AND status IN ('open','awaiting_approval')",
				SIQO_Table_Sessions::table_name(),
				(int) $siqo_session->id
			)
		);
		if ( 0 === $siqo_transitioned ) {
			$order->update_meta_data( '_siqo_approval', 'rejected' );
			$order->update_status( 'cancelled', __( 'Sessione del tavolo non piÃƒÆ’Ã‚Â¹ attiva.', 'studio-immens-quick-order' ) );
			$order->save();
			SIQO_Table_Sessions::release_order( (int) $siqo_session->id );
			wp_send_json_error( array( 'message' => __( 'Tavolo non attivo. Chiama un operatore.', 'studio-immens-quick-order' ) ), 403 );
		}
	}

	$siqo_previous_order_ids = array();
	if ( ! empty( $info_string['orders'] ) && is_array( $info_string['orders'] ) ) {
		foreach ( $info_string['orders'] as $siqo_previous_order_id ) {
			$siqo_previous_order_id = absint( $siqo_previous_order_id );
			if ( $siqo_previous_order_id && $siqo_previous_order_id !== $order_id ) {
				$siqo_previous_order_ids[] = $siqo_previous_order_id;
			}
		}
	}
	$siqo_previous_order_ids = array_values( array_unique( $siqo_previous_order_ids ) );

	if ( $clear && ! empty( $siqo_previous_order_ids ) ) {
		foreach ( $siqo_previous_order_ids as $siqo_previous_order_id ) {
			$siqo_previous_order = wc_get_order( $siqo_previous_order_id );
			if ( ! $siqo_previous_order || $siqo_previous_order->get_id() === $order_id ) {
				continue;
			}
			if ( ! in_array( $siqo_previous_order->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) ) {
				continue;
			}
			/* translators: %d: order ID. */
			$siqo_previous_order->add_order_note( sprintf( __( 'Unito allÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ordine #%d.', 'studio-immens-quick-order' ), $order_id ) );
			$siqo_previous_order->set_status( 'cancelled' );
			$siqo_previous_order->save();
		}
	}

	if ( isset( $_POST['siqo_table_status'] ) && ! $verified ) {
		$siqo_new_status     = sanitize_text_field( wp_unslash( $_POST['siqo_table_status'] ) );
		$siqo_allowed_status = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
		if ( in_array( $siqo_new_status, $siqo_allowed_status, true ) ) {
			$order->update_status( $siqo_new_status );
		}
	}

	$order = wc_get_order($order_id);

	if ( 'customer_session' === $siqo_source && $siqo_session ) {
		$siqo_added = SIQO_Table_Sessions::add_order_total( $siqo_session->id, (float) $order->get_total() );
		if ( ! $siqo_added ) {
			$order->update_meta_data( '_siqo_approval', 'rejected' );
			$order->update_status( 'cancelled' );
			$order->save();
			SIQO_Table_Sessions::release_order( (int) $siqo_session->id );
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET status = 'open' WHERE id = %d AND status = 'awaiting_approval'",
					SIQO_Table_Sessions::table_name(),
					(int) $siqo_session->id
				)
			);
			$order->add_order_note( __( 'Annullato: superato il limite importo della sessione tavolo.', 'studio-immens-quick-order' ) );
			wp_send_json_error( array( 'message' => __( 'Limite importo raggiunto per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) ), 403 );
		}
	}

	// if ($c_index == 1) {
	// 	$order->set_customer_id($user_id);
	// }

	$order->calculate_totals();
	$order->save();
$table_id = (isset($_POST['table_id'])) ? sanitize_text_field(wp_unslash($_POST['table_id'])):'';

$table_total = 0.0;
if ( ! $verified && isset($_POST['table_total']) ) {
	$table_total = (float) sanitize_text_field(wp_unslash($_POST['table_total']));
}
if ( $verified && '' !== $table_id && function_exists('SIQOP_get_table_by_table_id_last') ) {
	$current_table = SIQOP_get_table_by_table_id_last( $table_id, 'OBJECT' );
	if ( $current_table && (int) $current_table->status !== 10 ) {
		$table_total = (float) $current_table->totals;
	}
}

$table_total = (float)$table_total + (float)$order->get_total();

	if ( '' !== $table_id ) {
		SIQO_update_meta( 'status_table_'.$table_id, 1, 'table_status' );
		if ( function_exists('SIQOP_update_table_from_table_id') ) {
			SIQOP_update_table_from_table_id( $table_id, array(
				'table_number'=> $table_name_cpt,
				'table_id'=> $table_id,
				'siqo_table_status' => 1,
				'orders' => $order_id,
				'totals' => (string)$table_total,
			) );
		}
	}

	if ($clear && '' !== $table_id) {
		do_action( 'siqo_table_paid', $order_id, $table_id, $table_name_cpt );
		SIQO_update_meta( 'status_table_'.$table_id, 0, 'table_status' );
		if (function_exists('SIQOP_update_table_from_table_id')) {
			SIQOP_update_table_from_table_id( $table_id, array(
				'end_time' 	=> wp_date('Y-m-d H:i:s'),
				'siqo_table_status' 	=> 10,
				'orders' 	=> $order_id,
			), true );
		}
	}

do_action( 'SIQO_flash_tab_order_ajax', $order_id );
	if (!SIQOcheck($poste) && !is_array($poste) ) {
		wp_send_json(array(
			// 'time_exec' => $time,
			// 'post' => $_POST,
			'table_id' => $table_id,
			'order_id'=> wp_json_encode($order_id),
			'order_date' => $order->get_date_created() ? wp_date( 'd/m/Y H:i', $order->get_date_created()->getTimestamp() ) : '',
			'order_total'=> (string)$order->get_total(),
			'price_array' => $price_array,
			// 'vari_arr' => $vari_arr
		));
		die();
	} else {
		wp_send_json(array(
			'table_id' => $table_id,
			'order_id'=> wp_json_encode($order_id),
			'order_date' => $order->get_date_created() ? wp_date( 'd/m/Y H:i', $order->get_date_created()->getTimestamp() ) : '',
			'order_total'=> (string)$order->get_total(),
			//'price_array' => $price_array,
		));
		die();
	}
}
add_action('wp_ajax_SIQO_flash_tab_order_ajax', 'SIQO_flash_tab_order_ajax');

function SIQO_flash_tab_clear_table( $poste = '' ){
	if ( !isset($_POST['_siqononce_flash_tab_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_flash_tab_order'])), 'SIQO_flash_tab_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	if (SIQOcheck($poste) && is_array($poste) ) {
		$_POST = $poste;
	}
	$table_id = ( isset( $_POST['table_id'] ) ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
	if ( ! $table_id ) {
		wp_send_json_error( array( 'message' => __( 'Tavolo non valido.', 'studio-immens-quick-order' ) ), 400 );
	}
	// do_action( 'SIQO_flash_tab_clear_table', $order_id );
	SIQO_update_meta( 'status_table_'.$table_id, 0, 'table_status' );
	if (function_exists('SIQOP_update_table_from_table_id')) {
		SIQOP_update_table_from_table_id( $table_id, array(
			'end_time' 	=> wp_date('Y-m-d H:i:s'),
			'siqo_table_status' 	=> 10,
		) );
	}
	if (!SIQOcheck($poste) && !is_array($poste) ) {
	    wp_send_json(array(
			'table_id' => $table_id,
		));
		die();
	}
}

function SIQO_flash_tab_clear_all_tables(){
	if ( !isset($_POST['_siqononce_flash_tab_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_siqononce_flash_tab_order'])), 'SIQO_flash_tab_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	$tables = SIQO_get_meta_by_assoc_id( 'table_status', 'OBJECT' );

	foreach ($tables as $table) {
		SIQO_update_meta( $table->meta_key, 0, 'table_status' );
		if (function_exists('SIQOP_update_table_from_table_id')) {
			SIQOP_update_table_from_table_id( str_replace( "status_table_", "", $table->meta_key ), array(
				'end_time' 	=> wp_date('Y-m-d H:i:s'),
				'siqo_table_status' 	=> 10,
			) );
		}
	}

   wp_send_json(array(
		// 'post' => $_POST,
		'tables' => $tables,
	));
	die();

}




function SIQO_flash_tab_pay_ajax(){
	if ( !isset($_POST['_siqononce_flash_tab_order']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_flash_tab_order'])), 'SIQO_flash_tab_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	$table_id = ( isset( $_POST['table_id'] ) ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
	if ( ! $table_id ) {
		wp_send_json_error( array( 'message' => __( 'Tavolo non valido.', 'studio-immens-quick-order' ) ), 400 );
	}
	// do_action( 'SIQO_flash_tab_clear_table', $order_id );
	$_POST['siqo_table_status'] = 'completed';
	// $_POST['pay_status_order'] = 'completed';
	// foreach ($_POST['orders'] as $key => $value) {
		// $_POST['order_id'] = $value;
		// $order = new WC_Order($value);
		// if (!empty($order)) {
		// 	$order->update_status( 'completed' );
		// }
	//	// SIQO_ajax_change_order_status( $_POST );
	// }
	// SIQO_flash_tab_clear_table( $_POST );
	// SIQO_update_meta( 'status_table_'.sanitize_text_field($_POST['table_id']), 0, 'table_status' );
	// // SIQO_update_meta( 'table_object_'.sanitize_text_field($_POST['table_id']), 1, 'table_status' );
	// if (function_exists('SIQOP_update_table_from_table_id')) {
	// 	SIQOP_update_table_from_table_id(sanitize_text_field($_POST['table_id']),array(
	// 		'end_time' 	=> wp_date('Y-m-d H:i:s'),
	// 		'siqo_table_status' 	=> 10,
	// 	) );
	// }
	SIQO_flash_tab_order_ajax( $_POST, true, true );

	wp_send_json(array(
		'table_id' => $table_id,
	));
	die();
}

function SIQO_pay_coupon_quote() {
	check_ajax_referer( 'siqo_ajax_view_orders_vars', 'nonce' );

	if ( ! is_user_logged_in() || ! class_exists( 'WC_Order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Operazione non consentita.', 'studio-immens-quick-order' ) ) );
	}

	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ) );
	}

	$items = siqo_json_post( 'items' );
	if ( ! is_array( $items ) || empty( $items ) ) {
		wp_send_json_error( array( 'message' => __( 'Nessun articolo da calcolare.', 'studio-immens-quick-order' ) ) );
	}

	$order = new WC_Order();

	$customer_email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
	if ( $customer_email ) {
		$order->set_billing_email( $customer_email );
	}

	$added = 0;
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
		$qty        = isset( $item['qty'] ) ? max( 1, absint( $item['qty'] ) ) : 1;
		$price      = isset( $item['price'] ) ? (float) wc_format_decimal( $item['price'] ) : 0;
		if ( ! $product_id ) {
			continue;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}

		$line_total = $price * $qty;
		$order->add_product(
			$product,
			$qty,
			array(
				'total'    => $line_total,
				'subtotal' => $line_total,
			)
		);
		$added++;
	}

	if ( ! $added ) {
		wp_send_json_error( array( 'message' => __( 'Nessun articolo valido da calcolare.', 'studio-immens-quick-order' ) ) );
	}

	$order->calculate_totals();

	$coupon_codes = siqo_json_post( 'coupons' );
	$coupon_codes = is_array( $coupon_codes ) ? $coupon_codes : array();

	$errors = array();
	foreach ( $coupon_codes as $coupon_code ) {
		$coupon_code = sanitize_text_field( $coupon_code );
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			$coupon_code = wc_format_coupon_code( $coupon_code );
		}
		if ( '' === $coupon_code ) {
			continue;
		}
		try {
			$order->apply_coupon( $coupon_code );
		} catch ( Exception $e ) {
			$errors[] = array(
				'code'    => $coupon_code,
				'message' => wp_strip_all_tags( $e->getMessage() ),
			);
		}
	}

	$order->calculate_totals();

	$coupon_lines = array();
	foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
		$coupon_lines[] = array(
			'code'     => method_exists( $coupon_item, 'get_code' ) ? $coupon_item->get_code() : $coupon_item->get_name(),
			'discount' => (float) $coupon_item->get_discount(),
		);
	}

	wp_send_json_success(
		array(
			'subtotal'       => (float) $order->get_subtotal(),
			'discount_total' => (float) $order->get_discount_total(),
			'total'          => (float) $order->get_total(),
			'coupons'        => $coupon_lines,
			'errors'         => $errors,
		)
	);
}
add_action( 'wp_ajax_SIQO_pay_coupon_quote', 'SIQO_pay_coupon_quote' );









function SIQOP_get_table_by_table_id_last( $table_id, $type = 'OBJECT' ){
  global $wpdb;
  $table = $wpdb->prefix . "siqo_tables";
  // $date = wp_date('Y-m-d H:i:s');
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE table_id = %s AND status < 10 ORDER BY last_update DESC, id DESC", $table, $table_id ), $type );
	return $result;
}


function SIQO_flash_tab_order( $tavoli = array() ){
	$order_index = 999999;
	$products = SIQO_get_products_for_loop(array('taxonomy'=>'siqo_macro_categories'));
	$products_w_vari = SIQO_get_products_for_loop(array('taxonomy'=>'siqo_macro_categories','type'=>array('simple','variable')));

	// $macro_cat_args = array(
	//    'number'     => false,
	//    'include' => 'all',
	//    'hide_empty' => true, //can be 1, '1' too
	//    'orderby'   => 'title',
	//    'order'     => 'ASC',
	// );
	// $products_macro_categories = get_terms( 'siqo_macro_categories', $macro_cat_args );

	$products_macro_categories = new WP_Term_Query( array(
		'taxonomy'	=> 'siqo_macro_categories',
		'include' 	=> array(),
		'hide_empty' => false,
		'orderby'   => 'title',
		'order'     => 'ASC',
	) );
		$m_cat_check = 'siqo_macro_categories';
	if ( $products_macro_categories->terms == null ) {
		$m_cat_check = 'product_cat';
		$products_macro_categories = new WP_Term_Query( array(
			'taxonomy'	=> 'product_cat',
			'include' 	=> array(),
			'hide_empty' => false,
			'orderby'   => 'title',
			'order'     => 'ASC',
		) );
	}
	if ( $products_macro_categories->terms != null ) {
		$first_macro = $products_macro_categories->terms[0];
	} else{ $first_macro = false; }


	$nonce = wp_create_nonce( 'SIQO_flash_tab_order' );
    echo '<input type="hidden" id="_siqononce_flash_tab_order" name="_siqononce_flash_tab_order" value="'.esc_attr($nonce).'" />';
	?>
	<div class="FO_flash_tab_order_container fo_tab_show" style="display:none;">
		<!-- <div class="Advanced_Card_Close"onclick="SIQO_tab_Card_hide()"><?php esc_html_e('CHIUDI','studio-immens-quick-order');?></div> -->
		<div class="FOloadingCardPublic tab_fix_load" style="display:none;">
			<span style="animation: fospin 1s infinite;font-size:120px;width:120px;height:120px;" class="dashicons dashicons-update"></span>
		</div>

		<div class="FO_flash_tab_header">
			<div class="fo_button fo_cat_butt fo_shadow" onclick="SIQO_filter_tab_product(<?php echo "'-BLANK-'"; ?>);siqo_tab_hystory_space('.fo_column_products','.fo_column_story','.fo_column_riepilogo')" ondrop="FOdrop(event)" ondragover="FOallowDrop(event)" fo_cat_name="-BLANK-" fo_cat_id="-BLANK-" fo_cat_ceck="<?php echo esc_attr($m_cat_check);?>">
				<span class="dashicons dashicons-editor-customchar" fo_cat_name="-BLANK-" fo_cat_id="-BLANK-" fo_cat_ceck="<?php echo esc_attr($m_cat_check);?>"></span>
			</div>
			<?php foreach ($products_macro_categories->terms as $key => $value) {
				$MC_img = get_term_meta($value->term_id, 'taxonomy_image');
				$MC_img = ( SIQOcheck($MC_img) && isset($MC_img[0]) && $MC_img[0] !='')? $MC_img[0]:'';
				?>
				<div class="fo_button fo_cat_butt fo_shadow" onclick="SIQO_filter_tab_product(<?php echo "'".esc_attr($value->slug)."'"; ?>);siqo_tab_hystory_space('.fo_column_products','.fo_column_story','.fo_column_riepilogo')" ondrop="FOdrop(event)" ondragover="FOallowDrop(event)" fo_cat_name="<?php echo esc_attr($value->slug);?>" fo_cat_id="<?php echo esc_attr($value->term_id);?>" fo_cat_ceck="<?php echo esc_attr($m_cat_check);?>" style="background-image:url(<?php echo esc_attr($MC_img);?>);">
					<?php echo esc_attr($value->name);?>
				</div>
			<?php } ?>
			<div class="fo_button Advanced_Card_Close_float" onclick="SIQO_tab_Card_hide()" style="margin-left:auto!important;">
				<?php esc_html_e('CHIUDI','studio-immens-quick-order');?>
			</div>
		</div>

		<div class="FO_flash_tab_body">
			<div class="fo_tab_prod_index_story" style="display:none;">999999</div>
			<div class="fo_tab_prod_index" style="display:none;">1</div>
			<div class="fo_tab_prod_ghost_draft_index" style="display:none;">1</div>

			<div class="fo_actual_prod" style="display:none;"></div>
			<div class="fo_actual_index" style="display:none;">0</div>
			<div class="fo_actual_index_story" style="display:none;">0</div>

			<div class="fo_actual_table" style="display:none;"></div>
			<div class="fo_woo_symb" style="display:none;"><?php echo esc_attr(get_woocommerce_currency_symbol());?></div>
<!-- COL 1: -->
			<div class="FO_flash_tab_max_column fo_tab_col_1" style="">
	<!-- RIEPILOGO: -->
				<div class="FO_flash_tab_column fo_column_story FO_story_background" style="width:calc(100% - 20px);height:calc(50% - 55px);padding-right:80px;" onclick="siqo_tab_hystory_space(this,'.fo_column_riepilogo', '.fo_column_products')">

					<div class="fo_ghost_draft" style="display:none;"></div>
					<!-- <strong class="fo_title_" style=""><?php esc_html_e('STORICO:','studio-immens-quick-order'); ?></strong> -->
					<?php $abs_total = 0;

					foreach ( $tavoli as $tavolo_key => $tavolo ) { ?>
						<strong class="fo_button fo_story fo_order_table_story fo_tab_prod_story fo_title_all" fotableid="<?php echo esc_attr($tavolo->ID);?>" onclick="siqo_filter_tab_story(this,'all')">
							<?php esc_html_e('STORY','studio-immens-quick-order');?>
						</strong>
						<?php
							$data_table = SIQOP_get_table_by_table_id_last($tavolo->ID);
							$story = ( $data_table != null ) ? json_decode($data_table->orders) : array();
							foreach ((array)$story as $story_v) {
								$order = wc_get_order( absint( $story_v ) );
								if ( ! $order ) { continue; }
								$history_dt = $order->get_date_created();
								if ( ! $history_dt ) {
									$history_dt = $order->get_date_paid();
								}
								$history_date = $history_dt ? $history_dt->date_i18n( 'd/m/Y H:i' ) : '';
								?>
								<div class="fo_button fo_story fo_order_table_story <?php if ($order->get_created_via()=='menu_ajax'){echo 'fo_story_menu_ajax';} ?>" fotableid="<?php echo esc_attr($tavolo->ID);?>"  ondblclick="siqo_change_order_status(<?php echo "'".esc_attr($order->get_id())."'";?>, 'processing' );" onclick="siqo_filter_tab_story(this,<?php echo "'".esc_attr($order->get_id())."'";?>)" fo_order_id="<?php echo esc_attr($order->get_id());?>" fo_order_total="<?php echo esc_attr($order->get_total());?>" fo_order_subtotal="<?php echo esc_attr($order->get_subtotal());?>"
									fo_order_date_created="<?php echo esc_attr($order->get_date_created());?>"
									fo_order_date_modified="<?php echo esc_attr($order->get_date_modified());?>"
									fo_order_date_completed="<?php echo esc_attr($order->get_date_completed());?>"
									fo_order_date_paid="<?php echo esc_attr($order->get_date_paid());?>"
									fo_order_customer_user_agent="<?php echo esc_attr($order->get_customer_user_agent());?>"
									fo_order_created_via="<?php echo esc_attr($order->get_created_via());?>"
									fo_order_customer_note="<?php echo esc_attr($order->get_customer_note());?>"
									fo_order_status="<?php echo esc_attr($order->get_status());?>">
									<span class="siqo-history-id">#<?php echo esc_attr($order->get_id());?></span>
									<span class="siqo-history-date"><?php echo esc_html( $history_date );?></span>
								</div>
								<?php //fo_change_order_status
							}
							foreach ((array)$story as $story_v) {
								$f_i = 200000;
								$order = wc_get_order( absint( $story_v ) );
								if ( ! $order ) { continue; }
								$items = $order->get_items();
								$meta_data = SIQO_get_all_order_meta( $order->get_id() );
								$meta_array = SIQO_extract_meta_object( $meta_data );

								foreach ($meta_array as $meta_index => $meta_value) {
									if (!isset($meta_value['id'])||$meta_value['id'] == '') {continue;}

									$prod_id = $meta_value['id'];
									$qty = (isset($meta_value['qty'])) ? $meta_value['qty'] : '';
									$total = (isset($meta_value['total'])) ? $meta_value['total'] : '';
									$note = (isset($meta_value['note'])) ? $meta_value['note'] : '';
									$modification = (isset($meta_value['modification'])) ? $meta_value['modification'] : '';
									$searched = (isset($meta_value['searched'])) ? $meta_value['searched'] : '';
									$ingredient = (isset($meta_value['ingredient'])) ? $meta_value['ingredient'] : '';
									$allergeni = (isset($meta_value['siqo_allergens'])) ? $meta_value['siqo_allergens'] : '';
									$temperature = (isset($meta_value['siqo_temperatures'])) ? $meta_value['siqo_temperatures'] : '';
									$variant = (isset($meta_value['variant'])) ? $meta_value['variant'] : array();

									// $product = $item->get_product();
									$product = wc_get_product($prod_id);

									if ($product == false||$product == null) {continue;}

									$short_title = (SIQOcheck(get_post_meta($prod_id,'short_title')) && get_post_meta($prod_id,'short_title')[0]!='')?get_post_meta($prod_id,'short_title')[0]:$product->get_name();
									$slang_title = ( SIQOcheck(get_post_meta($prod_id,'slang_title') ) && get_post_meta($prod_id,'slang_title')[0]!='' )?get_post_meta($prod_id,'slang_title')[0]:$short_title;
									$order_index++;
									$style = '';
										if ( SIQOcheck($temperature) || SIQOcheck($note) || SIQOcheck($modification) ) {
											$style = 'background-color:var(--siqo-modified);';
										}
										?>
										<div class="fo_tab_prod fo_story relative <?php if ($order->get_created_via()=='menu_ajax'){echo 'fo_story_menu_ajax';} ?>" foprodid="<?php echo esc_attr($prod_id);?>" foprodtot="<?php echo esc_attr($total);?>" onclick="SIQO_filter_tab_variant(this,<?php echo "'".esc_attr($prod_id)."'"; ?>)" fo_index="<?php echo esc_attr($order_index);?>" fo_index_story="<?php echo esc_attr($story_v);?>" fo_modificable="false" fo_type="story" fotableid="<?php echo esc_attr($tavolo->ID);?>" style="<?php echo esc_attr($style);?>">
											<div class="FO_prod_name_manage fo_text_fix">
												<?php echo esc_attr($slang_title);?>
											</div>
											<?php if ( $modification !== '' ) : ?>
												<div class="siqo-row-item-note"><?php echo esc_html( $modification ); ?></div>
											<?php endif; ?>
											<div class="FO_prod_img" style="border-radius:5px;">
												<?php echo wp_kses_post($product->get_image());?>
											</div>
											<input fo_tab_target="id" type="hidden" name="[<?php echo esc_attr($meta_index);?>][id][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($qty);?>">
											<input fo_tab_target="qty" type="hidden" name="[<?php echo esc_attr($meta_index);?>][qty][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($qty);?>">

											<!-- <input fo_tab_target="siqo_ingredients" type="hidden" name="[<?php echo esc_attr($meta_index);?>][Ingredienti][<?php echo esc_attr($prod_id);?>]" value="<?php ?>"> -->

											<!-- <input fo_tab_target="siqo_allergens" type="hidden" name="[<?php echo esc_attr($meta_index);?>][Allergeni][<?php echo esc_attr($prod_id);?>]" value="<?php ?>"> -->

											<input fo_tab_target="Temperature" type="hidden" name="[<?php echo esc_attr($meta_index);?>][Temperature][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($temperature);?>">

											<input fo_tab_target="price" type="hidden" name="[<?php echo esc_attr($meta_index);?>][price][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($total);?>">

											<input fo_tab_target="note" type="hidden" name="[<?php echo esc_attr($meta_index);?>][note][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($note);?>">
											<input fo_tab_target="item_note" type="hidden" name="[<?php echo esc_attr($meta_index);?>][item_note][<?php echo esc_attr($prod_id);?>]" value="<?php echo esc_attr($modification);?>">
											<?php
								// SIQO_debug($meta_data);
								// SIQO_debug($meta_array);
											foreach ($variant as $vari_key => $vari_val) {
												?>
												<input fo_tab_target="variante" fovariant="<?php echo esc_attr($vari_key);?>" name="[<?php echo esc_attr($meta_index);?>][Variante][<?php echo esc_attr($prod_id);?>][<?php echo esc_attr($vari_key);?>]" type="hidden" value="<?php echo esc_attr($vari_val);?>">
											<?php }  ?>
										</div>
								<?php }

								 foreach ( $order->get_fees() as $item_fee ) {
									$fee_name = $item_fee->get_name();
									$fee_total = $item_fee->get_total();
									$abs_total = floatval($abs_total) + floatval($fee_total);
								?>
									<div class="fo_tab_prod fo_story relative" foprodid="<?php echo esc_attr($fee_name);?>" foprodtot="<?php echo esc_attr($fee_total);?>" onclick="SIQO_filter_tab_variant(this,<?php echo "'".esc_attr($fee_name)."'"; ?>)" fo_index="<?php echo esc_attr($order_index);?>" fo_index_story="<?php echo esc_attr($story_v);?>" fo_modificable="false" fo_type="story" fotableid="<?php echo esc_attr($tavolo->ID);?>">
										<div class="FO_prod_name_manage fo_text_fix">
											<?php echo esc_attr($fee_name);?>
										</div>
										<div class="FO_prod_img" style="border-radius:5px;">
											<img width="300" height="300" src="<?php echo esc_url(wc_placeholder_img_src( 300 )); ?>"><?php  ?>
										</div>
										<input fo_tab_target="id" type="hidden" name="[<?php echo esc_attr($f_i);?>][id][<?php echo esc_attr($fee_name);?>]" value="1">
										<input fo_tab_target="qty" type="hidden" name="[<?php echo esc_attr($f_i);?>][qty][<?php echo esc_attr($fee_name);?>]" value="1">
										<input fo_tab_target="siqo_ingredients" type="hidden" name="[<?php echo esc_attr($f_i);?>][Ingredienti][<?php echo esc_attr($fee_name);?>]" value="">
										<input fo_tab_target="price" type="hidden" name="[<?php echo esc_attr($f_i);?>][price][<?php echo esc_attr($fee_name);?>]" value="<?php echo esc_attr($fee_total);?>">
										<input fo_tab_target="note" type="hidden" name="[<?php echo esc_attr($f_i);?>][note][<?php echo esc_attr($fee_name);?>]" value="">
									</div>
								<?php $f_i++; } ?>
							<?php } ?>
						<?php } ?>
					<div class="fo_tab_tool_section fo_tool_story">
						<div class="fo_button_thin fo_wooprint_preconto" onclick="SIQO_print_receipts(this);" style="color: yellow;">
							<span class="dashicons dashicons-printer"></span>
							<?php esc_html_e('Preconto','studio-immens-quick-order');?>
						</div>

					<?php if ( in_array( 'flash_order_pro/flash_order_pro.php', (array) get_option( 'active_plugins', array() ), true ) ) {
					?>
						<div class="fo_button_thin fo_tab_order_info" onclick="fop_show_order_info_tab()" style="color:lightblue;">
							<span class="dashicons dashicons-welcome-widgets-menus"></span>
							<?php esc_html_e('INFO','studio-immens-quick-order');?>
						</div>
					<?php } ?>

						<div class="fo_tab_tool_total" style="margin-top:auto;">
							<b>Totale:</b>
							<strong> <?php echo esc_attr(SIQO_price($abs_total));?> </strong>
						</div>
					</div>
				</div>
<!-- <strong style=""><?php esc_html_e('RIEPILOGO:','studio-immens-quick-order'); ?></strong> -->
				<div class="FO_flash_tab_column fo_column_riepilogo FO_new_background" style="width:calc(100% - 20px);padding-right:80px;" onclick="siqo_tab_hystory_space(this,'.fo_column_story','.fo_column_products')">
					<div class="fo_tab_tool_section fo_tool_riepilogo">
						<div class="fo_button_thin" onclick="siqo_tab_empty_section(jQuery('.fo_column_riepilogo'))" style="color:red;">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e('TUTTO','studio-immens-quick-order');?>
						</div>
						<div class="fo_tab_tool_total" style="margin-top:auto;">
							<b>Totale:</b>
							<strong> - </strong>
						</div>
						<?php siqo_add_inline_js( <<<'JS'

							jQuery(window).on('load', function() {
								SIQO_calc_totals();
								SIQO_reorder_story();
							});
JS
); ?>
					</div>
				</div>
	<!-- PRODOTTI: -->
				<div class="FO_flash_tab_column fo_column_products" style="width:calc(100% - 20px);" onclick="siqo_tab_hystory_space(this,'.fo_column_story','.fo_column_riepilogo')">
					<!-- <strong style=""><?php esc_html_e('PRODOTTI','studio-immens-quick-order'); ?></strong> -->
					<?php foreach ($products as $key => $value) { ?>
						<?php foreach ($value as $product) {
							$macro_cat = get_the_terms( $product->get_id(), 'siqo_macro_categories');
							if ( empty( $macro_cat ) || is_wp_error( $macro_cat ) ) {
								$macro_cat = get_the_terms( $product->get_id(), 'product_cat');
								if ( empty( $macro_cat ) || is_wp_error( $macro_cat ) ) {
									$macro_cat = array( (object) array( 'slug' => '-BLANK-' ) );
								}
							}
							$display = 'display:none';
							if ( $first_macro != false ) {
								if ( $first_macro->slug == $macro_cat[0]->slug) {
									$display = 'display:block';
								}
							}

							$short_title = (SIQOcheck(get_post_meta($product->get_id(),'short_title')) && get_post_meta($product->get_id(),'short_title')[0]!='')?get_post_meta($product->get_id(),'short_title')[0]:$product->get_name();
							$slang_title = (SIQOcheck(get_post_meta($product->get_id(),'slang_title')) && get_post_meta($product->get_id(),'slang_title')[0]!='' )?get_post_meta($product->get_id(),'slang_title')[0]:$short_title;
							?>
							<div class="fo_tab_prod relative" foprodid="<?php echo esc_attr($product->get_id());?>" foprodtot="<?php echo esc_attr($product->get_price());?>" fomacrocat="<?php echo esc_attr($macro_cat[0]->slug);?>" style="<?php echo esc_attr($display);?>" onclick="SIQO_filter_tab_variant(this,<?php echo "'".esc_attr($product->get_id())."'"; ?>)" fo_index="0" fo_index_story="0" fo_modificable="true" fo_type="new" draggable="true" ontouchmove="touchstartDrag(event,this)" ontouchend="FOtouchdrop(event)" ontouchcancel="FOtouchdrop(event)" ondragstart="FOdrag(event)">
								<span class="fo_tab_prod_remove dashicons dashicons-trash" onclick="" style="display:none;"></span>
								<div class="FO_prod_name_manage fo_text_fix">
									<?php echo esc_attr($slang_title); ?>
								</div>
								<div class="FO_prod_img" style="border-radius:5px;">
									<?php echo wp_kses_post($product->get_image()); ?>
								</div>
								<input fo_tab_target="id" type="hidden" name="[id][<?php echo esc_attr($product->get_id());?>]" value="1">
								<input fo_tab_target="qty" type="hidden" name="[qty][<?php echo esc_attr($product->get_id());?>]" value="1">
								<input fo_tab_target="siqo_ingredients" type="hidden" name="[Ingredienti][<?php echo esc_attr($product->get_id());?>]" value="">
								<input fo_tab_target="Temperature" type="hidden" name="[Temperature][<?php echo esc_attr($product->get_id());?>]" value="">
								<input fo_tab_target="price" type="hidden" name="[price][<?php echo esc_attr($product->get_id());?>]" value="<?php echo esc_attr($product->get_price());?>" regularPrice="<?php echo esc_attr($product->get_price());?>" price_added="">

								<input fo_tab_target="note" type="hidden" name="[note][<?php echo esc_attr($product->get_id());?>]" value="">
								<input fo_tab_target="item_note" type="hidden" name="[item_note][<?php echo esc_attr($product->get_id());?>]" value="">
								<?php
								$product_attr = $product->get_attributes();
								foreach ($product_attr as $k => $v) {
									$attr_key = $k;
									if (siqo_str_contains($k, 'pa_')) {
										$k = str_replace('pa_', '', $k);
									}
									?>
									<input fo_tab_target="variante" fovariant="<?php echo esc_attr($attr_key);?>" name="[Variante][<?php echo esc_attr($product->get_id());?>][<?php echo esc_attr($attr_key);?>]" type="hidden" priceadded="" value="">
								<?php } ?>
							</div>
						<?php } ?>
					<?php } ?>
				</div>
			</div>
<!-- COL 2: -->
	<!-- VARIANTI: -->
			<div class="FO_flash_tab_column fo_tab_col_2" style="">
				<strong class="fo_col_2_name" style=""><?php esc_html_e('VARIANTI:','studio-immens-quick-order'); ?></strong>
				<div class="FO_flash_tab_qty" style="display:none;"> QuantitÃƒÆ’Ã‚Â Ãƒâ€šÃ‚Â 
					<div class="spinner-button-big" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_qty_value(-1)}">-</div>
						<input class="fo_target_qty_prod" type="number" name="qty" step="1" value="1" fo_modificable="" onchange="siqo_tab_parse_qty(this);">
					<div class="spinner-button-big" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_qty_value(+1)}">+</div>
					<?php siqo_add_inline_js( <<<'JS'

						jQuery('.fo_target_qty_prod').on('DOMSubtreeModified', function(){
						  siqo_tab_parse_qty(jQuery('.fo_target_qty_prod'));
						});
JS
); ?>
				</div>
				<?php
				$temperature_terms = SIQO_get_temperature_terms();
				 foreach ($products_w_vari as $key => $value) { ?>
					<?php foreach ($value as $k => $product) {
						$macro_cat = get_the_terms( $product->get_id(), 'siqo_macro_categories');
						$product_child = $product->get_children();
						if ( !empty( $product_child ) && $macro_cat ) { ?>
							<div class="fo_tab_variantcont" fomacrocat="<?php echo esc_attr($macro_cat[0]->slug);?>" foprodidtarget="<?php echo esc_attr($product->get_id());?>" fo_modificable="true" style="display:none;">
							<?php
								$product_attr = $product->get_attributes();
								foreach ($product_attr as $k => $v) {
									$attr_key = $k;
									$attr_options = array();
									if (siqo_str_contains($k, 'pa_')) {
										$k = str_replace('pa_', '', $k);
										$attr_term = 'pa_'.$k;
										if ( taxonomy_exists( $attr_key ) ) {
											$terms = wc_get_product_terms( $product->get_id(), $attr_key, array( 'fields' => 'all' ) );
											foreach ( $terms as $term ) {
												$attr_options[] = array(
													'value' => $term->slug,
													'label' => $term->name,
													'price_to_add' => siqo_get_string_between( $term->description, '[', ']' ),
												);
											}
										}
									} else{
										$attr = explode( ' | ', $product->get_attribute( $k ) );
										foreach ($attr as $opt) {
											$attr_options[] = array(
												'value' => $opt,
												'label' => $opt,
												'price_to_add' => '0.0',
											);
										}
									}
									?>
									<div class="fovariant fovariant_fix" fovariant="<?php echo esc_attr($attr_key);?>">
										<strong style="flex-basis:100%;"><?php echo esc_attr($k);?></strong>
											<?php
										foreach ($attr_options as $opt) {
											$price_to_add = ( $opt['price_to_add'] !== '' && $opt['price_to_add'] !== false ) ? $opt['price_to_add'] : '0.0';
											?>
											<div class="fo_button_thin" fo_prod_id="<?php echo esc_attr($product->get_id());?>" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){jQuery(this).find('input').prop('checked', true);siqo_tab_parse_variant(jQuery(this).find('input'));}" fo_price="<?php echo esc_attr($product->get_price());?>" fo_price_to_add="<?php echo esc_attr($price_to_add);?>"><?php echo esc_html($opt['label']);?>
											<input name="[Variante][<?php echo esc_attr($product->get_id());?>][<?php echo esc_attr($attr_key);?>]" type="radio" value="<?php echo esc_attr($opt['value']);?>" fovariant="<?php echo esc_attr($attr_key);?>">
											</div>
										<?php } ?>
									</div>
								<?php } ?>
							</div>
						<?php } ?>
						<?php
						$temperature = get_the_terms( $product->get_id(), 'siqo_temperatures');
						if ( !empty( $temperature ) && $temperature != null ) { ?>
						<div class="FO_flash_tab_temperature" style="display:none;" foprodidtarget="<?php echo esc_attr($product->get_id());?>" fo_modificable="true" style="display:none;">
							<div class="fovariant fovariant_fix">
								<strong style="flex-basis:100%;"><?php esc_html_e('siqo_temperatures','studio-immens-quick-order');?></strong>
								<?php
								// SIQO_debug($temperature_terms);
								foreach ($temperature as $key => $term) { ?>
									<div class="fo_button_thin" fo_prod_id="<?php echo esc_attr($product->get_id());?>" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){jQuery(this).find('input').prop('checked', true);siqo_tab_parse_temp(jQuery(this).find('input'));}"><?php echo esc_attr($term->name);?>
										<input name="[Temperature][<?php echo esc_attr($product->get_id());?>]" type="radio" value="<?php echo esc_attr($term->slug);?>">
									</div>
								<?php } ?>
							</div>
						</div>
						<?php } ?>
					<?php } ?>
				<?php } ?>

				<div class="FO_flash_tab_note" style="display:none;">
					<strong style="flex-basis:100%;"><?php esc_html_e('Note','studio-immens-quick-order');?></strong>
					<textarea class="fo_target_note_prod" name="note" onchange="siqo_tab_parse_note(this);" placeholder="<?php esc_html_e('Note del prodotto...','studio-immens-quick-order');?>"></textarea>
					<?php siqo_add_inline_js( <<<'JS'

						jQuery('.fo_target_note_prod').on('DOMSubtreeModified', function(){
						  siqo_tab_parse_note(jQuery('.fo_target_note_prod'));
						});
JS
); ?>
				</div>

				<div class="FO_flash_tab_item_note" style="display:none;">
					<strong style="flex-basis:100%;"><?php esc_html_e('Modifiche','studio-immens-quick-order');?></strong>
					<textarea class="fo_target_item_note_prod" oninput="siqo_tab_parse_item_note(this);" onchange="siqo_tab_parse_item_note(this);" placeholder="<?php esc_html_e('Modifiche del prodotto...','studio-immens-quick-order');?>"></textarea>
					<?php siqo_add_inline_js( <<<'JS'

						jQuery('.fo_target_item_note_prod').on('DOMSubtreeModified', function(){
						  siqo_tab_parse_item_note(jQuery('.fo_target_item_note_prod'));
						});
JS
); ?>
				</div>
			</div>
<!-- COL 3: -->
			<div class="FO_flash_tab_column fo_tab_col_3" style="" fo_show="hide">
				<div class="fo_generic_prod" style="display:none!important;">

					<div class="fo_tab_prod fo_tab_prod_special relative" foprodid="Special" foprodtot="0" fomacrocat="Special" onclick="SIQO_filter_tab_variant(this,'Special')" fo_index="0" fo_index_story="0" fo_modificable="true" fo_type="modify" fo_special="Special">
						<span class="fo_tab_prod_remove dashicons dashicons-trash" onclick="" style="display:none;"></span>
						<div class="FO_prod_name_manage fo_text_fix">
							Special
						</div>
						<div class="FO_prod_img" style="border-radius:5px;">
							<img width="300" height="300" src="<?php echo esc_url(wc_placeholder_img_src( 300 )); ?>"><?php  ?>
						</div>
						<input fo_tab_target="id" type="hidden" name="[prod_generic]" value="">
						<input fo_tab_target="qty" type="hidden" name="[qty]" value="1">
						<input fo_tab_target="siqo_ingredients" type="hidden" name="[Ingredienti]" value="">
						<input fo_tab_target="siqo_temperatures" type="hidden" name="[Temperature]" value="">
						<input fo_tab_target="price" type="hidden" name="[price]" value="0" regularPrice="0" price_added="">
						<input fo_tab_target="note" type="hidden" name="[note]" value="">
					</div>

					<div class="fo_tab_prod fo_tab_prod_discount relative" foprodid="Sconto" foprodtot="0" fomacrocat="Special" onclick="SIQO_filter_tab_variant(this,'Sconto')" fo_index="0" fo_index_story="0" fo_modificable="true" fo_type="modify" fo_special="Sconto">
						<span class="fo_tab_prod_remove dashicons dashicons-trash" onclick="" style="display:none;"></span>
						<div class="FO_prod_name_manage fo_text_fix">
							<?php esc_html_e( 'Sconto', 'studio-immens-quick-order' ); ?>
						</div>
						<span class="fo_tab_prod_price"> 0.00 </span>

						<div class="FO_prod_img" style="border-radius:5px;">
							<img width="300" height="300" src="<?php echo esc_url(wc_placeholder_img_src( 300 )); ?>"><?php  ?>
						</div>
						<input fo_tab_target="id" type="hidden" name="[sconto]" value="">
						<input fo_tab_target="qty" type="hidden" name="[qty]" value="1">
						<input fo_tab_target="price" type="hidden" name="[price]" value="0" regularPrice="0" price_added="">
						<input fo_tab_target="note" type="hidden" name="[note]" value="">
					</div>

				</div>
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="siqo_keyboard_special('special','Special')">
							<strong style="height:50px!important;display:flex;flex-direction:column;justify-content:center;">Special</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="siqo_keyboard_special('special','Bar')">
							<strong style="height:50px!important;display:flex;flex-direction:column;justify-content:center;">Bar</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="siqo_keyboard_special('special','Bevande')">
							<strong style="height:50px!important;display:flex;flex-direction:column;justify-content:center;">Bevande</strong>
						</div>
					</div>

				<div class="fo_tab_calc_display">
					<span class="fo_tab_price" fo_status_modify="false" fo_actual_prod="" fo_actual_index="" fo_modificable="" onchange="siqo_tab_parse_price(this)"></span>
					<?php siqo_add_inline_js( <<<'JS'

						jQuery('.fo_tab_price').on('DOMSubtreeModified', function(){
						  siqo_tab_parse_price(jQuery('.fo_tab_price'));
						});
JS
); ?>
					<!-- <div class="fo_tab_calc_keyboard_key fo_tab_add_to_order" onclick="siqo_tab_add_product_to_order()">
						<strong>AGGIUNGI</strong>
					</div> -->
				</div>


				<div class="fo_tab_calc_keyboard">

					<!-- <div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="siqo_keyboard_special('sub')">
							<strong>SUB</strong>
						</div>
						<div class="fo_tab_calc_keyboard_special" onclick="siqo_keyboard_special('-')">
							<strong>( - )</strong>
						</div>
						<div class="fo_tab_calc_keyboard_special" onclick="siqo_keyboard_special('%')">
							<strong>( % )</strong>
						</div>
					</div> -->
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('7')}">
							<strong>7</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('8')}">
							<strong>8</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('9')}">
							<strong>9</strong>
						</div>
					</div>
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('4')}">
							<strong>4</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('5')}">
							<strong>5</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('6')}">
							<strong>6</strong>
						</div>
					</div>
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('1')}">
							<strong>1</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('2')}">
							<strong>2</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('3')}">
							<strong>3</strong>
						</div>
					</div>
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('.')}">
							<strong>.</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('0')}">
							<strong>0</strong>
						</div>
						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_keyboard_price('.')}">
							<strong>.</strong>
						</div>
					</div>
					<div class="fo_tab_calc_keyboard_row">
						<div class="fo_tab_calc_keyboard_key fo_tab_reset_price" fovalue="" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_tab_price_default(this);}">
							<span class="dashicons dashicons-image-rotate"></span>
						</div>

						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_tab_price_del();}">
							<span class="dashicons dashicons-minus"></span>
						</div>

						<div class="fo_tab_calc_keyboard_key" onclick="if (jQuery('.fo_target_qty_prod').attr('fo_modificable') == 'true' ){siqo_tab_price_ac();}">
							<span class="dashicons dashicons-no"></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		if ( function_exists('FOP_order_info_tab') ) {
			FOP_order_info_tab();
		}?>
		<div class="FO_flash_tab_footer">
<div class="fo_table_name" role="button" tabindex="0" aria-label="<?php esc_attr_e('Chiudi il tavolo','studio-immens-quick-order'); ?>" onclick="SIQO_tab_Card_hide()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_tab_Card_hide();}">
<strong> <?php esc_html_e('TAVOLO: ','studio-immens-quick-order'); ?> </strong>
<strong class="fo_tab_table_name"> - - - - </strong>
</div>

<div class="fo_button_fix fo_clear_table_button siqo-secondary" style="margin: 0 auto 0 15px;" role="button" tabindex="0" onclick="SIQO_tab_clear_table()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_tab_clear_table();}"><?php esc_html_e('LIBERA TAVOLO','studio-immens-quick-order');?></div>
<!-- <textarea class="fo_tab_fix_order_note" name="order_note" placeholder="Note..." style=""></textarea> -->
<div class="fo_button_fix fo_pay_table_button siqo-secondary" style="margin-left:auto;display:none;" fotableid="" role="button" tabindex="0" onclick="SIQO_pay_tab_Card_show(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_pay_tab_Card_show(this);}">
<?php esc_html_e('PAGA TAVOLO','studio-immens-quick-order');?>
</div>

<?php SIQO_pay_this_order_tab();?>

<div class="fo_button_fix siqo-primary" style="margin-left:auto;" role="button" tabindex="0" fo_order_type="new_order" onclick="SIQO_tab_go_order(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_tab_go_order(this);}"><?php esc_html_e('AVVIA ORDINE','studio-immens-quick-order');?></div>

<div class="fo_button_fix fo_variant_show_button" style="bottom:220px!important;" role="button" tabindex="0" aria-label="<?php esc_attr_e('Apri varianti','studio-immens-quick-order'); ?>" onclick="SIQO_tab_show_variation_button()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_tab_show_variation_button();}">
<span class="dashicons dashicons-table-col-after" aria-hidden="true"></span>
</div>
<div class="fo_button_fix fo_pay_show_keyboard_button" style="" role="button" tabindex="0" aria-label="<?php esc_attr_e('Apri tastierino quantitÃƒÆ’Ã‚Â ','studio-immens-quick-order'); ?>" onclick="SIQO_tab_show_keyboard_button()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();SIQO_tab_show_keyboard_button();}">
<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
</div>

			<input id="table_name" type="hidden" name="table_name_cpt" value="">
			<input type="hidden" name="table_ID" value="">
			<input type="hidden" name="table_total" value="">

			<input type="hidden" name="table_start_time" value="">
			<input type="hidden" name="table_last_update" value="">
			<input type="hidden" name="table_orders" value="">
			<input type="hidden" name="table_info" value="">
			<input type="hidden" name="table_receipt" value="">
			<input type="hidden" name="table_other" value="">
			<input type="hidden" name="table_totals" value="">
			<input type="hidden" name="table_table_tableid" value="">
		</div>

	</div>
	<div class="Advanced_Card_background fo_tab_show" style="display:none;"></div>
	<?php siqo_add_inline_css( '#eltdf-back-to-top{visibility:hidden!important;}' ); ?>

	<?php
}






function SIQO_get_temperature_terms(){
	// $return = get_taxonomy('siqo_temperatures');
	$return = get_terms( array(
	    'taxonomy'   => 'siqo_temperatures',
	    'hide_empty' => false,
	) );
	return $return;
}






function SIQO_get_pay_coupons() {
	if ( ! class_exists( 'WC_Coupon' ) || ! function_exists( 'wc_get_coupon_types' ) ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$coupons = array();
	foreach ( $posts as $post ) {
		$coupon = new WC_Coupon( $post->ID );
		if ( ! $coupon->get_id() ) {
			continue;
		}

		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			continue;
		}

		$usage_limit = (int) $coupon->get_usage_limit();
		if ( $usage_limit > 0 && (int) $coupon->get_usage_count() >= $usage_limit ) {
			continue;
		}

		$type   = $coupon->get_discount_type();
		$amount = (float) $coupon->get_amount();

		if ( 'percent' === $type ) {
			$label = wc_format_decimal( $amount, 2 ) . '%';
		} elseif ( 'free_shipping' === $type ) {
			$label = __( 'Spedizione gratuita', 'studio-immens-quick-order' );
		} elseif ( function_exists( 'wc_price' ) ) {
			$label = wp_strip_all_tags( wc_price( $amount ) );
		} else {
			$label = wc_format_decimal( $amount, 2 );
		}

		$emails = array();
		foreach ( (array) $coupon->get_email_restrictions() as $email ) {
			$email = strtolower( trim( (string) $email ) );
			if ( '' !== $email ) {
				$emails[] = $email;
			}
		}

		$coupons[] = array(
			'code'              => $coupon->get_code(),
			'type'              => $type,
			'amount'            => $amount,
			'label'             => $label,
			'description'       => $coupon->get_description(),
			'emails'            => array_values( array_unique( $emails ) ),
			'minimum'           => (float) $coupon->get_minimum_amount(),
			'maximum'           => (float) $coupon->get_maximum_amount(),
			'products'          => array_values( array_filter( array_map( 'absint', (array) $coupon->get_product_ids() ) ) ),
			'excluded_products' => array_values( array_filter( array_map( 'absint', (array) $coupon->get_excluded_product_ids() ) ) ),
			'expires'           => $expires ? $expires->getTimestamp() : 0,
		);
	}

	return $coupons;
}

function SIQO_pay_coupon_button_html( $coupon ) {
	$title = $coupon['description'] ? $coupon['description'] : sprintf(
		/* translators: %s: coupon code */
		__( 'Applica il coupon %s', 'studio-immens-quick-order' ),
		$coupon['code']
	);

	ob_start();
	?>
	<button type="button" class="siqo-pay-coupon" data-siqo-coupon
		data-code="<?php echo esc_attr( $coupon['code'] ); ?>"
		data-type="<?php echo esc_attr( $coupon['type'] ); ?>"
		data-amount="<?php echo esc_attr( $coupon['amount'] ); ?>"
		data-label="<?php echo esc_attr( $coupon['label'] ); ?>"
		data-emails="<?php echo esc_attr( wp_json_encode( $coupon['emails'] ) ); ?>"
		data-minimum="<?php echo esc_attr( $coupon['minimum'] ); ?>"
		data-maximum="<?php echo esc_attr( $coupon['maximum'] ); ?>"
		data-products="<?php echo esc_attr( wp_json_encode( $coupon['products'] ) ); ?>"
		data-excluded-products="<?php echo esc_attr( wp_json_encode( $coupon['excluded_products'] ) ); ?>"
		data-expires="<?php echo esc_attr( $coupon['expires'] ); ?>"
		title="<?php echo esc_attr( $title ); ?>"
		aria-pressed="false">
		<span class="siqo-pay-coupon-code"><?php echo esc_html( $coupon['code'] ); ?></span>
		<span class="siqo-pay-coupon-meta"><?php echo esc_html( $coupon['label'] ); ?></span>
		<span class="siqo-pay-coupon-action"><?php esc_html_e( 'Aggiungi', 'studio-immens-quick-order' ); ?></span>
	</button>
	<?php
	return ob_get_clean();
}

function SIQO_pay_this_order_tab(){
	$siqo_pay_coupons    = SIQO_get_pay_coupons();
	$siqo_general_coupons = array();
	$siqo_customer_coupons = array();
	foreach ( $siqo_pay_coupons as $siqo_pay_coupon ) {
		if ( empty( $siqo_pay_coupon['emails'] ) ) {
			$siqo_general_coupons[] = $siqo_pay_coupon;
		} else {
			$siqo_customer_coupons[] = $siqo_pay_coupon;
		}
	}
?>
	<div class="FO_flash_tab_order_pay_container fo_flex" style="display:none;">

		<div class="FOloadingCardPublic tab_fix_load" style="display:none;">
			<span style="animation: fospin 1s infinite;font-size:120px;width:120px;height:120px;" class="dashicons dashicons-update"></span>
		</div>

		<div class="fo_pay_header">
			<div class="fo_pay_header_copy">
				<span class="fo_pay_kicker"><?php esc_html_e('Pagamento tavolo','studio-immens-quick-order');?></span>
				<h2><?php esc_html_e('Chiudi il conto','studio-immens-quick-order');?></h2>
				<p><?php esc_html_e('Controlla gli articoli, associa un cliente se serve e completa il pagamento.','studio-immens-quick-order');?></p>
			</div>
			<button type="button" class="fo_pay_close" onclick="SIQO_pay_tab_Card_hide()">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span><?php esc_html_e('Chiudi','studio-immens-quick-order');?></span>
			</button>
		</div>

		<div class="fo_pay_column fo_pay_ajax_container">
			<div class="fo_pay_section_head">
				<div>
					<span class="fo_pay_section_kicker"><?php esc_html_e('Conto','studio-immens-quick-order');?></span>
					<h3><?php esc_html_e('Articoli ordinati','studio-immens-quick-order');?></h3>
				</div>
				<span class="fo_pay_count" aria-label="<?php esc_attr_e('Numero articoli','studio-immens-quick-order');?>"></span>
			</div>
			<div class="fo_flex fo_list_items">
			</div>
			<div class="fo_flex fo_list_final_order">
			</div>
		</div>

		<div class="fo_pay_column fo_pay_side">
			<div class="fo_pay_section_head">
				<div>
					<span class="fo_pay_section_kicker"><?php esc_html_e('Anagrafica','studio-immens-quick-order');?></span>
					<h3><?php esc_html_e('Cliente','studio-immens-quick-order');?></h3>
				</div>
				<span class="fo_pay_optional"><?php esc_html_e('Opzionale','studio-immens-quick-order');?></span>
			</div>
			<div class="fo_pay_body fo_flex">
				<p class="fo_pay_hint"><?php esc_html_e('Puoi collegare un cliente esistente oppure inserire i dati del nuovo cliente.','studio-immens-quick-order');?></p>
				<div class="fo_pay_customer_type">
					<span><?php esc_html_e('Cliente esistente','studio-immens-quick-order');?></span>
					<input type="checkbox" name="customer_type" onclick="SIQO_customer_pay(this);" unchecked>
				</div>

				<div class="fot_pay_new_customer">
					<strong><?php esc_html_e('Nuovo cliente','studio-immens-quick-order');?></strong>
					<input class="fot_pay_input N_customer_name" type="text" name="[customer_name][]" placeholder="<?php esc_html_e('Nome cliente','studio-immens-quick-order');?>">
					<input class="fot_pay_input N_customer_mail" type="email" name="[customer_mail][]" placeholder="<?php esc_html_e('Email cliente','studio-immens-quick-order');?>">
					<input class="fot_pay_input N_customer_phone" type="tel" name="[customer_phone][]" placeholder="<?php esc_html_e('Telefono cliente','studio-immens-quick-order');?>">
					<button type="button" class="fo_pay_secondary" onclick="SIQO_add_new_customer(jQuery(this).parent());"><?php esc_html_e('Aggiungi cliente','studio-immens-quick-order');?></button>
				</div>

				<div class="fot_pay_search_customer" style="display:none;">
					<strong><?php esc_html_e('Cerca un cliente','studio-immens-quick-order');?></strong>
					<input type="search" onkeyup="SIQO_search_for_target(this,'.FO_customer_target_search');jQuery('.FO_customer_list').show()" onclick="SIQO_search_for_target(this,'.FO_customer_target_search');jQuery('.FO_customer_list').show()" class="focatsearchall FO_list_height" style="width: 200px!important;" placeholder="<?php esc_html_e('Cerca cliente...','studio-immens-quick-order' );?>">
					<div class="FO_clear_input" onclick="jQuery(this).prev().val('');jQuery(this).next().hide();">X</div>

					<div class="FO_customer_list" style="display:none;">
					<?php
						$users = get_users(array( 'fields' => array( 'ID','display_name','user_email','user_registered' ) ) );
						// SIQO_debug($users);
						foreach ($users as $key => $value) {
							$u_id = ( SIQOcheck( $value->ID) ) ? $value->ID : ' - ';
							$u_name = ( SIQOcheck( $value->display_name ) ) ? $value->display_name : ' - ';
							$u_mail = ( SIQOcheck( $value->user_email) ) ? $value->user_email : ' - ';
							$u_phone = ( SIQOcheck( get_user_meta( $u_id, 'billing_phone', true ) ) ) ? get_user_meta( $u_id, 'billing_phone', true ) : ' - ';

							$u_since = ( SIQOcheck( $value->user_registered) ) ? $value->user_registered : '';
							$u_aurl = '';

						?>
						<div class="FO_customer_target_search FO_list_prod_summ" style="display:none;" onclick="SIQO_pay_customer_list(this);" fo_u_id="<?php echo esc_attr($u_id);?>" fo_u_name="<?php echo esc_attr($u_name);?>" fo_u_mail="<?php echo esc_attr($u_mail);?>" fo_u_phone="<?php echo esc_attr($u_phone);?>" fo_u_since="<?php echo esc_attr($u_since);?>" fo_u_aurl="<?php echo esc_attr($u_aurl);?>">
							<div class="foname">
								<div> <?php echo esc_attr($u_id);?> </div>
								<div> <?php echo esc_attr($u_name);?> </div>
								<div> <?php echo esc_attr($u_mail);?> </div>
								<div> <?php echo esc_attr($u_phone);?> </div>
							</div>
							<div class="FO_clear_target" style="display:none;" onclick="">X</div>
							<input type="hidden" name="[customer_ID][<?php echo esc_attr($u_id);?>]" value="<?php echo esc_attr($u_id);?>">
							<input type="hidden" name="[customer_name][<?php echo esc_attr($u_id);?>]" value="<?php echo esc_attr($u_name);?>">
							<input type="hidden" name="[customer_mail][<?php echo esc_attr($u_id);?>]" value="<?php echo esc_attr($u_mail);?>">
							<input type="hidden" name="[customer_phone][<?php echo esc_attr($u_id);?>]" value="<?php echo esc_attr($u_phone);?>">
						</div>
						<?php
						}
					?>
					</div>

				</div>
			</div>

			<div class="FO_example FO_pay_customer_card FO_exemple_customer_card" style="display:none;">
				<span class="fo_tab_remove dashicons dashicons-trash" onclick="SIQO_remove_pay_customer(jQuery(this));"></span>
				<span class="FO_pay_customer_id"> <?php esc_html_e('NUOVO!','studio-immens-quick-order'); ?> </span>
				<span class="FO_pay_customer_name"> name </span>
				<span class="FO_pay_customer_mail"> mail@gmail.com </span>
				<span class="FO_pay_customer_phone"> 123456789 </span>
			</div>

			<div class="FO_customers_container">
			</div>

			<div class="siqo-pay-discounts" data-siqo-pay-discounts>
				<div class="fo_pay_section_head">
					<div>
						<span class="fo_pay_section_kicker"><?php esc_html_e( 'Sconti', 'studio-immens-quick-order' ); ?></span>
						<h3><?php esc_html_e( 'Coupon e sconti', 'studio-immens-quick-order' ); ?></h3>
					</div>
					<span class="fo_pay_optional" data-siqo-pay-discount-count><?php echo esc_html( count( $siqo_pay_coupons ) ); ?></span>
				</div>

				<p class="fo_pay_hint"><?php esc_html_e( 'Aggiungi uno o piÃƒÆ’Ã‚Â¹ coupon. Se selezioni un cliente, vedrai anche gli sconti associati al suo indirizzo email.', 'studio-immens-quick-order' ); ?></p>

				<div class="siqo-pay-coupon-search">
					<input type="search" data-siqo-pay-coupon-search placeholder="<?php esc_attr_e( 'Cerca un couponÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦', 'studio-immens-quick-order' ); ?>" aria-label="<?php esc_attr_e( 'Cerca un coupon', 'studio-immens-quick-order' ); ?>">
				</div>

				<div class="siqo-pay-applied" data-siqo-pay-applied hidden>
					<span class="siqo-pay-applied-label"><?php esc_html_e( 'Applicati', 'studio-immens-quick-order' ); ?></span>
					<div class="siqo-pay-applied-list" data-siqo-pay-applied-list></div>
				</div>

				<div class="siqo-pay-coupon-group" data-siqo-pay-coupon-customer hidden>
					<div class="siqo-pay-coupon-group-head">
						<span><?php esc_html_e( 'Sconti del cliente', 'studio-immens-quick-order' ); ?></span>
						<span class="siqo-pay-coupon-group-count" data-siqo-pay-customer-count></span>
					</div>
					<div class="siqo-pay-coupon-list">
						<?php foreach ( $siqo_customer_coupons as $siqo_pay_coupon ) : ?>
							<?php echo wp_kses( SIQO_pay_coupon_button_html( $siqo_pay_coupon ), siqo_allowed_html() ); ?>
						<?php endforeach; ?>
					</div>
					<p class="siqo-pay-coupon-empty" data-siqo-pay-coupon-customer-empty hidden><?php esc_html_e( 'Nessuno sconto associato a questo cliente.', 'studio-immens-quick-order' ); ?></p>
				</div>

				<div class="siqo-pay-coupon-group" data-siqo-pay-coupon-available>
					<div class="siqo-pay-coupon-group-head">
						<span><?php esc_html_e( 'Coupon disponibili', 'studio-immens-quick-order' ); ?></span>
						<span class="siqo-pay-coupon-group-count" data-siqo-pay-general-count></span>
					</div>
					<div class="siqo-pay-coupon-list">
						<?php foreach ( $siqo_general_coupons as $siqo_pay_coupon ) : ?>
							<?php echo wp_kses( SIQO_pay_coupon_button_html( $siqo_pay_coupon ), siqo_allowed_html() ); ?>
						<?php endforeach; ?>
					</div>
					<p class="siqo-pay-coupon-empty" data-siqo-pay-coupon-empty <?php echo empty( $siqo_general_coupons ) ? '' : 'hidden'; ?>><?php esc_html_e( 'Nessun coupon disponibile.', 'studio-immens-quick-order' ); ?></p>
				</div>

				<p class="siqo-pay-coupon-message" data-siqo-pay-coupon-message hidden></p>
			</div>

		</div>
<?php /*
				<!-- <div class="FO_table_input" style="border:1px solid #ffffff6b;"> -->
				<!-- Prodotto Generico -->
					<!-- <input type="text" value="<?php esc_html_e('Special','studio-immens-quick-order');?>" style="flex-basis:100%;" onkeyup="jQuery(this).parent().find('.final_input').attr('name','[prod_generic]['+jQuery(this).val()+']');jQuery(this).parent().find('.foname').text(jQuery(this).val())">
					<div class="spinner-button-big" onclick="SIQO_add_value_to_input(-1.00,'.fo_target_discount_prod')">-1</div>
					<div class="spinner-button-big" onclick="jQuery(this).next().val(parseFloat(parseFloat(jQuery(this).next().val()) - 0.10).toFixed(2))">-</div>
						<input id="discount" class="fo_target_discount_prod" type="number" name="discount" value="0">
					<div class="spinner-button-big" onclick="jQuery(this).prev().val(parseFloat(parseFloat(jQuery(this).prev().val()) + 0.10).toFixed(2))">+</div>
					<div class="spinner-button-big" onclick="SIQO_add_value_to_input(1.00,'.fo_target_discount_prod')">+1</div> -->
					<!-- <div class="FO_list_prod_summ" style="display:none;" siqoprice="">
						<div class="foname"><?php esc_html_e('Prodotto Generico','studio-immens-quick-order');?></div>
						<input class="final_input" type="hidden" name="[prod_generic][Prodotto Generico]" value="">
						<div class="FO_list_price"></div>
						<div class="FO_clear_target" onclick="SIQO_order_list_remove(jQuery(this).parent());jQuery(this).parent().remove();">X</div>
					</div> -->
					<!-- <div class="spinner-button-big" style="font-size:15px!important;" onclick="SIQO_order_discount_list(this,'.fo_target_discount_prod');"><?php esc_html_e('AGGIUNGI','studio-immens-quick-order');?></div> -->
				<!-- </div> -->

				<!-- <div class="fo_flex" style="border:1px solid #ffffff6b;">
					<span style="flex-basis:100%;">
						<?php esc_html_e('prezzo / sconto','studio-immens-quick-order');?>
					</span>
					<div class="spinner-button-big" onclick="SIQO_add_value_to_input(-1.00,'.fo_target_discount')">-1</div>
					<div class="spinner-button-big" onclick="jQuery(this).next().val(parseFloat(parseFloat(jQuery(this).next().val()) - 0.10).toFixed(2))">-</div>
						<input id="discount" class="fo_target_discount" type="number" name="discount"  value="0">
					<div class="spinner-button-big" onclick="jQuery(this).prev().val(parseFloat(parseFloat(jQuery(this).prev().val()) + 0.10).toFixed(2))">+</div>
					<div class="spinner-button-big" onclick="SIQO_add_value_to_input(1.00,'.fo_target_discount')">+1</div>
					<div class="FO_list_prod_summ" style="display:none;" siqoprice="">
						<div class="foname"><?php echo '- prezzo / sconto -';?></div>
						<input class="final_input" type="hidden" name="[sconto][]" value="">
						<div class="FO_list_price"></div>
						<div class="FO_clear_target" onclick="SIQO_order_list_remove(jQuery(this).parent());jQuery(this).parent().remove();">X</div>
					</div>
					<div class="spinner-button-big" style="font-size:15px!important;" onclick="SIQO_order_discount_list(this,'.fo_target_discount');"><?php esc_html_e('AGGIUNGI','studio-immens-quick-order');?></div>
				</div> -->
*/ ?>
		<div class="fo_pay_footer">
			<div class="fo_pay_total_block">
				<span class="fo_pay_total_label"><?php esc_html_e('Totale da pagare','studio-immens-quick-order');?></span>
				<span class="siqo-pay-discount-line" data-siqo-pay-discount-line hidden></span>
				<span class="fo_pay_total_line">
					<strong class="fo_pay_total"></strong>
				</span>
			</div>

			<div class="fo_pay_footer_actions">
				<button type="button" class="fo_pay_preconto fo_wooprint_preconto" onclick="SIQO_print_receipts(this);">
					<span class="dashicons dashicons-printer" aria-hidden="true"></span>
					<span><?php esc_html_e('Preconto','studio-immens-quick-order');?></span>
				</button>

				<button type="button" class="fo_button_fix fo_pay_table_button fo_pay_primary" onclick="SIQO_order_tab_pay_ajax()">
					<span><?php esc_html_e('Paga tavolo','studio-immens-quick-order');?></span>
					<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				</button>
			</div>
		</div>

	</div>
	<div class="Advanced_Card_background fo_pay_tab_show" style="display:none;"></div>
<?php
}











// add_filter('woocommerce_rest_check_permissions', 'my_woocommerce_rest_check_permissions', 90, 4);
// function my_woocommerce_rest_check_permissions($permission, $context, $object_id, $post_type) {
// 	// if($_GET['consumer_key']=='ck_2bdb98b40c89c0314a683f8eda705364563b7ed5' && $_GET['consumer_secret']=='cs_684407889915a2d4fe8a840cf4b12ddc1fd7f3f1') {
// 		return true;
// 	// }
// 	// return $permission;
// }

// wp_insert_term(
//   'New Category', // the term
//   'product_cat', // the taxonomy
//   array(
//     'description'=> 'Category description',
//     'slug' => 'new-category'
//   )
// );


function siqo_ajax_require_cap( $capability ) {
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( $capability ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
}

function siqo_ajax_post_capability( $post_type ) {
	if ( in_array( $post_type, array( 'siqo_catering', 'catering' ), true ) ) {
		return 'manage_catering';
	}
	if ( in_array( $post_type, array( 'siqo_tables', 'tavoli' ), true ) ) {
		return 'manage_tables';
	}
	return '';
}

function SIQO_insert_product_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( 'manage_inventory' );
	if (!isset($_POST['title']) || $_POST['title'] == '' ) {
	    return;
	}

	$title = (isset($_POST['title'])) ? sanitize_text_field(wp_unslash( $_POST['title'])) : '';
	$sh_title = (isset($_POST['sh_title'])) ? sanitize_text_field(wp_unslash( $_POST['sh_title'])) : '';
	$sl_title = (isset($_POST['sl_title'])) ? sanitize_text_field(wp_unslash( $_POST['sl_title'])) : '';

	$price = (isset($_POST['price'])) ? sanitize_text_field(wp_unslash( $_POST['price'])) : '0';
	$cat = (isset($_POST['cat'])) ? sanitize_text_field(wp_unslash( $_POST['cat'])) : '';
	$variant = (isset($_POST['variant'])) ? sanitize_text_field(wp_unslash( $_POST['variant'])) : '';

	$description = (isset($_POST['description'])) ? sanitize_text_field(wp_unslash( $_POST['description'])) : '';
	$exerpt = (isset($_POST['exerpt'])) ? sanitize_text_field(wp_unslash( $_POST['exerpt'])) : '';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload is validated by wp_check_filetype_and_ext() and media_handle_upload().
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload is validated by SIQO_load_images_from_front().
	$image = ( isset( $_FILES['image'] ) && is_array( $_FILES['image'] ) ) ? $_FILES['image'] : array();

	$product = new WC_Product_Simple();

	$product->set_name( $title );
	$product->set_status( 'publish' );

	$product->set_regular_price( $price );
	// $product->set_sale_price( 250.00 );
	// $product->set_category_ids( array( $cat ) );

	$product->set_short_description($exerpt);

	$resp = SIQO_load_images_from_front( $image );
	if ( is_array( $resp ) && isset( $resp['siqo_table_status'] ) && 'success' === $resp['siqo_table_status'] ) {
		$product->set_image_id( (int) $resp['id'] );
	}

// $product->set_image_id( 90 );
// $product->set_stock_status( 'instock' ); // 'instock', 'outofstock' or 'onbackorder'
// $product->set_manage_stock( true );
// $product->set_stock_quantity( 5 );
// $product->set_backorders( 'no' ); // 'yes', 'no' or 'notify'
// $prodict->set_low_stock_amount( 2 );
// $product->set_sold_individually( true );

// $product->set_weight( 0.5 );
// $product->set_length( 50 );
// $product->set_width( 50 );
// $product->set_height( 30 );
// $product->set_shipping_class_id( 'hats-shipping' );


	$product->save();

	wp_set_post_terms( $product->get_id(), array( $cat ), 'siqo_macro_categories', false );

	update_post_meta( $product->get_id(), 'short_title', $sh_title );
	update_post_meta( $product->get_id(), 'slang_title', $sl_title );

	// update_post_meta( $post_id, '_stock', '' );

	// $product_id = wp_insert_term(
	// 	sanitize_text_field(wp_unslash($_POST['name'])), // the term
	// 	'siqo_macro_categories', // the taxonomy
	// 	// array(
	// 	// 	'description'=> (isset($_POST['description']))?sanitize_text_field(wp_unslash($_POST['description'])):'',
	// 	// 	'slug' => 'new-category'
	// 	// )
	// );
	wp_send_json(array(
		//'post' => $_POST,
		'product_id' => $product->get_id(),
	));
	die();
}


function SIQO_load_images_from_front( $in_file ){
	$response = array(
		'siqo_table_status' => 'error',
		'message' => __( 'File non valido.', 'studio-immens-quick-order' ),
	);

	if ( ! is_array( $in_file ) || empty( $in_file['name'] ) || ! isset( $in_file['error'], $in_file['size'], $in_file['tmp_name'] ) ) {
		return $response;
	}

	$allowedExts = array( 'png', 'jpg', 'webp' );
	$extension = strtolower( pathinfo( $in_file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $extension, $allowedExts, true ) ) {
		return $response;
	}

	if ( (int) $in_file['error'] !== UPLOAD_ERR_OK ) {
		$response['message'] = 'ERROR Return Code: ' . (int) $in_file['error'];
		return $response;
	}

	if ( (int) $in_file['size'] <= 0 || (int) $in_file['size'] > 3145728 ) {
		$response['message'] = __( 'File troppo grande.', 'studio-immens-quick-order' );
		return $response;
	}

	$file_check = wp_check_filetype_and_ext( $in_file['tmp_name'], $in_file['name'] );
	if ( empty( $file_check['type'] ) || ! in_array( $file_check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return $response;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$movefile = wp_handle_upload( $in_file, array( 'test_form' => false ) );
	if ( ! is_array( $movefile ) || isset( $movefile['error'] ) ) {
		$response['message'] = is_array( $movefile ) && isset( $movefile['error'] ) ? $movefile['error'] : __( 'Caricamento non riuscito.', 'studio-immens-quick-order' );
		return $response;
	}

	$attachment = array(
		'post_mime_type' => $movefile['type'],
		'post_title' => sanitize_file_name( $in_file['name'] ),
		'post_content' => '',
		'post_status' => 'inherit',
	);
	$attach_id = wp_insert_attachment( $attachment, $movefile['file'], 0 );
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		$response['message'] = __( 'Impossibile creare lÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢allegato.', 'studio-immens-quick-order' );
		return $response;
	}

	$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return array(
		'siqo_table_status' => 'success',
		'id' => (int) $attach_id,
		'url' => $movefile['url'],
	);
}


function SIQO_insert_macro_cat_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( 'manage_inventory' );
	// if (isset($_POST['term_id']) && $_POST['term_id'] != '' ) {
	//     SIQO_update_post_ajax( $_POST );
	//     return;
	// }
	if (!isset($_POST['name']) || $_POST['name'] == '' ) {
	    return;
	}
	$term_id = wp_insert_term(
		sanitize_text_field(wp_unslash($_POST['name'])), // the term
		'siqo_macro_categories', // the taxonomy
		// array(
		// 	'description'=> (isset($_POST['description']))?sanitize_text_field(wp_unslash($_POST['description'])):'',
		// 	'slug' => 'new-category'
		// )
	);
	wp_send_json(array(
		//'post' => $_POST,
		'term_id' => $term_id,
	));
	die();
}

function SIQO_delete_macro_cat_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( 'manage_inventory' );
//_fononce_stat_update_nonce: jQuery('input[name="_fononce_stat_update_nonce"]').val(),

	if (isset($_POST['delete_id'])) {
		$post = wp_delete_term( (int)sanitize_text_field(wp_unslash($_POST['delete_id'])),'siqo_macro_categories' );
	} else { $post = ''; }

	wp_send_json(array(
		//'post' => $_POST,
		'post' => $post,
	));
	die();
}





function SIQO_insert_post_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if (isset($_POST['post_id']) && $_POST['post_id'] != '' ) {
	    SIQO_update_post_ajax( $_POST );
	    return;
	}
	$cpt = (isset($_POST['cpt'])) ? sanitize_key( wp_unslash( $_POST['cpt'] ) ) : '';
	if ( 'catering' === $cpt ) {
		$cpt = 'siqo_catering';
	}
	if ( 'tavoli' === $cpt ) {
		$cpt = 'siqo_tables';
	}
	$capability = siqo_ajax_post_capability( $cpt );
	if ( ! $capability ) {
		wp_send_json_error( array( 'message' => __( 'Tipo di contenuto non consentito.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( $capability );
	$post_id = wp_insert_post(array (
	    'post_type' => $cpt,
	    'post_title' => (isset($_POST['title']))?sanitize_text_field(wp_unslash($_POST['title'])):'',
	    'post_content' => (isset($_POST['content']))?sanitize_text_field(wp_unslash($_POST['content'])):'',
	    'post_status' => 'publish',
	));
	if ($post_id) {
	    add_post_meta($post_id, 'from_user_id', get_current_user_id());
	    if (isset($_POST['fo_delivery_date'])) {
	    	add_post_meta($post_id, 'fo_delivery_date', sanitize_text_field(wp_unslash($_POST['fo_delivery_date'])));
	    }
	    if (isset($_POST['fo_catering_status'])) {
	    	add_post_meta($post_id, 'fo_catering_status', sanitize_text_field(wp_unslash($_POST['fo_catering_status'])));
	    }
	}
	wp_send_json(array(
		//'post' => $_POST,
		'post_id' => $post_id,
	));
	die();
}


function SIQO_update_post_macro_cat_ajax( $_poste = '' ){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( 'manage_inventory' );
	if ($_poste != '') {
		$_POST = $_poste;
	}
	if (isset($_POST['macro_category_check']) && sanitize_text_field(wp_unslash($_POST['macro_category_check'])) != 'siqo_macro_categories' ) {
		return;
	}
	$post_id = (isset($_POST['post_id'])) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
	$macro_category = (isset($_POST['macro_category'])) ? sanitize_text_field(wp_unslash($_POST['macro_category'])) : '';

	$term_id = wp_set_post_terms( $post_id, $macro_category, 'siqo_macro_categories', false );

	wp_send_json(array(
		//'post' => $_POST,
		'term_id' => $term_id,
	));
	die();
}


function SIQO_remove_post_macro_cat_ajax( $_poste = '' ){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( 'manage_inventory' );
	if ($_poste != '') {
		$_POST = $_poste;
	}
	if (isset($_POST['macro_category_check']) && sanitize_text_field(wp_unslash($_POST['macro_category_check'])) != 'siqo_macro_categories' ) {
		return;
	}
	$post_id = (isset($_POST['post_id'])) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
	$macro_category = (isset($_POST['macro_category'])) ? sanitize_text_field(wp_unslash($_POST['macro_category'])) : '';

	$term_id = wp_remove_object_terms( $post_id, $macro_category, 'siqo_macro_categories' );

	wp_send_json(array(
		//'post' => $_POST,
		'term_id' => $term_id,
	));
	die();
}




function SIQO_update_post_ajax( $_poste = '' ){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ($_poste != '') {
		$_POST = $_poste;
	}
	$post_id = (isset($_POST['post_id']))? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	$post_type = $post_id ? get_post_type( $post_id ) : '';
	$capability = siqo_ajax_post_capability( $post_type );
	if ( ! $post_id || ! $capability ) {
		wp_send_json_error( array( 'message' => __( 'Contenuto non consentito.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( $capability );
	$post_id = wp_update_post(array (
		'ID' => $post_id,
	    'post_title' => (isset($_POST['title']))?sanitize_text_field(wp_unslash($_POST['title'])):'',
	    'post_content' => (isset($_POST['content']))?sanitize_text_field(wp_unslash($_POST['content'])):'',
	    'post_status' => 'publish',
	));
	if ($post_id) {
	    if (isset($_POST['fo_delivery_date'])) {
	    	update_post_meta($post_id, 'fo_delivery_date', sanitize_text_field(wp_unslash($_POST['fo_delivery_date'])));
	    }
	    if (isset($_POST['fo_catering_status'])) {
	    	update_post_meta($post_id, 'fo_catering_status', sanitize_text_field(wp_unslash($_POST['fo_catering_status'])));
	    }
	}
	wp_send_json(array(
		//'post' => $_POST,
		'post_id' => $post_id,
	));
	die();
}

function SIQO_delete_post_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	$delete_id = (isset($_POST['delete_id']))? absint( wp_unslash( $_POST['delete_id'] ) ) : 0;
	$post_type = $delete_id ? get_post_type( $delete_id ) : '';
	$capability = siqo_ajax_post_capability( $post_type );
	if ( ! $delete_id || ! $capability ) {
		wp_send_json_error( array( 'message' => __( 'Contenuto non consentito.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( $capability );
	$post = wp_delete_post( $delete_id );

	wp_send_json(array(
		'post' => $post,
	));
	die();
}

function SIQO_trash_post_ajax(){
	if ( !isset($_POST['_siqononce_insert_post_ajax_nonce']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_insert_post_ajax_nonce'])), 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	$delete_id = (isset($_POST['delete_id']))? absint( wp_unslash( $_POST['delete_id'] ) ) : 0;
	$post_type = $delete_id ? get_post_type( $delete_id ) : '';
	$capability = siqo_ajax_post_capability( $post_type );
	if ( ! $delete_id || ! $capability ) {
		wp_send_json_error( array( 'message' => __( 'Contenuto non consentito.', 'studio-immens-quick-order' ) ), 403 );
	}
	siqo_ajax_require_cap( $capability );
	$post = wp_trash_post( $delete_id );

	wp_send_json(array(
		'post' => $post,
	));
	die();
}





function SIQO_price( $number ){
	$return = $number;
	if ( strstr($number, '.') ) {
		if ( strlen( substr( $number, strpos($number,'.') ) )-1 == 1 ) {
			$return .= '0 '.get_woocommerce_currency_symbol();
		} elseif ( strlen( substr( $number, strpos($number,'.') ) )-1 == 2 ) {
			$return .= ' '.get_woocommerce_currency_symbol();
		}
	}else{
		if ( $number == '' ) {
			$return .= '0.00 '.get_woocommerce_currency_symbol();
		}else{
			$return .= '.00 '.get_woocommerce_currency_symbol();
		}
	}
	return $return;
}








function SIQO_get_translated_status( $status ) {
	if ( $status == 'processing' ) {
		$status = esc_html__('In Lavorazione','studio-immens-quick-order');
	} elseif ( $status == 'completed' ) {
		$status = esc_html__('Completato','studio-immens-quick-order');
	} elseif ( $status == 'cancelled' ) {
		$status = esc_html__('Cancellato','studio-immens-quick-order');
	} elseif ( $status == 'delivered' ) {
		$status = esc_html__('Consegnato','studio-immens-quick-order');
	} elseif ( $status == 'pending' ) {
		$status = esc_html__('In Attesa','studio-immens-quick-order');
	}else{}
	return $status;
}










if (isset( $_GET['post_type']) && $_GET['post_type'] === 'product' ) {
	add_action('edit_form_before_permalink', 'SIQO_add_extra_field_to_post' );
	// add_action('quick_edit_custom_box', 'SIQO_add_extra_field_to_post' );
}else{
	if (isset( $_GET['post']) && get_post(sanitize_text_field(wp_unslash($_GET['post'])))->post_type === 'product') {
		add_action('edit_form_before_permalink', 'SIQO_add_extra_field_to_post' );
	}
}

function SIQO_add_extra_field_to_post() {
	$short_title = array('');
	$slang_title = array('');
	if ( isset( $_GET['post'] ) ) {
		$post_id = sanitize_text_field(wp_unslash( $_GET['post']));
		$short_title = (get_post_meta( $post_id , 'short_title'))?get_post_meta($post_id , 'short_title'): array('');
		$slang_title = (get_post_meta( $post_id , 'slang_title'))?get_post_meta($post_id , 'slang_title'):array('');
	}
	$nonce = wp_create_nonce( 'FO_post_extra_field_nonce' );
    echo '<input type="hidden" id="_fononce_post_extra_field" name="_fononce_post_extra_field" value="'.esc_attr($nonce).'" />';
	?>
	<div id="titlediv">
		<div id="titlewrap">
			<input type="text" name="short_title" size="30" value="<?php echo esc_attr($short_title[0]);?>" id="shortTitle" spellcheck="true" autocomplete="off" placeholder="<?php esc_html_e('Nome breve', 'studio-immens-quick-order');?>">
			<input type="text" name="slang_title" size="30" value="<?php echo esc_attr($slang_title[0]);?>" id="slangTitle" spellcheck="true" autocomplete="off" placeholder="<?php esc_html_e('Nome slang', 'studio-immens-quick-order');?>">
		</div>
	</div>
	<?php
}

add_filter( 'manage_product_posts_columns', 'SIQO_short_slang_title_columns' );
function SIQO_short_slang_title_columns( $column_array ) {
	$column_array[ 'short_title' ] = 'Short Title';
	$column_array[ 'slang_title' ] = 'Slang Title';
	return $column_array;
}
// Populate our new columns with data
add_action( 'manage_posts_custom_column', 'SIQO_populate_both_columns', 10, 2 );
function SIQO_populate_both_columns( $column_name, $post_id ) {
	// if you have to populate more that one columns, use switch()
	switch( $column_name ) {
		case 'short_title': {
			$short_title = (get_post_meta(get_the_ID(),'short_title'))?get_post_meta(get_the_ID(),'short_title',true):'';
			echo esc_attr($short_title);
			break;
		}
		case 'slang_title': {
			$slang_title = (get_post_meta(get_the_ID(),'slang_title'))?get_post_meta(get_the_ID(),'slang_title',true):'';
			echo esc_attr($slang_title);
			break;
		}
	}
}
add_action( 'quick_edit_custom_box', 'SIQO_quick_edit_fields', 10, 2 );
function SIQO_quick_edit_fields( $column_name, $post_type ) {
	// global $post;
    $nonce = wp_create_nonce( 'FO_post_extra_field_nonce' );
    echo '<input type="hidden" id="_fononce_post_extra_field" name="_fononce_post_extra_field" value="'.esc_attr($nonce).'" />';
	switch( $column_name ) {
		case 'short_title': {
			?>
				<fieldset class="inline-edit-col-left">
					<div id="titlediv">
						<div id="titlewrap">
							<span class="short_title"><?php esc_html_e('Nome breve', 'studio-immens-quick-order');?></span>
							<input type="text" name="short_title" size="30" value="" id="shortTitle" spellcheck="true" autocomplete="off" placeholder="<?php esc_html_e('Nome breve', 'studio-immens-quick-order');?>">
						</div>
					</div>
				</fieldset>
				<?php
			break;
		}
		case 'slang_title': {
			?>
				<fieldset class="inline-edit-col-left">
					<div id="titlediv">
						<div id="titlewrap">
							<span class="slang_title"><?php esc_html_e('Nome slang', 'studio-immens-quick-order');?></span>
							<input type="text" name="slang_title" size="30" value="" id="slangTitle" spellcheck="true" autocomplete="off" placeholder="<?php esc_html_e('Nome slang', 'studio-immens-quick-order');?>">
						</div>
					</div>
				</fieldset>
			<?php
			break;
		}
	}
	?>
	<?php siqo_add_inline_js( <<<'JS'

		jQuery( function( $ ){
			const wp_inline_edit_function = inlineEditPost.edit;
			// we overwrite the it with our own
			inlineEditPost.edit = function( post_id ) {
				// let's merge arguments of the original function
				wp_inline_edit_function.apply( this, arguments );
				// get the post ID from the argument
				if ( typeof( post_id ) == 'object' ) { // if it is object, get the ID number
					post_id = parseInt( this.getId( post_id ) );
				}
				// add rows to variables
				const edit_row = $( '#edit-' + post_id )
				const post_row = $( '#post-' + post_id )

				const short_title = $( '.short_title', post_row ).text()
				const slang_title = $( '.slang_title', post_row ).text()

				// populate the inputs with column data
				$(':input[name="short_title"]', edit_row ).val( short_title );
				$(':input[name="slang_title"]', edit_row ).val( slang_title );
			}
		});
JS
); ?>
	<?php
}
// add_action( 'manage_product_posts_custom_column', 'SIQO_show_custom_field_quick_edit_data', 9999, 2 );
// function SIQO_show_custom_field_quick_edit_data( $column, $post_id ){
//     if ( 'short_title' == $column ) {
// 	    echo '<div>Short Title: <span id="cf_' . esc_attr($post_id) . '">' . get_post_meta( $post_id, 'short_title', true ) . '</span></div>';
// 	    wc_enqueue_js( "
// 	      $('#the-list').on('click', '.editinline', function() {
// 	         var post_id = $(this).closest('tr').attr('id');
// 	         post_id = post_id.replace('post-', '');
// 	         var custom_field = $('#cf_' + post_id).text();
// 	         $('input[name=\'short_title\']', '.inline-edit-row').val(custom_field);
// 	        });
// 	    " );
// 	}
// 	if ( 'slang_title' == $column ) {
// 	    echo '<div>Slang Title: <span id="cf_' . esc_attr($post_id) . '">' . get_post_meta( $post_id, 'slang_title', true ) . '</span></div>';
// 	    wc_enqueue_js( "
// 	      $('#the-list').on('click', '.editinline', function() {
// 	         var post_id = $(this).closest('tr').attr('id');
// 	         post_id = post_id.replace('post-', '');
// 	         var custom_field = $('#cf_' + post_id).text();
// 	         $('input[name=\'slang_title\']', '.inline-edit-row').val(custom_field);
// 	        });
// 	    " );
// 	}
// }

function SIQO_get_settings_head_manage_tables(){
$ajax_refresh_table_seconds = ( intval( SIQO_get_meta('ajax_refresh_table_seconds') ) < 500 ) ? 500 : intval( SIQO_get_meta('ajax_refresh_table_seconds'));
?>
	<div id="settings" style="display:none!important;">
	    <div id="ajax_refresh_table_seconds"> <?php echo esc_attr($ajax_refresh_table_seconds);?> </div>
	    <div id="fo_tab_go_order_text"> <?php esc_html_e( 'Sicuro di voler procedere con l\'Ordinazione?', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_clear_table_text"><?php esc_html_e('Sicuro di voler LIBERARE il tavolo?','studio-immens-quick-order');?></div>
	    <div id="fo_tab_clear_all_table_text"><?php esc_html_e('Sicuro di voler LIBERARE tutti i tavoli?','studio-immens-quick-order');?></div>
	    <div id="fo_tab_table_status_free_text"> <?php esc_html_e( 'Libero', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_table_status_wait_text"> <?php esc_html_e( 'Attesa', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_table_status_close_text"> <?php esc_html_e( 'Occupato', 'studio-immens-quick-order' );?> </div>

	    <div id="fo_tab_create_table_text"> <?php esc_html_e( 'Vuoi creare un nuovo tavolo chiamato: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_create_table_error_text"> <?php esc_html_e( 'Inserisci prima il nome del tavolo.', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_delete_table_text"> <?php esc_html_e( 'Vuoi ELIMINARE il tavolo chiamato: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_delete_table_error_text"> <?php esc_html_e( 'seleziona prima il tavolo da eliminare.', 'studio-immens-quick-order' );?> </div>

	    <div id="fo_tab_create_catering_text"> <?php esc_html_e( 'Vuoi creare un nuovo evento chiamato: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_refresh_catering_text"> <?php esc_html_e( 'Vuoi aggiornare l\'evento chiamato: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_create_catering_error_text"> <?php esc_html_e( 'Completa prima i campi obbligatori contrassegnati da: * .', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_create_catering_delete_text"> <?php esc_html_e( 'Vuoi ELIMINARE in modo definitivo questo evento, chiamato: ', 'studio-immens-quick-order' );?> </div>

	    <div id="fo_tab_new_customer_error_name_text"> <?php esc_html_e( 'Inserisci prima il Nome del Cliente!', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_new_customer_error_mail_phone_text"> <?php esc_html_e( 'Inserisci almeno la mail o il numero di telefono del Cliente!', 'studio-immens-quick-order' );?> </div>

	    <div id="fo_tab_change_category_to_product_text"> <?php esc_html_e( 'Sei sicuro di voler cambiare la categoria di questo prodotto?', 'studio-immens-quick-order' );?> </div>

<!-- Create Macro Category -->
	    <div id="fo_tab_create_macro_cat_text"> <?php esc_html_e( 'Vuoi creare una nuova macro categoria chiamata: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_create_macro_cat_error_text"> <?php esc_html_e( 'Inserisci prima il nome della categoria.', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_delete_macro_cat_text"> <?php esc_html_e( 'Vuoi ELIMINARE la categoria chiamata: ', 'studio-immens-quick-order' );?> </div>
	    <div id="fo_tab_delete_macro_cat_error_text"> <?php esc_html_e( 'seleziona prima la categoria da eliminare.', 'studio-immens-quick-order' );?> </div>

<!-- Create Product -->
		<div id="fo_tab_create_prod_error_text"> <?php esc_html_e( 'inserisci prima tutti i campi richiesti e contrassegnati da *', 'studio-immens-quick-order' );?> </div>
		<div id="fo_tab_create_prod_confirm_text"> <?php esc_html_e( 'Confermi di voler creare il Prodotto chiamato: ', 'studio-immens-quick-order' );?> </div>

		<div id="fo_tab_trash_prod_error_text"> <?php esc_html_e( 'Seleziona prima il Prodotto da ELIMINARE', 'studio-immens-quick-order' );?> </div>
		<div id="fo_tab_trash_prod_confirm_text"> <?php esc_html_e( 'Sei sicuro di voler ELIMINARE il prodotto chiamato:', 'studio-immens-quick-order' );?> </div>

	</div>

	<?php siqo_add_inline_css( '.foTimeOutAnim{animation:foerrcol 2s alternate infinite!important;}' ); ?>

<?php
}


































function SIQO_save_post_extra_field( $post_id, $post, $update ) {
	// Only want to set if this is a new post!
		if ( !$update ){
			return;
		}
	// Only set for post_type = product!
		if ( 'product' !== $post->post_type ) {
			return;
		}
	if ( !isset($_POST['_siqononce_post_extra_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash( $_POST['_siqononce_post_extra_field'])), 'FO_post_extra_field_nonce' ) ) {
		return;
	}//_fononce_post_extra_field: jQuery('input[name="_fononce_post_extra_field"]').val(),

	if ( isset( $_POST['short_title'] ) ) {
		$short_title = sanitize_text_field(wp_unslash($_POST['short_title']));
		update_post_meta( $post_id, 'short_title', $short_title );
	}
	if ( isset( $_POST['slang_title'] ) ) {
		$slang_title = sanitize_text_field(wp_unslash($_POST['slang_title']));
		update_post_meta( $post_id, 'slang_title', $slang_title );
	}
}
add_action('save_post','SIQO_save_post_extra_field',10,3);





function siqo_is_number($s) {
	$ss = str_split($s);
    for ($i = 0; $i < strlen($s); $i++)
        if (is_numeric($s[$i]) == false) {
            return false;
        }
    return true;
}









/**
 * Function for `woocommerce_new_order` action-hook.
 *
 * @param int       $order_id Order ID.
 * @param \WC_Order $order    Order object.
 *
 * @return void
 */
function SIQO_add_products_to_order( $order_id, $order ) {
	if ( $order->post_type === 'shop_order' ) {
		$fin_prod = array();
		$order = wc_get_order( $order_id );
		$meta_data = get_post_meta( $order_id );
		foreach( $meta_data as $key => $value ){
			if ( siqo_str_contains( (string)$key, 'Product-' ) ) {
				$fin_prod[$key] = explode( ',', $value[0] );
			}
		}
		foreach( $fin_prod as $k => $v ){
			$order->add_product( wc_get_product( $v[0] ), $v[1] );
		}
	}
}
//add_action('wp_insert_post','SIQO_add_products_to_order', 10, 2);
//add_action('wp_ajax_SIQO_add_products_to_order', 'SIQO_add_products_to_order');
//add_action('wp_ajax_nopriv_SIQO_add_products_to_order', 'SIQO_add_products_to_order');

function SIQO_get_order_info_button_tab ( $order ){
	?>
	<button onclick="jQuery(this).next().toggle();"><?php echo esc_html__( 'INFO', 'studio-immens-quick-order' ); ?></button>
	<div class="flash_card" style="display:none;" onclick="jQuery(this).css('display', 'none')">

		<div class="title" style="font-size:30px;color:var(--siqo-scnd-color);"><?php echo esc_html__( 'ORDINE', 'studio-immens-quick-order' ).' #'.esc_attr($order->get_id()); ?>
		</div>

		<div class="title"><?php echo esc_html__( 'FATTURAZIONE', 'studio-immens-quick-order' ); ?>
			<p><?php echo esc_attr($order->get_billing_first_name()).' '.esc_attr($order->get_billing_last_name()); ?></p>
			<p><?php echo esc_attr($order->get_billing_company()); ?></p>
			<p><?php echo esc_attr($order->get_billing_address_1()); ?></p>
			<p><?php echo esc_attr($order->get_billing_address_2()); ?></p>
			<p><?php echo esc_attr($order->get_billing_city()).' '.esc_attr($order->get_billing_state()); ?></p>
			<p><?php echo esc_attr($order->get_billing_postcode()).' '.esc_attr($order->get_billing_country()); ?></p>
		</div>
		<div class="title"><?php echo esc_html__( 'SPEDIZIONE', 'studio-immens-quick-order' ); ?>
			<p><?php echo esc_attr($order->get_shipping_first_name()).' '.esc_attr($order->get_shipping_last_name()); ?></p>
			<p><?php echo esc_attr($order->get_shipping_company()); ?></p>
			<p><?php echo esc_attr($order->get_shipping_address_1()); ?></p>
			<p><?php echo esc_attr($order->get_shipping_address_2()); ?></p>
			<p><?php echo esc_attr($order->get_shipping_city()).' '.esc_attr($order->get_shipping_state()); ?></p>
			<p><?php echo esc_attr($order->get_shipping_postcode()).' '.esc_attr($order->get_shipping_country()); ?></p>
		</div>
		<div class="title"><?php echo esc_html__( 'CONTATTO', 'studio-immens-quick-order' ); ?>
			<p><?php echo esc_attr($order->get_billing_email()); ?></p>
			<p><?php echo esc_attr($order->get_billing_phone()); ?></p>
		</div>
	</div>
	<?php
}
function SIQO_get_print_receipt ( $order ){
	?>
	<button type="button" onClick="window.print()"> <?php echo esc_html__( 'Stampa scontrino', 'studio-immens-quick-order' ); ?> </button>
	<?php
}



function SIQO_get_flash_orders_visualize_settings() {
	$visualize = array();
	$settings = SIQO_get_meta_by_assoc_id( 'setting', 'OBJECT' );
	foreach ( $settings as $key => $val) {
	if ($val->meta_key == 'flash_orders_table_visualize_id' && $val->meta_value == 'yes') { $visualize['id'] = true;}
	if ($val->meta_key == 'flash_orders_table_visualize_name' && $val->meta_value == 'yes') { $visualize['name'] = true;}
	if ($val->meta_key == 'flash_orders_table_visualize_image' && $val->meta_value == 'yes') { $visualize['image'] = true;}
	if ($val->meta_key == 'flash_orders_table_visualize_warehouse' && $val->meta_value == 'yes') { $visualize['warehouse'] = true;}
	if ($val->meta_key == 'flash_orders_table_visualize_note' && $val->meta_value == 'yes') { $visualize['note'] = true;}
	if ($val->meta_key == 'flash_orders_table_visualize_actions' && $val->meta_value == 'yes') { $visualize['actions'] = true;}
	}
	return $visualize;
}

function SIQO_get_manage_orders_visualize_settings() {
	$visualize = array();
	$settings = SIQO_get_meta_by_assoc_id( 'setting', 'OBJECT' );
	foreach ( $settings as $key => $val) {
	if ($val->meta_key == 'manage_orders_table_visualize_id' && $val->meta_value == 'yes') { $visualize['id'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_name' && $val->meta_value == 'yes') { $visualize['name'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_products' && $val->meta_value == 'yes') { $visualize['products'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_info' && $val->meta_value == 'yes') { $visualize['info'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_status' && $val->meta_value == 'yes') { $visualize['siqo_table_status'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_actions' && $val->meta_value == 'yes') { $visualize['actions'] = true;}
	if ($val->meta_key == 'manage_orders_table_visualize_totals' && $val->meta_value == 'yes') { $visualize['totals'] = true;}
	}
	return $visualize;
}



function SIQO_get_order_meta_array( $order ) {
	$meta_data = array();
	if ( ! $order instanceof WC_Order ) {
		return $meta_data;
	}
	foreach ( $order->get_meta_data() as $meta ) {
		$meta_data[ $meta->key ][] = $meta->value;
	}
	return $meta_data;
}

function SIQO_manage_order_add_order( $order, $visualize = array() ){
	$order = new WC_Order($order->get_id());
	$visualize = wp_parse_args( SIQO_get_manage_orders_visualize_settings(), $visualize );

	if ($order->get_status() == 'pending') {$back_status = 'FOstatus_pending';
	} elseif ($order->get_status() == 'processing') {$back_status = 'FOstatus_processing';
	} elseif ($order->get_status() == 'failed') {$back_status = 'FOstatus_failed';
	} elseif ($order->get_status() == 'cancelled') {$back_status = 'FOstatus_cancelled';
	} elseif ($order->get_status() == 'refunded') {$back_status = 'FOstatus_refunded';
	} elseif ($order->get_status() == 'completed') {$back_status = 'FOstatus_completed';
	} else { $back_status = ''; }

if ( isset($visualize['div']) && $visualize['div'] ) {
	$_div_class = ' order-div-class';
}else{
	$_div_class = '';
}
	$string = '';
	$string .= '<tr orderid="'.absint( $order->get_id() ).'" orderdata="'.esc_attr( $order->get_date_modified() ).'" orderstatus="'.esc_attr( $order->get_status() ).'" ordertype="'.esc_attr( $order->get_meta('delivery_type') ).'" class="'.esc_attr( $back_status.$_div_class ).'" style="position:relative;">';

	if ( isset($visualize['id']) && $visualize['id'] ) {
		$string .= '<td identify="ID">'.$order->get_id().'</td>';
	}
	if ( isset($visualize['name']) && $visualize['name'] ) {
		$string .= '<td identify="name">';
			$string .= esc_html( $order->get_billing_first_name() ).' '.esc_html( $order->get_billing_last_name() );
		$string .= '</td>';
	}
	$meta_data = SIQO_get_order_meta_array( $order );
	$Front_Post = array();
	foreach ($meta_data as $key => $value) {
		if ( siqo_str_contains( (string)$key, 'info-' )) {
			$index_var = (int)filter_var($key, FILTER_SANITIZE_NUMBER_INT);
			$Front_Post[$index_var] = $value;
		}
	}

	if ( isset($visualize['products']) && $visualize['products'] ) {
		$string .= '<td identify="products" class="FOmanOrderImg">';
		// echo SIQO_get_product_image_and_ingredient( $order->get_id() );
		if ( $order->get_created_via() == 'checkout' ) {
			foreach ( $order->get_items() as $item_id => $item ) {
				$prod_ing = array(); $i = 1;
				$products = $item->get_product();
					$short_title = (get_post_meta($products->get_id(),'short_title'))?get_post_meta($products->get_id(),'short_title')[0]:$products->get_name();
					$slang_title = (get_post_meta($products->get_id(),'slang_title'))?get_post_meta($products->get_id(),'slang_title')[0]:$short_title;
				$product_note = '';
				$Ingredienti = get_the_terms( $item->get_product_id(), 'siqo_ingredients');
				$default_ing = array(); $p = 1;
			// SIQO_debug($Ingredienti);
				if (is_array($Ingredienti)) {
					foreach ( $Ingredienti as $key => $value) {
						$default_ing[$p] = $value->term_id;
						$p++;
					}
				}
				$class = '';
				if ( array_diff_assoc( $prod_ing, $default_ing ) != null || $product_note != '' ) {
					$class = 'FOmodified';
				}
				$string .= '<div foprodid="'.$products->get_id().'" foprodtot="'.number_format( $products->get_price(), 2 ).get_woocommerce_currency_symbol().'" class="relative">';
				$string .= '<div class="'.$class.'"  onclick="jQuery(this).next().slideToggle()">';
					$string .= wp_kses_post($products->get_image());
					$string .= '<span class="FO_id_info">#'.$item->get_id().'</span>';
	//-----------------
						$string .= '<div class="FO_prod_name_manage">';
							$string .= esc_html( $products->get_name() );
							// echo $slang_title;
							if ( $products->get_type() == 'variation' ) {
								$string .= "  |>".esc_html( implode(", ", $products->get_attributes() ) );
							}
						$string .= '</div>';

					$string .= '<span class="FO_quantity_info">'.$item->get_quantity().'</span>';
				$string .= '</div>';
				$string .= '<div class="FOimgProdInfo" style="display:none;">';
					$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';
					$string .= '<div class="FO_prod_name">';
						$string .= esc_html( $item->get_name() );

					$string .= '</div>';
					$string .= '<div class="FO_prod_img">';
						$string .= wp_kses_post($products->get_image());
					$string .= '</div>';

				if ( $class != '' ) {
					$string .= '<div class="FO_prod_ingred">';
						$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
					$string .= '</div>';
					if (count($prod_ing) > 0 ) {
						foreach ($prod_ing as $key => $value) {
							$clssmod = ''; if ( !in_array( $value, $default_ing ) ) {
								$clssmod = 'FOstatus_modified';
							}
							$string .= '<div class="FOIngredProdTab '.$clssmod.'">';
								$string .= get_term( $value, 'siqo_ingredients' )->name;
							$string .= '</div>';
						}
					}
				} else{
					if ( count($default_ing) == 0 ) {
						$string .= '<div class="FO_prod_ingred">';
							$string .= esc_html__( 'il prodotto non ha nessun ingrediente associato ad esso', 'studio-immens-quick-order' );
						$string .= '</div>';
					} else{
						$string .= '<div class="FO_prod_ingred">';
							$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
						$string .= '</div>';
						foreach ($default_ing as $key => $value) {
							$string .= '<div class="FOIngredProdTab">';
								$string .= get_term( $value, 'siqo_ingredients' )->name;
							$string .= '</div>';
						}
					}
				}
				if ( $product_note != '' ) {
					$string .= '<div class="FO_prod_note">';
						$string .= esc_html__( 'Note prodotto:', 'studio-immens-quick-order' );
					$string .= '</div>';
						$string .= '<div style="width:100%;justify-content:center;">';
							$string .= esc_html( $product_note );
						$string .= '</div>';
				}
				$string .= '</div>';
				$string .= '</div>';
			}
		} else{
			if ( $order->get_created_via() == 'ajax' || $order->get_created_via() == 'lista_ajax'|| $order->get_created_via() != 'checkout' ) {
				$meta_array = SIQO_extract_meta_array($meta_data);
				// SIQO_debug( $meta_array  );
				foreach ( $meta_array as $meta_index => $meta_elem ){
					$product_id = (array_key_exists('id', $meta_elem))?$meta_elem['id']:'';
					$product_quantity = (array_key_exists('qty', $meta_elem))?$meta_elem['qty']:'';
					$product_note = (array_key_exists('note', $meta_elem))?$meta_elem['note']:'';
					$prod_ing = (array_key_exists('ingredient', $meta_elem))?$meta_elem['ingredient']:array();

					$varianti = (array_key_exists('variant', $meta_elem))?$meta_elem['variant']:array();
					$temperature = (array_key_exists('siqo_temperatures', $meta_elem))?$meta_elem['siqo_temperatures']:'';

					if ($temperature!='' && is_scalar($temperature)) {
						$temper_term = get_term_by( 'name', $temperature, 'siqo_temperatures' );
						$temper_id = $temper_term ? $temper_term->term_id : 0;
						$tax_img = $temper_id ? get_term_meta($temper_id, 'taxonomy_image') : array();
					}

					$Ingredienti = get_the_terms($product_id,'siqo_ingredients');
					$default_ing = array();
					if (is_array($Ingredienti)) { $p = 1;
						foreach ( $Ingredienti as $key => $value) {
							$default_ing[$p] = $value->term_id;
							$p++;
						}
					}
					// SIQO_debug( $product_note  );
					$products = wc_get_product( $product_id );
					if ($products == false||$products == null) {continue;}
					$short_title = ( SIQOcheck(get_post_meta($products->get_id(),'short_title') ) && get_post_meta($products->get_id(),'short_title')[0]!='')?get_post_meta($products->get_id(),'short_title')[0]:$products->get_name();
					$slang_title = ( SIQOcheck(get_post_meta($products->get_id(),'slang_title') ) && get_post_meta($products->get_id(),'slang_title')[0]!='' )?get_post_meta($products->get_id(),'slang_title')[0]:$short_title;
					if ( SIQOcheck( array_diff_assoc( $prod_ing, $default_ing ) ) || $product_note != '' || count($varianti) || $products->get_type() == 'variation' ) {
						$class = 'FOmodified';
					}else{$class = '';}

					$string .= '<div foprodid="'.$products->get_id().'" foprodtot="'.$products->get_price().get_woocommerce_currency_symbol().'" class="relative">';
					$string .= '<div class="'.$class.'"  onclick="jQuery(this).next().slideToggle()">';
						$string .= wp_kses_post($products->get_image());
						$string .= '<span class="FO_id_info">#'.$products->get_id().'</span>';
						if (count($varianti) || $products->get_type() == 'variation') {
							$string .= '<span class="FO_id_variant" title="'.count($varianti).'"><img src="/wp-content/plugins/studio-immens-quick-order/includes/img/cycle.webp"></span>';
						}
						$string .= '<div class="FO_prod_name_manage">';
							$string .= esc_html( $slang_title );
							if ( $products->get_type() == 'variation' ) {
								$string .= "  |>".esc_html( implode(", ", $products->get_attributes() ) );
							}
						$string .= '</div>';
						if ($temperature!='') {
							$string .= '<span class="FO_id_temperature" title="'.esc_attr($temperature).'">';
								$string .= '<img src="/wp-content/plugins/studio-immens-quick-order/includes/img/thermometer2.webp">';
								if ( SIQOcheck($tax_img) && isset($tax_img[0]) && $tax_img[0] != '' ) {
									$string .= '<img class="fotemper_image"width="20"height="20"src="'.esc_url($tax_img[0]).'">';
								}
							$string .= '</span>';
						}
						$string .= '<span class="FO_quantity_info">'.$product_quantity.'</span>';
					$string .= '</div>';
					$string .= '<div class="FOimgProdInfo" style="display:none;">';
						$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';
						$string .= '<div class="FO_prod_name">';
							$string .= esc_html( $slang_title );
							if ( $products->get_type() == 'variation' ) {
								$string .= "  |>".esc_html( implode(", ", $products->get_attributes() ) );
							}
						$string .= '</div>';
						$string .= '<div class="FO_prod_img">';
							$string .= wp_kses_post($products->get_image());
						$string .= '</div>';

						if (count($varianti)) {
							$string .= '<div class="FO_prod_variant">';
								$string .= esc_html__( 'le varianti:', 'studio-immens-quick-order' );
							$string .= '</div>';
							$string .= '<div class="FOAdvVariantProdTab">';
							foreach ($varianti as $key => $value) {
								$string .= '<div class="FO_variant" title="'.esc_attr($key).'">';
									$string .= '<p>'.esc_html($key).': </p>';
									$string .= '<p> '.esc_html(is_scalar($value) ? (string) $value : '').'</p>';
								$string .= '</div>';
							}
							$string .= '</div>';
						}

						if ($temperature!='') {
							$string .= '<div class="FO_prod_temperature" style="width:100%;justify-content:center;">';
								$string .= '<div class="FO_prod_temp">';
									$string .= esc_html__( 'Temperatura:', 'studio-immens-quick-order' );
								$string .= '</div>';

								$string .= '<img src="/wp-content/plugins/studio-immens-quick-order/includes/img/thermometer2.webp">';

								if ( SIQOcheck($tax_img) && isset($tax_img[0]) && $tax_img[0] != '' ) {
									$string .= '<img class="fotemper_image"width="20"height="20"src="'.esc_url($tax_img[0]).'">';
								}
								$string .= '<span class="FO_temperature" title="'.esc_attr($temperature).'">'.esc_html($temperature).'</span>';
							$string .= '</div>';
						}

					if ( $class != '' ) {
						$string .= '<div class="FO_prod_ingred">';
							$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
						$string .= '</div>';
						if (count($prod_ing) > 0 ) {
							// echo SIQO_get_tax_cloud_from_id_ajax( $prod_ing, 'siqo_ingredients', $products->get_id() );
							foreach ($prod_ing as $key => $value) {
								$clssmod = ''; if ( !in_array( $value, $default_ing ) ) {
									$clssmod = 'FOstatus_modified';
								}
								$value = get_term( $value, 'siqo_ingredients' );
								$tax_img = get_term_meta($value->term_id, 'taxonomy_image');
								$string .= '<div class="FOAdvIngredProdTab '.$clssmod.'">';
									$string .= '<p>'.esc_html($value->name).'</p>';
									if ( SIQOcheck($tax_img) && isset($tax_img[0]) && $tax_img[0] != '' ) {
										$string .= '<img class="fotax_image"width="30"height="30"src="'.esc_url($tax_img[0]).'">';
									}
								$string .= '</div>';
							}
						}
					} else{
						if ( count($default_ing) == 0 ) {
							$string .= '<div class="FO_prod_ingred">';
								$string .= esc_html__( 'il prodotto non ha nessun ingrediente associato ad esso', 'studio-immens-quick-order' );
							$string .= '</div>';
						} else{
							$string .= '<div class="FO_prod_ingred">';
								$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
							$string .= '</div>';
							foreach ($default_ing as $key => $value) {
								if ( SIQOcheck($value) ) {
									$ing_name = (SIQOcheck(get_term($value,'siqo_ingredients')))?get_term($value,'siqo_ingredients')->name:'';
									$tax_img = get_term_meta($value, 'taxonomy_image');

									$string .= '<div class="FOAdvIngredProdTab">';
										$string .= '<p>'.esc_html($ing_name).'</p>';
										// $string .= $ing_name;
										if ( SIQOcheck($tax_img) && isset($tax_img[0]) && $tax_img[0] != '' ) {
											$string .= '<img class="fotax_image"width="30"height="30"src="'.esc_url($tax_img[0]).'">';
										}
									$string .= '</div>';
								}
							}
						}
					}
					if ( is_string($product_note) && $product_note != '' ) {
						$string .= '<div class="FO_prod_note">';
							$string .= esc_html__( 'Note prodotto:', 'studio-immens-quick-order' );
						$string .= '</div>';
						$string .= '<div style="width:100%;justify-content:center;">';
							$string .= esc_html( $product_note );
						$string .= '</div>';
					}
					$string .= '</div>';
					$string .= '</div>';
				}
			} else{
				$product_note = '';
				foreach ( $Front_Post as $item_id => $it ) {
				foreach ( $it as $kk => $vv ) {
					// SIQO_debug($vv);
					$item = json_decode($vv);
				if ( is_string($item) || $item == null ){continue;}

					$prod_ing = array(); $i = 1;
						foreach ( $item as $keykey => $valval ) {
							$product_id = null;
							if ( is_numeric( $keykey)) {
								$product_id = $keykey;
								$product_quantity = $valval;
							}
							if ( siqo_str_contains( (string)$keykey, 'siqo_ingredients' ) ) {
								foreach ((array)$valval as $inkey => $invalue) {
									// SIQO_debug($invalue);
									if (is_string( $invalue ) ) {
										$prod_ing[$i] = get_term_by( 'name', $invalue, 'siqo_ingredients' )->term_id;
										$i++;
									} else { continue;
										$prod_ing[$i] = get_term_by( 'name', '', 'siqo_ingredients' )->term_id;
										$i++;
									}
								}
							}
							if ( siqo_str_contains( (string)$keykey, 'note' ) ) {
								$product_note = (array)$valval;
							}
						}
					$product_id = ($product_id != null ) ? $product_id: '';
					$products = wc_get_product( $product_id );

					if ($products == false||$products == null) {continue;}

					$Ingredienti = get_the_terms($product_id, 'siqo_ingredients');
					$default_ing = array(); $p = 1;
					if (is_array($Ingredienti)) {
						foreach ( $Ingredienti as $key => $value) {
							$default_ing[$p] = $value->term_id;
							$p++;
						}
					}
					$class = '';
					if ( array_diff_assoc( $prod_ing, $default_ing ) != null || $product_note != '' ) {
						$class = 'FOmodified';
					}

					$string .= '<div foprodid="'.$products->get_id().'" foprodtot="'.$products->get_price().get_woocommerce_currency_symbol().'" class="relative">';
						$string .= '<div class="'.$class.'"  onclick="jQuery(this).next().slideToggle()">';
							$string .= wp_kses_post($products->get_image());
							$string .= '<span class="FO_id_info">#'.$products->get_id().'</span>';
							$string .= '<span class="FO_quantity_info">'.$product_quantity.'</span>';
						$string .= '</div>';
						$string .= '<div class="FOimgProdInfo" style="display:none;">';
							$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';
							$string .= '<div class="FO_prod_name">';
								$string .= esc_html( $products->get_name() );
							$string .= '</div>';
							$string .= '<div class="FO_prod_img">';
								$string .= wp_kses_post($products->get_image());
							$string .= '</div>';

						if ( $class != '' ) {
							$string .= '<div class="FO_prod_ingred">';
								$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
							$string .= '</div>';
							if (count($prod_ing) > 0 ) {
								foreach ($prod_ing as $key => $value) {
									$clssmod = ''; if ( !in_array( $value, $default_ing ) ) {
										$clssmod = 'FOstatus_modified';
									}
									$string .= '<div class="FOIngredProdTab '.$clssmod.'">';
										$string .= get_term( $value, 'siqo_ingredients' )->name;
									$string .= '</div>';
								}
							}
						} else{
							if ( count($default_ing) == 0 ) {
								$string .= '<div class="FO_prod_ingred">';
									$string .= esc_html__( 'il prodotto non ha nessun ingrediente associato ad esso', 'studio-immens-quick-order' );
								$string .= '</div>';
							} else{
								$string .= '<div class="FO_prod_ingred">';
									$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
								$string .= '</div>';
								foreach ($default_ing as $key => $value) {
									$string .= '<div class="FOIngredProdTab">';
										$string .= get_term( $value, 'siqo_ingredients' )->name;
									$string .= '</div>';
								}
							}
						}
						if ( is_string($product_note) && $product_note != '' ) {
							$string .= '<div class="FO_prod_note">';
								$string .= esc_html__( 'Note prodotto:', 'studio-immens-quick-order' );
							$string .= '</div>';
							$string .= '<div style="width:100%;justify-content:center;">';
								$string .= esc_html( $product_note );
							$string .= '</div>';
						}
						$string .= '</div>';
					$string .= '</div>';
				}
				}
			}
// fee -------------------------------------
			foreach ( $order->get_fees() as $item_fee ) {
				$fee_name = $item_fee->get_name();
				$fee_total = $item_fee->get_total();

				$string .= '<div foprodid="'.esc_attr( $fee_name ).'" foprodtot="'.esc_attr( $fee_total ).'" class="relative">';
					$string .= '<div onclick="jQuery(this).next().slideToggle()">';
							$string .= '<div class="fo_fake_image_fee">'.esc_html__( 'Special', 'studio-immens-quick-order' ).'</div>';
						$string .= '<div class="FO_prod_name_manage">';
							$string .= esc_html( $fee_name );
						$string .= '</div>';

						$string .= '<span class="FO_quantity_info">'.SIQO_price($fee_total).'</span>';
					$string .= '</div>';

					$string .= '<div class="FOimgProdInfo" style="display:none;">';
						$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';

						$string .= '<div class="FO_prod_name">';
							$string .= esc_html( $fee_name );
						$string .= '</div>';
						$string .= '<div class="FO_prod_img">';
							$string .= '<div class="fo_fake_image_fee">'.esc_html__( 'Special', 'studio-immens-quick-order' ).'</div>';
						$string .= '</div>';
						$string .= '<div class="FO_prod_ingred">';
							$string .= SIQO_price($fee_total);
						$string .= '</div>';
					$string .= '</div>';
				$string .= '</div>';

			}

		}
		$string .= '</td>';
	}
	if ( isset($visualize['info']) && $visualize['info'] ) {
		$string .= '<td identify="info">';
		$del_type = '';
		$tab_num_data = '';
		$table_surname = '';
		if ($order->get_meta('delivery_type') != null && $order->get_meta('delivery_type') != '') {
			if ( $order->get_meta('delivery_type') == 'table' ) {
				$del_type = esc_html__( 'Tavolo', 'studio-immens-quick-order' );
				$tab_num_data = $order->get_meta('Table');
				$table_surname = $order->get_meta('table_surname');
			} elseif ($order->get_meta('delivery_type') == 'pickup') {
				$del_type = esc_html__( 'Ritiro', 'studio-immens-quick-order' );
			} elseif ($order->get_meta('delivery_type') == 'delivery') {
				$del_type = esc_html__( 'Consegna', 'studio-immens-quick-order' );
			} else{
				$del_type = '';
			}
		}
		$string .= '<div fotarget="ordertype" title="'.esc_html__( 'Tipo di ordine', 'studio-immens-quick-order' ).'">';
		if ($order->get_meta('Table_cpt')!= '') {
			$string .= esc_html( $order->get_meta('Table_cpt') );
			$separator_1 = ' - ';
		} else{	$separator_1 = '';
			// $order = new WC_Order($order->get_id());
		}
		if ($tab_num_data != '0' ) {
			$string .= $separator_1.esc_html( $del_type );
			$string .= ' '.esc_html( $tab_num_data );
			$string .= ' '.esc_html( $table_surname );
		}
		$string .= '</div>';
		$string .= SIQO_get_order_info_button_tab_string($order->get_id());
//--------------------------------------
		$string .= '</td>';
	}
	if ( isset($visualize['siqo_table_status']) && $visualize['siqo_table_status'] ) {
		$string .= '<td identify="siqo_table_status" class="status '.$back_status.'" style="">';
			$string .= '<p>'.SIQO_status_titled( $order->get_status() ).'</p>';
			$string .= '<button statustarget onclick="jQuery(this).next().slideToggle();" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'" class="relative">';
				$string .= SIQO_status_titled( $order->get_status() );
			$string .= '</button>';
			$string .= SIQO_get_status_changer( $order );
	if ( SIQO_get_meta( 'visualize_orders_timer') == 'yes' ) {
			$string .= '<div identify="fotimer" class="fotimer fo_status_timer_1" style="display:none">';
				$string .= 'timer';
			$string .= '</div>';
	}
		$string .= '</td>';
	}
	if ( isset($visualize['actions']) && $visualize['actions'] ) {
		$string .= '<td identify="actions">';
			$string .= '<div class="FOloadingCardPublic" style="display:none;">';
				$string .= '<span style="animation: fospin 1s infinite;font-size:70px;width:70px;height:70px;" class="dashicons dashicons-update"></span>';
			$string .= '</div>';
		if ( !isset($visualize['siqo_table_status']) ) {
			$string .= '<button statustarget onclick="jQuery(this).next().slideToggle();" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'" class="relative">';
				$string .= SIQO_status_titled( $order->get_status() );
			$string .= '</button>';
			$string .= SIQO_get_status_changer( $order );
		}

		$string .= '<button type="button" class="fo_button_thin" onclick="SIQO_print_receipt(' . $order->get_id() . ');" style="color: yellow;">';
		$string .= '<span class="dashicons dashicons-printer"></span> ' . esc_html__( 'Stampa', 'studio-immens-quick-order' );
		$string .= '</button>';
		if ( SIQO_get_meta( 'visualize_orders_timer') == 'yes' ) {
			$string .= '<div identify="fotimer" class="fotimer fo_status_timer_1" style="display:none">';
				$string .= 'timer';
			$string .= '</div>';
		}
		$string .= '</td>';
	}
	if ( isset($visualize['totals']) && $visualize['totals'] ) {
		$string .= '<td identify="totals">';
			if ( $order->get_subtotal() != 0 ) {
				$string .= SIQO_price( $order->get_subtotal() ).' </br>';
			}
			if ( $order->get_total_fees() != 0 ) {
				$string .= SIQO_price( $order->get_total_fees() ).' </br>';
			}
			if ( $order->get_total() != 0 ) {
				$string .= 'tot '.SIQO_price( $order->get_total() ).' </br>';
			} else{
				if ($order->get_total_fees() != 0) {
					$string .= 'tot '.SIQO_price( $order->get_total() ).' </br>';
				} else{
					$string .= 'tot '.SIQO_price( $order->get_subtotal() ).' </br>';
				}
			}
		$string .= '</td>';
	}
	$string .= '</tr>';
	//SIQO_get_order_info_button_tab ( $order );
	//SIQO_get_print_receipt ( $order );
	return $string;
}
function SIQO_status_titled( $status ){
	if ( $status == 'pending') {
		$return = esc_html__('nuovo','studio-immens-quick-order');
	} elseif( $status == 'processing' ){
		$return = esc_html__('in lavorazione','studio-immens-quick-order');
	} elseif( $status == 'cancelled' ){
		$return = esc_html__('cancellato','studio-immens-quick-order');
	} elseif( $status == 'refunded' ){
		$return = esc_html__('rimborsato','studio-immens-quick-order');
	} elseif( $status == 'completed' ){
		$return = esc_html__('completato','studio-immens-quick-order');
	}else{ $return = ''; }
	return $return;
}
function SIQO_get_all_order_meta( $order_id ){
	global $wpdb;
	$table = $wpdb->prefix . "wc_orders_meta";

	$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE order_id = %s", [ $table, $order_id ] ) );

	return $result;
}
function SIQO_extract_meta_object( $meta ){
	$array = array();$i = 1;
	if (!is_array($meta)) {
		return false;
	}
	foreach ($meta as $key => $value) {
		if ( siqo_str_contains( (string)$value->meta_key, '{' ) ) {
			$index = substr( (string)$value->meta_key, 1, strpos((string)$value->meta_key,'}')-1 );
			if ( siqo_str_contains( (string)$value->meta_key, 'index' ) ) {
				$array[$index][substr( (string)$value->meta_key, strpos((string)$value->meta_key,'-')+1 )] = $value->meta_value;
				$array[$index]['id'] = substr( (string)$value->meta_key, strpos((string)$value->meta_key,'-')+1 );
				$array[$index]['qty'] = $value->meta_value;
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'note' ) ) {
				$array[$index]['note'] = $value->meta_value;
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'modification' ) ) {
				$array[$index]['modification'] = $value->meta_value;
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'searched' ) ) {
				$array[$index]['searched'] = $value->meta_value;
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'ingredient' ) ) {
				$array[$index]['ingredient'][$i] = substr( (string)$value->meta_key, strpos((string)$value->meta_key,'ingredient-')+11 );
				$i++;
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'siqo_temperatures' ) ) {
				$array[$index]['siqo_temperatures'] = substr($value->meta_value,strpos($value->meta_value,'| ')+2);
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'variant' ) ) {
				$array[$index]['variant'][substr((string)$value->meta_key,strpos((string)$value->meta_key,'variant-')+8)] = substr($value->meta_value,strpos($value->meta_value,'| ')+2);
	//[substr((string)$value->meta_key,strpos((string)$value->meta_key,'prod-')+5,strpos((string)$value->meta_key,'_variant')-8)]
			}
			if ( siqo_str_contains( (string)$value->meta_key, 'price' ) ) {
				$array[$index]['total'] = $value->meta_value;
			}
		}
	}
	return $array;
}
function SIQO_extract_meta_array( $meta ){
	$array = array();$i = 1;
	if (!is_array($meta)) {
		// $order->get_meta('delivery_type');
	}
	foreach ($meta as $key => $value) {
		if ( siqo_str_contains( (string)$key, '{' ) ) {
			$index = substr( (string)$key, 1, strpos((string)$key,'}')-1 );
			if ( siqo_str_contains( (string)$key, 'index' ) ) {
				$array[$index][substr( (string)$key, strpos((string)$key,'-')+1 )] = $value[0];
				$array[$index]['id'] = substr( (string)$key, strpos((string)$key,'-')+1 );
				$array[$index]['qty'] = $value[0];
			}
			if ( siqo_str_contains( (string)$key, 'note' ) ) {
				if (is_array($value)) {
					$array[$index]['note'] = $value[0];
				}else{
					$array[$index]['note'] = $value;
				}
			}
			if ( siqo_str_contains( (string)$key, 'searched' ) ) {
				$array[$index]['searched'] = $value;
			}
			// if (siqo_str_contains( (string)$key, 'ingredient' )){$array[$index]['ingredient'][substr((string)$key, strpos((string)$key,'ingredient-')+11)] = substr($value[0], strpos($value[0], '| ')+2);}
			if ( siqo_str_contains( (string)$key, 'ingredient' ) ) {
				$array[$index]['ingredient'][$i] = substr( (string)$key, strpos((string)$key,'ingredient-')+11 );
				$i++;
			}
			if ( siqo_str_contains( (string)$key, 'siqo_temperatures' ) || siqo_str_contains( (string)$key, '_temperature-' ) ) {
				$array[$index]['siqo_temperatures'] = substr( $value[0], strpos( $value[0], '| ' ) + 2 );
			}
			if ( siqo_str_contains( (string)$key, 'variant' ) ) {
				$array[$index]['variant'][substr((string)$key,strpos((string)$key,'variant-')+8)] = substr($value[0],strpos($value[0],'| ')+2);
			}
			if ( siqo_str_contains( (string)$key, 'price' ) ) {
				$array[$index]['total'] = $value[0];
			}

			// $array[$index]['total'] = $value[0];
		}
	}
	return $array;
}


function SIQO_get_product_image_and_ingredient( $order_id ){
	$order = new WC_Order($order_id);
	$meta_data = SIQO_get_order_meta_array( $order );
	$Front_Post = array();
	$string = '';

	foreach ($meta_data as $key => $value) {
		if ( siqo_str_contains( (string)$key, 'info-' )) {
			$index_var = (int)filter_var($key, FILTER_SANITIZE_NUMBER_INT);
			$Front_Post[$index_var] = $value;
		}
	}
	if ( $order->get_created_via() == 'checkout' ) {
		foreach ( $order->get_items() as $item_id => $item ) {
			// SIQO_debug($item);
			$prod_ing = array(); $i = 1;
			$products = $item->get_product();
			$product_note = '';
			$Ingredienti = get_the_terms( $item->get_product_id(), 'siqo_ingredients');
			$default_ing = array(); $p = 1;
	// SIQO_debug($Ingredienti);
			if (is_array($Ingredienti)) {
				foreach ( $Ingredienti as $key => $value) {
					$default_ing[$p] = $value->term_id;
					$p++;
				}
			}
			$class = '';
			if ( array_diff_assoc( $prod_ing, $default_ing ) != null || $product_note != '' ) {
				$class = 'FOmodified';
			}
			$string .= '<div class="relative">';
			$string .= '<div class="'.$class.'"  onclick="jQuery(this).next().slideToggle()">';
				$string .= wp_kses_post($products->get_image());
				$string .= '<span class="FO_quantity_info">'.$item->get_quantity().'</span>';
			$string .= '</div>';
			$string .= '<div class="FOimgProdInfo" style="display:none;">';
				$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';
				$string .= '<div style="width:100%;justify-content:center;">';
					$string .= esc_html( $item->get_name() );
				$string .= '</div>';
				$string .= '<div style="width:100%;justify-content:center;">';
					$string .= wp_kses_post($products->get_image());
				$string .= '</div>';

			if ( $class != '' ) {
				$string .= '<div class="FO_prod_ingred">';
					$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
				$string .= '</div>';
				if (count($prod_ing) > 0 ) {
					foreach ($prod_ing as $key => $value) {
						$string .= '<div class="FOIngredProdTab">';
							$string .= get_term( $value, 'siqo_ingredients' )->name;
						$string .= '</div>';
					}
				}
			} else{
				if ( count($default_ing) == 0 ) {
					$string .= '<div class="FO_prod_ingred">';
						$string .= esc_html__( 'il prodotto non ha nessun ingrediente associato ad esso', 'studio-immens-quick-order' );
					$string .= '</div>';
				} else{
					$string .= '<div class="FO_prod_ingred">';
						$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
					$string .= '</div>';
					foreach ($default_ing as $key => $value) {
						$string .= '<div class="FOIngredProdTab">';
							$string .= get_term( $value, 'siqo_ingredients' )->name;
						$string .= '</div>';
					}
				}
			}
			if ( $product_note != '' ) {
				$string .= '<div class="FO_prod_note">';
					$string .= esc_html__( 'Note prodotto:', 'studio-immens-quick-order' );
				$string .= '</div>';
				$string .= '<div style="width:100%;justify-content:center;">';
					$string .= esc_html( $product_note );
				$string .= '</div>';
			}
			$string .= '</div>';
			$string .= '</div>';
		}
	} else{
		foreach ( $Front_Post as $item_id => $it ) {

			foreach ( $it as $kk => $vv ) {
				$item = json_decode($vv);
				$prod_ing = array(); $i = 1;
					foreach ( $item as $keykey => $valval ) {
						if ( is_numeric( $keykey)) {
							$product_id = $keykey;
							$product_quantity = $valval;
						}
						if ( siqo_str_contains( (string)$keykey, 'siqo_ingredients' ) ) {
							foreach (current($valval) as $inkey => $invalue) {
								$prod_ing[$i] = get_term_by( 'name', $invalue, 'siqo_ingredients' )->term_id;
								$i++;
							}
						}
						if ( siqo_str_contains( (string)$keykey, 'note' ) ) {
							$product_note = current($valval);
						}
					}
				// $product_id = ($product_id) ? : ;
				$products = wc_get_product( $product_id );
				$Ingredienti = get_the_terms($product_id, 'siqo_ingredients');
				$default_ing = array(); $p = 1;
				if (is_array($Ingredienti)) {
					foreach ( $Ingredienti as $key => $value) {
						$default_ing[$p] = $value->term_id;
						$p++;
					}
				}

				$class = '';
				if ( array_diff_assoc( $prod_ing, $default_ing ) != null || $product_note != '' ) {
					$class = 'FOmodified';
				}
				$string .= '<div class="relative">';
					$string .= '<div class="'.$class.'"  onclick="jQuery(this).next().slideToggle()">';
						$string .= wp_kses_post($products->get_image());
						$string .= '<span class="FO_quantity_info">'.$product_quantity.'</span>';
					$string .= '</div>';
					$string .= '<div class="FOimgProdInfo" style="display:none;">';
						$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';
						$string .= '<div style="width:100%;justify-content:center;">';
							$string .= esc_html( $products->get_name() );
						$string .= '</div>';
						$string .= '<div style="width:100%;justify-content:center;">';
							$string .= wp_kses_post($products->get_image());
						$string .= '</div>';

					if ( $class != '' ) {
						$string .= '<div class="FO_prod_ingred">';
							$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
						$string .= '</div>';
						if (count($prod_ing) > 0 ) {
							foreach ($prod_ing as $key => $value) {
								$string .= '<div class="FOIngredProdTab">';
									$string .= get_term( $value, 'siqo_ingredients' )->name;
								$string .= '</div>';
							}
						}
					} else{
						if ( count($default_ing) == 0 ) {
							$string .= '<div class="FO_prod_ingred">';
								$string .= esc_html__( 'il prodotto non ha nessun ingrediente associato ad esso', 'studio-immens-quick-order' );
							$string .= '</div>';
						} else{
							$string .= '<div class="FO_prod_ingred">';
								$string .= esc_html__( 'gli ingredienti:', 'studio-immens-quick-order' );
							$string .= '</div>';
							foreach ($default_ing as $key => $value) {
								$string .= '<div class="FOIngredProdTab">';
									$string .= get_term( $value, 'siqo_ingredients' )->name;
								$string .= '</div>';
							}
						}
					}
					if ( $product_note != '' ) {
						$string .= '<div class="FO_prod_note">';
							$string .= esc_html__( 'Note prodotto:', 'studio-immens-quick-order' );
						$string .= '</div>';
						$string .= '<div style="width:100%;justify-content:center;">';
							$string .= esc_html( $product_note );
						$string .= '</div>';
					}
					$string .= '</div>';
				$string .= '</div>';
			}
		}
	}
	return $string;
}

function SIQO_get_status_changer( $order ){
	$string = '';
	$string .= '<div class="FOstatusChanger" style="display:none;">';
	$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle();">';
	$string .= esc_html__( 'CHIUDI', 'studio-immens-quick-order' );
	$string .= '</div>';
	// if ( $order->get_status() == 'pending' ) {
		$string .= '<button onclick="siqo_change_order_status(`'.$order->get_id().'`, `processing`, `'.esc_html__( 'Sei sicuro di voler cambiare lo status dell\'ordine?`', 'studio-immens-quick-order' ).');" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'">'.esc_html__( 'in lavorazione', 'studio-immens-quick-order' ).'</button>';
	// }
	$string .= '<button onclick="siqo_change_order_status(`'.$order->get_id().'`, `cancelled`, `'.esc_html__( 'Sei sicuro di voler cambiare lo status dell\'ordine?`', 'studio-immens-quick-order' ).');" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'">'.esc_html__( 'cancellato', 'studio-immens-quick-order' ).'</button>';
	$string .= '<button onclick="siqo_change_order_status(`'.$order->get_id().'`, `refunded`, `'.esc_html__( 'Sei sicuro di voler cambiare lo status dell\'ordine?`', 'studio-immens-quick-order' ).');" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'">'.esc_html__( 'rimborsato', 'studio-immens-quick-order' ).'</button>';
	$string .= '<button onclick="siqo_change_order_status(`'.$order->get_id().'`, `completed`, `'.esc_html__( 'Sei sicuro di voler cambiare lo status dell\'ordine?`', 'studio-immens-quick-order' ).');" title="'.esc_html__( 'cambia lo status dell\'ordine', 'studio-immens-quick-order' ).'">'.esc_html__( 'completato', 'studio-immens-quick-order' ).'</button>';
	$string .= '</div>';
	return $string;
}

function SIQO_get_order_info_button_tab_string ( $order_id = null ){

	$order_id = absint( $order_id );
	if ( isset($_POST['ajax_order_id']) && $order_id == null ) {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'siqo_ajax_view_orders_vars' ) ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
		}
		$order_id = absint( wp_unslash( $_POST['ajax_order_id'] ) );
		$ajax_check = true;
	} else{
		$ajax_check = false;
	}
	if ( $ajax_check && ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
$order = wc_get_order( $order_id );
	if ( ! $order ) {
		if ( $ajax_check ) {
			wp_send_json_error( array( 'message' => __( 'Ordine non trovato.', 'studio-immens-quick-order' ) ), 404 );
		}
		return '';
	}
	$string = '';
	if (!$ajax_check) {
		$string .= '<button onclick="jQuery(this).next().slideToggle();">'.esc_html__( 'INFO', 'studio-immens-quick-order' ).'</button>';
	}
	$string .= '<div class="flash_card" style="display:none;">';
		$string .= '<div class="FOCloseTab" onclick="jQuery(this).parent().slideToggle()">'.esc_html__('CHIUDI','studio-immens-quick-order').'</div>';

		$string .= '<div class="FO_inSIQO_section">';
			$string .= '<div class="title" style="font-size:30px;color:var(--siqo-scnd-color);">';
			$string .= esc_html__( 'ORDINE', 'studio-immens-quick-order' ).' #'.$order->get_id();
			$string .= '</div>';

			$string .= '<div class="title" style="font-size:20px;color:var(--siqo-scnd-color);">';
			$string .= esc_html__( 'Tipo di ordine', 'studio-immens-quick-order' ).'- '.esc_html( $order->get_meta( 'delivery_type' ) );
			$string .= '</div>';
		$string .= '</div>';
		$string .= '<div class="FO_inSIQO_section">';
			$string .= '<div class="title">'.esc_html__( '[DATE]', 'studio-immens-quick-order' );
			if ( $order->get_date_created() ) {
				$string .= '<p class="fo-fake-table" title="'.esc_html__( 'data di creazione dell\'ordine', 'studio-immens-quick-order' ).'">';
					$string .= esc_html__( 'creato: ', 'studio-immens-quick-order' ).'<p style="margin: 5px 5px 15px 15px;">'.date_format($order->get_date_created(),"Y/m/d H:i:s").'</p>';
				$string .= '</p>';
			} if ( $order->get_date_modified() ) {
				$string .= '<p class="fo-fake-table" title="'.esc_html__( 'data dell\'ultima modifica dell\'ordine', 'studio-immens-quick-order' ).'">';
					$string .= esc_html__( 'ultima modifica: ', 'studio-immens-quick-order' ).'<p style="margin: 5px 5px 15px 15px;">'.date_format($order->get_date_modified(),"Y/m/d H:i:s").'</p>';
				$string .= '</p>';
			} if ( $order->get_date_paid() ) {
				$string .= '<p class="fo-fake-table" title="'.esc_html__( 'data della fatturazione dell\'ordine', 'studio-immens-quick-order' ).'">';
					$string .= esc_html__( 'fatturazione: ', 'studio-immens-quick-order' ).'<p style="margin: 5px 5px 15px 15px;">'.date_format($order->get_date_paid(),"Y/m/d H:i:s").'</p>';
				$string .= '</p>';
			} if ( $order->get_date_completed() ) {
				$string .= '<p class="fo-fake-table" title="'.esc_html__( 'data del completamento dell\'ordine', 'studio-immens-quick-order' ).'">';
						$string .= esc_html__( 'completato: ', 'studio-immens-quick-order' ).'<p style="margin: 5px 5px 15px 15px;">'.date_format($order->get_date_completed(),"Y/m/d H:i:s").'</p>';
				$string .= '</p>';
			}
			$string .= '</div>';

			$string .= '<div class="title">'.esc_html__( '[FATTURAZIONE]', 'studio-immens-quick-order' );
				$string .= '<p title="get_billing_first_name - get_billing_last_name">'.esc_html( $order->get_billing_first_name() ).' '.esc_html( $order->get_billing_last_name() ).'</p>';
				$string .= '<p title="get_billing_company">'.esc_html( $order->get_billing_company() ).'</p>';
				$string .= '<p title="get_billing_address_1">'.esc_html( $order->get_billing_address_1() ).'</p>';
				$string .= '<p title="get_billing_address_2">'.esc_html( $order->get_billing_address_2() ).'</p>';
				$string .= '<p title="get_billing_city - get_billing_state">'.esc_html( $order->get_billing_city() ).' '.esc_html( $order->get_billing_state() ).'</p>';
				$string .= '<p title="get_billing_postcode - get_billing_country">'.esc_html( $order->get_billing_postcode() ).' '.esc_html( $order->get_billing_country() ).'</p>';
			$string .= '</div>';

			$string .= '<div class="title">'.esc_html__( '[SPEDIZIONE]', 'studio-immens-quick-order' );
				$string .= '<p title="get_shipping_first_name - get_shipping_last_name">'.esc_html( $order->get_shipping_first_name() ).' '.esc_html( $order->get_shipping_last_name() ).'</p>';
				$string .= '<p title="get_shipping_company">'.esc_html( $order->get_shipping_company() ).'</p>';
				$string .= '<p title="get_shipping_address_1">'.esc_html( $order->get_shipping_address_1() ).'</p>';
				$string .= '<p title="get_shipping_address_2">'.esc_html( $order->get_shipping_address_2() ).'</p>';


				$string .= '<p title="get_shipping_city - get_shipping_state">'.esc_html( $order->get_shipping_city() ).' '.esc_html( $order->get_shipping_state() ).'</p>';
				$string .= '<p title="get_shipping_postcode - get_shipping_country">'.esc_html( $order->get_shipping_postcode() ).' '.esc_html( $order->get_shipping_country() ).'</p>';

			$string .= '</div>';

			$string .= '<div class="title">'.esc_html__( '[INFO]', 'studio-immens-quick-order' );
				$string .= '<p title="get_created_via">'.esc_html__( 'creato da - ', 'studio-immens-quick-order' ).esc_html( $order->get_created_via() ).'</p>';
				$string .= '<p title="get_billing_email">'.esc_html( $order->get_billing_email() ).'</p>';
				$string .= '<p title="get_billing_phone">'.esc_html( $order->get_billing_phone() ).'</p>';
				$string .= '<p title="get_customer_ip_address">'.esc_html__('indirizzo ip - ','studio-immens-quick-order').esc_html( $order->get_customer_ip_address() ).'</p>';
				$string .= '<p title="get_customer_user_agent">'.esc_html__('user agent - ','studio-immens-quick-order').esc_html( $order->get_customer_user_agent() ).'</p>';
			$string .= '</div>';
		$string .= '</div>';

		$string .= '<div class="FO_inSIQO_section">';
			$string .= '<div class="title">'.esc_html__( '[ALTRO]', 'studio-immens-quick-order' );
				$string .= '<p>'.esc_html__( 'note dell\'ordine: - ', 'studio-immens-quick-order' ).esc_html( $order->get_meta( 'order_note' ) ).'</p>';
			$string .= '</div>';
		$string .= '</div>';
	$string .= '</div>';

	if ($ajax_check) {
		wp_send_json(array(
			'string' => $string,
		));
	} else{
		return $string;
	}
}
add_action('wp_ajax_SIQO_get_order_info_button_tab_string', 'SIQO_get_order_info_button_tab_string');






if ( SIQO_get_meta('siqo_pickup_delivery_checkout') == 'yes' ) {
	/* Add Checkout field*/
	add_action('woocommerce_before_checkout_form','SIQO_add_pickup_delivery_section');
	/* Validate Checkout field*/
	add_action('woocommerce_checkout_process', 'SIQO_validate_pickup_delivery_section');
	/* Save Checkout field*/
	add_action('woocommerce_checkout_update_order_meta', 'SIQO_save_pickup_delivery_section');

	add_action( 'woocommerce_view_order', 'SIQO_view_Pickup_Delivery' );
	add_action( 'woocommerce_thankyou', 'SIQO_view_Pickup_Delivery' );
}
function SIQO_add_pickup_delivery_section(){
	$nonce = wp_create_nonce( 'FO_pickup_delivery_section_nonce' );
	?>
	<div id="FO_pickup_delivery">
		<input type="hidden" id="_siqononce_pickup_delivery_section" name="_siqononce_pickup_delivery_section" value="<?php echo esc_attr($nonce);?>">
		<p>
			<span class="woocommerce-input-wrapper">
				<input type="radio" class="input-radio " value="pickup" name="delivery_type" id="fo_order_type_pickup">
				<label for="fo_order_type_pickup" class="radio "><?php echo esc_html__( 'Ritiro in Sede', 'studio-immens-quick-order' ); ?></label>

				<input type="radio" class="input-radio " value="delivery" name="delivery_type" id="fo_order_type_delivery" checked="checked">
				<label for="fo_order_type_delivery" class="radio "><?php echo esc_html__( 'Consegna a domicilio', 'studio-immens-quick-order' ); ?></label>
			</span>
		</p>
		<div class="fo_error_message" style="display: none;"></div>

        <div class="fo_delivery">
	        <div class="fo_date_section">
	            <p>
	            	<label for="fo_delivery_date" class=""><?php echo esc_html__( 'Data di consegna', 'studio-immens-quick-order' ); ?>
	            		<abbr class="required" title="obbligatorio">*</abbr>
	            	</label>
	            	<span class="woocommerce-input-wrapper">
	            		<input type="date" name="fo_delivery_date" id="fo_delivery_date" placeholder="" value="" required>
	            	</span>
	            </p>
	        </div>
	        <div class="fo_time_section" title="<?php echo esc_html__( 'Cercheremo di rispettare questo orario con il massimo dell\'impegno, tuttavia l\'orario effettivo potrebbe non corrispondere.', 'studio-immens-quick-order' ); ?>">
	            <p>
	            	<label for="fo_delivery_time" class=""><?php echo esc_html__( 'Ora di consegna', 'studio-immens-quick-order' ); ?>
	            		<abbr class="required" title="obbligatorio">*</abbr>
	            	</label>
	            	<span class="woocommerce-input-wrapper">
	            		<input type="time" name="fo_delivery_time" id="fo_delivery_time" placeholder="" value="" required>
	            	</span>
	            </p>
	        </div>
        </div>
    </div>
	<?php siqo_add_inline_js( <<<'JS'

	jQuery(function($){
		function siqo_toggle_delivery(){
			var pickup = $('#fo_order_type_pickup').is(':checked');
			$('.fo_delivery').toggle(!pickup);
			$('#fo_delivery_date, #fo_delivery_time').prop('required', !pickup);
		}
		$(document).on('change', 'input[name="delivery_type"]', siqo_toggle_delivery);
		siqo_toggle_delivery();
	});
JS
); ?>
	<?php
}

function SIQO_view_Pickup_Delivery( $order_id ){
	$order = wc_get_order( $order_id );
	$delivery_type = $order ? $order->get_meta( 'delivery_type' ) : '';
		if ($delivery_type == 'delivery'){ $delivery_type = esc_html__('Consegna a Domicilio', 'studio-immens-quick-order'); }
		if ($delivery_type == 'pickup'){ $delivery_type = esc_html__('Ritiro in Sede', 'studio-immens-quick-order'); }
	$fo_delivery_date = $order ? $order->get_meta( 'fo_delivery_date' ) : '';
	$fo_delivery_time = $order ? $order->get_meta( 'fo_delivery_time' ) : '';

	echo '<p>'.esc_html__("Tipo Ordine: ", 'studio-immens-quick-order').'<strong>'.esc_attr($delivery_type).'</strong></p>';
	if ( $order && 'delivery' === $order->get_meta( 'delivery_type' ) ) {
		echo '<p>'.esc_html__("Data: ", 'studio-immens-quick-order').'<strong>'.esc_attr($fo_delivery_date).'</strong> '.esc_html__("Ora: ", 'studio-immens-quick-order').'<strong>'.esc_attr($fo_delivery_time).'</strong></p>';
	}
}

function SIQO_validate_pickup_delivery_section(){
// Show an error message if the field is not set.
    if ( !isset($_POST['_siqononce_pickup_delivery_section']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_pickup_delivery_section'])), 'FO_pickup_delivery_section_nonce' ) ) {
		return;
	}	//_fononce_stat_update_nonce: jQuery('input[name="_fononce_stat_update_nonce"]').val(),
	$delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) ) : 'delivery';
	if ( 'delivery' === $delivery_type ) {
		if ( empty( $_POST['fo_delivery_date'] ) ) {
			wc_add_notice( esc_html__( 'Seleziona una data!', 'studio-immens-quick-order' ), 'error' );
		}
		if ( empty( $_POST['fo_delivery_time'] ) ) {
			wc_add_notice( esc_html__( 'Seleziona un\'orario!', 'studio-immens-quick-order' ), 'error' );
		}
	}
}

function SIQO_save_pickup_delivery_section($order_id){
    if ( !isset($_POST['_siqononce_pickup_delivery_section']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_siqononce_pickup_delivery_section'])), 'FO_pickup_delivery_section_nonce' ) ) {
		return;
	}	//_fononce_stat_update_nonce: jQuery('input[name="_fononce_stat_update_nonce"]').val(),
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$delivery_type = ! empty( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) ) : 'delivery';
	$order->update_meta_data( 'delivery_type', $delivery_type );
	if ( 'delivery' === $delivery_type ) {
		if ( ! empty( $_POST['fo_delivery_date'] ) ) {
			$order->update_meta_data( 'fo_delivery_date', sanitize_text_field( wp_unslash( $_POST['fo_delivery_date'] ) ) );
		}
		if ( ! empty( $_POST['fo_delivery_time'] ) ) {
			$order->update_meta_data( 'fo_delivery_time', sanitize_text_field( wp_unslash( $_POST['fo_delivery_time'] ) ) );
		}
	} else {
		$order->delete_meta_data( 'fo_delivery_date' );
		$order->delete_meta_data( 'fo_delivery_time' );
	}
	$order->save();
}





function SIQO_get_connection_info(){
	$info = array();

	$info['ip'] = gethostbyname( gethostname() );
	$info['host'] = gethostname();

	// $info['details'] = json_decode(file_get_contents("https://ipinfo.io"));
	$info['details'] = array();

	$ipinfo = SIQO_get_meta('ipinfo');

	// FO_limit_ip_to_local_network

	if ( SIQOcheck($ipinfo) && false ) {
		$info['is_local'] = true;
	} else{
		$info['is_local'] = false;
	}
	// if ( !filter_var($info['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ){
	//     $info['is_local'] = true;
	// } else{
	// 	$info['is_local'] = false;
	// }

	// SIQO_debug( $info );
	return $info;
}

function SIQO_check_meta_setting( $setting ){

	if ( SIQOcheck( SIQO_get_meta($setting) ) && SIQO_get_meta($setting) == 'yes' || SIQO_get_meta($setting) == 'si' ){
	    $return = true;
	} else{
		$return = false;
	}

	return $return;
}





















//ajax & auto refresh

function SIQO_check_for_orders() {
	if ( !isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'siqo_ajax_view_orders_vars' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
    $update_needed = false;
    $orders_to_refresh = array();
    $order_items = array();
    $string = '';
    $orders_date_created = intval( SIQO_get_meta('orders_date_created') ) * 3600;
	$orders_date_created_operator =  ( $orders_date_created == 0 ) ? '<' : '>';

	$order_id_table = array();
	if ( isset( $_POST['order_id_table'] ) && is_array( $_POST['order_id_table'] ) ) {
		$order_id_table = array_map( 'absint', wp_unslash( $_POST['order_id_table'] ) );
	}

	$orders = wc_get_orders( array(
		'numberposts'    	=> 5,
		'posts_per_page' 	=> -1,
		'paged'          	=> 'page',
		'orderby'           => 'date',
		'order'             => 'DESC',
		'return'            => 'objects',
		'paginate'          => false,
		'date_created' => $orders_date_created_operator . ( time() - $orders_date_created ),// 3600s = 1h  28800s = 8h
	) );
	if ( empty( $orders ) ) {
		wp_send_json( array(
			'updateNeeded'   => false,
			'newOrders'      => array(),
			'last_id'        => 0,
			'order_id_table' => $order_id_table,
			'string'         => '',
		) );
	}
	$last_id = $orders[0]->get_id();

	if ( isset($_POST['last_order_id']) && sanitize_text_field(wp_unslash($_POST['last_order_id'])) != $last_id ) {
		foreach( $orders as $k => $v ){
			if ( isset($_POST['last_order_data']) && $v->get_date_modified() > sanitize_text_field(wp_unslash($_POST['last_order_data']))) {
				if ( !in_array( $v->get_id(), $order_id_table, true ) ) {
					$orders_to_refresh[$k] = $v->get_data();
					//$order_items[$k] = $v->get_items();
					$string .= SIQO_manage_order_add_order( $v );
				}
			} else { continue; }
		}
		if (isset($orders_to_refresh)) {
			$update_needed = true;
		}
		if ( SIQO_get_meta( 'siqo_last_woocommerce_order_id') != $last_id ) {
			SIQO_update_meta( 'siqo_last_woocommerce_order_id', $last_id );
		}
	}
	// return wp_json_encode('prova');
    wp_send_json(array(
		'updateNeeded' 	=> $update_needed,
		'newOrders' 	=> $orders_to_refresh,
		'last_id' 		=> $last_id,
		'order_id_table' => $order_id_table,
		'string' 		=> $string,
		//'debug' 		=> $orders[0]->get_data()
	));
	// wp_send_json_error();
	die();
}
add_action('wp_ajax_SIQO_check_for_orders', 'SIQO_check_for_orders');


function SIQO_ajax_change_order_status( $poste = '' ){
	$siqo_change_status_nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash( $_POST['nonce'])) : '';
	if ( ! wp_verify_nonce( $siqo_change_status_nonce, 'siqo_ajax_view_orders_vars' ) && ! wp_verify_nonce( $siqo_change_status_nonce, 'SIQO_insert_post_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	if (SIQOcheck($poste) && is_array($poste) ) {
		$_POST = $poste;
	}
	$order_id = (isset($_POST['order_id'])) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$status = (isset($_POST['siqo_table_status'])) ? sanitize_text_field(wp_unslash( $_POST['siqo_table_status'] )) : '';
	$allowed_statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
	if ( ! $order_id || ! in_array( $status, $allowed_statuses, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Dati ordine non validi.', 'studio-immens-quick-order' ) ), 400 );
	}
	$order = wc_get_order($order_id);
	// $order = new WC_Order($order_id);
	if (!empty($order)) {
		$order->update_status( $status );
	}
	wp_send_json(array(
		'order_id' 	=> $order_id,
		'siqo_table_status' 	=> $status,
		// 'order' 	=> $order
	));
	// wp_send_json_success();
	if (!SIQOcheck($poste) && !is_array($poste) ) {
		die();
	}
}
add_action('wp_ajax_SIQO_ajax_change_order_status', 'SIQO_ajax_change_order_status');



function siqo_enqueue_ajax_scripts() {
	// wp_enqueue_script('studio-immens-quick-order-public', plugin_dir_url(__FILE__) . 'js/studio-immens-quick-order-public.js', array('jquery', 'wp-api'), '1.0', false);
    // Passa i dati che possono essere utili per la tua richiesta AJAX
    wp_localize_script('studio-immens-quick-order-public', 'siqo_ajax_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('siqo_ajax_vars'),
        'in_footer' => true,
        'strategy'  => 'async',
    ));
}
add_action('wp_enqueue_scripts', 'siqo_enqueue_ajax_scripts');
// add_action('admin_enqueue_scripts', 'siqo_enqueue_ajax_scripts');

function siqo_enqueue_ajax_view_orders_scripts() {
	wp_enqueue_script('studio-immens-quick-order-ajax-view-orders', plugin_dir_url(__FILE__) . 'js/ajax-view-orders.js', array('jquery', 'wp-api'), '1.0', false);
    wp_localize_script('studio-immens-quick-order-ajax-view-orders', 'siqo_ajax_view_orders_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('siqo_ajax_view_orders_vars'),
        'in_footer' => true,
        'strategy'  => 'async',
    ));
}
add_action('wp_enqueue_scripts', 'siqo_enqueue_ajax_view_orders_scripts');

function siqo_enqueue_ajax_manage_restaurant_scripts() {
    wp_localize_script('studio-immens-quick-order-ajax-manage-tables', 'siqo_ajax_manage_restaurant_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('siqo_ajax_manage_restaurant_vars'),
        'in_footer' => true,
        'strategy'  => 'async',
    ));
}

add_action('wp_enqueue_scripts', 'siqo_enqueue_ajax_manage_restaurant_scripts');

function SIQO_load_media_files() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen ? (string) $screen->id : '';
	if ( false === strpos( $id, 'si-quick-order' ) && false === strpos( $id, 'siqo' ) && false === strpos( $id, 'product' ) ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'SIQO_load_media_files' );

function SIQO_register_ajax_action_aliases() {
	$aliases = array(
		'SIQO_submit_order_ajax'          => array( 'SIQO_submit_order_ajax', true ),
		'SIQO_flash_list_order_ajax'      => array( 'SIQO_flash_list_order_ajax', false ),
		'SIQO_flash_tab_clear_table'      => array( 'SIQO_flash_tab_clear_table', false ),
		'SIQO_flash_tab_clear_all_tables' => array( 'SIQO_flash_tab_clear_all_tables', false ),
		'SIQO_flash_tab_pay_ajax'         => array( 'SIQO_flash_tab_pay_ajax', false ),
		'SIQO_insert_post_ajax'           => array( 'SIQO_insert_post_ajax', false ),
		'SIQO_delete_post_ajax'           => array( 'SIQO_delete_post_ajax', false ),
		'SIQO_insert_product_ajax'        => array( 'SIQO_insert_product_ajax', false ),
		'SIQO_trash_post_ajax'            => array( 'SIQO_trash_post_ajax', false ),
		'SIQO_create_any_QR_code_ajax'    => array( 'SIQO_create_any_QR_code_ajax', false ),
		'SIQO_delete_QR_images'           => array( 'SIQO_delete_QR_images', false ),
	);
	foreach ( $aliases as $action => $alias ) {
		if ( ! has_action( 'wp_ajax_' . $action ) ) {
			add_action( 'wp_ajax_' . $action, $alias[0] );
		}
		if ( $alias[1] && ! has_action( 'wp_ajax_nopriv_' . $action ) ) {
			add_action( 'wp_ajax_nopriv_' . $action, $alias[0] );
		}
	}
}
SIQO_register_ajax_action_aliases();






