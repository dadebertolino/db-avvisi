<?php
/**
 * Form di pubblicazione / modifica avviso.
 *
 * @package DB_Avvisi
 *
 * @var object|null $avviso   Avviso in modifica.
 * @var array       $files    Allegati esistenti.
 * @var array       $settings Impostazioni.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit  = (bool) $avviso;
$max_size = DBAV_Settings::max_file_bytes();
$accept   = '.' . implode( ',.', (array) $settings['allowed_ext'] );
$default_expiry = '';
if ( ! $is_edit && (int) $settings['default_days'] > 0 ) {
	$default_expiry = gmdate( 'Y-m-d', strtotime( '+' . (int) $settings['default_days'] . ' days', (int) current_time( 'timestamp' ) ) );
}
?>
<div class="wrap dbav-wrap">

	<div class="db-ui-page-header">
		<h1><?php echo $is_edit ? esc_html__( 'Modifica avviso', 'db-avvisi' ) : esc_html__( 'Nuovo avviso', 'db-avvisi' ); ?></h1>
		<div class="db-ui-actions">
			<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-avvisi' ) ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Torna agli avvisi', 'db-avvisi' ); ?>
			</a>
		</div>
	</div>

	<?php DBAV_Admin::notices(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="dbav-form" id="dbav-form">
		<?php wp_nonce_field( 'dbav_save_avviso' ); ?>
		<input type="hidden" name="action" value="dbav_save">
		<input type="hidden" name="avviso_id" value="<?php echo $is_edit ? (int) $avviso->id : 0; ?>">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) $max_size; ?>">

		<div class="dbav-form-grid">

			<div class="db-ui-card">
				<div class="db-ui-card-header">
					<h2><?php esc_html_e( 'Contenuto', 'db-avvisi' ); ?></h2>
				</div>

				<p>
					<label class="dbav-label" for="dbav-title"><?php esc_html_e( 'Titolo', 'db-avvisi' ); ?> <span class="dbav-req" aria-hidden="true">*</span></label>
					<input class="db-ui-input" type="text" id="dbav-title" name="title" required maxlength="255"
						value="<?php echo $is_edit ? esc_attr( $avviso->title ) : ''; ?>"
						placeholder="<?php esc_attr_e( 'Es. Sospensione attività didattica del 12 novembre', 'db-avvisi' ); ?>">
				</p>

				<p>
					<label class="dbav-label" for="dbavcontent"><?php esc_html_e( 'Testo dell\'avviso', 'db-avvisi' ); ?></label>
					<?php
					wp_editor(
						$is_edit ? $avviso->content : '',
						'dbavcontent',
						array(
							'textarea_name' => 'content',
							'textarea_rows' => 12,
							'media_buttons' => false,
							'teeny'         => true,
							'quicktags'     => true,
						)
					);
					?>
				</p>
			</div>

			<div class="dbav-form-side">

				<div class="db-ui-card">
					<div class="db-ui-card-header">
						<h2><?php esc_html_e( 'Pubblicazione', 'db-avvisi' ); ?></h2>
					</div>

					<p>
						<label class="dbav-label" for="dbav-category"><?php esc_html_e( 'Categoria', 'db-avvisi' ); ?></label>
						<select class="db-ui-select" id="dbav-category" name="category">
							<option value=""><?php esc_html_e( '— nessuna —', 'db-avvisi' ); ?></option>
							<?php foreach ( (array) $settings['categories'] as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $is_edit ? $avviso->category : '', $cat ); ?>><?php echo esc_html( $cat ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label class="dbav-label" for="dbav-expires"><?php esc_html_e( 'Visibile fino al', 'db-avvisi' ); ?></label>
						<input class="db-ui-input" type="date" id="dbav-expires" name="expires_at"
							value="<?php echo esc_attr( $is_edit ? (string) $avviso->expires_at : $default_expiry ); ?>">
						<span class="dbav-hint"><?php esc_html_e( 'Lascia vuoto per un avviso senza scadenza.', 'db-avvisi' ); ?></span>
					</p>

					<?php if ( dbav_can_manage() ) : ?>
						<hr class="db-ui-sep">
						<p>
							<label class="dbav-label" for="dbav-status"><?php esc_html_e( 'Stato', 'db-avvisi' ); ?></label>
							<select class="db-ui-select" id="dbav-status" name="status">
								<option value="published" <?php selected( $is_edit ? $avviso->status : 'published', 'published' ); ?>><?php esc_html_e( 'Pubblicato', 'db-avvisi' ); ?></option>
								<option value="hidden" <?php selected( $is_edit ? $avviso->status : 'published', 'hidden' ); ?>><?php esc_html_e( 'Nascosto', 'db-avvisi' ); ?></option>
							</select>
						</p>
						<p>
							<label class="dbav-check">
								<input type="checkbox" name="pinned" value="1" <?php checked( $is_edit ? (int) $avviso->pinned : 0, 1 ); ?>>
								<?php esc_html_e( 'Fissa in cima all\'elenco', 'db-avvisi' ); ?>
							</label>
						</p>
					<?php endif; ?>

					<hr class="db-ui-sep">
					<p class="dbav-submit">
						<button type="submit" class="db-ui-btn db-ui-btn-primary db-ui-btn-lg">
							<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
							<?php echo $is_edit ? esc_html__( 'Salva modifiche', 'db-avvisi' ) : esc_html__( 'Pubblica avviso', 'db-avvisi' ); ?>
						</button>
					</p>
				</div>

				<div class="db-ui-card">
					<div class="db-ui-card-header">
						<h2><?php esc_html_e( 'Allegati', 'db-avvisi' ); ?></h2>
					</div>

					<?php if ( $files ) : ?>
						<ul class="dbav-attachments">
							<?php foreach ( $files as $file ) : ?>
								<li>
									<a href="<?php echo esc_url( DBAV_Files::download_url( $file ) ); ?>">
										<span class="dashicons <?php echo esc_attr( DBAV_Files::icon( $file ) ); ?>" aria-hidden="true"></span>
										<?php echo esc_html( $file->original_name ); ?>
									</a>
									<span class="dbav-filesize"><?php echo esc_html( size_format( $file->size ) ); ?></span>
									<a class="dbav-remove-file dbav-confirm-delete-file"
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_delete_file&file=' . (int) $file->id ), 'dbav_delete_file_' . (int) $file->id ) ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nome file. */ __( 'Elimina allegato %s', 'db-avvisi' ), $file->original_name ) ); ?>">
										<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<hr class="db-ui-sep">
					<?php endif; ?>

					<p>
						<label class="dbav-label" for="dbav-files"><?php esc_html_e( 'Aggiungi file', 'db-avvisi' ); ?></label>
						<input type="file" id="dbav-files" name="attachments[]" multiple accept="<?php echo esc_attr( $accept ); ?>">
					</p>

					<p class="dbav-hint">
						<?php
						printf(
							/* translators: 1: numero massimo di file, 2: dimensione massima, 3: estensioni ammesse. */
							esc_html__( 'Fino a %1$d file per avviso, massimo %2$s ciascuno. Formati ammessi: %3$s.', 'db-avvisi' ),
							(int) $settings['max_files'],
							esc_html( size_format( $max_size ) ),
							esc_html( implode( ', ', (array) $settings['allowed_ext'] ) )
						);
						?>
					</p>
					<p class="dbav-hint">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php esc_html_e( 'Gli allegati sono scaricabili solo dagli utenti che hanno effettuato l\'accesso.', 'db-avvisi' ); ?>
					</p>
					<div id="dbav-file-errors" class="dbav-file-errors" role="alert"></div>
				</div>

			</div>
		</div>
	</form>
</div>
