# Installation

Diese Seite installiert 7x SQLI Import 2.0 in einer Exponential-Installation, prüft, ob es funktioniert, und sagt,
was nach einem Deployment zu tun ist. Ein erster Import, Schritt für Schritt, folgt in
[getting-started.md](getting-started.md).

English: [../install.md](../install.md).

## Inhalt

- [Voraussetzungen](#voraussetzungen)
- [Installieren](#installieren)
- [Der Reiter Importe](#der-reiter-importe)
- [Die Installation prüfen](#die-installation-prüfen)
- [Nach einem Deployment: PHP-FPM und Velocity](#nach-einem-deployment-php-fpm-und-velocity)
- [Das Schema ausgeben oder schreiben](#das-schema-ausgeben-oder-schreiben)
- [Aktualisierung von 1.x](#aktualisierung-von-1x)

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
   `ActiveAccessExtensions[]` der Admin-Siteaccesses ein, zum Beispiel:

   ```ini
   # settings/siteaccess/admin/site.ini.append.php
   [ExtensionSettings]
   ActiveAccessExtensions[]=expqueue
   ActiveAccessExtensions[]=expui
   ActiveAccessExtensions[]=sqliimport
   ```

   So arbeitet die Testinstallation dieses Handbuchs: Imports, seine Seiten und seine Webhook-Adresse gibt es nur
   unter `/admin`. Ein Cron-Teil läuft mit den Einstellungen des Siteaccess, für den er gestartet wird; ist die
   Erweiterung nicht überall aktiv, starten Sie die Cron-Teile daher mit `-s admin` für einen Admin-Siteaccess.

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

4. Lassen Sie den Cron-Teil der Job-Warteschlange jede Minute laufen:

   ```bash
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q expqueue
   ```

   Der Teil `expqueue` startet die Worker, die die Läufe verarbeiten (expqueue kann auch als systemd-Dienst
   laufen), und führt auch den **Tick** von 7x SQLI Import aus: Er stellt die Läufe fälliger Zeitpläne und offener
   Importaufträge von 1.x in die Warteschlange, beendet Läufe, deren Jobs beendet sind, und entfernt alte Läufe. Der
   eigene Teil der Erweiterung macht nur den Tick; fügen Sie ihn hinzu, wenn die Worker der Job-Warteschlange als
   Dienst statt per Cron laufen:

   ```bash
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q sqliimport
   ```

   Beide dürfen laufen: Eine Sperre verhindert, dass sich zwei Ticks überschneiden. Die Testinstallation dieses
   Handbuchs hat eine einzige Zeile, für ihren Admin-Siteaccess:

   ```bash
   * * * * * cd /pfad/zu/exponential && php runcronjobs.php -q -s admin expqueue >> var/log/cronjob-expqueue.log 2>&1
   ```

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
| Statistics | `sqliimport/statistics` | [statistics.md](statistics.md) |
| New import (der Import-Editor) | `sqliimport/edit/new` | [editor.md](editor.md) |
| Running runs, Failed runs | `sqliimport/list/(status)/active`, `.../failed` | [runs.md](runs.md#die-übersicht) |
| Schedules | `sqliimport/schedules` | [schedules.md](schedules.md#die-seite-schedules) |
| Job queue | `expqueue/dashboard` | das Handbuch von expqueue |
| Catalogue | `sqliimport/catalogue` | die Quelltypen, Handler und Umwandlungen der Website |
| Settings | `sqliimport/settings` | [settings.md](settings.md#die-seite-settings) |

Ein Link erscheint nur für Benutzer, die seine Seite öffnen dürfen ([permissions.md](permissions.md)). Die
Erweiterung fügt außerdem dem Dashboard der Administration den Block „Imports“ hinzu
(`dashboard.ini [DashboardBlock_sqliimport]`).

Geben Sie den Redakteuren, die mit Importen arbeiten, eine Rolle mit `sqliimport/run` oder `sqliimport/manage`
([permissions.md](permissions.md)).

## Die Installation prüfen

Drei Befehle sagen, ob alles an seinem Platz ist. Die ersten beiden lesen nur; die Ausgabe unten stammt von der
Testinstallation dieses Handbuchs.

```bash
./console ext:sqliimport:install-schema --check
```

```text
database engine: sqlite
PASS the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_notice, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled, sqliimport_trigger, sqliimport_trigger_call, sqliimport_trigger_key exist with every column and index
```

Eine fehlende Tabelle, Spalte oder ein fehlender Index steht in einer eigenen Zeile; führen Sie dann
`./console ext:sqliimport:install-schema` ohne `--check` aus, das anlegt, was fehlt, und sonst nichts.

```bash
./console ext:sqliimport:list
```

```text
   3  demo-releases-json             json   mapping              last run #6 completed
  76  demo_books_datatable           csv    datatable            last run #119 completed
   4  demo-feeds-opml                opml   mapping              last run #7 completed
   2  demo-php-news-atom             rss    mapping              last run #258 completed
   1  demo-products-csv              csv    mapping              last run #4 completed
 132  docs-first-import              csv    mapping              last run #215 completed
  71  sa1a                           webpage mapping              never run (disabled)
```

(gekürzt: Die Testinstallation hat 55 Importe). Jede Zeile nennt ID, Bezeichner, Quelltyp, Handler und letzten Lauf
des Imports. Nach einer frischen Installation ist die Liste leer, außer es sind Handler von 1.x eingerichtet oder Sie
haben von 1.x migriert.

```bash
./console ext:sqliimport:tick
```

führt eine Runde des Ticks von Hand aus und gibt aus, was sie getan hat (sie reiht fällige Läufe ein, liest also
nicht nur).

Öffnen Sie dann **Importe** in der Administration. Warnt die Übersicht, dass die Tabellen oder die
Job-Warteschlange fehlen, führen Sie den passenden Befehl `install-schema` aus. Die Seite **Settings** zeigt die
Einstellungen, wie sie gelten, und jede Karte des **Catalogue** sagt, welche Treiber dieser Server für ihr Format hat:

![Die Seite Settings: die Einstellungen der sqliimport.ini, wie sie gelten, etwa [RunSettings] ChunkSize 200, DryRunRows 20, KeepDays 90, [SourceSettings] MaxSourceMegabytes 200 und die Einstellungen für Auslöser und SQL](../images/inst-settings-top.png)

![Die Katalogkarte Apache Avro file mit geöffnetem „What this server can connect with“: die Codecs null, deflate und bzip2 verfügbar, snappy und zstandard mit „no driver“ und der PECL-Erweiterung, die jeder braucht](../images/inst-catalogue-drivers.png)

Eine Karte mit **no driver** nennt die PHP-Erweiterung, die der Host braucht; jedes andere Format funktioniert ohne
sie. Legen Sie dann einen ersten Import an ([getting-started.md](getting-started.md)).

## Nach einem Deployment: PHP-FPM und Velocity

Ein Webserver, der PHP-Prozesse am Leben hält, behält den alten Code, bis seine Prozesse erneuert werden. Nach dem
Installieren oder Aktualisieren der Erweiterung:

1. Erzeugen Sie die Autoloads neu und leeren Sie den INI-Cache (Schritte 2 und 5 oben).
2. Laden Sie PHP-FPM neu, damit seine Worker die neuen Klassen laden.
3. Starten Sie Exponential Velocity neu (`./console exp:velocity restart`): Seine Worker erben die Klassen seines
   Aufwärmens und sehen eine neue Klasse erst nach einem Neustart. Wo die Installation es hat, erledigt
   `./console exp:velocity deploy` alle diese Schritte in der richtigen Reihenfolge.

Ein Zeichen für einen fehlenden Neustart ist `unknown handler '...'` oder „Class ... not found“ auf dem einen Server
und nicht auf dem anderen. Einzelheiten:
[troubleshooting.md](troubleshooting.md#velocity-oder-php-fpm-führen-noch-den-alten-code-aus).

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
