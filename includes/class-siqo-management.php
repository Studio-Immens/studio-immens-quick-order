<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SIQO_Management {

	const PAGE_SLUG    = 'gestione';
	const PAGE_VERSION = '1.0.0';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 21 );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 22 );
		add_shortcode( 'SIQO_management_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_pages' ) );
		add_action( 'admin_post_siqo_create_pages', array( __CLASS__, 'handle_create_pages' ) );
		add_action( 'wp_ajax_siqo_management_dashboard', array( __CLASS__, 'ajax_dashboard' ) );
		add_action( 'wp_ajax_siqo_save_table_layout', array( __CLASS__, 'ajax_save_table_layout' ) );
		add_action( 'wp_ajax_siqo_profile_save', array( __CLASS__, 'ajax_profile_save' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'siqo_management_modules', array( __CLASS__, 'default_modules' ) );
	}

	public static function install() {
		self::ensure_page();
		update_option( 'siqo_management_db_version', self::PAGE_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'siqo_management_db_version' ) !== self::PAGE_VERSION ) {
			self::ensure_page();
			update_option( 'siqo_management_db_version', self::PAGE_VERSION );
		}
	}

	public static function ensure_page() {
		$page = get_page_by_path( self::PAGE_SLUG );
		$content = '[SIQO_management_dashboard]';

		if ( $page ) {
			$post = get_post( $page );
			if ( ! $post || false === strpos( (string) $post->post_content, 'SIQO_management_dashboard' ) ) {
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $content,
						'post_status'  => 'publish',
					)
				);
			}
			update_option( 'siqo_management_page_id', (int) $page->ID );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => 'Gestione',
				'post_name'      => self::PAGE_SLUG,
				'post_content'   => $content,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'siqo_management_page_id', (int) $page_id );
		}
	}

	public static function page_url( $args = array() ) {
		$page_id = (int) get_option( 'siqo_management_page_id', 0 );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' . self::PAGE_SLUG . '/' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function get_page_url( $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			return get_permalink( $page );
		}
		return '';
	}

	public static function role_flags() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array( 'worker' => false, 'supervisor' => false, 'manager' => false );
		}
		if ( function_exists( 'SIQO_get_user_role_flags' ) ) {
			return SIQO_get_user_role_flags( $user_id );
		}
		$worker     = (bool) get_user_meta( $user_id, 'siqo_user_role_worker', true );
		$supervisor = (bool) get_user_meta( $user_id, 'siqo_user_role_supervisor', true );
		$manager    = (bool) get_user_meta( $user_id, 'siqo_user_role_manager', true );
		if ( $manager ) {
			$supervisor = true;
		}
		if ( $supervisor ) {
			$worker = true;
		}
		return array( 'worker' => $worker, 'supervisor' => $supervisor, 'manager' => $manager );
	}

	public static function role_level() {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			return 'manager';
		}
		$flags = self::role_flags();
		if ( $flags['manager'] ) {
			return 'manager';
		}
		if ( $flags['supervisor'] ) {
			return 'supervisor';
		}
		if ( $flags['worker'] ) {
			return 'worker';
		}
		return 'none';
	}

	public static function can( $capability ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		$level = self::role_level();
		$map   = array(
			'view_dashboard'      => 'worker',
			'manage_tables'       => 'worker',
			'view_stations'       => 'worker',
			'print_receipts'      => 'worker',
			'manage_inventory'    => 'supervisor',
			'manage_printers'     => 'supervisor',
			'manage_reservations' => 'supervisor',
			'manage_catering'     => 'supervisor',
			'view_reports'        => 'supervisor',
			'manage_staff'        => 'manager',
			'manage_settings'     => 'manager',
			'manage_license'      => 'manager',
			'manage_users'        => 'manager',
			'manage_discounts'    => 'manager',
		);
		$required = isset( $map[ $capability ] ) ? $map[ $capability ] : 'manager';
		$rank     = array( 'none' => 0, 'worker' => 1, 'supervisor' => 2, 'manager' => 3 );
		return isset( $rank[ $level ] ) && $rank[ $level ] >= $rank[ $required ];
	}

	public static function license_active() {
		if ( ! class_exists( 'SIQO_Inventory' ) && ! class_exists( 'SIQO_Printers' ) && ! class_exists( 'SIQO_Reservations' ) ) {
			return false;
		}
		if ( class_exists( 'SIQO_License' ) && method_exists( 'SIQO_License', 'is_active' ) ) {
			return (bool) SIQO_License::is_active();
		}
		return 'active' === get_option( 'siqo_license_status', '' );
	}

	public static function get_brand() {
		$name = get_option( 'siqo_restaurant_name', '' );
		if ( ! $name ) {
			$name = get_bloginfo( 'name' );
		}
		$logo = get_option( 'siqo_restaurant_logo_url', '' );
		if ( ! $logo ) {
			$logo = plugins_url( 'includes/img/logo.webp', dirname( __FILE__ ) );
		}
		$primary = sanitize_hex_color( (string) get_option( 'siqo_brand_primary', '#a6462f' ) );
		$accent  = sanitize_hex_color( (string) get_option( 'siqo_brand_accent', '#c98a00' ) );
		$theme   = (string) get_option( 'siqo_brand_theme', 'auto' );
		if ( ! in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ) {
			$theme = 'auto';
		}
		$credit = 'no' === get_option( 'siqo_quick_order_credit', 'yes' ) ? 'no' : 'yes';
		return array(
			'name'        => $name,
			'logo'        => $logo,
			'primary'     => $primary ? $primary : '#a6462f',
			'accent'      => $accent ? $accent : '#c98a00',
			'theme'       => $theme,
			'credit'      => $credit,
			'receipt_head'=> get_option( 'siqo_receipt_header', '' ),
			'receipt_foot'=> get_option( 'siqo_receipt_footer', '' ),
		);
	}

	public static function get_brand_css() {
		$brand = self::get_brand();
		return ':root{--siqo-brand:' . esc_attr( $brand['primary'] ) . ';--siqo-accent:' . esc_attr( $brand['accent'] ) . ';}';
	}

	public static function body_class( $classes ) {
		if ( is_page( self::PAGE_SLUG ) ) {
			$classes[] = 'siqo-operator-mode';
			$classes[] = 'siqo-management-page';
		}
		$page_id = get_queried_object_id();
		if ( $page_id ) {
			$content = (string) get_post_field( 'post_content', $page_id );
			if ( false !== strpos( $content, 'SIQO_front_order_ajax_section' ) ) {
				$classes[] = 'siqo-customer-mode';
			}
			if ( false !== strpos( $content, 'SIQO_manage_tables_section' ) || false !== strpos( $content, 'SIQO_station_orders_section' ) ) {
				$classes[] = 'siqo-operator-mode';
			}
		}
		return $classes;
	}

	public static function enqueue_assets() {
		$page_id = get_queried_object_id();
		$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
		$is_app  = is_page( self::PAGE_SLUG ) || false !== strpos( $content, 'SIQO_management_dashboard' ) || false !== strpos( $content, 'SIQO_manage_tables_section' ) || false !== strpos( $content, 'SIQO_station_orders_section' );
		$is_customer = false !== strpos( $content, 'SIQO_front_order_ajax_section' );

		if ( ! $is_app && ! $is_customer ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'siqo-management', plugins_url( 'public/css/siqo-management.css', dirname( __FILE__ ) ), array( 'dashicons' ), STUDIO_IMMENS_QUICK_ORDER_VERSION );
		wp_add_inline_style( 'siqo-management', self::get_brand_css() );

		if ( is_page( self::PAGE_SLUG ) && isset( $_GET['view'] ) && 'stations' === sanitize_key( wp_unslash( $_GET['view'] ) ) && function_exists( 'siqo_station_enqueue_assets' ) ) {
			siqo_station_enqueue_assets();
		}

		if ( $is_customer ) {
			wp_enqueue_style( 'siqo-customer', plugins_url( 'public/css/siqo-customer.css', dirname( __FILE__ ) ), array( 'siqo-management' ), STUDIO_IMMENS_QUICK_ORDER_VERSION );
		}

		if ( $is_app ) {
			wp_enqueue_script( 'siqo-management', plugins_url( 'public/js/siqo-management.js', dirname( __FILE__ ) ), array( 'jquery' ), STUDIO_IMMENS_QUICK_ORDER_VERSION, true );
			wp_localize_script(
				'siqo-management',
				'SIQO_MANAGEMENT',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'siqo_management' ),
				)
			);
		}
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'si-quick-order_settings' ) && false === strpos( (string) $hook, 'si-quick-order_legacy' ) && 'toplevel_page_si-quick-order' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'siqo-management-admin', plugins_url( 'admin/css/siqo-management-admin.css', dirname( __FILE__ ) ), array(), STUDIO_IMMENS_QUICK_ORDER_VERSION );
	}

	public static function default_modules( $modules ) {
		$modules[] = array(
			'id'    => 'dashboard',
			'label' => __( 'Dashboard', 'studio-immens-quick-order' ),
			'url'   => self::page_url(),
			'cap'   => 'view_dashboard',
			'icon'  => 'dashboard',
		);
		$modules[] = array(
			'id'     => 'tables',
			'label'  => __( 'Tavoli', 'studio-immens-quick-order' ),
			'url'    => self::page_url( array( 'view' => 'tables' ) ),
			'cap'    => 'manage_tables',
			'icon'   => 'grid-view',
			'legacy' => 'manage-tables',
		);
		$modules[] = array(
			'id'     => 'stations',
			'label'  => __( 'Reparti', 'studio-immens-quick-order' ),
			'url'    => self::page_url( array( 'view' => 'stations' ) ),
			'cap'    => 'view_stations',
			'icon'   => 'food',
			'legacy' => 'ordini-reparti',
		);
		return $modules;
	}

	public static function get_modules() {
		$modules = apply_filters( 'siqo_management_modules', array() );
		$out     = array();
		foreach ( $modules as $module ) {
			if ( empty( $module['id'] ) || empty( $module['label'] ) || empty( $module['url'] ) ) {
				continue;
			}
			if ( ! empty( $module['cap'] ) && ! self::can( $module['cap'] ) ) {
				continue;
			}
			if ( ! empty( $module['requires_pro'] ) && ! self::license_active() && 'manager' !== self::role_level() ) {
				continue;
			}
			$out[] = $module;
		}
		return $out;
	}

	public static function nav_groups( $modules ) {
		$groups = array(
			'sala'     => array(
				'label'   => esc_html__( 'Sala', 'studio-immens-quick-order' ),
				'icon'    => 'store',
				'modules' => array( 'dashboard', 'tables', 'stations', 'reservations' ),
			),
			'catalogo' => array(
				'label'   => esc_html__( 'Catalogo', 'studio-immens-quick-order' ),
				'icon'    => 'book-alt',
				'modules' => array( 'inventory', 'catering', 'discounts' ),
			),
			'squadra'  => array(
				'label'   => esc_html__( 'Squadra', 'studio-immens-quick-order' ),
				'icon'    => 'groups',
				'modules' => array( 'staff', 'users' ),
			),
			'sistema'  => array(
				'label'   => esc_html__( 'Sistema', 'studio-immens-quick-order' ),
				'icon'    => 'admin-generic',
				'modules' => array( 'reports', 'receipts', 'printers' ),
			),
		);

		$groups = apply_filters( 'siqo_management_nav_groups', $groups, $modules );

		$by_id = array();
		foreach ( $modules as $module ) {
			$by_id[ $module['id'] ] = $module;
		}

		$out = array();
		$used = array();
		foreach ( $groups as $key => $group ) {
			if ( empty( $group['modules'] ) ) {
				continue;
			}
			$items = array();
			foreach ( $group['modules'] as $id ) {
				if ( empty( $by_id[ $id ] ) ) {
					continue;
				}
				$items[]    = $by_id[ $id ];
				$used[ $id ] = true;
			}
			if ( empty( $items ) ) {
				continue;
			}
			$out[] = array(
				'id'    => sanitize_key( $key ),
				'label' => ! empty( $group['label'] ) ? $group['label'] : esc_html__( 'Altro', 'studio-immens-quick-order' ),
				'icon'  => ! empty( $group['icon'] ) ? $group['icon'] : 'admin-generic',
				'items' => $items,
			);
		}

		$remaining = array();
		foreach ( $modules as $module ) {
			if ( empty( $used[ $module['id'] ] ) ) {
				$remaining[] = $module;
			}
		}
		if ( ! empty( $remaining ) ) {
			$out[] = array(
				'id'    => 'altro',
				'label' => esc_html__( 'Altro', 'studio-immens-quick-order' ),
				'icon'  => 'ellipsis',
				'items' => $remaining,
			);
		}

		return $out;
	}

	public static function module_is_active( $module, $current ) {
		if ( isset( $module['id'] ) && $module['id'] === $current ) {
			return true;
		}
		$request = empty( $_SERVER['REQUEST_URI'] ) ? '' : wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		if ( ! $request ) {
			return false;
		}
		if ( ! empty( $module['legacy'] ) ) {
			$legacy = self::get_page_url( $module['legacy'] );
			$target = $legacy ? wp_parse_url( $legacy, PHP_URL_PATH ) : '';
			if ( $target && untrailingslashit( $request ) === untrailingslashit( $target ) ) {
				return true;
			}
		}
		if ( empty( $module['url'] ) || false !== strpos( $module['url'], 'view=' ) ) {
			return false;
		}
		$target = wp_parse_url( $module['url'], PHP_URL_PATH );
		if ( ! $target ) {
			return false;
		}
		return untrailingslashit( $request ) === untrailingslashit( $target );
	}

	public static function render_bar( $context = 'staff', $args = array() ) {
		$brand   = self::get_brand();
		$modules = 'customer' === $context ? array() : self::get_modules();
		$groups  = 'customer' === $context ? array() : self::nav_groups( $modules );
		$current = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$title   = isset( $args['title'] ) ? $args['title'] : '';
		?>
		<header class="siqo-bar siqo-bar-<?php echo esc_attr( $context ); ?>">
			<div class="siqo-bar-brand">
				<a class="siqo-brand-link" href="<?php echo esc_url( 'customer' === $context ? home_url( '/' ) : self::page_url() ); ?>">
					<?php if ( ! empty( $brand['logo'] ) ) : ?>
						<img class="siqo-brand-logo" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="">
					<?php endif; ?>
					<span class="siqo-brand-name"><?php echo esc_html( $brand['name'] ); ?></span>
				</a>
			</div>

			<?php if ( 'customer' === $context ) : ?>
				<div class="siqo-bar-context">
					<?php if ( ! empty( $args['table_name'] ) ) : ?>
						<span class="siqo-table-badge"><?php echo esc_html( $args['table_name'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $title ) ) : ?>
						<span class="siqo-bar-title"><?php echo esc_html( $title ); ?></span>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<nav class="siqo-bar-nav" aria-label="<?php esc_attr_e( 'Navigazione gestionale', 'studio-immens-quick-order' ); ?>">
					<?php foreach ( $groups as $group ) : ?>
						<?php
						$group_active = false;
						foreach ( $group['items'] as $item ) {
							if ( self::module_is_active( $item, $current ) ) {
								$group_active = true;
								break;
							}
						}
						$menu_id = 'siqo-nav-menu-' . $group['id'];
						?>
						<div class="siqo-nav-group<?php echo $group_active ? ' is-active is-open' : ''; ?>" data-siqo-nav-group>
							<button type="button" class="siqo-nav-trigger<?php echo $group_active ? ' is-active' : ''; ?>" aria-expanded="<?php echo $group_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $menu_id ); ?>" aria-haspopup="true">
								<span class="dashicons dashicons-<?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
								<span><?php echo esc_html( $group['label'] ); ?></span>
								<span class="dashicons dashicons-arrow-down-alt2 siqo-nav-caret" aria-hidden="true"></span>
							</button>
							<div class="siqo-nav-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="menu">
								<?php foreach ( $group['items'] as $module ) : ?>
									<?php $active = self::module_is_active( $module, $current ); ?>
									<a class="siqo-nav-item<?php echo $active ? ' is-active' : ''; ?>" role="menuitem" href="<?php echo esc_url( $module['url'] ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
										<span class="siqo-nav-item-icon">
											<?php if ( ! empty( $module['icon'] ) ) : ?>
												<span class="dashicons dashicons-<?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></span>
											<?php endif; ?>
										</span>
										<span class="siqo-nav-item-label"><?php echo esc_html( $module['label'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<div class="siqo-bar-actions">
				<?php if ( 'customer' === $context ) : ?>
					<?php if ( ! empty( $args['account_url'] ) ) : ?>
						<a class="siqo-bar-action" href="<?php echo esc_url( $args['account_url'] ); ?>">
							<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
							<span><?php echo esc_html( $args['account_label'] ); ?></span>
						</a>
					<?php endif; ?>
					<a class="siqo-bar-action siqo-cart-action" href="#ordine" data-siqo-cart>
						<span class="dashicons dashicons-cart" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Ordine', 'studio-immens-quick-order' ); ?></span>
						<b class="siqo-cart-count" data-siqo-cart-count><?php echo esc_html( isset( $args['cart_count'] ) ? $args['cart_count'] : 0 ); ?></b>
					</a>
				<?php else : ?>
					<?php
					$current_user = wp_get_current_user();
					if ( $current_user->exists() ) :
						$account_label = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
						$avatar_url    = get_avatar_url( $current_user->ID, array( 'size' => 48 ) );
						?>
						<a class="siqo-bar-action siqo-account-action" href="<?php echo esc_url( self::page_url( array( 'view' => 'account' ) ) ); ?>" title="<?php echo esc_attr( sprintf( __( 'Account di %s', 'studio-immens-quick-order' ), $account_label ) ); ?>">
							<?php if ( $avatar_url ) : ?>
								<img class="siqo-account-avatar" src="<?php echo esc_url( $avatar_url ); ?>" alt="" width="24" height="24" loading="lazy">
							<?php else : ?>
								<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="siqo-account-name"><?php echo esc_html( $account_label ); ?></span>
						</a>
					<?php endif; ?>
					<button type="button" class="siqo-bar-action" data-siqo-fullscreen>
						<span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Schermo intero', 'studio-immens-quick-order' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
		</header>
		<?php
	}

	public static function render_shell_start( $args = array() ) {
		$brand = self::get_brand();
		$theme = isset( $brand['theme'] ) ? $brand['theme'] : 'auto';
		?>
		<div id="siqo-app" class="siqo-app siqo-app-staff" data-theme="<?php echo esc_attr( $theme ); ?>">
		<?php
	}

	public static function render_shell_end() {
		?>
		<footer class="siqo-app-footer">
			<span><?php echo esc_html( self::get_brand()['name'] ); ?></span>
			<span>Quick Order</span>
		</footer>
		</div>
		<?php
	}

	public static function render_account_panel() {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return '';
		}
		$avatar = get_avatar_url( $user->ID, array( 'size' => 96 ) );
		$roles  = array(
			'manager'    => __( 'Manager', 'studio-immens-quick-order' ),
			'supervisor' => __( 'Supervisor', 'studio-immens-quick-order' ),
			'worker'     => __( 'Worker', 'studio-immens-quick-order' ),
			'none'       => __( 'Nessun ruolo Quick Order', 'studio-immens-quick-order' ),
		);
		$level  = self::role_level();
		$role   = isset( $roles[ $level ] ) ? $roles[ $level ] : $roles['none'];

		ob_start();
		?>
		<section class="siqo-view siqo-view-account">
			<header class="siqo-view-head">
				<div>
					<p class="siqo-eyebrow"><?php esc_html_e( 'Profilo', 'studio-immens-quick-order' ); ?></p>
					<h1><?php esc_html_e( 'Il tuo account', 'studio-immens-quick-order' ); ?></h1>
					<p class="siqo-muted"><?php esc_html_e( 'Aggiorna i tuoi dati di accesso e il nome mostrato in sala.', 'studio-immens-quick-order' ); ?></p>
				</div>
			</header>

			<div class="siqo-panel siqo-account-panel">
				<div class="siqo-account-head">
					<?php if ( $avatar ) : ?>
						<img class="siqo-account-avatar-lg" src="<?php echo esc_url( $avatar ); ?>" alt="" width="72" height="72" loading="lazy">
					<?php else : ?>
						<span class="siqo-account-avatar-lg siqo-account-avatar-fallback"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span></span>
					<?php endif; ?>
					<div>
						<strong><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_login ); ?></strong>
						<span class="siqo-account-role"><?php echo esc_html( $role ); ?></span>
						<span class="siqo-muted"><?php echo esc_html( $user->user_login ); ?></span>
					</div>
				</div>

				<form id="siqo-profile-form" class="siqo-form">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'siqo_profile_save' ) ); ?>">
					<div class="siqo-form-row">
						<label><?php esc_html_e( 'Nome visualizzato', 'studio-immens-quick-order' ); ?><input type="text" name="display_name" required value="<?php echo esc_attr( $user->display_name ); ?>"></label>
						<label><?php esc_html_e( 'Email', 'studio-immens-quick-order' ); ?><input type="email" name="user_email" required value="<?php echo esc_attr( $user->user_email ); ?>"></label>
					</div>
					<div class="siqo-form-row">
						<label><?php esc_html_e( 'Nome', 'studio-immens-quick-order' ); ?><input type="text" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>"></label>
						<label><?php esc_html_e( 'Cognome', 'studio-immens-quick-order' ); ?><input type="text" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>"></label>
					</div>
					<div class="siqo-form-row">
						<label><?php esc_html_e( 'Nuova password', 'studio-immens-quick-order' ); ?><input type="password" name="user_pass" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Lascia vuoto per non modificare', 'studio-immens-quick-order' ); ?>"></label>
						<label><?php esc_html_e( 'Conferma password', 'studio-immens-quick-order' ); ?><input type="password" name="user_pass_confirm" autocomplete="new-password"></label>
					</div>
					<div class="siqo-form-row">
						<label><?php esc_html_e( 'Password attuale', 'studio-immens-quick-order' ); ?><input type="password" name="user_pass_current" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Richiesta solo per cambiare email o password', 'studio-immens-quick-order' ); ?>"></label>
					</div>
					<button class="siqo-btn siqo-btn-primary" type="submit"><?php esc_html_e( 'Salva profilo', 'studio-immens-quick-order' ); ?></button>
					<p class="siqo-form-status" data-profile-status aria-live="polite"></p>
				</form>
			</div>
		</section>
		<script type="text/javascript">
		(function () {
			var form = document.getElementById('siqo-profile-form');
			if (!form) {
				return;
			}
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				var status = form.querySelector('[data-profile-status]');
				var body = new FormData(form);
				body.set('action', 'siqo_profile_save');
				if (status) {
					status.textContent = 'Salvataggio...';
					status.classList.remove('is-error');
				}
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				}).then(function (response) {
					return response.json();
				}).then(function (result) {
					if (result && result.success) {
						if (status) {
							status.textContent = 'Profilo aggiornato';
						}
						window.setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						if (status) {
							status.textContent = (result && result.data && result.data.message) ? result.data.message : 'Salvataggio non riuscito';
							status.classList.add('is-error');
						}
					}
				}).catch(function () {
					if (status) {
						status.textContent = 'Salvataggio non riuscito';
						status.classList.add('is-error');
					}
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	public static function ajax_profile_save() {
		check_ajax_referer( 'siqo_profile_save', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Accesso negato.' ), 403 );
		}

		$user_id      = get_current_user_id();
		$user         = wp_get_current_user();
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$email        = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$password     = isset( $_POST['user_pass'] ) ? (string) wp_unslash( $_POST['user_pass'] ) : '';
		$confirm      = isset( $_POST['user_pass_confirm'] ) ? (string) wp_unslash( $_POST['user_pass_confirm'] ) : '';
		$current_pass = isset( $_POST['user_pass_current'] ) ? (string) wp_unslash( $_POST['user_pass_current'] ) : '';

		if ( '' === $display_name ) {
			wp_send_json_error( array( 'message' => 'Il nome visualizzato è obbligatorio.' ), 400 );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email non valida.' ), 400 );
		}
		$existing = email_exists( $email );
		if ( $existing && (int) $existing !== (int) $user_id ) {
			wp_send_json_error( array( 'message' => 'Email già usata da un altro account.' ), 400 );
		}
		if ( '' !== $password && $password !== $confirm ) {
			wp_send_json_error( array( 'message' => 'Le password non coincidono.' ), 400 );
		}

		$email_changed = $user && $user->user_email !== $email;
		if ( '' !== $password || $email_changed ) {
			if ( '' === $current_pass || ! $user || ! wp_check_password( $current_pass, $user->user_pass, $user_id ) ) {
				wp_send_json_error( array( 'message' => 'La password attuale non è corretta.' ), 403 );
			}
		}

		$data = array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
		);
		if ( '' !== $password ) {
			$data['user_pass'] = $password;
		}

		$result = wp_update_user( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => 'Profilo aggiornato.' ) );
	}

	public static function render_dashboard() {
		if ( ! self::can( 'view_dashboard' ) ) {
			return '<div class="siqo-denied">' . esc_html__( 'Non hai i permessi per accedere alla gestione.', 'studio-immens-quick-order' ) . '</div>';
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
		$views = array( 'dashboard', 'tables', 'stations', 'account', 'inventory', 'printers', 'receipts', 'reservations', 'staff', 'reports', 'catering', 'users', 'discounts' );
		if ( ! in_array( $view, $views, true ) ) {
			$view = 'dashboard';
		}

		$panels = array(
			'inventory'    => array( 'SIQO_Inventory', 'manage_inventory' ),
			'printers'     => array( 'SIQO_Printers', 'manage_printers' ),
			'receipts'     => array( 'SIQO_Receipts', 'print_receipts' ),
			'reservations' => array( 'SIQO_Reservations', 'manage_reservations' ),
			'staff'        => array( 'SIQO_Staff', 'manage_staff' ),
			'reports'      => array( 'SIQO_Reports', 'view_reports' ),
			'catering'     => array( 'SIQO_Catering', 'manage_catering' ),
			'users'        => array( 'SIQO_Users', 'manage_users' ),
			'discounts'    => array( 'SIQO_Discounts', 'manage_discounts' ),
		);
		$free_views = array(
			'tables'   => 'manage_tables',
			'stations' => 'view_stations',
			'account'  => 'view_dashboard',
		);

		if ( 'dashboard' !== $view && isset( $free_views[ $view ] ) ) {
			if ( ! self::can( $free_views[ $view ] ) ) {
				return '<div class="siqo-denied">' . esc_html__( 'Modulo non disponibile.', 'studio-immens-quick-order' ) . '</div>';
			}
		} elseif ( 'dashboard' !== $view ) {
			if ( ! isset( $panels[ $view ] ) || ! class_exists( $panels[ $view ][0] ) || ! self::can( $panels[ $view ][1] ) ) {
				return '<div class="siqo-denied">' . esc_html__( 'Modulo non disponibile.', 'studio-immens-quick-order' ) . '</div>';
			}
			if ( ! self::license_active() && 'manager' !== self::role_level() ) {
				return '<div class="siqo-denied">' . esc_html__( 'Licenza Quick Order Pro non attiva.', 'studio-immens-quick-order' ) . '</div>';
			}
		}

		ob_start();
		self::render_shell_start();
		self::render_bar( 'staff' );
		echo '<main class="siqo-main">';
		if ( 'dashboard' === $view ) {
			$stats  = self::get_live_stats();
			$orders = self::get_live_orders( 12 );
			$modules = self::get_modules();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'public/template/management-dashboard.php';
		} elseif ( 'tables' === $view ) {
			echo '<section class="siqo-view siqo-view-tables">';
			echo '<header class="siqo-view-head"><div>';
			echo '<p class="siqo-eyebrow">' . esc_html__( 'Sala', 'studio-immens-quick-order' ) . '</p>';
			echo '<h1>' . esc_html__( 'Gestione tavoli', 'studio-immens-quick-order' ) . '</h1>';
			echo '<p class="siqo-muted">' . esc_html__( 'Apri un tavolo per creare l’ordine o gestire il conto.', 'studio-immens-quick-order' ) . '</p>';
			echo '</div></header>';
			if ( function_exists( 'SIQO_manage_table' ) ) {
				SIQO_manage_table( true );
			}
			echo '</section>';
		} elseif ( 'stations' === $view ) {
			echo '<section class="siqo-view siqo-view-stations">';
			if ( function_exists( 'siqo_station_orders_render' ) ) {
				echo siqo_station_orders_render( true );
			}
			echo '</section>';
		} elseif ( 'account' === $view ) {
			echo self::render_account_panel();
		} else {
			$class = $panels[ $view ][0];
			if ( ! self::license_active() ) {
				echo '<section class="siqo-panel siqo-license-lock">';
				echo '<h2>' . esc_html__( 'Licenza Quick Order Pro non attiva', 'studio-immens-quick-order' ) . '</h2>';
				echo '<p class="siqo-muted">' . esc_html__( 'Attiva la licenza per usare questo modulo. I dati esistenti restano intatti.', 'studio-immens-quick-order' ) . '</p>';
				echo '<a class="siqo-btn siqo-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=si-quick-order_settings&section=license' ) ) . '">' . esc_html__( 'Gestisci licenza', 'studio-immens-quick-order' ) . '</a>';
				echo '</section>';
			} elseif ( method_exists( $class, 'render_panel' ) ) {
				echo $class::render_panel();
			}
		}
		echo '</main>';
		self::render_shell_end();
		return ob_get_clean();
	}

	public static function get_live_stats() {
		$cached = get_transient( 'siqo_live_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stats = array(
			'orders_today'       => 0,
			'revenue_today'      => 0,
			'open_tables'        => 0,
			'low_stock'          => 0,
			'reservations_today' => 0,
			'staff_on_shift'     => 0,
		);

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'      => 1000,
					'status'     => array( 'processing', 'completed', 'on-hold' ),
					'date_after' => current_time( 'Y-m-d' ) . ' 00:00:00',
					'return'     => 'objects',
				)
			);
			foreach ( $orders as $order ) {
				$stats['orders_today']++;
				$stats['revenue_today'] += (float) $order->get_total();
			}
		}

		$tables = get_posts(
			array(
				'post_type'      => 'siqo_tables',
				'posts_per_page' => 200,
				'post_status'    => 'publish',
			)
		);
		foreach ( $tables as $table ) {
			$status = function_exists( 'SIQO_get_meta' ) ? SIQO_get_meta( 'status_table_' . $table->ID ) : '';
			if ( $status && ! in_array( strtolower( (string) $status ), array( '0', 'free', 'libero' ), true ) ) {
				$stats['open_tables']++;
			}
		}

		if ( self::license_active() ) {
			if ( class_exists( 'SIQO_Inventory' ) && method_exists( 'SIQO_Inventory', 'get_low_stock' ) ) {
				$stats['low_stock'] = count( SIQO_Inventory::get_low_stock() );
			}
			if ( class_exists( 'SIQO_Reservations' ) && method_exists( 'SIQO_Reservations', 'get_today_count' ) ) {
				$stats['reservations_today'] = (int) SIQO_Reservations::get_today_count();
			}
			if ( class_exists( 'SIQO_Staff' ) && method_exists( 'SIQO_Staff', 'get_active_count' ) ) {
				$stats['staff_on_shift'] = (int) SIQO_Staff::get_active_count();
			}
		}
		set_transient( 'siqo_live_stats', $stats, (int) apply_filters( 'siqo_live_stats_cache_ttl', 30 ) );
		return $stats;
	}

	public static function get_live_orders( $limit = 12 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'status'  => array( 'processing', 'completed', 'on-hold', 'pending' ),
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		$out = array();
		foreach ( $orders as $order ) {
			$table = $order->get_meta( 'Table_cpt' );
			if ( ! $table ) {
				$table = $order->get_meta( 'Table' );
			}
			if ( ! $table ) {
				$table = $order->get_shipping_address_1();
			}
			$out[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'table'  => $table ? $table : '—',
				'status' => wc_get_order_status_name( $order->get_status() ),
				'slug'   => $order->get_status(),
				'total'  => $order->get_formatted_order_total(),
				'items'  => $order->get_item_count(),
				'time'   => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'H:i' ) : '',
				'url'    => $order->get_edit_order_url(),
			);
		}
		return $out;
	}

	public static function ajax_dashboard() {
		check_ajax_referer( 'siqo_management', 'nonce' );
		if ( ! self::can( 'view_dashboard' ) ) {
			wp_send_json_error( array( 'message' => 'Accesso negato.' ), 403 );
		}
		wp_send_json_success(
			array(
				'stats'  => self::get_live_stats(),
				'orders' => self::get_live_orders( 12 ),
			)
		);
	}

	public static function ajax_save_table_layout() {
		check_ajax_referer( 'SIQO_insert_post_ajax_nonce', 'nonce' );
		if ( ! self::can( 'manage_tables' ) ) {
			wp_send_json_error( array( 'message' => 'Accesso negato.' ), 403 );
		}

		$raw    = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '';
		$layout = json_decode( $raw, true );
		if ( ! is_array( $layout ) ) {
			wp_send_json_error( array( 'message' => 'Disposizione non valida.' ), 400 );
		}

		$saved = 0;
		foreach ( $layout as $item ) {
			$table_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $table_id || 'siqo_tables' !== get_post_type( $table_id ) ) {
				continue;
			}
			$position = array(
				'x' => isset( $item['x'] ) ? absint( $item['x'] ) : 0,
				'y' => isset( $item['y'] ) ? absint( $item['y'] ) : 0,
			);
			update_post_meta( $table_id, '_siqo_plan_position', $position );
			$saved++;
		}

		wp_send_json_success( array( 'saved' => $saved ) );
	}

	public static function handle_settings_save() {
		if ( ! isset( $_POST['siqo_management_action'] ) || 'save' !== $_POST['siqo_management_action'] ) {
			return;
		}
		if ( ! isset( $_POST['_siqo_management_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_siqo_management_nonce'] ) ), 'siqo_management_settings' ) ) {
			return;
		}
		if ( ! self::can( 'manage_settings' ) ) {
			return;
		}

		if ( isset( $_POST['siqo_restaurant_name'] ) ) {
			update_option( 'siqo_restaurant_name', sanitize_text_field( wp_unslash( $_POST['siqo_restaurant_name'] ) ) );
		}
		foreach ( array( 'siqo_brand_primary' => '#a6462f', 'siqo_brand_accent' => '#c98a00' ) as $key => $fallback ) {
			if ( isset( $_POST[ $key ] ) ) {
				$color = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) );
				update_option( $key, $color ? $color : $fallback );
			}
		}
		if ( isset( $_POST['siqo_brand_theme'] ) ) {
			$theme = sanitize_key( wp_unslash( $_POST['siqo_brand_theme'] ) );
			update_option( 'siqo_brand_theme', in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto' );
		}
		if ( isset( $_POST['siqo_quick_order_credit'] ) ) {
			update_option( 'siqo_quick_order_credit', 'no' === sanitize_key( wp_unslash( $_POST['siqo_quick_order_credit'] ) ) ? 'no' : 'yes' );
		}
		foreach ( array( 'siqo_receipt_header', 'siqo_receipt_footer' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		if ( isset( $_POST['siqo_receipt_paper_width'] ) ) {
			$paper_width = sanitize_key( wp_unslash( $_POST['siqo_receipt_paper_width'] ) );
			update_option( 'siqo_receipt_paper_width', in_array( $paper_width, array( '58', '80' ), true ) ? $paper_width : '80' );
		}
		foreach ( array( 'siqo_receipt_show_logo', 'siqo_receipt_auto_print' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, 'no' === sanitize_key( wp_unslash( $_POST[ $key ] ) ) ? 'no' : 'yes' );
			}
		}
		if ( isset( $_POST['siqo_receipt_printer_id'] ) ) {
			update_option( 'siqo_receipt_printer_id', absint( $_POST['siqo_receipt_printer_id'] ) );
		}
		if ( isset( $_POST['siqo_restaurant_logo_url'] ) ) {
			update_option( 'siqo_restaurant_logo_url', esc_url_raw( wp_unslash( $_POST['siqo_restaurant_logo_url'] ) ) );
		}
		if ( isset( $_POST['siqo_management_section'] ) && 'advanced' === sanitize_key( wp_unslash( $_POST['siqo_management_section'] ) ) ) {
			update_option( 'siqo_delete_data_on_uninstall', isset( $_POST['siqo_delete_data_on_uninstall'] ) ? 'yes' : 'no' );
		}
		wp_safe_redirect( add_query_arg( 'siqo_saved', '1', wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=si-quick-order_settings' ) ) );
		exit;
	}

	public static function handle_create_pages() {
		if ( ! self::can( 'manage_settings' ) ) {
			wp_die( esc_html__( 'Accesso negato.', 'studio-immens-quick-order' ) );
		}
		check_admin_referer( 'siqo_create_pages' );
		if ( class_exists( 'Studio_Immens_Quick_Order_Activator' ) ) {
			Studio_Immens_Quick_Order_Activator::activate();
		}
		self::ensure_page();
		wp_safe_redirect( add_query_arg( 'siqo_saved', '1', admin_url( 'admin.php?page=si-quick-order_settings&section=pages' ) ) );
		exit;
	}

	public static function redirect_legacy_pages() {
		if ( ! isset( $_GET['page'] ) || ! self::can( 'manage_settings' ) ) {
			return;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		$map  = array(
			'siqo-warehouse'       => array( 'inventory', 'SIQO_Inventory' ),
			'siqo-print-rules'     => array( 'printers', 'SIQO_Printers' ),
			'siqo-receipts'        => array( 'receipts', 'SIQO_Receipts' ),
			'siqo-reservations'    => array( 'reservations', 'SIQO_Reservations' ),
			'siqo-staff'           => array( 'staff', 'SIQO_Staff' ),
		);
		if ( isset( $map[ $page ] ) ) {
			$section = $map[ $page ][0];
			$class   = $map[ $page ][1];
			if ( $class && ( ! class_exists( $class ) || ! self::license_active() ) ) {
				$section = 'license';
			}
			wp_safe_redirect( admin_url( 'admin.php?page=si-quick-order_settings&section=' . $section ) );
			exit;
		}
	}

	public static function render_settings_page() {
		if ( ! self::can( 'manage_settings' ) ) {
			wp_die( esc_html__( 'Accesso negato.', 'studio-immens-quick-order' ) );
		}
		include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/pages/management-settings.php';
	}
}
