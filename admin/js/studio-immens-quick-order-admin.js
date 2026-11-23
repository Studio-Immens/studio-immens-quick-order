(function( $ ) {
	//'use strict';
$.noConflict();

})( jQuery );

function SIQO_adm_restore_prev_prev( target, value ){
	target.previousElementSibling.value = value;
	jQuery(target).closest('.FOsettingStyle').css('backgroundColor',value);
}

function addSrcToAudio(value){
	var audio = document.getElementById('FOadminAudio');
	//console.log();
	audio.setAttribute('src', value);
	//audio.play();
}




jQuery(function($){
	jQuery('body').on('click', '.aw_upload_image_button', function(e){
        e.preventDefault();
        aw_uploader = wp.media({
            title: 'Custom image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        }).on('select', function() {
            var attachment = aw_uploader.state().get('selection').first().toJSON();
            jQuery('#tax-image').val(attachment.url);
            jQuery('#tax-img-src').attr('src',attachment.url);
            jQuery('#tax-img-src').css('display','block');
        })
        .open();
    });
});




function FOsettingSync(input){
	jQuery(input).parent().parent().css('background-color', jQuery(this).val());
}

function SIQO_settings_navigations( target ){
	
	jQuery('#settSection form').slideUp();

	jQuery(target).slideDown();
}

// console.log(window);
function SIQO_create_refresh_pages(){
	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			action: 'SIQO_create_all_necessary_pages',
			from: 'ajax',
			nonce: jQuery('#_siqononce_manage_pages').val(),
		},
		success: function(response) {
			console.log(' ...ajax-response... ');
			console.log(response);
			location.reload();
		}
	});
}

function SIQO_delete_page( page ){
	console.log(jQuery('#_siqononce_manage_pages').val());
	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			action: 'SIQO_delete_page',
			page: page,
			nonce: jQuery('#_siqononce_manage_pages').val(),
		},
		success: function(response) {
			console.log(' ...ajax-response... ');
			console.log(response);
			location.reload();
		}
	});
}

function SIQO_create_QR( target ){
	var size = '150';
	 size = jQuery(target).find('input[name="NEW_QR_size"]').val();
	var content = '';
	 content = jQuery(target).find('input[name="NEW_QR_content"]').val();
	 if (content == '') { alert(jQuery('#FO_fill_content_QR').text()); return; }
	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			action: 'SIQO_create_any_QR_code_ajax',
			size: size,
			content: content,
			nonce: jQuery('#_siqononce_main_page').val(),
		},
		success: function(response) {
			console.log(' ...ajax-response... ');
			console.log(response);
			location.reload();
		}
	});
}

function SIQO_delete_QR_images( target ){
	var del_path = jQuery(target).attr('del_path');
	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			action: 'SIQO_delete_QR_images',
			del_path: del_path,
			nonce: jQuery('#_siqononce_main_page').val(),
		},
		success: function(response) {
			console.log(' ...ajax-response... ');
			console.log(response);
			location.reload();
		}
	});
}

function SIQO_search_for_file(input, def = 'show'){
	var searchVal = jQuery(input).val();
	var target = jQuery(input).attr('target');
	var items = jQuery('.'+target);
	// console.log(items);
	items.each(function(index, row){
		var allDataPerRow = jQuery(row).attr('target');
		if (allDataPerRow.length > 0) {
			var found = false;
			
			var regExp = new RegExp(searchVal, "i");
			if (regExp.test(allDataPerRow)) {
				found = true;
			}

			if (def == 'hide' && searchVal == '') {
				jQuery(items).fadeOut();
			}
			if(found === true) {
				jQuery(row).css('scale','1');
				jQuery(row).show();
			}else {
				if (searchVal == '') {
					jQuery(row).css('scale','1');
					jQuery(row).show();
				} else {
					jQuery(row).css('scale','0');
					jQuery(row).fadeOut();
				}
			}
		}
	});
}

function SIQO_admin_show_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideDown();
}

function SIQO_admin_hide_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideUp();
}

function SIQO_admin_toggle_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideToggle();
}



function SIQO_condition_setting(input){
	var bool = jQuery(input).attr('siqo_bool') || jQuery(input).attr('fo_bool');
	if (jQuery(input).val() == bool) {
		jQuery( jQuery(input).attr('target_bool') ).slideDown();
	} else{
		jQuery( jQuery(input).attr('target_bool') ).slideUp();
	}
}



function SIQO_hide_condition_setting(input){
	var dep = jQuery(input).attr('siqo_dep') || jQuery(input).attr('fo_dep');
	var val = jQuery(dep).attr('siqo_val') || jQuery(dep).attr('fo_val');
	if ( val != 'yes' && val != 'si') {
		jQuery(input).hide();
	} else{
		jQuery(input).show();
	}
}

window.SIQO_adm_restore_prev_prev = SIQO_adm_restore_prev_prev;
window.SIQO_settings_navigations = SIQO_settings_navigations;
window.SIQO_create_refresh_pages = SIQO_create_refresh_pages;
window.SIQO_delete_page = SIQO_delete_page;
window.siqo_create_QR = SIQO_create_QR;
window.SIQO_delete_QR_images = SIQO_delete_QR_images;
window.siqo_search_for_file = SIQO_search_for_file;
window.SIQO_toggle_pages_rapid_impost = SIQO_admin_toggle_pages_rapid_impost;
window.SIQO_condition_setting = SIQO_condition_setting;










