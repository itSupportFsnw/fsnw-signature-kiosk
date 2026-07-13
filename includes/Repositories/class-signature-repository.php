<?php
/**
 * Datenzugriffsschicht für Unterschriften.
 *
 * @package FsnwSignatureKiosk\Includes\Repositories
 */

namespace FsnwSignatureKiosk\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_sig_signatures.
 */
class SignatureRepository {

	/**
	 * WordPress-Datenbankverbindung.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Voll qualifizierter Tabellenname inkl. Präfix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'fsnw_sig_signatures';
	}

	/**
	 * Legt eine neue Unterschrift an und gibt deren ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte (request_id, signature_file, signed_at).
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Findet eine Unterschrift anhand ihrer ID.
	 *
	 * @param int $id Signatur-ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Findet die neueste Unterschrift zu einer Anforderung.
	 *
	 * @param int $request_id Anforderungs-ID.
	 */
	public function find_by_request( int $request_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE request_id = %d ORDER BY signed_at DESC, id DESC LIMIT 1', $this->table, $request_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}
}
