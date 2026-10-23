# 7x SQLI Import

[![PHP](https://img.shields.io/badge/PHP-8.0%E2%80%938.5-8892BF.png?logo=php&logoColor=white)](https://php.net)
[![Exponential](https://img.shields.io/badge/Exponential-6.0.14%2B-orange.png)](https://github.com/se7enxweb/exponential)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.png)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

**7x SQLI Import** brings content from elsewhere into Exponential: a spreadsheet, a feed, a website, an API,
another database or another CMS, once (a migration), on a schedule (a nightly sync) or when another system calls
it. Everything is done in the administration, without PHP or the command line. It is release 2 of
`se7enxweb/sqliimport`, the successor of SQLI Import 1.x, and keeps every 1.x handler working untouched.

![The Imports dashboard of the administration: the Run now toolbar, the key figures, and the imports with their last run](doc/images/gs-dashboard-top.png)

> **Status: 2.0 in testing** on the branch `2.0-dev` (last pre-release 2.0.0-alpha.1). See
> [CHANGELOG.md](CHANGELOG.md).

## Contents

- [What it does](#what-it-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Documentation](#documentation)
- [Development](#development)

## What it does

- **More than 60 sources, in a catalogue:** CSV/TSV, XLSX/ODS, JSON/NDJSON, XML, YAML, TOML, OPML, Avro/Arrow,
  media ZIPs with EXIF/IPTC, Markdown folders, WordPress, iCalendar, vCard; RSS/Atom, podcasts, YouTube, Vimeo,
  sitemaps and web pages; REST APIs, Google Sheets, Airtable, Notion, Baserow, Supabase, Firebase, Salesforce,
  HubSpot; SQL (MySQL, PostgreSQL, SQLite, SQL Server, Oracle) and NoSQL databases (MongoDB, Redis, Elasticsearch,
  CouchDB, DynamoDB, Firestore, Cosmos DB, Cassandra, Neo4j, ClickHouse, BigQuery, Snowflake, InfluxDB, DuckDB,
  LDAP); other Exponential sites, Drupal, Joomla, packages; Google Merchant, Shopify, WooCommerce; IMAP mailboxes
  and office documents. Every remote source is read-only, and its secrets are kept by name in the secret store.
- **The import editor:** six steps (source, processing, mapping, preview, schedule, review) with a live preview and
  a dry run before anything is published.
- **No-code mapping:** source fields to the attributes of a content class, each with a chain of named transforms
  (trim, date, number, map, split, rich text from HTML, image or file from a URL, lookup of related objects ...),
  matched by a remote ID pattern or by an attribute's value, placed below a parent node or below the object a record
  names. A new version is published only when a field really changed.
- **Handlers:** the 2.x handler interface for imports that need code, and every SQLI Import 1.x handler
  (`ISQLIImportHandler`) through a compatibility adapter.
- **Runs on the job queue** (`expqueue`): one run per import at a time, different imports in parallel, chunks of 200
  records with checkpoints, memory reset between chunks, live progress, cancel at any time, retry of the failed rows
  only, dry runs that publish nothing and show what would change, undo of a run for 30 days, conflict rules for
  objects that people edited since the last run, notifications by mail and in the header bell.
- **Started by other systems:** a webhook / API trigger per import (signed calls or API keys), optionally with the
  data in the call.
- **Data tables:** an import can fill a module's own table instead of content.
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

In the administration: **Imports, New import**, choose a format, upload the file, map its fields, look at the
preview, then **Save and run** and follow the run on its page:

![The run page of a first import: Completed, 5 created, the five records with their new objects, and the log](doc/images/gs-run-page.png)

The whole first import, step by step with screenshots: [doc/getting-started.md](doc/getting-started.md).

## Documentation

The manual is in [doc/](doc/index.md) (German: [doc/ger-de/](doc/ger-de/index.md)) and, in an installation, in the
administration's help center under Extensions. Every use case has a worked example:

| Topic | Page |
|---|---|
| A first import, end to end | [doc/getting-started.md](doc/getting-started.md) |
| The six-step editor | [doc/editor.md](doc/editor.md) |
| Mappings and the 28 transforms | [doc/mappings.md](doc/mappings.md), [doc/transforms.md](doc/transforms.md) |
| Dry run, retry, undo, conflict rules, notifications | [doc/runs.md](doc/runs.md) |
| Schedules and the webhook / API trigger | [doc/schedules.md](doc/schedules.md), [doc/triggers.md](doc/triggers.md) |
| Every source | [files](doc/source-examples-files.md), [feeds and web](doc/source-examples-feeds-web.md), [APIs](doc/source-examples-apis.md), [databases](doc/source-examples-databases.md), [platforms, commerce, mail, documents](doc/source-examples-platforms.md) |
| Data tables, Export and CIE round trips, fetch functions | [doc/datatables.md](doc/datatables.md), [doc/transfer.md](doc/transfer.md), [doc/rest-and-fetch.md](doc/rest-and-fetch.md) |
| Your own source, handler or transform | [doc/handlers.md](doc/handlers.md) |
| Upgrading from 1.x | [doc/migration-1x.md](doc/migration-1x.md) |
| Problems and questions | [doc/troubleshooting.md](doc/troubleshooting.md) |

The manual of the 1.x API, which still applies, is
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
