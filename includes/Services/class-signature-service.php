<?php
/**
 * Geschäftslogik für die PNG-Ablage der Unterschriften.
 *
 * @package FsnwSignatureKiosk\Includes\Services
 */

namespace FsnwSignatureKiosk\Includes\Services;

use FsnwSignatureKiosk\Includes\Repositories\SignatureRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Speichert Unterschriften als PNG im geschützten Upload-Verzeichnis
 * (siehe Activator::create_protected_signature_directory()) und liefert sie
 * ausschließlich über einen berechtigten REST-Endpoint aus.
 */
class SignatureService {

	/**
	 * Maximale Größe eines Signaturbildes in Byte (~2 MB).
	 */
	private const MAX_FILE_SIZE = 2 * 1024 * 1024;

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var SignatureRepository
	 */
	private SignatureRepository $repository;

	/**
	 * Konstruktor.
	 *
	 * @param SignatureRepository|null $repository Repository für den Datenbankzugriff.
	 */
	public function __construct( ?SignatureRepository $repository = null ) {
		$this->repository = $repository ?? new SignatureRepository();
	}

	/**
	 * Speichert eine per signature_pad erzeugte PNG-Unterschrift (Data-URL) im
	 * geschützten Upload-Verzeichnis und legt den zugehörigen Datenbankeintrag an.
	 *
	 * @param int    $request_id Zugehörige Anforderung.
	 * @param string $data_url   Unterschrift als "data:image/png;base64,…"-String.
	 * @throws \InvalidArgumentException Wenn die Unterschrift ungültig, leer oder zu groß ist.
	 */
	public function store( int $request_id, string $data_url ): int {
		$prefix = 'data:image/png;base64,';

		if ( ! str_starts_with( $data_url, $prefix ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Ungültiges Signaturformat.', 'fsnw-signature-kiosk' ) );
		}

		$binary = base64_decode( substr( $data_url, strlen( $prefix ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $binary || '' === $binary ) {
			throw new \InvalidArgumentException( esc_html__( 'Die Unterschrift ist leer oder beschädigt.', 'fsnw-signature-kiosk' ) );
		}

		if ( strlen( $binary ) > self::MAX_FILE_SIZE ) {
			throw new \InvalidArgumentException( esc_html__( 'Die Unterschrift ist zu groß.', 'fsnw-signature-kiosk' ) );
		}

		$filename   = 'request-' . $request_id . '-' . wp_generate_password( 12, false ) . '.png';
		$target_dir = $this->signatures_directory();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $target_dir . $filename, $binary ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Die Unterschrift konnte nicht gespeichert werden.', 'fsnw-signature-kiosk' ) );
		}

		return $this->repository->insert(
			array(
				'request_id'     => $request_id,
				'signature_file' => $filename,
				'signed_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Findet eine Unterschrift anhand ihrer ID.
	 *
	 * @param int $signature_id Signatur-ID.
	 */
	public function find( int $signature_id ): ?array {
		return $this->repository->find( $signature_id );
	}

	/**
	 * Liefert die neueste Unterschrift zu einer Anforderung.
	 *
	 * @param int $request_id Anforderungs-ID.
	 */
	public function find_by_request( int $request_id ): ?array {
		return $this->repository->find_by_request( $request_id );
	}

	/**
	 * Liefert den absoluten Dateipfad einer Unterschrift zur Auslieferung, oder
	 * null, wenn der Datensatz bzw. die Datei nicht (mehr) existiert.
	 *
	 * @param int $signature_id Signatur-ID.
	 */
	public function get_file_path( int $signature_id ): ?string {
		$signature = $this->repository->find( $signature_id );

		if ( null === $signature ) {
			return null;
		}

		$path = $this->signatures_directory() . $signature['signature_file'];

		return file_exists( $path ) ? $path : null;
	}

	/**
	 * Absoluter Pfad des geschützten Signatur-Upload-Verzeichnisses (mit abschließendem Slash).
	 */
	private function signatures_directory(): string {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'fsnw-signature-kiosk/signatures/';
	}
}
