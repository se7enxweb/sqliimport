<?php
/**
 * File containing the expImportRedisClient class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A minimal Redis client over a socket (driver "socket"), for a PHP without the extension redis: commands as RESP
 * arrays of bulk strings, answers in RESP2 (simple strings, errors, integers, bulk strings, arrays). TLS verifies the
 * certificate. Only AUTH and SELECT (in open()) and the commands of READ_COMMANDS are ever sent.
 */
class expImportRedisClient implements expImportRedisTransport
{
    /** @var resource|null */
    protected $stream = null;

    /** @var int the largest bulk string accepted, in bytes */
    protected $maxBytes;

    /** @var int the most elements of one array accepted */
    protected $maxElements = 1000000;

    public function __construct()
    {
        $this->maxBytes = max( 1, (int)expImport::setting( 'RedisSourceSettings', 'MaxValueMegabytes', 16 ) ) * 1048576;
    }

    public function open( $host, $port, $tls, $user, $password, $database, $timeout )
    {
        $this->connect( (string)$host, (int)$port, (bool)$tls, max( 1, (int)$timeout ) );
        if ( (string)$password !== '' )
            $this->send( (string)$user !== '' ? array( 'AUTH', (string)$user, (string)$password ) : array( 'AUTH', (string)$password ) );
        if ( (int)$database !== 0 )
            $this->send( array( 'SELECT', (string)(int)$database ) );
    }

    public function command( array $args )
    {
        $name = strtoupper( (string)reset( $args ) );
        if ( !in_array( $name, self::READ_COMMANDS, true ) )
            throw new expImportSourceException( "Redis: the command $name is not one of the read-only commands of the source" );
        return $this->send( $args );
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

    /**
     * Sends a command and reads its answer.
     *
     * @param array $args
     * @return mixed
     * @throws expImportSourceException
     */
    protected function send( array $args )
    {
        $data = '*' . count( $args ) . "\r\n";
        foreach ( $args as $arg )
            $data .= '$' . strlen( (string)$arg ) . "\r\n" . (string)$arg . "\r\n";
        $this->write( $data );
        return $this->reply();
    }

    /**
     * One answer of the server.
     *
     * @return mixed
     * @throws expImportSourceException
     */
    protected function reply()
    {
        $line = $this->readLine();
        if ( $line === '' )
            throw new expImportSourceException( 'Redis: an empty answer' );
        $type = $line[0];
        $rest = (string)substr( $line, 1 );
        switch ( $type )
        {
            case '+':
                return $rest;
            case '-':
                throw new expImportSourceException( 'Redis: ' . $rest );
            case ':':
                return (int)$rest;
            case '$':
                $n = (int)$rest;
                if ( $n < 0 )
                    return null;
                if ( $n > $this->maxBytes )
                    throw new expImportSourceException( 'Redis: a value is larger than [RedisSourceSettings] MaxValueMegabytes' );
                $data = $this->readBytes( $n + 2 );
                return (string)substr( $data, 0, $n );
            case '*':
                $n = (int)$rest;
                if ( $n < 0 )
                    return null;
                if ( $n > $this->maxElements )
                    throw new expImportSourceException( 'Redis: an answer with too many elements' );
                $list = array();
                for ( $i = 0; $i < $n; $i++ )
                    $list[] = $this->reply();
                return $list;
        }
        throw new expImportSourceException( 'Redis: an answer this client does not read' );
    }

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
            throw new expImportSourceException( "Redis: no connection to $host:$port" . ( $errstr !== '' ? ": $errstr" : '' ) );
        stream_set_timeout( $stream, $timeout );
        $this->stream = $stream;
    }

    /**
     * @return string a line without its CRLF
     * @throws expImportSourceException
     */
    protected function readLine()
    {
        $line = $this->stream ? fgets( $this->stream, 65536 ) : false;
        if ( $line === false )
            throw new expImportSourceException( 'Redis: the connection was closed or timed out' );
        return rtrim( $line, "\r\n" );
    }

    /**
     * @param int $n
     * @return string exactly n bytes
     * @throws expImportSourceException
     */
    protected function readBytes( $n )
    {
        $data = '';
        while ( strlen( $data ) < $n )
        {
            $chunk = $this->stream ? fread( $this->stream, min( 1048576, $n - strlen( $data ) ) ) : false;
            if ( $chunk === false || $chunk === '' )
                throw new expImportSourceException( 'Redis: the connection was closed or timed out' );
            $data .= $chunk;
        }
        return $data;
    }

    /**
     * @param string $data
     * @throws expImportSourceException
     */
    protected function write( $data )
    {
        if ( !$this->stream || fwrite( $this->stream, $data ) !== strlen( $data ) )
            throw new expImportSourceException( 'Redis: the connection was closed' );
    }
}
