# Auslöser: einen Import aus einem anderen System starten

Ein anderes System (ein Shop, ein CRM, eine Build-Pipeline, ein Cronjob auf einem anderen Server) kann einen Import
starten, indem es eine Adresse der Site aufruft, und es kann die Daten im Aufruf mitbringen. Das ist der Webhook-/
API-Auslöser eines Imports.

- **Standardmäßig aus, pro Import.** Nichts antwortet, bis der Auslöser dieses Imports im Editor eingeschaltet ist
  (Schritt **Schedule**, Abschnitt *Started by another system*).
- **Zwei Wege, den Aufruf zu belegen:** ein **signierter Aufruf** (HMAC-SHA256 mit dem Signaturgeheimnis des Imports)
  oder ein **API-Schlüssel** des Imports.
- **Nie anonym:** ein ausgelöster Lauf läuft als **Laufbenutzer** des Auslösers, ein Benutzer mit der Richtlinie
  *Run imports* (oder *Manage imports*) für den Handler des Imports.
- **Daten im Aufruf (optional):** der Body des Aufrufs wird zu den Daten dieses Laufs, für Importe, die eine Datei
  lesen.
- **Protokolliert im Audit-Trail, mit Ratenbegrenzung, ohne Session, ohne CORS.**

## Durchgespieltes Beispiel auf einer Testinstallation

Dieses Beispiel startet den Import „Docs: First import (teas CSV)“ (Kennung `docs-first-import`, Import #132) von der
Kommandozeile: einmal signiert, einmal mit API-Schlüssel und neuen Daten und einmal mit einem falschen Schlüssel. Alle
Aufrufe und Antworten unten sind echt; nur das Signiergeheimnis und der API-Schlüssel sind durch Platzhalter ersetzt.
Auf dieser Testinstallation ist die Erweiterung nur im Siteaccess der Verwaltung aktiv, daher liegt die Adresse unter
`/admin`. **Auf einer normalen Website rufen Sie einen Siteaccess auf, der Besucher nicht zur Anmeldung auffordert**
(meist die öffentliche Website), wie in [Die Adresse](#die-adresse) beschrieben.

### 1. Den Auslöser einschalten

Öffnen Sie den Import im Editor, Schritt **Zeitplan** (Schedule), Abschnitt *Started by another system (webhook or
API)*:

1. **The trigger is on** anhaken.
2. **Run as user (ID)**: ein Benutzer mit *Run imports* für diesen Import (hier ein Test-Administrator). Ein
   ausgelöster Lauf läuft nie als anonymer Benutzer.
3. **A call may bring the data** anhaken (dieser Import liest eine CSV-Datei).
4. **Signing secret by name** leer lassen, damit die Erweiterung das Geheimnis erzeugt, und mit **Save the trigger**
   speichern.

Die Seite zeigt jetzt das neue **Signiergeheimnis, einmal**:

![Der Abschnitt des Auslösers direkt nach dem Einschalten: das Signiergeheimnis in einem Hinweis, der sagt, dass es nur dieses eine Mal gezeigt wird, dann die Felder des Auslösers und die Adresse](../images/trigger-secret-once.png)

Übernehmen Sie es in diesem Moment in das aufrufende System. Es wird **nur dieses eine Mal gezeigt** und nicht in
lesbarer Form gespeichert. Ist es verloren oder bekannt geworden, **erneuern** Sie es mit **New signing secret**: Das
alte Geheimnis gilt sofort nicht mehr.

Dann einen API-Schlüssel anlegen: einen Namen in **Name of a new key** (hier `Docs: curl example`, wer den Schlüssel
nutzt) und **Create an API key**. Auch der Schlüssel wird **einmal gezeigt**:

![Der Abschnitt des Auslösers mit einem neuen API-Schlüssel namens Docs: curl example, einmal in einem Hinweis gezeigt, und darunter die Liste der Schlüssel](../images/trigger-api-key-once.png)

Der Abschnitt danach: der Auslöser ist an, die aufzurufende Adresse, wann das Signiergeheimnis erzeugt wurde, und die
Liste der Schlüssel mit Schlüssel-ID und letzter Nutzung. **Revoke** sperrt einen Schlüssel sofort; tun Sie das,
wenn ein Schlüssel bekannt wird oder sein Aufrufer außer Dienst geht.

![Der Abschnitt des Auslösers eines Imports: Auslöser an, Benutzer des Laufs, Daten erlaubt, die Adresse, das Datum des Signiergeheimnisses und die Liste der API-Schlüssel mit Revoke](../images/trigger-section.png)

Die Adresse, die der Abschnitt zeigt:

```
POST https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import
```

### 2. Ein falscher Schlüssel wird abgelehnt

```sh
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "Authorization: Bearer sqk_0123456789ab_sq-demo-token-REPLACE"
```

```
HTTP 401 {"error":"invalid_key"}
```

### 3. Ein signierter Aufruf und seine Wiederholung

Die Signatur deckt Methode, Pfad, Zeit und den SHA-256 des Inhalts ab (hier leer):

```sh
SECRET='sq-demo-signing-secret-REPLACE'
TS=$(date +%s); BODY=''
SIG=$(printf 'POST\n/sqliimport/trigger/docs-first-import\n%s\n%s' "$TS" "$(printf '%s' "$BODY" | sha256sum | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

```
HTTP 202 {"run_id":202,"status":"queued"}
```

Der Lauf #202 las die eigene Quelle des Imports und endete erfolgreich (Art import, Auslöser rest): 7 Datensätze
unverändert, weil sich seit dem letzten Lauf nichts geändert hatte. Derselbe Aufruf noch einmal mit demselben
Zeitstempel und derselben Signatur:

```
HTTP 401 {"error":"replayed"}
```

Eine Signatur wird einmal angenommen, und nur innerhalb von `[TriggerSettings] SignatureWindowSeconds` um die Zeit
des Servers.

### 4. Ein API-Schlüssel mit Daten im Aufruf

Das aufrufende System schickt zwei geänderte Zeilen als CSV (`teas-push.csv`): Tee 105 mit neuem Preis und einen
neuen Tee 108.

```
id,name,code,description,price,harvest
105,White Peony,TEA-105,"A mild white tea with silver buds.",11.95,2026-04-20
108,Rooibos Vanilla,TEA-108,"A caffeine-free red bush tea with vanilla.",6.45,2026-06-01
```

```sh
curl -sS -X POST "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import" \
     -H "X-Api-Key: sqk_0123456789ab_sq-demo-token-REPLACE" \
     -H "Content-Type: text/csv" --data-binary @teas-push.csv
```

```
HTTP 202 {"run_id":204,"status":"queued","status_url":".../run/204"}
```

`status_url` (hier gekürzt) ist die Adresse des Statusaufrufs unten. Der Inhalt ist nur die Daten dieses Laufs; die
eigene Quelle des Imports wird nicht gelesen.

### 5. Nach dem Status fragen

Mit dem API-Schlüssel (ein signierter Aufruf kann nicht nach einem Status fragen):

```sh
curl -sS "https://alpha.se7enx.com/admin/sqliimport/trigger/docs-first-import/run/204" \
     -H "Authorization: Bearer sqk_0123456789ab_sq-demo-token-REPLACE"
```

Während der Lauf auf einen Worker der Job-Warteschlange wartete:

```
HTTP 200 {"status":"queued",...,"is_final":false,"run_id":204}
```

und nachdem er endete:

```
HTTP 200 {"status":"completed","kind":"import","trigger":"rest","percent":100,"total":2,"done":2,"created":1,"updated":1,"unchanged":0,"skipped":0,"failed":0,"processed":0,"undone":0,"queued_at":1792719682,"started_at":1792719722,"finished_at":1792719723,"is_final":true,"run_id":204}
```

Fragen Sie, bis `is_final` `true` ist. Die Seite des Laufs zeigt denselben Lauf, gestartet über *REST API* als Benutzer
des Laufs: Tee 105 aktualisiert (Version 1 auf 2), Tee 108 angelegt. Er lässt sich wie jeder Lauf rückgängig machen:

![Die Seite von Lauf #204: abgeschlossen, Import gestartet über REST API, 1 angelegt und 1 aktualisiert, die beiden Datensätze 105 und 108 und das Protokoll](../images/trigger-run-page.png)

Die Aufrufe des Auslösers stehen im Audit-Trail mit der Schlüssel-ID, nie mit dem Schlüssel ([Audit-Trail](#audit-trail)).

## Die Adresse

| Anfrage | Was sie tut | Wer darf |
|---|---|---|
| `POST /<siteaccess>/sqliimport/trigger/<identifier>` | Startet einen Lauf des Imports | signierter Aufruf oder API-Schlüssel |
| `GET /<siteaccess>/sqliimport/trigger/<identifier>/run/<Lauf-ID>` | Der Status eines Laufs dieses Imports | nur API-Schlüssel |

`<identifier>` ist der Bezeichner des Imports (im Editor zu sehen, z. B. `books`). Rufen Sie einen Siteaccess auf, der
keine Anmeldung verlangt (meist die öffentliche Site): für einen Siteaccess mit `RequireUserLogin=true` tragen die
Einstellungen der Erweiterung `sqliimport/trigger` in `[SiteAccessSettings] AnonymousAccessList[]` ein, und die Ansicht
steht in `[RoleSettings] PolicyOmitList[]`, weil der Aufrufer nie eine Session hat. Die Ansicht prüft den Aufrufer
selbst.

Warum eine Modulansicht und nicht die REST-API des Kernels: die Erweiterung hat keine eigenen REST-Routen, und die
REST-Schicht des Kernels (ezprestapi) bräuchte in jeder Installation eine eigene Anmeldung (OAuth). Eine Modulansicht
mit eigener Signatur- und Schlüsselprüfung ist der kleinere, in sich geschlossene Weg: sie braucht nur die
Erweiterung, antwortet mit JSON, startet nie eine Session und setzt kein Cookie, also gibt es nichts, worauf eine
Browserseite einer anderen Site aufsetzen könnte (kein CSRF), und sie sendet keine CORS-Header, also kann keine Seite
einer anderen Site eine Antwort lesen.

## Einschalten

Im Editor, Schritt **Schedule**, Abschnitt *Started by another system (webhook or API)*:

| Feld | Bedeutung |
|---|---|
| The trigger is on | Aus: jeder Aufruf erhält 404, genau wie für einen Import, den es nicht gibt. |
| Run as user (ID) | Der Benutzer, als der der Lauf läuft: braucht *Run imports* oder *Manage imports* für den Handler des Imports. Pflicht, wenn der Auslöser an ist. Der Robot-Benutzer des Schritts Processing (`owner_id`) hat wie bei jedem Lauf Vorrang. |
| A call may bring the data | Der Body eines Aufrufs wird statt der Quelle des Imports gelesen, nur für diesen Lauf. Nur für Importe, die eine Datei lesen (CSV, XML, JSON, Tabellen ...); nicht für Quellen, die selbst eine Datenbank oder einen Dienst lesen. |
| Signing secret by name | Optional. Leer: die Erweiterung erzeugt ein Signaturgeheimnis. Ein Name: ein Geheimnis von mindestens 32 Zeichen in Setup > Secrets (Bereich `sqliimport`), `[SecretSettings] Secrets[]` oder `env:VARIABLE`. |

Wird der Auslöser zum ersten Mal ohne Geheimnisnamen eingeschaltet, zeigt die Seite das neue **Signaturgeheimnis
einmal**. Übernehmen Sie es dann in das aufrufende System: es wird nie wieder gezeigt. **New signing secret** erzeugt
ein neues (das alte gilt sofort nicht mehr).

**API-Schlüssel:** geben Sie dem Schlüssel einen Namen (wer ihn benutzt) und wählen Sie **Create an API key**. Der
Schlüssel (`sqk_<Schlüssel-ID>_<43 Zeichen>`) wird **einmal** gezeigt. Die Liste zeigt Name, Schlüssel-ID, wann er
erstellt und zuletzt benutzt wurde; **Revoke** sperrt einen Schlüssel sofort.

### Wie die Geheimnisse aufbewahrt werden

- Ein API-Schlüssel wird nie gespeichert: nur ein HMAC-SHA256 davon, mit dem Serverschlüssel als Pepper, und seine
  Schlüssel-ID, um ihn zu finden.
- Auch ein Signaturgeheimnis wird nie gespeichert: es wird aus dem Serverschlüssel und einem zufälligen Salt des
  Imports abgeleitet (`random_bytes`, 32 Bytes); Erneuern zieht ein neues Salt. Oder es ist ein benanntes Geheimnis
  (`expImportSecrets`).
- Der **Serverschlüssel** ist das Geheimnis, das `[TriggerSettings] ServerKeySecret` nennt, sonst ein zufälliger
  Schlüssel, den die Erweiterung einmal nach `var/<site>/storage/sqliimport/trigger.key` schreibt (Modus 0600),
  außerhalb der Datenbank. Eine Kopie der Datenbank allein liefert weder ein Signaturgeheimnis noch etwas, womit sich
  ein Schlüssel prüfen ließe. Ein neuer Serverschlüssel macht alle Signaturgeheimnisse und API-Schlüssel ungültig.
- Kein Geheimnis, Schlüssel oder keine Signatur wird in ein Log oder den Audit-Trail geschrieben; der Audit-Trail nennt
  die Schlüssel-ID.

## Ein signierter Aufruf

Header:

```
X-Sqliimport-Timestamp: <Unix-Zeit in Sekunden>
X-Sqliimport-Signature: sha256=<HMAC-SHA256 hexadezimal>
```

oder dasselbe als signierte URL: `?timestamp=<Unix-Zeit>&signature=<hex>`.

Was signiert wird, vier Zeilen, verbunden mit `\n`:

```
POST
/sqliimport/trigger/<identifier>
<timestamp>
<SHA-256 hexadezimal des Bodys (der leeren Zeichenkette, wenn es keinen Body gibt)>
```

Der Pfad ist immer `/sqliimport/trigger/<identifier>`, ohne Host, Siteaccess oder Query, damit dieselbe Signatur auf
jedem Siteaccess und hinter einem Proxy gilt. Der Aufruf wird angenommen, wenn der Zeitstempel höchstens
`[TriggerSettings] SignatureWindowSeconds` (300) von der Serverzeit abweicht, und **nur einmal**: dieselbe Signatur ein
zweites Mal wird als Wiederholung (Replay) abgewiesen. Ein signierter Aufruf kann keinen Status abfragen (dafür braucht
es einen API-Schlüssel).

```sh
SECRET='<Signaturgeheimnis>'
TS=$(date +%s)
BODY=''
SIG=$(printf 'POST\n/sqliimport/trigger/books\n%s\n%s' "$TS" "$(printf '%s' "$BODY" | sha256sum | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

Mit Daten (CSV):

```sh
TS=$(date +%s)
SIG=$(printf 'POST\n/sqliimport/trigger/books\n%s\n%s' "$TS" "$(sha256sum books.csv | cut -d' ' -f1)" \
      | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "Content-Type: text/csv" --data-binary @books.csv \
     -H "X-Sqliimport-Timestamp: $TS" -H "X-Sqliimport-Signature: sha256=$SIG"
```

In PHP:

```php
$ts = time();
$body = file_get_contents( 'books.csv' );
$sig = hash_hmac( 'sha256', "POST\n/sqliimport/trigger/books\n$ts\n" . hash( 'sha256', $body ), $secret );
```

## Ein Aufruf mit API-Schlüssel

```sh
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "Authorization: Bearer sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"

# mit Daten (NDJSON), der Schlüssel in X-Api-Key (wenn ein Proxy Authorization verwirft)
curl -sS -X POST "https://www.example.invalid/sqliimport/trigger/books" \
     -H "X-Api-Key: sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" \
     -H "Content-Type: application/x-ndjson" --data-binary @books.ndjson

# der Status des Laufs
curl -sS "https://www.example.invalid/sqliimport/trigger/books/run/42" \
     -H "Authorization: Bearer sqk_0123456789ab_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

## Daten im Aufruf

Nur wenn der Auslöser es erlaubt und der Import eine Datei liest. Der Body muss im Format des Imports sein:

| Content-Type | Gelesen als |
|---|---|
| `text/csv`, `application/csv`, `text/tab-separated-values` | `csv` / `tsv` |
| `application/json` | `json` |
| `application/x-ndjson`, `application/ndjson`, `application/jsonl`, `application/x-jsonlines` | `ndjson` / `jsonl` |
| `application/xml`, `text/xml`, `application/rss+xml`, `application/atom+xml` | `xml` (`rss`, `atom`) |
| `application/yaml`, `text/yaml` | `yaml` / `yml` |
| Content-Types von xlsx und ods | `xlsx` / `ods` |

`X-Sqliimport-Format: <Endung>` nennt die Endung direkt (jede Endung des Formats des Imports, z. B. `xlsx` mit
`application/octet-stream`). Alles andere, oder Daten für einen Import, der keine nimmt, wird mit 415 beantwortet;
ein Body größer als `[TriggerSettings] MaxBodyMegabytes` (20) mit 413.

Der Body wird in `var/<site>/storage/sqliimport/pushed/` aufbewahrt und beim Start des Laufs als `source.<Endung>` in
das Verzeichnis des Laufs verschoben; der Lauf liest ihn dann wie eine hochgeladene Datei, und er wird mit der
Geschichte des Laufs entfernt. Die eigene Quelle des Imports (Upload, Adresse oder Serverdatei) bleibt unberührt und
wird vom nächsten Lauf ohne Daten wieder gelesen. Ein Handler, der seine Datensätze selbst liest (nicht über eine
Quelle), übergeht die Daten.

## Antworten

Jede Antwort ist JSON mit `Cache-Control: no-store`.

| Status | Body | Wann |
|---|---|---|
| 202 | `{"run_id": 42, "status": "queued", "status_url": "..."}` | Der Lauf steht in der Warteschlange. `status_url` nur für einen API-Schlüssel. |
| 200 | `{"run_id", "status", "kind", "trigger", "percent", "total", "done", "created", "updated", "unchanged", "skipped", "failed", "processed", "undone", "queued_at", "started_at", "finished_at", "is_final"}` | Der Status eines Laufs |
| 401 | `{"error": "missing_credentials" \| "invalid_key" \| "invalid_signature" \| "expired" \| "replayed"}` | Keine oder falsche Zugangsdaten, ein Zeitstempel außerhalb des Fensters, eine schon benutzte Signatur |
| 403 | `{"error": "no_run_user" \| "import_disabled" \| "status_needs_api_key"}` | Der Laufbenutzer fehlt oder darf den Import nicht ausführen; der Import ist deaktiviert; ein signierter Aufruf fragt einen Status ab |
| 404 | `{"error": "not_found"}` | Kein Import dieses Bezeichners, sein Auslöser ist aus, `[TriggerSettings] Trigger=disabled`, oder ein Lauf eines anderen Imports |
| 405 | `{"error": "method_not_allowed"}` | Nicht POST (Start) oder GET (Status); `Allow` nennt die Methode |
| 409 | `{"error": "already_running"}` | Ein Lauf des Imports wartet oder läuft |
| 413 | `{"error": "body_too_large"}` | Größer als `MaxBodyMegabytes` |
| 415 | `{"error": "unsupported_media_type", "message"}` | Der Body ist nicht im Format des Imports, oder der Import nimmt keine Daten im Aufruf |
| 429 | `{"error": "rate_limited"}` | Über der Grenze des Imports; `Retry-After: 60` |
| 503 | `{"error": "unavailable"}` | Die Job-Warteschlange fehlt, die Tabellen sind nicht installiert, der Lauf konnte nicht eingereiht werden |

Der Lauf selbst zeigt auf seiner Seite und in der Statistik *REST API* als Auslöser.

## Ratenbegrenzung

Pro Import und Minute (`[TriggerSettings]`, `0` = keine Grenze):

| Einstellung | Standard | Zählt |
|---|---|---|
| `RequestsPerMinute` | `6` | Angenommene Aufrufe, die einen Lauf starten |
| `StatusRequestsPerMinute` | `60` | Statusabfragen |
| `FailedRequestsPerMinute` | `20` | Abgewiesene Aufrufe (falscher Schlüssel, falsche Signatur, Zeitstempel); darüber wird jeder Aufruf des Imports für den Rest der Minute abgewiesen |

Die Aufrufe der letzten Minuten stehen in `sqliimport_trigger_call` (nur Hashes) und werden nach zehn Minuten entfernt.

## Audit-Trail

Jeder Aufruf, der einen Import mit eingeschaltetem Auslöser erreicht, ist ein Ereignis des Audit-Trails
(`system.import.*`): `trigger.start` (Lauf-ID, Schlüssel-ID oder `signature`, ob Daten kamen), `trigger.refused` (der
Grund), `trigger.limited`; im Editor `trigger.change`, `trigger.rotate`, `trigger.key.create`, `trigger.key.revoke`.
Nie ein Geheimnis, ein Schlüssel oder eine Signatur.

## Einstellungen

| Einstellung | Standard | Bedeutung |
|---|---|---|
| `Trigger` | `enabled` | `disabled` beantwortet jeden Import mit 404 |
| `SignatureWindowSeconds` | `300` | Wie weit der Zeitstempel eines signierten Aufrufs von der Serverzeit abweichen darf |
| `RequestsPerMinute`, `StatusRequestsPerMinute`, `FailedRequestsPerMinute` | `6`, `60`, `20` | Die Grenzen oben |
| `MaxBodyMegabytes` | `20` | Der größte Body, der Daten bringt |
| `ServerKeySecret` | leer | Der Name eines Geheimnisses (32 Zeichen oder mehr) als Serverschlüssel; leer: `var/<site>/storage/sqliimport/trigger.key` |

## Tabellen

`./console ext:sqliimport:install-schema` legt sie an (MySQL, PostgreSQL, SQLite, Oracle); in einer bestehenden
Installation nach dem Update einmal ausführen:

| Tabelle | Enthält |
|---|---|
| `sqliimport_trigger` | Eine Zeile pro Import: an oder aus, Laufbenutzer, Daten erlaubt, Geheimnisname, Signatur-Salt |
| `sqliimport_trigger_key` | Die API-Schlüssel: Name, Schlüssel-ID, Hash, erstellt, zuletzt benutzt, gesperrt |
| `sqliimport_trigger_call` | Die Aufrufe der letzten Minuten: Signatur-Hashes (Schutz vor Wiederholung) und die Ratenbegrenzung |

Wird ein Import entfernt, werden sein Auslöser und seine Schlüssel mit entfernt.
