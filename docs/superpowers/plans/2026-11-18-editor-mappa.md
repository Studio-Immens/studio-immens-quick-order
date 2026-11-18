# Piano: editor mappa "Disposizione" (piani, zone, tavoli)

Spec: `docs/superpowers/specs/2026-11-18-editor-mappa-design.md`
Plugin: `studio-immens-quick-order` (Free, solo frontend operatore)
Target: PHP 7.4, vanilla JS ES5, nessuna dipendenza nuova, versione finale 1.2.0.

## Architettura

- Le card `.fo_table_cell` restano gli elementi reali: la mappa è la griglia con le card spostate in `[data-plan-canvas]` e filtrate per piano con `data-plan-floor`.
- Zoom = `transform: scale(z)` sul canvas; pan = scroll dell'area (`[data-plan-area]`). Coordinate sempre in px del piano: `toPlanCoords()` unica.
- Catena DOM: `[data-plan-area]` (overflow auto) > `[data-plan-stage]` (px = canvas × zoom) > `[data-plan-canvas]` (dimensioni canvas, `transform-origin: 0 0`) > `[data-plan-zones]` + card.
- Piani = taxonomy `siqo_floors` (term meta `_siqo_floor_layout`, `_siqo_floor_order`). Piano virtuale id 0 = tavoli senza termine; canvas in option `siqo_plan_virtual_layout`.
- Zone = taxonomy `siqo_zones` + `_siqo_zone_floor`, `_siqo_zone_bounds`, `_siqo_zone_color`, `_siqo_zone_bg`.
- Il vecchio handler AJAX non viene modificato: `remove_all_actions('wp_ajax_siqo_save_table_layout')` su `init` 20 e registrazione dei nuovi handler (file WIP intoccato).
- Editor JS unico: `public/js/siqo-plan-editor.js`, script inline del template spostato lì. CSS in `public/css/siqo-management.css`.

## Contratti

JSON in `<script type="application/json" id="siqo-plan-data">`:

```json
{
  "ajaxUrl": "/wp-admin/admin-ajax.php",
  "defaults": {"w":180,"h":120,"minW":80,"minH":60,"step":10,"zoomMin":0.3,"zoomMax":2},
  "canvas": {"w":1200,"h":800},
  "floors": [{"id":0,"name":"Piano terra","virtual":true,"free":1,"busy":1,"layout":{"w":1200,"h":800}},
             {"id":31,"name":"Primo piano","virtual":false,"free":2,"busy":0,"layout":{"w":1200,"h":800}}],
  "zones": [{"id":12,"name":"Sala","floor":0,"color":"#a6462f","bg":0,"bgUrl":"","x":40,"y":40,"w":280,"h":180}],
  "strings": {"saved":"Salvato","saving":"Salvataggio…","error":"Salvataggio non riuscito","expired":"Sessione scaduta, ricarica la pagina","zone":"Zona","moveTo":"Sposta i tavoli su","cancel":"Annulla","readonly":"Modifica da computer o tablet"}
}
```

Salvataggio (`siqo_save_table_layout`, campo `layout` JSON):

```json
{"floor_id":0,"canvas":{"w":1200,"h":800},
 "tables":[{"id":560,"x":40,"y":40,"w":180,"h":120,"shape":"rect","seats":4,"zone_id":12}],
 "zones":[{"id":12,"x":40,"y":40,"w":280,"h":180}]}
```

Risposta: `{success:true,data:{saved:N,floor_id:0,canvas:{w,h}}}`. Array legacy `[{id,x,y}]` accettato (trattato come `tables`, piano 0).

Altri endpoint (tutti `wp_ajax_*`, nonce `SIQO_insert_post_ajax_nonce`, gate `SIQO_Management::can('manage_tables')`):

| Azione | Payload | Note |
|---|---|---|
| `siqo_floor_create` | `name`, `copy_from?` | copia solo il canvas |
| `siqo_floor_update` | `id`, `name?`, `order?` | |
| `siqo_floor_delete` | `id`, `move_to?` | 409 con elenco tavoli se non vuoto e senza `move_to`; elimina le zone del piano |
| `siqo_zone_create` | `floor_id`, `name`, `x`, `y`, `w`, `h` | |
| `siqo_zone_update` | `id`, `name?`, `color?`, `bg?` | `bg=0` rimuove |
| `siqo_zone_delete` | `id` | i tavoli restano senza zona |
| `siqo_zone_bg_upload` | `zone_id`, `file` | MIME jpeg/png/webp, max 2 MB |

Regole: id con `absint`, nomi 1–60 char, shape `rect|square|round`, sedie 0–20, min 80×60, canvas 1200×800–4000, posizioni clampate al canvas cresciuto, `zone_id` azzerato se il termine non è una zona del piano. Validazione completa prima di scrivere (400 senza scritture).

## Convenzioni di esecuzione

- Un task = verifica + commit. Fallita una verifica, si corregge e si ripete il task.
- `php -l` sui PHP toccati, `node --check public/js/siqo-plan-editor.js` sul JS.
- Mai `git add -A`; `includes/class-siqo-management.php` contiene un WIP altrui: non toccarlo, non committarlo.
- Stringhe IT, text-domain `studio-immens-quick-order`, token CSS `--siqo-*`.
- Baseline da preservare: 34 ordini, 0 sessioni, 0 print job, 0 stampanti, tavoli 560/580, prodotti 49/347.

Comandi:

```powershell
cd "C:\Users\mauro\Local Sites\local\app\public\wp-content\plugins\studio-immens-quick-order"
php -l includes/class-siqo-plan-layout.php
node --check public/js/siqo-plan-editor.js
php -c C:\Users\mauro\AppData\Local\Temp\opencode\siqo-php.ini C:\Users\mauro\AppData\Local\Temp\opencode\siqo-smoke.php
```

QA browser: `http://localhost:10004/manage-tables/`, `nat`/`nat`, viewport 390×844, 820×1180, 1440×900.

---

## Task 1 — Modello piani/zone e helper canvas

**File:** crea `includes/class-siqo-plan-layout.php`; modifica `includes/class-studio-immens-quick-order.php`.

**Step 1: classe completa**

```php
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
		add_action( 'wp_ajax_siqo_zone_bg_upload', array( __CLASS__, 'ajax_zone_bg_upload' ) );
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
		if ( ( ! empty( $counts[0]['free'] ) || ! empty( $counts[0]['busy'] ) ) || ! $terms ) {
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

	public static function table_zone( $table_id ) {
		$terms = wp_get_object_terms( (int) $table_id, self::ZONE_TAX, array( 'fields' => 'ids' ) );
		return ( is_wp_error( $terms ) || ! $terms ) ? 0 : (int) $terms[0];
	}

	public static function zone_floor( $zone_id ) {
		return (int) get_term_meta( (int) $zone_id, '_siqo_zone_floor', true );
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
```

**Step 2: bootstrap**

In `includes/class-studio-immens-quick-order.php`, in `load_dependencies()` dopo il require di `class-siqo-table-sessions.php`:

```php
		require_once plugin_dir_path( __FILE__ ) . 'class-siqo-plan-layout.php';
```

Nel costruttore, dopo `SIQO_Table_Sessions::init();`:

```php
		SIQO_Plan_Layout::init();
```

**Step 3: verifica**

```powershell
php -l includes/class-siqo-plan-layout.php
php -c C:\Users\mauro\AppData\Local\Temp\opencode\siqo-php.ini C:\Users\mauro\AppData\Local\Temp\opencode\siqo-smoke.php
```

Apri `/manage-tables/`: nessun errore PHP, `floors` nell'HTML non esiste ancora (Task 2). In wp-cli/console: `get_terms(['taxonomy'=>'siqo_floors','hide_empty'=>false])` → `[]` senza errori; `SIQO_Plan_Layout::floors()` non deve emettere warning.

**Step 4: commit**

```powershell
git add includes/class-siqo-plan-layout.php includes/class-studio-immens-quick-order.php
git commit -m "feat: add floor and zone model for plan editor"
```

---

## Task 2 — Markup: barra, tab piani, stage/canvas, dati JSON

**File:** `public/template/manage-tables.php`, `public/css/siqo-management.css`.

**Step 1: barra**

Dentro `.siqo-plan-toolbar` (dopo i bottoni vista esistenti) aggiungi:

```php
		<div class="siqo-plan-views" role="group" aria-label="<?php esc_attr_e( 'Vista', 'studio-immens-quick-order' ); ?>">
			<button type="button" data-plan-view="grid" class="is-active" aria-pressed="true"><?php esc_html_e( 'Griglia', 'studio-immens-quick-order' ); ?></button>
			<button type="button" data-plan-view="plan" aria-pressed="false"><?php esc_html_e( 'Disposizione', 'studio-immens-quick-order' ); ?></button>
		</div>
		<button type="button" class="siqo-plan-edit" data-plan-edit aria-pressed="false"><?php esc_html_e( 'Modifica disposizione', 'studio-immens-quick-order' ); ?></button>
		<button type="button" class="siqo-plan-manage" data-plan-manage aria-expanded="false" aria-controls="siqo-plan-manager"><?php esc_html_e( 'Gestisci', 'studio-immens-quick-order' ); ?></button>
		<div class="siqo-plan-zoom" role="group" aria-label="<?php esc_attr_e( 'Zoom', 'studio-immens-quick-order' ); ?>">
			<button type="button" data-plan-zoom="out" aria-label="<?php esc_attr_e( 'Riduci zoom', 'studio-immens-quick-order' ); ?>">&minus;</button>
			<span data-plan-zoom-value>100%</span>
			<button type="button" data-plan-zoom="in" aria-label="<?php esc_attr_e( 'Aumenta zoom', 'studio-immens-quick-order' ); ?>">+</button>
			<button type="button" data-plan-zoom="fit"><?php esc_html_e( 'Adatta', 'studio-immens-quick-order' ); ?></button>
		</div>
		<button type="button" data-plan-reload><?php esc_html_e( 'Ricarica disposizione', 'studio-immens-quick-order' ); ?></button>
		<span class="siqo-plan-status" data-plan-status role="status" aria-live="polite"></span>
		<div class="siqo-plan-tabs" data-plan-tabs role="tablist" aria-label="<?php esc_attr_e( 'Piani', 'studio-immens-quick-order' ); ?>"></div>
		<p class="siqo-plan-hint" data-plan-hint hidden><?php esc_html_e( 'Modifica da computer o tablet', 'studio-immens-quick-order' ); ?></p>
```

**Step 2: area piano**

Sostituisci il contenitore della disposizione con:

```php
		<div class="siqo-plan-area" data-plan-area>
			<div class="siqo-plan-stage" data-plan-stage>
				<div class="siqo-plan-canvas" data-plan-canvas>
					<div class="siqo-plan-zones" data-plan-zones aria-live="off"></div>
				</div>
			</div>
		</div>
		<div class="siqo-plan-inspector" data-plan-inspector hidden></div>
		<div class="siqo-plan-manager" id="siqo-plan-manager" data-plan-manager hidden></div>
		<input type="file" accept="image/jpeg,image/png,image/webp" data-plan-file hidden>
```

**Step 3: card**

Sulla card `.fo_table_cell` aggiungi gli attributi (usando i valori già letti nel template):

```php
			data-plan-floor="<?php echo esc_attr( $floor_id ); ?>"
			data-plan-zone="<?php echo esc_attr( $zone_id ); ?>"
			data-plan-shape="<?php echo esc_attr( $shape ); ?>"
			data-plan-seats="<?php echo esc_attr( $seats ); ?>"
			data-plan-x="<?php echo esc_attr( $pos['x'] ); ?>"
			data-plan-y="<?php echo esc_attr( $pos['y'] ); ?>"
			data-plan-w="<?php echo esc_attr( $w ); ?>"
			data-plan-h="<?php echo esc_attr( $h ); ?>"
```

dove `$floor_id = SIQO_Plan_Layout::table_floor( $id )`, `$zone_id = SIQO_Plan_Layout::table_zone( $id )`, `$shape = get_post_meta( $id, '_siqo_plan_shape', true ) ?: 'rect'`, `$seats = (int) get_post_meta( $id, '_siqo_plan_seats', true )`, `$pos` con default 40/40, `$w/$h` da `_siqo_plan_position` con default 180/120. Calcola questi valori nel loop delle card, prima dell'`echo`.

**Step 4: JSON dati**

Prima di `</div>` finale della sezione:

```php
		<?php
		$siqo_plan_data = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'defaults' => array( 'w' => 180, 'h' => 120, 'minW' => 80, 'minH' => 60, 'step' => 10, 'zoomMin' => 0.3, 'zoomMax' => 2 ),
			'canvas'   => SIQO_Plan_Layout::floor_layout( 0 ),
			'floors'   => SIQO_Plan_Layout::floors(),
			'zones'    => SIQO_Plan_Layout::zones(),
			'strings'  => array(
				'saving'   => __( 'Salvataggio…', 'studio-immens-quick-order' ),
				'saved'    => __( 'Salvato', 'studio-immens-quick-order' ),
				'error'    => __( 'Salvataggio non riuscito', 'studio-immens-quick-order' ),
				'expired'  => __( 'Sessione scaduta, ricarica la pagina', 'studio-immens-quick-order' ),
				'zone'     => __( 'Zona', 'studio-immens-quick-order' ),
				'moveTo'   => __( 'Sposta i tavoli su', 'studio-immens-quick-order' ),
				'cancel'   => __( 'Annulla', 'studio-immens-quick-order' ),
				'readonly' => __( 'Modifica da computer o tablet', 'studio-immens-quick-order' ),
			),
		);
		?>
		<script type="application/json" id="siqo-plan-data">
			<?php echo wp_json_encode( $siqo_plan_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
		</script>
```

Aggiungi il nonce in un campo hidden se non esiste già: `<input type="hidden" data-plan-nonce value="<?php echo esc_attr( wp_create_nonce( 'SIQO_insert_post_ajax_nonce' ) ); ?>">`.

**Step 5: CSS base**

In coda a `public/css/siqo-management.css`:

```css
.siqo-plan-views { display: inline-flex; gap: 4px; }
.siqo-plan-views button,
.siqo-plan-zoom button,
.siqo-plan-edit,
.siqo-plan-manage,
[data-plan-reload] {
	border: 1px solid #d8cbbd;
	border-radius: 8px;
	background: #fff;
	padding: 8px 12px;
	font: inherit;
	font-size: 12px;
	font-weight: 700;
	cursor: pointer;
}
.siqo-plan-views button.is-active,
.siqo-plan-edit[aria-pressed="true"] { background: #a6462f; border-color: #a6462f; color: #fff; }
.siqo-plan-toolbar button:focus-visible,
.siqo-plan-tabs button:focus-visible,
.siqo-plan-area :focus-visible { outline: 2px solid #1f6feb; outline-offset: 2px; }
.siqo-plan-status { font-size: 12px; color: #6b5d50; min-width: 110px; }
.siqo-plan-status.is-error { color: #a6462f; font-weight: 700; }
.siqo-plan-tabs { display: flex; gap: 6px; overflow-x: auto; padding: 4px 0; }
.siqo-plan-tabs button { white-space: nowrap; border: 1px solid #d8cbbd; background: #fff; border-radius: 999px; padding: 6px 12px; font: inherit; font-size: 12px; cursor: pointer; }
.siqo-plan-tabs button[aria-selected="true"] { background: #2f2a26; color: #fff; border-color: #2f2a26; }
.siqo-plan-tabs .siqo-plan-count { font-size: 11px; opacity: .75; margin-left: 4px; }
.siqo-plan-hint { margin: 4px 0 0; font-size: 12px; color: #6b5d50; }
.siqo-plan-area { position: relative; overflow: auto; max-height: 72vh; background: #f4efe8; border: 1px solid #e2d8ca; border-radius: 12px; }
.siqo-plan-stage { position: relative; }
.siqo-plan-canvas { position: absolute; top: 0; left: 0; transform-origin: 0 0; background: #fff; box-shadow: 0 1px 0 #e2d8ca inset; }
.siqo-plan-canvas::before { content: ""; position: absolute; inset: 0; background-image: linear-gradient(#f0e9df 1px, transparent 1px), linear-gradient(90deg, #f0e9df 1px, transparent 1px); background-size: 50px 50px; pointer-events: none; }
.siqo-plan-zones { position: absolute; inset: 0; z-index: 0; }
.siqo-plan-canvas .fo_table_cell { position: absolute; margin: 0; z-index: 1; }
.siqo-plan-canvas .fo_table_cell.is-other-floor { display: none !important; }
.siqo-plan-canvas .fo_table_cell.is-selected { outline: 3px solid #1f6feb; outline-offset: 2px; }
.siqo-plan-canvas.is-editing .fo_table_cell { cursor: grab; touch-action: none; }
.siqo-plan-canvas.is-editing .fo_table_cell.is-dragging { cursor: grabbing; will-change: transform; }
.siqo-plan-canvas.is-readonly .siqo-plan-zone { pointer-events: none; }
.siqo-plan-seats { position: absolute; right: 6px; bottom: 4px; font-size: 10px; font-weight: 700; opacity: .8; pointer-events: none; }
.siqo-plan-card-round { border-radius: 50% !important; }
```

**Step 6: verifica**

- `/manage-tables/` risponde 200, console pulita, `#siqo-plan-data` contiene `floors` e `zones` validi (`JSON.parse`).
- I bottoni non fanno nulla (JS in Task 5): nessun errore.
- Layout invariato in griglia.

**Step 7: commit**

```powershell
git add public/template/manage-tables.php public/css/siqo-management.css
git commit -m "feat: plan editor markup with floors, zones and zoom toolbar"
```

---

## Task 3 — Endpoint di salvataggio validato (tavoli, zone, canvas)

**File:** `includes/class-siqo-plan-layout.php`.

**Step 1: helper**

```php
	public static function int_field( $item, $key, $default = 0 ) {
		return isset( $item[ $key ] ) ? (int) $item[ $key ] : $default;
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
```

**Step 2: handler**

```php
	public static function ajax_save_layout() {
		self::guard();

		$raw  = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '';
		$data = json_decode( (string) $raw, true );
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
			if ( ! $table['legacy'] || isset( $table['w'] ) ) {
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
```

**Step 3: verifica**

Console del browser su `/manage-tables/`:

```js
fetch(siqoPlanData.ajaxUrl, {method:'POST', credentials:'same-origin', body:new URLSearchParams({action:'siqo_save_table_layout', nonce:document.querySelector('[data-plan-nonce]').value, layout:JSON.stringify({floor_id:0, canvas:{w:1200,h:800}, tables:[{id:560,x:60,y:70}], zones:[]})})}).then(r=>r.json()).then(console.log)
```

- Risposta `{success:true, data:{saved:1, floor_id:0, canvas:{...}}}`.
- `wp post meta get 560 _siqo_plan_position --format=json` → `{"x":60,"y":70,"w":180,"h":120}`.
- Con `layout` non JSON → 400 e meta invariati; con `id` di un ordine → 400.
- Ripristina la posizione originale del tavolo 560 a fine test.
- Verifica che il salvataggio legacy funzioni: `layout: JSON.stringify([{id:560,x:40,y:40}])` → 200 e forma/sedie/piano invariati.

**Step 4: commit**

```powershell
git add includes/class-siqo-plan-layout.php
git commit -m "feat: validated layout save endpoint with canvas growth"
```

---

## Task 4 — Endpoint CRUD piani

**File:** `includes/class-siqo-plan-layout.php`.

**Step 1: helper ordine**

```php
	public static function next_floor_order() {
		$max = 0;
		$terms = get_terms( array( 'taxonomy' => self::FLOOR_TAX, 'hide_empty' => false ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		foreach ( $terms as $term ) {
			$order = (int) get_term_meta( $term->term_id, '_siqo_floor_order', true );
			$max   = max( $max, $order ? $order : (int) $term->term_id );
		}
		return $max + 1;
	}
```

**Step 2: create/update/delete**

```php
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
				$list[] = array( 'id' => $table_id, 'name' => get_the_title( $table_id ) );
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
```

**Step 3: verifica**

Console su `/manage-tables/` (helper: `function p(action, extra){var b=new URLSearchParams({action:action, nonce:document.querySelector('[data-plan-nonce]').value}); Object.keys(extra||{}).forEach(function(k){b.append(k,extra[k]);}); return fetch(siqoPlanData.ajaxUrl,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json();});}`):

- `p('siqo_floor_create',{name:'Primo piano'})` → 200, tab presente (dopo Task 5) e term creato.
- `p('siqo_floor_create',{name:'Primo piano'})` → 400 nome duplicato.
- `p('siqo_floor_update',{id:ID,name:'Piano 1',order:50})` → 200.
- `p('siqo_floor_delete',{id:ID})` con un tavolo assegnato → 409 con `tables`.
- `p('siqo_floor_delete',{id:ID,move_to:0})` → 200, tavoli su piano 0, zone del piano eliminate.
- Elimina a fine test i piani creati e riporta i tavoli su piano 0.

**Step 4: commit**

```powershell
git add includes/class-siqo-plan-layout.php
git commit -m "feat: floor create update delete endpoints"
```

---

## Task 5 — Editor JS: vista, piani, zoom/pan, drag, salvataggio con rollback

**File:** crea `public/js/siqo-plan-editor.js`; modifica `public/template/manage-tables.php`.

**Step 1: prepara il template**

- Aggiungi `data-plan-grid` al contenitore della griglia dei tavoli (quello che oggi viene nascosto quando si apre la mappa).
- Aggiungi `data-plan-nonce` all'input hidden del nonce se manca.
- Sposta il contenuto dello script inline (righe ~220-461) dentro `siqo-plan-editor.js`, sezione `/* legacy */` in cima, **senza modificarlo**: contiene le funzioni globali usate dagli `onclick` delle card (`SIQO_tab_Card_show`, ecc.). Verifica che ogni `onclick="X("` del template abbia una `function X` nel file: `grep -o 'onclick="[A-Za-z_]*' public/template/manage-tables.php`.
- Dal blocco legacy rimuovi solo: la funzione `setView`, i listener di drag della mappa e la lettura/scrittura della vista in `localStorage` (li sostituisce il nuovo codice). Le funzioni dell'ordine restano identiche.

**Step 2: file completo**

```js
(function () {
	'use strict';

	var dataEl = document.getElementById('siqo-plan-data');
	if (!dataEl) { return; }
	var data;
	try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

	var area = document.querySelector('[data-plan-area]');
	var stage = document.querySelector('[data-plan-stage]');
	var canvasEl = document.querySelector('[data-plan-canvas]');
	var zonesEl = document.querySelector('[data-plan-zones]');
	var gridEl = document.querySelector('[data-plan-grid]');
	var tabsEl = document.querySelector('[data-plan-tabs]');
	var statusEl = document.querySelector('[data-plan-status]');
	var hintEl = document.querySelector('[data-plan-hint]');
	var nonceEl = document.querySelector('[data-plan-nonce]');
	if (!area || !stage || !canvasEl || !tabsEl) { return; }

	var nonce = nonceEl ? nonceEl.value : '';
	var D = data.defaults;
	var state = {
		view: 'grid', floor: 0, editing: false, zoom: 1,
		canvas: data.canvas, floors: data.floors, zones: data.zones,
		selected: null, selectedZone: null, snapshot: null,
		saveTimer: null, statusTimer: null, busy: false
	};
	var cards = [];
	var mobile = window.matchMedia('(max-width: 767px)');
	var wide = window.matchMedia('(max-width: 1023px)');

	function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
	function num(v, d) { var n = parseInt(v, 10); return isNaN(n) ? d : n; }
	function pad(n) { return n < 10 ? '0' + n : String(n); }
	function store(key, value) { try { window.localStorage.setItem(key, String(value)); } catch (e) {} }
	function read(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
	function S(key) { return (data.strings && data.strings[key]) || key; }

	function setStatus(text, isError) {
		if (!statusEl) { return; }
		statusEl.textContent = text;
		statusEl.className = 'siqo-plan-status' + (isError ? ' is-error' : '');
		window.clearTimeout(state.statusTimer);
		if (text && !isError) {
			state.statusTimer = window.setTimeout(function () { statusEl.textContent = ''; }, 4000);
		}
	}

	function applyCanvas() {
		var w = state.canvas.w, h = state.canvas.h, z = state.zoom;
		canvasEl.style.width = w + 'px';
		canvasEl.style.height = h + 'px';
		stage.style.width = Math.round(w * z) + 'px';
		stage.style.height = Math.round(h * z) + 'px';
		canvasEl.style.transform = 'scale(' + z + ')';
		var label = document.querySelector('[data-plan-zoom-value]');
		if (label) { label.textContent = Math.round(z * 100) + '%'; }
	}

	function setZoom(z) {
		state.zoom = clamp(Math.round(z * 10) / 10, D.zoomMin, D.zoomMax);
		applyCanvas();
		store('siqo_plan_zoom', state.zoom);
	}

	function fitZoom() {
		var w = area.clientWidth - 24, h = area.clientHeight - 24;
		if (w <= 0 || h <= 0) { return; }
		var z = Math.min(w / state.canvas.w, h / state.canvas.h);
		setZoom(clamp(z, D.zoomMin, D.zoomMax));
		area.scrollLeft = 0;
		area.scrollTop = 0;
	}

	function floorById(id) {
		for (var i = 0; i < state.floors.length; i++) {
			if (Number(state.floors[i].id) === Number(id)) { return state.floors[i]; }
		}
		return null;
	}

	function renderTabs() {
		tabsEl.innerHTML = '';
		state.floors.forEach(function (floor) {
			var button = document.createElement('button');
			button.type = 'button';
			button.setAttribute('role', 'tab');
			button.setAttribute('aria-selected', Number(floor.id) === state.floor ? 'true' : 'false');
			button.setAttribute('aria-label', floor.name + ': ' + floor.free + ' liberi, ' + floor.busy + ' occupati');
			button.setAttribute('data-floor', String(floor.id));
			if (state.floors.length > 1) {
				button.tabIndex = Number(floor.id) === state.floor ? 0 : -1;
			}
			button.innerHTML = '<span></span><span class="siqo-plan-count">' + floor.free + '/' + floor.busy + '</span>';
			button.firstChild.textContent = floor.name;
			tabsEl.appendChild(button);
		});
	}

	function setFloor(id, silent) {
		if (!floorById(id)) {
			id = state.floors.length ? state.floors[0].id : 0;
		}
		state.floor = Number(id);
		var floor = floorById(state.floor);
		state.canvas = floor && floor.layout ? { w: floor.layout.w, h: floor.layout.h } : { w: 1200, h: 800 };
		store('siqo_plan_floor', state.floor);
		renderTabs();
		if (state.selected) { selectCard(null); }
		if (state.selectedZone) { selectZone(null); }
		applyCanvas();
		renderCards();
		if (typeof renderZones === 'function') { renderZones(); }
		if (!silent) { area.scrollLeft = 0; area.scrollTop = 0; }
	}

	function setView(view) {
		state.view = view === 'plan' ? 'plan' : 'grid';
		store('siqo_plan_view', state.view);
		document.querySelectorAll('[data-plan-view]').forEach(function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-plan-view') === state.view);
			b.setAttribute('aria-pressed', b.getAttribute('data-plan-view') === state.view ? 'true' : 'false');
		});
		area.hidden = state.view !== 'plan';
		if (gridEl) { gridEl.hidden = state.view === 'plan'; }
		if (state.view === 'plan') {
			cards.forEach(function (el) { canvasEl.appendChild(el); });
			applyCanvas();
			renderCards();
			if (typeof renderZones === 'function') { renderZones(); }
			window.setTimeout(fitZoom, 30);
		} else {
			cards.forEach(function (el) { if (el._home) { el._home.appendChild(el); } });
		}
	}

	function setEditing(on) {
		state.editing = !!on && !mobile.matches;
		canvasEl.classList.toggle('is-editing', state.editing);
		canvasEl.classList.toggle('is-readonly', !state.editing);
		document.querySelectorAll('[data-plan-edit]').forEach(function (b) {
			b.setAttribute('aria-pressed', state.editing ? 'true' : 'false');
			b.textContent = state.editing ? 'Fine modifica' : 'Modifica disposizione';
		});
		if (hintEl) { hintEl.hidden = !mobile.matches; }
		if (!state.editing) {
			selectCard(null);
			selectZone(null);
			if (managerEl) { managerEl.hidden = true; }
		}
	}

	function cardData(el) {
		return {
			id: num(el.getAttribute('data-table-id'), 0),
			x: num(el.getAttribute('data-plan-x'), 40),
			y: num(el.getAttribute('data-plan-y'), 40),
			w: num(el.getAttribute('data-plan-w'), D.w),
			h: num(el.getAttribute('data-plan-h'), D.h),
			shape: el.getAttribute('data-plan-shape') || 'rect',
			seats: num(el.getAttribute('data-plan-seats'), 0),
			zone: num(el.getAttribute('data-plan-zone'), 0),
			floor: num(el.getAttribute('data-plan-floor'), 0)
		};
	}

	function writeCard(el, c) {
		el.setAttribute('data-plan-x', c.x);
		el.setAttribute('data-plan-y', c.y);
		el.setAttribute('data-plan-w', c.w);
		el.setAttribute('data-plan-h', c.h);
		el.setAttribute('data-plan-shape', c.shape);
		el.setAttribute('data-plan-seats', c.seats);
		el.setAttribute('data-plan-zone', c.zone);
		el.setAttribute('data-plan-floor', c.floor);
		el.style.left = c.x + 'px';
		el.style.top = c.y + 'px';
		el.style.width = c.w + 'px';
		el.style.height = c.h + 'px';
		el.classList.toggle('siqo-plan-card-round', c.shape === 'round');
		if (typeof renderSeats === 'function') { renderSeats(el, c.seats); }
	}

	function renderCards() {
		cards.forEach(function (el) {
			var c = cardData(el);
			c.floor = num(el.getAttribute('data-plan-floor'), 0);
			el.classList.toggle('is-other-floor', c.floor !== state.floor);
			writeCard(el, c);
		});
	}

	function buildLayout() {
		var tables = [], zones = [];
		cards.forEach(function (el) {
			var c = cardData(el);
			tables.push({ id: c.id, x: c.x, y: c.y, w: c.w, h: c.h, shape: c.shape, seats: c.seats, zone_id: c.zone });
		});
		state.zones.forEach(function (z) {
			zones.push({ id: z.id, x: z.x, y: z.y, w: z.w, h: z.h });
		});
		return { floor_id: state.floor, canvas: { w: state.canvas.w, h: state.canvas.h }, tables: tables, zones: zones };
	}

	function captureSnapshot() {
		return JSON.stringify(buildLayout());
	}

	function restoreSnapshot(json) {
		var snap;
		try { snap = JSON.parse(json); } catch (e) { return; }
		state.canvas = snap.canvas;
		cards.forEach(function (el) {
			var c = cardData(el);
			for (var i = 0; i < snap.tables.length; i++) {
				if (Number(snap.tables[i].id) === c.id) {
					var t = snap.tables[i];
					c.x = t.x; c.y = t.y; c.w = t.w; c.h = t.h; c.shape = t.shape; c.seats = t.seats; c.zone = t.zone_id;
					writeCard(el, c);
				}
			}
		});
		state.zones = snap.zones.map(function (z) {
			var prev = null;
			for (var i = 0; i < state.zones.length; i++) {
				if (Number(state.zones[i].id) === Number(z.id)) { prev = state.zones[i]; }
			}
			return prev ? Object.assign({}, prev, z) : z;
		});
		applyCanvas();
		if (typeof renderZones === 'function') { renderZones(); }
	}

	function post(action, params, json) {
		var body = new window.FormData();
		body.append('action', action);
		body.append('nonce', nonce);
		Object.keys(params || {}).forEach(function (key) { body.append(key, params[key]); });
		if (json) { body.append('layout', JSON.stringify(json)); }
		return window.fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function save() {
		window.clearTimeout(state.saveTimer);
		state.saveTimer = window.setTimeout(doSave, 500);
	}

	function doSave() {
		if (state.busy) { return; }
		state.busy = true;
		var snapshot = state.snapshot || captureSnapshot();
		var payload = buildLayout();
		setStatus(S('saving'), false);
		post('siqo_save_table_layout', {}, payload).then(function (result) {
			if (result && result.success) {
				state.snapshot = null;
				state.canvas = result.data.canvas || state.canvas;
				if (result.data.floor_id !== undefined) { state.floor = Number(result.data.floor_id); }
				applyCanvas();
				var now = new Date();
				setStatus(S('saved') + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()), false);
			} else {
				restoreSnapshot(snapshot);
				var msg = (result && result.data && result.data.message) || S('error');
				setStatus(result && result.data && result.data.code === 'expired' ? S('expired') : msg, true);
			}
		}).catch(function () {
			restoreSnapshot(snapshot);
			setStatus(S('error'), true);
		}).then(function () {
			state.busy = false;
		});
	}

	function toPlanCoords(clientX, clientY) {
		var rect = canvasEl.getBoundingClientRect();
		return { x: (clientX - rect.left) / state.zoom, y: (clientY - rect.top) / state.zoom };
	}

	function beginOp() { state.snapshot = captureSnapshot(); }

	function selectCard(el) {
		if (state.selected) { state.selected.classList.remove('is-selected'); }
		state.selected = el || null;
		if (state.selected) {
			state.selected.classList.add('is-selected');
			if (state.editing) { state.selected.scrollIntoView({ block: 'nearest' }); }
		}
	}

	cards = Array.prototype.slice.call(document.querySelectorAll('.fo_table_cell'));
	cards.forEach(function (el) { el._home = el.parentNode; });
	document.querySelectorAll('[data-plan-view]').forEach(function (b) {
		b.addEventListener('click', function () { setView(b.getAttribute('data-plan-view')); });
	});
	document.querySelectorAll('[data-plan-edit]').forEach(function (b) {
		b.addEventListener('click', function () { setEditing(!state.editing); });
	});
	tabsEl.addEventListener('click', function (event) {
		var button = event.target.closest('[data-floor]');
		if (button) { setFloor(button.getAttribute('data-floor')); }
	});
	document.querySelectorAll('[data-plan-zoom]').forEach(function (button) {
		button.addEventListener('click', function () {
			var mode = button.getAttribute('data-plan-zoom');
			if (mode === 'in') { setZoom(state.zoom + 0.1); }
			if (mode === 'out') { setZoom(state.zoom - 0.1); }
			if (mode === 'fit') { fitZoom(); }
		});
	});
	var reload = document.querySelector('[data-plan-reload]');
	if (reload) { reload.addEventListener('click', function () { window.location.reload(); }); }

	area.addEventListener('wheel', function (event) {
		if (!event.ctrlKey && !event.metaKey) { return; }
		event.preventDefault();
		setZoom(state.zoom + (event.deltaY < 0 ? 0.1 : -0.1));
	}, { passive: false });

	canvasEl.addEventListener('pointerdown', function (event) {
		if (!state.editing || event.button !== 0) { return; }
		var card = event.target.closest('.fo_table_cell');
		if (card) {
			if (event.target.closest('.siqo-plan-handle')) { return; }
			beginOp();
			selectCard(card);
			var start = toPlanCoords(event.clientX, event.clientY);
			var origin = cardData(card);
			var moved = false;
			card.classList.add('is-dragging');
			card.setPointerCapture(event.pointerId);
			function move(ev) {
				var point = toPlanCoords(ev.clientX, ev.clientY);
				var dx = point.x - start.x, dy = point.y - start.y;
				if (Math.abs(dx) > 2 || Math.abs(dy) > 2) { moved = true; }
				var c = cardData(card);
				c.x = Math.max(0, origin.x + dx);
				c.y = Math.max(0, origin.y + dy);
				c.x = Math.round(c.x / D.step) * D.step;
				c.y = Math.round(c.y / D.step) * D.step;
				var needW = c.x + c.w + 40, needH = c.y + c.h + 40;
				if (needW > state.canvas.w) { state.canvas.w = Math.min(4000, needW); }
				if (needH > state.canvas.h) { state.canvas.h = Math.min(4000, needH); }
				writeCard(card, c);
				applyCanvas();
			}
			function up() {
				card.classList.remove('is-dragging');
				card.removeEventListener('pointermove', move);
				card.removeEventListener('pointerup', up);
				card.removeEventListener('pointercancel', up);
				if (moved) { save(); }
			}
			card.addEventListener('pointermove', move);
			card.addEventListener('pointerup', up);
			card.addEventListener('pointercancel', up);
			event.preventDefault();
			return;
		}
		if (state.editing) { selectCard(null); }
	});

	var pan = null;
	area.addEventListener('pointerdown', function (event) {
		if (event.target.closest('.fo_table_cell') || event.target.closest('.siqo-plan-zone')) { return; }
		if (!state.editing && !state.view) { return; }
		pan = { x: event.clientX, y: event.clientY, left: area.scrollLeft, top: area.scrollTop };
		area.setPointerCapture(event.pointerId);
		area.style.cursor = 'grabbing';
	});
	area.addEventListener('pointermove', function (event) {
		if (!pan) { return; }
		area.scrollLeft = pan.left - (event.clientX - pan.x);
		area.scrollTop = pan.top - (event.clientY - pan.y);
	});
	area.addEventListener('pointerup', function () { pan = null; area.style.cursor = ''; });
	area.addEventListener('pointercancel', function () { pan = null; area.style.cursor = ''; });

	var pinch = null;
	area.addEventListener('touchstart', function (event) {
		if (event.touches.length !== 2) { return; }
		var dx = event.touches[0].clientX - event.touches[1].clientX;
		var dy = event.touches[0].clientY - event.touches[1].clientY;
		pinch = { dist: Math.sqrt(dx * dx + dy * dy), zoom: state.zoom };
	}, { passive: true });
	area.addEventListener('touchmove', function (event) {
		if (!pinch || event.touches.length !== 2) { return; }
		var dx = event.touches[0].clientX - event.touches[1].clientX;
		var dy = event.touches[0].clientY - event.touches[1].clientY;
		var dist = Math.sqrt(dx * dx + dy * dy);
		setZoom(pinch.zoom * dist / pinch.dist);
	}, { passive: true });
	area.addEventListener('touchend', function () { pinch = null; });

	if (mobile.matches) { setEditing(false); }
	mobile.addEventListener('change', function () { setEditing(state.editing); });

	setView(read('siqo_plan_view') === 'plan' ? 'plan' : 'grid');
	setZoom(num(read('siqo_plan_zoom'), 1));
	setFloor(num(read('siqo_plan_floor'), state.floors.length ? state.floors[0].id : 0), true);
	setEditing(false);

	window.SIQO_plan = {
		state: state, cards: cards, save: save, beginOp: beginOp, captureSnapshot: captureSnapshot,
		restoreSnapshot: restoreSnapshot, post: post, setFloor: setFloor, selectCard: selectCard,
		renderTabs: renderTabs, applyCanvas: applyCanvas, setStatus: setStatus, writeCard: writeCard,
		cardData: cardData, toPlanCoords: toPlanCoords, floorById: floorById, clamp: clamp
	};
})();
```

**Step 3: verifiche**

- `node --check public/js/siqo-plan-editor.js`.
- `/manage-tables/`: la vista salvata viene ripristinata; switch griglia/piano; tab piani con `liberi/occupati`; zoom 30–200% a passi 10, `Adatta`; pan con drag sullo sfondo; drag tavolo con snap e growth del canvas; `Salvato HH:MM` e sparizione dopo 4s.
- Forza errore (offline) → posizioni tornano allo snapshot e status rosso.
- Nessun errore console; le funzioni legacy dell'ordine continuano a funzionare (`SIQO_tab_Card_show` da onclick).
- A 390px la modifica è disattivata e il pinch funziona.

**Step 4: commit**

```powershell
git add public/js/siqo-plan-editor.js public/template/manage-tables.php
git commit -m "feat: plan editor core with floors, zoom, pan and rollback"
```

---

## Task 6 — Resize, sedie, ispettore tavolo, tastiera

**File:** `public/js/siqo-plan-editor.js`, `public/css/siqo-management.css`.

**Step 1: sedie e forma**

Dopo `writeCard()`:

```js
	function renderSeats(el, seats) {
		var badge = el.querySelector('.siqo-plan-seats');
		if (!seats) {
			if (badge) { badge.parentNode.removeChild(badge); }
			return;
		}
		if (!badge) {
			badge = document.createElement('span');
			badge.className = 'siqo-plan-seats';
			badge.setAttribute('aria-hidden', 'true');
			el.appendChild(badge);
		}
		badge.textContent = seats + ' posti';
	}

	function selectCard(el) {
		var previous = state.selected;
		if (previous) {
			previous.classList.remove('is-selected');
			removeHandles(previous);
		}
		state.selected = el || null;
		if (state.selected) {
			state.selected.classList.add('is-selected');
			if (state.editing) {
				addHandles(state.selected);
				buildTableInspector(state.selected);
			}
		} else {
			hideInspector();
		}
	}

	function removeHandles(el) {
		el.querySelectorAll('.siqo-plan-handle').forEach(function (h) { h.parentNode.removeChild(h); });
	}

	function addHandles(el) {
		removeHandles(el);
		var name = el.getAttribute('data-table-name') || 'tavolo';
		['se', 'e', 's'].forEach(function (dir) {
			var handle = document.createElement('span');
			handle.className = 'siqo-plan-handle siqo-plan-handle-' + dir;
			handle.setAttribute('data-plan-handle', dir);
			handle.setAttribute('aria-label', 'Ridimensiona ' + name);
			handle.setAttribute('role', 'button');
			handle.setAttribute('tabindex', '-1');
			el.appendChild(handle);
		});
	}
```

**Step 2: resize**

Aggiungi in cima al `pointerdown` del canvas (prima di `closest('.fo_table_cell')`):

```js
		var handle = event.target.closest('[data-plan-handle]');
		if (handle) {
			var owner = event.target.closest('.fo_table_cell');
			beginOp();
			selectCard(owner);
			var dir = handle.getAttribute('data-plan-handle');
			var origin = cardData(owner);
			var start = toPlanCoords(event.clientX, event.clientY);
			function onMove(ev) {
				var p = toPlanCoords(ev.clientX, ev.clientY);
				var c = cardData(owner);
				if (dir === 'se' || dir === 'e') { c.w = origin.w + (p.x - start.x); }
				if (dir === 'se' || dir === 's') { c.h = origin.h + (p.y - start.y); }
				c.w = Math.max(D.minW, Math.round(c.w / D.step) * D.step);
				c.h = Math.max(D.minH, Math.round(c.h / D.step) * D.step);
				if (c.shape !== 'rect') {
					var side = Math.max(D.minW, Math.min(c.w, c.h));
					c.w = side; c.h = side;
				}
				var needW = c.x + c.w + 40, needH = c.y + c.h + 40;
				if (needW > state.canvas.w) { state.canvas.w = Math.min(4000, needW); }
				if (needH > state.canvas.h) { state.canvas.h = Math.min(4000, needH); }
				writeCard(owner, c);
				applyCanvas();
			}
			function onUp() {
				owner.removeEventListener('pointermove', onMove);
				owner.removeEventListener('pointerup', onUp);
				owner.removeEventListener('pointercancel', onUp);
				save();
			}
			owner.setPointerCapture(event.pointerId);
			owner.addEventListener('pointermove', onMove);
			owner.addEventListener('pointerup', onUp);
			owner.addEventListener('pointercancel', onUp);
			event.preventDefault();
			event.stopPropagation();
			return;
		}
```

**Step 3: ispettore tavolo**

```js
	function hideInspector() {
		if (inspectorEl) { inspectorEl.hidden = true; inspectorEl.innerHTML = ''; }
	}

	function zoneOptions(selected) {
		var html = '<option value="0">Nessuna</option>';
		state.zones.forEach(function (z) {
			if (Number(z.floor) !== state.floor) { return; }
			html += '<option value="' + z.id + '"' + (Number(selected) === Number(z.id) ? ' selected' : '') + '>' + esc(z.name) + '</option>';
		});
		return html;
	}

	function esc(text) {
		return String(text).replace(/[&<>"]/g, function (ch) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
		});
	}

	function buildTableInspector(card) {
		if (!inspectorEl) { return; }
		var c = cardData(card);
		inspectorEl.innerHTML =
			'<div class="siqo-plan-inspector-head"><strong></strong>' +
			'<button type="button" data-plan-close aria-label="Chiudi">&times;</button></div>' +
			'<label class="siqo-plan-field">Forma<select data-plan-shape><option value="rect">Rettangolo</option><option value="square">Quadrato</option><option value="round">Rotondo</option></select></label>' +
			'<label class="siqo-plan-field">Sedie<span class="siqo-plan-stepper"><button type="button" data-seats="-" aria-label="Meno sedie">&minus;</button><output data-seats-value></output><button type="button" data-seats="+" aria-label="Più sedie">+</button></span></label>' +
			'<label class="siqo-plan-field">Zona<select data-plan-zone-list>' + zoneOptions(c.zone) + '</select></label>' +
			'<p class="siqo-plan-inspector-meta"></p>';
		inspectorEl.hidden = false;
		inspectorEl.querySelector('.siqo-plan-inspector-head strong').textContent = card.getAttribute('data-table-name') || 'Tavolo ' + c.id;
		inspectorEl.querySelector('.siqo-plan-inspector-meta').textContent = card.getAttribute('data-table-status-label') || '';
		inspectorEl.querySelector('[data-plan-shape]').value = c.shape;
		inspectorEl.querySelector('[data-seats-value]').textContent = c.seats;

		function apply(patch) {
			beginOp();
			var current = cardData(card);
			Object.keys(patch).forEach(function (key) { current[key] = patch[key]; });
			writeCard(card, current);
			save();
		}

		inspectorEl.querySelector('[data-plan-close]').addEventListener('click', function () { selectCard(null); });
		inspectorEl.querySelector('[data-plan-shape]').addEventListener('change', function (e) {
			apply({ shape: e.target.value });
		});
		inspectorEl.querySelector('[data-plan-zone-list]').addEventListener('change', function (e) {
			apply({ zone: num(e.target.value, 0) });
		});
		inspectorEl.querySelectorAll('[data-seats]').forEach(function (button) {
			button.addEventListener('click', function () {
				var value = clamp(cardData(card).seats + (button.getAttribute('data-seats') === '+' ? 1 : -1), 0, 20);
				inspectorEl.querySelector('[data-seats-value]').textContent = value;
				apply({ seats: value });
			});
		});
	}
```

**Step 4: tastiera**

```js
	document.addEventListener('keydown', function (event) {
		if (event.altKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
			var index = -1;
			for (var i = 0; i < state.floors.length; i++) {
				if (Number(state.floors[i].id) === state.floor) { index = i; }
			}
			if (index >= 0 && state.floors.length > 1) {
				event.preventDefault();
				var next = (index + (event.key === 'ArrowRight' ? 1 : -1) + state.floors.length) % state.floors.length;
				setFloor(state.floors[next].id);
				var tab = tabsEl.querySelector('[data-floor="' + state.floors[next].id + '"]');
				if (tab) { tab.focus(); }
			}
			return;
		}
		if (!state.editing) {
			if (event.key === 'Enter' && state.selected) {
				state.selected.click();
			}
			return;
		}
		if (event.key === 'Escape') { selectCard(null); selectZone(null); return; }
		if (event.key === 'Tab' && state.view === 'plan') {
			var order = cards.filter(function (el) { return !el.classList.contains('is-other-floor'); });
			if (!order.length) { return; }
			var current = order.indexOf(state.selected);
			var step = event.shiftKey ? -1 : 1;
			var target = order[(current + step + order.length) % order.length];
			if (current === -1) { target = order[0]; }
			event.preventDefault();
			selectCard(target);
			return;
		}
		if (!state.selected || ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(event.key) === -1) { return; }
		event.preventDefault();
		beginOp();
		var delta = event.shiftKey ? 50 : D.step;
		var c = cardData(state.selected);
		if (event.key === 'ArrowLeft') { c.x = Math.max(0, c.x - delta); }
		if (event.key === 'ArrowRight') { c.x = c.x + delta; }
		if (event.key === 'ArrowUp') { c.y = Math.max(0, c.y - delta); }
		if (event.key === 'ArrowDown') { c.y = c.y + delta; }
		if (c.x + c.w + 40 > state.canvas.w) { state.canvas.w = Math.min(4000, c.x + c.w + 40); }
		if (c.y + c.h + 40 > state.canvas.h) { state.canvas.h = Math.min(4000, c.y + c.h + 40); }
		writeCard(state.selected, c);
		applyCanvas();
		save();
	});
```

**Step 5: CSS**

```css
.siqo-plan-handle { position: absolute; width: 24px; height: 24px; background: #fff; border: 2px solid #1f6feb; border-radius: 4px; z-index: 3; }
.siqo-plan-handle-e { right: -12px; top: 50%; margin-top: -12px; cursor: ew-resize; }
.siqo-plan-handle-s { bottom: -12px; left: 50%; margin-left: -12px; cursor: ns-resize; }
.siqo-plan-handle-se { right: -12px; bottom: -12px; cursor: nwse-resize; }
@media (pointer: coarse) { .siqo-plan-handle { width: 28px; height: 28px; } .siqo-plan-handle-e { right: -14px; margin-top: -14px; } .siqo-plan-handle-s { bottom: -14px; margin-left: -14px; } .siqo-plan-handle-se { right: -14px; bottom: -14px; } }
.siqo-plan-inspector { position: absolute; z-index: 20; width: 260px; background: #fff; border: 1px solid #e2d8ca; border-radius: 12px; box-shadow: 0 10px 30px rgba(47,42,38,.15); padding: 12px; font-size: 12px; }
.siqo-plan-inspector-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.siqo-plan-inspector-head button { border: 0; background: transparent; font-size: 20px; line-height: 1; cursor: pointer; }
.siqo-plan-field { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin: 8px 0; }
.siqo-plan-field select, .siqo-plan-field input { max-width: 130px; font: inherit; }
.siqo-plan-stepper { display: inline-flex; align-items: center; gap: 6px; }
.siqo-plan-stepper button { width: 26px; height: 26px; border: 1px solid #d8cbbd; border-radius: 6px; background: #fff; cursor: pointer; }
.siqo-plan-inspector-meta { margin: 8px 0 0; color: #6b5d50; }
@media (max-width: 1023px) {
	.siqo-plan-inspector { position: fixed; left: 8px; right: 8px; bottom: 8px; width: auto; max-height: 42vh; overflow: auto; }
}
```

**Step 6: verifica**

- Resize con i tre handle (min 80×60), `square`/`round` bloccano il lato, badge `N posti` aggiornato.
- Ispettore: forma cambia la card, sedie 0–20, zona salvata e riletta dopo reload.
- Frecce 10px / Shift 50px, `Esc`, `Tab` cicla solo i tavoli del piano, `Alt+←/→` cambia piano, `Invio` apre l'ordine solo fuori modifica.
- `node --check` pulito.

**Step 7: commit**

```powershell
git add public/js/siqo-plan-editor.js public/css/siqo-management.css
git commit -m "feat: table resize, seats, inspector and keyboard support"
```

---

## Task 7 — Zone: disegno, spostamento, resize, ispettore, CRUD

**File:** `includes/class-siqo-plan-layout.php`, `public/js/siqo-plan-editor.js`, `public/css/siqo-management.css`.

**Step 1: endpoint `siqo_zone_create`**

```php
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
		$bounds = array(
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
		wp_send_json_success( array( 'zone' => self::zone_payload( $id ), 'zones' => self::zones() ) );
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
		if ( isset( $_POST['bg'] ) ) {
			$bg = absint( $_POST['bg'] );
			if ( $bg && 'image' !== substr( (string) get_post_mime_type( $bg ), 0, 5 ) ) {
				wp_send_json_error( array( 'message' => __( 'Allegato non valido.', 'studio-immens-quick-order' ) ), 400 );
			}
			if ( $bg ) { update_term_meta( $id, '_siqo_zone_bg', $bg ); } else { delete_term_meta( $id, '_siqo_zone_bg' ); }
		}
		wp_send_json_success( array( 'zone' => self::zone_payload( $id ), 'zones' => self::zones() ) );
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
```

**Step 2: rendering zone**

```js
	function renderZones() {
		if (!zonesEl) { return; }
		zonesEl.innerHTML = '';
		zoneEls = {};
		state.zones.forEach(function (zone) {
			if (Number(zone.floor) !== state.floor) { return; }
			var el = document.createElement('div');
			el.className = 'siqo-plan-zone';
			el.setAttribute('role', 'group');
			el.setAttribute('aria-label', zone.name);
			el.setAttribute('data-zone-id', String(zone.id));
			el.style.left = zone.x + 'px';
			el.style.top = zone.y + 'px';
			el.style.width = zone.w + 'px';
			el.style.height = zone.h + 'px';
			if (zone.bgUrl) {
				el.style.backgroundImage = 'url("' + zone.bgUrl.replace(/"/g, '%22') + '")';
				el.style.backgroundSize = 'cover';
				el.style.backgroundPosition = 'center';
			}
			var tint = document.createElement('span');
			tint.className = 'siqo-plan-zone-tint';
			tint.style.background = zone.color || 'transparent';
			var label = document.createElement('span');
			label.className = 'siqo-plan-zone-label';
			label.textContent = zone.name;
			el.appendChild(tint);
			el.appendChild(label);
			zonesEl.appendChild(el);
			zoneEls[zone.id] = el;
		});
	}

	function zoneById(id) {
		for (var i = 0; i < state.zones.length; i++) {
			if (Number(state.zones[i].id) === Number(id)) { return state.zones[i]; }
		}
		return null;
	}

	function zoneGeometry(el) {
		return {
			id: num(el.getAttribute('data-zone-id'), 0),
			x: Math.round(parseFloat(el.style.left) || 0),
			y: Math.round(parseFloat(el.style.top) || 0),
			w: Math.round(parseFloat(el.style.width) || 240),
			h: Math.round(parseFloat(el.style.height) || 160)
		};
	}

	function selectZone(el) {
		if (state.selectedZone) { state.selectedZone.classList.remove('is-selected'); }
		state.selectedZone = el || null;
		if (state.selectedZone) {
			state.selectedZone.classList.add('is-selected');
			buildZoneInspector(state.selectedZone);
		} else if (state.editing && !state.selected) {
			hideInspector();
		}
	}
```

**Step 3: spostamento e resize zona**

```js
	zonesEl.addEventListener('pointerdown', function (event) {
		if (!state.editing || event.button !== 0) { return; }
		var el = event.target.closest('.siqo-plan-zone');
		if (!el) { return; }
		var zone = zoneById(el.getAttribute('data-zone-id'));
		if (!zone) { return; }
		if (event.target.closest('.siqo-plan-handle')) { return; }
		beginOp();
		selectZone(el);
		var start = toPlanCoords(event.clientX, event.clientY);
		var origin = zoneGeometry(el);
		el.setPointerCapture(event.pointerId);
		function move(ev) {
			var p = toPlanCoords(ev.clientX, ev.clientY);
			var dx = Math.round((p.x - start.x) / D.step) * D.step;
			var dy = Math.round((p.y - start.y) / D.step) * D.step;
			var geo = { x: Math.max(0, origin.x + dx), y: Math.max(0, origin.y + dy), w: origin.w, h: origin.h };
			zone.x = geo.x; zone.y = geo.y;
			el.style.left = geo.x + 'px';
			el.style.top = geo.y + 'px';
			if (geo.x + geo.w + 40 > state.canvas.w) { state.canvas.w = Math.min(4000, geo.x + geo.w + 40); }
			if (geo.y + geo.h + 40 > state.canvas.h) { state.canvas.h = Math.min(4000, geo.y + geo.h + 40); }
			applyCanvas();
		}
		function up() {
			el.removeEventListener('pointermove', move);
			el.removeEventListener('pointerup', up);
			el.removeEventListener('pointercancel', up);
			var geo = zoneGeometry(el);
			zone.w = geo.w; zone.h = geo.h;
			save();
		}
		el.addEventListener('pointermove', move);
		el.addEventListener('pointerup', up);
		el.addEventListener('pointercancel', up);
		event.preventDefault();
		event.stopPropagation();
	});

	zonesEl.addEventListener('pointerdown', function (event) {
		var handle = event.target.closest('[data-zone-handle]');
		if (!handle || !state.editing) { return; }
		var el = handle.closest('.siqo-plan-zone');
		var zone = el ? zoneById(el.getAttribute('data-zone-id')) : null;
		if (!zone) { return; }
		beginOp();
		selectZone(el);
		var dir = handle.getAttribute('data-zone-handle');
		var origin = zoneGeometry(el);
		var start = toPlanCoords(event.clientX, event.clientY);
		el.setPointerCapture(event.pointerId);
		function move(ev) {
			var p = toPlanCoords(ev.clientX, ev.clientY);
			var w = origin.w, h = origin.h;
			if (dir === 'se' || dir === 'e') { w = Math.max(D.minW, origin.w + (p.x - start.x)); }
			if (dir === 'se' || dir === 's') { h = Math.max(D.minH, origin.h + (p.y - start.y)); }
			zone.w = Math.round(w / D.step) * D.step;
			zone.h = Math.round(h / D.step) * D.step;
			el.style.width = zone.w + 'px';
			el.style.height = zone.h + 'px';
			if (origin.x + zone.w + 40 > state.canvas.w) { state.canvas.w = Math.min(4000, origin.x + zone.w + 40); }
			if (origin.y + zone.h + 40 > state.canvas.h) { state.canvas.h = Math.min(4000, origin.y + zone.h + 40); }
			applyCanvas();
		}
		function up() {
			el.removeEventListener('pointermove', move);
			el.removeEventListener('pointerup', up);
			el.removeEventListener('pointercancel', up);
			renderZones();
			save();
		}
		el.addEventListener('pointermove', move);
		el.addEventListener('pointerup', up);
		el.addEventListener('pointercancel', up);
		event.preventDefault();
		event.stopPropagation();
	}, true);
```

Aggiungi gli handle in `renderZones()`, dopo `el.appendChild(label);`:

```js
			if (state.editing) {
				['se', 'e', 's'].forEach(function (dir) {
					var handle = document.createElement('span');
					handle.className = 'siqo-plan-zone-handle siqo-plan-handle-' + dir;
					handle.setAttribute('data-zone-handle', dir);
					handle.setAttribute('aria-label', 'Ridimensiona zona ' + zone.name);
					el.appendChild(handle);
				});
			}
```

**Step 4: disegno nuova zona**

Sostituisci il blocco pan (`area.addEventListener('pointerdown', ...)` che imposta `pan`) con:

```js
	canvasEl.addEventListener('pointerdown', function (event) {
		if (!state.editing || event.button !== 0) { return; }
		if (event.target.closest('.fo_table_cell') || event.target.closest('.siqo-plan-zone')) { return; }
		var start = toPlanCoords(event.clientX, event.clientY);
		var box = document.createElement('div');
		box.className = 'siqo-plan-draw';
		zonesEl.appendChild(box);
		function move(ev) {
			var p = toPlanCoords(ev.clientX, ev.clientY);
			box.style.left = Math.min(start.x, p.x) + 'px';
			box.style.top = Math.min(start.y, p.y) + 'px';
			box.style.width = Math.abs(p.x - start.x) + 'px';
			box.style.height = Math.abs(p.y - start.y) + 'px';
		}
		function up(ev) {
			canvasEl.removeEventListener('pointermove', move);
			canvasEl.removeEventListener('pointerup', up);
			zonesEl.removeChild(box);
			var p = toPlanCoords(ev.clientX, ev.clientY);
			var x = Math.max(0, Math.round(Math.min(start.x, p.x) / D.step) * D.step);
			var y = Math.max(0, Math.round(Math.min(start.y, p.y) / D.step) * D.step);
			var w = Math.max(D.minW, Math.round(Math.abs(p.x - start.x) / D.step) * D.step);
			var h = Math.max(D.minH, Math.round(Math.abs(p.y - start.y) / D.step) * D.step);
			if (w < 80 || h < 60) { return; }
			var name = S('zone') + ' ' + (state.zones.length + 1);
			post('siqo_zone_create', { floor_id: state.floor, name: name, x: x, y: y, w: w, h: h }).then(function (result) {
				if (result && result.success) {
					state.zones = result.data.zones;
					renderZones();
					var el = zoneEls[result.data.zone.id];
					if (el) {
						selectZone(el);
						var input = inspectorEl.querySelector('[data-zone-name]');
						if (input) { input.focus(); input.select(); }
					}
					setStatus(S('saved'), false);
				} else {
					setStatus((result && result.data && result.data.message) || S('error'), true);
				}
			});
		}
		canvasEl.setPointerCapture(event.pointerId);
		canvasEl.addEventListener('pointermove', move);
		canvasEl.addEventListener('pointerup', up);
		event.preventDefault();
	});

	area.addEventListener('pointerdown', function (event) {
		if (state.editing) { return; }
		if (event.target.closest('.fo_table_cell')) { return; }
		pan = { x: event.clientX, y: event.clientY, left: area.scrollLeft, top: area.scrollTop };
		area.setPointerCapture(event.pointerId);
		area.style.cursor = 'grabbing';
	});
```

**Step 5: ispettore zona**

```js
	function buildZoneInspector(el) {
		if (!inspectorEl) { return; }
		var zone = zoneById(el.getAttribute('data-zone-id'));
		if (!zone) { return; }
		inspectorEl.innerHTML =
			'<div class="siqo-plan-inspector-head"><strong data-zone-title></strong>' +
			'<button type="button" data-plan-close aria-label="Chiudi">&times;</button></div>' +
			'<label class="siqo-plan-field">Nome<input type="text" maxlength="60" data-zone-name></label>' +
			'<label class="siqo-plan-field">Colore<input type="color" data-zone-color></label>' +
			'<div class="siqo-plan-zone-actions"><button type="button" data-zone-bg>Carica sfondo</button>' +
			'<button type="button" data-zone-library>Libreria</button>' +
			'<button type="button" data-zone-bg-remove>Rimuovi</button></div>' +
			'<button type="button" class="is-danger" data-zone-delete>Elimina zona</button>';
		inspectorEl.hidden = false;
		inspectorEl.querySelector('[data-zone-title]').textContent = zone.name;
		inspectorEl.querySelector('[data-zone-name]').value = zone.name;
		inspectorEl.querySelector('[data-zone-color]').value = zone.color || '#a6462f';
		inspectorEl.querySelector('[data-plan-close]').addEventListener('click', function () { selectZone(null); });

		function refresh(result) {
			if (!result || !result.success) {
				setStatus((result && result.data && result.data.message) || S('error'), true);
				return;
			}
			state.zones = result.data.zones;
			renderZones();
			var again = zoneEls[zone.id];
			if (again) { selectZone(again); }
			setStatus(S('saved'), false);
		}

		inspectorEl.querySelector('[data-zone-name]').addEventListener('change', function (e) {
			post('siqo_zone_update', { id: zone.id, name: e.target.value.trim() }).then(refresh);
		});
		inspectorEl.querySelector('[data-zone-color]').addEventListener('change', function (e) {
			post('siqo_zone_update', { id: zone.id, color: e.target.value }).then(refresh);
		});
		inspectorEl.querySelector('[data-zone-bg]').addEventListener('click', function () { pickBg(zone.id); });
		inspectorEl.querySelector('[data-zone-library]').addEventListener('click', function () { pickLibrary(zone.id); });
		inspectorEl.querySelector('[data-zone-bg-remove]').addEventListener('click', function () {
			post('siqo_zone_update', { id: zone.id, bg: 0 }).then(refresh);
		});
		inspectorEl.querySelector('[data-zone-delete]').addEventListener('click', function () {
			if (!window.confirm('Eliminare la zona "' + zone.name + '"? I tavoli resteranno senza zona.')) { return; }
			post('siqo_zone_delete', { id: zone.id }).then(function (result) {
				if (result && result.success) {
					state.zones = result.data.zones;
					selectZone(null);
					renderZones();
					renderCards();
					setStatus(S('saved'), false);
				}
			});
		});
	}
```

`pickBg` e `pickLibrary` sono definite nella Task 9; per ora dichiara stub vuoti in fondo al file (`function pickBg() {} function pickLibrary() {}`), sostituiti nella Task 9.

**Step 6: CSS**

```css
.siqo-plan-zone { position: absolute; border: 2px solid rgba(166,70,47,.55); border-radius: 10px; overflow: visible; z-index: 0; touch-action: none; }
.siqo-plan-zone.is-selected { border-color: #1f6feb; outline: 1px dashed #1f6feb; outline-offset: 2px; }
.siqo-plan-zone-tint { position: absolute; inset: 0; opacity: .22; border-radius: 8px; pointer-events: none; }
.siqo-plan-zone-label { position: absolute; left: 8px; top: 6px; font-size: 11px; font-weight: 700; color: #2f2a26; background: rgba(255,255,255,.85); padding: 1px 6px; border-radius: 999px; pointer-events: none; }
.siqo-plan-zone-handle { position: absolute; width: 20px; height: 20px; background: #fff; border: 2px solid #1f6feb; border-radius: 50%; }
.siqo-plan-zone-handle.siqo-plan-handle-e { right: -10px; top: 50%; margin-top: -10px; }
.siqo-plan-zone-handle.siqo-plan-handle-s { bottom: -10px; left: 50%; margin-left: -10px; }
.siqo-plan-zone-handle.siqo-plan-handle-se { right: -10px; bottom: -10px; }
.siqo-plan-draw { position: absolute; border: 2px dashed #1f6feb; background: rgba(31,111,235,.12); pointer-events: none; }
.siqo-plan-zone-actions { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
.siqo-plan-zone-actions button, .siqo-plan-inspector button.is-danger { flex: 1; min-width: 74px; padding: 7px 8px; border: 1px solid #d8cbbd; border-radius: 8px; background: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.siqo-plan-inspector button.is-danger { width: 100%; margin-top: 8px; border-color: #a6462f; color: #a6462f; }
.siqo-plan-canvas.is-editing .siqo-plan-zone { cursor: move; }
```

**Step 7: verifica**

- In modifica, trascina sullo spazio vuoto: rettangolo tratteggiato, al rilascio nasce "Zona N" selezionata con nome in focus.
- Sposta (snap 10) e ridimensiona con i tre handle; dopo reload posizione e dimensioni persistono.
- Rinomina e cambia colore dall'ispettore; eliminazione con conferma, i tavoli restano.
- Zona di un altro piano non appare; zona senza piano (esistente) appare su Piano terra con bounds 40,40,240×160.
- Le zone non intercettano il tap dei tavoli (stanno dietro) e in sola lettura non sono interattive.

**Step 8: commit**

```powershell
git add includes/class-siqo-plan-layout.php public/js/siqo-plan-editor.js public/css/siqo-management.css
git commit -m "feat: zone drawing, move, resize and CRUD"
```

---

## Task 8 — Pannello Gestisci: piani e zone del piano

**File:** `public/js/siqo-plan-editor.js`, `public/css/siqo-management.css`, `public/template/manage-tables.php`.

**Step 1: markup**

Il contenitore `[data-plan-manager]` è già nel template (Task 2): viene popolato via JS, nessun altro markup.

**Step 2: render e wiring**

```js
	function floorOrderList() {
		return state.floors.slice().filter(function (f) { return !f.virtual; });
	}

	function renderManager() {
		if (!managerEl || !state.editing) { return; }
		var html = '<div class="siqo-plan-manager-head"><strong>Piani</strong>' +
			'<button type="button" data-plan-manager-close aria-label="Chiudi">&times;</button></div>' +
			'<ul class="siqo-plan-manager-list">';
		floorOrderList().forEach(function (floor, index, list) {
			html += '<li data-manager-floor="' + floor.id + '">' +
				'<input type="text" maxlength="60" value="' + esc(floor.name) + '" aria-label="Nome piano">' +
				'<button type="button" data-floor-up aria-label="Sposta su"' + (index === 0 ? ' disabled' : '') + '>&uarr;</button>' +
				'<button type="button" data-floor-down aria-label="Sposta giù"' + (index === list.length - 1 ? ' disabled' : '') + '>&darr;</button>' +
				'<button type="button" data-floor-delete aria-label="Elimina piano">Elimina</button></li>';
		});
		html += '</ul><form class="siqo-plan-manager-add" data-floor-create>' +
			'<input type="text" maxlength="60" placeholder="Nuovo piano" aria-label="Nome nuovo piano" required>' +
			'<label class="siqo-plan-manager-copy"><input type="checkbox" data-floor-copy> Copia dimensioni</label>' +
			'<button type="submit">Crea</button></form>' +
			'<div class="siqo-plan-manager-head"><strong>Zone del piano</strong></div><ul class="siqo-plan-manager-list">';
		state.zones.forEach(function (zone) {
			if (Number(zone.floor) !== state.floor) { return; }
			html += '<li data-manager-zone="' + zone.id + '">' +
				'<input type="text" maxlength="60" value="' + esc(zone.name) + '" aria-label="Nome zona">' +
				'<input type="color" value="' + (zone.color || '#a6462f') + '" aria-label="Colore zona">' +
				'<button type="button" data-zone-bg-manager aria-label="Sfondo zona">Sfondo</button>' +
				'<button type="button" data-zone-delete-manager aria-label="Elimina zona">Elimina</button></li>';
		});
		html += '</ul><form class="siqo-plan-manager-add" data-zone-create>' +
			'<input type="text" maxlength="60" placeholder="Nuova zona" aria-label="Nome nuova zona" required>' +
			'<button type="submit">Crea zona</button></form>' +
			'<div class="siqo-plan-manager-dialog" data-floor-dialog hidden></div>';
		managerEl.innerHTML = html;
		managerEl.hidden = false;
	}

	function refreshFrom(result, keepManager) {
		if (!result || !result.success) {
			setStatus((result && result.data && result.data.message) || S('error'), true);
			return false;
		}
		state.floors = result.data.floors;
		state.zones = result.data.zones || state.zones;
		if (!floorById(state.floor)) { state.floor = state.floors.length ? state.floors[0].id : 0; }
		renderTabs();
		renderCards();
		renderZones();
		if (keepManager) { renderManager(); }
		setStatus(S('saved'), false);
		return true;
	}

	function bindManager() {
		if (!managerEl) { return; }
		managerEl.addEventListener('click', function (event) {
			var close = event.target.closest('[data-plan-manager-close]');
			if (close) { managerEl.hidden = true; return; }
			var row = event.target.closest('[data-manager-floor]');
			if (row) {
				var id = num(row.getAttribute('data-manager-floor'), 0);
				if (event.target.closest('[data-floor-delete]')) {
					post('siqo_floor_delete', { id: id }).then(function (result) {
						if (result && result.success) { refreshFrom(result, true); return; }
						if (result && result.data && result.data.tables) { showFloorDialog(id, result.data.tables); return; }
						setStatus((result && result.data && result.data.message) || S('error'), true);
					});
					return;
				}
				if (event.target.closest('[data-floor-up]') || event.target.closest('[data-floor-down]')) {
					var list = floorOrderList();
					var index = -1;
					for (var i = 0; i < list.length; i++) { if (Number(list[i].id) === id) { index = i; } }
					var swap = event.target.closest('[data-floor-up]') ? index - 1 : index + 1;
					if (index < 0 || swap < 0 || swap >= list.length) { return; }
					post('siqo_floor_update', { id: id, order: swap + 1 }).then(function (r1) {
						return post('siqo_floor_update', { id: list[swap].id, order: index + 1 }).then(function () { refreshFrom(r1, true); });
					});
				}
			}
			var zoneRow = event.target.closest('[data-manager-zone]');
			if (zoneRow) {
				var zid = num(zoneRow.getAttribute('data-manager-zone'), 0);
				if (event.target.closest('[data-zone-delete-manager]')) {
					var zone = zoneById(zid);
					if (zone && window.confirm('Eliminare la zona "' + zone.name + '"?')) {
						post('siqo_zone_delete', { id: zid }).then(function (result) {
							if (result && result.success) { refreshFrom({ success: true, data: { floors: state.floors, zones: result.data.zones } }, true); }
						});
					}
				}
				if (event.target.closest('[data-zone-bg-manager]')) { pickBg(zid); }
			}
		});
		managerEl.addEventListener('change', function (event) {
			var row = event.target.closest('[data-manager-floor]');
			if (row && event.target.tagName === 'INPUT' && event.target.type === 'text') {
				post('siqo_floor_update', { id: row.getAttribute('data-manager-floor'), name: event.target.value.trim() }).then(function (r) { refreshFrom(r, true); });
				return;
			}
			var zoneRow = event.target.closest('[data-manager-zone]');
			if (zoneRow && event.target.tagName === 'INPUT') {
				var params = { id: zoneRow.getAttribute('data-manager-zone') };
				if (event.target.type === 'color') { params.color = event.target.value; } else { params.name = event.target.value.trim(); }
				post('siqo_zone_update', params).then(function (r) { refreshFrom(r, true); });
			}
		});
		managerEl.addEventListener('submit', function (event) {
			var zoneForm = event.target.closest('[data-zone-create]');
			if (zoneForm) {
				event.preventDefault();
				var zoneName = zoneForm.querySelector('input[type="text"]').value.trim();
				if (!zoneName) { return; }
				var zx = Math.round(((state.canvas.w - 240) / 2) / 10) * 10;
				var zy = Math.round(((state.canvas.h - 160) / 2) / 10) * 10;
				post('siqo_zone_create', { floor_id: state.floor, name: zoneName, x: Math.max(0, zx), y: Math.max(0, zy), w: 240, h: 160 }).then(function (r) {
					refreshFrom({ success: true, data: { floors: state.floors, zones: r.data.zones } }, true);
				});
				return;
			}
			var floorForm = event.target.closest('[data-floor-create]');
			if (floorForm) {
				event.preventDefault();
				var name = floorForm.querySelector('input[type="text"]').value.trim();
				if (!name) { return; }
				var params = { name: name };
				var copy = floorForm.querySelector('[data-floor-copy]');
				if (copy && copy.checked) { params.copy_from = state.floor; }
				post('siqo_floor_create', params).then(function (r) { refreshFrom(r, true); });
			}
		});
	}

	function showFloorDialog(id, tables) {
		if (!managerEl) { return; }
		var dialog = managerEl.querySelector('[data-floor-dialog]');
		if (!dialog) { return; }
		var html = '<p>' + esc(S('moveTo')) + ':</p>';
		state.floors.forEach(function (floor) {
			if (Number(floor.id) === Number(id)) { return; }
			html += '<button type="button" data-floor-target="' + floor.id + '">' + esc(floor.name) + '</button>';
		});
		html += '<p class="siqo-plan-manager-warn">' + tables.length + ' tavoli</p>' +
			'<button type="button" data-floor-cancel>' + esc(S('cancel')) + '</button>';
		dialog.innerHTML = html;
		dialog.hidden = false;
		dialog.querySelectorAll('[data-floor-target]').forEach(function (button) {
			button.addEventListener('click', function () {
				var target = button.getAttribute('data-floor-target');
				post('siqo_floor_delete', { id: id, move_to: target }).then(function (r) { dialog.hidden = true; refreshFrom(r, true); });
			});
		});
		dialog.querySelector('[data-floor-cancel]').addEventListener('click', function () { dialog.hidden = true; });
	}
```

**Step 3: attivazione**

Sostituisci il listener di `[data-plan-manage]` (aggiunto in Task 5 non esiste ancora: aggiungilo qui) e chiama `bindManager()` una volta al boot:

```js
	document.querySelectorAll('[data-plan-manage]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (!state.editing) { setEditing(true); }
			if (managerEl.hidden) { renderManager(); } else { managerEl.hidden = true; }
			button.setAttribute('aria-expanded', managerEl.hidden ? 'false' : 'true');
		});
	});
	bindManager();
```

Aggiungi `renderManager()` in coda a `setFloor()` (solo se il pannello è visibile).

**Step 4: CSS**

```css
.siqo-plan-manager { margin-top: 8px; background: #fff; border: 1px solid #e2d8ca; border-radius: 12px; padding: 12px; font-size: 12px; }
.siqo-plan-manager-head { display: flex; justify-content: space-between; align-items: center; margin: 10px 0 6px; }
.siqo-plan-manager-head:first-child { margin-top: 0; }
.siqo-plan-manager-list { list-style: none; margin: 0 0 8px; padding: 0; display: grid; gap: 6px; }
.siqo-plan-manager-list li { display: flex; gap: 6px; align-items: center; }
.siqo-plan-manager-list input[type="text"] { flex: 1; min-width: 0; }
.siqo-plan-manager-list input[type="color"] { width: 34px; height: 30px; padding: 2px; }
.siqo-plan-manager-list button, .siqo-plan-manager-add button { padding: 7px 10px; border: 1px solid #d8cbbd; border-radius: 8px; background: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.siqo-plan-manager-add { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.siqo-plan-manager-add input[type="text"] { flex: 1; min-width: 120px; }
.siqo-plan-manager-copy { font-size: 11px; display: inline-flex; gap: 4px; align-items: center; }
.siqo-plan-manager-dialog { border-top: 1px dashed #d8cbbd; padding-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.siqo-plan-manager-dialog p { flex-basis: 100%; margin: 0 0 4px; }
.siqo-plan-manager-warn { color: #a6462f; font-weight: 700; }
```

**Step 5: verifica**

- `Gestisci` apre il pannello solo in modifica (lo attiva se serve); chiude con `×`.
- Crea "Primo piano" con e senza "Copia dimensioni"; rinomina inline; `↑/↓` cambia ordine e la tab si riordina; crea tavoli? no (fuori scope).
- Elimina un piano vuoto → sparisce; elimina un piano con tavoli → dialog con i piani disponibili, scelta → tavoli spostati, zone del piano eliminate.
- Crea zona dal manager: 240×160 centrata, selezionata.
- Nessun errore console; dopo reload stato coerente.

**Step 6: commit**

```powershell
git add public/js/siqo-plan-editor.js public/css/siqo-management.css public/template/manage-tables.php
git commit -m "feat: manage panel for floors and zones"
```

---

## Task 9 — Sfondo zona: upload e libreria media

**File:** `includes/class-siqo-plan-layout.php`, `public/js/siqo-plan-editor.js`.

**Step 1: endpoint upload**

```php
	public static function ajax_zone_bg_upload() {
		self::guard();
		$id   = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0;
		$term = $id ? get_term( $id, self::ZONE_TAX ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Zona inesistente.', 'studio-immens-quick-order' ) ), 404 );
		}
		if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nessun file ricevuto.', 'studio-immens-quick-order' ) ), 400 );
		}
		if ( (int) $_FILES['file']['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Immagine troppo grande (max 2 MB).', 'studio-immens-quick-order' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload(
			'file',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
				),
			)
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 400 );
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( array( 'message' => __( 'Formato immagine non supportato.', 'studio-immens-quick-order' ) ), 400 );
		}
		update_term_meta( $id, '_siqo_zone_bg', (int) $attachment_id );
		wp_send_json_success( array( 'zone' => self::zone_payload( $id ) ) );
	}
```

**Step 2: JS**

Sostituisci gli stub `pickBg`/`pickLibrary`:

```js
	function applyZoneResult(result) {
		if (!result || !result.success) {
			setStatus((result && result.data && result.data.message) || S('error'), true);
			return;
		}
		var zone = result.data.zone;
		for (var i = 0; i < state.zones.length; i++) {
			if (Number(state.zones[i].id) === Number(zone.id)) { state.zones[i] = zone; }
		}
		renderZones();
		var el = zoneEls[zone.id];
		if (el && state.editing) { selectZone(el); }
		setStatus(S('saved'), false);
	}

	function pickBg(zoneId) {
		if (!fileEl) { return; }
		state.uploadZone = zoneId;
		fileEl.value = '';
		fileEl.click();
	}

	function pickLibrary(zoneId) {
		if (!window.wp || !window.wp.media) { pickBg(zoneId); return; }
		if (!state.frame) {
			state.frame = window.wp.media({ multiple: false, library: { type: 'image' } });
		}
		state.frame.off('select');
		state.frame.on('select', function () {
			var selection = state.frame.state().get('selection').first();
			if (!selection) { return; }
			var attachment = selection.toJSON();
			post('siqo_zone_update', { id: zoneId, bg: attachment.id }).then(applyZoneResult);
		});
		state.frame.open();
	}

	if (fileEl) {
		fileEl.addEventListener('change', function () {
			if (!fileEl.files || !fileEl.files.length || !state.uploadZone) { return; }
			if (fileEl.files[0].size > 2 * 1024 * 1024) {
				setStatus('Immagine troppo grande (max 2 MB).', true);
				fileEl.value = '';
				return;
			}
			var body = new window.FormData();
			body.append('action', 'siqo_zone_bg_upload');
			body.append('nonce', nonce);
			body.append('zone_id', state.uploadZone);
			body.append('file', fileEl.files[0]);
			setStatus(S('saving'), false);
			window.fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (response) { return response.json(); })
				.then(function (result) {
					fileEl.value = '';
					applyZoneResult(result);
				})
				.catch(function () { setStatus(S('error'), true); });
		});
	}
```

`state.uploadZone` va inizializzato a `0` nell'oggetto `state` della Task 5.

**Step 3: verifica**

- Upload PNG 1 MB → 200, sfondo visibile (`background-size: cover`), `_siqo_zone_bg` valorizzato.
- Upload `documento.txt` → 400, nessun allegato creato.
- Upload > 2 MB → 400 client e server.
- "Libreria" apre il frame media (se `wp.media` disponibile), scelta immagine → sfondo aggiornato.
- "Rimuovi" → meta cancellato, resta il colore.
- Zona trascinata con sfondo non deforma l'immagine (cover).

**Step 4: commit**

```powershell
git add includes/class-siqo-plan-layout.php public/js/siqo-plan-editor.js
git commit -m "feat: zone background upload and media library picker"
```

---

## Task 10 — Accessibilità, responsive, transizioni

**File:** `public/css/siqo-management.css`, `public/js/siqo-plan-editor.js`, `public/template/manage-tables.php`.

**Step 1: CSS responsive**

```css
@media (max-width: 767px) {
	.siqo-plan-edit, .siqo-plan-manage, [data-plan-manager] { display: none !important; }
	.siqo-plan-toolbar { flex-wrap: wrap; gap: 6px; }
	.siqo-plan-area { max-height: 68vh; }
	.siqo-plan-inspector { display: none !important; }
}
@media (prefers-reduced-motion: reduce) {
	.siqo-plan-canvas, .siqo-plan-tabs button { transition: none !important; }
}
.siqo-plan-canvas.is-switching { opacity: 0; transform: translateY(12px) scale(var(--siqo-plan-z, 1)); }
.siqo-plan-canvas { transition: opacity 160ms ease, transform 160ms ease; }
```

Per non rompere lo zoom nella transizione, in `setFloor()` imposta `canvasEl.style.setProperty('--siqo-plan-z', state.zoom)` prima della classe e rimuovi la classe dopo 180ms:

```js
		canvasEl.classList.add('is-switching');
		canvasEl.style.setProperty('--siqo-plan-z', state.zoom);
		window.setTimeout(function () { canvasEl.classList.remove('is-switching'); }, 180);
```

**Step 2: aria e focus**

- `aria-pressed` su modifica, `aria-expanded` su Gestisci, `aria-selected` sulle tab, `role="tablist"`/`role="tab"` (già in Task 2/5).
- Handle tavolo: `aria-label="Ridimensiona <nome>"` (già); handle zona: `aria-label="Ridimensiona zona <nome>"` (già).
- Zone: `role="group"` + `aria-label` (già in Task 7).
- Ispettori: `<label>` avvolge ogni campo (già); `Esc` chiude (già).
- Card: testo di stato presente (`Libero/Occupato/Da incassare`) — verifica che il badge testuale esista già nel template; se manca, aggiungi `<span class="siqo-plan-status-label">` con il testo e nascondilo visivamente solo se il colore è affiancato da titolo/aria.
- Hint telefono: `data-plan-hint` visibile solo <768.

**Step 3: verifica**

- 390px: toolbar senza Modifica/Gestisci, hint visibile, swipe tra piani funzionante, tap apre l'ordine, pinch 30–200%.
- 820px: modifica attiva, handle 28px, ispettore bottom-sheet, pannello Gestisci usabile.
- 1440px: ispettore flottante in alto a destra del canvas, frecce/tab/Alt+frecce, `Adatta`.
- Solo tastiera: raggiungi tab piani, cambia piano, seleziona card con Tab, sposta con frecce, `Esc` deseleziona.
- Nessun testo tagliato o overflow orizzontale; contrasto leggibile sulle zone.

**Step 4: commit**

```powershell
git add public/css/siqo-management.css public/js/siqo-plan-editor.js public/template/manage-tables.php
git commit -m "feat: plan editor accessibility and responsive behaviour"
```

---

## Task 11 — QA completa, baseline, versione 1.2.0, e2e

**File:** `studio-immens-quick-order.php`, `readme.txt`.

**Step 1: matrice QA**

| Caso | 390×844 | 820×1180 | 1440×900 |
| --- | --- | --- | --- |
| Vista griglia | ok, tap apre ordine | ok | ok |
| Disposizione | sola lettura, no overflow | editing, fit | editing, fit |
| Tab piani | swipe | click + frecce | click + frecce |
| Drag/resize | assente | snap 10, handle 28px | snap 10, handle 24px |
| Ispettore tavolo | assente | bottom sheet | flottante |
| Zone | visibili, non interattive | disegno/sposta/resize | idem |
| Gestisci | nascosto | due liste | due liste |

Verifica anche: ricarica mantiene piano e zoom (vista solo se "plan"); `Ricarica disposizione` scarta le modifiche locali; cambio piano non salva; stato live delle card aggiornato anche sui piani nascosti (cambia stato da `/ordini-reparti/` in un'altra tab e controlla la card su un piano non visibile).

**Step 2: percorsi d'errore**

- Nonce invalido → 403 `-1`, nessuna scrittura.
- Utente senza `manage_tables` → 403 JSON.
- `floor_id` inesistente → 404; payload JSON invalido → 400; `id` non `siqo_tables` → 400, meta invariati.
- Elimina piano con tavoli senza `move_to` → 409 + dialog; con `move_to` → tavoli spostati, zone eliminate.
- Upload non immagine / > 2 MB → 400, nessun allegato orfano.
- Offline durante il salvataggio → rollback allo snapshot e status rosso; dopo reload nessuna posizione fantasma.
- 409 lato client → ricarica elenco piani.

**Step 3: regressione e baseline**

- `/manage-tables/`, `/quick-menu/`, `/ordini-reparti/` → 200; invio ordine e storico invariati; `SIQO_tab_Card_show` funzionante.
- `SELECT COUNT(*) FROM wp_wc_orders;` = 34; `wp_wc_orders_meta` invariata; 0 sessioni, 0 print job, 0 stampanti; tavoli 560/580 presenti; prodotti 49/347.
- `SELECT COUNT(*) FROM wp_termmeta WHERE meta_key LIKE '_siqo_%';` solo chiavi previste.

**Step 4: versione e changelog**

In `studio-immens-quick-order.php`: ` * Version:           1.2.0` e `define( 'STUDIO_IMMENS_QUICK_ORDER_VERSION', '1.2.0' );`.

In `readme.txt`: `Stable tag: 1.2.0` e in cima al changelog:

```
= 1.2.0 =
* Editor disposizione: piani multipli, zone con sfondo e colore, forme e sedie, ridimensionamento, zoom e pan.
* Creazione e gestione di piani e zone dalla pagina Gestione tavoli.
* Salvataggio validato della disposizione con rollback in caso di errore.
```

**Step 5: verifiche finali**

```powershell
php -l studio-immens-quick-order.php
php -l includes/class-siqo-plan-layout.php
php -l public/template/manage-tables.php
php -l includes/class-studio-immens-quick-order.php
node --check public/js/siqo-plan-editor.js
git status --short
git diff --stat
```

`git status` non deve includere `includes/class-siqo-management.php` tra i file staged.

**Step 6: commit**

```powershell
git add studio-immens-quick-order.php readme.txt
git add docs/superpowers/plans/2026-11-18-editor-mappa.md docs/superpowers/specs/2026-11-18-editor-mappa-design.md
git commit -m "chore: release 1.2.0 with plan editor"
```

**Step 7: deploy e2e**

Ripeti il flusso della 1.1.8 (`C:\Users\mauro\AppData\Local\Temp\opencode\e2e-deploy.sh`): zip da `git archive` dell'HEAD, copia su `ubuntu@100.70.29.94:/tmp/e2e-plugins/`, installazione wp-cli nel container `srv-captain--e2e-wordpress`. Verifica: plugin attivo 1.2.0; `/`, `/quick-menu/`, `/manage-tables/`, `/ordini-reparti/` → 200; su `/manage-tables/` tab piani presente, editor apribile a 1440px, console pulita.

---

## Ordine di esecuzione

1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11, un commit per task.

Regole:

- Non riordinare: 5 dipende da 2/3, 6–10 da 5, 11 da tutti.
- Nessuna dipendenza nuova, nessun jQuery nel nuovo JS, ES5 (no `let/const`, arrow, `Object.assign` è già usato: se il target include browser senza supporto, sostituisci con copia manuale).
- `php -l` + `node --check` dopo ogni task.
- Se un test sporca dati (posizioni, piani, zone, allegati), ripristina la baseline prima del commit.

