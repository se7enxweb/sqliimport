<?php
/**
 * File containing the expImportMongoDbExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The MongoDB source's connection through the PHP extension mongodb (MongoDB\Driver\Manager, without the composer
 * library). Only MongoDB\Driver\Query and the aggregate command (through executeReadCommand(), with a pipeline the
 * source has checked to have reading stages only) are ever executed: no other command, no bulk write.
 */
class expImportMongoDbExtension implements expImportMongoDbTransport
{
    /** The type map of the documents: subdocuments and arrays as PHP arrays, other BSON values as their classes. */
    const TYPE_MAP = array( 'root' => 'array', 'document' => 'array', 'array' => 'array' );

    /** @var object|null MongoDB\Driver\Manager */
    protected $manager = null;

    /** @return bool whether this PHP has the extension */
    public static function available()
    {
        return class_exists( 'MongoDB\Driver\Manager' ) && class_exists( 'MongoDB\Driver\Query' );
    }

    public function open( $uri, array $uriOptions )
    {
        if ( !self::available() )
            throw new expImportSourceException( 'MongoDB: this PHP has no mongodb extension' );
        try
        {
            $class = 'MongoDB\Driver\Manager';
            $this->manager = new $class( $uri, $uriOptions );
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'MongoDB: ' . $e->getMessage() );
        }
    }

    public function find( $database, $collection, $filter, $after, array $options )
    {
        if ( !$this->manager )
            throw new expImportSourceException( 'MongoDB: not connected' );
        $mode = isset( $options['readPreference'] ) ? (string)$options['readPreference'] : 'primary';
        unset( $options['readPreference'] );
        try
        {
            $query = self::document( $filter === '' ? '{}' : $filter );
            if ( $after !== null )
                $query = $query ? array( '$and' => array( $query, array( '_id' => array( '$gt' => $after ) ) ) ) : array( '_id' => array( '$gt' => $after ) );
            foreach ( array( 'projection', 'sort' ) as $name )
                if ( isset( $options[$name] ) && $options[$name] === array() )
                    unset( $options[$name] );
            $queryClass = 'MongoDB\Driver\Query';
            $preferenceClass = 'MongoDB\Driver\ReadPreference';
            // an empty filter must be a BSON document, not an empty array
            $cursor = $this->manager->executeQuery( $database . '.' . $collection, new $queryClass( $query ?: new stdClass(), $options ),
                                                    array( 'readPreference' => new $preferenceClass( $mode ) ) );
            $cursor->setTypeMap( self::TYPE_MAP );
            foreach ( $cursor as $document )
                yield (array)$document;
        }
        catch ( expImportSourceException $e )
        {
            throw $e;
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'MongoDB: ' . $e->getMessage() );
        }
    }

    public function aggregate( $database, $collection, $pipeline, array $options )
    {
        if ( !$this->manager )
            throw new expImportSourceException( 'MongoDB: not connected' );
        $mode = isset( $options['readPreference'] ) ? (string)$options['readPreference'] : 'primary';
        try
        {
            $json = '{"aggregate": ' . json_encode( (string)$collection ) . ', "pipeline": ' . (string)$pipeline
                  . ', "cursor": {"batchSize": ' . max( 1, (int)( isset( $options['batchSize'] ) ? $options['batchSize'] : 500 ) ) . '}'
                  . ', "maxTimeMS": ' . max( 1, (int)( isset( $options['maxTimeMS'] ) ? $options['maxTimeMS'] : 30000 ) ) . '}';
            // documents as objects, so an empty stage body stays a document ({"$match": {}})
            $command = self::decode( $json, array( 'root' => 'object', 'document' => 'object', 'array' => 'array' ) );
            $commandClass = 'MongoDB\Driver\Command';
            $preferenceClass = 'MongoDB\Driver\ReadPreference';
            $cursor = $this->manager->executeReadCommand( (string)$database, new $commandClass( $command ),
                                                          array( 'readPreference' => new $preferenceClass( $mode ) ) );
            $cursor->setTypeMap( self::TYPE_MAP );
            foreach ( $cursor as $document )
                yield (array)$document;
        }
        catch ( expImportSourceException $e )
        {
            throw $e;
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'MongoDB: ' . $e->getMessage() );
        }
    }

    /**
     * A JSON document (MongoDB Extended JSON) as a PHP array with BSON values ({"$oid": ...} an ObjectId).
     *
     * @param string $json
     * @return array
     */
    public static function document( $json )
    {
        return (array)self::decode( $json, self::TYPE_MAP );
    }

    /**
     * A JSON document (MongoDB Extended JSON) as PHP values of a type map.
     *
     * @param string $json
     * @param array $typeMap
     * @return array|object
     */
    protected static function decode( $json, array $typeMap )
    {
        $documentClass = 'MongoDB\BSON\Document';
        if ( class_exists( $documentClass ) )
            return $documentClass::fromJSON( (string)$json )->toPHP( $typeMap );
        // drivers before 1.16
        return call_user_func( 'MongoDB\BSON\toPHP', call_user_func( 'MongoDB\BSON\fromJSON', (string)$json ), $typeMap );
    }

    public function close()
    {
        $this->manager = null;
    }
}
