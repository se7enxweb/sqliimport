# Einstellungen

Alle Einstellungen stehen in `sqliimport.ini`. Bearbeiten Sie nie `extension/sqliimport/settings/sqliimport.ini`;
überschreiben Sie sie in `settings/override/sqliimport.ini.append.php`, in einem Siteaccess-Verzeichnis oder in
Ihrer eigenen Erweiterung.

## [SourceSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `Sources[]` | `csv`, `xml`, `rss`, `json` | Die Quelltypen des Katalogs; jeder hat einen Block `[Source_<id>]` |
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

## [AdminSettings]

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `ListLimit` | `25` | Zeilen pro Seite der Listen (mindestens 5) |

## Einstellungen von 1.x, die weiter gelten

| Einstellung | Bedeutung in 2.x |
|---|---|
| `[ImportSettings] AvailableSourceHandlers[]` und `[<id>-HandlerSettings]` | Die Handler von 1.x und ihre Einstellungen, unverändert |
| `[ImportSettings] StreamTimeout` | Das Zeitlimit eines Downloads (URL-Quellen, Umwandlungen `image` und `file`), in Sekunden |
| `[ImportSettings] RobotUserID`, `DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing` | Wie in 1.x, für Handler von 1.x |
| `[ContentSettings]` | Die Diff-Handler der Inhalts-API, verwendet vom Handler `mapping` und von Handlern von 1.x |

## Andere Einstellungsdateien

| Datei | Was die Erweiterung setzt |
|---|---|
| `cronjob.ini` | Den Cron-Teil `[CronjobPart-sqliimport]` und die veralteten Teile `sqliimport_run` und `sqliimport_cleanup` von 1.x |
| `expqueue.ini` | Die Job-Typen `[JobType_sqliimport.run]`, `[JobType_sqliimport.chunk]`, `[JobType_sqliimport.finish]` |
| `kernelservices.ini` | Den Dienst `sqliimport.registry` |
| `audit.ini` | Den Audit-Zweig `sqliimport` (`system.import.*`) |
| `menu.ini` | Den Reiter `sqliimport` (Importe) und das linke Menü des Reiters |

Ein Siteaccess, der `[TopAdminMenu] Tabs[]` in seiner eigenen `menu.ini.append.php` zurücksetzt, muss dort selbst
`Tabs[]=sqliimport` eintragen ([install.md](install.md)).

Leeren Sie nach einer Änderung den INI-Cache: `php bin/php/ezcache.php --clear-tag=ini`. Die Worker der
Job-Warteschlange lesen den Katalog neu, wenn sie auf einen Handler stoßen, den sie noch nicht kennen; starten Sie
lang laufende Worker nach anderen Änderungen neu.
