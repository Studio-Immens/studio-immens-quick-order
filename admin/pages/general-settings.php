<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Variables are local to this included template.

$disp_general = 'display:none;';

$sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : '';
if ( $sub ) {
    if ( $sub == 'general' || $sub == '' ) {
        $disp_general = 'display:flex;';
    }
    if ( $sub == 'pro' ) {
        $disp_pro = 'display:flex;';
    }

} else{
    $disp_general = 'display:flex;';
}

wp_enqueue_style( 'studio-immens-quick-order-settings-css', plugins_url( 'public/css/settings-css.css', dirname(__FILE__, 2) ), array(), STUDIO_IMMENS_QUICK_ORDER_VERSION, 'all' );

?>

<nav>
    <?php do_action('siqo_settings_navigation_start') ?>
    <button onclick="SIQO_settings_navigations( jQuery('#general') );">Generali</button>

    <?php do_action('siqo_settings_navigation_end') ?>
</nav>

<div id="settSection">
<form id="general" method="post" class="SIQOForm" style="<?php echo esc_attr($disp_general); ?>">
    <button name="update" value="update" class="SIQObutton pointer" style="position:sticky;margin: 10px 10px 10px auto;top:45px;"> <?php esc_html_e( 'SALVA', 'studio-immens-quick-order' ); ?> </button>

    <div class="SIQOFormSeparator" onclick="jQuery(`[board='global']`).slideToggle()">
        <b> <?php esc_html_e('Impostazioni Globali' , 'studio-immens-quick-order'); ?> </b>
        <span class="dashicons dashicons-arrow-down"></span>
    </div>

<div class="SIQOSetting_Board" board="global">
<?php

    wp_nonce_field( 'SIQO_save_settings', '_siqononce_save_settings' );

    wp_nonce_field( 'SIQO_manage_pages', '_siqononce_manage_pages' );

SIQO_manage_pages();

SIQO_general_setting( array( 'name' => 'siqo_menu_order',
    'default'   => '5',
    'type'      => 'number',
    'class'     => '',
    'text'      => __('posizione del menu admin' , 'studio-immens-quick-order'),
    'info'      => __('inserisci la posizione del pannello menu nel backend', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_show_admin_bar_all',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('Scegli se mostrare la barra admin', 'studio-immens-quick-order'),
    'info'      => __('Scegli se mostrare la barra admin', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_pickup_delivery_checkout',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('visualizza la sezione per scegliere la modalità di consegna ( ritiro o consegna ), la data e l\'ora, nella pagina (checkout)', 'studio-immens-quick-order'),
    'info'      => __('visualizza la sezione per scegliere la modalità di consegna (ritiro o consegna), la data e l\'ora, nella pagina (checkout)', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_allow_menu_order',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('abilita l\'inserimento ordini dalla pagina del menu ( flash-order-ajax )', 'studio-immens-quick-order'),
    'info'      => __('i clienti potranno inserire gli ordini direttamente dalla pagina del menu', 'studio-immens-quick-order')
) );
?>
    </div>

    <div class="SIQOFormSeparator" onclick="jQuery(`[board='page_manage_order']`).slideToggle()" style="display:none;">
        <b> <?php esc_html_e('Pagina gestione ordini (manage-orders)' , 'studio-immens-quick-order'); ?> </b>
        <span class="dashicons dashicons-arrow-down"></span>
    </div>

<div class="SIQOSetting_Board" board="page_manage_order" style="display:none;">
<?php
?>
    <div class="SIQOFormCategory">
        <b> <?php esc_html_e('impostazioni generali' , 'studio-immens-quick-order'); ?> </b>
    </div>
<?php
SIQO_general_setting( array( 'name' => 'siqo_orders_limit_view',
    'default'   => '200',
    'options'   => '',
    'type'      => 'number',
    'class'     => '',
    'text'      => __('inserisci il numero massimo di ordini da visualizzare nella pagina (manage-order)', 'studio-immens-quick-order'),
    'info'      => __('imposta a "-1" per visualizzare tutti gli ordini', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_orders_date_created',
   'default'   => '24',
   'options'   => '',
   'type'      => 'number',
   'class'     => '',
   'text'      => __('inserisci fino a quante ore prima verranno visualizzati gli ordini nella pagina "manage-order"', 'studio-immens-quick-order'),
   'info'      => __('imposta il numero di ore', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_ajax_refresh_order_seconds',
    'default'   => '2000',
    'other'     => 'min="500"',
    'options'   => '',
    'type'      => 'number',
    'class'     => '',
    'text'      => __('inserisci ogni quanti millisecondi si aggiornano gli ordini nella pagina (manage-order)', 'studio-immens-quick-order'),
    'info'      => __('il valore minimo è 500, cioè 0,5 secondi ( 1000 millisecondi = 1 secondo )', 'studio-immens-quick-order')
) );

?>
    <div class="SIQOFormCategory">
        <b> <?php esc_html_e('Visualizza sezioni della tabella' , 'studio-immens-quick-order'); ?> </b>
    </div>
<?php

SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_id',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('id', 'studio-immens-quick-order'),
    'info'      => __('visualizza l\'id dell\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_name',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('nome', 'studio-immens-quick-order'),
    'info'      => __('visualizza il nome di chi ha effettuato l\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_products',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('prodotti', 'studio-immens-quick-order'),
    'info'      => __('visualizza i prodotti dell\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_info',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('info', 'studio-immens-quick-order'),
    'info'      => __('visualizza le info dell\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_status',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('stato', 'studio-immens-quick-order'),
    'info'      => __('visualizza lo stato dell\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_actions',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('azioni', 'studio-immens-quick-order'),
    'info'      => __('visualizza le azioni possibili per l\'ordine nella tabella', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_manage_orders_table_visualize_totals',
    'options'   => array('yes','no'),
    'type'      => 'select',
    'class'     => '',
    'text'      => __('totale', 'studio-immens-quick-order'),
    'info'      => __('visualizza il totale dell\'ordine nella tabella', 'studio-immens-quick-order')
) );

?>
    </div>

    <div class="SIQOFormSeparator" onclick="jQuery(`[board='advanced']`).slideToggle()">
        <b> <?php esc_html_e('Avanzate' , 'studio-immens-quick-order'); ?> </b>
        <span class="dashicons dashicons-arrow-down"></span>
    </div>

<div class="SIQOSetting_Board" board="advanced" style="display:none;">
<?php

SIQO_general_setting( array( 'name' => 'siqo_custom_shortcode_login',
    'default'   => '[woocommerce_my_account]',
    'type'      => 'textarea',
    'class'     => '',
    'text'      => __('inserisci lo shortcode per effettuare il login dalla finestra pop up. (pagina flash-orders-ajax)', 'studio-immens-quick-order'),
    'info'      => __('inserisci lo shortcode per effettuare il login dalla finestra pop up. (pagina flash-orders-ajax)', 'studio-immens-quick-order')
) );

?>
    <div class="SIQOFormCategory">
        <b> <?php esc_html_e('Tassonomie' , 'studio-immens-quick-order'); ?> </b>
    </div>
<?php
do_action('siqo_setting_advanced');
SIQO_general_setting( array( 'name' => 'siqo_product_ingredients_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('INGREDIENTI', 'studio-immens-quick-order'),
    'text'      => __('utilizza la tassonomia per gli ingredienti dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà una nuova tassonomia per selezionare facilmente gli ingredienti di cui composto un piatto, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_show_product_ingredients_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('VISUALIZZA INGREDIENTI', 'studio-immens-quick-order'),
    'text'      => __('visualizza la tassonomia ingredienti nella pagina del prodotto' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà la tassonomia degli ingredienti di cui composto un piatto, nella pagina prodotto di woocommerce', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_product_allergens_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('ALLERGENI', 'studio-immens-quick-order'),
    'text'      => __('utilizza la tassonomia per gli allergeni dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà una nuova tassonomia per selezionare facilmente gli allergeni di un piatto, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_show_product_allergens_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('VISUALIZZA ALLERGENI', 'studio-immens-quick-order'),
    'text'      => __('visualizza la tassonomia allergeni nella pagina del prodotto' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà la tassonomia degli allergeni di un piatto, nella pagina prodotto di woocommerce', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_product_temperature_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('TEMPERATURE', 'studio-immens-quick-order'),
    'text'      => __('utilizza la tassonomia per le temperature' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà una nuova tassonomia per selezionare facilmente le temperature di consegna che possono essere associate ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_sticker_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('STICKER', 'studio-immens-quick-order'),
    'text'      => __('utilizza la tassonomia per gli sticker' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà una nuova tassonomia per selezionare facilmente gli sticker che possono essere associati ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_macro_categories_tax',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('MACRO CATEGORIE', 'studio-immens-quick-order'),
    'text'      => __('utilizza la tassonomia per le macro categorie' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà una nuova tassonomia per selezionare facilmente le macro categorie che possono essere associate ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
?>
    <div class="SIQOFormCategory">
        <b> <?php esc_html_e('Immagini delle Tassonomie' , 'studio-immens-quick-order'); ?> </b>
    </div>
<?php

SIQO_general_setting( array( 'name' => 'siqo_product_ingredients_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('INGREDIENTI', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per gli ingredienti dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia degli ingredienti, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_allergens_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('ALLERGENI', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per gli allergeni dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia degli allergeni, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_temperature_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('TEMPERATURE', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per le temperature dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia delle temperature, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_sticker_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('STICKER', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per gli sticker dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia degli sticker, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_macro_categories_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('MACRO CATEGORIES', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per le macro categorie dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia delle macro categorie, associate ai prodotti di woocommerce', 'studio-immens-quick-order')
) );
SIQO_general_setting( array( 'name' => 'siqo_product_tags_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('TAG PRODOTTI', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per i tag dei prodotti' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia dei tag, associata ai prodotti di woocommerce', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_posts_tag_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('TAG POST', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per i tag dei post' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia dei tag, associata ai post di wordpress', 'studio-immens-quick-order')
) );

SIQO_general_setting( array( 'name' => 'siqo_posts_category_images',
    'options'   => array( 'yes', 'no' ),
    'type'      => 'select',
    'class'     => '',
    'title'      => __('CATEGORIE POST', 'studio-immens-quick-order'),
    'text'      => __('aggiungi un\'immagine alla tassonomia per le categorie dei post' , 'studio-immens-quick-order'),
    'info'      => __('mostrerà un nuovo meta box per selezionare facilmente l\' immagine da associare alla tassonomia delle categorie, associate ai post di wordpress', 'studio-immens-quick-order')
) );


    wp_nonce_field( 'SIQO_save_settings', '_siqononce_save_settings' );
?>

</div>

<?php
do_action('siqo_setting_general_foot');
?>
<button name="update" value="update" class="SIQObutton pointer" style="margin: 10px auto;"><?php esc_html_e( 'SALVA', 'studio-immens-quick-order' ); ?></button>
</form>

<?php do_action('siqo_settings_sections_end'); ?>

</div>

<?php
SIQO_save_settings( "setting", 'setting' );

