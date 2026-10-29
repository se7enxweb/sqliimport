#!/usr/bin/env php
<?php
/**
 * ./console ext:sqliimport:migrate-1x: Migrates the handlers, scheduled imports and history of sqliimport 1.x.
 * The code is in classes/exp/expimportcommand.php; run with --help for the options.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 * @description Migrates the handlers, scheduled imports and history of sqliimport 1.x
 */

$root = dirname( __DIR__, 4 );
chdir( $root );
require_once $root . '/autoload.php';
expImportCommand::main( 'migrate-1x' );
