<?php

use PHPUnit\Framework\TestCase;

/**
 * The SQL source through a named connection of every engine of the kernel's data table connections (Exponential
 * 6.0.15, expDataConnections::engineTypes()): each is read through the connection, in its engine's dialect of the
 * query check, and what that dialect must refuse is refused before the database sees it. The kernel's
 * expDataConnections is replaced by a resolver.
 */
class SqlNamedConnectionEnginesTest extends TestCase
{
    /** The engines of the kernel's connections that hold rows of a table (MongoDB is read with its own format) */
    const ENGINES = array( 'sqlite', 'mysql', 'postgresql', 'oracle', 'sqlserver', 'odbc', 'msaccess', 'firebird', 'duckdb',
                           'cockroachdb', 'yugabytedb', 'spanner', 'neon', 'supabase', 'aurorapostgresql', 'db2', 'informix' );

    /** @var array */
    private $queries = array();

    protected function setUp(): void
    {
        $test = $this;
        $db = new class( $test ) {
            /** @var SqlNamedConnectionEnginesTest */
            private $test;
            public function __construct( $test ) { $this->test = $test; }
            public function arrayQuery( $sql, array $params = array() )
            {
                $this->test->logQuery( $sql );
                return isset( $params['offset'] ) && $params['offset'] > 0 ? array() : array( array( 'id' => 1, 'name' => 'Tea' ) );
            }
        };
        expImportSqlSource::$connections = function ( $id ) use ( $db )
        {
            return in_array( $id, SqlNamedConnectionEnginesTest::ENGINES, true )
                ? array( array( 'identifier' => $id, 'engine' => $id, 'secret' => '' ), $db ) : null;
        };
    }

    protected function tearDown(): void
    {
        expImportSqlSource::$connections = null;
    }

    public function logQuery( $sql )
    {
        $this->queries[] = $sql;
    }

    // loops, not data providers: PHPUnit 9 to 13 read the same test (12 has no @dataProvider annotations)
    public function testEveryEngineIsReadThroughItsConnection(): void
    {
        foreach ( self::ENGINES as $engine )
            $this->engineIsReadThroughItsConnection( $engine );
    }

    private function engineIsReadThroughItsConnection( $engine )
    {
        $this->queries = array();
        $this->assertArrayHasKey( $engine, expImportSqlSource::CONNECTION_DIALECTS );
        $result = expImportSqlSource::test( array( 'connection' => $engine, 'query' => "SELECT id, name FROM teas WHERE name <> 'x' -- a comment" ) );
        $this->assertTrue( $result['ok'], $engine . ': ' . ( $result['message'] ?? '' ) );
        $this->assertSame( array( 'id', 'name' ), $result['columns'] );
        foreach ( array( 'DELETE FROM teas', 'SELECT 1; DROP TABLE teas', 'SELECT 1 /* x */; DELETE FROM teas', 'SELECT * INTO t2 FROM teas',
                         'SELECT nextval(1)', 'SELECT 1 FROM teas FOR UPDATE' ) as $query )
        {
            $result = expImportSqlSource::test( array( 'connection' => $engine, 'query' => $query ) );
            $this->assertFalse( $result['ok'], "$engine: $query" );
        }
        $this->assertCount( 1, $this->queries, 'a refused query never reaches the database' );
    }

    public static function refusalProvider()
    {
        return array(
            // the strict dialects: a comment the database might end earlier than a nesting reader would
            array( 'cockroachdb', "SELECT 1 /* /* */ ; DELETE FROM teas; -- */" ),
            array( 'duckdb', "SELECT 1 /* /* */ ; DELETE FROM teas; -- */" ),
            array( 'firebird', "SELECT 1 /* /* */ x */ FROM rdb\$database" ),
            array( 'db2', "SELECT 1 FROM sysibm.sysdummy1 /* /* */" ),
            array( 'odbc', "SELECT a[1] FROM t /* /* */" ),
            // statements of their own
            array( 'firebird', 'SELECT GEN_ID( g_teas, 1 ) FROM rdb$database' ),
            array( 'firebird', "SELECT RDB\$SET_CONTEXT( 'USER_SESSION', 'a', 'b' ) FROM rdb\$database" ),
            array( 'firebird', 'SELECT NEXT VALUE FOR g_teas FROM rdb$database' ),
            array( 'db2', "SELECT * FROM TABLE( SYSPROC.ADMIN_CMD( 'x' ) )" ),
            array( 'db2', 'SELECT 1 FROM sysibm.sysdummy1 WITH UR COMMIT' ),
            array( 'informix', "SELECT 1 { ; DELETE FROM teas }" ),
            array( 'informix', "SELECT sysadmin:task( 'drop database', 'x' ) FROM sysmaster:sysdual" ),
            array( 'informix', "SELECT task( 'onmode', 'k' ) FROM systables" ),
            array( 'cockroachdb', "SELECT crdb_internal.force_error( 'x', 'y' )" ),
            array( 'yugabytedb', "SELECT set_config( 'a', 'b', false )" ),
            array( 'neon', 'SELECT pg_read_file( $$/etc/passwd$$ )' ),
            array( 'odbc', 'SELECT 1 FROM t; SET x = 1' ),
            array( 'msaccess', "SELECT [a;b] FROM t; DELETE FROM t" ),
            // DuckDB reads files, URLs and settings from a query
            array( 'duckdb', "SELECT * FROM read_csv( '/etc/passwd' )" ),
            array( 'duckdb', "SELECT * FROM 'data.csv'" ),
            array( 'duckdb', 'SELECT * FROM "secret.parquet"' ),
            array( 'duckdb', "SELECT * FROM teas t JOIN 'other.csv' o ON o.id = t.id" ),
            array( 'duckdb', "SELECT * FROM teas, 'x.json'" ),
            array( 'duckdb', "SELECT * FROM ( SELECT 1 ), ( 'x.csv' )" ),
            array( 'duckdb', "SELECT getenv( 'HOME' )" ),
            array( 'duckdb', "SELECT * FROM glob( '/var/*' )" ),
            array( 'duckdb', "SELECT * FROM query( 'SELECT 1' )" ),
            array( 'duckdb', 'SELECT * FROM duckdb_secrets()' ),
            array( 'duckdb', 'SELECT * FROM sqlite_scan( $$x.db$$, $$t$$ )' ),
        );
    }

    public function testWhatTheDialectMustRefuse(): void
    {
        foreach ( self::refusalProvider() as list( $engine, $query ) )
        {
            $result = expImportSqlSource::test( array( 'connection' => $engine, 'query' => $query ) );
            $this->assertFalse( $result['ok'], "$engine: $query" );
        }
        $this->assertSame( array(), $this->queries, 'a refused query never reaches the database' );
    }

    public static function allowedProvider()
    {
        return array(
            array( 'duckdb', "SELECT id, 'herbal' AS kind, concat( name, ', ', origin ) FROM teas WHERE name IN ( 'a', 'b' )" ),
            array( 'duckdb', "WITH t AS ( SELECT * FROM teas ) SELECT * FROM t JOIN origins o ON o.id = t.origin AND o.name <> ''" ),
            array( 'duckdb', "SELECT * FROM teas WHERE note LIKE '%read_csv(%'" ),
            array( 'cockroachdb', 'SELECT $$a;b$$ AS t, E\'it\\\'s\' AS u FROM teas' ),
            array( 'firebird', "SELECT FIRST 10 id, \"Name\" FROM teas WHERE note = 'it''s'" ),
            array( 'informix', "SELECT FIRST 10 id, system_name FROM teas WHERE note = '{x}'" ),
            array( 'msaccess', 'SELECT [tea name], [price] FROM [tea list]' ),
            array( 'db2', 'SELECT id FROM teas FETCH FIRST 10 ROWS ONLY' ),
            array( 'tidb', "SELECT id FROM teas WHERE name = 'a\\'b'" ),
        );
    }

    public function testWhatTheDialectAllows(): void
    {
        foreach ( self::allowedProvider() as list( $engine, $query ) )
            $this->assertNull( expImportSqlSource::checkQuery( $query, expImportSqlSource::CONNECTION_DIALECTS[$engine] ), "$engine: $query" );
    }

    /** The kernel's own list of engines, when the kernel is there: every engine of a table is readable */
    public function testEveryEngineOfTheKernelHasADialect(): void
    {
        if ( !class_exists( 'expDataConnections' ) )
            $this->markTestSkipped( 'no Exponential kernel (expDataConnections) loaded' );
        foreach ( expDataConnections::engineTypes() as $engine => $type )
            if ( $type['kind'] === 'relational' )
                $this->assertArrayHasKey( $engine, expImportSqlSource::CONNECTION_DIALECTS, $engine );
    }
}
