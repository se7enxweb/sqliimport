<?php
/**
 * File containing the expImportSqlSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of an SQL query against a MySQL or MariaDB, PostgreSQL, SQLite, Oracle or SQL Server database, or against
 * this site's own database when the settings allow it. One record is one row, its fields the columns of the query.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() runs the query and writes the rows into
 * the run's snapshot, one JSON object per line, which the chunks of the run then read like an NDJSON file.
 *
 * Only reading is possible:
 *   - the query must be a single SELECT (or WITH ... SELECT); checkQuery() refuses everything else, with the string
 *     literals and comments of the driver's dialect taken out first, so a keyword in a text does not count and a
 *     statement hidden behind a comment or a quote is found
 *   - the connection itself is read-only where the driver offers it (MySQL and MariaDB: SET SESSION TRANSACTION READ
 *     ONLY; PostgreSQL: default_transaction_read_only; SQLite: opened read-only, PRAGMA query_only; Oracle: SET
 *     TRANSACTION READ ONLY, never committed; SQL Server: ApplicationIntent=ReadOnly, which only routes the connection,
 *     so the database user must have SELECT rights only), multiple statements and emulated prepares are off, and a
 *     statement time limit is set where the server has one
 *   - the password is the name of a secret (expImportSecrets), never stored in the import, and masked in every message
 *   - a database server in a private network is refused like an address (sqliimport.ini [SourceSettings]
 *     AllowPrivateHosts), unless an administrator lists it in [SqlSourceSettings] AllowedHosts[]; an SQLite file must
 *     lie inside [SourceSettings] AllowedPaths[]; an Oracle TNS name must be listed in [SqlSourceSettings]
 *     AllowedTnsNames[]; this site's database only with [SqlSourceSettings] SiteDatabase=enabled
 *
 * Drivers: Oracle through oci8, else pdo_oci; SQL Server through pdo_sqlsrv, else pdo_dblib (FreeTDS), else PDO_ODBC
 * with an ODBC driver ([SqlSourceSettings] OdbcDriver). Runs are read by the job queue's workers, whose PHP may have a
 * driver the web server's PHP has not: drivers() tells both apart (recordWorkerPhp()).
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
        'FTS3_TOKENIZER', 'SYS_EXEC', 'SYS_EVAL', 'XP_CMDSHELL', 'OPENROWSET', 'OPENQUERY', 'OPENDATASOURCE',
    );

    /** The words of T-SQL (SQL Server) that start another statement, wait, lock or reach outside the query. */
    const SQLSRV_FORBIDDEN = array(
        'BEGIN', 'DECLARE', 'SET', 'USE', 'WAITFOR', 'DBCC', 'BACKUP', 'RESTORE', 'BULK', 'RECONFIGURE', 'SETUSER', 'REVERT',
        'DENY', 'CHECKPOINT', 'COMMIT', 'ROLLBACK', 'SAVE', 'READTEXT', 'WRITETEXT', 'UPDATETEXT', 'OPENXML', 'UPDLOCK',
        'XLOCK', 'HOLDLOCK', 'TABLOCK', 'TABLOCKX', 'PAGLOCK',
    );

    /** The words of Oracle SQL and PL/SQL that open a block, declare code, end the transaction or call out. */
    const ORACLE_FORBIDDEN = array(
        'BEGIN', 'DECLARE', 'FUNCTION', 'PROCEDURE', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'HTTPURITYPE', 'DBURITYPE',
        'XDBURITYPE',
    );

    /** The ODBC driver for SQL Server when [SqlSourceSettings] OdbcDriver is not set. */
    const DEFAULT_ODBC_DRIVER = 'ODBC Driver 18 for SQL Server';

    /**
     * What drivers() and the connection see of this PHP, for the tests: array( 'pdo' => string[] PDO drivers,
     * 'oci8' => bool, 'odbc' => string[]|null the ODBC drivers of odbcinst.ini, null: unknown ). null: this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    /**
     * Makes the PDO connections instead of new PDO(), for the tests: function ( $dsn, $user, $password, array
     * $attributes ) returning a PDO. null: new PDO().
     *
     * @var callable|null
     */
    public static $pdoFactory = null;

    /** @var string|null the file of the worker's PHP (recordWorkerPhp()); null: in the storage directory */
    public static $workerFile = null;

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
        return 'The rows of an SQL query against a MySQL or MariaDB, PostgreSQL, SQLite, Oracle or SQL Server database (an '
             . 'intranet database, the database of another application): one record is one row, its fields the columns of '
             . 'the query. Only a single SELECT is accepted and the connection is read-only; the password is the name of a '
             . 'secret in the settings. Oracle needs oci8 or pdo_oci, SQL Server pdo_sqlsrv, pdo_dblib or PDO_ODBC in the '
             . 'PHP of the job queue\'s workers.';
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
            array( 'label' => 'An Oracle database: host ora.example.org, service ORCLPDB1, the password as the secret erp_db',
                   'options' => array( 'driver' => 'oracle', 'host' => 'ora.example.org', 'port' => 1521, 'database' => 'ORCLPDB1', 'user' => 'reader',
                                       'password' => 'erp_db', 'query' => 'SELECT id AS "id", title AS "title", changed AS "changed" FROM news WHERE published = 1 ORDER BY id',
                                       'key' => 'id' ) ),
            array( 'label' => 'A SQL Server database: host mssql.example.org, database Shop, the 100 newest products, the password as the secret shop_db',
                   'options' => array( 'driver' => 'sqlsrv', 'host' => 'mssql.example.org', 'port' => 1433, 'database' => 'Shop', 'user' => 'reader',
                                       'password' => 'shop_db', 'query' => 'SELECT TOP 100 [Id], [Name], [Description] FROM [dbo].[Products] ORDER BY [Changed] DESC',
                                       'key' => 'Id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'driver' => array( 'type' => 'enum', 'label' => 'Database', 'default' => 'mysql',
                               'values' => array( 'mysql' => 'MySQL or MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite (a file on the server)',
                                                  'oracle' => 'Oracle', 'sqlsrv' => 'Microsoft SQL Server',
                                                  'site' => "This site's database (read-only)" ) ),
            'host' => array( 'type' => 'string', 'label' => 'Host', 'max' => 255,
                             'help' => 'MySQL, PostgreSQL, Oracle and SQL Server: the database server (a private network only with [SqlSourceSettings] AllowedHosts[]). Oracle: empty when Database is a TNS name or an Easy Connect string' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 0, 'min' => 0, 'max' => 65535,
                             'help' => '0: the default port (3306, 5432, Oracle 1521, SQL Server 1433)' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'max' => 1024,
                                 'help' => 'The database name; SQLite: the file, inside one of [SourceSettings] AllowedPaths[]; Oracle: the service name, or without a host //host:port/service or a TNS name of [SqlSourceSettings] AllowedTnsNames[]' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255 ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'A single SELECT (or WITH ... SELECT); each row is a record, its columns the fields. SQL Server: TOP and OFFSET ... FETCH page the rows' ),
            'key' => array( 'type' => 'string', 'label' => 'Key column',
                            'help' => 'The column that names a row (an ID). Empty: the position in the result. Oracle gives unquoted column names in capitals' ),
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
     * word of FORBIDDEN (REPLACE only as the function REPLACE( ... )), nor of the dialect's own list.
     *
     * @param string $query
     * @param string $driver mysql, pgsql, sqlite, oracle or sqlsrv (site: the dialect of this site's database)
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
        if ( $driver === 'oracle' && strpos( $code, '@' ) !== false )
            return 'database links (@name) are not allowed in the query';
        if ( strpos( $code, ':=' ) !== false || preg_match( '/@@?\s*[A-Za-z_]/', $code ) )
            return 'variables (@name, :=) are not allowed in the query';
        if ( preg_match( '/\bREPLACE\b(?!\s*\()/i', $code ) )
            return 'the query contains REPLACE (only the function REPLACE( ... ) is allowed)';
        if ( preg_match( '/\b(' . implode( '|', self::FORBIDDEN ) . ')\b/i', $code, $m ) )
            return 'the query contains ' . strtoupper( $m[1] ) . ', only reading is allowed';
        if ( $driver === 'sqlsrv' )
        {
            if ( preg_match( '/\b(' . implode( '|', self::SQLSRV_FORBIDDEN ) . ')\b/i', $code, $m ) )
                return 'the query contains ' . strtoupper( $m[1] ) . ', only reading is allowed';
            if ( preg_match( '/\b((?:sp|xp)_[A-Za-z0-9_]*)/i', $code, $m ) )
                return 'the query calls ' . $m[1] . ': system procedures (sp_..., xp_...) are not allowed';
            if ( preg_match( '/\bNEXT\s+VALUE\s+FOR\b/i', $code ) )
                return 'the query contains NEXT VALUE FOR, which changes a sequence';
        }
        if ( $driver === 'oracle' )
        {
            if ( preg_match( '/\b(' . implode( '|', self::ORACLE_FORBIDDEN ) . ')\b/i', $code, $m ) )
                return 'the query contains ' . strtoupper( $m[1] ) . ', only reading is allowed';
            if ( preg_match( '/\b((?:DBMS|UTL|OWA)_[A-Za-z0-9_$#]*|HTP|HTF)\b/i', $code, $m ) )
                return 'the query calls the package ' . strtoupper( $m[1] ) . ': Oracle packages (DBMS_..., UTL_...) are not allowed';
        }
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
        $sqlsrv = $driver === 'sqlsrv';
        $oracle = $driver === 'oracle';
        // PostgreSQL and T-SQL nest block comments; brackets quote names in SQLite and T-SQL
        $nested = $pgsql || $sqlsrv;
        $brackets = $driver === 'sqlite' || $sqlsrv;
        $out = '';
        $n = strlen( $query );
        $i = 0;
        while ( $i < $n )
        {
            $c = $query[$i];
            $next = $i + 1 < $n ? $query[$i + 1] : '';
            // comments: -- (and # in MySQL) to the end of the line, /* ... */ (nested in PostgreSQL and T-SQL)
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
                    if ( $nested && $query[$i] === '/' && $i + 1 < $n && $query[$i + 1] === '*' )
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
            // Oracle alternative quoting: q'[...]', Q'{...}', nq'<...>', q'!...!' (the closing delimiter and a quote)
            if ( $oracle && ( $c === 'q' || $c === 'Q' ) && $next === "'" && self::startsWord( $query, $i ) )
            {
                if ( $i + 2 >= $n || strpos( " \t\r\n'", $query[$i + 2] ) !== false )
                    return null;
                $pairs = array( '[' => ']', '{' => '}', '(' => ')', '<' => '>' );
                $close = isset( $pairs[$query[$i + 2]] ) ? $pairs[$query[$i + 2]] : $query[$i + 2];
                $end = strpos( $query, $close . "'", $i + 3 );
                if ( $end === false )
                    return null;
                // the N of nq'...' was copied as code already: it stays a harmless N before the literal
                $out .= "''";
                $i = $end + 2;
                continue;
            }
            // string literals and quoted identifiers
            if ( $c === "'" || $c === '"' || $c === '`' || ( $brackets && $c === '[' ) )
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
                        // a doubled quote is the quote itself (in T-SQL also ]] in a bracketed name)
                        if ( ( $close !== ']' || $sqlsrv ) && $j + 1 < $n && $query[$j + 1] === $close )
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
                // a quoted name that is called ("pg_read_file"( ... )) is checked like a function name; in Oracle also
                // one before a dot, the package of a call ("DBMS_LOCK"."SLEEP")
                if ( $isName && preg_match( $oracle ? '/\G\s*[(.]/' : '/\G\s*\(/', $query, $m, 0, $j + 1 ) )
                    $out .= ' ' . preg_replace( '/[^A-Za-z0-9_$#]+/', ' ', substr( $query, $i + 1, $j - $i - 1 ) ) . ' ';
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

    /** @return bool whether the letter at $i starts a word (or follows the N of Oracle's nq'...') */
    protected static function startsWord( $query, $i )
    {
        if ( $i === 0 || !preg_match( '/[A-Za-z0-9_$#]/', $query[$i - 1] ) )
            return true;
        return strtoupper( $query[$i - 1] ) === 'N' && ( $i === 1 || !preg_match( '/[A-Za-z0-9_$#]/', $query[$i - 2] ) );
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
        $tmp = $target . '.part';
        $out = fopen( $tmp, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'SQL: the snapshot cannot be written' );
        try
        {
            self::rows( $options, $limit, function ( array $row ) use ( $out )
            {
                fwrite( $out, self::jsonLine( $row ) . "\n" );
            } );
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'SQL: the snapshot cannot be written' );
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
     * The editor's "Test connection": connects, runs the query for its first row and reports the columns, or why it
     * failed (never a password: the message is masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $driver = isset( $options['driver'] ) ? (string)$options['driver'] : 'mysql';
        $rows = 0;
        $first = array();
        try
        {
            $columns = self::rows( $options, 1, function ( array $row ) use ( &$rows, &$first )
            {
                $rows++;
                if ( !$first )
                    $first = array_keys( $row );
            } );
            if ( !$columns )
                $columns = $first;
            return array( 'ok' => true, 'columns' => array_values( array_map( 'strval', $columns ) ), 'rows' => $rows, 'driver' => $driver,
                          'message' => $rows ? 'connected, the query gives these columns' : 'connected; the query gives no row' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver, 'message' => $e->getMessage() );
        }
    }

    /**
     * Connects, checks and runs the query, and hands each row (column => value) to $each.
     *
     * @param array $options
     * @param int $limit the most rows; 0: all
     * @param callable $each function ( array $row )
     * @return string[] the columns of the result (empty when the driver cannot tell before the first row)
     * @throws expImportSourceException
     */
    protected static function rows( array $options, $limit, callable $each )
    {
        $driver = isset( $options['driver'] ) ? (string)$options['driver'] : 'mysql';
        $query = isset( $options['query'] ) ? (string)$options['query'] : '';
        list( $dsn, $user, $password, $dialect, $refs ) = self::connection( $options );
        $refusal = self::checkQuery( $query, $dialect );
        if ( $refusal !== null )
            throw new expImportSourceException( 'SQL: ' . $refusal );
        // Oracle takes one statement without its ";"
        if ( $dialect === 'oracle' )
            $query = rtrim( preg_replace( '/;\s*$/', '', rtrim( $query ) ) );
        $seconds = max( 1, (int)expImport::setting( 'SqlSourceSettings', 'QueryTimeout', 60 ) );
        if ( strpos( $dsn, 'oci8:' ) === 0 )
            return self::ociRows( substr( $dsn, 5 ), $user, $password, $query, $limit, $seconds, $refs, $each );
        $started = microtime( true );
        try
        {
            $pdo = self::open( $dsn, $user, $password, $dialect, $seconds );
            $statement = $pdo->prepare( $query );
            $statement->execute();
            $columns = array();
            try
            {
                for ( $c = 0, $count = (int)$statement->columnCount(); $c < $count; $c++ )
                {
                    $meta = $statement->getColumnMeta( $c );
                    if ( is_array( $meta ) && isset( $meta['name'] ) )
                        $columns[] = (string)$meta['name'];
                }
            }
            catch ( PDOException $e )
            {
                $columns = array();
            }
            $rows = 0;
            while ( ( $row = $statement->fetch( PDO::FETCH_ASSOC ) ) !== false )
            {
                $each( self::textValues( (array)$row, $dialect ) );
                $rows++;
                if ( $limit > 0 && $rows >= $limit )
                    break;
                if ( ( $rows & 255 ) === 0 && microtime( true ) - $started > $seconds * 10 )
                    throw new expImportSourceException( "SQL: reading the rows took longer than ten times [SqlSourceSettings] QueryTimeout ($seconds s)" );
            }
            $statement->closeCursor();
            return $columns;
        }
        catch ( PDOException $e )
        {
            throw new expImportSourceException( 'SQL (' . $driver . '): ' . self::maskText( $e->getMessage(), $refs, $password ) );
        }
    }

    /**
     * The rows of an Oracle query through oci8: a connection of its own (oci_new_connect, AL32UTF8), the session's
     * date, timestamp and number formats fixed, a read-only transaction that is never committed, LOBs read as text.
     *
     * @param string $connect the connect string (//host:port/service or a TNS name)
     * @param string $user
     * @param string $password
     * @param string $query
     * @param int $limit
     * @param int $seconds
     * @param string[] $refs
     * @param callable $each
     * @return string[] the columns
     * @throws expImportSourceException
     */
    protected static function ociRows( $connect, $user, $password, $query, $limit, $seconds, array $refs, callable $each )
    {
        $connection = self::openOracle( $connect, $user, $password, $seconds, $refs );
        $started = microtime( true );
        $statement = null;
        try
        {
            $statement = @oci_parse( $connection, $query );
            if ( !$statement )
                throw self::ociException( $connection, $refs, $password );
            @oci_set_prefetch( $statement, 200 );
            if ( !@oci_execute( $statement, OCI_NO_AUTO_COMMIT ) )
                throw self::ociException( $statement, $refs, $password );
            $columns = array();
            for ( $c = 1, $count = (int)oci_num_fields( $statement ); $c <= $count; $c++ )
                $columns[] = (string)oci_field_name( $statement, $c );
            $rows = 0;
            while ( ( $row = @oci_fetch_array( $statement, OCI_ASSOC | OCI_RETURN_NULLS | OCI_RETURN_LOBS ) ) !== false )
            {
                $each( self::textValues( $row, 'oracle' ) );
                $rows++;
                if ( $limit > 0 && $rows >= $limit )
                    break;
                if ( ( $rows & 255 ) === 0 && microtime( true ) - $started > $seconds * 10 )
                    throw new expImportSourceException( "SQL: reading the rows took longer than ten times [SqlSourceSettings] QueryTimeout ($seconds s)" );
            }
            if ( $row === false && is_array( oci_error( $statement ) ) )
                throw self::ociException( $statement, $refs, $password );
            return $columns;
        }
        finally
        {
            if ( $statement )
                @oci_free_statement( $statement );
            // nothing was written; ending the read-only transaction without a commit
            @oci_rollback( $connection );
            @oci_close( $connection );
        }
    }

    /**
     * A read-only Oracle session through oci8 (public for the checks against a real server).
     *
     * @param string $connect
     * @param string $user
     * @param string $password
     * @param int $seconds the time limit of each call to the server (oci8 2.2 and Oracle client 18 or later)
     * @param string[] $refs the secrets, for masking
     * @return resource|object the connection
     * @throws expImportSourceException
     */
    public static function openOracle( $connect, $user, $password, $seconds, array $refs = array() )
    {
        if ( !self::hasOci8() || !function_exists( 'oci_new_connect' ) )
            throw new expImportSourceException( self::missingDriverMessage( 'oracle' ) );
        $connection = @oci_new_connect( $user, $password, $connect, 'AL32UTF8' );
        if ( !$connection )
            throw self::ociException( null, $refs, $password );
        if ( function_exists( 'oci_set_call_timeout' ) )
            @oci_set_call_timeout( $connection, min( 3600, $seconds ) * 1000 );
        foreach ( self::sessionStatements( 'oracle', $seconds ) as $sql )
        {
            $statement = @oci_parse( $connection, $sql );
            if ( !$statement || !@oci_execute( $statement, OCI_NO_AUTO_COMMIT ) )
            {
                $e = self::ociException( $statement ?: $connection, $refs, $password );
                @oci_close( $connection );
                throw $e;
            }
            @oci_free_statement( $statement );
        }
        return $connection;
    }

    /** @return expImportSourceException the last oci8 error, masked */
    protected static function ociException( $handle, array $refs, $password )
    {
        $error = $handle ? @oci_error( $handle ) : @oci_error();
        $text = is_array( $error ) && isset( $error['message'] ) ? (string)$error['message'] : 'the Oracle server did not answer';
        return new expImportSourceException( 'SQL (oracle): ' . self::maskText( $text, $refs, $password ) );
    }

    /**
     * The statements a session runs after connecting, before the query.
     *
     * @param string $dialect
     * @param int $seconds
     * @return string[]
     */
    public static function sessionStatements( $dialect, $seconds )
    {
        switch ( $dialect )
        {
            case 'oracle':
                // ISO 8601 dates and a decimal point, whatever the server's language; then a transaction that cannot write
                return array(
                    "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD\"T\"HH24:MI:SS'",
                    "ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD\"T\"HH24:MI:SS.FF6'",
                    "ALTER SESSION SET NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD\"T\"HH24:MI:SS.FF6TZH:TZM'",
                    "ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'",
                    'SET TRANSACTION READ ONLY',
                );
            case 'sqlsrv':
                // no row counts between the rows, and no waiting for locks longer than the time limit
                return array( 'SET NOCOUNT ON', 'SET IMPLICIT_TRANSACTIONS OFF', 'SET LOCK_TIMEOUT ' . ( $seconds * 1000 ) );
        }
        return array();
    }

    /**
     * What to connect to: the DSN, user, password, the dialect of the query check and the secrets used, after the
     * checks of host, file and settings. Oracle through oci8 has the DSN oci8:<connect string>.
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
            case 'sqlsrv':
                if ( $host === '' || $database === '' )
                    throw new expImportSourceException( 'SQL: the host and the database are required' );
                self::checkHost( $host );
                if ( !preg_match( '/^[A-Za-z0-9_.$-]+$/', $database ) )
                    throw new expImportSourceException( 'SQL: the database name has characters that are not allowed' );
                if ( $driver === 'sqlsrv' )
                    return array( self::sqlServerDsn( $host, $port, $database, self::sqlServerDriver() ), $user, $password, 'sqlsrv', $refs );
                $h = strpos( $host, ':' ) !== false && strpos( $host, '[' ) !== 0 ? '[' . $host . ']' : $host;
                $h = $driver === 'pgsql' ? trim( $h, '[]' ) : $h;
                $dsn = $driver === 'mysql'
                     ? 'mysql:host=' . $h . ';port=' . ( $port ?: 3306 ) . ';dbname=' . $database . ';charset=utf8mb4'
                     : 'pgsql:host=' . $h . ';port=' . ( $port ?: 5432 ) . ';dbname=' . $database;
                return array( $dsn, $user, $password, $driver, $refs );
            case 'oracle':
                $connect = self::oracleConnectString( $host, $port, $database );
                $dsn = self::oracleDriver() === 'pdo_oci' ? 'oci:dbname=' . $connect . ';charset=AL32UTF8' : 'oci8:' . $connect;
                return array( $dsn, $user, $password, 'oracle', $refs );
            case 'site':
                return self::siteConnection();
        }
        throw new expImportSourceException( "SQL: unknown database driver '$driver'" );
    }

    /**
     * Refuses a database server in a private or reserved network unless [SqlSourceSettings] AllowedHosts[] lists it.
     *
     * @param string $host
     * @throws expImportSourceException
     */
    protected static function checkHost( $host )
    {
        $refusal = expImportSourceFiles::refuseHost( $host, (array)expImport::setting( 'SqlSourceSettings', 'AllowedHosts', array() ) );
        if ( $refusal !== null )
            throw new expImportSourceException( 'SQL: ' . $refusal . ' (or list it in sqliimport.ini [SqlSourceSettings] AllowedHosts[])' );
    }

    /**
     * The Oracle connect string of the options, in one of three forms:
     *   host + port + service name (Database)      //host:port/service
     *   no host, Database //host[:port]/service    Easy Connect, host checked the same way
     *   no host, Database a TNS name               only one of [SqlSourceSettings] AllowedTnsNames[] (tnsnames.ora of
     *                                              the server names the host, an administrator's choice)
     * Connect descriptors ((DESCRIPTION=...)) are not accepted: their host could not be checked.
     *
     * @param string $host
     * @param int $port
     * @param string $database
     * @return string
     * @throws expImportSourceException
     */
    public static function oracleConnectString( $host, $port, $database )
    {
        $host = trim( (string)$host );
        $database = trim( (string)$database );
        $service = '/^[A-Za-z0-9_.$#-]+$/';
        if ( $host === '' )
        {
            if ( $database === '' )
                throw new expImportSourceException( 'SQL: Oracle needs a host and a service name, an Easy Connect string (//host:port/service) or a TNS name' );
            if ( preg_match( '~^(?://)?(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9._-]+)(?::(\d{1,5}))?/([A-Za-z0-9_.$#-]+)$~', $database, $m ) )
                return self::oracleConnectString( trim( $m[1], '[]' ), isset( $m[2] ) && $m[2] !== '' ? (int)$m[2] : $port, $m[3] );
            if ( preg_match( '/^[A-Za-z][A-Za-z0-9_.-]*$/', $database ) )
            {
                foreach ( (array)expImport::setting( 'SqlSourceSettings', 'AllowedTnsNames', array() ) as $name )
                    if ( strcasecmp( trim( (string)$name ), $database ) === 0 )
                        return $database;
                throw new expImportSourceException( "SQL: the TNS name $database is not listed in sqliimport.ini [SqlSourceSettings] AllowedTnsNames[]" );
            }
            throw new expImportSourceException( 'SQL: give the Oracle database as host, port and service name, //host:port/service or a TNS name (connect descriptors are not accepted)' );
        }
        if ( $database === '' || !preg_match( $service, $database ) )
            throw new expImportSourceException( 'SQL: the Oracle service name is missing or has characters that are not allowed' );
        self::checkHost( $host );
        $h = strpos( $host, ':' ) !== false ? '[' . trim( $host, '[]' ) . ']' : $host;
        return '//' . $h . ':' . ( $port ?: 1521 ) . '/' . $database;
    }

    /**
     * The PDO DSN of a SQL Server database for a driver: pdo_sqlsrv and the Microsoft ODBC driver with
     * ApplicationIntent=ReadOnly and the encryption of [SqlSourceSettings] SqlServerEncrypt; pdo_dblib and the FreeTDS
     * ODBC driver in UTF-8.
     *
     * @param string $host
     * @param int $port
     * @param string $database
     * @param string $driver sqlsrv, dblib or odbc
     * @return string
     * @throws expImportSourceException
     */
    public static function sqlServerDsn( $host, $port, $database, $driver )
    {
        $port = $port ?: 1433;
        $host = trim( (string)$host, '[]' );
        $encrypt = expImport::setting( 'SqlSourceSettings', 'SqlServerEncrypt', 'enabled' ) !== 'disabled';
        $trust = expImport::setting( 'SqlSourceSettings', 'SqlServerTrustServerCertificate', 'disabled' ) === 'enabled';
        switch ( $driver )
        {
            case 'dblib':
                return 'dblib:host=' . $host . ':' . $port . ';dbname=' . $database . ';charset=UTF-8;appname=Exponential Imports';
            case 'odbc':
                $name = self::odbcDriverName();
                if ( stripos( $name, 'freetds' ) !== false )
                    return 'odbc:Driver={' . $name . '};Server=' . $host . ';Port=' . $port . ';Database=' . $database
                         . ';ClientCharset=UTF-8;TDS_Version=7.4;ApplicationIntent=ReadOnly';
                return 'odbc:Driver={' . $name . '};Server=tcp:' . $host . ',' . $port . ';Database=' . $database . ';ApplicationIntent=ReadOnly'
                     . ';Encrypt=' . ( $encrypt ? 'yes' : 'no' ) . ';TrustServerCertificate=' . ( $trust ? 'yes' : 'no' ) . ';APP=Exponential Imports';
        }
        return 'sqlsrv:Server=tcp:' . $host . ',' . $port . ';Database=' . $database . ';ApplicationIntent=ReadOnly;MultipleActiveResultSets=false'
             . ';Encrypt=' . ( $encrypt ? '1' : '0' ) . ';TrustServerCertificate=' . ( $trust ? '1' : '0' ) . ';APP=Exponential Imports';
    }

    /**
     * The ODBC driver for SQL Server ([SqlSourceSettings] OdbcDriver), as odbcinst.ini names it.
     *
     * @return string
     * @throws expImportSourceException
     */
    protected static function odbcDriverName()
    {
        $name = trim( (string)expImport::setting( 'SqlSourceSettings', 'OdbcDriver', '' ) );
        if ( $name === '' )
        {
            // the Microsoft driver when it is installed, else FreeTDS when it is
            $installed = self::odbcDrivers();
            $name = self::DEFAULT_ODBC_DRIVER;
            if ( is_array( $installed ) && !in_array( $name, $installed, true ) )
                foreach ( $installed as $candidate )
                    if ( stripos( $candidate, 'freetds' ) !== false )
                        $name = $candidate;
        }
        if ( !preg_match( '/^[A-Za-z0-9 ._()+-]+$/', $name ) )
            throw new expImportSourceException( 'SQL: [SqlSourceSettings] OdbcDriver has characters that are not allowed' );
        return $name;
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
        if ( strpos( $implementation, 'oracle' ) !== false )
        {
            // the kernel's Oracle setting names a connect string or a TNS name in Database, the administrator's own
            $connect = $server !== '' && $server !== 'localhost' ? '//' . $server . ':' . ( $port ?: 1521 ) . '/' . $database : $database;
            return array( self::oracleDriver() === 'pdo_oci' ? 'oci:dbname=' . $connect . ';charset=AL32UTF8' : 'oci8:' . $connect, $user, $password, 'oracle', array() );
        }
        throw new expImportSourceException( "SQL: this site's database ($implementation) cannot be read by the SQL source" );
    }

    /**
     * A read-only connection: errors as exceptions, no emulated prepares, no multiple statements, a time limit.
     *
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param string $dialect mysql, pgsql, sqlite, oracle (pdo_oci) or sqlsrv
     * @param int $seconds the statement time limit
     * @return PDO
     * @throws PDOException|expImportSourceException
     */
    public static function open( $dsn, $user, $password, $dialect, $seconds )
    {
        $pdoDriver = (string)substr( $dsn, 0, (int)strpos( $dsn, ':' ) );
        if ( !in_array( $pdoDriver, self::pdoDrivers(), true ) )
        {
            if ( in_array( $dialect, array( 'oracle', 'sqlsrv' ), true ) )
                throw new expImportSourceException( self::missingDriverMessage( $dialect ) );
            throw new expImportSourceException( "SQL: the PHP extension pdo_$pdoDriver is not installed" );
        }
        $attributes = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION );
        if ( in_array( $dialect, array( 'mysql', 'pgsql', 'sqlite' ), true ) )
            $attributes += array( PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => min( 30, $seconds ), PDO::ATTR_STRINGIFY_FETCHES => false );
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
        if ( $pdoDriver === 'oci' )
            $attributes[PDO::ATTR_AUTOCOMMIT] = false;
        if ( $pdoDriver === 'sqlsrv' )
        {
            // nvarchar and varchar as UTF-8, and the statement time limit of the driver
            if ( defined( 'PDO::SQLSRV_ATTR_ENCODING' ) && defined( 'PDO::SQLSRV_ENCODING_UTF8' ) )
                $attributes[(int)constant( 'PDO::SQLSRV_ATTR_ENCODING' )] = (int)constant( 'PDO::SQLSRV_ENCODING_UTF8' );
            if ( defined( 'PDO::SQLSRV_ATTR_QUERY_TIMEOUT' ) )
                $attributes[(int)constant( 'PDO::SQLSRV_ATTR_QUERY_TIMEOUT' )] = $seconds;
        }
        if ( $pdoDriver === 'dblib' )
        {
            $timeout = self::driverConstant( 'Dblib', 'QUERY_TIMEOUT', 'DBLIB_ATTR_QUERY_TIMEOUT' );
            if ( $timeout !== null )
                $attributes[$timeout] = $seconds;
        }
        $user = $user !== '' ? $user : null;
        $password = $password !== '' ? $password : null;
        $pdo = self::$pdoFactory ? call_user_func( self::$pdoFactory, $dsn, $user, $password, $attributes ) : new PDO( $dsn, $user, $password, $attributes );
        if ( !$pdo instanceof PDO )
            throw new expImportSourceException( 'SQL: no connection' );
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
            case 'oracle':
                foreach ( self::sessionStatements( 'oracle', $seconds ) as $sql )
                    $pdo->exec( $sql );
                break;
            case 'sqlsrv':
                foreach ( self::sessionStatements( 'sqlsrv', $seconds ) as $sql )
                    $pdo->exec( $sql );
                break;
        }
        return $pdo;
    }

    /**
     * A constant of a PDO driver: Pdo\Mysql::ATTR_... (PHP 8.4 and later; the PDO::MYSQL_ATTR_... names are deprecated
     * in 8.5), else the PDO:: one.
     *
     * @param string $class Mysql, Sqlite or Dblib
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

    /**
     * The values of a row as JSON can hold them: streams (LOBs of PDO) read, oci8 LOB objects loaded, and text from an
     * ODBC driver in another character set ([SqlSourceSettings] OdbcCharset) converted to UTF-8.
     *
     * @param array $row
     * @param string $dialect
     * @return array
     */
    protected static function textValues( array $row, $dialect )
    {
        $charset = $dialect === 'sqlsrv' ? trim( (string)expImport::setting( 'SqlSourceSettings', 'OdbcCharset', '' ) ) : '';
        foreach ( $row as $column => $value )
        {
            if ( is_object( $value ) && method_exists( $value, 'load' ) )
                $value = (string)$value->load();
            if ( is_resource( $value ) )
                $value = (string)stream_get_contents( $value );
            if ( $charset !== '' && strcasecmp( $charset, 'UTF-8' ) !== 0 && is_string( $value ) && !preg_match( '//u', $value ) && function_exists( 'mb_convert_encoding' ) )
                $value = (string)mb_convert_encoding( $value, 'UTF-8', $charset );
            $row[$column] = $value;
        }
        return $row;
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

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /** @return string[] the PDO drivers of this PHP */
    protected static function pdoDrivers()
    {
        if ( is_array( self::$environment ) && isset( self::$environment['pdo'] ) )
            return (array)self::$environment['pdo'];
        return class_exists( 'PDO' ) ? PDO::getAvailableDrivers() : array();
    }

    /** @return bool whether this PHP has oci8 */
    protected static function hasOci8()
    {
        if ( is_array( self::$environment ) && isset( self::$environment['oci8'] ) )
            return (bool)self::$environment['oci8'];
        return function_exists( 'oci_new_connect' );
    }

    /**
     * The ODBC drivers unixODBC knows (the sections of odbcinst.ini), null when the file cannot be read.
     *
     * @return string[]|null
     */
    protected static function odbcDrivers()
    {
        if ( is_array( self::$environment ) && array_key_exists( 'odbc', self::$environment ) )
            return self::$environment['odbc'] === null ? null : (array)self::$environment['odbc'];
        $dirs = array_filter( array( (string)getenv( 'ODBCSYSINI' ), '/etc', '/usr/local/etc', '/etc/unixODBC' ) );
        foreach ( $dirs as $dir )
        {
            $file = rtrim( $dir, '/' ) . '/odbcinst.ini';
            $text = @is_readable( $file ) ? @file_get_contents( $file ) : false;
            if ( is_string( $text ) && preg_match_all( '/^\s*\[([^\]\r\n]+)\]/m', $text, $m ) )
                return array_values( array_filter( array_map( 'trim', $m[1] ), function ( $name ) { return strcasecmp( $name, 'ODBC' ) !== 0; } ) );
        }
        return null;
    }

    /**
     * The driver Oracle connects through: [SqlSourceSettings] OracleDriver (auto, oci8, pdo_oci); auto: oci8 when this
     * PHP has it, else pdo_oci.
     *
     * @return string oci8 or pdo_oci (the one to use, or to name when neither is there)
     */
    public static function oracleDriver()
    {
        $setting = (string)expImport::setting( 'SqlSourceSettings', 'OracleDriver', 'auto' );
        if ( in_array( $setting, array( 'oci8', 'pdo_oci' ), true ) )
            return $setting;
        if ( self::hasOci8() )
            return 'oci8';
        return in_array( 'oci', self::pdoDrivers(), true ) ? 'pdo_oci' : 'oci8';
    }

    /**
     * The PDO driver SQL Server connects through: [SqlSourceSettings] SqlServerDriver (auto, sqlsrv, dblib, odbc); auto:
     * pdo_sqlsrv, else pdo_dblib, else PDO_ODBC.
     *
     * @return string sqlsrv, dblib or odbc
     */
    public static function sqlServerDriver()
    {
        $setting = (string)expImport::setting( 'SqlSourceSettings', 'SqlServerDriver', 'auto' );
        if ( in_array( $setting, array( 'sqlsrv', 'dblib', 'odbc' ), true ) )
            return $setting;
        $pdo = self::pdoDrivers();
        foreach ( array( 'sqlsrv', 'dblib' ) as $driver )
            if ( in_array( $driver, $pdo, true ) )
                return $driver;
        return self::sqlServerOdbc() !== '' ? 'odbc' : 'sqlsrv';
    }

    /**
     * PDO_ODBC with a driver for SQL Server: [SqlSourceSettings] OdbcDriver, or one of odbcinst.ini whose name has
     * "SQL Server" or "FreeTDS" in it.
     *
     * @return string PDO_ODBC and the driver's name; '' when this PHP has no PDO_ODBC or no such driver is installed
     */
    protected static function sqlServerOdbc()
    {
        if ( !in_array( 'odbc', self::pdoDrivers(), true ) )
            return '';
        $odbc = self::odbcDrivers();
        $setting = trim( (string)expImport::setting( 'SqlSourceSettings', 'OdbcDriver', '' ) );
        if ( $odbc === null )
            return 'PDO_ODBC' . ( $setting !== '' ? ' (' . $setting . ')' : '' );
        foreach ( $odbc as $name )
            if ( $setting !== '' ? strcasecmp( $name, $setting ) === 0 : ( stripos( $name, 'SQL Server' ) !== false || stripos( $name, 'freetds' ) !== false ) )
                return 'PDO_ODBC (' . $name . ')';
        return '';
    }

    /**
     * Which databases this PHP can read and through what (the editor's and the catalogue's driver list), with what
     * the host needs for a missing one and what the job queue's workers last had (recordWorkerPhp()).
     *
     * @return array database => array( 'label', 'available' => bool, 'via' => string, 'needs' => string,
     *               'worker' => yes, no or '' (not known), 'worker_php' => string )
     */
    public static function drivers()
    {
        $pdo = self::pdoDrivers();
        $sqlServerOdbc = self::sqlServerOdbc();
        $list = array(
            'mysql' => array( 'label' => 'MySQL or MariaDB', 'via' => in_array( 'mysql', $pdo, true ) ? 'pdo_mysql' : '',
                              'needs' => 'pdo_mysql (RHEL: php-mysqlnd; Debian: php-mysql)' ),
            'pgsql' => array( 'label' => 'PostgreSQL', 'via' => in_array( 'pgsql', $pdo, true ) ? 'pdo_pgsql' : '',
                              'needs' => 'pdo_pgsql (RHEL: php-pgsql; Debian: php-pgsql)' ),
            'sqlite' => array( 'label' => 'SQLite', 'via' => in_array( 'sqlite', $pdo, true ) ? 'pdo_sqlite' : '',
                               'needs' => 'pdo_sqlite (RHEL: php-pdo; Debian: php-sqlite3)' ),
            'oracle' => array( 'label' => 'Oracle', 'via' => self::hasOci8() ? 'oci8' : ( in_array( 'oci', $pdo, true ) ? 'pdo_oci' : '' ),
                               'needs' => 'oci8 and the Oracle Instant Client (RHEL: oracle-instantclient-basic and the PECL oci8 extension, '
                                        . 'e.g. php-oci8 of Remi; Debian: the Instant Client and the PECL oci8 extension), or pdo_oci' ),
            'sqlsrv' => array( 'label' => 'Microsoft SQL Server',
                               'via' => in_array( 'sqlsrv', $pdo, true ) ? 'pdo_sqlsrv' : ( in_array( 'dblib', $pdo, true ) ? 'pdo_dblib (FreeTDS)' : $sqlServerOdbc ),
                               'needs' => 'pdo_sqlsrv and the Microsoft ODBC Driver 18 (RHEL and Debian: msodbcsql18 from Microsoft\'s repository and the '
                                        . 'PECL pdo_sqlsrv extension, e.g. php-sqlsrv of Remi), or pdo_dblib with FreeTDS (RHEL: php-pdo-dblib, freetds; '
                                        . 'Debian: php-sybase, freetds-common), or PDO_ODBC with an ODBC driver (RHEL: php-odbc, unixODBC and msodbcsql18 '
                                        . 'or freetds; Debian: php-odbc, unixodbc and msodbcsql18 or tdsodbc)' ),
        );
        $worker = self::workerPhp();
        foreach ( $list as $id => $row )
        {
            $list[$id]['available'] = $row['via'] !== '';
            $list[$id]['worker'] = $worker && isset( $worker['drivers'][$id] ) ? ( $worker['drivers'][$id] !== '' ? 'yes' : 'no' ) : '';
            $list[$id]['worker_php'] = $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '';
        }
        return $list;
    }

    /**
     * Why a database cannot be read in this PHP, and whether the job queue's workers, which read the runs, can.
     *
     * @param string $database oracle or sqlsrv
     * @return string
     */
    public static function missingDriverMessage( $database )
    {
        $drivers = self::drivers();
        $row = isset( $drivers[$database] ) ? $drivers[$database] : array( 'label' => $database, 'needs' => '', 'worker' => null, 'worker_php' => '' );
        $what = $database === 'oracle' ? 'neither oci8 nor pdo_oci' : ( $database === 'sqlsrv' ? 'neither pdo_sqlsrv, pdo_dblib nor PDO_ODBC with a SQL Server driver' : 'no driver' );
        $text = 'SQL (' . $database . '): this PHP (' . PHP_VERSION . ', ' . PHP_BINARY . ') has ' . $what . ' for ' . $row['label'] . '. ';
        if ( $row['worker'] === 'yes' )
            $text .= 'Runs still work: the job queue\'s workers read them, and their PHP (' . $row['worker_php'] . ') has it.';
        elseif ( $row['worker'] === 'no' )
            $text .= 'The job queue\'s workers (' . $row['worker_php'] . ') have none either; the host needs ' . $row['needs'] . '.';
        else
            $text .= 'Runs are read by the job queue\'s workers, which may have it (their PHP is named here after their first run); '
                   . 'otherwise the host needs ' . $row['needs'] . '.';
        return $text;
    }

    /** @return string the file that holds what the workers' PHP has */
    protected static function workerFile()
    {
        return self::$workerFile !== null ? self::$workerFile : expImport::storageDirectory( 'state' ) . '/sql-worker-php.json';
    }

    /**
     * Notes the PHP of a job queue worker and the databases it can read (called by the job handler), so the editor
     * running in another PHP can tell whether runs will work. Written only when it changed, or once a day.
     */
    public static function recordWorkerPhp()
    {
        try
        {
            $file = self::workerFile();
            $drivers = array();
            foreach ( self::drivers() as $id => $row )
                $drivers[$id] = (string)$row['via'];
            $now = array( 'php' => PHP_BINARY, 'version' => PHP_VERSION, 'drivers' => $drivers );
            $old = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : null;
            if ( is_array( $old ) && isset( $old['at'] ) && array_diff_key( $old, array( 'at' => 1 ) ) == $now && time() - (int)$old['at'] < 86400 )
                return;
            $now['at'] = time();
            file_put_contents( $file . '.part', json_encode( $now, JSON_UNESCAPED_SLASHES ) );
            rename( $file . '.part', $file );
        }
        catch ( Throwable $e )
        {
            // only a hint for the editor
        }
    }

    /** @return array|null what recordWorkerPhp() noted: php, version, drivers (database => via), at */
    public static function workerPhp()
    {
        try
        {
            $file = self::workerFile();
        }
        catch ( Throwable $e )
        {
            return null;
        }
        $data = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : null;
        return is_array( $data ) && isset( $data['php'], $data['version'], $data['drivers'] ) && is_array( $data['drivers'] ) ? $data : null;
    }
}
