<?php
/**
 * Frontend-Controller: Shortcode und Asset-Auslieferung für die Kiosk-Seite.
 *
 * @package FsnwSignatureKiosk\Frontend
 */

namespace FsnwSignatureKiosk\Frontend;

use FsnwSignatureKiosk\Includes\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert den Kiosk-Shortcode und lädt CSS/JS nur auf Seiten, die ihn nutzen.
 */
class FrontendController {

	/**
	 * Registriert alle Frontend-Hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registriert den Kiosk-Shortcode.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'wp_fsnw_signature_kiosk', array( $this, 'render_kiosk_shortcode' ) );
	}

	/**
	 * Rendert die Kiosk-Seite. Bewusst ohne Login-Prüfung: das Tablet läuft
	 * dauerhaft ohne angemeldeten Nutzer, alle Endpunkte sind rate-limitiert.
	 */
	public function render_kiosk_shortcode(): string {
		ob_start();
		include FSNW_SIGNATURE_KIOSK_PLUGIN_DIR . 'templates/kiosk/kiosk-page.php';

		return (string) ob_get_clean();
	}

	/**
	 * Lädt CSS/JS nur auf Seiten mit dem Kiosk-Shortcode.
	 *
	 * Der Legacy-Shortcode wp_fsnw_car_signature wird übergangsweise mit
	 * geprüft, damit die bestehende Tablet-Seite nach der Umstellung von
	 * wp-fsnw-car-rent ohne Seitenbearbeitung weiterläuft (der dortige
	 * Deprecation-Wrapper rendert unser Template).
	 */
	public function enqueue_assets(): void {
		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if (
			! has_shortcode( $post->post_content, 'wp_fsnw_signature_kiosk' )
			&& ! has_shortcode( $post->post_content, 'wp_fsnw_car_signature' )
		) {
			return;
		}

		wp_enqueue_style( 'fsnw-signature-kiosk-tokens', FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/css/tokens.css', array(), FSNW_SIGNATURE_KIOSK_VERSION );
		Branding::inline_css_vars( 'fsnw-signature-kiosk-tokens' );
		wp_enqueue_style( 'fsnw-signature-kiosk-base', FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/css/base.css', array( 'fsnw-signature-kiosk-tokens' ), FSNW_SIGNATURE_KIOSK_VERSION );
		wp_enqueue_style( 'fsnw-signature-kiosk-kiosk', FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/css/kiosk.css', array( 'fsnw-signature-kiosk-tokens', 'fsnw-signature-kiosk-base' ), FSNW_SIGNATURE_KIOSK_VERSION );
		wp_enqueue_script(
			'fsnw-signature-kiosk-signature-pad',
			FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/vendor/signature_pad/signature_pad.umd.min.js',
			array(),
			'4.1.7',
			true
		);
		wp_enqueue_script(
			'fsnw-signature-kiosk-kiosk',
			FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/js/signature-kiosk.js',
			array( 'fsnw-signature-kiosk-signature-pad' ),
			FSNW_SIGNATURE_KIOSK_VERSION,
			true
		);
		wp_localize_script(
			'fsnw-signature-kiosk-kiosk',
			'fsnwSignatureKiosk',
			array(
				'pendingUrl'    => rest_url( 'fsnw-signature-kiosk/v1/kiosk/pending' ),
				'signaturesUrl' => rest_url( 'fsnw-signature-kiosk/v1/kiosk/signatures' ),
				'i18n'          => array(
					'waiting'                => __( 'Warten auf Anforderung.', 'fsnw-signature-kiosk' ),
					'empty'                  => __( 'Bitte unterschreibe im markierten Feld.', 'fsnw-signature-kiosk' ),
					'confirm'                => __( 'Bestätigen', 'fsnw-signature-kiosk' ),
					'clear'                  => __( 'Löschen', 'fsnw-signature-kiosk' ),
					'submitting'             => __( 'Wird gespeichert …', 'fsnw-signature-kiosk' ),
					'success'                => __( 'Danke!', 'fsnw-signature-kiosk' ),
					'error'                  => __( 'Fehler beim Speichern der Unterschrift. Bitte erneut versuchen.', 'fsnw-signature-kiosk' ),
					'connectionError'        => __( 'Verbindung unterbrochen – erneuter Versuch …', 'fsnw-signature-kiosk' ),
					'connectionErrorServer'  => __( 'Server nicht erreichbar (Internet vorhanden) – erneuter Versuch …', 'fsnw-signature-kiosk' ),
					'connectionErrorOffline' => __( 'Kein Internet – bitte WLAN-Verbindung des Tablets prüfen', 'fsnw-signature-kiosk' ),
					'live'                   => __( 'Live', 'fsnw-signature-kiosk' ),
					'tick'                   => __( 'Tick', 'fsnw-signature-kiosk' ),
					'errorPrefix'            => __( 'Fehler:', 'fsnw-signature-kiosk' ),
					'pendingLabel'           => __( 'ausstehend', 'fsnw-signature-kiosk' ),
					'busyLabel'              => __( 'belegt', 'fsnw-signature-kiosk' ),
					'retry'                  => __( 'Erneut senden', 'fsnw-signature-kiosk' ),
				),
			)
		);
	}
}
