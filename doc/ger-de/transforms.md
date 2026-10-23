# Umwandlungen: jede eingebaute Umwandlung mit einem Beispiel

Eine Umwandlung (Transform) verändert einen Wert auf seinem Weg von der Quelle zu einem Attribut: Sie kürzt einen
Namen, liest ein Datum, macht aus HTML Rich Text oder lädt ein Bild herunter. Im Schritt Zuordnung des Import-Editors
hat jede Zeile des Rasters ein Feld **Transforms**, das eine Kette von Umwandlungen als eine Textzeile nimmt, zum
Beispiel `trim | upper`. Diese Seite zeigt jede eingebaute Umwandlung mit einer echten Eingabe und der Ausgabe, die
sie ergibt.

English: [../transforms.md](../transforms.md).

## Inhalt

- [Wie eine Kette geschrieben wird](#wie-eine-kette-geschrieben-wird)
- [Text](#text): `trim`, `lower`, `upper`, `title`, `strip_tags`, `prefix`, `suffix`, `replace`, `regex_replace`,
  `truncate`, `default`, `slug`
- [Werte aus einer Tabelle](#werte-aus-einer-tabelle): `map`
- [Listen](#listen): `split`, `join`, `first`
- [Mehrere Felder](#mehrere-felder): `concat`, `template`
- [Zahlen und Ja oder Nein](#zahlen-und-ja-oder-nein): `number`, `int`, `bool`
- [Datumsangaben](#datumsangaben): `date`, `date_format`
- [Rich Text](#rich-text): `richtext`
- [Verwandte Objekte](#verwandte-objekte): `lookup`
- [Dateien und Bilder](#dateien-und-bilder): `image`, `file`, `media_object`
- [Datentypen, die einen besonderen Text brauchen](#datentypen-die-einen-besonderen-text-brauchen): Preise,
  Schlagwörter, Kontrollkästchen, Datumsangaben
- [Eine eigene Umwandlung](#eine-eigene-umwandlung)

## Wie eine Kette geschrieben wird

- Die Schritte einer Kette werden durch einen senkrechten Strich getrennt: `trim | upper` kürzt zuerst und schreibt
  dann in Großbuchstaben.
- Ein Schritt ohne Argumente ist nur sein Name: `trim`.
- Argumente folgen in Klammern als `name=wert`, durch Kommas getrennt: `truncate(length=80)`.
- Ein Wert, der ein Komma, eine Klammer, einen senkrechten Strich, ein Anführungszeichen, geschweifte oder eckige
  Klammern enthält oder leer ist, wird als JSON geschrieben: `suffix(text=" g")`, `replace(search=",", replace=.)`,
  `map(table={"A":"Austria"})`.
- Eine Umwandlung, die ihre Arbeit nicht tun kann (ein Datum, das kein Datum ist, ein Lookup, das nichts findet),
  lässt den Datensatz mit ihrer Meldung fehlschlagen. Die Seite des Laufs zeigt die Meldung in der Zeile des
  Datensatzes.

Die Tabelle unter dem Raster des Schritts Zuordnung, **The transforms**, listet jede Umwandlung mit ihren Argumenten,
sodass Sie sie sich nicht merken müssen. Die Regeln des einzeiligen Texts stehen in
[mappings.md](mappings.md#der-einzeilige-text-einer-kette).

Jedes Beispiel auf dieser Seite lief auf einer echten Installation: Der Text in der ersten Spalte wurde vom Parser des
Editors gelesen und auf die Eingabe in der zweiten Spalte angewandt. Der Datensatz der Beispiele hat die Felder
`title=Dune`, `year=1965`, `first_name=Frank`, `last_name=Herbert`.

## Text

| Text der Umwandlung | Eingabe | Ausgabe |
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
| `default(value="No description yet")` | (leer) | `No description yet` |
| `slug` | `Earl Grey: Blue Flower!` | `earl-grey-blue-flower` |

Wann Sie sie brauchen:

- **`trim`** bei fast jeder Textspalte einer Tabelle: Zellen enden oft mit einem Leerzeichen, das niemand sieht.
- **`strip_tags`** für einen Feed-Titel, der HTML enthält, bevor er in ein reines Textattribut geht.
- **`regex_replace`** nimmt einen regulären Ausdruck von PHP mit seinen Begrenzern (`/.../`). Ein leeres `replace`
  entfernt, was passt.
- **`default`** entspricht der Spalte **Default** des Rasters, aber innerhalb einer Kette: `default(value=Tea) | upper`
  ergibt `TEA` für eine leere Zelle.
- **`slug`** bildet aus einem Namen einen Bezeichner, etwa für einen Teil einer Remote-ID oder ein URL-taugliches
  Attribut.

## Werte aus einer Tabelle

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `map(table={"D":"Germany","A":"Austria"}, otherwise=Other)` | `A` | `Austria` |
| dieselbe | `CH` | `Other` |
| `map(table={"1":"In stock","0":"Sold out"})` | `5` | `5` (ohne `otherwise` bleibt der Wert erhalten) |

`map` ist die Umwandlung für Codes: Ländercodes, Statusnummern, Kategorieschlüssel eines anderen Systems.

## Listen

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `split(separator=;)` | `green;black;white` | die Liste `green`, `black`, `white` |
| `split(separator=;) \| join(separator=" / ")` | `green;black;white` | `green / black / white` |
| `split(separator=;) \| first` | `green;black;white` | `green` |

Eine Liste, die ein Attribut erreicht, wird mit `|` zwischen den Einträgen geschrieben; das ist die Textform von
Objektbeziehungslisten und ähnlichen Datentypen. Für ein Schlagwort-Attribut (`ezkeyword`) verbinden Sie die Liste
mit Kommas: `split(separator=;) | join(separator=", ")`.

## Mehrere Felder

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `concat(paths=["last_name"])` | `Frank` (das Quellfeld der Zeile ist `first_name`) | `Frank Herbert` |
| `template(text="{title} ({year})")` | (beliebig) | `Dune (1965)` |

- **`concat`** verbindet den aktuellen Wert mit den in `paths` genannten Feldern, mit einem Leerzeichen (oder
  `separator`).
- **`template`** baut einen Text aus beliebigen Feldern des Datensatzes in geschweiften Klammern. `{value}` ist der
  aktuelle Wert der Kette, also schreibt `number | template(text="{value}|1|1")` einen Preis so, wie `ezprice` ihn
  liest (siehe unten).

## Zahlen und Ja oder Nein

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `number` | `1.234,56` | `1234.56` |
| `number(decimals=2)` | `6.9` | `6.90` |
| `int` | `41.6` | `42` |
| `bool` | `yes` | `1` |
| `bool` | `nein` | `0` |

`number` liest die deutsche (`1.234,56`) und die englische (`1,234.56`) Schreibweise einer Zahl. `bool` ergibt `1`
für 1, true, yes, y, on, ja, oui und x (in beliebiger Schreibung), sonst `0`: den Wert, den ein
Kontrollkästchen-Attribut (`ezboolean`) nimmt.

## Datumsangaben

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `date(format=d.m.Y, timezone=Europe/Berlin)` | `12.04.2026` | `1775944800` (Mitternacht am 12. April 2026 in Berlin) |
| `date` | `2026-04-12 08:30` | `1775982600` (jede Form, die `strtotime()` von PHP liest, in UTC) |
| `date(format=excel)` | `45231.5` | `1698840000` (1. November 2023, 12:00, ein Tabellendatum) |
| `date_format(format=Y-m-d)` | `1775980800` | `2026-04-12` |

- **`date`** ergibt einen Zeitstempel; den nehmen Datums- und Datum-Uhrzeit-Attribute (`ezdate`, `ezdatetime`).
  Geben Sie `format` an, wenn die Quelle Datumsangaben in einer festen Form schreibt, die `strtotime()` falsch lesen
  könnte, etwa `04/05/2026`.
- **`date(format=excel)`** ist für XLSX-Dateien, die Datumsangaben als Tage seit dem 30. Dezember 1899 speichern
  ([mappings.md](mappings.md#datumsangaben-aus-tabellen)).
- **`date_format`** schreibt ein Datum als Text, für ein Textattribut oder eine Remote-ID: `date_format(format=Y-m)`.

## Rich Text

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `richtext` | `<p>A <b>strong</b> tea.</p>` | der Rich Text `<paragraph>A <strong>strong</strong> tea.</paragraph>` (XML-Text von `ezxmltext`) |

`richtext` ist für jedes Rich-Text-Attribut nötig (Haupttext, Einleitung, Beschreibung), auch wenn die Quelle reiner
Text ist. Bilder im HTML, deren Adresse eine Datei des Laufs ist (`run:` oder `source:`, wie die Dokument- und
Markdown-Quellen sie liefern), werden zu Bildobjekten, die in den Text eingebettet sind. Die Argumente:

| Argument | Bedeutung |
|---|---|
| `images` | `embed` (Vorgabe) oder `drop` (jedes Bild weglassen) |
| `image_parent` | der Knoten, unter dem die Bildobjekte angelegt werden (Vorgabe: der Elternknoten des Imports) |
| `image_size` | der Bild-Alias der Einbettungen: `small`, `medium` (Vorgabe), `large`, `original` |

Beispiel: `richtext(images=embed, image_size=large)`. Ein durchgerechnetes Beispiel mit Bildern ist der
Markdown-Ordner in [source-examples-files.md](source-examples-files.md).

## Verwandte Objekte

| Text der Umwandlung | Eingabe | Ausgabe |
|---|---|---|
| `lookup(prefix=docs-tea-)` | `101` | die Objekt-ID des Objekts mit der Remote-ID `docs-tea-101` |

`lookup` füllt eine Objektbeziehung (`ezobjectrelation`, `ezobjectrelationlist`): Die Quelle liefert einen Schlüssel
des verwandten Objekts, und der Import, der dieses Objekt angelegt hat, hat dessen Remote-ID aus demselben Schlüssel
gebildet. Importieren Sie die verwandten Objekte zuerst (ein anderer Import oder frühere Datensätze derselben
Quelle). Ein Schlüssel, der kein Objekt findet, lässt den Datensatz mit `lookup: no object with the remote ID '...'`
fehlschlagen; bringen Sie die Reihenfolge in Ordnung und nutzen Sie **Retry failed rows**
([runs.md](runs.md#abbrechen-und-wiederholen)).

Für eine Liste von Schlüsseln: `split(separator=,) | lookup(prefix=docs-tea-)` (jeder Eintrag wird nachgeschlagen).

## Dateien und Bilder

| Umwandlung | Attribut, das sie füllt | Was sie tut |
|---|---|---|
| `image` | ein Bildattribut (`ezimage`) | lädt das Bild von der Adresse im Feld herunter und speichert es im Attribut |
| `file` | ein Datei- oder Medienattribut (`ezbinaryfile`, `ezmedia`) | lädt die Datei herunter und speichert sie im Attribut |
| `media_object` | eine Objektbeziehung zu Bildern oder Dateien | legt für die Adresse ein Bild- (oder Datei-)Objekt an, einmal pro Datei, und liefert seine ID |

Alle drei nehmen eine `http(s)`-Adresse (mit denselben Regeln wie eine Adressquelle: keine privaten Netze, außer
erlaubt), `source:<pfad>` (eine Datei im ZIP oder Paket des Laufs) oder `run:<pfad>` (eine Datei, die eine Quelle in
das Verzeichnis des Laufs geschrieben hat). In einem Probelauf und in der Vorschau des Editors wird nichts
heruntergeladen: Die Adresse steht für die Datei.

`image(required=true)` lässt den Datensatz fehlschlagen, wenn das Bild nicht zu bekommen ist; ohne das Argument wird
der Datensatz ohne Bild importiert, mit einem Hinweis in seiner Zeile.

`media_object` ist die richtige Wahl für die Klasse `product` des Webshops, deren `image` eine Objektbeziehung ist:

```text
media_object(parent=54990, class=image, attribute=image, name=title)
```

Sie legt pro Adresse ein Bildobjekt unter Knoten 54990 an, benannt nach dem `title` des Datensatzes, und dieselbe
Adresse in einem späteren Lauf (oder in einem anderen Datensatz) nutzt dieses Objekt wieder, statt ein neues
anzulegen. Das Medien-ZIP und die WordPress-Beispiele in [source-examples-files.md](source-examples-files.md) zeigen
es mit echten Dateien.

## Datentypen, die einen besonderen Text brauchen

Manche Attribute lesen ihren Wert aus einem Text in fester Form. Die Umwandlungen oben bauen ihn:

| Datentyp | Text, den er liest | Kette |
|---|---|---|
| Preis (`ezprice`) | `Betrag\|MwSt.-Typ\|Preis enthält MwSt.`, z. B. `6.95\|1\|1` | `number \| template(text="{value}\|1\|1")` |
| Schlagwörter (`ezkeyword`) | durch Kommas getrennte Wörter | `split(separator=;) \| join(separator=", ")` |
| Kontrollkästchen (`ezboolean`) | `1` oder `0` | `bool` |
| Datum, Datum und Uhrzeit (`ezdate`, `ezdatetime`) | ein Zeitstempel | `date` oder `date(format=...)` |
| Objektbeziehung (Liste) | Objekt-IDs, durch `\|` getrennt | `lookup(prefix=...)` oder `media_object` |
| Rich Text (`ezxmltext`) | das XML des Rich Texts | `richtext` |

Ein Preis ohne den Teil `|MwSt.-Typ|enthalten` erhält den vorgegebenen MwSt.-Typ und die Einstellung „Preis enthält
MwSt.“ des Klassenattributs (vor 2.0.0 wurde er als 0 gespeichert). Für andere Werte nehmen Sie die Vorlage oben und
ändern den MwSt.-Typ `1` und die letzte `1` (`0`: der Preis enthält keine MwSt.) auf das, was Ihr Shop verwendet. Siehe auch [troubleshooting.md](troubleshooting.md#preise-sind-0-oder-jeder-lauf-aktualisiert-jeden-preis).

## Eine eigene Umwandlung

Eine Website fügt eine Umwandlung als Kernel-Dienst mit dem Tag `sqliimport.transform` hinzu. Die Klasse implementiert
`expImportTransformInterface`; der Entwicklerteil von
[handlers.md](handlers.md#eine-eigene-umwandlung-schreiben) zeigt eine vollständige. Eine Zuordnung kann nur
registrierte Umwandlungen nennen und trägt nie Code.
