<?php
/**
 * File containing the expImportImapTransport interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The connection to an IMAP mailbox the IMAP source reads: the PHP extension imap where it is installed
 * (expImportImapExtension), else a small client of its own over a TLS socket (expImportImapClient, since PHP 8.4 has
 * no imap extension in its core). Messages are addressed by their UID.
 */
interface expImportImapTransport
{
    /**
     * Connects, logs in and opens a mailbox.
     *
     * @param string $host
     * @param int $port
     * @param string $encryption ssl (TLS from the start), tls (STARTTLS) or none
     * @param string $user
     * @param string $password
     * @param string $mailbox
     * @param bool $readOnly true: EXAMINE, nothing in the mailbox changes
     * @throws expImportSourceException
     */
    public function open( $host, $port, $encryption, $user, $password, $mailbox, $readOnly );

    /**
     * @param string $criteria IMAP search criteria (ALL, UNSEEN, SINCE 1-Jan-2026 ...)
     * @return int[] the UIDs of the matching messages, ascending
     * @throws expImportSourceException
     */
    public function search( $criteria );

    /**
     * @param int $uid
     * @return int the size of a message in bytes
     * @throws expImportSourceException
     */
    public function size( $uid );

    /**
     * The whole message (headers and body) without marking it as seen; only its headers when $headersOnly.
     *
     * @param int $uid
     * @param bool $headersOnly
     * @return string
     * @throws expImportSourceException
     */
    public function message( $uid, $headersOnly = false );

    /**
     * @param int $uid
     * @throws expImportSourceException
     */
    public function markSeen( $uid );

    /** Logs out and closes the connection. */
    public function close();
}
