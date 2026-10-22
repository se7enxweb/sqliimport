<?php

use PHPUnit\Framework\TestCase;

/**
 * The platform formats that read a site or an export of one: Drupal JSON:API (flattening, paging through an injected
 * HTTP transport, never the network), Joomla CSV and XML exports, and another Exponential site's REST API.
 */
class PlatformSourcesTest extends TestCase
{
    /** @var array requests the fake transport saw */
    protected $requests = array();

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        putenv( 'SQ688_PLATFORM_PASSWORD' );
        putenv( 'SQ688_PLATFORM_CLIENT' );
    }

    protected static function example( string $name ): string
    {
        return dirname( __DIR__, 2 ) . '/share/examples/' . $name;
    }

    /**
     * A fake site: path and query => answer (array: JSON, int: a status without body).
     *
     * @param array $answers url => answer
     */
    protected function fakeSite( array $answers ): void
    {
        expImportPlatformHttp::$transport = function ( $method, $url, array $headers, $body ) use ( $answers )
        {
            $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body );
            if ( !array_key_exists( $url, $answers ) )
                return array( 'status' => 404, 'headers' => array(), 'body' => '' );
            $answer = $answers[$url];
            if ( is_int( $answer ) )
                return array( 'status' => $answer, 'headers' => array(), 'body' => '' );
            return array( 'status' => 200, 'headers' => array( 'Content-Type: application/json' ), 'body' => json_encode( $answer ) );
        };
    }

    // ---- Drupal --------------------------------------------------------------------------------------------------

    public function testDrupalDocumentIsFlattened(): void
    {
        $records = array();
        foreach ( ( new expImportDrupalSource( array( 'file' => self::example( 'drupal-jsonapi.json' ), 'base_url' => 'https://drupal.example.org', 'key' => 'id' ) ) )->records() as $r )
            $records[] = $r->data;
        $this->assertCount( 2, $records );
        $a = $records[0];
        $this->assertSame( '6f1c2a3e-1111-4c1d-9e6a-0a1b2c3d4e01', $a['id'] );
        $this->assertSame( 11, $a['internal_id'] );
        $this->assertSame( 11, $a['nid'] );
        $this->assertSame( 'Planting trees in the example park', $a['title'] );
        $this->assertStringStartsWith( '<p>Volunteers planted', $a['body'] );
        $this->assertSame( '<p>Volunteers planted twenty trees.</p>', $a['intro'], 'without a summary the intro is the first paragraph' );
        $this->assertSame( 'basic_html', $a['body_format'] );
        $this->assertSame( '/news/planting-trees', $a['path'] );
        $this->assertSame( 'https://drupal.example.org/news/planting-trees', $a['url'] );
        $this->assertTrue( $a['status'] );
        $this->assertSame( 'https://drupal.example.org/sites/default/files/2026-04/trees.jpg', $a['image_url'] );
        $this->assertSame( 'Young trees in a row', $a['image_alt'] );
        $this->assertSame( 'trees.jpg', $a['field_image']['filename'] );
        $this->assertSame( 'Example Editor', $a['author'] );
        $this->assertSame( array( 'Park', 'Volunteers' ), $a['tags'] );
        $this->assertCount( 2, $a['field_tags_id'] );
        $b = $records[1];
        $this->assertSame( 'A quiet room for everyone.', $b['intro'], 'the summary is the intro' );
        $this->assertSame( '', $b['image_url'] );
        $this->assertSame( array(), $b['tags'] );
        $this->assertSame( '', $b['path'] );
        $this->assertSame( 2, ( new expImportDrupalSource( array( 'file' => self::example( 'drupal-jsonapi.json' ) ) ) )->count() );
    }

    public function testDrupalMediaImageIsFollowed(): void
    {
        $record = expImportDrupalSource::flatten(
            array( 'type' => 'node--page', 'id' => 'p1', 'attributes' => array( 'title' => 'Page' ),
                   'relationships' => array( 'field_media' => array( 'data' => array( 'type' => 'media--image', 'id' => 'm1' ) ) ) ),
            array( 'media--image/m1' => array( 'type' => 'media--image', 'id' => 'm1', 'attributes' => array( 'name' => 'Lake' ),
                                                'relationships' => array( 'field_media_image' => array( 'data' => array( 'type' => 'file--file', 'id' => 'f1', 'meta' => array( 'alt' => 'A lake' ) ) ) ) ),
                   'file--file/f1' => array( 'type' => 'file--file', 'id' => 'f1', 'attributes' => array( 'uri' => array( 'url' => 'https://cdn.example.org/lake.jpg' ) ) ) ),
            'https://drupal.example.org' );
        $this->assertSame( 'https://cdn.example.org/lake.jpg', $record['image_url'] );
        $this->assertSame( 'A lake', $record['image_alt'] );
        $this->assertSame( 'Lake', $record['field_media']['name'] );
    }

    public function testDrupalPagingWithAuthentication(): void
    {
        putenv( 'SQ688_PLATFORM_PASSWORD=pa55-word-x' );
        $first = 'https://drupal.example.org/jsonapi/node/article?page%5Blimit%5D=2&include=field_image%2Cuid&filter[status]=1';
        $second = 'https://drupal.example.org/jsonapi/node/article?page%5Boffset%5D=2';
        $page = json_decode( (string)file_get_contents( self::example( 'drupal-jsonapi.json' ) ), true );
        $page['links']['next']['href'] = $second;
        $last = array( 'data' => array( array( 'type' => 'node--article', 'id' => 'third', 'attributes' => array( 'title' => 'Third' ) ) ), 'links' => array() );
        $this->fakeSite( array( $first => $page, $second => $last ) );
        $options = array( 'base_url' => 'https://drupal.example.org/', 'resource' => 'node--article', 'page_size' => 2, 'include' => 'field_image,uid',
                          'filter' => '?filter[status]=1', 'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'env:SQ688_PLATFORM_PASSWORD' );
        $this->assertSame( $first, expImportDrupalSource::firstUrl( $options ) );
        $target = tempnam( sys_get_temp_dir(), 'drupal' );
        expImportDrupalSource::fetch( $options, $target, 0 );
        $refs = array();
        foreach ( ( new expImportDrupalSource( array( 'file' => $target, 'key' => 'id' ) ) )->records() as $r )
            $refs[] = $r->ref;
        $this->assertSame( array( '6f1c2a3e-1111-4c1d-9e6a-0a1b2c3d4e01', '6f1c2a3e-2222-4c1d-9e6a-0a1b2c3d4e02', 'third' ), $refs );
        $this->assertCount( 2, $this->requests );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pa55-word-x' ), $this->requests[0]['headers'] );
        $this->assertContains( 'Accept: application/vnd.api+json', $this->requests[0]['headers'] );
        // a limit stops early
        $this->requests = array();
        expImportDrupalSource::fetch( $options, $target, 1 );
        $this->assertCount( 1, $this->requests );
        $this->assertSame( 1, count( file( $target, FILE_SKIP_EMPTY_LINES ) ) );
        unlink( $target );
    }

    public function testDrupalRefusesANextPageOnAnotherSite(): void
    {
        $first = 'https://drupal.example.org/jsonapi/node/article?page%5Blimit%5D=50';
        $this->fakeSite( array( $first => array( 'data' => array(), 'links' => array( 'next' => array( 'href' => 'https://evil.example.net/jsonapi/x' ) ) ) ) );
        $used = array();
        $this->expectException( expImportSourceException::class );
        $this->expectExceptionMessage( 'another site' );
        foreach ( expImportDrupalSource::resources( array( 'base_url' => 'https://drupal.example.org', 'resource' => 'node--article', 'page_size' => 50, 'include' => '' ), 0, $used ) as $r )
            continue;
    }

    public function testDrupalAccessRefusedAndMissingSecret(): void
    {
        $this->fakeSite( array( 'https://drupal.example.org/jsonapi/node/article?page%5Blimit%5D=50' => 403 ) );
        $options = array( 'base_url' => 'https://drupal.example.org', 'resource' => 'node--article', 'page_size' => 50, 'include' => '' );
        $target = tempnam( sys_get_temp_dir(), 'drupal' );
        try
        {
            expImportDrupalSource::fetch( $options, $target, 0 );
            $this->fail( 'a refused access is an error' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'answered 403', $e->getMessage() );
        }
        try
        {
            expImportDrupalSource::fetch( $options + array( 'auth' => 'bearer', 'token_secret' => 'env:SQ688_NO_SUCH_TOKEN' ), $target, 0 );
            $this->fail( 'a missing secret is an error' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( "the secret 'env:SQ688_NO_SUCH_TOKEN' is not set", $e->getMessage() );
        }
        unlink( $target );
    }

    public function testHttpHelpers(): void
    {
        $this->assertTrue( expImportPlatformHttp::sameSite( 'https://a.example.org/x?y', 'https://a.example.org' ) );
        $this->assertFalse( expImportPlatformHttp::sameSite( 'http://a.example.org/x', 'https://a.example.org' ) );
        $this->assertFalse( expImportPlatformHttp::sameSite( 'https://a.example.org:8443/x', 'https://a.example.org' ) );
        $this->assertFalse( expImportPlatformHttp::sameSite( '/relative', 'https://a.example.org' ) );
        $this->assertSame( 'https://a.example.org/b/c', expImportPlatformHttp::resolve( 'https://a.example.org/x/y', '/b/c' ) );
        $this->assertSame( 'https://a.example.org/x/c', expImportPlatformHttp::resolve( 'https://a.example.org/x/y', 'c' ) );
        $this->assertSame( 'https://a.example.org/p', expImportPlatformHttp::shown( 'https://user:secret@a.example.org/p?token=1' ) );
    }

    // ---- Joomla --------------------------------------------------------------------------------------------------

    public function testJoomlaCsvAndXmlGiveTheSameArticles(): void
    {
        foreach ( array( 'joomla-articles.csv' => 'csv', 'joomla-articles.xml' => 'xml' ) as $file => $variant )
        {
            $source = new expImportJoomlaSource( array( 'file' => self::example( $file ), 'site_url' => 'https://www.example.org/', 'key' => 'id' ) );
            $records = array();
            foreach ( $source->records() as $r )
                $records[$r->ref] = $r->data;
            $this->assertSame( array( '101', '102' ), array_map( 'strval', array_keys( $records ) ), "$file: the trashed article is left out" );
            $a = $records['101'];
            $this->assertSame( 'Welcome to the example club', $a['title'], $file );
            $this->assertSame( '<p>The example club meets every Tuesday.</p>', $a['intro'], $file );
            $this->assertSame( "<p>The example club meets every Tuesday.</p>\n<p>Bring a friend, the first visit is free.</p>", $a['body'], $file );
            $this->assertSame( 'Club news', $a['category'], "$file: the category by its title" );
            $this->assertSame( 'https://www.example.org/images/club/intro.jpg', $a['image_intro'], $file );
            $this->assertSame( 'https://www.example.org/images/club/full.jpg', $a['image_fulltext'], "$file: Joomla 4's # suffix is cut off" );
            $this->assertSame( 'https://www.example.org/images/club/full.jpg', $a['image_url'], $file );
            $this->assertSame( 'The club house at night', $a['image_alt'], $file );
            $this->assertSame( array( 'club', 'meeting' ), $a['keywords'], $file );
            $this->assertSame( '', $a['language'], "$file: * is every language" );
            $this->assertTrue( $a['published'], $file );
            $this->assertTrue( $a['featured'], $file );
            $this->assertSame( 'Club editor', $a['author'], $file );
            $this->assertSame( '2026-03-02 09:00:00', $a['date'], $file );
            $b = $records['102'];
            $this->assertSame( '<p>Our trip to the lake is booked.</p>', $b['body'], "$file: without full text the body is the intro" );
            $this->assertSame( '', $b['image_url'], $file );
            $this->assertSame( 'en-GB', $b['language'], $file );
            $this->assertSame( '2026-05-12 08:00:00', $b['date'], "$file: the date is publish_up" );
            $this->assertSame( $variant, $source->sample( 1 )['detected']['format'], $file );
            $all = new expImportJoomlaSource( array( 'file' => self::example( $file ), 'status' => 'all', 'key' => 'id' ) );
            $this->assertSame( 3, $all->count(), "$file: every status" );
            // the cursor resumes after the first article
            $first = null;
            foreach ( $all->records() as $r )
            {
                $first = $r;
                break;
            }
            $rest = array();
            foreach ( $all->records( $first->cursor ) as $r )
                $rest[] = (string)$r->ref;
            $this->assertSame( array( '102', '103' ), $rest, $file );
        }
        $xml = new expImportJoomlaSource( array( 'file' => self::example( 'joomla-articles.xml' ), 'status' => 'all', 'key' => 'id' ) );
        $records = array();
        foreach ( $xml->records() as $r )
            $records[(string)$r->ref] = $r->data;
        $this->assertSame( 'Archive', $records['103']['category'] );
        $this->assertSame( 'club/club-news', $records['101']['category_path'] );
        // without the site's address an image stays a path, and there is no address to download
        foreach ( ( new expImportJoomlaSource( array( 'file' => self::example( 'joomla-articles.csv' ), 'key' => 'id' ) ) )->records() as $r )
        {
            $this->assertSame( 'images/club/intro.jpg', $r->data['image_intro'] );
            $this->assertSame( '', $r->data['image_url'] );
            $this->assertSame( '', $r->data['image_alt'] );
            break;
        }
    }

    public function testJoomlaPresetFitsAnArticle(): void
    {
        $fields = expImportEditor::presetFields( expImportJoomlaSource::preset(), array( 'title' => 'ezstring', 'intro' => 'ezxmltext', 'body' => 'ezxmltext',
                                                                                         'image' => 'ezimage', 'publish_date' => 'ezdatetime', 'tags' => 'ezkeyword' ) );
        $this->assertSame( array( 'title', 'intro', 'body', 'publish_date', 'image', 'tags' ), array_map( function ( $f ) { return $f['attribute']; }, $fields ) );
    }

    public function testJoomlaXmlWithoutArticles(): void
    {
        $this->expectException( expImportSourceException::class );
        foreach ( ( new expImportJoomlaSource( array( 'file' => self::example( 'books.xml' ) ) ) )->records() as $r )
            continue;
    }

    // ---- another Exponential site --------------------------------------------------------------------------------

    /** @return array a list answer of the REST API v1 */
    protected static function listAnswer( array $children, int $count ): array
    {
        return array( 'childrenNodes' => $children, 'metadata' => array( 'childrenCount' => $count, 'parentNodeId' => 2 ) );
    }

    protected static function node( int $id, string $class, string $name ): array
    {
        return array( 'objectName' => $name, 'classIdentifier' => $class, 'datePublished' => 1790000000 + $id, 'dateModified' => 1790000100 + $id,
                      'objectRemoteId' => 'remote-' . $id, 'objectId' => $id + 1000, 'nodeId' => $id, 'nodeRemoteId' => 'node-remote-' . $id,
                      'fullUrl' => 'https://www.example.org/News/' . str_replace( ' ', '-', $name ),
                      'fields' => array(
                          'title' => array( 'type' => 'ezstring', 'identifier' => 'title', 'value' => $name, 'id' => 1, 'classattribute_id' => 1 ),
                          'body' => array( 'type' => 'ezxmltext', 'identifier' => 'body', 'value' => 'Text of ' . $name, 'id' => 2, 'classattribute_id' => 2 ),
                          'image' => array( 'type' => 'ezimage', 'identifier' => 'image', 'value' => 'var/site/storage/images/' . $id . '.jpg', 'id' => 3, 'classattribute_id' => 3 ),
                          'related' => array( 'type' => 'ezobjectrelationlist', 'identifier' => 'related', 'value' => '12-13', 'id' => 4, 'classattribute_id' => 4 ),
                          'empty' => array( 'type' => 'ezstring', 'identifier' => 'empty', 'value' => false, 'id' => 5, 'classattribute_id' => 5 ),
                      ),
                      'link' => 'https://www.example.org/api/ezp/v1/content/node/' . $id );
    }

    public function testExponentialSiteListsPagesAndDepth(): void
    {
        $base = 'https://www.example.org/api/ezp/v1/content/node/';
        $q = '?ResponseGroups=Metadata,Fields&Translation=eng-GB';
        $this->fakeSite( array(
            $base . '2/list/offset/0/limit/2' . $q => self::listAnswer( array( self::node( 60, 'folder', 'News' ), self::node( 61, 'article', 'First' ) ), 3 ),
            $base . '2/list/offset/2/limit/2' . $q => self::listAnswer( array( self::node( 62, 'article', 'Second' ) ), 3 ),
            $base . '60/list/offset/0/limit/2' . $q => self::listAnswer( array( self::node( 70, 'article', 'Nested' ) ), 1 ),
            $base . '61/list/offset/0/limit/2' . $q => self::listAnswer( array(), 0 ),
            $base . '62/list/offset/0/limit/2' . $q => self::listAnswer( array(), 0 ),
        ) );
        $options = array( 'base_url' => 'https://www.example.org/', 'parent_node' => 2, 'depth' => 2, 'class' => 'article', 'translation' => 'eng-GB',
                          'page_size' => 2, 'max_items' => 10, 'auth' => 'none' );
        $used = array();
        $records = iterator_to_array( expImportExponentialSiteSource::nodes( $options, 0, $used ), false );
        $this->assertSame( array( 61, 62, 70 ), array_map( function ( $r ) { return $r['node_id']; }, $records ), 'the folder is left out, its child is read' );
        $first = $records[0];
        $this->assertSame( 'remote-61', $first['remote_id'] );
        $this->assertSame( 1061, $first['object_id'] );
        $this->assertSame( 'article', $first['class_identifier'] );
        $this->assertSame( 'First', $first['name'] );
        $this->assertSame( 'News/First', $first['url_alias'] );
        $this->assertSame( 2, $first['parent_node_id'] );
        $this->assertSame( 'https://www.example.org/var/site/storage/images/61.jpg', $first['fields']['image'] );
        $this->assertSame( '', $first['fields']['empty'] );
        $this->assertSame( 'ezxmltext', $first['field_types']['body'] );
        $this->assertSame( 60, $records[2]['parent_node_id'] );
        $this->assertSame( 2, $records[2]['depth'] );
        $this->assertCount( 5, $this->requests );
        // max items
        $this->requests = array();
        $records = iterator_to_array( expImportExponentialSiteSource::nodes( array( 'max_items' => 1 ) + $options, 0, $used ), false );
        $this->assertCount( 1, $records );
    }

    public function testExponentialSiteOauthPasswordGrant(): void
    {
        putenv( 'SQ688_PLATFORM_PASSWORD=pa55-word-x' );
        putenv( 'SQ688_PLATFORM_CLIENT=client-s3cret' );
        $this->fakeSite( array(
            'https://www.example.org/api/oauth/token' => array( 'access_token' => 'tok123', 'expires_in' => 3600 ),
            'https://www.example.org/api/ezp/v1/content/node/2/list/offset/0/limit/50?ResponseGroups=Metadata,Fields'
                => self::listAnswer( array( self::node( 61, 'article', 'First' ) ), 1 ),
        ) );
        $target = tempnam( sys_get_temp_dir(), 'expsite' );
        expImportExponentialSiteSource::fetch( array( 'base_url' => 'https://www.example.org', 'parent_node' => 2, 'depth' => 1, 'page_size' => 50, 'max_items' => 100,
                                                      'auth' => 'oauth_password', 'client_id' => 'importer', 'client_secret' => 'env:SQ688_PLATFORM_CLIENT',
                                                      'user' => 'reader', 'password_secret' => 'env:SQ688_PLATFORM_PASSWORD' ), $target, 0 );
        $this->assertSame( 'POST', $this->requests[0]['method'] );
        parse_str( (string)$this->requests[0]['body'], $form );
        $this->assertSame( array( 'client_id' => 'importer', 'client_secret' => 'client-s3cret', 'grant_type' => 'password', 'username' => 'reader', 'password' => 'pa55-word-x' ), $form );
        $this->assertContains( 'Authorization: Bearer tok123', $this->requests[1]['headers'] );
        $records = array();
        foreach ( ( new expImportExponentialSiteSource( array( 'file' => $target, 'key' => 'remote_id' ) ) )->records() as $r )
            $records[] = $r->ref;
        $this->assertSame( array( 'remote-61' ), $records );
        unlink( $target );
    }

    public function testExponentialSiteTokenRefusedIsMasked(): void
    {
        $target = tempnam( sys_get_temp_dir(), 'expsite' );
        $used = array();
        putenv( 'SQ688_PLATFORM_PASSWORD=pa55-word-x' );
        expImportPlatformHttp::$transport = function ( $method, $url, array $headers, $body )
        {
            return array( 'status' => 400, 'headers' => array(), 'body' => json_encode( array( 'error' => 'invalid_grant pa55-word-x' ) ) );
        };
        try
        {
            expImportExponentialSiteSource::fetch( array( 'base_url' => 'https://www.example.org', 'auth' => 'oauth_password', 'client_id' => 'c', 'user' => 'u',
                                                          'password_secret' => 'env:SQ688_PLATFORM_PASSWORD' ), $target, 0 );
            $this->fail( 'a refused grant is an error' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringNotContainsString( 'pa55-word-x', $e->getMessage() );
            $this->assertStringContainsString( '[secret env:SQ688_PLATFORM_PASSWORD]', $e->getMessage() );
        }
        try
        {
            expImportExponentialSiteSource::headers( array( 'base_url' => 'https://www.example.org', 'auth' => 'oauth_refresh', 'token_url' => 'https://other.example.net/token' ), $used );
            $this->fail( 'a token end point on another host is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'on the site itself', $e->getMessage() );
        }
        unlink( $target );
    }

    public function testExponentialSitePresetFromTheFirstRecord(): void
    {
        $preset = expImportExponentialSiteSource::presetFor( array( 'file' => self::example( 'exponential-site.ndjson' ) ) );
        $this->assertSame( 'article', $preset['class'] );
        $this->assertSame( '{remote_id}', $preset['match']['pattern'] );
        $byAttribute = array();
        foreach ( $preset['fields'] as $f )
            $byAttribute[$f['attribute']] = $f;
        $this->assertSame( array( 'title', 'short_title', 'intro', 'body', 'image', 'enable_comments' ), array_keys( $byAttribute ) );
        $this->assertSame( 'richtext', $byAttribute['body']['transforms'][0]['name'] );
        $this->assertSame( 'image', $byAttribute['image']['transforms'][0]['name'] );
        $this->assertSame( 'fields.title', $byAttribute['title']['source'] );
    }
}
