# Sources

A source type reads records from a local file. Where the file comes from is the `origin` of the import's
`source_config`. The engine turns it into a local file once per run, so every chunk of the run reads the same data.

## Where the data comes from

| `origin` | Configuration | What happens |
|---|---|---|
| `upload` | `file`: the stored name of an uploaded file | The upload (kept in the site's storage directory, `sqliimport/uploads/`) is copied into the run's directory |
| `url` | `url`: an `http` or `https` address | The address is downloaded into the run's directory, with the time limit `[ImportSettings] StreamTimeout` and the size limit `[SourceSettings] MaxSourceMegabytes` |
| `path` | `path`: a file on the server | The file is read in place, but only if it lies inside one of `[SourceSettings] AllowedPaths[]` |

Rules:

- **Addresses of private or reserved networks are refused** (intranet addresses, loopback, link-local), and so are
  host names that resolve to them. Set `[SourceSettings] AllowPrivateHosts=enabled` only if an import must read an
  intranet export. Only `http` and `https` are accepted.
- **A server path must lie inside an allowed directory.** `AllowedPaths[]` is empty by default, so no server file
  can be read until you list a directory. Relative paths are relative to the installation.
- A file read by `path` is not copied. Do not change it while a run is in progress.
- Snapshots and downloaded images are kept in the run's directory (`sqliimport/runs/<run id>/` in the site's
  storage directory) and are removed together with the run after `[RunSettings] KeepDays`.

## Common option

Every source type except RSS has the option `key`: the field that names a record (an ID column, a GUID). Without a
key, a record is named by its position (`#1`, `#2`, ...). The key is the record's reference in the run's rows and
log.

The key must be unique within the source. A run processes each reference once, so a second record with the same
key is not processed.

## CSV, TSV and delimited text (`csv`, `tsv`)

File name extensions: `csv`, `tsv`, `txt`. The file is read as a stream, one record per line (a quoted field may
span lines). Each record is keyed by the header line.

| Option | Values | Default |
|---|---|---|
| `delimiter` | `auto`, `,` (comma), `;` (semicolon), `tab`, or the pipe character | `auto` (detected from the first line) |
| `enclosure` | one character | `"` |
| `encoding` | `auto`, `UTF-8`, `WINDOWS-1252`, `ISO-8859-1`, `ISO-8859-15` | `auto` (UTF-8, else Windows-1252) |
| `header` | the first line names the columns | `true` |
| `key` | the key column | empty |

Empty or repeated column names are made unique (`col_3`). A byte order mark is removed. Without a header line, the
columns are named `0`, `1`, `2` and so on.

## XML (`xml`)

File name extension: `xml`. The file is read as a stream with XMLReader. External entities are never loaded.

| Option | Meaning |
|---|---|
| `record_path` | Required. The element of one record: `book` or `catalog/book`. It matches the end of an element's path, so `book` finds every `book`. Namespaced elements are named with their prefix (`dc:creator`). |
| `xsd` | Optional. An XSD file the source must validate against |
| `key` | The key field |

An element becomes an array: attributes as `@name`, child elements by their name, repeated children as a list, and
the text of an element that also has attributes or children as `#text`. Example: in
`<book id="7"><title>Dune</title></book>` the paths are `@id` and `title`.

## RSS / Atom (`rss`)

File name extensions: `xml`, `rss`, `atom`. RSS 2.0 and Atom are detected from the file. Each item or entry is one
record with the same fields in both formats, so one mapping fits both:

| Field | From |
|---|---|
| `id` | `guid` (RSS) or `id` (Atom) |
| `title`, `link` | the title and the link |
| `summary` | `description` or `summary` |
| `content` | `content:encoded` or `content` |
| `date` | `pubDate`, `updated` or `published`, as given |
| `author` | the author |
| `categories` | a list |
| `enclosure` | the URL of the first enclosure or `media:content` (for the `image` transform) |
| `raw.*` | the raw element |

The only option is `key` (default `id`). This source replaces the RSS sample handler of 1.x
(`SQLIRSSImportHandler`), which stays for compatibility.

## JSON and JSON Lines (`json`, `ndjson`)

File name extensions: `json`, `ndjson`, `jsonl`.

| Option | Values | Default |
|---|---|---|
| `format` | `auto`, `json` (a list of objects), `ndjson` (one object per line) | `auto` |
| `record_path` | JSON only: the path of the list of records, such as `data.items`; empty: the top | empty |
| `key` | the key field | empty |

A JSON file is read whole. NDJSON is read as a stream, so deliver very large exports as NDJSON.

## Spreadsheets (`spreadsheet`)

File name extensions: `xlsx` (Excel) and `ods` (OpenDocument, as LibreOffice saves it). The format is detected from
the file. Both are ZIP files of XML, which the source reads as streams, without a spreadsheet library. It needs the
PHP extension `zip` (`ext-zip`); without it, reading the file fails with the message "reading spreadsheets needs
the PHP extension zip".

| Option | Values | Default |
|---|---|---|
| `sheet` | The name of the sheet, or its number (`1` is the first) | `1` |
| `header` | The first row names the columns | `true` |
| `key` | The key column | empty |

One record per row of the chosen sheet, keyed by the header row. Rows with no value at all are left out, also before
the header row. Empty or repeated column names are made unique (`col_3`, `price_2`). Without a header row, the
columns are named `0`, `1`, `2` and so on. A sheet that does not exist makes the run fail; for an XLSX file the
message names the sheets the file has, and the preview of the editor lists them as well.

Values come as they are stored in the file:

- text as text, and the last computed value of a formula;
- numbers as stored, without the display format of the cell (`0.5`, not `50 %`);
- an **Excel date** is a number of days (`45231` is 1 November 2023; a fraction is the time of day). The transform
  `date(format=excel)` turns it into a timestamp ([mappings.md](mappings.md#transforms));
- an **ODS date** is its ISO value (`2023-11-01`), which `date` reads without a format.

A very large spreadsheet is read faster when it is saved as CSV.

## OPML (`opml`)

File name extensions: `opml`, `xml`. The outline format feed readers and podcast apps export their subscriptions
in. Every `<outline>` is a record:

| Field | What it holds |
|---|---|
| `id` | the feed address (`xmlUrl`), else the web address (`htmlUrl`), else the outline's position |
| `text`, `title` | the outline's text and title (each falls back to the other) |
| `type` | `rss`, `link`, `include` ... as written |
| `xml_url`, `html_url` | the feed address and the web address |
| `description`, `language`, `version` | as written |
| `category` | the `category` attribute, else the path of the enclosing outlines (`News/Tech`) |
| `path`, `depth`, `position` | the enclosing outlines' texts joined by `/`, the depth (1 at the top), the position (`1.2`) |
| `parent_id` | the `id` of the enclosing outline |
| `attributes` | every attribute of the outline |

| Option | Values | Default |
|---|---|---|
| `outlines` | `feeds` (only outlines with a feed address), `leaves` (only outlines without children), `all` | `feeds` |
| `key` | the key field | `id` |

The preset maps an outline to a `link` object (name, description, location). To follow the feeds of a list rather
than store the list, let the OPML import create one RSS import per feed:

```bash
./console ext:sqliimport:opml-feeds <identifier of the OPML import> [--parent=<node id>] [--prefix=<identifier start>]
```

Each feed import is created **disabled**, with the feed address as its source and the RSS preset as its mapping,
below the given parent node. Check the mappings in the administration, then enable them. A second call creates only
the imports whose identifiers do not exist yet.

## WordPress export (`wxr`)

File name extensions: `xml`, `wxr`. The export file of WordPress (Tools, Export). The option `records` chooses what a
record is:

| `records` | Fields |
|---|---|
| `posts` (default) | `id`, `type`, `title`, `slug`, `link`, `date` (UTC, ISO 8601), `author` (login), `author_name`, `status`, `parent_id`, `menu_order`, `content` (as written), `content_html` (paragraphs made from blank lines, as WordPress shows them), `excerpt`, `categories`, `tags`, `category_slugs`, `tag_slugs`, `featured_image` (the address of the featured image), `images` (the image addresses in the content), `comment_count`, `meta` (custom fields without WordPress's own `_` keys) |
| `attachments` | `id`, `title`, `url`, `mime`, `parent_id`, `date`, `alt`, `caption`, `description` |
| `categories` | `id` (slug), `term_id`, `name`, `slug`, `parent` (slug), `description` |
| `tags` | `id` (slug), `term_id`, `name`, `slug`, `description` |
| `authors` | `id`, `login`, `email`, `display_name`, `first_name`, `last_name` |

| Option | Values | Default |
|---|---|---|
| `post_types` | the post types to read, one per line | `post`, `page` |
| `status` | `publish` (only published) or `all` (drafts, private and pending too) | `publish` |
| `key` | the key field | `id` |

The preset maps a post to an `article` (title, intro from the excerpt, body from `content_html`, and, where the class
has them, publish date, image and tags). `featured_image` and `images` are addresses, which the transform `image`
downloads.

## The catalogue of formats

**Imports, Catalogue** lists the formats by group (data, content, platforms, commerce, media, other). Each card says
what the format reads, its file name extensions and options, offers example files to download and, where the format
comes with one, the mapping it starts from. **New import** opens the editor with that format chosen; when the import
is saved, the format's preset mapping is filled in with the fields this site's class has (a preset line may name the
datatypes it fits, so an image download is not mapped to an object relation). Check it in the step Mapping.

Some formats are variants of a source class with other defaults: **TSV** (`tsv`) is the CSV source with a tab as
delimiter, **JSON Lines** (`ndjson`) the JSON source with one object per line.

## Adding a format

A format is **one source class plus one block in `kernelservices.ini`**. The class implements
`expImportSourceInterface` (`identifier()`, `label()`, `schema()`, `extensions()`, a constructor taking the
validated configuration with `file`, `records( $cursor )`, `count()` and `sample( $limit )`); extending
`expImportAbstractSource` gives the `key` option, the record reference and the catalogue methods with their
defaults, which a format overrides as it needs:

| Method | Returns |
|---|---|
| `description()` | what the format is and what one record is |
| `group()` | `data`, `content`, `platform`, `commerce`, `media` or `other` |
| `examples()` | a list of `array( 'label', 'file' )` (a file in the extension's `share/examples`, with `'options'` if it needs some) or `array( 'label', 'url' )` |
| `preset()` | a mapping to start from: `class`, `match`, `fields` (each with `source`, `attribute`, `transforms` and optionally `datatypes`), or `array()` |
| `variants()` | id => `label`, `description`, `extensions`, `defaults` (option => value), `examples`, `preset` |

Register it in your extension's `settings/kernelservices.ini.append.php`:

```ini
[Service_mysite.source.myformat]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=myImportSource
# for a variant of the class: Arguments[]=<variant id>
Scope=worker
Tags[]=sqliimport.source
```

`[Source_<id>] Status=` in `sqliimport.ini` sets a format to `enabled`, `example` (listed, but not offered for a new
import) or `disabled`; `Enabled=false` in the service block switches it off. The way of 2.0.0-alpha.1
(`[SourceSettings] Sources[]` with a `[Source_<id>] Class=` block) still works.
