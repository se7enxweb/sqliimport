<?php
/**
 * File containing the expImportScheduler class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The work of the cron part "sqliimport" (every minute): start the runs of the schedules that are due, end runs
 * whose jobs ended without them, hand the pending items of 1.x to the 2.x engine, and purge old runs. All heavy work
 * happens in the job queue's workers; tick() only queues.
 */
class expImportScheduler
{
    /**
     * One round.
     *
     * @param callable|null $out receives a line of progress
     * @return array counters: started, skipped, ended, legacy, purged, undo_expired
     */
    public static function tick( ?callable $out = null )
    {
        $say = function ( $line ) use ( $out )
        {
            if ( $out )
                $out( $line );
        };
        $stats = array( 'started' => 0, 'skipped' => 0, 'ended' => 0, 'legacy' => 0, 'purged' => 0, 'undo_expired' => 0 );
        if ( !expImportSchema::installed() )
        {
            $say( 'the tables of 7x SQLI Import are missing: ./console ext:sqliimport:install-schema' );
            return $stats;
        }
        if ( !expImport::queueReady() )
        {
            $say( 'the job queue (expqueue) is not installed: nothing is started' );
            return $stats;
        }
        $stats['ended'] = expImportRunner::reconcile();
        $now = expImport::now();
        foreach ( expImportSchedule::fetchDue( $now ) as $schedule )
        {
            $import = $schedule->import();
            $late = $now - (int)$schedule->attribute( 'next_run' );
            $schedule->computeNext( $now );
            $schedule->setAttribute( 'modified', $now );
            $schedule->store();
            if ( !$import )
            {
                $schedule->setAttribute( 'is_active', 0 );
                $schedule->store( array( 'is_active' ) );
                continue;
            }
            if ( $schedule->attribute( 'catch_up' ) === 'skip' && $late > 600 )
            {
                $say( 'schedule #' . $schedule->attribute( 'id' ) . ': missed by ' . round( $late / 60 ) . ' minutes, waits for its next time' );
                $stats['skipped']++;
                continue;
            }
            try
            {
                $run = expImportRunner::start( $import, array( 'trigger' => 'schedule', 'schedule_id' => (int)$schedule->attribute( 'id' ),
                                                               'user_id' => (int)$schedule->attribute( 'creator_id' ) ) );
                $say( 'schedule #' . $schedule->attribute( 'id' ) . ': run #' . $run->attribute( 'id' ) . ' of ' . $import->attribute( 'identifier' ) . ' queued' );
                $stats['started']++;
            }
            catch ( expImportException $e )
            {
                $say( 'schedule #' . $schedule->attribute( 'id' ) . ': ' . $e->getMessage() );
                $stats['skipped']++;
            }
        }
        $stats['legacy'] = self::legacyItems( $say );
        $stats['purged'] = self::purge();
        $stats['undo_expired'] = expImportRunner::expireUndo();
        return $stats;
    }

    /**
     * The import of a 1.x handler: one per handler (identifier legacy-<handler>), made on first use.
     *
     * @param string $handler
     * @return expImportDefinition
     */
    public static function legacyDefinition( $handler )
    {
        $identifier = expImportDefinition::LEGACY_PREFIX . preg_replace( '/[^a-z0-9_-]/i', '_', (string)$handler );
        $import = expImportDefinition::fetchByIdentifier( $identifier );
        if ( $import )
            return $import;
        $available = expImportLegacyHandlerAdapter::available();
        $import = expImportDefinition::create( array(
            'identifier' => $identifier,
            'name' => isset( $available[$handler] ) ? $available[$handler]['name'] : (string)$handler,
            'description' => 'The 1.x handler ' . $handler . ' (sqliimport.ini), run by the 2.x engine.',
            'source_type' => 'legacy',
            'handler' => (string)$handler,
        ) );
        $import->store();
        expImport::audit( 'import.create', array( 'identifier' => $identifier, 'legacy' => true ) );
        return $import;
    }

    /**
     * The pending imports of 1.x (sqliimport/addimport, and the due 1.x schedules) go to the 2.x engine; an item a
     * user interrupted in the 1.x list cancels its run.
     *
     * @param callable $say
     * @return int runs started
     */
    public static function legacyItems( callable $say )
    {
        $n = 0;
        $now = expImport::now();
        // 1.x schedules that were not migrated yet
        $due = SQLIScheduledImport::fetchList( 0, null, array( 'is_active' => 1, 'next' => array( '<=', $now ) ) );
        foreach ( (array)$due as $scheduled )
        {
            if ( expImportDefinition::fetchCount( array( 'legacy_scheduled_id' => (int)$scheduled->attribute( 'id' ) ) ) )
                continue;
            SQLIImportItem::fromScheduledImport( $scheduled );
            $scheduled->updateNextImport();
        }
        foreach ( (array)SQLIImportItem::fetchPendingList() as $item )
        {
            $handler = (string)$item->attribute( 'handler' );
            $options = array();
            $legacyOptions = $item->attribute( 'options' );
            if ( $legacyOptions instanceof SQLIImportHandlerOptions )
                foreach ( $legacyOptions->toArray() as $key => $value )
                    $options[$key] = $value;
            try
            {
                $import = self::legacyDefinition( $handler );
                $run = expImportRunner::start( $import, array( 'trigger' => 'legacy', 'legacy_item_id' => (int)$item->attribute( 'id' ),
                                                               'user_id' => (int)$item->attribute( 'user_id' ), 'options' => $options ) );
                $item->setAttribute( 'status', SQLIImportItem::STATUS_RUNNING );
                $item->store( array( 'status' ) );
                $say( "1.x import #{$item->attribute( 'id' )} ($handler): run #" . $run->attribute( 'id' ) . ' queued' );
                $n++;
            }
            catch ( expImportException $e )
            {
                // the handler's import runs already: the item waits for the next round
                $say( "1.x import #{$item->attribute( 'id' )} ($handler): " . $e->getMessage() );
            }
        }
        // interrupted in the 1.x list
        foreach ( (array)SQLIImportItem::fetchList( 0, 50, array( 'status' => SQLIImportItem::STATUS_INTERRUPTED ) ) as $item )
        {
            foreach ( expImportRun::fetchList( array( 'legacy_item_id' => (int)$item->attribute( 'id' ) ), 0, 1 ) as $run )
                if ( !$run->isFinal() )
                    expImportRunner::cancel( $run, 'interrupted in the 1.x import list' );
        }
        return $n;
    }

    /**
     * Removes ended runs older than [RunSettings] KeepDays with their rows, log and files.
     *
     * @param int|null $days
     * @param bool $dryRun only count
     * @return int runs removed
     */
    public static function purge( $days = null, $dryRun = false )
    {
        $days = $days !== null ? (int)$days : (int)expImport::setting( 'RunSettings', 'KeepDays', 90 );
        if ( $days <= 0 )
            return 0;
        $before = expImport::now() - $days * 86400;
        $n = 0;
        $runs = expImportRun::fetchList( array( 'status' => array( expImportRun::finalStatuses() ), 'finished_at' => array( '<', $before ) ), 0, 500 );
        foreach ( $runs as $run )
        {
            if ( (int)$run->attribute( 'finished_at' ) <= 0 )
                continue;
            $n++;
            if ( $dryRun )
                continue;
            expImportSourceFiles::removeRunFiles( $run->attribute( 'id' ) );
            $run->removeAll();
        }
        return $n;
    }

    /**
     * Tells the notify list of the run's schedule that a run failed or ended with failed rows.
     *
     * @param expImportRun $run
     */
    public static function notifyFailure( expImportRun $run )
    {
        $schedule = (int)$run->attribute( 'schedule_id' ) ? expImportSchedule::fetch( $run->attribute( 'schedule_id' ) ) : null;
        if ( !$schedule || !$schedule->notify() || !class_exists( 'eZMail' ) )
            return;
        $import = $run->import();
        $site = eZINI::instance( 'site.ini' );
        $mail = new eZMail();
        $sender = $site->variable( 'MailSettings', 'EmailSender' ) ?: $site->variable( 'MailSettings', 'AdminEmail' );
        $mail->setSender( $sender );
        foreach ( $schedule->notify() as $address )
            if ( eZMail::validate( $address ) )
                $mail->addReceiver( $address );
        if ( !$mail->receiverElements() )
            return;
        $mail->setSubject( '[' . $site->variable( 'SiteSettings', 'SiteName' ) . '] import "' . ( $import ? $import->attribute( 'name' ) : '?' )
                           . '": run #' . $run->attribute( 'id' ) . ' ' . $run->attribute( 'status' ) );
        $mail->setBody( sprintf( "Run #%d of the import \"%s\" ended %s.\n\nCreated %d, updated %d, unchanged %d, skipped %d, failed %d.\n\nThe log is on the run's page in the administration (Imports tab).\n",
                                 $run->attribute( 'id' ), $import ? $import->attribute( 'name' ) : '?', $run->attribute( 'status' ),
                                 $run->attribute( 'created_count' ), $run->attribute( 'updated_count' ), $run->attribute( 'unchanged_count' ),
                                 $run->attribute( 'skipped_count' ), $run->attribute( 'failed_count' ) ) );
        eZMailTransport::send( $mail );
    }
}
