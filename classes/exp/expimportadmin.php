<?php
/**
 * File containing the expImportAdmin class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the admin pages of the Imports tab show: the figures of the dashboard, the imports with their last run and next
 * schedule, the runs filtered by view parameters, one run with its rows and log, and the access checks of the
 * policies (2.x functions, with the 1.x functions still accepted).
 */
class expImportAdmin
{
    /** The statuses the run list filters on, with the ones that belong together */
    const STATUS_FILTERS = array(
        'active' => array( 'queued', 'running' ),
        'failed' => array( 'failed', 'completed_with_errors' ),
        'completed' => array( 'completed' ),
        'cancelled' => array( 'cancelled' ),
    );

    /**
     * Whether the current user may do something. Two policies: manage (Manage imports: create, edit, copy, delete,
     * schedule, settings, and run) and run (Run imports); the 1.x manageimports counts as manage, listimports lets one look.
     *
     * @param string $function view, run, manage (edit and settings are manage)
     * @param string $handler the handler (for the SQLIImport_Type limitation), '' when none is concerned
     * @return bool
     */
    public static function can( $function, $handler = '' )
    {
        $user = eZUser::currentUser();
        // two policies: manage (Manage imports) and run (Run imports); the 1.x ones count as before
        $map = array(
            'view' => array( 'manage', 'run', 'listimports', 'manageimports' ),
            'run' => array( 'run', 'manage', 'manageimports' ),
            'manage' => array( 'manage', 'manageimports' ),
            'edit' => array( 'manage', 'manageimports' ),
            'settings' => array( 'manage', 'manageimports' ),
        );
        $names = isset( $map[$function] ) ? $map[$function] : array( $function );
        foreach ( $names as $name )
        {
            $access = $user->hasAccessTo( 'sqliimport', $name );
            if ( $access['accessWord'] === 'yes' )
                return true;
            if ( $access['accessWord'] === 'limited' )
            {
                if ( $handler === '' )
                    return true;
                foreach ( $access['policies'] as $limitations )
                {
                    if ( !isset( $limitations['SQLIImport_Type'] ) || in_array( $handler, (array)$limitations['SQLIImport_Type'], true ) )
                        return true;
                }
            }
        }
        return false;
    }

    /** @return array the key figures of the dashboard */
    public static function figures()
    {
        $now = expImport::now();
        $today = mktime( 0, 0, 0, (int)date( 'n', $now ), (int)date( 'j', $now ), (int)date( 'Y', $now ) );
        $db = expImport::db();
        $one = function ( $sql ) use ( $db )
        {
            $rows = $db->arrayQuery( $sql );
            $row = is_array( $rows ) && isset( $rows[0] ) ? array_change_key_case( $rows[0], CASE_LOWER ) : array();
            return $row ? (int)reset( $row ) : 0;
        };
        $next = $one( 'SELECT MIN(next_run) AS n FROM sqliimport_schedule WHERE is_active = 1 AND next_run > 0' );
        return array(
            'imports' => $one( "SELECT COUNT(*) AS n FROM sqliimport_definition WHERE source_type <> 'legacy' OR legacy_scheduled_id > 0" ),
            'runs_today' => $one( 'SELECT COUNT(*) AS n FROM sqliimport_run WHERE queued_at >= ' . $today ),
            'active' => $one( "SELECT COUNT(*) AS n FROM sqliimport_run WHERE status IN ('queued', 'running')" ),
            'failed_24h' => $one( "SELECT COUNT(*) AS n FROM sqliimport_run WHERE status IN ('failed', 'completed_with_errors') AND finished_at >= " . ( $now - 86400 ) ),
            'next_run' => $next,
            'schedules' => $one( 'SELECT COUNT(*) AS n FROM sqliimport_schedule WHERE is_active = 1' ),
            'legacy_pending' => $one( 'SELECT COUNT(*) AS n FROM sqliimport_item WHERE status = 0' ),
        );
    }

    /**
     * The imports with what the list shows of them.
     * @return array list of array( 'import', 'last_run', 'next_run', 'can_run' )
     */
    public static function imports( $limit = 50 )
    {
        $out = array();
        foreach ( expImportDefinition::fetchList( 0, $limit ) as $import )
        {
            $next = 0;
            foreach ( $import->schedules() as $schedule )
                if ( (int)$schedule->attribute( 'is_active' ) && (int)$schedule->attribute( 'next_run' ) && ( !$next || (int)$schedule->attribute( 'next_run' ) < $next ) )
                    $next = (int)$schedule->attribute( 'next_run' );
            $last = $import->lastRun();
            $out[] = array(
                'import' => $import,
                'last_run' => $last ? self::runRow( $last ) : null,
                'next_run' => $next,
                'can_run' => self::can( 'run', (string)$import->attribute( 'handler' ) ),
                'can_edit' => self::can( 'edit', (string)$import->attribute( 'handler' ) ),
            );
        }
        return $out;
    }

    /**
     * The runs of the list, filtered.
     *
     * @param array $filter status (a key of STATUS_FILTERS), import (an ID)
     * @return array ( expImportRun[], int total )
     */
    public static function runs( array $filter, $offset, $limit )
    {
        $conds = array();
        if ( isset( $filter['status'] ) && isset( self::STATUS_FILTERS[$filter['status']] ) )
            $conds['status'] = array( self::STATUS_FILTERS[$filter['status']] );
        if ( !empty( $filter['import'] ) )
            $conds['import_id'] = (int)$filter['import'];
        return array( expImportRun::fetchList( $conds, $offset, $limit ), expImportRun::fetchCount( $conds ) );
    }

    /** @return string "3 min 12 s" */
    public static function duration( $seconds )
    {
        $seconds = (int)$seconds;
        if ( $seconds < 60 )
            return $seconds . ' s';
        if ( $seconds < 3600 )
            return intdiv( $seconds, 60 ) . ' min ' . ( $seconds % 60 ) . ' s';
        return intdiv( $seconds, 3600 ) . ' h ' . intdiv( $seconds % 3600, 60 ) . ' min';
    }

    /**
     * The words of the pages for the codes the tables store (status, run kind, trigger, outcome), translated.
     *
     * @param string $group status, kind, trigger, outcome
     * @param string $code
     * @return string
     */
    public static function label( $group, $code )
    {
        $labels = array(
            'status' => array( 'queued' => 'Queued', 'running' => 'Running', 'completed' => 'Completed', 'completed_with_errors' => 'Completed with errors',
                               'failed' => 'Failed', 'cancelled' => 'Cancelled', 'active' => 'Running or queued' ),
            'kind' => array( 'import' => 'Import', 'dry_run' => 'Dry run', 'retry' => 'Retry of failed rows' ),
            'trigger' => array( 'manual' => 'Started by hand', 'schedule' => 'Schedule', 'cli' => 'Command line', 'rest' => 'REST API', 'legacy' => '1.x import request' ),
            'outcome' => array( 'created' => 'Created', 'updated' => 'Updated', 'unchanged' => 'Unchanged', 'skipped' => 'Skipped', 'failed' => 'Failed',
                                'processed' => 'Processed (1.x)' ),
        );
        if ( !isset( $labels[$group][$code] ) )
            return (string)$code;
        return ezpI18n::tr( 'extension/sqliimport/' . $group, $labels[$group][$code] );
    }

    /** @return string the badge modifier of a run status (is-ok, is-bad, is-warn, is-accent or '') */
    public static function statusKind( $status )
    {
        $map = array( 'completed' => 'is-ok', 'completed_with_errors' => 'is-warn', 'failed' => 'is-bad', 'running' => 'is-accent',
                      'queued' => '', 'cancelled' => '' );
        return isset( $map[$status] ) ? $map[$status] : '';
    }

    /**
     * A run as the template wants it.
     * @return array
     */
    public static function runRow( expImportRun $run )
    {
        $import = $run->import();
        $user = eZUser::fetch( (int)$run->attribute( 'user_id' ) );
        return array(
            'id' => (int)$run->attribute( 'id' ),
            'import_id' => (int)$run->attribute( 'import_id' ),
            'import_name' => $import ? $import->attribute( 'name' ) : '#' . $run->attribute( 'import_id' ),
            'status' => $run->attribute( 'status' ),
            'status_kind' => self::statusKind( $run->attribute( 'status' ) ),
            'status_label' => self::label( 'status', $run->attribute( 'status' ) ),
            'kind' => $run->attribute( 'run_kind' ),
            'kind_label' => self::label( 'kind', $run->attribute( 'run_kind' ) ),
            'trigger' => $run->attribute( 'trigger_kind' ),
            'trigger_label' => self::label( 'trigger', $run->attribute( 'trigger_kind' ) ),
            'percent' => $run->percent(),
            'pct_class' => 'a4-pct-' . (int)( round( $run->percent() / 4 ) * 4 ),
            'total' => (int)$run->attribute( 'total_count' ),
            'done' => $run->doneCount(),
            'created' => (int)$run->attribute( 'created_count' ),
            'updated' => (int)$run->attribute( 'updated_count' ),
            'unchanged' => (int)$run->attribute( 'unchanged_count' ),
            'skipped' => (int)$run->attribute( 'skipped_count' ),
            'failed' => (int)$run->attribute( 'failed_count' ),
            'processed' => (int)$run->attribute( 'processed_count' ),
            'queued_at' => (int)$run->attribute( 'queued_at' ),
            'started_at' => (int)$run->attribute( 'started_at' ),
            'finished_at' => (int)$run->attribute( 'finished_at' ),
            'duration' => self::duration( $run->duration() ),
            'memory' => round( (int)$run->attribute( 'memory_peak' ) / 1048576, 1 ),
            'user' => $user ? $user->attribute( 'login' ) : '',
            'is_final' => $run->isFinal(),
            'queue_group' => $run->attribute( 'queue_group' ),
            'parent_run_id' => (int)$run->attribute( 'parent_run_id' ),
        );
    }
}
