# Zuordnungen

Der Handler `mapping` (Klasse `expImportMappingHandler`) importiert ohne PHP. Eine Zuordnung legt fest, welches
Quellfeld welches Attribut einer Inhaltsklasse füllt. Der Handler veröffentlicht über die Inhalts-API von 1.x
(`SQLIContent`, `SQLIContentPublisher`), die nur dann eine neue Version veröffentlicht, wenn sich ein Feld wirklich
geändert hat.

In der Administration legt der Schritt Zuordnung des Import-Editors jeden Teil einer Zuordnung in einem Formular
fest, mit einem Raster aus einer Zeile pro Attribut ([editor.md](editor.md#schritt-3-zuordnung-mapping)). Diese
Seite beschreibt, was das Formular speichert.

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
- Die Abschnitte eines Laufs laufen nacheinander, nie nebeneinander: Der nächste Abschnitt wird erst eingereiht,
  wenn der vorige fertig ist. Ein Ordner-Datensatz, der vor seinen Dokumenten kommt, wird also vor ihnen
  veröffentlicht, wie groß der Lauf auch ist. Ein Datensatz, dessen Elternobjekt trotzdem fehlt, schlägt mit „the
  parent of the record does not exist (yet)“ fehl; **Retry failed rows** nach dem Lauf platziert ihn.

### Eine Klasse je Datensatz

Manche Quellen liefern Datensätze verschiedener Art: die Dokumente von ezodf liefern Ordner und Dokumente, ein Feed
vielleicht Folgen und Notizen. `class_from` nennt das Feld des Datensatzes, das die Klasse wählt, und `variants`
gibt je Wert eine Klasse und Zeilen; Zuordnung und Platzierung bleiben die der Zuordnung, ein Baum aus Ordnern und
Dokumenten teilt sich also das Muster der Remote-ID und die Regel für das Elternobjekt. Ein Wert ohne Variante nimmt
die Klasse und die Zeilen der Zuordnung; `skip` lässt diese Datensätze weg (ihre Zeilen sagen „not imported“).

```json
"class_from": "kind",
"variants": {
  "folder": { "class": "folder", "fields": [ { "source": "title", "attribute": "name" } ] },
  "note":   { "skip": true }
}
```

Die Vorgabe eines Formats kann sie mitbringen, ebenso eine Zuordnungsdatei (herunterladen, bearbeiten, laden). Der
Schritt Mapping zeigt sie über dem Raster, das die Zuordnung aller übrigen Datensätze ist; beim Speichern des Rasters
bleiben sie erhalten, außer **Remove the class per record when saving** ist angekreuzt.

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
| `date` | Ein Datum oder eine Uhrzeit als Zeitstempel (ezdate, ezdatetime) | `format` (PHP-Datumsformat; `excel`: ein Tabellendatum; leer: jede Form, die `strtotime()` liest), `timezone` (Vorgabe `UTC`) |
| `date_format` | Ein Datum als Text in einem Format | `format` (Pflicht), `timezone` (Vorgabe `UTC`) |
| `slug` | Ein URL-freundlicher Bezeichner | |
| `richtext` | HTML in den Rich Text (ezxmltext) des Attributs; seine Bilder des Laufs werden eingebettete Bildobjekte | `images` (`embed`, Vorgabe, oder `drop`), `image_parent`, `image_size` (Vorgabe `medium`) |
| `lookup` | Die ID des Objekts mit dieser Remote-ID (Objektbeziehungen) | `prefix` |
| `image` | Lädt ein Bild von einer URL herunter (ezimage) | |
| `file` | Lädt eine Datei von einer URL herunter (ezbinaryfile, ezmedia) | |

Für `image` und `file` gelten dieselben Adressregeln wie für eine Quelle mit `url` ([sources.md](sources.md)). In
einem Probelauf, also auch in der Vorschau des Editors, laden sie nichts herunter: Die Adresse steht für die Datei.

**Dateien des Laufs und der Quelle.** Außer einer Adresse nehmen `image`, `file` und `media_object` auch
`source:<Pfad>` (eine Datei aus dem ZIP oder Paket des Laufs) und `run:<Pfad>`: eine Datei, die eine Quelle neben
ihren Abzug in das Verzeichnis des Laufs geschrieben hat, etwa ein Dokument, das eine entfernte Quelle geladen hat,
oder ein Bild aus einem Dokument (die Dokumentformate von ezodf). Ein `run:`-Pfad verlässt dieses Verzeichnis nie
(kein `..`, kein Link nach draußen) und ist höchstens `[SourceSettings] MaxSourceMegabytes` groß; in der Stichprobe
des Editors wird er aus dem Vorschauverzeichnis des Imports gelesen.

**Bilder im Rich Text.** `richtext` macht aus jedem `<img>`, dessen `src` `run:` oder `source:` ist, ein Bildobjekt
(einmal je Datei, wie `media_object`, unter `image_parent` oder dem Elternknoten des Imports; der `alt`-Text als
Name) und bettet es an der Stelle des Bildes ein, in der Größe `image_size`. Ein Bild, das nicht zu haben ist,
entfällt mit einem Hinweis in der Zeile. `images=drop` lässt alle weg; ein `<img>` einer anderen Adresse entfernt
der XML-Parser wie bisher. Ein Probelauf legt kein Objekt an und lässt die Bilder weg.

### Datumsangaben aus Tabellen

Eine Excel-Datei speichert ein Datum als Anzahl der Tage seit dem 30. Dezember 1899, mit der Uhrzeit als Bruchteil
(`45231.5` ist der 1. November 2023, 12:00 Uhr). `date(format=excel)` macht aus einer solchen Zahl einen
Zeitstempel; das Argument `timezone` gibt an, in welcher Zeitzone Datum und Uhrzeit gemeint sind:

```text
date(format=excel, timezone=Europe/Berlin)
```

Ein Wert, der keine Zahl ist, lässt den Datensatz mit „date: '...' is not a spreadsheet date“ fehlschlagen. Eine
ODS-Datei liefert Datumsangaben als ISO-Text (`2023-11-01`), den `date` ohne Format liest.

### Der einzeilige Text einer Kette

Das Zuordnungsraster des Editors zeigt und liest eine Kette als eine Zeile Text. Denselben Text schreibt
`expImportTransforms::formatChain()` und liest `expImportTransforms::parseChain()`:

- die Schritte werden durch einen senkrechten Strich `|` getrennt;
- ein Schritt ohne Argumente ist sein Name: `trim`;
- die Argumente folgen in Klammern als `name=wert`, durch Kommas getrennt: `truncate(length=80)`;
- ein Wert, der ein Komma, eine Klammer, einen senkrechten Strich, ein Anführungszeichen, geschweifte oder eckige
  Klammern enthält oder leer ist, wird als JSON geschrieben: ein Text in doppelten Anführungszeichen (`""` für einen
  leeren Text), eine Tabelle in geschweiften Klammern, eine Liste in eckigen Klammern.

Beispiele:

| Text | Kette |
|---|---|
| `trim` | Entfernt Leerzeichen |
| `trim \| upper` | Entfernt Leerzeichen, dann Großbuchstaben |
| `date(format=d.m.Y, timezone=Europe/Berlin)` | Liest `31.12.2025` als Mitternacht dieses Tages in Berlin |
| `date(format=excel)` | Liest ein Tabellendatum |
| `map(table={"D":"Germany","A":"Austria"}, otherwise=Other)` | Ersetzt Ländercodes durch Namen |
| `split(separator=;) \| first` | Der erste Eintrag einer durch Semikolons getrennten Liste |
| `concat(paths=["first_name","last_name"])` | Verbindet den Wert mit zwei weiteren Feldern |
| `replace(search=",", replace=.)` | Liest `12,50` als `12.50`; das Komma steht in Anführungszeichen, weil es sonst das Argument beenden würde |

Ein Text, der sich nicht lesen lässt, etwa eine unbekannte Form eines Arguments oder ungültiges JSON, wird beim
Speichern des Schritts Zuordnung an der Zeile seines Attributs gemeldet, und die Zeile behält keine Umwandlungen,
bis er korrigiert ist. Die Argumente werden dann wie bei jeder Kette geprüft.

Eine Website fügt eigene Umwandlungen als Kernel-Dienst mit dem Tag `sqliimport.transform` hinzu, der
`expImportTransformInterface` implementiert (`name()`, `label()`, `schema()`, `apply( $value, $args, $record )`).
Eine Zuordnung kann nur registrierte Umwandlungen nennen; sie enthält nie Code.

## Übersetzungen je Spalte

Eine Quelle mit einer Spalte je Sprache (`title`, `title_de`, `title_fr`) ergibt ein Objekt mit seinen Übersetzungen.
Der Schritt Zuordnung führt unter **Translations by column** jede Sprache der Site außer der eigenen Sprache der
Zuordnung auf, mit den übersetzbaren Attributen der Klasse: geben Sie jedem die Spalte dieser Sprache (und
Umwandlungen wie im Raster). Eine Zeile behält ihre Sprache in der Zuordnung (`"language": "ger-DE"`); der Lauf
veröffentlicht das Objekt mit einer Übersetzung je Sprache, die einen Wert hat. Eine geleerte Quelle entfernt die
Zeile. Nicht übersetzbare Attribute übernehmen den Wert der Hauptsprache.

## Einen Import in PHP anlegen

Der übliche Weg, einen Import anzulegen, ist der Import-Editor ([editor.md](editor.md)). Ein Skript, das Importe
einrichtet, etwa bei der Installation einer Website, kann einen Import in PHP anlegen und mit
`php bin/php/ezexec.php <skript> --allow-root-user` ausgeführt werden:

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
