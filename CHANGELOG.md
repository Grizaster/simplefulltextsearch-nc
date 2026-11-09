# Changelog

Alle relevanten Änderungen an der Nextcloud-App **SimpleFullTextFileSearch** werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) und dieses Projekt befolgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- **Prüfung auf Lese-Berechtigung (`isReadable()`):** Vor Aufnahme in die Trefferliste wird für jeden Treffer `$node->isReadable()` geprüft. Dokumente ohne Leserechte (z. B. Drop-Only/Upload-Only Freigaben) werden vollständig herausgefiltert; Textauszüge (Snippets), Dateinamen und Vorschaubilder (Previews) leaken nicht mehr an Unberechtigte ([`lib/Search/Provider.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Search/Provider.php)).
- **Behebung der Pfad-Leckage interner Ordnerstrukturen:** Dateipfade werden nun ausschließlich aus der Sicht des suchenden Benutzers berechnet (`$userFolder->getRelativePath($node->getPath())`). Der interne Ordnerpfad des Scope-Eigentümers (`$row['path']`) wird weder im Pfad-Attribut noch im Text-Snippet noch im Deep-Link-Parameter `dir` an Dritte herausgegeben ([`lib/Search/Provider.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Search/Provider.php), [`lib/Service/IndexerService.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Service/IndexerService.php)).
- **Such-Aushungerung (Search Starvation) behoben:** Das DB-Query limitiert nicht mehr vor der Rechteprüfung. Stattdessen werden Trefferkandidaten paginiert nachgeladen (bis zu 500 Zeilen) und erst nach erfolgreicher Rechteprüfung auf das gewünschte Limit begrenzt. Berechtigte Dokumente in nachgelagerten Scopes werden nicht mehr von unberechtigten Dokumenten verdrängt ([`lib/Search/Provider.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Search/Provider.php), [`lib/Db/IndexMapper.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Db/IndexMapper.php)).

#### P1 — Migration & Datenkontinuität
- **Rename-Migration (`dossier` → `simplefulltextsearch`):** In `Version2ScopeMigration::postSchemaChange` werden vorhandene Daten aus der Alt-Tabelle `oc_dossier_index` idempotent in `simplefulltextsearch_index` übernommen, ohne dass Daten verloren gehen oder die Alt-Tabelle gelöscht wird. Gleichzeitig werden Konfigurationseinträge (`scopes`, `scope_owner`, `scope_folder`, `provider_id`) von `dossier` nach `simplefulltextsearch` migriert ([`lib/Migration/Version2ScopeMigration.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Migration/Version2ScopeMigration.php)).

#### P1 — Robustheit & Absicherung
- **Inode-Leak im Temp-Verzeichnis behoben:** In `ExtractorService::fromPdf()` werden temporäre Dateien ohne doppelte Dateiendung erzeugt und in einem `finally`-Block garantiert gelöscht. Auch bei Fehlern oder Scans verbleiben keine verwaisten `elds_*`-Dateien in `sys_get_temp_dir()` ([`lib/Service/ExtractorService.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Service/ExtractorService.php)).
- **Admin-Absicherung gegen OPcache-Stripping:** `SettingsController::save()` und `SettingsController::reindex()` wurden mit dem echten PHP-8-Attribut `#[AdminRequired]` ausgestattet (DocBlock `@AdminRequired` für Abwärtskompatibilität beibehalten). Zusätzlich verhindert eine explizite Controller-Prüfung (`IGroupManager::isAdmin`) unberechtigte Zugriffe, selbst wenn PHP mit `opcache.save_comments=0` betrieben wird ([`lib/Controller/SettingsController.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Controller/SettingsController.php), [`lib/Controller/AdminRequired.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Controller/AdminRequired.php)).

#### P2 — Wartbarkeit, Fehlerprotokollierung & Kompatibilität
- **Bereinigung verwaister Index-Einträge:** Entfernen eines Scopes aus den Einstellungen führt bei nachfolgenden Index-Läufen zur automatischen Bereinigung nicht mehr konfigurierter Scopes via `IndexMapper::deleteOrphanedScopes()` ([`lib/Db/IndexMapper.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Db/IndexMapper.php), [`lib/Service/IndexerService.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Service/IndexerService.php)).
- **Protokollierung stiller Fehler:** Nicht-lokale Dateipfade (z. B. S3 / Object Storage ohne lokalen Cache) sowie leere Extraktionstexte werden mit Dateipfad und Grund per `logger->warning()` geloggt statt lautlos ignoriert zu werden. CLI-Fehler in `ExtractorService::sh()` erfassen Exit-Codes und `stderr` und warnen im Nextcloud-Log ([`lib/Service/IndexerService.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Service/IndexerService.php), [`lib/Service/ExtractorService.php`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/lib/Service/ExtractorService.php)).
- **Datenbank-Deklaration in `info.xml`:** Tag `<database>mysql</database>` in den Abhängigkeiten ergänzt (die App nutzt MySQL/MariaDB FULLTEXT `MATCH...AGAINST`). Die Versionskompatibilität `min-version="33" max-version="35"` bleibt unverändert ([`appinfo/info.xml`](file:///home/bensn/workspace/github/simplefulltextsearch-nc/appinfo/info.xml)).
- **Automatisierte PHPUnit-Testsuite:** Vollständige Testsuite aufgesetzt (`phpunit.xml`, `tests/bootstrap.php`), inklusive Tests für Rechtefilter (`isReadable`), Pfad-Neutralisierung, Search-Starvation-Vermeidung, Temp-Cleanup, Rename-Migration, Settings-ACL und Scope-Cleanup. Alle 23 PHP-Dateien mit `php -l` geprüft.

---

## [1.0.0] - 2026-09-30

- Initiales Release von SimpleFullTextFileSearch (Multi-Scope, MariaDB FULLTEXT, OCR-Pipeline).
