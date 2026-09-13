<?php
/**
 * Livello dati: tabelle avvisi e allegati.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_DB {

	/**
	 * Nome tabella avvisi.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'dbav_avvisi';
	}

	/**
	 * Nome tabella allegati.
	 *
	 * @return string
	 */
	public static function files_table() {
		global $wpdb;
		return $wpdb->prefix . 'dbav_files';
	}

	/**
	 * Crea/aggiorna lo schema (idempotente).
	 */
	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$avvisi  = self::table();
		$files   = self::files_table();

		$sql_avvisi = "CREATE TABLE $avvisi (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			content longtext NOT NULL,
			category varchar(100) NOT NULL DEFAULT '',
			board varchar(20) NOT NULL DEFAULT 'scuola',
			user_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'published',
			pinned tinyint(1) NOT NULL DEFAULT 0,
			expires_at date DEFAULT NULL,
			views bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY category (category),
			KEY board (board),
			KEY created_at (created_at)
		) $charset;";

		$sql_files = "CREATE TABLE $files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			avviso_id bigint(20) unsigned NOT NULL,
			path varchar(255) NOT NULL,
			original_name varchar(255) NOT NULL,
			mime varchar(120) NOT NULL DEFAULT '',
			size bigint(20) unsigned NOT NULL DEFAULT 0,
			downloads bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY avviso_id (avviso_id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_avvisi );
		dbDelta( $sql_files );
	}

	/**
	 * Legge un avviso.
	 *
	 * @param int $id ID avviso.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella custom del plugin.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Inserisce un avviso.
	 *
	 * @param array $data Dati già sanificati.
	 * @return int ID inserito (0 in caso di errore).
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		$row = array(
			'title'      => $data['title'],
			'content'    => $data['content'],
			'category'   => isset( $data['category'] ) ? $data['category'] : '',
			'board'      => isset( $data['board'] ) ? dbav_board( $data['board'] ) : 'scuola',
			'user_id'    => isset( $data['user_id'] ) ? (int) $data['user_id'] : get_current_user_id(),
			'status'     => isset( $data['status'] ) ? $data['status'] : 'published',
			'pinned'     => ! empty( $data['pinned'] ) ? 1 : 0,
			'expires_at' => ! empty( $data['expires_at'] ) ? $data['expires_at'] : null,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$ok = $wpdb->insert( self::table(), $row, array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' ) );
		if ( ! $ok ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;
		do_action( 'dbav_avviso_created', $id, $row );
		return $id;
	}

	/**
	 * Aggiorna un avviso.
	 *
	 * @param int   $id   ID avviso.
	 * @param array $data Campi da aggiornare.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		$row     = array();
		$formats = array();
		$map     = array(
			'title'      => '%s',
			'content'    => '%s',
			'category'   => '%s',
			'status'     => '%s',
			'pinned'     => '%d',
			'expires_at' => '%s',
			'user_id'    => '%d',
		);

		foreach ( $map as $key => $format ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ]  = $data[ $key ];
				$formats[]    = $format;
			}
		}

		if ( ! $row ) {
			return false;
		}

		$row['updated_at'] = current_time( 'mysql' );
		$formats[]         = '%s';

		$ok = false !== $wpdb->update( self::table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
		if ( $ok ) {
			do_action( 'dbav_avviso_updated', $id, $row );
		}
		return $ok;
	}

	/**
	 * Elimina un avviso e i suoi allegati (file inclusi).
	 *
	 * @param int $id ID avviso.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		foreach ( self::get_files( $id ) as $file ) {
			DBAV_Files::delete( $file );
		}

		$deleted = (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		if ( $deleted ) {
			do_action( 'dbav_avviso_deleted', $id );
		}
		return $deleted;
	}

	/**
	 * Query elenco avvisi.
	 *
	 * Argomenti: search, category, board ('' = tutte), user_id, status ('' = tutti),
	 * include_expired, orderby, order, per_page, page.
	 *
	 * @param array $args Filtri.
	 * @return array { items: object[], total: int, pages: int, page: int }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'          => '',
				'category'        => '',
				'board'           => '',
				'user_id'         => 0,
				'status'          => 'published',
				'include_expired' => false,
				'orderby'         => 'created_at',
				'order'           => 'DESC',
				'per_page'        => 20,
				'page'            => 1,
				'pinned_first'    => true,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( '' !== $args['category'] ) {
			$where[]  = 'category = %s';
			$params[] = $args['category'];
		}

		if ( '' !== $args['board'] ) {
			$where[]  = 'board = %s';
			$params[] = dbav_board( $args['board'] );
		}

		if ( $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! $args['include_expired'] ) {
			$where[]  = '(expires_at IS NULL OR expires_at >= %s)';
			$params[] = current_time( 'Y-m-d' );
		}

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR content LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array( 'created_at', 'updated_at', 'title', 'views', 'category' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$order_sql       = ( $args['pinned_first'] ? 'pinned DESC, ' : '' ) . $orderby . ' ' . $order;

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $where_sql;
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$sql         = 'SELECT * FROM ' . self::table() . ' WHERE ' . $where_sql . ' ORDER BY ' . $order_sql . ' LIMIT %d OFFSET %d';
		$sql_params  = array_merge( $params, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $sql, $sql_params ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Allegati di un avviso.
	 *
	 * @param int $avviso_id ID avviso.
	 * @return array
	 */
	public static function get_files( $avviso_id ) {
		global $wpdb;
		$avviso_id = (int) $avviso_id;
		if ( $avviso_id <= 0 ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::files_table() . ' WHERE avviso_id = %d ORDER BY id ASC', $avviso_id ) );
		return $rows ? $rows : array();
	}

	/**
	 * Conta gli allegati di un avviso.
	 *
	 * @param int $avviso_id ID avviso.
	 * @return int
	 */
	public static function count_files( $avviso_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::files_table() . ' WHERE avviso_id = %d', (int) $avviso_id ) );
	}

	/**
	 * Legge un allegato.
	 *
	 * @param int $id ID allegato.
	 * @return object|null
	 */
	public static function get_file( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::files_table() . ' WHERE id = %d', (int) $id ) );
	}

	/**
	 * Registra un allegato.
	 *
	 * @param array $data Dati file.
	 * @return int
	 */
	public static function insert_file( array $data ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::files_table(),
			array(
				'avviso_id'     => (int) $data['avviso_id'],
				'path'          => $data['path'],
				'original_name' => $data['original_name'],
				'mime'          => isset( $data['mime'] ) ? $data['mime'] : '',
				'size'          => isset( $data['size'] ) ? (int) $data['size'] : 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Cancella il record di un allegato.
	 *
	 * @param int $id ID allegato.
	 * @return bool
	 */
	public static function delete_file_row( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::files_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Incrementa un contatore (views su avviso, downloads su allegato).
	 *
	 * @param string $what 'views' oppure 'downloads'.
	 * @param int    $id   ID record.
	 */
	public static function bump( $what, $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		if ( 'views' === $what ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET views = views + 1 WHERE id = %d', $id ) );
		} elseif ( 'downloads' === $what ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::files_table() . ' SET downloads = downloads + 1 WHERE id = %d', $id ) );
		}
	}
}
