<?php

use PHPUnit\Framework\TestCase;

/**
 * The SQL source: only a single SELECT passes the check, whatever is hidden in comments, strings and quoted names;
 * the example SQLite database is read into an NDJSON snapshot over a read-only connection; hosts, paths and secrets
 * are checked before anything connects.
 */
class SqlSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-sqltest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SourceSettings', 'AllowedPaths', array( dirname( __DIR__, 2 ) . '/share/examples' ) );
    }

    protected function tearDown(): void
    {
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( array( 'SourceSettings', 'AllowedPaths' ), array( 'SourceSettings', 'AllowPrivateHosts' ), array( 'SqlSourceSettings', 'AllowedHosts' ),
                         array( 'SqlSourceSettings', 'SiteDatabase' ) ) as $setting )
            $ini->removeSetting( $setting[0], $setting[1] );
    }

    public function testSelectsPass(): void
    {
        $ok = array(
            'SELECT 1',
            'select id, name from teas',
            "SELECT id, name FROM teas WHERE name = 'DROP TABLE teas; DELETE FROM x'",
            "SELECT id FROM teas WHERE note = 'it''s; INSERT'",
            "SELECT * FROM teas -- DELETE FROM teas\nWHERE id > 1",
            'SELECT * FROM teas /* UPDATE teas SET x = 1; */ WHERE id = 2',
            'SELECT * FROM teas;',
            "SELECT * FROM teas;  \n ",
            'WITH t AS ( SELECT id FROM teas ) SELECT * FROM t',
            '( SELECT id FROM teas ) UNION ( SELECT id FROM coffees )',
            'SELECT updated_at, created, deleted_flag, last_update FROM news',
            "SELECT REPLACE( name, 'a', 'b' ) AS name FROM teas",
            'SELECT "insert" FROM teas',
            'SELECT `update`, `delete` FROM teas',
            "SELECT * FROM t WHERE a = 'x\\' ; still in the string'",
        );
        foreach ( $ok as $query )
            $this->assertNull( expImportSqlSource::checkQuery( $query, 'mysql' ), $query );
        $this->assertNull( expImportSqlSource::checkQuery( 'SELECT $$ DROP TABLE x; $$ AS a', 'pgsql' ), 'a dollar-quoted text' );
        $this->assertNull( expImportSqlSource::checkQuery( 'SELECT $tag$ ; DELETE $tag$ AS a', 'pgsql' ) );
        $this->assertNull( expImportSqlSource::checkQuery( 'SELECT 1 /* outer /* inner; DROP */ still a comment; DROP */', 'pgsql' ), 'nested comments of PostgreSQL' );
        $this->assertNull( expImportSqlSource::checkQuery( 'SELECT [delete] FROM teas', 'sqlite' ) );
        $this->assertNull( expImportSqlSource::checkQuery( "SELECT E'it\\'s; DROP' AS a", 'pgsql' ) );
    }

    public function testEverythingElseIsRefused(): void
    {
        $refused = array(
            'mysql' => array(
                'DELETE FROM teas',
                'delete from teas',
                'DeLeTe FROM teas',
                '  UPDATE teas SET price = 0',
                'INSERT INTO teas VALUES ( 9 )',
                'DROP TABLE teas',
                'SELECT 1; DROP TABLE teas',
                'SELECT 1;DROP TABLE teas;',
                "SELECT 1; -- \nDROP TABLE teas",
                'SELECT 1 /*! ; DROP TABLE teas */',
                'SELECT /*+ MAX_EXECUTION_TIME(1) */ 1',
                'SELECT * INTO OUTFILE "/var/www/x.php" FROM teas',
                "SELECT * FROM teas INTO DUMPFILE '/tmp/x'",
                "SELECT LOAD_FILE( '/etc/passwd' )",
                "SELECT load_file('/etc/passwd')",
                'SELECT `LOAD_FILE`( 1 )',
                'SELECT SLEEP( 100 )',
                'SELECT BENCHMARK( 100000000, MD5( 1 ) )',
                'SELECT * FROM teas FOR UPDATE',
                'SELECT * FROM teas LOCK IN SHARE MODE',
                'SELECT @a := 1',
                'SELECT @@version',
                'WITH x AS ( SELECT 1 ) DELETE FROM teas',
                'REPLACE INTO teas VALUES ( 1 )',
                'SELECT 1 FROM teas WHERE REPLACE INTO',
                'CALL do_something()',
                'EXEC xp_cmdshell',
                'GRANT ALL ON *.* TO x',
                'TRUNCATE teas',
                'CREATE TABLE x ( a int )',
                'ALTER TABLE teas ADD c int',
                'MERGE INTO teas USING x ON 1 WHEN MATCHED THEN DELETE',
                'SHOW TABLES',
                'EXPLAIN SELECT 1',
                "SELECT 'unclosed",
                'SELECT 1 /* unclosed',
                'SELECT `unclosed',
                "SELECT 1 # comment\n; DROP TABLE teas",
                "SELECT 'a' \0 DROP",
                '',
                ';',
                '-- only a comment',
            ),
            'pgsql' => array(
                'SELECT 1; DROP TABLE teas',
                "COPY teas TO '/tmp/x'",
                "SELECT pg_read_file( '/etc/passwd' )",
                "SELECT pg_catalog.pg_read_file( '/etc/passwd' )",
                "SELECT \"pg_read_file\"( '/etc/passwd' )",
                'SELECT pg_sleep( 100 )',
                "SELECT set_config( 'default_transaction_read_only', 'off', false )",
                "SELECT nextval( 'teas_id_seq' )",
                "SELECT lo_import( '/etc/passwd' )",
                "SELECT dblink_exec( 'x', 'DROP TABLE teas' )",
                'WITH d AS ( DELETE FROM teas RETURNING * ) SELECT * FROM d',
                'SELECT * INTO new_table FROM teas',
                'SELECT $$ unclosed',
                "SELECT 1 # 2; DROP TABLE teas",
                "SELECT 'it\\'s; DROP TABLE teas; --'",
                'SELECT 1 /* outer /* inner */ ; DROP TABLE teas',
                'DO $$ BEGIN DELETE FROM teas; END $$',
            ),
            'sqlite' => array(
                "ATTACH DATABASE '/tmp/x.db' AS x",
                "SELECT 1; ATTACH DATABASE '/tmp/x.db' AS x",
                'PRAGMA writable_schema = 1',
                'SELECT load_extension( "x.so" )',
                "SELECT writefile( '/tmp/x', 'y' )",
                'SELECT [load_extension]( 1 )',
                'VACUUM INTO "/tmp/copy.db"',
            ),
        );
        foreach ( $refused as $driver => $queries )
            foreach ( $queries as $query )
                $this->assertNotNull( expImportSqlSource::checkQuery( $query, $driver ), "$driver: " . str_replace( "\0", '\0', $query ) );
        $this->assertStringContainsString( 'one statement', (string)expImportSqlSource::checkQuery( 'SELECT 1; DROP TABLE teas' ) );
        $this->assertStringContainsString( 'SELECT', (string)expImportSqlSource::checkQuery( 'DELETE FROM teas' ) );
    }

    public function testTheExampleDatabaseIsReadIntoASnapshot(): void
    {
        if ( !class_exists( 'PDO' ) || !in_array( 'sqlite', PDO::getAvailableDrivers(), true ) )
            $this->markTestSkipped( 'pdo_sqlite is not installed' );
        $database = dirname( __DIR__, 2 ) . '/share/examples/sql-teas.sqlite';
        $before = md5_file( $database );
        $target = $this->dir . '/source.ndjson';
        $options = array( 'driver' => 'sqlite', 'database' => $database, 'query' => "SELECT id, name, origin, kind, price FROM teas WHERE kind <> 'herbal' ORDER BY id", 'key' => 'id' );
        expImportSqlSource::fetch( $options, $target, 0 );
        $lines = file( $target, FILE_IGNORE_NEW_LINES );
        $this->assertCount( 4, $lines );
        $first = json_decode( $lines[0], true );
        // PHP 8.0 reads SQLite numbers as strings, 8.1 and later as numbers
        $this->assertEquals( array( 'id' => 1, 'name' => 'Morning Sencha', 'origin' => 'Japan', 'kind' => 'green', 'price' => 6.5 ), $first );
        // the records of the snapshot, as a run reads them
        $source = new expImportSqlSource( array( 'file' => $target, 'key' => 'id' ) );
        $refs = array();
        foreach ( $source->records() as $record )
            $refs[] = $record->ref;
        $this->assertSame( array( '1', '2', '3', '5' ), $refs );
        $this->assertSame( 4, $source->count() );
        // the editor's sample: the first rows only
        expImportSqlSource::fetch( $options, $target, 2 );
        $this->assertCount( 2, file( $target, FILE_IGNORE_NEW_LINES ) );
        $this->assertSame( $before, md5_file( $database ), 'the database is not changed' );
        $this->assertFileDoesNotExist( $target . '.part' );
    }

    public function testTheConnectionIsReadOnly(): void
    {
        if ( !class_exists( 'PDO' ) || !in_array( 'sqlite', PDO::getAvailableDrivers(), true ) )
            $this->markTestSkipped( 'pdo_sqlite is not installed' );
        $copy = $this->dir . '/copy.sqlite';
        copy( dirname( __DIR__, 2 ) . '/share/examples/sql-teas.sqlite', $copy );
        $pdo = expImportSqlSource::open( 'sqlite:' . $copy, '', '', 'sqlite', 10 );
        $this->assertSame( 5, (int)$pdo->query( 'SELECT count(*) FROM teas' )->fetchColumn() );
        $refused = 0;
        foreach ( array( 'DELETE FROM teas', "UPDATE teas SET name = 'x'", 'CREATE TABLE x ( a int )' ) as $write )
        {
            try
            {
                $pdo->exec( $write );
            }
            catch ( PDOException $e )
            {
                $refused++;
            }
        }
        $this->assertSame( 3, $refused, 'even a statement that passed no check cannot write' );
    }

    public function testARefusedQueryIsNotRun(): void
    {
        $this->expectException( expImportSourceException::class );
        $this->expectExceptionMessage( 'only one statement' );
        expImportSqlSource::fetch( array( 'driver' => 'sqlite', 'database' => dirname( __DIR__, 2 ) . '/share/examples/sql-teas.sqlite',
                                          'query' => 'SELECT 1; DELETE FROM teas' ), $this->dir . '/source.ndjson', 0 );
    }

    public function testConnectionChecks(): void
    {
        // 203.0.113.5 (a documentation address) stands for a public server, whatever this PHP counts as reserved
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'AllowedHosts', array( '203.0.113.5' ) );
        $cases = array(
            array( array( 'driver' => 'sqlite', 'database' => '/etc/passwd' ), 'AllowedPaths' ),
            array( array( 'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'crm' ), 'private or reserved' ),
            array( array( 'driver' => 'pgsql', 'host' => '10.1.2.3', 'database' => 'crm' ), 'AllowedHosts' ),
            array( array( 'driver' => 'mysql', 'host' => '', 'database' => 'crm' ), 'host and the database' ),
            array( array( 'driver' => 'mysql', 'host' => '203.0.113.5', 'database' => 'crm; host=evil' ), 'not allowed' ),
            array( array( 'driver' => 'mysql', 'host' => '203.0.113.5', 'database' => 'crm', 'password' => 'sq688_no_such_secret' ), "secret 'sq688_no_such_secret'" ),
            array( array( 'driver' => 'site' ), 'SiteDatabase=enabled' ),
        );
        foreach ( $cases as $case )
        {
            try
            {
                expImportSqlSource::connection( $case[0] );
                $this->fail( 'refused: ' . json_encode( $case[0] ) );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( $case[1], $e->getMessage(), json_encode( $case[0] ) );
            }
        }
        // a host an administrator trusts
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SqlSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        putenv( 'SQ688_SQL_TEST_PASSWORD=pw-of-the-test' );
        list( $dsn, $user, $password, $dialect, $refs ) = expImportSqlSource::connection( array( 'driver' => 'pgsql', 'host' => '10.1.2.3', 'port' => 0,
                                                                                                  'database' => 'crm', 'user' => 'reader', 'password' => 'env:SQ688_SQL_TEST_PASSWORD' ) );
        putenv( 'SQ688_SQL_TEST_PASSWORD' );
        $this->assertSame( 'pgsql:host=10.1.2.3;port=5432;dbname=crm', $dsn );
        $this->assertSame( array( 'reader', 'pw-of-the-test', 'pgsql', array( 'env:SQ688_SQL_TEST_PASSWORD' ) ), array( $user, $password, $dialect, $refs ) );
        $this->assertNull( expImportSourceFiles::refuseHost( 'DB.example.ORG', array( 'db.example.org' ) ), 'trusted hosts are compared case-insensitively' );
        $this->assertNotNull( expImportSourceFiles::refuseHost( 'evil host' ) );
        // addresses keep their rules (the host test is shared)
        $this->assertSame( 'only http and https addresses can be read', expImportSourceFiles::refuseUrl( 'ftp://example.org/x' ) );
        $this->assertStringContainsString( 'private or reserved', (string)expImportSourceFiles::refuseUrl( 'http://127.0.0.1/export.csv' ) );
        $this->assertStringContainsString( 'private or reserved', (string)expImportSourceFiles::refuseUrl( 'http://[::1]/export.csv' ) );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SourceSettings', 'AllowPrivateHosts', 'enabled' );
        $this->assertNull( expImportSourceFiles::refuseUrl( 'http://127.0.0.1/export.csv' ) );
    }

    public function testTheSchemaAndExamples(): void
    {
        $schema = expImportSqlSource::schema();
        $this->assertSame( 'secret', $schema['password']['type'] );
        $this->assertTrue( $schema['query']['required'] );
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'query' => 'SELECT 1', 'password' => 'my real password!' ) );
        $this->assertArrayHasKey( 'password', $errors, 'a password itself is not accepted, only the name of a secret' );
        foreach ( expImportSqlSource::examples() as $example )
            $this->assertNull( expImportSqlSource::checkQuery( $example['options']['query'], $example['options']['driver'] ), $example['label'] );
    }
}
