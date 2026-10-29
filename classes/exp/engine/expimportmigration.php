<?php
/**
 * File containing the expImportMigration class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The migration of an installation of sqliimport 1.x (./console ext:sqliimport:migrate-1x):
 *
 *   handlers    each 1.x handler of [ImportSettings] AvailableSourceHandlers[] becomes an import of the catalogue
 *               (identifier legacy-<handler>)
 *   schedules   each row of sqliimport_scheduled becomes an import with its options (the k=v text as typed JSON
 *               options) and a schedule of the same frequency; the 1.x row is switched off, not removed
 *   history     each row of sqliimport_item becomes a read-only run (status, time, user; no rows or log)
 *   roles       the policy sqliimport/manageimports gets sqliimport/manage ("Manage imports") next to it, with the same
 *               handler limitation, only with --roles
 *
 * plan() reports what would be done and writes nothing; apply() does it. Both are idempotent: what was migrated
 * before is recognised (legacy_scheduled_id, legacy_item_id, existing policies) and left alone. The 1.x tables stay
 * until the owner removes them.
 */
class expImportMigration
{
    /**
     * @param bool $apply write (false: only report)
     * @param bool $roles also add the 2.x policies to the roles holding the 1.x ones
     * @return array list of array( 'what' => handlers|schedules|history|roles, 'text' => ..., 'done' => bool )
     */
    public static function run( $apply = false, $roles = false )
    {
        $report = array();
        $add = function ( $what, $text, $done ) use ( &$report )
        {
            $report[] = array( 'what' => $what, 'text' => $text, 'done' => $done );
        };

        foreach ( expImportLegacyHandlerAdapter::available() as $id => $info )
        {
            $identifier = expImportDefinition::LEGACY_PREFIX . preg_replace( '/[^a-z0-9_-]/i', '_', $id );
            if ( expImportDefinition::fetchByIdentifier( $identifier ) )
            {
                $add( 'handlers', "$id: already in the catalogue as $identifier", false );
                continue;
            }
            if ( $apply )
                expImportScheduler::legacyDefinition( $id );
            $add( 'handlers', "$id ({$info['name']}): becomes the import $identifier", $apply );
        }

        foreach ( (array)SQLIScheduledImport::fetchList( 0, null, null ) as $scheduled )
        {
            $sid = (int)$scheduled->attribute( 'id' );
            if ( expImportDefinition::fetchCount( array( 'legacy_scheduled_id' => $sid ) ) )
            {
                $add( 'schedules', "scheduled import #$sid: migrated before", false );
                continue;
            }
            $handler = (string)$scheduled->attribute( 'handler' );
            $label = (string)$scheduled->attribute( 'label' );
            $schedule = self::scheduleOf( $scheduled );
            $text = "scheduled import #$sid \"$label\" ($handler): becomes an import with the schedule " . $schedule['kind']
                  . ( $schedule['kind'] === 'interval' ? ' every ' . $schedule['interval_minutes'] . ' minutes' : '' );
            if ( $apply )
            {
                $options = array();
                $legacyOptions = $scheduled->attribute( 'options' );
                if ( $legacyOptions instanceof SQLIImportHandlerOptions )
                    foreach ( $legacyOptions->toArray() as $key => $value )
                        $options[$key] = $value;
                $import = expImportDefinition::create( array(
                    'identifier' => expImportDefinition::uniqueIdentifier( 'legacy_' . $handler . '_' . $sid ),
                    'name' => $label !== '' ? $label : $handler . ' #' . $sid,
                    'description' => "Migrated from the 1.x scheduled import #$sid.",
                    'source_type' => 'legacy',
                    'handler' => $handler,
                    'options_json' => expImport::json( $options ),
                    'legacy_scheduled_id' => $sid,
                    'creator_id' => (int)$scheduled->attribute( 'user_id' ),
                    'is_enabled' => 1,
                ) );
                $import->store();
                $row = new expImportSchedule( $schedule + array(
                    'import_id' => (int)$import->attribute( 'id' ),
                    'is_active' => (int)$scheduled->attribute( 'is_active' ),
                    'created' => expImport::now(),
                    'creator_id' => (int)$scheduled->attribute( 'user_id' ),
                    'modified' => expImport::now(),
                    'catch_up' => 'one',
                    'notify_json' => '',
                    'last_run' => 0,
                ) );
                $row->setAttribute( 'next_run', (int)$scheduled->attribute( 'next' ) );
                if ( (int)$row->attribute( 'next_run' ) <= expImport::now() )
                    $row->computeNext();
                $row->store();
                $scheduled->setAttribute( 'is_active', 0 );
                $scheduled->store( array( 'is_active' ) );
            }
            $add( 'schedules', $text, $apply );
        }

        $items = (array)SQLIImportItem::fetchList( 0, 0, null );
        $migrated = 0;
        $todo = 0;
        foreach ( $items as $item )
        {
            if ( expImportRun::fetchCount( array( 'legacy_item_id' => (int)$item->attribute( 'id' ) ) ) )
            {
                $migrated++;
                continue;
            }
            $todo++;
            if ( !$apply )
                continue;
            $import = expImportScheduler::legacyDefinition( (string)$item->attribute( 'handler' ) );
            $status = array( SQLIImportItem::STATUS_PENDING => expImportRun::STATUS_QUEUED, SQLIImportItem::STATUS_RUNNING => expImportRun::STATUS_FAILED,
                             SQLIImportItem::STATUS_FAILED => expImportRun::STATUS_FAILED, SQLIImportItem::STATUS_COMPLETED => expImportRun::STATUS_COMPLETED,
                             SQLIImportItem::STATUS_CANCELED => expImportRun::STATUS_CANCELLED, SQLIImportItem::STATUS_INTERRUPTED => expImportRun::STATUS_CANCELLED );
            $s = (int)$item->attribute( 'status' );
            // a pending item is left to the engine (it starts a real run); the history is read-only
            if ( $s === SQLIImportItem::STATUS_PENDING )
            {
                $todo--;
                continue;
            }
            $requested = (int)$item->attribute( 'requested_time' );
            $run = new expImportRun( array(
                'import_id' => (int)$import->attribute( 'id' ),
                'def_version' => 0,
                'trigger_kind' => 'legacy',
                'run_kind' => 'import',
                'user_id' => (int)$item->attribute( 'user_id' ),
                'legacy_item_id' => (int)$item->attribute( 'id' ),
                'status' => isset( $status[$s] ) ? $status[$s] : expImportRun::STATUS_FAILED,
                'queued_at' => $requested,
                'started_at' => $requested,
                'finished_at' => $requested + (int)$item->attribute( 'process_time' ),
                'summary' => expImport::json( array( 'migrated_from_1x' => true, 'progression_notes' => (string)$item->attribute( 'progression_notes' ),
                                                     'percentage' => (int)$item->attribute( 'percentage_int' ) ) ),
            ) );
            $run->store();
        }
        $add( 'history', $todo ? "$todo 1.x imports of the history become read-only runs" . ( $migrated ? " ($migrated migrated before)" : '' )
                               : 'the 1.x history is migrated' . ( $migrated ? " ($migrated runs)" : '' ), $apply && $todo > 0 );

        foreach ( self::rolePlan() as $line )
        {
            if ( $apply && $roles )
            {
                $role = eZRole::fetch( $line['role_id'] );
                if ( $role )
                {
                    foreach ( $line['add'] as $function => $limitation )
                        $role->appendPolicy( 'sqliimport', $function, $limitation );
                    $role->store();
                }
            }
            $add( 'roles', 'role "' . $line['role'] . '": add sqliimport/' . implode( ', sqliimport/', array_keys( $line['add'] ) )
                           . ( $roles ? '' : ' (run with --roles to apply)' ), $apply && $roles );
        }
        if ( $apply )
        {
            if ( $roles && class_exists( 'eZRole' ) )
                eZRole::expireCache();
            expImport::audit( 'migrate', array( 'roles' => $roles ) );
        }
        return $report;
    }

    /**
     * The 2.x schedule of a 1.x scheduled import.
     * @return array kind, run_at, interval_minutes, cron_expr, timezone
     */
    public static function scheduleOf( SQLIScheduledImport $scheduled )
    {
        $frequency = (string)$scheduled->attribute( 'frequency' );
        $next = (int)$scheduled->attribute( 'next' );
        $kinds = array( 'none' => 'once', 'hourly' => 'hourly', 'daily' => 'daily', 'weekly' => 'weekly', 'monthly' => 'monthly', 'manual' => 'interval' );
        return array(
            'kind' => isset( $kinds[$frequency] ) ? $kinds[$frequency] : 'once',
            'run_at' => $next,
            'interval_minutes' => $frequency === 'manual' ? max( expImportCron::MIN_INTERVAL, (int)$scheduled->attribute( 'manual_frequency' ) ) : 0,
            'cron_expr' => '',
            'timezone' => date_default_timezone_get() ?: 'UTC',
        );
    }

    /**
     * The roles holding a 1.x policy of sqliimport and the 2.x functions they lack.
     * @return array list of array( role_id, role, add => function => limitation array )
     */
    public static function rolePlan()
    {
        $db = expImport::db();
        $rows = $db->arrayQuery( "SELECT p.id AS policy_id, p.role_id, p.function_name, r.name FROM ezpolicy p JOIN ezrole r ON r.id = p.role_id
                                  WHERE p.module_name = 'sqliimport' AND p.original_id = 0 AND r.version = 0" );
        $byRole = array();
        foreach ( (array)$rows as $row )
        {
            $id = (int)$row['role_id'];
            if ( !isset( $byRole[$id] ) )
                $byRole[$id] = array( 'role_id' => $id, 'role' => $row['name'], 'has' => array(), 'limitation' => array() );
            $byRole[$id]['has'][] = $row['function_name'];
            if ( $row['function_name'] === 'manageimports' )
            {
                $policy = eZPolicy::fetch( (int)$row['policy_id'] );
                if ( $policy )
                    foreach ( $policy->limitationList() as $limitation )
                        $byRole[$id]['limitation'][$limitation->attribute( 'identifier' )] = $limitation->allValues();
            }
        }
        $out = array();
        foreach ( $byRole as $role )
        {
            $add = array();
            $has = $role['has'];
            if ( in_array( '*', $has, true ) )
                continue;
            // manageimports becomes "Manage imports" with the same handler limitation; listimports needs nothing new
            if ( in_array( 'manageimports', $has, true ) && !in_array( 'manage', $has, true ) )
                $add['manage'] = $role['limitation'];
            if ( $add )
                $out[] = array( 'role_id' => $role['role_id'], 'role' => $role['role'], 'add' => $add );
        }
        return $out;
    }
}
