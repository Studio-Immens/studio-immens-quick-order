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
			'label' => 'Dashboard',
			'url'   => self::page_url(),
			'cap'   => 'view_dashboard',
			'icon'  => 'dashboard',
		);
		$tables = self::get_page_url( 'manage-tables' );
		if ( $tables ) {
			$modules[] = array(
				'id'    => 'tables',
				'label' => 'Tavoli',
				'url'   => $tables,
				'cap'   => 'manage_tables',
				'icon'  => 'grid-view',
			);
		}
		$stations = self::get_page_url( 'ordini-reparti' );
		if ( $stations ) {
			$modules[] = array(
				'id'    => 'stations',
				'label' => 'Reparti',
				'url'   => $stations,
				'cap'   => 'view_stations',
				'icon'  => 'food',
			);
		}
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
		if ( empty( $_SERVER['REQUEST_URI'] ) || empty( $module['url'] ) ) {
			return false;
		}
		if ( false !== strpos( $module['url'], 'view=' ) ) {
			return false;
		}
		$request = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		$target  = wp_parse_url( $module['url'], PHP_URL_PATH );
		if ( ! $request || ! $target ) {
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
						<div class="siqo-nav-group<?php echo $group_active ? ' is-active' : ''; ?>" data-siqo-nav-group>
							<button type="button" class="siqo-nav-trigger<?php echo $group_active ? ' is-active' : ''; ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>" aria-haspopup="true">
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

	public static function render_dashboard() {
		if ( ! self::can( 'view_dashboard' ) ) {
			return '<div class="siqo-denied">' . esc_html__( 'Non hai i permessi per accedere alla gestione.', 'studio-immens-quick-order' ) . '</div>';
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
		$views = array( 'dashboard', 'inventory', 'printers', 'receipts', 'reservations', 'staff', 'reports', 'catering', 'users', 'discounts' );
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

		if ( 'dashboard' !== $view ) {
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
					'limit'        => 1000,
					'status'       => array( 'processing', 'completed', 'on-hold' ),
					'date_created' => current_time( 'Y-m-d' ),
					'return'       => 'objects',
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
			'si-quick-order_style' => array( 'brand', '' ),
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
