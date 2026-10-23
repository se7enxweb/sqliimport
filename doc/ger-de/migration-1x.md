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

## Durchgespieltes Beispiel: ein Handler von 1.x in der Engine von 2.x

Dieses Beispiel nimmt einen Handler, der so geschrieben ist wie Handler von 1.x: den Fixture-Handler der Tests der
Erweiterung (`tests/fixtures/legacy/sqlifixturecsvhandler.php`, Klasse `SQLIFixtureCSVHandler`). Er liest eine CSV
mit den Spalten `id` und `name` über `SQLICSVDoc` und veröffentlicht je Zeile einen Ordner mit `SQLIContent` und
`SQLIContentPublisher`. Eine Zeile mit dem Namen `FAIL` wirft eine Ausnahme, wie es ein fehlerhafter Datensatz täte.
Das Beispiel zeigt die Einstellungen des Handlers, was der Katalog listet, seine Optionen als `name=value`-Zeilen, die
Zeilen eines Laufs und den Migrationsbericht. Alles unten lief auf dem Server der Dokumentation, mit den
Einstellungen nur für diesen einen Prozess; die Ausgaben sind echt (der Pfad der CSV ist gekürzt, Knoten 54916 ist der
versteckte Ordner, in den das Beispiel veröffentlichte).

**Die Einstellungen**, wie eine Website von 1.x sie in `settings/override/sqliimport.ini.append.php` (oder in der
`sqliimport.ini.append.php` einer Erweiterung) hat:

```ini
[ImportSettings]
AvailableSourceHandlers[]=fixturecsv

[fixturecsv-HandlerSettings]
Enabled=true
Name=Fixture CSV handler (1.x style)
ClassName=SQLIFixtureCSVHandler
DefaultParentNodeID=54916
CSVFile=var/import/legacy-folders.csv
```

Die CSV:

```
id,name
1,Spring news
2,FAIL
3,Autumn news
```

**Was der Katalog listet.** **Importe, Katalog**, Abschnitt *Handlers*, listet jeden Handler mit Klasse, Optionen und
Status. Ein Handler von 1.x ist eine eigene Zeile mit dem Kennzeichen **1.x handler**, und seine Spalte Optionen sagt
*free name=value options*. Auf dem Server der Dokumentation ist kein Handler von 1.x eingerichtet, die Tabelle zeigt
also nur die beiden Handler von 2.x:

![Die Tabelle Handlers des Katalogs: der Zuordnungs-Handler und der Datentabellen-Handler, beide aktiviert](../images/dev-catalogue-handlers-table.png)

Mit den Einstellungen oben ergab die Registry hinter dieser Tabelle:

```
mapping      Mapping to a content class (no code) 2.x    enabled
datatable    Rows of a data table (a persistent class) 2.x    enabled
fixturecsv   Fixture CSV handler (1.x style)    legacy enabled
```

**Der Import und seine Optionen.** `migrate-1x --apply` legt den Import `legacy-fixturecsv` an (oder man legt ihn im
Editor an: *Source type*, *A 1.x handler that reads its own source*, und der Handler). Die Optionen eines Handlers von
1.x sind der `name=value`-Text von 1.x, eine Option pro Zeile, im Schritt Verarbeitung:

```
csv=var/import/legacy-folders.csv
```

Der Handler liest sie wie bisher über seine `SQLIImportHandlerOptions` (`$this->options->attribute( 'csv' )`), und
seinen Einstellungsblock als `handlerConfArray`. Ein Import eines Handlers von 1.x hat keinen Schritt Zuordnung und
keine Vorschau: Ein Handler von 1.x kann nicht laufen, ohne zu veröffentlichen.

**Ein Lauf.** Die Engine umhüllt den Handler mit `expImportLegacyHandlerAdapter`. Sie ruft `initialize()`,
`getProcessLength()`, `getNextRow()` und für jede Zeile `process()` auf, dann `cleanup()`, in einem einzigen
Abschnitt, weil ein Handler von 1.x nicht mittendrin beginnen kann. Die Zeilen des Laufs:

```
handler class: expImportLegacyHandlerAdapter, resumable: false
count(): 3
#1   processed     record {"id":"1","name":"Spring news"}
#2   failed     the fixture line 2 fails on purpose   record {"id":"2","name":"FAIL"}
#3   processed     record {"id":"3","name":"Autumn news"}
fixturecsv-1: object 56640 "Spring news", node 55019
fixturecsv-3: object 56641 "Autumn news", node 55020
```

- Die Zeilen heißen nach ihrer Position (`#1`, `#2` ...), und der gezeigte Datensatz ist eine lesbare Kopie der Zeile
  von 1.x.
- Das Ergebnis ist `processed`: Die Engine weiß nicht, ob `process()` ein Objekt angelegt, aktualisiert oder in Ruhe
  gelassen hat, also behauptet sie es nicht. Der Filter *Processed (1.x)* der Seite des Laufs listet diese Zeilen.
- Die Ausnahme von Zeile 2 lässt nur diese Zeile fehlschlagen, mit ihrer Meldung; der Lauf geht weiter.
- Was der Handler aus `getProgressionNotes()` zurückgibt, kommt in das Protokoll des Laufs (`line 3: Autumn news`).
- **Retry failed rows** gilt nicht, und **Undo** hat nichts rückgängig zu machen: Die Zeilen tragen keinen Datensatz
  und kein Objekt, das die Engine angelegt hätte. Die Daten korrigieren und den Import erneut laufen lassen.

**Der Migrationsbericht.** `./console ext:sqliimport:migrate-1x` ohne `--apply` berichtet nur. Auf dem Server der
Dokumentation, der keinen Handler, keinen Zeitplan und keine Historie von 1.x hat, gab er aus:

```
history   WOULD the 1.x history is migrated
PASS dry run: nothing was changed (--apply to migrate)
```

Mit dem Handler oben eingerichtet ergab derselbe Bericht (`expImportMigration::run()` ohne Anwenden):

```
handlers  WOULD fixturecsv (Fixture CSV handler (1.x style)): becomes the import legacy-fixturecsv
history   WOULD the 1.x history is migrated
```

Jede Zeile eines Probelaufs beginnt mit `WOULD`, auch eine, die nichts zu tun hat: "the 1.x history is migrated"
heißt, dass keine Historie mehr zu migrieren ist. Ein geplanter Import von 1.x fügt eine Zeile hinzu wie `schedules
WOULD scheduled import #4 "Nightly products" (products): becomes an import with the schedule daily`.

**Umzug auf eine Zuordnung.** Dieser Handler kopiert nur zwei CSV-Spalten in einen Ordner und ist damit ein Kandidat
für den nächsten Abschnitt: die CSV-Quelle mit dem Schlüssel `id`, die Klasse `folder`, die Zeile `name` nach `name`
und das Zuordnen über die Remote-ID `fixturecsv-{id}`, das Muster, das der Handler nutzte. Die bestehenden Ordner
werden dann aktualisiert und nicht neu angelegt.

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
