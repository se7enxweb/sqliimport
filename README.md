# 7x SQLI Import

[![PHP](https://img.shields.io/badge/PHP-8.0%E2%80%938.5-8892BF.png?logo=php&logoColor=white)](https://php.net)
[![Exponential](https://img.shields.io/badge/Exponential-6.0.14%2B-orange.png)](https://github.com/se7enxweb/exponential)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.png)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

**7x SQLI Import** brings content from elsewhere into Exponential: a CSV export, an XML file, an RSS or Atom feed, a
JSON API dump, once (a migration) or on a schedule (a nightly sync). It is release 2 of `se7enxweb/sqliimport`, the
successor of SQLI Import 1.x, and keeps every 1.x handler working untouched.

> **Status: 2.0.0-alpha.** The engine, the command line, the dashboard and the run pages are here. The import editor
> of the administration (source upload, mapping grid, preview, schedules) comes with 2.0.0-beta.1, and 2.0.0 follows
> with the full manual in English and German. See [CHANGELOG.md](CHANGELOG.md).

## What it does

- **Sources:** CSV/TSV (delimiter and encoding detected), XML (a record element, XSD validation), RSS 2.0 and Atom
  (one set of fields for both), JSON and NDJSON. A source is an uploaded file, an `http(s)` address (private
  networks refused unless allowed) or a file on the server inside an allowed directory.
- **No-code mapping:** source fields to the attributes of a content class, each with a chain of named transforms
  (trim, date, number, map, split, rich text from HTML, image or file from a URL, lookup of related objects ...),
  matched by a remote ID pattern or by an attribute's value, placed below a parent node or below the object a record
  names. A new version is published only when a field really changed.
- **Handlers:** the 2.x handler interface for imports that need code, and every SQLI Import 1.x handler
  (`ISQLIImportHandler`) through a compatibility adapter.
- **Runs on the job queue** (`expqueue`): one run per import at a time, different imports in parallel, chunks of 200
  records with checkpoints, memory reset between chunks, cancel at any time, retry of the failed rows only, dry runs
  that publish nothing and show what would change.
- **Every run is traceable:** a row per record (created, updated, unchanged, skipped, failed, with the object and its
  versions before and after) and a log per run with the record each line is about.
- **Schedules:** once, every N minutes, hourly, daily, weekly, monthly or a cron expression, in a time zone, across
  daylight saving time; one cron part every minute queues what is due.
- **Administration:** the **Imports** tab (admin4, admin4l and the classic designs) with a dashboard, run pages,
  context help, light and dark mode, no inline CSS or JavaScript.
- **Command line:** `./console ext:sqliimport:install-schema|list|run|status|cancel|retry|migrate-1x|tick|purge`.

## Requirements

- Exponential 6.0.14 or later, PHP 8.0 to 8.5 (`json`, `mbstring`, `dom`, `xmlreader`).
- The extensions **expqueue** (the job queue, with its cron part or systemd worker) and **expui**.
- MySQL/MariaDB, PostgreSQL, SQLite or Oracle.

## Installation

```bash
composer require se7enxweb/sqliimport:^2.0@alpha
```

1. Activate `sqliimport` (and `expqueue`, `expui`) in `settings/override/site.ini.append.php` `[ExtensionSettings]
   ActiveExtensions[]`, or for the administration siteaccesses only in `ActiveAccessExtensions[]`.
2. Regenerate the autoloads: `php bin/php/ezpgenerateautoloads.php -e`.
3. Create the tables (idempotent, also the upgrade from 1.x): `./console ext:sqliimport:install-schema`.
4. Run the cron parts every minute: `php runcronjobs.php -q expqueue` and `php runcronjobs.php -q sqliimport`.
5. Clear the caches. A siteaccess that resets `[TopAdminMenu] Tabs[]` in its own `menu.ini.append.php` must list
   `Tabs[]=sqliimport` itself, or the Imports tab does not show there.

Upgrading from 1.x: install 2.x over it, run `install-schema`, then `./console ext:sqliimport:migrate-1x` (it reports
first; add `--apply` to migrate, `--roles` to give the 1.x roles the 2.x policies). See
[doc/migration-1x.md](doc/migration-1x.md).

## Quick start

```bash
./console ext:sqliimport:list                         # the imports
./console ext:sqliimport:run my_import --dry-run --wait   # what would change, nothing published
./console ext:sqliimport:run my_import                 # queue a run (the job queue's workers do it)
./console ext:sqliimport:status 12                     # a run with its log
```

In the administration: **Imports** tab, **Run now**, then follow the run on its page.

## Documentation

The manual is in [doc/](doc/index.md) (German: [doc/ger-de/](doc/ger-de/index.md)) and, in an installation, in the
administration's help center under Extensions. The manual of the 1.x API, which still applies, is
[doc/content-api-and-handlers-1x.md](doc/content-api-and-handlers-1x.md).

## Development

```bash
phpunit                     # the unit tests: no database, PHP 8.0 (PHPUnit 9.6) to 8.5
phpstan analyse             # static analysis of the 2.x code (phpstan.neon)
phpcs                       # code style of the 2.x code (phpcs.xml.dist)
```

The integration tests run against a throwaway installation with the job queue (see doc/troubleshooting.md). GitHub
Actions runs the unit tests, PHPStan and phpcs on PHP 8.0 to 8.5 for every push.

## License

GNU General Public License v2.0. See [doc/LICENSE](doc/LICENSE).

## Credits

SQLI Import was written by Jérôme Vieilledent at SQLi Agency in 2010, with Oracle support by Simone Tellini and
contributions from its community. 7x maintains it for Exponential since 2024 and wrote release 2.
