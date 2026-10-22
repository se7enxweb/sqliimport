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
