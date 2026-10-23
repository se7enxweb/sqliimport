<?php
/**
 * sqliimport/mapping/<import id>: the import's mapping as a JSON file, to load in the editor of another import or
 * installation ("Load a mapping"). A GET that changes nothing; who may look at imports may download it.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$import = expImportSchema::installed() ? expImportDefinition::fetch( (int)$Params['ImportID'] ) : null;
if ( !$import || !$import->mappingArray() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
$json = json_encode( expImportEditor::mappingFile( $import ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
expImport::discardOutput();
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="sqliimport-mapping-' . preg_replace( '/[^a-z0-9_-]+/i', '-', (string)$import->attribute( 'identifier' ) ) . '.json"' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store' );
echo $json;
eZExecution::cleanExit();
