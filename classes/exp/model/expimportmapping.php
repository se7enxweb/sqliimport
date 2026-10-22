<?php
/**
 * File containing the expImportMapping and expImportMappingField classes.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The no-code mapping of an import (sqliimport_mapping, one row) and its lines (sqliimport_mapping_field). toArray()
 * gives the array expImportMapper validates and applies; saveArray() stores one.
 */
class expImportMapping extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_mapping' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportMapping',
            'name' => 'sqliimport_mapping',
            'function_attributes' => array( 'fields' => 'fieldRows', 'as_array' => 'toArray' ),
        );
    }

    /** @return expImportMapping|null */
    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return expImportMappingField[] */
    public function fieldRows()
    {
        return eZPersistentObject::fetchObjectList( expImportMappingField::definition(), null,
                                                    array( 'mapping_id' => (int)$this->attribute( 'id' ) ), array( 'sort_order' => 'asc', 'id' => 'asc' ) );
    }

    /** @return array the mapping for expImportMapper */
    public function toArray()
    {
        $match = expImport::decode( $this->attribute( 'match_key' ) );
        $fields = array();
        foreach ( $this->fieldRows() as $row )
        {
            $fields[] = array(
                'source' => $row->attribute( 'source_path' ),
                'attribute' => $row->attribute( 'attribute' ),
                'transforms' => expImport::decode( $row->attribute( 'transforms_json' ) ),
                'default' => (string)$row->attribute( 'default_value' ),
                'required' => (bool)$row->attribute( 'is_required' ),
                'language' => $row->attribute( 'language' ),
            );
        }
        return array(
            'class' => $this->attribute( 'class_identifier' ),
            'language' => $this->attribute( 'language' ),
            'match' => $match,
            'fields' => $fields,
            'location' => expImport::decode( $this->attribute( 'location_json' ) ),
        );
    }

    /**
     * Stores a mapping array (replaces its lines).
     *
     * @param array $mapping
     * @param int $importID
     * @param expImportMapping|null $existing
     * @return expImportMapping
     */
    public static function saveArray( array $mapping, $importID, ?expImportMapping $existing = null )
    {
        $now = expImport::now();
        $db = expImport::db();
        $db->begin();
        $row = $existing ?: new self( array( 'created' => $now, 'import_id' => (int)$importID ) );
        $row->setAttribute( 'import_id', (int)$importID );
        $row->setAttribute( 'class_identifier', isset( $mapping['class'] ) ? (string)$mapping['class'] : '' );
        $row->setAttribute( 'language', isset( $mapping['language'] ) ? (string)$mapping['language'] : '' );
        // the match rule is small JSON; it lives in match_key (varchar 255)
        $row->setAttribute( 'match_key', substr( expImport::json( isset( $mapping['match'] ) ? $mapping['match'] : array() ), 0, 255 ) );
        $row->setAttribute( 'location_json', expImport::json( isset( $mapping['location'] ) ? $mapping['location'] : array() ) );
        $row->setAttribute( 'modified', $now );
        $row->store();
        $id = (int)$row->attribute( 'id' );
        $db->query( 'DELETE FROM sqliimport_mapping_field WHERE mapping_id = ' . $id );
        foreach ( array_values( isset( $mapping['fields'] ) ? $mapping['fields'] : array() ) as $i => $field )
        {
            $line = new expImportMappingField( array(
                'mapping_id' => $id,
                'sort_order' => $i,
                'source_path' => substr( isset( $field['source'] ) ? (string)$field['source'] : '', 0, 255 ),
                'attribute' => substr( isset( $field['attribute'] ) ? (string)$field['attribute'] : '', 0, 100 ),
                'transforms_json' => expImport::json( isset( $field['transforms'] ) ? array_values( (array)$field['transforms'] ) : array() ),
                'default_value' => isset( $field['default'] ) ? (string)$field['default'] : '',
                'is_required' => !empty( $field['required'] ) ? 1 : 0,
                'language' => isset( $field['language'] ) ? (string)$field['language'] : '',
            ) );
            $line->store();
        }
        $db->commit();
        return $row;
    }

    /** Removes the mapping with its lines. */
    public function removeAll()
    {
        expImport::db()->query( 'DELETE FROM sqliimport_mapping_field WHERE mapping_id = ' . (int)$this->attribute( 'id' ) );
        $this->remove();
    }
}

/** One line of a mapping (sqliimport_mapping_field). */
class expImportMappingField extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_mapping_field' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportMappingField',
            'name' => 'sqliimport_mapping_field',
        );
    }
}
