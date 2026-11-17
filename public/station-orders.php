<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/template/station-table-sessions.php';

define( 'SIQO_STATION_VERSION', '1.0.4' );
define( 'SIQO_STATION_TAXONOMY', 'siqo_preparation_points' );
define( 'SIQO_STATION_META_KEY', '_siqo_station_status' );
define( 'SIQO_STATION_PAGE_SLUG', 'ordini-reparti' );

/**
 * Un ordine in attesa di approvazione pagato non deve restare bloccato.
 *
 * Il flusso di pagamento porta l'ordine fuori dagli stati accettati dal
 * gestore di approvazione (409) mentre Pro non stampa gli ordini `pending`:
 * soldi incassati, nulla stampato. Al pagamento lo stato viene quindi
 * promosso ad `approved` e viene emesso `siqo_order_approved`, cosi' le
 * regole di stampa esistenti possono girare. Questo callback non stampa
 * nulla direttamente ed e' idempotente (agisce solo su `pending`).
 *
 * @param int $order_id       Order ID.
 * @param int $table_id       Unused, kept for the hook signature.
 * @param int $table_name_cpt Unused, kept for the hook signature.
 * @return void
 */
function siqo_station_auto_approve_paid_order( $order_id, $table_id = 0, $table_name_cpt = 0 ) {
	$order_id = absint( $order_id );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( 'pending' !== (string) $order->get_meta( '_siqo_approval' ) ) {
		return;
	}

	$order->update_meta_data( '_siqo_approval', 'approved' );
	$order->add_order_note( __( 'Approvato automaticamente al pagamento.', 'studio-immens-quick-order' ) );
	$order->save();

	do_action( 'siqo_order_approved', $order_id );
}

/**
 * Close the table session behind a paid order.
 *
 * The table payment flow (FO_flash_tab_pay_ajax -> SIQO_flash_tab_order_ajax with
 * $clear = true) fires `siqo_table_paid`; gateways that mark an order as paid fire
 * `woocommerce_payment_complete`. Both carry the order's `_siqo_session_id`, so the
 * session is the source of truth and closing it twice is a no-op. When the meta is
 * missing (legacy or admin order) the table post ID passed by `siqo_table_paid` is
 * used as a fallback.
 *
 * `woocommerce_order_status_processing` is intentionally NOT hooked: approving the
 * first order moves it to `processing` while the table must stay open.
 *
 * @param int $order_id       Order ID.
 * @param int $table_id       Table post ID passed by `siqo_table_paid`.
 * @param int $table_name_cpt Unused, kept for the hook signature.
 * @return bool True when a table session was closed by this call.
 */
function siqo_station_close_paid_table_session( $order_id, $table_id = 0, $table_name_cpt = 0 ) {
	$order_id = absint( $order_id );
	if ( ! $order_id || ! class_exists( 'SIQO_Table_Sessions' ) || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false;
	}

	$session_id = (int) $order->get_meta( '_siqo_session_id' );
	if ( $session_id ) {
		$session = SIQO_Table_Sessions::get( $session_id );
		if ( ! $session || ! in_array( (string) $session->status, array( 'open', 'awaiting_approval' ), true ) ) {
			return false;
		}

		SIQO_Table_Sessions::close( (int) $session->table_id, 'closed' );
		return true;
	}

	$table_id = absint( $table_id );
	if ( $table_id && 'siqo_tables' === get_post_type( $table_id ) && SIQO_Table_Sessions::get_active_for_table( $table_id ) ) {
		SIQO_Table_Sessions::close( $table_id, 'closed' );
		return true;
	}

	return false;
}
add_action( 'siqo_table_paid', 'siqo_station_auto_approve_paid_order', 15, 3 );
add_action( 'woocommerce_payment_complete', 'siqo_station_auto_approve_paid_order', 15, 1 );
add_action( 'siqo_table_paid', 'siqo_station_close_paid_table_session', 20, 3 );
add_action( 'woocommerce_payment_complete', 'siqo_station_close_paid_table_session', 20, 1 );

add_action( 'init', 'siqo_station_register_taxonomy', 0 );
function siqo_station_register_taxonomy() {
	$labels = array(
		'name'                       => __( 'Punti di preparazione', 'studio-immens-quick-order' ),
		'singular_name'              => __( 'Punto di preparazione', 'studio-immens-quick-order' ),
		'menu_name'                  => __( 'Punti di preparazione', 'studio-immens-quick-order' ),
		'all_items'                  => __( 'Tutti i punti', 'studio-immens-quick-order' ),
		'edit_item'                  => __( 'Modifica punto', 'studio-immens-quick-order' ),
		'view_item'                  => __( 'Visualizza punto', 'studio-immens-quick-order' ),
		'update_item'                => __( 'Aggiorna punto', 'studio-immens-quick-order' ),
		'add_new_item'               => __( 'Aggiungi punto', 'studio-immens-quick-order' ),
		'new_item_name'              => __( 'Nuovo punto', 'studio-immens-quick-order' ),
		'search_items'               => __( 'Cerca punti', 'studio-immens-quick-order' ),
		'not_found'                  => __( 'Nessun punto trovato', 'studio-immens-quick-order' ),
		'not_found_in_trash'         => __( 'Nessun punto nel cestino', 'studio-immens-quick-order' ),
		'back_to_items'              => __( 'Torna ai punti', 'studio-immens-quick-order' ),
		'separate_items_with_commas' => __( 'Separa i punti con la virgola', 'studio-immens-quick-order' ),
		'add_or_remove_items'        => __( 'Aggiungi o rimuovi punti', 'studio-immens-quick-order' ),
		'choose_from_most_used'      => __( 'Scegli tra i punti più usati', 'studio-immens-quick-order' ),
	);

	register_taxonomy(
		SIQO_STATION_TAXONOMY,
		array( 'product', 'product_variation', 'siqo_flash_product' ),
		array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
			'show_admin_column' => true,
		)
	);
}

add_action( 'init', 'siqo_station_ensure_page', 20 );
function siqo_station_ensure_page() {
	$slug    = SIQO_STATION_PAGE_SLUG;
	$content = '<!-- wp:shortcode -->[SIQO_station_orders_section]<!-- /wp:shortcode -->';
	$page    = get_page_by_path( $slug );

	if ( ! $page ) {
		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Ordini Reparti', 'studio-immens-quick-order' ),
				'post_name'      => $slug,
				'post_content'   => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( is_wp_error( $page_id ) ) {
			return;
		}

		update_post_meta( $page_id, '_siqo_station_page_version', SIQO_STATION_VERSION );
		if ( function_exists( 'SIQO_update_meta' ) ) {
			SIQO_update_meta( 'page_id_' . $slug, $page_id, 'page_id' );
		}
		return;
	}

	if ( get_post_meta( $page->ID, '_siqo_station_page_version', true ) !== SIQO_STATION_VERSION ) {
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => $content,
			)
		);
		update_post_meta( $page->ID, '_siqo_station_page_version', SIQO_STATION_VERSION );
	}

	if ( function_exists( 'SIQO_get_meta' ) && function_exists( 'SIQO_update_meta' ) ) {
		if ( (int) SIQO_get_meta( 'page_id_' . $slug ) !== (int) $page->ID ) {
			SIQO_update_meta( 'page_id_' . $slug, $page->ID, 'page_id' );
		}
	}
}

function siqo_station_user_can_access() {
	if ( function_exists( 'SIQO_access_autorization' ) && SIQO_access_autorization() ) {
		return true;
	}

	return current_user_can( 'manage_woocommerce' );
}

add_shortcode( 'SIQO_station_orders_section', 'siqo_station_orders_shortcode' );
function siqo_station_orders_shortcode() {
	siqo_station_enqueue_assets();
	return siqo_station_orders_render();
}

function siqo_station_enqueue_assets() {
	wp_enqueue_style(
		'siqo-station-orders',
		plugins_url( 'css/siqo-station-orders.css', __FILE__ ),
		array(),
		SIQO_STATION_VERSION
	);

	wp_enqueue_script(
		'siqo-station-orders',
		plugins_url( 'js/siqo-station-orders.js', __FILE__ ),
		array(),
		SIQO_STATION_VERSION,
		true
	);
}

function siqo_station_orders_render( $embedded = false ) {
	if ( ! siqo_station_user_can_access() ) {
		return '<div class="siqo-station-denied">' . esc_html__( 'Non hai i permessi per visualizzare gli ordini dei reparti.', 'studio-immens-quick-order' ) . '</div>';
	}

	$station = '__all';
	if ( isset( $_GET['station'] ) ) {
		$requested = sanitize_key( wp_unslash( $_GET['station'] ) );
		if ( siqo_station_is_valid_station( $requested ) ) {
			$station = $requested;
		}
	}

	$data   = siqo_station_get_orders_data();
	$counts = siqo_station_get_counts( $data );

	ob_start();
	if ( ! $embedded ) {
		echo '<div id="siqo-app" class="siqo-app siqo-app-staff siqo-station-app" data-theme="dark">';
		SIQO_Management::render_bar( 'staff' );
		echo '<main class="siqo-main">';
	}
	?>
	<div id="siqo-station-orders"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'siqo_station_orders' ) ); ?>"
		data-active-station="<?php echo esc_attr( $station ); ?>"
		data-poll-interval="30000">
		<header class="siqo-station-head">
			<div>
				<p class="siqo-station-eyebrow"><?php esc_html_e( 'Punti di preparazione', 'studio-immens-quick-order' ); ?></p>
				<h1 class="siqo-station-title"><?php esc_html_e( 'Ordini Reparti', 'studio-immens-quick-order' ); ?></h1>
				<p class="siqo-station-subtitle"><?php esc_html_e( 'Ordini delle ultime 8 ore, aggiornati automaticamente.', 'studio-immens-quick-order' ); ?></p>
			</div>
			<button type="button" class="siqo-station-refresh" data-station-refresh>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Aggiorna', 'studio-immens-quick-order' ); ?>
			</button>
		</header>

		<?php echo siqo_table_session_render_bar(); ?>

		<nav class="siqo-station-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filtra per punto di preparazione', 'studio-immens-quick-order' ); ?>">
			<?php echo siqo_station_render_tabs( $station, $counts ); ?>
		</nav>

		<div id="siqo-station-list" class="siqo-station-list" aria-live="polite" aria-busy="false">
			<?php echo siqo_station_render_list( $data, $station ); ?>
		</div>
	</div>
	<?php
	if ( ! $embedded ) {
		echo '</main>';
		SIQO_Management::render_shell_end();
	}
	return ob_get_clean();
}

add_action( 'wp_ajax_SIQO_station_orders', 'siqo_station_ajax_orders' );
function siqo_station_ajax_orders() {
	if ( ! check_ajax_referer( 'siqo_station_orders', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'studio-immens-quick-order' ) ), 403 );
	}

	if ( ! siqo_station_user_can_access() ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	$station = isset( $_POST['station'] ) ? sanitize_key( wp_unslash( $_POST['station'] ) ) : '__all';
	if ( ! siqo_station_is_valid_station( $station ) ) {
		wp_send_json_error( array( 'message' => __( 'Punto di preparazione non valido.', 'studio-immens-quick-order' ) ), 400 );
	}

	$data   = siqo_station_get_orders_data();
	$counts = siqo_station_get_counts( $data );

	wp_send_json_success(
		array(
			'html'   => siqo_station_render_list( $data, $station ),
			'counts' => $counts,
			'station' => $station,
		)
	);
}

add_action( 'wp_ajax_SIQO_station_update_status', 'siqo_station_ajax_update_status' );
function siqo_station_ajax_update_status() {
	if ( ! check_ajax_referer( 'siqo_station_orders', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Sessione scaduta. Ricarica la pagina.', 'studio-immens-quick-order' ) ), 403 );
	}

	if ( ! siqo_station_user_can_access() ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$station  = isset( $_POST['station'] ) ? sanitize_key( wp_unslash( $_POST['station'] ) ) : '';
	$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

	if ( ! $order_id || ! siqo_station_is_valid_station( $station ) || in_array( $station, array( '__all', '__none' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 400 );
	}

	if ( ! in_array( $status, array( 'pending', 'preparing', 'ready' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Stato non valido.', 'studio-immens-quick-order' ) ), 400 );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Ordine non trovato.', 'studio-immens-quick-order' ) ), 404 );
	}

	if ( ! siqo_station_order_has_station( $order, $station ) ) {
		wp_send_json_error( array( 'message' => __( 'Questo ordine non contiene prodotti del punto selezionato.', 'studio-immens-quick-order' ) ), 400 );
	}

	$status_map = $order->get_meta( SIQO_STATION_META_KEY );
	if ( ! is_array( $status_map ) ) {
		$status_map = array();
	}
	$status_map[ $station ] = $status;

	$order->update_meta_data( SIQO_STATION_META_KEY, $status_map );
	$order->save();

	wp_send_json_success(
		array(
			'order_id' => $order_id,
			'station'  => $station,
			'status'   => $status,
			'label'    => siqo_station_status_label( $status ),
		)
	);
}

add_action( 'wp_ajax_siqo_order_approve', 'siqo_order_approval_ajax' );
add_action( 'wp_ajax_siqo_order_reject', 'siqo_order_approval_ajax' );
function siqo_order_approval_ajax() {
	$hook      = preg_replace( '/^wp_ajax_(?:nopriv_)?/', '', (string) current_action() );
	$operation = '';
	if ( 'siqo_order_approve' === $hook ) {
		$operation = 'approve';
	} elseif ( 'siqo_order_reject' === $hook ) {
		$operation = 'reject';
	}

	if ( '' === $operation ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 400 );
	}

	if ( ! isset( $_POST['_siqo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_siqo_nonce'] ) ), 'siqo_order_approval' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}

	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';

	if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) || $decision !== $operation ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 400 );
	}

	$order = ( $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Ordine non trovato.', 'studio-immens-quick-order' ) ), 404 );
	}

	$siqo_sources = array( 'pending', 'on-hold', 'failed' );
	if ( 'pending' !== (string) $order->get_meta( '_siqo_approval' ) || ! in_array( $order->get_status(), $siqo_sources, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Ordine già gestito.', 'studio-immens-quick-order' ) ), 409 );
	}

	global $wpdb;

	$siqo_lock = 'siqo_approval_lock_' . $order_id;
	if ( ! add_option( $siqo_lock, time(), '', false ) ) {
		$siqo_locked_at = (int) get_option( $siqo_lock );
		if ( $siqo_locked_at && ( time() - $siqo_locked_at ) < 120 ) {
			wp_send_json_error( array( 'message' => __( 'Ordine già gestito.', 'studio-immens-quick-order' ) ), 409 );
		}
		update_option( $siqo_lock, time(), false );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || 'pending' !== (string) $order->get_meta( '_siqo_approval' ) || ! in_array( $order->get_status(), $siqo_sources, true ) ) {
		delete_option( $siqo_lock );
		wp_send_json_error( array( 'message' => __( 'Ordine già gestito.', 'studio-immens-quick-order' ) ), 409 );
	}

	$session_id = (int) $order->get_meta( '_siqo_session_id' );
	$session    = ( $session_id && class_exists( 'SIQO_Table_Sessions' ) ) ? SIQO_Table_Sessions::get( $session_id ) : null;

	if ( 'approve' === $operation ) {
		$order->update_meta_data( '_siqo_approval', 'approved' );
		$order->update_status( 'processing', __( 'Ordine confermato dall\'operatore.', 'studio-immens-quick-order' ) );
		$order->save();

		if ( $session ) {
			$reopened = $wpdb->query(
				$wpdb->prepare(
					"UPDATE " . SIQO_Table_Sessions::table_name() . " SET status = 'open' WHERE id = %d AND status = 'awaiting_approval'",
					$session_id
				)
			);
			if ( ! $reopened ) {
				error_log( sprintf( 'SIQO: approvazione ordine %d, sessione %d non in awaiting_approval (nessuna riga aggiornata).', $order_id, $session_id ) );
			}
		}

		do_action( 'siqo_order_approved', $order_id );
		delete_option( $siqo_lock );

		wp_send_json_success(
			array(
				'order_id' => $order_id,
				'approval' => 'approved',
			)
		);
	}

	$order->update_meta_data( '_siqo_approval', 'rejected' );
	$order->update_status( 'cancelled', __( 'Ordine rifiutato dall\'operatore.', 'studio-immens-quick-order' ) );
	$order->save();

	if ( $session ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE " . SIQO_Table_Sessions::table_name() . " SET status = 'open', orders_count = IF( orders_count > 0, orders_count - 1, 0 ), session_total = GREATEST( 0, session_total - %f ) WHERE id = %d AND status = 'awaiting_approval'",
				(float) $order->get_total(),
				$session_id
			)
		);
	}

	delete_option( $siqo_lock );

	wp_send_json_success(
		array(
			'order_id' => $order_id,
			'approval' => 'rejected',
		)
	);
}

function siqo_station_get_orders_data() {
	$args = array(
		'status'       => array( 'pending', 'processing', 'on-hold' ),
		'limit'        => 50,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'date_created' => '>' . ( time() - 8 * HOUR_IN_SECONDS ),
		'type'         => 'shop_order',
	);

	$args  = apply_filters( 'siqo_station_orders_query_args', $args );
	$query = wc_get_orders( $args );
	$data  = array();

	foreach ( $query as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$stations = siqo_station_get_item_terms( $item );
			$details  = siqo_station_get_item_details( $order, $item->get_product_id() );

			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => (int) $item->get_quantity(),
				'stations' => $stations,
				'details'  => $details,
			);
		}

		if ( empty( $items ) ) {
			continue;
		}

		$data[] = array(
			'order' => $order,
			'items' => $items,
		);
	}

	return $data;
}

function siqo_station_get_item_terms( $item ) {
	$terms      = array();
	$product_id = (int) $item->get_product_id();

	if ( $item->get_variation_id() ) {
		$terms = wp_get_post_terms( (int) $item->get_variation_id(), SIQO_STATION_TAXONOMY );
	}

	if ( empty( $terms ) && $product_id ) {
		$terms = wp_get_post_terms( $product_id, SIQO_STATION_TAXONOMY );
	}

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$result = array();
	foreach ( $terms as $term ) {
		$result[] = array(
			'slug' => $term->slug,
			'name' => $term->name,
		);
	}

	return $result;
}

function siqo_station_get_item_details( $order, $product_id ) {
	$details = array(
		'variant'     => array(),
		'temperature' => array(),
		'ingredients' => array(),
		'note'        => '',
	);

	$needle = 'prod-' . (int) $product_id . '_';

	foreach ( $order->get_meta_data() as $meta ) {
		$key   = (string) $meta->key;
		$value = is_scalar( $meta->value ) ? (string) $meta->value : wp_json_encode( $meta->value );
		$pos   = strpos( $key, $needle );

		if ( false === $pos ) {
			continue;
		}

		$rest = substr( $key, $pos + strlen( $needle ) );

		if ( 0 === strpos( $rest, 'variant-' ) ) {
			$details['variant'][] = str_replace( ' | ', ': ', $value );
		} elseif ( 0 === strpos( $rest, 'temperature-' ) ) {
			$details['temperature'][] = siqo_station_meta_after_pipe( $value );
		} elseif ( 0 === strpos( $rest, 'ingredient-' ) ) {
			$details['ingredients'][] = str_replace( ' | ', ': ', $value );
		} elseif ( 0 === strpos( $rest, 'note-' ) && '' === $details['note'] ) {
			$details['note'] = trim( $value );
		}
	}

	$details['variant']     = array_values( array_unique( array_filter( $details['variant'] ) ) );
	$details['temperature'] = array_values( array_unique( array_filter( $details['temperature'] ) ) );
	$details['ingredients'] = array_values( array_unique( array_filter( $details['ingredients'] ) ) );

	return $details;
}

function siqo_station_meta_after_pipe( $value ) {
	$pos = strpos( $value, '| ' );

	if ( false === $pos ) {
		return trim( $value );
	}

	return trim( substr( $value, $pos + 2 ) );
}

function siqo_station_get_counts( $data ) {
	$counts = array( '__all' => 0 );

	foreach ( $data as $entry ) {
		$counts['__all']++;
		$slugs   = array();
		$has_none = false;

		foreach ( $entry['items'] as $item ) {
			if ( empty( $item['stations'] ) ) {
				$has_none = true;
				continue;
			}

			foreach ( $item['stations'] as $station ) {
				$slugs[ $station['slug'] ] = true;
			}
		}

		foreach ( array_keys( $slugs ) as $slug ) {
			$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
		}

		if ( $has_none ) {
			$counts['__none'] = isset( $counts['__none'] ) ? $counts['__none'] + 1 : 1;
		}
	}

	return $counts;
}

function siqo_station_get_station_terms() {
	$terms = get_terms(
		array(
			'taxonomy'   => SIQO_STATION_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return $terms;
}

function siqo_station_is_valid_station( $station ) {
	if ( in_array( $station, array( '__all', '__none' ), true ) ) {
		return true;
	}

	$term = get_term_by( 'slug', $station, SIQO_STATION_TAXONOMY );
	return $term instanceof WP_Term;
}

function siqo_station_render_tabs( $active, $counts ) {
	$tabs = array(
		array(
			'slug'  => '__all',
			'name'  => __( 'Tutti', 'studio-immens-quick-order' ),
			'count' => isset( $counts['__all'] ) ? $counts['__all'] : 0,
		),
	);

	foreach ( siqo_station_get_station_terms() as $term ) {
		$tabs[] = array(
			'slug'  => $term->slug,
			'name'  => $term->name,
			'count' => isset( $counts[ $term->slug ] ) ? $counts[ $term->slug ] : 0,
		);
	}

	$tabs[] = array(
		'slug'  => '__none',
		'name'  => __( 'Senza punto', 'studio-immens-quick-order' ),
		'count' => isset( $counts['__none'] ) ? $counts['__none'] : 0,
	);

	$html = '';
	foreach ( $tabs as $tab ) {
		$is_active = $active === $tab['slug'];
		$html     .= sprintf(
			'<button type="button" role="tab" class="siqo-station-tab%1$s" data-station-target="%2$s" aria-controls="siqo-station-list" aria-selected="%3$s"><span>%4$s</span><span class="siqo-station-count" data-station-count="%2$s">%5$d</span></button>',
			$is_active ? ' is-active' : '',
			esc_attr( $tab['slug'] ),
			$is_active ? 'true' : 'false',
			esc_html( $tab['name'] ),
			(int) $tab['count']
		);
	}

	return $html;
}

function siqo_station_render_list( $data, $station ) {
	$html = '';

	foreach ( $data as $entry ) {
		$visible_items = siqo_station_filter_items( $entry['items'], $station );

		if ( empty( $visible_items ) ) {
			continue;
		}

		$html .= siqo_station_render_order_card( $entry['order'], $visible_items, $station );
	}

	if ( '' === $html ) {
		$html = '<div class="siqo-station-empty"><strong>' . esc_html__( 'Nessun ordine', 'studio-immens-quick-order' ) . '</strong><span>' . esc_html__( 'Non ci sono ordini da preparare per questo punto.', 'studio-immens-quick-order' ) . '</span></div>';
	}

	return $html;
}

function siqo_station_filter_items( $items, $station ) {
	$visible = array();

	foreach ( $items as $item ) {
		if ( '__all' === $station ) {
			$visible[] = $item;
			continue;
		}

		if ( '__none' === $station ) {
			if ( empty( $item['stations'] ) ) {
				$visible[] = $item;
			}
			continue;
		}

		foreach ( $item['stations'] as $item_station ) {
			if ( $item_station['slug'] === $station ) {
				$visible[] = $item;
				break;
			}
		}
	}

	return $visible;
}

function siqo_station_render_order_card( $order, $items, $station ) {
	$order_id  = $order->get_id();
	$table     = trim( (string) $order->get_meta( 'Table_cpt' ) . ' ' . (string) $order->get_meta( 'Table' ) );
	$created   = $order->get_date_created();
	$time      = $created ? $created->date_i18n( 'H:i' ) : '';
	$status    = 'pending';
	$action    = '';
	$is_filter = ! in_array( $station, array( '__all', '__none' ), true );

	if ( $is_filter ) {
		$status_map = $order->get_meta( SIQO_STATION_META_KEY );
		if ( is_array( $status_map ) && isset( $status_map[ $station ] ) ) {
			$status = $status_map[ $station ];
		}

		if ( ! in_array( $status, array( 'pending', 'preparing', 'ready' ), true ) ) {
			$status = 'pending';
		}

		if ( 'pending' === $status ) {
			$action = '<button type="button" class="siqo-station-action" data-station-action="preparing" data-order-id="' . esc_attr( $order_id ) . '" data-station="' . esc_attr( $station ) . '">' . esc_html__( 'In preparazione', 'studio-immens-quick-order' ) . '</button>';
		} elseif ( 'preparing' === $status ) {
			$action = '<button type="button" class="siqo-station-action" data-station-action="ready" data-order-id="' . esc_attr( $order_id ) . '" data-station="' . esc_attr( $station ) . '">' . esc_html__( 'Pronto', 'studio-immens-quick-order' ) . '</button>';
		} elseif ( 'ready' === $status ) {
			$action = '<button type="button" class="siqo-station-action siqo-station-action--reset" data-station-action="pending" data-order-id="' . esc_attr( $order_id ) . '" data-station="' . esc_attr( $station ) . '">' . esc_html__( 'Riapri', 'studio-immens-quick-order' ) . '</button>';
		}
	}

	ob_start();
	?>
	<article class="siqo-station-order siqo-station-order--<?php echo esc_attr( $status ); ?>">
		<header class="siqo-station-order-head">
			<div class="siqo-station-order-id">#<?php echo esc_html( $order_id ); ?></div>
			<div class="siqo-station-order-meta">
				<?php if ( $table ) : ?>
					<strong><?php echo esc_html( $table ); ?></strong>
				<?php endif; ?>
				<?php if ( $time ) : ?>
					<time><?php echo esc_html( $time ); ?></time>
				<?php endif; ?>
				<?php echo siqo_render_author_badge( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( $is_filter ) : ?>
				<span class="siqo-station-badge siqo-station-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( siqo_station_status_label( $status ) ); ?></span>
			<?php endif; ?>
		</header>

		<ul class="siqo-station-items">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$detail_parts = array();
				if ( ! empty( $item['details']['variant'] ) ) {
					$detail_parts[] = implode( ', ', $item['details']['variant'] );
				}
				if ( ! empty( $item['details']['temperature'] ) ) {
					$detail_parts[] = implode( ', ', $item['details']['temperature'] );
				}
				if ( ! empty( $item['details']['ingredients'] ) ) {
					$detail_parts[] = implode( ', ', $item['details']['ingredients'] );
				}
				if ( ! empty( $item['details']['note'] ) ) {
					$detail_parts[] = $item['details']['note'];
				}
				?>
				<li class="siqo-station-item">
					<span class="siqo-station-item-qty"><?php echo esc_html( $item['quantity'] ); ?>×</span>
					<span class="siqo-station-item-body">
						<span class="siqo-station-item-name"><?php echo esc_html( $item['name'] ); ?></span>
						<?php if ( ! empty( $detail_parts ) ) : ?>
							<span class="siqo-station-item-details"><?php echo esc_html( implode( ' · ', $detail_parts ) ); ?></span>
						<?php endif; ?>
						<?php if ( '__all' === $station && ! empty( $item['stations'] ) ) : ?>
							<span class="siqo-station-item-stations">
								<?php foreach ( $item['stations'] as $item_station ) : ?>
									<span class="siqo-station-chip"><?php echo esc_html( $item_station['name'] ); ?></span>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( 'pending' === $order->get_meta( '_siqo_approval' ) ) : ?>
			<div class="siqo-order-approval"
				data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'siqo_order_approval' ) ); ?>">
				<div class="siqo-order-approval-head">
					<strong class="siqo-order-approval-title"><?php esc_html_e( 'Da confermare', 'studio-immens-quick-order' ); ?></strong>
					<span class="siqo-order-approval-status" role="status" aria-live="polite"></span>
				</div>
				<div class="siqo-order-approval-actions">
					<button type="button" class="siqo-station-action siqo-approve" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Conferma', 'studio-immens-quick-order' ); ?></button>
					<button type="button" class="siqo-station-action siqo-station-action--reset siqo-reject" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Rifiuta', 'studio-immens-quick-order' ); ?></button>
				</div>
			</div>
		<?php endif; ?>

		<footer class="siqo-station-order-foot">
			<span class="siqo-station-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			<?php if ( $action ) : ?>
				<div class="siqo-station-actions"><?php echo $action; ?></div>
			<?php endif; ?>
		</footer>
	</article>
	<?php
	return ob_get_clean();
}

function siqo_station_order_has_station( $order, $station ) {
	foreach ( $order->get_items() as $item ) {
		foreach ( siqo_station_get_item_terms( $item ) as $item_station ) {
			if ( $item_station['slug'] === $station ) {
				return true;
			}
		}
	}

	return false;
}

function siqo_station_status_label( $status ) {
	switch ( $status ) {
		case 'preparing':
			return __( 'In preparazione', 'studio-immens-quick-order' );
		case 'ready':
			return __( 'Pronto', 'studio-immens-quick-order' );
		case 'pending':
		default:
			return __( 'In attesa', 'studio-immens-quick-order' );
	}
}
