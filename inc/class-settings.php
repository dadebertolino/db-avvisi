<?php
/**
 * Impostazioni del plugin (option unica, con default sicuri).
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Settings {

	const OPTION = 'dbav_settings';

	/**
	 * Valori di default.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'categories'    => array( 'Generale', 'Didattica', 'Amministrativo', 'Sicurezza', 'Eventi' ),
			'max_file_mb'   => 10,
			'max_files'     => 5,
			'allowed_ext'   => array( 'pdf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip' ),
			'per_page'      => 20,
			'notify_emails' => '',
			'default_days'  => 0,
			'create_cap'    => 'read',
		);
	}

	/**
	 * Capability selezionabili per "Chi può pubblicare", in ordine dal ruolo più ampio.
	 *
	 * @return array capability => etichetta.
	 */
	public static function create_cap_options() {
		return array(
			'read'              => __( 'Tutti gli utenti loggati (compresi i Sottoscrittori)', 'db-avvisi' ),
			'edit_posts'        => __( 'Collaboratori e ruoli superiori', 'db-avvisi' ),
			'publish_posts'     => __( 'Autori e ruoli superiori', 'db-avvisi' ),
			'edit_others_posts' => __( 'Editori e amministratori', 'db-avvisi' ),
		);
	}

	/**
	 * Capability richiesta per pubblicare, validata contro l'elenco ammesso.
	 *
	 * @return string
	 */
	public static function create_cap() {
		$cap = (string) self::get( 'create_cap', 'read' );
		return in_array( $cap, array( 'read', 'edit_posts', 'publish_posts', 'edit_others_posts' ), true ) ? $cap : 'read';
	}

	/**
	 * Restituisce tutte le impostazioni, con i default a colmare i buchi.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Legge una singola impostazione.
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $default Valore di fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Salva le impostazioni ripulite.
	 *
	 * @param array $input Dati grezzi dal form.
	 * @return array Impostazioni salvate.
	 */
	public static function save( array $input ) {
		$defaults = self::defaults();
		$clean    = self::all();

		if ( isset( $input['categories'] ) ) {
			$cats = is_array( $input['categories'] ) ? $input['categories'] : explode( "\n", (string) $input['categories'] );
			$cats = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $cats ) ) );
			$cats = array_values( array_unique( $cats ) );
			$clean['categories'] = $cats ? $cats : $defaults['categories'];
		}

		if ( isset( $input['max_file_mb'] ) ) {
			$clean['max_file_mb'] = max( 1, min( 200, (int) $input['max_file_mb'] ) );
		}

		if ( isset( $input['max_files'] ) ) {
			$clean['max_files'] = max( 1, min( 20, (int) $input['max_files'] ) );
		}

		if ( isset( $input['per_page'] ) ) {
			$clean['per_page'] = max( 5, min( 200, (int) $input['per_page'] ) );
		}

		if ( isset( $input['default_days'] ) ) {
			$clean['default_days'] = max( 0, min( 3650, (int) $input['default_days'] ) );
		}

		if ( isset( $input['allowed_ext'] ) ) {
			$raw = is_array( $input['allowed_ext'] ) ? implode( ',', $input['allowed_ext'] ) : (string) $input['allowed_ext'];
			$ext = preg_split( '/[\s,]+/', strtolower( $raw ) );
			$ext = array_filter( array_map( function ( $e ) {
				return preg_replace( '/[^a-z0-9]/', '', $e );
			}, (array) $ext ) );
			$ext = array_values( array_unique( array_diff( $ext, self::forbidden_ext() ) ) );
			$clean['allowed_ext'] = $ext ? $ext : $defaults['allowed_ext'];
		}

		if ( isset( $input['notify_emails'] ) ) {
			$emails = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $input['notify_emails'] ) ), 'is_email' );
			$clean['notify_emails'] = implode( ', ', $emails );
		}

		if ( isset( $input['create_cap'] ) ) {
			$cap                 = (string) $input['create_cap'];
			$clean['create_cap'] = array_key_exists( $cap, self::create_cap_options() ) ? $cap : $defaults['create_cap'];
		}

		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * Estensioni che non possono mai essere autorizzate, nemmeno a mano.
	 *
	 * @return array
	 */
	public static function forbidden_ext() {
		return array(
			'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'phtml', 'phar',
			'pht', 'shtml', 'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe',
			'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'jar', 'js', 'mjs', 'html', 'htm', 'svg', 'xhtml',
		);
	}

	/**
	 * Scrive i default alla prima attivazione senza sovrascrivere scelte esistenti.
	 */
	public static function install_defaults() {
		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * Dimensione massima allegato in byte, limitata anche dal server.
	 *
	 * @return int
	 */
	public static function max_file_bytes() {
		$plugin_limit = (int) self::get( 'max_file_mb', 10 ) * MB_IN_BYTES;
		$server_limit = (int) wp_max_upload_size();
		if ( $server_limit > 0 ) {
			return min( $plugin_limit, $server_limit );
		}
		return $plugin_limit;
	}
}
