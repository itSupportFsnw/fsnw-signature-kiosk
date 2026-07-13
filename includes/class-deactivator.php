<?php
/**
 * Deaktivierungsroutine.
 *
 * @package FsnwSignatureKiosk\Includes
 */

namespace FsnwSignatureKiosk\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Wird über register_deactivation_hook() ausgeführt.
 *
 * Löscht bewusst keine Daten oder Tabellen - dies geschieht ausschließlich
 * in uninstall.php nach expliziter Löschung durch den Administrator.
 */
class Deactivator {

	/**
	 * Führt die Deaktivierungsroutine aus.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
