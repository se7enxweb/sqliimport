<?php
/**
 * sqliimport/run/<id>: one run. Its status, progress and counters, the records by outcome (filtered by (outcome)),
 * the log, and Cancel or Retry failed rows for users who may run the import.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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
        'id' => (int)$row->attribute( 'id' ),
        'ref' => $row->attribute( 'row_ref' ),
        'outcome' => $row->attribute( 'outcome' ),
        'outcome_label' => expImportAdmin::label( 'outcome', $row->attribute( 'outcome' ) ),
        'object_id' => (int)$row->attribute( 'object_id' ),
        'remote_id' => $row->attribute( 'remote_id' ),
        'version_before' => (int)$row->attribute( 'version_before' ),
        'version_after' => (int)$row->attribute( 'version_after' ),
        'message' => $row->attribute( 'message' ),
        'diff' => isset( $nodes['diff'] ) ? $nodes['diff'] : array(),
        'ai' => false,
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

// the AI assist (expImportAi, doc/ai.md): "Explain" for a failed row and "Summarize" for a finished run, a POST of this
// page (ezformtoken checks it); the answers are kept for the session per run and shown on every later view
$aiAvailable = $import && !$import->isLegacy() && expImportAi::available( array( 'purpose' => 'summary', 'run_id' => (int)$run->attribute( 'id' ) ) );
$aiSummaryKey = expImportAi::key( 'summary', (int)$run->attribute( 'id' ), array( $run->attribute( 'status' ), (int)$run->attribute( 'finished_at' ) ) );
$isPost = strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST';
if ( $isPost && ( $http->hasPostVariable( 'AiExplainButton' ) || $http->hasPostVariable( 'AiSummaryButton' ) ) )
{
    $aiError = '';
    $mappingArray = $import ? $import->mappingArray() : null;
    $context = $import ? expImportAi::contextFor( $import, is_array( $mappingArray ) ? (string)$mappingArray['class'] : '' ) + array( 'run_id' => (int)$run->attribute( 'id' ) ) : array();
    if ( !$aiAvailable )
        $aiError = ezpI18n::tr( 'extension/sqliimport', 'The AI assist is not available: %reason', null, array( '%reason' => expImportAi::refusal( array( 'purpose' => 'summary' ) ) ) );
    elseif ( $http->hasPostVariable( 'AiExplainButton' ) )
    {
        $rowID = (int)$http->postVariable( 'AiExplainButton' );
        $found = expImportRunRow::fetchByRun( $run->attribute( 'id' ), array( 'id' => $rowID, 'outcome' => 'failed' ), 0, 1 );
        $key = expImportAi::key( 'explain', (int)$run->attribute( 'id' ), array( $rowID ) );
        if ( !$found )
            $aiError = ezpI18n::tr( 'extension/sqliimport', 'The failed row was not found.' );
        elseif ( expImportAi::cached( $key ) === null )
        {
            $row = $found[0];
            $lines = expImportAi::linesFor( $mappingArray, (string)$row->attribute( 'message' ) );
            // the record's values only when the import opts in (masked before they are sent), and only its mapped fields
            $values = null;
            if ( expImportAi::sendsSamples( $import->behaviour() ) && ( $record = $row->record() ) )
            {
                $flat = expImportPreview::flatten( (array)$record->data );
                $sources = array_filter( array_column( $lines, 'source' ), 'strlen' );
                $values = $sources ? array_intersect_key( $flat, array_flip( $sources ) ) : array_slice( $flat, 0, 40, true );
            }
            try
            {
                expImportAi::remember( $key, expImportAi::explain( (string)$row->attribute( 'message' ), $lines, $values, $context ) );
                expImport::audit( 'ai.explain', array( 'id' => (int)$run->attribute( 'id' ), 'import' => $import->attribute( 'identifier' ), 'row' => $rowID, 'samples' => $values !== null ) );
            }
            catch ( expImportAiException $e )
            {
                $aiError = ezpI18n::tr( 'extension/sqliimport', 'The AI assist could not answer: %error', null, array( '%error' => $e->getMessage() ) );
            }
        }
    }
    elseif ( !$run->isFinal() )
        $aiError = ezpI18n::tr( 'extension/sqliimport', 'A run can be summarized when it has ended.' );
    elseif ( expImportAi::cached( $aiSummaryKey ) === null )
    {
        // the run's own figures: counts, durations, the most common errors (masked) and the fields updates changed
        $messages = array();
        foreach ( expImportRunRow::fetchByRun( $run->attribute( 'id' ), array( 'outcome' => 'failed' ), 0, 500 ) as $row )
            $messages[] = (string)$row->attribute( 'message' );
        $changed = array();
        foreach ( expImportRunRow::fetchByRun( $run->attribute( 'id' ), array( 'outcome' => 'updated' ), 0, 500 ) as $row )
        {
            $nodes = expImport::decode( $row->attribute( 'nodes_json' ) );
            foreach ( isset( $nodes['diff'] ) && is_array( $nodes['diff'] ) ? array_keys( $nodes['diff'] ) : array() as $field )
                $changed[$field] = ( isset( $changed[$field] ) ? $changed[$field] : 0 ) + 1;
        }
        try
        {
            expImportAi::remember( $aiSummaryKey, expImportAi::summarize( expImportAi::summaryStats( expImportAdmin::runRow( $run ), $messages, $changed ), $context ) );
            expImport::audit( 'ai.summary', array( 'id' => (int)$run->attribute( 'id' ), 'import' => $import->attribute( 'identifier' ) ) );
        }
        catch ( expImportAiException $e )
        {
            $aiError = ezpI18n::tr( 'extension/sqliimport', 'The AI assist could not answer: %error', null, array( '%error' => $e->getMessage() ) );
        }
    }
    if ( $aiError !== '' )
        $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => 'bad', 'message' => $aiError ) );
    $Module->redirectTo( $here );
    return;
}
if ( $aiAvailable )
    foreach ( $rows as $i => $r )
        if ( $r['outcome'] === 'failed' )
        {
            $kept = expImportAi::cached( expImportAi::key( 'explain', (int)$run->attribute( 'id' ), array( $r['id'] ) ) );
            $rows[$i]['ai'] = is_array( $kept ) ? $kept : false;
        }

$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );

$tpl = eZTemplate::factory();
// looking at a run reads its notices
expImportNotifier::markSeen( expImport::currentUserID(), (int)$run->attribute( 'id' ) );
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
$aiSummary = $aiAvailable ? expImportAi::cached( $aiSummaryKey ) : null;
$tpl->setVariable( 'ai', array( 'available' => (bool)$aiAvailable, 'summary' => is_array( $aiSummary ) ? $aiSummary : false,
                                'samples' => $import ? expImportAi::sendsSamples( $import->behaviour() ) : false ) );
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
