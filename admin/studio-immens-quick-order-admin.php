<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://studioimmens.com
 * @since      1.0.0
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/admin
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function SIQO_initial_setup(){

}

function SIQO_menu_page(){
    $menu_slug = 'si-quick-order';
    $position = (SIQO_get_meta( 'siqo_menu_order') != '')?SIQO_get_meta( 'siqo_menu_order') : '15';

    $link = plugins_url( 'includes/img/logo-20.webp', dirname(__FILE__) );

   add_menu_page( 'Quick Order', esc_html__( 'Quick Order', 'studio-immens-quick-order' ), 'manage_options', $menu_slug, 'SIQO_main_menu_page', $link, $position );
   add_submenu_page( $menu_slug, esc_html__( 'Settings', 'studio-immens-quick-order' ), esc_html__( 'Settings', 'studio-immens-quick-order' ), 'manage_options', $menu_slug.'_settings', 'SIQO_sub_menu_page_settings' );
   add_submenu_page( $menu_slug, esc_html__( 'Style', 'studio-immens-quick-order' ), esc_html__( 'Style', 'studio-immens-quick-order' ), 'manage_options', $menu_slug.'_style', 'SIQO_sub_menu_page_style' );


}
add_action( 'admin_menu', 'SIQO_menu_page' );

function SIQO_head_menu_page(){
    ?>
    <div id="SIQOadminContent">
    <h1 style="margin:30px 0px;display:flex;">
        <img src="<?php echo esc_url('https://studioimmens.com/wp-content/uploads/2023/10/cropped-logo-512-transparent-bg.png');?>" width="50" height="50" alt="light logo">
        Quick Order
        <img src="<?php echo esc_url('https://studioimmens.com/wp-content/uploads/2024/09/logo-512.png');?>" width="50" height="50" alt="light logo">
        <a class="SIQO_abutton" href="https://studioimmens.com/quick-order/" style="margin: 0px 20px 0px auto!important;padding: 0px 10px!important;"><?php esc_html_e( 'GUIDA', 'studio-immens-quick-order' );?></a>
    </h1>
    <?php
}

function SIQO_nav_menu_page(){
    $color1 = 'var(--siqo-main-color)';
    $color2 = 'var(--siqo-bg3-color)';
    $current_page = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';
    $QuickOrder_color = ( $current_page == 'si-quick-order' )? $color1 : $color2;
    $Settings_color = ( $current_page == 'si-quick-order_settings' )? $color1 : $color2;
    $Style_color = ( $current_page == 'si-quick-order_style' )? $color1 : $color2;
    $Addons_color = ( $current_page == 'si-quick-order_addons' )? $color1 : $color2;
    $Pro_color = ( $current_page == 'si-quick-order_pro' )? $color1 : $color2;
    SIQO_head_menu_page();
    ?>
    <nav class="SIQOMainNav">
        <a href="admin.php?page=si-quick-order" class="SIQOMainNavEl" style="background-color:<?php echo esc_attr($QuickOrder_color); ?>;">
        <?php esc_html_e( 'Quick Order', 'studio-immens-quick-order' ); ?></a>
        <a href="admin.php?page=si-quick-order_settings" class="SIQOMainNavEl" style="background-color:<?php echo esc_attr($Settings_color); ?>;">
        <?php esc_html_e( 'Settings', 'studio-immens-quick-order' ); ?></a>
        <a href="admin.php?page=si-quick-order_style" class="SIQOMainNavEl" style="background-color:<?php echo esc_attr($Style_color); ?>">
        <?php esc_html_e( 'Style', 'studio-immens-quick-order' ); ?></a>
        <a href="admin.php?page=si-quick-order_addons" class="SIQOMainNavEl" style="background-color:<?php echo esc_attr($Addons_color); ?>">
        <?php esc_html_e( 'Addons', 'studio-immens-quick-order' ); ?></a>
        <a href="admin.php?page=si-quick-order_pro" class="SIQOMainNavEl" style="background-color:<?php echo esc_attr($Pro_color); ?>">
        <?php esc_html_e( 'Pro', 'studio-immens-quick-order' ); ?></a>
    </nav>
    <?php
}
function SIQO_foot_menu_page( $debug = false ){
    ?>
    </div>
    <?php
    if ( $debug ) {
        SIQO_debug( $_POST );
    }
}
function SIQO_main_menu_page(){
SIQO_nav_menu_page();
include( plugin_dir_path( __FILE__ ) . 'pages/main.php');
SIQO_foot_menu_page();
}
function SIQO_sub_menu_page_settings(){
SIQO_nav_menu_page();
include( plugin_dir_path( __FILE__ ) . 'pages/general-settings.php');
SIQO_foot_menu_page();
}
function SIQO_sub_menu_page_style(){
SIQO_nav_menu_page();
include( plugin_dir_path( __FILE__ ) . 'pages/style.php');
SIQO_foot_menu_page();
}
function SIQO_style_setting( $setting, $style, $type ){
    $style = ( $style == null ) ? SIQO_customized_css() : $style;
    if ( $type == 'color' ) {
        $background_color = $style[$setting];
    }
    ?>
    <div class="SIQOsettingStyle" id="<?php echo 'setting'. esc_attr($setting); ?>"style="background-color:<?php echo esc_attr($background_color); ?>" onclick="document.getElementById(<?php echo'`'.esc_attr($setting).'`';?>).click();">
        <p title="<?php echo esc_attr($setting); ?>"> <?php echo esc_attr($setting); ?>
            <input id="<?php echo esc_attr($setting); ?>" type="<?php echo esc_attr($type); ?>" name="css[<?php echo esc_attr($setting); ?>]" value="<?php echo esc_attr($style[$setting]); ?>" oninput="document.getElementById(<?php echo'`setting'.esc_attr($setting).'`';?>).style.backgroundColor = this.value;">
            <span class="dashicons dashicons-image-rotate pointer" onclick="SIQO_adm_restore_prev_prev( this, <?php echo "'".esc_attr($style[$setting])."'";?> )"></span>
        </p>
    </div>
    <?php
}

function SIQO_new_style_setting( $setting, $args = array() ){
    $default_css = SIQO_default_css();
    $args['type'] = (isset($args['type']) && $args['type']!='')?$args['type']:'color';

    $set_val = SIQO_get_meta($setting);
    $background_color = '';

    if ( $args['type'] == 'color' ) {
        $background_color = $set_val;
    }
    ?>
    <div class="SIQOsettingStyle" id="<?php echo 'setting'.esc_attr($setting); ?>"style="background-color:<?php echo esc_attr($background_color); ?>" onclick="document.getElementById(<?php echo'`'.esc_attr($setting).'`';?>).click();">
        <p title="<?php echo esc_attr($setting); ?>"> <?php echo esc_attr($setting); ?>
            <input id="<?php echo esc_attr($setting); ?>" type="<?php echo esc_attr($args['type']); ?>" name="css[<?php echo esc_attr($setting); ?>]" value="<?php echo esc_attr($set_val); ?>" oninput="document.getElementById(<?php echo'`setting'.esc_attr($setting).'`';?>).style.backgroundColor = this.value;">
            <span class="dashicons dashicons-image-rotate pointer" onclick="SIQO_adm_restore_prev_prev( this, <?php echo "'".esc_attr($default_css[$setting])."'";?> )" title="<?php esc_html_e('Ripristina il valore di default', 'studio-immens-quick-order' );?>"></span>
        </p>
    </div>
    <?php
}

function SIQO_general_setting( $setting = array() ){
    $name = ( isset($setting['name']) ) ? $setting['name'] : '';
    $title = ( isset($setting['title']) ) ? $setting['title'] : '';
    $default = ( isset($setting['default']) ) ? $setting['default'] : null;
    $data_default = ( SIQO_get_meta($name) ) ? SIQO_get_meta($name) : $default;
    $options = ( isset($setting['options']) ) ? $setting['options'] : array();
    $type = ( isset($setting['type']) ) ? $setting['type'] : 'text';
    $class = ( isset($setting['class']) ) ? $setting['class'] : '';
    $text = ( isset($setting['text']) ) ? $setting['text'] : '';
    $info = ( isset($setting['info']) ) ? $setting['info'] : '';
    $other = ( isset($setting['other']) ) ? $setting['other'] : '';

    $id = ( isset($setting['id']) ) ? $setting['id'] : '';
    $bool = ( isset($setting['bool']) ) ? $setting['bool'] : '';
    ?>

    <div id="<?php echo esc_attr($id);?>" class="SIQOsettingEl <?php echo esc_attr($class);?>" title="<?php echo esc_attr($info).' ______ '.esc_html_e('nome dell\'impostazione nel database: ( ', 'studio-immens-quick-order').esc_attr($name).' )';?>">
        <?php if($title != ''){ ?>
            <strong class="SIQOtextSettings" style="flex-basis:100%"><?php echo esc_attr($title);?></strong>
        <?php }?>
        <p class="SIQOtextSettings"><?php echo esc_attr($text);?></p>
        <?php if($type == 'audio'){
            $audio = ( SIQO_get_meta('siqo_notify_audio') ) ? SIQO_get_meta('siqo_notify_audio') : '';
            $options = array_diff(scandir(plugin_dir_path(__DIR__).'includes/audio/'),array('..', '.'));
            ?>
            <select name="setting[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($data_default); ?>" onchange="addSrcToAudio(this.value);" <?php echo esc_attr($other);?>>
                <option selected disabled hidden><?php esc_html_e('Seleziona l\'audio...', 'studio-immens-quick-order'); ?></option>
                <?php if ( count($options) ) { ?>
                    <?php foreach ($options as $option) { ?>
                        <option value="<?php echo esc_url( plugins_url( 'includes/audio/', dirname(__FILE__, 2) ) ) . esc_attr($option);?>"><?php echo esc_attr($option);?></option>
                    <?php } ?>
                <?php } ?>
            </select>
            <audio id="SIQOadminAudio" controls autoplay>
              <source  src="<?php echo esc_attr($audio);?>" type="audio/mpeg">
                Your browser does not support the audio element.
            </audio>
            <script>
              var audio = document.getElementById("SIQOadminAudio");
              audio.volume = 0.1;
            </script>
        <?php } elseif($type == 'textarea'){ ?>
           <textarea type="<?php echo esc_attr($type);?>" name="setting[<?php echo esc_attr($name);?>]" <?php echo esc_attr($other);?>><?php echo esc_attr($data_default);?></textarea>
        <?php } elseif ($type != 'select') { ?>
            <input type="<?php echo esc_attr($type); ?>" name="setting[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($data_default);?>" <?php echo esc_attr($other);?>>
        <?php } else{ ?>
            <select name="setting[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($data_default); ?>" <?php echo esc_attr($other);?>>
                <option selected disabled hidden><?php echo esc_attr($data_default); ?></option>
                <?php if ( count($options) ) { ?>
                    <?php foreach ($options as $option) { ?>
                        <option value="<?php echo esc_attr($option);?>"><?php echo esc_attr($option);?></option>
                    <?php } ?>
                <?php } ?>
            </select>
        <?php } ?>
        <?php if ( $default != '' ) { ?>
            <span class="dashicons dashicons-image-rotate pointer" onclick="SIQO_adm_restore_prev_prev( this, <?php echo "'".esc_attr($default)."'";?> )"></span>
        <?php } ?>
    </div>

    <?php
}

function SIQO_conditional_setting( $setting = array() ){
    $name = ( isset($setting['name']) ) ? $setting['name'] : '';
    $title = ( isset($setting['title']) ) ? $setting['title'] : '';

    $default = ( isset($setting['default']) ) ? $setting['default'] : '';
    $data_default = ( SIQO_get_meta($name) ) ? SIQO_get_meta($name) : $default;

    $options = ( isset($setting['options']) ) ? $setting['options'] : array();
    $type = ( isset($setting['type']) ) ? $setting['type'] : 'text';

    $id = ( isset($setting['id']) ) ? $setting['id'] : '';
    $class = ( isset($setting['class']) ) ? $setting['class'] : '';

    $text = ( isset($setting['text']) ) ? $setting['text'] : '';
    $info = ( isset($setting['info']) ) ? $setting['info'] : '';
    $other = ( isset($setting['other']) ) ? $setting['other'] : '';

    $bool = ( isset($setting['bool']) ) ? $setting['bool'] : '';
    $siqo_dep = ( isset($setting['siqo_dep']) ) ? $setting['siqo_dep'] : '';
    $target_bool = ( isset($setting['target_bool']) ) ? $setting['target_bool'] : '';
    ?>

    <div id="<?php echo esc_attr($id);?>" class="SIQOsettingEl <?php echo esc_attr($class);?>" title="<?php echo esc_attr($info).' ______ '.esc_html_e('nome dell\'impostazione nel database: ( ', 'studio-immens-quick-order').esc_attr($name).' )';?>" siqo_val="<?php echo esc_attr($data_default);?>" siqo_dep="<?php echo esc_attr($siqo_dep);?>">
        <?php if ($title != '') { ?>
            <strong class="SIQOtextSettings" style="flex-basis:100%"><?php echo esc_attr($title);?></strong>
        <?php } ?>
        <p class="SIQOtextSettings"><?php echo esc_attr($text);?></p>
        <?php if ($type == 'radio') { ?>
            <input type="<?php echo esc_attr($type); ?>" name="setting[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($data_default);?>" <?php echo esc_attr($other);?>>
        <?php } elseif ($type == 'select'){ ?>
            <select name="setting[<?php echo esc_attr($name);?>]" value="<?php echo esc_attr($data_default);?>" onchange="SIQO_condition_setting(this);" siqo_bool="<?php echo esc_attr($bool);?>" target_bool="<?php echo esc_attr($target_bool);?>" <?php echo esc_attr($other);?>>
                <option selected disabled hidden><?php echo esc_attr($data_default);?></option>
                <?php if ( count($options) ) { ?>
                    <?php foreach ($options as $option) { ?>
                        <option value="<?php echo esc_attr($option);?>"><?php echo esc_attr($option);?></option>
                    <?php } ?>
                <?php } ?>
            </select>
        <?php } else{ } ?>
        <?php if ( $default != '' ) { ?>
            <span class="dashicons dashicons-image-rotate pointer" onclick="SIQO_adm_restore_prev_prev( this, <?php echo "'".esc_attr($default)."'";?> )"></span>
        <?php } ?>
    </div>

    <?php
}

function SIQO_set_local_network( $setting = array() ){
    $net_info = FO_get_connection_info();

    $name = ( isset($setting['name']) ) ? $setting['name'] : '';

    $data_default = ( SIQO_get_meta('siqo_limit_ip_to_local_network') ) ? SIQO_get_meta('siqo_limit_ip_to_local_network') : '';

    $class = ( isset($setting['class']) ) ? $setting['class'] : '';

    $text = ( isset($setting['text']) ) ? $setting['text'] : '';
    $info = ( isset($setting['info']) ) ? $setting['info'] : '';
    $other = ( isset($setting['other']) ) ? $setting['other'] : '';

    $bool = ( isset($setting['bool']) ) ? $setting['bool'] : 'yes';
    $siqo_dep = ( isset($setting['siqo_dep']) ) ? $setting['siqo_dep'] : '#SIQO_limit_ip_to_local_network';
    ?>
    <div id="SIQO_select_local_network" class="SIQOsettingEl <?php echo esc_attr($class);?>" title="" style="max-width:100%;flex-basis:100%;" siqo_val="<?php echo esc_attr($data_default);?>" siqo_dep="<?php echo esc_attr($siqo_dep);?>">
        <strong class="SIQOtextSettings title" style="flex-basis:100%;">
            <?php esc_html_e('Impostazioni di rete', 'studio-immens-quick-order' );?>
        </strong>
        <input type="text" name="setting[ipv4]" value="<?php echo esc_attr($net_info['ip']);?>">
    </div>
    <?php
}

function SIQO_save_settings( $args, $assoc_id = '', $debug = false ){
    if ( isset($_POST["update"]) && current_user_can( 'manage_options' ) ) {
        if ( isset($_POST['_siqononce_save_settings']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_siqononce_save_settings'])), 'SIQO_save_settings' ) ) {
            return;
        }
        if ( isset( $_POST[$args] ) ) {
            $sany_args = SIQO_recursive_sanitize_text_field(wp_unslash($_POST[$args]));
            foreach ($sany_args as $key => $value) {
                SIQO_update_meta( sanitize_text_field(wp_unslash($key)), sanitize_text_field(wp_unslash($value)), $assoc_id );
            }
        }
        if ( isset( $_POST['PGSett'] ) ) {
            $assoc_id = 'page_settings';
            foreach ( $_POST['PGSett'] as $PGSett_K => $PGSett_V ) {
                $settings = json_decode(SIQO_get_meta( sanitize_text_field(wp_unslash($PGSett_K)) ));

                $default_sett = array(
                  'showHead'    => 'yes',
                  'showFoot'    => 'yes',
                  'showQR'      => 'yes',
                  'OverColor'   => 'no',
                  'BGColor'     => '#00000f',
                  'TextColor'   => '#ffffff',
                  'EXTCss'      => 'body, header, footer',
                  'info'        => '',
                  'last_update' => wp_date('Y-m-d H:i:s')
                  );

                $new_sett = wp_parse_args( $settings, $default_sett );

                $fin_sett = wp_parse_args( $PGSett_V, $new_sett );

                SIQO_update_meta( sanitize_text_field(wp_unslash($PGSett_K)), wp_json_encode($fin_sett), $assoc_id );
            }
        }
        if ($debug) {
            SIQO_debug($_POST);
        } else{
            if (isset($_SERVER['REQUEST_URI'])) {
                $req_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                wp_redirect( $req_uri );
                exit;
            }
        }
    }
}

function SIQO_get_post_by_post_name( $slug = '', $post_type = '' ) {
    if ( !$slug  || !$post_type ){
        return false;
    }
    $post_object = get_page_by_path( $slug, OBJECT, $post_type );
    if ( !$post_object )
        return false;

    return $post_object;
}

function SIQO_manage_pages( $setting = array() ){
    $siqo_pages = SIQO_get_meta_by_assoc_id( 'page_id', 'OBJECT' );
?>
    <div class="SIQOsettingEl"style="max-width:100%;flex-basis:100%;">
        <strong class="SIQOtextSettings title" style="flex-basis:100%;">
            <?php esc_html_e('Gestione Pagine del Plugin', 'studio-immens-quick-order'); ?>
        </strong>
        <?php foreach ($siqo_pages as $key => $value) {
            if ( get_post( $value->meta_value ) == null ) {
                SIQO_delete_meta( $value->meta_key );
                continue;
            }
            ?>
            <div class="SIQOsettingEl">
                <strong class="SIQOtextSettings"style="flex-basis:100%;">
                    <?php
                    echo esc_attr(substr($value->meta_key, 8)); ?>
                </strong>
                 <?php
                    echo '<p class="SIQOtextSettings">'.esc_html__('Ultima modifica della pagina: ', 'studio-immens-quick-order').esc_attr(SIQO_get_meta('last_update_'.substr($value->meta_key, 8))).'</p>';
                    echo '<p class="SIQOtextSettings">'.esc_html__('l\'ID della pagina ', 'studio-immens-quick-order').esc_attr($value->meta_value).'</p>';
                    echo '<p class="SIQOtextSettings">'.esc_html__('la versione della pagina ', 'studio-immens-quick-order').esc_attr(SIQO_get_meta('page_version_'.substr($value->meta_key, 8))).'</p>';
                ?>
                <div class="SIQObutton" style="height:20px;cursor:pointer;" onclick="SIQO_toggle_pages_rapid_impost(<?php echo '`'.esc_attr($value->meta_value).'`'?>)">
                    <?php esc_html_e('IMPOSTAZIONI', 'studio-immens-quick-order');?>
                </div>

                <?php SIQO_get_pages_impost($value->meta_value);?>

                <div class="SIQObutton" style="height:20px;cursor:pointer;" onclick="SIQO_delete_page(<?php echo "'".esc_attr( substr($value->meta_key, 8) )."'"; ?>);">
                    <?php esc_html_e('ELIMINA pagina', 'studio-immens-quick-order');?>
                </div>
                <a class="SIQObutton" style="height:20px;cursor:pointer;text-decoration:none;" href="<?php echo esc_attr(get_home_url()).'?p='.esc_attr($value->meta_value);?>">
                    <?php esc_html_e('VISITA pagina', 'studio-immens-quick-order');?>
                </a>
            </div>
       <?php } ?>
        <div class="SIQObutton" style="height:20px;cursor:pointer;" onclick="SIQO_create_refresh_pages();" title="<?php esc_html_e( 'Aggiorna tutte le pagine necessarie al plugin, e se non sono ancora state create, le Crea.', 'studio-immens-quick-order' );?>">
             <?php esc_html_e('CREA o AGGIORNA pagine', 'studio-immens-quick-order');?>
        </div>
    </div>
<?php
}

function SIQO_donate(){
    ?>
    <div class="SIQO_section">
        <?php
        esc_html_e('Se vuoi sostenerci con una donazione, ','studio-immens-quick-order');
        echo '<a href="https://studioimmens.com/donate" target="_blank">'.esc_html__(' Visita Questa Pagina ','studio-immens-quick-order').'</a>';
        esc_html_e('. Ogni tuo gesto  per noi un prezioso impegno, e siamo lieti di ascoltare le tue proposte e suggerimenti.','studio-immens-quick-order');
        ?>
    </div>
    <?php
}

function SIQO_submit_suggestions(){
    ?>
    <div class="SIQO_section">
        <?php
        esc_html_e('Se desideri darci un feedback o fare una richiesta, ','studio-immens-quick-order');
        echo '<a href="https://studioimmens.com/feedback" target="_blank">'.esc_html__(' Clicca QUI ','studio-immens-quick-order').'</a>';
        esc_html_e('. Siamo sempre pronti ad ascoltare le tue opinioni e suggerimenti, impegnandoci al massimo per soddisfare ogni tua richiesta con attenzione e dedizione.','studio-immens-quick-order');
        ?>
    </div>
    <?php
}

function SIQO_statistics_in_flash(){
    ?>
    <div class="SIQOForm">

        <div class="SIQOsettingEl">
            <strong class="SIQOtextSettings">
                <?php echo esc_html__( ' Statistiche in Flash: ', 'studio-immens-quick-order' ); ?>
            </strong>

        </div>

    </div>
    <?php
}

function SIQO_manage_QR_codes(){
    ?>
    <div class="SIQOForm">

        <div class="SIQOsettingEl"  style="max-width:100%;flex-basis:100%;">
            <strong class="SIQOtextSettings" style="max-width:100%;flex-basis:100%;">
                <?php esc_html_e( ' Gestore QR codes: ', 'studio-immens-quick-order' ); ?>
            </strong>

            <div class="SIQOSetting_Board SIQOsettingEl" style="max-width:100%;flex-basis:100%;">
                <div class="SIQOtextSettings">
                    <b><?php esc_html_e( ' Shortcodes disponibili: ', 'studio-immens-quick-order' ); ?></b>
                </div>

                <div class="SIQOtextSettings" style="max-width:100%;flex-basis:100%;text-align:left;">
                </div>

                <strong class="SIQOtextSettings">[siqo_qr_code]</strong>
                <div style="width:100%;height:1px"></div>

                <p class="SIQOtextSettings">
                    <?php esc_html_e('Questo shortcode va utilizzato direttamente sulla pagina di cui vuoi visualizzare il QRcode.','studio-immens-quick-order');?>
                </p>
                <div style="width:100%;height:1px"></div>

                <div class="SIQOtextSettings">
                    <?php esc_html_e( 'I parametri disponibili sono:' , 'studio-immens-quick-order' ); ?>
                    <strong class="SIQOtextSettings">size</strong> <?php esc_html_e( '(default: 150)' , 'studio-immens-quick-order' ); ?>
                </div>
                <div style="width:100%;height:1px"></div>

                <div class="SIQOtextSettings">
                     <?php esc_html_e( 'Esempio di utilizzo:' , 'studio-immens-quick-order' ); ?>
                     <?php echo '[siqo_qr_code size="300"]'; ?>
                </div>
                <div style="width:100%;height:20px"></div>

                <div style="width:100%;height:1px;border-top:1px solid black;"></div>

                <strong class="SIQOtextSettings">[siqo_qr_code_content]</strong>
                <div style="width:100%;height:1px"></div>

                <p class="SIQOtextSettings">
                    <?php esc_html_e('Questo shortcode va utilizzato sulla pagina su cui vuoi visualizzare il QRcode, ma il contenuto del QRcode  definito dal parametro content.','studio-immens-quick-order');?>
                </p>
                <div style="width:100%;height:1px"></div>

                <div class="SIQOtextSettings">
                    <?php esc_html_e( 'I parametri disponibili sono:' , 'studio-immens-quick-order' ); ?>
                    <strong class="SIQOtextSettings">size</strong> <?php esc_html_e( '(default: 150)' , 'studio-immens-quick-order' ); ?>
                    <strong class="SIQOtextSettings">content</strong> <?php esc_html_e( '(default: home url)' , 'studio-immens-quick-order' ); ?>
                </div>
                <div style="width:100%;height:1px"></div>

                <div class="SIQOtextSettings">
                    <?php esc_html_e( 'Esempio di utilizzo:' , 'studio-immens-quick-order' ); ?>
                    <?php echo '[siqo_qr_code_content size="300" content="'.esc_html__( 'La frase o il link che volete utilizzare per il QR' , 'studio-immens-quick-order' ).'"]'; ?>
                </div>
                <div style="width:100%;height:20px"></div>
            </div>

            <div class="SIQOQRCloud">

                <div class="SIQO_NEW_QR SIQOtextSettings SIQOsettingEl" style="max-width:100%;flex-basis:100%;">
                    <div class="SIQOtextSettings"><?php esc_html_e( 'Crea un nuovo shortcode:' , 'studio-immens-quick-order' ); ?></div>
                    <span id="SIQO_fill_content_QR" style="display:none;"><?php esc_html_e( 'Per poter creare il QRcode, devi inserire il contenuto nel campo content!', 'studio-immens-quick-order' ); ?></span>
                    <input type="text" class="SIQOtextSettings" name="NEW_QR_size" placeholder="size (default 150)">
                    <input type="text" class="SIQOtextSettings" name="NEW_QR_content" placeholder="content*" style="width:calc(80% - 40px);">
                    <button class="SIQObutton" onclick="siqo_create_QR(jQuery(this).parent());">
                        <?php esc_html_e('CREA','studio-immens-quick-order');?>
                    </button>
                </div>

                <input type="text" class="SIQOtextSettings" target="SIQO_search_target" onkeyup="siqo_search_for_file(this)" onkeydown="siqo_search_for_file(this)" style="max-width:100%;flex-basis:100%;text-align:left;margin:15px;" placeholder="<?php esc_html_e(' Cerca QR ... ','studio-immens-quick-order' );?>">
            <?php
                $QR_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/QRgenerate/';
                if ( ! is_dir( $QR_dir ) ) {
                    wp_mkdir_p( $QR_dir );
                }
                $QR_img = is_dir( $QR_dir ) ? (array) scandir( $QR_dir ) : array();
                foreach ($QR_img as $key => $value) {
                    if ($value != '.' && $value != '..') {
                        $QR_url = esc_url_raw( plugin_dir_url( dirname(__FILE__) ) . 'includes/phpqrcode/QRgenerate/' . $value);
                        $size = substr($value,strpos($value,'(')+1, -5);
                        $size = ($size >= '450' )?'450':$size;
                        $RealName = (!str_contains($value,'any-')) ? esc_attr($value): str_replace('any-', '', $value);
                        ?>
                            <div target="<?php echo esc_attr($RealName);?>" class="SIQOsettingEl SIQOtextSettings SIQO_search_target">
                                <img src="<?php echo esc_attr($QR_url);?>" width="<?php echo esc_attr($size);?>" height="<?php echo esc_attr($size);?>">

                                <strong class="SIQOtextSettings" style="display:flex;flex-wrap:wrap;flex-direction:column;">
                                    <?php echo esc_attr($RealName);?>
                                    <a class="SIQO_link" href="<?php echo esc_attr($QR_url);?>" download="<?php echo esc_attr($RealName);?>">
                                        <?php esc_html_e('Scarica Immagine','studio-immens-quick-order');?>
                                    </a>
                                    <a class="SIQO_link_del" del_path="<?php echo esc_attr(plugin_dir_path(dirname(__FILE__))).'includes/phpqrcode/QRgenerate/'.esc_attr($value);?>" onclick="SIQO_delete_QR_images(this);">
                                        <?php esc_html_e('Elimina Immagine','studio-immens-quick-order');?>
                                    </a>
                                </strong>
                            </div>
                        <?php
                    }
                }
            ?>
            </div>
        </div>
    </div>
    <?php
}

