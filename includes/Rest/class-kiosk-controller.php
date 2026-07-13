<?php
/**
 * REST-Endpunkte für das Kiosk-Tablet (Polling + Abgabe der Unterschrift).
 *
 * @package FsnwSignatureKiosk\Includes\Rest
 */

namespace FsnwSignatureKiosk\Includes\Rest;

use FsnwSignatureKiosk\Includes\Services\RequestService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Beide Endpunkte sind bewusst anonym erreichbar: Das Kiosk-Tablet läuft
 * dauerhaft ohne angemeldeten Nutzer. Als Schutz dienen Rate-Limits pro
 * Client-IP; die Anforderungs-IDs sind nicht erratbar relevant, da eine
 * Unterschrift nur eine ohnehin offene Anforderung abschließen kann.
 */
class KioskController extends RestController {

	/**
	 * Service für die Anforderungs-Queue.
	 *
	 * @var RequestService
	 */
	private RequestService $request_service;

	/**
	 * Konstruktor.
	 *
	 * @param RequestService|null $request_service Service für die Anforderungs-Queue.
	 */
	public function __construct( ?RequestService $request_service = null ) {
		$this->request_service = $request_service ?? new RequestService();
	}

	/**
	 * Registriert die Kiosk-Routen.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/kiosk/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pending' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/kiosk/signatures',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'store' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'request_id' => $this->id_arg(),
					'signature'  => $this->arg( 'string', true ),
				),
			)
		);
	}

	/**
	 * Liefert die älteste offene Signatur-Anforderung für das Kiosk-Polling.
	 *
	 * @param WP_REST_Request $request REST-Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_pending( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'kiosk_pending', 120, MINUTE_IN_SECONDS );

		if ( null !== $limited ) {
			return $limited;
		}

		// Anonyme REST-Antworten werden von WordPress nicht automatisch als
		// nicht-cachebar markiert - für das Polling ist das aber zwingend.
		nocache_headers();

		$pending = $this->request_service->get_pending();

		return new WP_REST_Response(
			array(
				'pending' => null === $pending ? null : $this->format_pending( $pending ),
			)
		);
	}

	/**
	 * Nimmt eine Unterschrift entgegen und schließt die Anforderung ab.
	 *
	 * @param WP_REST_Request $request REST-Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public function store( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'kiosk_signatures_store', 20, MINUTE_IN_SECONDS );

		if ( null !== $limited ) {
			return $limited;
		}

		try {
			$signature_id = $this->request_service->complete(
				(int) $request->get_param( 'request_id' ),
				(string) $request->get_param( 'signature' )
			);
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error( 'fsnw_sig_invalid', $exception->getMessage(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'signature_id' => $signature_id,
			)
		);
	}

	/**
	 * Formatiert eine Anforderung als Kiosk-Payload.
	 *
	 * @param array<string, mixed> $pending Dekodierte Anforderung.
	 * @return array<string, mixed>
	 */
	private function format_pending( array $pending ): array {
		$period = null;

		if ( ! empty( $pending['period_start'] ) && ! empty( $pending['period_end'] ) ) {
			$period = array(
				'start_date' => substr( (string) $pending['period_start'], 0, 10 ),
				'start_time' => substr( (string) $pending['period_start'], 11, 5 ),
				'end_date'   => substr( (string) $pending['period_end'], 0, 10 ),
				'end_time'   => substr( (string) $pending['period_end'], 11, 5 ),
			);
		}

		return array(
			'id'             => (int) $pending['id'],
			'title'          => $pending['title'],
			'recipient_name' => $pending['recipient_name'],
			'items'          => $pending['items'],
			'period'         => $period,
			'meta_lines'     => $pending['meta_lines'],
		);
	}
}
