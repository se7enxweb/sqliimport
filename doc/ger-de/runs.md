# Läufe

Ein Lauf ist eine Ausführung eines Imports (`expImportRun`, Tabelle `sqliimport_run`). Er wird von der
Job-Warteschlange (expqueue) verarbeitet, nicht von der Anfrage oder dem Befehl, der ihn startet.

## Wie ein Lauf abläuft

Der Start eines Laufs (`expImportRunner::start()`) speichert den Lauf mit dem Status `queued` und stellt seinen
ersten Job in die Warteschlange. Jeder Lauf hat eine eigene Job-Gruppe und die Ressource `sqliimport:<Import-ID>`.

| Job | Was er tut |
|---|---|
| `sqliimport.run` | Legt die Momentaufnahme der Quelle an, zählt die Datensätze, stellt den ersten Abschnitt ein |
| `sqliimport.chunk` | Verarbeitet bis zu `ChunkSize` Datensätze ab einem Cursor und stellt dann den nächsten Abschnitt oder den Abschluss ein |
| `sqliimport.finish` | Leert die View-Caches der geänderten Objekte, indiziert sie, schreibt die Zusammenfassung und benachrichtigt die Adressen des Zeitplans über Fehler |

Daraus folgen diese Regeln:

- **Ein Lauf pro Import gleichzeitig.** Ein neuer Lauf eines Imports wird abgelehnt, solange ein anderer wartet oder
  läuft.
- **Verschiedene Importe laufen parallel**, jeder in seiner eigenen Ressource. Das ersetzt die eine globale Sperre
  von 1.x.
- **Die Abschnitte eines Laufs laufen nacheinander**, die Datensätze werden also in ihrer Reihenfolge verarbeitet.
- **Prüfpunkte:** Ein Abschnitt speichert seinen Cursor alle 20 Datensätze. Stirbt ein Worker, setzt der nächste
  Versuch nach dem letzten Prüfpunkt fort, und ein Datensatz, der schon eine Zeile hat, wird nicht zweimal
  verarbeitet.
- **Der Speicher bleibt flach:** Die Zwischenspeicher des Kernels werden zwischen den Abschnitten geleert. Die
  Spitze wird am Lauf vermerkt.
- Der Lauf wird als der Benutzer verarbeitet, der ihn gestartet hat, oder als Robot-Benutzer des Imports
  (`owner_id` des Ziels).

Die Job-Typen und ihre Zeitlimits stehen in `expqueue.ini [JobType_sqliimport.*]` (900 Sekunden für den Start,
1800 für einen Abschnitt und den Abschluss, jeweils drei Versuche).

## Status

| Status | Bedeutung |
|---|---|
| `queued` | Wartet auf einen Worker |
| `running` | Ein Worker verarbeitet ihn |
| `completed` | Beendet, kein Datensatz ist fehlgeschlagen |
| `completed_with_errors` | Beendet, einige Datensätze sind fehlgeschlagen |
| `failed` | Der Lauf wurde abgebrochen: Die Quelle war nicht lesbar, die Zuordnung ungültig, oder ein Job schlug bei jedem Versuch fehl |
| `cancelled` | Von einem Benutzer abgebrochen, oder seine Job-Gruppe wurde in der Job-Warteschlange abgebrochen |

Der Cron-Teil `sqliimport` beendet Läufe, deren Jobs ohne sie beendet wurden (ein Job in der Liste der endgültig
gescheiterten Jobs, eine auf der Seite der Job-Warteschlange abgebrochene Job-Gruppe).

## Arten

| Art | Was sie tut |
|---|---|
| `import` | Verarbeitet alle Datensätze (oder `max_rows`) |
| `dry_run` | Veröffentlicht nichts. Liest die ersten `[RunSettings] DryRunRows` Datensätze und vermerkt, was geschehen würde, mit den geänderten Feldern. Auch ein deaktivierter Import kann einen Probelauf haben. |
| `retry` | Verarbeitet nur die fehlgeschlagenen Zeilen eines früheren Laufs, aus den an diesen Zeilen gespeicherten Datensätzen |

## Zeilen und Protokoll

Jeder Datensatz eines Laufs bekommt eine Zeile: seinen Verweis, sein Ergebnis (`created`, `updated`, `unchanged`,
`skipped`, `failed`, `processed`), das Objekt, die Version davor und danach, die hinzugefügten Knoten und eine
Meldung. Eine fehlgeschlagene Zeile behält auch den Datensatz, damit er wiederholt werden kann. Das Protokoll
behält Zeilen ab `[RunSettings] LogLevel` aufwärts (`debug`, `info`, `notice`, `warning`, `error`), jede mit dem
Datensatz, um den es geht.

## In der Administration

**Importe** (`sqliimport/list`) ist die Übersicht. Die Beschriftungen der Schaltflächen sind in 2.0.0-alpha.1
noch nicht übersetzt; sie stehen hier so, wie die Seite sie zeigt.

- Kennzahlen: Importe, Läufe heute, laufende oder wartende Läufe, in den letzten 24 Stunden fehlgeschlagene Läufe,
  der nächste geplante Lauf.
- Eine Leiste **Run now** (jetzt ausführen): einen Import wählen und starten.
- Die Importe mit Quelle, Handler, letztem und nächstem Lauf, mit **Run now** und **Dry run** (Probelauf) für
  Benutzer, die sie ausführen dürfen.
- Die Läufe, die neuesten zuerst, gefiltert nach Status: `sqliimport/list/(status)/active`, `failed`, `completed`
  oder `cancelled`, und nach Import: `sqliimport/list/(import)/<id>`.

**Die Seite eines Laufs** (`sqliimport/run/<id>`) zeigt:

- Den Fortschritt, die Dauer und die Speicherspitze sowie einen Link zu den Jobs des Laufs in der
  Job-Warteschlange.
- Die Ergebnisse als Kennzahlen. Jede filtert die Datensätze: `sqliimport/run/<id>/(outcome)/failed`.
- Die Datensätze mit Objekt, Version und Meldung. In einem Probelauf führt jeder Datensatz seine geänderten Felder
  mit aktuellem und neuem Wert auf.
- Das Protokoll.
- **Cancel** (abbrechen), solange der Lauf nicht beendet ist, und **Retry failed rows** (fehlgeschlagene Zeilen
  wiederholen), wenn er mit fehlgeschlagenen Zeilen endete.

Jede Änderung (Ausführen, Probelauf, Abbrechen, Wiederholen) ist eine POST-Anfrage an `sqliimport/action` mit dem
Formular-Token.

## Abbrechen und wiederholen

- **Cancel** entfernt die wartenden Jobs des Laufs sofort und hält den laufenden Abschnitt zwischen zwei
  Datensätzen an. Bereits verarbeitete Datensätze bleiben, wie sie sind.
- **Retry failed rows** startet einen Lauf der Art `retry`, der nur die fehlgeschlagenen
  Datensätze des beendeten Laufs verarbeitet. Für Handler von 1.x gibt es das nicht; starten Sie deren Import
  stattdessen erneut.

Auf der Kommandozeile: `./console ext:sqliimport:cancel <Lauf-ID>` und `./console ext:sqliimport:retry <Lauf-ID>`
([cli.md](cli.md)).

## Aufbewahrung

Beendete Läufe, die älter als `[RunSettings] KeepDays` (Vorgabe 90) sind, entfernt der Cron-Teil samt Zeilen,
Protokoll und Dateien, ebenso `./console ext:sqliimport:purge`. Wird ein Import entfernt, bleiben seine Läufe, bis
sie bereinigt werden.
