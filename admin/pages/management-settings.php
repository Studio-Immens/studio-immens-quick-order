<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$brand   = SIQO_Management::get_brand();
$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'brand';
$sections = array(
	'brand'        => array( 'Ristorante e brand', 'Identità, colori e crediti.' ),
	'pages'        => array( 'Pagine e accessi', 'Pagine applicative e ruoli.' ),
	'catalog'      => array( 'Catalogo', 'Prodotti, categorie e reparti.' ),
	'inventory'    => array( 'Magazzino', 'Ingredienti, ricette, lotti e scorte.' ),
	'printers'     => array( 'Stampanti', 'Stampanti, routing e coda.' ),
	'receipts'     => array( 'Scontrini', 'Comande e ricevute non fiscali.' ),
	'reservations' => array( 'Prenotazioni', 'Servizio, tavoli e disponibilità.' ),
	'staff'        => array( 'Personale', 'Ruoli, turni e timbrature.' ),
	'advanced'     => array( 'Avanzate', 'Compatibilità, manutenzione e disinstallazione.' ),
	'license'      => array( 'Licenza', 'Stato Quick Order Pro.' ),
);
if ( ! isset( $sections[ $section ] ) ) {
	$section = 'brand';
}
$page_url = admin_url( 'admin.php?page=si-quick-order_settings' );
?>
<div class="siqo-admin-settings">
	<?php if ( isset( $_GET['siqo_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Impostazioni salvate.', 'studio-immens-quick-order' ); ?></p></div>
	<?php endif; ?>

	<header class="siqo-admin-hero">
		<div>
			<p><?php esc_html_e( 'Quick Order', 'studio-immens-quick-order' ); ?></p>
			<h1><?php esc_html_e( 'Impostazioni', 'studio-immens-quick-order' ); ?></h1>
			<span><?php esc_html_e( 'Un unico posto per ristorante, sala, magazzino, stampanti e personale.', 'studio-immens-quick-order' ); ?></span>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( SIQO_Management::page_url() ); ?>"><?php esc_html_e( 'Apri gestione', 'studio-immens-quick-order' ); ?></a>
	</header>

	<nav class="siqo-admin-tabs" aria-label="<?php esc_attr_e( 'Sezioni impostazioni', 'studio-immens-quick-order' ); ?>">
		<?php foreach ( $sections as $key => $item ) : ?>
			<a class="<?php echo $section === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'section', $key, $page_url ) ); ?>"><?php echo esc_html( $item[0] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="siqo-admin-layout">
		<aside class="siqo-admin-aside">
			<p class="siqo-admin-kicker"><?php echo esc_html( $sections[ $section ][0] ); ?></p>
			<p><?php echo esc_html( $sections[ $section ][1] ); ?></p>
			<?php if ( $section === 'brand' ) : ?>
				<div class="siqo-admin-preview">
					<img src="<?php echo esc_url( $brand['logo'] ); ?>" alt="">
					<strong><?php echo esc_html( $brand['name'] ); ?></strong>
					<span><?php esc_html_e( 'Anteprima brand', 'studio-immens-quick-order' ); ?></span>
				</div>
			<?php endif; ?>
		</aside>

		<div class="siqo-admin-panel">
			<?php if ( 'brand' === $section ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'siqo_management_settings', '_siqo_management_nonce' ); ?>
					<input type="hidden" name="siqo_management_action" value="save">
					<div class="siqo-admin-section">
						<h2><?php esc_html_e( 'Identità del ristorante', 'studio-immens-quick-order' ); ?></h2>
						<label><?php esc_html_e( 'Nome visualizzato', 'studio-immens-quick-order' ); ?>
							<input type="text" name="siqo_restaurant_name" value="<?php echo esc_attr( $brand['name'] ); ?>" class="regular-text">
						</label>
						<label><?php esc_html_e( 'URL logo', 'studio-immens-quick-order' ); ?>
							<input type="url" name="siqo_restaurant_logo_url" value="<?php echo esc_url( $brand['logo'] ); ?>" class="regular-text">
						</label>
						<label><?php esc_html_e( 'Mostra credito Quick Order', 'studio-immens-quick-order' ); ?>
							<select name="siqo_quick_order_credit">
								<option value="yes"<?php selected( $brand['credit'], 'yes' ); ?>><?php esc_html_e( 'Sì, discreto', 'studio-immens-quick-order' ); ?></option>
								<option value="no"<?php selected( $brand['credit'], 'no' ); ?>><?php esc_html_e( 'No, solo brand ristorante', 'studio-immens-quick-order' ); ?></option>
							</select>
						</label>
					</div>
					<div class="siqo-admin-section siqo-admin-colors">
						<h2><?php esc_html_e( 'Colori', 'studio-immens-quick-order' ); ?></h2>
						<label><?php esc_html_e( 'Colore principale', 'studio-immens-quick-order' ); ?>
							<input type="color" name="siqo_brand_primary" value="<?php echo esc_attr( $brand['primary'] ); ?>">
						</label>
						<label><?php esc_html_e( 'Colore accento', 'studio-immens-quick-order' ); ?>
							<input type="color" name="siqo_brand_accent" value="<?php echo esc_attr( $brand['accent'] ); ?>">
						</label>
						<label><?php esc_html_e( 'Tema predefinito', 'studio-immens-quick-order' ); ?>
							<select name="siqo_brand_theme">
								<option value="auto"<?php selected( $brand['theme'], 'auto' ); ?>><?php esc_html_e( 'Automatico per contesto', 'studio-immens-quick-order' ); ?></option>
								<option value="light"<?php selected( $brand['theme'], 'light' ); ?>><?php esc_html_e( 'Chiaro', 'studio-immens-quick-order' ); ?></option>
								<option value="dark"<?php selected( $brand['theme'], 'dark' ); ?>><?php esc_html_e( 'Scuro', 'studio-immens-quick-order' ); ?></option>
							</select>
						</label>
					</div>
					<div class="siqo-admin-section">
						<h2><?php esc_html_e( 'Scontrini', 'studio-immens-quick-order' ); ?></h2>
						<label><?php esc_html_e( 'Intestazione', 'studio-immens-quick-order' ); ?>
							<textarea name="siqo_receipt_header" rows="2"><?php echo esc_textarea( $brand['receipt_head'] ); ?></textarea>
						</label>
						<label><?php esc_html_e( 'Piè di pagina', 'studio-immens-quick-order' ); ?>
							<textarea name="siqo_receipt_footer" rows="2"><?php echo esc_textarea( $brand['receipt_foot'] ); ?></textarea>
						</label>
					</div>
					<?php submit_button( __( 'Salva brand', 'studio-immens-quick-order' ) ); ?>
				</form>

			<?php elseif ( 'pages' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Pagine applicative', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Queste pagine contengono gli shortcode del plugin. Puoi ricrearle senza perdere il contenuto esistente.', 'studio-immens-quick-order' ); ?></p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Pagina', 'studio-immens-quick-order' ); ?></th><th><?php esc_html_e( 'Slug', 'studio-immens-quick-order' ); ?></th><th><?php esc_html_e( 'Stato', 'studio-immens-quick-order' ); ?></th></tr></thead>
						<tbody>
							<?php
							$pages = array(
								'gestione'      => 'gestione',
								'manage-tables' => 'manage-tables',
								'ordini-reparti'=> 'ordini-reparti',
								'quick-menu'    => 'Quick Menu',
							);
							foreach ( $pages as $slug => $label ) :
								$page = get_page_by_path( $slug );
								?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td><code><?php echo esc_html( $slug ); ?></code></td>
									<td><?php echo $page ? esc_html__( 'Presente', 'studio-immens-quick-order' ) : esc_html__( 'Mancante', 'studio-immens-quick-order' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="siqo_create_pages">
						<?php wp_nonce_field( 'siqo_create_pages' ); ?>
						<?php submit_button( __( 'Crea o ripara pagine', 'studio-immens-quick-order' ), 'secondary' ); ?>
					</form>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Ruoli e accessi', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'La gerarchia è worker, supervisor e manager. L’amministratore WordPress ha sempre accesso completo.', 'studio-immens-quick-order' ); ?></p>
					<ul class="siqo-admin-list">
						<li><strong>Worker</strong> cruscotto, tavoli, reparti e stampa ricevute.</li>
						<li><strong>Supervisor</strong> magazzino, stampanti, prenotazioni e statistiche.</li>
						<li><strong>Manager</strong> personale, impostazioni e licenza.</li>
					</ul>
				</div>

			<?php elseif ( 'catalog' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Catalogo WooCommerce', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Prodotti, categorie, varianti e prezzi restano gestiti da WooCommerce. Quick Order usa il catalogo senza duplicarlo.', 'studio-immens-quick-order' ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Apri prodotti', 'studio-immens-quick-order' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ); ?>"><?php esc_html_e( 'Apri categorie', 'studio-immens-quick-order' ); ?></a>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Reparti di preparazione', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'I reparti sono le stazioni che ricevono le comande: cucina, bar, pizzeria e altre postazioni.', 'studio-immens-quick-order' ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=siqo_station&post_type=product' ) ); ?>"><?php esc_html_e( 'Gestisci reparti', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'inventory' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Magazzino e ricette', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Lo stock prodotto resta in WooCommerce. Quick Order Pro aggiunge ingredienti, ricette, lotti, fornitori e inventario.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php esc_html_e( 'Stato modulo:', 'studio-immens-quick-order' ); ?></strong> <?php echo class_exists( 'SIQO_Inventory' ) ? esc_html__( 'installato', 'studio-immens-quick-order' ) : esc_html__( 'non installato', 'studio-immens-quick-order' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'inventory' ) ) ); ?>"><?php esc_html_e( 'Apri magazzino', 'studio-immens-quick-order' ); ?></a>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Riconciliazione scorte', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Il plugin non duplica lo stock prodotto: legge la giacenza WooCommerce e registra solo i movimenti ingrediente.', 'studio-immens-quick-order' ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=stock' ) ); ?>"><?php esc_html_e( 'Report stock WooCommerce', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'printers' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Stampanti e coda', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Configura stampanti di rete ESC/POS, stampa browser o PrintNode. Ogni stampante può essere associata a un reparto.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php esc_html_e( 'Stato modulo:', 'studio-immens-quick-order' ); ?></strong> <?php echo class_exists( 'SIQO_Printers' ) ? esc_html__( 'installato', 'studio-immens-quick-order' ) : esc_html__( 'non installato', 'studio-immens-quick-order' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'printers' ) ) ); ?>"><?php esc_html_e( 'Apri stampanti', 'studio-immens-quick-order' ); ?></a>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Regole di stampa esistenti', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Le regole Pro per zona, categoria e tipo ordine sono gestite nella pagina dedicata.', 'studio-immens-quick-order' ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=siqo-print-rules' ) ); ?>"><?php esc_html_e( 'Apri regole', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'receipts' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Comande e ricevute', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Quick Order stampa comande di reparto e ricevute non fiscali. Non gestisce scontrini fiscali, RT o IVA.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php esc_html_e( 'Stato modulo:', 'studio-immens-quick-order' ); ?></strong> <?php echo esc_html__( 'integrato', 'studio-immens-quick-order' ); ?></p>
					<p><?php esc_html_e( 'Preconto e ricevuta usano il motore interno: non serve WooPrint né ReceiptJet.', 'studio-immens-quick-order' ); ?></p>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Formato documento', 'studio-immens-quick-order' ); ?></h2>
					<?php
					$receipt_printer_id      = absint( get_option( 'siqo_receipt_printer_id', 0 ) );
					$receipt_printer_options = array();
					if ( class_exists( 'SIQO_Printers' ) && method_exists( 'SIQO_Printers', 'get_printers' ) ) {
						foreach ( SIQO_Printers::get_printers() as $receipt_printer ) {
							if ( in_array( $receipt_printer->type, array( 'network', 'printnode' ), true ) ) {
								$receipt_printer_options[ (int) $receipt_printer->id ] = $receipt_printer->name;
							}
						}
					}
					?>
					<form method="post">
						<?php wp_nonce_field( 'siqo_management_settings', '_siqo_management_nonce' ); ?>
						<input type="hidden" name="siqo_management_action" value="save">
						<label><?php esc_html_e( 'Larghezza carta', 'studio-immens-quick-order' ); ?>
							<select name="siqo_receipt_paper_width">
								<option value="80"<?php selected( get_option( 'siqo_receipt_paper_width', '80' ), '80' ); ?>><?php esc_html_e( '80 mm', 'studio-immens-quick-order' ); ?></option>
								<option value="58"<?php selected( get_option( 'siqo_receipt_paper_width', '80' ), '58' ); ?>><?php esc_html_e( '58 mm', 'studio-immens-quick-order' ); ?></option>
							</select>
						</label>
						<label><?php esc_html_e( 'Logo in testata', 'studio-immens-quick-order' ); ?>
							<select name="siqo_receipt_show_logo">
								<option value="yes"<?php selected( get_option( 'siqo_receipt_show_logo', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Sì', 'studio-immens-quick-order' ); ?></option>
								<option value="no"<?php selected( get_option( 'siqo_receipt_show_logo', 'yes' ), 'no' ); ?>><?php esc_html_e( 'No', 'studio-immens-quick-order' ); ?></option>
							</select>
						</label>
						<label><?php esc_html_e( 'Stampa automatica conto pagato', 'studio-immens-quick-order' ); ?>
							<select name="siqo_receipt_auto_print">
								<option value="no"<?php selected( get_option( 'siqo_receipt_auto_print', 'no' ), 'no' ); ?>><?php esc_html_e( 'No', 'studio-immens-quick-order' ); ?></option>
								<option value="yes"<?php selected( get_option( 'siqo_receipt_auto_print', 'no' ), 'yes' ); ?>><?php esc_html_e( 'Sì, con licenza Pro', 'studio-immens-quick-order' ); ?></option>
							</select>
						</label>
						<label><?php esc_html_e( 'Stampante predefinita', 'studio-immens-quick-order' ); ?>
							<?php if ( $receipt_printer_options ) : ?>
								<select name="siqo_receipt_printer_id">
									<option value="0"><?php esc_html_e( 'Nessuna', 'studio-immens-quick-order' ); ?></option>
									<?php foreach ( $receipt_printer_options as $receipt_printer_option_id => $receipt_printer_option_name ) : ?>
										<option value="<?php echo esc_attr( $receipt_printer_option_id ); ?>"<?php selected( $receipt_printer_id, $receipt_printer_option_id ); ?>><?php echo esc_html( $receipt_printer_option_name ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="number" min="0" step="1" name="siqo_receipt_printer_id" value="<?php echo esc_attr( $receipt_printer_id ); ?>" class="small-text">
							<?php endif; ?>
						</label>
						<p class="description"><?php esc_html_e( 'La stampa automatica richiede Quick Order Pro con licenza attiva. La stampa browser resta sempre disponibile.', 'studio-immens-quick-order' ); ?></p>
						<?php submit_button( __( 'Salva impostazioni ricevute', 'studio-immens-quick-order' ) ); ?>
					</form>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Testo ricevuta', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Intestazione e piè di pagina si modificano nella sezione Ristorante e brand.', 'studio-immens-quick-order' ); ?></p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'section', 'brand', $page_url ) ); ?>"><?php esc_html_e( 'Modifica testo', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'reservations' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Prenotazioni interne', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Gestisci prenotazioni, assegnazione tavoli e stati. Il booking pubblico resta fuori da Quick Order.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php esc_html_e( 'Stato modulo:', 'studio-immens-quick-order' ); ?></strong> <?php echo class_exists( 'SIQO_Reservations' ) ? esc_html__( 'installato', 'studio-immens-quick-order' ) : esc_html__( 'non installato', 'studio-immens-quick-order' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'reservations' ) ) ); ?>"><?php esc_html_e( 'Apri prenotazioni', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'staff' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Personale e turni', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Timbrature semplici, turno aperto e storico. Nessuna elaborazione payroll.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php esc_html_e( 'Stato modulo:', 'studio-immens-quick-order' ); ?></strong> <?php echo class_exists( 'SIQO_Staff' ) ? esc_html__( 'installato', 'studio-immens-quick-order' ) : esc_html__( 'non installato', 'studio-immens-quick-order' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( SIQO_Management::page_url( array( 'view' => 'staff' ) ) ); ?>"><?php esc_html_e( 'Apri personale', 'studio-immens-quick-order' ); ?></a>
				</div>

			<?php elseif ( 'advanced' === $section ) : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Compatibilità', 'studio-immens-quick-order' ); ?></h2>
					<ul class="siqo-admin-list">
						<li><?php esc_html_e( 'WooCommerce:', 'studio-immens-quick-order' ); ?> <strong><?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'attivo', 'studio-immens-quick-order' ) : esc_html__( 'non attivo', 'studio-immens-quick-order' ); ?></strong></li>
						<li><?php esc_html_e( 'Quick Order Pro:', 'studio-immens-quick-order' ); ?> <strong><?php echo class_exists( 'SIQO_Inventory' ) || class_exists( 'SIQO_Printers' ) ? esc_html__( 'installato', 'studio-immens-quick-order' ) : esc_html__( 'non installato', 'studio-immens-quick-order' ); ?></strong></li>
						<li><?php esc_html_e( 'Licenza:', 'studio-immens-quick-order' ); ?> <strong><?php echo SIQO_Management::license_active() ? esc_html__( 'attiva', 'studio-immens-quick-order' ) : esc_html__( 'non attiva', 'studio-immens-quick-order' ); ?></strong></li>
					</ul>
				</div>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Disinstallazione', 'studio-immens-quick-order' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'siqo_management_settings', '_siqo_management_nonce' ); ?>
						<input type="hidden" name="siqo_management_action" value="save">
						<input type="hidden" name="siqo_management_section" value="advanced">
						<label class="siqo-check"><input type="checkbox" name="siqo_delete_data_on_uninstall" value="yes"<?php checked( get_option( 'siqo_delete_data_on_uninstall' ), 'yes' ); ?>> <?php esc_html_e( 'Elimina dati, pagine e tabelle alla disinstallazione', 'studio-immens-quick-order' ); ?></label>
						<p class="description"><?php esc_html_e( 'Disattivato per impostazione predefinita. Le opzioni restano intatte durante gli aggiornamenti.', 'studio-immens-quick-order' ); ?></p>
						<?php submit_button( __( 'Salva preferenza', 'studio-immens-quick-order' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>

			<?php else : ?>
				<div class="siqo-admin-section">
					<h2><?php esc_html_e( 'Licenza Quick Order Pro', 'studio-immens-quick-order' ); ?></h2>
					<p><?php esc_html_e( 'Le funzioni Pro richiedono una licenza attiva. I moduli installati ma non licenziati restano visibili solo ai manager.', 'studio-immens-quick-order' ); ?></p>
					<p><strong><?php echo SIQO_Management::license_active() ? esc_html__( 'Licenza attiva', 'studio-immens-quick-order' ) : esc_html__( 'Licenza non attiva', 'studio-immens-quick-order' ); ?></strong></p>
					<?php if ( class_exists( 'SIQO_License' ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=si-quick-order_pro' ) ); ?>"><?php esc_html_e( 'Gestisci licenza', 'studio-immens-quick-order' ); ?></a>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Installa Quick Order Pro per attivare la licenza e i moduli avanzati.', 'studio-immens-quick-order' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
