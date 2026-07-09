<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function SIQO_manage_restaurant(){


//------------------

do_action( 'siqo_manage_restaurant_page_head' );

include_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( !is_plugin_active('studio-immens-quick-order-pro/studio-immens-quick-order-pro.php') ) {
    SIQO_pass_to_pro();
    return;
} else{
    do_action( 'siqo_manage_restaurant_pro_page_head' );
    // include_once( WP_PLUGIN_DIR .'/flash_order/public/template/manage-tables.php');
    SIQO_manage_table();
}


// if ( is_plugin_active('studio-immens-quick-order/flash_order.php') ) {
//     include_once( WP_PLUGIN_DIR .'/flash_order/public/template/manage-tables.php');
// }

}



