<?php
/**
 * Geschäftslogik für Signatur-Anforderungen (die Kiosk-Queue).
 *
 * @package FsnwSignatureKiosk\Includes\Services
 */

namespace FsnwSignatureKiosk\Includes\Services;

use FsnwSignatureKiosk\Includes\Repositories\RequestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet den Lebenszyklus von Signatur-Anforderungen:
 * pending → completed (Unterschrift) bzw. pending → cancelled (Abbruch).
 * Die Queue ist die einzige Quelle der Wahrheit dafür, was der Kiosk anzeigt.
 */
class RequestService {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $repository;

	/**
	 * Service für die PNG-Ablage der Unterschriften.
	 *
	 * @var SignatureService
	 */
	private SignatureService $signature_service;

	/**
	 * Konstruktor.
	 *
	 * @param RequestRepository|null $repository        Repository für den Datenbankzugriff.
	 * @param SignatureService|null  $signature_service Service für die PNG-Ablage.
	 */
	public function __construct( ?RequestRepository $repository = null, ?SignatureService $signature_service = null ) {
		$this->repository        = $repository ?? new RequestRepository();
		$this->signature_service = $signature_service ?? new SignatureService();
	}

	/**
	 * Erstellt eine neue Anforderung und gibt deren ID zurück.
	 *
	 * Eine evtl. noch offene Anforderung mit gleichem source+reference wird
	 * zuvor storniert, damit wiederholtes Ausgeben nicht mehrere Einträge
	 * in der Kiosk-Queue erzeugt.
	 *
	 * @param array<string, mixed> $args Anforderungsdaten (siehe Api::create_request()).
	 * @throws \InvalidArgumentException Wenn Pflichtfelder fehlen oder ungültig sind.
	 */
	public function create( array $args ): int {
		$source         = sanitize_key( (string) ( $args['source'] ?? '' ) );
		$reference      = sanitize_text_field( (string) ( $args['reference'] ?? '' ) );
		$title          = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$recipient_name = sanitize_text_field( (string) ( $args['recipient_name'] ?? '' ) );
		$items          = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $args['items'] ?? array() ) ) ) );
		$meta_lines     = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $args['meta_lines'] ?? array() ) ) ) );

		if ( '' === $source || '' === $reference || '' === $title || '' === $recipient_name || empty( $items ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Signatur-Anforderung unvollständig: source, reference, title, recipient_name und mindestens eine Position sind Pflicht.', 'fsnw-signature-kiosk' )
			);
		}

		$this->cancel_by_reference( $source, $reference );

		$now = current_time( 'mysql' );

		return $this->repository->insert(
			array(
				'source'         => $source,
				'reference'      => $reference,
				'title'          => $title,
				'recipient_name' => $recipient_name,
				'items'          => wp_json_encode( $items ),
				'period_start'   => $this->sanitize_datetime( $args['period_start'] ?? null ),
				'period_end'     => $this->sanitize_datetime( $args['period_end'] ?? null ),
				'meta_lines'     => empty( $meta_lines ) ? null : wp_json_encode( $meta_lines ),
				'status'         => self::STATUS_PENDING,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
	}

	/**
	 * Storniert eine offene Anforderung.
	 *
	 * @param int $request_id Anforderungs-ID.
	 * @return bool True, wenn eine offene Anforderung storniert wurde.
	 */
	public function cancel( int $request_id ): bool {
		$request = $this->repository->find( $request_id );

		if ( null === $request || self::STATUS_PENDING !== $request['status'] ) {
			return false;
		}

		return $this->repository->update(
			$request_id,
			array(
				'status'     => self::STATUS_CANCELLED,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Storniert die offene Anforderung eines Produzenten anhand seiner Referenz.
	 *
	 * @param string $source    Slug des anfordernden Plugins.
	 * @param string $reference Externe Referenz-ID.
	 * @return bool True, wenn eine offene Anforderung storniert wurde.
	 */
	public function cancel_by_reference( string $source, string $reference ): bool {
		$request = $this->repository->find_pending_by_reference( $source, $reference );

		if ( null === $request ) {
			return false;
		}

		return $this->cancel( (int) $request['id'] );
	}

	/**
	 * Liefert eine Anforderung inkl. dekodierter Listenfelder, oder null.
	 *
	 * @param int $request_id Anforderungs-ID.
	 */
	public function get( int $request_id ): ?array {
		$request = $this->repository->find( $request_id );

		return null === $request ? null : $this->decode( $request );
	}

	/**
	 * Liefert die älteste offene Anforderung für den Kiosk, oder null.
	 */
	public function get_pending(): ?array {
		$request = $this->repository->find_oldest_pending();

		return null === $request ? null : $this->decode( $request );
	}

	/**
	 * Schließt eine offene Anforderung mit einer Unterschrift ab.
	 *
	 * Speichert das PNG, markiert die Anforderung als abgeschlossen und feuert
	 * die Abschluss-Hooks für den Produzenten.
	 *
	 * @param int    $request_id Anforderungs-ID.
	 * @param string $data_url   Unterschrift als "data:image/png;base64,…"-String.
	 * @return int Die ID der gespeicherten Unterschrift.
	 * @throws \InvalidArgumentException Wenn die Anforderung nicht offen oder die Unterschrift ungültig ist.
	 */
	public function complete( int $request_id, string $data_url ): int {
		$request = $this->repository->find( $request_id );

		if ( null === $request || self::STATUS_PENDING !== $request['status'] ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Diese Anforderung ist nicht (mehr) offen.', 'fsnw-signature-kiosk' )
			);
		}

		$signature_id = $this->signature_service->store( $request_id, $data_url );

		$now = current_time( 'mysql' );

		$this->repository->update(
			$request_id,
			array(
				'status'       => self::STATUS_COMPLETED,
				'updated_at'   => $now,
				'completed_at' => $now,
			)
		);

		/**
		 * Signalisiert den Abschluss einer Signatur-Anforderung.
		 *
		 * @param int    $request_id   Anforderungs-ID.
		 * @param int    $signature_id Signatur-ID (für Api::get_signature_file_path()).
		 * @param string $source       Slug des anfordernden Plugins.
		 * @param string $reference    Externe Referenz-ID des Produzenten.
		 */
		do_action( 'fsnw_signature_completed', $request_id, $signature_id, $request['source'], $request['reference'] );

		/** This action is documented above. */
		do_action( "fsnw_signature_completed_{$request['source']}", $request_id, $signature_id, $request['source'], $request['reference'] );

		return $signature_id;
	}

	/**
	 * Dekodiert die JSON-Listenfelder einer Datenbankzeile.
	 *
	 * @param array<string, mixed> $request Rohe Datenbankzeile.
	 */
	private function decode( array $request ): array {
		$items      = json_decode( (string) $request['items'], true );
		$meta_lines = empty( $request['meta_lines'] ) ? array() : json_decode( (string) $request['meta_lines'], true );

		$request['id']         = (int) $request['id'];
		$request['items']      = is_array( $items ) ? $items : array();
		$request['meta_lines'] = is_array( $meta_lines ) ? $meta_lines : array();

		return $request;
	}

	/**
	 * Validiert einen optionalen "Y-m-d H:i:s"-Zeitstempel, sonst null.
	 *
	 * @param mixed $value Eingabewert.
	 */
	private function sanitize_datetime( $value ): ?string {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );

		return ( $datetime && $datetime->format( 'Y-m-d H:i:s' ) === $value ) ? $value : null;
	}
}
