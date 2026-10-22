<?php

use PHPUnit\Framework\TestCase;

/**
 * The Couchbase source against a scripted Query service (expImportPlatformHttp::$transport): readonly=true on every
 * request, only a single SELECT or WITH ... SELECT without parameters, the paging by LIMIT and OFFSET or by keyset
 * (the statement wrapped as SELECT RAW of a subquery), the flattening of objects and plain values, Test connection
 * with a masked error, secrets by name, hosts and the driver list.
 */
class CouchbaseSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: url, headers, body (decoded) */
    protected $requests = array();

    /** @var array[] the results of the scripted query */
    protected $results = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-cbtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CouchbaseSourceSettings', 'AllowedHosts', array( 'cb.test.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_CB_PASSWORD=pw-Couchbase-Secret-9' );
        foreach ( array( 1, 2, 3, 4, 5 ) as $i )
            $this->results[] = array( 'sku' => 'TEA-' . $i, 'name' => "Tea $i", 'price' => $i + 0.5, 'stock' => array( 'store' => $i, 'warehouse' => 10 * $i ),
                                      'tags' => array( 'green', "t$i" ) );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportSearchSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->removeSetting( 'CouchbaseSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_CB_PASSWORD' );
    }

    /** The scripted Query service: LIMIT, OFFSET and the keyset condition of the wrapped statement. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( parse_url( $url, PHP_URL_PATH ) !== '/query/service' )
            return $json( 404, array( 'errors' => array( array( 'code' => 0, 'msg' => 'no such endpoint' ) ) ) );
        $statement = (string)$data['statement'];
        if ( strpos( $statement, 'nosuchbucket' ) !== false )
            return $json( 404, array( 'status' => 'fatal', 'errors' => array( array( 'code' => 12003, 'msg' => 'Keyspace not found: nosuchbucket (user reader with pw-Couchbase-Secret-9)' ) ) ) );
        $rows = $this->results;
        if ( strpos( $statement, 'SELECT RAW 1' ) !== false )
            $rows = array( 1 );
        if ( isset( $data['$after'] ) )
            $rows = array_values( array_filter( $rows, function ( $r ) use ( $data ) { return strcmp( $r['sku'], $data['$after'] ) > 0; } ) );
        if ( preg_match( '/LIMIT (\d+)(?: OFFSET (\d+))?$/', $statement, $m ) && strpos( $statement, 'exp_page' ) !== false )
            $rows = array_slice( $rows, isset( $m[2] ) ? (int)$m[2] : 0, (int)$m[1] );
        return $json( 200, array( 'requestID' => 'r-1', 'results' => $rows, 'status' => 'success', 'metrics' => array( 'resultCount' => count( $rows ) ) ) );
    }

    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportCouchbaseSource::schema(),
                                                                    $given + array( 'base_url' => 'https://cb.test.invalid:18093', 'statement' => 'SELECT p.* FROM shop p ORDER BY p.sku' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes, by sku */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportCouchbaseSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportCouchbaseSource( array( 'file' => $file, 'key' => 'sku' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportCouchbaseSource::schema() ) );
        $this->assertSame( 'nosql', expImportCouchbaseSource::group() );
        $this->assertSame( array( 'remote' ), expImportCouchbaseSource::origins() );
        foreach ( expImportCouchbaseSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( expImportCouchbaseSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportCouchbaseSource::checkStatement( $values['statement'] ), $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportCouchbaseSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/couchbase-products.ndjson', 'key' => 'sku' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'TEA-301', 'TEA-302', 'TEA-303' ), array_keys( $records ) );
        $this->assertSame( 64, $records['TEA-302']['stock.warehouse'] );
    }

    public function testOnlyASelectWithoutParametersPasses(): void
    {
        $ok = array(
            'SELECT p.* FROM shop p',
            "select p.name from `shop`.inventory.products p where p.note = 'DELETE me; then UPDATE' order by p.name;",
            "WITH cheap AS (SELECT RAW x.sku FROM shop x WHERE x.price < 5) SELECT p.* FROM shop p WHERE p.sku IN cheap",
            'SELECT RAW META(p).id FROM shop p -- a comment that says DROP',
            'SELECT p.`update`, p.`set` FROM shop p /* INSERT */',
            'SELECT "a \\" quote" AS x FROM shop',
        );
        foreach ( $ok as $sql )
            $this->assertNull( expImportCouchbaseSource::checkStatement( $sql ), $sql );
        $refused = array(
            'INSERT INTO shop (KEY, VALUE) VALUES ("k", {})', 'UPSERT INTO shop VALUES ("k", {})', 'UPDATE shop SET a = 1', 'DELETE FROM shop',
            'MERGE INTO shop USING x ON KEY x.id WHEN MATCHED THEN DELETE', 'CREATE INDEX i ON shop(a)', 'DROP INDEX shop.i', 'ALTER INDEX i ON shop WITH {}',
            'GRANT query_select ON shop TO reader', 'REVOKE query_select ON shop FROM reader', 'EXECUTE FUNCTION f()', 'INFER shop', 'PREPARE p FROM SELECT 1',
            'EXPLAIN SELECT * FROM shop', 'BEGIN WORK', 'SELECT * FROM shop; DELETE FROM shop', 'SELECT CURL("https://example.invalid") FROM shop',
            'SELECT * FROM system:user_info', 'SELECT * FROM shop WHERE a = $a', 'SELECT * FROM shop WHERE a = ?', 'SELECT * FROM shop WHERE a = \'open',
            'SELECT * FROM (UPDATE shop SET a = 1 RETURNING *) x', 'WITH x AS (SELECT 1)', '', 'ADVISE SELECT * FROM shop', 'BUILD INDEX ON shop(i)',
            'SELECT * FROM shop /* not closed', 'CREATE FUNCTION f() LANGUAGE JAVASCRIPT AS "x"',
        );
        foreach ( $refused as $sql )
            $this->assertNotNull( expImportCouchbaseSource::checkStatement( $sql ), $sql );
        $result = expImportCouchbaseSource::test( $this->options( array( 'statement' => 'DELETE FROM shop' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'only a SELECT', $result['message'] );
        $this->assertSame( array(), $this->requests, 'a refused statement is never sent' );
    }

    public function testThePagingWrapsTheStatement(): void
    {
        $this->assertSame( "SELECT RAW exp_page FROM (SELECT p.* FROM shop p\n) AS exp_page LIMIT 10 OFFSET 20",
                           expImportCouchbaseSource::pageStatement( 'SELECT p.* FROM shop p;', 'offset', 10, 20 ) );
        $this->assertSame( "WITH c AS (SELECT RAW 1) SELECT RAW exp_page FROM (SELECT p.* FROM shop p WHERE 1 IN c\n) AS exp_page WHERE exp_page.`meta`.`id` > \$after ORDER BY exp_page.`meta`.`id` LIMIT 5",
                           expImportCouchbaseSource::pageStatement( 'WITH c AS (SELECT RAW 1) SELECT p.* FROM shop p WHERE 1 IN c', 'keyset', 5, 0, 'meta.id', true ) );
        $this->assertSame( 'SELECT 1 FROM shop', expImportCouchbaseSource::pageStatement( 'SELECT 1 FROM shop ; ', 'none', 5 ) );
        // offset
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'user' => 'reader', 'password_secret' => 'env:SQ688_CB_PASSWORD', 'query_context' => 'shop.inventory' ) ) );
        $this->assertSame( array( 'TEA-1', 'TEA-2', 'TEA-3', 'TEA-4', 'TEA-5' ), array_keys( $records ) );
        $this->assertSame( 30, $records['TEA-3']['stock.warehouse'] );
        $this->assertSame( '["green","t3"]', $records['TEA-3']['tags'] );
        $this->assertCount( 3, $this->requests );
        foreach ( $this->requests as $request )
        {
            $this->assertSame( 'POST', $request['method'] );
            $this->assertTrue( $request['body']['readonly'], 'every request is read-only' );
            $this->assertSame( 'default:shop.inventory', $request['body']['query_context'] );
            $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pw-Couchbase-Secret-9' ), $request['headers'] );
        }
        $this->assertStringEndsWith( 'LIMIT 2 OFFSET 4', $this->requests[2]['body']['statement'] );
        $this->assertSame( 'not_bounded', $this->requests[0]['body']['scan_consistency'] );
        // keyset: after the last value of the field
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'page_size' => 2, 'paging' => 'keyset', 'keyset_field' => 'sku', 'scan_consistency' => 'request_plus' ) ) ) );
        $this->assertArrayNotHasKey( '$after', $this->requests[0]['body'] );
        $this->assertSame( 'TEA-2', $this->requests[1]['body']['$after'] );
        $this->assertSame( 'TEA-4', $this->requests[2]['body']['$after'] );
        $this->assertSame( 'request_plus', $this->requests[0]['body']['scan_consistency'] );
        // none: one request; the sample limit
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'page_size' => 2, 'paging' => 'none' ) ) ) );
        $this->assertCount( 1, $this->requests );
        $this->assertSame( 'SELECT p.* FROM shop p ORDER BY p.sku', $this->requests[0]['body']['statement'] );
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'page_size' => 2 ) ), 3 ) );
        $this->assertCount( 2, $this->requests );
        // a keyset field the results do not have
        $this->expectException( expImportSourceException::class );
        $this->fetched( $this->options( array( 'page_size' => 2, 'paging' => 'keyset', 'keyset_field' => 'missing' ) ) );
    }

    public function testRecordsTestConnectionAndMasking(): void
    {
        $this->assertSame( array( 'value' => 7 ), expImportCouchbaseSource::record( 7 ) );
        $this->assertSame( array( 'value' => '["a","b"]' ), expImportCouchbaseSource::record( array( 'a', 'b' ) ) );
        $this->assertSame( array( 'a.b' => 1, 'c' => 'x, y' ), expImportCouchbaseSource::record( array( 'a' => array( 'b' => 1 ), 'c' => array( 'x', 'y' ) ), 'join' ) );
        $result = expImportCouchbaseSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'env:SQ688_CB_PASSWORD' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( 'sku', 'name', 'price', 'stock.store', 'stock.warehouse', 'tags' ), $result['columns'] );
        $this->assertStringEndsWith( 'LIMIT 1 OFFSET 0', $this->requests[0]['body']['statement'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $result = expImportCouchbaseSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'env:SQ688_CB_PASSWORD',
                                                                         'statement' => 'SELECT x.* FROM nosuchbucket x' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( '12003: Keyspace not found', $result['message'] );
        $this->assertStringNotContainsString( 'pw-Couchbase-Secret-9', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_CB_PASSWORD]', $result['message'] );
        $this->requests = array();
        $result = expImportCouchbaseSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'sq688_not_set' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_not_set' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testHostsAndDrivers(): void
    {
        foreach ( array( 'http://10.1.2.3:8093', 'https://127.0.0.1:18093', 'https://cb.test.invalid:18093/?x=1', 'https://u:p@cb.test.invalid' ) as $base )
            $this->assertFalse( expImportCouchbaseSource::test( array( 'base_url' => $base, 'statement' => 'SELECT 1' ) )['ok'], $base );
        $this->assertSame( array(), $this->requests );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CouchbaseSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $this->assertTrue( expImportCouchbaseSource::test( array( 'base_url' => 'http://10.1.2.3:8093', 'statement' => 'SELECT RAW 1' ) )['ok'] );
        $this->assertSame( 'http://10.1.2.3:8093/query/service', $this->requests[0]['url'] );
        expImportSearchSource::$environment = array( 'allow_url_fopen' => false, 'openssl' => true );
        $drivers = expImportCouchbaseSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        $this->assertFalse( $drivers['https']['available'] );
        $this->assertStringContainsString( 'allow_url_fopen', $drivers['https']['needs'] );
    }
}
