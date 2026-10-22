<?php

use PHPUnit\Framework\TestCase;

/**
 * The SQL source with Oracle and SQL Server: the SELECT-only check in T-SQL and in Oracle SQL and PL/SQL, the Oracle
 * connect strings and their host checks, SQL Server through a mocked PDO (DSN, read-only intent, session statements,
 * masked errors) for each of its drivers, and the driver list with what the job queue's workers have.
 */
class SqlServerOracleSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var string[] the statements a mocked connection was given with exec() */
    protected $executed = array();

    /** @var array the DSN, user, password and attributes of the last mocked connection */
    protected $connected = array();

    /** @var string the query a mocked connection was asked to prepare */
    protected $prepared = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-sqlxtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        // 203.0.113.5 (a documentation address) stands for a public server, whatever this PHP counts as reserved
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'AllowedHosts', array( '203.0.113.5', '2001:db8::5' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
    }

    protected function tearDown(): void
    {
        expImportSqlSource::$environment = null;
        expImportSqlSource::$pdoFactory = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowedHosts', 'AllowedTnsNames', 'SqlServerDriver', 'OracleDriver', 'OdbcDriver', 'SqlServerEncrypt' ) as $name )
            $ini->removeSetting( 'SqlSourceSettings', $name );
        putenv( 'SQ688_SQLX_PASSWORD' );
    }

    public function testTsqlAndOracleSelectsPass(): void
    {
        $ok = array(
            'sqlsrv' => array(
                'SELECT TOP 10 [Id], [Name] FROM [dbo].[Products] ORDER BY [Id]',
                'SELECT TOP (5) PERCENT Id FROM Products',
                'SELECT Id FROM Products ORDER BY Id OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY',
                "SELECT N'Grüner Tee; DELETE FROM x' AS n",
                "SELECT N'it''s' AS n;",
                'SELECT [a]]; DROP TABLE x --] FROM t',
                'SELECT [delete], [update] FROM t',
                'SELECT "insert" FROM t',
                'SELECT 1 /* outer /* inner; DROP */ still a comment; DROP */ AS a',
                "SELECT 1 AS a -- EXEC xp_cmdshell 'dir'\nFROM t",
                'WITH t AS ( SELECT Id FROM Products ) SELECT * FROM t;',
                'SELECT * FROM Products WITH (NOLOCK)',
                'SELECT * FROM #recent',
                'SELECT Id, Name FROM Products FOR JSON PATH',
                'SELECT updated_at, setting_name, reset_count, used FROM news',
            ),
            'oracle' => array(
                "SELECT q'[it's; DELETE FROM x]' AS a FROM dual",
                "SELECT Q'{a ' b}' AS a FROM dual",
                "SELECT nq'<x; DROP>' AS a FROM dual",
                "SELECT q'!x;y!' AS a FROM dual",
                "SELECT q'(a (b) c)' AS a FROM dual",
                'SELECT id AS "id", "UPDATE" FROM news',
                'SELECT * FROM news ORDER BY id FETCH FIRST 10 ROWS ONLY',
                'SELECT * FROM news ORDER BY id OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY',
                "SELECT TO_CHAR( SYSDATE, 'YYYY-MM-DD' ) AS d FROM dual;",
                "SELECT 'a /* b' || '*/ ; c' AS a FROM dual",
                'SELECT id, parent_id FROM tree START WITH parent_id IS NULL CONNECT BY PRIOR id = parent_id',
                'SELECT sid FROM v$session',
                'WITH t AS ( SELECT id FROM news ) SELECT * FROM t',
                'SELECT /*+ INDEX(news news_pk) */ id FROM news',
            ),
        );
        foreach ( $ok as $driver => $queries )
            foreach ( $queries as $query )
                $this->assertNull( expImportSqlSource::checkQuery( $query, $driver ), "$driver: $query" );
    }

    public function testTsqlAndPlsqlThatWritesOrCallsOutIsRefused(): void
    {
        $refused = array(
            'sqlsrv' => array(
                'EXEC sp_who',
                "EXECUTE ( 'SELECT 1' )",
                "SELECT 1 EXEC xp_cmdshell 'dir'",
                'SELECT * FROM sp_helpdb',
                "SELECT [xp_cmdshell]( 'dir' )",
                "SELECT 1 FROM t WHERE x = sp_executesql",
                'SELECT * INTO NewTable FROM Products',
                'SELECT * INTO #copy FROM Products',
                "SELECT * FROM OPENROWSET( 'SQLNCLI', 'Server=x;Trusted_Connection=yes;', 'SELECT 1' )",
                "SELECT * FROM OPENQUERY( linked, 'SELECT 1' )",
                "SELECT * FROM OPENDATASOURCE( 'SQLNCLI', 'Data Source=x' ).db.dbo.t",
                "SELECT * FROM [OPENROWSET]( 'x' )",
                'SELECT 1; SELECT 2',
                'SELECT 1;; ',
                "SELECT 1 WAITFOR DELAY '00:00:10'",
                'MERGE Products USING s ON 1 = 1 WHEN MATCHED THEN DELETE;',
                'WITH x AS ( SELECT 1 AS a ) DELETE FROM Products',
                'WITH x AS ( SELECT 1 AS a ) UPDATE Products SET a = 1',
                'WITH x AS ( SELECT 1 AS a ) MERGE Products USING x ON 1 = 1 WHEN MATCHED THEN DELETE;',
                'WITH x AS ( SELECT 1 AS a ) INSERT INTO t SELECT * FROM x',
                'SELECT * FROM Products WITH (UPDLOCK)',
                'SELECT * FROM Products WITH (TABLOCKX, HOLDLOCK)',
                'SELECT NEXT VALUE FOR dbo.seq',
                'SELECT 1 /* outer /* inner */ ; DROP TABLE t',
                'SELECT [unclosed FROM t',
                "SELECT N'unclosed",
                'DECLARE @x int; SELECT @x',
                'SELECT @@VERSION',
                'SELECT @p',
                'SELECT 1 SET ROWCOUNT 0',
                'BEGIN TRAN SELECT 1',
                'SELECT 1 BEGIN TRAN',
                'SELECT 1 COMMIT',
                'USE master SELECT 1',
                'SELECT 1 USE master',
                'SELECT * FROM Products FOR UPDATE',
                'SELECT 1 DBCC CHECKDB',
                "SELECT 1 BULK INSERT t FROM 'x'",
            ),
            'oracle' => array(
                'BEGIN DELETE FROM news; END;',
                'DECLARE x NUMBER; BEGIN NULL; END;',
                'SELECT 1 FROM dual BEGIN NULL; END;',
                'SELECT * FROM news FOR UPDATE',
                'SELECT * FROM news FOR UPDATE NOWAIT',
                'SELECT * FROM news FOR UPDATE SKIP LOCKED',
                'SELECT DBMS_LOCK.SLEEP( 10 ) FROM dual',
                "SELECT SYS.DBMS_PIPE.SEND_MESSAGE( 'x' ) FROM dual",
                "SELECT \"DBMS_PIPE\".\"SEND_MESSAGE\"( 'x' ) FROM dual",
                "SELECT \"SYS\" . \"DBMS_LOCK\" . \"SLEEP\"( 1 ) FROM dual",
                "SELECT dbms_xmlgen.getxml( 'SELECT 1 FROM dual' ) FROM dual",
                "SELECT UTL_HTTP.REQUEST( 'http://x' ) FROM dual",
                "SELECT utl_inaddr.get_host_address( 'x' ) FROM dual",
                "SELECT HTTPURITYPE( 'http://x' ).getclob() FROM dual",
                "SELECT HTP.PRINT( 'x' ) FROM dual",
                'SELECT * FROM news@remote_db',
                'SELECT * FROM news@"remote"',
                'SELECT * FROM news @ remote',
                'SELECT news_seq.NEXTVAL FROM dual',
                'WITH FUNCTION f RETURN NUMBER IS BEGIN RETURN 1; END; SELECT f FROM dual',
                "SELECT q'[unclosed FROM dual",
                "SELECT q' x ' FROM dual",
                "SELECT q'[a]' AS a FROM dual; DROP TABLE news",
                'SELECT 1 FROM dual; DELETE FROM news',
                'SELECT 1 FROM dual; COMMIT',
                'SELECT 1 FROM dual COMMIT',
                'MERGE INTO news USING s ON ( 1 = 1 ) WHEN MATCHED THEN UPDATE SET a = 1',
                'SELECT * INTO x FROM news',
                'LOCK TABLE news IN EXCLUSIVE MODE',
                'CALL p()',
                "EXECUTE IMMEDIATE 'DROP TABLE news'",
                'SELECT x := 1 FROM dual',
                'SELECT 1 FROM dual /* unclosed',
            ),
        );
        foreach ( $refused as $driver => $queries )
            foreach ( $queries as $query )
                $this->assertNotNull( expImportSqlSource::checkQuery( $query, $driver ), "$driver: $query" );
        $this->assertStringContainsString( 'database links', (string)expImportSqlSource::checkQuery( 'SELECT * FROM news@remote_db', 'oracle' ) );
        $this->assertStringContainsString( 'DBMS_PIPE', (string)expImportSqlSource::checkQuery( "SELECT \"DBMS_PIPE\".\"SEND_MESSAGE\"( 'x' ) FROM dual", 'oracle' ) );
        $this->assertStringContainsString( 'system procedures', (string)expImportSqlSource::checkQuery( 'SELECT * FROM sp_helpdb', 'sqlsrv' ) );
        // the dialect lists add to the common one: what MySQL refuses stays refused in T-SQL and Oracle
        foreach ( array( 'SELECT 1; DROP TABLE t', "SELECT LOAD_FILE( '/etc/passwd' )", 'SELECT SLEEP( 10 )', 'DELETE FROM t', 'SELECT * FROM t FOR UPDATE' ) as $query )
            foreach ( array( 'sqlsrv', 'oracle' ) as $driver )
                $this->assertNotNull( expImportSqlSource::checkQuery( $query, $driver ), "$driver: $query" );
    }

    public function testOracleConnectStrings(): void
    {
        $ini = eZINI::instance( 'sqliimport.ini' );
        $cases = array(
            array( '203.0.113.5', 0, 'ORCLPDB1', '//203.0.113.5:1521/ORCLPDB1' ),
            array( '203.0.113.5', 1522, 'erp.example.org', '//203.0.113.5:1522/erp.example.org' ),
            array( '2001:db8::5', 0, 'FREEPDB1', '//[2001:db8::5]:1521/FREEPDB1' ),
            array( '', 0, '//203.0.113.5:1600/FREEPDB1', '//203.0.113.5:1600/FREEPDB1' ),
            array( '', 0, '203.0.113.5/FREEPDB1', '//203.0.113.5:1521/FREEPDB1' ),
            array( '', 1530, '//203.0.113.5/FREEPDB1', '//203.0.113.5:1530/FREEPDB1' ),
            array( '', 0, '//[2001:db8::5]:1521/FREEPDB1', '//[2001:db8::5]:1521/FREEPDB1' ),
        );
        foreach ( $cases as $case )
            $this->assertSame( $case[3], expImportSqlSource::oracleConnectString( $case[0], $case[1], $case[2] ), json_encode( $case ) );
        $refused = array(
            array( '', 0, 'PRODDB', 'AllowedTnsNames' ),
            array( '', 0, '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=10.0.0.1)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=x)))', 'descriptors' ),
            array( '10.1.2.3', 0, 'ORCL', 'private or reserved' ),
            array( '', 0, '//10.1.2.3:1521/ORCL', 'private or reserved' ),
            array( '', 0, '127.0.0.1/ORCL', 'private or reserved' ),
            array( '203.0.113.5', 0, 'ORCL;x', 'not allowed' ),
            array( '203.0.113.5', 0, '', 'service name' ),
            array( '', 0, '', 'TNS name' ),
            array( 'evil host', 0, 'ORCL', 'host name or an IP address' ),
        );
        foreach ( $refused as $case )
        {
            try
            {
                expImportSqlSource::oracleConnectString( $case[0], $case[1], $case[2] );
                $this->fail( 'refused: ' . json_encode( $case ) );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( $case[3], $e->getMessage(), json_encode( $case ) );
            }
        }
        // a TNS name an administrator lists (tnsnames.ora of the server names its host)
        $ini->setVariable( 'SqlSourceSettings', 'AllowedTnsNames', array( 'proddb' ) );
        $this->assertSame( 'PRODDB', expImportSqlSource::oracleConnectString( '', 0, 'PRODDB' ) );
        // the driver: oci8 when there, else pdo_oci with AL32UTF8
        putenv( 'SQ688_SQLX_PASSWORD=pw-of-the-oracle-test' );
        $options = array( 'driver' => 'oracle', 'host' => '203.0.113.5', 'database' => 'ORCLPDB1', 'user' => 'reader', 'password' => 'env:SQ688_SQLX_PASSWORD' );
        expImportSqlSource::$environment = array( 'pdo' => array( 'oci' ), 'oci8' => true );
        list( $dsn, $user, $password, $dialect, $refs ) = expImportSqlSource::connection( $options );
        $this->assertSame( array( 'oci8://203.0.113.5:1521/ORCLPDB1', 'reader', 'pw-of-the-oracle-test', 'oracle', array( 'env:SQ688_SQLX_PASSWORD' ) ),
                           array( $dsn, $user, $password, $dialect, $refs ) );
        expImportSqlSource::$environment = array( 'pdo' => array( 'oci' ), 'oci8' => false );
        $this->assertSame( 'oci:dbname=//203.0.113.5:1521/ORCLPDB1;charset=AL32UTF8', expImportSqlSource::connection( $options )[0] );
        $ini->setVariable( 'SqlSourceSettings', 'OracleDriver', 'oci8' );
        $this->assertSame( 'oci8', expImportSqlSource::oracleDriver() );
    }

    public function testOracleSessionsAreReadOnlyWithStableFormats(): void
    {
        $statements = expImportSqlSource::sessionStatements( 'oracle', 60 );
        $this->assertSame( 'SET TRANSACTION READ ONLY', end( $statements ), 'the read-only transaction starts last, after the session settings' );
        $all = implode( "\n", $statements );
        foreach ( array( "NLS_DATE_FORMAT = 'YYYY-MM-DD\"T\"HH24:MI:SS'", 'NLS_TIMESTAMP_FORMAT', 'NLS_TIMESTAMP_TZ_FORMAT', "NLS_NUMERIC_CHARACTERS = '.,'" ) as $part )
            $this->assertStringContainsString( $part, $all );
        // pdo_oci: no autocommit, the session statements, the query without its ";", LOBs read
        expImportSqlSource::$environment = array( 'pdo' => array( 'oci' ), 'oci8' => false );
        expImportSqlSource::$pdoFactory = $this->factory( array( array( 'ID' => '1', 'BODY' => $this->lob( 'a long text' ), 'CHANGED' => '2026-10-22T10:00:00' ) ), array( 'ID', 'BODY', 'CHANGED' ) );
        $target = $this->dir . '/oracle.ndjson';
        expImportSqlSource::fetch( array( 'driver' => 'oracle', 'host' => '203.0.113.5', 'database' => 'ORCLPDB1', 'query' => "SELECT id, body, changed FROM news;\n" ), $target, 0 );
        $this->assertSame( 'oci:dbname=//203.0.113.5:1521/ORCLPDB1;charset=AL32UTF8', $this->connected[0] );
        $this->assertFalse( $this->connected[3][PDO::ATTR_AUTOCOMMIT] );
        $this->assertSame( $statements, $this->executed );
        $this->assertSame( 'SELECT id, body, changed FROM news', $this->prepared );
        $this->assertSame( array( 'ID' => '1', 'BODY' => 'a long text', 'CHANGED' => '2026-10-22T10:00:00' ), json_decode( (string)file_get_contents( $target ), true ) );
    }

    public function testSqlServerThroughEachDriver(): void
    {
        putenv( 'SQ688_SQLX_PASSWORD=pw-of-the-sqlsrv-test' );
        $options = array( 'driver' => 'sqlsrv', 'host' => '203.0.113.5', 'port' => 0, 'database' => 'Shop', 'user' => 'reader', 'password' => 'env:SQ688_SQLX_PASSWORD',
                          'query' => "SELECT TOP 2 [Id], [Name] FROM [dbo].[Products] ORDER BY [Id]" );
        $rows = array( array( 'Id' => 1, 'Name' => 'Grüner Tee' ), array( 'Id' => 2, 'Name' => 'Oolong' ) );
        $cases = array(
            array( array( 'pdo' => array( 'sqlsrv', 'dblib', 'odbc' ) ),
                   'sqlsrv:Server=tcp:203.0.113.5,1433;Database=Shop;ApplicationIntent=ReadOnly;MultipleActiveResultSets=false;Encrypt=1;TrustServerCertificate=0;APP=Exponential Imports' ),
            array( array( 'pdo' => array( 'dblib', 'odbc' ) ), 'dblib:host=203.0.113.5:1433;dbname=Shop;charset=UTF-8;appname=Exponential Imports' ),
            array( array( 'pdo' => array( 'odbc' ), 'odbc' => array( 'PostgreSQL', 'ODBC Driver 18 for SQL Server', 'FreeTDS' ) ),
                   'odbc:Driver={ODBC Driver 18 for SQL Server};Server=tcp:203.0.113.5,1433;Database=Shop;ApplicationIntent=ReadOnly;Encrypt=yes;TrustServerCertificate=no;APP=Exponential Imports' ),
            array( array( 'pdo' => array( 'odbc' ), 'odbc' => array( 'PostgreSQL', 'FreeTDS' ) ),
                   'odbc:Driver={FreeTDS};Server=203.0.113.5;Port=1433;Database=Shop;ClientCharset=UTF-8;TDS_Version=7.4;ApplicationIntent=ReadOnly' ),
        );
        foreach ( $cases as $case )
        {
            expImportSqlSource::$environment = $case[0];
            expImportSqlSource::$pdoFactory = $this->factory( $rows, array( 'Id', 'Name' ) );
            $target = $this->dir . '/sqlsrv.ndjson';
            expImportSqlSource::fetch( $options, $target, 0 );
            $this->assertSame( $case[1], $this->connected[0] );
            $this->assertSame( array( 'reader', 'pw-of-the-sqlsrv-test' ), array( $this->connected[1], $this->connected[2] ) );
            $this->assertSame( array( 'SET NOCOUNT ON', 'SET IMPLICIT_TRANSACTIONS OFF', 'SET LOCK_TIMEOUT 60000' ), $this->executed );
            $this->assertSame( $options['query'], $this->prepared );
            $lines = file( $target, FILE_IGNORE_NEW_LINES );
            $this->assertSame( '{"Id":2,"Name":"Oolong"}', $lines[1], $case[1] );
            $this->assertSame( 'Grüner Tee', json_decode( $lines[0], true )['Name'] );
        }
        // the driver an administrator chooses, and the encryption
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'SqlServerDriver', 'odbc' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'OdbcDriver', 'ODBC Driver 17 for SQL Server' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'SqlServerEncrypt', 'disabled' );
        $this->assertStringStartsWith( 'odbc:Driver={ODBC Driver 17 for SQL Server};', expImportSqlSource::connection( $options )[0] );
        $this->assertStringContainsString( 'Encrypt=no;', expImportSqlSource::connection( $options )[0] );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'OdbcDriver', 'x};Server=evil' );
        try
        {
            expImportSqlSource::connection( $options );
            $this->fail( 'an ODBC driver name with ; and } is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'OdbcDriver', $e->getMessage() );
        }
        // the checks before connecting are the same as for MySQL
        foreach ( array( array( 'host' => '10.1.2.3' ), array( 'database' => 'Shop;Server=evil' ), array( 'query' => 'EXEC sp_who' ) ) as $change )
        {
            try
            {
                expImportSqlSource::fetch( array_merge( $options, $change ), $this->dir . '/x.ndjson', 0 );
                $this->fail( 'refused: ' . json_encode( $change ) );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringStartsWith( 'SQL', $e->getMessage() );
            }
        }
    }

    public function testTheTestConnectionReportsColumnsOrAMaskedError(): void
    {
        putenv( 'SQ688_SQLX_PASSWORD=pw-of-the-sqlsrv-test' );
        $options = array( 'driver' => 'sqlsrv', 'host' => '203.0.113.5', 'database' => 'Shop', 'user' => 'reader', 'password' => 'env:SQ688_SQLX_PASSWORD',
                          'query' => 'SELECT [Id], [Name] FROM [dbo].[Products]' );
        expImportSqlSource::$environment = array( 'pdo' => array( 'sqlsrv' ) );
        expImportSqlSource::$pdoFactory = $this->factory( array( array( 'Id' => 1, 'Name' => 'Sencha' ), array( 'Id' => 2, 'Name' => 'Oolong' ) ), array( 'Id', 'Name' ) );
        $result = expImportSqlSource::test( $options );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( array( 'Id', 'Name' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'], 'only the first row is read' );
        // a server error that quotes the password: masked
        expImportSqlSource::$pdoFactory = function ()
        {
            throw new PDOException( "SQLSTATE[28000]: Login failed for user 'reader' with password pw-of-the-sqlsrv-test" );
        };
        $result = expImportSqlSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Login failed', $result['message'] );
        $this->assertStringNotContainsString( 'pw-of-the-sqlsrv-test', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_SQLX_PASSWORD]', $result['message'] );
        // a refused query is not run
        $result = expImportSqlSource::test( array_merge( $options, array( 'query' => 'SELECT 1; DROP TABLE x' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'one statement', $result['message'] );
    }

    public function testAMissingDriverNamesTheWorkersAndWhatTheHostNeeds(): void
    {
        $options = array( 'driver' => 'sqlsrv', 'host' => '203.0.113.5', 'database' => 'Shop', 'query' => 'SELECT 1' );
        expImportSqlSource::$environment = array( 'pdo' => array( 'mysql', 'odbc' ), 'oci8' => false, 'odbc' => array( 'PostgreSQL' ) );
        expImportSqlSource::$pdoFactory = function ()
        {
            throw new LogicException( 'no connection may be made without a driver' );
        };
        $result = expImportSqlSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'neither pdo_sqlsrv', $result['message'] );
        $this->assertStringContainsString( 'msodbcsql18', $result['message'] );
        // the workers have run once and noted their PHP
        $drivers = expImportSqlSource::drivers();
        $this->assertFalse( $drivers['oracle']['available'] );
        $this->assertFalse( $drivers['sqlsrv']['available'] );
        $this->assertTrue( $drivers['mysql']['available'] );
        $this->assertSame( '', $drivers['oracle']['worker'] );
        expImportSqlSource::$environment = array( 'pdo' => array( 'mysql', 'odbc' ), 'oci8' => true, 'odbc' => array( 'FreeTDS' ) );
        expImportSqlSource::recordWorkerPhp();
        $this->assertFileExists( $this->dir . '/worker.json' );
        $worker = expImportSqlSource::workerPhp();
        $this->assertSame( array( 'oci8', 'PDO_ODBC (FreeTDS)' ), array( $worker['drivers']['oracle'], $worker['drivers']['sqlsrv'] ) );
        // and the web server's PHP has neither
        expImportSqlSource::$environment = array( 'pdo' => array( 'mysql' ), 'oci8' => false, 'odbc' => null );
        $drivers = expImportSqlSource::drivers();
        $this->assertSame( array( false, 'yes' ), array( $drivers['oracle']['available'], $drivers['oracle']['worker'] ) );
        $this->assertSame( 'yes', $drivers['sqlsrv']['worker'] );
        $message = expImportSqlSource::test( array( 'driver' => 'oracle', 'host' => '203.0.113.5', 'database' => 'ORCL', 'query' => 'SELECT 1 FROM dual' ) )['message'];
        $this->assertStringContainsString( 'neither oci8 nor pdo_oci', $message );
        $this->assertStringContainsString( 'Runs still work', $message );
        $this->assertStringContainsString( PHP_BINARY, $message );
    }

    public function testTheSchemaAndExamplesOfTheNewDrivers(): void
    {
        $schema = expImportSqlSource::schema();
        $this->assertArrayHasKey( 'oracle', $schema['driver']['values'] );
        $this->assertArrayHasKey( 'sqlsrv', $schema['driver']['values'] );
        foreach ( expImportSqlSource::examples() as $example )
            $this->assertNull( expImportSqlSource::checkQuery( $example['options']['query'], $example['options']['driver'] ), $example['label'] );
    }

    /**
     * A PDO factory that hands out a mocked connection giving $rows, and notes what it was given.
     *
     * @param array $rows
     * @param string[] $columns
     * @return callable
     */
    protected function factory( array $rows, array $columns )
    {
        $this->executed = array();
        $this->prepared = '';
        return function ( $dsn, $user, $password, array $attributes ) use ( $rows, $columns )
        {
            $this->connected = array( $dsn, $user, $password, $attributes );
            $statement = $this->createMock( PDOStatement::class );
            $statement->method( 'execute' )->willReturn( true );
            $statement->method( 'columnCount' )->willReturn( count( $columns ) );
            $statement->method( 'getColumnMeta' )->willReturnCallback( function ( $i ) use ( $columns )
            {
                return array( 'name' => $columns[$i] );
            } );
            $statement->method( 'fetch' )->willReturnOnConsecutiveCalls( ...array_merge( $rows, array( false ) ) );
            $statement->method( 'closeCursor' )->willReturn( true );
            $pdo = $this->createMock( PDO::class );
            $pdo->method( 'exec' )->willReturnCallback( function ( $sql )
            {
                $this->executed[] = $sql;
                return 0;
            } );
            $pdo->method( 'prepare' )->willReturnCallback( function ( $sql ) use ( $statement )
            {
                $this->prepared = $sql;
                return $statement;
            } );
            return $pdo;
        };
    }

    /** @return object a stand-in for an OCI-Lob */
    protected function lob( string $text )
    {
        return new class( $text )
        {
            /** @var string */
            private $text;

            public function __construct( string $text )
            {
                $this->text = $text;
            }

            public function load(): string
            {
                return $this->text;
            }
        };
    }
}
