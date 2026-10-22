<?php
/**
 * File containing the expImportLegacy class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the 2.x pages do with the import requests (sqliimport_item, SQLIImportItem) and the scheduled imports
 * (sqliimport_scheduled, SQLIScheduledImport) of 1.x, so no page of 1.x is needed any more:
 *
 *   the dashboard (sqliimport/list)   the 1.x import requests: request an import of a 1.x handler, cancel a pending
 *                                     request, interrupt a running one, purge the history, follow its run
 *   sqliimport/schedules              the 1.x scheduled imports: add, edit (frequency, options, label, start),
 *                                     run now, enable or disable, delete
 *
 * The addresses of the 1.x pages (sqliimport/items, scheduledlist, addimport, addscheduled, alterimport,
 * removescheduled, purgelist) stay: a GET goes to the 2.x page (redirectFor()), a POST of the 1.x form does what it
 * did and then goes there. Every change is a POST checked by ezformtoken; who may do what is expImportAdmin::can()
 * with the 1.x handler as the SQLIImport_Type limitation (manageimports of 1.x counts as manage).
 */
class expImportLegacy
{
    /** The frequencies of a 1.x scheduled import (SQLIScheduledImport::FREQUENCY_*) */
    const FREQUENCIES = array( 'none', 'hourly', 'daily', 'weekly', 'monthly', 'manual' );

    /** The shortest interval of the frequency "manual", in minutes (as in 1.x) */
    const MIN_MANUAL_MINUTES = 5;

    /** The anchor of the 1.x import requests on the dashboard */
    const LIST_ANCHOR = 'a4-sqliimport-legacy';

    /**
     * The 2.x page an address of a 1.x page goes to (a GET of a 1.x page, or the page after a POST of one).
     * Pure: no database; the caller passes the import a 1.x scheduled import was migrated to, if any.
     *
     * @param string $view the 1.x view (items, scheduledlist, addimport, addscheduled, alterimport, removescheduled,
     *                     purgelist)
     * @param array $params the ordered parameters of the view (ScheduledImportID, ImportID, Action)
     * @param array $userParams the unordered parameters, e.g. offset
     * @param int $migratedImportID the 2.x import of a migrated 1.x scheduled import (addscheduled), 0 if none
     * @return string an address of the module, starting with /sqliimport/
     */
    public static function redirectFor( $view, array $params = array(), array $userParams = array(), $migratedImportID = 0 )
    {
        $list = '/sqliimport/list';
        $offset = isset( $userParams['offset'] ) ? max( 0, (int)$userParams['offset'] ) : 0;
        switch ( (string)$view )
        {
            case 'items':
                return $list . ( $offset > 0 ? '/(legacyoffset)/' . $offset : '' ) . '#' . self::LIST_ANCHOR;
            case 'addimport':
                return $list . '#' . self::LIST_ANCHOR . '-request';
            case 'alterimport':
            case 'purgelist':
                return $list . '#' . self::LIST_ANCHOR;
            case 'scheduledlist':
            case 'removescheduled':
                return '/sqliimport/schedules#' . self::LIST_ANCHOR;
            case 'addscheduled':
                $id = isset( $params['ScheduledImportID'] ) ? (int)$params['ScheduledImportID'] : 0;
                if ( $id > 0 && (int)$migratedImportID > 0 )
                    return '/sqliimport/edit/' . (int)$migratedImportID . '/(step)/schedule';
                return '/sqliimport/schedules/(legacy)/' . ( $id > 0 ? $id : 'new' );
        }
        return $list;
    }

    /**
     * The time of the first run of a 1.x scheduled import from the form (date YYYY-MM-DD, hour, minute), as 1.x read
     * it. Pure.
     *
     * @return int|null the timestamp, null when the date is not a real date or the time is out of range
     */
    public static function startTime( $date, $hour, $minute )
    {
        if ( !preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim( (string)$date ), $m ) || !checkdate( (int)$m[2], (int)$m[3], (int)$m[1] ) )
            return null;
        if ( !is_numeric( $hour ) || !is_numeric( $minute ) || (int)$hour < 0 || (int)$hour > 23 || (int)$minute < 0 || (int)$minute > 59 )
            return null;
        $time = mktime( (int)$hour, (int)$minute, 0, (int)$m[2], (int)$m[3], (int)$m[1] );
        return $time === false ? null : $time;
    }

    /**
     * What is wrong with a frequency of the form, as a key of messages(). Pure.
     *
     * @param string $frequency one of FREQUENCIES
     * @param mixed $manualMinutes the minutes of the frequency "manual"
     * @return string '' when it is fine, else frequency or manual
     */
    public static function frequencyProblem( $frequency, $manualMinutes )
    {
        if ( !in_array( (string)$frequency, self::FREQUENCIES, true ) )
            return 'frequency';
        if ( $frequency === 'manual' && (int)$manualMinutes < self::MIN_MANUAL_MINUTES )
            return 'manual';
        return '';
    }

    /** @return string the badge modifier of a 1.x item status (SQLIImportItem::STATUS_*). Pure. */
    public static function itemStatusKind( $status )
    {
        $map = array( 0 => '', 1 => 'is-accent', 2 => 'is-bad', 3 => 'is-ok', 4 => '', 5 => 'is-warn' );
        return isset( $map[(int)$status] ) ? $map[(int)$status] : '';
    }

    /** @return string the code of a 1.x item status (pending, running, failed, completed, cancelled, interrupted). Pure. */
    public static function itemStatusCode( $status )
    {
        $map = array( 0 => 'pending', 1 => 'running', 2 => 'failed', 3 => 'completed', 4 => 'cancelled', 5 => 'interrupted' );
        return isset( $map[(int)$status] ) ? $map[(int)$status] : 'unknown';
    }

    /**
     * The 1.x handlers of sqliimport.ini the current user may use for a function (run or manage): id => name.
     * @return array
     */
    public static function handlers( $function = 'run' )
    {
        $out = array();
        foreach ( expImportLegacyHandlerAdapter::available() as $id => $info )
            if ( $info['enabled'] && expImportAdmin::can( $function, (string)$id ) )
                $out[$id] = $info['name'];
        return $out;
    }

    /** @return string the name of a 1.x handler (sqliimport.ini Name), or its id */
    public static function handlerName( $handler )
    {
        $available = expImportLegacyHandlerAdapter::available();
        return isset( $available[$handler] ) ? (string)$available[$handler]['name'] : (string)$handler;
    }

    /** @return string the text of 1.x options (one name=value per line) */
    protected static function optionsText( $options )
    {
        return $options instanceof SQLIImportHandlerOptions ? trim( (string)$options->toText() ) : '';
    }

    /**
     * Requests an immediate import of a 1.x handler (a pending SQLIImportItem; the cron part sqliimport starts its
     * run in the 2.x engine). The caller checks the access.
     *
     * @param string $handler
     * @param string $optionsText one name=value per line
     * @return SQLIImportItem
     */
    public static function requestImport( $handler, $optionsText = '' )
    {
        $handler = (string)$handler;
        $available = expImportLegacyHandlerAdapter::available();
        if ( !isset( $available[$handler] ) || !$available[$handler]['enabled'] )
            throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The 1.x handler "%handler" is not available.', null, array( '%handler' => $handler ) ) );
        $item = new SQLIImportItem( array( 'handler' => $handler, 'user_id' => eZUser::currentUserID() ) );
        if ( trim( (string)$optionsText ) !== '' )
            $item->setAttribute( 'options', SQLIImportHandlerOptions::fromText( (string)$optionsText ) );
        $item->store();
        SQLIImportLogger::logNotice( 'User #' . eZUser::currentUserID() . ' requested an immediate import of the handler ' . $handler . ' (item #' . $item->attribute( 'id' ) . ')', false );
        return $item;
    }

    /**
     * Cancels a pending 1.x import request or interrupts a running one (as sqliimport/alterimport of 1.x), and cancels
     * the 2.x run it started, if any. The caller checks the access.
     *
     * @param string $action cancel or interrupt
     * @return SQLIImportItem
     */
    public static function alterItem( SQLIImportItem $item, $action )
    {
        $id = (int)$item->attribute( 'id' );
        if ( $action === 'cancel' )
            $status = (int)$item->attribute( 'status' ) === SQLIImportItem::STATUS_RUNNING ? SQLIImportItem::STATUS_INTERRUPTED : SQLIImportItem::STATUS_CANCELED;
        elseif ( $action === 'interrupt' )
            $status = SQLIImportItem::STATUS_INTERRUPTED;
        else
            throw new expImportException( ezpI18n::tr( 'extension/sqliimport/error', "Unknown alter import action '%action'", null, array( '%action' => $action ) ) );
        SQLIImportLogger::logNotice( 'User #' . eZUser::currentUserID() . ' requested the ' . ( $action === 'cancel' ? 'cancelation' : 'interruption' ) . ' of import #' . $id . ' on ' . date( 'Y-m-d H:i' ), false );
        $item->setAttribute( 'status', $status );
        $item->store();
        foreach ( expImportRun::fetchList( array( 'legacy_item_id' => $id ), 0, 1 ) as $run )
            if ( !$run->isFinal() )
                expImportRunner::cancel( $run, $action === 'cancel' ? 'the 1.x import request was cancelled' : 'the 1.x import was interrupted' );
        return $item;
    }

    /** Removes the ended 1.x import requests (as sqliimport/purgelist of 1.x). */
    public static function purgeHistory()
    {
        SQLIImportLogger::logNotice( 'User #' . eZUser::currentUserID() . ' requested import history purge on ' . date( 'Y-m-d H:i' ), false );
        SQLIImportItem::purgeImportHistory();
    }

    /**
     * Stores a 1.x scheduled import from the fields of the form (the POST names of sqliimport/addscheduled of 1.x:
     * ImportHandler, ImportOptions, ScheduledDate, ScheduledHour, ScheduledMinute, ScheduledFrequency,
     * ManualScheduledFrequency, ScheduledLabel, ScheduledActive). The caller checks the access to the handler.
     *
     * @param int $id 0 for a new one
     * @param array $in the fields
     * @return SQLIScheduledImport
     * @throws expImportValidationException
     */
    public static function saveScheduled( $id, array $in )
    {
        $errors = array();
        $handler = isset( $in['ImportHandler'] ) ? (string)$in['ImportHandler'] : '';
        $available = expImportLegacyHandlerAdapter::available();
        if ( !isset( $available[$handler] ) || !$available[$handler]['enabled'] )
            $errors['ImportHandler'] = ezpI18n::tr( 'extension/sqliimport', 'Choose an available 1.x handler.' );
        $next = self::startTime( isset( $in['ScheduledDate'] ) ? $in['ScheduledDate'] : '', isset( $in['ScheduledHour'] ) ? $in['ScheduledHour'] : 0,
                                 isset( $in['ScheduledMinute'] ) ? $in['ScheduledMinute'] : 0 );
        if ( $next === null )
            $errors['ScheduledDate'] = ezpI18n::tr( 'extension/sqliimport/error', 'Please choose a correct date' );
        $frequency = isset( $in['ScheduledFrequency'] ) ? (string)$in['ScheduledFrequency'] : 'none';
        $manual = isset( $in['ManualScheduledFrequency'] ) ? (int)$in['ManualScheduledFrequency'] : 0;
        $problem = self::frequencyProblem( $frequency, $manual );
        if ( $problem === 'frequency' )
            $errors['ScheduledFrequency'] = ezpI18n::tr( 'extension/sqliimport', 'Choose a frequency.' );
        elseif ( $problem === 'manual' )
            $errors['ManualScheduledFrequency'] = ezpI18n::tr( 'extension/sqliimport/error', 'Please choose a frequency greater than 5min' );
        if ( $errors )
            throw new expImportValidationException( $errors );

        $row = array(
            'handler' => $handler,
            'label' => isset( $in['ScheduledLabel'] ) ? trim( (string)$in['ScheduledLabel'] ) : '',
            'frequency' => $frequency,
            'next' => $next,
            'is_active' => !empty( $in['ScheduledActive'] ) ? 1 : 0,
        );
        if ( $frequency === 'manual' )
            $row['manual_frequency'] = $manual;
        $scheduled = $id ? SQLIScheduledImport::fetch( (int)$id ) : null;
        if ( $scheduled instanceof SQLIScheduledImport )
            $scheduled->fromArray( $row );
        else
            $scheduled = new SQLIScheduledImport( $row + array( 'user_id' => eZUser::currentUserID() ) );
        $options = isset( $in['ImportOptions'] ) ? (string)$in['ImportOptions'] : '';
        if ( trim( $options ) !== '' )
            $scheduled->setAttribute( 'options', SQLIImportHandlerOptions::fromText( $options ) );
        elseif ( $scheduled->attribute( 'id' ) )
            $scheduled->setAttribute( 'options', new SQLIImportHandlerOptions() );
        $scheduled->store();
        SQLIImportLogger::logNotice( 'User #' . eZUser::currentUserID() . ' saved the scheduled import #' . $scheduled->attribute( 'id' ) . ' on ' . date( 'Y-m-d H:i' ), false );
        return $scheduled;
    }

    /** Removes a 1.x scheduled import (as sqliimport/removescheduled of 1.x). The caller checks the access. */
    public static function removeScheduled( SQLIScheduledImport $scheduled )
    {
        SQLIImportLogger::logNotice( 'User #' . eZUser::currentUserID() . ' removed scheduled import #' . $scheduled->attribute( 'id' ) . ' on ' . date( 'Y-m-d H:i' ), false );
        $db = eZDB::instance();
        $db->begin();
        $scheduled->remove();
        $db->commit();
    }

    /** Switches a 1.x scheduled import on or off. The caller checks the access. */
    public static function setScheduledActive( SQLIScheduledImport $scheduled, $active )
    {
        $scheduled->setAttribute( 'is_active', $active ? 1 : 0 );
        $scheduled->store( array( 'is_active' ) );
    }

    /** Requests an immediate import with the handler and options of a 1.x scheduled import. The caller checks the access. */
    public static function runScheduledNow( SQLIScheduledImport $scheduled )
    {
        return SQLIImportItem::fromScheduledImport( $scheduled );
    }

    /** @return int the 2.x import a 1.x scheduled import was migrated to, 0 if none */
    public static function migratedImportID( $scheduledID )
    {
        if ( (int)$scheduledID <= 0 || !expImportSchema::installed() )
            return 0;
        $list = expImportDefinition::fetchList( 0, 1, array( 'legacy_scheduled_id' => (int)$scheduledID ) );
        return $list ? (int)$list[0]->attribute( 'id' ) : 0;
    }

    /**
     * The 1.x import requests as the dashboard shows them, newest first.
     * @return array ( list of rows, int total )
     */
    public static function items( $offset, $limit )
    {
        $rows = array();
        foreach ( (array)SQLIImportItem::fetchList( (int)$offset, (int)$limit ) as $item )
        {
            $id = (int)$item->attribute( 'id' );
            $handler = (string)$item->attribute( 'handler' );
            $status = (int)$item->attribute( 'status' );
            $user = eZUser::fetch( (int)$item->attribute( 'user_id' ) );
            $runs = expImportRun::fetchList( array( 'legacy_item_id' => $id ), 0, 1 );
            $time = (int)$item->attribute( 'process_time' );
            $rows[] = array(
                'id' => $id,
                'handler' => $handler,
                'handler_name' => self::handlerName( $handler ),
                'options' => self::optionsText( $item->attribute( 'options' ) ),
                'user' => $user ? $user->attribute( 'login' ) : '',
                'requested_time' => (int)$item->attribute( 'requested_time' ),
                'scheduled' => (int)$item->attribute( 'type' ) === SQLIImportItem::TYPE_SCHEDULED,
                'scheduled_id' => (int)$item->attribute( 'scheduled_id' ),
                'status' => $status,
                'status_code' => self::itemStatusCode( $status ),
                'status_kind' => self::itemStatusKind( $status ),
                'status_label' => ezpI18n::tr( 'extension/sqliimport/type', (string)$item->attribute( 'status_string' ) ),
                'percent' => max( 0, min( 100, (int)round( (float)$item->attribute( 'percentage' ) ) ) ),
                'notes' => (string)$item->attribute( 'progression_notes' ),
                'duration' => expImportAdmin::duration( $time ),
                'run_id' => $runs ? (int)$runs[0]->attribute( 'id' ) : 0,
                'can_change' => expImportAdmin::can( 'run', $handler ),
            );
        }
        $total = (int)SQLIImportItem::count( SQLIImportItem::definition() );
        return array( $rows, $total );
    }

    /**
     * The 1.x scheduled imports as sqliimport/schedules shows them.
     * @return array list of rows
     */
    public static function scheduled()
    {
        $rows = array();
        foreach ( (array)SQLIScheduledImport::fetchList( 0, null, null ) as $s )
        {
            $id = (int)$s->attribute( 'id' );
            $handler = (string)$s->attribute( 'handler' );
            $frequency = (string)$s->attribute( 'frequency' );
            $rows[] = array(
                'id' => $id,
                'label' => (string)$s->attribute( 'label' ),
                'handler' => $handler,
                'handler_name' => self::handlerName( $handler ),
                'frequency' => $frequency,
                'manual_frequency' => (int)$s->attribute( 'manual_frequency' ),
                'options' => self::optionsText( $s->attribute( 'options' ) ),
                'next' => (int)$s->attribute( 'next' ),
                'is_active' => (bool)$s->attribute( 'is_active' ),
                'migrated_import_id' => self::migratedImportID( $id ),
                'can_run' => expImportAdmin::can( 'run', $handler ),
                'can_edit' => expImportAdmin::can( 'manage', $handler ),
            );
        }
        return $rows;
    }

    /**
     * The values of the form of a 1.x scheduled import.
     * @param SQLIScheduledImport|null $s
     * @param array|null $posted the fields of a form that did not validate
     * @return array
     */
    public static function form( $s, $posted = null )
    {
        $next = $s ? (int)$s->attribute( 'next' ) : 0;
        if ( !$next && $s )
            $next = (int)$s->attribute( 'requested_time' );
        if ( !$next )
            $next = mktime( (int)date( 'H' ), 0, 0 );
        $form = array(
            'id' => $s ? (int)$s->attribute( 'id' ) : 0,
            'handler' => $s ? (string)$s->attribute( 'handler' ) : '',
            'label' => $s ? (string)$s->attribute( 'label' ) : '',
            'options' => $s ? self::optionsText( $s->attribute( 'options' ) ) : '',
            'date' => date( 'Y-m-d', $next ),
            'hour' => (int)date( 'G', $next ),
            'minute' => (int)date( 'i', $next ),
            'frequency' => $s ? (string)$s->attribute( 'frequency' ) : 'none',
            'manual_frequency' => $s ? (int)$s->attribute( 'manual_frequency' ) : 0,
            'is_active' => $s ? (bool)$s->attribute( 'is_active' ) : true,
        );
        if ( is_array( $posted ) )
        {
            $keys = array( 'handler' => 'ImportHandler', 'label' => 'ScheduledLabel', 'options' => 'ImportOptions', 'date' => 'ScheduledDate',
                           'hour' => 'ScheduledHour', 'minute' => 'ScheduledMinute', 'frequency' => 'ScheduledFrequency',
                           'manual_frequency' => 'ManualScheduledFrequency' );
            foreach ( $keys as $k => $name )
                if ( isset( $posted[$name] ) )
                    $form[$k] = is_int( $form[$k] ) ? (int)$posted[$name] : (string)$posted[$name];
            $form['is_active'] = !empty( $posted['ScheduledActive'] );
        }
        $minutes = array( 0, 15, 30, 45 );
        if ( !in_array( $form['minute'], $minutes, true ) )
            $minutes[] = $form['minute'];
        sort( $minutes );
        $form['minutes'] = $minutes;
        return $form;
    }

    /** @return array the POST fields of the form of a 1.x scheduled import that were sent */
    public static function postedScheduleFields( eZHTTPTool $http )
    {
        $in = array();
        foreach ( array( 'ImportHandler', 'ImportOptions', 'ScheduledDate', 'ScheduledHour', 'ScheduledMinute', 'ScheduledFrequency',
                         'ManualScheduledFrequency', 'ScheduledLabel', 'ScheduledActive' ) as $name )
            if ( $http->hasPostVariable( $name ) )
                $in[$name] = $http->postVariable( $name );
        return $in;
    }

    /** Keeps a message for the next page (the flash of the 2.x pages). */
    public static function flash( $kind, $message )
    {
        eZHTTPTool::instance()->setSessionVariable( 'SQLIImportFlash', array( 'kind' => $kind, 'message' => $message ) );
    }
}
