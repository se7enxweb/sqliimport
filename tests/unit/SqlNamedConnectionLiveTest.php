<?php

use PHPUnit\Framework\TestCase;

// run from an installation's PHPUnit, the extension's own autoloader is not there yet
require_once dirname( __DIR__ ) . '/bootstrap.php';

/**
 * The SQL source through a named connection to a real CockroachDB and YugabyteDB, opened by the kernel's data table
 * connections (expDataConnections, Exponential 6.0.15 and later) and their drivers (expCockroachDB, expYugabyteDB):
 * rows read in pages, the query refused before it reaches the database, the table left as it was.
 *
 * Needs an Exponential kernel loaded (run with the installation's PHPUnit and bootstrap, the extension in
 * extension/sqliimport) and the servers of the kernel's pgwire-live suite: EXP_TEST_PGWIRE_COCKROACHDB_HOST, _PORT,
 * _USER, _DATABASE and EXP_TEST_PGWIRE_YUGABYTEDB_HOST ... (database exp_pgwire_test by default). Skipped otherwise:
 *   EXP_TEST_PGWIRE_COCKROACHDB_HOST=127.0.0.1 php vendor/bin/phpunit extension/sqliimport/tests/unit/SqlNamedConnectionLiveTest.php
 */
class SqlNamedConnectionLiveTest extends TestCase
{
    public static function engineProvider()
    {
        return array( array( 'cockroachdb', 26257, 'root' ), array( 'yugabytedb', 5433, 'yugabyte' ) );
    }

    /** @return array|null the descriptor of the connection, null (and why) when there is no server for it */
    private function connection( $engine, $port, $user, &$why )
    {
        if ( !class_exists( 'expDataConnections' ) )
        {
            $why = 'no Exponential kernel (expDataConnections) loaded';
            return null;
        }
        $prefix = 'EXP_TEST_PGWIRE_' . strtoupper( $engine ) . '_';
        $host = getenv( $prefix . 'HOST' );
        if ( $host === false || $host === '' )
        {
            $why = "no server: set EXP_TEST_PGWIRE_COCKROACHDB_HOST or EXP_TEST_PGWIRE_YUGABYTEDB_HOST (and _PORT, _USER, _DATABASE)";
            return null;
        }
        $value = function ( $key, $default ) use ( $prefix ) {
            $v = getenv( $prefix . $key );
            return $v === false || $v === '' ? $default : $v;
        };
        return array( 'engine' => $engine, 'server' => $host, 'port' => (int)$value( 'PORT', $port ), 'user' => $value( 'USER', $user ),
                      'database' => $value( 'DATABASE', 'exp_pgwire_test' ), 'secret' => '', 'origin' => 'settings',
                      'options' => array( 'sslmode' => 'disable' ) );
    }

    protected function tearDown(): void
    {
        if ( class_exists( 'expDataConnections' ) )
            expDataConnections::setConnections( null );
        expImportSqlSource::$connections = null;
    }

    // a loop, not a data provider: PHPUnit 9 to 13 read the same test
    public function testRowsThroughTheKernelsConnection(): void
    {
        $tested = array();
        $why = '';
        foreach ( self::engineProvider() as list( $engine, $port, $user ) )
        {
            $descriptor = $this->connection( $engine, $port, $user, $why );
            if ( $descriptor === null )
                continue;
            $this->rowsThroughTheKernelsConnection( $engine, $descriptor );
            $tested[] = $engine;
        }
        if ( !$tested )
            $this->markTestSkipped( $why );
    }

    private function rowsThroughTheKernelsConnection( $engine, array $descriptor )
    {
        $id = 'sqli_live_' . $engine;
        expDataConnections::setConnections( array( $id => $descriptor ) );
        $db = expDataConnections::db( $id );
        $table = 'exp_sqli_live_teas';
        $db->query( "DROP TABLE IF EXISTS $table" );
        $db->query( "CREATE TABLE $table ( id INT4 NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL, note VARCHAR(100) )" );
        $db->query( "INSERT INTO $table ( id, name, note ) SELECT g, 'Tea ' || g, CASE WHEN g = 7 THEN 'it''s; -- not a comment' END FROM generate_series( 1, 1203 ) g" );
        $target = tempnam( sys_get_temp_dir(), 'sqli-live' );
        try
        {
            expImportSqlSource::fetch( array( 'connection' => $id, 'query' => "SELECT id, name, note FROM $table WHERE name <> 'x' /* every tea */ ORDER BY id" ), $target, 0 );
            $lines = file( $target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $this->assertCount( 1203, $lines, 'three pages of 500' );
            $this->assertEquals( array( 'id' => 1, 'name' => 'Tea 1', 'note' => null ), json_decode( $lines[0], true ) );
            $this->assertSame( "it's; -- not a comment", json_decode( $lines[6], true )['note'] );

            $result = expImportSqlSource::test( array( 'connection' => $id, 'query' => "SELECT name FROM $table WHERE id = 2" ) );
            $this->assertTrue( $result['ok'], (string)( $result['message'] ?? '' ) );
            $this->assertSame( array( 'name' ), $result['columns'] );

            foreach ( array( "DELETE FROM $table", "SELECT 1 /* /* */ ; DELETE FROM $table; -- */", "SELECT 1; DROP TABLE $table",
                             "WITH d AS ( DELETE FROM $table RETURNING id ) SELECT * FROM d", "SELECT crdb_internal.force_error( 'a', 'b' )" ) as $query )
            {
                $result = expImportSqlSource::test( array( 'connection' => $id, 'query' => $query ) );
                $this->assertFalse( $result['ok'], $query );
            }
            $rows = $db->arrayQuery( "SELECT count(*) AS n FROM $table" );
            $this->assertSame( 1203, (int)$rows[0]['n'], 'the refused queries changed nothing' );
        }
        finally
        {
            @unlink( $target );
            $db->query( "DROP TABLE IF EXISTS $table" );
        }
    }
}
