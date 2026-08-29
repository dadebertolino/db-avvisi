<?php
/**
 * Scheda statistiche: chi ha caricato cosa.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$totals     = DBAV_Stats::totals();
$by_author  = DBAV_Stats::by_author( 200 );
$by_cat     = DBAV_Stats::by_category();
$by_month   = DBAV_Stats::by_month( 12 );
$top_files  = DBAV_Stats::top_downloads( 10 );
$top_views  = DBAV_Stats::top_views( 10 );
$max_month  = $by_month ? max( array_values( $by_month ) ) : 0;
$max_author = $by_author ? max( array_map( function ( $r ) { return (int) $r->avvisi; }, $by_author ) ) : 0;
?>

<div class="dbav-stats-grid">
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-primary" aria-hidden="true">📢</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['total'] ) ); ?></span>
				<span class="db-ui-stat-label"><?php esc_html_e( 'avvisi in archivio', 'db-avvisi' ); ?></span>
			</span>
		</div>
	</div>
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-success" aria-hidden="true">👥</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['authors'] ) ); ?></span>
				<span class="db-ui-stat-label"><?php esc_html_e( 'utenti che hanno pubblicato', 'db-avvisi' ); ?></span>
			</span>
		</div>
	</div>
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-warning" aria-hidden="true">📎</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['files'] ) ); ?></span>
				<span class="db-ui-stat-label">
					<?php
					printf(
						/* translators: %s: spazio occupato. */
						esc_html__( 'allegati · %s', 'db-avvisi' ),
						esc_html( size_format( $totals['bytes'] ) )
					);
					?>
				</span>
			</span>
		</div>
	</div>
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-primary" aria-hidden="true">⬇️</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['downloads'] ) ); ?></span>
				<span class="db-ui-stat-label"><?php esc_html_e( 'download di allegati', 'db-avvisi' ); ?></span>
			</span>
		</div>
	</div>
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-success" aria-hidden="true">👁️</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['views'] ) ); ?></span>
				<span class="db-ui-stat-label"><?php esc_html_e( 'letture totali', 'db-avvisi' ); ?></span>
			</span>
		</div>
	</div>
	<div class="db-ui-card">
		<div class="db-ui-stat">
			<span class="db-ui-stat-icon db-ui-stat-icon-danger" aria-hidden="true">🙈</span>
			<span>
				<span class="db-ui-stat-value"><?php echo esc_html( number_format_i18n( $totals['hidden'] + $totals['expired'] ) ); ?></span>
				<span class="db-ui-stat-label"><?php esc_html_e( 'nascosti o scaduti', 'db-avvisi' ); ?></span>
			</span>
		</div>
	</div>
</div>

<div class="db-ui-card">
	<div class="db-ui-card-header">
		<h2><?php esc_html_e( 'Chi ha caricato cosa', 'db-avvisi' ); ?></h2>
		<a class="db-ui-btn db-ui-btn-sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbav_export_stats' ), 'dbav_export_stats' ) ); ?>">
			<span class="dashicons dashicons-download" aria-hidden="true"></span>
			<?php esc_html_e( 'Esporta CSV', 'db-avvisi' ); ?>
		</a>
	</div>

	<?php if ( ! $by_author ) : ?>
		<div class="db-ui-empty">
			<span class="db-ui-empty-icon" aria-hidden="true">📊</span>
			<p class="db-ui-empty-text"><?php esc_html_e( 'Nessun avviso pubblicato: le statistiche compariranno qui.', 'db-avvisi' ); ?></p>
		</div>
	<?php else : ?>
		<table class="db-ui-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Utente', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Avvisi', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Allegati', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Spazio', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Download', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Letture', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Ultimo avviso', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Azioni', 'db-avvisi' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $by_author as $row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $row->user_name ); ?></strong>
							<?php if ( $row->user_roles ) : ?>
								<br><span class="dbav-filesize"><?php echo esc_html( $row->user_roles ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( number_format_i18n( $row->avvisi ) ); ?></strong>
							<?php if ( $max_author > 0 ) : ?>
								<div class="db-ui-progress">
									<div class="db-ui-progress-fill" style="width:<?php echo esc_attr( round( ( (int) $row->avvisi / $max_author ) * 100 ) ); ?>%"></div>
								</div>
							<?php endif; ?>
							<?php if ( (int) $row->hidden > 0 ) : ?>
								<span class="db-ui-badge db-ui-badge-muted">
									<?php
									printf(
										/* translators: %d: numero di avvisi nascosti. */
										esc_html__( '%d nascosti', 'db-avvisi' ),
										(int) $row->hidden
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $row->files ) ); ?></td>
						<td><?php echo esc_html( size_format( (int) $row->bytes ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row->downloads ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row->views ) ); ?></td>
						<td><?php echo esc_html( DBAV_Admin::date( $row->last_at ) ); ?></td>
						<td>
							<a class="db-ui-btn db-ui-btn-sm" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-gestione', array( 'tab' => 'avvisi', 'author' => (int) $row->user_id ) ) ); ?>">
								<?php esc_html_e( 'Vedi avvisi', 'db-avvisi' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="dbav-two-cols">

	<div class="db-ui-card">
		<div class="db-ui-card-header">
			<h2><?php esc_html_e( 'Avvisi per mese', 'db-avvisi' ); ?></h2>
		</div>
		<?php if ( $max_month > 0 ) : ?>
			<ul class="dbav-bars">
				<?php foreach ( $by_month as $ym => $count ) : ?>
					<li>
						<span class="dbav-bars-label"><?php echo esc_html( mysql2date( 'M Y', $ym . '-01' ) ); ?></span>
						<span class="dbav-bars-track">
							<span class="dbav-bars-fill" style="width:<?php echo esc_attr( round( ( $count / $max_month ) * 100 ) ); ?>%"></span>
						</span>
						<span class="dbav-bars-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="dbav-hint"><?php esc_html_e( 'Ancora nessun dato negli ultimi 12 mesi.', 'db-avvisi' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="db-ui-card">
		<div class="db-ui-card-header">
			<h2><?php esc_html_e( 'Per categoria', 'db-avvisi' ); ?></h2>
		</div>
		<?php if ( $by_cat ) : ?>
			<table class="db-ui-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Categoria', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Avvisi', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Letture', 'db-avvisi' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $by_cat as $row ) : ?>
						<tr>
							<td><?php echo $row->category ? esc_html( $row->category ) : '<em>' . esc_html__( 'senza categoria', 'db-avvisi' ) . '</em>'; ?></td>
							<td><?php echo esc_html( number_format_i18n( $row->total ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row->views ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="dbav-hint"><?php esc_html_e( 'Nessun dato disponibile.', 'db-avvisi' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="db-ui-card">
		<div class="db-ui-card-header">
			<h2><?php esc_html_e( 'Allegati più scaricati', 'db-avvisi' ); ?></h2>
		</div>
		<?php if ( $top_files ) : ?>
			<table class="db-ui-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Avviso', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Download', 'db-avvisi' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_files as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->original_name ); ?></td>
							<td><a href="<?php echo esc_url( DBAV_Admin::url( 'dbav-avvisi', array( 'view' => (int) $row->avviso_id ) ) ); ?>"><?php echo esc_html( $row->title ); ?></a></td>
							<td><strong><?php echo esc_html( number_format_i18n( $row->downloads ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="dbav-hint"><?php esc_html_e( 'Nessun allegato ancora scaricato.', 'db-avvisi' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="db-ui-card">
		<div class="db-ui-card-header">
			<h2><?php esc_html_e( 'Avvisi più letti', 'db-avvisi' ); ?></h2>
		</div>
		<?php if ( $top_views ) : ?>
			<table class="db-ui-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Avviso', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Autore', 'db-avvisi' ); ?></th>
						<th><?php esc_html_e( 'Letture', 'db-avvisi' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_views as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( DBAV_Admin::url( 'dbav-avvisi', array( 'view' => (int) $row->id ) ) ); ?>"><?php echo esc_html( $row->title ); ?></a></td>
							<td><?php echo esc_html( DBAV_Admin::author_name( $row->user_id ) ); ?></td>
							<td><strong><?php echo esc_html( number_format_i18n( $row->views ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="dbav-hint"><?php esc_html_e( 'Nessuna lettura registrata.', 'db-avvisi' ); ?></p>
		<?php endif; ?>
	</div>

</div>
