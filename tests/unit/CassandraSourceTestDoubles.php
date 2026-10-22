<?php

/**
 * A scripted Cassandra connection for CassandraSourceTest: pages of rows by paging state, and what it was asked.
 */
class CassandraSourceTestTransport implements expImportCassandraTransport
{
    /** @var array[] the rows of the scripted table */
    public $rows = array();

    /** @var array|null the arguments of open() */
    public $opened = null;

    /** @var array[] the queries: cql, consistency, page size, paging state */
    public $queries = array();

    /** @var string|null an error open() throws */
    public $failOpen = null;

    /** @var bool whether close() was called */
    public $closed = false;

    public function open( array $hosts, $tls, $user, $password, $keyspace, $timeout )
    {
        $this->opened = array( 'hosts' => $hosts, 'tls' => $tls, 'user' => $user, 'password' => $password, 'keyspace' => $keyspace, 'timeout' => $timeout );
        if ( $this->failOpen !== null )
            throw new expImportSourceException( $this->failOpen );
    }

    public function query( $cql, $consistency, $pageSize, $pagingState )
    {
        $this->queries[] = array( 'cql' => $cql, 'consistency' => $consistency, 'page_size' => $pageSize, 'paging_state' => $pagingState );
        $offset = $pagingState === null ? 0 : (int)substr( $pagingState, 6 );
        $page = array_slice( $this->rows, $offset, $pageSize );
        $next = $offset + count( $page ) < count( $this->rows ) ? 'state-' . ( $offset + count( $page ) ) : null;
        return array( 'columns' => $this->rows ? array_keys( $this->rows[0] ) : array( 'event_id' ), 'rows' => $page, 'paging_state' => $next );
    }

    public function driver()
    {
        return 'double';
    }

    public function close()
    {
        $this->closed = true;
    }
}

/**
 * The frames of a CQL native protocol v4 server, for the tests of the socket client.
 */
class CassandraTestFrames
{
    public static function frame( $opcode, $body, $flags = 0 )
    {
        return chr( 0x84 ) . chr( $flags ) . pack( 'n', 0 ) . chr( $opcode ) . pack( 'N', strlen( $body ) ) . $body;
    }

    public static function string( $text )
    {
        return pack( 'n', strlen( $text ) ) . $text;
    }

    public static function int( $n )
    {
        return pack( 'N', $n & 0xFFFFFFFF );
    }

    public static function bytes( $data )
    {
        return $data === null ? self::int( -1 ) : self::int( strlen( $data ) ) . $data;
    }

    /** A ROWS result: $columns name => type option bytes, $rows lists of serialized values (null: null). */
    public static function rows( array $columns, array $rows, $paging = null )
    {
        $flags = 0x0001 | ( $paging !== null ? 0x0002 : 0 );
        $body = self::int( 2 ) . self::int( $flags ) . self::int( count( $columns ) );
        if ( $paging !== null )
            $body .= self::bytes( $paging );
        $body .= self::string( 'shop' ) . self::string( 'events' );
        foreach ( $columns as $name => $type )
            $body .= self::string( $name ) . $type;
        $body .= self::int( count( $rows ) );
        foreach ( $rows as $row )
            foreach ( $row as $value )
                $body .= self::bytes( $value );
        return self::frame( 0x08, $body );
    }

    /** @return string a type option */
    public static function type( $id, ...$inner )
    {
        return pack( 'n', $id ) . implode( '', $inner );
    }

    /** @return string a serialized collection of serialized elements */
    public static function collection( array $items )
    {
        $out = self::int( count( $items ) );
        foreach ( $items as $item )
            $out .= self::bytes( $item );
        return $out;
    }
}

/**
 * The socket client over one end of a socket pair: the test writes the server's frames into the other end before the
 * client runs, and reads what the client sent afterwards.
 */
class CassandraSocketTestClient extends expImportCassandraClient
{
    /** @var resource the test's end */
    public $peer;

    /** @var array[] the hosts connect() was asked for */
    public $connects = array();

    /** @var string[] hosts that do not answer */
    public $down = array();

    public function __construct()
    {
        parent::__construct();
        list( $mine, $peer ) = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP );
        $this->pending = $mine;
        $this->peer = $peer;
        stream_set_timeout( $mine, 2 );
    }

    /** @var resource the client's end, until connect() */
    protected $pending;

    protected function connect( $host, $port, $tls, $timeout )
    {
        $this->connects[] = array( $host, $port, $tls );
        if ( in_array( $host, $this->down, true ) )
            throw new expImportSourceException( "Cassandra: no connection to $host:$port" );
        $this->stream = $this->pending;
    }

    /** Queues frames of the server. */
    public function serve( $frames )
    {
        fwrite( $this->peer, $frames );
    }

    /** @return array[] the frames the client sent: array( opcode, body ) */
    public function sent()
    {
        stream_set_blocking( $this->peer, false );
        $data = '';
        while ( ( $chunk = fread( $this->peer, 65536 ) ) !== false && $chunk !== '' )
            $data .= $chunk;
        stream_set_blocking( $this->peer, true );
        $frames = array();
        while ( strlen( $data ) >= 9 )
        {
            $length = unpack( 'N', substr( $data, 5, 4 ) )[1];
            $frames[] = array( ord( $data[0] ), ord( $data[4] ), substr( $data, 9, $length ) );
            $data = (string)substr( $data, 9 + $length );
        }
        return $frames;
    }
}
