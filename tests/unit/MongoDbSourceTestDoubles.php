<?php
/**
 * The test doubles of MongoDbSourceTest: the BSON value classes of the extension mongodb, for a PHP without it (each
 * declared only when the extension does not have it, with the same constructor and methods), and a collection in
 * memory that answers finds the way the driver does.
 */

namespace MongoDB\BSON
{
    if ( !class_exists( 'MongoDB\BSON\ObjectId' ) )
    {
        final class ObjectId
        {
            private $hex;

            public function __construct( $id = null )
            {
                $this->hex = strtolower( (string)$id );
            }

            public function __toString()
            {
                return $this->hex;
            }
        }
    }

    if ( !class_exists( 'MongoDB\BSON\UTCDateTime' ) )
    {
        final class UTCDateTime
        {
            private $ms;

            public function __construct( $milliseconds = null )
            {
                $this->ms = (int)$milliseconds;
            }

            public function toDateTime()
            {
                $seconds = intdiv( $this->ms, 1000 );
                return \DateTime::createFromFormat( 'U.u', $seconds . '.' . sprintf( '%06d', ( $this->ms % 1000 ) * 1000 ), new \DateTimeZone( 'UTC' ) );
            }

            public function __toString()
            {
                return (string)$this->ms;
            }
        }
    }

    if ( !class_exists( 'MongoDB\BSON\Decimal128' ) )
    {
        final class Decimal128
        {
            private $value;

            public function __construct( $value = '' )
            {
                $this->value = (string)$value;
            }

            public function __toString()
            {
                return $this->value;
            }
        }
    }

    if ( !class_exists( 'MongoDB\BSON\Binary' ) )
    {
        final class Binary
        {
            private $data;

            private $type;

            public function __construct( $data = '', $type = 0 )
            {
                $this->data = (string)$data;
                $this->type = (int)$type;
            }

            public function getData()
            {
                return $this->data;
            }

            public function getType()
            {
                return $this->type;
            }
        }
    }

    if ( !class_exists( 'MongoDB\BSON\Regex' ) )
    {
        final class Regex
        {
            private $pattern;

            private $flags;

            public function __construct( $pattern = '', $flags = '' )
            {
                $this->pattern = (string)$pattern;
                $this->flags = (string)$flags;
            }

            public function getPattern()
            {
                return $this->pattern;
            }

            public function getFlags()
            {
                return $this->flags;
            }
        }
    }

    if ( !class_exists( 'MongoDB\BSON\Timestamp' ) )
    {
        final class Timestamp
        {
            private $increment;

            private $timestamp;

            public function __construct( $increment = 0, $timestamp = 0 )
            {
                $this->increment = (int)$increment;
                $this->timestamp = (int)$timestamp;
            }

            public function getTimestamp()
            {
                return $this->timestamp;
            }

            public function getIncrement()
            {
                return $this->increment;
            }
        }
    }
}

namespace
{
    /** A collection in memory: documents in _id order, finds with $gt paging, skip and limit, and what was asked. */
    class MongoDbSourceTestTransport implements expImportMongoDbTransport
    {
        /** @var array[] the documents, in _id order */
        public $documents;

        /** @var array|null the arguments of open() */
        public $opened = null;

        /** @var array[] each find: database, collection, filter, after, options */
        public $finds = array();

        /** @var bool */
        public $closed = false;

        /** @var string|null an error find() throws */
        public $fail = null;

        public function __construct( array $documents )
        {
            $this->documents = $documents;
        }

        public function open( $uri, array $uriOptions )
        {
            $this->opened = array( $uri, $uriOptions );
        }

        public function find( $database, $collection, $filter, $after, array $options )
        {
            $this->finds[] = array( $database, $collection, $filter, $after, $options );
            if ( $this->fail !== null )
                throw new expImportSourceException( $this->fail );
            $out = array();
            foreach ( $this->documents as $document )
                if ( $after === null || (string)$document['_id'] > (string)$after )
                    $out[] = $document;
            $out = array_slice( $out, isset( $options['skip'] ) ? (int)$options['skip'] : 0 );
            if ( !empty( $options['limit'] ) )
                $out = array_slice( $out, 0, (int)$options['limit'] );
            return $out;
        }

        public function close()
        {
            $this->closed = true;
        }
    }
}
