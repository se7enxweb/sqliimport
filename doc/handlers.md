# Handlers

Use the no-code handler `mapping` when a mapping can express the import ([mappings.md](mappings.md)). Write a
handler when it cannot: several objects per record, calculations across records, an external API per record.

## The 2.x handler interface

A handler implements `expImportHandlerInterface`:

| Method | Purpose |
|---|---|
| `static identifier()` | The id an import stores in `handler` |
| `static label()` | The name in the catalogue |
| `static schema()` | The handler's options as an `expImportOptionSchema` schema |
| `open( expImportContext $context )` | Prepares a chunk (caches, a publisher) |
| `process( expImportRecord $record, expImportContext $context )` | Processes one record and returns an `expImportRowResult` |
| `close( expImportContext $context )` | Ends a chunk |

Per chunk, the engine calls `open()` once, `process()` for each record and `close()` once, in a worker of the job
queue. An exception in `process()` fails only that record; its message becomes the row's message and the run goes
on.

The records come from the import's source, so a handler does not read files. The context gives it:

| Property | Content |
|---|---|
| `$context->import` | The import (`expImportDefinition`) |
| `$context->run` | The run (`expImportRun`) |
| `$context->options` | The handler's options, validated against `schema()` |
| `$context->target` | `parent_node_id`, `section_id`, `language`, `owner_id` |
| `$context->behaviour` | `chunk_size`, `update_policy`, `trash_policy`, `max_rows` |
| `$context->log` | The run's log (`expImportLogger`) |
| `$context->dryRun` | `true` in a dry run: publish nothing |

`$context->throwIfCancelled()` extends the job's lease and stops the record when the run was cancelled. The engine
already calls it between records; call it inside long loops of your own.

### Results

`expImportRowResult` has one factory per outcome:

```php
expImportRowResult::created( $objectID, $versionAfter );
expImportRowResult::updated( $objectID, $versionBefore, $versionAfter );
expImportRowResult::unchanged( $objectID, $version );
expImportRowResult::skipped( 'the product is discontinued', $objectID );
expImportRowResult::failed( 'the price is missing' );
```

In a dry run, fill `$result->diff` (`attribute => array( old, new )`) so the run page can show what would change.

### Option types

A schema is an array `name => definition`. Types: `string`, `text`, `int`, `float`, `bool`, `enum` (with
`values`), `path`, `url`, `node`, `class`, `list`, `json` and `secret` (a reference to a secret in the settings,
never the secret itself). Each definition can carry `label`, `help`, `required`, `default`, `min`, `max` and
`pattern`. Unknown keys are dropped.

## Writing your own source, handler or transform

When no format of the catalogue reads your data, when a mapping cannot express what an import must do, or when a
value needs a calculation of your own, an extension adds a **source**, a **handler** or a **transform**. The three
examples below belong together: a source reads a pipe-separated tea list, a transform turns a net price into the
text the price datatype `ezprice` takes, and a handler publishes each tea as a product with that price. Each class is
complete. They were run on the documentation server exactly as printed here, and the outputs below are what they
printed (see [How the examples were checked](#how-the-examples-were-checked)).

The file `teas.psv` of the examples:

```
sku|name|origin|net_price
TEA-SEN-100|Morning Sencha|Japan|6.50
TEA-HBL-100|Highland Black|India|5,90
TEA-SNE-050|Silver Needle|China|
```

Put the classes into your own extension (for example `extension/mytea/classes/`), never into sqliimport's, register
them as shown, and regenerate the autoloads (`php bin/php/ezpgenerateautoloads.php -e`). After an extension upgrade
or new classes, restart the long-lived workers too: `./console exp:velocity restart` where Velocity serves the site,
and the PHP-FPM pool ([troubleshooting.md](troubleshooting.md#velocity-or-php-fpm-still-run-the-old-code)).

### Writing your own source

A source implements `expImportSourceInterface`; extending `expImportAbstractSource` gives it the common option
`key`, the reference of a record (`refOf()`), the checked local file (`file()`), `sample()` and the catalogue entry's
defaults. It never fetches anything: the engine resolves the upload, address or server path and hands the source a
local file as `file` in its configuration, so every chunk of a run reads the same snapshot.

| Method | Purpose |
|---|---|
| `static identifier()`, `label()`, `extensions()`, `schema()` | The id an import stores in `source_type`, the name in the catalogue, the file name extensions of an upload, the options |
| `static description()`, `group()`, `examples()`, `preset()` | The catalogue entry: what one record is, the group (`data`, `nosql`, `content`, `platform`, `commerce`, `media`, `other`), example files, the mapping to start from |
| `records( array $cursor = array() )` | The records, as `expImportRecord( $ref, $index, $data, $cursor )`, from the start or right after a cursor an earlier record carried |
| `count()` | The number of records when it is cheap to tell (progress and ETA), else `null` |
| `sample( $limit )` | The first records and the fields (inherited) |

The cursor of a record says where it ends. A run is cut into chunks, and the next chunk calls `records( $cursor )`
with the cursor of the last record of the chunk before, so a source must be able to start right after it.

```php
<?php
class myTeaPsvSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'tea_psv';
    }

    public static function label()
    {
        return 'Pipe separated tea list (PSV)';
    }

    public static function extensions()
    {
        return array( 'psv', 'txt' );
    }

    public static function description()
    {
        return 'A text file with one tea per line and the fields separated by |; the first line names the fields.';
    }

    public static function group()
    {
        return 'data';
    }

    public static function schema()
    {
        return array(
            'header' => array( 'type' => 'bool', 'label' => 'The first line names the fields', 'default' => true ),
        ) + expImportAbstractSource::commonSchema();
    }

    public static function preset()
    {
        return array(
            'class' => 'product',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'tea-{sku}' ),
            'fields' => array(
                array( 'source' => 'name', 'attribute' => 'name', 'transforms' => array( array( 'name' => 'trim' ) ) ),
                array( 'source' => 'sku', 'attribute' => 'product_number' ),
                array( 'source' => 'net_price', 'attribute' => 'price', 'transforms' => array( array( 'name' => 'vat', 'args' => array( 'rate' => 19 ) ) ) ),
            ),
        );
    }

    public function records( array $cursor = array() )
    {
        $handle = fopen( $this->file(), 'rb' );
        if ( !$handle )
            throw new expImportSourceException( 'the PSV file cannot be opened' );
        try
        {
            $withHeader = !isset( $this->config['header'] ) || !empty( $this->config['header'] );
            $after = isset( $cursor['line'] ) ? (int)$cursor['line'] : 0;
            $header = null;
            $line = 0;
            $index = -1;
            while ( ( $text = fgets( $handle ) ) !== false )
            {
                $line++;
                $text = rtrim( self::toUtf8( $text ), "\r\n" );
                if ( trim( $text ) === '' )
                    continue;
                $cells = array_map( 'trim', explode( '|', $text ) );
                if ( $header === null )
                {
                    if ( $withHeader )
                    {
                        $header = $cells;
                        continue;
                    }
                    $header = array_map( function ( $i ) { return 'column_' . ( $i + 1 ); }, array_keys( $cells ) );
                }
                $index++;
                if ( $line <= $after )
                    continue;
                $data = array();
                foreach ( $header as $i => $name )
                    $data[$name] = isset( $cells[$i] ) ? $cells[$i] : '';
                // the cursor is where this record ends: a later chunk starts right after it
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'line' => $line ) );
            }
        }
        finally
        {
            fclose( $handle );
        }
    }

    public function count()
    {
        $lines = array_filter( file( $this->file(), FILE_IGNORE_NEW_LINES ) ?: array(), function ( $l ) { return trim( $l ) !== ''; } );
        $withHeader = !isset( $this->config['header'] ) || !empty( $this->config['header'] );
        return max( 0, count( $lines ) - ( $withHeader ? 1 : 0 ) );
    }
}
```

**Registration**, in `extension/mytea/settings/sqliimport.ini.append.php`:

```ini
[SourceSettings]
Sources[]=tea_psv

[Source_tea_psv]
Class=myTeaPsvSource
Status=enabled
```

or, on Exponential 6.0.15 and later, as a kernel service in `extension/mytea/settings/kernelservices.ini.append.php`:

```ini
[Service_mytea.source.tea_psv]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=myTeaPsvSource
Scope=worker
Tags[]=sqliimport.source
Description=Tea list (PSV)
```

`Status=example` lists a format in the catalogue without letting imports use it, `Status=disabled` hides it.

**The catalogue entry.** The catalogue builds the card from the static methods: label, description, group, file
name extensions, options, examples and the preset mapping. What the registry made of the class, and what the source
read:

```
label: Pipe separated tea list (PSV) | group: data | status: enabled | extensions: psv, txt | options: header, key
options valid: {"header":true,"key":"sku"}
count(): 3
TEA-SEN-100 {"sku":"TEA-SEN-100","name":"Morning Sencha","origin":"Japan","net_price":"6.50"} cursor {"line":2}
TEA-HBL-100 {"sku":"TEA-HBL-100","name":"Highland Black","origin":"India","net_price":"5,90"} cursor {"line":3}
TEA-SNE-050 {"sku":"TEA-SNE-050","name":"Silver Needle","origin":"China","net_price":""} cursor {"line":4}
--- records( {"line":2} ) resumes after the first record
TEA-HBL-100
TEA-SNE-050
```

### Writing your own handler

A handler implements `expImportHandlerInterface` ([the interface](#the-2x-handler-interface)). This one publishes a
product per record through the content API, computes its gross price with the transform below, and in a dry run
compares with what the object has, so the run page shows what would change.

```php
<?php
class myTeaImportHandler implements expImportHandlerInterface
{
    public static function identifier()
    {
        return 'teas';
    }

    public static function label()
    {
        return 'Teas with their gross price';
    }

    public static function schema()
    {
        return array(
            'parent_node_id' => array( 'type' => 'node', 'label' => 'Parent node', 'required' => true ),
            'vat_rate' => array( 'type' => 'float', 'label' => 'VAT rate in percent', 'default' => 19, 'min' => 0, 'max' => 100 ),
        );
    }

    public function open( expImportContext $context )
    {
    }

    public function process( expImportRecord $record, expImportContext $context )
    {
        $sku = trim( (string)$record->get( 'sku', '' ) );
        if ( $sku === '' )
            return expImportRowResult::failed( 'the record has no SKU' );
        $name = trim( (string)$record->get( 'name', '' ) );
        $rate = isset( $context->options['vat_rate'] ) ? (float)$context->options['vat_rate'] : 19.0;
        // a transform of the catalogue, here the site's own "vat"; an unreadable price fails this record only
        $price = expImportTransforms::applyChain( $record->get( 'net_price', '' ), array( array( 'name' => 'vat', 'args' => array( 'rate' => $rate ) ) ), $record );

        $remoteID = 'tea-' . $sku;
        $object = eZContentObject::fetchByRemoteID( $remoteID );
        if ( $context->dryRun )
        {
            // compare with what the object has now, in the fromString() form the publisher would write
            $now = array();
            if ( $object )
                foreach ( $object->dataMap() as $identifier => $attribute )
                    $now[$identifier] = (string)$attribute->toString();
            $diff = array();
            foreach ( array( 'name' => $name, 'product_number' => $sku, 'price' => $price ) as $field => $new )
            {
                $old = isset( $now[$field] ) ? $now[$field] : '';
                if ( $old !== (string)$new )
                    $diff[$field] = array( $old, (string)$new );
            }
            if ( !$object )
                $result = new expImportRowResult( expImportRowResult::CREATED, 'dry run: would be created' );
            else
            {
                $id = (int)$object->attribute( 'id' );
                $version = (int)$object->attribute( 'current_version' );
                $result = $diff ? expImportRowResult::updated( $id, $version, $version, 'dry run: would be updated' )
                                : expImportRowResult::unchanged( $id, $version, 'dry run: unchanged' );
            }
            $result->diff = $diff;
            return $result;
        }

        $before = $object ? (int)$object->attribute( 'current_version' ) : 0;
        $content = $object ? SQLIContent::fromContentObject( $object )
                           : SQLIContent::create( new SQLIContentOptions( array( 'class_identifier' => 'product', 'remote_id' => $remoteID ) ) );
        $isNew = $content->isNew();
        $content->fields->name = $name;
        $content->fields->product_number = $sku;
        $content->fields->price = $price;
        $publisher = SQLIContentPublisher::getInstance();
        $publisher->setOptions( new SQLIContentPublishOptions( array(
            'parent_node_id' => $isNew ? (int)$context->options['parent_node_id'] : null,
            'modification_check' => true,
        ) ) );
        $publisher->publish( $content );
        $id = (int)$content->id;
        $after = (int)$content->current_version;
        $content->flush();

        if ( $isNew )
            return expImportRowResult::created( $id, $after );
        return $after !== $before ? expImportRowResult::updated( $id, $before, $after ) : expImportRowResult::unchanged( $id, $after );
    }

    public function close( expImportContext $context )
    {
    }
}
```

**Registration**, in `extension/mytea/settings/sqliimport.ini.append.php`:

```ini
[HandlerSettings]
Handlers[]=teas

[Handler_teas]
Class=myTeaImportHandler
Status=enabled
```

or as a kernel service tagged `sqliimport.handler`:

```ini
[Service_mytea.handler.teas]
Class=myTeaImportHandler
Scope=worker
Tags[]=sqliimport.handler
Description=Teas with their gross price
```

The import chooses it in the step **Processing** (*How the records are processed*), which then shows a form of its
options (*Parent node*, *VAT rate in percent*).

**What it did.** The preview's dry run before any product existed:

```
TEA-SEN-100  created  dry run: would be created {"name":["","Morning Sencha"],"product_number":["","TEA-SEN-100"],"price":["","7.74|1|1"]}
TEA-HBL-100  created  dry run: would be created {"name":["","Highland Black"],"product_number":["","TEA-HBL-100"],"price":["","7.02|1|1"]}
TEA-SNE-050  failed   vat: the price is empty
```

A real run, twice: the first creates the products, the second finds nothing to change (the content API publishes a
new version only when a field changed):

```
--- real run, pass 1
TEA-SEN-100  created   object 56638
TEA-HBL-100  created   object 56639
TEA-SNE-050  failed    object - vat: the price is empty
--- real run, pass 2
TEA-SEN-100  unchanged object 56638
TEA-HBL-100  unchanged object 56639
TEA-SNE-050  failed    object - vat: the price is empty
```

After the net price of Highland Black was changed to `6,10` in the file, the dry run shows exactly that change:

```
TEA-SEN-100  unchanged dry run: unchanged
TEA-HBL-100  updated  dry run: would be updated {"price":["7.02|1|1","7.26|1|1"]}
TEA-SNE-050  failed   vat: the price is empty
```

The record without a price fails on its own, with the transform's message, and the run goes on.

### Writing your own transform

A transform implements `expImportTransformInterface`: a `name()` a mapping line uses, a `label()`, the arguments as
a `schema()` and `apply( $value, $args, $record )`. It is a function of its input: it keeps no state between calls.
An `expImportTransformException` fails the record with its message. This one turns a net price into the `ezprice`
text of the gross price, `amount|VAT type|1` (the price includes VAT):

```php
<?php
class myVatTransform implements expImportTransformInterface
{
    public function name()
    {
        return 'vat';
    }

    public function label()
    {
        return 'A net price as the ezprice text of its gross price, e.g. vat(rate=19)';
    }

    public function schema()
    {
        return array(
            'rate' => array( 'type' => 'float', 'label' => 'VAT rate in percent', 'default' => 19, 'min' => 0, 'max' => 100 ),
            'vat_type' => array( 'type' => 'int', 'label' => 'The VAT type ID of the shop', 'default' => 1, 'min' => 1 ),
        );
    }

    public function apply( $value, array $args, ?expImportRecord $record = null )
    {
        $text = str_replace( ',', '.', trim( (string)$value ) );
        if ( $text === '' )
            throw new expImportTransformException( 'vat: the price is empty' );
        if ( !is_numeric( $text ) )
            throw new expImportTransformException( "vat: '$value' is not a number" );
        $rate = isset( $args['rate'] ) ? (float)$args['rate'] : 19.0;
        $type = isset( $args['vat_type'] ) ? (int)$args['vat_type'] : 1;
        // in cents, so 6.50 at 19 % is 7.74 (773.5 cents rounded up), not 7.73 of a binary fraction
        $gross = round( (float)$text * ( 100 + $rate ) ) / 100;
        // amount|VAT type|1: the price includes VAT (ezprice's fromString() form). The amount as PHP writes the
        // number (7.1, not 7.10): that is how ezprice gives it back, so an unchanged price compares equal
        return (string)$gross . '|' . $type . '|1';
    }
}
```

**Registration**, as a kernel service tagged `sqliimport.transform` (transforms have no INI list):

```ini
[Service_mytea.transform.vat]
Class=myVatTransform
Scope=worker
Tags[]=sqliimport.transform
Description=Net price to the ezprice text of the gross price
```

A mapping line then uses it like a built-in transform, in the one-line form of the mapping grid:
`vat(rate=19)`, or `vat(rate=7, vat_type=2)` for the reduced rate of a shop whose VAT type 2 is 7 %. The mapping
editor offers it, and the catalogue's Transforms table lists it. What it gave:

```
vat(rate=19) on '6.50' => '7.74|1|1'
vat(rate=7, vat_type=2) on '5,90' => '6.31|2|1'
vat on 'n/a' => error: vat: 'n/a' is not a number
```

The Transforms table of the catalogue, where a registered `vat` appears next to the
built-in ones:

![The Transforms table of the catalogue: name, what it does, arguments and origin of each built-in transform](images/dev-catalogue-transforms-table.png)

The amount is written as PHP writes the number (`7.1`, not `7.10`), the way `ezprice` gives a stored price back, so an
unchanged price compares equal and the object is not updated at every run
([troubleshooting.md](troubleshooting.md#prices-are-0-or-every-run-updates-every-price)). It rounds in cents, so
6.50 at 19 % is 7.74 and not the 7.73 a binary fraction would give.

### How the examples were checked

The three classes were defined in a script outside the extension, which registered the classes for its own process with
`eZINI::setVariable()` and `expImportTransforms::register()`, and ran them through the engine on the documentation
server with `php bin/php/ezexec.php <script> -s admin`. The run used the registry's catalogue,
`expImportPreview::preview()` for the dry run, and the handler's `open()`, `process()` and `close()` for the real
run. The two products were published in a hidden folder.

## The target "A data table" (Exponential 6.0.15)

Besides the mapping to a content class, an import can fill the table of a **persistent class** (a module's or an
extension's own table, listed in **Setup, Data tables**): choose **Rows of a data table (a persistent class)** in the
step Processing. The rows are written by the kernel's `expDataTableWriter`: checked against the class's definition,
on the class's connection, matched by a unique key, a transaction per chunk, never raw SQL. Nothing of content is
made, so a run of this target cannot be undone; its report lists the key of every row.

| Option | Values | Default |
|---|---|---|
| `class` | a persistent class with a table (a list of the data tables of the site) | required |
| `mode` | `upsert` (add new rows, update existing ones), `insert`, `update`, `replace` (an existing row is removed and written again) | `upsert` |
| `keys` | the columns a row is matched by, one per line | the class's keys |
| `columns` | one line per column: `column = field of the record \| transforms` (the one-line chain of the mapping grid, e.g. `price = price \| number`); empty: every field whose name is a column | |

A relation column (object, node, user) takes a reference to content instead of an id: `@object:<remote id>`,
`@node:<remote id>` or `@path:<url alias>`. A dry run checks and counts with the writer, and writes nothing.

**A new table from an import.** Each import offers **New data table from its fields** under More (policy
setup/setup). It opens the kernel's module table wizard with the import's fields, guessed from a sample of its
source: column names, types (string, text, integer, float, boolean, date, datetime, json), lengths, required and
unique columns. The wizard makes the class, the table and its admin views in an extension; the import can then fill
it with this target. The provider is the kernel service `datatable.field_source.sqliimport`.

**Content and tables in sync.** The catalogue's group **Data tables** opens the kernel's synchronisations (policy
datatable/sync) with a data table and a content class filled in: export content to a table, migrate content into a
table, keep a table in sync with content, import a table into content, or both ways with a conflict rule. The
kernel runs them and keeps their history (tool `transfer.datatable`); 7x SQLI Import copies nothing itself.

## Handlers that read their own records

A handler that also implements `expImportRecordProviderInterface` supplies the records itself, instead of the
import's source:

| Method | Purpose |
|---|---|
| `records( expImportContext $context, array $cursor = array() )` | The records, after a cursor |
| `count( expImportContext $context )` | The number of records, or `null` |
| `resumable()` | Whether `records()` can start after a cursor |

When `resumable()` returns `false`, the whole run is one chunk, and a retry starts from the beginning.

## The 1.x compatibility layer

Every 1.x handler (`ISQLIImportHandler`, extending `SQLIImportAbstractHandler`) runs in the 2.x engine untouched,
through `expImportLegacyHandlerAdapter`:

- The handlers of `[ImportSettings] AvailableSourceHandlers[]` with their `[<id>-HandlerSettings]` blocks appear in
  the catalogue, marked as legacy.
- The handler is built with `SQLIImportHandlerOptions` filled from the import's options. It gets its
  `handlerConfArray` from its settings block as before.
- `initialize()`, `getProcessLength()`, `getNextRow()`, `process()` and `cleanup()` are called in that order.
  `getProgressionNotes()` goes to the run's log.
- A 1.x handler reads its own source and cannot start in the middle, so its run is one chunk.
- What `process()` did is not known to the engine, so its rows have the outcome `processed`, or `failed` with the
  exception's message.
- "Retry failed rows" does not apply, because the rows carry no record. Run the import again; the content API's
  unchanged detection keeps that cheap.

The 1.x API itself (the content API, `SQLICSVDoc`, `SQLIXMLParser`, the handler methods) is described in
[content-api-and-handlers-1x.md](content-api-and-handlers-1x.md).
