<?php
defined( 'ABSPATH' ) || exit;

class SIQO_Table_Sessions {

	const DB_VERSION   = '1.0.0';
	const DEFAULT_TTL  = 9000;  // 150 minuti
	const MAX_ORDERS   = 20;
	const MIN_INTERVAL = 60;    // secondi tra due ordini
	const MAX_TOTAL    = 500;   // EUR per sessione

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'init', array( __CLASS__, 'maybe_backfill_keys' ), 20 );
		add_action( 'save_post_siqo_tables', array( __CLASS__, 'on_save_table' ), 10, 3 );
		add_action( 'siqo_table_session_gc', array( __CLASS__, 'gc' ) );

		if ( ! wp_next_scheduled( 'siqo_table_session_gc' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'siqo_table_session_gc' );
		}
	}

	public static function ensure_key( $table_id ) {
		$table_id = absint( $table_id );
		$key      = get_post_meta( $table_id, '_siqo_table_key', true );
		if ( $key && preg_match( '/^[a-z0-9]{12}$/', $key ) ) {
			return $key;
		}
		$key = strtolower( wp_generate_password( 12, false, false ) );
		update_post_meta( $table_id, '_siqo_table_key', $key );
		return $key;
	}

	public static function key_for_table( $table_id ) {
		return self::ensure_key( $table_id );
	}

	public static function find_table_by_key( $key ) {
		$key = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $key ) );
		if ( 12 !== strlen( $key ) ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'      => 'siqo_tables',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_siqo_table_key',
				'meta_value'     => $key,
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	public static function table_url( $table_id ) {
		$page = get_page_by_path( 'quick-menu' );
		$base = $page ? get_permalink( $page ) : home_url( '/quick-menu/' );
		return add_query_arg( 't', self::ensure_key( $table_id ), $base );
	}

	public static function maybe_backfill_keys() {
		if ( '1' === get_option( 'siqo_table_keys_backfill' ) ) {
			return;
		}
		$tables = get_posts(
			array(
				'post_type'      => 'siqo_tables',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $tables as $table_id ) {
			self::ensure_key( $table_id );
		}
		update_option( 'siqo_table_keys_backfill', '1', false );
	}

	public static function on_save_table( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::ensure_key( $post_id );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'siqo_table_sessions';
	}

	public static function maybe_install() {
		if ( get_option( 'siqo_table_sessions_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			table_id bigint(20) unsigned NOT NULL,
			session_token varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			opened_by bigint(20) unsigned NOT NULL DEFAULT 0,
			opened_via varchar(20) NOT NULL DEFAULT 'list',
			opened_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			closed_at datetime DEFAULT NULL,
			orders_count int unsigned NOT NULL DEFAULT 0,
			last_order_at datetime DEFAULT NULL,
			session_total decimal(12,2) NOT NULL DEFAULT 0,
			guest_label varchar(190) NOT NULL DEFAULT '',
			meta longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY table_status (table_id,status),
			KEY expires_at (expires_at)
		) $collate;";
		dbDelta( $sql );
		update_option( 'siqo_table_sessions_db', self::DB_VERSION, false );
	}

	private static function now() {
		return current_time( 'mysql', true );
	}

	public static function get( $session_id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $session_id ) ) );
	}

	public static function get_active_for_table( $table_id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE table_id = %d AND status IN ('open','awaiting_approval') ORDER BY id DESC LIMIT 1",
				absint( $table_id )
			)
		);
	}

	public static function is_active( $table_id ) {
		$session = self::get_active_for_table( $table_id );
		if ( ! $session ) {
			return false;
		}
		return strtotime( $session->expires_at . ' UTC' ) > time();
	}

	public static function open( $table_id, $user_id = 0, $via = 'list', $ttl = 0 ) {
		$table_id = absint( $table_id );
		if ( ! $table_id || 'siqo_tables' !== get_post_type( $table_id ) ) {
			return new WP_Error( 'invalid_table', __( 'Tavolo non valido.', 'studio-immens-quick-order' ) );
		}

		$current = self::get_active_for_table( $table_id );
		if ( $current && strtotime( $current->expires_at . ' UTC' ) > time() ) {
			return array( 'session' => $current, 'reused' => true );
		}

		self::close( $table_id, 'expired' );

		global $wpdb;
		$ttl = $ttl > 0 ? absint( $ttl ) : (int) apply_filters( 'siqo_table_session_ttl', self::DEFAULT_TTL, $table_id );
		if ( $ttl <= 0 ) {
			$ttl = self::DEFAULT_TTL;
		}
		$opened   = self::now();
		$expires  = gmdate( 'Y-m-d H:i:s', strtotime( $opened . ' UTC' ) + $ttl );
		$token    = bin2hex( random_bytes( 16 ) );
		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'table_id'      => $table_id,
				'session_token' => $token,
				'status'        => 'open',
				'opened_by'     => absint( $user_id ),
				'opened_via'    => sanitize_key( $via ),
				'opened_at'     => $opened,
				'expires_at'    => $expires,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Impossibile aprire il tavolo.', 'studio-immens-quick-order' ) );
		}
		return array( 'session' => self::get( $wpdb->insert_id ), 'reused' => false );
	}

	public static function close( $table_id, $status = 'closed' ) {
		global $wpdb;
		$table   = self::table_name();
		$allowed = array( 'closed', 'expired' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'closed';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, closed_at = %s WHERE table_id = %d AND status IN ('open','awaiting_approval')",
				$status,
				self::now(),
				absint( $table_id )
			)
		);
	}

	public static function close_all() {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'closed', closed_at = %s WHERE status IN ('open','awaiting_approval')",
				self::now()
			)
		);
	}

	public static function reserve_order( $table_id, $total = 0 ) {
		$session = self::get_active_for_table( $table_id );
		if ( ! $session ) {
			return new WP_Error( 'no_session', __( 'Tavolo non attivo. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		if ( strtotime( $session->expires_at . ' UTC' ) <= time() ) {
			return new WP_Error( 'session_expired', __( 'Sessione del tavolo scaduta. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		if ( ! self::register_order( $session->id, $total ) ) {
			$fresh = self::get( $session->id );
			if ( ! $fresh || ! in_array( $fresh->status, array( 'open', 'awaiting_approval' ), true ) ) {
				return new WP_Error( 'no_session', __( 'Tavolo non attivo. Chiama un operatore.', 'studio-immens-quick-order' ) );
			}
			$max_orders = max( 1, (int) apply_filters( 'siqo_order_max_orders', self::MAX_ORDERS, $fresh ) );
			if ( (int) $fresh->orders_count >= $max_orders ) {
				return new WP_Error( 'too_many_orders', __( 'Troppi ordini per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) );
			}
			$max_total = (float) apply_filters( 'siqo_order_max_total', self::MAX_TOTAL, $fresh );
			if ( (float) $fresh->session_total + (float) $total > $max_total ) {
				return new WP_Error( 'total_cap', __( 'Limite importo raggiunto per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) );
			}
			return new WP_Error( 'too_fast', __( 'Attendi qualche secondo prima di inviare un altro ordine.', 'studio-immens-quick-order' ) );
		}
		return self::get( $session->id );
	}

	public static function register_order( $session_id, $total = 0 ) {
		global $wpdb;
		$table      = self::table_name();
		$session    = self::get( $session_id );
		if ( ! $session ) {
			return false;
		}
		$max_orders = max( 1, (int) apply_filters( 'siqo_order_max_orders', self::MAX_ORDERS, $session ) );
		$interval   = (int) apply_filters( 'siqo_order_min_interval', self::MIN_INTERVAL, $session );
		$max_total  = (float) apply_filters( 'siqo_order_max_total', self::MAX_TOTAL, $session );
		$threshold  = gmdate( 'Y-m-d H:i:s', time() - $interval );
		$affected   = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET orders_count = orders_count + 1, last_order_at = %s, session_total = session_total + %f
				 WHERE id = %d AND status IN ('open','awaiting_approval')
				 AND expires_at > %s
				 AND orders_count < %d
				 AND (last_order_at IS NULL OR last_order_at < %s)
				 AND session_total + %f <= %f",
				self::now(),
				(float) $total,
				absint( $session_id ),
				self::now(),
				$max_orders,
				$threshold,
				(float) $total,
				$max_total
			)
		);
		return 1 === (int) $affected;
	}

	public static function add_order_total( $session_id, $total ) {
		global $wpdb;
		$session = self::get( $session_id );
		if ( ! $session ) {
			return false;
		}
		$max_total = (float) apply_filters( 'siqo_order_max_total', self::MAX_TOTAL, $session );
		$affected  = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . self::table_name() . " SET session_total = session_total + %f
				 WHERE id = %d AND session_total + %f <= %f",
				(float) $total,
				absint( $session_id ),
				(float) $total,
				$max_total
			)
		);
		return 1 === (int) $affected;
	}

	public static function gc() {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired', closed_at = %s WHERE status IN ('open','awaiting_approval') AND expires_at < %s",
				self::now(),
				self::now()
			)
		);
	}
}
