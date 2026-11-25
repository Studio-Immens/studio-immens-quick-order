<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Variables are local to this included template.
$brand = SIQO_Management::get_brand();
$cards = array(
	array( 'key' => 'revenue_today', 'label' => 'Incasso di oggi', 'value' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $stats['revenue_today'] ) ) : number_format_i18n( $stats['revenue_today'], 2 ), 'tone' => 'brand', 'icon' => 'money-alt' ),
	array( 'key' => 'orders_today', 'label' => 'Ordini di oggi', 'value' => number_format_i18n( $stats['orders_today'] ), 'tone' => 'neutral', 'icon' => 'clipboard' ),
	array( 'key' => 'open_tables', 'label' => 'Tavoli aperti', 'value' => number_format_i18n( $stats['open_tables'] ), 'tone' => 'warn', 'icon' => 'grid-view' ),
	array( 'key' => 'low_stock', 'label' => 'Scorte basse', 'value' => number_format_i18n( $stats['low_stock'] ), 'tone' => 'danger', 'icon' => 'warning' ),
	array( 'key' => 'reservations_today', 'label' => 'Prenotazioni oggi', 'value' => number_format_i18n( $stats['reservations_today'] ), 'tone' => 'neutral', 'icon' => 'calendar-alt' ),
	array( 'key' => 'staff_on_shift', 'label' => 'In servizio', 'value' => number_format_i18n( $stats['staff_on_shift'] ), 'tone' => 'ok', 'icon' => 'groups' ),
);
$quick = array();
foreach ( $modules as $module ) {
	if ( in_array( $module['id'], array( 'dashboard', 'settings' ), true ) ) {
		continue;
	}
	$quick[] = $module;
}
?>
<div class="siqo-dashboard">
	<section class="siqo-hero">
		<div>
			<p class="siqo-eyebrow"><?php esc_html_e( 'Centro di controllo', 'studio-immens-quick-order' ); ?></p>
			<h1><?php echo esc_html( $brand['name'] ); ?></h1>
			<p class="siqo-hero-copy"><?php esc_html_e( 'Sala, cucina, magazzino e personale in un unico punto. I dati si aggiornano mentre lavori.', 'studio-immens-quick-order' ); ?></p>
		</div>
		<div class="siqo-hero-actions">
			<a class="siqo-btn siqo-btn-primary" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'dashboard' ) ) ); ?>" data-siqo-refresh>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Aggiorna', 'studio-immens-quick-order' ); ?>
			</a>
			<a class="siqo-btn" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'tables' ) ) ); ?>">
				<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Apri tavolo', 'studio-immens-quick-order' ); ?>
			</a>
		</div>
	</section>

	<section class="siqo-stat-grid" aria-label="<?php esc_attr_e( 'Statistiche di servizio', 'studio-immens-quick-order' ); ?>">
		<?php foreach ( $cards as $card ) : ?>
			<article class="siqo-stat siqo-tone-<?php echo esc_attr( $card['tone'] ); ?>" data-stat="<?php echo esc_attr( $card['key'] ); ?>">
				<span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
				<p><?php echo esc_html( $card['label'] ); ?></p>
				<strong><?php echo esc_html( $card['value'] ); ?></strong>
			</article>
		<?php endforeach; ?>
	</section>

	<section class="siqo-quick-grid">
		<?php foreach ( $quick as $module ) : ?>
			<a class="siqo-quick-card" href="<?php echo esc_url( $module['url'] ); ?>">
				<span class="dashicons dashicons-<?php echo esc_attr( $module['icon'] ? $module['icon'] : 'arrow-right-alt' ); ?>" aria-hidden="true"></span>
				<strong><?php echo esc_html( $module['label'] ); ?></strong>
				<span><?php esc_html_e( 'Apri modulo', 'studio-immens-quick-order' ); ?></span>
			</a>
		<?php endforeach; ?>
	</section>

	<section class="siqo-panel">
		<div class="siqo-panel-head">
			<div>
				<p class="siqo-eyebrow"><?php esc_html_e( 'Servizio', 'studio-immens-quick-order' ); ?></p>
				<h2><?php esc_html_e( 'Ultimi ordini', 'studio-immens-quick-order' ); ?></h2>
			</div>
			<span class="siqo-live"><i></i><?php esc_html_e( 'Aggiornamento live', 'studio-immens-quick-order' ); ?></span>
		</div>
		<div class="siqo-table-wrap">
			<table class="siqo-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ordine', 'studio-immens-quick-order' ); ?></th>
						<th><?php esc_html_e( 'Tavolo', 'studio-immens-quick-order' ); ?></th>
						<th><?php esc_html_e( 'Stato', 'studio-immens-quick-order' ); ?></th>
						<th><?php esc_html_e( 'Voci', 'studio-immens-quick-order' ); ?></th>
						<th><?php esc_html_e( 'Totale', 'studio-immens-quick-order' ); ?></th>
						<th><?php esc_html_e( 'Ora', 'studio-immens-quick-order' ); ?></th>
					</tr>
				</thead>
				<tbody data-siqo-orders>
					<?php if ( empty( $orders ) ) : ?>
						<tr><td colspan="6" class="siqo-empty"><?php esc_html_e( 'Nessun ordine recente.', 'studio-immens-quick-order' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $orders as $order ) : ?>
							<tr>
								<td><a class="siqo-order-link" href="<?php echo esc_url( $order['url'] ); ?>">#<?php echo esc_html( $order['number'] ); ?></a></td>
								<td><?php echo esc_html( $order['table'] ); ?></td>
								<td><span class="siqo-status siqo-status-<?php echo esc_attr( $order['slug'] ); ?>"><?php echo esc_html( $order['status'] ); ?></span></td>
								<td><?php echo esc_html( $order['items'] ); ?></td>
								<td><?php echo wp_kses_post( $order['total'] ); ?></td>
								<td><?php echo esc_html( $order['time'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="siqo-two-col">
		<div class="siqo-panel">
			<div class="siqo-panel-head">
				<div>
					<p class="siqo-eyebrow"><?php esc_html_e( 'Sala', 'studio-immens-quick-order' ); ?></p>
					<h2><?php esc_html_e( 'Tavoli', 'studio-immens-quick-order' ); ?></h2>
				</div>
				<a class="siqo-text-link" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'tables' ) ) ); ?>"><?php esc_html_e( 'Vedi tutti', 'studio-immens-quick-order' ); ?></a>
			</div>
			<div class="siqo-room-grid">
				<?php
				$tables = get_posts( array( 'post_type' => 'siqo_tables', 'posts_per_page' => 12, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
				if ( empty( $tables ) ) :
					?>
					<p class="siqo-empty"><?php esc_html_e( 'Nessun tavolo configurato.', 'studio-immens-quick-order' ); ?></p>
				<?php else : ?>
					<?php foreach ( $tables as $table ) : ?>
						<?php
						$status = function_exists( 'SIQO_get_meta' ) ? SIQO_get_meta( 'status_table_' . $table->ID ) : '';
						$open   = $status && ! in_array( strtolower( (string) $status ), array( '0', 'free', 'libero' ), true );
						?>
						<a class="siqo-room<?php echo $open ? ' is-open' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'table_id', $table->ID, SIQO_Management::page_url( array( 'view' => 'tables' ) ) ) ); ?>">
							<span><?php echo esc_html( $table->post_title ); ?></span>
							<small><?php echo $open ? esc_html__( 'Aperto', 'studio-immens-quick-order' ) : esc_html__( 'Libero', 'studio-immens-quick-order' ); ?></small>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="siqo-panel">
			<div class="siqo-panel-head">
				<div>
					<p class="siqo-eyebrow"><?php esc_html_e( 'Squadra', 'studio-immens-quick-order' ); ?></p>
					<h2><?php esc_html_e( 'Operatività', 'studio-immens-quick-order' ); ?></h2>
				</div>
			</div>
			<ul class="siqo-checklist">
				<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Ordini reparti separati per cucina, bar e pizzeria', 'studio-immens-quick-order' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Magazzino ingredienti con ricette e lotti', 'studio-immens-quick-order' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Prenotazioni, turni e statistiche nella stessa barra', 'studio-immens-quick-order' ); ?></li>
			</ul>
			<?php $shortcuts = array_values( array_filter( $quick, function ( $module ) { return in_array( $module['id'], array( 'inventory', 'reservations', 'reports' ), true ); } ) ); ?>
			<?php if ( $shortcuts ) : ?>
				<div class="siqo-quick-actions">
					<?php foreach ( $shortcuts as $module ) : ?>
						<a class="siqo-btn" href="<?php echo esc_url( $module['url'] ); ?>"><?php echo esc_html( $module['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
