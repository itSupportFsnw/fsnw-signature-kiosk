# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

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
