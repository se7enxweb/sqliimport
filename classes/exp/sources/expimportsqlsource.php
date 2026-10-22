<?php
/**
 * File containing the expImportSqlSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of an SQL query against a MySQL or MariaDB, PostgreSQL or SQLite database, or against this site's own
 * database when the settings allow it. One record is one row, its fields the columns of the query.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() runs the query and writes the rows into
 * the run's snapshot, one JSON object per line, which the chunks of the run then read like an NDJSON file.
 *
 * Only reading is possible:
 *   - the query must be a single SELECT (or WITH ... SELECT); checkQuery() refuses everything else, with the string
 *     literals and comments of the driver's dialect taken out first, so a keyword in a text does not count and a
 *     statement hidden behind a comment or a quote is found
 *   - the connection itself is read-only where the driver offers it (MySQL and MariaDB: SET SESSION TRANSACTION READ
 *     ONLY; PostgreSQL: default_transaction_read_only; SQLite: opened read-only, PRAGMA query_only), multiple
 *     statements and emulated prepares are off, and a statement time limit is set where the server has one
 *   - the password is the name of a secret (expImportSecrets), never stored in the import, and masked in every message
 *   - a database server in a private network is refused like an address (sqliimport.ini [SourceSettings]
 *     AllowPrivateHosts), unless an administrator lists it in [SqlSourceSettings] AllowedHosts[]; an SQLite file must
 *     lie inside [SourceSettings] AllowedPaths[]; this site's database only with [SqlSourceSettings] SiteDatabase=enabled
 */
class expImportSqlSource extends expImportJsonSource
{
    /** The words a query must not contain outside its string literals: statements, file access, side effects. */
    const FORBIDDEN = array(
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'TRUNCATE', 'MERGE', 'UPSERT', 'CALL',
        'EXEC', 'EXECUTE', 'INTO', 'OUTFILE', 'DUMPFILE', 'LOAD', 'LOAD_FILE', 'LOAD_EXTENSION', 'COPY', 'ATTACH',
        'DETACH', 'PRAGMA', 'LOCK', 'UNLOCK', 'HANDLER', 'RENAME', 'VACUUM', 'REINDEX', 'PREPARE', 'DEALLOCATE',
        'SHUTDOWN', 'KILL', 'SLEEP', 'PG_SLEEP', 'BENCHMARK', 'DBLINK', 'DBLINK_EXEC', 'LO_IMPORT', 'LO_EXPORT', 'LO_GET',
        'LO_PUT', 'LO_UNLINK', 'PG_READ_FILE', 'PG_READ_BINARY_FILE', 'PG_LS_DIR', 'PG_STAT_FILE', 'PG_TERMINATE_BACKEND',
        'PG_CANCEL_BACKEND', 'PG_RELOAD_CONF', 'PG_NOTIFY', 'SET_CONFIG', 'NEXTVAL', 'SETVAL', 'READFILE', 'WRITEFILE',
        'FTS3_TOKENIZER', 'SYS_EXEC', 'SYS_EVAL', 'XP_CMDSHELL',
    );

    public static function identifier()
    {
        return 'sql';
    }

    public static function label()
    {
        return 'SQL database or query';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'platform';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function description()
    {
        return 'The rows of an SQL query against a MySQL or MariaDB, PostgreSQL or SQLite database (an intranet database, the '
             . 'database of another application): one record is one row, its fields the columns of the query. Only a single '
             . 'SELECT is accepted and the connection is read-only; the password is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'The SQLite database sql-teas.sqlite (five teas): copy it into a directory of [SourceSettings] AllowedPaths[] and give its path',
                   'file' => 'sql-teas.sqlite',
                   'options' => array( 'driver' => 'sqlite', 'database' => 'var/import/sql-teas.sqlite',
                                       'query' => "SELECT id, name, origin, kind, price, description FROM teas WHERE kind <> 'herbal' ORDER BY id",
                                       'key' => 'id' ) ),
            array( 'label' => 'A MySQL database of the intranet: host db.example.org, user reader, the password as the secret crm_db',
                   'options' => array( 'driver' => 'mysql', 'host' => 'db.example.org', 'port' => 3306, 'database' => 'crm', 'user' => 'reader',
                                       'password' => 'crm_db', 'query' => 'SELECT id, title, body, updated_at FROM news WHERE published = 1',
                                       'key' => 'id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'driver' => array( 'type' => 'enum', 'label' => 'Database', 'default' => 'mysql',
                               'values' => array( 'mysql' => 'MySQL or MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite (a file on the server)',
                                                  'site' => "This site's database (read-only)" ) ),
            'host' => array( 'type' => 'string', 'label' => 'Host', 'max' => 255,
                             'help' => 'MySQL and PostgreSQL: the database server (a private network only with [SqlSourceSettings] AllowedHosts[])' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 0, 'min' => 0, 'max' => 65535,
                             'help' => '0: the default port (3306, 5432)' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'max' => 1024,
                                 'help' => 'The database name; SQLite: the file, inside one of [SourceSettings] AllowedPaths[]' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255 ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'A single SELECT (or WITH ... SELECT); each row is a record, its columns the fields' ),
            'key' => array( 'type' => 'string', 'label' => 'Key column',
                            'help' => 'The column that names a row (an ID). Empty: the position in the result.' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether a query is a single SELECT: the string literals, quoted identifiers and comments of the driver's dialect
     * are taken out, then it must start with SELECT or WITH (after opening brackets), hold no second statement and no
     * word of FORBIDDEN (REPLACE only as the function REPLACE( ... )).
     *
     * @param string $query
     * @param string $driver mysql, pgsql or sqlite (site: the dialect of this site's database)
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $query, $driver = 'mysql' )
    {
        $code = self::codeOf( (string)$query, $driver );
        if ( $code === null )
            return 'the query has a string, a quoted name or a comment that is not closed, or an executable comment';
        $code = trim( $code );
        $code = rtrim( preg_replace( '/;\s*$/', '', $code ) );
        if ( $code === '' )
            return 'the query is empty';
        if ( strpos( $code, ';' ) !== false )
            return 'only one statement is allowed (a ";" is followed by more)';
        if ( !preg_match( '/^[\s(]*(SELECT|WITH)\b/i', $code ) )
            return 'only a SELECT (or WITH ... SELECT) query is allowed';
        if ( strpos( $code, ':=' ) !== false || preg_match( '/@@?\s*[A-Za-z_]/', $code ) )
            return 'variables (@name, :=) are not allowed in the query';
        if ( preg_match( '/\bREPLACE\b(?!\s*\()/i', $code ) )
            return 'the query contains REPLACE (only the function REPLACE( ... ) is allowed)';
        if ( preg_match( '/\b(' . implode( '|', self::FORBIDDEN ) . ')\b/i', $code, $m ) )
            return 'the query contains ' . strtoupper( $m[1] ) . ', only reading is allowed';
        return null;
    }

    /**
     * The code of a query without its string literals, quoted identifiers and comments (each replaced by a space or an
     * empty literal), in the dialect of a driver; null when one is not closed or a comment would be executed.
     *
     * @param string $query
     * @param string $driver
     * @return string|null
     */
    public static function codeOf( $query, $driver )
    {
        if ( strpos( $query, "\0" ) !== false )
            return null;
        $mysql = $driver === 'mysql';
        $pgsql = $driver === 'pgsql';
        $out = '';
        $n = strlen( $query );
        $i = 0;
        while ( $i < $n )
        {
            $c = $query[$i];
            $next = $i + 1 < $n ? $query[$i + 1] : '';
            // comments: -- (and # in MySQL) to the end of the line, /* ... */ (nested in PostgreSQL)
            if ( ( $c === '-' && $next === '-' ) || ( $mysql && $c === '#' ) )
            {
                $end = strpos( $query, "\n", $i );
                $i = $end === false ? $n : $end + 1;
                $out .= ' ';
                continue;
            }
            if ( $c === '/' && $next === '*' )
            {
                // MySQL runs the text of /*! ... */ and reads hints of /*+ ... */: neither is a comment here
                if ( $mysql && $i + 2 < $n && ( $query[$i + 2] === '!' || $query[$i + 2] === '+' ) )
                    return null;
                $depth = 1;
                $i += 2;
                while ( $i < $n && $depth > 0 )
                {
                    if ( $pgsql && $query[$i] === '/' && $i + 1 < $n && $query[$i + 1] === '*' )
                    {
                        $depth++;
                        $i += 2;
                    }
                    elseif ( $query[$i] === '*' && $i + 1 < $n && $query[$i + 1] === '/' )
                    {
                        $depth--;
                        $i += 2;
                    }
                    else
                        $i++;
                }
                if ( $depth > 0 )
                    return null;
                $out .= ' ';
                continue;
            }
            // PostgreSQL dollar quoting: $$ ... $$ or $tag$ ... $tag$
            if ( $pgsql && $c === '$' && preg_match( '/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $query, $m, 0, $i ) )
            {
                $end = strpos( $query, $m[0], $i + strlen( $m[0] ) );
                if ( $end === false )
                    return null;
                $i = $end + strlen( $m[0] );
                $out .= "''";
                continue;
            }
            // string literals and quoted identifiers
            if ( $c === "'" || $c === '"' || $c === '`' || ( $driver === 'sqlite' && $c === '[' ) )
            {
                $close = $c === '[' ? ']' : $c;
                // backslash escapes: MySQL strings, and PostgreSQL E'...' strings
                $backslash = ( $mysql && $c !== '`' ) || ( $pgsql && $c === "'" && $i > 0 && strtoupper( $query[$i - 1] ) === 'E'
                                                           && ( $i < 2 || !preg_match( '/[A-Za-z0-9_]/', $query[$i - 2] ) ) );
                $j = $i + 1;
                $closed = false;
                while ( $j < $n )
                {
                    if ( $backslash && $query[$j] === '\\' )
                    {
                        $j += 2;
                        continue;
                    }
                    if ( $query[$j] === $close )
                    {
                        // a doubled quote is the quote itself
                        if ( $close !== ']' && $j + 1 < $n && $query[$j + 1] === $close )
                        {
                            $j += 2;
                            continue;
                        }
                        $closed = true;
                        break;
                    }
                    $j++;
                }
                if ( !$closed )
                    return null;
                $isName = $c === '`' || $c === '[' || ( $c === '"' && !$mysql );
                if ( $isName && preg_match( '/\G\s*\(/', $query, $m, 0, $j + 1 ) )
                    // a quoted name that is called ("pg_read_file"( ... )) is checked like a function name
                    $out .= ' ' . preg_replace( '/[^A-Za-z0-9_]+/', ' ', substr( $query, $i + 1, $j - $i - 1 ) ) . ' ';
                else
                    $out .= $c === "'" ? "''" : '""';
                $i = $j + 1;
                continue;
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Runs the query and writes its rows into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most rows to write (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $driver = isset( $options['driver'] ) ? (string)$options['driver'] : 'mysql';
        $query = isset( $options['query'] ) ? (string)$options['query'] : '';
        list( $dsn, $user, $password, $dialect, $refs ) = self::connection( $options );
        $refusal = self::checkQuery( $query, $dialect );
        if ( $refusal !== null )
            throw new expImportSourceException( 'SQL: ' . $refusal );
        $seconds = max( 1, (int)expImport::setting( 'SqlSourceSettings', 'QueryTimeout', 60 ) );
        $started = microtime( true );
        $tmp = $target . '.part';
        $out = null;
        try
        {
            $pdo = self::open( $dsn, $user, $password, $dialect, $seconds );
            $statement = $pdo->prepare( $query );
            $statement->execute();
            $out = fopen( $tmp, 'wb' );
            if ( !$out )
                throw new expImportSourceException( 'SQL: the snapshot cannot be written' );
            $rows = 0;
            while ( ( $row = $statement->fetch( PDO::FETCH_ASSOC ) ) !== false )
            {
                fwrite( $out, self::jsonLine( $row ) . "\n" );
                $rows++;
                if ( $limit > 0 && $rows >= $limit )
                    break;
                if ( ( $rows & 255 ) === 0 && microtime( true ) - $started > $seconds * 10 )
                    throw new expImportSourceException( "SQL: reading the rows took longer than ten times [SqlSourceSettings] QueryTimeout ($seconds s)" );
            }
            $statement->closeCursor();
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'SQL: the snapshot cannot be written' );
        }
        catch ( PDOException $e )
        {
            throw new expImportSourceException( 'SQL (' . $driver . '): ' . self::maskText( $e->getMessage(), $refs, $password ) );
        }
        finally
        {
            if ( $out )
                fclose( $out );
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * What to connect to: the DSN, user, password, the dialect of the query check and the secrets used, after the
     * checks of host, file and settings.
     *
     * @param array $options
     * @return array the list ( string dsn, string user, string password, string dialect, string[] secret names )
     * @throws expImportSourceException
     */
    public static function connection( array $options )
    {
        $driver = isset( $options['driver'] ) ? (string)$options['driver'] : 'mysql';
        $host = isset( $options['host'] ) ? trim( (string)$options['host'] ) : '';
        $port = isset( $options['port'] ) ? (int)$options['port'] : 0;
        $database = isset( $options['database'] ) ? trim( (string)$options['database'] ) : '';
        $user = isset( $options['user'] ) ? (string)$options['user'] : '';
        $ref = isset( $options['password'] ) ? trim( (string)$options['password'] ) : '';
        $refs = $ref !== '' ? array( $ref ) : array();
        $password = $ref !== '' ? expImportSecrets::required( $ref, 'SQL password' ) : '';
        switch ( $driver )
        {
            case 'sqlite':
                if ( $database === '' )
                    throw new expImportSourceException( 'SQL: the SQLite file is missing (option Database)' );
                try
                {
                    $file = expImportSourceFiles::allowedPath( $database );
                }
                catch ( expImportSourceException $e )
                {
                    throw new expImportSourceException( 'SQL: ' . $e->getMessage() );
                }
                return array( 'sqlite:' . $file, '', '', 'sqlite', array() );
            case 'mysql':
            case 'pgsql':
                if ( $host === '' || $database === '' )
                    throw new expImportSourceException( 'SQL: the host and the database are required' );
                $refusal = expImportSourceFiles::refuseHost( $host, (array)expImport::setting( 'SqlSourceSettings', 'AllowedHosts', array() ) );
                if ( $refusal !== null )
                    throw new expImportSourceException( 'SQL: ' . $refusal . ' (or list it in sqliimport.ini [SqlSourceSettings] AllowedHosts[])' );
                if ( !preg_match( '/^[A-Za-z0-9_.$-]+$/', $database ) )
                    throw new expImportSourceException( 'SQL: the database name has characters that are not allowed' );
                $h = strpos( $host, ':' ) !== false && strpos( $host, '[' ) !== 0 ? '[' . $host . ']' : $host;
                $h = $driver === 'pgsql' ? trim( $h, '[]' ) : $h;
                $dsn = $driver === 'mysql'
                     ? 'mysql:host=' . $h . ';port=' . ( $port ?: 3306 ) . ';dbname=' . $database . ';charset=utf8mb4'
                     : 'pgsql:host=' . $h . ';port=' . ( $port ?: 5432 ) . ';dbname=' . $database;
                return array( $dsn, $user, $password, $driver, $refs );
            case 'site':
                return self::siteConnection();
        }
        throw new expImportSourceException( "SQL: unknown database driver '$driver'" );
    }

    /**
     * This site's own database, read-only through a connection of its own (never the site's connection, so nothing
     * a query does can reach it), and only with [SqlSourceSettings] SiteDatabase=enabled.
     *
     * @return array as connection()
     * @throws expImportSourceException
     */
    protected static function siteConnection()
    {
        if ( expImport::setting( 'SqlSourceSettings', 'SiteDatabase', 'disabled' ) !== 'enabled' )
            throw new expImportSourceException( "SQL: this site's database can be read only with sqliimport.ini [SqlSourceSettings] SiteDatabase=enabled" );
        $ini = eZINI::instance( 'site.ini' );
        $implementation = (string)$ini->variable( 'DatabaseSettings', 'DatabaseImplementation' );
        $database = (string)$ini->variable( 'DatabaseSettings', 'Database' );
        $server = (string)$ini->variable( 'DatabaseSettings', 'Server' );
        $port = (int)$ini->variable( 'DatabaseSettings', 'Port' );
        $user = (string)$ini->variable( 'DatabaseSettings', 'User' );
        $password = (string)$ini->variable( 'DatabaseSettings', 'Password' );
        if ( strpos( $implementation, 'sqlite' ) !== false )
        {
            $file = class_exists( 'eZSQLite3DB' ) ? eZSQLite3DB::filePath( $database ) : $database;
            return array( 'sqlite:' . $file, '', '', 'sqlite', array() );
        }
        if ( strpos( $implementation, 'postgres' ) !== false )
            return array( 'pgsql:host=' . $server . ';port=' . ( $port ?: 5432 ) . ';dbname=' . $database, $user, $password, 'pgsql', array() );
        if ( strpos( $implementation, 'mysql' ) !== false )
        {
            $socket = (string)$ini->variable( 'DatabaseSettings', 'Socket' );
            $where = $socket !== '' && $socket !== 'disabled' ? 'unix_socket=' . $socket : 'host=' . $server . ';port=' . ( $port ?: 3306 );
            return array( 'mysql:' . $where . ';dbname=' . $database . ';charset=utf8mb4', $user, $password, 'mysql', array() );
        }
        throw new expImportSourceException( "SQL: this site's database ($implementation) cannot be read by the SQL source" );
    }

    /**
     * A read-only connection: errors as exceptions, no emulated prepares, no multiple statements, a time limit.
     *
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param string $dialect mysql, pgsql or sqlite
     * @param int $seconds the statement time limit
     * @return PDO
     * @throws PDOException|expImportSourceException
     */
    public static function open( $dsn, $user, $password, $dialect, $seconds )
    {
        if ( !class_exists( 'PDO' ) || !in_array( $dialect, PDO::getAvailableDrivers(), true ) )
            throw new expImportSourceException( "SQL: the PHP extension pdo_$dialect is not installed" );
        $attributes = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
                             PDO::ATTR_TIMEOUT => min( 30, $seconds ), PDO::ATTR_STRINGIFY_FETCHES => false );
        if ( $dialect === 'mysql' )
        {
            foreach ( array( 'MULTI_STATEMENTS', 'USE_BUFFERED_QUERY', 'LOCAL_INFILE' ) as $name )
            {
                $attribute = self::driverConstant( 'Mysql', $name, 'MYSQL_ATTR_' . $name );
                if ( $attribute === null )
                    throw new expImportSourceException( "SQL: the MySQL driver of this PHP has no option $name" );
                $attributes[$attribute] = false;
            }
        }
        if ( $dialect === 'sqlite' )
        {
            $flags = self::driverConstant( 'Sqlite', 'OPEN_FLAGS', 'SQLITE_ATTR_OPEN_FLAGS' );
            if ( $flags === null )
                throw new expImportSourceException( 'SQL: this PHP cannot open an SQLite file read-only (PHP 7.3 or later is needed)' );
            $attributes[$flags] = self::driverConstant( 'Sqlite', 'OPEN_READONLY', 'SQLITE_OPEN_READONLY', false );
        }
        $pdo = new PDO( $dsn, $user !== '' ? $user : null, $password !== '' ? $password : null, $attributes );
        switch ( $dialect )
        {
            case 'mysql':
                $pdo->exec( 'SET SESSION TRANSACTION READ ONLY' );
                // MySQL 5.7.8 and later: milliseconds; MariaDB 10.1 and later: seconds. One of them is unknown.
                foreach ( array( 'SET SESSION MAX_EXECUTION_TIME=' . ( $seconds * 1000 ), 'SET SESSION max_statement_time=' . $seconds ) as $sql )
                {
                    try
                    {
                        $pdo->exec( $sql );
                    }
                    catch ( PDOException $e )
                    {
                    }
                }
                break;
            case 'pgsql':
                $pdo->exec( 'SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY' );
                $pdo->exec( 'SET default_transaction_read_only = on' );
                $pdo->exec( 'SET statement_timeout = ' . ( $seconds * 1000 ) );
                break;
            case 'sqlite':
                $pdo->exec( 'PRAGMA query_only = ON' );
                break;
        }
        return $pdo;
    }

    /**
     * A constant of a PDO driver: Pdo\Mysql::ATTR_... (PHP 8.4 and later; the PDO::MYSQL_ATTR_... names are deprecated
     * in 8.5), else the PDO:: one.
     *
     * @param string $class Mysql or Sqlite
     * @param string $name the name in the driver class without ATTR_ (MULTI_STATEMENTS)
     * @param string $old the name in PDO (MYSQL_ATTR_MULTI_STATEMENTS)
     * @param bool $attribute whether it is an attribute (ATTR_ prefix in the driver class)
     * @return int|null null when the driver does not have it
     */
    protected static function driverConstant( $class, $name, $old, $attribute = true )
    {
        $new = 'Pdo\\' . $class . '::' . ( $attribute ? 'ATTR_' : '' ) . $name;
        if ( defined( $new ) )
            return (int)constant( $new );
        return defined( 'PDO::' . $old ) ? (int)constant( 'PDO::' . $old ) : null;
    }

    /** @return string a row as one line of JSON (binary values that are not UTF-8 as base64) */
    protected static function jsonLine( array $row )
    {
        foreach ( $row as $column => $value )
        {
            if ( is_resource( $value ) )
                $value = (string)stream_get_contents( $value );
            if ( is_string( $value ) && !preg_match( '//u', $value ) )
                $value = 'base64:' . base64_encode( $value );
            $row[$column] = $value;
        }
        return (string)json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }

    /** @return string a message without the password (by its secret name, and the value itself) */
    protected static function maskText( $text, array $refs, $password )
    {
        $text = expImportSecrets::mask( (string)$text, $refs );
        if ( strlen( (string)$password ) >= 3 )
            $text = str_replace( (string)$password, '[password]', $text );
        return $text;
    }
}
