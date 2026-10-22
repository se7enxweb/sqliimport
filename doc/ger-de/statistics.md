# Statistik

7x SQLI Import bewahrt jeden Lauf mit seinen Zählern, seinen Zeiten und einer Zeile je fehlgeschlagenem
Datensatz auf ([runs.md](runs.md)). Zwei Seiten werten diese Geschichte aus: das Dashboard zeigt die letzten
30 Tage auf einen Blick, die Seite „Statistik“ blickt über jeden Zeitraum zurück und vergleicht ihn mit einem
anderen.

Englisch: [../statistics.md](../statistics.md).

## Das Dashboard (sqliimport/list)

Unter den Kennzahlen zeigt der Block „Die letzten 30 Tage“:

- **Läufe**, den Anteil der **Läufe ohne Fehler** (mit Balken), die **verarbeiteten Datensätze**, die
  **durchschnittliche Dauer** und den **langsamsten Lauf** (verlinkt).
- **Datensätze pro Tag**: ein Balken je Tag, gestapelt nach Ergebnis (angelegt, aktualisiert, unverändert,
  übersprungen, fehlgeschlagen). Beim Überfahren eines Balkens erscheinen seine Zahlen; **Als Tabelle zeigen**
  listet sie auf.
- **Importe mit den meisten Fehlern**: bis zu fünf Importe mit den fehlerhaften Läufen, dem Anteil
  fehlgeschlagener Datensätze und dem häufigsten Fehler. Jeder Name öffnet den Import in der Statistik.

Probeläufe und Rückgängig-Läufe zählen nicht mit.

## Die Seite Statistik (sqliimport/statistics)

Sie öffnet sich über das linke Menü des Reiters „Importe“ (**Statistics**). Wer den Reiter sehen darf, darf auch
sie sehen (dieselben Richtlinien wie bei den anderen Seiten, die nur anzeigen: „Manage imports“, „Run imports“
oder die Richtlinien von 1.x).

### Zeitraum und Filter

Das Formular oben ist ein einfaches GET-Formular; jede Ansicht hat also eine Adresse, die man als Lesezeichen
speichern oder weitergeben kann:

| Feld | Parameter | Werte |
|---|---|---|
| Last | `days` | 7, 30 (Vorgabe), 90, 365 |
| Or from / to | `from`, `to` | Datum (JJJJ-MM-TT); beide zusammen ersetzen `days` |
| Import | `import` | die ID eines Imports |
| Source type | `source` | csv, xml, rss, json, xlsx … |
| User | `user` | die ID des Benutzers, der die Läufe gestartet hat |
| Runs | `scope` | `production` (Importe und Wiederholungen, Vorgabe), `dry_run`, `all` (mit Probeläufen und Rückgängig) |
| Compare with | `compare` | `previous` (der Zeitraum davor, Vorgabe), `custom` (mit `cfrom`, `cto`), `none` |

Dieselben Parameter gehen auch als Ansichtsparameter, zum Beispiel `sqliimport/statistics/(days)/90/(import)/3`.
Die Tage richten sich nach der Zeitzone der Website; ein Lauf zählt an dem Tag, an dem er begann.

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
  eine Legende und **Als Tabelle zeigen** mit seinen Zahlen.
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
