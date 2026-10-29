#!/usr/bin/env php
<?php
/**
 * ./console ext:sqliimport:opml-feeds: Creates one RSS import per feed of an OPML list.
 * The code is in classes/exp/expimportcommand.php; run with --help for the options.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 * @description Creates one RSS import per feed of an OPML list
 */

$root = dirname( __DIR__, 4 );
chdir( $root );
require_once $root . '/autoload.php';
expImportCommand::main( 'opml-feeds' );
