<?php
/**
 * sqliimport/schedules: every schedule of the 2.x imports with its next run, Pause, Resume and Edit; and the 1.x
 * scheduled imports that were not migrated yet.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( !expImportSchema::installed() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
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
$legacy = 0;
foreach ( (array)SQLIScheduledImport::fetchList( 0, null, array( 'is_active' => 1 ) ) as $old )
    if ( !expImportDefinition::fetchCount( array( 'legacy_scheduled_id' => (int)$old->attribute( 'id' ) ) ) )
        $legacy++;
$http = eZHTTPTool::instance();
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );
$tpl = eZTemplate::factory();
$tpl->setVariable( 'schedules', $rows );
$tpl->setVariable( 'legacy_count', $legacy );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'here', '/sqliimport/schedules' );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/schedules.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Schedules' ) ) );
