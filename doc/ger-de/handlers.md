# Handler

Verwenden Sie den Handler `mapping` ohne Programmierung, wenn sich der Import als Zuordnung ausdrücken lässt
([mappings.md](mappings.md)). Schreiben Sie einen Handler, wenn das nicht geht: mehrere Objekte pro Datensatz,
Berechnungen über mehrere Datensätze, eine externe API pro Datensatz.

## Die Handler-Schnittstelle von 2.x

Ein Handler implementiert `expImportHandlerInterface`:

| Methode | Zweck |
|---|---|
| `static identifier()` | Die ID, die ein Import in `handler` speichert |
| `static label()` | Der Name im Katalog |
| `static schema()` | Die Optionen des Handlers als Schema von `expImportOptionSchema` |
| `open( expImportContext $context )` | Bereitet einen Abschnitt vor (Caches, einen Publisher) |
| `process( expImportRecord $record, expImportContext $context )` | Verarbeitet einen Datensatz und gibt ein `expImportRowResult` zurück |
| `close( expImportContext $context )` | Beendet einen Abschnitt |

Pro Abschnitt ruft die Engine einmal `open()`, für jeden Datensatz `process()` und einmal `close()` auf, in einem
Worker der Job-Warteschlange. Eine Ausnahme in `process()` lässt nur diesen Datensatz fehlschlagen; ihre Meldung
wird zur Meldung der Zeile, und der Lauf geht weiter.

Die Datensätze kommen aus der Quelle des Imports; ein Handler liest also keine Dateien. Der Kontext gibt ihm:

| Eigenschaft | Inhalt |
|---|---|
| `$context->import` | Der Import (`expImportDefinition`) |
| `$context->run` | Der Lauf (`expImportRun`) |
| `$context->options` | Die Optionen des Handlers, geprüft gegen `schema()` |
| `$context->target` | `parent_node_id`, `section_id`, `language`, `owner_id` |
| `$context->behaviour` | `chunk_size`, `update_policy`, `trash_policy`, `max_rows` |
| `$context->log` | Das Protokoll des Laufs (`expImportLogger`) |
| `$context->dryRun` | `true` in einem Probelauf: nichts veröffentlichen |

`$context->throwIfCancelled()` verlängert die Reservierung des Jobs und bricht den Datensatz ab, wenn der Lauf
abgebrochen wurde. Die Engine ruft es schon zwischen den Datensätzen auf; rufen Sie es in eigenen langen Schleifen
auf.

### Ergebnisse

`expImportRowResult` hat eine Fabrikmethode pro Ergebnis:

```php
expImportRowResult::created( $objectID, $versionAfter );
expImportRowResult::updated( $objectID, $versionBefore, $versionAfter );
expImportRowResult::unchanged( $objectID, $version );
expImportRowResult::skipped( 'the product is discontinued', $objectID );
expImportRowResult::failed( 'the price is missing' );
```

Füllen Sie in einem Probelauf `$result->diff` (`attribut => array( alt, neu )`), damit die Seite des Laufs zeigen
kann, was sich ändern würde.

### Optionstypen

Ein Schema ist ein Array `name => definition`. Typen: `string`, `text`, `int`, `float`, `bool`, `enum` (mit
`values`), `path`, `url`, `node`, `class`, `list`, `json` und `secret` (ein Verweis auf ein Geheimnis in den
Einstellungen, nie das Geheimnis selbst). Jede Definition kann `label`, `help`, `required`, `default`, `min`, `max`
und `pattern` enthalten. Unbekannte Schlüssel werden verworfen.

## Beispiel

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

Registrieren Sie ihn in `settings/sqliimport.ini.append.php` Ihrer Erweiterung und erzeugen Sie die Autoloads neu:

```ini
[HandlerSettings]
Handlers[]=products

[Handler_products]
Class=myProductImportHandler
Status=enabled
```

Ein Handler kann auch ein Kernel-Dienst mit dem Tag `sqliimport.handler` sein
(`settings/kernelservices.ini.append.php`).

## Das Ziel „Eine Datentabelle“ (Exponential 6.0.15)

Außer der Zuordnung zu einer Inhaltsklasse kann ein Import die Tabelle einer **persistenten Klasse** füllen (die
eigene Tabelle eines Moduls oder einer Erweiterung, aufgeführt unter **Setup, Data tables**): im Schritt Processing
**Rows of a data table (a persistent class)** wählen. Die Zeilen schreibt der `expDataTableWriter` des Kernels: gegen
die Definition der Klasse geprüft, über die Verbindung der Klasse, über einen eindeutigen Schlüssel zugeordnet, eine
Transaktion je Abschnitt, nie rohes SQL. Es entsteht kein Inhalt, ein Lauf dieses Ziels kann also nicht rückgängig
gemacht werden; sein Bericht nennt den Schlüssel jeder Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `class` | eine persistente Klasse mit Tabelle (eine Liste der Datentabellen der Website) | Pflicht |
| `mode` | `upsert` (neue Zeilen anlegen, bestehende ändern), `insert`, `update`, `replace` (eine bestehende Zeile wird entfernt und neu geschrieben) | `upsert` |
| `keys` | die Spalten, über die eine Zeile zugeordnet wird, eine je Zeile | die Schlüssel der Klasse |
| `columns` | eine Zeile je Spalte: `Spalte = Feld des Datensatzes \| Umwandlungen` (die einzeilige Kette des Rasters, z. B. `price = price \| number`); leer: jedes Feld, dessen Name eine Spalte ist | |

Eine Beziehungsspalte (Objekt, Knoten, Benutzer) nimmt statt einer ID einen Verweis auf Inhalt: `@object:<Remote-ID>`,
`@node:<Remote-ID>` oder `@path:<URL-Alias>`. Ein Probelauf prüft und zählt mit dem Writer und schreibt nichts.

**Eine neue Tabelle aus einem Import.** Jeder Import bietet unter More **New data table from its fields** an
(Richtlinie setup/setup). Das öffnet den Modultabellen-Assistenten des Kernels mit den Feldern des Imports, geschätzt
aus einer Stichprobe seiner Quelle: Spaltennamen, Typen (string, text, integer, float, boolean, date, datetime, json),
Längen, Pflicht- und eindeutige Spalten. Der Assistent legt Klasse, Tabelle und ihre Verwaltungsseiten in einer
Erweiterung an; der Import kann sie dann mit diesem Ziel füllen. Der Anbieter ist der Kernel-Dienst
`datatable.field_source.sqliimport`.

**Inhalte und Tabellen abgleichen.** Die Gruppe **Data tables** des Katalogs öffnet die Synchronisationen des Kernels
(Richtlinie datatable/sync) mit eingetragener Datentabelle und Inhaltsklasse: Inhalte in eine Tabelle exportieren,
Inhalte in eine Tabelle migrieren, eine Tabelle mit Inhalten synchron halten, eine Tabelle in Inhalte importieren,
oder beides mit einer Regel für Konflikte. Der Kernel führt sie aus und führt ihre Historie (Werkzeug
`transfer.datatable`); 7x SQLI Import kopiert selbst nichts.

## Handler, die ihre Datensätze selbst lesen

Ein Handler, der zusätzlich `expImportRecordProviderInterface` implementiert, liefert die Datensätze selbst statt
der Quelle des Imports:

| Methode | Zweck |
|---|---|
| `records( expImportContext $context, array $cursor = array() )` | Die Datensätze, nach einem Cursor |
| `count( expImportContext $context )` | Die Anzahl der Datensätze oder `null` |
| `resumable()` | Ob `records()` nach einem Cursor beginnen kann |

Gibt `resumable()` `false` zurück, ist der ganze Lauf ein Abschnitt, und eine Wiederholung beginnt von vorn.

## Die Kompatibilitätsschicht für 1.x

Jeder Handler von 1.x (`ISQLIImportHandler`, abgeleitet von `SQLIImportAbstractHandler`) läuft unverändert in der
Engine von 2.x, über `expImportLegacyHandlerAdapter`:

- Die Handler aus `[ImportSettings] AvailableSourceHandlers[]` mit ihren Blöcken `[<id>-HandlerSettings]`
  erscheinen im Katalog, als Legacy gekennzeichnet.
- Der Handler wird mit `SQLIImportHandlerOptions` erzeugt, gefüllt aus den Optionen des Imports. Sein
  `handlerConfArray` bekommt er wie bisher aus seinem Einstellungsblock.
- `initialize()`, `getProcessLength()`, `getNextRow()`, `process()` und `cleanup()` werden in dieser Reihenfolge
  aufgerufen. `getProgressionNotes()` geht in das Protokoll des Laufs.
- Ein Handler von 1.x liest seine Quelle selbst und kann nicht in der Mitte beginnen; sein Lauf ist daher ein
  Abschnitt.
- Was `process()` getan hat, kennt die Engine nicht. Seine Zeilen haben daher das Ergebnis `processed` oder
  `failed` mit der Meldung der Ausnahme.
- „Retry failed rows“ (fehlgeschlagene Zeilen wiederholen) gilt hier nicht, weil die Zeilen keinen Datensatz enthalten. Starten Sie den
  Import erneut; die Erkennung unveränderter Inhalte der Inhalts-API hält das günstig.

Die API von 1.x selbst (die Inhalts-API, `SQLICSVDoc`, `SQLIXMLParser`, die Handler-Methoden) beschreibt
[content-api-and-handlers-1x.md](../content-api-and-handlers-1x.md) (auf Englisch).
