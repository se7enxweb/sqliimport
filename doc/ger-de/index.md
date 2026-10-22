# Handbuch zu 7x SQLI Import 2.0

7x SQLI Import holt Inhalte von außen nach Exponential: einen CSV-Export, eine XML-Datei, einen RSS- oder
Atom-Feed, einen JSON- oder NDJSON-Abzug, eine Excel- oder OpenDocument-Tabelle. Es läuft einmal (eine Migration)
oder nach Zeitplan (ein nächtlicher Abgleich). Dies ist das Handbuch der Erweiterung `se7enxweb/sqliimport` im Stand
2.0.0-beta (in Erprobung): die Version 2.0.0-alpha.1 und der seither hinzugekommene Import-Editor, die Seiten der
Administration, die Tabellenquellen und die beiden Richtlinien.

English: [../index.md](../index.md).

## Was in 2.0 neu ist

- **Importe liegen in der Datenbank.** Ein Import (Tabelle `sqliimport_definition`) nennt seine Quelle, seinen
  Handler, sein Ziel, sein Verhalten und seine typisierten Optionen. Die Tabellen von 1.x bleiben.
- **Der Import-Editor** der Administration legt Importe in sechs Schritten an und ändert sie: Quelle,
  Verarbeitung, Zuordnung, Vorschau, Zeitplan und Prüfung. Jeder Schritt wird für sich gespeichert; **Auto-match by
  name** füllt die Zuordnung aus den Feldern der Quelle, und die Vorschau zeigt eine Stichprobe und einen Probelauf
  der ersten Datensätze, bevor etwas veröffentlicht wird ([editor.md](editor.md)).
- **Läufe gehen über die Job-Warteschlange (expqueue).** Ein Lauf ist eine Kette von Jobs: Start, Abschnitte von
  Datensätzen, Abschluss. Jeder Abschnitt speichert Prüfpunkte. Ein Import läuft immer nur einmal gleichzeitig;
  verschiedene Importe laufen parallel.
- **Fünf Quelltypen:** CSV/TSV, XML, RSS/Atom, JSON/NDJSON und Tabellen (XLSX und ODS), aus einem Upload, einer
  `http(s)`-Adresse oder einer Datei auf dem Server in einem erlaubten Verzeichnis.
- **Ein Handler ohne Programmierung, „mapping“.** Er ordnet Quellfelder den Attributen einer Inhaltsklasse zu, mit
  Ketten aus 27 eingebauten Umwandlungen, geschrieben als eine Zeile Text (`trim | date(format=d.m.Y)`), und
  veröffentlicht nur dann eine neue Version, wenn sich ein Feld wirklich geändert hat.
- **Handler von 1.x laufen unverändert** über eine Kompatibilitätsschicht.
- **Jeder Lauf ist nachvollziehbar:** eine Zeile pro Datensatz (angelegt, aktualisiert, unverändert, übersprungen,
  fehlgeschlagen) und ein Protokoll pro Lauf. Die Seite eines Laufs verfolgt einen laufenden Lauf live. Ein
  Probelauf veröffentlicht nichts und zeigt, was sich ändern würde. „Retry failed rows“ verarbeitet nur die
  fehlgeschlagenen Datensätze.
- **Zeitpläne:** einmal, alle N Minuten, stündlich, täglich, wöchentlich, monatlich oder als Cron-Ausdruck, in
  einer Zeitzone. Die Seite Schedules listet sie mit ihren nächsten Läufen und hält sie an oder setzt sie fort.
- **Administration:** der Reiter Importe mit der Übersicht, dem Editor, einer Live-Seite pro Lauf, den Seiten
  Schedules, Catalogue und Settings, dem Export und Import von Importdefinitionen als JSON und dem Block „Imports“
  auf dem Dashboard der Administration.
- **Zwei Richtlinien:** „Manage imports“ (`manage`) und „Run imports“ (`run`), beide mit einer
  Handler-Einschränkung. Die Richtlinien von 1.x funktionieren weiter ([permissions.md](permissions.md)).
- **Kommandozeile:** `./console ext:sqliimport:install-schema|list|run|status|cancel|retry|migrate-1x|tick|purge`.
- **Fehler von 1.x unter Exponential 6 behoben:** Die Felder von Importaufträgen und geplanten Importen aus 1.x
  sind nicht mehr leer, mit NUL-Bytes serialisierte Optionen werden unter SQLite nicht mehr abgeschnitten, und
  Optionstext ohne `=` erzeugt unter PHP 8 keine Warnung mehr.

## Seiten

| Seite | Inhalt |
|---|---|
| [install.md](install.md) | Voraussetzungen, Installation, die Cron-Teile, der Reiter Importe und seine Seiten |
| [concepts.md](concepts.md) | Importe, Quellen, Handler, Zuordnungen, Läufe, Zeilen, Zeitpläne |
| [editor.md](editor.md) | Importe in der Administration anlegen und ändern, Schritt für Schritt |
| [sources.md](sources.md) | Quellen für CSV, XML, RSS, JSON und Tabellen und woher ihre Daten kommen |
| [mappings.md](mappings.md) | Die Zuordnung ohne Programmierung, die Umwandlungen und ihr einzeiliger Text, einen Import in PHP anlegen |
| [handlers.md](handlers.md) | Einen Handler für 2.x schreiben, und wie Handler von 1.x weiterlaufen |
| [runs.md](runs.md) | Wie ein Lauf abläuft, die Live-Seite eines Laufs, Status, Zeilen und Protokoll, Abbrechen, Wiederholen, Probelauf |
| [statistics.md](statistics.md) | Die Diagramme des Dashboards und die Seite Statistik: Verläufe, je Import, Vergleich, Vorschläge, CSV |
| [schedules.md](schedules.md) | Arten von Zeitplänen, Zeitzonen, Sommerzeit, die Seite Schedules, der Cron-Teil |
| [cli.md](cli.md) | Jeder Befehl `./console ext:sqliimport:*` |
| [permissions.md](permissions.md) | Die beiden Richtlinien von 2.x und die Richtlinien von 1.x |
| [settings.md](settings.md) | Jede Einstellung von `sqliimport.ini` für 2.x, die Seite Settings, Export und Import |
| [migration-1x.md](migration-1x.md) | Eine Installation von SQLI Import 1.x aktualisieren |
| [troubleshooting.md](troubleshooting.md) | Was zu prüfen ist, wenn etwas nicht funktioniert |

Die Inhalts-API und die Handler-API von 1.x, die weiterhin gelten, beschreibt
[content-api-and-handlers-1x.md](../content-api-and-handlers-1x.md) (auf Englisch).
