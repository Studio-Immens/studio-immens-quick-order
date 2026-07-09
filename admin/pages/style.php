<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$style = SIQO_customized_css();
?>
<div id="settSection">
<form id="" method="post" class="SIQOForm">
    <button name="update" value="update" class="SIQObutton pointer" style="position:sticky;margin: 10px 10px 10px auto;top:45px;"> <?php esc_html_e( 'SALVA', 'studio-immens-quick-order' ); ?> </button>

    <?php wp_nonce_field( 'SIQO_save_settings', '_siqononce_save_settings' ); ?>
    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori principali', 'studio-immens-quick-order' ); ?> </b>
    </div>
    <div class="SIQOFormZone">
    <?php SIQO_new_style_setting('--siqo-main-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-scnd-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-high-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-invert-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-link-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-selection-color', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-error-color', array('type'=>'color')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori di Background principali', 'studio-immens-quick-order' ); ?> </b>
    </div>
    <div class="SIQOFormZone">
        <?php SIQO_new_style_setting('--siqo-bg-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-bg2-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-bg3-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-bg4-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-bg5-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-bg6-color', array('type'=>'color')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori dei testi e effetti', 'studio-immens-quick-order' ); ?> </b>
    </div>
    <div class="SIQOFormZone">
        <?php SIQO_new_style_setting('--siqo-text-color', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-text-color-inv', array('type'=>'color')); ?>
        <?php SIQO_new_style_setting('--siqo-text-shadow', array('type'=>'text')); ?>
        <?php SIQO_new_style_setting('--siqo-main-border', array('type'=>'text')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Tempi delle animazioni', 'studio-immens-quick-order' ); ?> </b>
    </div>
    <div class="SIQOFormZone">
        <?php SIQO_new_style_setting('--siqo-main-tran', array('type'=>'text')); ?>
        <?php SIQO_new_style_setting('--siqo-scnd-tran', array('type'=>'text')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori degli status degli ordini di woocommerce', 'studio-immens-quick-order' ); ?> </b>
    </div>
    <div class="SIQOFormZone">
    <?php SIQO_new_style_setting('--siqo-pending', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-processing', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-failed', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-cancelled', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-refunded', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-completed', array('type'=>'color')); ?>

    <?php SIQO_new_style_setting('--siqo-modified', array('type'=>'color')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori della gestione tavoli', 'studio-immens-quick-order' ); ?> </b>
    </div>

    <div class="SIQOFormZone">
    <?php SIQO_new_style_setting('--siqo-tab-1', array('type'=>'color') ); ?>
    <?php SIQO_new_style_setting('--siqo-tab-2', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-tab-3', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-tab-4', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-tab-5', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-tab-6', array('type'=>'color')); ?>
    </div>

    <div class="SIQOFormSeparator">
        <b> <?php esc_html_e( 'Colori degli status del timer', 'studio-immens-quick-order' ); ?> </b>
    </div>

    <div class="SIQOFormZone">
    <?php SIQO_new_style_setting('--siqo-timer-1', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-timer-2', array('type'=>'color')); ?>
    <?php SIQO_new_style_setting('--siqo-timer-3', array('type'=>'color')); ?>
    </div>
  <button name="update" value="update" class="SIQObutton pointer" style="margin: 10px auto;"> <?php esc_html_e( 'SALVA', 'studio-immens-quick-order' ); ?> </button>
</form>
</div>
<?php

SIQO_save_settings( 'css', 'style_css' );
