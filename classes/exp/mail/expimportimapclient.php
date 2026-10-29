<?php
/**
 * File containing the expImportImapClient class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A small IMAP4rev1 client (RFC 3501) for PHP without the imap extension: it speaks just what the IMAP source needs,
 * over TLS from the start (port 993), STARTTLS or, when asked, plain TCP:
 *
 *   LOGIN, EXAMINE (read-only) or SELECT, UID SEARCH, UID FETCH (RFC822.SIZE, BODY.PEEK[] or BODY.PEEK[HEADER]),
 *   UID STORE +FLAGS.SILENT (\Seen) and LOGOUT
 *
 * BODY.PEEK never sets \Seen, so reading changes nothing in the mailbox. Certificates are verified. The input and
 * output of the connection go through readLine(), readBytes() and write(), so a test can replace the server.
 */
class expImportImapClient implements expImportImapTransport
{
    /** @var resource|null */
    protected $stream = null;

    /** @var int */
    protected $tag = 0;

    /** @var int the size limit of one response line or literal, in bytes */
    protected $maxBytes = 67108864;

    public function open( $host, $port, $encryption, $user, $password, $mailbox, $readOnly )
    {
        $timeout = max( 5, (int)expImport::setting( 'ImapSourceSettings', 'Timeout', 30 ) );
        $this->connect( (string)$host, (int)$port, (string)$encryption, $timeout );
        $greeting = $this->readLine();
        if ( strpos( $greeting, '* OK' ) !== 0 && strpos( $greeting, '* PREAUTH' ) !== 0 )
            throw new expImportSourceException( 'IMAP: the server did not greet: ' . self::short( $greeting ) );
        if ( $encryption === 'tls' )
        {
            $this->command( 'STARTTLS' );
            $this->startTls();
        }
        $this->command( 'LOGIN ' . $this->astring( $user ) . ' ' . $this->astring( $password ) );
        $this->command( ( $readOnly ? 'EXAMINE ' : 'SELECT ' ) . $this->astring( self::mailboxName( $mailbox ) ) );
    }

    /**
     * Opens the connection (TLS from the start for "ssl").
     *
     * @param string $host
     * @param int $port
     * @param string $encryption
     * @param int $timeout
     * @throws expImportSourceException
     */
    protected function connect( $host, $port, $encryption, $timeout )
    {
        $context = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
                                                                 'SNI_enabled' => true ) ) );
        $address = ( $encryption === 'ssl' ? 'ssl://' : 'tcp://' ) . ( strpos( $host, ':' ) !== false ? '[' . $host . ']' : $host ) . ':' . $port;
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client( $address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context );
        if ( !$stream )
            throw new expImportSourceException( "IMAP: no connection to $host:$port" . ( $errstr !== '' ? ": $errstr" : '' ) );
        stream_set_timeout( $stream, $timeout );
        $this->stream = $stream;
    }

    /** Switches the connection to TLS after STARTTLS. */
    protected function startTls()
    {
        if ( !$this->stream || !@stream_socket_enable_crypto( $this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) )
            throw new expImportSourceException( 'IMAP: STARTTLS failed (the certificate or the TLS handshake)' );
    }

    public function search( $criteria )
    {
        $uids = array();
        foreach ( $this->command( 'UID SEARCH ' . self::criteria( $criteria ) ) as $response )
            if ( preg_match( '/^\* SEARCH\b(.*)$/i', $response['line'], $m ) )
                foreach ( preg_split( '/\s+/', trim( $m[1] ) ) as $uid )
                    if ( ctype_digit( $uid ) )
                        $uids[] = (int)$uid;
        sort( $uids );
        return array_values( array_unique( $uids ) );
    }

    public function size( $uid )
    {
        foreach ( $this->command( 'UID FETCH ' . (int)$uid . ' (RFC822.SIZE)' ) as $response )
            if ( preg_match( '/RFC822\.SIZE (\d+)/i', $response['line'], $m ) )
                return (int)$m[1];
        return 0;
    }

    public function message( $uid, $headersOnly = false )
    {
        foreach ( $this->command( 'UID FETCH ' . (int)$uid . ' (' . ( $headersOnly ? 'BODY.PEEK[HEADER]' : 'BODY.PEEK[]' ) . ')' ) as $response )
            if ( preg_match( '/^\* \d+ FETCH\b/i', $response['line'] ) && $response['literals'] )
                return $response['literals'][0];
        throw new expImportSourceException( "IMAP: message $uid was not sent by the server" );
    }

    public function markSeen( $uid )
    {
        $this->command( 'UID STORE ' . (int)$uid . ' +FLAGS.SILENT (\\Seen)' );
    }

    public function close()
    {
        if ( $this->stream )
        {
            try
            {
                $this->command( 'LOGOUT' );
            }
            catch ( expImportSourceException $e )
            {
            }
            @fclose( $this->stream );
        }
        $this->stream = null;
    }

    /**
     * Sends a command and reads up to its tagged answer.
     *
     * @param string $command without tag and line end
     * @return array the untagged responses: list of array( 'line' (the text, literals left out), 'literals' (list of string) )
     * @throws expImportSourceException when the answer is not OK
     */
    public function command( $command )
    {
        $tag = 'A' . str_pad( (string)++$this->tag, 3, '0', STR_PAD_LEFT );
        $this->write( $tag . ' ' . $command . "\r\n" );
        $responses = array();
        while ( true )
        {
            $line = $this->readLine();
            $literals = array();
            // a line that ends in {n} is followed by n bytes and the rest of the line
            while ( preg_match( '/\{(\d+)\}$/', $line, $m ) )
            {
                $n = (int)$m[1];
                if ( $n > $this->maxBytes )
                    throw new expImportSourceException( "IMAP: the server sent a literal of $n bytes, more than allowed" );
                $literals[] = $this->readBytes( $n );
                $line = substr( $line, 0, -strlen( $m[0] ) ) . '"" ' . $this->readLine();
            }
            if ( strpos( $line, $tag . ' ' ) === 0 )
            {
                if ( !preg_match( '/^' . $tag . ' OK\b/i', $line ) )
                {
                    $what = strpos( $command, 'LOGIN ' ) === 0 ? 'LOGIN' : $command;
                    throw new expImportSourceException( 'IMAP: ' . $what . ' failed: ' . self::short( substr( $line, strlen( $tag ) + 1 ) ) );
                }
                return $responses;
            }
            if ( strpos( $line, '+' ) === 0 )
                throw new expImportSourceException( 'IMAP: the server asked for more input than the command has' );
            $responses[] = array( 'line' => $line, 'literals' => $literals );
        }
    }

    /**
     * @return string one line of the server without its line end
     * @throws expImportSourceException
     */
    protected function readLine()
    {
        $line = $this->stream ? fgets( $this->stream, 1048576 ) : false;
        if ( $line === false )
            throw new expImportSourceException( 'IMAP: the connection was closed or timed out' );
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
                throw new expImportSourceException( 'IMAP: the connection was closed or timed out' );
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
            throw new expImportSourceException( 'IMAP: the connection was closed' );
    }

    /**
     * A string argument: quoted when it is printable ASCII, else refused (a literal would need a second round trip that
     * this client does not do).
     *
     * @param string $text
     * @return string
     * @throws expImportSourceException
     */
    protected function astring( $text )
    {
        $text = (string)$text;
        if ( !preg_match( '/^[\x20-\x7E]*$/', $text ) )
            throw new expImportSourceException( 'IMAP: user, password and mailbox must be printable ASCII for this client (install the PHP extension imap otherwise)' );
        return '"' . addcslashes( $text, '"\\' ) . '"';
    }

    /**
     * The search criteria as they may be sent: printable ASCII, no literal and no line break.
     *
     * @param string $criteria
     * @return string
     * @throws expImportSourceException
     */
    public static function criteria( $criteria )
    {
        $criteria = trim( (string)$criteria );
        if ( $criteria === '' )
            return 'ALL';
        if ( !preg_match( '/^[\x20-\x7E]+$/', $criteria ) || strpos( $criteria, '{' ) !== false )
            throw new expImportSourceException( 'IMAP: the search criteria must be printable ASCII without { }' );
        return $criteria;
    }

    /** @return string a mailbox name in modified UTF-7 (RFC 3501 5.1.3) */
    public static function mailboxName( $mailbox )
    {
        $mailbox = (string)$mailbox;
        if ( preg_match( '/^[\x20-\x7E]*$/', $mailbox ) )
            return str_replace( '&', '&-', $mailbox );
        if ( function_exists( 'mb_convert_encoding' ) )
            return (string)mb_convert_encoding( $mailbox, 'UTF7-IMAP', 'UTF-8' );
        return $mailbox;
    }

    /** @return string a server answer for a message, shortened */
    protected static function short( $text )
    {
        $text = trim( (string)$text );
        return strlen( $text ) > 200 ? substr( $text, 0, 200 ) . '...' : $text;
    }
}
