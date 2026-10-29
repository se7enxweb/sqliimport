#!/usr/bin/env php
<?php
/**
 * ./console ext:sqliimport:install-schema: creates or upgrades the tables of 7x SQLI Import (idempotent); --check,
 * --print=<engine>, --write-files. The code is in classes/exp/expimportcommand.php; run with --help for the options.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 * @description Create or upgrade the tables of 7x SQLI Import (idempotent); --check, --print=<engine>, --write-files
 */

$root = dirname( __DIR__, 4 );
chdir( $root );
require_once $root . '/autoload.php';
expImportCommand::main( 'install-schema' );
