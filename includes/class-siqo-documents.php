<?php
/**
 * Internal receipt and proforma document engine.
 *
 * @package    Studio_Immens_Quick_Order
 * @subpackage Studio_Immens_Quick_Order/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds, archives and prints Quick Order documents without external plugins.
 */
class SIQO_Documents {

	const DB_VERSION = '1.1.0';

	/**
	 * Receipts table name.
	 *
	 * @var string
	 */
	private static $table = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		global $wpdb;

		self::$table = $wpdb->prefix . 'siqo_receipts';

		if ( get_option( 'siqo_documents_db_version' ) !== self::DB_VERSION ) {
			self::create_table();
		}

		add_action( 'wp_ajax_siqo_print_document', array( __CLASS__, 'ajax_print_document' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_script' ), 20 );
		add_action( 'siqo_table_paid', array( __CLASS__, 'on_table_paid' ), 10, 3 );
	}

	/**
	 * Create or update the receipts table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sql     = 'CREATE TABLE ' . self::$table . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			table_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_ids longtext NULL,
			receipt_number varchar(60) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'receipt',
			status varchar(20) NOT NULL DEFAULT 'issued',
			total decimal(12,2) NOT NULL DEFAULT 0,
			html longtext NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY table_id (table_id),
			UNIQUE KEY receipt_number (receipt_number)
		) $charset;";

		dbDelta( $sql );
		update_option( 'siqo_documents_db_version', self::DB_VERSION );
	}

	/**
	 * Add document configuration to the public script.
	 *
	 * @return void
	 */
	public static function localize_script() {
		wp_localize_script(
			'studio-immens-quick-order',
			'siqo_documents_vars',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'siqo_print_document' ),
			)
		);
	}

	/**
	 * AJAX endpoint used by the operator interface.
	 *
	 * @return void
	 */
	public static function ajax_print_document() {
		check_ajax_referer( 'siqo_print_document', 'nonce' );

		if ( ! self::user_can_print() ) {
			wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce non è attivo.', 'studio-immens-quick-order' ) ), 400 );
		}

		$type     = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'preconto';
		$type     = in_array( $type, array( 'preconto', 'receipt' ), true ) ? $type : 'preconto';
		$table_id = isset( $_POST['table_id'] ) ? absint( $_POST['table_id'] ) : 0;
		$table    = isset( $_POST['table_name'] ) ? sanitize_text_field( wp_unslash( $_POST['table_name'] ) ) : '';
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_ids = self::sanitize_order_ids( isset( $_POST['order_ids'] ) ? wp_unslash( $_POST['order_ids'] ) : array() );

		if ( $order_id ) {
			$order_ids[] = $order_id;
		}

		if ( empty( $order_ids ) && $table_id ) {
			$order_ids = self::get_table_order_ids( $table_id );
		}

		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );

		if ( empty( $order_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Nessun ordine disponibile per questo documento.', 'studio-immens-quick-order' ) ), 400 );
		}

		$data = self::build_data( $type, $order_ids, $table_id, $table );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		if ( 'receipt' === $type && 1 === count( $order_ids ) ) {
			$existing = self::get_existing_receipt( $order_ids[0] );
			if ( $existing && ! empty( $existing->html ) ) {
				wp_send_json_success(
					array(
						'html'    => $existing->html,
						'number'  => $existing->receipt_number,
						'queued'  => false,
						'reprint' => true,
					)
				);
			}
		}

		$html = self::build_html( $data );

		if ( 'receipt' === $type ) {
			self::save_receipt( $data, $html );
		}

		wp_send_json_success(
			array(
				'html'   => $html,
				'number' => $data['number'],
				'queued' => false,
			)
		);
	}

	/**
	 * Archive a paid table and optionally send it to the default printer.
	 *
	 * @param int    $order_id   Paid order ID.
	 * @param int    $table_id   Table post ID.
	 * @param string $table_name Table label.
	 * @return void
	 */
	public static function on_table_paid( $order_id, $table_id, $table_name ) {
		$order_id = absint( $order_id );
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_siqo_receipt_id' ) ) {
			return;
		}

		$data = self::build_data( 'receipt', array( $order_id ), absint( $table_id ), $table_name );
		if ( is_wp_error( $data ) ) {
			return;
		}

		$html = self::build_html( $data );
		self::save_receipt( $data, $html );

		if ( 'yes' !== get_option( 'siqo_receipt_auto_print', 'no' ) || ! self::user_can_print() ) {
			return;
		}

		$printer_id = absint( get_option( 'siqo_receipt_printer_id', 0 ) );
		if ( ! $printer_id || ! class_exists( 'SIQO_Printers' ) || ! SIQO_Management::license_active() ) {
			return;
		}

		$job_id = SIQO_Printers::queue_escpos( $printer_id, self::build_text( $data ), $order_id, 'receipt' );
		if ( $job_id && method_exists( 'SIQO_Printers', 'process_job' ) ) {
			SIQO_Printers::process_job( $job_id );
		}
	}

	/**
	 * Build normalized document data from WooCommerce orders.
	 *
	 * @param string $type      Document type.
	 * @param array  $order_ids Order IDs.
	 * @param int    $table_id  Table ID.
	 * @param string $table     Table label.
	 * @return array|WP_Error
	 */
	private static function build_data( $type, $order_ids, $table_id, $table ) {
		$orders = array();
		$first  = null;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$orders[] = $order;
			if ( ! $first ) {
				$first = $order;
			}
		}

		if ( empty( $orders ) ) {
			return new WP_Error( 'siqo_document_empty', __( 'Nessun ordine valido trovato.', 'studio-immens-quick-order' ) );
		}

		$table = trim( $table );
		if ( '' === $table && $first ) {
			$table = trim( (string) $first->get_meta( 'Table_cpt' ) . ' ' . (string) $first->get_meta( 'Table' ) );
		}
		if ( '' === $table && $table_id ) {
			$table = get_the_title( $table_id );
		}

		$items          = array();
		$subtotal       = 0.0;
		$items_total    = 0.0;
		$fees           = array();
		$fees_total     = 0.0;
		$shipping       = 0.0;
		$total          = 0.0;
		$discount_total = 0.0;

		foreach ( $orders as $order ) {
			$total    += (float) $order->get_total();
			$shipping += (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$qty        = (int) $item->get_quantity();
				$line_total = (float) $item->get_total() + (float) $item->get_total_tax();
				$meta       = trim( wp_strip_all_tags( wc_display_item_meta( $item, array( 'echo' => false, 'separator' => ', ', 'before' => '', 'after' => '' ) ) ) );

				$subtotal += (float) $item->get_subtotal() + (float) $item->get_subtotal_tax();
				$items[]   = array(
					'name'  => $item->get_name(),
					'qty'   => $qty,
					'total' => $line_total,
					'meta'  => $meta,
				);
				$items_total += $line_total;
			}

			foreach ( $order->get_items( 'fee' ) as $fee ) {
				$fee_total = (float) $fee->get_total() + (float) $fee->get_total_tax();
				$fees[]    = array(
					'name'  => $fee->get_name(),
					'total' => $fee_total,
				);
				$fees_total += $fee_total;
			}
		}

		$discount_total = max( 0, $subtotal - $items_total );

		$created    = $first->get_date_created();
		$order_id_list = array();
		foreach ( $orders as $document_order ) {
			$order_id_list[] = $document_order->get_id();
		}

		$number = 'preconto' === $type
			? 'PRE-' . ( $table_id ? $table_id . '-' : '' ) . current_time( 'Ymd-His' )
			: 'QO-' . ( 1 === count( $orders ) ? $first->get_order_number() : ( $table_id ? $table_id . '-' : '' ) . current_time( 'Ymd-His' ) );

		return array(
			'type'       => $type,
			'number'     => $number,
			'order_ids'  => $order_id_list,
			'orders'     => $orders,
			'table_id'   => absint( $table_id ),
			'table'      => $table,
			'items'      => $items,
			'fees'       => $fees,
			'subtotal'   => $subtotal,
			'discount'   => $discount_total,
			'fees_total' => $fees_total,
			'shipping'   => $shipping,
			'total'      => $total,
			'created_at' => $created ? $created->date_i18n( 'd/m/Y H:i' ) : current_time( 'd/m/Y H:i' ),
			'operator'   => wp_get_current_user()->display_name,
		);
	}

	/**
	 * Render the printable HTML document.
	 *
	 * @param array $data Document data.
	 * @return string
	 */
	private static function build_html( $data ) {
		$brand   = SIQO_Management::get_brand();
		$paper   = '58' === (string) get_option( 'siqo_receipt_paper_width', '80' ) ? '58' : '80';
		$width   = '58' === $paper ? '52mm' : '72mm';
		$show    = 'yes' === get_option( 'siqo_receipt_show_logo', 'yes' );
		$header  = get_option( 'siqo_receipt_header', '' );
		$footer  = get_option( 'siqo_receipt_footer', __( 'Grazie e a presto.', 'studio-immens-quick-order' ) );
		$is_pre  = 'preconto' === $data['type'];
		$label   = $is_pre ? __( 'PRECONTO', 'studio-immens-quick-order' ) : __( 'RICEVUTA', 'studio-immens-quick-order' );
		$note    = $is_pre
			? __( 'Documento non fiscale. Il conto può variare.', 'studio-immens-quick-order' )
			: __( 'Documento non fiscale. Non sostituisce scontrino o fattura.', 'studio-immens-quick-order' );

		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $data['number'] ); ?></title>
<style>
	@page { size: <?php echo esc_attr( $paper ); ?>mm auto; margin: 3mm; }
	* { box-sizing: border-box; }
	body { margin: 0; padding: 0; background: #fff; color: #111; font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.35; }
	.doc { width: <?php echo esc_attr( $width ); ?>; margin: 0 auto; padding: 2mm 0; }
	.head { text-align: center; padding-bottom: 8px; border-bottom: 1px dashed #555; }
	.logo { display: block; max-width: 60%; max-height: 18mm; margin: 0 auto 6px; object-fit: contain; }
	.brand { font-size: 16px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
	.kicker { margin-top: 2px; font-size: 10px; font-weight: 700; letter-spacing: .12em; }
	.head-text { margin-top: 4px; white-space: pre-line; }
	.meta { padding: 7px 0; border-bottom: 1px dashed #555; }
	.meta-row, .total-row { display: flex; justify-content: space-between; gap: 8px; }
	.meta-row span:first-child { color: #444; }
	.doc-author { font-weight: 700; overflow-wrap: anywhere; }
	.items { padding: 6px 0; border-bottom: 1px dashed #555; }
	.item { display: flex; justify-content: space-between; gap: 8px; padding: 3px 0; }
	.item-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
	.item-meta { color: #444; font-size: 9px; }
	.item-price { white-space: nowrap; text-align: right; }
	.totals { padding: 7px 0; border-bottom: 1px dashed #555; }
	.total-row { padding: 1px 0; }
	.total-row.is-discount { color: #444; }
	.total-row.is-total { margin-top: 5px; padding-top: 5px; border-top: 1px solid #111; font-size: 15px; font-weight: 700; }
	.footer { padding-top: 7px; text-align: center; white-space: pre-line; }
	.note { margin-top: 7px; color: #444; font-size: 9px; text-align: center; }
	@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="doc">
	<div class="head">
		<?php if ( $show && ! empty( $brand['logo'] ) ) : ?>
			<img class="logo" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="">
		<?php endif; ?>
		<div class="brand"><?php echo esc_html( $brand['name'] ); ?></div>
		<div class="kicker"><?php echo esc_html( $label ); ?></div>
		<?php if ( $header ) : ?><div class="head-text"><?php echo esc_html( $header ); ?></div><?php endif; ?>
	</div>

	<div class="meta">
		<div class="meta-row"><span><?php esc_html_e( 'Documento', 'studio-immens-quick-order' ); ?></span><strong><?php echo esc_html( $data['number'] ); ?></strong></div>
		<?php
		$document_author = ! empty( $data['orders'][0] ) ? html_entity_decode( wp_strip_all_tags( siqo_render_author_badge( $data['orders'][0] ) ), ENT_QUOTES, 'UTF-8' ) : '';
		if ( '' !== $document_author ) :
			?>
			<div class="doc-author"><?php echo esc_html( $document_author ); ?></div>
		<?php endif; ?>
		<div class="meta-row"><span><?php esc_html_e( 'Data', 'studio-immens-quick-order' ); ?></span><span><?php echo esc_html( $data['created_at'] ); ?></span></div>
		<?php if ( $data['table'] ) : ?><div class="meta-row"><span><?php esc_html_e( 'Tavolo', 'studio-immens-quick-order' ); ?></span><strong><?php echo esc_html( $data['table'] ); ?></strong></div><?php endif; ?>
		<?php if ( $data['operator'] ) : ?><div class="meta-row"><span><?php esc_html_e( 'Operatore', 'studio-immens-quick-order' ); ?></span><span><?php echo esc_html( $data['operator'] ); ?></span></div><?php endif; ?>
		<div class="meta-row"><span><?php esc_html_e( 'Ordini', 'studio-immens-quick-order' ); ?></span><span><?php echo esc_html( implode( ', #', $data['order_ids'] ) ? '#' . implode( ', #', $data['order_ids'] ) : '—' ); ?></span></div>
	</div>

	<div class="items">
		<?php foreach ( $data['items'] as $item ) : ?>
			<div class="item">
				<div class="item-name">
					<?php echo esc_html( $item['qty'] . ' × ' . $item['name'] ); ?>
					<?php if ( $item['meta'] ) : ?><div class="item-meta"><?php echo esc_html( $item['meta'] ); ?></div><?php endif; ?>
				</div>
				<div class="item-price"><?php echo wp_kses_post( wc_price( $item['total'] ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="totals">
		<div class="total-row"><span><?php esc_html_e( 'Subtotale', 'studio-immens-quick-order' ); ?></span><span><?php echo wp_kses_post( wc_price( $data['subtotal'] ) ); ?></span></div>
		<?php if ( $data['discount'] > 0 ) : ?>
			<div class="total-row is-discount"><span><?php esc_html_e( 'Sconti', 'studio-immens-quick-order' ); ?></span><span>-<?php echo wp_kses_post( wc_price( $data['discount'] ) ); ?></span></div>
		<?php endif; ?>
		<?php foreach ( $data['fees'] as $fee ) : ?>
			<div class="total-row"><span><?php echo esc_html( $fee['name'] ); ?></span><span><?php echo wp_kses_post( wc_price( $fee['total'] ) ); ?></span></div>
		<?php endforeach; ?>
		<?php if ( $data['shipping'] > 0 ) : ?>
			<div class="total-row"><span><?php esc_html_e( 'Spedizione', 'studio-immens-quick-order' ); ?></span><span><?php echo wp_kses_post( wc_price( $data['shipping'] ) ); ?></span></div>
		<?php endif; ?>
		<div class="total-row is-total"><span><?php esc_html_e( 'TOTALE', 'studio-immens-quick-order' ); ?></span><span><?php echo wp_kses_post( wc_price( $data['total'] ) ); ?></span></div>
	</div>

	<div class="footer">
		<?php echo esc_html( $footer ); ?>
		<div class="note"><?php echo esc_html( $note ); ?></div>
	</div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render plain text for ESC/POS printers.
	 *
	 * @param array $data Document data.
	 * @return string
	 */
	private static function build_text( $data ) {
		$width  = '58' === (string) get_option( 'siqo_receipt_paper_width', '80' ) ? 32 : 42;
		$footer = get_option( 'siqo_receipt_footer', __( 'Grazie e a presto.', 'studio-immens-quick-order' ) );
		$is_pre = 'preconto' === $data['type'];
		$label  = $is_pre ? __( 'PRECONTO', 'studio-immens-quick-order' ) : __( 'RICEVUTA', 'studio-immens-quick-order' );
		$brand  = SIQO_Management::get_brand();
		$lines  = array();

		$lines[] = self::center( $brand['name'], $width );
		$lines[] = self::center( $label, $width );

		$header = get_option( 'siqo_receipt_header', '' );
		if ( $header ) {
			$lines[] = self::center( $header, $width );
		}

		$lines[] = str_repeat( '-', $width );
		$lines[] = self::line( __( 'Documento', 'studio-immens-quick-order' ), $data['number'], $width );
		$author  = ! empty( $data['orders'][0] ) ? html_entity_decode( trim( wp_strip_all_tags( siqo_render_author_badge( $data['orders'][0] ) ) ), ENT_QUOTES, 'UTF-8' ) : '';
		if ( '' !== $author ) {
			foreach ( explode( "\n", wordwrap( self::ascii( $author ), $width, "\n", true ) ) as $author_line ) {
				$lines[] = $author_line;
			}
		}
		$lines[] = self::line( __( 'Data', 'studio-immens-quick-order' ), $data['created_at'], $width );
		if ( $data['table'] ) {
			$lines[] = self::line( __( 'Tavolo', 'studio-immens-quick-order' ), $data['table'], $width );
		}
		$lines[] = str_repeat( '-', $width );

		foreach ( $data['items'] as $item ) {
			$name  = self::ascii( $item['name'] );
			$qty   = (int) $item['qty'];
			$left  = $qty . ' x ' . $name;
			$money = self::money( $item['total'] );
			$rows  = explode( "\n", wordwrap( $left, $width - 10, "\n", true ) );
			foreach ( $rows as $index => $row ) {
				$lines[] = self::line( $row, 0 === $index ? $money : '', $width );
			}
			if ( $item['meta'] ) {
				$lines[] = '  ' . self::ascii( $item['meta'] );
			}
		}

		$lines[] = str_repeat( '-', $width );
		$lines[] = self::line( __( 'Subtotale', 'studio-immens-quick-order' ), self::money( $data['subtotal'] ), $width );
		if ( $data['discount'] > 0 ) {
			$lines[] = self::line( __( 'Sconti', 'studio-immens-quick-order' ), '-' . self::money( $data['discount'] ), $width );
		}
		foreach ( $data['fees'] as $fee ) {
			$lines[] = self::line( self::ascii( $fee['name'] ), self::money( $fee['total'] ), $width );
		}
		if ( $data['shipping'] > 0 ) {
			$lines[] = self::line( __( 'Spedizione', 'studio-immens-quick-order' ), self::money( $data['shipping'] ), $width );
		}
		$lines[] = str_repeat( '-', $width );
		$lines[] = self::line( 'TOTALE', self::money( $data['total'] ), $width );
		$lines[] = str_repeat( '-', $width );
		$lines[] = self::center( $footer, $width );
		$lines[] = self::center( $is_pre ? __( 'Documento non fiscale. Il conto puo variare.', 'studio-immens-quick-order' ) : __( 'Documento non fiscale.', 'studio-immens-quick-order' ), $width );

		return implode( "\n", $lines );
	}

	/**
	 * Persist a document in the archive.
	 *
	 * @param array  $data Document data.
	 * @param string $html Rendered HTML.
	 * @return int
	 */
	private static function save_receipt( $data, $html ) {
		global $wpdb;

		if ( empty( $data['order_ids'] ) ) {
			return 0;
		}

		$order_ids = array_map( 'absint', $data['order_ids'] );
		$inserted  = $wpdb->insert(
			self::$table,
			array(
				'order_id'       => (int) $order_ids[0],
				'table_id'       => absint( $data['table_id'] ),
				'order_ids'      => wp_json_encode( $order_ids ),
				'receipt_number' => sanitize_text_field( $data['number'] ),
				'type'           => sanitize_key( $data['type'] ),
				'status'         => 'issued',
				'total'          => (float) $data['total'],
				'html'           => $html,
				'created_by'     => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;
		if ( $id ) {
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->update_meta_data( '_siqo_receipt_id', $id );
					$order->save();
				}
			}
		}

		return $id;
	}

	/**
	 * Retrieve an archived receipt for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	private static function get_existing_receipt( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$id = absint( $order->get_meta( '_siqo_receipt_id' ) );
		if ( $id ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . self::$table . ' WHERE id = %d',
					$id
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::$table . ' WHERE order_id = %d OR ( JSON_VALID(order_ids) AND JSON_CONTAINS(order_ids, %s) ) ORDER BY id DESC LIMIT 1',
				absint( $order_id ),
				(string) absint( $order_id )
			)
		);

		if ( $row ) {
			$order->update_meta_data( '_siqo_receipt_id', (int) $row->id );
			$order->save();
		}

		return $row;
	}

	/**
	 * Extract table order IDs from the legacy table store.
	 *
	 * @param int $table_id Table post ID.
	 * @return array
	 */
	private static function get_table_order_ids( $table_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT orders FROM ' . $wpdb->prefix . 'siqo_tables WHERE table_id = %d AND status != 10 ORDER BY id DESC LIMIT 1',
				absint( $table_id )
			)
		);

		if ( ! $row || empty( $row->orders ) ) {
			return array();
		}

		$decoded = json_decode( (string) $row->orders, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( array_map( 'absint', $decoded ) ) );
		}

		$ids = preg_split( '/[,\s]+/', (string) $row->orders );
		return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	}

	/**
	 * Normalize order IDs from an array or JSON string.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private static function sanitize_order_ids( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
		}

		return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
	}

	/**
	 * Current user can print documents.
	 *
	 * @return bool
	 */
	private static function user_can_print() {
		if ( class_exists( 'SIQO_Management' ) && method_exists( 'SIQO_Management', 'can' ) ) {
			return SIQO_Management::can( 'print_receipts' );
		}

		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Format an amount for ESC/POS.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		$formatted = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		return self::ascii( str_replace( '€', 'EUR', $formatted ) );
	}

	/**
	 * Convert text to a printer-safe string.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function ascii( $text ) {
		$text = str_replace(
			array( '—', '–', '‘', '’', '“', '”', '…' ),
			array( '-', '-', "'", "'", '"', '"', '...' ),
			(string) $text
		);
		$text = remove_accents( $text );
		$text = str_replace( array( "\r", "\t", '€' ), array( '', ' ', 'EUR' ), $text );
		return trim( substr( $text, 0, 4096 ) );
	}

	/**
	 * Center a line.
	 *
	 * @param string $text  Text.
	 * @param int    $width Width.
	 * @return string
	 */
	private static function center( $text, $width ) {
		$text = self::ascii( $text );
		return str_pad( $text, min( $width, strlen( $text ) ), ' ', STR_PAD_BOTH );
	}

	/**
	 * Format a two-column line.
	 *
	 * @param string $left  Left text.
	 * @param string $right Right text.
	 * @param int    $width Width.
	 * @return string
	 */
	private static function line( $left, $right, $width ) {
		$left  = self::ascii( $left );
		$right = self::ascii( $right );

		if ( '' === $right ) {
			return $left;
		}

		$space = $width - strlen( $left ) - strlen( $right );
		if ( $space < 1 ) {
			return $left . "\n" . str_pad( $right, $width, ' ', STR_PAD_LEFT );
		}

		return $left . str_repeat( ' ', $space ) . $right;
	}
}

if ( ! function_exists( 'siqo_render_author_badge' ) ) {
	/**
	 * Render the author badge for an order.
	 *
	 * Operator orders show the operator avatar and name; customer orders show
	 * the table label instead. Orders without source metadata (legacy) fall
	 * back to the customer label.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	function siqo_render_author_badge( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return '';
		}

		$source  = (string) $order->get_meta( '_siqo_order_source' );
		$user_id = (int) $order->get_meta( '_siqo_inserted_by' );
		$parts   = array();

		$table_cpt  = trim( (string) $order->get_meta( 'Table_cpt' ) );
		$table_meta = trim( (string) $order->get_meta( 'Table' ) );

		if ( '' !== $table_cpt ) {
			$parts[] = $table_cpt;
		}
		if ( '' !== $table_meta && $table_meta !== $table_cpt ) {
			$parts[] = $table_meta;
		}

		$table = implode( ' ', $parts );

		if ( 'operator' === $source && $user_id ) {
			$user   = get_userdata( $user_id );
			$name   = $user ? $user->display_name : __( 'Operatore', 'studio-immens-quick-order' );
			$avatar = get_avatar_url( $user_id, array( 'size' => 56 ) );
			$label  = sprintf( __( 'Operatore: %s', 'studio-immens-quick-order' ), $name );
		} else {
			$avatar = '';
			$label  = $table
				? sprintf( __( 'Cliente — %s', 'studio-immens-quick-order' ), $table )
				: __( 'Cliente', 'studio-immens-quick-order' );
		}

		$html = '<span class="siqo-author' . ( $avatar ? ' has-avatar' : '' ) . '">';
		if ( $avatar ) {
			$html .= '<img src="' . esc_url( $avatar ) . '" alt="" width="28" height="28">';
		}
		$html .= '<span class="siqo-author-name">' . esc_html( $label ) . '</span>';
		$html .= '</span>';

		return $html;
	}
}
