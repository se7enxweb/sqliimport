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

## Google Sheets (`google_sheets`)

Gruppe: Daten. Eine Tabelle, die bei jedem Lauf über ihren Link gelesen wird: teilen Sie sie so, dass jeder mit dem
Link sie ansehen kann (oder veröffentlichen Sie sie im Web), und geben Sie den Link als Adresse an. Der Link wird zum
CSV-Export der Tabelle, mit dem Tabellenblatt des Links (`#gid=`); die erste Zeile benennt die Spalten. Die Optionen
sind die von CSV, mit Komma und UTF-8.

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
