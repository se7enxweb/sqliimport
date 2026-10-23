# Concepts

This page names the parts of 7x SQLI Import and shows how they hang together: an import, its source and handler,
its mapping, its runs with their rows and logs, its schedule, its trigger and the notices of its runs. The last
section follows one real import of the test installation through all of them.

German: [ger-de/concepts.md](ger-de/concepts.md).

## Table of contents

- [Import](#import)
- [Source](#source)
- [Record](#record)
- [Handler](#handler)
- [Mapping](#mapping)
- [Run](#run)
- [Row and log](#row-and-log)
- [Schedule](#schedule)
- [Trigger](#trigger)
- [Notice](#notice)
- [How the parts relate: import #132](#how-the-parts-relate-import-132)
- [The tables](#the-tables)
- [The catalogue](#the-catalogue)
- [Audit trail](#audit-trail)

## Import

An import is one row of `sqliimport_definition` (class `expImportDefinition`). It says:

| Field | Meaning |
|---|---|
| `identifier`, `name` | How the command line and the administration name it. The identifier is unique. |
| `source_type` | The format: `csv`, `tsv`, `spreadsheet`, `json`, `ndjson`, `xml`, `rss`, `sql`, `mongodb`, `wxr` and every other registered source ([sources.md](sources.md)); `legacy` for a 1.x handler that reads its own data |
| `source_config` | JSON: `origin` (`upload`, `url`, `path`, or `remote` for a source that reads a database or a service), the file, address or path, and the options of the source type |
| `handler` | `mapping` (no code), `datatable` (rows of a data table), another 2.x handler or a 1.x handler |
| `mapping_id` | The mapping of the `mapping` handler |
| `target_json` | JSON: `parent_node_id`, `section_id`, `language`, `owner_id` (a robot user; 0 = the user who starts) |
| `behaviour_json` | JSON: `chunk_size`, `update_policy`, `trash_policy`, `max_rows`, `conflict_policy`, `undo_days`, `notify_when`, `notify_starter`, `notify_emails` |
| `options_json` | JSON: the handler's options, validated against its schema |
| `is_enabled` | A disabled import cannot run (a dry run still can) |
| `def_version` | Raised on every change; a run records the version it ran with |

## Source

A source type reads records: from a file, an address, a database or a service ([sources.md](sources.md)). The engine
first makes a local snapshot of the data where it can (an uploaded file, a download, a file on the server), so
every chunk of a run reads the same data.

## Record

One record is one line of a CSV file, one element of an XML file, one item of a feed, one object of a JSON file or
one row of a database (class `expImportRecord`). It has a reference (`ref`: a key field or its position), its data as
an array, and a cursor that tells the source where to continue after it. Nested values are read with dotted paths
such as `author.name` or `items.0.title`.

## Handler

A handler turns a record into content or rows:

- **`mapping`** applies the import's mapping. No PHP is needed: [mappings.md](mappings.md).
- **`datatable`** writes the record as a row of a module's own table: [datatables.md](datatables.md).
- **A 2.x handler** implements `expImportHandlerInterface`: [handlers.md](handlers.md).
- **A 1.x handler** (`ISQLIImportHandler`) runs through `expImportLegacyHandlerAdapter`. It reads its own data,
  so its import has the source type `legacy`.

## Mapping

A mapping (tables `sqliimport_mapping` and `sqliimport_mapping_field`) names the content class, how a record finds
its object (a remote ID pattern or an attribute's value), which source field fills which attribute through which
transforms, and where new objects are placed.

## Run

A run (table `sqliimport_run`, class `expImportRun`) is one execution of an import. Its kind is `import`,
`dry_run`, `retry` (the failed rows of an earlier run) or `undo` (takes back an earlier run). Its trigger is
`manual`, `schedule`, `cli`, `rest` (the webhook / API trigger) or `legacy`. A retry or an undo names its earlier
run in `parent_run_id`. A run is processed by the job queue in chunks: [runs.md](runs.md).

## Row and log

Every record of a run gets a row (table `sqliimport_run_row`) with its outcome:

| Outcome | Meaning |
|---|---|
| `created` | A new object was published |
| `updated` | The object got a new version because a field changed |
| `unchanged` | The object exists and no field changed; nothing was published |
| `skipped` | The record was left out on purpose (for example, the object is in the trash, or the conflict rule says skip) |
| `failed` | The record could not be processed; the row carries the message and the record |
| `undone` | An undo run took back what the earlier run did with this object |
| `processed` | A 1.x handler processed the record; what it did is not known to the engine |

Every run also has a log (table `sqliimport_run_log`) with a severity per line and, where it applies, the record
the line is about.

## Schedule

A schedule (table `sqliimport_schedule`) starts an import by itself: [schedules.md](schedules.md). An import has at
most one schedule in the editor.

## Trigger

A trigger (table `sqliimport_trigger`) lets another system start the import with a signed call or an API key; its
keys are in `sqliimport_trigger_key` (only a hash of each key), and every call is noted in
`sqliimport_trigger_call`: [triggers.md](triggers.md).

## Notice

A notice (table `sqliimport_notice`) tells the user who started a run how it ended: on the dashboard and in the bell
of the header, until it is seen ([runs.md](runs.md#notifications)).

## How the parts relate: import #132

The import "Docs: First import (teas CSV)" of [getting-started.md](getting-started.md) has been through all of this on
the test installation. Read from top to bottom, the table shows what belongs to what:

| Part | Record on the test installation | What it says |
|---|---|---|
| Import | `sqliimport_definition` #132, `docs-first-import`, source `csv`, handler `mapping`, enabled | the definition |
| └ Mapping | `sqliimport_mapping` #101, class `product`, 4 lines in `sqliimport_mapping_field` | what fills which attribute |
| └ Schedule | `sqliimport_schedule` #2: cron `0 6 * * 1-5`, time zone `Europe/Berlin`, paused (`is_active` 0) | weekdays at 06:00 Berlin time, switched off |
| └ Trigger | `sqliimport_trigger` #1, switched off again after the examples (it was on, with the documentation's test administrator as run user) | the webhook / API trigger |
| └└ Trigger key | `sqliimport_trigger_key` #1, label "Docs: curl example", revoked after the examples | one API key (its hash) |
| └└ Trigger calls | 2 rows in `sqliimport_trigger_call` | the calls it received |
| └ Run #180 | `import`, `manual`, completed: 5 created; 5 rows, 2 log lines | the first run |
| └ Run #181 | `dry_run`, `manual`, completed with errors: 1 created, 2 updated, 3 unchanged, 1 failed; 7 rows | the dry run of the changed file |
| └└ Run #182 | `retry` of #181 (`parent_run_id` 181): the 1 failed row failed again; 1 row | a retry repeats the stored record, so a data error stays |
| └ Run #184 | `import`, `manual`, completed: 2 created, 2 updated, 3 unchanged; 7 rows | the second run with the corrected file |
| └ Runs #192, #193, #194, #196 | `dry_run`, one per conflict rule | the conflict rule examples |
| └ Run #200 | `import`, completed: 1 updated, 6 unchanged | the merge example |
| └└ Run #201 | `undo` of #200 (`parent_run_id` 200): 1 undone | takes #200 back |
| └ Runs #202, #204 | `import`, trigger `rest`: 7 unchanged; then 1 created, 1 updated (2 records sent in the call) | started by the trigger |
| └ Run #215 | `import`, `manual`, completed: 1 updated, 6 unchanged | a later run |
| └ Notices | #15 (run 181, errors, seen), #16 (run 182, errors, seen), #19 (run 215, completed, not seen yet) | what the starter was told |

Every run has its own rows (`sqliimport_run_row`, one per record: 5 for #180, 7 for #184) and log lines
(`sqliimport_run_log`). A dry run has rows too, with what would have happened, but publishes nothing. Removing the
import removes its mapping and its schedule; its runs, rows, logs and notices stay until
`[RunSettings] KeepDays` purges them.

## The tables

| Table | Holds |
|---|---|
| `sqliimport_definition` | The imports |
| `sqliimport_mapping`, `sqliimport_mapping_field` | The mappings and their lines |
| `sqliimport_schedule` | The schedules |
| `sqliimport_run`, `sqliimport_run_row`, `sqliimport_run_log` | The runs, their rows and their logs |
| `sqliimport_notice` | The notices of ended runs, per user |
| `sqliimport_trigger`, `sqliimport_trigger_key`, `sqliimport_trigger_call` | The triggers, their API keys (hashed) and their calls |
| `sqliimport_item`, `sqliimport_scheduled` | The tables of 1.x, kept |

`./console ext:sqliimport:install-schema --check` lists all thirteen ([install.md](install.md#check-the-installation)).

## The catalogue

The kernel service `sqliimport.registry` (class `expImportRegistry`) lists the sources, handlers and transforms of
a site. It reads `sqliimport.ini` and the kernel services tagged `sqliimport.source`, `sqliimport.handler` and
`sqliimport.transform`. The **Catalogue** page shows them, grouped.

## Audit trail

The audit trail records, under `system.import.*`: runs started, finished, failed or cancelled; imports created,
changed or deleted; schedules changed; trigger calls; and the migration of 1.x. The rows of a run are not copied to
the audit trail; they stay in `sqliimport_run_row`.
