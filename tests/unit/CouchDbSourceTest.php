<?php

use PHPUnit\Framework\TestCase;

/**
 * The CouchDB source against a scripted server (expImportPlatformHttp::$transport): only reading requests, Mango
 * selectors with query operators only, paging of _all_docs and views by the extra row (startkey, startkey_docid), of
 * Mango by bookmark and of keys in slices, design documents left out, the flattening with _id and _rev, Test
 * connection with basic and cookie authentication and masked errors, secrets by name, hosts and the driver list.
 */
class CouchDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, path, query, headers, body (decoded) */
    protected $requests = array();

    /** @var array id => document, the database articles */
    protected $documents = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-couchtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CouchDbSourceSettings', 'AllowedHosts', array( 'couch.test.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_COUCH_PASSWORD=pw-Couch-Secret-77' );
        $this->documents['_design/news'] = array( '_id' => '_design/news', '_rev' => '1-d', 'views' => array( 'by_date' => array( 'map' => 'function (doc) {}' ) ) );
        foreach ( array( 1, 2, 3, 4, 5 ) as $i )
            $this->documents['article-' . $i] = array( '_id' => 'article-' . $i, '_rev' => $i . '-abc', 'title' => "Article $i",
                                                      'author' => array( 'name' => 'Desk', 'address' => array( 'city' => 'Town' ) ),
                                                      'tags' => array( 'tea', "t$i" ), 'date' => $i <= 3 ? '2026-01-01' : '2026-02-0' . $i );
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
        $ini->removeSetting( 'CouchDbSourceSettings', 'AllowedHosts' );
        $ini->removeSetting( 'SecretSettings', 'Secrets' );
        putenv( 'SQ688_COUCH_PASSWORD' );
    }

    /** The scripted server. */
    public function answer( $method, $url, array $headers, $body )
    {
        $path = (string)parse_url( $url, PHP_URL_PATH );
        parse_str( (string)parse_url( $url, PHP_URL_QUERY ), $query );
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'path' => $path, 'query' => $query, 'headers' => $headers, 'body' => $data );
        $json = function ( $status, $value, array $lines = array() ) { return array( 'status' => $status, 'headers' => $lines, 'body' => json_encode( $value ) ); };
        if ( $path === '/' )
            return $json( 200, array( 'couchdb' => 'Welcome', 'version' => '3.4.2' ) );
        if ( $path === '/_session' )
            return $data['password'] === 'pw-Couch-Secret-77'
                 ? $json( 200, array( 'ok' => true ), array( 'HTTP/1.1 200 OK', 'Set-Cookie: AuthSession=c2Vzc2lvbi10b2tlbi0xMjM0; Version=1; Path=/; HttpOnly' ) )
                 : $json( 401, array( 'error' => 'unauthorized', 'reason' => 'Name or password is incorrect.' ) );
        if ( in_array( 'Authorization: Basic ' . base64_encode( 'reader:wrong-pw-123' ), $headers, true ) )
            return $json( 401, array( 'error' => 'unauthorized', 'reason' => 'bad password wrong-pw-123' ) );
        if ( $path === '/articles/_all_docs' )
        {
            $ids = array_keys( $this->documents );
            sort( $ids, SORT_STRING );
            if ( isset( $data['keys'] ) )
                return $json( 200, array( 'rows' => array_map( function ( $id )
                {
                    return isset( $this->documents[$id] ) ? $this->row( $id, $id, array( 'rev' => $this->documents[$id]['_rev'] ), true ) : array( 'key' => $id, 'error' => 'not_found' );
                }, $data['keys'] ) ) );
            $start = isset( $query['startkey'] ) ? json_decode( $query['startkey'] ) : null;
            $rows = array();
            foreach ( $ids as $id )
                if ( $start === null || strcmp( $id, $start ) >= 0 )
                    $rows[] = $this->row( $id, $id, array( 'rev' => $this->documents[$id]['_rev'] ), isset( $query['include_docs'] ) );
            return $json( 200, array( 'total_rows' => count( $ids ), 'rows' => array_slice( $rows, 0, (int)$query['limit'] ) ) );
        }
        if ( $path === '/articles/_design/news/_view/by_date' )
        {
            if ( $query['reduce'] === 'true' )
                return $json( 200, array( 'rows' => array( array( 'key' => '2026-01-01', 'value' => 3 ), array( 'key' => '2026-02-04', 'value' => 1 ) ) ) );
            $rows = array();
            foreach ( $this->documents as $id => $doc )
                if ( isset( $doc['date'] ) )
                    $rows[] = $this->row( $id, $doc['date'], 1, isset( $query['include_docs'] ) );
            usort( $rows, function ( $a, $b ) { return strcmp( $a['key'] . $a['id'], $b['key'] . $b['id'] ); } );
            $startKey = isset( $query['startkey'] ) ? json_decode( $query['startkey'] ) : null;
            $startDoc = isset( $query['startkey_docid'] ) ? $query['startkey_docid'] : '';
            $rows = array_values( array_filter( $rows, function ( $r ) use ( $startKey, $startDoc )
            {
                return $startKey === null || strcmp( $r['key'], $startKey ) > 0 || ( $r['key'] === $startKey && strcmp( $r['id'], $startDoc ) >= 0 );
            } ) );
            return $json( 200, array( 'rows' => array_slice( $rows, 0, (int)$query['limit'] ) ) );
        }
        if ( $path === '/articles/_find' )
        {
            $docs = array_values( array_filter( $this->documents, function ( $d ) { return isset( $d['title'] ); } ) );
            $from = isset( $data['bookmark'] ) ? (int)substr( $data['bookmark'], 3 ) : 0;
            $page = array_slice( $docs, $from, (int)$data['limit'] );
            return $json( 200, array( 'docs' => $page, 'bookmark' => 'bm-' . ( $from + count( $page ) ) ) );
        }
        return $json( 404, array( 'error' => 'not_found', 'reason' => 'no route ' . $path ) );
    }

    /** @return array a row of _all_docs or a view */
    protected function row( $id, $key, $value, $docs )
    {
        $row = array( 'id' => $id, 'key' => $key, 'value' => $value );
        if ( $docs )
            $row['doc'] = $this->documents[$id];
        return $row;
    }

    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportCouchDbSource::schema(), $given + array( 'base_url' => 'https://couch.test.invalid:6984', 'database' => 'articles' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes, by _id */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportCouchDbSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportCouchDbSource( array( 'file' => $file, 'key' => '_id' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportCouchDbSource::schema() ) );
        $this->assertSame( 'nosql', expImportCouchDbSource::group() );
        $this->assertSame( array( 'remote' ), expImportCouchDbSource::origins() );
        $this->assertSame( 'secret', expImportCouchDbSource::schema()['password_secret']['type'] );
        foreach ( expImportCouchDbSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportCouchDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            if ( isset( $example['options']['password_secret'] ) )
                $this->assertNull( expImportSecrets::get( $example['options']['password_secret'] ) );
        }
        $records = array();
        foreach ( ( new expImportCouchDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/couchdb-articles.ndjson', 'key' => '_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'article-2001', 'article-2002', 'article-2003' ), array_keys( $records ) );
        $this->assertSame( 'Tasting desk', $records['article-2001']['author.name'] );
        $this->assertStringStartsWith( '1-', $records['article-2002']['_rev'] );
    }

    public function testOnlyReadingRequestsAndSelectors(): void
    {
        foreach ( array( array( 'GET', '/' ), array( 'POST', '/_session' ), array( 'GET', '/articles/_all_docs' ), array( 'POST', '/articles/_all_docs' ),
                         array( 'POST', '/articles/_find' ), array( 'GET', '/articles/_design/news/_view/by_date' ) ) as $ok )
            $this->assertTrue( expImportCouchDbSource::allowedRequest( $ok[0], $ok[1] ), implode( ' ', $ok ) );
        foreach ( array( array( 'POST', '/articles/_bulk_docs' ), array( 'PUT', '/articles/article-1' ), array( 'DELETE', '/articles/article-1' ),
                         array( 'POST', '/articles/_design/news/_update/touch' ), array( 'PUT', '/articles/_design/news' ), array( 'GET', '/articles/_find' ),
                         array( 'POST', '/articles' ), array( 'GET', '/_all_dbs' ), array( 'POST', '/_replicate' ), array( 'GET', '/articles/article-1' ),
                         array( 'POST', '/articles/_design/news/_show/x' ), array( 'PUT', '/_users/org.couchdb.user:x' ), array( 'POST', '/_users/_all_docs' ) ) as $refused )
            $this->assertFalse( expImportCouchDbSource::allowedRequest( $refused[0], $refused[1] ), implode( ' ', $refused ) );
        $this->assertNull( expImportCouchDbSource::checkSelector( array( 'type' => 'article', 'year' => array( '$gte' => 2025 ),
                                                                         '$or' => array( array( 'a' => array( '$regex' => '^x' ) ), array( 'b' => array( '$elemMatch' => array( '$eq' => 1 ) ) ) ) ) ) );
        $this->assertNull( expImportCouchDbSource::checkSelector( null ) );
        foreach ( array( array( 'a' => array( '$where' => 'x' ) ), array( '$or' => array( array( 'a' => array( '$function' => array() ) ) ) ), array( 'a', 'b' ), 'x' ) as $bad )
            $this->assertNotNull( expImportCouchDbSource::checkSelector( $bad ), json_encode( $bad ) );
        // refused options send nothing
        foreach ( array( array( 'database' => '_users' ), array( 'database' => 'articles/_bulk_docs' ), array( 'mode' => 'view', 'design' => 'news', 'view' => '' ),
                         array( 'mode' => 'mango', 'selector' => '{"a": {"$where": "1"}}' ), array( 'mode' => 'mango', 'sort' => '{"a": 1}' ),
                         array( 'keys' => '{"a": 1}' ), array( 'startkey' => 'not json' ) ) as $given )
        {
            $result = expImportCouchDbSource::test( $given + array( 'base_url' => 'https://couch.test.invalid', 'database' => 'articles' ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests );
        $this->assertFalse( expImportOptionSchema::validate( expImportCouchDbSource::schema(), array( 'base_url' => 'https://couch.test.invalid', 'database' => 'a', 'design' => 'x/_update/y' ) )[1] === array() );
    }

    public function testAllDocsPagesByTheExtraRowAndLeavesOutDesignDocuments(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2 ) ) );
        $this->assertSame( array( 'article-1', 'article-2', 'article-3', 'article-4', 'article-5' ), array_keys( $records ) );
        $this->assertSame( '3-abc', $records['article-3']['_rev'] );
        $this->assertSame( 'Town', $records['article-3']['author.address.city'] );
        $this->assertSame( '["tea","t3"]', $records['article-3']['tags'] );
        $this->assertCount( 3, $this->requests );
        $this->assertSame( array( 'include_docs' => 'true', 'limit' => '3' ), $this->requests[0]['query'] );
        $this->assertSame( '"article-2"', $this->requests[1]['query']['startkey'], 'the next page starts at the extra row' );
        $this->assertSame( '"article-4"', $this->requests[2]['query']['startkey'] );
        // design documents on request, without documents only ID and revision, joined lists, the sample limit
        $this->requests = array();
        $this->assertArrayHasKey( '_design/news', $this->fetched( $this->options( array( 'include_design' => true ) ) ) );
        $records = $this->fetched( $this->options( array( 'include_docs' => false ) ) );
        $this->assertSame( array( '_id' => 'article-1', '_rev' => '1-abc' ), $records['article-1'] );
        $this->assertSame( 'tea | t2', $this->fetched( $this->options( array( 'arrays' => 'join', 'separator' => ' | ' ) ) )['article-2']['tags'] );
        $this->requests = array();
        $this->assertCount( 2, $this->fetched( $this->options( array( 'page_size' => 10 ) ), 2 ) );
        $this->assertSame( '3', $this->requests[0]['query']['limit'] );
        // keys: POST in slices of a page, a missing key left out
        $this->requests = array();
        $records = $this->fetched( $this->options( array( 'keys' => '["article-5", "gone", "article-1"]', 'page_size' => 2 ) ) );
        $this->assertSame( array( 'article-5', 'article-1' ), array_keys( $records ) );
        $this->assertSame( array( 'POST', 'POST' ), array_column( $this->requests, 'method' ) );
        $this->assertSame( array( 'keys' => array( 'article-5', 'gone' ) ), $this->requests[0]['body'] );
    }

    public function testViewsPageByKeyAndDocumentIdAndMangoByBookmark(): void
    {
        $records = $this->fetched( $this->options( array( 'mode' => 'view', 'design' => '_design/news', 'view' => 'by_date', 'page_size' => 2 ) ) );
        $this->assertSame( array( 'article-1', 'article-2', 'article-3', 'article-4', 'article-5' ), array_keys( $records ) );
        $this->assertSame( 'false', $this->requests[0]['query']['reduce'] );
        $this->assertSame( array( '"2026-01-01"', 'article-3' ), array( $this->requests[1]['query']['startkey'], $this->requests[1]['query']['startkey_docid'] ),
                           'the rows of one key told apart by the document ID' );
        // a range, and rows without documents
        $this->requests = array();
        $records = $this->fetched( $this->options( array( 'mode' => 'view', 'design' => 'news', 'view' => 'by_date', 'startkey' => '"2026-02-01"',
                                                          'endkey' => '"2026-12-31"', 'include_docs' => false ) ) );
        $this->assertSame( array( '_id' => 'article-4', 'key' => '2026-02-04', 'value' => 1 ), $records['article-4'] );
        $this->assertSame( '"2026-12-31"', $this->requests[0]['query']['endkey'] );
        // a reduce: grouped, no documents
        $this->requests = array();
        $file = $this->dir . '/reduce.ndjson';
        expImportCouchDbSource::fetch( $this->options( array( 'mode' => 'view', 'design' => 'news', 'view' => 'by_date', 'reduce' => true ) ), $file, 0 );
        $this->assertSame( array( 'key' => '2026-01-01', 'value' => 3 ), json_decode( explode( "\n", (string)file_get_contents( $file ) )[0], true ) );
        $this->assertSame( array( 'true', 'true' ), array( $this->requests[0]['query']['reduce'], $this->requests[0]['query']['group'] ) );
        $this->assertArrayNotHasKey( 'include_docs', $this->requests[0]['query'] );
        // Mango: POST _find with the bookmark of the last answer
        $this->requests = array();
        $records = $this->fetched( $this->options( array( 'mode' => 'mango', 'selector' => '{"title": {"$regex": "^Article"}}', 'fields' => "_id\ntitle",
                                                          'sort' => '[{"title": "asc"}]', 'page_size' => 2 ) ) );
        $this->assertCount( 5, $records );
        $this->assertSame( array( 'POST', 'POST', 'POST' ), array_column( $this->requests, 'method' ) );
        $this->assertSame( array( 'selector' => array( 'title' => array( '$regex' => '^Article' ) ), 'limit' => 2, 'fields' => array( '_id', 'title' ),
                                  'sort' => array( array( 'title' => 'asc' ) ) ), $this->requests[0]['body'] );
        $this->assertSame( 'bm-2', $this->requests[1]['body']['bookmark'] );
        $this->assertSame( '/articles/_find', $this->requests[0]['path'] );
    }

    public function testTestConnectionAuthenticationAndMasking(): void
    {
        $result = expImportCouchDbSource::test( $this->options( array( 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'env:SQ688_COUCH_PASSWORD' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'CouchDB 3.4.2', $result['driver'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( '_id', '_rev', 'title', 'author.name', 'author.address.city', 'tags', 'date' ), $result['columns'] );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pw-Couch-Secret-77' ), $this->requests[1]['headers'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        // cookie: a session, then the cookie on every request
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( $this->options( array( 'auth' => 'cookie', 'user' => 'reader', 'password_secret' => 'env:SQ688_COUCH_PASSWORD' ) ) ) );
        $this->assertSame( array( 'POST', '/_session' ), array( $this->requests[0]['method'], $this->requests[0]['path'] ) );
        $this->assertSame( array( 'name' => 'reader', 'password' => 'pw-Couch-Secret-77' ), $this->requests[0]['body'] );
        $this->assertContains( 'Cookie: AuthSession=c2Vzc2lvbi10b2tlbi0xMjM0', $this->requests[1]['headers'] );
        // an error that quotes the password: masked
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SecretSettings', 'Secrets', array( 'couch_bad' => 'wrong-pw-123' ) );
        $result = expImportCouchDbSource::test( $this->options( array( 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'couch_bad' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringNotContainsString( 'wrong-pw-123', $result['message'] );
        $this->assertStringContainsString( '[secret couch_bad]', $result['message'] );
        $result = expImportCouchDbSource::test( $this->options( array( 'auth' => 'cookie', 'user' => 'reader', 'password_secret' => 'couch_bad' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Name or password is incorrect', $result['message'] );
        // a secret that is not set: named, nothing sent
        $this->requests = array();
        $result = expImportCouchDbSource::test( $this->options( array( 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'sq688_not_set' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_not_set' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
        // a database that is not there
        $result = expImportCouchDbSource::test( $this->options( array( 'database' => 'missing' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'not_found', $result['message'] );
    }

    public function testHostsAndDrivers(): void
    {
        foreach ( array( 'http://10.1.2.3:5984', 'https://127.0.0.1:6984', 'https://couch.test.invalid/?x=1', 'https://user:pw@couch.test.invalid', 'ftp://couch.test.invalid' ) as $base )
            $this->assertFalse( expImportCouchDbSource::test( array( 'base_url' => $base, 'database' => 'articles' ) )['ok'], $base );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CouchDbSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $this->assertTrue( expImportCouchDbSource::test( array( 'base_url' => 'http://10.1.2.3:5984', 'database' => 'articles' ) )['ok'], 'an allowed intranet server' );
        expImportSearchSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportCouchDbSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertTrue( $drivers['https']['available'] );
    }
}
