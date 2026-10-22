<?php

use PHPUnit\Framework\TestCase;

/**
 * The Firebase Realtime Database source against a scripted database (expImportPlatformHttp::$transport): only GET,
 * paging in key order with orderBy="$key", limitToFirst and startAt, the shallow listing of the keys first, the order
 * of keys, flattening, the database secret and the service account, Test connection with masked errors, the hosts and
 * the driver list. No request reaches Firebase.
 */
class FirebaseSourceTest extends TestCase
{
    /** @var string|null */
    protected static $privateKey = null;

    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, query */
    protected $requests = array();

    /** @var array path => the data under it */
    protected $tree = array();

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
        $pem = '';
        openssl_pkey_export( $key, $pem );
        self::$privateKey = $pem;
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-firebasetest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_FB_KEY=' . json_encode( array( 'type' => 'service_account', 'private_key' => self::$privateKey,
                                                      'client_email' => 'reader@example-app.iam.gserviceaccount.com' ) ) );
        putenv( 'SQ688_FB_SECRET=fb-Database-Secret-Very-44' );
        expImportGoogleAuth::reset();
        expImportPlatformHttp::$transport = array( $this, 'answer' );
        $this->tree = array( 'articles' => array(
            'c' => array( 'title' => 'Third', 'author' => array( 'name' => 'Desk', 'email' => 'desk@example.invalid' ), 'tags' => array( 'tea', 'green' ) ),
            '10' => array( 'title' => 'Ten', 'views' => 10 ),
            'a' => array( 'title' => 'First', 'draft' => true ),
            '2' => array( 'title' => 'Two', 'views' => 2 ),
            'b' => 'just text',
        ), 'settings' => array( 'motd' => 'Hello' ) );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportGoogleAuth::reset();
        expImportFirebaseSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'GoogleCloudSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_FB_KEY' );
        putenv( 'SQ688_FB_SECRET' );
    }

    /** The scripted database: the token endpoint and GET <path>.json with shallow, orderBy, startAt, endAt, limitToFirst. */
    public function answer( $method, $url, array $headers, $body )
    {
        $reply = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $url === expImportGoogleAuth::TOKEN_URL )
        {
            $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'query' => array() );
            return $reply( 200, array( 'access_token' => 'ya29.fb-token-1', 'expires_in' => 3600 ) );
        }
        parse_str( (string)parse_url( $url, PHP_URL_QUERY ), $query );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'query' => $query );
        $this->assertSame( 'GET', $method, 'only GET is sent' );
        $this->assertNull( $body );
        $auth = expImportPlatformHttp::header( $headers, 'Authorization' );
        if ( $auth !== 'Bearer ya29.fb-token-1' && ( !isset( $query['auth'] ) || $query['auth'] !== 'fb-Database-Secret-Very-44' ) )
            return $reply( 401, array( 'error' => 'Permission denied' ) );
        $path = trim( (string)preg_replace( '/\.json$/', '', rawurldecode( (string)parse_url( $url, PHP_URL_PATH ) ) ), '/' );
        if ( $path === 'denied' )
            return $reply( 401, array( 'error' => 'Permission denied (auth ' . ( $query['auth'] ?? '' ) . ')' ) );
        $data = $path === '' ? $this->tree : null;
        if ( $path !== '' )
        {
            $data = $this->tree;
            foreach ( explode( '/', $path ) as $part )
                $data = is_array( $data ) && isset( $data[$part] ) ? $data[$part] : null;
        }
        if ( isset( $query['shallow'] ) )
        {
            $this->assertArrayNotHasKey( 'orderBy', $query, 'shallow is not combined with a query' );
            if ( !is_array( $data ) )
                return $reply( 200, $data );
            $out = array();
            foreach ( $data as $key => $value )
                $out[$key] = is_array( $value ) ? true : $value;
            return $reply( 200, $out );
        }
        if ( isset( $query['orderBy'] ) && is_array( $data ) )
        {
            $this->assertSame( '"$key"', $query['orderBy'] );
            $keys = array_map( 'strval', array_keys( $data ) );
            usort( $keys, array( 'expImportFirebaseSource', 'compareKeys' ) );
            $start = isset( $query['startAt'] ) ? json_decode( $query['startAt'] ) : null;
            $end = isset( $query['endAt'] ) ? json_decode( $query['endAt'] ) : null;
            $out = array();
            foreach ( $keys as $key )
            {
                if ( $start !== null && expImportFirebaseSource::compareKeys( $key, $start ) < 0 )
                    continue;
                if ( $end !== null && expImportFirebaseSource::compareKeys( $key, $end ) > 0 )
                    continue;
                $out[$key] = $data[$key];
                if ( isset( $query['limitToFirst'] ) && count( $out ) >= (int)$query['limitToFirst'] )
                    break;
            }
            // the REST API gives no order: the source sorts itself
            return $reply( 200, array_reverse( $out, true ) );
        }
        return $reply( 200, $data );
    }

    /** @return array validated options */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportFirebaseSource::schema(), $given + array(
            'database_url' => 'https://example-app-default-rtdb.firebaseio.com', 'path' => 'articles', 'credentials_secret' => 'env:SQ688_FB_KEY' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot of a fetch, by _key */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportFirebaseSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportFirebaseSource( array( 'file' => $file, 'key' => '_key' ) ) )->records() as $record )
            $out[(string)$record->ref] = $record->data;
        return $out;
    }

    /** @return array[] the queries of the database requests */
    protected function queries(): array
    {
        $out = array();
        foreach ( $this->requests as $request )
            if ( $request['url'] !== expImportGoogleAuth::TOKEN_URL )
                $out[] = $request['query'];
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportFirebaseSource::schema() ) );
        $this->assertSame( 'nosql', expImportFirebaseSource::group() );
        $this->assertSame( array( 'remote' ), expImportFirebaseSource::origins() );
        foreach ( expImportFirebaseSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportFirebaseSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportFirebaseSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/firebase-articles.ndjson', 'key' => '_key' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertCount( 3, $records );
        $this->assertSame( 'Tea desk', $records['-Nx1aExampleKey0001']['author.name'] );
    }

    public function testKeyOrder(): void
    {
        $keys = array( 'b', '10', '-1', 'a', '2', '2147483648', '007', 'A' );
        usort( $keys, array( 'expImportFirebaseSource', 'compareKeys' ) );
        $this->assertSame( array( '-1', '2', '10', '007', '2147483648', 'A', 'a', 'b' ), $keys, '32-bit integers first by value, then the rest by text' );
    }

    public function testPagesInKeyOrderWithStartAt(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2 ) ) );
        $this->assertSame( array( '2', '10', 'a', 'b', 'c' ), array_map( 'strval', array_keys( $records ) ) );
        $this->assertSame( array( 'orderBy' => '"$key"', 'limitToFirst' => '2' ), $this->queries()[0] );
        $this->assertSame( array( 'orderBy' => '"$key"', 'limitToFirst' => '3', 'startAt' => '"10"' ), $this->queries()[1] );
        $this->assertSame( array( 'orderBy' => '"$key"', 'limitToFirst' => '3', 'startAt' => '"b"' ), $this->queries()[2] );
        $this->assertCount( 3, $this->queries() );
        $this->assertSame( 'https://example-app-default-rtdb.firebaseio.com/articles.json', strtok( $this->requests[1]['url'], '?' ) );
        $this->assertContains( 'Authorization: Bearer ya29.fb-token-1', $this->requests[1]['headers'] );
        $this->assertSame( array( '_key' => 'c', 'title' => 'Third', 'author.name' => 'Desk', 'author.email' => 'desk@example.invalid', 'tags' => '["tea","green"]' ), $records['c'] );
        $this->assertSame( array( '_key' => 'b', 'value' => 'just text' ), $records['b'] );
        $this->assertSame( 10, $records['10']['views'] );
        // the editor's sample and the option limit
        $this->requests = array();
        $this->assertSame( array( '2', '10', 'a' ), array_map( 'strval', array_keys( $this->fetched( $this->options( array( 'page_size' => 2, 'arrays' => 'join' ) ), 3 ) ) ) );
        $this->assertSame( '2', $this->queries()[1]['limitToFirst'], 'one more than the one missing: the first is the last key of the page before' );
        $this->requests = array();
        $this->assertCount( 1, $this->fetched( $this->options( array( 'limit' => 1 ) ) ) );
        $this->assertSame( '1', $this->queries()[0]['limitToFirst'] );
    }

    public function testShallowKeysFirst(): void
    {
        $records = $this->fetched( $this->options( array( 'shallow' => true, 'page_size' => 2, 'auth' => 'database_secret', 'database_secret' => 'env:SQ688_FB_SECRET' ) ) );
        $this->assertSame( array( '2', '10', 'a', 'b', 'c' ), array_map( 'strval', array_keys( $records ) ) );
        $queries = $this->queries();
        $this->assertSame( array( 'shallow' => 'true', 'auth' => 'fb-Database-Secret-Very-44' ), $queries[0] );
        $this->assertSame( array( 'orderBy' => '"$key"', 'startAt' => '"2"', 'endAt' => '"10"', 'auth' => 'fb-Database-Secret-Very-44' ), $queries[1] );
        $this->assertSame( array( 'orderBy' => '"$key"', 'startAt' => '"a"', 'endAt' => '"b"', 'auth' => 'fb-Database-Secret-Very-44' ), $queries[2] );
        $this->assertSame( array( 'orderBy' => '"$key"', 'startAt' => '"c"', 'endAt' => '"c"', 'auth' => 'fb-Database-Secret-Very-44' ), $queries[3] );
        $this->assertCount( 4, $queries );
        $this->assertSame( 'tea, green', $this->fetched( $this->options( array( 'shallow' => true, 'arrays' => 'join' ) ) )['c']['tags'] );
        // a path that holds a plain value is one record
        $this->requests = array();
        $this->assertSame( array( 'motd' => array( '_key' => 'motd', 'value' => 'Hello' ) ), $this->fetched( $this->options( array( 'path' => 'settings/motd', 'shallow' => true ) ) ) );
        $this->assertSame( array( 'motd' => array( '_key' => 'motd', 'value' => 'Hello' ) ), $this->fetched( $this->options( array( 'path' => 'settings/motd' ) ) ) );
        $this->assertSame( array(), $this->fetched( $this->options( array( 'path' => 'nothing/here' ) ) ), 'an empty path gives no record' );
    }

    public function testTestConnectionAndMasking(): void
    {
        $result = expImportFirebaseSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertStringContainsString( 'has 5 children', $result['message'] );
        $this->assertSame( array( '_key', 'title', 'views' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        // an error that quotes the database secret: masked (and the address in the message has no query)
        $result = expImportFirebaseSource::test( $this->options( array( 'path' => 'denied', 'auth' => 'database_secret', 'database_secret' => 'env:SQ688_FB_SECRET' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringNotContainsString( 'fb-Database-Secret-Very-44', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_FB_SECRET]', $result['message'] );
        // without authentication the rules refuse
        $result = expImportFirebaseSource::test( $this->options( array( 'auth' => 'none' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'access was refused', $result['message'] );
        // a secret that is not set
        $this->requests = array();
        $result = expImportFirebaseSource::test( $this->options( array( 'auth' => 'database_secret', 'database_secret' => 'sq688_no_such_secret' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_secret' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testHostsAndPaths(): void
    {
        foreach ( array( array( 'database_url' => 'http://example-app.firebaseio.com' ), array( 'database_url' => 'https://example.org' ),
                         array( 'database_url' => 'https://firebaseio.com.example.org' ), array( 'database_url' => 'https://example-app.firebaseio.com/articles' ),
                         array( 'database_url' => 'https://localhost:9000/?ns=example-app' ), array( 'database_url' => 'https://example-app.firebaseio.com/?print=pretty' ) ) as $given )
        {
            $result = expImportFirebaseSource::test( $this->options( $given ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        foreach ( array( 'a/../b', 'a.b', 'a$b', 'a#b', 'a[0]', 'a?x=1' ) as $path )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportFirebaseSource::schema(), array( 'database_url' => 'https://x.firebaseio.com', 'path' => $path ) );
            $refused = isset( $errors['path'] ) || !expImportFirebaseSource::test( array( 'database_url' => 'https://x.firebaseio.com', 'path' => $path, 'auth' => 'none' ) )['ok'];
            $this->assertTrue( $refused, $path );
        }
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused address' );
        // the emulator, listed
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'GoogleCloudSourceSettings', 'AllowedHosts', array( 'localhost' ) );
        $result = expImportFirebaseSource::test( $this->options( array( 'database_url' => 'http://localhost:9000/?ns=example-app' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'example-app', end( $this->requests )['query']['ns'] );
        $this->assertStringStartsWith( 'http://localhost:9000/articles.json?', end( $this->requests )['url'] );
        // regional databases
        $this->assertTrue( expImportFirebaseSource::test( $this->options( array( 'database_url' => 'https://example-shop.europe-west1.firebasedatabase.app' ) ) )['ok'] );
    }

    public function testDrivers(): void
    {
        expImportFirebaseSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportFirebaseSource::drivers();
        $this->assertSame( array( 'https' ), array_keys( $drivers ) );
        $this->assertTrue( $drivers['https']['available'] );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['https'] );
    }
}
