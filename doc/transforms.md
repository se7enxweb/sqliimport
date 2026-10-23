# Transforms: every built-in transform with an example

A transform changes a value on its way from the source to an attribute: it trims a name, reads a date, turns HTML
into rich text or downloads a picture. In the mapping step of the import editor, each line of the grid has a
**Transforms** box that takes a chain of transforms as one line of text, for example `trim | upper`. This page shows
every built-in transform with a real input and the output it gives.

German: [ger-de/transforms.md](ger-de/transforms.md).

## Table of contents

- [How a chain is written](#how-a-chain-is-written)
- [Text](#text): `trim`, `lower`, `upper`, `title`, `strip_tags`, `prefix`, `suffix`, `replace`, `regex_replace`,
  `truncate`, `default`, `slug`
- [Values from a table](#values-from-a-table): `map`
- [Lists](#lists): `split`, `join`, `first`
- [Several fields](#several-fields): `concat`, `template`
- [Numbers and yes or no](#numbers-and-yes-or-no): `number`, `int`, `bool`
- [Dates](#dates): `date`, `date_format`
- [Rich text](#rich-text): `richtext`
- [Related objects](#related-objects): `lookup`
- [Files and images](#files-and-images): `image`, `file`, `media_object`
- [Datatypes that need a special text](#datatypes-that-need-a-special-text): prices, keywords, checkboxes, dates
- [Your own transform](#your-own-transform)

## How a chain is written

- The steps of a chain are separated by a vertical bar: `trim | upper` first trims, then makes upper case.
- A step without arguments is just its name: `trim`.
- Arguments follow in parentheses as `name=value`, separated by commas: `truncate(length=80)`.
- A value that contains a comma, a parenthesis, a vertical bar, a quote, braces or brackets, or that is empty, is
  written as JSON: `suffix(text=" g")`, `replace(search=",", replace=.)`, `map(table={"A":"Austria"})`.
- A transform that cannot do its work (a date that is not a date, a lookup that finds nothing) makes the record
  fail with its message. The run page lists the message on the record's row.

The table below the grid of the mapping step, **The transforms**, lists every transform with its arguments, so you
do not have to remember them. The rules of the one-line text are in [mappings.md](mappings.md#the-one-line-text-of-a-chain).

Every example on this page was run on a real installation: the text in the first column was read by the editor's
parser and applied to the input in the second column. The record the examples use has the fields
`title=Dune`, `year=1965`, `first_name=Frank`, `last_name=Herbert`.

## Text

| Transform text | Input | Output |
|---|---|---|
| `trim` | `  Assam Breakfast  ` | `Assam Breakfast` |
| `lower` | `Assam BREAKFAST` | `assam breakfast` |
| `upper` | `tea-101` | `TEA-101` |
| `title` | `darjeeling first flush` | `Darjeeling First Flush` |
| `strip_tags` | `<p>Light &amp; <b>floral</b></p>` | `Light & floral` |
| `prefix(text=tea-)` | `101` | `tea-101` |
| `suffix(text=" g")` | `250` | `250 g` |
| `replace(search=",", replace=.)` | `12,50` | `12.50` |
| `regex_replace(pattern="/[^0-9]/", replace="")` | `ISBN 978-3-16-148410-0` | `9783161484100` |
| `truncate(length=12)` | `A strong, malty black tea` | `A strong, ma` |
| `default(value="No description yet")` | (empty) | `No description yet` |
| `slug` | `Earl Grey: Blue Flower!` | `earl-grey-blue-flower` |

When to use them:

- **`trim`** on almost every text column of a spreadsheet: cells often end with a space nobody sees.
- **`strip_tags`** for a feed title that carries HTML, before it goes into a plain text attribute.
- **`regex_replace`** takes a PHP regular expression with its delimiters (`/.../`). An empty `replace` removes what
  matches.
- **`default`** is the same as the **Default** column of the grid, but inside a chain: `default(value=Tea) | upper`
  gives `TEA` for an empty cell.
- **`slug`** builds an identifier from a name, for example for a remote ID part or a URL-friendly attribute.

## Values from a table

| Transform text | Input | Output |
|---|---|---|
| `map(table={"D":"Germany","A":"Austria"}, otherwise=Other)` | `A` | `Austria` |
| the same | `CH` | `Other` |
| `map(table={"1":"In stock","0":"Sold out"})` | `5` | `5` (no `otherwise`: the value is kept) |

`map` is the transform for codes: country codes, status numbers, category keys of another system.

## Lists

| Transform text | Input | Output |
|---|---|---|
| `split(separator=;)` | `green;black;white` | the list `green`, `black`, `white` |
| `split(separator=;) \| join(separator=" / ")` | `green;black;white` | `green / black / white` |
| `split(separator=;) \| first` | `green;black;white` | `green` |

A list that reaches an attribute is written with `|` between the items, which is the text form of object relation
lists and similar datatypes. For a keyword attribute (`ezkeyword`), join the list with commas:
`split(separator=;) | join(separator=", ")`.

## Several fields

| Transform text | Input | Output |
|---|---|---|
| `concat(paths=["last_name"])` | `Frank` (the line's source field is `first_name`) | `Frank Herbert` |
| `template(text="{title} ({year})")` | (any) | `Dune (1965)` |

- **`concat`** joins the current value with the fields named in `paths`, with a space (or `separator`).
- **`template`** builds a text from any fields of the record in braces. `{value}` is the current value of the chain,
  so `number | template(text="{value}|1|1")` writes a price the way `ezprice` reads it (see below).

## Numbers and yes or no

| Transform text | Input | Output |
|---|---|---|
| `number` | `1.234,56` | `1234.56` |
| `number(decimals=2)` | `6.9` | `6.90` |
| `int` | `41.6` | `42` |
| `bool` | `yes` | `1` |
| `bool` | `nein` | `0` |

`number` reads both the German (`1.234,56`) and the English (`1,234.56`) way of writing a number. `bool` gives `1`
for 1, true, yes, y, on, ja, oui and x (in any case), else `0`: the value a checkbox attribute (`ezboolean`) takes.

## Dates

| Transform text | Input | Output |
|---|---|---|
| `date(format=d.m.Y, timezone=Europe/Berlin)` | `12.04.2026` | `1775944800` (midnight of 12 April 2026 in Berlin) |
| `date` | `2026-04-12 08:30` | `1775982600` (any form PHP's `strtotime()` reads, in UTC) |
| `date(format=excel)` | `45231.5` | `1698840000` (1 November 2023, 12:00, a spreadsheet date) |
| `date_format(format=Y-m-d)` | `1775980800` | `2026-04-12` |

- **`date`** gives a timestamp, which is what date and date-and-time attributes (`ezdate`, `ezdatetime`) take. Give
  `format` when the source writes dates in a fixed form that `strtotime()` could misread, such as `04/05/2026`.
- **`date(format=excel)`** is for XLSX files, which store dates as days since 30 December 1899
  ([mappings.md](mappings.md#dates-from-spreadsheets)).
- **`date_format`** writes a date as text, for a text attribute or for a remote ID: `date_format(format=Y-m)`.

## Rich text

| Transform text | Input | Output |
|---|---|---|
| `richtext` | `<p>A <b>strong</b> tea.</p>` | the rich text `<paragraph>A <strong>strong</strong> tea.</paragraph>` (XML text of `ezxmltext`) |

`richtext` is needed for every rich text attribute (body, intro, description), also when the source is plain
text. Pictures in the HTML whose address is a file of the run (`run:` or `source:`, as the document and Markdown
sources give them) become image objects embedded in the text. The arguments:

| Argument | Meaning |
|---|---|
| `images` | `embed` (default) or `drop` (leave every picture out) |
| `image_parent` | the node the image objects are created below (default: the import's parent) |
| `image_size` | the image alias of the embeds: `small`, `medium` (default), `large`, `original` |

Example: `richtext(images=embed, image_size=large)`. A worked example with pictures is the Markdown folder in
[source-examples-files.md](source-examples-files.md).

## Related objects

| Transform text | Input | Output |
|---|---|---|
| `lookup(prefix=docs-tea-)` | `101` | the object ID of the object with the remote ID `docs-tea-101` |

`lookup` fills an object relation (`ezobjectrelation`, `ezobjectrelationlist`): the source gives a key of the
related object, and the import that created that object made its remote ID from the same key. Import the related
objects first (another import, or earlier records of the same source). A key that finds no object makes the record
fail with `lookup: no object with the remote ID '...'`; fix the order and use **Retry failed rows**
([runs.md](runs.md#retry-failed-rows)).

For a list of keys: `split(separator=,) | lookup(prefix=docs-tea-)` (each item is looked up).

## Files and images

| Transform | Attribute it fills | What it does |
|---|---|---|
| `image` | an image attribute (`ezimage`) | downloads the picture from the address in the field and stores it in the attribute |
| `file` | a file or media attribute (`ezbinaryfile`, `ezmedia`) | downloads the file and stores it in the attribute |
| `media_object` | an object relation to images or files | makes an image (or file) object for the address, once per file, and gives its ID |

All three take an `http(s)` address (with the same rules as an address source: no private networks unless allowed),
`source:<path>` (a file inside the run's ZIP or package) or `run:<path>` (a file a source wrote into the run's
directory). In a dry run and in the editor's preview nothing is downloaded: the address stands for the file.

`image(required=true)` makes the record fail when the picture cannot be had; by default the record is imported
without it, with a note on its row.

`media_object` is the one to use for the webshop's `product` class, whose `image` is an object relation:

```text
media_object(parent=54990, class=image, attribute=image, name=title)
```

It makes one image object per address below node 54990, named after the record's `title`, and the same address
in a later run (or in another record) reuses that object instead of making a new one. The media ZIP and the
WordPress examples in [source-examples-files.md](source-examples-files.md) show it with real files.

## Datatypes that need a special text

Some attributes read their value from a text with a fixed form. The transforms above build it:

| Datatype | Text it reads | Chain |
|---|---|---|
| Price (`ezprice`) | `amount\|VAT type\|price includes VAT`, e.g. `6.95\|1\|1` | `number \| template(text="{value}\|1\|1")` |
| Keywords (`ezkeyword`) | words separated by commas | `split(separator=;) \| join(separator=", ")` |
| Checkbox (`ezboolean`) | `1` or `0` | `bool` |
| Date, date and time (`ezdate`, `ezdatetime`) | a timestamp | `date` or `date(format=...)` |
| Object relation (list) | object IDs, separated by `\|` | `lookup(prefix=...)` or `media_object` |
| Rich text (`ezxmltext`) | the XML of the rich text | `richtext` |

A price without the `|VAT type|included` part is stored as 0: always use the template above for a price, and
change the VAT type `1` and the last `1` (`0`: the price excludes VAT) to what your shop uses. See also
[troubleshooting.md](troubleshooting.md#prices-are-0-or-every-run-updates-every-price).

## Your own transform

A site adds a transform as a kernel service tagged `sqliimport.transform`. The class implements
`expImportTransformInterface`; the developer section of [handlers.md](handlers.md#writing-your-own-transform)
shows a complete one. A mapping can only name registered transforms and never carries code.
