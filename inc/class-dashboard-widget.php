<?php
/**
 * Widget "Avvisi" nella home della bacheca.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Dashboard_Widget {

	/**
	 * Registra il widget.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Aggiunge il widget solo per chi può vedere gli avvisi.
	 */
	public static function register() {
		if ( ! dbav_can_view() ) {
			return;
		}

		wp_add_dashboard_widget(
			'dbav_dashboard_widget',
			__( 'Avvisi recenti', 'db-avvisi' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Contenuto del widget.
	 */
	public static function render() {
		$result = DBAV_DB::query(
			array(
				'status'   => 'published',
				'per_page' => 5,
				'page'     => 1,
			)
		);

		if ( empty( $result['items'] ) ) {
			echo '<p>' . esc_html__( 'Nessun avviso pubblicato al momento.', 'db-avvisi' ) . '</p>';
		} else {
			echo '<ul class="dbav-widget-list">';
			foreach ( $result['items'] as $avviso ) {
				$url   = DBAV_Admin::url( 'dbav-avvisi', array( 'view' => (int) $avviso->id ) );
				$files = DBAV_DB::count_files( $avviso->id );

				echo '<li style="margin:0 0 10px;padding:0 0 10px;border-bottom:1px solid #f0f0f1;">';
				if ( $avviso->pinned ) {
					echo '<span class="dashicons dashicons-sticky" style="color:#dba617;" aria-hidden="true"></span> ';
				}
				echo '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $avviso->title ) . '</strong></a><br>';
				echo '<span style="color:#646970;font-size:12px;">';
				printf(
					/* translators: 1: autore, 2: data. */
					esc_html__( '%1$s · %2$s', 'db-avvisi' ),
					esc_html( DBAV_Admin::author_name( $avviso->user_id ) ),
					esc_html( mysql2date( get_option( 'date_format' ), $avviso->created_at ) )
				);
				if ( $files ) {
					echo ' · ';
					printf(
						/* translators: %d: numero di allegati. */
						esc_html( _n( '%d allegato', '%d allegati', $files, 'db-avvisi' ) ),
						(int) $files
					);
				}
				echo '</span></li>';
			}
			echo '</ul>';
		}

		echo '<p style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">';
		echo '<a class="button button-secondary" href="' . esc_url( DBAV_Admin::url( 'dbav-avvisi' ) ) . '">' . esc_html__( 'Tutti gli avvisi', 'db-avvisi' ) . '</a>';
		if ( dbav_can_create() ) {
			echo '<a class="button button-primary" href="' . esc_url( DBAV_Admin::url( 'dbav-nuovo' ) ) . '">' . esc_html__( 'Pubblica un avviso', 'db-avvisi' ) . '</a>';
		}
		echo '</p>';
	}
}
