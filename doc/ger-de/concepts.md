# Begriffe

Diese Seite benennt die Teile von 7x SQLI Import und zeigt, wie sie zusammenhängen: ein Import, seine Quelle und sein
Handler, seine Zuordnung, seine Läufe mit ihren Zeilen und Protokollen, sein Zeitplan, sein Auslöser und die Hinweise
seiner Läufe. Der letzte Abschnitt verfolgt einen echten Import der Testinstallation durch alle diese Teile.

English: [../concepts.md](../concepts.md).

## Inhalt

- [Import](#import)
- [Quelle](#quelle)
- [Datensatz](#datensatz)
- [Handler](#handler)
- [Zuordnung](#zuordnung)
- [Lauf](#lauf)
- [Zeile und Protokoll](#zeile-und-protokoll)
- [Zeitplan](#zeitplan)
- [Auslöser](#auslöser)
- [Hinweis](#hinweis)
- [Wie die Teile zusammenhängen: Import #132](#wie-die-teile-zusammenhängen-import-132)
- [Die Tabellen](#die-tabellen)
- [Der Katalog](#der-katalog)
- [Audit-Trail](#audit-trail)

## Import

Ein Import ist eine Zeile von `sqliimport_definition` (Klasse `expImportDefinition`). Er legt fest:

| Feld | Bedeutung |
|---|---|
| `identifier`, `name` | Wie Kommandozeile und Administration ihn nennen. Der Bezeichner ist eindeutig. |
| `source_type` | Das Format: `csv`, `tsv`, `spreadsheet`, `json`, `ndjson`, `xml`, `rss`, `sql`, `mongodb`, `wxr` und jede andere registrierte Quelle ([sources.md](sources.md)); `legacy` für einen Handler von 1.x, der seine Daten selbst liest |
| `source_config` | JSON: `origin` (`upload`, `url`, `path` oder `remote` für eine Quelle, die eine Datenbank oder einen Dienst liest), die Datei, Adresse oder der Pfad, und die Optionen des Quelltyps |
| `handler` | `mapping` (ohne Programmierung), `datatable` (Zeilen einer Datentabelle), ein anderer Handler für 2.x oder ein Handler von 1.x |
| `mapping_id` | Die Zuordnung des Handlers `mapping` |
| `target_json` | JSON: `parent_node_id`, `section_id`, `language`, `owner_id` (ein Robot-Benutzer; 0 = der Benutzer, der startet) |
| `behaviour_json` | JSON: `chunk_size`, `update_policy`, `trash_policy`, `max_rows`, `conflict_policy`, `undo_days`, `notify_when`, `notify_starter`, `notify_emails` |
| `options_json` | JSON: die Optionen des Handlers, geprüft gegen sein Schema |
| `is_enabled` | Ein deaktivierter Import kann nicht laufen (ein Probelauf schon) |
| `def_version` | Steigt bei jeder Änderung; ein Lauf merkt sich die Version, mit der er lief |

## Quelle

Ein Quelltyp liest Datensätze: aus einer Datei, von einer Adresse, aus einer Datenbank oder einem Dienst
([sources.md](sources.md)). Die Engine legt, wo sie kann, zuerst eine lokale Momentaufnahme der Daten an (eine
hochgeladene Datei, ein Download, eine Datei auf dem Server), sodass jeder Abschnitt eines Laufs dieselben Daten liest.

## Datensatz

Ein Datensatz ist eine Zeile einer CSV-Datei, ein Element einer XML-Datei, ein Eintrag eines Feeds, ein Objekt einer
JSON-Datei oder eine Zeile einer Datenbank (Klasse `expImportRecord`). Er hat einen Verweis (`ref`: ein Schlüsselfeld
oder seine Position), seine Daten als Array und einen Cursor, der der Quelle sagt, wo sie nach ihm weiterliest.
Verschachtelte Werte werden über Pfade mit Punkten gelesen, etwa `author.name` oder `items.0.title`.

## Handler

Ein Handler macht aus einem Datensatz Inhalt oder Zeilen:

- **`mapping`** wendet die Zuordnung des Imports an. Es ist kein PHP nötig: [mappings.md](mappings.md).
- **`datatable`** schreibt den Datensatz als Zeile in die eigene Tabelle eines Moduls: [datatables.md](datatables.md).
- **Ein Handler für 2.x** implementiert `expImportHandlerInterface`: [handlers.md](handlers.md).
- **Ein Handler von 1.x** (`ISQLIImportHandler`) läuft über `expImportLegacyHandlerAdapter`. Er liest seine Daten
  selbst, daher hat sein Import den Quelltyp `legacy`.

## Zuordnung

Eine Zuordnung (Tabellen `sqliimport_mapping` und `sqliimport_mapping_field`) nennt die Inhaltsklasse, wie ein
Datensatz sein Objekt findet (ein Muster für die Remote-ID oder der Wert eines Attributs), welches Quellfeld über
welche Umwandlungen welches Attribut füllt und wo neue Objekte angelegt werden.

## Lauf

Ein Lauf (Tabelle `sqliimport_run`, Klasse `expImportRun`) ist eine Ausführung eines Imports. Seine Art ist
`import`, `dry_run`, `retry` (die fehlgeschlagenen Zeilen eines früheren Laufs) oder `undo` (nimmt einen früheren Lauf
zurück). Sein Auslöser ist `manual`, `schedule`, `cli`, `rest` (der Webhook- / API-Auslöser) oder `legacy`. Eine
Wiederholung oder ein Rückgängigmachen nennt ihren früheren Lauf in `parent_run_id`. Die Job-Warteschlange verarbeitet
einen Lauf in Abschnitten: [runs.md](runs.md).

## Zeile und Protokoll

Jeder Datensatz eines Laufs bekommt eine Zeile (Tabelle `sqliimport_run_row`) mit seinem Ergebnis:

| Ergebnis | Bedeutung |
|---|---|
| `created` | Ein neues Objekt wurde veröffentlicht |
| `updated` | Das Objekt hat eine neue Version, weil sich ein Feld geändert hat |
| `unchanged` | Das Objekt existiert, kein Feld hat sich geändert; nichts wurde veröffentlicht |
| `skipped` | Der Datensatz wurde absichtlich ausgelassen (zum Beispiel liegt das Objekt im Papierkorb, oder die Konfliktregel sagt überspringen) |
| `failed` | Der Datensatz konnte nicht verarbeitet werden; die Zeile enthält die Meldung und den Datensatz |
| `undone` | Ein Rückgängig-Lauf hat zurückgenommen, was der frühere Lauf mit diesem Objekt getan hat |
| `processed` | Ein Handler von 1.x hat den Datensatz verarbeitet; was er getan hat, kennt die Engine nicht |

Jeder Lauf hat außerdem ein Protokoll (Tabelle `sqliimport_run_log`) mit einem Schweregrad pro Zeile und, wo es
passt, dem Datensatz, um den es in der Zeile geht.

## Zeitplan

Ein Zeitplan (Tabelle `sqliimport_schedule`) startet einen Import von selbst: [schedules.md](schedules.md). Ein
Import hat im Editor höchstens einen Zeitplan.

## Auslöser

Ein Auslöser (Tabelle `sqliimport_trigger`) lässt ein anderes System den Import mit einem signierten Aufruf oder
einem API-Schlüssel starten; seine Schlüssel liegen in `sqliimport_trigger_key` (nur ein Hash jedes Schlüssels), und
jeder Aufruf wird in `sqliimport_trigger_call` vermerkt: [triggers.md](triggers.md).

## Hinweis

Ein Hinweis (Tabelle `sqliimport_notice`) sagt dem Benutzer, der einen Lauf gestartet hat, wie er endete: auf der
Übersicht und in der Glocke der Kopfzeile, bis er gesehen ist ([runs.md](runs.md#benachrichtigungen)).

## Wie die Teile zusammenhängen: Import #132

Der Import „Docs: First import (teas CSV)“ aus [getting-started.md](getting-started.md) hat auf der Testinstallation
all das durchlaufen. Von oben nach unten gelesen zeigt die Tabelle, was wozu gehört:

| Teil | Eintrag auf der Testinstallation | Was er sagt |
|---|---|---|
| Import | `sqliimport_definition` #132, `docs-first-import`, Quelle `csv`, Handler `mapping`, aktiviert | die Definition |
| └ Zuordnung | `sqliimport_mapping` #101, Klasse `product`, 4 Zeilen in `sqliimport_mapping_field` | was welches Attribut füllt |
| └ Zeitplan | `sqliimport_schedule` #2: Cron `0 6 * * 1-5`, Zeitzone `Europe/Berlin`, angehalten (`is_active` 0) | werktags um 06:00 Berliner Zeit, ausgeschaltet |
| └ Auslöser | `sqliimport_trigger` #1, nach den Beispielen wieder ausgeschaltet (er war an, mit dem Test-Administrator der Dokumentation als Laufbenutzer) | der Webhook- / API-Auslöser |
| └└ Schlüssel | `sqliimport_trigger_key` #1, Bezeichnung „Docs: curl example“, nach den Beispielen gesperrt | ein API-Schlüssel (sein Hash) |
| └└ Aufrufe | 2 Zeilen in `sqliimport_trigger_call` | die Aufrufe, die er bekam |
| └ Lauf #180 | `import`, `manual`, abgeschlossen: 5 angelegt; 5 Zeilen, 2 Protokollzeilen | der erste Lauf |
| └ Lauf #181 | `dry_run`, `manual`, mit Fehlern abgeschlossen: 1 angelegt, 2 aktualisiert, 3 unverändert, 1 fehlgeschlagen; 7 Zeilen | der Probelauf der geänderten Datei |
| └└ Lauf #182 | `retry` von #181 (`parent_run_id` 181): die 1 fehlgeschlagene Zeile schlug wieder fehl; 1 Zeile | eine Wiederholung nimmt den gespeicherten Datensatz, ein Datenfehler bleibt also |
| └ Lauf #184 | `import`, `manual`, abgeschlossen: 2 angelegt, 2 aktualisiert, 3 unverändert; 7 Zeilen | der zweite Lauf mit der korrigierten Datei |
| └ Läufe #192, #193, #194, #196 | `dry_run`, einer pro Konfliktregel | die Beispiele der Konfliktregeln |
| └ Lauf #200 | `import`, abgeschlossen: 1 aktualisiert, 6 unverändert | das Beispiel für merge |
| └└ Lauf #201 | `undo` von #200 (`parent_run_id` 200): 1 rückgängig gemacht | nimmt #200 zurück |
| └ Läufe #202, #204 | `import`, Auslöser `rest`: 7 unverändert; dann 1 angelegt, 1 aktualisiert (2 Datensätze im Aufruf geschickt) | vom Auslöser gestartet |
| └ Lauf #215 | `import`, `manual`, abgeschlossen: 1 aktualisiert, 6 unverändert | ein späterer Lauf |
| └ Hinweise | #15 (Lauf 181, Fehler, gesehen), #16 (Lauf 182, Fehler, gesehen), #19 (Lauf 215, abgeschlossen, noch nicht gesehen) | was dem Starter gesagt wurde |

Jeder Lauf hat seine eigenen Zeilen (`sqliimport_run_row`, eine pro Datensatz: 5 bei #180, 7 bei #184) und
Protokollzeilen (`sqliimport_run_log`). Auch ein Probelauf hat Zeilen, mit dem, was geschehen wäre, veröffentlicht
aber nichts. Wird der Import entfernt, gehen seine Zuordnung und sein Zeitplan mit; seine Läufe, Zeilen, Protokolle
und Hinweise bleiben, bis `[RunSettings] KeepDays` sie bereinigt.

## Die Tabellen

| Tabelle | Enthält |
|---|---|
| `sqliimport_definition` | Die Importe |
| `sqliimport_mapping`, `sqliimport_mapping_field` | Die Zuordnungen und ihre Zeilen |
| `sqliimport_schedule` | Die Zeitpläne |
| `sqliimport_run`, `sqliimport_run_row`, `sqliimport_run_log` | Die Läufe, ihre Zeilen und ihre Protokolle |
| `sqliimport_notice` | Die Hinweise beendeter Läufe, pro Benutzer |
| `sqliimport_trigger`, `sqliimport_trigger_key`, `sqliimport_trigger_call` | Die Auslöser, ihre API-Schlüssel (als Hash) und ihre Aufrufe |
| `sqliimport_item`, `sqliimport_scheduled` | Die Tabellen von 1.x, beibehalten |

`./console ext:sqliimport:install-schema --check` führt alle dreizehn auf
([install.md](install.md#die-installation-prüfen)).

## Der Katalog

Der Kernel-Dienst `sqliimport.registry` (Klasse `expImportRegistry`) führt die Quellen, Handler und Umwandlungen
einer Website. Er liest `sqliimport.ini` und die Kernel-Dienste mit den Tags `sqliimport.source`,
`sqliimport.handler` und `sqliimport.transform`. Die Seite **Catalogue** zeigt sie, gruppiert.

## Audit-Trail

Der Audit-Trail verzeichnet unter `system.import.*`: gestartete, beendete, fehlgeschlagene und abgebrochene Läufe;
angelegte, geänderte und gelöschte Importe; geänderte Zeitpläne; Aufrufe des Auslösers; und die Migration von 1.x.
Die Zeilen eines Laufs werden nicht in den Audit-Trail kopiert; sie bleiben in `sqliimport_run_row`.
