# Schedules

A schedule starts an import by itself (table `sqliimport_schedule`, class `expImportSchedule`). In the
administration, a schedule is set in the schedule step of the import editor ([editor.md](editor.md#step-5-schedule)),
which keeps one schedule per import; an import created in PHP can have several.

German: [ger-de/schedules.md](ger-de/schedules.md).

## Contents

- [The Schedules page](#the-schedules-page)
- [Kinds](#kinds)
- [Cron expressions](#cron-expressions)
- [Time zones and daylight saving time](#time-zones-and-daylight-saving-time)
- [Missed runs and notifications](#missed-runs-and-notifications)
- [Examples: one schedule of each kind](#examples-one-schedule-of-each-kind)
  - [Once](#once)
  - [Every N minutes, with "wait for the next time"](#every-n-minutes-with-wait-for-the-next-time)
  - [Every hour](#every-hour)
  - [Every day across the change to summer time (March)](#every-day-across-the-change-to-summer-time-march)
  - [Every day across the change to winter time (October)](#every-day-across-the-change-to-winter-time-october)
  - [Every week](#every-week)
  - [Every month, on the 31st](#every-month-on-the-31st)
  - [A cron expression, with an e-mail on failure](#a-cron-expression-with-an-e-mail-on-failure)
- [Example: pause and resume on the Schedules page](#example-pause-and-resume-on-the-schedules-page)
- [Why the times are not those of the schedule](#why-the-times-are-not-those-of-the-schedule)
- [The cron part](#the-cron-part)
- [Creating a schedule in PHP](#creating-a-schedule-in-php)

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

Below, the section **1.x scheduled imports** (it was `sqliimport/scheduledlist`) lists the scheduled imports of
1.x with their handler, options, frequency and next run: **Run now** (requests an import with its options; run),
**Edit**, **Enable** or **Disable** and **Delete** (manage), and **New 1.x scheduled import**. Edit opens the form
`sqliimport/schedules/(legacy)/<id>` (or `/new`; it was `sqliimport/addscheduled`) above the lists: label, handler,
options, first run (date, hour, minute), frequency (once, hourly, daily, weekly, monthly, every N minutes, at least
5) and whether it is active. A migrated one links to its import's schedule instead. They keep running until
`./console ext:sqliimport:migrate-1x --apply` moves them here
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

## Examples: one schedule of each kind

Every example below was saved in the schedule step of the import "Docs: First import (teas CSV)" on the
documentation server (`sqliimport/edit/132/(step)/schedule`), with **Save**, which stays on the step and lists the
**next five runs**. The start dates lie in the future, so none of them came due while the screenshots were taken;
afterwards the import's schedule was set back to its cron expression and paused.

**Read the times with two time zones in mind.** *Date and time* and the schedule are in the schedule's *Time zone*
(here Europe/Berlin). The **Next runs** list, the Schedules page and every other date of the administration are
shown in the time zone the administration runs in: the documentation server runs in America/Los_Angeles, nine
hours behind Berlin (eight while one of the two has summer time and the other not). So "08:00 in Berlin" is listed as
"11:00:00 pm" of the day before. See [Why the times are not those of the schedule](#why-the-times-are-not-those-of-the-schedule).

### Once

| Field | Value |
|---|---|
| Runs by itself | Once, at the date and time below |
| Date and time | `11/02/2026, 08:00 AM` |
| Time zone | `Europe/Berlin` |
| When runs were missed | Run once as soon as possible |

Next runs: **Sunday November 01 2026 11:00:00 pm** (08:00 on 2 November in Berlin). One run, after which the schedule
switches itself off.

![The schedule step with Once at 11/02/2026 08:00 Europe/Berlin and its one next run, Sunday November 01 2026 11:00:00 pm](images/sched-once.png)

### Every N minutes, with "wait for the next time"

| Field | Value |
|---|---|
| Runs by itself | Every N minutes, from the date and time below |
| Date and time | `11/02/2026, 08:00 AM`, Europe/Berlin |
| Every how many minutes | `15` (at least 5) |
| When runs were missed | **Wait for the next time** (`skip`) |

Next runs: November 01 2026 11:00, 11:15, 11:30, 11:45 pm and November 02 12:00 am. With *Wait for the next time*, a
run that is more than ten minutes late (the cron did not run, the server was down) is not made up: the schedule
waits for its next quarter hour. With *Run once as soon as possible* (`one`), one run is made at once, however many
were missed.

![The schedule step with every 15 minutes from 11/02/2026 08:00 Europe/Berlin, wait for the next time, and the next five runs a quarter of an hour apart](images/sched-interval.png)

### Every hour

Runs by itself: *Every hour, at the minute of the time below*; `11/02/2026, 08:20 AM`, Europe/Berlin. Only the minute
counts after the first run: the next runs are 11:20 pm, 12:20 am, 01:20 am, 02:20 am and 03:20 am (Los Angeles).

![The schedule step with every hour at minute 20 and the next five runs on the hour plus 20 minutes](images/sched-hourly.png)

### Every day across the change to summer time (March)

| Field | Value |
|---|---|
| Runs by itself | Every day, at the time below |
| Date and time | `03/25/2027, 02:30 AM` |
| Time zone | `Europe/Berlin` |

In Berlin the clocks jump from 02:00 to 03:00 on Sunday 28 March 2027, so 02:30 does not exist that day. The next runs
listed:

```
Wednesday March 24 2027 06:30:00 pm    = Thursday 25 March, 02:30 in Berlin
Thursday March 25 2027 06:30:00 pm     = Friday 26 March, 02:30
Friday March 26 2027 06:30:00 pm       = Saturday 27 March, 02:30
Sunday March 28 2027 05:30:00 pm       = Monday 29 March, 02:30 (summer time; Sunday 28 March is skipped)
Monday March 29 2027 05:30:00 pm       = Tuesday 30 March, 02:30
```

Sunday's run is skipped, as [Time zones and daylight saving time](#time-zones-and-daylight-saving-time) says, and
from Monday the run stays at 02:30 Berlin time; in Los Angeles (already on summer time since 14 March) it moves from
06:30 pm to 05:30 pm.

![The schedule step with every day at 02:30 Europe/Berlin from 03/25/2027 and the next five runs, in which Sunday 28 March is missing](images/sched-daily-dst-march.png)

### Every day across the change to winter time (October)

The same schedule from `10/24/2026, 02:30 AM`. In Berlin the clocks go back from 03:00 to 02:00 on Sunday 25 October
2026, so 02:30 happens twice that night; the run is made once, at the first 02:30 (still summer time):

```
Friday October 23 2026 05:30:00 pm     = Saturday 24 October, 02:30 in Berlin (summer time)
Saturday October 24 2026 05:30:00 pm   = Sunday 25 October, the first 02:30 (once)
Sunday October 25 2026 06:30:00 pm     = Monday 26 October, 02:30 (winter time)
Monday October 26 2026 06:30:00 pm
Tuesday October 27 2026 06:30:00 pm
```

![The schedule step with every day at 02:30 Europe/Berlin from 10/24/2026 and the next five runs, one run on the night the clocks go back](images/sched-daily-dst-october.png)

### Every week

*Every week, on the weekday and at the time below*: `11/04/2026, 07:00 AM` (a Wednesday), Europe/Berlin. Next runs:
Tuesday November 03, 10, 17, 24 and December 01 2026, 10:00 pm, each Wednesday 07:00 in Berlin.

![The schedule step with every week on Wednesday 07:00 Europe/Berlin and the next five runs a week apart](images/sched-weekly.png)

### Every month, on the 31st

*Every month, on the day and at the time below*: `01/31/2027, 06:00 AM`, Europe/Berlin. A month without a 31st runs
on its last day, and the next month goes back to the 31st:

```
Saturday January 30 2027 09:00:00 pm   = 31 January, 06:00 in Berlin
Saturday February 27 2027 09:00:00 pm  = 28 February (the last day)
Tuesday March 30 2027 09:00:00 pm      = 31 March (summer time in both cities)
Thursday April 29 2027 09:00:00 pm     = 30 April (the last day)
Sunday May 30 2027 09:00:00 pm         = 31 May
```

![The schedule step with every month on the 31st at 06:00 Europe/Berlin and the next five runs, on the last day of February and April](images/sched-monthly.png)

### A cron expression, with an e-mail on failure

| Field | Value |
|---|---|
| Runs by itself | A cron expression |
| Cron expression | `0 6 * * 1-5` (06:00 on weekdays) |
| Time zone | `Europe/Berlin` |
| E-mail when a run fails | `imports@example.invalid` (one address per line) |

Next runs: Thursday October 22 2026 09:00 pm (Friday 06:00 in Berlin), then Sunday October 25 10:00 pm (Monday;
Berlin is on winter time from that night, Los Angeles not before 1 November, so the gap is eight hours), Monday 26,
Tuesday 27 and Wednesday 28 October 10:00 pm. Saturday and Sunday have no run.

When a scheduled run fails or ends with failed records, the addresses get a message with the subject
`[<site name>] Docs: First import (teas CSV): run #<id> <status>`, the run's counters, its first five errors and the
link to its run page. The sender is `site.ini [MailSettings] EmailSender`, else `AdminEmail`. Use an
address of the team that owns the import; `example.invalid` here is only a placeholder.

![The schedule step with the cron expression 0 6 * * 1-5, Europe/Berlin, an e-mail address for failures and the next five runs on weekdays](images/sched-cron-notify.png)

The same step in the dark theme, without the address:

![The schedule step with the cron expression in the dark theme](images/sched-cron-dark.png)

## Example: pause and resume on the Schedules page

The Schedules page lists both schedules of the documentation server: the php.net demo every 10 minutes, and the cron
schedule of "Docs: First import (teas CSV)":

![The Schedules page: Demo: PHP news every 10 minutes, active, with its next three runs and last run #203; Docs: First import cron 0 6 * * 1-5 (Europe/Berlin), active, with its next runs; each with Pause, Edit and Delete](images/sched-list-active.png)

**Pause** sends the action `schedule_pause` (a POST to `sqliimport/action` with the form token, `ScheduleID` and the
return address `RedirectURI=/sqliimport/schedules`). The page comes back with "The schedule of "Docs: First import
(teas CSV)" is changed": the row says *paused*, has no next runs, and offers **Resume**:

![The Schedules page after Pause: the message that the schedule is changed, and the Docs import paused with no next runs and the button Resume](images/sched-list-paused.png)

**Resume** sends `schedule_resume`: the schedule is active again and its next run is computed from now, so runs
missed while it was paused are not made up:

![The Schedules page after Resume: the Docs import active again with its next runs](images/sched-list-resumed.png)

Both actions are recorded in the audit log. On the documentation server the schedule was paused again afterwards, so
that this import never runs by itself.

## Why the times are not those of the schedule

A schedule is stored with its own time zone, and its run times are instants (Unix timestamps). The administration
shows every instant as a date of the time zone it runs in: PHP's time zone (`date.timezone`), which is the server's
when PHP sets none. The documentation server runs in America/Los_Angeles, so the Berlin schedule above is listed eight
or nine hours earlier, without a zone next to the time; the runs still happen at 06:00 in Berlin, and an installation
whose PHP runs in Europe/Berlin lists them at 06:00. The field *Date and time* of the schedule step is the exception:
it is in the schedule's own time zone, the one chosen next to it. When a list looks an hour off around the end of March
or October, check which of the two zones changed its clocks.

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
