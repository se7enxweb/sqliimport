<?php
/**
 * File containing the expImportRedisTransport interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The connection the Redis source reads through: the PHP extension redis (expImportRedisExtension), else a small
 * RESP client of its own over a socket (expImportRedisClient), or a test double. Only the read-only commands of
 * READ_COMMANDS are sent after connecting; AUTH and SELECT only by open().
 */
interface expImportRedisTransport
{
    /** The commands command() sends; anything else is refused before it reaches the server. */
    const READ_COMMANDS = array( 'SCAN', 'TYPE', 'HGETALL', 'GET', 'LRANGE', 'SMEMBERS', 'ZRANGE', 'XRANGE', 'TTL', 'JSON.GET' );

    /**
     * Connects, authenticates (AUTH user password, or AUTH password) and selects the database.
     *
     * @param string $host
     * @param int $port
     * @param bool $tls
     * @param string $user '' for none (Redis before 6, or the default user)
     * @param string $password '' for none
     * @param int $database the database index (SELECT when it is not 0)
     * @param int $timeout seconds, for connecting and each answer
     * @throws expImportSourceException
     */
    public function open( $host, $port, $tls, $user, $password, $database, $timeout );

    /**
     * One read-only command and its answer as RESP2 gives it: a string, an int, null, or a list (nested lists for
     * SCAN and XRANGE; HGETALL and ZRANGE WITHSCORES a flat list of pairs).
     *
     * @param array $args the command and its arguments (SCAN 0 MATCH * COUNT 100)
     * @return mixed
     * @throws expImportSourceException an error of the server, or a command not in READ_COMMANDS
     */
    public function command( array $args );

    /** @return string the driver: phpredis or socket */
    public function driver();

    /** Closes the connection. */
    public function close();
}
