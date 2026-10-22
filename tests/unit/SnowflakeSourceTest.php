<?php

use PHPUnit\Framework\TestCase;

/**
 * The Snowflake source against a scripted SQL API (expImportPlatformHttp::$transport): the key-pair JWT (claims,
 * fingerprint, RS256 signature), the query check, a running statement polled, partitions, the type mapping of
 * rowType, Test connection with masked errors, secrets by name, hosts and drivers().
 */
class SnowflakeSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, body (decoded) */
    protected $requests = array();

    /** @var string the PEM of the test key (encrypted with the passphrase) */
    protected static $pem = '';

    /** @var string the PEM of its public key */
    protected static $public = '';

    /** @var int how often the statement answers 202 before its result */
    protected $running = 0;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
        openssl_pkey_export( $key, $pem, 'pass-Phrase-Secret-9' );
        self::$pem = $pem;
        self::$public = openssl_pkey_get_details( $key )['key'];
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-snowflaketest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SnowflakeSourceSettings', 'AllowedHosts', array( 'exampleorg-shop.snowflakecomputing.com' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_SF_KEY=' . self::$pem );
        putenv( 'SQ688_SF_PASS=pass-Phrase-Secret-9' );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
        expImportSnowflakeSource::$sleeper = function ( $seconds ) {};
        expImportSnowflakeSource::$now = 1780000000;
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportSnowflakeSource::$sleeper = null;
        expImportSnowflakeSource::$now = null;
        expImportSnowflakeSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'SnowflakeSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_SF_KEY' );
        putenv( 'SQ688_SF_PASS' );
    }

    /** The scripted SQL API: a statement in two partitions of two rows, 202 while it runs. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $data && strpos( $data['statement'], 'FAIL' ) !== false )
            return $json( 422, array( 'code' => '002003', 'message' => "SQL compilation error: Object 'FAIL' does not exist (pass-Phrase-Secret-9)", 'sqlState' => '02000' ) );
        $meta = array( 'numRows' => 4, 'format' => 'jsonv2',
                       'rowType' => array( array( 'name' => 'ORDER_ID', 'type' => 'fixed', 'scale' => 0 ), array( 'name' => 'TOTAL', 'type' => 'fixed', 'scale' => 2 ),
                                           array( 'name' => 'RATE', 'type' => 'real' ), array( 'name' => 'PAID', 'type' => 'boolean' ),
                                           array( 'name' => 'DAY', 'type' => 'date' ), array( 'name' => 'AT_NTZ', 'type' => 'timestamp_ntz' ),
                                           array( 'name' => 'AT_TZ', 'type' => 'timestamp_tz' ), array( 'name' => 'AT', 'type' => 'time' ),
                                           array( 'name' => 'ADDRESS', 'type' => 'object' ), array( 'name' => 'ITEMS', 'type' => 'array' ),
                                           array( 'name' => 'NOTE', 'type' => 'text' ) ),
                       'partitionInfo' => array( array( 'rowCount' => 2 ), array( 'rowCount' => 2 ) ) );
        $row = function ( $i ) {
            return array( (string)( 1000 + $i ), '48.50', '0.25', $i % 2 ? 'true' : 'false', '20514', '1772445600.123000000', '1772445600.000000000 1500',
                          '36000.500000000', '{"city": "Example Town", "zip": "12345"}', '[1, 2]', null );
        };
        if ( $method === 'POST' )
        {
            if ( $this->running > 0 )
                return $json( 202, array( 'statementHandle' => '01b2-handle', 'message' => 'Asynchronous execution in progress.' ) );
            return $json( 200, array( 'statementHandle' => '01b2-handle', 'resultSetMetaData' => $meta, 'data' => array( $row( 1 ), $row( 2 ) ) ) );
        }
        if ( strpos( $url, 'partition=1' ) !== false )
            return $json( 200, array( 'data' => array( $row( 3 ), $row( 4 ) ) ) );
        if ( --$this->running > 0 )
            return $json( 202, array( 'statementHandle' => '01b2-handle' ) );
        return $json( 200, array( 'statementHandle' => '01b2-handle', 'resultSetMetaData' => $meta, 'data' => array( $row( 1 ), $row( 2 ) ) ) );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportSnowflakeSource::schema(), $given + array(
            'account' => 'exampleorg-shop', 'user' => 'import_reader', 'private_key_secret' => 'env:SQ688_SF_KEY', 'passphrase_secret' => 'env:SQ688_SF_PASS',
            'warehouse' => 'REPORTING_WH', 'database' => 'SHOP', 'role' => 'IMPORT_READ', 'query' => 'SELECT * FROM ORDERS ORDER BY ORDER_ID' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportSnowflakeSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportSnowflakeSource( array( 'file' => $file, 'key' => 'ORDER_ID' ) ) )->records() as $record )
            $out[] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportSnowflakeSource::schema() ) );
        $this->assertSame( 'nosql', expImportSnowflakeSource::group() );
        foreach ( expImportSnowflakeSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportSnowflakeSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportSnowflakeSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/snowflake-orders.ndjson', 'key' => 'ORDER_ID' ) ) )->records() as $record )
            $records[] = $record->data;
        $this->assertCount( 3, $records );
        $this->assertSame( '48.50', $records[0]['TOTAL'] );
    }

    public function testOnlyReadingStatementsPass(): void
    {
        foreach ( array( 'SELECT 1', 'WITH t AS (SELECT 1 AS X) SELECT X FROM t', 'SHOW TABLES IN SCHEMA SHOP.PUBLIC', 'DESCRIBE TABLE ORDERS', 'DESC TABLE ORDERS',
                         "SELECT 'DELETE FROM x' AS T, \"UPDATE\" FROM ORDERS -- DROP TABLE x", 'SELECT GET(ITEMS, 0), $1 FROM ORDERS',
                         "SELECT \$\$ it's INSERT \$\$", 'SELECT * FROM ORDERS ORDER BY ORDER_ID DESC;' ) as $query )
            $this->assertNull( expImportSnowflakeSource::checkQuery( $query ), $query );
        foreach ( array( 'INSERT INTO ORDERS VALUES (1)', 'UPDATE ORDERS SET X = 1', 'DELETE FROM ORDERS', 'MERGE INTO A USING B ON 1 = 1 WHEN MATCHED THEN DELETE',
                         'CREATE TABLE X AS SELECT 1', 'COPY INTO @stage FROM ORDERS', 'CALL MY_PROC()', 'EXECUTE IMMEDIATE \'DROP TABLE X\'',
                         'USE ROLE ACCOUNTADMIN', 'SET X = 1', 'SELECT SYSTEM$CANCEL_ALL_QUERIES(1)', 'SELECT 1; DROP TABLE X', 'GRANT ROLE A TO USER B',
                         'SELECT 1 // x' . "\n" . '; DELETE FROM X', "SELECT 'unclosed", 'PUT file:///x @stage', 'ALTER SESSION SET QUERY_TAG = 1', '' ) as $query )
            $this->assertNotNull( expImportSnowflakeSource::checkQuery( $query ), $query );
    }

    public function testTheKeyPairToken(): void
    {
        $jwt = expImportSnowflakeSource::jwt( 'exampleorg-shop', 'import_reader', self::$pem, 'pass-Phrase-Secret-9' );
        list( $head, $payload, $signature ) = explode( '.', $jwt );
        $decode = function ( $part ) { return base64_decode( strtr( $part, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $part ) % 4 ) % 4 ) ); };
        $this->assertSame( array( 'alg' => 'RS256', 'typ' => 'JWT' ), json_decode( $decode( $head ), true ) );
        $der = base64_decode( (string)preg_replace( '/-----[^-]+-----|\s+/', '', self::$public ) );
        $fingerprint = 'SHA256:' . base64_encode( hash( 'sha256', $der, true ) );
        $this->assertSame( array( 'iss' => 'EXAMPLEORG-SHOP.IMPORT_READER.' . $fingerprint, 'sub' => 'EXAMPLEORG-SHOP.IMPORT_READER',
                                  'iat' => 1780000000, 'exp' => 1780003540 ), json_decode( $decode( $payload ), true ) );
        $this->assertSame( 1, openssl_verify( $head . '.' . $payload, $decode( $signature ), self::$public, OPENSSL_ALGO_SHA256 ) );
        $this->assertSame( 'XY12345', expImportSnowflakeSource::accountOf( 'xy12345.eu-central-1' ), 'a locator without its region' );
        // a key on one line with \n, and a wrong passphrase
        $this->assertNotSame( '', expImportSnowflakeSource::jwt( 'a', 'b', str_replace( "\n", '\n', self::$pem ), 'pass-Phrase-Secret-9' ) );
        try
        {
            expImportSnowflakeSource::jwt( 'a', 'b', self::$pem, 'wrong' );
            $this->fail( 'a wrong passphrase' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'cannot be read', $e->getMessage() );
        }
    }

    public function testPartitionsPollingAndTypes(): void
    {
        $this->running = 2;
        $records = $this->fetched( $this->options( array() ) );
        $this->assertCount( 4, $records );
        $routes = array_map( function ( $r ) { return $r['method'] . ' ' . parse_url( $r['url'], PHP_URL_PATH ) . ( strpos( $r['url'], 'partition=' ) ? '?partition' : '' ); }, $this->requests );
        $this->assertSame( array( 'POST /api/v2/statements', 'GET /api/v2/statements/01b2-handle', 'GET /api/v2/statements/01b2-handle', 'GET /api/v2/statements/01b2-handle?partition' ), $routes );
        $first = $this->requests[0];
        $this->assertStringStartsWith( 'https://exampleorg-shop.snowflakecomputing.com/api/v2/statements?requestId=', $first['url'] );
        $this->assertContains( 'X-Snowflake-Authorization-Token-Type: KEYPAIR_JWT', $first['headers'] );
        $this->assertSame( 'SELECT * FROM ORDERS ORDER BY ORDER_ID', $first['body']['statement'] );
        $this->assertSame( array( 'MULTI_STATEMENT_COUNT' => '1' ), $first['body']['parameters'] );
        $this->assertSame( 'REPORTING_WH', $first['body']['warehouse'] );
        $this->assertSame( 'IMPORT_READ', $first['body']['role'] );
        $this->assertArrayNotHasKey( 'schema', $first['body'] );
        $this->assertSame( array( 'ORDER_ID' => '1003', 'TOTAL' => '48.50', 'RATE' => 0.25, 'PAID' => true, 'DAY' => '2026-03-02',
                                  'AT_NTZ' => '2026-03-02T10:00:00.123', 'AT_TZ' => '2026-03-02T11:00:00+01:00', 'AT' => '10:00:00.5',
                                  'ADDRESS.city' => 'Example Town', 'ADDRESS.zip' => '12345', 'ITEMS' => array( 1, 2 ), 'NOTE' => null ), $records[2] );
        $this->assertSame( '2026-03-02T10:00:00Z', expImportSnowflakeSource::value( '1772445600', 'timestamp_ltz' ) );
        $this->assertSame( '2026-03-02T05:00:00-05:00', expImportSnowflakeSource::value( '1772445600 1140', 'timestamp_tz' ) );
        $this->assertSame( 'not json', expImportSnowflakeSource::value( 'not json', 'variant' ) );
        // the editor's sample reads the first partition only
        $this->requests = array();
        $this->assertCount( 2, $this->fetched( $this->options( array() ), 2 ) );
        $this->assertCount( 1, $this->requests );
    }

    public function testTestConnectionHostsAndSecrets(): void
    {
        $result = expImportSnowflakeSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertContains( 'ADDRESS.city', $result['columns'] );
        $this->assertSame( "SELECT * FROM (\nSELECT * FROM ORDERS ORDER BY ORDER_ID\n) LIMIT 1", $this->requests[0]['body']['statement'] );
        $result = expImportSnowflakeSource::test( $this->options( array( 'query' => 'SELECT * FROM FAIL' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( '002003', $result['message'] );
        $this->assertStringNotContainsString( 'pass-Phrase-Secret-9', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_SF_PASS]', $result['message'] );
        $this->requests = array();
        $result = expImportSnowflakeSource::test( $this->options( array( 'private_key_secret' => 'sq688_no_such_key' ) ) );
        $this->assertStringContainsString( "'sq688_no_such_key' is not set", $result['message'] );
        $this->assertFalse( expImportSnowflakeSource::test( $this->options( array( 'base_url' => 'https://10.2.3.4' ) ) )['ok'] );
        $this->assertSame( array(), $this->requests, 'nothing is sent without the key or to a refused server' );
    }

    public function testDrivers(): void
    {
        expImportSnowflakeSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportSnowflakeSource::drivers();
        $this->assertSame( array( 'https' ), array_keys( $drivers ) );
        $this->assertTrue( $drivers['https']['available'] );
        $this->assertStringContainsString( 'signs', $drivers['https']['needs'] );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['https'] );
    }
}
