<?php

use PHPUnit\Framework\TestCase;

/**
 * The Notion source against a scripted API (expImportPlatformHttp::$transport): the schema and examples, database IDs
 * and links, the query body with filter, sorts and start_cursor, paging while has_more, the property types, the page
 * content as Markdown and HTML, waiting after a 429, Test connection with its masked failure and the driver row.
 */
class NotionSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests made: method, url, headers, body */
    protected $requests = array();

    /** @var float[] */
    protected $slept = array();

    /** @var float */
    protected $now = 1000.0;

    const DB = '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-notiontest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        putenv( 'SQ688_NOTION_TOKEN=secret_SampleNotionToken0123' );
        expImportApiHttp::reset();
        expImportApiHttp::$sleeper = function ( $seconds )
        {
            $this->slept[] = round( $seconds, 3 );
            $this->now += $seconds;
        };
        expImportApiHttp::$clock = function ()
        {
            return $this->now;
        };
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportApiHttp::$sleeper = null;
        expImportApiHttp::$clock = null;
        expImportApiHttp::reset();
        putenv( 'SQ688_NOTION_TOKEN' );
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportNotionSource::schema(), $more + array( 'database_id' => self::DB, 'token' => 'env:SQ688_NOTION_TOKEN' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @param array[] $answers status, body (array: JSON), headers */
    protected function script( array $answers ): void
    {
        $this->requests = array();
        expImportPlatformHttp::$transport = function ( $method, $url, array $headers, $body ) use ( &$answers )
        {
            $this->requests[] = array( $method, $url, $headers, $body );
            $answer = array_shift( $answers );
            if ( $answer === null )
                throw new LogicException( 'more requests than scripted: ' . $url );
            return array( 'status' => $answer[0], 'headers' => isset( $answer[2] ) ? $answer[2] : array(),
                          'body' => is_array( $answer[1] ) ? json_encode( $answer[1] ) : (string)$answer[1] );
        };
    }

    protected function snapshot( string $file ): array
    {
        return array_map( function ( $line ) { return json_decode( $line, true ); }, file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
    }

    /** @return array a rich text list of one plain part */
    protected function rich( string $text, array $annotations = array(), $href = null ): array
    {
        return array( array( 'type' => 'text', 'plain_text' => $text, 'href' => $href, 'annotations' => $annotations + array( 'bold' => false, 'italic' => false, 'code' => false ) ) );
    }

    /** @return array a page of the API with every property type */
    protected function page( string $id ): array
    {
        return array( 'object' => 'page', 'id' => $id, 'url' => 'https://www.notion.so/x-' . str_replace( '-', '', $id ),
            'created_time' => '2026-09-01T08:00:00.000Z', 'last_edited_time' => '2026-09-02T09:00:00.000Z', 'archived' => false,
            'properties' => array(
                'Name' => array( 'id' => 'title', 'type' => 'title', 'title' => array_merge( $this->rich( 'Spring ' ), $this->rich( 'teas', array( 'bold' => true ) ) ) ),
                'Summary' => array( 'type' => 'rich_text', 'rich_text' => $this->rich( 'First harvest' ) ),
                'Minutes' => array( 'type' => 'number', 'number' => 4.5 ),
                'Status' => array( 'type' => 'status', 'status' => array( 'id' => 's', 'name' => 'Published', 'color' => 'green' ) ),
                'Kind' => array( 'type' => 'select', 'select' => array( 'name' => 'Green' ) ),
                'Empty select' => array( 'type' => 'select', 'select' => null ),
                'Tags' => array( 'type' => 'multi_select', 'multi_select' => array( array( 'name' => 'Japan' ), array( 'name' => 'Spring' ) ) ),
                'Date' => array( 'type' => 'date', 'date' => array( 'start' => '2026-10-01', 'end' => '2026-10-03', 'time_zone' => null ) ),
                'No date' => array( 'type' => 'date', 'date' => null ),
                'Featured' => array( 'type' => 'checkbox', 'checkbox' => true ),
                'Link' => array( 'type' => 'url', 'url' => 'https://example.invalid/spring' ),
                'Mail' => array( 'type' => 'email', 'email' => 'press@example.invalid' ),
                'Phone' => array( 'type' => 'phone_number', 'phone_number' => '+1 555 0100' ),
                'Author' => array( 'type' => 'people', 'people' => array( array( 'object' => 'user', 'id' => 'u1', 'name' => 'Sample Author' ), array( 'object' => 'user', 'id' => 'u2' ) ) ),
                'Image' => array( 'type' => 'files', 'files' => array( array( 'name' => 'spring.jpg', 'type' => 'file', 'file' => array( 'url' => 'https://files.example.invalid/spring.jpg', 'expiry_time' => 'x' ) ),
                                                                        array( 'name' => 'Link', 'type' => 'external', 'external' => array( 'url' => 'https://example.invalid/b.png' ) ) ) ),
                'Related' => array( 'type' => 'relation', 'relation' => array( array( 'id' => 'r1' ), array( 'id' => 'r2' ) ), 'has_more' => false ),
                'Score' => array( 'type' => 'formula', 'formula' => array( 'type' => 'number', 'number' => 7 ) ),
                'Label' => array( 'type' => 'formula', 'formula' => array( 'type' => 'string', 'string' => 'A-7' ) ),
                'Due' => array( 'type' => 'formula', 'formula' => array( 'type' => 'date', 'date' => array( 'start' => '2026-12-24T10:00:00.000+01:00' ) ) ),
                'Total' => array( 'type' => 'rollup', 'rollup' => array( 'type' => 'number', 'number' => 12, 'function' => 'sum' ) ),
                'Titles' => array( 'type' => 'rollup', 'rollup' => array( 'type' => 'array', 'array' => array( array( 'type' => 'title', 'title' => $this->rich( 'One' ) ), array( 'type' => 'title', 'title' => $this->rich( 'Two' ) ) ) ) ),
                'Created' => array( 'type' => 'created_time', 'created_time' => '2026-09-01T08:00:00.000Z' ),
                'Editor' => array( 'type' => 'last_edited_by', 'last_edited_by' => array( 'object' => 'user', 'id' => 'u3', 'name' => 'Sample Editor' ) ),
                'Number' => array( 'type' => 'unique_id', 'unique_id' => array( 'prefix' => 'ART', 'number' => 42 ) ),
                'id' => array( 'type' => 'rich_text', 'rich_text' => $this->rich( 'own id' ) ),
            ) );
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportNotionSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['token']['type'] );
        $this->assertSame( 'platform', expImportNotionSource::group() );
        foreach ( expImportNotionSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportSecrets::get( $values['token'] ) );
            $this->assertSame( self::DB, expImportNotionSource::databaseId( $values['database_id'] ) );
            if ( isset( $example['file'] ) )
            {
                $source = new expImportNotionSource( $values + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'] ) );
                $records = iterator_to_array( $source->records(), false );
                $this->assertCount( 2, $records );
                $this->assertSame( 'Spring teas', $records[0]->data['title'] );
            }
            else
                $this->assertSame( 'Published', $values['filter']['status']['equals'], 'a JSON option becomes an array' );
        }
    }

    public function testDatabaseIds(): void
    {
        $this->assertSame( self::DB, expImportNotionSource::databaseId( '0f1e2d3c4b5a69788796a5b4c3d2e1f0' ) );
        $this->assertSame( self::DB, expImportNotionSource::databaseId( 'https://www.notion.so/sample/Articles-0f1e2d3c4b5a69788796a5b4c3d2e1f0?v=aaaabbbbccccddddeeeeffff00001111' ) );
        $this->assertSame( self::DB, expImportNotionSource::databaseId( strtoupper( self::DB ) ) );
        $this->expectException( expImportSourceException::class );
        expImportNotionSource::databaseId( 'not-an-id' );
    }

    public function testQueryBody(): void
    {
        $body = expImportNotionSource::queryBody( $this->options( array( 'filter' => '{"property": "Status", "status": {"equals": "Published"}}',
                                                                          'sorts' => '{"property": "Date", "direction": "descending"}', 'page_size' => 25 ) ), 'cur-2' );
        $this->assertSame( array( 'page_size' => 25, 'filter' => array( 'property' => 'Status', 'status' => array( 'equals' => 'Published' ) ),
                                  'sorts' => array( array( 'property' => 'Date', 'direction' => 'descending' ) ), 'start_cursor' => 'cur-2' ), $body );
        $this->assertSame( array( 'page_size' => 100 ), expImportNotionSource::queryBody( $this->options() ) );
        $this->expectException( expImportSourceException::class );
        expImportNotionSource::queryBody( $this->options( array( 'filter' => '[1, 2]' ) ) );
    }

    public function testPagingAndPropertyTypes(): void
    {
        $this->script( array(
            array( 200, array( 'object' => 'list', 'results' => array( $this->page( 'p1' ) ), 'has_more' => true, 'next_cursor' => 'cur-2' ) ),
            array( 200, array( 'object' => 'list', 'results' => array( $this->page( 'p2' ) ), 'has_more' => false, 'next_cursor' => null ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportNotionSource::fetch( $this->options( array( 'filter' => '{"property": "Featured", "checkbox": {"equals": true}}' ) ), $target, 0 );
        $this->assertCount( 2, $this->requests );
        $this->assertSame( 'POST', $this->requests[0][0] );
        $this->assertSame( 'https://api.notion.com/v1/databases/' . self::DB . '/query', $this->requests[0][1] );
        $this->assertContains( 'Notion-Version: 2022-06-28', $this->requests[0][2] );
        $this->assertContains( 'Authorization: Bearer secret_SampleNotionToken0123', $this->requests[0][2] );
        $first = json_decode( $this->requests[0][3], true );
        $this->assertArrayNotHasKey( 'start_cursor', $first );
        $this->assertTrue( $first['filter']['checkbox']['equals'] );
        $this->assertSame( 'cur-2', json_decode( $this->requests[1][3], true )['start_cursor'] );
        $records = $this->snapshot( $target );
        $this->assertCount( 2, $records );
        $r = $records[0];
        $expected = array(
            'id' => 'p1', 'title' => 'Spring teas', 'archived' => false, 'created_time' => '2026-09-01T08:00:00.000Z',
            'Name' => 'Spring teas', 'Summary' => 'First harvest', 'Minutes' => 4.5, 'Status' => 'Published', 'Kind' => 'Green', 'Empty select' => null,
            'Tags' => 'Japan, Spring', 'Date' => '2026-10-01', 'Date_end' => '2026-10-03', 'No date' => null, 'No date_end' => null,
            'Featured' => true, 'Link' => 'https://example.invalid/spring', 'Mail' => 'press@example.invalid', 'Phone' => '+1 555 0100',
            'Author' => array( 'Sample Author', 'u2' ), 'Image' => array( 'https://files.example.invalid/spring.jpg', 'https://example.invalid/b.png' ),
            'Image_names' => array( 'spring.jpg', 'Link' ), 'Related' => array( 'r1', 'r2' ), 'Score' => 7, 'Label' => 'A-7',
            'Due' => '2026-12-24T10:00:00.000+01:00', 'Total' => 12, 'Titles' => array( 'One', 'Two' ), 'Created' => '2026-09-01T08:00:00.000Z',
            'Editor' => 'Sample Editor', 'Number' => 'ART-42', 'field_id' => 'own id',
        );
        foreach ( $expected as $key => $value )
            $this->assertSame( $value, $r[$key], $key );
        $this->assertArrayNotHasKey( 'content', $r );
    }

    public function testThePageContentAsMarkdownAndHtml(): void
    {
        $blocks = array(
            array( 'type' => 'heading_1', 'heading_1' => array( 'rich_text' => $this->rich( 'Spring teas' ) ) ),
            array( 'type' => 'paragraph', 'paragraph' => array( 'rich_text' => array_merge( $this->rich( 'The first ' ), $this->rich( 'harvest', array( 'bold' => true ) ),
                                                                                          $this->rich( ' is ' ), $this->rich( 'here', array(), 'https://example.invalid/h' ) ) ) ),
            array( 'type' => 'bulleted_list_item', 'bulleted_list_item' => array( 'rich_text' => $this->rich( 'Sencha' ), 'children' => array() ), 'has_children' => true ),
            array( 'type' => 'bulleted_list_item', 'bulleted_list_item' => array( 'rich_text' => $this->rich( 'Gyokuro' ) ) ),
            array( 'type' => 'numbered_list_item', 'numbered_list_item' => array( 'rich_text' => $this->rich( 'Heat' ) ) ),
            array( 'type' => 'numbered_list_item', 'numbered_list_item' => array( 'rich_text' => $this->rich( 'Brew' ) ) ),
            array( 'type' => 'to_do', 'to_do' => array( 'rich_text' => $this->rich( 'Taste' ), 'checked' => true ) ),
            array( 'type' => 'quote', 'quote' => array( 'rich_text' => $this->rich( 'Brew it twice.' ) ) ),
            array( 'type' => 'code', 'code' => array( 'rich_text' => $this->rich( 'a < b' ), 'language' => 'php' ) ),
            array( 'type' => 'divider', 'divider' => array() ),
            array( 'type' => 'image', 'image' => array( 'type' => 'external', 'external' => array( 'url' => 'https://example.invalid/i.jpg' ), 'caption' => $this->rich( 'A cup' ) ) ),
            array( 'type' => 'child_database', 'child_database' => array( 'title' => 'left out' ) ),
        );
        $this->assertSame( "# Spring teas\n\nThe first **harvest** is [here](https://example.invalid/h)\n\n- Sencha\n- Gyokuro\n\n1. Heat\n2. Brew\n\n- [x] Taste\n\n"
                         . "> Brew it twice.\n\n```php\na < b\n```\n\n---\n\n![A cup](https://example.invalid/i.jpg)", expImportNotionSource::blocksToMarkdown( $blocks ) );
        $this->assertSame( "<h1>Spring teas</h1>\n<p>The first <strong>harvest</strong> is <a href=\"https://example.invalid/h\">here</a></p>\n<ul>\n<li>Sencha</li>\n"
                         . "<li>Gyokuro</li>\n</ul>\n<ol>\n<li>Heat</li>\n<li>Brew</li>\n</ol>\n<ul>\n<li>[x] Taste</li>\n</ul>\n<blockquote>Brew it twice.</blockquote>\n"
                         . "<pre><code>a &lt; b</code></pre>\n<hr />\n<img src=\"https://example.invalid/i.jpg\" alt=\"A cup\" />", expImportNotionSource::blocksToHtml( $blocks ) );
        // in a run: one more request per page, paged by its own cursor, first level only
        $page = $this->page( '11111111-2222-4333-8444-555555555501' );
        $this->script( array(
            array( 200, array( 'results' => array( $page ), 'has_more' => false ) ),
            array( 200, array( 'results' => array_slice( $blocks, 0, 2 ), 'has_more' => true, 'next_cursor' => 'b-2' ) ),
            array( 200, array( 'results' => array_slice( $blocks, 2, 2 ), 'has_more' => false ) ),
        ) );
        expImportNotionSource::fetch( $this->options( array( 'content' => 'html' ) ), $this->dir . '/s.ndjson', 0 );
        $this->assertSame( 'GET', $this->requests[1][0] );
        $this->assertSame( 'https://api.notion.com/v1/blocks/11111111-2222-4333-8444-555555555501/children?page_size=100', $this->requests[1][1] );
        $this->assertStringEndsWith( '&start_cursor=b-2', $this->requests[2][1] );
        $record = $this->snapshot( $this->dir . '/s.ndjson' )[0];
        $this->assertStringStartsWith( '<h1>Spring teas</h1>', $record['content'] );
        $this->assertStringEndsWith( "<li>Gyokuro</li>\n</ul>", $record['content'] );
        // an address that is not http(s) is not linked
        $this->assertSame( 'x', expImportNotionSource::richHtml( $this->rich( 'x', array(), 'javascript:alert(1)' ) ) );
    }

    public function testRateLimitAndTheTestConnection(): void
    {
        $this->script( array(
            array( 429, array( 'object' => 'error', 'status' => 429, 'code' => 'rate_limited', 'message' => 'Rate limited' ), array( 'Retry-After: 1' ) ),
            array( 200, array( 'results' => array( $this->page( 'p1' ) ), 'has_more' => true, 'next_cursor' => 'c' ) ),
        ) );
        $this->slept = array();
        $result = expImportNotionSource::test( $this->options() );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertContains( 1.0, $this->slept, 'the Retry-After of a 429 is followed' );
        $this->assertCount( 2, $this->requests );
        $this->assertSame( 1, json_decode( $this->requests[1][3], true )['page_size'] );
        $this->assertSame( array( 'id', 'url', 'created_time', 'last_edited_time', 'archived', 'title' ), array_slice( $result['columns'], 0, 6 ) );
        $this->assertContains( 'Date_end', $result['columns'] );
        // a token that was refused, quoted by the answer: masked
        $this->script( array( array( 401, array( 'object' => 'error', 'status' => 401, 'code' => 'unauthorized', 'message' => 'API token secret_SampleNotionToken0123 is invalid.' ) ) ) );
        $result = expImportNotionSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Notion answered 401', $result['message'] );
        $this->assertStringContainsString( 'unauthorized', $result['message'] );
        $this->assertStringNotContainsString( 'secret_SampleNotionToken0123', $result['message'] );
        // a database the integration was not given
        $this->script( array( array( 404, array( 'object' => 'error', 'code' => 'object_not_found', 'message' => 'Could not find database' ) ) ) );
        $this->assertStringContainsString( 'object_not_found', expImportNotionSource::test( $this->options() )['message'] );
        // the interval between two requests: a third of a second
        $this->script( array( array( 200, array( 'results' => array(), 'has_more' => false ) ), array( 200, array( 'results' => array(), 'has_more' => false ) ) ) );
        $this->slept = array();
        expImportApiHttp::reset();
        expImportNotionSource::test( $this->options() );
        expImportNotionSource::test( $this->options() );
        $this->assertSame( array( 0.34 ), $this->slept );
    }

    public function testDrivers(): void
    {
        foreach ( expImportNotionSource::drivers() as $row )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $row );
    }
}
