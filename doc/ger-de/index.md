# Handbuch zu 7x SQLI Import 2.0

7x SQLI Import holt Inhalte von außen nach Exponential: einen CSV-Export, eine XML-Datei, einen RSS- oder
Atom-Feed, einen JSON- oder NDJSON-Abzug. Es läuft einmal (eine Migration) oder nach Zeitplan (ein nächtlicher
Abgleich). Dies ist das Handbuch zur Version 2.0.0-alpha.1 der Erweiterung `se7enxweb/sqliimport`.

English: [../index.md](../index.md).

## Was in 2.0 neu ist

- **Importe liegen in der Datenbank.** Ein Import (Tabelle `sqliimport_definition`) nennt seine Quelle, seinen
  Handler, sein Ziel, sein Verhalten und seine typisierten Optionen. Die Tabellen von 1.x bleiben.
- **Läufe gehen über die Job-Warteschlange (expqueue).** Ein Lauf ist eine Kette von Jobs: Start, Abschnitte von
  Datensätzen, Abschluss. Jeder Abschnitt speichert Prüfpunkte. Ein Import läuft immer nur einmal gleichzeitig;
  verschiedene Importe laufen parallel.
- **Vier Quelltypen:** CSV/TSV, XML, RSS/Atom und JSON/NDJSON, aus einem Upload, einer `http(s)`-Adresse oder
  einer Datei auf dem Server in einem erlaubten Verzeichnis.
- **Ein Handler ohne Programmierung, „mapping“.** Er ordnet Quellfelder den Attributen einer Inhaltsklasse zu, mit
  Ketten aus 27 eingebauten Umwandlungen, und veröffentlicht nur dann eine neue Version, wenn sich ein Feld
  wirklich geändert hat.
- **Handler von 1.x laufen unverändert** über eine Kompatibilitätsschicht.
- **Jeder Lauf ist nachvollziehbar:** eine Zeile pro Datensatz (angelegt, aktualisiert, unverändert, übersprungen,
  fehlgeschlagen) und ein Protokoll pro Lauf. Ein Probelauf veröffentlicht nichts und zeigt, was sich ändern würde.
  „Retry failed rows“ verarbeitet nur die fehlgeschlagenen Datensätze.
- **Zeitpläne:** einmal, alle N Minuten, stündlich, täglich, wöchentlich, monatlich oder als Cron-Ausdruck, in
  einer Zeitzone.
- **Kommandozeile:** `./console ext:sqliimport:install-schema|list|run|status|cancel|retry|migrate-1x|tick|purge`.
- **Administration:** der Reiter Importe mit einer Übersicht und einer Seite pro Lauf.
- **Fehler von 1.x unter Exponential 6 behoben:** Die Felder von Importaufträgen und geplanten Importen aus 1.x
  sind nicht mehr leer, mit NUL-Bytes serialisierte Optionen werden unter SQLite nicht mehr abgeschnitten, und
  Optionstext ohne `=` erzeugt unter PHP 8 keine Warnung mehr.

Der Import-Editor der Administration (Importe anlegen und bearbeiten, Zuordnungsraster, Vorschau, Zeitpläne)
kommt mit 2.0.0-beta.1. Bis dahin legen Sie Importe mit `migrate-1x`, aus Handlern von 1.x oder in PHP an (siehe
[mappings.md](mappings.md)).

## Seiten

| Seite | Inhalt |
|---|---|
| [install.md](install.md) | Voraussetzungen, Installation, die Cron-Teile, der Reiter Importe |
| [concepts.md](concepts.md) | Importe, Quellen, Handler, Zuordnungen, Läufe, Zeilen, Zeitpläne |
| [sources.md](sources.md) | Quellen für CSV, XML, RSS und JSON und woher ihre Daten kommen |
| [mappings.md](mappings.md) | Die Zuordnung ohne Programmierung, die Umwandlungen, einen Import in PHP anlegen |
| [handlers.md](handlers.md) | Einen Handler für 2.x schreiben, und wie Handler von 1.x weiterlaufen |
| [runs.md](runs.md) | Wie ein Lauf abläuft, seine Status, Zeilen und sein Protokoll, Abbrechen, Wiederholen, Probelauf |
| [schedules.md](schedules.md) | Arten von Zeitplänen, Zeitzonen, Sommerzeit, der Cron-Teil |
| [cli.md](cli.md) | Jeder Befehl `./console ext:sqliimport:*` |
| [permissions.md](permissions.md) | Die Richtlinien von 2.x und 1.x |
| [settings.md](settings.md) | Jede Einstellung von `sqliimport.ini` für 2.x |
| [migration-1x.md](migration-1x.md) | Eine Installation von SQLI Import 1.x aktualisieren |
| [troubleshooting.md](troubleshooting.md) | Was zu prüfen ist, wenn etwas nicht funktioniert |

Die Inhalts-API und die Handler-API von 1.x, die weiterhin gelten, beschreibt
[content-api-and-handlers-1x.md](../content-api-and-handlers-1x.md) (auf Englisch).
