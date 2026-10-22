# Changelog of 7x SQLI Import

Every release, newest first, in the form Added / Updated / Fixed / Removed / Renamed. The history of 1.x before 1.3.1
is in [doc/CHANGELOG](doc/CHANGELOG).

## Unreleased (branch 2.0-dev, in testing for 2.0.0-beta.1)

### Added

- Added: The import editor (sqliimport/edit): source (upload, address or server file with the options of its type),
  processing (the no-code mapping or a handler with a form made from its options; target and behaviour), mapping
  (class, remote ID pattern or attribute to match, parents, a grid with a line per attribute; Auto-match by name),
  preview (the sample and a dry run of the first records with the changed fields), schedule (with the next runs)
  and review (Save, or Save and run). Every step is saved on its own and every value is checked on the server.
- Added: The live run page: status, progress, counters and new log lines every two seconds while a run goes on.
- Added: The pages Schedules (pause, resume, edit, delete), Catalogue (sources, handlers, transforms) and Settings
  (the settings as they stand, export and import of import definitions as JSON), and a dashboard block with the
  latest runs.
- Added: Edit, Duplicate, Enable or Disable and Delete of an import in the list.
- Added: Spreadsheet sources (XLSX and ODS), read without a library; date(format=excel) for spreadsheet dates.
- Added: The one-line text of a transform chain in the mapping grid: trim | date(format=d.m.Y) | map(table={...}).
- Added: Undo of a run (sqliimport/run, "Undo", and bin/php/undo.php): objects the run created go to the trash,
  objects it updated get the version before the run published again as a new version. An object changed after
  the run is left alone and listed. A run is undone once, within the days set by UndoDays (30, per import too).
- Added: Conflict rules for objects a person changed since the last run: overwrite, skip, fill_empty (only empty
  fields) and merge (only the fields nobody changed), decided by the field values the import wrote.
- Added: The CSV report of a run (one line per record), from the run page and ./console ext:sqliimport:report.
- Added: A catalogue of formats: a format is one source class plus one block in kernelservices.ini (tagged
  sqliimport.source), with a description, a group, example files and a preset mapping that a new import starts
  from. The catalogue shows them as cards by group with New import; the editor groups them the same way.
- Added: OPML feed lists (opml), with ./console ext:sqliimport:opml-feeds to create one RSS import per feed.
- Added: TSV (tsv) and JSON Lines (ndjson) as formats of their own (variants of the CSV and JSON sources).
- Added: WordPress exports (wxr): posts and pages with author, dates, categories, tags, meta and featured image, or
  the media, categories, tags and authors of the export.
- Added: The format iCalendar (.ics, file or address): one record per event (and per task on request) with start
  and end as written, in ISO 8601 UTC and as timestamps (time zones and all-day dates included), recurrence rule,
  categories and organizer; read as a stream. Preset: the class event.
- Added: The format vCard (.vcf, 3.0 and 4.0): one record per contact with name parts, e-mail addresses, phone
  numbers, organization, address, birthday and photo address; read as a stream. No preset.
- Added: The format Google Merchant product feed (RSS with the g: namespace): ID, title, description, images, price
  and sale price with currency, availability, brand, GTIN, MPN, categories, shipping and tax. Preset: the class
  product of the webshop.
- Added: The formats Shopify product CSV (the rows of one handle are one product with its variants and images) and
  WooCommerce product CSV (variations nested in their variable product), with prices as decimals, the currency as
  an option and the tax fields. Preset: the class product of the webshop.

- Added: Markdown files with front matter (markdown), one file or a folder as ZIP (Jekyll, Hugo, Obsidian): title,
  date, tags, summary, the body as HTML and every front matter key; YAML and TOML read without a library.
- Added: Media files with EXIF and IPTC (media), one file or a folder as ZIP: caption, keywords, photographer,
  copyright, date taken, camera, GPS and a content hash; the transforms image and file import the file itself out
  of the run's ZIP, and a remote ID from the hash imports the same file once.

### Updated

- Updated: The tick also runs within the cron part expqueue, so schedules need no cron line of their own; a lock
  keeps two ticks from running at once.
- Updated: A preset maps only the fields the site's class can take (by attribute and datatype).
- Updated: Two policies, as the owner chose: manage (Manage imports) and run (Run imports), both with the handler
  limitation; the functions view, edit, rollback, ai and settings of 2.0.0-alpha.1 are gone. manageimports of 1.x
  counts as manage, listimports lets one look; migrate-1x --roles gives manage to the roles with manageimports.

### Fixed

- Fixed: The run page of a queued or running run ended in an error (2.0.0-alpha.1).
- Fixed: Spaces at the ends of a text argument of a transform (replace(search=" GmbH")) were cut off.

## 2.0.0-alpha.1 (2026-10-22)

The first release of 7x SQLI Import 2: the foundation and the engine, with the dashboard and run pages of the
administration. The import editor (upload, mapping grid, preview, schedules in the administration) comes with
2.0.0-beta.1. Every 1.x handler, setting, table, policy and page keeps working.

### Added

- Added: The 2.x engine on the job queue (expqueue): a run is a chain of jobs (start, chunks of 200 records, finish)
  in its own job group and resource, with checkpoints, cancel at any time, and one run per import at a time while
  different imports run in parallel (it replaces the one global lock of 1.x).
- Added: Imports stored in the database (sqliimport_definition) with their source, handler, target, behaviour and
  typed JSON options, and the tables sqliimport_mapping, sqliimport_mapping_field, sqliimport_schedule,
  sqliimport_run, sqliimport_run_row and sqliimport_run_log, defined once in expImportSchema and generated for MySQL,
  PostgreSQL, SQLite and Oracle (share/db_schema.dba, sql/<engine>/schema.sql).
- Added: The sources CSV/TSV (delimiter and encoding detected), XML (record element, XSD validation), RSS and Atom (one
  set of fields), JSON and NDJSON, from an upload, an http(s) address (private networks refused unless allowed) or a
  server file inside [SourceSettings] AllowedPaths[], read as streams with resumable cursors.
- Added: The no-code handler "mapping": source fields to class attributes with chains of named transforms (27
  built-in, more as kernel services), matching by remote ID pattern or attribute, parents from the record, update
  policies, and dry runs that record the changed fields without publishing.
- Added: Every 1.x handler runs in the 2.x engine through expImportLegacyHandlerAdapter, untouched; its progression
  notes go to the run's log.
- Added: A row per record of a run (outcome, object, versions before and after, nodes added, the record of a failed
  row) and a log per run; "Retry failed rows" reprocesses only those records.
- Added: Schedules: once, every N minutes, hourly, daily, weekly, monthly or a cron expression, in a time zone and
  across daylight saving time, queued by the new cron part "sqliimport" (every minute), which also purges runs older
  than [RunSettings] KeepDays.
- Added: The console commands ext:sqliimport:install-schema, list, run (--dry-run, --wait), status, cancel, retry,
  migrate-1x (reports first; --apply, --roles), tick and purge.
- Added: The Imports dashboard (sqliimport/list: key figures, Run now, the imports, the runs filtered by status) and the
  run page (sqliimport/run/<id>: progress, outcomes, records with their changes, log, Cancel, Retry failed rows), built
  from the administration's shared components, in light and dark mode, without inline CSS or JavaScript.
- Added: The policies view, edit, run, manage, rollback, ai and settings (edit and run with the handler limitation);
  roles with the 1.x policies listimports and manageimports keep their access.
- Added: The audit trail branch system.import.* (runs started, finished, failed, cancelled; imports changed; 1.x
  migrated), the kernel service sqliimport.registry and the tags sqliimport.source, sqliimport.handler and
  sqliimport.transform for a site's own sources, handlers and transforms.
- Added: The manual in English and German in doc/ and doc/ger-de/, unit tests (sources, transforms, mapping,
  schedules, options, schema, styling and script rules) and GitHub Actions for PHP 8.0 to 8.5.

### Updated

- Updated: The left menu of the Imports tab is the administration's shared menu (parts/ini_menu.tpl) with admin4's width
  switch; the 1.x pages keep their content.
- Updated: The admin header tab is called Imports (German: Importe).
- Updated: The 1.x cron part sqliimport_run hands the pending and scheduled 1.x imports to the 2.x engine when the job
  queue is installed (deprecated in favour of the cron part sqliimport).
- Updated: Cancel, interrupt, remove and purge on the 1.x pages are small POST forms with a confirmation instead of
  links; a GET request changes nothing.
- Updated: composer.json requires expqueue and expui and suggests expai; README.md describes release 2.

### Fixed

- Fixed: On Exponential 6 the fields of a 1.x import request and of a 1.x scheduled import read as empty (their getter
  bypassed the kernel's persistent storage), so the 1.x list and cronjob saw nothing.
- Fixed: The options of a 1.x import were cut on SQLite (the NUL bytes of a serialized object); they are now stored
  with a marker on every database, and only the options class is unserialized.
- Fixed: A scheduled 1.x import replaced its options by empty ones when they were read twice.
- Fixed: Option text with a line without "=" warned on PHP 8, and "=" inside a value was split in the handler option
  line of the command line.
- Fixed: The values written by the 1.x templates (labels, handler names, options, error messages) are escaped.

### Removed

- Removed: The jscal2 calendar with its themes and border-radius.css; the date of a 1.x scheduled import is a date
  field.

### Renamed

- Renamed: The 1.x list of import requests moved from sqliimport/list to sqliimport/items (sqliimport/list is the 2.x
  dashboard); the PostgreSQL schema directory sql/postgesql is sql/postgresql; the 1.x README is
  doc/content-api-and-handlers-1x.md.

## 1.3.1 (2026-10-22)

See [doc/CHANGELOG](doc/CHANGELOG).
