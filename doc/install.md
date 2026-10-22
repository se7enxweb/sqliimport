# Installation

## Requirements

- Exponential 6.0.14 or later.
- PHP 8.0 or later, with `json`, `mbstring`, `dom` and `xmlreader`.
- The extension **expqueue** (the job queue). Every run is processed by its workers.
- The extension **expui** (the shared components of the administration).
- MySQL/MariaDB, PostgreSQL, SQLite or Oracle.

Optional (suggested in `composer.json`):

- `zip` (`ext-zip`) reads spreadsheet sources (XLSX and ODS). Without it, every other source type works, and a
  spreadsheet import fails with a message that names the missing extension.
- `pcntl` lets a 1.x run started by the old cron part be stopped safely with SIGTERM or SIGINT.

## Install

```bash
composer require se7enxweb/sqliimport:^2.0@alpha
```

1. Activate the extensions in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=expqueue
   ActiveExtensions[]=expui
   ActiveExtensions[]=sqliimport
   ```

   To use the extension only in the administration, list it in `ActiveAccessExtensions[]` of the admin
   siteaccesses instead.

2. Regenerate the autoloads:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   ```

3. Create the tables of the job queue and of 7x SQLI Import. Both commands are idempotent; the second one also
   upgrades the tables of 1.x:

   ```bash
   ./console ext:expqueue:install-schema
   ./console ext:sqliimport:install-schema
   ```

4. Run both cron parts every minute:

   ```bash
   * * * * * cd /path/to/exponential && php runcronjobs.php -q expqueue
   * * * * * cd /path/to/exponential && php runcronjobs.php -q sqliimport
   ```

   The part `expqueue` runs the workers that process the runs (expqueue can also run as a systemd service). The part
   `sqliimport` queues the runs of due schedules and of pending 1.x import requests, ends runs whose jobs ended,
   and removes old runs.

5. Clear the caches:

   ```bash
   php bin/php/ezcache.php --clear-tag=ini
   php bin/php/ezcache.php --clear-id=template,template-override
   ```

## The Imports tab

The extension adds the tab **Imports** to the top menu of the administration (`menu.ini [TopAdminMenu] Tabs[]`).

**Important:** a siteaccess that resets `[TopAdminMenu] Tabs[]` in its own `menu.ini.append.php` (a line `Tabs[]`
without a value) must list `Tabs[]=sqliimport` there itself. Otherwise the tab does not show in that siteaccess.

```ini
[TopAdminMenu]
Tabs[]
Tabs[]=content
Tabs[]=media
Tabs[]=users
Tabs[]=setup
Tabs[]=sqliimport
```

The left menu of the tab has these pages:

| Page | Address | See |
|---|---|---|
| Overview (the dashboard) | `sqliimport/list` | [runs.md](runs.md#the-dashboard) |
| New import (the import editor) | `sqliimport/edit/new` | [editor.md](editor.md) |
| Running runs, Failed runs | `sqliimport/list/(status)/active`, `.../failed` | [runs.md](runs.md#the-dashboard) |
| Schedules | `sqliimport/schedules` | [schedules.md](schedules.md#the-schedules-page) |
| Job queue | `expqueue/dashboard` | the manual of expqueue |
| Catalogue | `sqliimport/catalogue` | the source types, handlers and transforms of the site |
| Settings | `sqliimport/settings` | [settings.md](settings.md#the-settings-page) |
| 1.x import requests, 1.x scheduled imports, New 1.x import request | `sqliimport/items`, `sqliimport/scheduledlist`, `sqliimport/addimport` | [migration-1x.md](migration-1x.md#after-the-upgrade) |

A link is shown only to users who may open its page ([permissions.md](permissions.md)). The extension also adds the
block "Imports" to the admin dashboard (`dashboard.ini [DashboardBlock_sqliimport]`).

Give the editors who work with imports a role with `sqliimport/run` or `sqliimport/manage`
([permissions.md](permissions.md)).

## Check the installation

```bash
./console ext:sqliimport:install-schema --check   # reports missing tables, columns and indexes
./console ext:sqliimport:list                     # the imports
./console ext:sqliimport:tick                     # one round of the cron part
```

Open **Imports** in the administration. If the dashboard warns that the tables or the job queue are missing, run
the matching `install-schema` command. Then create a first import with **New import** and look at its preview
([editor.md](editor.md)).

## Print or write the schema

`install-schema` creates what is missing in the current database. It can also print the SQL for one engine or
rewrite the schema files of the extension:

```bash
./console ext:sqliimport:install-schema --print=postgresql
./console ext:sqliimport:install-schema --write-files
```

## Upgrading from 1.x

Install 2.x over 1.x, run `install-schema`, then follow [migration-1x.md](migration-1x.md).
