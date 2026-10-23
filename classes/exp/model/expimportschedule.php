<?php
/**
 * File containing the expImportSchedule class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * When an import runs by itself (sqliimport_schedule). The kinds and the calculation are in expImportCron; the cron
 * part sqliimport (expImportScheduler) starts the runs that are due.
 *
 *   catch_up  what happens when runs were missed (the cron did not run): one (run once now, the default) or skip
 *             (wait for the next time)
 *   notify_json  list of e-mail addresses told when a run fails
 */
class expImportSchedule extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_schedule' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportSchedule',
            'name' => 'sqliimport_schedule',
            'function_attributes' => array( 'import' => 'import', 'notify' => 'notify', 'description' => 'describe' ),
        );
    }

    /** @return expImportSchedule|null */
    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return expImportSchedule[] */
    public static function fetchByImport( $importID )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, array( 'import_id' => (int)$importID ), array( 'id' => 'asc' ) );
    }

    /** @return expImportSchedule[] every schedule, the next first */
    public static function fetchAll( $offset = 0, $limit = 200 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, null, array( 'is_active' => 'desc', 'next_run' => 'asc' ),
                                                    array( 'offset' => (int)$offset, 'length' => (int)$limit ) );
    }

    /** @return expImportSchedule[] active schedules due at $now */
    public static function fetchDue( $now )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null,
                                                    array( 'is_active' => 1, 'next_run' => array( '<=', (int)$now ) ),
                                                    array( 'next_run' => 'asc' ) );
    }

    /** @return array the fields expImportCron reads */
    public function calcArray()
    {
        return array( 'kind' => $this->attribute( 'kind' ), 'run_at' => (int)$this->attribute( 'run_at' ),
                      'interval_minutes' => (int)$this->attribute( 'interval_minutes' ), 'cron_expr' => $this->attribute( 'cron_expr' ),
                      'timezone' => $this->attribute( 'timezone' ) );
    }

    /** Sets next_run from the schedule and $after (0 = now). */
    public function computeNext( $after = 0 )
    {
        $after = $after ?: expImport::now() - 1;
        $next = expImportCron::next( $this->calcArray(), $after );
        $this->setAttribute( 'next_run', $next );
        if ( !$next )
            $this->setAttribute( 'is_active', 0 );
    }

    /** @return expImportDefinition|null */
    public function import()
    {
        return expImportDefinition::fetch( $this->attribute( 'import_id' ) );
    }

    /** @return string[] */
    public function notify()
    {
        return array_values( array_filter( (array)expImport::decode( $this->attribute( 'notify_json' ) ), 'is_string' ) );
    }

    /** @return string one line: "daily at 02:30 (Europe/Berlin)" */
    public function describe()
    {
        $zone = $this->attribute( 'timezone' ) ?: 'UTC';
        $at = new DateTime( '@' . (int)$this->attribute( 'run_at' ) );
        try
        {
            $at->setTimezone( new DateTimeZone( $zone ) );
        }
        catch ( Exception $e )
        {
        }
        $time = $at->format( 'H:i' );
        switch ( $this->attribute( 'kind' ) )
        {
            case 'once':
                return expImport::tr( 'extension/sqliimport/schedule', 'once at %datetime (%zone)', array( '%datetime' => $at->format( 'Y-m-d H:i' ), '%zone' => $zone ) );
            case 'interval':
                return expImport::tr( 'extension/sqliimport/schedule', 'every %n minutes', array( '%n' => (int)$this->attribute( 'interval_minutes' ) ) );
            case 'hourly':
                return expImport::tr( 'extension/sqliimport/schedule', 'hourly at minute %minute', array( '%minute' => $at->format( 'i' ) ) );
            case 'daily':
                return expImport::tr( 'extension/sqliimport/schedule', 'daily at %time (%zone)', array( '%time' => $time, '%zone' => $zone ) );
            case 'weekly':
                $days = array( expImport::tr( 'extension/sqliimport/schedule', 'Sunday' ), expImport::tr( 'extension/sqliimport/schedule', 'Monday' ), expImport::tr( 'extension/sqliimport/schedule', 'Tuesday' ), expImport::tr( 'extension/sqliimport/schedule', 'Wednesday' ),
                               expImport::tr( 'extension/sqliimport/schedule', 'Thursday' ), expImport::tr( 'extension/sqliimport/schedule', 'Friday' ), expImport::tr( 'extension/sqliimport/schedule', 'Saturday' ) );
                return expImport::tr( 'extension/sqliimport/schedule', 'weekly on %day at %time (%zone)', array( '%day' => $days[(int)$at->format( 'w' )], '%time' => $time, '%zone' => $zone ) );
            case 'monthly':
                return expImport::tr( 'extension/sqliimport/schedule', 'monthly on day %day at %time (%zone)', array( '%day' => $at->format( 'j' ), '%time' => $time, '%zone' => $zone ) );
            case 'cron':
                return expImport::tr( 'extension/sqliimport/schedule', 'cron %expression (%zone)', array( '%expression' => $this->attribute( 'cron_expr' ), '%zone' => $zone ) );
        }
        return (string)$this->attribute( 'kind' );
    }
}
