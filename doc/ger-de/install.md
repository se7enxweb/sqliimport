# Installation

## Voraussetzungen

- Exponential 6.0.14 oder neuer.
- PHP 8.0 oder neuer, mit `json`, `mbstring`, `dom` und `xmlreader`.
- Die Erweiterung **expqueue** (die Job-Warteschlange). Ihre Worker verarbeiten jeden Lauf.
- Die Erweiterung **expui** (die gemeinsamen Komponenten der Administration).
- MySQL/MariaDB, PostgreSQL, SQLite oder Oracle.

Optional (in `composer.json` vorgeschlagen):

- `zip` (`ext-zip`) liest Tabellenquellen (XLSX und ODS). Ohne sie funktionieren alle anderen Quelltypen, und ein
  Tabellenimport schlägt mit einer Meldung fehl, die die fehlende Erweiterung nennt.
- Mit `pcntl` lässt sich ein Lauf von 1.x, den der alte Cron-Teil gestartet hat, mit SIGTERM oder SIGINT sicher
  anhalten.

## Installieren

```bash
composer require se7enxweb/sqliimport:^2.0@alpha
```

1. Aktivieren Sie die Erweiterungen in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=expqueue
   ActiveExtensions[]=expui
   ActiveExtensions[]=sqliimport
   ```

   Soll die Erweiterung nur in der Administration aktiv sein, tragen Sie sie stattdessen in
   `ActiveAccessExtensions[]` der Admin-Siteaccesses ein.

2. Erzeugen Sie die Autoloads neu:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   ```

3. Legen Sie die Tabellen der Job-Warteschlange und von 7x SQLI Import an. Beide Befehle sind idempotent; der
   zweite aktualisiert auch die Tabellen von 1.x:

   ```bash
   ./console ext:expqueue:install-schema
   ./console ext:sqliimport:install-schema
   ```

4. Lassen Sie beide Cron-Teile jede Minute laufen:

   ```bash
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q expqueue
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q sqliimport
   ```

   Der Teil `expqueue` startet die Worker, die die Läufe verarbeiten (expqueue kann auch als systemd-Dienst
   laufen). Der Teil `sqliimport` stellt die Läufe fälliger Zeitpläne und offener Importaufträge von 1.x in die
   Warteschlange, beendet Läufe, deren Jobs beendet sind, und entfernt alte Läufe.

5. Leeren Sie die Caches:

   ```bash
   php bin/php/ezcache.php --clear-tag=ini
   php bin/php/ezcache.php --clear-id=template,template-override
   ```

## Der Reiter Importe

Die Erweiterung fügt dem oberen Menü der Administration den Reiter **Importe** hinzu
(`menu.ini [TopAdminMenu] Tabs[]`).

**Wichtig:** Ein Siteaccess, der `[TopAdminMenu] Tabs[]` in seiner eigenen `menu.ini.append.php` zurücksetzt (eine
Zeile `Tabs[]` ohne Wert), muss dort selbst `Tabs[]=sqliimport` eintragen. Sonst erscheint der Reiter in diesem
Siteaccess nicht.

```ini
[TopAdminMenu]
Tabs[]
Tabs[]=content
Tabs[]=media
Tabs[]=users
Tabs[]=setup
Tabs[]=sqliimport
```

Das linke Menü des Reiters hat diese Seiten:

| Seite | Adresse | Siehe |
|---|---|---|
| Overview (die Übersicht) | `sqliimport/list` | [runs.md](runs.md#die-übersicht) |
| New import (der Import-Editor) | `sqliimport/edit/new` | [editor.md](editor.md) |
| Running runs, Failed runs | `sqliimport/list/(status)/active`, `.../failed` | [runs.md](runs.md#die-übersicht) |
| Schedules | `sqliimport/schedules` | [schedules.md](schedules.md#die-seite-schedules) |
| Job queue | `expqueue/dashboard` | das Handbuch von expqueue |
| Catalogue | `sqliimport/catalogue` | die Quelltypen, Handler und Umwandlungen der Website |
| Settings | `sqliimport/settings` | [settings.md](settings.md#die-seite-settings) |
| 1.x import requests, 1.x scheduled imports, New 1.x import request | `sqliimport/items`, `sqliimport/scheduledlist`, `sqliimport/addimport` | [migration-1x.md](migration-1x.md#nach-der-aktualisierung) |

Ein Link erscheint nur für Benutzer, die seine Seite öffnen dürfen ([permissions.md](permissions.md)). Die
Erweiterung fügt außerdem dem Dashboard der Administration den Block „Imports“ hinzu
(`dashboard.ini [DashboardBlock_sqliimport]`).

Geben Sie den Redakteuren, die mit Importen arbeiten, eine Rolle mit `sqliimport/run` oder `sqliimport/manage`
([permissions.md](permissions.md)).

## Die Installation prüfen

```bash
./console ext:sqliimport:install-schema --check   # meldet fehlende Tabellen, Spalten und Indizes
./console ext:sqliimport:list                     # die Importe
./console ext:sqliimport:tick                     # eine Runde des Cron-Teils
```

Öffnen Sie **Importe** in der Administration. Warnt die Übersicht, dass die Tabellen oder die Job-Warteschlange
fehlen, führen Sie den passenden Befehl `install-schema` aus. Legen Sie dann mit **New import** einen ersten Import
an und sehen Sie sich seine Vorschau an ([editor.md](editor.md)).

## Das Schema ausgeben oder schreiben

`install-schema` legt an, was in der aktuellen Datenbank fehlt. Es kann auch das SQL für eine Datenbank ausgeben
oder die Schemadateien der Erweiterung neu schreiben:

```bash
./console ext:sqliimport:install-schema --print=postgresql
./console ext:sqliimport:install-schema --write-files
```

## Aktualisierung von 1.x

Installieren Sie 2.x über 1.x, führen Sie `install-schema` aus und folgen Sie dann
[migration-1x.md](migration-1x.md).
