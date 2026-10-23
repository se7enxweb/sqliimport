# 7x SQLI Import 2.0 manual

7x SQLI Import brings content from outside into Exponential: a spreadsheet, a feed, a website, an API, another
database, another CMS. It runs once (a migration), on a schedule (a nightly sync) or when another system calls it.
This is the manual of the extension `se7enxweb/sqliimport`, release 2.0, with a worked example for every use case:
the exact steps, the settings and mapping lines, a sample of the input, the result, and screenshots of a real
installation.

German: [ger-de/index.md](ger-de/index.md).

![The top of the Imports dashboard: the Run now toolbar with New import, and the figures imports, runs today, running or queued, failed in 24 hours and the next scheduled run](images/gs-dashboard-top.png)

## Start here

New to imports? Read **[getting-started.md](getting-started.md)**: it activates the extension, explains the two
policies and the catalogue, and walks through a first CSV import from the file to the published products, with a
second run that updates, adds and catches a broken line.

## Table of contents

### Using imports

| Page | What it covers |
|---|---|
| [getting-started.md](getting-started.md) | Activation, permissions, the Imports tab, the catalogue, a first CSV import end to end |
| [editor.md](editor.md) | Every step and field of the six-step editor, values kept on errors, secrets and the "New value" field |
| [mappings.md](mappings.md) | Classes, parents, remote ID patterns, matching, a class per record, translations by column, media |
| [transforms.md](transforms.md) | Every built-in transform with a real input and output, and the datatypes that need a special text |
| [runs.md](runs.md) | Dry run, Run now, live progress and cancel, retry of failed rows, undo, conflict rules, notifications and the bell, the run history and the CSV report |
| [schedules.md](schedules.md) | Schedules with cron expressions, time zones, missed runs, pause and resume |
| [triggers.md](triggers.md) | Starting an import from another system: signed calls and API keys, with curl examples |
| [statistics.md](statistics.md) | The charts of the dashboard and the Statistics page |
| [definitions.md](definitions.md) | Download as JSON, Export selected, Import definitions, Copy to … and templates of imports |
| [ai.md](ai.md) | The AI assist through expai: mapping and class suggestions, explanations of failed rows, run summaries, what is sent |

### Every source, with a worked example

| Page | Sources |
|---|---|
| [source-examples-files.md](source-examples-files.md) | CSV, TSV, XLSX/ODS, NDJSON, JSON, XML, YAML, TOML, OPML, Avro/Arrow (Parquet via DuckDB), media ZIP with EXIF/IPTC, Markdown folder, WordPress WXR, iCalendar, vCard |
| [source-examples-feeds-web.md](source-examples-feeds-web.md) | RSS/Atom, podcasts, YouTube (Data API, and the opt-in page scraper), Vimeo, sitemap crawl, web page scraper |
| [source-examples-apis.md](source-examples-apis.md) | REST API (paging and sign-in modes), Google Sheets, Airtable, Notion, Baserow, Supabase, Firebase, Salesforce, HubSpot |
| [source-examples-databases.md](source-examples-databases.md) | SQL (MySQL, PostgreSQL, SQLite, SQL Server, Oracle) and NoSQL (MongoDB, Redis, Elasticsearch, CouchDB/Couchbase, DynamoDB, Firestore, Cosmos DB, Cassandra, Neo4j/ArangoDB, ClickHouse/BigQuery/Snowflake, InfluxDB, DuckDB, LDAP) |
| [source-examples-platforms.md](source-examples-platforms.md) | Another Exponential site, Drupal, Joomla, .ezpkg packages, Google Merchant, Shopify, WooCommerce, IMAP mailboxes, documents (files, ZIP, URLs, feeds, WebDAV, Drive, OneDrive, mail attachments) |
| [sources.md](sources.md) | The reference: every option of every source type |

### Working with the rest of the site

| Page | What it covers |
|---|---|
| [datatables.md](datatables.md) | Importing into a data table, a new data table from an import's fields, content and tables in sync |
| [transfer.md](transfer.md) | "Looking for…?", "Import into this folder…", round trips with Export and CIE, the shared run history |
| [rest-and-fetch.md](rest-and-fetch.md) | Template fetch functions, the ezjscore calls, the trigger endpoint |
| [handlers.md](handlers.md) | For developers: writing your own source, handler or transform, with tested code |
| [migration-1x.md](migration-1x.md) | Upgrading from SQLI Import 1.x; 1.x handlers in the 2.x engine |

### Running the tool

| Page | What it covers |
|---|---|
| [install.md](install.md) | Requirements, installation, the cron parts, the Imports tab |
| [permissions.md](permissions.md) | Manage imports and Run imports, the handler limitation, the 1.x policies |
| [settings.md](settings.md) | Every setting of `sqliimport.ini`, the Settings page, export and import of definitions |
| [cli.md](cli.md) | Every `./console ext:sqliimport:*` command |
| [concepts.md](concepts.md) | Imports, sources, handlers, mappings, runs, rows, schedules, the tables |
| [troubleshooting.md](troubleshooting.md) | Problems and frequently asked questions |

The content API and the handler API of 1.x, which still apply, are described in
[content-api-and-handlers-1x.md](content-api-and-handlers-1x.md).

## What is new in 2.0

- **Imports are stored in the database** (table `sqliimport_definition`): source, handler, target, behavior and
  typed options, made in the administration. The 1.x tables stay.
- **The import editor** creates and changes imports in six steps: source, processing, mapping, preview, schedule
  and review ([editor.md](editor.md)).
- **More than 60 source types**, grouped in a catalogue: files and data, NoSQL databases, content, platforms,
  commerce, media and mail, each with an example and, where it makes sense, a preset mapping.
- **A no-code handler, "mapping"**, with 28 built-in transforms written as one line of text
  (`trim | date(format=d.m.Y)`), which publishes a new version only when a field really changed.
- **Runs on the job queue (expqueue):** chunks with checkpoints, one run per import at a time, different imports in
  parallel, live progress, cancel, dry runs, retry of failed rows, undo for 30 days, conflict rules for objects
  edited by people, notifications by mail and in the header bell.
- **Schedules** (once, every N minutes, hourly, daily, weekly, monthly, cron) in a time zone, and a **trigger** for
  other systems (signed calls or API keys, with the data in the call).
- **Data tables:** an import can fill a module's own table, and offer a new table from its fields.
- **Statistics** of the run history, with charts and a CSV download.
- **Two policies**, "Manage imports" and "Run imports". The 1.x policies keep working.
- **1.x handlers run untouched** through a compatibility adapter ([migration-1x.md](migration-1x.md)).
- **The AI assist** (with the extension expai): mapping and class suggestions, explanations of failed rows and run
  summaries, for a person to accept ([ai.md](ai.md)).
- **Definitions, copies and templates:** imports move between installations as JSON files, are copied with **Copy
  to …** and kept as templates ([definitions.md](definitions.md)).

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
