<?php

use PHPUnit\Framework\TestCase;

/**
 * The Elasticsearch and OpenSearch source against a scripted server (expImportPlatformHttp::$transport): only searches
 * are sent and scripts are refused, the paging by point in time, search_after or scroll for each server version, the
 * flattening of _source, Test connection with its masked errors, secrets by name, hosts and the driver list.
 */
class SearchSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests the server was sent: method, url, headers, body (decoded) */
    protected $requests = array();

    /** @var array the documents of the scripted index */
    protected $documents = array();

    /** @var array the answer of GET /, null: 403 */
    protected $root = array( 'cluster_name' => 'docs', 'version' => array( 'number' => '8.11.0' ) );

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-searchtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SearchSourceSettings', 'AllowedHosts', array( 'search.example.org' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_ES_PASSWORD=pw-Very-Secret-42' );
        putenv( 'SQ688_ES_KEY=a2V5LWlkOmtleS1WZXJ5LVNlY3JldA==' );
        for ( $i = 1; $i <= 5; $i++ )
            $this->documents[] = array( '_index' => 'articles', '_id' => 'd' . $i, '_source' => array(
                'title' => 'Article ' . $i, 'author' => array( 'name' => 'Desk', 'address' => array( 'city' => 'Berlin' ) ),
                'tags' => array( 'a', 'b' ), 'images' => array( array( 'url' => '/i' . $i . '.jpg' ) ) ), 'sort' => array( $i ) );
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
        $ini->removeSetting( 'SearchSourceSettings', 'AllowedHosts' );
        $ini->removeSetting( 'SourceSettings', 'AllowPrivateHosts' );
        putenv( 'SQ688_ES_PASSWORD' );
        putenv( 'SQ688_ES_KEY' );
    }

    /** The scripted server: GET /, point in time, search, scroll and their closing. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data, 'raw' => $body );
        $path = (string)parse_url( $url, PHP_URL_PATH );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $method === 'GET' && $path === '/' )
            return $this->root === null ? $json( 403, array( 'error' => array( 'type' => 'security_exception', 'reason' => 'no cluster privilege' ) ) )
                                        : $json( 200, $this->root );
        if ( $method === 'DELETE' )
            return $json( 200, array( 'succeeded' => true ) );
        if ( $path === '/articles/_pit' )
            return $json( 200, array( 'id' => 'pit-1' ) );
        if ( $path === '/articles/_search/point_in_time' )
            return $json( 200, array( 'pit_id' => 'ospit-1' ) );
        if ( $path === '/_search/scroll' )
        {
            $page = (int)substr( (string)$data['scroll_id'], 7 );
            return $json( 200, array( '_scroll_id' => 'scroll-' . ( $page + 1 ), 'hits' => array( 'hits' => array_slice( $this->documents, $page * 2, 2 ) ) ) );
        }
        if ( substr( $path, -8 ) === '/_search' )
        {
            if ( isset( $data['query']['match'] ) && $data['query']['match'] === 'fail' )
                return $json( 400, array( 'error' => array( 'root_cause' => array( array( 'type' => 'parsing_exception', 'reason' => 'bad value pw-Very-Secret-42' ) ) ) ) );
            $size = (int)$data['size'];
            $offset = 0;
            if ( isset( $data['search_after'] ) )
                $offset = (int)$data['search_after'][0];
            $hits = array_slice( $this->documents, $offset, $size );
            $answer = array( 'hits' => array( 'hits' => $hits ) );
            if ( strpos( $url, 'scroll=' ) !== false )
                $answer['_scroll_id'] = 'scroll-1';
            if ( isset( $data['pit'] ) )
                $answer['pit_id'] = $data['pit']['id'];
            return $json( 200, $answer );
        }
        return $json( 404, array( 'error' => 'no route ' . $path ) );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportSearchSource::schema(), $given + array( 'base_url' => 'https://search.example.org:9200', 'index' => 'articles' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportSearchSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportSearchSource( array( 'file' => $file, 'key' => '_id' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    /** @return string[] method and path of the requests */
    protected function routes(): array
    {
        return array_map( function ( $r ) { return $r['method'] . ' ' . parse_url( $r['url'], PHP_URL_PATH ) . ( parse_url( $r['url'], PHP_URL_QUERY ) ? '?' . parse_url( $r['url'], PHP_URL_QUERY ) : '' ); }, $this->requests );
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportSearchSource::schema() ) );
        $this->assertSame( 'nosql', expImportSearchSource::group() );
        $this->assertSame( array( 'remote' ), expImportSearchSource::origins() );
        foreach ( expImportSearchSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportSearchSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $file = dirname( __DIR__, 2 ) . '/share/examples/search-articles.ndjson';
        $records = array();
        foreach ( ( new expImportSearchSource( array( 'file' => $file, 'key' => '_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'a-1001', 'a-1002', 'a-1003' ), array_keys( $records ) );
        $this->assertSame( 'Tea desk', $records['a-1002']['author.name'] );
        $this->assertSame( array( 'oolong', 'brewing' ), $records['a-1002']['tags'] );
    }

    public function testOnlySearchesWithoutScriptsPass(): void
    {
        $ok = array(
            array(),
            array( 'term' => array( 'status' => 'published' ) ),
            array( 'bool' => array( 'must' => array( array( 'match' => array( 'title' => 'script' ) ) ) ) ),
            array( 'query' => array( 'match_all' => array() ), 'sort' => array( array( 'date' => 'desc' ) ), '_source' => array( 'title' ) ),
            array( '_source' => false ),
        );
        foreach ( $ok as $query )
            $this->assertNull( expImportSearchSource::checkBody( $query ), json_encode( $query ) );
        $refused = array(
            array( 'script' => array( 'script' => array( 'source' => 'true' ) ) ),
            array( 'bool' => array( 'filter' => array( array( 'script' => array( 'script' => 'doc.x.value > 1' ) ) ) ) ),
            array( 'function_score' => array( 'query' => array( 'match_all' => array() ), 'script_score' => array( 'script' => 'x' ) ) ),
            array( 'query' => array( 'match_all' => array() ), 'script_fields' => array( 'x' => array( 'script' => '1' ) ) ),
            array( 'query' => array( 'match_all' => array() ), 'runtime_mappings' => array( 'x' => array( 'type' => 'keyword' ) ) ),
            array( 'query' => array( 'match_all' => array() ), 'aggs' => array( 'x' => array( 'scripted_metric' => array() ) ) ),
            array( 'size' => 100000 ),
            array( 'query' => array( 'match_all' => array() ), 'pit' => array( 'id' => 'x' ) ),
            array( 'a', 'b' ),
        );
        foreach ( $refused as $query )
            $this->assertNotNull( expImportSearchSource::checkBody( $query ), json_encode( $query ) );
        try
        {
            $this->fetched( $this->options( array( 'query' => '{"query": {"match_all": {}}, "script_fields": {"x": {"script": "1"}}}' ) ) );
            $this->fail( 'a script is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'script_fields', $e->getMessage() );
        }
        $this->assertSame( array(), $this->requests, 'a refused query sends nothing' );
    }

    public function testFlatten(): void
    {
        $record = expImportSearchSource::document( $this->documents[0] );
        $this->assertSame( 'd1', $record['_id'] );
        $this->assertSame( 'articles', $record['_index'] );
        $this->assertSame( 'Desk', $record['author.name'] );
        $this->assertSame( 'Berlin', $record['author.address.city'] );
        $this->assertSame( array( 'a', 'b' ), $record['tags'], 'a list of values stays a list' );
        $this->assertSame( '/i1.jpg', $record['images.0.url'], 'a list of objects is numbered' );
        $this->assertSame( array( 'x' => array() ), expImportSearchSource::flatten( array( 'x' => array() ) ) );
    }

    public function testElasticsearchPagesWithAPointInTime(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'auth' => 'api_key', 'api_key_secret' => 'env:SQ688_ES_KEY',
                                                          'query' => '{"match_all": {}}', 'source_fields' => "title\nauthor.*" ) ) );
        $this->assertSame( array( 'd1', 'd2', 'd3', 'd4', 'd5' ), array_keys( $records ) );
        $this->assertSame( 'Desk', $records['d3']['author.name'] );
        $this->assertSame( array( 'GET /', 'POST /articles/_pit?keep_alive=2m', 'POST /_search', 'POST /_search', 'POST /_search', 'DELETE /_pit' ), $this->routes() );
        $search = $this->requests[2];
        $this->assertContains( 'Authorization: ApiKey a2V5LWlkOmtleS1WZXJ5LVNlY3JldA==', $search['headers'] );
        $this->assertSame( array( 'id' => 'pit-1', 'keep_alive' => '2m' ), $search['body']['pit'] );
        $this->assertSame( array( '_shard_doc' ), $search['body']['sort'] );
        $this->assertSame( array( 'title', 'author.*' ), $search['body']['_source'] );
        $this->assertStringContainsString( '"match_all":{}', (string)$search['raw'], 'an empty object stays an object' );
        $this->assertArrayNotHasKey( 'search_after', $search['body'] );
        $this->assertSame( array( 2 ), $this->requests[3]['body']['search_after'] );
        $this->assertSame( array( 'id' => 'pit-1' ), $this->requests[5]['body'], 'the point in time is closed' );
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'page_size' => 2 ) ), 3 ), 'the editor\'s sample stops at its limit' );
        $this->assertSame( 'DELETE /_pit', $this->routes()[count( $this->requests ) - 1], 'also then the point in time is closed' );
    }

    public function testOpenSearchAndOlderServers(): void
    {
        // OpenSearch 2.11 with a sort: its own point in time
        $this->root = array( 'version' => array( 'distribution' => 'opensearch', 'number' => '2.11.0' ) );
        $this->fetched( $this->options( array( 'page_size' => 2, 'sort' => 'published:desc' ) ) );
        $this->assertSame( 'POST /articles/_search/point_in_time?keep_alive=2m', $this->routes()[1] );
        $this->assertSame( array( array( 'published' => array( 'order' => 'desc' ) ) ), $this->requests[2]['body']['sort'] );
        $routes = $this->routes();
        $this->assertSame( 'DELETE /_search/point_in_time', end( $routes ) );
        $this->assertSame( array( 'pit_id' => array( 'ospit-1' ) ), end( $this->requests )['body'] );
        // without a sort: a scroll
        $this->requests = array();
        $records = $this->fetched( $this->options( array( 'page_size' => 2 ) ) );
        $this->assertCount( 5, $records );
        $this->assertSame( array( 'GET /', 'POST /articles/_search?scroll=2m', 'POST /_search/scroll', 'POST /_search/scroll', 'DELETE /_search/scroll' ), $this->routes() );
        $this->assertSame( array( '_doc' ), $this->requests[1]['body']['sort'] );
        // Elasticsearch 7.9 with a sort that ends in _id: search_after alone
        $this->root = array( 'version' => array( 'number' => '7.9.3' ) );
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'page_size' => 2, 'sort' => 'published:asc,_id' ) ) ) );
        $this->assertSame( array( 'GET /', 'POST /articles/_search', 'POST /articles/_search', 'POST /articles/_search' ), $this->routes() );
        $this->assertSame( array( 4 ), $this->requests[3]['body']['search_after'] );
        // search_after chosen without a unique sort
        try
        {
            $this->fetched( $this->options( array( 'paging' => 'search_after', 'sort' => 'published' ) ) );
            $this->fail( 'search_after needs a unique last sort field' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( '_id', $e->getMessage() );
        }
        $this->assertSame( 'pit_es', expImportSearchSource::pagingMode( 'auto', array( 'distribution' => 'elasticsearch', 'version' => '7.10.0' ), array() ) );
        $this->assertSame( 'scroll', expImportSearchSource::pagingMode( 'auto', null, array() ), 'an unknown version scrolls' );
    }

    public function testTestConnection(): void
    {
        $result = expImportSearchSource::test( $this->options( array( 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'env:SQ688_ES_PASSWORD' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'Elasticsearch 8.11.0', $result['driver'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( '_id', '_index', 'title', 'author.name', 'author.address.city', 'tags', 'images.0.url' ), $result['columns'] );
        $this->assertSame( array( 'GET /', 'POST /articles/_search' ), $this->routes() );
        $this->assertSame( 1, $this->requests[1]['body']['size'] );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pw-Very-Secret-42' ), $this->requests[1]['headers'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        // an error of the server that quotes the password: masked
        $result = expImportSearchSource::test( $this->options( array( 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'env:SQ688_ES_PASSWORD',
                                                                      'query' => '{"match": "fail"}' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'parsing_exception', $result['message'] );
        $this->assertStringNotContainsString( 'pw-Very-Secret-42', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_ES_PASSWORD]', $result['message'] );
        // a user without cluster rights: the version is unknown, the search still works
        $this->root = null;
        $result = expImportSearchSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'] );
        $this->assertStringContainsString( 'version is not readable', $result['message'] );
        // a secret that is not set is named, nothing is sent
        $this->requests = array();
        $result = expImportSearchSource::test( $this->options( array( 'auth' => 'api_key', 'api_key_secret' => 'sq688_no_such_key' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_key' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testHostsAndIndexNames(): void
    {
        foreach ( array( array( 'base_url' => 'http://10.1.2.3:9200' ), array( 'index' => 'articles/_delete_by_query' ), array( 'index' => 'remote:articles' ),
                         array( 'index' => '_all' ), array( 'base_url' => 'https://search.example.org/?x=1' ) ) as $given )
        {
            $result = expImportSearchSource::test( $given + array( 'base_url' => 'https://search.example.org', 'index' => 'articles' ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server or index' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SearchSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $this->assertTrue( expImportSearchSource::test( array( 'base_url' => 'http://10.1.2.3:9200', 'index' => 'articles' ) )['ok'], 'an allowed intranet server' );
    }

    public function testDrivers(): void
    {
        expImportSearchSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => false );
        $drivers = expImportSearchSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertTrue( $drivers['http']['available'] );
        $this->assertFalse( $drivers['https']['available'] );
        $this->assertStringContainsString( 'openssl', $drivers['https']['needs'] );
        // what the job queue's workers had
        expImportSqlSource::recordWorkerPhp();
        $worker = expImportSqlSource::workerPhp();
        $this->assertArrayHasKey( 'search.https', $worker['drivers'] );
        $this->assertArrayHasKey( 'duckdb.cli', $worker['drivers'] );
        $this->assertSame( 'no', expImportSearchSource::drivers()['https']['worker'] );
    }
}
