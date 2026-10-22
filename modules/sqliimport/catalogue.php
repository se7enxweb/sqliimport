<?php
/**
 * sqliimport/catalogue: what this site can import from and with. The formats by group (description, extensions,
 * examples to download, the mapping they come with, New import), the handlers (1.x ones marked legacy) and the
 * transforms. sqliimport/catalogue/(example)/<format>/(n)/<n> downloads an example file of a format.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$registry = expImportRegistry::instance();
$sources = $registry->sources();

if ( isset( $Params['Example'] ) && $Params['Example'] !== false && $Params['Example'] !== '' )
{
    $id = (string)$Params['Example'];
    $n = isset( $Params['N'] ) ? (int)$Params['N'] : 0;
    $example = isset( $sources[$id]['examples'][$n]['file'] ) ? basename( (string)$sources[$id]['examples'][$n]['file'] ) : '';
    $path = $example !== '' ? expImport::exampleDirectory() . '/' . $example : '';
    if ( $path === '' || !is_file( $path ) )
        return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
    $types = array( 'csv' => 'text/csv', 'tsv' => 'text/tab-separated-values', 'json' => 'application/json', 'ndjson' => 'application/x-ndjson',
                    'xml' => 'application/xml', 'rss' => 'application/rss+xml', 'atom' => 'application/atom+xml', 'opml' => 'text/x-opml',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'sqlite' => 'application/vnd.sqlite3' );
    $extension = strtolower( pathinfo( $example, PATHINFO_EXTENSION ) );
    while ( ob_get_level() > 0 )
        ob_end_clean();
    header( 'Content-Type: ' . ( isset( $types[$extension] ) ? $types[$extension] : 'application/octet-stream' ) );
    header( 'Content-Disposition: attachment; filename="' . $example . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $path );
    eZExecution::cleanExit();
}

$registry->registerTransforms();
$tpl = eZTemplate::factory();
$tpl->setVariable( 'sources', $sources );
$tpl->setVariable( 'sources_by_group', $registry->sourcesByGroup() );
$tpl->setVariable( 'handlers', $registry->handlers() );
$tpl->setVariable( 'transforms', expImportTransforms::catalogue() );
$tpl->setVariable( 'can_manage', expImportAdmin::can( 'edit' ) );
// the databases the SQL source can read here and in the job queue's workers
$tpl->setVariable( 'sql_drivers', isset( $sources['sql'] ) ? expImportSqlSource::drivers() : array() );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/catalogue.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Catalogue' ) ) );
