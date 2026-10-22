<?php
/**
 * File containing the expImportCassandraClient class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A minimal client of the CQL native protocol, version 4 (Cassandra 2.2 and later, ScyllaDB), over a socket (driver
 * "socket"), for a PHP without the extension cassandra: STARTUP, AUTHENTICATE with the PasswordAuthenticator
 * (AUTH_RESPONSE with user and password), QUERY with a page size and the paging state, and the decoding of a ROWS
 * result with the common types. One request at a time on stream 0, no compression; TLS verifies the certificate.
 *
 * Only two kinds of QUERY are ever sent: "USE <keyspace>" (by open(), with a checked name) and the import's SELECT,
 * which expImportCassandraSource::checkQuery() has accepted; query() refuses anything that does not start with
 * SELECT before it is sent.
 *
 * This client is tested against a scripted server (tests/unit/CassandraSourceTest.php), which sends the frames the
 * protocol specification describes; it has not been run against a live Cassandra or ScyllaDB server of this project.
 */
class expImportCassandraClient implements expImportCassandraTransport
{
    const VERSION = 0x04;

    const OP_ERROR = 0x00;
    const OP_STARTUP = 0x01;
    const OP_READY = 0x02;
    const OP_AUTHENTICATE = 0x03;
    const OP_QUERY = 0x07;
    const OP_RESULT = 0x08;
    const OP_AUTH_CHALLENGE = 0x0E;
    const OP_AUTH_RESPONSE = 0x0F;
    const OP_AUTH_SUCCESS = 0x10;

    /** The consistency levels by name. */
    const CONSISTENCY = array( 'ANY' => 0x00, 'ONE' => 0x01, 'TWO' => 0x02, 'THREE' => 0x03, 'QUORUM' => 0x04, 'ALL' => 0x05,
                               'LOCAL_QUORUM' => 0x06, 'EACH_QUORUM' => 0x07, 'LOCAL_ONE' => 0x0A );

    /** @var resource|null */
    protected $stream = null;

    /** @var int the largest frame accepted, in bytes */
    protected $maxBytes;

    /** @var string the frame body being read */
    protected $body = '';

    /** @var int the position in $body */
    protected $pos = 0;

    public function __construct()
    {
        $this->maxBytes = max( 1, (int)expImport::setting( 'CassandraSourceSettings', 'MaxFrameMegabytes', 64 ) ) * 1048576;
    }

    public function open( array $hosts, $tls, $user, $password, $keyspace, $timeout )
    {
        $errors = array();
        foreach ( $hosts as $host )
        {
            try
            {
                $this->connect( (string)$host[0], (int)$host[1], (bool)$tls, max( 1, (int)$timeout ) );
                break;
            }
            catch ( expImportSourceException $e )
            {
                $errors[] = $e->getMessage();
            }
        }
        if ( !$this->stream )
            throw new expImportSourceException( $errors ? implode( '; ', $errors ) : 'Cassandra: no contact point' );
        list( $op, ) = $this->request( self::OP_STARTUP, $this->stringMap( array( 'CQL_VERSION' => '3.0.0' ) ) );
        if ( $op === self::OP_AUTHENTICATE )
        {
            $authenticator = $this->readString();
            if ( (string)$user === '' )
                throw new expImportSourceException( "Cassandra: the server asks for authentication ($authenticator): give a user and a password" );
            list( $op, ) = $this->request( self::OP_AUTH_RESPONSE, $this->bytes( "\0" . $user . "\0" . $password ) );
            if ( $op === self::OP_AUTH_CHALLENGE )
                throw new expImportSourceException( "Cassandra: the authenticator $authenticator asks for more than a user and a password" );
            if ( $op !== self::OP_AUTH_SUCCESS )
                throw new expImportSourceException( 'Cassandra: the authentication did not succeed' );
        }
        elseif ( $op !== self::OP_READY )
            throw new expImportSourceException( 'Cassandra: the server did not answer STARTUP with READY' );
        if ( (string)$keyspace !== '' )
        {
            if ( !preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,47}$/', (string)$keyspace ) )
                throw new expImportSourceException( 'Cassandra: the keyspace must be a name of letters, digits and _' );
            $this->send( 'USE ' . $keyspace, 'ONE', 0, null );
        }
    }

    public function query( $cql, $consistency, $pageSize, $pagingState )
    {
        if ( !preg_match( '/^\s*SELECT\b/i', (string)$cql ) )
            throw new expImportSourceException( 'Cassandra: only a SELECT is sent' );
        return $this->send( (string)$cql, (string)$consistency, (int)$pageSize, $pagingState );
    }

    public function driver()
    {
        return 'socket';
    }

    public function close()
    {
        if ( $this->stream )
            @fclose( $this->stream );
        $this->stream = null;
    }

    // ---- frames ------------------------------------------------------------------------------------------------------

    /**
     * Sends a QUERY and reads its result.
     *
     * @return array columns, rows, paging_state
     * @throws expImportSourceException
     */
    protected function send( $cql, $consistency, $pageSize, $pagingState )
    {
        if ( !isset( self::CONSISTENCY[$consistency] ) )
            throw new expImportSourceException( "Cassandra: the consistency $consistency is not known" );
        $flags = 0;
        $tail = '';
        if ( $pageSize > 0 )
        {
            $flags |= 0x04;
            $tail .= pack( 'N', $pageSize );
        }
        if ( $pagingState !== null && $pagingState !== '' )
        {
            $flags |= 0x08;
            $tail .= $this->bytes( (string)$pagingState );
        }
        $body = pack( 'N', strlen( $cql ) ) . $cql . pack( 'n', self::CONSISTENCY[$consistency] ) . chr( $flags ) . $tail;
        list( $op, ) = $this->request( self::OP_QUERY, $body );
        if ( $op !== self::OP_RESULT )
            throw new expImportSourceException( 'Cassandra: the server did not answer the query with a result' );
        $kind = $this->readInt();
        if ( $kind !== 0x0002 )
            return array( 'columns' => array(), 'rows' => array(), 'paging_state' => null );
        return $this->readRows();
    }

    /**
     * Writes a request frame and reads the answer's header and body; an ERROR answer is thrown.
     *
     * @param int $opcode
     * @param string $body
     * @return array ( int opcode, int flags ), the body ready to read
     * @throws expImportSourceException
     */
    protected function request( $opcode, $body )
    {
        $this->write( chr( self::VERSION ) . chr( 0 ) . pack( 'n', 0 ) . chr( $opcode ) . pack( 'N', strlen( $body ) ) . $body );
        $header = $this->readBytes( 9 );
        $version = ord( $header[0] );
        if ( ( $version & 0x7F ) !== self::VERSION || !( $version & 0x80 ) )
            throw new expImportSourceException( 'Cassandra: the server does not speak the native protocol v4 (version byte ' . sprintf( '0x%02x', $version ) . ')' );
        $flags = ord( $header[1] );
        $op = ord( $header[4] );
        $length = (int)unpack( 'N', substr( $header, 5, 4 ) )[1];
        if ( $length > $this->maxBytes )
            throw new expImportSourceException( 'Cassandra: an answer is larger than [CassandraSourceSettings] MaxFrameMegabytes' );
        if ( $flags & 0x01 )
            throw new expImportSourceException( 'Cassandra: the server sent a compressed frame, which was not asked for' );
        $this->body = $length > 0 ? $this->readBytes( $length ) : '';
        $this->pos = 0;
        if ( $flags & 0x02 )
            $this->take( 16 );
        if ( $flags & 0x08 )
        {
            $n = $this->readShort();
            for ( $i = 0; $i < $n; $i++ )
                $this->readString();
        }
        if ( $flags & 0x04 )
        {
            $n = $this->readShort();
            for ( $i = 0; $i < $n; $i++ )
            {
                $this->readString();
                $this->readBytesValue();
            }
        }
        if ( $op === self::OP_ERROR )
        {
            $code = $this->readInt();
            $message = $this->readString();
            $what = array( 0x0100 => 'bad credentials', 0x2100 => 'unauthorized', 0x2000 => 'syntax error', 0x2200 => 'invalid query',
                           0x1001 => 'overloaded', 0x1000 => 'unavailable', 0x1200 => 'read timeout', 0x000A => 'protocol error' );
            throw new expImportSourceException( 'Cassandra: ' . ( isset( $what[$code] ) ? $what[$code] : sprintf( 'error 0x%04x', $code ) ) . ': ' . substr( $message, 0, 300 ) );
        }
        return array( $op, $flags );
    }

    /**
     * The rows of a ROWS result (after its kind).
     *
     * @return array columns, rows, paging_state
     * @throws expImportSourceException
     */
    protected function readRows()
    {
        $flags = $this->readInt();
        $count = $this->readInt();
        $paging = null;
        if ( $flags & 0x0002 )
            $paging = $this->readBytesValue();
        if ( $flags & 0x0004 )
            throw new expImportSourceException( 'Cassandra: a result without metadata, which was not asked for' );
        if ( $flags & 0x0001 )
        {
            $this->readString();
            $this->readString();
        }
        $columns = array();
        for ( $i = 0; $i < $count; $i++ )
        {
            if ( !( $flags & 0x0001 ) )
            {
                $this->readString();
                $this->readString();
            }
            $columns[] = array( $this->readString(), $this->readType() );
        }
        $n = $this->readInt();
        $rows = array();
        for ( $r = 0; $r < $n; $r++ )
        {
            $row = array();
            foreach ( $columns as $column )
            {
                $bytes = $this->readBytesValue();
                $row[$column[0]] = $bytes === null ? null : self::decode( $column[1], $bytes );
            }
            $rows[] = $row;
        }
        return array( 'columns' => array_map( function ( $c ) { return $c[0]; }, $columns ), 'rows' => $rows,
                      'paging_state' => $paging === null || $paging === '' ? null : $paging );
    }

    /**
     * A type [option]: array( 'id' => int, 'of' => array of types (list, set, map, tuple), 'fields' => names (UDT),
     * 'class' => custom class ).
     *
     * @return array
     */
    protected function readType()
    {
        $id = $this->readShort();
        $type = array( 'id' => $id );
        switch ( $id )
        {
            case 0x0000:
                $type['class'] = $this->readString();
                break;
            case 0x0020:
            case 0x0022:
                $type['of'] = array( $this->readType() );
                break;
            case 0x0021:
                $type['of'] = array( $this->readType(), $this->readType() );
                break;
            case 0x0030:
                $this->readString();
                $this->readString();
                $n = $this->readShort();
                $type['fields'] = array();
                $type['of'] = array();
                for ( $i = 0; $i < $n; $i++ )
                {
                    $type['fields'][] = $this->readString();
                    $type['of'][] = $this->readType();
                }
                break;
            case 0x0031:
                $n = $this->readShort();
                $type['of'] = array();
                for ( $i = 0; $i < $n; $i++ )
                    $type['of'][] = $this->readType();
                break;
        }
        return $type;
    }

    // ---- values ------------------------------------------------------------------------------------------------------

    /**
     * The PHP value of a serialized value.
     *
     * @param array $type readType()
     * @param string $data
     * @return mixed
     */
    public static function decode( array $type, $data )
    {
        $data = (string)$data;
        switch ( $type['id'] )
        {
            case 0x0001: // ascii
            case 0x000D: // varchar, text
                return $data;
            case 0x0002: // bigint
            case 0x0005: // counter
                return strlen( $data ) === 8 ? (int)unpack( 'J', $data )[1] : null;
            case 0x0009: // int
                return strlen( $data ) === 4 ? self::signed( (int)unpack( 'N', $data )[1], 32 ) : null;
            case 0x0013: // smallint
                return strlen( $data ) === 2 ? self::signed( (int)unpack( 'n', $data )[1], 16 ) : null;
            case 0x0014: // tinyint
                return strlen( $data ) === 1 ? self::signed( ord( $data ), 8 ) : null;
            case 0x0004: // boolean
                return $data !== '' && ord( $data[0] ) !== 0;
            case 0x0007: // double
                return strlen( $data ) === 8 ? (float)unpack( 'E', $data )[1] : null;
            case 0x0008: // float
                return strlen( $data ) === 4 ? (float)unpack( 'G', $data )[1] : null;
            case 0x0006: // decimal
                if ( strlen( $data ) < 4 )
                    return null;
                return self::decimal( self::varint( substr( $data, 4 ) ), self::signed( (int)unpack( 'N', substr( $data, 0, 4 ) )[1], 32 ) );
            case 0x000E: // varint
                return self::varint( $data );
            case 0x000C: // uuid
            case 0x000F: // timeuuid
                if ( strlen( $data ) !== 16 )
                    return null;
                $hex = bin2hex( $data );
                return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
            case 0x000B: // timestamp: milliseconds since the epoch
                if ( strlen( $data ) !== 8 )
                    return null;
                $ms = (int)unpack( 'J', $data )[1];
                $seconds = intdiv( $ms, 1000 );
                $rest = $ms - $seconds * 1000;
                if ( $rest < 0 )
                {
                    $seconds--;
                    $rest += 1000;
                }
                return gmdate( 'Y-m-d\TH:i:s', $seconds ) . ( $rest ? sprintf( '.%03d', $rest ) : '' ) . 'Z';
            case 0x0011: // date: days with the epoch at 2^31
                if ( strlen( $data ) !== 4 )
                    return null;
                return gmdate( 'Y-m-d', ( (int)unpack( 'N', $data )[1] - 2147483648 ) * 86400 );
            case 0x0012: // time: nanoseconds since midnight
                if ( strlen( $data ) !== 8 )
                    return null;
                $ns = (int)unpack( 'J', $data )[1];
                $seconds = intdiv( $ns, 1000000000 );
                $fraction = $ns % 1000000000;
                return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 )
                     . ( $fraction ? '.' . rtrim( sprintf( '%09d', $fraction ), '0' ) : '' );
            case 0x0010: // inet
                $text = in_array( strlen( $data ), array( 4, 16 ), true ) ? @inet_ntop( $data ) : false;
                return $text === false ? base64_encode( $data ) : $text;
            case 0x0020: // list
            case 0x0022: // set
                $out = array();
                $pos = 0;
                $n = self::intAt( $data, $pos );
                for ( $i = 0; $i < $n && $pos <= strlen( $data ); $i++ )
                {
                    $item = self::bytesAt( $data, $pos );
                    $out[] = $item === null ? null : self::decode( $type['of'][0], $item );
                }
                return $out;
            case 0x0021: // map
                $out = new stdClass();
                $pos = 0;
                $n = self::intAt( $data, $pos );
                for ( $i = 0; $i < $n && $pos <= strlen( $data ); $i++ )
                {
                    $key = self::bytesAt( $data, $pos );
                    $value = self::bytesAt( $data, $pos );
                    $k = $key === null ? '' : self::decode( $type['of'][0], $key );
                    $k = is_bool( $k ) ? ( $k ? 'true' : 'false' ) : ( is_scalar( $k ) ? (string)$k : (string)json_encode( $k ) );
                    $out->{$k} = $value === null ? null : self::decode( $type['of'][1], $value );
                }
                return $out;
            case 0x0030: // user-defined type
                $out = array();
                $pos = 0;
                foreach ( $type['fields'] as $i => $name )
                {
                    if ( $pos >= strlen( $data ) )
                        break;
                    $item = self::bytesAt( $data, $pos );
                    $out[$name] = $item === null ? null : self::decode( $type['of'][$i], $item );
                }
                return $out;
            case 0x0031: // tuple
                $out = array();
                $pos = 0;
                foreach ( $type['of'] as $inner )
                {
                    if ( $pos >= strlen( $data ) )
                        break;
                    $item = self::bytesAt( $data, $pos );
                    $out[] = $item === null ? null : self::decode( $inner, $item );
                }
                return $out;
        }
        // blob, custom and types this client does not know (duration ...): base64
        return base64_encode( $data );
    }

    /** @return int a value of $bits bits as a signed number */
    protected static function signed( $value, $bits )
    {
        $half = 1 << ( $bits - 1 );
        return $value >= $half ? $value - ( 1 << $bits ) : $value;
    }

    /** @return int the [int] at a position of a value, moving past it */
    protected static function intAt( $data, &$pos )
    {
        if ( $pos + 4 > strlen( $data ) )
        {
            $pos = strlen( $data ) + 1;
            return 0;
        }
        $n = self::signed( (int)unpack( 'N', substr( $data, $pos, 4 ) )[1], 32 );
        $pos += 4;
        return $n;
    }

    /** @return string|null the [bytes] at a position of a value, moving past it */
    protected static function bytesAt( $data, &$pos )
    {
        $n = self::intAt( $data, $pos );
        if ( $n < 0 )
            return null;
        $item = (string)substr( $data, $pos, $n );
        $pos += $n;
        return $item;
    }

    /**
     * A varint (two's complement, big-endian, any length) as a decimal string, without gmp or bcmath.
     *
     * @param string $data
     * @return string
     */
    public static function varint( $data )
    {
        $data = (string)$data;
        if ( $data === '' )
            return '0';
        $bytes = array_values( unpack( 'C*', $data ) );
        $negative = ( $bytes[0] & 0x80 ) !== 0;
        if ( $negative )
        {
            // the magnitude: invert and add one
            $carry = 1;
            for ( $i = count( $bytes ) - 1; $i >= 0; $i-- )
            {
                $v = ( ~$bytes[$i] & 0xFF ) + $carry;
                $bytes[$i] = $v & 0xFF;
                $carry = $v >> 8;
            }
        }
        $digits = '';
        while ( $bytes && !( count( $bytes ) === 1 && $bytes[0] === 0 ) )
        {
            $rest = 0;
            $next = array();
            foreach ( $bytes as $b )
            {
                $acc = $rest * 256 + $b;
                $q = intdiv( $acc, 10 );
                $rest = $acc % 10;
                if ( $next || $q )
                    $next[] = $q;
            }
            $digits = $rest . $digits;
            $bytes = $next;
        }
        if ( $digits === '' )
            $digits = '0';
        return ( $negative && $digits !== '0' ? '-' : '' ) . $digits;
    }

    /**
     * An unscaled value and a scale as a decimal string: 12345 and 2 give 123.45.
     *
     * @param string $unscaled
     * @param int $scale
     * @return string
     */
    public static function decimal( $unscaled, $scale )
    {
        $negative = $unscaled !== '' && $unscaled[0] === '-';
        $digits = ltrim( (string)$unscaled, '-' );
        if ( $scale <= 0 )
            $text = $digits === '0' ? '0' : $digits . str_repeat( '0', -$scale );
        else
        {
            $digits = str_pad( $digits, $scale + 1, '0', STR_PAD_LEFT );
            $text = substr( $digits, 0, -$scale ) . '.' . substr( $digits, -$scale );
        }
        return ( $negative ? '-' : '' ) . $text;
    }

    // ---- the body of a frame -----------------------------------------------------------------------------------------

    /** @return string a [string map] */
    protected function stringMap( array $map )
    {
        $out = pack( 'n', count( $map ) );
        foreach ( $map as $key => $value )
            $out .= pack( 'n', strlen( (string)$key ) ) . $key . pack( 'n', strlen( (string)$value ) ) . $value;
        return $out;
    }

    /** @return string [bytes] */
    protected function bytes( $data )
    {
        return pack( 'N', strlen( (string)$data ) ) . $data;
    }

    /** @return string the next $n bytes of the body */
    protected function take( $n )
    {
        if ( $n < 0 || $this->pos + $n > strlen( $this->body ) )
            throw new expImportSourceException( 'Cassandra: an answer ends too early' );
        $out = (string)substr( $this->body, $this->pos, $n );
        $this->pos += $n;
        return $out;
    }

    protected function readShort()
    {
        return (int)unpack( 'n', $this->take( 2 ) )[1];
    }

    protected function readInt()
    {
        return self::signed( (int)unpack( 'N', $this->take( 4 ) )[1], 32 );
    }

    protected function readString()
    {
        return $this->take( $this->readShort() );
    }

    /** @return string|null [bytes], null for a negative length */
    protected function readBytesValue()
    {
        $n = $this->readInt();
        return $n < 0 ? null : $this->take( $n );
    }

    // ---- the socket --------------------------------------------------------------------------------------------------

    /**
     * @param string $host
     * @param int $port
     * @param bool $tls
     * @param int $timeout
     * @throws expImportSourceException
     */
    protected function connect( $host, $port, $tls, $timeout )
    {
        $context = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
                                                                 'SNI_enabled' => true ) ) );
        $address = ( $tls ? 'tls://' : 'tcp://' ) . ( strpos( $host, ':' ) !== false ? '[' . $host . ']' : $host ) . ':' . $port;
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client( $address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context );
        if ( !$stream )
            throw new expImportSourceException( "Cassandra: no connection to $host:$port" . ( $errstr !== '' ? ": $errstr" : '' ) );
        stream_set_timeout( $stream, $timeout );
        $this->stream = $stream;
    }

    /** Writes all of a frame. */
    protected function write( $data )
    {
        if ( !$this->stream )
            throw new expImportSourceException( 'Cassandra: not connected' );
        $data = (string)$data;
        while ( $data !== '' )
        {
            $n = @fwrite( $this->stream, $data );
            if ( !$n )
                throw new expImportSourceException( 'Cassandra: the connection was closed while sending' );
            $data = (string)substr( $data, $n );
        }
    }

    /** @return string exactly $n bytes of the socket */
    protected function readBytes( $n )
    {
        if ( !$this->stream )
            throw new expImportSourceException( 'Cassandra: not connected' );
        $out = '';
        while ( strlen( $out ) < $n )
        {
            $chunk = fread( $this->stream, min( 65536, $n - strlen( $out ) ) );
            if ( $chunk === false || $chunk === '' )
            {
                $meta = stream_get_meta_data( $this->stream );
                throw new expImportSourceException( !empty( $meta['timed_out'] ) ? 'Cassandra: the server did not answer in time ([CassandraSourceSettings] Timeout)'
                                                                                  : 'Cassandra: the connection was closed' );
            }
            $out .= $chunk;
        }
        return $out;
    }
}
