# Statistics

7x SQLI Import keeps every run with its counters, its timings and a row per failed record
([runs.md](runs.md)). Two pages read that history: the dashboard shows the last 30 days at a glance, and the
Statistics page lets you look back over any period and compare it with another one.

German: [ger-de/statistics.md](ger-de/statistics.md).

## The dashboard (sqliimport/list)

Under the key figures, the block "The last 30 days" shows:

- **Runs**, the share of **runs without errors** (with a bar), the **records processed**, the **average
  duration** and the **slowest run** (a link to it).
- **Records per day**: one bar per day, stacked by outcome (created, updated, unchanged, skipped, failed). Hover
  a bar for its numbers; **Show as a table** lists them.
- **Imports with the most failures**: up to five imports with the runs that had errors, the share of failed
  records and the most common error. Each name opens the import in Statistics.

Dry runs and undo runs are not counted.

## The Statistics page (sqliimport/statistics)

Open it from the left menu of the Imports tab (**Statistics**). Who may see the Imports tab may see it (the same
policies as the other pages that only show: Manage imports, Run imports, or the 1.x policies).

### Period and filters

The form at the top is a plain GET form, so every view has an address you can bookmark or send:

| Field | Parameter | Values |
|---|---|---|
| Last | `days` | 7, 30 (default), 90, 365 |
| Or from / to | `from`, `to` | dates (YYYY-MM-DD); both together replace `days` |
| Import | `import` | the ID of an import |
| Source type | `source` | csv, xml, rss, json, xlsx ... |
| User | `user` | the ID of the user who started the runs |
| Runs | `scope` | `production` (imports and retries, default), `dry_run`, `all` (with dry runs and undo) |
| Compare with | `compare` | `previous` (the period before, default), `custom` (with `cfrom`, `cto`), `none` |

The same parameters work as view parameters, for example `sqliimport/statistics/(days)/90/(import)/3` or
`sqliimport/statistics/(from)/2026-08-01/(to)/2026-08-31/(compare)/custom/(cfrom)/2026-07-01/(cto)/2026-07-31`.
Days follow the time zone of the site; a run counts on the day it started.

### What the page shows

- **Key figures**: runs, runs without errors, records processed, failure rate of records, average duration, the
  duration 95 % of the runs stayed under (95th percentile), records per second.
- **Suggestions**, where the history points at something to tune:
  - *fails on the field X in N % of its records*: the failed records of an import name one field (an attribute,
    field or column in the error message) often enough;
  - *slow since DATE*: the time per record of an import stepped up for good (at least 1.5 times, clear of the
    noise) at a run; the date is the first slow run;
  - *N % of the runs ended with errors*, with the most common error;
  - *nearly every record was unchanged*: the import may run less often.
- **Trends** per day (periods up to 62 days), per week (up to 200 days) or per month: runs by result, records by
  outcome, the average duration of a run and the failure rate of records. Every chart has a legend and **Show as
  a table** with its numbers.
- **Per import**: a sparkline of the records per day, week or month, runs and runs with errors, average and 95th
  percentile duration, records per second, failure rate (a link to the failed runs) and the three most common
  errors. An import whose time per record stepped up carries the badge *slow since*.
- **Comparison**: every key figure of this period next to the compared one, with the change; green is better,
  red is worse.
- **Most common errors** of all imports. Numbers in the messages are shown as `#`, so the same error with another
  row number counts once.

**Table as CSV** downloads the per-import table (`(format)/csv`), one line per import, in UTF-8 with a byte order
mark. Cells that a spreadsheet would read as a formula get a leading apostrophe, as in the report of a run.

## How it is computed

The class `expImportStatistics` (classes/exp/expimportstatistics.php) reads the runs of the period once (one
narrow row per run, from `sqliimport_run`) and the messages of their failed records (`sqliimport_run_row`, the
newest 20,000; for runs that failed as a whole the errors of `sqliimport_run_log`). The queries are generic SQL
for MySQL, PostgreSQL, SQLite and Oracle; long message texts are never grouped in SQL. Days, weeks and months are
built in PHP in the site's time zone, so daylight saving time is right. A period reads at most 50,000 runs; a page
says so when it hits that limit.

The computing parts are pure functions on plain arrays (percentiles, rates, deltas, buckets, the change point,
the folding of messages, the suggestions) and are covered by `tests/unit/StatisticsTest.php`.
