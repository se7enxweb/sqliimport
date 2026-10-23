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

## Durchgespielte Beispiele

Jedes Beispiel unten lief auf einer Testinstallation: ein Import einer kleinen Datei, unterhalb eines versteckten
Ordners, mit einem Probelauf oder einem kleinen echten Lauf. Dateien, Laufnummern, Objekt-IDs und Ausgaben sind echt.
Jedes Beispiel zeigt die Eingabe, die Zuordnung (wie der Schritt Zuordnung sie speichert), was der Lauf meldete und
was veröffentlicht wurde.

- [Muster der Remote-ID](#muster-der-remote-id)
- [Zuordnen über den Wert eines Attributs](#zuordnen-über-den-wert-eines-attributs)
- [Elternobjekt aus einem Feld und weitere Platzierungen](#elternobjekt-aus-einem-feld-und-weitere-platzierungen)
- [Eine Klasse je Datensatz](#eine-klasse-je-datensatz-durchgespielt)
- [Übersetzungen je Spalte](#übersetzungen-je-spalte-durchgespielt)
- [Bilder und verknüpfte Bildobjekte](#bilder-und-verknüpfte-bildobjekte)

### Muster der Remote-ID

Ein Muster setzt Felder des Datensatzes in geschweifte Klammern: `{sku}`. Mehrere Felder sind erlaubt; `{_ref}` ist
die eigene Referenz des Datensatzes (sein Schlüsselfeld, sonst seine Position `#3`). Eine Remote-ID mit mehr als 100
Zeichen behält ihre ersten 60 Zeichen und bekommt einen Bindestrich und den MD5-Hash der ganzen ID, damit sie
eindeutig bleibt. Was der Mapper aus drei Datensätzen machte (der erste mit dem Schlüsselfeld `sku`, der zweite ohne
`sku`, der dritte mit einem langen Namen):

```
tea-{sku}                  with key sku   => tea-TEA-SEN-100 (15 chars)
tea-{sku}                  without key    => error: the remote ID needs sku, which the record does not have
docs-map-{origin}-{sku}    with key sku   => docs-map-Japan-TEA-SEN-100 (26 chars)
docs-map-{origin}-{sku}    long name      => docs-map-Taiwan-TEA-LNG-001 (27 chars)
tea-{_ref}                 with key sku   => tea-TEA-SEN-100 (15 chars)
tea-{_ref}                 without key    => tea-#3 (6 chars)
tea-{name}                 with key sku   => tea-Morning Sencha (18 chars)
tea-{name}                 long name      => tea-Long product name Long product name Long product name Lo-bb44102a90a3656d20d9f147a84f3b95 (93 chars)
```

- Nehmen Sie lieber ein Feld, das sich nie ändert (eine ID, eine SKU), als einen Namen: Ändert sich der Name, legt
  `tea-{name}` ein neues Objekt an.
- `{_ref}` benennt einen Datensatz ohne Schlüssel nach seiner Position. Das ist nur stabil, solange die Reihenfolge der
  Quelle gleich bleibt.

Der Import „Docs: remote ID from two fields“ (#146) nutzt `docs-map-{origin}-{sku}` auf dieser CSV:

```
sku,origin,name
TEA-SEN-100,Japan,Morning Sencha
TEA-HBL-100,India,Highland Black
,China,Silver Needle
```

Sein Probelauf #217: 2 würden angelegt (`docs-map-Japan-TEA-SEN-100`, `docs-map-India-TEA-HBL-100`), und Datensatz
`#3` schlug mit „the remote ID needs sku, which the record does not have“ fehl:

![Der Probelauf der Remote-ID aus zwei Feldern: zwei Datensätze würden angelegt, der dritte schlug fehl, weil er keine sku hat](../images/map-remote-id-two-fields-dry-run.png)

### Zuordnen über den Wert eines Attributs

Hat das andere System keine eigene stabile ID, tragen die Objekte aber schon eine (eine Produktnummer, eine ISBN),
ordnen Sie über dieses Attribut statt über eine Remote-ID zu. Der Import „Docs: match products by product number“
(#147), Klasse `product`:

```
product_number,name,price
DOCS-MAP-1,Tea caddy,"14.5|1|1"
DOCS-MAP-2,Tea strainer,"4.2|1|1"
```

| Einstellung | Wert |
|---|---|
| How a record finds its object | **By the value of an attribute** |
| Attribute and source field to match on | `product_number`, `product_number` |
| Zeilen | `name` nach `name` (Pflicht), `product_number` nach `product_number`, `price` nach `price` |

Gespeichert: `"match": {"type": "attribute", "attribute": "product_number", "source": "product_number"}`.

![Der Schritt Zuordnung des Imports, der über ein Attribut zuordnet: Klasse product und die Wahl By the value of an attribute](../images/map-match-by-attribute-mapping.png)

Der echte Lauf #218 legte die beiden Produkte an (Objekte 56648 und 56649). Ein neues Objekt bekommt eine erzeugte
Remote-ID (`9ded5562b529a52f926696fafa3e5460`); wiedergefunden wird es über seine Produktnummer. Der Probelauf #225
danach fand beide und meldete sie unverändert:

![Der Probelauf nach dem ersten Lauf: beide Datensätze unverändert, jedes Objekt über seine Produktnummer gefunden](../images/map-match-by-attribute-dry-run.png)

Ein Datensatz mit leerem Zuordnungsfeld schlägt mit „the match field 'product_number' is empty“ fehl. Das Zuordnen
über ein Attribut sucht den Wert unter den Objekten der Klasse, was bei großen Klassen langsamer sein kann als eine
Remote-ID; nehmen Sie eine Remote-ID, wenn es geht.

### Elternobjekt aus einem Feld und weitere Platzierungen

`parent_from` nennt ein Feld mit der **Remote-ID des Elternobjekts** (mit `parent_prefix` davor);
`extra_parents_from` nennt ein Feld mit weiteren Elternobjekten (eine Liste oder ein Text mit Kommas), die zu
weiteren Platzierungen eines neuen Objekts werden. Ein Datensatz ohne Elternobjekt kommt unter den Elternknoten des
Imports. Der Import „Docs: parents and more locations from fields“ (#148), Klasse `folder`, Remote-IDs
`docs-map-tree-{id}`:

```
id,title,parent,also_in
shelf-a,Shelf A,,
shelf-b,Shelf B,,
tea-1,Sencha,shelf-a,shelf-b
tea-2,Assam,shelf-b,
```

| Einstellung | Wert |
|---|---|
| Parent from a field (its remote ID) | `parent`, Präfix `docs-map-tree-` |
| More locations from a field | `also_in` |

Gespeichert: `"location": {"parent_from": "parent", "parent_prefix": "docs-map-tree-", "extra_parents_from": "also_in", "parent_node_id": 55028}`.

![Der Schritt Zuordnung mit dem Elternobjekt aus dem Feld parent, dem Präfix docs-map-tree- und weiteren Platzierungen aus dem Feld also_in](../images/map-location-from-fields-mapping.png)

Der echte Lauf #219 legte 4 Ordner an, die Regale zuerst, damit es sie gibt, wenn die Tees sie brauchen:

![Der Lauf des Baum-Imports: vier Ordner angelegt, Shelf A, Shelf B, Sencha und Assam](../images/map-location-from-fields-run.png)

Was veröffentlicht wurde:

```
docs-map-tree-shelf-a: object 56651, class folder, "Shelf A"; node 55029 (main) below "Map: tree"
docs-map-tree-shelf-b: object 56652, class folder, "Shelf B"; node 55030 (main) below "Map: tree"
docs-map-tree-tea-1: object 56653, class folder, "Sencha"; node 55031 (main) below "Shelf A", node 55046 below "Shelf B"
docs-map-tree-tea-2: object 56654, class folder, "Assam"; node 55032 (main) below "Shelf B"
```

Sencha hat eine zweite Platzierung unter Shelf B, aus `also_in`. Die weiteren Platzierungen werden gleich nach dem
Veröffentlichen hinzugefügt, bei einem neuen und bei einem schon vorhandenen Objekt, und ein Lauf, der nur eine
Platzierung hinzufügt, zählt das Objekt als aktualisiert („1 more location(s) added“ in seiner Zeile). Sie werden als
der Import hinzugefügt, wer auch immer den Lauf startet (auch ein Lauf der Kommandozeile, der keinen Benutzer hat).
Rückgängig entfernt die Platzierungen, die ein Lauf hinzugefügt hat; die Hauptplatzierung bleibt. Lauf #219 dieses
Beispiels entstand, bevor das behoben war, und gab Sencha keine zweite Platzierung; der spätere Lauf #264 fügte sie
hinzu (Knoten 55046).

<a id="eine-klasse-je-datensatz-durchgespielt"></a>

### Eine Klasse je Datensatz

Eine Quelle, mehrere Arten von Datensätzen. Das NDJSON des Imports „Docs: a class per record“ (#149):

```json
{"id":"sec-1","kind":"folder","title":"Green teas"}
{"id":"art-1","kind":"article","title":"Brewing Sencha","parent":"sec-1","intro":"<p>Use water at 70 to 80 degrees.</p>"}
{"id":"note-1","kind":"note","title":"Reorder in May","parent":"sec-1"}
```

Die eigene Klasse der Zuordnung ist `article` (Zeilen `title` nach `title`, `intro` nach `intro` mit `richtext`),
Remote-IDs `docs-map-mixed-{id}`, Elternobjekt aus `parent` mit dem Präfix `docs-map-mixed-`. Ihre Klasse je
Datensatz:

```json
"class_from": "kind",
"variants": {
  "folder": { "class": "folder", "fields": [ { "source": "title", "attribute": "name", "required": true } ] },
  "note":   { "skip": true }
}
```

Ein Datensatz der Art `folder` wird ein Ordner mit seiner eigenen Zeile; `note` wird weggelassen; jede andere Art
(hier `article`) nutzt Klasse und Zeilen des Rasters. Der Schritt Zuordnung sagt das über dem Raster:

![Der Schritt Zuordnung der Klasse je Datensatz: ein Hinweis, dass Datensätze ihre Klasse über das Feld kind wählen, folder nach folder, note nicht importiert](../images/map-class-per-record-mapping.png)

Der echte Lauf #220: 2 angelegt, 1 übersprungen („not imported: its kind is note“):

![Der Lauf der Klasse je Datensatz: Ordner und Artikel angelegt, die Notiz als nicht importiert übersprungen](../images/map-class-per-record-run.png)

```
docs-map-mixed-sec-1: object 56656, class folder, "Green teas"; node 55034 (main) below "Map: class per record"
docs-map-mixed-art-1: object 56657, class article, "Brewing Sencha"; node 55035 (main) below "Green teas"
docs-map-mixed-note-1: missing
```

Die Varianten werden in einer Zuordnungsdatei gesetzt (im Schritt Zuordnung herunterladen, bearbeiten, laden); das
Raster bearbeitet die Zeilen der eigenen Klasse der Zuordnung.

<a id="übersetzungen-je-spalte-durchgespielt"></a>

### Übersetzungen je Spalte

Eine Quelle mit einer Spalte je Sprache ergibt ein Objekt mit seinen Übersetzungen. Der Import „Docs: translations by
column“ (#150), Klasse `article`:

```
id,title,title_de,intro,intro_de
t-1,Morning Sencha,Morgen-Sencha,"<p>A fresh green tea.</p>","<p>Ein frischer gruener Tee.</p>"
```

Das Raster ordnet `title` und `intro` zu (die Hauptsprache); unter **Translations by column** ordnet der Abschnitt
**German (ger-DE)** `title_de` nach `title` und `intro_de` nach `intro` (mit `richtext`) zu:

![Der Abschnitt Translations by column des Schritts Zuordnung: German ger-DE mit title_de und intro_de nach title und intro](../images/map-translations-by-column.png)

Gespeichert tragen die beiden Zeilen ihre Sprache:

```json
{ "source": "title_de", "attribute": "title", "language": "ger-DE" },
{ "source": "intro_de", "attribute": "intro", "language": "ger-DE", "transforms": [ { "name": "richtext" } ] }
```

Der echte Lauf #221 legte einen Artikel mit beiden Sprachen an:

```
docs-map-tr-t-1: object 56659, class article, "Morning Sencha", languages eng-US,ger-DE
  eng-US: title "Morning Sencha", intro "A fresh green tea."
  ger-DE: title "Morgen-Sencha", intro "Ein frischer gruener Tee."
```

![Der Reiter Translations des Artikels: Englisch und Deutsch](../images/map-translations-tab.png)

Ein Datensatz mit leerem `title_de` bekommt keine deutsche Übersetzung; ein nicht übersetzbares Attribut nimmt den
Wert der Hauptsprache.

### Bilder und verknüpfte Bildobjekte

**Ein Bildattribut (`image`).** Der Import „Docs: images into an image attribute“ (#151) legt Objekte der Klasse
`image` an; die Zeile `image_url` nach `image` mit `image(required=true)` lädt jede Adresse in das `ezimage`-Attribut:

```
id,title,image_url
img-1,Illustration,https://alpha.se7enx.com/design/mysite/images/mysite-illustration.jpg
img-2,Front page illustration,https://alpha.se7enx.com/design/mysite/images/mysite-illustration-frontpage.jpg
```

Der echte Lauf #222 legte beide an, mit gespeicherten Bildern:

```
docs-map-img-img-1: object 56661, class image, "Illustration"
  image: illustration.jpg, 700x55, image/jpeg, 11433 bytes
docs-map-img-img-2: object 56662, class image, "Front page illustration"
  image: front-page-illustration.jpg, 700x125, image/jpeg, 24745 bytes
```

![Der Lauf des Bild-Imports: zwei Bildobjekte angelegt](../images/map-image-run.png)

**Eine Objektrelation (`media_object`).** Das `product` des Webshops hält sein Bild als Relation zu einem Bildobjekt.
`media_object` legt je Adresse ein Bildobjekt an und gibt seine ID; dieselbe Adresse später, im selben Lauf oder im
nächsten, nutzt dieses Objekt wieder. Der Import „Docs: product images as related image objects“ (#152), mit zwei
Produkten, die sich ein Bild teilen:

```
product_number,name,image_url
DOCS-MAP-IMG-1,Gift box,https://alpha.se7enx.com/design/mysite/images/mysite-illustration.jpg
DOCS-MAP-IMG-2,Gift box large,https://alpha.se7enx.com/design/mysite/images/mysite-illustration.jpg
```

Die Zeile: `image_url` nach `image` mit `media_object(class=image, attribute=image, name=name)` (das Bildobjekt heißt
nach dem `name` des Datensatzes und liegt unter dem Elternknoten des Imports). Der echte Lauf #223 legte die beiden
Produkte und **ein** Bildobjekt an, auf das beide verweisen:

```
docs-map-prod-DOCS-MAP-IMG-1: object 56665, class product, "Gift box"
  image relation: 56664
    object 56664, class image, "Gift box", remote ID sqliimport-media-f6f5c1dacc0515c39afcdedb143ce1e55df1bd9a
docs-map-prod-DOCS-MAP-IMG-2: object 56666, class product, "Gift box large"
  image relation: 56664
```

Die Remote-ID des Bildobjekts entsteht aus einem Hash der Adresse (`sqliimport-media-...`), darüber wird es
wiedergefunden. Der zweite Lauf #224 derselben Datei: 2 unverändert, kein neues Bildobjekt:

![Der zweite Lauf des media_object-Imports: beide Produkte unverändert, kein neues Bildobjekt angelegt](../images/map-media-object-second-run.png)

In einem Probelauf und in der Vorschau des Editors laden `image` und `media_object` nichts herunter: die Adresse
steht für die Datei. Adressen folgen den Regeln einer Adressquelle (keine privaten Netze, außer erlaubt).

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
