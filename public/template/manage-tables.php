<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function SIQO_manage_table( $embedded = false ){

    if ( ! $embedded ) {
        do_action( 'siqo_manage_tables_page_head' );
    }

    $tavoli = get_posts( array(
        'numberposts'      => 100,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'post_type'        => 'siqo_tables',
    ) );

    $settings_page_id = get_the_ID();
    if ( $embedded ) {
        $settings_page = get_page_by_path( 'manage-tables' );
        if ( $settings_page ) {
            $settings_page_id = $settings_page->ID;
        }
    }

    $settings = SIQO_get_meta( 'setting_page_id' . $settings_page_id );
    if ( isset($settings) && $settings != null ) {
        $settings = json_decode($settings);
    }
    
    $nonce = wp_create_nonce( 'SIQO_insert_post_ajax_nonce' );
    echo '<input type="hidden" id="_siqononce_insert_post_ajax_nonce" name="_siqononce_insert_post_ajax_nonce" data-plan-nonce value="'.esc_attr($nonce).'" />';
    
    if ( function_exists( 'SIQO_get_settings_head_manage_tables' ) ) {
        SIQO_get_settings_head_manage_tables();
    } elseif ( function_exists( 'SIQO_get_settings_head_manage_tables' ) ) {
        SIQO_get_settings_head_manage_tables();
    }

    // $tavoli_info = FO_get_all_tables_by_status_last();
    // SIQO_debug($tavoli_info);
    if ( ! $embedded ) {
        if (isset($settings->showQR) && $settings->showQR == 'yes' ) {
            echo SIQO_create_post_QR_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe renderer output.
        }

        if (isset($settings->showHead) && $settings->showHead == 'no' ) { ?>
            <script type="text/javascript">
                jQuery(window).on('load', function() {
                    jQuery('header').not('.siqo-app-header').css('display','none');
                });
            </script>
        <?php }

        if (isset($settings->showFoot) && $settings->showFoot == 'no' ) { ?>
            <script type="text/javascript">
                jQuery(window).on('load', function() {
                    jQuery('footer').not('.siqo-story-foot').css('display','none');
                });
            </script>
        <?php }

        if (isset($settings->OverColor) && $settings->OverColor == 'yes' ) { ?>
            <style type="text/css">
                <?php if (isset($settings->EXTCss) && $settings->EXTCss != '' ){
                    echo esc_attr($settings->EXTCss);?>{
                        background-color: <?php echo esc_attr($settings->BGColor);?>!important;
                        color: <?php echo esc_attr($settings->TextColor);?>!important;
                    }
                <?php } ?>
            </style>
        <?php }
    }
?>
<div id="SIQO_Front_Content" class="<?php echo $embedded ? 'siqo-embed' : ''; ?>">
    <script type="text/javascript">document.documentElement.classList.add('siqo-operator-mode');</script>
    <?php if ( ! $embedded ) : ?>
        <?php $siqo_brand = SIQO_Management::get_brand(); ?>
        <header class="siqo-app-header">
            <div class="siqo-brand">
                <?php if ( ! empty( $siqo_brand['logo'] ) ) : ?>
                    <img class="siqo-brand-logo" src="<?php echo esc_url( $siqo_brand['logo'] ); ?>" alt="">
                <?php else : ?>
                    <span class="siqo-brand-mark" aria-hidden="true"><?php echo esc_html( mb_substr( $siqo_brand['name'], 0, 1 ) ); ?></span>
                <?php endif; ?>
                <div class="siqo-brand-text">
                    <strong><?php echo esc_html( $siqo_brand['name'] ); ?></strong>
                    <span><?php esc_html_e( 'Gestione tavoli', 'studio-immens-quick-order' ); ?></span>
                </div>
            </div>
            <div class="siqo-summary" aria-live="polite" aria-label="Stato tavoli">
                <span class="siqo-summary-item siqo-summary-item--free"><span class="siqo-summary-dot" aria-hidden="true"></span><b class="siqo-count-free">0</b><span class="siqo-summary-label">liberi</span></span>
                <span class="siqo-summary-item siqo-summary-item--busy"><span class="siqo-summary-dot" aria-hidden="true"></span><b class="siqo-count-busy">0</b><span class="siqo-summary-label">occupati</span></span>
            </div>
            <div class="siqo-app-actions">
                <button type="button" class="siqo-app-btn" onclick="window.location.reload()">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <span class="siqo-btn-label">Aggiorna</span>
                </button>
                <a class="siqo-app-btn siqo-app-btn--logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
                    <span class="dashicons dashicons-exit" aria-hidden="true"></span>
                    <span class="siqo-btn-label">Esci</span>
                </a>
            </div>
        </header>
        <?php
        if (function_exists('FOP_tab_status_bar')){FOP_tab_status_bar();}

        if (function_exists('FOP_tab_Statistics_section')){
            FOP_tab_Statistics_section();
        }

        $settings_section = function_exists('SIQOP_tab_Settings_section') ? 'SIQOP_tab_Settings_section' : (function_exists('FOP_tab_Settings_section') ? 'FOP_tab_Settings_section' : '');
        if ($settings_section){
            call_user_func($settings_section, $tavoli);
        ?>
        <div class="fo_button fo_show_settings" onclick="SIQO_settings_show();">
            <span class="dashicons dashicons-admin-settings" style="width:40px;height:40px;font-size:40px;"></span>
        </div>

        <?php }?>
    <?php endif; ?>

    <?php if ( ! empty( $tavoli ) ) : ?>
        <div class="siqo-plan-toolbar" data-plan-toolbar>
            <div class="siqo-plan-switch" role="group" aria-label="<?php esc_attr_e( 'Vista tavoli', 'studio-immens-quick-order' ); ?>">
                <button type="button" class="is-active" data-plan-view="grid" aria-pressed="true"><?php esc_html_e( 'Griglia', 'studio-immens-quick-order' ); ?></button>
                <button type="button" data-plan-view="plan" aria-pressed="false"><?php esc_html_e( 'Disposizione', 'studio-immens-quick-order' ); ?></button>
            </div>
            <button type="button" class="siqo-plan-edit" data-plan-edit aria-pressed="false"><?php esc_html_e( 'Modifica disposizione', 'studio-immens-quick-order' ); ?></button>
            <button type="button" class="siqo-plan-manage" data-plan-manage aria-expanded="false" aria-controls="siqo-plan-manager"><?php esc_html_e( 'Gestisci', 'studio-immens-quick-order' ); ?></button>
            <div class="siqo-plan-zoom" role="group" aria-label="<?php esc_attr_e( 'Zoom', 'studio-immens-quick-order' ); ?>">
                <button type="button" data-plan-zoom="out" aria-label="<?php esc_attr_e( 'Riduci zoom', 'studio-immens-quick-order' ); ?>">&minus;</button>
                <span data-plan-zoom-value>100%</span>
                <button type="button" data-plan-zoom="in" aria-label="<?php esc_attr_e( 'Aumenta zoom', 'studio-immens-quick-order' ); ?>">+</button>
                <button type="button" data-plan-zoom="fit"><?php esc_html_e( 'Adatta', 'studio-immens-quick-order' ); ?></button>
            </div>
            <button type="button" data-plan-reload><?php esc_html_e( 'Ricarica disposizione', 'studio-immens-quick-order' ); ?></button>
            <span class="siqo-plan-status" data-plan-status aria-live="polite"></span>
            <div class="siqo-plan-tabs" data-plan-tabs role="tablist" aria-label="<?php esc_attr_e( 'Piani', 'studio-immens-quick-order' ); ?>"></div>
            <p class="siqo-plan-hint" data-plan-hint hidden><?php esc_html_e( 'Modifica da computer o tablet', 'studio-immens-quick-order' ); ?></p>
        </div>
        <div class="siqo-plan-area" data-plan-area role="region" aria-label="<?php esc_attr_e( 'Disposizione tavoli', 'studio-immens-quick-order' ); ?>" hidden>
            <div class="siqo-plan-stage" data-plan-stage>
                <div class="siqo-plan-canvas" id="siqo-plan-canvas" data-plan-canvas role="tabpanel" tabindex="-1">
                    <div class="siqo-plan-zones" data-plan-zones></div>
                </div>
            </div>
        </div>
        <div class="siqo-plan-inspector" data-plan-inspector role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Proprietà elemento', 'studio-immens-quick-order' ); ?>" hidden></div>
        <div class="siqo-plan-manager" id="siqo-plan-manager" data-plan-manager role="dialog" aria-modal="false" aria-labelledby="siqo-plan-manager-title" hidden></div>
        <input type="file" accept="image/jpeg,image/png,image/webp" data-plan-file hidden>
    <?php endif; ?>

    <div class="FO_table_grid" data-plan-grid>
        <div class="FOloadingMessage FOloadingCardPublic FOloadingCardPublicMain tab_fix_load" style="">
            <span style="animation: fospin 1s infinite;font-size:120px;width:120px;height:120px;" class="dashicons dashicons-update"></span>
        </div>
        
        <?php 

        foreach ( $tavoli as $key => $tavolo ) {
        $zone = get_the_terms( $tavolo->ID, 'siqo_zones' );
        if ( is_wp_error( $zone ) ) {
            $zone = false;
        }
        $macro_status = get_the_terms( $tavolo->ID, 'siqo_table_status' );
        if ( is_wp_error( $macro_status ) ) {
            $macro_status = false;
        }
        $status = SIQO_get_meta( 'status_table_'.$tavolo->ID );
        if ( $status === '' || $status === null || $status === false ) {
            $status = 0;
        }
        $plan_position = get_post_meta( $tavolo->ID, '_siqo_plan_position', true );
        $plan_x = isset( $plan_position['x'] ) ? absint( $plan_position['x'] ) : 40 + ( $key % 4 ) * 220;
        $plan_y = isset( $plan_position['y'] ) ? absint( $plan_position['y'] ) : 40 + intdiv( $key, 4 ) * 160;
        $plan_w = isset( $plan_position['w'] ) ? absint( $plan_position['w'] ) : 180;
        $plan_h = isset( $plan_position['h'] ) ? absint( $plan_position['h'] ) : 120;
        $plan_floor = SIQO_Plan_Layout::table_floor( $tavolo->ID );
        $plan_zone = SIQO_Plan_Layout::table_zone( $tavolo->ID );
        $plan_shape = get_post_meta( $tavolo->ID, '_siqo_plan_shape', true );
        $plan_shape = in_array( $plan_shape, array( 'rect', 'square', 'round' ), true ) ? $plan_shape : 'rect';
        $plan_seats = (int) get_post_meta( $tavolo->ID, '_siqo_plan_seats', true );
        // SIQO_debug($status);
        if ( $macro_status != false ) {
            if ($macro_status[0]->name == 'Libero' || $macro_status[0]->name == 'Aperto' || $macro_status[0]->name == 'Open') {
                $tab_check = true;
            } else{
                $tab_check = false;
            }
        } else { $tab_check = true;}
        if ( $tab_check ) {
            if ($status == 0) {
                $Style = 'background-color:var(--fo-tab-1);';$status_string = __( 'Libero', 'studio-immens-quick-order' );
            } elseif ($status == 1) {
                $Style = 'background-color:var(--fo-tab-2);';$status_string = __( 'Occupato', 'studio-immens-quick-order' );
            } elseif ($status == 2) {
                $Style = 'background-color:yellow;';$status_string = __( 'Da incassare', 'studio-immens-quick-order' );
            } else{
                $Style = 'background-color:var(--fo-bg4-color);';$status_string = '';
            }
        } else{
            $Style = 'background-color:var(--fo-bg4-color);';$status_string = '';
        }
            // $tab_info = FO_get_table_by_table_id_status_last($tavolo->ID);
            // SIQO_debug( $tab_info);
            ?>
            <div class="fo_table_cell relative" fotable="<?php echo esc_attr($tavolo->post_title);?>" fo_tableid="<?php echo esc_attr($tavolo->ID);?>" fotable_status="<?php echo esc_attr($status);?>" style="<?php echo esc_attr($Style);?>" onclick="SIQO_tab_Card_show( this )" table_start_time="" table_last_update="" table_orders="" table_info="" table_receipt="" table_other="" table_totals="" table_end_time="" table_table_tableid="" data-plan-id="<?php echo esc_attr($tavolo->ID);?>" data-plan-x="<?php echo esc_attr($plan_x);?>" data-plan-y="<?php echo esc_attr($plan_y);?>" data-plan-w="<?php echo esc_attr($plan_w);?>" data-plan-h="<?php echo esc_attr($plan_h);?>" data-plan-floor="<?php echo esc_attr($plan_floor);?>" data-plan-zone="<?php echo esc_attr($plan_zone);?>" data-plan-shape="<?php echo esc_attr($plan_shape);?>" data-plan-seats="<?php echo esc_attr($plan_seats);?>" data-table-name="<?php echo esc_attr($tavolo->post_title);?>" data-table-status-label="<?php echo esc_attr($status_string);?>">

                <span class="fo_tab_notify" fo_not_counter="" onclick="" style="display:none;">0</span>
                
                <div class="fo_tab_abs_info" style="display: none;" onclick="" >
                    <span class="dashicons dashicons-info"></span>
                </div>
                
                <div class="FOloadingTable" style="display:none;">
                    <span style="animation: fospin 1s infinite;font-size:120px;width:120px;height:120px;" class="dashicons dashicons-update"></span>
                </div>
                <h6> <?php echo esc_attr($tavolo->post_title);?> </h6>
                <?php if ( $zone != false ) {
                foreach ($zone as $k => $zona) { ?>
                    <strong> <?php echo esc_attr($zona->name);?> </strong>
                <?php } } ?>
                <p class="fo_status_string"> <?php echo esc_attr($status_string);?> </p>

                <?php $date = new DateTime($tavolo->last_update); ?>

                <span class="fo_tab_abs_data" title="<?php esc_html_e( 'Ora e data dell\'ultima modifica', 'studio-immens-quick-order' );?>"><?php echo esc_attr(date_format($date, 'H:i:s d/m')); ?></span>
            </div>
        <?php } ?>
        <span class="siqo-plan-anchor" hidden></span>
        <?php SIQO_flash_tab_order( $tavoli );?>
    </div>

    <?php if ( ! $embedded && function_exists( 'FOP_tab_Catering_section' ) ) { FOP_tab_Catering_section(); } ?>

    <script type="text/javascript">
        // console.log(window);
        jQuery(window).on('load', function() {
            jQuery(".FOloadingCardPublicMain").fadeOut(200);
        });
    </script>

    <?php if ( ! empty( $tavoli ) ) : ?>
        <?php
        $siqo_plan_floor = SIQO_Plan_Layout::table_floor( $tavoli[0]->ID );
        $siqo_plan_data  = array(
            'floor'  => $siqo_plan_floor,
            'canvas' => SIQO_Plan_Layout::floor_layout( $siqo_plan_floor ),
            'floors' => SIQO_Plan_Layout::floors(),
            'zones'  => SIQO_Plan_Layout::zones(),
        );
        ?>
        <script type="application/json" id="siqo-plan-data"><?php echo wp_json_encode( $siqo_plan_data ); ?></script>
    <?php endif; ?>

</div>

<?php

}

// SIQO_manage_table();









