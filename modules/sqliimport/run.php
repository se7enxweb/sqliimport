<?php
/**
 * sqliimport/run/<id>: one run. Its status, progress and counters, the records by outcome (filtered by (outcome)),
 * the log, and Cancel or Retry failed rows for users who may run the import.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$run = expImportSchema::installed() ? expImportRun::fetch( (int)$Params['RunID'] ) : null;
if ( !$run )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
$import = $run->import();
$outcomes = array( 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed', 'undone' );
$outcome = isset( $Params['Outcome'] ) && in_array( $Params['Outcome'], $outcomes, true ) ? (string)$Params['Outcome'] : '';
$offset = isset( $Params['Offset'] ) ? max( 0, (int)$Params['Offset'] ) : 0;
$limit = max( 5, (int)expImport::setting( 'AdminSettings', 'ListLimit', 25 ) );
$conds = $outcome !== '' ? array( 'outcome' => $outcome ) : array();
$rows = array();
foreach ( expImportRunRow::fetchByRun( $run->attribute( 'id' ), $conds, $offset, $limit ) as $row )
{
    $nodes = expImport::decode( $row->attribute( 'nodes_json' ) );
    $rows[] = array(
        'ref' => $row->attribute( 'row_ref' ),
        'outcome' => $row->attribute( 'outcome' ),
        'outcome_label' => expImportAdmin::label( 'outcome', $row->attribute( 'outcome' ) ),
        'object_id' => (int)$row->attribute( 'object_id' ),
        'remote_id' => $row->attribute( 'remote_id' ),
        'version_before' => (int)$row->attribute( 'version_before' ),
        'version_after' => (int)$row->attribute( 'version_after' ),
        'message' => $row->attribute( 'message' ),
        'diff' => isset( $nodes['diff'] ) ? $nodes['diff'] : array(),
    );
}
$rowCount = expImportRunRow::countByRun( $run->attribute( 'id' ), $conds );
$log = array();
$logIDs = array();
foreach ( expImportRunLog::fetchByRun( $run->attribute( 'id' ), 0, array(), 0, 500 ) as $line )
{
    $logIDs[] = (int)$line->attribute( 'id' );
    $log[] = array( 'time' => (int)$line->attribute( 'created' ), 'severity' => $line->attribute( 'severity' ), 'ref' => $line->attribute( 'row_ref' ),
                    'message' => $line->attribute( 'message' ) );
}

$base = '/sqliimport/run/' . (int)$run->attribute( 'id' );
$outcomeLinks = array( array( 'name' => '', 'url' => $base, 'current' => $outcome === '' ) );
foreach ( $outcomes as $o )
    $outcomeLinks[] = array( 'name' => $o, 'label' => expImportAdmin::label( 'outcome', $o ), 'url' => $base . '/(outcome)/' . $o, 'current' => $o === $outcome );
$here = $base . ( $outcome !== '' ? '/(outcome)/' . $outcome : '' );

$http = eZHTTPTool::instance();
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'run', expImportAdmin::runRow( $run ) );
$tpl->setVariable( 'import', $import );
$tpl->setVariable( 'rows', $rows );
$tpl->setVariable( 'row_count', $rowCount );
$tpl->setVariable( 'report_url', '/sqliimport/report/' . (int)$run->attribute( 'id' ) . ( $outcome !== '' ? '/(outcome)/' . $outcome : '' ) );
$tpl->setVariable( 'outcome_links', $outcomeLinks );
$tpl->setVariable( 'log', $log );
$tpl->setVariable( 'last_log_id', $log ? (int)end( $logIDs ) : 0 );
$tpl->setVariable( 'here', $here );
$tpl->setVariable( 'offset', $offset );
$tpl->setVariable( 'prev_url', $offset > 0 ? $here . ( $offset - $limit > 0 ? '/(offset)/' . ( $offset - $limit ) : '' ) : '' );
$tpl->setVariable( 'next_url', $offset + $limit < $rowCount ? $here . '/(offset)/' . ( $offset + $limit ) : '' );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'can_run', $import ? expImportAdmin::can( 'run', (string)$import->attribute( 'handler' ) ) : false );
$undoRefusal = expImportUndoHandler::refusal( $run );
$summary = $run->summaryArray();
$tpl->setVariable( 'can_undo', $undoRefusal === null && $import && expImportAdmin::can( 'manage', (string)$import->attribute( 'handler' ) ) );
$tpl->setVariable( 'undo_refusal', $undoRefusal );
$tpl->setVariable( 'undone_by', isset( $summary['undone_by'] ) ? (int)$summary['undone_by'] : 0 );
$tpl->setVariable( 'can_retry', $run->isFinal() && (int)$run->attribute( 'failed_count' ) > 0 && $import
                                 && expImportRunRow::countByRun( $run->attribute( 'id' ), array( 'outcome' => 'failed', 'data_json' => array( '!=', '' ) ) ) > 0 );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/run.tpl' );
$Result['path'] = array(
    array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
    array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Run #%id', null, array( '%id' => (int)$run->attribute( 'id' ) ) ) ),
);
