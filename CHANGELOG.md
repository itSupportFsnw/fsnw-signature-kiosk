# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [0.3.0] - 2026-07-13

Added

- Kiosk-Frontend: Shortcode `[wp_fsnw_signature_kiosk]` mit Vollbild-Template
  (Ruhebildschirm mit großem Logo + Uhr, generische Anforderungs-Karte mit Titel,
  Empfänger, Positionsliste, optionalem Zeitraum und Zusatzzeilen, Erfolgsbildschirm),
  portiert aus wp-fsnw-car-rent inkl. aller Robustheits-Mechanismen (2s-Polling,
  8s-Timeout, Offline-Diagnose per Connectivity-Check, 30-min-Idle-Reload,
  Verbindungs-Debug unten rechts).
- Einstellungsseite unter Einstellungen → Signatur-Kiosk: Firmenlogo und
  Hintergrundbild per Media-Uploader (eigene Options, unabhängig von Car-Rent).
- Design-Basis (`tokens.css`/`base.css` Corporate-Design) und signature_pad 4.1.7
  übernommen; `kiosk.css` als generalisierte Fassung von frontend-signature.css.
- Übergangsweise lädt der Enqueue-Check auch Seiten mit dem Legacy-Shortcode
  `wp_fsnw_car_signature`, damit die bestehende Tablet-Seite nach der Umstellung
  von wp-fsnw-car-rent ohne Seitenbearbeitung weiterläuft.

## [0.2.0] - 2026-07-13

Added

- REST-Endpunkte im Namespace `fsnw-signature-kiosk/v1`:
  - `GET /kiosk/pending` (anonym, 120/min pro IP, no-cache): älteste offene Anforderung
    als `{pending: null|{id, title, recipient_name, items[], period, meta_lines[]}}`.
  - `POST /kiosk/signatures` (anonym, 20/min pro IP): `{request_id, signature}` schließt
    eine offene Anforderung ab und feuert die Abschluss-Hooks.
  - `GET /signatures/{id}/image` (Standard: `manage_options`; erweiterbar über den Filter
    `fsnw_signature_kiosk_can_view_image`): streamt das Unterschrift-PNG.
- `RateLimiter` (Fixed-Window per Transient, inkl. reset_at-Fix aus wp-fsnw-car-rent
  v1.7.4) und abstrakter `RestController` portiert.

## [0.1.0] - 2026-07-13

Added

- Plugin-Grundgerüst: Bootstrap, Aktivierung/Deaktivierung, Uninstall, Composer-Classmap,
  WPCS-Konfiguration (extrahiert aus dem Kiosk-Subsystem von wp-fsnw-car-rent).
- Datenbanktabellen `fsnw_sig_requests` (Signatur-Anforderungs-Queue mit source/reference/
  title/recipient_name/items/period/meta_lines/status) und `fsnw_sig_signatures`
  (PNG-Dateiverweise), angelegt per dbDelta.
- Geschütztes Upload-Verzeichnis `uploads/fsnw-signature-kiosk/signatures/`
  (.htaccess-Deny + index.php), Auslieferung später nur über REST.
- Öffentliche PHP-API `\FsnwSignatureKiosk\Includes\Api`: `create_request()` (idempotent
  je source+reference), `cancel_request()`, `cancel_by_reference()`, `get_request()`,
  `get_signature()`, `get_signature_file_path()`.
- `RequestService` (Queue-Lebenszyklus pending → completed/cancelled, Abschluss-Hooks
  `fsnw_signature_completed` und `fsnw_signature_completed_{source}`) und
  `SignatureService` (Data-URL-Validierung ≤2 MB, PNG-Ablage; Port aus wp-fsnw-car-rent).
