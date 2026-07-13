<?php
/**
 * Admin-Ansicht: Einstellungen (Shortcode-Übersicht, Branding).
 *
 * @package FsnwSignatureKiosk
 *
 * @var string       $logo_id
 * @var string|false $logo_url
 * @var string       $background_id
 * @var string|false $background_url
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Signatur-Kiosk', 'fsnw-signature-kiosk' ); ?></h1>

	<?php if ( isset( $_GET['fsnw_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'fsnw-signature-kiosk' ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Shortcode', 'fsnw-signature-kiosk' ); ?></h2>
	<p>
		<code>[wp_fsnw_signature_kiosk]</code> –
		<?php esc_html_e( 'Vollbild-Kiosk-Modus für digitale Unterschriften auf einem dauerhaft laufenden Tablet, keine Anmeldung erforderlich. Andere Plugins senden ihre Signatur-Anforderungen über die PHP-API an diese Seite.', 'fsnw-signature-kiosk' ); ?>
	</p>

	<h2><?php esc_html_e( 'Corporate Design', 'fsnw-signature-kiosk' ); ?></h2>
	<p><?php esc_html_e( 'Firmenlogo und Hintergrundbild für den Kiosk-Wartebildschirm. Ohne Upload wird eine Ersatz-Wortmarke mit Verlaufshintergrund angezeigt.', 'fsnw-signature-kiosk' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fsnw_signature_kiosk_save_branding' ); ?>
		<input type="hidden" name="action" value="fsnw_signature_kiosk_save_branding">

		<table class="form-table">
			<tr>
				<th><label for="fsnw-logo-id"><?php esc_html_e( 'Firmenlogo', 'fsnw-signature-kiosk' ); ?></label></th>
				<td>
					<div class="fsnw-logo-preview fsnw-image-preview">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
						<?php endif; ?>
					</div>
					<input type="hidden" id="fsnw-logo-id" name="logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
					<button type="button" class="button fsnw-select-image" data-target-input="fsnw-logo-id" data-target-preview=".fsnw-logo-preview"><?php esc_html_e( 'Logo auswählen', 'fsnw-signature-kiosk' ); ?></button>
					<button type="button" class="button fsnw-remove-image" data-target-input="fsnw-logo-id" data-target-preview=".fsnw-logo-preview"><?php esc_html_e( 'Entfernen', 'fsnw-signature-kiosk' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><label for="fsnw-background-id"><?php esc_html_e( 'Hintergrundbild', 'fsnw-signature-kiosk' ); ?></label></th>
				<td>
					<div class="fsnw-background-preview fsnw-image-preview">
						<?php if ( $background_url ) : ?>
							<img src="<?php echo esc_url( $background_url ); ?>" alt="">
						<?php endif; ?>
					</div>
					<input type="hidden" id="fsnw-background-id" name="background_id" value="<?php echo esc_attr( $background_id ); ?>">
					<button type="button" class="button fsnw-select-image" data-target-input="fsnw-background-id" data-target-preview=".fsnw-background-preview"><?php esc_html_e( 'Hintergrund auswählen', 'fsnw-signature-kiosk' ); ?></button>
					<button type="button" class="button fsnw-remove-image" data-target-input="fsnw-background-id" data-target-preview=".fsnw-background-preview"><?php esc_html_e( 'Entfernen', 'fsnw-signature-kiosk' ); ?></button>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Speichern', 'fsnw-signature-kiosk' ) ); ?>
	</form>
</div>
