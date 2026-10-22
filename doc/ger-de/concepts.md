# Begriffe

## Import

Ein Import ist eine Zeile von `sqliimport_definition` (Klasse `expImportDefinition`). Er legt fest:

| Feld | Bedeutung |
|---|---|
| `identifier`, `name` | Wie Kommandozeile und Administration ihn nennen. Der Bezeichner ist eindeutig. |
| `source_type` | `csv`, `xml`, `rss`, `json` oder eine andere registrierte Quelle; `legacy` für einen Handler von 1.x, der seine Daten selbst liest |
| `source_config` | JSON: `origin` (`upload`, `url`, `path`), die Datei, Adresse oder der Pfad, und die Optionen des Quelltyps |
| `handler` | `mapping` (ohne Programmierung), ein Handler für 2.x oder ein Handler von 1.x |
| `mapping_id` | Die Zuordnung des Handlers `mapping` |
| `target_json` | JSON: `parent_node_id`, `section_id`, `language`, `owner_id` (ein Robot-Benutzer; 0 = der Benutzer, der startet) |
| `behaviour_json` | JSON: `chunk_size`, `update_policy`, `trash_policy`, `max_rows` |
| `options_json` | JSON: die Optionen des Handlers, geprüft gegen sein Schema |
| `is_enabled` | Ein deaktivierter Import kann nicht laufen (ein Probelauf schon) |
| `def_version` | Steigt bei jeder Änderung; ein Lauf merkt sich die Version, mit der er lief |

## Quelle

Ein Quelltyp liest Datensätze aus einer Datei: [sources.md](sources.md). Die Engine legt zuerst eine lokale
Momentaufnahme der Daten an (eine hochgeladene Datei, ein Download oder eine Datei auf dem Server), sodass jeder
Abschnitt eines Laufs dieselben Daten liest.

## Datensatz

Ein Datensatz ist eine Zeile einer CSV-Datei, ein Element einer XML-Datei, ein Eintrag eines Feeds oder ein Objekt
einer JSON-Datei (Klasse `expImportRecord`). Er hat einen Verweis (`ref`: ein Schlüsselfeld oder seine Position),
seine Daten als Array und einen Cursor, der der Quelle sagt, wo sie nach ihm weiterliest. Verschachtelte Werte
werden über Pfade mit Punkten gelesen, etwa `author.name` oder `items.0.title`.

## Handler

Ein Handler macht aus einem Datensatz Inhalt:

- **`mapping`** wendet die Zuordnung des Imports an. Es ist kein PHP nötig: [mappings.md](mappings.md).
- **Ein Handler für 2.x** implementiert `expImportHandlerInterface`: [handlers.md](handlers.md).
- **Ein Handler von 1.x** (`ISQLIImportHandler`) läuft über `expImportLegacyHandlerAdapter`. Er liest seine Daten
  selbst, daher hat sein Import den Quelltyp `legacy`.

## Zuordnung

Eine Zuordnung (Tabellen `sqliimport_mapping` und `sqliimport_mapping_field`) nennt die Inhaltsklasse, wie ein
Datensatz sein Objekt findet (ein Muster für die Remote-ID oder der Wert eines Attributs), welches Quellfeld über
welche Umwandlungen welches Attribut füllt und wo neue Objekte angelegt werden.

## Lauf

Ein Lauf (Tabelle `sqliimport_run`, Klasse `expImportRun`) ist eine Ausführung eines Imports. Seine Art ist
`import`, `dry_run` oder `retry`. Sein Auslöser ist `manual`, `schedule`, `cli`, `rest` oder `legacy`. Die
Job-Warteschlange verarbeitet einen Lauf in Abschnitten: [runs.md](runs.md).

## Zeile und Protokoll

Jeder Datensatz eines Laufs bekommt eine Zeile (Tabelle `sqliimport_run_row`) mit seinem Ergebnis:

| Ergebnis | Bedeutung |
|---|---|
| `created` | Ein neues Objekt wurde veröffentlicht |
| `updated` | Das Objekt hat eine neue Version, weil sich ein Feld geändert hat |
| `unchanged` | Das Objekt existiert, kein Feld hat sich geändert; nichts wurde veröffentlicht |
| `skipped` | Der Datensatz wurde absichtlich ausgelassen (zum Beispiel liegt das Objekt im Papierkorb) |
| `failed` | Der Datensatz konnte nicht verarbeitet werden; die Zeile enthält die Meldung und den Datensatz |
| `processed` | Ein Handler von 1.x hat den Datensatz verarbeitet; was er getan hat, kennt die Engine nicht |

Jeder Lauf hat außerdem ein Protokoll (Tabelle `sqliimport_run_log`) mit einem Schweregrad pro Zeile und, wo es
passt, dem Datensatz, um den es in der Zeile geht.

## Zeitplan

Ein Zeitplan (Tabelle `sqliimport_schedule`) startet einen Import von selbst: [schedules.md](schedules.md).

## Die Tabellen

| Tabelle | Enthält |
|---|---|
| `sqliimport_definition` | Die Importe |
| `sqliimport_mapping`, `sqliimport_mapping_field` | Die Zuordnungen und ihre Zeilen |
| `sqliimport_schedule` | Die Zeitpläne |
| `sqliimport_run`, `sqliimport_run_row`, `sqliimport_run_log` | Die Läufe, ihre Zeilen und ihre Protokolle |
| `sqliimport_item`, `sqliimport_scheduled` | Die Tabellen von 1.x, beibehalten |

## Der Katalog

Der Kernel-Dienst `sqliimport.registry` (Klasse `expImportRegistry`) führt die Quellen, Handler und Umwandlungen
einer Website. Er liest `sqliimport.ini` und die Kernel-Dienste mit den Tags `sqliimport.source`,
`sqliimport.handler` und `sqliimport.transform`.

## Audit-Trail

Der Audit-Trail verzeichnet unter `system.import.*`: gestartete, beendete, fehlgeschlagene und abgebrochene Läufe;
angelegte, geänderte und gelöschte Importe; geänderte Zeitpläne; und die Migration von 1.x. Die Zeilen eines Laufs
werden nicht in den Audit-Trail kopiert; sie bleiben in `sqliimport_run_row`.
