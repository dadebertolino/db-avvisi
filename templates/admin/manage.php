<?php
/**
 * Area di gestione: moderazione avvisi, statistiche, impostazioni.
 *
 * @package DB_Avvisi
 *
 * @var string $tab      Scheda attiva.
 * @var array  $settings Impostazioni.
 * @var array  $result   Elenco avvisi (solo scheda "avvisi").
 * @var array  $args      Filtri (solo scheda "avvisi").
 * @var array  $perm_args Filtri utenti (solo scheda "permessi").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'avvisi'       => __( 'Avvisi', 'db-avvisi' ),
	'statistiche'  => __( 'Statistiche', 'db-avvisi' ),
	'permessi'     => __( 'Permessi', 'db-avvisi' ),
	'impostazioni' => __( 'Impostazioni', 'db-avvisi' ),
);
?>
<div class="wrap dbav-wrap">

	<div class="db-ui-page-header">
		<h1><?php esc_html_e( 'Gestione avvisi', 'db-avvisi' ); ?></h1>
		<div class="db-ui-actions">
			<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-avvisi' ) ); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Vedi la bacheca', 'db-avvisi' ); ?>
			</a>
			<a class="db-ui-btn db-ui-btn-primary" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-nuovo' ) ); ?>">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Nuovo avviso', 'db-avvisi' ); ?>
			</a>
		</div>
	</div>

	<?php DBAV_Admin::notices(); ?>

	<nav class="nav-tab-wrapper dbav-tabs">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"
				href="<?php echo esc_url( DBAV_Admin::url( 'dbav-gestione', array( 'tab' => $slug ) ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'statistiche' === $tab ) : ?>
		<?php include DBAV_DIR . 'templates/admin/stats.php'; ?>

	<?php elseif ( 'permessi' === $tab ) : ?>
		<?php include DBAV_DIR . 'templates/admin/permissions.php'; ?>

	<?php elseif ( 'impostazioni' === $tab ) : ?>
		<?php include DBAV_DIR . 'templates/admin/settings.php'; ?>

	<?php else : ?>

		<form method="get" class="dbav-filters db-ui-card">
			<input type="hidden" name="page" value="dbav-gestione">
			<input type="hidden" name="tab" value="avvisi">

			<label class="screen-reader-text" for="dbav-m-search"><?php esc_html_e( 'Cerca', 'db-avvisi' ); ?></label>
			<input class="db-ui-input" type="search" id="dbav-m-search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Cerca…', 'db-avvisi' ); ?>">

			<label class="screen-reader-text" for="dbav-m-cat"><?php esc_html_e( 'Categoria', 'db-avvisi' ); ?></label>
			<select class="db-ui-select" id="dbav-m-cat" name="cat">
				<option value=""><?php esc_html_e( 'Tutte le categorie', 'db-avvisi' ); ?></option>
				<?php foreach ( (array) $settings['categories'] as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $args['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="dbav-m-board"><?php esc_html_e( 'Bacheca', 'db-avvisi' ); ?></label>
			<select class="db-ui-select" id="dbav-m-board" name="board">
				<option value="" <?php selected( $args['board'], '' ); ?>><?php esc_html_e( 'Tutte le bacheche', 'db-avvisi' ); ?></option>
				<?php foreach ( dbav_boards() as $slug => $board_labels ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $args['board'], $slug ); ?>><?php echo esc_html( $board_labels['choice'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="dbav-m-status"><?php esc_html_e( 'Stato', 'db-avvisi' ); ?></label>
			<select class="db-ui-select" id="dbav-m-status" name="status">
				<option value="" <?php selected( $args['status'], '' ); ?>><?php esc_html_e( 'Tutti gli stati', 'db-avvisi' ); ?></option>
				<option value="published" <?php selected( $args['status'], 'published' ); ?>><?php esc_html_e( 'Pubblicati', 'db-avvisi' ); ?></option>
				<option value="hidden" <?php selected( $args['status'], 'hidden' ); ?>><?php esc_html_e( 'Nascosti', 'db-avvisi' ); ?></option>
			</select>

			<label class="screen-reader-text" for="dbav-m-author"><?php esc_html_e( 'Autore', 'db-avvisi' ); ?></label>
			<?php
			wp_dropdown_users(
				array(
					'name'            => 'author',
					'id'              => 'dbav-m-author',
					'selected'        => (int) $args['user_id'],
					'show_option_all' => __( 'Tutti gli autori', 'db-avvisi' ),
					'class'           => 'db-ui-select',
					'who'             => '',
				)
			);
			?>

			<button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e( 'Filtra', 'db-avvisi' ); ?></button>
			<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-gestione' ) ); ?>"><?php esc_html_e( 'Azzera', 'db-avvisi' ); ?></a>
		</form>

		<?php if ( empty( $result['items'] ) ) : ?>
			<div class="db-ui-card">
				<div class="db-ui-empty">
					<span class="db-ui-empty-icon" aria-hidden="true">📭</span>
					<p class="db-ui-empty-text"><?php esc_html_e( 'Nessun avviso corrisponde ai filtri.', 'db-avvisi' ); ?></p>
				</div>
			</div>
		<?php else : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dbav-bulk-form">
				<?php wp_nonce_field( 'dbav_bulk' ); ?>
				<input type="hidden" name="action" value="dbav_bulk">

				<div class="dbav-bulkbar">
					<label class="screen-reader-text" for="dbav-bulk-action"><?php esc_html_e( 'Azione di gruppo', 'db-avvisi' ); ?></label>
					<select class="db-ui-select" name="bulk_action" id="dbav-bulk-action">
						<option value=""><?php esc_html_e( 'Azioni di gruppo', 'db-avvisi' ); ?></option>
						<option value="publish"><?php esc_html_e( 'Pubblica', 'db-avvisi' ); ?></option>
						<option value="hide"><?php esc_html_e( 'Nascondi', 'db-avvisi' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Elimina definitivamente', 'db-avvisi' ); ?></option>
					</select>
					<button type="submit" class="db-ui-btn dbav-bulk-submit"><?php esc_html_e( 'Applica', 'db-avvisi' ); ?></button>
					<span class="dbav-count">
						<?php
						printf(
							/* translators: %d: numero di avvisi. */
							esc_html( _n( '%d avviso', '%d avvisi', (int) $result['total'], 'db-avvisi' ) ),
							(int) $result['total']
						);
						?>
					</span>
				</div>

				<table class="db-ui-table dbav-table">
					<thead>
						<tr>
							<th class="dbav-col-check">
								<input type="checkbox" id="dbav-check-all" aria-label="<?php esc_attr_e( 'Seleziona tutti', 'db-avvisi' ); ?>">
							</th>
							<th><?php esc_html_e( 'Avviso', 'db-avvisi' ); ?></th>
							<th><?php esc_html_e( 'Autore', 'db-avvisi' ); ?></th>
							<th><?php esc_html_e( 'Data', 'db-avvisi' ); ?></th>
							<th><?php esc_html_e( 'Allegati', 'db-avvisi' ); ?></th>
							<th><?php esc_html_e( 'Letture', 'db-avvisi' ); ?></th>
							<th><?php esc_html_e( 'Stato', 'db-avvisi' ); ?></th>
							<th class="dbav-col-actions"><?php esc_html_e( 'Azioni', 'db-avvisi' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $result['items'] as $avviso ) : ?>
							<?php
							$files    = DBAV_DB::get_files( $avviso->id );
							$downloads = 0;
							foreach ( $files as $f ) {
								$downloads += (int) $f->downloads;
							}
							$expired = $avviso->expires_at && $avviso->expires_at < current_time( 'Y-m-d' );
							?>
							<tr<?php echo 'published' !== $avviso->status ? ' class="db-ui-row-muted"' : ''; ?>>
								<td><input type="checkbox" name="ids[]" value="<?php echo (int) $avviso->id; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titolo avviso. */ __( 'Seleziona %s', 'db-avvisi' ), $avviso->title ) ); ?>"></td>
								<td>
									<?php if ( $avviso->pinned ) : ?>
										<span class="dashicons dashicons-sticky dbav-pin" aria-hidden="true" title="<?php esc_attr_e( 'Fissato in alto', 'db-avvisi' ); ?>"></span>
									<?php endif; ?>
									<a href="<?php echo esc_url( DBAV_Admin::view_url( $avviso ) ); ?>"><strong><?php echo esc_html( $avviso->title ); ?></strong></a>
									<?php $is_sindacale = 'sindacale' === dbav_board( $avviso->board ); ?>
									<?php if ( $is_sindacale || $avviso->category ) : ?>
										<br>
									<?php endif; ?>
									<?php if ( $is_sindacale ) : ?>
										<span class="db-ui-badge db-ui-badge-warning"><?php esc_html_e( 'sindacale', 'db-avvisi' ); ?></span>
									<?php endif; ?>
									<?php if ( $avviso->category ) : ?>
										<span class="db-ui-badge db-ui-badge-primary"><?php echo esc_html( $avviso->category ); ?></span>
									<?php endif; ?>
									<?php if ( $expired ) : ?>
										<span class="db-ui-badge db-ui-badge-warning"><?php esc_html_e( 'scaduto', 'db-avvisi' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( DBAV_Admin::author_name( $avviso->user_id ) ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $avviso->created_at ) ); ?></td>
								<td>
									<?php echo esc_html( number_format_i18n( count( $files ) ) ); ?>
									<?php if ( $downloads ) : ?>
										<span class="dbav-filesize">
											<?php
											printf(
												/* translators: %s: numero di download. */
												esc_html__( '%s download', 'db-avvisi' ),
												esc_html( number_format_i18n( $downloads ) )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( $avviso->views ) ); ?></td>
								<td>
									<?php if ( 'published' === $avviso->status ) : ?>
										<span class="db-ui-badge db-ui-badge-success"><?php esc_html_e( 'pubblicato', 'db-avvisi' ); ?></span>
									<?php else : ?>
										<span class="db-ui-badge db-ui-badge-muted"><?php esc_html_e( 'nascosto', 'db-avvisi' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="dbav-col-actions">
									<a class="db-ui-btn db-ui-btn-sm db-ui-btn-icon db-ui-tip"
										data-tooltip="<?php echo 'published' === $avviso->status ? esc_attr__( 'Nascondi', 'db-avvisi' ) : esc_attr__( 'Pubblica', 'db-avvisi' ); ?>"
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_toggle&what=status&id=' . (int) $avviso->id ), 'dbav_toggle_' . (int) $avviso->id ) ); ?>">
										<span class="dashicons <?php echo 'published' === $avviso->status ? 'dashicons-hidden' : 'dashicons-visibility'; ?>" aria-hidden="true"></span>
									</a>
									<a class="db-ui-btn db-ui-btn-sm db-ui-btn-icon db-ui-tip"
										data-tooltip="<?php echo $avviso->pinned ? esc_attr__( 'Sblocca dall\'alto', 'db-avvisi' ) : esc_attr__( 'Fissa in alto', 'db-avvisi' ); ?>"
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_toggle&what=pinned&id=' . (int) $avviso->id ), 'dbav_toggle_' . (int) $avviso->id ) ); ?>">
										<span class="dashicons dashicons-sticky" aria-hidden="true"></span>
									</a>
									<a class="db-ui-btn db-ui-btn-sm db-ui-btn-icon db-ui-tip" data-tooltip="<?php esc_attr_e( 'Modifica', 'db-avvisi' ); ?>"
										href="<?php echo esc_url( DBAV_Admin::url( 'dbav-nuovo', array( 'edit' => (int) $avviso->id ) ) ); ?>">
										<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									</a>
									<a class="db-ui-btn db-ui-btn-sm db-ui-btn-icon db-ui-btn-danger db-ui-tip dbav-confirm-delete" data-tooltip="<?php esc_attr_e( 'Elimina', 'db-avvisi' ); ?>"
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_delete&back=dbav-gestione&id=' . (int) $avviso->id ), 'dbav_delete_' . (int) $avviso->id ) ); ?>">
										<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>

			<?php
			DBAV_Admin::pagination(
				$result,
				'dbav-gestione',
				array_filter(
					array(
						'tab'    => 'avvisi',
						's'      => $args['search'],
						'cat'    => $args['category'],
						'board'  => $args['board'],
						'status' => $args['status'],
						'author' => $args['user_id'] ? (int) $args['user_id'] : '',
					)
				)
			);
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
