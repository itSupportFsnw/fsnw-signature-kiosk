<?php
/**
 * Inline-SVG-Icon-Set (Pfaddaten im Lucide-Stil, ISC-Lizenz, https://lucide.dev).
 *
 * @package FsnwSignatureKiosk\Includes\Support
 */

namespace FsnwSignatureKiosk\Includes\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Rendert das vom Kiosk benötigte Set moderner Outline-Icons als Inline-SVG.
 */
class Icons {

	/**
	 * SVG-Pfaddaten je Icon-Name (24x24 Viewbox, stroke-basiert).
	 *
	 * @var array<string, string>
	 */
	private const PATHS = array(
		'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5"/>',
		'user'         => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
		'x'            => '<path d="M18 6 6 18M6 6l12 12"/>',
		'check'        => '<path d="m5 12 5 5 9-9"/>',
		'wifi-off'     => '<path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 6.6 0M5 12.5a10 10 0 0 1 3.5-2.3M19 12.5a10 10 0 0 0-3-2M12 20h.01"/>',
		'pen'          => '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
	);

	/**
	 * Gibt Inline-SVG-Markup für ein benanntes Icon zurück.
	 *
	 * @param string               $name  Icon-Name, siehe self::PATHS.
	 * @param array<string,string> $attrs Zusätzliche HTML-Attribute (z. B. class).
	 * @return string Escapetes SVG-Markup, oder leerer String bei unbekanntem Namen.
	 */
	public static function svg( string $name, array $attrs = array() ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}

		$attrs = wp_parse_args(
			$attrs,
			array(
				'class' => 'fsnw-icon',
			)
		);

		$attr_string = '';

		foreach ( $attrs as $key => $value ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return sprintf(
			'<svg%1$s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			$attr_string,
			self::PATHS[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statische, fest hinterlegte SVG-Pfaddaten, kein Nutzerinput.
		);
	}

	/**
	 * Gibt eine base64-kodierte SVG-Data-URI zurück, geeignet als WP-Admin-Menü-Icon
	 * (einfarbige Silhouette, wird von WordPress automatisch eingefärbt).
	 *
	 * @return string Data-URI für add_menu_page().
	 */
	public static function menu_icon_data_uri(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Erforderlich für WP add_menu_page() Data-URI-Icons, keine Verschleierung.
	}
}
