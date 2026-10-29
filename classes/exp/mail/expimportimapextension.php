<?php
/**
 * File containing the expImportImapExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The IMAP transport of the PHP extension imap (imap_open). A read-only mailbox is opened with OP_READONLY, and
 * messages are read with FT_PEEK, so their \Seen flag does not change.
 */
class expImportImapExtension implements expImportImapTransport
{
    /** @var resource|object|null the IMAP stream (IMAP\Connection since PHP 8.1) */
    protected $stream = null;

    /** @return bool whether the extension is there */
    public static function available()
    {
        return function_exists( 'imap_open' );
    }

    public function open( $host, $port, $encryption, $user, $password, $mailbox, $readOnly )
    {
        $flags = array( 'ssl' => '/imap/ssl', 'tls' => '/imap/tls', 'none' => '/imap/notls' );
        $spec = '{' . $host . ':' . (int)$port . ( isset( $flags[$encryption] ) ? $flags[$encryption] : '/imap/ssl' ) . '}' . $mailbox;
        $timeout = max( 5, (int)expImport::setting( 'ImapSourceSettings', 'Timeout', 30 ) );
        foreach ( array( IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT ) as $kind )
            imap_timeout( $kind, $timeout );
        $stream = @imap_open( $spec, $user, $password, $readOnly ? OP_READONLY : 0, 0, array( 'DISABLE_AUTHENTICATOR' => 'GSSAPI' ) );
        if ( !$stream )
            throw new expImportSourceException( 'IMAP: the mailbox cannot be opened: ' . self::lastError() );
        $this->stream = $stream;
    }

    public function search( $criteria )
    {
        $uids = @imap_search( $this->stream, (string)$criteria, SE_UID, 'UTF-8' );
        if ( $uids === false )
        {
            $error = self::lastError();
            // "no messages" is not an error
            return $error === '' ? array() : self::fail( 'the search failed: ' . $error );
        }
        $uids = array_map( 'intval', $uids );
        sort( $uids );
        return $uids;
    }

    public function size( $uid )
    {
        $overview = @imap_fetch_overview( $this->stream, (string)(int)$uid, FT_UID );
        return $overview && isset( $overview[0]->size ) ? (int)$overview[0]->size : 0;
    }

    public function message( $uid, $headersOnly = false )
    {
        $header = @imap_fetchheader( $this->stream, (int)$uid, FT_UID | FT_PREFETCHTEXT );
        if ( $header === false )
            return self::fail( "message $uid cannot be read: " . self::lastError() );
        if ( $headersOnly )
            return (string)$header;
        $body = @imap_body( $this->stream, (int)$uid, FT_UID | FT_PEEK );
        return (string)$header . (string)$body;
    }

    public function markSeen( $uid )
    {
        if ( !@imap_setflag_full( $this->stream, (string)(int)$uid, '\\Seen', ST_UID ) )
            self::fail( "message $uid cannot be marked as seen: " . self::lastError() );
    }

    public function close()
    {
        if ( $this->stream )
            @imap_close( $this->stream );
        $this->stream = null;
        // the extension keeps errors and alerts for the end of the request otherwise
        @imap_errors();
        @imap_alerts();
    }

    /** @return string the last error of the extension, '' when there is none */
    protected static function lastError()
    {
        $errors = @imap_errors();
        return $errors ? (string)end( $errors ) : (string)@imap_last_error();
    }

    /**
     * @param string $message
     * @return never
     * @throws expImportSourceException
     */
    protected static function fail( $message )
    {
        throw new expImportSourceException( 'IMAP: ' . $message );
    }
}
