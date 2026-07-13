# FSNW Signature Kiosk

Generischer Tablet-Signatur-Kiosk für WordPress. Andere Plugins senden Signatur-Anforderungen
(Ausgabe von Fahrzeugen, Geräten, Schlüsseln, …), Empfänger bestätigen per Unterschrift auf
einem dauerhaft laufenden Tablet.

## Shortcode

`[wp_fsnw_signature_kiosk]` — Vollbild-Kiosk-Modus, keine Anmeldung erforderlich. Ruhebildschirm
mit Firmenlogo und Uhr; sobald eine Anforderung eintrifft, erscheint die Unterschrift-Karte.

## PHP-API für Produzenten-Plugins

```php
use FsnwSignatureKiosk\Includes\Api;

if ( class_exists( Api::class ) ) {
    $request_id = Api::create_request( array(
        'source'         => 'mein-plugin',
        'reference'      => (string) $meine_id,
        'title'          => 'Geräteausgabe',
        'recipient_name' => 'Max Mustermann',
        'items'          => array( 'ThinkPad X1 (INV-0042)', 'Netzteil' ),
        'period_start'   => '2026-07-13 08:00:00', // optional
        'period_end'     => '2026-07-20 17:00:00', // optional
        'meta_lines'     => array( 'Standort: Verwaltung' ), // optional
    ) );
}

// Abbruch (z. B. Ausgabe zurückgezogen):
Api::cancel_by_reference( 'mein-plugin', (string) $meine_id );

// Abschluss empfangen:
add_action( 'fsnw_signature_completed', function ( $request_id, $signature_id, $source, $reference ) {
    if ( 'mein-plugin' !== $source ) {
        return;
    }
    // $signature_id speichern; PNG-Pfad: Api::get_signature_file_path( $signature_id )
}, 10, 4 );

// Bild-Abruf für eigene Rollen freischalten:
add_filter( 'fsnw_signature_kiosk_can_view_image', function ( $allowed, $signature_id, $source ) {
    if ( 'mein-plugin' === $source ) {
        return current_user_can( 'meine_capability' );
    }
    return $allowed;
}, 10, 3 );
```

`create_request()` ist idempotent je `source`+`reference`: eine noch offene Anforderung mit
derselben Referenz wird zuvor storniert.

## REST-Endpunkte (`fsnw-signature-kiosk/v1`)

| Route | Zugriff | Zweck |
| --- | --- | --- |
| `GET /kiosk/pending` | anonym, 120/min pro IP | Kiosk-Polling (älteste offene Anforderung) |
| `POST /kiosk/signatures` | anonym, 20/min pro IP | Unterschrift abgeben |
| `GET /signatures/{id}/image` | `manage_options` bzw. Filter | Unterschrift-PNG abrufen |

Die anonymen Endpunkte sind bewusst ohne Nonce (Kiosk-Tablet ohne Login) und stattdessen
per Fixed-Window-Rate-Limiter pro Client-IP gehärtet.

## Sicherheit der Ablage

Unterschriften liegen als PNG in `uploads/fsnw-signature-kiosk/signatures/` — geschützt per
`.htaccess` (Deny from all), Auslieferung ausschließlich über den berechtigten REST-Endpoint.
Beim Löschen des Plugins bleiben Anforderungen und Unterschriften als Nachweise erhalten.
