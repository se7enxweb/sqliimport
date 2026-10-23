# Templates, Skripte und andere Systeme: Fetch-Funktionen, Serverfunktionen und der Auslöser

Diese Seite ist für Website-Bauer und Entwickler, die den Stand der Importe anderswo brauchen: in einem Template (ein
Block auf einem Dashboard, eine Warnung im Seitenlayout), im Browser (die Live-Seite eines Laufs, die Glocke), in
einem Skript oder einer Überwachung, oder in einem anderen System, das Importe startet. Jede Schnittstelle hier liest
nur, außer dem Auslöser, der Läufe startet.

English: [../rest-and-fetch.md](../rest-and-fetch.md).

## Inhalt

- [Überblick](#überblick)
- [Template-Fetch-Funktionen](#template-fetch-funktionen)
  - [recent_runs](#recent_runs)
  - [failed_runs_24h](#failed_runs_24h)
  - [notices](#notices)
  - [Die Felder eines Laufs](#die-felder-eines-laufs)
  - [Ein vollständiges Beispiel](#ein-vollständiges-beispiel)
- [ezjscore-Serverfunktionen](#ezjscore-serverfunktionen)
  - [sqliimport::runstatus](#sqliimportrunstatus)
  - [sqliimport::notices](#sqliimportnotices)
- [Einen Lauf aus einem anderen System starten](#einen-lauf-aus-einem-anderen-system-starten)
- [Die Kommandozeile für Skripte und Überwachung](#die-kommandozeile-für-skripte-und-überwachung)
- [Verwandte Seiten](#verwandte-seiten)

## Überblick

| Schnittstelle | Adresse oder Aufruf | Liest oder schreibt | Wer darf |
|---|---|---|---|
| Template-Fetch-Funktionen | `fetch( 'sqliimport', … )` | liest | Benutzer mit der Richtlinie sqliimport/view (Run imports, Manage imports oder eine Richtlinie von 1.x); andere bekommen ein leeres Ergebnis |
| ezjscore-Serverfunktionen | `ezjscore/call/sqliimport::runstatus::…`, `sqliimport::notices::…` | liest | dieselben Benutzer; runstatus nur als POST mit dem Formular-Token |
| Webhook- / API-Auslöser | `POST /<siteaccess>/sqliimport/trigger/<bezeichner>` | startet einen Lauf | ein signierter Aufruf oder ein API-Schlüssel dieses Imports ([triggers.md](triggers.md)) |
| Kommandozeile | `./console ext:sqliimport:list`, `status`, `report` (mit `--json`) | liest (und führt mit `run` aus) | wer die Konsole auf dem Server ausführt |

Die Erweiterung hat keine Routen in der REST-API des Kernels (ezprestapi); der Auslöser ist eine eigene Modulansicht.
Warum, und wie sie gesichert ist: [triggers.md](triggers.md#die-adresse).

## Template-Fetch-Funktionen

Definiert in `modules/sqliimport/function_definition.php`, umgesetzt von `expImportFunctionCollection`. Alle drei
geben ein leeres Ergebnis zurück (eine leere Liste, `0` oder einen Zähler `0`), wenn die Tabellen nicht installiert
sind oder der aktuelle Benutzer die Importe nicht sehen darf; ein Template braucht also keine eigene Rechteprüfung.

### recent_runs

```
fetch( 'sqliimport', 'recent_runs', hash( 'limit', 5 ) )
```

| Parameter | Typ | Vorgabe | Bedeutung |
|---|---|---|---|
| `limit` | integer | `5` | Wie viele Läufe, die neuesten zuerst; höchstens 50 |

Gibt eine Liste von Läufen zurück, jeder ein Hash mit [den Feldern eines Laufs](#die-felder-eines-laufs). Alle Läufe
aller Importe sind dabei (auch Probeläufe), wie auf der Übersicht.

### failed_runs_24h

```
fetch( 'sqliimport', 'failed_runs_24h' )
```

Keine Parameter. Gibt eine Zahl zurück: wie viele Läufe in den letzten 24 Stunden fehlgeschlagen oder mit Fehlern
beendet sind, dieselbe Zahl wie **Failed in 24 hours** auf der Übersicht.

### notices

```
fetch( 'sqliimport', 'notices', hash( 'limit', 10 ) )
```

| Parameter | Typ | Vorgabe | Bedeutung |
|---|---|---|---|
| `limit` | integer | `10` | Wie viele Hinweise; höchstens 50 |

Gibt für den aktuellen Benutzer einen Hash zurück: `count`, die Zahl der ungelesenen Hinweise, und `items`, die
neuesten ungelesenen Hinweise. Jeder Eintrag hat `id`, `run_id`, `import_id`, `import` (der Name des Imports), `kind`
(`failed`, `errors`, `cancelled` oder `completed`), `created` (eine Unix-Zeit), `seen` und `url`
(`/sqliimport/run/<Lauf-ID>`). Ein Hinweis entsteht, wenn ein Lauf endet, für den Benutzer, der ihn gestartet hat, so
wie es die **Notifications** des Imports festlegen ([runs.md](runs.md)). Die Glocke der Kopfzeile zeigt dieselben
Daten.

### Die Felder eines Laufs

Die Einträge von `recent_runs` (und die Antwort von [runstatus](#sqliimportrunstatus)) haben diese Schlüssel:

| Schlüssel | Beispiel (Lauf #119) | Bedeutung |
|---|---|---|
| `id`, `import_id`, `import_name` | `119`, `76`, `Demo: Books into a data table` | Der Lauf und sein Import |
| `status`, `status_label`, `status_kind` | `completed`, `Completed`, `is-ok` | `queued`, `running`, `completed`, `completed_with_errors`, `failed`, `cancelled`; die angezeigte Bezeichnung; ein CSS-Modifikator |
| `kind`, `kind_label` | `import`, `Import` | `import`, `dry_run`, `retry`, `undo` |
| `trigger`, `trigger_label` | `manual`, `Started by hand` | `manual`, `schedule`, `cli`, `rest`, `legacy` |
| `percent`, `total`, `done` | `100`, `3`, `3` | Der Fortschritt |
| `created`, `updated`, `unchanged`, `skipped`, `failed`, `processed`, `undone` | `0`, `1`, `2`, `0`, `0`, `0`, `0` | Die Zähler der Ergebnisse |
| `queued_at`, `started_at`, `finished_at` | Unix-Zeiten | Wann |
| `duration`, `memory` | `0 s`, `26` | Die Dauer als Text, die Speicherspitze in MB |
| `user` | das Login des Benutzers, der ihn gestartet hat (leer, wenn es den Benutzer nicht mehr gibt) | |
| `is_final` | `true` | Ob der Lauf beendet ist |
| `parent_run_id` | `0` | Bei einer Wiederholung oder einem Rückgängigmachen: der Lauf, zu dem er gehört |
| `queue_group`, `pct_class` | | Für die eigenen Seiten der Administration |

### Ein vollständiges Beispiel

Ein kleiner Block für ein Dashboard oder ein Seitenlayout der Administration, mit einer Warnung, wenn Läufe
fehlgeschlagen sind:

```
{def $sq_runs = fetch( 'sqliimport', 'recent_runs', hash( 'limit', 3 ) )
     $sq_failed = fetch( 'sqliimport', 'failed_runs_24h' )
     $sq_bell = fetch( 'sqliimport', 'notices', hash( 'limit', 3 ) )}
{if $sq_failed}<p><a href={'/sqliimport/list/(status)/failed'|ezurl}>{$sq_failed} import runs failed in the last 24 hours</a></p>{/if}
<ul>
{foreach $sq_runs as $r}
  <li><a href={concat( '/sqliimport/run/', $r.id )|ezurl}>#{$r.id}</a> {$r.import_name|wash}:
      {$r.status_label|wash}, {$r.kind_label|wash}, {$r.trigger_label|wash},
      {$r.done}/{$r.total} records ({$r.created} created, {$r.updated} updated, {$r.unchanged} unchanged,
      {$r.failed} failed) in {$r.duration|wash}</li>
{/foreach}
</ul>
{if $sq_bell.count}<p>{$sq_bell.count} new import notices</p>{/if}
{undef $sq_runs $sq_failed $sq_bell}
```

Auf der Testinstallation für einen Administrator ausgegeben (das Template oben, unverändert), lautete die Ausgabe:

```html
<p><a href="/sqliimport/list/(status)/failed">7 import runs failed in the last 24 hours</a></p><ul>  <li><a href="/sqliimport/run/202">#202</a> Docs: First import (teas CSV):
      Queued, Import, REST API,
      0/0 records (0 created, 0 updated, 0 unchanged,
      0 failed) in 0 s</li>  <li><a href="/sqliimport/run/201">#201</a> Docs: First import (teas CSV):
      Completed, Undo, Started by hand,
      1/1 records (0 created, 0 updated, 0 unchanged,
      0 failed) in 1 s</li>  <li><a href="/sqliimport/run/200">#200</a> Docs: First import (teas CSV):
      Completed, Import, Started by hand,
      7/7 records (0 created, 1 updated, 6 unchanged,
      0 failed) in 0 s</li></ul>
```

Der neueste Lauf (#202) war gerade vom Auslöser eingereiht worden (`REST API`) und noch nicht gestartet; ein
Rückgängig-Lauf (#201) erscheint wie jeder andere. Für die Hinweise gibt es keine Zeile, weil dieser Benutzer keine
ungelesenen hatte (`count` 0). Für einen Benutzer ohne die Richtlinie zeigt der Block nichts. Geben Sie Namen immer
durch `|wash` aus: Den Namen eines Imports tippt ein Redakteur.

Diese Funktionen nutzen auch die eigenen Templates der Erweiterung: den Block **Imports** auf dem Dashboard der
Administration (`recent_runs`, `failed_runs_24h`) und, auf einem Kernel vor 6.0.15 ohne Platz in der Kopfzeile, eine
von Hand gebaute Glocke (`notices`).

## ezjscore-Serverfunktionen

`expImportServerFunctions`, in `settings/ezjscore.ini.append.php` als `[ezjscServer_sqliimport]` registriert. Sie
antworten mit JSON über `ezjscore/call/…` (mit `?ContentType=json`) und sind das, was die eigenen Skripte der
Administration aufrufen. Beide lesen nur und prüfen die Richtlinie sqliimport/view.

### sqliimport::runstatus

```
POST ezjscore/call/sqliimport::runstatus::<Lauf-ID>::<letzte Protokoll-ID>?ContentType=json
```

Die Live-Seite eines Laufs ruft sie alle zwei Sekunden auf, solange ein Lauf wartet oder läuft. Sie braucht einen
**POST** mit dem Formular-Token (`ezxform_token` im Inhalt oder der Kopf `X-CSRF-Token`); ein GET wird mit „Only POST
requests are answered.“ abgelehnt. Die Antwort ist der Lauf mit [den Feldern eines Laufs](#die-felder-eines-laufs),
dazu `log`: bis zu 100 Protokollzeilen nach `<letzte Protokoll-ID>` (`0` für alle), jede mit `id`, `time`,
`severity`, `ref` und `message`. Für Lauf #119:

```json
{
    "id": 119,
    "import_id": 76,
    "import_name": "Demo: Books into a data table",
    "status": "completed",
    "status_label": "Completed",
    "kind": "import",
    "trigger": "manual",
    "percent": 100,
    "total": 3,
    "done": 3,
    "created": 0,
    "updated": 1,
    "unchanged": 2,
    "failed": 0,
    "duration": "0 s",
    "is_final": true,
    "log": [
        { "id": 326, "time": "17:14:02", "severity": "info", "ref": "", "message": "run started: 3 records, handler datatable" },
        { "id": 327, "time": "17:14:02", "severity": "info", "ref": "", "message": "run ended completed: 0 created, 1 updated, 2 unchanged, 0 skipped, 0 failed in 0s" }
    ]
}
```

(gekürzt: Die Antwort hat jeden Schlüssel der Tabelle oben). Ein Lauf, den es nicht gibt, ein fehlendes Token oder
ein Benutzer, der die Importe nicht sehen darf, ergibt eine Fehlerantwort von ezjscore mit der Meldung.

### sqliimport::notices

```
GET ezjscore/call/sqliimport::notices::<Anzahl>?ContentType=json
```

Die Quelle der Glocke in der Kopfzeile (admin4 und admin4l, Exponential 6.0.15). Sie beantwortet ein GET, weil sie
nur die eigenen Hinweise des Aufrufers liest; `<Anzahl>` ist 1 bis 20 (Vorgabe 8). Die Antwort:

```json
{
    "count": 0,
    "label": "Import notifications",
    "items": []
}
```

Mit ungelesenen Hinweisen wird `label` zu „Import notifications: 3 new“, und jeder Eintrag lautet etwa
`{ "text": "Run #42 · Products (CSV)", "url": "/admin/sqliimport/run/42", "meta": "failed · 10/22/2026 06:10 pm" }`.
Ein Benutzer ohne die Richtlinie bekommt `count` 0 und keine Einträge.

Beide Antworten oben entstanden auf der Testinstallation, mit dem Test-Administrator der Dokumentation, runstatus als
POST mit dem Formular-Token.

## Einen Lauf aus einem anderen System starten

Die einzige Schnittstelle, die etwas ändert: der Webhook- / API-Auslöser eines Imports. Kurz:

| Anfrage | Was sie tut | Wer darf |
|---|---|---|
| `POST /<siteaccess>/sqliimport/trigger/<bezeichner>` | Startet einen Lauf, auf Wunsch mit den Daten im Inhalt | ein signierter Aufruf (HMAC-SHA256 mit dem Geheimnis des Imports) oder ein API-Schlüssel |
| `GET /<siteaccess>/sqliimport/trigger/<bezeichner>/run/<Lauf-ID>` | Der Status eines Laufs dieses Imports, mit denselben Zählern wie oben | ein API-Schlüssel |

Er ist für jeden Import aus, bis er im Editor eingeschaltet wird (Schritt **Schedule**, Abschnitt *Started by another
system*), läuft als der Ausführungsbenutzer des Auslösers und antwortet mit JSON (`202` mit der ID des Laufs, `401`,
`404`, `409`, `429` …). Nutzen Sie den Siteaccess, in dem die Erweiterung aktiv ist. Ein Aufruf mit API-Schlüssel:

```sh
curl -sS -X POST "https://www.example.invalid/admin/sqliimport/trigger/books" \
     -H "Authorization: Bearer sq-demo-token-REPLACE" \
     -H "Content-Type: text/csv" --data-binary @books.csv
```

Signieren, Schlüssel, Antworten, Grenzen und das Audit-Protokoll: [triggers.md](triggers.md).

## Die Kommandozeile für Skripte und Überwachung

Auf dem Server liefert die Konsole dieselben Angaben als JSON, für eine Prüfung per Cron, ein Deployment-Skript oder
einen Agenten, der nur lesen darf:

```bash
./console ext:sqliimport:list --json            # jeder Import mit seinem letzten Lauf
./console ext:sqliimport:status 119 --json      # ein Lauf mit Zählern und Protokoll
./console ext:sqliimport:report 119 > run.csv   # die Zeilen eines Laufs als CSV
```

`list --json` liefert pro Import `id`, `identifier`, `name`, `source`, `handler`, `enabled` und `last_run` (`id`,
`status`), zum Beispiel:

```json
{ "id": 76, "identifier": "demo_books_datatable", "name": "Demo: Books into a data table", "source": "csv",
  "handler": "datatable", "enabled": true, "last_run": { "id": 119, "status": "completed" } }
```

Eine Überwachung kann melden, wenn `failed_runs_24h` nicht 0 ist oder der `last_run.status` eines Imports `failed`
lautet. Alle Befehle: [cli.md](cli.md).

Ein KI-Agent, der über den nur lesenden MCP-Server der Installation (die Erweiterung expmcp) angebunden ist, kann
Template-Fetch-Funktionen mit seinem Werkzeug `exp_fetch` aufrufen, aber nur die der Module, die der Server kennt. Auf
der Testinstallation führte `exp_fetch` das Modul `sqliimport` beim Schreiben dieser Seite nicht auf; geben Sie einem
solchen Agenten bis dahin die Konsolenbefehle oben.

## Verwandte Seiten

- [triggers.md](triggers.md): der Webhook- / API-Auslöser vollständig
- [runs.md](runs.md): Läufe, Benachrichtigungen, die Glocke
- [statistics.md](statistics.md): die Zahlen im Zeitverlauf
- [cli.md](cli.md): jeder Befehl
