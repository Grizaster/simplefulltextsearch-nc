# Changelog

Alle relevanten Änderungen an der Nextcloud-App **SimpleFullTextFileSearch** werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) und dieses Projekt befolgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-11

### Sicherheits-Härtung (Release-Welle für den Nextcloud App Store)

#### Behoben
- **Speicher-DoS über Konsolen-Ausgabe (mittel):** Die Extractor-CLI-Aufrufe (pdftotext, ocrmypdf, unzip) sammelten ihre Ausgabe unbegrenzt im PHP-Speicher — eine Zip-Bombe oder eine PDF mit Gigabytes Text konnte den Index-Lauf beliebig aufblähen. Die Ausgabe wird jetzt über eine Datei mit 2-MB-Deckel eingesammelt; auch die Datei-Reads (Klartext, HTML, OCR-Sidecar) sind gecappt (`lib/Service/ExtractorService.php`).
- **Fail-Open in der Admin-Belt-Prüfung (mittel):** Die zweite Admin-Prüfung (Defense in Depth neben Nextclouds Core-Middleware) hing an optional per DI injizierbaren Feldern — ohne gesetzte `IUserSession`/`IGroupManager` wäre sie still entfallen. Beide Abhängigkeiten sind jetzt zwingend (fail-closed), die Prüfung läuft immer (`lib/Controller/SettingsController.php`).
- **Irreführender Deko-Attribute entfernt:** Die leere `AdminRequired`-Attributklasse hatte bei Nextcloud keine Wirkung (echte Absicherung = Core-Middleware: Controller-Methoden sind ohne `#[NoAdminRequired]` admin-only). Entfernt; der Vertrag ist jetzt in Tests festgezogen (keine Relaxing-Attribute, zwingende DI-Deps) (`lib/Controller/AdminRequired.php` gelöscht, `tests/SettingsControllerTest.php`).
- **XSS-Defense-in-Depth im Snippet:** Dokumenttext kann Nutzer-Markup enthalten; Snippets werden zusätzlich per `strip_tags` bereinigt, bevor sie an Konsumierende (Apps, Drittanbieter) gehen (`lib/Search/Provider.php`).
- **Pfad-Traversierung in Suchbereichen abgewiesen:** `..`-Segmente werden in Ordnerpfaden nicht mehr durchgereicht (Abbildung auf Root → Eintrag ungültig); Doppelslashes und `.`-Segmente werden normalisiert (`lib/Service/IndexerService.php`).
- **DB-Texttransport begrenzt:** Die Suche holt nur noch die ersten 2.000 Zeichen Text je Treffer (Snippet-Bedarf) statt ganzer Dokumente — kein Speicher-Peak mehr bei 500 Kandidaten × Volltext (`lib/Db/IndexMapper.php`).
- **Keine lokalen Pfade mehr im Changelog:** Die bisherigen `file:///…`-Links sind relative Links geworden (keine Verrät von Entwicklungspfaden).

#### Behoben (zweites, unabhängiges Audit)
- **Platten-DoS bei Dekomprimierung (hoch):** Die Ausgabe-Begrenzung deckte nur den Speicher — die Shell-Redirection schrieb die dekomprimierte Zip-Bombe weiter komplett nach `/tmp`. Die Konsolen-Ausgabe läuft jetzt durch `head -c` (Deckel auch auf der Platte, SIGPIPE beendet den Schreibprozess); `pipefail` reicht den echten Exit-Code durch (`lib/Service/ExtractorService.php`).
- **Zyklus-/Tiefenschutz bei der Ordnerrekursion (mittel):** `walk()` begrenzt die Tiefe (30) und merkt besuchte Ordner — gemountete/symlinkte Strukturen treiben den Index-Lauf nicht mehr endlos (`lib/Service/IndexerService.php`).
- **Kein OOM bei großen Beständen (mittel):** `deleteOrphanedScopes()` arbeitet mit Cursor-Pagination (1.000er Blöcke) statt die ganze Tabelle in den PHP-Speicher zu holen (`lib/Db/IndexMapper.php`).
- **Kein Gateway-Timeout beim Reindex-Knopf (mittel):** „Jetzt neu indizieren" plant einen einmaligen Hintergrundjob (`lib/BackgroundJob/ReindexJob.php`) statt synchron im HTTP-Request zu indizieren; die Antwort heißt jetzt `scheduled` (`lib/Controller/SettingsController.php`, UI).
- **Suche crasht nicht mehr (mittel):** Fehlender/defekter UserFolder (z. B. frische LDAP-/Gastnutzer) liefert leere Treffer statt HTTP 500 (`lib/Search/Provider.php`).
- **Ergebnis-Deduplizierung (info):** Dieselbe Datei aus mehreren Suchbereichen erscheint nur noch einmal (`lib/Search/Provider.php`).
- **Store-Konformität (niedrig):** Interne `\OC::$server`-Nutzung in der Migration durch das bewährte `\OCP\Server`-Fallback-Muster ersetzt (Migrationsschritte erhalten kein Constructor-DI — `new $class()`); Lizenz-Tag auf `AGPL-3.0-only` aktualisiert (Alt-Wert `agpl` ist im Schema als veraltet markiert).
- **Stiller Textverlust bei Nicht-UTF8 verhindert (niedrig):** Windows-1252/ISO-8859-1-Dateien werden nach UTF-8 konvertiert statt durch die PCRE-`/u`-Regel still auf `''` zu kollabieren (`lib/Service/ExtractorService.php`).
- **Kein Log-Spam ohne `ocrmypdf` (niedrig):** Tool-Verfügbarkeit wird einmalig geprüft; fehlt es, überspringt der OCR-Zweig mit einer Info-Meldung statt Warnung je Scan (`lib/Service/ExtractorService.php`).

### Geändert
- **Komplett übersetzte Oberfläche (en/de):** Alle Formular-Texte laufen über l10n (Quellsprache Englisch); die JS-Statusmeldungen werden serverseitig übersetzt mitgegeben (`lib/Settings/AdminSettings.php`, `templates/settings/admin.php`, `js/admin.js`, `l10n/de.json`).
- **Größenlimit dedupliziert:** Der 25-MB-Index-Deckel ist eine gemeinsame Konstante (`ExtractorService::MAX_BYTES`).
- **Testreste entfernt:** `tests/eldotest.php`/`tests/eldoprobe.php` (Entwicklungs-Helfer mit fest verdrahteten Pfaden) sind nicht mehr Teil des Repositories.

---

## [1.1.2] - 2026-11-09

### Geändert
- Metadata-Rename (User-Vorgabe): Autor = pcBensn (vorher „NikolAI"). Keine funktionalen Änderungen.

---

## [1.1.1] - 2026-11-09

### Behoben
- **Migration 2/3-Kollision behoben (Rename-Migration auf bestehenden Systemen):**
  - **Ursache:** In Version 1.1.0 wurde die Rename-Logik (Kopieren von `oc_dossier_index` nach `simplefulltextsearch_index` sowie Migration der App-Konfigurationen von `dossier` nach `simplefulltextsearch`) fälschlicherweise in die bereits existierende `Version2ScopeMigration.php` integriert. Da `2ScopeMigration` auf bereits installierten Systemen (1.0.0) in der Nextcloud-Tabelle `oc_migrations` bereits als ausgeführt markiert war, wurde die erweiterte Migration bei Updates nie ausgeführt.
  - **Wirkung & Fix:** Die Rename-Logik wurde aus `Version2ScopeMigration.php` vollständig entfernt (wieder auf den reinen Scope-Index-Umbau zurückgesetzt) und in eine neue Schema-Migration `lib/Migration/Version3RenameMigration.php` überführt. Nextcloud führt `Version3RenameMigration` nun beim App-Update auf 1.1.1 garantiert und idempotent aus, ohne dass Altdaten gelöscht werden.

---

## [1.1.0] - 2026-11-09

### Behobene Review-Funde & Sicherheits-Härtung (Fixwave)

#### P1 — Daten-Leck-Schutz & Privatsphäre
- **Prüfung auf Lese-Berechtigung (`isReadable()`):** Vor Aufnahme in die Trefferliste wird für jeden Treffer `$node->isReadable()` geprüft. Dokumente ohne Leserechte (z. B. Drop-Only/Upload-Only Freigaben) werden vollständig herausgefiltert; Textauszüge (Snippets), Dateinamen und Vorschaubilder (Previews) leaken nicht mehr an Unberechtigte ([`lib/Search/Provider.php`](lib/Search/Provider.php)).
- **Behebung der Pfad-Leckage interner Ordnerstrukturen:** Dateipfade werden nun ausschließlich aus der Sicht des suchenden Benutzers berechnet (`$userFolder->getRelativePath($node->getPath())`). Der interne Ordnerpfad des Scope-Eigentümers (`$row['path']`) wird weder im Pfad-Attribut noch im Text-Snippet noch im Deep-Link-Parameter `dir` an Dritte herausgegeben ([`lib/Search/Provider.php`](lib/Search/Provider.php), [`lib/Service/IndexerService.php`](lib/Service/IndexerService.php)).
- **Such-Aushungerung (Search Starvation) behoben:** Das DB-Query limitiert nicht mehr vor der Rechteprüfung. Stattdessen werden Trefferkandidaten paginiert nachgeladen (bis zu 500 Zeilen) und erst nach erfolgreicher Rechteprüfung auf das gewünschte Limit begrenzt. Berechtigte Dokumente in nachgelagerten Scopes werden nicht mehr von unberechtigten Dokumenten verdrängt ([`lib/Search/Provider.php`](lib/Search/Provider.php), [`lib/Db/IndexMapper.php`](lib/Db/IndexMapper.php)).

#### P1 — Migration & Datenkontinuität
- **Rename-Migration (`dossier` → `simplefulltextsearch`):** In `Version2ScopeMigration::postSchemaChange` werden vorhandene Daten aus der Alt-Tabelle `oc_dossier_index` idempotent in `simplefulltextsearch_index` übernommen, ohne dass Daten verloren gehen oder die Alt-Tabelle gelöscht wird. Gleichzeitig werden Konfigurationseinträge (`scopes`, `scope_owner`, `scope_folder`, `provider_id`) von `dossier` nach `simplefulltextsearch` migriert ([`lib/Migration/Version2ScopeMigration.php`](lib/Migration/Version2ScopeMigration.php)).

#### P1 — Robustheit & Absicherung
- **Inode-Leak im Temp-Verzeichnis behoben:** In `ExtractorService::fromPdf()` werden temporäre Dateien ohne doppelte Dateiendung erzeugt und in einem `finally`-Block garantiert gelöscht. Auch bei Fehlern oder Scans verbleiben keine verwaisten `elds_*`-Dateien in `sys_get_temp_dir()` ([`lib/Service/ExtractorService.php`](lib/Service/ExtractorService.php)).
- **Admin-Absicherung gegen OPcache-Stripping:** `SettingsController::save()` und `SettingsController::reindex()` wurden mit dem echten PHP-8-Attribut `#[AdminRequired]` ausgestattet (DocBlock `@AdminRequired` für Abwärtskompatibilität beibehalten). Zusätzlich verhindert eine explizite Controller-Prüfung (`IGroupManager::isAdmin`) unberechtigte Zugriffe, selbst wenn PHP mit `opcache.save_comments=0` betrieben wird ([`lib/Controller/SettingsController.php`](lib/Controller/SettingsController.php), [`lib/Controller/AdminRequired.php`](lib/Controller/AdminRequired.php)).

#### P2 — Wartbarkeit, Fehlerprotokollierung & Kompatibilität
- **Bereinigung verwaister Index-Einträge:** Entfernen eines Scopes aus den Einstellungen führt bei nachfolgenden Index-Läufen zur automatischen Bereinigung nicht mehr konfigurierter Scopes via `IndexMapper::deleteOrphanedScopes()` ([`lib/Db/IndexMapper.php`](lib/Db/IndexMapper.php), [`lib/Service/IndexerService.php`](lib/Service/IndexerService.php)).
- **Protokollierung stiller Fehler:** Nicht-lokale Dateipfade (z. B. S3 / Object Storage ohne lokalen Cache) sowie leere Extraktionstexte werden mit Dateipfad und Grund per `logger->warning()` geloggt statt lautlos ignoriert zu werden. CLI-Fehler in `ExtractorService::sh()` erfassen Exit-Codes und `stderr` und warnen im Nextcloud-Log ([`lib/Service/IndexerService.php`](lib/Service/IndexerService.php), [`lib/Service/ExtractorService.php`](lib/Service/ExtractorService.php)).
- **Datenbank-Deklaration in `info.xml`:** Tag `<database>mysql</database>` in den Abhängigkeiten ergänzt (die App nutzt MySQL/MariaDB FULLTEXT `MATCH...AGAINST`). Die Versionskompatibilität `min-version="33" max-version="35"` bleibt unverändert ([`appinfo/info.xml`](appinfo/info.xml)).
- **Automatisierte PHPUnit-Testsuite:** Vollständige Testsuite aufgesetzt (`phpunit.xml`, `tests/bootstrap.php`), inklusive Tests für Rechtefilter (`isReadable`), Pfad-Neutralisierung, Search-Starvation-Vermeidung, Temp-Cleanup, Rename-Migration, Settings-ACL und Scope-Cleanup. Alle 23 PHP-Dateien mit `php -l` geprüft.

---

## [1.0.0] - 2026-09-30

- Initiales Release von SimpleFullTextFileSearch (Multi-Scope, MariaDB FULLTEXT, OCR-Pipeline).