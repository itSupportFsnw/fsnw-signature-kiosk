<?php
/**
 * Admin-Controller: Einstellungsseite für Branding (Logo/Hintergrund).
 *
 * @package FsnwSignatureKiosk\Admin
 */

namespace FsnwSignatureKiosk\Admin;

use FsnwSignatureKiosk\Includes\Support\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Stellt eine schlanke Einstellungsseite unter "Einstellungen" bereit.
 */
class AdminController {

	/**
	 * Slug der Einstellungsseite.
	 */
	private const PAGE_SLUG = 'fsnw-signature-kiosk';

	/**
	 * Registriert alle Admin-Hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_fsnw_signature_kiosk_save_branding', array( $this, 'handle_save_branding' ) );
	}

	/**
	 * Registriert die Einstellungsseite unter "Einstellungen".
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'Signatur-Kiosk', 'fsnw-signature-kiosk' ),
			__( 'Signatur-Kiosk', 'fsnw-signature-kiosk' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Lädt den Media-Uploader nur auf der eigenen Einstellungsseite.
	 *
	 * @param string $hook_suffix Aktueller Admin-Seiten-Hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'fsnw-signature-kiosk-admin',
			FSNW_SIGNATURE_KIOSK_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FSNW_SIGNATURE_KIOSK_VERSION,
			true
		);
		wp_localize_script(
			'fsnw-signature-kiosk-admin',
			'fsnwSignatureKioskAdmin',
			array(
				'selectImageTitle' => __( 'Bild auswählen', 'fsnw-signature-kiosk' ),
				'useImageLabel'    => __( 'Bild verwenden', 'fsnw-signature-kiosk' ),
			)
		);
	}

	/**
	 * Rendert die Einstellungsseite.
	 */
	public function render_settings_page(): void {
		$logo_id        = (string) get_option( Branding::OPTION_LOGO_ID, '' );
		$logo_url       = Branding::get_logo_url( 'thumbnail' );
		$background_id  = (string) get_option( Branding::OPTION_BACKGROUND_ID, '' );
		$background_url = Branding::get_background_url( 'thumbnail' );

		include FSNW_SIGNATURE_KIOSK_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Speichert die Branding-Einstellungen.
	 */
	public function handle_save_branding(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'fsnw-signature-kiosk' ) );
		}

		check_admin_referer( 'fsnw_signature_kiosk_save_branding' );

		update_option( Branding::OPTION_LOGO_ID, absint( $_POST['logo_id'] ?? 0 ) );
		update_option( Branding::OPTION_BACKGROUND_ID, absint( $_POST['background_id'] ?? 0 ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'fsnw_saved' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
