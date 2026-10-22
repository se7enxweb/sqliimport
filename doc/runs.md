# Runs

A run is one execution of an import (`expImportRun`, table `sqliimport_run`). It is processed by the job queue
(expqueue), not by the request or the command that starts it.

## How a run works

Starting a run (`expImportRunner::start()`) stores the run with the status `queued` and puts its first job into the
queue. Every run has its own job group and the queue resource `sqliimport:<import id>`.

| Job | What it does |
|---|---|
| `sqliimport.run` | Makes the snapshot of the source, counts the records, queues the first chunk |
| `sqliimport.chunk` | Processes up to `ChunkSize` records from a cursor, then queues the next chunk or the finish |
| `sqliimport.finish` | Clears the view caches of the changed objects, indexes them, writes the summary and notifies the schedule's addresses about failures |

Rules that follow from this:

- **One run per import at a time.** A new run of an import is refused while another one is queued or running.
- **Different imports run in parallel**, each in its own resource. This replaces the single global lock of 1.x.
- **The chunks of a run run one after another**, so records are processed in their order.
- **Checkpoints:** a chunk saves its cursor every 20 records. If a worker dies, the next attempt continues after
  the last checkpoint, and a record that already has its row is not processed twice.
- **Memory stays flat:** the kernel's in-memory caches are emptied between chunks. The peak is recorded on the run.
- The run is processed as the user who started it, or as the robot user of the import (`owner_id` of the target).

The job types and their time limits are set in `expqueue.ini [JobType_sqliimport.*]` (timeout 900 seconds for the
start, 1800 for a chunk and the finish, three attempts each).

## Statuses

| Status | Meaning |
|---|---|
| `queued` | Waiting for a worker |
| `running` | A worker is processing it |
| `completed` | Ended, no record failed |
| `completed_with_errors` | Ended, some records failed |
| `failed` | The run stopped: the source could not be read, the mapping is invalid, or a job failed every attempt |
| `cancelled` | Cancelled by a user, or its job group was cancelled in the job queue |

The cron part `sqliimport` ends runs whose jobs ended without them (a job in the dead letter list, a job group
cancelled on the job queue's page).

## Kinds

| Kind | What it does |
|---|---|
| `import` | Processes all records (or `max_rows`) |
| `dry_run` | Publishes nothing. Reads the first `[RunSettings] DryRunRows` records and records what would happen, with the changed fields. A disabled import can still have a dry run. Images and files are not downloaded. The preview step of the editor does the same in the request, without storing a run. |
| `retry` | Processes only the failed rows of an earlier run, from the records stored on those rows |

## Rows and log

Each record of a run gets one row: its reference, outcome (`created`, `updated`, `unchanged`, `skipped`, `failed`,
`processed`), object, version before and after, nodes added and a message. A failed row also keeps the record, so it
can be retried. The log keeps lines from `[RunSettings] LogLevel` upward (`debug`, `info`, `notice`, `warning`,
`error`), each with the record it is about.

## In the administration

The left menu of the Imports tab links the pages of the extension:

| Page | Address | What it shows |
|---|---|---|
| Overview | `sqliimport/list` | The dashboard (below) |
| New import | `sqliimport/edit/new` | The import editor ([editor.md](editor.md)) |
| Running runs, Failed runs | `sqliimport/list/(status)/active`, `sqliimport/list/(status)/failed` | The dashboard filtered by status |
| Schedules | `sqliimport/schedules` | Every schedule with its next runs ([schedules.md](schedules.md#the-schedules-page)) |
| Job queue | `expqueue/dashboard` | The job queue of expqueue |
| Catalogue | `sqliimport/catalogue` | The source types, handlers and transforms the site offers, with their options and status |
| Settings | `sqliimport/settings` | The settings as they stand, export and import of definitions ([settings.md](settings.md#the-settings-page)) |

The pages of 1.x are part of these: the 1.x import requests are on the dashboard, the 1.x scheduled imports on
Schedules; their old addresses redirect there ([migration-1x.md](migration-1x.md#after-the-upgrade)).

### The dashboard

**Imports** (`sqliimport/list`) is the dashboard:

- Key figures: imports, runs today, runs running or queued, runs failed in the last 24 hours, the next scheduled run.
- A **Run now** toolbar: choose an import and start it, and the **New import** button.
- The imports with their source, handler, last run and next run. **Run now** (for an enabled import) and **Dry run**
  are shown to users who may run the import; **Edit** and, under **More**, **Duplicate**, **Enable** or
  **Disable** and **Delete** to users who may manage it ([editor.md](editor.md#changing-copying-and-deleting)).
- The runs, newest first, filtered by status: `sqliimport/list/(status)/active`, `failed`, `completed` or
  `cancelled`, and by import: `sqliimport/list/(import)/<id>`.
- **1.x import requests** (shown when 1.x handlers are configured or requests exist; it was `sqliimport/items`):
  the form **Request import** (a 1.x handler and its options, one `name=value` per line; it was
  `sqliimport/addimport`), **Purge history** (the ended requests; manage), and each request with its status,
  progress and progression notes, a link to its run, and **Cancel** (pending) or **Interrupt** (running) for users
  who may run its handler. Older requests: `sqliimport/list/(legacyoffset)/<n>`.

The admin dashboard (`content/dashboard`) has a block **Imports** with the latest runs and their status, and a
warning with a link when runs failed or ended with errors in the last 24 hours (`dashboard.ini
[DashboardBlock_sqliimport]`, `NumberOfItems=5`).

### The run page

**The run page** (`sqliimport/run/<id>`) shows:

- The status, the progress, the duration and the memory peak, and a link to the run's jobs in the job queue.
- The outcomes as figures. Each one filters the records: `sqliimport/run/<id>/(outcome)/failed`.
- The records with their object, version and message. In a dry run, each record lists its changed fields with the
  current and the new value.
- The log.
- **Cancel** while the run has not ended, and **Retry failed rows** when it ended with failed rows.
- **Refresh**, which loads the page again.

While a run is queued or running, the page follows it live: every two seconds it fetches the status, the progress,
the counters and the new log lines, and shows them without loading the page again. When the run ends, the page loads
once more with the final records. If an update gets no answer (a network error, a restart of the server), the page
tries again a few times and then stays as it is; **Refresh** brings it up to date. The updates come from the ezjscore
server function `sqliimport::runstatus`, which only answers POST requests with the form token from users who may
see the imports.

Every change (run, dry run, cancel, retry) is a POST request to `sqliimport/action` with the form token.

## Cancel and retry

- **Cancel** removes the run's queued jobs at once and stops the running chunk between two records. Records already
  processed stay as they are.
- **Retry failed rows** starts a run of kind `retry` that processes only the failed records of the ended run. It is
  not available for 1.x handlers; run their import again instead.

From the command line: `./console ext:sqliimport:cancel <run id>` and `./console ext:sqliimport:retry <run id>`
([cli.md](cli.md)).

## Notifications

Who hears of a run that ended is set per import, in the editor's step Processing, under **Notifications**:

| Setting | What it does |
|---|---|
| Tell when a run | `failed or had errors` (the default), `failed`, `ended (every run)` or `never` |
| A notice for the user who started the run | the user sees it on the Imports dashboard (**New since you last looked**) until the run's page is opened or **Mark all as read** is used (default: on) |
| E-mail addresses | one per line; they get a mail with the counts, the first errors and the link to the run |

Dry runs and undo runs give their starter a notice, never a mail. The addresses of a schedule (2.0.0-alpha.1) still
get a mail when a scheduled run fails or ends with errors. The link in a mail uses `[AdminSettings] AdminURL` of
`sqliimport.ini` when it is set (e.g. `https://edit.example.org/admin`), else a path.

A page can show the notices too, e.g. a bell in the header:

```
{def $sq_bell = fetch( 'sqliimport', 'notices', hash( 'limit', 5 ) )}
{if $sq_bell.count}<a href={'/sqliimport/list'|ezurl}>{$sq_bell.count}</a>{/if}
```

The notices live in the table `sqliimport_notice` and go with their run when it is purged.

## Retention

Ended runs older than `[RunSettings] KeepDays` (90 by default) are removed with their rows, log and files by the
cron part, or with `./console ext:sqliimport:purge`. Removing an import keeps its runs until they are purged.
