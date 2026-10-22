<?php
/**
 * File containing the expImportBoltClient and expImportBoltStructure classes.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A structure of PackStream as the server sent it: a node, a relationship, a path, a temporal value, a point.
 */
class expImportBoltStructure
{
    /** @var int */
    public $signature;

    /** @var array */
    public $fields;

    public function __construct( $signature, array $fields )
    {
        $this->signature = (int)$signature;
        $this->fields = $fields;
    }
}

/**
 * A minimal client of the Bolt protocol, versions 4.0 to 4.4 (Neo4j 4 and 5, Memgraph), for a PHP without a Neo4j
 * driver: the handshake, HELLO with basic authentication, RUN in read mode ("mode": "r"), PULL with a page size
 * (n), DISCARD and GOODBYE, messages in chunks and values in PackStream. It sends nothing else: no BEGIN, no
 * COMMIT, no ROUTE, so every query runs as a read-only auto-commit transaction.
 *
 * Tests replace the socket with $connector: a callable( string $host, int $port, bool $tls, int $timeout ) returning
 * an expImportBoltTransport.
 */
class expImportBoltClient
{
    const HELLO = 0x01;
    const GOODBYE = 0x02;
    const RUN = 0x10;
    const DISCARD = 0x2F;
    const PULL = 0x3F;
    const SUCCESS = 0x70;
    const RECORD = 0x71;
    const IGNORED = 0x7E;
    const FAILURE = 0x7F;

    /** @var callable|null replaces the socket (tests) */
    public static $connector = null;

    /** @var expImportBoltTransport */
    protected $transport;

    /** @var string the version agreed on, 4.4 */
    protected $version = '';

    /** @var string the server of HELLO's answer (Neo4j/5.12.0, Memgraph/2.11.0) */
    protected $server = '';

    /** @var int the largest message accepted */
    protected $maxBytes;

    /**
     * @param expImportBoltTransport $transport
     * @param int $maxBytes the largest message accepted
     */
    public function __construct( expImportBoltTransport $transport, $maxBytes = 20971520 )
    {
        $this->transport = $transport;
        $this->maxBytes = max( 1024, (int)$maxBytes );
    }

    /**
     * Connects, agrees on a version and authenticates.
     *
     * @param string $host
     * @param int $port
     * @param bool $tls
     * @param int $timeout
     * @param string $user '' for none
     * @param string $password
     * @param int $maxBytes
     * @return expImportBoltClient
     * @throws expImportSourceException
     */
    public static function open( $host, $port, $tls, $timeout, $user, $password, $maxBytes = 20971520 )
    {
        $transport = self::$connector ? call_user_func( self::$connector, (string)$host, (int)$port, (bool)$tls, (int)$timeout )
                                      : new expImportBoltSocket( (string)$host, (int)$port, (bool)$tls, (int)$timeout );
        $client = new self( $transport, $maxBytes );
        try
        {
            $client->handshake();
            $client->hello( (string)$user, (string)$password );
        }
        catch ( expImportSourceException $e )
        {
            $transport->close();
            throw $e;
        }
        return $client;
    }

    /** @return string the version agreed on (4.4) */
    public function version()
    {
        return $this->version;
    }

    /** @return string the server as it names itself (Neo4j/5.12.0) */
    public function server()
    {
        return $this->server;
    }

    /**
     * The handshake: Bolt 4.4, 4.3, 4.1 and 4.0 are offered.
     *
     * @throws expImportSourceException
     */
    public function handshake()
    {
        $this->transport->write( "\x60\x60\xB0\x17" . "\x00\x00\x04\x04" . "\x00\x00\x03\x04" . "\x00\x00\x01\x04" . "\x00\x00\x00\x04" );
        $answer = $this->transport->read( 4 );
        if ( $answer === "\x48\x54\x54\x50" )
            throw new expImportSourceException( 'Bolt: the port answers HTTP, not Bolt (give the Bolt port, usually 7687)' );
        $major = ord( $answer[3] );
        $minor = ord( $answer[2] );
        if ( $major !== 4 )
            throw new expImportSourceException( 'Bolt: the server speaks none of the versions 4.0 to 4.4 this client speaks' );
        $this->version = $major . '.' . $minor;
    }

    /**
     * HELLO with basic authentication (or none).
     *
     * @param string $user
     * @param string $password
     * @throws expImportSourceException
     */
    public function hello( $user, $password )
    {
        $extra = array( 'user_agent' => '7x-SQLI-Import/2.0' );
        if ( $user !== '' )
            $extra += array( 'scheme' => 'basic', 'principal' => $user, 'credentials' => $password );
        else
            $extra['scheme'] = 'none';
        $this->send( self::HELLO, array( $extra ) );
        $meta = $this->success();
        $this->server = isset( $meta['server'] ) ? (string)$meta['server'] : '';
    }

    /**
     * RUN in read mode.
     *
     * @param string $query
     * @param array $parameters
     * @param string $database '' for the default database
     * @return string[] the fields (columns) of the result
     * @throws expImportSourceException
     */
    public function run( $query, array $parameters, $database = '' )
    {
        $extra = array( 'mode' => 'r' );
        if ( (string)$database !== '' )
            $extra['db'] = (string)$database;
        $this->send( self::RUN, array( (string)$query, $parameters ? $parameters : new stdClass(), $extra ) );
        $meta = $this->success();
        return isset( $meta['fields'] ) && is_array( $meta['fields'] ) ? array_map( 'strval', $meta['fields'] ) : array();
    }

    /**
     * PULL of the next n records.
     *
     * @param int $n
     * @return array the list ( array[] records (lists of values), bool whether there are more )
     * @throws expImportSourceException
     */
    public function pull( $n )
    {
        $this->send( self::PULL, array( array( 'n' => max( 1, (int)$n ) ) ) );
        $records = array();
        while ( true )
        {
            list( $signature, $fields ) = $this->response();
            if ( $signature === self::RECORD )
            {
                $records[] = isset( $fields[0] ) && is_array( $fields[0] ) ? $fields[0] : array();
                continue;
            }
            $meta = isset( $fields[0] ) && is_array( $fields[0] ) ? $fields[0] : array();
            return array( $records, !empty( $meta['has_more'] ) );
        }
    }

    /**
     * DISCARD of the rest of a result (when a run stops early).
     *
     * @throws expImportSourceException
     */
    public function discard()
    {
        $this->send( self::DISCARD, array( array( 'n' => -1 ) ) );
        $this->success();
    }

    /** GOODBYE and closing; never fails. */
    public function close()
    {
        try
        {
            $this->send( self::GOODBYE, array() );
        }
        catch ( Exception $e )
        {
        }
        $this->transport->close();
    }

    /**
     * The next answer, which must be SUCCESS.
     *
     * @return array its metadata
     * @throws expImportSourceException
     */
    protected function success()
    {
        list( $signature, $fields ) = $this->response();
        if ( $signature !== self::SUCCESS )
            throw new expImportSourceException( 'Bolt: an answer of the type 0x' . dechex( $signature ) . ' where SUCCESS was expected' );
        return isset( $fields[0] ) && is_array( $fields[0] ) ? $fields[0] : array();
    }

    /**
     * The next answer: SUCCESS or RECORD; FAILURE and IGNORED are errors.
     *
     * @return array the list ( int signature, array fields )
     * @throws expImportSourceException
     */
    protected function response()
    {
        $message = $this->receive();
        if ( !$message instanceof expImportBoltStructure )
            throw new expImportSourceException( 'Bolt: the server sent something that is not a message' );
        if ( $message->signature === self::FAILURE )
        {
            $meta = isset( $message->fields[0] ) && is_array( $message->fields[0] ) ? $message->fields[0] : array();
            throw new expImportSourceException( 'Bolt: ' . trim( ( isset( $meta['code'] ) ? $meta['code'] . ': ' : '' ) . ( isset( $meta['message'] ) ? (string)$meta['message'] : 'the request failed' ) ) );
        }
        if ( $message->signature === self::IGNORED )
            throw new expImportSourceException( 'Bolt: the server ignored the request' );
        return array( $message->signature, $message->fields );
    }

    /**
     * Sends a message in chunks.
     *
     * @param int $signature
     * @param array $fields
     * @throws expImportSourceException
     */
    protected function send( $signature, array $fields )
    {
        $this->transport->write( self::chunk( self::pack( new expImportBoltStructure( $signature, $fields ) ) ) );
    }

    /**
     * A message in chunks of at most 65535 bytes, ended by an empty chunk.
     *
     * @param string $data
     * @return string
     */
    public static function chunk( $data )
    {
        $out = '';
        foreach ( str_split( (string)$data, 65535 ) as $part )
            $out .= pack( 'n', strlen( $part ) ) . $part;
        return $out . "\x00\x00";
    }

    /**
     * Reads one message (its chunks up to the empty one; empty chunks before it are no-ops).
     *
     * @return mixed
     * @throws expImportSourceException
     */
    protected function receive()
    {
        $data = '';
        while ( true )
        {
            $size = unpack( 'n', $this->transport->read( 2 ) );
            $size = (int)$size[1];
            if ( $size === 0 )
            {
                if ( $data === '' )
                    continue;
                break;
            }
            if ( strlen( $data ) + $size > $this->maxBytes )
                throw new expImportSourceException( 'Bolt: a message is larger than [PlatformSettings] MaxResponseMegabytes' );
            $data .= $this->transport->read( $size );
        }
        $pos = 0;
        return self::unpack( $data, $pos );
    }

    // ---- PackStream --------------------------------------------------------------------------------------------------

    /**
     * A value in PackStream: null, bool, int, float, string, list (an array with keys 0..n-1), map (any other array
     * or an object) or a structure.
     *
     * @param mixed $value
     * @return string
     */
    public static function pack( $value )
    {
        if ( $value === null )
            return "\xC0";
        if ( $value === true )
            return "\xC3";
        if ( $value === false )
            return "\xC2";
        if ( is_int( $value ) )
        {
            if ( $value >= -16 && $value <= 127 )
                return pack( 'c', $value );
            if ( $value >= -128 && $value <= 127 )
                return "\xC8" . pack( 'c', $value );
            if ( $value >= -32768 && $value <= 32767 )
                return "\xC9" . pack( 'n', $value & 0xFFFF );
            if ( $value >= -2147483648 && $value <= 2147483647 )
                return "\xCA" . pack( 'N', $value & 0xFFFFFFFF );
            return "\xCB" . pack( 'J', $value );
        }
        if ( is_float( $value ) )
            return "\xC1" . pack( 'E', $value );
        if ( is_string( $value ) )
            return self::header( strlen( $value ), 0x80, 0xD0 ) . $value;
        if ( $value instanceof expImportBoltStructure )
        {
            $out = chr( 0xB0 + count( $value->fields ) ) . chr( $value->signature );
            foreach ( $value->fields as $field )
                $out .= self::pack( $field );
            return $out;
        }
        if ( is_object( $value ) )
            $value = (array)$value;
        if ( is_array( $value ) && $value !== array() && array_values( $value ) === $value )
        {
            $out = self::header( count( $value ), 0x90, 0xD4 );
            foreach ( $value as $item )
                $out .= self::pack( $item );
            return $out;
        }
        $out = self::header( count( (array)$value ), 0xA0, 0xD8 );
        foreach ( (array)$value as $key => $item )
            $out .= self::pack( (string)$key ) . self::pack( $item );
        return $out;
    }

    /** @return string the marker and size of a string, list or map */
    protected static function header( $size, $tiny, $sized )
    {
        if ( $size < 16 )
            return chr( $tiny + $size );
        if ( $size < 256 )
            return chr( $sized ) . chr( $size );
        if ( $size < 65536 )
            return chr( $sized + 1 ) . pack( 'n', $size );
        return chr( $sized + 2 ) . pack( 'N', $size );
    }

    /**
     * One value of PackStream at a position.
     *
     * @param string $data
     * @param int $pos moved past the value
     * @return mixed
     * @throws expImportSourceException
     */
    public static function unpack( $data, &$pos )
    {
        $marker = ord( self::bytes( $data, $pos, 1 ) );
        if ( $marker < 0x80 )
            return $marker;
        if ( $marker >= 0xF0 )
            return $marker - 0x100;
        $high = $marker & 0xF0;
        $low = $marker & 0x0F;
        if ( $high === 0x80 )
            return self::bytes( $data, $pos, $low );
        if ( $high === 0x90 )
            return self::listOf( $data, $pos, $low );
        if ( $high === 0xA0 )
            return self::mapOf( $data, $pos, $low );
        if ( $high === 0xB0 )
        {
            $signature = ord( self::bytes( $data, $pos, 1 ) );
            return new expImportBoltStructure( $signature, self::listOf( $data, $pos, $low ) );
        }
        switch ( $marker )
        {
            case 0xC0:
                return null;
            case 0xC1:
                $v = unpack( 'E', self::bytes( $data, $pos, 8 ) );
                return (float)$v[1];
            case 0xC2:
                return false;
            case 0xC3:
                return true;
            case 0xC8:
                $v = ord( self::bytes( $data, $pos, 1 ) );
                return $v >= 128 ? $v - 256 : $v;
            case 0xC9:
                $v = unpack( 'n', self::bytes( $data, $pos, 2 ) );
                return $v[1] >= 32768 ? $v[1] - 65536 : $v[1];
            case 0xCA:
                $v = unpack( 'N', self::bytes( $data, $pos, 4 ) );
                return $v[1] >= 2147483648 ? $v[1] - 4294967296 : $v[1];
            case 0xCB:
                $v = unpack( 'J', self::bytes( $data, $pos, 8 ) );
                return (int)$v[1];
            case 0xCC:
            case 0xCD:
            case 0xCE:
                // a byte array: base64:..., as the SQL source writes binary values
                return 'base64:' . base64_encode( self::bytes( $data, $pos, self::size( $data, $pos, 1 << ( $marker - 0xCC ) ) ) );
            case 0xD0:
                return self::bytes( $data, $pos, self::size( $data, $pos, 1 ) );
            case 0xD1:
                return self::bytes( $data, $pos, self::size( $data, $pos, 2 ) );
            case 0xD2:
                return self::bytes( $data, $pos, self::size( $data, $pos, 4 ) );
            case 0xD4:
                return self::listOf( $data, $pos, self::size( $data, $pos, 1 ) );
            case 0xD5:
                return self::listOf( $data, $pos, self::size( $data, $pos, 2 ) );
            case 0xD6:
                return self::listOf( $data, $pos, self::size( $data, $pos, 4 ) );
            case 0xD8:
                return self::mapOf( $data, $pos, self::size( $data, $pos, 1 ) );
            case 0xD9:
                return self::mapOf( $data, $pos, self::size( $data, $pos, 2 ) );
            case 0xDA:
                return self::mapOf( $data, $pos, self::size( $data, $pos, 4 ) );
        }
        throw new expImportSourceException( 'Bolt: an unknown PackStream marker 0x' . dechex( $marker ) );
    }

    /** @return string n bytes at a position */
    protected static function bytes( $data, &$pos, $n )
    {
        $pos = (int)$pos;
        if ( $n < 0 || $pos + $n > strlen( $data ) )
            throw new expImportSourceException( 'Bolt: a message ends too early' );
        $out = (string)substr( $data, $pos, $n );
        $pos += $n;
        return $out;
    }

    /** @return int an unsigned size of 1, 2 or 4 bytes */
    protected static function size( $data, &$pos, $width )
    {
        $raw = self::bytes( $data, $pos, $width );
        $v = unpack( $width === 1 ? 'C' : ( $width === 2 ? 'n' : 'N' ), $raw );
        return (int)$v[1];
    }

    /** @return array a list of n values */
    protected static function listOf( $data, &$pos, $n )
    {
        if ( $n > strlen( $data ) - $pos )
            throw new expImportSourceException( 'Bolt: a message ends too early' );
        $out = array();
        for ( $i = 0; $i < $n; $i++ )
            $out[] = self::unpack( $data, $pos );
        return $out;
    }

    /** @return array a map of n entries */
    protected static function mapOf( $data, &$pos, $n )
    {
        if ( $n > strlen( $data ) - $pos )
            throw new expImportSourceException( 'Bolt: a message ends too early' );
        $out = array();
        for ( $i = 0; $i < $n; $i++ )
        {
            $key = self::unpack( $data, $pos );
            $out[(string)$key] = self::unpack( $data, $pos );
        }
        return $out;
    }
}
