<?php
/**
 * Elenco avvisi visibile a tutti gli utenti loggati.
 *
 * @package DB_Avvisi
 *
 * @var array $result   Risultato query.
 * @var array $args     Filtri applicati.
 * @var array  $settings Impostazioni.
 * @var string $board    Bacheca mostrata ('scuola' o 'sindacale').
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$labels = dbav_boards();
$labels = $labels[ $board ];
?>
<div class="wrap dbav-wrap">

	<div class="db-ui-page-header">
		<h1><?php echo esc_html( $labels['label'] ); ?></h1>
		<div class="db-ui-actions">
			<?php if ( dbav_can_create( $board ) ) : ?>
				<a class="db-ui-btn db-ui-btn-primary" href="<?php echo esc_url( DBAV_Admin::new_url( $board ) ); ?>">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php echo esc_html( $labels['new'] ); ?>
				</a>
			<?php endif; ?>
			<?php if ( dbav_can_manage() ) : ?>
				<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-gestione' ) ); ?>">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<?php esc_html_e( 'Gestione', 'db-avvisi' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php DBAV_Admin::notices(); ?>

	<?php if ( 'sindacale' === $board ) : ?>
		<p class="dbav-hint"><?php esc_html_e( 'Comunicazioni delle organizzazioni sindacali e della RSU. Pubblica solo chi è stato abilitato dagli amministratori.', 'db-avvisi' ); ?></p>
	<?php endif; ?>

	<form method="get" class="dbav-filters db-ui-card">
		<input type="hidden" name="page" value="<?php echo esc_attr( DBAV_Admin::board_page( $board ) ); ?>">
		<label class="screen-reader-text" for="dbav-search"><?php esc_html_e( 'Cerca negli avvisi', 'db-avvisi' ); ?></label>
		<input class="db-ui-input" type="search" id="dbav-search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Cerca per titolo o testo…', 'db-avvisi' ); ?>">

		<label class="screen-reader-text" for="dbav-cat"><?php esc_html_e( 'Categoria', 'db-avvisi' ); ?></label>
		<select class="db-ui-select" id="dbav-cat" name="cat">
			<option value=""><?php esc_html_e( 'Tutte le categorie', 'db-avvisi' ); ?></option>
			<?php foreach ( (array) $settings['categories'] as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $args['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="dbav-check">
			<input type="checkbox" name="mine" value="1" <?php checked( (int) $args['user_id'], get_current_user_id() ); ?>>
			<?php esc_html_e( 'Solo i miei', 'db-avvisi' ); ?>
		</label>

		<button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e( 'Filtra', 'db-avvisi' ); ?></button>
		<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::board_url( $board ) ); ?>"><?php esc_html_e( 'Azzera', 'db-avvisi' ); ?></a>
	</form>

	<?php if ( empty( $result['items'] ) ) : ?>
		<div class="db-ui-card">
			<div class="db-ui-empty">
				<span class="db-ui-empty-icon" aria-hidden="true">📭</span>
				<p class="db-ui-empty-text"><?php esc_html_e( 'Nessun avviso trovato con questi criteri.', 'db-avvisi' ); ?></p>
			</div>
		</div>
	<?php else : ?>

		<p class="dbav-count">
			<?php
			printf(
				/* translators: %d: numero di avvisi trovati. */
				esc_html( _n( '%d avviso', '%d avvisi', (int) $result['total'], 'db-avvisi' ) ),
				(int) $result['total']
			);
			?>
		</p>

		<div class="dbav-cards">
			<?php foreach ( $result['items'] as $avviso ) : ?>
				<?php
				$files    = DBAV_DB::get_files( $avviso->id );
				$view_url = DBAV_Admin::view_url( $avviso );
				?>
				<article class="db-ui-card dbav-card<?php echo $avviso->pinned ? ' dbav-card-pinned' : ''; ?>">
					<div class="db-ui-card-header">
						<h2>
							<?php if ( $avviso->pinned ) : ?>
								<span class="dashicons dashicons-sticky dbav-pin" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Avviso fissato in alto', 'db-avvisi' ); ?></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $avviso->title ); ?></a>
						</h2>
						<?php if ( $avviso->category ) : ?>
							<span class="db-ui-badge db-ui-badge-primary"><?php echo esc_html( $avviso->category ); ?></span>
						<?php endif; ?>
					</div>

					<div class="db-ui-card-body">
						<p class="dbav-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $avviso->content ), 40 ) ); ?></p>

						<?php if ( $files ) : ?>
							<ul class="dbav-attachments">
								<?php foreach ( $files as $file ) : ?>
									<li>
										<a href="<?php echo esc_url( DBAV_Files::download_url( $file ) ); ?>">
											<span class="dashicons <?php echo esc_attr( DBAV_Files::icon( $file ) ); ?>" aria-hidden="true"></span>
											<?php echo esc_html( $file->original_name ); ?>
										</a>
										<span class="dbav-filesize"><?php echo esc_html( size_format( $file->size ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="db-ui-card-footer dbav-meta">
						<span>
							<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
							<?php echo esc_html( DBAV_Admin::author_name( $avviso->user_id ) ); ?>
						</span>
						<span>
							<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
							<?php echo esc_html( DBAV_Admin::date( $avviso->created_at ) ); ?>
						</span>
						<?php if ( $avviso->expires_at ) : ?>
							<span>
								<span class="dashicons dashicons-clock" aria-hidden="true"></span>
								<?php
								printf(
									/* translators: %s: data di scadenza. */
									esc_html__( 'fino al %s', 'db-avvisi' ),
									esc_html( mysql2date( get_option( 'date_format' ), $avviso->expires_at ) )
								);
								?>
							</span>
						<?php endif; ?>
						<span class="dbav-meta-actions">
							<a class="db-ui-btn db-ui-btn-sm" href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'Apri', 'db-avvisi' ); ?></a>
							<?php if ( dbav_can_edit( $avviso ) ) : ?>
								<a class="db-ui-btn db-ui-btn-sm" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-nuovo', array( 'edit' => (int) $avviso->id ) ) ); ?>"><?php esc_html_e( 'Modifica', 'db-avvisi' ); ?></a>
							<?php endif; ?>
						</span>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php
		DBAV_Admin::pagination(
			$result,
			DBAV_Admin::board_page( $board ),
			array_filter(
				array(
					's'    => $args['search'],
					'cat'  => $args['category'],
					'mine' => $args['user_id'] ? '1' : '',
				)
			)
		);
		?>
	<?php endif; ?>
</div>
