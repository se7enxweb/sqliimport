<?php
/**
 * sqliimport/schedules: every schedule of the 2.x imports with its next run, Pause, Resume and Edit; and the scheduled
 * imports of 1.x (sqliimport_scheduled), which were sqliimport/scheduledlist: Run now, Edit, Enable or Disable and
 * Delete. sqliimport/schedules/(legacy)/<id> (or /new) shows the form of a 1.x scheduled import above the lists (it
 * was sqliimport/addscheduled); its POST (LegacyScheduleSaveButton, the field names of 1.x, checked by ezformtoken) is
 * handled here, so a value that is not valid is shown again with its message.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( !expImportSchema::installed() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
$http = eZHTTPTool::instance();

// the form of a 1.x scheduled import: (legacy)/new or (legacy)/<id>
$legacyRef = isset( $Params['Legacy'] ) ? (string)$Params['Legacy'] : '';
$legacyForm = null;
$legacyErrors = array();
if ( $legacyRef !== '' )
{
    $scheduled = null;
    if ( $legacyRef !== 'new' )
    {
        $scheduled = SQLIScheduledImport::fetch( (int)$legacyRef );
        if ( !$scheduled instanceof SQLIScheduledImport )
            return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
        if ( !expImportAdmin::can( 'manage', (string)$scheduled->attribute( 'handler' ) ) )
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    }
    elseif ( !expImportLegacy::handlers( 'manage' ) )
    {
        // no 1.x handler is set up (or none this user may manage): say so instead of refusing the page
        if ( !expImportAdmin::can( 'manage' ) )
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        expImportLegacy::flash( 'info', ezpI18n::tr( 'extension/sqliimport', 'There is no 1.x handler to schedule: the site lists none in sqliimport.ini [ImportSettings] AvailableSourceHandlers[]. Schedule a 2.x import in its editor instead.' ) );
        $Module->redirectTo( '/sqliimport/schedules' );
        return;
    }
    $posted = null;
    if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST' && $http->hasPostVariable( 'LegacyScheduleSaveButton' ) )
    {
        $posted = expImportLegacy::postedScheduleFields( $http );
        $handler = isset( $posted['ImportHandler'] ) ? (string)$posted['ImportHandler'] : '';
        if ( !expImportAdmin::can( 'manage', $handler ) )
            return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        try
        {
            $saved = expImportLegacy::saveScheduled( $scheduled ? (int)$scheduled->attribute( 'id' ) : 0, $posted );
            expImportLegacy::flash( 'ok', ezpI18n::tr( 'extension/sqliimport', 'The 1.x scheduled import "%name" is saved.', null,
                                                       array( '%name' => $saved->attribute( 'label' ) !== '' ? $saved->attribute( 'label' ) : '#' . $saved->attribute( 'id' ) ) ) );
            $Module->redirectTo( '/sqliimport/schedules' );
            return;
        }
        catch ( expImportValidationException $e )
        {
            $legacyErrors = $e->errors;
        }
    }
    $legacyForm = expImportLegacy::form( $scheduled, $posted );
}

$rows = array();
foreach ( expImportSchedule::fetchAll( 0, 500 ) as $s )
{
    $import = $s->import();
    if ( !$import )
        continue;
    $rows[] = array( 'id' => (int)$s->attribute( 'id' ), 'import' => $import, 'description' => $s->describe(), 'is_active' => (bool)$s->attribute( 'is_active' ),
                     'next_run' => (int)$s->attribute( 'next_run' ), 'last_run' => (int)$s->attribute( 'last_run' ),
                     'next' => (int)$s->attribute( 'is_active' ) ? expImportEditor::nextTimes( $s->calcArray(), 3 ) : array(),
                     'can_edit' => expImportAdmin::can( 'edit', (string)$import->attribute( 'handler' ) ) );
}
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );
$tpl = eZTemplate::factory();
$tpl->setVariable( 'schedules', $rows );
$tpl->setVariable( 'legacy_schedules', expImportLegacy::scheduled() );
$tpl->setVariable( 'legacy_form', $legacyForm );
$tpl->setVariable( 'legacy_errors', $legacyErrors );
$tpl->setVariable( 'legacy_form_handlers', $legacyForm ? expImportLegacy::handlers( 'manage' ) : array() );
$tpl->setVariable( 'can_add_legacy', (bool)expImportLegacy::handlers( 'manage' ) );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'here', '/sqliimport/schedules' );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/schedules.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Schedules' ) ) );
