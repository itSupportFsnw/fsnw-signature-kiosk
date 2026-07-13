<?php
/**
 * Datenzugriffsschicht für Signatur-Anforderungen.
 *
 * @package FsnwSignatureKiosk\Includes\Repositories
 */

namespace FsnwSignatureKiosk\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_sig_requests.
 */
class RequestRepository {

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
		$this->table = $wpdb->prefix . 'fsnw_sig_requests';
	}

	/**
	 * Legt eine neue Anforderung an und gibt deren ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Aktualisiert eine Anforderung.
	 *
	 * @param int                  $id   Anforderungs-ID.
	 * @param array<string, mixed> $data Zu ändernde Spaltenwerte.
	 */
	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/**
	 * Findet eine Anforderung anhand ihrer ID.
	 *
	 * @param int $id Anforderungs-ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Liefert die älteste offene Anforderung (FIFO-Queue für den Kiosk).
	 */
	public function find_oldest_pending(): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s ORDER BY created_at ASC, id ASC LIMIT 1',
				$this->table,
				'pending'
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Findet die offene Anforderung eines Produzenten anhand seiner Referenz.
	 *
	 * @param string $source    Slug des anfordernden Plugins.
	 * @param string $reference Externe Referenz-ID.
	 */
	public function find_pending_by_reference( string $source, string $reference ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE source = %s AND reference = %s AND status = %s ORDER BY id DESC LIMIT 1',
				$this->table,
				$source,
				$reference,
				'pending'
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}
}
