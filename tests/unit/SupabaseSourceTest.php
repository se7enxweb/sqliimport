<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The Supabase (PostgREST) source against a scripted API: the schema and the example, the checks of select, filters
 * and order (only comparing operators, at any depth of and/or), paging by Range header and by limit/offset with the
 * total of Content-Range, embedded resources flattened, Test connection with its masked failure, the key by its
 * secret and the refusal of private hosts.
 */
class SupabaseSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    const KEY = 'eyJhbGciOiJIUzI1NiJ9.sampleAnonKey.signature0123';

    protected function setUp(): void
    {
        $this->startTransport( 'supabase' );
        putenv( 'SQ688_SUPABASE_KEY=' . self::KEY );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SupabaseSourceSettings', 'AllowedHosts', array( 'abcdefghijklmnop.supabase.co', 'postgrest.example.invalid' ) );
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_SUPABASE_KEY' );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->removeSetting( 'SupabaseSourceSettings', 'AllowedHosts' );
        $ini->removeSetting( 'SupabaseSourceSettings', 'MaxPages' );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportSupabaseSource::schema(), $more + array(
            'base_url' => 'https://abcdefghijklmnop.supabase.co', 'table' => 'articles', 'api_key' => 'env:SQ688_SUPABASE_KEY' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportSupabaseSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['api_key']['type'] );
        $this->assertSame( 'platform', expImportSupabaseSource::group() );
        $this->assertSame( array( 'remote' ), expImportSupabaseSource::origins() );
        foreach ( expImportSupabaseSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            foreach ( (array)$values['filters'] as $line )
                $this->assertIsArray( expImportSupabaseSource::checkFilter( $line ), $line );
            if ( !isset( $example['file'] ) )
                continue;
            $records = $this->readExample( 'expImportSupabaseSource', $example );
            $this->assertCount( 3, $records );
            $this->assertSame( '101', $records[0]->ref );
            $this->assertSame( 'Editorial desk', $records[0]->data['author.name'] );
        }
    }

    public function testFiltersOnlyCompare(): void
    {
        foreach ( array( 'status=eq.published', 'price=lt.10', 'title=ilike.*tea*', 'deleted_at=is.null', 'id=in.(1,2,3)', 'tags=cs.{tea,green}',
                         'status=not.eq.draft', 'author.name=eq.Desk', 'or=(stock.gt.0,preorder.is.true)', 'and=(a.gte.1,or(b.eq.x,not.and(c.lt.2,d.neq.3)))',
                         'not.or=(a.eq.1,b.eq.2)', 'author.or=(name.eq.A,name.eq.B)', 'tags=eq(any).{a,b}', 'name=eq."a,b"' ) as $line )
            $this->assertIsArray( expImportSupabaseSource::checkFilter( $line ), $line );
        foreach ( array( 'status=fts.tea', 'body=plfts.tea', 'range=ov.[1,2]', 'or=(a.eq.1,b.fts.x)', 'and=(a.eq.1,or(b.match.x))', 'select=*',
                         'order=id', 'limit=5', 'offset=10', 'columns=a', 'on_conflict=id', 'status=published', 'x=is.1', 'id=in.1,2', 'or=a.eq.1',
                         'or=(a.eq.1', 'a b=eq.1', '=eq.1', 'status' ) as $line )
            $this->assertIsString( expImportSupabaseSource::checkFilter( $line ), $line );
        $this->assertNull( expImportSupabaseSource::checkSelect( 'id,title,author:authors!inner(name,email),data->>color,price::text,count()' ) );
        foreach ( array( 'id&order=x', 'id,(title', 'a/b', 'id;drop', "id\n" ) as $select )
            $this->assertIsString( expImportSupabaseSource::checkSelect( str_replace( "\n", '%0A', $select ) ), $select );
        $this->assertNull( expImportSupabaseSource::checkOrder( 'published_at.desc.nullslast,id' ) );
        $this->assertIsString( expImportSupabaseSource::checkOrder( 'id;delete' ) );
        // nothing is sent with a refused option, and rpc is never read
        $this->script( array() );
        foreach ( array( array( 'filters' => 'body=fts.tea' ), array( 'select' => 'id&x=1' ), array( 'order' => 'id desc' ), array( 'table' => 'rpc' ) ) as $more )
        {
            $result = expImportSupabaseSource::test( $this->options( $more ) );
            $this->assertFalse( $result['ok'], json_encode( $more ) );
        }
        $this->assertSame( array(), $this->requests );
    }

    public function testRangePagingAndValues(): void
    {
        $this->script( array(
            array( 206, array( array( 'id' => 1, 'title' => 'A', 'tags' => array( 'tea', 'green' ), 'meta' => array( 'words' => 10 ), 'author' => array( 'name' => 'Desk' ) ),
                               array( 'id' => 2, 'title' => 'B', 'tags' => array(), 'meta' => null, 'author' => null ) ),
                   array( 'HTTP/1.1 206 Partial Content', 'Content-Range: 0-1/3' ) ),
            array( 206, array( array( 'id' => 3, 'title' => 'C', 'tags' => array( 'x' ), 'meta' => array( 'words' => 3 ), 'author' => array( 'name' => 'Guest' ) ) ),
                   array( 'Content-Range: 2-2/3' ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportSupabaseSource::fetch( $this->options( array( 'select' => 'id,title,tags,meta,author:authors(name)', 'filters' => "status=eq.published\nor=(a.eq.1,b.eq.2)",
                                                                'order' => 'id', 'page_size' => 2, 'count' => 'exact', 'arrays' => 'join' ) ), $target, 0 );
        $this->assertCount( 2, $this->requests, 'the total ends the paging' );
        $this->assertSame( 'GET', $this->requests[0][0] );
        $this->assertSame( 'https://abcdefghijklmnop.supabase.co/rest/v1/articles?select=id,title,tags,meta,author:authors(name)&status=eq.published&or=(a.eq.1,b.eq.2)&order=id',
                           $this->requests[0][1] );
        $this->assertSame( '0-1', $this->header( $this->requests[0], 'Range' ) );
        $this->assertSame( 'items', $this->header( $this->requests[0], 'Range-Unit' ) );
        $this->assertSame( '2-3', $this->header( $this->requests[1], 'Range' ) );
        $this->assertSame( 'count=exact', $this->header( $this->requests[0], 'Prefer' ) );
        $this->assertSame( self::KEY, $this->header( $this->requests[0], 'apikey' ) );
        $this->assertSame( 'Bearer ' . self::KEY, $this->header( $this->requests[0], 'Authorization' ) );
        $records = $this->snapshot( $target );
        $this->assertCount( 3, $records );
        $this->assertSame( array( 'id' => 1, 'title' => 'A', 'tags' => 'tea, green', 'meta.words' => 10, 'author.name' => 'Desk' ), $records[0] );
        $this->assertNull( $records[1]['author'] );
        // limit and offset; a short page ends the paging; a 416 (the range after the last row) ends it too
        $this->script( array( array( 200, array( array( 'sku' => 'a' ), array( 'sku' => 'b' ) ) ), array( 416, array( 'code' => 'PGRST103', 'message' => 'Requested range not satisfiable' ) ) ) );
        expImportSupabaseSource::fetch( $this->options( array( 'base_url' => 'https://postgrest.example.invalid', 'table' => 'products', 'paging' => 'offset',
                                                                'page_size' => 2, 'api_key' => '', 'schema' => 'shop' ) ), $target, 0 );
        $this->assertSame( 'https://postgrest.example.invalid/products?select=*&limit=2&offset=0', $this->requests[0][1] );
        $this->assertSame( 'https://postgrest.example.invalid/products?select=*&limit=2&offset=2', $this->requests[1][1] );
        $this->assertSame( 'shop', $this->header( $this->requests[0], 'Accept-Profile' ) );
        $this->assertSame( '', $this->header( $this->requests[0], 'apikey' ), 'no key: no headers' );
        $this->assertCount( 2, $this->snapshot( $target ) );
        // the sample and the option "Most rows" stop early
        $this->script( array( array( 200, array( array( 'id' => 1 ), array( 'id' => 2 ) ) ) ) );
        expImportSupabaseSource::fetch( $this->options( array( 'limit' => 2 ) ), $target, 0 );
        $this->assertSame( '0-1', $this->header( $this->requests[0], 'Range' ) );
        $this->assertCount( 1, $this->requests );
        // MaxPages
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SupabaseSourceSettings', 'MaxPages', 1 );
        $this->script( array( array( 200, array( array( 'id' => 1 ) ) ) ) );
        $this->expectException( expImportSourceException::class );
        expImportSupabaseSource::fetch( $this->options( array( 'page_size' => 1 ) ), $target, 0 );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $this->script( array( array( 206, array( array( 'id' => 1, 'title' => 'A' ) ), array( 'Content-Range: 0-0/42' ) ) ) );
        $result = expImportSupabaseSource::test( $this->options( array( 'count' => 'planned' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( array( 'id', 'title' ), $result['columns'] );
        $this->assertSame( 42, $result['rows'] );
        $this->assertStringContainsString( '42 rows', $result['message'] );
        $this->assertSame( '0-0', $this->header( $this->requests[0], 'Range' ) );
        $this->assertResultAndDrivers( $result, expImportSupabaseSource::drivers() );
        $this->script( array( array( 401, array( 'code' => 'PGRST301', 'message' => 'JWT invalid: ' . self::KEY ) ) ) );
        $result = expImportSupabaseSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringContainsString( 'PGRST301', $result['message'] );
        $this->assertStringNotContainsString( 'sampleAnonKey', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_SUPABASE_KEY]', $result['message'] );
        $result = expImportSupabaseSource::test( $this->options( array( 'api_key' => 'no_such_supabase_key' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'no_such_supabase_key' is not set", $result['message'] );
        // a bearer token of its own wins over the key for Authorization
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'supabase_reader_jwt' => 'eyJ.reader.jwt' ) );
        try
        {
            $this->script( array( array( 200, array() ) ) );
            $result = expImportSupabaseSource::test( $this->options( array( 'bearer' => 'supabase_reader_jwt' ) ) );
            $this->assertTrue( $result['ok'] );
            $this->assertSame( 0, $result['rows'] );
            $this->assertSame( 'Bearer eyJ.reader.jwt', $this->header( $this->requests[0], 'Authorization' ) );
            $this->assertSame( self::KEY, $this->header( $this->requests[0], 'apikey' ) );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
    }

    public function testHosts(): void
    {
        $this->script( array() );
        foreach ( array( 'https://10.1.2.3', 'http://127.0.0.1:3000', 'https://user:pw@postgrest.example.invalid', 'https://postgrest.example.invalid/?x=1' ) as $base )
            $this->assertFalse( expImportSupabaseSource::test( $this->options( array( 'base_url' => $base ) ) )['ok'], $base );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SupabaseSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $this->script( array( array( 200, array() ) ) );
        $this->assertTrue( expImportSupabaseSource::test( $this->options( array( 'base_url' => 'https://10.1.2.3/api' ) ) )['ok'], 'an allowed intranet server' );
        $this->assertStringStartsWith( 'https://10.1.2.3/api/articles?', $this->requests[0][1], 'a PostgREST path is kept, /rest/v1 only for supabase.co' );
    }
}
