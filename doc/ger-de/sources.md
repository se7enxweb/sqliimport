# Quellen

Ein Quelltyp liest Datensätze aus einer lokalen Datei. Woher die Datei kommt, ist der `origin` in der
`source_config` des Imports. Die Engine macht daraus einmal pro Lauf eine lokale Datei, sodass jeder Abschnitt des
Laufs dieselben Daten liest.

## Woher die Daten kommen

| `origin` | Konfiguration | Was geschieht |
|---|---|---|
| `upload` | `file`: der gespeicherte Name einer hochgeladenen Datei | Der Upload (im Speicherverzeichnis der Website unter `sqliimport/uploads/`) wird in das Verzeichnis des Laufs kopiert |
| `url` | `url`: eine Adresse mit `http` oder `https` | Die Adresse wird in das Verzeichnis des Laufs heruntergeladen, mit dem Zeitlimit `[ImportSettings] StreamTimeout` und dem Größenlimit `[SourceSettings] MaxSourceMegabytes` |
| `path` | `path`: eine Datei auf dem Server | Die Datei wird an Ort und Stelle gelesen, aber nur, wenn sie in einem der Verzeichnisse von `[SourceSettings] AllowedPaths[]` liegt |

Regeln:

- **Adressen privater oder reservierter Netze werden abgelehnt** (Intranet-Adressen, Loopback, Link-Local), ebenso
  Hostnamen, die auf solche Adressen auflösen. Setzen Sie `[SourceSettings] AllowPrivateHosts=enabled` nur, wenn ein
  Import einen Export aus dem Intranet lesen muss. Nur `http` und `https` werden angenommen.
- **Ein Serverpfad muss in einem erlaubten Verzeichnis liegen.** `AllowedPaths[]` ist anfangs leer; bis Sie ein
  Verzeichnis eintragen, kann keine Serverdatei gelesen werden. Relative Pfade gelten relativ zur Installation.
- Eine über `path` gelesene Datei wird nicht kopiert. Ändern Sie sie nicht, während ein Lauf läuft.
- Momentaufnahmen und heruntergeladene Bilder liegen im Verzeichnis des Laufs (`sqliimport/runs/<Lauf-ID>/` im
  Speicherverzeichnis der Website) und werden zusammen mit dem Lauf nach `[RunSettings] KeepDays` entfernt.

## Gemeinsame Option

Jeder Quelltyp außer RSS hat die Option `key`: das Feld, das einen Datensatz benennt (eine ID-Spalte, eine GUID).
Ohne Schlüssel wird ein Datensatz nach seiner Position benannt (`#1`, `#2`, ...). Der Schlüssel ist der Verweis des
Datensatzes in den Zeilen und im Protokoll des Laufs.

Der Schlüssel muss innerhalb der Quelle eindeutig sein. Ein Lauf verarbeitet jeden Verweis einmal, ein zweiter
Datensatz mit demselben Schlüssel wird also nicht verarbeitet.

## CSV, TSV und Text mit Trennzeichen (`csv`, `tsv`)

Dateiendungen: `csv`, `tsv`, `txt`. Die Datei wird als Datenstrom gelesen, ein Datensatz pro Zeile (ein Feld in
Anführungszeichen darf über mehrere Zeilen gehen). Die Felder jedes Datensatzes sind nach der Kopfzeile benannt.

| Option | Werte | Vorgabe |
|---|---|---|
| `delimiter` | `auto`, `,` (Komma), `;` (Semikolon), `tab` oder der senkrechte Strich | `auto` (aus der ersten Zeile erkannt) |
| `enclosure` | ein Zeichen | `"` |
| `encoding` | `auto`, `UTF-8`, `WINDOWS-1252`, `ISO-8859-1`, `ISO-8859-15` | `auto` (UTF-8, sonst Windows-1252) |
| `header` | die erste Zeile benennt die Spalten | `true` |
| `key` | die Schlüsselspalte | leer |

Leere oder doppelte Spaltennamen werden eindeutig gemacht (`col_3`). Eine Byte-Order-Mark wird entfernt. Ohne
Kopfzeile heißen die Spalten `0`, `1`, `2` und so weiter.

## XML (`xml`)

Dateiendung: `xml`. Die Datei wird mit XMLReader als Datenstrom gelesen. Externe Entitäten werden nie geladen.

| Option | Bedeutung |
|---|---|
| `record_path` | Pflicht. Das Element eines Datensatzes: `book` oder `catalog/book`. Es passt auf das Ende des Pfads eines Elements, `book` findet also jedes `book`. Elemente mit Namensraum werden mit ihrem Präfix genannt (`dc:creator`). |
| `xsd` | Optional. Eine XSD-Datei, gegen die die Quelle geprüft wird |
| `key` | Das Schlüsselfeld |

Ein Element wird zu einem Array: Attribute als `@name`, Kindelemente unter ihrem Namen, wiederholte Kindelemente als
Liste und der Text eines Elements, das auch Attribute oder Kinder hat, als `#text`. Beispiel: In
`<book id="7"><title>Dune</title></book>` lauten die Pfade `@id` und `title`.

## RSS / Atom (`rss`)

Dateiendungen: `xml`, `rss`, `atom`. RSS 2.0 und Atom werden an der Datei erkannt. Jeder Eintrag ist ein Datensatz
mit denselben Feldern in beiden Formaten, sodass eine Zuordnung für beide passt:

| Feld | Aus |
|---|---|
| `id` | `guid` (RSS) oder `id` (Atom) |
| `title`, `link` | Titel und Link |
| `summary` | `description` oder `summary` |
| `content` | `content:encoded` oder `content` |
| `date` | `pubDate`, `updated` oder `published`, wie angegeben |
| `author` | der Autor |
| `categories` | eine Liste |
| `enclosure` | die URL des ersten Anhangs oder von `media:content` (für die Umwandlung `image`) |
| `raw.*` | das unveränderte Element |

Die einzige Option ist `key` (Vorgabe `id`). Diese Quelle ersetzt den RSS-Beispiel-Handler von 1.x
(`SQLIRSSImportHandler`), der aus Kompatibilitätsgründen bleibt.

## JSON und JSON Lines (`json`, `ndjson`)

Dateiendungen: `json`, `ndjson`, `jsonl`.

| Option | Werte | Vorgabe |
|---|---|---|
| `format` | `auto`, `json` (eine Liste von Objekten), `ndjson` (ein Objekt pro Zeile) | `auto` |
| `record_path` | Nur JSON: der Pfad der Liste der Datensätze, etwa `data.items`; leer: die oberste Ebene | leer |
| `key` | das Schlüsselfeld | leer |

Eine JSON-Datei wird ganz gelesen. NDJSON wird als Datenstrom gelesen; liefern Sie sehr große Exporte daher als
NDJSON.

## Tabellen (`spreadsheet`)

Dateiendungen: `xlsx` (Excel) und `ods` (OpenDocument, wie LibreOffice speichert). Das Format wird aus der Datei
erkannt. Beide sind ZIP-Dateien aus XML, die die Quelle als Datenstrom liest, ohne Bibliothek für Tabellen. Sie
braucht die PHP-Erweiterung `zip` (`ext-zip`); ohne sie schlägt das Lesen der Datei mit der Meldung „reading
spreadsheets needs the PHP extension zip“ fehl.

| Option | Werte | Vorgabe |
|---|---|---|
| `sheet` | Der Name des Blatts oder seine Nummer (`1` ist das erste) | `1` |
| `header` | Die erste Zeile benennt die Spalten | `true` |
| `key` | Die Schlüsselspalte | leer |

Ein Datensatz pro Zeile des gewählten Blatts, mit den Namen aus der Kopfzeile als Schlüsseln. Zeilen ganz ohne Wert
entfallen, auch vor der Kopfzeile. Leere oder doppelte Spaltennamen werden eindeutig gemacht (`col_3`, `price_2`).
Ohne Kopfzeile heißen die Spalten `0`, `1`, `2` und so weiter. Ein Blatt, das es nicht gibt, lässt den Lauf
fehlschlagen; bei einer XLSX-Datei nennt die Meldung die Blätter der Datei, und die Vorschau des Editors listet sie
ebenfalls auf.

Die Werte kommen so, wie sie in der Datei gespeichert sind:

- Text als Text, und von einer Formel der zuletzt berechnete Wert;
- Zahlen wie gespeichert, ohne das Anzeigeformat der Zelle (`0.5`, nicht `50 %`);
- ein **Excel-Datum** ist eine Anzahl von Tagen (`45231` ist der 1. November 2023; ein Bruchteil ist die Uhrzeit).
  Die Umwandlung `date(format=excel)` macht daraus einen Zeitstempel ([mappings.md](mappings.md#umwandlungen));
- ein **ODS-Datum** ist sein ISO-Wert (`2023-11-01`), den `date` ohne Format liest.

Eine sehr große Tabelle wird schneller gelesen, wenn sie als CSV gespeichert ist.

## OPML (`opml`)

Dateiendungen: `opml`, `xml`. Das Gliederungsformat, in dem Feedreader und Podcast-Apps ihre Abonnements
exportieren. Jedes `<outline>` ist ein Datensatz:

| Feld | Inhalt |
|---|---|
| `id` | die Feed-Adresse (`xmlUrl`), sonst die Webadresse (`htmlUrl`), sonst die Position der Gliederung |
| `text`, `title` | Text und Titel der Gliederung (jeweils mit dem anderen als Ersatz) |
| `type` | `rss`, `link`, `include` ... wie angegeben |
| `xml_url`, `html_url` | die Feed-Adresse und die Webadresse |
| `description`, `language`, `version` | wie angegeben |
| `category` | das Attribut `category`, sonst der Pfad der umgebenden Gliederungen (`News/Tech`) |
| `path`, `depth`, `position` | die Texte der umgebenden Gliederungen mit `/` verbunden, die Tiefe (1 ganz oben), die Position (`1.2`) |
| `parent_id` | die `id` der umgebenden Gliederung |
| `attributes` | alle Attribute der Gliederung |

| Option | Werte | Vorgabe |
|---|---|---|
| `outlines` | `feeds` (nur Gliederungen mit Feed-Adresse), `leaves` (nur Gliederungen ohne Kinder), `all` | `feeds` |
| `key` | das Schlüsselfeld | `id` |

Die Vorlage bildet eine Gliederung auf ein `link`-Objekt ab (Name, Beschreibung, Adresse). Sollen die Feeds einer
Liste verfolgt statt die Liste gespeichert werden, legt der OPML-Import je Feed einen RSS-Import an:

```bash
./console ext:sqliimport:opml-feeds <Kennung des OPML-Imports> [--parent=<Knoten-ID>] [--prefix=<Anfang der Kennungen>]
```

Jeder Feed-Import wird **deaktiviert** angelegt, mit der Feed-Adresse als Quelle und der RSS-Vorlage als Zuordnung,
unterhalb des angegebenen Elternknotens. Prüfen Sie die Zuordnungen in der Administration und aktivieren Sie die
Importe dann. Ein zweiter Aufruf legt nur die Importe an, deren Kennungen es noch nicht gibt.

## WordPress-Export (`wxr`)

Dateiendungen: `xml`, `wxr`. Die Exportdatei von WordPress (Werkzeuge, Daten exportieren). Die Option `records`
bestimmt, was ein Datensatz ist:

| `records` | Felder |
|---|---|
| `posts` (Vorgabe) | `id`, `type`, `title`, `slug`, `link`, `date` (UTC, ISO 8601), `author` (Login), `author_name`, `status`, `parent_id`, `menu_order`, `content` (wie geschrieben), `content_html` (Absätze aus Leerzeilen, wie WordPress sie anzeigt), `excerpt`, `categories`, `tags`, `category_slugs`, `tag_slugs`, `featured_image` (die Adresse des Beitragsbilds), `images` (die Bildadressen im Inhalt), `comment_count`, `meta` (eigene Felder ohne die `_`-Schlüssel von WordPress) |
| `attachments` | `id`, `title`, `url`, `mime`, `parent_id`, `date`, `alt`, `caption`, `description` |
| `categories` | `id` (Slug), `term_id`, `name`, `slug`, `parent` (Slug), `description` |
| `tags` | `id` (Slug), `term_id`, `name`, `slug`, `description` |
| `authors` | `id`, `login`, `email`, `display_name`, `first_name`, `last_name` |

| Option | Werte | Vorgabe |
|---|---|---|
| `post_types` | die zu lesenden Beitragstypen, einer je Zeile | `post`, `page` |
| `status` | `publish` (nur veröffentlichte) oder `all` (auch Entwürfe, private und ausstehende) | `publish` |
| `key` | das Schlüsselfeld | `id` |

Die Vorlage bildet einen Beitrag auf einen `article` ab (Titel, Einleitung aus dem Auszug, Text aus `content_html`
und, wo die Klasse sie hat, Veröffentlichungsdatum, Bild und Schlagwörter). `featured_image` und `images` sind
Adressen, die die Umwandlung `image` herunterlädt.

## Der Katalog der Formate

**Importe, Katalog** zeigt die Formate nach Gruppen (Daten, Inhalte, Plattformen, Handel, Medien, Sonstiges). Jede
Karte sagt, was das Format liest, nennt Dateiendungen und Optionen, bietet Beispieldateien zum Herunterladen und,
wo das Format eine mitbringt, die Zuordnung, mit der es beginnt. **Neuer Import** öffnet den Editor mit diesem
Format; beim Speichern wird die Vorlage mit den Feldern ausgefüllt, die die Klasse dieser Site hat (eine Zeile der
Vorlage kann die passenden Datentypen nennen, damit ein Bild-Download nicht auf eine Objektbeziehung abgebildet wird).
Prüfen Sie sie im Schritt Zuordnung.

Manche Formate sind Varianten einer Quellklasse mit anderen Vorgaben: **TSV** (`tsv`) ist die CSV-Quelle mit
Tabulator als Trennzeichen, **JSON Lines** (`ndjson`) die JSON-Quelle mit einem Objekt je Zeile.

## Ein Format hinzufügen

Ein Format ist **eine Quellklasse und ein Block in `kernelservices.ini`**. Die Klasse implementiert
`expImportSourceInterface` (`identifier()`, `label()`, `schema()`, `extensions()`, einen Konstruktor, der die
geprüfte Konfiguration mit `file` erhält, `records( $cursor )`, `count()` und `sample( $limit )`); wer von
`expImportAbstractSource` erbt, erhält die Option `key`, den Verweis des Datensatzes und die Methoden des Katalogs
mit ihren Vorgaben, die ein Format nach Bedarf überschreibt:

| Methode | Liefert |
|---|---|
| `description()` | was das Format ist und was ein Datensatz ist |
| `group()` | `data`, `content`, `platform`, `commerce`, `media` oder `other` |
| `examples()` | eine Liste von `array( 'label', 'file' )` (eine Datei in `share/examples` der Erweiterung, mit `'options'`, falls nötig) oder `array( 'label', 'url' )` |
| `preset()` | eine Zuordnung als Ausgangspunkt: `class`, `match`, `fields` (je mit `source`, `attribute`, `transforms` und wahlweise `datatypes`), oder `array()` |
| `variants()` | ID => `label`, `description`, `extensions`, `defaults` (Option => Wert), `examples`, `preset` |

Registrieren Sie es in `settings/kernelservices.ini.append.php` Ihrer Erweiterung:

```ini
[Service_mysite.source.myformat]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=myImportSource
# für eine Variante der Klasse: Arguments[]=<ID der Variante>
Scope=worker
Tags[]=sqliimport.source
```

`[Source_<id>] Status=` in `sqliimport.ini` setzt ein Format auf `enabled`, `example` (aufgeführt, aber für einen
neuen Import nicht angeboten) oder `disabled`; `Enabled=false` im Dienstblock schaltet es ab. Der Weg von
2.0.0-alpha.1 (`[SourceSettings] Sources[]` mit einem Block `[Source_<id>] Class=`) funktioniert weiterhin.
