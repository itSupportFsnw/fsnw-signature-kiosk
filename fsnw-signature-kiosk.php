<?php
/**
 * Plugin Name:       FSNW Signature Kiosk
 * Plugin URI:        https://github.com/itSupportFsnw/fsnw-signature-kiosk
 * Description:       Generischer Tablet-Signatur-Kiosk – andere Plugins senden Signatur-Anforderungen (Ausgabe von Fahrzeugen, Geräten usw.), Empfänger bestätigen per Unterschrift auf dem Tablet.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            freestyle Jugendhilfe gGmbh
 * Author URI:        https://www.freestyle-jugendhilfe.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fsnw-signature-kiosk
 * Domain Path:       /languages
 *
 * @package FsnwSignatureKiosk
 */

defined( 'ABSPATH' ) || exit;

define( 'FSNW_SIGNATURE_KIOSK_VERSION', '0.3.0' );
define( 'FSNW_SIGNATURE_KIOSK_DB_VERSION', '0.1.0' );
define( 'FSNW_SIGNATURE_KIOSK_PLUGIN_FILE', __FILE__ );
define( 'FSNW_SIGNATURE_KIOSK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSNW_SIGNATURE_KIOSK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$fsnw_signature_kiosk_autoloader = FSNW_SIGNATURE_KIOSK_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $fsnw_signature_kiosk_autoloader ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'FSNW Signature Kiosk: Composer-Abhängigkeiten fehlen. Bitte "composer install" im Plugin-Verzeichnis ausführen.', 'fsnw-signature-kiosk' )
			);
		}
	);
	return;
}

require_once $fsnw_signature_kiosk_autoloader;

register_activation_hook( __FILE__, array( \FsnwSignatureKiosk\Includes\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \FsnwSignatureKiosk\Includes\Deactivator::class, 'deactivate' ) );

\FsnwSignatureKiosk\Includes\Plugin::instance()->run();
