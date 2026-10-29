<?php
/**
 * sqliimport/purgelist (1.x): remove the ended 1.x import requests. The button is on the dashboard now
 * (sqliimport/list, "1.x import requests"). A POST (checked by ezformtoken) still purges; then, as a GET, it goes to
 * the dashboard.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST' )
{
    try
    {
        expImportLegacy::purgeHistory();
        expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The history of the 1.x import requests is purged.' ) );
    }
    catch ( Exception $e )
    {
        SQLIImportLogger::writeError( $e->getMessage() );
        expImportLegacy::flash( 'bad', $e->getMessage() );
    }
}
$Module->redirectTo( expImportLegacy::redirectFor( 'purgelist' ) );
