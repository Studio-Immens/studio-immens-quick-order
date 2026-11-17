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
    echo '<input type="hidden" id="_siqononce_insert_post_ajax_nonce" name="_siqononce_insert_post_ajax_nonce" value="'.esc_attr($nonce).'" />';
    
    if ( function_exists( 'SIQO_get_settings_head_manage_tables' ) ) {
        SIQO_get_settings_head_manage_tables();
    } elseif ( function_exists( 'FO_get_settings_head_manage_tables' ) ) {
        FO_get_settings_head_manage_tables();
    }

    // $tavoli_info = FO_get_all_tables_by_status_last();
    // SIQO_debug($tavoli_info);
    if ( ! $embedded ) {
        if (isset($settings->showQR) && $settings->showQR == 'yes' ) {
            echo SIQO_create_post_QR_code();
        }

        if (isset($settings->showHead) && $settings->showHead == 'no' ) { ?>
            <script type="text/javascript">
                jQuery(window).on('load', function() {
                    jQuery('header').css('display','none');
                });
            </script>
        <?php }

        if (isset($settings->showFoot) && $settings->showFoot == 'no' ) { ?>
            <script type="text/javascript">
                jQuery(window).on('load', function() {
                    jQuery('footer').css('display','none');
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

    <?php if ( ! $embedded && ! empty( $tavoli ) ) : ?>
        <div class="siqo-plan-toolbar" data-plan-toolbar>
            <div class="siqo-plan-switch" role="group" aria-label="<?php esc_attr_e( 'Vista tavoli', 'studio-immens-quick-order' ); ?>">
                <button type="button" class="is-active" data-plan-view="grid"><?php esc_html_e( 'Griglia', 'studio-immens-quick-order' ); ?></button>
                <button type="button" data-plan-view="plan"><?php esc_html_e( 'Disposizione', 'studio-immens-quick-order' ); ?></button>
            </div>
            <button type="button" class="siqo-plan-edit" data-plan-edit><?php esc_html_e( 'Modifica disposizione', 'studio-immens-quick-order' ); ?></button>
            <span class="siqo-plan-status" data-plan-status aria-live="polite"></span>
        </div>
        <div class="FO_table_plan" data-plan-area aria-label="<?php esc_attr_e( 'Disposizione tavoli', 'studio-immens-quick-order' ); ?>"></div>
    <?php endif; ?>

    <div class="FO_table_grid">
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
            <div class="fo_table_cell relative" fotable="<?php echo esc_attr($tavolo->post_title);?>" fo_tableid="<?php echo esc_attr($tavolo->ID);?>" fotable_status="<?php echo esc_attr($status);?>" style="<?php echo esc_attr($Style);?>" onclick="SIQO_tab_Card_show( this )" table_start_time="" table_last_update="" table_orders="" table_info="" table_receipt="" table_other="" table_totals="" table_end_time="" table_table_tableid="" data-plan-id="<?php echo esc_attr($tavolo->ID);?>" data-plan-x="<?php echo esc_attr($plan_x);?>" data-plan-y="<?php echo esc_attr($plan_y);?>" data-plan-w="180" data-plan-h="120">

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
        <?php FO_flash_tab_order( $tavoli );?>
    </div>

    <?php if ( ! $embedded && function_exists( 'FOP_tab_Catering_section' ) ) { FOP_tab_Catering_section(); } ?>

    <script type="text/javascript">
        // console.log(window);
        jQuery(window).on('load', function() {
            jQuery(".FOloadingCardPublicMain").fadeOut(200);
        });
    </script>

    <?php if ( ! $embedded && ! empty( $tavoli ) ) : ?>
    <script type="text/javascript">
    (function () {
        var toolbar = document.querySelector('[data-plan-toolbar]');
        var plan = document.querySelector('[data-plan-area]');
        var grid = document.querySelector('#SIQO_Front_Content .FO_table_grid');
        if (!toolbar || !plan || !grid) {
            return;
        }
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.fo_table_cell'));
        var anchor = grid.querySelector('.siqo-plan-anchor');
        var status = toolbar.querySelector('[data-plan-status]');
        var editBtn = toolbar.querySelector('[data-plan-edit]');
        var viewBtns = Array.prototype.slice.call(toolbar.querySelectorAll('[data-plan-view]'));
        var nonceEl = document.getElementById('_siqononce_insert_post_ajax_nonce');
        var editing = false;
        var drag = null;
        var statusTimer = null;

        function setStatus(text, isError) {
            if (!status) {
                return;
            }
            window.clearTimeout(statusTimer);
            status.textContent = text || '';
            status.classList.toggle('is-error', !!isError);
            if (text) {
                statusTimer = window.setTimeout(function () {
                    status.textContent = '';
                    status.classList.remove('is-error');
                }, 3200);
            }
        }

        function resizePlan() {
            var max = 640;
            cards.forEach(function (card) {
                var y = parseInt(card.dataset.planY || '0', 10) + parseInt(card.dataset.planH || '120', 10) + 40;
                if (y > max) {
                    max = y;
                }
            });
            plan.style.minHeight = max + 'px';
        }

        function applyPositions() {
            cards.forEach(function (card) {
                card.style.left = (parseInt(card.dataset.planX || '0', 10)) + 'px';
                card.style.top = (parseInt(card.dataset.planY || '0', 10)) + 'px';
                card.style.width = (parseInt(card.dataset.planW || '180', 10)) + 'px';
                card.style.height = (parseInt(card.dataset.planH || '120', 10)) + 'px';
            });
        }

        function setView(view) {
            var isPlan = view === 'plan';
            plan.classList.toggle('is-active', isPlan);
            grid.classList.toggle('is-plan-hidden', isPlan);
            viewBtns.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.planView === view);
            });
            if (isPlan) {
                cards.forEach(function (card) {
                    plan.appendChild(card);
                });
                applyPositions();
                resizePlan();
            } else {
                editing = false;
                plan.classList.remove('is-editing');
                editBtn.classList.remove('is-active');
                editBtn.textContent = 'Modifica disposizione';
                cards.forEach(function (card) {
                    card.style.left = '';
                    card.style.top = '';
                    card.style.width = '';
                    card.style.height = '';
                    if (anchor) {
                        grid.insertBefore(card, anchor);
                    } else {
                        grid.appendChild(card);
                    }
                });
            }
        }

        function saveLayout() {
            if (!nonceEl) {
                return;
            }
            var layout = cards.map(function (card) {
                return {
                    id: parseInt(card.dataset.planId || '0', 10),
                    x: parseInt(card.dataset.planX || '0', 10),
                    y: parseInt(card.dataset.planY || '0', 10)
                };
            });
            var body = new URLSearchParams();
            body.set('action', 'siqo_save_table_layout');
            body.set('nonce', nonceEl.value);
            body.set('layout', JSON.stringify(layout));
            fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (result && result.success) {
                    setStatus('Disposizione salvata', false);
                } else {
                    setStatus('Salvataggio non riuscito', true);
                }
            }).catch(function () {
                setStatus('Salvataggio non riuscito', true);
            });
        }

        viewBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setView(btn.dataset.planView);
            });
        });

        editBtn.addEventListener('click', function () {
            if (!plan.classList.contains('is-active')) {
                setView('plan');
            }
            editing = !editing;
            plan.classList.toggle('is-editing', editing);
            editBtn.classList.toggle('is-active', editing);
            editBtn.textContent = editing ? 'Fine modifica' : 'Modifica disposizione';
            if (editing) {
                setStatus('Trascina i tavoli per sistemare la sala', false);
            }
        });

        plan.addEventListener('pointerdown', function (event) {
            if (!editing) {
                return;
            }
            var card = event.target.closest('.fo_table_cell');
            if (!card || !plan.contains(card)) {
                return;
            }
            var cardRect = card.getBoundingClientRect();
            var planRect = plan.getBoundingClientRect();
            drag = {
                card: card,
                pointerId: event.pointerId,
                offsetX: event.clientX - cardRect.left,
                offsetY: event.clientY - cardRect.top,
                startX: event.clientX,
                startY: event.clientY,
                moved: false,
                planLeft: planRect.left + plan.scrollLeft,
                planTop: planRect.top + plan.scrollTop
            };
            card.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        document.addEventListener('pointermove', function (event) {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }
            if (Math.abs(event.clientX - drag.startX) > 4 || Math.abs(event.clientY - drag.startY) > 4) {
                drag.moved = true;
            }
            var x = Math.max(0, Math.round((event.clientX - drag.planLeft - drag.offsetX) / 10) * 10);
            var y = Math.max(0, Math.round((event.clientY - drag.planTop - drag.offsetY) / 10) * 10);
            drag.card.style.left = x + 'px';
            drag.card.style.top = y + 'px';
            drag.card.dataset.planX = String(x);
            drag.card.dataset.planY = String(y);
            resizePlan();
        });

        document.addEventListener('pointerup', function (event) {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }
            var card = drag.card;
            var moved = drag.moved;
            if (card.hasPointerCapture(event.pointerId)) {
                card.releasePointerCapture(event.pointerId);
            }
            drag = null;
            if (moved) {
                card.addEventListener('click', function (clickEvent) {
                    clickEvent.preventDefault();
                    clickEvent.stopPropagation();
                }, { capture: true, once: true });
                saveLayout();
            }
        });

        document.addEventListener('pointercancel', function (event) {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }
            if (drag.card.hasPointerCapture(event.pointerId)) {
                drag.card.releasePointerCapture(event.pointerId);
            }
            drag = null;
        });
    })();
    </script>
    <?php endif; ?>

</div>

<?php

}

// SIQO_manage_table();









