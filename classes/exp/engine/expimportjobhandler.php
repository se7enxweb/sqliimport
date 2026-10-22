<?php
/**
 * File containing the expImportJobHandler class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The jobs of a run in the job queue (expqueue.ini [JobType_sqliimport.*]): sqliimport.run, sqliimport.chunk and
 * sqliimport.finish (see expImportRunner). They run in a worker, as the user the run belongs to.
 *
 * A chunk stores the cursor of the last record it finished as the job's checkpoint every few records, so an attempt
 * after a crash continues there; a record that already has its row in the run is not processed twice. Between chunks
 * the kernel's in-memory object caches are emptied, so the memory of a long run stays flat (upstream issue #9); the
 * peak is recorded on the run.
 */
class expImportJobHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        if ( empty( $payload['run_id'] ) )
            return 'run_id is missing';
        return null;
    }

    public function describe( expQueueJob $job )
    {
        $p = $job->payloadArray();
        $text = 'import run #' . ( isset( $p['run_id'] ) ? (int)$p['run_id'] : 0 );
        if ( isset( $p['n'] ) )
            $text .= ', chunk ' . ( (int)$p['n'] + 1 );
        return $text;
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $payload = $job->payloadArray();
        $run = expImportRun::fetch( isset( $payload['run_id'] ) ? $payload['run_id'] : 0 );
        if ( !$run )
            return expQueueResult::fail( 'the run no longer exists' );
        if ( $run->isFinal() )
            return expQueueResult::done( array(), 'the run has ended already (' . $run->attribute( 'status' ) . ')' );
        $import = $run->import();
        if ( !$import )
        {
            $run->moveTo( expImportRun::STATUS_FAILED, array( 'finished_at' => expImport::now() ) );
            return expQueueResult::fail( 'the import of the run no longer exists' );
        }
        expImportRegistry::instance()->registerTransforms();
        $log = new expImportLogger( $run->attribute( 'id' ), (string)expImport::setting( 'RunSettings', 'LogLevel', 'info' ) );
        try
        {
            switch ( $job->attribute( 'type' ) )
            {
                case expImportRunner::JOB_RUN:
                    // what this worker's PHP can read (the drivers of the database sources), for the editor, which may run in another PHP
                    expImportSqlSource::recordWorkerPhp();
                    expImportLdapSource::recordWorkerPhp();
                    return $this->prepare( $run, $import, $job, $log );
                case expImportRunner::JOB_CHUNK:
                    return $this->chunk( $run, $import, $job, $context, $log, $payload );
                case expImportRunner::JOB_FINISH:
                    return $this->finish( $run, $import, $log );
            }
            return expQueueResult::fail( 'unknown job type ' . $job->attribute( 'type' ) );
        }
        catch ( expQueueCancelledException $e )
        {
            $log->notice( 'cancelled while it ran' );
            $run->moveTo( expImportRun::STATUS_CANCELLED, array( 'finished_at' => expImport::now() ) );
            expImportRunner::syncLegacyItem( $run );
            throw $e;
        }
        catch ( Exception $e )
        {
            // a source that cannot be read, a mapping that is not valid, a 1.x handler that refuses to start (its
            // SQLIImport* and ezc exceptions): trying again does not help. Other errors (a database that is busy,
            // a PHP error) go to the job queue, which retries the job.
            if ( !$e instanceof expImportException && !$e instanceof SQLIImportBaseException && !$e instanceof ezcBaseException )
                throw $e;
            $log->error( $e->getMessage() );
            $this->fail( $run, $log );
            return expQueueResult::fail( $e->getMessage() );
        }
        finally
        {
            $log->flush();
        }
    }

    protected function fail( expImportRun $run, expImportLogger $log )
    {
        $log->flush();
        if ( $run->moveTo( expImportRun::STATUS_FAILED, array( 'finished_at' => expImport::now(), 'memory_peak' => memory_get_peak_usage( true ) ) ) )
        {
            expImportRunner::syncLegacyItem( $run );
            expImport::audit( 'run.fail', array( 'id' => (int)$run->attribute( 'id' ) ) );
            expImportNotifier::runEnded( $run );
        }
    }

    /** sqliimport.run: the snapshot, the count, the first chunk. */
    protected function prepare( expImportRun $run, expImportDefinition $import, expQueueJob $job, expImportLogger $log )
    {
        $run->moveTo( expImportRun::STATUS_RUNNING, array( 'started_at' => expImport::now() ) );
        expImportRunner::syncLegacyItem( $run );
        $handler = $this->handlerOf( $run, $import );
        $context = $this->context( $run, $import, $log, null );
        $total = null;
        if ( $run->attribute( 'run_kind' ) === 'undo' )
            $total = (int)expImportRunRow::countByRun( $run->attribute( 'parent_run_id' ), array( 'outcome' => array( array( 'created', 'updated' ) ) ) );
        elseif ( $run->attribute( 'run_kind' ) === 'retry' )
            $total = expImportRunRow::countByRun( $run->attribute( 'parent_run_id' ), array( 'outcome' => 'failed', 'data_json' => array( '!=', '' ) ) );
        elseif ( $handler instanceof expImportRecordProviderInterface )
            $total = $handler->count( $context );
        else
        {
            $file = expImportSourceFiles::snapshot( $import, $run );
            $run->setAttribute( 'source_ref', substr( $file, 0, 255 ) );
            $run->store( array( 'source_ref' ) );
            $total = $this->source( $import, $file )->count();
        }
        $max = $this->maxRows( $run, $import );
        if ( $total !== null && $max && $total > $max )
            $total = $max;
        if ( $total !== null )
        {
            $run->setAttribute( 'total_count', (int)$total );
            $run->store( array( 'total_count' ) );
        }
        $log->info( 'run started: ' . ( $total !== null ? $total . ' records' : 'records counted while they are read' )
                    . ', handler ' . $import->attribute( 'handler' ) . ( $run->attribute( 'run_kind' ) !== 'import' ? ', ' . $run->attribute( 'run_kind' ) : '' ) );
        $this->queueNext( $run, $import, $job, self::chunkPayload( $run, 0, array() ) );
        return expQueueResult::done( array( 'total' => $total ) );
    }

    /** @return array */
    protected static function chunkPayload( expImportRun $run, $n, array $cursor )
    {
        return array( 'run_id' => (int)$run->attribute( 'id' ), 'n' => (int)$n, 'cursor' => $cursor );
    }

    /** @return int the row limit of the run (0 = none) */
    protected function maxRows( expImportRun $run, expImportDefinition $import )
    {
        if ( in_array( $run->attribute( 'run_kind' ), array( 'undo', 'retry' ), true ) )
            return 0;
        $summary = $run->summaryArray();
        if ( isset( $summary['max_rows'] ) && (int)$summary['max_rows'] > 0 )
            return (int)$summary['max_rows'];
        if ( $run->attribute( 'run_kind' ) === 'dry_run' )
            return max( 1, (int)expImport::setting( 'RunSettings', 'DryRunRows', 20 ) );
        $behaviour = $import->behaviour();
        return (int)$behaviour['max_rows'];
    }

    /** Queues the next chunk (payload with n) or the finish job, in the run's group and resource. */
    protected function queueNext( expImportRun $run, expImportDefinition $import, expQueueJob $job, ?array $chunk )
    {
        $type = $chunk ? expImportRunner::JOB_CHUNK : expImportRunner::JOB_FINISH;
        $payload = $chunk ?: array( 'run_id' => (int)$run->attribute( 'id' ) );
        $key = 'sqliimport-run-' . (int)$run->attribute( 'id' ) . ( $chunk ? '-chunk-' . (int)$chunk['n'] : '-finish' );
        $next = expQueue::enqueue( $type, $payload, array(
            'group' => (int)$job->attribute( 'group_id' ) ?: null,
            'resource' => expImportRunner::resource( $import ),
            'user_id' => (int)$run->attribute( 'user_id' ),
            'idempotency_key' => $key,
        ) );
        $run->setAttribute( 'queue_job', (string)$next->attribute( 'uuid' ) );
        $run->store( array( 'queue_job' ) );
    }

    /** @return expImportContext */
    protected function context( expImportRun $run, expImportDefinition $import, expImportLogger $log, ?expQueueContext $queue )
    {
        $options = $import->options();
        $summary = $run->summaryArray();
        if ( isset( $summary['options'] ) && is_array( $summary['options'] ) )
            $options = $summary['options'] + $options;
        $handlers = expImportRegistry::instance()->handlers();
        $id = (string)$import->attribute( 'handler' );
        if ( isset( $handlers[$id] ) && $handlers[$id]['schema'] )
        {
            list( $options, $errors ) = expImportOptionSchema::validate( $handlers[$id]['schema'], $options );
            if ( $errors )
                throw new expImportValidationException( $errors, 'the options of the handler are not valid: ' . implode( '; ', $errors ) );
        }
        return new expImportContext( $run, $import, $options, $log, $queue );
    }

    /** @return expImportSourceInterface */
    protected function source( expImportDefinition $import, $file )
    {
        $registry = expImportRegistry::instance();
        $sources = $registry->sources();
        $type = (string)$import->attribute( 'source_type' );
        if ( !isset( $sources[$type] ) )
            throw new expImportException( "unknown source type '$type'" );
        $config = $import->sourceConfig();
        list( $values, $errors ) = expImportOptionSchema::validate( $sources[$type]['schema'], $config );
        if ( $errors )
            throw new expImportValidationException( $errors, 'the source is not configured correctly: ' . implode( '; ', $errors ) );
        $values['file'] = $file;
        return $registry->source( $type, $values );
    }

    /** @return iterable<expImportRecord> the records of a chunk, after a cursor */
    protected function records( expImportRun $run, expImportDefinition $import, expImportHandlerInterface $handler, expImportContext $context, array $cursor )
    {
        if ( $run->attribute( 'run_kind' ) === 'retry' )
            return $this->failedRecords( (int)$run->attribute( 'parent_run_id' ), isset( $cursor['offset'] ) ? (int)$cursor['offset'] : 0 );
        if ( $run->attribute( 'run_kind' ) === 'undo' )
            return $this->undoRecords( (int)$run->attribute( 'parent_run_id' ), isset( $cursor['offset'] ) ? (int)$cursor['offset'] : 0 );
        if ( $handler instanceof expImportRecordProviderInterface )
            return $handler->records( $context, $cursor );
        $file = (string)$run->attribute( 'source_ref' );
        if ( $file === '' || !is_file( $file ) )
            throw new expImportSourceException( 'the snapshot of the source is missing' );
        return $this->source( $import, $file )->records( $cursor );
    }

    /** @return expImportHandlerInterface the handler of a run: the undo handler for an undo run, else the import's */
    protected function handlerOf( expImportRun $run, expImportDefinition $import )
    {
        if ( $run->attribute( 'run_kind' ) === 'undo' )
            return new expImportUndoHandler();
        return expImportRegistry::instance()->handlerFor( $import );
    }

    /**
     * The rows of a run that created or updated an object, newest first (children before their parents), as records
     * of an undo run.
     * @return Generator
     */
    protected function undoRecords( $parentRunID, $offset )
    {
        $index = $offset;
        while ( true )
        {
            $rows = eZPersistentObject::fetchObjectList( expImportRunRow::definition(), null,
                                                         array( 'run_id' => (int)$parentRunID, 'outcome' => array( array( 'created', 'updated' ) ) ),
                                                         array( 'row_index' => 'desc', 'id' => 'desc' ), array( 'offset' => $index, 'length' => 100 ) );
            if ( !$rows )
                return;
            foreach ( $rows as $row )
            {
                $data = array( 'object_id' => (int)$row->attribute( 'object_id' ), 'outcome' => $row->attribute( 'outcome' ),
                               'version_before' => (int)$row->attribute( 'version_before' ), 'version_after' => (int)$row->attribute( 'version_after' ),
                               'remote_id' => $row->attribute( 'remote_id' ) );
                yield new expImportRecord( $row->attribute( 'row_ref' ), $index, $data, array( 'offset' => $index + 1 ) );
                $index++;
            }
        }
    }

    /** @return Generator the stored records of the failed rows of a run */
    protected function failedRecords( $parentRunID, $offset )
    {
        $index = $offset;
        while ( true )
        {
            $rows = expImportRunRow::fetchByRun( $parentRunID, array( 'outcome' => 'failed', 'data_json' => array( '!=', '' ) ), $index, 100 );
            if ( !$rows )
                return;
            foreach ( $rows as $row )
            {
                $record = $row->record();
                $index++;
                if ( $record )
                {
                    $record->cursor = array( 'offset' => $index );
                    yield $record;
                }
            }
        }
    }

    /** sqliimport.chunk */
    protected function chunk( expImportRun $run, expImportDefinition $import, expQueueJob $job, expQueueContext $queue, expImportLogger $log, array $payload )
    {
        $n = isset( $payload['n'] ) ? (int)$payload['n'] : 0;
        $cursor = isset( $payload['cursor'] ) && is_array( $payload['cursor'] ) ? $payload['cursor'] : array();
        $checkpoint = $queue->checkpoint();
        if ( $checkpoint && isset( $checkpoint['cursor'] ) && is_array( $checkpoint['cursor'] ) )
        {
            $cursor = $checkpoint['cursor'];
            $log->notice( 'chunk ' . ( $n + 1 ) . ' continues after its last checkpoint (attempt ' . $queue->attempt() . ')' );
        }
        $handler = $this->handlerOf( $run, $import );
        $context = $this->context( $run, $import, $log, $queue );
        $chunkSize = max( 1, (int)$context->behaviour['chunk_size'] );
        if ( $handler instanceof expImportRecordProviderInterface && !$handler->resumable() )
            $chunkSize = PHP_INT_MAX;
        $max = $this->maxRows( $run, $import );
        $restore = $this->deferCaches();
        $handler->open( $context );
        $processed = 0;
        $more = false;
        $counts = array();
        $read = 0;
        $last = $cursor;
        try
        {
            foreach ( $this->records( $run, $import, $handler, $context, $cursor ) as $record )
            {
                if ( $processed >= $chunkSize )
                {
                    $more = true;
                    break;
                }
                if ( $max && $record->index >= $max && $run->attribute( 'run_kind' ) !== 'retry' )
                {
                    $log->notice( "the row limit of $max is reached" );
                    break;
                }
                $context->throwIfCancelled();
                $processed++;
                $last = $record->cursor;
                if ( expImportRunRow::exists( $run->attribute( 'id' ), $record->ref ) )
                    continue;
                $read++;
                $result = $this->processRecord( $handler, $record, $context );
                $this->storeRow( $run, $record, $result, $log );
                $counts[$result->outcome] = ( isset( $counts[$result->outcome] ) ? $counts[$result->outcome] : 0 ) + 1;
                if ( $processed % 20 === 0 )
                {
                    $run->addCounts( $counts, $read );
                    $counts = array();
                    $read = 0;
                    $queue->saveCheckpoint( array( 'cursor' => $last ) );
                    $queue->progress( $run->doneCount(), (int)$run->attribute( 'total_count' ) ?: null );
                    $log->flush();
                }
            }
        }
        finally
        {
            $handler->close( $context );
            $run->addCounts( $counts, $read );
            $this->restoreCaches( $restore );
            self::resetKernelCaches();
            $peak = memory_get_peak_usage( true );
            if ( $peak > (int)$run->attribute( 'memory_peak' ) )
                expImport::db()->query( 'UPDATE sqliimport_run SET memory_peak = ' . (int)$peak . ', cursor_json = \'' . expImport::db()->escapeString( expImport::json( $last ) ) . '\' WHERE id = ' . (int)$run->attribute( 'id' ) );
        }
        $log->debug( 'chunk ' . ( $n + 1 ) . ": $processed records, memory peak " . round( memory_get_peak_usage( true ) / 1048576, 1 ) . ' MB' );
        $run->reload();
        if ( $run->isFinal() )
            return expQueueResult::done( array( 'processed' => $processed ), 'the run ended meanwhile' );
        $this->queueNext( $run, $import, $job, $more ? self::chunkPayload( $run, $n + 1, $last ) : null );
        return expQueueResult::done( array( 'processed' => $processed, 'more' => $more ) );
    }

    /** @return expImportRowResult */
    protected function processRecord( expImportHandlerInterface $handler, expImportRecord $record, expImportContext $context )
    {
        if ( $record->sourceError() !== '' )
            return expImportRowResult::failed( $record->sourceError() );
        try
        {
            $result = $handler->process( $record, $context );
            if ( !$result instanceof expImportRowResult || !$result->isValid() )
                $result = expImportRowResult::failed( 'the handler returned no row result' );
        }
        catch ( expQueueCancelledException $e )
        {
            throw $e;
        }
        catch ( Throwable $e )
        {
            $db = expImport::db();
            // a failure inside a transaction of the content API must not leave it open for the next record
            while ( method_exists( $db, 'transactionCounter' ) && $db->transactionCounter() > 0 )
                $db->rollback();
            $result = expImportRowResult::failed( $e->getMessage() );
        }
        return $result;
    }

    /** Stores a row of the run (and the record itself for a failed row, for "retry failed rows"). */
    protected function storeRow( expImportRun $run, expImportRecord $record, expImportRowResult $result, expImportLogger $log )
    {
        $data = '';
        if ( $result->outcome === expImportRowResult::FAILED )
        {
            $data = expImport::json( $record->toArray() );
            if ( strlen( $data ) > 1048576 )
                $data = '';
            $log->error( $result->message, $record->ref );
        }
        elseif ( $result->message !== '' && $result->outcome === expImportRowResult::SKIPPED )
            $log->info( $result->message, $record->ref );
        if ( $result->written && $result->outcome !== expImportRowResult::FAILED )
        {
            $data = expImport::json( array( 'written' => $result->written ) );
            if ( strlen( $data ) > 262144 )
                $data = '';
        }
        $nodes = $result->nodesAdded ? array( 'added' => $result->nodesAdded ) : array();
        if ( $result->diff )
            $nodes['diff'] = $result->diff;
        $row = new expImportRunRow( array(
            'run_id' => (int)$run->attribute( 'id' ),
            'row_ref' => substr( $record->ref, 0, 255 ),
            'row_index' => $record->index,
            'status' => $result->outcome === expImportRowResult::FAILED ? 'error' : 'ok',
            'outcome' => $result->outcome,
            'object_id' => $result->objectID,
            'remote_id' => substr( $result->remoteID, 0, 100 ),
            'version_before' => $result->versionBefore,
            'version_after' => $result->versionAfter,
            'nodes_json' => $nodes ? expImport::json( $nodes ) : '',
            'message' => mb_substr( $result->message, 0, 2000 ),
            'data_json' => $data,
            'created' => expImport::now(),
        ) );
        $row->store();
    }

    /**
     * View cache and indexing are deferred while a chunk publishes (as in 1.x, sqliimport.ini [ImportSettings]
     * ViewCaching and ObjectIndexing), and caught up by the finish job.
     * @return array what to restore
     */
    protected function deferCaches()
    {
        $site = eZINI::instance( 'site.ini' );
        $restore = array(
            'ViewCaching' => $site->variable( 'ContentSettings', 'ViewCaching' ),
            'DelayedIndexing' => $site->hasVariable( 'SearchSettings', 'DelayedIndexing' ) ? $site->variable( 'SearchSettings', 'DelayedIndexing' ) : 'disabled',
        );
        if ( expImport::setting( 'ImportSettings', 'ViewCaching', 'disabled' ) === 'disabled' )
            $site->setVariable( 'ContentSettings', 'ViewCaching', 'disabled' );
        if ( expImport::setting( 'ImportSettings', 'ObjectIndexing', 'disabled' ) === 'disabled' )
            $site->setVariable( 'SearchSettings', 'DelayedIndexing', 'enabled' );
        return $restore;
    }

    protected function restoreCaches( array $restore )
    {
        $site = eZINI::instance( 'site.ini' );
        $site->setVariable( 'ContentSettings', 'ViewCaching', $restore['ViewCaching'] );
        $site->setVariable( 'SearchSettings', 'DelayedIndexing', $restore['DelayedIndexing'] );
    }

    /** Empties the kernel's in-memory object caches (between chunks). */
    public static function resetKernelCaches()
    {
        eZContentObject::clearCache();
        if ( class_exists( 'eZContentClass' ) && method_exists( 'eZContentClass', 'expireCache' ) )
            eZContentClass::expireCache();
        unset( $GLOBALS['eZContentObjectDataMapCache'], $GLOBALS['eZContentClassAttributeCache'] );
        if ( function_exists( 'gc_collect_cycles' ) )
            gc_collect_cycles();
    }

    /** sqliimport.finish: caches, indexing, status, summary, notifications. */
    protected function finish( expImportRun $run, expImportDefinition $import, expImportLogger $log )
    {
        $runID = (int)$run->attribute( 'id' );
        $ids = array();
        if ( $run->attribute( 'run_kind' ) !== 'dry_run' )
        {
            foreach ( (array)expImport::db()->arrayQuery( "SELECT DISTINCT object_id FROM sqliimport_run_row WHERE run_id = $runID AND outcome IN ('created', 'updated', 'undone') AND object_id > 0" ) as $row )
                $ids[] = (int)$row['object_id'];
        }
        $cacheNote = $this->clearCaches( $ids );
        $indexed = $this->indexObjects( $ids );
        $run->reload();
        $status = (int)$run->attribute( 'failed_count' ) > 0 ? expImportRun::STATUS_COMPLETED_WITH_ERRORS : expImportRun::STATUS_COMPLETED;
        $summary = $run->summaryArray();
        $summary['caches'] = $cacheNote;
        $summary['indexed'] = $indexed;
        $run->moveTo( $status, array( 'finished_at' => expImport::now(), 'summary' => expImport::json( $summary ) ) );
        $log->info( sprintf( 'run ended %s: %d created, %d updated, %d unchanged, %d skipped, %d failed%s in %ds',
                             $status, $run->attribute( 'created_count' ), $run->attribute( 'updated_count' ), $run->attribute( 'unchanged_count' ),
                             $run->attribute( 'skipped_count' ), $run->attribute( 'failed_count' ),
                             (int)$run->attribute( 'processed_count' ) ? ', ' . (int)$run->attribute( 'processed_count' ) . ' processed' : '', $run->duration() ) );
        expImportRunner::syncLegacyItem( $run );
        if ( (int)$run->attribute( 'schedule_id' ) )
            expImport::db()->query( 'UPDATE sqliimport_schedule SET last_run = ' . $runID . ' WHERE id = ' . (int)$run->attribute( 'schedule_id' ) );
        expImport::audit( 'run.finish', array( 'id' => $runID, 'status' => $status, 'failed' => (int)$run->attribute( 'failed_count' ) ) );
        // a notice for the starter, mails of the import and of its schedule, by the import's settings
        expImportNotifier::runEnded( $run );
        return expQueueResult::done( array( 'status' => $status ) );
    }

    /**
     * Clears the view caches of the changed objects: one by one, or the whole content view cache at once when there
     * are more than site.ini [ContentSettings] CacheThreshold of them (upstream PR #27).
     *
     * @param int[] $ids
     * @return string what was done
     */
    protected function clearCaches( array $ids )
    {
        if ( !$ids )
            return 'none';
        $site = eZINI::instance( 'site.ini' );
        $threshold = $site->hasVariable( 'ContentSettings', 'CacheThreshold' ) ? (int)$site->variable( 'ContentSettings', 'CacheThreshold' ) : 0;
        if ( $threshold > 0 && count( $ids ) > $threshold )
        {
            eZContentCacheManager::clearAllContentCache();
            return 'all (' . count( $ids ) . ' objects, more than CacheThreshold ' . $threshold . ')';
        }
        foreach ( $ids as $id )
            eZContentCacheManager::clearContentCacheIfNeeded( $id );
        if ( class_exists( 'eZStaticCache' ) && $site->variable( 'ContentSettings', 'StaticCache' ) === 'enabled' )
        {
            $handler = $site->hasVariable( 'ContentSettings', 'StaticCacheHandler' ) ? $site->variable( 'ContentSettings', 'StaticCacheHandler' ) : 'eZStaticCache';
            if ( class_exists( $handler ) && method_exists( $handler, 'executeActions' ) )
                call_user_func( array( $handler, 'executeActions' ) );
        }
        return count( $ids ) . ' objects';
    }

    /**
     * Indexes the changed objects the chunks left to the delayed indexing, and removes their pending actions.
     *
     * @param int[] $ids
     * @return int
     */
    protected function indexObjects( array $ids )
    {
        if ( !$ids || expImport::setting( 'ImportSettings', 'ObjectIndexing', 'disabled' ) !== 'disabled' )
            return 0;
        $n = 0;
        $db = expImport::db();
        foreach ( array_chunk( $ids, 100 ) as $chunk )
        {
            foreach ( $chunk as $id )
            {
                $object = eZContentObject::fetch( $id );
                if ( $object instanceof eZContentObject )
                {
                    eZSearch::addObject( $object, true );
                    $n++;
                }
            }
            $db->query( "DELETE FROM ezpending_actions WHERE action = 'index_object' AND param IN ('" . implode( "','", array_map( 'intval', $chunk ) ) . "')" );
            eZContentObject::clearCache();
        }
        return $n;
    }
}
