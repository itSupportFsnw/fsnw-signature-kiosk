<?php
/**
 * Frontend-Template für [wp_fsnw_signature_kiosk] - der Vollbild-Kiosk-Modus
 * für digitale Unterschriften auf dem Tablet.
 *
 * Läuft dauerhaft im Browser des Tablets, ohne Anmeldung. Die Zustände
 * (Warten/Anforderung/Erfolg) werden ausschließlich per JavaScript
 * (assets/js/signature-kiosk.js) per REST-Polling umgeschaltet. Die Karte
 * rendert generische Anforderungsdaten (Titel, Empfänger, Positionen,
 * optional Zeitraum/Zusatzzeilen) - die Inhalte kommen vom Produzenten-Plugin.
 *
 * @package FsnwSignatureKiosk
 */

use FsnwSignatureKiosk\Includes\Support\Branding;
use FsnwSignatureKiosk\Includes\Support\Icons;

defined( 'ABSPATH' ) || exit;
?>
<div id="fsnw-signature-app" class="fsnw-signature-kiosk">
	<p id="fsnw-signature-connection" class="fsnw-signature-connection fsnw-hidden" role="status"></p>
	<p id="fsnw-signature-livestatus" class="fsnw-signature-livestatus"></p>

	<div id="fsnw-signature-waiting" class="fsnw-signature-state fsnw-signature-waiting">
		<?php if ( Branding::has_logo() ) : ?>
			<img class="fsnw-signature-waiting-logo-image" src="<?php echo esc_url( Branding::get_logo_url( 'large' ) ); ?>" alt="">
		<?php else : ?>
			<p class="fsnw-signature-waiting-logo"><?php esc_html_e( 'FSNW', 'fsnw-signature-kiosk' ); ?></p>
		<?php endif; ?>
		<p class="fsnw-signature-waiting-clock" id="fsnw-signature-clock" aria-hidden="true"></p>
	</div>

	<div id="fsnw-signature-request" class="fsnw-signature-state fsnw-hidden">
		<div class="fsnw-card fsnw-signature-card">
			<div class="fsnw-signature-details">
				<p class="fsnw-signature-context" id="fsnw-signature-title"></p>
				<h1 id="fsnw-signature-recipient"></h1>
				<ul class="fsnw-signature-items" id="fsnw-signature-items"></ul>
				<p class="fsnw-signature-meta fsnw-hidden" id="fsnw-signature-period-row">
					<?php echo Icons::svg( 'clock', array( 'class' => 'fsnw-icon fsnw-icon--inline' ) ); ?>
					<span id="fsnw-signature-period"></span>
				</p>
				<div id="fsnw-signature-meta-lines"></div>
			</div>

			<canvas id="fsnw-signature-canvas" class="fsnw-signature-canvas"></canvas>

			<p id="fsnw-signature-error" class="fsnw-signature-error" role="alert"></p>

			<div class="fsnw-signature-actions">
				<button type="button" id="fsnw-signature-clear" class="fsnw-btn fsnw-btn--secondary fsnw-btn--large">
					<?php echo Icons::svg( 'x', array( 'class' => 'fsnw-icon' ) ); ?>
					<?php esc_html_e( 'Löschen', 'fsnw-signature-kiosk' ); ?>
				</button>
				<button type="button" id="fsnw-signature-confirm" class="fsnw-btn fsnw-btn--primary fsnw-btn--large">
					<?php echo Icons::svg( 'check', array( 'class' => 'fsnw-icon' ) ); ?>
					<?php esc_html_e( 'Bestätigen', 'fsnw-signature-kiosk' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div id="fsnw-signature-success" class="fsnw-signature-state fsnw-hidden">
		<?php echo Icons::svg( 'check-circle', array( 'class' => 'fsnw-icon fsnw-signature-success-icon' ) ); ?>
		<p class="fsnw-signature-success-text"><?php esc_html_e( 'Danke!', 'fsnw-signature-kiosk' ); ?></p>
	</div>
</div>
