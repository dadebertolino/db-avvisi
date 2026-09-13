<?php
/**
 * Scheda permessi: regola generale per ruolo ed eccezioni per singolo utente.
 *
 * @package DB_Avvisi
 *
 * @var array $result    Utenti della pagina corrente { items: WP_User[], total, pages, page }.
 * @var array $perm_args Filtri applicati { search, role, show }.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$create_cap      = DBAV_Settings::create_cap();
$roles           = wp_roles()->get_names();
$override_labels = array(
	''      => __( 'Segui la regola generale', 'db-avvisi' ),
	'allow' => __( 'Può pubblicare', 'db-avvisi' ),
	'deny'  => __( 'Non può pubblicare', 'db-avvisi' ),
);
?>

<div class="db-ui-card">
	<div class="db-ui-card-header">
		<h2><?php esc_html_e( 'Regola generale', 'db-avvisi' ); ?></h2>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'dbav_permissions_role' ); ?>
		<input type="hidden" name="action" value="dbav_permissions_role">
		<p>
			<label class="dbav-label" for="dbav-create-cap"><?php esc_html_e( 'Chi può pubblicare avvisi', 'db-avvisi' ); ?></label>
			<select class="db-ui-select" id="dbav-create-cap" name="create_cap">
				<?php foreach ( DBAV_Settings::create_cap_options() as $cap => $label ) : ?>
					<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( $create_cap, $cap ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dbav-hint"><?php esc_html_e( 'Vale per tutti gli utenti senza un\'eccezione personale. Gli amministratori possono sempre pubblicare, e tutti gli utenti loggati continuano a leggere gli avvisi.', 'db-avvisi' ); ?></span>
		</p>
		<p>
			<button type="submit" class="db-ui-btn db-ui-btn-primary">
				<span class="dashicons dashicons-yes" aria-hidden="true"></span>
				<?php esc_html_e( 'Salva regola', 'db-avvisi' ); ?>
			</button>
		</p>
	</form>
</div>

<h2 class="dbav-h2"><?php esc_html_e( 'Eccezioni per utente', 'db-avvisi' ); ?></h2>
<p class="dbav-hint">
	<?php esc_html_e( 'Autorizza o blocca singoli utenti indipendentemente dal loro ruolo. Chi non può pubblicare non può nemmeno modificare o eliminare i propri avvisi: restano gestibili dagli amministratori.', 'db-avvisi' ); ?>
</p>
<p class="dbav-hint">
	<?php esc_html_e( 'Nella colonna "Bacheca sindacale" abiliti chi può pubblicare le comunicazioni sindacali (RSU, rappresentanti): lì la regola per ruolo non vale, serve l\'abilitazione esplicita.', 'db-avvisi' ); ?>
</p>

<form method="get" class="dbav-filters db-ui-card">
	<input type="hidden" name="page" value="dbav-gestione">
	<input type="hidden" name="tab" value="permessi">

	<label class="screen-reader-text" for="dbav-p-search"><?php esc_html_e( 'Cerca utenti', 'db-avvisi' ); ?></label>
	<input class="db-ui-input" type="search" id="dbav-p-search" name="s" value="<?php echo esc_attr( $perm_args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Nome, login o email…', 'db-avvisi' ); ?>">

	<label class="screen-reader-text" for="dbav-p-role"><?php esc_html_e( 'Ruolo', 'db-avvisi' ); ?></label>
	<select class="db-ui-select" id="dbav-p-role" name="role">
		<option value=""><?php esc_html_e( 'Tutti i ruoli', 'db-avvisi' ); ?></option>
		<?php foreach ( $roles as $slug => $name ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $perm_args['role'], $slug ); ?>><?php echo esc_html( translate_user_role( $name ) ); ?></option>
		<?php endforeach; ?>
	</select>

	<label class="screen-reader-text" for="dbav-p-show"><?php esc_html_e( 'Mostra', 'db-avvisi' ); ?></label>
	<select class="db-ui-select" id="dbav-p-show" name="show">
		<option value="" <?php selected( $perm_args['show'], '' ); ?>><?php esc_html_e( 'Tutti gli utenti', 'db-avvisi' ); ?></option>
		<option value="exceptions" <?php selected( $perm_args['show'], 'exceptions' ); ?>><?php esc_html_e( 'Solo con eccezione', 'db-avvisi' ); ?></option>
		<option value="sindacale" <?php selected( $perm_args['show'], 'sindacale' ); ?>><?php esc_html_e( 'Solo abilitati alla bacheca sindacale', 'db-avvisi' ); ?></option>
	</select>

	<button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e( 'Filtra', 'db-avvisi' ); ?></button>
	<a class="db-ui-btn" href="<?php echo esc_url( DBAV_Admin::url( 'dbav-gestione', array( 'tab' => 'permessi' ) ) ); ?>"><?php esc_html_e( 'Azzera', 'db-avvisi' ); ?></a>
</form>

<?php if ( empty( $result['items'] ) ) : ?>
	<div class="db-ui-card">
		<div class="db-ui-empty">
			<span class="db-ui-empty-icon" aria-hidden="true">👥</span>
			<p class="db-ui-empty-text"><?php esc_html_e( 'Nessun utente corrisponde ai filtri.', 'db-avvisi' ); ?></p>
		</div>
	</div>
<?php else : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'dbav_permissions_users' ); ?>
		<input type="hidden" name="action" value="dbav_permissions_users">
		<input type="hidden" name="back_s" value="<?php echo esc_attr( $perm_args['search'] ); ?>">
		<input type="hidden" name="back_role" value="<?php echo esc_attr( $perm_args['role'] ); ?>">
		<input type="hidden" name="back_show" value="<?php echo esc_attr( $perm_args['show'] ); ?>">
		<input type="hidden" name="back_paged" value="<?php echo (int) $result['page']; ?>">

		<div class="dbav-bulkbar">
			<button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e( 'Salva eccezioni', 'db-avvisi' ); ?></button>
			<span class="dbav-count">
				<?php
				printf(
					/* translators: %d: numero di utenti. */
					esc_html( _n( '%d utente', '%d utenti', (int) $result['total'], 'db-avvisi' ) ),
					(int) $result['total']
				);
				?>
			</span>
		</div>

		<table class="db-ui-table dbav-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Utente', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Ruolo', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Regola generale', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Eccezione', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Risultato', 'db-avvisi' ); ?></th>
					<th><?php esc_html_e( 'Bacheca sindacale', 'db-avvisi' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $user ) : ?>
					<?php
					$is_manager = user_can( $user, dbav_manage_cap() );
					$by_role    = user_can( $user, $create_cap );
					$override   = dbav_publish_override( $user->ID );
					$effective  = dbav_user_can_publish( $user->ID );
					$sindacale  = dbav_publish_override( $user->ID, 'sindacale' );
					$role_names = array();
					foreach ( (array) $user->roles as $role ) {
						$role_names[] = isset( $roles[ $role ] ) ? translate_user_role( $roles[ $role ] ) : $role;
					}
					?>
					<tr<?php echo ( $effective || 'allow' === $sindacale ) ? '' : ' class="db-ui-row-muted"'; ?>>
						<td>
							<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
							<span class="dbav-filesize"><?php echo esc_html( $user->user_login . ' · ' . $user->user_email ); ?></span>
						</td>
						<td><?php echo $role_names ? esc_html( implode( ', ', $role_names ) ) : '—'; ?></td>
						<td>
							<?php if ( $is_manager || $by_role ) : ?>
								<span class="db-ui-badge db-ui-badge-success"><?php esc_html_e( 'sì', 'db-avvisi' ); ?></span>
							<?php else : ?>
								<span class="db-ui-badge db-ui-badge-muted"><?php esc_html_e( 'no', 'db-avvisi' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_manager ) : ?>
								<span class="db-ui-badge db-ui-badge-primary"><?php esc_html_e( 'gestore: sempre sì', 'db-avvisi' ); ?></span>
							<?php else : ?>
								<label class="screen-reader-text" for="dbav-publish-<?php echo (int) $user->ID; ?>">
									<?php
									/* translators: %s: nome utente. */
									echo esc_html( sprintf( __( 'Eccezione per %s', 'db-avvisi' ), $user->display_name ) );
									?>
								</label>
								<select class="db-ui-select" id="dbav-publish-<?php echo (int) $user->ID; ?>" name="publish[<?php echo (int) $user->ID; ?>]">
									<?php foreach ( $override_labels as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $override, $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $effective ) : ?>
								<span class="db-ui-badge db-ui-badge-success"><?php esc_html_e( 'può pubblicare', 'db-avvisi' ); ?></span>
							<?php else : ?>
								<span class="db-ui-badge db-ui-badge-danger"><?php esc_html_e( 'non può pubblicare', 'db-avvisi' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_manager ) : ?>
								<span class="db-ui-badge db-ui-badge-primary"><?php esc_html_e( 'gestore: sempre sì', 'db-avvisi' ); ?></span>
							<?php else : ?>
								<label class="screen-reader-text" for="dbav-sindacale-<?php echo (int) $user->ID; ?>">
									<?php
									/* translators: %s: nome utente. */
									echo esc_html( sprintf( __( 'Bacheca sindacale per %s', 'db-avvisi' ), $user->display_name ) );
									?>
								</label>
								<select class="db-ui-select" id="dbav-sindacale-<?php echo (int) $user->ID; ?>" name="sindacale[<?php echo (int) $user->ID; ?>]">
									<option value="" <?php selected( $sindacale, '' ); ?>><?php esc_html_e( 'Non abilitato', 'db-avvisi' ); ?></option>
									<option value="allow" <?php selected( $sindacale, 'allow' ); ?>><?php esc_html_e( 'Può pubblicare', 'db-avvisi' ); ?></option>
								</select>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<button type="submit" class="db-ui-btn db-ui-btn-primary"><?php esc_html_e( 'Salva eccezioni', 'db-avvisi' ); ?></button>
		</p>
	</form>

	<?php
	DBAV_Admin::pagination(
		$result,
		'dbav-gestione',
		array_filter(
			array(
				'tab'  => 'permessi',
				's'    => $perm_args['search'],
				'role' => $perm_args['role'],
				'show' => $perm_args['show'],
			)
		)
	);
	?>
<?php endif; ?>
