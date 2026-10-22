# Troubleshooting

## The Imports tab is missing

- The extension is not active in this siteaccess: check `ActiveExtensions[]` or `ActiveAccessExtensions[]`.
- The siteaccess resets `[TopAdminMenu] Tabs[]` in its own `menu.ini.append.php`. Add `Tabs[]=sqliimport` there.
- The INI cache is stale: `php bin/php/ezcache.php --clear-tag=ini`.
- The user lacks `sqliimport/view` (or a 1.x policy): [permissions.md](permissions.md).

## "The tables of 7x SQLI Import are missing"

Run `./console ext:sqliimport:install-schema`. `--check` lists what is missing.

## "The job queue (expqueue) is not installed"

Activate the extension expqueue and run `./console ext:expqueue:install-schema`.

## A run stays queued

The workers of the job queue process the runs. Check that the cron part `expqueue` runs every minute (or that its
systemd service runs), and look at the job queue's dashboard (`expqueue/dashboard`, linked from the Imports tab).
To process a run at once from the command line:

```bash
./console ext:sqliimport:run my_import --wait
```

## "The import already runs (run #12)"

One import runs once at a time. Wait for the run to end, or cancel it:
`./console ext:sqliimport:cancel 12`. A run that stays `running` although its job failed every attempt is ended by
the cron part `sqliimport`; check that it runs every minute, or run `./console ext:sqliimport:tick`.

## Schedules do not start

- The cron part `sqliimport` must run every minute: `php runcronjobs.php -q sqliimport`.
- The schedule may be inactive (a `once` schedule switches itself off after its run).
- With `catch_up` = `skip`, a run late by more than ten minutes waits for the next time.
- If the import is disabled, its scheduled run is skipped; `tick` prints the reason.

## The run failed at once

Look at the run's log (`./console ext:sqliimport:status <run id>` or the run page). Common messages:

| Message | Cause |
|---|---|
| `the uploaded source file is missing; upload it again` | The upload was removed from the storage directory |
| `the path is outside the directories ... AllowedPaths[] allows` | Add the directory to `[SourceSettings] AllowedPaths[]` |
| `the host ... is in a private or reserved network` | An intranet address; see `[SourceSettings] AllowPrivateHosts` |
| `only http and https addresses can be read` | Another scheme was used |
| `the download is larger than [SourceSettings] MaxSourceMegabytes` | Raise the limit, or deliver the file in parts |
| `the mapping is not valid: ...` | The mapping names a class or an attribute that does not exist, or lacks a match rule or a parent |
| `unknown handler '...'` | The handler is not registered, or its class is not autoloaded |

## Records fail

Filter the run page by **Failed** (`sqliimport/run/<id>/(outcome)/failed`) to see each message. Typical causes:

- `the remote ID needs id, which the record does not have`: the pattern names a field the record lacks. Check the
  field names; CSV headers are case sensitive.
- `the required attribute '...' has no value`: give a default, or let the record fail on purpose.
- `the parent of the record does not exist (yet)`: the parent comes later in the source, or not at all. Sort the
  source so parents come first.
- `lookup: no object with the remote ID '...'`: the related object was not imported yet.

Fix the cause, then use **Retry failed rows** (or `./console ext:sqliimport:retry <run id>`).

## Records are missing from a run

Two records with the same key are processed once: the second is not processed. Make the source's `key` field unique,
or leave it empty so records are named by their position.

## Nothing is updated

- `update_policy` = `create_only` skips existing objects.
- Objects in the trash are skipped.
- The rows say `unchanged`: no field differs from the published version, so no version was made. A dry run shows the
  compared values.

## A 1.x handler

- Its rows have the outcome `processed`; the engine does not know what the handler did. Read its progression notes
  in the run's log.
- "Retry failed rows" is not available. Run the import again.
- 1.x import requests made on `sqliimport/addimport` wait until the cron part `sqliimport` (or the deprecated
  `sqliimport_run`) hands them to the job queue. If one stays pending, check that a cron part runs.

## Memory and time

A chunk processes `[RunSettings] ChunkSize` records (200 by default) and may take up to 1800 seconds
(`expqueue.ini [JobType_sqliimport.chunk] Timeout`). Lower the import's `chunk_size` for heavy records, such as
records with images to download.
