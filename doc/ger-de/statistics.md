# Statistik

7x SQLI Import bewahrt jeden Lauf mit seinen Zählern, seinen Zeiten und einer Zeile je fehlgeschlagenem
Datensatz auf ([runs.md](runs.md)). Zwei Seiten werten diese Geschichte aus: das Dashboard zeigt die letzten
30 Tage auf einen Blick, die Seite „Statistik“ blickt über jeden Zeitraum zurück und vergleicht ihn mit einem
anderen.

Englisch: [../statistics.md](../statistics.md).

Die Bildschirmfotos und Zahlen dieser Seite stammen vom Server der Dokumentation am 22. Oktober 2026, dem Tag, an dem
die Demo- und Dokumentations-Importe zum ersten Mal liefen; jedes Diagramm hat seine Balken daher an diesem einen Tag.
Die php.net-Demo läuft alle zehn Minuten, deshalb unterscheiden sich Zahlen, die einige Minuten auseinander gelesen
wurden, um ein paar Läufe. Die Beschriftungen der Oberfläche stehen so, wie die Administration sie zeigt (in
Englisch).

## Inhalt

- [Das Dashboard (sqliimport/list)](#das-dashboard-sqliimportlist)
- [Die Seite Statistik (sqliimport/statistics)](#die-seite-statistik-sqliimportstatistics)
  - [Zeitraum und Filter](#zeitraum-und-filter)
  - [Was die Seite zeigt](#was-die-seite-zeigt)
- [Beispiel: die Seite Statistik lesen](#beispiel-die-seite-statistik-lesen)
  - [Die Kennzahlen](#die-kennzahlen)
  - [Die Vorschläge](#die-vorschläge)
  - [Die Verlaufsdiagramme](#die-verlaufsdiagramme)
  - [Je Import, Vergleich und häufigste Fehler](#je-import-vergleich-und-häufigste-fehler)
- [Beispiel: eine Adresse je Filter](#beispiel-eine-adresse-je-filter)
- [Beispiel: der CSV-Download](#beispiel-der-csv-download)
- [Wie gerechnet wird](#wie-gerechnet-wird)

## Das Dashboard (sqliimport/list)

Unter den Kennzahlen zeigt der Block „Die letzten 30 Tage“ (*The last 30 days*):

- **Läufe**, den Anteil der **Läufe ohne Fehler** (mit Balken), die **verarbeiteten Datensätze**, die
  **durchschnittliche Dauer** und den **langsamsten Lauf** (verlinkt).
- **Datensätze pro Tag**: ein Balken je Tag, gestapelt nach Ergebnis (angelegt, aktualisiert, unverändert,
  übersprungen, fehlgeschlagen). Beim Überfahren eines Balkens erscheinen seine Zahlen; **Show as a table**
  listet sie auf.
- **Importe mit den meisten Fehlern**: bis zu fünf Importe mit den fehlerhaften Läufen, dem Anteil
  fehlgeschlagener Datensätze und dem häufigsten Fehler. Jeder Name öffnet den Import in der Statistik.

Probeläufe und Rückgängig-Läufe zählen nicht mit.

**Beispiel.** Auf dem Server der Dokumentation zeigte der Block 109 Läufe, 96,3 % ohne Fehler, 1.445 Datensätze,
0,7 s im Durchschnitt und als langsamsten Lauf 4,0 s (Demo: Products (CSV), 50 Datensätze). Der Balken des
22. Oktober ist fast ganz grau: 1.326 der 1.445 Datensätze blieben unverändert, weil die php.net-Demo alle zehn
Minuten läuft und dieselben 15 Einträge findet. Rechts führt „Docs: Tea shelves (retry of failed rows)“ mit 1 von 3
Läufen mit Fehlern und 16,7 % fehlgeschlagenen Datensätzen, häufigster Fehler „the parent of the record does not
exist (yet)“:

![Der Block „Die letzten 30 Tage“ des Dashboards: 109 Läufe, 96,3 % ohne Fehler, 1.445 verarbeitete Datensätze, 0,7 s durchschnittliche Dauer, langsamster Lauf 4,0 s; das Diagramm der Datensätze pro Tag und die Importe mit den meisten Fehlern](../images/stats-dashboard.png)

## Die Seite Statistik (sqliimport/statistics)

Sie öffnet sich über das linke Menü des Reiters „Importe“ (**Statistics**) oder über den Link **Statistics** neben
„The last 30 days“ auf dem Dashboard. Wer den Reiter sehen darf, darf auch sie sehen (dieselben Richtlinien wie bei
den anderen Seiten, die nur anzeigen: „Manage imports“, „Run imports“ oder die Richtlinien von 1.x).

### Zeitraum und Filter

Das Formular oben ist ein einfaches GET-Formular; jede Ansicht hat also eine Adresse, die man als Lesezeichen
speichern oder weitergeben kann:

| Feld | Parameter | Werte |
|---|---|---|
| Last | `days` | 7, 30 (Vorgabe), 90, 365 |
| Or from / to | `from`, `to` | Datum (JJJJ-MM-TT); beide zusammen ersetzen `days` |
| Import | `import` | die ID eines Imports (die Liste zeigt jeden Import mit der Zahl seiner Läufe) |
| Source type | `source` | csv, xml, rss, json, spreadsheet … (jeweils mit der Zahl der Läufe) |
| User | `user` | die ID des Benutzers, der die Läufe gestartet hat |
| Tool | `tool` | leer: die Importe von 7x SQLI Import (Vorgabe); `transfer.package`, `transfer.datatable` …: ein Werkzeug des Menüs „Import und Export“; `all`: alle Werkzeuge ([transfer.md](transfer.md)) |
| Runs | `scope` | `production` (Importe und Wiederholungen, Vorgabe), `dry_run`, `all` (mit Probeläufen und Rückgängig) |
| Compare with | `compare` | `previous` (der Zeitraum davor, Vorgabe), `custom` (mit `cfrom`, `cto`), `none` |

Dieselben Parameter gehen auch als Ansichtsparameter, zum Beispiel `sqliimport/statistics/(days)/90/(import)/3`.
Die Tage richten sich nach der Zeitzone der Website; ein Lauf zählt an dem Tag, an dem er begann.

![Das Filterformular der Seite Statistik: die schnellen Zeiträume 7, 30, 90 und 365 Tage, Last, From und To, Import, Source type, User, Tool, Runs und Compare with, und die Schaltflächen zum Anzeigen und Zurücksetzen](../images/stats-filters.png)

Dasselbe Formular bei 960 Pixeln und doppelter Skalierung:

![Das Filterformular der Seite Statistik bei 960 Pixeln Breite und doppelter Skalierung](../images/stats-filters-960.png)

### Was die Seite zeigt

- **Kennzahlen**: Läufe, Läufe ohne Fehler, verarbeitete Datensätze, Fehlerquote der Datensätze,
  durchschnittliche Dauer, die Dauer, unter der 95 % der Läufe blieben (95. Perzentil), Datensätze pro Sekunde.
- **Vorschläge**, wo die Geschichte auf etwas hinweist, das sich verbessern lässt:
  - *scheitert am Feld X bei N % seiner Datensätze*: die fehlgeschlagenen Datensätze eines Imports nennen oft
    dasselbe Feld (ein Attribut, Feld oder eine Spalte in der Fehlermeldung);
  - *langsam seit DATUM*: die Zeit je Datensatz eines Imports ist dauerhaft gestiegen (mindestens auf das
    1,5-Fache und deutlich über dem Rauschen); das Datum ist der erste langsame Lauf;
  - *N % der Läufe endeten mit Fehlern*, mit dem häufigsten Fehler;
  - *fast jeder Datensatz blieb unverändert*: der Import könnte seltener laufen.
- **Verläufe** pro Tag (Zeiträume bis 62 Tage), pro Woche (bis 200 Tage) oder pro Monat: Läufe nach Ergebnis,
  Datensätze nach Ergebnis, durchschnittliche Dauer eines Laufs und Fehlerquote der Datensätze. Jedes Diagramm hat
  eine Legende und **Show as a table** mit seinen Zahlen.
- **Je Import**: ein Mini-Diagramm der Datensätze pro Tag, Woche oder Monat, Läufe und fehlerhafte Läufe,
  durchschnittliche Dauer und 95. Perzentil, Datensätze pro Sekunde, Fehlerquote (verlinkt auf die fehlerhaften
  Läufe) und die drei häufigsten Fehler. Ein Import, dessen Zeit je Datensatz gestiegen ist, trägt das Abzeichen
  *slow since*.
- **Vergleich**: jede Kennzahl dieses Zeitraums neben der des verglichenen, mit der Änderung; grün ist besser,
  rot schlechter.
- **Häufigste Fehler** aller Importe. Zahlen in den Meldungen erscheinen als `#`, damit derselbe Fehler mit einer
  anderen Zeilennummer nur einmal zählt.

**Table as CSV** lädt die Tabelle je Import herunter (`(format)/csv`), eine Zeile je Import, in UTF-8 mit
Byte-Order-Mark. Zellen, die eine Tabellenkalkulation als Formel lesen würde, bekommen wie im Bericht eines Laufs
ein vorangestelltes Apostroph.

## Beispiel: die Seite Statistik lesen

Die Adresse `sqliimport/statistics` ohne Parameter: die letzten 30 Tage, Importe und Wiederholungen, verglichen mit
den 30 Tagen davor.

### Die Kennzahlen

![Die Kennzahlen der letzten 30 Tage: 109 Läufe, 96,3 % Läufe ohne Fehler, 1.445 verarbeitete Datensätze, 0,3 % fehlgeschlagen (5), 0,7 s durchschnittliche Dauer, 95 % der Läufe dauerten höchstens 2,0 s, 17,8 Datensätze pro Sekunde](../images/stats-key-figures.png)

Zu lesen als: 109 Läufe, davon 4 *completed with errors* (96,3 % ohne Fehler); 1.445 Datensätze, 5 davon
fehlgeschlagen (0,3 %); ein Lauf dauerte im Mittel 0,7 s, und 95 % der Läufe dauerten höchstens 2,0 s; die Importe
verarbeiteten 17,8 Datensätze pro Sekunde, während sie liefen. Die Zeile darüber nennt den Zeitraum
(`09/23/2026 to 10/22/2026 30 days`) und bietet **Table as CSV** an.

### Die Vorschläge

![Die Vorschläge: 98,8 % der Datensätze von Demo: PHP news (Atom feed) blieben über 84 Läufe unverändert, ein seltenerer Zeitplan genügt wohl, mit dem Link Look at this import](../images/stats-suggestions.png)

„98.8 % of the records of Demo: PHP news (Atom feed) were unchanged over 84 runs: a less frequent schedule may do.“
Der Feed ändert sich ein paarmal am Tag; ein stündlicher Zeitplan fände dieselben Nachrichten mit einem Sechstel der
Läufe. Früher am selben Tag zeigte die Seite außerdem *slow since*: „Demo: PHP news (Atom feed) is slow since
2026-10-22: 2.0 times the time per record of before (9.5 s instead of 4.8 s per 100 records). Look at what changed
then: the source, the mapping or the server.“ Der Vorschlag verschwand, als spätere Läufe wieder schnell waren, denn
der Anstieg muss anhalten, um zu zählen. **Look at this import** öffnet dieselbe Seite, gefiltert auf diesen Import.

### Die Verlaufsdiagramme

![Die vier Verlaufsdiagramme pro Tag: Läufe (completed 105, completed with errors 4), Datensätze (angelegt 102, aktualisiert 12, unverändert 1.326, fehlgeschlagen 5), die durchschnittliche Dauer eines Laufs (höchster Balken 0,7 s) und die Fehlerquote der Datensätze (höchster Balken 0,3 %), jedes mit Show as a table](../images/stats-trends.png)

- **Runs**: ein Balken je Tag, nach Ergebnis. Grün ist *completed*, bernsteinfarben *completed with errors*, rot
  *failed*, grau *cancelled or running*.
- **Records**: die Datensätze des Tages nach Ergebnis; der graue Teil (unverändert) ist, was ein Lauf las und
  liegen ließ.
- **Average duration of a run** und **Failure rate of records**: ein Wert je Tag; ein Anstieg in einem von beiden ist,
  wonach die Vorschläge suchen.

Jedes Diagramm nennt seinen höchsten Balken (`highest bar: 1445`) und öffnet mit **Show as a table** die Zahlen je
Tag, etwa „10/22/2026: Completed 102, Completed with errors 4, Failed 0, Cancelled or running 0, Total 106“. Dieselben
Diagramme im dunklen Design:

![Die vier Verlaufsdiagramme im dunklen Design](../images/stats-trends-dark.png)

### Je Import, Vergleich und häufigste Fehler

![Die Tabelle Per import: jeder Import mit seinem Quelltyp, einem Mini-Diagramm der Datensätze pro Tag, Läufen (und Läufen mit Fehlern), durchschnittlicher Dauer und 95. Perzentil, Datensätzen pro Sekunde, Fehlerquote und darunter seinen häufigsten Fehlern](../images/stats-per-import.png)

Die Zeile von „Docs: First import (teas CSV)“: 7 Läufe, 1 mit Fehlern, im Mittel 0,9 s, eine Fehlerquote von 2,8 %
(verlinkt auf seine fehlerhaften Läufe) und darunter der häufigste Fehler mit seiner Zahl: „the required attribute
'name' has no value“. Die Fehlerquote von „Docs: Sitemap crawl (snapshot)“ ist 100 %: Sein einziger produktiver Lauf
war die Wiederholung der einen Zeile, die robots.txt abgelehnt hatte.

**Vergleich** eines Tages mit den drei Wochen davor (die letzte Adresse im nächsten Abschnitt):

![Der Vergleich von 2026-10-22 mit 2026-10-01 bis 2026-10-21: 109 Läufe gegen 0, 1.445 Datensätze gegen 0, 5 fehlgeschlagene Datensätze gegen 0, und die Quoten nur dieses Zeitraums](../images/stats-compare.png)

Quoten lassen sich nicht mit einem Zeitraum ohne Läufe vergleichen; ihre Änderung ist daher ein Strich.

**Häufigste Fehler** aller Importe, zusammengefasst (eine Zeilennummer in einer Meldung wird `#`):

![Die häufigsten Fehler: the parent of the record does not exist (yet) 2-mal, 40 %; the required attribute 'name' has no value 2-mal, 40 %; robots.txt of the site does not allow reading https://example.org/about/ 1-mal, 20 %](../images/stats-errors.png)

## Beispiel: eine Adresse je Filter

Jede Adresse folgt auf `/admin/`; daneben, was die Kennzahlen auf dem Server der Dokumentation zeigten, innerhalb von
zwei Minuten nacheinander gelesen (30 Tage, außer die Adresse setzt den Zeitraum).

| Adresse | Was sich ändert |
|---|---|
| `sqliimport/statistics` | Die Vorgabe: `09/23/2026 to 10/22/2026 30 days`, 109 Läufe, 1.445 Datensätze, 5 fehlgeschlagen. |
| `sqliimport/statistics/(days)/7` | Der Zeitraum ist `10/16/2026 to 10/22/2026 7 days`; da jeder Lauf in der letzten Woche lag, sind die Zahlen die der 30 Tage (inzwischen 112 Läufe). Die Verläufe bleiben pro Tag. |
| `sqliimport/statistics?from=2026-10-01&to=2026-10-22` | Ein eigener Zeitraum, `10/01/2026 to 10/22/2026 22 days`: `from` und `to` zusammen ersetzen `days`, der Vergleich nimmt die 22 Tage davor. |
| `sqliimport/statistics/(import)/2/(days)/30` | Nur die php.net-Demo: 84 Läufe, 100,0 % ohne Fehler, 1.260 Datensätze, 0 fehlgeschlagen, 19,4 Datensätze pro Sekunde; Vorschläge und Tabelle je Import zeigen nur diesen Import. |
| `sqliimport/statistics/(source)/csv` | Nur Importe mit dem Quelltyp CSV: 22 Läufe, 86,4 % ohne Fehler, 173 Datensätze, 4 fehlgeschlagen (2,3 %). |
| `sqliimport/statistics/(user)/56534` | Nur die Läufe, die dieser Benutzer startete (hier das Konto der Dokumentation): 9 Läufe, 88,9 % ohne Fehler, 43 Datensätze, 1 fehlgeschlagen. |
| `sqliimport/statistics/(user)/10` | Benutzer 10 ist *anonymous*: Mit ihm werden die auf der Kommandozeile gestarteten Läufe (`./console ext:sqliimport:run`) gespeichert, hier 2 Läufe. |
| `sqliimport/statistics/(tool)/all` | Die Läufe aller Werkzeuge des Menüs „Import und Export“, nicht nur der Importe: 116 Läufe. |
| `sqliimport/statistics/(scope)/dry_run` | Nur Probeläufe: 44 Läufe, 168 Datensätze, 8 fehlgeschlagen (4,8 %), im Mittel 0,3 s: wie oft Zuordnungen ausprobiert wurden, bevor sie liefen. |
| `sqliimport/statistics/(scope)/all` | Importe, Wiederholungen, Probe- und Rückgängig-Läufe zusammen: 163 Läufe, 1.637 Datensätze, 13 fehlgeschlagen (0,8 %). |
| `sqliimport/statistics/(compare)/none` | Dieselben Zahlen ohne den Abschnitt Vergleich. |
| `sqliimport/statistics/(from)/2026-10-22/(to)/2026-10-22/(compare)/custom/(cfrom)/2026-10-01/(cto)/2026-10-21` | Ein Tag im Vergleich mit den drei Wochen davor: der oben gezeigte Abschnitt Vergleich. |

Nur die Probeläufe und nur die php.net-Demo, wie die Kennzahlen sie zeigten (die Probeläufe einige Minuten früher,
bevor drei weitere dazukamen):

![Die Kennzahlen nur der Probeläufe: 41 Läufe, 90,2 % ohne Fehler, 159 verarbeitete Datensätze, 4,4 % fehlgeschlagen, 0,2 s durchschnittliche Dauer](../images/stats-scope-dry-run.png)

![Die Kennzahlen des Imports 2, der php.net-Demo: 84 Läufe, 100,0 % ohne Fehler, 1.260 verarbeitete Datensätze, 0,0 % fehlgeschlagen, 0,8 s durchschnittliche Dauer, 19,4 Datensätze pro Sekunde](../images/stats-import-2.png)

## Beispiel: der CSV-Download

**Table as CSV** (Adresse `sqliimport/statistics/(format)/csv/(days)/30`) speicherte die Datei
`sqliimport-statistics-2026-09-23-2026-10-22.csv`. Ihre ersten Zeilen (die Datei beginnt mit einem
UTF-8-Byte-Order-Mark, damit eine Tabellenkalkulation Umlaute und Akzente richtig liest):

```csv
import_id,identifier,name,source_type,runs,completed,with_errors,failed_runs,success_rate,records,created,updated,unchanged,skipped,failed_records,failure_rate,avg_duration_s,p95_duration_s,max_duration_s,records_per_second,top_error,top_error_count,failing_field,failing_field_share,slow_since
2,demo-php-news-atom,Demo: PHP news (Atom feed),rss,83,83,0,0,100.0,1245,15,0,1230,0,0,0.0,0.8,2.0,3,19.2,,,,,2026-10-22
132,docs-first-import,Docs: First import (teas CSV),csv,6,5,1,0,83.3,29,8,4,16,0,1,3.4,1.0,1.8,2,4.8,the required attribute 'name' has no value,1,name,3.4,
1,demo-products-csv,Demo: Products (CSV),csv,2,1,1,0,50.0,100,50,5,44,0,1,1.0,2.5,3.8,4,20.0,the required attribute 'name' has no value,1,name,1.0,
```

Eine Zeile je Import, mit den Spalten der Tabelle je Import und drei weiteren: `failing_field` und
`failing_field_share` (das Feld des Vorschlags *scheitert am Feld*: `name` bei 3,4 % der Datensätze von „Docs: First
import“) und `slow_since` (das Datum des Abzeichens *slow since*, hier 2026-10-22 für die php.net-Demo). Der
Download behält die Filter der Seite: `(format)/csv/(source)/csv` lädt nur die CSV-Importe.

## Wie gerechnet wird

Die Klasse `expImportStatistics` (classes/exp/expimportstatistics.php) liest die Läufe des Zeitraums einmal (eine
schmale Zeile je Lauf aus `sqliimport_run`) und die Meldungen ihrer fehlgeschlagenen Datensätze
(`sqliimport_run_row`, die neuesten 20.000; bei Läufen, die als Ganzes scheiterten, die Fehler aus
`sqliimport_run_log`). Die Abfragen sind allgemeines SQL für MySQL, PostgreSQL, SQLite und Oracle; lange
Meldungstexte werden nie in SQL gruppiert. Tage, Wochen und Monate entstehen in PHP in der Zeitzone der Website,
so stimmt auch die Sommerzeit. Ein Zeitraum liest höchstens 50.000 Läufe; die Seite sagt es, wenn sie an diese
Grenze stößt.

Die rechnenden Teile sind reine Funktionen auf einfachen Arrays (Perzentile, Quoten, Änderungen, Zeitabschnitte,
der Wendepunkt, das Zusammenfassen von Meldungen, die Vorschläge) und werden von `tests/unit/StatisticsTest.php`
geprüft.
