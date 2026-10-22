# Sources

A source type reads records from a local file. Where the file comes from is the `origin` of the import's
`source_config`. The engine turns it into a local file once per run, so every chunk of the run reads the same data.

## Where the data comes from

| `origin` | Configuration | What happens |
|---|---|---|
| `upload` | `file`: the stored name of an uploaded file | The upload (kept in the site's storage directory, `sqliimport/uploads/`) is copied into the run's directory |
| `url` | `url`: an `http` or `https` address | The address is downloaded into the run's directory, with the time limit `[ImportSettings] StreamTimeout` and the size limit `[SourceSettings] MaxSourceMegabytes` |
| `path` | `path`: a file on the server | The file is read in place, but only if it lies inside one of `[SourceSettings] AllowedPaths[]` |
| `remote` | the source's own options (a database, a mailbox) | The source reads its data itself once per run and writes it into the run's directory as JSON Lines (`sql`, `imap`) |

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

## Google Sheets (`google_sheets`)

Group: data. A spreadsheet read at every run from its link: share it so that anyone with the link can view it (or
publish it to the web) and give the link as the address. The link is turned into the sheet's CSV export, keeping the
tab of the link (`#gid=`); the first row names the columns. The options are those of CSV, with a comma and UTF-8.

## SQL database or query (`sql`)

Group: platforms. Origin: `remote`. The rows of an SQL query against a MySQL or MariaDB, PostgreSQL or SQLite
database (an intranet database, the database of another application): one record is one row, its fields the
columns of the query (name them with `AS` to get the field names you want). Once per run the source runs the query
and writes the rows into the run's directory, one JSON object per line; binary values that are not UTF-8 are written
as `base64:...`. The editor's sample and preview read the first `[RunSettings] DryRunRows` rows only.

| Option | Values | Default |
|---|---|---|
| `driver` | `mysql` (MySQL or MariaDB), `pgsql` (PostgreSQL), `sqlite` (a file on the server), `site` (this site's database) | `mysql` |
| `host`, `port` | the database server; port `0` is the default port (3306, 5432) | |
| `database` | the database name; SQLite: the path of the file | |
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
- **The connection is read-only:** MySQL and MariaDB `SET SESSION TRANSACTION READ ONLY`, PostgreSQL
  `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY`, SQLite opened read-only with `PRAGMA query_only`. Multiple
  statements and emulated prepares are off, MySQL's `LOCAL INFILE` too.
- **A time limit** of `[SqlSourceSettings] QueryTimeout` seconds is set on the server where it has one (MySQL
  `MAX_EXECUTION_TIME`, MariaDB `max_statement_time`, PostgreSQL `statement_timeout`); reading the rows stops after
  ten times as long.

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

### Secrets

A source never stores a password. Its option of the type `secret` holds the **name** of a secret, and the value is
read when a run needs it:

- `<name>`: `sqliimport.ini [SecretSettings] Secrets[<name>]=<value>`, set in the site's own settings
  (`settings/override/sqliimport.ini.append.php` or a siteaccess), which are not part of a repository;
- `env:<VARIABLE>`: an environment variable of the PHP process (the PHP-FPM pool, the worker).

**Imports, Settings** lists the names of the secrets that are set, never a value. Messages and logs show a secret
as `[secret <name>]`, also when a server quotes it in an error.

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
