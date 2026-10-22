<?php

use PHPUnit\Framework\TestCase;

/**
 * The Amazon DynamoDB source against a scripted DynamoDB API (expImportPlatformHttp::$transport): Signature Version 4
 * against AWS's published examples, only Scan and Query (DescribeTable for Test connection), paging with
 * LastEvaluatedKey, the mapping of every AttributeValue type, retries after throttling, Test connection with masked
 * errors, secrets by name, custom endpoints and the driver list. No request reaches AWS.
 */
class DynamoDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers (lines), body (decoded), target */
    protected $requests = array();

    /** @var array[] the items of the scripted table, in key order */
    protected $items = array();

    /** @var array[] answers to give before the scripted ones: array( status, body ) */
    protected $queued = array();

    /** @var bool whether DescribeTable is refused (AccessDeniedException) */
    protected $describeDenied = false;

    /** @var float[] the waits of expImportApiHttp::sleep() */
    protected $slept = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-dynamodbtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_AWS_SECRET=aws-Secret-Key-Very-42' );
        putenv( 'SQ688_AWS_TOKEN=aws-Session-Token-Very-43' );
        for ( $i = 1; $i <= 5; $i++ )
            $this->items[] = array( 'sku' => array( 'S' => 'tea-' . $i ), 'price' => array( 'N' => $i . '.50' ),
                                    'dimensions' => array( 'M' => array( 'weight_g' => array( 'N' => '100' ) ) ) );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
        expImportApiHttp::$sleeper = function ( $seconds ) { $this->slept[] = $seconds; };
        expImportAwsSigV4::$clock = function () { return gmmktime( 12, 0, 0, 4, 2, 2026 ); };
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportApiHttp::$sleeper = null;
        expImportAwsSigV4::$clock = null;
        expImportDynamoDbSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->removeSetting( 'DynamoDbSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_AWS_SECRET' );
        putenv( 'SQ688_AWS_TOKEN' );
    }

    /** The scripted API: Scan, Query and DescribeTable by X-Amz-Target. */
    public function answer( $method, $url, array $headers, $body )
    {
        $target = '';
        foreach ( $headers as $line )
            if ( stripos( $line, 'X-Amz-Target:' ) === 0 )
                $target = trim( substr( $line, 13 ) );
        $data = json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data, 'target' => $target );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $this->queued )
        {
            list( $status, $value ) = array_shift( $this->queued );
            return $json( $status, $value );
        }
        if ( $target === 'DynamoDB_20120810.DescribeTable' )
            return $this->describeDenied
                ? $json( 400, array( '__type' => 'com.amazon.coral.service#AccessDeniedException', 'message' => 'not authorized to perform dynamodb:DescribeTable' ) )
                : $json( 200, array( 'Table' => array( 'TableName' => $data['TableName'], 'TableStatus' => 'ACTIVE', 'ItemCount' => count( $this->items ) ) ) );
        if ( $target === 'DynamoDB_20120810.Scan' || $target === 'DynamoDB_20120810.Query' )
        {
            if ( $data['TableName'] === 'missing' )
                return $json( 400, array( '__type' => 'com.amazonaws.dynamodb.v20120810#ResourceNotFoundException',
                                          'message' => 'Requested resource not found (token aws-Session-Token-Very-43)' ) );
            $offset = 0;
            if ( isset( $data['ExclusiveStartKey']['sku']['S'] ) )
                $offset = (int)substr( $data['ExclusiveStartKey']['sku']['S'], 4 );
            $page = array_slice( $this->items, $offset, (int)$data['Limit'] );
            $answer = array( 'Items' => $page, 'Count' => count( $page ), 'ScannedCount' => count( $page ) );
            if ( $offset + count( $page ) < count( $this->items ) )
                $answer['LastEvaluatedKey'] = array( 'sku' => end( $page )['sku'] );
            return $json( 200, $answer );
        }
        return $json( 400, array( '__type' => 'com.amazon.coral.service#UnknownOperationException' ) );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportDynamoDbSource::schema(), $given + array(
            'region' => 'eu-central-1', 'access_key_id' => 'AKIAEXAMPLEEXAMPLE01', 'secret_key_secret' => 'env:SQ688_AWS_SECRET', 'table' => 'products' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportDynamoDbSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportDynamoDbSource( array( 'file' => $file, 'key' => 'sku' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    /** @return string the value of a header of a request */
    protected function header( array $request, $name ): string
    {
        return expImportPlatformHttp::header( $request['headers'], $name );
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportDynamoDbSource::schema() ) );
        $this->assertSame( 'nosql', expImportDynamoDbSource::group() );
        $this->assertSame( array( 'remote' ), expImportDynamoDbSource::origins() );
        foreach ( expImportDynamoDbSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportDynamoDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportDynamoDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/dynamodb-products.ndjson', 'key' => 'sku' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'tea-1001', 'tea-1002', 'tea-1003' ), array_keys( $records ) );
        $this->assertSame( 'tin', $records['tea-1001']['dimensions.box'] );
    }

    public function testSignatureVersion4MatchesTheExamplesOfAws(): void
    {
        // the signing key of the AWS documentation's example (IAM, 2012-02-15)
        $this->assertSame( 'f4780e2d9f65fa895f9c67b32ce1baf0b0d8a43505a000a1a9e090d414db404d',
                           bin2hex( expImportAwsSigV4::signingKey( 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '20120215', 'us-east-1', 'iam' ) ) );
        // "get-vanilla" of the AWS Signature Version 4 test suite
        $headers = expImportAwsSigV4::sign( 'GET', 'https://example.amazonaws.com/', array(), '', 'AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '',
                                            'us-east-1', 'service', gmmktime( 12, 36, 0, 8, 30, 2015 ) );
        $this->assertSame( '20150830T123600Z', $headers['X-Amz-Date'] );
        $this->assertSame( 'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/service/aws4_request, SignedHeaders=host;x-amz-date, '
                           . 'Signature=5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31', $headers['Authorization'] );
        // "get-vanilla-query-order-key-case": the query is sorted
        $headers = expImportAwsSigV4::sign( 'GET', 'https://example.amazonaws.com/?Param2=value2&Param1=value1', array(), '', 'AKIDEXAMPLE',
                                            'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '', 'us-east-1', 'service', gmmktime( 12, 36, 0, 8, 30, 2015 ) );
        $this->assertStringEndsWith( 'Signature=b97d918cfa904a5beff61c982a1b6f458b799221646efd99d3219ec94cdf2500', $headers['Authorization'] );
        // a session token is signed too
        $headers = expImportAwsSigV4::sign( 'POST', 'https://dynamodb.eu-central-1.amazonaws.com/', array( 'X-Amz-Target' => 'DynamoDB_20120810.Scan' ), '{}',
                                            'AKID', 'secret', 'token-1', 'eu-central-1', 'dynamodb', 0 );
        $this->assertSame( 'token-1', $headers['X-Amz-Security-Token'] );
        $this->assertStringContainsString( 'SignedHeaders=host;x-amz-date;x-amz-security-token;x-amz-target', $headers['Authorization'] );
    }

    public function testOnlyScanAndQueryAreSent(): void
    {
        foreach ( array( 'PutItem', 'UpdateItem', 'DeleteItem', 'BatchWriteItem', 'TransactWriteItems', 'ExecuteStatement', 'DeleteTable', 'scan' ) as $operation )
        {
            try
            {
                expImportDynamoDbSource::target( $operation );
                $this->fail( "$operation is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'refused', $e->getMessage() );
            }
        }
        $this->assertSame( 'DynamoDB_20120810.Query', expImportDynamoDbSource::target( 'Query' ) );
        list( $operation, $body ) = expImportDynamoDbSource::body( $this->options( array( 'operation' => 'query', 'index_name' => 'by_customer',
            'key_condition' => 'customer_id = :c', 'values' => '{":c": {"S": "c-1"}}', 'names' => '{"#s": "status"}', 'filter' => '#s = :c',
            'projection' => 'sku, #s', 'consistent_read' => true, 'scan_forward' => false ) ) );
        $this->assertSame( 'Query', $operation );
        $this->assertSame( array( 'TableName' => 'products', 'IndexName' => 'by_customer', 'KeyConditionExpression' => 'customer_id = :c',
                                  'ScanIndexForward' => false, 'FilterExpression' => '#s = :c', 'ProjectionExpression' => 'sku, #s',
                                  'ExpressionAttributeNames' => array( '#s' => 'status' ), 'ExpressionAttributeValues' => array( ':c' => array( 'S' => 'c-1' ) ),
                                  'ConsistentRead' => true ), $body );
        foreach ( array( array( 'operation' => 'query' ), array( 'key_condition' => 'a = :a' ), array( 'names' => '{"s": "status"}' ),
                         array( 'values' => '{":p": "published"}' ), array( 'values' => '{":p": {"X": "1"}}' ), array( 'names' => '["#a"]' ) ) as $given )
        {
            $result = expImportDynamoDbSource::test( $this->options( $given ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests, 'a refused import sends nothing' );
    }

    public function testScanPagesWithLastEvaluatedKeyAndSigns(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'session_token_secret' => 'env:SQ688_AWS_TOKEN' ) ) );
        $this->assertSame( array( 'tea-1', 'tea-2', 'tea-3', 'tea-4', 'tea-5' ), array_keys( $records ) );
        $this->assertCount( 3, $this->requests );
        $this->assertArrayNotHasKey( 'ExclusiveStartKey', $this->requests[0]['body'] );
        $this->assertSame( array( 'sku' => array( 'S' => 'tea-2' ) ), $this->requests[1]['body']['ExclusiveStartKey'] );
        $this->assertSame( array( 'sku' => array( 'S' => 'tea-4' ) ), $this->requests[2]['body']['ExclusiveStartKey'] );
        $first = $this->requests[0];
        $this->assertSame( 'POST', $first['method'] );
        $this->assertSame( 'https://dynamodb.eu-central-1.amazonaws.com/', $first['url'] );
        $this->assertSame( 'DynamoDB_20120810.Scan', $first['target'] );
        $this->assertSame( 2, $first['body']['Limit'] );
        $this->assertStringStartsWith( 'AWS4-HMAC-SHA256 Credential=AKIAEXAMPLEEXAMPLE01/20260402/eu-central-1/dynamodb/aws4_request, SignedHeaders=', $this->header( $first, 'Authorization' ) );
        $this->assertSame( '20260402T120000Z', $this->header( $first, 'X-Amz-Date' ) );
        $this->assertSame( 'aws-Session-Token-Very-43', $this->header( $first, 'X-Amz-Security-Token' ) );
        $this->assertSame( 'application/x-amz-json-1.0', $this->header( $first, 'Content-Type' ) );
        foreach ( $this->requests as $request )
            $this->assertStringNotContainsString( 'aws-Secret-Key-Very-42', implode( "\n", $request['headers'] ) . json_encode( $request['body'] ), 'the secret key is never sent' );
        // the editor's sample and the option limit stop early, and ask only for what is needed
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'page_size' => 2 ) ), 3 ) );
        $this->assertSame( array( 2, 1 ), array_map( function ( $r ) { return $r['body']['Limit']; }, $this->requests ) );
        $this->requests = array();
        $this->assertCount( 1, $this->fetched( $this->options( array( 'limit' => 1 ) ) ) );
        $this->assertCount( 1, $this->requests );
        // a Query
        $this->requests = array();
        $this->fetched( $this->options( array( 'operation' => 'query', 'key_condition' => 'sku = :s', 'values' => '{":s": {"S": "tea-1"}}' ) ) );
        $this->assertSame( 'DynamoDB_20120810.Query', $this->requests[0]['target'] );
        // MaxPages
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DynamoDbSourceSettings', 'MaxPages', 2 );
        try
        {
            $this->fetched( $this->options( array( 'page_size' => 1 ) ) );
            $this->fail( 'MaxPages stops a run' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'MaxPages', $e->getMessage() );
        }
        finally
        {
            eZINI::instance( 'sqliimport.ini' )->removeSetting( 'DynamoDbSourceSettings', 'MaxPages' );
        }
    }

    public function testTypeMapping(): void
    {
        $item = array(
            'sku' => array( 'S' => 'tea-1' ), 'price' => array( 'N' => '12.50' ), 'big' => array( 'N' => '123456789012345678901234567890' ),
            'photo' => array( 'B' => base64_encode( "\x00\x01binary" ) ), 'in_stock' => array( 'BOOL' => true ), 'gone' => array( 'NULL' => true ),
            'dimensions' => array( 'M' => array( 'weight_g' => array( 'N' => '100' ), 'box' => array( 'M' => array( 'kind' => array( 'S' => 'tin' ) ) ) ) ),
            'empty' => array( 'M' => array() ),
            'variants' => array( 'L' => array( array( 'M' => array( 'size' => array( 'S' => '50g' ) ) ), array( 'N' => '7' ), array( 'BOOL' => false ) ) ),
            'tags' => array( 'SS' => array( 'green', 'japan' ) ), 'sizes' => array( 'NS' => array( '50', '100' ) ), 'blobs' => array( 'BS' => array( 'AA==', 'AQ==' ) ),
        );
        $record = expImportDynamoDbSource::item( $item );
        $this->assertSame( 'tea-1', $record['sku'] );
        $this->assertSame( '12.50', $record['price'], 'a number stays its decimal text' );
        $this->assertSame( '123456789012345678901234567890', $record['big'] );
        $this->assertSame( base64_encode( "\x00\x01binary" ), $record['photo'] );
        $this->assertTrue( $record['in_stock'] );
        $this->assertNull( $record['gone'] );
        $this->assertSame( '100', $record['dimensions.weight_g'] );
        $this->assertSame( 'tin', $record['dimensions.box.kind'] );
        $this->assertSame( '{}', $record['empty'] );
        $this->assertSame( '[{"size":"50g"},"7",false]', $record['variants'] );
        $this->assertSame( 'green, japan', $record['tags'] );
        $this->assertSame( '50, 100', $record['sizes'] );
        $this->assertSame( 'AA==, AQ==', $record['blobs'] );
        $json = expImportDynamoDbSource::item( $item, 'json' );
        $this->assertSame( '["green","japan"]', $json['tags'] );
        $this->assertSame( 'green|japan', expImportDynamoDbSource::item( $item, 'join', '|' )['tags'] );
    }

    public function testThrottlingIsRetried(): void
    {
        $this->queued[] = array( 400, array( '__type' => 'com.amazonaws.dynamodb.v20120810#ProvisionedThroughputExceededException', 'message' => 'slow down' ) );
        $this->queued[] = array( 503, array( '__type' => 'com.amazonaws.dynamodb.v20120810#ServiceUnavailable' ) );
        $this->assertCount( 5, $this->fetched( $this->options( array( 'page_size' => 10 ) ) ) );
        $this->assertSame( array( 1.0, 2.0 ), $this->slept );
        $this->assertCount( 3, $this->requests );
        $this->assertNotSame( $this->header( $this->requests[0], 'Authorization' ), '', 'every try is signed' );
    }

    public function testTestConnection(): void
    {
        $result = expImportDynamoDbSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertSame( array( 'sku', 'price', 'dimensions.weight_g' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertStringContainsString( 'ACTIVE, about 5 items', $result['message'] );
        $this->assertSame( array( 'DynamoDB_20120810.DescribeTable', 'DynamoDB_20120810.Scan' ), array_column( $this->requests, 'target' ) );
        $this->assertSame( 1, $this->requests[1]['body']['Limit'] );
        // a key that may not describe the table still tests the scan
        $this->describeDenied = true;
        $result = expImportDynamoDbSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'] );
        $this->assertStringContainsString( 'DescribeTable is not allowed', $result['message'] );
        // an error that quotes the session token: masked
        $result = expImportDynamoDbSource::test( $this->options( array( 'table' => 'missing', 'session_token_secret' => 'env:SQ688_AWS_TOKEN' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'ResourceNotFoundException', $result['message'] );
        $this->assertStringNotContainsString( 'aws-Session-Token-Very-43', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_AWS_TOKEN]', $result['message'] );
        // a secret that is not set is named, nothing is sent
        $this->requests = array();
        $result = expImportDynamoDbSource::test( $this->options( array( 'secret_key_secret' => 'sq688_no_such_key' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_key' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testEndpoints(): void
    {
        foreach ( array( array( 'endpoint' => 'http://10.1.2.3:8000' ), array( 'endpoint' => 'http://127.0.0.1:8000' ),
                         array( 'endpoint' => 'https://dynamodb.example.org/other/path' ), array( 'endpoint' => 'https://user:pw@dynamodb.example.org' ) ) as $given )
        {
            $result = expImportDynamoDbSource::test( $this->options( $given ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused endpoint' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'DynamoDbSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $result = expImportDynamoDbSource::test( $this->options( array( 'endpoint' => 'http://10.1.2.3:8000' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'http://10.1.2.3:8000/', $this->requests[0]['url'] );
        $this->assertSame( '10.1.2.3:8000', $this->header( $this->requests[0], 'Host' ) );
        list( , $errors ) = expImportOptionSchema::validate( expImportDynamoDbSource::schema(), array( 'region' => 'EU', 'access_key_id' => 'A', 'secret_key_secret' => 'x', 'table' => 'products' ) );
        $this->assertArrayHasKey( 'region', $errors );
        list( , $errors ) = expImportOptionSchema::validate( expImportDynamoDbSource::schema(), array( 'region' => 'eu-west-1', 'access_key_id' => 'A', 'secret_key_secret' => 'the key itself!', 'table' => 'products' ) );
        $this->assertArrayHasKey( 'secret_key_secret', $errors, 'a secret is a name, never the key' );
    }

    public function testDrivers(): void
    {
        expImportDynamoDbSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => false );
        $drivers = expImportDynamoDbSource::drivers();
        $this->assertSame( array( 'https' ), array_keys( $drivers ) );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['https'] );
        $this->assertFalse( $drivers['https']['available'] );
        expImportDynamoDbSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $this->assertTrue( expImportDynamoDbSource::drivers()['https']['available'] );
    }
}
