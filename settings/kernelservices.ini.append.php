<?php /* #?ini charset="utf-8"?

# The services of 7x SQLI Import (Exponential's kernel service container, settings/kernelservices.ini).
# A site adds a source, a handler or a transform with a block of its own tagged sqliimport.source,
# sqliimport.handler or sqliimport.transform, and can replace these by a block of the same name
# (Enabled=false switches one off; sqliimport.ini [Source_<id>] Status=example or disabled does too).

[Service_sqliimport.registry]
Class=expImportRegistry
Scope=worker
Description=7x SQLI Import: the catalogue of sources, handlers and transforms

# 7x SQLI Import in Exponential's transfer registry (kernel 6.0.15 and later). Older kernels ignore the block.
[Service_transfer.sqliimport]
Class=expKernelTransferTool
Factory=expKernelTransferTool::fromSettings
Arguments[]=transfer.sqliimport
Scope=worker
Tags[]=transfer.tool
Description=Bring rows of a spreadsheet, CSV, XML, JSON or a feed in as content items: map the columns to a class, preview, run now or on a schedule, undo.
Name=Imports (7x SQLI Import)
Direction=import
Scopes[]=subtree
Scopes[]=class
Scopes[]=data
Formats[]=CSV
Formats[]=TSV
Formats[]=XLSX
Formats[]=ODS
Formats[]=XML
Formats[]=JSON
Formats[]=NDJSON
Formats[]=YAML
Formats[]=TOML
Formats[]=RSS
Formats[]=OPML
Formats[]=WXR
Formats[]=Markdown
Formats[]=iCalendar
Formats[]=vCard
Formats[]=ZIP
Formats[]=SQL
Formats[]=IMAP
Formats[]=Elasticsearch
Formats[]=OpenSearch
Formats[]=Parquet
Formats[]=DuckDB
Formats[]=MongoDB
Formats[]=Redis
Formats[]=CouchDB
Formats[]=Couchbase
Formats[]=Cosmos DB
Formats[]=Neo4j
Formats[]=Memgraph
Formats[]=ArangoDB
Formats[]=ClickHouse
Formats[]=Snowflake
Formats[]=InfluxDB
Formats[]=LDAP
Formats[]=Airtable
Formats[]=Notion
Formats[]=DynamoDB
Formats[]=Firestore
Formats[]=BigQuery
Formats[]=Firebase
Formats[]=Cassandra
Formats[]=ScyllaDB
Formats[]=Supabase
Formats[]=PostgREST
Formats[]=Baserow
Formats[]=Salesforce
Formats[]=HubSpot
Formats[]=YouTube
Formats[]=REST API
Formats[]=Avro
Formats[]=Arrow
Formats[]=Feather
EntryURL=sqliimport/list
Permission=sqliimport/manage|sqliimport/run|sqliimport/manageimports|sqliimport/listimports
HelpPage=ext/sqliimport/README.md
RoundTrip[]=transfer.xrowextract
RoundTrip[]=transfer.bccie
RoundTrip[]=transfer.rss_export
RoundTrip[]=transfer.sqliimport
Keywords[]=import csv
Keywords[]=spreadsheet
Keywords[]=excel
Keywords[]=from another system
Keywords[]=shop
Keywords[]=crm
Keywords[]=wordpress
Keywords[]=mapping
Keywords[]=Tabelle
Keywords[]=importieren
Keywords[]=Zuordnung
Actions[]=container|import|Import into this folder…|sqliimport/edit/new/(parent)/%nodeID%|sqliimport/manage
Actions[]=class|import|Import objects of this class…|sqliimport/edit/new/(class)/%classIdentifier%|sqliimport/manage
Weight=15

# The formats of the catalogue. A format is one source class plus one block here:
#   Factory=expImportSourceEntry::of, Arguments[]=<the source class>[, Arguments[]=<a variant of it>]
# What the catalogue shows (description, group, example, preset mapping) comes from the class.

# data
[Service_sqliimport.source.csv]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: CSV and delimited text

[Service_sqliimport.source.tsv]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCsvSource
Arguments[]=tsv
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: TSV (tab separated values)

[Service_sqliimport.source.spreadsheet]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSpreadsheetSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: XLSX and ODS spreadsheets

[Service_sqliimport.source.json]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportJsonSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: JSON

[Service_sqliimport.source.ndjson]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportJsonSource
Arguments[]=ndjson
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: JSON Lines (NDJSON)

[Service_sqliimport.source.avro]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportAvroSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Apache Avro object container files (.avro)

[Service_sqliimport.source.arrow]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportArrowSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Apache Arrow IPC and Feather v2 files (.arrow, .feather, .arrows)

[Service_sqliimport.source.elasticsearch]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSearchSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Elasticsearch or OpenSearch index (read-only search)

[Service_sqliimport.source.duckdb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportDuckDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: DuckDB query over Parquet, CSV or JSON files

[Service_sqliimport.source.google_sheets]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportGoogleSheetsSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Google Sheets by its link

[Service_sqliimport.source.yaml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYamlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: YAML data files

[Service_sqliimport.source.toml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYamlSource
Arguments[]=toml
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: TOML data files

[Service_sqliimport.source.xml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportXmlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: XML

# content
[Service_sqliimport.source.rss]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: RSS and Atom feeds

[Service_sqliimport.source.opml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportOpmlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: OPML outlines and feed lists

[Service_sqliimport.source.wxr]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWxrSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: WordPress export (WXR)

[Service_sqliimport.source.markdown]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportMarkdownSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Markdown files with front matter

[Service_sqliimport.source.podcast]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=podcast
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: podcast feeds

[Service_sqliimport.source.video_feed]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=video_feed
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: YouTube and Vimeo channel feeds

[Service_sqliimport.source.imap]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportImapSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: e-mail inbox (IMAP)

# platforms
[Service_sqliimport.source.xrowextract]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportXrowextractSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: export files of Export (xrowextract)

[Service_sqliimport.source.sql]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSqlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: SQL database or query (MySQL, MariaDB, PostgreSQL, SQLite)

[Service_sqliimport.source.mongodb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportMongoDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: MongoDB collection (read-only find with filter, projection and sort)

[Service_sqliimport.source.redis]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRedisSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Redis keys (SCAN by pattern, read-only commands)

[Service_sqliimport.source.couchdb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCouchDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: CouchDB database (all documents, a view or a Mango query; read-only)

[Service_sqliimport.source.couchbase]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCouchbaseSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Couchbase SQL++ query through the Query service (readonly=true, SELECT only)

[Service_sqliimport.source.cosmosdb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCosmosDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Azure Cosmos DB container, API for NoSQL (SELECT query, read-only)

[Service_sqliimport.source.dynamodb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportDynamoDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: items of an Amazon DynamoDB table (Scan or Query only, Signature Version 4)

[Service_sqliimport.source.firestore]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportFirestoreSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: documents of a Google Cloud Firestore collection (runQuery or list, read-only)

[Service_sqliimport.source.bigquery]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportBigQuerySource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: rows of a Google BigQuery query (a single SELECT, bytes billed limit)

[Service_sqliimport.source.firebase_rtdb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportFirebaseSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: children of a Firebase Realtime Database path (REST GET only)

[Service_sqliimport.source.cassandra]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCassandraSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: rows of a CQL SELECT against Cassandra or ScyllaDB (read-only)

[Service_sqliimport.source.neo4j]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportNeo4jSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Neo4j or Memgraph graph (read-only Cypher over HTTP or Bolt)

[Service_sqliimport.source.arangodb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportArangoDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: ArangoDB query (read-only AQL through the cursor API)

[Service_sqliimport.source.clickhouse]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportClickHouseSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: ClickHouse query (SELECT over the HTTP interface with readonly)

[Service_sqliimport.source.snowflake]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSnowflakeSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Snowflake query (SQL API v2 with key-pair authentication, reading statements only)

[Service_sqliimport.source.influxdb]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportInfluxDbSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: InfluxDB query (read-only Flux or InfluxQL)

[Service_sqliimport.source.ldap]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportLdapSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: entries of an LDAP directory (read-only search)

[Service_sqliimport.source.cie]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCieSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: form submissions of CIE (bccie)

[Service_sqliimport.source.exponential_rss]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=exponential_rss
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: RSS export of another Exponential site

[Service_sqliimport.source.sitemap]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSitemapSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: the pages of a site by its sitemap (crawl)

[Service_sqliimport.source.webpage]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWebPageSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: fields picked from web pages (scraper)

[Service_sqliimport.source.exponential_site]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportExponentialSiteSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: content of another Exponential site through its REST API

[Service_sqliimport.source.ezpkg]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportEzpkgSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: content objects of an Exponential package (.ezpkg)

[Service_sqliimport.source.drupal]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportDrupalSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Drupal content through JSON:API

[Service_sqliimport.source.joomla]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportJoomlaSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Joomla articles exported as CSV or XML

[Service_sqliimport.source.airtable]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportAirtableSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: records of an Airtable table (Web API, read-only)

[Service_sqliimport.source.notion]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportNotionSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: pages of a Notion database (API, read-only)

[Service_sqliimport.source.supabase]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSupabaseSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: rows of a Supabase or PostgREST table (REST API, read-only)

[Service_sqliimport.source.baserow]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportBaserowSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: rows of a Baserow table (REST API, read-only)

[Service_sqliimport.source.salesforce]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSalesforceSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: records of a Salesforce SOQL query (REST API, read-only)

[Service_sqliimport.source.hubspot]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportHubSpotSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: objects of the HubSpot CRM (API v3, read-only)

[Service_sqliimport.source.youtube]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYouTubeSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: videos of a YouTube channel, playlist or search (Data API v3, read-only)

[Service_sqliimport.source.youtube_page]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYouTubePageSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: videos listed on a youtube.com page (scraper, off unless [YouTubeSettings] AllowPageScraper=enabled)

[Service_sqliimport.source.rest_api]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRestApiSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: records of any JSON REST API, paged (read-only)

[Service_sqliimport.source.media]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportMediaSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: media files with EXIF and IPTC
[Service_sqliimport.source.ical]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportIcalSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: iCalendar events and tasks (.ics)

[Service_sqliimport.source.vcard]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportVcardSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: vCard contacts (.vcf)

# commerce
[Service_sqliimport.source.google_merchant]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportGoogleMerchantSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Google Merchant product feeds

[Service_sqliimport.source.shopify]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportShopifyCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Shopify product CSV exports

[Service_sqliimport.source.woocommerce]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWooCommerceCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: WooCommerce product CSV exports

*/ ?>
