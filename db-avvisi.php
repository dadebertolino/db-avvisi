<?php
/**
 * Plugin Name:       DB Avvisi
 * Plugin URI:        https://www.davidebertolino.it/progetti/
 * Description:       Bacheca avvisi interna: ogni utente loggato pubblica avvisi con allegati protetti, gli amministratori gestiscono, moderano e consultano le statistiche. Tutto dentro la bacheca di WordPress, niente servizi esterni.
 * Version:           1.2.0
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

define( 'DBAV_VERSION', '1.2.0' );
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
 * Bacheche disponibili, con le etichette mostrate nell'interfaccia.
 *
 * @return array slug => { label, choice, new, back, widget, all, empty }
 */
function dbav_boards() {
	return array(
		'scuola'    => array(
			'label'  => __( 'Avvisi', 'db-avvisi' ),
			'choice' => __( 'Avvisi della scuola', 'db-avvisi' ),
			'new'    => __( 'Nuovo avviso', 'db-avvisi' ),
			'back'   => __( 'Torna agli avvisi', 'db-avvisi' ),
			'widget' => __( 'Avvisi recenti', 'db-avvisi' ),
			'all'    => __( 'Tutti gli avvisi', 'db-avvisi' ),
			'empty'  => __( 'Nessun avviso pubblicato al momento.', 'db-avvisi' ),
		),
		'sindacale' => array(
			'label'  => __( 'Bacheca sindacale', 'db-avvisi' ),
			'choice' => __( 'Bacheca sindacale', 'db-avvisi' ),
			'new'    => __( 'Nuovo avviso sindacale', 'db-avvisi' ),
			'back'   => __( 'Torna alla bacheca sindacale', 'db-avvisi' ),
			'widget' => __( 'Bacheca sindacale', 'db-avvisi' ),
			'all'    => __( 'Tutta la bacheca sindacale', 'db-avvisi' ),
			'empty'  => __( 'Nessun avviso sindacale pubblicato al momento.', 'db-avvisi' ),
		),
	);
}

/**
 * Normalizza lo slug di una bacheca: tutto ciò che non è "sindacale" è la bacheca della scuola.
 *
 * @param mixed $board Slug grezzo.
 * @return string 'scuola' oppure 'sindacale'.
 */
function dbav_board( $board ) {
	return 'sindacale' === $board ? 'sindacale' : 'scuola';
}

/**
 * Eccezione personale sul permesso di pubblicazione in una bacheca.
 *
 * Salvata come user option: su multisite vale per il singolo sito e sparisce con l'utente.
 * Bacheca scuola: 'allow', 'deny' oppure '' (segue la regola generale).
 * Bacheca sindacale: 'allow' oppure '' (serve l'abilitazione esplicita).
 *
 * @param int    $user_id ID utente.
 * @param string $board   Bacheca.
 * @return string
 */
function dbav_publish_override( $user_id, $board = 'scuola' ) {
	if ( 'sindacale' === dbav_board( $board ) ) {
		return 'allow' === get_user_option( 'dbav_publish_sindacale', (int) $user_id ) ? 'allow' : '';
	}
	$value = get_user_option( 'dbav_publish', (int) $user_id );
	return in_array( $value, array( 'allow', 'deny' ), true ) ? $value : '';
}

/**
 * Un utente specifico può pubblicare in una bacheca?
 * Chi gestisce sempre sì. Bacheca sindacale: solo chi è abilitato uno per uno.
 * Bacheca scuola: l'eccezione personale, altrimenti la regola per ruolo.
 *
 * @param int    $user_id ID utente.
 * @param string $board   Bacheca.
 * @return bool
 */
function dbav_user_can_publish( $user_id, $board = 'scuola' ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( user_can( $user_id, dbav_manage_cap() ) ) {
		return true;
	}
	$board    = dbav_board( $board );
	$override = dbav_publish_override( $user_id, $board );
	if ( 'sindacale' === $board || '' !== $override ) {
		return 'allow' === $override;
	}
	return user_can( $user_id, DBAV_Settings::create_cap() );
}

/**
 * L'utente corrente può pubblicare in una bacheca?
 *
 * @param string $board Bacheca.
 * @return bool
 */
function dbav_can_create( $board = 'scuola' ) {
	$board   = dbav_board( $board );
	$allowed = is_user_logged_in() && ( dbav_can_manage() || dbav_user_can_publish( get_current_user_id(), $board ) );
	return (bool) apply_filters( 'dbav_can_create', $allowed, $board );
}

/**
 * Bacheche in cui l'utente corrente può pubblicare.
 *
 * @return string[]
 */
function dbav_creatable_boards() {
	return array_values( array_filter( array( 'scuola', 'sindacale' ), 'dbav_can_create' ) );
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
	$board   = isset( $avviso->board ) ? $avviso->board : 'scuola';
	$allowed = dbav_can_manage() || ( (int) $avviso->user_id === get_current_user_id() && dbav_can_create( $board ) );
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
