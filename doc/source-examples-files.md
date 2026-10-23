# Worked examples: files

This page shows one complete import for every file format of 7x SQLI Import: delimited text (CSV, TSV),
spreadsheets (XLSX, ODS), JSON and JSON Lines, XML, YAML, TOML, OPML, Apache Avro and Arrow, a ZIP of photos with
their EXIF and IPTC fields, a folder of Markdown files, a WordPress export, an iCalendar file and a vCard file. Each
example says what it achieves, shows real lines of the example file, the exact values to give the import editor, the
mapping lines, and what the dry run or the run gave, with screenshots of the documentation server.

Every option is described in [sources.md](sources.md); each example links to its section there. The other worked
examples: [feeds and web pages](source-examples-feeds-web.md), [databases](source-examples-databases.md),
[APIs and hosted services](source-examples-apis.md) and [platforms, commerce and documents](source-examples-platforms.md).
German: [ger-de/source-examples-files.md](ger-de/source-examples-files.md).

**What ran.** Every example on this page ran on the documentation server as an import named "Docs: ...", with the
example file of the catalogue (`share/examples/` of the extension, also offered for download on each card of
**Imports, Catalogue**) as its upload. Most ran as a **dry run**, which publishes nothing; the CSV and the media
examples also ran for real into a hidden folder, so the result in the content tree can be shown.

## Contents

- [How every file example works](#how-every-file-example-works)
- [CSV (`csv`)](#csv-csv)
- [TSV (`tsv`)](#tsv-tsv)
- [Spreadsheets: XLSX and ODS (`spreadsheet`)](#spreadsheets-xlsx-and-ods-spreadsheet)
- [JSON Lines (`ndjson`)](#json-lines-ndjson)
- [JSON (`json`)](#json-json)
- [XML (`xml`)](#xml-xml)
- [YAML (`yaml`)](#yaml-yaml)
- [TOML (`toml`)](#toml-toml)
- [OPML (`opml`)](#opml-opml)
  - [The outlines as link objects](#the-outlines-as-link-objects)
  - [One feed import per outline](#one-feed-import-per-outline)
- [Apache Avro (`avro`) and Apache Arrow (`arrow`)](#apache-avro-avro-and-apache-arrow-arrow)
- [Parquet, through DuckDB](#parquet-through-duckdb)
- [Photos in a ZIP with EXIF and IPTC (`media`)](#photos-in-a-zip-with-exif-and-iptc-media)
- [A folder of Markdown files (`markdown`)](#a-folder-of-markdown-files-markdown)
- [WordPress export (`wxr`)](#wordpress-export-wxr)
- [iCalendar (`ical`)](#icalendar-ical)
- [vCard (`vcard`)](#vcard-vcard)
- [See also](#see-also)

## How every file example works

1. **Imports, Catalogue** lists every format with its example files. **New import** on a card opens the editor with
   that format chosen ([editor.md](editor.md)).
2. Step **Source**: a name, *Where the data comes from* **An uploaded file** (or an address, read at every run), the
   file, and the options of the format. The values of each example are given in a table.
3. Step **Processing**: the parent node of new content; the examples keep the defaults (update existing content,
   200 records per chunk, `[RunSettings] ChunkSize`).
4. Step **Mapping**: the content class, *How a record finds its object* (a remote ID pattern with fields of the
   record in braces), and one line per attribute: the source field, the transforms as one line of text
   ([transforms.md](transforms.md)), a default and *Required*. A format with a preset fills the lines when the import
   is first saved; check them here.
5. Step **Preview**: the *Sample of the source* (the first records as the source reads them, with what it detected)
   and the *Dry run of the first records* (what would be created, updated or left unchanged, field by field).
6. **Dry run** or **Run now** in the list of imports, or **Save and run** in the review step. The run page counts the
   records ([runs.md](runs.md)).

In the mapping tables below, *Transforms* is the one-line text exactly as it is typed into the grid. An empty cell
means the line has none.

## CSV (`csv`)

Options: [sources.md, CSV, TSV and delimited text](sources.md#csv-tsv-and-delimited-text-csv-tsv).
Docs import "Docs: Products (CSV)": run #164 (real), 3 created.

**Goal:** the three products of a shop's CSV export become three folders, with the price in the summary, and a
second run of the same file changes nothing.

**Input** (`share/examples/products.csv`, the whole file; note the quoted comma in line 3):

```
id,title,price,active
1,Café crème,9.50,1
2,"Tea, green",4.20,1
3,Hot chocolate,5.00,0
```

**Editor steps:**

| Step, field | Value |
|---|---|
| Source, source type | *CSV and delimited text* (group Data) |
| Where the data comes from | An uploaded file: `products.csv` |
| Delimiter | Detect (the preview reports `delimiter: ,`) |
| Enclosure | `"` |
| Encoding | Detect |
| The first line names the columns | on |
| Key field | `id` |
| Mapping, content class | `folder` |
| How a record finds its object | Remote ID, pattern `sq688doc-csv-{id}` |

![The source step of the CSV example: an uploaded file products.csv, delimiter and encoding detected, the first line names the columns, key field id](images/src-csv-source.png)

**Mapping lines:**

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` (Name) | `title` | `trim` | yes |
| `short_description` (Summary) | `price` | `number(decimals=2) \| suffix(text=" EUR") \| richtext` | |
| `show_children` (Display sub items) | `active` | `bool` | |

**Result.** The real run #164 created three folders below the import's parent: "Café crème", "Tea, green" and
"Hot chocolate", with the remote IDs `sq688doc-csv-1` to `sq688doc-csv-3`:

![The run page of run #164: completed, 3 created, 0 updated, 0 unchanged, 0 skipped, 0 failed, with one row per record](images/src-csv-run.png)

![The sub items of the target folder: the three folders Café crème, Tea, green and Hot chocolate](images/src-csv-tree.png)

Opened again afterwards, the preview shows the same three records and says each is **Unchanged**: the content API
publishes a new version only when a field really changed, so a nightly run of an unchanged file costs nothing.

![The preview after the run: the sample of three records with the detected delimiter, and a dry run in which all three are unchanged](images/src-csv-preview.png)

## TSV (`tsv`)

Options: [sources.md, CSV, TSV and delimited text](sources.md#csv-tsv-and-delimited-text-csv-tsv).
Docs import "Docs: Products (TSV)": dry run #124, 3 would be created.

**Goal:** the same products from a tab-separated file, as spreadsheet programs export it with "Text (tab
delimited)".

**Input** (`share/examples/products.tsv`; the columns are separated by tab characters, so the comma in "Tea, green"
needs no quotes):

```
id	title	price	active
1	Café crème	9.50	1
2	Tea, green	4.20	1
```

**Editor steps:** source type *TSV file (tab separated)*, an uploaded file `products.tsv`, *Delimiter* **Tab** (the
default of this format; in a source configuration written by hand it is `"delimiter": "tab"`, not `"\t"`),
*Key field* `id`. Content class `folder`, remote ID `sq688doc-tsv-{id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `short_name` | `price` | `number(decimals=2)` | |

**Result:** the dry run of the preview: three folders would be created, with their names and short names.

![The preview of the TSV example: three records with id, title, price and active, and a dry run in which each would be created](images/src-tsv-preview.png)

## Spreadsheets: XLSX and ODS (`spreadsheet`)

Options: [sources.md, Spreadsheets](sources.md#spreadsheets-spreadsheet).
Docs imports "Docs: Products (XLSX)" (dry run #165) and "Docs: Products (ODS)" (dry run #166), 3 would be created
each.

**Goal:** the products sheet of an Excel workbook, without saving it as CSV first.

**Input** (`share/examples/products.xlsx`): a workbook with two sheets, *Products* and *Other*. The first sheet:

| id | title | price | active |
|---|---|---|---|
| 1 | Café crème | 9.5 | 1 |
| 2 | Second | 0 | |
| 3 | Inline | 12 | |

**Editor steps:**

| Step, field | Value |
|---|---|
| Source type | *Spreadsheet (XLSX, ODS)* |
| Where the data comes from | An uploaded file: `products.xlsx` (or `products.ods`) |
| Sheet | `1` (the first; the name `Products` works the same) |
| The first row names the columns | on |
| Key field | `id` |
| Content class, match | `folder`, remote ID `sq688doc-xlsx-{id}` |

![The source step of the XLSX example: an uploaded file products.xlsx, sheet 1, the first row names the columns, key field id](images/src-xlsx-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `short_description` | `price` | `number(decimals=2) \| richtext` | |

**Result.** The preview names the format and the sheets the file has (`format: xlsx · sheets: Products, Other`); a
sheet name that does not exist would be refused with that list. The values are those stored in the cells: `9.5`,
not the display format of the cell. Three folders would be created.

![The preview of the XLSX example: format xlsx with the sheets Products and Other, three records, and a dry run in which each would be created](images/src-xlsx-preview.png)

The ODS file gives the same records (`format: ods`); there a date cell comes as ISO text (`2026-10-21`), which
`date` reads without a format, while an Excel date is a day number for `date(format=excel)`.

## JSON Lines (`ndjson`)

Options: [sources.md, JSON and JSON Lines](sources.md#json-and-json-lines-json-ndjson).
Docs import "Docs: Articles (NDJSON)": dry run #167, 2 would be created.

**Goal:** an export with one JSON object per line, read as a stream (for exports of any size), with a nested field,
a list and a date.

**Input** (`share/examples/articles.ndjson`, both lines):

```json
{"id":"a-100","title":"Spring opening hours","published":"2026-03-20T09:00:00Z","author":{"name":"Desk"},"tags":["news","opening"],"body":"<p>From April the shop opens at <b>8 am</b>.</p>"}
{"id":"a-101","title":"New tea selection","published":"2026-04-02T12:30:00Z","author":{"name":"Shop"},"tags":["products"],"body":"<p>Twelve new teas are in.</p>"}
```

**Editor steps:** source type *JSON Lines (NDJSON)*, an uploaded file `articles.ndjson`, *Format* **NDJSON (one
object per line)**, *Record list* empty, *Key field* `id`. Content class `folder`, remote ID `sq688doc-ndjson-{id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `short_description` | `author.name` | `prefix(text="By ") \| richtext` | |
| `description` | `body` | `richtext` | |
| `tags` (keywords) | `tags` | `join(separator=", ")` | |
| `publish_date` | `published` | `date` | |

A nested value is named by its path (`author.name`); the sample lists the paths it found (`tags.0`, `tags.1` and the
whole list `tags`).

**Result:** two folders would be created; the dry run shows `tags : news, opening` and `publish_date : 1773997200`
(20 March 2026, 09:00 UTC).

![The preview of the NDJSON example: the paths id, title, published, author.name, tags and body, and a dry run with name, summary, description, tags and publish date](images/src-ndjson-preview.png)

## JSON (`json`)

Options: [sources.md, JSON and JSON Lines](sources.md#json-and-json-lines-json-ndjson).
Docs import "Docs: Articles (JSON)": dry run #168, 2 would be created.

**Goal:** the answer of a REST API that wraps its records, read as articles.

**Input** (`share/examples/articles.json`): the same two articles, inside `data.items`:

```json
{
  "data": {
    "items": [
      { "id": "a-100", "title": "Spring opening hours", "published": "2026-03-20T09:00:00Z", "author": { "name": "Desk" }, ... },
      { "id": "a-101", "title": "New tea selection", ... }
    ]
  }
}
```

**Editor steps:** source type *JSON file or REST answer*; an uploaded file `articles.json` (or *An address* to read
the API at every run); *Format* **JSON (a list of objects)**; *Record list* `data.items`; *Key field* `id`. Content
class `article`, remote ID `sq688doc-json-{id}`.

![The source options of the JSON example: format JSON, record list data.items, key field id](images/src-json-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `title` | `title` | `trim` | yes |
| `intro` (rich text, required by the class) | `body` | `richtext` | |

**Result:** two articles would be created. Without `data.items` the source would see one record (the whole answer).
For an API with pages, keys and rate limits use the REST source of [source-examples-apis.md](source-examples-apis.md).

## XML (`xml`)

Options: [sources.md, XML](sources.md#xml-xml).
Docs import "Docs: Books (XML)": dry run #175, 2 would be created.

**Goal:** the books of a catalogue file, with an attribute, a namespaced element and an element that has both text
and an attribute.

**Input** (`share/examples/books.xml`):

```xml
<catalog xmlns:dc="http://purl.org/dc/elements/1.1/">
  <book id="b1">
    <title>Dune</title>
    <dc:creator>Frank Herbert</dc:creator>
    <year>1965</year>
    <price currency="EUR">12.90</price>
  </book>
  ...
```

**Editor steps:** source type *XML file*, an uploaded file `books.xml`, *Record element* `book`, *XSD schema* empty,
*Key field* `@id`. Content class `folder`, remote ID `sq688doc-xml-{@id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `short_description` | `dc:creator` | `template(text="{dc:creator} ({year}), {price.#text} {price.@currency}") \| richtext` | |

The paths follow the element: `@id` is the attribute of `<book>`, `dc:creator` the element with its prefix, and
`<price currency="EUR">12.90</price>` gives `price.@currency` and `price.#text`.

**Result:** two folders, "Dune" and "The Left Hand of Darkness", whose summary reads "Frank Herbert (1965), 12.90 EUR".

![The preview of the XML example: the paths @id, title, dc:creator, year, price.@currency and price.#text, and a dry run of two folders](images/src-xml-preview.png)

## YAML (`yaml`)

Options: [sources.md, YAML and TOML data files](sources.md#yaml-and-toml-data-files-yaml-toml).
Docs import "Docs: Teas (YAML)": dry run #176, 3 would be created.

**Goal:** a hand-written YAML catalogue, with nested prices, lists in two notations and a block text.

**Input** (`share/examples/teas.yaml`, the first two records):

```yaml
shop: Example Tea Shop
teas:
  - id: sencha
    name: Sencha
    origin: Japan
    price:
      amount: 9.50
      currency: EUR
    tags: [green, steamed]
    notes: |
      Steep at 70 °C
      for one minute.
  - id: darjeeling
    name: "Darjeeling: first flush"
    price: {amount: 11.00, currency: EUR}
    tags:
      - black
      - spring
```

**Editor steps:** source type *YAML data file*, an uploaded file `teas.yaml`, *Format* Detect by the file name,
*Record list* `teas`, *Key field* `id`. Content class `folder`, remote ID `sq688doc-yaml-{id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `name` | `trim` | yes |
| `short_description` | `price.amount` | `number(decimals=2) \| suffix(text=" EUR") \| richtext` | |
| `description` | `notes` | `richtext` | |
| `tags` | `tags` | `join(separator=", ")` | |

**Result:** three folders would be created: Sencha (tags "green, steamed"), "Darjeeling: first flush" (the quoted
colon is part of the name) and Rooibos (no tags). `shop` at the top is not a record: only the list at `teas` is read.

![The preview of the YAML example: format yaml, the paths id, name, origin, price.amount, price.currency, tags and notes, and a dry run of three folders](images/src-yaml-preview.png)

## TOML (`toml`)

Options: [sources.md, YAML and TOML data files](sources.md#yaml-and-toml-data-files-yaml-toml).
Docs import "Docs: Teas (TOML)": dry run #171, 3 would be created.

**Goal:** the same teas as TOML, an array of tables, with an inline table and a sub-table.

**Input** (`share/examples/teas.toml`, excerpt):

```toml
[[teas]]
id = "sencha"
name = "Sencha"
tags = ["green", "steamed"]
price = { amount = 9.50, currency = "EUR" }

[[teas]]
id = "darjeeling"
name = "Darjeeling: first flush"
[teas.price]
amount = 11.00
currency = "EUR"
```

**Editor steps:** source type *TOML data file* (*Format* TOML), an uploaded file `teas.toml`, *Record list* `teas`,
*Key field* `id`. Content class `folder`, remote ID `sq688doc-toml-{id}`.

| Attribute | Source field | Transforms | Default | Required |
|---|---|---|---|---|
| `name` | `name` | `trim` | | yes |
| `short_description` | `price.amount` | `number(decimals=2) \| richtext` | `on request` | |
| `tags` | `tags` | `join(separator=", ")` | | |

**Result:** three folders would be created. Rooibos has no price in this file, so its summary is the default
"on request". The same preview in the dark theme:

![The preview of the TOML example in the dark theme: format toml, three records, and a dry run of three folders](images/src-toml-preview-dark.png)

## OPML (`opml`)

Options: [sources.md, OPML](sources.md#opml-opml).

An OPML file is a feed list, as feed readers and podcast apps export their subscriptions. It is used in two ways: the
outlines become objects (a list of links on the site), or each outline becomes an import of its own that follows that
feed.

**Input** (`share/examples/feeds.opml`):

```xml
<body>
  <outline text="News" title="News">
    <outline type="rss" text="Example news" title="Example news" xmlUrl="https://example.org/feed.rss" htmlUrl="https://example.org/" description="The news of example.org"/>
    <outline type="rss" text="Example blog" xmlUrl="https://example.org/blog/atom.xml" htmlUrl="https://example.org/blog/"/>
  </outline>
  <outline text="Tech">
    <outline type="rss" text="Example releases" xmlUrl="https://example.net/releases.rss" category="Tech/Releases"/>
    <outline type="link" text="Example documentation" url="https://example.net/docs/"/>
  </outline>
</body>
```

### The outlines as link objects

Docs import "Docs: Feed list (OPML)": dry run #133, 3 would be created.

**Goal:** a page "Our feeds" with one link object per subscribed feed.

**Editor steps:** source type *OPML outline / feed list*, an uploaded file `feeds.opml`, *Outlines* **Only feeds
(outlines with a feed address)**, *Key field* `id`. The preset fills the mapping: class `link`, remote ID
`opml-{id}` (here `sq688doc-opml-{id}`).

![The source options of the OPML example: outlines only feeds, key field id](images/src-opml-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | | yes |
| `description` | `description` | `richtext` | |
| `location` (URL) | `xml_url` | | |

**Result:** three links (Example news, Example blog, Example releases). The categories News and Tech and the
documentation link are left out by *Only feeds*; with **Every outline** they are records too, with `depth`, `path`
and `parent_id` to rebuild the tree. The sample shows `category` (`News`, or `Tech/Releases` from the attribute) and
`position` (`1.1`, `1.2`, `2.1`).

![The preview of the OPML example: three feed outlines with id, text, type, xml_url, html_url, category, path, depth and position, and a dry run of three links](images/src-opml-preview.png)

### One feed import per outline

**Goal:** follow every feed of the list: one RSS import per outline, each reading its feed into articles.

The command reads the outlines of an OPML import and creates one `rss` import per feed address, with the feed as its
address, the RSS preset as its mapping and the OPML import's parent (or `--parent=<node id>`) as its target:

```bash
./console ext:sqliimport:opml-feeds docs-files-opml
```

```
PASS created docs-files-opml-example-news https://example.org/feed.rss
PASS created docs-files-opml-example-blog https://example.org/blog/atom.xml
PASS created docs-files-opml-example-releases https://example.net/releases.rss
The feed imports are disabled: check their mapping in the administration, then enable them.
```

The identifiers start with the OPML import's identifier (or `--prefix=`). The new imports are **disabled**: open
each in the editor, check the mapping (class `article`, remote ID `feed-{id}`), then enable it in the review step
and give it a schedule. A second call creates only the imports that do not exist yet, so it can follow a list that
grows.

![The list of imports with the OPML import and the three feed imports it created, Example blog, Example news and Example releases, each disabled](images/src-opml-feed-imports.png)

## Apache Avro (`avro`) and Apache Arrow (`arrow`)

Options: [sources.md, Apache Avro and Apache Arrow or Feather](sources.md#apache-avro-avro-and-apache-arrow-or-feather-arrow).
Docs imports "Docs: Teas (Avro)" (dry run #172) and "Docs: Teas (Arrow)" (dry run #173), 5 would be created each.

**Goal:** the teas of a data pipeline's export, written by a Kafka connector (Avro) or by pandas or Polars (Arrow or
Feather), with a nested record, a list, a decimal and timestamps.

**Input:** `share/examples/teas.avro` (an object container file, codec deflate, two blocks) and
`share/examples/teas.arrow` (the same five records as an Arrow IPC file, two record batches). Both are binary; the
first record as the source reads it:

```json
{"id": 1, "name": "Sencha Asamushi", "origin.country": "Japan", "origin.region": "Shizuoka", "kind": "green",
 "tags": "steamed, grassy", "price": "12.50", "harvested": "2026-05-02", "updated": "2026-09-14T08:30:00.000Z",
 "organic": true, "rating": 4.5}
```

**Editor steps:** source type *Apache Avro file* (or *Apache Arrow or Feather file*), an uploaded file, *Arrays*
**As a joined list (a, b)**, *List separator* `, `, *Key field* `id`. Content class `folder`, remote ID
`sq688doc-avro-{id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `name` | | yes |
| `short_description` | `origin.country` | `template(text="{origin.country}, {price} EUR") \| richtext` | |
| `tags` | `tags` | | |
| `publish_date` | `updated` | `date` | |

The nested record `origin` is flattened to `origin.country` and `origin.region`; the decimal comes as the text
`12.50` with its scale, never as a float; the list `tags` is joined because of the option *Arrays*.

**Result:** five folders would be created. The preview of the Avro file names the codec (`codec: deflate`), that of
the Arrow file `format: file`:

![The preview of the Avro example: codec deflate, five teas with id, name, origin.country, origin.region, kind, tags, price, harvested, updated, organic and rating, and a dry run of five folders](images/src-avro-preview.png)

## Parquet, through DuckDB

Parquet files are not read by a file format of their own: the DuckDB format reads them with a query (`SELECT ...
FROM data`), which needs the `duckdb` program or libduckdb on the server. The worked example is on the databases
page: [DuckDB over Parquet or CSV](source-examples-databases.md#duckdb). An Arrow file, by contrast, is read here
without DuckDB.

## Photos in a ZIP with EXIF and IPTC (`media`)

Options: [sources.md, Media files](sources.md#media-files-media).
Docs import "Docs: Photos (media ZIP)": run #134 (real), 3 created; run #150 (real, the same file again), 3
unchanged.

**Goal:** a photographer delivers a ZIP of photos; each becomes an image object with its name and caption from the
IPTC fields, and a photo that comes again is not imported twice.

**Input** (`share/examples/media.zip`):

```
media/Shop/storefront.jpg
media/Shop/teapot.jpg
media/Shop/price-list.txt
media/leaves.jpg
```

The JPEG files carry EXIF and IPTC metadata, for example for `storefront.jpg`: object name "Storefront", caption
"The front of the example tea shop with its new awning.", keywords shop, spring, street, city Hamburg, taken
2026-03-21T10:15:00 with "ExampleCam X100", GPS 53.5511, 9.9937.

**Editor steps:** source type *Media files (folder as ZIP) with EXIF and IPTC*, an uploaded file `media.zip`,
*Files* **Images only**, *Only this folder* empty, *Key field* `path`. Mapping from the preset: class `image`,
remote ID `sq688doc-media-{sha1}` (the hash of the file's content: the same photo is found again wherever it lies in
the ZIP).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `name` | | yes |
| `caption` | `caption` | `richtext` | |
| `image` | `media` | `image` | |

`media` is the file itself (`source:media/Shop/storefront.jpg`); the transform `image` takes it out of the run's ZIP.
With a class that has a keywords or tags attribute, the preset adds `keywords` with `join(separator=", ")`.

**Result.** The sample lists every field the source read: path, folder, size, `sha1`, name, caption, the keywords,
author, copyright, city, country, `date_taken`, width and height, camera, `gps_lat` and `gps_lon` and the raw
`exif.*` fields. `price-list.txt` is not an image and is left out. The real run created three image objects:

![The run page of run #134: completed, 3 created, with the records media/Shop/storefront.jpg, media/Shop/teapot.jpg and media/leaves.jpg](images/src-media-run.png)

![The sub items of the target folder: the images Storefront, Teapot and Tea leaves](images/src-media-tree.png)

A second real run of the same ZIP (run #150) left all three **unchanged**. The preview opened after the first run
predicts *Updated* for each picture, with the stored file and the `source:` address as the change; this is a
limitation of the dry run, which never opens the file, and the real run decides correctly:

![The preview of the media example after the run: the sample with path, folder, file, mime, size, sha1, name, caption and keywords, and a dry run that shows each image as updated](images/src-media-preview.png)

## A folder of Markdown files (`markdown`)

Options: [sources.md, Markdown files](sources.md#markdown-files-markdown).
Docs import "Docs: Site (Markdown ZIP)": dry run #135, 3 would be created.

**Goal:** move a small Jekyll or Hugo site, or an Obsidian vault, into articles, with the front matter as fields.

**Input** (`share/examples/markdown-site.zip`):

```
site/_posts/2026-03-20-spring-opening-hours.md   YAML front matter, a Jekyll post
site/content/teas/sencha.md                       TOML front matter (+++), a Hugo page with a table
site/notes/Tea brewing.md                         Obsidian: [[links]], task list, ![[teapot.png]]
site/_layouts/default.md                          a layout (left out)
site/_drafts/coffee.md                            a draft (left out without the option drafts)
site/.obsidian/workspace.md                       settings of the vault (left out)
```

The Hugo page:

```
+++
title = "Sencha"
date = 2026-04-02T12:30:00Z
tags = ["tea", "green"]
description = "A Japanese green tea."
+++

# Sencha

Steep at 70 °C for **one minute**.
```

**Editor steps:** source type *Markdown files (folder as ZIP)*, an uploaded file `markdown-site.zip`, *Read drafts
too* off, *Leave out README and index files* off, *Only this folder* empty, *Address of [[links]]* empty (the links
keep only their text), *Key field* `id`. Mapping from the preset: class `article`, remote ID `md-{id}` (here
`sq688doc-md-{id}`).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `title` | `title` | | yes |
| `intro` | `summary` | `richtext` | |
| `body` | `html` | `richtext` | |

**Result:** three articles would be created: "Spring opening hours" (`id` `_posts/2026-03-20-spring-opening-hours`,
date from the file name), "Sencha" (summary from `description`, the table as HTML) and "Tea brewing" (the title from
the first heading). The layout, the draft and the `.obsidian` folder are not records.

![The preview of the Markdown example: id, path, folder, file, slug, title, date, tags, summary, markdown, html and the front matter fields of three files, and a dry run of three articles](images/src-markdown-preview.png)

## WordPress export (`wxr`)

Options: [sources.md, WordPress export](sources.md#wordpress-export-wxr).
Docs import "Docs: Blog (WordPress WXR)": dry run #136, 3 would be created.

**Goal:** the published posts and pages of a WordPress blog (Tools, Export, All content) as articles, with their
featured image.

**Input** (`share/examples/wordpress.xml`, one item, shortened):

```xml
<item>
  <title><![CDATA[New tea selection]]></title>
  <link>https://blog.example.org/2026/04/new-tea-selection/</link>
  <dc:creator><![CDATA[editor]]></dc:creator>
  <content:encoded><![CDATA[Twelve new teas are in.

Come and taste them
on Saturday.
...]]></content:encoded>
  <excerpt:encoded><![CDATA[Twelve new teas are in.]]></excerpt:encoded>
  <wp:post_id>11</wp:post_id>
  <wp:status><![CDATA[publish]]></wp:status>
  <wp:post_type><![CDATA[post]]></wp:post_type>
  <wp:postmeta><wp:meta_key><![CDATA[_thumbnail_id]]></wp:meta_key><wp:meta_value><![CDATA[10]]></wp:meta_value></wp:postmeta>
</item>
```

The file also holds an attachment (the photo with ID 10), a page "About us" and a draft "Draft about coffee".

**Editor steps:**

| Field | Value |
|---|---|
| Source type | *WordPress export (WXR)* |
| Where the data comes from | An uploaded file: `wordpress.xml` |
| Records | Posts and pages |
| Post types | `post` and `page`, one per line |
| Status | Only published |
| Key field | `id` |
| Content class, match | `article`, remote ID `sq688doc-wp-{type}-{id}` (the preset: `wp-{type}-{id}`) |

![The source options of the WordPress example: records posts and pages, post types post and page, status only published, key field id](images/src-wxr-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `title` | `title` | | yes |
| `intro` | `excerpt` | `richtext` | |
| `body` | `content_html` | `richtext` | |
| `image` (object relation) | `featured_image` | `media_object` | |

`content_html` has the paragraphs WordPress makes from blank lines; `featured_image` is the address of the
attachment that `_thumbnail_id` names. The class `article` relates its image (`ezobjectrelation`), so the preset
picks `media_object`, which imports the picture as an image object and relates it; a class with an image attribute
(`ezimage`) gets `image` instead.

**Result:** three articles would be created: the posts 11 "New tea selection" and 12 "Spring opening hours" and the
page 13 "About us". The draft is left out by *Only published*, the attachment because the records are posts and
pages (choose *Records* **Media (attachments)** to import the media library first).

![The preview of the WordPress example: id, type, title, slug, link, date, author, status, content, content_html, excerpt, categories, tags and featured_image of three records, and a dry run of three articles](images/src-wxr-preview.png)

## iCalendar (`ical`)

Options: [sources.md, iCalendar](sources.md#icalendar-ical).
Docs import "Docs: Events (iCalendar)": dry run #137, 4 would be created.

**Goal:** the events of a booking system's calendar subscription as event objects, in the right time zone.

**Input** (`share/examples/events.ics`, the first event, shortened):

```
BEGIN:VEVENT
UID:weekly-meetup-1@example.org
SUMMARY:Weekly user group meetup
DESCRIPTION:Talks\, demos and questions.\nBring your laptop\; the room has
  power outlets.
LOCATION:Example Hall\, Room 2\, 1 Example Street
DTSTART;TZID=Europe/Berlin:20261105T190000
DTEND;TZID=Europe/Berlin:20261105T210000
RRULE:FREQ=WEEKLY;BYDAY=TH;COUNT=8
EXDATE;TZID=Europe/Berlin:20261126T190000
CATEGORIES:Meetup,Community
END:VEVENT
```

The file also has a changed occurrence of that series (`RECURRENCE-ID`, moved to Room 5), an all-day open day, an
online webinar in UTC and a task (`VTODO`).

**Editor steps:** source type *iCalendar (.ics)*, an uploaded file `events.ics` (or *An address*: the subscription
link, read at every run), *Records* **Events (VEVENT)**, *Time zone* `Europe/Berlin`, *Key field* `id`. Mapping from
the preset: class `event`, remote ID `ical-{id}` (here `sq688doc-ical-{id}`).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `short_title` (required by the class) | `summary` | `trim \| truncate(length=60)` | yes |
| `title` | `summary` | `trim` | |
| `text` | `description` | `richtext` | |
| `category` (keywords) | `categories` | `join(separator=", ")` | |
| `from_time` | `start_timestamp` | `date` | yes |
| `to_time` | `end_timestamp` | `date` | |

The grid of the mapping step with these lines:

![The mapping grid of the iCalendar example with the class event: short_title, title, text, category, from_time and to_time with their source fields and transforms](images/src-ical-mapping.png)

**Result:** four events would be created; the task is left out by *Events (VEVENT)*. The meetup starts at
`1793901600` (5 November 2026, 19:00 in Berlin, 18:00 UTC); the moved occurrence has the ID
`weekly-meetup-1@example.org@2026-11-12T18:00:00Z`, so it is an object of its own next to the series; the open day
is all-day (`start_iso` `2026-11-21`). The recurrence rule is not expanded: map `rrule` to a text attribute if the
site shows it.

![The preview of the iCalendar example: four events with id, uid, type and summary, and a dry run with short title, title, text, category, from time and to time of each](images/src-ical-preview.png)

## vCard (`vcard`)

Options: [sources.md, vCard](sources.md#vcard-vcard).
Docs import "Docs: Contacts (vCard)": dry run #138, 3 would be created.

**Goal:** the people of a team page, exported from an address book, as person objects. The example file holds
fictitious people with `example.com` addresses and 555 numbers; import real contact data only where the people
agreed to it.

**Input** (`share/examples/contacts.vcf`, the first card, shortened):

```
BEGIN:VCARD
VERSION:4.0
UID:urn:uuid:4fbe8971-0bc3-424c-9c26-36c3e1eff6b1
FN:Alex Example
N:Example;Alex;J.;Dr.;
ORG:Example Corp;Research
TITLE:Head of Research
EMAIL;TYPE=work;PREF=1:alex@example.com
NOTE:Met at the example conference\, 2026.\nLikes long
  walks in example parks.
PHOTO:https://example.com/photos/alex.jpg
END:VCARD
```

The second card is vCard 3.0 without a UID, the third has an embedded photo.

**Editor steps:** source type *vCard contacts (.vcf)*, an uploaded file `contacts.vcf`, *Key field* `id`. There is
no preset (a standard installation has no contact class); this site has the class `ng_person`. Remote ID
`sq688doc-vcard-{id}`.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `fn` | | yes |
| `cv` | `note` | `richtext` | |
| `image` | `photo` | `image` | |

**Result:** three persons would be created. The first is matched by its UID without `urn:uuid:`; the card without a
UID gets `vcard-` and a hash (`vcard-ef66682dd84e7825`), which stays the same as long as its name and e-mail address do;
the embedded photo of the third card is not kept (`photo_embedded` is `1`, `photo` empty). The preview at 960 pixels
and double scale:

![The preview of the vCard example at 960 pixels and double scale: three cards with id, uid, version, fn, n, org, title, email and tel fields, and a dry run of three persons](images/src-vcard-preview-960.png)

## See also

- [sources.md](sources.md): every option of every format.
- [Feeds and web pages](source-examples-feeds-web.md): RSS and Atom, podcasts, YouTube and Vimeo, sitemaps and the web
  page scraper.
- [mappings.md](mappings.md) and [transforms.md](transforms.md): what a mapping stores and every transform.
- [editor.md](editor.md): the six steps of the editor. [runs.md](runs.md): the run page, retry and undo.
