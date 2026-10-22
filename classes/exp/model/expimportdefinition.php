<?php
/**
 * File containing the expImportDefinition class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * An import (sqliimport_definition): where the data comes from, what processes it (the no-code mapping or a handler),
 * where the content goes, how it behaves, and its typed options. Every change raises def_version, so a run records
 * the version of the definition it ran with.
 *
 *   source_type    csv, xml, rss, json ... (a registered source), or legacy for a 1.x handler that reads by itself
 *   source_config  JSON: 'origin' (upload, url, path), 'file' (an upload's stored name), 'url', 'path', and the
 *                  options of the source type
 *   handler        mapping (no code) or the id of a registered handler (a 2.x handler or a 1.x one)
 *   target_json    JSON: parent_node_id, section_id, language, owner_id (the robot user, 0 = the user who starts)
 *   behaviour_json JSON: chunk_size, update_policy (update, skip_existing, create_only), trash_policy (restore,
 *                  recreate, skip), max_rows
 *   options_json   JSON: the handler's options, validated against its schema
 */
class expImportDefinition extends eZPersistentObject
{
    /** The identifier of the import a 1.x handler gets when it runs through the 2.x engine */
    const LEGACY_PREFIX = 'legacy-';

    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_definition' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportDefinition',
            'name' => 'sqliimport_definition',
            'function_attributes' => array(
                'source' => 'sourceConfig',
                'target' => 'target',
                'behaviour' => 'behaviour',
                'options' => 'options',
                'mapping' => 'mappingArray',
                'is_legacy' => 'isLegacy',
                'last_run' => 'lastRun',
                'schedules' => 'schedules',
            ),
        );
    }

    /** @return expImportDefinition|null */
    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return expImportDefinition|null */
    public static function fetchByIdentifier( $identifier )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'identifier' => (string)$identifier ) );
    }

    /**
     * @param int $offset
     * @param int $limit
     * @return expImportDefinition[]
     */
    public static function fetchList( $offset = 0, $limit = 100, array $conds = array() )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, $conds ?: null, array( 'name' => 'asc' ),
                                                    array( 'offset' => (int)$offset, 'length' => (int)$limit ) );
    }

    /** @return int */
    public static function fetchCount( array $conds = array() )
    {
        return (int)eZPersistentObject::count( self::definition(), $conds ?: null );
    }

    /**
     * A new import with sensible defaults.
     *
     * @param array $row
     * @return expImportDefinition
     */
    public static function create( array $row )
    {
        $now = expImport::now();
        $user = expImport::currentUserID();
        $row += array( 'name' => '', 'identifier' => '', 'description' => '', 'source_type' => 'csv', 'source_config' => '{}',
                       'handler' => 'mapping', 'mapping_id' => 0, 'target_json' => '{}', 'behaviour_json' => '{}', 'options_json' => '{}',
                       'is_enabled' => 1, 'def_version' => 1, 'legacy_scheduled_id' => 0, 'created' => $now, 'creator_id' => $user,
                       'modified' => $now, 'modifier_id' => $user );
        if ( $row['identifier'] === '' )
            $row['identifier'] = self::uniqueIdentifier( $row['name'] !== '' ? $row['name'] : 'import' );
        return new self( $row );
    }

    /** @return string an identifier made from a name that no import has yet */
    public static function uniqueIdentifier( $name )
    {
        $base = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( (string)$name ) ), '_' );
        $base = substr( $base !== '' ? $base : 'import', 0, 80 );
        $identifier = $base;
        $n = 2;
        while ( self::fetchByIdentifier( $identifier ) )
            $identifier = $base . '_' . $n++;
        return $identifier;
    }

    /** Stores and raises the version (a run records which version it used). */
    public function storeChange()
    {
        $this->setAttribute( 'def_version', (int)$this->attribute( 'def_version' ) + 1 );
        $this->setAttribute( 'modified', expImport::now() );
        $this->setAttribute( 'modifier_id', expImport::currentUserID() );
        $this->store();
    }

    /** @return array */
    public function sourceConfig()
    {
        return expImport::decode( $this->attribute( 'source_config' ) );
    }

    /** @return array */
    public function target()
    {
        return expImport::decode( $this->attribute( 'target_json' ) ) + array( 'parent_node_id' => 0, 'section_id' => 0, 'language' => '', 'owner_id' => 0 );
    }

    /** @return array */
    public function behaviour()
    {
        return expImport::decode( $this->attribute( 'behaviour_json' ) ) + array(
            'chunk_size' => (int)expImport::setting( 'RunSettings', 'ChunkSize', 200 ),
            'update_policy' => 'update',
            'trash_policy' => 'skip',
            'max_rows' => 0,
            'conflict_policy' => 'overwrite',
            'undo_days' => '',
        );
    }

    /** @return array */
    public function options()
    {
        return expImport::decode( $this->attribute( 'options_json' ) );
    }

    /** @return array|null the no-code mapping as an array (expImportMapper), null when the import has none */
    public function mappingArray()
    {
        if ( !(int)$this->attribute( 'mapping_id' ) )
            return null;
        $mapping = expImportMapping::fetch( $this->attribute( 'mapping_id' ) );
        return $mapping ? $mapping->toArray() : null;
    }

    /** @return bool whether this import runs a 1.x handler */
    public function isLegacy()
    {
        return $this->attribute( 'source_type' ) === 'legacy';
    }

    /** @return expImportRun|null */
    public function lastRun()
    {
        $list = expImportRun::fetchList( array( 'import_id' => (int)$this->attribute( 'id' ) ), 0, 1 );
        return $list ? $list[0] : null;
    }

    /** @return expImportSchedule[] */
    public function schedules()
    {
        return expImportSchedule::fetchByImport( $this->attribute( 'id' ) );
    }

    /**
     * Removes the import with its mapping and schedules; its runs stay (their history) until they are purged.
     */
    public function removeAll()
    {
        $db = expImport::db();
        $db->begin();
        $id = (int)$this->attribute( 'id' );
        if ( (int)$this->attribute( 'mapping_id' ) )
        {
            $mapping = expImportMapping::fetch( $this->attribute( 'mapping_id' ) );
            if ( $mapping )
                $mapping->removeAll();
        }
        $db->query( 'DELETE FROM sqliimport_schedule WHERE import_id = ' . $id );
        $this->remove();
        $db->commit();
    }

    /** @return array the import as an exportable array (no IDs, no secrets), for Settings > export */
    public function export()
    {
        return array(
            'identifier' => $this->attribute( 'identifier' ),
            'name' => $this->attribute( 'name' ),
            'description' => $this->attribute( 'description' ),
            'source_type' => $this->attribute( 'source_type' ),
            'source' => $this->sourceConfig(),
            'handler' => $this->attribute( 'handler' ),
            'mapping' => $this->mappingArray(),
            'target' => $this->target(),
            'behaviour' => $this->behaviour(),
            'options' => $this->options(),
        );
    }
}
