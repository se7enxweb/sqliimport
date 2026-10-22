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

## [AdminSettings]

| Setting | Default | Meaning |
|---|---|---|
| `ListLimit` | `25` | Rows per page of the lists (at least 5) |

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

Tick the imports to export (none ticked: all) and choose **Export**. The browser saves a JSON file named
`sqliimport-definitions-<date>-<time>.json`:

```json
{
    "format": "sqliimport-definitions",
    "version": 1,
    "exported": "2026-10-22T09:30:00+02:00",
    "imports": [
        {
            "identifier": "news_feed",
            "name": "News from the partner feed",
            "description": "",
            "source_type": "rss",
            "source": { "origin": "url", "url": "https://www.example.com/news.rss" },
            "handler": "mapping",
            "mapping": { "class": "article", "match": { "type": "remote_id", "pattern": "partner-news-{id}" }, "fields": [] },
            "target": { "parent_node_id": 2, "section_id": 0, "language": "", "owner_id": 0 },
            "behaviour": { "chunk_size": 200, "update_policy": "update" },
            "options": {}
        }
    ]
}
```

(The mapping and the behavior are shortened here.) An export holds the source, processing, mapping, target, behavior and options of
each import. It holds no runs, no schedules and no file content: **uploaded source files are not part of an
export.**

### Import import definitions

Choose an exported file (at most 10 MB) and **Import**:

- an import with the same identifier is updated, and keeps whether it is enabled;
- the other imports are created disabled;
- an entry without an identifier or a name is skipped and reported.

The page reports how many imports were created and updated. Then open each imported import in the editor: an
import that read an uploaded file needs the file uploaded again in the source step, or its runs fail with "the
uploaded source file is missing; upload it again". Add schedules in the schedule step, and enable the import in the
review step.

This is how imports move from a staging installation to the live one. The export contains the addresses and
options of the sources, so keep the file as safe as the settings of the site.
