<?php
defined( 'ABSPATH' ) || exit;

function siqo_table_session_ajax() {
	if ( ! isset( $_POST['_siqo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_siqo_nonce'] ) ), 'siqo_table_session' ) ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 403 );
	}

	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accesso negato.', 'studio-immens-quick-order' ) ), 403 );
	}

	if ( ! class_exists( 'SIQO_Table_Sessions' ) ) {
		wp_send_json_error( array( 'message' => __( 'Gestione tavoli non disponibile.', 'studio-immens-quick-order' ) ), 500 );
	}

	$table_id = isset( $_POST['table_id'] ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;

	$hook   = preg_replace( '/^wp_ajax_(?:nopriv_)?/', '', (string) current_action() );
	$action = '';
	if ( 'siqo_table_open' === $hook ) {
		$action = 'open';
	} elseif ( 'siqo_table_close' === $hook ) {
		$action = 'close';
	} elseif ( 'siqo_tables_close_all' === $hook ) {
		$action = 'close_all';
	}

	if ( '' === $action ) {
		wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 400 );
	}

	if ( isset( $_POST['do'] ) ) {
		$posted_action = sanitize_key( wp_unslash( $_POST['do'] ) );
		$allowed_do    = 'close_all' === $action ? array( 'close_all' ) : array( 'open', 'close' );
		if ( ! in_array( $posted_action, $allowed_do, true ) || $posted_action !== $action ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'studio-immens-quick-order' ) ), 400 );
		}
	}

	if ( 'close_all' === $action ) {
		$closed = SIQO_Table_Sessions::close_all();
		if ( $closed > 0 ) {
			$message = sprintf(
				/* translators: %d: number of closed tables. */
				_n( '%d tavolo chiuso.', '%d tavoli chiusi.', $closed, 'studio-immens-quick-order' ),
				$closed
			);
		} else {
			$message = __( 'Nessun tavolo attivo da chiudere.', 'studio-immens-quick-order' );
		}
		wp_send_json_success(
			array(
				'closed'  => (int) $closed,
				'message' => $message,
			)
		);
	}

	if ( ! $table_id || 'siqo_tables' !== get_post_type( $table_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Tavolo non valido.', 'studio-immens-quick-order' ) ), 400 );
	}

	if ( 'close' === $action ) {
		SIQO_Table_Sessions::close( $table_id, 'closed' );
		wp_send_json_success(
			array(
				'table_id' => $table_id,
				'active'   => false,
				'label'    => __( 'Non attivo', 'studio-immens-quick-order' ),
			)
		);
	}

	$result = SIQO_Table_Sessions::open( $table_id, get_current_user_id(), 'list' );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	$session = $result['session'];
	/* translators: %s: session expiry time. */
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
add_action( 'wp_ajax_siqo_tables_close_all', 'siqo_table_session_ajax' );

function siqo_table_session_render_bar( $compact = false ) {
	if ( ! class_exists( 'SIQO_Management' ) || ! SIQO_Management::can( 'manage_tables' ) ) {
		return '';
	}

	$tables = get_posts(
		array(
			'post_type'   => 'siqo_tables',
			'numberposts' => 100,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'post_status' => 'publish',
		)
	);
	$active_ids = class_exists( 'SIQO_Table_Sessions' ) ? SIQO_Table_Sessions::active_table_ids() : array();
	$expiries   = array();
	foreach ( $active_ids as $active_id ) {
		$active_session = SIQO_Table_Sessions::get_active_for_table( $active_id );
		if ( $active_session ) {
			$expiries[ (int) $active_id ] = $active_session->expires_at;
		}
	}

	ob_start();
	?>
	<div class="siqo-table-session-bar<?php echo $compact ? ' is-compact' : ''; ?>"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'siqo_table_session' ) ); ?>"
		data-label-open="<?php esc_attr_e( 'Apri', 'studio-immens-quick-order' ); ?>"
		data-label-close="<?php esc_attr_e( 'Chiudi', 'studio-immens-quick-order' ); ?>"
		data-confirm-close-all="<?php esc_attr_e( 'Chiudere tutti i tavoli attivi?', 'studio-immens-quick-order' ); ?>">
		<div class="siqo-table-session-head">
			<div class="siqo-table-session-head-text">
				<h2 class="siqo-table-session-title"><?php esc_html_e( 'Tavoli', 'studio-immens-quick-order' ); ?></h2>
				<p class="siqo-table-session-help"><?php esc_html_e( 'Apri un tavolo per permettere agli ospiti di ordinare dal menù.', 'studio-immens-quick-order' ); ?></p>
			</div>
			<?php if ( ! empty( $tables ) ) : ?>
				<button type="button" class="siqo-table-close-all" data-table-close-all<?php disabled( empty( $active_ids ) ); ?>>
					<?php esc_html_e( 'Chiudi tutti', 'studio-immens-quick-order' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( empty( $tables ) ) : ?>
			<p class="siqo-table-session-empty"><?php esc_html_e( 'Nessun tavolo configurato.', 'studio-immens-quick-order' ); ?></p>
		<?php else : ?>
			<ul class="siqo-table-grid">
				<?php foreach ( $tables as $siqo_table ) : ?>
					<?php
					$siqo_table_id = (int) $siqo_table->ID;
					$siqo_active   = in_array( $siqo_table_id, $active_ids, true );
					$siqo_expires  = $siqo_active && isset( $expiries[ $siqo_table_id ] ) ? (string) $expiries[ $siqo_table_id ] : '';
					$siqo_label    = $siqo_active
						? ( $siqo_expires
							? sprintf( __( 'Attivo fino alle %s', 'studio-immens-quick-order' ), wp_date( 'H:i', strtotime( $siqo_expires . ' UTC' ) ) )
							: __( 'Attivo', 'studio-immens-quick-order' ) )
						: __( 'Non attivo', 'studio-immens-quick-order' );
					?>
					<li class="siqo-table-chip<?php echo $siqo_active ? ' is-active' : ''; ?>" data-table-id="<?php echo esc_attr( $siqo_table_id ); ?>" data-expires="<?php echo esc_attr( $siqo_expires ); ?>">
						<span class="siqo-table-chip-body">
							<span class="siqo-table-chip-name"><?php echo esc_html( $siqo_table->post_title ); ?></span>
							<span class="siqo-table-chip-status"><?php echo esc_html( $siqo_label ); ?></span>
						</span>
						<button type="button" class="siqo-table-toggle<?php echo $siqo_active ? ' is-close' : ' is-open'; ?>" data-do="<?php echo $siqo_active ? 'close' : 'open'; ?>" aria-pressed="<?php echo $siqo_active ? 'true' : 'false'; ?>">
							<?php echo $siqo_active ? esc_html__( 'Chiudi', 'studio-immens-quick-order' ) : esc_html__( 'Apri', 'studio-immens-quick-order' ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p class="siqo-table-session-status" role="status" aria-live="polite"></p>
	</div>
	<?php
	return ob_get_clean();
}
