# Contributing

Thanks for considering a contribution! This is a small, dependency-light app —
the bar for changes is "keeps it simple and honest".

## Reporting bugs and ideas

Use the [issue templates](https://github.com/Grizaster/simplefulltextsearch-nc/issues/new/choose).
For bugs, the version numbers (app, Nextcloud, PHP) and a log excerpt do more
than any amount of prose. Security issues go through the
[security policy](SECURITY.md), not public issues.

## Development setup

1. A working Nextcloud development install (any recent release).
2. Clone this repo into `apps/` and enable the app
   (`php occ app:enable simplefulltextsearch`).
3. Run the test suite from the repo root: `phpunit`.

## Pull requests

- One topic per PR — bugfix and refactor are two PRs.
- Add/extend tests when behavior changes; the suite must stay green.
- Update `CHANGELOG.md` (and `appinfo/info.xml` on releases).
- **Sign off your commits** (`git commit -s`) — a `Signed-off-by:` line is
  required (Developer Certificate of Origin, same as for the Nextcloud App
  Store).
- Keep the code style of the surrounding code (PSR-12-ish, no style-only
  churn in the same PR as logic changes).
- PRs may include a note if AI tools were used — the project is open about it
  (see the README) and the Nextcloud App Store asks for disclosure anyway.

## Releases (maintainers)

Version bumps touch `appinfo/info.xml`, `CHANGELOG.md`, and the tag/release
assets on GitHub. App Store releases additionally need a signed certificate
request upstream — see the
[Nextcloud app developer docs](https://nextcloudappstore.readthedocs.io/en/latest/developer.html).
