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

## Example

```php
<?php
class myProductImportHandler implements expImportHandlerInterface
{
    public static function identifier() { return 'products'; }

    public static function label() { return 'Products from the ERP export'; }

    public static function schema()
    {
        return array( 'parent_node_id' => array( 'type' => 'node', 'label' => 'Parent node', 'required' => true ) );
    }

    public function open( expImportContext $context ) {}

    public function process( expImportRecord $record, expImportContext $context )
    {
        $sku = trim( (string)$record->get( 'sku', '' ) );
        if ( $sku === '' )
            return expImportRowResult::failed( 'the record has no SKU' );
        $remoteID = 'erp-product-' . $sku;
        $object = eZContentObject::fetchByRemoteID( $remoteID );
        if ( $context->dryRun )
            return $object ? expImportRowResult::unchanged( $object->attribute( 'id' ), $object->attribute( 'current_version' ) )
                           : new expImportRowResult( expImportRowResult::CREATED, 'dry run: would be created' );

        $before = $object ? (int)$object->attribute( 'current_version' ) : 0;
        $content = $object ? SQLIContent::fromContentObject( $object )
                           : SQLIContent::create( new SQLIContentOptions( array( 'class_identifier' => 'product', 'remote_id' => $remoteID ) ) );
        $isNew = $content->isNew();
        $content->fields->name = (string)$record->get( 'name', '' );
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

    public function close( expImportContext $context ) {}
}
```

Register it in your extension's `settings/sqliimport.ini.append.php` and regenerate the autoloads:

```ini
[HandlerSettings]
Handlers[]=products

[Handler_products]
Class=myProductImportHandler
Status=enabled
```

A handler can also be a kernel service tagged `sqliimport.handler` (`settings/kernelservices.ini.append.php`).

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
