# Settings

All settings are in `sqliimport.ini`. Never edit `extension/sqliimport/settings/sqliimport.ini`; override it in
`settings/override/sqliimport.ini.append.php`, a siteaccess directory, or your own extension.

## [SourceSettings]

| Setting | Default | Meaning |
|---|---|---|
| `Sources[]` | `csv`, `xml`, `rss`, `json`, `spreadsheet` | The source types of the catalogue; each has a `[Source_<id>]` block |
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

## [TriggerSettings]

The webhook / API trigger of imports ([triggers.md](triggers.md)).

| Setting | Default | Meaning |
|---|---|---|
| `Trigger` | `enabled` | `disabled` answers 404 for every import; each import switches its own trigger on in the editor |
| `SignatureWindowSeconds` | `300` | How far a signed call's timestamp may be from the server's time |
| `RequestsPerMinute` | `6` | Calls per import and minute that start a run (`0` = no limit) |
| `StatusRequestsPerMinute` | `60` | Status requests per import and minute |
| `FailedRequestsPerMinute` | `20` | Refused calls per import and minute before every call of the import is refused for the rest of the minute |
| `MaxBodyMegabytes` | `20` | The largest body a call may bring as the data of its run |
| `ServerKeySecret` | empty | The name of a secret (at least 32 characters) used as the server key; empty: a random key in `var/<site>/storage/sqliimport/trigger.key` |

## [AiSettings]

| Setting | Default | Meaning |
|---|---|---|
| `Enabled` | `disabled` | `enabled` offers the AI assist through expai ([ai.md](ai.md)) to users with `expai/write` |
| `SampleValues` | `3` | Masked sample values per field (1 to 5) for an import with "Send sample values to the AI" |
| `MaxFields` | `150` | The most source fields one request describes |

## [AdminSettings]

| Setting | Default | Meaning |
|---|---|---|
| `ListLimit` | `25` | Rows per page of the lists (at least 5) |

## [TemplateSettings]

| Setting | Default | Meaning |
|---|---|---|
| `Templates[]` | `sqliimport` | Where the templates of "New import from a template" come from besides this site's own (`var/<site>/storage/sqliimport/templates/`): the name of an extension (its `share/templates/*.json`) or a JSON file relative to the installation's root ([definitions.md](definitions.md)) |

## Settings of 1.x that still apply

| Setting | Meaning in 2.x |
|---|---|
| `[ImportSettings] AvailableSourceHandlers[]` and `[<id>-HandlerSettings]` | The 1.x handlers and their settings, unchanged |
| `[ImportSettings] StreamTimeout` | The time limit of a download (URL sources, `image` and `file` transforms), in seconds |
| `[ImportSettings] RobotUserID`, `DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing` | As in 1.x, for 1.x handlers. `DefaultParentNodeID` is also the parent node the editor proposes for a new import. |
| `[ContentSettings]` | The diff handlers of the content API, used by the `mapping` handler and by 1.x handlers |

## Other settings files

| File | What the extension sets |
|---|---|
| `cronjob.ini` | The cron part `[CronjobPart-sqliimport]`, and the deprecated 1.x parts `sqliimport_run` and `sqliimport_cleanup` |
| `expqueue.ini` | The job types `[JobType_sqliimport.run]`, `[JobType_sqliimport.chunk]`, `[JobType_sqliimport.finish]` |
| `kernelservices.ini` | The service `sqliimport.registry` |
| `audit.ini` | The audit branch `sqliimport` (`system.import.*`) |
| `menu.ini` | The tab `sqliimport` (named Imports) and the left menu of the tab |
| `dashboard.ini` | The block "Imports" of the admin dashboard, `[DashboardBlock_sqliimport]` (`Priority=40`, `NumberOfItems=5`) |
| `ezjscore.ini` | The server function `sqliimport::runstatus` of the live run page, `[ezjscServer_sqliimport]` |
| `design.ini` | The stylesheet and the scripts of the Imports tab in the administration |

A siteaccess that resets `[TopAdminMenu] Tabs[]` in its own `menu.ini.append.php` must list `Tabs[]=sqliimport` there
itself ([install.md](install.md)).

After changing a setting, clear the INI cache: `php bin/php/ezcache.php --clear-tag=ini`. The job queue's workers
read the catalogue again when they meet a handler they do not know yet; restart long-running workers after other
changes.

## The Settings page

**Settings** in the left menu of the Imports tab (`sqliimport/settings`) needs the policy `manage`
([permissions.md](permissions.md)). It has three parts.

### sqliimport.ini

The settings as they stand for the administration, after every override: `[RunSettings] ChunkSize`, `DryRunRows`,
`LogLevel`, `KeepDays`; `[SourceSettings] MaxSourceMegabytes`, `AllowPrivateHosts`, `AllowedPaths`;
`[ImportSettings] DefaultParentNodeID`, `ViewCaching`, `ObjectIndexing`, `StreamTimeout`; `[AdminSettings]
ListLimit`. The page only shows them. They are changed in `settings/override/sqliimport.ini.append.php` or a
siteaccess, as described above.

### Export import definitions

Tick the imports to export (none ticked: all you may manage) and choose **Export**. The browser saves a definitions
file (format `sqliimport-definitions`, version 2): the source, processing, mapping, target (with remote IDs next to
the node IDs), behavior, options, schedules and trigger settings of each import, and the names of the secrets they
need. It holds no runs, no uploaded file, no API key, no signing salt and no secret value. The same file comes from
**Download as JSON** of one import and **Export selected** of the dashboard; [definitions.md](definitions.md)
describes it field by field.

### Import import definitions

**Import definitions** (`sqliimport/transfer`) reads such a file, also one of version 1: it shows per import what it
would create here (new or an identifier that is taken, the class and parent node it resolves to, missing secrets,
classes, attributes or nodes), lets you choose create new, replace or skip, and creates the chosen imports disabled,
with their schedules paused and their trigger switched off ([definitions.md](definitions.md)).

This is how imports move from a staging installation to the live one. The file contains the addresses and options
of the sources, so keep it as safe as the settings of the site.
