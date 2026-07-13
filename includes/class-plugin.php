<?php
/**
 * Zentrale Plugin-Orchestrierung.
 *
 * @package FsnwSignatureKiosk\Includes
 */

namespace FsnwSignatureKiosk\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps alle Controller, Services und REST-Routen des Plugins.
 */
class Plugin {

	/**
	 * Singleton-Instanz.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Gibt die einzige Plugin-Instanz zurück.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Privater Konstruktor - Instanziierung nur über instance().
	 */
	private function __construct() {}

	/**
	 * Registriert alle Hooks und startet das Plugin.
	 */
	public function run(): void {
		Activator::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Lädt die Übersetzungsdateien.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'fsnw-signature-kiosk',
			false,
			dirname( plugin_basename( FSNW_SIGNATURE_KIOSK_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
