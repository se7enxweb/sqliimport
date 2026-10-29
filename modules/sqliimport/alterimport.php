<?php
/**
 * sqliimport/alterimport/<action>/<id> (1.x): cancel a pending 1.x import request or interrupt a running one. The
 * buttons are on the dashboard now (sqliimport/list, "1.x import requests"). A POST (checked by ezformtoken) still
 * does it, if the user may use the handler (SQLIImport_Type), and cancels the 2.x run of the request; then, as a GET,
 * it goes to the dashboard.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST' )
{
    $id = (int)$Params['ImportID'];
    $item = SQLIImportItem::fetch( $id );
    if ( !$item instanceof SQLIImportItem )
        expImportLegacy::flash( 'bad', ezpI18n::tr( 'extension/sqliimport/error', 'No import item found with ID #%importID', null, array( '%importID' => $id ) ) );
    else
    {
        if ( !expImportAdmin::can( 'manage', (string)$item->attribute( 'handler' ) ) )
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        try
        {
            expImportLegacy::alterItem( $item, (string)$Params['Action'] );
            expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x import #%id is %status.', null,
                                                       array( '%id' => $id, '%status' => ezpI18n::tr( 'extension/sqliimport/type', (string)$item->attribute( 'status_string' ) ) ) ) );
        }
        catch ( Exception $e )
        {
            SQLIImportLogger::writeError( $e->getMessage() );
            expImportLegacy::flash( 'bad', $e->getMessage() );
        }
    }
}
$Module->redirectTo( expImportLegacy::redirectFor( 'alterimport' ) );
