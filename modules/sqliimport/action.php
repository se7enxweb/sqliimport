<?php
/**
 * sqliimport/action: every change of the 2.x pages, POST only (ezformtoken checks the form token of every POST):
 *   run, dryrun, duplicate, delete, enable, disable (ImportID); cancel, retry, undo (RunID);
 *   schedule_pause, schedule_resume, schedule_delete (ScheduleID);
 *   1.x: legacy_request (ImportHandler, ImportOptions), legacy_cancel, legacy_interrupt (ItemID), legacy_purge,
 *   legacy_schedule_run, legacy_schedule_enable, legacy_schedule_disable, legacy_schedule_delete (LegacyScheduleID)
 * The result is shown on the next page (a session message); RedirectURI must be a page of this module.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) !== 'POST' )
{
    $Module->redirectTo( '/sqliimport/list' );
    return;
}

$action = $http->hasPostVariable( 'Action' ) ? (string)$http->postVariable( 'Action' ) : '';
$redirect = $http->hasPostVariable( 'RedirectURI' ) ? (string)$http->postVariable( 'RedirectURI' ) : '/sqliimport/list';
if ( !preg_match( '#^/sqliimport/[a-z]+(/[A-Za-z0-9_()/-]*)?$#', $redirect ) )
    $redirect = '/sqliimport/list';
$flash = function ( $kind, $message ) use ( $http )
{
    $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => $kind, 'message' => $message ) );
};

try
{
    switch ( $action )
    {
        case 'notices_seen':
            // the current user's own notices: anyone who sees the dashboard may mark them as read
            expImportNotifier::markSeen( expImport::currentUserID() );
            break;
        case 'run':
        case 'dryrun':
            $import = expImportDefinition::fetch( (int)$http->postVariable( 'ImportID' ) );
            if ( !$import )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The import does not exist.' ) );
            if ( !expImportAdmin::can( 'run', (string)$import->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            $run = expImportRunner::start( $import, array( 'trigger' => 'manual', 'run_kind' => $action === 'dryrun' ? 'dry_run' : 'import' ) );
            $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'Run #%id of "%name" is queued.', null,
                                       array( '%id' => $run->attribute( 'id' ), '%name' => $import->attribute( 'name' ) ) ) );
            $redirect = '/sqliimport/run/' . (int)$run->attribute( 'id' );
            break;
        case 'cancel':
        case 'retry':
        case 'undo':
            $run = expImportRun::fetch( (int)$http->postVariable( 'RunID' ) );
            $import = $run ? $run->import() : null;
            if ( !$run || !$import )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The run does not exist.' ) );
            // an undo removes and reverts content: Manage imports; cancel and retry: Run imports
            if ( !expImportAdmin::can( $action === 'undo' ? 'manage' : 'run', (string)$import->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            if ( $action === 'undo' )
            {
                $undo = expImportRunner::undo( $run );
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'Run #%id undoes run #%parent.', null,
                                           array( '%id' => $undo->attribute( 'id' ), '%parent' => $run->attribute( 'id' ) ) ) );
                $redirect = '/sqliimport/run/' . (int)$undo->attribute( 'id' );
            }
            elseif ( $action === 'cancel' )
            {
                $done = expImportRunner::cancel( $run );
                $flash( $done ? 'ok' : 'warn', $done ? ezpI18n::tr( 'extension/sqliimport', 'Run #%id is cancelled.', null, array( '%id' => $run->attribute( 'id' ) ) )
                                                     : ezpI18n::tr( 'extension/sqliimport', 'Run #%id had ended already.', null, array( '%id' => $run->attribute( 'id' ) ) ) );
            }
            else
            {
                $retry = expImportRunner::retryFailed( $run );
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'Run #%id retries the failed rows of run #%parent.', null,
                                           array( '%id' => $retry->attribute( 'id' ), '%parent' => $run->attribute( 'id' ) ) ) );
                $redirect = '/sqliimport/run/' . (int)$retry->attribute( 'id' );
            }
            break;
        case 'duplicate':
        case 'delete':
        case 'enable':
        case 'disable':
            $import = expImportDefinition::fetch( (int)$http->postVariable( 'ImportID' ) );
            if ( !$import )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The import does not exist.' ) );
            if ( !expImportAdmin::can( 'edit', (string)$import->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            if ( $action === 'duplicate' )
            {
                $copy = expImportEditor::duplicate( $import );
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', '"%name" is a copy (disabled until it is saved).', null, array( '%name' => $copy->attribute( 'name' ) ) ) );
                $redirect = '/sqliimport/edit/' . (int)$copy->attribute( 'id' ) . '/(step)/source';
            }
            elseif ( $action === 'delete' )
            {
                if ( expImportRun::fetchActive( $import->attribute( 'id' ) ) )
                    throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The import runs now; cancel the run first.' ) );
                $name = $import->attribute( 'name' );
                expImport::audit( 'import.delete', array( 'id' => (int)$import->attribute( 'id' ), 'identifier' => $import->attribute( 'identifier' ) ) );
                $import->removeAll();
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The import "%name" is deleted; its runs stay in the history.', null, array( '%name' => $name ) ) );
                $redirect = '/sqliimport/list';
            }
            else
            {
                $import->setAttribute( 'is_enabled', $action === 'enable' ? 1 : 0 );
                $import->storeChange();
                expImport::audit( 'import.change', array( 'id' => (int)$import->attribute( 'id' ), 'enabled' => $action === 'enable' ) );
                $flash( 'ok', $action === 'enable' ? ezpI18n::tr( 'extension/sqliimport', 'The import "%name" is enabled.', null, array( '%name' => $import->attribute( 'name' ) ) )
                                                   : ezpI18n::tr( 'extension/sqliimport', 'The import "%name" is disabled.', null, array( '%name' => $import->attribute( 'name' ) ) ) );
            }
            break;
        case 'schedule_pause':
        case 'schedule_resume':
        case 'schedule_delete':
            $schedule = expImportSchedule::fetch( (int)$http->postVariable( 'ScheduleID' ) );
            $import = $schedule ? $schedule->import() : null;
            if ( !$schedule || !$import )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The schedule does not exist.' ) );
            if ( !expImportAdmin::can( 'edit', (string)$import->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            if ( $action === 'schedule_delete' )
                $schedule->remove();
            else
            {
                $schedule->setAttribute( 'is_active', $action === 'schedule_resume' ? 1 : 0 );
                if ( $action === 'schedule_resume' )
                    $schedule->computeNext();
                $schedule->setAttribute( 'modified', expImport::now() );
                $schedule->store();
            }
            expImport::audit( 'schedule.change', array( 'id' => (int)$schedule->attribute( 'id' ), 'import' => (int)$import->attribute( 'id' ), 'action' => $action ) );
            $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The schedule of "%name" is changed.', null, array( '%name' => $import->attribute( 'name' ) ) ) );
            break;
        // the 1.x import requests (dashboard) and scheduled imports (sqliimport/schedules), see expImportLegacy
        case 'legacy_request':
            $handler = $http->hasPostVariable( 'ImportHandler' ) ? (string)$http->postVariable( 'ImportHandler' ) : '';
            if ( !expImportAdmin::can( 'run', $handler ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            $item = expImportLegacy::requestImport( $handler, $http->hasPostVariable( 'ImportOptions' ) ? (string)$http->postVariable( 'ImportOptions' ) : '' );
            $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x import #%id of "%handler" is requested; the next cron round starts it.', null,
                                       array( '%id' => $item->attribute( 'id' ), '%handler' => expImportLegacy::handlerName( $handler ) ) ) );
            break;
        case 'legacy_cancel':
        case 'legacy_interrupt':
            $item = SQLIImportItem::fetch( (int)$http->postVariable( 'ItemID' ) );
            if ( !$item instanceof SQLIImportItem )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The 1.x import request does not exist.' ) );
            if ( !expImportAdmin::can( 'run', (string)$item->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            expImportLegacy::alterItem( $item, $action === 'legacy_cancel' ? 'cancel' : 'interrupt' );
            $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x import #%id is %status.', null,
                                       array( '%id' => $item->attribute( 'id' ), '%status' => ezpI18n::tr( 'extension/sqliimport/type', (string)$item->attribute( 'status_string' ) ) ) ) );
            break;
        case 'legacy_purge':
            if ( !expImportAdmin::can( 'manage' ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            expImportLegacy::purgeHistory();
            $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The history of the 1.x import requests is purged.' ) );
            break;
        case 'legacy_schedule_run':
        case 'legacy_schedule_enable':
        case 'legacy_schedule_disable':
        case 'legacy_schedule_delete':
            $scheduled = SQLIScheduledImport::fetch( (int)$http->postVariable( 'LegacyScheduleID' ) );
            if ( !$scheduled instanceof SQLIScheduledImport )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import does not exist.' ) );
            $handler = (string)$scheduled->attribute( 'handler' );
            $name = $scheduled->attribute( 'label' ) !== '' ? (string)$scheduled->attribute( 'label' ) : '#' . $scheduled->attribute( 'id' );
            if ( !expImportAdmin::can( $action === 'legacy_schedule_run' ? 'run' : 'manage', $handler ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            if ( $action === 'legacy_schedule_run' )
            {
                $item = expImportLegacy::runScheduledNow( $scheduled );
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x import #%id of "%handler" is requested; the next cron round starts it.', null,
                                           array( '%id' => $item->attribute( 'id' ), '%handler' => $name ) ) );
            }
            elseif ( $action === 'legacy_schedule_delete' )
            {
                expImportLegacy::removeScheduled( $scheduled );
                $flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import "%name" is deleted.', null, array( '%name' => $name ) ) );
            }
            else
            {
                expImportLegacy::setScheduledActive( $scheduled, $action === 'legacy_schedule_enable' );
                $flash( 'ok', $action === 'legacy_schedule_enable' ? ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import "%name" is enabled.', null, array( '%name' => $name ) )
                                                                   : ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import "%name" is disabled.', null, array( '%name' => $name ) ) );
            }
            break;
        default:
            $flash( 'warn', ezpI18n::tr( 'extension/sqliimport', 'Unknown action.' ) );
    }
}
catch ( expImportException $e )
{
    $flash( 'bad', $e->getMessage() );
}
$Module->redirectTo( $redirect );
