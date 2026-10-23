# Worked examples: databases

This page shows one complete import for every database source of 7x SQLI Import: SQL (MySQL or MariaDB,
PostgreSQL, SQLite, SQL Server, Oracle) and the NoSQL and analytics stores (MongoDB, Redis, Elasticsearch and
OpenSearch, CouchDB, Couchbase, DynamoDB, Firestore, Cosmos DB, Cassandra and ScyllaDB, Neo4j and Memgraph, ArangoDB,
ClickHouse, BigQuery, Snowflake, InfluxDB, DuckDB) and LDAP or Active Directory. Each example names its goal, the
connection and secret it needs, the exact values to type into the import editor, the query, an excerpt of what comes
back, the mapping lines and the result.

Every option is described in [sources.md](sources.md); each example links to its section there. The other worked
examples: [APIs and hosted services](source-examples-apis.md) and
[platforms, commerce and documents](source-examples-platforms.md).

**What ran and what did not.** The [SQLite example](#sqlite-run-on-the-documentation-server) ran on the documentation
server through a named connection, with its Test connection, its refused `UPDATE` and its dry run shown as they
happened. Every other example is **illustrative: it was not run against a live service**, because no such server
is connected to the documentation server. Their configurations are complete, the record excerpts are the
catalogue's example snapshots in `share/examples/` (exactly what the source writes into the run's directory), and the
results are what a run of those records gives. Hosts use `example.invalid` and every secret is a placeholder name.

## Contents

- [How every database example works](#how-every-database-example-works)
- [SQL databases (`sql`)](#sql-databases-sql)
  - [Drivers and the catalogue](#drivers-and-the-catalogue)
  - [SQLite, run on the documentation server](#sqlite-run-on-the-documentation-server)
  - [The read-only guard refuses an UPDATE](#the-read-only-guard-refuses-an-update)
  - [MySQL or MariaDB](#mysql-or-mariadb)
  - [PostgreSQL](#postgresql)
  - [Microsoft SQL Server](#microsoft-sql-server)
  - [Oracle: oci8 and eZOracleDB](#oracle-oci8-and-ezoracledb)
- [MongoDB (`mongodb`)](#mongodb-mongodb)
- [Redis (`redis`)](#redis-redis)
- [Elasticsearch or OpenSearch (`elasticsearch`)](#elasticsearch-or-opensearch-elasticsearch)
- [CouchDB (`couchdb`) and Couchbase (`couchbase`)](#couchdb-couchdb-and-couchbase-couchbase)
- [Amazon DynamoDB (`dynamodb`)](#amazon-dynamodb-dynamodb)
- [Firestore (`firestore`)](#firestore-firestore)
- [Azure Cosmos DB (`cosmosdb`)](#azure-cosmos-db-cosmosdb)
- [Cassandra or ScyllaDB (`cassandra`)](#cassandra-or-scylladb-cassandra)
- [Neo4j or Memgraph (`neo4j`)](#neo4j-or-memgraph-neo4j)
- [ArangoDB (`arangodb`)](#arangodb-arangodb)
- [ClickHouse (`clickhouse`)](#clickhouse-clickhouse)
- [BigQuery (`bigquery`)](#bigquery-bigquery)
- [Snowflake (`snowflake`)](#snowflake-snowflake)
- [InfluxDB (`influxdb`)](#influxdb-influxdb)
- [DuckDB over Parquet or CSV (`duckdb`)](#duckdb)
- [LDAP or Active Directory (`ldap`)](#ldap-or-active-directory-ldap)

## How every database example works

1. **Imports, Catalogue**: the SQL source is in the group *Platforms*, the stores below in *NoSQL databases*, LDAP
   in *Data*. Open the card's *What this server can connect with* to see whether this server (and the job queue's
   workers) has the driver. **New import** opens the editor with the format chosen.
2. Step **Source**: a name and the options of the example. The origin is `remote`: the source reads the database
   itself once per run and writes the rows into the run's directory, one JSON object per line.
3. **Test connection** (under the options) connects with the values of the form, reads the first row or document
   and shows its fields, or the error with every secret masked. **Nothing is saved.**
4. Step **Mapping**, then **Preview** (the sample and a dry run), then **Run** or a schedule.

**Secrets.** A password, key or token is never an option value. The option holds the **name** of a secret; give it
a value either in the field **New value** right under the option (it is stored in **Setup, Secrets** of Exponential
6.0.15, scope `sqliimport`, and never shown again) or beforehand in Setup, Secrets, `sqliimport.ini
[SecretSettings] Secrets[<name>]` in `settings/override/`, or as `env:<VARIABLE>`
([sources.md, Secrets](sources.md#secrets)). The screenshots on this page show forms that were filled in but not
saved, with no value in **New value**.

**Read-only.** Every database source only reads: a single `SELECT` (or the store's read call), a read-only session
where the server has one, and a user or key that may only read. Give each import such a user.

![The NoSQL databases group of the catalogue with the cards of MongoDB, Redis, Elasticsearch, DuckDB and the other stores](images/src-catalogue-nosql-group.png)

## SQL databases (`sql`)

Options: [sources.md, SQL database or query](sources.md#sql-database-or-query-sql).

All SQL examples use the same table `teas` of the example database `share/examples/sql-teas.sqlite` (`id`, `name`,
`origin`, `kind`, `price`, `description`, `updated`), so the query, the mapping and the result are the same whatever
the engine. The **goal**: the teas that are not herbal, as products of the webshop class `product`.

The query:

```sql
SELECT id, name, origin, kind, price, description FROM teas WHERE kind <> 'herbal' ORDER BY id
```

The mapping (class `product`, match by the remote ID `sql-tea-{id}`):

| Source field | Attribute | Transforms (one-line chain) |
|---|---|---|
| `name` | `name` | `trim` (required) |
| `id` | `product_number` | `template(text=TEA-{id})` |
| `description` | `description` | `richtext` |
| `price` | `price` | `template(text="{price}\|1\|1")` (amount, VAT type 1, price includes VAT) |
| `kind` | `tags` | |

### Drivers and the catalogue

The SQL card of the catalogue lists the databases this server can read, green with the driver it uses. On the
documentation server: MySQL or MariaDB, PostgreSQL and SQLite through PDO, Oracle through `oci8` (and, for named
connections, the kernel's `eZOracleDB` of the extension ezoracle), and SQL Server through `PDO_ODBC` with FreeTDS:

![The SQL card of the catalogue with its driver rows: MySQL, PostgreSQL, SQLite, Oracle with oci8 and eZOracleDB, SQL Server with PDO_ODBC](images/src-catalogue-sql-card-drivers.png)

A grey row names what the host needs ([sources.md](sources.md#sql-database-or-query-sql), *Drivers*). Runs are
read by the job queue's workers, whose PHP can differ from the web server's; the list says "in the workers" when
only they have a driver.

### SQLite, run on the documentation server

**Goal:** the four teas that are not herbal, from `sql-teas.sqlite`, as products. This example **ran** on the
documentation server (import "Docs: Teas from SQLite (named connection)", dry run #156).

**Connection.** Exponential 6.0.15 keeps database connections by name in **Setup, Data tables, Connections**. The
example database was copied into the connections' SQLite directory (`var/datatable`, `[ConnectionSettings]
SQLiteDirectories[]` of `datatable.ini`) and a connection was made there:

| Field | Value |
|---|---|
| Identifier | `docs_teas` |
| Name | `Docs: teas (SQLite example)` |
| Engine | SQLite |
| Database | `var/datatable/docs-sql-teas.sqlite` |
| Password: the name of a secret | empty (a SQLite file has none) |
| More, Read only | on |

**Test connection** on the connection's page answered "Connected through ez in 11 ms: 3.34.1, 1 tables":

![The named connection docs_teas in Setup, Data tables, Connections, with its successful test](images/src-sql-connection-setup.png)

For a MySQL, PostgreSQL, Oracle or SQL Server connection, the same page takes the server, port, database and user,
and **Password: the name of a secret** names the secret (with **Store it in the secret store when saving** a
password typed in for the test is stored under that name).

Without a named connection (an installation before 6.0.15) the same import uses the driver `sqlite` and the path of
the file as *Database*; the file must then lie inside `[SourceSettings] AllowedPaths[]` of `sqliimport.ini`.

**Editor steps:**

1. **Imports, Catalogue**, card *SQL database or query*, **New import**.
2. Name: `Docs: Teas from SQLite (named connection)`.
3. Options: *Named connection* `docs_teas`; *Query* the query above; *Key column* `id`. The fields *Database* to
   *Password* stay empty: a named connection brings them.
4. **Test connection**: "The connection works. Its fields: `id`, `name`, `origin`, `kind`, `price`, `description`".
   The drivers are listed under it:

![The SQL source options with the named connection docs_teas, the query, the successful Test connection and the driver rows](images/src-sql-sqlite-test-connection.png)

The same test at 960 pixels and double scale:

![Test connection of the SQLite example at 960 pixels width and double scale](images/src-sql-sqlite-test-connection-960.png)

5. **Save and continue**; step **Mapping**: the class `product` and the lines of the table above.
6. Step **Preview**: the sample shows the four rows (record 4, the herbal Red Bush Evening, is not in the result of
   the query), and the dry run says what each would become:

![The preview of the SQLite import: four sample rows and a dry run in which each would be created with its name, product number, description, price and tag](images/src-sql-sqlite-preview.png)

**What comes back** (the rows as the source writes them):

```json
{"id":1,"name":"Morning Sencha","origin":"Japan","kind":"green","price":6.5,"description":"A fresh, grassy green tea for the first cup of the day."}
{"id":2,"name":"Highland Black","origin":"India","kind":"black","price":5.9,"description":"A malty black tea that takes milk well."}
{"id":3,"name":"Silver Needle","origin":"China","kind":"white","price":12,"description":"Young buds only, light and sweet."}
{"id":5,"name":"Oolong Mountain","origin":"Taiwan","kind":"oolong","price":9.8,"description":"Half oxidised, with notes of orchid and honey; \"best\" brewed twice."}
```

**Result.** The dry run #156: 4 would be created, 0 failed, nothing published:

![The run page of the dry run of the SQLite import: 4 created, 0 updated, 0 unchanged, 0 skipped, 0 failed](images/src-sql-sqlite-run.png)

A real run creates the products `sql-tea-1`, `sql-tea-2`, `sql-tea-3` and `sql-tea-5` (product numbers `TEA-1` ...,
prices 6.50, 5.90, 12.00 and 9.80 including VAT). When a price changes in the database, the next run updates that
product and leaves the others unchanged.

### The read-only guard refuses an UPDATE

The same form with the query

```sql
UPDATE teas SET price = 0 WHERE id = 1
```

is refused before anything reaches the database. Test connection says so, and a run with such a query would stop
with the same reason:

![Test connection with an UPDATE query: the test failed, only a SELECT or WITH ... SELECT query is allowed](images/src-sql-update-refused.png)

The same happens to `SELECT ...; DELETE ...`, to `SELECT ... INTO OUTFILE`, to `SELECT ... FOR UPDATE` and to the
other words and functions listed in [sources.md](sources.md#sql-database-or-query-sql). On top of the check, the
session itself is read-only (SQLite: opened read-only with `PRAGMA query_only`).

### MySQL or MariaDB

Illustrative: not run against a live service.

**Goal:** the same teas from the shop database of an intranet MySQL server.

**Connection:** in Setup, Data tables, Connections, identifier `shop_mysql`, engine MySQL / MariaDB, server
`db.example.invalid`, database `shop`, user `import_reader` (a user with `SELECT` rights only), *Password: the name
of a secret* `shop_db`. Or, without a named connection, in the import's options:

| Option | Value |
|---|---|
| Database | `mysql` |
| Host, Port | `db.example.invalid`, `0` (3306) |
| Database | `shop` |
| User | `import_reader` |
| Password (name of a secret) | `shop_db` (its value in **New value**) |
| Query | the query above |
| Key column | `id` |

An intranet server must be listed in `[SqlSourceSettings] AllowedHosts[]`. The session runs `SET SESSION
TRANSACTION READ ONLY` and the time limit `MAX_EXECUTION_TIME`. **Expected result:** as the SQLite example.

### PostgreSQL

Illustrative: not run against a live service.

Options: *Database* `pgsql`, host `pg.example.invalid`, database `catalog`, user `import_reader`, password secret
`catalog_db`. PostgreSQL wants the column names you give it with `AS` in lower case or quoted:

```sql
SELECT id, name, origin, kind, price::text AS price, description FROM public.teas WHERE kind <> 'herbal' ORDER BY id
```

The session runs `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY` and `statement_timeout`. TimescaleDB is read
the same way ([sources.md](sources.md#sql-database-or-query-sql)).

### Microsoft SQL Server

Illustrative: not run against a live service.

Options: *Database* `sqlsrv`, host `mssql.example.invalid`, database `Shop`, user `import_reader` (role
`db_datareader`), password secret `shop_db`. T-SQL with `TOP` and bracketed names:

```sql
SELECT TOP 100 [Id] AS id, [Name] AS name, [Origin] AS origin, [Kind] AS kind, [Price] AS price, [Description] AS description
FROM [dbo].[Teas] WHERE [Kind] <> 'herbal' ORDER BY [Id]
```

The connection asks for `ApplicationIntent=ReadOnly`; `EXEC`, `SELECT ... INTO`, `sp_...` and locking hints are
refused by the query check.

### Oracle: oci8 and eZOracleDB

Illustrative: not run against a live service.

Two drivers can read Oracle, and the catalogue shows which one is used:

- **The SQL source's own options** (host, service name, user, secret) connect through the PHP extension **oci8**
  (else `pdo_oci`; `[SqlSourceSettings] OracleDriver`), in a connection of its own with `SET TRANSACTION READ ONLY`
  that is never committed.
- **A named connection** of the engine Oracle is opened by the kernel: with the extension **ezoracle** active through
  its handler **eZOracleDB**, else PDO `oci`. The import then only names the connection.

Options without a named connection: *Database* `oracle`, host `ora.example.invalid`, port `1521`, database (the
service name) `ORCLPDB1`, user `IMPORT_READER` (`CREATE SESSION` and `SELECT` on the table), password secret
`erp_db`. Oracle gives unquoted names in capitals, so name the columns:

```sql
SELECT id AS "id", name AS "name", origin AS "origin", kind AS "kind", TO_CHAR(price, 'FM9990.00') AS "price", description AS "description"
FROM shop.teas WHERE kind <> 'herbal' ORDER BY id
```

Easy Connect (`//ora.example.invalid:1521/ORCLPDB1` as *Database*, no host) works too, and a TNS name only when it is
listed in `[SqlSourceSettings] AllowedTnsNames[]`.

## MongoDB (`mongodb`)

Options: [sources.md, MongoDB collection](sources.md#mongodb-collection-mongodb). Illustrative: not run against a
live service.

**Goal:** the published articles of a newsroom database on MongoDB Atlas, newest first.

**Secret:** `mongo_reader`, the password of the database user `reader` (role `read` on the database `newsroom`).

**With a find** (the default mode):

| Option | Value |
|---|---|
| Server or URI | `mongodb+srv://cluster0.example.invalid/?retryWrites=true&w=majority` (no user or password in it) |
| TLS | on (`mongodb+srv://` always uses it) |
| User | `reader` |
| Password (name of a secret) | `mongo_reader` |
| Authentication database | `admin` |
| Database, Collection | `newsroom`, `articles` |
| Read | A find |
| Filter (JSON) | `{"status": "published"}` |
| Sort (JSON) | `{"published_at": -1}` |
| Paging | `skip` (needed for a sort other than `_id`) |
| Arrays | joined (`join`) |
| Key field | `_id` |

**With an aggregation** (*Read*: an aggregation): the filter, projection and sort become stages of the pipeline,
and the paging becomes `cursor`:

```json
[{"$match": {"status": "published"}}, {"$sort": {"published_at": -1}},
 {"$project": {"title": 1, "intro": 1, "body": 1, "author.name": 1, "tags": 1, "published_at": 1}}]
```

![The MongoDB source options filled in for an aggregation on Atlas: the URI without credentials, the user, the secret name mongo_reader, the pipeline and the paging](images/src-mongodb-options-aggregate.png)

**What comes back** (`share/examples/mongodb-articles.ndjson`, the first document, flattened):

```json
{"_id":"6717a1f0c2d4e5f601234561","title":"Green tea harvest begins","intro":"The first flush of the season is in.","body":"<p>Picking started this week in the high gardens.</p>","status":"published","author.name":"Editorial team","author.desk":"news","tags":"tea, harvest","published_at":"2026-04-02T08:30:00Z","price":"12.50","views":1450}
```

| Source field | Attribute (`article`) | Transforms |
|---|---|---|
| `title` | `title` | `trim` |
| `intro` | `intro` | `richtext` |
| `body` | `body` | `richtext` |
| `author.name` | `author` | |
| `published_at` | `publish_date` (where the class has it) | `date` |

Match: `mongo-{_id}`. **Expected result:** three articles; a later run updates the changed ones.

**`$out` is refused.** A pipeline that writes, such as `[{"$match": {...}}, {"$out": "articles_copy"}]`, is refused
with "MongoDB: the pipeline contains $out, only reading is allowed ($where, $function, $accumulator, $out and $merge
are refused)", and so are `$merge`, `$where` and `$function` at any depth. The hosts are checked first: with the
placeholder host of this page, Test connection stops at the address, here in dark mode:

![Test connection of the MongoDB source in dark mode: the SRV record of the placeholder cluster cannot be resolved](images/src-mongodb-test-connection-dark.png)

## Redis (`redis`)

Options: [sources.md, Redis keys](sources.md#redis-keys-redis). Illustrative: not run against a live service.

**Goal:** the product hashes `product:*` of a cache as products.

**Secret:** `redis_reader`, the password of the ACL user `reader` (`ACL SETUSER reader on >... ~product:* +@read`).

| Option | Value |
|---|---|
| Host, Port | `redis.example.invalid`, `6379` |
| TLS | on |
| User | `reader` |
| Password | `redis_reader` |
| Pattern | `product:*` |
| Types | `hash` |
| Key field | `_key` |

The source walks the keys with `SCAN ... MATCH product:*` (never `KEYS`) and reads each with `HGETALL`. Excerpt of
`share/examples/redis-products.ndjson`:

```json
{"_key":"product:1001","_type":"hash","_ttl":-1,"sku":"TEA-SENCHA-100","name":"Sencha","price":"9.90","stock":"42"}
```

Mapping (`product`): `name` to `name`, `sku` to `product_number`, `price` to `price` (`template(text="{price}|1|1")`).
Match: `redis-{sku}`. **Expected result:** one product per hash.

## Elasticsearch or OpenSearch (`elasticsearch`)

Options: [sources.md, Elasticsearch or OpenSearch index](sources.md#elasticsearch-or-opensearch-index-elasticsearch).
Illustrative: not run against a live service.

**Goal:** the published articles of a search index, newest first.

**Secret:** `search_api_key`, the encoded API key with the `read` privilege on `articles`.

| Option | Value |
|---|---|
| Address | `https://search.example.invalid:9200` |
| Index | `articles` |
| Authentication | `api_key` |
| API key secret | `search_api_key` |
| Query | `{"query": {"term": {"status": "published"}}, "sort": [{"published_at": "desc"}]}` |
| Source fields | `title`, `body`, `author.*`, `published_at`, `tags` |
| Paging | `auto` |
| Key field | `_id` |

Excerpt of `share/examples/search-articles.ndjson`:

```json
{"_id":"a-1001","_index":"articles","title":"Green tea harvest begins","body":"<p>The first flush of the year is picked in the hills.</p>","author.name":"Editorial team","published_at":"2026-04-02T08:00:00Z","status":"published","tags":["tea","harvest"]}
```

Mapping (`article`): `title` to `title`, `body` to `body` and `intro` (`richtext`), `tags` to `tags`
(`join(separator=", ")`). Match: `es-{_id}`. **Expected result:** three articles. A query with `aggs` or a script is
refused.

## CouchDB (`couchdb`) and Couchbase (`couchbase`)

Options: [CouchDB](sources.md#couchdb-database-couchdb), [Couchbase](sources.md#couchbase-query-couchbase).
Illustrative: not run against a live service.

**CouchDB goal:** the published articles of a database through a Mango query. **Secret:** `couch_reader` (a member
of the database, not an admin).

| Option | Value |
|---|---|
| Address | `https://couch.example.invalid:6984` |
| Database | `articles` |
| Authentication | `cookie`, user `reader`, password secret `couch_reader` |
| Mode | `mango` |
| Selector | `{"type": "article", "status": "published"}` |
| Sort | `[{"published_at": "desc"}]` |

Excerpt (`share/examples/couchdb-articles.ndjson`):

```json
{"_id":"article-2001","_rev":"3-9c1f0e2b7a4d5e6f8091a2b3c4d5e6f7","type":"article","title":"Spring tea tasting","status":"published","author.name":"Tasting desk","published_at":"2026-03-12T09:00:00Z","tags":"[\"tea\",\"tasting\"]"}
```

Mapping: `title` to `title`, `published_at` to `publish_date` (`date`). Match: `couch-{_id}`. **Expected result:**
three articles.

**Couchbase goal:** the active products of a bucket through SQL++. **Secret:** `couchbase_reader` (role Query Select).

| Option | Value |
|---|---|
| Query service | `https://cb.example.invalid:18093` |
| User, Password secret | `reader`, `couchbase_reader` |
| Statement | ``SELECT META(p).id AS id, p.sku, p.name, p.price, p.stock FROM `shop`.`inventory`.`products` AS p WHERE p.active = true ORDER BY p.sku`` |
| Paging | `keyset`, field `sku` |
| Key field | `sku` |

Excerpt (`share/examples/couchbase-products.ndjson`):

```json
{"sku":"TEA-301","name":"Sencha Asamushi","price":8.9,"active":true,"stock.store":14,"stock.warehouse":120,"tags":"[\"green\",\"japan\"]"}
```

Every request carries `readonly=true`, so the Query service itself refuses a statement that writes. Mapping
(`product`): `name`, `sku` to `product_number`, `price` (`template(text="{price}|1|1")`). **Expected result:** three
products.

## Amazon DynamoDB (`dynamodb`)

Options: [sources.md, Amazon DynamoDB table](sources.md#amazon-dynamodb-table-dynamodb). Illustrative: not run
against a live service.

**Goal:** the products of a DynamoDB table that cost less than 20.

**Secret:** `dynamo_reader_secret`, the secret access key of an IAM user whose policy allows only `Scan`, `Query`
and `DescribeTable` on the table (the policy is in sources.md).

| Option | Value |
|---|---|
| Region | `eu-central-1` |
| Access key ID | `AKIAEXAMPLEREPLACE01` |
| Secret access key | `dynamo_reader_secret` |
| Table | `products` |
| Operation | `scan` |
| Filter | `#s = :p AND price < :max` |
| Names | `{"#s": "status"}` |
| Values | `{":p": {"S": "published"}, ":max": {"N": "20"}}` |
| Key field | `sku` |

Excerpt (`share/examples/dynamodb-products.ndjson`):

```json
{"sku":"tea-1001","title":"Sencha Morning","price":"12.50","in_stock":true,"tags":"green, japan","dimensions.weight_g":"100","dimensions.box":"tin","variants":"[{\"size\":\"50g\",\"price\":\"6.90\"},{\"size\":\"100g\",\"price\":\"12.50\"}]","discontinued":null}
```

Mapping (`product`): `title` to `name`, `sku` to `product_number`, `price` to `price` (`template(text="{price}|1|1")`),
`tags` to `tags`. Match: `dynamo-{sku}`. **Expected result:** three products. Numbers stay decimal text (`12.50`).

## Firestore (`firestore`)

Options: [sources.md, Firestore collection](sources.md#firestore-collection-firestore). Illustrative: not run
against a live service.

**Goal:** the published articles of a Firestore collection.

**Secret:** `firestore_reader`, the JSON key of a service account with the role *Cloud Datastore Viewer* (`roles/datastore.viewer`; the whole
key file is the secret's value).

| Option | Value |
|---|---|
| Project | `example-tea-project` |
| Service account key | `firestore_reader` |
| Mode | `query` |
| Collection | `articles` |
| Where | `{"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}` |
| Order by | `published_at:desc` |
| Key field | `_id` |

Excerpt (`share/examples/firestore-articles.ndjson`):

```json
{"_id":"a-2001","_path":"articles/a-2001","title":"Brewing green tea","status":"published","views":1250,"rating":4.6,"published_at":"2026-02-01T09:00:00Z","author.name":"Tea desk","author.ref":"authors/au-1","tags":"[\"green\",\"brewing\"]","location":"48.137,11.575"}
```

Mapping: `title` to `title`, `author.name` to `author`, `published_at` to `publish_date` (`date`). Match:
`firestore-{_id}`. **Expected result:** three articles.

## Azure Cosmos DB (`cosmosdb`)

Options: [sources.md, Azure Cosmos DB container](sources.md#azure-cosmos-db-container-cosmosdb). Illustrative: not
run against a live service.

**Goal:** the open orders of a Cosmos DB container (API for NoSQL) as a list for the shop team.

**Secret:** `cosmos_readonly_key`, the account's **read-only** key (Keys, Read-only keys).

| Option | Value |
|---|---|
| Endpoint | `https://example-tea.documents.azure.com` |
| Database, Container | `shop`, `orders` |
| Authentication | `master_key` |
| Key secret | `cosmos_readonly_key` |
| Query | `SELECT c.id, c.customer.id, c.status, c.total, c.currency, c.lines FROM c WHERE c.status = 'open'` |
| Key field | `id` |

Excerpt (`share/examples/cosmosdb-orders.ndjson`):

```json
{"id":"order-5001","customer.id":"c-17","status":"open","total":"42.80","currency":"EUR","lines":"[{\"sku\":\"TEA-301\",\"qty\":2},{\"sku\":\"TEA-302\",\"qty\":1}]","_ts":1775118600}
```

Mapping into a data table or a `folder` per order: `id` to `name`, `total` to `short_name`. The customer's e-mail
address in the snapshot is personal data: leave it unmapped. **Expected result:** three records. A Cosmos DB account
of the API for MongoDB is read with the MongoDB source instead.

## Cassandra or ScyllaDB (`cassandra`)

Options: [sources.md, Cassandra or ScyllaDB query](sources.md#cassandra-or-scylladb-query-cassandra). Illustrative:
not run against a live service.

**Goal:** the tasting events of one day from a Cassandra table.

**Secret:** `cassandra_reader` (a role with `SELECT` on the keyspace).

| Option | Value |
|---|---|
| Hosts | `cassandra1.example.invalid, cassandra2.example.invalid:9142` |
| TLS | on |
| User, Password secret | `reader`, `cassandra_reader` |
| Keyspace | `events` |
| Query | `SELECT event_id, day, kind, title, starts_at, seats, price, tags FROM events_by_day WHERE day = '2026-05-04'` |
| Consistency | `LOCAL_QUORUM` |
| Key field | `event_id` |

Excerpt (`share/examples/cassandra-events.ndjson`):

```json
{"event_id":"5b6e0f2a-1c3d-11f0-8de9-0242ac120002","day":"2026-05-04","kind":"tasting","title":"Spring tasting","starts_at":"2026-05-04T16:00:00Z","seats":24,"price":"15.00","tags":"[\"green\",\"tasting\"]","venue.city":"Example City"}
```

Mapping: `title` to `title`, `starts_at` to `publish_date` (`date`). Match: `cassandra-{event_id}`. **Expected
result:** three events. ScyllaDB is read the same way.

## Neo4j or Memgraph (`neo4j`)

Options: [sources.md, Neo4j and Memgraph](sources.md#neo4j-and-memgraph-neo4j). Illustrative: not run against a live
service.

**Goal:** the published article nodes of a graph, with their tags.

**Secret:** `neo4j_reader` (a user with the reader role).

| Option | Value |
|---|---|
| Server | `neo4j` (or `memgraph`, Bolt only) |
| Address | `https://graph.example.invalid:7473` (or `neo4j+s://graph.example.invalid:7687`) |
| Database | `neo4j` |
| User, Password secret | `reader`, `neo4j_reader` |
| Query | `MATCH (a:Article {status: $status}) RETURN a ORDER BY a.published_at DESC` |
| Parameters | `{"status": "published"}` |
| Key field | `id` |

Excerpt (`share/examples/neo4j-articles.ndjson`):

```json
{"_element_id":"4:0f2c9a1e-7b3d-4c55-9e01-5a6b7c8d9e0f:11","_labels":["Article"],"id":"art-11","title":"Brewing green tea at the right temperature","status":"published","published_at":"2026-03-02T09:00:00Z","tags":["tea","brewing"]}
```

Mapping: `title` to `title`, `tags` to `tags` (`join(separator=", ")`). Match: `graph-{id}`. A query with `CREATE`,
`MERGE`, `SET` or `DELETE` is refused. **Expected result:** three articles.

## ArangoDB (`arangodb`)

Options: [sources.md, ArangoDB](sources.md#arangodb-arangodb). Illustrative: not run against a live service.

**Goal:** the published articles of an ArangoDB collection.

**Secret:** `arango_reader` (a user with read access to the database).

| Option | Value |
|---|---|
| Address | `https://arango.example.invalid:8529` |
| Database | `newsroom` |
| Authentication | `basic`, user `reader`, password secret `arango_reader` |
| Query | `FOR a IN articles FILTER a.status == @status SORT a.published_at DESC RETURN a` |
| Bind variables | `{"status": "published"}` |
| Key field | `_key` |

Excerpt (`share/examples/arangodb-articles.ndjson`):

```json
{"_key":"1001","_id":"articles/1001","_rev":"_hX2kQ3a---","title":"A guide to white tea","status":"published","author.name":"Tea desk","tags":["white tea","guide"],"published_at":"2026-02-11T08:30:00Z"}
```

Mapping: `title` to `title`, `author.name` to `author`. Match: `arango-{_key}`. `INSERT`, `UPDATE`, `REPLACE`,
`REMOVE` and `UPSERT` are refused. **Expected result:** three articles.

## ClickHouse (`clickhouse`)

Options: [sources.md, ClickHouse](sources.md#clickhouse-clickhouse). Illustrative: not run against a live service.

**Goal:** yesterday's most read pages from a ClickHouse table of page views, as a "most read" list.

**Secret:** `clickhouse_reader` (a user with `readonly` set).

| Option | Value |
|---|---|
| Address | `https://clickhouse.example.invalid:8443` |
| Database | `analytics` |
| User, Password secret | `reader`, `clickhouse_reader` |
| Query | `SELECT concat(toString(day), ':', path) AS id, path, day, count() AS views, uniq(visitor) AS visitors FROM page_views WHERE day = yesterday() GROUP BY day, path ORDER BY views DESC LIMIT 20` |
| Read-only | `2` |
| Key field | `id` |

Excerpt (`share/examples/clickhouse-page-views.ndjson`):

```json
{"id":"2026-05-01:/tea/green","path":"/tea/green","day":"2026-05-01","views":412,"visitors":"388"}
```

The rows go best into a data table (target *Rows of a data table*); with a mapping, `path` to `name` and `views` to
a number attribute. **Expected result:** up to twenty rows, refreshed daily by a schedule.

## BigQuery (`bigquery`)

Options: [sources.md, BigQuery query](sources.md#bigquery-query-bigquery). Illustrative: not run against a live
service.

**Goal:** the paid orders of the last week from BigQuery.

**Secret:** `bigquery_reader`, the JSON key of a service account with `roles/bigquery.jobUser` on the project and `roles/bigquery.dataViewer` on the dataset.

| Option | Value |
|---|---|
| Project | `example-tea-project` |
| Service account key | `bigquery_reader` |
| Location | `EU` |
| Query | ``SELECT order_id, customer.name AS customer_name, customer.country, total, created_at, paid FROM `example-tea-project.shop.orders` WHERE paid AND created_at > TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 7 DAY) ORDER BY created_at`` |
| Maximum bytes billed | `0` (the setting's 1 GiB) |
| Key field | `order_id` |

Test connection is a free dry run that shows the columns and the bytes the query would process. Excerpt
(`share/examples/bigquery-orders.ndjson`):

```json
{"order_id":"9000001","customer.country":"DE","total":"42.30","created_at":"2026-04-02T08:30:00Z","items":"[{\"sku\":\"tea-1001\",\"qty\":\"2\"},{\"sku\":\"tea-1002\",\"qty\":\"1\"}]","paid":true}
```

**Expected result:** one record per order; `NUMERIC` stays decimal text (`42.30`).

## Snowflake (`snowflake`)

Options: [sources.md, Snowflake](sources.md#snowflake-snowflake). Illustrative: not run against a live service.

**Goal:** the orders of a Snowflake warehouse.

**Secrets:** `snowflake_key`, the PEM private key of the user's key pair (and `snowflake_key_passphrase` when the
key is encrypted).

| Option | Value |
|---|---|
| Account | `exampleorg-teaaccount` |
| User | `IMPORT_READER` |
| Private key secret | `snowflake_key` |
| Warehouse, Database, Schema, Role | `READ_WH`, `SHOP`, `PUBLIC`, `READER` |
| Query | `SELECT order_id, customer_ref, total, ordered_at, items FROM orders WHERE ordered_at >= DATEADD(day, -7, CURRENT_TIMESTAMP()) ORDER BY order_id` |
| Key field | `ORDER_ID` (Snowflake names columns in upper case) |

Excerpt (`share/examples/snowflake-orders.ndjson`):

```json
{"ORDER_ID":"1001","CUSTOMER_REF":"C-0042","TOTAL":"48.50","ORDERED_AT":"2026-04-01T10:15:00Z","ITEMS":[{"sku":"TEA-GRN-100","qty":2},{"sku":"TEA-OOL-50","qty":1}]}
```

**Expected result:** three records; map them by their upper case names (`ORDER_ID`, `TOTAL`).

## InfluxDB (`influxdb`)

Options: [sources.md, InfluxDB](sources.md#influxdb-influxdb). Illustrative: not run against a live service.

**Goal:** the hourly temperature and humidity of the tea store, one row per time.

**Secret:** `influx_read_token`, an API token with read access to the bucket.

| Option | Value |
|---|---|
| Address | `https://influx.example.invalid:8086` |
| Language | `flux` |
| Organization | `example-tea` |
| Authentication | `token`, secret `influx_read_token` |
| Query | `from(bucket: "climate") \|> range(start: -1d) \|> filter(fn: (r) => r._measurement == "climate" and r.room == "store") \|> aggregateWindow(every: 1h, fn: mean)` |
| Pivot | on (one row per time with a column per field) |

Excerpt (`share/examples/influxdb-climate.ndjson`):

```json
{"_start":"2026-06-01T00:00:00Z","_stop":"2026-06-02T00:00:00Z","_time":"2026-06-01T08:00:00Z","_measurement":"climate","room":"store","humidity":48.5,"temperature":19.2}
```

**Expected result:** twenty-four rows per day, best kept in a data table. InfluxQL (`language` `influxql`) works with
a `SELECT` against InfluxDB 1.x.

<a id="duckdb"></a>

## DuckDB over Parquet or CSV (`duckdb`)

Options: [sources.md, DuckDB query over files](sources.md#duckdb-query-over-files-duckdb).

**Goal:** the teas of a nightly export that another system writes as Parquet files, or the example CSV.

**Secret:** none; the files must lie inside `[SourceSettings] AllowedPaths[]` (for example `var/import`).

**Not run on the documentation server:** it has neither the duckdb program nor PHP FFI with libduckdb, and the
catalogue says so in grey, with what the host needs:

![The DuckDB card of the catalogue: no driver for the duckdb program nor for libduckdb through PHP FFI, with what the host needs](images/src-catalogue-duckdb-card-drivers.png)

| Option | Value (Parquet) | Value (the CSV example) |
|---|---|---|
| Files | `var/import/exports/teas-*.parquet` | `var/import/duckdb-teas.csv` |
| File type | `parquet` | `auto` (by the name) |
| Query | `SELECT id, name, origin, kind, price FROM data WHERE kind <> 'herbal' ORDER BY id` | the same |
| Page size | `10000` | `10000` |
| Key column | `id` | `id` |

The files are the view `data` of the query; a pattern reads every file it matches as one table.

![The DuckDB source options for a Parquet pattern and the query, with the driver rows under Test connection](images/src-duckdb-options-drivers.png)

The example file `share/examples/duckdb-teas.csv`:

```
id,name,origin,kind,price
1,Sencha,Japan,green,6.50
2,Darjeeling First Flush,India,black,9.80
3,Tie Guan Yin,China,oolong,8.20
4,Rooibos,South Africa,herbal,4.10
5,Gyokuro,Japan,green,14.00
```

Mapping (`product`): `name` to `name`, `id` to `product_number` (`template(text=TEA-{id})`), `price` to `price`
(`template(text="{price}|1|1")`). **Expected result:** four products (Rooibos is herbal). A query with `COPY`,
`ATTACH`, `read_csv(...)` or `FROM 'other.csv'` is refused: the query reads only the files of the import.

## LDAP or Active Directory (`ldap`)

Options: [sources.md, LDAP directory](sources.md#ldap-directory-ldap). Illustrative: not run against a live service.

**Goal:** the staff directory as contact pages: name, title and e-mail address of everyone below `ou=Staff`.

**Secret:** `ldap_reader`, the password of the service account `cn=reader,ou=Services,dc=example,dc=org` (read
access to `ou=Staff` only).

| Option | Value |
|---|---|
| URI | `ldaps://ldap.example.invalid` (Active Directory: `ldaps://dc1.example.invalid`) |
| Bind DN | `cn=reader,ou=Services,dc=example,dc=org` (Active Directory also `reader@example.invalid`) |
| Password | `ldap_reader` |
| Base DN | `ou=Staff,dc=example,dc=org` |
| Filter | `(&(objectClass=inetOrgPerson)(title=*))` (Active Directory: `(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))`, the enabled users) |
| Attributes | `uid`, `cn`, `givenName`, `sn`, `mail`, `title` (one per line) |
| Scope | `sub` |
| Page size | `500` |
| Key field | `uid` (Active Directory: `objectGUID_text`) |

![The LDAP source options with the URI, bind DN, secret name, base DN, filter and attributes](images/src-ldap-options.png)

Excerpt (`share/examples/ldap-staff.ndjson`, invented entries):

```json
{"dn":"uid=sample.one,ou=Staff,dc=example,dc=org","uid":"sample.one","cn":"Sample One","givenName":"Sample","sn":"One","mail":"sample.one@example.invalid","title":"Editor"}
```

Mapping (a contact class, or `folder`): `cn` to `name`, `title` to `short_name`. These are **personal data**: map only
what the site may show. Match: `ldap-{uid}`. **Expected result:** one object per person; someone who leaves the
directory is no longer read (remove such objects by hand or with an undo).

## See also

- [Worked examples: APIs and hosted services](source-examples-apis.md)
- [Worked examples: platforms, commerce and documents](source-examples-platforms.md)
- [Sources](sources.md), [The import editor](editor.md), [Mappings](mappings.md), [Runs](runs.md)
