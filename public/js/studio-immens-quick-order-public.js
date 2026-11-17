function SIQO_show_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideDown();
}

function SIQO_hide_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideUp();
}

function SIQO_toggle_pages_rapid_impost( pageId ){
	jQuery('[fo_page_id="'+pageId+'"]').slideToggle();
}

jQuery(document).ready(function($) {
	   "use strict";


    //jQuery('.fo_column_products .fo_tab_prod').bind('touchstart', function(event) {
	//	console.log(event);
	//	jQuery(event).trigger('ondragstart');
	//	//element.ontouchstart
	//});


});


function SIQO_Hide_all_popup(targetClass){
	jQuery(targetClass).each(function(i,e){
		jQuery(e).hide();
	});
}

/*
	var coll = document.getElementsByClassName("collapsible");
	var i;

	for (i = 0; i < coll.length; i++) {
	  coll[i].addEventListener("click", function() {
	    this.classList.toggle("active");
	    var content = this.nextElementSibling;
	    if (content.style.display === "block") {
	      content.style.display = "none";
	    } else {
	      content.style.display = "block";
	    }
	  });
	}
*/

function SIQO_toggle_elements( target ) {
	console.log(target);
	jQuery(target).toggle();
}

function SIQO_Remove_Item_to_Order( target ) {
	jQuery(target).parent().parent().remove();
	jQuery('#FOProdNumber').text( parseInt(jQuery('#FOProdNumber').text()) - 1 );
	jQuery('#FOProdNumberMenu').text( parseInt(jQuery('#FOProdNumberMenu').text()) - 1 );
}

function SIQO_Add_Item_to_Order( target ) {
	var id, category, name, permalink, image, quantity, note, value;
	id = jQuery(target).parent().parent().attr('foid');
	category = jQuery(target).parent().parent().attr('focategories');
	name = jQuery(target).parent().parent().attr('foname');

	permalink = jQuery(target).parent().parent().find('.fopermalink a').attr('href');
	image = jQuery(target).parent().parent().find('.fopermalink a').html();

	quantity = jQuery(target).parent().parent().find('.foquantity').text();
	note = jQuery(target).parent().parent().find('.fonote textarea').val();
	value = jQuery(target).parent().find('input').val();


//console.log(image);
	var copy = jQuery(target).parent().parent().clone();
	copy.find('.FOAddBut').attr('onclick', 'SIQO_Remove_Item_to_Order(this);return false;');
	copy.find('.FOAddBut').text('RIMUOVI');

	// copy.find('.prod_input input').attr('name', jQuery('#FOProdNumber').text()+'~'+id+'~' );

	jQuery('#FOProdNumber').text( parseInt(jQuery('#FOProdNumber').text()) + 1 );
	jQuery('#FOProdNumberMenu').text( parseInt(jQuery('#FOProdNumberMenu').text()) + 1 );

	jQuery('#FOProdNumberMenu').parent().css( "background-color", 'var(--fo-invert-color)');
	jQuery('#FOProdNumberMenu').parent().css( "opacity", '1');

	setTimeout( function(){ 
		jQuery('#FOProdNumberMenu').parent().css( "background-color", 'var(--fo-bg3-color)'); 
		jQuery('#FOProdNumberMenu').parent().css( "opacity", '0.5');
	},800);

	copy.attr('foindex', jQuery('#FOProdNumber').text());

jQuery('#summ_order').append( copy );
	
	// jQuery(target).parent().parent().addClass('selected');

}


function SIQO_validate_order_form( target ){
	if ( jQuery(target).parent().find('#table_name').val() <= 0 ) {
		event.preventDefault();
		alert('seleziona il numero del tavolo');
		return false;
	} else{
		jQuery('#left-fixed-menu').animate({left:'-100%'});
		jQuery('.FOloadingMessage').show();
		SIQO_assign_index_to_inputs(this);
	}
}


function SIQO_assign_index_to_inputs(){
	// event.preventDefault();
	var table = jQuery('#summ_order').find('tr');

	table.each(function(I,E){
		var foindex = jQuery(E).attr('foindex');
		var stringToAdd = 'foindex['+foindex+']';
// console.log(jQuery(E).find('input, textarea'));
		jQuery(E).find('input, textarea').each(function(i,e){
			var name = jQuery(e).attr('name');
			// if ( name.indexOf('[') ) {
// 				var newName = stringToAdd+name.slice(0, name.indexOf('['))+']'+name.slice(name.indexOf('['));
			// } else{
// 				var newName = stringToAdd+name+']';
			// }
			var newName = stringToAdd+name;
			jQuery(e).attr('name', newName );
			console.log(newName);
		});
	});
}

// let FOstickyParent = document.querySelector('.sticky').parentElement;
// while (FOstickyParent) {
//   const hasOverflow = getComputedStyle(FOstickyParent).overflow;
//   if (hasOverflow !== 'visible') {
//     console.log(hasOverflow, parent);
//   }
//   let FOstickyParent = FOstickyParent.parentElement;
// }

function SIQO_Edit_Item_ingredients(trigger){
	// $(trigger).next().;
}

function FOSearchIngred(search) {
	var searchValue = jQuery(search).val();
	var FilterSec = jQuery(search).next();

	FilterSec.find('.SIQOIngredProdTab').each(function(index, element){
		var DataInput = jQuery(element).find('input');
		if (DataInput.length > 0) {
			var regExp = new RegExp(searchValue, "i");
	        if (regExp.test(jQuery(DataInput).val())) {
	            // jQuery(element).slideUp();
	            jQuery(element).slideDown();
	        } else {
	        	// jQuery(element).slideDown();
	        	jQuery(element).slideUp();
	        }
		}
		if ( searchValue == '') {jQuery(element).slideUp();}
	});

	// console.log(searchValue);
	// console.log('--------');
	// console.log(FilterSec);
}

function FOadvToRegularFit(input){
	var editIng = jQuery(input).closest('.foProdCard').find('input[value='+jQuery(input).val()+']');
	if ( !jQuery(input).is(':checked') ) {
		editIng.each(function(i,e){
			jQuery(e).addClass('SIQOstatus_modified');
			jQuery(e).attr('checked','');
		});
		// jQuery(input).closest('.foProdCard').find('#FOeditIngTab input[value='+jQuery(input).val()+']').addClass('SIQOstatus_modified');
	} else{
		editIng.each(function(i,e){
			jQuery(e).removeClass('SIQOstatus_modified');
			jQuery(e).attr('checked','checked');
		});
		// jQuery(input).closest('.foProdCard').find('#FOeditIngTab input[value='+jQuery(input).val()+']').removeClass('SIQOstatus_modified');
	}
}

function FOaddIng(input){
	jQuery(input).attr('onclick', 'FOremoveIng(this);FOcheckCardModified(this);');
		var copy = jQuery(input).parent().clone();
	copy.addClass('SIQOstatus_modified');
	// console.log( jQuery(input).parent().parent().parent() );
	jQuery(input).parent().parent().parent().prepend(copy);
	// if ( jQuery(input).parent().parent().parent().find('[modified=modified]').length ) {
	// 	jQuery(input).parent().parent().parent().parent().addClass('SIQOstatus_modified');
	// }
	FOcheckIfModified(input);
	jQuery(input).parent().remove();
}

function FOcheckIfModified(input){
	if ( !jQuery(input).is(':checked') ) {
		jQuery(input).parent().addClass('SIQOstatus_modified');
	} else{
		jQuery(input).parent().removeClass('SIQOstatus_modified');
	}
	FOcheckCardModified(input);
}
function FOcheckCardModified(input){
	// if ( jQuery(input).closest('.foProdCard').find('.SIQOstatus_modified').length ) {
	// 	jQuery(input).closest('.foProdCard').addClass('SIQOstatus_modified');
	// } else{
	// 	jQuery(input).closest('.foProdCard').removeClass('SIQOstatus_modified');
	// }
	jQuery('.foProdCard').each(function(i,e){
		if ( jQuery(e).find('.SIQOstatus_modified').length ) {
			jQuery(e).addClass('SIQOstatus_modified');
		} else{
			jQuery(e).removeClass('SIQOstatus_modified');
		}
	})
}
function FOremoveIng(input){
	// var parent = jQuery(input).parent();
	jQuery(input).parent().removeClass('SIQOstatus_modified');

	jQuery(input).attr('onclick', 'FOaddIng(this);FOcheckCardModified(this);');
		var copy = jQuery(input).parent().clone();
	copy.removeClass('SIQOstatus_modified');
	jQuery(input).parent().parent().find('.FOIngredProdSec').prepend(copy);

	if ( jQuery(input).parent().parent().find('input[modified=modified]:checked').length  <= 0 &&
	 jQuery(input).parent().parent().find('input[modified=native]:checked').length < jQuery(input).attr('nat-ing') ) {
		jQuery(input).parent().parent().parent().removeClass('SIQOstatus_modified');
	}
	FOcheckIfModified(input);
	jQuery(input).parent().remove();
}
function FOcheckIng(input){
	if ( !jQuery(input).is(':checked') ) {
		jQuery(input).parent().addClass('SIQOstatus_modified');
	} else{
		jQuery(input).parent().removeClass('SIQOstatus_modified');
	}
	if ( jQuery(input).parent().parent().find('input[modified=native]:checked').length < jQuery(input).attr('nat-ing') ) {
		jQuery(input).parent().parent().parent().parent().addClass('SIQOstatus_modified');
	} else {
		jQuery(input).parent().parent().parent().parent().removeClass('SIQOstatus_modified');
	}
}

function toggleFullScreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
  } else if (document.exitFullscreen) {
    document.exitFullscreen();
  }
}

function SIQO_grid_adjust( value ){
	console.log(value);
	document.documentElement.style.setProperty('--fo-grid-width', value+'%');
	jQuery('.GridRange').val(value);
}




function SIQO_animate_css( target, first, second, time = 200 ) {
	if ( second == null || second == '' ) { second = first}
var target = target;
var first = first;
var second = second;
var time = time;

    jQuery(target).animate({ first }, time, function(){
        jQuery(target).animate({ second }, time, function(){
            SIQO_animate_css( target, first, second, time );
        });
    });
}






function FOallowDrop(ev) {
  ev.preventDefault();
}

function FOdrag(ev) {
	//jQuery(input)
	var foprodid = ev.target.attributes.foprodid.nodeValue;
	//var foprodid = jQuery(input).attr('foprodid');

	//console.log(ev);
	// console.log(jQuery(ev.target).find('.FO_prod_name_manage').text());
	// console.log(foprodid);
	ev.dataTransfer.setData("foprodid", foprodid);
	ev.dataTransfer.setData("foprodname", jQuery(ev.target).find('.FO_prod_name_manage').text().trim());
}

function FOdrop(ev) {
	ev.preventDefault();
	var foprodid = ev.dataTransfer.getData("foprodid");
	var foprodname = ev.dataTransfer.getData("foprodname");
	var fo_cat_name = ev.target.attributes.fo_cat_name.nodeValue;
	var fo_cat_id = ev.target.attributes.fo_cat_id.nodeValue;
	var fo_cat_ceck = ev.target.attributes.fo_cat_ceck.nodeValue;

	console.log(foprodid);
	console.log(foprodname);

	console.log(fo_cat_name);
	console.log(fo_cat_id);
	console.log(fo_cat_ceck);

	var args = [];

	args['foprodid'] = foprodid;
	args['foprodname'] = foprodname;
	args['fo_cat_name'] = fo_cat_name;
	args['fo_cat_id'] = fo_cat_id;
	args['fo_cat_ceck'] = fo_cat_ceck;
	args['fo_cat_copy'] = jQuery('.fo_cat_copy').attr('fo_cat_copy');
	
	jQuery('.fo_tab_prod[foprodid="'+foprodid+'"]').attr('fomacrocat',fo_cat_name);
console.log(fo_cat_name);
	if (args['fo_cat_copy']) {

	} else{
		jQuery('.fo_tab_prod[foprodid="'+foprodid+'"]').hide();
	}

	SIQOP_change_category( args );
	// ev.target.appendChild(document.getElementById(data));

}

function touchstartDrag(event,input) {
	if (jQuery('input[name="siqop_touch_drag_drop"]').prop('checked')) {
	
	  var prod = jQuery(input);
	
	  let left = parseFloat(event.touches[0].clientX) - 50;
	  let top = parseFloat(event.touches[0].clientY) - 25;
	
	  prod.addClass("fo_touch_drag");
	  prod.css("left", left+"px");
	  prod.css("top", top+"px");
	  prod.attr("fo_posX", left+50);
	  prod.attr("fo_posY", top+25);
	}
}

function FOtouchdrop(event) {
	if (jQuery('input[name="siqop_touch_drag_drop"]').prop('checked')) {
		var elem = jQuery(event.target).closest('.fo_tab_prod');
			jQuery(elem).removeClass("fo_touch_drag");
			jQuery(elem).css("left", "");
			jQuery(elem).css("top", "");

		var targ = document.elementFromPoint(jQuery(elem).attr('fo_posX'),jQuery(elem).attr('fo_posY')) // x, y

		if (jQuery(targ).hasClass('fo_cat_butt')) {

			var foprodid = jQuery(elem).attr("foprodid");
			var foprodname = jQuery(elem).find('.FO_prod_name_manage').text().trim();
			var fo_cat_name = jQuery(targ).attr("fo_cat_name");
			var fo_cat_id = jQuery(targ).attr("fo_cat_id");
			var fo_cat_ceck = jQuery(targ).attr("fo_cat_ceck");
		
			var args = [];
		
			args['foprodid'] = foprodid;
			args['foprodname'] = foprodname;
			args['fo_cat_name'] = fo_cat_name;
			args['fo_cat_id'] = fo_cat_id;
			args['fo_cat_ceck'] = fo_cat_ceck;
			args['fo_cat_copy'] = jQuery('.fo_cat_copy').attr('fo_cat_copy');
			
			SIQOP_change_category( args );

			jQuery(elem).attr('fomacrocat',fo_cat_name);
			jQuery(elem).hide();

			console.log(elem);
		} else{
			
		}
	}
}









function SIQO_add_prod_to_favorites(input){
	if (jQuery('.SIQO_favourites_container').find('.foProdCard[foname="'+jQuery(input).attr('foname')+'"]').length > 0 ) {
		SIQO_alert('',jQuery('#SIQO_favourite_alert').text());
		return;
	}

	var p_clone = jQuery(input).clone();

	p_clone.attr('focategories','SIQO_favourite');

	p_clone.find('.foheart span').removeClass('dashicons-heart');
	p_clone.find('.foheart span').addClass('dashicons-no');
	
	p_clone.find('.foheart').attr('onclick',"SIQO_remove_prod_from_favorites(jQuery(this).closest('.foProdCard'))");

	jQuery('.SIQO_favourites_container').prepend(p_clone);


}

function SIQO_remove_prod_from_favorites(input){
	jQuery(input).remove();
}









function SIQO_refine_search(input, def = 'show'){
	var searchVal = jQuery(input).val();
	var target = jQuery(input).attr('fotargetcat');
	var items = jQuery('#products_container [focategories='+target+']');

		items.each(function(index, row){
		var allDataPerRow = jQuery(row).find('.foname');
		if (allDataPerRow.length > 0) {
			var found = false;
			allDataPerRow.each(function(ind, td) {
				var regExp = new RegExp(searchVal, "i");
				if (regExp.test(jQuery(td).text())) {
					found = true;
					return false;
				}
			});
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
function SIQO_search_for_all(input){
	var searchVal = jQuery(input).val();
	var sections = jQuery('#products_container .siqo-menu-section[data-category]');
	var items = sections.find('.foProdCard');

	sections.not('.siqo-favourites-section').show();

	items.each(function(index, row){
		var allDataPerRow = jQuery(row).find('.foname');
		if (allDataPerRow.length > 0) {
			var found = false;
			allDataPerRow.each(function(ind, td) {
				var regExp = new RegExp(searchVal, "i");
				if (regExp.test(jQuery(td).text())) {
					found = true;
					return false;
				}
			});
			if(found === true) {
				jQuery(row).show();
			}else {
				jQuery(row).hide();
			}
		}
	});

	var visibleTotal = 0;
	sections.each(function(){
		var section = jQuery(this);
		if (section.hasClass('siqo-favourites-section')) {
			return;
		}
		var hasMatch = section.find('.foProdCard').filter(function(){
			return this.style.display !== 'none';
		}).length > 0;
		section.toggle(hasMatch);
		if (hasMatch) {
			visibleTotal++;
		}
	});
	jQuery('#siqo-search-empty').prop('hidden', visibleTotal > 0);
	jQuery('.siqo-toolbar-note').toggle(searchVal === '');
}
function SIQO_search_for_target(input,target){
	var searchVal = jQuery(input).val();
	var items = jQuery(target);

	items.each(function(index, row){
		var allDataPerRow = jQuery(row).find('.foname');
		if (allDataPerRow.length > 0) {
			var found = false;
			allDataPerRow.each(function(ind, td) {
				var regExp = new RegExp(searchVal, "i");
				if (regExp.test(jQuery(td).text())) {
					found = true;
					return false;
				}
			});
			if(found === true) {
				jQuery(row).show();
				jQuery('.SIQO_order_list').show()
			}else {
				jQuery(row).hide();
			}
			if (searchVal == '') {jQuery(row).show();jQuery('.SIQO_order_list').show()}
		}
	});
}

function SIQO_inizialize_timer( check = '' ){
	console.log('--SIQO_inizialize_timer--');
	if ( check == 'table ') {
		jQuery('#result-container tr[identify="orders"] tr').each(function(i,e){
			if ( jQuery(e).attr('orderdata') != null ) {
				var status = jQuery(e).attr('orderstatus');
				if ( status != 'cancelled' && status != 'refunded' && status != 'completed' ) {
					SIQO_timer( jQuery(e) );
				} else{
					SIQO_clearTimer( jQuery(e) );
				}
			}
		});
	}else{
		jQuery('#result-container tr').each(function(i,e){
			if ( jQuery(e).attr('orderdata') != null ) {
				var status = jQuery(e).attr('orderstatus');
				if ( status != 'cancelled' && status != 'refunded' && status != 'completed' ) {
					SIQO_timer( jQuery(e) );
				} else{
					SIQO_clearTimer( jQuery(e) );
				}
			}
		});
	}
}


















  // date.getMonth();
  // date.getDate();
  // date.getFullYear();
//   date.getHours();
//   date.getMinutes();
//   date.getSeconds();
function SIQO_timer( target ){
	// SIQO_clearTimer( target );
	var status = jQuery(target).attr('orderstatus');
	var data = jQuery(target).attr('orderdata');
var adv_durat = parseInt(jQuery('#fotimer_duration').text());
	if ( jQuery('#fotimer_advanced').text() == 'yes' ) {
		// adv_durat = parseInt(jQuery('#fotimer_advanced_factor').text()) * parseInt(jQuery('#fotimer_duration').text()) * jQuery(target).find('[identify="products"]').length;
	}
	// console.log(jQuery(target).find('[identify="products"]').length);
	// console.log(adv_durat);

	var now = new Date();
	var date = new Date( data );

	var duration = parseInt( adv_durat ) * 60 * 1000;
	var difference = now.getTime() - date.getTime();
// difference = Math.floor(difference / 1000);
	var diff_min = duration - difference;
	diff_min = Math.floor(diff_min / 1000);
// console.log(diff_min);
	if ( status != 'cancelled' || status != 'refunded' || status != 'completed'  ) {
		SIQO_startTimer( diff_min, jQuery(target).find('.fotimer'), jQuery(target).attr('orderid') );
	} else{
		jQuery(target).find('.fotimer').slideUp();
		SIQO_clearTimer( jQuery(target) );
		clearInterval(jQuery(target).attr('orderid'));
	}
}

function SIQO_startTimer( duration, display, id ) {

	var status = jQuery(display).parent().parent().attr('orderstatus');
	if (status == 'cancelled' || status == 'refunded' || status == 'completed') {
		jQuery( display ).slideUp();
		SIQO_clearTimer( jQuery(display).parent().parent() );
		return;
	}
	// window.fointerval = [];
    	var start = Date.now();
    	var durat_fixed = parseInt( jQuery('#fotimer_duration').text() );
    	var percent = parseInt(durat_fixed*60) / 100;

	function timer( id ) {
		var diff = duration - ((Date.now() - start) / 1000);
		var minutes = (diff / 60) | 0;
		var seconds = (diff % 60) | 0;
		var fullseconds = diff | 0;
// console.log(fullseconds);	
		minutes_ = minutes < 10 ? "0" + minutes : minutes;
		seconds_ = seconds < 10 ? "0" + seconds : seconds;
		var time = minutes_ + ":" + seconds_;

jQuery( display ).removeClass('foyelcol');
jQuery( display ).removeClass('foerrcol');
jQuery( display ).removeClass('fo_status_timer_2');
jQuery( display ).removeClass('fo_status_timer_3');
// jQuery( display ).removeClass('foTimeOutAnim');
		jQuery( display ).text( time );
		jQuery( display ).css('width', parseInt(fullseconds) / percent +'%' );
		jQuery( display ).slideDown();

	if ( status == 'cancelled' || status == 'refunded' || status == 'completed' ) {
		jQuery(jQuery(display).parent().parent()).slideUp();
		SIQO_clearTimer(jQuery(display).parent().parent());
		clearInterval(id);
		return;
	} else{
		if (fullseconds <= 0  ) {
			clearInterval(id);
			SIQO_clearTimer(jQuery(display).parent().parent());
			return;
		} else{
				jQuery( display ).text( time );
				jQuery( display ).css('width', parseInt(fullseconds) / percent +'%' );
				jQuery(display).removeClass('foTimeOutAnim');
			if ( minutes < 10 &&  minutes > 5 ) {
				jQuery( display ).addClass('fo_status_timer_2');
				jQuery( display ).addClass('foyelcol');
			} else {
				if(minutes < 5) {
					jQuery( display ).removeClass('fo_status_timer_2');
					jQuery( display ).addClass('fo_status_timer_3');
					jQuery( display ).removeClass('foyelcol');
					jQuery( display ).addClass('foerrcol');
				}
			}
		}
	}
};
	// fointerval[String(id)] = setInterval(timer, 1000);
	setIntervalWrapper(timer, 1000, id);

	// window.fointerval.push( fointerval );
}

function SIQO_clearTimer( target ){
	console.log('SIQO_clearTimer');
	var id = jQuery(target).attr('orderid');
	var display = jQuery(target).find('.fotimer');
// var fointerval;
	// SIQO_startTimer( 0, display, id ).cancTimer(id);
	clearInterval( id );
	jQuery(display).text("00:00");
	jQuery(display).addClass('foTimeOutAnim');
	// return;
}

function setIntervalWrapper(callback, time) {
    var args = Array.prototype.slice.call(arguments, 1);
    args[0] = setInterval(function() {
        callback.apply(null, args);
    }, time);
}


// function FO_loop() {
//   setTimeout(() => {
//     // Your logic here
//     FO_loop();
//   }, delay);
// }

// console.log(window );








function SIQO_show_temp(target){
	var src = jQuery(target).find('[value="'+jQuery(target).val()+'"]').attr('img');
	var color = jQuery(target).find('[value="'+jQuery(target).val()+'"]').attr('color');

	jQuery(target).prev().val(jQuery(target).val());

	jQuery(target).parent().parent().find('.fotax_image_temp').attr('src',src);
	jQuery(target).parent().parent().find('.FOthermometer').attr('color',color);

	jQuery(target).parent().parent().find('.fotax_image_temp').css('display','block');
	jQuery(target).parent().parent().find('.fotemp_card').slideUp();
}



function Show_SIQO_Front_Float(){
	jQuery('#FO_Front_Float').css('bottom','0px');
	jQuery('#FO_Front_Float').css('opacity','1');
	document.body.classList.add('siqo-drawer-open');
	// jQuery('header').css('display','none');
	// jQuery('footer').css('display','none');
	SIQO_toggle_header_footer();
}

function Hide_SIQO_Front_Float(){
	jQuery('#FO_Front_Float').css('bottom','calc(-100% - 15px)');
	jQuery('#FO_Front_Float').css('opacity','0.5');
	document.body.classList.remove('siqo-drawer-open');
	// jQuery('header').css('display','');
	// jQuery('header').css('display','');
	SIQO_toggle_header_footer();
}

function Show_SIQOListRapidView(){
	jQuery('.SIQOListRapidView').css('left','0px');
	jQuery('.SIQOListRapidView').css('opacity','1');
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');
	// SIQO_toggle_header_footer();
}

function Hide_SIQOListRapidView(){
	jQuery('.SIQOListRapidView').css('left','-200px');
	jQuery('.SIQOListRapidView').css('opacity','0');
	// jQuery('header').css('display','');
	// jQuery('header').css('display','');
	SIQO_toggle_header_footer();
}

function SIQO_Advanced_Prod_Card( input ){
	jQuery('.Advanced_Card').fadeIn();
	jQuery('.Advanced_Card_background').fadeIn();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');

	var AC_ID = jQuery(input).closest('.foProdCard').attr('foid');
	var AC_Name = jQuery(input).closest('.foProdCard').attr('foname');
	var Advanced_Card_gallery = jQuery(input).closest('.foProdCard').attr('fo_gallery');

	var AC_description = jQuery(input).closest('.foProdCard').attr('fo_description');

	var AC_tax_cat = jQuery(input).closest('.foProdCard').attr('fo_tax_cat');
	var AC_tax_tag = jQuery(input).closest('.foProdCard').attr('fo_tax_tag');
	var AC_tax_ing = jQuery(input).closest('.foProdCard').attr('fo_tax_ing');
	var AC_tax_allerg = jQuery(input).closest('.foProdCard').attr('fo_tax_allerg');

	var AC_foindex = jQuery(input).closest('.foProdCard').attr('foindex');
	var AC_tot = jQuery(input).closest('.foProdCard').attr('fo_tot');
	var AC_Variant = jQuery(input).closest('.foProdCard').find('.fovariantcont').clone();
	AC_Variant.show();

	var foware = jQuery(input).closest('.foProdCard').attr('foware');

	var fowarehouse = jQuery(input).closest('.foProdCard').find('.fowarehouse img').attr('color');

	jQuery('.AC_ID').empty();
	jQuery('.AC_Name').empty();
	jQuery('.AC_description').empty();
	jQuery('.AC_Variant').empty();
	jQuery('.AC_tot').empty();
	jQuery('.AC_foindex').empty();

	
	jQuery('.AC_ID').text(AC_ID);
	jQuery('.AC_Name').text(AC_Name);
	jQuery('.AC_description').text(AC_description);

	Advanced_Card_gallery = JSON.parse(Advanced_Card_gallery);
	if ( Advanced_Card_gallery == '' || Advanced_Card_gallery == null ) {
		jQuery('.FO_no_images').show();
	} else{
		jQuery('.FO_no_images').hide();
		if (jQuery('[fo_prod_target_id="'+AC_ID+'"]').length ) {
			jQuery('[fo_prod_target_id="'+AC_ID+'"]').show();
		} else{
			jQuery('.Advanced_Card_gallery').append('<div class="fo_cont_gall_img" fo_prod_target_id="'+AC_ID+'"></div>');

			jQuery(Advanced_Card_gallery).each(function(i,e){
				var img = '<img class="fo_gallery_img" src="'+e+'">';
				jQuery('.Advanced_Card_gallery [fo_prod_target_id="'+AC_ID+'"]').append(img);
			});
		}
	}

if (AC_tax_cat == '[]') {jQuery('.AC_tax_cat').hide();}else{jQuery('.AC_tax_cat').show();}
	AC_tax_cat = JSON.parse(AC_tax_cat);
	jQuery(AC_tax_cat).each(function(i,e){
		jQuery('.AC_tax [fo_tax_id="'+e+'"]').show();
	});
if (AC_tax_tag == '[]') {jQuery('.AC_tax_tag').hide();}else{jQuery('.AC_tax_tag').show();}
	AC_tax_tag = JSON.parse(AC_tax_tag);
	jQuery(AC_tax_tag).each(function(i,e){
		jQuery('.AC_tax [fo_tax_id="'+e+'"]').show();
	});
if (AC_tax_ing == '[]') {jQuery('.AC_tax_ing').hide();}else{jQuery('.AC_tax_ing').show();}
	AC_tax_ing = JSON.parse(AC_tax_ing);
	jQuery(AC_tax_ing).each(function(i,e){
		jQuery('.AC_tax [fo_tax_id="'+e+'"]').show();
	});
if (AC_tax_allerg == '[]') {jQuery('.AC_tax_allerg').hide();}else{jQuery('.AC_tax_allerg').show();}
	AC_tax_allerg = JSON.parse(AC_tax_allerg);
	jQuery(AC_tax_allerg).each(function(i,e){
		jQuery('.AC_tax [fo_tax_id="'+e+'"]').show();
	});
	jQuery('.AC_foindex').val(AC_foindex);

	jQuery('.AC_tot').text(AC_tot);
	jQuery('.AC_Variant').append(AC_Variant);

	jQuery('.Advanced_Card .fowarehouse').attr('foware', foware); 
	jQuery('.Advanced_Card .fowarehouse img').attr('color', fowarehouse);
}

function SIQO_Advanced_Prod_Card_hide( target ){
	jQuery('.Advanced_Card').hide();
	jQuery('.Advanced_Card_background').fadeOut();
	// jQuery('header').css('display','');
	// jQuery('footer').css('display','');
	SIQO_toggle_header_footer();

	jQuery('.AC_ID').text('');
	jQuery('.AC_Name').text('');
	jQuery('.AC_description').text('');

	jQuery('.AC_foindex').val('');

	jQuery('.FOAdvIngredProdTab').hide();
	jQuery('.fo_cont_gall_img').hide();

	jQuery('.AC_tot').text('');
	jQuery('.AC_Variant').empty();
}

function SIQO_Advanced_Prod_Card_hide_all(){
	jQuery('.Advanced_Card').hide();
	jQuery('.Advanced_Card_background').hide();
}

function SIQO_toggle_Favourite( input ){
	jQuery('.SIQO_favourites_container').slideToggle();
	if (jQuery(input).find('span').hasClass('dashicons-arrow-down-alt2')) {
		jQuery(input).find('span').removeClass('dashicons-arrow-down-alt2');
		jQuery(input).find('span').addClass('dashicons-arrow-up-alt2');
	} else{
		jQuery(input).find('span').removeClass('dashicons-arrow-up-alt2');
		jQuery(input).find('span').addClass('dashicons-arrow-down-alt2');
	}
	
}


// if ( !jQuery('.fothermo').is(':focus') ) {
// jQuery(this).hide();
// }

document.addEventListener('click', function handleClickOutsideBox(event) {
//   const box = document.getElementById('FO_Front_Float');
// console.log(event.target);
//   if (!box.contains(event.target)) {
//     box.style.display = 'none';
//   }

	// jQuery('.fobox').each(function(i,e){
		// if ( !jQuery.contains(event.target, jQuery('#products_container') ) ) {
		// 	jQuery('.fobox').hide();
		// 	console.log('no');
		// } else{
		// 	console.log('si');
		// }
	// });

});





function SIQO_reorder_story(){
	jQuery('.fo_story').each(function(i,e){
		jQuery(e).insertBefore('.fo_tab_prod[fo_index_story="'+jQuery(e).attr('fo_order_id')+'"]:first');
	});
	
}






function SIQO_check_warehouse(item){
	if (jQuery(item).closest('.foProdCard').attr('foware')>0 || jQuery(item).closest('.Advanced_Card').find('.fowarehouse').attr('foware')>0) {
		SIQO_ajax_Add_Item_to_Order(item);
		jQuery(item).closest('.foProdCard').find('.fovariation_card').slideUp();
	}else{
		SIQO_alert( '', jQuery('#SIQO_warehouse_alert').text() );
		// alert(jQuery('#SIQO_warehouse_alert').text());
		// return false;
	}
}

function SIQO_ajax_selectVarBefore_Add_Item_to_Order(item){
	//if (jQuery(item).closest('.foProdCard').attr('fovariations') != '') {
		//jQuery(item).closest('.foProdCard').find('.fovariation_card').slideDown();
	//} else{
		SIQO_check_warehouse(item);
	//}
}

function SIQO_ajax_selectVarAfter_Add_Item_to_Order(item){
	var check = true;
	// jQuery(item).closest('.foProdCard').find('.fovariant select').each(function(i,e){
	// 	if ( jQuery(e).val() == '') { check = false; }
	// });
	if ( check ) {
		SIQO_check_warehouse(item);
	} else{
	// SIQO_alert( '', jQuery('#SIQO_select_variant_alert').text() );
		// alert(jQuery('#SIQO_select_variant_alert').text());
		// return false;
	}
}
function SIQO_ajax_Remove_Item_to_Order( target ) {
	jQuery(target).closest('.foProdCard').remove();
	jQuery('#FOProdNumber').text( parseInt(jQuery('#FOProdNumber').text()) - 1 );
	jQuery('#FO_order_live_count').text( parseInt(jQuery('#FO_order_live_count').text()) - 1 );
	jQuery('#FOProdNumberMenu').text( parseInt(jQuery('#FOProdNumberMenu').text()) - 1 );
}
function SIQO_ajax_Add_Item_to_Order(item){
	var id, image, prod, copy;
	if (jQuery('.AC_ID').text()!='') {
		prod = jQuery('.foProdCard[foid="'+jQuery('.AC_ID').text()+'"][fo_menu_prod="fo_menu_prod"]');
	} else{
		prod = jQuery(item).closest('.foProdCard[fo_menu_prod="fo_menu_prod"]');
	}
	var current_index = jQuery('#FO_order_index').val();
	var stringToAdd = '['+current_index+']';

	id = prod.attr('foid');
	image = prod.find('img').attr('src');

	copy = prod.clone();

	copy.find('.foadd').attr('onclick', 'SIQO_ajax_Remove_Item_to_Order(this);');
	copy.find('.foadd').text('-');
	copy.find('.fovariation_card .foadd').remove();

	copy.find('.foheart').remove();

	copy.attr('fo_menu_prod','fo_summary_prod');

	copy.find('.fovariation_card').slideUp();

//assign unique index
	copy.attr('foindex', current_index);
	copy.find('input, select, textarea').each(function(i,e){
		if ( /\d/.test(jQuery(e).attr('name').substr(1, 2)) ) {
			jQuery(e).attr('name', '['+parseInt(current_index + 1)+']'+jQuery(e).attr('name'));
		} else{
			jQuery(e).attr('name', stringToAdd+jQuery(e).attr('name'));
		}
	});

	copy.find('select').each(function(i,e){
		jQuery(e).find('option[value="'+jQuery(e).attr('value')+'"]').attr('selected','selected');
	});

	jQuery('#FOProdNumber').text( parseInt(jQuery('#FOProdNumber').text()) + 1 );
	jQuery('#FO_order_live_count').text( parseInt(jQuery('#FO_order_live_count').text()) + 1 );

	jQuery('.FO_show_order_summary').addClass('foBorderPulse');
	jQuery('.FO_show_order_summary img').attr('src', image);
	jQuery('.FO_show_order_summary img').show();

	jQuery('#FO_sum_order').append( copy );

	jQuery('#FO_order_index').val( parseInt(current_index) + 1 );
}

function SIQO_variant_set(input){
	var prod = jQuery('.foProdCard[foid="'+jQuery('.AC_ID').text()+'"][foindex="'+jQuery('.AC_foindex').val()+'"]');

	prod.find('select[name="'+jQuery(input).attr('name')+'"]').attr('value', jQuery(input).find('option[value="'+jQuery(input).val()+'"]').val() );

	prod.find('select[name="'+jQuery(input).attr('name')+'"] option').removeAttr('selected');
	prod.find('select[name="'+jQuery(input).attr('name')+'"] option[value="'+jQuery(input).val()+'"]').attr('selected','selected');

	jQuery(input).attr('value', jQuery(input).find('option[value="'+jQuery(input).val()+'"]').val());

	var fo_price_to_add = parseFloat(jQuery(input).find('option[value="'+jQuery(input).val()+'"]').attr('fo_price_to_add'));
	var fo_price = parseFloat(jQuery(input).attr('fo_price'));

	var final_price = parseFloat( fo_price + fo_price_to_add ).toFixed(2);
	//jQuery(input).attr('fo_price', );

jQuery('.AC_tot').text( final_price + ' ' + jQuery('#FO_woo_currency_sym').text() );
	prod.find('input[fo_tab_target="price"]').val(final_price).attr('regularPrice', fo_price).attr('price_added', fo_price_to_add);
	//SIQO_show_variant_tag(input);
}

function SIQO_show_variant_tag(input){
	var attr = ( jQuery(input).attr('founivariant')!= null )? jQuery(input).attr('founivariant') : jQuery(input).attr('foadvariant');
	jQuery(input).closest('.foProdCard').find(`.fovariantag_container [founivariant='`+attr+`']`)
		.each(function(i,e){
			// console.log( jQuery(e).attr('founitag') );
		if ( jQuery(input).val() != '' && jQuery(e).attr('founitag') == jQuery(input).val() ) {
			jQuery(e).show();
		} else{
			jQuery(e).hide();
		}
	});
}




function SIQO_validate_order_form_ajax( target ){
	if(jQuery('#SIQOSetting_UserLoggedIn').text() == 0) {
        event.preventDefault();
        SIQO_alert( '', jQuery('#SIQO_access_alert').text() );
        // alert('Per poter effettuare ordini su questa pagina, Ã© neccessario Effettuare l\'accesso!');
		Hide_SIQO_Front_Float();
		jQuery('.FO_logIn_popUp').fadeIn();
		return false;
    } else{
		// if ( jQuery(target).parent().find('#table_name').val() <= 0 ) {
		// 	event.preventDefault();
		// 	alert('seleziona il numero del tavolo');
		// 	return false;
		// } else {
			jQuery('.FOloadingMessage').fadeIn();
			// SIQO_assign_index_to_ajax_inputs(target);
			SIQO_submit_order_ajax_form(target);
		// }
	}
}
function SIQO_assign_index_to_input(input){
	// event.preventDefault();
	var table = jQuery('#FO_sum_order').find('.foProdCard');

	table.each(function(I,E){
		var foindex = jQuery(E).attr('foindex');
		var stringToAdd = '['+foindex+']';
// console.log(jQuery(E).find('input, textarea'));
		jQuery(E).find('input, textarea').each(function(i,e){
			var name = jQuery(e).attr('name');
			var newName = stringToAdd+name;
			jQuery(e).attr('name', newName );
			console.log(newName);
		});
	});
}

function SIQO_assign_index_to_ajax_inputs(){
	// event.preventDefault();
	var table = jQuery('#FO_sum_order').find('.foProdCard');

	table.each(function(I,E){
		var foindex = jQuery(E).attr('foindex');
		var stringToAdd = '['+foindex+']';
// console.log(jQuery(E).find('input, textarea'));
		jQuery(E).find('input, textarea').each(function(i,e){
			var name = jQuery(e).attr('name');
			var newName = stringToAdd+name;
			jQuery(e).attr('name', newName );
			console.log(newName);
		});
	});
}

function SIQO_submit_order_ajax_form(target){
	var table = jQuery('#FO_sum_order').find('.foProdCard');
	var foserial = jQuery(table).find('input, textarea, select').serializeArray();
	// foserial.filter(function(e){return e});
	var foserialmap = foserial.map(({ name, value }) => ({ [name]: value }));

foserialmap = foserial.map(item => {
    var container = {};
    container[item.name] = item.value;
    return container;
});

console.log(foserialmap);
	console.log(' order submitted sucessfully !!! ');
		jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
		    action: 'SIQO_submit_order_ajax',
		    nonce: siqo_ajax_view_orders_vars.nonce,
		    table_id: jQuery('[name="table_name_cpt"]').val(),
		    table_name_cpt: jQuery('[name="table_name_cpt"]').text(),
		    order_note: jQuery('#orderNote').val(),
		    foserial: foserial,
		    foserialmap: foserialmap,
		    _siqononce_front_order_ajax: jQuery('input[name="_siqononce_front_order_ajax"]').val()
		    // obj: obj,
		},
		success: function (response) {
			console.log(response);
				jQuery('#FO_sum_order').empty();

				jQuery('#FOProdNumber').text( 0 );
				jQuery('#FO_order_live_count').text( 0 );
				jQuery('.FO_show_order_summary').removeClass('foBorderPulse');
				jQuery('.FO_show_order_summary img').attr('src', '');
				jQuery('.FO_show_order_summary img').hide();
				jQuery('#orderNote').empty();
				Hide_SIQO_Front_Float();
				jQuery('.FOloadingMessage').fadeOut();
			return;
		}
	});
}


function SIQO_order_list_ajax(item){
	if ( jQuery(item).closest('.SIQO_flash_list_order_container').find('#table_name').val() <= 0 && 
			jQuery(item).closest('.SIQO_flash_list_order_container').find('[name="table_name_cpt"]').val() <= '' ) {
		event.preventDefault();
		alert(jQuery('#folist_order_table_mex').text());
		return false;
	} else {
		jQuery(item).closest('.SIQO_flash_list_order_container').find('.FOloadingCardPublic').fadeIn();
		jQuery(item).closest('.SIQO_flash_list_order_container').find('.FO_list_height').val('');
		jQuery(item).closest('.SIQO_flash_list_order_container').find('.FO_target_search').hide();
		var table = jQuery(item).closest('.SIQO_flash_list_order_container').find('.SIQO_order_list_summary');

		var foserial = jQuery(table).find('input, textarea').serializeArray();
		var foserialmap = foserial.map(({ name, value }) => ({ [name]: value }));
	foserialmap = foserial.map(item => {
	    var container = {};
	    container[item.name] = item.value;
	    return container;
	});
	console.log(foserialmap);
		// console.log(' order submitted sucessfully !!! ');
			jQuery.ajax({
			type: 'POST',
			url: siqo_ajax_view_orders_vars.ajaxurl,
			data: {
			    action: 'SIQO_flash_list_order_ajax',
			    nonce: siqo_ajax_view_orders_vars.nonce,
			    table_name: jQuery('input[name="table_name"]').val(),
			    table_surname: jQuery('select[name="table_surname"]').val(),
			    table_name_cpt: jQuery('select[name="table_name_cpt"]').val(),
			    order_note: jQuery('textarea[name="order_note"]').val(),
			    foserial: foserial,
			    foserialmap: foserialmap,
			    _siqononce_flash_list_order: jQuery('input[name="_siqononce_flash_list_order"]').val(),
			},
			success: function (response) {
				console.log(response);
					jQuery('.SIQO_order_list_summary').empty();

					jQuery('input[name="discount"]').val( 0 );
					jQuery('input[name="table_name"]').val( 0 );
					jQuery('select[name="table_name_cpt"]').val('');
					jQuery('select[name="table_surname"]').val('');

					jQuery('.FO_summ_tot span').text('0');
					jQuery('.FONum_products').text('0');

					jQuery('.SIQO_flash_list_order_container').find('.FOloadingCardPublic').fadeOut();
				return;
			}
		});
	}
}


function SIQO_order_list(item){
	var price = parseFloat(jQuery(item).attr('foprice'));
	var tot = parseFloat(jQuery('.FO_summ_tot span').text());
	var NumProd = parseInt(jQuery('.FONum_products').text());

	jQuery('.FO_summ_tot span').text( parseFloat(tot + price).toFixed(2) );

 if (jQuery('.FO_summ_tot span').text()=='NaN') { jQuery('.FO_summ_tot span').text('0') }

	var copy = jQuery(item).clone();
copy.removeClass('FO_target_search');

	copy.find('.FO_list_price').show();
	copy.find('.FO_clear_target').show();
	copy.find('.FO_clear_target').attr('onclick','SIQO_order_list_remove(jQuery(this).parent());jQuery(this).parent().remove();');
	copy.find('input').attr('name', '['+NumProd+']'+copy.find('input').attr('name') );
	copy.attr('onclick','');
	jQuery(item).closest('.SIQO_flash_list_order_container').find('.SIQO_order_list_summary').append(copy);

	jQuery('.FONum_products').text( parseInt(parseInt(NumProd) + 1) );
}

function SIQO_add_new_customer( item ){
	var fo_cu_name = jQuery(item).find('.N_customer_name').val();
	var fo_cu_mail = jQuery(item).find('.N_customer_mail').val();
	var fo_cu_phone = jQuery(item).find('.N_customer_phone').val();

	if (fo_cu_name == '') {
		SIQO_alert( '', jQuery('#fo_tab_new_customer_error_name_text').text());return;}
	if (fo_cu_mail == '' && fo_cu_phone == '' ) {
		SIQO_alert( '', jQuery('#fo_tab_new_customer_error_mail_phone_text').text());return;}
	
	var input = jQuery(item).find('input').clone();
	input.removeClass('fot_pay_input');
	input.attr('type','hidden');

	var copy = jQuery('.FO_exemple_customer_card').clone();
	copy.removeClass('FO_exemple_customer_card');
	copy.removeClass('FO_example');
	copy.css('display','flex');
	copy.css('backgroundColor','var(--fo-high-color)');

	copy.find('.FO_pay_customer_name').text(fo_cu_name);
	copy.find('.FO_pay_customer_mail').text(fo_cu_mail);
	copy.find('.FO_pay_customer_phone').text(fo_cu_phone);

	copy.append(input);
	
	console.log(copy);
	jQuery('.FO_customers_container').empty();
	jQuery('.FO_customers_container').append(copy);
	SIQO_refresh_pay_discounts();
	siqo_pay_quote_coupons();
	console.log(jQuery('.FO_customers_container input').serializeArray());
}

function SIQO_pay_customer_list(item){
	console.log(item);
	var fo_u_id = jQuery(item).attr('fo_u_id');
	var fo_u_name = jQuery(item).attr('fo_u_name');
	var fo_u_mail = jQuery(item).attr('fo_u_mail');
	var fo_u_phone = jQuery(item).attr('fo_u_phone');
	var fo_u_since = jQuery(item).attr('fo_u_since');
	var fo_u_aurl = jQuery(item).attr('fo_u_aurl');

	var input = jQuery(item).find('input').clone();

	var copy = jQuery('.FO_exemple_customer_card').clone();
copy.removeClass('FO_exemple_customer_card');
copy.removeClass('FO_example');
copy.css('display','flex');

	copy.find('.FO_pay_customer_id').text(fo_u_id);
	copy.find('.FO_pay_customer_name').text(fo_u_name);
	copy.find('.FO_pay_customer_mail').text(fo_u_mail);
	copy.find('.FO_pay_customer_phone').text(fo_u_phone);

	copy.prepend(fo_u_aurl);
	copy.append(input);
	
	console.log(copy);
	jQuery('.FO_customers_container').empty();
	jQuery('.FO_customers_container').append(copy);
	SIQO_refresh_pay_discounts();
	siqo_pay_quote_coupons();
	console.log(jQuery('.FO_customers_container input').serializeArray());
}

function SIQO_order_list_remove(item){
	var price = parseFloat(jQuery(item).attr('foprice'));
	var tot = parseFloat(jQuery('.FO_summ_tot span').text());
	var NumProd = jQuery('.FONum_products').text();

	jQuery('.FO_summ_tot span').text( parseFloat(tot - price).toFixed(2) );
	jQuery('.FONum_products').text( parseInt(NumProd - 1) );
 // if (jQuery('.FO_summ_tot span').text()=='NaN') { jQuery('.FO_summ_tot span').text('0') }
 	
}

function SIQO_order_discount_list(item, qtyTarget){
	var price = parseFloat(jQuery(qtyTarget).val());
	var tot = parseFloat(jQuery('.FO_summ_tot span').text());
var NumProd = parseInt(jQuery('.FONum_products').text());

	var copy = jQuery(item).parent().find('.FO_list_prod_summ').clone();
	copy.css('display','');
	copy.attr('foprice', price);
	copy.find('.FO_list_price').text( parseFloat(price).toFixed(2) );
	copy.find('input').attr('name', '['+NumProd+']'+copy.find('input').attr('name') );
	copy.find('input').val( price );

	jQuery(item).closest('.SIQO_flash_list_order_container').find('.SIQO_order_list_summary').append(copy);

	jQuery('.FO_summ_tot span').text( parseFloat(tot + price).toFixed(2) );
	jQuery('.FONum_products').text( parseInt(parseInt(NumProd) + 1) );
 	
}















function SIQO_add_value_to_input( value, target ){
	jQuery(target).val(parseFloat(parseFloat(jQuery(target).val()) + parseFloat(value)).toFixed(2));
}



function SIQO_filter_tab_product( slug_target ){
	jQuery('.fo_column_products .fo_tab_prod').hide();
	jQuery('.fo_column_products .fo_tab_prod[fomacrocat="'+slug_target+'"]').show();
}

function siqo_filter_tab_story( input, slug_target ){
	jQuery('.fo_tab_prod').removeClass('fo_tab_prod_story');
	jQuery('.fo_order_table_story').removeClass('fo_tab_prod_story');
	jQuery('.fo_column_story .fo_tab_prod').removeClass('fo_tab_prod_story');
	jQuery('.fo_column_story .fo_tab_prod').hide();
	jQuery(input).addClass('fo_tab_prod_story');
	if (slug_target == 'all') {
		jQuery('.fo_column_story .fo_tab_prod[fotableid="'+jQuery(input).attr('fotableid')+'"]').show();
		jQuery('.fo_column_story .fo_tab_prod[fotableid="'+jQuery(input).attr('fotableid')+'"]').addClass('fo_tab_prod_story');
	}else{
		jQuery('.fo_column_story .fo_tab_prod[fo_index_story="'+slug_target+'"]').show();
		jQuery('.fo_column_story .fo_tab_prod[fotableid="'+jQuery(input).attr('fotableid')+'"][fo_index_story="'+jQuery(input).attr('fo_order_id')+'"]').addClass('fo_tab_prod_story');
	}
	SIQO_calc_totals();
}
function SIQO_filter_tab_variant( input, slug_target ){
	jQuery('.fo_tab_prod').removeClass('fo_tab_prod_selected');
	jQuery('.fo_tab_prod').removeClass('fo_tab_prod_modify');

	if (jQuery(input).attr('fo_type') == 'new') {
		jQuery(input).addClass('fo_tab_prod_selected');
	}else if (jQuery(input).attr('fo_type') == 'modify') {
		jQuery(input).addClass('fo_tab_prod_modify');
	}else if (jQuery(input).attr('fo_type') == 'story') {
		jQuery(input).addClass('fo_tab_prod_story');
	}else {
		// jQuery(input).addClass('fo_tab_prod_selected');
	}

//fix the params -----------------------------------
	jQuery('.fo_actual_prod').text(jQuery(input).attr('foprodid'));
	jQuery('.fo_actual_index').text(jQuery(input).attr('fo_index'));
	jQuery('.fo_actual_index_story').text(jQuery(input).attr('fo_index_story'));

//variant -------------------------------------------
	jQuery(input).find('[fo_tab_target="variante"]').each(function(i,e){
		if (jQuery(e).val() == '') {
			jQuery('.fo_tab_variantcont[foprodidtarget="'+slug_target+'"]').find('input[fovariant="'+jQuery(e).attr('fovariant')+'"]').prop('checked', false);
		}else{
			jQuery('.fo_tab_variantcont[foprodidtarget="'+slug_target+'"]').find('input[fovariant="'+jQuery(e).attr('fovariant')+'"][value="'+jQuery(e).val()+'"]').prop('checked', true);
		}
	});
	jQuery('.fo_tab_variantcont[foprodidtarget="'+slug_target+'"]').attr('fo_modificable',jQuery(input).attr('fo_modificable') );
	jQuery('.fo_tab_variantcont').hide();
	jQuery('.fo_tab_variantcont[foprodidtarget="'+slug_target+'"]').show();

//temperature --------------------------------------
	jQuery('.FO_flash_tab_temperature[foprodidtarget="'+slug_target+'"]').find('input').prop('checked', false);
	jQuery('.FO_flash_tab_temperature[foprodidtarget="'+slug_target+'"]').find('input[value="'+jQuery(input).find('[fo_tab_target="Temperature"]').val()+'"]').prop('checked', true);
	jQuery('.FO_flash_tab_temperature[foprodidtarget="'+slug_target+'"]').attr('fo_modificable',jQuery(input).attr('fo_modificable') );
	jQuery('.FO_flash_tab_temperature').hide();
	jQuery('.FO_flash_tab_temperature[foprodidtarget="'+slug_target+'"]').show();

//name ---------------------------------------------
	var name = jQuery(input).find('.FO_prod_name_manage').text();
	var order_text = 'ORD nÂ°'+jQuery(input).attr('fo_index_story')+'  |  ';
	if ( parseInt(jQuery(input).attr('fo_index_story')) >= 999999 ) { order_text = '' }
	jQuery('.fo_col_2_name').text( order_text + name );

//price --------------------------------------------
	var price = jQuery(input).find('[fo_tab_target="price"]').val();
		if (price==''||price==null||!price) {price = 0.00}
	jQuery('.fo_tab_price').attr('fo_modificable',jQuery(input).attr('fo_modificable') );
	jQuery('.fo_tab_price').text( parseFloat(price).toFixed(2));
	jQuery('.fo_tab_price').attr('fo_status_modify','false');

	// jQuery(input).attr('foprodtot', slug_target );
	jQuery('.fo_tab_price').attr('fo_actual_prod', slug_target );
	jQuery('.fo_tab_price').attr('fo_actual_index', jQuery(input).attr('fo_index') );

	jQuery('.fo_tab_reset_price').attr('fovalue', parseFloat(jQuery(input).attr('foprodtot')).toFixed(2) );

//qty --------------------------------------------------
	if ( jQuery(input).attr('fo_special') == 'Special' ||  jQuery(input).attr('fo_special') == 'Sconto' ) {
			// prod.find('input[fo_tab_target="id"]').attr('name', prod.find('input[fo_tab_target="id"]').attr('name')+'['+jQuery(input).text()+']');
		jQuery('.FO_flash_tab_qty').hide();
	} else{
		jQuery('.FO_flash_tab_qty').show();
	}
	jQuery('.fo_target_qty_prod').val(jQuery(input).find('[fo_tab_target="qty"]').val());
	jQuery('.fo_target_qty_prod').attr('fo_modificable',jQuery(input).attr('fo_modificable'));

//note -------------------------------------------------
	jQuery('.fo_target_note_prod').empty();
	jQuery('.fo_target_note_prod').val(jQuery(input).find('[fo_tab_target="note"]').val());
	jQuery('.fo_target_note_prod').attr('fo_modificable',jQuery(input).attr('fo_modificable'));
	jQuery('.FO_flash_tab_note').show();
	
	if ( jQuery(input).attr('fo_type') == 'new' ) {
		fo_tab_add_product_to_order();
	}
	SIQO_calc_totals();
	if ( jQuery(input).attr('fo_modificable') == 'false' ) {
		jQuery('input[type="radio"]').attr('onclick','return false;');
		jQuery('.fo_target_note_prod, .fo_target_qty_prod').attr('disabled',true);
		jQuery('.fo_tab_col_2, .fo_tab_col_3').removeClass('FO_new_background');
		jQuery('.fo_tab_col_2, .fo_tab_col_3').addClass('FO_story_background');
	} else{
		jQuery('input[type="radio"]').attr('onclick','');
		jQuery('.fo_target_note_prod, .fo_target_qty_prod').attr('disabled',false);
		jQuery('.fo_tab_col_2, .fo_tab_col_3').removeClass('FO_story_background');
		jQuery('.fo_tab_col_2, .fo_tab_col_3').addClass('FO_new_background');
	}
}

function SIQO_calc_totals(){
	var tot_r = 0.00;
	jQuery('.fo_column_riepilogo .fo_tab_prod').each(function(i,e){
		var temp_r_price = 0.00;
		var qty_r = parseFloat(jQuery(e).find('[fo_tab_target="qty"]').val());
			if (qty_r==''||qty_r==null) {qty_r = 1}
		// if (jQuery(e).find('[fo_tab_target="price"]').val()!='') {
		var temp_r_price =  parseFloat( jQuery(e).find('[fo_tab_target="price"]').val() );
			if (temp_r_price==''||temp_r_price==null||!temp_r_price) {temp_r_price = 0}
		temp_r_price = parseFloat( temp_r_price * qty_r);
		// }
		tot_r = parseFloat( tot_r ) + parseFloat( temp_r_price );
	});
	jQuery('.fo_tool_riepilogo .fo_tab_tool_total strong').text( parseFloat(tot_r).toFixed(2) + jQuery('.fo_woo_symb').text() );

	var tot_s = 0.00;
	jQuery('.fo_column_story .fo_tab_prod.fo_tab_prod_story[fotableid="'+jQuery('input[name="table_ID"]').val()+'"]').each(function(i,e){
		var temp_s_price = 0.00;
		var qty_s = parseFloat(jQuery(e).find('[fo_tab_target="qty"]').val());
			if (qty_s==''||qty_s==null) {qty_s = 1}
		// if (jQuery(e).find('[fo_tab_target="price"]').val()!='') {
		var temp_s_price =  parseFloat( jQuery(e).find('[fo_tab_target="price"]').val() );
			if (temp_s_price==''||temp_s_price==null) {temp_s_price = 0}
		temp_s_price = parseFloat( temp_s_price * qty_s );
		// }
		tot_s = parseFloat( tot_s ) + parseFloat( temp_s_price );
	});
	jQuery('.fo_tool_story .fo_tab_tool_total strong').text( parseFloat(tot_s).toFixed(2) + jQuery('.fo_woo_symb').text() );

	var tot_p = 0.00;
	jQuery('.fo_pay_column .fo_tab_prod').each(function(i,e){
		var temp_p_price = 0.00;
		var qty_p = parseFloat(jQuery(e).find('[fo_tab_target="qty"]').val());
			if (qty_p==''||qty_p==null) {qty_p = 1}
		// if (jQuery(e).find('[fo_tab_target="price"]').val()!='') {
		var temp_p_price =  parseFloat( jQuery(e).find('[fo_tab_target="price"]').val() );
			if (temp_p_price==''||temp_p_price==null) {temp_p_price = 0}
			temp_p_price = parseFloat( temp_p_price * qty_p );
		// }
		tot_p = parseFloat( tot_p ) + parseFloat( temp_p_price );
	});
	jQuery('.fo_pay_total').text( parseFloat(tot_p).toFixed(2) + jQuery('.fo_woo_symb').text() );
}


function siqo_tab_add_product_to_order(){
	var copy = jQuery('.fo_column_products .fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"]').clone();

	copy.attr('draggable','false');
	copy.attr('ontouchmove','');
	copy.attr('ondragstart','');
	copy.attr('ontouchcancel','');
	copy.attr('ontouchend','');

	if ( copy.attr('fo_type') == 'new' ) {
		var index = jQuery('.fo_tab_prod_index').text();
		var index_story = jQuery('.fo_tab_prod_index_story').text();
	// copy.find('input').attr('name', '['+index+']'+copy.find('input').attr('name') );
		copy.find('input').each(function(i,e){
			jQuery(e).attr('name', '['+index+']'+jQuery(e).attr('name') );
		});
		copy.attr('fo_index', index);
		copy.attr('fo_index_story', index_story);
		copy.attr('fo_type','modify');
		copy.removeClass('fo_tab_prod_selected');
		copy.find('.fo_tab_prod_remove').show();
		copy.find('.fo_tab_prod_remove').attr('onclick','fo_tab_prod_remove(this);');
		jQuery('.fo_column_riepilogo').append(copy.first());

		jQuery('.fo_tab_prod_index').text( parseInt(index) + 1 );
		jQuery('.fo_tab_prod_index_story').text( parseInt(index_story) + 1 );

		jQuery('.fo_column_riepilogo .fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"]').click();
		// fo_ajax_riepilogo_height();
		// fo_tab_hystory_space('.fo_column_riepilogo','.fo_column_story','.fo_column_products');
	}
}


function siqo_tab_prod_remove( input ){
	jQuery('.FO_flash_tab_header').removeClass('fo_tab_prod_modify');
	jQuery('.FO_flash_tab_footer').removeClass('fo_tab_prod_modify');

	jQuery('.fo_tab_col_2, .fo_tab_col_3').removeClass('FO_story_background');
	jQuery('.fo_tab_col_2, .fo_tab_col_3').removeClass('FO_new_background');

	jQuery(input).parent().remove();
	SIQO_calc_totals();
}

function siqo_tab_parse_price( input ){
	var prod = jQuery('.fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"][fo_index="'+jQuery('.fo_actual_index').text()+'"][fo_index_story="'+jQuery('.fo_actual_index_story').text()+'"]');
	var DisplayValue = jQuery(input).text();
	if (DisplayValue==''||DisplayValue==null) { DisplayValue='0' }
	if ( jQuery(input).attr('fo_modificable') == 'true' ) {
		prod.find('input[fo_tab_target="price"]').val(DisplayValue);
		if ( jQuery(input).text() == '' ) {
			prod.find('input[fo_tab_target="price"]').val(jQuery('.fo_tab_reset_price').attr('fovalue'));
		}
		if ( prod.attr('fo_special') == 'Sconto' ) {
			prod.find('input[fo_tab_target="id"]').val(DisplayValue);
		}
		if ( prod.attr('fo_special') == 'Special' ) {
			prod.find('input[fo_tab_target="id"]').val(DisplayValue);
		}
	}
}

function siqo_tab_parse_qty( input ){
	var prod = jQuery('.fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"][fo_index="'+jQuery('.fo_actual_index').text()+'"][fo_index_story="'+jQuery('.fo_actual_index_story').text()+'"]');
	if ( jQuery(prod).attr('fo_modificable') == 'true' ) {
		if ( prod.attr('fo_special') == 'Special' ||  prod.attr('fo_special') == 'Sconto' ) {
			prod.find('input[fo_tab_target="qty"]').val(jQuery(input).val());
		} else{
			prod.find('input[fo_tab_target="id"]').val(jQuery(input).val());
			prod.find('input[fo_tab_target="qty"]').val(jQuery(input).val().trim());
		}
	}
	SIQO_calc_totals();
}

function siqo_tab_parse_note( input ){
	var prod = jQuery('.fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"][fo_index="'+jQuery('.fo_actual_index').text()+'"][fo_index_story="'+jQuery('.fo_actual_index_story').text()+'"]');
	if ( jQuery(prod).attr('fo_modificable') == 'true' ) {
		if ( prod.attr('fo_special') != 'Special' &&  prod.attr('fo_special') != 'Sconto' ) {
			prod.find('input[fo_tab_target="note"]').val(jQuery(input).val());
		}
	}
}

function siqo_tab_parse_temp( input ){
	var prod = jQuery('.fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"][fo_index="'+jQuery('.fo_actual_index').text()+'"][fo_index_story="'+jQuery('.fo_actual_index_story').text()+'"]');
	if ( jQuery(prod).attr('fo_modificable') == 'true' ) {
		if ( prod.attr('fo_special') != 'Special' &&  prod.attr('fo_special') != 'Sconto' ) {
			prod.find('input[fo_tab_target="Temperature"]').val(jQuery(input).val());
		}
	}
}

function siqo_tab_parse_variant( input ){
	var prod = jQuery('.fo_tab_prod[foprodid="'+jQuery('.fo_actual_prod').text()+'"][fo_index="'+jQuery('.fo_actual_index').text()+'"][fo_index_story="'+jQuery('.fo_actual_index_story').text()+'"]');

	if ( prod.attr('fo_modificable') == 'true' ) {
		var price = parseFloat(prod.find('input[fo_tab_target="price"]').attr('regularPrice')).toFixed(2);
		var finalPrice = price;
		var priceToAdd = 0.00;
	//variant --------------------------------
		prod.find('input[fo_tab_target="variante"][fovariant="'+jQuery(input).attr('fovariant')+'"]').val(jQuery(input).val());
		var variVal = prod.find('input[fo_tab_target="variante"][fovariant="'+jQuery(input).attr('fovariant')+'"]').val();
		prod.find('input[fo_tab_target="variante"][fovariant="'+jQuery(input).attr('fovariant')+'"]').attr('priceadded',jQuery(input).parent().attr('fo_price_to_add'));
	//price ----------------------------------
		prod.find('input[fo_tab_target="variante"]').each(function(i,e){
			if ( jQuery(e).attr('priceadded') != null && jQuery(e).attr('priceadded') != '' ) {
				priceToAdd = parseFloat( parseFloat(priceToAdd)+parseFloat( jQuery(e).attr('priceadded') ) ).toFixed(2);
			}
		});
		finalPrice = parseFloat(  parseFloat(price)+parseFloat(priceToAdd) ).toFixed(2);
		jQuery('.fo_tab_price').text( parseFloat(finalPrice).toFixed(2) );
		jQuery('.fo_tab_reset_price').attr('fovalue', parseFloat(finalPrice).toFixed(2) );

		prod.find('input[fo_tab_target="price"]').val(parseFloat(finalPrice).toFixed(2));
	}
}

function siqo_tab_price_default( input ){
	var price = jQuery(input).attr('fovalue');
	if (price!='') {
		jQuery('.fo_tab_price').text( parseFloat(price).toFixed(2));
		jQuery('.fo_tab_price').attr('fo_status_modify','false');
	}
}
function siqo_keyboard_price( number ){
	var price = jQuery('.fo_tab_price').text();
	var modify = jQuery('.fo_tab_price').attr('fo_status_modify');
	if (modify == 'false') {
		if (number == '.') { jQuery('.fo_tab_price').text('0.') }else{
			jQuery('.fo_tab_price').text(number);
		}
	}
	if (modify == 'true') {
		if (number == '.') {
			if (price == '') { jQuery('.fo_tab_price').text('0.'); 
			}else if (price == '0.') { jQuery('.fo_tab_price').text(price);
			}else if (price.includes(".")) {jQuery('.fo_tab_price').text(price.replace(".", "")+'.');
			} else{ jQuery('.fo_tab_price').text( price + number );}
		} else{
			jQuery('.fo_tab_price').text( price + number );
		}
	}
	jQuery('.fo_tab_price').attr('fo_status_modify','true');
	fo_tab_parse_price( jQuery('.fo_tab_price') );
}

function siqo_tab_price_del( input ){
	var price = jQuery('.fo_tab_price').text();
	jQuery('.fo_tab_price').text( price.slice(0, -1) );
	jQuery('.fo_tab_price').attr('fo_status_modify','true');
}

function siqo_tab_price_ac( input ){
	jQuery('.fo_tab_price').text('');
	jQuery('.fo_tab_price').attr('fo_status_modify','true');
}

function siqo_keyboard_special( number, text ){
	jQuery('.fo_tab_price').attr('fo_status_modify','true');
	if ( number == 'special' ) {
		SIQO_add_special_product(text);
	} else if(number == 'sub'){

	} else if(number == '-'){
		SIQO_add_discount_product(number);
	} else if(number == '%'){
		SIQO_add_discount_product(number);
	} else {}
	// fo_actual_table
	var price = jQuery('.fo_tab_price').text();
	var modify = jQuery('.fo_tab_price').attr('fo_status_modify');
}

function SIQO_add_discount_product( number ){
	var prodSpecial = jQuery('.fo_generic_prod .fo_tab_prod_discount').clone();
	var index = jQuery('.fo_tab_prod_index').text();
	var index_story = jQuery('.fo_tab_prod_index_story').text();
	var tab_price = jQuery('.fo_tab_price').text();
	if (tab_price=='') {tab_price='0';}
	if (parseFloat(tab_price) <= 0) {
		return false;
	}
	prodSpecial.find('input').each(function(i,e){
		jQuery(e).attr('name', '['+index+']'+jQuery(e).attr('name')+'[Sconto'+index+']' );
	});
	prodSpecial.attr('foprodid', 'Sconto'+index );
	prodSpecial.attr('fo_index', index);
	prodSpecial.attr('fo_index_story', index_story);
	prodSpecial.attr('foprodtot', tab_price);

	prodSpecial.removeClass('fo_tab_prod_selected');

	prodSpecial.find('[fo_tab_target="id"]').val(tab_price);

	prodSpecial.find('[fo_tab_target="price"]').val(tab_price);
	prodSpecial.find('[fo_tab_target="price"]').attr('regularPrice',tab_price);

	if (number == '-') {
		prodSpecial.find('.fo_tab_prod_price').text('- '+parseFloat(tab_price).toFixed(2)+' '+jQuery('.fo_woo_symb').text());
	} else if (number == '%') {
		prodSpecial.find('.fo_tab_prod_price').text(tab_price+' %');
	} else{
		prodSpecial.find('.fo_tab_prod_price').text(tab_price);
	}
	

	prodSpecial.find('.fo_tab_prod_remove').show();
	prodSpecial.find('.fo_tab_prod_remove').attr('onclick','fo_tab_prod_remove(this);');
	jQuery('.fo_column_riepilogo').append(prodSpecial);

jQuery('.fo_column_riepilogo .fo_tab_prod_special[foprodid="Special'+index+'"]').click();

	jQuery('.fo_tab_prod_index').text( parseInt(index) + 1 );
	jQuery('.fo_tab_prod_index_story').text( parseInt(index_story) + 1 );

	fo_tab_hystory_space('.fo_column_riepilogo','.fo_column_story','.fo_column_products');
}



function SIQO_add_special_product( text ){
	var prodSpecial = jQuery('.fo_generic_prod .fo_tab_prod_special').clone();
	var index = jQuery('.fo_tab_prod_index').text();
	var index_story = jQuery('.fo_tab_prod_index_story').text();
	var tab_price = jQuery('.fo_tab_price').text();
	if (tab_price==''||tab_price==null) {tab_price='0';}

	prodSpecial.find('input').each(function(i,e){
		jQuery(e).attr('name', '['+index+']'+jQuery(e).attr('name')+'['+text+index+']' );
	});
	prodSpecial.attr('foprodid', text+index );
	prodSpecial.attr('fo_index', index);
	prodSpecial.attr('fo_index_story', index_story);
	prodSpecial.attr('foprodtot', tab_price);

	prodSpecial.removeClass('fo_tab_prod_selected');

	prodSpecial.find('[fo_tab_target="price"]').val(tab_price);
	prodSpecial.find('[fo_tab_target="id"]').val(tab_price);

	prodSpecial.find('[fo_tab_target="price"]').attr('regularPrice',tab_price);
	prodSpecial.find('.FO_prod_name_manage').text(text);

	prodSpecial.find('.fo_tab_prod_remove').show();
	prodSpecial.find('.fo_tab_prod_remove').attr('onclick','fo_tab_prod_remove(this);');
	jQuery('.fo_column_riepilogo').append(prodSpecial);

jQuery('.fo_column_riepilogo .fo_tab_prod_special[foprodid="'+text+index+'"]').click();

	jQuery('.fo_tab_prod_index').text( parseInt(index) + 1 );
	jQuery('.fo_tab_prod_index_story').text( parseInt(index_story) + 1 );

	fo_tab_hystory_space('.fo_column_riepilogo','.fo_column_story','.fo_column_products');
}
















function SIQO_tab_Card_show( target ){
	jQuery('.fo_tab_show').show();
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');

	jQuery('.fo_story').hide();
	jQuery('.fo_story').removeClass('fo_tab_prod_story');
	jQuery('.fo_story[fotableid="'+jQuery(target).attr('fo_tableid')+'"]').show();
	jQuery('.fo_story[fotableid="'+jQuery(target).attr('fo_tableid')+'"]').addClass('fo_tab_prod_story');

	jQuery('input[name="table_name_cpt"]').val( jQuery(target).attr('fotable') );
	jQuery('input[name="table_ID"]').val( jQuery(target).attr('fo_tableid') );

	jQuery('input[name="table_start_time"]').val( jQuery(target).attr('table_start_time') );
	jQuery('input[name="table_last_update"]').val( jQuery(target).attr('table_last_update') );
	jQuery('input[name="table_orders"]').val( jQuery(target).attr('table_orders') );
	jQuery('input[name="table_info"]').val( jQuery(target).attr('table_info') );
	jQuery('input[name="table_receipt"]').val( jQuery(target).attr('table_receipt') );
	jQuery('input[name="table_other"]').val( jQuery(target).attr('table_other') );
	jQuery('input[name="table_totals"]').val( jQuery(target).attr('table_totals') );
	jQuery('input[name="table_table_tableid"]').val( jQuery(target).attr('table_table_tableid') );

	jQuery('.fo_actual_table').text( jQuery(target).attr('fo_tableid') );
	// if (true) {}fo_order_table_story 
	jQuery('.fo_pay_table_button').attr('fotableid', jQuery(target).attr('fo_tableid') );

	jQuery('.fo_tab_table_name').text( jQuery(target).attr('fotable') );
	jQuery('.fo_tab_table_name').parent().attr('style', jQuery(target).attr('style'));

	if ( jQuery(target).attr('fotable_status') > 0 ) {
		jQuery('.fo_pay_table_button').show();
		jQuery('.fo_clear_table_button').show();
	} else { 
		jQuery('.fo_pay_table_button').hide(); 
		jQuery('.fo_clear_table_button').hide(); 
	}
	SIQO_calc_totals();
}
function SIQO_tab_Card_hide(){
	jQuery('.fo_tab_show').hide();
	// jQuery('header').css('display','');
	// jQuery('footer').css('display','');
	SIQO_toggle_header_footer();
}

function SIQO_confirm( call_b, text, text_v = '', text_f = '' ){
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');
	// jQuery('.fo_tab_show').hide();
	if (text_v == '') {text_v = ' si '}
	if (text_f == '') {text_f = ' no '}
	var ELconfirm = '';
	ELconfirm += '<div class="SIQO_confirm_container">';
		ELconfirm += '<div class="Advanced_Card_background"></div>';
		ELconfirm += '<div class="SIQO_confirm_message">';
			ELconfirm += '<p style="flex-basis:100%;">'+text+'</p>';
		ELconfirm += '<button style="margin-right:auto;min-width:75px;" class="fo_button" onclick="jQuery(this).closest(`.SIQO_confirm_container`).remove();'+call_b+'">'+
			text_v+'</button>';
		ELconfirm += '<button style="margin-left:auto;min-width:75px;" class="fo_button" onclick="jQuery(this).closest(`.SIQO_confirm_container`).remove();">'+
			text_f+'</button>';
			ELconfirm += '</div>';
		ELconfirm += '</div>';
	ELconfirm += '</div>';
	jQuery('body').append(ELconfirm);
}

function SIQO_alert( call_b, text, text_v = '' ){
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');
	// jQuery('.fo_tab_show').hide();
	if (text_v == '') {text_v = ' OK '}
	var ELconfirm = '';
	ELconfirm += '<div class="SIQO_confirm_container">';
		ELconfirm += '<div class="Advanced_Card_background"></div>';
		ELconfirm += '<div class="SIQO_confirm_message">';
			ELconfirm += '<p style="flex-basis:100%;">'+text+'</p>';
		ELconfirm += '<button style="margin-left:auto;margin-right:auto;min-width:75px;" class="fo_button" onclick="jQuery(this).closest(`.SIQO_confirm_container`).remove();'+call_b+'">'+
			text_v+'</button>';
			ELconfirm += '</div>';
		ELconfirm += '</div>';
	ELconfirm += '</div>';
	jQuery('body').append(ELconfirm);
}

function SIQO_tab_clear_table( def_confirm = false, hide = true ){
	if ( def_confirm == false ) {
		text = jQuery('#SIQO_tab_clear_table_text').text();
		// if ( !SIQO_confirm('SIQO_tab_clear_table(true)',text) ) { return false; }
		SIQO_confirm('SIQO_tab_clear_table(true)',text);
		return false;
	}
	// jQuery('.FOloadingCardPublic').show();
	jQuery('.FO_flash_tab_order_container').find('.FOloadingCardPublic').fadeOut();

	jQuery('.fo_table_cell[fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"]').attr('onclick','return false;');
	// jQuery(".FOloadingCardPublicMain").fadeOut(200);
	jQuery('.FO_table_grid [fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"] .FOloadingTable').show();
	if (hide) {
		SIQO_tab_Card_hide();
	}
	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
		    action: 'SIQO_flash_tab_clear_table',
		    nonce: siqo_ajax_view_orders_vars.nonce,
		    table_name_cpt: jQuery('input[name="table_name_cpt"]').val(),
		    table_id: jQuery('input[name="table_ID"]').val(),
		    _siqononce_flash_tab_order: jQuery('input[name="_siqononce_flash_tab_order"]').val(),
		    // def_confirm: def_confirm,
		},
		success: function (response) {
			console.log(response);
			// console.log(jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]'));
			// jQuery('.SIQO_order_list_summary').empty();
			// var strong = jQuery('.fo_column_story strong').clone();
			jQuery('.fo_order_table_story[fotableid="'+response.table_id+'"][fo_order_id]').remove();
			jQuery('.fo_tab_prod[fo_type="story"][fotableid="'+response.table_id+'"]').remove();
			// jQuery('.fo_column_story').append(strong);
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').attr('fotable_status','0');
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').css('backgroundColor','var(--fo-tab-1)');
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_status_string')
				.text(jQuery('#fo_tab_table_status_free_text').text());
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_tab_notify').hide();
				
			// jQuery('.FO_flash_tab_order_container').find('.FOloadingCardPublic').fadeOut();
			// SIQO_tab_Card_hide();
			jQuery(".FOloadingCardPublicMain").fadeOut(200);
			jQuery('.fo_table_cell[fo_tableid="'+response.table_id+'"]').attr('onclick','SIQO_tab_Card_show(this)');
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"] .FOloadingTable').hide();
			return;
		}
	});
}


function siqo_clear_all_tables( def_confirm = false ){
	if ( def_confirm == false ) {
		text = jQuery('#fo_tab_clear_all_table_text').text();
		SIQO_confirm('siqo_clear_all_tables(true)',text);
		return false;
	}
	jQuery('.FOloadingCardPublic').show();
	jQuery('.FO_flash_tab_order_container').find('.FOloadingCardPublic').fadeOut();

	jQuery('.fo_table_cell').attr('onclick','return false;');
	jQuery('.FO_table_grid .FOloadingTable').show();
	SIQO_tab_Card_hide();
	SIQO_statistics_hide();

	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
		    action: 'SIQO_flash_tab_clear_all_tables',
		    nonce: siqo_ajax_view_orders_vars.nonce,
		    _siqononce_flash_tab_order: jQuery('input[name="_siqononce_flash_tab_order"]').val(),
		},
		success: function (response) {
			console.log(response);
			jQuery('.fo_order_table_story[fo_order_id]').remove();
			jQuery('.fo_tab_prod[fo_type="story"]').remove();
			jQuery('.fo_table_cell').attr('fotable_status','0');
			jQuery('.fo_table_cell').css('backgroundColor','var(--fo-tab-1)');
			jQuery('.fo_table_cell').find('.fo_status_string').text(jQuery('#fo_tab_table_status_free_text').text());

			jQuery(".FOloadingCardPublicMain").fadeOut(200);
			jQuery('.fo_table_cell').attr('onclick','SIQO_tab_Card_show(this)');
			jQuery('.FO_table_grid').find('.fo_tab_notify').hide();
			jQuery('.FO_table_grid .FOloadingTable').hide();
			return;
		}
	});
}

function SIQO_tab_go_order(input){
	text = jQuery('#fo_tab_go_order_text').text();
	// if (confirm(text) == true) {
		jQuery('.FOloadingCardPublic').show();
		if (jQuery(input).attr('fo_order_type') == 'new_order') {
			SIQO_order_tab_ajax();
		} else if(jQuery(input).attr('fo_order_type') == 'modify_order'){
		} else{ }
	// }
}

// in the pay function:   (input = '.fo_pay_ajax_container',action= 'SIQO_flash_tab_pay_ajax' )
function SIQO_order_tab_ajax(input = '.fo_column_riepilogo', action = 'SIQO_flash_tab_order_ajax'){
	if (action === 'SIQO_flash_tab_pay_ajax') {
		if (window.SIQO_pay_in_flight) {
			console.log('Pagamento già in corso.');
			return;
		}
		window.SIQO_pay_in_flight = true;
	}
	var table = jQuery(input);
	var item;
	var foserial = jQuery(table).find('input, textarea').serializeArray();
	var foserialmap = foserial.map(({ name, value }) => ({ [name]: value }));
	foserialmap = foserial.map(item => {
	    var container = {};
	    container[item.name] = item.value;
	    return container;
	}); 
		var Src_custom = jQuery('.FO_customers_container input').serializeArray();
	var Src_customers = Src_custom.map(({ name, value }) => ({ [name]: value }));
	Src_customers = Src_custom.map(item => {
	    var Src_container = {};
	    Src_container[item.name] = item.value;
	    return Src_container;
	}); 
	console.log(Src_customers);

	var created_via = 'tab_ajax';
	if (action == 'SIQO_flash_tab_pay_ajax') {
		created_via = 'pay_tab';
		console.log(' Pay order submitted sucessfully !!! ');
	}else{
		console.log(' order submitted sucessfully !!! ');
	}

	jQuery('.FO_flash_tab_order_container').find('.FOloadingCardPublic').fadeOut();
	jQuery(".FOloadingCardPublicMain").fadeOut(200);

	jQuery('.FO_table_grid [fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"] .FOloadingTable').show();
	jQuery('.fo_table_cell[fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"]').attr('onclick','return false;');

	jQuery('.fo_column_story .fo_title_all[fotableid="'+jQuery('input[name="table_ID"]').val()+'"]')
		.after('<div class="fo_button fo_story fo_order_table_story" fotableid="'+jQuery('input[name="table_ID"]').val()+'" fo_status="Loading" > Loading </div>');

	var Ghost = jQuery(input).find('.fo_tab_prod').clone();
	// Ghost.attr('onclick','');
	jQuery('.fo_ghost_draft').append('<div fotableid="'+jQuery('input[name="table_ID"]').val()+'" index="'+jQuery('.fo_tab_prod_ghost_draft_index').text()+'" fo_status="Loading"></div>');
	jQuery('.fo_ghost_draft [fotableid="'+jQuery('input[name="table_ID"]').val()+'"][index="'+jQuery('.fo_tab_prod_ghost_draft_index').text()+'"]').append( Ghost );
	jQuery('.fo_tab_prod_ghost_draft_index').text( parseInt(jQuery('.fo_tab_prod_ghost_draft_index').text()) +1 );

	jQuery(input).find('.fo_tab_prod').remove();
	SIQO_tab_Card_hide();
	SIQO_pay_tab_Card_hide();

	// console.log(jQuery('input[name="user_ID"]').val());
	
	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
			action: action,
			nonce: siqo_ajax_view_orders_vars.nonce,
			table_name_cpt: jQuery('input[name="table_name_cpt"]').val(),
			order_note: jQuery('textarea[name="order_note"]').val(),
			table_id: jQuery('input[name="table_ID"]').val(),
			table_total: jQuery('input[name="table_total"]').val(),

			user_id: jQuery('input[name="user_ID"]').val(),
			// customer_id: jQuery('input[name="customer_ID"]').val(),
			// N_customers: N_customers,
			S_customers: Src_customers,
			customer_type: jQuery('input[name="customer_type"]').prop('checked'),
			customer_email: siqo_pay_customer_email(),
			customer_id: siqo_pay_customer_id(),
			coupon_codes: siqo_pay_coupon_codes(),
			created_via: created_via,
			foserial: foserial,
			foserialmap: foserialmap,
			_siqononce_flash_tab_order: jQuery('input[name="_siqononce_flash_tab_order"]').val(),
		},
		success: function (response) {
			console.log(response);

			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').css('backgroundColor','var(--fo-tab-2)');
			jQuery('.FO_table_grid').removeClass('fo_tab_prod_last_modify');
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_status_string')
				.text(jQuery('#fo_tab_table_status_close_text').text());

			jQuery('.fo_ghost_draft [fotableid="'+response.table_id+'"][fo_status="Loading"] .fo_tab_prod').each(function(i,e){
				var story = jQuery(e).clone();
				console.log(story);
				story.attr('fo_index_story',response.order_id);
				story.attr('fotableid',response.table_id);
				story.attr('fo_index',jQuery('.fo_tab_prod_index_story').text());
				story.removeClass('fo_tab_prod_modify');
				story.addClass('fo_story');

				story.attr('fo_modificable','false');
				story.attr('fo_type','story');

				story.css('display','none');
				story.find('.fo_tab_prod_remove').remove();
				jQuery('.fo_column_story').append(story);
			});
			jQuery('.fo_ghost_draft [fotableid="'+response.table_id+'"][fo_status="Loading"]').remove();

			jQuery('.fo_order_table_story[fotableid="'+response.table_id+'" ][fo_status="Loading"]').attr('fo_order_id', response.order_id);
			jQuery('.fo_order_table_story[fotableid="'+response.table_id+'"][fo_status="Loading"]').attr('onclick', 'fo_filter_tab_story(this,`'+response.order_id+'`)');
			jQuery('.fo_order_table_story[fotableid="'+response.table_id+'"][fo_status="Loading"]').text( response.order_id );

			jQuery('.fo_order_table_story[fotableid="'+response.table_id+'" ][fo_status="Loading"]').attr('fo_status', 'completed');

			if (action != 'SIQO_flash_tab_pay_ajax') {
				jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').attr('fotable_status','1');
			} else{
				jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').attr('fotable_status','0');
				jQuery('.fo_order_table_story[fotableid="'+response.table_id+'"][fo_order_id]').remove();
				jQuery('.fo_tab_prod[fo_type="story"][fotableid="'+response.table_id+'"]').remove();
				jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').css('backgroundColor','var(--fo-tab-1)');
				jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_status_string')
					.text(jQuery('#fo_tab_table_status_free_text').text());
				jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_tab_notify').hide();
			}

			jQuery('.fo_table_cell[fo_tableid="'+response.table_id+'"]').attr('onclick','SIQO_tab_Card_show(this)');
			jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"] .FOloadingTable').hide();

			return;
		},
		//error: function(response){
		//	console.log(response);
		//}
		complete: function(){
			if (action === 'SIQO_flash_tab_pay_ajax') {
				window.SIQO_pay_in_flight = false;
			}
		}
	});
}




function siqo_tab_hystory_space( input, target, sc_target = '' ){
	// if ( window.screen.width > 1100 ) {
	// 	// if (jQuery(input).find('.fo_tab_prod').length) {
	// 		// jQuery(target).find('.fo_tab_prod').css('scale', 0.2);
	// 		jQuery(target).find('.fo_tab_prod').css('height', '30px');
	// 		jQuery(target).find('.fo_tab_prod').css('width', '45px');
	// 		// jQuery(target).css('height', '40px');
	// 		jQuery(target).css("font-size", "15px");
	// 		// jQuery(target).find('.fo_tab_prod_remove').slideUp();
	// 		// jQuery(input).find('.fo_tab_prod').css('scale', 1);
	// 		jQuery(input).find('.fo_tab_prod').css('height', '90px');
	// 		jQuery(input).find('.fo_tab_prod').css('width', '90px');
	// 		// jQuery(input).css('height', 'calc(50% - 55px)');
	// 		jQuery(input).css("font-size", "");
	// 		jQuery(input).find('.fo_tab_prod_remove').slideDown();
	// 	// }
	// }

jQuery('.fo_column_story, .fo_column_riepilogo, .fo_column_products').removeClass('fo_area_expanded_full');
jQuery('.fo_column_story, .fo_column_riepilogo, .fo_column_products').removeClass('fo_area_expanded_med');
jQuery('.fo_column_story, .fo_column_riepilogo, .fo_column_products').removeClass('fo_area_expanded_thin');

	jQuery(input).addClass('fo_area_expanded_full');
	// jQuery(input).removeClass('fo_area_expanded_thin');
	jQuery(target).addClass('fo_area_expanded_thin');
	// jQuery(target).removeClass('fo_area_expanded_full');
	jQuery(sc_target).addClass('fo_area_expanded_thin');
	// jQuery(sc_target).removeClass('fo_area_expanded_full');
}

function siqo_tab_empty_section( target ){
	jQuery(target).find('.fo_tab_prod').remove();
	SIQO_calc_totals();
}





function siqo_keyboard_qty_value( number ){
	jQuery('.fo_target_qty_prod').val( parseInt(jQuery('.fo_target_qty_prod').val()) + number);
	if( jQuery('.fo_target_qty_prod').val() < 1 ){
		jQuery('.fo_target_qty_prod').val(1);
	}
	fo_tab_parse_qty( jQuery('.fo_target_qty_prod') );
	// jQuery('.fo_target_qty_prod').attr('value',)
}





// function fo_update_table_page(){
// 	setInterval(function () {
//         jQuery.ajax({
//             type: 'POST',
//             url: siqo_ajax_view_orders_vars.ajaxurl,
//             data: {
//                 action: 'SIQO_check_for_orders',
//                 nonce: siqo_ajax_view_orders_vars.nonce,
//                 last_order_id: jQuery('#result-container tr:nth-child(1)').attr('orderid'),
//                 last_order_data: jQuery('#result-container tr:nth-child(1)').attr('orderdata'),
//                 order_id_table: order_id_table,
//             },
//             success: function (response) {
//                 console.log(response);
//                 if ( response.updateNeeded ) {
//                     if (response.newOrders.length) {
//                         response.newOrders.forEach(function(order) {
//                             if ( jQuery('#result-container tr:nth-child(1)').attr('orderid') != order.id ) {
//                                 SIQO_append_to_order( order, response.string );
//                                 SIQO_play_sound_notify();
//                                 SIQO_inizialize_timer();
//                             }
//                         });
//                     }
//                 }
//                 return;
//             }
//         });
//         SIQO_hide_duplicate_orders();
//     }, 1000 );
// }
function SIQO_pay_tab_Card_show( input ){
	jQuery('.FO_flash_tab_order_pay_container').addClass('is-open');
	jQuery('.fo_pay_tab_show').show();
	jQuery('.fo_tab_prod').removeClass('fo_tab_prod_story');
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');
	
	jQuery('.fo_list_items').empty();
	jQuery('.fo_list_final_order').empty();
	jQuery('.fo_pay_total').empty();
	jQuery('.fo_pay_count').empty();
var tot = 0.00;
var pay_items_count = 0;
	jQuery('.fo_tab_prod[fotableid="'+jQuery(input).attr('fotableid')+'"]').each(function(i,e){
		var item_clone = jQuery(e).clone();
		var item_price = parseFloat( jQuery(e).find('[fo_tab_target="price"]').val() ) || 0;
		var item_qty = parseInt( jQuery(e).find('[fo_tab_target="qty"]').val(), 10 ) || 1;
		var item_line_total = item_price * item_qty;
		tot = parseFloat( tot ) + item_line_total;
		item_clone.attr('style','');
		item_clone.removeAttr('onclick');
		item_clone.append('<span class="fo_pay_line_qty">×'+item_qty+'</span><span class="fo_pay_line_price">'+item_line_total.toFixed(2)+'</span>');
		jQuery('.fo_list_items').append(item_clone);
		pay_items_count++;
	});
	jQuery('.fo_column_riepilogo .fo_tab_prod').each(function(i,e){
		var item_clone = jQuery(e).clone();
		var item_price = parseFloat( jQuery(e).find('[fo_tab_target="price"]').val() ) || 0;
		var item_qty = parseInt( jQuery(e).find('[fo_tab_target="qty"]').val(), 10 ) || 1;
		var item_line_total = item_price * item_qty;
		tot = parseFloat( tot ) + item_line_total;
		item_clone.attr('style','');
		item_clone.removeAttr('onclick');
		item_clone.append('<span class="fo_pay_line_qty">×'+item_qty+'</span><span class="fo_pay_line_price">'+item_line_total.toFixed(2)+'</span>');
		jQuery('.fo_list_items').append(item_clone);
		pay_items_count++;
	});
var index = 10000;
	jQuery('.fo_order_table_story[fotableid="'+jQuery(input).attr('fotableid')+'"]').each(function(i,e){
		if ( jQuery(e).attr('fo_order_id') != null ) {
			jQuery('.fo_list_final_order').append('<input type="hidden" name="['+index+i+'][orders]['+jQuery(e).attr('fo_order_id')+']" value="'+jQuery(e).attr('fo_order_id')+'">');
		}
	});
	jQuery('.fo_pay_total')
		.data('siqo-base-total', parseFloat(tot))
		.text( parseFloat(tot).toFixed(2) + jQuery('.fo_woo_symb').text() );
	jQuery('.fo_pay_count').text( pay_items_count );
	SIQO_init_pay_discounts();
}
function SIQO_pay_tab_Card_hide( input ){
	jQuery('.fo_list_items').empty();
	jQuery('.fo_list_final_order').empty();
	jQuery('.fo_pay_total').empty();
	SIQO_reset_pay_discounts();
	jQuery('.FO_flash_tab_order_pay_container').removeClass('is-open');
	jQuery('.fo_pay_tab_show').hide();
	jQuery('.FO_customers_container').empty();
}



function siqo_pay_customer_email() {
	var mail = jQuery('.FO_customers_container .FO_pay_customer_mail').first().text().trim();
	if (!mail || mail === '-') {
		return '';
	}
	return mail.toLowerCase();
}

function siqo_pay_customer_id() {
	var input = jQuery('.FO_customers_container input[name^="[customer_ID]"]').first();
	return input.length ? input.val() : '';
}

function SIQO_remove_pay_customer(element) {
	jQuery(element).parent().remove();
	SIQO_refresh_pay_discounts();
	siqo_pay_quote_coupons();
}

function siqo_coupon_email_match(pattern, email) {
	pattern = String(pattern || '').toLowerCase().trim();
	email = String(email || '').toLowerCase().trim();
	if (!pattern || !email) {
		return false;
	}
	if (pattern === email) {
		return true;
	}
	if (pattern.indexOf('*') === -1) {
		return false;
	}
	var escaped = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
	return new RegExp('^' + escaped + '$').test(email);
}

function siqo_parse_json(value) {
	if (!value) {
		return [];
	}
	if (typeof value === 'object') {
		return value;
	}
	try {
		var parsed = JSON.parse(value);
		return Array.isArray(parsed) ? parsed : [];
	} catch (e) {
		return [];
	}
}

function siqo_pay_coupon_codes() {
	var codes = [];
	jQuery('[data-siqo-applied-code]').each(function () {
		var code = jQuery(this).attr('data-siqo-applied-code');
		if (code && codes.indexOf(code) === -1) {
			codes.push(code);
		}
	});
	return codes;
}

function siqo_pay_coupon_message(message) {
	var box = jQuery('[data-siqo-pay-coupon-message]');
	if (!message) {
		box.prop('hidden', true).text('');
		return;
	}
	box.text(message).prop('hidden', false);
}

function siqo_pay_coupon_buttons(code) {
	return jQuery('[data-siqo-coupon]').filter(function () {
		return jQuery(this).attr('data-code') === code;
	});
}

function siqo_pay_set_coupon_applied(code, applied) {
	siqo_pay_coupon_buttons(code)
		.toggleClass('is-applied', applied)
		.attr('aria-pressed', applied ? 'true' : 'false')
		.find('.siqo-pay-coupon-action')
		.text(applied ? 'Rimuovi' : 'Aggiungi');
}

function siqo_pay_add_coupon(button) {
	var code = jQuery(button).attr('data-code');
	var emails = siqo_parse_json(jQuery(button).attr('data-emails'));
	var email = siqo_pay_customer_email();
	var label = jQuery(button).attr('data-label') || '';

	if (!code) {
		return;
	}
	if (siqo_pay_coupon_codes().indexOf(code) !== -1) {
		return;
	}
	if (emails.length) {
		if (!email) {
			siqo_pay_coupon_message('Seleziona un cliente per usare questo sconto.');
			return;
		}
		var allowed = emails.some(function (pattern) {
			return siqo_coupon_email_match(pattern, email);
		});
		if (!allowed) {
			siqo_pay_coupon_message('Questo sconto non è associato al cliente selezionato.');
			return;
		}
	}

	var minimum = parseFloat(jQuery(button).attr('data-minimum')) || 0;
	var base = parseFloat(jQuery('.fo_pay_total').data('siqo-base-total')) || 0;
	if (minimum > 0 && base < minimum) {
		siqo_pay_coupon_message('Il coupon richiede un minimo di ' + minimum.toFixed(2) + jQuery('.fo_woo_symb').text() + '.');
		return;
	}

	var chip = jQuery('<div>', {
		'class': 'siqo-pay-applied-chip',
		'data-siqo-applied-code': code
	});
	chip.append(jQuery('<span>', { 'class': 'siqo-pay-applied-code' }).text(code));
	chip.append(jQuery('<span>', { 'class': 'siqo-pay-applied-value' }).text(label));
	chip.append(
		jQuery('<button>', {
			type: 'button',
			'class': 'siqo-pay-applied-remove',
			'data-siqo-remove-coupon': code,
			'aria-label': 'Rimuovi ' + code
		}).text('×')
	);

	jQuery('[data-siqo-pay-applied-list]').append(chip);
	jQuery('[data-siqo-pay-applied]').prop('hidden', false);
	siqo_pay_set_coupon_applied(code, true);
	siqo_pay_coupon_message('');
	siqo_pay_quote_coupons();
}

function siqo_pay_remove_coupon(code) {
	if (!code) {
		return;
	}
	jQuery('[data-siqo-applied-code]').filter(function () {
		return jQuery(this).attr('data-siqo-applied-code') === code;
	}).remove();
	siqo_pay_set_coupon_applied(code, false);
	if (!jQuery('[data-siqo-applied-code]').length) {
		jQuery('[data-siqo-pay-applied]').prop('hidden', true);
	}
	siqo_pay_quote_coupons();
}

function siqo_pay_filter_coupons() {
	var term = jQuery('[data-siqo-pay-coupon-search]').val();
	term = term ? String(term).toLowerCase().trim() : '';
	jQuery('[data-siqo-coupon]').each(function () {
		var haystack = [
			jQuery(this).attr('data-code'),
			jQuery(this).attr('data-label'),
			jQuery(this).attr('title')
		].join(' ').toLowerCase();
		jQuery(this).toggle(!term || haystack.indexOf(term) !== -1);
	});
}

function SIQO_refresh_pay_discounts() {
	var email = siqo_pay_customer_email();
	var customerGroup = jQuery('[data-siqo-pay-coupon-customer]');
	var customerEmpty = jQuery('[data-siqo-pay-coupon-customer-empty]');
	var matched = 0;

	customerGroup.find('[data-siqo-coupon]').each(function () {
		var emails = siqo_parse_json(jQuery(this).attr('data-emails'));
		var allowed = !!email && emails.some(function (pattern) {
			return siqo_coupon_email_match(pattern, email);
		});
		jQuery(this).toggle(allowed);
		if (allowed) {
			matched++;
		}
	});

	customerGroup.prop('hidden', !email);
	customerEmpty.prop('hidden', !email || matched > 0);
	jQuery('[data-siqo-pay-customer-count]').text(matched ? matched : '');

	jQuery('[data-siqo-applied-code]').each(function () {
		var code = jQuery(this).attr('data-siqo-applied-code');
		var button = siqo_pay_coupon_buttons(code).first();
		if (!button.length) {
			return;
		}
		var emails = siqo_parse_json(button.attr('data-emails'));
		if (!emails.length) {
			return;
		}
		var allowed = !!email && emails.some(function (pattern) {
			return siqo_coupon_email_match(pattern, email);
		});
		if (!allowed) {
			jQuery(this).remove();
			siqo_pay_set_coupon_applied(code, false);
		}
	});

	if (!jQuery('[data-siqo-applied-code]').length) {
		jQuery('[data-siqo-pay-applied]').prop('hidden', true);
	}

	siqo_pay_filter_coupons();
	jQuery('[data-siqo-pay-general-count]').text(jQuery('[data-siqo-pay-coupon-available] [data-siqo-coupon]:visible').length || '');
}

function siqo_pay_quote_coupons() {
	var codes = siqo_pay_coupon_codes();
	var base = parseFloat(jQuery('.fo_pay_total').data('siqo-base-total')) || 0;
	var discountLine = jQuery('[data-siqo-pay-discount-line]');
	var symbol = jQuery('.fo_woo_symb').text();

	if (!codes.length) {
		jQuery('.fo_pay_total').text(base.toFixed(2) + symbol);
		discountLine.prop('hidden', true).text('');
		siqo_pay_coupon_message('');
		return;
	}

	var items = [];
	jQuery('.fo_list_items .fo_tab_prod').each(function () {
		var productId = parseInt(jQuery(this).attr('foprodid'), 10) || 0;
		var qty = parseInt(jQuery(this).find('[fo_tab_target="qty"]').val(), 10) || 1;
		var price = parseFloat(jQuery(this).find('[fo_tab_target="price"]').val()) || 0;
		if (productId) {
			items.push({ product_id: productId, qty: qty, price: price });
		}
	});

	if (!items.length || typeof siqo_ajax_view_orders_vars === 'undefined') {
		return;
	}

	jQuery('.fo_pay_total').addClass('is-loading');
	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		dataType: 'json',
		data: {
			action: 'SIQO_pay_coupon_quote',
			nonce: siqo_ajax_view_orders_vars.nonce,
			items: items,
			coupons: codes,
			customer_email: siqo_pay_customer_email()
		}
	}).done(function (response) {
		if (!response || !response.success || !response.data) {
			siqo_pay_coupon_message(response && response.data && response.data.message ? response.data.message : 'Impossibile calcolare lo sconto.');
			return;
		}
		var data = response.data;
		jQuery('.fo_pay_total').text(parseFloat(data.total).toFixed(2) + symbol);
		if (parseFloat(data.discount_total) > 0) {
			discountLine.text('- ' + parseFloat(data.discount_total).toFixed(2) + symbol).prop('hidden', false);
		} else {
			discountLine.prop('hidden', true).text('');
		}
		if (data.errors && data.errors.length) {
			siqo_pay_coupon_message(data.errors.map(function (error) {
				return error.message;
			}).join(' '));
		} else {
			siqo_pay_coupon_message('');
		}
	}).fail(function () {
		siqo_pay_coupon_message('Impossibile calcolare lo sconto. Riprova.');
	}).always(function () {
		jQuery('.fo_pay_total').removeClass('is-loading');
	});
}

function SIQO_init_pay_discounts() {
	jQuery('[data-siqo-pay-applied-list]').empty();
	jQuery('[data-siqo-pay-applied]').prop('hidden', true);
	jQuery('[data-siqo-pay-discount-line]').prop('hidden', true).text('');
	jQuery('[data-siqo-pay-coupon-message]').prop('hidden', true).text('');
	jQuery('[data-siqo-pay-coupon-search]').val('');
	jQuery('[data-siqo-coupon]').removeClass('is-applied').attr('aria-pressed', 'false')
		.find('.siqo-pay-coupon-action').text('Aggiungi');
	SIQO_refresh_pay_discounts();
	setTimeout(function () {
		jQuery('.fot_pay_search_customer .focatsearchall').each(function () {
			this.style.setProperty('width', '100%', 'important');
		});
	}, 50);
}

function SIQO_reset_pay_discounts() {
	jQuery('[data-siqo-pay-applied-list]').empty();
	jQuery('[data-siqo-pay-applied]').prop('hidden', true);
	jQuery('[data-siqo-pay-discount-line]').prop('hidden', true).text('');
	jQuery('[data-siqo-pay-coupon-message]').prop('hidden', true).text('');
	jQuery('[data-siqo-coupon]').removeClass('is-applied').attr('aria-pressed', 'false')
		.find('.siqo-pay-coupon-action').text('Aggiungi');
}

jQuery(document)
	.off('click.siqoPayCoupon', '[data-siqo-coupon]')
	.on('click.siqoPayCoupon', '[data-siqo-coupon]', function (event) {
		event.preventDefault();
		if (jQuery(this).hasClass('is-applied')) {
			siqo_pay_remove_coupon(jQuery(this).attr('data-code'));
			return;
		}
		siqo_pay_add_coupon(this);
	})
	.off('click.siqoPayCouponRemove', '[data-siqo-remove-coupon]')
	.on('click.siqoPayCouponRemove', '[data-siqo-remove-coupon]', function (event) {
		event.preventDefault();
		siqo_pay_remove_coupon(jQuery(this).attr('data-siqo-remove-coupon'));
	})
	.off('input.siqoPayCouponSearch', '[data-siqo-pay-coupon-search]')
	.on('input.siqoPayCouponSearch', '[data-siqo-pay-coupon-search]', function () {
		siqo_pay_filter_coupons();
	})
	.off('input.siqoPayCouponSearchKeyup', '[data-siqo-pay-coupon-search]')
	.on('keyup.siqoPayCouponSearchKeyup', '[data-siqo-pay-coupon-search]', function () {
		siqo_pay_filter_coupons();
	});

function SIQO_order_tab_pay_ajax(){
	// jQuery('.FO_flash_tab_order_pay_container .FOloadingCardPublic').show();
	// jQuery('.fo_table_cell[fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"]').attr('onclick','return false;');
	
	// var table = jQuery('.fo_pay_ajax_container'); 
	// var item; 
	// var foserial = jQuery(table).find('input, textarea').serializeArray(); 
	// var foserialmap = foserial.map(({ name, value }) => ({ [name]: value })); 
	// foserialmap = foserial.map(item => {
	// 	var container = {};
	// 	container[item.name] = item.value;
	// 	return container;
	// });
	// console.log(foserialmap);
	// console.log(window);
	// console.log(' order submitted sucessfully !!! ');
	// SIQO_pay_tab_Card_hide();
	// SIQO_tab_Card_hide();
	// jQuery('.FO_flash_tab_order_pay_container .FOloadingCardPublic').hide();
	// jQuery(".FOloadingCardPublicMain").fadeOut(200);

// jQuery('.FO_table_grid [fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"] .FOloadingTable').show();
// jQuery('.fo_table_cell[fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"]').attr('onclick','return false;');

// jQuery('.fo_column_story .fo_title_all[fotableid="'+jQuery('input[name="table_ID"]').val()+'"]')
// 		.after('<div class="fo_button fo_story fo_order_table_story" fotableid="'+jQuery('input[name="table_ID"]').val()+'" fo_status="Loading" > Loading </div>');

// var Ghost = jQuery('.fo_column_riepilogo .fo_tab_prod').clone();
// 	// Ghost.attr('onclick','');
// 	jQuery('.fo_ghost_draft').append('<div fotableid="'+jQuery('input[name="table_ID"]').val()+'" index="'+jQuery('.fo_tab_prod_ghost_draft_index').text()+'" fo_status="Loading"></div>');
// 	jQuery('.fo_ghost_draft [fotableid="'+jQuery('input[name="table_ID"]').val()+'"][index="'+jQuery('.fo_tab_prod_ghost_draft_index').text()+'"]').append( Ghost );
// 	jQuery('.fo_tab_prod_ghost_draft_index').text( parseInt(jQuery('.fo_tab_prod_ghost_draft_index').text()) +1 );

	// jQuery.ajax({
	// 	type: 'POST',
	// 	url: siqo_ajax_view_orders_vars.ajaxurl,
	// 	data: {
	// 	    action: 'SIQO_flash_tab_pay_ajax',
	// 	    nonce: siqo_ajax_view_orders_vars.nonce,
	// 	    table_name_cpt: jQuery('input[name="table_name_cpt"]').val(),
	// 	    order_note: jQuery('textarea[name="order_note"]').val(),
	// 	    table_id: jQuery('input[name="table_ID"]').val(),
	// 	    foserial: foserial,
	// 	    foserialmap: foserialmap,
	// 	    // order_id: ,
	// 	},
	// 	success: function (response) {
	// 		console.log(response);
	// 			// jQuery('.SIQO_order_list_summary').empty();
	// 		// jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').css('backgroundColor','var(--fo-tab-2)');
	// 		// jQuery('.FO_table_grid').removeClass('fo_tab_prod_last_modify');
	// 		// // jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').addClass('fo_tab_prod_last_modify');

	// 		// jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').find('.fo_status_string')
	// 		// 	.text(jQuery('#fo_tab_table_status_close_text').text());
	// 		// 	jQuery('.FO_flash_tab_order_container').find('.FOloadingCardPublic').fadeOut();

	// 		// jQuery('.fo_column_story .fo_story').remove();
	// 		// jQuery('.fo_column_riepilogo .fo_tab_prod').remove();

	// 		// fo_change_order_status();
	// 		SIQO_tab_clear_table(true, false);
	// 	// jQuery('.fo_table_cell[fo_tableid="'+response.table_id+'"]').attr('onclick','SIQO_tab_Card_show(this)');
	// 	// jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"]').attr('fotable_status','0');
	// 	// jQuery('.FO_table_grid [fo_tableid="'+response.table_id+'"] .FOloadingTable').hide();
	// 		// return;
	// 	}
	// });

	// console.log(' order submitted sucessfully 2 !!! ');
	SIQO_order_tab_ajax('.fo_pay_ajax_container', 'SIQO_flash_tab_pay_ajax');
	// SIQO_tab_clear_table(true, false);
}





function siqo_change_order_status( order_id = '', status = '' ){
	if (order_id != '') {
		if (status == '') {status = 'completed'}
		jQuery.ajax({
		    type: 'POST',
		    url: siqo_ajax_view_orders_vars.ajaxurl,
		    data: {
		        action: 'SIQO_ajax_change_order_status',
		        nonce: jQuery('#_siqononce_insert_post_ajax_nonce').val(),
		        order_id: order_id,
		        status: status
		    },
		    success: function(response) {
		        console.log(' ...ajax-response... ');
		        console.log(response);
			}
		});
	}
}



function SIQO_toggle_header_footer( check = false ){
	if (check) {
		if (jQuery('.SIQO_toggle_header_footer').val() == 'true') {
			jQuery('.SIQO_toggle_header_footer').val('false');
		} else{
			jQuery('.SIQO_toggle_header_footer').val('true');
		}
	}
	if ( jQuery('.SIQO_toggle_header_footer').val() == 'true') {
		if (jQuery('header').css('display') == 'none') {
			jQuery('header').css('display','');
			jQuery('footer').css('display','');
		}else{
			jQuery('header').css('display','none');
			jQuery('footer').css('display','none');
		}
	} else{
		jQuery('header').css('display','none');
		jQuery('footer').css('display','none');
	}
}









function SIQO_toggle_inner_checkbox(input){
	if ( jQuery(input).find('input[type="checkbox"]').is(":checked") ) {
		jQuery(input).find('input[type="checkbox"]').prop('checked', false);
	} else{
		jQuery(input).find('input[type="checkbox"]').prop('checked', true);
	}
}
function SIQO_toggle_inverted_checkbox(input){
	if ( jQuery(input).is(":checked") ) {
		jQuery(input).prop('checked', false);
	} else{
		jQuery(input).prop('checked', true);
	}
}
function SIQO_customer_pay(input){
	if ( jQuery(input).is(":checked") ) {
		jQuery('.fot_pay_new_customer').slideUp();
		jQuery('.fot_pay_search_customer').slideDown();
	} else{
		jQuery('.fot_pay_new_customer').slideDown();
		jQuery('.fot_pay_search_customer').slideUp();
	}
}









function SIQO_catering_show(){
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');

	jQuery('.SIQOP_tab_catering_fixed_window').show();
}

function SIQO_catering_hide(){
	SIQO_toggle_header_footer();

	jQuery('.SIQOP_tab_catering_fixed_window').hide();

	jQuery('input[name="fo_catering_id"]').val('');
	jQuery('input[name="fo_tab_catering_title"]').val('');
	jQuery('textarea[name="fo_tab_catering_desc"]').val('');
	jQuery('input[name="fo_delivery_date"]').val('');
	jQuery('select[name="fo_catering_status"]').val('');

	jQuery('input[name="fo_catering_type"]').val('');
}

function siqo_catering_modify( input ){
	jQuery('strong.SIQOP_tab_catering_title').text(jQuery('strong.SIQOP_tab_catering_title').attr('modify'));

	jQuery('input[name="fo_catering_id"]').val(jQuery(input).attr('fo_catering_id'));

	jQuery('input[name="fo_tab_catering_title"]').val(jQuery(input).find('.fo_catering_name').text().replace(/^\s+|\s+$/gm,''));
	jQuery('textarea[name="fo_tab_catering_desc"]').val(jQuery(input).find('.fo_catering_desc').text().replace(/^\s+|\s+$/gm,''));
	jQuery('input[name="fo_delivery_date"]').val(jQuery(input).find('.fo_catering_date').attr('fo_date'));
	jQuery('select[name="fo_catering_status"]').val(jQuery(input).find('.fo_catering_status').attr('fo_status'));
	jQuery('input[name="fo_catering_type"]').val('update');
	SIQO_catering_show();
}


function SIQO_catering_save( def_confirm = false ){
	var title = jQuery('input[name="fo_tab_catering_title"]').val();
	var date = jQuery('input[name="fo_delivery_date"]').val();
	if ( title == '' || date == '' ) {
		SIQO_alert('', jQuery('#fo_tab_create_catering_error_text').text() );
		return false;
	}
	if ( def_confirm == false ) {
		if ( jQuery('input[name="fo_catering_type"]').val() == 'update' ) {
			var text = jQuery('#fo_tab_refresh_catering_text').text();
		} else{
			var text = jQuery('#fo_tab_create_catering_text').text();
		}
		
		SIQO_confirm('SIQO_catering_save( true )', text + '</br> 	"' + title + '" ?' );
		return false;
	}
	// jQuery('.fo_table_cell[fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"]').attr('onclick','return false;');
	jQuery(".FOloadingCardPublicMain").show();
	// jQuery('.FO_table_grid [fo_tableid="'+jQuery('input[name="table_ID"]').val()+'"] .FOloadingTable').show();
	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
		    action: 'SIQO_insert_post_ajax',
		    nonce: siqo_ajax_view_orders_vars.nonce,
		    cpt: 'catering',
		    title: jQuery('input[name="fo_tab_catering_title"]').val(),
		    content: jQuery('textarea[name="fo_tab_catering_desc"]').val(),
		    fo_delivery_date: jQuery('input[name="fo_delivery_date"]').val(),
		    fo_catering_status: jQuery('select[name="fo_catering_status"]').val(),
		    post_id: jQuery('input[name="fo_catering_id"]').val(),
		    _siqononce_insert_post_ajax_nonce: jQuery('input[name="_siqononce_insert_post_ajax_nonce"]').val(),
		},
		success: function (response) {
			console.log(response);
			SIQO_catering_hide();
			javascript:history.go(0);
			return;
		}
	});

}
function SIQO_catering_delete( input_id, def_confirm = false ){
	if ( def_confirm == false ) {
		var title = jQuery('input[name="fo_tab_catering_title"]').text();
		var text = jQuery('#fo_tab_create_catering_delete_text').text();
		SIQO_confirm('SIQO_catering_delete('+ input_id +', true )', text + '</br> 	"' + title + '" ?' );
		return false;
	}
	jQuery(".FOloadingCardPublicMain").show();

	jQuery.ajax({
		type: 'POST',
		url: siqo_ajax_view_orders_vars.ajaxurl,
		data: {
		    action: 'SIQO_trash_post_ajax',
		    nonce: siqo_ajax_view_orders_vars.nonce,
		    cpt: 'catering',
		    delete_id: input_id,
		     _siqononce_insert_post_ajax_nonce: jQuery('input[name="_siqononce_insert_post_ajax_nonce"]').val(),
		},
		success: function (response) {
			console.log(response);
			SIQO_catering_hide();
			javascript:history.go(0);
			return;
		}
	});

}


function SIQO_settings_show(){
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');
	jQuery('#SIQO_active_settings').animate({
		right: '0px',
	}, 300);
	jQuery('.fo_setting_show').fadeIn();
	setTimeout(function() {
		jQuery('#SIQO_active_settings').addClass('setting_is_show');
	}, 1000);
}

function SIQO_statistics_show(){
	SIQO_toggle_header_footer();
	jQuery('header').css('display','none');
	jQuery('footer').css('display','none');

	fo_stat_filter_table();
	jQuery('.fo_stat_container').slideDown();
	jQuery('.fo_statistic_show').fadeIn();
	

	setTimeout(function() {
		jQuery('.fo_stat_container').addClass('setting_is_show');
	}, 1000);
}

function SIQO_statistics_hide(){
	SIQO_toggle_header_footer();

	jQuery('.fo_stat_container').removeClass('setting_is_show');
	jQuery('.fo_stat_container').slideUp();
	jQuery('.fo_statistic_show').fadeOut();
}

function SIQO_settings_hide(){
	var str
	if ( window.screen.width < 1100 ) {
		str = '-100%';
	} else{ str = '-50%'; }
	jQuery('#SIQO_active_settings').animate({
		right: str,
	}, 300);
	jQuery('.fo_setting_show').fadeOut();
	jQuery('#SIQO_active_settings').removeClass('setting_is_show');
	SIQO_toggle_header_footer();
}



function fo_tab_show_keyboard_button(){
	if ( jQuery('.fo_tab_col_3').attr('fo_show') == 'hide' ) {
		jQuery('.fo_tab_col_3').show();
		jQuery('.fo_tab_col_3').attr('fo_show','show');
	} else{
		jQuery('.fo_tab_col_3').hide();
		jQuery('.fo_tab_col_3').attr('fo_show','hide');
	}
}

function fo_tab_show_variation_button(){
	if (  window.screen.width > 600 && window.screen.width <= 1100 ) {
		if ( jQuery('.fo_tab_col_2').attr('fo_show') == 'hide' ) {
			jQuery('.fo_tab_col_2').show();
			jQuery('.fo_tab_col_2').attr('fo_show','show');
			jQuery('.fo_tab_col_1').attr('style','width:calc(65% - 21px);');
		} else{
			jQuery('.fo_tab_col_2').hide();
			jQuery('.fo_tab_col_2').attr('fo_show','hide');
			jQuery('.fo_tab_col_1').attr('style','width:calc(100% - 21px);');
		}
		
	} else if ( window.screen.width <= 600 ) {
		jQuery('.fo_tab_col_1').attr('style','width:calc(100% - 21px);');
		if ( jQuery('.fo_tab_col_2').attr('fo_show') == 'hide' ) {
			jQuery('.fo_tab_col_2').show();
			jQuery('.fo_tab_col_2').attr('fo_show','show');
			//jQuery('.fo_tab_col_1').attr('style','width:calc(55% - 21px);');
		} else{
			jQuery('.fo_tab_col_2').hide();
			jQuery('.fo_tab_col_2').attr('fo_show','hide');
			//jQuery('.fo_tab_col_1').attr('style','width:calc(65% - 21px);');
		}
	} else{

	}
}

















function SIQO_list_view_sel(input){
	jQuery('.foProdCard').attr('foview',jQuery(input).val());
}

function SIQO_document_config(){
	if (typeof siqo_documents_vars !== 'undefined') {
		return siqo_documents_vars;
	}
	return {
		ajaxurl: (typeof siqo_ajax_vars !== 'undefined') ? siqo_ajax_vars.ajaxurl : '',
		nonce: ''
	};
}

function SIQO_open_document_window(){
	var win = window.open('', '_blank', 'width=440,height=760');
	if (!win) {
		return null;
	}
	win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Documento</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f6f3ee;color:#2d2a26;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box}.box{background:#fff;border-radius:14px;padding:24px;box-shadow:0 18px 50px rgba(45,42,38,.12);max-width:340px;text-align:center;font-size:14px;line-height:1.5}.error{color:#a6462f}</style></head><body><div class="box" id="siqo-document-message">Preparazione documento…</div></body></html>');
	win.document.close();
	return win;
}

function SIQO_document_message(win, message, is_error){
	if (!win || win.closed) {
		return;
	}
	win.document.open();
	win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Documento</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f6f3ee;color:#2d2a26;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box}.box{background:#fff;border-radius:14px;padding:24px;box-shadow:0 18px 50px rgba(45,42,38,.12);max-width:340px;text-align:center;font-size:14px;line-height:1.5}.error{color:#a6462f}</style></head><body><div class="box' + (is_error ? ' error' : '') + '" id="siqo-document-message"></div></body></html>');
	win.document.close();
	win.document.getElementById('siqo-document-message').textContent = message;
}

function SIQO_request_document(payload){
	var cfg = SIQO_document_config();
	if (!cfg.ajaxurl || !cfg.nonce) {
		window.alert('Configurazione stampa non disponibile. Ricarica la pagina.');
		return;
	}

	var win = SIQO_open_document_window();
	var data = jQuery.extend({
		action: 'siqo_print_document',
		nonce: cfg.nonce
	}, payload || {});

	jQuery.post(cfg.ajaxurl, data).done(function(response){
		if (!response || !response.success || !response.data) {
			SIQO_document_message(win, (response && response.data && response.data.message) ? response.data.message : 'Impossibile preparare il documento.', true);
			return;
		}

		if (response.data.queued) {
			SIQO_document_message(win, response.data.message || 'Documento inviato in stampa.', false);
			window.setTimeout(function(){ if (win && !win.closed) { win.close(); } }, 1400);
			return;
		}

		if (!response.data.html) {
			SIQO_document_message(win, 'Documento vuoto.', true);
			return;
		}

		if (!win || win.closed) {
			win = window.open('', '_blank', 'width=440,height=760');
		}
		if (!win) {
			window.alert('Abilita i popup per stampare il documento.');
			return;
		}

		win.document.open();
		win.document.write(response.data.html);
		win.document.close();
		win.focus();
		window.setTimeout(function(){
			if (win && !win.closed) {
				win.print();
			}
		}, 450);
	}).fail(function(xhr){
		var message = 'Errore di comunicazione con il server.';
		if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
			message = xhr.responseJSON.data.message;
		}
		SIQO_document_message(win, message, true);
	});
}

function SIQO_print_receipt(order_id){
	if (!order_id) {
		return;
	}
	SIQO_request_document({
		type: 'receipt',
		order_id: order_id
	});
}

function SIQO_print_receipts(btn){
	var table = jQuery('input[name="table_ID"]').val() || '';
	if (!table) {
		table = jQuery(btn).closest('[fotableid]').attr('fotableid') || '';
	}
	if (!table) {
		table = jQuery('.fo_pay_ajax_container').closest('[fotableid]').attr('fotableid') || '';
	}

	var ids = [];
	var seen = {};
	var push_id = function(value){
		value = String(value || '').replace(/[^0-9]/g, '');
		if (value && !seen[value]) {
			seen[value] = true;
			ids.push(value);
		}
	};

	if (table) {
		jQuery('.fo_order_table_story[fotableid="' + table + '"]').each(function(){
			push_id(jQuery(this).attr('fo_order_id'));
		});
	}

	if (!ids.length) {
		jQuery(btn).closest('.fo_tool_story').find('[fo_order_id]').each(function(){
			push_id(jQuery(this).attr('fo_order_id'));
		});
	}

	if (!ids.length) {
		jQuery('.fo_pay_ajax_container .fo_list_final_order input').each(function(){
			push_id(jQuery(this).val());
		});
	}

	if (!ids.length && !table) {
		window.alert('Nessun ordine da stampare per questo tavolo.');
		return;
	}

	SIQO_request_document({
		type: 'preconto',
		table_id: table,
		order_ids: ids,
		table_name: jQuery('input[name="table_name_cpt"]').val() || ''
	});
}



