<?php
/**
 * Öffentliche PHP-API für andere Plugins (Produzenten von Signatur-Anforderungen).
 *
 * @package FsnwSignatureKiosk\Includes
 */

namespace FsnwSignatureKiosk\Includes;

use FsnwSignatureKiosk\Includes\Services\RequestService;
use FsnwSignatureKiosk\Includes\Services\SignatureService;

defined( 'ABSPATH' ) || exit;

/**
 * Statische Fassade über RequestService/SignatureService.
 *
 * Andere Plugins prüfen per class_exists( '\FsnwSignatureKiosk\Includes\Api' )
 * auf die Verfügbarkeit des Kiosks und nutzen ausschließlich diese Klasse.
 * Der Abschluss einer Anforderung wird über die Hooks
 * `fsnw_signature_completed` bzw. `fsnw_signature_completed_{source}`
 * signalisiert (Argumente: $request_id, $signature_id, $source, $reference).
 */
class Api {

	/**
	 * Erstellt eine neue Signatur-Anforderung und gibt deren ID zurück.
	 *
	 * Eine evtl. noch offene Anforderung mit gleichem source+reference wird
	 * zuvor storniert (Idempotenz bei wiederholter Ausgabe).
	 *
	 * @param array<string, mixed> $args {
	 *     Anforderungsdaten.
	 *
	 *     @type string      $source         Slug des anfordernden Plugins (Pflicht).
	 *     @type string      $reference      Externe Referenz-ID, z. B. Buchungs-ID (Pflicht).
	 *     @type string      $title          Kontext-Titel, z. B. "Fahrzeugübergabe" (Pflicht).
	 *     @type string      $recipient_name Name der bestätigenden Person (Pflicht).
	 *     @type string[]    $items          Liste der ausgegebenen Positionen (Pflicht, min. 1).
	 *     @type string|null $period_start   Optionaler Zeitraum-Beginn ("Y-m-d H:i:s").
	 *     @type string|null $period_end     Optionales Zeitraum-Ende ("Y-m-d H:i:s").
	 *     @type string[]    $meta_lines     Optionale Zusatzzeilen.
	 * }
	 * @throws \InvalidArgumentException Wenn Pflichtfelder fehlen oder ungültig sind.
	 */
	public static function create_request( array $args ): int {
		return ( new RequestService() )->create( $args );
	}

	/**
	 * Storniert eine offene Anforderung.
	 *
	 * @param int $request_id Anforderungs-ID.
	 * @return bool True, wenn eine offene Anforderung storniert wurde.
	 */
	public static function cancel_request( int $request_id ): bool {
		return ( new RequestService() )->cancel( $request_id );
	}

	/**
	 * Storniert die offene Anforderung eines Produzenten anhand seiner Referenz.
	 *
	 * @param string $source    Slug des anfordernden Plugins.
	 * @param string $reference Externe Referenz-ID.
	 * @return bool True, wenn eine offene Anforderung storniert wurde.
	 */
	public static function cancel_by_reference( string $source, string $reference ): bool {
		return ( new RequestService() )->cancel_by_reference( $source, $reference );
	}

	/**
	 * Liefert eine Anforderung inkl. dekodierter Listenfelder, oder null.
	 *
	 * @param int $request_id Anforderungs-ID.
	 */
	public static function get_request( int $request_id ): ?array {
		return ( new RequestService() )->get( $request_id );
	}

	/**
	 * Liefert den Datensatz einer Unterschrift, oder null.
	 *
	 * @param int $signature_id Signatur-ID.
	 */
	public static function get_signature( int $signature_id ): ?array {
		return ( new SignatureService() )->find( $signature_id );
	}

	/**
	 * Liefert den absoluten Dateipfad eines Unterschrift-PNGs, oder null.
	 *
	 * @param int $signature_id Signatur-ID.
	 */
	public static function get_signature_file_path( int $signature_id ): ?string {
		return ( new SignatureService() )->get_file_path( $signature_id );
	}
}
