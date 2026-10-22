# Zuordnungen

Der Handler `mapping` (Klasse `expImportMappingHandler`) importiert ohne PHP. Eine Zuordnung legt fest, welches
Quellfeld welches Attribut einer Inhaltsklasse füllt. Der Handler veröffentlicht über die Inhalts-API von 1.x
(`SQLIContent`, `SQLIContentPublisher`), die nur dann eine neue Version veröffentlicht, wenn sich ein Feld wirklich
geändert hat.

## Eine Zuordnung

Als Array (`expImportMapper`, gespeichert mit `expImportMapping::saveArray()`):

| Schlüssel | Bedeutung |
|---|---|
| `class` | Der Bezeichner der Inhaltsklasse |
| `language` | Die Sprache der Felder; leer: die Zielsprache des Imports, sonst die Standardsprache der Website |
| `match` | Wie ein Datensatz sein Objekt findet (siehe unten) |
| `fields` | Die Zeilen: `source` (ein Pfad im Datensatz), `attribute`, `transforms` (eine Kette), `default`, `required`, `language` (leer: die der Zuordnung) |
| `location` | Wohin neue Objekte kommen: `parent_node_id`, `parent_from`, `parent_prefix`, `extra_parents_from` |

### Zuordnen zum Objekt

| `match` | Das Objekt des Datensatzes ist |
|---|---|
| `array( 'type' => 'remote_id', 'pattern' => 'news-{id}' )` | Das Objekt mit dieser Remote-ID. Das Muster braucht mindestens ein `{feld}` des Datensatzes. Eine Remote-ID mit mehr als 100 Zeichen wird mit einem MD5-Hash gekürzt. |
| `array( 'type' => 'attribute', 'attribute' => 'isbn', 'source' => 'isbn' )` | Das Objekt der Klasse, dessen Attribut den Wert des Datensatzes hat |

Passt kein Objekt, wird ein neues angelegt. Verwenden Sie möglichst die Remote-ID: Sie ist indiziert und reist mit
dem Inhalt mit.

### Felder

- Ein Listenwert (aus `split`, einer CSV-Liste oder einem JSON-Array) wird mit `|` verbunden; das ist die Textform
  von Objektbeziehungslisten und ähnlichen Datentypen.
- `default` gilt, wenn der Quellwert nach den Umwandlungen leer ist.
- `required` lässt den Datensatz fehlschlagen, wenn der Wert dann immer noch leer ist.
- Eine Zeile mit eigener `language` schreibt eine Übersetzung des Objekts.

### Platzierung

- `parent_from` nennt ein Feld des Datensatzes mit der Remote-ID des Elternobjekts; `parent_prefix` wird davor
  gesetzt. Hat der Datensatz keinen solchen Wert, gilt `parent_node_id`.
- `extra_parents_from` nennt ein Feld mit den Remote-IDs weiterer Elternobjekte (eine Liste oder ein Text mit
  Kommas). Sie werden zu weiteren Platzierungen eines neuen Objekts.
- Datensätze werden in ihrer Reihenfolge verarbeitet. Ein Elternobjekt, das im selben Lauf importiert wird, muss
  also vor seinen Kindern kommen.

## Verhalten

Das `behaviour_json` des Imports entscheidet, was mit bestehenden Objekten geschieht:

| `update_policy` | Wirkung |
|---|---|
| `update` (Vorgabe) | Neue Objekte werden angelegt; bestehende bekommen eine neue Version, wenn sich ein Feld geändert hat |
| `create_only` | Bestehende Objekte werden übersprungen |
| `update_only` | Datensätze ohne Objekt werden übersprungen |

Ein Objekt im Papierkorb wird übersprungen und gemeldet. Sektion (`section_id`) und Besitzer (`owner_id`) des Ziels
gelten für neue Objekte.

In einem Probelauf wird nichts geschrieben. Jede Zeile sagt, was geschehen würde, und führt die geänderten Felder
mit aktuellem und neuem Wert auf.

## Umwandlungen

Eine Kette ist eine Liste von Schritten: `array( array( 'name' => 'trim' ), array( 'name' => 'date', 'args' =>
array( 'format' => 'd.m.Y' ) ) )`. Die Argumente werden geprüft. Schlägt eine Umwandlung fehl, schlägt der
Datensatz mit ihrer Meldung fehl. Die eingebauten Umwandlungen (`expImportTransforms::builtins()`):

| Name | Was sie tut | Argumente |
|---|---|---|
| `trim` | Entfernt Leerzeichen an beiden Enden | |
| `lower` | Kleinbuchstaben | |
| `upper` | Großbuchstaben | |
| `title` | Erster Buchstabe jedes Worts groß | |
| `strip_tags` | Entfernt HTML-Tags und dekodiert Entitäten | |
| `prefix` | Setzt einen Text vor den Wert | `text` (Pflicht) |
| `suffix` | Setzt einen Text hinter den Wert | `text` (Pflicht) |
| `replace` | Ersetzt einen Text | `search` (Pflicht), `replace` |
| `regex_replace` | Ersetzt, was ein regulärer Ausdruck findet | `pattern` (Pflicht), `replace` |
| `truncate` | Kürzt den Wert auf eine Länge | `length` (Pflicht, mindestens 1) |
| `default` | Ein Wert, wenn die Quelle leer ist | `value` |
| `map` | Ersetzt einen Wert durch einen anderen aus einer Tabelle | `table` (Pflicht, JSON), `otherwise` (leer: Wert behalten) |
| `split` | Teilt einen Text in eine Liste | `separator` (Vorgabe `,`) |
| `join` | Verbindet eine Liste zu einem Text | `separator` (Vorgabe `, `) |
| `first` | Der erste Eintrag einer Liste | |
| `concat` | Verbindet den Wert mit anderen Feldern des Datensatzes | `paths` (Pflicht, eine Liste), `separator` (Vorgabe ein Leerzeichen) |
| `template` | Baut einen Text aus Feldern des Datensatzes: `{title} ({year})`; `{value}` ist der aktuelle Wert | `text` (Pflicht) |
| `number` | Eine Zahl; liest `1.234,56` und `1,234.56` | `decimals` (Vorgabe -1: wie angegeben) |
| `int` | Eine ganze Zahl (gerundet) | |
| `bool` | `1` für 1, true, yes, y, on, ja, oui, x; sonst `0` | |
| `date` | Ein Datum oder eine Uhrzeit als Zeitstempel (ezdate, ezdatetime) | `format` (PHP-Datumsformat; leer: jede Form, die `strtotime()` liest), `timezone` (Vorgabe `UTC`) |
| `date_format` | Ein Datum als Text in einem Format | `format` (Pflicht), `timezone` (Vorgabe `UTC`) |
| `slug` | Ein URL-freundlicher Bezeichner | |
| `richtext` | HTML in den Rich Text (ezxmltext) des Attributs | |
| `lookup` | Die ID des Objekts mit dieser Remote-ID (Objektbeziehungen) | `prefix` |
| `image` | Lädt ein Bild von einer URL herunter (ezimage) | |
| `file` | Lädt eine Datei von einer URL herunter (ezbinaryfile, ezmedia) | |

Für `image` und `file` gelten dieselben Adressregeln wie für eine Quelle mit `url` ([sources.md](sources.md)).

Eine Website fügt eigene Umwandlungen als Kernel-Dienst mit dem Tag `sqliimport.transform` hinzu, der
`expImportTransformInterface` implementiert (`name()`, `label()`, `schema()`, `apply( $value, $args, $record )`).
Eine Zuordnung kann nur registrierte Umwandlungen nennen; sie enthält nie Code.

## Einen Import in PHP anlegen

Der Import-Editor der Administration kommt mit 2.0.0-beta.1. Bis dahin legen Sie einen Import mit Zuordnung in PHP
an, zum Beispiel in einem Skript, das Sie mit `php bin/php/ezexec.php <skript> --allow-root-user` ausführen:

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

Prüfen Sie eine Zuordnung, bevor Sie sie speichern:
`expImportMapper::validate( $mapping, expImportMappingHandler::classAttributes( 'article' ) )` gibt eine Liste von
Fehlern zurück (leer, wenn die Zuordnung gültig ist). Probieren Sie sie dann mit
`./console ext:sqliimport:run news_feed --dry-run --wait` aus.
