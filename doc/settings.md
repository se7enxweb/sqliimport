# Settings

All settings are in `sqliimport.ini`. Never edit `extension/sqliimport/settings/sqliimport.ini`; override it in
`settings/override/sqliimport.ini.append.php`, a siteaccess directory, or your own extension.

## [SourceSettings]

| Setting | Default | Meaning |
|---|---|---|
| `Sources[]` | `csv`, `xml`, `rss`, `json` | The source types of the catalogue; each has a `[Source_<id>]` block |
| `AllowedPaths[]` | empty | The directories on the server a source may read a file from (origin `path`). Empty: none, only uploads and URLs. Relative to the installation. |
| `MaxSourceMegabytes` | `200` | The largest upload or download of a source, in megabytes |
| `AllowPrivateHosts` | `disabled` | `enabled` lets a URL source (and the `image` and `file` transforms) read addresses of private or reserved networks |

```ini
[SourceSettings]
AllowedPaths[]
AllowedPaths[]=var/import
```

### `[Source_<id>]`

| Setting | Meaning |
|---|---|
| `Class` | The class, implementing `expImportSourceInterface` |
| `Status` | `enabled`; `example` (shown, but not offered for a new import); `disabled` |

## [HandlerSettings]

| Setting | Default | Meaning |
|---|---|---|
| `Handlers[]` | empty | The 2.x handlers besides `mapping`, which is always there; each has a `[Handler_<id>]` block |

### `[Handler_<id>]`

| Setting | Meaning |
|---|---|
| `Class` | The class, implementing `expImportHandlerInterface` |
| `Status` | `enabled`, `example` or `disabled` |

The 1.x handlers of `[ImportSettings] AvailableSourceHandlers[]` are listed in the catalogue too, marked legacy.

## [RunSettings]

| Setting | Default | Meaning |
|---|---|---|
| `ChunkSize` | `200` | Records per chunk (one job of the job queue each). An import can set its own `chunk_size`. |
| `DryRunRows` | `20` | Records a dry run reads |
| `LogLevel` | `info` | The lowest severity kept in a run's log: `debug`, `info`, `notice`, `warning`, `error` |
| `KeepDays` | `90` | Ended runs older than this many days are removed with their rows, log and files (`0` = never) |

## [AdminSettings]

| Setting | Default | Meaning |
|---|---|---|
| `ListLimit` | `25` | Rows per page of the lists (at least 5) |

## Settings of 1.x that still apply

| Setting | Meaning in 2.x |
|---|---|
| `[ImportSettings] AvailableSourceHandlers[]` and `[<id>-HandlerSettings]` | The 1.x handlers and their settings, unchanged |
| `[ImportSettings] StreamTimeout` | The time limit of a download (URL sources, `image` and `file` transforms), in seconds |
| `[ImportSettings] RobotUserID`, `DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing` | As in 1.x, for 1.x handlers |
| `[ContentSettings]` | The diff handlers of the content API, used by the `mapping` handler and by 1.x handlers |

## Other settings files

| File | What the extension sets |
|---|---|
| `cronjob.ini` | The cron part `[CronjobPart-sqliimport]`, and the deprecated 1.x parts `sqliimport_run` and `sqliimport_cleanup` |
| `expqueue.ini` | The job types `[JobType_sqliimport.run]`, `[JobType_sqliimport.chunk]`, `[JobType_sqliimport.finish]` |
| `kernelservices.ini` | The service `sqliimport.registry` |
| `audit.ini` | The audit branch `sqliimport` (`system.import.*`) |
| `menu.ini` | The tab `sqliimport` (named Imports) and the left menu of the tab |

A siteaccess that resets `[TopAdminMenu] Tabs[]` in its own `menu.ini.append.php` must list `Tabs[]=sqliimport` there
itself ([install.md](install.md)).

After changing a setting, clear the INI cache: `php bin/php/ezcache.php --clear-tag=ini`. The job queue's workers
read the catalogue again when they meet a handler they do not know yet; restart long-running workers after other
changes.
