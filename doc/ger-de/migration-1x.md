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

   Fügen Sie `--roles` hinzu, damit die Rollen mit Richtlinien von 1.x die passenden Richtlinien von 2.x bekommen
   ([permissions.md](permissions.md)).

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
| roles | Mit `--roles`: Die Funktionen von 2.x kommen neben die von 1.x |

Die Häufigkeiten von 1.x werden so übertragen: `none` zu `once`; `hourly`, `daily`, `weekly` und `monthly` zur
gleichen Art; `manual` (alle N Minuten) zu `interval` mit mindestens 5 Minuten. Die Zeitzone des Zeitplans ist die
PHP-Zeitzone des Servers. Ein Zeitplan, dessen nächster Termin vorbei ist, bekommt seinen nächsten Termin ab jetzt
berechnet.

Die Migration ist idempotent. Bereits Migriertes wird erkannt und nicht angetastet; Sie können sie also nach dem
Hinzufügen eines Handlers erneut ausführen.

Offene Importaufträge von 1.x werden nicht zum Verlauf. Der Cron-Teil `sqliimport` startet sie als echte Läufe des
Imports `legacy-<handler>`.

## Nach der Aktualisierung

- **Handler von 1.x laufen unverändert** in der Engine von 2.x. Ihre Zeilen haben das Ergebnis `processed`, ihre
  Fortschrittsnotizen gehen in das Protokoll des Laufs ([handlers.md](handlers.md)).
- **Die Seiten von 1.x bleiben** im linken Menü des Reiters Importe unter „sqliimport 1.x“: die Importaufträge
  von 1.x (`sqliimport/items`), die geplanten Importe (`sqliimport/scheduledlist`) und die Formulare für einen
  neuen Auftrag (`sqliimport/addimport`, `sqliimport/addscheduled`). Ihre Aktionen (abbrechen, unterbrechen,
  entfernen, bereinigen) sind jetzt POST-Formulare mit Bestätigung. Ein dort angelegter Auftrag wird vom Cron-Teil
  an die Engine von 2.x übergeben.
- **Die Liste von 1.x ist umgezogen:** `sqliimport/list` ist jetzt die Übersicht von 2.x. Die Liste der
  Importaufträge von 1.x ist `sqliimport/items`. Passen Sie Lesezeichen an.
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
