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

	public static function respond_floors() {
		wp_send_json_success( array( 'floors' => self::floors(), 'zones' => self::zones() ) );
	}
}
