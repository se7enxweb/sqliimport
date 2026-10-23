# 7x SQLI Import 2.0 manual

7x SQLI Import brings content from outside into Exponential: a CSV export, an XML file, an RSS or Atom feed, a JSON
or NDJSON dump, an Excel or OpenDocument spreadsheet. It runs once (a migration) or on a schedule (a nightly sync).
This is the manual of the extension `se7enxweb/sqliimport` in the state 2.0.0-beta (in testing): release
2.0.0-alpha.1 and the import editor, the administration pages, spreadsheet sources and the two policies added
since.

German: [ger-de/index.md](ger-de/index.md).

## What is new in 2.0

- **Imports are stored in the database.** An import (table `sqliimport_definition`) names its source, its handler,
  its target, its behavior and its typed options. The 1.x tables stay.
- **The import editor** in the administration creates and changes imports in six steps: source, processing,
  mapping, preview, schedule and review. Each step is saved on its own; **Auto-match by name** fills the mapping
  from the source's fields, and the preview shows a sample and a dry run of the first records before anything is
  published ([editor.md](editor.md)).
- **Runs go through the job queue (expqueue).** A run is a chain of jobs: start, chunks of records, finish. Each
  chunk saves checkpoints. One import runs once at a time; different imports run in parallel.
- **Five source types:** CSV/TSV, XML, RSS/Atom, JSON/NDJSON and spreadsheets (XLSX and ODS), from an upload, an
  `http(s)` address or a file on the server inside an allowed directory.
- **A no-code handler, "mapping".** It maps source fields to the attributes of a content class, with chains of 27
  built-in transforms written as one line of text (`trim | date(format=d.m.Y)`), and publishes a new version only
  when a field really changed.
- **1.x handlers run untouched** through a compatibility adapter.
- **Every run is traceable:** one row per record (created, updated, unchanged, skipped, failed) and a log per run.
  The run page follows a running run live. A dry run publishes nothing and shows what would change. "Retry failed
  rows" processes only the failed records.
- **Schedules:** once, every N minutes, hourly, daily, weekly, monthly or a cron expression, in a time zone. The
  Schedules page lists them with their next runs and pauses or resumes them.
- **Administration:** the Imports tab with the dashboard, the editor, a live page per run, the Schedules, Catalogue
  and Settings pages, the export and import of import definitions as JSON, and the block "Imports" on the admin
  dashboard.
- **Two policies:** "Manage imports" (`manage`) and "Run imports" (`run`), both with a handler limitation. The 1.x
  policies keep working ([permissions.md](permissions.md)).
- **Command line:** `./console ext:sqliimport:install-schema|list|run|status|cancel|retry|migrate-1x|tick|purge`.
- **1.x bugs fixed on Exponential 6:** the fields of 1.x import requests and scheduled imports no longer read as
  empty, options serialized with NUL bytes are no longer cut on SQLite, and option text without `=` no longer
  warns on PHP 8.

## Languages

The pages of the extension are in American English and in German: `translations/ger-DE/translation.ts` translates
every text of the templates, the messages of the editor, the labels and help texts of the options of every format,
handler and transform (context `extension/sqliimport/options`), the descriptions of the catalogue
(`extension/sqliimport/catalogue`), the words of the statuses and the schedule descriptions. An administrator whose
locale is `ger-DE` sees the German pages; the kernel loads the file through `site.ini [RegionalSettings]
TranslationExtensions[]=sqliimport`. `translations/untranslated/translation.ts` lists every text for a new
language; the French and Spanish files hold the texts of 1.x. The log of a run, the messages of a source and the
command line stay in English. `tests/unit/TranslationsTest.php` fails when a text of the code is missing in ger-DE or
untranslated, or when a translation loses a `%placeholder`.

## Pages

| Page | What it covers |
|---|---|
| [install.md](install.md) | Requirements, installation, the cron parts, the Imports tab and its pages |
| [concepts.md](concepts.md) | Imports, sources, handlers, mappings, runs, rows, schedules |
| [editor.md](editor.md) | Creating and changing imports in the administration, step by step |
| [sources.md](sources.md) | CSV, XML, RSS, JSON and spreadsheet sources and where their data comes from |
| [mappings.md](mappings.md) | The no-code mapping, the transforms and their one-line text, creating an import in PHP |
| [handlers.md](handlers.md) | Writing a 2.x handler, and how 1.x handlers keep working |
| [runs.md](runs.md) | How a run works, the live run page, statuses, rows and log, cancel, retry, dry run |
| [statistics.md](statistics.md) | The charts of the dashboard and the Statistics page: trends, per import, comparison, suggestions, CSV |
| [transfer.md](transfer.md) | Imports among the import and export tools: Import into this folder, files of Export and CIE, results and mapping files |
| [schedules.md](schedules.md) | Schedule kinds, time zones, daylight saving time, the Schedules page, the cron part |
| [triggers.md](triggers.md) | Starting an import from another system: signed calls, API keys, data in the call, status, limits |
| [ai.md](ai.md) | The AI assist through expai: mapping and class suggestions, explanations of failed rows, run summaries, what is sent |
| [cli.md](cli.md) | Every `./console ext:sqliimport:*` command |
| [permissions.md](permissions.md) | The two policies of 2.x and the policies of 1.x |
| [settings.md](settings.md) | Every setting of `sqliimport.ini` for 2.x, the Settings page, export and import |
| [migration-1x.md](migration-1x.md) | Upgrading an installation of SQLI Import 1.x |
| [troubleshooting.md](troubleshooting.md) | What to check when something does not work |

The content API and the handler API of 1.x, which still apply, are described in
[content-api-and-handlers-1x.md](content-api-and-handlers-1x.md).
