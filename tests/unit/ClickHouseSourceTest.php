<?php

use PHPUnit\Framework\TestCase;

/**
 * The ClickHouse source against a scripted HTTP interface (expImportPlatformHttp::$transport): the query check with
 * its table functions, readonly and the limits in every request, user and password as headers, paging with LIMIT and
 * OFFSET, JSONEachRow flattened, Test connection with masked errors, hosts and drivers().
 */
class ClickHouseSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, body */
    protected $requests = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-clickhousetest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'ClickHouseSourceSettings', 'AllowedHosts', array( 'clickhouse.example.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_CH_PASSWORD=pw-Click-Secret-31' );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportClickHouseSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowedHosts', 'AllowedTableFunctions', 'MaxResultRows' ) as $name )
            $ini->removeSetting( 'ClickHouseSourceSettings', $name );
        putenv( 'SQ688_CH_PASSWORD' );
    }

    /** The scripted server: version() and 5 rows paged by the LIMIT and OFFSET of the wrapper. */
    public function answer( $method, $url, array $headers, $body )
    {
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => (string)$body );
        if ( strpos( (string)$body, 'fail' ) !== false )
            return array( 'status' => 500, 'headers' => array(), 'body' => "Code: 60. DB::Exception: Table analytics.fail does not exist (password pw-Click-Secret-31). (UNKNOWN_TABLE)\n" );
        if ( strpos( (string)$body, 'version()' ) !== false )
            return array( 'status' => 200, 'headers' => array(), 'body' => "{\"version\":\"24.8.4.13\"}\n" );
        $limit = preg_match( '/LIMIT (\d+)(?: OFFSET (\d+))?\s*FORMAT/', (string)$body, $m ) ? (int)$m[1] : 5;
        $offset = isset( $m[2] ) ? (int)$m[2] : 0;
        $lines = '';
        for ( $i = $offset + 1; $i <= min( 5, $offset + $limit ); $i++ )
            $lines .= json_encode( array( 'id' => $i, 'path' => '/p/' . $i, 'visits' => (string)( 1000 * $i ), 'meta' => array( 'device' => 'mobile', 'tags' => array( 'x' ) ) ) ) . "\n";
        return array( 'status' => 200, 'headers' => array(), 'body' => $lines );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportClickHouseSource::schema(), $given + array(
            'base_url' => 'https://clickhouse.example.invalid:8443', 'database' => 'analytics', 'user' => 'reader', 'password_secret' => 'env:SQ688_CH_PASSWORD',
            'query' => 'SELECT id, path, visits, meta FROM page_views ORDER BY id' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportClickHouseSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportClickHouseSource( array( 'file' => $file, 'key' => 'id' ) ) )->records() as $record )
            $out[] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportClickHouseSource::schema() ) );
        $this->assertSame( 'nosql', expImportClickHouseSource::group() );
        foreach ( expImportClickHouseSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportClickHouseSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $n = 0;
        foreach ( ( new expImportClickHouseSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/clickhouse-page-views.ndjson', 'key' => 'id' ) ) )->records() as $record )
            $n++;
        $this->assertSame( 3, $n );
    }

    public function testOnlyASelectWithoutOutsideFunctionsPasses(): void
    {
        foreach ( array( 'SELECT 1', 'WITH t AS (SELECT 1 AS x) SELECT x FROM t', '(SELECT 1)', "SELECT 'INSERT INTO x', replace(path, 'a', 'b') FROM t",
                         'SELECT format(\'{} {}\', a, b) FROM t -- FORMAT CSV', 'SELECT * FROM numbers(10)', 'SELECT "file", `url` FROM t',
                         "SELECT \$\$ DROP TABLE x \$\$", 'SELECT 1;', 'SELECT "a\\" , url(1) , "' ) as $query )
            $this->assertNull( expImportClickHouseSource::checkQuery( $query ), $query );
        foreach ( array( 'INSERT INTO t VALUES (1)', 'ALTER TABLE t DELETE WHERE 1', 'DROP TABLE t', 'TRUNCATE TABLE t', 'OPTIMIZE TABLE t',
                         'SYSTEM DROP DNS CACHE', 'KILL QUERY WHERE 1', 'SET readonly = 0', 'GRANT ALL ON *.* TO x', 'SHOW TABLES',
                         'SELECT 1 FORMAT CSV', 'SELECT 1 SETTINGS readonly = 0', "SELECT 1 INTO OUTFILE 'x.csv'",
                         "SELECT * FROM url('https://example.invalid/x.csv', CSV)", "SELECT * FROM s3('https://bucket.example.invalid/x')",
                         "SELECT * FROM file('/etc/passwd', 'LineAsString')", "SELECT * FROM remote('10.0.0.1', db.t)", "SELECT * FROM mysql('h', 'd', 't', 'u', 'p')",
                         "SELECT * FROM executable('x.sh', TabSeparated, 'a String')", "SELECT * FROM postgresql('h', 'd', 't', 'u', 'p')",
                         'SELECT * FROM HDFS(\'hdfs://x\')', 'SELECT 1; DROP TABLE t', "SELECT \$x\$'\$x\$, url('https://example.invalid') AS u",
                         "SELECT 'it\\'s', 1 FROM t WHERE x = 'a", '' ) as $query )
            $this->assertNotNull( expImportClickHouseSource::checkQuery( $query ), $query );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'ClickHouseSourceSettings', 'AllowedTableFunctions', array( 's3' ) );
        $this->assertNull( expImportClickHouseSource::checkQuery( "SELECT * FROM s3('https://bucket.example.invalid/x')" ), 'an allowed table function' );
        $this->assertNotNull( expImportClickHouseSource::checkQuery( "SELECT * FROM url('https://example.invalid')" ) );
        try
        {
            $this->fetched( $this->options( array( 'query' => 'ALTER TABLE t DELETE WHERE 1' ) ) );
            $this->fail( 'a write is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'SELECT', $e->getMessage() );
        }
        $this->assertSame( array(), $this->requests, 'a refused query sends nothing' );
    }

    public function testPagingReadonlyAndHeaders(): void
    {
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'ClickHouseSourceSettings', 'MaxResultRows', 100000 );
        $records = $this->fetched( $this->options( array( 'page_size' => 2 ) ) );
        $this->assertCount( 5, $records );
        $this->assertCount( 3, $this->requests );
        $first = $this->requests[0];
        $this->assertSame( 'POST', $first['method'] );
        parse_str( (string)parse_url( $first['url'], PHP_URL_QUERY ), $params );
        $this->assertSame( array( 'database' => 'analytics', 'readonly' => '2', 'max_execution_time' => '60', 'max_result_rows' => '100000',
                                  'result_overflow_mode' => 'throw' ), $params );
        $this->assertStringNotContainsString( 'pw-Click', $first['url'], 'the password is never in the address' );
        $this->assertContains( 'X-ClickHouse-User: reader', $first['headers'] );
        $this->assertContains( 'X-ClickHouse-Key: pw-Click-Secret-31', $first['headers'] );
        $this->assertSame( "SELECT * FROM (\nSELECT id, path, visits, meta FROM page_views ORDER BY id\n) LIMIT 2 OFFSET 0\nFORMAT JSONEachRow", $first['body'] );
        $this->assertStringContainsString( 'LIMIT 2 OFFSET 4', $this->requests[2]['body'] );
        $this->assertSame( array( 'id' => 3, 'path' => '/p/3', 'visits' => '3000', 'meta.device' => 'mobile', 'meta.tags' => array( 'x' ) ), $records[2] );
        // readonly=1 sends no settings; no paging: the query as it is
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'readonly' => '1', 'paging' => 'none' ) ) ) );
        parse_str( (string)parse_url( $this->requests[0]['url'], PHP_URL_QUERY ), $params );
        $this->assertSame( array( 'database' => 'analytics', 'readonly' => '1' ), $params );
        $this->assertSame( "SELECT id, path, visits, meta FROM page_views ORDER BY id\nFORMAT JSONEachRow", $this->requests[0]['body'] );
        // the editor's sample
        $this->requests = array();
        $this->assertCount( 2, $this->fetched( $this->options( array() ), 2 ) );
        $this->assertCount( 1, $this->requests );
    }

    public function testTestConnectionHostsAndSecrets(): void
    {
        $result = expImportClickHouseSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'ClickHouse 24.8.4.13', $result['driver'] );
        $this->assertSame( array( 'id', 'path', 'visits', 'meta.device', 'meta.tags' ), $result['columns'] );
        $this->assertStringContainsString( ') LIMIT 1', $this->requests[1]['body'] );
        $result = expImportClickHouseSource::test( $this->options( array( 'query' => 'SELECT * FROM fail' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Code: 60', $result['message'] );
        $this->assertStringNotContainsString( 'pw-Click-Secret-31', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_CH_PASSWORD]', $result['message'] );
        $this->requests = array();
        $result = expImportClickHouseSource::test( $this->options( array( 'password_secret' => 'sq688_no_such_secret' ) ) );
        $this->assertStringContainsString( "'sq688_no_such_secret' is not set", $result['message'] );
        foreach ( array( 'http://127.0.0.1:8123', 'http://10.0.0.8:8123', 'https://clickhouse.example.invalid/?user=x' ) as $base )
            $this->assertFalse( expImportClickHouseSource::test( $this->options( array( 'base_url' => $base ) ) )['ok'], $base );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'ClickHouseSourceSettings', 'AllowedHosts', array( '10.0.0.8' ) );
        $this->assertTrue( expImportClickHouseSource::test( $this->options( array( 'base_url' => 'http://10.0.0.8:8123' ) ) )['ok'] );
    }

    public function testDrivers(): void
    {
        expImportClickHouseSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportClickHouseSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        $this->assertTrue( $drivers['https']['available'] );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['https'] );
    }
}
