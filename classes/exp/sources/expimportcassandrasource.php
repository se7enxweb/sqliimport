<?php
/**
 * File containing the expImportCassandraSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a CQL SELECT against Apache Cassandra or ScyllaDB. One record is one row: its columns, user-defined
 * types flattened to dotted names, lists and sets as JSON or a joined list, maps as JSON.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the SELECT (page size and
 * paging state) and writes the rows into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible:
 *   - checkQuery() accepts a single SELECT and refuses, outside string literals, comments and quoted names, INSERT,
 *     UPDATE, DELETE, BATCH, APPLY, BEGIN, TRUNCATE, CREATE, ALTER, DROP, GRANT, REVOKE, USE and USING, and a second
 *     statement after a semicolon; each transport refuses again whatever does not start with SELECT
 *   - the keyspace is an option (a checked name), not a USE in the query
 *   - the password is the name of a secret (expImportSecrets), masked in every message; give the import a role with
 *     SELECT on the keyspace only (GRANT SELECT ON KEYSPACE shop TO reader)
 *   - a server in a private or reserved network is refused unless it is listed in [CassandraSourceSettings]
 *     AllowedHosts[] (or [SourceSettings] AllowPrivateHosts=enabled)
 *
 * Drivers: the PHP extension cassandra of DataStax where it is installed, else the built-in client of the native
 * protocol v4 over a socket (expImportCassandraClient), chosen by [CassandraSourceSettings] Client.
 */
class expImportCassandraSource extends expImportJsonSource
{
    /** The words refused outside literals and quoted names. */
    const FORBIDDEN = array( 'INSERT', 'UPDATE', 'DELETE', 'BATCH', 'APPLY', 'BEGIN', 'TRUNCATE', 'CREATE', 'ALTER', 'DROP', 'GRANT',
                             'REVOKE', 'USE', 'USING' );

    /** The consistency levels a read may ask for. */
    const CONSISTENCY = array( 'LOCAL_ONE', 'ONE', 'LOCAL_QUORUM', 'QUORUM', 'ALL' );

    /**
     * Makes the transport instead of transport(), for the tests: function () returning an expImportCassandraTransport.
     *
     * @var callable|null
     */
    public static $transportFactory = null;

    /** @var array|null what drivers() sees of this PHP (tests): array( 'cassandra' => version or '', 'socket' => bool ) */
    public static $environment = null;

    public static function identifier()
    {
        return 'cassandra';
    }

    public static function label()
    {
        return 'Cassandra or ScyllaDB query';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'nosql';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function variants()
    {
        return array();
    }

    public static function description()
    {
        return 'The rows of a CQL SELECT against Apache Cassandra or ScyllaDB: one record is one row, user-defined types '
             . 'flattened to dotted names, lists and sets as JSON or joined, maps as JSON. Only a single SELECT is '
             . 'accepted; the password is the name of a secret in the settings. Through the PHP extension cassandra or the '
             . 'built-in client of the native protocol.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three events as the source writes it from a SELECT (one row per line)',
                   'file' => 'cassandra-events.ndjson',
                   'options' => array( 'hosts' => 'cassandra.example.org', 'keyspace' => 'shop', 'query' => 'SELECT * FROM events', 'key' => 'event_id' ) ),
            array( 'label' => 'The products of one category, user reader with the password as the secret cql_reader, over TLS',
                   'options' => array( 'hosts' => "cassandra1.example.org, cassandra2.example.org", 'port' => 9142, 'tls' => true,
                                       'user' => 'reader', 'password_secret' => 'cql_reader', 'keyspace' => 'shop',
                                       'query' => "SELECT sku, title, price, tags, attributes FROM products WHERE category = 'tea'",
                                       'consistency' => 'LOCAL_QUORUM', 'key' => 'sku' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'hosts' => array( 'type' => 'string', 'label' => 'Contact points', 'required' => true, 'max' => 1000,
                              'pattern' => '/^[A-Za-z0-9.:\[\]_-]+(\s*,\s*[A-Za-z0-9.:\[\]_-]+)*$/',
                              'help' => 'One or more hosts, comma separated, each with an optional :port (cassandra1.example.org, cassandra2.example.org:9142); the first that answers is used' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 9042, 'min' => 1, 'max' => 65535 ),
            'tls' => array( 'type' => 'bool', 'label' => 'TLS', 'default' => false, 'help' => 'Encrypt the connection (the certificate is verified)' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'Empty: no authentication' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'keyspace' => array( 'type' => 'string', 'label' => 'Keyspace', 'max' => 48, 'pattern' => '/^([A-Za-z][A-Za-z0-9_]{0,47})?$/',
                                 'help' => 'Empty: the query names its tables with the keyspace (shop.products)' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'One CQL SELECT; INSERT, UPDATE, DELETE, BATCH, TRUNCATE, CREATE, ALTER, DROP, GRANT and USE are refused' ),
            'consistency' => array( 'type' => 'enum', 'label' => 'Consistency', 'default' => 'LOCAL_ONE',
                                    'values' => array( 'LOCAL_ONE' => 'LOCAL_ONE', 'ONE' => 'ONE', 'LOCAL_QUORUM' => 'LOCAL_QUORUM', 'QUORUM' => 'QUORUM', 'ALL' => 'ALL' ) ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 1, 'max' => 10000, 'help' => 'Rows per page' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most rows', 'default' => 0, 'min' => 0, 'help' => '0: all' ),
            'collections' => array( 'type' => 'enum', 'label' => 'Lists and sets', 'default' => 'json',
                                    'values' => array( 'json' => 'JSON (["a","b"])', 'join' => 'A joined list (a, b)' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'Separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '', 'help' => 'The column that names a row. Empty: the position' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * The query with its string literals, comments and quoted names replaced by blanks.
     *
     * @param string $cql
     * @return string|null null when a literal, a quoted name or a comment is not closed
     */
    public static function strip( $cql )
    {
        $cql = (string)$cql;
        $out = '';
        $n = strlen( $cql );
        for ( $i = 0; $i < $n; )
        {
            $c = $cql[$i];
            $two = substr( $cql, $i, 2 );
            if ( $two === '--' || $two === '//' )
            {
                $end = strpos( $cql, "\n", $i );
                $i = $end === false ? $n : $end;
                $out .= ' ';
                continue;
            }
            if ( $two === '/*' )
            {
                $end = strpos( $cql, '*/', $i + 2 );
                if ( $end === false )
                    return null;
                $i = $end + 2;
                $out .= ' ';
                continue;
            }
            if ( $two === '$$' )
            {
                $end = strpos( $cql, '$$', $i + 2 );
                if ( $end === false )
                    return null;
                $i = $end + 2;
                $out .= ' 0 ';
                continue;
            }
            if ( $c === '\'' || $c === '"' )
            {
                // a quote inside is written twice ('it''s', "my ""col""")
                $j = $i + 1;
                $closed = false;
                while ( $j < $n )
                {
                    if ( $cql[$j] === $c )
                    {
                        if ( $j + 1 < $n && $cql[$j + 1] === $c )
                        {
                            $j += 2;
                            continue;
                        }
                        $closed = true;
                        $j++;
                        break;
                    }
                    $j++;
                }
                if ( !$closed )
                    return null;
                $out .= $c === '"' ? ' x ' : ' 0 ';
                $i = $j;
                continue;
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }

    /**
     * Whether a query only reads: a single SELECT, without a forbidden word outside literals and quoted names.
     *
     * @param string $cql
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $cql )
    {
        $stripped = self::strip( $cql );
        if ( $stripped === null )
            return 'a string, a quoted name or a comment is not closed';
        $stripped = rtrim( trim( $stripped ), "; \t\r\n" );
        if ( $stripped === '' )
            return 'the query is empty';
        if ( strpos( $stripped, ';' ) !== false )
            return 'only a single statement is accepted (no semicolon between statements)';
        if ( !preg_match( '/^SELECT\b/i', $stripped ) )
            return 'the query must start with SELECT';
        if ( preg_match_all( '/\b[A-Za-z_][A-Za-z0-9_]*\b/', $stripped, $m ) )
            foreach ( $m[0] as $word )
                if ( in_array( strtoupper( $word ), self::FORBIDDEN, true ) )
                    return 'the word ' . strtoupper( $word ) . ' is not allowed: the source only reads (a single SELECT)';
        return null;
    }

    /**
     * The contact points of the option: array( host, port ) each, every one checked.
     *
     * @param string $text
     * @param int $port the default port
     * @return array
     * @throws expImportSourceException
     */
    public static function hosts( $text, $port )
    {
        $trusted = (array)expImport::setting( 'CassandraSourceSettings', 'AllowedHosts', array() );
        $hosts = array();
        foreach ( explode( ',', (string)$text ) as $part )
        {
            $part = trim( $part );
            if ( $part === '' )
                continue;
            $host = $part;
            $p = (int)$port;
            if ( preg_match( '/^\[([0-9A-Fa-f:.]+)\](?::(\d+))?$/', $part, $m ) )
            {
                $host = $m[1];
                $p = isset( $m[2] ) && $m[2] !== '' ? (int)$m[2] : $p;
            }
            elseif ( substr_count( $part, ':' ) === 1 && preg_match( '/^([^:]+):(\d+)$/', $part, $m ) )
            {
                $host = $m[1];
                $p = (int)$m[2];
            }
            if ( $p < 1 || $p > 65535 )
                throw new expImportSourceException( "Cassandra: the port of $part is not a port" );
            $refusal = expImportSourceFiles::refuseHost( $host, $trusted );
            if ( $refusal !== null )
                throw new expImportSourceException( 'Cassandra: ' . $refusal . ' (or list it in sqliimport.ini [CassandraSourceSettings] AllowedHosts[])' );
            $hosts[] = array( $host, $p );
        }
        if ( !$hosts )
            throw new expImportSourceException( 'Cassandra: give at least one contact point' );
        return $hosts;
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The transport: [CassandraSourceSettings] Client (auto: the extension cassandra where it is installed, else the
     * built-in client; extension; socket).
     *
     * @return expImportCassandraTransport
     * @throws expImportSourceException
     */
    public static function transport()
    {
        if ( self::$transportFactory )
        {
            $transport = call_user_func( self::$transportFactory );
            if ( $transport instanceof expImportCassandraTransport )
                return $transport;
        }
        $client = (string)expImport::setting( 'CassandraSourceSettings', 'Client', 'auto' );
        if ( $client === 'socket' )
            return new expImportCassandraClient();
        if ( expImportCassandraExtension::available() )
            return new expImportCassandraExtension();
        if ( $client === 'extension' )
            throw new expImportSourceException( 'Cassandra: [CassandraSourceSettings] Client=extension, but this PHP (' . PHP_VERSION . ') has no extension cassandra' );
        return new expImportCassandraClient();
    }

    /**
     * Opens a checked connection of the options.
     *
     * @param array $options
     * @param string[] $used the secrets used
     * @return expImportCassandraTransport
     * @throws expImportSourceException
     */
    protected static function open( array $options, array &$used )
    {
        $hosts = self::hosts( isset( $options['hosts'] ) ? (string)$options['hosts'] : '', isset( $options['port'] ) ? (int)$options['port'] : 9042 );
        $keyspace = isset( $options['keyspace'] ) ? trim( (string)$options['keyspace'] ) : '';
        if ( $keyspace !== '' && !preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,47}$/', $keyspace ) )
            throw new expImportSourceException( 'Cassandra: the keyspace must be a name of letters, digits and _' );
        $user = isset( $options['user'] ) ? trim( (string)$options['user'] ) : '';
        $password = '';
        if ( $user !== '' )
        {
            $ref = isset( $options['password_secret'] ) ? trim( (string)$options['password_secret'] ) : '';
            $used[] = $ref;
            $password = expImportSecrets::required( $ref, 'Cassandra: the password' );
        }
        $timeout = max( 1, (int)expImport::setting( 'CassandraSourceSettings', 'Timeout', 30 ) );
        $transport = self::transport();
        try
        {
            $transport->open( $hosts, !empty( $options['tls'] ), $user, $password, $keyspace, $timeout );
        }
        catch ( expImportSourceException $e )
        {
            $transport->close();
            throw $e;
        }
        return $transport;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Runs the SELECT and writes the rows into the snapshot, one JSON object per line.
     *
     * @param array $options
     * @param string $target
     * @param int $limit the editor's sample; 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::rows( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'Cassandra: the snapshot cannot be written' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The rows of the SELECT, page by page.
     *
     * @param array $options
     * @param int $limit 0: all (else also the option limit)
     * @param string[] $used
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function rows( array $options, $limit, array &$used )
    {
        $cql = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $refusal = self::checkQuery( $cql );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Cassandra: ' . $refusal );
        $cql = rtrim( $cql, "; \t\r\n" );
        $consistency = isset( $options['consistency'] ) && in_array( $options['consistency'], self::CONSISTENCY, true ) ? (string)$options['consistency'] : 'LOCAL_ONE';
        $max = isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0;
        if ( $limit > 0 && ( $max === 0 || $limit < $max ) )
            $max = (int)$limit;
        $size = max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 500 ) );
        if ( $max > 0 )
            $size = min( $size, $max );
        $collections = isset( $options['collections'] ) && $options['collections'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $maxPages = max( 1, (int)expImport::setting( 'CassandraSourceSettings', 'MaxPages', 10000 ) );
        $transport = self::open( $options, $used );
        try
        {
            $state = null;
            $n = 0;
            for ( $page = 1; $page <= $maxPages; $page++ )
            {
                $answer = $transport->query( $cql, $consistency, $size, $state );
                foreach ( $answer['rows'] as $row )
                {
                    yield self::row( (array)$row, $collections, $separator );
                    $n++;
                    if ( $max > 0 && $n >= $max )
                        return;
                }
                $state = $answer['paging_state'];
                if ( $state === null || $state === '' )
                    return;
            }
            throw new expImportSourceException( 'Cassandra: the result has more pages than [CassandraSourceSettings] MaxPages (' . $maxPages . ')' );
        }
        finally
        {
            $transport->close();
        }
    }

    /**
     * A row as a record: user-defined types flattened to dotted names, lists, sets and tuples as JSON or joined, maps
     * as JSON, a float that is not finite as its text.
     *
     * @param array $row column => value of the transport
     * @param string $collections json or join
     * @param string $separator
     * @return array
     */
    public static function row( array $row, $collections = 'json', $separator = ', ' )
    {
        $plain = array();
        foreach ( $row as $name => $value )
            $plain[(string)$name] = self::value( $value );
        return expImportMongoDbSource::flatten( $plain, $collections, $separator );
    }

    /** @return mixed a value of the transport, maps as JSON text (at any depth inside a list, as JSON objects) */
    protected static function value( $value )
    {
        if ( $value instanceof stdClass )
            return (string)json_encode( self::plain( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
        if ( is_float( $value ) && !is_finite( $value ) )
            return self::nonFinite( $value );
        if ( is_array( $value ) )
        {
            $isList = $value === array() || array_keys( $value ) === range( 0, count( $value ) - 1 );
            if ( $isList )
            {
                foreach ( $value as $i => $item )
                    if ( $item instanceof stdClass || is_array( $item ) )
                        return (string)json_encode( self::plain( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
                return array_map( array( __CLASS__, 'plain' ), $value );
            }
            foreach ( $value as $k => $item )
                $value[$k] = self::value( $item );
        }
        return $value;
    }

    /** @return string a float that is not finite as text: NaN, Infinity, -Infinity */
    protected static function nonFinite( $value )
    {
        return is_nan( $value ) ? 'NaN' : ( $value > 0 ? 'Infinity' : '-Infinity' );
    }

    /** @return mixed a value ready for json_encode (maps stay objects) */
    protected static function plain( $value )
    {
        if ( $value instanceof stdClass )
        {
            $out = new stdClass();
            foreach ( get_object_vars( $value ) as $k => $item )
                $out->{$k} = self::plain( $item );
            return $out;
        }
        if ( is_array( $value ) )
            return array_map( array( __CLASS__, 'plain' ), $value );
        if ( is_float( $value ) && !is_finite( $value ) )
            return self::nonFinite( $value );
        return $value;
    }

    /**
     * The editor's "Test connection": connects, reads one row of the SELECT and reports its columns, or why it failed
     * (masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = '';
        $transport = null;
        try
        {
            $cql = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
            $refusal = self::checkQuery( $cql );
            if ( $refusal !== null )
                throw new expImportSourceException( 'Cassandra: ' . $refusal );
            $transport = self::open( $options, $used );
            $driver = $transport->driver();
            $consistency = isset( $options['consistency'] ) && in_array( $options['consistency'], self::CONSISTENCY, true ) ? (string)$options['consistency'] : 'LOCAL_ONE';
            $answer = $transport->query( rtrim( $cql, "; \t\r\n" ), $consistency, 1, null );
            $rows = (array)$answer['rows'];
            $columns = $rows ? array_map( 'strval', array_keys( self::row( (array)reset( $rows ) ) ) ) : array_map( 'strval', (array)$answer['columns'] );
            return array( 'ok' => true, 'columns' => $columns, 'rows' => min( 1, count( $rows ) ), 'driver' => $driver,
                          'message' => 'connected (' . $driver . ')' . ( $rows ? '; the query gives these fields' : '; the query gives no row (these are its columns)' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
        finally
        {
            if ( $transport )
                $transport->close();
        }
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /** @return array driver => the way this PHP has it ('' none), for the job queue's workers (recordWorkerPhp()) */
    public static function localDrivers()
    {
        $env = is_array( self::$environment ) ? self::$environment : array();
        $version = isset( $env['cassandra'] ) ? (string)$env['cassandra'] : expImportCassandraExtension::version();
        $socket = isset( $env['socket'] ) ? (bool)$env['socket'] : function_exists( 'stream_socket_client' );
        return array( 'extension' => $version !== '' ? 'ext-cassandra ' . $version : '',
                      'socket' => $socket ? 'stream_socket_client' : '' );
    }

    /**
     * What this PHP can connect with, and what the job queue's workers last had.
     *
     * @return array driver => array( 'label', 'available', 'via', 'needs', 'worker' => yes, no or '', 'worker_php' )
     */
    public static function drivers()
    {
        $local = self::localDrivers();
        $worker = expImportSqlSource::workerPhp();
        $seen = $worker && isset( $worker['sources'][self::identifier()] ) && is_array( $worker['sources'][self::identifier()] ) ? $worker['sources'][self::identifier()] : null;
        $rows = array(
            'extension' => array( 'label' => 'Cassandra and ScyllaDB through the PHP extension cassandra (DataStax)',
                                  'needs' => 'the PECL extension cassandra of DataStax with the DataStax C/C++ driver (no packages for current PHP versions; optional, the built-in client works without it)' ),
            'socket' => array( 'label' => 'Cassandra and ScyllaDB through the built-in client (native protocol v4 over a socket)',
                               'needs' => 'stream_socket_client (part of PHP), with openssl for TLS' ),
        );
        foreach ( $rows as $id => $row )
        {
            $rows[$id]['via'] = $local[$id];
            $rows[$id]['available'] = $local[$id] !== '';
            $rows[$id]['worker'] = $seen !== null && isset( $seen[$id] ) ? ( $seen[$id] !== '' ? 'yes' : 'no' ) : '';
            $rows[$id]['worker_php'] = $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '';
        }
        return $rows;
    }
}
