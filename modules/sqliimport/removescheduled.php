<?php
/**
 * sqliimport/removescheduled/<id> (1.x): remove a 1.x scheduled import. The button is on sqliimport/schedules now.
 * A POST (checked by ezformtoken) still removes it, if the user may use its handler (SQLIImport_Type); then, as a GET,
 * it goes to sqliimport/schedules.
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
    $scheduled = SQLIScheduledImport::fetch( $id );
    if ( !$scheduled instanceof SQLIScheduledImport )
        expImportLegacy::flash( 'bad', ezpI18n::tr( 'extension/sqliimport/error', 'No import item found with ID #%importID', null, array( '%importID' => $id ) ) );
    else
    {
        if ( !expImportAdmin::can( 'manage', (string)$scheduled->attribute( 'handler' ) ) )
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        expImportLegacy::removeScheduled( $scheduled );
        expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import #%id is deleted.', null, array( '%id' => $id ) ) );
    }
}
$Module->redirectTo( expImportLegacy::redirectFor( 'removescheduled' ) );
