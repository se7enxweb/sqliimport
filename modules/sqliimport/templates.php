<?php
/**
 * sqliimport/templates (policy Manage imports): the templates of imports (expImportTemplates), this site's ("Save as
 * template" of an import) and the ones extensions ship, filtered by (format); (use)/<template id> opens "New import
 * from a template": the placeholders (name, identifier and what the template declares) and what the template needs
 * on this site; Create makes the import, disabled, and opens it in the editor. Every POST carries the form token.
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

$isPost = strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST';
$templates = expImportTemplates::all();
$format = isset( $Params['Format'] ) && is_string( $Params['Format'] ) && preg_match( '/^[a-z0-9_]{1,50}$/', $Params['Format'] ) ? $Params['Format'] : '';
$useID = isset( $Params['Use'] ) && is_string( $Params['Use'] ) ? $Params['Use'] : '';
if ( $isPost && $http->hasPostVariable( 'TemplateID' ) )
    $useID = (string)$http->postVariable( 'TemplateID' );
$use = $useID !== '' && isset( $templates[$useID] ) ? $templates[$useID] : null;
if ( $useID !== '' && !$use )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );

$errors = array();
$values = array();
$check = null;
if ( $use )
{
    foreach ( $use['placeholders'] as $name => $def )
        $values[$name] = $def['default'];
    if ( $isPost && $http->hasPostVariable( 'Placeholders' ) )
        foreach ( (array)$http->postVariable( 'Placeholders' ) as $name => $value )
            if ( isset( $values[$name] ) && is_scalar( $value ) )
                $values[$name] = (string)$value;
    $site = expImportTransfer::site();
    if ( $isPost && $http->hasPostVariable( 'CreateButton' ) )
    {
        try
        {
            $item = expImportTemplates::fill( $use['template'], $values );
            $resolved = expImportTransfer::resolve( $item, $site );
            if ( $site->importId( $item['identifier'] ) )
                $errors['identifier'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Another import has this identifier.' );
            elseif ( !expImportAdmin::can( 'edit', (string)$resolved['import']['handler'] ) )
                $errors['Template'] = ezpI18n::tr( 'extension/sqliimport/edit', 'You may not edit imports of this handler.' );
            foreach ( $resolved['errors'] as $n => $message )
                $errors['Template_' . $n] = $message;
            if ( !$errors )
            {
                $import = expImportTransfer::store( $resolved, $item['identifier'] );
                expImport::audit( 'import.create', array( 'id' => (int)$import->attribute( 'id' ), 'identifier' => (string)$import->attribute( 'identifier' ),
                                                          'template' => $use['id'] ) );
                $message = ezpI18n::tr( 'extension/sqliimport', '"%name" is made from the template "%title", disabled: finish it in the editor.', null,
                                        array( '%name' => $import->attribute( 'name' ), '%title' => $use['title'] ) );
                $http->setSessionVariable( 'SQLIImportFlash', array( 'kind' => $resolved['warnings'] ? 'warn' : 'ok',
                                                                     'message' => $message . ( $resolved['warnings'] ? ' ' . implode( '; ', $resolved['warnings'] ) : '' ) ) );
                $Module->redirectTo( '/sqliimport/edit/' . (int)$import->attribute( 'id' ) . '/(step)/source' );
                return;
            }
        }
        catch ( expImportValidationException $e )
        {
            $errors = $e->errors;
        }
    }
    // what the template needs here, with the values as they stand (placeholders the person has not filled in yet
    // take a stand-in, so the check can run)
    $stand = array();
    foreach ( $use['placeholders'] as $name => $def )
        $stand[$name] = $values[$name] !== '' ? $values[$name] : ( $name === 'identifier' ? 'template_check' : 'x' );
    try
    {
        $check = expImportTransfer::resolve( expImportTemplates::fill( $use['template'], $stand ), $site );
    }
    catch ( expImportValidationException $e )
    {
        $check = array( 'errors' => array_values( $e->errors ), 'warnings' => array(), 'class' => '', 'class_found' => null, 'secrets' => array() );
    }
}

$list = array();
foreach ( $templates as $id => $t )
    if ( $format === '' || $t['source_type'] === $format )
        $list[$id] = $t;
$sources = expImportRegistry::instance()->sources();
$flash = $http->hasSessionVariable( 'SQLIImportFlash' ) ? $http->sessionVariable( 'SQLIImportFlash' ) : null;
if ( $flash )
    $http->removeSessionVariable( 'SQLIImportFlash' );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'templates', $list );
$tpl->setVariable( 'count_all', count( $templates ) );
$tpl->setVariable( 'format', $format );
$tpl->setVariable( 'format_label', $format !== '' && isset( $sources[$format] ) ? (string)$sources[$format]['label'] : $format );
$tpl->setVariable( 'source_labels', array_map( function ( $s ) { return (string)$s['label']; }, $sources ) );
$tpl->setVariable( 'use', $use );
$tpl->setVariable( 'values', $values );
$tpl->setVariable( 'check', $check );
$tpl->setVariable( 'errors', $errors );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'directory', expImportTemplates::directory() );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/templates.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => $use ? 'sqliimport/templates' : false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Templates' ) ) );
if ( $use )
    $Result['path'][] = array( 'url' => false, 'text' => $use['title'] );
