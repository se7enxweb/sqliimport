<?php
/**
 * sqliimport/report/<run id>[/(outcome)/<outcome>]: the CSV report of a run, one line per record (expImportReport).
 * A GET that changes nothing; who may look at the run may download its report.
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
$outcomes = array( 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed', 'undone' );
$outcome = isset( $Params['Outcome'] ) && in_array( $Params['Outcome'], $outcomes, true ) ? (string)$Params['Outcome'] : '';

expImport::discardOutput();
header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="' . expImportReport::fileName( $run, $outcome ) . '"' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store' );
$out = fopen( 'php://output', 'w' );
expImportReport::write( $run, $out, $outcome );
fclose( $out );
eZExecution::cleanExit();
