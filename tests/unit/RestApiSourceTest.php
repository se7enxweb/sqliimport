<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The REST API source against a scripted API with invented data: the schema and the examples, every paging mode
 * (page number, offset, cursor, next address, Link header) and its stops (empty page, short page, total, the import's
 * own most pages, MaxPages), every authentication (bearer, basic, API key in a header or a parameter, OAuth 2.0 client
 * credentials kept for the run) with its secret masked, the waits after 429 and 503 on the test's clock, the same-site
 * rule of next addresses and Link headers, the allow-list of headers and parameters, POST with a JSON body, the
 * JSONPath subset, Test connection and drivers().
 */
class RestApiSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    const TOKEN = 'sampleRestToken-0123456789abcdef';

    const BASE = 'https://api.example.invalid/v2/products';

    protected function setUp(): void
    {
        $this->startTransport( 'restapi' );
        putenv( 'SQ688_REST_TOKEN=' . self::TOKEN );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RestApiSourceSettings', 'AllowedHosts',
            array( 'api.example.invalid', 'login.example.invalid', 'intranet.example.invalid' ) );
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_REST_TOKEN' );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowedHosts', 'AllowedTokenHosts', 'MaxPages', 'MaxWaitSeconds' ) as $name )
            $ini->removeSetting( 'RestApiSourceSettings', $name );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportRestApiSource::schema(), $more + array( 'base_url' => self::BASE ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the records of a fetch */
    protected function fetch( array $more, int $limit = 0 ): array
    {
        $target = $this->dir . '/source.ndjson';
        expImportRestApiSource::fetch( $this->options( $more ), $target, $limit );
        return $this->snapshot( $target );
    }

    /** @return string the message of a failing fetch */
    protected function failure( array $more ): string
    {
        try
        {
            $this->fetch( $more );
        }
        catch ( expImportSourceException $e )
        {
            return $e->getMessage();
        }
        $this->fail( 'the fetch should fail: ' . json_encode( $more ) );
    }

    /** @return array[] n records with ids from $from */
    protected function items( int $from, int $n ): array
    {
        $out = array();
        for ( $i = 0; $i < $n; $i++ )
            $out[] = array( 'id' => $from + $i, 'name' => 'Item ' . ( $from + $i ) );
        return $out;
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportRestApiSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['auth_secret']['type'] );
        $this->assertSame( 'data', expImportRestApiSource::group() );
        $this->assertSame( array( 'remote' ), expImportRestApiSource::origins() );
        $this->assertSame( 'rest_api', expImportRestApiSource::identifier() );
        foreach ( expImportRestApiSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            foreach ( $values['headers'] as $line )
                $this->assertIsArray( expImportRestApiSource::checkHeader( $line ), $line );
            foreach ( $values['query'] as $line )
                $this->assertIsArray( expImportRestApiSource::checkParam( $line ), $line );
            if ( !isset( $example['file'] ) )
                continue;
            $records = $this->readExample( 'expImportRestApiSource', $example );
            $this->assertCount( 3, $records );
            $this->assertSame( 'P-1001', $records[0]->ref );
            $this->assertSame( 'EUR', $records[0]->data['price.currency'] );
        }
    }

    public function testHeaderAndParameterAllowList(): void
    {
        foreach ( array( 'Accept: application/vnd.example+json', 'Accept-Language: de', 'X-Client: website-import', 'X-Requested-With: import' ) as $line )
            $this->assertIsArray( expImportRestApiSource::checkHeader( $line ), $line );
        foreach ( array( 'Authorization: Bearer x', 'authorization: Basic eA==', 'Cookie: s=1', 'Host: other.example.invalid', 'Content-Type: text/plain',
                         'Proxy-Authorization: x', 'X-HTTP-Method-Override: DELETE', 'X-Method-Override: PUT', 'X-HTTP-Method: PATCH',
                         'X-Forwarded-For: 10.0.0.1', 'X-Forwarded-Host: a', 'X-Real-IP: 10.0.0.1', 'X-Original-URL: /admin', 'X-Api-Key: abc',
                         'X-Auth-Token: abc', 'X-Session: abc', 'X-Access-Token: abc', 'Bad Name: x', 'NoColon', ': x' ) as $line )
            $this->assertIsString( expImportRestApiSource::checkHeader( $line ), $line );
        foreach ( array( 'status=active', 'sort_key=name', 'fields[]=id', 'q=tea pot' ) as $line )
            $this->assertIsArray( expImportRestApiSource::checkParam( $line ), $line );
        foreach ( array( 'api_key=abc', 'apikey=abc', 'access_token=abc', 'token=abc', 'key=abc', 'client_secret=abc', 'password=abc', 'sig=abc', 'a b=1', 'novalue' ) as $line )
            $this->assertIsString( expImportRestApiSource::checkParam( $line ), $line );
        // nothing is sent with a refused option
        $this->script( array() );
        foreach ( array( array( 'headers' => 'Authorization: Bearer abc' ), array( 'headers' => 'Cookie: a=b' ), array( 'query' => 'api_key=abc' ),
                         array( 'record_path' => 'items[*].id' ), array( 'paging' => 'cursor' ), array( 'paging' => 'next_url' ),
                         array( 'method' => 'POST' ), array( 'method' => 'POST', 'body' => '[1,2]' ), array( 'body' => '{"q":1}' ),
                         array( 'auth' => 'api_key_header', 'auth_secret' => 'env:SQ688_REST_TOKEN', 'api_key_name' => 'Cookie' ),
                         array( 'auth' => 'bearer' ) ) as $more )
            $this->assertFalse( expImportRestApiSource::test( $this->options( $more ) )['ok'], json_encode( $more ) );
        $this->assertSame( array(), $this->requests );
        // the extra headers are sent
        $this->script( array( array( 200, array( 'data' => array() ) ) ) );
        $this->fetch( array( 'headers' => "Accept-Language: de\nX-Client: website-import" ) );
        $this->assertSame( 'de', $this->header( $this->requests[0], 'Accept-Language' ) );
        $this->assertSame( 'website-import', $this->header( $this->requests[0], 'X-Client' ) );
        $this->assertSame( 'application/json', $this->header( $this->requests[0], 'Accept' ) );
        $this->assertSame( '', $this->header( $this->requests[0], 'Authorization' ) );
    }

    public function testJsonPathSubsetAndRecordList(): void
    {
        $this->assertSame( '', expImportRestApiSource::dottedPath( '$' ) );
        $this->assertSame( 'data.items', expImportRestApiSource::dottedPath( 'data.items' ) );
        $this->assertSame( 'data.items', expImportRestApiSource::dottedPath( '$.data.items' ) );
        $this->assertSame( 'results.0.hits', expImportRestApiSource::dottedPath( 'results[0].hits' ) );
        $this->assertSame( 'a.b c', expImportRestApiSource::dottedPath( "a['b c']" ) );
        $this->assertSame( '0.x', expImportRestApiSource::dottedPath( '$[0].x' ) );
        foreach ( array( 'a[*]', '$..a', 'a[?(@.x)]', 'a.', '.a', 'a[', 'a..b' ) as $path )
            $this->assertNull( expImportRestApiSource::dottedPath( $path ), $path );
        $this->assertSame( 7, expImportRestApiSource::at( array( 'meta' => array( 'total' => 7 ) ), '$.meta.total' ) );
        $this->assertNull( expImportRestApiSource::at( array( 'meta' => array() ), 'meta.total' ) );
        // the list at the path, the top list, or data / items / results / records
        $this->script( array( array( 200, array( 'results' => array( array( 'hits' => array( array( 'id' => 1, 'author' => array( 'name' => 'Desk' ), 'tags' => array( 'a', 'b' ) ) ) ) ) ) ),
                              array( 200, array( array( 'id' => 2 ) ) ),
                              array( 200, array( 'items' => array( array( 'id' => 3 ) ) ) ),
                              array( 200, array( 'meta' => array() ) ) ) );
        $records = $this->fetch( array( 'record_path' => 'results[0].hits', 'arrays' => 'join' ) );
        $this->assertSame( array( 'id' => 1, 'author.name' => 'Desk', 'tags' => 'a, b' ), $records[0] );
        $this->assertSame( 2, $this->fetch( array() )[0]['id'] );
        $this->assertSame( 3, $this->fetch( array() )[0]['id'] );
        $this->assertStringContainsString( 'give the record list', $this->failure( array() ) );
    }

    public function testPageNumberPaging(): void
    {
        $this->script( array( array( 200, array( 'data' => $this->items( 1, 2 ), 'meta' => array( 'total' => 5 ) ) ),
                              array( 200, array( 'data' => $this->items( 3, 2 ), 'meta' => array( 'total' => 5 ) ) ),
                              array( 200, array( 'data' => $this->items( 5, 1 ), 'meta' => array( 'total' => 5 ) ) ) ) );
        $records = $this->fetch( array( 'paging' => 'page', 'page_size' => 2, 'size_param' => 'per_page', 'record_path' => 'data', 'query' => "status=active\nfields[]=id" ) );
        $this->assertCount( 5, $records );
        $this->assertSame( self::BASE . '?status=active&fields[]=id&page=1&per_page=2', $this->requests[0][1] );
        $this->assertSame( self::BASE . '?status=active&fields[]=id&page=3&per_page=2', $this->requests[2][1] );
        $this->assertSame( 'GET', $this->requests[0][0] );
        $this->assertNull( $this->requests[0][3] );
        // the total ends the paging without asking for an empty page
        $this->script( array( array( 200, array( 'data' => $this->items( 1, 2 ), 'meta' => array( 'total' => 2 ) ) ) ) );
        $this->assertCount( 2, $this->fetch( array( 'paging' => 'page', 'page_size' => 2, 'record_path' => 'data', 'total_path' => 'meta.total',
                                                    'page_param' => 'p', 'page_start' => 0, 'size_param' => '' ) ) );
        $this->assertCount( 1, $this->requests );
        $this->assertSame( self::BASE . '?p=0', $this->requests[0][1] );
        // an empty page ends it
        $this->script( array( array( 200, array( 'data' => $this->items( 1, 2 ) ) ), array( 200, array( 'data' => array() ) ) ) );
        $this->assertCount( 2, $this->fetch( array( 'paging' => 'page', 'page_size' => 2, 'record_path' => 'data' ) ) );
        $this->assertCount( 2, $this->requests );
    }

    public function testOffsetPagingAndItsLimits(): void
    {
        $this->script( array( array( 200, $this->items( 1, 3 ) ), array( 200, $this->items( 4, 3 ) ), array( 200, $this->items( 7, 1 ) ) ) );
        $this->assertCount( 7, $this->fetch( array( 'paging' => 'offset', 'page_size' => 3, 'offset_param' => 'skip', 'limit_param' => 'take' ) ) );
        $this->assertSame( self::BASE . '?skip=0&take=3', $this->requests[0][1] );
        $this->assertSame( self::BASE . '?skip=3&take=3', $this->requests[1][1] );
        $this->assertSame( self::BASE . '?skip=6&take=3', $this->requests[2][1] );
        // the import's own most pages stop quietly; the sample and "Most records" stop early
        $this->script( array( array( 200, $this->items( 1, 3 ) ), array( 200, $this->items( 4, 3 ) ) ) );
        $this->assertCount( 6, $this->fetch( array( 'paging' => 'offset', 'page_size' => 3, 'max_pages' => 2 ) ) );
        $this->script( array( array( 200, $this->items( 1, 3 ) ) ) );
        $this->assertCount( 2, $this->fetch( array( 'paging' => 'offset', 'page_size' => 3, 'limit' => 2 ) ) );
        $this->script( array( array( 200, $this->items( 1, 3 ) ) ) );
        $this->assertCount( 1, $this->fetch( array( 'paging' => 'offset', 'page_size' => 3 ), 1 ) );
        // more pages than MaxPages is an error
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RestApiSourceSettings', 'MaxPages', 2 );
        $this->script( array( array( 200, $this->items( 1, 3 ) ), array( 200, $this->items( 4, 3 ) ) ) );
        $this->assertStringContainsString( 'MaxPages (2)', $this->failure( array( 'paging' => 'offset', 'page_size' => 3 ) ) );
    }

    public function testCursorPaging(): void
    {
        $this->script( array( array( 200, array( 'items' => $this->items( 1, 2 ), 'meta' => array( 'next_cursor' => 'c2' ) ) ),
                              array( 200, array( 'items' => $this->items( 3, 2 ), 'meta' => array( 'next_cursor' => 'c3' ) ) ),
                              array( 200, array( 'items' => $this->items( 5, 1 ), 'meta' => array( 'next_cursor' => null ) ) ) ) );
        $this->assertCount( 5, $this->fetch( array( 'paging' => 'cursor', 'cursor_path' => 'meta.next_cursor', 'cursor_param' => 'after',
                                                    'size_param' => 'limit', 'page_size' => 2 ) ) );
        $this->assertSame( self::BASE . '?limit=2', $this->requests[0][1] );
        $this->assertSame( self::BASE . '?limit=2&after=c2', $this->requests[1][1] );
        $this->assertSame( self::BASE . '?limit=2&after=c3', $this->requests[2][1] );
        // a cursor that comes back again ends the paging (no endless loop)
        $this->script( array( array( 200, array( 'items' => $this->items( 1, 2 ), 'next' => 'same' ) ),
                              array( 200, array( 'items' => $this->items( 3, 2 ), 'next' => 'same' ) ) ) );
        $this->assertCount( 4, $this->fetch( array( 'paging' => 'cursor', 'cursor_path' => 'next', 'page_size' => 2 ) ) );
        $this->assertCount( 2, $this->requests );
    }

    public function testNextAddressFromTheAnswerSameSiteOnly(): void
    {
        $this->script( array( array( 200, array( 'data' => $this->items( 1, 2 ), 'links' => array( 'next' => '/v2/products?page=2&per_page=2' ) ) ),
                              array( 200, array( 'data' => $this->items( 3, 2 ), 'links' => array( 'next' => self::BASE . '?page=3&per_page=2' ) ) ),
                              array( 200, array( 'data' => $this->items( 5, 1 ), 'links' => array( 'next' => null ) ) ) ) );
        $this->assertCount( 5, $this->fetch( array( 'paging' => 'next_url', 'next_path' => 'links.next', 'record_path' => 'data', 'page_size' => 2, 'size_param' => 'per_page',
                                                    'query' => 'status=active' ) ) );
        $this->assertSame( self::BASE . '?status=active&per_page=2', $this->requests[0][1] );
        $this->assertSame( self::BASE . '?page=2&per_page=2', $this->requests[1][1], 'a next address carries its own parameters' );
        $this->assertSame( self::BASE . '?page=3&per_page=2', $this->requests[2][1] );
        // another host, another scheme or another port is never followed
        foreach ( array( 'https://evil.example.invalid/v2/products?page=2', 'http://api.example.invalid/v2/products?page=2',
                         'https://api.example.invalid:8443/v2/products?page=2', '//evil.example.invalid/x' ) as $next )
        {
            $this->script( array( array( 200, array( 'data' => $this->items( 1, 2 ), 'links' => array( 'next' => $next ) ) ) ) );
            $message = $this->failure( array( 'paging' => 'next_url', 'next_path' => 'links.next', 'record_path' => 'data', 'page_size' => 2, 'size_param' => 'per_page',
                                              'auth' => 'bearer', 'auth_secret' => 'env:SQ688_REST_TOKEN' ) );
            $this->assertStringContainsString( 'another site', $message, $next );
            $this->assertCount( 1, $this->requests, $next );
        }
    }

    public function testLinkHeaderPagingSameSiteOnly(): void
    {
        $this->assertSame( 'https://api.example.invalid/v2/products?page=2',
            expImportRestApiSource::linkNext( array( 'HTTP/1.1 200 OK', 'Link: <https://api.example.invalid/v2/products?page=1>; rel="first", '
                                                     . '<https://api.example.invalid/v2/products?page=2>; rel="next", <https://api.example.invalid/v2/products?page=9>; rel="last"' ),
                                              self::BASE ) );
        $this->assertSame( 'https://api.example.invalid/v2/products?page=3', expImportRestApiSource::linkNext( array( 'link: </v2/products?page=3>; rel=next' ), self::BASE ) );
        $this->assertSame( 'https://api.example.invalid/x', expImportRestApiSource::linkNext( array( 'Link: </x>; title="a"; rel="prefetch next"' ), self::BASE ) );
        $this->assertSame( '', expImportRestApiSource::linkNext( array( 'Link: </x>; rel="prev"' ), self::BASE ) );
        $this->script( array( array( 200, $this->items( 1, 2 ), array( 'Link: <' . self::BASE . '?page=2>; rel="next"' ) ),
                              array( 200, $this->items( 3, 2 ), array( 'Link: </v2/products?page=3>; rel="next", </v2/products?page=1>; rel="first"' ) ),
                              array( 200, $this->items( 5, 1 ), array( 'Link: </v2/products?page=1>; rel="first"' ) ) ) );
        $this->assertCount( 5, $this->fetch( array( 'paging' => 'link', 'size_param' => 'per_page', 'page_size' => 2 ) ) );
        $this->assertSame( self::BASE . '?per_page=2', $this->requests[0][1] );
        $this->assertSame( self::BASE . '?page=2', $this->requests[1][1] );
        $this->assertSame( self::BASE . '?page=3', $this->requests[2][1] );
        $this->script( array( array( 200, $this->items( 1, 2 ), array( 'Link: <https://cdn.example.invalid/v2/products?page=2>; rel="next"' ) ) ) );
        $this->assertStringContainsString( 'another site', $this->failure( array( 'paging' => 'link' ) ) );
        $this->assertCount( 1, $this->requests );
    }

    public function testBearerBasicAndApiKeysMasked(): void
    {
        $this->script( array( array( 200, array() ) ) );
        $this->fetch( array( 'auth' => 'bearer', 'auth_secret' => 'env:SQ688_REST_TOKEN' ) );
        $this->assertSame( 'Bearer ' . self::TOKEN, $this->header( $this->requests[0], 'Authorization' ) );
        $this->script( array( array( 200, array() ) ) );
        $this->fetch( array( 'auth' => 'basic', 'auth_user' => 'reader', 'auth_secret' => 'env:SQ688_REST_TOKEN' ) );
        $this->assertSame( 'Basic ' . base64_encode( 'reader:' . self::TOKEN ), $this->header( $this->requests[0], 'Authorization' ) );
        $this->script( array( array( 200, array() ) ) );
        $this->fetch( array( 'auth' => 'api_key_header', 'api_key_name' => 'X-Api-Key', 'auth_secret' => 'env:SQ688_REST_TOKEN' ) );
        $this->assertSame( self::TOKEN, $this->header( $this->requests[0], 'X-Api-Key' ) );
        $this->assertSame( '', $this->header( $this->requests[0], 'Authorization' ) );
        // a key in a parameter, also on the next address of the answer
        $this->script( array( array( 200, array( 'data' => $this->items( 1, 1 ), 'next' => self::BASE . '?page=2' ) ), array( 200, array( 'data' => array() ) ) ) );
        $this->fetch( array( 'auth' => 'api_key_query', 'api_key_name' => 'api_key', 'auth_secret' => 'env:SQ688_REST_TOKEN',
                             'paging' => 'next_url', 'next_path' => 'next', 'size_param' => '' ) );
        $this->assertSame( self::BASE . '?api_key=' . self::TOKEN, $this->requests[0][1] );
        $this->assertSame( self::BASE . '?page=2&api_key=' . self::TOKEN, $this->requests[1][1] );
        // messages never show a secret, also when the server quotes it; the snapshot never holds it
        foreach ( array( array( 'auth' => 'bearer' ), array( 'auth' => 'basic', 'auth_user' => 'reader' ), array( 'auth' => 'api_key_query' ) ) as $more )
        {
            // the server quotes what it was sent: the secret, and for basic the encoded credentials
            $sent = $more['auth'] === 'basic' ? base64_encode( 'reader:' . self::TOKEN ) : self::TOKEN;
            $this->script( array( array( 401, array( 'error' => 'invalid credentials ' . self::TOKEN . ' / ' . $sent ) ) ) );
            $result = expImportRestApiSource::test( $this->options( $more + array( 'auth_secret' => 'env:SQ688_REST_TOKEN' ) ) );
            $this->assertFalse( $result['ok'] );
            $this->assertStringContainsString( 'answered 401', $result['message'] );
            $this->assertStringNotContainsString( self::TOKEN, $result['message'] );
            $this->assertStringNotContainsString( $sent, $result['message'] );
            $this->assertStringNotContainsString( 'api_key=', $result['message'], 'the address in a message has no query' );
        }
        $this->script( array( array( 200, array( array( 'id' => 1, 'echo' => 'Bearer ' . self::TOKEN ) ) ) ) );
        $records = $this->fetch( array( 'auth' => 'bearer', 'auth_secret' => 'env:SQ688_REST_TOKEN' ) );
        $this->assertSame( 'Bearer [secret]', $records[0]['echo'] );
        $this->assertStringNotContainsString( self::TOKEN, (string)file_get_contents( $this->dir . '/source.ndjson' ) );
        // a missing secret is named, never guessed
        $result = expImportRestApiSource::test( $this->options( array( 'auth' => 'bearer', 'auth_secret' => 'no_such_rest_token' ) ) );
        $this->assertStringContainsString( "'no_such_rest_token' is not set", $result['message'] );
    }

    public function testOAuth2ClientCredentialsKeptForTheRun(): void
    {
        $oauth = array( 'auth' => 'oauth2', 'token_url' => 'https://api.example.invalid/oauth/token', 'auth_user' => 'website-import',
                        'auth_secret' => 'env:SQ688_REST_TOKEN', 'scope' => 'products:read', 'paging' => 'page', 'page_size' => 1 );
        $this->script( array( array( 200, array( 'access_token' => 'access-abc-123', 'token_type' => 'Bearer', 'expires_in' => 3600 ) ),
                              array( 200, $this->items( 1, 1 ) ), array( 200, $this->items( 2, 1 ) ), array( 200, array() ) ) );
        $this->assertCount( 2, $this->fetch( $oauth ) );
        $this->assertCount( 4, $this->requests, 'one token for the run' );
        $this->assertSame( 'POST', $this->requests[0][0] );
        $this->assertSame( 'https://api.example.invalid/oauth/token', $this->requests[0][1] );
        $this->assertSame( 'grant_type=client_credentials&scope=products%3Aread', $this->requests[0][3] );
        $this->assertSame( 'Basic ' . base64_encode( 'website-import:' . self::TOKEN ), $this->header( $this->requests[0], 'Authorization' ) );
        $this->assertSame( 'application/x-www-form-urlencoded', $this->header( $this->requests[0], 'Content-Type' ) );
        foreach ( array( 1, 2, 3 ) as $i )
            $this->assertSame( 'Bearer access-abc-123', $this->header( $this->requests[$i], 'Authorization' ) );
        // the client secret in the body; a token that expires is fetched again
        $this->script( array( array( 200, array( 'access_token' => 'access-one', 'expires_in' => 61 ) ), array( 200, $this->items( 1, 1 ) ),
                              array( 200, array( 'access_token' => 'access-two', 'expires_in' => 3600 ) ), array( 200, array() ) ) );
        expImportApiHttp::$sleeper = function ( $seconds ) { $this->now += $seconds; };
        $this->assertCount( 1, $this->fetch( array( 'client_auth' => 'body', 'min_interval' => 2 ) + $oauth ) );
        $this->assertStringContainsString( 'client_secret=' . rawurlencode( self::TOKEN ), $this->requests[0][3] );
        $this->assertSame( '', $this->header( $this->requests[0], 'Authorization' ) );
        $this->assertSame( 'Bearer access-two', $this->header( $this->requests[3], 'Authorization' ) );
        // an error of the token address is masked (the client secret and the basic credentials)
        $this->script( array( array( 401, array( 'error' => 'invalid_client', 'error_description' => 'bad secret ' . self::TOKEN ) ) ) );
        $message = $this->failure( $oauth );
        $this->assertStringContainsString( 'the token address answered 401', $message );
        $this->assertStringNotContainsString( self::TOKEN, $message );
        // a token address on another host only from AllowedTokenHosts[]
        $this->script( array() );
        $other = array( 'token_url' => 'https://login.example.invalid/token' ) + $oauth;
        $this->assertStringContainsString( 'AllowedTokenHosts', $this->failure( $other ) );
        $this->assertSame( array(), $this->requests );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RestApiSourceSettings', 'AllowedTokenHosts', array( 'login.example.invalid' ) );
        $this->script( array( array( 200, array( 'access_token' => 'access-x', 'token_type' => 'bearer' ) ), array( 200, array() ) ) );
        $this->fetch( $other );
        $this->assertSame( 'https://login.example.invalid/token', $this->requests[0][1] );
        // a token of another type is refused
        $this->script( array( array( 200, array( 'access_token' => 'access-y', 'token_type' => 'mac' ) ) ) );
        $this->assertStringContainsString( 'no bearer access token', $this->failure( $oauth ) );
    }

    public function testRateLimitWaits(): void
    {
        $now = 1793000000.0;
        $this->assertSame( 12.0, expImportRestApiSource::rateLimit( 429, array( 'Retry-After: 12' ), $now ) );
        $this->assertSame( 30.0, expImportRestApiSource::rateLimit( 503, array( 'Retry-After: ' . gmdate( 'D, d M Y H:i:s', (int)$now + 30 ) . ' GMT' ), $now ) );
        $this->assertSame( 0.0, expImportRestApiSource::rateLimit( 429, array( 'Retry-After: ' . gmdate( 'D, d M Y H:i:s', (int)$now - 30 ) . ' GMT' ), $now ) );
        $this->assertSame( 45.0, expImportRestApiSource::rateLimit( 429, array( 'X-RateLimit-Reset: ' . ( (int)$now + 45 ) ), $now ), 'a time since 1970' );
        $this->assertSame( 20.0, expImportRestApiSource::rateLimit( 429, array( 'RateLimit-Reset: 20' ), $now ), 'seconds to wait' );
        $this->assertSame( 9.0, expImportRestApiSource::rateLimit( 429, array( 'RateLimit: limit=100, remaining=0, reset=9' ), $now ) );
        $this->assertNull( expImportRestApiSource::rateLimit( 429, array(), $now ) );
        $this->assertNull( expImportRestApiSource::rateLimit( 500, array( 'Retry-After: 5' ), $now ) );
        // the waits on the test's clock, then the answer
        $this->now = $now;
        $this->script( array( array( 429, array(), array( 'Retry-After: 3' ) ),
                              array( 503, array(), array( 'Retry-After: ' . gmdate( 'D, d M Y H:i:s', (int)$now + 60 ) . ' GMT' ) ),
                              array( 429, array(), array( 'X-RateLimit-Reset: ' . ( (int)$now + 100 ) ) ),
                              array( 429, array() ),
                              array( 200, $this->items( 1, 1 ) ) ) );
        $this->assertCount( 1, $this->fetch( array() ) );
        $this->assertCount( 5, $this->requests );
        $this->assertSame( 3.0, $this->slept[0] );
        $this->assertEqualsWithDelta( 57.0, $this->slept[1], 0.1 );
        $this->assertEqualsWithDelta( 40.0, $this->slept[2], 0.2 );
        $this->assertSame( 5.0, $this->slept[3], 'no hint: five seconds' );
        // a wait longer than MaxWaitSeconds fails clearly, without waiting
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RestApiSourceSettings', 'MaxWaitSeconds', 30 );
        $this->slept = array();
        $this->script( array( array( 429, array(), array( 'Retry-After: 3600' ) ) ) );
        $message = $this->failure( array() );
        $this->assertStringContainsString( 'asks to wait 3600 seconds', $message );
        $this->assertStringContainsString( 'MaxWaitSeconds', $message );
        $this->assertSame( array(), $this->slept );
        // still 429 after the retries
        $this->script( array_fill( 0, 5, array( 429, array(), array( 'Retry-After: 1' ) ) ) );
        $this->assertStringContainsString( 'still after waiting', $this->failure( array() ) );
        $this->assertCount( 5, $this->requests );
        // the least time between two requests
        $this->slept = array();
        $this->script( array( array( 200, $this->items( 1, 1 ) ), array( 200, $this->items( 2, 1 ) ), array( 200, array() ) ) );
        $this->fetch( array( 'paging' => 'page', 'page_size' => 1, 'min_interval' => 1.5 ) );
        $this->assertSame( array( 1.49, 1.49 ), $this->slept );
    }

    public function testPostWithJsonBodyAndHosts(): void
    {
        $this->script( array( array( 200, array( 'hits' => $this->items( 1, 2 ) ) ), array( 200, array( 'hits' => array() ) ) ) );
        $this->assertCount( 2, $this->fetch( array( 'method' => 'POST', 'body' => '{"query": {"term": "tea"}, "from": 99}', 'record_path' => 'hits',
                                                    'paging' => 'offset', 'offset_param' => 'from', 'limit_param' => 'size', 'page_size' => 2 ) ) );
        $this->assertSame( 'POST', $this->requests[0][0] );
        $this->assertSame( self::BASE, $this->requests[0][1] );
        $this->assertSame( array( 'from' => 0, 'size' => 2, 'query' => array( 'term' => 'tea' ) ), json_decode( $this->requests[0][3], true ) );
        $this->assertSame( array( 'from' => 2, 'size' => 2, 'query' => array( 'term' => 'tea' ) ), json_decode( $this->requests[1][3], true ) );
        $this->assertSame( 'application/json', $this->header( $this->requests[0], 'Content-Type' ) );
        // https only, http for an allowed host; private networks, credentials and queries in the address refused
        $this->script( array() );
        foreach ( array( 'http://public.example.invalid/api', 'https://10.1.2.3/api', 'http://127.0.0.1:8080/api', 'https://user:pw@api.example.invalid/v2',
                         self::BASE . '?page=1', self::BASE . '#x' ) as $base )
            $this->assertFalse( expImportRestApiSource::test( $this->options( array( 'base_url' => $base ) ) )['ok'], $base );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused address' );
        $this->script( array( array( 200, array() ) ) );
        $this->assertTrue( expImportRestApiSource::test( $this->options( array( 'base_url' => 'http://intranet.example.invalid/api' ) ) )['ok'], 'http for an allowed host' );
    }

    public function testTheTestConnectionAndDrivers(): void
    {
        $this->script( array( array( 200, array( 'data' => array( array( 'id' => 1, 'name' => 'A' ), array( 'id' => 2, 'price' => array( 'amount' => '1.00' ) ) ),
                                                 'meta' => array( 'total' => 40 ) ) ) ) );
        $result = expImportRestApiSource::test( $this->options( array( 'paging' => 'page', 'page_size' => 2, 'record_path' => 'data', 'total_path' => 'meta.total' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 2, $result['rows'] );
        $this->assertSame( array( 'id', 'name', 'price.amount' ), $result['columns'] );
        $this->assertStringContainsString( '40 in all', $result['message'] );
        $this->assertCount( 1, $this->requests, 'Test connection reads the first page only' );
        $drivers = expImportRestApiSource::drivers();
        $this->assertSame( array( 'streams', 'curl' ), array_keys( $drivers ) );
        $this->assertResultAndDrivers( $result, $drivers );
        $this->script( array( array( 200, array() ) ) );
        $result = expImportRestApiSource::test( $this->options() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 0, $result['rows'] );
        $this->script( array( array( 500, 'not json' ) ) );
        $result = expImportRestApiSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 500', $result['message'] );
    }
}
