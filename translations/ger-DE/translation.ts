<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0" language="de_DE" sourcelanguage="en_US">
<context>
    <name>extension/sqliimport</name>
    <message>
        <source>Imports</source>
        <translation>Importe</translation>
    </message>
    <message>
        <source>%n runs failed or ended with errors in the last 24 hours.</source>
        <translation>%n Läufe sind in den letzten 24 Stunden fehlgeschlagen oder mit Fehlern beendet worden.</translation>
    </message>
    <message>
        <source>Run</source>
        <translation>Lauf</translation>
    </message>
    <message>
        <source>Import</source>
        <translation>Import</translation>
    </message>
    <message>
        <source>Status</source>
        <translation>Status</translation>
    </message>
    <message>
        <source>Started</source>
        <translation>Gestartet</translation>
    </message>
    <message>
        <source>No import has run yet.</source>
        <translation>Es ist noch kein Import gelaufen.</translation>
    </message>
    <message>
        <source>All imports and runs</source>
        <translation>Alle Importe und Läufe</translation>
    </message>
    <message>
        <source>Catalogue</source>
        <translation>Katalog</translation>
    </message>
    <message>
        <source>Formats are kernel services tagged sqliimport.source (one source class and one block in kernelservices.ini each); handlers and transforms come from sqliimport.ini and from services tagged sqliimport.handler and sqliimport.transform.</source>
        <translation>Formate sind Kernel-Dienste mit dem Tag sqliimport.source (je eine Quellklasse und ein Block in kernelservices.ini); Handler und Umwandlungen kommen aus sqliimport.ini und aus Diensten mit den Tags sqliimport.handler und sqliimport.transform.</translation>
    </message>
    <message>
        <source>What this server can connect with</source>
        <translation>Womit sich dieser Server verbinden kann</translation>
    </message>
    <message>
        <source>Files</source>
        <translation>Dateien</translation>
    </message>
    <message>
        <source>Options</source>
        <translation>Optionen</translation>
    </message>
    <message>
        <source>Comes with a mapping to the class %class</source>
        <translation>Bringt eine Zuordnung zur Klasse %class mit</translation>
    </message>
    <message>
        <source>Example</source>
        <translation>Beispiel</translation>
    </message>
    <message>
        <source>New import</source>
        <translation>Neuer Import</translation>
    </message>
    <message>
        <source>Handlers</source>
        <translation>Handler</translation>
    </message>
    <message>
        <source>Handler</source>
        <translation>Handler</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>Klasse</translation>
    </message>
    <message>
        <source>1.x handler</source>
        <translation>Handler von 1.x</translation>
    </message>
    <message>
        <source>free name=value options</source>
        <translation>freie Optionen name=wert</translation>
    </message>
    <message>
        <source>Transforms</source>
        <translation>Umwandlungen</translation>
    </message>
    <message>
        <source>Name</source>
        <translation>Name</translation>
    </message>
    <message>
        <source>What it does</source>
        <translation>Was sie tut</translation>
    </message>
    <message>
        <source>Arguments</source>
        <translation>Argumente</translation>
    </message>
    <message>
        <source>Origin</source>
        <translation>Herkunft</translation>
    </message>
    <message>
        <source>built in</source>
        <translation>eingebaut</translation>
    </message>
    <message>
        <source>site</source>
        <translation>Site</translation>
    </message>
    <message>
        <source>%n fields</source>
        <translation>%n Felder</translation>
    </message>
    <message>
        <source>An import brings content from a file or an address (CSV, XML, RSS, JSON) into the site, or runs an import handler. Every run is processed by the job queue in chunks, keeps a row per record and its own log, and can be cancelled or retried.</source>
        <translation>Ein Import holt Inhalte aus einer Datei oder von einer Adresse (CSV, XML, RSS, JSON) in die Site oder führt einen Import-Handler aus. Jeder Lauf wird von der Auftragswarteschlange in Abschnitten verarbeitet, führt eine Zeile je Datensatz und ein eigenes Protokoll und lässt sich abbrechen oder wiederholen.</translation>
    </message>
    <message>
        <source>The tables of 7x SQLI Import are missing in this database. Run ./console ext:sqliimport:install-schema on the server.</source>
        <translation>In dieser Datenbank fehlen die Tabellen von 7x SQLI Import. Führen Sie ./console ext:sqliimport:install-schema auf dem Server aus.</translation>
    </message>
    <message>
        <source>The job queue (expqueue) is not installed: runs cannot start. Activate the extension expqueue and run ./console ext:expqueue:install-schema.</source>
        <translation>Die Auftragswarteschlange (expqueue) ist nicht installiert: Läufe können nicht starten. Aktivieren Sie die Erweiterung expqueue und führen Sie ./console ext:expqueue:install-schema aus.</translation>
    </message>
    <message>
        <source>Import to run</source>
        <translation>Auszuführender Import</translation>
    </message>
    <message>
        <source>Run now</source>
        <translation>Jetzt ausführen</translation>
    </message>
    <message>
        <source>1.x import requests</source>
        <translation>Importaufträge (1.x)</translation>
    </message>
    <message>
        <source>Job queue</source>
        <translation>Auftragswarteschlange</translation>
    </message>
    <message>
        <source>Key figures</source>
        <translation>Kennzahlen</translation>
    </message>
    <message>
        <source>Runs today</source>
        <translation>Läufe heute</translation>
    </message>
    <message>
        <source>Running or queued</source>
        <translation>Laufend oder wartend</translation>
    </message>
    <message>
        <source>Failed in 24 hours</source>
        <translation>Fehlgeschlagen in 24 Stunden</translation>
    </message>
    <message>
        <source>Next scheduled run</source>
        <translation>Nächster geplanter Lauf</translation>
    </message>
    <message>
        <source>No import yet.</source>
        <translation>Noch kein Import.</translation>
    </message>
    <message>
        <source>An import is made from a source (a file or an address) and a mapping to a content class, or from an import handler. The editor takes you through it step by step, with a preview before anything is published.</source>
        <translation>Ein Import besteht aus einer Quelle (einer Datei oder einer Adresse) und einer Zuordnung zu einer Inhaltsklasse oder aus einem Import-Handler. Der Editor führt Sie Schritt für Schritt hindurch, mit einer Vorschau, bevor etwas veröffentlicht wird.</translation>
    </message>
    <message>
        <source>Read the manual</source>
        <translation>Handbuch lesen</translation>
    </message>
    <message>
        <source>Source</source>
        <translation>Quelle</translation>
    </message>
    <message>
        <source>Processing</source>
        <translation>Verarbeitung</translation>
    </message>
    <message>
        <source>Last run</source>
        <translation>Letzter Lauf</translation>
    </message>
    <message>
        <source>Next run</source>
        <translation>Nächster Lauf</translation>
    </message>
    <message>
        <source>Actions</source>
        <translation>Aktionen</translation>
    </message>
    <message>
        <source>disabled</source>
        <translation>deaktiviert</translation>
    </message>
    <message>
        <source>Mapping</source>
        <translation>Zuordnung</translation>
    </message>
    <message>
        <source>never</source>
        <translation>nie</translation>
    </message>
    <message>
        <source>Runs</source>
        <translation>Läufe</translation>
    </message>
    <message>
        <source>%from to %to of %count</source>
        <translation>%from bis %to von %count</translation>
    </message>
    <message>
        <source>all</source>
        <translation>alle</translation>
    </message>
    <message>
        <source>one import ×</source>
        <translation>ein Import ×</translation>
    </message>
    <message>
        <source>Tool</source>
        <translation>Werkzeug</translation>
    </message>
    <message>
        <source>Runs of the other import and export tools</source>
        <translation>Läufe der anderen Import- und Exportwerkzeuge</translation>
    </message>
    <message>
        <source>Records</source>
        <translation>Datensätze</translation>
    </message>
    <message>
        <source>Failed</source>
        <translation>Fehlgeschlagen</translation>
    </message>
    <message>
        <source>Duration</source>
        <translation>Dauer</translation>
    </message>
    <message>
        <source>No run matches.</source>
        <translation>Kein Lauf passt.</translation>
    </message>
    <message>
        <source>Progress</source>
        <translation>Fortschritt</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>Angelegt</translation>
    </message>
    <message>
        <source>Updated</source>
        <translation>Aktualisiert</translation>
    </message>
    <message>
        <source>Newer</source>
        <translation>Neuere</translation>
    </message>
    <message>
        <source>Older</source>
        <translation>Ältere</translation>
    </message>
    <message>
        <source>Data tables</source>
        <translation>Datentabellen</translation>
    </message>
    <message>
        <source>Tables of modules and extensions (persistent classes): import into them, make a new one from an import, keep them and content in sync. Setup, Data tables lists them.</source>
        <translation>Tabellen von Modulen und Erweiterungen (persistente Klassen): in sie importieren, aus einem Import eine neue anlegen, sie mit Inhalten synchron halten. Setup, Datentabellen listet sie auf.</translation>
    </message>
    <message>
        <source>Import into a data table</source>
        <translation>In eine Datentabelle importieren</translation>
    </message>
    <message>
        <source>Any format can fill the table of a persistent class instead of making content: choose the target "Rows of a data table" in the step Processing. Rows are matched by a key, checked against the class and written by the kernel, never with raw SQL.</source>
        <translation>Jedes Format kann statt Inhalten die Tabelle einer persistenten Klasse füllen: Wählen Sie im Schritt Verarbeitung das Ziel „Zeilen einer Datentabelle“. Zeilen werden über einen Schlüssel zugeordnet, gegen die Klasse geprüft und vom Kernel geschrieben, nie mit rohem SQL.</translation>
    </message>
    <message>
        <source>Data tables of this site: %n</source>
        <translation>Datentabellen dieser Site: %n</translation>
    </message>
    <message>
        <source>Create a new table from an import</source>
        <translation>Neue Tabelle aus einem Import anlegen</translation>
    </message>
    <message>
        <source>The module table wizard reads the fields of an import (from a sample of its source: names, types, required and unique columns), makes the class, the table and its admin views in an extension, and the import can then fill it. Each import offers it under More, "New data table from its fields".</source>
        <translation>Der Assistent für Modultabellen liest die Felder eines Imports (aus einer Stichprobe seiner Quelle: Namen, Typen, Pflicht- und eindeutige Spalten), legt die Klasse, die Tabelle und ihre Verwaltungsansichten in einer Erweiterung an, und der Import kann sie danach füllen. Jeder Import bietet ihn unter Mehr an, „Neue Datentabelle aus seinen Feldern“.</translation>
    </message>
    <message>
        <source>Open the module table wizard</source>
        <translation>Assistenten für Modultabellen öffnen</translation>
    </message>
    <message>
        <source>Content and data tables in sync</source>
        <translation>Inhalte und Datentabellen synchron</translation>
    </message>
    <message>
        <source>Export content to a table</source>
        <translation>Inhalte in eine Tabelle exportieren</translation>
    </message>
    <message>
        <source>direction content to table, run once</source>
        <translation>Richtung Inhalt zu Tabelle, einmal ausgeführt</translation>
    </message>
    <message>
        <source>Migrate content into a table</source>
        <translation>Inhalte in eine Tabelle migrieren</translation>
    </message>
    <message>
        <source>content to table once; removing the content afterwards is a separate step, never the default</source>
        <translation>einmal Inhalt zu Tabelle; die Inhalte danach zu entfernen ist ein eigener Schritt, nie die Voreinstellung</translation>
    </message>
    <message>
        <source>Keep a table in sync with content</source>
        <translation>Eine Tabelle mit Inhalten synchron halten</translation>
    </message>
    <message>
        <source>content to table, scheduled or live</source>
        <translation>Inhalt zu Tabelle, nach Zeitplan oder live</translation>
    </message>
    <message>
        <source>Import a table into content</source>
        <translation>Eine Tabelle in Inhalte importieren</translation>
    </message>
    <message>
        <source>direction table to content</source>
        <translation>Richtung Tabelle zu Inhalt</translation>
    </message>
    <message>
        <source>Two-way sync</source>
        <translation>Synchronisation in beide Richtungen</translation>
    </message>
    <message>
        <source>both ways, with a rule for conflicts (newest, content, table, or ask)</source>
        <translation>in beide Richtungen, mit einer Regel für Konflikte (neuester Stand, Inhalt, Tabelle oder nachfragen)</translation>
    </message>
    <message>
        <source>Data table</source>
        <translation>Datentabelle</translation>
    </message>
    <message>
        <source>Choose in the form</source>
        <translation>Im Formular wählen</translation>
    </message>
    <message>
        <source>Content class</source>
        <translation>Inhaltsklasse</translation>
    </message>
    <message>
        <source>New synchronisation</source>
        <translation>Neue Synchronisation</translation>
    </message>
    <message>
        <source>Synchronisations</source>
        <translation>Synchronisationen</translation>
    </message>
    <message>
        <source>Conflicts</source>
        <translation>Konflikte</translation>
    </message>
    <message>
        <source>The form of Setup, Data tables opens with these two filled in; choose the direction and the mode there.</source>
        <translation>Das Formular unter Setup, Datentabellen öffnet sich mit diesen beiden Angaben ausgefüllt; wählen Sie dort die Richtung und die Betriebsart.</translation>
    </message>
    <message>
        <source>Dry run</source>
        <translation>Probelauf</translation>
    </message>
    <message>
        <source>Edit</source>
        <translation>Bearbeiten</translation>
    </message>
    <message>
        <source>More</source>
        <translation>Mehr</translation>
    </message>
    <message>
        <source>Duplicate</source>
        <translation>Duplizieren</translation>
    </message>
    <message>
        <source>New data table from its fields</source>
        <translation>Neue Datentabelle aus seinen Feldern</translation>
    </message>
    <message>
        <source>Disable</source>
        <translation>Deaktivieren</translation>
    </message>
    <message>
        <source>Enable</source>
        <translation>Aktivieren</translation>
    </message>
    <message>
        <source>Delete</source>
        <translation>Löschen</translation>
    </message>
    <message>
        <source>Delete this import with its mapping and schedules? Its runs stay in the history.</source>
        <translation>Diesen Import mit seiner Zuordnung und seinen Zeitplänen löschen? Seine Läufe bleiben in der Historie.</translation>
    </message>
    <message>
        <source>Purge history</source>
        <translation>Historie leeren</translation>
    </message>
    <message>
        <source>Remove every ended 1.x import request from the list? Pending and running requests stay.</source>
        <translation>Jeden beendeten Importauftrag von 1.x aus der Liste entfernen? Wartende und laufende Aufträge bleiben.</translation>
    </message>
    <message>
        <source>Imports of the handlers of sqliimport 1.x, requested here, by a 1.x scheduled import or by a script. The cron part "sqliimport" starts each pending request as a run of the handler; its progress and log are on the run page.</source>
        <translation>Importe der Handler von sqliimport 1.x, hier, von einem geplanten Import von 1.x oder von einem Skript beauftragt. Der Cron-Teil „sqliimport“ startet jeden wartenden Auftrag als Lauf des Handlers; Fortschritt und Protokoll stehen auf der Seite des Laufs.</translation>
    </message>
    <message>
        <source>Options (optional), one name=value per line</source>
        <translation>Optionen (optional), eine name=wert je Zeile</translation>
    </message>
    <message>
        <source>Request import</source>
        <translation>Import beauftragen</translation>
    </message>
    <message>
        <source>No 1.x import request.</source>
        <translation>Kein Importauftrag von 1.x.</translation>
    </message>
    <message>
        <source>Choose a 1.x handler above and request an import; or let it run by itself with a 1.x scheduled import.</source>
        <translation>Wählen Sie oben einen Handler von 1.x und beauftragen Sie einen Import; oder lassen Sie ihn mit einem geplanten Import von 1.x von selbst laufen.</translation>
    </message>
    <message>
        <source>Schedules</source>
        <translation>Zeitpläne</translation>
    </message>
    <message>
        <source>Request</source>
        <translation>Auftrag</translation>
    </message>
    <message>
        <source>Requested on</source>
        <translation>Beauftragt am</translation>
    </message>
    <message>
        <source>Run #%id</source>
        <translation>Lauf #%id</translation>
    </message>
    <message>
        <source>Cancel</source>
        <translation>Abbrechen</translation>
    </message>
    <message>
        <source>Are you sure you want to cancel this import?</source>
        <translation>Möchten Sie diesen Import wirklich abbrechen?</translation>
    </message>
    <message>
        <source>Interrupt</source>
        <translation>Unterbrechen</translation>
    </message>
    <message>
        <source>Are you sure you want to interrupt this import?</source>
        <translation>Möchten Sie diesen Import wirklich unterbrechen?</translation>
    </message>
    <message>
        <source>Edit the 1.x scheduled import #%id</source>
        <translation>Geplanten Import #%id von 1.x bearbeiten</translation>
    </message>
    <message>
        <source>New 1.x scheduled import</source>
        <translation>Neuer geplanter Import von 1.x</translation>
    </message>
    <message>
        <source>A 1.x scheduled import requests an import of its handler at the first run and then at every interval of its frequency. To use the schedules of 2.x (cron expressions, time zones, notification), make it an import with ./console ext:sqliimport:migrate-1x.</source>
        <translation>Ein geplanter Import von 1.x beauftragt einen Import seines Handlers beim ersten Lauf und danach in jedem Intervall seiner Häufigkeit. Um die Zeitpläne von 2.x zu nutzen (Cron-Ausdrücke, Zeitzonen, Benachrichtigung), machen Sie ihn mit ./console ext:sqliimport:migrate-1x zu einem Import.</translation>
    </message>
    <message>
        <source>Input did not validate</source>
        <translation>Die Eingabe ist ungültig</translation>
    </message>
    <message>
        <source>Import label</source>
        <translation>Bezeichnung des Imports</translation>
    </message>
    <message>
        <source>Options (optional)</source>
        <translation>Optionen (optional)</translation>
    </message>
    <message>
        <source>One option per line: name=value</source>
        <translation>Eine Option je Zeile: name=wert</translation>
    </message>
    <message>
        <source>First run on</source>
        <translation>Erster Lauf am</translation>
    </message>
    <message>
        <source>Hour</source>
        <translation>Stunde</translation>
    </message>
    <message>
        <source>Minutes</source>
        <translation>Minuten</translation>
    </message>
    <message>
        <source>Every how many minutes</source>
        <translation>Alle wie viele Minuten</translation>
    </message>
    <message>
        <source>For "Every N minutes"; at least 5.</source>
        <translation>Für „Alle N Minuten“; mindestens 5.</translation>
    </message>
    <message>
        <source>The scheduled import is active</source>
        <translation>Der geplante Import ist aktiv</translation>
    </message>
    <message>
        <source>Save</source>
        <translation>Speichern</translation>
    </message>
    <message>
        <source>1.x scheduled imports</source>
        <translation>Geplante Importe (1.x)</translation>
    </message>
    <message>
        <source>They request an import of a handler of sqliimport 1.x; the cron part "sqliimport" runs them until they are migrated with ./console ext:sqliimport:migrate-1x, which makes each an import with a schedule above.</source>
        <translation>Sie beauftragen einen Import eines Handlers von sqliimport 1.x; der Cron-Teil „sqliimport“ führt sie aus, bis sie mit ./console ext:sqliimport:migrate-1x migriert sind, das aus jedem einen Import mit einem Zeitplan oben macht.</translation>
    </message>
    <message>
        <source>No 1.x scheduled import.</source>
        <translation>Kein geplanter Import von 1.x.</translation>
    </message>
    <message>
        <source>migrated</source>
        <translation>migriert</translation>
    </message>
    <message>
        <source>active</source>
        <translation>aktiv</translation>
    </message>
    <message>
        <source>Open the import</source>
        <translation>Import öffnen</translation>
    </message>
    <message>
        <source>Are you sure you want to remove this scheduled import?</source>
        <translation>Möchten Sie diesen geplanten Import wirklich entfernen?</translation>
    </message>
    <message>
        <source>New since you last looked</source>
        <translation>Neu seit Ihrem letzten Besuch</translation>
    </message>
    <message>
        <source>Run #%run</source>
        <translation>Lauf #%run</translation>
    </message>
    <message>
        <source>failed</source>
        <translation>fehlgeschlagen</translation>
    </message>
    <message>
        <source>completed with errors</source>
        <translation>mit Fehlern abgeschlossen</translation>
    </message>
    <message>
        <source>cancelled</source>
        <translation>abgebrochen</translation>
    </message>
    <message>
        <source>completed</source>
        <translation>abgeschlossen</translation>
    </message>
    <message>
        <source>Mark all as read</source>
        <translation>Alle als gelesen markieren</translation>
    </message>
    <message>
        <source>available</source>
        <translation>verfügbar</translation>
    </message>
    <message>
        <source>no driver</source>
        <translation>kein Treiber</translation>
    </message>
    <message>
        <source>in the workers</source>
        <translation>in den Workern</translation>
    </message>
    <message>
        <source>Runs work: the workers of the job queue have a driver (%php).</source>
        <translation>Läufe funktionieren: Die Worker der Auftragswarteschlange haben einen Treiber (%php).</translation>
    </message>
    <message>
        <source>not in the workers</source>
        <translation>nicht in den Workern</translation>
    </message>
    <message>
        <source>The workers of the job queue (%php), which read the runs, have no driver.</source>
        <translation>Die Worker der Auftragswarteschlange (%php), die die Läufe lesen, haben keinen Treiber.</translation>
    </message>
    <message>
        <source>The host needs:</source>
        <translation>Der Server braucht:</translation>
    </message>
    <message>
        <source>retry of</source>
        <translation>Wiederholung von</translation>
    </message>
    <message>
        <source>Cancel this run? Records already processed stay as they are.</source>
        <translation>Diesen Lauf abbrechen? Bereits verarbeitete Datensätze bleiben, wie sie sind.</translation>
    </message>
    <message>
        <source>Retry failed rows</source>
        <translation>Fehlgeschlagene Zeilen wiederholen</translation>
    </message>
    <message>
        <source>Undo this run</source>
        <translation>Diesen Lauf rückgängig machen</translation>
    </message>
    <message>
        <source>Undo this run? What it created goes to the trash; what it updated gets the version before the run again, unless someone changed it since.</source>
        <translation>Diesen Lauf rückgängig machen? Was er angelegt hat, kommt in den Papierkorb; was er aktualisiert hat, erhält wieder die Version vor dem Lauf, sofern es seitdem niemand geändert hat.</translation>
    </message>
    <message>
        <source>Summarize</source>
        <translation>Zusammenfassen</translation>
    </message>
    <message>
        <source>Refresh</source>
        <translation>Aktualisieren</translation>
    </message>
    <message>
        <source>Jobs of this run</source>
        <translation>Aufträge dieses Laufs</translation>
    </message>
    <message>
        <source>This run was undone by run</source>
        <translation>Dieser Lauf wurde rückgängig gemacht durch Lauf</translation>
    </message>
    <message>
        <source>Undo: %reason</source>
        <translation>Rückgängig: %reason</translation>
    </message>
    <message>
        <source>%done of %total records, %percent%</source>
        <translation>%done von %total Datensätzen, %percent %</translation>
    </message>
    <message>
        <source>duration %d</source>
        <translation>Dauer %d</translation>
    </message>
    <message>
        <source>memory peak %m MB</source>
        <translation>Speicherspitze %m MB</translation>
    </message>
    <message>
        <source>Summary by the AI</source>
        <translation>Zusammenfassung der KI</translation>
    </message>
    <message>
        <source>Made from the figures of the run, without record values; check it against them.</source>
        <translation>Aus den Zahlen des Laufs erstellt, ohne Werte der Datensätze; prüfen Sie sie daran.</translation>
    </message>
    <message>
        <source>Outcomes</source>
        <translation>Ergebnisse</translation>
    </message>
    <message>
        <source>Unchanged</source>
        <translation>Unverändert</translation>
    </message>
    <message>
        <source>Skipped</source>
        <translation>Übersprungen</translation>
    </message>
    <message>
        <source>Undone</source>
        <translation>Rückgängig gemacht</translation>
    </message>
    <message>
        <source>Processed (1.x)</source>
        <translation>Verarbeitet (1.x)</translation>
    </message>
    <message>
        <source>Download results (CSV)</source>
        <translation>Ergebnisse herunterladen (CSV)</translation>
    </message>
    <message>
        <source>Outcome</source>
        <translation>Ergebnis</translation>
    </message>
    <message>
        <source>No record matches.</source>
        <translation>Kein Datensatz passt.</translation>
    </message>
    <message>
        <source>Record</source>
        <translation>Datensatz</translation>
    </message>
    <message>
        <source>Object</source>
        <translation>Objekt</translation>
    </message>
    <message>
        <source>Version</source>
        <translation>Version</translation>
    </message>
    <message>
        <source>Message</source>
        <translation>Meldung</translation>
    </message>
    <message>
        <source>Explanation by the AI</source>
        <translation>Erklärung der KI</translation>
    </message>
    <message>
        <source>Suggested fix</source>
        <translation>Vorgeschlagene Korrektur</translation>
    </message>
    <message>
        <source>Asked with the message, the mapping lines and the masked values of the record.</source>
        <translation>Gefragt mit der Meldung, den Zeilen der Zuordnung und den maskierten Werten des Datensatzes.</translation>
    </message>
    <message>
        <source>Asked with the message and the mapping lines, without record values.</source>
        <translation>Gefragt mit der Meldung und den Zeilen der Zuordnung, ohne Werte des Datensatzes.</translation>
    </message>
    <message>
        <source>Nothing was changed; change the mapping yourself.</source>
        <translation>Es wurde nichts geändert; ändern Sie die Zuordnung selbst.</translation>
    </message>
    <message>
        <source>Explain the failure of record %r</source>
        <translation>Den Fehler von Datensatz %r erklären</translation>
    </message>
    <message>
        <source>Explain</source>
        <translation>Erklären</translation>
    </message>
    <message>
        <source>Field</source>
        <translation>Feld</translation>
    </message>
    <message>
        <source>Now</source>
        <translation>Jetzt</translation>
    </message>
    <message>
        <source>New</source>
        <translation>Neu</translation>
    </message>
    <message>
        <source>Previous</source>
        <translation>Zurück</translation>
    </message>
    <message>
        <source>Next</source>
        <translation>Weiter</translation>
    </message>
    <message>
        <source>Log</source>
        <translation>Protokoll</translation>
    </message>
    <message>
        <source>Nothing logged yet.</source>
        <translation>Noch nichts protokolliert.</translation>
    </message>
    <message>
        <source>record %r</source>
        <translation>Datensatz %r</translation>
    </message>
    <message>
        <source>The cron part "sqliimport" queues the runs that are due every minute; the workers of the job queue do them.</source>
        <translation>Der Cron-Teil „sqliimport“ stellt jede Minute die fälligen Läufe in die Warteschlange; die Worker der Auftragswarteschlange führen sie aus.</translation>
    </message>
    <message>
        <source>No import runs by itself yet. A schedule is the fifth step of the import editor.</source>
        <translation>Noch läuft kein Import von selbst. Ein Zeitplan ist der fünfte Schritt des Import-Editors.</translation>
    </message>
    <message>
        <source>When</source>
        <translation>Wann</translation>
    </message>
    <message>
        <source>Next runs</source>
        <translation>Nächste Läufe</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>pausiert</translation>
    </message>
    <message>
        <source>Pause</source>
        <translation>Pausieren</translation>
    </message>
    <message>
        <source>Resume</source>
        <translation>Fortsetzen</translation>
    </message>
    <message>
        <source>Delete this schedule? The import stays.</source>
        <translation>Diesen Zeitplan löschen? Der Import bleibt.</translation>
    </message>
    <message>
        <source>Settings</source>
        <translation>Einstellungen</translation>
    </message>
    <message>
        <source>sqliimport.ini</source>
        <translation>sqliimport.ini</translation>
    </message>
    <message>
        <source>Changed in settings/override/sqliimport.ini.append.php or a siteaccess (the manual, page Settings, describes each).</source>
        <translation>Geändert in settings/override/sqliimport.ini.append.php oder einem Siteaccess (das Handbuch beschreibt jede auf der Seite Einstellungen).</translation>
    </message>
    <message>
        <source>Secrets</source>
        <translation>Geheimnisse</translation>
    </message>
    <message>
        <source>The names an import can give as a password (database, mailbox). The values are set in [SecretSettings] Secrets[] of the settings of the site and are never shown; env:VARIABLE names an environment variable instead.</source>
        <translation>Die Namen, die ein Import als Passwort angeben kann (Datenbank, Postfach). Die Werte werden in [SecretSettings] Secrets[] der Einstellungen der Site gesetzt und nie angezeigt; env:VARIABLE nennt stattdessen eine Umgebungsvariable.</translation>
    </message>
    <message>
        <source>Set the values in Setup, Secrets</source>
        <translation>Die Werte unter Setup, Geheimnisse setzen</translation>
    </message>
    <message>
        <source>No secret is set.</source>
        <translation>Es ist kein Geheimnis gesetzt.</translation>
    </message>
    <message>
        <source>Export import definitions</source>
        <translation>Importdefinitionen exportieren</translation>
    </message>
    <message>
        <source>Imports (none ticked: all)</source>
        <translation>Importe (keiner angehakt: alle)</translation>
    </message>
    <message>
        <source>A JSON file with the source, processing, mapping, target and options of each import; no run, schedule or file content.</source>
        <translation>Eine JSON-Datei mit Quelle, Verarbeitung, Zuordnung, Ziel und Optionen jedes Imports; ohne Läufe, Zeitpläne oder Dateiinhalte.</translation>
    </message>
    <message>
        <source>Export</source>
        <translation>Exportieren</translation>
    </message>
    <message>
        <source>Import import definitions</source>
        <translation>Importdefinitionen importieren</translation>
    </message>
    <message>
        <source>Exported file</source>
        <translation>Exportierte Datei</translation>
    </message>
    <message>
        <source>An import with the same identifier is updated; the others are created disabled. Uploaded source files are not part of an export: upload them again in the editor.</source>
        <translation>Ein Import mit demselben Bezeichner wird aktualisiert; die anderen werden deaktiviert angelegt. Hochgeladene Quelldateien gehören nicht zu einem Export: Laden Sie sie im Editor erneut hoch.</translation>
    </message>
    <message>
        <source>Import notifications</source>
        <translation>Import-Benachrichtigungen</translation>
    </message>
    <message>
        <source>All imports</source>
        <translation>Alle Importe</translation>
    </message>
    <message>
        <source>No new notifications</source>
        <translation>Keine neuen Benachrichtigungen</translation>
    </message>
    <message>
        <source>There is no import %ref of 7x SQLI Import that you may see.</source>
        <translation>Es gibt keinen Import %ref von 7x SQLI Import, den Sie sehen dürfen.</translation>
    </message>
    <message>
        <source>A 1.x handler reads its records itself; its fields cannot be read.</source>
        <translation>Ein Handler von 1.x liest seine Datensätze selbst; seine Felder lassen sich nicht lesen.</translation>
    </message>
    <message>
        <source>The source of the import could not be read: %error</source>
        <translation>Die Quelle des Imports konnte nicht gelesen werden: %error</translation>
    </message>
    <message>
        <source>the import %id of 7x SQLI Import, %label</source>
        <translation>der Import %id von 7x SQLI Import, %label</translation>
    </message>
    <message>
        <source>Copy of %name</source>
        <translation>Kopie von %name</translation>
    </message>
    <message>
        <source>The 1.x handler "%handler" is not available.</source>
        <translation>Der Handler „%handler“ von 1.x ist nicht verfügbar.</translation>
    </message>
    <message>
        <source>Choose an available 1.x handler.</source>
        <translation>Wählen Sie einen verfügbaren Handler von 1.x.</translation>
    </message>
    <message>
        <source>Choose a frequency.</source>
        <translation>Wählen Sie eine Häufigkeit.</translation>
    </message>
    <message>
        <source>Only POST requests are answered.</source>
        <translation>Es werden nur POST-Anfragen beantwortet.</translation>
    </message>
    <message>
        <source>The form token is missing or wrong. Please reload the page.</source>
        <translation>Das Formular-Token fehlt oder ist falsch. Bitte laden Sie die Seite neu.</translation>
    </message>
    <message>
        <source>You may not see the imports.</source>
        <translation>Sie dürfen die Importe nicht sehen.</translation>
    </message>
    <message>
        <source>The run does not exist.</source>
        <translation>Der Lauf existiert nicht.</translation>
    </message>
    <message>
        <source>Import notifications: %count new</source>
        <translation>Import-Benachrichtigungen: %count neu</translation>
    </message>
    <message>
        <source>The import does not exist.</source>
        <translation>Der Import existiert nicht.</translation>
    </message>
    <message>
        <source>Run #%id of "%name" is queued.</source>
        <translation>Lauf #%id von „%name“ ist in der Warteschlange.</translation>
    </message>
    <message>
        <source>Run #%id undoes run #%parent.</source>
        <translation>Lauf #%id macht Lauf #%parent rückgängig.</translation>
    </message>
    <message>
        <source>Run #%id is cancelled.</source>
        <translation>Lauf #%id ist abgebrochen.</translation>
    </message>
    <message>
        <source>Run #%id had ended already.</source>
        <translation>Lauf #%id war bereits beendet.</translation>
    </message>
    <message>
        <source>Run #%id retries the failed rows of run #%parent.</source>
        <translation>Lauf #%id wiederholt die fehlgeschlagenen Zeilen von Lauf #%parent.</translation>
    </message>
    <message>
        <source>"%name" is a copy (disabled until it is saved).</source>
        <translation>„%name“ ist eine Kopie (deaktiviert, bis sie gespeichert wird).</translation>
    </message>
    <message>
        <source>The import runs now; cancel the run first.</source>
        <translation>Der Import läuft gerade; brechen Sie zuerst den Lauf ab.</translation>
    </message>
    <message>
        <source>The import "%name" is deleted; its runs stay in the history.</source>
        <translation>Der Import „%name“ ist gelöscht; seine Läufe bleiben in der Historie.</translation>
    </message>
    <message>
        <source>The import "%name" is enabled.</source>
        <translation>Der Import „%name“ ist aktiviert.</translation>
    </message>
    <message>
        <source>The import "%name" is disabled.</source>
        <translation>Der Import „%name“ ist deaktiviert.</translation>
    </message>
    <message>
        <source>The schedule does not exist.</source>
        <translation>Der Zeitplan existiert nicht.</translation>
    </message>
    <message>
        <source>The schedule of "%name" is changed.</source>
        <translation>Der Zeitplan von „%name“ ist geändert.</translation>
    </message>
    <message>
        <source>The 1.x import #%id of "%handler" is requested; the next cron round starts it.</source>
        <translation>Der Import #%id von „%handler“ (1.x) ist beauftragt; die nächste Cron-Runde startet ihn.</translation>
    </message>
    <message>
        <source>The 1.x import request does not exist.</source>
        <translation>Der Importauftrag von 1.x existiert nicht.</translation>
    </message>
    <message>
        <source>The 1.x import #%id is %status.</source>
        <translation>Der Import #%id (1.x) ist %status.</translation>
    </message>
    <message>
        <source>The history of the 1.x import requests is purged.</source>
        <translation>Die Historie der Importaufträge von 1.x ist geleert.</translation>
    </message>
    <message>
        <source>The 1.x scheduled import does not exist.</source>
        <translation>Der geplante Import von 1.x existiert nicht.</translation>
    </message>
    <message>
        <source>The 1.x scheduled import "%name" is deleted.</source>
        <translation>Der geplante Import „%name“ von 1.x ist gelöscht.</translation>
    </message>
    <message>
        <source>The 1.x scheduled import "%name" is enabled.</source>
        <translation>Der geplante Import „%name“ von 1.x ist aktiviert.</translation>
    </message>
    <message>
        <source>The 1.x scheduled import "%name" is disabled.</source>
        <translation>Der geplante Import „%name“ von 1.x ist deaktiviert.</translation>
    </message>
    <message>
        <source>Unknown action.</source>
        <translation>Unbekannte Aktion.</translation>
    </message>
    <message>
        <source>The 1.x scheduled import "%name" is saved.</source>
        <translation>Der geplante Import „%name“ von 1.x ist gespeichert.</translation>
    </message>
    <message>
        <source>The import "%name" is saved.</source>
        <translation>Der Import „%name“ ist gespeichert.</translation>
    </message>
    <message>
        <source>All import and export tools</source>
        <translation>Alle Import- und Exportwerkzeuge</translation>
    </message>
    <message>
        <source>The 1.x scheduled import #%id is deleted.</source>
        <translation>Der geplante Import #%id von 1.x ist gelöscht.</translation>
    </message>
    <message>
        <source>The AI assist is not available: %reason</source>
        <translation>Die KI-Unterstützung ist nicht verfügbar: %reason</translation>
    </message>
    <message>
        <source>The failed row was not found.</source>
        <translation>Die fehlgeschlagene Zeile wurde nicht gefunden.</translation>
    </message>
    <message>
        <source>The AI assist could not answer: %error</source>
        <translation>Die KI-Unterstützung konnte nicht antworten: %error</translation>
    </message>
    <message>
        <source>A run can be summarized when it has ended.</source>
        <translation>Ein Lauf kann zusammengefasst werden, wenn er beendet ist.</translation>
    </message>
    <message>
        <source>There is no 1.x handler to schedule: the site lists none in sqliimport.ini [ImportSettings] AvailableSourceHandlers[]. Schedule a 2.x import in its editor instead.</source>
        <translation>Es gibt keinen Handler von 1.x zum Planen: Die Site listet keinen in sqliimport.ini [ImportSettings] AvailableSourceHandlers[]. Planen Sie stattdessen einen Import von 2.x in seinem Editor.</translation>
    </message>
    <message>
        <source>The file is not an export of import definitions.</source>
        <translation>Die Datei ist kein Export von Importdefinitionen.</translation>
    </message>
    <message>
        <source>%created imports created (disabled), %updated updated.</source>
        <translation>%created Importe angelegt (deaktiviert), %updated aktualisiert.</translation>
    </message>
    <message>
        <source>Statistics</source>
        <translation>Statistik</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/catalogue</name>
    <message>
        <source>CSV and delimited text</source>
        <translation>CSV und Text mit Trennzeichen</translation>
    </message>
    <message>
        <source>Comma, semicolon, tab or pipe separated text, as spreadsheets and databases export it. The first line names the columns, every further line is a record. Delimiter and encoding (UTF-8, Windows-1252) are detected.</source>
        <translation>Durch Komma, Semikolon, Tabulator oder senkrechten Strich getrennter Text, wie ihn Tabellenkalkulationen und Datenbanken exportieren. Die erste Zeile benennt die Spalten, jede weitere Zeile ist ein Datensatz. Trennzeichen und Zeichenkodierung (UTF-8, Windows-1252) werden erkannt.</translation>
    </message>
    <message>
        <source>Three products with title, price and an active flag</source>
        <translation>Drei Produkte mit Titel, Preis und einem Aktiv-Kennzeichen</translation>
    </message>
    <message>
        <source>TSV file (tab separated)</source>
        <translation>TSV-Datei (durch Tabulatoren getrennt)</translation>
    </message>
    <message>
        <source>Tab separated values: what a copy from a spreadsheet, many databases and analytics tools give. The first line names the columns. Quotes are read as in CSV.</source>
        <translation>Durch Tabulatoren getrennte Werte: was eine Kopie aus einer Tabellenkalkulation, viele Datenbanken und Analysewerkzeuge liefern. Die erste Zeile benennt die Spalten. Anführungszeichen werden wie bei CSV gelesen.</translation>
    </message>
    <message>
        <source>Three products, tab separated</source>
        <translation>Drei Produkte, durch Tabulatoren getrennt</translation>
    </message>
    <message>
        <source>Spreadsheet (XLSX, ODS)</source>
        <translation>Tabellenkalkulation (XLSX, ODS)</translation>
    </message>
    <message>
        <source>Excel (XLSX) and OpenDocument (ODS) workbooks, read without a library. The first row of a sheet names the columns; choose the sheet by name. Dates come as spreadsheet numbers: date(format=excel) turns them into dates.</source>
        <translation>Arbeitsmappen von Excel (XLSX) und OpenDocument (ODS), ohne Bibliothek gelesen. Die erste Zeile eines Tabellenblatts benennt die Spalten; wählen Sie das Tabellenblatt über seinen Namen. Datumswerte kommen als Zahlen der Tabellenkalkulation: date(format=excel) macht Datumswerte daraus.</translation>
    </message>
    <message>
        <source>Three products (XLSX)</source>
        <translation>Drei Produkte (XLSX)</translation>
    </message>
    <message>
        <source>Three products (ODS)</source>
        <translation>Drei Produkte (ODS)</translation>
    </message>
    <message>
        <source>JSON file or REST answer</source>
        <translation>JSON-Datei oder REST-Antwort</translation>
    </message>
    <message>
        <source>A JSON file or the answer of a REST address: a list of objects, at the top or at a path such as data.items. Nested values are mapped by their path (author.name, tags.0).</source>
        <translation>Eine JSON-Datei oder die Antwort einer REST-Adresse: eine Liste von Objekten, auf oberster Ebene oder unter einem Pfad wie data.items. Verschachtelte Werte werden über ihren Pfad zugeordnet (author.name, tags.0).</translation>
    </message>
    <message>
        <source>Two articles with nested authors and tags</source>
        <translation>Zwei Artikel mit verschachtelten Autoren und Tags</translation>
    </message>
    <message>
        <source>JSON Lines (NDJSON)</source>
        <translation>JSON Lines (NDJSON)</translation>
    </message>
    <message>
        <source>One JSON object per line, as log shippers, data warehouses and many APIs export large data. It is read as a stream, so files of any size import with little memory.</source>
        <translation>Ein JSON-Objekt je Zeile, wie Log-Versender, Data Warehouses und viele APIs große Datenmengen exportieren. Die Datei wird als Datenstrom gelesen, sodass sich Dateien jeder Größe mit wenig Speicher importieren lassen.</translation>
    </message>
    <message>
        <source>Two articles, one per line</source>
        <translation>Zwei Artikel, einer je Zeile</translation>
    </message>
    <message>
        <source>Apache Avro file</source>
        <translation>Apache-Avro-Datei</translation>
    </message>
    <message>
        <source>An Apache Avro object container file (.avro), as Kafka connectors and data pipelines write it: one record is one Avro record, nested records as dotted fields (address.city), dates and timestamps as ISO 8601, decimals as text. Read in PHP (codecs null and deflate; snappy and zstandard need their PHP extensions). For Parquet use the DuckDB format.</source>
        <translation>Eine Apache-Avro-Objektcontainerdatei (.avro), wie Kafka-Connectoren und Datenpipelines sie schreiben: Ein Datensatz ist ein Avro-Datensatz, verschachtelte Datensätze werden zu Feldern mit Punkt (address.city), Datums- und Zeitstempelwerte zu ISO 8601, Dezimalzahlen zu Text. In PHP gelesen (Codecs null und deflate; snappy und zstandard brauchen ihre PHP-Erweiterungen). Für Parquet verwenden Sie das Format DuckDB.</translation>
    </message>
    <message>
        <source>Five teas with a nested origin, tags, a price as a decimal and a date (deflate)</source>
        <translation>Fünf Tees mit verschachtelter Herkunft, Tags, einem Preis als Dezimalzahl und einem Datum (deflate)</translation>
    </message>
    <message>
        <source>Apache Arrow or Feather file</source>
        <translation>Apache-Arrow- oder Feather-Datei</translation>
    </message>
    <message>
        <source>An Apache Arrow IPC file (.arrow), a Feather v2 file (.feather) or an Arrow stream (.arrows), as pandas, Polars and R write them: one record is one row, struct columns as dotted fields, timestamps as ISO 8601, decimals as text. Read in PHP (LZ4 compression too; Zstandard needs the PHP extension zstd). For Parquet use the DuckDB format.</source>
        <translation>Eine Apache-Arrow-IPC-Datei (.arrow), eine Feather-v2-Datei (.feather) oder ein Arrow-Datenstrom (.arrows), wie pandas, Polars und R sie schreiben: Ein Datensatz ist eine Zeile, Struct-Spalten werden zu Feldern mit Punkt, Zeitstempel zu ISO 8601, Dezimalzahlen zu Text. In PHP gelesen (auch mit LZ4-Kompression; Zstandard braucht die PHP-Erweiterung zstd). Für Parquet verwenden Sie das Format DuckDB.</translation>
    </message>
    <message>
        <source>Five teas with a struct origin, a list of tags, a decimal price, a date and a timestamp (Arrow file format)</source>
        <translation>Fünf Tees mit einer Herkunft als Struct, einer Liste von Tags, einem Dezimalpreis, einem Datum und einem Zeitstempel (Arrow-Dateiformat)</translation>
    </message>
    <message>
        <source>Elasticsearch or OpenSearch index</source>
        <translation>Index von Elasticsearch oder OpenSearch</translation>
    </message>
    <message>
        <source>The documents of an Elasticsearch or OpenSearch index (or index pattern), read through the search API: one record is one document, its _source flattened to dotted field names (author.name) with _id and _index. A query in the query DSL selects them; scripts and every endpoint other than search are refused. The password or API key is the name of a secret in the settings.</source>
        <translation>Die Dokumente eines Index (oder Indexmusters) von Elasticsearch oder OpenSearch, über die Such-API gelesen: Ein Datensatz ist ein Dokument, seine _source wird zu Feldnamen mit Punkt (author.name) abgeflacht, mit _id und _index. Eine Abfrage in der Query DSL wählt sie aus; Skripte und jeder Endpunkt außer der Suche werden abgelehnt. Das Passwort oder der API-Schlüssel ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three articles of an index "articles" as the source writes it (one document per line)</source>
        <translation>Ein Abzug von drei Artikeln eines Index „articles“, wie die Quelle ihn schreibt (ein Dokument je Zeile)</translation>
    </message>
    <message>
        <source>The published articles of an Elasticsearch index, newest first, with an API key as the secret search_key</source>
        <translation>Die veröffentlichten Artikel eines Elasticsearch-Index, die neuesten zuerst, mit einem API-Schlüssel als Geheimnis search_key</translation>
    </message>
    <message>
        <source>All documents of the OpenSearch indices logs-2026-*, user reader with the password as the secret os_reader</source>
        <translation>Alle Dokumente der OpenSearch-Indizes logs-2026-*, Benutzer reader mit dem Passwort als Geheimnis os_reader</translation>
    </message>
    <message>
        <source>DuckDB query over Parquet, CSV or JSON files</source>
        <translation>DuckDB-Abfrage über Parquet-, CSV- oder JSON-Dateien</translation>
    </message>
    <message>
        <source>The rows of a DuckDB query over files on the server: Parquet, CSV or JSON files (one, several or a pattern such as exports/*.parquet) or a DuckDB database file, opened read-only. One record is one row. The files are the view "data" of the query; only a single SELECT is accepted and the files must lie inside [SourceSettings] AllowedPaths[]. Needs the duckdb program or PHP FFI with libduckdb on the server.</source>
        <translation>Die Zeilen einer DuckDB-Abfrage über Dateien auf dem Server: Parquet-, CSV- oder JSON-Dateien (eine, mehrere oder ein Muster wie exports/*.parquet) oder eine DuckDB-Datenbankdatei, nur lesend geöffnet. Ein Datensatz ist eine Zeile. Die Dateien sind die Sicht „data“ der Abfrage; nur ein einzelnes SELECT wird angenommen, und die Dateien müssen innerhalb von [SourceSettings] AllowedPaths[] liegen. Braucht das Programm duckdb oder PHP FFI mit libduckdb auf dem Server.</translation>
    </message>
    <message>
        <source>The CSV file duckdb-teas.csv (five teas): copy it into a directory of [SourceSettings] AllowedPaths[] and give its path</source>
        <translation>Die CSV-Datei duckdb-teas.csv (fünf Tees): Kopieren Sie sie in ein Verzeichnis von [SourceSettings] AllowedPaths[] und geben Sie ihren Pfad an</translation>
    </message>
    <message>
        <source>Every Parquet file of an export directory, the newest products first</source>
        <translation>Jede Parquet-Datei eines Exportverzeichnisses, die neuesten Produkte zuerst</translation>
    </message>
    <message>
        <source>A table of a DuckDB database file, opened read-only</source>
        <translation>Eine Tabelle einer DuckDB-Datenbankdatei, nur lesend geöffnet</translation>
    </message>
    <message>
        <source>Google Sheets (by its link)</source>
        <translation>Google Sheets (über seinen Link)</translation>
    </message>
    <message>
        <source>A spreadsheet in Google Sheets, read at every run from its link: share it so that anyone with the link can view it, or publish it to the web, and give the link as the address. The tab in the link is read, its first row names the columns.</source>
        <translation>Eine Tabelle in Google Sheets, bei jedem Lauf über ihren Link gelesen: Geben Sie sie so frei, dass jeder mit dem Link sie ansehen kann, oder veröffentlichen Sie sie im Web, und geben Sie den Link als Adresse an. Das Tabellenblatt im Link wird gelesen, seine erste Zeile benennt die Spalten.</translation>
    </message>
    <message>
        <source>What a sheet with three products gives (its CSV export)</source>
        <translation>Was ein Tabellenblatt mit drei Produkten liefert (sein CSV-Export)</translation>
    </message>
    <message>
        <source>YAML data file</source>
        <translation>YAML-Datendatei</translation>
    </message>
    <message>
        <source>A YAML file with a list of records at the top or at a path such as products: the data files of static site generators and of catalogues kept in a repository. Nested values are mapped by their path (price.amount).</source>
        <translation>Eine YAML-Datei mit einer Liste von Datensätzen auf oberster Ebene oder unter einem Pfad wie products: die Datendateien von Generatoren statischer Sites und von Katalogen, die in einem Repository gepflegt werden. Verschachtelte Werte werden über ihren Pfad zugeordnet (price.amount).</translation>
    </message>
    <message>
        <source>Three teas with nested prices and tags</source>
        <translation>Drei Tees mit verschachtelten Preisen und Tags</translation>
    </message>
    <message>
        <source>TOML data file</source>
        <translation>TOML-Datendatei</translation>
    </message>
    <message>
        <source>A TOML file whose array of tables ([[teas]]) or array at a path is the list of records, as Hugo data files and many tool configurations are written.</source>
        <translation>Eine TOML-Datei, deren Array von Tabellen ([[teas]]) oder Array unter einem Pfad die Liste der Datensätze ist, wie Hugo-Datendateien und viele Werkzeugkonfigurationen geschrieben sind.</translation>
    </message>
    <message>
        <source>Three teas as an array of tables</source>
        <translation>Drei Tees als Array von Tabellen</translation>
    </message>
    <message>
        <source>XML file</source>
        <translation>XML-Datei</translation>
    </message>
    <message>
        <source>Any XML file: name the element of one record (book, catalog/book), and its attributes (@id) and child elements become the fields. Read as a stream, external entities are never loaded, and an XSD can check it first.</source>
        <translation>Jede XML-Datei: Benennen Sie das Element eines Datensatzes (book, catalog/book), und seine Attribute (@id) und Kindelemente werden zu den Feldern. Als Datenstrom gelesen, externe Entitäten werden nie geladen, und eine XSD kann sie vorher prüfen.</translation>
    </message>
    <message>
        <source>A catalogue of two books</source>
        <translation>Ein Katalog mit zwei Büchern</translation>
    </message>
    <message>
        <source>RSS / Atom feed</source>
        <translation>RSS-/Atom-Feed</translation>
    </message>
    <message>
        <source>RSS 2.0 and Atom feeds of a site, a blog or a news service, from an address or a file. Every item is a record with id, title, link, summary, content, date, author, categories and enclosure.</source>
        <translation>RSS-2.0- und Atom-Feeds einer Site, eines Blogs oder eines Nachrichtendienstes, von einer Adresse oder aus einer Datei. Jeder Eintrag ist ein Datensatz mit id, title, link, summary, content, date, author, categories und enclosure.</translation>
    </message>
    <message>
        <source>An RSS feed of two items</source>
        <translation>Ein RSS-Feed mit zwei Einträgen</translation>
    </message>
    <message>
        <source>An Atom feed</source>
        <translation>Ein Atom-Feed</translation>
    </message>
    <message>
        <source>OPML outline / feed list</source>
        <translation>OPML-Gliederung / Feed-Liste</translation>
    </message>
    <message>
        <source>The outline format feed readers and podcast apps export their subscriptions in. Every outline is a record, with its feed and web address, its category and its place in the outline. Import the outlines as link objects, or let the import create one RSS import per feed of the list.</source>
        <translation>Das Gliederungsformat, in dem Feedreader und Podcast-Apps ihre Abonnements exportieren. Jede Gliederung ist ein Datensatz mit ihrer Feed- und Webadresse, ihrer Kategorie und ihrem Platz in der Gliederung. Importieren Sie die Gliederungen als Link-Objekte, oder lassen Sie den Import je Feed der Liste einen RSS-Import anlegen.</translation>
    </message>
    <message>
        <source>A feed list of three feeds in two categories</source>
        <translation>Eine Feed-Liste mit drei Feeds in zwei Kategorien</translation>
    </message>
    <message>
        <source>WordPress export (WXR)</source>
        <translation>WordPress-Export (WXR)</translation>
    </message>
    <message>
        <source>The export file of WordPress (Tools, Export): posts and pages with their author, date, categories, tags, excerpt and featured image, or the media, categories, tags and authors of the site. Import the media first and the posts after it to keep the images.</source>
        <translation>Die Exportdatei von WordPress (Werkzeuge, Exportieren): Beiträge und Seiten mit Autor, Datum, Kategorien, Tags, Textauszug und Beitragsbild, oder die Medien, Kategorien, Tags und Autoren der Site. Importieren Sie zuerst die Medien und danach die Beiträge, um die Bilder zu behalten.</translation>
    </message>
    <message>
        <source>A WordPress export with two posts, a page, an image and their categories and tags</source>
        <translation>Ein WordPress-Export mit zwei Beiträgen, einer Seite, einem Bild und ihren Kategorien und Tags</translation>
    </message>
    <message>
        <source>Markdown files (folder as ZIP)</source>
        <translation>Markdown-Dateien (Ordner als ZIP)</translation>
    </message>
    <message>
        <source>Markdown with front matter, one file or a folder as ZIP: a Jekyll or Hugo site, an Obsidian vault, a documentation folder. Every Markdown file is a record with its title, date, tags, summary, the body as HTML and every front matter key.</source>
        <translation>Markdown mit Front Matter, eine Datei oder ein Ordner als ZIP: eine Jekyll- oder Hugo-Site, ein Obsidian-Vault, ein Dokumentationsordner. Jede Markdown-Datei ist ein Datensatz mit Titel, Datum, Tags, Zusammenfassung, dem Textkörper als HTML und jedem Schlüssel des Front Matter.</translation>
    </message>
    <message>
        <source>A small site of three Markdown files (Jekyll, Hugo and Obsidian style) as ZIP</source>
        <translation>Eine kleine Site aus drei Markdown-Dateien (im Stil von Jekyll, Hugo und Obsidian) als ZIP</translation>
    </message>
    <message>
        <source>One Markdown file with YAML front matter</source>
        <translation>Eine Markdown-Datei mit YAML-Front-Matter</translation>
    </message>
    <message>
        <source>Podcast feed</source>
        <translation>Podcast-Feed</translation>
    </message>
    <message>
        <source>The RSS feed of a podcast: every episode is a record with its audio or video file (enclosure, type, length), duration in seconds, episode and season, cover image and the iTunes fields. The preset imports the episode file into a file object.</source>
        <translation>Der RSS-Feed eines Podcasts: Jede Folge ist ein Datensatz mit ihrer Audio- oder Videodatei (enclosure, type, length), Dauer in Sekunden, Folge und Staffel, Titelbild und den iTunes-Feldern. Die Vorgabe importiert die Datei der Folge in ein Dateiobjekt.</translation>
    </message>
    <message>
        <source>A podcast feed of two episodes</source>
        <translation>Ein Podcast-Feed mit zwei Folgen</translation>
    </message>
    <message>
        <source>YouTube or Vimeo channel feed</source>
        <translation>Kanal-Feed von YouTube oder Vimeo</translation>
    </message>
    <message>
        <source>The feed of a YouTube channel or playlist (youtube.com/feeds/videos.xml?channel_id=...) or of a Vimeo user (vimeo.com/&lt;user&gt;/videos/rss): every video is a record with its ID, embed address, thumbnail, description and, on YouTube, the view count. The preset stores a video as a link.</source>
        <translation>Der Feed eines YouTube-Kanals oder einer Playlist (youtube.com/feeds/videos.xml?channel_id=...) oder eines Vimeo-Benutzers (vimeo.com/&lt;user&gt;/videos/rss): Jedes Video ist ein Datensatz mit seiner ID, Einbettungsadresse, Vorschaubild, Beschreibung und, bei YouTube, der Zahl der Aufrufe. Die Vorgabe speichert ein Video als Link.</translation>
    </message>
    <message>
        <source>A YouTube channel feed (Atom)</source>
        <translation>Ein YouTube-Kanal-Feed (Atom)</translation>
    </message>
    <message>
        <source>A Vimeo user feed (RSS)</source>
        <translation>Ein Vimeo-Benutzer-Feed (RSS)</translation>
    </message>
    <message>
        <source>E-mail inbox (IMAP)</source>
        <translation>E-Mail-Postfach (IMAP)</translation>
    </message>
    <message>
        <source>The messages of an IMAP mailbox (a press inbox, a submissions address): one record is one message with sender, recipients, subject, date, text and HTML body and the names and sizes of its attachments. The mailbox is read without changing it unless "Mark as seen" is ticked; the password is the name of a secret in the settings.</source>
        <translation>Die Nachrichten eines IMAP-Postfachs (ein Presse-Postfach, eine Adresse für Einsendungen): Ein Datensatz ist eine Nachricht mit Absender, Empfängern, Betreff, Datum, Text- und HTML-Inhalt und den Namen und Größen ihrer Anhänge. Das Postfach wird gelesen, ohne es zu ändern, es sei denn, „Als gelesen markieren“ ist angehakt; das Passwort ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>The unread messages of the last 14 days of press@example.org on imap.example.org (port 993, SSL), the password as the secret press_mailbox</source>
        <translation>Die ungelesenen Nachrichten der letzten 14 Tage von press@example.org auf imap.example.org (Port 993, SSL), das Passwort als Geheimnis press_mailbox</translation>
    </message>
    <message>
        <source>Export (xrowextract) file</source>
        <translation>Datei von Export (xrowextract)</translation>
    </message>
    <message>
        <source>A class export of Export (xrowextract) as CSV, JSON or XML. Its column manifest names the class and the attribute of every column, so the mapping is made exactly; with the remote ID column, objects are updated instead of created. Use "Re-import this file" on the Jobs page of Export, or upload the file with its .manifest.json.</source>
        <translation>Ein Klassenexport von Export (xrowextract) als CSV, JSON oder XML. Sein Spaltenmanifest nennt die Klasse und das Attribut jeder Spalte, sodass die Zuordnung genau entsteht; mit der Spalte der Remote-ID werden Objekte aktualisiert statt angelegt. Verwenden Sie „Re-import this file“ auf der Seite Jobs von Export, oder laden Sie die Datei mit ihrer .manifest.json hoch.</translation>
    </message>
    <message>
        <source>A folder export as JSON with its manifest inside</source>
        <translation>Ein Ordnerexport als JSON mit seinem Manifest darin</translation>
    </message>
    <message>
        <source>The same export as CSV (with no manifest the class option names the class)</source>
        <translation>Derselbe Export als CSV (ohne Manifest nennt die Option class die Klasse)</translation>
    </message>
    <message>
        <source>SQL database or query</source>
        <translation>SQL-Datenbank oder -Abfrage</translation>
    </message>
    <message>
        <source>The rows of an SQL query against a MySQL or MariaDB, PostgreSQL, SQLite, Oracle or SQL Server database (an intranet database, the database of another application): one record is one row, its fields the columns of the query. Only a single SELECT is accepted and the connection is read-only; the password is the name of a secret in the settings. Oracle needs oci8 or pdo_oci, SQL Server pdo_sqlsrv, pdo_dblib or PDO_ODBC in the PHP of the job queue's workers.</source>
        <translation>Die Zeilen einer SQL-Abfrage an eine Datenbank von MySQL oder MariaDB, PostgreSQL, SQLite, Oracle oder SQL Server (eine Intranet-Datenbank, die Datenbank einer anderen Anwendung): Ein Datensatz ist eine Zeile, seine Felder sind die Spalten der Abfrage. Nur ein einzelnes SELECT wird angenommen, und die Verbindung ist nur lesend; das Passwort ist der Name eines Geheimnisses in den Einstellungen. Oracle braucht oci8 oder pdo_oci, SQL Server pdo_sqlsrv, pdo_dblib oder PDO_ODBC im PHP der Worker der Auftragswarteschlange.</translation>
    </message>
    <message>
        <source>The SQLite database sql-teas.sqlite (five teas): copy it into a directory of [SourceSettings] AllowedPaths[] and give its path</source>
        <translation>Die SQLite-Datenbank sql-teas.sqlite (fünf Tees): Kopieren Sie sie in ein Verzeichnis von [SourceSettings] AllowedPaths[] und geben Sie ihren Pfad an</translation>
    </message>
    <message>
        <source>A MySQL database of the intranet: host db.example.org, user reader, the password as the secret crm_db</source>
        <translation>Eine MySQL-Datenbank des Intranets: Host db.example.org, Benutzer reader, das Passwort als Geheimnis crm_db</translation>
    </message>
    <message>
        <source>An Oracle database: host ora.example.org, service ORCLPDB1, the password as the secret erp_db</source>
        <translation>Eine Oracle-Datenbank: Host ora.example.org, Dienst ORCLPDB1, das Passwort als Geheimnis erp_db</translation>
    </message>
    <message>
        <source>A SQL Server database: host mssql.example.org, database Shop, the 100 newest products, the password as the secret shop_db</source>
        <translation>Eine SQL-Server-Datenbank: Host mssql.example.org, Datenbank Shop, die 100 neuesten Produkte, das Passwort als Geheimnis shop_db</translation>
    </message>
    <message>
        <source>MongoDB collection</source>
        <translation>MongoDB-Collection</translation>
    </message>
    <message>
        <source>The documents of a MongoDB collection (the database of another application, a product or event store): one record is one document, nested documents as dotted fields (author.name), ObjectId, dates, decimals and binary values as text, arrays as JSON or a joined list. Only a find with a filter, projection and sort, or an aggregation pipeline of reading stages, is run, read page by page; the password is the name of a secret in the settings. MongoDB Atlas by its mongodb+srv:// URI. Needs the PHP extension mongodb in the PHP of the job queue's workers.</source>
        <translation>Die Dokumente einer MongoDB-Collection (die Datenbank einer anderen Anwendung, ein Produkt- oder Ereignisspeicher): Ein Datensatz ist ein Dokument, verschachtelte Dokumente werden zu Feldern mit Punkt (author.name), ObjectId, Datumswerte, Dezimalzahlen und Binärwerte zu Text, Arrays zu JSON oder einer verbundenen Liste. Ausgeführt wird nur ein find mit Filter, Projektion und Sortierung oder eine Aggregations-Pipeline aus lesenden Stufen, Seite für Seite gelesen; das Passwort ist der Name eines Geheimnisses in den Einstellungen. MongoDB Atlas über seine mongodb+srv://-URI. Braucht die PHP-Erweiterung mongodb im PHP der Worker der Auftragswarteschlange.</translation>
    </message>
    <message>
        <source>mongodb-articles.ndjson: what a run reads from a collection of articles (three documents, flattened), to try the mapping</source>
        <translation>mongodb-articles.ndjson: was ein Lauf aus einer Collection von Artikeln liest (drei Dokumente, abgeflacht), um die Zuordnung auszuprobieren</translation>
    </message>
    <message>
        <source>The published articles of the collection articles in the database cms on mongo.example.org, newest first, user reader, the password as the secret cms_mongo</source>
        <translation>Die veröffentlichten Artikel der Collection articles in der Datenbank cms auf mongo.example.org, die neuesten zuerst, Benutzer reader, das Passwort als Geheimnis cms_mongo</translation>
    </message>
    <message>
        <source>A replica set read from a secondary: mongodb://db1.example.org,db2.example.org/?replicaSet=rs0, collection products, the password as the secret shop_mongo</source>
        <translation>Ein Replica Set, von einem Secondary gelesen: mongodb://db1.example.org,db2.example.org/?replicaSet=rs0, Collection products, das Passwort als Geheimnis shop_mongo</translation>
    </message>
    <message>
        <source>MongoDB Atlas: the orders per customer of 2026 by an aggregation pipeline (mongodb+srv://cluster0.example.mongodb.net), the password as the secret atlas_reader</source>
        <translation>MongoDB Atlas: die Bestellungen je Kunde von 2026 über eine Aggregations-Pipeline (mongodb+srv://cluster0.example.mongodb.net), das Passwort als Geheimnis atlas_reader</translation>
    </message>
    <message>
        <source>Redis keys</source>
        <translation>Redis-Schlüssel</translation>
    </message>
    <message>
        <source>The keys of a Redis database that match a pattern (a cache of product data, a store of another application): one record is one key with _key, _type and _ttl, a hash's fields as fields, a string as value (decoded when it is JSON), the values of a list, set or sorted set, the entries of a stream, a RedisJSON document's fields. The keys are walked with SCAN, only reading commands are sent; the password is the name of a secret in the settings. Uses the PHP extension redis where it is installed, else a small client of its own.</source>
        <translation>Die Schlüssel einer Redis-Datenbank, die zu einem Muster passen (ein Cache von Produktdaten, ein Speicher einer anderen Anwendung): Ein Datensatz ist ein Schlüssel mit _key, _type und _ttl, die Felder eines Hashs als Felder, ein String als value (dekodiert, wenn er JSON ist), die Werte einer List, eines Sets oder Sorted Sets, die Einträge eines Streams, die Felder eines RedisJSON-Dokuments. Die Schlüssel werden mit SCAN durchlaufen, es werden nur lesende Befehle gesendet; das Passwort ist der Name eines Geheimnisses in den Einstellungen. Verwendet die PHP-Erweiterung redis, wo sie installiert ist, sonst einen eigenen kleinen Client.</translation>
    </message>
    <message>
        <source>redis-products.ndjson: what a run reads from the keys product:* (hashes, a JSON string, a set), to try the mapping</source>
        <translation>redis-products.ndjson: was ein Lauf aus den Schlüsseln product:* liest (Hashes, ein JSON-String, ein Set), um die Zuordnung auszuprobieren</translation>
    </message>
    <message>
        <source>The product hashes product:* of database 2 on redis.example.org (TLS, port 6380), user reader, the password as the secret cache_redis</source>
        <translation>Die Produkt-Hashes product:* der Datenbank 2 auf redis.example.org (TLS, Port 6380), Benutzer reader, das Passwort als Geheimnis cache_redis</translation>
    </message>
    <message>
        <source>The events of the stream events:site on redis.example.org, the first 1000 entries, no password</source>
        <translation>Die Ereignisse des Streams events:site auf redis.example.org, die ersten 1000 Einträge, ohne Passwort</translation>
    </message>
    <message>
        <source>CouchDB database</source>
        <translation>CouchDB-Datenbank</translation>
    </message>
    <message>
        <source>The documents of a CouchDB database (or Cloudant), read over HTTP: all documents, the rows of a view or the answer of a Mango query. One record is one document with _id and _rev, nested objects as dotted fields (author.name). Only reading requests are sent; the password is the name of a secret in the settings.</source>
        <translation>Die Dokumente einer CouchDB-Datenbank (oder Cloudant), über HTTP gelesen: alle Dokumente, die Zeilen einer View oder die Antwort einer Mango-Abfrage. Ein Datensatz ist ein Dokument mit _id und _rev, verschachtelte Objekte werden zu Feldern mit Punkt (author.name). Es werden nur lesende Anfragen gesendet; das Passwort ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>couchdb-articles.ndjson: what a run reads from a database of articles (three documents, flattened), to try the mapping</source>
        <translation>couchdb-articles.ndjson: was ein Lauf aus einer Datenbank von Artikeln liest (drei Dokumente, abgeflacht), um die Zuordnung auszuprobieren</translation>
    </message>
    <message>
        <source>The published articles of the database articles on couch.example.org by a Mango query, user reader, the password as the secret couch_reader</source>
        <translation>Die veröffentlichten Artikel der Datenbank articles auf couch.example.org über eine Mango-Abfrage, Benutzer reader, das Passwort als Geheimnis couch_reader</translation>
    </message>
    <message>
        <source>The rows of the view by_date of the design document news, with their documents, cookie authentication</source>
        <translation>Die Zeilen der View by_date des Design-Dokuments news, mit ihren Dokumenten, Cookie-Authentifizierung</translation>
    </message>
    <message>
        <source>Couchbase query (SQL++)</source>
        <translation>Couchbase-Abfrage (SQL++)</translation>
    </message>
    <message>
        <source>The results of a SQL++ (N1QL) SELECT of Couchbase Server or Capella, read through the Query service (port 8093, TLS 18093) with readonly=true: one record is one result, nested objects as dotted fields (author.name). Every other statement is refused; the password is the name of a secret in the settings.</source>
        <translation>Die Ergebnisse eines SELECT in SQL++ (N1QL) von Couchbase Server oder Capella, über den Query-Dienst gelesen (Port 8093, TLS 18093) mit readonly=true: Ein Datensatz ist ein Ergebnis, verschachtelte Objekte werden zu Feldern mit Punkt (author.name). Jede andere Anweisung wird abgelehnt; das Passwort ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>couchbase-products.ndjson: what a run reads from a query of products (three results, flattened), to try the mapping</source>
        <translation>couchbase-products.ndjson: was ein Lauf aus einer Abfrage von Produkten liest (drei Ergebnisse, abgeflacht), um die Zuordnung auszuprobieren</translation>
    </message>
    <message>
        <source>The active products of the collection shop.inventory.products, user reader, the password as the secret cb_reader, paged by sku</source>
        <translation>Die aktiven Produkte der Collection shop.inventory.products, Benutzer reader, das Passwort als Geheimnis cb_reader, seitenweise nach sku</translation>
    </message>
    <message>
        <source>Articles with their authors (WITH ... SELECT) in the scope cms.content, newest first</source>
        <translation>Artikel mit ihren Autoren (WITH ... SELECT) im Scope cms.content, die neuesten zuerst</translation>
    </message>
    <message>
        <source>Azure Cosmos DB container (API for NoSQL)</source>
        <translation>Azure-Cosmos-DB-Container (API für NoSQL)</translation>
    </message>
    <message>
        <source>The items of an Azure Cosmos DB container (API for NoSQL) that a SELECT query gives, read through the REST API and paged with continuation tokens: one record is one item, nested objects as dotted fields (author.name). The key (a read-only key or a resource token) is the name of a secret in the settings. An account of the API for MongoDB is read with the MongoDB source.</source>
        <translation>Die Elemente eines Azure-Cosmos-DB-Containers (API für NoSQL), die eine SELECT-Abfrage liefert, über die REST-API gelesen und mit Fortsetzungstoken seitenweise abgerufen: Ein Datensatz ist ein Element, verschachtelte Objekte werden zu Feldern mit Punkt (author.name). Der Schlüssel (ein Nur-Lese-Schlüssel oder ein Ressourcentoken) ist der Name eines Geheimnisses in den Einstellungen. Ein Konto der API für MongoDB wird mit der Quelle MongoDB gelesen.</translation>
    </message>
    <message>
        <source>cosmosdb-orders.ndjson: what a run reads from a container of orders (three items, flattened), to try the mapping</source>
        <translation>cosmosdb-orders.ndjson: was ein Lauf aus einem Container von Bestellungen liest (drei Elemente, abgeflacht), um die Zuordnung auszuprobieren</translation>
    </message>
    <message>
        <source>The open orders of the container orders, signed with the read-only key as the secret cosmos_readonly</source>
        <translation>Die offenen Bestellungen des Containers orders, signiert mit dem Nur-Lese-Schlüssel als Geheimnis cosmos_readonly</translation>
    </message>
    <message>
        <source>The products of one partition (category "tea") with a resource token as the secret cosmos_token</source>
        <translation>Die Produkte einer Partition (Kategorie „tea“) mit einem Ressourcentoken als Geheimnis cosmos_token</translation>
    </message>
    <message>
        <source>Amazon DynamoDB table</source>
        <translation>Amazon-DynamoDB-Tabelle</translation>
    </message>
    <message>
        <source>The items of an Amazon DynamoDB table or index, read with Scan or Query through the DynamoDB API (signed with AWS Signature Version 4, no SDK): one record is one item, maps flattened to dotted names (address.city). Only Scan and Query are sent; the secret access key is the name of a secret in the settings.</source>
        <translation>Die Elemente einer Tabelle oder eines Index von Amazon DynamoDB, mit Scan oder Query über die DynamoDB-API gelesen (signiert mit AWS Signature Version 4, ohne SDK): Ein Datensatz ist ein Element, Maps werden zu Namen mit Punkt abgeflacht (address.city). Es werden nur Scan und Query gesendet; der geheime Zugriffsschlüssel ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three products of a table "products" as the source writes it (one item per line)</source>
        <translation>Ein Abzug von drei Produkten einer Tabelle „products“, wie die Quelle ihn schreibt (ein Element je Zeile)</translation>
    </message>
    <message>
        <source>The orders of one customer, newest first, through the index by_customer (Query)</source>
        <translation>Die Bestellungen eines Kunden, die neuesten zuerst, über den Index by_customer (Query)</translation>
    </message>
    <message>
        <source>The published articles of a table on DynamoDB Local (a custom endpoint, listed in AllowedHosts)</source>
        <translation>Die veröffentlichten Artikel einer Tabelle auf DynamoDB Local (ein eigener Endpunkt, in AllowedHosts aufgeführt)</translation>
    </message>
    <message>
        <source>Google Cloud Firestore collection</source>
        <translation>Collection von Google Cloud Firestore</translation>
    </message>
    <message>
        <source>The documents of a Firestore collection or collection group, read through the Firestore REST API with a service account: one record is one document, _id and _path with its fields, maps flattened to dotted names. A structured query (where, orderBy, select) or the list of the collection; nothing is written. The service account key is the name of a secret in the settings.</source>
        <translation>Die Dokumente einer Firestore-Collection oder Collection-Gruppe, über die Firestore-REST-API mit einem Dienstkonto gelesen: Ein Datensatz ist ein Dokument, _id und _path mit seinen Feldern, Maps werden zu Namen mit Punkt abgeflacht. Eine strukturierte Abfrage (where, orderBy, select) oder die Liste der Collection; es wird nichts geschrieben. Der Schlüssel des Dienstkontos ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three articles of a collection "articles" as the source writes it (one document per line)</source>
        <translation>Ein Abzug von drei Artikeln einer Collection „articles“, wie die Quelle ihn schreibt (ein Dokument je Zeile)</translation>
    </message>
    <message>
        <source>The published articles, newest first, with the fields title, body and author</source>
        <translation>Die veröffentlichten Artikel, die neuesten zuerst, mit den Feldern title, body und author</translation>
    </message>
    <message>
        <source>Every order of every customer (the collection group "orders" below customers/*)</source>
        <translation>Jede Bestellung jedes Kunden (die Collection-Gruppe „orders“ unterhalb von customers/*)</translation>
    </message>
    <message>
        <source>Google BigQuery query</source>
        <translation>Google-BigQuery-Abfrage</translation>
    </message>
    <message>
        <source>The rows of a BigQuery query (GoogleSQL) through the BigQuery REST API with a service account: one record is one row, RECORD columns flattened to dotted names, REPEATED as JSON. Only a single SELECT or WITH is accepted, every query has a bytes billed limit, and Test connection is a free dry run. The service account key is the name of a secret in the settings.</source>
        <translation>Die Zeilen einer BigQuery-Abfrage (GoogleSQL) über die BigQuery-REST-API mit einem Dienstkonto: Ein Datensatz ist eine Zeile, RECORD-Spalten werden zu Namen mit Punkt abgeflacht, REPEATED zu JSON. Nur ein einzelnes SELECT oder WITH wird angenommen, jede Abfrage hat eine Grenze der abgerechneten Bytes, und Verbindung testen ist ein kostenloser Probelauf. Der Schlüssel des Dienstkontos ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three orders as the source writes it from a query (one row per line)</source>
        <translation>Ein Abzug von drei Bestellungen, wie die Quelle ihn aus einer Abfrage schreibt (ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The orders of the last 30 days, at most 1 GB scanned, in the EU location</source>
        <translation>Die Bestellungen der letzten 30 Tage, höchstens 1 GB durchsucht, am Standort EU</translation>
    </message>
    <message>
        <source>A public dataset, with WITH (the job runs in the project of the service account)</source>
        <translation>Ein öffentliches Dataset, mit WITH (der Job läuft im Projekt des Dienstkontos)</translation>
    </message>
    <message>
        <source>Firebase Realtime Database path</source>
        <translation>Pfad einer Firebase Realtime Database</translation>
    </message>
    <message>
        <source>The children of a path of a Firebase Realtime Database, read through its REST API in key order: one record is one child, _key with its value, nested objects flattened to dotted names. Only GET requests; a database secret or a service account key is the name of a secret in the settings.</source>
        <translation>Die Kinder eines Pfads einer Firebase Realtime Database, über ihre REST-API in der Reihenfolge der Schlüssel gelesen: Ein Datensatz ist ein Kind, _key mit seinem Wert, verschachtelte Objekte werden zu Namen mit Punkt abgeflacht. Nur GET-Anfragen; ein Datenbank-Secret oder ein Schlüssel eines Dienstkontos ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three articles under /articles as the source writes it (one child per line)</source>
        <translation>Ein Abzug von drei Artikeln unter /articles, wie die Quelle ihn schreibt (ein Kind je Zeile)</translation>
    </message>
    <message>
        <source>The products of a European database, read with a legacy database secret, keys first (shallow)</source>
        <translation>Die Produkte einer europäischen Datenbank, mit einem alten Datenbank-Secret gelesen, zuerst die Schlüssel (shallow)</translation>
    </message>
    <message>
        <source>Cassandra or ScyllaDB query</source>
        <translation>Abfrage an Cassandra oder ScyllaDB</translation>
    </message>
    <message>
        <source>The rows of a CQL SELECT against Apache Cassandra or ScyllaDB: one record is one row, user-defined types flattened to dotted names, lists and sets as JSON or joined, maps as JSON. Only a single SELECT is accepted; the password is the name of a secret in the settings. Through the PHP extension cassandra or the built-in client of the native protocol.</source>
        <translation>Die Zeilen eines CQL-SELECT an Apache Cassandra oder ScyllaDB: Ein Datensatz ist eine Zeile, benutzerdefinierte Typen werden zu Namen mit Punkt abgeflacht, Lists und Sets zu JSON oder verbunden, Maps zu JSON. Nur ein einzelnes SELECT wird angenommen; das Passwort ist der Name eines Geheimnisses in den Einstellungen. Über die PHP-Erweiterung cassandra oder den eingebauten Client des nativen Protokolls.</translation>
    </message>
    <message>
        <source>A snapshot of three events as the source writes it from a SELECT (one row per line)</source>
        <translation>Ein Abzug von drei Ereignissen, wie die Quelle ihn aus einem SELECT schreibt (ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The products of one category, user reader with the password as the secret cql_reader, over TLS</source>
        <translation>Die Produkte einer Kategorie, Benutzer reader mit dem Passwort als Geheimnis cql_reader, über TLS</translation>
    </message>
    <message>
        <source>Neo4j or Memgraph graph (Cypher)</source>
        <translation>Graph von Neo4j oder Memgraph (Cypher)</translation>
    </message>
    <message>
        <source>The rows of a Cypher query against Neo4j (HTTP or Bolt) or Memgraph (Bolt): a node as its properties with _labels and _element_id, a relationship with _type, paths as JSON, temporal values in ISO 8601. Only reading clauses and procedures are accepted and the transaction is opened read-only; the password is the name of a secret in the settings.</source>
        <translation>Die Zeilen einer Cypher-Abfrage an Neo4j (HTTP oder Bolt) oder Memgraph (Bolt): ein Knoten als seine Eigenschaften mit _labels und _element_id, eine Beziehung mit _type, Pfade als JSON, Zeitwerte in ISO 8601. Nur lesende Klauseln und Prozeduren werden angenommen, und die Transaktion wird nur lesend geöffnet; das Passwort ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three article nodes as the source writes it (one row per line)</source>
        <translation>Ein Abzug von drei Artikelknoten, wie die Quelle ihn schreibt (ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The articles with their authors from Neo4j over HTTP, user reader with the password as the secret graph_reader</source>
        <translation>Die Artikel mit ihren Autoren aus Neo4j über HTTP, Benutzer reader mit dem Passwort als Geheimnis graph_reader</translation>
    </message>
    <message>
        <source>The products of a Memgraph database over Bolt</source>
        <translation>Die Produkte einer Memgraph-Datenbank über Bolt</translation>
    </message>
    <message>
        <source>ArangoDB query (AQL)</source>
        <translation>ArangoDB-Abfrage (AQL)</translation>
    </message>
    <message>
        <source>The results of an AQL query against ArangoDB, read batch by batch through the cursor API: a document with its nested objects flattened to dotted names and _key, _id and _rev kept. INSERT, UPDATE, REPLACE, REMOVE, UPSERT and calls of code are refused; the password or token is the name of a secret in the settings.</source>
        <translation>Die Ergebnisse einer AQL-Abfrage an ArangoDB, Stapel für Stapel über die Cursor-API gelesen: ein Dokument mit seinen verschachtelten Objekten, zu Namen mit Punkt abgeflacht, mit _key, _id und _rev. INSERT, UPDATE, REPLACE, REMOVE, UPSERT und Aufrufe von Code werden abgelehnt; das Passwort oder Token ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three documents of a collection "articles" as the source writes it (one document per line)</source>
        <translation>Ein Abzug von drei Dokumenten einer Collection „articles“, wie die Quelle ihn schreibt (ein Dokument je Zeile)</translation>
    </message>
    <message>
        <source>The published articles, newest first, user reader with the password as the secret arango_reader</source>
        <translation>Die veröffentlichten Artikel, die neuesten zuerst, Benutzer reader mit dem Passwort als Geheimnis arango_reader</translation>
    </message>
    <message>
        <source>ClickHouse query</source>
        <translation>ClickHouse-Abfrage</translation>
    </message>
    <message>
        <source>The rows of a SELECT against ClickHouse through its HTTP interface, read page by page with readonly set, so the server refuses every write; statements other than SELECT and table functions that reach outside the server are refused before. The password is the name of a secret in the settings.</source>
        <translation>Die Zeilen eines SELECT an ClickHouse über seine HTTP-Schnittstelle, Seite für Seite mit gesetztem readonly gelesen, sodass der Server jedes Schreiben ablehnt; andere Anweisungen als SELECT und Tabellenfunktionen, die über den Server hinausgreifen, werden vorher abgelehnt. Das Passwort ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three rows of a table "page_views" as the source writes it (one row per line)</source>
        <translation>Ein Abzug von drei Zeilen einer Tabelle „page_views“, wie die Quelle ihn schreibt (ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The most read pages of the last week, user reader with the password as the secret clickhouse_reader</source>
        <translation>Die meistgelesenen Seiten der letzten Woche, Benutzer reader mit dem Passwort als Geheimnis clickhouse_reader</translation>
    </message>
    <message>
        <source>Snowflake query</source>
        <translation>Snowflake-Abfrage</translation>
    </message>
    <message>
        <source>The rows of a SELECT (or SHOW, DESCRIBE) against Snowflake through its SQL API with key-pair authentication, read partition by partition, values typed by the result (numbers of FIXED as text, timestamps in ISO 8601, VARIANT, OBJECT and ARRAY decoded). Statements that write are refused; the private key is the name of a secret in the settings.</source>
        <translation>Die Zeilen eines SELECT (oder SHOW, DESCRIBE) an Snowflake über seine SQL-API mit Schlüsselpaar-Authentifizierung, Partition für Partition gelesen, die Werte nach dem Ergebnis typisiert (Zahlen von FIXED als Text, Zeitstempel in ISO 8601, VARIANT, OBJECT und ARRAY dekodiert). Schreibende Anweisungen werden abgelehnt; der private Schlüssel ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three orders as the source writes it (one row per line)</source>
        <translation>Ein Abzug von drei Bestellungen, wie die Quelle ihn schreibt (ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The orders of the last 30 days with warehouse, database, schema and role</source>
        <translation>Die Bestellungen der letzten 30 Tage mit Warehouse, Datenbank, Schema und Rolle</translation>
    </message>
    <message>
        <source>InfluxDB query (Flux or InfluxQL)</source>
        <translation>InfluxDB-Abfrage (Flux oder InfluxQL)</translation>
    </message>
    <message>
        <source>The rows of a Flux query (InfluxDB 2, Cloud) or an InfluxQL query (InfluxDB 1 and the 1.x API): one record per row with _time in ISO 8601 and the tags and fields as columns. Flux that writes (to(), wideTo()) or reaches out (http, sql, notification endpoints) and InfluxQL INTO are refused; the token is the name of a secret in the settings.</source>
        <translation>Die Zeilen einer Flux-Abfrage (InfluxDB 2, Cloud) oder einer InfluxQL-Abfrage (InfluxDB 1 und die 1.x-API): ein Datensatz je Zeile mit _time in ISO 8601 und den Tags und Feldern als Spalten. Flux, das schreibt (to(), wideTo()) oder nach außen greift (http, sql, Benachrichtigungsendpunkte), und InfluxQL INTO werden abgelehnt; das Token ist der Name eines Geheimnisses in den Einstellungen.</translation>
    </message>
    <message>
        <source>A snapshot of three readings of a measurement "climate" as the source writes it (pivoted, one row per line)</source>
        <translation>Ein Abzug von drei Messwerten eines Measurements „climate“, wie die Quelle ihn schreibt (pivotiert, ein Datensatz je Zeile)</translation>
    </message>
    <message>
        <source>The hourly mean temperature of the last week with InfluxQL (InfluxDB 1)</source>
        <translation>Die stündliche Durchschnittstemperatur der letzten Woche mit InfluxQL (InfluxDB 1)</translation>
    </message>
    <message>
        <source>LDAP directory</source>
        <translation>LDAP-Verzeichnis</translation>
    </message>
    <message>
        <source>The entries of an LDAP directory (OpenLDAP, Active Directory, 389 Directory Server): one record is one entry of a search (base DN, filter, scope), its fields the DN and the attributes. Read-only (bind and search only), over ldaps:// or StartTLS; the password is the name of a secret in the settings; large results are read page by page. Needs the PHP extension ldap in the PHP of the job queue's workers. A directory of people holds personal data.</source>
        <translation>Die Einträge eines LDAP-Verzeichnisses (OpenLDAP, Active Directory, 389 Directory Server): Ein Datensatz ist ein Eintrag einer Suche (Basis-DN, Filter, Bereich), seine Felder sind der DN und die Attribute. Nur lesend (nur Bind und Suche), über ldaps:// oder StartTLS; das Passwort ist der Name eines Geheimnisses in den Einstellungen; große Ergebnisse werden Seite für Seite gelesen. Braucht die PHP-Erweiterung ldap im PHP der Worker der Auftragswarteschlange. Ein Verzeichnis von Personen enthält personenbezogene Daten.</translation>
    </message>
    <message>
        <source>A snapshot of three entries of a staff directory (what a run writes): DN, names, e-mail addresses, a multi-valued attribute and a photo as base64</source>
        <translation>Ein Abzug von drei Einträgen eines Mitarbeiterverzeichnisses (was ein Lauf schreibt): DN, Namen, E-Mail-Adressen, ein mehrwertiges Attribut und ein Foto als base64</translation>
    </message>
    <message>
        <source>The people of ou=Staff of ldap.example.org over ldaps://, bound as a reader, the password as the secret ldap_reader</source>
        <translation>Die Personen von ou=Staff auf ldap.example.org über ldaps://, als Leser gebunden, das Passwort als Geheimnis ldap_reader</translation>
    </message>
    <message>
        <source>The users of an Active Directory group over StartTLS, named by their objectGUID, the password as the secret ad_reader</source>
        <translation>Die Benutzer einer Active-Directory-Gruppe über StartTLS, nach ihrer objectGUID benannt, das Passwort als Geheimnis ad_reader</translation>
    </message>
    <message>
        <source>CIE form submissions (bccie CSV)</source>
        <translation>Formulareinsendungen von CIE (bccie-CSV)</translation>
    </message>
    <message>
        <source>The form submissions CIE (bccie) exports from an information collector: the first row names the form's fields, every further row is one submission with its Created date and ID. Choose the class the submissions become (testimonials, applications ...); its attributes are matched to the columns by identifier or name.</source>
        <translation>Die Formulareinsendungen, die CIE (bccie) aus einem Informationssammler exportiert: Die erste Zeile benennt die Felder des Formulars, jede weitere Zeile ist eine Einsendung mit ihrem Datum Created und ihrer ID. Wählen Sie die Klasse, zu der die Einsendungen werden (Referenzen, Bewerbungen ...); ihre Attribute werden den Spalten über Bezeichner oder Namen zugeordnet.</translation>
    </message>
    <message>
        <source>Three testimonials submitted through a form</source>
        <translation>Drei über ein Formular eingesandte Referenzen</translation>
    </message>
    <message>
        <source>RSS export of another Exponential site</source>
        <translation>RSS-Export einer anderen Exponential-Site</translation>
    </message>
    <message>
        <source>The RSS export of a subtree of another Exponential site (its address is rss/feed/&lt;name&gt;): every item is an article there, with title, link, summary, date and author. The preset brings them in as articles, so content moves from one installation to another and keeps following it at every run.</source>
        <translation>Der RSS-Export eines Teilbaums einer anderen Exponential-Site (seine Adresse ist rss/feed/&lt;name&gt;): Jeder Eintrag ist dort ein Artikel, mit Titel, Link, Zusammenfassung, Datum und Autor. Die Vorgabe übernimmt sie als Artikel, sodass Inhalte von einer Installation in eine andere wandern und ihr bei jedem Lauf weiter folgen.</translation>
    </message>
    <message>
        <source>Pages of a site by its sitemap</source>
        <translation>Seiten einer Site über ihre Sitemap</translation>
    </message>
    <message>
        <source>The public pages of a site, found by its sitemap.xml (or a sitemap index, or the Sitemap lines of its robots.txt): one record per page with address, last change and priority of the sitemap, title, description, canonical address, language and the fields picked by CSS or XPath selectors. Only public pages are read, and the terms of the site and its robots.txt apply: pages robots.txt does not allow are not read (each is a failed row of the run), requests are spaced by the delay or the Crawl-delay of the site, no cookies or credentials are sent and no JavaScript is run.</source>
        <translation>Die öffentlichen Seiten einer Site, gefunden über ihre sitemap.xml (oder einen Sitemap-Index oder die Sitemap-Zeilen ihrer robots.txt): ein Datensatz je Seite mit Adresse, letzter Änderung und Priorität aus der Sitemap, Titel, Beschreibung, kanonischer Adresse, Sprache und den Feldern, die CSS- oder XPath-Selektoren auswählen. Nur öffentliche Seiten werden gelesen, und es gelten die Bedingungen der Site und ihre robots.txt: Seiten, die robots.txt nicht erlaubt, werden nicht gelesen (jede ist eine fehlgeschlagene Zeile des Laufs), Anfragen werden um die Verzögerung oder das Crawl-delay der Site auseinandergezogen, es werden keine Cookies oder Zugangsdaten gesendet und kein JavaScript ausgeführt.</translation>
    </message>
    <message>
        <source>The snapshot of a crawl: three pages read, one refused by robots.txt</source>
        <translation>Der Abzug eines Crawls: drei Seiten gelesen, eine von robots.txt abgelehnt</translation>
    </message>
    <message>
        <source>A sitemap of four pages (its pages are listed, not read)</source>
        <translation>Eine Sitemap mit vier Seiten (ihre Seiten sind aufgelistet, nicht gelesen)</translation>
    </message>
    <message>
        <source>Web page scraper</source>
        <translation>Webseiten-Scraper</translation>
    </message>
    <message>
        <source>Fields picked by CSS or XPath selectors from public web pages: one record per page, or per repeating item (a product, an event of a list) with the picks relative to it, following a "next page" link if asked. Only public pages are read, and the terms of the site and its robots.txt apply: pages robots.txt does not allow are not read (each is a failed row of the run), requests are spaced by the delay or the Crawl-delay of the site, no cookies or credentials are sent and no JavaScript is run.</source>
        <translation>Felder, die CSS- oder XPath-Selektoren aus öffentlichen Webseiten auswählen: ein Datensatz je Seite oder je wiederkehrendem Element (ein Produkt, ein Termin einer Liste) mit den Auswahlen relativ dazu, auf Wunsch einem Link „nächste Seite“ folgend. Nur öffentliche Seiten werden gelesen, und es gelten die Bedingungen der Site und ihre robots.txt: Seiten, die robots.txt nicht erlaubt, werden nicht gelesen (jede ist eine fehlgeschlagene Zeile des Laufs), Anfragen werden um die Verzögerung oder das Crawl-delay der Site auseinandergezogen, es werden keine Cookies oder Zugangsdaten gesendet und kein JavaScript ausgeführt.</translation>
    </message>
    <message>
        <source>A product list of three items</source>
        <translation>Eine Produktliste mit drei Einträgen</translation>
    </message>
    <message>
        <source>Another Exponential site (REST API)</source>
        <translation>Eine andere Exponential-Site (REST-API)</translation>
    </message>
    <message>
        <source>Content of another Exponential site, read through its REST API: the children of a node (or a subtree to a depth), of one class or every class, with every attribute. The mapping is made from the first record (its class, every attribute by identifier) and matches the remote ID, so the copy follows the original at every run.</source>
        <translation>Inhalte einer anderen Exponential-Site, über ihre REST-API gelesen: die Kinder eines Knotens (oder ein Teilbaum bis zu einer Tiefe), einer Klasse oder aller Klassen, mit jedem Attribut. Die Zuordnung entsteht aus dem ersten Datensatz (seine Klasse, jedes Attribut über seinen Bezeichner) und gleicht die Remote-ID ab, sodass die Kopie bei jedem Lauf dem Original folgt.</translation>
    </message>
    <message>
        <source>Two articles of a site, as the source reads them (a snapshot)</source>
        <translation>Zwei Artikel einer Site, wie die Quelle sie liest (ein Abzug)</translation>
    </message>
    <message>
        <source>Exponential package (.ezpkg)</source>
        <translation>Exponential-Paket (.ezpkg)</translation>
    </message>
    <message>
        <source>A content package of an Exponential site (Setup, Packages, Export): every content object of the package is a record with its class, remote ID, name, language and its attributes by identifier. The mapping is made from the package (the class of its objects, every attribute by identifier, matched by the remote ID), so importing the package again updates the objects.</source>
        <translation>Ein Inhaltspaket einer Exponential-Site (Setup, Pakete, Export): Jedes Inhaltsobjekt des Pakets ist ein Datensatz mit seiner Klasse, Remote-ID, seinem Namen, seiner Sprache und seinen Attributen über ihren Bezeichner. Die Zuordnung entsteht aus dem Paket (die Klasse seiner Objekte, jedes Attribut über seinen Bezeichner, über die Remote-ID abgeglichen), sodass ein erneuter Import des Pakets die Objekte aktualisiert.</translation>
    </message>
    <message>
        <source>A folder and three articles with an image</source>
        <translation>Ein Ordner und drei Artikel mit einem Bild</translation>
    </message>
    <message>
        <source>Drupal (JSON:API)</source>
        <translation>Drupal (JSON:API)</translation>
    </message>
    <message>
        <source>Content of a Drupal site through its JSON:API (articles, pages, terms, any entity type and bundle). The source reads the site page by page and includes the related images, authors and tags, so a record has the title, body, summary, dates, path alias, image address and tag names as fields.</source>
        <translation>Inhalte einer Drupal-Site über ihre JSON:API (Artikel, Seiten, Begriffe, jeder Entitätstyp und jedes Bundle). Die Quelle liest die Site Seite für Seite und bindet die zugehörigen Bilder, Autoren und Tags ein, sodass ein Datensatz Titel, Textkörper, Zusammenfassung, Datumswerte, Pfad-Alias, Bildadresse und Tag-Namen als Felder hat.</translation>
    </message>
    <message>
        <source>A JSON:API page of two articles with their images, authors and tags</source>
        <translation>Eine JSON:API-Seite mit zwei Artikeln mit ihren Bildern, Autoren und Tags</translation>
    </message>
    <message>
        <source>Joomla articles (CSV or XML export)</source>
        <translation>Joomla-Artikel (CSV- oder XML-Export)</translation>
    </message>
    <message>
        <source>Articles of a Joomla site, exported as CSV (the columns of the articles table) or as XML (J2XML or an export with one &lt;article&gt; per article). Intro and full text are joined into the body, the images JSON gives the intro and full text image, and categories are named by their title.</source>
        <translation>Artikel einer Joomla-Site, als CSV (die Spalten der Artikeltabelle) oder als XML exportiert (J2XML oder ein Export mit einem &lt;article&gt; je Artikel). Einleitung und Volltext werden zum Textkörper verbunden, das JSON images liefert das Bild der Einleitung und des Volltexts, und Kategorien werden über ihren Titel benannt.</translation>
    </message>
    <message>
        <source>Three articles as CSV</source>
        <translation>Drei Artikel als CSV</translation>
    </message>
    <message>
        <source>The same articles as J2XML with their categories</source>
        <translation>Dieselben Artikel als J2XML mit ihren Kategorien</translation>
    </message>
    <message>
        <source>Airtable table</source>
        <translation>Airtable-Tabelle</translation>
    </message>
    <message>
        <source>The records of an Airtable table (or of one of its views, or those a formula selects) through the Airtable Web API: one record is one row, its fields the table's fields by name; attachments come as addresses the mapping can download, linked records as IDs. Read-only; the personal access token is the name of a secret in the settings. The table may contain personal data (collaborators' names and e-mail addresses).</source>
        <translation>Die Datensätze einer Airtable-Tabelle (oder einer ihrer Ansichten, oder die, die eine Formel auswählt) über die Airtable Web API: Ein Datensatz ist eine Zeile, seine Felder sind die Felder der Tabelle über ihren Namen; Anhänge kommen als Adressen, die die Zuordnung herunterladen kann, verknüpfte Datensätze als IDs. Nur lesend; das persönliche Zugriffstoken ist der Name eines Geheimnisses in den Einstellungen. Die Tabelle kann personenbezogene Daten enthalten (Namen und E-Mail-Adressen von Mitarbeitenden).</translation>
    </message>
    <message>
        <source>A snapshot of three records of a table "Events" (what a run writes): text, a date, a single select, attachments, linked records and a collaborator</source>
        <translation>Ein Abzug von drei Datensätzen einer Tabelle „Events“ (was ein Lauf schreibt): Text, ein Datum, eine Einfachauswahl, Anhänge, verknüpfte Datensätze und eine mitarbeitende Person</translation>
    </message>
    <message>
        <source>The published events of the view "Website" of the base appExampleBase01, the token as the secret airtable_token</source>
        <translation>Die veröffentlichten Termine der Ansicht „Website“ der Base appExampleBase01, das Token als Geheimnis airtable_token</translation>
    </message>
    <message>
        <source>Notion database</source>
        <translation>Notion-Datenbank</translation>
    </message>
    <message>
        <source>The pages of a Notion database through the Notion API: one record is one page, its fields the properties of the database as plain values (text, numbers, dates in ISO 8601, select options, people's names, file addresses, related page IDs), optionally with the content of the page as Markdown or HTML. Read-only; the integration token is the name of a secret in the settings. People are personal data.</source>
        <translation>Die Seiten einer Notion-Datenbank über die Notion-API: Ein Datensatz ist eine Seite, seine Felder sind die Eigenschaften der Datenbank als einfache Werte (Text, Zahlen, Datumswerte in ISO 8601, Auswahloptionen, Namen von Personen, Dateiadressen, IDs verknüpfter Seiten), auf Wunsch mit dem Inhalt der Seite als Markdown oder HTML. Nur lesend; das Integrationstoken ist der Name eines Geheimnisses in den Einstellungen. Personen sind personenbezogene Daten.</translation>
    </message>
    <message>
        <source>A snapshot of two pages of a database "Articles" (what a run writes): title, rich text, a date, select options, people, files, a relation and the content as Markdown</source>
        <translation>Ein Abzug von zwei Seiten einer Datenbank „Articles“ (was ein Lauf schreibt): Titel, formatierter Text, ein Datum, Auswahloptionen, Personen, Dateien, eine Relation und der Inhalt als Markdown</translation>
    </message>
    <message>
        <source>The published articles of a database, newest first, with their content as HTML, the token as the secret notion_token</source>
        <translation>Die veröffentlichten Artikel einer Datenbank, die neuesten zuerst, mit ihrem Inhalt als HTML, das Token als Geheimnis notion_token</translation>
    </message>
    <message>
        <source>Supabase or PostgREST table</source>
        <translation>Tabelle von Supabase oder PostgREST</translation>
    </message>
    <message>
        <source>The rows of a table or view of a Supabase project (or of any PostgREST server) through its REST API: one record is one row, embedded resources as dotted fields (author.name). Select, filters (eq, gt, in, like ... also in and/or) and order are checked before they are sent; only GET requests. The key is the name of a secret; the anon key with a read-only role and row level security is the right one.</source>
        <translation>Die Zeilen einer Tabelle oder Sicht eines Supabase-Projekts (oder eines beliebigen PostgREST-Servers) über seine REST-API: Ein Datensatz ist eine Zeile, eingebettete Ressourcen werden zu Feldern mit Punkt (author.name). Select, Filter (eq, gt, in, like ... auch in and/or) und Sortierung werden geprüft, bevor sie gesendet werden; nur GET-Anfragen. Der Schlüssel ist der Name eines Geheimnisses; der richtige ist der anon-Schlüssel mit einer Nur-Lese-Rolle und Row Level Security.</translation>
    </message>
    <message>
        <source>A snapshot of three rows of a table "articles" with the embedded author (what a run writes)</source>
        <translation>Ein Abzug von drei Zeilen einer Tabelle „articles“ mit dem eingebetteten Autor (was ein Lauf schreibt)</translation>
    </message>
    <message>
        <source>The published articles with their author, newest first, the anon key as the secret supabase_anon_key</source>
        <translation>Die veröffentlichten Artikel mit ihrem Autor, die neuesten zuerst, der anon-Schlüssel als Geheimnis supabase_anon_key</translation>
    </message>
    <message>
        <source>A self-hosted PostgREST server (listed in [SupabaseSourceSettings] AllowedHosts[]), the view products_public in the schema shop, paged with limit and offset</source>
        <translation>Ein selbst betriebener PostgREST-Server (in [SupabaseSourceSettings] AllowedHosts[] aufgeführt), die Sicht products_public im Schema shop, seitenweise mit limit und offset</translation>
    </message>
    <message>
        <source>Baserow table</source>
        <translation>Baserow-Tabelle</translation>
    </message>
    <message>
        <source>The rows of a Baserow table (baserow.io or self-hosted, also of one of its views) through the REST API: one record is one row with the fields by name; link rows as values and IDs, files as addresses the mapping can download, selects as their values. Read-only; the database token is the name of a secret. The table may contain personal data.</source>
        <translation>Die Zeilen einer Baserow-Tabelle (baserow.io oder selbst betrieben, auch einer ihrer Ansichten) über die REST-API: Ein Datensatz ist eine Zeile mit den Feldern über ihren Namen; Verknüpfungszeilen als Werte und IDs, Dateien als Adressen, die die Zuordnung herunterladen kann, Auswahlfelder als ihre Werte. Nur lesend; das Datenbank-Token ist der Name eines Geheimnisses. Die Tabelle kann personenbezogene Daten enthalten.</translation>
    </message>
    <message>
        <source>A snapshot of three rows of a table "Events" (what a run writes): a date, a single and a multiple select, a file and link rows</source>
        <translation>Ein Abzug von drei Zeilen einer Tabelle „Events“ (was ein Lauf schreibt): ein Datum, eine Einfach- und eine Mehrfachauswahl, eine Datei und Verknüpfungszeilen</translation>
    </message>
    <message>
        <source>The published events of the view 815 of the table 4711 on baserow.io, newest first, the token as the secret baserow_token</source>
        <translation>Die veröffentlichten Termine der Ansicht 815 der Tabelle 4711 auf baserow.io, die neuesten zuerst, das Token als Geheimnis baserow_token</translation>
    </message>
    <message>
        <source>A self-hosted Baserow (listed in [BaserowSourceSettings] AllowedHosts[]): the rows whose name contains "tea"</source>
        <translation>Ein selbst betriebenes Baserow (in [BaserowSourceSettings] AllowedHosts[] aufgeführt): die Zeilen, deren Name „tea“ enthält</translation>
    </message>
    <message>
        <source>Salesforce SOQL query</source>
        <translation>Salesforce-SOQL-Abfrage</translation>
    </message>
    <message>
        <source>The records of a SOQL query through the Salesforce REST API (a single SELECT; also the deleted records with queryAll): relationship fields as dotted names (Account.Name), addresses flattened. Signs in with a connected app by the JWT bearer flow or a refresh token; keys and tokens are names of secrets. Contacts and leads are personal data.</source>
        <translation>Die Datensätze einer SOQL-Abfrage über die Salesforce-REST-API (ein einzelnes SELECT; mit queryAll auch die gelöschten Datensätze): Beziehungsfelder als Namen mit Punkt (Account.Name), Adressen abgeflacht. Meldet sich mit einer Connected App über den JWT-Bearer-Flow oder ein Refresh-Token an; Schlüssel und Token sind Namen von Geheimnissen. Kontakte und Leads sind personenbezogene Daten.</translation>
    </message>
    <message>
        <source>A snapshot of three accounts with their owner and billing address (what a run writes)</source>
        <translation>Ein Abzug von drei Accounts mit ihrem Eigentümer und ihrer Rechnungsadresse (was ein Lauf schreibt)</translation>
    </message>
    <message>
        <source>The customer accounts changed this year with their owner, JWT bearer flow, the private key as the secret salesforce_jwt_key</source>
        <translation>Die in diesem Jahr geänderten Kunden-Accounts mit ihrem Eigentümer, JWT-Bearer-Flow, der private Schlüssel als Geheimnis salesforce_jwt_key</translation>
    </message>
    <message>
        <source>The products of a sandbox, also the deleted ones (queryAll), refresh token flow</source>
        <translation>Die Produkte einer Sandbox, auch die gelöschten (queryAll), Refresh-Token-Flow</translation>
    </message>
    <message>
        <source>HubSpot CRM objects</source>
        <translation>HubSpot-CRM-Objekte</translation>
    </message>
    <message>
        <source>The objects of a HubSpot CRM type (contacts, companies, deals, tickets, products, line items or a custom object) through the CRM API v3: listed, or found by a search with filter groups; the asked properties by name and the IDs of associated objects. Read-only; the private app token is the name of a secret. Contacts hold personal data.</source>
        <translation>Die Objekte eines HubSpot-CRM-Typs (Kontakte, Unternehmen, Deals, Tickets, Produkte, Positionen oder ein eigenes Objekt) über die CRM-API v3: aufgelistet oder über eine Suche mit Filtergruppen gefunden; die angefragten Eigenschaften über ihren Namen und die IDs verknüpfter Objekte. Nur lesend; das Token der privaten App ist der Name eines Geheimnisses. Kontakte enthalten personenbezogene Daten.</translation>
    </message>
    <message>
        <source>A snapshot of three companies with their associated contacts (what a run writes)</source>
        <translation>Ein Abzug von drei Unternehmen mit ihren verknüpften Kontakten (was ein Lauf schreibt)</translation>
    </message>
    <message>
        <source>The companies with their domain, industry and city, and the IDs of their contacts, the token as the secret hubspot_token</source>
        <translation>Die Unternehmen mit Domain, Branche und Stadt und den IDs ihrer Kontakte, das Token als Geheimnis hubspot_token</translation>
    </message>
    <message>
        <source>A search: the deals won since 2026, the biggest first</source>
        <translation>Eine Suche: die seit 2026 gewonnenen Deals, die größten zuerst</translation>
    </message>
    <message>
        <source>YouTube channel, playlist or search (Data API)</source>
        <translation>YouTube-Kanal, -Playlist oder -Suche (Data API)</translation>
    </message>
    <message>
        <source>The videos of a YouTube channel (by its ID or @handle), of a playlist or of a search through the YouTube Data API v3: one record is one video with title, description, tags, duration, the largest thumbnail, view, like and comment counts, privacy and the addresses to watch and to embed. Read-only (GET only); the API key is the name of a secret in the settings. A search costs 100 quota units per page of the 10,000 a day a project has.</source>
        <translation>Die Videos eines YouTube-Kanals (über seine ID oder seinen @Handle), einer Playlist oder einer Suche über die YouTube Data API v3: Ein Datensatz ist ein Video mit Titel, Beschreibung, Tags, Dauer, dem größten Vorschaubild, den Zahlen der Aufrufe, Likes und Kommentare, der Sichtbarkeit und den Adressen zum Ansehen und Einbetten. Nur lesend (nur GET); der API-Schlüssel ist der Name eines Geheimnisses in den Einstellungen. Eine Suche kostet je Seite 100 der 10.000 Kontingenteinheiten, die ein Projekt am Tag hat.</translation>
    </message>
    <message>
        <source>A snapshot of three videos of a channel (what a run writes): durations, thumbnails, statistics and tags</source>
        <translation>Ein Abzug von drei Videos eines Kanals (was ein Lauf schreibt): Dauer, Vorschaubilder, Statistiken und Tags</translation>
    </message>
    <message>
        <source>The uploads of the channel @examplechannel, the API key as the secret youtube_api_key</source>
        <translation>Die Uploads des Kanals @examplechannel, der API-Schlüssel als Geheimnis youtube_api_key</translation>
    </message>
    <message>
        <source>The videos about tea of a channel published in 2026, newest first (100 quota units per page)</source>
        <translation>Die 2026 veröffentlichten Videos eines Kanals über Tee, die neuesten zuerst (100 Kontingenteinheiten je Seite)</translation>
    </message>
    <message>
        <source>YouTube channel or playlist page (scraper, opt-in)</source>
        <translation>Seite eines YouTube-Kanals oder einer Playlist (Scraper, nur nach Freischaltung)</translation>
    </message>
    <message>
        <source>The videos listed on a youtube.com channel page (/videos) or playlist page, read from the page itself without an API key: ID, title, duration, thumbnail, view count and the addresses to watch and to embed. Only the first batch of videos of a page (about 30). Off unless sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled. Reading youtube.com pages is against YouTube's Terms of Service and may break whenever YouTube changes its pages. Use it only where you may, and prefer the YouTube Data API (youtube) or the channel feed (video_feed).</source>
        <translation>Die Videos, die eine Kanalseite (/videos) oder Playlist-Seite von youtube.com auflistet, aus der Seite selbst ohne API-Schlüssel gelesen: ID, Titel, Dauer, Vorschaubild, Zahl der Aufrufe und die Adressen zum Ansehen und Einbetten. Nur der erste Schwung Videos einer Seite (etwa 30). Ausgeschaltet, außer mit sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled. Das Lesen von Seiten von youtube.com verstößt gegen die Nutzungsbedingungen von YouTube und kann jederzeit nicht mehr funktionieren, wenn YouTube seine Seiten ändert. Verwenden Sie es nur, wo Sie es dürfen, und ziehen Sie die YouTube Data API (youtube) oder den Kanal-Feed (video_feed) vor.</translation>
    </message>
    <message>
        <source>Reading youtube.com pages is against YouTube's Terms of Service and may break whenever YouTube changes its pages. Use it only where you may, and prefer the YouTube Data API (youtube) or the channel feed (video_feed).</source>
        <translation>Das Lesen von Seiten von youtube.com verstößt gegen die Nutzungsbedingungen von YouTube und kann jederzeit nicht mehr funktionieren, wenn YouTube seine Seiten ändert. Verwenden Sie es nur, wo Sie es dürfen, und ziehen Sie die YouTube Data API (youtube) oder den Kanal-Feed (video_feed) vor.</translation>
    </message>
    <message>
        <source>Switched off: reading youtube.com pages is against YouTube's Terms of Service. An administrator may switch it on with sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled.</source>
        <translation>Ausgeschaltet: Das Lesen von Seiten von youtube.com verstößt gegen die Nutzungsbedingungen von YouTube. Ein Administrator kann es mit sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled einschalten.</translation>
    </message>
    <message>
        <source>A snapshot of three videos (what a run writes; the scraper gives fewer fields than the API)</source>
        <translation>Ein Abzug von drei Videos (was ein Lauf schreibt; der Scraper liefert weniger Felder als die API)</translation>
    </message>
    <message>
        <source>The videos on the page of the channel @examplechannel</source>
        <translation>Die Videos auf der Seite des Kanals @examplechannel</translation>
    </message>
    <message>
        <source>REST API (JSON, paged)</source>
        <translation>REST-API (JSON, seitenweise)</translation>
    </message>
    <message>
        <source>The records of any JSON REST API, read page by page: page number, offset and limit, a cursor, the next address of the answer or the Link header (same site only). Authentication by bearer token, basic, an API key in a header or a parameter, or OAuth 2.0 client credentials, every secret by its name. GET only (POST only with a JSON body for an endpoint that reads); waits on 429 and 503 as the server says.</source>
        <translation>Die Datensätze einer beliebigen JSON-REST-API, Seite für Seite gelesen: Seitenzahl, Offset und Limit, ein Cursor, die nächste Adresse aus der Antwort oder der Link-Header (nur dieselbe Site). Authentifizierung per Bearer-Token, Basic, einem API-Schlüssel in einem Header oder Parameter oder OAuth-2.0-Client-Credentials, jedes Geheimnis über seinen Namen. Nur GET (POST nur mit JSON-Inhalt für einen lesenden Endpunkt); wartet bei 429 und 503 so, wie der Server es sagt.</translation>
    </message>
    <message>
        <source>A snapshot of three records of a paged API (what a run writes): nested objects as dotted fields, tags as a list</source>
        <translation>Ein Abzug von drei Datensätzen einer seitenweisen API (was ein Lauf schreibt): verschachtelte Objekte als Felder mit Punkt, Tags als Liste</translation>
    </message>
    <message>
        <source>Products of an API paged by page number, the bearer token as the secret shop_api_token</source>
        <translation>Produkte einer nach Seitenzahl geblätterten API, das Bearer-Token als Geheimnis shop_api_token</translation>
    </message>
    <message>
        <source>Events of an API with a cursor in the answer (meta.next_cursor) and an API key in the header X-Api-Key</source>
        <translation>Termine einer API mit einem Cursor in der Antwort (meta.next_cursor) und einem API-Schlüssel im Header X-Api-Key</translation>
    </message>
    <message>
        <source>Articles of an API with OAuth 2.0 client credentials, paged by the Link header (rel="next")</source>
        <translation>Artikel einer API mit OAuth-2.0-Client-Credentials, über den Link-Header geblättert (rel="next")</translation>
    </message>
    <message>
        <source>Media files (folder as ZIP) with EXIF and IPTC</source>
        <translation>Mediendateien (Ordner als ZIP) mit EXIF und IPTC</translation>
    </message>
    <message>
        <source>Photos, documents, audio and video, one file or a folder as ZIP. Every file is a record with its folder, size, content hash and what its EXIF and IPTC metadata say: title, caption, keywords, photographer, copyright, date taken, camera and GPS position. The file itself is imported into an image or file attribute.</source>
        <translation>Fotos, Dokumente, Audio und Video, eine Datei oder ein Ordner als ZIP. Jede Datei ist ein Datensatz mit ihrem Ordner, ihrer Größe, dem Hash ihres Inhalts und dem, was ihre EXIF- und IPTC-Metadaten sagen: Titel, Bildunterschrift, Schlagwörter, Fotograf, Copyright, Aufnahmedatum, Kamera und GPS-Position. Die Datei selbst wird in ein Bild- oder Dateiattribut importiert.</translation>
    </message>
    <message>
        <source>Three photos with EXIF and IPTC and a text file, in folders, as ZIP</source>
        <translation>Drei Fotos mit EXIF und IPTC und eine Textdatei, in Ordnern, als ZIP</translation>
    </message>
    <message>
        <source>One photo with EXIF and IPTC</source>
        <translation>Ein Foto mit EXIF und IPTC</translation>
    </message>
    <message>
        <source>iCalendar (.ics)</source>
        <translation>iCalendar (.ics)</translation>
    </message>
    <message>
        <source>The calendar format of calendar apps, booking systems and event sites, from a file or a subscription address. Every event is a record with title, description, place, start and end (as written, in ISO 8601 UTC and as a timestamp, time zones and all-day dates included), recurrence rule, categories and organizer. Tasks can be read too.</source>
        <translation>Das Kalenderformat von Kalender-Apps, Buchungssystemen und Veranstaltungsseiten, aus einer Datei oder von einer Abonnement-Adresse. Jeder Termin ist ein Datensatz mit Titel, Beschreibung, Ort, Beginn und Ende (wie geschrieben, in ISO 8601 UTC und als Zeitstempel, Zeitzonen und ganztägige Daten eingeschlossen), Wiederholungsregel, Kategorien und Veranstalter. Aufgaben lassen sich ebenfalls lesen.</translation>
    </message>
    <message>
        <source>A calendar of four events: a series, an all-day event, one in UTC and a changed occurrence</source>
        <translation>Ein Kalender mit vier Terminen: eine Serie, ein ganztägiger Termin, einer in UTC und ein geändertes Vorkommen</translation>
    </message>
    <message>
        <source>vCard contacts (.vcf)</source>
        <translation>vCard-Kontakte (.vcf)</translation>
    </message>
    <message>
        <source>The contact format of address books, phones and mail programs (vCard 3.0 and 4.0). Every card is a record with name and its parts, e-mail addresses, phone numbers, organization, title, address, web address, note, birthday and photo address. There is no preset mapping: map the cards to a contact class of your site.</source>
        <translation>Das Kontaktformat von Adressbüchern, Telefonen und Mailprogrammen (vCard 3.0 und 4.0). Jede Karte ist ein Datensatz mit dem Namen und seinen Teilen, E-Mail-Adressen, Telefonnummern, Organisation, Titel, Adresse, Webadresse, Notiz, Geburtstag und Fotoadresse. Es gibt keine vorgegebene Zuordnung: Ordnen Sie die Karten einer Kontaktklasse Ihrer Site zu.</translation>
    </message>
    <message>
        <source>Three fictitious contacts in vCard 4.0 and 3.0</source>
        <translation>Drei erfundene Kontakte in vCard 4.0 und 3.0</translation>
    </message>
    <message>
        <source>Google Merchant product feed</source>
        <translation>Produkt-Feed für Google Merchant</translation>
    </message>
    <message>
        <source>The product feed shops make for Google Merchant Center and price comparison sites (RSS 2.0 with the g: namespace). Every item is a product with ID, title, description, link, images, price and sale price with currency, availability, brand, GTIN, MPN, categories, shipping and tax.</source>
        <translation>Der Produkt-Feed, den Shops für das Google Merchant Center und Preisvergleichsseiten erstellen (RSS 2.0 mit dem Namensraum g:). Jeder Eintrag ist ein Produkt mit ID, Titel, Beschreibung, Link, Bildern, Preis und Angebotspreis mit Währung, Verfügbarkeit, Marke, GTIN, MPN, Kategorien, Versand und Steuer.</translation>
    </message>
    <message>
        <source>A feed of three products with prices, a sale price, shipping and tax</source>
        <translation>Ein Feed mit drei Produkten mit Preisen, einem Angebotspreis, Versand und Steuer</translation>
    </message>
    <message>
        <source>Shopify product CSV</source>
        <translation>Shopify-Produkt-CSV</translation>
    </message>
    <message>
        <source>The product export of a Shopify store. The rows of one handle become one product with title, description, vendor, type, tags, its variants (SKU, price, compare-at price, options) and its images.</source>
        <translation>Der Produktexport eines Shopify-Shops. Die Zeilen eines Handles werden zu einem Produkt mit Titel, Beschreibung, Anbieter, Typ, Tags, seinen Varianten (SKU, Preis, Vergleichspreis, Optionen) und seinen Bildern.</translation>
    </message>
    <message>
        <source>Two products, one with three variants and two images</source>
        <translation>Zwei Produkte, eines mit drei Varianten und zwei Bildern</translation>
    </message>
    <message>
        <source>WooCommerce product CSV</source>
        <translation>WooCommerce-Produkt-CSV</translation>
    </message>
    <message>
        <source>The product export of a WooCommerce shop. Every product is a record with SKU, name, short and full description, regular and sale price, tax status and class, stock, categories, tags, images and attributes; the variations of a variable product become its list of variants.</source>
        <translation>Der Produktexport eines WooCommerce-Shops. Jedes Produkt ist ein Datensatz mit SKU, Name, Kurz- und Langbeschreibung, regulärem Preis und Angebotspreis, Steuerstatus und Steuerklasse, Lagerbestand, Kategorien, Tags, Bildern und Attributen; die Varianten eines variablen Produkts werden zu seiner Liste von Varianten.</translation>
    </message>
    <message>
        <source>Three products: a simple one, a variable one with two variations and one on sale</source>
        <translation>Drei Produkte: ein einfaches, ein variables mit zwei Varianten und eines im Angebot</translation>
    </message>
    <message>
        <source>Mapping to a content class (no code)</source>
        <translation>Zuordnung zu einer Inhaltsklasse (ohne Code)</translation>
    </message>
    <message>
        <source>Rows of a data table (a persistent class)</source>
        <translation>Zeilen einer Datentabelle (eine persistente Klasse)</translation>
    </message>
    <message>
        <source>Handler of sqliimport 1.x</source>
        <translation>Handler von sqliimport 1.x</translation>
    </message>
    <message>
        <source>Undo of a run</source>
        <translation>Rückgängigmachen eines Laufs</translation>
    </message>
    <message>
        <source>Removes spaces at both ends</source>
        <translation>Entfernt Leerzeichen an beiden Enden</translation>
    </message>
    <message>
        <source>Lower case</source>
        <translation>Kleinbuchstaben</translation>
    </message>
    <message>
        <source>Upper case</source>
        <translation>Großbuchstaben</translation>
    </message>
    <message>
        <source>Upper case first letter of each word</source>
        <translation>Erster Buchstabe jedes Worts groß</translation>
    </message>
    <message>
        <source>Removes HTML tags and decodes entities</source>
        <translation>Entfernt HTML-Tags und dekodiert Entitäten</translation>
    </message>
    <message>
        <source>Puts a text before the value</source>
        <translation>Setzt einen Text vor den Wert</translation>
    </message>
    <message>
        <source>Puts a text after the value</source>
        <translation>Setzt einen Text hinter den Wert</translation>
    </message>
    <message>
        <source>Replaces a text</source>
        <translation>Ersetzt einen Text</translation>
    </message>
    <message>
        <source>Replaces what a regular expression matches</source>
        <translation>Ersetzt, was ein regulärer Ausdruck findet</translation>
    </message>
    <message>
        <source>Cuts the value to a length</source>
        <translation>Kürzt den Wert auf eine Länge</translation>
    </message>
    <message>
        <source>A value when the source is empty</source>
        <translation>Ein Wert, wenn die Quelle leer ist</translation>
    </message>
    <message>
        <source>Replaces a value by another from a table</source>
        <translation>Ersetzt einen Wert durch einen anderen aus einer Tabelle</translation>
    </message>
    <message>
        <source>Splits a text into a list</source>
        <translation>Teilt einen Text in eine Liste</translation>
    </message>
    <message>
        <source>Joins a list into a text</source>
        <translation>Verbindet eine Liste zu einem Text</translation>
    </message>
    <message>
        <source>The first item of a list</source>
        <translation>Das erste Element einer Liste</translation>
    </message>
    <message>
        <source>Joins the value with other fields of the record</source>
        <translation>Verbindet den Wert mit anderen Feldern des Datensatzes</translation>
    </message>
    <message>
        <source>Builds a text from fields of the record: "{title} ({year})"</source>
        <translation>Baut einen Text aus Feldern des Datensatzes: „{title} ({year})“</translation>
    </message>
    <message>
        <source>A number (decimal comma or point)</source>
        <translation>Eine Zahl (Dezimalkomma oder -punkt)</translation>
    </message>
    <message>
        <source>A whole number</source>
        <translation>Eine ganze Zahl</translation>
    </message>
    <message>
        <source>Yes or no as 1 or 0</source>
        <translation>Ja oder nein als 1 oder 0</translation>
    </message>
    <message>
        <source>A date or time as a timestamp (ezdate, ezdatetime)</source>
        <translation>Ein Datum oder eine Uhrzeit als Zeitstempel (ezdate, ezdatetime)</translation>
    </message>
    <message>
        <source>A date as a text in a format</source>
        <translation>Ein Datum als Text in einem Format</translation>
    </message>
    <message>
        <source>A URL-friendly identifier</source>
        <translation>Ein URL-freundlicher Bezeichner</translation>
    </message>
    <message>
        <source>HTML to the rich text (ezxmltext) of the attribute</source>
        <translation>HTML in den formatierten Text (ezxmltext) des Attributs</translation>
    </message>
    <message>
        <source>The ID of the object with this remote ID (object relations)</source>
        <translation>Die ID des Objekts mit dieser Remote-ID (Objektbeziehungen)</translation>
    </message>
    <message>
        <source>Downloads an image from a URL (ezimage)</source>
        <translation>Lädt ein Bild von einer URL herunter (ezimage)</translation>
    </message>
    <message>
        <source>Downloads a file from a URL (ezbinaryfile, ezmedia)</source>
        <translation>Lädt eine Datei von einer URL herunter (ezbinaryfile, ezmedia)</translation>
    </message>
    <message>
        <source>An image or file object for a URL, made once per file (object relations)</source>
        <translation>Ein Bild- oder Dateiobjekt für eine URL, einmal je Datei angelegt (Objektbeziehungen)</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/edit</name>
    <message>
        <source>Source</source>
        <translation>Quelle</translation>
    </message>
    <message>
        <source>Processing</source>
        <translation>Verarbeitung</translation>
    </message>
    <message>
        <source>Mapping</source>
        <translation>Zuordnung</translation>
    </message>
    <message>
        <source>Preview</source>
        <translation>Vorschau</translation>
    </message>
    <message>
        <source>Schedule</source>
        <translation>Zeitplan</translation>
    </message>
    <message>
        <source>Review and save</source>
        <translation>Prüfen und speichern</translation>
    </message>
    <message>
        <source>Edit import: %name</source>
        <translation>Import bearbeiten: %name</translation>
    </message>
    <message>
        <source>Steps of the import editor</source>
        <translation>Schritte des Import-Editors</translation>
    </message>
    <message>
        <source>Please check these points:</source>
        <translation>Bitte prüfen Sie diese Punkte:</translation>
    </message>
    <message>
        <source>What you entered is kept; a file that was uploaded stays uploaded.</source>
        <translation>Ihre Eingaben bleiben erhalten; eine hochgeladene Datei bleibt hochgeladen.</translation>
    </message>
    <message>
        <source>Save</source>
        <translation>Speichern</translation>
    </message>
    <message>
        <source>Save and run</source>
        <translation>Speichern und ausführen</translation>
    </message>
    <message>
        <source>Save and continue</source>
        <translation>Speichern und weiter</translation>
    </message>
    <message>
        <source>Back to the imports</source>
        <translation>Zurück zu den Importen</translation>
    </message>
    <message>
        <source>The sample of the source could not be read: %error</source>
        <translation>Die Stichprobe der Quelle konnte nicht gelesen werden: %error</translation>
    </message>
    <message>
        <source>Content class</source>
        <translation>Inhaltsklasse</translation>
    </message>
    <message>
        <source>Choose a class</source>
        <translation>Wählen Sie eine Klasse</translation>
    </message>
    <message>
        <source>After a new class is chosen, save to see its attributes.</source>
        <translation>Speichern Sie nach der Wahl einer neuen Klasse, um ihre Attribute zu sehen.</translation>
    </message>
    <message>
        <source>Suggest a class with AI</source>
        <translation>Eine Klasse mit KI vorschlagen</translation>
    </message>
    <message>
        <source>Sends the field names and types and masked sample values.</source>
        <translation>Sendet die Namen und Typen der Felder und maskierte Beispielwerte.</translation>
    </message>
    <message>
        <source>Sends only the field names and types, no values.</source>
        <translation>Sendet nur die Namen und Typen der Felder, keine Werte.</translation>
    </message>
    <message>
        <source>Classes the AI suggests</source>
        <translation>Klassen, die die KI vorschlägt</translation>
    </message>
    <message>
        <source>Confidence of the AI</source>
        <translation>Sicherheit der KI</translation>
    </message>
    <message>
        <source>In the form</source>
        <translation>Im Formular</translation>
    </message>
    <message>
        <source>Use this class</source>
        <translation>Diese Klasse verwenden</translation>
    </message>
    <message>
        <source>Records choose their class by the field %field:</source>
        <translation>Datensätze wählen ihre Klasse über das Feld %field:</translation>
    </message>
    <message>
        <source>not imported</source>
        <translation>nicht importiert</translation>
    </message>
    <message>
        <source>%n lines</source>
        <translation>%n Zeilen</translation>
    </message>
    <message>
        <source>Every other record uses the class and the lines below. Change the variants with a mapping file (download, edit, load).</source>
        <translation>Jeder andere Datensatz verwendet die Klasse und die Zeilen unten. Ändern Sie die Varianten mit einer Zuordnungsdatei (herunterladen, bearbeiten, laden).</translation>
    </message>
    <message>
        <source>Remove the class per record when saving</source>
        <translation>Die Klasse je Datensatz beim Speichern entfernen</translation>
    </message>
    <message>
        <source>Language of the fields</source>
        <translation>Sprache der Felder</translation>
    </message>
    <message>
        <source>The language of the import</source>
        <translation>Die Sprache des Imports</translation>
    </message>
    <message>
        <source>How a record finds its object</source>
        <translation>Wie ein Datensatz sein Objekt findet</translation>
    </message>
    <message>
        <source>By a remote ID made from the record</source>
        <translation>Über eine Remote-ID aus dem Datensatz</translation>
    </message>
    <message>
        <source>By the value of an attribute</source>
        <translation>Über den Wert eines Attributs</translation>
    </message>
    <message>
        <source>Remote ID pattern</source>
        <translation>Muster der Remote-ID</translation>
    </message>
    <message>
        <source>Fields of the record in braces, for example:</source>
        <translation>Felder des Datensatzes in geschweiften Klammern, zum Beispiel:</translation>
    </message>
    <message>
        <source>Attribute and source field to match on</source>
        <translation>Attribut und Quellfeld für den Abgleich</translation>
    </message>
    <message>
        <source>Attribute</source>
        <translation>Attribut</translation>
    </message>
    <message>
        <source>Source field</source>
        <translation>Quellfeld</translation>
    </message>
    <message>
        <source>Parent node of new objects</source>
        <translation>Elternknoten neuer Objekte</translation>
    </message>
    <message>
        <source>Parent from a field (its remote ID)</source>
        <translation>Elternknoten aus einem Feld (seine Remote-ID)</translation>
    </message>
    <message>
        <source>Field</source>
        <translation>Feld</translation>
    </message>
    <message>
        <source>prefix</source>
        <translation>Präfix</translation>
    </message>
    <message>
        <source>Prefix of the remote ID</source>
        <translation>Präfix der Remote-ID</translation>
    </message>
    <message>
        <source>More locations from a field</source>
        <translation>Weitere Platzierungen aus einem Feld</translation>
    </message>
    <message>
        <source>Attributes</source>
        <translation>Attribute</translation>
    </message>
    <message>
        <source>Auto-match by name</source>
        <translation>Automatisch nach Namen zuordnen</translation>
    </message>
    <message>
        <source>Suggest with AI</source>
        <translation>Mit KI vorschlagen</translation>
    </message>
    <message>
        <source>Accept all</source>
        <translation>Alle übernehmen</translation>
    </message>
    <message>
        <source>The AI gets the attributes of the class, the field names and types and masked sample values (the option of the processing step).</source>
        <translation>Die KI erhält die Attribute der Klasse, die Namen und Typen der Felder und maskierte Beispielwerte (die Option des Schritts Verarbeitung).</translation>
    </message>
    <message>
        <source>The AI gets the attributes of the class and only the field names and types, no values.</source>
        <translation>Die KI erhält die Attribute der Klasse und nur die Namen und Typen der Felder, keine Werte.</translation>
    </message>
    <message>
        <source>Suggestions are only filled into the form when you accept them; save to keep them. A dry run is needed before the next real run.</source>
        <translation>Vorschläge kommen erst in das Formular, wenn Sie sie übernehmen; speichern Sie, um sie zu behalten. Vor dem nächsten echten Lauf ist ein Probelauf nötig.</translation>
    </message>
    <message>
        <source>%n suggestions of the AI</source>
        <translation>%n Vorschläge der KI</translation>
    </message>
    <message>
        <source>%n left out (an attribute, field or transform that does not exist)</source>
        <translation>%n weggelassen (ein Attribut, Feld oder eine Umwandlung, die es nicht gibt)</translation>
    </message>
    <message>
        <source>Transforms</source>
        <translation>Umwandlungen</translation>
    </message>
    <message>
        <source>Default</source>
        <translation>Vorgabewert</translation>
    </message>
    <message>
        <source>Required</source>
        <translation>Pflicht</translation>
    </message>
    <message>
        <source>AI suggestion</source>
        <translation>Vorschlag der KI</translation>
    </message>
    <message>
        <source>required by the class</source>
        <translation>von der Klasse verlangt</translation>
    </message>
    <message>
        <source>Source field of %a</source>
        <translation>Quellfeld von %a</translation>
    </message>
    <message>
        <source>Transforms of %a</source>
        <translation>Umwandlungen von %a</translation>
    </message>
    <message>
        <source>Default of %a</source>
        <translation>Vorgabewert von %a</translation>
    </message>
    <message>
        <source>%a is required</source>
        <translation>%a ist ein Pflichtfeld</translation>
    </message>
    <message>
        <source>Accept the suggestion for %a</source>
        <translation>Den Vorschlag für %a übernehmen</translation>
    </message>
    <message>
        <source>Accept</source>
        <translation>Übernehmen</translation>
    </message>
    <message>
        <source>Translations by column</source>
        <translation>Übersetzungen nach Spalten</translation>
    </message>
    <message>
        <source>When the source has a column per language (title_de, title_fr), map it here: the object gets a translation in that language. Only translatable attributes are listed; an empty source leaves the language out.</source>
        <translation>Hat die Quelle eine Spalte je Sprache (title_de, title_fr), ordnen Sie sie hier zu: Das Objekt erhält eine Übersetzung in dieser Sprache. Nur übersetzbare Attribute sind aufgeführt; eine leere Quelle lässt die Sprache aus.</translation>
    </message>
    <message>
        <source>%n fields</source>
        <translation>%n Felder</translation>
    </message>
    <message>
        <source>Translation into %language</source>
        <translation>Übersetzung in %language</translation>
    </message>
    <message>
        <source>Source of %a in %l</source>
        <translation>Quelle von %a in %l</translation>
    </message>
    <message>
        <source>Transforms of %a in %l</source>
        <translation>Umwandlungen von %a in %l</translation>
    </message>
    <message>
        <source>The transforms</source>
        <translation>Die Umwandlungen</translation>
    </message>
    <message>
        <source>One line per attribute, steps separated by a vertical bar, arguments in parentheses, for example:</source>
        <translation>Eine Zeile je Attribut, Schritte durch einen senkrechten Strich getrennt, Argumente in Klammern, zum Beispiel:</translation>
    </message>
    <message>
        <source>Name</source>
        <translation>Name</translation>
    </message>
    <message>
        <source>What it does</source>
        <translation>Was sie tut</translation>
    </message>
    <message>
        <source>Arguments</source>
        <translation>Argumente</translation>
    </message>
    <message>
        <source>The fields of the sample (%n records)</source>
        <translation>Die Felder der Stichprobe (%n Datensätze)</translation>
    </message>
    <message>
        <source>Mapping file</source>
        <translation>Zuordnungsdatei</translation>
    </message>
    <message>
        <source>A mapping moves between imports and installations as a JSON file: download this one, or load one to replace the lines above.</source>
        <translation>Eine Zuordnung wandert als JSON-Datei zwischen Importen und Installationen: Laden Sie diese herunter, oder laden Sie eine, um die Zeilen oben zu ersetzen.</translation>
    </message>
    <message>
        <source>Download mapping (JSON)</source>
        <translation>Zuordnung herunterladen (JSON)</translation>
    </message>
    <message>
        <source>Mapping file (JSON)</source>
        <translation>Zuordnungsdatei (JSON)</translation>
    </message>
    <message>
        <source>Load a mapping…</source>
        <translation>Eine Zuordnung laden …</translation>
    </message>
    <message>
        <source>The source could not be read: %error</source>
        <translation>Die Quelle konnte nicht gelesen werden: %error</translation>
    </message>
    <message>
        <source>Sample of the source</source>
        <translation>Stichprobe der Quelle</translation>
    </message>
    <message>
        <source>Record</source>
        <translation>Datensatz</translation>
    </message>
    <message>
        <source>Dry run of the first records</source>
        <translation>Probelauf der ersten Datensätze</translation>
    </message>
    <message>
        <source>Would be</source>
        <translation>Ergebnis wäre</translation>
    </message>
    <message>
        <source>Object</source>
        <translation>Objekt</translation>
    </message>
    <message>
        <source>Changes</source>
        <translation>Änderungen</translation>
    </message>
    <message>
        <source>The source has no records.</source>
        <translation>Die Quelle hat keine Datensätze.</translation>
    </message>
    <message>
        <source>The 1.x handler %handler reads its own source and publishes the content itself.</source>
        <translation>Der Handler %handler von 1.x liest seine eigene Quelle und veröffentlicht die Inhalte selbst.</translation>
    </message>
    <message>
        <source>Options (one per line: name=value)</source>
        <translation>Optionen (eine je Zeile: name=wert)</translation>
    </message>
    <message>
        <source>The handler reads them as in 1.x (SQLIImportHandlerOptions); a value may contain "=".</source>
        <translation>Der Handler liest sie wie in 1.x (SQLIImportHandlerOptions); ein Wert darf „=“ enthalten.</translation>
    </message>
    <message>
        <source>How the records are processed</source>
        <translation>Wie die Datensätze verarbeitet werden</translation>
    </message>
    <message>
        <source>Options of the handler</source>
        <translation>Optionen des Handlers</translation>
    </message>
    <message>
        <source>Suggest a data table with AI</source>
        <translation>Eine Datentabelle mit KI vorschlagen</translation>
    </message>
    <message>
        <source>Data tables the AI suggests</source>
        <translation>Datentabellen, die die KI vorschlägt</translation>
    </message>
    <message>
        <source>Use this table</source>
        <translation>Diese Tabelle verwenden</translation>
    </message>
    <message>
        <source>Target</source>
        <translation>Ziel</translation>
    </message>
    <message>
        <source>Parent node of new content</source>
        <translation>Elternknoten neuer Inhalte</translation>
    </message>
    <message>
        <source>The node ID (a mapping can name another parent per record).</source>
        <translation>Die Knoten-ID (eine Zuordnung kann je Datensatz einen anderen Elternknoten nennen).</translation>
    </message>
    <message>
        <source>Section</source>
        <translation>Sektion</translation>
    </message>
    <message>
        <source>0: the section of the parent.</source>
        <translation>0: die Sektion des Elternknotens.</translation>
    </message>
    <message>
        <source>Language</source>
        <translation>Sprache</translation>
    </message>
    <message>
        <source>The default language of the site</source>
        <translation>Die Standardsprache der Site</translation>
    </message>
    <message>
        <source>Run as user (robot)</source>
        <translation>Als Benutzer ausführen (Robot)</translation>
    </message>
    <message>
        <source>A user ID; 0: the user who starts or schedules the run. Its permissions apply.</source>
        <translation>Eine Benutzer-ID; 0: der Benutzer, der den Lauf startet oder plant. Dessen Rechte gelten.</translation>
    </message>
    <message>
        <source>Runs</source>
        <translation>Läufe</translation>
    </message>
    <message>
        <source>Records per chunk</source>
        <translation>Datensätze je Abschnitt</translation>
    </message>
    <message>
        <source>Existing content</source>
        <translation>Vorhandene Inhalte</translation>
    </message>
    <message>
        <source>Create new, update existing</source>
        <translation>Neue anlegen, vorhandene aktualisieren</translation>
    </message>
    <message>
        <source>Only create, leave existing as they are</source>
        <translation>Nur anlegen, vorhandene unverändert lassen</translation>
    </message>
    <message>
        <source>Only update existing</source>
        <translation>Nur vorhandene aktualisieren</translation>
    </message>
    <message>
        <source>When someone edited an object since the last run</source>
        <translation>Wenn jemand ein Objekt seit dem letzten Lauf bearbeitet hat</translation>
    </message>
    <message>
        <source>Overwrite with the source</source>
        <translation>Mit der Quelle überschreiben</translation>
    </message>
    <message>
        <source>Merge: keep the fields that were edited</source>
        <translation>Zusammenführen: die bearbeiteten Felder behalten</translation>
    </message>
    <message>
        <source>Only fill empty fields</source>
        <translation>Nur leere Felder füllen</translation>
    </message>
    <message>
        <source>Skip the object</source>
        <translation>Das Objekt überspringen</translation>
    </message>
    <message>
        <source>Days a run can be undone</source>
        <translation>Tage, in denen sich ein Lauf rückgängig machen lässt</translation>
    </message>
    <message>
        <source>Empty: the setting ([RunSettings] UndoDays, %days days). 0: no undo.</source>
        <translation>Leer: die Einstellung ([RunSettings] UndoDays, %days Tage). 0: kein Rückgängigmachen.</translation>
    </message>
    <message>
        <source>At most this many records per run</source>
        <translation>Höchstens so viele Datensätze je Lauf</translation>
    </message>
    <message>
        <source>0: no limit.</source>
        <translation>0: keine Grenze.</translation>
    </message>
    <message>
        <source>Notifications</source>
        <translation>Benachrichtigungen</translation>
    </message>
    <message>
        <source>Tell when a run</source>
        <translation>Benachrichtigen, wenn ein Lauf</translation>
    </message>
    <message>
        <source>failed or had errors</source>
        <translation>fehlgeschlagen ist oder Fehler hatte</translation>
    </message>
    <message>
        <source>failed</source>
        <translation>fehlgeschlagen ist</translation>
    </message>
    <message>
        <source>ended (every run)</source>
        <translation>beendet ist (jeder Lauf)</translation>
    </message>
    <message>
        <source>never</source>
        <translation>nie</translation>
    </message>
    <message>
        <source>A notice for the user who started the run</source>
        <translation>Ein Hinweis für den Benutzer, der den Lauf gestartet hat</translation>
    </message>
    <message>
        <source>Shown on the Imports dashboard until the run is looked at.</source>
        <translation>Auf der Übersicht der Importe angezeigt, bis der Lauf angesehen wurde.</translation>
    </message>
    <message>
        <source>E-mail addresses</source>
        <translation>E-Mail-Adressen</translation>
    </message>
    <message>
        <source>One per line; they get a mail with the counts, the first errors and the link to the run. Dry runs send no mail.</source>
        <translation>Eine je Zeile; sie erhalten eine E-Mail mit den Zahlen, den ersten Fehlern und dem Link zum Lauf. Probeläufe senden keine E-Mail.</translation>
    </message>
    <message>
        <source>AI assist</source>
        <translation>KI-Unterstützung</translation>
    </message>
    <message>
        <source>Send sample values to the AI</source>
        <translation>Beispielwerte an die KI senden</translation>
    </message>
    <message>
        <source>Off: the AI gets only the names and types of the fields. On: also a few sample values per field, and the values of a failed row it explains; e-mail addresses, phone numbers and IBAN-like values are masked before they are sent.</source>
        <translation>Aus: Die KI erhält nur die Namen und Typen der Felder. An: auch einige Beispielwerte je Feld und die Werte einer fehlgeschlagenen Zeile, die sie erklärt; E-Mail-Adressen, Telefonnummern und IBAN-ähnliche Werte werden vor dem Senden maskiert.</translation>
    </message>
    <message>
        <source>Mapping to %class</source>
        <translation>Zuordnung zu %class</translation>
    </message>
    <message>
        <source>node %node</source>
        <translation>Knoten %node</translation>
    </message>
    <message>
        <source>runs as user %user</source>
        <translation>läuft als Benutzer %user</translation>
    </message>
    <message>
        <source>none</source>
        <translation>keiner</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>pausiert</translation>
    </message>
    <message>
        <source>Before the import can run:</source>
        <translation>Bevor der Import laufen kann:</translation>
    </message>
    <message>
        <source>The import is enabled (it can run and its schedule runs it)</source>
        <translation>Der Import ist aktiviert (er kann laufen, und sein Zeitplan führt ihn aus)</translation>
    </message>
    <message>
        <source>Runs by itself</source>
        <translation>Läuft von selbst</translation>
    </message>
    <message>
        <source>Never (only by hand or from the command line)</source>
        <translation>Nie (nur von Hand oder über die Kommandozeile)</translation>
    </message>
    <message>
        <source>Once, at the date and time below</source>
        <translation>Einmal, zu Datum und Uhrzeit unten</translation>
    </message>
    <message>
        <source>Every N minutes, from the date and time below</source>
        <translation>Alle N Minuten, ab Datum und Uhrzeit unten</translation>
    </message>
    <message>
        <source>Every hour, at the minute of the time below</source>
        <translation>Jede Stunde, zur Minute der Uhrzeit unten</translation>
    </message>
    <message>
        <source>Every day, at the time below</source>
        <translation>Jeden Tag, zur Uhrzeit unten</translation>
    </message>
    <message>
        <source>Every week, on the weekday and at the time below</source>
        <translation>Jede Woche, am Wochentag und zur Uhrzeit unten</translation>
    </message>
    <message>
        <source>Every month, on the day and at the time below</source>
        <translation>Jeden Monat, am Tag und zur Uhrzeit unten</translation>
    </message>
    <message>
        <source>A cron expression</source>
        <translation>Ein Cron-Ausdruck</translation>
    </message>
    <message>
        <source>Date and time</source>
        <translation>Datum und Uhrzeit</translation>
    </message>
    <message>
        <source>Time zone</source>
        <translation>Zeitzone</translation>
    </message>
    <message>
        <source>Every how many minutes</source>
        <translation>Alle wie viele Minuten</translation>
    </message>
    <message>
        <source>At least 5.</source>
        <translation>Mindestens 5.</translation>
    </message>
    <message>
        <source>Cron expression</source>
        <translation>Cron-Ausdruck</translation>
    </message>
    <message>
        <source>Minute, hour, day of the month, month, day of the week; *, lists, ranges, steps and names (mon, jan).</source>
        <translation>Minute, Stunde, Tag des Monats, Monat, Wochentag; *, Listen, Bereiche, Schritte und Namen (mon, jan).</translation>
    </message>
    <message>
        <source>When runs were missed</source>
        <translation>Wenn Läufe verpasst wurden</translation>
    </message>
    <message>
        <source>Run once as soon as possible</source>
        <translation>Einmal so bald wie möglich ausführen</translation>
    </message>
    <message>
        <source>Wait for the next time</source>
        <translation>Auf den nächsten Termin warten</translation>
    </message>
    <message>
        <source>E-mail when a run fails</source>
        <translation>E-Mail, wenn ein Lauf fehlschlägt</translation>
    </message>
    <message>
        <source>Addresses, one per line.</source>
        <translation>Adressen, eine je Zeile.</translation>
    </message>
    <message>
        <source>The schedule is active</source>
        <translation>Der Zeitplan ist aktiv</translation>
    </message>
    <message>
        <source>Next runs</source>
        <translation>Nächste Läufe</translation>
    </message>
    <message>
        <source>The objects will be created in %folder.</source>
        <translation>Die Objekte werden in %folder angelegt.</translation>
    </message>
    <message>
        <source>The mapping will start with the class %class.</source>
        <translation>Die Zuordnung beginnt mit der Klasse %class.</translation>
    </message>
    <message>
        <source>Use the file %name from %tool (job %job)</source>
        <translation>Die Datei %name aus %tool verwenden (Auftrag %job)</translation>
    </message>
    <message>
        <source>Its column manifest comes with it: the mapping is made from it.</source>
        <translation>Ihr Spaltenmanifest kommt mit: Die Zuordnung entsteht daraus.</translation>
    </message>
    <message>
        <source>Choose the format below, or upload another file instead.</source>
        <translation>Wählen Sie unten das Format, oder laden Sie stattdessen eine andere Datei hoch.</translation>
    </message>
    <message>
        <source>Identifier</source>
        <translation>Bezeichner</translation>
    </message>
    <message>
        <source>Names the import on the command line (ext:sqliimport:run followed by the identifier). Empty: made from the name.</source>
        <translation>Benennt den Import auf der Kommandozeile (ext:sqliimport:run, gefolgt vom Bezeichner). Leer: aus dem Namen gebildet.</translation>
    </message>
    <message>
        <source>Description</source>
        <translation>Beschreibung</translation>
    </message>
    <message>
        <source>Source type</source>
        <translation>Quelltyp</translation>
    </message>
    <message>
        <source>Every format, with an example and what it maps to, is described in the</source>
        <translation>Jedes Format, mit einem Beispiel und dem, worauf es abbildet, ist beschrieben im</translation>
    </message>
    <message>
        <source>catalogue</source>
        <translation>Katalog</translation>
    </message>
    <message>
        <source>A 1.x handler that reads its own source</source>
        <translation>Ein Handler von 1.x, der seine eigene Quelle liest</translation>
    </message>
    <message>
        <source>Where the data comes from</source>
        <translation>Woher die Daten kommen</translation>
    </message>
    <message>
        <source>An uploaded file</source>
        <translation>Eine hochgeladene Datei</translation>
    </message>
    <message>
        <source>An address (http or https), read at every run</source>
        <translation>Eine Adresse (http oder https), bei jedem Lauf gelesen</translation>
    </message>
    <message>
        <source>Read by the source itself (a database, a mailbox, a site), with the options below</source>
        <translation>Von der Quelle selbst gelesen (eine Datenbank, ein Postfach, eine Site), mit den Optionen unten</translation>
    </message>
    <message>
        <source>A file on the server</source>
        <translation>Eine Datei auf dem Server</translation>
    </message>
    <message>
        <source>File</source>
        <translation>Datei</translation>
    </message>
    <message>
        <source>Uploaded: %name. Choose a file only to replace it.</source>
        <translation>Hochgeladen: %name. Wählen Sie nur dann eine Datei, wenn Sie sie ersetzen möchten.</translation>
    </message>
    <message>
        <source>At most %mb MB.</source>
        <translation>Höchstens %mb MB.</translation>
    </message>
    <message>
        <source>Address</source>
        <translation>Adresse</translation>
    </message>
    <message>
        <source>Server file</source>
        <translation>Datei auf dem Server</translation>
    </message>
    <message>
        <source>Inside: %paths</source>
        <translation>Innerhalb von: %paths</translation>
    </message>
    <message>
        <source>1.x handler</source>
        <translation>Handler von 1.x</translation>
    </message>
    <message>
        <source>Options of the source (%type)</source>
        <translation>Optionen der Quelle (%type)</translation>
    </message>
    <message>
        <source>Load a mapping (optional)</source>
        <translation>Eine Zuordnung laden (optional)</translation>
    </message>
    <message>
        <source>A mapping file (JSON) of another import or installation: its lines are used instead of those of the format.</source>
        <translation>Eine Zuordnungsdatei (JSON) eines anderen Imports oder einer anderen Installation: Ihre Zeilen werden statt der des Formats verwendet.</translation>
    </message>
    <message>
        <source>Test connection</source>
        <translation>Verbindung testen</translation>
    </message>
    <message>
        <source>Connects with the options above and reads the first record. Nothing is saved.</source>
        <translation>Verbindet sich mit den Optionen oben und liest den ersten Datensatz. Es wird nichts gespeichert.</translation>
    </message>
    <message>
        <source>The connection works.</source>
        <translation>Die Verbindung funktioniert.</translation>
    </message>
    <message>
        <source>Its fields:</source>
        <translation>Seine Felder:</translation>
    </message>
    <message>
        <source>The test failed:</source>
        <translation>Der Test ist fehlgeschlagen:</translation>
    </message>
    <message>
        <source>What this server can connect with</source>
        <translation>Womit sich dieser Server verbinden kann</translation>
    </message>
    <message>
        <source>Connects with the options above and reads the first row of the query. Nothing is saved.</source>
        <translation>Verbindet sich mit den Optionen oben und liest die erste Zeile der Abfrage. Es wird nichts gespeichert.</translation>
    </message>
    <message>
        <source>The query gives the columns:</source>
        <translation>Die Abfrage liefert die Spalten:</translation>
    </message>
    <message>
        <source>The query gives no row.</source>
        <translation>Die Abfrage liefert keine Zeile.</translation>
    </message>
    <message>
        <source>Its columns:</source>
        <translation>Ihre Spalten:</translation>
    </message>
    <message>
        <source>Databases this server can read</source>
        <translation>Datenbanken, die dieser Server lesen kann</translation>
    </message>
    <message>
        <source>Started by another system (webhook or API)</source>
        <translation>Von einem anderen System gestartet (Webhook oder API)</translation>
    </message>
    <message>
        <source>Another system can start this import with a signed call or an API key, and may bring the data in the call. Off until it is switched on here.</source>
        <translation>Ein anderes System kann diesen Import mit einem signierten Aufruf oder einem API-Schlüssel starten und die Daten im Aufruf mitbringen. Ausgeschaltet, bis es hier eingeschaltet wird.</translation>
    </message>
    <message>
        <source>Triggers are switched off for every import ([TriggerSettings] Trigger=disabled): calls are answered with 404.</source>
        <translation>Auslöser sind für jeden Import ausgeschaltet ([TriggerSettings] Trigger=disabled): Aufrufe werden mit 404 beantwortet.</translation>
    </message>
    <message>
        <source>API key "%label"</source>
        <translation>API-Schlüssel „%label“</translation>
    </message>
    <message>
        <source>Signing secret</source>
        <translation>Signaturgeheimnis</translation>
    </message>
    <message>
        <source>Copy it now and keep it where the calling system keeps its secrets: it is shown only this once and is not stored in a readable form. If it is lost, create a new one.</source>
        <translation>Kopieren Sie es jetzt und bewahren Sie es dort auf, wo das aufrufende System seine Geheimnisse aufbewahrt: Es wird nur dieses eine Mal angezeigt und nicht in lesbarer Form gespeichert. Geht es verloren, legen Sie ein neues an.</translation>
    </message>
    <message>
        <source>The trigger is on</source>
        <translation>Der Auslöser ist eingeschaltet</translation>
    </message>
    <message>
        <source>Run as user (ID)</source>
        <translation>Als Benutzer ausführen (ID)</translation>
    </message>
    <message>
        <source>A user with the policy Run imports (or Manage imports) for this import; a triggered run never runs as anonymous. The robot user of the processing step still takes precedence.</source>
        <translation>Ein Benutzer mit der Richtlinie Run imports (oder Manage imports) für diesen Import; ein ausgelöster Lauf läuft nie als anonym. Der Robot-Benutzer des Schritts Verarbeitung hat weiterhin Vorrang.</translation>
    </message>
    <message>
        <source>Now: %login.</source>
        <translation>Derzeit: %login.</translation>
    </message>
    <message>
        <source>A call may bring the data</source>
        <translation>Ein Aufruf darf die Daten mitbringen</translation>
    </message>
    <message>
        <source>The body of the call (in the format of the import, up to %mb MB) is read instead of the source, for that run only.</source>
        <translation>Der Inhalt des Aufrufs (im Format des Imports, bis zu %mb MB) wird statt der Quelle gelesen, nur für diesen Lauf.</translation>
    </message>
    <message>
        <source>Only an import that reads a file can take its data from a call; this one reads its source itself.</source>
        <translation>Nur ein Import, der eine Datei liest, kann seine Daten aus einem Aufruf nehmen; dieser liest seine Quelle selbst.</translation>
    </message>
    <message>
        <source>Signing secret by name (optional)</source>
        <translation>Signaturgeheimnis über einen Namen (optional)</translation>
    </message>
    <message>
        <source>Empty: the extension makes a secret and shows it once. A name: a secret of at least 32 characters kept in Secrets, the settings or the environment.</source>
        <translation>Leer: Die Erweiterung erzeugt ein Geheimnis und zeigt es einmal an. Ein Name: ein Geheimnis mit mindestens 32 Zeichen, das in Geheimnisse, den Einstellungen oder der Umgebung aufbewahrt wird.</translation>
    </message>
    <message>
        <source>A signed call signs the path %path, the time and the body (doc/triggers.md); an API key is sent as Authorization: Bearer or X-Api-Key. Call a siteaccess that does not ask to sign in.</source>
        <translation>Ein signierter Aufruf signiert den Pfad %path, die Uhrzeit und den Inhalt (doc/triggers.md); ein API-Schlüssel wird als Authorization: Bearer oder X-Api-Key gesendet. Rufen Sie einen Siteaccess auf, der keine Anmeldung verlangt.</translation>
    </message>
    <message>
        <source>Save the trigger</source>
        <translation>Auslöser speichern</translation>
    </message>
    <message>
        <source>The trigger signs with the secret %name.</source>
        <translation>Der Auslöser signiert mit dem Geheimnis %name.</translation>
    </message>
    <message>
        <source>Made on %date.</source>
        <translation>Erzeugt am %date.</translation>
    </message>
    <message>
        <source>Make a new signing secret? The current one stops working at once.</source>
        <translation>Ein neues Signaturgeheimnis erzeugen? Das aktuelle funktioniert sofort nicht mehr.</translation>
    </message>
    <message>
        <source>New signing secret</source>
        <translation>Neues Signaturgeheimnis</translation>
    </message>
    <message>
        <source>None yet: it is made when the trigger is switched on.</source>
        <translation>Noch keines: Es wird erzeugt, wenn der Auslöser eingeschaltet wird.</translation>
    </message>
    <message>
        <source>API keys</source>
        <translation>API-Schlüssel</translation>
    </message>
    <message>
        <source>No key yet. A key may start runs and read their status.</source>
        <translation>Noch kein Schlüssel. Ein Schlüssel darf Läufe starten und ihren Status lesen.</translation>
    </message>
    <message>
        <source>Key ID</source>
        <translation>Schlüssel-ID</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>Angelegt</translation>
    </message>
    <message>
        <source>Last used</source>
        <translation>Zuletzt verwendet</translation>
    </message>
    <message>
        <source>Actions</source>
        <translation>Aktionen</translation>
    </message>
    <message>
        <source>revoked</source>
        <translation>widerrufen</translation>
    </message>
    <message>
        <source>Revoke this key? Calls with it are refused at once.</source>
        <translation>Diesen Schlüssel widerrufen? Aufrufe mit ihm werden sofort abgelehnt.</translation>
    </message>
    <message>
        <source>Revoke</source>
        <translation>Widerrufen</translation>
    </message>
    <message>
        <source>Name of a new key</source>
        <translation>Name eines neuen Schlüssels</translation>
    </message>
    <message>
        <source>e.g. the shop system</source>
        <translation>z. B. das Shopsystem</translation>
    </message>
    <message>
        <source>Create an API key</source>
        <translation>API-Schlüssel anlegen</translation>
    </message>
    <message>
        <source>New value (stored in Setup, Secrets under this name)</source>
        <translation>Neuer Wert (unter diesem Namen in Setup, Geheimnisse gespeichert)</translation>
    </message>
    <message>
        <source>Leave it empty to keep the stored value. It is never shown again, not even after an error.</source>
        <translation>Lassen Sie das Feld leer, um den gespeicherten Wert zu behalten. Er wird nie wieder angezeigt, auch nicht nach einem Fehler.</translation>
    </message>
    <message>
        <source>Import "%import": %option</source>
        <translation>Import „%import“: %option</translation>
    </message>
    <message>
        <source>The secret %name is stored.</source>
        <translation>Das Geheimnis %name ist gespeichert.</translation>
    </message>
    <message>
        <source>Give the import a name.</source>
        <translation>Geben Sie dem Import einen Namen.</translation>
    </message>
    <message>
        <source>Choose a source type.</source>
        <translation>Wählen Sie einen Quelltyp.</translation>
    </message>
    <message>
        <source>Fill in the options of %format below, then save again.</source>
        <translation>Füllen Sie unten die Optionen von %format aus und speichern Sie dann erneut.</translation>
    </message>
    <message>
        <source>Choose the file to upload.</source>
        <translation>Wählen Sie die hochzuladende Datei.</translation>
    </message>
    <message>
        <source>Choose the 1.x handler.</source>
        <translation>Wählen Sie den Handler von 1.x.</translation>
    </message>
    <message>
        <source>The identifier takes lower case letters, digits, _ and -.</source>
        <translation>Der Bezeichner besteht aus Kleinbuchstaben, Ziffern, _ und -.</translation>
    </message>
    <message>
        <source>Another import has this identifier.</source>
        <translation>Ein anderer Import hat diesen Bezeichner.</translation>
    </message>
    <message>
        <source>This source type has no connection to test.</source>
        <translation>Dieser Quelltyp hat keine Verbindung zum Testen.</translation>
    </message>
    <message>
        <source>Correct the options first.</source>
        <translation>Korrigieren Sie zuerst die Optionen.</translation>
    </message>
    <message>
        <source>Test connection works for the source type SQL database or query.</source>
        <translation>Verbindung testen funktioniert für den Quelltyp SQL-Datenbank oder -Abfrage.</translation>
    </message>
    <message>
        <source>The mapping file was loaded: %n lines for the class %class.</source>
        <translation>Die Zuordnungsdatei wurde geladen: %n Zeilen für die Klasse %class.</translation>
    </message>
    <message>
        <source>The format comes with a mapping to the class %class, which this site does not have. Map the fields yourself.</source>
        <translation>Das Format bringt eine Zuordnung zur Klasse %class mit, die diese Site nicht hat. Ordnen Sie die Felder selbst zu.</translation>
    </message>
    <message>
        <source>The mapping of the format to %class was filled in (%n fields). Check it in the step Mapping.</source>
        <translation>Die Zuordnung des Formats zu %class wurde ausgefüllt (%n Felder). Prüfen Sie sie im Schritt Zuordnung.</translation>
    </message>
    <message>
        <source>The mapping starts with the class %class: match its fields in the step Mapping (Auto-match helps).</source>
        <translation>Die Zuordnung beginnt mit der Klasse %class: Ordnen Sie ihre Felder im Schritt Zuordnung zu (die automatische Zuordnung hilft).</translation>
    </message>
    <message>
        <source>Choose how the records are processed.</source>
        <translation>Wählen Sie, wie die Datensätze verarbeitet werden.</translation>
    </message>
    <message>
        <source>There is no node with this ID.</source>
        <translation>Es gibt keinen Knoten mit dieser ID.</translation>
    </message>
    <message>
        <source>There is no section with this ID.</source>
        <translation>Es gibt keine Sektion mit dieser ID.</translation>
    </message>
    <message>
        <source>The site has no language %locale.</source>
        <translation>Die Site hat keine Sprache %locale.</translation>
    </message>
    <message>
        <source>There is no user with this ID.</source>
        <translation>Es gibt keinen Benutzer mit dieser ID.</translation>
    </message>
    <message>
        <source>A chunk has between 1 and 5000 records.</source>
        <translation>Ein Abschnitt hat zwischen 1 und 5000 Datensätze.</translation>
    </message>
    <message>
        <source>Undo days: a number of days, or empty for the setting.</source>
        <translation>Tage zum Rückgängigmachen: eine Anzahl von Tagen oder leer für die Einstellung.</translation>
    </message>
    <message>
        <source>You may not edit imports of this handler.</source>
        <translation>Sie dürfen Importe dieses Handlers nicht bearbeiten.</translation>
    </message>
    <message>
        <source>%address is not an e-mail address.</source>
        <translation>%address ist keine E-Mail-Adresse.</translation>
    </message>
    <message>
        <source>At most 20 addresses.</source>
        <translation>Höchstens 20 Adressen.</translation>
    </message>
    <message>
        <source>Choose the content class.</source>
        <translation>Wählen Sie die Inhaltsklasse.</translation>
    </message>
    <message>
        <source>Auto-match filled %n lines. Check them, then save.</source>
        <translation>Die automatische Zuordnung hat %n Zeilen ausgefüllt. Prüfen Sie sie und speichern Sie dann.</translation>
    </message>
    <message>
        <source>The AI assist needs the fields of the sample, which could not be read.</source>
        <translation>Die KI-Unterstützung braucht die Felder der Stichprobe, die nicht gelesen werden konnte.</translation>
    </message>
    <message>
        <source>The AI assist could not answer: %error</source>
        <translation>Die KI-Unterstützung konnte nicht antworten: %error</translation>
    </message>
    <message>
        <source>The AI suggests %n lines. Accept the ones that fit, check them, then save.</source>
        <translation>Die KI schlägt %n Zeilen vor. Übernehmen Sie die passenden, prüfen Sie sie und speichern Sie dann.</translation>
    </message>
    <message>
        <source>The AI has no suggestion that fits the class and the fields.</source>
        <translation>Die KI hat keinen Vorschlag, der zur Klasse und zu den Feldern passt.</translation>
    </message>
    <message>
        <source>The suggestions are no longer there. Ask the AI again.</source>
        <translation>Die Vorschläge sind nicht mehr vorhanden. Fragen Sie die KI erneut.</translation>
    </message>
    <message>
        <source>Accepted %n suggestions into the form. Check them, then save; nothing is saved before.</source>
        <translation>%n Vorschläge in das Formular übernommen. Prüfen Sie sie und speichern Sie dann; vorher wird nichts gespeichert.</translation>
    </message>
    <message>
        <source>Unknown time zone.</source>
        <translation>Unbekannte Zeitzone.</translation>
    </message>
    <message>
        <source>Choose a date and time.</source>
        <translation>Wählen Sie ein Datum und eine Uhrzeit.</translation>
    </message>
    <message>
        <source>This user may not run this import (policy Run imports or Manage imports).</source>
        <translation>Dieser Benutzer darf diesen Import nicht ausführen (Richtlinie Run imports oder Manage imports).</translation>
    </message>
    <message>
        <source>Choose the user a triggered run runs as.</source>
        <translation>Wählen Sie den Benutzer, als der ein ausgelöster Lauf läuft.</translation>
    </message>
    <message>
        <source>There is no secret of this name.</source>
        <translation>Es gibt kein Geheimnis mit diesem Namen.</translation>
    </message>
    <message>
        <source>A signing secret has at least 32 characters.</source>
        <translation>Ein Signaturgeheimnis hat mindestens 32 Zeichen.</translation>
    </message>
    <message>
        <source>Only an import that reads a file can take its data from a call.</source>
        <translation>Nur ein Import, der eine Datei liest, kann seine Daten aus einem Aufruf nehmen.</translation>
    </message>
    <message>
        <source>The trigger signs with a named secret: change it where the secret is kept.</source>
        <translation>Der Auslöser signiert mit einem benannten Geheimnis: Ändern Sie es dort, wo das Geheimnis aufbewahrt wird.</translation>
    </message>
    <message>
        <source>Name the key (who or what uses it).</source>
        <translation>Benennen Sie den Schlüssel (wer oder was ihn verwendet).</translation>
    </message>
    <message>
        <source>This key does not exist.</source>
        <translation>Dieser Schlüssel existiert nicht.</translation>
    </message>
    <message>
        <source>The import has no source yet.</source>
        <translation>Der Import hat noch keine Quelle.</translation>
    </message>
    <message>
        <source>The mapping is missing.</source>
        <translation>Die Zuordnung fehlt.</translation>
    </message>
    <message>
        <source>The AI assist is not available: %reason</source>
        <translation>Die KI-Unterstützung ist nicht verfügbar: %reason</translation>
    </message>
    <message>
        <source>The data table %class is chosen in the form. Check it, then save; nothing is saved before.</source>
        <translation>Die Datentabelle %class ist im Formular gewählt. Prüfen Sie sie und speichern Sie dann; vorher wird nichts gespeichert.</translation>
    </message>
    <message>
        <source>The class %class is chosen in the form: map its attributes, then save; nothing is saved before.</source>
        <translation>Die Klasse %class ist im Formular gewählt: Ordnen Sie ihre Attribute zu und speichern Sie dann; vorher wird nichts gespeichert.</translation>
    </message>
    <message>
        <source>Choose the mapping file (JSON) to load.</source>
        <translation>Wählen Sie die zu ladende Zuordnungsdatei (JSON).</translation>
    </message>
    <message>
        <source>The API key is created. Copy it now: it is shown only this once.</source>
        <translation>Der API-Schlüssel ist angelegt. Kopieren Sie ihn jetzt: Er wird nur dieses eine Mal angezeigt.</translation>
    </message>
    <message>
        <source>The signing secret is new. Copy it now: it is shown only this once.</source>
        <translation>Das Signaturgeheimnis ist neu. Kopieren Sie es jetzt: Es wird nur dieses eine Mal angezeigt.</translation>
    </message>
    <message>
        <source>The file of %tool job %job is not available to you; upload the file instead.</source>
        <translation>Die Datei des Auftrags %job von %tool ist für Sie nicht verfügbar; laden Sie die Datei stattdessen hoch.</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/error</name>
    <message>
        <source>Unknown alter import action '%action'</source>
        <translation>Unbekannte Aktion zum Ändern eines Imports: „%action“</translation>
    </message>
    <message>
        <source>Please choose a correct date</source>
        <translation>Bitte wählen Sie ein gültiges Datum</translation>
    </message>
    <message>
        <source>Please choose a frequency greater than 5min</source>
        <translation>Bitte wählen Sie eine Häufigkeit von mehr als 5 Minuten</translation>
    </message>
    <message>
        <source>SQLIPendingImport : Unknown attribute "%attribute"</source>
        <translation>SQLIPendingImport: Unbekanntes Attribut „%attribute“</translation>
    </message>
    <message>
        <source>No import item found with ID #%importID</source>
        <translation>Kein Importeintrag mit der ID #%importID gefunden</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/group</name>
    <message>
        <source>Data</source>
        <translation>Daten</translation>
    </message>
    <message>
        <source>NoSQL databases</source>
        <translation>NoSQL-Datenbanken</translation>
    </message>
    <message>
        <source>Content</source>
        <translation>Inhalte</translation>
    </message>
    <message>
        <source>Platforms</source>
        <translation>Plattformen</translation>
    </message>
    <message>
        <source>Commerce</source>
        <translation>Handel</translation>
    </message>
    <message>
        <source>Media</source>
        <translation>Medien</translation>
    </message>
    <message>
        <source>Other</source>
        <translation>Sonstiges</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/kind</name>
    <message>
        <source>Import</source>
        <translation>Import</translation>
    </message>
    <message>
        <source>Dry run</source>
        <translation>Probelauf</translation>
    </message>
    <message>
        <source>Retry of failed rows</source>
        <translation>Wiederholung fehlgeschlagener Zeilen</translation>
    </message>
    <message>
        <source>Undo</source>
        <translation>Rückgängigmachen</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/mapping</name>
    <message>
        <source>choose the content class</source>
        <translation>Wählen Sie die Inhaltsklasse</translation>
    </message>
    <message>
        <source>the remote ID pattern needs at least one {field} of the record, e.g. news-{id}, or {_ref} for the reference of the record</source>
        <translation>Das Muster der Remote-ID braucht mindestens ein {field} des Datensatzes, z. B. news-{id}, oder {_ref} für die Referenz des Datensatzes</translation>
    </message>
    <message>
        <source>matching on an attribute needs the attribute and the source field</source>
        <translation>Der Abgleich über ein Attribut braucht das Attribut und das Quellfeld</translation>
    </message>
    <message>
        <source>the class has no attribute '%attribute' to match on</source>
        <translation>Die Klasse hat kein Attribut „%attribute“ für den Abgleich</translation>
    </message>
    <message>
        <source>choose how a record finds its object (remote ID or attribute)</source>
        <translation>Wählen Sie, wie ein Datensatz sein Objekt findet (Remote-ID oder Attribut)</translation>
    </message>
    <message>
        <source>map at least one field</source>
        <translation>Ordnen Sie mindestens ein Feld zu</translation>
    </message>
    <message>
        <source>line %n: choose the attribute</source>
        <translation>Zeile %n: Wählen Sie das Attribut</translation>
    </message>
    <message>
        <source>line %n: the class has no attribute '%attribute'</source>
        <translation>Zeile %n: Die Klasse hat kein Attribut „%attribute“</translation>
    </message>
    <message>
        <source>line %n: the attribute '%attribute' is mapped twice</source>
        <translation>Zeile %n: Das Attribut „%attribute“ ist zweimal zugeordnet</translation>
    </message>
    <message>
        <source>line %n: give a source field or a default value for '%attribute'</source>
        <translation>Zeile %n: Geben Sie ein Quellfeld oder einen Vorgabewert für „%attribute“ an</translation>
    </message>
    <message>
        <source>line %n: %error</source>
        <translation>Zeile %n: %error</translation>
    </message>
    <message>
        <source>choose where new objects are placed (a parent node, or a field with the parent's remote ID)</source>
        <translation>Wählen Sie, wo neue Objekte platziert werden (ein Elternknoten oder ein Feld mit der Remote-ID des Elternknotens)</translation>
    </message>
    <message>
        <source>the variants of the mapping must be a list of values with their class</source>
        <translation>Die Varianten der Zuordnung müssen eine Liste von Werten mit ihrer Klasse sein</translation>
    </message>
    <message>
        <source>variants need the field that chooses them (class_from)</source>
        <translation>Varianten brauchen das Feld, das sie auswählt (class_from)</translation>
    </message>
    <message>
        <source>variant '%value': give a class or skip</source>
        <translation>Variante „%value“: Geben Sie eine Klasse oder skip an</translation>
    </message>
    <message>
        <source>variant '%value': the class '%class' does not exist</source>
        <translation>Variante „%value“: Die Klasse „%class“ existiert nicht</translation>
    </message>
    <message>
        <source>variant '%value': %error</source>
        <translation>Variante „%value“: %error</translation>
    </message>
    <message>
        <source>unknown transform #%n</source>
        <translation>Unbekannte Umwandlung #%n</translation>
    </message>
    <message>
        <source>unknown transform '%name'</source>
        <translation>Unbekannte Umwandlung „%name“</translation>
    </message>
    <message>
        <source>%name: %error</source>
        <translation>%name: %error</translation>
    </message>
    <message>
        <source>regex_replace: the pattern is not a valid regular expression (delimiters included, e.g. /a+/)</source>
        <translation>regex_replace: Das Muster ist kein gültiger regulärer Ausdruck (mit Begrenzern, z. B. /a+/)</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/menu</name>
    <message>
        <source>Imports</source>
        <translation>Importe</translation>
    </message>
    <message>
        <source>Overview</source>
        <translation>Übersicht</translation>
    </message>
    <message>
        <source>Statistics</source>
        <translation>Statistik</translation>
    </message>
    <message>
        <source>New import</source>
        <translation>Neuer Import</translation>
    </message>
    <message>
        <source>Schedules</source>
        <translation>Zeitpläne</translation>
    </message>
    <message>
        <source>Catalogue</source>
        <translation>Katalog</translation>
    </message>
    <message>
        <source>Settings</source>
        <translation>Einstellungen</translation>
    </message>
    <message>
        <source>Running runs</source>
        <translation>Laufende Läufe</translation>
    </message>
    <message>
        <source>Failed runs</source>
        <translation>Fehlgeschlagene Läufe</translation>
    </message>
    <message>
        <source>Job queue</source>
        <translation>Auftragswarteschlange</translation>
    </message>
    <message>
        <source>Manage your imports</source>
        <translation>Importe verwalten</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/options</name>
    <message>
        <source>%label is required</source>
        <translation>%label ist ein Pflichtfeld</translation>
    </message>
    <message>
        <source>%label: %error</source>
        <translation>%label: %error</translation>
    </message>
    <message>
        <source>a whole number is expected</source>
        <translation>eine ganze Zahl wird erwartet</translation>
    </message>
    <message>
        <source>a node ID is expected</source>
        <translation>eine Knoten-ID wird erwartet</translation>
    </message>
    <message>
        <source>a number is expected</source>
        <translation>eine Zahl wird erwartet</translation>
    </message>
    <message>
        <source>yes or no is expected</source>
        <translation>ja oder nein wird erwartet</translation>
    </message>
    <message>
        <source>one of %values is expected</source>
        <translation>einer der Werte %values wird erwartet</translation>
    </message>
    <message>
        <source>an http or https address is expected</source>
        <translation>eine http- oder https-Adresse wird erwartet</translation>
    </message>
    <message>
        <source>a path without ".." is expected</source>
        <translation>ein Pfad ohne „..“ wird erwartet</translation>
    </message>
    <message>
        <source>a class identifier is expected</source>
        <translation>ein Klassenbezeichner wird erwartet</translation>
    </message>
    <message>
        <source>a JSON object or list is expected</source>
        <translation>ein JSON-Objekt oder eine JSON-Liste wird erwartet</translation>
    </message>
    <message>
        <source>the name of a secret in the settings is expected, never the secret itself</source>
        <translation>der Name eines Geheimnisses in den Einstellungen wird erwartet, nie das Geheimnis selbst</translation>
    </message>
    <message>
        <source>one line is expected</source>
        <translation>eine Zeile wird erwartet</translation>
    </message>
    <message>
        <source>the value does not have the expected form</source>
        <translation>der Wert hat nicht die erwartete Form</translation>
    </message>
    <message>
        <source>at least %min characters</source>
        <translation>mindestens %min Zeichen</translation>
    </message>
    <message>
        <source>at least %min</source>
        <translation>mindestens %min</translation>
    </message>
    <message>
        <source>at most %max characters</source>
        <translation>höchstens %max Zeichen</translation>
    </message>
    <message>
        <source>at most %max</source>
        <translation>höchstens %max</translation>
    </message>
    <message>
        <source>Delimiter</source>
        <translation>Trennzeichen</translation>
    </message>
    <message>
        <source>Detect</source>
        <translation>Erkennen</translation>
    </message>
    <message>
        <source>Comma</source>
        <translation>Komma</translation>
    </message>
    <message>
        <source>Semicolon</source>
        <translation>Semikolon</translation>
    </message>
    <message>
        <source>Tab</source>
        <translation>Tabulator</translation>
    </message>
    <message>
        <source>Pipe</source>
        <translation>Senkrechter Strich</translation>
    </message>
    <message>
        <source>Enclosure</source>
        <translation>Textbegrenzer</translation>
    </message>
    <message>
        <source>Encoding</source>
        <translation>Zeichenkodierung</translation>
    </message>
    <message>
        <source>Windows-1252</source>
        <translation>Windows-1252</translation>
    </message>
    <message>
        <source>The first line names the columns</source>
        <translation>Die erste Zeile benennt die Spalten</translation>
    </message>
    <message>
        <source>Key field</source>
        <translation>Schlüsselfeld</translation>
    </message>
    <message>
        <source>The field that names a record (an ID column, a GUID). Empty: the position in the source.</source>
        <translation>Das Feld, das einen Datensatz benennt (eine ID-Spalte, eine GUID). Leer: die Position in der Quelle.</translation>
    </message>
    <message>
        <source>Sheet</source>
        <translation>Tabellenblatt</translation>
    </message>
    <message>
        <source>The name of the sheet, or its number (1 is the first)</source>
        <translation>Der Name des Tabellenblatts oder seine Nummer (1 ist das erste)</translation>
    </message>
    <message>
        <source>The first row names the columns</source>
        <translation>Die erste Zeile benennt die Spalten</translation>
    </message>
    <message>
        <source>Format</source>
        <translation>Format</translation>
    </message>
    <message>
        <source>JSON (a list of objects)</source>
        <translation>JSON (eine Liste von Objekten)</translation>
    </message>
    <message>
        <source>NDJSON (one object per line)</source>
        <translation>NDJSON (ein Objekt je Zeile)</translation>
    </message>
    <message>
        <source>Record list</source>
        <translation>Liste der Datensätze</translation>
    </message>
    <message>
        <source>JSON: the path of the list of records, e.g. data.items; empty: the top</source>
        <translation>JSON: der Pfad der Liste der Datensätze, z. B. data.items; leer: die oberste Ebene</translation>
    </message>
    <message>
        <source>Arrays</source>
        <translation>Arrays</translation>
    </message>
    <message>
        <source>As JSON (["a","b"])</source>
        <translation>Als JSON (["a","b"])</translation>
    </message>
    <message>
        <source>As a joined list (a, b); arrays of records stay JSON</source>
        <translation>Als verbundene Liste (a, b); Arrays von Datensätzen bleiben JSON</translation>
    </message>
    <message>
        <source>List separator</source>
        <translation>Listentrennzeichen</translation>
    </message>
    <message>
        <source>Lists</source>
        <translation>Listen</translation>
    </message>
    <message>
        <source>As a joined list (a, b); lists of structs stay JSON</source>
        <translation>Als verbundene Liste (a, b); Listen von Structs bleiben JSON</translation>
    </message>
    <message>
        <source>Server address</source>
        <translation>Serveradresse</translation>
    </message>
    <message>
        <source>The address of the cluster, e.g. https://search.example.org:9200 (a private network only with [SearchSourceSettings] AllowedHosts[])</source>
        <translation>Die Adresse des Clusters, z. B. https://search.example.org:9200 (ein privates Netz nur mit [SearchSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Index</source>
        <translation>Index</translation>
    </message>
    <message>
        <source>An index, an alias or a pattern: articles, logs-2026-*, news,blog</source>
        <translation>Ein Index, ein Alias oder ein Muster: articles, logs-2026-*, news,blog</translation>
    </message>
    <message>
        <source>Authentication</source>
        <translation>Authentifizierung</translation>
    </message>
    <message>
        <source>None</source>
        <translation>Keine</translation>
    </message>
    <message>
        <source>User and password</source>
        <translation>Benutzer und Passwort</translation>
    </message>
    <message>
        <source>API key</source>
        <translation>API-Schlüssel</translation>
    </message>
    <message>
        <source>Token (bearer)</source>
        <translation>Token (Bearer)</translation>
    </message>
    <message>
        <source>User</source>
        <translation>Benutzer</translation>
    </message>
    <message>
        <source>With User and password: the user name</source>
        <translation>Bei Benutzer und Passwort: der Benutzername</translation>
    </message>
    <message>
        <source>Password (name of a secret)</source>
        <translation>Passwort (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>With User and password: the name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password</source>
        <translation>Bei Benutzer und Passwort: der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort</translation>
    </message>
    <message>
        <source>API key (name of a secret)</source>
        <translation>API-Schlüssel (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>With API key: the name of the secret that holds the encoded key (the "encoded" value Elasticsearch gives)</source>
        <translation>Bei API-Schlüssel: der Name des Geheimnisses, das den kodierten Schlüssel enthält (den Wert „encoded“, den Elasticsearch liefert)</translation>
    </message>
    <message>
        <source>Token (name of a secret)</source>
        <translation>Token (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>With Token: the name of the secret with the token</source>
        <translation>Bei Token: der Name des Geheimnisses mit dem Token</translation>
    </message>
    <message>
        <source>Query</source>
        <translation>Abfrage</translation>
    </message>
    <message>
        <source>Query DSL: a query ({"term": {"status": "published"}}) or a body with query, sort and _source. Empty: all documents. Scripts are refused</source>
        <translation>Query DSL: eine Abfrage ({"term": {"status": "published"}}) oder ein Inhalt mit query, sort und _source. Leer: alle Dokumente. Skripte werden abgelehnt</translation>
    </message>
    <message>
        <source>Fields</source>
        <translation>Felder</translation>
    </message>
    <message>
        <source>The fields of _source to read, one per line (author.*, title). Empty: all</source>
        <translation>Die zu lesenden Felder von _source, eines je Zeile (author.*, title). Leer: alle</translation>
    </message>
    <message>
        <source>Sort</source>
        <translation>Sortierung</translation>
    </message>
    <message>
        <source>Fields with :asc or :desc, comma separated: published_at:desc,_id. Empty: the sort of the query, else the index order</source>
        <translation>Felder mit :asc oder :desc, durch Kommas getrennt: published_at:desc,_id. Leer: die Sortierung der Abfrage, sonst die Reihenfolge des Index</translation>
    </message>
    <message>
        <source>Page size</source>
        <translation>Seitengröße</translation>
    </message>
    <message>
        <source>Documents per request</source>
        <translation>Dokumente je Anfrage</translation>
    </message>
    <message>
        <source>Paging</source>
        <translation>Blättern</translation>
    </message>
    <message>
        <source>Automatic (by the server version)</source>
        <translation>Automatisch (nach der Serverversion)</translation>
    </message>
    <message>
        <source>Point in time and search_after</source>
        <translation>Point in Time und search_after</translation>
    </message>
    <message>
        <source>search_after on the sort (its last field must be unique)</source>
        <translation>search_after über die Sortierung (ihr letztes Feld muss eindeutig sein)</translation>
    </message>
    <message>
        <source>Scroll (older servers)</source>
        <translation>Scroll (ältere Server)</translation>
    </message>
    <message>
        <source>The field that names a document; _id is its ID in the index</source>
        <translation>Das Feld, das ein Dokument benennt; _id ist seine ID im Index</translation>
    </message>
    <message>
        <source>Files</source>
        <translation>Dateien</translation>
    </message>
    <message>
        <source>One path per line, inside [SourceSettings] AllowedPaths[]; a pattern (exports/*.parquet) reads every file it matches. A DuckDB database: one file</source>
        <translation>Ein Pfad je Zeile, innerhalb von [SourceSettings] AllowedPaths[]; ein Muster (exports/*.parquet) liest jede Datei, auf die es passt. Eine DuckDB-Datenbank: eine Datei</translation>
    </message>
    <message>
        <source>File type</source>
        <translation>Dateityp</translation>
    </message>
    <message>
        <source>By the file name</source>
        <translation>Nach dem Dateinamen</translation>
    </message>
    <message>
        <source>Parquet</source>
        <translation>Parquet</translation>
    </message>
    <message>
        <source>CSV (delimiter and header detected)</source>
        <translation>CSV (Trennzeichen und Kopfzeile erkannt)</translation>
    </message>
    <message>
        <source>JSON or NDJSON</source>
        <translation>JSON oder NDJSON</translation>
    </message>
    <message>
        <source>DuckDB database (read-only)</source>
        <translation>DuckDB-Datenbank (nur lesend)</translation>
    </message>
    <message>
        <source>A single SELECT (or WITH ... SELECT); the files are the view data, a database file gives its tables. An ORDER BY keeps the pages stable</source>
        <translation>Ein einzelnes SELECT (oder WITH ... SELECT); die Dateien sind die Sicht data, eine Datenbankdatei liefert ihre Tabellen. Ein ORDER BY hält die Seiten stabil</translation>
    </message>
    <message>
        <source>Rows per query (LIMIT and OFFSET); 0: all rows at once</source>
        <translation>Zeilen je Abfrage (LIMIT und OFFSET); 0: alle Zeilen auf einmal</translation>
    </message>
    <message>
        <source>Key column</source>
        <translation>Schlüsselspalte</translation>
    </message>
    <message>
        <source>The column that names a row (an ID). Empty: the position in the result</source>
        <translation>Die Spalte, die eine Zeile benennt (eine ID). Leer: die Position im Ergebnis</translation>
    </message>
    <message>
        <source>Detect by the file name</source>
        <translation>Nach dem Dateinamen erkennen</translation>
    </message>
    <message>
        <source>YAML</source>
        <translation>YAML</translation>
    </message>
    <message>
        <source>TOML</source>
        <translation>TOML</translation>
    </message>
    <message>
        <source>The path of the list of records, e.g. products or data.items; empty: the top</source>
        <translation>Der Pfad der Liste der Datensätze, z. B. products oder data.items; leer: die oberste Ebene</translation>
    </message>
    <message>
        <source>Record element</source>
        <translation>Element eines Datensatzes</translation>
    </message>
    <message>
        <source>The element of one record, e.g. book or catalog/book</source>
        <translation>Das Element eines Datensatzes, z. B. book oder catalog/book</translation>
    </message>
    <message>
        <source>XSD schema</source>
        <translation>XSD-Schema</translation>
    </message>
    <message>
        <source>A schema file the source must validate against (optional)</source>
        <translation>Eine Schemadatei, gegen die die Quelle gültig sein muss (optional)</translation>
    </message>
    <message>
        <source>The field that names an item; id is the guid of RSS and the id of Atom</source>
        <translation>Das Feld, das einen Eintrag benennt; id ist die guid von RSS und die id von Atom</translation>
    </message>
    <message>
        <source>Outlines</source>
        <translation>Gliederungen</translation>
    </message>
    <message>
        <source>Only feeds (outlines with a feed address)</source>
        <translation>Nur Feeds (Gliederungen mit einer Feed-Adresse)</translation>
    </message>
    <message>
        <source>Only outlines without children</source>
        <translation>Nur Gliederungen ohne Kinder</translation>
    </message>
    <message>
        <source>Every outline, the categories too</source>
        <translation>Jede Gliederung, auch die Kategorien</translation>
    </message>
    <message>
        <source>The field that names a record. id: the feed address, else the web address, else the position.</source>
        <translation>Das Feld, das einen Datensatz benennt. id: die Feed-Adresse, sonst die Webadresse, sonst die Position.</translation>
    </message>
    <message>
        <source>Records</source>
        <translation>Datensätze</translation>
    </message>
    <message>
        <source>Posts and pages</source>
        <translation>Beiträge und Seiten</translation>
    </message>
    <message>
        <source>Media (attachments)</source>
        <translation>Medien (Anhänge)</translation>
    </message>
    <message>
        <source>Categories</source>
        <translation>Kategorien</translation>
    </message>
    <message>
        <source>Tags</source>
        <translation>Tags</translation>
    </message>
    <message>
        <source>Authors</source>
        <translation>Autoren</translation>
    </message>
    <message>
        <source>Post types</source>
        <translation>Beitragstypen</translation>
    </message>
    <message>
        <source>With Records "posts": the post types to read, one per line (post, page, or a custom type)</source>
        <translation>Bei Datensätze „posts“: die zu lesenden Beitragstypen, einer je Zeile (post, page oder ein eigener Typ)</translation>
    </message>
    <message>
        <source>Status</source>
        <translation>Status</translation>
    </message>
    <message>
        <source>Only published</source>
        <translation>Nur veröffentlichte</translation>
    </message>
    <message>
        <source>Every status (drafts, private, pending too)</source>
        <translation>Jeder Status (auch Entwürfe, private und ausstehende)</translation>
    </message>
    <message>
        <source>The field that names a record; id is the WordPress ID (slug for categories and tags).</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die WordPress-ID (der Slug bei Kategorien und Tags).</translation>
    </message>
    <message>
        <source>Read drafts too</source>
        <translation>Auch Entwürfe lesen</translation>
    </message>
    <message>
        <source>Files with draft: true or published: false, and the _drafts folder</source>
        <translation>Dateien mit draft: true oder published: false und der Ordner _drafts</translation>
    </message>
    <message>
        <source>Leave out README and index files</source>
        <translation>README- und index-Dateien auslassen</translation>
    </message>
    <message>
        <source>Only this folder</source>
        <translation>Nur dieser Ordner</translation>
    </message>
    <message>
        <source>A folder in the ZIP, e.g. content/posts; empty: all</source>
        <translation>Ein Ordner im ZIP, z. B. content/posts; leer: alle</translation>
    </message>
    <message>
        <source>Address of [[links]]</source>
        <translation>Adresse von [[Links]]</translation>
    </message>
    <message>
        <source>Obsidian [[Page]] links become links to this address plus the page slug; empty: their text only</source>
        <translation>Obsidian-Links [[Page]] werden zu Links auf diese Adresse plus den Slug der Seite; leer: nur ihr Text</translation>
    </message>
    <message>
        <source>The field that names a record; id is the path or the front matter id</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist der Pfad oder die id des Front Matter</translation>
    </message>
    <message>
        <source>IMAP server</source>
        <translation>IMAP-Server</translation>
    </message>
    <message>
        <source>e.g. imap.example.org (a private network only with [ImapSourceSettings] AllowedHosts[])</source>
        <translation>z. B. imap.example.org (ein privates Netz nur mit [ImapSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Port</source>
        <translation>Port</translation>
    </message>
    <message>
        <source>Encryption</source>
        <translation>Verschlüsselung</translation>
    </message>
    <message>
        <source>SSL/TLS (port 993)</source>
        <translation>SSL/TLS (Port 993)</translation>
    </message>
    <message>
        <source>STARTTLS (port 143)</source>
        <translation>STARTTLS (Port 143)</translation>
    </message>
    <message>
        <source>None (not recommended: the password is sent in clear)</source>
        <translation>Keine (nicht empfohlen: Das Passwort wird im Klartext gesendet)</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort</translation>
    </message>
    <message>
        <source>Mailbox</source>
        <translation>Postfach</translation>
    </message>
    <message>
        <source>Search</source>
        <translation>Suche</translation>
    </message>
    <message>
        <source>IMAP search criteria: ALL, UNSEEN, FROM "news@example.org", SUBJECT "Press" ...</source>
        <translation>IMAP-Suchkriterien: ALL, UNSEEN, FROM "news@example.org", SUBJECT "Press" ...</translation>
    </message>
    <message>
        <source>Only the last days</source>
        <translation>Nur die letzten Tage</translation>
    </message>
    <message>
        <source>0: every matching message</source>
        <translation>0: jede passende Nachricht</translation>
    </message>
    <message>
        <source>Mark the messages read as seen</source>
        <translation>Gelesene Nachrichten als gesehen markieren</translation>
    </message>
    <message>
        <source>Off: the mailbox is opened read-only and nothing in it changes</source>
        <translation>Aus: Das Postfach wird nur lesend geöffnet, und nichts darin ändert sich</translation>
    </message>
    <message>
        <source>List the attachments</source>
        <translation>Anhänge auflisten</translation>
    </message>
    <message>
        <source>Their names, types and sizes (never their content)</source>
        <translation>Ihre Namen, Typen und Größen (nie ihr Inhalt)</translation>
    </message>
    <message>
        <source>The field that names a message; message_id is unique</source>
        <translation>Das Feld, das eine Nachricht benennt; message_id ist eindeutig</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>Klasse</translation>
    </message>
    <message>
        <source>Used when the file has no manifest: the class whose attributes the columns are named after</source>
        <translation>Verwendet, wenn die Datei kein Manifest hat: die Klasse, nach deren Attributen die Spalten benannt sind</translation>
    </message>
    <message>
        <source>Manifest</source>
        <translation>Manifest</translation>
    </message>
    <message>
        <source>Named connection</source>
        <translation>Benannte Verbindung</translation>
    </message>
    <message>
        <source>Exponential 6.0.15 and later: a connection of Setup &gt; Data tables &gt; Connections instead of the options below</source>
        <translation>Exponential 6.0.15 und später: eine Verbindung aus Setup &gt; Datentabellen &gt; Verbindungen statt der Optionen unten</translation>
    </message>
    <message>
        <source>Database</source>
        <translation>Datenbank</translation>
    </message>
    <message>
        <source>MySQL or MariaDB</source>
        <translation>MySQL oder MariaDB</translation>
    </message>
    <message>
        <source>PostgreSQL</source>
        <translation>PostgreSQL</translation>
    </message>
    <message>
        <source>SQLite (a file on the server)</source>
        <translation>SQLite (eine Datei auf dem Server)</translation>
    </message>
    <message>
        <source>Oracle</source>
        <translation>Oracle</translation>
    </message>
    <message>
        <source>Microsoft SQL Server</source>
        <translation>Microsoft SQL Server</translation>
    </message>
    <message>
        <source>This site's database (read-only)</source>
        <translation>Die Datenbank dieser Site (nur lesend)</translation>
    </message>
    <message>
        <source>Host</source>
        <translation>Host</translation>
    </message>
    <message>
        <source>MySQL, PostgreSQL, Oracle and SQL Server: the database server (a private network only with [SqlSourceSettings] AllowedHosts[]). Oracle: empty when Database is a TNS name or an Easy Connect string</source>
        <translation>MySQL, PostgreSQL, Oracle und SQL Server: der Datenbankserver (ein privates Netz nur mit [SqlSourceSettings] AllowedHosts[]). Oracle: leer, wenn Datenbank ein TNS-Name oder ein Easy-Connect-String ist</translation>
    </message>
    <message>
        <source>0: the default port (3306, 5432, Oracle 1521, SQL Server 1433)</source>
        <translation>0: der Standardport (3306, 5432, Oracle 1521, SQL Server 1433)</translation>
    </message>
    <message>
        <source>The database name; SQLite: the file, inside one of [SourceSettings] AllowedPaths[]; Oracle: the service name, or without a host //host:port/service or a TNS name of [SqlSourceSettings] AllowedTnsNames[]</source>
        <translation>Der Datenbankname; SQLite: die Datei, innerhalb eines der [SourceSettings] AllowedPaths[]; Oracle: der Dienstname, oder ohne Host //host:port/service oder ein TNS-Name aus [SqlSourceSettings] AllowedTnsNames[]</translation>
    </message>
    <message>
        <source>A single SELECT (or WITH ... SELECT); each row is a record, its columns the fields. SQL Server: TOP and OFFSET ... FETCH page the rows</source>
        <translation>Ein einzelnes SELECT (oder WITH ... SELECT); jede Zeile ist ein Datensatz, ihre Spalten sind die Felder. SQL Server: TOP und OFFSET ... FETCH blättern durch die Zeilen</translation>
    </message>
    <message>
        <source>The column that names a row (an ID). Empty: the position in the result. Oracle gives unquoted column names in capitals</source>
        <translation>Die Spalte, die eine Zeile benennt (eine ID). Leer: die Position im Ergebnis. Oracle liefert Spaltennamen ohne Anführungszeichen in Großbuchstaben</translation>
    </message>
    <message>
        <source>Server or URI</source>
        <translation>Server oder URI</translation>
    </message>
    <message>
        <source>A host (mongo.example.org), host:port, or a mongodb:// or mongodb+srv:// URI without user and password (a private network only with [MongoDbSourceSettings] AllowedHosts[])</source>
        <translation>Ein Host (mongo.example.org), host:port oder eine URI mongodb:// oder mongodb+srv:// ohne Benutzer und Passwort (ein privates Netz nur mit [MongoDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Only with a host: 0 is the default port 27017</source>
        <translation>Nur mit einem Host: 0 ist der Standardport 27017</translation>
    </message>
    <message>
        <source>TLS</source>
        <translation>TLS</translation>
    </message>
    <message>
        <source>Encrypt the connection (certificates are verified); mongodb+srv:// always uses TLS</source>
        <translation>Die Verbindung verschlüsseln (Zertifikate werden geprüft); mongodb+srv:// verwendet immer TLS</translation>
    </message>
    <message>
        <source>Authentication database</source>
        <translation>Authentifizierungsdatenbank</translation>
    </message>
    <message>
        <source>The database the user is defined in; empty: the database of the URI, else admin</source>
        <translation>Die Datenbank, in der der Benutzer definiert ist; leer: die Datenbank der URI, sonst admin</translation>
    </message>
    <message>
        <source>Collection</source>
        <translation>Collection</translation>
    </message>
    <message>
        <source>Read</source>
        <translation>Lesen</translation>
    </message>
    <message>
        <source>A find (filter, projection, sort)</source>
        <translation>Ein find (Filter, Projektion, Sortierung)</translation>
    </message>
    <message>
        <source>An aggregation pipeline (reading stages only)</source>
        <translation>Eine Aggregations-Pipeline (nur lesende Stufen)</translation>
    </message>
    <message>
        <source>Pipeline (JSON)</source>
        <translation>Pipeline (JSON)</translation>
    </message>
    <message>
        <source>With an aggregation: a list of stages, e.g. [{"$match": {"status": "published"}}, {"$project": {"title": 1}}]. Only reading stages; $out, $merge, $function and the like are refused</source>
        <translation>Bei einer Aggregation: eine Liste von Stufen, z. B. [{"$match": {"status": "published"}}, {"$project": {"title": 1}}]. Nur lesende Stufen; $out, $merge, $function und Ähnliches werden abgelehnt</translation>
    </message>
    <message>
        <source>Filter (JSON)</source>
        <translation>Filter (JSON)</translation>
    </message>
    <message>
        <source>A query document, e.g. {"status": "published"}; Extended JSON for types: {"$oid": "..."}, {"$date": "2026-01-01T00:00:00Z"}. $where, $function and $accumulator are refused. Empty: every document</source>
        <translation>Ein Abfragedokument, z. B. {"status": "published"}; Extended JSON für Typen: {"$oid": "..."}, {"$date": "2026-01-01T00:00:00Z"}. $where, $function und $accumulator werden abgelehnt. Leer: jedes Dokument</translation>
    </message>
    <message>
        <source>Projection (JSON)</source>
        <translation>Projektion (JSON)</translation>
    </message>
    <message>
        <source>The fields to read, e.g. {"title": 1, "author.name": 1}; empty: all</source>
        <translation>Die zu lesenden Felder, z. B. {"title": 1, "author.name": 1}; leer: alle</translation>
    </message>
    <message>
        <source>Sort (JSON)</source>
        <translation>Sortierung (JSON)</translation>
    </message>
    <message>
        <source>e.g. {"published_at": -1}; needs the paging Skip and limit (paging by _id reads in _id order)</source>
        <translation>z. B. {"published_at": -1}; braucht das Blättern Skip und Limit (Blättern über _id liest in der Reihenfolge von _id)</translation>
    </message>
    <message>
        <source>By _id (each page after the last _id; fast, in _id order)</source>
        <translation>Über _id (jede Seite nach der letzten _id; schnell, in der Reihenfolge von _id)</translation>
    </message>
    <message>
        <source>Skip and limit (any sort order)</source>
        <translation>Skip und Limit (beliebige Sortierung)</translation>
    </message>
    <message>
        <source>One cursor read in batches (any order; the server keeps it open)</source>
        <translation>Ein Cursor, in Stapeln gelesen (beliebige Reihenfolge; der Server hält ihn offen)</translation>
    </message>
    <message>
        <source>Documents per page</source>
        <translation>Dokumente je Seite</translation>
    </message>
    <message>
        <source>Most documents</source>
        <translation>Höchstzahl an Dokumenten</translation>
    </message>
    <message>
        <source>0: every matching document</source>
        <translation>0: jedes passende Dokument</translation>
    </message>
    <message>
        <source>As a joined list (a, b); arrays of documents stay JSON</source>
        <translation>Als verbundene Liste (a, b); Arrays von Dokumenten bleiben JSON</translation>
    </message>
    <message>
        <source>Arrays as a joined list: what stands between the values</source>
        <translation>Arrays als verbundene Liste: was zwischen den Werten steht</translation>
    </message>
    <message>
        <source>Read from</source>
        <translation>Lesen von</translation>
    </message>
    <message>
        <source>The primary</source>
        <translation>Dem Primary</translation>
    </message>
    <message>
        <source>The primary, else a secondary</source>
        <translation>Dem Primary, sonst einem Secondary</translation>
    </message>
    <message>
        <source>A secondary, else the primary</source>
        <translation>Einem Secondary, sonst dem Primary</translation>
    </message>
    <message>
        <source>A secondary</source>
        <translation>Einem Secondary</translation>
    </message>
    <message>
        <source>The nearest member</source>
        <translation>Dem nächstgelegenen Mitglied</translation>
    </message>
    <message>
        <source>The field that names a document; _id is unique. A nested field by its dotted name (meta.sku)</source>
        <translation>Das Feld, das ein Dokument benennt; _id ist eindeutig. Ein verschachteltes Feld über seinen Namen mit Punkt (meta.sku)</translation>
    </message>
    <message>
        <source>Redis server</source>
        <translation>Redis-Server</translation>
    </message>
    <message>
        <source>e.g. redis.example.org (a private network only with [RedisSourceSettings] AllowedHosts[])</source>
        <translation>z. B. redis.example.org (ein privates Netz nur mit [RedisSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Encrypt the connection (the certificate is verified)</source>
        <translation>Die Verbindung verschlüsseln (das Zertifikat wird geprüft)</translation>
    </message>
    <message>
        <source>Database index</source>
        <translation>Datenbankindex</translation>
    </message>
    <message>
        <source>An ACL user (Redis 6 and later); empty: only the password</source>
        <translation>Ein ACL-Benutzer (Redis 6 und später); leer: nur das Passwort</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password; empty: no AUTH</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort; leer: kein AUTH</translation>
    </message>
    <message>
        <source>Key pattern</source>
        <translation>Schlüsselmuster</translation>
    </message>
    <message>
        <source>The keys to read, as SCAN MATCH reads it: product:*, user:?, news:[0-9]*</source>
        <translation>Die zu lesenden Schlüssel, wie SCAN MATCH es liest: product:*, user:?, news:[0-9]*</translation>
    </message>
    <message>
        <source>Types</source>
        <translation>Typen</translation>
    </message>
    <message>
        <source>The types of keys to read, separated by commas: hash, string, list, set, zset, stream, json (RedisJSON)</source>
        <translation>Die Typen der zu lesenden Schlüssel, durch Kommas getrennt: hash, string, list, set, zset, stream, json (RedisJSON)</translation>
    </message>
    <message>
        <source>JSON path</source>
        <translation>JSON-Pfad</translation>
    </message>
    <message>
        <source>RedisJSON keys: the path JSON.GET reads, $ (the whole document) or $.product; a legacy path (.product) works too</source>
        <translation>RedisJSON-Schlüssel: der Pfad, den JSON.GET liest, $ (das ganze Dokument) oder $.product; ein alter Pfad (.product) funktioniert auch</translation>
    </message>
    <message>
        <source>Keys per SCAN</source>
        <translation>Schlüssel je SCAN</translation>
    </message>
    <message>
        <source>The COUNT of each SCAN: a hint how many keys the server looks at per step</source>
        <translation>Der COUNT jedes SCAN: ein Hinweis, wie viele Schlüssel der Server je Schritt ansieht</translation>
    </message>
    <message>
        <source>Most values per key</source>
        <translation>Höchstzahl an Werten je Schlüssel</translation>
    </message>
    <message>
        <source>Lists, sets, sorted sets and streams: how many values (entries) of a key are read</source>
        <translation>Lists, Sets, Sorted Sets und Streams: wie viele Werte (Einträge) eines Schlüssels gelesen werden</translation>
    </message>
    <message>
        <source>Most keys</source>
        <translation>Höchstzahl an Schlüsseln</translation>
    </message>
    <message>
        <source>0: every matching key</source>
        <translation>0: jeder passende Schlüssel</translation>
    </message>
    <message>
        <source>Decode JSON strings</source>
        <translation>JSON-Strings dekodieren</translation>
    </message>
    <message>
        <source>A string value that is a JSON object or list is read as its fields (value.title)</source>
        <translation>Ein String-Wert, der ein JSON-Objekt oder eine JSON-Liste ist, wird als seine Felder gelesen (value.title)</translation>
    </message>
    <message>
        <source>As lists (values.0, values.1 ...)</source>
        <translation>Als Listen (values.0, values.1 ...)</translation>
    </message>
    <message>
        <source>As a joined list (a, b); stream entries stay JSON</source>
        <translation>Als verbundene Liste (a, b); Stream-Einträge bleiben JSON</translation>
    </message>
    <message>
        <source>The field that names a record; _key is the key itself</source>
        <translation>Das Feld, das einen Datensatz benennt; _key ist der Schlüssel selbst</translation>
    </message>
    <message>
        <source>The address of CouchDB, e.g. https://couch.example.org:6984 (a private network only with [CouchDbSourceSettings] AllowedHosts[])</source>
        <translation>Die Adresse von CouchDB, z. B. https://couch.example.org:6984 (ein privates Netz nur mit [CouchDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>The name of the database: small letters, digits and _ $ ( ) + -; no path</source>
        <translation>Der Name der Datenbank: Kleinbuchstaben, Ziffern und _ $ ( ) + -; kein Pfad</translation>
    </message>
    <message>
        <source>User and password (basic)</source>
        <translation>Benutzer und Passwort (Basic)</translation>
    </message>
    <message>
        <source>User and password (cookie session)</source>
        <translation>Benutzer und Passwort (Cookie-Sitzung)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort</translation>
    </message>
    <message>
        <source>All documents (_all_docs)</source>
        <translation>Alle Dokumente (_all_docs)</translation>
    </message>
    <message>
        <source>A view (_design/&lt;doc&gt;/_view/&lt;view&gt;)</source>
        <translation>Eine View (_design/&lt;doc&gt;/_view/&lt;view&gt;)</translation>
    </message>
    <message>
        <source>A Mango query (_find)</source>
        <translation>Eine Mango-Abfrage (_find)</translation>
    </message>
    <message>
        <source>Design document</source>
        <translation>Design-Dokument</translation>
    </message>
    <message>
        <source>With a view: the name of the design document (news or _design/news)</source>
        <translation>Bei einer View: der Name des Design-Dokuments (news oder _design/news)</translation>
    </message>
    <message>
        <source>View</source>
        <translation>View</translation>
    </message>
    <message>
        <source>With a view: its name</source>
        <translation>Bei einer View: ihr Name</translation>
    </message>
    <message>
        <source>With the documents</source>
        <translation>Mit den Dokumenten</translation>
    </message>
    <message>
        <source>All documents and views: read each document (else only the ID, the revision or the key and value of the row)</source>
        <translation>Alle Dokumente und Views: jedes Dokument lesen (sonst nur die ID, die Revision oder Schlüssel und Wert der Zeile)</translation>
    </message>
    <message>
        <source>Reduce</source>
        <translation>Reduce</translation>
    </message>
    <message>
        <source>A view with a reduce function: read its results grouped by key (no documents)</source>
        <translation>Eine View mit Reduce-Funktion: ihre Ergebnisse nach Schlüssel gruppiert lesen (keine Dokumente)</translation>
    </message>
    <message>
        <source>Keys (JSON list)</source>
        <translation>Schlüssel (JSON-Liste)</translation>
    </message>
    <message>
        <source>All documents and views: only these keys (IDs), e.g. ["a-1", "a-2"]</source>
        <translation>Alle Dokumente und Views: nur diese Schlüssel (IDs), z. B. ["a-1", "a-2"]</translation>
    </message>
    <message>
        <source>Start key (JSON)</source>
        <translation>Startschlüssel (JSON)</translation>
    </message>
    <message>
        <source>All documents and views: the first key, as JSON ("2026-01-01", ["news", 1])</source>
        <translation>Alle Dokumente und Views: der erste Schlüssel, als JSON ("2026-01-01", ["news", 1])</translation>
    </message>
    <message>
        <source>End key (JSON)</source>
        <translation>Endschlüssel (JSON)</translation>
    </message>
    <message>
        <source>All documents and views: the last key, as JSON</source>
        <translation>Alle Dokumente und Views: der letzte Schlüssel, als JSON</translation>
    </message>
    <message>
        <source>Selector (JSON)</source>
        <translation>Selektor (JSON)</translation>
    </message>
    <message>
        <source>Mango: a selector, e.g. {"type": "article", "year": {"$gte": 2025}}; empty: every document</source>
        <translation>Mango: ein Selektor, z. B. {"type": "article", "year": {"$gte": 2025}}; leer: jedes Dokument</translation>
    </message>
    <message>
        <source>Mango: the fields to read, one per line (author.name); empty: all</source>
        <translation>Mango: die zu lesenden Felder, eines je Zeile (author.name); leer: alle</translation>
    </message>
    <message>
        <source>Mango: e.g. [{"published_at": "desc"}] (needs an index on the fields)</source>
        <translation>Mango: z. B. [{"published_at": "desc"}] (braucht einen Index auf den Feldern)</translation>
    </message>
    <message>
        <source>Design documents too</source>
        <translation>Auch Design-Dokumente</translation>
    </message>
    <message>
        <source>All documents: read the design documents (_design/...) as well</source>
        <translation>Alle Dokumente: auch die Design-Dokumente (_design/...) lesen</translation>
    </message>
    <message>
        <source>0: every document</source>
        <translation>0: jedes Dokument</translation>
    </message>
    <message>
        <source>As a joined list (a, b); arrays of objects stay JSON</source>
        <translation>Als verbundene Liste (a, b); Arrays von Objekten bleiben JSON</translation>
    </message>
    <message>
        <source>The field that names a document; _id is its ID</source>
        <translation>Das Feld, das ein Dokument benennt; _id ist seine ID</translation>
    </message>
    <message>
        <source>Query service</source>
        <translation>Query-Dienst</translation>
    </message>
    <message>
        <source>The address of the Query service: https://cb.example.org:18093 (TLS) or http://cb.example.org:8093 (a private network only with [CouchbaseSourceSettings] AllowedHosts[])</source>
        <translation>Die Adresse des Query-Dienstes: https://cb.example.org:18093 (TLS) oder http://cb.example.org:8093 (ein privates Netz nur mit [CouchbaseSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>A user with the role Query Select on the keyspaces</source>
        <translation>Ein Benutzer mit der Rolle Query Select auf den Keyspaces</translation>
    </message>
    <message>
        <source>Query (SQL++)</source>
        <translation>Abfrage (SQL++)</translation>
    </message>
    <message>
        <source>One SELECT (or WITH ... SELECT) without parameters, e.g. SELECT p.* FROM shop p WHERE p.active = true ORDER BY META(p).id</source>
        <translation>Ein SELECT (oder WITH ... SELECT) ohne Parameter, z. B. SELECT p.* FROM shop p WHERE p.active = true ORDER BY META(p).id</translation>
    </message>
    <message>
        <source>Query context</source>
        <translation>Abfragekontext</translation>
    </message>
    <message>
        <source>The bucket and scope the names of the query are read in (Couchbase 7): default:shop.inventory. Empty: none</source>
        <translation>Bucket und Scope, in denen die Namen der Abfrage gelesen werden (Couchbase 7): default:shop.inventory. Leer: keiner</translation>
    </message>
    <message>
        <source>LIMIT and OFFSET (give the query an ORDER BY)</source>
        <translation>LIMIT und OFFSET (geben Sie der Abfrage ein ORDER BY)</translation>
    </message>
    <message>
        <source>After the last value of a unique field (fast)</source>
        <translation>Nach dem letzten Wert eines eindeutigen Felds (schnell)</translation>
    </message>
    <message>
        <source>One request (the query's own LIMIT)</source>
        <translation>Eine Anfrage (das eigene LIMIT der Abfrage)</translation>
    </message>
    <message>
        <source>Keyset field</source>
        <translation>Keyset-Feld</translation>
    </message>
    <message>
        <source>With the keyset paging: a unique field of the results (id, sku, meta.id)</source>
        <translation>Beim Keyset-Blättern: ein eindeutiges Feld der Ergebnisse (id, sku, meta.id)</translation>
    </message>
    <message>
        <source>Consistency</source>
        <translation>Konsistenz</translation>
    </message>
    <message>
        <source>As the index stands (fast)</source>
        <translation>Wie der Index steht (schnell)</translation>
    </message>
    <message>
        <source>Every change before the run (waits for the index)</source>
        <translation>Jede Änderung vor dem Lauf (wartet auf den Index)</translation>
    </message>
    <message>
        <source>Results per request</source>
        <translation>Ergebnisse je Anfrage</translation>
    </message>
    <message>
        <source>Most results</source>
        <translation>Höchstzahl an Ergebnissen</translation>
    </message>
    <message>
        <source>0: every result</source>
        <translation>0: jedes Ergebnis</translation>
    </message>
    <message>
        <source>The field that names a result (select META(x).id AS id for the document ID)</source>
        <translation>Das Feld, das ein Ergebnis benennt (wählen Sie META(x).id AS id für die Dokument-ID)</translation>
    </message>
    <message>
        <source>Account endpoint</source>
        <translation>Konto-Endpunkt</translation>
    </message>
    <message>
        <source>The URI of the account: https://&lt;account&gt;.documents.azure.com (a private network only with [CosmosDbSourceSettings] AllowedHosts[])</source>
        <translation>Die URI des Kontos: https://&lt;account&gt;.documents.azure.com (ein privates Netz nur mit [CosmosDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Container</source>
        <translation>Container</translation>
    </message>
    <message>
        <source>Account key (signs each request; use the read-only key)</source>
        <translation>Kontoschlüssel (signiert jede Anfrage; verwenden Sie den Nur-Lese-Schlüssel)</translation>
    </message>
    <message>
        <source>Resource token (permission Read)</source>
        <translation>Ressourcentoken (Berechtigung Read)</translation>
    </message>
    <message>
        <source>Key or token (name of a secret)</source>
        <translation>Schlüssel oder Token (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE with the read-only key (base64) or the resource token, never the key</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE mit dem Nur-Lese-Schlüssel (base64) oder dem Ressourcentoken, nie der Schlüssel</translation>
    </message>
    <message>
        <source>One SELECT without parameters, e.g. SELECT c.id, c.title FROM c WHERE c.status = 'published'</source>
        <translation>Ein SELECT ohne Parameter, z. B. SELECT c.id, c.title FROM c WHERE c.status = 'published'</translation>
    </message>
    <message>
        <source>Across partitions</source>
        <translation>Über Partitionen hinweg</translation>
    </message>
    <message>
        <source>Query every partition (without a partition key). ORDER BY, TOP, DISTINCT and aggregates across partitions are refused by the gateway: give a partition key for them</source>
        <translation>Jede Partition abfragen (ohne Partitionsschlüssel). ORDER BY, TOP, DISTINCT und Aggregate über Partitionen hinweg lehnt das Gateway ab: Geben Sie dafür einen Partitionsschlüssel an</translation>
    </message>
    <message>
        <source>Partition key (JSON)</source>
        <translation>Partitionsschlüssel (JSON)</translation>
    </message>
    <message>
        <source>Only the items of one partition: its key value as JSON ("tea", 42)</source>
        <translation>Nur die Elemente einer Partition: ihr Schlüsselwert als JSON ("tea", 42)</translation>
    </message>
    <message>
        <source>Items per page</source>
        <translation>Elemente je Seite</translation>
    </message>
    <message>
        <source>Most items</source>
        <translation>Höchstzahl an Elementen</translation>
    </message>
    <message>
        <source>0: every item</source>
        <translation>0: jedes Element</translation>
    </message>
    <message>
        <source>System properties</source>
        <translation>Systemeigenschaften</translation>
    </message>
    <message>
        <source>Keep _rid, _self, _etag and _attachments (_ts, the time of the last change, is always kept)</source>
        <translation>_rid, _self, _etag und _attachments behalten (_ts, die Zeit der letzten Änderung, bleibt immer erhalten)</translation>
    </message>
    <message>
        <source>The field that names an item; id is unique in a partition</source>
        <translation>Das Feld, das ein Element benennt; id ist innerhalb einer Partition eindeutig</translation>
    </message>
    <message>
        <source>Region</source>
        <translation>Region</translation>
    </message>
    <message>
        <source>The AWS region of the table: eu-central-1, us-east-1</source>
        <translation>Die AWS-Region der Tabelle: eu-central-1, us-east-1</translation>
    </message>
    <message>
        <source>Endpoint</source>
        <translation>Endpunkt</translation>
    </message>
    <message>
        <source>Empty: https://dynamodb.&lt;region&gt;.amazonaws.com. Else DynamoDB Local or a compatible service (http://localhost:8000, a private network only with [DynamoDbSourceSettings] AllowedHosts[])</source>
        <translation>Leer: https://dynamodb.&lt;region&gt;.amazonaws.com. Sonst DynamoDB Local oder ein kompatibler Dienst (http://localhost:8000, ein privates Netz nur mit [DynamoDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Access key ID</source>
        <translation>Zugriffsschlüssel-ID</translation>
    </message>
    <message>
        <source>The access key ID of an IAM user or role that may only read the table (AKIA...)</source>
        <translation>Die Zugriffsschlüssel-ID eines IAM-Benutzers oder einer IAM-Rolle, die die Tabelle nur lesen darf (AKIA...)</translation>
    </message>
    <message>
        <source>Secret access key (name of a secret)</source>
        <translation>Geheimer Zugriffsschlüssel (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the key itself</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, nie der Schlüssel selbst</translation>
    </message>
    <message>
        <source>Session token (name of a secret)</source>
        <translation>Sitzungstoken (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>Only with temporary credentials (STS): the name of the secret with the session token</source>
        <translation>Nur mit temporären Zugangsdaten (STS): der Name des Geheimnisses mit dem Sitzungstoken</translation>
    </message>
    <message>
        <source>Table</source>
        <translation>Tabelle</translation>
    </message>
    <message>
        <source>The name of the table</source>
        <translation>Der Name der Tabelle</translation>
    </message>
    <message>
        <source>Operation</source>
        <translation>Operation</translation>
    </message>
    <message>
        <source>Scan (every item, or those of the filter)</source>
        <translation>Scan (jedes Element oder die des Filters)</translation>
    </message>
    <message>
        <source>Query (the items of a partition key)</source>
        <translation>Query (die Elemente eines Partitionsschlüssels)</translation>
    </message>
    <message>
        <source>A global or local secondary index to read instead of the table (IndexName)</source>
        <translation>Ein globaler oder lokaler sekundärer Index, der statt der Tabelle gelesen wird (IndexName)</translation>
    </message>
    <message>
        <source>Key condition</source>
        <translation>Schlüsselbedingung</translation>
    </message>
    <message>
        <source>Query only (required there): KeyConditionExpression, e.g. customer_id = :c AND created_at &gt; :d</source>
        <translation>Nur bei Query (dort Pflicht): KeyConditionExpression, z. B. customer_id = :c AND created_at &gt; :d</translation>
    </message>
    <message>
        <source>Filter</source>
        <translation>Filter</translation>
    </message>
    <message>
        <source>FilterExpression, e.g. #s = :p AND price &lt; :max</source>
        <translation>FilterExpression, z. B. #s = :p AND price &lt; :max</translation>
    </message>
    <message>
        <source>Attribute names</source>
        <translation>Attributnamen</translation>
    </message>
    <message>
        <source>ExpressionAttributeNames as JSON: {"#s": "status"}</source>
        <translation>ExpressionAttributeNames als JSON: {"#s": "status"}</translation>
    </message>
    <message>
        <source>Attribute values</source>
        <translation>Attributwerte</translation>
    </message>
    <message>
        <source>ExpressionAttributeValues as JSON in the DynamoDB form: {":p": {"S": "published"}, ":max": {"N": "20"}}</source>
        <translation>ExpressionAttributeValues als JSON in der Form von DynamoDB: {":p": {"S": "published"}, ":max": {"N": "20"}}</translation>
    </message>
    <message>
        <source>Attributes</source>
        <translation>Attribute</translation>
    </message>
    <message>
        <source>ProjectionExpression: the attributes to read, e.g. sku, title, price, #s. Empty: all</source>
        <translation>ProjectionExpression: die zu lesenden Attribute, z. B. sku, title, price, #s. Leer: alle</translation>
    </message>
    <message>
        <source>Strongly consistent read</source>
        <translation>Streng konsistentes Lesen</translation>
    </message>
    <message>
        <source>ConsistentRead (not on a global secondary index; costs twice the read capacity)</source>
        <translation>ConsistentRead (nicht bei einem globalen sekundären Index; kostet die doppelte Lesekapazität)</translation>
    </message>
    <message>
        <source>Ascending sort key</source>
        <translation>Aufsteigender Sortierschlüssel</translation>
    </message>
    <message>
        <source>Query only: ScanIndexForward; no gives the items by descending sort key</source>
        <translation>Nur bei Query: ScanIndexForward; nein liefert die Elemente nach absteigendem Sortierschlüssel</translation>
    </message>
    <message>
        <source>Limit: the items DynamoDB evaluates per request (an answer is also at most 1 MB)</source>
        <translation>Limit: die Elemente, die DynamoDB je Anfrage auswertet (eine Antwort ist außerdem höchstens 1 MB groß)</translation>
    </message>
    <message>
        <source>0: all</source>
        <translation>0: alle</translation>
    </message>
    <message>
        <source>Sets (SS, NS, BS)</source>
        <translation>Sets (SS, NS, BS)</translation>
    </message>
    <message>
        <source>A joined list (a, b)</source>
        <translation>Eine verbundene Liste (a, b)</translation>
    </message>
    <message>
        <source>JSON (["a","b"])</source>
        <translation>JSON (["a","b"])</translation>
    </message>
    <message>
        <source>Separator</source>
        <translation>Trennzeichen</translation>
    </message>
    <message>
        <source>What stands between the values of a joined set</source>
        <translation>Was zwischen den Werten eines verbundenen Sets steht</translation>
    </message>
    <message>
        <source>The attribute that names an item (its partition key, or partition and sort key joined in a transform). Empty: the position</source>
        <translation>Das Attribut, das ein Element benennt (sein Partitionsschlüssel, oder Partitions- und Sortierschlüssel in einer Umwandlung verbunden). Leer: die Position</translation>
    </message>
    <message>
        <source>Project ID</source>
        <translation>Projekt-ID</translation>
    </message>
    <message>
        <source>The Google Cloud project of the database (example-project)</source>
        <translation>Das Google-Cloud-Projekt der Datenbank (example-project)</translation>
    </message>
    <message>
        <source>The database ID; (default) for the default database</source>
        <translation>Die Datenbank-ID; (default) für die Standarddatenbank</translation>
    </message>
    <message>
        <source>Service account key (name of a secret)</source>
        <translation>Schlüssel des Dienstkontos (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret whose value is the JSON key file of a service account with the role roles/datastore.viewer</source>
        <translation>Der Name eines Geheimnisses, dessen Wert die JSON-Schlüsseldatei eines Dienstkontos mit der Rolle roles/datastore.viewer ist</translation>
    </message>
    <message>
        <source>Scope of the token</source>
        <translation>Bereich des Tokens</translation>
    </message>
    <message>
        <source>cloud-platform.read-only</source>
        <translation>cloud-platform.read-only</translation>
    </message>
    <message>
        <source>datastore (when the read-only scope is refused; the role keeps it read-only)</source>
        <translation>datastore (wenn der Nur-Lese-Bereich abgelehnt wird; die Rolle hält es nur lesend)</translation>
    </message>
    <message>
        <source>Reading</source>
        <translation>Lesen</translation>
    </message>
    <message>
        <source>A structured query (runQuery)</source>
        <translation>Eine strukturierte Abfrage (runQuery)</translation>
    </message>
    <message>
        <source>The list of the collection (no filter)</source>
        <translation>Die Liste der Collection (ohne Filter)</translation>
    </message>
    <message>
        <source>A collection (articles) or a subcollection by its path (customers/c-1001/orders)</source>
        <translation>Eine Collection (articles) oder eine Subcollection über ihren Pfad (customers/c-1001/orders)</translation>
    </message>
    <message>
        <source>Collection group</source>
        <translation>Collection-Gruppe</translation>
    </message>
    <message>
        <source>Query only: every collection of this name below the path (allDescendants)</source>
        <translation>Nur bei Abfrage: jede Collection dieses Namens unterhalb des Pfads (allDescendants)</translation>
    </message>
    <message>
        <source>Query only: the where of a structured query, e.g. {"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}</source>
        <translation>Nur bei Abfrage: das where einer strukturierten Abfrage, z. B. {"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}</translation>
    </message>
    <message>
        <source>Order</source>
        <translation>Reihenfolge</translation>
    </message>
    <message>
        <source>Fields with :asc or :desc, comma separated: published_at:desc. The document name is always the last order</source>
        <translation>Felder mit :asc oder :desc, durch Kommas getrennt: published_at:desc. Der Dokumentname ist immer die letzte Sortierung</translation>
    </message>
    <message>
        <source>The fields to read, one per line (select). Empty: all</source>
        <translation>Die zu lesenden Felder, eines je Zeile (select). Leer: alle</translation>
    </message>
    <message>
        <source>Cursor (startAt the last document)</source>
        <translation>Cursor (startAt beim letzten Dokument)</translation>
    </message>
    <message>
        <source>Offset (slower for large collections)</source>
        <translation>Offset (langsamer bei großen Collections)</translation>
    </message>
    <message>
        <source>A joined list (a, b); an array of maps stays JSON</source>
        <translation>Eine verbundene Liste (a, b); ein Array von Maps bleibt JSON</translation>
    </message>
    <message>
        <source>_id is the document ID; _path its full path (unique also in a collection group)</source>
        <translation>_id ist die Dokument-ID; _path ihr vollständiger Pfad (auch in einer Collection-Gruppe eindeutig)</translation>
    </message>
    <message>
        <source>The project that runs (and pays for) the query job</source>
        <translation>Das Projekt, das den Abfrage-Job ausführt (und bezahlt)</translation>
    </message>
    <message>
        <source>The name of a secret whose value is the JSON key file of a service account with roles/bigquery.jobUser and roles/bigquery.dataViewer</source>
        <translation>Der Name eines Geheimnisses, dessen Wert die JSON-Schlüsseldatei eines Dienstkontos mit roles/bigquery.jobUser und roles/bigquery.dataViewer ist</translation>
    </message>
    <message>
        <source>Location</source>
        <translation>Standort</translation>
    </message>
    <message>
        <source>The location of the datasets (EU, US, europe-west3). Empty: BigQuery finds it</source>
        <translation>Der Standort der Datasets (EU, US, europe-west3). Leer: BigQuery findet ihn</translation>
    </message>
    <message>
        <source>One SELECT or WITH in GoogleSQL (standard SQL); DML, DDL, scripts, EXPORT DATA and CALL are refused</source>
        <translation>Ein SELECT oder WITH in GoogleSQL (Standard-SQL); DML, DDL, Skripte, EXPORT DATA und CALL werden abgelehnt</translation>
    </message>
    <message>
        <source>Most bytes billed</source>
        <translation>Höchstzahl abgerechneter Bytes</translation>
    </message>
    <message>
        <source>A query that would scan more fails. 0: [GoogleCloudSourceSettings] MaximumBytesBilled</source>
        <translation>Eine Abfrage, die mehr durchsuchen würde, schlägt fehl. 0: [GoogleCloudSourceSettings] MaximumBytesBilled</translation>
    </message>
    <message>
        <source>Rows per request (maxResults)</source>
        <translation>Zeilen je Anfrage (maxResults)</translation>
    </message>
    <message>
        <source>Most rows</source>
        <translation>Höchstzahl an Zeilen</translation>
    </message>
    <message>
        <source>The column that names a row. Empty: the position</source>
        <translation>Die Spalte, die eine Zeile benennt. Leer: die Position</translation>
    </message>
    <message>
        <source>Database address</source>
        <translation>Datenbankadresse</translation>
    </message>
    <message>
        <source>https://&lt;name&gt;.firebaseio.com or https://&lt;name&gt;.&lt;region&gt;.firebasedatabase.app (the emulator only with [GoogleCloudSourceSettings] AllowedHosts[])</source>
        <translation>https://&lt;name&gt;.firebaseio.com oder https://&lt;name&gt;.&lt;region&gt;.firebasedatabase.app (der Emulator nur mit [GoogleCloudSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Path</source>
        <translation>Pfad</translation>
    </message>
    <message>
        <source>The node whose children are the records: articles, catalog/products. Empty: the root</source>
        <translation>Der Knoten, dessen Kinder die Datensätze sind: articles, catalog/products. Leer: die Wurzel</translation>
    </message>
    <message>
        <source>Service account (Google access token)</source>
        <translation>Dienstkonto (Google-Zugriffstoken)</translation>
    </message>
    <message>
        <source>Database secret (legacy)</source>
        <translation>Datenbank-Secret (alt)</translation>
    </message>
    <message>
        <source>None (public read rules)</source>
        <translation>Keine (öffentliche Leseregeln)</translation>
    </message>
    <message>
        <source>With a service account: the name of a secret whose value is its JSON key file (role roles/firebasedatabase.viewer)</source>
        <translation>Mit einem Dienstkonto: der Name eines Geheimnisses, dessen Wert seine JSON-Schlüsseldatei ist (Rolle roles/firebasedatabase.viewer)</translation>
    </message>
    <message>
        <source>Database secret (name of a secret)</source>
        <translation>Datenbank-Secret (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>With a database secret: the name of the secret that holds it</source>
        <translation>Mit einem Datenbank-Secret: der Name des Geheimnisses, das es enthält</translation>
    </message>
    <message>
        <source>Keys first (shallow)</source>
        <translation>Zuerst die Schlüssel (shallow)</translation>
    </message>
    <message>
        <source>Read the keys first (shallow=true), then the children a page at a time: for a big tree</source>
        <translation>Zuerst die Schlüssel lesen (shallow=true), dann die Kinder seitenweise: für einen großen Baum</translation>
    </message>
    <message>
        <source>Children per request</source>
        <translation>Kinder je Anfrage</translation>
    </message>
    <message>
        <source>Most children</source>
        <translation>Höchstzahl an Kindern</translation>
    </message>
    <message>
        <source>_key is the key of the child</source>
        <translation>_key ist der Schlüssel des Kindes</translation>
    </message>
    <message>
        <source>Contact points</source>
        <translation>Kontaktpunkte</translation>
    </message>
    <message>
        <source>One or more hosts, comma separated, each with an optional :port (cassandra1.example.org, cassandra2.example.org:9142); the first that answers is used</source>
        <translation>Ein oder mehrere Hosts, durch Kommas getrennt, jeder mit optionalem :port (cassandra1.example.org, cassandra2.example.org:9142); der erste, der antwortet, wird verwendet</translation>
    </message>
    <message>
        <source>Empty: no authentication</source>
        <translation>Leer: keine Authentifizierung</translation>
    </message>
    <message>
        <source>Keyspace</source>
        <translation>Keyspace</translation>
    </message>
    <message>
        <source>Empty: the query names its tables with the keyspace (shop.products)</source>
        <translation>Leer: Die Abfrage benennt ihre Tabellen mit dem Keyspace (shop.products)</translation>
    </message>
    <message>
        <source>One CQL SELECT; INSERT, UPDATE, DELETE, BATCH, TRUNCATE, CREATE, ALTER, DROP, GRANT and USE are refused</source>
        <translation>Ein CQL-SELECT; INSERT, UPDATE, DELETE, BATCH, TRUNCATE, CREATE, ALTER, DROP, GRANT und USE werden abgelehnt</translation>
    </message>
    <message>
        <source>Rows per page</source>
        <translation>Zeilen je Seite</translation>
    </message>
    <message>
        <source>Lists and sets</source>
        <translation>Lists und Sets</translation>
    </message>
    <message>
        <source>Server</source>
        <translation>Server</translation>
    </message>
    <message>
        <source>Neo4j</source>
        <translation>Neo4j</translation>
    </message>
    <message>
        <source>Memgraph (Bolt only)</source>
        <translation>Memgraph (nur Bolt)</translation>
    </message>
    <message>
        <source>Address</source>
        <translation>Adresse</translation>
    </message>
    <message>
        <source>https://graph.example.org:7473 (HTTP) or bolt://graph.example.org:7687, bolt+s://, neo4j://, neo4j+s:// (Bolt); a private network only with [Neo4jSourceSettings] AllowedHosts[]</source>
        <translation>https://graph.example.org:7473 (HTTP) oder bolt://graph.example.org:7687, bolt+s://, neo4j://, neo4j+s:// (Bolt); ein privates Netz nur mit [Neo4jSourceSettings] AllowedHosts[]</translation>
    </message>
    <message>
        <source>Interface</source>
        <translation>Schnittstelle</translation>
    </message>
    <message>
        <source>By the address (http: transactional endpoint, bolt: Bolt)</source>
        <translation>Nach der Adresse (http: transaktionaler Endpunkt, bolt: Bolt)</translation>
    </message>
    <message>
        <source>HTTP transactional endpoint (/db/&lt;database&gt;/tx/commit)</source>
        <translation>Transaktionaler HTTP-Endpunkt (/db/&lt;database&gt;/tx/commit)</translation>
    </message>
    <message>
        <source>Query API (/db/&lt;database&gt;/query/v2, Neo4j 5.19 and later)</source>
        <translation>Query API (/db/&lt;database&gt;/query/v2, Neo4j 5.19 und später)</translation>
    </message>
    <message>
        <source>Bolt</source>
        <translation>Bolt</translation>
    </message>
    <message>
        <source>The database; empty: neo4j over HTTP, the default database over Bolt. Never system</source>
        <translation>Die Datenbank; leer: neo4j über HTTP, die Standarddatenbank über Bolt. Nie system</translation>
    </message>
    <message>
        <source>Cypher query</source>
        <translation>Cypher-Abfrage</translation>
    </message>
    <message>
        <source>A reading query: MATCH (a:Article) RETURN a ORDER BY a.id. Clauses and procedures that write are refused</source>
        <translation>Eine lesende Abfrage: MATCH (a:Article) RETURN a ORDER BY a.id. Schreibende Klauseln und Prozeduren werden abgelehnt</translation>
    </message>
    <message>
        <source>Parameters</source>
        <translation>Parameter</translation>
    </message>
    <message>
        <source>A JSON object of the $parameters of the query</source>
        <translation>Ein JSON-Objekt der $parameters der Abfrage</translation>
    </message>
    <message>
        <source>Rows per request (HTTP: LIMIT, Bolt: PULL n)</source>
        <translation>Zeilen je Anfrage (HTTP: LIMIT, Bolt: PULL n)</translation>
    </message>
    <message>
        <source>Automatic (SKIP and LIMIT over HTTP when the query allows it)</source>
        <translation>Automatisch (SKIP und LIMIT über HTTP, wenn die Abfrage es erlaubt)</translation>
    </message>
    <message>
        <source>None (one request, the query as it is)</source>
        <translation>Keines (eine Anfrage, die Abfrage, wie sie ist)</translation>
    </message>
    <message>
        <source>The field that names a row (id, _element_id)</source>
        <translation>Das Feld, das eine Zeile benennt (id, _element_id)</translation>
    </message>
    <message>
        <source>The address of the server or coordinator, e.g. https://arango.example.org:8529 (a private network only with [ArangoDbSourceSettings] AllowedHosts[])</source>
        <translation>Die Adresse des Servers oder Koordinators, z. B. https://arango.example.org:8529 (ein privates Netz nur mit [ArangoDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>The database name</source>
        <translation>Der Datenbankname</translation>
    </message>
    <message>
        <source>JWT (bearer)</source>
        <translation>JWT (Bearer)</translation>
    </message>
    <message>
        <source>JWT (name of a secret)</source>
        <translation>JWT (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>With JWT: the name of the secret with the token</source>
        <translation>Bei JWT: der Name des Geheimnisses mit dem Token</translation>
    </message>
    <message>
        <source>AQL query</source>
        <translation>AQL-Abfrage</translation>
    </message>
    <message>
        <source>A reading query: FOR a IN articles FILTER a.status == @status RETURN a</source>
        <translation>Eine lesende Abfrage: FOR a IN articles FILTER a.status == @status RETURN a</translation>
    </message>
    <message>
        <source>Bind parameters</source>
        <translation>Bind-Parameter</translation>
    </message>
    <message>
        <source>A JSON object of the @parameters of the query</source>
        <translation>Ein JSON-Objekt der @parameters der Abfrage</translation>
    </message>
    <message>
        <source>Batch size</source>
        <translation>Stapelgröße</translation>
    </message>
    <message>
        <source>Results per batch of the cursor</source>
        <translation>Ergebnisse je Stapel des Cursors</translation>
    </message>
    <message>
        <source>Streaming cursor</source>
        <translation>Streaming-Cursor</translation>
    </message>
    <message>
        <source>Yes: the server computes the results batch by batch (options.stream), which keeps its memory low</source>
        <translation>Ja: Der Server berechnet die Ergebnisse Stapel für Stapel (options.stream), was seinen Speicherbedarf niedrig hält</translation>
    </message>
    <message>
        <source>The field that names a result</source>
        <translation>Das Feld, das ein Ergebnis benennt</translation>
    </message>
    <message>
        <source>The HTTP interface, e.g. https://clickhouse.example.org:8443 (a private network only with [ClickHouseSourceSettings] AllowedHosts[])</source>
        <translation>Die HTTP-Schnittstelle, z. B. https://clickhouse.example.org:8443 (ein privates Netz nur mit [ClickHouseSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password. Empty: no password</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort. Leer: kein Passwort</translation>
    </message>
    <message>
        <source>A single SELECT (or WITH ... SELECT), without FORMAT and SETTINGS</source>
        <translation>Ein einzelnes SELECT (oder WITH ... SELECT), ohne FORMAT und SETTINGS</translation>
    </message>
    <message>
        <source>Read-only level</source>
        <translation>Stufe von readonly</translation>
    </message>
    <message>
        <source>2: read-only, the source may send its time and row limits</source>
        <translation>2: nur lesend, die Quelle darf ihre Zeit- und Zeilengrenzen senden</translation>
    </message>
    <message>
        <source>1: read-only, no settings sent (the limits of the user's profile apply)</source>
        <translation>1: nur lesend, es werden keine Einstellungen gesendet (es gelten die Grenzen des Benutzerprofils)</translation>
    </message>
    <message>
        <source>Rows per request (LIMIT ... OFFSET ...)</source>
        <translation>Zeilen je Anfrage (LIMIT ... OFFSET ...)</translation>
    </message>
    <message>
        <source>LIMIT and OFFSET around the query</source>
        <translation>LIMIT und OFFSET um die Abfrage herum</translation>
    </message>
    <message>
        <source>None (one request)</source>
        <translation>Keines (eine Anfrage)</translation>
    </message>
    <message>
        <source>The column that names a row</source>
        <translation>Die Spalte, die eine Zeile benennt</translation>
    </message>
    <message>
        <source>Account identifier</source>
        <translation>Kontobezeichner</translation>
    </message>
    <message>
        <source>orgname-accountname (or the account locator with its region, xy12345.eu-central-1)</source>
        <translation>orgname-accountname (oder der Account Locator mit seiner Region, xy12345.eu-central-1)</translation>
    </message>
    <message>
        <source>Empty: https://&lt;account&gt;.snowflakecomputing.com; a private link address only with [SnowflakeSourceSettings] AllowedHosts[]</source>
        <translation>Leer: https://&lt;account&gt;.snowflakecomputing.com; eine Private-Link-Adresse nur mit [SnowflakeSourceSettings] AllowedHosts[]</translation>
    </message>
    <message>
        <source>Private key (name of a secret)</source>
        <translation>Privater Schlüssel (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of the secret that holds the PEM private key (-----BEGIN PRIVATE KEY----- or ENCRYPTED PRIVATE KEY), never the key</source>
        <translation>Der Name des Geheimnisses, das den privaten PEM-Schlüssel enthält (-----BEGIN PRIVATE KEY----- oder ENCRYPTED PRIVATE KEY), nie der Schlüssel</translation>
    </message>
    <message>
        <source>Passphrase of the key (name of a secret)</source>
        <translation>Passphrase des Schlüssels (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>For an encrypted key: the name of the secret with its passphrase</source>
        <translation>Bei einem verschlüsselten Schlüssel: der Name des Geheimnisses mit seiner Passphrase</translation>
    </message>
    <message>
        <source>Warehouse</source>
        <translation>Warehouse</translation>
    </message>
    <message>
        <source>Schema</source>
        <translation>Schema</translation>
    </message>
    <message>
        <source>Role</source>
        <translation>Rolle</translation>
    </message>
    <message>
        <source>A role with SELECT on what the import reads and nothing more</source>
        <translation>Eine Rolle mit SELECT auf das, was der Import liest, und nichts weiter</translation>
    </message>
    <message>
        <source>A single SELECT, WITH ... SELECT, SHOW or DESCRIBE</source>
        <translation>Ein einzelnes SELECT, WITH ... SELECT, SHOW oder DESCRIBE</translation>
    </message>
    <message>
        <source>The column that names a row (Snowflake names columns in upper case)</source>
        <translation>Die Spalte, die eine Zeile benennt (Snowflake benennt Spalten in Großbuchstaben)</translation>
    </message>
    <message>
        <source>e.g. https://influx.example.org:8086 or the address of InfluxDB Cloud (a private network only with [InfluxDbSourceSettings] AllowedHosts[])</source>
        <translation>z. B. https://influx.example.org:8086 oder die Adresse von InfluxDB Cloud (ein privates Netz nur mit [InfluxDbSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Query language</source>
        <translation>Abfragesprache</translation>
    </message>
    <message>
        <source>Flux (InfluxDB 2, Cloud)</source>
        <translation>Flux (InfluxDB 2, Cloud)</translation>
    </message>
    <message>
        <source>InfluxQL (InfluxDB 1, the 1.x API)</source>
        <translation>InfluxQL (InfluxDB 1, die 1.x-API)</translation>
    </message>
    <message>
        <source>Organization</source>
        <translation>Organisation</translation>
    </message>
    <message>
        <source>Flux: the organization name or ID</source>
        <translation>Flux: der Name oder die ID der Organisation</translation>
    </message>
    <message>
        <source>InfluxQL: the database (or the bucket mapped to it)</source>
        <translation>InfluxQL: die Datenbank (oder der ihr zugeordnete Bucket)</translation>
    </message>
    <message>
        <source>Retention policy</source>
        <translation>Retention Policy</translation>
    </message>
    <message>
        <source>InfluxQL: empty for the default</source>
        <translation>InfluxQL: leer für die Vorgabe</translation>
    </message>
    <message>
        <source>Token</source>
        <translation>Token</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE with a token that may read the bucket, never the token</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE mit einem Token, das den Bucket lesen darf, nie das Token</translation>
    </message>
    <message>
        <source>With User and password</source>
        <translation>Bei Benutzer und Passwort</translation>
    </message>
    <message>
        <source>Flux: from(bucket: "sensors") |&gt; range(start: -1d) ...; InfluxQL: a single SELECT or SHOW</source>
        <translation>Flux: from(bucket: "sensors") |&gt; range(start: -1d) ...; InfluxQL: ein einzelnes SELECT oder SHOW</translation>
    </message>
    <message>
        <source>One row per time (Flux)</source>
        <translation>Eine Zeile je Zeitpunkt (Flux)</translation>
    </message>
    <message>
        <source>Yes: appends pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value"), so each field is a column</source>
        <translation>Ja: hängt pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value") an, sodass jedes Feld eine Spalte ist</translation>
    </message>
    <message>
        <source>Page size (InfluxQL)</source>
        <translation>Seitengröße (InfluxQL)</translation>
    </message>
    <message>
        <source>Points per series and request (LIMIT ... OFFSET ...)</source>
        <translation>Punkte je Serie und Anfrage (LIMIT ... OFFSET ...)</translation>
    </message>
    <message>
        <source>The column that names a row; empty: the position</source>
        <translation>Die Spalte, die eine Zeile benennt; leer: die Position</translation>
    </message>
    <message>
        <source>ldaps://ldap.example.org (port 636) or ldap://ldap.example.org (port 389, with StartTLS); a private network only with [LdapSourceSettings] AllowedHosts[]</source>
        <translation>ldaps://ldap.example.org (Port 636) oder ldap://ldap.example.org (Port 389, mit StartTLS); ein privates Netz nur mit [LdapSourceSettings] AllowedHosts[]</translation>
    </message>
    <message>
        <source>StartTLS on ldap://</source>
        <translation>StartTLS bei ldap://</translation>
    </message>
    <message>
        <source>ldap:// is encrypted with StartTLS; ldaps:// is encrypted from the start</source>
        <translation>ldap:// wird mit StartTLS verschlüsselt; ldaps:// ist von Anfang an verschlüsselt</translation>
    </message>
    <message>
        <source>Allow an unencrypted connection to this server itself</source>
        <translation>Eine unverschlüsselte Verbindung zu diesem Server selbst erlauben</translation>
    </message>
    <message>
        <source>Only for ldap://localhost (127.0.0.1, ::1) without StartTLS; any other server needs ldaps:// or StartTLS</source>
        <translation>Nur für ldap://localhost (127.0.0.1, ::1) ohne StartTLS; jeder andere Server braucht ldaps:// oder StartTLS</translation>
    </message>
    <message>
        <source>Bind DN</source>
        <translation>Bind-DN</translation>
    </message>
    <message>
        <source>The user to bind as, e.g. cn=reader,ou=Services,dc=example,dc=org (Active Directory also reader@example.org); empty: anonymous</source>
        <translation>Der Benutzer, als der gebunden wird, z. B. cn=reader,ou=Services,dc=example,dc=org (bei Active Directory auch reader@example.org); leer: anonym</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password; needed with a bind DN</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, nie das Passwort; nötig mit einem Bind-DN</translation>
    </message>
    <message>
        <source>Base DN</source>
        <translation>Basis-DN</translation>
    </message>
    <message>
        <source>Where the search starts, e.g. ou=Staff,dc=example,dc=org</source>
        <translation>Wo die Suche beginnt, z. B. ou=Staff,dc=example,dc=org</translation>
    </message>
    <message>
        <source>An LDAP filter (RFC 4515), e.g. (&amp;(objectClass=inetOrgPerson)(mail=*)); special characters in values as \2a, \28, \29, \5c</source>
        <translation>Ein LDAP-Filter (RFC 4515), z. B. (&amp;(objectClass=inetOrgPerson)(mail=*)); Sonderzeichen in Werten als \2a, \28, \29, \5c</translation>
    </message>
    <message>
        <source>The attributes to read, one per line; empty: all user attributes. A listed attribute is in every record, also when an entry has none</source>
        <translation>Die zu lesenden Attribute, eines je Zeile; leer: alle Benutzerattribute. Ein aufgeführtes Attribut steht in jedem Datensatz, auch wenn ein Eintrag keines hat</translation>
    </message>
    <message>
        <source>Scope</source>
        <translation>Bereich</translation>
    </message>
    <message>
        <source>The base and everything below it</source>
        <translation>Die Basis und alles darunter</translation>
    </message>
    <message>
        <source>The entries directly below the base</source>
        <translation>Die Einträge direkt unterhalb der Basis</translation>
    </message>
    <message>
        <source>The base entry only</source>
        <translation>Nur der Basiseintrag</translation>
    </message>
    <message>
        <source>Entries per page of the paged results control; 0: no paging (the server's size limit applies, often 1000 in Active Directory)</source>
        <translation>Einträge je Seite des Paged Results Control; 0: kein Blättern (es gilt die Größengrenze des Servers, bei Active Directory oft 1000)</translation>
    </message>
    <message>
        <source>Most entries</source>
        <translation>Höchstzahl an Einträgen</translation>
    </message>
    <message>
        <source>0: all entries</source>
        <translation>0: alle Einträge</translation>
    </message>
    <message>
        <source>Binary attributes</source>
        <translation>Binäre Attribute</translation>
    </message>
    <message>
        <source>jpegPhoto, objectGUID, objectSid, certificates and every value that is not UTF-8 text</source>
        <translation>jpegPhoto, objectGUID, objectSid, Zertifikate und jeder Wert, der kein UTF-8-Text ist</translation>
    </message>
    <message>
        <source>As base64:&lt;data&gt; (a photo can be mapped to an image)</source>
        <translation>Als base64:&lt;data&gt; (ein Foto lässt sich einem Bild zuordnen)</translation>
    </message>
    <message>
        <source>Left out</source>
        <translation>Ausgelassen</translation>
    </message>
    <message>
        <source>Attributes with several values</source>
        <translation>Attribute mit mehreren Werten</translation>
    </message>
    <message>
        <source>A list (one value: the value)</source>
        <translation>Eine Liste (ein Wert: der Wert)</translation>
    </message>
    <message>
        <source>The values joined by the separator</source>
        <translation>Die Werte, durch das Trennzeichen verbunden</translation>
    </message>
    <message>
        <source>With joined values</source>
        <translation>Bei verbundenen Werten</translation>
    </message>
    <message>
        <source>The field that names an entry: dn, or an attribute such as uid, entryUUID or objectGUID_text</source>
        <translation>Das Feld, das einen Eintrag benennt: dn oder ein Attribut wie uid, entryUUID oder objectGUID_text</translation>
    </message>
    <message>
        <source>The class a submission becomes; its attributes are matched to the columns</source>
        <translation>Die Klasse, zu der eine Einsendung wird; ihre Attribute werden den Spalten zugeordnet</translation>
    </message>
    <message>
        <source>Sitemap</source>
        <translation>Sitemap</translation>
    </message>
    <message>
        <source>The address of sitemap.xml or a sitemap index; a site's root (https://example.org/) reads the Sitemap lines of its robots.txt</source>
        <translation>Die Adresse der sitemap.xml oder eines Sitemap-Index; die Wurzel einer Site (https://example.org/) liest die Sitemap-Zeilen ihrer robots.txt</translation>
    </message>
    <message>
        <source>Only addresses like</source>
        <translation>Nur Adressen wie</translation>
    </message>
    <message>
        <source>One pattern per line: a glob (https://example.org/blog/*, or /blog/* for the path) or regex:&lt;expression&gt;; empty: every page</source>
        <translation>Ein Muster je Zeile: ein Glob (https://example.org/blog/* oder /blog/* für den Pfad) oder regex:&lt;Ausdruck&gt;; leer: jede Seite</translation>
    </message>
    <message>
        <source>Never addresses like</source>
        <translation>Nie Adressen wie</translation>
    </message>
    <message>
        <source>Patterns as above</source>
        <translation>Muster wie oben</translation>
    </message>
    <message>
        <source>Picks</source>
        <translation>Auswahlen</translation>
    </message>
    <message>
        <source>One field=selector per line: CSS (h1, .price, #main, a[rel=next], meta[property=og:image]::attr(content), img@src, article .content) or XPath (xpath://h1). A field body keeps the HTML of what it finds.</source>
        <translation>Ein feld=selektor je Zeile: CSS (h1, .price, #main, a[rel=next], meta[property=og:image]::attr(content), img@src, article .content) oder XPath (xpath://h1). Ein Feld body behält das HTML dessen, was es findet.</translation>
    </message>
    <message>
        <source>Pages at most</source>
        <translation>Höchstens Seiten</translation>
    </message>
    <message>
        <source>The most pages one run reads (never more than sqliimport.ini [CrawlSettings] MaxPages)</source>
        <translation>Die meisten Seiten, die ein Lauf liest (nie mehr als sqliimport.ini [CrawlSettings] MaxPages)</translation>
    </message>
    <message>
        <source>Pause between requests</source>
        <translation>Pause zwischen Anfragen</translation>
    </message>
    <message>
        <source>Seconds between two requests to the site (at least [CrawlSettings] MinDelay; a Crawl-delay of robots.txt is longer)</source>
        <translation>Sekunden zwischen zwei Anfragen an die Site (mindestens [CrawlSettings] MinDelay; ein Crawl-delay der robots.txt ist länger)</translation>
    </message>
    <message>
        <source>The field that names a page; url is its address</source>
        <translation>Das Feld, das eine Seite benennt; url ist ihre Adresse</translation>
    </message>
    <message>
        <source>Addresses</source>
        <translation>Adressen</translation>
    </message>
    <message>
        <source>One address (http or https) per line</source>
        <translation>Eine Adresse (http oder https) je Zeile</translation>
    </message>
    <message>
        <source>Repeating item</source>
        <translation>Wiederkehrendes Element</translation>
    </message>
    <message>
        <source>A selector of the element that repeats on a page (.product, article.event); empty: the page is one record</source>
        <translation>Ein Selektor des Elements, das sich auf einer Seite wiederholt (.product, article.event); leer: die Seite ist ein Datensatz</translation>
    </message>
    <message>
        <source>Next page</source>
        <translation>Nächste Seite</translation>
    </message>
    <message>
        <source>A selector of the link to the next page (a[rel=next], .pager .next a); its href is followed</source>
        <translation>Ein Selektor des Links zur nächsten Seite (a[rel=next], .pager .next a); seinem href wird gefolgt</translation>
    </message>
    <message>
        <source>The field that names a record; id is its address</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist seine Adresse</translation>
    </message>
    <message>
        <source>Site address</source>
        <translation>Adresse der Site</translation>
    </message>
    <message>
        <source>The address of the other site, e.g. https://www.example.org (REST API at /api/ezp/v1)</source>
        <translation>Die Adresse der anderen Site, z. B. https://www.example.org (REST-API unter /api/ezp/v1)</translation>
    </message>
    <message>
        <source>Parent node ID</source>
        <translation>ID des Elternknotens</translation>
    </message>
    <message>
        <source>The node of the other site whose children are read</source>
        <translation>Der Knoten der anderen Site, dessen Kinder gelesen werden</translation>
    </message>
    <message>
        <source>Depth</source>
        <translation>Tiefe</translation>
    </message>
    <message>
        <source>1: the children; 2: their children too, and so on</source>
        <translation>1: die Kinder; 2: auch deren Kinder und so weiter</translation>
    </message>
    <message>
        <source>Only the objects of this class (empty: every class)</source>
        <translation>Nur die Objekte dieser Klasse (leer: jede Klasse)</translation>
    </message>
    <message>
        <source>Translation</source>
        <translation>Übersetzung</translation>
    </message>
    <message>
        <source>The language to read, e.g. ger-DE (empty: the site's)</source>
        <translation>Die zu lesende Sprache, z. B. ger-DE (leer: die der Site)</translation>
    </message>
    <message>
        <source>Most nodes</source>
        <translation>Höchstzahl an Knoten</translation>
    </message>
    <message>
        <source>At most this many nodes per run (and never more than [PlatformSettings] MaxItems)</source>
        <translation>Höchstens so viele Knoten je Lauf (und nie mehr als [PlatformSettings] MaxItems)</translation>
    </message>
    <message>
        <source>None (a public REST API)</source>
        <translation>Keine (eine öffentliche REST-API)</translation>
    </message>
    <message>
        <source>Token or API key</source>
        <translation>Token oder API-Schlüssel</translation>
    </message>
    <message>
        <source>OAuth2: password grant</source>
        <translation>OAuth2: Password Grant</translation>
    </message>
    <message>
        <source>OAuth2: refresh token</source>
        <translation>OAuth2: Refresh-Token</translation>
    </message>
    <message>
        <source>With User and password and the password grant: the user name</source>
        <translation>Bei Benutzer und Passwort und dem Password Grant: der Benutzername</translation>
    </message>
    <message>
        <source>Password (secret)</source>
        <translation>Passwort (Geheimnis)</translation>
    </message>
    <message>
        <source>The name of the secret with the password</source>
        <translation>Der Name des Geheimnisses mit dem Passwort</translation>
    </message>
    <message>
        <source>Token (secret)</source>
        <translation>Token (Geheimnis)</translation>
    </message>
    <message>
        <source>With Token: the name of the secret with the token or API key</source>
        <translation>Bei Token: der Name des Geheimnisses mit dem Token oder API-Schlüssel</translation>
    </message>
    <message>
        <source>OAuth client ID</source>
        <translation>OAuth-Client-ID</translation>
    </message>
    <message>
        <source>OAuth client secret (secret)</source>
        <translation>OAuth-Client-Secret (Geheimnis)</translation>
    </message>
    <message>
        <source>The name of the secret with the client secret (empty for a public client)</source>
        <translation>Der Name des Geheimnisses mit dem Client-Secret (leer bei einem öffentlichen Client)</translation>
    </message>
    <message>
        <source>Refresh token (secret)</source>
        <translation>Refresh-Token (Geheimnis)</translation>
    </message>
    <message>
        <source>With the refresh token: the name of the secret with it</source>
        <translation>Mit dem Refresh-Token: der Name des Geheimnisses, das es enthält</translation>
    </message>
    <message>
        <source>Token end point</source>
        <translation>Token-Endpunkt</translation>
    </message>
    <message>
        <source>Empty: &lt;site address&gt;/api/oauth/token</source>
        <translation>Leer: &lt;Adresse der Site&gt;/api/oauth/token</translation>
    </message>
    <message>
        <source>The field that names a record; remote_id is the object's remote ID.</source>
        <translation>Das Feld, das einen Datensatz benennt; remote_id ist die Remote-ID des Objekts.</translation>
    </message>
    <message>
        <source>Only the objects of this class (empty: every object of the package)</source>
        <translation>Nur die Objekte dieser Klasse (leer: jedes Objekt des Pakets)</translation>
    </message>
    <message>
        <source>Language</source>
        <translation>Sprache</translation>
    </message>
    <message>
        <source>The translation to read, e.g. ger-DE (empty: the initial language of each object)</source>
        <translation>Die zu lesende Übersetzung, z. B. ger-DE (leer: die Ausgangssprache jedes Objekts)</translation>
    </message>
    <message>
        <source>The address of the Drupal site, e.g. https://www.example.org (JSON:API at /jsonapi)</source>
        <translation>Die Adresse der Drupal-Site, z. B. https://www.example.org (JSON:API unter /jsonapi)</translation>
    </message>
    <message>
        <source>Resource type</source>
        <translation>Ressourcentyp</translation>
    </message>
    <message>
        <source>The entity type and bundle: node--article, node--page, taxonomy_term--tags, media--image</source>
        <translation>Entitätstyp und Bundle: node--article, node--page, taxonomy_term--tags, media--image</translation>
    </message>
    <message>
        <source>Resources per request (JSON:API answers at most 50)</source>
        <translation>Ressourcen je Anfrage (JSON:API antwortet mit höchstens 50)</translation>
    </message>
    <message>
        <source>Include</source>
        <translation>Einbinden</translation>
    </message>
    <message>
        <source>The relationships whose resources come along, comma separated: field_image,uid,field_tags</source>
        <translation>Die Beziehungen, deren Ressourcen mitkommen, durch Kommas getrennt: field_image,uid,field_tags</translation>
    </message>
    <message>
        <source>JSON:API query parameters as they are, e.g. filter[status]=1&amp;sort=-created</source>
        <translation>JSON:API-Abfrageparameter, wie sie sind, z. B. filter[status]=1&amp;sort=-created</translation>
    </message>
    <message>
        <source>None (public content)</source>
        <translation>Keine (öffentliche Inhalte)</translation>
    </message>
    <message>
        <source>User and password (basic_auth)</source>
        <translation>Benutzer und Passwort (basic_auth)</translation>
    </message>
    <message>
        <source>Token (OAuth bearer)</source>
        <translation>Token (OAuth-Bearer)</translation>
    </message>
    <message>
        <source>With User and password: the Drupal user name</source>
        <translation>Bei Benutzer und Passwort: der Drupal-Benutzername</translation>
    </message>
    <message>
        <source>With User and password: the name of the secret with the password</source>
        <translation>Bei Benutzer und Passwort: der Name des Geheimnisses mit dem Passwort</translation>
    </message>
    <message>
        <source>With Token: the name of the secret with the access token</source>
        <translation>Bei Token: der Name des Geheimnisses mit dem Zugriffstoken</translation>
    </message>
    <message>
        <source>The field that names a record; id is the UUID of the resource.</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die UUID der Ressource.</translation>
    </message>
    <message>
        <source>Export</source>
        <translation>Export</translation>
    </message>
    <message>
        <source>CSV</source>
        <translation>CSV</translation>
    </message>
    <message>
        <source>XML (J2XML or &lt;article&gt; elements)</source>
        <translation>XML (J2XML oder &lt;article&gt;-Elemente)</translation>
    </message>
    <message>
        <source>The address of the Joomla site, so the images (images/...) can be downloaded</source>
        <translation>Die Adresse der Joomla-Site, damit sich die Bilder (images/...) herunterladen lassen</translation>
    </message>
    <message>
        <source>Articles</source>
        <translation>Artikel</translation>
    </message>
    <message>
        <source>Published, unpublished and archived</source>
        <translation>Veröffentlicht, unveröffentlicht und archiviert</translation>
    </message>
    <message>
        <source>All, also trashed</source>
        <translation>Alle, auch im Papierkorb</translation>
    </message>
    <message>
        <source>The field that names a record; id is the Joomla article ID.</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die Joomla-Artikel-ID.</translation>
    </message>
    <message>
        <source>Base ID</source>
        <translation>Base-ID</translation>
    </message>
    <message>
        <source>The ID of the base, app... (in the address of the base: airtable.com/appXXXXXXXXXXXXXX/...)</source>
        <translation>Die ID der Base, app... (in der Adresse der Base: airtable.com/appXXXXXXXXXXXXXX/...)</translation>
    </message>
    <message>
        <source>The name of the table or its ID (tbl...); the ID stays right when the table is renamed</source>
        <translation>Der Name der Tabelle oder ihre ID (tbl...); die ID bleibt richtig, wenn die Tabelle umbenannt wird</translation>
    </message>
    <message>
        <source>Optional: the name or ID (viw...) of a view; its filters and its order apply</source>
        <translation>Optional: der Name oder die ID (viw...) einer Ansicht; ihre Filter und ihre Sortierung gelten</translation>
    </message>
    <message>
        <source>Filter formula</source>
        <translation>Filterformel</translation>
    </message>
    <message>
        <source>Optional: filterByFormula, an Airtable formula a record must make true, e.g. {Status} = "Published"</source>
        <translation>Optional: filterByFormula, eine Airtable-Formel, die ein Datensatz erfüllen muss, z. B. {Status} = "Published"</translation>
    </message>
    <message>
        <source>Optional: the fields to read, one per line; empty: all. A listed field is in every record, also when it is empty</source>
        <translation>Optional: die zu lesenden Felder, eines je Zeile; leer: alle. Ein aufgeführtes Feld steht in jedem Datensatz, auch wenn es leer ist</translation>
    </message>
    <message>
        <source>Records per request (Airtable answers at most 100)</source>
        <translation>Datensätze je Anfrage (Airtable antwortet mit höchstens 100)</translation>
    </message>
    <message>
        <source>Personal access token (name of a secret)</source>
        <translation>Persönliches Zugriffstoken (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding a personal access token with the scope data.records:read, never the token</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, das ein persönliches Zugriffstoken mit dem Bereich data.records:read enthält, nie das Token</translation>
    </message>
    <message>
        <source>The field that names a record; id is the record ID (rec...)</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die Datensatz-ID (rec...)</translation>
    </message>
    <message>
        <source>Database ID</source>
        <translation>Datenbank-ID</translation>
    </message>
    <message>
        <source>The ID of the database (32 characters, with or without dashes) or its link (Share, Copy link)</source>
        <translation>Die ID der Datenbank (32 Zeichen, mit oder ohne Bindestriche) oder ihr Link (Teilen, Link kopieren)</translation>
    </message>
    <message>
        <source>Optional: a filter object of the Notion API, e.g. {"property": "Status", "status": {"equals": "Published"}}</source>
        <translation>Optional: ein Filterobjekt der Notion-API, z. B. {"property": "Status", "status": {"equals": "Published"}}</translation>
    </message>
    <message>
        <source>Sorts</source>
        <translation>Sortierungen</translation>
    </message>
    <message>
        <source>Optional: a list of sorts of the Notion API, e.g. [{"property": "Date", "direction": "descending"}]</source>
        <translation>Optional: eine Liste von Sortierungen der Notion-API, z. B. [{"property": "Date", "direction": "descending"}]</translation>
    </message>
    <message>
        <source>Page content</source>
        <translation>Seiteninhalt</translation>
    </message>
    <message>
        <source>The blocks of the first level of every page (paragraphs, headings, lists, quotes, code, images); one more request per page</source>
        <translation>Die Blöcke der ersten Ebene jeder Seite (Absätze, Überschriften, Listen, Zitate, Code, Bilder); eine weitere Anfrage je Seite</translation>
    </message>
    <message>
        <source>No (the properties only)</source>
        <translation>Nein (nur die Eigenschaften)</translation>
    </message>
    <message>
        <source>As Markdown (field content)</source>
        <translation>Als Markdown (Feld content)</translation>
    </message>
    <message>
        <source>As HTML (field content)</source>
        <translation>Als HTML (Feld content)</translation>
    </message>
    <message>
        <source>Pages per request (Notion answers at most 100)</source>
        <translation>Seiten je Anfrage (Notion antwortet mit höchstens 100)</translation>
    </message>
    <message>
        <source>Integration token (name of a secret)</source>
        <translation>Integrationstoken (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding the token of an internal integration the database is shared with, never the token</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, das das Token einer internen Integration enthält, für die die Datenbank freigegeben ist, nie das Token</translation>
    </message>
    <message>
        <source>The field that names a record; id is the ID of the page</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die ID der Seite</translation>
    </message>
    <message>
        <source>Project or server address</source>
        <translation>Projekt- oder Serveradresse</translation>
    </message>
    <message>
        <source>The address of the Supabase project (https://&lt;ref&gt;.supabase.co; /rest/v1 is added) or of the PostgREST server (a private network only with [SupabaseSourceSettings] AllowedHosts[])</source>
        <translation>Die Adresse des Supabase-Projekts (https://&lt;ref&gt;.supabase.co; /rest/v1 wird angehängt) oder des PostgREST-Servers (ein privates Netz nur mit [SupabaseSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Table or view</source>
        <translation>Tabelle oder Sicht</translation>
    </message>
    <message>
        <source>The name of the table or view, no path</source>
        <translation>Der Name der Tabelle oder Sicht, kein Pfad</translation>
    </message>
    <message>
        <source>Optional: a schema the API exposes besides public (header Accept-Profile)</source>
        <translation>Optional: ein Schema, das die API neben public bereitstellt (Header Accept-Profile)</translation>
    </message>
    <message>
        <source>Select</source>
        <translation>Select</translation>
    </message>
    <message>
        <source>Columns and embedded resources, e.g. id,title,author:authors(name,email); empty: *</source>
        <translation>Spalten und eingebettete Ressourcen, z. B. id,title,author:authors(name,email); leer: *</translation>
    </message>
    <message>
        <source>Filters</source>
        <translation>Filter</translation>
    </message>
    <message>
        <source>One per line as a PostgREST query parameter: status=eq.published, price=lt.10, tags=cs.{tea}, or=(stock.gt.0,preorder.is.true); operators eq, neq, gt, gte, lt, lte, like, ilike, is, in, cs, cd and not.</source>
        <translation>Einer je Zeile als PostgREST-Abfrageparameter: status=eq.published, price=lt.10, tags=cs.{tea}, or=(stock.gt.0,preorder.is.true); Operatoren eq, neq, gt, gte, lt, lte, like, ilike, is, in, cs, cd und not.</translation>
    </message>
    <message>
        <source>e.g. published_at.desc,id; an order keeps the pages stable</source>
        <translation>z. B. published_at.desc,id; eine Sortierung hält die Seiten stabil</translation>
    </message>
    <message>
        <source>Range header (Range-Unit: items)</source>
        <translation>Range-Header (Range-Unit: items)</translation>
    </message>
    <message>
        <source>limit and offset parameters</source>
        <translation>Parameter limit und offset</translation>
    </message>
    <message>
        <source>Count</source>
        <translation>Zählung</translation>
    </message>
    <message>
        <source>Whether the server tells the total (Content-Range); Test connection shows it</source>
        <translation>Ob der Server die Gesamtzahl nennt (Content-Range); Verbindung testen zeigt sie</translation>
    </message>
    <message>
        <source>No count (fastest)</source>
        <translation>Keine Zählung (am schnellsten)</translation>
    </message>
    <message>
        <source>Exact (Prefer: count=exact)</source>
        <translation>Genau (Prefer: count=exact)</translation>
    </message>
    <message>
        <source>Planned (the planner's estimate)</source>
        <translation>Geplant (die Schätzung des Planers)</translation>
    </message>
    <message>
        <source>Estimated</source>
        <translation>Geschätzt</translation>
    </message>
    <message>
        <source>Rows per request (Supabase answers at most its max rows setting, 1000 by default)</source>
        <translation>Zeilen je Anfrage (Supabase antwortet mit höchstens seiner Einstellung max rows, standardmäßig 1000)</translation>
    </message>
    <message>
        <source>0: every row</source>
        <translation>0: jede Zeile</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding the anon key (sent as apikey and bearer token); empty for a PostgREST server without authentication</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, das den anon-Schlüssel enthält (als apikey und Bearer-Token gesendet); leer bei einem PostgREST-Server ohne Authentifizierung</translation>
    </message>
    <message>
        <source>Bearer token (name of a secret)</source>
        <translation>Bearer-Token (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>Optional: a JWT of a read-only role sent as the bearer token instead of the API key</source>
        <translation>Optional: ein JWT einer Nur-Lese-Rolle, das statt des API-Schlüssels als Bearer-Token gesendet wird</translation>
    </message>
    <message>
        <source>https://api.baserow.io, or the address of a self-hosted Baserow (a private network only with [BaserowSourceSettings] AllowedHosts[])</source>
        <translation>https://api.baserow.io oder die Adresse eines selbst betriebenen Baserow (ein privates Netz nur mit [BaserowSourceSettings] AllowedHosts[])</translation>
    </message>
    <message>
        <source>Table ID</source>
        <translation>Tabellen-ID</translation>
    </message>
    <message>
        <source>The ID of the table (the number in the address of the table, and in the API docs of the database)</source>
        <translation>Die ID der Tabelle (die Zahl in der Adresse der Tabelle und in der API-Dokumentation der Datenbank)</translation>
    </message>
    <message>
        <source>View ID</source>
        <translation>Ansichts-ID</translation>
    </message>
    <message>
        <source>Optional: a view whose filters and order apply; 0: none</source>
        <translation>Optional: eine Ansicht, deren Filter und Sortierung gelten; 0: keine</translation>
    </message>
    <message>
        <source>One per line as &lt;field&gt;__&lt;type&gt;=&lt;value&gt;, e.g. Status__single_select_equal=3 or Price__higher_than=10 (sent as filter__&lt;field&gt;__&lt;type&gt;)</source>
        <translation>Einer je Zeile als &lt;feld&gt;__&lt;typ&gt;=&lt;wert&gt;, z. B. Status__single_select_equal=3 oder Price__higher_than=10 (gesendet als filter__&lt;feld&gt;__&lt;typ&gt;)</translation>
    </message>
    <message>
        <source>Filters combined with</source>
        <translation>Filter verknüpft mit</translation>
    </message>
    <message>
        <source>AND (every filter)</source>
        <translation>UND (jeder Filter)</translation>
    </message>
    <message>
        <source>OR (any filter)</source>
        <translation>ODER (irgendein Filter)</translation>
    </message>
    <message>
        <source>Optional: only rows that contain this text</source>
        <translation>Optional: nur Zeilen, die diesen Text enthalten</translation>
    </message>
    <message>
        <source>Fields by name, comma separated, - in front for descending: -Date,Name</source>
        <translation>Felder über ihren Namen, durch Kommas getrennt, ein - davor für absteigend: -Date,Name</translation>
    </message>
    <message>
        <source>Optional: the fields to read, one per line; empty: all</source>
        <translation>Optional: die zu lesenden Felder, eines je Zeile; leer: alle</translation>
    </message>
    <message>
        <source>Rows per request (Baserow answers at most 200)</source>
        <translation>Zeilen je Anfrage (Baserow antwortet mit höchstens 200)</translation>
    </message>
    <message>
        <source>Between the values of link rows, multiple selects and collaborators</source>
        <translation>Zwischen den Werten von Verknüpfungszeilen, Mehrfachauswahlen und Mitarbeitenden</translation>
    </message>
    <message>
        <source>Database token (name of a secret)</source>
        <translation>Datenbank-Token (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding a database token with the permission read, never the token</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, das ein Datenbank-Token mit der Berechtigung read enthält, nie das Token</translation>
    </message>
    <message>
        <source>The field that names a row; id is the row ID</source>
        <translation>Das Feld, das eine Zeile benennt; id ist die Zeilen-ID</translation>
    </message>
    <message>
        <source>Login address</source>
        <translation>Anmeldeadresse</translation>
    </message>
    <message>
        <source>https://login.salesforce.com, https://test.salesforce.com for a sandbox, or the My Domain address</source>
        <translation>https://login.salesforce.com, https://test.salesforce.com für eine Sandbox oder die Adresse von My Domain</translation>
    </message>
    <message>
        <source>Sign in with</source>
        <translation>Anmelden mit</translation>
    </message>
    <message>
        <source>JWT bearer flow (consumer key, user, private key)</source>
        <translation>JWT-Bearer-Flow (Consumer Key, Benutzer, privater Schlüssel)</translation>
    </message>
    <message>
        <source>Refresh token (consumer key and secret, refresh token)</source>
        <translation>Refresh-Token (Consumer Key und Secret, Refresh-Token)</translation>
    </message>
    <message>
        <source>Consumer key</source>
        <translation>Consumer Key</translation>
    </message>
    <message>
        <source>The consumer key of the connected app</source>
        <translation>Der Consumer Key der Connected App</translation>
    </message>
    <message>
        <source>User (JWT)</source>
        <translation>Benutzer (JWT)</translation>
    </message>
    <message>
        <source>JWT bearer flow: the user name of the integration user</source>
        <translation>JWT-Bearer-Flow: der Benutzername des Integrationsbenutzers</translation>
    </message>
    <message>
        <source>JWT bearer flow: the name of a secret holding the PEM of the private key of the connected app's certificate</source>
        <translation>JWT-Bearer-Flow: der Name eines Geheimnisses, das das PEM des privaten Schlüssels des Zertifikats der Connected App enthält</translation>
    </message>
    <message>
        <source>Consumer secret (name of a secret)</source>
        <translation>Consumer Secret (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>Refresh token flow</source>
        <translation>Refresh-Token-Flow</translation>
    </message>
    <message>
        <source>Refresh token (name of a secret)</source>
        <translation>Refresh-Token (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>SOQL query</source>
        <translation>SOQL-Abfrage</translation>
    </message>
    <message>
        <source>A single SELECT, e.g. SELECT Id, Name, Owner.Name FROM Account WHERE Type = 'Customer'</source>
        <translation>Ein einzelnes SELECT, z. B. SELECT Id, Name, Owner.Name FROM Account WHERE Type = 'Customer'</translation>
    </message>
    <message>
        <source>Also deleted and archived records (queryAll)</source>
        <translation>Auch gelöschte und archivierte Datensätze (queryAll)</translation>
    </message>
    <message>
        <source>API version</source>
        <translation>API-Version</translation>
    </message>
    <message>
        <source>Records per answer (Sforce-Query-Options batchSize; Salesforce may give fewer)</source>
        <translation>Datensätze je Antwort (Sforce-Query-Options batchSize; Salesforce kann weniger liefern)</translation>
    </message>
    <message>
        <source>The field that names a record</source>
        <translation>Das Feld, das einen Datensatz benennt</translation>
    </message>
    <message>
        <source>Object type</source>
        <translation>Objekttyp</translation>
    </message>
    <message>
        <source>contacts, companies, deals, tickets, products, line_items, or a custom object's type ID (2-1234567) or name (p_events)</source>
        <translation>contacts, companies, deals, tickets, products, line_items oder die Typ-ID (2-1234567) oder der Name (p_events) eines eigenen Objekts</translation>
    </message>
    <message>
        <source>Every object (list)</source>
        <translation>Jedes Objekt (Liste)</translation>
    </message>
    <message>
        <source>A search (filter groups)</source>
        <translation>Eine Suche (Filtergruppen)</translation>
    </message>
    <message>
        <source>Properties</source>
        <translation>Eigenschaften</translation>
    </message>
    <message>
        <source>The properties to read, one per line (internal names: firstname, domain, amount); empty: HubSpot's default properties</source>
        <translation>Die zu lesenden Eigenschaften, eine je Zeile (interne Namen: firstname, domain, amount); leer: die Standardeigenschaften von HubSpot</translation>
    </message>
    <message>
        <source>Associations</source>
        <translation>Verknüpfungen</translation>
    </message>
    <message>
        <source>List only: the associated object types whose IDs to read, one per line (contacts, companies, deals)</source>
        <translation>Nur bei Liste: die verknüpften Objekttypen, deren IDs gelesen werden, einer je Zeile (contacts, companies, deals)</translation>
    </message>
    <message>
        <source>Filter groups (JSON)</source>
        <translation>Filtergruppen (JSON)</translation>
    </message>
    <message>
        <source>Search: e.g. [{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]; groups are combined with OR, the filters of a group with AND (at most 5 groups and 18 filters)</source>
        <translation>Suche: z. B. [{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]; Gruppen werden mit ODER verknüpft, die Filter einer Gruppe mit UND (höchstens 5 Gruppen und 18 Filter)</translation>
    </message>
    <message>
        <source>Search text</source>
        <translation>Suchtext</translation>
    </message>
    <message>
        <source>Search: the text HubSpot looks for in the default text properties</source>
        <translation>Suche: der Text, nach dem HubSpot in den Standard-Texteigenschaften sucht</translation>
    </message>
    <message>
        <source>Search: one property and the direction, e.g. createdate DESCENDING</source>
        <translation>Suche: eine Eigenschaft und die Richtung, z. B. createdate DESCENDING</translation>
    </message>
    <message>
        <source>Archived objects</source>
        <translation>Archivierte Objekte</translation>
    </message>
    <message>
        <source>List only: read the archived objects instead</source>
        <translation>Nur bei Liste: stattdessen die archivierten Objekte lesen</translation>
    </message>
    <message>
        <source>Date properties</source>
        <translation>Datumseigenschaften</translation>
    </message>
    <message>
        <source>Properties whose values in milliseconds since 1970 become ISO 8601, besides createdate, closedate and the other dates HubSpot keeps</source>
        <translation>Eigenschaften, deren Werte in Millisekunden seit 1970 zu ISO 8601 werden, neben createdate, closedate und den anderen Datumswerten, die HubSpot führt</translation>
    </message>
    <message>
        <source>Objects per request (a list at most 100, a search 200)</source>
        <translation>Objekte je Anfrage (eine Liste höchstens 100, eine Suche 200)</translation>
    </message>
    <message>
        <source>Private app access token (name of a secret)</source>
        <translation>Zugriffstoken der privaten App (Name eines Geheimnisses)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding the access token of a private app with read scopes only</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE, das das Zugriffstoken einer privaten App nur mit Lesebereichen enthält</translation>
    </message>
    <message>
        <source>The field that names an object; id is its record ID</source>
        <translation>Das Feld, das ein Objekt benennt; id ist seine Datensatz-ID</translation>
    </message>
    <message>
        <source>channel: every video the channel uploaded; playlist: the videos of a playlist; search: the videos a search finds</source>
        <translation>channel: jedes Video, das der Kanal hochgeladen hat; playlist: die Videos einer Playlist; search: die Videos, die eine Suche findet</translation>
    </message>
    <message>
        <source>The uploads of a channel</source>
        <translation>Die Uploads eines Kanals</translation>
    </message>
    <message>
        <source>A playlist</source>
        <translation>Eine Playlist</translation>
    </message>
    <message>
        <source>A search (100 quota units per page)</source>
        <translation>Eine Suche (100 Kontingenteinheiten je Seite)</translation>
    </message>
    <message>
        <source>Channel</source>
        <translation>Kanal</translation>
    </message>
    <message>
        <source>Channel and search: the channel ID (UC...) or @handle, or its address (youtube.com/@name, youtube.com/channel/UC...); a search without it searches all of YouTube</source>
        <translation>Kanal und Suche: die Kanal-ID (UC...) oder der @Handle oder seine Adresse (youtube.com/@name, youtube.com/channel/UC...); eine Suche ohne ihn durchsucht ganz YouTube</translation>
    </message>
    <message>
        <source>Playlist</source>
        <translation>Playlist</translation>
    </message>
    <message>
        <source>Playlist: the playlist ID (PL...) or its address (youtube.com/playlist?list=...)</source>
        <translation>Playlist: die Playlist-ID (PL...) oder ihre Adresse (youtube.com/playlist?list=...)</translation>
    </message>
    <message>
        <source>Search terms</source>
        <translation>Suchbegriffe</translation>
    </message>
    <message>
        <source>Search: the terms (q); | means or, - excludes a term</source>
        <translation>Suche: die Begriffe (q); | bedeutet oder, - schließt einen Begriff aus</translation>
    </message>
    <message>
        <source>Published after</source>
        <translation>Veröffentlicht nach</translation>
    </message>
    <message>
        <source>Search: only videos published at or after this date (2026-01-31 or 2026-01-31T12:00:00Z)</source>
        <translation>Suche: nur Videos, die an oder nach diesem Datum veröffentlicht wurden (2026-01-31 oder 2026-01-31T12:00:00Z)</translation>
    </message>
    <message>
        <source>Published before</source>
        <translation>Veröffentlicht vor</translation>
    </message>
    <message>
        <source>Search: only videos published before this date</source>
        <translation>Suche: nur Videos, die vor diesem Datum veröffentlicht wurden</translation>
    </message>
    <message>
        <source>Search: the order of the results</source>
        <translation>Suche: die Reihenfolge der Ergebnisse</translation>
    </message>
    <message>
        <source>Newest first</source>
        <translation>Neueste zuerst</translation>
    </message>
    <message>
        <source>Rating</source>
        <translation>Bewertung</translation>
    </message>
    <message>
        <source>Relevance</source>
        <translation>Relevanz</translation>
    </message>
    <message>
        <source>Title</source>
        <translation>Titel</translation>
    </message>
    <message>
        <source>Views</source>
        <translation>Aufrufe</translation>
    </message>
    <message>
        <source>Videos per request (YouTube answers at most 50)</source>
        <translation>Videos je Anfrage (YouTube antwortet mit höchstens 50)</translation>
    </message>
    <message>
        <source>Most videos</source>
        <translation>Höchstzahl an Videos</translation>
    </message>
    <message>
        <source>The most videos one run reads; 0: all (within [YouTubeSettings] MaxPages)</source>
        <translation>Die meisten Videos, die ein Lauf liest; 0: alle (innerhalb von [YouTubeSettings] MaxPages)</translation>
    </message>
    <message>
        <source>The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding an API key restricted to the YouTube Data API v3, never the key</source>
        <translation>Der Name eines Geheimnisses aus sqliimport.ini [SecretSettings] Secrets[] oder env:VARIABLE, das einen auf die YouTube Data API v3 beschränkten API-Schlüssel enthält, nie der Schlüssel</translation>
    </message>
    <message>
        <source>The field that names a record; id is the video ID</source>
        <translation>Das Feld, das einen Datensatz benennt; id ist die Video-ID</translation>
    </message>
    <message>
        <source>Page</source>
        <translation>Seite</translation>
    </message>
    <message>
        <source>A channel's videos page (https://www.youtube.com/@name/videos, /channel/UC.../videos) or a playlist (https://www.youtube.com/playlist?list=...). Reading youtube.com pages is against YouTube's Terms of Service and may break whenever YouTube changes its pages. Use it only where you may, and prefer the YouTube Data API (youtube) or the channel feed (video_feed).</source>
        <translation>Die Videoseite eines Kanals (https://www.youtube.com/@name/videos, /channel/UC.../videos) oder eine Playlist (https://www.youtube.com/playlist?list=...). Das Lesen von Seiten von youtube.com verstößt gegen die Nutzungsbedingungen von YouTube und kann jederzeit nicht mehr funktionieren, wenn YouTube seine Seiten ändert. Verwenden Sie es nur, wo Sie es dürfen, und ziehen Sie die YouTube Data API (youtube) oder den Kanal-Feed (video_feed) vor.</translation>
    </message>
    <message>
        <source>The most videos one run reads; 0: all the page lists</source>
        <translation>Die meisten Videos, die ein Lauf liest; 0: alle, die die Seite auflistet</translation>
    </message>
    <message>
        <source>Pause (seconds)</source>
        <translation>Pause (Sekunden)</translation>
    </message>
    <message>
        <source>The pause before reading the page (a longer Crawl-delay of robots.txt wins)</source>
        <translation>Die Pause vor dem Lesen der Seite (ein längeres Crawl-delay der robots.txt gewinnt)</translation>
    </message>
    <message>
        <source>The address of the list, https (http only for a host of [RestApiSourceSettings] AllowedHosts[]); parameters go into the option below</source>
        <translation>Die Adresse der Liste, https (http nur für einen Host aus [RestApiSourceSettings] AllowedHosts[]); Parameter gehören in die Option unten</translation>
    </message>
    <message>
        <source>Method</source>
        <translation>Methode</translation>
    </message>
    <message>
        <source>POST with a JSON body (only an endpoint that reads: a search or query)</source>
        <translation>POST mit JSON-Inhalt (nur ein lesender Endpunkt: eine Suche oder Abfrage)</translation>
    </message>
    <message>
        <source>JSON body (POST)</source>
        <translation>JSON-Inhalt (POST)</translation>
    </message>
    <message>
        <source>POST only: the JSON object sent; the paging parameters are set in it. Only for an endpoint that reads</source>
        <translation>Nur bei POST: das gesendete JSON-Objekt; die Parameter zum Blättern werden darin gesetzt. Nur für einen lesenden Endpunkt</translation>
    </message>
    <message>
        <source>One per line as name=value (status=active); never a key or token, they belong to the authentication</source>
        <translation>Einer je Zeile als name=wert (status=active); nie ein Schlüssel oder Token, die gehören zur Authentifizierung</translation>
    </message>
    <message>
        <source>Extra headers</source>
        <translation>Zusätzliche Header</translation>
    </message>
    <message>
        <source>One per line as Name: value; only Accept, Accept-Language and X-... (never Authorization, Cookie, Host or a key)</source>
        <translation>Einer je Zeile als Name: Wert; nur Accept, Accept-Language und X-... (nie Authorization, Cookie, Host oder ein Schlüssel)</translation>
    </message>
    <message>
        <source>The path of the list in an answer: data.items or $.data.items or results[0].hits; empty: the top, or data, items, results, records</source>
        <translation>Der Pfad der Liste in einer Antwort: data.items oder $.data.items oder results[0].hits; leer: die oberste Ebene oder data, items, results, records</translation>
    </message>
    <message>
        <source>Bearer token</source>
        <translation>Bearer-Token</translation>
    </message>
    <message>
        <source>Basic (user and password)</source>
        <translation>Basic (Benutzer und Passwort)</translation>
    </message>
    <message>
        <source>API key in a header</source>
        <translation>API-Schlüssel in einem Header</translation>
    </message>
    <message>
        <source>API key in a parameter</source>
        <translation>API-Schlüssel in einem Parameter</translation>
    </message>
    <message>
        <source>OAuth 2.0 client credentials</source>
        <translation>OAuth-2.0-Client-Credentials</translation>
    </message>
    <message>
        <source>Secret (name)</source>
        <translation>Geheimnis (Name)</translation>
    </message>
    <message>
        <source>The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE: the token, the password, the API key or the client secret</source>
        <translation>Der Name eines Geheimnisses aus [SecretSettings] Secrets[] oder env:VARIABLE: das Token, das Passwort, der API-Schlüssel oder das Client-Secret</translation>
    </message>
    <message>
        <source>User or client ID</source>
        <translation>Benutzer oder Client-ID</translation>
    </message>
    <message>
        <source>Basic: the user; OAuth 2.0: the client ID</source>
        <translation>Basic: der Benutzer; OAuth 2.0: die Client-ID</translation>
    </message>
    <message>
        <source>API key header or parameter</source>
        <translation>Header oder Parameter des API-Schlüssels</translation>
    </message>
    <message>
        <source>The name of the header (X-Api-Key) or of the parameter (api_key) that carries the key</source>
        <translation>Der Name des Headers (X-Api-Key) oder des Parameters (api_key), der den Schlüssel trägt</translation>
    </message>
    <message>
        <source>OAuth 2.0 token address</source>
        <translation>Token-Adresse von OAuth 2.0</translation>
    </message>
    <message>
        <source>On the host of the address, or on one of [RestApiSourceSettings] AllowedTokenHosts[]</source>
        <translation>Auf dem Host der Adresse oder auf einem von [RestApiSourceSettings] AllowedTokenHosts[]</translation>
    </message>
    <message>
        <source>OAuth 2.0 scope</source>
        <translation>Bereich von OAuth 2.0</translation>
    </message>
    <message>
        <source>Optional; a read scope</source>
        <translation>Optional; ein Lesebereich</translation>
    </message>
    <message>
        <source>OAuth 2.0 client authentication</source>
        <translation>Client-Authentifizierung von OAuth 2.0</translation>
    </message>
    <message>
        <source>Basic header (RFC 6749)</source>
        <translation>Basic-Header (RFC 6749)</translation>
    </message>
    <message>
        <source>client_id and client_secret in the body</source>
        <translation>client_id und client_secret im Inhalt</translation>
    </message>
    <message>
        <source>Page number</source>
        <translation>Seitenzahl</translation>
    </message>
    <message>
        <source>Offset and limit</source>
        <translation>Offset und Limit</translation>
    </message>
    <message>
        <source>Cursor from the answer</source>
        <translation>Cursor aus der Antwort</translation>
    </message>
    <message>
        <source>Next address from the answer</source>
        <translation>Nächste Adresse aus der Antwort</translation>
    </message>
    <message>
        <source>Link header rel="next"</source>
        <translation>Link-Header rel="next"</translation>
    </message>
    <message>
        <source>Page parameter</source>
        <translation>Seitenparameter</translation>
    </message>
    <message>
        <source>First page</source>
        <translation>Erste Seite</translation>
    </message>
    <message>
        <source>Page size parameter</source>
        <translation>Parameter der Seitengröße</translation>
    </message>
    <message>
        <source>Page number, cursor, next address and Link: the parameter of the page size (per_page, limit); empty: none is sent and the server's own page size applies</source>
        <translation>Seitenzahl, Cursor, nächste Adresse und Link: der Parameter der Seitengröße (per_page, limit); leer: keiner wird gesendet, und die eigene Seitengröße des Servers gilt</translation>
    </message>
    <message>
        <source>Offset parameter</source>
        <translation>Offset-Parameter</translation>
    </message>
    <message>
        <source>Limit parameter</source>
        <translation>Limit-Parameter</translation>
    </message>
    <message>
        <source>Cursor path</source>
        <translation>Cursor-Pfad</translation>
    </message>
    <message>
        <source>Cursor: the path of the next cursor in an answer, e.g. meta.next_cursor</source>
        <translation>Cursor: der Pfad des nächsten Cursors in einer Antwort, z. B. meta.next_cursor</translation>
    </message>
    <message>
        <source>Cursor parameter</source>
        <translation>Cursor-Parameter</translation>
    </message>
    <message>
        <source>Next address path</source>
        <translation>Pfad der nächsten Adresse</translation>
    </message>
    <message>
        <source>Next address: its path in an answer, e.g. links.next (same site only)</source>
        <translation>Nächste Adresse: ihr Pfad in einer Antwort, z. B. links.next (nur dieselbe Site)</translation>
    </message>
    <message>
        <source>Total path</source>
        <translation>Pfad der Gesamtzahl</translation>
    </message>
    <message>
        <source>Optional: the path of the total count in an answer, e.g. meta.total</source>
        <translation>Optional: der Pfad der Gesamtzahl in einer Antwort, z. B. meta.total</translation>
    </message>
    <message>
        <source>Most pages</source>
        <translation>Höchstzahl an Seiten</translation>
    </message>
    <message>
        <source>Stop after so many pages; 0: up to [RestApiSourceSettings] MaxPages (more is an error)</source>
        <translation>Nach so vielen Seiten aufhören; 0: bis zu [RestApiSourceSettings] MaxPages (mehr ist ein Fehler)</translation>
    </message>
    <message>
        <source>Most records</source>
        <translation>Höchstzahl an Datensätzen</translation>
    </message>
    <message>
        <source>0: every record</source>
        <translation>0: jeder Datensatz</translation>
    </message>
    <message>
        <source>Least time between requests (seconds)</source>
        <translation>Mindestzeit zwischen Anfragen (Sekunden)</translation>
    </message>
    <message>
        <source>Images only (JPEG, PNG, GIF, WebP, TIFF)</source>
        <translation>Nur Bilder (JPEG, PNG, GIF, WebP, TIFF)</translation>
    </message>
    <message>
        <source>Every file</source>
        <translation>Jede Datei</translation>
    </message>
    <message>
        <source>A folder in the ZIP; empty: all</source>
        <translation>Ein Ordner im ZIP; leer: alle</translation>
    </message>
    <message>
        <source>The field that names a record: path, or sha1 to import the same file once wherever it is</source>
        <translation>Das Feld, das einen Datensatz benennt: path oder sha1, um dieselbe Datei nur einmal zu importieren, wo immer sie liegt</translation>
    </message>
    <message>
        <source>Events (VEVENT)</source>
        <translation>Termine (VEVENT)</translation>
    </message>
    <message>
        <source>Events and tasks (VEVENT, VTODO)</source>
        <translation>Termine und Aufgaben (VEVENT, VTODO)</translation>
    </message>
    <message>
        <source>Tasks (VTODO)</source>
        <translation>Aufgaben (VTODO)</translation>
    </message>
    <message>
        <source>Time zone</source>
        <translation>Zeitzone</translation>
    </message>
    <message>
        <source>The zone of times without a zone and of all-day dates, e.g. Europe/Berlin</source>
        <translation>Die Zone von Uhrzeiten ohne Zone und von ganztägigen Daten, z. B. Europe/Berlin</translation>
    </message>
    <message>
        <source>The field that names a record. id: the UID, with the date of a changed occurrence.</source>
        <translation>Das Feld, das einen Datensatz benennt. id: die UID, mit dem Datum eines geänderten Vorkommens.</translation>
    </message>
    <message>
        <source>The field that names a record. id: the UID, else a hash of name and e-mail address.</source>
        <translation>Das Feld, das einen Datensatz benennt. id: die UID, sonst ein Hash aus Name und E-Mail-Adresse.</translation>
    </message>
    <message>
        <source>The field that names a product; id is g:id</source>
        <translation>Das Feld, das ein Produkt benennt; id ist g:id</translation>
    </message>
    <message>
        <source>Currency</source>
        <translation>Währung</translation>
    </message>
    <message>
        <source>The ISO 4217 code of the prices (EUR, USD); the export does not name it</source>
        <translation>Der ISO-4217-Code der Preise (EUR, USD); der Export nennt ihn nicht</translation>
    </message>
    <message>
        <source>The field that names a product</source>
        <translation>Das Feld, das ein Produkt benennt</translation>
    </message>
    <message>
        <source>Variations</source>
        <translation>Varianten</translation>
    </message>
    <message>
        <source>In the list of variants of their product</source>
        <translation>In der Liste der Varianten ihres Produkts</translation>
    </message>
    <message>
        <source>Records of their own</source>
        <translation>Eigene Datensätze</translation>
    </message>
    <message>
        <source>Data table</source>
        <translation>Datentabelle</translation>
    </message>
    <message>
        <source>The persistent class whose table the rows go into (Setup &gt; Data tables, Exponential 6.0.15)</source>
        <translation>Die persistente Klasse, in deren Tabelle die Zeilen kommen (Setup &gt; Datentabellen, Exponential 6.0.15)</translation>
    </message>
    <message>
        <source>Rows</source>
        <translation>Zeilen</translation>
    </message>
    <message>
        <source>Add new rows, update existing ones</source>
        <translation>Neue Zeilen hinzufügen, vorhandene aktualisieren</translation>
    </message>
    <message>
        <source>Only add new rows</source>
        <translation>Nur neue Zeilen hinzufügen</translation>
    </message>
    <message>
        <source>Only update existing rows</source>
        <translation>Nur vorhandene Zeilen aktualisieren</translation>
    </message>
    <message>
        <source>Replace existing rows (removed and written again)</source>
        <translation>Vorhandene Zeilen ersetzen (entfernt und neu geschrieben)</translation>
    </message>
    <message>
        <source>Match rows by</source>
        <translation>Zeilen abgleichen über</translation>
    </message>
    <message>
        <source>One column per line that names a row (e.g. sku). Empty: the keys of the class</source>
        <translation>Eine Spalte je Zeile, die eine Zeile benennt (z. B. sku). Leer: die Schlüssel der Klasse</translation>
    </message>
    <message>
        <source>Columns</source>
        <translation>Spalten</translation>
    </message>
    <message>
        <source>One line per column: column = field of the record | transforms (e.g. price = price | number). Empty: every field whose name is a column</source>
        <translation>Eine Zeile je Spalte: spalte = feld des datensatzes | umwandlungen (z. B. price = price | number). Leer: jedes Feld, dessen Name eine Spalte ist</translation>
    </message>
    <message>
        <source>The persistent class whose table the rows go into (Setup &gt; Data tables)</source>
        <translation>Die persistente Klasse, in deren Tabelle die Zeilen kommen (Setup &gt; Datentabellen)</translation>
    </message>
    <message>
        <source>empty: keep the value</source>
        <translation>leer: den Wert behalten</translation>
    </message>
    <message>
        <source>PHP date format of the source; excel: a spreadsheet date (days since 1899); empty: any form strtotime() reads</source>
        <translation>PHP-Datumsformat der Quelle; excel: ein Datum der Tabellenkalkulation (Tage seit 1899); leer: jede Form, die strtotime() liest</translation>
    </message>
    <message>
        <source>embed: every &lt;img&gt; of a file of the run (run:, source:) becomes an image object embedded in the text; drop: images are left out</source>
        <translation>embed: jedes &lt;img&gt; einer Datei des Laufs (run:, source:) wird zu einem im Text eingebetteten Bildobjekt; drop: Bilder werden ausgelassen</translation>
    </message>
    <message>
        <source>the node the image objects are created below (default: the import's parent node)</source>
        <translation>der Knoten, unter dem die Bildobjekte angelegt werden (Vorgabe: der Elternknoten des Imports)</translation>
    </message>
    <message>
        <source>the image alias of the embed (small, medium, large, original)</source>
        <translation>der Bildalias der Einbettung (small, medium, large, original)</translation>
    </message>
    <message>
        <source>put before the value to make the remote ID</source>
        <translation>vor den Wert gesetzt, um die Remote-ID zu bilden</translation>
    </message>
    <message>
        <source>the record fails when the image cannot be had (default: it is imported without it, with a note)</source>
        <translation>der Datensatz schlägt fehl, wenn das Bild nicht zu bekommen ist (Vorgabe: er wird ohne es importiert, mit einem Hinweis)</translation>
    </message>
    <message>
        <source>the record fails when the file cannot be had (default: it is imported without it, with a note)</source>
        <translation>der Datensatz schlägt fehl, wenn die Datei nicht zu bekommen ist (Vorgabe: er wird ohne sie importiert, mit einem Hinweis)</translation>
    </message>
    <message>
        <source>the node the media objects are created below (default: the import's parent node)</source>
        <translation>der Knoten, unter dem die Medienobjekte angelegt werden (Vorgabe: der Elternknoten des Imports)</translation>
    </message>
    <message>
        <source>the class of a media object (default image)</source>
        <translation>die Klasse eines Medienobjekts (Vorgabe image)</translation>
    </message>
    <message>
        <source>its file attribute (default image)</source>
        <translation>sein Dateiattribut (Vorgabe image)</translation>
    </message>
    <message>
        <source>a field of the record for its name (default: the file name)</source>
        <translation>ein Feld des Datensatzes für seinen Namen (Vorgabe: der Dateiname)</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/outcome</name>
    <message>
        <source>Created</source>
        <translation>Angelegt</translation>
    </message>
    <message>
        <source>Updated</source>
        <translation>Aktualisiert</translation>
    </message>
    <message>
        <source>Unchanged</source>
        <translation>Unverändert</translation>
    </message>
    <message>
        <source>Skipped</source>
        <translation>Übersprungen</translation>
    </message>
    <message>
        <source>Failed</source>
        <translation>Fehlgeschlagen</translation>
    </message>
    <message>
        <source>Processed (1.x)</source>
        <translation>Verarbeitet (1.x)</translation>
    </message>
    <message>
        <source>Undone</source>
        <translation>Rückgängig gemacht</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/schedule</name>
    <message>
        <source>unknown schedule kind %kind</source>
        <translation>Unbekannte Art des Zeitplans %kind</translation>
    </message>
    <message>
        <source>unknown time zone %zone</source>
        <translation>Unbekannte Zeitzone %zone</translation>
    </message>
    <message>
        <source>an interval is at least %n minutes</source>
        <translation>Ein Intervall beträgt mindestens %n Minuten</translation>
    </message>
    <message>
        <source>choose the first date and time</source>
        <translation>Wählen Sie das erste Datum und die erste Uhrzeit</translation>
    </message>
    <message>
        <source>once at %datetime (%zone)</source>
        <translation>einmal am %datetime (%zone)</translation>
    </message>
    <message>
        <source>every %n minutes</source>
        <translation>alle %n Minuten</translation>
    </message>
    <message>
        <source>hourly at minute %minute</source>
        <translation>stündlich zur Minute %minute</translation>
    </message>
    <message>
        <source>daily at %time (%zone)</source>
        <translation>täglich um %time (%zone)</translation>
    </message>
    <message>
        <source>Sunday</source>
        <translation>Sonntag</translation>
    </message>
    <message>
        <source>Monday</source>
        <translation>Montag</translation>
    </message>
    <message>
        <source>Tuesday</source>
        <translation>Dienstag</translation>
    </message>
    <message>
        <source>Wednesday</source>
        <translation>Mittwoch</translation>
    </message>
    <message>
        <source>Thursday</source>
        <translation>Donnerstag</translation>
    </message>
    <message>
        <source>Friday</source>
        <translation>Freitag</translation>
    </message>
    <message>
        <source>Saturday</source>
        <translation>Samstag</translation>
    </message>
    <message>
        <source>weekly on %day at %time (%zone)</source>
        <translation>wöchentlich am %day um %time (%zone)</translation>
    </message>
    <message>
        <source>monthly on day %day at %time (%zone)</source>
        <translation>monatlich am %day. um %time (%zone)</translation>
    </message>
    <message>
        <source>cron %expression (%zone)</source>
        <translation>Cron %expression (%zone)</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/schedulefrequency</name>
    <message>
        <source>Frequency</source>
        <translation>Häufigkeit</translation>
    </message>
    <message>
        <source>Once</source>
        <translation>Einmal</translation>
    </message>
    <message>
        <source>Hourly</source>
        <translation>Stündlich</translation>
    </message>
    <message>
        <source>Daily</source>
        <translation>Täglich</translation>
    </message>
    <message>
        <source>Weekly</source>
        <translation>Wöchentlich</translation>
    </message>
    <message>
        <source>Monthly</source>
        <translation>Monatlich</translation>
    </message>
    <message>
        <source>Every N minutes</source>
        <translation>Alle N Minuten</translation>
    </message>
    <message>
        <source>Every %n minutes</source>
        <translation>Alle %n Minuten</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/statistics</name>
    <message>
        <source>The last %days days</source>
        <translation>Die letzten %days Tage</translation>
    </message>
    <message>
        <source>Statistics</source>
        <translation>Statistik</translation>
    </message>
    <message>
        <source>No run in the last %days days. Dry runs are not counted here.</source>
        <translation>Kein Lauf in den letzten %days Tagen. Probeläufe werden hier nicht gezählt.</translation>
    </message>
    <message>
        <source>Figures of the last %days days</source>
        <translation>Zahlen der letzten %days Tage</translation>
    </message>
    <message>
        <source>Runs</source>
        <translation>Läufe</translation>
    </message>
    <message>
        <source>Runs without errors</source>
        <translation>Läufe ohne Fehler</translation>
    </message>
    <message>
        <source>Records processed</source>
        <translation>Verarbeitete Datensätze</translation>
    </message>
    <message>
        <source>Average duration</source>
        <translation>Durchschnittliche Dauer</translation>
    </message>
    <message>
        <source>Slowest run: %name</source>
        <translation>Langsamster Lauf: %name</translation>
    </message>
    <message>
        <source>Slowest run</source>
        <translation>Langsamster Lauf</translation>
    </message>
    <message>
        <source>Records per day</source>
        <translation>Datensätze je Tag</translation>
    </message>
    <message>
        <source>Day</source>
        <translation>Tag</translation>
    </message>
    <message>
        <source>Records per day of the last %days days, by outcome</source>
        <translation>Datensätze je Tag der letzten %days Tage, nach Ergebnis</translation>
    </message>
    <message>
        <source>Imports with the most failures</source>
        <translation>Importe mit den meisten Fehlern</translation>
    </message>
    <message>
        <source>No run had errors in the last %days days.</source>
        <translation>Kein Lauf hatte in den letzten %days Tagen Fehler.</translation>
    </message>
    <message>
        <source>%runs of %all runs with errors · %rate of records failed</source>
        <translation>%runs von %all Läufen mit Fehlern · %rate der Datensätze fehlgeschlagen</translation>
    </message>
    <message>
        <source>The table below the chart has the numbers.</source>
        <translation>Die Tabelle unter dem Diagramm enthält die Zahlen.</translation>
    </message>
    <message>
        <source>Legend</source>
        <translation>Legende</translation>
    </message>
    <message>
        <source>highest bar: %n</source>
        <translation>höchster Balken: %n</translation>
    </message>
    <message>
        <source>Show as a table</source>
        <translation>Als Tabelle anzeigen</translation>
    </message>
    <message>
        <source>total</source>
        <translation>gesamt</translation>
    </message>
    <message>
        <source>Total</source>
        <translation>Gesamt</translation>
    </message>
    <message>
        <source>Import statistics</source>
        <translation>Importstatistik</translation>
    </message>
    <message>
        <source>The run history of the imports: how often they ran, how many records they processed, how long they took and how often they failed, per day, week or month, compared with another period. Dry runs and undo runs are left out unless you choose them.</source>
        <translation>Die Historie der Läufe der Importe: wie oft sie liefen, wie viele Datensätze sie verarbeiteten, wie lange sie dauerten und wie oft sie fehlschlugen, je Tag, Woche oder Monat, verglichen mit einem anderen Zeitraum. Probeläufe und Läufe zum Rückgängigmachen bleiben außen vor, sofern Sie sie nicht wählen.</translation>
    </message>
    <message>
        <source>Week</source>
        <translation>Woche</translation>
    </message>
    <message>
        <source>Month</source>
        <translation>Monat</translation>
    </message>
    <message>
        <source>per day</source>
        <translation>je Tag</translation>
    </message>
    <message>
        <source>per week</source>
        <translation>je Woche</translation>
    </message>
    <message>
        <source>per month</source>
        <translation>je Monat</translation>
    </message>
    <message>
        <source>Period</source>
        <translation>Zeitraum</translation>
    </message>
    <message>
        <source>%n days</source>
        <translation>%n Tage</translation>
    </message>
    <message>
        <source>Period and filters</source>
        <translation>Zeitraum und Filter</translation>
    </message>
    <message>
        <source>Last</source>
        <translation>Letzte</translation>
    </message>
    <message>
        <source>Or from</source>
        <translation>Oder von</translation>
    </message>
    <message>
        <source>to</source>
        <translation>bis</translation>
    </message>
    <message>
        <source>Source type</source>
        <translation>Quelltyp</translation>
    </message>
    <message>
        <source>User</source>
        <translation>Benutzer</translation>
    </message>
    <message>
        <source>Tool</source>
        <translation>Werkzeug</translation>
    </message>
    <message>
        <source>Imports</source>
        <translation>Importe</translation>
    </message>
    <message>
        <source>All import and export tools</source>
        <translation>Alle Import- und Exportwerkzeuge</translation>
    </message>
    <message>
        <source>Imports and retries</source>
        <translation>Importe und Wiederholungen</translation>
    </message>
    <message>
        <source>Dry runs only</source>
        <translation>Nur Probeläufe</translation>
    </message>
    <message>
        <source>All, with dry runs and undo</source>
        <translation>Alle, mit Probeläufen und Rückgängigmachen</translation>
    </message>
    <message>
        <source>Compare with</source>
        <translation>Vergleichen mit</translation>
    </message>
    <message>
        <source>The period before</source>
        <translation>Dem Zeitraum davor</translation>
    </message>
    <message>
        <source>The period below</source>
        <translation>Dem Zeitraum unten</translation>
    </message>
    <message>
        <source>Nothing</source>
        <translation>Nichts</translation>
    </message>
    <message>
        <source>Second period from</source>
        <translation>Zweiter Zeitraum von</translation>
    </message>
    <message>
        <source>Second period to</source>
        <translation>Zweiter Zeitraum bis</translation>
    </message>
    <message>
        <source>Show</source>
        <translation>Anzeigen</translation>
    </message>
    <message>
        <source>Reset</source>
        <translation>Zurücksetzen</translation>
    </message>
    <message>
        <source>From and to, when both are given, replace the last days. The days follow the time zone of the site.</source>
        <translation>Von und bis ersetzen, wenn beide angegeben sind, die letzten Tage. Die Tage folgen der Zeitzone der Site.</translation>
    </message>
    <message>
        <source>%from to %to</source>
        <translation>%from bis %to</translation>
    </message>
    <message>
        <source>Table as CSV</source>
        <translation>Tabelle als CSV</translation>
    </message>
    <message>
        <source>This period has more runs than the page reads at once; the figures are from the oldest ones. Choose a shorter period or an import.</source>
        <translation>Dieser Zeitraum hat mehr Läufe, als die Seite auf einmal liest; die Zahlen stammen von den ältesten. Wählen Sie einen kürzeren Zeitraum oder einen Import.</translation>
    </message>
    <message>
        <source>No run in this period.</source>
        <translation>Kein Lauf in diesem Zeitraum.</translation>
    </message>
    <message>
        <source>Choose a longer period or fewer filters. A run counts on the day it started.</source>
        <translation>Wählen Sie einen längeren Zeitraum oder weniger Filter. Ein Lauf zählt an dem Tag, an dem er begann.</translation>
    </message>
    <message>
        <source>Key figures of the period</source>
        <translation>Kennzahlen des Zeitraums</translation>
    </message>
    <message>
        <source>%n cancelled</source>
        <translation>%n abgebrochen</translation>
    </message>
    <message>
        <source>Records failed (%n)</source>
        <translation>Fehlgeschlagene Datensätze (%n)</translation>
    </message>
    <message>
        <source>95 % of runs took at most</source>
        <translation>95 % der Läufe dauerten höchstens</translation>
    </message>
    <message>
        <source>Records per second</source>
        <translation>Datensätze je Sekunde</translation>
    </message>
    <message>
        <source>Suggestions</source>
        <translation>Vorschläge</translation>
    </message>
    <message>
        <source>Look at this import</source>
        <translation>Diesen Import ansehen</translation>
    </message>
    <message>
        <source>Trends</source>
        <translation>Trends</translation>
    </message>
    <message>
        <source>Cancelled or running</source>
        <translation>Abgebrochen oder laufend</translation>
    </message>
    <message>
        <source>Records</source>
        <translation>Datensätze</translation>
    </message>
    <message>
        <source>Records by outcome</source>
        <translation>Datensätze nach Ergebnis</translation>
    </message>
    <message>
        <source>Average duration of a run</source>
        <translation>Durchschnittliche Dauer eines Laufs</translation>
    </message>
    <message>
        <source>Failure rate of records</source>
        <translation>Fehlerquote der Datensätze</translation>
    </message>
    <message>
        <source>Records failed</source>
        <translation>Fehlgeschlagene Datensätze</translation>
    </message>
    <message>
        <source>Per tool</source>
        <translation>Je Werkzeug</translation>
    </message>
    <message>
        <source>Average</source>
        <translation>Durchschnitt</translation>
    </message>
    <message>
        <source>Failure rate</source>
        <translation>Fehlerquote</translation>
    </message>
    <message>
        <source>Per import</source>
        <translation>Je Import</translation>
    </message>
    <message>
        <source>Records %per</source>
        <translation>Datensätze %per</translation>
    </message>
    <message>
        <source>95th percentile of the duration</source>
        <translation>95. Perzentil der Dauer</translation>
    </message>
    <message>
        <source>P95</source>
        <translation>P95</translation>
    </message>
    <message>
        <source>Records/s</source>
        <translation>Datensätze/s</translation>
    </message>
    <message>
        <source>slow since %date</source>
        <translation>langsam seit %date</translation>
    </message>
    <message>
        <source>Records %per: %values</source>
        <translation>Datensätze %per: %values</translation>
    </message>
    <message>
        <source>%n with errors</source>
        <translation>%n mit Fehlern</translation>
    </message>
    <message>
        <source>Most common errors</source>
        <translation>Häufigste Fehler</translation>
    </message>
    <message>
        <source>Comparison</source>
        <translation>Vergleich</translation>
    </message>
    <message>
        <source>%from – %to compared with %ofrom – %oto</source>
        <translation>%from – %to verglichen mit %ofrom – %oto</translation>
    </message>
    <message>
        <source>Figure</source>
        <translation>Kennzahl</translation>
    </message>
    <message>
        <source>This period</source>
        <translation>Dieser Zeitraum</translation>
    </message>
    <message>
        <source>Compared period</source>
        <translation>Vergleichszeitraum</translation>
    </message>
    <message>
        <source>Change</source>
        <translation>Veränderung</translation>
    </message>
    <message>
        <source>better</source>
        <translation>besser</translation>
    </message>
    <message>
        <source>worse</source>
        <translation>schlechter</translation>
    </message>
    <message>
        <source>numbers in the messages are shown as #, so the same error counts once</source>
        <translation>Zahlen in den Meldungen werden als # angezeigt, sodass derselbe Fehler einmal zählt</translation>
    </message>
    <message>
        <source>%n times, %share %</source>
        <translation>%n Mal, %share %</translation>
    </message>
    <message>
        <source>%name fails on the field %field in %share % of its records (%count records). Check the source values or the transform of this field.</source>
        <translation>%name schlägt beim Feld %field in %share % seiner Datensätze fehl (%count Datensätze). Prüfen Sie die Werte der Quelle oder die Umwandlung dieses Felds.</translation>
    </message>
    <message>
        <source>%name is slow since %date: %factor times the time per record of before (%now instead of %then per 100 records). Look at what changed then: the source, the mapping or the server.</source>
        <translation>%name ist seit %date langsam: das %factor-Fache der Zeit je Datensatz von vorher (%now statt %then je 100 Datensätze). Sehen Sie nach, was sich damals geändert hat: die Quelle, die Zuordnung oder der Server.</translation>
    </message>
    <message>
        <source>%share % of the runs of %name ended with errors (%runs runs).</source>
        <translation>%share % der Läufe von %name endeten mit Fehlern (%runs Läufe).</translation>
    </message>
    <message>
        <source>The most common error: %error</source>
        <translation>Der häufigste Fehler: %error</translation>
    </message>
    <message>
        <source>%share % of the records of %name were unchanged over %runs runs: a less frequent schedule may do.</source>
        <translation>%share % der Datensätze von %name waren über %runs Läufe unverändert: ein seltenerer Zeitplan könnte genügen.</translation>
    </message>
    <message>
        <source>Failed records</source>
        <translation>Fehlgeschlagene Datensätze</translation>
    </message>
    <message>
        <source>Successful runs</source>
        <translation>Erfolgreiche Läufe</translation>
    </message>
    <message>
        <source>Duration, 95th percentile</source>
        <translation>Dauer, 95. Perzentil</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/status</name>
    <message>
        <source>queued</source>
        <translation>wartend</translation>
    </message>
    <message>
        <source>Completed</source>
        <translation>Abgeschlossen</translation>
    </message>
    <message>
        <source>Completed with errors</source>
        <translation>Mit Fehlern abgeschlossen</translation>
    </message>
    <message>
        <source>Failed</source>
        <translation>Fehlgeschlagen</translation>
    </message>
    <message>
        <source>Queued</source>
        <translation>In der Warteschlange</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>Läuft</translation>
    </message>
    <message>
        <source>Cancelled</source>
        <translation>Abgebrochen</translation>
    </message>
    <message>
        <source>Running or queued</source>
        <translation>Laufend oder wartend</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/trigger</name>
    <message>
        <source>Started by hand</source>
        <translation>Von Hand gestartet</translation>
    </message>
    <message>
        <source>Schedule</source>
        <translation>Zeitplan</translation>
    </message>
    <message>
        <source>Command line</source>
        <translation>Kommandozeile</translation>
    </message>
    <message>
        <source>REST API</source>
        <translation>REST-API</translation>
    </message>
    <message>
        <source>1.x import request</source>
        <translation>Importauftrag (1.x)</translation>
    </message>
</context>
<context>
    <name>extension/sqliimport/type</name>
    <message>
        <source>Scheduled</source>
        <translation>Geplant</translation>
    </message>
    <message>
        <source>Immediate</source>
        <translation>Sofort</translation>
    </message>
    <message>
        <source>Pending</source>
        <translation>Wartend</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>Läuft</translation>
    </message>
    <message>
        <source>Failed</source>
        <translation>Fehlgeschlagen</translation>
    </message>
    <message>
        <source>Completed</source>
        <translation>Abgeschlossen</translation>
    </message>
    <message>
        <source>Interrupted</source>
        <translation>Unterbrochen</translation>
    </message>
    <message>
        <source>Canceled</source>
        <translation>Abgebrochen</translation>
    </message>
</context>
<context>
    <name>kernel/navigationpart</name>
    <message>
        <source>Import management</source>
        <translation>Importverwaltung</translation>
    </message>
</context>
<context>
    <name>kernel/transfer</name>
    <message>
        <source>Imports (7x SQLI Import)</source>
        <translation>Importe (7x SQLI Import)</translation>
    </message>
    <message>
        <source>Bring rows of a spreadsheet, CSV, XML, JSON or a feed in as content items: map the columns to a class, preview, run now or on a schedule, undo.</source>
        <translation>Zeilen einer Tabelle, einer CSV-, XML- oder JSON-Datei oder eines Feeds als Inhaltsobjekte übernehmen: die Spalten einer Klasse zuordnen, Vorschau, sofort oder nach Zeitplan ausführen, rückgängig machen.</translation>
    </message>
    <message>
        <source>Import into this folder…</source>
        <translation>In diesen Ordner importieren …</translation>
    </message>
    <message>
        <source>Import objects of this class…</source>
        <translation>Objekte dieser Klasse importieren …</translation>
    </message>
</context>
</TS>
