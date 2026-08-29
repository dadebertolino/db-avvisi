<?php
/**
 * Scheda impostazioni.
 *
 * @package DB_Avvisi
 *
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protection = DBAV_Files::check_protection();
$server_max = wp_max_upload_size();
?>

<?php if ( false === $protection ) : ?>
	<div class="db-ui-alert db-ui-alert-danger">
		<span class="db-ui-alert-icon" aria-hidden="true">⛔</span>
		<span>
			<strong><?php esc_html_e( 'Attenzione: la cartella degli allegati è raggiungibile dal web.', 'db-avvisi' ); ?></strong><br>
			<?php
			printf(
				/* translators: %s: percorso della cartella. */
				esc_html__( 'Il server non applica il file .htaccess in %s (tipico di Nginx). Chiedi all\'hosting di negare l\'accesso diretto a questa cartella: i nomi dei file sono casuali, ma senza questa regola un indirizzo indovinato resta scaricabile senza login.', 'db-avvisi' ),
				'<code>' . esc_html( str_replace( ABSPATH, '', DBAV_Files::basedir() ) ) . '</code>'
			);
			?>
		</span>
	</div>
<?php elseif ( true === $protection ) : ?>
	<div class="db-ui-alert db-ui-alert-success">
		<span class="db-ui-alert-icon" aria-hidden="true">✅</span>
		<span><?php esc_html_e( 'La cartella degli allegati non è raggiungibile dal web: i file passano solo dal download controllato.', 'db-avvisi' ); ?></span>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'dbav_settings' ); ?>
	<input type="hidden" name="action" value="dbav_settings">

	<div class="dbav-two-cols">

		<div class="db-ui-card">
			<div class="db-ui-card-header">
				<h2><?php esc_html_e( 'Categorie', 'db-avvisi' ); ?></h2>
			</div>
			<p>
				<label class="dbav-label" for="dbav-categories"><?php esc_html_e( 'Una categoria per riga', 'db-avvisi' ); ?></label>
				<textarea class="db-ui-textarea" id="dbav-categories" name="categories" rows="8"><?php echo esc_textarea( implode( "\n", (array) $settings['categories'] ) ); ?></textarea>
				<span class="dbav-hint"><?php esc_html_e( 'Se rimuovi una categoria, gli avvisi che la usavano restano invariati ma non saranno più filtrabili.', 'db-avvisi' ); ?></span>
			</p>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header">
				<h2><?php esc_html_e( 'Allegati', 'db-avvisi' ); ?></h2>
			</div>
			<p>
				<label class="dbav-label" for="dbav-max-files"><?php esc_html_e( 'Numero massimo di allegati per avviso', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="number" id="dbav-max-files" name="max_files" min="1" max="20" value="<?php echo esc_attr( $settings['max_files'] ); ?>">
			</p>
			<p>
				<label class="dbav-label" for="dbav-max-size"><?php esc_html_e( 'Dimensione massima per file (MB)', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="number" id="dbav-max-size" name="max_file_mb" min="1" max="200" value="<?php echo esc_attr( $settings['max_file_mb'] ); ?>">
				<span class="dbav-hint">
					<?php
					printf(
						/* translators: %s: limite del server. */
						esc_html__( 'Il server accetta al massimo %s: il limite più basso fra i due è quello che vale.', 'db-avvisi' ),
						esc_html( size_format( $server_max ) )
					);
					?>
				</span>
			</p>
			<p>
				<label class="dbav-label" for="dbav-ext"><?php esc_html_e( 'Estensioni ammesse', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="text" id="dbav-ext" name="allowed_ext" value="<?php echo esc_attr( implode( ', ', (array) $settings['allowed_ext'] ) ); ?>">
				<span class="dbav-hint"><?php esc_html_e( 'Separate da virgola. Le estensioni eseguibili (php, js, html, svg, exe…) sono sempre rifiutate, anche se le scrivi qui.', 'db-avvisi' ); ?></span>
			</p>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header">
				<h2><?php esc_html_e( 'Elenco e scadenze', 'db-avvisi' ); ?></h2>
			</div>
			<p>
				<label class="dbav-label" for="dbav-per-page"><?php esc_html_e( 'Avvisi per pagina', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="number" id="dbav-per-page" name="per_page" min="5" max="200" value="<?php echo esc_attr( $settings['per_page'] ); ?>">
			</p>
			<p>
				<label class="dbav-label" for="dbav-default-days"><?php esc_html_e( 'Scadenza proposta per i nuovi avvisi (giorni)', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="number" id="dbav-default-days" name="default_days" min="0" max="3650" value="<?php echo esc_attr( $settings['default_days'] ); ?>">
				<span class="dbav-hint"><?php esc_html_e( '0 = nessuna scadenza proposta. È solo un valore precompilato: chi pubblica può cambiarlo.', 'db-avvisi' ); ?></span>
			</p>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header">
				<h2><?php esc_html_e( 'Notifiche', 'db-avvisi' ); ?></h2>
			</div>
			<p>
				<label class="dbav-label" for="dbav-notify"><?php esc_html_e( 'Email da avvisare a ogni nuovo avviso', 'db-avvisi' ); ?></label>
				<input class="db-ui-input" type="text" id="dbav-notify" name="notify_emails" value="<?php echo esc_attr( $settings['notify_emails'] ); ?>" placeholder="segreteria@esempio.it, vicepresidenza@esempio.it">
				<span class="dbav-hint"><?php esc_html_e( 'Lascia vuoto per non inviare nulla. Se il server non spedisce email in modo affidabile, la notifica può non arrivare: la bacheca resta comunque aggiornata.', 'db-avvisi' ); ?></span>
			</p>
		</div>

	</div>

	<p>
		<button type="submit" class="db-ui-btn db-ui-btn-primary db-ui-btn-lg">
			<span class="dashicons dashicons-yes" aria-hidden="true"></span>
			<?php esc_html_e( 'Salva impostazioni', 'db-avvisi' ); ?>
		</button>
	</p>
</form>
