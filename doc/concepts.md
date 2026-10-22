# Concepts

## Import

An import is one row of `sqliimport_definition` (class `expImportDefinition`). It says:

| Field | Meaning |
|---|---|
| `identifier`, `name` | How the command line and the administration name it. The identifier is unique. |
| `source_type` | `csv`, `xml`, `rss`, `json`, `spreadsheet` or another registered source; `legacy` for a 1.x handler that reads its own data |
| `source_config` | JSON: `origin` (`upload`, `url`, `path`), the file, address or path, and the options of the source type |
| `handler` | `mapping` (no code), a 2.x handler or a 1.x handler |
| `mapping_id` | The mapping of the `mapping` handler |
| `target_json` | JSON: `parent_node_id`, `section_id`, `language`, `owner_id` (a robot user; 0 = the user who starts) |
| `behaviour_json` | JSON: `chunk_size`, `update_policy`, `trash_policy`, `max_rows` |
| `options_json` | JSON: the handler's options, validated against its schema |
| `is_enabled` | A disabled import cannot run (a dry run still can) |
| `def_version` | Raised on every change; a run records the version it ran with |

## Source

A source type reads records from a file: [sources.md](sources.md). The engine first makes a local snapshot of the
data (an uploaded file, a download, or a file on the server), so every chunk of a run reads the same data.

## Record

One record is one line of a CSV file, one element of an XML file, one item of a feed or one object of a JSON file
(class `expImportRecord`). It has a reference (`ref`: a key field or its position), its data as an array, and a
cursor that tells the source where to continue after it. Nested values are read with dotted paths such as
`author.name` or `items.0.title`.

## Handler

A handler turns a record into content:

- **`mapping`** applies the import's mapping. No PHP is needed: [mappings.md](mappings.md).
- **A 2.x handler** implements `expImportHandlerInterface`: [handlers.md](handlers.md).
- **A 1.x handler** (`ISQLIImportHandler`) runs through `expImportLegacyHandlerAdapter`. It reads its own data,
  so its import has the source type `legacy`.

## Mapping

A mapping (tables `sqliimport_mapping` and `sqliimport_mapping_field`) names the content class, how a record finds
its object (a remote ID pattern or an attribute's value), which source field fills which attribute through which
transforms, and where new objects are placed.

## Run

A run (table `sqliimport_run`, class `expImportRun`) is one execution of an import. Its kind is `import`,
`dry_run` or `retry`. Its trigger is `manual`, `schedule`, `cli`, `rest` or `legacy`. A run is processed by the job
queue in chunks: [runs.md](runs.md).

## Row and log

Every record of a run gets a row (table `sqliimport_run_row`) with its outcome:

| Outcome | Meaning |
|---|---|
| `created` | A new object was published |
| `updated` | The object got a new version because a field changed |
| `unchanged` | The object exists and no field changed; nothing was published |
| `skipped` | The record was left out on purpose (for example, the object is in the trash) |
| `failed` | The record could not be processed; the row carries the message and the record |
| `processed` | A 1.x handler processed the record; what it did is not known to the engine |

Every run also has a log (table `sqliimport_run_log`) with a severity per line and, where it applies, the record
the line is about.

## Schedule

A schedule (table `sqliimport_schedule`) starts an import by itself: [schedules.md](schedules.md).

## The tables

| Table | Holds |
|---|---|
| `sqliimport_definition` | The imports |
| `sqliimport_mapping`, `sqliimport_mapping_field` | The mappings and their lines |
| `sqliimport_schedule` | The schedules |
| `sqliimport_run`, `sqliimport_run_row`, `sqliimport_run_log` | The runs, their rows and their logs |
| `sqliimport_item`, `sqliimport_scheduled` | The tables of 1.x, kept |

## The catalogue

The kernel service `sqliimport.registry` (class `expImportRegistry`) lists the sources, handlers and transforms of
a site. It reads `sqliimport.ini` and the kernel services tagged `sqliimport.source`, `sqliimport.handler` and
`sqliimport.transform`.

## Audit trail

The audit trail records, under `system.import.*`: runs started, finished, failed or cancelled; imports created,
changed or deleted; schedules changed; and the migration of 1.x. The rows of a run are not copied to the audit
trail; they stay in `sqliimport_run_row`.
