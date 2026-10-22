# 7x SQLI Import 2.0 manual

7x SQLI Import brings content from outside into Exponential: a CSV export, an XML file, an RSS or Atom feed, a JSON
or NDJSON dump. It runs once (a migration) or on a schedule (a nightly sync). This is the manual of release
2.0.0-alpha.1 of the extension `se7enxweb/sqliimport`.

German: [ger-de/index.md](ger-de/index.md).

## What is new in 2.0

- **Imports are stored in the database.** An import (table `sqliimport_definition`) names its source, its handler,
  its target, its behavior and its typed options. The 1.x tables stay.
- **Runs go through the job queue (expqueue).** A run is a chain of jobs: start, chunks of records, finish. Each
  chunk saves checkpoints. One import runs once at a time; different imports run in parallel.
- **Four source types:** CSV/TSV, XML, RSS/Atom and JSON/NDJSON, from an upload, an `http(s)` address or a file
  on the server inside an allowed directory.
- **A no-code handler, "mapping".** It maps source fields to the attributes of a content class, with chains of 27
  built-in transforms, and publishes a new version only when a field really changed.
- **1.x handlers run untouched** through a compatibility adapter.
- **Every run is traceable:** one row per record (created, updated, unchanged, skipped, failed) and a log per run.
  A dry run publishes nothing and shows what would change. "Retry failed rows" processes only the failed records.
- **Schedules:** once, every N minutes, hourly, daily, weekly, monthly or a cron expression, in a time zone.
- **Command line:** `./console ext:sqliimport:install-schema|list|run|status|cancel|retry|migrate-1x|tick|purge`.
- **Administration:** the Imports tab with a dashboard and a page per run.
- **1.x bugs fixed on Exponential 6:** the fields of 1.x import requests and scheduled imports no longer read as
  empty, options serialized with NUL bytes are no longer cut on SQLite, and option text without `=` no longer
  warns on PHP 8.

The import editor of the administration (create and edit imports, mapping grid, preview, schedules) comes with
2.0.0-beta.1. Until then you create imports with `migrate-1x`, from 1.x handlers, or in PHP (see
[mappings.md](mappings.md)).

## Pages

| Page | What it covers |
|---|---|
| [install.md](install.md) | Requirements, installation, the cron parts, the Imports tab |
| [concepts.md](concepts.md) | Imports, sources, handlers, mappings, runs, rows, schedules |
| [sources.md](sources.md) | CSV, XML, RSS and JSON sources and where their data comes from |
| [mappings.md](mappings.md) | The no-code mapping, the transforms, creating an import in PHP |
| [handlers.md](handlers.md) | Writing a 2.x handler, and how 1.x handlers keep working |
| [runs.md](runs.md) | How a run works, its statuses, rows and log, cancel, retry, dry run |
| [schedules.md](schedules.md) | Schedule kinds, time zones, daylight saving time, the cron part |
| [cli.md](cli.md) | Every `./console ext:sqliimport:*` command |
| [permissions.md](permissions.md) | The policies of 2.x and 1.x |
| [settings.md](settings.md) | Every setting of `sqliimport.ini` for 2.x |
| [migration-1x.md](migration-1x.md) | Upgrading an installation of SQLI Import 1.x |
| [troubleshooting.md](troubleshooting.md) | What to check when something does not work |

The content API and the handler API of 1.x, which still apply, are described in
[content-api-and-handlers-1x.md](content-api-and-handlers-1x.md).
