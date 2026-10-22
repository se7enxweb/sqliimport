# Kommandozeile

Alle Befehle lauten `./console ext:sqliimport:<name>` und werden im Wurzelverzeichnis der Installation
ausgeführt. Jeder ist eine kleine Datei in `extension/sqliimport/bin/php/`, die `expImportCommand` aufruft.
`--help` zeigt die Optionen eines Befehls.

Exit-Codes: `0` Erfolg, `1` Fehler oder ein falsches Argument, `2` nichts zu tun.

Als root gestartet, wechselt ein Befehl zum Benutzer der Website (wenn expqueue das anbietet), bevor er eine
Einstellung liest oder die Datenbank öffnet; nichts, was er schreibt, gehört dann root. Jeder Befehl außer
`install-schema` bricht mit einer Meldung ab, wenn die Tabellen fehlen.

## install-schema

Legt die Tabellen von 7x SQLI Import an oder aktualisiert sie. Der Befehl ist idempotent und aktualisiert auch die
Tabellen von 1.x.

```bash
./console ext:sqliimport:install-schema                 # anlegen, was fehlt (Vorgabe)
./console ext:sqliimport:install-schema --check         # nur melden, was fehlt
./console ext:sqliimport:install-schema --print=mysql   # das SQL ausgeben: mysql, postgresql, sqlite oder oracle
./console ext:sqliimport:install-schema --write-files   # share/db_schema.dba und sql/*/schema.sql neu schreiben
```

## list

Listet die Importe mit ID, Bezeichner, Quelle, Handler und letztem Lauf.

```bash
./console ext:sqliimport:list
./console ext:sqliimport:list --json
```

## run

Startet einen Lauf eines Imports, genannt über seinen Bezeichner oder seine ID. Ohne `--wait` wird der Lauf nur in
die Warteschlange gestellt; die Worker der Job-Warteschlange verarbeiten ihn.

```bash
./console ext:sqliimport:run news_feed
./console ext:sqliimport:run news_feed --dry-run --wait
./console ext:sqliimport:run 4 --max-rows=100 --wait
```

| Option | Wirkung |
|---|---|
| `--dry-run` | Nichts veröffentlichen; für die ersten `[RunSettings] DryRunRows` Datensätze vermerken, was geschehen würde |
| `--wait` | Den Lauf hier verarbeiten, als Worker nur für diesen Import, und das Ergebnis ausgeben |
| `--max-rows=<n>` | Nach so vielen Datensätzen aufhören |
| `--json` | Den Lauf als JSON ausgeben |

Mit `--wait` ist der Exit-Code `0`, wenn der Lauf abgeschlossen wurde (mit oder ohne fehlgeschlagene Datensätze),
sonst `1`.

## status

Ohne Argument: die letzten Läufe. Mit einer Lauf-ID: dieser Lauf und sein Protokoll.

```bash
./console ext:sqliimport:status
./console ext:sqliimport:status 12 --limit=50
./console ext:sqliimport:status 12 --json
```

Eine Zeile eines Laufs lautet: `run #12 completed (import, cli): 100% of 250; created 10, updated 3, unchanged 237,
skipped 0, failed 0; 41s, 38.2 MB`.

## cancel

```bash
./console ext:sqliimport:cancel 12
```

Bricht einen Lauf ab. Exit-Code `2`, wenn er schon beendet war.

## retry

```bash
./console ext:sqliimport:retry 12 --wait
```

Startet einen Lauf mit den fehlgeschlagenen Zeilen eines beendeten Laufs. Abgelehnt wird er, wenn der Lauf keine
fehlgeschlagenen Zeilen hat oder seine Zeilen keinen Datensatz enthalten (ein Handler von 1.x).

## undo

```bash
./console ext:sqliimport:undo 12 --wait
```

Macht einen Lauf rückgängig: was er angelegt hat, kommt in den Papierkorb; was er geändert hat, erhält die Version
vor dem Lauf erneut als neue Version. Ein danach geändertes Objekt bleibt unberührt und wird aufgeführt. Ein Lauf
wird einmal rückgängig gemacht, innerhalb von `[RunSettings] UndoDays` (oder der eigenen Zahl von Tagen des Imports).
Siehe [runs.md](runs.md).

## report

```bash
./console ext:sqliimport:report 12 > lauf-12.csv
./console ext:sqliimport:report 12 --outcome=failed --output=var/import/lauf-12-fehler.csv
```

Schreibt den CSV-Bericht eines Laufs, eine Zeile je Datensatz: `run_id`, `row`, `ref`, `outcome`, `status`,
`object_id`, `remote_id`, `version_before`, `version_after`, `changed_fields`, `message`, `time`. Die Zeilen werden
seitenweise gelesen, ein großer Lauf braucht also wenig Speicher. Eine Zelle, die eine Tabellenkalkulation als Formel
lesen würde, erhält ein führendes Apostroph. Die Seite des Laufs bietet denselben Bericht als **CSV-Bericht
herunterladen** (alle Datensätze oder das angezeigte Ergebnis).

## opml-feeds

```bash
./console ext:sqliimport:opml-feeds meine-feedliste --parent=2345
```

Legt je Feed der Liste eines OPML-Imports einen deaktivierten RSS-Import an, mit der RSS-Vorlage als Zuordnung.
Siehe [sources.md](sources.md#opml-opml).

## migrate-1x

Migriert die Handler, geplanten Importe und den Verlauf von SQLI Import 1.x. Ohne `--apply` meldet der Befehl nur.

```bash
./console ext:sqliimport:migrate-1x                   # Probelauf: was getan würde
./console ext:sqliimport:migrate-1x --apply           # migrieren
./console ext:sqliimport:migrate-1x --apply --roles   # auch Rollen mit manageimports die Richtlinie manage geben
```

Siehe [migration-1x.md](migration-1x.md).

## tick

Eine Runde des Cron-Teils `sqliimport`: beendet Läufe, deren Jobs beendet sind, stellt fällige Zeitpläne und
offene Importe von 1.x ein und bereinigt alte Läufe. Die Zähler gibt der Befehl als JSON aus. Der Tick läuft auch im
Cron-Teil `expqueue` der Job-Warteschlange mit; eine Site, auf der die Warteschlange jede Minute läuft, braucht also
keine eigene Cron-Zeile. Eine Sperre verhindert, dass zwei Ticks gleichzeitig laufen.

```bash
./console ext:sqliimport:tick
```

## purge

Entfernt beendete Läufe, die älter als `[RunSettings] KeepDays` sind, mit ihren Zeilen, Protokollen und Dateien.

```bash
./console ext:sqliimport:purge --dry-run     # nur zählen
./console ext:sqliimport:purge --days=30
```

## Das Skript von 1.x

`extension/sqliimport/bin/php/sqlidoimport.php` aus 1.x bleibt erhalten. Es führt einen Handler von 1.x direkt
aus, außerhalb der Job-Warteschlange. Verwenden Sie besser `run`.
