/* global SignaturePad, fsnwSignatureKiosk */
( function () {
	'use strict';

	var POLL_INTERVAL_MS = 2000;
	// Diese Seite macht nie einen Reload, wenn sich Daten ändern - sie lebt
	// dauerhaft im Kiosk-Tab. Ohne diesen Timer würde ein bereits offener
	// Tablet-Tab nach einem Plugin-Deploy für immer die alte JS-Version im
	// Speicher weiterlaufen lassen. Reload erfolgt nur im Wartezustand, nie
	// während einer laufenden Unterschrift.
	var RELOAD_INTERVAL_MS = 30 * 60 * 1000;
	var SUCCESS_DISPLAY_MS = 7000;
	var REQUEST_TIMEOUT_MS = 8000;
	// Öffentlicher, garantiert erreichbarer Endpunkt (Googles eigener
	// Connectivity-Check, den auch Android selbst nutzt) - dient nur zur
	// Unterscheidung "kein Internet" vs. "nur unser Server nicht erreichbar",
	// hat sonst keine Funktion.
	var CONNECTIVITY_CHECK_URL = 'https://www.gstatic.com/generate_204';
	var CONNECTIVITY_CHECK_TIMEOUT_MS = 4000;

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'fsnw-signature-app' );

		if ( ! root ) {
			return;
		}

		var waitingEl = document.getElementById( 'fsnw-signature-waiting' );
		var requestEl = document.getElementById( 'fsnw-signature-request' );
		var successEl = document.getElementById( 'fsnw-signature-success' );
		var titleEl = document.getElementById( 'fsnw-signature-title' );
		var recipientEl = document.getElementById( 'fsnw-signature-recipient' );
		var itemsEl = document.getElementById( 'fsnw-signature-items' );
		var periodRowEl = document.getElementById( 'fsnw-signature-period-row' );
		var periodEl = document.getElementById( 'fsnw-signature-period' );
		var metaLinesEl = document.getElementById( 'fsnw-signature-meta-lines' );
		var errorEl = document.getElementById( 'fsnw-signature-error' );
		var connectionEl = document.getElementById( 'fsnw-signature-connection' );
		var clockEl = document.getElementById( 'fsnw-signature-clock' );
		var canvas = document.getElementById( 'fsnw-signature-canvas' );
		var clearButton = document.getElementById( 'fsnw-signature-clear' );
		var confirmButton = document.getElementById( 'fsnw-signature-confirm' );

		var signaturePad = new SignaturePad( canvas );
		var statusEl = document.getElementById( 'fsnw-signature-livestatus' );
		var pollCount = 0;
		var currentRequestId = null;
		// true from "Bestätigen" bis zum Ende der Erfolgsanzeige - blendet
		// zwischenzeitliche Polling-Ticks aus, damit sie die Erfolgsmeldung
		// nicht vorzeitig überschreiben und den Reset in einen sauberen
		// Wartezustand nicht stören.
		var isBusy = false;
		var consecutiveFailures = 0;
		var isCheckingConnectivity = false;
		var defaultConfirmLabel = confirmButton.textContent;

		function checkInternetConnectivity() {
			return new Promise( function ( resolve ) {
				var controller = new AbortController();
				var timeout = setTimeout( function () {
					controller.abort();
					resolve( false );
				}, CONNECTIVITY_CHECK_TIMEOUT_MS );

				fetch( CONNECTIVITY_CHECK_URL + '?_=' + Date.now(), {
					mode: 'no-cors',
					cache: 'no-store',
					signal: controller.signal,
				} )
					.then( function () {
						clearTimeout( timeout );
						resolve( true );
					} )
					.catch( function () {
						clearTimeout( timeout );
						resolve( false );
					} );
			} );
		}

		function resizeCanvas() {
			var ratio = Math.max( window.devicePixelRatio || 1, 1 );

			canvas.width = canvas.offsetWidth * ratio;
			canvas.height = canvas.offsetHeight * ratio;
			canvas.getContext( '2d' ).scale( ratio, ratio );
			signaturePad.clear();
		}

		window.addEventListener( 'resize', resizeCanvas );
		resizeCanvas();

		function showState( state ) {
			waitingEl.classList.toggle( 'fsnw-hidden', 'waiting' !== state );
			requestEl.classList.toggle( 'fsnw-hidden', 'request' !== state );
			successEl.classList.toggle( 'fsnw-hidden', 'success' !== state );
		}

		function renderList( container, tagName, values ) {
			container.textContent = '';

			values.forEach( function ( value ) {
				var element = document.createElement( tagName );
				element.textContent = value;

				if ( 'p' === tagName ) {
					element.className = 'fsnw-signature-meta';
				}

				container.appendChild( element );
			} );
		}

		function renderPending( pending ) {
			if ( isBusy ) {
				return;
			}

			if ( ! pending ) {
				currentRequestId = null;
				showState( 'waiting' );
				return;
			}

			if ( pending.id !== currentRequestId ) {
				currentRequestId = pending.id;
				errorEl.textContent = '';
				confirmButton.textContent = defaultConfirmLabel;
				// Solange der Bereich per display:none versteckt war, hatte das
				// Canvas die Größe 0x0. Erst jetzt, wo er für diese neue Anfrage
				// sichtbar wird, kann die echte Breite/Höhe ermittelt werden.
				// Absichtlich nur hier (nicht bei jedem Poll-Tick), sonst würde
				// eine bereits begonnene Unterschrift bei jedem Tick gelöscht.
				showState( 'request' );
				resizeCanvas();
			}

			titleEl.textContent = pending.title;
			recipientEl.textContent = pending.recipient_name;
			renderList( itemsEl, 'li', pending.items || [] );
			renderList( metaLinesEl, 'p', pending.meta_lines || [] );

			if ( pending.period ) {
				periodEl.textContent = pending.period.start_date + ' ' + pending.period.start_time +
					' – ' + pending.period.end_date + ' ' + pending.period.end_time;
				periodRowEl.classList.remove( 'fsnw-hidden' );
			} else {
				periodRowEl.classList.add( 'fsnw-hidden' );
			}

			showState( 'request' );
		}

		function updateLiveStatus( state, pending ) {
			if ( ! statusEl ) {
				return;
			}

			pollCount += 1;
			var i18n = fsnwSignatureKiosk.i18n;
			var pendingLabel = 'undefined' === typeof pending ? '?' : ( pending ? ( 'id=' + pending.id ) : 'null' );
			statusEl.textContent = i18n.live + ' (' + state + ') – ' + i18n.tick + ' #' + pollCount + ' – ' + new Date().toLocaleTimeString( 'de-DE' ) +
				' – ' + i18n.pendingLabel + ':' + pendingLabel + ' – ' + i18n.busyLabel + ':' + isBusy;
		}

		function fetchPending() {
			// Bewusst ohne X-WP-Nonce: der Endpoint ist absichtlich ohne Login
			// nutzbar (Kiosk-Tablet) und ein auf dem Tablet zufällig vorhandener,
			// abgelaufener Session-Cookie würde einen falschen Nonce sonst hart
			// als Fehler abweisen.
			var url = fsnwSignatureKiosk.pendingUrl + '?_=' + Date.now();
			var controller = new AbortController();
			var timeout = setTimeout( function () {
				controller.abort();
			}, REQUEST_TIMEOUT_MS );

			fetch( url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal } )
				.then( function ( response ) {
					clearTimeout( timeout );

					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}

					return response.json();
				} )
				.then( function ( data ) {
					consecutiveFailures = 0;
					connectionEl.classList.add( 'fsnw-hidden' );
					updateLiveStatus( 'ok', data.pending );
					renderPending( data.pending );
				} )
				.catch( function ( error ) {
					clearTimeout( timeout );
					consecutiveFailures += 1;
					updateLiveStatus( fsnwSignatureKiosk.i18n.errorPrefix + ' ' + error.message );

					if ( consecutiveFailures >= 3 ) {
						connectionEl.textContent = fsnwSignatureKiosk.i18n.connectionError;
						connectionEl.classList.remove( 'fsnw-hidden' );

						if ( ! isCheckingConnectivity ) {
							isCheckingConnectivity = true;
							checkInternetConnectivity().then( function ( hasInternet ) {
								isCheckingConnectivity = false;

								if ( consecutiveFailures >= 3 ) {
									connectionEl.textContent = hasInternet
										? fsnwSignatureKiosk.i18n.connectionErrorServer
										: fsnwSignatureKiosk.i18n.connectionErrorOffline;
								}
							} );
						}
					}
				} );
		}

		clearButton.addEventListener( 'click', function () {
			signaturePad.clear();
			errorEl.textContent = '';
		} );

		confirmButton.addEventListener( 'click', function () {
			if ( ! currentRequestId || isBusy ) {
				return;
			}

			if ( signaturePad.isEmpty() ) {
				errorEl.textContent = fsnwSignatureKiosk.i18n.empty;
				return;
			}

			isBusy = true;
			confirmButton.disabled = true;
			errorEl.textContent = fsnwSignatureKiosk.i18n.submitting;

			fetch( fsnwSignatureKiosk.signaturesUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify( {
					request_id: currentRequestId,
					signature: signaturePad.toDataURL( 'image/png' ),
				} ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					confirmButton.disabled = false;

					if ( ! result.ok ) {
						isBusy = false;
						errorEl.textContent = result.data.message || fsnwSignatureKiosk.i18n.error;
						confirmButton.textContent = fsnwSignatureKiosk.i18n.retry;
						return;
					}

					currentRequestId = null;
					signaturePad.clear();
					showState( 'success' );

					// isBusy bleibt bis hierhin true, damit zwischenzeitliche
					// Polling-Ticks die Erfolgsanzeige nicht unterbrechen.
					setTimeout( function () {
						isBusy = false;
						showState( 'waiting' );
						fetchPending();
					}, SUCCESS_DISPLAY_MS );
				} )
				.catch( function () {
					isBusy = false;
					confirmButton.disabled = false;
					errorEl.textContent = fsnwSignatureKiosk.i18n.error;
					confirmButton.textContent = fsnwSignatureKiosk.i18n.retry;
				} );
		} );

		window.addEventListener( 'online', function () {
			connectionEl.classList.add( 'fsnw-hidden' );
			fetchPending();
		} );
		window.addEventListener( 'offline', function () {
			connectionEl.textContent = fsnwSignatureKiosk.i18n.connectionErrorOffline;
			connectionEl.classList.remove( 'fsnw-hidden' );
		} );

		function updateClock() {
			if ( ! clockEl ) {
				return;
			}

			var now = new Date();

			clockEl.textContent = now.toLocaleDateString( 'de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' } ) +
				' – ' + now.toLocaleTimeString( 'de-DE' );
		}

		showState( 'waiting' );
		fetchPending();
		updateClock();
		setInterval( fetchPending, POLL_INTERVAL_MS );
		setInterval( updateClock, 1000 );
		setInterval( function () {
			if ( ! isBusy ) {
				window.location.reload();
			}
		}, RELOAD_INTERVAL_MS );
	} );
} )();
