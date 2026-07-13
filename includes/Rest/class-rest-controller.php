<?php
/**
 * Abstrakte Basisklasse für alle REST-Controller.
 *
 * @package FsnwSignatureKiosk\Includes\Rest
 */

namespace FsnwSignatureKiosk\Includes\Rest;

use FsnwSignatureKiosk\Includes\Support\RateLimiter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stellt gemeinsame Hilfsmethoden für die REST-Controller im Namespace fsnw-signature-kiosk/v1 bereit.
 */
abstract class RestController {

	/**
	 * REST-API-Namespace des Plugins.
	 */
	protected const NAMESPACE_V1 = 'fsnw-signature-kiosk/v1';

	/**
	 * Registriert die Routen dieses Controllers.
	 */
	abstract public function register_routes(): void;

	/**
	 * Liefert einen permission_callback, der eine Capability prüft.
	 *
	 * @param string $capability Zu prüfende Capability.
	 */
	protected function permission_check( string $capability ): callable {
		return static function () use ( $capability ): bool {
			return current_user_can( $capability );
		};
	}

	/**
	 * Baut ein REST-Args-Schema-Element für register_rest_route().
	 *
	 * @param string        $type     REST-Datentyp (integer|string|boolean).
	 * @param bool          $required Ob der Parameter Pflicht ist.
	 * @param callable|null $sanitize Sanitize-Callback.
	 * @param callable|null $validate Validate-Callback.
	 * @return array<string, mixed>
	 */
	protected function arg( string $type, bool $required = false, ?callable $sanitize = null, ?callable $validate = null ): array {
		$schema = array(
			'type'     => $type,
			'required' => $required,
		);

		if ( null !== $sanitize ) {
			$schema['sanitize_callback'] = $sanitize;
		}

		if ( null !== $validate ) {
			$schema['validate_callback'] = $validate;
		}

		return $schema;
	}

	/**
	 * Args-Schema für eine positive Integer-ID (z. B. Pfadparameter {id}).
	 *
	 * @param bool $required Ob der Parameter Pflicht ist.
	 * @return array<string, mixed>
	 */
	protected function id_arg( bool $required = true ): array {
		return $this->arg(
			'integer',
			$required,
			'absint',
			static function ( $value ): bool {
				return is_numeric( $value ) && (int) $value > 0;
			}
		);
	}

	/**
	 * Prüft ein Rate-Limit für anonyme Endpunkte und liefert bei Überschreitung
	 * einen fertigen 429-Fehler zurück.
	 *
	 * @param string $key            Eindeutiger Schlüssel (z. B. Routenname).
	 * @param int    $max_attempts   Maximal erlaubte Aufrufe innerhalb des Fensters.
	 * @param int    $window_seconds Fensterlänge in Sekunden.
	 * @return WP_Error|null Null, wenn der Aufruf erlaubt ist.
	 */
	protected function rate_limit( string $key, int $max_attempts, int $window_seconds ): ?WP_Error {
		$limiter = new RateLimiter();

		if ( $limiter->check( $key, $max_attempts, $window_seconds ) ) {
			return null;
		}

		return new WP_Error(
			'fsnw_sig_rate_limited',
			__( 'Zu viele Anfragen. Bitte kurz warten und erneut versuchen.', 'fsnw-signature-kiosk' ),
			array( 'status' => 429 )
		);
	}
}
