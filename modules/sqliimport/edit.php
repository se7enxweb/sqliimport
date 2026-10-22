<?php
/**
 * sqliimport/edit/<id>/(step)/<step>, sqliimport/edit/new: the import editor (expImportEditor). Every step is a form
 * saved on its own (POST, checked by ezformtoken); Save goes to the next step, Save and stay keeps it, Auto-match
 * fills the mapping from the sample, Save and run starts a run at the end.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
if ( !expImportSchema::installed() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );

$ref = isset( $Params['ImportID'] ) ? (string)$Params['ImportID'] : 'new';
$import = null;
if ( $ref !== 'new' && $ref !== '' )
{
    $import = expImportDefinition::fetch( (int)$ref );
    if ( !$import )
        return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}
if ( !expImportAdmin::can( 'edit', $import ? (string)$import->attribute( 'handler' ) : '' ) )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );

$editor = new expImportEditor( $import, $http );
$step = isset( $Params['Step'] ) && in_array( $Params['Step'], expImportEditor::STEPS, true ) ? (string)$Params['Step'] : 'source';
if ( !$import )
    $step = 'source';
if ( !in_array( $step, $editor->steps(), true ) )
    $step = $editor->next( $step );

$isPost = strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST';
$sample = null;
$sampleError = '';
$preview = null;
$previewError = '';
if ( $isPost && $http->hasPostVariable( 'Step' ) )
{
    $step = in_array( $http->postVariable( 'Step' ), expImportEditor::STEPS, true ) ? (string)$http->postVariable( 'Step' ) : $step;
    $ok = false;
    switch ( $step )
    {
        case 'source':
            $ok = $editor->saveSource();
            break;
        case 'processing':
            $ok = $editor->saveProcessing();
            break;
        case 'mapping':
            if ( $http->hasPostVariable( 'LoadMappingButton' ) )
            {
                // a mapping file of another import or installation replaces the lines; the page stays to show them
                if ( $editor->loadMappingUpload() === null )
                    $editor->errors['MappingFile'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose the mapping file (JSON) to load.' );
                $ok = false;
                break;
            }
            $fields = array();
            if ( $http->hasPostVariable( 'AutoMatchButton' ) )
            {
                try
                {
                    $fields = expImportPreview::sample( $editor->import, 20 )['fields'];
                }
                catch ( Exception $e )
                {
                    $editor->errors['Sample'] = $e->getMessage();
                }
            }
            $ok = $editor->saveMapping( $http->hasPostVariable( 'AutoMatchButton' ), $fields );
            if ( $http->hasPostVariable( 'AutoMatchButton' ) )
                $ok = false;
            break;
        case 'preview':
            $ok = true;
            break;
        case 'schedule':
            $ok = $editor->saveSchedule();
            break;
        case 'review':
            $ok = $editor->saveReview();
            if ( $ok && $http->hasPostVariable( 'SaveAndRunButton' ) && (int)$editor->import->attribute( 'is_enabled' ) )
            {
                try
                {
                    $run = expImportRunner::start( $editor->import, array( 'trigger' => 'manual' ) );
                    $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => 'ok', 'message' => ezpI18n::tr( 'extension/sqliimport', 'Run #%id of "%name" is queued.', null,
                                                                       array( '%id' => $run->attribute( 'id' ), '%name' => $editor->import->attribute( 'name' ) ) ) ) );
                    $Module->redirectTo( '/sqliimport/run/' . (int)$run->attribute( 'id' ) );
                    return;
                }
                catch ( expImportException $e )
                {
                    $editor->errors['Run'] = $e->getMessage();
                    $ok = false;
                }
            }
            if ( $ok )
            {
                $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => 'ok', 'message' => ezpI18n::tr( 'extension/sqliimport', 'The import "%name" is saved.', null,
                                                                   array( '%name' => $editor->import->attribute( 'name' ) ) ) ) );
                $Module->redirectTo( '/sqliimport/list' );
                return;
            }
            break;
    }
    if ( $ok )
    {
        $target = $http->hasPostVariable( 'StayButton' ) ? $step : $editor->next( $step );
        $Module->redirectTo( '/sqliimport/edit/' . (int)$editor->import->attribute( 'id' ) . '/(step)/' . $target );
        return;
    }
}

// what the step shows
$registry = expImportRegistry::instance();
$registry->registerTransforms();
if ( $editor->import && in_array( $step, array( 'mapping', 'preview' ), true ) && !$editor->import->isLegacy() )
{
    try
    {
        $sample = expImportPreview::sample( $editor->import, 20 );
    }
    catch ( Exception $e )
    {
        $sampleError = $e->getMessage();
    }
}
if ( $editor->import && $step === 'preview' && !$editor->import->isLegacy() )
{
    try
    {
        $preview = expImportPreview::preview( $editor->import, (int)expImport::setting( 'RunSettings', 'DryRunRows', 20 ) );
    }
    catch ( Exception $e )
    {
        $previewError = $e->getMessage();
    }
}
$mapping = $editor->mappingForForm();
$classes = array();
foreach ( eZContentClass::fetchList( eZContentClass::VERSION_STATUS_DEFINED, true, false, array( 'name' => 'asc' ) ) as $class )
    $classes[] = array( 'identifier' => $class->attribute( 'identifier' ), 'name' => $class->attribute( 'name' ) );
$languages = array();
foreach ( eZContentLanguage::fetchList() as $language )
    $languages[] = array( 'locale' => $language->attribute( 'locale' ), 'name' => $language->attribute( 'name' ) );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'import', $editor->import );
$tpl->setVariable( 'step', $step );
$tpl->setVariable( 'steps', $editor->steps() );
$tpl->setVariable( 'errors', $editor->errors );
$tpl->setVariable( 'notice', $editor->notice );
$sources = $registry->sources();
$tpl->setVariable( 'sources', $sources );
$tpl->setVariable( 'sources_by_group', $registry->sourcesByGroup() );
// a new import opened from elsewhere: a format ((source) or (preset)), a folder ((parent)), a class ((class)), a file
// another tool hands over ((from)/<tool>/(job)/<id>). Unknown or refused values are ignored: the form opens empty.
$param = function ( $name ) use ( $Params )
{
    return isset( $Params[$name] ) && $Params[$name] !== false ? trim( (string)$Params[$name] ) : '';
};
$preselect = 'csv';
foreach ( array( 'Source', 'Preset' ) as $name )
    if ( $param( $name ) !== '' && isset( $sources[$param( $name )] ) )
        $preselect = $param( $name );
$entryParent = 0;
$entryParentName = '';
$entryClass = '';
$handover = null;
if ( !$editor->import )
{
    $node = ctype_digit( $param( 'Parent' ) ) ? eZContentObjectTreeNode::fetch( (int)$param( 'Parent' ) ) : null;
    if ( $node instanceof eZContentObjectTreeNode && $node->canCreate() )
    {
        $entryParent = (int)$node->attribute( 'node_id' );
        $entryParentName = (string)$node->attribute( 'name' );
    }
    if ( preg_match( '/^[a-z0-9_]+$/i', $param( 'Class' ) ) && eZContentClass::fetchByIdentifier( $param( 'Class' ) ) )
        $entryClass = $param( 'Class' );
    if ( $param( 'From' ) !== '' && $param( 'Job' ) !== '' && !$isPost )
    {
        try
        {
            $handover = expImportExternalFile::handOver( $param( 'From' ), $param( 'Job' ), $sources[$preselect]['extensions'] );
        }
        catch ( expImportSourceException $e )
        {
            $handover = null;
            eZDebug::writeNotice( 'hand-over refused: ' . $e->getMessage(), 'sqliimport/edit' );
        }
        if ( !$handover )
            $tpl->setVariable( 'notice', ezpI18n::tr( 'extension/sqliimport/edit', 'The file of %tool job %job is not available to you; upload the file instead.',
                                                       null, array( '%tool' => $param( 'From' ), '%job' => $param( 'Job' ) ) ) );
    }
    elseif ( $isPost || $param( 'From' ) === '' )
        $handover = expImportExternalFile::pending();
    // the format of a tool's files, when the link names none
    $toolFormats = array( 'xrowextract' => 'xrowextract', 'bccie' => 'cie' );
    if ( $handover && $param( 'Preset' ) === '' && $param( 'Source' ) === '' && isset( $toolFormats[$handover['tool']], $sources[$toolFormats[$handover['tool']]] ) )
        $preselect = $toolFormats[$handover['tool']];
}
$tpl->setVariable( 'source_type', $editor->import ? (string)$editor->import->attribute( 'source_type' ) : $preselect );
$tpl->setVariable( 'entry_parent', $entryParent );
$tpl->setVariable( 'entry_parent_name', $entryParentName );
$tpl->setVariable( 'entry_class', $entryClass );
$tpl->setVariable( 'handover', $handover );
$tpl->setVariable( 'handlers', $registry->handlers() );
$tpl->setVariable( 'transforms', expImportTransforms::catalogue() );
$tpl->setVariable( 'source', $editor->import ? $editor->import->sourceConfig() : array( 'origin' => 'upload' ) );
$tpl->setVariable( 'target', $editor->import ? $editor->import->target() : array( 'parent_node_id' => (int)expImport::setting( 'ImportSettings', 'DefaultParentNodeID', 2 ), 'section_id' => 0, 'language' => '', 'owner_id' => 0 ) );
$tpl->setVariable( 'behaviour', $editor->import ? $editor->import->behaviour() : array( 'chunk_size' => (int)expImport::setting( 'RunSettings', 'ChunkSize', 200 ), 'update_policy' => 'update', 'max_rows' => 0, 'conflict_policy' => 'overwrite', 'undo_days' => '', 'notify_when' => 'errors', 'notify_starter' => true, 'notify_emails' => array() ) );
$tpl->setVariable( 'options', $editor->import ? $editor->import->options() : array() );
$tpl->setVariable( 'legacy_options', $editor->import && $editor->import->isLegacy() ? $editor->import->options() : array() );
$tpl->setVariable( 'mapping', $mapping );
$tpl->setVariable( 'grid', expImportEditor::gridLines( $mapping ) );
$translationGrid = $editor->import ? expImportEditor::translationGrid( $mapping, $languages ) : array();
foreach ( $translationGrid as $i => $block )
    foreach ( $block['lines'] as $j => $line )
        $translationGrid[$i]['lines'][$j]['error'] = isset( $editor->errors['Translations_' . $block['locale'] . '_' . $line['attribute']] )
                                                   ? $editor->errors['Translations_' . $block['locale'] . '_' . $line['attribute']] : '';
$tpl->setVariable( 'translation_grid', $translationGrid );
$tpl->setVariable( 'classes', $classes );
$tpl->setVariable( 'languages', $languages );
$tpl->setVariable( 'sample', $sample );
$tpl->setVariable( 'sample_error', $sampleError );
$tpl->setVariable( 'preview', $preview );
$tpl->setVariable( 'preview_error', $previewError );
$tpl->setVariable( 'schedule', $editor->scheduleForForm() );
$tpl->setVariable( 'timezones', DateTimeZone::listIdentifiers() );
$tpl->setVariable( 'problems', $editor->import ? $editor->problems() : array() );
$tpl->setVariable( 'can_settings', expImportAdmin::can( 'settings' ) );
$tpl->setVariable( 'undo_days_default', (int)expImport::setting( 'RunSettings', 'UndoDays', 30 ) );
$tpl->setVariable( 'max_megabytes', (int)expImport::setting( 'SourceSettings', 'MaxSourceMegabytes', 200 ) );
$tpl->setVariable( 'allowed_paths', (array)expImport::setting( 'SourceSettings', 'AllowedPaths', array() ) );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/edit.tpl' );
$Result['path'] = array(
    array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
    array( 'url' => false, 'text' => $editor->import ? $editor->import->attribute( 'name' ) : ezpI18n::tr( 'extension/sqliimport', 'New import' ) ),
);
