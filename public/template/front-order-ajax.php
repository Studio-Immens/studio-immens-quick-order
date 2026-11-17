<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
// FO_access_denied();
// FO_loading_message();


function SIQO_front_order_ajax(){

SIQO_soft_access_denied();
// $role = FO_access_autorization();
$user_id = get_current_user();

$net_info = FO_get_connection_info();

$settings = SIQO_get_meta( 'setting_page_id'.get_the_ID() );
if ( isset($settings) && $settings != null ) {
	$settings = json_decode($settings);
}

$options = array(
	'menu' 		=> '',
	'closebtn' 	=> '',
	'sidebtn' 	=> '',
	// 'menu' 		=> '',
);

$tavoli = get_posts( array(
    'numberposts'      => 100,
    'orderby'          => 'title',
    'order'            => 'ASC',
    'post_type'        => 'siqo_tables',
) );

$products = SIQO_get_products_for_loop();

$table_id_valid = 0;
$table_name     = '';
$table_notice   = '';
$table_link_id  = '0';
$product_count  = 0;
$table_session_active = false;

foreach ( $products as $category_products ) {
	$product_count += count( $category_products );
}


// Risoluzione del tavolo dalla chiave pubblica ?t=.
$table_link_key = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
if ( $table_link_key && class_exists( 'SIQO_Table_Sessions' ) ) {
	$key_table_id = SIQO_Table_Sessions::find_table_by_key( $table_link_key );
	if ( $key_table_id ) {
		$table_id_valid = (int) $key_table_id;
		$table_name     = get_the_title( $key_table_id );
	}
}

if ( isset($_GET['_siqononce_tab_check']) && wp_verify_nonce( sanitize_text_field(wp_unslash( $_GET['_siqononce_tab_check'])), 'SIQO_check_table_nonce' ) ) {
// get table_id from link
	if ( isset($_GET['tab_hs']) && sanitize_text_field(wp_unslash($_GET['tab_hs'])) != '' ) {
		$table_link_hs = sanitize_text_field(wp_unslash($_GET['tab_hs']));
	} else{ $table_link_hs = ''; }
// get table_id from link
	if ( isset($_GET['tab_id']) && sanitize_text_field(wp_unslash($_GET['tab_id'])) != '' ) {
		$table_link_id = sanitize_text_field(wp_unslash($_GET['tab_id']));
	} else{ $table_link_id = '0'; }
} else{
	$net_info['is_local'] = false;
}

if ( $table_link_id ) {
	foreach ( $tavoli as $tavolo ) {
		if ( (string) $tavolo->ID === (string) $table_link_id ) {
			$table_id_valid = (int) $tavolo->ID;
			$table_name     = $tavolo->post_title;
			break;
		}
	}
	if ( ! $table_id_valid && '0' !== (string) $table_link_id ) {
		$table_notice = __( 'Il tavolo richiesto non è disponibile. Selezionalo qui sotto.', 'studio-immens-quick-order' );
	}
}

$table_session_active = ( $table_id_valid && class_exists( 'SIQO_Table_Sessions' ) ) ? SIQO_Table_Sessions::is_active( $table_id_valid ) : false;
if ( $table_id_valid && ! $table_session_active ) {
	$table_notice = __( 'Questo tavolo non è ancora attivo. Chiama un operatore per farti aprire il tavolo.', 'studio-immens-quick-order' );
}

	if (isset($settings->showHead) && $settings->showHead == 'no' ) { ?>
		<script type="text/javascript">
			jQuery(window).on('load', function() {
	        	jQuery('#header, .ct-header, .site-header').css('display','none');
	        });
		</script>
	<?php }

	if (isset($settings->showFoot) && $settings->showFoot == 'no' ) { ?>
		<script type="text/javascript">
			jQuery(window).on('load', function() {
	        	jQuery('#footer, .ct-footer, .site-footer').css('display','none');
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

?>
<div class="FOmniContent">
	<?php
	$account_page = get_option( 'woocommerce_myaccount_page_id' ) ? get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) : wp_login_url( get_permalink() );
	$account_url  = is_user_logged_in() ? wp_logout_url( get_permalink() ) : wp_login_url( get_permalink() );
	$account_text = is_user_logged_in() ? __( 'Esci', 'studio-immens-quick-order' ) : __( 'Accedi', 'studio-immens-quick-order' );
	if ( is_user_logged_in() && $account_page ) {
		$account_url = $account_page;
		$account_text = __( 'Account', 'studio-immens-quick-order' );
	}
	SIQO_Management::render_bar(
		'customer',
		array(
			'table_name'    => $table_name,
			'account_url'   => $account_url,
			'account_label' => $account_text,
			'title'         => $table_notice,
		)
	);
	?>

	<div style="display:none!important;">
		<div id="FO_select_variant_alert"><?php esc_html_e( 'Seleziona prima la variante', 'studio-immens-quick-order' );?></div>
		<div id="FO_warehouse_alert"><?php esc_html_e( 'Siamo spiacenti ma il prodotto non é al momento disponibile', 'studio-immens-quick-order' );?></div>
		<div id="FO_access_alert"><?php esc_html_e( 'Per poter effettuare ordini su questa pagina, é necessario Effettuare l\'accesso!', 'studio-immens-quick-order' );?></div>

		<div id="FO_favourite_alert"><?php esc_html_e( 'Il prodotto é già tra i favoriti', 'studio-immens-quick-order' );?></div>

		<div id="FO_home_url"><?php echo esc_attr(get_home_url());?></div>

		<div id="FO_woo_currency_sym"><?php echo esc_attr(get_woocommerce_currency_symbol());?></div>

		<style type="text/css">
			#eltdf-back-to-top{
				margin-bottom: 120px!important;
			}
		</style>
	</div>

	<div class="siqo-customer-shell">
		<section class="siqo-customer-hero">
			<div class="siqo-customer-hero-copy">
				<p class="siqo-eyebrow"><?php echo $table_name ? esc_html( $table_name ) : esc_html__( 'Menù digitale', 'studio-immens-quick-order' ); ?></p>
				<h1><?php esc_html_e( 'Cosa desideri?', 'studio-immens-quick-order' ); ?></h1>
				<p class="siqo-customer-sub"><?php esc_html_e( 'Sfoglia il menù, aggiungi i piatti e invia l’ordine dal tuo tavolo.', 'studio-immens-quick-order' ); ?></p>
			</div>
			<?php if ( $table_name ) { ?>
				<span class="siqo-hero-table">
					<span class="dashicons dashicons-location" aria-hidden="true"></span>
					<?php echo esc_html( $table_name ); ?>
				</span>
			<?php } ?>
		</section>

		<div class="siqo-customer-toolbar">
			<label class="siqo-search-field">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input id="siqo-menu-search" type="search" oninput="SIQO_search_for_all(this)" placeholder="<?php esc_attr_e( 'Cerca nel menù…', 'studio-immens-quick-order' ); ?>" autocomplete="off">
			</label>
			<span class="siqo-toolbar-note"><?php echo esc_html( $product_count ); ?> <?php esc_html_e( 'prodotti', 'studio-immens-quick-order' ); ?></span>
		</div>

		<div class="siqo-customer-layout">
			<aside class="siqo-customer-sidebar">
				<p class="siqo-sidebar-label"><?php esc_html_e( 'Categorie', 'studio-immens-quick-order' ); ?></p>
				<nav class="siqo-category-nav" aria-label="<?php esc_attr_e( 'Categorie del menù', 'studio-immens-quick-order' ); ?>">
					<a class="siqo-cat-link" data-category-link="FO_favourite" data-fav-link href="#FO_favourite" hidden>
						<span class="siqo-cat-icon dashicons dashicons-heart" aria-hidden="true"></span>
						<span class="siqo-cat-name"><?php esc_html_e( 'Preferiti', 'studio-immens-quick-order' ); ?></span>
					</a>
					<?php foreach ( $products as $key => $value ) {
						$term = get_term_by( 'name', $key, 'product_cat' );
						$term_meta = ( SIQOcheck( $term ) ) ? get_term_meta( $term->term_id ) : false;
						$image = ( isset( $term_meta['thumbnail_id'] ) ) ? wp_get_attachment_image_src( $term_meta['thumbnail_id'][0] ) : array( '0' );
						$thumb_url = ( ! empty( $image[0] ) && '0' !== (string) $image[0] ) ? $image[0] : '';
						$cat_id = 'cat-' . sanitize_title( $key );
						?>
						<a class="siqo-cat-link" data-category-link="<?php echo esc_attr( $cat_id ); ?>" href="#<?php echo esc_attr( $cat_id ); ?>">
							<?php if ( $thumb_url ) { ?>
								<img class="siqo-cat-thumb" src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
							<?php } else { ?>
								<span class="siqo-cat-dot" aria-hidden="true"></span>
							<?php } ?>
							<span class="siqo-cat-name"><?php echo esc_html( $key ); ?></span>
							<small><?php echo esc_html( count( $value ) ); ?></small>
						</a>
					<?php } ?>
				</nav>
				<div class="siqo-sidebar-foot">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Tocca un piatto per ingredienti e varianti.', 'studio-immens-quick-order' ); ?></p>
				</div>
			</aside>

			<main class="siqo-customer-menu">
				<div id="products_container" class="siqo-menu-sections">
					<section class="siqo-menu-section siqo-favourites-section" id="FO_favourite" data-category="FO_favourite" hidden>
						<header class="siqo-menu-section-head">
							<span class="siqo-menu-section-icon dashicons dashicons-heart" aria-hidden="true"></span>
							<div class="siqo-menu-section-title">
								<p class="siqo-eyebrow"><?php esc_html_e( 'La tua selezione', 'studio-immens-quick-order' ); ?></p>
								<h2><?php esc_html_e( 'Preferiti', 'studio-immens-quick-order' ); ?></h2>
							</div>
						</header>
						<div class="FO_favourites_container SIQO_favourites_container siqo-menu-grid"></div>
					</section>

					<?php SIQO_products_for_div_loop( $products ); ?>
				</div>
				<p class="siqo-search-empty" id="siqo-search-empty" hidden>
					<?php esc_html_e( 'Nessun piatto trovato. Prova con un altro termine.', 'studio-immens-quick-order' ); ?>
				</p>
			</main>
		</div>
	</div>



		<div class="FO_show_order_summary" onclick="Show_SIQO_Front_Float()">
			<strong class="text_white" style="z-index:99;"> 
				<?php esc_html_e( 'ORDINE', 'studio-immens-quick-order' ); ?> 
			</strong>
			<strong id="FO_order_live_count" class="text_white" style="z-index:99;">0</strong>
			<img class="FO_img_summary" src="" style="display:none;"><?php  ?>
		</div>
		
		<?php SIQO_Advanced_prod_card(); ?>
		
		<div id="FO_Front_Float">
			<button type="button" class="Hide_FO_Front_Float" onclick="Hide_SIQO_Front_Float()" aria-label="<?php esc_attr_e( 'Chiudi', 'studio-immens-quick-order' ); ?>">
				<?php esc_html_e( 'CHIUDI', 'studio-immens-quick-order' ); ?>
			</button>
	<!-- .unbind("hover"); -->
	<!-- onclick="jQuery(this).animate({left:'0%'});" -->
	
			<header class="siqo-drawer-head">
				<p class="siqo-eyebrow"><?php esc_html_e( 'Ordine', 'studio-immens-quick-order' ); ?></p>
				<h2><?php esc_html_e( 'Il tuo ordine', 'studio-immens-quick-order' ); ?></h2>
			</header>

			<div id="FO_sum_order">
			</div>

<?php 
	if ( !SIQO_get_meta('fo_allow_menu_order') ) {
		$net_info['is_local'] = false;
	}
	$net_info['is_local'] = true;
?>
			<div id="order_actions">
				<input type="hidden" id="FO_order_index" name="FO_order_index" value="10000">
				<?php if ($net_info['is_local']) {
					$nonce = wp_create_nonce( 'SIQO_front_order_ajax' );
    				echo '<input type="hidden" id="_siqononce_front_order_ajax" name="_siqononce_front_order_ajax" value="'.esc_attr($nonce).'" />';
				} ?>

				<p><?php esc_html_e( 'Varianti Prodotti', 'studio-immens-quick-order' );?>
					<div id="FOProdNumber" style="margin-left:15px">0</div>
				</p>

				<div id="table_input">
					<?php if ( $table_notice ) { ?>
						<p class="siqo-table-notice"><?php echo esc_html( $table_notice ); ?></p>
					<?php } ?>
					<select class="fo-select" name="table_name_cpt">
						<option value=""> <?php esc_html_e( 'Seleziona il Tavolo', 'studio-immens-quick-order' );?> </option>
						<?php foreach ($tavoli as $tavolo) { ?>
							<?php $is_table_active = class_exists( 'SIQO_Table_Sessions' ) ? SIQO_Table_Sessions::is_active( $tavolo->ID ) : false; ?>
							<option value="<?php echo esc_attr( $tavolo->ID ); ?>"<?php selected( $table_id_valid, $tavolo->ID ); ?><?php disabled( ! $is_table_active ); ?>>
								<?php echo esc_html( $tavolo->post_title ); ?><?php echo $is_table_active ? '' : ' — ' . esc_html__( 'non attivo', 'studio-immens-quick-order' ); ?>
							</option>
						<?php } ?>
					</select>
				</div>

				<div class="FO_order_sub" id="submit_order" onclick="SIQO_validate_order_form_ajax(this);">
					<?php esc_html_e( 'AVVIA ORDINE', 'studio-immens-quick-order' ); ?>
				</div>

			</div>
		</div>
	
		<script type="text/javascript">
			function fo_ajax_form_height(){
				var formVal = window.innerHeight - jQuery("#order_actions").height() - 70;
				jQuery("#FO_sum_order").css("height", formVal );
			}
			window.addEventListener('scroll', fo_ajax_form_height);
			window.addEventListener('resize', fo_ajax_form_height);
			fo_ajax_form_height();

			jQuery(window).on('load', function() {
				jQuery(".FOloadingCardPublicMain").fadeOut(200);
			});

			(function(){
				var cartLink = document.querySelector('[data-siqo-cart]');
				if (cartLink) {
					cartLink.addEventListener('click', function(event){
						event.preventDefault();
						if (typeof Show_SIQO_Front_Float === 'function') {
							Show_SIQO_Front_Float();
						}
					});
				}

				var liveCount = document.getElementById('FO_order_live_count');
				var cartBadge = document.querySelector('[data-siqo-cart-count]');
				if (liveCount && cartBadge) {
					var syncCart = function(){
						cartBadge.textContent = (liveCount.textContent || '0').trim() || '0';
					};
					new MutationObserver(syncCart).observe(liveCount, { childList: true, subtree: true, characterData: true });
					syncCart();
				}

				var favourites = document.querySelector('.FO_favourites_container');
				var favouritesSection = document.querySelector('.siqo-favourites-section');
				var favouritesLink = document.querySelector('[data-fav-link]');
				var refreshFavourites = function(){
					var hasFavourites = !!(favourites && favourites.querySelector('.foProdCard'));
					if (favouritesSection) {
						favouritesSection.hidden = !hasFavourites;
					}
					if (favouritesLink) {
						favouritesLink.hidden = !hasFavourites;
					}
				};
				if (favourites) {
					new MutationObserver(refreshFavourites).observe(favourites, { childList: true });
					refreshFavourites();
				}

				var categoryLinks = Array.prototype.slice.call(document.querySelectorAll('.siqo-cat-link[data-category-link]'));
				var categorySections = Array.prototype.slice.call(document.querySelectorAll('.siqo-menu-section[data-category]'));
				var setActiveCategory = function(id){
					categoryLinks.forEach(function(link){
						link.classList.toggle('is-active', link.getAttribute('data-category-link') === id);
					});
				};
				var updateActiveCategory = function(){
					var threshold = window.scrollY + 180;
					var current = '';
					categorySections.forEach(function(section){
						if (!section.hidden && section.getBoundingClientRect().top + window.scrollY <= threshold) {
							current = section.id;
						}
					});
					setActiveCategory(current);
				};
				window.addEventListener('scroll', updateActiveCategory, { passive: true });
				window.addEventListener('resize', updateActiveCategory);
				categoryLinks.forEach(function(link){
					link.addEventListener('click', function(){
						var searchInput = document.getElementById('siqo-menu-search');
						if (searchInput && searchInput.value !== '' && typeof SIQO_search_for_all === 'function') {
							searchInput.value = '';
							SIQO_search_for_all(searchInput);
						}
						setActiveCategory(link.getAttribute('data-category-link'));
					});
				});
				updateActiveCategory();
			})();
		</script>
	</div>
</div>
<?php
// if ( isset($_POST['submit']) && $_POST['submit'] == 'submit' ) {
// 	flash_orders_ordination();
// }
}






