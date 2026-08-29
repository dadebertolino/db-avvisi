/**
 * DB Avvisi — script admin.
 * Nessuna dipendenza: conferme, selezione multipla, controlli sugli allegati.
 */
( function () {
	'use strict';

	var L10n = window.dbavL10n || {};

	function sprintf( template, values ) {
		var i = 0;
		return String( template )
			.replace( /%(\d)\$s/g, function ( match, index ) {
				return values[ parseInt( index, 10 ) - 1 ];
			} )
			.replace( /%[ds]/g, function () {
				return values[ i++ ];
			} );
	}

	function formatBytes( bytes ) {
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var value = bytes;
		var unit = 0;
		while ( value >= 1024 && unit < units.length - 1 ) {
			value = value / 1024;
			unit++;
		}
		return ( Math.round( value * 10 ) / 10 ) + ' ' + units[ unit ];
	}

	document.addEventListener( 'DOMContentLoaded', function () {

		// Conferme prima delle azioni distruttive.
		document.querySelectorAll( '.dbav-confirm-delete' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( L10n.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );

		document.querySelectorAll( '.dbav-confirm-delete-file' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( L10n.confirmDeleteFile ) ) {
					event.preventDefault();
				}
			} );
		} );

		// Seleziona/deseleziona tutti nella tabella di gestione.
		var checkAll = document.getElementById( 'dbav-check-all' );
		if ( checkAll ) {
			checkAll.addEventListener( 'change', function () {
				document.querySelectorAll( '#dbav-bulk-form input[name="ids[]"]' ).forEach( function ( box ) {
					box.checked = checkAll.checked;
				} );
			} );
		}

		// Conferma azioni di gruppo distruttive.
		var bulkForm = document.getElementById( 'dbav-bulk-form' );
		if ( bulkForm ) {
			bulkForm.addEventListener( 'submit', function ( event ) {
				var action = bulkForm.querySelector( '[name="bulk_action"]' );
				if ( action && 'delete' === action.value && ! window.confirm( L10n.confirmBulk ) ) {
					event.preventDefault();
				}
			} );
		}

		// Controlli lato client sugli allegati: numero e dimensione.
		var fileInput = document.getElementById( 'dbav-files' );
		var errorBox = document.getElementById( 'dbav-file-errors' );
		if ( fileInput && errorBox ) {
			fileInput.addEventListener( 'change', function () {
				var errors = [];
				var files = Array.prototype.slice.call( fileInput.files || [] );

				if ( L10n.maxFiles && files.length > L10n.maxFiles ) {
					errors.push( sprintf( L10n.tooManyFiles, [ L10n.maxFiles ] ) );
				}

				files.forEach( function ( file ) {
					if ( L10n.maxBytes && file.size > L10n.maxBytes ) {
						errors.push( sprintf( L10n.fileTooBig, [ file.name, formatBytes( L10n.maxBytes ) ] ) );
					}
				} );

				errorBox.innerHTML = '';
				errors.forEach( function ( message ) {
					var p = document.createElement( 'p' );
					p.textContent = message;
					errorBox.appendChild( p );
				} );
			} );
		}
	} );
} )();
