<?php

use PHPUnit\Framework\TestCase;

/**
 * The Google Cloud sources against a scripted Google (expImportPlatformHttp::$transport): the service account's
 * signed JWT and the token cache (expImportGoogleAuth), Firestore (structured query with cursor or offset paging, the
 * list of a collection, the check of a filter, typed values) and BigQuery (the query check, a job that is polled and
 * paged with pageToken, the schema's types, the dry run of Test connection and the bytes billed limit). No request
 * reaches Google.
 */
class GoogleCloudSourcesTest extends TestCase
{
    /** @var string|null the PEM of a key made for the tests */
    protected static $privateKey = null;

    /** @var string|null */
    protected static $publicKey = null;

    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, body (raw), json (decoded) */
    protected $requests = array();

    /** @var array[] the claims of the JWTs the token endpoint got */
    protected $grants = array();

    /** @var int now, for the token cache */
    protected $now = 1775000000;

    /** @var array[] the documents of the scripted Firestore collection "articles" */
    protected $documents = array();

    /** @var array[] the rows of the scripted BigQuery result */
    protected $rows = array();

    /** @var int|null a status the APIs answer with instead (with an error message that quotes the token) */
    protected $failWith = null;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
        $pem = '';
        openssl_pkey_export( $key, $pem );
        self::$privateKey = $pem;
        self::$publicKey = openssl_pkey_get_details( $key )['key'];
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-gcptest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_GCP_KEY=' . json_encode( array( 'type' => 'service_account', 'project_id' => 'example-project', 'private_key_id' => 'kid-0123456789',
                                                       'private_key' => self::$privateKey, 'client_email' => 'reader@example-project.iam.gserviceaccount.com',
                                                       'token_uri' => 'https://elsewhere.example.invalid/token' ) ) );
        putenv( 'SQ688_GCP_BROKEN=not a key' );
        expImportGoogleAuth::reset();
        expImportGoogleAuth::$clock = function () { return $this->now; };
        expImportPlatformHttp::$transport = array( $this, 'answer' );
        expImportApiHttp::$sleeper = function ( $seconds ) {};
        $this->documents = array();
        for ( $i = 1; $i <= 5; $i++ )
            $this->documents[] = array( 'name' => 'projects/example-project/databases/(default)/documents/articles/a-' . $i,
                                        'fields' => array( 'title' => array( 'stringValue' => 'Article ' . $i ), 'views' => array( 'integerValue' => (string)( 100 * $i ) ),
                                                           'author' => array( 'mapValue' => array( 'fields' => array( 'name' => array( 'stringValue' => 'Desk' ) ) ) ) ),
                                        'createTime' => '2026-02-0' . $i . 'T09:00:00.000000Z', 'updateTime' => '2026-02-0' . $i . 'T10:00:00.000000Z' );
        $this->rows = array();
        for ( $i = 1; $i <= 5; $i++ )
            $this->rows[] = array( 'f' => array( array( 'v' => (string)( 9000000 + $i ) ), array( 'v' => array( 'f' => array( array( 'v' => 'Customer ' . $i ), array( 'v' => 'DE' ) ) ) ),
                                                 array( 'v' => '1' . $i . '.50' ) ) );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportApiHttp::$sleeper = null;
        expImportGoogleAuth::$clock = null;
        expImportGoogleAuth::reset();
        expImportFirestoreSource::$environment = null;
        expImportBigQuerySource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'GoogleCloudSourceSettings', 'MaximumBytesBilled' );
        putenv( 'SQ688_GCP_KEY' );
        putenv( 'SQ688_GCP_BROKEN' );
    }

    // ---- the scripted Google -------------------------------------------------------------------------------------------

    public function answer( $method, $url, array $headers, $body )
    {
        $json = json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'json' => $json );
        $reply = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $url === expImportGoogleAuth::TOKEN_URL )
            return $this->token( (string)$body, $reply );
        $token = substr( expImportPlatformHttp::header( $headers, 'Authorization' ), 7 );
        if ( !preg_match( '/^ya29\.token-(\d+)$/', $token, $m ) || (int)$m[1] < 1 || (int)$m[1] > count( $this->grants ) )
            return $reply( 401, array( 'error' => array( 'code' => 401, 'status' => 'UNAUTHENTICATED', 'message' => 'no token' ) ) );
        if ( $this->failWith !== null )
            return $reply( $this->failWith, array( 'error' => array( 'code' => $this->failWith, 'status' => 'PERMISSION_DENIED',
                                                                     'message' => 'Missing permission for token ' . $token ) ) );
        $path = (string)parse_url( $url, PHP_URL_PATH );
        parse_str( (string)parse_url( $url, PHP_URL_QUERY ), $query );
        if ( strpos( $path, '/v1/projects/' ) === 0 )
            return $this->serveFirestore( $method, $path, $query, $json, $reply );
        if ( strpos( $path, '/bigquery/v2/projects/' ) === 0 )
            return $this->serveBigQuery( $method, $path, $query, $json, $reply );
        return $reply( 404, array( 'error' => array( 'message' => 'no route' ) ) );
    }

    /** The token endpoint: checks the JWT's signature and claims. */
    protected function token( $body, $reply )
    {
        parse_str( $body, $form );
        $this->assertSame( 'urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type'] );
        list( $h, $c, $s ) = explode( '.', $form['assertion'] );
        $decode = function ( $part ) { return base64_decode( strtr( $part, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $part ) % 4 ) % 4 ) ); };
        $this->assertSame( 1, openssl_verify( $h . '.' . $c, $decode( $s ), self::$publicKey, OPENSSL_ALGO_SHA256 ), 'the JWT is signed with the key' );
        $header = json_decode( $decode( $h ), true );
        $this->assertSame( array( 'alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'kid-0123456789' ), $header );
        $claims = json_decode( $decode( $c ), true );
        $this->grants[] = $claims;
        return $reply( 200, array( 'access_token' => 'ya29.token-' . count( $this->grants ), 'expires_in' => 3600, 'token_type' => 'Bearer' ) );
    }

    /** Firestore: runQuery (limit, offset, startAt by the document name) and the list of a collection. */
    protected function serveFirestore( $method, $path, array $query, $json, $reply )
    {
        $docs = $this->documents;
        if ( $method === 'GET' )
        {
            $offset = isset( $query['pageToken'] ) ? (int)substr( $query['pageToken'], 5 ) : 0;
            $page = array_slice( $docs, $offset, (int)$query['pageSize'] );
            $answer = array( 'documents' => $page );
            if ( $offset + count( $page ) < count( $docs ) )
                $answer['nextPageToken'] = 'page-' . ( $offset + count( $page ) );
            return $reply( 200, $answer );
        }
        $structured = $json['structuredQuery'];
        $direction = end( $structured['orderBy'] )['direction'];
        if ( $direction === 'DESCENDING' )
            $docs = array_reverse( $docs );
        $start = 0;
        if ( isset( $structured['startAt'] ) )
        {
            $after = end( $structured['startAt']['values'] )['referenceValue'];
            foreach ( $docs as $i => $doc )
                if ( $doc['name'] === $after )
                    $start = $i + 1;
        }
        if ( isset( $structured['offset'] ) )
            $start = (int)$structured['offset'];
        $answer = array();
        foreach ( array_slice( $docs, $start, (int)$structured['limit'] ) as $doc )
            $answer[] = array( 'document' => $doc, 'readTime' => '2026-04-02T08:30:00Z' );
        if ( !$answer )
            $answer[] = array( 'readTime' => '2026-04-02T08:30:00Z' );
        return $reply( 200, $answer );
    }

    /** BigQuery: jobs.query (dry run, or a job that is not complete yet) and getQueryResults with pageToken. */
    protected function serveBigQuery( $method, $path, array $query, $json, $reply )
    {
        $schema = array( 'fields' => array( array( 'name' => 'order_id', 'type' => 'INTEGER', 'mode' => 'NULLABLE' ),
                                            array( 'name' => 'customer', 'type' => 'RECORD', 'mode' => 'NULLABLE', 'fields' => array(
                                                array( 'name' => 'name', 'type' => 'STRING' ), array( 'name' => 'country', 'type' => 'STRING' ) ) ),
                                            array( 'name' => 'total', 'type' => 'NUMERIC' ) ) );
        if ( $method === 'POST' )
        {
            if ( !empty( $json['dryRun'] ) )
                return $reply( 200, array( 'kind' => 'bigquery#queryResponse', 'schema' => $schema, 'totalBytesProcessed' => '1234567', 'jobComplete' => true ) );
            return $reply( 200, array( 'jobReference' => array( 'projectId' => 'example-project', 'jobId' => 'job_1', 'location' => 'EU' ), 'jobComplete' => false ) );
        }
        $offset = isset( $query['pageToken'] ) ? (int)substr( $query['pageToken'], 5 ) : 0;
        $page = array_slice( $this->rows, $offset, (int)$query['maxResults'] );
        $answer = array( 'jobComplete' => true, 'schema' => $schema, 'rows' => $page, 'totalRows' => (string)count( $this->rows ),
                         'jobReference' => array( 'jobId' => 'job_1', 'location' => 'EU' ) );
        if ( $offset + count( $page ) < count( $this->rows ) )
            $answer['pageToken'] = 'page-' . ( $offset + count( $page ) );
        return $reply( 200, $answer );
    }

    /** @return string[] method and path (with the query) of the requests */
    protected function routes(): array
    {
        return array_map( function ( $r ) { return $r['method'] . ' ' . preg_replace( '#^https://[^/]+#', '', $r['url'] ); }, $this->requests );
    }

    /** @return array validated options */
    protected function options( $class, array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( $class::schema(), $given );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot of a fetch, by key */
    protected function fetched( $class, array $options, $key, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        $class::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new $class( array( 'file' => $file, 'key' => $key ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    // ---- the service account -------------------------------------------------------------------------------------------

    public function testServiceAccountTokenIsSignedCachedAndMasked(): void
    {
        $token = expImportGoogleAuth::accessToken( 'env:SQ688_GCP_KEY', 'https://www.googleapis.com/auth/cloud-platform.read-only', 'Test' );
        $this->assertSame( 'ya29.token-1', $token );
        $this->assertSame( array( expImportGoogleAuth::TOKEN_URL ), array_column( $this->requests, 'url' ), 'the token_uri of the key file is not followed' );
        $claims = $this->grants[0];
        $this->assertSame( 'reader@example-project.iam.gserviceaccount.com', $claims['iss'] );
        $this->assertSame( 'https://oauth2.googleapis.com/token', $claims['aud'] );
        $this->assertSame( 'https://www.googleapis.com/auth/cloud-platform.read-only', $claims['scope'] );
        $this->assertSame( 3600, $claims['exp'] - $claims['iat'] );
        // the same run keeps the token; another scope gets its own; an expiring one is renewed
        $this->assertSame( 'ya29.token-1', expImportGoogleAuth::accessToken( 'env:SQ688_GCP_KEY', 'https://www.googleapis.com/auth/cloud-platform.read-only', 'Test' ) );
        $this->assertCount( 1, $this->grants );
        $this->assertSame( 'ya29.token-2', expImportGoogleAuth::accessToken( 'env:SQ688_GCP_KEY', 'https://www.googleapis.com/auth/bigquery', 'Test' ) );
        $this->now += 3560;
        $this->assertSame( 'ya29.token-3', expImportGoogleAuth::accessToken( 'env:SQ688_GCP_KEY', 'https://www.googleapis.com/auth/cloud-platform.read-only', 'Test' ) );
        // the token, the private key and the key file are masked
        $text = expImportGoogleAuth::mask( 'token ya29.token-3, key ' . self::$privateKey, array( 'env:SQ688_GCP_KEY' ) );
        $this->assertStringNotContainsString( 'ya29.token-3', $text );
        $this->assertStringNotContainsString( 'PRIVATE KEY', $text );
        // a secret that is not a key: named, never quoted
        try
        {
            expImportGoogleAuth::accessToken( 'env:SQ688_GCP_BROKEN', 'x', 'Test' );
            $this->fail( 'not a key' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( "'env:SQ688_GCP_BROKEN' is not the JSON key of a service account", $e->getMessage() );
            $this->assertStringNotContainsString( 'not a key', $e->getMessage() );
        }
    }

    // ---- Firestore -----------------------------------------------------------------------------------------------------

    protected function firestore( array $given ): array
    {
        return $this->options( 'expImportFirestoreSource', $given + array( 'project' => 'example-project', 'credentials_secret' => 'env:SQ688_GCP_KEY',
                                                                           'collection' => 'articles' ) );
    }

    public function testFirestoreSchemaExamplesAndSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportFirestoreSource::schema() ) );
        $this->assertSame( 'nosql', expImportFirestoreSource::group() );
        $this->assertSame( array( 'remote' ), expImportFirestoreSource::origins() );
        foreach ( expImportFirestoreSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportFirestoreSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportFirestoreSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/firestore-articles.ndjson', 'key' => '_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'a-2001', 'a-2002', 'a-2003' ), array_keys( $records ) );
        $this->assertSame( 'Tea desk', $records['a-2001']['author.name'] );
    }

    public function testFirestoreFiltersOnlyFilter(): void
    {
        $ok = array(
            array(),
            array( 'fieldFilter' => array( 'field' => array( 'fieldPath' => 'status' ), 'op' => 'EQUAL', 'value' => array( 'stringValue' => 'published' ) ) ),
            array( 'compositeFilter' => array( 'op' => 'AND', 'filters' => array(
                array( 'unaryFilter' => array( 'field' => array( 'fieldPath' => 'deleted' ), 'op' => 'IS_NULL' ) ),
                array( 'fieldFilter' => array( 'field' => array( 'fieldPath' => 'tags' ), 'op' => 'ARRAY_CONTAINS_ANY',
                                               'value' => array( 'arrayValue' => array( 'values' => array( array( 'stringValue' => 'tea' ) ) ) ) ) ) ) ) ),
        );
        foreach ( $ok as $where )
            $this->assertNull( expImportFirestoreSource::checkWhere( $where ), json_encode( $where ) );
        $refused = array(
            array( 'fieldFilter' => array( 'field' => array( 'fieldPath' => 'a' ), 'op' => 'SET', 'value' => array( 'stringValue' => 'x' ) ) ),
            array( 'fieldFilter' => array( 'field' => array( 'fieldPath' => 'a' ), 'op' => 'EQUAL', 'value' => 'x' ) ),
            array( 'fieldFilter' => array( 'field' => array( 'fieldPath' => 'a' ), 'op' => 'EQUAL', 'value' => array( 'stringValue' => 'x' ), 'transform' => array() ) ),
            array( 'writes' => array() ),
            array( 'compositeFilter' => array( 'op' => 'XOR', 'filters' => array() ) ),
            array( 'compositeFilter' => array( 'op' => 'AND', 'filters' => array( array( 'update' => array() ) ) ) ),
            array( 'unaryFilter' => array( 'field' => array( 'fieldPath' => 'a' ), 'op' => 'IS_NULL' ), 'fieldFilter' => array() ),
        );
        foreach ( $refused as $where )
            $this->assertNotNull( expImportFirestoreSource::checkWhere( $where ), json_encode( $where ) );
        foreach ( array( array( 'where' => '{"writes": []}' ), array( 'collection' => 'customers/c-1' ), array( 'collection' => 'a/../b' ),
                         array( 'mode' => 'list', 'where' => '{"unaryFilter": {"field": {"fieldPath": "a"}, "op": "IS_NULL"}}' ) ) as $given )
        {
            $result = expImportFirestoreSource::test( $this->firestore( $given ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests, 'a refused import sends nothing, not even a token request' );
    }

    public function testFirestoreQueryPagesWithACursor(): void
    {
        $records = $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'page_size' => 2, 'fields' => "title\nviews" ) ), '_id' );
        $this->assertSame( array( 'a-1', 'a-2', 'a-3', 'a-4', 'a-5' ), array_keys( $records ) );
        $this->assertSame( array( 'POST /token', 'POST /v1/projects/example-project/databases/(default)/documents:runQuery',
                                  'POST /v1/projects/example-project/databases/(default)/documents:runQuery',
                                  'POST /v1/projects/example-project/databases/(default)/documents:runQuery' ), $this->routes() );
        $first = $this->requests[1]['json']['structuredQuery'];
        $this->assertSame( array( array( 'collectionId' => 'articles', 'allDescendants' => false ) ), $first['from'] );
        $this->assertSame( array( array( 'field' => array( 'fieldPath' => '__name__' ), 'direction' => 'ASCENDING' ) ), $first['orderBy'] );
        $this->assertSame( array( 'fields' => array( array( 'fieldPath' => 'title' ), array( 'fieldPath' => 'views' ) ) ), $first['select'] );
        $this->assertSame( 2, $first['limit'] );
        $this->assertArrayNotHasKey( 'startAt', $first );
        $this->assertSame( array( 'values' => array( array( 'referenceValue' => $this->documents[1]['name'] ) ), 'before' => false ),
                           $this->requests[2]['json']['structuredQuery']['startAt'] );
        $this->assertSame( 'https://www.googleapis.com/auth/cloud-platform.read-only', $this->grants[0]['scope'] );
        $this->assertSame( 'articles/a-3', $records['a-3']['_path'] );
        $this->assertSame( 300, $records['a-3']['views'] );
        $this->assertSame( 'Desk', $records['a-3']['author.name'] );
        $this->assertSame( '2026-02-03T10:00:00.000000Z', $records['a-3']['_update_time'] );
        // an order of the import: its field's value and the name are the cursor, the name in the same direction
        $this->requests = array();
        $records = $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'page_size' => 2, 'order_by' => 'views:desc', 'scope' => 'datastore' ) ), '_id' );
        $this->assertSame( array( 'a-5', 'a-4', 'a-3', 'a-2', 'a-1' ), array_keys( $records ) );
        $query = $this->requests[1]['json']['structuredQuery'];
        $this->assertSame( array( array( 'field' => array( 'fieldPath' => 'views' ), 'direction' => 'DESCENDING' ),
                                  array( 'field' => array( 'fieldPath' => '__name__' ), 'direction' => 'DESCENDING' ) ), $query['orderBy'] );
        $this->assertSame( array( array( 'integerValue' => '400' ), array( 'referenceValue' => $this->documents[3]['name'] ) ),
                           $this->requests[2]['json']['structuredQuery']['startAt']['values'] );
        $this->assertSame( 'https://www.googleapis.com/auth/datastore', end( $this->grants )['scope'] );
        // offset paging, the editor's sample, a subcollection
        $this->requests = array();
        $this->assertCount( 5, $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'page_size' => 2, 'paging' => 'offset' ) ), '_id' ) );
        $this->assertSame( array( null, 2, 4 ), array_map( function ( $r ) { return $r['json']['structuredQuery']['offset'] ?? null; }, $this->requests ) );
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'page_size' => 2 ) ), '_id', 3 ) );
        $this->assertSame( 1, $this->requests[1]['json']['structuredQuery']['limit'], 'the last page asks only for what is missing' );
        $this->requests = array();
        $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'collection' => 'customers/c-1/orders', 'all_descendants' => true ) ), '_id' );
        $this->assertSame( 'POST /v1/projects/example-project/databases/(default)/documents/customers/c-1:runQuery', $this->routes()[0] );
        $this->assertSame( array( array( 'collectionId' => 'orders', 'allDescendants' => true ) ), $this->requests[0]['json']['structuredQuery']['from'] );
    }

    public function testFirestoreListPagesWithPageToken(): void
    {
        $records = $this->fetched( 'expImportFirestoreSource', $this->firestore( array( 'mode' => 'list', 'page_size' => 2, 'fields' => 'title', 'order_by' => 'title' ) ), '_id' );
        $this->assertCount( 5, $records );
        $routes = $this->routes();
        $this->assertSame( 'GET /v1/projects/example-project/databases/(default)/documents/articles?pageSize=2&orderBy=title&mask.fieldPaths=title', $routes[1] );
        $this->assertSame( 'GET /v1/projects/example-project/databases/(default)/documents/articles?pageSize=2&pageToken=page-2&orderBy=title&mask.fieldPaths=title', $routes[2] );
        $this->assertCount( 4, $routes );
    }

    public function testFirestoreTypedValues(): void
    {
        $record = expImportFirestoreSource::document( array( 'name' => 'projects/p/databases/(default)/documents/shops/s-1', 'fields' => array(
            's' => array( 'stringValue' => 'text' ), 'i' => array( 'integerValue' => '42' ), 'huge' => array( 'integerValue' => '92233720368547758070' ),
            'd' => array( 'doubleValue' => 1.5 ), 'nan' => array( 'doubleValue' => 'NaN' ), 'b' => array( 'booleanValue' => true ),
            't' => array( 'timestampValue' => '2026-04-02T08:30:00.250Z' ), 'n' => array( 'nullValue' => null ),
            'm' => array( 'mapValue' => array( 'fields' => array( 'city' => array( 'stringValue' => 'Example City' ), 'geo' => array( 'mapValue' => array( 'fields' => array(
                'lat' => array( 'doubleValue' => 48.1 ) ) ) ) ) ) ),
            'em' => array( 'mapValue' => array() ),
            'a' => array( 'arrayValue' => array( 'values' => array( array( 'stringValue' => 'x' ), array( 'integerValue' => '2' ) ) ) ),
            'ea' => array( 'arrayValue' => array() ),
            'r' => array( 'referenceValue' => 'projects/p/databases/(default)/documents/authors/au-1' ),
            'g' => array( 'geoPointValue' => array( 'latitude' => 48.137, 'longitude' => 11.575 ) ),
            'bytes' => array( 'bytesValue' => base64_encode( "\x00\xff" ) ) ) ) );
        $this->assertSame( 's-1', $record['_id'] );
        $this->assertSame( 'shops/s-1', $record['_path'] );
        $this->assertSame( 'text', $record['s'] );
        $this->assertSame( 42, $record['i'] );
        $this->assertSame( '92233720368547758070', $record['huge'], 'beyond PHP\'s integers: its text' );
        $this->assertSame( 1.5, $record['d'] );
        $this->assertSame( 'NaN', $record['nan'] );
        $this->assertTrue( $record['b'] );
        $this->assertSame( '2026-04-02T08:30:00.250Z', $record['t'] );
        $this->assertNull( $record['n'] );
        $this->assertSame( 'Example City', $record['m.city'] );
        $this->assertSame( 48.1, $record['m.geo.lat'] );
        $this->assertSame( '{}', $record['em'] );
        $this->assertSame( '["x",2]', $record['a'] );
        $this->assertSame( '[]', $record['ea'] );
        $this->assertSame( 'authors/au-1', $record['r'] );
        $this->assertSame( '48.137,11.575', $record['g'] );
        $this->assertSame( base64_encode( "\x00\xff" ), $record['bytes'] );
        $this->assertSame( 'x, 2', expImportFirestoreSource::document( array( 'name' => 'x/documents/a/b', 'fields' => array(
            'a' => array( 'arrayValue' => array( 'values' => array( array( 'stringValue' => 'x' ), array( 'integerValue' => '2' ) ) ) ) ) ), 'join' )['a'] );
    }

    public function testFirestoreTestConnectionAndDrivers(): void
    {
        $result = expImportFirestoreSource::test( $this->firestore( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( array( '_id', '_path', '_create_time', '_update_time', 'title', 'views', 'author.name' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( 1, $this->requests[1]['json']['structuredQuery']['limit'] );
        $this->failWith = 403;
        $result = expImportFirestoreSource::test( $this->firestore( array() ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'PERMISSION_DENIED', $result['message'] );
        $this->assertStringNotContainsString( 'ya29.', $result['message'], 'the token is masked' );
        $this->assertStringContainsString( '[access token]', $result['message'] );
        expImportFirestoreSource::$environment = array( 'allow_url_fopen' => false, 'openssl' => true );
        $drivers = expImportFirestoreSource::drivers();
        $this->assertSame( array( 'https' ), array_keys( $drivers ) );
        $this->assertFalse( $drivers['https']['available'] );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['https'] );
    }

    // ---- BigQuery ------------------------------------------------------------------------------------------------------

    protected function bigquery( array $given ): array
    {
        return $this->options( 'expImportBigQuerySource', $given + array( 'project' => 'example-project', 'credentials_secret' => 'env:SQ688_GCP_KEY',
                                                                          'query' => 'SELECT order_id, customer, total FROM shop.orders' ) );
    }

    public function testBigQuerySchemaExamplesAndSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportBigQuerySource::schema() ) );
        $this->assertSame( 'nosql', expImportBigQuerySource::group() );
        foreach ( expImportBigQuerySource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportBigQuerySource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportBigQuerySource::checkQuery( $example['options']['query'] ), $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportBigQuerySource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/bigquery-orders.ndjson', 'key' => 'order_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( '9000001', '9000002', '9000003' ), array_map( 'strval', array_keys( $records ) ) );
    }

    public function testBigQueryOnlyASingleSelect(): void
    {
        $ok = array(
            'SELECT 1',
            'select * from `p.d.t` where name = "DELETE me" -- UPDATE in a comment',
            "WITH a AS (SELECT 1 AS x) SELECT * FROM a;",
            "(SELECT 1) UNION ALL (SELECT 2)",
            "SELECT IF(x > 1, 'a', 'b'), REPEAT('-', 3), `update`, '''drop\ntable''' FROM t /* CREATE */ # MERGE",
            "SELECT * FROM t FOR SYSTEM_TIME AS OF TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR)",
        );
        foreach ( $ok as $sql )
            $this->assertNull( expImportBigQuerySource::checkQuery( $sql ), $sql );
        $refused = array(
            'DELETE FROM t WHERE true', 'INSERT INTO t VALUES (1)', 'UPDATE t SET a = 1 WHERE true', 'MERGE t USING s ON false WHEN NOT MATCHED THEN INSERT ROW',
            'TRUNCATE TABLE t', 'CREATE TABLE x AS SELECT 1', 'CREATE TEMP FUNCTION f() AS (1); SELECT f()', 'DROP TABLE t', 'ALTER TABLE t ADD COLUMN c INT64',
            "EXPORT DATA OPTIONS(uri='gs://b/*.csv', format='CSV') AS SELECT 1", "LOAD DATA INTO t FROM FILES(uris=['gs://b/x'])", 'CALL p()',
            'DECLARE x INT64; SELECT x', 'BEGIN SELECT 1; END', 'SELECT 1; SELECT 2', 'SELECT 1; DELETE FROM t WHERE true',
            "EXECUTE IMMEDIATE 'DELETE FROM t WHERE true'", 'GRANT `roles/bigquery.dataViewer` ON TABLE t TO "user:x@example.invalid"',
            "SELECT * FROM EXTERNAL_QUERY('conn', 'DELETE FROM x')", "SELECT 'unclosed", 'SELECT 1 /* unclosed', 'WITH x AS (DELETE FROM t) SELECT 1',
            "SELECT r'a\\' ; DELETE FROM t WHERE true; SELECT '", '',
        );
        foreach ( $refused as $sql )
            $this->assertNotNull( expImportBigQuerySource::checkQuery( $sql ), $sql );
        $result = expImportBigQuerySource::test( $this->bigquery( array( 'query' => 'DELETE FROM shop.orders WHERE true' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'must start with SELECT', $result['message'] );
        $this->assertSame( array(), $this->requests, 'a refused query sends nothing' );
    }

    public function testBigQueryPollsTheJobAndPagesWithPageToken(): void
    {
        $records = $this->fetched( 'expImportBigQuerySource', $this->bigquery( array( 'page_size' => 2, 'location' => 'EU' ) ), 'order_id' );
        $this->assertSame( array( '9000001', '9000002', '9000003', '9000004', '9000005' ), array_map( 'strval', array_keys( $records ) ) );
        $this->assertSame( array( 'POST /token', 'POST /bigquery/v2/projects/example-project/queries',
                                  'GET /bigquery/v2/projects/example-project/queries/job_1?maxResults=2&timeoutMs=10000&formatOptions.useInt64Timestamp=true&location=EU',
                                  'GET /bigquery/v2/projects/example-project/queries/job_1?maxResults=2&timeoutMs=10000&formatOptions.useInt64Timestamp=true&location=EU&pageToken=page-2',
                                  'GET /bigquery/v2/projects/example-project/queries/job_1?maxResults=2&timeoutMs=10000&formatOptions.useInt64Timestamp=true&location=EU&pageToken=page-4' ),
                           $this->routes() );
        $body = $this->requests[1]['json'];
        $this->assertFalse( $body['useLegacySql'] );
        $this->assertSame( '1073741824', $body['maximumBytesBilled'], 'the default limit of the settings' );
        $this->assertSame( 'EU', $body['location'] );
        $this->assertArrayNotHasKey( 'dryRun', $body );
        $this->assertSame( 'https://www.googleapis.com/auth/bigquery', $this->grants[0]['scope'] );
        $this->assertSame( array( 'order_id' => '9000003', 'customer.name' => 'Customer 3', 'customer.country' => 'DE', 'total' => '13.50' ), $records['9000003'] );
        // the import's own limit, and the editor's sample
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( 'expImportBigQuerySource', $this->bigquery( array( 'maximum_bytes_billed' => 5000000 ) ), 'order_id', 3 ) );
        $this->assertSame( '5000000', $this->requests[0]['json']['maximumBytesBilled'], 'the token is cached for the run' );
        $this->assertSame( 3, $this->requests[0]['json']['maxResults'] );
    }

    public function testBigQueryTypes(): void
    {
        $fields = array( array( 'name' => 'i', 'type' => 'INT64' ), array( 'name' => 'n', 'type' => 'NUMERIC' ), array( 'name' => 'bn', 'type' => 'BIGNUMERIC' ),
                         array( 'name' => 'f', 'type' => 'FLOAT64' ), array( 'name' => 'inf', 'type' => 'FLOAT' ), array( 'name' => 'b', 'type' => 'BOOL' ),
                         array( 'name' => 'ts', 'type' => 'TIMESTAMP' ), array( 'name' => 'tsf', 'type' => 'TIMESTAMP' ), array( 'name' => 'd', 'type' => 'DATE' ),
                         array( 'name' => 'dt', 'type' => 'DATETIME' ), array( 'name' => 'by', 'type' => 'BYTES' ), array( 'name' => 'nul', 'type' => 'STRING' ),
                         array( 'name' => 'rec', 'type' => 'RECORD', 'fields' => array( array( 'name' => 'a', 'type' => 'STRING' ),
                             array( 'name' => 'inner', 'type' => 'RECORD', 'fields' => array( array( 'name' => 'b', 'type' => 'INTEGER' ) ) ) ) ),
                         array( 'name' => 'tags', 'type' => 'STRING', 'mode' => 'REPEATED' ),
                         array( 'name' => 'items', 'type' => 'RECORD', 'mode' => 'REPEATED', 'fields' => array( array( 'name' => 'sku', 'type' => 'STRING' ),
                                                                                                                 array( 'name' => 'qty', 'type' => 'INT64' ) ) ),
                         array( 'name' => 'old', 'type' => 'TIMESTAMP' ) );
        $row = array( 'f' => array( array( 'v' => '9007199254740993' ), array( 'v' => '12.50' ), array( 'v' => '1.23456789012345678901234567890' ),
                                    array( 'v' => '1.5' ), array( 'v' => 'Infinity' ), array( 'v' => 'true' ), array( 'v' => '1775118600250000' ),
                                    array( 'v' => '1.7751186E9' ), array( 'v' => '2026-04-02' ), array( 'v' => '2026-04-02T08:30:00' ), array( 'v' => base64_encode( 'ab' ) ),
                                    array( 'v' => null ), array( 'v' => array( 'f' => array( array( 'v' => 'x' ), array( 'v' => array( 'f' => array( array( 'v' => '7' ) ) ) ) ) ) ),
                                    array( 'v' => array( array( 'v' => 'green' ), array( 'v' => 'tea' ) ) ),
                                    array( 'v' => array( array( 'v' => array( 'f' => array( array( 'v' => 'tea-1' ), array( 'v' => '2' ) ) ) ) ) ),
                                    array( 'v' => '-1000000' ) ) );
        $record = expImportBigQuerySource::row( $fields, $row );
        $this->assertSame( '9007199254740993', $record['i'], 'INT64 as text' );
        $this->assertSame( '12.50', $record['n'] );
        $this->assertSame( '1.23456789012345678901234567890', $record['bn'] );
        $this->assertSame( 1.5, $record['f'] );
        $this->assertSame( 'Infinity', $record['inf'] );
        $this->assertTrue( $record['b'] );
        $this->assertSame( '2026-04-02T08:30:00.25Z', $record['ts'] );
        $this->assertSame( '2026-04-02T08:30:00Z', $record['tsf'] );
        $this->assertSame( '1969-12-31T23:59:59Z', $record['old'] );
        $this->assertSame( '2026-04-02', $record['d'] );
        $this->assertSame( '2026-04-02T08:30:00', $record['dt'] );
        $this->assertSame( base64_encode( 'ab' ), $record['by'] );
        $this->assertNull( $record['nul'] );
        $this->assertSame( 'x', $record['rec.a'] );
        $this->assertSame( '7', $record['rec.inner.b'] );
        $this->assertSame( '["green","tea"]', $record['tags'] );
        $this->assertSame( '[{"sku":"tea-1","qty":"2"}]', $record['items'] );
        $this->assertSame( array( 'i', 'n', 'bn', 'f', 'inf', 'b', 'ts', 'tsf', 'd', 'dt', 'by', 'nul', 'rec.a', 'rec.inner.b', 'tags', 'items', 'old' ),
                           expImportBigQuerySource::columns( $fields ) );
    }

    public function testBigQueryTestConnectionIsADryRun(): void
    {
        $result = expImportBigQuerySource::test( $this->bigquery( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( array( 'order_id', 'customer.name', 'customer.country', 'total' ), $result['columns'] );
        $this->assertStringContainsString( 'would process 1.2 MB (limit 1.1 GB)', $result['message'] );
        $this->assertTrue( $this->requests[1]['json']['dryRun'] );
        $this->assertCount( 2, $this->requests, 'a dry run, no job is read' );
        // a query that would scan more than its limit
        $result = expImportBigQuerySource::test( $this->bigquery( array( 'maximum_bytes_billed' => 1000 ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'a run would fail', $result['message'] );
        // the settings' limit
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'GoogleCloudSourceSettings', 'MaximumBytesBilled', 2000000 );
        $this->requests = array();
        expImportBigQuerySource::test( $this->bigquery( array() ) );
        $this->assertSame( '2000000', $this->requests[0]['json']['maximumBytesBilled'] );
        // an error quoting the token
        $this->failWith = 403;
        $result = expImportBigQuerySource::test( $this->bigquery( array() ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringNotContainsString( 'ya29.', $result['message'] );
        $this->assertStringContainsString( 'access was refused', $result['message'] );
        $this->assertSame( array( 'https' ), array_keys( expImportBigQuerySource::drivers() ) );
    }
}
