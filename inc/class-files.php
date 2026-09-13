<?php
/**
 * Gestione allegati: cartella protetta, validazione, salvataggio, download controllato.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Files {

	const SUBDIR = 'db-avvisi';

	/**
	 * Percorso base della cartella allegati.
	 *
	 * @return string
	 */
	public static function basedir() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
	}

	/**
	 * Crea la cartella allegati e la mette al riparo dall'accesso diretto.
	 */
	public static function protect_upload_dir() {
		$dir = self::basedir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$webconfig = $dir . '/web.config';
		if ( ! file_exists( $webconfig ) ) {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n";
			file_put_contents( $webconfig, $xml ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * La cartella è davvero irraggiungibile dal web?
	 * Usato solo per mostrare un avviso in area gestione.
	 *
	 * @return bool|null true protetta, false esposta, null non verificabile.
	 */
	public static function check_protection() {
		$cached = get_transient( 'dbav_protection_check' );
		if ( false !== $cached ) {
			return 'unknown' === $cached ? null : ( 'safe' === $cached );
		}

		$result = self::run_protection_probe();
		set_transient( 'dbav_protection_check', null === $result ? 'unknown' : ( $result ? 'safe' : 'exposed' ), 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Esegue davvero il test: scrive un file di prova e prova a scaricarlo dall'esterno.
	 *
	 * @return bool|null
	 */
	private static function run_protection_probe() {
		$dir = self::basedir();
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		$probe_name = 'dbav-probe.txt';
		$probe_path = $dir . '/' . $probe_name;
		if ( ! file_exists( $probe_path ) ) {
			file_put_contents( $probe_path, 'db-avvisi probe' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$uploads   = wp_upload_dir();
		$probe_url = trailingslashit( $uploads['baseurl'] ) . self::SUBDIR . '/' . $probe_name;
		$response  = wp_remote_get( $probe_url, array( 'timeout' => 5, 'sslverify' => false ) );

		if ( is_wp_error( $response ) ) {
			if ( file_exists( $probe_path ) ) {
				@unlink( $probe_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			return null;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$exposed = ( 200 === $code && false !== strpos( wp_remote_retrieve_body( $response ), 'db-avvisi probe' ) );

		if ( file_exists( $probe_path ) ) {
			@unlink( $probe_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return ! $exposed;
	}

	/**
	 * Normalizza l'array $_FILES di un input multiplo in una lista di file singoli.
	 *
	 * @param array $files Voce di $_FILES.
	 * @return array
	 */
	public static function normalize( $files ) {
		$out = array();
		if ( empty( $files ) || ! isset( $files['name'] ) ) {
			return $out;
		}

		if ( ! is_array( $files['name'] ) ) {
			return array( $files );
		}

		$count = count( $files['name'] );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $files['name'][ $i ] ) ) {
				continue;
			}
			$out[] = array(
				'name'     => $files['name'][ $i ],
				'type'     => isset( $files['type'][ $i ] ) ? $files['type'][ $i ] : '',
				'tmp_name' => isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '',
				'error'    => isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $files['size'][ $i ] ) ? $files['size'][ $i ] : 0,
			);
		}
		return $out;
	}

	/**
	 * Valida e salva un singolo file, registrandolo in tabella.
	 *
	 * @param array $file      Voce normalizzata di $_FILES.
	 * @param int   $avviso_id Avviso di destinazione.
	 * @return int|WP_Error ID allegato oppure errore.
	 */
	public static function store( array $file, $avviso_id ) {
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'dbav_upload_error', self::upload_error_message( isset( $file['error'] ) ? (int) $file['error'] : -1, $file['name'] ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'dbav_not_uploaded', sprintf( __( 'File "%s" non valido.', 'db-avvisi' ), $file['name'] ) );
		}

		$max = DBAV_Settings::max_file_bytes();
		if ( (int) $file['size'] > $max ) {
			return new WP_Error(
				'dbav_too_big',
				sprintf(
					/* translators: 1: nome file, 2: dimensione massima. */
					__( 'File "%1$s" troppo grande: il limite è %2$s.', 'db-avvisi' ),
					$file['name'],
					size_format( $max )
				)
			);
		}

		$original = sanitize_file_name( $file['name'] );
		$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		$allowed  = (array) DBAV_Settings::get( 'allowed_ext', array() );

		if ( '' === $ext || in_array( $ext, DBAV_Settings::forbidden_ext(), true ) || ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error(
				'dbav_bad_ext',
				sprintf(
					/* translators: 1: nome file, 2: elenco estensioni ammesse. */
					__( 'File "%1$s": estensione non ammessa. Sono ammessi: %2$s.', 'db-avvisi' ),
					$original,
					implode( ', ', $allowed )
				)
			);
		}

		// Doppio controllo sul tipo reale dichiarato da WordPress.
		$checked = wp_check_filetype( $original, self::mime_map() );
		if ( empty( $checked['type'] ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'dbav_bad_mime', sprintf( __( 'File "%s": tipo non riconosciuto.', 'db-avvisi' ), $original ) );
		}

		$dir = trailingslashit( self::basedir() ) . gmdate( 'Y/m' );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::protect_upload_dir();

		$stored_name = wp_generate_password( 24, false, false ) . '.' . $ext;
		$target      = trailingslashit( $dir ) . $stored_name;

		if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			/* translators: %s: nome del file. */
			return new WP_Error( 'dbav_move_failed', sprintf( __( 'Impossibile salvare "%s" sul server.', 'db-avvisi' ), $original ) );
		}

		@chmod( $target, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$relative = self::SUBDIR . '/' . gmdate( 'Y/m' ) . '/' . $stored_name;
		$file_id  = DBAV_DB::insert_file(
			array(
				'avviso_id'     => (int) $avviso_id,
				'path'          => $relative,
				'original_name' => $original,
				'mime'          => $checked['type'],
				'size'          => (int) $file['size'],
			)
		);

		if ( ! $file_id ) {
			@unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			/* translators: %s: nome del file. */
			return new WP_Error( 'dbav_db_failed', sprintf( __( 'Impossibile registrare "%s" in archivio.', 'db-avvisi' ), $original ) );
		}

		return $file_id;
	}

	/**
	 * Percorso assoluto di un allegato.
	 *
	 * @param object $file Record allegato.
	 * @return string
	 */
	public static function path( $file ) {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . ltrim( $file->path, '/' );
	}

	/**
	 * Elimina file e record.
	 *
	 * @param object $file Record allegato.
	 * @return bool
	 */
	public static function delete( $file ) {
		if ( ! $file ) {
			return false;
		}
		$path = self::path( $file );
		$base = wp_normalize_path( trailingslashit( self::basedir() ) );

		if ( file_exists( $path ) && 0 === strpos( wp_normalize_path( $path ), $base ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		return DBAV_DB::delete_file_row( $file->id );
	}

	/**
	 * URL di download protetto.
	 *
	 * @param object $file Record allegato.
	 * @return string
	 */
	public static function download_url( $file ) {
		return add_query_arg(
			array(
				'action' => 'dbav_download',
				'file'   => (int) $file->id,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Serve un allegato solo a utenti loggati.
	 */
	public static function handle_download() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		// auth_redirect() non ferma un utente già loggato: il permesso va verificato a parte.
		if ( ! dbav_can_view() ) {
			wp_die( esc_html__( 'Non hai i permessi per scaricare questo allegato.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		$file_id = isset( $_GET['file'] ) ? (int) $_GET['file'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$file    = DBAV_DB::get_file( $file_id );
		if ( ! $file ) {
			wp_die( esc_html__( 'Allegato non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}

		$avviso = DBAV_DB::get( $file->avviso_id );
		if ( ! $avviso ) {
			wp_die( esc_html__( 'Avviso non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}

		if ( 'published' !== $avviso->status && ! dbav_can_edit( $avviso ) ) {
			wp_die( esc_html__( 'Non hai i permessi per scaricare questo allegato.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		$path = self::path( $file );
		$base = wp_normalize_path( trailingslashit( self::basedir() ) );
		if ( ! file_exists( $path ) || 0 !== strpos( wp_normalize_path( $path ), $base ) ) {
			wp_die( esc_html__( 'File non più disponibile sul server.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}

		DBAV_DB::bump( 'downloads', $file->id );

		nocache_headers();
		header( 'Content-Type: ' . ( $file->mime ? $file->mime : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file->original_name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $file->original_name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( function_exists( 'ob_get_level' ) ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
		}

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Mappa estensione => mime per le estensioni ammesse.
	 *
	 * @return array
	 */
	public static function mime_map() {
		$all     = wp_get_mime_types();
		$allowed = (array) DBAV_Settings::get( 'allowed_ext', array() );
		$map     = array();

		foreach ( $all as $exts => $mime ) {
			foreach ( explode( '|', $exts ) as $ext ) {
				if ( in_array( $ext, $allowed, true ) && ! in_array( $ext, DBAV_Settings::forbidden_ext(), true ) ) {
					$map[ $exts ] = $mime;
					break;
				}
			}
		}
		return $map;
	}

	/**
	 * Messaggio leggibile per gli errori di upload PHP.
	 *
	 * @param int    $code Codice UPLOAD_ERR_*.
	 * @param string $name Nome file.
	 * @return string
	 */
	public static function upload_error_message( $code, $name ) {
		$name = sanitize_file_name( $name );
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				/* translators: 1: nome file, 2: limite del server. */
				return sprintf( __( 'File "%1$s" troppo grande per il server (limite %2$s).', 'db-avvisi' ), $name, size_format( wp_max_upload_size() ) );
			case UPLOAD_ERR_PARTIAL:
				/* translators: %s: nome del file. */
				return sprintf( __( 'Caricamento di "%s" interrotto: riprova.', 'db-avvisi' ), $name );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Il server non riesce a scrivere il file temporaneo: contatta l\'amministratore.', 'db-avvisi' );
			case UPLOAD_ERR_EXTENSION:
				/* translators: %s: nome del file. */
				return sprintf( __( 'Caricamento di "%s" bloccato da un\'estensione PHP.', 'db-avvisi' ), $name );
			default:
				/* translators: %s: nome del file. */
				return sprintf( __( 'Caricamento di "%s" non riuscito.', 'db-avvisi' ), $name );
		}
	}

	/**
	 * Dashicon adatto al tipo di file.
	 *
	 * @param object $file Record allegato.
	 * @return string
	 */
	public static function icon( $file ) {
		$ext = strtolower( pathinfo( $file->original_name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			return 'dashicons-format-image';
		}
		if ( 'pdf' === $ext ) {
			return 'dashicons-media-document';
		}
		if ( in_array( $ext, array( 'xls', 'xlsx', 'ods', 'csv' ), true ) ) {
			return 'dashicons-media-spreadsheet';
		}
		if ( in_array( $ext, array( 'ppt', 'pptx', 'odp' ), true ) ) {
			return 'dashicons-media-interactive';
		}
		if ( 'zip' === $ext ) {
			return 'dashicons-media-archive';
		}
		return 'dashicons-media-default';
	}
}
