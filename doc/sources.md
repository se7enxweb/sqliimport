# Sources

A source type reads records from a local file. Where the file comes from is the `origin` of the import's
`source_config`. The engine turns it into a local file once per run, so every chunk of the run reads the same data.

## Where the data comes from

| `origin` | Configuration | What happens |
|---|---|---|
| `upload` | `file`: the stored name of an uploaded file | The upload (kept in the site's storage directory, `sqliimport/uploads/`) is copied into the run's directory |
| `url` | `url`: an `http` or `https` address | The address is downloaded into the run's directory, with the time limit `[ImportSettings] StreamTimeout` and the size limit `[SourceSettings] MaxSourceMegabytes` |
| `path` | `path`: a file on the server | The file is read in place, but only if it lies inside one of `[SourceSettings] AllowedPaths[]` |
| `remote` | the source's own options (a database, a search index, a mailbox, a directory, a service) | The source reads its data itself once per run and writes it into the run's directory as JSON Lines (`sql`, `mongodb`, `redis`, `couchdb`, `couchbase`, `cosmosdb`, `elasticsearch`, `duckdb`, `imap`, `ldap`, `airtable`, `notion`, `dynamodb`, `firestore`, `bigquery`, `firebase_rtdb`, `cassandra`, `neo4j`, `arangodb`, `clickhouse`, `snowflake`, `influxdb`, `supabase`, `baserow`, `salesforce`, `hubspot`, `youtube`, `youtube_page` and the sites of the platforms group) |

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

## iCalendar (`ical`)

Group: content. File name extensions: `ics`, `ical`, `ifb`, `txt`. Calendar files (RFC 5545) as calendar apps,
booking systems and event sites publish them, from an upload or a subscription address. The file is read as a
stream, line by line; folded lines are joined and the escapes `\\`, `\;`, `\,` and `\n` are read back. A record's
cursor is the byte position after its `END:VEVENT`. Example: `share/examples/events.ics`.

| Option | Values | Default |
|---|---|---|
| `components` | `events` (VEVENT), `events_todos` (VEVENT and VTODO), `todos` (VTODO) | `events` |
| `timezone` | the zone of times without a zone and of all-day dates (`Europe/Berlin`) | `UTC` |
| `key` | the key field | `id` |

| Field | Content |
|---|---|
| `id` | the UID; a changed occurrence of a series (with `RECURRENCE-ID`) gets `<uid>@<date in ISO 8601 UTC>`; without a UID a hash of summary and start |
| `uid`, `type` | the UID as written; `event` or `todo` |
| `summary`, `description`, `location`, `url`, `status`, `priority`, `class`, `sequence` | as written, unescaped (`status` and `class` in lower case) |
| `start`, `end` | the value as written (`20261105T190000`) |
| `start_iso`, `end_iso` | ISO 8601 UTC (`2026-11-05T18:00:00Z`), an all-day date as `2026-11-05` |
| `start_timestamp`, `end_timestamp` | Unix timestamps (an all-day date: midnight in the option's zone) |
| `start_timezone`, `end_timezone` | the `TZID`, `UTC`, or the option's zone |
| `all_day` | `1` for a date without a time |
| `rrule`, `exdates` | the recurrence rule as written; the excluded dates (a list, ISO 8601) |
| `recurrence_id` | the occurrence a changed instance replaces (ISO 8601) |
| `categories` | a list (repeated `CATEGORIES` lines add up) |
| `organizer`, `organizer_name`, `attendees` | the organizer's address without `mailto:`, its `CN`, the attendees' addresses |
| `created`, `last_modified` | ISO 8601 UTC |
| `geo_lat`, `geo_lon`, `calendar` | the position; the calendar's name (`X-WR-CALNAME`) |
| `raw.*` | every property by its name in lower case, as text |

The end comes from `DTEND`, from `DUE` for a task, or from `DURATION`; an all-day event without an end lasts the
day. A `TZID` is converted with PHP's zone database; a zone PHP does not know (such as a Windows zone name) is read
in the option's zone and named in the preview's detected values. Alarms inside an event are left out. A recurrence
rule is not expanded: a series is one record, and its `rrule` can be mapped.

Preset: class `event` of the standard installation, remote ID `ical-{id}`: `summary` to `title`, `description`
to `text` (rich text), `categories` to `category` (keywords, joined), `start_timestamp` to `from_time` and
`end_timestamp` to `to_time`.

## vCard (`vcard`)

Group: content. File name extensions: `vcf`, `vcard`, `txt`. Contacts as address books, phones and mail programs
export them: vCard 4.0 and 3.0, and of 2.1 what is common (bare types such as `TEL;WORK`, quoted-printable values
with `CHARSET`). Every `BEGIN:VCARD` ... `END:VCARD` is one record, read as a stream like iCalendar. Example:
`share/examples/contacts.vcf` (fictitious people, `example.com` addresses, 555 numbers).

| Field | Content |
|---|---|
| `id` | the UID without `urn:uuid:`, else `vcard-` and a hash of the formatted name and the e-mail address |
| `uid`, `version`, `kind`, `fn` | as written; `fn` is made from `N` when it is missing |
| `n` | `family`, `given`, `additional`, `prefix`, `suffix`; also as `family_name` and `given_name` |
| `nickname`, `title`, `role`, `note`, `gender`, `anniversary`, `rev` | as written |
| `org`, `org_unit` | the organization and its units |
| `email`, `emails` | the preferred (else the first) address, and all of them; `email_work`, `email_home` the first of that type |
| `tel`, `tels` | the preferred (else the first) number, and all; `tel_work`, `tel_home`, `tel_cell`, `tel_fax` |
| `adr`, `adr_text`, `adrs` | the first address (`po_box`, `extended`, `street`, `locality`, `region`, `postal_code`, `country`, `type`), the same on one line, every address |
| `url`, `urls` | the first web address and all of them |
| `bday`, `bday_iso` | the birthday as written and as `YYYY-MM-DD` (`--MM-DD` without a year) |
| `categories` | a list |
| `photo`, `photo_embedded` | the photo's `http(s)` address; an embedded photo is not kept, `photo_embedded` is then `1` |
| `raw.*` | every property by its name in lower case, as text |

There is no preset: a standard installation has no contact class, and users (`ezuser`) are not created by a
mapping. Map the cards to a class of your site. Contact data is personal data: import it only where the people it
names agreed to it.

## Google Merchant feed (`google_merchant`)

Group: commerce. File name extensions: `xml`, `rss`. The product feed shops make for Google Merchant Center and
price comparison sites: RSS 2.0 (or Atom) with the `g:` namespace, read as a stream like XML, external entities
never loaded. Every item is one product. Fields are found with the prefix `g:` and without it (`<title>` and
`<g:title>`). Example: `share/examples/products-google.xml`.

| Field | Content |
|---|---|
| `id`, `item_group_id` | `g:id` (else the link), the group of variants |
| `title`, `description`, `link`, `mobile_link` | as written (the description may hold HTML) |
| `image_link` (also `image`), `additional_image_links`, `images` | the main image, the further ones, all of them |
| `price`, `currency`, `price_text` | the amount as a decimal string (`1,299.00 USD` gives `1299.00`), the ISO 4217 code, the value as written |
| `sale_price`, `sale_price_currency`, `sale_price_effective_date` | the sale price |
| `availability`, `availability_date`, `condition`, `brand`, `gtin`, `mpn`, `identifier_exists` | as written |
| `product_type`, `categories`, `google_product_category` | the shop's category path, the same as a list (split at `>`), Google's category |
| `color`, `size`, `gender`, `age_group`, `material`, `pattern` | as written |
| `shipping`, `shipping_weight` | a list of `country`, `region`, `service`, `price`, `currency` |
| `tax`, `vat` | a list of `country`, `region`, `rate`, `tax_ship`; `vat` is the rate of the first |
| `tags` | the custom labels `custom_label_0` to `custom_label_4` that are set |
| `raw.*` | the raw element |

The only option is `key` (default `id`). Preset: see [the product preset](#the-product-preset), remote ID
`gmc-{id}`, product number from `id`.

## Shopify product CSV (`shopify`)

Group: commerce. File name extension: `csv`. The product export of a Shopify store (Products, Export). The rows
that share a `Handle` are one product: the first row holds the product, further rows add variants and images. The
file is read as a stream like CSV; a record's cursor is the byte position of the next product's first row.
Example: `share/examples/products-shopify.csv`.

| Option | Values | Default |
|---|---|---|
| `delimiter`, `enclosure`, `encoding` | as for CSV | `auto`, `"`, `auto` |
| `currency` | the ISO 4217 code of the prices (the export does not name it) | empty |
| `key` | the key field | `id` |

| Field | Content |
|---|---|
| `id`, `handle` | the Handle |
| `title`, `description`, `vendor`, `product_category`, `type`, `status`, `seo_title`, `seo_description` | as written (`description` is `Body (HTML)`) |
| `tags` | a list |
| `published`, `gift_card` | `1` or `0` |
| `options` | the option names (`Size`, `Color`; the placeholder `Title` of a product without variants is left out) |
| `variants` | a list of `sku`, `price`, `compare_at_price`, `option1` to `option3`, `grams`, `inventory_qty`, `barcode`, `taxable`, `requires_shipping`, `weight_unit`, `tax_code`, `cost`, `image` |
| `sku`, `price`, `compare_at_price`, `taxable`, `tax_code` | those of the first variant |
| `price_min`, `price_max` | the lowest and highest variant price |
| `currency` | the option `currency` |
| `images`, `image`, `image_alt` | the image addresses in their order, the first, its alt text |
| `row.*` | the first row as read, by column name in lower case |

Preset: [the product preset](#the-product-preset), remote ID `shopify-{id}`, product number from `sku`.

## WooCommerce product CSV (`woocommerce`)

Group: commerce. File name extension: `csv`. The product export of WooCommerce (Products, Export) with the English
column names. One row is one product. Example: `share/examples/products-woocommerce.csv`.

| Option | Values | Default |
|---|---|---|
| `variations` | `nested` (the variations that follow their variable product go into its `variants`), `records` (every row is a record) | `nested` |
| `delimiter`, `enclosure`, `encoding`, `currency`, `key` | as for Shopify | |

| Field | Content |
|---|---|
| `id`, `wc_id` | the ID (else the SKU), the ID as written |
| `type`, `types` | `simple`, `variable`, `variation`, `grouped`, `external`; all types of a cell like `simple, virtual` |
| `sku`, `title`, `visibility`, `short_description`, `description`, `purchase_note` | as written; WooCommerce's `\n` is a line break |
| `published`, `featured` | `1`, `0`, or `-1` for a draft |
| `regular_price`, `sale_price`, `price`, `current_price`, `currency` | decimal strings; `price` is the regular price (of a variable product the lowest of its variations), `current_price` the sale price when set |
| `sale_from`, `sale_to` | the dates of the sale price |
| `tax_status`, `tax_class` | the tax fields of WooCommerce (it has no rate in the export) |
| `in_stock`, `stock`, `backorders`, `weight`, `length`, `width`, `height`, `shipping_class` | as written |
| `categories`, `category`, `tags` | the category paths (`Clothing > Hoodies`) as a list, the last part of the first, the tags as a list |
| `images`, `image` | the image addresses, the first |
| `parent`, `parent_id`, `grouped`, `upsells`, `cross_sells` | the parent (`id:102` gives `102`), the related products as lists |
| `external_url`, `button_text`, `position` | as written |
| `attributes` | a list of `name`, `values` (a list), `visible`, `global` |
| `meta.*` | the columns `Meta: <key>` by key |
| `variants` | the variations: `id`, `sku`, `title`, `regular_price`, `sale_price`, `price`, `stock`, `in_stock`, `image`, `attributes` |
| `row.*` | the row as read |

A comma inside a list item is written `\,` by WooCommerce and read back. Preset: [the product preset](#the-product-preset),
remote ID `woocommerce-{id}`, product number from `sku`, and `short_description` to `short_description`.

### The product preset

The three product formats start from the class `product` of the Exponential webshop: `title` to `name`, the product
number to `product_number`, `description` to `description` (rich text), `price` to `price` and `tags` to `tags`
(keywords, joined with commas). The price goes through `template` as `{price}|1|1`, which `ezprice` reads as the
amount, VAT type 1 and "price includes VAT"; change the VAT type and the last `1` (`0`: excluding VAT) in the
mapping when your shop needs it. Images are not in the preset: the webshop's `image` is an object relation, so map
`image` with `lookup` to image objects you import first. A product with an empty price fails, because `ezprice`
refuses an amount that is not a number.

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

## Markdown files (`markdown`)

Group: content. File name extensions: `zip`, `md`, `markdown`. One Markdown file, or a folder as ZIP: a Jekyll or
Hugo site, an Obsidian vault, a documentation folder. Every `.md` / `.markdown` file is a record. Front matter in
YAML (between `---` lines) or TOML (between `+++` lines) is read without a library.

| Field | What it holds |
|---|---|
| `id` | the front matter's `id`, else the path without extension (`_posts/2026-03-20-spring-opening-hours`) |
| `path`, `folder`, `file` | where the file is (a folder all files share, the ZIP's own folder, is no part of it) |
| `slug` | the front matter's `slug`, else the file name without a Jekyll date |
| `title` | the front matter's `title`, else the first `#` heading, else the file name |
| `date` | the front matter's `date` (or `created`, `publishDate`), else the date of a Jekyll file name |
| `draft` | `true` for `draft: true`, `published: false` or a file in `_drafts` |
| `tags`, `categories` | lists (a text `a, b` is split) |
| `summary` | the front matter's `description`, `summary` or `excerpt`, else the first paragraph as text |
| `markdown`, `html`, `text` | the body as written, as HTML, as plain text |
| `links`, `images` | the Obsidian `[[links]]` and the image paths of the body |
| `front` | the whole front matter; each of its keys is also a field of its own |

The HTML knows headings, paragraphs, emphasis, code, links, images, lists (also nested and task lists), block quotes,
tables and Obsidian `[[Page]]` / `[[Page|text]]` links and `![[image.png]]` embeds. Addresses other than `http`,
`https` and `mailto` are dropped.

| Option | Values | Default |
|---|---|---|
| `drafts` | read drafts too | no |
| `skip_index` | leave out `README` and `index` files | no |
| `folder` | only this folder of the ZIP | all |
| `wiki_base` | the address `[[links]]` point to (plus the page's slug); empty: their text only | empty |

Folders starting with `_` or `.` (layouts, includes, `.obsidian`) are left out, except `_posts` (and `_drafts` with
the option `drafts`). The preset maps a file to an `article` (title, intro from the summary, body from the HTML,
and, where the class has them, publish date and tags).

## Media files (`media`)

Group: media. File name extensions: `zip`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `tif`, `tiff`, `pdf`, `mp3`, `mp4`.
Photos, documents, audio and video, one file or a folder as ZIP. Every file is a record:

| Field | What it holds |
|---|---|
| `id`, `path`, `folder`, `file`, `extension`, `mime`, `size` | where the file is and what it is |
| `sha1` | the hash of the content: with the remote ID pattern `media-{sha1}` the same file is imported once, wherever it is |
| `name` | the IPTC object name or headline, else the EXIF description, else the file name in words |
| `caption` | the IPTC caption, else the EXIF description |
| `keywords` | the IPTC keywords (a list) |
| `author`, `copyright`, `credit`, `source`, `city`, `country` | from IPTC (author and copyright else from EXIF) |
| `date_taken` | when the photo was taken (EXIF `DateTimeOriginal`, else the IPTC date), ISO 8601 |
| `width`, `height`, `orientation`, `camera`, `gps_lat`, `gps_lon` | from the image and its EXIF (GPS in decimal degrees) |
| `exif`, `iptc` | the EXIF fields read, the IPTC fields by name |
| `media` | the file itself, as `source:<path>`: the transforms `image` and `file` take it like an address and import the file out of the run's ZIP |

| Option | Values | Default |
|---|---|---|
| `types` | `images` (JPEG, PNG, GIF, WebP, TIFF) or `all` | `images` |
| `folder` | only this folder of the ZIP | all |
| `key` | `path`, or `sha1` | `path` |

The preset maps a photo to an `image` object (name, caption, the image and, where the class has them, the keywords
as tags) with the remote ID `media-{sha1}`. The metadata is read in memory: no file is written for it. A file in the
ZIP larger than 256 MB is refused.

## Podcast feeds (`podcast`) and YouTube or Vimeo channel feeds (`video_feed`)

Group: media. Both are the RSS source (`rss`) with a preset of their own; every RSS and Atom record also has these
fields:

| Field | What it holds |
|---|---|
| `enclosure`, `enclosure_type`, `enclosure_length` | the episode's file, its media type and size |
| `duration` | the iTunes duration in seconds (`38:21` is `2301`) |
| `episode`, `season`, `explicit` | the iTunes episode and season numbers, and whether it is marked explicit |
| `image` | the episode's or video's image (iTunes image, media thumbnail) |
| `video_id`, `channel_id`, `embed_url` | the YouTube video and channel IDs, or the Vimeo video ID, and the player address to embed |
| `views`, `media_description` | the YouTube view count and the video's description |

The podcast preset imports an episode into a `file` object (name, description, and the episode file through the
transform `file`). The video preset stores a video as a `link` (name, description, address), matched by
`video-{video_id}`. A YouTube channel's feed is `https://www.youtube.com/feeds/videos.xml?channel_id=<channel ID>`
(a playlist: `?playlist_id=`), a Vimeo user's `https://vimeo.com/<user>/videos/rss`.

## YouTube Data API (`youtube`)

Group: media. The videos of a YouTube channel, of a playlist or of a search through the YouTube Data API v3 at
`https://www.googleapis.com/youtube/v3/` (the host is fixed). Unlike the channel feed (`video_feed`, the last 15
videos), it reads every video and its details.

| Option | Meaning | Default |
|---|---|---|
| `mode` | `channel` (the uploads of a channel), `playlist` or `search` | `channel` |
| `channel` | Channel and search: the channel ID (`UC...`) or `@handle`, or the channel's address (`youtube.com/@name`, `youtube.com/channel/UC...`); a search without it searches all of YouTube | empty |
| `playlist_id` | Playlist: the playlist ID (`PL...`) or its address (`youtube.com/playlist?list=...`) | empty |
| `query` | Search: the terms (`q`) | empty |
| `published_after`, `published_before` | Search: `2026-01-31` or `2026-01-31T12:00:00Z` (sent as RFC 3339 in UTC) | empty |
| `order` | Search: `date`, `rating`, `relevance`, `title`, `viewCount` | `date` |
| `page_size` | Videos per request (at most 50) | `50` |
| `max_records` | The most videos one run reads; `0`: all | `0` |
| `api_key` | Required. The **name** of the secret holding the API key | |
| `key` | the key field | `id` |

**How it reads:** `channel` asks `channels.list` (by `id` or `forHandle`) for the channel's playlist of uploads
(`contentDetails.relatedPlaylists.uploads`) and reads it like `playlist` with `playlistItems.list`; `search` uses
`search.list` with `type=video`, `q`, `channelId`, `publishedAfter`, `publishedBefore` and `order`. Each page of video
IDs is followed by `videos.list` (`snippet`, `contentDetails`, `statistics`, `status`) for up to 50 videos per
request; the records keep the order of the listing, a video listed twice comes once, and a private or deleted video
of a playlist (which `videos.list` does not give) is left out. Paging follows `nextPageToken`.

**Quota:** a Google Cloud project has 10,000 units a day by default. `channels.list`, `playlistItems.list` and
`videos.list` cost 1 unit per request, `search.list` **100**: a channel of 500 videos costs about 21 units a run, a
search of 5 pages 505. `[YouTubeSettings] MaxPages` (100) limits the pages of a channel or playlist and
`MaxSearchPages` (5) those of a search; a run with more pages stops with a message rather than import a part (set
`max_records`, or raise the setting). After a `429` the source waits (`Retry-After`); when the quota is used up the
message says so (it is reset at midnight Pacific Time).

**Read-only guarantee:** the source sends `GET` requests only, to the four methods `channels`, `playlistItems`,
`search` and `videos`; any other method is refused before a request is made. Create an API key in the Google Cloud
console, enable the YouTube Data API v3 and restrict the key to it. The key is sent in the header `X-goog-api-key`,
never in an address, and is masked in every message. A service account is not used: the Data API reads public videos
with an API key, and private ones only with the channel owner's OAuth consent, which this source does not ask for.

**Fields:**

| Field | What it holds |
|---|---|
| `id`, `url`, `embed_url` | the video ID, `https://www.youtube.com/watch?v=<id>` and `https://www.youtube-nocookie.com/embed/<id>` |
| `title`, `description`, `published` | the title, the description (plain text with line breaks) and the time of publication (ISO 8601) |
| `channel_id`, `channel_title` | the channel |
| `tags`, `category_id` | the tags (a list, empty when none) and the category's ID |
| `duration_seconds`, `duration` | the length from the ISO 8601 duration (`PT1H2M3S`) in seconds (`3723`) and as `H:MM:SS` (`1:02:03`); `0` for a live stream that has not started |
| `thumbnail`, `thumbnails` | the address of the largest thumbnail (`maxres`, `standard`, `high`, `medium`, `default`) and all of them as JSON |
| `view_count`, `like_count`, `comment_count` | numbers; `null` when the channel hides them |
| `privacy`, `live_broadcast_content` | `public`, `unlisted` or `private`; `none`, `upcoming` or `live` |

**Preset:** a `link` object matched by `youtube-{id}`: `title` to `name`, `description` to `description` (rich text),
`url` to `location`; a class with an image attribute (`ezimage`) called `image` also gets the thumbnail (transform
`image`), one with `publish_date` the time of publication.

**Test connection** makes one request of 1 unit: `channels.list` for a channel (and a search that names a channel,
with the number of its videos), `playlistItems.list` for a playlist (with its total), `videos.list` of one popular
video for a search without a channel, so a test never costs the 100 units of a search; the message says what was used.
**What the server needs:** the PHP extension openssl and `allow_url_fopen=On`.

**Example:** `share/examples/youtube-videos.ndjson` (three invented videos).

## YouTube channel or playlist page (`youtube_page`, opt-in)

Group: media. **Reading youtube.com pages with a program is against YouTube's Terms of Service, and this source may
break whenever YouTube changes its pages.** Use it only where you may, and prefer the Data API (`youtube`) or the
channel feed (`video_feed`). It is **off** by default: until an administrator sets `sqliimport.ini [YouTubeSettings]
AllowPageScraper=enabled`, the catalogue lists it as disabled with the reason, the editor does not offer it, and a run
or a test of an existing import is refused. The catalogue and the editor show the warning with the format.

| Option | Meaning | Default |
|---|---|---|
| `url` | Required. A channel's videos page (`https://www.youtube.com/@name/videos`, `/channel/UC.../videos`, also `/streams` and `/shorts`) or a playlist (`https://www.youtube.com/playlist?list=...`) | |
| `max_records` | The most videos one run reads; `0`: all the page lists | `0` |
| `delay` | The pause before reading the page, in seconds (a longer `Crawl-delay` wins) | `2` |
| `key` | the key field | `id` |

Only `www.youtube.com` is read (`youtube.com` and `m.youtube.com` addresses are rewritten to it); other hosts, user
names, ports and other paths (a single video, search results) are refused. The page is read like the web page scraper
reads one: `robots.txt` of `www.youtube.com` is obeyed, the address checks and size limits of a URL source apply, no
cookie and no credential is sent, and no script runs: the JSON object assigned to `ytInitialData` is cut out of the
HTML text and decoded. A redirect to another host (a consent page) stops the run with a message. Only the first batch
of videos the page holds is read (about 30 on a channel page, 100 on a playlist): more would need YouTube's internal
API, which this source never calls.

The records have the fields of `youtube` as far as the page gives them: `id`, `url`, `embed_url`, `title`,
`duration_seconds`, `duration`, `thumbnail`, `thumbnails` (a JSON list), `view_count` (only an exact count such as
`1,234 views`; `1.2K` gives `null`), `channel_id`, `channel_title` and `live_broadcast_content`; `published` is `null`
and `published_text` holds the page's relative text (`3 days ago`); `like_count`, `comment_count`, `tags`,
`category_id`, `privacy` and usually `description` are empty. The preset is the one of `youtube`. Test connection reads
the page and reports how many videos it lists.

**Example:** `share/examples/youtube-page-videos.ndjson` (three invented videos).

## YAML and TOML data files (`yaml`, `toml`)

Group: data. File name extensions: `yaml`, `yml`, `toml`. The list of records is at the top or at `record_path`
(`teas`, `data.items`); in TOML usually an array of tables (`[[teas]]`). A mapping of mappings is a list too: its
keys become the field `_key`. Nested values are mapped by their path (`price.amount`). The file is read whole, by
the extension's own parser: a file reads the same on every server, with or without the PHP extension yaml (YAML
1.2: `y` and `n` are text). Anchors and aliases are not read. Front matter of Markdown files uses the same parser.

| Option | Values | Default |
|---|---|---|
| `format` | `auto` (by the file name), `yaml`, `toml` | `auto` (`toml` for the format `toml`) |
| `record_path` | the path of the list | the top |
| `key` | the key field | empty |

## Apache Avro (`avro`) and Apache Arrow or Feather (`arrow`)

Group: data. Origins `upload`, `url` and `path`, like the other files. Both are read in PHP alone; nothing has to be
installed for the usual files.

**Avro** (`.avro`): object container files as Kafka connectors, Hadoop and data pipelines write them. The reader checks
the header (`Obj1`, the schema in `avro.schema`, the sync marker after every block) and decodes every record with the
schema of the file. **Arrow** (`.arrow`, `.feather`, `.arrows`, `.ipc`): the Arrow IPC file format (also Feather
version 2, which is the same) and the stream format, as pandas, Polars, R and DuckDB write them; a file is read whole,
up to `[SourceSettings] MaxSourceMegabytes`. Feather version 1 (before 2020) is refused with a message.

| Option | Meaning | Default |
|---|---|---|
| `arrays` | Arrays (lists) as JSON (`["a","b"]`) or as a joined list (`a, b`; lists of records stay JSON) | `json` |
| `separator` | The list separator | `, ` |
| `key` | the key field | empty |

One record is one Avro record or one Arrow row; nested records, maps and struct columns are flattened to dotted names
(`origin.country`) as the MongoDB source does. A chunk of a run goes on at the block (Avro) or the batch (Arrow) of the
record before it.

| Type | Value |
|---|---|
| null, boolean, int, long, float, double | as they are (Arrow: int 8 to 64 bits, signed or not; an unsigned 64-bit number above PHP's range as text; half floats too) |
| NaN and infinity | the text `NaN`, `INF`, `-INF` |
| string (Arrow utf8, large utf8) | text |
| bytes, fixed (Arrow binary, fixed size binary) | base64 |
| enum (Arrow dictionary-encoded columns) | the symbol or value |
| union | the value of its branch (Arrow unions are refused) |
| date | `2026-10-22` |
| timestamp-millis, -micros (Arrow timestamp with a time zone) | ISO 8601 in UTC with `Z` |
| local-timestamp-* (Arrow timestamp without a time zone) | ISO 8601 without `Z` |
| time-millis, -micros (Arrow time) | `10:20:30.500` |
| decimal (bytes or fixed; Arrow decimal 32 to 256 bits) | a decimal number in text with its scale (`-1234.50`), never a float |
| uuid | the 8-4-4-4-12 text |
| Arrow duration | the number of its unit |

**Compression:** Avro codecs `null` and `deflate` (zlib); `snappy` only with the PECL extension snappy (the CRC-32 of
each block is checked), `zstandard` with the PECL extension zstd, `bzip2` with the extension bz2; any other codec is
refused with a message. Arrow: uncompressed, LZ4 frames (the default of pyarrow's Feather; read in PHP) and Zstandard
(only with the PECL extension zstd). The catalogue's driver list shows which this PHP and the job queue's workers have.
Arrow intervals, unions, run-end encoded and view types are refused with the column's name: cast them to a plain type
when writing the file.

**Parquet** is read by the DuckDB format (`duckdb`), which needs the `duckdb` program or libduckdb on the server. DuckDB
reads Arrow files only through its community extension `arrow`, which the DuckDB source does not load (it refuses
`INSTALL` and `LOAD`), so Arrow files are read here.

**Examples:** `share/examples/teas.avro` (five invented teas with a nested origin, tags, a decimal price, a date and a
timestamp; deflate, two blocks) and `share/examples/teas.arrow` (the same as an Arrow file with a struct, a list, a
dictionary column and two record batches).

## Google Sheets (`google_sheets`)

Group: data. A spreadsheet read at every run from its link: share it so that anyone with the link can view it (or
publish it to the web) and give the link as the address. The link is turned into the sheet's CSV export, keeping the
tab of the link (`#gid=`); the first row names the columns. The options are those of CSV, with a comma and UTF-8.

## SQL database or query (`sql`)

Group: platforms. Origin: `remote`. The rows of an SQL query against a MySQL or MariaDB, PostgreSQL, SQLite, Oracle or
Microsoft SQL Server database (an intranet database, the database of another application): one record is one row, its fields the
columns of the query (name them with `AS` to get the field names you want). Once per run the source runs the query
and writes the rows into the run's directory, one JSON object per line; binary values that are not UTF-8 are written
as `base64:...`. The editor's sample and preview read the first `[RunSettings] DryRunRows` rows only.

| Option | Values | Default |
|---|---|---|
| `driver` | `mysql` (MySQL or MariaDB), `pgsql` (PostgreSQL), `sqlite` (a file on the server), `oracle` (Oracle), `sqlsrv` (Microsoft SQL Server), `site` (this site's database) | `mysql` |
| `host`, `port` | the database server; port `0` is the default port (3306, 5432, Oracle 1521, SQL Server 1433) | |
| `database` | the database name; SQLite: the path of the file; Oracle: the service name (see below) | |
| `user` | the database user | |
| `password` | the **name** of a secret (see below), never the password | |
| `query` | a single `SELECT` (or `WITH ... SELECT`) | required |
| `key` | the column that names a row | the position |

Only reading is possible, checked three times:

- **The query must be a single `SELECT`** (or `WITH ... SELECT`, also in brackets). Before the check, string
  literals, quoted names and comments are taken out in the dialect of the driver (backslash escapes and `#` comments
  of MySQL, dollar quoting, `E'...'` strings and nested comments of PostgreSQL), so a keyword inside a text does not
  count and a statement hidden behind a comment or a quote is found. Refused are a `;` followed by more, an unclosed
  string or comment, MySQL's executable comments `/*! ... */` and hints `/*+ ... */`, variables (`@name`, `:=`), and
  anywhere outside a string the words `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `GRANT`, `REVOKE`,
  `TRUNCATE`, `MERGE`, `CALL`, `EXEC`, `INTO` (also `INTO OUTFILE` and PostgreSQL's `SELECT ... INTO`), `LOAD`,
  `LOAD_FILE`, `COPY`, `ATTACH`, `PRAGMA`, `LOCK` (also `FOR UPDATE`) and functions with side effects
  (`pg_read_file`, `lo_import`, `dblink`, `set_config`, `nextval`, `sleep`, `load_extension`, `writefile` ...).
  `REPLACE` is allowed only as the function `REPLACE( ... )`. A column with such a name can be quoted
  (`` `update` ``, `"update"`). A refused query stops the run with the reason.
  - **T-SQL (SQL Server)** in addition: `[bracketed names]` (with `]]` inside), `N'...'` strings and nested
    comments are read as SQL Server reads them; `TOP` and `OFFSET ... FETCH` page the rows. Refused are
    `EXEC`/`EXECUTE`, system procedures (`sp_...`, `xp_...`, also bracketed), `SELECT ... INTO` (it creates a table),
    `MERGE`, `OPENROWSET`, `OPENQUERY`, `OPENDATASOURCE`, `WITH ... DELETE`/`UPDATE`/`MERGE`/`INSERT`, a second
    statement without a `;` (`BEGIN`, `DECLARE`, `SET`, `USE`, `WAITFOR`, `DBCC`, `BULK`, `COMMIT` ...), locking hints
    (`UPDLOCK`, `XLOCK`, `HOLDLOCK`, `TABLOCK`, `TABLOCKX`, `PAGLOCK`) and `NEXT VALUE FOR`.
  - **Oracle** in addition: `q'[...]'` strings (any delimiter, also `nq'...'`) are read as Oracle reads them.
    Refused are PL/SQL blocks (`BEGIN`, `DECLARE`, also `WITH FUNCTION`), `FOR UPDATE` (it locks rows), calls of the
    packages `DBMS_...`, `UTL_...`, `OWA_...`, `HTP`, `HTF` and of `HTTPURITYPE` (also as quoted names such as
    `"DBMS_PIPE"."SEND_MESSAGE"`), database links (`table@link`), `sequence.NEXTVAL`, `COMMIT` and `ROLLBACK`.
    Oracle gives unquoted column names in capitals: name the key column that way, or use `AS "id"`.
- **The connection is read-only:** MySQL and MariaDB `SET SESSION TRANSACTION READ ONLY`, PostgreSQL
  `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY`, SQLite opened read-only with `PRAGMA query_only`, Oracle a
  connection of its own with `SET TRANSACTION READ ONLY` that is never committed (a write in it fails with
  ORA-01456). SQL Server has no read-only session: the connection asks for `ApplicationIntent=ReadOnly` (which sends
  it to a readable secondary of an availability group, where there is one), runs `SET NOCOUNT ON`,
  `SET IMPLICIT_TRANSACTIONS OFF` and `SET LOCK_TIMEOUT`, and otherwise relies on the query check. **Give the import
  a database user that may only read** (on SQL Server the role `db_datareader`, on Oracle `CREATE SESSION` and
  `SELECT` on the tables it needs, on the others a user with `SELECT` rights only). Multiple statements and emulated
  prepares are off, MySQL's `LOCAL INFILE` too.
- **A time limit** of `[SqlSourceSettings] QueryTimeout` seconds is set on the server where it has one (MySQL
  `MAX_EXECUTION_TIME`, MariaDB `max_statement_time`, PostgreSQL `statement_timeout`, Oracle the call timeout of
  oci8, SQL Server the query timeout of pdo_sqlsrv or pdo_dblib); reading the rows stops after ten times as long.

**Drivers.** Oracle and SQL Server need a PHP extension that not every host has:

| Database | Driver, in this order | Character set |
|---|---|---|
| Oracle | `oci8`, else `pdo_oci` (`[SqlSourceSettings] OracleDriver`) | AL32UTF8 (UTF-8) |
| SQL Server | `pdo_sqlsrv`, else `pdo_dblib` (FreeTDS), else `PDO_ODBC` with an ODBC driver for SQL Server (`[SqlSourceSettings] SqlServerDriver`, `OdbcDriver`) | UTF-8 (the UTF-8 encoding of pdo_sqlsrv, `charset=UTF-8` of dblib, `ClientCharset=UTF-8` of the FreeTDS ODBC driver) |

What the host needs for a missing driver (packages of the system or of the PHP distribution; Plesk and other control
panels have their own):

- **Oracle:** the Oracle Instant Client (RHEL: `oracle-instantclient-basic`; Debian: the Instant Client from
  Oracle) and the PECL extension `oci8` (RHEL: `php-oci8` of Remi; Debian: built with PECL), or `pdo_oci`.
- **SQL Server:** the Microsoft ODBC Driver 18 (`msodbcsql18` from Microsoft's repository, RHEL and Debian) and the
  PECL extension `pdo_sqlsrv` (RHEL: `php-sqlsrv` of Remi; Debian: `php-sqlsrv` or PECL); or `pdo_dblib` with FreeTDS
  (RHEL: `php-pdo-dblib`, `freetds`; Debian: `php-sybase`, `freetds-common`); or `PDO_ODBC` with an ODBC driver
  (RHEL: `php-odbc`, `unixODBC` and `msodbcsql18` or `freetds`; Debian: `php-odbc`, `unixodbc` and `msodbcsql18` or
  `tdsodbc`).

The **catalogue** (the card of the format) and the editor's source step show which databases this server can read:
green with the driver used, grey with what the host needs. Runs are read by the **job queue's workers**, and their
PHP is not always the PHP of the web server (the administration may be served by another PHP than the one the
queue's cron job starts). The workers note their PHP and its drivers at every run; the list then also says "in the
workers" for a database the web server's PHP cannot read, and a test or a sample in the editor says that runs still
work. Encryption of SQL Server connections: `[SqlSourceSettings] SqlServerEncrypt` (enabled) and
`SqlServerTrustServerCertificate` (disabled). The Microsoft ODBC driver gives text in the locale of the PHP process:
give the workers a UTF-8 locale (`LANG=C.UTF-8`), or name the character set in `[SqlSourceSettings] OdbcCharset` to
have it converted.

**Oracle connections**, in one of three forms:

- host, port and the **service name** as database: `ora.example.org`, `1521`, `ORCLPDB1`;
- no host, and **Easy Connect** as database: `//ora.example.org:1521/ORCLPDB1` (the host is checked the same way);
- no host, and a **TNS name** as database: only one the administrator lists in `[SqlSourceSettings]
  AllowedTnsNames[]`, since the server's `tnsnames.ora` decides where it leads.

Connect descriptors (`(DESCRIPTION=...)`) are not accepted. Dates and timestamps come in ISO 8601
(`2026-10-22T10:11:12`, `...T10:11:12.500000`: the session's `NLS_DATE_FORMAT` and `NLS_TIMESTAMP_FORMAT`), numbers
as Oracle writes them, with a decimal point (`3.5`, `.25`; `TO_CHAR` in the query for another form), `CLOB` and
`NCLOB` as text, `BLOB` and `RAW` as `base64:...`.

**SQL Server example:**

```sql
SELECT TOP 100 [Id], [Name], [Description] FROM [dbo].[Products] ORDER BY [Changed] DESC
```

**Test connection.** On the source step of an import of this format, *Test connection* connects with the options of
the form, runs the query for its first row and shows its columns, or the error (a password never appears: it is
masked). Nothing is saved; it needs the same permission as saving the import.

Where it may connect:

- A database server in a private or reserved network is refused like an address, unless
  `[SourceSettings] AllowPrivateHosts=enabled` or the administrator lists it in `[SqlSourceSettings] AllowedHosts[]`
  (the usual case: an intranet database).
- An SQLite file must lie inside one of `[SourceSettings] AllowedPaths[]`.
- This site's own database (`site`) only with `[SqlSourceSettings] SiteDatabase=enabled`, over a read-only
  connection of its own. Its tables hold user accounts and password hashes: enable it only if the people who edit
  imports may see all of it.

**Example:** `share/examples/sql-teas.sqlite` is a small SQLite database with a table `teas` (`id`, `name`, `origin`,
`kind`, `price`, `description`, `updated`). Copy it into a directory of `AllowedPaths[]` (for example `var/import`)
and create an import with the driver `sqlite`, the path of the file as database, the key `id` and the query

```sql
SELECT id, name, origin, kind, price, description FROM teas WHERE kind <> 'herbal' ORDER BY id
```

**TimescaleDB** is PostgreSQL with an extension, so it has no source of its own: read it with the driver `pgsql` of
this source. Its functions are ordinary functions in a `SELECT` (`time_bucket`, `first`, `last`, continuous
aggregates as views), for example

```sql
SELECT time_bucket('1 hour', time) AS hour, room, avg(temperature) AS temperature
FROM climate WHERE time > now() - interval '1 day' GROUP BY hour, room ORDER BY hour, room
```

### Secrets

A source never stores a password. Its option of the type `secret` holds the **name** of a secret, and the value is
read when a run needs it:

- `<name>`: `sqliimport.ini [SecretSettings] Secrets[<name>]=<value>`, set in the site's own settings
  (`settings/override/sqliimport.ini.append.php` or a siteaccess), which are not part of a repository;
- `env:<VARIABLE>`: an environment variable of the PHP process (the PHP-FPM pool, the worker).

**Imports, Settings** lists the names of the secrets that are set, never a value. Messages and logs show a secret
as `[secret <name>]`, also when a server quotes it in an error.

**From Exponential 6.0.15 on**, the kernel's secret store answers first: values set in **Setup, Secrets** (stored
encrypted, never shown again; this tool's scope is `sqliimport`), `secrets.ini` and `env:`; `sqliimport.ini
[SecretSettings] Secrets[]` stays as the fallback, so existing imports need no change. To move a value, set it in
Setup, Secrets under the same name and remove it from the settings. **Imports, Settings** then links to that page.

## Elasticsearch or OpenSearch index (`elasticsearch`)

Group: NoSQL databases. Origin: `remote`. The documents of an Elasticsearch or OpenSearch index, alias or index pattern, read
through the search API over HTTP. One record is one document: its `_source` with nested objects flattened to dotted
field names (`author.name`, `author.address.city`), a list of plain values kept as a list (`tags`), a list of objects
numbered (`images.0.url`), and `_id` and `_index` besides. Once per run the source pages through the search and writes
the documents into the run's directory, one JSON object per line.

| Option | Values | Default |
|---|---|---|
| `base_url` | the address of the cluster, `https://search.example.org:9200` (no query, no credentials in it) | required |
| `index` | an index, an alias or a pattern: `articles`, `logs-2026-*`, `news,blog` | required |
| `auth` | `none`, `basic` (user and password), `api_key`, `bearer` (a token) | `none` |
| `user` | with `basic`: the user name | |
| `password_secret` | with `basic`: the **name** of the secret with the password | |
| `api_key_secret` | with `api_key`: the **name** of the secret with the encoded API key (the `encoded` value Elasticsearch gives; sent as `Authorization: ApiKey ...`) | |
| `token_secret` | with `bearer`: the **name** of the secret with the token | |
| `query` | query DSL as JSON: a query (`{"term": {"status": "published"}}`) or a body with `query`, `sort` and `_source` | all documents |
| `source_fields` | the fields of `_source` to read, one per line (`title`, `author.*`) | all |
| `sort` | fields with `:asc` or `:desc`, comma separated: `published_at:desc,_id` | the sort of the query, else the index order |
| `page_size` | documents per request, 1 to 10000 | `500` |
| `paging` | `auto`, `pit` (point in time), `search_after`, `scroll` | `auto` |
| `key` | the field that names a document | `_id` |

**Read-only.** The source builds only these requests: `GET /` (the version), `POST <index>/_search`, and the requests
that open, use and close a point in time or a scroll for that search. The index is checked before anything is sent
(letters, digits, `_ . - * +` and commas; no path such as `articles/_delete_by_query`, no remote cluster `other:index`,
nothing starting with `_` or `-`). The query may only be a query clause or a body with `query`, `sort` and `_source`
at its top; anything else (`size`, `aggs`, `pit`, `script_fields`, `runtime_mappings`, `suggest`, `rescore` ...) is
refused, and so is a script anywhere in it (`script`, `script_score`, `scripted_metric`, `script_fields`,
`runtime_mappings`). Give the import a user or an API key with the `read` privilege on the index only (and, for
the version, `monitor` on the cluster; without it the source still works and pages with `search_after` or a scroll).

**Paging** (`auto`): Elasticsearch 7.10 and later through a point in time (`_pit`) with `search_after` and the implicit
tie-breaker `_shard_doc`; OpenSearch 2.4 and later through its point in time (`_search/point_in_time`) when the import
gives a sort; otherwise `search_after` alone when the last field of the sort is unique (`_id`), else a scroll (`_doc`
order), which also serves old servers. A point in time or a scroll stays open two minutes between pages and is closed
at the end, also when a run stops early. `[SearchSourceSettings] MaxPages` limits the pages of a run (1000).

**Test connection** reads the version (`GET /`) and searches for one document: it shows the server
(`Elasticsearch 8.11.0`, `OpenSearch 2.11.0`) and the fields of that document, or the error with the server's reason
(`index_not_found_exception`, `parsing_exception`). A password, key or token never appears: it is masked as
`[secret <name>]`, also when a server quotes it. Nothing is saved.

**What the host needs:** PHP's `http` and `https` streams (`allow_url_fopen=On` and the extension `openssl`) in the
web server's PHP and in the PHP of the job queue's workers; the catalogue and the source step list them. curl is not
needed. Certificates are verified, answers are limited to `[PlatformSettings] MaxResponseMegabytes`, the time limit
is `[ImportSettings] StreamTimeout`, and a redirect to another host loses the credentials. A server in a private or
reserved network is refused unless `[SourceSettings] AllowPrivateHosts=enabled` or it is listed in
`[SearchSourceSettings] AllowedHosts[]` (the usual case: an intranet cluster).

**Example:** `share/examples/search-articles.ndjson` is a snapshot of three articles as the source writes it. A query
for the published articles, newest first:

```json
{"query": {"term": {"status": "published"}}, "sort": [{"published_at": "desc"}]}
```

## DuckDB query over files (`duckdb`)

Group: NoSQL databases. Origin: `remote`. The rows of a DuckDB query over files on the server: Parquet, CSV or JSON files (one,
several, or a pattern such as `var/import/exports/*.parquet`), or a DuckDB database file. One record is one row, its
fields the columns of the query. The files are the view `data` of the query (`SELECT * FROM data WHERE ...`); a
database file is opened read-only and the query names its tables. Once per run the source runs the query page by page
and writes the rows into the run's directory, one JSON object per line.

| Option | Values | Default |
|---|---|---|
| `paths` | one path per line, inside `[SourceSettings] AllowedPaths[]`; a pattern (`*`, `?`, `[...]`) reads every file it matches | required |
| `reader` | `auto` (by the file name: `.parquet`, `.csv`/`.tsv`, `.json`/`.ndjson`/`.jsonl`, `.duckdb`/`.db`), `parquet`, `csv` (delimiter and header detected), `json`, `duckdb` | `auto` |
| `query` | a single `SELECT` (or `WITH ... SELECT`) | `SELECT * FROM data` |
| `page_size` | rows per query (`LIMIT` and `OFFSET` around the query); `0`: all rows at once | `10000` |
| `key` | the column that names a row | the position |

Give the query an `ORDER BY` when it has more rows than a page: the pages are separate queries, and only a sorted
result gives them the same order.

**Read-only**, checked three times:

- **The files:** every path must lie inside `[SourceSettings] AllowedPaths[]` (the real path, so a link out of it is
  refused), `..` is refused, a pattern must match at least one file, at most `[DuckDbSourceSettings] MaxFiles`. A
  database is a single file and is opened with `-readonly` (FFI: `access_mode=READ_ONLY`). Addresses (`https://`,
  `s3://`) only with `[DuckDbSourceSettings] AllowRemoteFiles=enabled`; an http host in a private network only when
  listed in `[DuckDbSourceSettings] AllowedHosts[]`; DuckDB's `httpfs` extension must be installed.
- **The query** is checked like the SQL source's, in the dialect of PostgreSQL (whose parser DuckDB uses: dollar
  quoting, `E'...'` strings, nested comments), and in addition refused with `COPY`, `ATTACH`, `DETACH`, `INSTALL`,
  `LOAD`, `SET`, `RESET`, `PRAGMA`, `EXPORT`, `IMPORT`, `CHECKPOINT`, `USE`, `SECRET`, the functions that read other
  files or run SQL (`read_csv`, `read_parquet`, `read_json`, `read_text`, `read_blob`, `glob`, `query`, `query_table`,
  `sqlite_scan`, `postgres_scan` ...), `getenv`, and a file named as a table (`FROM 'file.csv'`,
  `FROM "file.csv"`). A column with such a name can be quoted (`"query"`).
- **The session:** before the query, DuckDB may neither install nor load extensions on its own
  (`autoinstall_known_extensions`, `autoload_known_extensions` off); with `[DuckDbSourceSettings] Sandbox=enabled`
  (DuckDB 1.3 or later) it may reach only the files of the import (`allowed_paths` and
  `enable_external_access=false`); then the configuration is locked (`lock_configuration`), so the query cannot
  change any of it. Set `Sandbox=disabled` only for an older DuckDB; then the query check alone keeps it to the files.

**Drivers**, in this order (`[DuckDbSourceSettings] Driver`: `auto`, `cli`, `ffi`):

- **The duckdb program** (`[DuckDbSourceSettings] Binary`, a name looked up in the `PATH`, `/usr/local/bin` and
  `/opt/duckdb`, or a full path). It is started without a shell (the arguments as a list), with `-json`, `-bail`,
  `-init /dev/null` (no `~/.duckdbrc`) and a reduced environment (no secrets of the PHP process), the statements on its
  input. It is stopped after `[DuckDbSourceSettings] QueryTimeout` seconds (60) per page or when a page's output is
  larger than `MaxOutputMegabytes` (100). Values come as DuckDB writes them in JSON (numbers, text, lists).
- **PHP FFI with libduckdb** (`[DuckDbSourceSettings] Library`, else `libduckdb.so` in `/usr/local/lib`, `/usr/lib64`,
  `/usr/lib` ...), when the PHP extension FFI is loaded with `ffi.enable=On` (or `preload` for the workers' CLI). Values
  come as text; the time limit is not enforced.

**What the host needs** without either: the duckdb command line program, a single file from duckdb.org (install it as
`/usr/local/bin/duckdb`), in the PHP of the job queue's workers too; or the PHP extension FFI (RHEL: `php-ffi`;
Debian: part of the `php` package) with `libduckdb.so` from duckdb.org. The catalogue and the source step list both
drivers, green with what is used, grey with what is missing, and what the workers had at their last run.

**Test connection** checks the files and the query, runs it for its first row and shows the columns and the driver,
or the error of DuckDB. Nothing is saved.

**Example:** `share/examples/duckdb-teas.csv` (five teas). Copy it into a directory of `AllowedPaths[]` (for example
`var/import`) and create an import with that path, the key `id` and the query

```sql
SELECT id, name, origin, kind, price FROM data WHERE kind <> 'herbal' ORDER BY id
```

## E-mail inbox via IMAP (`imap`)

Group: content. Origin: `remote`. The messages of an IMAP mailbox (a press inbox, a submissions address): one record
is one message. Once per run the source reads the matching messages into the run's directory, one per line; the
editor's sample reads the newest ones.

| Field | What it holds |
|---|---|
| `message_id` | the Message-ID without `<>` (the key); a message without one gets `sha1-...` of its content |
| `uid`, `mailbox` | the message's UID in the mailbox, and the mailbox |
| `from` | `name`, `address` of the sender (`from.address`) |
| `to`, `cc`, `reply_to` | lists of `name`, `address` (`to.0.address`) |
| `subject` | the subject, decoded to UTF-8 |
| `date` | the date in ISO 8601 with its offset (`2026-10-13T09:15:00+02:00`) |
| `text` | the text body (`text/plain`; else the HTML body as text) |
| `html` | the HTML body, if there is one |
| `summary` | the first paragraph of the text |
| `body` | the HTML body, else the text as HTML paragraphs |
| `attachments` | list of `name`, `mime`, `size` (bytes) of the attachments, never their content; `attachment_count` |
| `in_reply_to`, `size`, `truncated` | the message it answers, its size, and whether it was read with its headers only |

Headers are decoded from RFC 2047 encoded words and RFC 2231 parameters, bodies from quoted-printable and base64 and
from their charset; 8-bit text that is not UTF-8 is taken as Windows-1252.

| Option | Values | Default |
|---|---|---|
| `host`, `port` | the IMAP server | port `993` |
| `encryption` | `ssl` (TLS from the start, port 993), `tls` (STARTTLS, port 143), `none` (not recommended: the password is sent in clear) | `ssl` |
| `user` | the login | |
| `password` | the **name** of a secret, never the password | |
| `mailbox` | the folder | `INBOX` |
| `search` | IMAP search criteria: `ALL`, `UNSEEN`, `FROM "news@example.org"`, `SUBJECT "Press"` ... | `ALL` |
| `days` | only the messages of the last days (adds `SINCE`); `0`: all | `0` |
| `mark_seen` | mark the messages read as seen | no |
| `attachments` | list the attachments (names, types and sizes) | yes |
| `key` | the key field | `message_id` |

**The import does not change the mailbox** unless `mark_seen` is ticked: the mailbox is opened read-only
(`EXAMINE`, `OP_READONLY`) and messages are read without setting `\Seen`. Certificates are verified. A mail server in
a private network is refused unless `AllowPrivateHosts=enabled` or it is listed in
`[ImapSourceSettings] AllowedHosts[]`. A message larger than `[ImapSourceSettings] MaxMessageMegabytes` is read with
its headers only and marked `truncated`; `[ImapSourceSettings] Timeout` limits connecting and each answer.

The PHP extension `imap` is used where it is installed; without it (it left the core of PHP 8.4) the source speaks
IMAP itself over a TLS socket (`LOGIN`, `EXAMINE`/`SELECT`, `UID SEARCH`, `UID FETCH BODY.PEEK[]`, `LOGOUT`). That
client needs user and password in printable ASCII.

The preset maps a message to an article, found again by the remote ID `mail-{message_id}` (shortened with a hash
when it is long): title from `subject`, intro from `summary`, body from `body` (both as rich text), publish date from
`date`.

## MongoDB collection (`mongodb`)

Group: NoSQL databases. Origin: `remote`. The documents of a MongoDB collection (the database of another application, a product
or event store): one record is one document. Once per run the source reads the matching documents page by page and
writes them into the run's directory, one JSON object per line; the editor's sample reads the first ones.

| Option | Values | Default |
|---|---|---|
| `uri` | a host (`mongo.example.org`), `host:port`, or a `mongodb://` or `mongodb+srv://` URI **without user and password** (`mongodb://db1.example.org,db2.example.org/?replicaSet=rs0`; MongoDB Atlas: `mongodb+srv://cluster0.example.mongodb.net/?retryWrites=true&w=majority`) | required |
| `port` | only with a host; `0` is 27017 | `0` |
| `tls` | encrypt the connection (certificates are verified); `mongodb+srv://` always uses TLS | no |
| `user` | the database user | |
| `password` | the **name** of a secret (see [Secrets](#secrets)), never the password | |
| `auth_source` | the database the user is defined in; empty: the database of the URI, else `admin` | |
| `database`, `collection` | what to read | required |
| `mode` | `find` (filter, projection, sort) or `aggregate` (an aggregation pipeline of reading stages) | `find` |
| `pipeline` | with `aggregate`: a JSON list of stages, e.g. `[{"$match": {"status": "published"}}, {"$group": {"_id": "$author", "n": {"$sum": 1}}}]` | |
| `filter` | a query document in JSON, e.g. `{"status": "published"}`; Extended JSON for types: `{"$oid": "..."}`, `{"$date": "2026-01-01T00:00:00Z"}` | every document |
| `projection` | the fields to read, e.g. `{"title": 1, "author.name": 1}` | all |
| `sort` | e.g. `{"published_at": -1}` (needs the paging `skip`) | `_id` |
| `paging` | `id`: each page after the last `_id` (fast, in `_id` order); `skip`: skip and limit (any sort order; with `aggregate` a `$skip` and `$limit` stage per page); `cursor`: one query read in batches of `batch_size` (any order; the default of `aggregate`) | `id` |
| `batch_size` | documents per page (at most `[MongoDbSourceSettings] MaxBatchSize`) | `500` |
| `limit` | the most documents; `0`: all | `0` |
| `arrays` | `json` (`["a","b"]`) or `join` (`a, b`; an array of documents stays JSON) | `json` |
| `separator` | what stands between the values of a joined array | `, ` |
| `read_preference` | `primary`, `primaryPreferred`, `secondaryPreferred`, `secondary`, `nearest` | `primary` |
| `key` | the key field; a nested field by its dotted name (`meta.sku`) | `_id` |

**Fields.** Nested documents are flattened into dotted names: `{"author": {"name": "...", "address": {"city": "..."}}}`
gives the fields `author.name` and `author.address.city`. Documents nested deeper than 32 levels are written as JSON.
The BSON types are written as text:

| BSON | Field value |
|---|---|
| ObjectId | its 24 hex digits (`6717a1f0c2d4e5f601234561`) |
| Date (UTCDateTime) | ISO 8601 in UTC: `2026-04-02T08:30:00Z`, with milliseconds when it has them (`...08:30:00.250Z`) |
| Decimal128 | its decimal string (`12.50`), never a float |
| Binary | base64; a UUID (subtype 3 or 4) as `0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0` |
| Int64, Int32, Double, Boolean, Null | as they are |
| Timestamp | ISO 8601 of its seconds |
| Regular expression, JavaScript | `/pattern/flags`, the code |
| Array | JSON, or a joined list (option `arrays`); BSON values inside are written the same way |

**Only reading is possible.** The source runs nothing but a find (`MongoDB\Driver\Query`) with the filter,
projection and sort, or (mode `aggregate`) the `aggregate` command sent as a read command (`executeReadCommand`):
no other command, no write. Refused, at any depth of the filter, projection, sort or pipeline, are the operators that
run code on the server or write: `$where`, `$function`, `$accumulator`, `$out`, `$merge` (and `$currentOp`,
`$listSessions`). A filter must be one JSON object. A pipeline may only have these reading stages: `$match`,
`$project`, `$addFields`, `$set`, `$unset`, `$sort`, `$limit`, `$skip`, `$unwind`, `$group`, `$bucket`, `$bucketAuto`,
`$count`, `$facet`, `$lookup`, `$replaceRoot`, `$replaceWith`, `$sample`, `$sortByCount`, `$densify`, `$fill`,
`$setWindowFields`, `$geoNear` (only as the first stage) and `$unionWith`; the pipelines inside `$facet`, `$lookup`
and `$unionWith` are checked the same way, and any other stage (`$out`, `$merge`, `$currentOp`, `$collStats`,
`$indexStats`, `$changeStream`, `$documents`, `$search` ...) is refused. In mode `aggregate` the filter, projection
and sort stay empty (they are `$match`, `$project` and `$sort` stages). System collections (`system.*`) are not read,
nor joined by `$lookup` or `$unionWith`, which name a collection of the same database only. Give the import a
database user with the role `read` on that database only.

**Paging an aggregation.** `cursor` (the default of an aggregation) sends the pipeline once and reads its cursor in
batches of `batch_size` (with a `$limit` stage at the end for the editor's sample or the option `limit`); `skip`
appends `{"$skip": n}` and `{"$limit": batch_size}` to the pipeline for each page, which needs a pipeline with a stable
order (end it with a `$sort` on a unique field).

**MongoDB Atlas** (and other hosted services) is reached with its `mongodb+srv://` URI as Atlas shows it under
Connect, without `<username>:<password>@`: the user is the option `user` and the password a secret. `mongodb+srv://`
always uses TLS, and the hosts its SRV record names are checked like any host (the public hosts of Atlas pass). An
Azure Cosmos DB account of the API for MongoDB is read the same way, with its connection string without the password.

**Secrets and hosts.** The URI must not hold a user or a password (nor URI options that carry one); the user is an
option and the password the name of a secret, handed to the driver as connection options and masked as
`[secret <name>]` in every message. A server in a private or reserved network is refused unless
`[SourceSettings] AllowPrivateHosts=enabled` or it is listed in `[MongoDbSourceSettings] AllowedHosts[]` (each host
of the URI; for `mongodb+srv://` the SRV name, or every host its SRV record names). `[MongoDbSourceSettings] Timeout`
limits connecting, choosing a server and each query on the server (`maxTimeMS`).

**Test connection.** On the source step, *Test connection* connects with the options of the form, reads the first
matching document and shows its fields, or the error (masked). Nothing is saved.

**Driver.** The PECL extension `mongodb` (1.15 or later; the composer library `mongodb/mongodb` is not needed) in the
PHP of the job queue's workers, which read the runs, and in the web server's PHP for the editor's test and sample.
RHEL: `php-pecl-mongodb` of Remi or `pecl install mongodb`; Debian: `php-mongodb`; Plesk: `pecl install mongodb` for
its PHP. The catalogue and the source step show whether this server and the workers have it.

**Example:** `share/examples/mongodb-articles.ndjson` is what a run reads from a collection of articles (three
documents, flattened), to try a mapping; the catalogue's other examples are configurations.

## Redis keys (`redis`)

Group: NoSQL databases. Origin: `remote`. The keys of a Redis database that match a pattern (a cache of product data, the store
of another application): one record is one key. Once per run the source walks the keys with `SCAN` and writes them
into the run's directory, one JSON object per line.

| Option | Values | Default |
|---|---|---|
| `host`, `port` | the Redis server | port `6379` |
| `tls` | encrypt the connection (the certificate is verified) | no |
| `database` | the database index (`SELECT`) | `0` |
| `user` | an ACL user (Redis 6 and later); empty: the password only | |
| `password` | the **name** of a secret, never the password; empty: no `AUTH` | |
| `pattern` | the keys to read, as `SCAN ... MATCH` reads it: `product:*`, `news:[0-9]*` | `*` |
| `types` | the types to read, separated by commas: `hash`, `string`, `list`, `set`, `zset`, `stream`, `json` (RedisJSON) | all seven |
| `json_path` | RedisJSON keys: the path `JSON.GET` reads, `$` (the whole document) or `$.product`; a legacy path (`.product`) works too | `$` |
| `scan_count` | the `COUNT` of each `SCAN` (a hint how many keys the server looks at per step) | `100` |
| `max_items` | the most values (entries) read of one list, set, sorted set or stream | `1000` |
| `limit` | the most keys; `0`: all | `0` |
| `decode_json` | a string value that is a JSON object or list is read as its fields (`value.title`) | yes |
| `arrays` | `keep` (a list: `values.0`, `values.1` ...), `json` or `join` | `keep` |
| `separator` | what stands between the values of a joined list | `, ` |
| `key` | the key field | `_key` |

Every record has `_key` (the key), `_type` and `_ttl` (seconds, `-1` without expiry), and by type:

| Type | Fields | Command |
|---|---|---|
| hash | its fields as fields (a field named `_key`, `_type` or `_ttl` is left out) | `HGETALL` |
| string | `value`: the text, or its fields when it is JSON | `GET` |
| list | `values`, the first `max_items` | `LRANGE` |
| set | `values`, sorted, so two runs give the same record | `SMEMBERS` |
| sorted set | `values` (the members, by score) and `scores` | `ZRANGE ... WITHSCORES` |
| stream | `entries`: a list of `id` and the entry's fields, the first `max_items` | `XRANGE - + COUNT` |
| json (RedisJSON, type `ReJSON-RL`) | the document's fields as fields, nested objects as dotted names (`dims.w`), lists by the option `arrays`, lists of objects numbered (`variants.0.g`); a value that is not an object as `value` | `JSON.GET <key> <json_path>` |

Values that are not UTF-8 are written as `base64:...`. Keys of the types of other modules (RedisBloom, RedisTimeSeries
...) are left out. A JSONPath (`$...`) gives the list of its matches; one match is read as the value itself.

**Only reading is possible.** After `AUTH` and `SELECT` the source sends only `SCAN`, `TYPE`, `TTL`, `HGETALL`,
`GET`, `LRANGE`, `SMEMBERS`, `ZRANGE`, `XRANGE` and `JSON.GET`; the client refuses any other command (also
`JSON.SET`, `JSON.DEL`, `JSON.MERGE` ...) before it reaches the server. `KEYS` is never used: `SCAN` walks the keys in steps without blocking the server, and a key it gives twice
is read once. A run stops when more than `[RedisSourceSettings] MaxScannedKeys` keys match (narrow the pattern).
Give the import an ACL user that may only read, e.g. `ACL SETUSER reader on >... ~product:* +@read` (`+@read`
includes `JSON.GET`).

**Secrets and hosts.** The password is the name of a secret and masked in every message. A server in a private or
reserved network is refused unless `AllowPrivateHosts=enabled` or it is listed in `[RedisSourceSettings]
AllowedHosts[]`. `[RedisSourceSettings] Timeout` limits connecting and each answer.

**Test connection.** *Test connection* connects, looks for the first matching key of the chosen types (in at most
`[RedisSourceSettings] TestScanRounds` steps of `SCAN`) and shows its fields, or the masked error.

**Drivers.** The PECL extension `redis` (phpredis) where it is installed (RHEL: `php-pecl-redis` of Remi or `pecl
install redis`; Debian: `php-redis`); without it, a small client of the extension speaks RESP over a socket (driver
`socket`, with `openssl` for TLS), so the source works on every PHP. `[RedisSourceSettings] Client` chooses: `auto`
(the default), `phpredis` or `socket`. The built-in client refuses a value larger than `MaxValueMegabytes`.

**Example:** `share/examples/redis-products.ndjson` is what a run reads from the keys `product:*` (two hashes, a JSON
string and a set), to try a mapping.

## CouchDB database (`couchdb`)

Group: NoSQL databases. Origin: `remote`. The documents of a CouchDB database (also IBM Cloudant and other servers
that speak the CouchDB API), read over HTTP: all documents (`_all_docs`), the rows of a view of a design document, or
the answer of a Mango query (`_find`). One record is one document with `_id` and `_rev`. Once per run the source pages
through the documents and writes them into the run's directory, one JSON object per line.

| Option | Values | Default |
|---|---|---|
| `base_url` | the address of CouchDB, `https://couch.example.org:6984` (no query, no credentials in it; a path for a reverse proxy is allowed) | required |
| `database` | the name of the database (small letters, digits and `_ $ ( ) + -`; never a path) | required |
| `auth` | `none`, `basic` (user and password on each request), `cookie` (a session: `POST /_session`, then the `AuthSession` cookie) | `none` |
| `user`, `password_secret` | the user and the **name** of the secret with the password | |
| `mode` | `all_docs`, `view` or `mango` | `all_docs` |
| `design`, `view` | with `view`: the design document (`news` or `_design/news`) and the view | |
| `include_docs` | `all_docs` and `view`: read each document (else only `_id` and `_rev`, or `_id`, `key` and `value` of a view's row) | yes |
| `reduce` | `view`: read the results of the reduce function, grouped by key (`group=true`, no documents) | no |
| `keys` | `all_docs` and `view`: only these keys (a JSON list), sent with `POST` in slices of a page | |
| `startkey`, `endkey` | `all_docs` and `view`: the range of keys, as JSON (`"2026-01-01"` with the quotes, `["news", 1]`) | |
| `selector` | `mango`: a selector, e.g. `{"type": "article", "year": {"$gte": 2025}}` | every document |
| `fields` | `mango`: the fields to read, one per line | all |
| `sort` | `mango`: e.g. `[{"published_at": "desc"}]` (needs an index on the fields) | |
| `include_design` | `all_docs`: read the design documents (`_design/...`) too | no |
| `page_size` | documents per request, 1 to 10000 | `500` |
| `limit` | the most documents; `0`: all | `0` |
| `arrays`, `separator` | arrays as JSON (`["a","b"]`) or a joined list (`a, b`; arrays of objects stay JSON) | `json`, `, ` |
| `key` | the key field | `_id` |

**Fields and types.** Nested objects are flattened into dotted names as the MongoDB source does (`author.name`,
`author.address.city`); strings, numbers, `true`/`false` and `null` stay as they are, arrays become JSON or a joined
list. `_id` and `_rev` are kept, as are the stubs of `_attachments` (`_attachments.photo.jpg.content_type` ...); the
attachments themselves are not read. Deleted documents and keys that are not found are left out.

**Only reading is possible.** The source builds only these requests, and checks each one before it is sent:
`GET /` (the version), `POST /_session` (cookie authentication), `GET` or `POST <db>/_all_docs`, `POST <db>/_find` and
`GET` or `POST <db>/_design/<doc>/_view/<view>`. Anything else (`_bulk_docs`, `_update`, `_show`, `_design` writes,
`PUT`, `DELETE`, `_replicate`, `_users` ...) is refused. The database, design document and view are names, never a
path. A Mango selector may only use Mango's query operators (`$and`, `$or`, `$not`, `$nor`, `$all`, `$elemMatch`,
`$allMatch`, `$keyMapMatch`, `$lt`, `$lte`, `$eq`, `$ne`, `$gte`, `$gt`, `$exists`, `$type`, `$in`, `$nin`, `$size`,
`$mod`, `$regex`, `$beginsWith`) at any depth. Give the import a user that is a member (reader) of the database only,
without the admin role.

**Paging.** `_all_docs` and views ask for one row more than a page and start the next page at that row (`startkey`,
and for a view `startkey_docid`, so the rows of a key that is emitted many times are neither lost nor read twice);
Mango pages with the `bookmark` of each answer; `keys` are sent in slices of a page. `[CouchDbSourceSettings] MaxPages`
limits the requests of a run.

**Secrets and hosts.** The password is the name of a secret, masked as `[secret <name>]` in every message, and the
session cookie is never shown. A server in a private or reserved network is refused unless
`[SourceSettings] AllowPrivateHosts=enabled` or it is listed in `[CouchDbSourceSettings] AllowedHosts[]`.

**Test connection** reads the version (`GET /`, shown as `CouchDB 3.4.2` or `Cloudant ...`) and the first document,
and shows its fields, or the error with the server's reason (`not_found`, `unauthorized`), masked.

**What the host needs:** PHP's `http` and `https` streams (`allow_url_fopen=On`, the extension `openssl`), the same as
the Elasticsearch source; certificates are verified, answers are limited to `[PlatformSettings] MaxResponseMegabytes`
and the time limit is `[ImportSettings] StreamTimeout`.

**Example:** `share/examples/couchdb-articles.ndjson` is what a run reads from a database of articles (three
documents, flattened).

## Couchbase query (`couchbase`)

Group: NoSQL databases. Origin: `remote`. The results of a SQL++ (N1QL) query of Couchbase Server or Couchbase Capella,
read through the REST API of the Query service: `POST /query/service` on port 8093, or 18093 with TLS. One record is
one result: an object flattened to dotted names (`stock.store`), a plain value (`SELECT RAW`) as `value`.

| Option | Values | Default |
|---|---|---|
| `base_url` | the Query service: `https://cb.example.org:18093` (TLS) or `http://cb.example.org:8093` | required |
| `user`, `password_secret` | a user with the role Query Select on the keyspaces, and the **name** of the secret with the password | |
| `statement` | one `SELECT` (or `WITH ... SELECT`) without parameters | required |
| `query_context` | the bucket and scope the names of the query are read in (Couchbase 7): `default:shop.inventory` | |
| `paging` | `offset` (`LIMIT` and `OFFSET`; give the query an `ORDER BY`), `keyset` (after the last value of a unique field), `none` (one request) | `offset` |
| `keyset_field` | with `keyset`: a unique field of the results (`id`, `sku`, `meta.id`) | |
| `scan_consistency` | `not_bounded` (as the index stands) or `request_plus` (waits until the index has every change before the run) | `not_bounded` |
| `page_size` | results per request, 1 to 10000 | `500` |
| `limit` | the most results; `0`: all | `0` |
| `arrays`, `separator` | arrays as JSON or a joined list | `json`, `, ` |
| `key` | the key field; select `META(x).id AS id` for the document ID | `id` |

**Only reading is possible, twice over.** Every request carries `readonly=true`, so the Query service itself refuses
any statement that changes data. Before that, the source lets only a single `SELECT` or `WITH ... SELECT` through and
refuses, outside string literals, quoted identifiers and comments, the words of every other statement: `INSERT`,
`UPSERT`, `UPDATE`, `DELETE`, `MERGE`, `CREATE`, `DROP`, `ALTER`, `GRANT`, `REVOKE`, `EXECUTE` (also
`EXECUTE FUNCTION`), `INFER`, `PREPARE`, `BUILD`, `ADVISE`, `EXPLAIN`, `TRUNCATE`, `FLUSH`, the transaction statements
(`BEGIN`, `COMMIT`, `ROLLBACK`, `SAVEPOINT`, `SET`), `FUNCTION`, and `CURL()` (a request from the server). The system
keyspaces (`system:...`), parameters (`$name`, `?`) and a second statement after `;` are refused too. A field whose
name is one of these words is written in backticks (`` p.`update` ``). The only request is `POST <server>/query/service`.

**Paging.** `offset` wraps the statement as `SELECT RAW exp_page FROM (<statement>) AS exp_page LIMIT n OFFSET m`
(a `WITH` clause stays in front); the order is the query's own, so give it an `ORDER BY` on a unique field. `keyset`
wraps it as `... WHERE exp_page.<field> > $after ORDER BY exp_page.<field> LIMIT n`, with the last value of the page as
the named parameter `$after`: faster on large results, and the field must be in the results. `none` sends the
statement as it is, once. `[CouchbaseSourceSettings] QueryTimeout` is the request's `timeout` (30 seconds) and
`MaxPages` limits the queries of a run.

**Types.** JSON as it is: strings, numbers, `true`/`false`, `null`; nested objects as dotted fields, arrays as JSON or
a joined list. `MISSING` fields do not appear.

**Secrets and hosts.** The password is the name of a secret and masked in every message. A server in a private or
reserved network is refused unless `AllowPrivateHosts=enabled` or it is listed in `[CouchbaseSourceSettings]
AllowedHosts[]`.

**Test connection** runs the statement for one result (`LIMIT 1`) and shows its fields, or the error of the Query
service with its code (`12003: Keyspace not found ...`), masked.

**What the host needs:** PHP's `http` and `https` streams, as for CouchDB; no Couchbase SDK.

**Example:** `share/examples/couchbase-products.ndjson` is what a run reads from a query of products.

## Azure Cosmos DB container (`cosmosdb`)

Group: NoSQL databases. Origin: `remote`. The items of an Azure Cosmos DB container of the API for NoSQL (formerly the
SQL API) that a query selects, read through the REST API and paged with continuation tokens. One record is one item,
nested objects flattened to dotted names (`customer.id`). An account of the **API for MongoDB** is read with the
[MongoDB source](#mongodb-collection-mongodb) and its connection string (`mongodb://<account>.mongo.cosmos.azure.com:10255/?ssl=true&replicaSet=globaldb`
without the password, which is a secret); the APIs for Cassandra, Gremlin and Table are not read.

| Option | Values | Default |
|---|---|---|
| `endpoint` | the URI of the account: `https://<account>.documents.azure.com` | required |
| `database`, `container` | names (letters, digits, `_ . -`) | required |
| `auth` | `master_key`: the account's key signs each request (use the **read-only** key); `resource_token`: a resource token with the permission Read | `master_key` |
| `key_secret` | the **name** of the secret with the key (base64) or the token | required |
| `query` | one `SELECT` without parameters, e.g. `SELECT c.id, c.title FROM c WHERE c.status = 'published'` | `SELECT * FROM c` |
| `cross_partition` | query every partition | yes |
| `partition_key` | only one partition: its key value as JSON (`"tea"`, `42`) | |
| `max_item_count` | items per page, 1 to 1000 | `100` |
| `limit` | the most items; `0`: all | `0` |
| `system_fields` | keep `_rid`, `_self`, `_etag` and `_attachments` (`_ts`, the time of the last change, is always kept) | no |
| `arrays`, `separator` | arrays as JSON or a joined list | `json`, `, ` |
| `key` | the key field | `id` |

**Signing.** With an account key the source signs each request itself, as the REST API describes it: HMAC-SHA256
with the decoded key over the verb, the resource type (`docs`), the resource link (`dbs/<db>/colls/<container>`) and
the date (`x-ms-date`), sent as `Authorization: type=master&ver=1.0&sig=...`; no SDK is needed. A resource token is
sent as it is.

**Only reading is possible.** The only request is the query of the container: `POST .../dbs/<db>/colls/<container>/docs`
with `x-ms-documentdb-isquery: True`. The query must be one `SELECT`; parameters (`@name`), user-defined functions
(`udf.`, JavaScript on the server) and the words of statements that write (`INSERT`, `UPDATE`, `DELETE`, `UPSERT`,
`MERGE`, `CREATE`, `DROP`, `ALTER`, `EXEC`, `PATCH`) outside string literals are refused; a field of such a name is read
as `c["update"]`. The query language of the API for NoSQL has no statement that writes. Use the account's
**read-only** key (Keys, Read-only keys) or a resource token with the permission Read: then the account refuses any
write as well.

**Paging.** Each answer gives a continuation token (`x-ms-continuation`) when more items follow; the source sends it
with the next request until there is none. `[CosmosDbSourceSettings] MaxPages` limits the pages of a run. Across
partitions the gateway serves a filter and a projection; `ORDER BY`, `TOP`, `DISTINCT`, `GROUP BY` and aggregates
across partitions are refused by the gateway ("The provided cross partition query can not be directly served by the
gateway"): give a partition key for them.

**Types.** JSON as it is; nested objects as dotted fields, arrays as JSON or a joined list; `_ts` is the time of the
last change in seconds since 1970.

**Secrets and hosts.** The key or token is the name of a secret, masked in every message (also URL-encoded). An
endpoint in a private or reserved network (the emulator on `https://localhost:8081`, a private endpoint) is refused
unless `AllowPrivateHosts=enabled` or it is listed in `[CosmosDbSourceSettings] AllowedHosts[]`.

**Test connection** reads the first item of the query and shows its fields, or the error with the account's code
(`Unauthorized`, `NotFound`, `BadRequest: ...`), masked.

**What the host needs:** PHP's `https` stream (`allow_url_fopen=On`, `openssl`) and `hash_hmac` (part of PHP).

**Example:** `share/examples/cosmosdb-orders.ndjson` is what a run reads from a container of orders.

## Amazon DynamoDB table (`dynamodb`)

Group: NoSQL databases. Origin: `remote`. The items of a DynamoDB table or one of its indexes, read with `Scan` or
`Query` through the DynamoDB HTTPS API: one record is one item. The requests are signed with AWS Signature Version 4
by a small class of the extension (no AWS SDK). Once per run the source pages through the items and writes them into
the run's directory, one JSON object per line.

| Option | Values | Default |
|---|---|---|
| `region` | the AWS region of the table: `eu-central-1` | required |
| `endpoint` | empty: `https://dynamodb.<region>.amazonaws.com`; else DynamoDB Local or a compatible service (`http://localhost:8000`) | |
| `access_key_id` | the access key ID of an IAM user or role (`AKIA...`) | required |
| `secret_key_secret` | the **name** of the secret with the secret access key (see [Secrets](#secrets)) | required |
| `session_token_secret` | temporary credentials (STS) only: the name of the secret with the session token | |
| `table` | the table | required |
| `operation` | `scan` (every item, or those of the filter) or `query` (the items of a partition key) | `scan` |
| `index_name` | a global or local secondary index (`IndexName`) | the table |
| `key_condition` | `query` only, and required there: `KeyConditionExpression`, e.g. `customer_id = :c AND created_at > :d` | |
| `filter` | `FilterExpression`, e.g. `#s = :p AND price < :max` | |
| `names` | `ExpressionAttributeNames` as JSON: `{"#s": "status"}` | |
| `values` | `ExpressionAttributeValues` as JSON in the DynamoDB form: `{":p": {"S": "published"}, ":max": {"N": "20"}}` | |
| `projection` | `ProjectionExpression`: the attributes to read | all |
| `consistent_read` | `ConsistentRead` (not on a global secondary index) | no |
| `scan_forward` | `query` only: ascending sort key (`ScanIndexForward`) | yes |
| `page_size` | `Limit`: items evaluated per request (an answer is also at most 1 MB) | `100` |
| `limit` | the most items; `0`: all | `0` |
| `sets` | string, number and binary sets as a joined list (`join`) or JSON (`json`) | `join` |
| `separator` | what stands between the values of a joined set | `, ` |
| `key` | the attribute that names an item (its partition key); empty: the position | |

**Fields.** Every attribute is a field; maps are flattened into dotted names (`dimensions.weight_g`):

| AttributeValue | Field value |
|---|---|
| `S` | the text |
| `N` | its decimal text (`12.50`, also beyond PHP's numbers), never a float |
| `B` | base64, as DynamoDB sends it |
| `BOOL`, `NULL` | `true`/`false`, `null` |
| `M` | flattened to dotted names; an empty map is `{}` |
| `L` | JSON (`[{"size":"50g"},"7",false]`) |
| `SS`, `NS`, `BS` | a joined list (`green, japan`) or JSON (option `sets`) |

**Only reading is possible.** The source sends only the operations `Scan` and `Query` (and `DescribeTable` for
*Test connection*); every other name (`PutItem`, `UpdateItem`, `DeleteItem`, `BatchWriteItem`, `TransactWriteItems`,
`ExecuteStatement` for PartiQL ...) is refused before a request is built. Expressions only choose and shape items;
an update or condition expression is never sent. Give the access key an IAM policy that allows only reading:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["dynamodb:Scan", "dynamodb:Query", "dynamodb:DescribeTable"],
    "Resource": ["arn:aws:dynamodb:eu-central-1:123456789012:table/products",
                 "arn:aws:dynamodb:eu-central-1:123456789012:table/products/index/*"]
  }]
}
```

`dynamodb:DescribeTable` is only used by *Test connection* (status and item count); without it the test still reads
one item.

**Secrets and hosts.** The secret access key and the session token are names of secrets; the key is never sent (only
the signature is), and both are masked in every message. A custom endpoint in a private or reserved network is refused
unless it is listed in `[DynamoDbSourceSettings] AllowedHosts[]`.

**Paging.** `Limit` items per request, and `ExclusiveStartKey` with the `LastEvaluatedKey` of the answer until there
is none; at most `[DynamoDbSourceSettings] MaxPages` requests per run. Throttling (`ProvisionedThroughputExceededException`,
`ThrottlingException`) and 5xx answers are tried again after 1, 2, 4 and 8 seconds.

**Test connection.** `DescribeTable` and one page of one item of the scan or query; it shows the fields, or the masked
error.

**What the host needs.** PHP's https stream with `allow_url_fopen=On` and the extension `openssl` (part of the
php package on RHEL and Debian), in the web server's PHP and in the job queue's workers.

**Example:** `share/examples/dynamodb-products.ndjson` is what a run reads from a table of products (three items).

## Google Cloud: Firestore (`firestore`) and BigQuery (`bigquery`)

Both read with a **service account**: its JSON key file is the value of a secret, the import names the secret
(`credentials_secret`). The extension signs a JWT with the key (RS256, `openssl_sign`), exchanges it at
`https://oauth2.googleapis.com/token` for an access token (the `token_uri` of the key file is not followed) and keeps
the token for the rest of the run. The key, the JWT and the token are masked in every message. The IAM roles of the
service account decide what it may do: give it only the read roles named below. `[GoogleCloudSourceSettings] MaxPages`
limits the requests of a run.

### Firestore collection (`firestore`)

Group: NoSQL databases. Origin: `remote`. The documents of a collection, a subcollection or a collection group of a
Firestore database (native mode), read through the Firestore REST API v1: one record is one document.

| Option | Values | Default |
|---|---|---|
| `project` | the Google Cloud project | required |
| `database` | the database ID | `(default)` |
| `credentials_secret` | the **name** of the secret with the service account's JSON key | required |
| `scope` | `read_only` (`cloud-platform.read-only`) or `datastore` (when the read-only scope is refused; the role keeps it read-only) | `read_only` |
| `mode` | `query` (a structured query, `documents:runQuery`) or `list` (the list of the collection, no filter) | `query` |
| `collection` | `articles`, or a subcollection by its path: `customers/c-1001/orders` | required |
| `all_descendants` | `query` only: every collection of this name below the path (a collection group) | no |
| `where` | `query` only: the `where` of a structured query, e.g. `{"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}` | all |
| `order_by` | `published_at:desc,title`; the document name is always the last order | the name |
| `fields` | the fields to read, one per line (`select`, or `mask.fieldPaths` of a list) | all |
| `paging` | `cursor` (`startAt` the last document) or `offset` | `cursor` |
| `page_size` | documents per request | `300` |
| `limit` | the most documents; `0`: all | `0` |
| `arrays` | `json` or `join` (an array of maps stays JSON) | `json` |
| `separator` | what stands between the values of a joined array | `, ` |
| `key` | `_id` (the document ID) or `_path` (unique also in a collection group) | `_id` |

Every record has `_id`, `_path` (below the database's documents, `articles/a-1`), `_create_time` and `_update_time`.
The typed values:

| Firestore value | Field value |
|---|---|
| `stringValue`, `timestampValue` | the text; a timestamp in ISO 8601 as Firestore gives it (`2026-02-01T09:00:00.250Z`) |
| `integerValue` | a number, or its text beyond PHP's integers |
| `doubleValue`, `booleanValue`, `nullValue` | as they are (`NaN` and `Infinity` as text) |
| `mapValue` | flattened to dotted names (`author.name`); an empty map is `{}` |
| `arrayValue` | JSON, or a joined list (option `arrays`) |
| `referenceValue` | the path of the document it points to (`authors/au-1`) |
| `geoPointValue` | `latitude,longitude` (`48.137,11.575`) |
| `bytesValue` | base64 |

**Only reading is possible.** The source builds only `documents:runQuery` (POST, a structured query: `from`, `where`,
`orderBy`, `select`, `limit`, `offset`, `startAt`) and the list of a collection (GET); `commit`, `write`, `batchWrite`,
`delete` and transactions are never built. The `where` of the import is checked before it is sent: only
`fieldFilter`, `compositeFilter` (`AND`, `OR`) and `unaryFilter` with their operators and typed values. Give the
service account the role **`roles/datastore.viewer`** only.

**Paging.** A query pages with a cursor: the import's order plus the document name, and `startAt` the values of the
last document of a page (or with `offset`, slower for large collections); a list pages with `pageToken`.

**Test connection.** The token and one document; it shows the fields, or the masked error.

**Example:** `share/examples/firestore-articles.ndjson` is what a run reads from a collection of articles.

### BigQuery query (`bigquery`)

Group: NoSQL databases. Origin: `remote`. The rows of a GoogleSQL (standard SQL) query, run through the BigQuery REST
API v2 (`jobs.query` with `useLegacySql=false`): one record is one row.

| Option | Values | Default |
|---|---|---|
| `project` | the project that runs (and pays for) the query job | required |
| `credentials_secret` | the **name** of the secret with the service account's JSON key | required |
| `location` | the location of the datasets (`EU`, `US`, `europe-west3`) | BigQuery finds it |
| `query` | one `SELECT` or `WITH`, e.g. ``SELECT order_id, total FROM `example-project.shop.orders` `` | required |
| `maximum_bytes_billed` | a query that would scan more fails; `0`: `[GoogleCloudSourceSettings] MaximumBytesBilled` (1 GiB) | `0` |
| `page_size` | rows per request (`maxResults`) | `1000` |
| `limit` | the most rows; `0`: all | `0` |
| `key` | the column that names a row; empty: the position | |

**Fields.** By the schema of the result:

| BigQuery type | Field value |
|---|---|
| `INT64`, `NUMERIC`, `BIGNUMERIC` | the text (`9007199254740993`, `12.50`), never a float |
| `FLOAT64` | a number (`NaN`, `Infinity` as text) |
| `BOOL` | `true`/`false` |
| `TIMESTAMP` | ISO 8601 in UTC (`2026-04-02T08:30:00.25Z`) |
| `DATE`, `DATETIME`, `TIME`, `STRING`, `GEOGRAPHY`, `JSON` | the text BigQuery gives |
| `BYTES` | base64 |
| `RECORD` (`STRUCT`) | flattened to dotted names (`customer.name`) |
| a `REPEATED` column | JSON (`["green","tea"]`, `[{"sku":"tea-1","qty":"2"}]`) |

**Only reading is possible.** The query must be a single `SELECT` or `WITH`. Outside string literals, comments and
quoted names, the source refuses DML (`INSERT`, `UPDATE`, `DELETE`, `MERGE`, `TRUNCATE`), DDL (`CREATE`, `ALTER`,
`DROP`, `UNDROP`), scripting (`DECLARE`, `SET`, `BEGIN`, `LOOP`, `WHILE`, `EXECUTE IMMEDIATE`, `COMMIT` ...),
`EXPORT DATA`, `LOAD DATA`, `CALL`, `GRANT`, `REVOKE`, `ASSERT`, `EXTERNAL_QUERY` and a second statement after a
semicolon. A raw string with a backslash before a quote is refused rather than guessed. The token's scope is
`https://www.googleapis.com/auth/bigquery`, because `bigquery.readonly` cannot run a query job: what the account may do
comes from its roles. Give the service account **`roles/bigquery.jobUser`** on the project that runs the job and
**`roles/bigquery.dataViewer`** on the datasets it reads, nothing more.

**Costs.** Every query carries `maximumBytesBilled`, so a query that would scan more fails instead of costing more.
*Test connection* is a **dry run** (free): it shows the columns and the bytes the query would process, and fails when
they exceed the limit.

**Paging.** When the job is not finished at once the source asks for its result again (`getQueryResults`), then pages
with `pageToken`.

**Example:** `share/examples/bigquery-orders.ndjson` is what a run reads from a query of orders.

**What the host needs (both).** PHP's https stream with `allow_url_fopen=On` and the extension `openssl`, which also
signs the token request, in the web server's PHP and in the job queue's workers.

## Firebase Realtime Database (`firebase_rtdb`)

Group: NoSQL databases. Origin: `remote`. The children of a path of a Firebase Realtime Database, read through its REST
API: one record is one child, `_key` with its value; nested objects are flattened into dotted names (`author.name`),
arrays are JSON or joined, and a child that is a plain value gives `_key` and `value`. A path that holds a plain value
is one record.

| Option | Values | Default |
|---|---|---|
| `database_url` | `https://<name>.firebaseio.com` or `https://<name>.<region>.firebasedatabase.app` | required |
| `path` | the node whose children are the records: `articles`, `catalog/products` | the root |
| `auth` | `service_account` (a Google access token), `database_secret` (legacy) or `none` (public read rules) | `service_account` |
| `credentials_secret` | with a service account: the **name** of the secret with its JSON key | |
| `database_secret` | with a database secret: the **name** of the secret that holds it | |
| `shallow` | read the keys first (`shallow=true`), then the children a page of keys at a time: for a big tree | no |
| `page_size` | children per request | `500` |
| `limit` | the most children; `0`: all | `0` |
| `arrays` | `json` or `join` | `json` |
| `separator` | what stands between the values of a joined array | `, ` |
| `key` | the key field | `_key` |

**Only reading is possible.** The source sends only `GET <path>.json` with the parameters `orderBy`, `limitToFirst`,
`startAt`, `endAt`, `shallow` and the authentication; `PUT`, `PATCH`, `POST` and `DELETE` are never built. A service
account's token asks for the scopes `firebase.database` and `userinfo.email`; give the account the role
**`roles/firebasedatabase.viewer`** only. A legacy database secret can read and write everything: prefer a service
account.

**Hosts.** The address must be a Firebase database; another host (the emulator, `http://localhost:9000/?ns=<name>`)
only when it is listed in `[GoogleCloudSourceSettings] AllowedHosts[]`.

**Paging.** In key order (`orderBy="$key"`: keys that are 32-bit integers first, by value, then the others by text):
`limitToFirst`, and `startAt` the last key of the page before. With `shallow`, a first request asks for the keys only,
then each page reads its keys with `startAt` and `endAt`, so no request downloads the whole tree.

**Test connection.** The number of children (a shallow request) and the first child's fields, or the masked error.

**What the host needs.** PHP's https stream with `allow_url_fopen=On` and `openssl`.

**Example:** `share/examples/firebase-articles.ndjson` is what a run reads from `/articles`.

## Cassandra or ScyllaDB query (`cassandra`)

Group: NoSQL databases. Origin: `remote`. The rows of a CQL `SELECT` against Apache Cassandra or ScyllaDB: one record
is one row. Once per run the source pages through the result and writes the rows into the run's directory.

| Option | Values | Default |
|---|---|---|
| `hosts` | contact points, comma separated, each with an optional `:port`: `cassandra1.example.org, cassandra2.example.org:9142`; the first that answers is used | required |
| `port` | the port of a contact point without one | `9042` |
| `tls` | encrypt the connection (the certificate is verified) | no |
| `user` | the role; empty: no authentication | |
| `password_secret` | the **name** of a secret, never the password | |
| `keyspace` | the keyspace; empty: the query names its tables with the keyspace (`shop.products`) | |
| `query` | one `SELECT`, e.g. `SELECT sku, title, price FROM products WHERE category = 'tea'` | required |
| `consistency` | `LOCAL_ONE`, `ONE`, `LOCAL_QUORUM`, `QUORUM`, `ALL` | `LOCAL_ONE` |
| `page_size` | rows per page | `500` |
| `limit` | the most rows; `0`: all | `0` |
| `collections` | lists and sets as `json` or a joined list (`join`) | `json` |
| `separator` | what stands between the values of a joined list | `, ` |
| `key` | the column that names a row; empty: the position | |

**Fields.** `ascii`, `text`, `varchar` as text; `int`, `bigint`, `smallint`, `tinyint`, `counter` as numbers;
`boolean`; `double`, `float` as numbers; `decimal` and `varint` as text (`123.45`); `uuid`, `timeuuid` as
`8-4-4-4-12` hex digits; `timestamp` as ISO 8601 in UTC with milliseconds when it has them; `date` as `YYYY-MM-DD`;
`time` as `HH:MM:SS.nnnnnnnnn`; `inet` as the address; `blob` (and types the built-in client does not know, such as
`duration`) as base64; `list`, `set` and `tuple` as JSON or joined; `map` as JSON (`{"room":"Hall 2"}`); a
user-defined type flattened to dotted names (`venue.city`).

**Only reading is possible.** The query must be a single `SELECT`. Outside string literals (`'...'`, `$$...$$`),
comments and quoted names, `INSERT`, `UPDATE`, `DELETE`, `BATCH`, `APPLY`, `BEGIN`, `TRUNCATE`, `CREATE`, `ALTER`,
`DROP`, `GRANT`, `REVOKE`, `USE` and `USING`, and a second statement after a semicolon are refused; each driver refuses
again whatever does not start with `SELECT`. The keyspace is an option, not a `USE` in the query. Give the import a
role that may only read: `GRANT SELECT ON KEYSPACE shop TO reader`.

**Secrets and hosts.** The password is the name of a secret and masked in every message. A contact point in a private
or reserved network is refused unless `AllowPrivateHosts=enabled` or it is listed in `[CassandraSourceSettings]
AllowedHosts[]`. `[CassandraSourceSettings] Timeout` limits connecting and each answer.

**Paging.** The page size of the import and the paging state of each page, until the server gives none; at most
`[CassandraSourceSettings] MaxPages` pages per run.

**Test connection.** Connects, reads one row and shows its fields, or the masked error.

**Drivers.** The PHP extension `cassandra` of DataStax where it is installed (it needs the DataStax C/C++ driver;
there are no packages for current PHP versions); without it, a small client of the extension speaks the CQL native
protocol v4 over a socket (driver `socket`, with `openssl` for TLS): `STARTUP`, `AUTHENTICATE` with the
`PasswordAuthenticator`, `QUERY` with page size and paging state, and the decoding of the types above. It refuses a
frame larger than `[CassandraSourceSettings] MaxFrameMegabytes`. `[CassandraSourceSettings] Client` chooses: `auto`
(the default), `extension` or `socket`. The built-in client is tested against a scripted server that sends the frames
of the protocol specification; it has not yet been run against a live Cassandra or ScyllaDB server.

**Example:** `share/examples/cassandra-events.ndjson` is what a run reads from a table of events.

## Query languages of other databases over HTTP (`neo4j`, `arangodb`, `clickhouse`, `snowflake`, `influxdb`)

These five sources send one query in the database's own language and write its rows into the run's directory, one
JSON object per line. They have in common:

- **Read-only, checked before anything is sent.** Each query is checked by a lexer of its language: string literals,
  quoted names and comments are taken out as the server reads them (escapes, doubled quotes, heredocs), so a keyword
  inside a text does not count and a statement hidden behind a quote or a comment is found. What writes, administers,
  runs code or reaches outside the database is refused at any depth, and a refused query sends nothing. Where the
  server has a read-only mode, the source uses it besides (Neo4j `access-mode: READ` and Bolt mode `r`, ClickHouse
  `readonly`). Give the import's user only the rights to read what it imports in any case.
- **Secrets by name.** A password, token or private key is the **name** of a secret (see *Secrets* above), never the
  value; messages show it as `[secret <name>]`, also when a server quotes it in an error.
- **Hosts.** A server in a private or reserved network (intranet, loopback) is refused unless
  `[SourceSettings] AllowPrivateHosts=enabled` or the host is listed in the source's own `AllowedHosts[]`
  (`[Neo4jSourceSettings]`, `[ArangoDbSourceSettings]`, `[ClickHouseSourceSettings]`, `[SnowflakeSourceSettings]`,
  `[InfluxDbSourceSettings]`). `MaxPages` in the same section limits the requests of one run.
- **HTTP** goes through PHP's `http` and `https` streams (`allow_url_fopen=On` and the extension `openssl`, in the web
  server's PHP and in the PHP of the job queue's workers; the catalogue and the source step list them): certificates
  verified, answers limited to `[PlatformSettings] MaxResponseMegabytes`, the time limit `[ImportSettings]
  StreamTimeout`, no credentials after a redirect to another host. curl is not needed.
- **Nested values** (a map, an object, a tuple) become dotted field names (`author.name`); a list of plain values stays
  a list, a list of objects is numbered (`items.0.sku`).
- **Test connection** runs the query for its first row and shows the server, its version where it tells it, and the
  fields, or the error with the server's reason, masked. Nothing is saved.

### Neo4j and Memgraph (`neo4j`)

Group: NoSQL databases. Origin: `remote`. The rows of a Cypher query. A node is its properties with `_labels` (a list)
and `_element_id`; a relationship its properties with `_type`, `_element_id`, `_start` and `_end`; a path the JSON of
its nodes and relationships in order; temporal values ISO 8601 (`2026-03-02`, `2026-03-02T10:00:00.5+01:00`,
`P1M2DT30S`); a point `x,y` (or `x,y,z`); integers larger than PHP's stay text; byte arrays `base64:...`. A query that
returns a single node or relationship (`RETURN a`) gives its fields directly; with several columns each is under its
name (`a.title`, `a._labels`, `author`).

| Option | Values | Default |
|---|---|---|
| `server` | `neo4j`, `memgraph` (Bolt only) | `neo4j` |
| `address` | `https://graph.example.org:7473` (HTTP) or `bolt://`, `bolt+s://`, `neo4j://`, `neo4j+s://` with host and port (Bolt); no path, no credentials | required |
| `api` | `auto` (by the address), `tx` (HTTP transactional endpoint), `query` (Query API), `bolt` | `auto` |
| `database` | the database; never `system` | HTTP `neo4j`, Bolt the default database |
| `user`, `password_secret` | the user and the **name** of the secret with the password (empty user: no authentication) | |
| `query` | a reading Cypher query | required |
| `parameters` | a JSON object of the query's `$parameters` | |
| `page_size` | rows per request (HTTP `LIMIT`, Bolt `PULL n`) | `1000` |
| `paging` | `auto`, `none` | `auto` |
| `key` | the field that names a row (`id`, `_element_id`) | the position |

The three ways to the server:

- **`tx`**: Neo4j's HTTP transactional endpoint, `POST /db/<database>/tx/commit` with the header
  `access-mode: READ`, the row format with the graph format for labels and types (Neo4j 4 and 5).
- **`query`**: the Query API, `POST /db/<database>/query/v2` with `"accessMode": "READ"` and typed JSON (Neo4j 5.19
  and later, Aura).
- **`bolt`**: the Bolt protocol, with a small client of the source's own over a socket (Bolt 4.0 to 4.4: `HELLO`,
  `RUN` with mode `r`, `PULL` with `n`, `DISCARD`, `GOODBYE`; nothing else is ever sent). `neo4j://` is read like
  `bolt://` (a direct connection, no routing table); `+s` uses TLS with the certificate verified (for a self-signed
  certificate install it as a trusted CA). **Memgraph has no HTTP API**: it is read only over Bolt
  (`bolt://memgraph.example.org:7687`), and the source refuses an `http` address for it.

**Read-only.** Outside strings and comments the query may not contain `CREATE`, `MERGE`, `SET`, `DELETE`, `DETACH`,
`REMOVE`, `DROP`, `FOREACH`, `LOAD` (`LOAD CSV`), `IN TRANSACTIONS` (`CALL { ... } IN TRANSACTIONS`), the
administration commands (`ALTER`, `GRANT`, `DENY`, `REVOKE`, `RENAME`, `START`, `STOP`, `TERMINATE`, `ENABLE`) and
Memgraph's (`ANALYZE`, `FREE`, `STORAGE`, `REPLICATION`, `REGISTER`, `IMPORT`), nor a second statement. `USE` is
allowed with the name of a database, never `system`. Procedures and functions: `dbms.*` is refused, `db.*` only for
the reading ones (`db.labels`, `db.relationshipTypes`, `db.propertyKeys`, `db.schema.*`, `db.indexes`,
`db.constraints`, `db.info`, `db.ping`, the full-text and vector queries), and any other name (`apoc.*`, `gds.*`,
Memgraph modules such as `migrate.*`, `mg.*`) is refused when a part of it writes, loads or runs code: `create`,
`merge`, `set...`, `delete`, `remove`, `load`, `import`, `export`, `periodic`, `trigger`, `refactor`, `cypher`, `do`,
`lock`, `atomic`, `write`, `mutate`, `drop`, `project`, `config`, `uuid`, `ttl`, `custom`, `systemdb`, `migrate` and
the connectors (`bolt`, `jdbc`, `mongo`, `redis`, `es`, `kafka` ...). A quoted name (`` `apoc`.`create`.node ``) is
checked as the server reads it. Give the import a user with the `reader` role (Neo4j) or read-only privileges.

**Paging.** Over HTTP the query gets `SKIP $sqliimport_skip LIMIT $sqliimport_limit` appended when it ends in a
`RETURN` without its own `SKIP` or `LIMIT` and has no `UNION`; give it an `ORDER BY` for a stable order. Otherwise
(or with `paging` `none`) it is sent once as it is. Over Bolt the query runs once and the rows come with `PULL n`, page
by page, from the same result.

**What the host needs:** for HTTP the streams above; for Bolt `stream_socket_client` (part of PHP) and for `+s` the
extension `openssl`. No Neo4j driver library is needed. `[Neo4jSourceSettings] Timeout` is the Bolt time limit.

**Example:** `share/examples/neo4j-articles.ndjson` is a snapshot of three article nodes as the source writes it for
`MATCH (a:Article) RETURN a ORDER BY a.id`. With parameters:

```cypher
MATCH (a:Article)-[:WRITTEN_BY]->(p:Person)
WHERE a.status = $status
RETURN a.id AS id, a.title AS title, p.name AS author
ORDER BY a.id
```

### ArangoDB (`arangodb`)

Group: NoSQL databases. Origin: `remote`. The results of an AQL query through the HTTP cursor API. A document is
flattened with `_key`, `_id` and `_rev` (and `_from`, `_to` of an edge) first; a result that is not an object (a
number, a string, a list) becomes the field `value`.

| Option | Values | Default |
|---|---|---|
| `base_url` | the server or coordinator, `https://arango.example.org:8529` | required |
| `database` | the database name | `_system` |
| `auth` | `basic` (user and password), `bearer` (a JWT), `none` | `basic` |
| `user`, `password_secret` | with `basic`: the user and the **name** of the secret with the password | |
| `token_secret` | with `bearer`: the **name** of the secret with the JWT | |
| `query` | a reading AQL query | required |
| `bind_vars` | a JSON object of the query's `@parameters` | |
| `page_size` | results per batch (`batchSize`) | `1000` |
| `stream` | a streaming cursor (`options.stream`), which keeps the server's memory low | yes |
| `key` | the field that names a result | `_key` |

**Read-only.** Outside strings, quoted names and comments the query may not contain `INSERT`, `UPDATE`, `REPLACE`,
`REMOVE`, `UPSERT`, the functions that call code or wait (`CALL`, `APPLY`, `SLEEP`, `V8`) or user functions
(`NAMESPACE::NAME()`), nor a `;`. Names in forward ticks are refused (use backticks). Give the user read-only access to
the database (`ro`).

**Paging:** `POST /_db/<database>/_api/cursor` with `batchSize`, then the next batch with
`POST /_api/cursor/<id>` (ArangoDB 3.7 and later; `PUT` for older servers) while `hasMore`. A cursor left open by an
early stop (the editor's sample) is deleted. **Test connection** also reads the version (`GET /_api/version`).

**Example:** `share/examples/arangodb-articles.ndjson`, for

```aql
FOR a IN articles
  FILTER a.status == @status
  SORT a.published_at DESC
  RETURN a
```

### ClickHouse (`clickhouse`)

Group: NoSQL databases. Origin: `remote`. The rows of a `SELECT` through the HTTP interface (port 8123, or 8443 with
TLS) in the format `JSONEachRow`. Types are as ClickHouse writes them in JSON: `Int64`, `UInt64` and larger integers
as text (no rounding), other numbers as numbers, `Date` and `DateTime` as `2026-05-01` and `2026-05-01 10:00:00`, a
named `Tuple`, a `Map` or a `JSON` column flattened to dotted names, an `Array` as a list.

| Option | Values | Default |
|---|---|---|
| `base_url` | the HTTP interface, `https://clickhouse.example.org:8443` | required |
| `database` | the database | `default` |
| `user`, `password_secret` | the user (sent as `X-ClickHouse-User`) and the **name** of the secret with the password (`X-ClickHouse-Key`); never in the address | `default` |
| `query` | a single `SELECT` or `WITH ... SELECT`, without `FORMAT` and `SETTINGS` | required |
| `readonly` | `2` (read-only, the source may send its limits), `1` (read-only, no settings are sent) | `2` |
| `page_size` | rows per request | `10000` |
| `paging` | `auto` (`LIMIT` and `OFFSET` around the query), `none` | `auto` |
| `key` | the column that names a row | the position |

**Read-only.** Every request carries `readonly=2` (or `1`), so the server itself refuses writes, DDL and a change of
`readonly`. Before, the query must start with `SELECT` or `WITH` and may not contain, outside strings, quoted names
(both with backslash escapes and doubled quotes) and comments: `INSERT`, `ALTER`, `CREATE`, `DROP`, `TRUNCATE`,
`OPTIMIZE`, `SYSTEM`, `KILL`, `SET`, `GRANT`, `REVOKE`, `RENAME`, `ATTACH`, `DETACH`, `EXCHANGE`, `DELETE`, `UPDATE`,
`INTO OUTFILE`, `USE`, `BACKUP`, `RESTORE`, the clauses `FORMAT` and `SETTINGS` (the functions `format()` are fine),
a second statement, and the table functions that reach outside the server: `url`, `s3`, `gcs`, `azureBlobStorage`,
`file`, `remote`, `remoteSecure`, `cluster`, `mysql`, `postgresql`, `mongodb`, `redis`, `sqlite`, `hdfs`, `jdbc`,
`odbc`, `executable`, `input`, `deltaLake`, `hudi`, `iceberg` and their `...Cluster` forms.
`[ClickHouseSourceSettings] AllowedTableFunctions[]` may allow one (for example `s3` for a data lake the import
should read). Heredocs (`$tag$...$tag$`) are read as ClickHouse reads them.

**Paging:** `SELECT * FROM ( <query> ) LIMIT <page_size> OFFSET <n>` until a page is short; give the query an
`ORDER BY` for a stable order. With `readonly=2` each request also sets `max_execution_time`
(`[ClickHouseSourceSettings] MaxExecutionTime`, 60) and, when `MaxResultRows` is set, `max_result_rows` with
`result_overflow_mode=throw`. **Test connection** reads `version()` and the first row.

**Example:** `share/examples/clickhouse-page-views.ndjson`, for

```sql
SELECT path, sum(views) AS views FROM page_views WHERE day >= today() - 7 GROUP BY path ORDER BY views DESC, path
```

### Snowflake (`snowflake`)

Group: NoSQL databases. Origin: `remote`. The rows of a query through the SQL API v2 (`POST /api/v2/statements`) with
**key-pair authentication**: the source signs a JWT with the user's RSA private key (RS256, PHP's openssl); its
issuer is `<ACCOUNT>.<USER>.SHA256:<fingerprint>`, the fingerprint computed from the public key exactly as
`DESC USER` shows it in `RSA_PUBLIC_KEY_FP`. Register the public key with `ALTER USER ... SET RSA_PUBLIC_KEY=...`.

| Option | Values | Default |
|---|---|---|
| `account` | the account identifier: `orgname-accountname`, or a locator with its region (`xy12345.eu-central-1`) | required |
| `base_url` | another address (a private link) | `https://<account>.snowflakecomputing.com` |
| `user` | the user | required |
| `private_key_secret` | the **name** of the secret with the PEM private key (`-----BEGIN PRIVATE KEY-----` or `ENCRYPTED PRIVATE KEY`; a key on one line with `\n` works too) | required |
| `passphrase_secret` | for an encrypted key: the **name** of the secret with its passphrase | |
| `warehouse`, `database`, `schema`, `role` | the context of the statement | the user's defaults |
| `query` | a single `SELECT`, `WITH ... SELECT`, `SHOW` or `DESCRIBE` | required |
| `key` | the column that names a row (Snowflake names columns in upper case) | the position |

**Read-only as far as the source can see.** Outside strings (`'...'` with backslash escapes and doubled quotes,
`$$...$$`), quoted names and comments (`--`, `//`, `/* */`) the query may not contain `INSERT`, `UPDATE`, `DELETE`,
`MERGE`, `CREATE`, `ALTER`, `DROP`, `TRUNCATE`, `GRANT`, `REVOKE`, `COPY`, `PUT`, `REMOVE`, `CALL`, `EXECUTE`
(`EXECUTE IMMEDIATE`), `USE`, `SET`, `UNSET`, `UNDROP`, `BEGIN`, `COMMIT`, `ROLLBACK`, `INTO`, nor a `SYSTEM$`
function or a second statement, and the request allows one statement (`MULTI_STATEMENT_COUNT` 1). **Snowflake has no
read-only session**: give the user a role with `SELECT` on what the import reads and nothing else (no `OWNERSHIP`, no
`USAGE` on external functions or procedures).

**Type mapping** (by `resultSetMetaData.rowType`; the API sends every value as text): `FIXED` (`NUMBER`, `DECIMAL`)
stays text, so no number is rounded; `REAL` a float; `BOOLEAN` true or false; `DATE` `2026-03-02`; `TIME`
`10:00:00.5`; `TIMESTAMP_NTZ` `2026-03-02T10:00:00.123`, `TIMESTAMP_LTZ` in UTC with `Z`, `TIMESTAMP_TZ` with its
offset (`2026-03-02T11:00:00+01:00`); `VARIANT`, `OBJECT` and `ARRAY` decoded from JSON, an object flattened under
the column's name (`ADDRESS.city`); `BINARY` hex text; `NULL` null.

**Paging:** the partitions of the result (`resultSetMetaData.partitionInfo`): the first comes with the answer, the
others with `GET /api/v2/statements/<handle>?partition=<n>`. A statement that is still running (`202`) is asked for
again every second until it ends or `[SnowflakeSourceSettings] StatementTimeout` (300 s, also sent as the statement's
`timeout`) passes. **What the host needs:** the `https` stream and the extension `openssl` (it signs the token too).

**Example:** `share/examples/snowflake-orders.ndjson`, three orders as the source writes them.

### InfluxDB (`influxdb`)

Group: NoSQL databases. Origin: `remote`. The rows of a **Flux** query (InfluxDB 2, InfluxDB Cloud; `POST
/api/v2/query`, the answer in annotated CSV) or of an **InfluxQL** query (InfluxDB 1 and the 1.x compatibility API of
InfluxDB 2; `GET /query`, the answer in JSON). One record is one row: `_time` in ISO 8601 (RFC 3339), the tags and
fields as columns, `_measurement`, and with Flux `_start`, `_stop`, and `_field` and `_value` unless the query pivots.

| Option | Values | Default |
|---|---|---|
| `base_url` | the server, `https://influx.example.org:8086`, or the InfluxDB Cloud address | required |
| `language` | `flux`, `influxql` | `flux` |
| `org` | Flux: the organization name or ID | |
| `database`, `retention_policy` | InfluxQL: the database (or the bucket mapped to it) and the retention policy | |
| `auth` | `token` (`Authorization: Token ...`), `basic` (user and password), `none` | `token` |
| `token_secret` | the **name** of the secret with the token | |
| `user`, `password_secret` | with `basic` | |
| `query` | Flux, or a single InfluxQL `SELECT` or `SHOW` | required |
| `pivot` | Flux: append `pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")`, one row per time with a column per field | no |
| `page_size` | InfluxQL: points per series and request | `10000` |
| `key` | the column that names a row | the position |

**Read-only.** Flux: only these packages may be imported: `array`, `strings`, `date`, `math`, `regexp`, `json`,
`dict`, `types`, `join`, `interpolate`, `timezone`, `runtime`, `system`, `influxdata/influxdb`,
`influxdata/influxdb/schema`, `influxdata/influxdb/v1`, `influxdata/influxdb/sample`, `sampledata`, `experimental`
and its reading parts (`aggregate`, `array`, `date/boundaries`, `geo`, `json`, `oee`, `polyline`, `query`, `record`,
`table`, `dynamic`). So `http`, `sql`, `csv` from a file or address, `influxdata/influxdb/secrets`, `socket`, `mqtt`,
`kafka`, the notification endpoints (`slack`, `pagerduty`, `opsgenie`, `telegram`, `sensu`, `discord`, `teams`,
`victorops`, `zenoss`, `servicenow`, `bigpanda`, `alerta`, `pushbullet`, `webexteams`) and every `contrib/...`
package are refused. Anywhere in the query `to()`, `wideTo()`, any `X.to()` (`experimental.to`, also through an alias
or a variable) and `sleep()` are refused, and so is string interpolation (`"${...}"`). InfluxQL: a single `SELECT`
or `SHOW`, without `INTO` and without `DROP`, `DELETE`, `CREATE`, `ALTER`, `GRANT`, `REVOKE`, `KILL` or
`SET PASSWORD`. Give the token read access to the bucket only (an InfluxDB 2 read authorization).

**Type mapping:** Flux by the `#datatype` annotation: `long` an integer, `unsignedLong` an integer (text when larger
than PHP's), `double` a float, `boolean` true or false, `dateTime:RFC3339` text as sent, the rest text; an empty
value null (or the table's `#default`); the columns `result` and `table` are left out; an error table stops the run
with its message. InfluxQL as the JSON answer gives it (`time` as `_time`, RFC 3339).

**Paging:** InfluxQL `SELECT` without its own `LIMIT`, `OFFSET`, `SLIMIT` or `SOFFSET` gets `LIMIT <page_size>
OFFSET <n>` appended (per series, as InfluxQL counts them) until no series fills a page. Flux comes in one answer:
narrow it with `range()` and `filter()` (answers are limited to `[PlatformSettings] MaxResponseMegabytes`); the
editor's sample appends `limit(n: ...)`. **Test connection** shows the server's version from `X-Influxdb-Version`.

**Example:** `share/examples/influxdb-climate.ndjson`, three pivoted readings, for

```flux
from(bucket: "sensors")
  |> range(start: -1d)
  |> filter(fn: (r) => r._measurement == "climate")
```

## LDAP directory (`ldap`)

Group: data. Origin: `remote`. The entries of an LDAP directory (OpenLDAP, Active Directory, 389 Directory Server and
the like): one record is one entry of a search, its fields the DN (`dn`) and the entry's attributes by name. Once per
run the source binds, searches and writes the entries into the run's directory, one per line; the editor's sample
reads the first ones. A directory of people holds **personal data**: import only the attributes the site needs.

| Option | Values | Default |
|---|---|---|
| `uri` | `ldaps://host[:port]` (TLS from the start, port 636) or `ldap://host[:port]` (port 389, with StartTLS); a bare host is `ldaps://` | |
| `starttls` | StartTLS on an `ldap://` connection | yes |
| `allow_plain` | allow an unencrypted connection, only to `localhost` (`127.0.0.1`, `::1`) | no |
| `bind_dn` | the user to bind as (`cn=reader,ou=Services,dc=example,dc=org`; Active Directory also `reader@example.org`); empty: anonymous | |
| `password` | the **name** of a secret, never the password; needed with a bind DN | |
| `base_dn` | where the search starts, e.g. `ou=Staff,dc=example,dc=org` | |
| `filter` | an LDAP filter (RFC 4515) | `(objectClass=*)` |
| `attributes` | the attributes to read, one per line; empty: all user attributes. A listed attribute is in every record (`null` when an entry has none) | |
| `scope` | `sub` (the base and everything below it), `one` (the entries directly below the base), `base` (the base entry only) | `sub` |
| `page_size` | entries per page of the paged results control (RFC 2696); `0`: no paging, the server's size limit applies (often 1000 in Active Directory) | `500` |
| `size_limit` | the most entries; `0`: all | `0` |
| `binary` | binary attributes as `base64:<data>`, or `skip` to leave them out | `base64` |
| `multi_valued` | an attribute with several values as a list (`array`; one value stays a value) or joined (`join`) | `array` |
| `separator` | the separator of joined values | `; ` |
| `key` | the key field: `dn`, or an attribute such as `uid`, `entryUUID`, `objectGUID_text` | `dn` |

**Read-only.** The source connects, binds and searches; it has no way to add, change or delete an entry. Give it a
directory user that may only read the part of the tree the import needs.

**Encrypted.** The connection is `ldaps://` or `ldap://` with StartTLS, and the server's certificate is checked (the
CA certificates of the system's OpenLDAP client, `TLS_CACERT` in `ldap.conf`). An unencrypted connection is refused,
except to `localhost` when the import ticks `allow_plain`. A directory server in a private or reserved network (the
usual intranet directory, and `localhost`) is refused unless `[SourceSettings] AllowPrivateHosts=enabled` or the
administrator lists it in `[LdapSourceSettings] AllowedHosts[]`. `[LdapSourceSettings] Timeout` (30 seconds) limits
connecting and each search. Referrals are not followed.

**The filter is checked** against the grammar of RFC 4515 before anything is sent: equality, presence (`(mail=*)`),
substrings (`(cn=Sam*)`), `~=`, `>=`, `<=`, extensible matches (`(memberOf:1.2.840.113556.1.4.1941:=cn=Press,...)`),
`&`, `|` and `!` nested at most 32 deep. Special characters in values are written as escapes: `\28` for `(`, `\29`
for `)`, `\2a` for `*`, `\5c` for `\`. A filter without outer brackets gets them. Attribute names are checked too.

**Values.** An attribute with one value is that value, one with several a list (`telephoneNumber.0`) or, with
`multi_valued=join`, the values joined. Binary attributes (`jpegPhoto`, `thumbnailPhoto`, `objectGUID`, `objectSid`,
certificates, an attribute with the option `;binary`, and every value that is not UTF-8 text) are written as
`base64:<data>` (the mapping can make an image of a photo) or left out with `binary=skip`. `objectGUID` of Active
Directory is also given as text in `objectGUID_text` (`12345678-9abc-...`), a key that stays when a user is renamed.
Password attributes (`userPassword`, `unicodePwd`, `sambaNTPassword`, the LAPS passwords ...) are never read into a
record, even when the server returns them.

**Paging.** With a page size, every request carries the paged results control and the cookie of the page before,
until the server has no more; the source needs PHP 7.3 or later for it. Without paging, a server stops at its own
size limit.

**Test connection.** On the source step, *Test connection* connects, binds, searches for the first entry and shows its
fields, or the error (a password never appears: it is masked). Nothing is saved; it needs the same permission as
saving the import. Next to the button, *What this server can connect with* says whether this PHP has the extension
`ldap` and, after the first run, whether the job queue's workers have it.

**What the host needs:** the PHP extension `ldap` in the PHP of the job queue's workers, which read the runs (RHEL:
`php-ldap`; Debian: `php-ldap`; Plesk: the `ldap` extension of the PHP version), and in the web server's PHP for the
editor's test and sample.

**Example:** `share/examples/ldap-staff.ndjson` is the snapshot of three invented entries (what a run writes). The
catalogue also shows a configuration for an OpenLDAP staff directory and one for the users of an Active Directory
group over StartTLS, keyed by `objectGUID_text`.

## Pages of a site by its sitemap (`sitemap`) and the web page scraper (`webpage`)

Group: platforms. These two formats read public web pages themselves (the origin `remote`): once per run they crawl
the site and write a snapshot of what they read (one JSON object per line in the run's directory), which every chunk
of the run then reads. The import editor's sample and preview read only the first pages (`[RunSettings]
DryRunRows`).

**The site's terms and its robots.txt apply. Read only public pages you may use.** The crawl is polite and safe by
design:

- **robots.txt is obeyed.** It is read once per host; the group of the user agent (or `*`) decides with its `Allow`
  and `Disallow` rules (the longest matching rule wins, `Allow` on a tie; `*` and a final `$` in patterns) and its
  `Crawl-delay`. A robots.txt that answers 4xx allows everything, one that cannot be read (5xx, no answer) nothing.
  A page that is not allowed is never requested: it becomes a failed row of the run with the reason, and the run
  ends *completed with errors*.
- **Requests are spaced.** Between two requests to a host the crawl waits the import's `delay` (1 second by default),
  at least `[CrawlSettings] MinDelay`, and longer when robots.txt asks for a longer `Crawl-delay`.
- **Limits.** A run reads at most `max_pages` pages, never more than `[CrawlSettings] MaxPages` (2000). Every page is
  read with the checks and limits of an address source: no private or reserved networks unless `[SourceSettings]
  AllowPrivateHosts`, the time limit `[ImportSettings] StreamTimeout` and the size limit `[SourceSettings]
  MaxSourceMegabytes`. Redirects are followed by the crawl itself (three at most), each target checked again.
- **Nothing is sent but the request.** No cookies, no credentials (an address with a user name or password is
  refused), and the user agent of `[CrawlSettings] UserAgent` (by default `7x SQLI Import (Exponential; +<SiteURL>)`,
  so the owner of a site can see who reads it).
- **No JavaScript is run.** A page is parsed as HTML text (DOMDocument without network access, in UTF-8 by the
  charset of the answer or of the page); scripts, styles, frames and event attributes are removed before anything is
  taken from it. A page whose content is built by JavaScript has little to read.

**Picks** (option `picks`, one per line) name the fields to take from a page: `field=selector`. A selector is CSS, a
practical subset: a tag or `*`, `#id`, `.class`, `[attr]`, `[attr=value]` (and `~=`, `^=`, `$=`, `*=`, `|=`), the
combinators descendant (a space), `>`, `+`, `~` and `,`, and `:first-child`, `:last-child`, `:only-child`,
`:nth-child(n)`, `:first-of-type`, `:last-of-type`. At its end `::attr(name)` or `@name` takes an attribute,
`::html` the inner HTML and `::text` the text (the default). A selector starting with `xpath:` is XPath. A pick
that finds several elements gives a list, one that finds nothing an empty text. Relative addresses of `href`, `src`
and similar attributes are made absolute. A pick named `body` keeps the inner HTML of what it finds (for a rich text
field) and adds `body_text` with its text.

```
title=h1
image=meta[property=og:image]::attr(content)
body=article .content
author=xpath://span[@itemprop='author']
```

### `sitemap`

The pages listed by a sitemap (`sitemap.xml`, gzip-compressed or not, or a text file of addresses), followed
through a sitemap index; for a site's root (`https://example.org/`) or its `robots.txt`, the `Sitemap:` lines of
robots.txt (else `/sitemap.xml`). Only pages and sitemaps of the sitemap's own site are read (its host, with or without `www.`). One record per page:
`url` (the key), `url_hash` (the SHA-1 of the address, for the remote ID), `lastmod`, `priority` and `changefreq`
from the sitemap, `status` (HTTP), `title` (of `<title>`), `description` (the meta description), `canonical`,
`language` (of `<html lang>`), and every pick (a pick `title` replaces the page title).

| Option | Values | Default |
|---|---|---|
| `sitemap` | the sitemap, a sitemap index or a site's root (required) | |
| `include` | patterns of the addresses to read, one per line: a glob (`https://example.org/blog/*`, `/blog/*` for the path and query) or `regex:<expression>` | every page |
| `exclude` | patterns of the addresses never to read (they win over `include`) | none |
| `picks` | `field=selector` lines | none |
| `max_pages` | the most pages one run reads | `200` |
| `delay` | seconds between two requests | `1` |
| `key` | the key field | `url` |

Preset: an article (`title` from the title, `intro` from the description and `body` from the pick `body`, both as
rich text), matched by the remote ID `page-{url_hash}`. Examples: `sitemap.xml` (a sitemap of four pages; a local
sitemap file lists its pages without reading them) and `sitemap-crawl.ndjson` (the snapshot of a crawl: three pages
read, one refused by robots.txt).

### `webpage`

One page or a list of addresses (option `urls`). With an `item` selector, every element it finds on a page (a
product of a list, an event of a calendar) is a record of its own and the picks are relative to it (`@data-sku` alone
takes an attribute of the item itself); without it, a page is one record. A `next` selector (`a[rel=next]`) names the
link to the next page, which is followed while `max_pages` allows. One record per item: `id` (the key: the address,
with `#<position>` for an item), `url`, `url_hash`, `page` (1 for the first page of an address), `position` (from 1
over all pages of an address), `status` and the picks.

| Option | Values | Default |
|---|---|---|
| `urls` | addresses, one per line (required) | |
| `item` | the selector of the repeating element | the page is one record |
| `next` | the selector of the link to the next page (its `href`) | no next page |
| `picks` | `field=selector` lines | none |
| `max_pages` | the most pages one run reads, over all addresses | `20` |
| `delay` | seconds between two requests | `1` |
| `key` | the key field | `id` |

No preset. Example: `page-products.html` (a list of three products, read as if it were
`https://example.org/products/`, with the item `.product`).

## Sites that are read by the source (`drupal`, `exponential_site`)

These formats need no file: the source reads the other site's API itself. Once per run it writes what it read as a
snapshot (`source.ndjson`) into the run's directory, which every chunk of the run then reads. The dry run of the
editor reads only as many records as it shows. Their rules:

- Every address is checked like a URL source: `http` and `https` only, and no private or reserved networks unless
  `[SourceSettings] AllowPrivateHosts=enabled`. Redirects are followed at most three times, every address is checked
  again, and the `Authorization` header is never sent to another host. A "next page" link of an answer is followed
  only on the same scheme, host and port.
- **Passwords, client secrets and tokens are never stored in the import.** Their options take the *name* of a secret:
  `Secrets[<name>]` of `[SecretSettings]` in the site's own settings, or `env:<VARIABLE>` of the PHP process. Error
  messages show the name of a secret, never its value.
- `[PlatformSettings]` limits a run: `MaxPages` (requests per run, 200), `MaxItems` (nodes per run of another
  Exponential site, 5000) and `MaxResponseMegabytes` (one answer, 20). The time limit is
  `[ImportSettings] StreamTimeout`.

### Drupal (`drupal`)

Group: platforms. Content of a Drupal site through the JSON:API of Drupal core (Drupal 8.7 and later). The source
requests `<site>/jsonapi/<entity type>/<bundle>` and follows `links.next` page by page.

| Option | Meaning | Default |
|---|---|---|
| `base_url` | Required. The address of the site, e.g. `https://www.example.org` | |
| `resource` | The entity type and bundle: `node--article`, `node--page`, `taxonomy_term--tags`, `media--image` | `node--article` |
| `page_size` | Resources per request (JSON:API answers at most 50) | `50` |
| `include` | The relationships whose resources come along, comma separated | `field_image,uid,field_tags` |
| `filter` | JSON:API query parameters as they are, e.g. `filter[status]=1&sort=-created` | empty |
| `auth` | `none` (public content), `basic` (`user` and `password_secret`, the module basic_auth) or `bearer` (`token_secret`, an OAuth access token) | `none` |
| `key` | the key field | `id` (the UUID) |

A record has the fields `id` (the UUID), `type`, `internal_id` (and `nid`, `vid`, `tid` ... by their name),
`title` (the `name` of a term or a media), `body` (the processed HTML) with `body_value`, `body_summary` and
`body_format`, `intro` (the summary, else the first paragraph of the body), `created`, `changed`, `status`, `path`
(the alias), `url` (the address of the alias), `langcode` and every other attribute by its name. A relationship
becomes `<name>` (the related resource with the attributes of the included one; a list for a multiple field),
`<name>_id`, `<name>_url` for files and media (a media is followed to its file) and `<name>_names` for lists of
terms. `image_url` and `image_alt` name the first image (`field_image`, then a media), `author` the user of `uid`
and `tags` the names of `field_tags`.

The preset maps a resource to an `article`: title, intro and body as rich text, and, where the class has them,
publish date (`created`), image (`image_url`, downloaded; an object relation gets an image object) and tags. The
remote ID is `drupal-<UUID>`, so a later run updates the objects.

### Another Exponential site (`exponential_site`)

Group: platforms. Content of another Exponential site through its REST API v1 (the extension ezprestapi, the API of
the mobile applications): the source requests
`<site>/api/ezp/v1/content/node/<node>/list/offset/<n>/limit/<m>?ResponseGroups=Metadata,Fields` page by page, and
the children of every node down to the depth.

| Option | Meaning | Default |
|---|---|---|
| `base_url` | Required. The address of the other site | |
| `parent_node` | The node whose children are read | `2` |
| `depth` | `1`: the children; `2`: their children too, up to 10 | `1` |
| `class` | Only the objects of this class (the others are still descended into) | every class |
| `translation` | The language to read, e.g. `ger-DE` | the site's |
| `page_size` | Nodes per request (at most 100) | `50` |
| `max_items` | The most nodes of a run (never more than `[PlatformSettings] MaxItems`) | `1000` |
| `auth` | `none`, `basic` (`user`, `password_secret`), `bearer` (`token_secret`: an OAuth access token or a personal API key), `oauth_password` (`client_id`, `client_secret`, `user`, `password_secret`) or `oauth_refresh` (`client_id`, `client_secret`, `refresh_token_secret`) | `none` |
| `token_url` | The token end point of OAuth2 (on the same site) | `<site>/api/oauth/token` |
| `key` | the key field | `remote_id` |

An Exponential site's token end point gives tokens for the grants `authorization_code` and `refresh_token`: use a
refresh token (`oauth_refresh`) or a personal API key (`bearer`) there; the password grant is for sites whose OAuth
server offers it. The REST API of a site normally needs authentication (`[Authentication] RequireAuthentication` of
its `rest.ini`).

A record has the fields `node_id`, `object_id`, `remote_id`, `node_remote_id`, `class_identifier`, `name`,
`published`, `modified` (timestamps), `url` (the full address), `url_alias`, `parent_node_id`, `depth`, and every
attribute in `fields.<identifier>` with its datatype in `field_types.<identifier>`. The REST API gives an attribute
in its text form: rich text as plain text, an image as its address on the site, a relation as object IDs of the site.

The mapping is made from the first record when the import is saved: its class, every attribute by identifier (rich
text through `richtext`, images through `image`; relations are left to you), matched by the remote ID, so the copy
follows the original at every run.

## Hosted services read by the source (`airtable`, `notion`, `supabase`, `baserow`, `salesforce`, `hubspot`)

These formats read a hosted service's API with a token: once per run the source requests the records page by page
and writes them as a snapshot (`source.ndjson`) into the run's directory, which every chunk of the run then reads;
the editor's dry run reads only as many records as it shows. They share these rules:

- **Read-only.** The sources only read: Airtable, Supabase and Baserow through `GET` requests, Notion through its
  database query and `GET` requests, Salesforce through its sign-in and `GET` queries, HubSpot through `GET` lists and
  its search (a `POST` that reads). Give the token as little as it needs (see each format).
- **The token is never stored in the import.** The option takes the *name* of a secret: `Secrets[<name>]` of
  `[SecretSettings]` in the site's own settings, or `env:<VARIABLE>` of the PHP process. Messages show
  `[secret <name>]`, never the token, also when the service quotes it in an error.
- **Rate limits.** The source keeps the least time between two requests the service allows, and when the service
  answers `429 Too Many Requests` it waits (the `Retry-After` of the answer, else the service's own penalty) and tries
  again, up to four times; a gateway error (502, 503, 504) is tried again the same way.
- `[PlatformSettings] MaxPages` limits the requests of a run of Airtable and Notion (200); Supabase, Baserow,
  Salesforce and HubSpot have `MaxPages` in their own sections (`[SupabaseSourceSettings]` ..., 1000).
  `[PlatformSettings] MaxResponseMegabytes` limits one answer (20) and `[ImportSettings] StreamTimeout` the time of a
  request.
- **Test connection** on the source step reads the first record and shows its fields, or the error (masked). Nothing
  is saved; it needs the same permission as saving the import. *What this server can connect with* names what the
  host needs.
- **What the host needs:** the PHP extension `openssl` and `allow_url_fopen=On` (both part of the usual PHP
  packages), and that the server may reach the service's address over HTTPS.
- The data may contain **personal data** (names and e-mail addresses of collaborators and people). Map only what the
  site needs.

### Airtable (`airtable`)

Group: platforms. The records of an Airtable table through the Airtable Web API (v0): the source requests
`https://api.airtable.com/v0/<base>/<table>` and follows the `offset` of every answer.

| Option | Meaning | Default |
|---|---|---|
| `base_id` | Required. The ID of the base, `app...` (in the address of the base) | |
| `table` | Required. The name of the table or its ID (`tbl...`; it stays right when the table is renamed) | |
| `view` | The name or ID of a view: its filters and order apply | empty |
| `formula` | `filterByFormula`: an Airtable formula a record must make true, e.g. `{Status} = "Published"` | empty |
| `fields` | The fields to read, one per line; empty: all. A listed field is in every record (`null` when it is empty) | empty |
| `page_size` | Records per request (at most 100) | `100` |
| `token` | Required. The **name** of the secret holding a personal access token | |
| `key` | the key field | `id` (the record ID, `rec...`) |

Create the personal access token on airtable.com (Developer hub) with the scope `data.records:read` only and access
to the bases the import reads. Airtable allows five requests a second per base: the source keeps a fifth of a second
between two requests and waits 30 seconds (Airtable's penalty) or the `Retry-After` after a `429`.

A record has `id`, `created_time` and every field by its name (Airtable leaves empty fields out of an answer; list
them in `fields` to have them in every record). Values: text, numbers, checkboxes, dates (ISO 8601), single and
multiple selects (a list) as Airtable gives them; **attachments** as the list of their addresses (`<field>`, which the
mapping can download, e.g. with the transform `image`) and of their file names (`<field>_names`); **linked records**
as the list of their record IDs; a **collaborator** as the name (`<field>`) and the e-mail address (`<field>_email`),
several as two lists; a button as its address, a barcode as its text, an AI text as its value. A field called like
one of the record's own fields (`id`) is written as `field_<name>`. Airtable's addresses of attachments expire after
a few hours, so the files are downloaded by the run that reads them.

**Example:** `share/examples/airtable-events.ndjson` is the snapshot of three invented records of a table `Events`;
the catalogue also shows a configuration for the published events of a view.

### Notion (`notion`)

Group: platforms. The pages of a Notion database through the Notion API (version `2022-06-28`): the source queries
`POST https://api.notion.com/v1/databases/<id>/query` with the import's filter and sorts, and follows `next_cursor`
while `has_more`.

| Option | Meaning | Default |
|---|---|---|
| `database_id` | Required. The ID of the database (32 characters, with or without dashes) or its link (Share, Copy link) | |
| `filter` | A filter object of the Notion API, e.g. `{"property": "Status", "status": {"equals": "Published"}}` | none |
| `sorts` | A list of sorts, e.g. `[{"property": "Date", "direction": "descending"}]` | none |
| `content` | `none`, or the page content as `markdown` or `html` in the field `content` | `none` |
| `page_size` | Pages per request (at most 100) | `100` |
| `token` | Required. The **name** of the secret holding the token of an internal integration | |
| `key` | the key field | `id` (the ID of the page) |

Create an internal integration (notion.so, My integrations) with the capability *Read content* only, and share the
database with it (the database's menu, Connections). Notion allows about three requests a second: the source keeps a
third of a second between two requests and follows the `Retry-After` of a `429`.

A record has `id`, `url`, `created_time`, `last_edited_time`, `archived`, `title` (the text of the title property) and
every property by its name, as a plain value:

| Property type | Value |
|---|---|
| `title`, `rich_text` | the plain text |
| `number`, `checkbox`, `url`, `email`, `phone_number` | as it is |
| `select`, `status` | the option's name |
| `multi_select` | the names joined by `, ` |
| `date` | the start in ISO 8601 (`<name>`) and the end (`<name>_end`, `null` without one) |
| `people`, `created_by`, `last_edited_by` | the names (a list for `people`) |
| `files` | the list of addresses (`<name>`) and of file names (`<name>_names`); Notion's own file addresses expire after an hour |
| `relation` | the list of the related pages' IDs |
| `formula`, `rollup` | the value (a rollup of a list: the list of values) |
| `created_time`, `last_edited_time`, `unique_id`, `verification` | as text (`unique_id` as `PREFIX-42`) |

With `content` set, the source requests the blocks of every page (one more request per page, paged by their own
cursor) and writes the **first level** as Markdown or HTML: paragraphs, headings, bulleted, numbered and to-do lists,
quotes and callouts, code, dividers, images and the links of bookmarks, embeds, videos and files; bold, italic,
strikethrough, code and links of the text are kept. A toggle gives its own text; nested blocks (the children of a
list item or a toggle), tables and child databases are not read.

**Example:** `share/examples/notion-articles.ndjson` is the snapshot of two invented pages with their content as
Markdown; the catalogue also shows a configuration for the published articles, newest first, with the content as
HTML.

### Supabase or PostgREST (`supabase`)

Group: platforms. The rows of a table or view of a Supabase project through its REST API, or of any PostgREST server:
the source requests `GET <base>/<table>?select=...&<filters>&order=...` page by page. For an address on
`supabase.co` the path `/rest/v1` is added; any other server is used with the path given.

| Option | Meaning | Default |
|---|---|---|
| `base_url` | Required. The project address (`https://<ref>.supabase.co`) or the PostgREST server's address | |
| `table` | Required. The table or view, a name (never `rpc`, functions are not read) | |
| `schema` | A schema the API exposes besides `public` (header `Accept-Profile`) | empty |
| `select` | Columns and embedded resources: `id,title,author:authors(name,email)`; aliases, casts (`::text`), JSON paths (`->>`) and aggregates allowed | `*` |
| `filters` | One per line as a PostgREST parameter: `status=eq.published`, `tags=cs.{tea}`, `or=(stock.gt.0,preorder.is.true)` | none |
| `order` | `published_at.desc,id` (also `.nullsfirst`, `.nullslast`); an order keeps the pages stable | empty |
| `paging` | `range` (header `Range: 0-999`, `Range-Unit: items`) or `offset` (`limit` and `offset` parameters) | `range` |
| `count` | `none`, `exact`, `planned` or `estimated` (header `Prefer: count=...`): the total from `Content-Range` | `none` |
| `page_size` | Rows per request (Supabase answers at most its *max rows*, 1000 by default) | `1000` |
| `limit` | The most rows; `0`: all | `0` |
| `api_key` | The **name** of the secret with the anon key, sent as `apikey` and as the bearer token; empty for a server without authentication | empty |
| `bearer` | The **name** of a secret with a JWT of a read-only role, sent as the bearer token instead of the key | empty |
| `arrays`, `separator` | Arrays as JSON or as a joined list | `json`, `, ` |
| `key` | the key column | `id` |

**Read-only guarantee:** only `GET` requests to the table; `select`, every filter and `order` are checked before
anything is sent. A filter may use only `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `ilike`, `is`, `in`, `cs`,
`cd` (with `not.` and the modifiers `(any)` and `(all)`), also inside `and=(...)`, `or=(...)` and `not.` at any depth;
full text search, ranges and the parameters `select`, `order`, `limit`, `offset`, `columns` and `on_conflict` are
refused as filters. **Use the anon key with a read-only role and row level security** (policies that allow `SELECT`
only on what the import reads); a service role key passes row level security and should not be used. A self-hosted
server in a private network only from `[SupabaseSourceSettings] AllowedHosts[]`.

**Paging:** a page ends the reading when it is shorter than the page size, when the total of `Content-Range` is
reached or when the server answers `416` (the range starts after the last row). **Values:** as PostgREST gives them:
text, numbers, booleans, timestamps in ISO 8601 as PostgreSQL writes them (`2026-09-01T08:00:00+00:00`), `json` and
`jsonb` columns and embedded resources as dotted fields (`author.name`), arrays and to-many embeddings as JSON or a
joined list, `bytea` as `\x...` hex text. **Test connection** reads one row and, with a count, the total.

**Example:** `share/examples/supabase-articles.ndjson` (three invented articles with their author).

### Baserow (`baserow`)

Group: platforms. The rows of a Baserow table through its REST API, on baserow.io or self-hosted: the source requests
`GET <base>/api/database/rows/table/<id>/?user_field_names=true&size=...&page=...` while the answer has a `next`.

| Option | Meaning | Default |
|---|---|---|
| `base_url` | `https://api.baserow.io` or the address of a self-hosted Baserow | `https://api.baserow.io` |
| `table_id` | Required. The ID of the table | |
| `view_id` | A view whose filters and order apply; `0`: none | `0` |
| `filters` | One per line as `<field>__<type>=<value>`, sent as `filter__<field>__<type>`: `Status__single_select_equal=3`, `Price__higher_than=10` | none |
| `filter_type` | `AND` or `OR` | `AND` |
| `search` | Only rows that contain this text | empty |
| `order_by` | Fields by name, `-` for descending: `-Date,Name` | empty |
| `include` | The fields to read, one per line; empty: all | empty |
| `page_size` | Rows per request (at most 200) | `100` |
| `separator` | Between the values of link rows, multiple selects and collaborators | `, ` |
| `token` | Required. The **name** of the secret with a database token | |
| `key` | the key field | `id` (the row ID) |

Create the database token (Settings, Database tokens) with the permission **read** only, on the workspace and tables
the import needs; it is sent as `Authorization: Token ...`. The source sends only `GET` requests to the list of rows.
A self-hosted Baserow in a private network only from `[BaserowSourceSettings] AllowedHosts[]`.

**Values:** link rows as their values joined (`<field>`) and their row IDs (`<field>_ids`, comma separated); files as
the list of their addresses (`<field>`, the mapping can download them) and of their names (`<field>_names`); a single
select as its value, multiple selects and collaborators as the names joined; a user (created by, last modified by) as
the name; dates in ISO 8601 as Baserow gives them; booleans as `true` and `false`; numbers as Baserow gives them
(decimals as text, so no digit is lost); an empty list as `[]`. Test connection reads one row and the count of rows.

**Example:** `share/examples/baserow-events.ndjson` (three invented events).

### Salesforce (`salesforce`)

Group: platforms. The records of a SOQL query through the Salesforce REST API: the source signs in with a connected
app, runs `GET <instance>/services/data/<version>/query?q=...` (or `queryAll`, which also gives deleted and archived
records) and follows `nextRecordsUrl`.

| Option | Meaning | Default |
|---|---|---|
| `login_url` | `https://login.salesforce.com`, `https://test.salesforce.com` (sandbox) or the My Domain address | `https://login.salesforce.com` |
| `auth` | `jwt` (JWT bearer flow) or `refresh_token` | `jwt` |
| `client_id` | Required. The consumer key of the connected app | |
| `user` | JWT: the user name of the integration user | |
| `private_key` | JWT: the **name** of the secret with the PEM of the private key of the connected app's certificate | |
| `client_secret` | Refresh token: the **name** of the secret with the consumer secret | |
| `refresh_token` | Refresh token: the **name** of the secret with the refresh token | |
| `query` | Required. A single SOQL `SELECT` | |
| `query_all` | Also deleted and archived records (`queryAll`) | no |
| `api_version` | The REST API version | `v61.0` |
| `batch_size` | Records per answer (`Sforce-Query-Options: batchSize`, 200 to 2000) | `2000` |
| `key` | the key field | `Id` |

**Signing in:** the JWT bearer flow signs a token (`iss` the consumer key, `sub` the user, `aud` the login server,
three minutes) with RS256 (`openssl_sign`) and needs the certificate uploaded to the connected app and the user
pre-authorized; the refresh token flow needs the consumer secret and a refresh token of the user. The instance address
comes from the token answer; it and the login address must be `https` addresses on a domain of
`[SalesforceSourceSettings] AllowedDomains[]` (`salesforce.com`, `force.com`, `cloudforce.com`, `salesforce.mil`) or a
host of `AllowedHosts[]`, so no token is sent anywhere else; `nextRecordsUrl` must be a query locator.

**Read-only guarantee:** the query must be one statement beginning with `SELECT`; `FOR UPDATE` (it locks records),
`FOR VIEW` and `FOR REFERENCE` (they write the last viewed dates), `UPDATE TRACKING` and `UPDATE VIEWSTAT` (they write
statistics) and the words of DML are refused outside strings. Give the integration user a profile or permission set
with *API Enabled* and read access to the objects the import needs only.

**Values:** relationship fields flattened (`Owner.Name`, `Owner.Manager.Name`), `attributes` dropped at every level;
address and geolocation fields flattened (`BillingAddress.city`, `Location__c.latitude`); a child relationship (a
subquery) as the JSON list of its records; dates as given (`2026-10-22`); date-times in ISO 8601 with `Z` for UTC
(Salesforce writes `+0000`) or `+02:00`; currency, percent and double values (numbers with a fraction) as a number in
text, never with an exponent; whole numbers as numbers; booleans as `true` and `false`. Records of people (contacts,
leads, users) are **personal data**. Test connection signs in, reads one record and the `totalSize`.

**Example:** `share/examples/salesforce-accounts.ndjson` (three invented accounts).

### HubSpot (`hubspot`)

Group: platforms. The objects of a HubSpot CRM object type through the CRM API v3 at `https://api.hubapi.com`:
`contacts`, `companies`, `deals`, `tickets`, `products`, `line_items` or a custom object (`2-1234567`, `p_<name>`).

| Option | Meaning | Default |
|---|---|---|
| `object_type` | Required. The object type | |
| `mode` | `list` (`GET /crm/v3/objects/<type>`, every object) or `search` (`POST /crm/v3/objects/<type>/search`) | `list` |
| `properties` | The properties to read, one per line (internal names); empty: HubSpot's default properties | empty |
| `associations` | List: the associated object types whose IDs to read (`companies`, `contacts`) | empty |
| `filter_groups` | Search: `[{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]` | empty |
| `search` | Search: the text HubSpot looks for in the default text properties | empty |
| `sort` | Search: one property and the direction, `createdate DESCENDING` | empty |
| `archived` | List: read the archived objects instead | no |
| `date_properties` | Properties whose values in milliseconds become ISO 8601, besides `createdate`, `closedate` and HubSpot's other dates | empty |
| `page_size` | Objects per request (a list at most 100, a search 200) | `100` |
| `token` | Required. The **name** of the secret with the access token of a private app | |
| `key` | the key field | `id` |

Create a private app with the **read** scopes of the objects the import needs only (`crm.objects.contacts.read`,
`crm.objects.companies.read` ...). **Read-only guarantee:** the only requests are the list (`GET`) and the search
(a `POST` to `.../search`, which reads); the search body is built by the source from `filter_groups`, which is checked
first: a list of at most 5 groups (or `{"filterGroups": [...]}`), each `{"filters": [...]}`, each filter only
`propertyName`, `operator` (`EQ`, `NEQ`, `LT`, `LTE`, `GT`, `GTE`, `BETWEEN`, `IN`, `NOT_IN`, `HAS_PROPERTY`,
`NOT_HAS_PROPERTY`, `CONTAINS_TOKEN`, `NOT_CONTAINS_TOKEN`), `value`, `highValue` and `values`, at most 18 filters.

**Rate limits and paging:** private apps may make 100 requests in 10 seconds (the source keeps a tenth of a second
between two requests), the search about 5 a second (a fifth of a second); after a `429` it waits the `Retry-After`. Both
modes follow `paging.next.after`; a search reads at most 10,000 objects (HubSpot's limit; the source says so).

**Values:** a record has `id`, `created_at`, `updated_at`, `archived`, every property by name (every asked one, `null`
when empty) and `associations.<type>` as the list of IDs. HubSpot gives every property as text: numbers stay text;
dates and date-times stay ISO 8601, and a date property given in milliseconds since 1970 becomes ISO 8601 in UTC; the
values of a multiple checkbox stay joined with `;`. **Contacts hold personal data** (names, e-mail addresses, phone
numbers): map only what the site needs. Test connection reads one object (and the total of a search).

**Example:** `share/examples/hubspot-companies.ndjson` (three invented companies with their contacts' IDs).

## Exports of other systems (`joomla`, `ezpkg`)

### Joomla articles (`joomla`)

Group: platforms. File name extensions: `csv`, `xml`. Articles of a Joomla site (com_content) exported as CSV, with
the columns of the articles table (`id`, `title`, `alias`, `introtext`, `fulltext`, `catid` or `category_title`,
`state`, `created`, `publish_up`, `language`, `metadesc`, `metakey`, `images` ...), or as XML: J2XML (articles as
`<content>`, categories as `<category>`) or an export with one `<article>` per article and the fields as child
elements. The variant is detected from the first bytes of the file.

| Option | Values | Default |
|---|---|---|
| `variant` | `auto`, `csv`, `xml` | `auto` |
| `site_url` | The address of the Joomla site, so the images (`images/...`) become addresses | empty |
| `status` | `published` (state 1), `not_trashed` (published, unpublished, archived) or `all` | `not_trashed` |
| `key` | the key field | `id` |

A record has the fields `id`, `title`, `alias`, `intro` (the intro text), `fulltext`, `body` (intro and full text
joined, as Joomla shows the article), `category` (its title: the column `category_title`, the categories of the XML,
else the last part of the path), `category_id`, `category_path`, `state`, `published`, `featured`, `created`,
`modified`, `publish_up`, `date` (`publish_up`, else `created`), `language` (empty for `*`, every language),
`metadesc`, `keywords` (the meta keywords as a list), `author`, `hits`, and the images of the `images` JSON:
`image_intro`, `image_fulltext`, `image_url` (the full text image, else the intro image, as an address: a path of
the site needs `site_url`) and their alternative texts (`image_alt`). Joomla 4's suffix of an image path
(`#joomlaImage://...`) is cut off.

The preset maps an article to an `article` (title, intro, body, and, where the class has them, publish date, image
and keywords as tags), with the remote ID `joomla-<id>`.

### Exponential packages (`ezpkg`)

Group: platforms. File name extensions: `ezpkg`, `tgz`. A content package of an Exponential site (Setup, Packages,
the export of a package with content objects). Every content object is a record. The archive is read with PharData
and never extracted: an archive with an entry outside it (`..`, an absolute path) is refused, and the XML is read
without the network. Only packages in the XML format can be read (a package exported as JSON says so).

| Option | Meaning | Default |
|---|---|---|
| `class` | Only the objects of this class | every object |
| `language` | The translation to read, e.g. `ger-DE`; objects without it are left out | the initial language of each object |
| `key` | the key field | `remote_id` |

A record has the fields `remote_id`, `object_id` (in the package), `name`, `class_identifier`, `class_remote_id`,
`language`, `languages`, `published`, `modified` (timestamps), `section_id`, `node_remote_id`,
`parent_node_remote_id` (of the main node), `parent_node_remote_ids`, and every attribute in `fields.<identifier>`
with its datatype in `field_types.<identifier>`. The values have the form the datatype's `fromString()` takes: rich
text as its XML, dates as timestamps, a link as `address|text`, relations as lists of remote IDs, tags as lists of
keywords. An image or file is named `source:<path in the package>` (with the alternative text of an image in
`fields.<identifier>_alt`): the transforms `image` and `file` take it out of the run's own copy of the package.

The mapping is made from the package when the import is saved: the class of its objects (with the option `class`, or
when every object has the same class), every attribute by identifier whose datatype is read in its `fromString()`
form (relations are left to you, they name objects of the package), and the remote ID as the match, so importing the
package again updates the objects instead of creating new ones.

## The catalogue of formats

**Imports, Catalogue** lists the formats by group (data, NoSQL databases, content, platforms, commerce, media, other). Each card says
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
| `examples()` | a list of `array( 'label', 'file' )` (a file in sqliimport's `share/examples`, with `'options'` if it needs some), `array( 'label', 'file', 'extension' => 'ezodf' )` (a file in that extension's `share/samples`), `array( 'label', 'path' => 'extension/<name>/share/...' )` (relative to the installation, only below an extension's `share/`) or `array( 'label', 'url' )` |
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
