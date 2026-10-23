# Triggers: start an import from another system

Another system (a shop, a CRM, a build pipeline, a cron job on another server) can start an import by calling an
address of the site, and it may bring the data in the call. This is the webhook / API trigger of an import.

- **Off by default, per import.** Nothing answers until the trigger of that import is switched on in the editor
  (step **Schedule**, section *Started by another system*).
- **Two ways to prove the call:** a **signed call** (HMAC-SHA256 with the import's signing secret) or an **API key**
  of the import.
- **Never anonymous:** a triggered run runs as the trigger's **run user**, a user with the policy *Run imports* (or
  *Manage imports*) for the import's handler.
- **Data in the call (optional):** the body of the call becomes the data of that run, for imports that read a file.
- **Audited, rate limited, no session, no CORS.**

## Worked example on a test installation

This example starts the import "Docs: First import (teas CSV)" (identifier `docs-first-import`, import #132) from the
command line: once signed, once with an API key and new data, and once with a wrong key. All the calls and answers
below are real; only the signing secret and the API key are replaced by placeholders. On this test installation the
extension is active only in the administration siteaccess, so the address is under `/admin`. **On a normal site,
call a siteaccess that does not ask visitors to sign in** (usually the public site), as described in
[The address](#the-address).

### 1. Switch the trigger on

Open the import in the editor, step **Schedule**, section *Started by another system (webhook or API)*:

1. Tick **The trigger is on**.
2. **Run as user (ID)**: a user with *Run imports* for this import (here a test administrator). A triggered run never
   runs as anonymous.
3. Tick **A call may bring the data** (this import reads a CSV file).
4. Leave **Signing secret by name** empty, so the extension makes the secret, and save with **Save the trigger**.

The page now shows the new **signing secret, once**:

![The trigger section right after it was switched on: the signing secret in a notice that says it is shown only this once, then the trigger's fields and the address](images/trigger-secret-once.png)

Copy it into the calling system at that moment. It is **shown only this once** and not stored in a readable form.
If it is lost or leaked, **rotate** it with **New signing secret**: the old secret stops working at once.

Then create an API key: a name in **Name of a new key** (here `Docs: curl example`, who uses the key), and **Create an
API key**. The key is also **shown once**:

![The trigger section with a new API key named Docs: curl example, shown once in a notice, and the list of keys below](images/trigger-api-key-once.png)

The section afterwards: the trigger is on, the address to call, when the signing secret was made, and the list of keys
with their key id and when they were last used. **Revoke** stops a key at once; do it when a key leaks or its caller
is retired.

![The trigger section of the import: trigger on, run user, data allowed, the address, the signing secret date and the API key list with Revoke](images/trigger-section.png)

The address the section shows:

```
POST https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import
```

### 2. A wrong key is refused

```sh
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "Authorization: Bearer sqk_0123456789ab_sq-demo-token-REPLACE"
```

```
HTTP 401 {"error":"invalid_key"}
```

### 3. A signed call, and its replay

The signature covers the method, the path, the time and the SHA-256 of the body (here empty):

```sh
SECRET='sq-demo-signing-secret-REPLACE'
TS=$(date +%s); BODY=''
SIG=$(printf 'POST\n/sqliimport/trigger/docs-first-import\n%s\n%s' "$TS" "$(printf '%s' "$BODY" | sha256sum | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

```
HTTP 202 {"run_id":202,"status":"queued"}
```

The run #202 read the import's own source and completed (kind import, trigger rest): 7 records unchanged, because
nothing had changed since the last run. The same call sent again with the same timestamp and signature:

```
HTTP 401 {"error":"replayed"}
```

A signature is accepted once, and only within `[TriggerSettings] SignatureWindowSeconds` of the server's time.

### 4. An API key with data in the call

The calling system sends two changed rows as CSV (`teas-push.csv`): tea 105 with a new price, and a new tea 108.

```
id,name,code,description,price,harvest
105,White Peony,TEA-105,"A mild white tea with silver buds.",11.95,2026-04-20
108,Rooibos Vanilla,TEA-108,"A caffeine-free red bush tea with vanilla.",6.45,2026-06-01
```

```sh
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "X-Api-Key: sqk_0123456789ab_sq-demo-token-REPLACE" \
     -H "Content-Type: text/csv" --data-binary @teas-push.csv
```

```
HTTP 202 {"run_id":204,"status":"queued","status_url":".../run/204"}
```

`status_url` (shortened here) is the address of the status call below. The body is the data of this run only; the
import's own source is not read.

### 5. Ask for the status

With the API key (a signed call cannot ask for a status):

```sh
curl -sS "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import/run/204" \
     -H "Authorization: Bearer sqk_0123456789ab_sq-demo-token-REPLACE"
```

While the run waited for a worker of the job queue:

```
HTTP 200 {"status":"queued",...,"is_final":false,"run_id":204}
```

and after it ended:

```
HTTP 200 {"status":"completed","kind":"import","trigger":"rest","percent":100,"total":2,"done":2,"created":1,"updated":1,"unchanged":0,"skipped":0,"failed":0,"processed":0,"undone":0,"queued_at":1792719682,"started_at":1792719722,"finished_at":1792719723,"is_final":true,"run_id":204}
```

Poll until `is_final` is `true`. The run page shows the same run, started by *REST API* as the run user: tea 105
updated (version 1 to 2), tea 108 created. It can be undone like any run:

![The run page of run #204: completed, import started by REST API, 1 created and 1 updated, the two records 105 and 108 and the log](images/trigger-run-page.png)

The trigger's calls are in the audit trail with the key id, never the key ([Audit trail](#audit-trail)).

## The address

| Request | What it does | Who may |
|---|---|---|
| `POST /<siteaccess>/sqliimport/trigger/<identifier>` | Starts a run of the import | signed call or API key |
| `GET /<siteaccess>/sqliimport/trigger/<identifier>/run/<run id>` | The status of a run of this import | API key only |

`<identifier>` is the import's identifier (shown in the editor, e.g. `books`). Call a siteaccess that does not ask
visitors to sign in (usually the public site): on a siteaccess with `RequireUserLogin=true` the extension's settings
add `sqliimport/trigger` to `[SiteAccessSettings] AnonymousAccessList[]`, and the view is on
`[RoleSettings] PolicyOmitList[]`, because the caller never has a session. The view checks the caller itself.

Why a module view and not the kernel's REST API: the extension has no REST routes of its own, and the kernel's REST
layer (ezprestapi) would need its own authentication setup (OAuth) on every installation. A module view with its own
signature and key check is the smaller, self-contained path: it needs nothing but the extension, answers JSON, never
starts a session and has no cookie, so there is nothing a browser page of another site could ride on (no CSRF), and it
sends no CORS headers, so no page of another site can read an answer.

## Switching it on

In the editor, step **Schedule**, section *Started by another system (webhook or API)*:

| Field | Meaning |
|---|---|
| The trigger is on | Off: every call gets 404, exactly as for an import that does not exist. |
| Run as user (ID) | The user the run runs as: must have *Run imports* or *Manage imports* for the import's handler. Required when the trigger is on. The robot user of the Processing step (`owner_id`) still takes precedence, as for every run. |
| A call may bring the data | The body of a call is read instead of the import's source, for that run only. Only for imports that read a file (CSV, XML, JSON, spreadsheets ...); not for sources that read a database or service themselves. |
| Signing secret by name | Optional. Empty: the extension makes a signing secret. A name: a secret of at least 32 characters kept in Setup > Secrets (scope `sqliimport`), `[SecretSettings] Secrets[]` or `env:VARIABLE`. |

When the trigger is first switched on without a secret name, the page shows the new **signing secret once**. Copy it
into the calling system then: it is never shown again. **New signing secret** draws another one (the old one stops
working at once).

**API keys:** give the key a name (who uses it) and choose **Create an API key**. The key (`sqk_<key id>_<43
characters>`) is shown **once**. The list shows the name, the key id, when it was created and last used; **Revoke**
stops a key at once.

### How the secrets are kept

- An API key is never stored: only an HMAC-SHA256 of it, with the server key as pepper, and its key id to find it.
- A signing secret is never stored either: it is derived from the server key and a random salt of the import
  (`random_bytes`, 32 bytes); rotating draws a new salt. Or it is a named secret (`expImportSecrets`).
- The **server key** is the secret named by `[TriggerSettings] ServerKeySecret`, else a random key the extension
  writes once to `var/<site>/storage/sqliimport/trigger.key` (mode 0600), outside the database. A copy of the database
  alone gives neither a signing secret nor anything to check a key against. Changing the server key invalidates every
  signing secret and API key.
- No secret, key or signature is written to a log or the audit trail; the audit names the key id.

## A signed call

Headers:

```
X-Sqliimport-Timestamp: <Unix time in seconds>
X-Sqliimport-Signature: sha256=<hex HMAC-SHA256>
```

or the same as a signed URL: `?timestamp=<Unix time>&signature=<hex>`.

What is signed, four lines joined by `\n`:

```
POST
/sqliimport/trigger/<identifier>
<timestamp>
<SHA-256 hex of the body (of the empty string when there is no body)>
```

The path is always `/sqliimport/trigger/<identifier>`, without host, siteaccess or query, so the same signature works
on every siteaccess and behind a proxy. The call is accepted when the timestamp is within
`[TriggerSettings] SignatureWindowSeconds` (300) of the server's time, and **only once**: the same signature again is
refused as a replay. A signed call cannot ask for a status (that needs an API key).

```sh
SECRET='<signing secret>'
TS=$(date +%s)
BODY=''
SIG=$(printf 'POST\n/sqliimport/trigger/books\n%s\n%s' "$TS" "$(printf '%s' "$BODY" | sha256sum | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

With data (CSV):

```sh
TS=$(date +%s)
SIG=$(printf 'POST\n/sqliimport/trigger/books\n%s\n%s' "$TS" "$(sha256sum books.csv | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "Content-Type: text/csv" --data-binary @books.csv \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

In PHP:

```php
$ts = time();
$body = file_get_contents( 'books.csv' );
$sig = hash_hmac( 'sha256', "POST\n/sqliimport/trigger/books\n$ts\n" . hash( 'sha256', $body ), $secret );
```

## A call with an API key

```sh
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "Authorization: Bearer sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"

# with data (NDJSON), the key in X-Api-Key (when a proxy drops Authorization)
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "X-Api-Key: sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" \
     -H "Content-Type: application/x-ndjson" --data-binary @books.ndjson

# the status of the run
curl -sS "https://www.example.invalid/sqliimport/trigger/books/run/42" \
     -H "Authorization: Bearer sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

## Data in the call

Only when the trigger allows it and the import reads a file. The body must be the import's format:

| Content-Type | Read as |
|---|---|
| `text/csv`, `application/csv`, `text/tab-separated-values` | `csv` / `tsv` |
| `application/json` | `json` |
| `application/x-ndjson`, `application/ndjson`, `application/jsonl`, `application/x-jsonlines` | `ndjson` / `jsonl` |
| `application/xml`, `text/xml`, `application/rss+xml`, `application/atom+xml` | `xml` (`rss`, `atom`) |
| `application/yaml`, `text/yaml` | `yaml` / `yml` |
| xlsx and ods content types | `xlsx` / `ods` |

`X-Sqliimport-Format: <extension>` names the extension directly (any extension of the import's format, e.g. `xlsx`
with `application/octet-stream`). Anything else, or data for an import that takes none, is answered with 415; a body
larger than `[TriggerSettings] MaxBodyMegabytes` (20) with 413.

The body is kept in `var/<site>/storage/sqliimport/pushed/` and moved into the run's directory as `source.<ext>` when
the run starts; the run then reads it like an uploaded file, and it is removed with the run's history. The import's
own source (its upload, address or server file) is untouched and is read again by the next run without data. A
handler that reads its records itself (not through a source) ignores the data.

## Answers

Every answer is JSON with `Cache-Control: no-store`.

| Status | Body | When |
|---|---|---|
| 202 | `{"run_id": 42, "status": "queued", "status_url": "..."}` | The run is queued. `status_url` only for an API key. |
| 200 | `{"run_id", "status", "kind", "trigger", "percent", "total", "done", "created", "updated", "unchanged", "skipped", "failed", "processed", "undone", "queued_at", "started_at", "finished_at", "is_final"}` | The status of a run |
| 401 | `{"error": "missing_credentials" \| "invalid_key" \| "invalid_signature" \| "expired" \| "replayed"}` | No or wrong credentials, a timestamp outside the window, a signature used before |
| 403 | `{"error": "no_run_user" \| "import_disabled" \| "status_needs_api_key"}` | The run user is missing or may not run the import; the import is disabled; a signed call asks for a status |
| 404 | `{"error": "not_found"}` | No import of that identifier, its trigger is off, `[TriggerSettings] Trigger=disabled`, or a run of another import |
| 405 | `{"error": "method_not_allowed"}` | Not POST (start) or GET (status); `Allow` names the method |
| 409 | `{"error": "already_running"}` | A run of the import is queued or running |
| 413 | `{"error": "body_too_large"}` | Larger than `MaxBodyMegabytes` |
| 415 | `{"error": "unsupported_media_type", "message"}` | The body is not the import's format, or the import takes no data in a call |
| 429 | `{"error": "rate_limited"}` | Over the limit of the import; `Retry-After: 60` |
| 503 | `{"error": "unavailable"}` | The job queue is missing, the tables are not installed, the run could not be queued |

The run itself shows *REST API* as its trigger on the run page and in the statistics.

## Rate limits

Per import and minute (`[TriggerSettings]`, `0` = no limit):

| Setting | Default | Counts |
|---|---|---|
| `RequestsPerMinute` | `6` | Accepted calls that start a run |
| `StatusRequestsPerMinute` | `60` | Status requests |
| `FailedRequestsPerMinute` | `20` | Refused calls (wrong key, signature, timestamp); over it, every call of the import is refused for the rest of the minute |

The calls of the last minutes are kept in `sqliimport_trigger_call` (only hashes) and removed after ten minutes.

## Audit trail

Every call that reaches an import with a switched-on trigger is an event of the audit trail (`system.import.*`):
`trigger.start` (run id, key id or `signature`, whether data came), `trigger.refused` (the reason), `trigger.limited`;
in the editor `trigger.change`, `trigger.rotate`, `trigger.key.create`, `trigger.key.revoke`. Never a secret, key or
signature.

## Settings

| Setting | Default | Meaning |
|---|---|---|
| `Trigger` | `enabled` | `disabled` answers 404 for every import |
| `SignatureWindowSeconds` | `300` | How far a signed call's timestamp may be from the server's time |
| `RequestsPerMinute`, `StatusRequestsPerMinute`, `FailedRequestsPerMinute` | `6`, `60`, `20` | The rate limits above |
| `MaxBodyMegabytes` | `20` | The largest body that brings data |
| `ServerKeySecret` | empty | The name of a secret (32 characters or more) used as the server key; empty: `var/<site>/storage/sqliimport/trigger.key` |

## Tables

`./console ext:sqliimport:install-schema` creates them (MySQL, PostgreSQL, SQLite, Oracle); on an existing
installation, run it once after the update:

| Table | Holds |
|---|---|
| `sqliimport_trigger` | One row per import: on or off, run user, data allowed, secret name, signing salt |
| `sqliimport_trigger_key` | The API keys: name, key id, hash, created, last used, revoked |
| `sqliimport_trigger_call` | The calls of the last minutes: signature hashes (replay protection) and the rate limit |

Removing an import removes its trigger and keys.
