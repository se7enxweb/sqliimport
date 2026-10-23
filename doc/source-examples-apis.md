# Worked examples: APIs and hosted services

This page shows one complete import for every source of 7x SQLI Import that reads a web API: any JSON REST API, a
Google Sheet by its link, Airtable, Notion, Baserow, Supabase or PostgREST, the Firebase Realtime Database,
Salesforce and HubSpot. Each example names its goal, the secret it needs, the exact values to type into the import
editor, the request the source sends, an excerpt of what comes back, the mapping lines and the result.

The options themselves are described in [sources.md](sources.md); each example links to its section there. The
editor's steps are explained in [editor.md](editor.md) and the mapping lines in [mappings.md](mappings.md). The other
worked examples: [databases](source-examples-databases.md) and [platforms, commerce and documents](source-examples-platforms.md).

**Illustrative: these examples were not run against a live account.** No API account of these services is
connected to the documentation server. The configurations are complete and checked by the editor, the record
excerpts are the catalogue's example snapshots in `share/examples/` (what the source writes into the run's
directory), and the results are what a run of those records gives. Every address uses `example.invalid` and every
token is a placeholder.

## Contents

- [Before you start: secrets](#before-you-start-secrets)
- [Any JSON REST API (`rest_api`)](#any-json-rest-api-rest_api)
  - [Cursor paging with a bearer token](#cursor-paging-with-a-bearer-token)
  - [Offset paging with an API key header](#offset-paging-with-an-api-key-header)
  - [Page numbers with basic authentication](#page-numbers-with-basic-authentication)
  - [The Link header of a public API, no authentication](#the-link-header-of-a-public-api-no-authentication)
- [Google Sheets by its link (`google_sheets`)](#google-sheets-by-its-link-google_sheets)
- [Airtable (`airtable`)](#airtable-airtable)
- [Notion (`notion`)](#notion-notion)
- [Baserow (`baserow`)](#baserow-baserow)
- [Supabase or PostgREST (`supabase`)](#supabase-or-postgrest-supabase)
- [Firebase Realtime Database (`firebase_rtdb`)](#firebase-realtime-database-firebase_rtdb)
- [Salesforce SOQL (`salesforce`)](#salesforce-soql-salesforce)
- [HubSpot (`hubspot`)](#hubspot-hubspot)

The REST API and Google Sheets are in the catalogue's group *Data*; the hosted services are in the group
*Platforms*, next to the SQL source and the site readers:

![The catalogue card of the REST API format with its options, example file and the New import button](images/src-catalogue-rest-api-card.png)

![The Platforms group of the catalogue with the cards of SQL, the site readers and the hosted services](images/src-catalogue-platforms-group.png)

## Before you start: secrets

An import never holds a token, a password or a key. Its secret options hold the **name** of a secret
([sources.md, Secrets](sources.md#secrets)). There are two ways to give that name a value:

1. **In the editor.** Type the name into the option (for example `shop_api_token`) and the token into the field
   **New value** under it, then save the step. The value goes into the kernel's secret store (Exponential 6.0.15,
   **Setup, Secrets**, scope `sqliimport`) under that name. The field is always empty afterwards; a stored value is
   never shown again. It needs the policy *secrets/manage*.
2. **In Setup, Secrets** (or, on older installations, `sqliimport.ini [SecretSettings] Secrets[shop_api_token]=...`
   in `settings/override/`, never in a repository), or as `env:SHOP_API_TOKEN`, an environment variable of the
   PHP-FPM pool and of the job queue's workers.

**Imports, Settings** lists which secret names are set, never a value. Messages and logs show `[secret <name>]`.
The screenshots on this page show filled forms that were **not saved**, with placeholder names and no value in
**New value**.

Every example below then follows the same steps:

1. **Imports, Catalogue**, the card of the format, **New import** (or **Imports, New import** and the format under
   *Source type*).
2. Step **Source**: a name, then the options listed in the example. **Test connection** reads the first record or
   the first page and shows its fields; nothing is saved.
3. Step **Mapping**: the class and the lines listed in the example (the format's preset fills most of them when
   the import is saved).
4. Step **Preview**: the sample and a dry run of the first records; then **Run** or a schedule.

## Any JSON REST API (`rest_api`)

Options: [sources.md, Any JSON REST API](sources.md#any-json-rest-api-rest_api). The four examples read the same
invented product API in the four paging modes and the four kinds of authentication, so each one shows one paging
mode and one way to sign in. All of them start from the snapshot `share/examples/rest-api-products.ndjson`.

What comes back, flattened into records (excerpt of the snapshot):

```json
{"id":"P-1001","name":"Sencha tea, 100 g","sku":"TEA-SEN-100","price.amount":"8.90","price.currency":"EUR","in_stock":true,"tags":"[\"tea\",\"green\"]","category.name":"Green tea","updated_at":"2026-09-02T10:15:00Z"}
{"id":"P-1002","name":"Oolong tea, 50 g","sku":"TEA-OOL-050","price.amount":"11.50","price.currency":"EUR","in_stock":false,"tags":"[\"tea\",\"oolong\"]","category.name":"Oolong","updated_at":"2026-09-05T14:40:00Z"}
{"id":"P-1003","name":"Tea strainer","sku":"ACC-STR-001","price.amount":"4.20","price.currency":"EUR","in_stock":true,"tags":"[]","category":null,"updated_at":"2026-09-09T08:05:00Z"}
```

The mapping is the same for all four, into the webshop class `product`:

| Source field | Attribute | Transforms (one-line chain) |
|---|---|---|
| `name` | `name` | `trim` |
| `sku` | `product_number` | |
| `price.amount` | `price` | `template(text="{price.amount}\|1\|1")` (amount, VAT type 1, price includes VAT) |
| `tags` | `tags` | `join(separator=", ")` |

Match: remote ID `shop-{id}`. **Expected result** of the first run: three products created (`shop-P-1001` to
`shop-P-1003`); a second run with no change in the API: three unchanged. When the API changes a price, that product
is updated and the other two stay unchanged.

### Cursor paging with a bearer token

**Goal:** import the active products of a shop API that pages with a cursor and signs in with a bearer token.

**Secret:** `shop_api_token` (the API's read-only token; type it into **New value**, or set it in Setup, Secrets).

| Option | Value |
|---|---|
| Address (`base_url`) | `https://api.example.invalid/v1/products` |
| Method | `GET` |
| Parameters (`query`) | `status=active` |
| Record list (`record_path`) | `data` |
| Authentication (`auth`) | `bearer` |
| Secret (`auth_secret`) | `shop_api_token` |
| Paging | `cursor` |
| Page size parameter (`size_param`) | `limit` |
| Cursor path (`cursor_path`) | `meta.next_cursor` |
| Cursor parameter (`cursor_param`) | `cursor` (the default) |
| Total path (`total_path`) | `meta.total` |
| Page size | `100` |
| Least time between requests | `0.5` |
| Key field | `id` |

![The options of the REST API source filled in for cursor paging with a bearer token, the secret named shop_api_token and the New value field empty](images/src-rest-api-options-cursor-bearer.png)

**The requests** the source sends:

```
GET https://api.example.invalid/v1/products?status=active&limit=100
Authorization: Bearer <the value of shop_api_token>

GET https://api.example.invalid/v1/products?status=active&limit=100&cursor=eyJpZCI6IlAtMTAwMyJ9
```

An answer looks like `{"data": [ ...records... ], "meta": {"next_cursor": "eyJpZCI6IlAtMTAwMyJ9", "total": 3}}`; the
source stops when `meta.next_cursor` is empty or the records reach `meta.total`.

**Test connection** reads the first page only and shows its fields and the total. With the placeholder address of
this example it shows what happens to a host that does not resolve to a public address: the source refuses it before
anything is sent (the reserved name `.invalid` counts as a reserved network). An intranet API is let through by
listing it in `[RestApiSourceSettings] AllowedHosts[]`. A secret never appears in such a message:

![Test connection of the REST API source on a placeholder address: the error message, with the token never shown](images/src-rest-api-test-connection-error.png)

### Offset paging with an API key header

**Goal:** the same products from an API that pages with `offset` and `limit` and wants its key in a header.

**Secret:** `shop_api_key`.

| Option | Value |
|---|---|
| Address | `https://api.example.invalid/v2/catalog/items` |
| Record list | `items` |
| Authentication | `api_key_header` |
| Secret | `shop_api_key` |
| API key header or parameter (`api_key_name`) | `X-Api-Key` |
| Paging | `offset` |
| Offset parameter, Limit parameter | `offset`, `limit` (the defaults) |
| Page size | `50` |
| Key field | `id` |

Requests: `GET .../items?offset=0&limit=50`, then `offset=50`, `offset=100` ..., each with `X-Api-Key: <value>`. The
reading stops at a page shorter than 50 records. With `api_key_query` instead, the key goes into the parameter
named in `api_key_name` (`?api_key=...`), also on the following pages; messages never show that parameter.

### Page numbers with basic authentication

**Goal:** an intranet event API with page numbers and a user and password.

**Secret:** `events_api_password`; the user is an option.

| Option | Value |
|---|---|
| Address | `https://events.example.invalid/api/events` |
| Parameters | `from=2026-10-01` |
| Authentication | `basic` |
| User or client ID (`auth_user`) | `import-reader` |
| Secret | `events_api_password` |
| Paging | `page` |
| Page parameter, First page | `page`, `1` |
| Page size parameter | `per_page` |
| Page size | `100` |

Requests: `GET .../events?from=2026-10-01&page=1&per_page=100`, `page=2` ..., each with `Authorization: Basic`. An
intranet host must be listed in `[RestApiSourceSettings] AllowedHosts[]` (and only a listed host may use `http`).

### The Link header of a public API, no authentication

**Goal:** the releases of a public project API that pages with the RFC 8288 `Link` header (the way GitHub, GitLab and
WordPress page).

**Secret:** none.

| Option | Value |
|---|---|
| Address | `https://api.example.invalid/repos/example/tea-tools/releases` |
| Extra headers | `Accept: application/json` |
| Authentication | `none` |
| Paging | `link` |
| Page size parameter, Page size | `per_page`, `30` |
| Most pages | `5` |

The source follows `Link: <https://api.example.invalid/...&page=2>; rel="next"` until there is no `next`, and only on
the host of the address. `next_url` works the same with an address inside the answer (`next_path` `links.next`).

## Google Sheets by its link (`google_sheets`)

Options: [sources.md, Google Sheets](sources.md#google-sheets-google_sheets).

**Goal:** a team keeps its event list in a Google Sheet; the site shows each row as an event, updated every hour.

**Secret:** none. Share the sheet as *Anyone with the link can view* (or publish it to the web).

Steps: format **Google Sheets (by its link)**; *Where the data comes from*: **An address**; the address is the link
of the sheet as the browser shows it:

```
https://docs.google.com/spreadsheets/d/1ExampleSheetIdREPLACE/edit#gid=0
```

![The source step of a Google Sheets import with the link of the sheet as the address](images/src-google-sheets-address.png)

The source turns the link into the CSV export of the tab of the link (`#gid=0`) and reads it as CSV with a comma and
UTF-8; the first row names the columns. A sheet with the columns `id`, `Title`, `Date`, `Place`:

```
id,Title,Date,Place
E-1,Tea tasting evening,05.11.2026,Tea room
E-2,Garden walk,12.11.2026,Garden
```

| Source field | Attribute (class `event` or `article`) | Transforms |
|---|---|---|
| `Title` | `title` | `trim` |
| `Date` | `publish_date` | `date(format=d.m.Y, timezone=Europe/Berlin)` |
| `Place` | `intro` | `richtext` |

Match: remote ID `sheet-event-{id}`. Schedule: hourly. **Expected result:** two objects on the first run; a row
added in the sheet becomes a new object at the next run, a changed cell updates its object.

## Airtable (`airtable`)

Options: [sources.md, Airtable](sources.md#airtable-airtable).

**Goal:** the published events of an Airtable base as articles.

**Secret:** `airtable_events`, a personal access token with the scope `data.records:read` on this base only.

| Option | Value |
|---|---|
| Base ID | `appExampleBase01` |
| Table | `Events` |
| View | `Published` |
| Formula | `{Status} = "Published"` |
| Token | `airtable_events` |
| Page size | `100` |
| Key field | `id` |

![The options of the Airtable source with a base, a table, a view, a formula and the name of the token secret](images/src-airtable-options.png)

Request: `GET https://api.airtable.com/v0/appExampleBase01/Events?view=Published&filterByFormula=...&pageSize=100`,
then with the `offset` of each answer. What comes back (`share/examples/airtable-events.ndjson`, excerpt):

```json
{"id":"recExample000001","created_time":"2026-09-01T08:00:00.000Z","Name":"Tea tasting evening","Date":"2026-11-05","Status":"Published","Poster":["https://dl.airtable.example.invalid/poster-tasting.jpg"],"Poster_names":["poster-tasting.jpg"],"Notes":"An evening with five green teas.\nBooking needed.","Seats":40,"Featured":true}
```

| Source field | Attribute (`article`) | Transforms |
|---|---|---|
| `Name` | `title` | `trim` |
| `Notes` | `intro` | `richtext` |
| `Date` | `publish_date` (where the class has it) | `date` |
| `Poster` | `image` | `first \| image` |

Match: remote ID `airtable-{id}`. **Expected result:** three articles; the poster of the first is downloaded as its
image (Airtable's file addresses expire, so the run downloads them at once).

## Notion (`notion`)

Options: [sources.md, Notion](sources.md#notion-notion).

**Goal:** the published pages of a Notion database as articles, with the page content as the body.

**Secret:** `notion_articles`, the token of an internal integration that the database is shared with (read content
only).

| Option | Value |
|---|---|
| Database ID | `11111111222243338444555555555500` (or the database's link) |
| Filter | `{"property": "Status", "status": {"equals": "Published"}}` |
| Sorts | `[{"property": "Date", "direction": "descending"}]` |
| Content | `html` |
| Token | `notion_articles` |

Request: `POST https://api.notion.com/v1/databases/<id>/query` with the filter (a query that reads), then
`GET .../blocks/<page>/children` for the content. Excerpt of `share/examples/notion-articles.ndjson`:

```json
{"id":"11111111-2222-4333-8444-555555555501","title":"Spring teas","Summary":"The first harvest of the year.","Date":"2026-10-01","Status":"Published","Tags":"Green, Japan","Author":["Sample Author"],"content":"# Spring teas\n\nThe first harvest is the **finest**.\n\n- Sencha\n- Gyokuro"}
```

| Source field | Attribute (`article`) | Transforms |
|---|---|---|
| `title` | `title` | |
| `Summary` | `intro` | `richtext` |
| `content` | `body` | `richtext` |
| `Tags` | `tags` (keywords, where the class has them) | |

Match: remote ID `notion-{id}`. **Expected result:** two articles; editing a page in Notion updates its article at
the next run (`last_edited_time` changes, and so does the content).

## Baserow (`baserow`)

Options: [sources.md, Baserow](sources.md#baserow-baserow).

**Goal:** the published events of a Baserow table.

**Secret:** `baserow_events`, a database token with read permission on the table.

| Option | Value |
|---|---|
| Address | `https://api.baserow.io` (or your own Baserow) |
| Table ID | `4711` |
| Filters | `Published__boolean=1` |
| Order by | `-Date` |
| Include | `Name`, `Date`, `Seats`, `Topics`, `Venue` (one per line) |
| Token | `baserow_events` |

Request: `GET https://api.baserow.io/api/database/rows/table/4711/?user_field_names=true&filter__Published__boolean=1&order_by=-Date&size=100`.
Excerpt of `share/examples/baserow-events.ndjson`:

```json
{"id":1,"Name":"Autumn tasting","Date":"2026-10-10","Seats":"40","Status":"Published","Published":true,"Topics":"Green tea, Japan","Venue":"Tea room","Venue_ids":"7"}
```

Mapping: `Name` to `title` (`trim`), `Topics` to `intro` (`richtext`), `Date` to `publish_date` (`date`). Match:
`baserow-{id}`. **Expected result:** the two published rows of the snapshot become articles; the draft row (`Published`
false) is not read.

## Supabase or PostgREST (`supabase`)

Options: [sources.md, Supabase or PostgREST](sources.md#supabase-or-postgrest-supabase).

**Goal:** the published articles of a Supabase table, with the author's name from a related table.

**Secret:** `supabase_anon_key` (the project's anon key, with row level security that lets it read published rows
only), or `bearer` with a JWT of a read-only role.

| Option | Value |
|---|---|
| Address | `https://exampleref.supabase.co` |
| Table | `articles` |
| Select | `id,title,slug,status,published_at,tags,word_count,author:authors(name,email)` |
| Filters | `status=eq.published` |
| Order | `published_at.desc,id` |
| Paging | `range` |
| Count | `exact` |
| API key (`api_key`) | `supabase_anon_key` |

Request: `GET https://exampleref.supabase.co/rest/v1/articles?select=...&status=eq.published&order=published_at.desc,id`
with `Range: 0-999` and `Prefer: count=exact`. Excerpt of `share/examples/supabase-articles.ndjson`:

```json
{"id":101,"title":"Brewing green tea at the right heat","slug":"brewing-green-tea","status":"published","published_at":"2026-09-01T08:00:00+00:00","tags":"[\"tea\",\"green\"]","word_count":840,"author.name":"Editorial desk","author.email":"desk@example.invalid"}
```

Mapping: `title` to `title`, `slug` to `short_title`, `author.name` to `author`, `published_at` to
`publish_date` (`date`). The author's e-mail address is personal data: leave it unmapped unless the site shows it.
Match: `supabase-{id}`. **Expected result:** three articles. Any PostgREST server works the same with its own address
and no key.

## Firebase Realtime Database (`firebase_rtdb`)

Options: [sources.md, Firebase Realtime Database](sources.md#firebase-realtime-database-firebase_rtdb).

**Goal:** the children of `/articles` of a Firebase Realtime Database.

**Secret:** `firebase_reader`, the JSON key of a service account with the role *Firebase Realtime Database Viewer* (`roles/firebasedatabase.viewer`)
(the whole key file is the value of the secret).

| Option | Value |
|---|---|
| Database address | `https://example-tea-default-rtdb.europe-west1.firebasedatabase.app` |
| Path | `articles` |
| Authentication | `service_account` |
| Service account key | `firebase_reader` |
| Shallow | on (a big tree: the keys first, then the children a page at a time) |
| Key field | `_key` |

Excerpt of `share/examples/firebase-articles.ndjson`:

```json
{"_key":"-Nx1aExampleKey0001","title":"Brewing green tea","status":"published","author.name":"Tea desk","tags":"[\"green\",\"brewing\"]","views":1250}
{"_key":"-Nx1aExampleKey0003","title":"Storing oolong","status":"draft","author.name":"Shop team","tags":"[\"oolong\",\"storage\"]","views":0}
```

Mapping: `title` to `title`, `tags` to `tags` (`join(separator=", ")`). This source has no filter, so the
mapping leaves the drafts out with a class per record ([mappings.md](mappings.md#a-class-per-record)): the class is
chosen by `status`, and the value `draft` is skipped:

```json
"class_from": "status",
"variants": { "draft": { "skip": true } }
```

Match: `firebase-{_key}`. **Expected result:** two articles created; the draft's row says "not imported".

## Salesforce SOQL (`salesforce`)

Options: [sources.md, Salesforce](sources.md#salesforce-salesforce).

**Goal:** the customer accounts of Salesforce as a list of partners on the site.

**Secrets:** `salesforce_jwt_key`, the PEM private key of the connected app's certificate (JWT bearer flow). With
`refresh_token` instead: `salesforce_client_secret` and `salesforce_refresh_token`.

| Option | Value |
|---|---|
| Login address | `https://login.salesforce.com` (`https://test.salesforce.com` for a sandbox) |
| Authentication | `jwt` |
| Consumer key (`client_id`) | `3MVG9Example.ConsumerKeyREPLACE` |
| User | `integration@example.invalid` |
| Private key | `salesforce_jwt_key` |
| Query | `SELECT Id, Name, Type, Industry, BillingAddress, Owner.Name, LastModifiedDate FROM Account WHERE Type = 'Customer' ORDER BY Name` |
| API version | `v61.0` |
| Key field | `Id` |

The source signs in, then sends `GET /services/data/v61.0/query?q=...` and follows `nextRecordsUrl`. Excerpt of
`share/examples/salesforce-accounts.ndjson`:

```json
{"Id":"001EX00000000001AA","Name":"Example Tea Traders","Type":"Customer","Industry":"Retail","BillingAddress.city":"Sampletown","BillingAddress.country":"Exampleland","Owner.Name":"Sales Desk","LastModifiedDate":"2026-09-20T14:05:00.000Z"}
```

Mapping into `folder`: `Name` to `name`, `BillingAddress.city` to `short_name`. Match: `salesforce-{Id}`.
**Expected result:** three folders. Only a `SELECT` is accepted; give the integration user a profile that may only
read accounts.

## HubSpot (`hubspot`)

Options: [sources.md, HubSpot](sources.md#hubspot-hubspot).

**Goal:** the customer companies of HubSpot, with the IDs of their contacts.

**Secret:** `hubspot_private_app`, the access token of a private app with the scope `crm.objects.companies.read`.

| Option | Value |
|---|---|
| Object type | `companies` |
| Mode | `search` |
| Properties | `name`, `domain`, `industry`, `city` (one per line) |
| Filter groups | `[{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]` |
| Sort | `createdate DESCENDING` |
| Token | `hubspot_private_app` |

With mode `list` and *Associations* `contacts`, the source reads every company with its contacts' IDs instead.
Excerpt of `share/examples/hubspot-companies.ndjson`:

```json
{"id":"9001","name":"Example Tea Traders","domain":"tea-traders.example.invalid","industry":"RETAIL","city":"Sampletown","createdate":"2026-08-01T10:00:00.000Z","associations.contacts":["501","502"]}
```

Mapping into `folder`: `name` to `name`, `domain` to `short_name`. Match: `hubspot-{id}`. **Expected result:** three
folders; a company renamed in HubSpot is renamed at the next run.

## See also

- [Worked examples: databases](source-examples-databases.md)
- [Worked examples: platforms, commerce and documents](source-examples-platforms.md)
- [Sources](sources.md), [The import editor](editor.md), [Mappings](mappings.md), [Schedules](schedules.md)
