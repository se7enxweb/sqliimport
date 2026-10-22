# Mappings

The handler `mapping` (class `expImportMappingHandler`) imports without PHP. A mapping says which source field
fills which attribute of a content class. The handler publishes through the content API of 1.x (`SQLIContent`,
`SQLIContentPublisher`), which publishes a new version only when a field really changed.

In the administration, the mapping step of the import editor sets every part of a mapping in a form, with a grid of
one line per attribute ([editor.md](editor.md#step-3-mapping)). This page describes what the form stores.

## A mapping

As an array (`expImportMapper`, stored by `expImportMapping::saveArray()`):

| Key | Meaning |
|---|---|
| `class` | The content class identifier |
| `language` | The language of the fields; empty: the import's target language, else the site's default |
| `match` | How a record finds its object (see below) |
| `fields` | The lines: `source` (a path in the record), `attribute`, `transforms` (a chain), `default`, `required`, `language` (empty: the mapping's) |
| `location` | Where new objects go: `parent_node_id`, `parent_from`, `parent_prefix`, `extra_parents_from` |

### Matching

| `match` | The record's object is |
|---|---|
| `array( 'type' => 'remote_id', 'pattern' => 'news-{id}' )` | The object with this remote ID. The pattern needs at least one `{field}` of the record. A remote ID longer than 100 characters is shortened with an MD5 hash. |
| `array( 'type' => 'attribute', 'attribute' => 'isbn', 'source' => 'isbn' )` | The object of the class whose attribute has the record's value |

If no object matches, a new one is created. Prefer a remote ID: it is indexed, and it travels with the content.

### Fields

- A list value (from `split`, a CSV list or a JSON array) is joined with `|`, which is the text form of object
  relation lists and similar datatypes.
- `default` is used when the source value is empty after the transforms.
- `required` makes the record fail when the value is still empty.
- A line with its own `language` writes a translation of the object.

### Location

- `parent_from` names a record field with the parent's remote ID; `parent_prefix` is put before it. If the record
  has no such value, `parent_node_id` is used.
- `extra_parents_from` names a field with more parents' remote IDs (a list, or a comma-separated text). They become
  additional locations of a new object.
- Records are processed in their order, so a parent that is imported in the same run must come before its
  children.

## Behavior

The import's `behaviour_json` decides what happens to existing objects:

| `update_policy` | Effect |
|---|---|
| `update` (default) | New objects are created; existing ones get a new version when a field changed |
| `create_only` | Existing objects are skipped |
| `update_only` | Records without an object are skipped |

An object in the trash is skipped and reported. The section (`section_id`) and owner (`owner_id`) of the target
apply to new objects.

In a dry run nothing is written. Each row says what would happen and lists the changed fields with the current and
the new value.

## Transforms

A chain is a list of steps: `array( array( 'name' => 'trim' ), array( 'name' => 'date', 'args' => array( 'format'
=> 'd.m.Y' ) ) )`. The arguments are validated. A transform that fails makes the record fail with its message. The
built-in transforms (`expImportTransforms::builtins()`):

| Name | What it does | Arguments |
|---|---|---|
| `trim` | Removes spaces at both ends | |
| `lower` | Lower case | |
| `upper` | Upper case | |
| `title` | Upper case first letter of each word | |
| `strip_tags` | Removes HTML tags and decodes entities | |
| `prefix` | Puts a text before the value | `text` (required) |
| `suffix` | Puts a text after the value | `text` (required) |
| `replace` | Replaces a text | `search` (required), `replace` |
| `regex_replace` | Replaces what a regular expression matches | `pattern` (required), `replace` |
| `truncate` | Cuts the value to a length | `length` (required, at least 1) |
| `default` | A value when the source is empty | `value` |
| `map` | Replaces a value by another from a table | `table` (required, JSON), `otherwise` (empty: keep the value) |
| `split` | Splits a text into a list | `separator` (default `,`) |
| `join` | Joins a list into a text | `separator` (default `, `) |
| `first` | The first item of a list | |
| `concat` | Joins the value with other fields of the record | `paths` (required, a list), `separator` (default a space) |
| `template` | Builds a text from fields of the record: `{title} ({year})`; `{value}` is the current value | `text` (required) |
| `number` | A number; reads `1.234,56` and `1,234.56` | `decimals` (default -1: as given) |
| `int` | A whole number (rounded) | |
| `bool` | `1` for 1, true, yes, y, on, ja, oui, x; else `0` | |
| `date` | A date or time as a timestamp (ezdate, ezdatetime) | `format` (PHP date format; `excel`: a spreadsheet date; empty: any form `strtotime()` reads), `timezone` (default `UTC`) |
| `date_format` | A date as a text in a format | `format` (required), `timezone` (default `UTC`) |
| `slug` | A URL-friendly identifier | |
| `richtext` | HTML to the rich text (ezxmltext) of the attribute | |
| `lookup` | The ID of the object with this remote ID (object relations) | `prefix` |
| `image` | Downloads an image from a URL (ezimage) | |
| `file` | Downloads a file from a URL (ezbinaryfile, ezmedia) | |

`image` and `file` follow the same address rules as a `url` source ([sources.md](sources.md)). In a dry run, and
so in the preview of the editor, they download nothing: the address stands for the file.

### Dates from spreadsheets

An Excel file stores a date as a number of days since 30 December 1899, with the time of day as a fraction
(`45231.5` is 1 November 2023, 12:00). `date(format=excel)` turns such a number into a timestamp; the time zone
argument says in which zone the date and time are meant:

```text
date(format=excel, timezone=Europe/Berlin)
```

A value that is not a number makes the record fail with "date: '...' is not a spreadsheet date". An ODS file gives
dates as ISO text (`2023-11-01`), which `date` reads without a format.

### The one-line text of a chain

The mapping grid of the editor shows and reads a chain as one line of text. The same text is what
`expImportTransforms::formatChain()` writes and `expImportTransforms::parseChain()` reads:

- the steps are separated by a vertical bar `|`;
- a step without arguments is its name: `trim`;
- the arguments follow in parentheses as `name=value`, separated by commas: `truncate(length=80)`;
- a value that contains a comma, a parenthesis, a vertical bar, a quote, braces or brackets, or that is empty, is
  written as JSON: a text in double quotes (`""` for an empty text), a table in braces, a list in brackets.

Examples:

| Text | Chain |
|---|---|
| `trim` | Removes spaces |
| `trim \| upper` | Removes spaces, then upper case |
| `date(format=d.m.Y, timezone=Europe/Berlin)` | Reads `31.12.2025` as midnight of that day in Berlin |
| `date(format=excel)` | Reads a spreadsheet date |
| `map(table={"D":"Germany","A":"Austria"}, otherwise=Other)` | Replaces country codes by names |
| `split(separator=;) \| first` | The first item of a list separated by semicolons |
| `concat(paths=["first_name","last_name"])` | Joins the value with two more fields |
| `replace(search=",", replace=.)` | Reads `12,50` as `12.50`; the comma is quoted because it would end the argument |

A text that cannot be read, such as an unknown argument form or invalid JSON, is reported on the line of its
attribute when the mapping step is saved, and the line keeps no transforms until it is fixed. The arguments are
then checked as for every chain.

A site adds its own transform as a kernel service tagged `sqliimport.transform`, implementing
`expImportTransformInterface` (`name()`, `label()`, `schema()`, `apply( $value, $args, $record )`). A mapping can
only name registered transforms; it never carries code.

## Creating an import in PHP

The import editor is the usual way to create an import ([editor.md](editor.md)). A script that sets up imports,
for example during the installation of a site, can create one in PHP and run it with
`php bin/php/ezexec.php <script> --allow-root-user`:

```php
<?php
$import = expImportDefinition::create( array(
    'identifier' => 'news_feed',
    'name' => 'News from the partner feed',
    'source_type' => 'rss',
    'source_config' => json_encode( array( 'origin' => 'url', 'url' => 'https://www.example.com/news.rss' ) ),
    'handler' => 'mapping',
    'target_json' => json_encode( array( 'parent_node_id' => 2 ) ),
    'behaviour_json' => json_encode( array( 'update_policy' => 'update' ) ),
) );
$import->store();

$mapping = expImportMapping::saveArray( array(
    'class' => 'article',
    'language' => '',
    'match' => array( 'type' => 'remote_id', 'pattern' => 'partner-news-{id}' ),
    'fields' => array(
        array( 'source' => 'title', 'attribute' => 'title', 'transforms' => array( array( 'name' => 'trim' ) ), 'required' => true ),
        array( 'source' => 'summary', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
        array( 'source' => 'content', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
        array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ) ),
    ),
    'location' => array( 'parent_node_id' => 2 ),
), $import->attribute( 'id' ) );

$import->setAttribute( 'mapping_id', $mapping->attribute( 'id' ) );
$import->store();
```

Check a mapping before you store it: `expImportMapper::validate( $mapping, expImportMappingHandler::classAttributes( 'article' ) )`
returns a list of errors (empty when the mapping is valid). Then try it with
`./console ext:sqliimport:run news_feed --dry-run --wait`.
