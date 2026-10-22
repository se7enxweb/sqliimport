# Command line

All commands are `./console ext:sqliimport:<name>`, run from the installation's root. Each one is a small file in
`extension/sqliimport/bin/php/` that calls `expImportCommand`. `--help` prints the options of a command.

Exit codes: `0` success, `1` failure or a bad argument, `2` nothing to do.

Started as root, a command switches to the site's user (when expqueue offers that) before it reads a setting or opens
the database, so nothing it writes belongs to root. Every command except `install-schema` stops with a message if the
tables are missing.

## install-schema

Creates or upgrades the tables of 7x SQLI Import. It is idempotent and also upgrades the tables of 1.x.

```bash
./console ext:sqliimport:install-schema                 # create what is missing (default)
./console ext:sqliimport:install-schema --check         # only report what is missing
./console ext:sqliimport:install-schema --print=mysql   # print the SQL: mysql, postgresql, sqlite or oracle
./console ext:sqliimport:install-schema --write-files   # rewrite share/db_schema.dba and sql/*/schema.sql
```

## list

Lists the imports with their ID, identifier, source, handler and last run.

```bash
./console ext:sqliimport:list
./console ext:sqliimport:list --json
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

## status

Without an argument: the recent runs. With a run ID: that run and its log.

```bash
./console ext:sqliimport:status
./console ext:sqliimport:status 12 --limit=50
./console ext:sqliimport:status 12 --json
```

A run line reads: `run #12 completed (import, cli): 100% of 250; created 10, updated 3, unchanged 237, skipped 0,
failed 0; 41s, 38.2 MB`.

## cancel

```bash
./console ext:sqliimport:cancel 12
```

Cancels a run. Exit code `2` if it had ended already.

## retry

```bash
./console ext:sqliimport:retry 12 --wait
```

Starts a run of the failed rows of an ended run. It is refused when the run has no failed rows, or when its rows
carry no record (a 1.x handler).

## migrate-1x

Migrates the handlers, scheduled imports and history of SQLI Import 1.x. Without `--apply` it only reports.

```bash
./console ext:sqliimport:migrate-1x                   # dry run: what would be done
./console ext:sqliimport:migrate-1x --apply           # migrate
./console ext:sqliimport:migrate-1x --apply --roles   # also give roles with manageimports the policy manage
```

See [migration-1x.md](migration-1x.md).

## tick

One round of the cron part `sqliimport`: ends runs whose jobs ended, queues due schedules and pending 1.x imports,
and purges old runs. It prints the counters as JSON.

```bash
./console ext:sqliimport:tick
```

## purge

Removes ended runs older than `[RunSettings] KeepDays`, with their rows, log and files.

```bash
./console ext:sqliimport:purge --dry-run     # only count
./console ext:sqliimport:purge --days=30
```

## The 1.x script

`extension/sqliimport/bin/php/sqlidoimport.php` of 1.x is kept. It runs a 1.x handler directly, outside the job
queue. Prefer `run`.
