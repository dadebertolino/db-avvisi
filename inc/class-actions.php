<?php
/**
 * Azioni POST/GET: salvataggio, cancellazione, moderazione, impostazioni, download.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Actions {

	/**
	 * Registra gli handler.
	 */
	public static function init() {
		add_action( 'admin_post_dbav_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_dbav_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_dbav_delete_file', array( __CLASS__, 'delete_file' ) );
		add_action( 'admin_post_dbav_toggle', array( __CLASS__, 'toggle' ) );
		add_action( 'admin_post_dbav_bulk', array( __CLASS__, 'bulk' ) );
		add_action( 'admin_post_dbav_settings', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_dbav_download', array( 'DBAV_Files', 'handle_download' ) );
		add_action( 'admin_post_nopriv_dbav_download', array( 'DBAV_Files', 'handle_download' ) );
		add_action( 'admin_post_dbav_export_stats', array( 'DBAV_Stats', 'export_csv' ) );
	}

	/**
	 * Salva un messaggio da mostrare dopo il redirect.
	 *
	 * @param string       $type    success|error|warning|info.
	 * @param string|array $message Messaggio o elenco di messaggi.
	 */
	public static function notice( $type, $message ) {
		$existing = get_transient( self::notice_key() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		foreach ( (array) $message as $line ) {
			$existing[] = array(
				'type' => $type,
				'text' => $line,
			);
		}
		set_transient( self::notice_key(), $existing, MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Legge e svuota i messaggi in coda.
	 *
	 * @return array
	 */
	public static function pull_notices() {
		$notices = get_transient( self::notice_key() );
		delete_transient( self::notice_key() );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Chiave transient per utente.
	 *
	 * @return string
	 */
	private static function notice_key() {
		return 'dbav_notice_' . get_current_user_id();
	}

	/**
	 * Redirect sicuro dentro l'area admin.
	 *
	 * @param array $args Query args.
	 */
	private static function redirect( array $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Crea o aggiorna un avviso, con relativi allegati.
	 */
	public static function save() {
		if ( ! dbav_can_create() ) {
			wp_die( esc_html__( 'Non hai i permessi per pubblicare avvisi.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dbav_save_avviso' );

		$id     = isset( $_POST['avviso_id'] ) ? (int) $_POST['avviso_id'] : 0;
		$avviso = $id ? DBAV_DB::get( $id ) : null;

		if ( $id && ! $avviso ) {
			wp_die( esc_html__( 'Avviso non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}
		if ( $avviso && ! dbav_can_edit( $avviso ) ) {
			wp_die( esc_html__( 'Non hai i permessi per modificare questo avviso.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$cats    = (array) DBAV_Settings::get( 'categories', array() );
		$cat_in  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$category = in_array( $cat_in, $cats, true ) ? $cat_in : '';

		$expires = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		if ( $expires && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
			$expires = '';
		}

		if ( '' === trim( $title ) ) {
			self::notice( 'error', __( 'Il titolo dell\'avviso è obbligatorio.', 'db-avvisi' ) );
			$back_args = array( 'page' => 'dbav-nuovo' );
			if ( $id ) {
				$back_args['edit'] = $id;
			}
			self::redirect( $back_args );
		}

		$data = array(
			'title'      => $title,
			'content'    => $content,
			'category'   => $category,
			'expires_at' => $expires ? $expires : null,
		);

		// Solo chi gestisce può fissare in alto o nascondere direttamente.
		if ( dbav_can_manage() ) {
			$data['pinned'] = ! empty( $_POST['pinned'] ) ? 1 : 0;
			$status_in      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'published';
			$data['status'] = in_array( $status_in, array( 'published', 'hidden' ), true ) ? $status_in : 'published';
		}

		if ( $avviso ) {
			DBAV_DB::update( $avviso->id, $data );
			$avviso_id = (int) $avviso->id;
			$created   = false;
		} else {
			$data['user_id'] = get_current_user_id();
			$data['status']  = isset( $data['status'] ) ? $data['status'] : 'published';
			$avviso_id       = DBAV_DB::insert( $data );
			$created         = true;

			if ( ! $avviso_id ) {
				self::notice( 'error', __( 'Salvataggio non riuscito: riprova.', 'db-avvisi' ) );
				self::redirect( array( 'page' => 'dbav-nuovo' ) );
			}
		}

		// Allegati.
		$errors   = array();
		$uploaded = 0;
		$incoming = isset( $_FILES['attachments'] ) ? DBAV_Files::normalize( $_FILES['attachments'] ) : array();

		if ( $incoming ) {
			$already = DBAV_DB::count_files( $avviso_id );
			$max     = (int) DBAV_Settings::get( 'max_files', 5 );

			foreach ( $incoming as $file ) {
				if ( $already + $uploaded >= $max ) {
					$errors[] = sprintf(
						/* translators: %d: numero massimo di allegati. */
						__( 'Limite di %d allegati per avviso raggiunto: i file in eccesso non sono stati caricati.', 'db-avvisi' ),
						$max
					);
					break;
				}
				$stored = DBAV_Files::store( $file, $avviso_id );
				if ( is_wp_error( $stored ) ) {
					$errors[] = $stored->get_error_message();
				} else {
					$uploaded++;
				}
			}
		}

		if ( $errors ) {
			self::notice( 'error', array_unique( $errors ) );
		}

		if ( $created ) {
			self::notify_new( $avviso_id );
			self::notice( 'success', __( 'Avviso pubblicato.', 'db-avvisi' ) );
		} else {
			self::notice( 'success', __( 'Avviso aggiornato.', 'db-avvisi' ) );
		}

		self::redirect( array( 'page' => 'dbav-avvisi', 'view' => $avviso_id ) );
	}

	/**
	 * Elimina un avviso.
	 */
	public static function delete() {
		$id     = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
		$avviso = DBAV_DB::get( $id );

		if ( ! $avviso ) {
			wp_die( esc_html__( 'Avviso non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}
		check_admin_referer( 'dbav_delete_' . $id );
		if ( ! dbav_can_edit( $avviso ) ) {
			wp_die( esc_html__( 'Non hai i permessi per eliminare questo avviso.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		DBAV_DB::delete( $id );
		self::notice( 'success', __( 'Avviso eliminato definitivamente.', 'db-avvisi' ) );

		$back = isset( $_REQUEST['back'] ) ? sanitize_key( wp_unslash( $_REQUEST['back'] ) ) : 'dbav-avvisi';
		self::redirect( array( 'page' => in_array( $back, array( 'dbav-avvisi', 'dbav-gestione' ), true ) ? $back : 'dbav-avvisi' ) );
	}

	/**
	 * Elimina un singolo allegato.
	 */
	public static function delete_file() {
		$file_id = isset( $_REQUEST['file'] ) ? (int) $_REQUEST['file'] : 0;
		$file    = DBAV_DB::get_file( $file_id );

		if ( ! $file ) {
			wp_die( esc_html__( 'Allegato non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}
		check_admin_referer( 'dbav_delete_file_' . $file_id );

		$avviso = DBAV_DB::get( $file->avviso_id );
		if ( ! dbav_can_edit( $avviso ) ) {
			wp_die( esc_html__( 'Non hai i permessi per eliminare questo allegato.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		DBAV_Files::delete( $file );
		self::notice( 'success', __( 'Allegato eliminato.', 'db-avvisi' ) );
		self::redirect( array( 'page' => 'dbav-nuovo', 'edit' => (int) $file->avviso_id ) );
	}

	/**
	 * Cambia stato o "fissato in alto" (solo gestione).
	 */
	public static function toggle() {
		if ( ! dbav_can_manage() ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
		check_admin_referer( 'dbav_toggle_' . $id );

		$avviso = DBAV_DB::get( $id );
		if ( ! $avviso ) {
			wp_die( esc_html__( 'Avviso non trovato.', 'db-avvisi' ), '', array( 'response' => 404 ) );
		}

		$what = isset( $_REQUEST['what'] ) ? sanitize_key( wp_unslash( $_REQUEST['what'] ) ) : '';

		if ( 'status' === $what ) {
			$new = 'published' === $avviso->status ? 'hidden' : 'published';
			DBAV_DB::update( $id, array( 'status' => $new ) );
			self::notice( 'success', 'hidden' === $new ? __( 'Avviso nascosto agli utenti.', 'db-avvisi' ) : __( 'Avviso ripubblicato.', 'db-avvisi' ) );
		} elseif ( 'pinned' === $what ) {
			$new = $avviso->pinned ? 0 : 1;
			DBAV_DB::update( $id, array( 'pinned' => $new ) );
			self::notice( 'success', $new ? __( 'Avviso fissato in alto.', 'db-avvisi' ) : __( 'Avviso sbloccato dall\'alto.', 'db-avvisi' ) );
		}

		self::redirect( array( 'page' => 'dbav-gestione' ) );
	}

	/**
	 * Azioni di gruppo dalla pagina di gestione.
	 */
	public static function bulk() {
		if ( ! dbav_can_manage() ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dbav_bulk' );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$ids    = array_filter( $ids );

		if ( ! $ids || ! in_array( $action, array( 'publish', 'hide', 'delete' ), true ) ) {
			self::notice( 'warning', __( 'Nessuna azione eseguita: seleziona almeno un avviso e un\'azione.', 'db-avvisi' ) );
			self::redirect( array( 'page' => 'dbav-gestione' ) );
		}

		$done = 0;
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$done += DBAV_DB::delete( $id ) ? 1 : 0;
			} else {
				$done += DBAV_DB::update( $id, array( 'status' => 'publish' === $action ? 'published' : 'hidden' ) ) ? 1 : 0;
			}
		}

		self::notice(
			'success',
			sprintf(
				/* translators: %d: numero di avvisi interessati. */
				_n( '%d avviso aggiornato.', '%d avvisi aggiornati.', $done, 'db-avvisi' ),
				$done
			)
		);
		self::redirect( array( 'page' => 'dbav-gestione' ) );
	}

	/**
	 * Salva le impostazioni.
	 */
	public static function settings() {
		if ( ! dbav_can_manage() ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dbav_settings' );

		$input = array();
		foreach ( array( 'categories', 'max_file_mb', 'max_files', 'per_page', 'allowed_ext', 'notify_emails', 'default_days' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = is_array( $_POST[ $key ] )
					? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) )
					: sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		DBAV_Settings::save( $input );
		DBAV_Files::protect_upload_dir();
		delete_transient( 'dbav_protection_check' );
		self::notice( 'success', __( 'Impostazioni salvate.', 'db-avvisi' ) );
		self::redirect( array( 'page' => 'dbav-gestione', 'tab' => 'impostazioni' ) );
	}

	/**
	 * Notifica via email i referenti configurati alla pubblicazione di un nuovo avviso.
	 *
	 * @param int $avviso_id ID avviso.
	 */
	private static function notify_new( $avviso_id ) {
		$to = trim( (string) DBAV_Settings::get( 'notify_emails', '' ) );
		if ( '' === $to ) {
			return;
		}

		$avviso = DBAV_DB::get( $avviso_id );
		if ( ! $avviso ) {
			return;
		}

		$user   = get_userdata( $avviso->user_id );
		$author = $user ? $user->display_name : __( 'Utente sconosciuto', 'db-avvisi' );
		$link   = add_query_arg(
			array(
				'page' => 'dbav-avvisi',
				'view' => $avviso_id,
			),
			admin_url( 'admin.php' )
		);

		$subject = sprintf(
			/* translators: 1: nome del sito, 2: titolo avviso. */
			__( '[%1$s] Nuovo avviso: %2$s', 'db-avvisi' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$avviso->title
		);

		$body = sprintf(
			/* translators: 1: autore, 2: titolo, 3: link. */
			__( "%1\$s ha pubblicato un nuovo avviso: \"%2\$s\".\n\nLeggilo qui: %3\$s", 'db-avvisi' ),
			$author,
			$avviso->title,
			$link
		);

		wp_mail( array_map( 'trim', explode( ',', $to ) ), $subject, $body );
	}
}
