# Schedules

A schedule starts an import by itself (table `sqliimport_schedule`, class `expImportSchedule`). In the
administration, a schedule is set in the schedule step of the import editor ([editor.md](editor.md#step-5-schedule)),
which keeps one schedule per import; an import created in PHP can have several.

## The Schedules page

**Schedules** in the left menu of the Imports tab (`sqliimport/schedules`) lists every schedule of the 2.x imports:

| Column | Shows |
|---|---|
| Import | The import, linked to its runs on the dashboard; "disabled" when the import is switched off |
| When | The schedule in words, and whether it is active or paused |
| Next runs | The next three run times of an active schedule |
| Last run | The last run, or "never" |

Users with the policy `manage` ([permissions.md](permissions.md)) also get these actions:

| Action | Effect |
|---|---|
| Pause | Switches the schedule off; it is kept with its settings |
| Resume | Switches it on again and computes its next run from now |
| Edit | Opens the schedule step of the import in the editor |
| Delete | Removes the schedule after a confirmation; the import stays |

Every action is a POST request with the form token and is recorded in the audit log. A disabled import keeps its
schedule, but its scheduled runs are skipped until it is enabled again.

When scheduled imports of 1.x have not been migrated yet, the page says how many there are and links their 1.x
list; they keep running until `./console ext:sqliimport:migrate-1x --apply` moves them here
([migration-1x.md](migration-1x.md)).

## Kinds

| `kind` | Runs | Uses |
|---|---|---|
| `once` | Once, at `run_at` | `run_at` |
| `interval` | Every `interval_minutes` minutes, counted from `run_at` (at least 5 minutes) | `run_at`, `interval_minutes` |
| `hourly` | Every hour at the minute of `run_at` | `run_at` |
| `daily` | Every day at the time of day of `run_at` | `run_at` |
| `weekly` | Every week on the weekday and at the time of `run_at` | `run_at` |
| `monthly` | Every month on the day and at the time of `run_at`; on the last day in a shorter month | `run_at` |
| `cron` | As a five-field cron expression | `cron_expr` |

## Cron expressions

Five fields: minute, hour, day of the month, month, day of the week. Supported:

- `*` and `?`, lists (`1,15`), ranges (`1-5`), steps (`*/10`, `0-30/5`);
- names of months (`jan` to `dec`) and days (`sun` to `sat`); day `0` and `7` are Sunday;
- the shortcuts `@hourly`, `@daily`, `@midnight`, `@weekly`, `@monthly`, `@yearly` and `@annually`.

When both the day of the month and the day of the week are restricted, either one matches, as in cron.

Examples:

| Expression | Runs |
|---|---|
| `30 2 * * *` | Every day at 02:30 |
| `0 */4 * * *` | Every four hours |
| `0 6 * * mon-fri` | Weekdays at 06:00 |
| `15 3 1 * *` | The first of every month at 03:15 |

## Time zones and daylight saving time

Every schedule has a `timezone` (default `UTC`). Times of day are those of that time zone, so "daily at 02:30"
stays at 02:30 local time across a change of daylight saving time:

- A time that does not exist on the day of the change (02:30 when clocks jump from 02:00 to 03:00) is skipped that
  day.
- A time that exists twice (clocks go back) runs once.

## Missed runs and notifications

- `catch_up` decides what happens when runs were missed, for example because the cron did not run: `one` (the
  default) runs once now; `skip` waits for the next time if the run is late by more than ten minutes.
- `notify_json` is a list of e-mail addresses. They get a message when a run of the schedule fails or ends with
  failed records. The sender is `site.ini [MailSettings] EmailSender`, else `AdminEmail`.
- A `once` schedule switches itself off after its run.
- A schedule whose import was removed is switched off.
- If the import still runs when its schedule is due, the new run is not started; the cron part reports it and the
  schedule waits for its next time.

## The cron part

The cron part `sqliimport` must run every minute:

```bash
* * * * * cd /path/to/exponential && php runcronjobs.php -q sqliimport
```

Each round (`expImportScheduler::tick()`):

1. ends runs whose jobs ended without them;
2. queues the runs of the schedules that are due (the runs are processed by the job queue's workers, cron part
   `expqueue`);
3. hands pending 1.x import requests and due 1.x scheduled imports to the 2.x engine;
4. removes ended runs older than `[RunSettings] KeepDays`.

`./console ext:sqliimport:tick` runs one round by hand and prints what it did.

The 1.x cron parts `sqliimport_run` and `sqliimport_cleanup` are deprecated and kept for one major version. With the
job queue installed, `sqliimport_run` hands the 1.x imports to the 2.x engine, as the part `sqliimport` does; without
it, it runs them as 1.x did. Replace them with `sqliimport`.

## Creating a schedule in PHP

A script that sets up imports can add a schedule without the editor:

```php
<?php
$import = expImportDefinition::fetchByIdentifier( 'news_feed' );
$zone = new DateTimeZone( 'Europe/Berlin' );
$schedule = new expImportSchedule( array(
    'import_id' => $import->attribute( 'id' ),
    'kind' => 'daily',
    'run_at' => ( new DateTime( '2026-11-01 02:30', $zone ) )->getTimestamp(),
    'interval_minutes' => 0,
    'cron_expr' => '',
    'timezone' => 'Europe/Berlin',
    'catch_up' => 'one',
    'notify_json' => json_encode( array( 'webmaster@example.com' ) ),
    'is_active' => 1,
    'last_run' => 0,
    'created' => time(),
    'modified' => time(),
    'creator_id' => eZUser::currentUserID(),
) );
$errors = expImportCron::validate( $schedule->calcArray() );   // empty when the schedule is valid
$schedule->computeNext();
$schedule->store();
```

Scheduled runs run as the schedule's creator, or as the robot user of the import.
