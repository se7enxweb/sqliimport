<?php
/**
 * sqliimport/transfer (policy Manage imports): "Import definitions". Upload a definitions file (expImportTransfer:
 * "Download as JSON", "Export selected", the export of Settings), see what it would create on this site (per import:
 * new or an identifier that is taken, the class and parent node it resolves to, what is missing here), choose per
 * import create new (the identifier suffixed when it is taken), replace or skip, and create them: disabled, schedules
 * paused, trigger switched off, so the person reviews, dry-runs and enables each. Every POST carries the form token.
 *
 * The uploaded file waits in var/<site>/storage/sqliimport/transfer/ under a random name kept in the session (not
 * the file itself) until it is applied or cancelled; files older than a day are removed with the next upload.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
if ( !expImportSchema::installed() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
if ( !expImportAdmin::can( 'manage' ) )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );

$sessionKey = 'SQLIImportTransferFile';
$isPost = strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST';
$dir = expImport::storageDirectory( 'transfer' );
$pending = $http->hasSessionVariable( $sessionKey ) ? (array)$http->sessionVariable( $sessionKey ) : array();
if ( $pending && ( !isset( $pending['file'] ) || !preg_match( '/^[a-f0-9]{32}\.json$/', (string)$pending['file'] ) || !is_file( $dir . '/' . $pending['file'] ) ) )
{
    $http->removeSessionVariable( $sessionKey );
    $pending = array();
}
$forget = function () use ( $http, $dir, $sessionKey, &$pending )
{
    if ( $pending )
        @unlink( $dir . '/' . $pending['file'] );
    $http->removeSessionVariable( $sessionKey );
    $pending = array();
};
$errors = array();
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );

if ( $isPost && $http->hasPostVariable( 'CancelButton' ) )
{
    $forget();
    $Module->redirectTo( '/sqliimport/transfer' );
    return;
}
if ( $isPost && $http->hasPostVariable( 'UploadButton' ) )
{
    $forget();
    $file = eZHTTPFile::canFetch( 'DefinitionsFile' ) ? eZHTTPFile::fetch( 'DefinitionsFile' ) : null;
    if ( !$file instanceof eZHTTPFile )
        $errors['DefinitionsFile'] = ezpI18n::tr( 'extension/sqliimport', 'Choose the definitions file (JSON).' );
    elseif ( filesize( $file->attribute( 'filename' ) ) > expImportTransfer::MAX_BYTES )
        $errors['DefinitionsFile'] = ezpI18n::tr( 'extension/sqliimport', 'The file is larger than 10 MB.' );
    else
    {
        $json = (string)file_get_contents( $file->attribute( 'filename' ) );
        try
        {
            $items = expImportTransfer::parse( $json );
            // the files of earlier uploads nobody applied
            foreach ( glob( $dir . '/*.json' ) ?: array() as $old )
                if ( filemtime( $old ) < expImport::now() - 86400 )
                    @unlink( $old );
            $name = md5( uniqid( (string)mt_rand(), true ) . random_bytes( 8 ) ) . '.json';
            eZFile::create( $name, $dir, $json, true );
            $http->setSessionVariable( $sessionKey, array( 'file' => $name, 'original_name' => basename( (string)$file->attribute( 'original_filename' ) ),
                                                                           'count' => count( $items ) ) );
            $Module->redirectTo( '/sqliimport/transfer' );
            return;
        }
        catch ( expImportValidationException $e )
        {
            $errors['DefinitionsFile'] = implode( ' ', $e->errors );
        }
    }
}

$plan = array();
if ( $pending )
{
    try
    {
        $plan = expImportTransfer::plan( expImportTransfer::parse( (string)file_get_contents( $dir . '/' . $pending['file'] ) ), expImportTransfer::site() );
    }
    catch ( expImportValidationException $e )
    {
        $errors['DefinitionsFile'] = implode( ' ', $e->errors );
        $forget();
    }
}

if ( $isPost && $http->hasPostVariable( 'ApplyButton' ) && $plan )
{
    if ( !$http->hasPostVariable( 'TransferFile' ) || (string)$http->postVariable( 'TransferFile' ) !== $pending['file'] )
        $errors['TransferFile'] = ezpI18n::tr( 'extension/sqliimport', 'The file was replaced in another window: check the choices again.' );
    else
    {
        $choices = $http->hasPostVariable( 'Choice' ) ? (array)$http->postVariable( 'Choice' ) : array();
        list( $done, $skipped ) = expImportTransfer::apply( $plan, $choices );
        $forget();
        $names = array_map( function ( $i ) { return (string)$i->attribute( 'name' ); }, $done );
        $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => $done ? 'ok' : 'warn',
            'message' => ezpI18n::tr( 'extension/sqliimport', '%n imports created or replaced, disabled: review, dry-run and enable each. %skipped skipped.', null,
                                      array( '%n' => count( $done ), '%skipped' => $skipped ) ) . ( $names ? ' ' . implode( ', ', $names ) : '' ) ) );
        $Module->redirectTo( '/sqliimport/list' );
        return;
    }
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'plan', $plan );
$tpl->setVariable( 'pending', $pending );
$tpl->setVariable( 'errors', $errors );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'max_megabytes', (int)( expImportTransfer::MAX_BYTES / 1048576 ) );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/transfer.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Import definitions' ) ) );
