# Aktualisierung von SQLI Import 1.x

Version 2 lässt jeden Handler, jede Einstellung, Tabelle, Richtlinie und Seite von 1.x funktionieren. Die
Aktualisierung fügt die Tabellen von 2.x hinzu und überträgt die Zeitpläne von 1.x an die Engine von 2.x. Nichts
von 1.x wird gelöscht.

## Schritte

1. Sichern Sie die Datenbank.
2. Installieren Sie 2.x über 1.x und aktivieren Sie expqueue und expui ([install.md](install.md)).
3. Erzeugen Sie die Autoloads neu und legen Sie die Tabellen an:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   ./console ext:expqueue:install-schema
   ./console ext:sqliimport:install-schema
   ```

4. Sehen Sie sich an, was die Migration tun würde. Dabei ändert sich nichts:

   ```bash
   ./console ext:sqliimport:migrate-1x
   ```

   Jede Zeile beginnt mit ihrem Bereich (`handlers`, `schedules`, `history`, `roles`) und `WOULD` für eine Änderung,
   die `--apply` vornimmt.

5. Migrieren Sie:

   ```bash
   ./console ext:sqliimport:migrate-1x --apply
   ```

   Fügen Sie `--roles` hinzu, damit jede Rolle mit `sqliimport/manageimports` die Richtlinie `sqliimport/manage`
   („Manage imports“) von 2.x mit derselben Handler-Einschränkung bekommt ([permissions.md](permissions.md)):

   ```bash
   ./console ext:sqliimport:migrate-1x --apply --roles
   ```

6. Ersetzen Sie die Cron-Teile von 1.x durch die neuen, beide jede Minute:

   ```bash
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q expqueue
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q sqliimport
   ```

7. Öffnen Sie **Importe** in der Administration und prüfen Sie die Importe und die nächsten geplanten Läufe.

## Was die Migration tut

| Bereich | Ergebnis |
|---|---|
| handlers | Jeder Handler von 1.x aus `[ImportSettings] AvailableSourceHandlers[]` wird ein Import mit dem Bezeichner `legacy-<handler>` |
| schedules | Jede Zeile von `sqliimport_scheduled` wird ein Import mit ihren Optionen (der Text `schlüssel=wert` als JSON-Optionen) und einem Zeitplan mit derselben Häufigkeit. Die Zeile von 1.x wird abgeschaltet, nicht entfernt. |
| history | Jede Zeile von `sqliimport_item`, die nicht offen ist, wird ein schreibgeschützter Lauf (Status, Zeit, Benutzer; keine Zeilen und kein Protokoll). Ein Auftrag, der noch als laufend markiert war, wird ein fehlgeschlagener Lauf. |
| roles | Mit `--roles`: Eine Rolle mit `manageimports` bekommt daneben `manage`, mit derselben Handler-Einschränkung. Eine Rolle nur mit `listimports` braucht nichts Neues: `listimports` lässt sie weiterhin den Reiter Importe ansehen. Eine Rolle mit `sqliimport/*` bleibt unverändert. |

Die Häufigkeiten von 1.x werden so übertragen: `none` zu `once`; `hourly`, `daily`, `weekly` und `monthly` zur
gleichen Art; `manual` (alle N Minuten) zu `interval` mit mindestens 5 Minuten. Die Zeitzone des Zeitplans ist die
PHP-Zeitzone des Servers. Ein Zeitplan, dessen nächster Termin vorbei ist, bekommt seinen nächsten Termin ab jetzt
berechnet.

Die Migration ist idempotent. Bereits Migriertes wird erkannt und nicht angetastet; Sie können sie also nach dem
Hinzufügen eines Handlers erneut ausführen.

Offene Importaufträge von 1.x werden nicht zum Verlauf. Der Cron-Teil `sqliimport` startet sie als echte Läufe des
Imports `legacy-<handler>`.

## Nach der Aktualisierung

- **Importe werden im Import-Editor bearbeitet** (`sqliimport/edit`, [editor.md](editor.md)). Ein migrierter
  Import eines Handlers von 1.x hat die Schritte Quelle, Verarbeitung, Zeitplan und Prüfung; seine Optionen sind
  der Text `schlüssel=wert` von 1.x.

- **Handler von 1.x laufen unverändert** in der Engine von 2.x. Ihre Zeilen haben das Ergebnis `processed`, ihre
  Fortschrittsnotizen gehen in das Protokoll des Laufs ([handlers.md](handlers.md)).
- **Die Seiten von 1.x sind Teil der Seiten von 2.x.** Keine Seite von 1.x wird mehr gebraucht, und das linke Menü
  hat keine Gruppe „sqliimport 1.x“ mehr:

  | Seite von 1.x | Wo ihre Funktion jetzt ist |
  |---|---|
  | `sqliimport/items` (die Importaufträge) | Die Übersicht `sqliimport/list`, Abschnitt „1.x import requests“: Status, Fortschritt, Fortschrittsnotizen, der Lauf jedes Auftrags, **Cancel** (wartend), **Interrupt** (laufend); geblättert mit `(legacyoffset)` |
  | `sqliimport/addimport` (einen Import anfordern) | Die Übersicht, das Formular **Request import** dieses Abschnitts (ein Handler von 1.x und seine Optionen `name=wert`) |
  | `sqliimport/purgelist` | Die Übersicht, **Purge history** in diesem Abschnitt |
  | `sqliimport/alterimport/<cancel\|interrupt>/<id>` | Die Übersicht, **Cancel** oder **Interrupt** des Auftrags; bricht auch seinen Lauf von 2.x ab |
  | `sqliimport/scheduledlist` | `sqliimport/schedules`, Abschnitt „1.x scheduled imports“: **Run now**, **Edit**, **Enable** oder **Disable**, **Delete** |
  | `sqliimport/addscheduled[/<id>]` | `sqliimport/schedules/(legacy)/<id>` (oder `/new`): Bezeichnung, Handler, Optionen, erster Lauf, Häufigkeit, aktiv; ein migrierter öffnet den Zeitplan seines Imports im Editor |
  | `sqliimport/removescheduled/<id>` | `sqliimport/schedules`, **Delete** |
  | der Fortschritt eines Auftrags | die Seite des Laufs `sqliimport/run/<id>` (Fortschritt, Zähler, Protokoll) |

- **Die alten Adressen funktionieren weiter.** Ein GET einer Seite von 1.x führt zur Seite von 2.x oben, mit ihren
  IDs und ihrem Offset (`sqliimport/addscheduled/12` zu `sqliimport/schedules/(legacy)/12`). Ein POST mit den Feldern
  von 1.x (`RequestImportButton`, `ImportHandler`, `ImportOptions`; `RequestScheduledImportButton` und die Felder
  `Scheduled*`; der POST von `alterimport`, `removescheduled`, `purgelist`) tut weiter, was er tat, geprüft durch das
  Formular-Token und die Handler-Einschränkung `SQLIImport_Type` wie bisher, und führt dann zur Seite von 2.x.
  Skripte und Handler von 1.x, die dorthin senden, brauchen keine Änderung. Diese Adressen zeigen keine eigene Seite.
- **Die Liste von 1.x ist umgezogen:** `sqliimport/list` ist die Übersicht von 2.x. Lesezeichen auf
  `sqliimport/items` werden weitergeleitet.
- **Ein Import blockiert nicht mehr alle anderen.** 1.x hatte eine globale Sperre (`SQLIImportToken`); 2.x führt
  verschiedene Importe parallel aus und jeden Import einmal gleichzeitig.
- **`sqlidoimport.php` und die Cron-Teile von 1.x bleiben erhalten**, sind aber veraltet.

## Behobene Fehler von 1.x

Unter Exponential 6 behebt Version 2 diese Fehler von 1.x:

- Die Felder eines Importauftrags und eines geplanten Imports von 1.x waren leer, weil ihr Getter den persistenten
  Speicher des Kernels umging. Liste und Cronjob von 1.x sahen nichts.
- Mit NUL-Bytes serialisierte Optionen wurden unter SQLite abgeschnitten. Sie werden jetzt auf jeder Datenbank mit
  einer Markierung gespeichert, und nur die Optionsklasse wird deserialisiert.
- Ein geplanter Import von 1.x ersetzte seine Optionen durch leere, wenn sie zweimal gelesen wurden.
- Optionstext mit einer Zeile ohne `=` erzeugte unter PHP 8 eine Warnung, und ein `=` innerhalb eines Werts wurde
  auf der Kommandozeile getrennt.
- Die Werte, die die Templates von 1.x anzeigen (Bezeichnungen, Handler-Namen, Optionen, Fehlermeldungen), werden
  maskiert.

## Einen Handler von 1.x durch eine Zuordnung ersetzen

Viele Handler von 1.x lesen nur eine CSV-, XML- oder RSS-Datei und kopieren Felder in eine Inhaltsklasse. Ein
solcher Import lässt sich durch eine Quelle und eine Zuordnung ersetzen, ohne PHP ([mappings.md](mappings.md)).
Übernehmen Sie das Schema der Remote-IDs des Handlers von 1.x in das Muster der Zuordnung, damit die vorhandenen
Objekte aktualisiert und nicht neu angelegt werden.
