<?php
/**
 * sqliimport/list: the dashboard of the Imports tab. The key figures, the imports with their last run and next
 * schedule (Run now and Dry run for users who may run them), and the runs, filtered by (status) and (import).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$tpl = eZTemplate::factory();
$installed = expImportSchema::installed();
$filter = array(
    'status' => isset( $Params['Status'] ) && isset( expImportAdmin::STATUS_FILTERS[$Params['Status']] ) ? (string)$Params['Status'] : '',
    'import' => isset( $Params['Import'] ) ? (int)$Params['Import'] : 0,
);
$offset = isset( $Params['Offset'] ) ? max( 0, (int)$Params['Offset'] ) : 0;
$limit = max( 5, (int)expImport::setting( 'AdminSettings', 'ListLimit', 25 ) );

$runs = array();
$runCount = 0;
$imports = array();
$figures = array();
if ( $installed )
{
    $figures = expImportAdmin::figures();
    $imports = expImportAdmin::imports( 100 );
    list( $runList, $runCount ) = expImportAdmin::runs( $filter, $offset, $limit );
    foreach ( $runList as $run )
        $runs[] = expImportAdmin::runRow( $run );
}
$base = '/sqliimport/list' . ( $filter['import'] ? '/(import)/' . $filter['import'] : '' );
$statusLinks = array();
foreach ( array_merge( array( '' ), array_keys( expImportAdmin::STATUS_FILTERS ) ) as $status )
    $statusLinks[] = array( 'name' => $status, 'label' => $status !== '' ? expImportAdmin::label( 'status', $status ) : '', 'url' => $base . ( $status !== '' ? '/(status)/' . $status : '' ), 'current' => $status === $filter['status'] );
$here = $base . ( $filter['status'] !== '' ? '/(status)/' . $filter['status'] : '' );

$http = eZHTTPTool::instance();
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );

$tpl->setVariable( 'installed', $installed );
$tpl->setVariable( 'queue_ready', expImport::queueReady() );
$tpl->setVariable( 'figures', $figures );
$tpl->setVariable( 'imports', $imports );
$tpl->setVariable( 'runs', $runs );
$tpl->setVariable( 'run_count', $runCount );
$tpl->setVariable( 'filter', $filter );
$tpl->setVariable( 'status_links', $statusLinks );
$tpl->setVariable( 'offset', $offset );
$tpl->setVariable( 'limit', $limit );
$tpl->setVariable( 'here', $here );
$tpl->setVariable( 'prev_url', $offset > 0 ? $here . ( $offset - $limit > 0 ? '/(offset)/' . ( $offset - $limit ) : '' ) : '' );
$tpl->setVariable( 'next_url', $offset + $limit < $runCount ? $here . '/(offset)/' . ( $offset + $limit ) : '' );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'can_run', expImportAdmin::can( 'run' ) );
$tpl->setVariable( 'can_edit', expImportAdmin::can( 'edit' ) );
$tpl->setVariable( 'can_legacy', expImportAdmin::can( 'manageimports' ) && (bool)expImportLegacyHandlerAdapter::available() );
$tpl->setVariable( 'now', expImport::now() );
$tpl->setVariable( 'stats', $installed ? expImportStatistics::overview( 30 ) : array() );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/list.tpl' );
$Result['path'] = array( array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ) );
