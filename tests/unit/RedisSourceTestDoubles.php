<?php
/**
 * The test doubles of RedisSourceTest: a Redis database in memory that answers the read-only commands as RESP2 gives
 * them, and the socket client of the extension against a server that answers from a script.
 */

/** A database in memory: key => array( type, value ), SCAN in pages of COUNT keys over the keys in their order. */
class RedisSourceTestTransport implements expImportRedisTransport
{
    /** @var array key => array( 'type', 'value', 'ttl' ) */
    public $data;

    /** @var array|null the arguments of open() */
    public $opened = null;

    /** @var array[] every command sent */
    public $commands = array();

    /** @var bool */
    public $closed = false;

    /** @var string|null an error open() throws */
    public $failOpen = null;

    /** @var bool whether SCAN gives the first key of each page twice (as SCAN may) */
    public $duplicates = false;

    public function __construct( array $data )
    {
        $this->data = $data;
    }

    public function open( $host, $port, $tls, $user, $password, $database, $timeout )
    {
        if ( $this->failOpen !== null )
            throw new expImportSourceException( $this->failOpen );
        $this->opened = array( $host, $port, $tls, $user, $password, $database, $timeout );
    }

    public function command( array $args )
    {
        $this->commands[] = $args;
        $name = strtoupper( (string)$args[0] );
        if ( !in_array( $name, self::READ_COMMANDS, true ) )
            throw new expImportSourceException( "Redis: the command $name is not one of the read-only commands of the source" );
        $key = isset( $args[1] ) ? (string)$args[1] : '';
        $entry = isset( $this->data[$key] ) ? $this->data[$key] : null;
        switch ( $name )
        {
            case 'SCAN':
                $count = (int)$args[5];
                $keys = array_values( array_filter( array_keys( $this->data ), function ( $k ) use ( $args ) { return fnmatch( (string)$args[3], (string)$k ); } ) );
                $page = array_slice( $keys, (int)$args[1], $count );
                if ( $this->duplicates && $page )
                    $page[] = $page[0];
                $next = (int)$args[1] + $count;
                return array( $next >= count( $keys ) ? '0' : (string)$next, array_map( 'strval', $page ) );
            case 'TYPE':
                return $entry ? $entry['type'] : 'none';
            case 'TTL':
                return $entry ? ( isset( $entry['ttl'] ) ? $entry['ttl'] : -1 ) : -2;
            case 'HGETALL':
                $flat = array();
                foreach ( $entry['value'] as $field => $value )
                    array_push( $flat, (string)$field, (string)$value );
                return $flat;
            case 'GET':
                return $entry ? $entry['value'] : null;
            case 'LRANGE':
                return array_slice( $entry['value'], (int)$args[2], (int)$args[3] - (int)$args[2] + 1 );
            case 'SMEMBERS':
                return $entry['value'];
            case 'ZRANGE':
                $flat = array();
                foreach ( array_slice( $entry['value'], (int)$args[2], (int)$args[3] - (int)$args[2] + 1, true ) as $member => $score )
                    array_push( $flat, (string)$member, (string)$score );
                return $flat;
            case 'XRANGE':
                return array_slice( $entry['value'], 0, (int)$args[5] );
            case 'JSON.GET':
                // RedisJSON: a JSONPath ($...) gives the list of its matches, a legacy path (.x) the value
                $document = json_decode( (string)$entry['value'], true );
                $path = (string)$args[2];
                $parts = array_values( array_filter( explode( '.', ltrim( $path, '$' ) ), 'strlen' ) );
                foreach ( $parts as $part )
                    $document = is_array( $document ) && array_key_exists( $part, $document ) ? $document[$part] : null;
                return json_encode( $path[0] === '$' ? array( $document ) : $document );
        }
        return null;
    }

    public function driver()
    {
        return 'memory';
    }

    public function close()
    {
        $this->closed = true;
    }
}

/** The socket client of the extension against a server that answers each command with the next answer of a list. */
class RedisSourceTestScriptedClient extends expImportRedisClient
{
    /** @var string[] what the client sent, as raw RESP */
    public $sent = array();

    /** @var array|null the arguments of connect() */
    public $connected = null;

    /** @var string the bytes the server has still to send */
    protected $buffer = '';

    /** @var string[] the answers, one per command, as raw RESP */
    protected $answers;

    public function __construct( array $answers )
    {
        parent::__construct();
        $this->answers = $answers;
    }

    protected function connect( $host, $port, $tls, $timeout )
    {
        $this->connected = array( $host, $port, $tls, $timeout );
    }

    protected function write( $data )
    {
        $this->sent[] = $data;
        $this->buffer .= (string)array_shift( $this->answers );
    }

    protected function readLine()
    {
        $end = strpos( $this->buffer, "\r\n" );
        if ( $end === false )
            throw new expImportSourceException( 'Redis: the connection was closed or timed out' );
        $line = substr( $this->buffer, 0, $end );
        $this->buffer = (string)substr( $this->buffer, $end + 2 );
        return $line;
    }

    protected function readBytes( $n )
    {
        if ( strlen( $this->buffer ) < $n )
            throw new expImportSourceException( 'Redis: the connection was closed or timed out' );
        $data = substr( $this->buffer, 0, $n );
        $this->buffer = (string)substr( $this->buffer, $n );
        return $data;
    }
}
