<?php
/**
 * File containing the expImportClickHouseSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a SELECT against ClickHouse, read through its HTTP interface (port 8123, or 8443 with TLS) in the
 * format JSONEachRow. One record is one row; a named Tuple, a Map or a JSON column is flattened to dotted names.
 *
 * Only reading is possible:
 *   - every request carries readonly=2 (or 1): the server itself refuses writes, DDL and changes of readonly
 *   - checkQuery() accepts a single SELECT or WITH, and refuses, outside string literals, quoted names and comments,
 *     INSERT, ALTER, CREATE, DROP, TRUNCATE, OPTIMIZE, SYSTEM, KILL, SET, GRANT and the other statements, the clauses
 *     FORMAT, SETTINGS and INTO OUTFILE, and the table functions that reach out of the server (url, s3, file, remote,
 *     mysql, postgresql, hdfs, jdbc, odbc, executable ...) unless [ClickHouseSourceSettings] AllowedTableFunctions[]
 *     lists one
 *   - the user (X-ClickHouse-User) and the password (X-ClickHouse-Key) never go into the address; the password is the
 *     name of a secret (expImportSecrets)
 *
 * Paging: SELECT * FROM ( <query> ) LIMIT <page> OFFSET <n> (give the query an ORDER BY for a stable order), with
 * max_execution_time and max_result_rows of the settings (readonly=2 only: readonly=1 forbids sending settings).
 */
class expImportClickHouseSource extends expImportHttpQuerySource
{
    const NAME = 'ClickHouse';

    const SETTINGS = 'ClickHouseSourceSettings';

    const WORKER_PREFIX = 'clickhouse';

    /** The statements and clauses that write, administer or leave the server. */
    const FORBIDDEN = array(
        'INSERT', 'ALTER', 'CREATE', 'DROP', 'TRUNCATE', 'OPTIMIZE', 'SYSTEM', 'KILL', 'SET', 'GRANT', 'REVOKE', 'RENAME',
        'ATTACH', 'DETACH', 'EXCHANGE', 'DELETE', 'UPDATE', 'INTO', 'OUTFILE', 'USE', 'BACKUP', 'RESTORE', 'UNDROP', 'MOVE',
        'CHECK', 'WATCH', 'EXPLAIN',
    );

    /** The table functions that read from outside the server or run programs. */
    const TABLE_FUNCTIONS = array(
        'url', 'urlcluster', 's3', 's3cluster', 'gcs', 'oss', 'cosn', 'azureblobstorage', 'azureblobstoragecluster', 'file', 'filecluster',
        'remote', 'remotesecure', 'cluster', 'clusterallreplicas', 'mysql', 'postgresql', 'mongodb', 'redis', 'sqlite', 'hdfs',
        'hdfscluster', 'jdbc', 'odbc', 'executable', 'executablepool', 'input', 'deltalake', 'deltalakecluster', 'hudi', 'hudicluster',
        'iceberg', 'icebergs3', 'icebergazure', 'iceberghdfs', 'icebergcluster', 'icebergs3cluster', 'paimon', 'arrowflight', 'ytsaurus',
        'prometheusquery', 'timeseriesdata', 's3queue', 'azurequeue', 'fuzzjson', 'fuzzquery',
    );

    public static function identifier()
    {
        return 'clickhouse';
    }

    public static function label()
    {
        return 'ClickHouse query';
    }

    public static function description()
    {
        return 'The rows of a SELECT against ClickHouse through its HTTP interface, read page by page with readonly set, so '
             . 'the server refuses every write; statements other than SELECT and table functions that reach outside the '
             . 'server are refused before. The password is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three rows of a table "page_views" as the source writes it (one row per line)',
                   'file' => 'clickhouse-page-views.ndjson',
                   'options' => array( 'base_url' => 'https://clickhouse.example.invalid:8443', 'database' => 'analytics',
                                       'query' => 'SELECT path, day, views FROM page_views ORDER BY day, path', 'key' => 'id' ) ),
            array( 'label' => 'The most read pages of the last week, user reader with the password as the secret clickhouse_reader',
                   'options' => array( 'base_url' => 'https://clickhouse.example.invalid:8443', 'database' => 'analytics', 'user' => 'reader',
                                       'password_secret' => 'clickhouse_reader',
                                       'query' => "SELECT path, sum(views) AS views\nFROM page_views\nWHERE day >= today() - 7\nGROUP BY path\nORDER BY views DESC, path\nLIMIT 100",
                                       'key' => 'path' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'required' => true,
                                 'help' => 'The HTTP interface, e.g. https://clickhouse.example.org:8443 (a private network only with [ClickHouseSourceSettings] AllowedHosts[])' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'default' => 'default', 'max' => 255,
                                 'pattern' => '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'default' => 'default', 'max' => 255 ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password. Empty: no password' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'A single SELECT (or WITH ... SELECT), without FORMAT and SETTINGS' ),
            'readonly' => array( 'type' => 'enum', 'label' => 'Read-only level', 'default' => '2',
                                 'values' => array( '2' => '2: read-only, the source may send its time and row limits',
                                                    '1' => '1: read-only, no settings sent (the limits of the user\'s profile apply)' ) ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 10000, 'min' => 1, 'max' => 1000000,
                                  'help' => 'Rows per request (LIMIT ... OFFSET ...)' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'auto',
                               'values' => array( 'auto' => 'LIMIT and OFFSET around the query', 'none' => 'None (one request)' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'help' => 'The column that names a row' ),
        );
    }

    /**
     * Whether a query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $query )
    {
        try
        {
            // strings and quoted names both with backslash escapes and doubled quotes, heredocs $tag$...$tag$
            list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, true ), '"' => array( 'name', true, true ),
                                                                               '`' => array( 'name', true, true ) ),
                                                             'line' => array( '--' ), 'block' => true, 'dollar' => true ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( self::hasSecondStatement( $stripped ) )
            return 'only one statement is allowed';
        $words = self::words( $stripped );
        if ( !$words || !in_array( $words[0], array( 'SELECT', 'WITH' ), true ) )
            return 'only a SELECT (or WITH ... SELECT) is allowed';
        $word = self::forbiddenWord( $stripped, self::FORBIDDEN );
        if ( $word !== null )
            return "the query contains $word: only reading is allowed";
        if ( preg_match( '/(?<![\w.$])(FORMAT|SETTINGS)\b(?!\s*\()/i', $stripped, $m ) )
            return 'the query may not have ' . strtoupper( $m[1] ) . ' (the source sets the format and the settings)';
        $allowed = array_map( 'strtolower', array_map( 'trim', (array)self::sourceSetting( 'AllowedTableFunctions', array() ) ) );
        preg_match_all( '/(?<![\w.$])([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $stripped, $calls );
        foreach ( $calls[1] as $name )
        {
            $lower = strtolower( $name );
            if ( in_array( $lower, self::TABLE_FUNCTIONS, true ) && !in_array( $lower, $allowed, true ) )
                return "the table function $name reaches outside the server (an administrator may allow it in [ClickHouseSourceSettings] AllowedTableFunctions[])";
        }
        return null;
    }

    /** @return string the query of the options, after the check, without a trailing ; */
    public static function queryOf( array $options )
    {
        $query = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $refusal = self::checkQuery( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( self::NAME . ': ' . $refusal );
        return rtrim( $query, " \t\r\n;" );
    }

    /**
     * The address with database, readonly and the limits, and the headers.
     *
     * @return array the list ( string address with its parameters, array headers, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        list( $base, $trusted ) = self::baseUrl( $options );
        $database = isset( $options['database'] ) && trim( (string)$options['database'] ) !== '' ? trim( (string)$options['database'] ) : 'default';
        if ( !preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $database ) )
            throw new expImportSourceException( self::NAME . ': the database must be a name' );
        $readonly = isset( $options['readonly'] ) && (string)$options['readonly'] === '1' ? 1 : 2;
        $params = array( 'database' => $database, 'readonly' => $readonly );
        if ( $readonly === 2 )
        {
            $params['max_execution_time'] = max( 1, (int)self::sourceSetting( 'MaxExecutionTime', 60 ) );
            $rows = (int)self::sourceSetting( 'MaxResultRows', 0 );
            if ( $rows > 0 )
            {
                $params['max_result_rows'] = $rows;
                $params['result_overflow_mode'] = 'throw';
            }
        }
        $headers = array( 'X-ClickHouse-User' => isset( $options['user'] ) && trim( (string)$options['user'] ) !== '' ? trim( (string)$options['user'] ) : 'default' );
        $ref = isset( $options['password_secret'] ) ? trim( (string)$options['password_secret'] ) : '';
        if ( $ref !== '' )
        {
            $used[] = $ref;
            $headers['X-ClickHouse-Key'] = expImportSecrets::required( $ref, self::NAME . ': the password' );
        }
        return array( $base . '/?' . http_build_query( $params ), $headers, $trusted );
    }

    /**
     * One query in JSONEachRow and its rows.
     *
     * @return array[]
     * @throws expImportSourceException
     */
    protected static function select( $url, array $headers, $sql, array $trusted )
    {
        $answer = self::send( 'POST', $url, $headers + array( 'Content-Type' => 'text/plain; charset=UTF-8' ), $sql . "\nFORMAT JSONEachRow", $trusted );
        $rows = array();
        foreach ( preg_split( '/\r?\n/', (string)$answer['body'] ) as $line )
        {
            if ( trim( $line ) === '' )
                continue;
            $row = json_decode( $line, true );
            if ( !is_array( $row ) )
                throw new expImportSourceException( self::NAME . ': a line of the answer is not JSON (' . substr( $line, 0, 120 ) . ')' );
            $rows[] = self::flatten( $row );
        }
        return $rows;
    }

    public static function rows( array $options, $limit, array &$used )
    {
        list( $url, $headers, $trusted ) = self::connection( $options, $used );
        $query = self::queryOf( $options );
        $size = max( 1, min( 1000000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 10000 ) );
        if ( $limit > 0 )
            $size = min( $size, $limit );
        $page = !isset( $options['paging'] ) || $options['paging'] !== 'none';
        $n = 0;
        for ( $p = 0; $p < self::maxPages(); $p++ )
        {
            $sql = $page ? "SELECT * FROM (\n" . $query . "\n) LIMIT " . $size . ' OFFSET ' . ( $p * $size ) : $query;
            $rows = self::select( $url, $headers, $sql, $trusted );
            foreach ( $rows as $row )
            {
                yield $row;
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( !$page || count( $rows ) < $size )
                return;
        }
    }

    /** @return string the server's reason: the first line of its exception (Code: 60. DB::Exception: ...) */
    protected static function reason( $body )
    {
        $line = trim( (string)strtok( (string)$body, "\n" ) );
        return $line !== '' ? $line : parent::reason( $body );
    }

    /**
     * The editor's "Test connection": the version and the first row of the query, or why it failed (masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'ClickHouse';
        try
        {
            list( $url, $headers, $trusted ) = self::connection( $options, $used );
            $query = self::queryOf( $options );
            $version = self::select( $url, $headers, 'SELECT version() AS version', $trusted );
            if ( isset( $version[0]['version'] ) )
                $driver .= ' ' . $version[0]['version'];
            $rows = self::select( $url, $headers, "SELECT * FROM (\n" . $query . "\n) LIMIT 1", $trusted );
            $columns = $rows ? array_map( 'strval', array_keys( $rows[0] ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $rows ), 'driver' => $driver,
                          'message' => 'connected to ' . $driver . ' (read-only)' . ( $rows ? '; the query gives these columns' : '; the query finds no row' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }
}
