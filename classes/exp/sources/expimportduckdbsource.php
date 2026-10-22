<?php
/**
 * File containing the expImportDuckDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a DuckDB query over files on the server: Parquet, CSV or JSON files (one or many, also a pattern such
 * as data/*.parquet), or a DuckDB database file. One record is one row, its fields the columns of the query. The files
 * are the view "data" of the query (SELECT * FROM data WHERE ...); a database file is opened read-only and the query
 * names its tables.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() runs the query page by page (LIMIT and
 * OFFSET) and writes the rows into the run's snapshot, one JSON object per line.
 *
 * Drivers, in this order ([DuckDbSourceSettings] Driver): the duckdb command line program (proc_open without a shell,
 * -json, a database file with -readonly, no ~/.duckdbrc), or PHP's FFI with libduckdb. Without either the source says
 * what the host needs.
 *
 * Only reading is possible:
 *   - every file lies inside [SourceSettings] AllowedPaths[]; an http, https or s3 address only with
 *     [DuckDbSourceSettings] AllowRemoteFiles=enabled (and its host not in a private network)
 *   - the query is a single SELECT (or WITH ... SELECT), checked like the SQL source's (DuckDB reads SQL as PostgreSQL
 *     does) and in addition refused with COPY, ATTACH, INSTALL, LOAD, SET, PRAGMA, EXPORT, the functions that read
 *     other files or run SQL (read_csv, read_parquet, glob, query, getenv ...) and a file named as a table
 *   - the session cannot install or load extensions; with [DuckDbSourceSettings] Sandbox=enabled (DuckDB 1.3 or later)
 *     it may reach only the files of the import (allowed_paths, enable_external_access=false), and its configuration
 *     is locked before the query runs
 */
class expImportDuckDbSource extends expImportJsonSource
{
    /** The words of DuckDB a query must not contain outside its strings, besides expImportSqlSource::FORBIDDEN. */
    const FORBIDDEN = array(
        'INSTALL', 'FORCE', 'SET', 'RESET', 'EXPORT', 'IMPORT', 'CHECKPOINT', 'FORCE_CHECKPOINT', 'USE', 'DETACH', 'SECRET',
        'READ_CSV', 'READ_CSV_AUTO', 'SNIFF_CSV', 'READ_PARQUET', 'PARQUET_SCAN', 'PARQUET_METADATA', 'PARQUET_SCHEMA',
        'PARQUET_FILE_METADATA', 'PARQUET_KV_METADATA', 'READ_JSON', 'READ_JSON_AUTO', 'READ_JSON_OBJECTS', 'READ_JSON_OBJECTS_AUTO',
        'READ_NDJSON', 'READ_NDJSON_AUTO', 'READ_NDJSON_OBJECTS', 'READ_TEXT', 'READ_BLOB', 'READ_XLSX', 'GLOB', 'QUERY', 'QUERY_TABLE',
        'GETENV', 'SQLITE_SCAN', 'SQLITE_ATTACH', 'POSTGRES_SCAN', 'POSTGRES_ATTACH', 'POSTGRES_QUERY', 'POSTGRES_EXECUTE', 'MYSQL_SCAN',
        'MYSQL_QUERY', 'MYSQL_EXECUTE', 'ICEBERG_SCAN', 'DELTA_SCAN', 'ST_READ', 'DUCKDB_SECRETS', 'WHICH_SECRET', 'DUCKDB_EXTENSIONS',
        'HTTP_GET', 'HTTP_POST', 'SHELL', 'SYSTEM',
    );

    /** The readers of the files, by option value: the DuckDB table function of each. */
    const READERS = array( 'parquet' => 'read_parquet', 'csv' => 'read_csv_auto', 'json' => 'read_json_auto' );

    /**
     * Runs a job instead of the duckdb program or FFI, for the tests: function ( array $job ) returning the rows (arrays
     * column => value). The job: driver (cli or ffi), database ('' in memory), readonly, setup (statements),
     * query, timeout, max_bytes. null: the driver of this host.
     *
     * @var callable|null
     */
    public static $runner = null;

    /**
     * What drivers() sees of this host, for the tests: array( 'binary' => string|null the duckdb program,
     * 'ffi' => bool, 'library' => string|null libduckdb ). null: this host.
     *
     * @var array|null
     */
    public static $environment = null;

    /** @var string[]|null the command that stands for the duckdb program (tests: PHP and a script); null: the program */
    public static $command = null;

    public static function identifier()
    {
        return 'duckdb';
    }

    public static function label()
    {
        return 'DuckDB query over Parquet, CSV or JSON files';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'data';
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
        return 'The rows of a DuckDB query over files on the server: Parquet, CSV or JSON files (one, several or a pattern '
             . 'such as exports/*.parquet) or a DuckDB database file, opened read-only. One record is one row. The files are '
             . 'the view "data" of the query; only a single SELECT is accepted and the files must lie inside '
             . '[SourceSettings] AllowedPaths[]. Needs the duckdb program or PHP FFI with libduckdb on the server.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'The CSV file duckdb-teas.csv (five teas): copy it into a directory of [SourceSettings] AllowedPaths[] and give its path',
                   'file' => 'duckdb-teas.csv',
                   'options' => array( 'paths' => 'var/import/duckdb-teas.csv', 'reader' => 'csv',
                                       'query' => "SELECT id, name, origin, kind, price FROM data WHERE kind <> 'herbal' ORDER BY id",
                                       'key' => 'id' ) ),
            array( 'label' => 'Every Parquet file of an export directory, the newest products first',
                   'options' => array( 'paths' => 'var/import/exports/*.parquet', 'reader' => 'parquet',
                                       'query' => 'SELECT sku, title, price, updated_at FROM data ORDER BY updated_at DESC, sku',
                                       'key' => 'sku' ) ),
            array( 'label' => 'A table of a DuckDB database file, opened read-only',
                   'options' => array( 'paths' => 'var/import/warehouse.duckdb', 'reader' => 'duckdb',
                                       'query' => 'SELECT id, name, city FROM customers ORDER BY id', 'key' => 'id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'paths' => array( 'type' => 'list', 'label' => 'Files', 'required' => true,
                              'help' => 'One path per line, inside [SourceSettings] AllowedPaths[]; a pattern (exports/*.parquet) reads every file it matches. A DuckDB database: one file' ),
            'reader' => array( 'type' => 'enum', 'label' => 'File type', 'default' => 'auto',
                               'values' => array( 'auto' => 'By the file name', 'parquet' => 'Parquet', 'csv' => 'CSV (delimiter and header detected)',
                                                  'json' => 'JSON or NDJSON', 'duckdb' => 'DuckDB database (read-only)' ) ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'default' => 'SELECT * FROM data',
                              'help' => 'A single SELECT (or WITH ... SELECT); the files are the view data, a database file gives its tables. An ORDER BY keeps the pages stable' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 10000, 'min' => 0, 'max' => 1000000,
                                  'help' => 'Rows per query (LIMIT and OFFSET); 0: all rows at once' ),
            'key' => array( 'type' => 'string', 'label' => 'Key column',
                            'help' => 'The column that names a row (an ID). Empty: the position in the result' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether a query only reads: the check of the SQL source in the dialect of PostgreSQL (whose parser DuckDB uses:
     * dollar quoting, E'...' strings, nested comments), then the words of FORBIDDEN, and no file named as a table
     * (FROM 'file.csv', FROM "file.csv").
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $query )
    {
        $query = (string)$query;
        $refusal = expImportSqlSource::checkQuery( $query, 'pgsql' );
        if ( $refusal !== null )
            return $refusal;
        $code = (string)expImportSqlSource::codeOf( $query, 'pgsql' );
        if ( preg_match( '/\b(' . implode( '|', self::FORBIDDEN ) . ')\b/i', $code, $m ) )
            return 'the query contains ' . strtoupper( $m[1] ) . ': only reading the files of the import is allowed';
        if ( preg_match( '/\b(FROM|JOIN)\s*\(?\s*\'\'/i', $code ) )
            return 'a file cannot be named as a table: the files of the import are the view data';
        if ( preg_match( '/\b(FROM|JOIN)\s+"[^"]*[.\/:\\\\][^"]*"/i', $query ) )
            return 'a file cannot be named as a table: the files of the import are the view data';
        return null;
    }

    // ---- the files ---------------------------------------------------------------------------------------------------

    /**
     * The files of the options, checked and patterns expanded, and their reader.
     *
     * @param array $options
     * @return array the list ( string[] files or addresses, string reader: parquet, csv, json or duckdb )
     * @throws expImportSourceException
     */
    public static function files( array $options )
    {
        $paths = isset( $options['paths'] ) ? $options['paths'] : array();
        if ( is_string( $paths ) )
            $paths = preg_split( '/\r\n|\r|\n/', $paths );
        $paths = array_values( array_filter( array_map( 'trim', (array)$paths ), 'strlen' ) );
        if ( !$paths )
            throw new expImportSourceException( 'DuckDB: no file given (option Files)' );
        $max = max( 1, (int)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'MaxFiles', 1000 ) );
        $files = array();
        foreach ( $paths as $path )
        {
            if ( strpos( $path, "\0" ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $path ) )
                throw new expImportSourceException( 'DuckDB: a path may not contain ".."' );
            if ( preg_match( '#^([a-z][a-z0-9+.-]*)://#i', $path, $m ) )
            {
                $files[] = self::remoteFile( $path, strtolower( $m[1] ) );
                continue;
            }
            if ( strpbrk( $path, '*?[' ) !== false )
            {
                $matches = glob( $path, GLOB_NOSORT );
                if ( !$matches )
                    throw new expImportSourceException( 'DuckDB: the pattern ' . basename( $path ) . ' matches no file' );
                sort( $matches );
                foreach ( $matches as $match )
                    $files[] = self::localFile( $match );
            }
            else
                $files[] = self::localFile( $path );
            if ( count( $files ) > $max )
                throw new expImportSourceException( "DuckDB: more than $max files ([DuckDbSourceSettings] MaxFiles)" );
        }
        $files = array_values( array_unique( $files ) );
        $reader = isset( $options['reader'] ) ? (string)$options['reader'] : 'auto';
        if ( $reader === 'auto' || $reader === '' )
            $reader = self::readerOf( $files[0] );
        if ( $reader !== 'duckdb' && !isset( self::READERS[$reader] ) )
            throw new expImportSourceException( "DuckDB: unknown file type '$reader'" );
        if ( $reader === 'duckdb' && ( count( $files ) !== 1 || preg_match( '#^[a-z0-9]+://#i', $files[0] ) ) )
            throw new expImportSourceException( 'DuckDB: a database is one file on the server' );
        return array( $files, $reader );
    }

    /** @return string a local file inside [SourceSettings] AllowedPaths[], its real path */
    protected static function localFile( $path )
    {
        try
        {
            return expImportSourceFiles::allowedPath( $path );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( 'DuckDB: ' . basename( (string)$path ) . ': ' . $e->getMessage() );
        }
    }

    /** @return string an http, https or s3 address, only with [DuckDbSourceSettings] AllowRemoteFiles=enabled */
    protected static function remoteFile( $url, $scheme )
    {
        if ( expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'AllowRemoteFiles', 'disabled' ) !== 'enabled' )
            throw new expImportSourceException( 'DuckDB: addresses are read only with sqliimport.ini [DuckDbSourceSettings] AllowRemoteFiles=enabled' );
        if ( !in_array( $scheme, array( 'http', 'https', 's3' ), true ) || strpbrk( $url, "'\\ \t\r\n" ) !== false )
            throw new expImportSourceException( 'DuckDB: only http, https and s3 addresses can be read' );
        if ( $scheme !== 's3' )
        {
            $refusal = expImportSourceFiles::refuseUrl( $url, (array)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'AllowedHosts', array() ) );
            if ( $refusal !== null )
                throw new expImportSourceException( 'DuckDB: ' . $refusal );
        }
        return $url;
    }

    /** @return string the reader of a file by its name: parquet, csv, json or duckdb */
    public static function readerOf( $file )
    {
        $ext = strtolower( pathinfo( (string)parse_url( (string)$file, PHP_URL_PATH ) ?: (string)$file, PATHINFO_EXTENSION ) );
        if ( in_array( $ext, array( 'gz', 'zst' ), true ) )
            $ext = strtolower( pathinfo( pathinfo( (string)$file, PATHINFO_FILENAME ), PATHINFO_EXTENSION ) );
        switch ( $ext )
        {
            case 'parquet':
            case 'pq':
                return 'parquet';
            case 'csv':
            case 'tsv':
            case 'txt':
                return 'csv';
            case 'json':
            case 'ndjson':
            case 'jsonl':
                return 'json';
            case 'duckdb':
            case 'ddb':
            case 'db':
                return 'duckdb';
        }
        throw new expImportSourceException( 'DuckDB: the file type of ' . basename( (string)$file ) . ' is not known (choose File type)' );
    }

    /** @return string a text as an SQL string literal */
    protected static function literal( $text )
    {
        return "'" . str_replace( "'", "''", (string)$text ) . "'";
    }

    /**
     * The statements that prepare a session before the query: no extensions installed or loaded on their own, the
     * sandbox ([DuckDbSourceSettings] Sandbox), the view data of the files, a locked configuration.
     *
     * @param string[] $files
     * @param string $reader
     * @return string[]
     */
    public static function setup( array $files, $reader )
    {
        $list = '[' . implode( ', ', array_map( array( __CLASS__, 'literal' ), $files ) ) . ']';
        $remote = false;
        foreach ( $files as $file )
            if ( preg_match( '#^[a-z0-9]+://#i', $file ) )
                $remote = true;
        $setup = array( 'SET autoinstall_known_extensions = false', 'SET autoload_known_extensions = false' );
        if ( $remote )
            $setup[] = 'LOAD httpfs';
        if ( expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'Sandbox', 'enabled' ) !== 'disabled' )
        {
            $setup[] = 'SET allowed_paths = ' . $list;
            $setup[] = 'SET enable_external_access = false';
        }
        if ( $reader !== 'duckdb' )
            $setup[] = 'CREATE TEMPORARY VIEW data AS SELECT * FROM ' . self::READERS[$reader] . '(' . $list . ')';
        $setup[] = 'SET lock_configuration = true';
        return $setup;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Runs the query and writes its rows into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most rows (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        try
        {
            expImportPlatformHttp::writeNdjson( self::rows( $options, (int)$limit ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'DuckDB: the snapshot cannot be written' );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The rows of the query, page by page (LIMIT and OFFSET around the query).
     *
     * @param array $options
     * @param int $limit 0: all
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function rows( array $options, $limit )
    {
        $query = trim( isset( $options['query'] ) && trim( (string)$options['query'] ) !== '' ? (string)$options['query'] : 'SELECT * FROM data' );
        $refusal = self::checkQuery( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( 'DuckDB: ' . $refusal );
        $query = rtrim( preg_replace( '/;\s*$/', '', $query ) );
        list( $files, $reader ) = self::files( $options );
        $driver = self::driver();
        $size = isset( $options['page_size'] ) ? max( 0, (int)$options['page_size'] ) : 10000;
        if ( $limit > 0 )
            $size = $size > 0 ? min( $size, $limit ) : $limit;
        $job = array(
            'driver' => $driver,
            'database' => $reader === 'duckdb' ? $files[0] : '',
            'readonly' => $reader === 'duckdb',
            'setup' => self::setup( $files, $reader ),
            'timeout' => max( 1, (int)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'QueryTimeout', 60 ) ),
            'max_bytes' => max( 1, (int)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'MaxOutputMegabytes', 100 ) ) * 1048576,
        );
        $n = 0;
        for ( $offset = 0; ; $offset += $size )
        {
            $job['query'] = $size > 0 ? "SELECT * FROM (\n" . $query . "\n) AS exp_import_page LIMIT " . $size . ' OFFSET ' . $offset : $query;
            $rows = self::run( $job );
            foreach ( $rows as $row )
            {
                yield is_array( $row ) ? $row : array( 'value' => $row );
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( $size === 0 || count( $rows ) < $size )
                return;
        }
    }

    /**
     * One job through the runner of the tests, the duckdb program or FFI.
     *
     * @param array $job
     * @return array[] the rows
     * @throws expImportSourceException
     */
    protected static function run( array $job )
    {
        if ( self::$runner )
            return (array)call_user_func( self::$runner, $job );
        if ( $job['driver'] === 'ffi' )
            return self::runFfi( $job );
        return self::runCli( $job );
    }

    /**
     * A job through the duckdb program: no shell (proc_open with the arguments as a list), no ~/.duckdbrc (-init with
     * an empty file), JSON output, -readonly for a database file, the statements on its input, a reduced environment,
     * stopped after the time limit or when its output is larger than the limit.
     *
     * @param array $job
     * @return array[]
     * @throws expImportSourceException
     */
    public static function runCli( array $job )
    {
        $binary = self::binary();
        if ( $binary === null && self::$command === null )
            throw new expImportSourceException( self::missingDriverMessage() );
        $command = self::$command !== null ? self::$command : array( (string)$binary );
        $command = array_merge( $command, array( '-json', '-bail', '-init', '/dev/null' ) );
        if ( !empty( $job['readonly'] ) )
            $command[] = '-readonly';
        if ( (string)$job['database'] !== '' )
            $command[] = (string)$job['database'];
        $script = '';
        foreach ( array_merge( (array)$job['setup'], array( (string)$job['query'] ) ) as $statement )
            $script .= $statement . ";\n";
        $env = array( 'PATH' => '/usr/local/bin:/usr/bin:/bin', 'LANG' => 'C.UTF-8', 'LC_ALL' => 'C.UTF-8' );
        if ( getenv( 'HOME' ) !== false )
            $env['HOME'] = (string)getenv( 'HOME' );
        $process = @proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
        if ( !is_resource( $process ) )
            throw new expImportSourceException( 'DuckDB: the duckdb program could not be started' );
        fwrite( $pipes[0], $script );
        fclose( $pipes[0] );
        stream_set_blocking( $pipes[1], false );
        stream_set_blocking( $pipes[2], false );
        $out = '';
        $err = '';
        $started = microtime( true );
        $failure = null;
        while ( !feof( $pipes[1] ) || !feof( $pipes[2] ) )
        {
            $read = array();
            foreach ( array( 1, 2 ) as $i )
                if ( !feof( $pipes[$i] ) )
                    $read[] = $pipes[$i];
            $write = null;
            $except = null;
            if ( @stream_select( $read, $write, $except, 0, 200000 ) === false )
                break;
            foreach ( $read as $pipe )
            {
                $chunk = (string)fread( $pipe, 65536 );
                if ( $pipe === $pipes[1] )
                    $out .= $chunk;
                elseif ( strlen( $err ) < 65536 )
                    $err .= $chunk;
            }
            if ( strlen( $out ) > (int)$job['max_bytes'] )
                $failure = 'DuckDB: the result is larger than [DuckDbSourceSettings] MaxOutputMegabytes (give a smaller page size)';
            elseif ( microtime( true ) - $started > (int)$job['timeout'] )
                $failure = 'DuckDB: the query took longer than [DuckDbSourceSettings] QueryTimeout (' . (int)$job['timeout'] . ' s)';
            if ( $failure !== null )
            {
                proc_terminate( $process, 9 );
                break;
            }
        }
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $status = proc_close( $process );
        if ( $failure !== null )
            throw new expImportSourceException( $failure );
        if ( $status !== 0 || ( trim( $out ) === '' && stripos( $err, 'error' ) !== false ) )
            throw new expImportSourceException( 'DuckDB: ' . self::errorText( $err !== '' ? $err : $out ) );
        return self::decodeOutput( $out );
    }

    /** @return string the first lines of an error of DuckDB, without the paths of the server's directories */
    protected static function errorText( $text )
    {
        $text = trim( preg_replace( '/\s+/', ' ', (string)$text ) );
        return $text === '' ? 'the duckdb program failed' : substr( $text, 0, 400 );
    }

    /**
     * The rows of the program's JSON output: one array per statement that gives rows (the last one is the query's);
     * nothing for a query without rows.
     *
     * @param string $out
     * @return array[]
     * @throws expImportSourceException
     */
    public static function decodeOutput( $out )
    {
        $out = trim( (string)$out );
        if ( $out === '' )
            return array();
        $rows = json_decode( $out, true );
        if ( is_array( $rows ) )
            return $rows;
        // several result sets: the last array of the output
        $start = strrpos( $out, "\n[" );
        $rows = $start !== false ? json_decode( substr( $out, $start + 1 ), true ) : null;
        if ( !is_array( $rows ) )
            throw new expImportSourceException( 'DuckDB: the output of the duckdb program is not JSON' );
        return $rows;
    }

    /**
     * A job through PHP's FFI and libduckdb: a database of its own (in memory, or the file with access_mode
     * READ_ONLY), the statements one by one, the values as text. The time limit is not enforced here.
     *
     * @param array $job
     * @return array[]
     * @throws expImportSourceException
     */
    public static function runFfi( array $job )
    {
        $library = self::library();
        if ( $library === null || !self::hasFfi() )
            throw new expImportSourceException( self::missingDriverMessage() );
        $ffiClass = 'FFI';
        try
        {
            $ffi = $ffiClass::cdef( self::FFI_HEADER, $library );
        }
        catch ( Throwable $e )
        {
            throw new expImportSourceException( 'DuckDB: libduckdb could not be loaded through FFI: ' . substr( $e->getMessage(), 0, 200 ) );
        }
        $db = $ffi->new( 'duckdb_database' );
        $connection = $ffi->new( 'duckdb_connection' );
        $config = $ffi->new( 'duckdb_config' );
        $error = $ffi->new( 'char *' );
        $opened = false;
        $connected = false;
        try
        {
            if ( $ffi->duckdb_create_config( $ffiClass::addr( $config ) ) !== 0 )
                throw new expImportSourceException( 'DuckDB: no configuration' );
            if ( !empty( $job['readonly'] ) )
                $ffi->duckdb_set_config( $config, 'access_mode', 'READ_ONLY' );
            $ffi->duckdb_set_config( $config, 'autoinstall_known_extensions', 'false' );
            $ffi->duckdb_set_config( $config, 'autoload_known_extensions', 'false' );
            $path = (string)$job['database'] !== '' ? (string)$job['database'] : null;
            if ( $ffi->duckdb_open_ext( $path, $ffiClass::addr( $db ), $config, $ffiClass::addr( $error ) ) !== 0 )
            {
                $message = !$ffiClass::isNull( $error ) ? $ffiClass::string( $error ) : 'the database could not be opened';
                throw new expImportSourceException( 'DuckDB: ' . self::errorText( $message ) );
            }
            $opened = true;
            if ( $ffi->duckdb_connect( $db, $ffiClass::addr( $connection ) ) !== 0 )
                throw new expImportSourceException( 'DuckDB: no connection' );
            $connected = true;
            $rows = array();
            $statements = array_merge( (array)$job['setup'], array( (string)$job['query'] ) );
            foreach ( $statements as $i => $sql )
            {
                $result = $ffi->new( 'duckdb_result' );
                $state = $ffi->duckdb_query( $connection, $sql, $ffiClass::addr( $result ) );
                try
                {
                    if ( $state !== 0 )
                    {
                        $message = $ffi->duckdb_result_error( $ffiClass::addr( $result ) );
                        throw new expImportSourceException( 'DuckDB: ' . self::errorText( self::cString( $message, 'the statement failed' ) ) );
                    }
                    if ( $i !== count( $statements ) - 1 )
                        continue;
                    $columns = array();
                    for ( $c = 0, $count = (int)$ffi->duckdb_column_count( $ffiClass::addr( $result ) ); $c < $count; $c++ )
                        $columns[] = self::cString( $ffi->duckdb_column_name( $ffiClass::addr( $result ), $c ), 'column' . $c );
                    for ( $r = 0, $total = (int)$ffi->duckdb_row_count( $ffiClass::addr( $result ) ); $r < $total; $r++ )
                    {
                        $row = array();
                        foreach ( $columns as $c => $name )
                        {
                            if ( $ffi->duckdb_value_is_null( $ffiClass::addr( $result ), $c, $r ) )
                            {
                                $row[$name] = null;
                                continue;
                            }
                            $text = $ffi->duckdb_value_varchar( $ffiClass::addr( $result ), $c, $r );
                            $row[$name] = self::cString( $text, '' );
                            if ( !is_string( $text ) && $text !== null )
                                $ffi->duckdb_free( $text );
                        }
                        $rows[] = $row;
                    }
                }
                finally
                {
                    $ffi->duckdb_destroy_result( $ffiClass::addr( $result ) );
                }
            }
            return $rows;
        }
        finally
        {
            if ( $connected )
                $ffi->duckdb_disconnect( $ffiClass::addr( $connection ) );
            if ( $opened )
                $ffi->duckdb_close( $ffiClass::addr( $db ) );
            $ffi->duckdb_destroy_config( $ffiClass::addr( $config ) );
        }
    }

    /** @return string a C string FFI gave back (a PHP string for const char *, else a pointer); $default for NULL */
    protected static function cString( $value, $default )
    {
        if ( is_string( $value ) )
            return $value;
        if ( $value === null || call_user_func( array( 'FFI', 'isNull' ), $value ) )
            return (string)$default;
        return (string)call_user_func( array( 'FFI', 'string' ), $value );
    }

    /** The part of DuckDB's C API (duckdb.h, 1.0 and later) runFfi() uses. */
    const FFI_HEADER = '
        typedef uint64_t idx_t;
        typedef int32_t duckdb_state;
        typedef struct _duckdb_database { void *internal_ptr; } *duckdb_database;
        typedef struct _duckdb_connection { void *internal_ptr; } *duckdb_connection;
        typedef struct _duckdb_config { void *internal_ptr; } *duckdb_config;
        typedef struct { idx_t deprecated_column_count; idx_t deprecated_row_count; idx_t deprecated_rows_changed;
                         void *deprecated_columns; char *deprecated_error_message; void *internal_data; } duckdb_result;
        duckdb_state duckdb_create_config(duckdb_config *out_config);
        duckdb_state duckdb_set_config(duckdb_config config, const char *name, const char *option);
        void duckdb_destroy_config(duckdb_config *config);
        duckdb_state duckdb_open_ext(const char *path, duckdb_database *out_database, duckdb_config config, char **out_error);
        void duckdb_close(duckdb_database *database);
        duckdb_state duckdb_connect(duckdb_database database, duckdb_connection *out_connection);
        void duckdb_disconnect(duckdb_connection *connection);
        duckdb_state duckdb_query(duckdb_connection connection, const char *query, duckdb_result *out_result);
        void duckdb_destroy_result(duckdb_result *result);
        const char *duckdb_column_name(duckdb_result *result, idx_t col);
        idx_t duckdb_column_count(duckdb_result *result);
        idx_t duckdb_row_count(duckdb_result *result);
        const char *duckdb_result_error(duckdb_result *result);
        bool duckdb_value_is_null(duckdb_result *result, idx_t col, idx_t row);
        char *duckdb_value_varchar(duckdb_result *result, idx_t col, idx_t row);
        void duckdb_free(void *ptr);
    ';

    /**
     * The editor's "Test connection": checks the files and the query, runs it for its first row and reports the
     * columns, or why it failed. Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $driver = '';
        try
        {
            $driver = self::driver();
            $via = $driver === 'ffi' ? 'libduckdb through FFI' : 'the duckdb program';
            $rows = array();
            foreach ( self::rows( array( 'page_size' => 1 ) + $options, 1 ) as $row )
                $rows[] = $row;
            return array( 'ok' => true, 'columns' => $rows ? array_map( 'strval', array_keys( $rows[0] ) ) : array(), 'rows' => count( $rows ),
                          'driver' => $via, 'message' => 'read with ' . $via . ( $rows ? '; the query gives these columns' : '; the query gives no row' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver, 'message' => $e->getMessage() );
        }
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /**
     * The driver of this host: [DuckDbSourceSettings] Driver (auto, cli, ffi); auto: the duckdb program when there is
     * one, else FFI with libduckdb.
     *
     * @return string cli or ffi
     * @throws expImportSourceException when neither is there
     */
    public static function driver()
    {
        $setting = (string)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'Driver', 'auto' );
        $cli = self::binary() !== null || self::$command !== null;
        $ffi = self::hasFfi() && self::library() !== null;
        if ( ( $setting === 'cli' || $setting === 'auto' ) && $cli )
            return 'cli';
        if ( ( $setting === 'ffi' || $setting === 'auto' ) && $ffi )
            return 'ffi';
        throw new expImportSourceException( self::missingDriverMessage() );
    }

    /** @return string|null the duckdb program: [DuckDbSourceSettings] Binary, a path or a name looked up in the PATH */
    public static function binary()
    {
        if ( is_array( self::$environment ) && array_key_exists( 'binary', self::$environment ) )
            return self::$environment['binary'] === null ? null : (string)self::$environment['binary'];
        $setting = trim( (string)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'Binary', 'duckdb' ) );
        if ( $setting === '' )
            return null;
        if ( strpos( $setting, '/' ) !== false )
            return @is_file( $setting ) && @is_executable( $setting ) ? $setting : null;
        $dirs = array_merge( explode( PATH_SEPARATOR, (string)getenv( 'PATH' ) ), array( '/usr/local/bin', '/usr/bin', '/opt/duckdb/bin', '/opt/duckdb' ) );
        foreach ( array_unique( array_filter( $dirs ) ) as $dir )
        {
            $file = rtrim( $dir, '/' ) . '/' . $setting;
            if ( @is_file( $file ) && @is_executable( $file ) )
                return $file;
        }
        return null;
    }

    /** @return bool whether this PHP may use FFI (the extension, and ffi.enable on, or preload in the CLI) */
    public static function hasFfi()
    {
        if ( is_array( self::$environment ) && isset( self::$environment['ffi'] ) )
            return (bool)self::$environment['ffi'];
        if ( !extension_loaded( 'ffi' ) || !class_exists( 'FFI' ) )
            return false;
        $enable = strtolower( (string)ini_get( 'ffi.enable' ) );
        return in_array( $enable, array( '1', 'on', 'true', 'yes' ), true ) || ( $enable === 'preload' && PHP_SAPI === 'cli' );
    }

    /** @return string|null libduckdb: [DuckDbSourceSettings] Library, else the usual places */
    public static function library()
    {
        if ( is_array( self::$environment ) && array_key_exists( 'library', self::$environment ) )
            return self::$environment['library'] === null ? null : (string)self::$environment['library'];
        $setting = trim( (string)expImportPlatformHttp::setting( 'DuckDbSourceSettings', 'Library', '' ) );
        $candidates = $setting !== '' ? array( $setting )
                    : array( '/usr/local/lib/libduckdb.so', '/usr/lib64/libduckdb.so', '/usr/lib/libduckdb.so', '/usr/lib/x86_64-linux-gnu/libduckdb.so',
                             '/usr/lib/aarch64-linux-gnu/libduckdb.so', '/opt/duckdb/libduckdb.so' );
        foreach ( $candidates as $file )
            if ( @is_file( $file ) && @is_readable( $file ) )
                return $file;
        return null;
    }

    /**
     * Which drivers this host has (the editor's and the catalogue's driver list), with what the host needs for a
     * missing one and what the job queue's workers last had.
     *
     * @return array id => array( 'label', 'available' => bool, 'via' => string, 'needs' => string, 'worker', 'worker_php' )
     */
    public static function drivers()
    {
        $binary = self::binary();
        $library = self::library();
        $list = array(
            'cli' => array( 'label' => 'The duckdb program', 'via' => $binary !== null ? $binary : '',
                            'needs' => 'the duckdb command line program, a single file from duckdb.org (install it as /usr/local/bin/duckdb, '
                                     . 'or name it in [DuckDbSourceSettings] Binary)' ),
            'ffi' => array( 'label' => 'libduckdb through PHP FFI', 'via' => $library !== null && self::hasFfi() ? 'FFI, ' . $library : '',
                            'needs' => 'the PHP extension FFI with ffi.enable=On (RHEL: php-ffi; Debian: part of the php package) and libduckdb.so '
                                     . 'from duckdb.org ([DuckDbSourceSettings] Library)' ),
        );
        return expImportSearchSource::withWorker( $list, 'duckdb' );
    }

    /** @return string why DuckDB cannot be read in this PHP, and what the host needs */
    public static function missingDriverMessage()
    {
        $drivers = self::drivers();
        $text = 'DuckDB: this server (PHP ' . PHP_VERSION . ') has neither the duckdb program nor FFI with libduckdb. ';
        if ( $drivers['cli']['worker'] === 'yes' || $drivers['ffi']['worker'] === 'yes' )
            return $text . 'Runs still work: the job queue\'s workers (' . $drivers['cli']['worker_php'] . ') have one.';
        return $text . 'The host needs ' . $drivers['cli']['needs'] . '; or ' . $drivers['ffi']['needs'] . '.';
    }
}
