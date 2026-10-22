<?php
/**
 * File containing the expImportImapSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The messages of an IMAP mailbox (a press inbox, a submissions address): one record is one message, with its
 * message ID, sender, recipients, subject, date, text and HTML body and the names and sizes of its attachments
 * (expImportMailMessage). The source reads its data itself (origin "remote"): once per run fetch() reads the
 * matching messages into the run's snapshot, one JSON object per line.
 *
 * The import does not change the mailbox unless asked: the mailbox is opened read-only (EXAMINE, OP_READONLY) and
 * messages are read without setting \Seen; only with the option mark_seen are the messages read marked as seen.
 * The password is the name of a secret (expImportSecrets) and masked in every message; a server in a private network
 * is refused unless [SourceSettings] AllowPrivateHosts=enabled or it is listed in [ImapSourceSettings] AllowedHosts[].
 * The PHP extension imap is used where it is installed, else a small client of its own (expImportImapClient).
 */
class expImportImapSource extends expImportJsonSource
{
    public static function identifier()
    {
        return 'imap';
    }

    public static function label()
    {
        return 'E-mail inbox (IMAP)';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'content';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function description()
    {
        return 'The messages of an IMAP mailbox (a press inbox, a submissions address): one record is one message with sender, '
             . 'recipients, subject, date, text and HTML body and the names and sizes of its attachments. The mailbox is read '
             . 'without changing it unless "Mark as seen" is ticked; the password is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'The unread messages of the last 14 days of press@example.org on imap.example.org (port 993, SSL), the password as the secret press_mailbox',
                   'options' => array( 'host' => 'imap.example.org', 'port' => 993, 'encryption' => 'ssl', 'user' => 'press@example.org',
                                       'password' => 'press_mailbox', 'mailbox' => 'INBOX', 'search' => 'UNSEEN', 'days' => 14 ) ),
        );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'mail-{message_id}' ),
            'fields' => array(
                array( 'source' => 'subject', 'attribute' => 'title', 'transforms' => array( array( 'name' => 'trim' ) ) ),
                array( 'source' => 'summary', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'body', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'host' => array( 'type' => 'string', 'label' => 'IMAP server', 'required' => true, 'max' => 255,
                             'help' => 'e.g. imap.example.org (a private network only with [ImapSourceSettings] AllowedHosts[])' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 993, 'min' => 1, 'max' => 65535 ),
            'encryption' => array( 'type' => 'enum', 'label' => 'Encryption', 'default' => 'ssl',
                                   'values' => array( 'ssl' => 'SSL/TLS (port 993)', 'tls' => 'STARTTLS (port 143)', 'none' => 'None (not recommended: the password is sent in clear)' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'required' => true, 'max' => 255 ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)', 'required' => true,
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'mailbox' => array( 'type' => 'string', 'label' => 'Mailbox', 'default' => 'INBOX', 'max' => 255 ),
            'search' => array( 'type' => 'string', 'label' => 'Search', 'default' => 'ALL', 'max' => 500,
                               'help' => 'IMAP search criteria: ALL, UNSEEN, FROM "news@example.org", SUBJECT "Press" ...' ),
            'days' => array( 'type' => 'int', 'label' => 'Only the last days', 'default' => 0, 'min' => 0, 'max' => 36500,
                             'help' => '0: every matching message' ),
            'mark_seen' => array( 'type' => 'bool', 'label' => 'Mark the messages read as seen', 'default' => false,
                                  'help' => 'Off: the mailbox is opened read-only and nothing in it changes' ),
            'attachments' => array( 'type' => 'bool', 'label' => 'List the attachments', 'default' => true,
                                    'help' => 'Their names, types and sizes (never their content)' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'message_id',
                            'help' => 'The field that names a message; message_id is unique' ),
        );
    }

    /** The snapshot is always one message per line. */
    protected function format()
    {
        return 'ndjson';
    }

    /**
     * Reads the matching messages into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most messages to read (the newest; the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $host = isset( $options['host'] ) ? trim( (string)$options['host'] ) : '';
        $refusal = expImportSourceFiles::refuseHost( $host, (array)expImport::setting( 'ImapSourceSettings', 'AllowedHosts', array() ) );
        if ( $refusal !== null )
            throw new expImportSourceException( 'IMAP: ' . $refusal . ' (or list it in sqliimport.ini [ImapSourceSettings] AllowedHosts[])' );
        self::fetchWith( self::transport(), $options, $target, $limit );
    }

    /** @return expImportImapTransport the extension imap where it is installed, else the client of this extension */
    public static function transport()
    {
        return expImportImapExtension::available() ? new expImportImapExtension() : new expImportImapClient();
    }

    /**
     * fetch() with a given transport (a test gives one that answers without a server).
     *
     * @param expImportImapTransport $transport
     * @param array $options
     * @param string $target
     * @param int $limit
     * @throws expImportSourceException
     */
    public static function fetchWith( expImportImapTransport $transport, array $options, $target, $limit )
    {
        $ref = isset( $options['password'] ) ? trim( (string)$options['password'] ) : '';
        $password = expImportSecrets::required( $ref, 'IMAP password' );
        $markSeen = !empty( $options['mark_seen'] );
        $withAttachments = !isset( $options['attachments'] ) || !empty( $options['attachments'] );
        $mailbox = isset( $options['mailbox'] ) && trim( (string)$options['mailbox'] ) !== '' ? trim( (string)$options['mailbox'] ) : 'INBOX';
        $criteria = expImportImapClient::criteria( isset( $options['search'] ) ? (string)$options['search'] : 'ALL' );
        $days = isset( $options['days'] ) ? (int)$options['days'] : 0;
        if ( $days > 0 )
            $criteria .= ' SINCE ' . date( 'j-M-Y', expImport::now() - $days * 86400 );
        $max = max( 1, (int)expImport::setting( 'ImapSourceSettings', 'MaxMessageMegabytes', 10 ) ) * 1048576;
        $tmp = $target . '.part';
        $out = null;
        try
        {
            $transport->open( isset( $options['host'] ) ? trim( (string)$options['host'] ) : '', isset( $options['port'] ) ? (int)$options['port'] : 993,
                              isset( $options['encryption'] ) ? (string)$options['encryption'] : 'ssl',
                              isset( $options['user'] ) ? (string)$options['user'] : '', $password, $mailbox, !$markSeen );
            $uids = $transport->search( $criteria );
            if ( $limit > 0 && count( $uids ) > $limit )
                $uids = array_slice( $uids, -$limit );
            $out = fopen( $tmp, 'wb' );
            if ( !$out )
                throw new expImportSourceException( 'IMAP: the snapshot cannot be written' );
            foreach ( $uids as $uid )
            {
                $size = $transport->size( $uid );
                $tooLarge = $size > $max;
                $message = expImportMailMessage::parse( $transport->message( $uid, $tooLarge ) );
                if ( !$withAttachments )
                    unset( $message['attachments'], $message['attachment_count'] );
                $message = array( 'uid' => (int)$uid, 'mailbox' => $mailbox ) + $message
                         + array( 'truncated' => $tooLarge );
                if ( $size > 0 )
                    $message['size'] = $size;
                fwrite( $out, (string)json_encode( $message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n" );
                if ( $markSeen )
                    $transport->markSeen( $uid );
            }
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'IMAP: the snapshot cannot be written' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::maskText( $e->getMessage(), $ref, $password ) );
        }
        finally
        {
            $transport->close();
            if ( $out )
                fclose( $out );
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /** @return string a message without the password (by its secret name, and the value itself) */
    protected static function maskText( $text, $ref, $password )
    {
        $text = expImportSecrets::mask( (string)$text, array( $ref ) );
        if ( strlen( (string)$password ) >= 3 )
            $text = str_replace( (string)$password, '[password]', $text );
        return $text;
    }
}
