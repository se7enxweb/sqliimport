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
- **Aus einem anderen System gestartet:** der Webhook-/API-Auslöser eines Imports startet einen Lauf mit einem
  signierten Aufruf oder einem API-Schlüssel, als Laufbenutzer des Auslösers, und kann die Daten des Laufs im Aufruf
  mitbringen (Auslöser `rest`, [triggers.md](triggers.md)).

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
| `dry_run` | Veröffentlicht nichts. Liest die ersten `[RunSettings] DryRunRows` Datensätze und vermerkt, was geschehen würde, mit den geänderten Feldern. Auch ein deaktivierter Import kann einen Probelauf haben. Bilder und Dateien werden nicht heruntergeladen. Der Schritt Vorschau des Editors tut dasselbe in der Anfrage, ohne einen Lauf zu speichern. |
| `retry` | Verarbeitet nur die fehlgeschlagenen Zeilen eines früheren Laufs, aus den an diesen Zeilen gespeicherten Datensätzen |

## Zeilen und Protokoll

Jeder Datensatz eines Laufs bekommt eine Zeile: seinen Verweis, sein Ergebnis (`created`, `updated`, `unchanged`,
`skipped`, `failed`, `processed`), das Objekt, die Version davor und danach, die hinzugefügten Knoten und eine
Meldung. Eine fehlgeschlagene Zeile behält auch den Datensatz, damit er wiederholt werden kann. Das Protokoll
behält Zeilen ab `[RunSettings] LogLevel` aufwärts (`debug`, `info`, `notice`, `warning`, `error`), jede mit dem
Datensatz, um den es geht.

## In der Administration

Die Beschriftungen der Seiten sind noch nicht vollständig übersetzt; sie stehen hier so, wie die Seite sie zeigt.
Das linke Menü des Reiters Importe verlinkt die Seiten der Erweiterung:

| Seite | Adresse | Was sie zeigt |
|---|---|---|
| Overview | `sqliimport/list` | Die Übersicht (unten) |
| New import | `sqliimport/edit/new` | Den Import-Editor ([editor.md](editor.md)) |
| Running runs, Failed runs | `sqliimport/list/(status)/active`, `sqliimport/list/(status)/failed` | Die Übersicht, nach Status gefiltert |
| Schedules | `sqliimport/schedules` | Jeden Zeitplan mit seinen nächsten Läufen ([schedules.md](schedules.md#die-seite-schedules)) |
| Job queue | `expqueue/dashboard` | Die Job-Warteschlange von expqueue |
| Catalogue | `sqliimport/catalogue` | Die Quelltypen, Handler und Umwandlungen der Website mit ihren Optionen und ihrem Status |
| Settings | `sqliimport/settings` | Die Einstellungen, wie sie gelten, Export und Import der Definitionen ([settings.md](settings.md#die-seite-settings)) |

Die Seiten von 1.x sind Teil dieser Seiten: die Importaufträge von 1.x stehen in der Übersicht, die geplanten Importe
von 1.x unter Schedules; ihre alten Adressen leiten dorthin weiter
([migration-1x.md](migration-1x.md#nach-der-aktualisierung)).

### Die Übersicht

**Importe** (`sqliimport/list`) ist die Übersicht:

- Kennzahlen: Importe, Läufe heute, laufende oder wartende Läufe, in den letzten 24 Stunden fehlgeschlagene Läufe,
  der nächste geplante Lauf.
- Eine Leiste **Run now** (jetzt ausführen): einen Import wählen und starten, und die Schaltfläche **New import**.
- Die Importe mit Quelle, Handler, letztem und nächstem Lauf. **Run now** (bei einem aktivierten Import) und **Dry
  run** (Probelauf) sehen Benutzer, die den Import ausführen dürfen; **Edit** und unter **More** die Aktionen
  **Duplicate**, **Enable** oder **Disable** und **Delete** sehen Benutzer, die ihn verwalten dürfen
  ([editor.md](editor.md#ändern-kopieren-und-löschen)).
- Die Läufe, die neuesten zuerst, gefiltert nach Status: `sqliimport/list/(status)/active`, `failed`, `completed`
  oder `cancelled`, und nach Import: `sqliimport/list/(import)/<id>`.
- **1.x import requests** (gezeigt, wenn Handler von 1.x eingerichtet sind oder Aufträge bestehen; früher
  `sqliimport/items`): das Formular **Request import** (ein Handler von 1.x und seine Optionen, eine `name=wert` pro
  Zeile; früher `sqliimport/addimport`), **Purge history** (die beendeten Aufträge; manage) und jeder Auftrag mit
  Status, Fortschritt und Fortschrittsnotizen, einem Link zu seinem Lauf und **Cancel** (wartend) oder **Interrupt**
  (laufend) für Benutzer, die seinen Handler ausführen dürfen. Ältere Aufträge: `sqliimport/list/(legacyoffset)/<n>`.

Das Dashboard der Administration (`content/dashboard`) hat einen Block **Imports** mit den letzten Läufen und ihrem
Status sowie einem Hinweis mit Link, wenn in den letzten 24 Stunden Läufe fehlgeschlagen oder mit Fehlern beendet
sind (`dashboard.ini [DashboardBlock_sqliimport]`, `NumberOfItems=5`).

### Die Seite eines Laufs

**Die Seite eines Laufs** (`sqliimport/run/<id>`) zeigt:

- Den Status, den Fortschritt, die Dauer und die Speicherspitze sowie einen Link zu den Jobs des Laufs in der
  Job-Warteschlange.
- Die Ergebnisse als Kennzahlen. Jede filtert die Datensätze: `sqliimport/run/<id>/(outcome)/failed`.
- Die Datensätze mit Objekt, Version und Meldung. In einem Probelauf führt jeder Datensatz seine geänderten Felder
  mit aktuellem und neuem Wert auf.
- Das Protokoll.
- **Cancel** (abbrechen), solange der Lauf nicht beendet ist, und **Retry failed rows** (fehlgeschlagene Zeilen
  wiederholen), wenn er mit fehlgeschlagenen Zeilen endete.
- **Refresh**, das die Seite neu lädt.
- Mit der KI-Unterstützung ([ai.md](ai.md)): **Explain** neben der Meldung einer fehlgeschlagenen Zeile (eine kurze
  Erklärung und ein Vorschlag zur Behebung, aus der Meldung und den betroffenen Zuordnungszeilen, ohne Werte des
  Datensatzes, es sei denn, der Import wählt es) und **Summarize** für einen beendeten Lauf (eine kurze
  Zusammenfassung aus den eigenen Zahlen des Laufs, nie Werte von Datensätzen). Beide zeigen nur Text; nichts wird
  geändert.

Solange ein Lauf wartet oder läuft, verfolgt die Seite ihn live: Alle zwei Sekunden holt sie Status, Fortschritt,
Zähler und neue Protokollzeilen und zeigt sie an, ohne die Seite neu zu laden. Endet der Lauf, lädt die Seite noch
einmal mit den endgültigen Datensätzen. Bleibt eine Aktualisierung ohne Antwort (ein Netzwerkfehler, ein Neustart
des Servers), versucht die Seite es einige Male erneut und bleibt dann, wie sie ist; **Refresh** bringt sie auf den
neuesten Stand. Die Aktualisierungen kommen von der ezjscore-Serverfunktion `sqliimport::runstatus`, die nur
POST-Anfragen mit dem Formular-Token von Benutzern beantwortet, die die Importe sehen dürfen.

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

## Benachrichtigungen

Wer vom Ende eines Laufs erfährt, wird je Import im Schritt Verarbeitung des Editors unter **Notifications**
eingestellt:

| Einstellung | Wirkung |
|---|---|
| Tell when a run | `failed or had errors` (die Vorgabe), `failed`, `ended (every run)` oder `never` |
| A notice for the user who started the run | der Benutzer sieht den Hinweis auf der Übersicht der Importe (**New since you last looked**), bis er die Seite des Laufs öffnet oder **Mark all as read** wählt (Vorgabe: an) |
| E-mail addresses | eine je Zeile; sie erhalten eine E-Mail mit den Zahlen, den ersten Fehlern und dem Link zum Lauf |

Probeläufe und Rückgängig-Läufe geben ihrem Starter einen Hinweis, nie eine E-Mail. Die Adressen eines Zeitplans
(2.0.0-alpha.1) erhalten weiterhin eine E-Mail, wenn ein geplanter Lauf fehlschlägt oder mit Fehlern endet. Der Link
in einer E-Mail nutzt `[AdminSettings] AdminURL` der `sqliimport.ini`, wenn er gesetzt ist (z. B.
`https://edit.example.org/admin`), sonst einen Pfad.

**Die Glocke im Kopf.** Ab Exponential 6.0.15 haben admin4 und admin4l einen Platz für Werkzeuge der Erweiterungen im
Kopf (`design.ini [AdminHeaderSettings] HeaderItems[]`), und die Erweiterung stellt dort eine Glocke hin
(`design/admin4/templates/sqliimport/header_bell.tpl`), für jeden Benutzer, der die Importe sehen darf. Ihre Zahl ist
die der ungelesenen Hinweise des Benutzers und Teil ihres Namens („Import notifications: 3 new“); ihre Liste zeigt die
acht neuesten mit Lauf und Ergebnis, und **All imports** öffnet die Übersicht. Der Kopf wird je Benutzer zwischengespeichert,
darum lädt die Glocke Zahl und Einträge über den ezjscore-Aufruf `sqliimport::notices` (ein GET mit den eigenen
Hinweisen des Benutzers, geprüft gegen die Richtlinie sqliimport/view), wenn die Seite gezeichnet wird und jedes Mal,
wenn sie aufgeht. Ohne Skript ist sie ein Link auf die Übersicht. Wer sie nicht will, setzt `HeaderItems[]` leer in der
`design.ini.append.php` einer Siteaccess.

Mit einem älteren Kernel kann eine Seite die Hinweise über die Abruffunktion der Vorlagen zeigen, etwa:

```
{def $sq_bell = fetch( 'sqliimport', 'notices', hash( 'limit', 5 ) )}
{if $sq_bell.count}<a href={'/sqliimport/list'|ezurl}>{$sq_bell.count}</a>{/if}
```

Die Hinweise liegen in der Tabelle `sqliimport_notice` und gehen mit ihrem Lauf, wenn er bereinigt wird.

## Aufbewahrung

Beendete Läufe, die älter als `[RunSettings] KeepDays` (Vorgabe 90) sind, entfernt der Cron-Teil samt Zeilen,
Protokoll und Dateien, ebenso `./console ext:sqliimport:purge`. Wird ein Import entfernt, bleiben seine Läufe, bis
sie bereinigt werden.
