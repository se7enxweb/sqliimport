<?php

use PHPUnit\Framework\TestCase;

/**
 * The ArangoDB source against a scripted server (expImportPlatformHttp::$transport): the AQL check, the cursor with
 * its batches (POST, and PUT for older servers) and its deletion after an early stop, documents flattened with
 * _key, _id and _rev, Test connection with masked errors, secrets by name, hosts and drivers().
 */
class ArangoDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, body (decoded) */
    protected $requests = array();

    /** @var array the documents of the scripted collection */
    protected $documents = array();

    /** @var bool whether the server is older than 3.7 (next batch only with PUT) */
    protected $old = false;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-arangotest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'ArangoDbSourceSettings', 'AllowedHosts', array( 'arango.example.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_ARANGO_PASSWORD=pw-Arango-Secret-55' );
        putenv( 'SQ688_ARANGO_JWT=eyJhbGciOi.jwt-Secret-56' );
        for ( $i = 1; $i <= 5; $i++ )
            $this->documents[] = array( '_key' => (string)( 1000 + $i ), '_id' => 'articles/' . ( 1000 + $i ), '_rev' => '_r' . $i, 'title' => 'Article ' . $i,
                                        'author' => array( 'name' => 'Desk', 'address' => array( 'city' => 'Example Town' ) ), 'tags' => array( 'a', 'b' ) );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportArangoDbSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'ArangoDbSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_ARANGO_PASSWORD' );
        putenv( 'SQ688_ARANGO_JWT' );
    }

    /** The scripted server: /_api/version and the cursor API with batches of batchSize. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $path = (string)parse_url( $url, PHP_URL_PATH );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $path === '/_db/news/_api/version' )
            return $json( 200, array( 'server' => 'arango', 'version' => '3.12.4', 'license' => 'community' ) );
        if ( $method === 'POST' && $path === '/_db/news/_api/cursor' )
        {
            if ( strpos( $data['query'], 'fail' ) !== false )
                return $json( 400, array( 'error' => true, 'errorNum' => 1501, 'errorMessage' => 'AQL: syntax error near pw-Arango-Secret-55' ) );
            if ( strpos( $data['query'], 'RETURN 1' ) !== false )
                return $json( 201, array( 'result' => array( 1, 'x' ), 'hasMore' => false ) );
            $size = (int)$data['batchSize'];
            return $json( 201, array( 'result' => array_slice( $this->documents, 0, $size ), 'hasMore' => count( $this->documents ) > $size, 'id' => (string)( 7000 + $size ) ) );
        }
        if ( preg_match( '#^/_db/news/_api/cursor/7(\d{3})$#', $path, $m ) )
        {
            if ( $method === 'DELETE' )
                return $json( 202, array( 'id' => 'c1', 'error' => false ) );
            if ( $this->old && $method === 'POST' )
                return $json( 405, array( 'error' => true, 'errorNum' => 405, 'errorMessage' => 'method not supported' ) );
            $size = (int)$m[1];
            $done = 0;
            foreach ( $this->requests as $r )
                if ( strpos( $r['url'], '/_api/cursor/7' ) !== false && $r['method'] !== 'DELETE' && !( $this->old && $r['method'] === 'POST' ) )
                    $done++;
            $from = $done * $size;
            return $json( 200, array( 'result' => array_slice( $this->documents, $from, $size ), 'hasMore' => count( $this->documents ) > $from + $size, 'id' => (string)( 7000 + $size ) ) );
        }
        return $json( 404, array( 'error' => true, 'errorNum' => 404, 'errorMessage' => 'unknown path ' . $path ) );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportArangoDbSource::schema(), $given + array(
            'base_url' => 'https://arango.example.invalid:8529', 'database' => 'news', 'user' => 'reader', 'password_secret' => 'env:SQ688_ARANGO_PASSWORD',
            'query' => 'FOR a IN articles RETURN a' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes, by _key */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportArangoDbSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportArangoDbSource( array( 'file' => $file, 'key' => '_key' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    /** @return string[] method and path of the requests */
    protected function routes(): array
    {
        return array_map( function ( $r ) { return $r['method'] . ' ' . parse_url( $r['url'], PHP_URL_PATH ); }, $this->requests );
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportArangoDbSource::schema() ) );
        $this->assertSame( 'nosql', expImportArangoDbSource::group() );
        foreach ( expImportArangoDbSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportArangoDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportArangoDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/arangodb-articles.ndjson', 'key' => '_key' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( '1001', '1002', '1003' ), array_map( 'strval', array_keys( $records ) ) );
        $this->assertSame( 'articles/1002', $records['1002']['_id'] );
    }

    public function testOnlyReadingAqlPasses(): void
    {
        foreach ( array( 'FOR a IN articles RETURN a', "FOR a IN articles FILTER a.title == 'INSERT here' RETURN a // REMOVE a IN articles",
                         'FOR a IN articles FILTER a.update > 1 RETURN { t: a.title, r: a.remove }', 'RETURN LENGTH(articles)',
                         'FOR a IN articles /* UPDATE a WITH {} IN articles */ SORT a.date DESC LIMIT 10 RETURN a',
                         'FOR v, e IN 1..2 OUTBOUND "people/1" knows RETURN v' ) as $query )
            $this->assertNull( expImportArangoDbSource::checkAql( $query ), $query );
        foreach ( array( 'INSERT { a: 1 } INTO articles', 'FOR a IN articles UPDATE a WITH { x: 1 } IN articles', 'FOR a IN articles REPLACE a WITH {} IN articles',
                         'FOR a IN articles REMOVE a IN articles', 'UPSERT { a: 1 } INSERT { a: 1 } UPDATE {} IN articles',
                         'RETURN CALL("myfunctions::x")', 'RETURN APPLY("x", [])', 'RETURN myfunctions::write(1)', 'RETURN SLEEP(100)',
                         "FOR a IN articles FILTER a.x == 'unclosed RETURN a", "RETURN \u{00B4}x\u{00B4}", 'RETURN 1; RETURN 2',
                         "FOR a IN `art\\` INSERT {} INTO c RETURN 1 `x` RETURN a", '' ) as $query )
            $this->assertNotNull( expImportArangoDbSource::checkAql( $query ), $query );
        try
        {
            $this->fetched( $this->options( array( 'query' => 'FOR a IN articles REMOVE a IN articles' ) ) );
            $this->fail( 'a write is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'REMOVE', $e->getMessage() );
        }
        $this->assertSame( array(), $this->requests, 'a refused query sends nothing' );
    }

    public function testTheCursorPagesInBatches(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'bind_vars' => '{"status": "published"}' ) ) );
        $this->assertSame( array( '1001', '1002', '1003', '1004', '1005' ), array_map( 'strval', array_keys( $records ) ) );
        $this->assertSame( array( 'POST /_db/news/_api/cursor', 'POST /_db/news/_api/cursor/7002', 'POST /_db/news/_api/cursor/7002' ), $this->routes() );
        $first = $this->requests[0];
        $this->assertSame( 2, $first['body']['batchSize'] );
        $this->assertSame( array( 'stream' => true ), $first['body']['options'] );
        $this->assertSame( array( 'status' => 'published' ), $first['body']['bindVars'] );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pw-Arango-Secret-55' ), $first['headers'] );
        $record = $records['1003'];
        $this->assertSame( array( '_key', '_id', '_rev', 'title', 'author.name', 'author.address.city', 'tags' ), array_keys( $record ) );
        $this->assertSame( 'Example Town', $record['author.address.city'] );
        $this->assertSame( array( 'a', 'b' ), $record['tags'] );
        // an early stop deletes the open cursor
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'page_size' => 2 ) ), 3 ) );
        $routes = $this->routes();
        $this->assertSame( 'DELETE /_db/news/_api/cursor/7002', end( $routes ) );
        // an older server: the next batch with PUT
        $this->old = true;
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'page_size' => 2, 'stream' => false, 'auth' => 'bearer', 'token_secret' => 'env:SQ688_ARANGO_JWT' ) ) ) );
        $this->assertContains( 'PUT /_db/news/_api/cursor/7002', $this->routes() );
        $this->assertArrayNotHasKey( 'options', $this->requests[0]['body'] );
        $this->assertContains( 'Authorization: Bearer eyJhbGciOi.jwt-Secret-56', $this->requests[0]['headers'] );
        // values that are not documents
        $this->assertSame( array( 'value' => 1 ), expImportArangoDbSource::document( 1 ) );
        $this->assertSame( array( 'value' => array( 1, 2 ) ), expImportArangoDbSource::document( array( 1, 2 ) ) );
    }

    public function testTestConnectionHostsAndSecrets(): void
    {
        $result = expImportArangoDbSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'ArangoDB 3.12.4', $result['driver'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( '_key', '_id', '_rev', 'title', 'author.name', 'author.address.city', 'tags' ), $result['columns'] );
        $this->assertSame( 'DELETE /_db/news/_api/cursor/7001', $this->routes()[2], 'the cursor of the test is closed' );
        $result = expImportArangoDbSource::test( $this->options( array( 'query' => 'FOR a IN fail RETURN a' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'error 1501', $result['message'] );
        $this->assertStringNotContainsString( 'pw-Arango-Secret-55', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_ARANGO_PASSWORD]', $result['message'] );
        $this->requests = array();
        $result = expImportArangoDbSource::test( $this->options( array( 'password_secret' => 'sq688_no_such_secret' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_secret' is not set", $result['message'] );
        foreach ( array( 'http://192.168.1.5:8529', 'http://localhost:8529' ) as $base )
            $this->assertFalse( expImportArangoDbSource::test( $this->options( array( 'base_url' => $base ) ) )['ok'], $base );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
    }

    public function testDrivers(): void
    {
        expImportArangoDbSource::$environment = array( 'allow_url_fopen' => false, 'openssl' => true );
        $drivers = expImportArangoDbSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertFalse( $drivers['https']['available'] );
        $this->assertStringContainsString( 'allow_url_fopen', $drivers['http']['needs'] );
    }
}
