<?php

use PHPUnit\Framework\TestCase;

/**
 * The Azure Cosmos DB source against a scripted account (expImportPlatformHttp::$transport): the signature of the
 * master key (HMAC-SHA256, checked against the example of the REST API's documentation), a resource token, only the
 * query of a container with a SELECT without parameters or user-defined functions, the paging with continuation
 * tokens, partition keys, the system properties, Test connection with a masked error, secrets by name, hosts and
 * the driver list.
 */
class CosmosDbSourceTest extends TestCase
{
    /** The resource token the scripted account accepts. */
    const GOOD_TOKEN = 'type=resource&ver=1.0&sig=resource-token-secret==';

    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers (name => value), body (decoded) */
    protected $requests = array();

    /** @var array[] the items of the container */
    protected $items = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-cosmostest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CosmosDbSourceSettings', 'AllowedHosts', array( 'shop.test.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        expImport::$now = 1775118600;
        putenv( 'SQ688_COSMOS_KEY=' . base64_encode( 'cosmos-test-key-0123456789abcdef' ) );
        putenv( 'SQ688_COSMOS_TOKEN=' . self::GOOD_TOKEN );
        foreach ( array( 1, 2, 3, 4, 5 ) as $i )
            $this->items[] = array( 'id' => 'order-' . $i, 'customer' => array( 'id' => 'c-' . $i ), 'lines' => array( array( 'sku' => 'TEA-' . $i, 'qty' => $i ) ),
                                    'total' => $i * 10, '_rid' => 'rid' . $i, '_self' => 'dbs/x/colls/y/docs/' . $i, '_etag' => '"e' . $i . '"', '_attachments' => 'attachments/', '_ts' => 1775118600 + $i );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportSearchSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        expImport::$now = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'CosmosDbSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_COSMOS_KEY' );
        putenv( 'SQ688_COSMOS_TOKEN' );
    }

    /** The scripted account: the query of the container orders, paged with continuation tokens. */
    public function answer( $method, $url, array $lines, $body )
    {
        $headers = array();
        foreach ( $lines as $line )
            $headers[strtolower( (string)strstr( $line, ':', true ) )] = trim( substr( $line, strpos( $line, ':' ) + 1 ) );
        $data = json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $json = function ( $status, $value, array $more = array() ) { return array( 'status' => $status, 'headers' => $more, 'body' => json_encode( $value ) ); };
        if ( $method !== 'POST' || parse_url( $url, PHP_URL_PATH ) !== '/dbs/shop/colls/orders/docs' )
            return $json( 404, array( 'code' => 'NotFound', 'message' => "Resource Not Found\r\nActivityId: 1" ) );
        $key = base64_decode( (string)getenv( 'SQ688_COSMOS_KEY' ) );
        $expected = expImportCosmosDbSource::authorization( 'POST', 'docs', 'dbs/shop/colls/orders', $headers['x-ms-date'], base64_encode( $key ) );
        if ( $headers['authorization'] !== $expected && $headers['authorization'] !== rawurlencode( self::GOOD_TOKEN ) )
            return $json( 401, array( 'code' => 'Unauthorized', 'message' => 'The input authorization token cannot serve the request: ' . rawurldecode( $headers['authorization'] ) . ' (' . $headers['authorization'] . ')' ) );
        if ( strpos( $data['query'], 'ORDER BY' ) !== false && !isset( $headers['x-ms-documentdb-partitionkey'] ) )
            return $json( 400, array( 'code' => 'BadRequest', 'message' => 'The provided cross partition query can not be directly served by the gateway.' ) );
        $from = isset( $headers['x-ms-continuation'] ) ? (int)substr( $headers['x-ms-continuation'], 5 ) : 0;
        $size = (int)$headers['x-ms-max-item-count'];
        $page = array_slice( $this->items, $from, $size );
        $more = $from + $size < count( $this->items ) ? array( 'x-ms-continuation: next-' . ( $from + $size ) ) : array();
        return $json( 200, array( '_rid' => 'coll', 'Documents' => $page, '_count' => count( $page ) ), array_merge( array( 'HTTP/1.1 200 OK' ), $more ) );
    }

    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportCosmosDbSource::schema(), $given + array(
            'endpoint' => 'https://shop.test.invalid:443/', 'database' => 'shop', 'container' => 'orders', 'key_secret' => 'env:SQ688_COSMOS_KEY' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes, by id */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportCosmosDbSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportCosmosDbSource( array( 'file' => $file, 'key' => 'id' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportCosmosDbSource::schema() ) );
        $this->assertSame( 'nosql', expImportCosmosDbSource::group() );
        $this->assertSame( array( 'remote' ), expImportCosmosDbSource::origins() );
        $this->assertStringContainsString( 'MongoDB source', expImportCosmosDbSource::description() );
        foreach ( expImportCosmosDbSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( expImportCosmosDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportCosmosDbSource::checkQuery( $values['query'] ), $example['label'] );
            $this->assertNull( expImportSecrets::get( $example['options']['key_secret'] ) );
        }
        $records = array();
        foreach ( ( new expImportCosmosDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/cosmosdb-orders.ndjson', 'key' => 'id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'order-5001', 'order-5002', 'order-5003' ), array_keys( $records ) );
        $this->assertSame( 'c-23', $records['order-5002']['customer.id'] );
    }

    public function testTheSignatureOfTheMasterKey(): void
    {
        // the value computed apart from the source (HMAC-SHA256 of "post\ndocs\ndbs/shop/colls/orders\n<date lowercase>\n\n")
        $this->assertSame( 'type%3Dmaster%26ver%3D1.0%26sig%3DHqoooJIOc78QyFvIZ3cSMiceVZ2k36Q5Nl21CM08xAg%3D',
                           expImportCosmosDbSource::authorization( 'POST', 'docs', 'dbs/shop/colls/orders', 'Thu, 02 Apr 2026 08:30:00 GMT',
                                                                   'Y29zbW9zLXRlc3Qta2V5LTAxMjM0NTY3ODlhYmNkZWY=' ) );
        $this->assertSame( 'Thu, 02 Apr 2026 08:30:00 GMT', expImportCosmosDbSource::date() );
        $this->expectException( expImportSourceException::class );
        expImportCosmosDbSource::authorization( 'POST', 'docs', 'dbs/a/colls/b', 'x', 'type=resource&ver=1.0&sig=not base64!' );
    }

    public function testOnlyASelectWithoutParametersOrFunctions(): void
    {
        foreach ( array( 'SELECT * FROM c', "SELECT c.id, c.title FROM c WHERE c.status = 'update me; please'", 'SELECT VALUE c.id FROM c;',
                         'SELECT TOP 10 c.id FROM c ORDER BY c._ts DESC', 'SELECT c.id FROM c JOIN l IN c.lines WHERE l.qty > 1',
                         'SELECT REPLACE(c.name, "a", "b") AS n FROM c', 'SELECT c["update"] FROM c' ) as $sql )
            $this->assertNull( expImportCosmosDbSource::checkQuery( $sql ), $sql );
        foreach ( array( 'DELETE FROM c', 'UPDATE c SET c.a = 1', 'SELECT * FROM c WHERE c.id = @id', 'SELECT udf.tax(c.total) FROM c',
                         'SELECT * FROM c; SELECT * FROM d', 'INSERT INTO c VALUES (1)', "SELECT * FROM c WHERE c.a = 'open", '', 'EXEC sp',
                         'SELECT c.update FROM c' ) as $sql )
            $this->assertNotNull( expImportCosmosDbSource::checkQuery( $sql ), $sql );
        foreach ( array( array( 'query' => 'SELECT udf.x(c) FROM c' ), array( 'database' => '../x' ), array( 'container' => 'orders/docs' ),
                         array( 'partition_key' => '["a", "b"]' ), array( 'endpoint' => 'https://shop.test.invalid/dbs' ) ) as $given )
            $this->assertFalse( expImportCosmosDbSource::test( $given + array( 'endpoint' => 'https://shop.test.invalid', 'database' => 'shop', 'container' => 'orders',
                                                                                 'key_secret' => 'env:SQ688_COSMOS_KEY' ) )['ok'], json_encode( $given ) );
        $this->assertSame( array(), $this->requests, 'a refused query or name sends nothing' );
    }

    public function testPagingWithContinuationTokens(): void
    {
        $records = $this->fetched( $this->options( array( 'max_item_count' => 2, 'query' => "SELECT * FROM c WHERE c.status = 'open'" ) ) );
        $this->assertSame( array( 'order-1', 'order-2', 'order-3', 'order-4', 'order-5' ), array_keys( $records ) );
        $this->assertSame( array( 'id' => 'order-3', 'customer.id' => 'c-3', 'lines' => '[{"sku":"TEA-3","qty":3}]', 'total' => 30, '_ts' => 1775118603 ), $records['order-3'],
                           'flattened, without the system properties but _ts' );
        $this->assertCount( 3, $this->requests );
        $first = $this->requests[0];
        $this->assertSame( 'https://shop.test.invalid:443/dbs/shop/colls/orders/docs', $first['url'] );
        $this->assertSame( array( 'query' => "SELECT * FROM c WHERE c.status = 'open'", 'parameters' => array() ), $first['body'] );
        $this->assertSame( 'True', $first['headers']['x-ms-documentdb-isquery'] );
        $this->assertSame( 'application/query+json', $first['headers']['content-type'] );
        $this->assertSame( 'True', $first['headers']['x-ms-documentdb-query-enablecrosspartition'] );
        $this->assertSame( '2018-12-31', $first['headers']['x-ms-version'] );
        $this->assertSame( 'Thu, 02 Apr 2026 08:30:00 GMT', $first['headers']['x-ms-date'] );
        $this->assertStringStartsWith( 'type%3Dmaster%26ver%3D1.0%26sig%3D', $first['headers']['authorization'] );
        $this->assertArrayNotHasKey( 'x-ms-continuation', $first['headers'] );
        $this->assertSame( 'next-2', $this->requests[1]['headers']['x-ms-continuation'] );
        // system properties on request, the sample limit, a resource token, one partition
        $this->assertSame( 'rid1', $this->fetched( $this->options( array( 'system_fields' => true ) ) )['order-1']['_rid'] );
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'max_item_count' => 2 ) ), 3 ) );
        $this->assertCount( 2, $this->requests );
        $this->requests = array();
        $records = $this->fetched( $this->options( array( 'auth' => 'resource_token', 'key_secret' => 'env:SQ688_COSMOS_TOKEN', 'partition_key' => '"c-1"',
                                                          'query' => 'SELECT * FROM c ORDER BY c.id' ) ) );
        $this->assertCount( 5, $records );
        $this->assertSame( '["c-1"]', $this->requests[0]['headers']['x-ms-documentdb-partitionkey'] );
        $this->assertArrayNotHasKey( 'x-ms-documentdb-query-enablecrosspartition', $this->requests[0]['headers'] );
        $this->assertSame( rawurlencode( 'type=resource&ver=1.0&sig=resource-token-secret==' ), $this->requests[0]['headers']['authorization'] );
        $this->assertSame( array( 'value' => 'x' ), expImportCosmosDbSource::record( 'x' ) );
    }

    public function testTestConnectionAndMasking(): void
    {
        $result = expImportCosmosDbSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( 'id', 'customer.id', 'lines', 'total', '_ts' ), $result['columns'] );
        $this->assertSame( '1', $this->requests[0]['headers']['x-ms-max-item-count'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        // the gateway's refusal of a cross-partition ORDER BY, with its reason
        $result = expImportCosmosDbSource::test( $this->options( array( 'query' => 'SELECT * FROM c ORDER BY c.total' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'BadRequest: The provided cross partition query', $result['message'] );
        // a token the account refuses and quotes: masked
        putenv( 'SQ688_COSMOS_TOKEN=type=resource&ver=1.0&sig=wrong' );
        $result = expImportCosmosDbSource::test( $this->options( array( 'auth' => 'resource_token', 'key_secret' => 'env:SQ688_COSMOS_TOKEN' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringNotContainsString( 'sig=wrong', $result['message'] );
        $this->assertStringNotContainsString( 'sig%3Dwrong', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_COSMOS_TOKEN]', $result['message'] );
        // a container that is not there, a secret that is not set
        $this->assertStringContainsString( 'NotFound: Resource Not Found', expImportCosmosDbSource::test( $this->options( array( 'container' => 'gone' ) ) )['message'] );
        $this->requests = array();
        $result = expImportCosmosDbSource::test( $this->options( array( 'key_secret' => 'sq688_not_set' ) ) );
        $this->assertStringContainsString( "'sq688_not_set' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testHostsAndDrivers(): void
    {
        foreach ( array( 'https://127.0.0.1:8081', 'https://10.0.0.5', 'https://shop.test.invalid/?x=1', 'https://a:b@shop.test.invalid' ) as $endpoint )
            $this->assertFalse( expImportCosmosDbSource::test( array( 'endpoint' => $endpoint, 'database' => 'shop', 'container' => 'orders',
                                                                      'key_secret' => 'env:SQ688_COSMOS_KEY' ) )['ok'], $endpoint );
        $this->assertSame( array(), $this->requests );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CosmosDbSourceSettings', 'AllowedHosts', array( '127.0.0.1' ) );
        $this->assertTrue( expImportCosmosDbSource::test( array( 'endpoint' => 'https://127.0.0.1:8081', 'database' => 'shop', 'container' => 'orders',
                                                                 'key_secret' => 'env:SQ688_COSMOS_KEY' ) )['ok'], 'the emulator when it is listed' );
        expImportSearchSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportCosmosDbSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        foreach ( $drivers['https'] as $key => $value )
            $this->assertContains( $key, array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) );
    }
}
