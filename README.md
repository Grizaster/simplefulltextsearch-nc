# SimpleFullTextFileSearch

Opinionated, dependency-light full-text search over a folder of documents for
Nextcloud. The index lives in your Nextcloud database — no search server, no
extra services, no AI. Includes OCR for scanned pages.

Results appear in the Nextcloud unified search (web and mobile apps) with a
text snippet from inside the document; tapping opens the file.

## Features

- Indexes one or more **search scopes** (a user + folder pair each) — e.g. three
  folders = three scopes, all results in one group. Scopes may belong to
  different users (results are permission-checked per searching user).
- Text extraction: PDF (`pdftotext`), Office documents (docx/odt), plain text formats
- Scanned pages are OCR'd (`ocrmypdf`, optional) so image-only PDFs become searchable
- Search results with content snippets and deep links to the file
- Quiet background indexing every 30 minutes — only new and changed files are touched
- Everything in the Nextcloud database (MariaDB/MySQL `FULLTEXT` index over
  `name` and `text` — a hard requirement, see below)

## Requirements

- Nextcloud **33 – 35**
- **MariaDB or MySQL with InnoDB `FULLTEXT` support — required.** The index uses
  a real `FULLTEXT` key (this is where the speed comes from); PostgreSQL and
  SQLite are **not** supported.
- CLI tools on the server:
  - `pdftotext` (package `poppler-utils`) — PDF text extraction
  - `unzip` — Office document text extraction
  - `ocrmypdf` + `tesseract` — *optional*, OCR for scanned documents

## Installation

```bash
cd /path/to/nextcloud/apps
git clone <this-repo> simplefulltextsearch      # folder name must be the app id: simplefulltextsearch
sudo -u www-data php occ app:enable simplefulltextsearch
```

Then open **Settings → Administration → Document search** and add one or more
search scopes (user + folder, e.g. `bensn` / `/Documents`). A first full index
runs on demand (or via the button in the settings); the search is usable
immediately afterwards.

## How it works
A background job (`IndexJob`, every 30 minutes via the Nextcloud cron) walks every
configured scope and upserts extracted text into the `simplefulltextsearch_index` table
(plus a MySQL/MariaDB `FULLTEXT` index over `name` and `text`). Deleted files
are pruned on the next run. An incremental run touches only new or modified
files. You can trigger a full reindex at any time in the admin settings.

## Result links

Each result entry links to the canonical Nextcloud file link
`/f/{fileid}?dir=<parent-with-trailing-slash>&scrollto=<filename>&openfile=true`
(plus `fileId`/`path` entry attributes). In the browser this opens the document
**directly in the viewer** — not just its folder. Nextcloud iOS 35.0.0 reads
only the `dir` and `scrollto` query parameters to resolve the file natively
(both are mandatory on *every* entry — one entry without them drops the whole
result section in the app); the URL path and extra parameters are ignored.

## Known issues

**Nextcloud iOS 35.0.0** opens unified-search results natively only for the
provider ids `files` and `fulltextsearch`; everything else falls back to a
webview. For native document opening on iOS 35.0.0 set:

```bash
sudo -u www-data php occ config:app:set simplefulltextsearch provider_id --value=fulltextsearch
```

Do **not** use `files` (collides with the core files provider). After updating
to Nextcloud iOS 36+ you can remove this setting again (36+ opens results of
any provider natively via the `fileId`/`path` entry attributes — the app sets
them regardless of the configured provider id).

## Development

- `tests/eldotest.php` — end-to-end CLI test (index + provider search)
- App id / namespace: `simplefulltextsearch` / `OCA\SimpleFullTextFileSearch`
- The search provider id is configurable (`provider_id`, default
  `simplefulltextsearch`)

## License

AGPL-3.0-only — see [LICENSE](LICENSE).
