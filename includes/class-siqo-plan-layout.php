<?php
/**
 * Piani, layout e zone della disposizione.
 *
 * @package Studio_Immens_Quick_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SIQO_Plan_Layout {

	const FLOOR_TAX      = 'siqo_floors';
	const ZONE_TAX       = 'siqo_zones';
	const VIRTUAL_OPTION = 'siqo_plan_virtual_layout';
	const DEFAULT_W      = 1200;
	const DEFAULT_H      = 800;
	const MAX_DIM        = 4000;
	const MIN_W          = 80;
	const MIN_H          = 60;
	const MAX_SEATS      = 20;
	const STEP           = 10;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 9 );
		add_action( 'init', array( __CLASS__, 'take_over_layout_endpoint' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function is_app_page() {
		if ( is_page( 'manage-tables' ) ) {
			return true;
		}
		$post = get_post();
		return $post && false !== strpos( (string) $post->post_content, 'SIQO_manage_tables_section' );
	}

	public static function enqueue_assets() {
		if ( ! self::is_app_page() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'siqo-plan-editor',
			plugins_url( 'public/js/siqo-plan-editor.js', dirname( __FILE__ ) ),
			array(),
			STUDIO_IMMENS_QUICK_ORDER_VERSION,
			true
		);
		wp_localize_script(
			'siqo-plan-editor',
			'SIQO_plan_editor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	public static function register_taxonomy() {
		register_taxonomy(
			self::FLOOR_TAX,
			array( 'siqo_tables' ),
			array(
				'labels'       => array(
					'name'          => __( 'Piani', 'studio-immens-quick-order' ),
					'singular_name' => __( 'Piano', 'studio-immens-quick-order' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'hierarchical' => false,
				'rewrite'      => false,
			)
		);
	}

	public static function take_over_layout_endpoint() {
		remove_all_actions( 'wp_ajax_siqo_save_table_layout' );
		add_action( 'wp_ajax_siqo_save_table_layout', array( __CLASS__, 'ajax_save_layout' ) );
		add_action( 'wp_ajax_siqo_floor_create', array( __CLASS__, 'ajax_floor_create' ) );
		add_action( 'wp_ajax_siqo_floor_update', array( __CLASS__, 'ajax_floor_update' ) );
		add_action( 'wp_ajax_siqo_floor_delete', array( __CLASS__, 'ajax_floor_delete' ) );
		add_action( 'wp_ajax_siqo_zone_create', array( __CLASS__, 'ajax_zone_create' ) );
		add_action( 'wp_ajax_siqo_zone_update', array( __CLASS__, 'ajax_zone_update' ) );
		add_action( 'wp_ajax_siqo_zone_delete', array( __CLASS__, 'ajax_zone_delete' ) );
		add_action( 'wp_ajax_siqo_floors', array( __CLASS__, 'respond_floors' ) );
		add_action( 'wp_ajax_siqo_zone_bg_upload', array( __CLASS__, 'ajax_zone_bg_upload' ) );
	}

	public static function floor_term( $floor_id ) {
		$floor_id = absint( $floor_id );
		if ( ! $floor_id ) {
			return null;
		}
		$term = get_term( $floor_id, self::FLOOR_TAX );
		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	public static function table_floor( $table_id ) {
		$terms = wp_get_object_terms( (int) $table_id, self::FLOOR_TAX, array( 'fields' => 'ids' ) );
		return ( is_wp_error( $terms ) || ! $terms ) ? 0 : (int) $terms[0];
	}

	public static function table_zone( $table_id ) {
		$terms = wp_get_object_terms( (int) $table_id, self::ZONE_TAX, array( 'fields' => 'ids' ) );
		return ( is_wp_error( $terms ) || ! $terms ) ? 0 : (int) $terms[0];
	}

	public static function zone_floor( $zone_id ) {
		return (int) get_term_meta( (int) $zone_id, '_siqo_zone_floor', true );
	}

	public static function floor_layout( $floor_id ) {
		$floor_id = absint( $floor_id );
		$layout   = $floor_id ? get_term_meta( $floor_id, '_siqo_floor_layout', true ) : get_option( self::VIRTUAL_OPTION, array() );
		$layout   = is_array( $layout ) ? $layout : array();
		$w        = isset( $layout['w'] ) ? (int) $layout['w'] : self::DEFAULT_W;
		$h        = isset( $layout['h'] ) ? (int) $layout['h'] : self::DEFAULT_H;
		return array(
			'w' => min( self::MAX_DIM, max( self::DEFAULT_W, $w ) ),
			'h' => min( self::MAX_DIM, max( self::DEFAULT_H, $h ) ),
		);
	}

	public static function save_canvas( $floor_id, $canvas ) {
		$w = min( self::MAX_DIM, max( self::DEFAULT_W, (int) $canvas['w'] ) );
		$h = min( self::MAX_DIM, max( self::DEFAULT_H, (int) $canvas['h'] ) );
		if ( absint( $floor_id ) ) {
			update_term_meta( absint( $floor_id ), '_siqo_floor_layout', array( 'w' => $w, 'h' => $h ) );
		} else {
			update_option( self::VIRTUAL_OPTION, array( 'w' => $w, 'h' => $h ) );
		}
		return array( 'w' => $w, 'h' => $h );
	}

	public static function grow_canvas( $canvas, $items ) {
		$w = max( self::DEFAULT_W, isset( $canvas['w'] ) ? (int) $canvas['w'] : 0 );
		$h = max( self::DEFAULT_H, isset( $canvas['h'] ) ? (int) $canvas['h'] : 0 );
		foreach ( $items as $item ) {
			$w = max( $w, (int) $item['x'] + (int) $item['w'] + 40 );
			$h = max( $h, (int) $item['y'] + (int) $item['h'] + 40 );
		}
		return array(
			'w' => min( self::MAX_DIM, $w ),
			'h' => min( self::MAX_DIM, $h ),
		);
	}

	public static function table_counts() {
		$counts = array( 0 => array( 'free' => 0, 'busy' => 0 ) );
		$ids    = get_posts(
			array(
				'post_type'   => 'siqo_tables',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$floor = self::table_floor( $id );
			if ( ! isset( $counts[ $floor ] ) ) {
				$counts[ $floor ] = array( 'free' => 0, 'busy' => 0 );
			}
			$status = (string) SIQO_get_meta( 'status_table_' . $id );
			if ( '' === $status || '0' === $status ) {
				$counts[ $floor ]['free']++;
			} else {
				$counts[ $floor ]['busy']++;
			}
		}
		return $counts;
	}

	public static function floors() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::FLOOR_TAX,
				'hide_empty' => false,
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;
		usort(
			$terms,
			function ( $a, $b ) {
				$oa = (int) get_term_meta( $a->term_id, '_siqo_floor_order', true );
				$ob = (int) get_term_meta( $b->term_id, '_siqo_floor_order', true );
				$oa = $oa ? $oa : (int) $a->term_id;
				$ob = $ob ? $ob : (int) $b->term_id;
				if ( $oa === $ob ) {
					return strcasecmp( $a->name, $b->name );
				}
				return ( $oa < $ob ) ? -1 : 1;
			}
		);

		$counts = self::table_counts();
		$list   = array();
		if ( ( isset( $counts[0]['free'] ) && $counts[0]['free'] ) || ( isset( $counts[0]['busy'] ) && $counts[0]['busy'] ) || ! $terms ) {
			$list[] = array(
				'id'      => 0,
				'name'    => __( 'Piano terra', 'studio-immens-quick-order' ),
				'virtual' => true,
				'free'    => isset( $counts[0]['free'] ) ? (int) $counts[0]['free'] : 0,
				'busy'    => isset( $counts[0]['busy'] ) ? (int) $counts[0]['busy'] : 0,
				'layout'  => self::floor_layout( 0 ),
			);
		}
		foreach ( $terms as $term ) {
			$list[] = array(
				'id'      => (int) $term->term_id,
				'name'    => $term->name,
				'virtual' => false,
				'free'    => isset( $counts[ $term->term_id ]['free'] ) ? (int) $counts[ $term->term_id ]['free'] : 0,
				'busy'    => isset( $counts[ $term->term_id ]['busy'] ) ? (int) $counts[ $term->term_id ]['busy'] : 0,
				'layout'  => self::floor_layout( $term->term_id ),
			);
		}
		return $list;
	}

	public static function zone_payload( $term_id ) {
		$term = get_term( (int) $term_id, self::ZONE_TAX );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$bounds = get_term_meta( $term->term_id, '_siqo_zone_bounds', true );
		$bounds = is_array( $bounds ) ? $bounds : array();
		$bg     = (int) get_term_meta( $term->term_id, '_siqo_zone_bg', true );
		$floor  = (int) get_term_meta( $term->term_id, '_siqo_zone_floor', true );
		return array(
			'id'    => (int) $term->term_id,
			'name'  => $term->name,
			'floor' => $floor,
			'color' => (string) get_term_meta( $term->term_id, '_siqo_zone_color', true ),
			'bg'    => $bg,
			'bgUrl' => $bg ? (string) wp_get_attachment_image_url( $bg, 'large' ) : '',
			'x'     => isset( $bounds['x'] ) ? (int) $bounds['x'] : 40,
			'y'     => isset( $bounds['y'] ) ? (int) $bounds['y'] : 40,
			'w'     => isset( $bounds['w'] ) ? (int) $bounds['w'] : 240,
			'h'     => isset( $bounds['h'] ) ? (int) $bounds['h'] : 160,
		);
	}

	public static function zones() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::ZONE_TAX,
				'hide_empty' => false,
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$list  = array();
		foreach ( $terms as $term ) {
			$payload = self::zone_payload( $term->term_id );
			if ( $payload ) {
				$list[] = $payload;
			}
		}
		return $list;
	}

	public static function tables_on_floor( $floor_id ) {
		$ids = get_posts(
			array(
				'post_type'   => 'siqo_tables',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( self::table_floor( $id ) === (int) $floor_id ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	public static function int_field( $item, $key, $default = 0 ) {
		return isset( $item[ $key ] ) ? (int) $item[ $key ] : $default;
	}

	public static function guard() {
		check_ajax_referer( 'SIQO_insert_post_ajax_nonce', 'nonce' );
		if ( ! SIQO_Management::can( 'manage_tables' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
		}
	}

	public static function ajax_save_layout() {
		self::guard();

		$raw = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '';
		if ( '' !== $raw ) {
			$data = json_decode( (string) $raw, true );
		} else {
			$data = array(
				'floor_id' => isset( $_POST['floor_id'] ) ? absint( $_POST['floor_id'] ) : 0,
				'canvas'   => isset( $_POST['canvas'] ) ? json_decode( wp_unslash( $_POST['canvas'] ), true ) : array(),
				'tables'   => isset( $_POST['tables'] ) ? json_decode( wp_unslash( $_POST['tables'] ), true ) : array(),
				'zones'    => isset( $_POST['zones'] ) ? json_decode( wp_unslash( $_POST['zones'] ), true ) : array(),
			);
		}
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Dati disposizione non validi.', 'studio-immens-quick-order' ) ), 400 );
		}

		$legacy = isset( $data[0] );
		if ( $legacy ) {
			$data = array( 'floor_id' => 0, 'tables' => $data, 'zones' => array() );
		}

		$floor_id = isset( $data['floor_id'] ) ? absint( $data['floor_id'] ) : 0;
		if ( $floor_id && ! self::floor_term( $floor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Piano inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}

		$raw_tables = isset( $data['tables'] ) && is_array( $data['tables'] ) ? $data['tables'] : array();
		$raw_zones  = isset( $data['zones'] ) && is_array( $data['zones'] ) ? $data['zones'] : array();
		if ( count( $raw_tables ) > 500 || count( $raw_zones ) > 200 ) {
			wp_send_json_error( array( 'message' => __( 'Troppi elementi in una sola richiesta.', 'studio-immens-quick-order' ) ), 400 );
		}

		$canvas = isset( $data['canvas'] ) && is_array( $data['canvas'] ) ? $data['canvas'] : self::floor_layout( $floor_id );

		$tables = array();
		foreach ( $raw_tables as $item ) {
			if ( ! is_array( $item ) ) {
				wp_send_json_error( array( 'message' => __( 'Tavolo non valido.', 'studio-immens-quick-order' ) ), 400 );
			}
			$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || 'siqo_tables' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'Tavolo inesistente.', 'studio-immens-quick-order' ) ), 400 );
			}
			$stored = get_post_meta( $id, '_siqo_plan_position', true );
			$stored = is_array( $stored ) ? $stored : array();
			$shape  = isset( $item['shape'] ) ? sanitize_key( $item['shape'] ) : '';
			if ( ! in_array( $shape, array( 'rect', 'square', 'round' ), true ) ) {
				$shape = (string) get_post_meta( $id, '_siqo_plan_shape', true );
				$shape = in_array( $shape, array( 'rect', 'square', 'round' ), true ) ? $shape : 'rect';
			}
			$seats = isset( $item['seats'] ) ? (int) $item['seats'] : (int) get_post_meta( $id, '_siqo_plan_seats', true );
			$zone  = isset( $item['zone_id'] ) ? absint( $item['zone_id'] ) : 0;
			if ( $zone ) {
				$zone_term = get_term( $zone, self::ZONE_TAX );
				if ( ! $zone_term || is_wp_error( $zone_term ) || self::zone_floor( $zone ) !== $floor_id ) {
					$zone = 0;
				}
			}
			$tables[] = array(
				'id'     => $id,
				'x'      => max( 0, self::int_field( $item, 'x', isset( $stored['x'] ) ? (int) $stored['x'] : 40 ) ),
				'y'      => max( 0, self::int_field( $item, 'y', isset( $stored['y'] ) ? (int) $stored['y'] : 40 ) ),
				'w'      => max( self::MIN_W, self::int_field( $item, 'w', isset( $stored['w'] ) ? (int) $stored['w'] : 180 ) ),
				'h'      => max( self::MIN_H, self::int_field( $item, 'h', isset( $stored['h'] ) ? (int) $stored['h'] : 120 ) ),
				'shape'  => $shape,
				'seats'  => max( 0, min( self::MAX_SEATS, $seats ) ),
				'zone'   => $zone,
				'legacy' => $legacy,
			);
		}

		$zones = array();
		foreach ( $raw_zones as $item ) {
			if ( ! is_array( $item ) ) {
				wp_send_json_error( array( 'message' => __( 'Zona non valida.', 'studio-immens-quick-order' ) ), 400 );
			}
			$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$term = $id ? get_term( $id, self::ZONE_TAX ) : null;
			if ( ! $term || is_wp_error( $term ) ) {
				wp_send_json_error( array( 'message' => __( 'Zona inesistente.', 'studio-immens-quick-order' ) ), 400 );
			}
			$zones[] = array(
				'id' => $id,
				'x'  => max( 0, self::int_field( $item, 'x', 40 ) ),
				'y'  => max( 0, self::int_field( $item, 'y', 40 ) ),
				'w'  => max( self::MIN_W, self::int_field( $item, 'w', 240 ) ),
				'h'  => max( self::MIN_H, self::int_field( $item, 'h', 160 ) ),
			);
		}

		$canvas = self::grow_canvas( $canvas, array_merge( $tables, $zones ) );
		foreach ( $tables as $i => $table ) {
			$tables[ $i ]['x'] = min( $table['x'], max( 0, $canvas['w'] - $table['w'] ) );
			$tables[ $i ]['y'] = min( $table['y'], max( 0, $canvas['h'] - $table['h'] ) );
		}
		foreach ( $zones as $i => $zone ) {
			$zones[ $i ]['x'] = min( $zone['x'], max( 0, $canvas['w'] - $zone['w'] ) );
			$zones[ $i ]['y'] = min( $zone['y'], max( 0, $canvas['h'] - $zone['h'] ) );
		}

		foreach ( $tables as $table ) {
			$position = array( 'x' => $table['x'], 'y' => $table['y'] );
			if ( ! $table['legacy'] ) {
				$position['w'] = $table['w'];
				$position['h'] = $table['h'];
			}
			update_post_meta( $table['id'], '_siqo_plan_position', $position );
			if ( ! $table['legacy'] ) {
				update_post_meta( $table['id'], '_siqo_plan_shape', $table['shape'] );
				update_post_meta( $table['id'], '_siqo_plan_seats', $table['seats'] );
				wp_set_object_terms( $table['id'], $floor_id ? array( (int) $floor_id ) : array(), self::FLOOR_TAX );
				wp_set_object_terms( $table['id'], $table['zone'] ? array( $table['zone'] ) : array(), self::ZONE_TAX );
			}
		}
		foreach ( $zones as $zone ) {
			update_term_meta( $zone['id'], '_siqo_zone_bounds', array( 'x' => $zone['x'], 'y' => $zone['y'], 'w' => $zone['w'], 'h' => $zone['h'] ) );
			if ( ! self::zone_floor( $zone['id'] ) ) {
				update_term_meta( $zone['id'], '_siqo_zone_floor', $floor_id );
			}
		}

		$canvas = self::save_canvas( $floor_id, $canvas );
		wp_send_json_success(
			array(
				'saved'    => count( $tables ) + count( $zones ),
				'floor_id' => $floor_id,
				'canvas'   => $canvas,
			)
		);
	}

	public static function next_floor_order() {
		$max   = 0;
		$terms = get_terms(
			array(
				'taxonomy'   => self::FLOOR_TAX,
				'hide_empty' => false,
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;
		foreach ( $terms as $term ) {
			$order = (int) get_term_meta( $term->term_id, '_siqo_floor_order', true );
			$max   = max( $max, $order ? $order : (int) $term->term_id );
		}
		return $max + 1;
	}

	public static function ajax_floor_create() {
		self::guard();
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > 60 ) {
			wp_send_json_error( array( 'message' => __( 'Nome piano non valido.', 'studio-immens-quick-order' ) ), 400 );
		}
		if ( term_exists( $name, self::FLOOR_TAX ) ) {
			wp_send_json_error( array( 'message' => __( 'Esiste già un piano con questo nome.', 'studio-immens-quick-order' ) ), 400 );
		}
		$layout = array( 'w' => self::DEFAULT_W, 'h' => self::DEFAULT_H );
		if ( isset( $_POST['copy_from'] ) ) {
			$from = absint( $_POST['copy_from'] );
			if ( $from ? self::floor_term( $from ) : true ) {
				$layout = self::floor_layout( $from );
			}
		}
		$inserted = wp_insert_term( $name, self::FLOOR_TAX );
		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( array( 'message' => $inserted->get_error_message() ), 400 );
		}
		update_term_meta( $inserted['term_id'], '_siqo_floor_order', self::next_floor_order() );
		update_term_meta( $inserted['term_id'], '_siqo_floor_layout', $layout );
		self::respond_floors();
	}

	public static function ajax_floor_update() {
		self::guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term = self::floor_term( $id );
		if ( ! $term ) {
			wp_send_json_error( array( 'message' => __( 'Piano inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		if ( isset( $_POST['name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
			if ( '' === $name || mb_strlen( $name ) > 60 ) {
				wp_send_json_error( array( 'message' => __( 'Nome piano non valido.', 'studio-immens-quick-order' ) ), 400 );
			}
			$clash = term_exists( $name, self::FLOOR_TAX );
			if ( $clash && (int) $clash['term_id'] !== $id ) {
				wp_send_json_error( array( 'message' => __( 'Esiste già un piano con questo nome.', 'studio-immens-quick-order' ) ), 400 );
			}
			$updated = wp_update_term( $id, self::FLOOR_TAX, array( 'name' => $name ) );
			if ( is_wp_error( $updated ) ) {
				wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
			}
		}
		if ( isset( $_POST['order'] ) ) {
			update_term_meta( $id, '_siqo_floor_order', absint( $_POST['order'] ) );
		}
		self::respond_floors();
	}

	public static function ajax_floor_delete() {
		self::guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term = self::floor_term( $id );
		if ( ! $term ) {
			wp_send_json_error( array( 'message' => __( 'Piano inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}

		$tables = self::tables_on_floor( $id );
		$move   = isset( $_POST['move_to'] ) ? absint( $_POST['move_to'] ) : -1;
		if ( $tables && $move < 0 ) {
			$list = array();
			foreach ( $tables as $table_id ) {
				$list[] = array(
					'id'   => $table_id,
					'name' => get_the_title( $table_id ),
				);
			}
			wp_send_json_error(
				array(
					'message' => __( 'Il piano contiene tavoli.', 'studio-immens-quick-order' ),
					'tables'  => $list,
				),
				409
			);
		}
		if ( $move >= 0 && $move !== $id && $move && ! self::floor_term( $move ) ) {
			wp_send_json_error( array( 'message' => __( 'Piano di destinazione inesistente.', 'studio-immens-quick-order' ) ), 400 );
		}
		if ( $move === $id ) {
			$move = 0;
		}
		foreach ( $tables as $table_id ) {
			wp_set_object_terms( $table_id, $move ? array( (int) $move ) : array(), self::FLOOR_TAX );
		}

		$zones = get_terms(
			array(
				'taxonomy'   => self::ZONE_TAX,
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_siqo_zone_floor',
						'value' => $id,
					),
				),
			)
		);
		if ( ! is_wp_error( $zones ) ) {
			foreach ( $zones as $zone ) {
				wp_delete_term( $zone->term_id, self::ZONE_TAX );
			}
		}

		$deleted = wp_delete_term( $id, self::FLOOR_TAX );
		if ( ! $deleted || is_wp_error( $deleted ) ) {
			wp_send_json_error( array( 'message' => __( 'Eliminazione non riuscita.', 'studio-immens-quick-order' ) ), 400 );
		}
		self::respond_floors();
	}

	public static function ajax_zone_create() {
		self::guard();
		$floor_id = isset( $_POST['floor_id'] ) ? absint( $_POST['floor_id'] ) : 0;
		if ( $floor_id && ! self::floor_term( $floor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Piano inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > 60 ) {
			wp_send_json_error( array( 'message' => __( 'Nome zona non valido.', 'studio-immens-quick-order' ) ), 400 );
		}
		if ( term_exists( $name, self::ZONE_TAX ) ) {
			wp_send_json_error( array( 'message' => __( 'Esiste già una zona con questo nome.', 'studio-immens-quick-order' ) ), 400 );
		}
		$bounds   = array(
			'x' => max( 0, self::int_field( $_POST, 'x', 40 ) ),
			'y' => max( 0, self::int_field( $_POST, 'y', 40 ) ),
			'w' => max( self::MIN_W, self::int_field( $_POST, 'w', 240 ) ),
			'h' => max( self::MIN_H, self::int_field( $_POST, 'h', 160 ) ),
		);
		$inserted = wp_insert_term( $name, self::ZONE_TAX );
		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( array( 'message' => $inserted->get_error_message() ), 400 );
		}
		$id = (int) $inserted['term_id'];
		update_term_meta( $id, '_siqo_zone_floor', $floor_id );
		update_term_meta( $id, '_siqo_zone_bounds', $bounds );
		update_term_meta( $id, '_siqo_zone_color', '#a6462f' );
		wp_send_json_success(
			array(
				'zone'  => self::zone_payload( $id ),
				'zones' => self::zones(),
			)
		);
	}

	public static function ajax_zone_update() {
		self::guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term = $id ? get_term( $id, self::ZONE_TAX ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Zona inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		if ( isset( $_POST['name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
			if ( '' === $name || mb_strlen( $name ) > 60 ) {
				wp_send_json_error( array( 'message' => __( 'Nome zona non valido.', 'studio-immens-quick-order' ) ), 400 );
			}
			$clash = term_exists( $name, self::ZONE_TAX );
			if ( $clash && (int) $clash['term_id'] !== $id ) {
				wp_send_json_error( array( 'message' => __( 'Esiste già una zona con questo nome.', 'studio-immens-quick-order' ) ), 400 );
			}
			$updated = wp_update_term( $id, self::ZONE_TAX, array( 'name' => $name ) );
			if ( is_wp_error( $updated ) ) {
				wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
			}
		}
		if ( isset( $_POST['color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['color'] ) );
			update_term_meta( $id, '_siqo_zone_color', $color ? $color : '' );
		}
		if ( isset( $_POST['x'] ) || isset( $_POST['y'] ) || isset( $_POST['w'] ) || isset( $_POST['h'] ) ) {
			$bounds = get_term_meta( $id, '_siqo_zone_bounds', true );
			$bounds = is_array( $bounds ) ? $bounds : array();
			$bounds = array(
				'x' => max( 0, self::int_field( $_POST, 'x', isset( $bounds['x'] ) ? (int) $bounds['x'] : 40 ) ),
				'y' => max( 0, self::int_field( $_POST, 'y', isset( $bounds['y'] ) ? (int) $bounds['y'] : 40 ) ),
				'w' => max( self::MIN_W, self::int_field( $_POST, 'w', isset( $bounds['w'] ) ? (int) $bounds['w'] : 240 ) ),
				'h' => max( self::MIN_H, self::int_field( $_POST, 'h', isset( $bounds['h'] ) ? (int) $bounds['h'] : 160 ) ),
			);
			update_term_meta( $id, '_siqo_zone_bounds', $bounds );
		}
		if ( isset( $_POST['bg'] ) ) {
			$bg = absint( $_POST['bg'] );
			if ( $bg && 'image' !== substr( (string) get_post_mime_type( $bg ), 0, 5 ) ) {
				wp_send_json_error( array( 'message' => __( 'Allegato non valido.', 'studio-immens-quick-order' ) ), 400 );
			}
			if ( $bg ) {
				update_term_meta( $id, '_siqo_zone_bg', $bg );
			} else {
				delete_term_meta( $id, '_siqo_zone_bg' );
			}
		}
		wp_send_json_success(
			array(
				'zone'  => self::zone_payload( $id ),
				'zones' => self::zones(),
			)
		);
	}

	public static function ajax_zone_delete() {
		self::guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term = $id ? get_term( $id, self::ZONE_TAX ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Zona inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		wp_delete_term( $id, self::ZONE_TAX );
		wp_send_json_success( array( 'zones' => self::zones() ) );
	}

	public static function ajax_zone_bg_upload() {
		self::guard();
		$zone_id = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0;
		$term    = $zone_id ? get_term( $zone_id, self::ZONE_TAX ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Zona inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nessun file ricevuto.', 'studio-immens-quick-order' ) ), 400 );
		}
		$file = $_FILES['file'];
		if ( ! empty( $file['size'] ) && (int) $file['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Immagine troppo grande (max 2 MB).', 'studio-immens-quick-order' ) ), 400 );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( ! $check['type'] || ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Formato immagine non supportato.', 'studio-immens-quick-order' ) ), 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$attachment = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $attachment ) ) {
			wp_send_json_error( array( 'message' => $attachment->get_error_message() ), 400 );
		}
		update_term_meta( $zone_id, '_siqo_zone_bg', (int) $attachment );
		wp_send_json_success(
			array(
				'zone'  => self::zone_payload( $zone_id ),
				'zones' => self::zones(),
			)
		);
	}

	public static function respond_floors() {
		wp_send_json_success( array( 'floors' => self::floors(), 'zones' => self::zones() ) );
	}
}
