# Integrations-Anleitung: Signatur-Anforderungen aus eigenen Plugins senden

Diese Anleitung beschreibt Schritt für Schritt, wie ein WordPress-Plugin den
FSNW Signature Kiosk nutzt, um sich Ausgaben (Fahrzeuge, Geräte, Schlüssel, …)
per Unterschrift auf dem Tablet bestätigen zu lassen.

Referenz-Implementierung: das Plugin **wp-fsnw-car-rent** (ab v1.10.0), Klasse
`includes/Integrations/class-signature-kiosk-integration.php` und
`DispatchService::issue_key()/abort_handover()/handle_kiosk_completion()`.

## Konzept in einem Absatz

Dein Plugin ist **Produzent**: Es legt eine Signatur-Anforderung in die
Kiosk-Queue (`Api::create_request()`) und identifiziert sie über ein Paar aus
`source` (dein Plugin-Slug) und `reference` (deine interne ID, z. B. eine
Buchungs- oder Ausgabe-Nummer). Das Kiosk-Tablet zeigt die älteste offene
Anforderung an. Unterschreibt die Person, speichert der Kiosk das PNG und
feuert den Hook `fsnw_signature_completed` — darauf reagierst du und schließt
deinen eigenen Prozess ab. Ziehst du die Ausgabe zurück, stornierst du die
Anforderung (`Api::cancel_by_reference()`), und das Tablet räumt sich binnen
~2 Sekunden selbst auf.

## Voraussetzungen

- Plugin **FSNW Signature Kiosk** ist installiert und aktiv.
- Eine WordPress-Seite mit dem Shortcode `[wp_fsnw_signature_kiosk]` läuft
  dauerhaft im Browser des Tablets (eine Kiosk-Seite bedient alle Produzenten).
- Verfügbarkeit prüfst du in PHP per
  `class_exists( '\FsnwSignatureKiosk\Includes\Api' )`.

## 1. Anforderung erstellen

Beim Auslösen der Ausgabe in deinem Plugin (Button, Statuswechsel, …):

```php
use FsnwSignatureKiosk\Includes\Api;

if ( ! class_exists( Api::class ) ) {
    // Empfehlung: Vorgang mit klarer Fehlermeldung abbrechen (siehe Abschnitt 5).
    throw new \InvalidArgumentException( 'Das Plugin "FSNW Signature Kiosk" ist nicht aktiv.' );
}

$request_id = Api::create_request( array(
    // Pflichtfelder:
    'source'         => 'mein-plugin',              // dein eindeutiger, stabiler Slug (sanitize_key-kompatibel)
    'reference'      => (string) $ausgabe_id,        // DEINE interne ID; kommt beim Abschluss unverändert zurück
    'title'          => __( 'Geräteausgabe', 'mein-plugin' ),   // Kontextzeile auf dem Tablet
    'recipient_name' => $empfaenger_name,            // wer bestätigt (groß angezeigt)
    'items'          => array(                       // was ausgegeben wird, min. 1 Position
        'ThinkPad X1 Carbon (INV-0042)',
        'Netzteil 65W',
    ),
    // Optional:
    'period_start'   => '2026-07-13 08:00:00',       // Format exakt "Y-m-d H:i:s", sonst wird der Wert verworfen
    'period_end'     => '2026-07-20 17:00:00',
    'meta_lines'     => array( 'Standort: Verwaltung', 'Ticket #4711' ),
) );
```

Wichtig zu wissen:

- **Idempotenz**: `create_request()` storniert automatisch eine noch offene
  Anforderung mit demselben `source`+`reference`. Doppeltes Klicken auf
  "Ausgeben" erzeugt also keine Duplikate in der Queue.
- **Reihenfolge**: Der Kiosk zeigt immer die **älteste** offene Anforderung
  zuerst (FIFO). Mehrere gleichzeitig offene Anforderungen werden nacheinander
  abgearbeitet.
- Bei ungültigen/fehlenden Pflichtfeldern wirft `create_request()` eine
  `\InvalidArgumentException` — fange sie und brich deinen Vorgang ab, statt
  ihn halbfertig stehen zu lassen.
- Die zurückgegebene `$request_id` musst du nicht speichern — `source` +
  `reference` reichen für Stornierung und Zuordnung. Speichern schadet aber nicht.

## 2. Abschluss empfangen

Registriere einen Listener auf den Abschluss-Hook (am besten in einer eigenen
Integrations-Klasse, die du bei Plugin-Start initialisierst):

```php
add_action( 'fsnw_signature_completed', 'mein_plugin_signatur_abgeschlossen', 10, 4 );

/**
 * @param int    $request_id   Anforderungs-ID im Kiosk-Plugin.
 * @param int    $signature_id Signatur-ID im Kiosk-Plugin (dauerhaft speichern!).
 * @param string $source       Slug des Produzenten.
 * @param string $reference    Deine Referenz aus create_request().
 */
function mein_plugin_signatur_abgeschlossen( $request_id, $signature_id, $source, $reference ): void {
    if ( 'mein-plugin' !== $source ) {
        return; // Anforderungen anderer Plugins ignorieren.
    }

    $ausgabe_id = (int) $reference;

    // WICHTIG: Zustand prüfen statt blind abschließen. Der Hook läuft innerhalb
    // des REST-Requests des Tablets - die Unterschrift ist zu diesem Zeitpunkt
    // bereits gespeichert. Ein unerwarteter/veralteter Abschluss sollte still
    // ignoriert (oder geloggt) werden, niemals eine Exception werfen, sonst
    // bricht die Antwort an das Tablet.
    // → $signature_id in deiner eigenen Tabelle an der Ausgabe speichern,
    //   dann deinen Statuswechsel/Audit-Log/Benachrichtigungen ausführen.
}
```

Alternativ gibt es den source-spezifischen Hook
`fsnw_signature_completed_{source}` (gleiche Argumente), falls du dir den
`$source`-Vergleich sparen willst:

```php
add_action( 'fsnw_signature_completed_mein-plugin', 'mein_plugin_signatur_abgeschlossen', 10, 4 );
```

## 3. Anforderung stornieren (Abbruch)

Wenn die Ausgabe zurückgezogen wird, bevor unterschrieben wurde:

```php
if ( class_exists( Api::class ) ) {
    Api::cancel_by_reference( 'mein-plugin', (string) $ausgabe_id );
}
```

Das Tablet verschwindet von selbst zurück in den Wartebildschirm (Polling alle
2 s). Storniere **immer** beim Abbruch — die Kiosk-Queue ist die einzige Quelle
der Wahrheit dafür, was auf dem Tablet erscheint; eine vergessene Anforderung
bliebe sonst dort stehen.

## 4. Unterschrift-Bild abrufen

Das PNG liegt im geschützten Upload-Verzeichnis des Kiosk-Plugins und wird nur
über dessen REST-Endpoint ausgeliefert:

```
GET /wp-json/fsnw-signature-kiosk/v1/signatures/{signature_id}/image
```

Standardmäßig dürfen das nur Administratoren (`manage_options`). Schalte den
Abruf für die Rollen deines Plugins frei:

```php
add_filter( 'fsnw_signature_kiosk_can_view_image', function ( $allowed, $signature_id, $source ) {
    if ( 'mein-plugin' === $source ) {
        return current_user_can( 'meine_capability' );
    }
    return $allowed; // Fremde Signaturen unangetastet lassen!
}, 10, 3 );
```

Serverseitig (z. B. für PDF-Erzeugung) kommst du per
`Api::get_signature_file_path( $signature_id )` an den absoluten Dateipfad.

## 5. Fehlen des Kiosk-Plugins behandeln

Empfohlenes Muster (harte Abhängigkeit, wie in wp-fsnw-car-rent):

- **Auslöse-Aktion blockieren**: `class_exists`-Guard mit verständlicher
  Fehlermeldung, bevor du deinen eigenen Statuswechsel machst — nie erst den
  Status wechseln und dann feststellen, dass keine Anforderung erzeugt werden kann.
- **Admin-Hinweis** anzeigen, solange das Plugin fehlt:

```php
add_action( 'admin_notices', function () {
    if ( class_exists( '\FsnwSignatureKiosk\Includes\Api' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html__( 'Mein Plugin benötigt das Plugin "FSNW Signature Kiosk" für Ausgabe-Unterschriften.', 'mein-plugin' )
    );
} );
```

## Checkliste für eine neue Integration

1. [ ] Stabilen `source`-Slug festlegen (ändert sich nie wieder).
2. [ ] `create_request()` an der Auslöse-Stelle, mit `class_exists`-Guard davor.
3. [ ] `cancel_by_reference()` an **jeder** Abbruch-Stelle.
4. [ ] Listener auf `fsnw_signature_completed` (Zustand prüfen, `$signature_id`
       speichern, keine Exceptions werfen).
5. [ ] Filter `fsnw_signature_kiosk_can_view_image` für die eigenen Rollen.
6. [ ] Admin-Hinweis bei fehlendem Kiosk-Plugin.
7. [ ] End-to-End-Test: auslösen → Tablet zeigt an (<2 s) → unterschreiben →
       eigener Abschluss läuft → Bild abrufbar; zusätzlich Abbruch-Test
       (Tablet leert sich <2 s) und Doppelklick-Test (keine Duplikate).

## Was du NICHT tun solltest

- Keine eigenen Zugriffe auf die Kiosk-Tabellen (`fsnw_sig_*`) — nur über die
  `Api`-Fassade und die Hooks; die Tabellen sind internes Implementierungsdetail.
- Keine PNGs aus dem Upload-Verzeichnis kopieren/verlinken — das Verzeichnis
  ist bewusst per `.htaccess` gesperrt, Auslieferung nur über den REST-Endpoint.
- Den Hook-Listener nicht mit langlaufenden Aktionen blockieren (er läuft im
  REST-Request des Tablets); Aufwendiges (PDF-Erzeugung, E-Mails) besser
  asynchron nachlagern (z. B. WP-Cron-Einzelevent).
