<?php
/**
 * sqliimport/addscheduled/<id> (1.x): a 1.x scheduled import. Its form is part of sqliimport/schedules now
 * (sqliimport/schedules/(legacy)/<id>, or the import's schedule in the editor once it was migrated); a GET goes there.
 * A POST with the fields of 1.x (RequestScheduledImportButton, ImportHandler, ImportOptions, ScheduledDate,
 * ScheduledHour, ScheduledMinute, ScheduledFrequency, ManualScheduledFrequency, ScheduledLabel, ScheduledActive;
 * checked by ezformtoken) still stores it, if the user may use the handler (SQLIImport_Type), and then goes to
 * sqliimport/schedules; a value that is not valid goes back to the form with the message.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$id = isset( $Params['ScheduledImportID'] ) ? (int)$Params['ScheduledImportID'] : 0;
$params = array( 'ScheduledImportID' => $id );
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST' && $http->hasPostVariable( 'RequestScheduledImportButton' ) )
{
    $in = expImportLegacy::postedScheduleFields( $http );
    $handler = isset( $in['ImportHandler'] ) ? (string)$in['ImportHandler'] : '';
    $existing = $id ? SQLIScheduledImport::fetch( $id ) : null;
    // the handler it has and the one it gets: the user must be allowed both
    if ( !expImportAdmin::can( 'manage', $handler )
         || ( $existing instanceof SQLIScheduledImport && !expImportAdmin::can( 'manage', (string)$existing->attribute( 'handler' ) ) ) )
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    try
    {
        $scheduled = expImportLegacy::saveScheduled( $existing instanceof SQLIScheduledImport ? $id : 0, $in );
        expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import "%name" is saved.', null,
                                                   array( '%name' => $scheduled->attribute( 'label' ) !== '' ? $scheduled->attribute( 'label' ) : '#' . $scheduled->attribute( 'id' ) ) ) );
        return $Module->redirectTo( expImportLegacy::redirectFor( 'scheduledlist' ) );
    }
    catch ( Exception $e )
    {
        SQLIImportLogger::writeError( $e->getMessage() );
        expImportLegacy::flash( 'bad', $e->getMessage() );
    }
    return $Module->redirectTo( expImportLegacy::redirectFor( 'addscheduled', $params ) );
}
$Module->redirectTo( expImportLegacy::redirectFor( 'addscheduled', $params, array(), expImportLegacy::migratedImportID( $id ) ) );
