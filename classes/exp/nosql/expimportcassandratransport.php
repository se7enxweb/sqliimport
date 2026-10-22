<?php
/**
 * File containing the expImportCassandraTransport interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The connection the Cassandra/ScyllaDB source reads through: the PHP extension cassandra of DataStax
 * (expImportCassandraExtension) where it is installed, else a small client of the CQL native protocol v4 of its own
 * over a socket (expImportCassandraClient), or a test double. After connecting only the import's checked SELECT is
 * sent (query()); the keyspace is chosen by open().
 *
 * The values of a row are PHP values: text, int (bigint, int, smallint, tinyint, counter), float, bool, null, the
 * text of a decimal or varint, a UUID as 8-4-4-4-12 hex digits, a timestamp as ISO 8601 in UTC, a date as
 * YYYY-MM-DD, a time as HH:MM:SS.nnnnnnnnn, an inet address as text, a blob as base64; a list, set or tuple as a PHP
 * list; a map as a stdClass object (key => value); a user-defined type as an array (name => value).
 */
interface expImportCassandraTransport
{
    /**
     * Connects to the first reachable contact point, authenticates (PasswordAuthenticator) and uses the keyspace.
     *
     * @param array $hosts list of array( host, port )
     * @param bool $tls
     * @param string $user '' for none
     * @param string $password
     * @param string $keyspace '' for none (the query names its tables with their keyspace)
     * @param int $timeout seconds
     * @throws expImportSourceException
     */
    public function open( array $hosts, $tls, $user, $password, $keyspace, $timeout );

    /**
     * One page of a SELECT.
     *
     * @param string $cql a SELECT checked by expImportCassandraSource::checkQuery()
     * @param string $consistency ONE, LOCAL_ONE, QUORUM, LOCAL_QUORUM or ALL
     * @param int $pageSize
     * @param string|null $pagingState the paging state of the previous page, null for the first
     * @return array array( 'columns' => string[], 'rows' => array[] name => value, 'paging_state' => string|null )
     * @throws expImportSourceException
     */
    public function query( $cql, $consistency, $pageSize, $pagingState );

    /** @return string the driver: extension or socket */
    public function driver();

    /** Closes the connection. */
    public function close();
}
