<?php
/**
 * File containing the expImportRunner class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Starts, cancels and watches runs. A run is a chain of jobs of the job queue (expqueue) in one job group:
 *
 *   sqliimport.run     makes the snapshot of the source, counts the records, queues the first chunk
 *   sqliimport.chunk   processes up to ChunkSize records from a cursor, then queues the next chunk or the finish
 *   sqliimport.finish  clears the view caches of the changed objects (all of them above site.ini CacheThreshold),
 *                      indexes them, writes the summary and tells the schedule's notify list about failures
 *
 * The chunks run one after another, so the records of an import are processed in their order (a parent before its
 * children). One import never runs twice at the same time (start() refuses while a run is queued or running), while
 * different imports run in parallel, each in its own expqueue resource sqliimport:<import id>. This replaces the one
 * global lock of 1.x (SQLIImportToken).
 */
class expImportRunner
{
    const JOB_RUN = 'sqliimport.run';
    const JOB_CHUNK = 'sqliimport.chunk';
    const JOB_FINISH = 'sqliimport.finish';

    /**
     * Starts a run of an import.
     *
     * @param expImportDefinition $import
     * @param array $options trigger (manual, schedule, cli, rest, legacy), user_id, schedule_id, run_kind (import,
     *                       dry_run, retry), parent_run_id, legacy_item_id, options (handler options of this run only,
     *                       a 1.x item's), max_rows
     * @return expImportRun
     * @throws expImportException the job queue is missing, the import is disabled or already running
     */
    public static function start( expImportDefinition $import, array $options = array() )
    {
        if ( !expImport::queueReady() )
            throw new expImportException( 'the job queue (expqueue) is not installed: activate the extension expqueue and run ./console ext:expqueue:install-schema' );
        $kind = isset( $options['run_kind'] ) ? (string)$options['run_kind'] : 'import';
        if ( !in_array( $kind, array( 'import', 'dry_run', 'retry', 'undo' ), true ) )
            throw new expImportException( "unknown run kind $kind" );
        if ( !in_array( $kind, array( 'dry_run', 'undo' ), true ) && !(int)$import->attribute( 'is_enabled' ) )
            throw new expImportException( 'the import is disabled' );
        $target = $import->target();
        $userID = isset( $options['user_id'] ) ? (int)$options['user_id'] : expImport::currentUserID();
        // the robot user of the import (chosen with the policy sqliimport/settings) runs it instead of the starter
        if ( (int)$target['owner_id'] && eZUser::fetch( (int)$target['owner_id'] ) )
            $userID = (int)$target['owner_id'];
        $trigger = isset( $options['trigger'] ) ? (string)$options['trigger'] : 'manual';

        $run = expQueue::withLock( function () use ( $import, $options, $kind, $userID, $trigger )
        {
            $active = expImportRun::fetchActive( $import->attribute( 'id' ) );
            if ( $active )
                throw new expImportException( 'the import already runs (run #' . $active->attribute( 'id' ) . ')' );
            $now = expImport::now();
            $summary = array();
            if ( isset( $options['options'] ) && is_array( $options['options'] ) )
                $summary['options'] = $options['options'];
            if ( isset( $options['max_rows'] ) )
                $summary['max_rows'] = (int)$options['max_rows'];
            $run = new expImportRun( array(
                'import_id' => (int)$import->attribute( 'id' ),
                'def_version' => (int)$import->attribute( 'def_version' ),
                'trigger_kind' => substr( $trigger, 0, 20 ),
                'run_kind' => $kind,
                'user_id' => $userID,
                'schedule_id' => isset( $options['schedule_id'] ) ? (int)$options['schedule_id'] : 0,
                'parent_run_id' => isset( $options['parent_run_id'] ) ? (int)$options['parent_run_id'] : 0,
                'legacy_item_id' => isset( $options['legacy_item_id'] ) ? (int)$options['legacy_item_id'] : 0,
                'status' => expImportRun::STATUS_QUEUED,
                'queued_at' => $now,
                'summary' => $summary ? expImport::json( $summary ) : '',
            ) );
            $run->store();
            return $run;
        } );

        $runID = (int)$run->attribute( 'id' );
        try
        {
            $group = expQueue::createGroup( 'Import "' . $import->attribute( 'name' ) . '", run #' . $runID, 'sqliimport', array( 'user_id' => $userID ) );
            $job = expQueue::enqueue( self::JOB_RUN, array( 'run_id' => $runID ), array(
                'group' => $group,
                'resource' => self::resource( $import ),
                'user_id' => $userID,
                'idempotency_key' => 'sqliimport-run-' . $runID,
            ) );
        }
        catch ( Exception $e )
        {
            $run->moveTo( expImportRun::STATUS_FAILED, array( 'finished_at' => expImport::now() ) );
            ( new expImportLogger( $runID ) )->error( 'the run could not be queued: ' . $e->getMessage() );
            ( new expImportLogger( $runID ) )->flush();
            throw new expImportException( 'the run could not be queued: ' . $e->getMessage() );
        }
        $run->setAttribute( 'queue_group', (string)$group->attribute( 'uuid' ) );
        $run->setAttribute( 'queue_job', (string)$job->attribute( 'uuid' ) );
        $run->store( array( 'queue_group', 'queue_job' ) );
        // the run in the history of the import and export tools (kernel 6.0.15 and later; nothing on an older kernel)
        expImportTransferRuns::started( $run );
        expImport::audit( 'run.start', array( 'id' => $runID, 'import' => $import->attribute( 'identifier' ), 'kind' => $kind, 'trigger' => $trigger ) );
        return $run;
    }

    /** @return string the expqueue resource of an import (its own concurrency key) */
    public static function resource( expImportDefinition $import )
    {
        return 'sqliimport:' . (int)$import->attribute( 'id' );
    }

    /**
     * Cancels a run: its queued jobs at once, the running one between two records.
     *
     * @param expImportRun $run
     * @return bool whether it was still running
     */
    public static function cancel( expImportRun $run, $reason = '' )
    {
        if ( $run->isFinal() )
            return false;
        if ( $run->attribute( 'queue_group' ) !== '' && class_exists( 'expQueue' ) )
            expQueue::cancelGroup( $run->attribute( 'queue_group' ), $reason !== '' ? $reason : 'cancelled from the import' );
        $changed = $run->moveTo( expImportRun::STATUS_CANCELLED, array( 'finished_at' => expImport::now() ) );
        if ( $changed )
        {
            $log = new expImportLogger( $run->attribute( 'id' ) );
            $log->notice( 'cancelled' . ( $reason !== '' ? ': ' . $reason : '' ) . ' by user #' . expImport::currentUserID() );
            $log->flush();
            self::syncLegacyItem( $run );
            expImport::audit( 'run.cancel', array( 'id' => (int)$run->attribute( 'id' ) ) );
        }
        return $changed;
    }

    /**
     * Starts a run that undoes an earlier run (expImportUndoHandler): what it created goes to the trash, what it
     * updated gets the version before the run again, unless it changed since.
     *
     * @param expImportRun $run
     * @return expImportRun
     */
    public static function undo( expImportRun $run )
    {
        $refusal = expImportUndoHandler::refusal( $run );
        if ( $refusal !== null )
            throw new expImportException( 'the run cannot be undone: ' . $refusal );
        $import = $run->import();
        if ( !$import )
            throw new expImportException( 'the import of the run no longer exists' );
        $undo = self::start( $import, array( 'run_kind' => 'undo', 'parent_run_id' => (int)$run->attribute( 'id' ), 'trigger' => 'manual' ) );
        $summary = $run->summaryArray();
        $summary['undone_by'] = (int)$undo->attribute( 'id' );
        $run->setAttribute( 'summary', expImport::json( $summary ) );
        $run->store( array( 'summary' ) );
        return $undo;
    }

    /**
     * Marks the runs whose time to undo is over ([RunSettings] UndoDays, or the import's undo_days), so they no longer
     * offer Undo (called by the cron part).
     *
     * @return int runs marked
     */
    public static function expireUndo()
    {
        $days = max( 1, (int)expImport::setting( 'RunSettings', 'UndoDays', 30 ) );
        $n = 0;
        // the oldest runs that are still undoable; an import with a longer undo_days keeps its runs
        foreach ( expImportRun::fetchList( array( 'status' => array( expImportRun::finalStatuses() ), 'finished_at' => array( '<', expImport::now() - 86400 ) ), 0, 500 ) as $run )
        {
            $summary = $run->summaryArray();
            if ( !empty( $summary['undo_expired'] ) || !in_array( $run->attribute( 'run_kind' ), array( 'import', 'retry' ), true ) )
                continue;
            $refusal = expImportUndoHandler::refusal( $run );
            if ( $refusal !== null && strpos( $refusal, 'the time to undo' ) === 0 )
            {
                $summary['undo_expired'] = true;
                $run->setAttribute( 'summary', expImport::json( $summary ) );
                $run->store( array( 'summary' ) );
                $n++;
            }
        }
        return $n;
    }

    /**
     * Starts a run that processes only the failed rows of an earlier run again (their stored records).
     *
     * @param expImportRun $run
     * @return expImportRun
     */
    public static function retryFailed( expImportRun $run )
    {
        if ( !$run->isFinal() )
            throw new expImportException( 'the run has not ended yet' );
        if ( !(int)$run->attribute( 'failed_count' ) )
            throw new expImportException( 'the run has no failed rows' );
        $import = $run->import();
        if ( !$import )
            throw new expImportException( 'the import of the run no longer exists' );
        if ( !expImportRunRow::countByRun( $run->attribute( 'id' ), array( 'outcome' => 'failed', 'data_json' => array( '!=', '' ) ) ) )
            throw new expImportException( 'the failed rows of this run carry no record to retry (a 1.x handler reads its own source): run the import again' );
        return self::start( $import, array( 'run_kind' => 'retry', 'parent_run_id' => (int)$run->attribute( 'id' ), 'trigger' => 'manual' ) );
    }

    /**
     * Ends runs whose jobs ended without them (a job in the dead letter, a group cancelled from the queue page). Called
     * by the cron part every minute.
     *
     * @return int runs ended
     */
    public static function reconcile()
    {
        $n = 0;
        foreach ( expImportRun::fetchList( array( 'status' => array( array( expImportRun::STATUS_QUEUED, expImportRun::STATUS_RUNNING ) ) ), 0, 200 ) as $run )
        {
            $uuid = (string)$run->attribute( 'queue_job' );
            if ( $uuid === '' )
                continue;
            $job = expQueue::fetch( $uuid );
            if ( !$job )
                $state = 'missing';
            else
                $state = $job->attribute( 'state' );
            if ( $state === 'dead' || $state === 'missing' )
            {
                if ( $run->moveTo( expImportRun::STATUS_FAILED, array( 'finished_at' => expImport::now() ) ) )
                {
                    $log = new expImportLogger( $run->attribute( 'id' ) );
                    $log->error( $state === 'dead' ? 'a job of the run failed every attempt (see the job queue)' : 'the job of the run no longer exists' );
                    $log->flush();
                    self::syncLegacyItem( $run );
                    expImport::audit( 'run.fail', array( 'id' => (int)$run->attribute( 'id' ) ) );
                    $n++;
                }
            }
            elseif ( $state === 'cancelled' )
            {
                if ( $run->moveTo( expImportRun::STATUS_CANCELLED, array( 'finished_at' => expImport::now() ) ) )
                {
                    self::syncLegacyItem( $run );
                    $n++;
                }
            }
        }
        return $n;
    }

    /** Mirrors the end of a run onto the 1.x item it runs for (the list of sqliimport/list in 1.x terms). */
    public static function syncLegacyItem( expImportRun $run )
    {
        $itemID = (int)$run->attribute( 'legacy_item_id' );
        if ( !$itemID )
            return;
        $item = SQLIImportItem::fetch( $itemID );
        if ( !$item instanceof SQLIImportItem )
            return;
        $map = array(
            expImportRun::STATUS_RUNNING => SQLIImportItem::STATUS_RUNNING,
            expImportRun::STATUS_COMPLETED => SQLIImportItem::STATUS_COMPLETED,
            expImportRun::STATUS_COMPLETED_WITH_ERRORS => SQLIImportItem::STATUS_COMPLETED,
            expImportRun::STATUS_FAILED => SQLIImportItem::STATUS_FAILED,
            expImportRun::STATUS_CANCELLED => SQLIImportItem::STATUS_CANCELED,
        );
        $status = $run->attribute( 'status' );
        if ( !isset( $map[$status] ) )
            return;
        $item->setAttribute( 'status', $map[$status] );
        $item->setAttribute( 'percentage', $run->percent() );
        $item->setAttribute( 'process_time', $run->duration() );
        $item->store();
    }
}
