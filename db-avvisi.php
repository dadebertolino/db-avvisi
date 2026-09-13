<?php
/**
 * Plugin Name:       DB Avvisi
 * Plugin URI:        https://www.davidebertolino.it/progetti/
 * Description:       Bacheca avvisi interna: ogni utente loggato pubblica avvisi con allegati protetti, gli amministratori gestiscono, moderano e consultano le statistiche. Tutto dentro la bacheca di WordPress, niente servizi esterni.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Davide Bertolino
 * Author URI:        https://www.davidebertolino.it
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       db-avvisi
 * Domain Path:       /languages
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DBAV_VERSION', '1.1.0' );
define( 'DBAV_FILE', __FILE__ );
define( 'DBAV_DIR', plugin_dir_path( __FILE__ ) );
define( 'DBAV_URL', plugin_dir_url( __FILE__ ) );
define( 'DBAV_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Privacy capabilities:
 *  - Dati personali:      SÌ — autore dell'avviso (user_id WordPress) e contenuti caricati.
 *  - Script di terze parti: NO
 *  - Consenso utente:     NO — trattamento interno all'area riservata, base giuridica organizzativa.
 *  - Solo utenti loggati: SÌ — nessun accesso anonimo, nemmeno agli allegati.
 */

require_once DBAV_DIR . 'inc/class-settings.php';
require_once DBAV_DIR . 'inc/class-db.php';
require_once DBAV_DIR . 'inc/class-files.php';
require_once DBAV_DIR . 'inc/class-stats.php';
require_once DBAV_DIR . 'inc/class-actions.php';
require_once DBAV_DIR . 'inc/class-admin.php';
require_once DBAV_DIR . 'inc/class-dashboard-widget.php';

if ( file_exists( DBAV_DIR . 'inc/class-updater.php' ) ) {
	require_once DBAV_DIR . 'inc/class-updater.php';
}

/**
 * Un utente può vedere gli avvisi?
 *
 * @return bool
 */
function dbav_can_view() {
	return (bool) apply_filters( 'dbav_can_view', is_user_logged_in() );
}

/**
 * Un utente può pubblicare avvisi?
 *
 * @return bool
 */
function dbav_can_create() {
	return (bool) apply_filters( 'dbav_can_create', is_user_logged_in() && current_user_can( DBAV_Settings::create_cap() ) );
}

/**
 * Capability richiesta per gestire avvisi e statistiche.
 *
 * @return string
 */
function dbav_manage_cap() {
	return (string) apply_filters( 'dbav_manage_capability', 'manage_options' );
}

/**
 * Un utente può gestire tutti gli avvisi e vedere le statistiche?
 *
 * @return bool
 */
function dbav_can_manage() {
	return (bool) apply_filters( 'dbav_can_manage', current_user_can( dbav_manage_cap() ) );
}

/**
 * Un utente può modificare/eliminare uno specifico avviso?
 *
 * @param object|null $avviso Record avviso.
 * @return bool
 */
function dbav_can_edit( $avviso ) {
	if ( ! $avviso ) {
		return false;
	}
	$allowed = dbav_can_manage() || ( (int) $avviso->user_id === get_current_user_id() && dbav_can_create() );
	return (bool) apply_filters( 'dbav_can_edit', $allowed, $avviso );
}

/**
 * Avvio del plugin.
 */
function dbav_bootstrap() {
	load_plugin_textdomain( 'db-avvisi', false, dirname( DBAV_BASENAME ) . '/languages' );

	DBAV_Actions::init();
	DBAV_Admin::init();
	DBAV_Dashboard_Widget::init();

	if ( class_exists( 'DB_GitHub_Updater' ) ) {
		new DB_GitHub_Updater( DBAV_FILE, 'dadebertolino', 'db-avvisi' );
	}
}
add_action( 'plugins_loaded', 'dbav_bootstrap' );

/**
 * Attivazione: crea tabelle e cartella protetta degli allegati.
 */
function dbav_activate() {
	DBAV_DB::install();
	DBAV_Files::protect_upload_dir();
	DBAV_Settings::install_defaults();
	delete_transient( 'dbav_protection_check' );
	update_option( 'dbav_version', DBAV_VERSION );
}
register_activation_hook( __FILE__, 'dbav_activate' );

/**
 * Aggiornamento silenzioso dello schema quando il plugin viene aggiornato via GitHub.
 */
function dbav_maybe_upgrade() {
	if ( get_option( 'dbav_version' ) !== DBAV_VERSION ) {
		DBAV_DB::install();
		DBAV_Files::protect_upload_dir();
		DBAV_Settings::install_defaults();
		update_option( 'dbav_version', DBAV_VERSION );
	}
}
add_action( 'admin_init', 'dbav_maybe_upgrade' );
