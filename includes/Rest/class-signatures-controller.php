<?php
/**
 * REST-Endpunkt zur Auslieferung gespeicherter Unterschrift-PNGs.
 *
 * @package FsnwSignatureKiosk\Includes\Rest
 */

namespace FsnwSignatureKiosk\Includes\Rest;

use FsnwSignatureKiosk\Includes\Repositories\RequestRepository;
use FsnwSignatureKiosk\Includes\Services\SignatureService;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Liefert Unterschriften aus dem geschützten Upload-Verzeichnis aus.
 * Die Berechtigung ist per Filter erweiterbar, damit Produzenten-Plugins
 * ihre eigenen Capabilities freischalten können.
 */
class SignaturesController extends RestController {

	/**
	 * Service für die PNG-Ablage.
	 *
	 * @var SignatureService
	 */
	private SignatureService $signature_service;

	/**
	 * Konstruktor.
	 *
	 * @param SignatureService|null $signature_service Service für die PNG-Ablage.
	 */
	public function __construct( ?SignatureService $signature_service = null ) {
		$this->signature_service = $signature_service ?? new SignatureService();
	}

	/**
	 * Registriert die Signatur-Routen.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/signatures/(?P<id>\d+)/image',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'image' ),
				'permission_callback' => array( $this, 'can_view_image' ),
				'args'                => array(
					'id' => $this->id_arg(),
				),
			)
		);
	}

	/**
	 * Prüft die Berechtigung zum Abruf eines Unterschrift-Bildes.
	 *
	 * @param WP_REST_Request $request REST-Anfrage.
	 */
	public function can_view_image( WP_REST_Request $request ): bool {
		$signature_id = (int) $request->get_param( 'id' );
		$source       = $this->resolve_source( $signature_id );

		/**
		 * Erlaubt Produzenten-Plugins, den Bild-Abruf für ihre eigenen
		 * Capabilities freizuschalten (z. B. fsnw_manage_dispatch für
		 * Unterschriften mit source "fsnw-car-rent").
		 *
		 * @param bool   $allowed      Standard: manage_options.
		 * @param int    $signature_id Signatur-ID.
		 * @param string $source       Slug des Produzenten-Plugins ('' wenn unbekannt).
		 */
		return (bool) apply_filters(
			'fsnw_signature_kiosk_can_view_image',
			current_user_can( 'manage_options' ),
			$signature_id,
			$source
		);
	}

	/**
	 * Streamt das Unterschrift-PNG.
	 *
	 * @param WP_REST_Request $request REST-Anfrage.
	 * @return WP_Error|void
	 */
	public function image( WP_REST_Request $request ) {
		$path = $this->signature_service->get_file_path( (int) $request->get_param( 'id' ) );

		if ( null === $path ) {
			return new WP_Error(
				'fsnw_sig_not_found',
				__( 'Unterschrift nicht gefunden.', 'fsnw-signature-kiosk' ),
				array( 'status' => 404 )
			);
		}

		header( 'Content-Type: image/png' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Ermittelt den Produzenten (source) einer Unterschrift über ihre Anforderung.
	 *
	 * @param int $signature_id Signatur-ID.
	 */
	private function resolve_source( int $signature_id ): string {
		$signature = $this->signature_service->find( $signature_id );

		if ( null === $signature ) {
			return '';
		}

		$request = ( new RequestRepository() )->find( (int) $signature['request_id'] );

		return null === $request ? '' : (string) $request['source'];
	}
}
