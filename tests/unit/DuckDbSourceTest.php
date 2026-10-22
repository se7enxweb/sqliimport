<?php

use PHPUnit\Framework\TestCase;

/**
 * The DuckDB source: the SELECT-only check with the words and functions of DuckDB, the files inside AllowedPaths[]
 * (patterns expanded, addresses only when allowed), the statements that prepare a session, paging with LIMIT and
 * OFFSET through a scripted runner, the duckdb program through a stand-in script (no shell, read-only, errors, time
 * and size limits), Test connection and the driver list.
 */
class DuckDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the jobs the runner was given */
    protected $jobs = array();

    /** @var array[] the rows of the scripted query */
    protected $rows = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-duckdbtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        mkdir( $this->dir . '/exports' );
        foreach ( array( 'exports/2026-01.parquet', 'exports/2026-02.parquet', 'teas.csv', 'notes.ndjson', 'warehouse.duckdb', 'odd.bin' ) as $name )
            file_put_contents( $this->dir . '/' . $name, 'x' );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SourceSettings', 'AllowedPaths', array( $this->dir . '/exports', $this->dir ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        expImportDuckDbSource::$environment = array( 'binary' => '/usr/local/bin/duckdb', 'ffi' => false, 'library' => null );
        for ( $i = 1; $i <= 5; $i++ )
            $this->rows[] = array( 'id' => $i, 'name' => 'Tea ' . $i, 'price' => $i + 0.5 );
        expImportDuckDbSource::$runner = array( $this, 'runJob' );
    }

    protected function tearDown(): void
    {
        expImportDuckDbSource::$runner = null;
        expImportDuckDbSource::$environment = null;
        expImportDuckDbSource::$command = null;
        expImportSqlSource::$workerFile = null;
        foreach ( array_merge( (array)glob( $this->dir . '/exports/*' ), (array)glob( $this->dir . '/*' ) ) as $file )
            if ( is_file( $file ) )
                unlink( $file );
        rmdir( $this->dir . '/exports' );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->removeSetting( 'SourceSettings', 'AllowedPaths' );
        foreach ( array( 'Sandbox', 'AllowRemoteFiles', 'AllowedHosts', 'QueryTimeout', 'MaxOutputMegabytes', 'Driver' ) as $name )
            $ini->removeSetting( 'DuckDbSourceSettings', $name );
        putenv( 'SQ688_DUCK_SECRET' );
    }

    /** The scripted runner: the rows of LIMIT and OFFSET of the job's query. */
    public function runJob( array $job ): array
    {
        $this->jobs[] = $job;
        if ( strpos( $job['query'], 'broken' ) !== false )
            throw new expImportSourceException( 'DuckDB: Catalog Error: Table with name broken does not exist!' );
        if ( preg_match( '/LIMIT (\d+) OFFSET (\d+)$/', $job['query'], $m ) )
            return array_slice( $this->rows, (int)$m[2], (int)$m[1] );
        return $this->rows;
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportDuckDbSource::schema(), $given );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return string the reason fetch() refuses the options */
    protected function refusal( array $given ): string
    {
        try
        {
            expImportDuckDbSource::fetch( $this->options( $given ), $this->dir . '/snapshot.ndjson', 0 );
        }
        catch ( expImportSourceException $e )
        {
            return $e->getMessage();
        }
        $this->fail( 'refused: ' . json_encode( $given ) );
    }

    public function testSchemaAndExamples(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportDuckDbSource::schema() ) );
        $this->assertSame( 'data', expImportDuckDbSource::group() );
        $this->assertSame( array( 'remote' ), expImportDuckDbSource::origins() );
        foreach ( expImportDuckDbSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportDuckDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $this->assertFileExists( dirname( __DIR__, 2 ) . '/share/examples/duckdb-teas.csv' );
    }

    public function testSelectsPass(): void
    {
        $ok = array(
            'SELECT * FROM data',
            "SELECT id, name FROM data WHERE kind <> 'herbal' ORDER BY id;",
            "SELECT 'COPY data TO x; ATTACH' AS a FROM data",
            'WITH t AS ( SELECT id FROM data ) SELECT * FROM t',
            'SELECT list_transform( [1, 2], x -> x + 1 ) AS l',
            'SELECT * FROM customers JOIN orders USING ( id )',
            'SELECT "read_csv" FROM data',
            'SELECT strftime( updated, \'%Y\' ) AS year, count(*) AS n FROM data GROUP BY ALL',
        );
        foreach ( $ok as $query )
            $this->assertNull( expImportDuckDbSource::checkQuery( $query ), $query );
    }

    public function testEverythingElseIsRefused(): void
    {
        $refused = array(
            "COPY data TO '/var/www/x.csv'",
            "SELECT 1; COPY data TO 'x.csv'",
            "ATTACH '/var/lib/other.duckdb' AS o",
            "SELECT * FROM data; ATTACH 'x.db'",
            'INSTALL httpfs',
            'LOAD httpfs',
            'SELECT 1; LOAD httpfs',
            "SELECT * FROM read_csv( '/etc/passwd' )",
            "SELECT * FROM read_parquet( 's3://bucket/x.parquet' )",
            "SELECT * FROM glob( '/etc/*' )",
            "SELECT * FROM '/etc/passwd'",
            "SELECT * FROM data JOIN 'other.csv' USING ( id )",
            'SELECT * FROM "/etc/passwd.csv"',
            "SELECT getenv( 'HOME' )",
            "SELECT * FROM query( 'COPY data TO ''x''' )",
            'PRAGMA database_list',
            "SET enable_external_access = true",
            "SELECT 1; SET lock_configuration = false",
            'EXPORT DATABASE \'/tmp/x\'',
            'CREATE TABLE t AS SELECT 1',
            'SELECT $$ \' $$, 1; COPY data TO \'x\' --\'',
            "SELECT * FROM data /* ; */ ; DROP TABLE data",
            'DESCRIBE data',
            "SELECT * FROM sqlite_scan( 'x.db', 't' )",
        );
        foreach ( $refused as $query )
            $this->assertNotNull( expImportDuckDbSource::checkQuery( $query ), $query );
        $this->assertStringContainsString( 'only a SELECT', $this->refusal( array( 'paths' => $this->dir . '/teas.csv', 'query' => "COPY data TO 'x.csv'" ) ) );
        $this->assertSame( array(), $this->jobs, 'a refused query runs nothing' );
    }

    public function testFilesInsideAllowedPathsOnly(): void
    {
        list( $files, $reader ) = expImportDuckDbSource::files( array( 'paths' => array( $this->dir . '/exports/*.parquet' ) ) );
        $this->assertSame( array( $this->dir . '/exports/2026-01.parquet', $this->dir . '/exports/2026-02.parquet' ), $files );
        $this->assertSame( 'parquet', $reader );
        $this->assertSame( 'json', expImportDuckDbSource::files( array( 'paths' => $this->dir . '/notes.ndjson' ) )[1] );
        $this->assertSame( 'duckdb', expImportDuckDbSource::files( array( 'paths' => $this->dir . '/warehouse.duckdb' ) )[1] );
        $this->assertStringContainsString( 'not known', $this->refusal( array( 'paths' => $this->dir . '/odd.bin' ) ) );
        $this->assertStringContainsString( 'AllowedPaths', $this->refusal( array( 'paths' => '/etc/passwd', 'reader' => 'csv' ) ) );
        $this->assertStringContainsString( '..', $this->refusal( array( 'paths' => $this->dir . '/exports/../teas.csv' ) ) );
        $this->assertStringContainsString( 'matches no file', $this->refusal( array( 'paths' => $this->dir . '/exports/*.csv' ) ) );
        $this->assertStringContainsString( 'does not exist', $this->refusal( array( 'paths' => $this->dir . '/missing.csv' ) ) );
        $this->assertStringContainsString( 'one file', $this->refusal( array( 'paths' => $this->dir . "/warehouse.duckdb\n" . $this->dir . '/teas.csv', 'reader' => 'duckdb' ) ) );
        $this->assertStringContainsString( 'AllowRemoteFiles', $this->refusal( array( 'paths' => 'https://data.example.org/x.parquet' ) ) );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DuckDbSourceSettings', 'AllowRemoteFiles', 'enabled' );
        $this->assertStringContainsString( 'private', $this->refusal( array( 'paths' => 'http://10.0.0.7/x.parquet' ) ) );
        $this->assertStringContainsString( 'only http, https and s3', $this->refusal( array( 'paths' => 'ftp://example.org/x.parquet' ) ) );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DuckDbSourceSettings', 'AllowedHosts', array( '10.0.0.7' ) );
        $this->assertSame( array( 'http://10.0.0.7/x.parquet' ), expImportDuckDbSource::files( array( 'paths' => 'http://10.0.0.7/x.parquet' ) )[0] );
        $setup = expImportDuckDbSource::setup( array( 'http://10.0.0.7/x.parquet' ), 'parquet' );
        $this->assertContains( 'LOAD httpfs', $setup, 'an address needs httpfs, loaded on purpose (autoloading is off)' );
        $this->assertSame( array(), $this->jobs );
    }

    public function testTheSessionIsPreparedAndLocked(): void
    {
        $files = array( $this->dir . '/exports/2026-01.parquet', $this->dir . "/it's.parquet" );
        $setup = expImportDuckDbSource::setup( $files, 'parquet' );
        $list = "['" . $files[0] . "', '" . $this->dir . "/it''s.parquet']";
        $this->assertSame( array( 'SET autoinstall_known_extensions = false', 'SET autoload_known_extensions = false', 'SET allowed_paths = ' . $list,
                                  'SET enable_external_access = false', 'CREATE TEMPORARY VIEW data AS SELECT * FROM read_parquet(' . $list . ')',
                                  'SET lock_configuration = true' ), $setup );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DuckDbSourceSettings', 'Sandbox', 'disabled' );
        $setup = expImportDuckDbSource::setup( array( $this->dir . '/teas.csv' ), 'csv' );
        $this->assertStringContainsString( 'read_csv_auto(', $setup[2] );
        $this->assertSame( 'SET lock_configuration = true', end( $setup ) );
        $this->assertNotContains( 'SET enable_external_access = false', $setup );
        // a database file: opened read-only, no view
        expImportDuckDbSource::fetch( $this->options( array( 'paths' => $this->dir . '/warehouse.duckdb', 'query' => 'SELECT * FROM customers' ) ), $this->dir . '/snapshot.ndjson', 0 );
        $this->assertTrue( $this->jobs[0]['readonly'] );
        $this->assertSame( realpath( $this->dir . '/warehouse.duckdb' ), $this->jobs[0]['database'] );
        $this->assertSame( 'cli', $this->jobs[0]['driver'] );
        foreach ( $this->jobs[0]['setup'] as $statement )
            $this->assertStringNotContainsString( 'CREATE', $statement );
    }

    public function testPagingWithLimitAndOffset(): void
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportDuckDbSource::fetch( $this->options( array( 'paths' => $this->dir . '/teas.csv', 'page_size' => 2, 'query' => 'SELECT * FROM data ORDER BY id;' ) ), $file, 0 );
        $this->assertCount( 3, $this->jobs );
        $this->assertSame( "SELECT * FROM (\nSELECT * FROM data ORDER BY id\n) AS exp_import_page LIMIT 2 OFFSET 2", $this->jobs[1]['query'] );
        $this->assertFalse( $this->jobs[0]['readonly'] );
        $this->assertSame( '', $this->jobs[0]['database'], 'files are read by a database in memory' );
        $refs = array();
        foreach ( ( new expImportDuckDbSource( array( 'file' => $file, 'key' => 'id' ) ) )->records() as $record )
            $refs[] = $record->ref;
        $this->assertSame( array( '1', '2', '3', '4', '5' ), $refs );
        // the editor's sample: one query of at most its limit
        $this->jobs = array();
        expImportDuckDbSource::fetch( $this->options( array( 'paths' => $this->dir . '/teas.csv' ) ), $file, 3 );
        $this->assertCount( 1, $this->jobs );
        $this->assertStringEndsWith( 'LIMIT 3 OFFSET 0', $this->jobs[0]['query'] );
        $this->assertCount( 3, file( $file ) );
        // page size 0: the query as it is
        $this->jobs = array();
        expImportDuckDbSource::fetch( $this->options( array( 'paths' => $this->dir . '/teas.csv', 'page_size' => 0 ) ), $file, 0 );
        $this->assertSame( 'SELECT * FROM data', $this->jobs[0]['query'] );
        $this->assertCount( 5, file( $file ) );
    }

    public function testTestConnectionAndDrivers(): void
    {
        $result = expImportDuckDbSource::test( $this->options( array( 'paths' => $this->dir . '/teas.csv' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( array( 'id', 'name', 'price' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( 'the duckdb program', $result['driver'] );
        $this->assertStringEndsWith( 'LIMIT 1 OFFSET 0', $this->jobs[0]['query'] );
        $result = expImportDuckDbSource::test( $this->options( array( 'paths' => $this->dir . '/teas.csv', 'query' => 'SELECT * FROM broken' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'broken does not exist', $result['message'] );
        // no driver on this host: what it needs
        expImportDuckDbSource::$environment = array( 'binary' => null, 'ffi' => false, 'library' => null );
        $result = expImportDuckDbSource::test( $this->options( array( 'paths' => $this->dir . '/teas.csv' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'neither the duckdb program nor FFI', $result['message'] );
        $this->assertStringContainsString( 'duckdb.org', $result['message'] );
        $drivers = expImportDuckDbSource::drivers();
        $this->assertSame( array( 'cli', 'ffi' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertFalse( $drivers['cli']['available'] );
        // FFI with libduckdb, chosen when there is no program, or by the setting
        expImportDuckDbSource::$environment = array( 'binary' => '/usr/local/bin/duckdb', 'ffi' => true, 'library' => '/usr/local/lib/libduckdb.so' );
        $this->assertSame( 'cli', expImportDuckDbSource::driver() );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DuckDbSourceSettings', 'Driver', 'ffi' );
        $this->assertSame( 'ffi', expImportDuckDbSource::driver() );
        $this->assertTrue( expImportDuckDbSource::drivers()['ffi']['available'] );
        // the workers' PHP noted
        expImportSqlSource::recordWorkerPhp();
        $this->assertSame( 'yes', expImportDuckDbSource::drivers()['cli']['worker'] );
    }

    public function testTheDuckdbProgram(): void
    {
        expImportDuckDbSource::$runner = null;
        expImportDuckDbSource::$command = array( PHP_BINARY, __DIR__ . '/DuckDbSourceFakeCli.php' );
        putenv( 'SQ688_DUCK_SECRET=must-not-reach-duckdb' );
        $job = array( 'driver' => 'cli', 'database' => $this->dir . '/warehouse.duckdb', 'readonly' => true,
                      'setup' => array( 'SET autoload_known_extensions = false', 'SET lock_configuration = true' ),
                      'query' => 'SELECT * FROM customers', 'timeout' => 10, 'max_bytes' => 1048576 );
        $rows = expImportDuckDbSource::runCli( $job );
        $this->assertSame( '-json -bail -init /dev/null -readonly ' . $this->dir . '/warehouse.duckdb', $rows[0]['args'] );
        $this->assertSame( 3, $rows[0]['statements'] );
        $this->assertSame( 'SELECT * FROM customers', $rows[0]['last'] );
        $this->assertSame( '', $rows[0]['secret'], 'the program gets a reduced environment' );
        // an error of DuckDB
        try
        {
            expImportDuckDbSource::runCli( array( 'query' => 'SELECT FAIL' ) + $job );
            $this->fail( 'an error of the program' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'Binder Error', $e->getMessage() );
        }
        // the size limit
        try
        {
            expImportDuckDbSource::runCli( array( 'query' => 'SELECT HUGE' ) + $job );
            $this->fail( 'the size limit' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'MaxOutputMegabytes', $e->getMessage() );
        }
        // the time limit
        $started = microtime( true );
        try
        {
            expImportDuckDbSource::runCli( array( 'query' => 'SELECT SLEEP', 'timeout' => 1 ) + $job );
            $this->fail( 'the time limit' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'QueryTimeout', $e->getMessage() );
        }
        $this->assertLessThan( 4, microtime( true ) - $started, 'the program is stopped' );
        $this->assertSame( array(), expImportDuckDbSource::decodeOutput( '' ), 'a query without rows' );
        $this->assertSame( array( array( 'a' => 2 ) ), expImportDuckDbSource::decodeOutput( "[{\"a\":1}]\n[{\"a\":2}]" ), 'the last result set' );
    }
}
