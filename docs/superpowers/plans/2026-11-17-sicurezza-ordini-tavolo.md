# Sicurezza ordini al tavolo + autore ordine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gli ordini al tavolo vengono accettati solo dentro una sessione aperta da un operatore, il primo ordine della sessione richiede conferma, e ogni ordine registra chi lo ha inserito.

**Architecture:** Nuova tabella `siqo_table_sessions` + classe `SIQO_Table_Sessions` (stato, TTL, rate limit, GC). Il tavolo si identifica con una chiave casuale in post meta `_siqo_table_key` (URL `?t=<key>`). Il gate server vive in `SIQO_flash_tab_order_ajax()` dove viene creato l'ordine, ramo `$verified === true` (cliente). L'operatore usa gli stessi endpoint con `$verified === false` + cap `manage_tables`.

**Tech Stack:** PHP 8.2 (WordPress plugin, WooCommerce CRUD ordini), `$wpdb`/dbDelta, WP AJAX + nonce, jQuery lato frontend, cron WP.

**Spec:** `docs/superpowers/specs/2026-11-17-ordini-tavolo-sicuri-stile-stampe.md`

## Global Constraints

- PHP minimo del plugin: 7.4 (niente sintassi 8-only: no named args, no `match`, no enum).
- Testo UI in italiano, wrappato in `esc_html__()` con text-domain `studio-immens-quick-order`.
- Non introdurre dipendenze Composer/npm: nessun test framework nel repo, verifica = `php -l` + smoke WP CLI-less (bootstrap `wp-load.php`) + QA browser manuale.
- PHP CLI locale: `C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win32\php.exe`
- Sito di test: `http://localhost:10004` (pagina `quick-menu`, `manage-tables`, `ordini-reparti`, admin `gestione`).
- Non modificare `docs/superpowers/specs/2026-11-14-quick-order-management-suite-design.md`.
- Version bump: `STUDIO_IMMENS_QUICK_ORDER_VERSION` e header `Version:` a `1.0.11` a fine piano.

---

### Task 1: Tabella sessioni e classe `SIQO_Table_Sessions`

**Files:**
- Create: `includes/class-siqo-table-sessions.php`
- Modify: `includes/class-studio-immens-quick-order.php` (require dopo `class-siqo-documents.php`, init nel costruttore)
- Modify: `includes/class-siqo-meta-db.php` (solo se si preferisce installare da qui — non necessario)

**Interfaces:**
- Produces:
  - `SIQO_Table_Sessions::table_name(): string`
  - `SIQO_Table_Sessions::get( int $session_id ): ?object`
  - `SIQO_Table_Sessions::get_active_for_table( int $table_id ): ?object`
  - `SIQO_Table_Sessions::open( int $table_id, int $user_id, string $via = 'list', int $ttl = 0 ): array|WP_Error`
  - `SIQO_Table_Sessions::close( int $table_id, string $status = 'closed' ): void`
  - `SIQO_Table_Sessions::close_all(): int`
  - `SIQO_Table_Sessions::can_accept_order( int $table_id ): true|WP_Error`
  - `SIQO_Table_Sessions::register_order( int $session_id, float $total ): void`
  - `SIQO_Table_Sessions::gc(): int`
  - `SIQO_Table_Sessions::is_active( int $table_id ): bool`

- [ ] **Step 1: Creare la classe**

```php
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
		add_action( 'siqo_table_session_gc', array( __CLASS__, 'gc' ) );

		if ( ! wp_next_scheduled( 'siqo_table_session_gc' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'siqo_table_session_gc' );
		}
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
		$ttl      = $ttl > 0 ? absint( $ttl ) : (int) apply_filters( 'siqo_table_session_ttl', self::DEFAULT_TTL, $table_id );
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

	public static function can_accept_order( $table_id ) {
		$session = self::get_active_for_table( $table_id );
		if ( ! $session ) {
			return new WP_Error( 'no_session', __( 'Tavolo non attivo. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		if ( strtotime( $session->expires_at . ' UTC' ) <= time() ) {
			return new WP_Error( 'session_expired', __( 'Sessione del tavolo scaduta. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		$max_orders = (int) apply_filters( 'siqo_order_max_orders', self::MAX_ORDERS, $session );
		if ( (int) $session->orders_count >= $max_orders ) {
			return new WP_Error( 'too_many_orders', __( 'Troppi ordini per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		$interval = (int) apply_filters( 'siqo_order_min_interval', self::MIN_INTERVAL, $session );
		if ( $session->last_order_at && ( time() - strtotime( $session->last_order_at . ' UTC' ) ) < $interval ) {
			return new WP_Error( 'too_fast', __( 'Attendi qualche secondo prima di inviare un altro ordine.', 'studio-immens-quick-order' ) );
		}
		$max_total = (float) apply_filters( 'siqo_order_max_total', self::MAX_TOTAL, $session );
		if ( (float) $session->session_total >= $max_total ) {
			return new WP_Error( 'total_cap', __( 'Limite importo raggiunto per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) );
		}
		return true;
	}

	public static function register_order( $session_id, $total = 0 ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET orders_count = orders_count + 1, last_order_at = %s, session_total = session_total + %f WHERE id = %d",
				self::now(),
				(float) $total,
				absint( $session_id )
			)
		);
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
```

- [ ] **Step 2: Caricare la classe**

In `includes/class-studio-immens-quick-order.php`, dopo la riga 111 (`require_once ... 'includes/class-siqo-documents.php'`):

```php
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-siqo-table-sessions.php';
```

E nel costruttore, dopo `SIQO_Documents::init();`:

```php
SIQO_Table_Sessions::init();
```

- [ ] **Step 3: Lint**

Run: `& "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win32\php.exe" -l "C:\Users\mauro\Local Sites\local\app\public\wp-content\plugins\studio-immens-quick-order\includes\class-siqo-table-sessions.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Smoke (tabella creata + open/close)**

Run:
```powershell
& "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win32\php.exe" -r "require 'C:/Users/mauro/Local Sites/local/app/public/wp-load.php'; do_action('init'); global `$wpdb; var_dump(`$wpdb->get_var('SHOW TABLES LIKE \"`$wpdb->prefix.siqo_table_sessions\"'));"
```
Expected: nome tabella `wp_siqo_table_sessions` (o prefisso del sito).

- [ ] **Step 5: Commit**

```bash
git add includes/class-siqo-table-sessions.php includes/class-studio-immens-quick-order.php
git commit -m "feat: table sessions storage"
```

---

### Task 2: Chiave tavolo, resolver e QR per tavolo

**Files:**
- Modify: `includes/class-siqo-table-sessions.php` (metodi chiave)
- Modify: `public/studio-immens-quick-order-public.php` (shortcode QR tavolo, backfill)

**Interfaces:**
- Produces:
  - `SIQO_Table_Sessions::ensure_key( int $table_id ): string`
  - `SIQO_Table_Sessions::key_for_table( int $table_id ): string`
  - `SIQO_Table_Sessions::find_table_by_key( string $key ): int`
  - `SIQO_Table_Sessions::table_url( int $table_id ): string`
  - shortcode `[siqo_table_qr table="123" size="200"]`

- [ ] **Step 1: Aggiungere i metodi alla classe**

```php
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
		return add_query_arg( 't', self::ensure_key( $table_id ), home_url( '/quick-menu/' ) );
	}
```

- [ ] **Step 2: Shortcode QR tavolo**

In `public/studio-immens-quick-order-public.php`, dopo `SIQO_create_post_QR_code_shortcode()`:

```php
add_shortcode( 'siqo_table_qr', 'SIQO_table_qr_shortcode' );
function SIQO_table_qr_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'table' => 0, 'size' => 200 ),
		$atts,
		'siqo_table_qr'
	);
	$table_id = absint( $atts['table'] );
	if ( ! $table_id || 'siqo_tables' !== get_post_type( $table_id ) ) {
		return '';
	}
	$size = absint( $atts['size'] );
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/phpqrcode/qrlib.php';
	$url      = SIQO_Table_Sessions::table_url( $table_id );
	$qr_dir   = SIQO_get_qr_dir();
	$filename = $qr_dir . 'table_' . $table_id . '(' . $size . ').png';
	if ( ! file_exists( $filename ) ) {
		QRcode::png( $url, $filename, 'L', 10, 2 );
	}
	$qr_url = esc_url_raw( plugin_dir_url( dirname( __FILE__ ) ) . 'includes/phpqrcode/QRgenerate/table_' . $table_id . '(' . $size . ').png' );
	return '<img src="' . esc_attr( $qr_url ) . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr( get_the_title( $table_id ) ) . '">';
}
```

- [ ] **Step 3: Backfill chiavi per tavoli esistenti**

Aggiungere in `SIQO_Table_Sessions::init()`:

```php
		add_action( 'init', array( __CLASS__, 'maybe_backfill_keys' ), 20 );
```

e il metodo:

```php
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
```

- [ ] **Step 4: Generare chiavi a ogni nuovo tavolo**

In `SIQO_Table_Sessions::init()`:

```php
		add_action( 'save_post_siqo_tables', array( __CLASS__, 'on_save_table' ), 10, 3 );
```

```php
	public static function on_save_table( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::ensure_key( $post_id );
	}
```

- [ ] **Step 5: Lint + smoke**

Run lint su entrambi i file. Poi:
```powershell
& "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win32\php.exe" -r "require 'C:/Users/mauro/Local Sites/local/app/public/wp-load.php'; `$t=get_posts(['post_type'=>'siqo_tables','numberposts'=>1,'fields'=>'ids']); echo SIQO_Table_Sessions::table_url(`$t[0]);"
```
Expected: URL `http://localhost:10004/quick-menu/?t=<12 char>`.

- [ ] **Step 6: Commit**

```bash
git add includes/class-siqo-table-sessions.php public/studio-immens-quick-order-public.php
git commit -m "feat: per-table random key and table QR shortcode"
```

---

### Task 3: Risoluzione `?t=` e gate frontend

**Files:**
- Modify: `public/template/front-order-ajax.php`

**Interfaces:**
- Consumes: `SIQO_Table_Sessions::find_table_by_key()`, `SIQO_Table_Sessions::is_active()`
- Produces: variabili template `$table_id_valid` (int), `$table_session_active` (bool)

- [ ] **Step 1: Risolvere la chiave**

In `public/template/front-order-ajax.php`, prima del blocco `if ( isset($_GET['_siqononce_tab_check']) ...` (riga ~49):

```php
$table_link_key = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
if ( $table_link_key && class_exists( 'SIQO_Table_Sessions' ) ) {
	$key_table_id = SIQO_Table_Sessions::find_table_by_key( $table_link_key );
	if ( $key_table_id ) {
		$table_id_valid = $key_table_id;
		$table_name     = get_the_title( $key_table_id );
	}
}
```

- [ ] **Step 2: Sostituire il gate `is_local`**

Sostituire entrambi i blocchi `<?php if ( $net_info['is_local'] ) { ?>` (righe ~261 e ~274) con:

```php
				<?php if ( true ) { ?>
```

e nel `<select>` disabilitare i tavoli senza sessione:

```php
								<option value="<?php echo esc_attr( $tavolo->ID ); ?>"<?php selected( $table_id_valid, $tavolo->ID ); ?><?php disabled( ! SIQO_Table_Sessions::is_active( $tavolo->ID ) ); ?>>
									<?php echo esc_html( $tavolo->post_title ); ?><?php echo SIQO_Table_Sessions::is_active( $tavolo->ID ) ? '' : ' — ' . esc_html__( 'non attivo', 'studio-immens-quick-order' ); ?>
								</option>
```

- [ ] **Step 3: Messaggio quando il tavolo non è attivo**

Dopo il calcolo di `$table_id_valid`:

```php
$table_session_active = $table_id_valid ? SIQO_Table_Sessions::is_active( $table_id_valid ) : false;
if ( $table_id_valid && ! $table_session_active ) {
	$table_notice = __( 'Questo tavolo non è ancora attivo. Chiama un operatore per farti aprire il tavolo.', 'studio-immens-quick-order' );
}
```

- [ ] **Step 4: Lint + QA manuale**

Aprire `http://localhost:10004/quick-menu/?t=<key tavolo chiuso>`: atteso select visibile, opzione "non attivo", messaggio. Poi aprire la sessione (Task 5) e ricaricare: nessun messaggio.

- [ ] **Step 5: Commit**

```bash
git add public/template/front-order-ajax.php
git commit -m "feat: resolve table key and gate menu on active session"
```

---

### Task 4: Enforcement server e meta autore

**Files:**
- Modify: `public/studio-immens-quick-order-public.php` (`SIQO_flash_tab_order_ajax`, ~3833-4292)

**Interfaces:**
- Consumes: `SIQO_Table_Sessions::can_accept_order()`, `::register_order()`, `::get_active_for_table()`
- Produces: order meta `_siqo_inserted_by`, `_siqo_order_source`, `_siqo_session_id`, `_siqo_approval`

- [ ] **Step 1: Bloccare il submit cliente senza sessione**

Dentro `SIQO_flash_tab_order_ajax()`, subito dopo il controllo `if ( $verified && $requested_table_id && 'siqo_tables' !== get_post_type( $requested_table_id ) ) { ... }`:

```php
	$siqo_is_operator = ( ! $verified && class_exists( 'SIQO_Management' ) && SIQO_Management::can( 'manage_tables' ) );
	$siqo_source      = $siqo_is_operator ? 'operator' : 'customer_session';

	if ( ! $siqo_is_operator ) {
		if ( ! $requested_table_id ) {
			wp_send_json_error( array( 'message' => __( 'Seleziona un tavolo attivo prima di ordinare.', 'studio-immens-quick-order' ) ), 400 );
		}
		$siqo_gate = SIQO_Table_Sessions::can_accept_order( $requested_table_id );
		if ( is_wp_error( $siqo_gate ) ) {
			wp_send_json_error( array( 'message' => $siqo_gate->get_error_message() ), 403 );
		}
	}
```

Nota: `$verified === true` = chiamata cliente (da `FO_submit_order_ajax`), `$verified === false` = operatore.

- [ ] **Step 2: Registrare autore, sessione e approvazione dopo il save**

Dopo `$order_id = $order->get_id();` (riga ~4189) e prima di `$siqo_previous_order_ids = array();`:

```php
	$siqo_session = SIQO_Table_Sessions::get_active_for_table( $requested_table_id );
	$siqo_approval = 'auto_approved';
	if ( 'customer_session' === $siqo_source ) {
		$siqo_approval = ( $siqo_session && (int) $siqo_session->orders_count === 0 ) ? 'pending' : 'auto_approved';
	}
	$order->update_meta_data( '_siqo_inserted_by', get_current_user_id() );
	$order->update_meta_data( '_siqo_order_source', $siqo_source );
	$order->update_meta_data( '_siqo_session_id', $siqo_session ? (int) $siqo_session->id : 0 );
	$order->update_meta_data( '_siqo_approval', $siqo_approval );
	$order->save();

	if ( $siqo_session ) {
		SIQO_Table_Sessions::register_order( $siqo_session->id, (float) $order->get_total() );
	}
	if ( 'pending' === $siqo_approval ) {
		global $wpdb;
		$wpdb->update( SIQO_Table_Sessions::table_name(), array( 'status' => 'awaiting_approval' ), array( 'id' => (int) $siqo_session->id ), array( '%s' ), array( '%d' ) );
	}
```

- [ ] **Step 3: Tetto importo dopo i totali**

Subito dopo `$order = wc_get_order( $order_id );` (~4231) e prima del ricalcolo finale:

```php
	if ( 'customer_session' === $siqo_source && $siqo_session ) {
		$siqo_max_total = (float) apply_filters( 'siqo_order_max_total', SIQO_Table_Sessions::MAX_TOTAL, $siqo_session );
		$siqo_new_total = (float) $siqo_session->session_total + (float) $order->get_total();
		if ( $siqo_new_total > $siqo_max_total ) {
			$order->update_status( 'cancelled' );
			$order->add_order_note( __( 'Annullato: superato il limite importo della sessione tavolo.', 'studio-immens-quick-order' ) );
			wp_send_json_error( array( 'message' => __( 'Limite importo raggiunto per questo tavolo. Chiama un operatore.', 'studio-immens-quick-order' ) ), 403 );
		}
	}
```

- [ ] **Step 4: Lint + test manuale**

Run lint. Poi con un tavolo **senza** sessione inviare ordine via `wp_ajax_nopriv_siqo_submit_order_ajax` (curl con nonce da pagina): atteso `403` e nessun ordine creato (`wc_get_orders` non aumenta).

- [ ] **Step 5: Commit**

```bash
git add public/studio-immens-quick-order-public.php
git commit -m "feat: enforce table session on customer orders and record author"
```

---

### Task 5: Apertura/chiusura sessione da parte dell'operatore

**Files:**
- Create: `public/template/station-table-sessions.php` (endpoint + markup pannello)
- Modify: `public/station-orders.php` (include + render pannello in testa)
- Modify: `public/js/siqo-station-orders.js`
- Modify: `public/css/siqo-station-orders.css`
- Modify: `public/template/front-order-ajax.php` (conferma `arm=1`)

**Interfaces:**
- Produces: AJAX `siqo_table_open`, `siqo_table_close` (nonce `siqo_table_session`), markup `.siqo-table-session-bar`

- [ ] **Step 1: Endpoint**

```php
<?php
defined( 'ABSPATH' ) || exit;

function siqo_table_session_ajax() {
	if ( ! isset( $_POST['_siqo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_siqo_nonce'] ) ), 'siqo_table_session' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	$table_id = isset( $_POST['table_id'] ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
	$action   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'open';

	if ( 'close' === $action ) {
		SIQO_Table_Sessions::close( $table_id, 'closed' );
		wp_send_json_success( array( 'table_id' => $table_id, 'active' => false ) );
	}

	$result = SIQO_Table_Sessions::open( $table_id, get_current_user_id(), 'list' );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}
	$session = $result['session'];
	wp_send_json_success(
		array(
			'table_id'   => $table_id,
			'active'     => true,
			'expires_at' => $session->expires_at,
			'label'      => sprintf( __( 'Attivo fino alle %s', 'studio-immens-quick-order' ), wp_date( 'H:i', strtotime( $session->expires_at . ' UTC' ) ) ),
		)
	);
}
add_action( 'wp_ajax_siqo_table_open', 'siqo_table_session_ajax' );
add_action( 'wp_ajax_siqo_table_close', 'siqo_table_session_ajax' );
```

- [ ] **Step 2: Pannello nella pagina Ordini reparti**

In `public/station-orders.php`, dentro `siqo_station_orders_render()` dopo l'header, includere `station-table-sessions.php` e renderizzare:

```php
	<div class="siqo-table-session-bar" data-nonce="<?php echo esc_attr( wp_create_nonce( 'siqo_table_session' ) ); ?>">
		<h2><?php esc_html_e( 'Tavoli', 'studio-immens-quick-order' ); ?></h2>
		<ul class="siqo-table-grid">
			<?php foreach ( get_posts( array( 'post_type' => 'siqo_tables', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) ) as $siqo_table ) : ?>
				<?php $siqo_active = SIQO_Table_Sessions::is_active( $siqo_table->ID ); ?>
				<li class="siqo-table-chip<?php echo $siqo_active ? ' is-active' : ''; ?>" data-table-id="<?php echo esc_attr( $siqo_table->ID ); ?>">
					<span class="siqo-table-chip-name"><?php echo esc_html( $siqo_table->post_title ); ?></span>
					<button type="button" class="siqo-btn siqo-btn-primary siqo-table-toggle" data-do="<?php echo $siqo_active ? 'close' : 'open'; ?>">
						<?php echo $siqo_active ? esc_html__( 'Chiudi', 'studio-immens-quick-order' ) : esc_html__( 'Apri', 'studio-immens-quick-order' ); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
		<a class="siqo-link" href="<?php echo esc_url( add_query_arg( 'siqo_qr', '1', get_permalink() ) ); ?>"><?php esc_html_e( 'Stampa QR tavoli', 'studio-immens-quick-order' ); ?></a>
	</div>
```

- [ ] **Step 3: JS**

In `public/js/siqo-station-orders.js`, in coda:

```js
document.addEventListener('click', function (event) {
	var button = event.target.closest('.siqo-table-toggle');
	if (!button) { return; }
	var bar = button.closest('.siqo-table-session-bar');
	var chip = button.closest('.siqo-table-chip');
	var body = new FormData();
	body.append('action', button.dataset.do === 'close' ? 'siqo_table_close' : 'siqo_table_open');
	body.append('_siqo_nonce', bar.dataset.nonce);
	body.append('table_id', chip.dataset.tableId);
	body.append('do', button.dataset.do);
	fetch(bar.dataset.ajaxUrl || window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
		.then(function (r) { return r.json(); })
		.then(function (json) {
			if (!json.success) { window.alert(json.data && json.data.message ? json.data.message : 'Errore'); return; }
			chip.classList.toggle('is-active', json.data.active);
			button.dataset.do = json.data.active ? 'close' : 'open';
			button.textContent = json.data.active ? 'Chiudi' : 'Apri';
		});
});
```

- [ ] **Step 4: QR `arm=1`**

In `public/template/front-order-ajax.php`, se `isset($_GET['arm'])` e l'utente ha `manage_tables`, mostrare un pannello con bottone "Apri questo tavolo" che chiama `siqo_table_open` con `table_id = $table_id_valid` (stesso schema del punto 3).

- [ ] **Step 5: QA**

Aprire Ordini reparti da telefono (viewport 390px), aprire un tavolo, ricaricare `quick-menu` con il QR: deve permettere l'ordine con il tavolo preselezionato.

- [ ] **Step 6: Commit**

```bash
git add public/template/station-table-sessions.php public/station-orders.php public/js/siqo-station-orders.js public/css/siqo-station-orders.css public/template/front-order-ajax.php
git commit -m "feat: operator table session controls"
```

---

### Task 6: Approvazione del primo ordine

**Files:**
- Modify: `public/template/station-table-sessions.php`
- Modify: `public/station-orders.php` (`siqo_station_render_order_card`)
- Modify: `public/js/siqo-station-orders.js`
- Modify: Pro `includes/class-siqo-printers.php`

**Interfaces:**
- Produces: AJAX `siqo_order_approve`, `siqo_order_reject` (nonce `siqo_order_approval`), action `siqo_order_approved` con `$order_id`

- [ ] **Step 1: Endpoint**

```php
function siqo_order_approval_ajax() {
	if ( ! isset( $_POST['_siqo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_siqo_nonce'] ) ), 'siqo_order_approval' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}
	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Ordine non trovato.', 'studio-immens-quick-order' ) ), 404 );
	}
	$session_id = (int) $order->get_meta( '_siqo_session_id' );
	$session    = $session_id ? SIQO_Table_Sessions::get( $session_id ) : null;

	if ( 'approve' === $decision ) {
		$order->update_meta_data( '_siqo_approval', 'approved' );
		$order->update_status( 'processing', __( 'Ordine confermato dall\'operatore.', 'studio-immens-quick-order' ) );
		$order->save();
		if ( $session ) {
			global $wpdb;
			$wpdb->update( SIQO_Table_Sessions::table_name(), array( 'status' => 'open' ), array( 'id' => $session_id ), array( '%s' ), array( '%d' ) );
		}
		do_action( 'siqo_order_approved', $order_id );
		wp_send_json_success( array( 'order_id' => $order_id, 'approval' => 'approved' ) );
	}

	$order->update_meta_data( '_siqo_approval', 'rejected' );
	$order->update_status( 'cancelled', __( 'Ordine rifiutato dall\'operatore.', 'studio-immens-quick-order' ) );
	$order->save();
	if ( $session ) {
		global $wpdb;
		$wpdb->update(
			SIQO_Table_Sessions::table_name(),
			array( 'status' => 'open', 'orders_count' => max( 0, (int) $session->orders_count - 1 ), 'session_total' => max( 0, (float) $session->session_total - (float) $order->get_total() ) ),
			array( 'id' => $session_id ),
			array( '%s', '%d', '%f' ),
			array( '%d' )
		);
	}
	wp_send_json_success( array( 'order_id' => $order_id, 'approval' => 'rejected' ) );
}
add_action( 'wp_ajax_siqo_order_approve', 'siqo_order_approval_ajax' );
add_action( 'wp_ajax_siqo_order_reject', 'siqo_order_approval_ajax' );
```

Nota: `decision` va impostato a `approve` nel JS quando si chiama `siqo_order_approve`, e il ramo reject gira per default.

- [ ] **Step 2: Banner nella card**

In `siqo_station_render_order_card()`, sopra `<footer>`:

```php
		<?php if ( 'pending' === $order->get_meta( '_siqo_approval' ) ) : ?>
			<div class="siqo-order-approval" data-nonce="<?php echo esc_attr( wp_create_nonce( 'siqo_order_approval' ) ); ?>">
				<strong><?php esc_html_e( 'Da confermare', 'studio-immens-quick-order' ); ?></strong>
				<button type="button" class="siqo-btn siqo-btn-primary siqo-approve" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Conferma', 'studio-immens-quick-order' ); ?></button>
				<button type="button" class="siqo-btn siqo-reject" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Rifiuta', 'studio-immens-quick-order' ); ?></button>
			</div>
		<?php endif; ?>
```

- [ ] **Step 3: JS conferma/rifiuta**

```js
document.addEventListener('click', function (event) {
	var approve = event.target.closest('.siqo-approve, .siqo-reject');
	if (!approve) { return; }
	var box = approve.closest('.siqo-order-approval');
	var body = new FormData();
	body.append('action', approve.classList.contains('siqo-approve') ? 'siqo_order_approve' : 'siqo_order_reject');
	body.append('_siqo_nonce', box.dataset.nonce);
	body.append('order_id', approve.dataset.orderId);
	body.append('decision', approve.classList.contains('siqo-approve') ? 'approve' : 'reject');
	fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
		.then(function (r) { return r.json(); })
		.then(function () { window.location.reload(); });
});
```

- [ ] **Step 4: Pro — non stampare ordini in attesa**

In Pro `includes/class-siqo-printers.php`, dentro `queue_order()`: se `'pending' === get_post_meta( $order_id, '_siqo_approval', true )` (o `$order->get_meta`), return. In `init()` aggiungere:

```php
add_action( 'siqo_order_approved', array( __CLASS__, 'queue_order' ) );
```

Verificare che `queue_order()` accetti un `$order_id` come primo argomento (è già il caso).

- [ ] **Step 5: QA**

Primo ordine → card "Da confermare", nessun job di stampa (`siqo_print_jobs` vuota per quell'ordine). Conferma → ordine `processing`, job creato.

- [ ] **Step 6: Commit**

```bash
git add public/template/station-table-sessions.php public/station-orders.php public/js/siqo-station-orders.js public/studio-immens-quick-order-public.php
git -C "C:/Users/mauro/Local Sites/local/app/public/wp-content/plugins/studio-immens-quick-order-pro" add includes/class-siqo-printers.php
git commit -m "feat: first-order approval flow and deferred printing"
```

---

### Task 7: Badge autore ordine

**Files:**
- Modify: `public/station-orders.php`
- Modify: `includes/class-siqo-documents.php` (comanda)
- Modify: `public/css/siqo-station-orders.css`

**Interfaces:**
- Produces: `siqo_render_author_badge( WC_Order $order ): string`

- [ ] **Step 1: Funzione badge**

```php
function siqo_render_author_badge( $order ) {
	$source  = $order->get_meta( '_siqo_order_source' );
	$user_id = (int) $order->get_meta( '_siqo_inserted_by' );
	$table   = trim( (string) $order->get_meta( 'Table_cpt' ) . ' ' . (string) $order->get_meta( 'Table' ) );

	if ( 'operator' === $source && $user_id ) {
		$user = get_userdata( $user_id );
		$name = $user ? $user->display_name : __( 'Operatore', 'studio-immens-quick-order' );
		$avatar = get_avatar_url( $user_id, array( 'size' => 56 ) );
		$label  = sprintf( __( 'Operatore: %s', 'studio-immens-quick-order' ), $name );
	} else {
		$avatar = '';
		$name   = $table ? sprintf( __( 'Cliente · %s', 'studio-immens-quick-order' ), $table ) : __( 'Cliente', 'studio-immens-quick-order' );
		$label  = $name;
	}

	$html  = '<span class="siqo-author' . ( $avatar ? ' has-avatar' : '' ) . '">';
	if ( $avatar ) {
		$html .= '<img src="' . esc_url( $avatar ) . '" alt="" width="28" height="28">';
	}
	$html .= '<span class="siqo-author-name">' . esc_html( $label ) . '</span>';
	$html .= '</span>';
	return $html;
}
```

- [ ] **Step 2: Inserire nella card**

In `siqo_station_render_order_card()`, dentro `header .siqo-station-order-meta`, dopo `<time>`:

```php
				<?php echo siqo_render_author_badge( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
```

- [ ] **Step 3: CSS**

```css
.siqo-author { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:inherit; opacity:.85; }
.siqo-author img { border-radius:50%; display:block; }
.siqo-station-order-meta { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
```

- [ ] **Step 4: Comanda**

In `SIQO_Documents`, nel builder della comanda, aggiungere dopo il numero ordine:

```php
		$doc .= '<div class="doc-author">' . wp_strip_all_tags( siqo_render_author_badge( $order ) ) . '</div>';
```

- [ ] **Step 5: QA**

Ordine cliente → "Cliente · Tavolo 7" senza avatar. Ordine operatore → avatar + "Operatore: Mario".

- [ ] **Step 6: Commit**

```bash
git add public/station-orders.php includes/class-siqo-documents.php public/css/siqo-station-orders.css
git commit -m "feat: order author badge"
```

---

### Task 8: Chiusura automatica, chiudi-tutti e versione

**Files:**
- Modify: `public/template/station-table-sessions.php`
- Modify: `public/station-orders.php`
- Modify: `studio-immens-quick-order.php` + `readme.txt`

- [ ] **Step 1: Hook pagamento**

```php
add_action( 'siqo_table_paid', function ( $order_id, $table_id ) {
	$table_post = 0;
	if ( function_exists( 'SIQOP_get_table_by_table_id_last' ) ) {
		$row = SIQOP_get_table_by_table_id_last( $table_id, 'OBJECT' );
		if ( $row && ! empty( $row->table_number ) ) {
			$table_post = (int) $row->table_number;
		}
	}
	if ( $table_post ) {
		SIQO_Table_Sessions::close( $table_post, 'closed' );
	}
}, 10, 2 );
```

Nota: `table_number` ospita l'ID del post tavolo nel flusso attuale (`table_name_cpt` = post ID). Verificare con un pagamento di test e, se non coincide, usare `get_page_by_path`/meta lookup.

- [ ] **Step 2: Chiudi tutti**

Endpoint `siqo_tables_close_all` (stesso nonce, `manage_tables`) → `SIQO_Table_Sessions::close_all()`, bottone "Chiudi tutti" nella barra tavoli con conferma `window.confirm`.

- [ ] **Step 3: Versione**

`studio-immens-quick-order.php`: header `Version: 1.0.11`; costante `STUDIO_IMMENS_QUICK_ORDER_VERSION` a `1.0.11`. `readme.txt`: `Stable tag: 1.0.11` + changelog.

- [ ] **Step 4: QA finale (spec §11 punti 1-9)**

Eseguire i punti 1-9 della checklist QA della spec, incluso: sessione scaduta (forzare `expires_at` nel DB) → ordine rifiutato; GC oraria → sessione `expired`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: table session lifecycle and 1.0.11"
```

---

## Self-review

- Spec §3.1 chiave tavolo → Task 2.
- Spec §3.2 tabella sessioni/TTL/GC → Task 1, Task 8.
- Spec §3.3 apertura da QR e lista → Task 5.
- Spec §3.4 enforcement + rate limit → Task 4.
- Spec §3.5 approvazione → Task 6.
- Spec §3.6 meta autore → Task 4, Task 7.
- Spec §11 QA → Task 3/4/5/6/8 step QA.
- Aree 4-7 della spec (stile, scontrini, stampa, badge) → piano separato `stile-scontrini-stampa` (Task 7 copre solo il badge autore, condiviso).
