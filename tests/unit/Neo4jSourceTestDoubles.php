<?php
/**
 * The test double of Neo4jSourceTest: a Bolt 4.4 server in memory that answers the client's messages from a script
 * (HELLO, RUN, PULL n, DISCARD, GOODBYE), encoding with the client's own PackStream.
 */
class Neo4jSourceTestBoltServer implements expImportBoltTransport
{
    /** @var string what the client sent and the server has not read yet */
    protected $in = '';

    /** @var string what the server sent and the client has not read yet */
    protected $out = '';

    /** @var bool */
    protected $shaken = false;

    /** @var array[] the messages received: array( signature, fields ) */
    public $messages = array();

    /** @var string[] the fields of the result */
    public $fields = array();

    /** @var array[] the records of the result (lists of values) */
    public $records = array();

    /** @var string the password HELLO must give ('' for none) */
    public $password = '';

    /** @var string|null a FAILURE message for RUN */
    public $failRun = null;

    /** @var string the version the handshake agrees on (4 bytes) */
    public $version = "\x00\x00\x04\x04";

    /** @var int the next record PULL sends */
    protected $next = 0;

    /** @var bool */
    public $closed = false;

    public function write( $data )
    {
        $this->in .= (string)$data;
        if ( !$this->shaken )
        {
            if ( strlen( $this->in ) < 20 )
                return;
            if ( substr( $this->in, 0, 4 ) !== "\x60\x60\xB0\x17" )
                throw new expImportSourceException( 'no Bolt magic' );
            $this->in = (string)substr( $this->in, 20 );
            $this->out .= $this->version;
            $this->shaken = true;
        }
        while ( ( $message = $this->take() ) !== null )
            $this->answer( $message );
    }

    public function read( $n )
    {
        if ( strlen( $this->out ) < $n )
            throw new expImportSourceException( 'Bolt: the connection was closed or timed out' );
        $data = (string)substr( $this->out, 0, $n );
        $this->out = (string)substr( $this->out, $n );
        return $data;
    }

    public function close()
    {
        // the next connection starts with a handshake again
        $this->closed = true;
        $this->shaken = false;
        $this->in = '';
        $this->out = '';
    }

    /** @return expImportBoltStructure|null one whole message of the input */
    protected function take()
    {
        $pos = 0;
        $data = '';
        while ( true )
        {
            if ( strlen( $this->in ) < $pos + 2 )
                return null;
            $size = unpack( 'n', substr( $this->in, $pos, 2 ) )[1];
            $pos += 2;
            if ( $size === 0 )
                break;
            if ( strlen( $this->in ) < $pos + $size )
                return null;
            $data .= substr( $this->in, $pos, $size );
            $pos += $size;
        }
        $this->in = (string)substr( $this->in, $pos );
        $p = 0;
        return expImportBoltClient::unpack( $data, $p );
    }

    protected function send( $signature, array $fields )
    {
        $this->out .= expImportBoltClient::chunk( expImportBoltClient::pack( new expImportBoltStructure( $signature, $fields ) ) );
    }

    protected function answer( expImportBoltStructure $message )
    {
        $this->messages[] = array( $message->signature, $message->fields );
        switch ( $message->signature )
        {
            case 0x01:
                $extra = $message->fields[0];
                if ( $this->password !== '' && ( !isset( $extra['credentials'] ) || $extra['credentials'] !== $this->password ) )
                    $this->send( 0x7F, array( array( 'code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'The client is unauthorized' ) ) );
                else
                    $this->send( 0x70, array( array( 'server' => 'Neo4j/5.12.0', 'connection_id' => 'bolt-1' ) ) );
                return;
            case 0x10:
                if ( $this->failRun !== null )
                {
                    $this->send( 0x7F, array( array( 'code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => $this->failRun ) ) );
                    return;
                }
                $this->next = 0;
                $this->send( 0x70, array( array( 'fields' => $this->fields, 't_first' => 1 ) ) );
                return;
            case 0x3F:
                $n = (int)$message->fields[0]['n'];
                foreach ( array_slice( $this->records, $this->next, $n ) as $record )
                    $this->send( 0x71, array( $record ) );
                $this->next += $n;
                $this->send( 0x70, $this->next < count( $this->records ) ? array( array( 'has_more' => true ) ) : array( array( 'type' => 'r', 't_last' => 1 ) ) );
                return;
            case 0x2F:
                $this->next = count( $this->records );
                $this->send( 0x70, array( array( 'type' => 'r' ) ) );
                return;
        }
    }
}
