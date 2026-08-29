<?php
/**
 * Disinstallazione: rimuove tabelle, opzioni e allegati.
 * Eseguito solo quando l'utente elimina il plugin da WordPress.
 *
 * @package DB_Avvisi
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Allegati: cancella la cartella dedicata dentro uploads.
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'db-avvisi';

if ( is_dir( $dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getRealPath() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		} else {
			@unlink( $item->getRealPath() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

// Tabelle.
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dbav_files' ); // phpcs:ignore WordPress.DB
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dbav_avvisi' ); // phpcs:ignore WordPress.DB

// Opzioni e transient.
delete_option( 'dbav_settings' );
delete_option( 'dbav_version' );
delete_transient( 'dbav_protection_check' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dbav_notice_%' OR option_name LIKE '_transient_timeout_dbav_notice_%'" ); // phpcs:ignore WordPress.DB
