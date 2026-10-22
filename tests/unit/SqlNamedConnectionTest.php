<?php

use PHPUnit\Framework\TestCase;

/**
 * The SQL source through a named connection of Exponential 6.0.15 (Setup > Data tables > Connections): the same query
 * check in the connection's dialect, paged reads, a limit, the site's database only when allowed, secrets masked,
 * and the source's own credential options not used. The kernel's expDataConnections is replaced by a resolver.
 */
class SqlNamedConnectionTest extends TestCase
{
    /** @var array */
    private $queries = array();

    protected function setUp(): void
    {
        $rows = array();
        for ( $i = 1; $i <= 1203; $i++ )
            $rows[] = array( 'id' => $i, 'name' => 'Tea ' . $i, 'price' => 2.5 );
        $test = $this;
        $db = new class( $rows, $test ) {
            /** @var array */
            private $rows;
            /** @var SqlNamedConnectionTest */
            private $test;
            public function __construct( array $rows, $test ) { $this->rows = $rows; $this->test = $test; }
            public function arrayQuery( $sql, array $params = array() )
            {
                $this->test->logQuery( $sql, $params );
                if ( strpos( $sql, 'broken' ) !== false )
                    throw new RuntimeException( 'access denied for user shop using password s3cret-conn-pw' );
                return array_slice( $this->rows, isset( $params['offset'] ) ? (int)$params['offset'] : 0, isset( $params['limit'] ) ? (int)$params['limit'] : null );
            }
        };
        expImportSqlSource::$connections = function ( $id ) use ( $db )
        {
            $connections = array(
                'shop' => array( 'identifier' => 'shop', 'engine' => 'mysql', 'secret' => 'env:SQ688_CONN_PW' ),
                'archive' => array( 'identifier' => 'archive', 'engine' => 'postgresql', 'secret' => '' ),
                'docs' => array( 'identifier' => 'docs', 'engine' => 'mongodb', 'secret' => '' ),
                'site' => array( 'identifier' => 'site', 'engine' => 'mysql', 'secret' => '' ),
            );
            return isset( $connections[$id] ) ? array( $connections[$id], $db ) : null;
        };
        putenv( 'SQ688_CONN_PW=s3cret-conn-pw' );
    }

    protected function tearDown(): void
    {
        expImportSqlSource::$connections = null;
        putenv( 'SQ688_CONN_PW' );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'SqlSourceSettings', 'SiteDatabase' );
    }

    public function logQuery( $sql, array $params )
    {
        $this->queries[] = array( $sql, $params );
    }

    public function testRowsInPagesThroughTheConnection(): void
    {
        $target = tempnam( sys_get_temp_dir(), 'sq-conn' );
        try
        {
            // host, user and password of the source are not used with a connection
            expImportSqlSource::fetch( array( 'connection' => 'shop', 'driver' => 'pgsql', 'host' => 'ignored.example.invalid',
                                              'query' => 'SELECT id, name, price FROM teas ORDER BY id' ), $target, 0 );
            $lines = file( $target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $this->assertCount( 1203, $lines );
            $this->assertEquals( array( 'id' => '1', 'name' => 'Tea 1', 'price' => '2.5' ), json_decode( $lines[0], true ) );
            $this->assertSame( array( array( 'limit' => 500, 'offset' => 0 ), array( 'limit' => 500, 'offset' => 500 ), array( 'limit' => 500, 'offset' => 1000 ) ),
                               array_column( $this->queries, 1 ) );
        }
        finally
        {
            @unlink( $target );
        }
        $this->queries = array();
        $result = expImportSqlSource::test( array( 'connection' => 'archive', 'query' => 'SELECT id, name FROM teas' ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 'connection archive', $result['driver'] );
        $this->assertSame( array( 'id', 'name', 'price' ), $result['columns'] );
        $this->assertSame( array( array( 'limit' => 1, 'offset' => 0 ) ), array_column( $this->queries, 1 ), 'Test connection reads one row' );
    }

    public function testRefusals(): void
    {
        $cases = array(
            array( array( 'connection' => 'shop', 'query' => 'DELETE FROM teas' ), 'SELECT' ),
            array( array( 'connection' => 'shop', 'query' => 'SELECT 1; DROP TABLE teas' ), '' ),
            array( array( 'connection' => 'nope', 'query' => 'SELECT 1' ), "there is no connection 'nope'" ),
            array( array( 'connection' => 'Bad Name', 'query' => 'SELECT 1' ), 'is not the identifier' ),
            array( array( 'connection' => 'docs', 'query' => 'SELECT 1' ), 'mongodb database' ),
            array( array( 'connection' => 'site', 'query' => 'SELECT 1' ), 'SiteDatabase=enabled' ),
        );
        foreach ( $cases as $case )
        {
            $result = expImportSqlSource::test( $case[0] );
            $this->assertFalse( $result['ok'], json_encode( $case[0] ) );
            if ( $case[1] !== '' )
                $this->assertStringContainsString( $case[1], $result['message'], json_encode( $case[0] ) );
        }
        $this->assertSame( array(), $this->queries, 'a refused query never reaches the database' );
    }

    public function testTheSecretOfTheConnectionIsMasked(): void
    {
        $result = expImportSqlSource::test( array( 'connection' => 'shop', 'query' => 'SELECT broken FROM teas' ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringNotContainsString( 's3cret-conn-pw', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_CONN_PW]', $result['message'] );
    }

    public function testTheOptionIsInTheSchema(): void
    {
        $schema = expImportSqlSource::schema();
        $this->assertSame( 'string', $schema['connection']['type'] );
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertTrue( expImportSqlSource::connectionsAvailable() );
    }
}
