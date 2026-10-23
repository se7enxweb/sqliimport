# Einstellungen

Alle Einstellungen stehen in `sqliimport.ini`. Bearbeiten Sie nie `extension/sqliimport/settings/sqliimport.ini`;
überschreiben Sie sie in `settings/override/sqliimport.ini.append.php`, in einem Siteaccess-Verzeichnis oder in
Ihrer eigenen Erweiterung.

Englisch: [../settings.md](../settings.md).

## Inhalt

- [[SourceSettings]](#sourcesettings)
- [[HandlerSettings]](#handlersettings)
- [[RunSettings]](#runsettings)
- [[TriggerSettings]](#triggersettings)
- [[AdminSettings]](#adminsettings)
- [Einstellungen von 1.x, die weiter gelten](#einstellungen-von-1x-die-weiter-gelten)
- [Andere Einstellungsdateien](#andere-einstellungsdateien)
- [Die Seite Settings](#die-seite-settings)
  - [sqliimport.ini](#sqliimportini)
  - [Secrets (Geheimnisse)](#secrets-geheimnisse)
  - [Export import definitions](#export-import-definitions-importdefinitionen-exportieren)
  - [Import import definitions](#import-import-definitions-importdefinitionen-importieren)
- [Beispiel: einen Docs-Import exportieren und als Kopie wieder importieren](#beispiel-einen-docs-import-exportieren-und-als-kopie-wieder-importieren)

## [SourceSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Sources[]` | `csv`, `xml`, `rss`, `json`, `spreadsheet` | Die Quelltypen des Katalogs; jeder hat einen Block `[Source_<id>]` |
| `AllowedPaths[]` | leer | Die Verzeichnisse auf dem Server, aus denen eine Quelle eine Datei lesen darf (origin `path`). Leer: keine, nur Uploads und URLs. Relativ zur Installation. |
| `MaxSourceMegabytes` | `200` | Der größte Upload oder Download einer Quelle, in Megabyte |
| `AllowPrivateHosts` | `disabled` | `enabled` erlaubt einer URL-Quelle (und den Umwandlungen `image` und `file`), Adressen privater oder reservierter Netze zu lesen |

```ini
[SourceSettings]
AllowedPaths[]
AllowedPaths[]=var/import
```

### `[Source_<id>]`

| Einstellung | Bedeutung |
|---|---|
| `Class` | Die Klasse, die `expImportSourceInterface` implementiert |
| `Status` | `enabled`; `example` (angezeigt, aber für einen neuen Import nicht angeboten); `disabled` |

## [HandlerSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Handlers[]` | leer | Die Handler für 2.x neben `mapping`, das immer vorhanden ist; jeder hat einen Block `[Handler_<id>]` |

### `[Handler_<id>]`

| Einstellung | Bedeutung |
|---|---|
| `Class` | Die Klasse, die `expImportHandlerInterface` implementiert |
| `Status` | `enabled`, `example` oder `disabled` |

Die Handler von 1.x aus `[ImportSettings] AvailableSourceHandlers[]` stehen ebenfalls im Katalog, als Legacy
gekennzeichnet.

## [RunSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `ChunkSize` | `200` | Datensätze pro Abschnitt (je ein Job der Job-Warteschlange). Ein Import kann eine eigene `chunk_size` setzen. |
| `DryRunRows` | `20` | Datensätze, die ein Probelauf liest |
| `LogLevel` | `info` | Der niedrigste Schweregrad, den das Protokoll eines Laufs behält: `debug`, `info`, `notice`, `warning`, `error` |
| `KeepDays` | `90` | Beendete Läufe, die älter als so viele Tage sind, werden mit Zeilen, Protokoll und Dateien entfernt (`0` = nie) |

## [TriggerSettings]

Der Webhook-/API-Auslöser der Importe ([triggers.md](triggers.md)).

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Trigger` | `enabled` | `disabled` beantwortet jeden Import mit 404; jeder Import schaltet seinen eigenen Auslöser im Editor ein |
| `SignatureWindowSeconds` | `300` | Wie weit der Zeitstempel eines signierten Aufrufs von der Serverzeit abweichen darf |
| `RequestsPerMinute` | `6` | Aufrufe pro Import und Minute, die einen Lauf starten (`0` = keine Grenze) |
| `StatusRequestsPerMinute` | `60` | Statusabfragen pro Import und Minute |
| `FailedRequestsPerMinute` | `20` | Abgewiesene Aufrufe pro Import und Minute, bevor jeder Aufruf des Imports für den Rest der Minute abgewiesen wird |
| `MaxBodyMegabytes` | `20` | Der größte Body, den ein Aufruf als Daten seines Laufs mitbringen darf |
| `ServerKeySecret` | leer | Der Name eines Geheimnisses (mindestens 32 Zeichen) als Serverschlüssel; leer: ein zufälliger Schlüssel in `var/<site>/storage/sqliimport/trigger.key` |

## [AiSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Enabled` | `disabled` | `enabled` bietet die KI-Unterstützung über expai an ([ai.md](ai.md)), für Benutzer mit `expai/write` |
| `SampleValues` | `3` | Maskierte Beispielwerte je Feld (1 bis 5) für einen Import mit „Send sample values to the AI“ |
| `MaxFields` | `150` | Die meisten Felder der Quelle, die eine Anfrage beschreibt |

## [AdminSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `ListLimit` | `25` | Zeilen pro Seite der Listen (mindestens 5) |

## [TemplateSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Templates[]` | `sqliimport` | Woher die Vorlagen von „New import from a template“ neben denen dieser Site (`var/<site>/storage/sqliimport/templates/`) kommen: der Name einer Erweiterung (deren `share/templates/*.json`) oder eine JSON-Datei relativ zum Wurzelverzeichnis der Installation ([definitions.md](definitions.md)) |

## Einstellungen von 1.x, die weiter gelten

| Einstellung | Bedeutung in 2.x |
|---|---|
| `[ImportSettings] AvailableSourceHandlers[]` und `[<id>-HandlerSettings]` | Die Handler von 1.x und ihre Einstellungen, unverändert |
| `[ImportSettings] StreamTimeout` | Das Zeitlimit eines Downloads (URL-Quellen, Umwandlungen `image` und `file`), in Sekunden |
| `[ImportSettings] RobotUserID`, `DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing` | Wie in 1.x, für Handler von 1.x. `DefaultParentNodeID` ist außerdem der Elternknoten, den der Editor für einen neuen Import vorschlägt. |
| `[ContentSettings]` | Die Diff-Handler der Inhalts-API, verwendet vom Handler `mapping` und von Handlern von 1.x |

## Andere Einstellungsdateien

| Datei | Was die Erweiterung setzt |
|---|---|
| `cronjob.ini` | Den Cron-Teil `[CronjobPart-sqliimport]` und die veralteten Teile `sqliimport_run` und `sqliimport_cleanup` von 1.x |
| `expqueue.ini` | Die Job-Typen `[JobType_sqliimport.run]`, `[JobType_sqliimport.chunk]`, `[JobType_sqliimport.finish]` |
| `kernelservices.ini` | Den Dienst `sqliimport.registry` |
| `audit.ini` | Den Audit-Zweig `sqliimport` (`system.import.*`) |
| `menu.ini` | Den Reiter `sqliimport` (Importe) und das linke Menü des Reiters |
| `dashboard.ini` | Den Block „Imports“ des Dashboards der Administration, `[DashboardBlock_sqliimport]` (`Priority=40`, `NumberOfItems=5`) |
| `ezjscore.ini` | Die Serverfunktion `sqliimport::runstatus` der Live-Seite eines Laufs, `[ezjscServer_sqliimport]` |
| `design.ini` | Das Stylesheet und die Skripte des Reiters Importe in der Administration |

Ein Siteaccess, der `[TopAdminMenu] Tabs[]` in seiner eigenen `menu.ini.append.php` zurücksetzt, muss dort selbst
`Tabs[]=sqliimport` eintragen ([install.md](install.md)).

Leeren Sie nach einer Änderung den INI-Cache: `php bin/php/ezcache.php --clear-tag=ini`. Die Worker der
Job-Warteschlange lesen den Katalog neu, wenn sie auf einen Handler stoßen, den sie noch nicht kennen; starten Sie
lang laufende Worker nach anderen Änderungen neu.

## Die Seite Settings

**Settings** im linken Menü des Reiters Importe (`sqliimport/settings`) braucht die Richtlinie `manage`
([permissions.md](permissions.md)). Sie hat vier Teile.

### sqliimport.ini

Die Einstellungen, wie sie für die Administration nach allen Überschreibungen gelten: `[RunSettings]`,
`[SourceSettings]`, `[TriggerSettings]`, die `AllowedHosts`, Grenzen und Zeitlimits jeder Datenbank- und
Dienst-Quelle (`[SqlSourceSettings]`, `[MongoDbSourceSettings]` … `[RestApiSourceSettings]`), `[YouTubeSettings]` und
`[ImportSettings]`, jede Zeile als `[Abschnitt] Einstellung` mit ihrem Wert. Die Seite zeigt sie nur. Geändert werden
sie in `settings/override/sqliimport.ini.append.php` oder in einem Siteaccess, wie oben beschrieben.

![Der Abschnitt sqliimport.ini der Seite Settings: [RunSettings] ChunkSize 200, DryRunRows 20, LogLevel info, KeepDays 90, [SourceSettings] MaxSourceMegabytes 200, AllowPrivateHosts disabled, AllowedPaths leer, die Einstellungen des Auslösers und die ersten Einstellungen der Quellen](../images/set-ini.png)

### Secrets (Geheimnisse)

Die Namen, die ein Import für ein Passwort, einen Schlüssel oder ein Token angeben kann (eine Datenbank, ein
Postfach, eine API), aus `[SecretSettings] Secrets[]`. Die Werte werden nie gezeigt; `env:VARIABLE` nennt eine
Umgebungsvariable. Gesetzt werden sie unter **Setup, Secrets** der Administration. Auf dem Server der Dokumentation
ist kein Geheimnis gesetzt:

![Der Abschnitt Secrets der Seite Settings: die Namen, die ein Import als Passwort angeben kann, nie ihre Werte, mit dem Link zu Setup, Secrets, und No secret is set](../images/set-secrets.png)

### Export import definitions (Importdefinitionen exportieren)

Kreuzen Sie die Importe an, die exportiert werden sollen (keiner angekreuzt: alle), und wählen Sie **Export**. Der
Browser speichert eine JSON-Datei: für einen Import `sqliimport-import-<Bezeichner>.json`, für mehrere
`sqliimport-definitions-<Datum>-<Uhrzeit>.json`. Dieselbe Datei liefern **Download as JSON** eines Imports und
**Export selected** des Dashboards.

Die Datei (Format-Version 2) enthält je Import Name, Beschreibung, ob er aktiviert ist, Quelle, Handler, Optionen,
Ziel, Verhalten, Zuordnung, Zeitpläne, Auslöser und die **Namen** der Geheimnisse und Verbindungen, die er nutzt.
Elternknoten stehen zusätzlich mit Remote-ID und Pfad darin, damit eine andere Installation dieselbe Stelle findet.
Sie enthält keine Läufe, keine API-Schlüssel, keine Werte von Geheimnissen und keinen Dateiinhalt: **hochgeladene
Quelldateien sind nicht Teil eines Exports**, nur ihr ursprünglicher Name. Das Beispiel unten ist ein echter Export.

### Import import definitions (Importdefinitionen importieren)

Der Abschnitt verlinkt auf **Import definitions** (`sqliimport/transfer`) und auf **Templates**. Import definitions
liest eine exportierte Datei (höchstens 10 MB) und zeigt je Import, was sie hier anlegen würde, bevor irgendetwas
angelegt wird:

- **neu**, oder **identifier taken**, wenn es einen Import mit diesem Bezeichner schon gibt;
- Quelle und Handler, die Klasse und den Elternknoten, den sie findet (über Remote-ID und Pfad, sonst die Knoten-ID);
- was im Editor zu prüfen ist: eine Datei, die erneut hochzuladen ist, ein Geheimnis oder eine Verbindung, die hier
  fehlt.

Je Import wählen Sie **Create a new import** (ist der Bezeichner vergeben, bekommt er ein Suffix: `_2`), **Replace
the existing import** (seine Läufe und API-Schlüssel bleiben) oder **Skip it**. **Create the chosen imports
(disabled)** legt sie **deaktiviert** an, mit angehaltenen Zeitplänen und ausgeschaltetem Auslöser: Sie prüfen jeden,
laden eine Datei hoch, die er braucht, machen einen Probelauf und aktivieren ihn. Das Dashboard meldet dann, wie viele
angelegt oder ersetzt und wie viele übersprungen wurden.

So ziehen Importe von einer Staging-Installation auf die Live-Installation um. Der Export enthält die Adressen und
Optionen der Quellen; bewahren Sie die Datei so sicher auf wie die Einstellungen der Website. Siehe auch
[transfer.md](transfer.md).

![Der Abschnitt Import import definitions der Seite Settings mit den Links Import definitions und Templates](../images/set-import-section.png)

## Beispiel: einen Docs-Import exportieren und als Kopie wieder importieren

**Ziel:** eine Kopie von „Docs: Teas (TOML)“ (Import #126) über seine Definitionsdatei, wie beim Umzug eines Imports
auf eine andere Installation.

**1. Exportieren.** Auf der Seite Settings unter *Export import definitions* „Docs: Teas (TOML)“ ankreuzen und
**Export** wählen:

![Die Exportliste der Seite Settings mit angekreuztem Docs: Teas (TOML) und der Schaltfläche Export](../images/set-export.png)

Der Browser speicherte `sqliimport-import-docs-files-toml.json`. Ein Auszug (die Zeilen der Zuordnung gekürzt):

```json
{
    "format": "sqliimport-definitions",
    "version": 2,
    "exported": "2026-10-23T01:52:14Z",
    "generator": "7x SQLI Import",
    "secrets": [],
    "imports": [
        {
            "identifier": "docs-files-toml",
            "name": "Docs: Teas (TOML)",
            "description": "The same teas as an array of tables.",
            "enabled": true,
            "source_type": "toml",
            "source": {
                "origin": "upload",
                "original_name": "teas.toml",
                "format": "toml",
                "record_path": "teas",
                "key": "id"
            },
            "handler": "mapping",
            "options": [],
            "target": {
                "parent_node_id": 54984,
                "section_id": 0,
                "language": "",
                "owner_id": 0,
                "parent": {
                    "node_id": 54984,
                    "remote_id": "250095e14b516fc6e9db1ce8e89260ab",
                    "object_remote_id": "sq688doc-docs-files-toml",
                    "path": "media/sqli_import_docs_examples/teas_toml"
                },
                "section": null,
                "owner": null
            },
            "behaviour": {
                "chunk_size": 200,
                "update_policy": "update",
                "trash_policy": "skip",
                "max_rows": 0,
                "conflict_policy": "overwrite",
                "undo_days": "",
                "notify_when": "errors",
                "notify_starter": true,
                "notify_emails": []
            },
            "mapping": {
                "class": "folder",
                "language": "",
                "match": { "type": "remote_id", "pattern": "sq688doc-toml-{id}" },
                "fields": [
                    { "source": "name", "attribute": "name", "transforms": [ { "name": "trim" } ], "default": "", "required": true, "language": "" },
                    ...
                ],
                "location": { "parent_node_id": 54984, "parent": { ... } }
            },
            "schedules": [],
            "trigger": null,
            "secrets": [],
            "connections": []
        }
    ]
}
```

Die Quelle behält `original_name`, aber nicht den gespeicherten Upload (`file`). Ein Import, der ein Geheimnis liest,
nennt dessen Namen, nie den Wert: Der Export von „Docs: YouTube channel (Data API, illustrative)“ hat
`"api_key": "sq-demo-youtube-key"` in seiner Quelle und `"secrets": [ "sq-demo-youtube-key" ]` oben und im Import,
sonst nichts vom Schlüssel.

**2. Umbenennen.** Damit sich die Kopie unterscheidet, wurde der Name in einer Kopie der Datei zu „Docs: Teas (TOML)
(imported definition)“ geändert. Der Bezeichner blieb, um zu zeigen, was geschieht, wenn er vergeben ist.

**3. Importieren.** **Import definitions**, Datei wählen, hochladen:

![Die Seite Import definitions, bevor eine Datei gewählt ist](../images/set-transfer-upload.png)

Der Plan sagt: Der Bezeichner `docs-files-toml` ist vergeben, die Quelle ist TOML mit der Zuordnung zu `folder`, der
Elternknoten #54984 wurde gefunden, ein neuer Import hieße `docs-files-toml_2`, und *To check in the editor: upload
the file teas.toml again in the editor (files are not part of an export)*. Die Wahl ist **Create a new import
(docs-files-toml_2)**:

![Der Plan von Import definitions: Docs: Teas (TOML) (imported definition), Bezeichner vergeben, Quelle TOML und Mapping, Klasse folder, Elternknoten #54984, ein neuer hieße docs-files-toml_2, der Hinweis, teas.toml erneut hochzuladen, und die Wahl Create a new import](../images/set-transfer-plan.png)

**4. Ergebnis.** **Create the chosen imports (disabled)** führte zurück zum Dashboard mit „1 imports created or
replaced, disabled: review, dry-run and enable each. 0 skipped. Docs: Teas (TOML) (imported definition)“:

![Die Meldung auf dem Dashboard: 1 Import angelegt oder ersetzt, deaktiviert, 0 übersprungen, und der Name des neuen Imports](../images/set-transfer-done.png)

Der neue Import `docs-files-toml_2` ist deaktiviert und hat noch keine Datei: Laden Sie im Schritt Quelle `teas.toml`
hoch, machen Sie einen Probelauf (3 Tees würden in denselben Ordner angelegt, über dieselben Remote-IDs wie beim
Original; ein echter Lauf würde also die Objekte des Originals aktualisieren statt neue anzulegen) und aktivieren Sie
ihn im Schritt Überprüfung.

So wandern Importe von einer Staging-Installation in die Live-Installation. Die Datei enthält die Adressen und
Optionen der Quellen; bewahren Sie sie so sicher auf wie die Einstellungen der Website. **Download as JSON** eines
Imports, **Export selected** der Übersicht, **Copy to …** und die Vorlagen verwenden dasselbe Format;
[definitions.md](definitions.md) beschreibt es Feld für Feld.
