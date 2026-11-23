<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$brand    = SIQO_Management::get_brand();
$app_url  = SIQO_Management::page_url();
$settings = admin_url( 'admin.php?page=si-quick-order_settings' );
$catering = get_page_by_path( 'catering' );

$tables_count   = (int) wp_count_posts( 'siqo_tables' )->publish;
$catering_count = (int) wp_count_posts( 'siqo_catering' )->publish;
$license_on     = SIQO_Management::license_active();

$cards = array(
	array(
		'title' => __( 'Gestione sala', 'studio-immens-quick-order' ),
		'text'  => __( 'Dashboard operativa, comande e stato del servizio.', 'studio-immens-quick-order' ),
		'url'   => $app_url,
		'cta'   => __( 'Apri gestione', 'studio-immens-quick-order' ),
		'icon'  => 'dashboard',
	),
	array(
		'title' => __( 'Tavoli', 'studio-immens-quick-order' ),
		/* translators: %d: number of published tables. */
		'text'  => sprintf( __( '%d tavoli pubblicati.', 'studio-immens-quick-order' ), $tables_count ),
		'url'   => SIQO_Management::page_url( array( 'view' => 'tables' ) ),
		'cta'   => __( 'Apri tavoli', 'studio-immens-quick-order' ),
		'icon'  => 'grid-view',
	),
	array(
		'title' => __( 'Reparti', 'studio-immens-quick-order' ),
		'text'  => __( 'Ordini per cucina, bar e punti di preparazione.', 'studio-immens-quick-order' ),
		'url'   => SIQO_Management::page_url( array( 'view' => 'stations' ) ),
		'cta'   => __( 'Apri reparti', 'studio-immens-quick-order' ),
		'icon'  => 'food',
	),
	array(
		'title' => __( 'Catering', 'studio-immens-quick-order' ),
		/* translators: %d: number of published events. */
		'text'  => sprintf( __( '%d eventi pubblicati.', 'studio-immens-quick-order' ), $catering_count ),
		'url'   => SIQO_Management::page_url( array( 'view' => 'catering' ) ),
		'cta'   => __( 'Gestisci catering', 'studio-immens-quick-order' ),
		'icon'  => 'calendar-alt',
	),
);

if ( class_exists( 'SIQO_Back_Office' ) && SIQO_Back_Office::can_access() ) {
	$cards[] = array(
		'title' => __( 'Back Office', 'studio-immens-quick-order' ),
		'text'  => __( 'Agenda, fornitori, acquisti e scadenze burocratiche.', 'studio-immens-quick-order' ),
		'url'   => SIQO_Back_Office::url(),
		'cta'   => __( 'Apri back office', 'studio-immens-quick-order' ),
		'icon'  => 'businessman',
	);
}
?>
<div class="wrap siqo-admin-dashboard">
	<header class="siqo-admin-hero">
		<div>
			<p><?php echo esc_html( $brand['name'] ); ?></p>
			<h1><?php esc_html_e( 'Quick Order', 'studio-immens-quick-order' ); ?></h1>
			<span><?php esc_html_e( 'Il centro di controllo per sala, cucina, tavoli e catering.', 'studio-immens-quick-order' ); ?></span>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( $app_url ); ?>"><?php esc_html_e( 'Apri gestione', 'studio-immens-quick-order' ); ?></a>
	</header>

	<div class="siqo-admin-dashboard-grid">
		<?php foreach ( $cards as $card ) : ?>
			<article class="siqo-admin-card">
				<span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
				<h2><?php echo esc_html( $card['title'] ); ?></h2>
				<p><?php echo esc_html( $card['text'] ); ?></p>
				<a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['cta'] ); ?> <span aria-hidden="true">&rarr;</span></a>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="siqo-admin-dashboard-columns">
		<section class="siqo-admin-card siqo-admin-card-wide">
			<h2><?php esc_html_e( 'Configurazione', 'studio-immens-quick-order' ); ?></h2>
			<p><?php esc_html_e( 'Brand, pagine, catalogo, stampanti, personale e licenza in un unico pannello.', 'studio-immens-quick-order' ); ?></p>
			<div class="siqo-admin-actions">
				<a class="button button-primary" href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'Apri impostazioni', 'studio-immens-quick-order' ); ?></a>
				<?php if ( class_exists( 'SIQO_Warehouse_Admin' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=siqo-warehouse' ) ); ?>"><?php esc_html_e( 'Magazzino', 'studio-immens-quick-order' ); ?></a>
				<?php endif; ?>
				<?php if ( class_exists( 'SIQO_Print_Rules_Admin' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=siqo-print-rules' ) ); ?>"><?php esc_html_e( 'Regole di stampa', 'studio-immens-quick-order' ); ?></a>
				<?php endif; ?>
				<?php if ( class_exists( 'SIQO_License_Admin' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=si-quick-order_pro' ) ); ?>"><?php esc_html_e( 'Licenza Pro', 'studio-immens-quick-order' ); ?></a>
				<?php endif; ?>
			</div>
		</section>

		<aside class="siqo-admin-card">
			<h2><?php esc_html_e( 'Stato sistema', 'studio-immens-quick-order' ); ?></h2>
			<ul class="siqo-admin-status">
				<li><span><?php esc_html_e( 'WooCommerce', 'studio-immens-quick-order' ); ?></span><strong class="<?php echo class_exists( 'WooCommerce' ) ? 'is-ok' : 'is-off'; ?>"><?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'Attivo', 'studio-immens-quick-order' ) : esc_html__( 'Non attivo', 'studio-immens-quick-order' ); ?></strong></li>
				<li><span><?php esc_html_e( 'Quick Order Pro', 'studio-immens-quick-order' ); ?></span><strong class="<?php echo class_exists( 'SIQO_Inventory' ) ? 'is-ok' : 'is-off'; ?>"><?php echo class_exists( 'SIQO_Inventory' ) ? esc_html__( 'Installato', 'studio-immens-quick-order' ) : esc_html__( 'Non installato', 'studio-immens-quick-order' ); ?></strong></li>
				<li><span><?php esc_html_e( 'Licenza', 'studio-immens-quick-order' ); ?></span><strong class="<?php echo $license_on ? 'is-ok' : 'is-off'; ?>"><?php echo $license_on ? esc_html__( 'Attiva', 'studio-immens-quick-order' ) : esc_html__( 'Non attiva', 'studio-immens-quick-order' ); ?></strong></li>
				<li><span><?php esc_html_e( 'Catering', 'studio-immens-quick-order' ); ?></span><strong class="<?php echo post_type_exists( 'siqo_catering' ) ? 'is-ok' : 'is-off'; ?>"><?php echo post_type_exists( 'siqo_catering' ) ? esc_html__( 'Disponibile', 'studio-immens-quick-order' ) : esc_html__( 'Non disponibile', 'studio-immens-quick-order' ); ?></strong></li>
			</ul>
		</aside>
	</div>
</div>
