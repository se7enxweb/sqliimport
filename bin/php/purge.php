#!/usr/bin/env php
<?php
/**
 * ./console ext:sqliimport:purge: Removes ended runs older than KeepDays.
 * The code is in classes/exp/expimportcommand.php; run with --help for the options.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 * @description Removes ended runs older than KeepDays
 */

$root = dirname( __DIR__, 4 );
chdir( $root );
require_once $root . '/autoload.php';
expImportCommand::main( 'purge' );
