<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIQO_STATION_VERSION', '1.0.0' );
define( 'SIQO_STATION_TAXONOMY', 'siqo_preparation_points' );
define( 'SIQO_STATION_META_KEY', '_siqo_station_status' );
define( 'SIQO_STATION_PAGE_SLUG', 'ordini-reparti' );

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
	if ( ! siqo_station_user_can_access() ) {
		return '<div class="siqo-station-denied">' . esc_html__( 'Non hai i permessi per visualizzare gli ordini dei reparti.', 'studio-immens-quick-order' ) . '</div>';
	}

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
	?>
	<div id="siqo-app" class="siqo-app siqo-app-staff siqo-station-app" data-theme="dark">
	<?php SIQO_Management::render_bar( 'staff' ); ?>
	<main class="siqo-main">
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

		<nav class="siqo-station-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filtra per punto di preparazione', 'studio-immens-quick-order' ); ?>">
			<?php echo siqo_station_render_tabs( $station, $counts ); ?>
		</nav>

		<div id="siqo-station-list" class="siqo-station-list" aria-live="polite" aria-busy="false">
			<?php echo siqo_station_render_list( $data, $station ); ?>
		</div>
	</div>
	</main>
	<?php SIQO_Management::render_shell_end(); ?>
	<?php
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
