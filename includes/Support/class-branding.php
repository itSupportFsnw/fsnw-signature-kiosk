<?php
/**
 * Branding-Helper: Firmenlogo und Corporate-Hintergrund mit Fallback.
 *
 * @package FsnwSignatureKiosk\Includes\Support
 */

namespace FsnwSignatureKiosk\Includes\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Liest die im Einstellungen-Bereich hinterlegten Branding-Assets (Logo/Hintergrund)
 * aus und liefert Fallback-Werte (Text-Wortmarke/Verlauf), solange keine Assets
 * hochgeladen wurden.
 */
class Branding {

	/**
	 * Options-Key für die Logo-Attachment-ID.
	 *
	 * @var string
	 */
	public const OPTION_LOGO_ID = 'fsnw_signature_kiosk_logo_id';

	/**
	 * Options-Key für die Hintergrund-Attachment-ID.
	 *
	 * @var string
	 */
	public const OPTION_BACKGROUND_ID = 'fsnw_signature_kiosk_background_id';

	/**
	 * Gibt die Logo-URL zurück, oder false, wenn noch kein Logo hochgeladen wurde.
	 *
	 * @param string $size WordPress-Bildgröße.
	 * @return string|false
	 */
	public static function get_logo_url( string $size = 'medium' ) {
		$attachment_id = (int) get_option( self::OPTION_LOGO_ID, 0 );

		if ( ! $attachment_id ) {
			return false;
		}

		$url = wp_get_attachment_image_url( $attachment_id, $size );

		return $url ? $url : false;
	}

	/**
	 * Gibt die Hintergrundbild-URL zurück, oder false, wenn noch keiner hochgeladen wurde.
	 *
	 * @param string $size WordPress-Bildgröße.
	 * @return string|false
	 */
	public static function get_background_url( string $size = 'full' ) {
		$attachment_id = (int) get_option( self::OPTION_BACKGROUND_ID, 0 );

		if ( ! $attachment_id ) {
			return false;
		}

		$url = wp_get_attachment_image_url( $attachment_id, $size );

		return $url ? $url : false;
	}

	/**
	 * Gibt true zurück, wenn ein Logo hochgeladen wurde (keine Fallback-Wortmarke nötig).
	 */
	public static function has_logo(): bool {
		return false !== self::get_logo_url();
	}

	/**
	 * Gibt true zurück, wenn ein Corporate-Hintergrund hochgeladen wurde.
	 */
	public static function has_background(): bool {
		return false !== self::get_background_url();
	}

	/**
	 * Registriert `--fsnw-logo-url`/`--fsnw-bg-url` als Inline-CSS-Custom-Properties auf dem
	 * angegebenen Style-Handle, damit Templates/CSS sie referenzieren können, ohne die
	 * Attachment-URLs hart zu verdrahten. Fällt bei fehlenden Assets auf den
	 * Verlaufshintergrund-Token zurück.
	 *
	 * @param string $handle Bereits registriertes Style-Handle (z. B. 'fsnw-signature-kiosk-tokens').
	 */
	public static function inline_css_vars( string $handle ): void {
		$logo_url = self::get_logo_url();
		$bg_url   = self::get_background_url();

		$css  = ':root{';
		$css .= $logo_url ? sprintf( '--fsnw-logo-url:url(%s);', esc_url( $logo_url ) ) : '--fsnw-logo-url:none;';
		$css .= $bg_url ? sprintf( '--fsnw-bg-url:url(%s);', esc_url( $bg_url ) ) : '--fsnw-bg-url:var(--fsnw-gradient-hero);';
		$css .= '}';

		wp_add_inline_style( $handle, $css );
	}
}
