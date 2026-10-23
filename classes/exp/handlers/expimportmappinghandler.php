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
        // the editor's preview (a run without an id) starts its plan afresh; a persistent worker keeps statics
        unset( self::$planned['0:' . (int)$context->import->attribute( 'id' )] );
        $mapping = $context->import->mappingArray();
        if ( !$mapping )
            throw new expImportException( 'the import has no mapping' );
        $errors = array_merge( expImportMapper::validate( $mapping, self::classAttributes( $mapping['class'] ) ),
                               expImportMapper::validateVariants( $mapping, function ( $class ) { return self::classAttributes( $class ); } ) );
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
        $parent = (int)( isset( $mapping['location']['parent_node_id'] ) && (int)$mapping['location']['parent_node_id'] ? $mapping['location']['parent_node_id']
                                                                                                                       : $context->target['parent_node_id'] );
        $owner = (int)$context->target['owner_id'] ?: expImport::currentUserID();
        expImportTransforms::$mediaObject = $context->dryRun
            ? function ( $url, array $args ) { return 0; }
            : function ( $url, array $args ) use ( $run, $parent, $owner )
            {
                return self::mediaObject( $url, $args, $run, $parent, $owner );
            };
    }

    /** @var array url => object ID of the media objects of this worker's runs */
    protected static $mediaObjects = array();

    /**
     * The media object (an image, by default) of a file at an address: made once per file, found again by the hash of
     * its content (remote ID sqliimport-media-<sha1>), so the same picture used by many records, runs or imports is
     * one object. Its ID is what an object relation takes.
     *
     * @param string $url
     * @param array $args parent, class, attribute, name_value
     * @param expImportRun $run
     * @param int $defaultParent
     * @param int $owner
     * @return int the object ID
     * @throws expImportTransformException
     */
    public static function mediaObject( $url, array $args, expImportRun $run, $defaultParent, $owner )
    {
        $key = (int)$run->attribute( 'id' ) . ':' . $url;
        if ( isset( self::$mediaObjects[$key] ) )
            return self::$mediaObjects[$key];
        $file = expImportSourceFiles::downloadAsset( $url, 'media_object', $run );
        $remoteID = 'sqliimport-media-' . sha1_file( $file );
        $existing = eZContentObject::fetchByRemoteID( $remoteID, false );
        if ( is_array( $existing ) )
            return self::$mediaObjects[$key] = (int)$existing['id'];
        $classIdentifier = isset( $args['class'] ) && $args['class'] !== '' ? (string)$args['class'] : 'image';
        $attribute = isset( $args['attribute'] ) && $args['attribute'] !== '' ? (string)$args['attribute'] : 'image';
        $attributes = self::classAttributes( $classIdentifier );
        if ( $attributes === null || !isset( $attributes[$attribute] ) )
            throw new expImportTransformException( "media_object: the class $classIdentifier has no attribute $attribute" );
        $parent = isset( $args['parent'] ) && (int)$args['parent'] ? (int)$args['parent'] : (int)$defaultParent;
        if ( $parent < 1 || !eZContentObjectTreeNode::fetch( $parent, false, false ) )
            throw new expImportTransformException( "media_object: no parent node $parent" );
        $name = isset( $args['name_value'] ) && trim( (string)$args['name_value'] ) !== '' ? trim( (string)$args['name_value'] )
              : ucfirst( trim( (string)preg_replace( '/[-_ ]+/', ' ', pathinfo( (string)parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) ) ) );
        $content = SQLIContent::create( new SQLIContentOptions( array( 'class_identifier' => $classIdentifier, 'remote_id' => $remoteID, 'creator_id' => (int)$owner ) ) );
        foreach ( array( 'name', 'title' ) as $nameAttribute )
            if ( isset( $attributes[$nameAttribute] ) )
            {
                $content->fields->$nameAttribute = $name !== '' ? $name : 'Media';
                break;
            }
        $content->fields->$attribute = $file;
        $publisher = SQLIContentPublisher::getInstance();
        $publisher->setOptions( new SQLIContentPublishOptions( array( 'parent_node_id' => $parent, 'modification_check' => false ) ) );
        $publisher->publish( $content );
        $id = (int)$content->id;
        $content->flush();
        unset( $content );
        return self::$mediaObjects[$key] = $id;
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
        // what the record lost without failing (a download that could not be had) goes into its row's message
        expImportTransforms::$notices = array();
        $result = $this->processRecord( $record, $context );
        if ( expImportTransforms::$notices && $result instanceof expImportRowResult && $result->outcome !== expImportRowResult::FAILED )
            $result->message = trim( $result->message . ( $result->message !== '' ? '; ' : '' ) . 'without ' . implode( '; ', expImportTransforms::$notices ) );
        expImportTransforms::$notices = array();
        return $result;
    }

    /** @var array class/attribute => array( vat id, vat included ) of the price attributes */
    protected static $priceDefaults = array();

    /** @var array run id => remote ID => true: what a dry run would create (a later record may be placed below it) */
    protected static $planned = array();

    /** @return string the file of a dry run's planned remote IDs (its chunks run in different jobs) */
    protected static function plannedFile( expImportContext $context )
    {
        $runID = (int)$context->run->attribute( 'id' );
        $dir = $runID > 0 ? expImportSourceFiles::runDirectory( $runID ) : expImport::storageDirectory( 'preview/' . (int)$context->import->attribute( 'id' ) );
        return $dir . '/dry-run-planned.txt';
    }

    /** @return array remote ID => true of what this dry run would create so far */
    protected static function planned( expImportContext $context )
    {
        $key = (int)$context->run->attribute( 'id' ) . ':' . (int)$context->import->attribute( 'id' );
        if ( !isset( self::$planned[$key] ) )
        {
            self::$planned[$key] = array();
            $file = (int)$context->run->attribute( 'id' ) > 0 ? self::plannedFile( $context ) : '';
            if ( $file !== '' && is_file( $file ) )
                foreach ( (array)file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
                    self::$planned[$key][$line] = true;
        }
        return self::$planned[$key];
    }

    /** Notes a remote ID a dry run would create. */
    protected static function planCreated( expImportContext $context, $remoteID )
    {
        $remoteID = (string)$remoteID;
        if ( $remoteID === '' || isset( self::planned( $context )[$remoteID] ) )
            return;
        $key = (int)$context->run->attribute( 'id' ) . ':' . (int)$context->import->attribute( 'id' );
        self::$planned[$key][$remoteID] = true;
        if ( (int)$context->run->attribute( 'id' ) > 0 )
            @file_put_contents( self::plannedFile( $context ), $remoteID . "\n", FILE_APPEND | LOCK_EX );
    }

    /**
     * The remote ID of a parent of the plan that this dry run would create, '' when there is none.
     *
     * @param expImportContext $context
     * @param array $parents the plan's parents
     * @return string
     */
    protected static function plannedParent( expImportContext $context, array $parents )
    {
        $planned = self::planned( $context );
        foreach ( $parents as $parent )
            if ( isset( $parent['remote_id'] ) && isset( $planned[(string)$parent['remote_id']] ) )
                return (string)$parent['remote_id'];
        return '';
    }

    /**
     * The values of the price attributes (ezprice) of a class as the kernel reads them (expImportPrice): a plain
     * number gets the attribute's default VAT type and "VAT included", and the number is written as the kernel prints
     * it, so an unchanged price compares equal on the next run.
     *
     * @param array $fields language => attribute => value
     * @param string $class
     * @return array
     */
    protected static function normalizePrices( array $fields, $class )
    {
        $types = self::classAttributes( $class );
        if ( !$types || !in_array( 'ezprice', $types, true ) )
            return $fields;
        foreach ( $fields as $language => $values )
        {
            foreach ( $values as $attribute => $value )
            {
                if ( !isset( $types[$attribute] ) || $types[$attribute] !== 'ezprice' )
                    continue;
                list( $vatID, $included ) = self::priceDefaults( $class, $attribute );
                $fields[$language][$attribute] = expImportPrice::normalize( $value, $vatID, $included );
            }
        }
        return $fields;
    }

    /** @return array array( VAT type id or '', VAT included ) of a price attribute of a class */
    protected static function priceDefaults( $class, $attribute )
    {
        $key = $class . '/' . $attribute;
        if ( isset( self::$priceDefaults[$key] ) )
            return self::$priceDefaults[$key];
        $vatID = '';
        $included = true;
        $contentClass = eZContentClass::fetchByIdentifier( $class );
        if ( $contentClass instanceof eZContentClass )
        {
            foreach ( $contentClass->fetchAttributes() as $classAttribute )
            {
                if ( $classAttribute->attribute( 'identifier' ) !== $attribute )
                    continue;
                $id = (int)$classAttribute->attribute( 'data_float1' );
                $vatID = $id > 0 ? (string)$id : '';
                // 1: VAT included, 2: excluded (eZPriceType INCLUDED_VAT / EXCLUDED_VAT)
                $included = (int)$classAttribute->attribute( 'data_int1' ) !== 2;
            }
        }
        return self::$priceDefaults[$key] = array( $vatID, $included );
    }

    /** @return expImportRowResult what was done with one record */
    protected function processRecord( expImportRecord $record, expImportContext $context )
    {
        // a mapping with a class per record (class_from): the variant of this record, or none when its kind is skipped
        $mapping = expImportMapper::variantFor( $this->mapping, $record );
        if ( $mapping === null )
            return expImportRowResult::skipped( "not imported: its {$this->mapping['class_from']} is " . trim( (string)$record->get( (string)$this->mapping['class_from'], '' ) ) );
        $plan = expImportMapper::apply( $mapping, $record );
        $plan['fields'] = self::normalizePrices( $plan['fields'], (string)$mapping['class'] );
        $language = $this->mapping['language'] !== '' ? $this->mapping['language']
                  : ( $context->target['language'] !== '' ? $context->target['language'] : eZContentObject::defaultLanguage() );

        $object = $this->findObject( $plan, $mapping['class'] );
        if ( $object && (int)$object->attribute( 'status' ) === eZContentObject::STATUS_ARCHIVED )
            return expImportRowResult::skipped( 'the object is in the trash', $object->attribute( 'id' ) );
        if ( $object && $context->behaviour['update_policy'] === 'create_only' )
            return expImportRowResult::skipped( 'the object exists (the import only creates)', $object->attribute( 'id' ) );
        if ( !$object && $context->behaviour['update_policy'] === 'update_only' )
            return expImportRowResult::skipped( 'no object to update' );

        $parents = $this->parentNodes( $plan['parents'] );
        if ( !$object && !$parents )
        {
            // a dry run publishes nothing: a parent this dry run would create (a folder before its documents) counts
            if ( !$context->dryRun || !self::plannedParent( $context, $plan['parents'] ) )
                throw new expImportException( 'the parent of the record does not exist (yet)' );
            $result = $this->dryRunResult( null, $plan, $language );
            $result->message = 'dry run: would be created below ' . self::plannedParent( $context, $plan['parents'] ) . ', which this run would create';
            self::planCreated( $context, $plan['remote_id'] );
            return $result;
        }

        // an object someone edited since this import last wrote it (a field whose value is not the one the import wrote):
        // the conflict rule decides; what the import wrote is kept per field across runs
        $conflict = isset( $context->behaviour['conflict_policy'] ) ? (string)$context->behaviour['conflict_policy'] : 'overwrite';
        $written = $object ? self::lastWritten( (int)$context->import->attribute( 'id' ), (int)$object->attribute( 'id' ) ) : array();
        $all = $plan['fields'];
        if ( $object && $conflict !== 'overwrite' && $written )
        {
            $edited = $this->editedFields( $object, $all, $language, $written );
            if ( $edited )
            {
                if ( $conflict === 'skip' )
                    return expImportRowResult::skipped( 'edited since the last run: ' . implode( ', ', array_keys( $edited ) ) . ' (conflict rule: skip)', $object->attribute( 'id' ) );
                foreach ( $plan['fields'] as $fieldLanguage => $values )
                {
                    foreach ( $values as $attribute => $value )
                    {
                        $drop = $conflict === 'merge' ? isset( $edited[$attribute] )
                                                      : trim( self::currentValue( $object, $fieldLanguage === '' ? $language : $fieldLanguage, $attribute ) ) !== '';
                        if ( $drop )
                            unset( $plan['fields'][$fieldLanguage][$attribute] );
                    }
                }
            }
        }
        // what the import has written so far: the earlier values, then what this run writes
        $writtenNow = $written;
        foreach ( $plan['fields'] as $fieldLanguage => $values )
            foreach ( $values as $attribute => $value )
                $writtenNow[$fieldLanguage][$attribute] = (string)$value;
        if ( $object && !array_filter( $plan['fields'] ) )
        {
            $result = expImportRowResult::unchanged( $object->attribute( 'id' ), 0, 'edited since the last run; nothing left to write (conflict rule: ' . $conflict . ')' );
            $result->written = $writtenNow;
            return $result;
        }

        if ( $context->dryRun )
        {
            if ( !$object )
                self::planCreated( $context, $plan['remote_id'] );
            return $this->dryRunResult( $object, $plan, $language );
        }

        $versionBefore = $object ? (int)$object->attribute( 'current_version' ) : 0;
        $options = array( 'class_identifier' => $mapping['class'], 'language' => $language );
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
        // the first parent is the main location of a new object; the others (extra_parents_from) are added after the
        // publish, to a new and to an existing object alike (an extra location given to the content before its first
        // publish was never created)
        $main = $parents ? $parents[0] : 0;
        $extra = array_slice( $parents, 1 );
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
        $extraNodes = self::addExtraLocations( $objectID, $extra );
        if ( $extraNodes )
        {
            // a new location publishes a new version: the run's version is that one (undo compares it)
            $nodesAdded = array_merge( $nodesAdded, $extraNodes );
            $row = eZContentObject::fetch( $objectID, false );
            $versionAfter = is_array( $row ) ? (int)$row['current_version'] : $versionAfter;
        }
        $nodesAdded = array_values( array_unique( $nodesAdded ) );

        if ( $isNew )
            $result = expImportRowResult::created( $objectID, $versionAfter );
        elseif ( $versionAfter !== $versionBefore )
            $result = expImportRowResult::updated( $objectID, $versionBefore, $versionAfter );
        else
            $result = expImportRowResult::unchanged( $objectID, $versionAfter );
        $result->remoteID = $plan['remote_id'] !== '' ? $plan['remote_id'] : (string)eZContentObject::fetch( $objectID, false )['remote_id'];
        $result->nodesAdded = $nodesAdded;
        // what the import has written, as the attributes read back after the publish (their toString(), so a later run
        // compares like with like): the conflict rules of later runs find a person's edits by it
        eZContentObject::clearCache( array( $objectID ) );
        $published = eZContentObject::fetch( $objectID );
        if ( $published )
            foreach ( $plan['fields'] as $fieldLanguage => $values )
                foreach ( $values as $attribute => $value )
                    $writtenNow[$fieldLanguage][$attribute] = self::currentValue( $published, $fieldLanguage === '' ? $language : $fieldLanguage, $attribute );
        $result->written = $writtenNow;
        return $result;
    }

    /**
     * What the earlier runs of an import wrote into an object, field by field (the newest row that kept it).
     *
     * @param int $importID
     * @param int $objectID
     * @return array language => attribute => value ('' language = the import's)
     */
    public static function lastWritten( $importID, $objectID )
    {
        $rows = expImport::db()->arrayQuery( 'SELECT r.data_json FROM sqliimport_run_row r JOIN sqliimport_run u ON u.id = r.run_id
                                              WHERE u.import_id = ' . (int)$importID . ' AND r.object_id = ' . (int)$objectID . "
                                              AND r.outcome IN ('created', 'updated', 'unchanged') AND u.run_kind IN ('import', 'retry')
                                              AND r.data_json <> '' ORDER BY r.id DESC",
                                             array( 'limit' => 1 ) );
        if ( !$rows )
            return array();
        $data = expImport::decode( $rows[0]['data_json'] );
        return isset( $data['written'] ) && is_array( $data['written'] ) ? $data['written'] : array();
    }

    /** @return string the value of an attribute of an object in a language now (its datatype's toString()) */
    public static function currentValue( eZContentObject $object, $language, $attribute )
    {
        static $maps = array();
        $key = $object->attribute( 'id' ) . '/' . $object->attribute( 'current_version' ) . '/' . $language;
        if ( !isset( $maps[$key] ) )
        {
            if ( count( $maps ) > 50 )
                $maps = array();
            $maps[$key] = $object->fetchDataMap( false, $language );
        }
        return isset( $maps[$key][$attribute] ) ? (string)$maps[$key][$attribute]->toString() : '';
    }

    /**
     * The mapped attributes a person changed since the import wrote them.
     *
     * @param eZContentObject $object
     * @param array $fields language => attribute => value (the plan)
     * @param string $language the language of '' in $fields
     * @param array $written what the import wrote before
     * @return array attribute => true
     */
    protected function editedFields( eZContentObject $object, array $fields, $language, array $written )
    {
        $edited = array();
        foreach ( $fields as $fieldLanguage => $values )
        {
            foreach ( array_keys( $values ) as $attribute )
            {
                if ( !isset( $written[$fieldLanguage][$attribute] ) )
                    continue;
                $now = self::currentValue( $object, $fieldLanguage === '' ? $language : $fieldLanguage, $attribute );
                if ( $now !== (string)$written[$fieldLanguage][$attribute] )
                    $edited[$attribute] = true;
            }
        }
        return $edited;
    }

    public function close( expImportContext $context )
    {
        expImportTransforms::$lookup = null;
        expImportTransforms::$download = null;
    }

    /** @return eZContentObject|null */
    protected function findObject( array $plan, $class = null )
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
            WHERE c.identifier = '" . $db->escapeString( $class !== null ? (string)$class : $this->mapping['class'] ) . "'
              AND ca.identifier = '" . $db->escapeString( $attribute ) . "'
              AND ( a.data_text = '" . $db->escapeString( $value ) . "' OR a.sort_key_string = '" . $db->escapeString( mb_strtolower( $value ) ) . "' )",
            array( 'limit' => 1 ) );
        return $rows ? eZContentObject::fetch( (int)$rows[0]['id'] ) : null;
    }

    /**
     * The parent nodes of $parents the object is not below yet, as more locations of it (the kernel's node
     * assignment, as the location's tab adds one).
     *
     * @param int $objectID
     * @param int[] $parents parent node IDs
     * @return int[] the node IDs of the new locations
     */
    public static function addExtraLocations( $objectID, array $parents )
    {
        $object = eZContentObject::fetch( (int)$objectID );
        if ( !$object instanceof eZContentObject || !$parents )
            return array();
        $missing = self::missingParents( self::currentParents( $object ), $parents );
        $main = eZContentObjectTreeNode::fetch( (int)$object->mainNodeID() );
        if ( !$missing || !$main instanceof eZContentObjectTreeNode )
            return array();
        // as the kernel's addAssignment() does, without its check of the current user's rights: a run places its
        // content as the import (publishing does not ask either), and a run of the command line has no user
        $db = eZDB::instance();
        $db->begin();
        $added = array();
        foreach ( $missing as $parentNodeID )
        {
            if ( !eZContentObjectTreeNode::fetch( $parentNodeID, false, false ) )
                continue;
            $node = $object->addLocation( $parentNodeID, true );
            if ( !$node instanceof eZContentObjectTreeNode )
                continue;
            $node->setAttribute( 'contentobject_is_published', 1 );
            $node->setAttribute( 'main_node_id', $main->attribute( 'main_node_id' ) );
            $node->setAttribute( 'contentobject_version', $main->attribute( 'contentobject_version' ) );
            $node->updateSubTreePath();
            $node->sync();
            $added[] = (int)$node->attribute( 'node_id' );
        }
        $db->commit();
        if ( $added )
        {
            if ( class_exists( 'eZSearch' ) )
                eZSearch::addNodeAssignment( (int)$main->attribute( 'node_id' ), (int)$objectID, $missing );
            eZContentCacheManager::clearContentCacheIfNeeded( (int)$objectID );
        }
        return $added;
    }

    /** @return int[] the parent node IDs of the object's locations */
    protected static function currentParents( eZContentObject $object )
    {
        $out = array();
        foreach ( (array)$object->assignedNodes() as $node )
            $out[] = (int)$node->attribute( 'parent_node_id' );
        return $out;
    }

    /**
     * The parents an object still needs a location below (pure).
     *
     * @param int[] $current the parent node IDs of its locations
     * @param int[] $wanted
     * @return int[]
     */
    public static function missingParents( array $current, array $wanted )
    {
        $current = array_map( 'intval', $current );
        $out = array();
        foreach ( $wanted as $id )
        {
            $id = (int)$id;
            if ( $id > 0 && !in_array( $id, $current, true ) && !in_array( $id, $out, true ) )
                $out[] = $id;
        }
        return $out;
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
