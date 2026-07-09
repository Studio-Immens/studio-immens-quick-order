<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_nonce_field( 'SIQO_main_page', '_siqononce_main_page' );

SIQO_manage_QR_codes();

SIQO_donate();
