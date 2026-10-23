# Kommandozeile

Alle Befehle lauten `./console ext:sqliimport:<name>` und werden im Wurzelverzeichnis der Installation
ausgeführt. Jeder ist eine kleine Datei in `extension/sqliimport/bin/php/`, die `expImportCommand` aufruft.
`--help` zeigt die Optionen eines Befehls.

Englisch: [../cli.md](../cli.md).

Exit-Codes: `0` Erfolg, `1` Fehler oder ein falsches Argument, `2` nichts zu tun.

Als root gestartet, wechselt ein Befehl zum Benutzer der Website (wenn expqueue das anbietet), bevor er eine
Einstellung liest oder die Datenbank öffnet; nichts, was er schreibt, gehört dann root. Jeder Befehl außer
`install-schema` bricht mit einer Meldung ab, wenn die Tabellen fehlen.

## Inhalt

- [Die Befehle aufrufen: Benutzer und Siteaccess](#die-befehle-aufrufen-benutzer-und-siteaccess)
- [install-schema](#install-schema)
- [list](#list)
- [run](#run)
- [status](#status)
- [cancel](#cancel)
- [retry](#retry)
- [undo](#undo)
- [report](#report)
- [opml-feeds](#opml-feeds)
- [migrate-1x](#migrate-1x)
- [tick](#tick)
- [purge](#purge)
- [Das Skript von 1.x](#das-skript-von-1x)

## Die Befehle aufrufen: Benutzer und Siteaccess

Jedes Beispiel dieser Seite ist die echte Ausgabe des Befehls auf dem Server der Dokumentation, ausgeführt mit den
Importen „Docs: ...“. Sie liefen als Benutzer der Website, mit dem Siteaccess, in dem 7x SQLI Import aktiv ist:

```bash
runuser -u alpha -- php ./console ext:sqliimport:<befehl> ... -s admin --allow-root-user
```

- `./console` gibt zuerst die Zeile `▶  running ext:sqliimport:run  →  extension/sqliimport/bin/php/run.php` aus;
  sie fehlt unten.
- **`-s <siteaccess>`** wählt den Siteaccess. Ohne ihn prüft ein Befehl, ob die Erweiterung für alle Siteaccesses
  aktiv ist; ist sie nur in einigen aktiv (wie auf dem Server der Dokumentation nur im Siteaccess der Administration),
  nimmt der Befehl den ersten davon, `admin` zuerst, und sagt es auf der Fehlerausgabe:
  `NOTE 7x SQLI Import is not active in the default siteaccess; using -s admin`. Ist sie in keinem Siteaccess aktiv,
  bricht der Befehl mit einer FAIL-Zeile ab. Vor 2.0.0 las ein Befehl ohne `-s admin` die Einstellungen des
  Standard-Siteaccess, und `run` stellte einen Lauf ein, der sofort mit „unknown job type sqliimport.run“ scheiterte.
- Auf der Kommandozeile gestartete Läufe werden mit dem Auslöser `cli` und dem anonymen Benutzer (`user_id` 10)
  gespeichert; die Seite Statistik zählt sie unter dem Benutzer *anonymous*.
- `--allow-root-user` erlaubt einem Befehl, als root zu starten (er wechselt dann zum Benutzer der Website, wie oben
  gesagt); mit `runuser` ändert die Option nichts.

## install-schema

Legt die Tabellen von 7x SQLI Import an oder aktualisiert sie. Der Befehl ist idempotent und aktualisiert auch die
Tabellen von 1.x.

```bash
./console ext:sqliimport:install-schema                 # anlegen, was fehlt (Vorgabe)
./console ext:sqliimport:install-schema --check         # nur melden, was fehlt
./console ext:sqliimport:install-schema --print=mysql   # das SQL ausgeben: mysql, postgresql, sqlite oder oracle
./console ext:sqliimport:install-schema --write-files   # share/db_schema.dba und sql/*/schema.sql neu schreiben
```

**Beispiel** (`--check`, nur lesend):

```
database engine: sqlite
PASS the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_notice, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled, sqliimport_trigger, sqliimport_trigger_call, sqliimport_trigger_key exist with every column and index
```

`--print=sqlite` beginnt so:

```sql
CREATE TABLE sqliimport_definition (
  behaviour_json longtext,
  created INTEGER(11) NOT NULL DEFAULT '0',
  creator_id INTEGER(11) NOT NULL DEFAULT '0',
  def_version INTEGER(11) NOT NULL DEFAULT '0',
  description longtext,
  handler varchar(100) NOT NULL DEFAULT 'mapping',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifier varchar(100) NOT NULL DEFAULT '',
  ...
```

## list

Listet die Importe mit ID, Bezeichner, Quelle, Handler und letztem Lauf.

```bash
./console ext:sqliimport:list
./console ext:sqliimport:list --json
```

**Beispiel** (die ersten Zeilen):

```
   3  demo-releases-json             json   mapping              last run #6 completed
  76  demo_books_datatable           csv    datatable            last run #119 completed
   4  demo-feeds-opml                opml   mapping              last run #7 completed
   2  demo-php-news-atom             rss    mapping              last run #203 completed
   1  demo-products-csv              csv    mapping              last run #4 completed
 123  docs-files-json                json   mapping              last run #168 completed
 122  docs-files-ndjson              ndjson mapping              last run #167 completed
 103  docs-web-sitemap-crawl         sitemap mapping              last run #145 completed_with_errors
```

Mit `--json` ein Objekt je Import:

```json
[
    {
        "id": 3,
        "identifier": "demo-releases-json",
        "name": "Demo: 7x SQLI Import releases (JSON address)",
        "source": "json",
        "handler": "mapping",
        "enabled": true,
        "last_run": {
            "id": 6,
            "status": "completed"
        }
    },
    ...
]
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

**Beispiel: ein Probelauf** von „Docs: Products (CSV)“, dessen drei Ordner es gibt und die aktuell sind:

```
$ ./console ext:sqliimport:run docs-files-csv --dry-run --wait -s admin
PASS run #208 of docs-files-csv queued (job group 7ec78f23-a4da-4235-9bbb-2141e5da3ce2)
run #208 completed (dry_run, cli): 100% of 3; created 0, updated 0, unchanged 3, skipped 0, failed 0; 1s, 28.0 MB
```

**Beispiel: ein echter Lauf** von „Docs: Books (XML)“ (die zwei Bücher wurden veröffentlicht und danach rückgängig
gemacht, siehe [undo](#undo)):

```
$ ./console ext:sqliimport:run docs-files-xml --wait -s admin
PASS run #209 of docs-files-xml queued (job group 657ccb46-58f3-4137-ab9f-ee68be3079d7)
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
```

**Beispiel: `--json` ohne `--wait`** gibt den eingestellten Lauf aus:

```json
{"id":211,"import_id":126,"run_kind":"dry_run","trigger_kind":"cli","status":"queued","total_count":0,"read_count":0,"created_count":0,"updated_count":0,"unchanged_count":0,"skipped_count":0,"failed_count":0,"processed_count":0,"user_id":10,"queue_group":"9f87231f-b9c9-4535-b430-593071910d0f","percent":0,"duration":0,"memory_peak_mb":0}
```

Mit `--wait` gibt derselbe Befehl die Textzeilen oben aus, kein JSON; nutzen Sie danach `status <ID> --json`.

**Ohne `-s admin`** nimmt der Befehl auf dem Server der Dokumentation den Siteaccess der Administration und läuft wie
oben, nach einer Zeile auf der Fehlerausgabe:

```
NOTE 7x SQLI Import is not active in the default siteaccess; using -s admin
```

Vor 2.0.0 scheiterte das mit „FAIL the run could not be queued: unknown job type sqliimport.run“ (Exit-Code 1, der
Lauf als fehlgeschlagen gespeichert, wie Lauf #207 unten zeigt).

## status

Ohne Argument: die letzten Läufe. Mit einer Lauf-ID: dieser Lauf und sein Protokoll.

```bash
./console ext:sqliimport:status
./console ext:sqliimport:status 12 --limit=50
./console ext:sqliimport:status 12 --json
```

Eine Zeile eines Laufs lautet: `run #12 completed (import, cli): 100% of 250; created 10, updated 3, unchanged 237,
skipped 0, failed 0; 41s, 38.2 MB`: der Lauf, sein Status, seine Art und sein Auslöser, der Fortschritt, die Zähler,
die Dauer und der höchste Speicherbedarf.

**Beispiel: die letzten Läufe** (`--limit=5`):

```
run #211 queued (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
run #210 completed (dry_run, cli): 100% of 3; created 3, updated 0, unchanged 0, skipped 0, failed 0; 1s, 28.0 MB
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
run #208 completed (dry_run, cli): 100% of 3; created 0, updated 0, unchanged 3, skipped 0, failed 0; 1s, 28.0 MB
run #207 failed (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
```

**Beispiel: ein Lauf mit seinem Protokoll** (Uhrzeiten in der Zeitzone des Servers):

```
$ ./console ext:sqliimport:status 209 -s admin
run #209 completed (import, cli): 100% of 2; created 2, updated 0, unchanged 0, skipped 0, failed 0; 0s, 32.0 MB
  18:43:41 info    run started: 2 records, handler mapping
  18:43:41 info    run ended completed: 2 created, 0 updated, 0 unchanged, 0 skipped, 0 failed in 0s
```

Der fehlgeschlagene Lauf #207 (ein Lauf ohne `-s admin`, bevor 2.0.0 den Siteaccess selbst wählte) zeigt den Grund in seinem Protokoll:

```
run #207 failed (dry_run, cli): 0% of 0; created 0, updated 0, unchanged 0, skipped 0, failed 0; 0s, 0.0 MB
  18:43:18 error   the run could not be queued: unknown job type sqliimport.run: register it in expqueue.ini as [JobType_sqliimport.run] Handler=<class>
```

**Beispiel: `--json`** (gekürzt):

```json
{
    "id": 209,
    "import_id": 130,
    "run_kind": "import",
    "trigger_kind": "cli",
    "status": "completed",
    "total_count": 2,
    "read_count": 2,
    "created_count": 2,
    "updated_count": 0,
    "unchanged_count": 0,
    "skipped_count": 0,
    "failed_count": 0,
    "processed_count": 0,
    "user_id": 10,
    "queue_group": "657ccb46-58f3-4137-ab9f-ee68be3079d7",
    "percent": 100,
    "duration": 0,
    "memory_peak_mb": 32,
    "log": [
        { "severity": "info", "ref": "", "message": "run started: 2 records, handler mapping" },
        { "severity": "info", "ref": "", "message": "run ended completed: 2 created, 0 updated, 0 unchanged, 0 skipped, 0 failed in 0s" }
    ]
}
```

## cancel

```bash
./console ext:sqliimport:cancel 12
```

Bricht einen Lauf ab. Exit-Code `2`, wenn er schon beendet war.

**Beispiel:** Lauf #211 (der eingestellte Probelauf oben) und Lauf #209 (beendet):

```
$ ./console ext:sqliimport:cancel 211 -s admin
PASS run #211 cancelled

$ ./console ext:sqliimport:cancel 209 -s admin
the run has ended already (completed)
```

Der zweite endet mit `2`.

## retry

```bash
./console ext:sqliimport:retry 12 --wait
```

Startet einen Lauf mit den fehlgeschlagenen Zeilen eines beendeten Laufs. Abgelehnt wird er, wenn der Lauf keine
fehlgeschlagenen Zeilen hat oder seine Zeilen keinen Datensatz enthalten (ein Handler von 1.x).

**Beispiel:** Lauf #145 von „Docs: Sitemap crawl (snapshot)“ hatte eine fehlgeschlagene Zeile, die Seite, die
robots.txt nicht erlaubt; die Wiederholung liest nur diesen Datensatz, der aus demselben Grund wieder scheitert. Ein
Lauf ohne fehlgeschlagene Zeilen wird abgelehnt (Exit-Code 1):

```
$ ./console ext:sqliimport:retry 145 --wait -s admin
PASS run #213 queued for the failed rows
run #213 completed_with_errors (retry, manual): 100% of 1; created 0, updated 0, unchanged 0, skipped 0, failed 1; 0s, 22.0 MB

$ ./console ext:sqliimport:retry 209 --wait -s admin
FAIL the run has no failed rows
```

## undo

```bash
./console ext:sqliimport:undo 12 --wait
```

Macht einen Lauf rückgängig: was er angelegt hat, kommt in den Papierkorb; was er geändert hat, erhält die Version
vor dem Lauf erneut als neue Version. Ein danach geändertes Objekt bleibt unberührt und wird aufgeführt. Ein Lauf
wird einmal rückgängig gemacht, innerhalb von `[RunSettings] UndoDays` (oder der eigenen Zahl von Tagen des Imports).
Siehe [runs.md](runs.md).

**Beispiel:** Der echte Lauf #209 legte zwei Ordner an; ihn rückgängig zu machen, verschiebt sie in den Papierkorb:

```
$ ./console ext:sqliimport:undo 209 --wait -s admin
PASS run #212 undoes run #209
run #212 completed (undo, manual): 100% of 2; created 0, updated 0, unchanged 0, skipped 0, failed 0; 1s, 22.0 MB
```

Die Zeile eines Rückgängig-Laufs hat keinen Zähler für rückgängig gemachte Datensätze; sein Bericht (unten) hat eine
Zeile je Datensatz mit dem Ergebnis `undone` und der Meldung „moved to the trash“.

## report

```bash
./console ext:sqliimport:report 12 > lauf-12.csv
./console ext:sqliimport:report 12 --outcome=failed --output=var/import/lauf-12-fehler.csv
```

Schreibt den CSV-Bericht eines Laufs, eine Zeile je Datensatz: `run_id`, `row`, `ref`, `outcome`, `status`,
`object_id`, `node_id`, `remote_id`, `version_before`, `version_after`, `changed_fields`, `message`, `time`. Die
Zeilen werden seitenweise gelesen, ein großer Lauf braucht also wenig Speicher. Eine Zelle, die eine
Tabellenkalkulation als Formel lesen würde, erhält ein führendes Apostroph. Die Seite des Laufs bietet denselben
Bericht als **CSV-Bericht herunterladen** (alle Datensätze oder das angezeigte Ergebnis).

**Beispiel:** der echte Lauf #209 und sein Rückgängig-Lauf #212:

```
$ ./console ext:sqliimport:report 209 -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
209,0,b1,created,ok,56643,55022,sq688doc-xml-b1,,1,,,2026-10-23T01:43:41Z
209,1,b2,created,ok,56644,55023,sq688doc-xml-b2,,1,,,2026-10-23T01:43:41Z

$ ./console ext:sqliimport:report 212 -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
212,0,b2,undone,ok,56644,55023,,1,,,moved to the trash,2026-10-23T01:44:11Z
212,1,b1,undone,ok,56643,55022,,1,,,moved to the trash,2026-10-23T01:44:12Z
```

Nur die fehlgeschlagenen Datensätze von Lauf #145:

```
$ ./console ext:sqliimport:report 145 --outcome=failed -s admin
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
145,3,https://example.org/about/,failed,error,,,,,,,robots.txt of the site does not allow reading https://example.org/about/,2026-10-23T00:50:18Z
```

Die Zeiten sind in UTC (ISO 8601 mit `Z`).

## opml-feeds

```bash
./console ext:sqliimport:opml-feeds meine-feedliste --parent=2345
```

Legt je Feed der Liste eines OPML-Imports einen deaktivierten RSS-Import an, mit der RSS-Vorlage als Zuordnung.
Siehe [sources.md](sources.md#opml-opml) und das durchgespielte Beispiel in
[source-examples-files.md](source-examples-files.md#ein-feed-import-pro-eintrag).

**Beispiel:** Der erste Aufruf legte drei Importe an; ein zweiter überspringt sie:

```
$ ./console ext:sqliimport:opml-feeds docs-files-opml -s admin
SKIP docs-files-opml-example-news https://example.org/feed.rss (an import with this identifier exists)
SKIP docs-files-opml-example-blog https://example.org/blog/atom.xml (an import with this identifier exists)
SKIP docs-files-opml-example-releases https://example.net/releases.rss (an import with this identifier exists)
The feed imports are disabled: check their mapping in the administration, then enable them.
```

## migrate-1x

Migriert die Handler, geplanten Importe und den Verlauf von SQLI Import 1.x. Ohne `--apply` meldet der Befehl nur.

```bash
./console ext:sqliimport:migrate-1x                   # Probelauf: was getan würde
./console ext:sqliimport:migrate-1x --apply           # migrieren
./console ext:sqliimport:migrate-1x --apply --roles   # auch Rollen mit manageimports die Richtlinie manage geben
```

**Beispiel** (ohne `--apply`, nichts wird geändert): Auf dem Server der Dokumentation würde nur der Verlauf von 1.x
übernommen:

```
history   WOULD the 1.x history is migrated
PASS dry run: nothing was changed (--apply to migrate)
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

`tick` hat keine Option, die nur meldet: Er tut immer seine Arbeit. Seine Hilfe (die Optionen, die jedes Skript der
Installation hat, keine eigenen):

```
Usage: .../extension/sqliimport/bin/php/tick.php [OPTION]...
7x SQLI Import: One round of the cron part: due schedules, 1.x pending imports, ended jobs, purge.

General options:
  -h,--help             display this help and exit
  -q,--quiet            do not give any output except when errors occur
  -s,--siteaccess       selected siteaccess for operations,
                          if not specified default siteaccess is used
  ...
```

## purge

Entfernt beendete Läufe, die älter als `[RunSettings] KeepDays` sind, mit ihren Zeilen, Protokollen und Dateien.

```bash
./console ext:sqliimport:purge --dry-run     # nur zählen
./console ext:sqliimport:purge --days=30
```

**Beispiel** (`--dry-run`, nur lesend; der älteste Lauf auf dem Server der Dokumentation ist einen Tag alt):

```
$ ./console ext:sqliimport:purge --dry-run -s admin
PASS 0 runs would be removed

$ ./console ext:sqliimport:purge --dry-run --days=30 -s admin
PASS 0 runs would be removed
```

## Das Skript von 1.x

`extension/sqliimport/bin/php/sqlidoimport.php` aus 1.x bleibt erhalten. Es führt einen Handler von 1.x direkt
aus, außerhalb der Job-Warteschlange. Verwenden Sie besser `run`.
