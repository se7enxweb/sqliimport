<?php
/**
 * File containing the expImportMappingHandler class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The no-code handler "mapping": it applies the import's mapping (expImportMapper) to each record and publishes the
 * result with the content API of 1.x (SQLIContent, SQLIContentPublisher), which publishes a new version only when a
 * field really changed. No PHP is written for an import that uses it.
 *
 * Per record:
 *   - the object is found by its remote ID (the mapping's pattern) or by an attribute's value;
 *   - an object in the trash is skipped (trash_policy skip, the default) and reported;
 *   - update_policy create_only skips existing objects, update (the default) publishes what changed;
 *   - a new object goes below the record's parent (a remote ID) or the mapping's parent node, with further parents as
 *     more locations;
 *   - in a dry run nothing is written: the row tells what would happen, with the changed fields (old and new value).
 */
class expImportMappingHandler implements expImportHandlerInterface
{
    /** @var array the mapping of the import */
    protected $mapping;

    /** @var array identifier => datatype of the target class */
    protected $attributes = array();

    public static function identifier()
    {
        return 'mapping';
    }

    public static function label()
    {
        return 'Mapping to a content class (no code)';
    }

    public static function schema()
    {
        return array();
    }

    public function open( expImportContext $context )
    {
        $mapping = $context->import->mappingArray();
        if ( !$mapping )
            throw new expImportException( 'the import has no mapping' );
        $errors = expImportMapper::validate( $mapping, self::classAttributes( $mapping['class'] ) );
        if ( $errors )
            throw new expImportValidationException( $errors, 'the mapping is not valid: ' . implode( '; ', $errors ) );
        $this->mapping = $mapping;
        $this->attributes = self::classAttributes( $mapping['class'] );
        expImportTransforms::$lookup = function ( $remoteID )
        {
            $object = eZContentObject::fetchByRemoteID( $remoteID, false );
            return is_array( $object ) ? (int)$object['id'] : 0;
        };
        $run = $context->run;
        // a dry run downloads nothing: the address stands for the file
        expImportTransforms::$download = $context->dryRun ? function ( $url, $kind ) { return $url; } : function ( $url, $kind ) use ( $run )
        {
            return expImportSourceFiles::downloadAsset( $url, $kind, $run );
        };
    }

    /**
     * The attributes of a class, identifier => datatype string; null when the class does not exist.
     *
     * @param string $identifier
     * @return array|null
     */
    public static function classAttributes( $identifier )
    {
        $class = eZContentClass::fetchByIdentifier( (string)$identifier );
        if ( !$class instanceof eZContentClass )
            return null;
        $out = array();
        foreach ( $class->fetchAttributes() as $attribute )
            $out[$attribute->attribute( 'identifier' )] = $attribute->attribute( 'data_type_string' );
        return $out;
    }

    public function process( expImportRecord $record, expImportContext $context )
    {
        $plan = expImportMapper::apply( $this->mapping, $record );
        $language = $this->mapping['language'] !== '' ? $this->mapping['language']
                  : ( $context->target['language'] !== '' ? $context->target['language'] : eZContentObject::defaultLanguage() );

        $object = $this->findObject( $plan );
        if ( $object && (int)$object->attribute( 'status' ) === eZContentObject::STATUS_ARCHIVED )
            return expImportRowResult::skipped( 'the object is in the trash', $object->attribute( 'id' ) );
        if ( $object && $context->behaviour['update_policy'] === 'create_only' )
            return expImportRowResult::skipped( 'the object exists (the import only creates)', $object->attribute( 'id' ) );
        if ( !$object && $context->behaviour['update_policy'] === 'update_only' )
            return expImportRowResult::skipped( 'no object to update' );

        $parents = $this->parentNodes( $plan['parents'] );
        if ( !$object && !$parents )
            throw new expImportException( 'the parent of the record does not exist (yet)' );

        if ( $context->dryRun )
            return $this->dryRunResult( $object, $plan, $language );

        $versionBefore = $object ? (int)$object->attribute( 'current_version' ) : 0;
        $options = array( 'class_identifier' => $this->mapping['class'], 'language' => $language );
        if ( $plan['remote_id'] !== '' )
            $options['remote_id'] = $plan['remote_id'];
        if ( (int)$context->target['section_id'] )
            $options['section_id'] = (int)$context->target['section_id'];
        $options['creator_id'] = (int)$context->target['owner_id'] ?: expImport::currentUserID();
        $content = $object ? SQLIContent::fromContentObject( $object ) : SQLIContent::create( new SQLIContentOptions( $options ) );
        $content->setActiveLanguage( $language );
        foreach ( $plan['fields'] as $fieldLanguage => $fields )
        {
            $fieldLanguage = $fieldLanguage === '' ? $language : $fieldLanguage;
            if ( $fieldLanguage !== $language && !isset( $content->fields[$fieldLanguage] ) )
                $content->addTranslation( $fieldLanguage );
            foreach ( $fields as $attribute => $value )
                $content->fields[$fieldLanguage]->$attribute = $value;
        }
        $isNew = $content->isNew();
        $nodesAdded = array();
        if ( $isNew )
        {
            $main = array_shift( $parents );
            foreach ( $parents as $nodeID )
                $content->addLocation( SQLILocation::fromNodeID( $nodeID ) );
        }
        $publisher = SQLIContentPublisher::getInstance();
        $publisher->setOptions( new SQLIContentPublishOptions( array( 'parent_node_id' => $isNew ? $main : null, 'modification_check' => true ) ) );
        $publisher->publish( $content );
        $objectID = (int)$content->id;
        $versionAfter = (int)$content->current_version;
        if ( $isNew )
            foreach ( $content->locations as $nodeID => $location )
                $nodesAdded[] = (int)$nodeID;
        $content->flush();
        unset( $content );

        if ( $isNew )
            $result = expImportRowResult::created( $objectID, $versionAfter );
        elseif ( $versionAfter !== $versionBefore )
            $result = expImportRowResult::updated( $objectID, $versionBefore, $versionAfter );
        else
            $result = expImportRowResult::unchanged( $objectID, $versionAfter );
        $result->remoteID = $plan['remote_id'] !== '' ? $plan['remote_id'] : (string)eZContentObject::fetch( $objectID, false )['remote_id'];
        $result->nodesAdded = $nodesAdded;
        return $result;
    }

    public function close( expImportContext $context )
    {
        expImportTransforms::$lookup = null;
        expImportTransforms::$download = null;
    }

    /** @return eZContentObject|null */
    protected function findObject( array $plan )
    {
        if ( $plan['remote_id'] !== '' )
        {
            $object = eZContentObject::fetchByRemoteID( $plan['remote_id'] );
            return $object instanceof eZContentObject ? $object : null;
        }
        list( $attribute, $value ) = $plan['match'];
        $db = eZDB::instance();
        $rows = $db->arrayQuery( "SELECT a.contentobject_id AS id FROM ezcontentobject_attribute a
            JOIN ezcontentobject o ON o.id = a.contentobject_id AND o.current_version = a.version
            JOIN ezcontentclass_attribute ca ON ca.id = a.contentclassattribute_id AND ca.version = 0
            JOIN ezcontentclass c ON c.id = o.contentclass_id AND c.version = 0
            WHERE c.identifier = '" . $db->escapeString( $this->mapping['class'] ) . "'
              AND ca.identifier = '" . $db->escapeString( $attribute ) . "'
              AND ( a.data_text = '" . $db->escapeString( $value ) . "' OR a.sort_key_string = '" . $db->escapeString( mb_strtolower( $value ) ) . "' )",
            array( 'limit' => 1 ) );
        return $rows ? eZContentObject::fetch( (int)$rows[0]['id'] ) : null;
    }

    /** @return int[] the node IDs of the parents (those that exist) */
    protected function parentNodes( array $parents )
    {
        $out = array();
        foreach ( $parents as $parent )
        {
            if ( isset( $parent['node'] ) )
            {
                if ( eZContentObjectTreeNode::fetch( (int)$parent['node'], false, false ) )
                    $out[] = (int)$parent['node'];
            }
            elseif ( isset( $parent['remote_id'] ) )
            {
                $object = eZContentObject::fetchByRemoteID( $parent['remote_id'] );
                if ( $object instanceof eZContentObject && $object->mainNodeID() )
                    $out[] = (int)$object->mainNodeID();
            }
        }
        return array_values( array_unique( $out ) );
    }

    /** @return expImportRowResult what the record would do, with the changed fields */
    protected function dryRunResult( ?eZContentObject $object, array $plan, $language )
    {
        if ( !$object )
        {
            $result = new expImportRowResult( expImportRowResult::CREATED, 'dry run: would be created' );
            foreach ( $plan['fields'] as $fields )
                foreach ( $fields as $attribute => $value )
                    $result->diff[$attribute] = array( '', $value );
            $result->remoteID = $plan['remote_id'];
            return $result;
        }
        $diff = array();
        foreach ( $plan['fields'] as $fieldLanguage => $fields )
        {
            $dataMap = $object->fetchDataMap( false, $fieldLanguage === '' ? $language : $fieldLanguage );
            foreach ( $fields as $attribute => $value )
            {
                $old = isset( $dataMap[$attribute] ) ? (string)$dataMap[$attribute]->toString() : '';
                if ( $old !== (string)$value )
                    $diff[$attribute] = array( $old, $value );
            }
        }
        $result = new expImportRowResult( $diff ? expImportRowResult::UPDATED : expImportRowResult::UNCHANGED,
                                          $diff ? 'dry run: would be updated' : 'dry run: unchanged' );
        $result->objectID = (int)$object->attribute( 'id' );
        $result->versionBefore = $result->versionAfter = (int)$object->attribute( 'current_version' );
        $result->diff = $diff;
        $result->remoteID = (string)$object->attribute( 'remote_id' );
        return $result;
    }
}
