<?php
/**
 * sqliimport/action: every change of the 2.x pages, POST only (ezformtoken checks the form token of every POST):
 *   run (ImportID), dryrun (ImportID), cancel (RunID), retry (RunID)
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
        default:
            $flash( 'warn', ezpI18n::tr( 'extension/sqliimport', 'Unknown action.' ) );
    }
}
catch ( expImportException $e )
{
    $flash( 'bad', $e->getMessage() );
}
$Module->redirectTo( $redirect );
