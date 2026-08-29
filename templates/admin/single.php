<?php
/**
 * Vista singolo avviso.
 *
 * @package DB_Avvisi
 *
 * @var object $avviso Record avviso.
 * @var array  $files  Allegati.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap dbav-wrap dbav-single">

	<div class="db-ui-page-header">
		<h1><?php echo esc_html( $avviso->title ); ?></h1>
		<div class="db-ui-actions">
			<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-avvisi' ) ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Torna agli avvisi', 'db-avvisi' ); ?>
			</a>
			<?php if ( dbav_can_edit( $avviso ) ) : ?>
				<a class="db-ui-btn db-ui-btn-primary" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-nuovo', array( 'edit' => (int) $avviso->id ) ) ); ?>">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<?php esc_html_e( 'Modifica', 'db-avvisi' ); ?>
				</a>
				<a class="db-ui-btn db-ui-btn-danger dbav-confirm-delete"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_delete&id=' . (int) $avviso->id ), 'dbav_delete_' . (int) $avviso->id ) ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Elimina', 'db-avvisi' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php DBAV_Admin::notices(); ?>

	<?php if ( 'published' !== $avviso->status ) : ?>
		<div class="db-ui-alert db-ui-alert-warning">
			<span class="db-ui-alert-icon" aria-hidden="true">⚠️</span>
			<span><?php esc_html_e( 'Questo avviso è nascosto: non è visibile agli altri utenti.', 'db-avvisi' ); ?></span>
		</div>
	<?php endif; ?>

	<div class="dbav-single-grid">

		<div class="db-ui-card">
			<div class="db-ui-card-body dbav-content">
				<?php echo wp_kses_post( wpautop( $avviso->content ) ); ?>
			</div>

			<?php if ( $files ) : ?>
				<hr class="db-ui-sep">
				<h2 class="dbav-h2">
					<?php
					printf(
						/* translators: %d: numero di allegati. */
						esc_html( _n( '%d allegato', '%d allegati', count( $files ), 'db-avvisi' ) ),
						count( $files )
					);
					?>
				</h2>
				<ul class="dbav-attachments dbav-attachments-lg">
					<?php foreach ( $files as $file ) : ?>
						<li>
							<a href="<?php echo esc_url( DBAV_Files::download_url( $file ) ); ?>">
								<span class="dashicons <?php echo esc_attr( DBAV_Files::icon( $file ) ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $file->original_name ); ?>
							</a>
							<span class="dbav-filesize"><?php echo esc_html( size_format( $file->size ) ); ?></span>
							<?php if ( dbav_can_manage() ) : ?>
								<span class="db-ui-badge db-ui-badge-muted">
									<?php
									printf(
										/* translators: %d: numero di download. */
										esc_html( _n( '%d download', '%d download', (int) $file->downloads, 'db-avvisi' ) ),
										(int) $file->downloads
									);
									?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<aside class="db-ui-card dbav-sidebar">
			<div class="db-ui-card-header">
				<h2><?php esc_html_e( 'Dettagli', 'db-avvisi' ); ?></h2>
			</div>
			<ul class="dbav-details">
				<li>
					<span class="dbav-details-label"><?php esc_html_e( 'Pubblicato da', 'db-avvisi' ); ?></span>
					<strong><?php echo esc_html( DBAV_Admin::author_name( $avviso->user_id ) ); ?></strong>
				</li>
				<li>
					<span class="dbav-details-label"><?php esc_html_e( 'Data', 'db-avvisi' ); ?></span>
					<strong><?php echo esc_html( DBAV_Admin::date( $avviso->created_at ) ); ?></strong>
				</li>
				<?php if ( $avviso->updated_at && $avviso->updated_at !== $avviso->created_at ) : ?>
					<li>
						<span class="dbav-details-label"><?php esc_html_e( 'Ultima modifica', 'db-avvisi' ); ?></span>
						<strong><?php echo esc_html( DBAV_Admin::date( $avviso->updated_at ) ); ?></strong>
					</li>
				<?php endif; ?>
				<?php if ( $avviso->category ) : ?>
					<li>
						<span class="dbav-details-label"><?php esc_html_e( 'Categoria', 'db-avvisi' ); ?></span>
						<span class="db-ui-badge db-ui-badge-primary"><?php echo esc_html( $avviso->category ); ?></span>
					</li>
				<?php endif; ?>
				<?php if ( $avviso->expires_at ) : ?>
					<li>
						<span class="dbav-details-label"><?php esc_html_e( 'Visibile fino al', 'db-avvisi' ); ?></span>
						<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $avviso->expires_at ) ); ?></strong>
					</li>
				<?php endif; ?>
				<?php if ( dbav_can_manage() ) : ?>
					<li>
						<span class="dbav-details-label"><?php esc_html_e( 'Letture', 'db-avvisi' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $avviso->views ) ); ?></strong>
					</li>
				<?php endif; ?>
			</ul>
		</aside>
	</div>
</div>
