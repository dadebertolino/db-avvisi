<?php
/**
 * Statistiche: chi ha caricato cosa, quanto pesa, quanto viene scaricato.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Stats {

	/**
	 * Numeri generali.
	 *
	 * @return array
	 */
	public static function totals() {
		global $wpdb;
		$avvisi = DBAV_DB::table();
		$files  = DBAV_DB::files_table();

		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
				SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
				SUM(views) AS views,
				COUNT(DISTINCT user_id) AS authors
			FROM $avvisi"
		);

		$expired = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $avvisi WHERE expires_at IS NOT NULL AND expires_at < %s", current_time( 'Y-m-d' ) )
		);

		$frow = $wpdb->get_row( "SELECT COUNT(*) AS files, COALESCE(SUM(size),0) AS bytes, COALESCE(SUM(downloads),0) AS downloads FROM $files" );

		return array(
			'total'     => $row ? (int) $row->total : 0,
			'published' => $row ? (int) $row->published : 0,
			'hidden'    => $row ? (int) $row->hidden : 0,
			'expired'   => $expired,
			'views'     => $row ? (int) $row->views : 0,
			'authors'   => $row ? (int) $row->authors : 0,
			'files'     => $frow ? (int) $frow->files : 0,
			'bytes'     => $frow ? (int) $frow->bytes : 0,
			'downloads' => $frow ? (int) $frow->downloads : 0,
		);
	}

	/**
	 * Chi ha caricato cosa: una riga per autore.
	 *
	 * @param int $limit Numero massimo di autori.
	 * @return array
	 */
	public static function by_author( $limit = 100 ) {
		global $wpdb;
		$avvisi = DBAV_DB::table();
		$files  = DBAV_DB::files_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.user_id,
					COUNT(DISTINCT a.id) AS avvisi,
					SUM(CASE WHEN a.status = 'published' THEN 1 ELSE 0 END) AS published,
					SUM(CASE WHEN a.status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
					COALESCE(SUM(a.views),0) AS views,
					MAX(a.created_at) AS last_at,
					COALESCE(f.files,0) AS files,
					COALESCE(f.bytes,0) AS bytes,
					COALESCE(f.downloads,0) AS downloads
				FROM $avvisi a
				LEFT JOIN (
					SELECT x.user_id,
						COUNT(fl.id) AS files,
						COALESCE(SUM(fl.size),0) AS bytes,
						COALESCE(SUM(fl.downloads),0) AS downloads
					FROM $files fl
					INNER JOIN $avvisi x ON x.id = fl.avviso_id
					GROUP BY x.user_id
				) f ON f.user_id = a.user_id
				GROUP BY a.user_id
				ORDER BY avvisi DESC, last_at DESC
				LIMIT %d",
				(int) $limit
			)
		);

		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$user            = get_userdata( $row->user_id );
			$row->user_name  = $user ? $user->display_name : __( 'Utente eliminato', 'db-avvisi' );
			$row->user_login = $user ? $user->user_login : '';
			$row->user_roles = $user ? implode( ', ', $user->roles ) : '';
		}

		return $rows;
	}

	/**
	 * Distribuzione per categoria.
	 *
	 * @return array
	 */
	public static function by_category() {
		global $wpdb;
		$avvisi = DBAV_DB::table();
		$rows   = $wpdb->get_results(
			"SELECT category, COUNT(*) AS total, COALESCE(SUM(views),0) AS views
			FROM $avvisi GROUP BY category ORDER BY total DESC"
		);
		return $rows ? $rows : array();
	}

	/**
	 * Avvisi per mese, ultimi N mesi.
	 *
	 * @param int $months Numero di mesi.
	 * @return array Array mese => conteggio, in ordine cronologico.
	 */
	public static function by_month( $months = 12 ) {
		global $wpdb;
		$avvisi = DBAV_DB::table();
		$months = max( 1, min( 36, (int) $months ) );

		$since = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', (int) current_time( 'timestamp' ) ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS ym, COUNT(*) AS total
				FROM $avvisi WHERE created_at >= %s GROUP BY ym ORDER BY ym ASC",
				$since
			)
		);

		$series  = array();
		$cursor  = strtotime( $since );
		for ( $i = 0; $i < $months; $i++ ) {
			$series[ gmdate( 'Y-m', $cursor ) ] = 0;
			$cursor = strtotime( '+1 month', $cursor );
		}
		foreach ( (array) $rows as $row ) {
			if ( isset( $series[ $row->ym ] ) ) {
				$series[ $row->ym ] = (int) $row->total;
			}
		}
		return $series;
	}

	/**
	 * Allegati più scaricati.
	 *
	 * @param int $limit Numero massimo di righe.
	 * @return array
	 */
	public static function top_downloads( $limit = 10 ) {
		global $wpdb;
		$files  = DBAV_DB::files_table();
		$avvisi = DBAV_DB::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.id, f.original_name, f.downloads, f.size, a.id AS avviso_id, a.title, a.user_id
				FROM $files f
				INNER JOIN $avvisi a ON a.id = f.avviso_id
				WHERE f.downloads > 0
				ORDER BY f.downloads DESC
				LIMIT %d",
				(int) $limit
			)
		);
		return $rows ? $rows : array();
	}

	/**
	 * Avvisi più letti.
	 *
	 * @param int $limit Numero massimo di righe.
	 * @return array
	 */
	public static function top_views( $limit = 10 ) {
		global $wpdb;
		$avvisi = DBAV_DB::table();
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, title, user_id, views, created_at FROM $avvisi WHERE views > 0 ORDER BY views DESC LIMIT %d", (int) $limit )
		);
		return $rows ? $rows : array();
	}

	/**
	 * Esporta le statistiche per autore in CSV.
	 */
	public static function export_csv() {
		if ( ! dbav_can_manage() ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'db-avvisi' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dbav_export_stats' );

		$rows     = self::by_author( 1000 );
		$filename = 'avvisi-statistiche-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM per Excel.
		fputcsv( $out, array( 'Utente', 'Login', 'Ruoli', 'Avvisi', 'Pubblicati', 'Nascosti', 'Allegati', 'Spazio (byte)', 'Download', 'Visualizzazioni', 'Ultimo avviso' ) );

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row->user_name,
					$row->user_login,
					$row->user_roles,
					(int) $row->avvisi,
					(int) $row->published,
					(int) $row->hidden,
					(int) $row->files,
					(int) $row->bytes,
					(int) $row->downloads,
					(int) $row->views,
					$row->last_at,
				)
			);
		}

		fclose( $out );
		exit;
	}
}
