# SQLI Import 1.x: handlers, the content API and the source helpers

This is the manual of sqliimport 1.3, kept because everything it describes still works in 7x SQLI Import 2.x: the
handler framework (ISQLIImportHandler, SQLIImportAbstractHandler), the content API (SQLIContent,
SQLIContentPublisher, SQLILocation), the XML and CSV helpers and the diff handlers. What is new in 2.x is in
[index.md](index.md).

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-8892BF.png?logo=php&logoColor=white)](https://php.net)
[![Exponential](https://img.shields.io/badge/Exponential-6.x-orange.png)](https://github.com/se7enxweb/exponential)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.png)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

**SQLI Import** imports external content into Exponential. It gives you three things:

1. **A framework for import handlers.** You write one small PHP class that reads your source (an XML feed, a CSV
   file, a web service, another database) and maps each row to Exponential content. SQLI Import runs it, tracks its
   progress, logs it and lets it be interrupted safely.
2. **A simplified content API** (`SQLIContent`, `SQLIContentPublisher`, `SQLILocation`) to create, update, translate
   and place content objects in a few lines, publishing a new version only when something really changed.
3. **An admin interface**, the **Import management** tab, to request imports, schedule recurring ones, follow their
   progress and cancel or interrupt them, with policies that can be limited per handler.

It is for developers and site builders who need to bring content in from elsewhere, once (a migration) or
regularly (a feed, a nightly export from another system).

## Table of contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage in the administration](#usage-in-the-administration)
5. [Command line and cronjobs](#command-line-and-cronjobs)
6. [Writing an import handler](#writing-an-import-handler)
7. [The content API](#the-content-api)
8. [Source helpers: XML and CSV](#source-helpers-xml-and-csv)
9. [Diff handlers](#diff-handlers)
10. [Permissions](#permissions)
11. [Upgrading](#upgrading)
12. [Known limits](#known-limits)
13. [Troubleshooting](#troubleshooting)
14. [Contributing](#contributing)
15. [License](#license)
16. [Credits and history](#credits-and-history)

## Requirements

- Exponential 6.x.
- PHP 8.0 or later. Optional: `pcntl` (safe interruption with signals, not on Windows), `simplexml` and `dom` (XML
  sources).
- MySQL/MariaDB, PostgreSQL or Oracle for the two tables of the extension (SQL files are included for each).
- The ability to run a cronjob every 5 minutes (imports requested in the administration are run by the cronjob).

## Installation

### With Composer

From the root of your Exponential installation:

```bash
composer require se7enxweb/sqliimport
```

The legacy installer places it in `extension/sqliimport`. Continue with step 2 below.

### By hand

1. Copy the extension to `extension/sqliimport`, for example:

   ```bash
   cd /path/to/exponential
   git clone https://github.com/se7enxweb/sqliimport.git extension/sqliimport
   ```

2. Activate it in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=sqliimport
   ```

3. Create its tables (`sqliimport_item` and `sqliimport_scheduled`) with the file for your database:

   ```bash
   mysql -u <user> -p <database> < extension/sqliimport/sql/mysql/schema.sql
   psql -U <user> -d <database> -f extension/sqliimport/sql/postgesql/schema.sql   # PostgreSQL
   sqlplus <user>/<password>@<database> @extension/sqliimport/sql/oracle/schema.sql  # Oracle
   ```

   The same schema is also in `share/db_schema.dba`, the kernel's database independent format.

4. Regenerate the autoloads and clear the caches:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-tag=ini
   php bin/php/ezcache.php --clear-all
   ```

5. Schedule the import cronjob every 5 minutes (not more often; less often is fine):

   ```cron
   */5 * * * * cd /path/to/exponential && php runcronjobs.php sqliimport_run 2>&1
   ```

6. Write and declare your import handler (see [Writing an import handler](#writing-an-import-handler)), or try the
   bundled RSS example first.

## Configuration

### `sqliimport.ini`

Do not edit `extension/sqliimport/settings/sqliimport.ini`; put your changes in
`settings/override/sqliimport.ini.append.php`.

```ini
[ImportSettings]
# Fallback parent node for imported content, when a handler sets none
DefaultParentNodeID=2
# View caching and search indexing are switched off for the import script only;
# the sqliimport_cleanup cronjob clears the view cache and the indexcontent
# cronjob indexes the imported objects afterwards. Imports are much faster so.
ViewCaching=disabled
ObjectIndexing=disabled
# The handlers that can be run; each has its own [<identifier>-HandlerSettings] group
AvailableSourceHandlers[]
AvailableSourceHandlers[]=myhandler
# User the imports run as; empty = site.ini [UserSettings] AnonymousUserID
RobotUserID=
# Seconds to wait for a remote stream (XML feed, remote file)
StreamTimeout=50

[myhandler-HandlerSettings]
# Mandatory: true or false
Enabled=true
# The name shown in the administration
Name=My Handler
# Your class, extending SQLIImportAbstractHandler and implementing ISQLIImportHandler
ClassName=MyImportHandler
# Optional
Debug=enabled
# Handler specific; overrides [ImportSettings] DefaultParentNodeID
DefaultParentNodeID=2
# Handler specific; empty = [ImportSettings] StreamTimeout
StreamTimeout=
# Any setting of your own, read in the handler as $this->handlerConfArray['MySetting']
MySetting=some value

[ContentSettings]
# Diff handlers decide whether a field really changed (see "Diff handlers")
DiffHandlerMap[ezimage]=SQLIImageDiffHandler
DiffHandlerMap[ezbinaryfile]=SQLIBinaryFileDiffHandler
DefaultDiffHandler=SQLIDefaultDiffHandler
# Datatypes whose fields are never compared for modification
ContentModificationSkipDatatypes[]
```

The extension's own `sqliimport.ini.append.php` declares the example `[rssimporthandler-HandlerSettings]`
(`ClassName=SQLIRSSImportHandler`, `DefaultParentNodeID=43`, `RSSFeed=<feed URL>`). It is not listed in
`AvailableSourceHandlers[]`; add `AvailableSourceHandlers[]=rssimporthandler` and your own `RSSFeed=` to try it.

### Other settings the extension adds

| File | Setting | Purpose |
|---|---|---|
| `menu.ini` | `[TopAdminMenu] Tabs[]=sqliimport`, `[Topmenu_sqliimport]` | The **Import management** tab, with `Icon=upload`, opening `sqliimport/list`, shown to users with `sqliimport/manageimports` |
| `cronjob.ini` | `[CronjobPart-sqliimport_run]` | `sqliimport_run.php`, `sqliimport_cleanup.php`, `indexcontent.php` |
| `cronjob.ini` | `[CronjobPart-sqliimport_cleanup]` | `sqliimport_cleanup.php`, `indexcontent.php` |
| `module.ini` | `ModuleList[]=sqliimport` | The `sqliimport` module |
| `design.ini` | `DesignExtensions[]=sqliimport` | The admin templates, scripts and stylesheets |
| `site.ini` | `TranslationExtensions[]=sqliimport` | Translations (Spanish and French are included) |

## Usage in the administration

Open the **Import management** tab (the upload icon) in the admin header. The list (`sqliimport/list`) shows every
import with its handler, who requested it, its status, progress, duration and notes.

There are two kinds of imports. Both are stored as pending and run by the next `sqliimport_run` cronjob.

- **Immediate imports** run once. Left menu, **Request a new immediate import** (`sqliimport/addimport`): choose the
  handler, optionally add options, submit.
- **Scheduled imports** start at a chosen date and time and can repeat: none (once), hourly, daily, weekly, monthly
  or every N minutes (not less than 5). Left menu, **Scheduled import(s)** (`sqliimport/scheduledlist`), then **Add a
  scheduled import** (`sqliimport/addscheduled`). A scheduled import has a label and can be deactivated; edit or
  remove it from the same list.

**Options.** If your handler reads runtime options, enter one per line as `optionName=optionValue`:

```text
file=var/import/products.csv
dryrun=1
```

They reach the handler's constructor as a `SQLIImportHandlerOptions` object (`$this->options->file`).

**Cancel and interrupt.** In the list, **Cancel** marks a pending import as canceled so the cronjob skips it, and
**Interrupt** asks a running import to stop after its current row (`sqliimport/alterimport/cancel/<id>` and
`sqliimport/alterimport/interrupt/<id>`). **Purge import history** (`sqliimport/purgelist`) deletes the finished, failed,
canceled and interrupted imports from the list.

## Command line and cronjobs

### `sqliimport_run` (cronjob part)

Runs pending immediate imports and due scheduled imports, then clears the view cache of imported objects and indexes
them (`sqliimport_cleanup.php`, `indexcontent.php`):

```bash
php runcronjobs.php sqliimport_run
```

### `sqliimport_cleanup` (cronjob part)

Only the clean-up and indexing part, for example after an import run from the command line:

```bash
php runcronjobs.php sqliimport_cleanup
```

### `bin/php/sqlidoimport.php`

Runs handlers directly, without going through the administration:

```bash
# list the available handlers
php extension/sqliimport/bin/php/sqlidoimport.php --list-source-handlers

# run one handler (all handlers when --source-handlers is left out)
php extension/sqliimport/bin/php/sqlidoimport.php --source-handlers=rssimporthandler

# with options, per handler
php extension/sqliimport/bin/php/sqlidoimport.php --source-handlers=myhandler \
    --options="myhandler::file=var/import/products.csv,dryrun=1"
```

`--options` takes `handler1::foo=bar,foo2=baz|handler2::someoption=biz`.

### Interrupting a running import

With the `pcntl` extension, SQLI Import catches SIGTERM and SIGINT and stops cleanly after the current row:

```bash
kill -15 <pid>   # SIGTERM
kill -2 <pid>    # SIGINT, the same as Ctrl+C
```

`kill -9` (SIGKILL) cannot be caught and leaves the import marked as running; prefer SIGTERM.

## Writing an import handler

A handler is a class that extends `SQLIImportAbstractHandler` and implements `ISQLIImportHandler`. SQLI Import
calls its methods in this order:

| Method | Purpose |
|---|---|
| `__construct( ?SQLIImportHandlerOptions $options = null )` | Call `parent::__construct( $options )` |
| `initialize()` | Open and read your source into `$this->dataSource` |
| `getProcessLength()` | The number of rows, for the progress bar |
| `getHandlerName()`, `getHandlerIdentifier()` | The name shown, and the identifier used in `sqliimport.ini` |
| `getNextRow()` | The next row, or `false` when done |
| `process( $row )` | Import one row (called for each row `getNextRow()` returns) |
| `getProgressionNotes()` | A note shown in the list after each row |
| `cleanup()` | Called once at the end |

Your handler's INI group is available as `$this->handlerConfArray`, the runtime options as `$this->options`.

A complete example is the bundled `classes/sourcehandlers/sqlirssimporthandler.php`, which imports the items of an
RSS feed as `article` objects. Shortened:

```php
<?php
class SQLIRSSImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    protected $rowIndex = 0;
    protected $rowCount;
    protected $currentGUID;

    public function initialize()
    {
        $xmlOptions = new SQLIXMLOptions( array(
            'xml_path'   => $this->handlerConfArray['RSSFeed'],
            'xml_parser' => 'simplexml'
        ) );
        $xmlParser = new SQLIXMLParser( $xmlOptions );
        $this->dataSource = $xmlParser->parse();
    }

    public function getProcessLength()
    {
        if ( !isset( $this->rowCount ) )
            $this->rowCount = count( $this->dataSource->channel->item );
        return $this->rowCount;
    }

    public function getNextRow()
    {
        if ( $this->rowIndex < $this->rowCount )
            return $this->dataSource->channel->item[$this->rowIndex++];
        return false;
    }

    public function process( $row )
    {
        $this->currentGUID = $row->guid;
        $content = SQLIContent::create( new SQLIContentOptions( array(
            'class_identifier' => 'article',
            'remote_id'        => (string)$row->guid
        ) ) );
        $content->fields->title = (string)$row->title;
        $content->fields->intro = $this->getRichContent( (string)$row->description );
        $content->addLocation( SQLILocation::fromNodeID( $this->handlerConfArray['DefaultParentNodeID'] ) );
        SQLIContentPublisher::getInstance()->publish( $content );
        unset( $content ); // frees the object's caches
    }

    public function cleanup() {}
    public function getHandlerName() { return 'RSS Import Handler'; }
    public function getHandlerIdentifier() { return 'rssimporthandler'; }
    public function getProgressionNotes() { return 'Currently importing : ' . $this->currentGUID; }
}
```

Then declare it (`AvailableSourceHandlers[]` and its `-HandlerSettings` group), regenerate the autoloads
(`php bin/php/ezpgenerateautoloads.php -e`) and request an import. Because the remote ID is the feed item's GUID, a
second run updates the same objects instead of creating new ones.

More examples are in `stubs/`: `contentcreate.php`, `contentremove.php`, `content.php`, `xml.php`, `csv.php` (with
`example.csv`), and `scriptinit.php` to run them.

## The content API

```php
<?php
$options = new SQLIContentOptions( array(
    'class_identifier' => 'comment',            // mandatory
    'remote_id'        => 'my_remote_id',       // find and update the object later
    'language'         => 'eng-US'              // default: the default language
) );
$comment = SQLIContent::create( $options );
$comment->fields->subject = 'My great subject';
$comment->fields->author  = 'Batman';
$comment->fields->message = 'Some text';

$comment->addTranslation( 'ger-DE' );
$comment->fields['ger-DE']->subject = 'Mein Betreff';

$comment->addLocation( SQLILocation::fromNodeID( 2 ) );
$comment->addLocation( SQLILocation::fromNodeID( 43 ) );

$publisher = SQLIContentPublisher::getInstance();
$publisher->publish( $comment );

foreach ( $comment->locations as $nodeID => $location )
    echo $nodeID . ' => ' . $location->path_identification_string . "\n";

unset( $comment ); // clears the object's caches and data map
```

- Fields are set from strings through each datatype's `fromString()` (see `doc/fromString.txt` for the format of
  every kernel datatype); custom datatypes without `fromString()`/`toString()` fall back to `data_text`.
- HTML for an XML text field: `$this->getRichContent( $html )` in a handler, `SQLIContentUtils::getRichContent( $html )`
  elsewhere.
- `SQLIContentOptions`: `class_identifier` (mandatory), `remote_id`, `section_id` (0 = inherited), `creator_id`
  (false = current user), `language`.
- `SQLIContentPublishOptions` (`$publisher->setOptions()`): `modification_check` (default true: publish only when a
  field changed), `update_null_field` (default false), `parent_node_id` (deprecated; use locations).
- `$content->defaultLocation` is the main node's location.

## Source helpers: XML and CSV

- **`SQLIXMLParser`** with `SQLIXMLOptions` (`xml_path` a file or URL, or `xml_string`; `xml_parser` `dom`, the
  default, or `simplexml`) turns parse errors into exceptions. Example: `stubs/xml.php`.
- **`SQLICSVDoc`** with `SQLICSVOptions` reads a CSV file into rows you can walk like SimpleXML, with headers turned
  into property names. Example: `stubs/csv.php` and `stubs/example.csv`.
- `SQLIContentUtils::getRemoteFile( $url, ?array $httpAuth = null, $debug = false, $allowProxyUse = true )` downloads a
  remote file (images, binaries) for import.

## Diff handlers

When an object is updated, `SQLIContentPublisher` compares each field with the stored one and publishes a new version
only if something changed. `SQLIDefaultDiffHandler` compares the string representations; `SQLIImageDiffHandler` and
`SQLIBinaryFileDiffHandler` compare files. For your own datatype, write a class implementing `ISQLIDiffHandler`
(one static method, `contentIsModified()`) and map it in `[ContentSettings] DiffHandlerMap[<datatype>]`.

## Permissions

The `sqliimport` module has two policy functions:

| Function | Allows | Limitation |
|---|---|---|
| `listimports` | `sqliimport/list`, `sqliimport/scheduledlist` | none |
| `manageimports` | requesting, scheduling, editing, removing, cancelling and interrupting imports, purging the history; the tab | `SQLIImport_Type`: only the chosen handlers |

Give `manageimports` with a `SQLIImport_Type` limitation to let a team run only its own imports. Administrators with
access to every module have both.

## Upgrading

- **To 1.3.1 (this release):** no database change. The code now runs on PHP 8. The Composer package is
  `se7enxweb/sqliimport` (it replaces `lolautruche/sqliimport`): `composer remove lolautruche/sqliimport` and
  `composer require se7enxweb/sqliimport`. Handlers you wrote keep working; on PHP 8.4 or later declare their
  constructor's parameter as `?SQLIImportHandlerOptions $options = null` to avoid a deprecation notice.
- **From 1.1.x:** run `update/sql/mysql/1.1-to-1.2.sql`.
- **From 1.0.x:** run `update/sql/mysql/1.0-to-1.1.sql`, then the 1.1 to 1.2 script.
- Then regenerate the autoloads and clear the caches.

The full history is in [doc/CHANGELOG](doc/CHANGELOG).

## Known limits

- Imports requested in the administration wait for the next `sqliimport_run` cronjob; nothing runs from the web
  request itself.
- Signal handling needs `pcntl`, which is not available on Windows.
- Update scripts are provided for MySQL only.
- The admin templates use the classic admin markup; the admin4 design draws the tab icon but the pages keep their
  original look.

## Troubleshooting

- **No Import management tab.** Check `ActiveExtensions[]`, that your role has `sqliimport/manageimports`, and
  clear the INI cache.
- **Imports stay pending.** The cronjob is not running: check your crontab, or run
  `php runcronjobs.php sqliimport_run` by hand and read its output.
- **An import is stuck as running** after the process was killed with `kill -9` or crashed: interrupt it from the
  list; next time stop imports with SIGTERM.
- **A handler is not offered.** It must be in `AvailableSourceHandlers[]`, have `Enabled=true`, and its class must be
  in the autoloads (`php bin/php/ezpgenerateautoloads.php -e`).
- **Imported content does not show up in search** until the `indexcontent` cronjob ran (it is part of
  `sqliimport_run` and `sqliimport_cleanup`).
- **Errors.** SQLI Import writes `sqliimport-error.log`, `sqliimport-warning.log` and `sqliimport-notice.log` in the site's log directory (`var/log` by default), and more to the debug output when the handler has `Debug=enabled`.

## Contributing

Issues and pull requests are welcome at <https://github.com/se7enxweb/sqliimport>. Please check your changes with
`php -l` on PHP 8 and describe how you tested an import.

## License

GNU General Public License, version 2. See [doc/LICENSE](doc/LICENSE).

## Credits and history

- Written by Jérôme Vieilledent and sponsored by [SQLi](https://www.sqli.com/) for eZ Publish, whose original creator
  discontinued it after about 18 years. Oracle support by Simone Tellini; contributions by Jérôme Gamez, Opencontent
  (PostgreSQL schema) and others.
- Maintained for Exponential by [7x](https://se7enx.com) since 2024.
- The original documentation, with screenshots of the admin pages and UML diagrams of the handlers, the content API
  and the CSV classes, is kept in [doc/README.txt](doc/README.txt), `doc/SQLIImport doc.pdf`, `doc/images/` and
  `doc/uml/`.
