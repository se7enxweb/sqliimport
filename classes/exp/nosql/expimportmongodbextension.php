<?php
/**
 * File containing the expImportMongoDbExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The MongoDB source's connection through the PHP extension mongodb (MongoDB\Driver\Manager, without the composer
 * library). Only MongoDB\Driver\Query is ever executed: no command, no bulk write, no aggregation.
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

    /**
     * A JSON document (MongoDB Extended JSON) as a PHP array with BSON values ({"$oid": ...} an ObjectId).
     *
     * @param string $json
     * @return array
     */
    public static function document( $json )
    {
        $documentClass = 'MongoDB\BSON\Document';
        if ( class_exists( $documentClass ) )
            return (array)$documentClass::fromJSON( (string)$json )->toPHP( self::TYPE_MAP );
        // drivers before 1.16
        return (array)call_user_func( 'MongoDB\BSON\toPHP', call_user_func( 'MongoDB\BSON\fromJSON', (string)$json ), self::TYPE_MAP );
    }

    public function close()
    {
        $this->manager = null;
    }
}
