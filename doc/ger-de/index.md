# Handbuch zu 7x SQLI Import 2.0

7x SQLI Import holt Inhalte von außen nach Exponential: eine Tabelle, einen Feed, eine Website, eine API, eine andere
Datenbank, ein anderes CMS. Es läuft einmal (eine Migration), nach Zeitplan (ein nächtlicher Abgleich) oder wenn ein
anderes System es aufruft. Dies ist das Handbuch der Erweiterung `se7enxweb/sqliimport`, Version 2.0, mit einem
durchgerechneten Beispiel für jeden Anwendungsfall: die genauen Schritte, die Einstellungen und Zuordnungszeilen,
eine Stichprobe der Eingabe, das Ergebnis und Screenshots einer echten Installation.

Die Oberfläche ist noch überwiegend auf Englisch; Schaltflächen und Felder stehen in diesem Handbuch so, wie die
Administration sie zeigt.

English: [../index.md](../index.md).

![Der obere Teil der Übersicht von Imports: die Leiste Run now mit New import und die Kennzahlen Importe, Läufe heute, laufend oder wartend, fehlgeschlagen in 24 Stunden und der nächste geplante Lauf](../images/gs-dashboard-top.png)

## Hier beginnen

Neu bei Importen? Lesen Sie **[getting-started.md](getting-started.md)**: Die Seite aktiviert die Erweiterung, erklärt
die beiden Richtlinien und den Katalog und geht einen ersten CSV-Import von der Datei bis zu den veröffentlichten
Produkten durch, mit einem zweiten Lauf, der aktualisiert, ergänzt und eine fehlerhafte Zeile abfängt.

## Inhalt

### Importe nutzen

| Seite | Was sie behandelt |
|---|---|
| [getting-started.md](getting-started.md) | Aktivierung, Berechtigungen, der Reiter Imports, der Katalog, ein erster CSV-Import von Anfang bis Ende |
| [editor.md](editor.md) | Jeder Schritt und jedes Feld des Editors mit sechs Schritten, erhaltene Eingaben bei Fehlern, Geheimnisse und das Feld „New value“ |
| [mappings.md](mappings.md) | Klassen, Elternknoten, Remote-ID-Muster, Abgleich, eine Klasse je Datensatz, Übersetzungen je Spalte, Medien |
| [transforms.md](transforms.md) | Jede eingebaute Umwandlung mit echter Eingabe und Ausgabe, und die Datentypen, die einen besonderen Text brauchen |
| [runs.md](runs.md) | Probelauf, Run now, Live-Fortschritt und Abbrechen, Wiederholen fehlgeschlagener Zeilen, Rückgängigmachen, Konfliktregeln, Benachrichtigungen und die Glocke, der Laufverlauf und der CSV-Bericht |
| [schedules.md](schedules.md) | Zeitpläne mit Cron-Ausdrücken, Zeitzonen, verpasste Läufe, Anhalten und Fortsetzen |
| [triggers.md](triggers.md) | Einen Import aus einem anderen System starten: signierte Aufrufe und API-Schlüssel, mit curl-Beispielen |
| [statistics.md](statistics.md) | Die Diagramme der Übersicht und die Seite Statistics |
| [definitions.md](definitions.md) | Als JSON herunterladen, Auswahl exportieren, Definitionen importieren, Kopieren nach … und Vorlagen von Importen |
| [ai.md](ai.md) | Die KI-Unterstützung über expai: Zuordnungs- und Klassenvorschläge, Erklärungen fehlgeschlagener Zeilen, Zusammenfassungen von Läufen, was gesendet wird |

### Jede Quelle, mit einem durchgerechneten Beispiel

| Seite | Quellen |
|---|---|
| [source-examples-files.md](source-examples-files.md) | CSV, TSV, XLSX/ODS, NDJSON, JSON, XML, YAML, TOML, OPML, Avro/Arrow (Parquet über DuckDB), Medien-ZIP mit EXIF/IPTC, Markdown-Ordner, WordPress WXR, iCalendar, vCard |
| [source-examples-feeds-web.md](source-examples-feeds-web.md) | RSS/Atom, Podcasts, YouTube (Data API und der Seiten-Scraper zum Einschalten), Vimeo, Sitemap-Crawl, Webseiten-Scraper |
| [source-examples-apis.md](source-examples-apis.md) | REST-API (seitenweiser Abruf und Anmeldearten), Google Sheets, Airtable, Notion, Baserow, Supabase, Firebase, Salesforce, HubSpot |
| [source-examples-databases.md](source-examples-databases.md) | SQL (MySQL, PostgreSQL, SQLite, SQL Server, Oracle) und NoSQL (MongoDB, Redis, Elasticsearch, CouchDB/Couchbase, DynamoDB, Firestore, Cosmos DB, Cassandra, Neo4j/ArangoDB, ClickHouse/BigQuery/Snowflake, InfluxDB, DuckDB, LDAP) |
| [source-examples-platforms.md](source-examples-platforms.md) | Eine andere Exponential-Website, Drupal, Joomla, .ezpkg-Pakete, Google Merchant, Shopify, WooCommerce, IMAP-Postfächer, Dokumente (Dateien, ZIP, URLs, Feeds, WebDAV, Drive, OneDrive, Mail-Anhänge) |
| [sources.md](sources.md) | Die Referenz: jede Option jedes Quelltyps |

### Mit dem Rest der Website arbeiten

| Seite | Was sie behandelt |
|---|---|
| [datatables.md](datatables.md) | In eine Datentabelle importieren, eine neue Datentabelle aus den Feldern eines Imports, Inhalte und Tabellen abgleichen |
| [transfer.md](transfer.md) | „Looking for…?“, „Import into this folder…“, Hin- und Rückwege mit Export und CIE, der gemeinsame Laufverlauf |
| [rest-and-fetch.md](rest-and-fetch.md) | Template-Fetch-Funktionen, die ezjscore-Aufrufe, der Endpunkt des Auslösers |
| [handlers.md](handlers.md) | Für Entwickler: eine eigene Quelle, einen eigenen Handler oder eine eigene Umwandlung schreiben, mit getestetem Code |
| [migration-1x.md](migration-1x.md) | Aktualisierung von SQLI Import 1.x; Handler von 1.x in der Engine von 2.x |

### Das Werkzeug betreiben

| Seite | Was sie behandelt |
|---|---|
| [install.md](install.md) | Voraussetzungen, Installation, die Cron-Teile, der Reiter Imports |
| [permissions.md](permissions.md) | Manage imports und Run imports, die Handler-Einschränkung, die Richtlinien von 1.x |
| [settings.md](settings.md) | Jede Einstellung der `sqliimport.ini`, die Seite Settings, Export und Import von Definitionen |
| [cli.md](cli.md) | Jeder Befehl `./console ext:sqliimport:*` |
| [concepts.md](concepts.md) | Importe, Quellen, Handler, Zuordnungen, Läufe, Zeilen, Zeitpläne, die Tabellen |
| [troubleshooting.md](troubleshooting.md) | Probleme und häufige Fragen |

Die Inhalts-API und die Handler-API von 1.x, die weiter gelten, beschreibt
[content-api-and-handlers-1x.md](../content-api-and-handlers-1x.md) (auf Englisch).

## Was in 2.0 neu ist

- **Importe liegen in der Datenbank** (Tabelle `sqliimport_definition`): Quelle, Handler, Ziel, Verhalten und
  typisierte Optionen, angelegt in der Administration. Die Tabellen von 1.x bleiben.
- **Der Import-Editor** legt Importe in sechs Schritten an und ändert sie: Quelle, Verarbeitung, Zuordnung, Vorschau,
  Zeitplan und Prüfung ([editor.md](editor.md)).
- **Mehr als 60 Quelltypen**, in einem Katalog gruppiert: Dateien und Daten, NoSQL-Datenbanken, Inhalte, Plattformen,
  Handel, Medien und Mail, jeder mit einem Beispiel und, wo es sinnvoll ist, einer vorbereiteten Zuordnung.
- **Ein Handler ohne Programmierung, „mapping“**, mit 28 eingebauten Umwandlungen, geschrieben als eine Textzeile
  (`trim | date(format=d.m.Y)`), der nur dann eine neue Version veröffentlicht, wenn sich ein Feld wirklich geändert
  hat.
- **Läufe über die Job-Warteschlange (expqueue):** Abschnitte mit Prüfpunkten, ein Lauf pro Import gleichzeitig,
  verschiedene Importe parallel, Live-Fortschritt, Abbrechen, Probeläufe, Wiederholen fehlgeschlagener Zeilen,
  Rückgängigmachen 30 Tage lang, Konfliktregeln für Objekte, die Menschen bearbeitet haben, Benachrichtigungen per
  E-Mail und über die Glocke in der Kopfzeile.
- **Zeitpläne** (einmal, alle N Minuten, stündlich, täglich, wöchentlich, monatlich, Cron) in einer Zeitzone und ein
  **Auslöser** für andere Systeme (signierte Aufrufe oder API-Schlüssel, mit den Daten im Aufruf).
- **Datentabellen:** Ein Import kann die eigene Tabelle eines Moduls füllen und aus seinen Feldern eine neue Tabelle
  anbieten.
- **Statistik** des Laufverlaufs, mit Diagrammen und einem CSV-Download.
- **Zwei Richtlinien**, „Manage imports“ und „Run imports“. Die Richtlinien von 1.x funktionieren weiter.
- **Handler von 1.x laufen unverändert** über einen Kompatibilitätsadapter ([migration-1x.md](migration-1x.md)).
- **Die KI-Unterstützung** (mit der Erweiterung expai): Vorschläge für Zuordnung und Klasse, Erklärungen
  fehlgeschlagener Zeilen und Zusammenfassungen von Läufen, die ein Mensch übernimmt ([ai.md](ai.md)).
- **Definitionen, Kopien und Vorlagen:** Importe wandern als JSON-Dateien zwischen Installationen, werden mit
  **Copy to …** kopiert und als Vorlagen aufbewahrt ([definitions.md](definitions.md)).

## Sprachen

Die Seiten der Erweiterung gibt es in amerikanischem Englisch und auf Deutsch: `translations/ger-DE/translation.ts`
übersetzt jeden Text der Templates, die Meldungen des Editors, die Bezeichnungen und Hilfetexte der Optionen jedes
Formats, Handlers und jeder Umwandlung (Kontext `extension/sqliimport/options`), die Beschreibungen des Katalogs
(`extension/sqliimport/catalogue`), die Wörter der Status und die Beschreibungen der Zeitpläne. Wer die
Administration mit dem Gebietsschema `ger-DE` benutzt, sieht die deutschen Seiten; der Kernel lädt die Datei über
`site.ini [RegionalSettings] TranslationExtensions[]=sqliimport`. `translations/untranslated/translation.ts` listet
jeden Text für eine neue Sprache; die französische und die spanische Datei enthalten die Texte von 1.x. Das
Protokoll eines Laufs, die Meldungen einer Quelle und die Kommandozeile bleiben englisch.
`tests/unit/TranslationsTest.php` schlägt fehl, wenn ein Text des Codes in ger-DE oder untranslated fehlt oder eine
Übersetzung einen `%Platzhalter` verliert.
