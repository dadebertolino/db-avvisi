<?php
/**
 * Interfaccia in bacheca: menu, asset, rendering delle pagine.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Admin {

	/**
	 * Slug delle pagine del plugin.
	 *
	 * @var array
	 */
	private static $hooks = array();

	/**
	 * Registra hook admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Voci di menu.
	 */
	public static function menu() {
		if ( ! dbav_can_view() ) {
			return;
		}

		self::$hooks['list'] = add_menu_page(
			__( 'Avvisi', 'db-avvisi' ),
			__( 'Avvisi', 'db-avvisi' ),
			'read',
			'dbav-avvisi',
			array( __CLASS__, 'render_list' ),
			'dashicons-megaphone',
			3
		);

		add_submenu_page(
			'dbav-avvisi',
			__( 'Tutti gli avvisi', 'db-avvisi' ),
			__( 'Tutti gli avvisi', 'db-avvisi' ),
			'read',
			'dbav-avvisi',
			array( __CLASS__, 'render_list' )
		);

		self::$hooks['sindacale'] = add_submenu_page(
			'dbav-avvisi',
			__( 'Bacheca sindacale', 'db-avvisi' ),
			__( 'Bacheca sindacale', 'db-avvisi' ),
			'read',
			'dbav-sindacale',
			array( __CLASS__, 'render_list_sindacale' )
		);

		if ( dbav_creatable_boards() ) {
			self::$hooks['new'] = add_submenu_page(
				'dbav-avvisi',
				__( 'Nuovo avviso', 'db-avvisi' ),
				__( 'Nuovo avviso', 'db-avvisi' ),
				'read',
				'dbav-nuovo',
				array( __CLASS__, 'render_form' )
			);
		}

		if ( dbav_can_manage() ) {
			self::$hooks['manage'] = add_submenu_page(
				'dbav-avvisi',
				__( 'Gestione e statistiche', 'db-avvisi' ),
				__( 'Gestione e statistiche', 'db-avvisi' ),
				dbav_manage_cap(),
				'dbav-gestione',
				array( __CLASS__, 'render_manage' )
			);
		}
	}

	/**
	 * Siamo su una pagina del plugin?
	 *
	 * @param string $hook Hook suffix corrente.
	 * @return bool
	 */
	private static function is_plugin_page( $hook ) {
		return in_array( $hook, array_values( self::$hooks ), true );
	}

	/**
	 * CSS/JS solo dove servono.
	 *
	 * @param string $hook Hook suffix corrente.
	 */
	public static function assets( $hook ) {
		if ( ! self::is_plugin_page( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'db-admin-ui', DBAV_URL . 'assets/css/db-admin-ui.css', array(), DBAV_VERSION );
		wp_enqueue_style( 'dbav-admin', DBAV_URL . 'assets/css/admin.css', array( 'db-admin-ui', 'dashicons' ), DBAV_VERSION );
		wp_enqueue_script( 'dbav-admin', DBAV_URL . 'assets/js/admin.js', array(), DBAV_VERSION, true );

		wp_localize_script(
			'dbav-admin',
			'dbavL10n',
			array(
				'confirmDelete'     => __( 'Eliminare definitivamente questo avviso e i suoi allegati?', 'db-avvisi' ),
				'confirmDeleteFile' => __( 'Eliminare questo allegato?', 'db-avvisi' ),
				'confirmBulk'       => __( 'Confermi l\'azione sugli avvisi selezionati?', 'db-avvisi' ),
				'maxFiles'          => (int) DBAV_Settings::get( 'max_files', 5 ),
				'maxBytes'          => (int) DBAV_Settings::max_file_bytes(),
				'tooManyFiles'      => __( 'Puoi allegare al massimo %d file per avviso.', 'db-avvisi' ),
				'fileTooBig'        => __( 'Il file "%1$s" supera il limite di %2$s.', 'db-avvisi' ),
			)
		);
	}

	/**
	 * Stampa i messaggi in coda.
	 */
	public static function notices() {
		foreach ( DBAV_Actions::pull_notices() as $notice ) {
			$class = 'db-ui-alert-info';
			$icon  = 'ℹ️';
			if ( 'success' === $notice['type'] ) {
				$class = 'db-ui-alert-success';
				$icon  = '✅';
			} elseif ( 'error' === $notice['type'] ) {
				$class = 'db-ui-alert-danger';
				$icon  = '⛔';
			} elseif ( 'warning' === $notice['type'] ) {
				$class = 'db-ui-alert-warning';
				$icon  = '⚠️';
			}
			printf(
				'<div class="db-ui-alert %s"><span class="db-ui-alert-icon" aria-hidden="true">%s</span><span>%s</span></div>',
				esc_attr( $class ),
				esc_html( $icon ),
				wp_kses_post( $notice['text'] )
			);
		}
	}

	/**
	 * Nome leggibile di un autore.
	 *
	 * @param int $user_id ID utente.
	 * @return string
	 */
	public static function author_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Utente eliminato', 'db-avvisi' );
	}

	/**
	 * Data formattata secondo le impostazioni del sito.
	 *
	 * @param string $mysql_date Data MySQL.
	 * @return string
	 */
	public static function date( $mysql_date ) {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '—';
		}
		return mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mysql_date );
	}

	/**
	 * URL di una pagina del plugin.
	 *
	 * @param string $page Slug pagina.
	 * @param array  $args Query args aggiuntivi.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Slug della pagina elenco di una bacheca.
	 *
	 * @param string $board Bacheca.
	 * @return string
	 */
	public static function board_page( $board ) {
		return 'sindacale' === dbav_board( $board ) ? 'dbav-sindacale' : 'dbav-avvisi';
	}

	/**
	 * URL dell'elenco di una bacheca.
	 *
	 * @param string $board Bacheca.
	 * @param array  $args  Query args aggiuntivi.
	 * @return string
	 */
	public static function board_url( $board, array $args = array() ) {
		return self::url( self::board_page( $board ), $args );
	}

	/**
	 * URL della vista singola di un avviso, dentro la sua bacheca.
	 *
	 * @param object $avviso Record avviso.
	 * @return string
	 */
	public static function view_url( $avviso ) {
		return self::board_url( isset( $avviso->board ) ? $avviso->board : 'scuola', array( 'view' => (int) $avviso->id ) );
	}

	/**
	 * URL del form di pubblicazione per una bacheca.
	 *
	 * @param string $board Bacheca.
	 * @return string
	 */
	public static function new_url( $board ) {
		return self::url( 'dbav-nuovo', array( 'board' => dbav_board( $board ) ) );
	}

	/**
	 * Elenco della bacheca della scuola (e vista singola).
	 */
	public static function render_list() {
		self::render_board( 'scuola' );
	}

	/**
	 * Elenco della bacheca sindacale (e vista singola).
	 */
	public static function render_list_sindacale() {
		self::render_board( 'sindacale' );
	}

	/**
	 * Elenco avvisi di una bacheca, oppure il singolo avviso richiesto.
	 *
	 * @param string $board Bacheca.
	 */
	private static function render_board( $board ) {
		if ( ! dbav_can_view() ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere gli avvisi.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}
		$board = dbav_board( $board );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo filtri di lettura.
		$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;

		if ( $view_id ) {
			$avviso = DBAV_DB::get( $view_id );
			if ( ! $avviso || ( 'published' !== $avviso->status && ! dbav_can_edit( $avviso ) ) ) {
				$labels = dbav_boards();
				echo '<div class="wrap dbav-wrap">';
				self::notices();
				echo '<div class="db-ui-empty"><span class="db-ui-empty-icon" aria-hidden="true">🔍</span><p class="db-ui-empty-text">' . esc_html__( 'Avviso non disponibile.', 'db-avvisi' ) . '</p></div>';
				echo '<p><a class="db-ui-btn" href="' . esc_url( self::board_url( $board ) ) . '">' . esc_html( $labels[ $board ]['back'] ) . '</a></p></div>';
				return;
			}

			if ( (int) $avviso->user_id !== get_current_user_id() ) {
				DBAV_DB::bump( 'views', $avviso->id );
			}

			$files = DBAV_DB::get_files( $avviso->id );
			include DBAV_DIR . 'templates/admin/single.php';
			return;
		}

		$settings = DBAV_Settings::all();
		$args     = array(
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'category' => isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '',
			'board'    => $board,
			'user_id'  => ( isset( $_GET['mine'] ) && '1' === $_GET['mine'] ) ? get_current_user_id() : 0,
			'status'   => 'published',
			'per_page' => (int) $settings['per_page'],
			'page'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = DBAV_DB::query( $args );
		include DBAV_DIR . 'templates/admin/list.php';
	}

	/**
	 * Form nuovo avviso / modifica.
	 */
	public static function render_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lettura dei parametri di pagina.
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$avviso  = $edit_id ? DBAV_DB::get( $edit_id ) : null;

		if ( $edit_id ) {
			if ( ! $avviso || ! dbav_can_edit( $avviso ) ) {
				wp_die( esc_html__( 'Non hai i permessi per modificare questo avviso.', 'db-avvisi' ), '', array( 'response' => 403 ) );
			}
			// La bacheca di un avviso esistente non si cambia.
			$board            = dbav_board( $avviso->board );
			$boards_available = array( $board );
		} else {
			$boards_available = dbav_creatable_boards();
			if ( ! $boards_available ) {
				wp_die( esc_html__( 'Non hai i permessi per pubblicare avvisi.', 'db-avvisi' ), '', array( 'response' => 403 ) );
			}
			$requested = isset( $_GET['board'] ) ? dbav_board( sanitize_key( wp_unslash( $_GET['board'] ) ) ) : 'scuola';
			$board     = in_array( $requested, $boards_available, true ) ? $requested : $boards_available[0];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$settings = DBAV_Settings::all();
		$files    = $avviso ? DBAV_DB::get_files( $avviso->id ) : array();
		include DBAV_DIR . 'templates/admin/form.php';
	}

	/**
	 * Pagina di gestione con schede.
	 */
	public static function render_manage() {
		if ( ! dbav_can_manage() ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo navigazione fra schede.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'avvisi';
		if ( ! in_array( $tab, array( 'avvisi', 'statistiche', 'permessi', 'impostazioni' ), true ) ) {
			$tab = 'avvisi';
		}

		$settings = DBAV_Settings::all();

		if ( 'avvisi' === $tab ) {
			$args = array(
				'search'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'category'        => isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '',
				'board'           => isset( $_GET['board'] ) ? sanitize_key( wp_unslash( $_GET['board'] ) ) : '',
				'user_id'         => isset( $_GET['author'] ) ? (int) $_GET['author'] : 0,
				'status'          => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
				'include_expired' => true,
				'per_page'        => (int) $settings['per_page'],
				'page'            => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
			);
			if ( ! in_array( $args['status'], array( '', 'published', 'hidden' ), true ) ) {
				$args['status'] = '';
			}
			if ( ! in_array( $args['board'], array( '', 'scuola', 'sindacale' ), true ) ) {
				$args['board'] = '';
			}
			$result = DBAV_DB::query( $args );
		}

		if ( 'permessi' === $tab ) {
			$perm_args = array(
				'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'role'   => isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '',
				'show'   => ( isset( $_GET['show'] ) && in_array( $_GET['show'], array( 'exceptions', 'sindacale' ), true ) ) ? sanitize_key( wp_unslash( $_GET['show'] ) ) : '',
			);
			$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
			$result = self::permissions_users( $perm_args, $page );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		include DBAV_DIR . 'templates/admin/manage.php';
	}

	/**
	 * Utenti del sito per la scheda permessi.
	 *
	 * @param array $perm_args Filtri { search, role, show }; il ruolo non valido viene azzerato.
	 * @param int   $page      Pagina corrente.
	 * @return array { items: WP_User[], total: int, pages: int, page: int }
	 */
	private static function permissions_users( array &$perm_args, $page ) {
		global $wpdb;
		$per_page = 20;

		if ( '' !== $perm_args['role'] && ! array_key_exists( $perm_args['role'], wp_roles()->get_names() ) ) {
			$perm_args['role'] = '';
		}

		$query_args = array(
			'number'      => $per_page,
			'paged'       => $page,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'count_total' => true,
		);
		if ( '' !== $perm_args['search'] ) {
			$query_args['search']         = '*' . $perm_args['search'] . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		if ( '' !== $perm_args['role'] ) {
			$query_args['role'] = $perm_args['role'];
		}
		if ( 'exceptions' === $perm_args['show'] ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => $wpdb->get_blog_prefix() . 'dbav_publish',
					'value'   => array( 'allow', 'deny' ),
					'compare' => 'IN',
				),
			);
		} elseif ( 'sindacale' === $perm_args['show'] ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => $wpdb->get_blog_prefix() . 'dbav_publish_sindacale',
					'value' => 'allow',
				),
			);
		}

		$query = new WP_User_Query( $query_args );
		$total = (int) $query->get_total();

		return array(
			'items' => $query->get_results(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Paginazione condivisa.
	 *
	 * @param array  $result Risultato di DBAV_DB::query().
	 * @param string $page   Slug pagina corrente.
	 * @param array  $args   Query args da conservare.
	 */
	public static function pagination( array $result, $page, array $args = array() ) {
		if ( $result['pages'] < 2 ) {
			return;
		}

		// add_query_arg non codifica i valori aggiunti: lo facciamo qui, lasciando
		// intatto il segnaposto %#% che paginate_links deve poter sostituire.
		$safe_args = array();
		foreach ( $args as $key => $value ) {
			$safe_args[ $key ] = rawurlencode( (string) $value );
		}
		$safe_args['paged'] = '%#%';

		$links = paginate_links(
			array(
				'base'      => self::url( $page, $safe_args ),
				'format'    => '',
				'current'   => $result['page'],
				'total'     => $result['pages'],
				'prev_text' => '‹ ' . __( 'Precedente', 'db-avvisi' ),
				'next_text' => __( 'Successiva', 'db-avvisi' ) . ' ›',
				'type'      => 'array',
			)
		);

		if ( ! $links ) {
			return;
		}

		echo '<nav class="dbav-pagination" aria-label="' . esc_attr__( 'Navigazione avvisi', 'db-avvisi' ) . '">';
		foreach ( $links as $link ) {
			echo wp_kses_post( $link );
		}
		echo '</nav>';
	}
}
