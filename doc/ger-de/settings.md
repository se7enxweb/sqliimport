# Einstellungen

Alle Einstellungen stehen in `sqliimport.ini`. Bearbeiten Sie nie `extension/sqliimport/settings/sqliimport.ini`;
überschreiben Sie sie in `settings/override/sqliimport.ini.append.php`, in einem Siteaccess-Verzeichnis oder in
Ihrer eigenen Erweiterung.

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
([permissions.md](permissions.md)). Sie hat drei Teile.

### sqliimport.ini

Die Einstellungen, wie sie für die Administration nach allen Überschreibungen gelten: `[RunSettings] ChunkSize`,
`DryRunRows`, `LogLevel`, `KeepDays`; `[SourceSettings] MaxSourceMegabytes`, `AllowPrivateHosts`, `AllowedPaths`;
`[ImportSettings] DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing`, `StreamTimeout`; `[AdminSettings]
ListLimit`. Die Seite zeigt sie nur an. Geändert werden sie in `settings/override/sqliimport.ini.append.php` oder in
einem Siteaccess, wie oben beschrieben.

### Export import definitions (Importdefinitionen exportieren)

Kreuzen Sie die zu exportierenden Importe an (keiner angekreuzt: alle, die Sie verwalten dürfen) und wählen Sie
**Export**. Der Browser speichert eine Definitionsdatei (Format `sqliimport-definitions`, Version 2): Quelle,
Verarbeitung, Zuordnung, Ziel (mit Remote-IDs neben den Knoten-IDs), Verhalten, Optionen, Zeitpläne und
Trigger-Einstellungen jedes Imports sowie die Namen der Geheimnisse, die sie brauchen. Sie enthält keine Läufe, keine
hochgeladene Datei, keinen API-Schlüssel, kein Signatursalz und keinen geheimen Wert. Dieselbe Datei entsteht mit
**Download as JSON** eines Imports und **Export selected** der Übersicht; [definitions.md](definitions.md) beschreibt
sie Feld für Feld.

### Import import definitions (Importdefinitionen importieren)

**Import definitions** (`sqliimport/transfer`) liest eine solche Datei, auch eine der Version 1: Die Seite zeigt je
Import, was sie hier anlegen würde (neu oder ein vergebener Bezeichner, die Klasse und der Elternknoten, zu denen die
Referenzen führen, fehlende Geheimnisse, Klassen, Attribute oder Knoten), lässt Sie anlegen, ersetzen oder
überspringen wählen und legt die gewählten Importe deaktiviert an, ihre Zeitpläne pausiert und ihren Trigger
ausgeschaltet ([definitions.md](definitions.md)).

So kommen Importe von einer Staging-Installation auf die Live-Installation. Die Datei enthält die Adressen und
Optionen der Quellen; bewahren Sie sie daher so sicher auf wie die Einstellungen der Website.
