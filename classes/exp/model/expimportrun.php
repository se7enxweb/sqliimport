<?php
/**
 * File containing the expImportRun, expImportRunRow and expImportRunLog classes.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * One run of an import (sqliimport_run): who and what started it, its job group in expqueue, its status and counters.
 *
 *   run_kind      import, retry (only the failed rows of parent_run_id), dry_run (nothing is published)
 *   trigger_kind  manual, schedule, cli, rest, legacy (a 1.x item)
 *   status        queued, running, completed, completed_with_errors, failed, cancelled
 */
class expImportRun extends eZPersistentObject
{
    const STATUS_QUEUED = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /** The outcome counters a row adds to, outcome => column */
    const COUNTERS = array( 'created' => 'created_count', 'updated' => 'updated_count', 'unchanged' => 'unchanged_count',
                            'skipped' => 'skipped_count', 'failed' => 'failed_count', 'processed' => 'processed_count',
                            'undone' => 'undone_count' );

    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_run' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportRun',
            'name' => 'sqliimport_run',
            'function_attributes' => array(
                'import' => 'import',
                'is_final' => 'isFinal',
                'percent' => 'percent',
                'done_count' => 'doneCount',
                'duration' => 'duration',
                'summary_array' => 'summaryArray',
                'user' => 'user',
            ),
        );
    }

    /** @return string[] */
    public static function finalStatuses()
    {
        return array( self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS, self::STATUS_FAILED, self::STATUS_CANCELLED );
    }

    /** @return expImportRun|null */
    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return expImportRun[] newest first */
    public static function fetchList( array $conds = array(), $offset = 0, $limit = 50 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, $conds ?: null, array( 'id' => 'desc' ),
                                                    array( 'offset' => (int)$offset, 'length' => (int)$limit ) );
    }

    /** @return int */
    public static function fetchCount( array $conds = array() )
    {
        return (int)eZPersistentObject::count( self::definition(), $conds ?: null );
    }

    /** @return expImportRun|null the queued or running run of an import */
    public static function fetchActive( $importID )
    {
        $list = eZPersistentObject::fetchObjectList( self::definition(), null,
                                                     array( 'import_id' => (int)$importID, 'status' => array( array( self::STATUS_QUEUED, self::STATUS_RUNNING ) ) ),
                                                     array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => 1 ) );
        return $list ? $list[0] : null;
    }

    /** @return expImportDefinition|null */
    public function import()
    {
        return expImportDefinition::fetch( $this->attribute( 'import_id' ) );
    }

    /** @return eZUser|null */
    public function user()
    {
        return eZUser::fetch( (int)$this->attribute( 'user_id' ) );
    }

    /** @return bool */
    public function isFinal()
    {
        return in_array( $this->attribute( 'status' ), self::finalStatuses(), true );
    }

    /** @return int rows processed so far */
    public function doneCount()
    {
        $n = 0;
        foreach ( self::COUNTERS as $column )
            $n += (int)$this->attribute( $column );
        return $n;
    }

    /** @return int 0..100 */
    public function percent()
    {
        if ( $this->isFinal() && $this->attribute( 'status' ) !== self::STATUS_FAILED && $this->attribute( 'status' ) !== self::STATUS_CANCELLED )
            return 100;
        $total = (int)$this->attribute( 'total_count' );
        if ( $total <= 0 )
            return 0;
        return (int)min( 100, floor( 100 * $this->doneCount() / $total ) );
    }

    /** @return int seconds */
    public function duration()
    {
        $start = (int)$this->attribute( 'started_at' );
        if ( !$start )
            return 0;
        $end = (int)$this->attribute( 'finished_at' ) ?: expImport::now();
        return max( 0, $end - $start );
    }

    /** @return array */
    public function summaryArray()
    {
        return expImport::decode( $this->attribute( 'summary' ) );
    }

    /**
     * Adds to the counters in one statement (concurrent-safe), from a row's outcome.
     *
     * @param array $outcomes outcome => count
     * @param int $read records read
     */
    public function addCounts( array $outcomes, $read )
    {
        $sets = array();
        foreach ( $outcomes as $outcome => $n )
            if ( isset( self::COUNTERS[$outcome] ) && $n )
                $sets[] = self::COUNTERS[$outcome] . ' = ' . self::COUNTERS[$outcome] . ' + ' . (int)$n;
        if ( $read )
            $sets[] = 'read_count = read_count + ' . (int)$read;
        if ( !$sets )
            return;
        $id = (int)$this->attribute( 'id' );
        expImport::db()->query( 'UPDATE sqliimport_run SET ' . implode( ', ', $sets ) . ' WHERE id = ' . $id );
        $this->reload();
    }

    /** Reads the row again from the database. */
    public function reload()
    {
        $fresh = self::fetch( $this->attribute( 'id' ) );
        if ( $fresh )
            foreach ( array_keys( expImport::persistentFields( 'sqliimport_run' ) ) as $field )
                $this->setAttribute( $field, $fresh->attribute( $field ) );
    }

    /**
     * Sets the status only when the run is not final yet (a cancel never becomes a completed, and the reverse).
     *
     * @param string $status
     * @param array $set more columns
     * @return bool whether it changed
     */
    public function moveTo( $status, array $set = array() )
    {
        $db = expImport::db();
        $parts = array( "status = '" . $db->escapeString( $status ) . "'" );
        foreach ( $set as $column => $value )
            $parts[] = $column . ' = ' . ( is_int( $value ) ? $value : "'" . $db->escapeString( (string)$value ) . "'" );
        $final = "'" . implode( "', '", self::finalStatuses() ) . "'";
        $db->query( 'UPDATE sqliimport_run SET ' . implode( ', ', $parts ) . ' WHERE id = ' . (int)$this->attribute( 'id' ) . " AND status NOT IN ($final)" );
        $before = $this->attribute( 'status' );
        $this->reload();
        $changed = $this->attribute( 'status' ) === $status && $before !== $status;
        // the run's row in the history of the import and export tools follows its status
        if ( $changed && class_exists( 'expImportTransferRuns' ) )
            expImportTransferRuns::statusChanged( $this );
        return $changed;
    }

    /** @return expImportRunRow[] */
    public function rows( array $conds = array(), $offset = 0, $limit = 100 )
    {
        return expImportRunRow::fetchByRun( $this->attribute( 'id' ), $conds, $offset, $limit );
    }

    /** Removes the run with its rows and log. */
    public function removeAll()
    {
        $id = (int)$this->attribute( 'id' );
        $db = expImport::db();
        $db->query( 'DELETE FROM sqliimport_run_row WHERE run_id = ' . $id );
        $db->query( 'DELETE FROM sqliimport_notice WHERE run_id = ' . $id );
        $db->query( 'DELETE FROM sqliimport_run_log WHERE run_id = ' . $id );
        $this->remove();
    }
}

/**
 * One record of a run (sqliimport_run_row): what was done to which object. It feeds "retry failed rows" (data_json
 * keeps a failed record) and the rollback of later versions (version_before, version_after, nodes_json).
 *
 *   outcome  created, updated, unchanged, skipped, failed
 */
class expImportRunRow extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_run_row' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportRunRow',
            'name' => 'sqliimport_run_row',
            'function_attributes' => array( 'record' => 'record' ),
        );
    }

    /** @return expImportRunRow[] */
    public static function fetchByRun( $runID, array $conds = array(), $offset = 0, $limit = 100 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, array( 'run_id' => (int)$runID ) + $conds, array( 'row_index' => 'asc', 'id' => 'asc' ),
                                                    array( 'offset' => (int)$offset, 'length' => (int)$limit ) );
    }

    /** @return int */
    public static function countByRun( $runID, array $conds = array() )
    {
        return (int)eZPersistentObject::count( self::definition(), array( 'run_id' => (int)$runID ) + $conds );
    }

    /** @return bool whether a run already has a row of this reference (a chunk that runs again skips it) */
    public static function exists( $runID, $ref )
    {
        return self::countByRun( $runID, array( 'row_ref' => substr( (string)$ref, 0, 255 ) ) ) > 0;
    }

    /** @return expImportRecord|null the stored record of a failed row */
    public function record()
    {
        $data = expImport::decode( $this->attribute( 'data_json' ) );
        return $data ? expImportRecord::fromArray( $data ) : null;
    }
}

/** A line of the log of a run (sqliimport_run_log): severity, the record it is about, message, context. */
class expImportRunLog extends eZPersistentObject
{
    const SEVERITIES = array( 'debug', 'info', 'notice', 'warning', 'error' );

    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_run_log' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportRunLog',
            'name' => 'sqliimport_run_log',
            'function_attributes' => array( 'context' => 'context' ),
        );
    }

    /**
     * @param int $runID
     * @param int $afterID only lines after this one (the live tail)
     * @param array $severities empty: all
     * @return expImportRunLog[]
     */
    public static function fetchByRun( $runID, $afterID = 0, array $severities = array(), $offset = 0, $limit = 200 )
    {
        $conds = array( 'run_id' => (int)$runID );
        if ( $afterID )
            $conds['id'] = array( '>', (int)$afterID );
        if ( $severities )
            $conds['severity'] = array( array_values( $severities ) );
        return eZPersistentObject::fetchObjectList( self::definition(), null, $conds, array( 'id' => 'asc' ),
                                                    array( 'offset' => (int)$offset, 'length' => (int)$limit ) );
    }

    /** @return array */
    public function context()
    {
        return expImport::decode( $this->attribute( 'context_json' ) );
    }
}
