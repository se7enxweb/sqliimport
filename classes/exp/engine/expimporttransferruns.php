<?php
/**
 * File containing the expImportTransferRuns class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Imports in the run history of Exponential's import and export tools (kernel 6.0.15 and later,
 * expKernelTransferHistory, table exp_transfer_run): every run of an import writes one row there, so the pages of
 * the other tools (and anything reading the history) see Imports' runs; and Imports' run list and Statistics read
 * the rows of the other tools for their (tool) filter.
 *
 * On a kernel without the history every method does nothing (and the reading ones give nothing): Imports behaves as
 * before. The kernel's writes never throw; these wrappers catch anything else too, so a run never fails for its row
 * in the history.
 */
class expImportTransferRuns
{
    /** The id of Imports in the transfer registry */
    const TOOL = 'transfer.sqliimport';

    /** @var bool|null whether the kernel's classes of the history are there (checked once per process) */
    protected static $kernel = null;

    /**
     * Whether the kernel keeps the run history. The kernel's files are looked for before the class is asked for: a
     * kernel whose autoload map names the class before its files are installed would end the request with a fatal
     * error on the plain class_exists().
     *
     * @return bool
     */
    public static function available()
    {
        if ( self::$kernel === null )
        {
            $loaded = class_exists( 'expKernelTransferHistory', false );
            if ( !$loaded && class_exists( 'eZSys' ) )
            {
                $dir = eZSys::rootDir() . '/kernel/classes/transfer';
                $loaded = is_file( $dir . '/expkerneltransferhistory.php' ) && is_file( $dir . '/expkerneltransferrun.php' )
                          && class_exists( 'expKernelTransferHistory' );
            }
            self::$kernel = $loaded;
        }
        return self::$kernel && expKernelTransferHistory::available();
    }

    /**
     * The state of the kernel row for a run's status.
     *
     * @param string $status
     * @return string queued, running, done, failed or cancelled
     */
    public static function stateOf( $status )
    {
        $map = array( 'queued' => 'queued', 'running' => 'running', 'completed' => 'done', 'completed_with_errors' => 'done',
                      'failed' => 'failed', 'cancelled' => 'cancelled' );
        return isset( $map[$status] ) ? $map[$status] : 'running';
    }

    /**
     * What the kernel row of a run holds.
     *
     * @param expImportRun $run
     * @return array
     */
    public static function infoOf( expImportRun $run )
    {
        $import = $run->import();
        $mapping = $import ? $import->mappingArray() : null;
        $failed = (int)$run->attribute( 'failed_count' );
        $done = (int)$run->attribute( 'created_count' ) + (int)$run->attribute( 'updated_count' ) + (int)$run->attribute( 'unchanged_count' )
              + (int)$run->attribute( 'skipped_count' ) + (int)$run->attribute( 'processed_count' ) + (int)$run->attribute( 'undone_count' ) + $failed;
        return array(
            'direction' => 'import',
            'kind' => (string)$run->attribute( 'run_kind' ),
            'title' => $import ? (string)$import->attribute( 'name' ) : 'Import #' . (int)$run->attribute( 'import_id' ),
            'scope' => 'class',
            'scope_ref' => $mapping && isset( $mapping['class'] ) ? (string)$mapping['class'] : '',
            'format' => $import ? (string)$import->attribute( 'source_type' ) : '',
            'tool_ref' => (string)(int)$run->attribute( 'id' ),
            'detail_url' => 'sqliimport/run/' . (int)$run->attribute( 'id' ),
            'engine' => 'expqueue',
            'job_ref' => (string)$run->attribute( 'queue_job' ),
            'rows_total' => (int)$run->attribute( 'total_count' ),
            'rows_done' => $done,
            'rows_failed' => $failed,
            'user_id' => (int)$run->attribute( 'user_id' ),
        );
    }

    /** Writes the row of a run that was just queued. */
    public static function started( expImportRun $run )
    {
        if ( !self::available() )
            return;
        try
        {
            if ( expKernelTransferHistory::findByToolRef( self::TOOL, (string)(int)$run->attribute( 'id' ) ) )
                return;
            expKernelTransferHistory::start( self::TOOL, array( 'state' => self::stateOf( (string)$run->attribute( 'status' ) ) ) + self::infoOf( $run ) );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'run #' . $run->attribute( 'id' ) . ' in the transfer history: ' . $e->getMessage(), __METHOD__ );
        }
    }

    /** Brings the row of a run to its new status: running, or its end with the final counts and first errors. */
    public static function statusChanged( expImportRun $run )
    {
        if ( !self::available() )
            return;
        try
        {
            $state = self::stateOf( (string)$run->attribute( 'status' ) );
            $info = self::infoOf( $run );
            $row = expKernelTransferHistory::findByToolRef( self::TOOL, $info['tool_ref'] );
            if ( !in_array( $state, array( 'done', 'failed', 'cancelled' ), true ) )
            {
                if ( $row )
                    expKernelTransferHistory::update( (int)$row->attribute( 'id' ), array( 'state' => $state, 'started' => (int)$run->attribute( 'started_at' ) ?: time() ) );
                else
                    self::started( $run );
                return;
            }
            $errors = array();
            foreach ( expImportRunLog::fetchByRun( $run->attribute( 'id' ), 0, array( 'error' ), 0, 20 ) as $line )
                $errors[] = mb_substr( ( $line->attribute( 'row_ref' ) !== '' ? $line->attribute( 'row_ref' ) . ': ' : '' ) . $line->attribute( 'message' ), 0, 250 );
            $end = array(
                'rows_done' => $info['rows_done'], 'rows_failed' => $info['rows_failed'], 'rows_total' => max( $info['rows_total'], $info['rows_done'] ),
                'detail' => array( 'created' => (int)$run->attribute( 'created_count' ), 'updated' => (int)$run->attribute( 'updated_count' ),
                                   'unchanged' => (int)$run->attribute( 'unchanged_count' ), 'skipped' => (int)$run->attribute( 'skipped_count' ),
                                   'undone' => (int)$run->attribute( 'undone_count' ) ),
                'errors' => $errors,
                'error' => $state === 'failed' && $errors ? $errors[0] : '',
            );
            if ( (int)$run->attribute( 'finished_at' ) )
                $end['finished'] = (int)$run->attribute( 'finished_at' );
            if ( (int)$run->attribute( 'started_at' ) )
                $end['started'] = (int)$run->attribute( 'started_at' );
            if ( $row )
                expKernelTransferHistory::finish( (int)$row->attribute( 'id' ), $state, $end );
            else
                expKernelTransferHistory::record( self::TOOL, array( 'state' => $state ) + $end + $info );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'run #' . $run->attribute( 'id' ) . ' in the transfer history: ' . $e->getMessage(), __METHOD__ );
        }
    }

    /**
     * The tools of the (tool) filter: Imports, the other tools the user may use, and all.
     *
     * @return array list of array( id, name, runs ); empty when there is no history or no other tool
     */
    public static function toolOptions()
    {
        if ( !self::available() )
            return array();
        try
        {
            $others = array_values( array_filter( (array)expKernelTransferHistory::toolOptions(), function ( $o ) { return $o['id'] !== self::TOOL; } ) );
        }
        catch ( Throwable $e )
        {
            return array();
        }
        return $others;
    }

    /**
     * The other tools a (tool) value names, never Imports itself (its runs come from sqliimport_run).
     *
     * @param string $tool '', transfer.sqliimport, all, or another registry id
     * @return string[] registry ids to read from the kernel; empty for Imports only
     */
    public static function otherTools( $tool )
    {
        $tool = (string)$tool;
        if ( $tool === '' || $tool === self::TOOL || !self::available() )
            return array();
        $ids = array_map( function ( $o ) { return (string)$o['id']; }, self::toolOptions() );
        if ( $tool === 'all' )
            return $ids;
        return in_array( $tool, $ids, true ) ? array( $tool ) : array();
    }

    /** @return bool whether a (tool) value keeps Imports' own runs on the page */
    public static function includesImports( $tool )
    {
        return in_array( (string)$tool, array( '', self::TOOL, 'all' ), true ) || !self::available();
    }

    /**
     * The runs of other tools for the run list.
     *
     * @param string[] $tools
     * @param array $filter state, user_id, from, to
     * @param int $limit
     * @return array rows of expKernelTransferHistory::listVisible()
     */
    public static function listRuns( array $tools, array $filter = array(), $limit = 50 )
    {
        if ( !$tools || !self::available() )
            return array();
        try
        {
            return (array)expKernelTransferHistory::listVisible( array( 'tool' => $tools ) + $filter, $limit );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'the runs of other tools: ' . $e->getMessage(), __METHOD__ );
            return array();
        }
    }

    /**
     * The runs of other tools for Statistics, in the shape of a sqliimport_run row.
     *
     * @param string[] $tools
     * @param int $from
     * @param int $to
     * @return array
     */
    public static function statisticsRows( array $tools, $from, $to )
    {
        if ( !$tools || !self::available() )
            return array();
        try
        {
            return (array)expKernelTransferHistory::statisticsRows( array( 'tool' => $tools, 'from' => (int)$from, 'to' => (int)$to ) );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'the statistics of other tools: ' . $e->getMessage(), __METHOD__ );
            return array();
        }
    }
}
