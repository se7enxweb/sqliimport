<?php
/**
 * sqliimport/addimport (1.x): a request of an immediate import of a 1.x handler. The form is on the dashboard now
 * (sqliimport/list, "Request an import"); a GET goes there. A POST with the fields of 1.x (RequestImportButton,
 * ImportHandler, ImportOptions; checked by ezformtoken) still stores the request, if the user may use the handler
 * (SQLIImport_Type), and then goes to the dashboard.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$target = expImportLegacy::redirectFor( 'addimport' );
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST' && $http->hasPostVariable( 'RequestImportButton' ) )
{
    $handler = $http->hasPostVariable( 'ImportHandler' ) ? (string)$http->postVariable( 'ImportHandler' ) : '';
    if ( !expImportAdmin::can( 'manage', $handler ) )
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    try
    {
        $item = expImportLegacy::requestImport( $handler, $http->hasPostVariable( 'ImportOptions' ) ? (string)$http->postVariable( 'ImportOptions' ) : '' );
        expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x import #%id of "%handler" is requested; the next cron round starts it.', null,
                                                   array( '%id' => $item->attribute( 'id' ), '%handler' => expImportLegacy::handlerName( $handler ) ) ) );
        $target = expImportLegacy::redirectFor( 'items' );
    }
    catch ( Exception $e )
    {
        SQLIImportLogger::writeError( $e->getMessage() );
        expImportLegacy::flash( 'bad', $e->getMessage() );
    }
}
$Module->redirectTo( $target );
