<?php
/**
 * File containing the expImportBoltTransport interface and the expImportBoltSocket class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The byte stream the Bolt client (expImportBoltClient) talks over: a socket (expImportBoltSocket) or a test double.
 */
interface expImportBoltTransport
{
    /**
     * @param string $data
     * @throws expImportSourceException
     */
    public function write( $data );

    /**
     * @param int $n
     * @return string exactly n bytes
     * @throws expImportSourceException the connection was closed or timed out
     */
    public function read( $n );

    /** Closes the connection. */
    public function close();
}

/**
 * A TCP connection, with TLS (certificate and name verified) for bolt+s:// and neo4j+s://.
 */
class expImportBoltSocket implements expImportBoltTransport
{
    /** @var resource|null */
    protected $stream = null;

    /**
     * @param string $host
     * @param int $port
     * @param bool $tls
     * @param int $timeout seconds, for connecting and each answer
     * @throws expImportSourceException
     */
    public function __construct( $host, $port, $tls, $timeout )
    {
        $context = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
                                                                 'SNI_enabled' => true ) ) );
        $address = ( $tls ? 'tls://' : 'tcp://' ) . ( strpos( $host, ':' ) !== false ? '[' . $host . ']' : $host ) . ':' . (int)$port;
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client( $address, $errno, $errstr, max( 1, (int)$timeout ), STREAM_CLIENT_CONNECT, $context );
        if ( !$stream )
            throw new expImportSourceException( "Bolt: no connection to $host:$port" . ( $errstr !== '' ? ": $errstr" : '' ) );
        stream_set_timeout( $stream, max( 1, (int)$timeout ) );
        $this->stream = $stream;
    }

    public function write( $data )
    {
        $data = (string)$data;
        while ( $data !== '' )
        {
            $written = $this->stream ? @fwrite( $this->stream, $data ) : false;
            if ( !$written )
                throw new expImportSourceException( 'Bolt: the connection was closed' );
            $data = (string)substr( $data, $written );
        }
    }

    public function read( $n )
    {
        $data = '';
        while ( strlen( $data ) < $n )
        {
            $chunk = $this->stream ? fread( $this->stream, min( 1048576, $n - strlen( $data ) ) ) : false;
            if ( $chunk === false || $chunk === '' )
                throw new expImportSourceException( 'Bolt: the connection was closed or timed out' );
            $data .= $chunk;
        }
        return $data;
    }

    public function close()
    {
        if ( $this->stream )
            @fclose( $this->stream );
        $this->stream = null;
    }
}
