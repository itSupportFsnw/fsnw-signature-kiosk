<?php
/**
 * Uninstall-Routine.
 *
 * Wird ausschließlich beim expliziten Löschen des Plugins über den WordPress-Adminbereich
 * ausgeführt. Löscht bewusst KEINE Signatur-Anforderungen oder Unterschriften, da diese
 * als Nachweis (Ausgabe-Belege) dauerhaft aufbewahrt werden müssen. Es werden nur
 * Plugin-Optionen entfernt.
 *
 * @package FsnwSignatureKiosk
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fsnw_signature_kiosk_db_version' );
delete_option( 'fsnw_signature_kiosk_logo_id' );
delete_option( 'fsnw_signature_kiosk_background_id' );
