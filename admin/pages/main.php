<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( 'SIQO_main_page' );
echo '<input type="hidden" id="_siqononce_main_page" name="_siqononce_main_page" value="'.esc_attr($nonce).'" />';

SIQO_manage_QR_codes();

SIQO_donate();
