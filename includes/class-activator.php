<?php
/**
 * Aktivierungsroutine: legt Datenbanktabellen und den geschützten
 * Upload-Ordner für Unterschriften an.
 *
 * @package FsnwSignatureKiosk\Includes
 */

namespace FsnwSignatureKiosk\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Wird über register_activation_hook() ausgeführt.
 */
class Activator {

	/**
	 * Führt die komplette Aktivierungsroutine aus.
	 */
	public static function activate(): void {
		self::create_tables();
		self::create_protected_signature_directory();

		update_option( 'fsnw_signature_kiosk_db_version', FSNW_SIGNATURE_KIOSK_DB_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Bringt das Datenbankschema bestehender Installationen auf den aktuellen Stand.
	 * dbDelta() ist idempotent und ergänzt lediglich fehlende Tabellen/Spalten,
	 * ohne bestehende Daten zu verändern.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'fsnw_signature_kiosk_db_version' ) === FSNW_SIGNATURE_KIOSK_DB_VERSION ) {
			return;
		}

		self::create_tables();

		update_option( 'fsnw_signature_kiosk_db_version', FSNW_SIGNATURE_KIOSK_DB_VERSION );
	}

	/**
	 * Legt die eigenen Datenbanktabellen per dbDelta() an bzw. aktualisiert sie.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$requests_table   = $wpdb->prefix . 'fsnw_sig_requests';
		$signatures_table = $wpdb->prefix . 'fsnw_sig_signatures';

		$sql = "CREATE TABLE {$requests_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(64) NOT NULL,
			reference VARCHAR(64) NOT NULL,
			title VARCHAR(190) NOT NULL,
			recipient_name VARCHAR(190) NOT NULL,
			items LONGTEXT NOT NULL,
			period_start DATETIME NULL,
			period_end DATETIME NULL,
			meta_lines LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY source_reference (source, reference, status)
		) {$charset_collate};
		CREATE TABLE {$signatures_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL,
			signature_file VARCHAR(255) NOT NULL,
			signed_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Legt das geschützte Upload-Verzeichnis für Unterschriften an
	 * (kein direkter Web-Zugriff, Auslieferung nur über den REST-Endpoint).
	 */
	private static function create_protected_signature_directory(): void {
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'fsnw-signature-kiosk/signatures/';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$htaccess_file = $target_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess_file, "Deny from all\n" );
		}

		$index_file = $target_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}
}
