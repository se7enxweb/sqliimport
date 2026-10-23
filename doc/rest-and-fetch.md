# Templates, scripts and other systems: fetch functions, server functions and the trigger

This page is for site builders and developers who want the state of the imports somewhere else: in a template (a
block on a dashboard, a warning in a page layout), in the browser (the live run page, the bell), in a script or a
monitoring check, or in another system that starts imports. Every interface here is read-only, except the trigger,
which starts runs.

German: [ger-de/rest-and-fetch.md](ger-de/rest-and-fetch.md).

## Table of contents

- [Overview](#overview)
- [Template fetch functions](#template-fetch-functions)
  - [recent_runs](#recent_runs)
  - [failed_runs_24h](#failed_runs_24h)
  - [notices](#notices)
  - [The fields of a run](#the-fields-of-a-run)
  - [A complete example](#a-complete-example)
- [ezjscore server functions](#ezjscore-server-functions)
  - [sqliimport::runstatus](#sqliimportrunstatus)
  - [sqliimport::notices](#sqliimportnotices)
- [Starting a run from another system](#starting-a-run-from-another-system)
- [The command line for scripts and monitoring](#the-command-line-for-scripts-and-monitoring)
- [Related pages](#related-pages)

## Overview

| Interface | Address or call | Reads or writes | Who may |
|---|---|---|---|
| Template fetch functions | `fetch( 'sqliimport', … )` | reads | users with the policy sqliimport/view (Run imports, Manage imports or a 1.x policy); others get an empty result |
| ezjscore server functions | `ezjscore/call/sqliimport::runstatus::…`, `sqliimport::notices::…` | reads | the same users; runstatus only as a POST with the form token |
| Webhook / API trigger | `POST /<siteaccess>/sqliimport/trigger/<identifier>` | starts a run | a signed call or an API key of that import ([triggers.md](triggers.md)) |
| Command line | `./console ext:sqliimport:list`, `status`, `report` (with `--json`) | reads (and runs, with `run`) | whoever runs the console on the server |

The extension has no routes in the kernel's REST API (ezprestapi); the trigger is a module view of its own. Why, and
how it is secured: [triggers.md](triggers.md#the-address).

## Template fetch functions

Defined in `modules/sqliimport/function_definition.php`, implemented by `expImportFunctionCollection`. All three
return an empty result (an empty list, `0`, or a count of `0`) when the tables are not installed or the current user
may not see the imports, so a template can use them without its own permission check.

### recent_runs

```
fetch( 'sqliimport', 'recent_runs', hash( 'limit', 5 ) )
```

| Parameter | Type | Default | Meaning |
|---|---|---|---|
| `limit` | integer | `5` | How many runs, newest first; at most 50 |

Returns a list of runs, each a hash with [the fields of a run](#the-fields-of-a-run). All runs of all imports are
included (dry runs too), as on the dashboard.

### failed_runs_24h

```
fetch( 'sqliimport', 'failed_runs_24h' )
```

No parameters. Returns a number: how many runs failed or ended with errors in the last 24 hours, the same figure as
the dashboard's **Failed in 24 hours**.

### notices

```
fetch( 'sqliimport', 'notices', hash( 'limit', 10 ) )
```

| Parameter | Type | Default | Meaning |
|---|---|---|---|
| `limit` | integer | `10` | How many notices; at most 50 |

Returns a hash for the current user: `count`, the number of unread notices, and `items`, the newest unread notices.
Each item has `id`, `run_id`, `import_id`, `import` (the import's name), `kind` (`failed`, `errors`, `cancelled` or
`completed`), `created` (a Unix time), `seen` and `url` (`/sqliimport/run/<run id>`). A notice is made when a run
ends, for the user who started it, as set in the import's **Notifications** ([runs.md](runs.md#notifications)). The
bell of the header shows the same data.

### The fields of a run

The items of `recent_runs` (and the answer of [runstatus](#sqliimportrunstatus)) have these keys:

| Key | Example (run #119) | Meaning |
|---|---|---|
| `id`, `import_id`, `import_name` | `119`, `76`, `Demo: Books into a data table` | The run and its import |
| `status`, `status_label`, `status_kind` | `completed`, `Completed`, `is-ok` | `queued`, `running`, `completed`, `completed_with_errors`, `failed`, `cancelled`; the label as shown; a CSS modifier |
| `kind`, `kind_label` | `import`, `Import` | `import`, `dry_run`, `retry`, `undo` |
| `trigger`, `trigger_label` | `manual`, `Started by hand` | `manual`, `schedule`, `cli`, `rest`, `legacy` |
| `percent`, `total`, `done` | `100`, `3`, `3` | The progress |
| `created`, `updated`, `unchanged`, `skipped`, `failed`, `processed`, `undone` | `0`, `1`, `2`, `0`, `0`, `0`, `0` | The counters of the outcomes |
| `queued_at`, `started_at`, `finished_at` | Unix times | When |
| `duration`, `memory` | `0 s`, `26` | The duration as text, the memory peak in MB |
| `user` | the login of the user who started it (empty when that user no longer exists) | |
| `is_final` | `true` | Whether the run has ended |
| `parent_run_id` | `0` | For a retry or an undo: the run it belongs to |
| `queue_group`, `pct_class` | | For the administration's own pages |

### A complete example

A small block for a dashboard or a page layout of the administration, with a warning when runs failed:

```
{def $sq_runs = fetch( 'sqliimport', 'recent_runs', hash( 'limit', 3 ) )
     $sq_failed = fetch( 'sqliimport', 'failed_runs_24h' )
     $sq_bell = fetch( 'sqliimport', 'notices', hash( 'limit', 3 ) )}
{if $sq_failed}<p><a href={'/sqliimport/list/(status)/failed'|ezurl}>{$sq_failed} import runs failed in the last 24 hours</a></p>{/if}
<ul>
{foreach $sq_runs as $r}
  <li><a href={concat( '/sqliimport/run/', $r.id )|ezurl}>#{$r.id}</a> {$r.import_name|wash}:
      {$r.status_label|wash}, {$r.kind_label|wash}, {$r.trigger_label|wash},
      {$r.done}/{$r.total} records ({$r.created} created, {$r.updated} updated, {$r.unchanged} unchanged,
      {$r.failed} failed) in {$r.duration|wash}</li>
{/foreach}
</ul>
{if $sq_bell.count}<p>{$sq_bell.count} new import notices</p>{/if}
{undef $sq_runs $sq_failed $sq_bell}
```

Rendered on the test installation for an administrator (the template above, unchanged), the output was:

```html
<p><a href="/sqliimport/list/(status)/failed">7 import runs failed in the last 24 hours</a></p><ul>  <li><a href="/sqliimport/run/202">#202</a> Docs: First import (teas CSV):
      Queued, Import, REST API,
      0/0 records (0 created, 0 updated, 0 unchanged,
      0 failed) in 0 s</li>  <li><a href="/sqliimport/run/201">#201</a> Docs: First import (teas CSV):
      Completed, Undo, Started by hand,
      1/1 records (0 created, 0 updated, 0 unchanged,
      0 failed) in 1 s</li>  <li><a href="/sqliimport/run/200">#200</a> Docs: First import (teas CSV):
      Completed, Import, Started by hand,
      7/7 records (0 created, 1 updated, 6 unchanged,
      0 failed) in 0 s</li></ul>
```

The newest run (#202) had just been queued by the trigger (`REST API`) and had not started; an undo run (#201) is
listed like any other. There is no line for the notices, because that user had none unread (`count` 0). For a user without the policy, the block
shows nothing. Always pass names through `|wash`: an import's name is typed by an editor.

These functions are what the extension's own templates use: the block **Imports** of the admin dashboard
(`recent_runs`, `failed_runs_24h`) and, on a kernel before 6.0.15 without the header slot, a bell made by hand
(`notices`).

## ezjscore server functions

`expImportServerFunctions`, registered in `settings/ezjscore.ini.append.php` as `[ezjscServer_sqliimport]`. They
answer JSON through `ezjscore/call/…` (add `?ContentType=json`) and are what the administration's own scripts call.
Both are read-only and check the policy sqliimport/view.

### sqliimport::runstatus

```
POST ezjscore/call/sqliimport::runstatus::<run id>::<last log id>?ContentType=json
```

The live run page calls it every two seconds while a run is queued or running. It needs a **POST** with the form
token (`ezxform_token` in the body, or the header `X-CSRF-Token`); a GET is refused with "Only POST requests are
answered.". The answer is the run with [the fields of a run](#the-fields-of-a-run), plus `log`: up to 100 log lines
after `<last log id>` (`0` for all), each with `id`, `time`, `severity`, `ref` and `message`. For run #119:

```json
{
    "id": 119,
    "import_id": 76,
    "import_name": "Demo: Books into a data table",
    "status": "completed",
    "status_label": "Completed",
    "kind": "import",
    "trigger": "manual",
    "percent": 100,
    "total": 3,
    "done": 3,
    "created": 0,
    "updated": 1,
    "unchanged": 2,
    "failed": 0,
    "duration": "0 s",
    "is_final": true,
    "log": [
        { "id": 326, "time": "17:14:02", "severity": "info", "ref": "", "message": "run started: 3 records, handler datatable" },
        { "id": 327, "time": "17:14:02", "severity": "info", "ref": "", "message": "run ended completed: 0 created, 1 updated, 2 unchanged, 0 skipped, 0 failed in 0s" }
    ]
}
```

(shortened: the answer has every key of the table above). A run that does not exist, a missing token or a user who
may not see the imports gives an error answer of ezjscore with the message.

### sqliimport::notices

```
GET ezjscore/call/sqliimport::notices::<count>?ContentType=json
```

The feed of the bell in the header (admin4 and admin4l, Exponential 6.0.15). It answers a GET, because it only reads
the caller's own notices; `<count>` is 1 to 20 (8 by default). The answer:

```json
{
    "count": 0,
    "label": "Import notifications",
    "items": []
}
```

With unread notices, `label` becomes "Import notifications: 3 new" and each item is
`{ "text": "Run #42 · Products (CSV)", "url": "/admin/sqliimport/run/42", "meta": "failed · 10/22/2026 06:10 pm" }`.
A user without the policy gets `count` 0 and no items.

Both answers above were produced on the test installation, calling the functions as the documentation's test
administrator, runstatus as a POST with the form token.

## Starting a run from another system

The only interface that changes something: an import's webhook / API trigger. In short:

| Request | What it does | Who may |
|---|---|---|
| `POST /<siteaccess>/sqliimport/trigger/<identifier>` | Starts a run, optionally with the data in the body | a signed call (HMAC-SHA256 with the import's secret) or an API key |
| `GET /<siteaccess>/sqliimport/trigger/<identifier>/run/<run id>` | The status of a run of this import, with the same counters as above | an API key |

It is off for every import until it is switched on in the editor (step **Schedule**, section *Started by another
system*), runs as the trigger's run user, and answers JSON (`202` with the run's ID, `401`, `404`, `409`, `429` …).
Use the siteaccess where the extension is active. A call with an API key:

```sh
curl -sS -X POST "https://www.example.invalid/admin/sqliimport/trigger/books" \
     -H "Authorization: Bearer sq-demo-token-REPLACE" \
     -H "Content-Type: text/csv" --data-binary @books.csv
```

Signing, keys, answers, rate limits and the audit trail: [triggers.md](triggers.md).

## The command line for scripts and monitoring

On the server, the console gives the same information as JSON, for a cron check, a deployment script or an agent
that may only read:

```bash
./console ext:sqliimport:list --json            # every import with its last run
./console ext:sqliimport:status 119 --json      # one run with its counters and log
./console ext:sqliimport:report 119 > run.csv   # the rows of a run as CSV
```

`list --json` gives, per import, `id`, `identifier`, `name`, `source`, `handler`, `enabled` and `last_run`
(`id`, `status`), for example:

```json
{ "id": 76, "identifier": "demo_books_datatable", "name": "Demo: Books into a data table", "source": "csv",
  "handler": "datatable", "enabled": true, "last_run": { "id": 119, "status": "completed" } }
```

A monitoring check can alert when `failed_runs_24h` is not 0, or when an import's `last_run.status` is `failed`.
All commands: [cli.md](cli.md).

An AI agent connected through the installation's read-only MCP server (the extension expmcp) can call template fetch
functions with its tool `exp_fetch`, but only of the modules the server knows. On the test installation its
`exp_fetch` did not list the module `sqliimport` when this page was written; until it does, give such an agent the
console commands above.

## Related pages

- [triggers.md](triggers.md): the webhook / API trigger in full
- [runs.md](runs.md): runs, notifications, the bell
- [statistics.md](statistics.md): the figures over time
- [cli.md](cli.md): every command
