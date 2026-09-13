<?php
/**
 * Widget in bacheca: avvisi recenti della scuola e della bacheca sindacale.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBAV_Dashboard_Widget {

	/**
	 * Registra i widget.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Aggiunge i widget solo per chi può vedere gli avvisi.
	 * Quello sindacale compare se c'è qualcosa da leggere, oppure a chi è abilitato a pubblicarvi.
	 */
	public static function register() {
		if ( ! dbav_can_view() ) {
			return;
		}

		$labels = dbav_boards();

		wp_add_dashboard_widget(
			'dbav_dashboard_widget',
			$labels['scuola']['widget'],
			array( __CLASS__, 'render' ),
			null,
			array( 'board' => 'scuola' )
		);

		$sindacale = DBAV_DB::query(
			array(
				'board'    => 'sindacale',
				'status'   => 'published',
				'per_page' => 1,
			)
		);
		if ( $sindacale['total'] > 0 || ( ! dbav_can_manage() && dbav_can_create( 'sindacale' ) ) ) {
			wp_add_dashboard_widget(
				'dbav_dashboard_widget_sindacale',
				$labels['sindacale']['widget'],
				array( __CLASS__, 'render' ),
				null,
				array( 'board' => 'sindacale' )
			);
		}
	}

	/**
	 * Contenuto del widget.
	 *
	 * @param mixed $object Non usato (passato da WordPress).
	 * @param array $box    Dati del widget; 'args' contiene la bacheca.
	 */
	public static function render( $object = null, $box = array() ) {
		$board  = dbav_board( isset( $box['args']['board'] ) ? $box['args']['board'] : 'scuola' );
		$labels = dbav_boards();
		$labels = $labels[ $board ];

		$result = DBAV_DB::query(
			array(
				'board'    => $board,
				'status'   => 'published',
				'per_page' => 5,
				'page'     => 1,
			)
		);

		if ( empty( $result['items'] ) ) {
			echo '<p>' . esc_html( $labels['empty'] ) . '</p>';
		} else {
			echo '<ul class="dbav-widget-list">';
			foreach ( $result['items'] as $avviso ) {
				$url   = DBAV_Admin::view_url( $avviso );
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
		echo '<a class="button button-secondary" href="' . esc_url( DBAV_Admin::board_url( $board ) ) . '">' . esc_html( $labels['all'] ) . '</a>';
		if ( dbav_can_create( $board ) ) {
			echo '<a class="button button-primary" href="' . esc_url( DBAV_Admin::new_url( $board ) ) . '">' . esc_html( $labels['new'] ) . '</a>';
		}
		echo '</p>';
	}
}
