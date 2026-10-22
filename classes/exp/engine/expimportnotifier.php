<?php
/**
 * File containing the expImportNotifier and expImportNotice classes.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Who hears of a run that ended, by the import's settings (Processing step, behaviour):
 *
 *   notify_when     never, failed (the run failed), errors (failed or completed with errors; the default) or always
 *   notify_starter  the user who started the run gets a notice in the administration (the default: yes)
 *   notify_emails   addresses that get a mail
 *
 * and, as in 2.0.0-alpha.1, the addresses of a schedule when its run fails. A notice is a row of sqliimport_notice:
 * the dashboard of Imports shows a user's new ones, the run's page marks its notices as read, and the template fetch
 * function fetch( 'sqliimport', 'notices' ) gives them to any page (a bell in the header).
 * Dry runs and undo runs notify only their starter, never by mail.
 */
class expImportNotifier
{
    const WHEN = array( 'never', 'failed', 'errors', 'always' );

    /**
     * Whether a run's end is worth telling, for a setting.
     *
     * @param string $when
     * @param string $status the run's final status
     * @return bool
     */
    public static function shouldNotify( $when, $status )
    {
        switch ( (string)$when )
        {
            case 'always':
                return in_array( $status, array( 'completed', 'completed_with_errors', 'failed', 'cancelled' ), true );
            case 'failed':
                return $status === 'failed';
            case 'never':
                return false;
        }
        return in_array( $status, array( 'failed', 'completed_with_errors' ), true );
    }

    /** @return string the kind of a notice for a status: failed, errors, completed or cancelled */
    public static function kindOf( $status )
    {
        $map = array( 'failed' => 'failed', 'completed_with_errors' => 'errors', 'completed' => 'completed', 'cancelled' => 'cancelled' );
        return isset( $map[$status] ) ? $map[$status] : (string)$status;
    }

    /**
     * Tells of a run that ended: a notice for its starter, a mail to the import's addresses, and the schedule's mail
     * when a scheduled run failed. Never throws: a run's end must not fail because a mail could not be sent.
     *
     * @param expImportRun $run
     */
    public static function runEnded( expImportRun $run )
    {
        try
        {
            $import = $run->import();
            $status = (string)$run->attribute( 'status' );
            $behaviour = $import ? $import->behaviour() : array();
            $when = isset( $behaviour['notify_when'] ) && in_array( $behaviour['notify_when'], self::WHEN, true ) ? $behaviour['notify_when'] : 'errors';
            $primary = !in_array( $run->attribute( 'run_kind' ), array( 'dry_run', 'undo' ), true );
            if ( self::shouldNotify( $when, $status ) )
            {
                if ( !isset( $behaviour['notify_starter'] ) || $behaviour['notify_starter'] )
                    self::addNotice( $run, (int)$run->attribute( 'user_id' ) );
                if ( $primary && !empty( $behaviour['notify_emails'] ) )
                    self::mail( $run, (array)$behaviour['notify_emails'] );
            }
            if ( $primary && in_array( $status, array( 'failed', 'completed_with_errors' ), true ) )
                expImportScheduler::notifyFailure( $run );
        }
        catch ( Exception $e )
        {
            eZDebug::writeWarning( 'run #' . $run->attribute( 'id' ) . ': the notification failed: ' . $e->getMessage(), __METHOD__ );
        }
    }

    /** Adds a notice of a run for a user (once per run and user). */
    public static function addNotice( expImportRun $run, $userID )
    {
        if ( $userID < 1 || !expImportSchema::installed() )
            return;
        $existing = eZPersistentObject::fetchObject( expImportNotice::definition(), null, array( 'run_id' => (int)$run->attribute( 'id' ), 'user_id' => (int)$userID ) );
        if ( $existing )
            return;
        $notice = new expImportNotice( array( 'user_id' => (int)$userID, 'run_id' => (int)$run->attribute( 'id' ), 'import_id' => (int)$run->attribute( 'import_id' ),
                                              'kind' => self::kindOf( (string)$run->attribute( 'status' ) ), 'created' => expImport::now(), 'seen' => 0 ) );
        $notice->store();
    }

    /**
     * The mail of a run's end.
     *
     * @param expImportRun $run
     * @param string[] $addresses
     * @return bool whether a mail was handed to the transport
     */
    public static function mail( expImportRun $run, array $addresses )
    {
        if ( !class_exists( 'eZMail' ) )
            return false;
        $import = $run->import();
        $site = eZINI::instance( 'site.ini' );
        $mail = new eZMail();
        $mail->setSender( $site->variable( 'MailSettings', 'EmailSender' ) ?: $site->variable( 'MailSettings', 'AdminEmail' ) );
        foreach ( $addresses as $address )
            if ( eZMail::validate( trim( (string)$address ) ) )
                $mail->addReceiver( trim( (string)$address ) );
        if ( !$mail->receiverElements() )
            return false;
        list( $subject, $body ) = self::message( $run, $import, $site->variable( 'SiteSettings', 'SiteName' ), self::runUrl( $run ) );
        $mail->setSubject( $subject );
        $mail->setBody( $body );
        return (bool)eZMailTransport::send( $mail );
    }

    /**
     * The subject and text of a run's mail.
     *
     * @param expImportRun $run
     * @param expImportDefinition|null $import
     * @param string $siteName
     * @param string $url the run's page
     * @return array ( subject, body )
     */
    public static function message( expImportRun $run, $import, $siteName, $url )
    {
        $name = $import ? (string)$import->attribute( 'name' ) : '?';
        $status = expImportAdmin::label( 'status', (string)$run->attribute( 'status' ) );
        $subject = '[' . $siteName . '] ' . $name . ': run #' . (int)$run->attribute( 'id' ) . ' ' . $status;
        $body = sprintf( "Run #%d of the import \"%s\" ended: %s.\n\nCreated %d, updated %d, unchanged %d, skipped %d, failed %d.\n",
                         $run->attribute( 'id' ), $name, $status, $run->attribute( 'created_count' ), $run->attribute( 'updated_count' ),
                         $run->attribute( 'unchanged_count' ), $run->attribute( 'skipped_count' ), $run->attribute( 'failed_count' ) );
        $errors = expImportRunLog::fetchByRun( $run->attribute( 'id' ), 0, array( 'error' ), 0, 5 );
        if ( $errors )
        {
            $body .= "\nThe first errors:\n";
            foreach ( $errors as $line )
                $body .= ' - ' . ( $line->attribute( 'row_ref' ) !== '' ? $line->attribute( 'row_ref' ) . ': ' : '' ) . $line->attribute( 'message' ) . "\n";
        }
        $body .= "\nThe run, its records and log: " . $url . "\n";
        return array( $subject, $body );
    }

    /** @return string the address of a run's page in the administration */
    public static function runUrl( expImportRun $run )
    {
        $ini = eZINI::instance( 'sqliimport.ini' );
        $base = $ini->hasVariable( 'AdminSettings', 'AdminURL' ) ? rtrim( (string)$ini->variable( 'AdminSettings', 'AdminURL' ), '/' ) : '';
        $path = '/sqliimport/run/' . (int)$run->attribute( 'id' );
        return $base !== '' ? $base . $path : $path;
    }

    /**
     * A user's notices, newest first, with what the page shows.
     *
     * @param int $userID
     * @param bool $unseenOnly
     * @param int $limit
     * @return array list of array( id, run_id, import_id, import, kind, created, seen, url )
     */
    public static function notices( $userID, $unseenOnly = true, $limit = 10 )
    {
        if ( !expImportSchema::installed() || $userID < 1 )
            return array();
        $conds = array( 'user_id' => (int)$userID );
        if ( $unseenOnly )
            $conds['seen'] = 0;
        $out = array();
        foreach ( eZPersistentObject::fetchObjectList( expImportNotice::definition(), null, $conds, array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => (int)$limit ) ) as $n )
        {
            $import = expImportDefinition::fetch( (int)$n->attribute( 'import_id' ) );
            $out[] = array( 'id' => (int)$n->attribute( 'id' ), 'run_id' => (int)$n->attribute( 'run_id' ), 'import_id' => (int)$n->attribute( 'import_id' ),
                            'import' => $import ? (string)$import->attribute( 'name' ) : '', 'kind' => (string)$n->attribute( 'kind' ),
                            'created' => (int)$n->attribute( 'created' ), 'seen' => (bool)$n->attribute( 'seen' ),
                            'url' => '/sqliimport/run/' . (int)$n->attribute( 'run_id' ) );
        }
        return $out;
    }

    /** @return int the number of a user's unseen notices */
    public static function unseenCount( $userID )
    {
        if ( !expImportSchema::installed() || $userID < 1 )
            return 0;
        return (int)eZPersistentObject::count( expImportNotice::definition(), array( 'user_id' => (int)$userID, 'seen' => 0 ) );
    }

    /**
     * Marks notices of a user as read: those of one run, or all.
     *
     * @param int $userID
     * @param int $runID 0: all
     */
    public static function markSeen( $userID, $runID = 0 )
    {
        if ( !expImportSchema::installed() || $userID < 1 )
            return;
        expImport::db()->query( 'UPDATE sqliimport_notice SET seen = 1 WHERE user_id = ' . (int)$userID . ' AND seen = 0'
                                . ( $runID ? ' AND run_id = ' . (int)$runID : '' ) );
    }

    /** Removes the notices of runs (when runs are purged). */
    public static function removeForRun( $runID )
    {
        expImport::db()->query( 'DELETE FROM sqliimport_notice WHERE run_id = ' . (int)$runID );
    }
}

/** A notice of a run for a user (sqliimport_notice). */
class expImportNotice extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_notice' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportNotice',
            'name' => 'sqliimport_notice',
        );
    }
}
