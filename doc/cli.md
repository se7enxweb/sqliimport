# Command line

All commands are `./console ext:sqliimport:<name>`, run from the installation's root. Each one is a small file in
`extension/sqliimport/bin/php/` that calls `expImportCommand`. `--help` prints the options of a command.

German: [ger-de/cli.md](ger-de/cli.md).

Exit codes: `0` success, `1` failure or a bad argument, `2` nothing to do.

Started as root, a command switches to the site's user (when expqueue offers that) before it reads a setting or opens
the database, so nothing it writes belongs to root. Every command except `install-schema` stops with a message if the
tables are missing.

## Contents

- [Running the commands: user and siteaccess](#running-the-commands-user-and-siteaccess)
- [install-schema](#install-schema)
- [list](#list)
- [run](#run)
- [status](#status)
- [cancel](#cancel)
- [retry](#retry)
- [undo](#undo)
- [report](#report)
- [opml-feeds](#opml-feeds)
- [migrate-1x](#migrate-1x)
- [tick](#tick)
- [purge](#purge)
- [The 1.x script](#the-1x-script)

## Running the commands: user and siteaccess

Every example on this page is the real output of the command on the documentation server, run on the "Docs: ..."
imports. They were run as the site's user, with the siteaccess where 7x SQLI Import is active:

```bash
runuser -u alpha -- php ./console ext:sqliimport:<command> ... -s admin --allow-root-user
```

- `./console` prints the line `▶  running ext:sqliimport:run  →  extension/sqliimport/bin/php/run.php` first; it is
  left out below.
- **`-s <siteaccess>`** chooses the siteaccess. Where the extension is active only in the administration siteaccess
  (as on the documentation server), a command without `-s admin` reads the settings of the default siteaccess, which
  do not know the job types of 7x SQLI Import: `run` then queues a run that fails at once with "the run could not be
  queued: unknown job type sqliimport.run: register it in expqueue.ini as [JobType_sqliimport.run]
  Handler=<class>". Commands that only read (`list`, `status`) work either way. Activate the extension for every
  siteaccess, or pass `-s` to every command.
- Runs started on the command line are recorded with the trigger `cli` and the anonymous user (`user_id` 10); the
  Statistics page counts them under the user *anonymous*.
- `--allow-root-user` lets a command start as root (it then switches to the site's user, as said above); with `runuser` it changes nothing.

## install-schema

Creates or upgrades the tables of 7x SQLI Import. It is idempotent and also upgrades the tables of 1.x.

```bash
./console ext:sqliimport:install-schema                 # create what is missing (default)
./console ext:sqliimport:install-schema --check         # only report what is missing
./console ext:sqliimport:install-schema --print=mysql   # print the SQL: mysql, postgresql, sqlite or oracle
./console ext:sqliimport:install-schema --write-files   # rewrite share/db_schema.dba and sql/*/schema.sql
```

**Example** (`--check`, read only):

```
database engine: sqlite
PASS the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_notice, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled, sqliimport_trigger, sqliimport_trigger_call, sqliimport_trigger_key exist with every column and index
```

`--print=sqlite` starts:

```sql
CREATE TABLE sqliimport_definition (
  behaviour_json longtext,
  created INTEGER(11) NOT NULL DEFAULT '0',
  creator_id INTEGER(11) NOT NULL DEFAULT '0',
  def_version INTEGER(11) NOT NULL DEFAULT '0',
  description longtext,
  handler varchar(100) NOT NULL DEFAULT 'mapping',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifier varchar(100) NOT NULL DEFAULT '',
  ...
```

## list

Lists the imports with their ID, identifier, source, handler and last run.

```bash
./console ext:sqliimport:list
./console ext:sqliimport:list --json
```

**Example** (the first lines):

```
   3  demo-releases-json             json   mapping              last run #6 completed
  76  demo_books_datatable           csv    datatable            last run #119 completed
   4  demo-feeds-opml                opml   mapping              last run #7 completed
   2  demo-php-news-atom             rss    mapping              last run #203 completed
   1  demo-products-csv              csv    mapping              last run #4 completed
 123  docs-files-json                json   mapping              last run #168 completed
 122  docs-files-ndjson              ndjson mapping              last run #167 completed
 103  docs-web-sitemap-crawl         sitemap mapping              last run #145 completed_with_errors
```

With `--json`, one object per import:

```json
[
    {
        "id": 3,
        "identifier": "demo-releases-json",
        "name": "Demo: 7x SQLI Import releases (JSON address)",
        "source": "json",
        "handler": "mapping",
        "enabled": true,
        "last_run": {
            "id": 6,
            "status": "completed"
        }
    },
    ...
]
```

## run

Starts a run of an import, named by its identifier or ID. Without `--wait` the run is only queued; the workers of
the job queue process it.

```bash
./console ext:sqliimport:run news_feed
./console ext:sqliimport:run news_feed --dry-run --wait
./console ext:sqliimport:run 4 --max-rows=100 --wait
```

| Option | Effect |
|---|---|
| `--dry-run` | Publish nothing; record what would happen for the first `[RunSettings] DryRunRows` records |
| `--wait` | Process the run here, as a worker limited to this import, and print the result |
| `--max-rows=<n>` | Stop after this many records |
| `--json` | Print the run as JSON |

With `--wait`, the exit code is `0` when the run completed (with or without failed records) and `1` otherwise.

**Example: a dry run** of "Docs: Products (CSV)", whose three folders exist and are up to date:

```
$ ./console ext:sqliimport:run docs-files-csv --dry-run --wait -s admin
PASS run #208 of docs-files-csv queued (job group 7ec78f23-a4da-4235-9bbb-2141e5da3ce2)
run #208 completed (dry_run, cli): 100% of 3; created 0, updated 0, unchanged 3, skipped 0, failed 0; 1s, 28.0 MB
```

**Example: a real run** of "Docs: Books (XML)" (the two books were published, and undone afterwards, see
[undo](#undo)):

```
$ ./console ext:sqliimport:run docs-files-xml --wait -s admin
PASS run #209 of docs-files-xml queued (job group 657ccb46-58f3-4137-ab9f-ee68be3079d7)
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
```

**Example: `--json` without `--wait`** prints the queued run:

```json
{"id":211,"import_id":126,"run_kind":"dry_run","trigger_kind":"cli","status":"queued","total_count":0,"read_count":0,"created_count":0,"updated_count":0,"unchanged_count":0,"skipped_count":0,"failed_count":0,"processed_count":0,"user_id":10,"queue_group":"9f87231f-b9c9-4535-b430-593071910d0f","percent":0,"duration":0,"memory_peak_mb":0}
```

With `--wait` the same command prints the text lines above, not JSON; use `status <id> --json` after it.

**Without `-s admin`** on the documentation server:

```
FAIL the run could not be queued: unknown job type sqliimport.run: register it in expqueue.ini as [JobType_sqliimport.run] Handler=<class>
```

(exit code 1; the run is recorded as failed, see [Running the commands](#running-the-commands-user-and-siteaccess)).

## status

Without an argument: the recent runs. With a run ID: that run and its log.

```bash
./console ext:sqliimport:status
./console ext:sqliimport:status 12 --limit=50
./console ext:sqliimport:status 12 --json
```

A run line reads: `run #12 completed (import, cli): 100% of 250; created 10, updated 3, unchanged 237, skipped 0,
failed 0; 41s, 38.2 MB`: the run, its status, its kind and trigger, the progress, the counters, the duration and the
peak memory.

**Example: the recent runs** (`--limit=5`):

```
run #211 queued (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
run #210 completed (dry_run, cli): 100% of 3; created 3, updated 0, unchanged 0, skipped 0, failed 0; 1s, 28.0 MB
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
run #208 completed (dry_run, cli): 100% of 3; created 0, updated 0, unchanged 3, skipped 0, failed 0; 1s, 28.0 MB
run #207 failed (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
```

**Example: one run with its log** (times in the server's time zone):

```
$ ./console ext:sqliimport:status 209 -s admin
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
  18:43:41 info    run started: 2 records, handler mapping
  18:43:41 info    run ended completed: 2 created, 0 updated, 0 unchanged, 0 skipped, 0 failed in 0s
```

The failed run #207 (the run without `-s admin`) shows why in its log:

```
run #207 failed (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
  18:43:18 error   the run could not be queued: unknown job type sqliimport.run: register it in expqueue.ini as [JobType_sqliimport.run] Handler=<class>
```

**Example: `--json`** (shortened):

```json
{
    "id": 209,
    "import_id": 130,
    "run_kind": "import",
    "trigger_kind": "cli",
    "status": "completed",
    "total_count": 2,
    "read_count": 2,
    "created_count": 2,
    "updated_count": 0,
    "unchanged_count": 0,
    "skipped_count": 0,
    "failed_count": 0,
    "processed_count": 0,
    "user_id": 10,
    "queue_group": "657ccb46-58f3-4137-ab9f-ee68be3079d7",
    "percent": 100,
    "duration": 0,
    "memory_peak_mb": 32,
    "log": [
        { "severity": "info", "ref": "", "message": "run started: 2 records, handler mapping" },
        { "severity": "info", "ref": "", "message": "run ended completed: 2 created, 0 updated, 0 unchanged, 0 skipped, 0 failed in 0s" }
    ]
}
```

## cancel

```bash
./console ext:sqliimport:cancel 12
```

Cancels a run. Exit code `2` if it had ended already.

**Example:** run #211 (the queued dry run above) and run #209 (ended):

```
$ ./console ext:sqliimport:cancel 211 -s admin
PASS run #211 cancelled

$ ./console ext:sqliimport:cancel 209 -s admin
the run has ended already (completed)
```

The second exits with `2`.

## retry

```bash
./console ext:sqliimport:retry 12 --wait
```

Starts a run of the failed rows of an ended run. It is refused when the run has no failed rows, or when its rows
carry no record (a 1.x handler).

**Example:** run #145 of "Docs: Sitemap crawl (snapshot)" had one failed row, the page robots.txt does not allow;
the retry reads only that record, which fails again for the same reason. A run without failed rows is refused (exit
code 1):

```
$ ./console ext:sqliimport:retry 145 --wait -s admin
PASS run #213 queued for the failed rows
run #213 completed_with_errors (retry, manual): 100% of 1; created 0, updated 0, unchanged 0, skipped 0, failed 1; 0s, 22.0 MB

$ ./console ext:sqliimport:retry 209 --wait -s admin
FAIL the run has no failed rows
```

## undo

```bash
./console ext:sqliimport:undo 12 --wait
```

Undoes a run: what it created goes to the trash, what it updated gets the version before the run published again
as a new version. An object changed after the run is left alone and listed. A run is undone once, within
`[RunSettings] UndoDays` (or the import's own number of days). See [runs.md](runs.md).

**Example:** the real run #209 created two folders; undoing it moves them to the trash:

```
$ ./console ext:sqliimport:undo 209 --wait -s admin
PASS run #212 undoes run #209
run #212 completed (undo, manual): 100% of 2; created 0, updated 0, unchanged 0, skipped 0, failed 0; 1s, 22.0 MB
```

The run line of an undo run has no counter for undone records; its report (below) has one row per record with the
outcome `undone` and the message "moved to the trash".

## report

```bash
./console ext:sqliimport:report 12 > run-12.csv
./console ext:sqliimport:report 12 --outcome=failed --output=var/import/run-12-failed.csv
```

Writes the CSV report of a run, one line per record: `run_id`, `row`, `ref`, `outcome`, `status`, `object_id`,
`node_id`, `remote_id`, `version_before`, `version_after`, `changed_fields`, `message`, `time`. The rows are read in
pages, so a large run needs little memory. A cell a spreadsheet would read as a formula gets a leading apostrophe.
The run page offers the same report as **Download CSV report** (all records, or the outcome shown).

**Example:** the real run #209 and its undo #212:

```
$ ./console ext:sqliimport:report 209 -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
209,0,b1,created,ok,56643,55022,sq688doc-xml-b1,,1,,,2026-10-23T01:43:41Z
209,1,b2,created,ok,56644,55023,sq688doc-xml-b2,,1,,,2026-10-23T01:43:41Z

$ ./console ext:sqliimport:report 212 -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
212,0,b2,undone,ok,56644,55023,,1,,,moved to the trash,2026-10-23T01:44:11Z
212,1,b1,undone,ok,56643,55022,,1,,,moved to the trash,2026-10-23T01:44:12Z
```

Only the failed records of run #145:

```
$ ./console ext:sqliimport:report 145 --outcome=failed -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
145,3,https://example.org/about/,failed,error,,,,,,,robots.txt of the site does not allow reading https://example.org/about/,2026-10-23T00:50:18Z
```

Times are in UTC (ISO 8601 with `Z`).

## opml-feeds

```bash
./console ext:sqliimport:opml-feeds my-feed-list --parent=2345
```

Creates one disabled RSS import per feed of an OPML import's list, with the RSS preset as mapping. See
[sources.md](sources.md#opml-opml) and the worked example in
[source-examples-files.md](source-examples-files.md#one-feed-import-per-outline).

**Example:** the first call created three imports; a second call skips them:

```
$ ./console ext:sqliimport:opml-feeds docs-files-opml -s admin
SKIP docs-files-opml-example-news https://example.org/feed.rss (an import with this identifier exists)
SKIP docs-files-opml-example-blog https://example.org/blog/atom.xml (an import with this identifier exists)
SKIP docs-files-opml-example-releases https://example.net/releases.rss (an import with this identifier exists)
The feed imports are disabled: check their mapping in the administration, then enable them.
```

## migrate-1x

Migrates the handlers, scheduled imports and history of SQLI Import 1.x. Without `--apply` it only reports.

```bash
./console ext:sqliimport:migrate-1x                   # dry run: what would be done
./console ext:sqliimport:migrate-1x --apply           # migrate
./console ext:sqliimport:migrate-1x --apply --roles   # also give roles with manageimports the policy manage
```

**Example** (without `--apply`, nothing is changed): on the documentation server only the history of 1.x would be
moved:

```
history   WOULD the 1.x history is migrated
PASS dry run: nothing was changed (--apply to migrate)
```

See [migration-1x.md](migration-1x.md).

## tick

One round of the cron part `sqliimport`: ends runs whose jobs ended, queues due schedules and pending 1.x imports,
and purges old runs. It prints the counters as JSON. The tick also runs within the cron part `expqueue` of the job
queue, so a site where the job queue runs every minute needs no cron line of its own; a lock keeps two ticks from
running at once.

```bash
./console ext:sqliimport:tick
```

`tick` has no option that only reports: it always does its work. Its help (the options every script of the
installation has, no options of its own):

```
Usage: .../extension/sqliimport/bin/php/tick.php [OPTION]...
7x SQLI Import: One round of the cron part: due schedules, 1.x pending imports, ended jobs, purge.

General options:
  -h,--help             display this help and exit
  -q,--quiet            do not give any output except when errors occur
  -s,--siteaccess       selected siteaccess for operations,
                          if not specified default siteaccess is used
  ...
```

## purge

Removes ended runs older than `[RunSettings] KeepDays`, with their rows, log and files.

```bash
./console ext:sqliimport:purge --dry-run     # only count
./console ext:sqliimport:purge --days=30
```

**Example** (`--dry-run`, read only; the oldest run on the documentation server is a day old):

```
$ ./console ext:sqliimport:purge --dry-run -s admin
PASS 0 runs would be removed

$ ./console ext:sqliimport:purge --dry-run --days=30 -s admin
PASS 0 runs would be removed
```

## The 1.x script

`extension/sqliimport/bin/php/sqlidoimport.php` of 1.x is kept. It runs a 1.x handler directly, outside the job
queue. Prefer `run`.
