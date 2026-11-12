
jQuery(document).ready(function($) {
    "use strict";
    $.noConflict();

    if ( jQuery('#fopage').attr('fopage') == 'front-orders' ) {
        SIQO_notify_check();
        var ajax_refresh_order_seconds = jQuery('#ajax_refresh_order_seconds').text();
        setInterval(function () {
            var last_order = jQuery('#result-container tr:nth-child(1)');
            // var skip = true;
            var order_id_table = SIQO_get_order_id_table();
            // console.log(order_id_table);
            jQuery.ajax({
                type: 'POST',
                url: siqo_ajax_view_orders_vars.ajaxurl,
                data: {
                    action: 'SIQO_check_for_orders',
                    nonce: siqo_ajax_view_orders_vars.nonce,
                    last_order_id: jQuery('#result-container tr:nth-child(1)').attr('orderid'),
                    last_order_data: jQuery('#result-container tr:nth-child(1)').attr('orderdata'),
                    order_id_table: order_id_table,
                },
                success: function (response) {
                    console.log(response);
                    if ( response.updateNeeded ) {
                        if (response.newOrders.length) {
                            response.newOrders.forEach(function(order) {
                                if ( jQuery('#result-container tr:nth-child(1)').attr('orderid') != order.id ) {
                                    SIQO_append_to_order( order, response.string );
                                    SIQO_play_sound_notify();
                                    SIQO_inizialize_timer();
                                }
                            });
                        }
                    }
                    return;
                }
            });
            SIQO_hide_duplicate_orders();
        }, parseInt(ajax_refresh_order_seconds) );
    }// check page
});
function SIQO_get_order_id_table(){
    var order_id_table = [];
    jQuery('#result-container tr').each(function(i,e){ 
        if ( i != null ) { 
            order_id_table[i] = jQuery(e).attr('orderid'); 
        }
    });
    return order_id_table;
}

function SIQO_append_to_order( order, string ){
// console.log('SIQO_append_to_order');
    var target = jQuery('#result-container tbody');
    target.prepend(string);
    SIQO_notify_check();
    return;
}

function SIQO_hide_duplicate_orders(){
    var order_id_table = [];
    jQuery('#result-container tr').each(function(i,e){ 
        if ( i != null ) { 
            order_id_table[i] = jQuery(e).attr('orderid'); 
        }
    });
    var counter = [];
    order_id_table.forEach(ele => {
        if (counter[ele]) {
            counter[ele] += 1;
        }else{
            counter[ele] = 1;
        }
    });
console.log('SIQO_hide_duplicate_orders');
// console.log(counter);
    counter.forEach(function(i,e){ 
        if (i > 1) {
            // console.log(e);
            var t = 0;
            jQuery('#result-container tr[orderid="'+e+'"]').each(function(ind,ele){
                if (t>0) {
                    jQuery(ele).hide();
                }
                t += 1;
            }); 
        }
        // jQuery('#result-container tr').each(function(ind,ele){ 
        //     if ( i == jQuery(e).attr('orderid') && e > 0 ) {
        //         jQuery(ele).hide();
        //     }
        // });
    });
}








    function SIQO_play_sound_notify(){
        jQuery('#SIQO_audio')[0].play();
    }

    function SIQO_notify_show( notify ){
        if ( parseInt(notify.text()) > 0 ) {
            notify.show();
        } else{
            notify.hide();
        }
    }
    function SIQO_notify_add( notify, value = 1 ){
        if ( value >= 1 ) {
            notify.text( parseInt(notify.text()) + value );
            SIQO_notify_show(notify);
        }
    }
    function SIQO_notify_remove( notify, value = 1 ){
        notify.text( parseInt(notify.text()) - value );
        SIQO_notify_show(notify);
    }
    function SIQO_notify_check(){
        // console.log('Notify check');
        var notAll = jQuery('#Not_All'), notRestaurant = jQuery('#Not_Restaurant');
        var notPickup = jQuery('#Not_Pickup'), notDelivery = jQuery('#Not_Delivery');
        var notAllCount = 0, notRestaurantCount = 0, notPickupCount = 0, notDeliveryCount = 0;
        jQuery('#result-container tr').each(function(i,e){
            //console.log('Notify check');
            if( jQuery(e).attr('orderstatus') == 'pending' || jQuery(e).attr('orderstatus') == 'processing' ){
                notAllCount += 1;
            if( jQuery(e).attr('ordertype') == 'table' ){notRestaurantCount += 1;}
            if( jQuery(e).attr('ordertype') == 'pickup' ){notPickupCount += 1;}
            if( jQuery(e).attr('ordertype') == 'delivery' ){notDeliveryCount += 1;}
            }
        });
jQuery('#Not_All').text('0');
        SIQO_notify_add( notAll, notAllCount );
jQuery('#Not_Restaurant').text('0');
        SIQO_notify_add( notRestaurant, notRestaurantCount );
jQuery('#Not_Pickup').text('0');
        SIQO_notify_add( notPickup, notPickupCount );
jQuery('#Not_Delivery').text('0');
        SIQO_notify_add( notDelivery, notDeliveryCount );
        console.log( 'Notify check'+ (jQuery('#result-container tr').length -1) );
    }





function SIQO_order_filter( filter ){
    if ( filter == 'all') {
        jQuery('#result-container tbody tr').each(function(i,e){
            jQuery(e).fadeIn();
        });
    }
    if ( filter == 'table') {
        jQuery('#result-container tbody tr[ordertype=table]').each(function(i,e){
            jQuery(e).fadeIn();
        });
        jQuery('#result-container tbody tr:not([ordertype=table])').each(function(i,e){
            jQuery(e).fadeOut();
        });
    }
    if ( filter == 'pickup') {
        jQuery('#result-container tbody tr[ordertype=pickup]').each(function(i,e){
            jQuery(e).fadeIn();
        });
        jQuery('#result-container tbody tr:not([ordertype=pickup])').each(function(i,e){
            jQuery(e).fadeOut();
        });
    }
    if ( filter == 'delivery') {
        jQuery('#result-container tbody tr[ordertype=delivery]').each(function(i,e){
            jQuery(e).fadeIn();
        });
        jQuery('#result-container tbody tr:not([ordertype=delivery])').each(function(i,e){
            jQuery(e).fadeOut();
        });
    }
}


function SIQO_change_order_status( order_id, status = 'processing' , text = '' ){
  if (confirm(text) == true) {
        jQuery('[orderid="'+order_id+'"]').find('.FOloadingCardPublic').fadeIn();
        SIQO_Hide_all_popup('.FOstatusChanger');
// console.log(' ...ajax-start... ');
    var ord_id = order_id;
    var stat = status;
    jQuery.ajax({
        type: 'POST',
        url: siqo_ajax_view_orders_vars.ajaxurl,
        data: {
            action: 'SIQO_ajax_change_order_status',
            nonce: siqo_ajax_view_orders_vars.nonce,
            order_id: ord_id,
            status: stat
        },
        success: function(response) {
            console.log(' ...ajax-response... ');
            // jQuery('.FOloadingMessage').fadeOut();
            jQuery('[orderid="'+ord_id+'"]').find('.FOloadingCardPublic').fadeOut();
        // var button = '<button onclick="jQuery(this).next().slideToggle();" title="status" class="relative">'+status+'</button>';
            jQuery('#result-container tr[orderid='+ord_id+']').find('td[identify="status"] p')
                .text( jQuery('#result-container').attr(status) );
            jQuery('#result-container tr[orderid='+ord_id+']').find('[statustarget]')
                .text( jQuery('#result-container').attr(status) );
            // jQuery('#result-container tr[orderid='+ord_id+']').find('td[identify="status"]').attr('class','');
            // jQuery('#result-container tr[orderid='+ord_id+']').find('td[identify="status"]').addClass('status');
            // jQuery('#result-container tr[orderid='+ord_id+']').find('td[identify="status"]').addClass('SIQOstatus_'+status);
            jQuery('#result-container tr[orderid='+ord_id+']').attr('class','');
            jQuery('#result-container tr[orderid='+ord_id+']').addClass('relative');
            jQuery('#result-container tr[orderid='+ord_id+']').addClass('SIQOstatus_'+status);

            jQuery('#result-container tr[orderid='+ord_id+']').attr('orderdata', new Date());
            jQuery('#result-container tr[orderid='+ord_id+']').attr('orderstatus', status);
            // SIQO_Hide_all_popup('.FOstatusChanger');
        if ( status != 'processing' && status != 'pending' ) {
            jQuery('#result-container tr[orderid='+ord_id+']').find('div[identify="fotimer"]')
                //.remove();
                .addClass('SIQO_finish');
        //  SIQO_clearTimer(jQuery('#result-container tr[orderid='+ord_id+']'));
            // clearInterval(ord_id);
        } 
            clearInterval(ord_id);
            SIQO_inizialize_timer();
            SIQO_notify_check();
            jQuery.ajax({
                type: 'POST',
                url: siqo_ajax_view_orders_vars.ajaxurl,
                data: {
                    action: 'SIQO_get_order_info_button_tab_string',
                    nonce: siqo_ajax_view_orders_vars.nonce,
                    ajax_order_id: ord_id,
                },
                success: function(response) {
                    console.log(' ...ajax-info-request-success... ');
                    // console.log(jQuery(response));
                   //  var new_info = jQuery.parseJSON( response );
                   // jQuery(new_info).find('button').remove();
                    jQuery('#result-container tr[orderid='+ord_id+']').find('[identify="info"] .flash_card').remove();
                    jQuery('#result-container tr[orderid='+ord_id+']').find('[identify="info"]').append(response.string);
                }
            });
            return;
        }
    });
  }
}



















