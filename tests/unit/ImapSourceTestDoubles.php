<?php
/**
 * The test doubles of ImapSourceTest: a mailbox in memory and the IMAP client of the extension against a scripted server.
 */

/** A mailbox in memory. */
class ImapSourceTestTransport implements expImportImapTransport
{
    /** @var array uid => raw message */
    public $messages;

    /** @var array|null the arguments of open() */
    public $opened = null;

    /** @var string[] */
    public $searches = array();

    /** @var int[] */
    public $seen = array();

    /** @var bool */
    public $closed = false;

    /** @var string|null an error open() throws */
    public $failOpen = null;

    public function __construct( array $messages )
    {
        $this->messages = $messages;
    }

    public function open( $host, $port, $encryption, $user, $password, $mailbox, $readOnly )
    {
        if ( $this->failOpen !== null )
            throw new expImportSourceException( $this->failOpen );
        $this->opened = array( $host, $port, $encryption, $user, $password, $mailbox, $readOnly );
    }

    public function search( $criteria )
    {
        $this->searches[] = $criteria;
        return array_keys( $this->messages );
    }

    public function size( $uid )
    {
        return strlen( $this->messages[$uid] );
    }

    public function message( $uid, $headersOnly = false )
    {
        return $this->messages[$uid];
    }

    public function markSeen( $uid )
    {
        if ( !empty( $this->opened[6] ) )
            throw new expImportSourceException( 'the mailbox is read-only' );
        $this->seen[] = $uid;
    }

    public function close()
    {
        $this->closed = true;
    }
}

/** The client of the extension against a scripted server: tag => the lines (and literal data) it answers. */
class ImapSourceTestScriptedClient extends expImportImapClient
{
    /** @var string[] what the client sent, without line ends */
    public $sent = array();

    /** @var string[] what the server has still to say */
    protected $pending = array();

    /** @var array */
    protected $script;

    public function __construct( array $script )
    {
        $this->script = $script;
        $this->pending[] = $script[0];
    }

    protected function connect( $host, $port, $encryption, $timeout )
    {
    }

    protected function readLine()
    {
        if ( !$this->pending )
            throw new expImportSourceException( 'IMAP: the connection was closed or timed out' );
        return array_shift( $this->pending );
    }

    protected function readBytes( $n )
    {
        $data = (string)array_shift( $this->pending );
        if ( strlen( $data ) !== $n )
            throw new expImportSourceException( 'IMAP: a literal of the wrong length' );
        return $data;
    }

    protected function write( $data )
    {
        $line = rtrim( $data, "\r\n" );
        $this->sent[] = $line;
        $tag = substr( $line, 0, 4 );
        foreach ( isset( $this->script[$tag] ) ? $this->script[$tag] : array( $tag . ' BAD no script' ) as $answer )
            $this->pending[] = $answer;
    }

    public function close()
    {
        $this->command( 'LOGOUT' );
    }
}
