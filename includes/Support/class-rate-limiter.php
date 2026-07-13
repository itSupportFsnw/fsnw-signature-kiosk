<?php
/**
 * Einfacher Transient-basierter Rate-Limiter für anonyme REST-Endpunkte.
 *
 * @package FsnwSignatureKiosk\Includes\Support
 */

namespace FsnwSignatureKiosk\Includes\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-Window-Rate-Limiter auf Basis von WordPress-Transients. Wird für die
 * Endpunkte eingesetzt, die bewusst ohne Capability-Check auskommen (Kiosk-
 * Tablet), da dort kein angemeldeter Nutzer als natürliche Bremse existiert.
 */
class RateLimiter {

	/**
	 * Prüft und zählt einen Aufruf innerhalb eines Zeitfensters.
	 *
	 * @param string $key            Eindeutiger Schlüssel (z. B. Routenname).
	 * @param int    $max_attempts   Maximal erlaubte Aufrufe innerhalb des Fensters.
	 * @param int    $window_seconds Fensterlänge in Sekunden.
	 * @return bool True, wenn der Aufruf noch erlaubt ist.
	 */
	public function check( string $key, int $max_attempts, int $window_seconds ): bool {
		$transient_key = 'fsnw_sig_rl_' . md5( $key . '|' . $this->client_identifier() );
		$data          = get_transient( $transient_key );
		$now           = time();

		// Neues Fenster starten, wenn noch keins läuft oder das alte bereits
		// abgelaufen ist. Wichtig: reset_at wird nur hier gesetzt und bei
		// nachfolgenden Aufrufen innerhalb des Fensters nicht mehr verschoben -
		// sonst verlängert ein Client, der schneller als window_seconds pollt,
		// sein eigenes Fenster bei jedem Aufruf und wird nie zurückgesetzt.
		if ( ! is_array( $data ) || ! isset( $data['count'], $data['reset_at'] ) || $data['reset_at'] <= $now ) {
			$data = array(
				'count'    => 0,
				'reset_at' => $now + $window_seconds,
			);
		}

		if ( $data['count'] >= $max_attempts ) {
			return false;
		}

		++$data['count'];
		set_transient( $transient_key, $data, $data['reset_at'] - $now );

		return true;
	}

	/**
	 * Ermittelt einen Client-Identifikator für die Zählung (IP-Adresse).
	 */
	private function client_identifier(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	}
}
