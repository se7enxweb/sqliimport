<?php
/**
 * sqliimport/action: every change of the 2.x pages, POST only (ezformtoken checks the form token of every POST):
 *   run, dryrun, duplicate, delete, enable, disable (ImportID); cancel, retry (RunID);
 *   schedule_pause, schedule_resume, schedule_delete (ScheduleID)
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
            $run = expImportRun::fetch( (int)$http->postVariable( 'RunID' ) );
            $import = $run ? $run->import() : null;
            if ( !$run || !$import )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The run does not exist.' ) );
            if ( !expImportAdmin::can( 'run', (string)$import->attribute( 'handler' ) ) )
                return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
            if ( $action === 'cancel' )
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
        default:
            $flash( 'warn', ezpI18n::tr( 'extension/sqliimport', 'Unknown action.' ) );
    }
}
catch ( expImportException $e )
{
    $flash( 'bad', $e->getMessage() );
}
$Module->redirectTo( $redirect );
