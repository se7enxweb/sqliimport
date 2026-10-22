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
| `remote` | die eigenen Optionen der Quelle (eine Datenbank, ein Suchindex, ein Postfach, ein Verzeichnisdienst, ein Dienst) | Die Quelle liest ihre Daten einmal pro Lauf selbst und schreibt sie als JSON Lines in das Verzeichnis des Laufs (`sql`, `mongodb`, `redis`, `couchdb`, `couchbase`, `cosmosdb`, `elasticsearch`, `duckdb`, `imap`, `ldap`, `airtable`, `notion`, `dynamodb`, `firestore`, `bigquery`, `firebase_rtdb`, `cassandra`, `neo4j`, `arangodb`, `clickhouse`, `snowflake`, `influxdb`, `supabase`, `baserow`, `salesforce`, `hubspot`, `rest_api` und die Websites der Gruppe Plattformen) |

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

## iCalendar (`ical`)

Gruppe: Inhalte. Dateiendungen: `ics`, `ical`, `ifb`, `txt`. Kalenderdateien (RFC 5545), wie Kalender-Apps,
Buchungssysteme und Veranstaltungsseiten sie veröffentlichen, als Upload oder über eine Abonnement-Adresse. Die Datei
wird als Datenstrom Zeile für Zeile gelesen; gefaltete Zeilen werden zusammengefügt und die Maskierungen `\\`, `\;`,
`\,` und `\n` zurückgelesen. Der Cursor eines Datensatzes ist die Byte-Position nach seinem `END:VEVENT`. Beispiel:
`share/examples/events.ics`.

| Option | Werte | Vorgabe |
|---|---|---|
| `components` | `events` (VEVENT), `events_todos` (VEVENT und VTODO), `todos` (VTODO) | `events` |
| `timezone` | die Zeitzone von Zeiten ohne Zone und von ganztägigen Daten (`Europe/Berlin`) | `UTC` |
| `key` | das Schlüsselfeld | `id` |

| Feld | Inhalt |
|---|---|
| `id` | die UID; ein geänderter Termin einer Serie (mit `RECURRENCE-ID`) erhält `<uid>@<Datum in ISO 8601 UTC>`; ohne UID ein Hash aus Titel und Beginn |
| `uid`, `type` | die UID wie geschrieben; `event` oder `todo` |
| `summary`, `description`, `location`, `url`, `status`, `priority`, `class`, `sequence` | wie geschrieben, ohne Maskierungen (`status` und `class` kleingeschrieben) |
| `start`, `end` | der Wert wie geschrieben (`20261105T190000`) |
| `start_iso`, `end_iso` | ISO 8601 in UTC (`2026-11-05T18:00:00Z`), ein ganztägiges Datum als `2026-11-05` |
| `start_timestamp`, `end_timestamp` | Unix-Zeitstempel (ein ganztägiges Datum: Mitternacht in der Zone der Option) |
| `start_timezone`, `end_timezone` | die `TZID`, `UTC` oder die Zone der Option |
| `all_day` | `1` für ein Datum ohne Uhrzeit |
| `rrule`, `exdates` | die Wiederholungsregel wie geschrieben; die ausgenommenen Termine (eine Liste, ISO 8601) |
| `recurrence_id` | der Termin, den eine geänderte Instanz ersetzt (ISO 8601) |
| `categories` | eine Liste (mehrere `CATEGORIES`-Zeilen werden zusammengefasst) |
| `organizer`, `organizer_name`, `attendees` | die Adresse des Veranstalters ohne `mailto:`, sein `CN`, die Adressen der Teilnehmer |
| `created`, `last_modified` | ISO 8601 in UTC |
| `geo_lat`, `geo_lon`, `calendar` | die Position; der Name des Kalenders (`X-WR-CALNAME`) |
| `raw.*` | jede Eigenschaft unter ihrem kleingeschriebenen Namen, als Text |

Das Ende kommt aus `DTEND`, bei einer Aufgabe aus `DUE`, oder aus `DURATION`; ein ganztägiger Termin ohne Ende dauert
den Tag. Eine `TZID` wird mit der Zonendatenbank von PHP umgerechnet; eine Zone, die PHP nicht kennt (etwa ein
Windows-Zonenname), wird in der Zone der Option gelesen und in den erkannten Werten der Vorschau genannt. Erinnerungen
innerhalb eines Termins entfallen. Eine Wiederholungsregel wird nicht aufgelöst: Eine Serie ist ein Datensatz, und ihre
`rrule` lässt sich zuordnen.

Vorlage: Klasse `event` der Standardinstallation, Remote-ID `ical-{id}`: `summary` nach `title`, `description` nach
`text` (Rich Text), `categories` nach `category` (Schlagwörter, verbunden), `start_timestamp` nach `from_time` und
`end_timestamp` nach `to_time`.

## vCard (`vcard`)

Gruppe: Inhalte. Dateiendungen: `vcf`, `vcard`, `txt`. Kontakte, wie Adressbücher, Telefone und Mailprogramme sie
exportieren: vCard 4.0 und 3.0, und von 2.1 das Übliche (bloße Typen wie `TEL;WORK`, Quoted-Printable-Werte mit
`CHARSET`). Jedes `BEGIN:VCARD` ... `END:VCARD` ist ein Datensatz, als Datenstrom gelesen wie bei iCalendar.
Beispiel: `share/examples/contacts.vcf` (erfundene Personen, Adressen unter `example.com`, 555-Nummern).

| Feld | Inhalt |
|---|---|
| `id` | die UID ohne `urn:uuid:`, sonst `vcard-` und ein Hash aus formatiertem Namen und E-Mail-Adresse |
| `uid`, `version`, `kind`, `fn` | wie geschrieben; `fn` wird aus `N` gebildet, wenn es fehlt |
| `n` | `family`, `given`, `additional`, `prefix`, `suffix`; auch als `family_name` und `given_name` |
| `nickname`, `title`, `role`, `note`, `gender`, `anniversary`, `rev` | wie geschrieben |
| `org`, `org_unit` | die Organisation und ihre Abteilungen |
| `email`, `emails` | die bevorzugte (sonst die erste) Adresse und alle; `email_work`, `email_home` die erste dieses Typs |
| `tel`, `tels` | die bevorzugte (sonst die erste) Nummer und alle; `tel_work`, `tel_home`, `tel_cell`, `tel_fax` |
| `adr`, `adr_text`, `adrs` | die erste Anschrift (`po_box`, `extended`, `street`, `locality`, `region`, `postal_code`, `country`, `type`), dieselbe in einer Zeile, alle Anschriften |
| `url`, `urls` | die erste Webadresse und alle |
| `bday`, `bday_iso` | der Geburtstag wie geschrieben und als `JJJJ-MM-TT` (`--MM-TT` ohne Jahr) |
| `categories` | eine Liste |
| `photo`, `photo_embedded` | die `http(s)`-Adresse des Fotos; ein eingebettetes Foto wird nicht übernommen, `photo_embedded` ist dann `1` |
| `raw.*` | jede Eigenschaft unter ihrem kleingeschriebenen Namen, als Text |

Es gibt keine Vorlage: Eine Standardinstallation hat keine Kontaktklasse, und Benutzer (`ezuser`) werden nicht über
eine Zuordnung angelegt. Ordnen Sie die Karten einer Klasse Ihrer Website zu. Kontaktdaten sind personenbezogene
Daten: Importieren Sie sie nur, wenn die genannten Personen zugestimmt haben.

## Google-Merchant-Feed (`google_merchant`)

Gruppe: Handel. Dateiendungen: `xml`, `rss`. Der Produkt-Feed, den Shops für das Google Merchant Center und
Preisvergleichsseiten erstellen: RSS 2.0 (oder Atom) mit dem Namensraum `g:`, als Datenstrom gelesen wie XML, externe
Entitäten werden nie geladen. Jeder Eintrag ist ein Produkt. Felder werden mit dem Präfix `g:` und ohne es gefunden
(`<title>` und `<g:title>`). Beispiel: `share/examples/products-google.xml`.

| Feld | Inhalt |
|---|---|
| `id`, `item_group_id` | `g:id` (sonst der Link), die Gruppe der Varianten |
| `title`, `description`, `link`, `mobile_link` | wie geschrieben (die Beschreibung kann HTML enthalten) |
| `image_link` (auch `image`), `additional_image_links`, `images` | das Hauptbild, die weiteren, alle |
| `price`, `currency`, `price_text` | der Betrag als Dezimalzahl (`1,299.00 USD` ergibt `1299.00`), der ISO-4217-Code, der Wert wie geschrieben |
| `sale_price`, `sale_price_currency`, `sale_price_effective_date` | der Angebotspreis |
| `availability`, `availability_date`, `condition`, `brand`, `gtin`, `mpn`, `identifier_exists` | wie geschrieben |
| `product_type`, `categories`, `google_product_category` | der Kategoriepfad des Shops, derselbe als Liste (bei `>` geteilt), die Kategorie von Google |
| `color`, `size`, `gender`, `age_group`, `material`, `pattern` | wie geschrieben |
| `shipping`, `shipping_weight` | eine Liste aus `country`, `region`, `service`, `price`, `currency` |
| `tax`, `vat` | eine Liste aus `country`, `region`, `rate`, `tax_ship`; `vat` ist der Satz des ersten Eintrags |
| `tags` | die gesetzten eigenen Labels `custom_label_0` bis `custom_label_4` |
| `raw.*` | das unveränderte Element |

Die einzige Option ist `key` (Vorgabe `id`). Vorlage: siehe [die Produktvorlage](#die-produktvorlage), Remote-ID
`gmc-{id}`, Artikelnummer aus `id`.

## Shopify-Produkt-CSV (`shopify`)

Gruppe: Handel. Dateiendung: `csv`. Der Produktexport eines Shopify-Shops (Produkte, Exportieren). Die Zeilen mit
demselben `Handle` sind ein Produkt: Die erste Zeile enthält das Produkt, weitere Zeilen fügen Varianten und Bilder
hinzu. Die Datei wird als Datenstrom gelesen wie CSV; der Cursor eines Datensatzes ist die Byte-Position der ersten
Zeile des nächsten Produkts. Beispiel: `share/examples/products-shopify.csv`.

| Option | Werte | Vorgabe |
|---|---|---|
| `delimiter`, `enclosure`, `encoding` | wie bei CSV | `auto`, `"`, `auto` |
| `currency` | der ISO-4217-Code der Preise (der Export nennt ihn nicht) | leer |
| `key` | das Schlüsselfeld | `id` |

| Feld | Inhalt |
|---|---|
| `id`, `handle` | der Handle |
| `title`, `description`, `vendor`, `product_category`, `type`, `status`, `seo_title`, `seo_description` | wie geschrieben (`description` ist `Body (HTML)`) |
| `tags` | eine Liste |
| `published`, `gift_card` | `1` oder `0` |
| `options` | die Namen der Optionen (`Size`, `Color`; der Platzhalter `Title` eines Produkts ohne Varianten entfällt) |
| `variants` | eine Liste aus `sku`, `price`, `compare_at_price`, `option1` bis `option3`, `grams`, `inventory_qty`, `barcode`, `taxable`, `requires_shipping`, `weight_unit`, `tax_code`, `cost`, `image` |
| `sku`, `price`, `compare_at_price`, `taxable`, `tax_code` | die der ersten Variante |
| `price_min`, `price_max` | der niedrigste und der höchste Variantenpreis |
| `currency` | die Option `currency` |
| `images`, `image`, `image_alt` | die Bildadressen in ihrer Reihenfolge, die erste, ihr Alternativtext |
| `row.*` | die erste Zeile wie gelesen, unter den kleingeschriebenen Spaltennamen |

Vorlage: [die Produktvorlage](#die-produktvorlage), Remote-ID `shopify-{id}`, Artikelnummer aus `sku`.

## WooCommerce-Produkt-CSV (`woocommerce`)

Gruppe: Handel. Dateiendung: `csv`. Der Produktexport von WooCommerce (Produkte, Exportieren) mit den englischen
Spaltennamen. Eine Zeile ist ein Produkt. Beispiel: `share/examples/products-woocommerce.csv`.

| Option | Werte | Vorgabe |
|---|---|---|
| `variations` | `nested` (die Variationen, die ihrem variablen Produkt folgen, kommen in dessen `variants`), `records` (jede Zeile ist ein Datensatz) | `nested` |
| `delimiter`, `enclosure`, `encoding`, `currency`, `key` | wie bei Shopify | |

| Feld | Inhalt |
|---|---|
| `id`, `wc_id` | die ID (sonst die SKU), die ID wie geschrieben |
| `type`, `types` | `simple`, `variable`, `variation`, `grouped`, `external`; alle Typen einer Zelle wie `simple, virtual` |
| `sku`, `title`, `visibility`, `short_description`, `description`, `purchase_note` | wie geschrieben; das `\n` von WooCommerce ist ein Zeilenumbruch |
| `published`, `featured` | `1`, `0` oder `-1` für einen Entwurf |
| `regular_price`, `sale_price`, `price`, `current_price`, `currency` | Dezimalzahlen; `price` ist der reguläre Preis (bei einem variablen Produkt der niedrigste seiner Variationen), `current_price` der Angebotspreis, wenn gesetzt |
| `sale_from`, `sale_to` | die Daten des Angebotspreises |
| `tax_status`, `tax_class` | die Steuerfelder von WooCommerce (der Export enthält keinen Steuersatz) |
| `in_stock`, `stock`, `backorders`, `weight`, `length`, `width`, `height`, `shipping_class` | wie geschrieben |
| `categories`, `category`, `tags` | die Kategoriepfade (`Clothing > Hoodies`) als Liste, der letzte Teil des ersten, die Schlagwörter als Liste |
| `images`, `image` | die Bildadressen, die erste |
| `parent`, `parent_id`, `grouped`, `upsells`, `cross_sells` | das übergeordnete Produkt (`id:102` ergibt `102`), die verknüpften Produkte als Listen |
| `external_url`, `button_text`, `position` | wie geschrieben |
| `attributes` | eine Liste aus `name`, `values` (eine Liste), `visible`, `global` |
| `meta.*` | die Spalten `Meta: <Schlüssel>` nach Schlüssel |
| `variants` | die Variationen: `id`, `sku`, `title`, `regular_price`, `sale_price`, `price`, `stock`, `in_stock`, `image`, `attributes` |
| `row.*` | die Zeile wie gelesen |

Ein Komma innerhalb eines Listeneintrags schreibt WooCommerce als `\,`; es wird zurückgelesen. Vorlage:
[die Produktvorlage](#die-produktvorlage), Remote-ID `woocommerce-{id}`, Artikelnummer aus `sku` und
`short_description` nach `short_description`.

### Die Produktvorlage

Die drei Produktformate beginnen mit der Klasse `product` des Exponential-Webshops: `title` nach `name`, die
Artikelnummer nach `product_number`, `description` nach `description` (Rich Text), `price` nach `price` und `tags`
nach `tags` (Schlagwörter, mit Kommas verbunden). Der Preis läuft durch `template` als `{price}|1|1`, was `ezprice`
als Betrag, Mehrwertsteuertyp 1 und „Preis enthält Mehrwertsteuer“ liest; ändern Sie Steuertyp und die letzte `1`
(`0`: ohne Mehrwertsteuer) in der Zuordnung, wenn Ihr Shop es braucht. Bilder sind nicht in der Vorlage: Das `image`
des Webshops ist eine Objektbeziehung, ordnen Sie `image` daher mit `lookup` Bildobjekten zu, die Sie zuerst
importieren. Ein Produkt mit leerem Preis schlägt fehl, weil `ezprice` einen Betrag ablehnt, der keine Zahl ist.

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

## Markdown-Dateien (`markdown`)

Gruppe: Inhalte. Dateiendungen: `zip`, `md`, `markdown`. Eine Markdown-Datei oder ein Ordner als ZIP: eine
Jekyll- oder Hugo-Site, ein Obsidian-Vault, ein Dokumentationsordner. Jede `.md`- / `.markdown`-Datei ist ein
Datensatz. Front Matter in YAML (zwischen `---`-Zeilen) oder TOML (zwischen `+++`-Zeilen) wird ohne Bibliothek gelesen.

| Feld | Inhalt |
|---|---|
| `id` | die `id` des Front Matter, sonst der Pfad ohne Endung (`_posts/2026-03-20-spring-opening-hours`) |
| `path`, `folder`, `file` | wo die Datei liegt (ein Ordner, den alle Dateien teilen, der eigene Ordner des ZIP, gehört nicht dazu) |
| `slug` | der `slug` des Front Matter, sonst der Dateiname ohne Jekyll-Datum |
| `title` | der `title` des Front Matter, sonst die erste `#`-Überschrift, sonst der Dateiname |
| `date` | das `date` des Front Matter (oder `created`, `publishDate`), sonst das Datum eines Jekyll-Dateinamens |
| `draft` | `true` bei `draft: true`, `published: false` oder einer Datei in `_drafts` |
| `tags`, `categories` | Listen (ein Text `a, b` wird geteilt) |
| `summary` | `description`, `summary` oder `excerpt` des Front Matter, sonst der erste Absatz als Text |
| `markdown`, `html`, `text` | der Text wie geschrieben, als HTML, als reiner Text |
| `links`, `images` | die Obsidian-`[[Links]]` und die Bildpfade des Textes |
| `front` | das ganze Front Matter; jeder Schlüssel ist auch ein eigenes Feld |

Das HTML kennt Überschriften, Absätze, Hervorhebungen, Code, Links, Bilder, Listen (auch verschachtelte und
Aufgabenlisten), Zitate, Tabellen sowie Obsidian-Links `[[Seite]]` / `[[Seite|Text]]` und Einbettungen
`![[bild.png]]`. Adressen außer `http`, `https` und `mailto` werden verworfen.

| Option | Werte | Vorgabe |
|---|---|---|
| `drafts` | auch Entwürfe lesen | nein |
| `skip_index` | `README`- und `index`-Dateien auslassen | nein |
| `folder` | nur dieser Ordner des ZIP | alle |
| `wiki_base` | die Adresse, auf die `[[Links]]` zeigen (plus Slug der Seite); leer: nur ihr Text | leer |

Ordner, die mit `_` oder `.` beginnen (Layouts, Includes, `.obsidian`), bleiben außen vor, außer `_posts` (und
`_drafts` mit der Option `drafts`). Die Vorlage bildet eine Datei auf einen `article` ab (Titel, Einleitung aus der
Zusammenfassung, Text aus dem HTML und, wo die Klasse sie hat, Veröffentlichungsdatum und Schlagwörter).

## Mediendateien (`media`)

Gruppe: Medien. Dateiendungen: `zip`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `tif`, `tiff`, `pdf`, `mp3`, `mp4`.
Fotos, Dokumente, Audio und Video, eine Datei oder ein Ordner als ZIP. Jede Datei ist ein Datensatz:

| Feld | Inhalt |
|---|---|
| `id`, `path`, `folder`, `file`, `extension`, `mime`, `size` | wo die Datei liegt und was sie ist |
| `sha1` | der Hash des Inhalts: mit dem Muster der Remote-ID `media-{sha1}` wird dieselbe Datei einmal importiert, wo sie auch liegt |
| `name` | Objektname oder Überschrift aus IPTC, sonst die EXIF-Beschreibung, sonst der Dateiname in Worten |
| `caption` | die IPTC-Bildunterschrift, sonst die EXIF-Beschreibung |
| `keywords` | die IPTC-Stichwörter (eine Liste) |
| `author`, `copyright`, `credit`, `source`, `city`, `country` | aus IPTC (Autor und Copyright sonst aus EXIF) |
| `date_taken` | wann das Foto aufgenommen wurde (EXIF `DateTimeOriginal`, sonst das IPTC-Datum), ISO 8601 |
| `width`, `height`, `orientation`, `camera`, `gps_lat`, `gps_lon` | aus dem Bild und seinem EXIF (GPS in Dezimalgrad) |
| `exif`, `iptc` | die gelesenen EXIF-Felder, die IPTC-Felder nach Namen |
| `media` | die Datei selbst als `source:<Pfad>`: die Umwandlungen `image` und `file` nehmen sie wie eine Adresse und importieren die Datei aus dem ZIP des Laufs |

| Option | Werte | Vorgabe |
|---|---|---|
| `types` | `images` (JPEG, PNG, GIF, WebP, TIFF) oder `all` | `images` |
| `folder` | nur dieser Ordner des ZIP | alle |
| `key` | `path` oder `sha1` | `path` |

Die Vorlage bildet ein Foto auf ein `image`-Objekt ab (Name, Bildunterschrift, das Bild und, wo die Klasse sie hat,
die Stichwörter als Schlagwörter) mit der Remote-ID `media-{sha1}`. Die Metadaten werden im Speicher gelesen; dafür
wird keine Datei geschrieben. Eine Datei im ZIP über 256 MB wird abgelehnt.

## Podcast-Feeds (`podcast`) und YouTube- oder Vimeo-Kanal-Feeds (`video_feed`)

Gruppe: Medien. Beide sind die RSS-Quelle (`rss`) mit einer eigenen Vorlage; jeder RSS- und Atom-Datensatz hat auch
diese Felder:

| Feld | Inhalt |
|---|---|
| `enclosure`, `enclosure_type`, `enclosure_length` | die Datei der Folge, ihr Medientyp und ihre Größe |
| `duration` | die iTunes-Dauer in Sekunden (`38:21` ist `2301`) |
| `episode`, `season`, `explicit` | die iTunes-Nummern von Folge und Staffel und ob sie als explizit markiert ist |
| `image` | das Bild der Folge oder des Videos (iTunes-Bild, Media-Thumbnail) |
| `video_id`, `channel_id`, `embed_url` | die IDs von YouTube-Video und -Kanal oder die Vimeo-Video-ID und die Adresse des Players zum Einbetten |
| `views`, `media_description` | die YouTube-Aufrufe und die Beschreibung des Videos |

Die Podcast-Vorlage importiert eine Folge in ein `file`-Objekt (Name, Beschreibung und die Datei der Folge über die
Umwandlung `file`). Die Video-Vorlage speichert ein Video als `link` (Name, Beschreibung, Adresse), zugeordnet über
`video-{video_id}`. Der Feed eines YouTube-Kanals ist `https://www.youtube.com/feeds/videos.xml?channel_id=<Kanal-ID>`
(eine Playlist: `?playlist_id=`), der eines Vimeo-Nutzers `https://vimeo.com/<Nutzer>/videos/rss`.

## YAML- und TOML-Datendateien (`yaml`, `toml`)

Gruppe: Daten. Dateiendungen: `yaml`, `yml`, `toml`. Die Liste der Datensätze steht ganz oben oder unter
`record_path` (`teas`, `data.items`); in TOML meist ein Array von Tabellen (`[[teas]]`). Eine Zuordnung von
Zuordnungen ist auch eine Liste: ihre Schlüssel werden zum Feld `_key`. Verschachtelte Werte werden über ihren Pfad
zugeordnet (`price.amount`). Die Datei wird ganz gelesen, vom eigenen Parser der Erweiterung: eine Datei liest sich auf
jedem Server gleich, mit oder ohne PHP-Erweiterung yaml (YAML 1.2: `y` und `n` sind Text). Anker und Aliase werden
nicht gelesen. Das Front Matter von Markdown-Dateien nutzt denselben Parser.

| Option | Werte | Vorgabe |
|---|---|---|
| `format` | `auto` (nach dem Dateinamen), `yaml`, `toml` | `auto` (`toml` beim Format `toml`) |
| `record_path` | der Pfad der Liste | ganz oben |
| `key` | das Schlüsselfeld | leer |

## Apache Avro (`avro`) und Apache Arrow oder Feather (`arrow`)

Gruppe: Daten. Herkunft `upload`, `url` und `path` wie die anderen Dateien. Beide werden allein in PHP gelesen; für die
üblichen Dateien muss nichts installiert werden.

**Avro** (`.avro`): Object Container Files, wie Kafka-Connectoren, Hadoop und Datenpipelines sie schreiben. Der Leser
prüft den Kopf (`Obj1`, das Schema in `avro.schema`, die Sync-Markierung nach jedem Block) und dekodiert jeden
Datensatz mit dem Schema der Datei. **Arrow** (`.arrow`, `.feather`, `.arrows`, `.ipc`): das Arrow-IPC-Dateiformat
(auch Feather Version 2, die dasselbe ist) und das Stream-Format, wie pandas, Polars, R und DuckDB sie schreiben; eine
Datei wird ganz gelesen, bis `[SourceSettings] MaxSourceMegabytes`. Feather Version 1 (vor 2020) wird mit einer Meldung
abgelehnt.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `arrays` | Arrays (Listen) als JSON (`["a","b"]`) oder als verbundene Liste (`a, b`; Listen von Datensätzen bleiben JSON) | `json` |
| `separator` | Das Trennzeichen der Liste | `, ` |
| `key` | das Schlüsselfeld | leer |

Ein Datensatz ist ein Avro-Datensatz oder eine Arrow-Zeile; verschachtelte Datensätze, Maps und Struct-Spalten werden
zu Namen mit Punkt (`origin.country`) aufgelöst, wie die MongoDB-Quelle es tut. Ein Abschnitt eines Laufs setzt beim
Block (Avro) oder Batch (Arrow) des Datensatzes davor fort.

| Typ | Wert |
|---|---|
| null, boolean, int, long, float, double | wie sie sind (Arrow: int mit 8 bis 64 Bit, mit oder ohne Vorzeichen; eine vorzeichenlose 64-Bit-Zahl über dem Bereich von PHP als Text; auch Half Floats) |
| NaN und Unendlich | der Text `NaN`, `INF`, `-INF` |
| string (Arrow utf8, large utf8) | Text |
| bytes, fixed (Arrow binary, fixed size binary) | Base64 |
| enum (Arrow-Spalten mit Wörterbuch) | das Symbol oder der Wert |
| union | der Wert seines Zweigs (Arrow-Unions werden abgelehnt) |
| date | `2026-10-22` |
| timestamp-millis, -micros (Arrow timestamp mit Zeitzone) | ISO 8601 in UTC mit `Z` |
| local-timestamp-* (Arrow timestamp ohne Zeitzone) | ISO 8601 ohne `Z` |
| time-millis, -micros (Arrow time) | `10:20:30.500` |
| decimal (bytes oder fixed; Arrow decimal mit 32 bis 256 Bit) | eine Dezimalzahl in Textform mit ihren Nachkommastellen (`-1234.50`), nie eine Gleitkommazahl |
| uuid | der Text 8-4-4-4-12 |
| Arrow duration | die Zahl ihrer Einheit |

**Kompression:** Avro-Codecs `null` und `deflate` (zlib); `snappy` nur mit der PECL-Erweiterung snappy (die CRC-32 jedes
Blocks wird geprüft), `zstandard` mit der PECL-Erweiterung zstd, `bzip2` mit der Erweiterung bz2; jeder andere Codec
wird mit einer Meldung abgelehnt. Arrow: unkomprimiert, LZ4-Frames (die Vorgabe von Feather in pyarrow; in PHP
gelesen) und Zstandard (nur mit der PECL-Erweiterung zstd). Die Treiberliste des Katalogs zeigt, was dieses PHP und die
Worker der Job-Queue haben. Arrow-Intervalle, Unions, Run-End-kodierte und View-Typen werden mit dem Namen der Spalte
abgelehnt: Wandeln Sie sie beim Schreiben der Datei in einen einfachen Typ um.

**Parquet** liest das Format DuckDB (`duckdb`), das das Programm `duckdb` oder libduckdb auf dem Server braucht. DuckDB
liest Arrow-Dateien nur über seine Community-Erweiterung `arrow`, die die DuckDB-Quelle nicht lädt (sie lehnt
`INSTALL` und `LOAD` ab); darum werden Arrow-Dateien hier gelesen.

**Beispiele:** `share/examples/teas.avro` (fünf erfundene Teesorten mit verschachtelter Herkunft, Schlagwörtern, einem
Dezimalpreis, einem Datum und einem Zeitstempel; deflate, zwei Blöcke) und `share/examples/teas.arrow` (dasselbe als
Arrow-Datei mit einem Struct, einer Liste, einer Wörterbuch-Spalte und zwei Record Batches).

## Google Sheets (`google_sheets`)

Gruppe: Daten. Eine Tabelle, die bei jedem Lauf über ihren Link gelesen wird: teilen Sie sie so, dass jeder mit dem
Link sie ansehen kann (oder veröffentlichen Sie sie im Web), und geben Sie den Link als Adresse an. Der Link wird zum
CSV-Export der Tabelle, mit dem Tabellenblatt des Links (`#gid=`); die erste Zeile benennt die Spalten. Die Optionen
sind die von CSV, mit Komma und UTF-8.

## SQL-Datenbank oder -Abfrage (`sql`)

Gruppe: Plattformen. Herkunft: `remote`. Die Zeilen einer SQL-Abfrage an eine MySQL- oder MariaDB-, PostgreSQL-,
SQLite-, Oracle- oder Microsoft-SQL-Server-Datenbank (eine Datenbank im Intranet, die Datenbank einer anderen Anwendung): Ein Datensatz ist eine Zeile,
seine Felder sind die Spalten der Abfrage (mit `AS` bekommen sie die gewünschten Namen). Einmal pro Lauf führt die
Quelle die Abfrage aus und schreibt die Zeilen in das Verzeichnis des Laufs, ein JSON-Objekt pro Zeile; binäre Werte,
die kein UTF-8 sind, werden als `base64:...` geschrieben. Stichprobe und Vorschau des Editors lesen nur die ersten
`[RunSettings] DryRunRows` Zeilen.

| Option | Werte | Vorgabe |
|---|---|---|
| `driver` | `mysql` (MySQL oder MariaDB), `pgsql` (PostgreSQL), `sqlite` (eine Datei auf dem Server), `oracle` (Oracle), `sqlsrv` (Microsoft SQL Server), `site` (die Datenbank dieser Website) | `mysql` |
| `host`, `port` | der Datenbankserver; Port `0` ist der Standardport (3306, 5432, Oracle 1521, SQL Server 1433) | |
| `database` | der Name der Datenbank; bei SQLite der Pfad der Datei; bei Oracle der Dienstname (siehe unten) | |
| `user` | der Datenbankbenutzer | |
| `password` | der **Name** eines Geheimnisses (siehe unten), nie das Passwort | |
| `query` | ein einzelnes `SELECT` (oder `WITH ... SELECT`) | Pflicht |
| `key` | die Spalte, die eine Zeile benennt | die Position |

Nur Lesen ist möglich, dreifach geprüft:

- **Die Abfrage muss ein einzelnes `SELECT` sein** (oder `WITH ... SELECT`, auch in Klammern). Vor der Prüfung werden
  Zeichenketten, Namen in Anführungszeichen und Kommentare im Dialekt des Treibers entfernt (Backslash-Escapes und
  `#`-Kommentare von MySQL, Dollar-Quoting, `E'...'`-Zeichenketten und verschachtelte Kommentare von PostgreSQL). So
  zählt ein Schlüsselwort in einem Text nicht, und eine hinter einem Kommentar oder einem Anführungszeichen
  versteckte Anweisung wird gefunden. Abgelehnt werden ein `;`, auf das noch etwas folgt, eine nicht geschlossene
  Zeichenkette oder ein nicht geschlossener Kommentar, die ausführbaren Kommentare `/*! ... */` und Hinweise
  `/*+ ... */` von MySQL, Variablen (`@name`, `:=`) und außerhalb von Zeichenketten die Wörter `INSERT`, `UPDATE`,
  `DELETE`, `DROP`, `ALTER`, `CREATE`, `GRANT`, `REVOKE`, `TRUNCATE`, `MERGE`, `CALL`, `EXEC`, `INTO` (auch
  `INTO OUTFILE` und `SELECT ... INTO` von PostgreSQL), `LOAD`, `LOAD_FILE`, `COPY`, `ATTACH`, `PRAGMA`, `LOCK` (auch
  `FOR UPDATE`) sowie Funktionen mit Nebenwirkungen (`pg_read_file`, `lo_import`, `dblink`, `set_config`, `nextval`,
  `sleep`, `load_extension`, `writefile` ...). `REPLACE` ist nur als Funktion `REPLACE( ... )` erlaubt. Eine Spalte
  mit einem solchen Namen lässt sich in Anführungszeichen setzen (`` `update` ``, `"update"`). Eine abgelehnte
  Abfrage beendet den Lauf mit dem Grund.
  - **T-SQL (SQL Server)** zusätzlich: `[Namen in eckigen Klammern]` (mit `]]` darin), `N'...'`-Zeichenketten und
    verschachtelte Kommentare werden so gelesen, wie SQL Server sie liest; `TOP` und `OFFSET ... FETCH` blättern durch
    die Zeilen. Abgelehnt werden `EXEC`/`EXECUTE`, Systemprozeduren (`sp_...`, `xp_...`, auch in eckigen Klammern),
    `SELECT ... INTO` (es legt eine Tabelle an), `MERGE`, `OPENROWSET`, `OPENQUERY`, `OPENDATASOURCE`,
    `WITH ... DELETE`/`UPDATE`/`MERGE`/`INSERT`, eine zweite Anweisung ohne `;` (`BEGIN`, `DECLARE`, `SET`, `USE`,
    `WAITFOR`, `DBCC`, `BULK`, `COMMIT` ...), Sperrhinweise (`UPDLOCK`, `XLOCK`, `HOLDLOCK`, `TABLOCK`, `TABLOCKX`,
    `PAGLOCK`) und `NEXT VALUE FOR`.
  - **Oracle** zusätzlich: `q'[...]'`-Zeichenketten (mit jedem Begrenzer, auch `nq'...'`) werden so gelesen, wie
    Oracle sie liest. Abgelehnt werden PL/SQL-Blöcke (`BEGIN`, `DECLARE`, auch `WITH FUNCTION`), `FOR UPDATE` (es
    sperrt Zeilen), Aufrufe der Pakete `DBMS_...`, `UTL_...`, `OWA_...`, `HTP`, `HTF` und von `HTTPURITYPE` (auch als
    Namen in Anführungszeichen wie `"DBMS_PIPE"."SEND_MESSAGE"`), Datenbank-Links (`tabelle@link`),
    `sequenz.NEXTVAL`, `COMMIT` und `ROLLBACK`. Oracle liefert Spaltennamen ohne Anführungszeichen in Großbuchstaben:
    Geben Sie die Schlüsselspalte so an, oder benennen Sie sie mit `AS "id"`.
- **Die Verbindung ist schreibgeschützt:** MySQL und MariaDB `SET SESSION TRANSACTION READ ONLY`, PostgreSQL
  `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY`, SQLite schreibgeschützt geöffnet mit `PRAGMA query_only`,
  Oracle eine eigene Verbindung mit `SET TRANSACTION READ ONLY`, die nie bestätigt (committet) wird (ein Schreibversuch
  darin scheitert mit ORA-01456). SQL Server kennt keine schreibgeschützte Sitzung: Die Verbindung fragt
  `ApplicationIntent=ReadOnly` an (das sie zu einem lesbaren Sekundärreplikat einer Verfügbarkeitsgruppe leitet, wo es
  eines gibt), führt `SET NOCOUNT ON`, `SET IMPLICIT_TRANSACTIONS OFF` und `SET LOCK_TIMEOUT` aus und verlässt sich
  sonst auf die Prüfung der Abfrage. **Geben Sie dem Import einen Datenbankbenutzer, der nur lesen darf** (bei SQL
  Server die Rolle `db_datareader`, bei Oracle `CREATE SESSION` und `SELECT` auf die nötigen Tabellen, bei den anderen
  einen Benutzer nur mit `SELECT`-Rechten). Mehrfachanweisungen und emulierte Prepared Statements sind abgeschaltet,
  `LOCAL INFILE` von MySQL ebenso.
- **Ein Zeitlimit** von `[SqlSourceSettings] QueryTimeout` Sekunden wird auf dem Server gesetzt, wo er eines kennt
  (MySQL `MAX_EXECUTION_TIME`, MariaDB `max_statement_time`, PostgreSQL `statement_timeout`, Oracle das Aufruf-Zeitlimit
  von oci8, SQL Server das Abfrage-Zeitlimit von pdo_sqlsrv oder pdo_dblib); das Lesen der Zeilen bricht nach der
  zehnfachen Zeit ab.

**Treiber.** Oracle und SQL Server brauchen eine PHP-Erweiterung, die nicht jeder Server hat:

| Datenbank | Treiber, in dieser Reihenfolge | Zeichensatz |
|---|---|---|
| Oracle | `oci8`, sonst `pdo_oci` (`[SqlSourceSettings] OracleDriver`) | AL32UTF8 (UTF-8) |
| SQL Server | `pdo_sqlsrv`, sonst `pdo_dblib` (FreeTDS), sonst `PDO_ODBC` mit einem ODBC-Treiber für SQL Server (`[SqlSourceSettings] SqlServerDriver`, `OdbcDriver`) | UTF-8 (die UTF-8-Kodierung von pdo_sqlsrv, `charset=UTF-8` von dblib, `ClientCharset=UTF-8` des FreeTDS-ODBC-Treibers) |

Was der Server für einen fehlenden Treiber braucht (Pakete des Systems oder der PHP-Distribution; Plesk und andere
Verwaltungsoberflächen haben eigene):

- **Oracle:** den Oracle Instant Client (RHEL: `oracle-instantclient-basic`; Debian: den Instant Client von Oracle)
  und die PECL-Erweiterung `oci8` (RHEL: `php-oci8` von Remi; Debian: mit PECL gebaut), oder `pdo_oci`.
- **SQL Server:** den Microsoft ODBC Driver 18 (`msodbcsql18` aus dem Repository von Microsoft, RHEL und Debian) und
  die PECL-Erweiterung `pdo_sqlsrv` (RHEL: `php-sqlsrv` von Remi; Debian: `php-sqlsrv` oder PECL); oder `pdo_dblib`
  mit FreeTDS (RHEL: `php-pdo-dblib`, `freetds`; Debian: `php-sybase`, `freetds-common`); oder `PDO_ODBC` mit einem
  ODBC-Treiber (RHEL: `php-odbc`, `unixODBC` und `msodbcsql18` oder `freetds`; Debian: `php-odbc`, `unixodbc` und
  `msodbcsql18` oder `tdsodbc`).

Der **Katalog** (die Karte des Formats) und der Schritt „Quelle“ des Editors zeigen, welche Datenbanken dieser Server
lesen kann: grün mit dem verwendeten Treiber, grau mit dem, was der Server braucht. Läufe lesen die **Worker der
Job-Warteschlange**, und deren PHP ist nicht immer das PHP des Webservers (die Administration kann von einem anderen
PHP ausgeliefert werden als dem, das der Cronjob der Warteschlange startet). Die Worker vermerken bei jedem Lauf ihr
PHP und dessen Treiber; die Liste zeigt dann auch „in den Workern“ für eine Datenbank, die das PHP des Webservers
nicht lesen kann, und ein Test oder eine Stichprobe im Editor meldet, dass Läufe trotzdem funktionieren.
Verschlüsselung der SQL-Server-Verbindungen: `[SqlSourceSettings] SqlServerEncrypt` (enabled) und
`SqlServerTrustServerCertificate` (disabled). Der ODBC-Treiber von Microsoft liefert Text in der Locale des
PHP-Prozesses: Geben Sie den Workern eine UTF-8-Locale (`LANG=C.UTF-8`), oder nennen Sie den Zeichensatz in
`[SqlSourceSettings] OdbcCharset`, damit er umgewandelt wird.

**Oracle-Verbindungen**, in einer von drei Formen:

- Host, Port und der **Dienstname** als Datenbank: `ora.example.org`, `1521`, `ORCLPDB1`;
- kein Host und **Easy Connect** als Datenbank: `//ora.example.org:1521/ORCLPDB1` (der Host wird genauso geprüft);
- kein Host und ein **TNS-Name** als Datenbank: nur einer, den die Administration in `[SqlSourceSettings]
  AllowedTnsNames[]` einträgt, denn die `tnsnames.ora` des Servers bestimmt, wohin er führt.

Verbindungsbeschreibungen (`(DESCRIPTION=...)`) werden nicht angenommen. Datums- und Zeitwerte kommen in ISO 8601
(`2026-10-22T10:11:12`, `...T10:11:12.500000`: `NLS_DATE_FORMAT` und `NLS_TIMESTAMP_FORMAT` der Sitzung), Zahlen so,
wie Oracle sie schreibt, mit Dezimalpunkt (`3.5`, `.25`; für eine andere Form `TO_CHAR` in der Abfrage), `CLOB` und
`NCLOB` als Text, `BLOB` und `RAW` als `base64:...`.

**Beispiel für SQL Server:**

```sql
SELECT TOP 100 [Id], [Name], [Description] FROM [dbo].[Products] ORDER BY [Changed] DESC
```

**Verbindung testen.** Im Schritt „Quelle“ eines Imports dieses Formats verbindet *Verbindung testen* mit den Optionen
des Formulars, führt die Abfrage für ihre erste Zeile aus und zeigt deren Spalten oder den Fehler (ein Passwort
erscheint nie, es wird maskiert). Nichts wird gespeichert; es braucht dieselbe Berechtigung wie das Speichern des
Imports.

Wohin verbunden werden darf:

- Ein Datenbankserver in einem privaten oder reservierten Netz wird wie eine Adresse abgelehnt, außer bei
  `[SourceSettings] AllowPrivateHosts=enabled` oder wenn die Administration ihn in
  `[SqlSourceSettings] AllowedHosts[]` einträgt (der übliche Fall: eine Datenbank im Intranet).
- Eine SQLite-Datei muss in einem der Verzeichnisse von `[SourceSettings] AllowedPaths[]` liegen.
- Die eigene Datenbank der Website (`site`) nur mit `[SqlSourceSettings] SiteDatabase=enabled`, über eine eigene,
  schreibgeschützte Verbindung. Ihre Tabellen enthalten Benutzerkonten und Passwort-Hashes: Schalten Sie das nur ein,
  wenn alle, die Importe bearbeiten, alles darin sehen dürfen.

**Beispiel:** `share/examples/sql-teas.sqlite` ist eine kleine SQLite-Datenbank mit der Tabelle `teas` (`id`, `name`,
`origin`, `kind`, `price`, `description`, `updated`). Kopieren Sie sie in ein Verzeichnis von `AllowedPaths[]` (zum
Beispiel `var/import`) und legen Sie einen Import mit dem Treiber `sqlite`, dem Pfad der Datei als Datenbank, dem
Schlüssel `id` und dieser Abfrage an:

```sql
SELECT id, name, origin, kind, price, description FROM teas WHERE kind <> 'herbal' ORDER BY id
```

**TimescaleDB** ist PostgreSQL mit einer Erweiterung und hat deshalb keine eigene Quelle: Lesen Sie es mit dem Treiber
`pgsql` dieser Quelle. Seine Funktionen sind gewöhnliche Funktionen in einem `SELECT` (`time_bucket`, `first`,
`last`, Continuous Aggregates als Views), zum Beispiel

```sql
SELECT time_bucket('1 hour', time) AS hour, room, avg(temperature) AS temperature
FROM climate WHERE time > now() - interval '1 day' GROUP BY hour, room ORDER BY hour, room
```

### Geheimnisse

Eine Quelle speichert nie ein Passwort. Ihre Option vom Typ `secret` enthält den **Namen** eines Geheimnisses, und
der Wert wird gelesen, wenn ein Lauf ihn braucht:

- `<Name>`: `sqliimport.ini [SecretSettings] Secrets[<Name>]=<Wert>`, gesetzt in den eigenen Einstellungen der
  Website (`settings/override/sqliimport.ini.append.php` oder ein Siteaccess), die nicht in ein Repository gehören;
- `env:<VARIABLE>`: eine Umgebungsvariable des PHP-Prozesses (der PHP-FPM-Pool, der Worker).

**Importe, Einstellungen** listet die Namen der gesetzten Geheimnisse, nie einen Wert. Meldungen und Protokolle
zeigen ein Geheimnis als `[secret <Name>]`, auch wenn ein Server es in einem Fehler zitiert.

**Ab Exponential 6.0.15** antwortet zuerst der Geheimnisspeicher des Kernels: Werte, die unter **Setup, Secrets**
gesetzt sind (verschlüsselt gespeichert, nie wieder angezeigt; der Bereich dieses Werkzeugs ist `sqliimport`),
`secrets.ini` und `env:`; `sqliimport.ini [SecretSettings] Secrets[]` bleibt als Rückfall, bestehende Importe brauchen
also keine Änderung. Um einen Wert umzuziehen, setzen Sie ihn unter Setup, Secrets mit demselben Namen und entfernen
ihn aus den Einstellungen. **Importe, Einstellungen** verlinkt dann auf diese Seite.

## Elasticsearch- oder OpenSearch-Index (`elasticsearch`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Dokumente eines Elasticsearch- oder OpenSearch-Index, eines Alias oder eines
Index-Musters, gelesen über die Such-API per HTTP. Ein Datensatz ist ein Dokument: sein `_source`, verschachtelte
Objekte zu Feldnamen mit Punkt aufgelöst (`author.name`, `author.address.city`), eine Liste einfacher Werte bleibt eine
Liste (`tags`), eine Liste von Objekten wird nummeriert (`images.0.url`), dazu `_id` und `_index`. Einmal pro Lauf
blättert die Quelle durch die Suche und schreibt die Dokumente in das Verzeichnis des Laufs, ein JSON-Objekt pro Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | die Adresse des Clusters, `https://search.example.org:9200` (ohne Abfrage, ohne Zugangsdaten darin) | Pflicht |
| `index` | ein Index, ein Alias oder ein Muster: `articles`, `logs-2026-*`, `news,blog` | Pflicht |
| `auth` | `none`, `basic` (Benutzer und Passwort), `api_key`, `bearer` (ein Token) | `none` |
| `user` | bei `basic`: der Benutzername | |
| `password_secret` | bei `basic`: der **Name** des Geheimnisses mit dem Passwort | |
| `api_key_secret` | bei `api_key`: der **Name** des Geheimnisses mit dem kodierten API-Schlüssel (der Wert `encoded`, den Elasticsearch ausgibt; gesendet als `Authorization: ApiKey ...`) | |
| `token_secret` | bei `bearer`: der **Name** des Geheimnisses mit dem Token | |
| `query` | Query DSL als JSON: eine Abfrage (`{"term": {"status": "published"}}`) oder ein Body mit `query`, `sort` und `_source` | alle Dokumente |
| `source_fields` | die zu lesenden Felder von `_source`, eines pro Zeile (`title`, `author.*`) | alle |
| `sort` | Felder mit `:asc` oder `:desc`, durch Kommas getrennt: `published_at:desc,_id` | die Sortierung der Abfrage, sonst die Reihenfolge des Index |
| `page_size` | Dokumente pro Anfrage, 1 bis 10000 | `500` |
| `paging` | `auto`, `pit` (Point in Time), `search_after`, `scroll` | `auto` |
| `key` | das Feld, das ein Dokument benennt | `_id` |

**Nur lesend.** Die Quelle baut nur diese Anfragen: `GET /` (die Version), `POST <index>/_search` und die Anfragen, die
für diese Suche einen Point in Time oder einen Scroll öffnen, nutzen und schließen. Der Index wird geprüft, bevor
etwas gesendet wird (Buchstaben, Ziffern, `_ . - * +` und Kommas; kein Pfad wie `articles/_delete_by_query`, kein
entfernter Cluster `other:index`, nichts, was mit `_` oder `-` beginnt). Die Abfrage darf nur eine Abfrage oder ein
Body mit `query`, `sort` und `_source` auf oberster Ebene sein; alles andere (`size`, `aggs`, `pit`, `script_fields`,
`runtime_mappings`, `suggest`, `rescore` ...) wird abgelehnt, ebenso ein Skript an beliebiger Stelle (`script`,
`script_score`, `scripted_metric`, `script_fields`, `runtime_mappings`). Geben Sie dem Import einen Benutzer oder
API-Schlüssel nur mit dem Recht `read` auf den Index (und für die Version `monitor` auf dem Cluster; ohne dieses
arbeitet die Quelle trotzdem und blättert mit `search_after` oder einem Scroll).

**Blättern** (`auto`): Elasticsearch ab 7.10 über einen Point in Time (`_pit`) mit `search_after` und dem impliziten
Tie-Breaker `_shard_doc`; OpenSearch ab 2.4 über seinen Point in Time (`_search/point_in_time`), wenn der Import eine
Sortierung angibt; sonst `search_after` allein, wenn das letzte Feld der Sortierung eindeutig ist (`_id`), sonst ein
Scroll (Reihenfolge `_doc`), der auch alten Servern dient. Ein Point in Time oder Scroll bleibt zwischen zwei Seiten
zwei Minuten offen und wird am Ende geschlossen, auch wenn ein Lauf früher aufhört. `[SearchSourceSettings] MaxPages`
begrenzt die Seiten eines Laufs (1000).

**Verbindung testen** liest die Version (`GET /`) und sucht ein Dokument: Es zeigt den Server (`Elasticsearch 8.11.0`,
`OpenSearch 2.11.0`) und die Felder dieses Dokuments oder den Fehler mit der Begründung des Servers
(`index_not_found_exception`, `parsing_exception`). Ein Passwort, Schlüssel oder Token erscheint nie: Es wird als
`[secret <Name>]` maskiert, auch wenn ein Server es zitiert. Nichts wird gespeichert.

**Was der Server braucht:** die `http`- und `https`-Streams von PHP (`allow_url_fopen=On` und die Erweiterung
`openssl`) im PHP des Webservers und im PHP der Worker der Job-Warteschlange; der Katalog und der Schritt „Quelle“
listen sie. curl wird nicht gebraucht. Zertifikate werden geprüft, Antworten sind auf `[PlatformSettings]
MaxResponseMegabytes` begrenzt, das Zeitlimit ist `[ImportSettings] StreamTimeout`, und eine Weiterleitung auf einen
anderen Host verliert die Zugangsdaten. Ein Server in einem privaten oder reservierten Netz wird abgelehnt, außer bei
`[SourceSettings] AllowPrivateHosts=enabled` oder wenn er in `[SearchSourceSettings] AllowedHosts[]` steht (der übliche
Fall: ein Cluster im Intranet).

**Beispiel:** `share/examples/search-articles.ndjson` ist ein Schnappschuss von drei Artikeln, wie die Quelle ihn
schreibt. Eine Abfrage der veröffentlichten Artikel, die neuesten zuerst:

```json
{"query": {"term": {"status": "published"}}, "sort": [{"published_at": "desc"}]}
```

## DuckDB-Abfrage über Dateien (`duckdb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen einer DuckDB-Abfrage über Dateien auf dem Server: Parquet-, CSV- oder
JSON-Dateien (eine, mehrere oder ein Muster wie `var/import/exports/*.parquet`) oder eine DuckDB-Datenbankdatei. Ein
Datensatz ist eine Zeile, seine Felder sind die Spalten der Abfrage. Die Dateien sind die View `data` der Abfrage
(`SELECT * FROM data WHERE ...`); eine Datenbankdatei wird schreibgeschützt geöffnet, und die Abfrage nennt ihre
Tabellen. Einmal pro Lauf führt die Quelle die Abfrage seitenweise aus und schreibt die Zeilen in das Verzeichnis des
Laufs, ein JSON-Objekt pro Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `paths` | ein Pfad pro Zeile, innerhalb von `[SourceSettings] AllowedPaths[]`; ein Muster (`*`, `?`, `[...]`) liest jede passende Datei | Pflicht |
| `reader` | `auto` (nach dem Dateinamen: `.parquet`, `.csv`/`.tsv`, `.json`/`.ndjson`/`.jsonl`, `.duckdb`/`.db`), `parquet`, `csv` (Trennzeichen und Kopfzeile erkannt), `json`, `duckdb` | `auto` |
| `query` | ein einzelnes `SELECT` (oder `WITH ... SELECT`) | `SELECT * FROM data` |
| `page_size` | Zeilen pro Abfrage (`LIMIT` und `OFFSET` um die Abfrage); `0`: alle Zeilen auf einmal | `10000` |
| `key` | die Spalte, die eine Zeile benennt | die Position |

Geben Sie der Abfrage ein `ORDER BY`, wenn sie mehr Zeilen als eine Seite hat: Die Seiten sind eigene Abfragen, und
nur ein sortiertes Ergebnis gibt ihnen dieselbe Reihenfolge.

**Nur lesend**, dreifach geprüft:

- **Die Dateien:** Jeder Pfad muss in `[SourceSettings] AllowedPaths[]` liegen (der echte Pfad, ein Link nach außen
  wird also abgelehnt), `..` wird abgelehnt, ein Muster muss mindestens eine Datei treffen, höchstens
  `[DuckDbSourceSettings] MaxFiles`. Eine Datenbank ist eine einzelne Datei und wird mit `-readonly` geöffnet (FFI:
  `access_mode=READ_ONLY`). Adressen (`https://`, `s3://`) nur mit `[DuckDbSourceSettings] AllowRemoteFiles=enabled`;
  ein http-Host in einem privaten Netz nur, wenn er in `[DuckDbSourceSettings] AllowedHosts[]` steht; die
  DuckDB-Erweiterung `httpfs` muss installiert sein.
- **Die Abfrage** wird wie die der SQL-Quelle geprüft, im Dialekt von PostgreSQL (dessen Parser DuckDB verwendet:
  Dollar-Quoting, `E'...'`-Zeichenketten, verschachtelte Kommentare), und zusätzlich abgelehnt mit `COPY`, `ATTACH`,
  `DETACH`, `INSTALL`, `LOAD`, `SET`, `RESET`, `PRAGMA`, `EXPORT`, `IMPORT`, `CHECKPOINT`, `USE`, `SECRET`, den
  Funktionen, die andere Dateien lesen oder SQL ausführen (`read_csv`, `read_parquet`, `read_json`, `read_text`,
  `read_blob`, `glob`, `query`, `query_table`, `sqlite_scan`, `postgres_scan` ...), `getenv` und einer Datei als
  Tabelle (`FROM 'datei.csv'`, `FROM "datei.csv"`). Eine Spalte mit einem solchen Namen kann in Anführungszeichen
  stehen (`"query"`).
- **Die Sitzung:** Vor der Abfrage darf DuckDB Erweiterungen weder selbst installieren noch laden
  (`autoinstall_known_extensions`, `autoload_known_extensions` aus); mit `[DuckDbSourceSettings] Sandbox=enabled`
  (DuckDB ab 1.3) erreicht sie nur die Dateien des Imports (`allowed_paths` und `enable_external_access=false`);
  danach wird die Konfiguration gesperrt (`lock_configuration`), die Abfrage kann davon nichts ändern. Setzen Sie
  `Sandbox=disabled` nur für ein älteres DuckDB; dann hält allein die Prüfung der Abfrage es bei den Dateien.

**Treiber**, in dieser Reihenfolge (`[DuckDbSourceSettings] Driver`: `auto`, `cli`, `ffi`):

- **Das Programm duckdb** (`[DuckDbSourceSettings] Binary`, ein Name, gesucht im `PATH`, in `/usr/local/bin` und
  `/opt/duckdb`, oder ein voller Pfad). Es wird ohne Shell gestartet (die Argumente als Liste), mit `-json`, `-bail`,
  `-init /dev/null` (keine `~/.duckdbrc`) und einer reduzierten Umgebung (keine Geheimnisse des PHP-Prozesses), die
  Anweisungen über seine Eingabe. Es wird nach `[DuckDbSourceSettings] QueryTimeout` Sekunden (60) pro Seite beendet
  oder wenn die Ausgabe einer Seite größer als `MaxOutputMegabytes` (100) ist. Werte kommen, wie DuckDB sie als JSON
  schreibt (Zahlen, Text, Listen).
- **PHP-FFI mit libduckdb** (`[DuckDbSourceSettings] Library`, sonst `libduckdb.so` in `/usr/local/lib`, `/usr/lib64`,
  `/usr/lib` ...), wenn die PHP-Erweiterung FFI mit `ffi.enable=On` geladen ist (oder `preload` für das CLI der
  Worker). Werte kommen als Text; das Zeitlimit wird nicht durchgesetzt.

**Was der Server braucht**, wenn beides fehlt: das Kommandozeilenprogramm duckdb, eine einzelne Datei von duckdb.org
(installiert als `/usr/local/bin/duckdb`), auch für das PHP der Worker der Job-Warteschlange; oder die
PHP-Erweiterung FFI (RHEL: `php-ffi`; Debian: Teil des Pakets `php`) mit `libduckdb.so` von duckdb.org. Der Katalog
und der Schritt „Quelle“ listen beide Treiber, grün mit dem verwendeten, grau mit dem fehlenden, und was die Worker
bei ihrem letzten Lauf hatten.

**Verbindung testen** prüft die Dateien und die Abfrage, führt sie für ihre erste Zeile aus und zeigt die Spalten und
den Treiber oder den Fehler von DuckDB. Nichts wird gespeichert.

**Beispiel:** `share/examples/duckdb-teas.csv` (fünf Tees). Kopieren Sie die Datei in ein Verzeichnis von
`AllowedPaths[]` (zum Beispiel `var/import`) und legen Sie einen Import mit diesem Pfad, dem Schlüssel `id` und dieser
Abfrage an:

```sql
SELECT id, name, origin, kind, price FROM data WHERE kind <> 'herbal' ORDER BY id
```

## E-Mail-Postfach über IMAP (`imap`)

Gruppe: Inhalte. Herkunft: `remote`. Die Nachrichten eines IMAP-Postfachs (ein Presse-Postfach, eine Adresse für
Einsendungen): Ein Datensatz ist eine Nachricht. Einmal pro Lauf liest die Quelle die passenden Nachrichten in das
Verzeichnis des Laufs, eine pro Zeile; die Stichprobe des Editors liest die neuesten.

| Feld | Inhalt |
|---|---|
| `message_id` | die Message-ID ohne `<>` (der Schlüssel); eine Nachricht ohne sie bekommt `sha1-...` ihres Inhalts |
| `uid`, `mailbox` | die UID der Nachricht im Postfach und das Postfach |
| `from` | `name`, `address` des Absenders (`from.address`) |
| `to`, `cc`, `reply_to` | Listen aus `name`, `address` (`to.0.address`) |
| `subject` | der Betreff, nach UTF-8 dekodiert |
| `date` | das Datum in ISO 8601 mit Zeitzonenversatz (`2026-10-13T09:15:00+02:00`) |
| `text` | der Text (`text/plain`; sonst der HTML-Teil als Text) |
| `html` | der HTML-Teil, falls vorhanden |
| `summary` | der erste Absatz des Texts |
| `body` | der HTML-Teil, sonst der Text als HTML-Absätze |
| `attachments` | Liste aus `name`, `mime`, `size` (Bytes) der Anhänge, nie ihr Inhalt; `attachment_count` |
| `in_reply_to`, `size`, `truncated` | die beantwortete Nachricht, die Größe und ob nur die Kopfzeilen gelesen wurden |

Kopfzeilen werden aus kodierten Wörtern nach RFC 2047 und Parametern nach RFC 2231 dekodiert, Inhalte aus
Quoted-Printable und Base64 und aus ihrem Zeichensatz; 8-Bit-Text, der kein UTF-8 ist, gilt als Windows-1252.

| Option | Werte | Vorgabe |
|---|---|---|
| `host`, `port` | der IMAP-Server | Port `993` |
| `encryption` | `ssl` (TLS von Anfang an, Port 993), `tls` (STARTTLS, Port 143), `none` (nicht empfohlen: das Passwort geht im Klartext) | `ssl` |
| `user` | die Anmeldung | |
| `password` | der **Name** eines Geheimnisses, nie das Passwort | |
| `mailbox` | der Ordner | `INBOX` |
| `search` | IMAP-Suchkriterien: `ALL`, `UNSEEN`, `FROM "news@example.org"`, `SUBJECT "Presse"` ... | `ALL` |
| `days` | nur die Nachrichten der letzten Tage (ergänzt `SINCE`); `0`: alle | `0` |
| `mark_seen` | gelesene Nachrichten als gelesen markieren | nein |
| `attachments` | die Anhänge auflisten (Namen, Typen und Größen) | ja |
| `key` | das Schlüsselfeld | `message_id` |

**Der Import ändert das Postfach nicht**, solange `mark_seen` nicht angekreuzt ist: Das Postfach wird
schreibgeschützt geöffnet (`EXAMINE`, `OP_READONLY`), und Nachrichten werden gelesen, ohne `\Seen` zu setzen.
Zertifikate werden geprüft. Ein Mailserver in einem privaten Netz wird abgelehnt, außer bei
`AllowPrivateHosts=enabled` oder wenn er in `[ImapSourceSettings] AllowedHosts[]` steht. Eine Nachricht, die größer
als `[ImapSourceSettings] MaxMessageMegabytes` ist, wird nur mit ihren Kopfzeilen gelesen und als `truncated`
markiert; `[ImapSourceSettings] Timeout` begrenzt den Verbindungsaufbau und jede Antwort.

Wo die PHP-Erweiterung `imap` installiert ist, wird sie verwendet; ohne sie (sie ist seit PHP 8.4 nicht mehr im Kern)
spricht die Quelle IMAP selbst über einen TLS-Socket (`LOGIN`, `EXAMINE`/`SELECT`, `UID SEARCH`,
`UID FETCH BODY.PEEK[]`, `LOGOUT`). Dieser Client braucht Benutzer und Passwort in druckbarem ASCII.

Die Vorlage bildet eine Nachricht auf einen Artikel ab, der über die Remote-ID `mail-{message_id}` wiedergefunden
wird (bei langen IDs mit einem Hash gekürzt): Titel aus `subject`, Einleitung aus `summary`, Text aus `body` (beide
als Rich Text), Veröffentlichungsdatum aus `date`.

## MongoDB-Collection (`mongodb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Dokumente einer MongoDB-Collection (die Datenbank einer anderen Anwendung,
ein Produkt- oder Ereignisspeicher): Ein Datensatz ist ein Dokument. Einmal pro Lauf liest die Quelle die passenden
Dokumente seitenweise und schreibt sie in das Verzeichnis des Laufs, ein JSON-Objekt pro Zeile; die Stichprobe des
Editors liest die ersten.

| Option | Werte | Vorgabe |
|---|---|---|
| `uri` | ein Host (`mongo.example.org`), `host:port` oder eine `mongodb://`- oder `mongodb+srv://`-URI **ohne Benutzer und Passwort** (`mongodb://db1.example.org,db2.example.org/?replicaSet=rs0`; MongoDB Atlas: `mongodb+srv://cluster0.example.mongodb.net/?retryWrites=true&w=majority`) | Pflicht |
| `port` | nur bei einem Host; `0` ist 27017 | `0` |
| `tls` | die Verbindung verschlüsseln (Zertifikate werden geprüft); `mongodb+srv://` verwendet immer TLS | nein |
| `user` | der Datenbankbenutzer | |
| `password` | der **Name** eines Geheimnisses (siehe [Geheimnisse](#geheimnisse)), nie das Passwort | |
| `auth_source` | die Datenbank, in der der Benutzer angelegt ist; leer: die Datenbank der URI, sonst `admin` | |
| `database`, `collection` | was gelesen wird | Pflicht |
| `mode` | `find` (Filter, Projektion, Sortierung) oder `aggregate` (eine Aggregations-Pipeline aus lesenden Stufen) | `find` |
| `pipeline` | bei `aggregate`: eine JSON-Liste von Stufen, z. B. `[{"$match": {"status": "published"}}, {"$group": {"_id": "$author", "n": {"$sum": 1}}}]` | |
| `filter` | ein Abfragedokument in JSON, z. B. `{"status": "published"}`; Extended JSON für Typen: `{"$oid": "..."}`, `{"$date": "2026-01-01T00:00:00Z"}` | jedes Dokument |
| `projection` | die zu lesenden Felder, z. B. `{"title": 1, "author.name": 1}` | alle |
| `sort` | z. B. `{"published_at": -1}` (braucht das Blättern `skip`) | `_id` |
| `paging` | `id`: jede Seite nach der letzten `_id` (schnell, in `_id`-Reihenfolge); `skip`: Überspringen und Begrenzen (jede Sortierung; bei `aggregate` eine `$skip`- und eine `$limit`-Stufe pro Seite); `cursor`: eine Abfrage, in Stapeln von `batch_size` gelesen (jede Reihenfolge; die Vorgabe von `aggregate`) | `id` |
| `batch_size` | Dokumente pro Seite (höchstens `[MongoDbSourceSettings] MaxBatchSize`) | `500` |
| `limit` | höchstens so viele Dokumente; `0`: alle | `0` |
| `arrays` | `json` (`["a","b"]`) oder `join` (`a, b`; ein Array aus Dokumenten bleibt JSON) | `json` |
| `separator` | was zwischen den Werten eines verbundenen Arrays steht | `, ` |
| `read_preference` | `primary`, `primaryPreferred`, `secondaryPreferred`, `secondary`, `nearest` | `primary` |
| `key` | das Schlüsselfeld; ein verschachteltes Feld mit seinem Punktnamen (`meta.sku`) | `_id` |

**Felder.** Verschachtelte Dokumente werden zu Punktnamen flachgeklopft: `{"author": {"name": "...", "address":
{"city": "..."}}}` ergibt die Felder `author.name` und `author.address.city`. Dokumente, die tiefer als 32 Ebenen
verschachtelt sind, werden als JSON geschrieben. Die BSON-Typen werden als Text geschrieben:

| BSON | Feldwert |
|---|---|
| ObjectId | ihre 24 Hexziffern (`6717a1f0c2d4e5f601234561`) |
| Datum (UTCDateTime) | ISO 8601 in UTC: `2026-04-02T08:30:00Z`, mit Millisekunden, wenn es welche hat (`...08:30:00.250Z`) |
| Decimal128 | seine Dezimalzeichenkette (`12.50`), nie eine Gleitkommazahl |
| Binary | Base64; eine UUID (Untertyp 3 oder 4) als `0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0` |
| Int64, Int32, Double, Boolean, Null | wie sie sind |
| Timestamp | ISO 8601 seiner Sekunden |
| Regulärer Ausdruck, JavaScript | `/muster/flags`, der Code |
| Array | JSON oder eine verbundene Liste (Option `arrays`); BSON-Werte darin werden genauso geschrieben |

**Nur Lesen ist möglich.** Die Quelle führt nichts aus als ein Find (`MongoDB\Driver\Query`) mit Filter, Projektion
und Sortierung oder (Modus `aggregate`) den Befehl `aggregate`, als Lesebefehl gesendet (`executeReadCommand`):
keinen anderen Befehl, kein Schreiben. Abgelehnt werden, in jeder Tiefe von Filter, Projektion, Sortierung oder
Pipeline, die Operatoren, die Code auf dem Server ausführen oder schreiben: `$where`, `$function`, `$accumulator`,
`$out`, `$merge` (und `$currentOp`, `$listSessions`). Ein Filter muss ein JSON-Objekt sein. Eine Pipeline darf nur
diese lesenden Stufen haben: `$match`, `$project`, `$addFields`, `$set`, `$unset`, `$sort`, `$limit`, `$skip`,
`$unwind`, `$group`, `$bucket`, `$bucketAuto`, `$count`, `$facet`, `$lookup`, `$replaceRoot`, `$replaceWith`,
`$sample`, `$sortByCount`, `$densify`, `$fill`, `$setWindowFields`, `$geoNear` (nur als erste Stufe) und
`$unionWith`; die Pipelines in `$facet`, `$lookup` und `$unionWith` werden genauso geprüft, jede andere Stufe (`$out`,
`$merge`, `$currentOp`, `$collStats`, `$indexStats`, `$changeStream`, `$documents`, `$search` ...) wird abgelehnt. Im
Modus `aggregate` bleiben Filter, Projektion und Sortierung leer (sie sind Stufen `$match`, `$project` und `$sort`).
System-Collections (`system.*`) werden nicht gelesen und auch nicht über `$lookup` oder `$unionWith` verbunden, die
nur eine Collection derselben Datenbank nennen. Geben Sie dem Import einen Datenbankbenutzer, der nur die Rolle `read`
auf dieser Datenbank hat.

**Blättern einer Aggregation.** `cursor` (die Vorgabe einer Aggregation) sendet die Pipeline einmal und liest ihren
Cursor in Stapeln von `batch_size` (mit einer `$limit`-Stufe am Ende für die Stichprobe des Editors oder die Option
`limit`); `skip` hängt für jede Seite `{"$skip": n}` und `{"$limit": batch_size}` an die Pipeline, was eine Pipeline
mit fester Reihenfolge braucht (beenden Sie sie mit einem `$sort` auf ein eindeutiges Feld).

**MongoDB Atlas** (und andere gehostete Dienste) wird mit seiner `mongodb+srv://`-URI erreicht, wie Atlas sie unter
Connect zeigt, ohne `<username>:<password>@`: Der Benutzer ist die Option `user` und das Passwort ein Geheimnis.
`mongodb+srv://` verwendet immer TLS, und die Hosts, die sein SRV-Eintrag nennt, werden wie jeder Host geprüft (die
öffentlichen Hosts von Atlas bestehen). Ein Azure-Cosmos-DB-Konto der API für MongoDB wird genauso gelesen, mit seiner
Verbindungszeichenkette ohne das Passwort.

**Geheimnisse und Hosts.** Die URI darf weder Benutzer noch Passwort enthalten (auch keine URI-Optionen, die eines
tragen); der Benutzer ist eine Option und das Passwort der Name eines Geheimnisses, das dem Treiber als
Verbindungsoption übergeben und in jeder Meldung als `[secret <Name>]` maskiert wird. Ein Server in einem privaten
oder reservierten Netz wird abgelehnt, außer bei `[SourceSettings] AllowPrivateHosts=enabled` oder wenn er in
`[MongoDbSourceSettings] AllowedHosts[]` steht (jeder Host der URI; bei `mongodb+srv://` der SRV-Name oder jeder Host,
den sein SRV-Eintrag nennt). `[MongoDbSourceSettings] Timeout` begrenzt den Verbindungsaufbau, die Serverauswahl und
jede Abfrage auf dem Server (`maxTimeMS`).

**Verbindung testen.** Im Schritt Quelle verbindet *Verbindung testen* mit den Optionen des Formulars, liest das
erste passende Dokument und zeigt seine Felder oder den Fehler (maskiert). Nichts wird gespeichert.

**Treiber.** Die PECL-Erweiterung `mongodb` (1.15 oder neuer; die Composer-Bibliothek `mongodb/mongodb` wird nicht
gebraucht) im PHP der Worker der Job-Queue, die die Läufe lesen, und im PHP des Webservers für Test und Stichprobe im
Editor. RHEL: `php-pecl-mongodb` von Remi oder `pecl install mongodb`; Debian: `php-mongodb`; Plesk:
`pecl install mongodb` für sein PHP. Katalog und Schritt Quelle zeigen, ob dieser Server und die Worker sie haben.

**Beispiel:** `share/examples/mongodb-articles.ndjson` ist, was ein Lauf aus einer Collection von Artikeln liest
(drei Dokumente, flachgeklopft), um eine Zuordnung auszuprobieren; die anderen Beispiele des Katalogs sind
Konfigurationen.

## Redis-Schlüssel (`redis`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Schlüssel einer Redis-Datenbank, die zu einem Muster passen (ein Cache mit
Produktdaten, der Speicher einer anderen Anwendung): Ein Datensatz ist ein Schlüssel. Einmal pro Lauf durchläuft die
Quelle die Schlüssel mit `SCAN` und schreibt sie in das Verzeichnis des Laufs, ein JSON-Objekt pro Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `host`, `port` | der Redis-Server | Port `6379` |
| `tls` | die Verbindung verschlüsseln (das Zertifikat wird geprüft) | nein |
| `database` | der Datenbankindex (`SELECT`) | `0` |
| `user` | ein ACL-Benutzer (Redis 6 und neuer); leer: nur das Passwort | |
| `password` | der **Name** eines Geheimnisses, nie das Passwort; leer: kein `AUTH` | |
| `pattern` | die zu lesenden Schlüssel, wie `SCAN ... MATCH` es liest: `product:*`, `news:[0-9]*` | `*` |
| `types` | die zu lesenden Typen, durch Kommas getrennt: `hash`, `string`, `list`, `set`, `zset`, `stream`, `json` (RedisJSON) | alle sieben |
| `json_path` | RedisJSON-Schlüssel: der Pfad, den `JSON.GET` liest, `$` (das ganze Dokument) oder `$.product`; ein alter Pfad (`.product`) geht auch | `$` |
| `scan_count` | das `COUNT` jedes `SCAN` (ein Hinweis, wie viele Schlüssel der Server pro Schritt ansieht) | `100` |
| `max_items` | höchstens so viele Werte (Einträge) einer Liste, Menge, sortierten Menge oder eines Streams | `1000` |
| `limit` | höchstens so viele Schlüssel; `0`: alle | `0` |
| `decode_json` | ein Zeichenkettenwert, der ein JSON-Objekt oder eine JSON-Liste ist, wird als seine Felder gelesen (`value.title`) | ja |
| `arrays` | `keep` (eine Liste: `values.0`, `values.1` ...), `json` oder `join` | `keep` |
| `separator` | was zwischen den Werten einer verbundenen Liste steht | `, ` |
| `key` | das Schlüsselfeld | `_key` |

Jeder Datensatz hat `_key` (den Schlüssel), `_type` und `_ttl` (Sekunden, `-1` ohne Ablauf) und je nach Typ:

| Typ | Felder | Befehl |
|---|---|---|
| hash | seine Felder als Felder (ein Feld namens `_key`, `_type` oder `_ttl` wird weggelassen) | `HGETALL` |
| string | `value`: der Text, oder seine Felder, wenn er JSON ist | `GET` |
| list | `values`, die ersten `max_items` | `LRANGE` |
| set | `values`, sortiert, damit zwei Läufe denselben Datensatz ergeben | `SMEMBERS` |
| sorted set | `values` (die Elemente, nach Score) und `scores` | `ZRANGE ... WITHSCORES` |
| stream | `entries`: eine Liste aus `id` und den Feldern des Eintrags, die ersten `max_items` | `XRANGE - + COUNT` |
| json (RedisJSON, Typ `ReJSON-RL`) | die Felder des Dokuments als Felder, verschachtelte Objekte als Punktnamen (`dims.w`), Listen nach der Option `arrays`, Listen aus Objekten nummeriert (`variants.0.g`); ein Wert, der kein Objekt ist, als `value` | `JSON.GET <Schlüssel> <json_path>` |

Werte, die kein UTF-8 sind, werden als `base64:...` geschrieben. Schlüssel der Typen anderer Module (RedisBloom,
RedisTimeSeries ...) werden ausgelassen. Ein JSONPath (`$...`) liefert die Liste seiner Treffer; ein Treffer wird als
der Wert selbst gelesen.

**Nur Lesen ist möglich.** Nach `AUTH` und `SELECT` sendet die Quelle nur `SCAN`, `TYPE`, `TTL`, `HGETALL`, `GET`,
`LRANGE`, `SMEMBERS`, `ZRANGE`, `XRANGE` und `JSON.GET`; der Client lehnt jeden anderen Befehl ab (auch `JSON.SET`,
`JSON.DEL`, `JSON.MERGE` ...), bevor er den Server erreicht.
`KEYS` wird nie verwendet: `SCAN` durchläuft die Schlüssel schrittweise, ohne den Server zu blockieren, und ein
Schlüssel, den es doppelt liefert, wird einmal gelesen. Ein Lauf bricht ab, wenn mehr als
`[RedisSourceSettings] MaxScannedKeys` Schlüssel passen (engen Sie das Muster ein). Geben Sie dem Import einen
ACL-Benutzer, der nur lesen darf, z. B. `ACL SETUSER reader on >... ~product:* +@read` (`+@read` enthält
`JSON.GET`).

**Geheimnisse und Hosts.** Das Passwort ist der Name eines Geheimnisses und wird in jeder Meldung maskiert. Ein Server
in einem privaten oder reservierten Netz wird abgelehnt, außer bei `AllowPrivateHosts=enabled` oder wenn er in
`[RedisSourceSettings] AllowedHosts[]` steht. `[RedisSourceSettings] Timeout` begrenzt den Verbindungsaufbau und jede
Antwort.

**Verbindung testen.** *Verbindung testen* verbindet, sucht den ersten passenden Schlüssel der gewählten Typen (in
höchstens `[RedisSourceSettings] TestScanRounds` Schritten von `SCAN`) und zeigt seine Felder oder den maskierten
Fehler.

**Treiber.** Die PECL-Erweiterung `redis` (phpredis), wo sie installiert ist (RHEL: `php-pecl-redis` von Remi oder
`pecl install redis`; Debian: `php-redis`); ohne sie spricht ein kleiner Client der Erweiterung RESP über einen
Socket (Treiber `socket`, mit `openssl` für TLS), sodass die Quelle mit jedem PHP funktioniert.
`[RedisSourceSettings] Client` wählt: `auto` (die Vorgabe), `phpredis` oder `socket`. Der eingebaute Client lehnt
einen Wert ab, der größer als `MaxValueMegabytes` ist.

**Beispiel:** `share/examples/redis-products.ndjson` ist, was ein Lauf aus den Schlüsseln `product:*` liest (zwei
Hashes, eine JSON-Zeichenkette und eine Menge), um eine Zuordnung auszuprobieren.

## CouchDB-Datenbank (`couchdb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Dokumente einer CouchDB-Datenbank (auch IBM Cloudant und andere
Server, die die CouchDB-API sprechen), über HTTP gelesen: alle Dokumente (`_all_docs`), die Zeilen einer View eines
Design-Dokuments oder die Antwort einer Mango-Abfrage (`_find`). Ein Datensatz ist ein Dokument mit `_id` und `_rev`.
Einmal pro Lauf blättert die Quelle durch die Dokumente und schreibt sie in das Verzeichnis des Laufs, ein JSON-Objekt
pro Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | die Adresse von CouchDB, `https://couch.example.org:6984` (ohne Query und Zugangsdaten; ein Pfad für einen Reverse Proxy ist erlaubt) | Pflicht |
| `database` | der Name der Datenbank (Kleinbuchstaben, Ziffern und `_ $ ( ) + -`; nie ein Pfad) | Pflicht |
| `auth` | `none`, `basic` (Benutzer und Passwort bei jeder Anfrage), `cookie` (eine Sitzung: `POST /_session`, dann das Cookie `AuthSession`) | `none` |
| `user`, `password_secret` | der Benutzer und der **Name** des Geheimnisses mit dem Passwort | |
| `mode` | `all_docs`, `view` oder `mango` | `all_docs` |
| `design`, `view` | bei `view`: das Design-Dokument (`news` oder `_design/news`) und die View | |
| `include_docs` | `all_docs` und `view`: jedes Dokument lesen (sonst nur `_id` und `_rev` bzw. `_id`, `key` und `value` der Zeile einer View) | ja |
| `reduce` | `view`: die Ergebnisse der Reduce-Funktion lesen, nach Schlüssel gruppiert (`group=true`, ohne Dokumente) | nein |
| `keys` | `all_docs` und `view`: nur diese Schlüssel (eine JSON-Liste), mit `POST` in Stücken einer Seite gesendet | |
| `startkey`, `endkey` | `all_docs` und `view`: der Bereich der Schlüssel als JSON (`"2026-01-01"` mit den Anführungszeichen, `["news", 1]`) | |
| `selector` | `mango`: ein Selektor, z. B. `{"type": "article", "year": {"$gte": 2025}}` | jedes Dokument |
| `fields` | `mango`: die zu lesenden Felder, eines pro Zeile | alle |
| `sort` | `mango`: z. B. `[{"published_at": "desc"}]` (braucht einen Index auf den Feldern) | |
| `include_design` | `all_docs`: auch die Design-Dokumente (`_design/...`) lesen | nein |
| `page_size` | Dokumente pro Anfrage, 1 bis 10000 | `500` |
| `limit` | höchstens so viele Dokumente; `0`: alle | `0` |
| `arrays`, `separator` | Arrays als JSON (`["a","b"]`) oder verbundene Liste (`a, b`; Arrays aus Objekten bleiben JSON) | `json`, `, ` |
| `key` | das Schlüsselfeld | `_id` |

**Felder und Typen.** Verschachtelte Objekte werden wie bei der MongoDB-Quelle zu Punktnamen flachgeklopft
(`author.name`, `author.address.city`); Zeichenketten, Zahlen, `true`/`false` und `null` bleiben, wie sie sind, Arrays
werden JSON oder eine verbundene Liste. `_id` und `_rev` bleiben erhalten, ebenso die Stubs von `_attachments`
(`_attachments.photo.jpg.content_type` ...); die Anhänge selbst werden nicht gelesen. Gelöschte Dokumente und nicht
gefundene Schlüssel werden ausgelassen.

**Nur Lesen ist möglich.** Die Quelle baut nur diese Anfragen und prüft jede, bevor sie gesendet wird: `GET /` (die
Version), `POST /_session` (Cookie-Anmeldung), `GET` oder `POST <db>/_all_docs`, `POST <db>/_find` und `GET` oder
`POST <db>/_design/<doc>/_view/<view>`. Alles andere (`_bulk_docs`, `_update`, `_show`, Schreiben von `_design`,
`PUT`, `DELETE`, `_replicate`, `_users` ...) wird abgelehnt. Datenbank, Design-Dokument und View sind Namen, nie ein
Pfad. Ein Mango-Selektor darf in jeder Tiefe nur die Abfrageoperatoren von Mango verwenden (`$and`, `$or`, `$not`,
`$nor`, `$all`, `$elemMatch`, `$allMatch`, `$keyMapMatch`, `$lt`, `$lte`, `$eq`, `$ne`, `$gte`, `$gt`, `$exists`,
`$type`, `$in`, `$nin`, `$size`, `$mod`, `$regex`, `$beginsWith`). Geben Sie dem Import einen Benutzer, der nur
Mitglied (Leser) der Datenbank ist, ohne Admin-Rolle.

**Blättern.** `_all_docs` und Views fragen eine Zeile mehr als eine Seite ab und beginnen die nächste Seite bei dieser
Zeile (`startkey`, bei einer View zusätzlich `startkey_docid`, damit die Zeilen eines mehrfach ausgegebenen Schlüssels
weder verloren gehen noch doppelt gelesen werden); Mango blättert mit dem `bookmark` jeder Antwort; `keys` werden in
Stücken einer Seite gesendet. `[CouchDbSourceSettings] MaxPages` begrenzt die Anfragen eines Laufs.

**Geheimnisse und Hosts.** Das Passwort ist der Name eines Geheimnisses und wird in jeder Meldung als `[secret <Name>]`
maskiert; das Sitzungs-Cookie wird nie gezeigt. Ein Server in einem privaten oder reservierten Netz wird abgelehnt,
außer bei `[SourceSettings] AllowPrivateHosts=enabled` oder wenn er in `[CouchDbSourceSettings] AllowedHosts[]` steht.

**Verbindung testen** liest die Version (`GET /`, angezeigt als `CouchDB 3.4.2` oder `Cloudant ...`) und das erste
Dokument und zeigt seine Felder oder den Fehler mit dem Grund des Servers (`not_found`, `unauthorized`), maskiert.

**Was der Host braucht:** die `http`- und `https`-Streams von PHP (`allow_url_fopen=On`, die Erweiterung `openssl`),
wie bei der Elasticsearch-Quelle; Zertifikate werden geprüft, Antworten sind auf `[PlatformSettings]
MaxResponseMegabytes` begrenzt, die Zeitgrenze ist `[ImportSettings] StreamTimeout`.

**Beispiel:** `share/examples/couchdb-articles.ndjson` ist, was ein Lauf aus einer Datenbank mit Artikeln liest (drei
Dokumente, flachgeklopft).

## Couchbase-Abfrage (`couchbase`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Ergebnisse einer SQL++-Abfrage (N1QL) von Couchbase Server oder
Couchbase Capella, gelesen über die REST-API des Query-Dienstes: `POST /query/service` auf Port 8093, mit TLS 18093.
Ein Datensatz ist ein Ergebnis: ein Objekt, zu Punktnamen flachgeklopft (`stock.store`), ein einfacher Wert
(`SELECT RAW`) als `value`.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | der Query-Dienst: `https://cb.example.org:18093` (TLS) oder `http://cb.example.org:8093` | Pflicht |
| `user`, `password_secret` | ein Benutzer mit der Rolle Query Select auf den Keyspaces und der **Name** des Geheimnisses mit dem Passwort | |
| `statement` | ein `SELECT` (oder `WITH ... SELECT`) ohne Parameter | Pflicht |
| `query_context` | Bucket und Scope, in denen die Namen der Abfrage gelesen werden (Couchbase 7): `default:shop.inventory` | |
| `paging` | `offset` (`LIMIT` und `OFFSET`; geben Sie der Abfrage ein `ORDER BY`), `keyset` (nach dem letzten Wert eines eindeutigen Feldes), `none` (eine Anfrage) | `offset` |
| `keyset_field` | bei `keyset`: ein eindeutiges Feld der Ergebnisse (`id`, `sku`, `meta.id`) | |
| `scan_consistency` | `not_bounded` (wie der Index steht) oder `request_plus` (wartet, bis der Index jede Änderung vor dem Lauf hat) | `not_bounded` |
| `page_size` | Ergebnisse pro Anfrage, 1 bis 10000 | `500` |
| `limit` | höchstens so viele Ergebnisse; `0`: alle | `0` |
| `arrays`, `separator` | Arrays als JSON oder verbundene Liste | `json`, `, ` |
| `key` | das Schlüsselfeld; wählen Sie `META(x).id AS id` für die Dokument-ID | `id` |

**Nur Lesen ist möglich, doppelt.** Jede Anfrage trägt `readonly=true`, sodass schon der Query-Dienst jede Anweisung
ablehnt, die Daten ändert. Davor lässt die Quelle nur ein einzelnes `SELECT` oder `WITH ... SELECT` durch und lehnt
außerhalb von Zeichenketten, Bezeichnern in Backticks und Kommentaren die Wörter jeder anderen Anweisung ab: `INSERT`,
`UPSERT`, `UPDATE`, `DELETE`, `MERGE`, `CREATE`, `DROP`, `ALTER`, `GRANT`, `REVOKE`, `EXECUTE` (auch
`EXECUTE FUNCTION`), `INFER`, `PREPARE`, `BUILD`, `ADVISE`, `EXPLAIN`, `TRUNCATE`, `FLUSH`, die Transaktionsanweisungen
(`BEGIN`, `COMMIT`, `ROLLBACK`, `SAVEPOINT`, `SET`), `FUNCTION` und `CURL()` (eine Anfrage vom Server aus). Auch die
System-Keyspaces (`system:...`), Parameter (`$name`, `?`) und eine zweite Anweisung nach `;` werden abgelehnt. Ein
Feld, das so heißt, wird in Backticks geschrieben (`` p.`update` ``). Die einzige Anfrage ist
`POST <Server>/query/service`.

**Blättern.** `offset` umschließt die Anweisung als `SELECT RAW exp_page FROM (<Anweisung>) AS exp_page LIMIT n
OFFSET m` (eine `WITH`-Klausel bleibt davor); die Reihenfolge ist die der Abfrage, geben Sie ihr also ein `ORDER BY`
auf ein eindeutiges Feld. `keyset` umschließt sie als `... WHERE exp_page.<Feld> > $after ORDER BY exp_page.<Feld>
LIMIT n` mit dem letzten Wert der Seite als benanntem Parameter `$after`: schneller bei großen Ergebnissen, das Feld
muss in den Ergebnissen stehen. `none` sendet die Anweisung, wie sie ist, einmal. `[CouchbaseSourceSettings]
QueryTimeout` ist das `timeout` der Anfrage (30 Sekunden), `MaxPages` begrenzt die Abfragen eines Laufs.

**Typen.** JSON, wie es ist: Zeichenketten, Zahlen, `true`/`false`, `null`; verschachtelte Objekte als Punktfelder,
Arrays als JSON oder verbundene Liste. Felder, die `MISSING` sind, erscheinen nicht.

**Geheimnisse und Hosts.** Das Passwort ist der Name eines Geheimnisses und wird in jeder Meldung maskiert. Ein Server
in einem privaten oder reservierten Netz wird abgelehnt, außer bei `AllowPrivateHosts=enabled` oder wenn er in
`[CouchbaseSourceSettings] AllowedHosts[]` steht.

**Verbindung testen** führt die Anweisung für ein Ergebnis aus (`LIMIT 1`) und zeigt seine Felder oder den Fehler des
Query-Dienstes mit seinem Code (`12003: Keyspace not found ...`), maskiert.

**Was der Host braucht:** die `http`- und `https`-Streams von PHP, wie bei CouchDB; kein Couchbase-SDK.

**Beispiel:** `share/examples/couchbase-products.ndjson` ist, was ein Lauf aus einer Abfrage von Produkten liest.

## Azure-Cosmos-DB-Container (`cosmosdb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Elemente eines Azure-Cosmos-DB-Containers der API für NoSQL
(früher SQL-API), die eine Abfrage auswählt, gelesen über die REST-API und mit Fortsetzungstokens geblättert. Ein
Datensatz ist ein Element, verschachtelte Objekte zu Punktnamen flachgeklopft (`customer.id`). Ein Konto der **API für
MongoDB** wird mit der [MongoDB-Quelle](#mongodb-collection-mongodb) und seiner Verbindungszeichenkette gelesen
(`mongodb://<Konto>.mongo.cosmos.azure.com:10255/?ssl=true&replicaSet=globaldb` ohne das Passwort, das ein Geheimnis
ist); die APIs für Cassandra, Gremlin und Table werden nicht gelesen.

| Option | Werte | Vorgabe |
|---|---|---|
| `endpoint` | die URI des Kontos: `https://<Konto>.documents.azure.com` | Pflicht |
| `database`, `container` | Namen (Buchstaben, Ziffern, `_ . -`) | Pflicht |
| `auth` | `master_key`: der Schlüssel des Kontos signiert jede Anfrage (verwenden Sie den **schreibgeschützten** Schlüssel); `resource_token`: ein Ressourcentoken mit der Berechtigung Read | `master_key` |
| `key_secret` | der **Name** des Geheimnisses mit dem Schlüssel (Base64) oder dem Token | Pflicht |
| `query` | ein `SELECT` ohne Parameter, z. B. `SELECT c.id, c.title FROM c WHERE c.status = 'published'` | `SELECT * FROM c` |
| `cross_partition` | jede Partition abfragen | ja |
| `partition_key` | nur eine Partition: ihr Schlüsselwert als JSON (`"tea"`, `42`) | |
| `max_item_count` | Elemente pro Seite, 1 bis 1000 | `100` |
| `limit` | höchstens so viele Elemente; `0`: alle | `0` |
| `system_fields` | `_rid`, `_self`, `_etag` und `_attachments` behalten (`_ts`, die Zeit der letzten Änderung, bleibt immer) | nein |
| `arrays`, `separator` | Arrays als JSON oder verbundene Liste | `json`, `, ` |
| `key` | das Schlüsselfeld | `id` |

**Signatur.** Mit einem Kontoschlüssel signiert die Quelle jede Anfrage selbst, wie die REST-API es beschreibt:
HMAC-SHA256 mit dem dekodierten Schlüssel über Verb, Ressourcentyp (`docs`), Ressourcenlink
(`dbs/<db>/colls/<Container>`) und Datum (`x-ms-date`), gesendet als `Authorization: type=master&ver=1.0&sig=...`;
ein SDK wird nicht gebraucht. Ein Ressourcentoken wird gesendet, wie es ist.

**Nur Lesen ist möglich.** Die einzige Anfrage ist die Abfrage des Containers:
`POST .../dbs/<db>/colls/<Container>/docs` mit `x-ms-documentdb-isquery: True`. Die Abfrage muss ein `SELECT` sein;
Parameter (`@name`), benutzerdefinierte Funktionen (`udf.`, JavaScript auf dem Server) und die Wörter schreibender
Anweisungen (`INSERT`, `UPDATE`, `DELETE`, `UPSERT`, `MERGE`, `CREATE`, `DROP`, `ALTER`, `EXEC`, `PATCH`) außerhalb von
Zeichenketten werden abgelehnt; ein so benanntes Feld wird als `c["update"]` gelesen. Die Abfragesprache der API für
NoSQL hat keine Anweisung, die schreibt. Verwenden Sie den **schreibgeschützten** Schlüssel des Kontos (Schlüssel,
Schreibgeschützte Schlüssel) oder ein Ressourcentoken mit der Berechtigung Read: Dann lehnt auch das Konto jedes
Schreiben ab.

**Blättern.** Jede Antwort gibt ein Fortsetzungstoken (`x-ms-continuation`), wenn weitere Elemente folgen; die Quelle
sendet es mit der nächsten Anfrage, bis keines mehr kommt. `[CosmosDbSourceSettings] MaxPages` begrenzt die Seiten
eines Laufs. Über Partitionen hinweg bedient das Gateway einen Filter und eine Projektion; `ORDER BY`, `TOP`,
`DISTINCT`, `GROUP BY` und Aggregate über Partitionen lehnt das Gateway ab ("The provided cross partition query can not
be directly served by the gateway"): Geben Sie dafür einen Partitionsschlüssel an.

**Typen.** JSON, wie es ist; verschachtelte Objekte als Punktfelder, Arrays als JSON oder verbundene Liste; `_ts` ist
die Zeit der letzten Änderung in Sekunden seit 1970.

**Geheimnisse und Hosts.** Schlüssel oder Token sind der Name eines Geheimnisses und werden in jeder Meldung maskiert
(auch URL-kodiert). Ein Endpunkt in einem privaten oder reservierten Netz (der Emulator auf `https://localhost:8081`,
ein privater Endpunkt) wird abgelehnt, außer bei `AllowPrivateHosts=enabled` oder wenn er in
`[CosmosDbSourceSettings] AllowedHosts[]` steht.

**Verbindung testen** liest das erste Element der Abfrage und zeigt seine Felder oder den Fehler mit dem Code des
Kontos (`Unauthorized`, `NotFound`, `BadRequest: ...`), maskiert.

**Was der Host braucht:** den `https`-Stream von PHP (`allow_url_fopen=On`, `openssl`) und `hash_hmac` (Teil von PHP).

**Beispiel:** `share/examples/cosmosdb-orders.ndjson` ist, was ein Lauf aus einem Container mit Bestellungen liest.

## Amazon-DynamoDB-Tabelle (`dynamodb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Items einer DynamoDB-Tabelle oder eines ihrer Indizes, gelesen mit
`Scan` oder `Query` über die HTTPS-API von DynamoDB: Ein Datensatz ist ein Item. Die Anfragen werden mit AWS Signature
Version 4 von einer kleinen Klasse der Erweiterung signiert (kein AWS-SDK). Einmal pro Lauf blättert die Quelle durch
die Items und schreibt sie in das Verzeichnis des Laufs, ein JSON-Objekt pro Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `region` | die AWS-Region der Tabelle: `eu-central-1` | erforderlich |
| `endpoint` | leer: `https://dynamodb.<region>.amazonaws.com`; sonst DynamoDB Local oder ein kompatibler Dienst (`http://localhost:8000`) | |
| `access_key_id` | die Zugriffsschlüssel-ID eines IAM-Benutzers oder einer Rolle (`AKIA...`) | erforderlich |
| `secret_key_secret` | der **Name** des Geheimnisses mit dem geheimen Zugriffsschlüssel (siehe [Geheimnisse](#geheimnisse)) | erforderlich |
| `session_token_secret` | nur bei temporären Zugangsdaten (STS): der Name des Geheimnisses mit dem Session-Token | |
| `table` | die Tabelle | erforderlich |
| `operation` | `scan` (jedes Item, oder die des Filters) oder `query` (die Items eines Partitionsschlüssels) | `scan` |
| `index_name` | ein globaler oder lokaler Sekundärindex (`IndexName`) | die Tabelle |
| `key_condition` | nur bei `query`, dort erforderlich: `KeyConditionExpression`, z. B. `customer_id = :c AND created_at > :d` | |
| `filter` | `FilterExpression`, z. B. `#s = :p AND price < :max` | |
| `names` | `ExpressionAttributeNames` als JSON: `{"#s": "status"}` | |
| `values` | `ExpressionAttributeValues` als JSON in der DynamoDB-Form: `{":p": {"S": "published"}, ":max": {"N": "20"}}` | |
| `projection` | `ProjectionExpression`: die zu lesenden Attribute | alle |
| `consistent_read` | `ConsistentRead` (nicht bei einem globalen Sekundärindex) | nein |
| `scan_forward` | nur bei `query`: aufsteigender Sortierschlüssel (`ScanIndexForward`) | ja |
| `page_size` | `Limit`: pro Anfrage geprüfte Items (eine Antwort ist außerdem höchstens 1 MB groß) | `100` |
| `limit` | höchstens so viele Items; `0`: alle | `0` |
| `sets` | Mengen aus Texten, Zahlen und Binärwerten als verbundene Liste (`join`) oder JSON (`json`) | `join` |
| `separator` | was zwischen den Werten einer verbundenen Menge steht | `, ` |
| `key` | das Attribut, das ein Item benennt (sein Partitionsschlüssel); leer: die Position | |

**Felder.** Jedes Attribut ist ein Feld; Maps werden zu Namen mit Punkten aufgelöst (`dimensions.weight_g`):

| AttributeValue | Feldwert |
|---|---|
| `S` | der Text |
| `N` | sein Dezimaltext (`12.50`, auch jenseits der Zahlen von PHP), nie eine Gleitkommazahl |
| `B` | Base64, wie DynamoDB es sendet |
| `BOOL`, `NULL` | `true`/`false`, `null` |
| `M` | zu Namen mit Punkten aufgelöst; eine leere Map ist `{}` |
| `L` | JSON (`[{"size":"50g"},"7",false]`) |
| `SS`, `NS`, `BS` | eine verbundene Liste (`green, japan`) oder JSON (Option `sets`) |

**Nur Lesen ist möglich.** Die Quelle sendet nur die Operationen `Scan` und `Query` (und `DescribeTable` für
*Verbindung testen*); jeder andere Name (`PutItem`, `UpdateItem`, `DeleteItem`, `BatchWriteItem`,
`TransactWriteItems`, `ExecuteStatement` für PartiQL ...) wird abgelehnt, bevor eine Anfrage entsteht. Ausdrücke
wählen und formen nur Items; ein Update- oder Bedingungsausdruck wird nie gesendet. Geben Sie dem Zugriffsschlüssel
eine IAM-Richtlinie, die nur Lesen erlaubt:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["dynamodb:Scan", "dynamodb:Query", "dynamodb:DescribeTable"],
    "Resource": ["arn:aws:dynamodb:eu-central-1:123456789012:table/products",
                 "arn:aws:dynamodb:eu-central-1:123456789012:table/products/index/*"]
  }]
}
```

`dynamodb:DescribeTable` braucht nur *Verbindung testen* (Status und Anzahl der Items); ohne das Recht liest der Test
trotzdem ein Item.

**Geheimnisse und Hosts.** Der geheime Zugriffsschlüssel und das Session-Token sind Namen von Geheimnissen; der
Schlüssel wird nie gesendet (nur die Signatur), und beide werden in jeder Meldung maskiert. Ein eigener Endpunkt in
einem privaten oder reservierten Netz wird abgelehnt, außer er steht in `[DynamoDbSourceSettings] AllowedHosts[]`.

**Blättern.** `Limit` Items pro Anfrage und `ExclusiveStartKey` mit dem `LastEvaluatedKey` der Antwort, bis keiner
mehr kommt; höchstens `[DynamoDbSourceSettings] MaxPages` Anfragen pro Lauf. Drosselungen
(`ProvisionedThroughputExceededException`, `ThrottlingException`) und 5xx-Antworten werden nach 1, 2, 4 und 8 Sekunden
wiederholt.

**Verbindung testen.** `DescribeTable` und eine Seite mit einem Item des Scans oder der Abfrage; zeigt die Felder oder
den maskierten Fehler.

**Was der Host braucht.** Den https-Stream von PHP mit `allow_url_fopen=On` und die Erweiterung `openssl` (auf RHEL und
Debian Teil des php-Pakets), im PHP des Webservers und der Worker der Job-Warteschlange.

**Beispiel:** `share/examples/dynamodb-products.ndjson` ist, was ein Lauf aus einer Produkttabelle liest (drei Items).

## Google Cloud: Firestore (`firestore`) und BigQuery (`bigquery`)

Beide lesen mit einem **Dienstkonto**: Seine JSON-Schlüsseldatei ist der Wert eines Geheimnisses, der Import nennt das
Geheimnis (`credentials_secret`). Die Erweiterung signiert mit dem Schlüssel ein JWT (RS256, `openssl_sign`), tauscht
es bei `https://oauth2.googleapis.com/token` gegen ein Zugriffstoken (der `token_uri` der Schlüsseldatei wird nicht
befolgt) und behält das Token für den Rest des Laufs. Schlüssel, JWT und Token werden in jeder Meldung maskiert. Was
das Dienstkonto darf, entscheiden seine IAM-Rollen: Geben Sie ihm nur die unten genannten Leserollen.
`[GoogleCloudSourceSettings] MaxPages` begrenzt die Anfragen eines Laufs.

### Firestore-Collection (`firestore`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Dokumente einer Collection, einer Subcollection oder einer
Collection-Gruppe einer Firestore-Datenbank (nativer Modus), gelesen über die Firestore-REST-API v1: Ein Datensatz ist
ein Dokument.

| Option | Werte | Vorgabe |
|---|---|---|
| `project` | das Google-Cloud-Projekt | erforderlich |
| `database` | die Datenbank-ID | `(default)` |
| `credentials_secret` | der **Name** des Geheimnisses mit dem JSON-Schlüssel des Dienstkontos | erforderlich |
| `scope` | `read_only` (`cloud-platform.read-only`) oder `datastore` (wenn der Nur-Lese-Scope abgelehnt wird; die Rolle hält es lesend) | `read_only` |
| `mode` | `query` (eine strukturierte Abfrage, `documents:runQuery`) oder `list` (die Liste der Collection, ohne Filter) | `query` |
| `collection` | `articles`, oder eine Subcollection über ihren Pfad: `customers/c-1001/orders` | erforderlich |
| `all_descendants` | nur `query`: jede Collection dieses Namens unterhalb des Pfads (eine Collection-Gruppe) | nein |
| `where` | nur `query`: das `where` einer strukturierten Abfrage, z. B. `{"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}` | alle |
| `order_by` | `published_at:desc,title`; der Dokumentname ist immer die letzte Ordnung | der Name |
| `fields` | die zu lesenden Felder, eines pro Zeile (`select`, bzw. `mask.fieldPaths` einer Liste) | alle |
| `paging` | `cursor` (`startAt` das letzte Dokument) oder `offset` | `cursor` |
| `page_size` | Dokumente pro Anfrage | `300` |
| `limit` | höchstens so viele Dokumente; `0`: alle | `0` |
| `arrays` | `json` oder `join` (ein Array aus Maps bleibt JSON) | `json` |
| `separator` | was zwischen den Werten eines verbundenen Arrays steht | `, ` |
| `key` | `_id` (die Dokument-ID) oder `_path` (auch in einer Collection-Gruppe eindeutig) | `_id` |

Jeder Datensatz hat `_id`, `_path` (unterhalb der Dokumente der Datenbank, `articles/a-1`), `_create_time` und
`_update_time`. Die typisierten Werte:

| Firestore-Wert | Feldwert |
|---|---|
| `stringValue`, `timestampValue` | der Text; ein Zeitstempel in ISO 8601, wie Firestore ihn liefert (`2026-02-01T09:00:00.250Z`) |
| `integerValue` | eine Zahl, oder ihr Text jenseits der Ganzzahlen von PHP |
| `doubleValue`, `booleanValue`, `nullValue` | wie sie sind (`NaN` und `Infinity` als Text) |
| `mapValue` | zu Namen mit Punkten aufgelöst (`author.name`); eine leere Map ist `{}` |
| `arrayValue` | JSON, oder eine verbundene Liste (Option `arrays`) |
| `referenceValue` | der Pfad des Dokuments, auf das er zeigt (`authors/au-1`) |
| `geoPointValue` | `Breite,Länge` (`48.137,11.575`) |
| `bytesValue` | Base64 |

**Nur Lesen ist möglich.** Die Quelle baut nur `documents:runQuery` (POST, eine strukturierte Abfrage: `from`,
`where`, `orderBy`, `select`, `limit`, `offset`, `startAt`) und die Liste einer Collection (GET); `commit`, `write`,
`batchWrite`, `delete` und Transaktionen entstehen nie. Das `where` des Imports wird vor dem Senden geprüft: nur
`fieldFilter`, `compositeFilter` (`AND`, `OR`) und `unaryFilter` mit ihren Operatoren und typisierten Werten. Geben Sie
dem Dienstkonto nur die Rolle **`roles/datastore.viewer`**.

**Blättern.** Eine Abfrage blättert mit einem Cursor: die Ordnung des Imports plus der Dokumentname, und `startAt` die
Werte des letzten Dokuments einer Seite (oder mit `offset`, langsamer bei großen Collections); eine Liste blättert mit
`pageToken`.

**Verbindung testen.** Das Token und ein Dokument; zeigt die Felder oder den maskierten Fehler.

**Beispiel:** `share/examples/firestore-articles.ndjson` ist, was ein Lauf aus einer Collection von Artikeln liest.

### BigQuery-Abfrage (`bigquery`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen einer GoogleSQL-Abfrage (Standard-SQL), ausgeführt über die
BigQuery-REST-API v2 (`jobs.query` mit `useLegacySql=false`): Ein Datensatz ist eine Zeile.

| Option | Werte | Vorgabe |
|---|---|---|
| `project` | das Projekt, das den Abfragejob ausführt (und bezahlt) | erforderlich |
| `credentials_secret` | der **Name** des Geheimnisses mit dem JSON-Schlüssel des Dienstkontos | erforderlich |
| `location` | der Standort der Datasets (`EU`, `US`, `europe-west3`) | BigQuery ermittelt ihn |
| `query` | ein `SELECT` oder `WITH`, z. B. ``SELECT order_id, total FROM `example-project.shop.orders` `` | erforderlich |
| `maximum_bytes_billed` | eine Abfrage, die mehr scannen würde, schlägt fehl; `0`: `[GoogleCloudSourceSettings] MaximumBytesBilled` (1 GiB) | `0` |
| `page_size` | Zeilen pro Anfrage (`maxResults`) | `1000` |
| `limit` | höchstens so viele Zeilen; `0`: alle | `0` |
| `key` | die Spalte, die eine Zeile benennt; leer: die Position | |

**Felder.** Nach dem Schema des Ergebnisses:

| BigQuery-Typ | Feldwert |
|---|---|
| `INT64`, `NUMERIC`, `BIGNUMERIC` | der Text (`9007199254740993`, `12.50`), nie eine Gleitkommazahl |
| `FLOAT64` | eine Zahl (`NaN`, `Infinity` als Text) |
| `BOOL` | `true`/`false` |
| `TIMESTAMP` | ISO 8601 in UTC (`2026-04-02T08:30:00.25Z`) |
| `DATE`, `DATETIME`, `TIME`, `STRING`, `GEOGRAPHY`, `JSON` | der Text, den BigQuery liefert |
| `BYTES` | Base64 |
| `RECORD` (`STRUCT`) | zu Namen mit Punkten aufgelöst (`customer.name`) |
| eine `REPEATED`-Spalte | JSON (`["green","tea"]`, `[{"sku":"tea-1","qty":"2"}]`) |

**Nur Lesen ist möglich.** Die Abfrage muss ein einzelnes `SELECT` oder `WITH` sein. Außerhalb von
Zeichenkettenliteralen, Kommentaren und quotierten Namen lehnt die Quelle DML (`INSERT`, `UPDATE`, `DELETE`, `MERGE`,
`TRUNCATE`), DDL (`CREATE`, `ALTER`, `DROP`, `UNDROP`), Skripte (`DECLARE`, `SET`, `BEGIN`, `LOOP`, `WHILE`,
`EXECUTE IMMEDIATE`, `COMMIT` ...), `EXPORT DATA`, `LOAD DATA`, `CALL`, `GRANT`, `REVOKE`, `ASSERT`, `EXTERNAL_QUERY`
und eine zweite Anweisung nach einem Semikolon ab. Eine Raw-Zeichenkette mit einem Backslash vor einem
Anführungszeichen wird abgelehnt statt geraten. Der Scope des Tokens ist `https://www.googleapis.com/auth/bigquery`,
weil `bigquery.readonly` keinen Abfragejob ausführen darf: Was das Konto darf, ergibt sich aus seinen Rollen. Geben
Sie dem Dienstkonto **`roles/bigquery.jobUser`** auf dem Projekt, das den Job ausführt, und
**`roles/bigquery.dataViewer`** auf den Datasets, die es liest, sonst nichts.

**Kosten.** Jede Abfrage trägt `maximumBytesBilled`, sodass eine Abfrage, die mehr scannen würde, fehlschlägt, statt
mehr zu kosten. *Verbindung testen* ist ein **Probelauf** (Dry Run, kostenlos): Er zeigt die Spalten und die Bytes, die
die Abfrage verarbeiten würde, und schlägt fehl, wenn sie das Limit überschreiten.

**Blättern.** Ist der Job nicht sofort fertig, fragt die Quelle sein Ergebnis erneut ab (`getQueryResults`) und
blättert dann mit `pageToken`.

**Beispiel:** `share/examples/bigquery-orders.ndjson` ist, was ein Lauf aus einer Abfrage von Bestellungen liest.

**Was der Host braucht (beide).** Den https-Stream von PHP mit `allow_url_fopen=On` und die Erweiterung `openssl`, die
auch die Token-Anfrage signiert, im PHP des Webservers und der Worker der Job-Warteschlange.

## Firebase Realtime Database (`firebase_rtdb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Kinder eines Pfads einer Firebase Realtime Database, gelesen über
ihre REST-API: Ein Datensatz ist ein Kind, `_key` mit seinem Wert; verschachtelte Objekte werden zu Namen mit Punkten
aufgelöst (`author.name`), Arrays sind JSON oder verbunden, und ein Kind, das ein einfacher Wert ist, ergibt `_key` und
`value`. Ein Pfad, der einen einfachen Wert enthält, ist ein Datensatz.

| Option | Werte | Vorgabe |
|---|---|---|
| `database_url` | `https://<name>.firebaseio.com` oder `https://<name>.<region>.firebasedatabase.app` | erforderlich |
| `path` | der Knoten, dessen Kinder die Datensätze sind: `articles`, `catalog/products` | die Wurzel |
| `auth` | `service_account` (ein Google-Zugriffstoken), `database_secret` (veraltet) oder `none` (öffentliche Leseregeln) | `service_account` |
| `credentials_secret` | mit Dienstkonto: der **Name** des Geheimnisses mit seinem JSON-Schlüssel | |
| `database_secret` | mit Datenbank-Secret: der **Name** des Geheimnisses, das es enthält | |
| `shallow` | erst die Schlüssel lesen (`shallow=true`), dann die Kinder seitenweise: für einen großen Baum | nein |
| `page_size` | Kinder pro Anfrage | `500` |
| `limit` | höchstens so viele Kinder; `0`: alle | `0` |
| `arrays` | `json` oder `join` | `json` |
| `separator` | was zwischen den Werten eines verbundenen Arrays steht | `, ` |
| `key` | das Schlüsselfeld | `_key` |

**Nur Lesen ist möglich.** Die Quelle sendet nur `GET <pfad>.json` mit den Parametern `orderBy`, `limitToFirst`,
`startAt`, `endAt`, `shallow` und der Anmeldung; `PUT`, `PATCH`, `POST` und `DELETE` entstehen nie. Das Token eines
Dienstkontos fragt nach den Scopes `firebase.database` und `userinfo.email`; geben Sie dem Konto nur die Rolle
**`roles/firebasedatabase.viewer`**. Ein veraltetes Datenbank-Secret darf alles lesen und schreiben: Nehmen Sie lieber
ein Dienstkonto.

**Hosts.** Die Adresse muss eine Firebase-Datenbank sein; ein anderer Host (der Emulator,
`http://localhost:9000/?ns=<name>`) nur, wenn er in `[GoogleCloudSourceSettings] AllowedHosts[]` steht.

**Blättern.** In Schlüsselreihenfolge (`orderBy="$key"`: Schlüssel, die 32-Bit-Ganzzahlen sind, zuerst nach Wert,
dann die übrigen nach Text): `limitToFirst`, und `startAt` der letzte Schlüssel der vorigen Seite. Mit `shallow` fragt
eine erste Anfrage nur die Schlüssel ab, dann liest jede Seite ihre Schlüssel mit `startAt` und `endAt`, sodass keine
Anfrage den ganzen Baum lädt.

**Verbindung testen.** Die Anzahl der Kinder (eine Shallow-Anfrage) und die Felder des ersten Kindes, oder der
maskierte Fehler.

**Was der Host braucht.** Den https-Stream von PHP mit `allow_url_fopen=On` und `openssl`.

**Beispiel:** `share/examples/firebase-articles.ndjson` ist, was ein Lauf aus `/articles` liest.

## Cassandra- oder ScyllaDB-Abfrage (`cassandra`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen eines CQL-`SELECT` gegen Apache Cassandra oder ScyllaDB: Ein
Datensatz ist eine Zeile. Einmal pro Lauf blättert die Quelle durch das Ergebnis und schreibt die Zeilen in das
Verzeichnis des Laufs.

| Option | Werte | Vorgabe |
|---|---|---|
| `hosts` | Kontaktpunkte, durch Kommas getrennt, jeweils mit optionalem `:port`: `cassandra1.example.org, cassandra2.example.org:9142`; der erste, der antwortet, wird verwendet | erforderlich |
| `port` | der Port eines Kontaktpunkts ohne eigenen | `9042` |
| `tls` | die Verbindung verschlüsseln (das Zertifikat wird geprüft) | nein |
| `user` | die Rolle; leer: keine Anmeldung | |
| `password_secret` | der **Name** eines Geheimnisses, nie das Passwort | |
| `keyspace` | der Keyspace; leer: die Abfrage nennt ihre Tabellen mit Keyspace (`shop.products`) | |
| `query` | ein `SELECT`, z. B. `SELECT sku, title, price FROM products WHERE category = 'tea'` | erforderlich |
| `consistency` | `LOCAL_ONE`, `ONE`, `LOCAL_QUORUM`, `QUORUM`, `ALL` | `LOCAL_ONE` |
| `page_size` | Zeilen pro Seite | `500` |
| `limit` | höchstens so viele Zeilen; `0`: alle | `0` |
| `collections` | Listen und Mengen als `json` oder verbundene Liste (`join`) | `json` |
| `separator` | was zwischen den Werten einer verbundenen Liste steht | `, ` |
| `key` | die Spalte, die eine Zeile benennt; leer: die Position | |

**Felder.** `ascii`, `text`, `varchar` als Text; `int`, `bigint`, `smallint`, `tinyint`, `counter` als Zahlen;
`boolean`; `double`, `float` als Zahlen; `decimal` und `varint` als Text (`123.45`); `uuid`, `timeuuid` als
`8-4-4-4-12` Hexziffern; `timestamp` als ISO 8601 in UTC, mit Millisekunden, wenn er welche hat; `date` als
`JJJJ-MM-TT`; `time` als `HH:MM:SS.nnnnnnnnn`; `inet` als Adresse; `blob` (und Typen, die der eingebaute Client nicht
kennt, etwa `duration`) als Base64; `list`, `set` und `tuple` als JSON oder verbunden; `map` als JSON
(`{"room":"Hall 2"}`); ein benutzerdefinierter Typ zu Namen mit Punkten aufgelöst (`venue.city`).

**Nur Lesen ist möglich.** Die Abfrage muss ein einzelnes `SELECT` sein. Außerhalb von Zeichenkettenliteralen
(`'...'`, `$$...$$`), Kommentaren und quotierten Namen werden `INSERT`, `UPDATE`, `DELETE`, `BATCH`, `APPLY`, `BEGIN`,
`TRUNCATE`, `CREATE`, `ALTER`, `DROP`, `GRANT`, `REVOKE`, `USE` und `USING` sowie eine zweite Anweisung nach einem
Semikolon abgelehnt; jeder Treiber lehnt erneut ab, was nicht mit `SELECT` beginnt. Der Keyspace ist eine Option, kein
`USE` in der Abfrage. Geben Sie dem Import eine Rolle, die nur lesen darf: `GRANT SELECT ON KEYSPACE shop TO reader`.

**Geheimnisse und Hosts.** Das Passwort ist der Name eines Geheimnisses und wird in jeder Meldung maskiert. Ein
Kontaktpunkt in einem privaten oder reservierten Netz wird abgelehnt, außer bei `AllowPrivateHosts=enabled` oder wenn
er in `[CassandraSourceSettings] AllowedHosts[]` steht. `[CassandraSourceSettings] Timeout` begrenzt den
Verbindungsaufbau und jede Antwort.

**Blättern.** Die Seitengröße des Imports und der Paging-State jeder Seite, bis der Server keinen mehr liefert;
höchstens `[CassandraSourceSettings] MaxPages` Seiten pro Lauf.

**Verbindung testen.** Verbindet, liest eine Zeile und zeigt ihre Felder oder den maskierten Fehler.

**Treiber.** Die PHP-Erweiterung `cassandra` von DataStax, wo sie installiert ist (sie braucht den DataStax-C/C++-
Treiber; für aktuelle PHP-Versionen gibt es keine Pakete); ohne sie spricht ein kleiner Client der Erweiterung das
native CQL-Protokoll v4 über einen Socket (Treiber `socket`, mit `openssl` für TLS): `STARTUP`, `AUTHENTICATE` mit dem
`PasswordAuthenticator`, `QUERY` mit Seitengröße und Paging-State und das Dekodieren der oben genannten Typen. Er lehnt
einen Frame ab, der größer als `[CassandraSourceSettings] MaxFrameMegabytes` ist. `[CassandraSourceSettings] Client`
wählt: `auto` (die Vorgabe), `extension` oder `socket`. Der eingebaute Client ist gegen einen geskripteten Server
getestet, der die Frames der Protokollspezifikation sendet; gegen einen echten Cassandra- oder ScyllaDB-Server ist er
noch nicht gelaufen.

**Beispiel:** `share/examples/cassandra-events.ndjson` ist, was ein Lauf aus einer Tabelle von Veranstaltungen liest.

## Abfragesprachen anderer Datenbanken über HTTP (`neo4j`, `arangodb`, `clickhouse`, `snowflake`, `influxdb`)

Diese fünf Quellen senden eine Abfrage in der Sprache der Datenbank und schreiben ihre Zeilen in das Verzeichnis des
Laufs, ein JSON-Objekt pro Zeile. Gemeinsam ist ihnen:

- **Nur lesend, geprüft, bevor etwas gesendet wird.** Jede Abfrage prüft ein Lexer ihrer Sprache: Zeichenketten,
  Namen in Anführungszeichen und Kommentare werden so herausgenommen, wie der Server sie liest (Escapes, verdoppelte
  Anführungszeichen, Heredocs), sodass ein Schlüsselwort in einem Text nicht zählt und eine hinter einem
  Anführungszeichen oder Kommentar versteckte Anweisung gefunden wird. Was schreibt, verwaltet, Code ausführt oder
  aus der Datenbank hinausreicht, wird in jeder Tiefe abgelehnt, und eine abgelehnte Abfrage sendet nichts. Hat der
  Server einen Nur-Lese-Modus, nutzt die Quelle ihn zusätzlich (Neo4j `access-mode: READ` und Bolt-Modus `r`,
  ClickHouse `readonly`). Geben Sie dem Benutzer des Imports in jedem Fall nur Leserechte auf das, was er importiert.
- **Geheimnisse über ihren Namen.** Ein Passwort, Token oder privater Schlüssel ist der **Name** eines Geheimnisses
  (siehe *Geheimnisse* oben), nie der Wert; Meldungen zeigen es als `[secret <Name>]`, auch wenn ein Server es in einem
  Fehler zitiert.
- **Hosts.** Ein Server in einem privaten oder reservierten Netz (Intranet, Loopback) wird abgelehnt, außer mit
  `[SourceSettings] AllowPrivateHosts=enabled` oder wenn der Host in der eigenen `AllowedHosts[]` der Quelle steht
  (`[Neo4jSourceSettings]`, `[ArangoDbSourceSettings]`, `[ClickHouseSourceSettings]`, `[SnowflakeSourceSettings]`,
  `[InfluxDbSourceSettings]`). `MaxPages` im selben Abschnitt begrenzt die Anfragen eines Laufs.
- **HTTP** läuft über die `http`- und `https`-Streams von PHP (`allow_url_fopen=On` und die Erweiterung `openssl`, im
  PHP des Webservers und im PHP der Worker der Job-Queue; Katalog und Quellschritt zeigen sie): Zertifikate geprüft,
  Antworten begrenzt auf `[PlatformSettings] MaxResponseMegabytes`, Zeitlimit `[ImportSettings] StreamTimeout`, keine
  Zugangsdaten nach einer Umleitung auf einen anderen Host. curl wird nicht gebraucht.
- **Verschachtelte Werte** (eine Map, ein Objekt, ein Tupel) werden zu Feldnamen mit Punkt (`author.name`); eine Liste
  einfacher Werte bleibt eine Liste, eine Liste von Objekten wird nummeriert (`items.0.sku`).
- **Verbindung testen** führt die Abfrage für ihre erste Zeile aus und zeigt den Server, seine Version, wo er sie
  nennt, und die Felder, oder den Fehler mit dem Grund des Servers, maskiert. Nichts wird gespeichert.

### Neo4j und Memgraph (`neo4j`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen einer Cypher-Abfrage. Ein Knoten sind seine Eigenschaften mit
`_labels` (eine Liste) und `_element_id`; eine Beziehung ihre Eigenschaften mit `_type`, `_element_id`, `_start` und
`_end`; ein Pfad das JSON seiner Knoten und Beziehungen in ihrer Reihenfolge; Zeitwerte ISO 8601 (`2026-03-02`,
`2026-03-02T10:00:00.5+01:00`, `P1M2DT30S`); ein Punkt `x,y` (oder `x,y,z`); ganze Zahlen, die größer sind als die
von PHP, bleiben Text; Byte-Arrays `base64:...`. Eine Abfrage, die einen einzelnen Knoten oder eine Beziehung liefert
(`RETURN a`), gibt deren Felder direkt; bei mehreren Spalten steht jede unter ihrem Namen (`a.title`, `a._labels`,
`author`).

| Option | Werte | Vorgabe |
|---|---|---|
| `server` | `neo4j`, `memgraph` (nur Bolt) | `neo4j` |
| `address` | `https://graph.example.org:7473` (HTTP) oder `bolt://`, `bolt+s://`, `neo4j://`, `neo4j+s://` mit Host und Port (Bolt); kein Pfad, keine Zugangsdaten | Pflicht |
| `api` | `auto` (nach der Adresse), `tx` (transaktionaler HTTP-Endpunkt), `query` (Query API), `bolt` | `auto` |
| `database` | die Datenbank; nie `system` | HTTP `neo4j`, Bolt die Standarddatenbank |
| `user`, `password_secret` | der Benutzer und der **Name** des Geheimnisses mit dem Passwort (leerer Benutzer: keine Anmeldung) | |
| `query` | eine lesende Cypher-Abfrage | Pflicht |
| `parameters` | ein JSON-Objekt der `$parameters` der Abfrage | |
| `page_size` | Zeilen pro Anfrage (HTTP `LIMIT`, Bolt `PULL n`) | `1000` |
| `paging` | `auto`, `none` | `auto` |
| `key` | das Feld, das eine Zeile benennt (`id`, `_element_id`) | die Position |

Die drei Wege zum Server:

- **`tx`**: der transaktionale HTTP-Endpunkt von Neo4j, `POST /db/<Datenbank>/tx/commit` mit dem Header
  `access-mode: READ`, das Zeilenformat mit dem Graphformat für Labels und Typen (Neo4j 4 und 5).
- **`query`**: die Query API, `POST /db/<Datenbank>/query/v2` mit `"accessMode": "READ"` und typisiertem JSON (Neo4j
  ab 5.19, Aura).
- **`bolt`**: das Bolt-Protokoll, mit einem kleinen eigenen Client der Quelle über einen Socket (Bolt 4.0 bis 4.4:
  `HELLO`, `RUN` mit dem Modus `r`, `PULL` mit `n`, `DISCARD`, `GOODBYE`; nichts anderes wird je gesendet).
  `neo4j://` wird wie `bolt://` gelesen (eine direkte Verbindung, keine Routing-Tabelle); `+s` nutzt TLS mit geprüftem
  Zertifikat (ein selbst signiertes Zertifikat als vertrauenswürdige CA installieren). **Memgraph hat keine
  HTTP-API**: Es wird nur über Bolt gelesen (`bolt://memgraph.example.org:7687`), und die Quelle lehnt dafür eine
  `http`-Adresse ab.

**Nur lesend.** Außerhalb von Zeichenketten und Kommentaren darf die Abfrage `CREATE`, `MERGE`, `SET`, `DELETE`,
`DETACH`, `REMOVE`, `DROP`, `FOREACH`, `LOAD` (`LOAD CSV`), `IN TRANSACTIONS` (`CALL { ... } IN TRANSACTIONS`), die
Verwaltungsbefehle (`ALTER`, `GRANT`, `DENY`, `REVOKE`, `RENAME`, `START`, `STOP`, `TERMINATE`, `ENABLE`) und die von
Memgraph (`ANALYZE`, `FREE`, `STORAGE`, `REPLICATION`, `REGISTER`, `IMPORT`) nicht enthalten, auch keine zweite
Anweisung. `USE` ist mit dem Namen einer Datenbank erlaubt, nie `system`. Prozeduren und Funktionen: `dbms.*` wird
abgelehnt, `db.*` nur für die lesenden (`db.labels`, `db.relationshipTypes`, `db.propertyKeys`, `db.schema.*`,
`db.indexes`, `db.constraints`, `db.info`, `db.ping`, die Volltext- und Vektorabfragen), und jeder andere Name
(`apoc.*`, `gds.*`, Memgraph-Module wie `migrate.*`, `mg.*`) wird abgelehnt, wenn ein Teil davon schreibt, lädt oder
Code ausführt: `create`, `merge`, `set...`, `delete`, `remove`, `load`, `import`, `export`, `periodic`, `trigger`,
`refactor`, `cypher`, `do`, `lock`, `atomic`, `write`, `mutate`, `drop`, `project`, `config`, `uuid`, `ttl`, `custom`,
`systemdb`, `migrate` und die Konnektoren (`bolt`, `jdbc`, `mongo`, `redis`, `es`, `kafka` ...). Ein Name in
Backticks (`` `apoc`.`create`.node ``) wird so geprüft, wie der Server ihn liest. Geben Sie dem Import einen Benutzer
mit der Rolle `reader` (Neo4j) oder nur Leserechten.

**Blättern.** Über HTTP wird an die Abfrage `SKIP $sqliimport_skip LIMIT $sqliimport_limit` angehängt, wenn sie mit
einem `RETURN` ohne eigenes `SKIP` oder `LIMIT` endet und kein `UNION` hat; geben Sie ihr ein `ORDER BY` für eine
stabile Reihenfolge. Sonst (oder mit `paging` `none`) wird sie einmal so gesendet, wie sie ist. Über Bolt läuft die
Abfrage einmal, und die Zeilen kommen Seite für Seite mit `PULL n` aus demselben Ergebnis.

**Was der Host braucht:** für HTTP die Streams oben; für Bolt `stream_socket_client` (Teil von PHP) und für `+s` die
Erweiterung `openssl`. Eine Neo4j-Treiberbibliothek ist nicht nötig. `[Neo4jSourceSettings] Timeout` ist das
Zeitlimit von Bolt.

**Beispiel:** `share/examples/neo4j-articles.ndjson` ist ein Snapshot von drei Artikel-Knoten, wie die Quelle ihn für
`MATCH (a:Article) RETURN a ORDER BY a.id` schreibt. Mit Parametern:

```cypher
MATCH (a:Article)-[:WRITTEN_BY]->(p:Person)
WHERE a.status = $status
RETURN a.id AS id, a.title AS title, p.name AS author
ORDER BY a.id
```

### ArangoDB (`arangodb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Ergebnisse einer AQL-Abfrage über die HTTP-Cursor-API. Ein Dokument
wird aufgelöst, `_key`, `_id` und `_rev` (und `_from`, `_to` einer Kante) zuerst; ein Ergebnis, das kein Objekt ist
(eine Zahl, eine Zeichenkette, eine Liste), wird zum Feld `value`.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | der Server oder Coordinator, `https://arango.example.org:8529` | Pflicht |
| `database` | der Name der Datenbank | `_system` |
| `auth` | `basic` (Benutzer und Passwort), `bearer` (ein JWT), `none` | `basic` |
| `user`, `password_secret` | bei `basic`: der Benutzer und der **Name** des Geheimnisses mit dem Passwort | |
| `token_secret` | bei `bearer`: der **Name** des Geheimnisses mit dem JWT | |
| `query` | eine lesende AQL-Abfrage | Pflicht |
| `bind_vars` | ein JSON-Objekt der `@parameters` der Abfrage | |
| `page_size` | Ergebnisse pro Batch (`batchSize`) | `1000` |
| `stream` | ein Streaming-Cursor (`options.stream`), der den Speicher des Servers klein hält | ja |
| `key` | das Feld, das ein Ergebnis benennt | `_key` |

**Nur lesend.** Außerhalb von Zeichenketten, Namen in Backticks und Kommentaren darf die Abfrage `INSERT`, `UPDATE`,
`REPLACE`, `REMOVE`, `UPSERT`, die Funktionen, die Code aufrufen oder warten (`CALL`, `APPLY`, `SLEEP`, `V8`), und
Benutzerfunktionen (`NAMESPACE::NAME()`) nicht enthalten, auch kein `;`. Namen in Forward Ticks werden abgelehnt
(Backticks verwenden). Geben Sie dem Benutzer nur Lesezugriff auf die Datenbank (`ro`).

**Blättern:** `POST /_db/<Datenbank>/_api/cursor` mit `batchSize`, dann der nächste Batch mit
`POST /_api/cursor/<id>` (ArangoDB ab 3.7; `PUT` bei älteren Servern), solange `hasMore`. Ein Cursor, den ein früher
Halt offen lässt (die Stichprobe des Editors), wird gelöscht. **Verbindung testen** liest auch die Version
(`GET /_api/version`).

**Beispiel:** `share/examples/arangodb-articles.ndjson`, für

```aql
FOR a IN articles
  FILTER a.status == @status
  SORT a.published_at DESC
  RETURN a
```

### ClickHouse (`clickhouse`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen eines `SELECT` über die HTTP-Schnittstelle (Port 8123, oder
8443 mit TLS) im Format `JSONEachRow`. Die Typen sind so, wie ClickHouse sie in JSON schreibt: `Int64`, `UInt64` und
größere ganze Zahlen als Text (ohne Rundung), andere Zahlen als Zahlen, `Date` und `DateTime` als `2026-05-01` und
`2026-05-01 10:00:00`, ein benanntes `Tuple`, eine `Map` oder eine `JSON`-Spalte zu Feldnamen mit Punkt aufgelöst, ein
`Array` als Liste.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | die HTTP-Schnittstelle, `https://clickhouse.example.org:8443` | Pflicht |
| `database` | die Datenbank | `default` |
| `user`, `password_secret` | der Benutzer (gesendet als `X-ClickHouse-User`) und der **Name** des Geheimnisses mit dem Passwort (`X-ClickHouse-Key`); nie in der Adresse | `default` |
| `query` | ein einzelnes `SELECT` oder `WITH ... SELECT`, ohne `FORMAT` und `SETTINGS` | Pflicht |
| `readonly` | `2` (nur lesend, die Quelle darf ihre Grenzen senden), `1` (nur lesend, es werden keine Einstellungen gesendet) | `2` |
| `page_size` | Zeilen pro Anfrage | `10000` |
| `paging` | `auto` (`LIMIT` und `OFFSET` um die Abfrage), `none` | `auto` |
| `key` | die Spalte, die eine Zeile benennt | die Position |

**Nur lesend.** Jede Anfrage trägt `readonly=2` (oder `1`), sodass der Server selbst Schreibzugriffe, DDL und eine
Änderung von `readonly` ablehnt. Vorher muss die Abfrage mit `SELECT` oder `WITH` beginnen und darf außerhalb von
Zeichenketten, Namen in Anführungszeichen (beide mit Backslash-Escapes und verdoppelten Anführungszeichen) und
Kommentaren nicht enthalten: `INSERT`, `ALTER`, `CREATE`, `DROP`, `TRUNCATE`, `OPTIMIZE`, `SYSTEM`, `KILL`, `SET`,
`GRANT`, `REVOKE`, `RENAME`, `ATTACH`, `DETACH`, `EXCHANGE`, `DELETE`, `UPDATE`, `INTO OUTFILE`, `USE`, `BACKUP`,
`RESTORE`, die Klauseln `FORMAT` und `SETTINGS` (die Funktionen `format()` sind erlaubt), eine zweite Anweisung und
die Tabellenfunktionen, die aus dem Server hinausreichen: `url`, `s3`, `gcs`, `azureBlobStorage`, `file`, `remote`,
`remoteSecure`, `cluster`, `mysql`, `postgresql`, `mongodb`, `redis`, `sqlite`, `hdfs`, `jdbc`, `odbc`, `executable`,
`input`, `deltaLake`, `hudi`, `iceberg` und ihre `...Cluster`-Formen. `[ClickHouseSourceSettings]
AllowedTableFunctions[]` kann eine davon erlauben (zum Beispiel `s3` für einen Data Lake, den der Import lesen soll).
Heredocs (`$tag$...$tag$`) werden so gelesen, wie ClickHouse sie liest.

**Blättern:** `SELECT * FROM ( <Abfrage> ) LIMIT <page_size> OFFSET <n>`, bis eine Seite kürzer ist; geben Sie der
Abfrage ein `ORDER BY` für eine stabile Reihenfolge. Mit `readonly=2` setzt jede Anfrage auch `max_execution_time`
(`[ClickHouseSourceSettings] MaxExecutionTime`, 60) und, wenn `MaxResultRows` gesetzt ist, `max_result_rows` mit
`result_overflow_mode=throw`. **Verbindung testen** liest `version()` und die erste Zeile.

**Beispiel:** `share/examples/clickhouse-page-views.ndjson`, für

```sql
SELECT path, sum(views) AS views FROM page_views WHERE day >= today() - 7 GROUP BY path ORDER BY views DESC, path
```

### Snowflake (`snowflake`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen einer Abfrage über die SQL API v2
(`POST /api/v2/statements`) mit **Schlüsselpaar-Authentifizierung**: Die Quelle signiert ein JWT mit dem privaten
RSA-Schlüssel des Benutzers (RS256, openssl von PHP); sein Aussteller ist `<ACCOUNT>.<USER>.SHA256:<Fingerabdruck>`,
der Fingerabdruck aus dem öffentlichen Schlüssel genau so berechnet, wie `DESC USER` ihn in `RSA_PUBLIC_KEY_FP`
zeigt. Hinterlegen Sie den öffentlichen Schlüssel mit `ALTER USER ... SET RSA_PUBLIC_KEY=...`.

| Option | Werte | Vorgabe |
|---|---|---|
| `account` | die Konto-ID: `orgname-accountname` oder ein Locator mit seiner Region (`xy12345.eu-central-1`) | Pflicht |
| `base_url` | eine andere Adresse (ein Private Link) | `https://<account>.snowflakecomputing.com` |
| `user` | der Benutzer | Pflicht |
| `private_key_secret` | der **Name** des Geheimnisses mit dem privaten PEM-Schlüssel (`-----BEGIN PRIVATE KEY-----` oder `ENCRYPTED PRIVATE KEY`; ein Schlüssel auf einer Zeile mit `\n` geht auch) | Pflicht |
| `passphrase_secret` | bei einem verschlüsselten Schlüssel: der **Name** des Geheimnisses mit seiner Passphrase | |
| `warehouse`, `database`, `schema`, `role` | der Kontext der Anweisung | die Vorgaben des Benutzers |
| `query` | ein einzelnes `SELECT`, `WITH ... SELECT`, `SHOW` oder `DESCRIBE` | Pflicht |
| `key` | die Spalte, die eine Zeile benennt (Snowflake schreibt Spaltennamen groß) | die Position |

**Nur lesend, soweit die Quelle es sieht.** Außerhalb von Zeichenketten (`'...'` mit Backslash-Escapes und
verdoppelten Anführungszeichen, `$$...$$`), Namen in Anführungszeichen und Kommentaren (`--`, `//`, `/* */`) darf die
Abfrage `INSERT`, `UPDATE`, `DELETE`, `MERGE`, `CREATE`, `ALTER`, `DROP`, `TRUNCATE`, `GRANT`, `REVOKE`, `COPY`, `PUT`,
`REMOVE`, `CALL`, `EXECUTE` (`EXECUTE IMMEDIATE`), `USE`, `SET`, `UNSET`, `UNDROP`, `BEGIN`, `COMMIT`, `ROLLBACK`,
`INTO` nicht enthalten, auch keine `SYSTEM$`-Funktion und keine zweite Anweisung, und die Anfrage erlaubt eine
Anweisung (`MULTI_STATEMENT_COUNT` 1). **Snowflake hat keine Nur-Lese-Sitzung**: Geben Sie dem Benutzer eine Rolle mit
`SELECT` auf das, was der Import liest, und sonst nichts (kein `OWNERSHIP`, kein `USAGE` auf externe Funktionen oder
Prozeduren).

**Typen** (nach `resultSetMetaData.rowType`; die API sendet jeden Wert als Text): `FIXED` (`NUMBER`, `DECIMAL`) bleibt
Text, sodass keine Zahl gerundet wird; `REAL` eine Gleitkommazahl; `BOOLEAN` wahr oder falsch; `DATE` `2026-03-02`;
`TIME` `10:00:00.5`; `TIMESTAMP_NTZ` `2026-03-02T10:00:00.123`, `TIMESTAMP_LTZ` in UTC mit `Z`, `TIMESTAMP_TZ` mit
seinem Versatz (`2026-03-02T11:00:00+01:00`); `VARIANT`, `OBJECT` und `ARRAY` aus JSON dekodiert, ein Objekt unter dem
Namen der Spalte aufgelöst (`ADDRESS.city`); `BINARY` als Hex-Text; `NULL` null.

**Blättern:** die Partitionen des Ergebnisses (`resultSetMetaData.partitionInfo`): Die erste kommt mit der Antwort, die
anderen mit `GET /api/v2/statements/<handle>?partition=<n>`. Eine Anweisung, die noch läuft (`202`), wird jede Sekunde
erneut abgefragt, bis sie endet oder `[SnowflakeSourceSettings] StatementTimeout` (300 s, auch als `timeout` der
Anweisung gesendet) verstreicht. **Was der Host braucht:** den `https`-Stream und die Erweiterung `openssl` (sie
signiert auch das Token).

**Beispiel:** `share/examples/snowflake-orders.ndjson`, drei Bestellungen, wie die Quelle sie schreibt.

### InfluxDB (`influxdb`)

Gruppe: NoSQL-Datenbanken. Herkunft: `remote`. Die Zeilen einer **Flux**-Abfrage (InfluxDB 2, InfluxDB Cloud;
`POST /api/v2/query`, die Antwort als annotiertes CSV) oder einer **InfluxQL**-Abfrage (InfluxDB 1 und die
1.x-Kompatibilitäts-API von InfluxDB 2; `GET /query`, die Antwort als JSON). Ein Datensatz ist eine Zeile: `_time` in
ISO 8601 (RFC 3339), die Tags und Felder als Spalten, `_measurement`, und bei Flux `_start`, `_stop` sowie `_field`
und `_value`, außer die Abfrage pivotiert.

| Option | Werte | Vorgabe |
|---|---|---|
| `base_url` | der Server, `https://influx.example.org:8086`, oder die Adresse von InfluxDB Cloud | Pflicht |
| `language` | `flux`, `influxql` | `flux` |
| `org` | Flux: der Name oder die ID der Organisation | |
| `database`, `retention_policy` | InfluxQL: die Datenbank (oder der ihr zugeordnete Bucket) und die Retention Policy | |
| `auth` | `token` (`Authorization: Token ...`), `basic` (Benutzer und Passwort), `none` | `token` |
| `token_secret` | der **Name** des Geheimnisses mit dem Token | |
| `user`, `password_secret` | bei `basic` | |
| `query` | Flux, oder ein einzelnes InfluxQL-`SELECT` oder -`SHOW` | Pflicht |
| `pivot` | Flux: `pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")` anhängen, eine Zeile pro Zeitpunkt mit einer Spalte pro Feld | nein |
| `page_size` | InfluxQL: Punkte pro Serie und Anfrage | `10000` |
| `key` | die Spalte, die eine Zeile benennt | die Position |

**Nur lesend.** Flux: Nur diese Pakete dürfen importiert werden: `array`, `strings`, `date`, `math`, `regexp`,
`json`, `dict`, `types`, `join`, `interpolate`, `timezone`, `runtime`, `system`, `influxdata/influxdb`,
`influxdata/influxdb/schema`, `influxdata/influxdb/v1`, `influxdata/influxdb/sample`, `sampledata`, `experimental`
und seine lesenden Teile (`aggregate`, `array`, `date/boundaries`, `geo`, `json`, `oee`, `polyline`, `query`,
`record`, `table`, `dynamic`). Damit werden `http`, `sql`, `csv` aus einer Datei oder Adresse,
`influxdata/influxdb/secrets`, `socket`, `mqtt`, `kafka`, die Benachrichtigungs-Endpunkte (`slack`, `pagerduty`,
`opsgenie`, `telegram`, `sensu`, `discord`, `teams`, `victorops`, `zenoss`, `servicenow`, `bigpanda`, `alerta`,
`pushbullet`, `webexteams`) und jedes `contrib/...`-Paket abgelehnt. An jeder Stelle der Abfrage werden `to()`,
`wideTo()`, jedes `X.to()` (`experimental.to`, auch über einen Alias oder eine Variable) und `sleep()` abgelehnt,
ebenso String-Interpolation (`"${...}"`). InfluxQL: ein einzelnes `SELECT` oder `SHOW`, ohne `INTO` und ohne `DROP`,
`DELETE`, `CREATE`, `ALTER`, `GRANT`, `REVOKE`, `KILL` oder `SET PASSWORD`. Geben Sie dem Token nur Lesezugriff auf
den Bucket (eine Lese-Autorisierung von InfluxDB 2).

**Typen:** Flux nach der Annotation `#datatype`: `long` eine ganze Zahl, `unsignedLong` eine ganze Zahl (Text, wenn
größer als die von PHP), `double` eine Gleitkommazahl, `boolean` wahr oder falsch, `dateTime:RFC3339` Text wie
gesendet, der Rest Text; ein leerer Wert null (oder das `#default` der Tabelle); die Spalten `result` und `table`
entfallen; eine Fehlertabelle beendet den Lauf mit ihrer Meldung. InfluxQL so, wie die JSON-Antwort es liefert (`time`
als `_time`, RFC 3339).

**Blättern:** Ein InfluxQL-`SELECT` ohne eigenes `LIMIT`, `OFFSET`, `SLIMIT` oder `SOFFSET` bekommt
`LIMIT <page_size> OFFSET <n>` angehängt (pro Serie, wie InfluxQL zählt), bis keine Serie eine Seite füllt. Flux kommt
in einer Antwort: Grenzen Sie sie mit `range()` und `filter()` ein (Antworten sind auf `[PlatformSettings]
MaxResponseMegabytes` begrenzt); die Stichprobe des Editors hängt `limit(n: ...)` an. **Verbindung testen** zeigt die
Version des Servers aus `X-Influxdb-Version`.

**Beispiel:** `share/examples/influxdb-climate.ndjson`, drei pivotierte Messwerte, für

```flux
from(bucket: "sensors")
  |> range(start: -1d)
  |> filter(fn: (r) => r._measurement == "climate")
```

## LDAP-Verzeichnis (`ldap`)

Gruppe: Daten. Herkunft: `remote`. Die Einträge eines LDAP-Verzeichnisses (OpenLDAP, Active Directory, 389 Directory
Server und ähnliche): Ein Datensatz ist ein Eintrag einer Suche, seine Felder sind der DN (`dn`) und die Attribute des
Eintrags nach Namen. Einmal pro Lauf meldet sich die Quelle an, sucht und schreibt die Einträge in das Verzeichnis des
Laufs, einen pro Zeile; die Stichprobe des Editors liest die ersten. Ein Verzeichnis von Personen enthält
**personenbezogene Daten**: Importieren Sie nur die Attribute, die die Website braucht.

| Option | Werte | Vorgabe |
|---|---|---|
| `uri` | `ldaps://host[:port]` (TLS von Anfang an, Port 636) oder `ldap://host[:port]` (Port 389, mit StartTLS); ein Host ohne Schema ist `ldaps://` | |
| `starttls` | StartTLS auf einer `ldap://`-Verbindung | ja |
| `allow_plain` | eine unverschlüsselte Verbindung erlauben, nur zu `localhost` (`127.0.0.1`, `::1`) | nein |
| `bind_dn` | der Benutzer der Anmeldung (`cn=reader,ou=Services,dc=example,dc=org`; Active Directory auch `reader@example.org`); leer: anonym | |
| `password` | der **Name** eines Geheimnisses, nie das Passwort; nötig mit einem Bind-DN | |
| `base_dn` | wo die Suche beginnt, z. B. `ou=Staff,dc=example,dc=org` | |
| `filter` | ein LDAP-Filter (RFC 4515) | `(objectClass=*)` |
| `attributes` | die zu lesenden Attribute, eines pro Zeile; leer: alle Benutzerattribute. Ein aufgeführtes Attribut steht in jedem Datensatz (`null`, wenn ein Eintrag es nicht hat) | |
| `scope` | `sub` (die Basis und alles darunter), `one` (die Einträge direkt unter der Basis), `base` (nur der Basiseintrag) | `sub` |
| `page_size` | Einträge pro Seite des Paged-Results-Controls (RFC 2696); `0`: ohne Seiten, es gilt das Größenlimit des Servers (im Active Directory oft 1000) | `500` |
| `size_limit` | die meisten Einträge; `0`: alle | `0` |
| `binary` | binäre Attribute als `base64:<Daten>`, oder `skip`, um sie wegzulassen | `base64` |
| `multi_valued` | ein Attribut mit mehreren Werten als Liste (`array`; ein Wert bleibt ein Wert) oder verbunden (`join`) | `array` |
| `separator` | das Trennzeichen verbundener Werte | `; ` |
| `key` | das Schlüsselfeld: `dn` oder ein Attribut wie `uid`, `entryUUID`, `objectGUID_text` | `dn` |

**Nur lesend.** Die Quelle verbindet sich, meldet sich an und sucht; sie hat keinen Weg, einen Eintrag anzulegen, zu
ändern oder zu löschen. Geben Sie ihr einen Verzeichnisbenutzer, der nur den Teil des Baums lesen darf, den der
Import braucht.

**Verschlüsselt.** Die Verbindung ist `ldaps://` oder `ldap://` mit StartTLS, und das Zertifikat des Servers wird
geprüft (gegen die CA-Zertifikate des OpenLDAP-Clients des Systems, `TLS_CACERT` in `ldap.conf`). Eine unverschlüsselte
Verbindung wird abgelehnt, außer zu `localhost`, wenn der Import `allow_plain` ankreuzt. Ein Verzeichnisserver in einem
privaten oder reservierten Netz (das übliche Intranet-Verzeichnis, und `localhost`) wird abgelehnt, außer mit
`[SourceSettings] AllowPrivateHosts=enabled` oder wenn die Administration ihn in `[LdapSourceSettings] AllowedHosts[]`
einträgt. `[LdapSourceSettings] Timeout` (30 Sekunden) begrenzt den Verbindungsaufbau und jede Suche. Verweisen
(Referrals) wird nicht gefolgt.

**Der Filter wird geprüft**, nach der Grammatik von RFC 4515, bevor etwas gesendet wird: Gleichheit, Vorhandensein
(`(mail=*)`), Teilzeichenketten (`(cn=Sam*)`), `~=`, `>=`, `<=`, erweiterte Vergleiche
(`(memberOf:1.2.840.113556.1.4.1941:=cn=Press,...)`), `&`, `|` und `!`, höchstens 32 Ebenen tief. Sonderzeichen in
Werten werden maskiert: `\28` für `(`, `\29` für `)`, `\2a` für `*`, `\5c` für `\`. Ein Filter ohne äußere Klammern
bekommt sie. Auch die Attributnamen werden geprüft.

**Werte.** Ein Attribut mit einem Wert ist dieser Wert, eines mit mehreren eine Liste (`telephoneNumber.0`) oder, mit
`multi_valued=join`, die verbundenen Werte. Binäre Attribute (`jpegPhoto`, `thumbnailPhoto`, `objectGUID`,
`objectSid`, Zertifikate, ein Attribut mit der Option `;binary` und jeder Wert, der kein UTF-8-Text ist) werden als
`base64:<Daten>` geschrieben (die Zuordnung kann aus einem Foto ein Bild machen) oder mit `binary=skip` weggelassen.
`objectGUID` des Active Directory steht zusätzlich als Text in `objectGUID_text` (`12345678-9abc-...`), ein Schlüssel,
der bleibt, wenn ein Benutzer umbenannt wird. Passwortattribute (`userPassword`, `unicodePwd`, `sambaNTPassword`, die
LAPS-Passwörter ...) werden nie in einen Datensatz gelesen, auch wenn der Server sie liefert.

**Seiten.** Mit einer Seitengröße trägt jede Anfrage das Paged-Results-Control und das Cookie der vorigen Seite, bis
der Server keine weitere hat; dafür braucht die Quelle PHP 7.3 oder neuer. Ohne Seiten hört ein Server bei seinem
eigenen Größenlimit auf.

**Verbindung testen.** Im Schritt „Quelle“ verbindet *Verbindung testen*, meldet sich an, sucht den ersten Eintrag und
zeigt seine Felder oder den Fehler (ein Passwort erscheint nie: Es wird maskiert). Nichts wird gespeichert; es braucht
dieselbe Berechtigung wie das Speichern des Imports. Neben der Schaltfläche sagt *Womit dieser Server verbinden kann*,
ob dieses PHP die Erweiterung `ldap` hat und, nach dem ersten Lauf, ob die Worker der Job-Warteschlange sie haben.

**Was der Host braucht:** die PHP-Erweiterung `ldap` im PHP der Worker der Job-Warteschlange, die die Läufe lesen
(RHEL: `php-ldap`; Debian: `php-ldap`; Plesk: die Erweiterung `ldap` der PHP-Version), und im PHP des Webservers für
Test und Stichprobe des Editors.

**Beispiel:** `share/examples/ldap-staff.ndjson` ist der Schnappschuss von drei erfundenen Einträgen (was ein Lauf
schreibt). Der Katalog zeigt außerdem eine Konfiguration für ein OpenLDAP-Mitarbeiterverzeichnis und eine für die
Benutzer einer Active-Directory-Gruppe über StartTLS, mit `objectGUID_text` als Schlüssel.

## Seiten einer Website über ihre Sitemap (`sitemap`) und der Webseiten-Scraper (`webpage`)

Gruppe: Plattformen. Diese beiden Formate lesen öffentliche Webseiten selbst (der Ursprung `remote`): einmal pro
Lauf durchsuchen sie die Website und schreiben eine Momentaufnahme des Gelesenen (ein JSON-Objekt je Zeile im
Verzeichnis des Laufs), die dann jeder Abschnitt des Laufs liest. Stichprobe und Vorschau des Import-Editors lesen
nur die ersten Seiten (`[RunSettings] DryRunRows`).

**Es gelten die Nutzungsbedingungen der Website und ihre robots.txt. Lesen Sie nur öffentliche Seiten, die Sie
verwenden dürfen.** Das Lesen ist von Grund auf rücksichtsvoll und sicher:

- **robots.txt wird befolgt.** Sie wird einmal pro Host gelesen; es entscheidet die Gruppe des User-Agents (oder
  `*`) mit ihren Regeln `Allow` und `Disallow` (die längste passende Regel gewinnt, bei Gleichstand `Allow`; `*` und
  ein abschließendes `$` in Mustern) und ihrem `Crawl-delay`. Antwortet robots.txt mit 4xx, ist alles erlaubt; kann
  sie nicht gelesen werden (5xx, keine Antwort), ist nichts erlaubt. Eine nicht erlaubte Seite wird nie angefragt:
  sie wird eine fehlgeschlagene Zeile des Laufs mit dem Grund, und der Lauf endet *mit Fehlern abgeschlossen*.
- **Anfragen haben Abstand.** Zwischen zwei Anfragen an einen Host wartet das Lesen die Pause `delay` des Imports
  (1 Sekunde als Vorgabe), mindestens `[CrawlSettings] MinDelay`, und länger, wenn robots.txt ein längeres
  `Crawl-delay` verlangt.
- **Grenzen.** Ein Lauf liest höchstens `max_pages` Seiten, nie mehr als `[CrawlSettings] MaxPages` (2000). Jede
  Seite wird mit den Prüfungen und Grenzen einer Adressquelle gelesen: keine privaten oder reservierten Netze ohne
  `[SourceSettings] AllowPrivateHosts`, das Zeitlimit `[ImportSettings] StreamTimeout` und das Größenlimit
  `[SourceSettings] MaxSourceMegabytes`. Weiterleitungen verfolgt das Lesen selbst (höchstens drei), jedes Ziel wird
  erneut geprüft.
- **Gesendet wird nur die Anfrage.** Keine Cookies, keine Zugangsdaten (eine Adresse mit Benutzername oder Passwort
  wird abgelehnt) und der User-Agent aus `[CrawlSettings] UserAgent` (als Vorgabe
  `7x SQLI Import (Exponential; +<SiteURL>)`, damit der Betreiber einer Website sieht, wer liest).
- **Es wird kein JavaScript ausgeführt.** Eine Seite wird als HTML-Text geparst (DOMDocument ohne Netzzugriff, in
  UTF-8 nach dem Zeichensatz der Antwort oder der Seite); Skripte, Styles, Frames und Event-Attribute werden entfernt,
  bevor etwas daraus genommen wird. Eine Seite, deren Inhalt JavaScript aufbaut, bietet wenig zu lesen.

**Picks** (Option `picks`, einer je Zeile) benennen die Felder, die einer Seite entnommen werden: `feld=selektor`.
Ein Selektor ist CSS, eine praktische Teilmenge: ein Tag oder `*`, `#id`, `.klasse`, `[attr]`, `[attr=wert]` (und
`~=`, `^=`, `$=`, `*=`, `|=`), die Kombinatoren Nachfahre (ein Leerzeichen), `>`, `+`, `~` und `,` sowie
`:first-child`, `:last-child`, `:only-child`, `:nth-child(n)`, `:first-of-type`, `:last-of-type`. Am Ende nimmt
`::attr(name)` oder `@name` ein Attribut, `::html` das innere HTML und `::text` den Text (die Vorgabe). Ein Selektor,
der mit `xpath:` beginnt, ist XPath. Findet ein Pick mehrere Elemente, ergibt er eine Liste, findet er keines, einen
leeren Text. Relative Adressen in `href`, `src` und ähnlichen Attributen werden absolut gemacht. Ein Pick namens
`body` behält das innere HTML des Gefundenen (für ein Rich-Text-Feld) und ergänzt `body_text` mit dessen Text.

```
title=h1
image=meta[property=og:image]::attr(content)
body=article .content
author=xpath://span[@itemprop='author']
```

### `sitemap`

Die Seiten, die eine Sitemap aufführt (`sitemap.xml`, mit gzip komprimiert oder nicht, oder eine Textdatei mit
Adressen), auch über einen Sitemap-Index; für die Wurzel einer Website (`https://example.org/`) oder ihre
`robots.txt` die Zeilen `Sitemap:` der robots.txt (sonst `/sitemap.xml`). Gelesen werden nur Seiten und Sitemaps
derselben Website wie die Sitemap (ihr Host, mit oder ohne `www.`). Ein Datensatz je Seite: `url` (der Schlüssel), `url_hash` (der SHA-1 der Adresse,
für die Remote-ID), `lastmod`, `priority` und `changefreq` aus der Sitemap, `status` (HTTP), `title` (aus
`<title>`), `description` (die Meta-Beschreibung), `canonical`, `language` (aus `<html lang>`) und jeder Pick (ein
Pick `title` ersetzt den Seitentitel).

| Option | Werte | Vorgabe |
|---|---|---|
| `sitemap` | die Sitemap, ein Sitemap-Index oder die Wurzel einer Website (Pflicht) | |
| `include` | Muster der zu lesenden Adressen, eines je Zeile: ein Glob (`https://example.org/blog/*`, `/blog/*` für Pfad und Abfrage) oder `regex:<Ausdruck>` | jede Seite |
| `exclude` | Muster der Adressen, die nie gelesen werden (sie haben Vorrang vor `include`) | keine |
| `picks` | Zeilen `feld=selektor` | keine |
| `max_pages` | die meisten Seiten, die ein Lauf liest | `200` |
| `delay` | Sekunden zwischen zwei Anfragen | `1` |
| `key` | das Schlüsselfeld | `url` |

Vorlage: ein Artikel (`title` aus dem Titel, `intro` aus der Beschreibung und `body` aus dem Pick `body`, beide als
Rich Text), zugeordnet über die Remote-ID `page-{url_hash}`. Beispiele: `sitemap.xml` (eine Sitemap mit vier
Seiten; eine lokale Sitemap-Datei führt ihre Seiten auf, ohne sie zu lesen) und `sitemap-crawl.ndjson` (die
Momentaufnahme eines Durchlaufs: drei Seiten gelesen, eine von robots.txt abgelehnt).

### `webpage`

Eine Seite oder eine Liste von Adressen (Option `urls`). Mit einem Selektor `item` wird jedes Element, das er auf
einer Seite findet (ein Produkt einer Liste, ein Termin eines Kalenders), ein eigener Datensatz, und die Picks gelten
relativ dazu (`@data-sku` allein nimmt ein Attribut des Elements selbst); ohne ihn ist eine Seite ein Datensatz. Ein
Selektor `next` (`a[rel=next]`) nennt den Link zur nächsten Seite, dem gefolgt wird, solange `max_pages` es erlaubt.
Ein Datensatz je Element: `id` (der Schlüssel: die Adresse, bei einem Element mit `#<Position>`), `url`,
`url_hash`, `page` (1 für die erste Seite einer Adresse), `position` (ab 1 über alle Seiten einer Adresse),
`status` und die Picks.

| Option | Werte | Vorgabe |
|---|---|---|
| `urls` | Adressen, eine je Zeile (Pflicht) | |
| `item` | der Selektor des wiederkehrenden Elements | die Seite ist ein Datensatz |
| `next` | der Selektor des Links zur nächsten Seite (sein `href`) | keine nächste Seite |
| `picks` | Zeilen `feld=selektor` | keine |
| `max_pages` | die meisten Seiten, die ein Lauf liest, über alle Adressen | `20` |
| `delay` | Sekunden zwischen zwei Anfragen | `1` |
| `key` | das Schlüsselfeld | `id` |

Keine Vorlage. Beispiel: `page-products.html` (eine Liste mit drei Produkten, gelesen, als wäre sie
`https://example.org/products/`, mit dem Element `.product`).

## Websites, die die Quelle selbst liest (`drupal`, `exponential_site`)

Diese Formate brauchen keine Datei: Die Quelle liest die Schnittstelle der anderen Website selbst. Einmal je Lauf
schreibt sie das Gelesene als Abzug (`source.ndjson`) in das Verzeichnis des Laufs, den dann jeder Abschnitt des Laufs
liest. Der Probelauf des Editors liest nur so viele Datensätze, wie er zeigt. Es gilt:

- Jede Adresse wird wie bei einer URL-Quelle geprüft: nur `http` und `https`, und keine privaten oder reservierten
  Netze, außer bei `[SourceSettings] AllowPrivateHosts=enabled`. Weiterleitungen werden höchstens dreimal verfolgt,
  jede Adresse wird erneut geprüft, und der Header `Authorization` geht nie an einen anderen Host. Ein Link „nächste
  Seite“ einer Antwort wird nur auf demselben Schema, Host und Port verfolgt.
- **Passwörter, Client-Secrets und Tokens werden nie im Import gespeichert.** Ihre Optionen nehmen den *Namen* eines
  Geheimnisses: `Secrets[<Name>]` aus `[SecretSettings]` in den eigenen Einstellungen der Website, oder
  `env:<VARIABLE>` des PHP-Prozesses. Fehlermeldungen zeigen den Namen eines Geheimnisses, nie seinen Wert.
- `[PlatformSettings]` begrenzt einen Lauf: `MaxPages` (Anfragen je Lauf, 200), `MaxItems` (Knoten je Lauf von einer
  anderen Exponential-Website, 5000) und `MaxResponseMegabytes` (eine Antwort, 20). Die Zeitgrenze ist
  `[ImportSettings] StreamTimeout`.

### Drupal (`drupal`)

Gruppe: Plattformen. Inhalte einer Drupal-Website über die JSON:API des Drupal-Kerns (ab Drupal 8.7). Die Quelle
fragt `<Website>/jsonapi/<Entitätstyp>/<Bundle>` ab und folgt `links.next` Seite für Seite.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `base_url` | Pflicht. Die Adresse der Website, z. B. `https://www.example.org` | |
| `resource` | Entitätstyp und Bundle: `node--article`, `node--page`, `taxonomy_term--tags`, `media--image` | `node--article` |
| `page_size` | Ressourcen je Anfrage (JSON:API liefert höchstens 50) | `50` |
| `include` | Die Beziehungen, deren Ressourcen mitkommen, durch Kommas getrennt | `field_image,uid,field_tags` |
| `filter` | JSON:API-Abfrageparameter, wie sie sind, z. B. `filter[status]=1&sort=-created` | leer |
| `auth` | `none` (öffentliche Inhalte), `basic` (`user` und `password_secret`, das Modul basic_auth) oder `bearer` (`token_secret`, ein OAuth-Zugriffstoken) | `none` |
| `key` | das Schlüsselfeld | `id` (die UUID) |

Ein Datensatz hat die Felder `id` (die UUID), `type`, `internal_id` (und `nid`, `vid`, `tid` … unter ihrem Namen),
`title` (der `name` eines Begriffs oder Mediums), `body` (das verarbeitete HTML) mit `body_value`, `body_summary` und
`body_format`, `intro` (die Zusammenfassung, sonst der erste Absatz des Textes), `created`, `changed`, `status`,
`path` (der Alias), `url` (die Adresse des Alias), `langcode` und jedes weitere Attribut unter seinem Namen. Eine
Beziehung wird zu `<Name>` (die verknüpfte Ressource mit den Attributen der mitgelieferten; eine Liste bei einem
mehrfachen Feld), `<Name>_id`, `<Name>_url` bei Dateien und Medien (ein Medium wird bis zu seiner Datei verfolgt) und
`<Name>_names` bei Listen von Begriffen. `image_url` und `image_alt` nennen das erste Bild (`field_image`, dann ein
Medium), `author` den Benutzer von `uid` und `tags` die Namen von `field_tags`.

Die Vorlage bildet eine Ressource auf einen `article` ab: Titel, Einleitung und Text als Rich Text und, wo die Klasse
sie hat, Veröffentlichungsdatum (`created`), Bild (`image_url`, heruntergeladen; eine Objektbeziehung erhält ein
Bildobjekt) und Schlagwörter. Die Remote-ID ist `drupal-<UUID>`, sodass ein späterer Lauf die Objekte aktualisiert.

### Eine andere Exponential-Website (`exponential_site`)

Gruppe: Plattformen. Inhalte einer anderen Exponential-Website über ihre REST-API v1 (die Erweiterung ezprestapi, die
Schnittstelle der mobilen Anwendungen): Die Quelle fragt
`<Website>/api/ezp/v1/content/node/<Knoten>/list/offset/<n>/limit/<m>?ResponseGroups=Metadata,Fields` Seite für Seite
ab, und die Kinder jedes Knotens bis zur gewählten Tiefe.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `base_url` | Pflicht. Die Adresse der anderen Website | |
| `parent_node` | Der Knoten, dessen Kinder gelesen werden | `2` |
| `depth` | `1`: die Kinder; `2`: auch deren Kinder, bis 10 | `1` |
| `class` | Nur die Objekte dieser Klasse (in die anderen wird trotzdem abgestiegen) | jede Klasse |
| `translation` | Die zu lesende Sprache, z. B. `ger-DE` | die der Website |
| `page_size` | Knoten je Anfrage (höchstens 100) | `50` |
| `max_items` | Die meisten Knoten eines Laufs (nie mehr als `[PlatformSettings] MaxItems`) | `1000` |
| `auth` | `none`, `basic` (`user`, `password_secret`), `bearer` (`token_secret`: ein OAuth-Zugriffstoken oder ein persönlicher API-Schlüssel), `oauth_password` (`client_id`, `client_secret`, `user`, `password_secret`) oder `oauth_refresh` (`client_id`, `client_secret`, `refresh_token_secret`) | `none` |
| `token_url` | Der Token-Endpunkt von OAuth2 (auf derselben Website) | `<Website>/api/oauth/token` |
| `key` | das Schlüsselfeld | `remote_id` |

Der Token-Endpunkt einer Exponential-Website gibt Tokens für die Grants `authorization_code` und `refresh_token`:
Verwenden Sie dort ein Refresh-Token (`oauth_refresh`) oder einen persönlichen API-Schlüssel (`bearer`); der
Password-Grant ist für Websites, deren OAuth-Server ihn anbietet. Die REST-API einer Website verlangt normalerweise
eine Anmeldung (`[Authentication] RequireAuthentication` ihrer `rest.ini`).

Ein Datensatz hat die Felder `node_id`, `object_id`, `remote_id`, `node_remote_id`, `class_identifier`, `name`,
`published`, `modified` (Zeitstempel), `url` (die volle Adresse), `url_alias`, `parent_node_id`, `depth` und jedes
Attribut in `fields.<Bezeichner>` mit seinem Datentyp in `field_types.<Bezeichner>`. Die REST-API liefert ein
Attribut in seiner Textform: Rich Text als einfachen Text, ein Bild als seine Adresse auf der Website, eine Beziehung
als Objekt-IDs der Website.

Die Zuordnung entsteht beim Speichern des Imports aus dem ersten Datensatz: seine Klasse, jedes Attribut nach
Bezeichner (Rich Text über `richtext`, Bilder über `image`; Beziehungen bleiben Ihnen überlassen), abgeglichen über
die Remote-ID, sodass die Kopie dem Original bei jedem Lauf folgt.

## Gehostete Dienste, die die Quelle selbst liest (`airtable`, `notion`, `supabase`, `baserow`, `salesforce`, `hubspot`)

Diese Formate lesen die API eines gehosteten Dienstes mit einem Token: Einmal pro Lauf fragt die Quelle die
Datensätze Seite für Seite ab und schreibt sie als Schnappschuss (`source.ndjson`) in das Verzeichnis des Laufs, den
dann jeder Abschnitt des Laufs liest; der Probelauf des Editors liest nur so viele Datensätze, wie er zeigt. Für beide
gilt:

- **Nur lesend.** Die Quellen lesen nur: Airtable, Supabase und Baserow über `GET`-Anfragen, Notion über seine
  Datenbankabfrage und `GET`-Anfragen, Salesforce über seine Anmeldung und `GET`-Abfragen, HubSpot über `GET`-Listen und
  seine Suche (ein `POST`, das liest). Geben Sie dem Token so wenig Rechte wie nötig (siehe jedes Format).
- **Das Token wird nie im Import gespeichert.** Die Option nimmt den *Namen* eines Geheimnisses: `Secrets[<Name>]` von
  `[SecretSettings]` in den eigenen Einstellungen der Website, oder `env:<VARIABLE>` des PHP-Prozesses. Meldungen zeigen
  `[secret <Name>]`, nie das Token, auch wenn der Dienst es in einem Fehler zitiert.
- **Ratenlimits.** Die Quelle hält den kleinsten Abstand zwischen zwei Anfragen ein, den der Dienst erlaubt, und wenn
  der Dienst mit `429 Too Many Requests` antwortet, wartet sie (das `Retry-After` der Antwort, sonst die eigene
  Sperrzeit des Dienstes) und versucht es erneut, bis zu viermal; ein Gateway-Fehler (502, 503, 504) wird ebenso
  wiederholt.
- `[PlatformSettings] MaxPages` begrenzt die Anfragen eines Laufs von Airtable und Notion (200); Supabase, Baserow,
  Salesforce und HubSpot haben `MaxPages` in eigenen Abschnitten (`[SupabaseSourceSettings]` ..., 1000).
  `[PlatformSettings] MaxResponseMegabytes` begrenzt eine Antwort (20) und `[ImportSettings] StreamTimeout` die Zeit
  einer Anfrage.
- **Verbindung testen** im Schritt „Quelle“ liest den ersten Datensatz und zeigt seine Felder oder den Fehler
  (maskiert). Nichts wird gespeichert; es braucht dieselbe Berechtigung wie das Speichern des Imports. *Womit dieser
  Server verbinden kann* nennt, was der Host braucht.
- **Was der Host braucht:** die PHP-Erweiterung `openssl` und `allow_url_fopen=On` (beides Teil der üblichen
  PHP-Pakete), und dass der Server die Adresse des Dienstes über HTTPS erreichen darf.
- Die Daten können **personenbezogene Daten** enthalten (Namen und E-Mail-Adressen von Mitarbeitenden und Personen).
  Ordnen Sie nur zu, was die Website braucht.

### Airtable (`airtable`)

Gruppe: Plattformen. Die Datensätze einer Airtable-Tabelle über die Airtable Web API (v0): Die Quelle fragt
`https://api.airtable.com/v0/<Base>/<Tabelle>` ab und folgt dem `offset` jeder Antwort.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `base_id` | Pflicht. Die ID der Base, `app...` (in der Adresse der Base) | |
| `table` | Pflicht. Der Name der Tabelle oder ihre ID (`tbl...`; sie bleibt richtig, wenn die Tabelle umbenannt wird) | |
| `view` | Der Name oder die ID einer Ansicht: Ihre Filter und ihre Reihenfolge gelten | leer |
| `formula` | `filterByFormula`: eine Airtable-Formel, die ein Datensatz erfüllen muss, z. B. `{Status} = "Published"` | leer |
| `fields` | Die zu lesenden Felder, eines pro Zeile; leer: alle. Ein aufgeführtes Feld steht in jedem Datensatz (`null`, wenn es leer ist) | leer |
| `page_size` | Datensätze pro Anfrage (höchstens 100) | `100` |
| `token` | Pflicht. Der **Name** des Geheimnisses mit einem Personal Access Token | |
| `key` | das Schlüsselfeld | `id` (die Datensatz-ID, `rec...`) |

Legen Sie das Personal Access Token auf airtable.com (Developer hub) nur mit dem Scope `data.records:read` und Zugriff
auf die Bases an, die der Import liest. Airtable erlaubt fünf Anfragen pro Sekunde und Base: Die Quelle hält eine
Fünftelsekunde zwischen zwei Anfragen ein und wartet nach einem `429` 30 Sekunden (die Sperrzeit von Airtable) oder
das `Retry-After`.

Ein Datensatz hat `id`, `created_time` und jedes Feld nach seinem Namen (Airtable lässt leere Felder in einer Antwort
weg; führen Sie sie in `fields` auf, damit sie in jedem Datensatz stehen). Werte: Text, Zahlen, Kontrollkästchen,
Datumsangaben (ISO 8601), Einfach- und Mehrfachauswahl (eine Liste) so, wie Airtable sie liefert; **Anhänge** als Liste
ihrer Adressen (`<Feld>`, die die Zuordnung herunterladen kann, z. B. mit der Umwandlung `image`) und ihrer
Dateinamen (`<Feld>_names`); **verknüpfte Datensätze** als Liste ihrer Datensatz-IDs; ein **Mitarbeiter**
(Collaborator) als Name (`<Feld>`) und E-Mail-Adresse (`<Feld>_email`), mehrere als zwei Listen; eine Schaltfläche als
ihre Adresse, ein Barcode als sein Text, ein KI-Text als sein Wert. Ein Feld, das wie eines der eigenen Felder des
Datensatzes heißt (`id`), wird als `field_<Name>` geschrieben. Die Adressen der Anhänge laufen bei Airtable nach
einigen Stunden ab; die Dateien lädt darum der Lauf herunter, der sie liest.

**Beispiel:** `share/examples/airtable-events.ndjson` ist der Schnappschuss von drei erfundenen Datensätzen einer
Tabelle `Events`; der Katalog zeigt außerdem eine Konfiguration für die veröffentlichten Veranstaltungen einer
Ansicht.

### Notion (`notion`)

Gruppe: Plattformen. Die Seiten einer Notion-Datenbank über die Notion-API (Version `2022-06-28`): Die Quelle fragt
`POST https://api.notion.com/v1/databases/<ID>/query` mit Filter und Sortierung des Imports ab und folgt
`next_cursor`, solange `has_more` gilt.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `database_id` | Pflicht. Die ID der Datenbank (32 Zeichen, mit oder ohne Bindestriche) oder ihr Link (Teilen, Link kopieren) | |
| `filter` | Ein Filterobjekt der Notion-API, z. B. `{"property": "Status", "status": {"equals": "Published"}}` | keiner |
| `sorts` | Eine Liste von Sortierungen, z. B. `[{"property": "Date", "direction": "descending"}]` | keine |
| `content` | `none`, oder der Inhalt der Seite als `markdown` oder `html` im Feld `content` | `none` |
| `page_size` | Seiten pro Anfrage (höchstens 100) | `100` |
| `token` | Pflicht. Der **Name** des Geheimnisses mit dem Token einer internen Integration | |
| `key` | das Schlüsselfeld | `id` (die ID der Seite) |

Legen Sie eine interne Integration an (notion.so, My integrations), nur mit der Fähigkeit *Read content*, und geben
Sie die Datenbank für sie frei (Menü der Datenbank, Connections). Notion erlaubt etwa drei Anfragen pro Sekunde: Die
Quelle hält eine Drittelsekunde zwischen zwei Anfragen ein und folgt dem `Retry-After` eines `429`.

Ein Datensatz hat `id`, `url`, `created_time`, `last_edited_time`, `archived`, `title` (der Text der Titeleigenschaft)
und jede Eigenschaft nach ihrem Namen, als einfacher Wert:

| Eigenschaftstyp | Wert |
|---|---|
| `title`, `rich_text` | der reine Text |
| `number`, `checkbox`, `url`, `email`, `phone_number` | wie er ist |
| `select`, `status` | der Name der Option |
| `multi_select` | die Namen, verbunden mit `, ` |
| `date` | der Beginn in ISO 8601 (`<Name>`) und das Ende (`<Name>_end`, `null` ohne Ende) |
| `people`, `created_by`, `last_edited_by` | die Namen (eine Liste bei `people`) |
| `files` | die Liste der Adressen (`<Name>`) und der Dateinamen (`<Name>_names`); die eigenen Dateiadressen von Notion laufen nach einer Stunde ab |
| `relation` | die Liste der IDs der verknüpften Seiten |
| `formula`, `rollup` | der Wert (ein Rollup einer Liste: die Liste der Werte) |
| `created_time`, `last_edited_time`, `unique_id`, `verification` | als Text (`unique_id` als `PREFIX-42`) |

Mit `content` fragt die Quelle die Blöcke jeder Seite ab (eine Anfrage mehr pro Seite, mit eigenem Cursor in Seiten)
und schreibt die **erste Ebene** als Markdown oder HTML: Absätze, Überschriften, Aufzählungen, nummerierte Listen und
To-do-Listen, Zitate und Callouts, Code, Trennlinien, Bilder und die Links von Lesezeichen, Einbettungen, Videos und
Dateien; Fett, Kursiv, Durchgestrichen, Code und Links im Text bleiben erhalten. Ein Toggle gibt seinen eigenen Text;
verschachtelte Blöcke (die Kinder eines Listenpunkts oder eines Toggles), Tabellen und untergeordnete Datenbanken
werden nicht gelesen.

**Beispiel:** `share/examples/notion-articles.ndjson` ist der Schnappschuss von zwei erfundenen Seiten mit ihrem
Inhalt als Markdown; der Katalog zeigt außerdem eine Konfiguration für die veröffentlichten Artikel, die neuesten
zuerst, mit dem Inhalt als HTML.

### Supabase oder PostgREST (`supabase`)

Gruppe: Plattformen. Die Zeilen einer Tabelle oder Sicht eines Supabase-Projekts über seine REST-API, oder eines
beliebigen PostgREST-Servers: Die Quelle fragt `GET <Basis>/<Tabelle>?select=...&<Filter>&order=...` Seite für Seite
ab. Bei einer Adresse auf `supabase.co` wird der Pfad `/rest/v1` ergänzt; jeder andere Server wird mit dem angegebenen
Pfad verwendet.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `base_url` | Pflicht. Die Adresse des Projekts (`https://<ref>.supabase.co`) oder des PostgREST-Servers | |
| `table` | Pflicht. Die Tabelle oder Sicht, ein Name (nie `rpc`, Funktionen werden nicht gelesen) | |
| `schema` | Ein Schema, das die API neben `public` freigibt (Header `Accept-Profile`) | leer |
| `select` | Spalten und eingebettete Ressourcen: `id,title,author:authors(name,email)`; Aliase, Casts (`::text`), JSON-Pfade (`->>`) und Aggregate erlaubt | `*` |
| `filters` | Einer pro Zeile als PostgREST-Parameter: `status=eq.published`, `tags=cs.{tea}`, `or=(stock.gt.0,preorder.is.true)` | keine |
| `order` | `published_at.desc,id` (auch `.nullsfirst`, `.nullslast`); eine Reihenfolge hält die Seiten stabil | leer |
| `paging` | `range` (Header `Range: 0-999`, `Range-Unit: items`) oder `offset` (Parameter `limit` und `offset`) | `range` |
| `count` | `none`, `exact`, `planned` oder `estimated` (Header `Prefer: count=...`): die Gesamtzahl aus `Content-Range` | `none` |
| `page_size` | Zeilen pro Anfrage (Supabase antwortet mit höchstens seinen *max rows*, standardmäßig 1000) | `1000` |
| `limit` | Höchstens so viele Zeilen; `0`: alle | `0` |
| `api_key` | Der **Name** des Geheimnisses mit dem Anon-Key, gesendet als `apikey` und als Bearer-Token; leer für einen Server ohne Authentifizierung | leer |
| `bearer` | Der **Name** eines Geheimnisses mit einem JWT einer nur lesenden Rolle, statt des Keys als Bearer-Token gesendet | leer |
| `arrays`, `separator` | Arrays als JSON oder als verbundene Liste | `json`, `, ` |
| `key` | die Schlüsselspalte | `id` |

**Zusicherung „nur lesend“:** nur `GET`-Anfragen an die Tabelle; `select`, jeder Filter und `order` werden geprüft,
bevor etwas gesendet wird. Ein Filter darf nur `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `ilike`, `is`, `in`,
`cs`, `cd` verwenden (mit `not.` und den Modifikatoren `(any)` und `(all)`), auch in `and=(...)`, `or=(...)` und `not.`
in jeder Tiefe; Volltextsuche, Bereiche und die Parameter `select`, `order`, `limit`, `offset`, `columns` und
`on_conflict` werden als Filter abgelehnt. **Verwenden Sie den Anon-Key mit einer nur lesenden Rolle und Row Level
Security** (Richtlinien, die nur `SELECT` auf das erlauben, was der Import liest); ein Service-Role-Key umgeht Row Level
Security und sollte nicht verwendet werden. Ein selbst betriebener Server in einem privaten Netz nur aus
`[SupabaseSourceSettings] AllowedHosts[]`.

**Seiten:** Das Lesen endet, wenn eine Seite kürzer als die Seitengröße ist, wenn die Gesamtzahl aus `Content-Range`
erreicht ist oder wenn der Server `416` antwortet (der Bereich beginnt nach der letzten Zeile). **Werte:** so, wie
PostgREST sie liefert: Text, Zahlen, Wahrheitswerte, Zeitstempel in ISO 8601, wie PostgreSQL sie schreibt
(`2026-09-01T08:00:00+00:00`), `json`- und `jsonb`-Spalten und eingebettete Ressourcen als Felder mit Punkt
(`author.name`), Arrays und Einbettungen mit mehreren Zeilen als JSON oder verbundene Liste, `bytea` als Hex-Text
`\x...`. **Verbindung testen** liest eine Zeile und, mit einer Zählung, die Gesamtzahl.

**Beispiel:** `share/examples/supabase-articles.ndjson` (drei erfundene Artikel mit ihrem Autor).

### Baserow (`baserow`)

Gruppe: Plattformen. Die Zeilen einer Baserow-Tabelle über ihre REST-API, auf baserow.io oder selbst betrieben: Die
Quelle fragt `GET <Basis>/api/database/rows/table/<ID>/?user_field_names=true&size=...&page=...` ab, solange die
Antwort ein `next` hat.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `base_url` | `https://api.baserow.io` oder die Adresse eines selbst betriebenen Baserow | `https://api.baserow.io` |
| `table_id` | Pflicht. Die ID der Tabelle | |
| `view_id` | Eine Ansicht, deren Filter und Reihenfolge gelten; `0`: keine | `0` |
| `filters` | Einer pro Zeile als `<Feld>__<Typ>=<Wert>`, gesendet als `filter__<Feld>__<Typ>`: `Status__single_select_equal=3`, `Price__higher_than=10` | keine |
| `filter_type` | `AND` oder `OR` | `AND` |
| `search` | Nur Zeilen, die diesen Text enthalten | leer |
| `order_by` | Felder nach Namen, `-` für absteigend: `-Date,Name` | leer |
| `include` | Die zu lesenden Felder, eines pro Zeile; leer: alle | leer |
| `page_size` | Zeilen pro Anfrage (höchstens 200) | `100` |
| `separator` | Zwischen den Werten von Verknüpfungen, Mehrfachauswahlen und Mitarbeitenden | `, ` |
| `token` | Pflicht. Der **Name** des Geheimnisses mit einem Datenbank-Token | |
| `key` | das Schlüsselfeld | `id` (die Zeilen-ID) |

Legen Sie das Datenbank-Token (Einstellungen, Datenbank-Tokens) nur mit der Berechtigung **Lesen** für den Arbeitsbereich
und die Tabellen an, die der Import braucht; es wird als `Authorization: Token ...` gesendet. Die Quelle sendet nur
`GET`-Anfragen an die Liste der Zeilen. Ein selbst betriebenes Baserow in einem privaten Netz nur aus
`[BaserowSourceSettings] AllowedHosts[]`.

**Werte:** Verknüpfungen (Link rows) als ihre verbundenen Werte (`<Feld>`) und ihre Zeilen-IDs (`<Feld>_ids`, durch
Komma getrennt); Dateien als Liste ihrer Adressen (`<Feld>`, die die Zuordnung herunterladen kann) und ihrer Namen
(`<Feld>_names`); eine Einfachauswahl als ihr Wert, Mehrfachauswahlen und Mitarbeitende als verbundene Namen; ein
Benutzer (erstellt von, zuletzt geändert von) als Name; Datumsangaben in ISO 8601, wie Baserow sie liefert;
Wahrheitswerte als `true` und `false`; Zahlen, wie Baserow sie liefert (Dezimalzahlen als Text, damit keine Stelle
verloren geht); eine leere Liste als `[]`. Verbindung testen liest eine Zeile und die Zahl der Zeilen.

**Beispiel:** `share/examples/baserow-events.ndjson` (drei erfundene Veranstaltungen).

### Salesforce (`salesforce`)

Gruppe: Plattformen. Die Datensätze einer SOQL-Abfrage über die Salesforce-REST-API: Die Quelle meldet sich mit einer
Connected App an, führt `GET <Instanz>/services/data/<Version>/query?q=...` aus (oder `queryAll`, das auch gelöschte
und archivierte Datensätze liefert) und folgt `nextRecordsUrl`.

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `login_url` | `https://login.salesforce.com`, `https://test.salesforce.com` (Sandbox) oder die My-Domain-Adresse | `https://login.salesforce.com` |
| `auth` | `jwt` (JWT Bearer Flow) oder `refresh_token` | `jwt` |
| `client_id` | Pflicht. Der Consumer Key der Connected App | |
| `user` | JWT: der Benutzername des Integrationsbenutzers | |
| `private_key` | JWT: der **Name** des Geheimnisses mit dem PEM des privaten Schlüssels zum Zertifikat der Connected App | |
| `client_secret` | Refresh Token: der **Name** des Geheimnisses mit dem Consumer Secret | |
| `refresh_token` | Refresh Token: der **Name** des Geheimnisses mit dem Refresh Token | |
| `query` | Pflicht. Ein einzelnes SOQL-`SELECT` | |
| `query_all` | Auch gelöschte und archivierte Datensätze (`queryAll`) | nein |
| `api_version` | Die Version der REST-API | `v61.0` |
| `batch_size` | Datensätze pro Antwort (`Sforce-Query-Options: batchSize`, 200 bis 2000) | `2000` |
| `key` | das Schlüsselfeld | `Id` |

**Anmeldung:** Der JWT Bearer Flow signiert ein Token (`iss` der Consumer Key, `sub` der Benutzer, `aud` der
Anmeldeserver, drei Minuten gültig) mit RS256 (`openssl_sign`) und braucht das Zertifikat in der Connected App und den
vorab autorisierten Benutzer; der Refresh-Token-Flow braucht das Consumer Secret und ein Refresh Token des Benutzers.
Die Instanzadresse kommt aus der Token-Antwort; sie und die Anmeldeadresse müssen `https`-Adressen auf einer Domain von
`[SalesforceSourceSettings] AllowedDomains[]` (`salesforce.com`, `force.com`, `cloudforce.com`, `salesforce.mil`) oder
ein Host aus `AllowedHosts[]` sein, damit kein Token woanders hin geht; `nextRecordsUrl` muss ein Query Locator sein.

**Zusicherung „nur lesend“:** Die Abfrage muss eine einzelne Anweisung sein, die mit `SELECT` beginnt; `FOR UPDATE`
(sperrt Datensätze), `FOR VIEW` und `FOR REFERENCE` (schreiben das Datum der letzten Ansicht), `UPDATE TRACKING` und
`UPDATE VIEWSTAT` (schreiben Statistiken) und die Wörter von DML werden außerhalb von Zeichenketten abgelehnt. Geben Sie
dem Integrationsbenutzer ein Profil oder einen Berechtigungssatz mit *API Enabled* und nur Leserechten auf die Objekte,
die der Import braucht.

**Werte:** Beziehungsfelder mit Punkt (`Owner.Name`, `Owner.Manager.Name`), `attributes` auf jeder Ebene weggelassen;
Adress- und Geolocation-Felder aufgelöst (`BillingAddress.city`, `Location__c.latitude`); eine untergeordnete Beziehung
(eine Unterabfrage) als JSON-Liste ihrer Datensätze; Datumsangaben wie geliefert (`2026-10-22`); Datum mit Uhrzeit in
ISO 8601 mit `Z` für UTC (Salesforce schreibt `+0000`) oder `+02:00`; Währungs-, Prozent- und Double-Werte (Zahlen mit
Nachkommastellen) als Zahl in Textform, nie mit Exponent; ganze Zahlen als Zahlen; Wahrheitswerte als `true` und
`false`. Datensätze von Personen (Kontakte, Leads, Benutzer) sind **personenbezogene Daten**. Verbindung testen meldet
sich an, liest einen Datensatz und die `totalSize`.

**Beispiel:** `share/examples/salesforce-accounts.ndjson` (drei erfundene Accounts).

### HubSpot (`hubspot`)

Gruppe: Plattformen. Die Objekte eines HubSpot-CRM-Objekttyps über die CRM-API v3 unter `https://api.hubapi.com`:
`contacts`, `companies`, `deals`, `tickets`, `products`, `line_items` oder ein eigenes Objekt (`2-1234567`,
`p_<Name>`).

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `object_type` | Pflicht. Der Objekttyp | |
| `mode` | `list` (`GET /crm/v3/objects/<Typ>`, jedes Objekt) oder `search` (`POST /crm/v3/objects/<Typ>/search`) | `list` |
| `properties` | Die zu lesenden Eigenschaften, eine pro Zeile (interne Namen); leer: die Standardeigenschaften von HubSpot | leer |
| `associations` | Liste: die verknüpften Objekttypen, deren IDs gelesen werden (`companies`, `contacts`) | leer |
| `filter_groups` | Suche: `[{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]` | leer |
| `search` | Suche: der Text, den HubSpot in den Standard-Texteigenschaften sucht | leer |
| `sort` | Suche: eine Eigenschaft und die Richtung, `createdate DESCENDING` | leer |
| `archived` | Liste: stattdessen die archivierten Objekte lesen | nein |
| `date_properties` | Eigenschaften, deren Werte in Millisekunden zu ISO 8601 werden, neben `createdate`, `closedate` und den anderen Datumsangaben von HubSpot | leer |
| `page_size` | Objekte pro Anfrage (eine Liste höchstens 100, eine Suche 200) | `100` |
| `token` | Pflicht. Der **Name** des Geheimnisses mit dem Zugriffstoken einer privaten App | |
| `key` | das Schlüsselfeld | `id` |

Legen Sie eine private App nur mit den **Lese**-Scopes der Objekte an, die der Import braucht
(`crm.objects.contacts.read`, `crm.objects.companies.read` ...). **Zusicherung „nur lesend“:** Die einzigen Anfragen
sind die Liste (`GET`) und die Suche (ein `POST` an `.../search`, das liest); den Rumpf der Suche baut die Quelle aus
`filter_groups`, das vorher geprüft wird: eine Liste von höchstens 5 Gruppen (oder `{"filterGroups": [...]}`), jede
`{"filters": [...]}`, jeder Filter nur `propertyName`, `operator` (`EQ`, `NEQ`, `LT`, `LTE`, `GT`, `GTE`, `BETWEEN`,
`IN`, `NOT_IN`, `HAS_PROPERTY`, `NOT_HAS_PROPERTY`, `CONTAINS_TOKEN`, `NOT_CONTAINS_TOKEN`), `value`, `highValue` und
`values`, höchstens 18 Filter.

**Ratenlimits und Seiten:** Private Apps dürfen 100 Anfragen in 10 Sekunden stellen (die Quelle hält eine Zehntelsekunde
zwischen zwei Anfragen ein), die Suche etwa 5 pro Sekunde (eine Fünftelsekunde); nach einem `429` wartet sie das
`Retry-After`. Beide Arten folgen `paging.next.after`; eine Suche liest höchstens 10.000 Objekte (die Grenze von
HubSpot; die Quelle meldet sie).

**Werte:** Ein Datensatz hat `id`, `created_at`, `updated_at`, `archived`, jede Eigenschaft nach Namen (jede angefragte,
`null`, wenn leer) und `associations.<Typ>` als Liste der IDs. HubSpot liefert jede Eigenschaft als Text: Zahlen bleiben
Text; Datumsangaben bleiben ISO 8601, und eine Datumseigenschaft in Millisekunden seit 1970 wird ISO 8601 in UTC; die
Werte eines Mehrfach-Kontrollkästchens bleiben mit `;` verbunden. **Kontakte enthalten personenbezogene Daten** (Namen,
E-Mail-Adressen, Telefonnummern): Ordnen Sie nur zu, was die Website braucht. Verbindung testen liest ein Objekt (und
die Gesamtzahl einer Suche).

**Beispiel:** `share/examples/hubspot-companies.ndjson` (drei erfundene Firmen mit den IDs ihrer Kontakte).

## Beliebige JSON-REST-API (`rest_api`)

Gruppe: Daten. Herkunft: `remote`. Die Datensätze einer beliebigen REST-API, die mit JSON antwortet (die Produkt-API
eines Shops, ein Veranstaltungskalender, die Inhalts-API eines CMS, ein interner Dienst): Einmal pro Lauf fragt die
Quelle die Adresse ab, folgt ihren Seiten und schreibt die Datensätze als Momentaufnahme (`source.ndjson`) in das
Verzeichnis des Laufs, das dann jeder Abschnitt des Laufs liest; der Probelauf des Editors liest nur so viele
Datensätze, wie er zeigt. Ein Datensatz ist ein Objekt der Liste einer Antwort; verschachtelte Objekte werden zu
Feldern mit Punkt (`price.amount`, `category.name`) wie beim Format MongoDB.

| Option | Bedeutung | Standard |
|---|---|---|
| `base_url` | Pflicht. Die Adresse der Liste, `https` (`http` nur für einen Host aus `AllowedHosts[]`); ohne Query (siehe `query`), ohne Benutzer oder Passwort | |
| `method` | `GET`, oder `POST` mit einem JSON-Body für einen Endpunkt, der **liest** (eine Suche oder Abfrage) | `GET` |
| `body` | Nur `POST`: das gesendete JSON-Objekt; die Seitenparameter werden als seine obersten Schlüssel gesetzt | leer |
| `query` | Parameter, einer pro Zeile als `name=wert` (`status=active`, `fields[]=id`) | keine |
| `headers` | Zusätzliche Header, einer pro Zeile als `Name: Wert`; nur `Accept`, `Accept-Language` und `X-...` (siehe unten) | keine |
| `record_path` | Der Pfad der Liste der Datensätze in einer Antwort (siehe *Pfade*); leer: die Antwort selbst, wenn sie eine Liste ist, sonst die erste Liste aus `data`, `items`, `results`, `records` oder `values` | leer |
| `auth` | `none`, `bearer`, `basic`, `api_key_header`, `api_key_query` oder `oauth2` | `none` |
| `auth_secret` | Der **Name** des Geheimnisses: das Bearer-Token, das Passwort, der API-Schlüssel oder das Client-Secret von OAuth 2.0 | leer |
| `auth_user` | Basic: der Benutzer; OAuth 2.0: die Client-ID | leer |
| `api_key_name` | Der Header (`X-Api-Key`) oder der Parameter (`api_key`), der den API-Schlüssel trägt | `X-Api-Key` / `api_key` |
| `token_url`, `scope`, `client_auth` | OAuth 2.0: die Token-Adresse, ein optionaler (lesender) Scope und wie sich der Client ausweist (`basic`-Header, wie RFC 6749 empfiehlt, oder `body`) | `basic` |
| `paging` | `none`, `page`, `offset`, `cursor`, `next_url` oder `link` (siehe *Seiten*) | `none` |
| `page_param`, `page_start` | Seitennummer: der Parameter und die erste Seite (`0` oder `1`) | `page`, `1` |
| `size_param` | Seitennummer, Cursor, nächste Adresse und Link: der Parameter der Seitengröße (`per_page`, `limit`); leer: keiner wird gesendet, die Seitengröße des Servers gilt | leer |
| `offset_param`, `limit_param` | Offset und Limit: die beiden Parameter | `offset`, `limit` |
| `cursor_path`, `cursor_param` | Cursor: wo eine Antwort den nächsten Cursor angibt (`meta.next_cursor`), und der Parameter, als der er gesendet wird | `cursor` |
| `next_path` | Nächste Adresse: wo eine Antwort die Adresse der nächsten Seite angibt (`links.next`) | |
| `total_path` | Optional: wo eine Antwort die Gesamtzahl angibt (`meta.total`) | leer |
| `page_size` | Datensätze pro Anfrage | `100` |
| `max_pages` | Nach so vielen Seiten aufhören; `0`: bis `[RestApiSourceSettings] MaxPages` | `0` |
| `limit` | Die meisten Datensätze; `0`: alle | `0` |
| `min_interval` | Die kürzeste Zeit zwischen zwei Anfragen in Sekunden (das Ratenlimit der API) | `0` |
| `arrays`, `separator` | Arrays als JSON oder als verbundene Liste | `json`, `, ` |
| `key` | das Schlüsselfeld | `id` |

**Seiten.** `page` sendet `page=1`, `page=2` ... (mit `per_page`, wenn `size_param` gesetzt ist); `offset` sendet
`offset=0&limit=100`, `offset=100&limit=100` ...; `cursor` sendet den Cursor der vorigen Antwort als Parameter;
`next_url` fragt die Adresse ab, die eine Antwort unter `next_path` angibt (relative Adressen werden aufgelöst); `link`
folgt dem `Link`-Header nach RFC 8288 mit `rel="next"` (GitHub, GitLab, WordPress und viele andere). Mit `POST` stehen
die Seitenparameter statt in der Adresse im JSON-Body. Das Lesen endet bei einer **leeren Seite**, bei einer Seite,
die kürzer als die Seitengröße ist (Seitennummer mit `size_param`, und Offset), wenn die Datensätze die Gesamtzahl
unter `total_path` erreichen, wenn kein nächster Cursor oder keine nächste Adresse kommt (oder dieselbe noch einmal),
und nach dem eigenen `max_pages` des Imports. Mehr Seiten als `[RestApiSourceSettings] MaxPages` (1000) sind ein
Fehler, damit ein Lauf nie stillschweigend mitten in einer Liste endet.

**Nur dieselbe Website.** Einer nächsten Adresse aus einer Antwort und einem `Link`-Header folgt die Quelle nur auf
Schema, Host und Port von `base_url`; jede andere Adresse ist ein Fehler, und es wird nichts an sie gesendet.
Weiterleitungen werden genauso behandelt (höchstens drei, nur dieselbe Website), damit nie ein Token, API-Schlüssel
oder Cookie an einen Host geht, den der Import nicht genannt hat.

**Pfade.** `record_path`, `cursor_path`, `next_path` und `total_path` nehmen eine Teilmenge von JSONPath:
`data.items`, `$.data.items`, `results[0].hits`, `meta['next cursor']`. Platzhalter, Filter und rekursiver Abstieg
(`*`, `?()`, `..`) werden abgelehnt.

**Anmeldung und Geheimnisse.** Jede Zugangsangabe ist der **Name** eines Geheimnisses: `Secrets[<Name>]` aus
`[SecretSettings]` in den Einstellungen der Website (oder der Geheimnisspeicher des Kernels von Exponential 6.0.15),
oder `env:<VARIABLE>` des PHP-Prozesses; sie wird nie im Import gespeichert.

- `bearer`: `Authorization: Bearer <Geheimnis>`.
- `basic`: `Authorization: Basic` aus `auth_user` und dem Geheimnis.
- `api_key_header`: das Geheimnis im Header `api_key_name` (nicht `Cookie`, `Host`, `Content-Type` oder ein
  Weiterleitungs-Header).
- `api_key_query`: das Geheimnis im Parameter `api_key_name`, auch bei nächsten Adressen.
- `oauth2`: Client Credentials (RFC 6749, 4.4): ein `POST` an `token_url` mit `grant_type=client_credentials` und dem
  `scope`, Client-ID und Secret als Basic-Header oder im Body; das Zugriffstoken gilt für den Lauf und wird eine
  Minute vor seinem Ablauf neu geholt. `token_url` muss auf dem Host von `base_url` liegen oder in
  `[RestApiSourceSettings] AllowedTokenHosts[]` stehen.

Meldungen zeigen `[secret <Name>]` oder `[token]`, nie ein Geheimnis, die Basic-Zugangsdaten oder das Zugriffstoken,
auch wenn der Server sie in einem Fehler zitiert; die Adresse in einer Meldung hat keine Query (also keinen Schlüssel
aus einem Parameter). Ein Datensatz, der einen davon zurückgibt, erhält an seiner Stelle `[secret]`, sodass die
Momentaufnahme und das Protokoll des Laufs nie einen `Authorization`-Wert enthalten.

**Header und Parameter.** `headers` nimmt nur `Accept`, `Accept-Language` und `X-...`-Header, und davon nie eine
Methodenüberschreibung (`X-HTTP-Method-Override`, `X-HTTP-Method`, `X-Method-Override`), einen Weiterleitungs- oder
Adress-Header (`X-Forwarded-*`, `X-Real-IP`, `X-Original-URL`, `X-Rewrite-URL`, `X-Host`) oder einen, dessen Name
sagt, dass er eine Zugangsangabe trägt (`X-Api-Key`, `X-Auth-Token`, `...-Secret`, `...-Session`); `Authorization`,
`Cookie` und `Host` werden darüber nie gesetzt. `query` lehnt Parameter ab, die wie Zugangsangaben heißen (`api_key`,
`access_token`, `token`, `key`, `client_secret`, `password`, `sig`). Zugangsangaben gehören in die Optionen der
Anmeldung, wo sie Geheimnisse sind.

**Nur-Lese-Garantie:** Die Quelle sendet `GET`, oder `POST` nur mit dem JSON-Body, den der Import angibt; sie sendet
nie `PUT`, `PATCH` oder `DELETE`, und kein Header kann die Methode überschreiben. Ein `POST`-Endpunkt muss einer sein,
der liest (eine Suche, eine Abfrage, eine GraphQL-Abfrage ohne Mutationen): Die Quelle kann nicht wissen, was ein
Endpunkt mit einem Body tut; wählen Sie ihn also mit Sorgfalt und geben Sie den Zugangsdaten **nur lesende Rechte**
(ein schreibgeschützter API-Schlüssel, ein Token oder OAuth-Client mit einem `read`-Scope).

**Ratenlimits.** `min_interval` hält die kürzeste Zeit zwischen zwei Anfragen ein. Eine Antwort `429 Too Many
Requests` oder `503 Service Unavailable` wird (bis zu viermal) wiederholt, nach der Zeit, die der Server verlangt:
`Retry-After` in Sekunden oder als HTTP-Datum, sonst `X-RateLimit-Reset` oder `RateLimit-Reset` (Sekunden bis dahin,
oder der Zeitpunkt in Sekunden seit 1970, wenn die Zahl so groß ist), sonst das `reset` eines `RateLimit`-Headers;
ohne Hinweis fünf Sekunden. Eine Wartezeit über `[RestApiSourceSettings] MaxWaitSeconds` (120) beendet den Lauf mit
einer klaren Meldung, statt den Worker warten zu lassen. `502` und `504` werden nach 2, 4, 6 ... Sekunden wiederholt.

**Hosts.** `base_url` und `token_url` müssen `https` sein. Ein Host in einem privaten oder reservierten Netz (ein
Intranet-Dienst, `localhost`) wird abgelehnt, außer er steht in `[RestApiSourceSettings] AllowedHosts[]`, und nur ein
dort genannter Host darf auch über `http` gelesen werden. `[PlatformSettings] MaxResponseMegabytes` begrenzt eine
Antwort (20), `[ImportSettings] StreamTimeout` die Zeit einer Anfrage.

**Werte (Typzuordnung):** wie JSON sie liefert: Zeichenketten bleiben Text, Zahlen bleiben Zahlen (wie PHP JSON
dekodiert: `8.90` wird `8.9`; genaue Dezimalzahlen sendet die API am besten als Text), `true`/`false` Wahrheitswerte,
`null` bleibt leer; verschachtelte Objekte werden Felder mit Punkt (`price.amount`); Arrays werden JSON
(`["tea","green"]`) oder mit `arrays=join` eine verbundene Liste (Arrays von Objekten bleiben JSON); ein
Listeneintrag, der kein Objekt ist, wird ein Datensatz mit dem Feld `value`. Datumsangaben bleiben der Text, den die
API liefert (meist ISO 8601); ordnen Sie sie mit den Datums-Transformationen zu.

**Verbindung testen** liest nur die **erste Seite** und zeigt, wie viele Datensätze sie hat, ihre Felder (jedes Feld
jedes Datensatzes der Seite) und mit `total_path` die Gesamtzahl; oder den Fehler, maskiert. Es wird nichts
gespeichert.

**Was der Host braucht:** die PHP-Erweiterung `openssl` mit `allow_url_fopen=On` (beide Teil der üblichen
PHP-Pakete); ohne `allow_url_fopen` nutzt die Quelle die PHP-Erweiterung `curl` (RHEL und Debian: `php-curl`) mit
denselben Prüfungen. *Womit dieser Server sich verbinden kann* zeigt beide (`streams`, `curl`). Der Server muss die
Adresse der API über HTTPS erreichen können.

**Beispiel:** `share/examples/rest-api-products.ndjson` (drei erfundene Produkte einer API mit verschachtelten Preisen
und Kategorien).

## Exporte anderer Systeme (`joomla`, `ezpkg`)

### Joomla-Beiträge (`joomla`)

Gruppe: Plattformen. Dateiendungen: `csv`, `xml`. Beiträge einer Joomla-Website (com_content), exportiert als CSV
mit den Spalten der Beitragstabelle (`id`, `title`, `alias`, `introtext`, `fulltext`, `catid` oder `category_title`,
`state`, `created`, `publish_up`, `language`, `metadesc`, `metakey`, `images` …) oder als XML: J2XML (Beiträge als
`<content>`, Kategorien als `<category>`) oder ein Export mit einem `<article>` je Beitrag und den Feldern als
Kindelementen. Die Variante wird an den ersten Bytes der Datei erkannt.

| Option | Werte | Vorgabe |
|---|---|---|
| `variant` | `auto`, `csv`, `xml` | `auto` |
| `site_url` | Die Adresse der Joomla-Website, damit die Bilder (`images/...`) zu Adressen werden | leer |
| `status` | `published` (Status 1), `not_trashed` (veröffentlicht, unveröffentlicht, archiviert) oder `all` | `not_trashed` |
| `key` | das Schlüsselfeld | `id` |

Ein Datensatz hat die Felder `id`, `title`, `alias`, `intro` (der Einleitungstext), `fulltext`, `body` (Einleitung
und Haupttext zusammengefügt, wie Joomla den Beitrag zeigt), `category` (ihr Titel: die Spalte `category_title`, die
Kategorien des XML, sonst der letzte Teil des Pfads), `category_id`, `category_path`, `state`, `published`,
`featured`, `created`, `modified`, `publish_up`, `date` (`publish_up`, sonst `created`), `language` (leer bei `*`,
alle Sprachen), `metadesc`, `keywords` (die Meta-Schlüsselwörter als Liste), `author`, `hits` und die Bilder aus dem
JSON `images`: `image_intro`, `image_fulltext`, `image_url` (das Bild des Haupttexts, sonst das Einleitungsbild, als
Adresse: ein Pfad der Website braucht `site_url`) und ihre Alternativtexte (`image_alt`). Der Zusatz von Joomla 4 an einem Bildpfad (`#joomlaImage://...`) wird
abgeschnitten.

Die Vorlage bildet einen Beitrag auf einen `article` ab (Titel, Einleitung, Text und, wo die Klasse sie hat,
Veröffentlichungsdatum, Bild und Schlüsselwörter als Schlagwörter), mit der Remote-ID `joomla-<id>`.

### Exponential-Pakete (`ezpkg`)

Gruppe: Plattformen. Dateiendungen: `ezpkg`, `tgz`. Ein Inhaltspaket einer Exponential-Website (Einrichtung, Pakete,
der Export eines Pakets mit Inhaltsobjekten). Jedes Inhaltsobjekt ist ein Datensatz. Das Archiv wird mit PharData
gelesen und nie entpackt: Ein Archiv mit einem Eintrag außerhalb (`..`, ein absoluter Pfad) wird abgelehnt, und das
XML wird ohne Netzwerk gelesen. Nur Pakete im XML-Format können gelesen werden (ein als JSON exportiertes Paket sagt
das).

| Option | Bedeutung | Vorgabe |
|---|---|---|
| `class` | Nur die Objekte dieser Klasse | jedes Objekt |
| `language` | Die zu lesende Übersetzung, z. B. `ger-DE`; Objekte ohne sie werden ausgelassen | die Ausgangssprache jedes Objekts |
| `key` | das Schlüsselfeld | `remote_id` |

Ein Datensatz hat die Felder `remote_id`, `object_id` (im Paket), `name`, `class_identifier`, `class_remote_id`,
`language`, `languages`, `published`, `modified` (Zeitstempel), `section_id`, `node_remote_id`,
`parent_node_remote_id` (des Hauptknotens), `parent_node_remote_ids` und jedes Attribut in `fields.<Bezeichner>` mit
seinem Datentyp in `field_types.<Bezeichner>`. Die Werte haben die Form, die `fromString()` des Datentyps nimmt: Rich
Text als sein XML, Datumsangaben als Zeitstempel, ein Link als `Adresse|Text`, Beziehungen als Listen von Remote-IDs,
Schlagwörter als Listen. Ein Bild oder eine Datei heißt `source:<Pfad im Paket>` (mit dem Alternativtext eines Bildes
in `fields.<Bezeichner>_alt`): Die Umwandlungen `image` und `file` holen sie aus der eigenen Kopie des Pakets im Lauf.

Die Zuordnung entsteht beim Speichern des Imports aus dem Paket: die Klasse seiner Objekte (mit der Option `class`
oder wenn alle Objekte dieselbe Klasse haben), jedes Attribut nach Bezeichner, dessen Datentyp in seiner
`fromString()`-Form gelesen wird (Beziehungen bleiben Ihnen überlassen, sie nennen Objekte des Pakets), und die
Remote-ID als Abgleich, sodass ein erneuter Import des Pakets die Objekte aktualisiert, statt neue anzulegen.

## Der Katalog der Formate

**Importe, Katalog** zeigt die Formate nach Gruppen (Daten, NoSQL-Datenbanken, Inhalte, Plattformen, Handel, Medien, Sonstiges). Jede
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
| `examples()` | eine Liste von `array( 'label', 'file' )` (eine Datei in `share/examples` von sqliimport, mit `'options'`, falls nötig), `array( 'label', 'file', 'extension' => 'ezodf' )` (eine Datei in `share/samples` dieser Erweiterung), `array( 'label', 'path' => 'extension/<Name>/share/...' )` (relativ zur Installation, nur unter `share/` einer Erweiterung) oder `array( 'label', 'url' )` |
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
