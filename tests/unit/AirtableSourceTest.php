<?php

use PHPUnit\Framework\TestCase;

/**
 * The Airtable source against a scripted API (expImportPlatformHttp::$transport): the schema and examples, the
 * address of a page, paging with offset, the values (attachments, linked records, collaborators), waiting between
 * requests and after a 429, Test connection with its masked failure, the driver row and the token by its secret.
 */
class AirtableSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests made: method, url, headers, body */
    protected $requests = array();

    /** @var float[] the waits asked for */
    protected $slept = array();

    /** @var float the clock of the test */
    protected $now = 1000.0;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-airtabletest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        putenv( 'SQ688_AIRTABLE_TOKEN=patSampleToken.0123456789abcdef' );
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
        putenv( 'SQ688_AIRTABLE_TOKEN' );
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
    }

    /** @return array the options of a test import */
    protected function options( array $more = array() ): array
    {
        $schema = expImportAirtableSource::schema();
        list( $values, $errors ) = expImportOptionSchema::validate( $schema, $more + array( 'base_id' => 'appExampleBase01', 'table' => 'Events',
                                                                                             'token' => 'env:SQ688_AIRTABLE_TOKEN' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /**
     * A transport that answers the requests in turn.
     *
     * @param array[] $answers status, body (array: JSON), headers
     */
    protected function script( array $answers ): void
    {
        $this->requests = array();
        expImportPlatformHttp::$transport = function ( $method, $url, array $headers, $body ) use ( &$answers )
        {
            $this->requests[] = array( $method, $url, $headers, $body );
            $this->now += 0.01;
            $answer = array_shift( $answers );
            if ( $answer === null )
                throw new LogicException( 'more requests than scripted: ' . $url );
            return array( 'status' => $answer[0], 'headers' => isset( $answer[2] ) ? $answer[2] : array(),
                          'body' => is_array( $answer[1] ) ? json_encode( $answer[1] ) : (string)$answer[1] );
        };
    }

    /** @return array[] the records of a snapshot */
    protected function snapshot( string $file ): array
    {
        $out = array();
        foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
            $out[] = json_decode( $line, true );
        return $out;
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportAirtableSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['token']['type'] );
        $this->assertTrue( $schema['base_id']['required'] && $schema['table']['required'] && $schema['token']['required'] );
        $this->assertSame( 'platform', expImportAirtableSource::group() );
        $this->assertSame( array( 'remote' ), expImportAirtableSource::origins() );
        $this->assertStringContainsString( 'personal data', expImportAirtableSource::description() );
        foreach ( expImportAirtableSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportSecrets::get( $values['token'] ), 'an example names a secret that does not exist' );
            if ( !isset( $example['file'] ) )
                continue;
            // the snapshot of the example reads like a run's
            $source = new expImportAirtableSource( $values + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'] ) );
            $records = iterator_to_array( $source->records(), false );
            $this->assertCount( 3, $records );
            $this->assertSame( 'recExample000001', $records[0]->ref );
            $this->assertSame( 'poster-tasting.jpg', $records[0]->data['Poster_names'][0] );
        }
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'base_id' => 'tblNotABase', 'table' => 'Events', 'token' => 'x' ) );
        $this->assertArrayHasKey( 'base_id', $errors );
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'base_id' => 'appExampleBase01', 'table' => 'Events', 'token' => 'pat secret value' ) );
        $this->assertArrayHasKey( 'token', $errors, 'a token itself is refused, only a name' );
    }

    public function testThePageAddress(): void
    {
        $url = expImportAirtableSource::pageUrl( $this->options( array( 'view' => 'Web site', 'formula' => '{Status} = "Published"',
                                                                         'fields' => "Name\nPoster", 'page_size' => 50 ) ), 'itrA/recB' );
        $this->assertSame( 'https://api.airtable.com/v0/appExampleBase01/Events?pageSize=50&view=Web%20site'
                         . '&filterByFormula=%7BStatus%7D%20%3D%20%22Published%22&fields%5B%5D=Name&fields%5B%5D=Poster&offset=itrA%2FrecB', $url );
        $this->assertStringContainsString( '/v0/appExampleBase01/My%20table%2F2?', expImportAirtableSource::pageUrl( $this->options( array( 'table' => 'My table/2' ) ) ) );
    }

    public function testPagingAndValues(): void
    {
        $this->script( array(
            array( 200, array( 'records' => array(
                array( 'id' => 'recA', 'createdTime' => '2026-09-01T08:00:00.000Z', 'fields' => array(
                    'Name' => 'Tasting', 'Seats' => 40, 'Done' => true,
                    'Poster' => array( array( 'id' => 'attA', 'url' => 'https://dl.example.invalid/a.jpg', 'filename' => 'a.jpg', 'size' => 10, 'type' => 'image/jpeg' ),
                                       array( 'id' => 'attB', 'url' => 'https://dl.example.invalid/b.png', 'filename' => 'b.png', 'size' => 12, 'type' => 'image/png' ) ),
                    'Venue' => array( 'recV1', 'recV2' ),
                    'Owner' => array( 'id' => 'usrA', 'email' => 'owner@example.invalid', 'name' => 'Sample Owner' ),
                    'Team' => array( array( 'id' => 'usrB', 'email' => 'b@example.invalid', 'name' => 'Sample B' ), array( 'id' => 'usrC', 'email' => 'c@example.invalid', 'name' => 'Sample C' ) ),
                    'Link' => array( 'label' => 'Open', 'url' => 'https://example.invalid/x' ),
                    'Code' => array( 'text' => '4006381333931', 'type' => 'ean13' ),
                    'Tags' => array( 'Green', 'Japan' ),
                    'id' => 'own id field' ) ) ),
                'offset' => 'itr1/recA' ) ),
            array( 200, array( 'records' => array( array( 'id' => 'recB', 'createdTime' => '2026-09-02T08:00:00.000Z', 'fields' => array( 'Name' => 'Walk' ) ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportAirtableSource::fetch( $this->options( array( 'fields' => array( 'Name', 'Seats' ) ) ), $target, 0 );
        $this->assertCount( 2, $this->requests );
        $this->assertSame( 'GET', $this->requests[0][0] );
        $this->assertContains( 'Authorization: Bearer patSampleToken.0123456789abcdef', $this->requests[0][2] );
        $this->assertStringNotContainsString( 'offset=', $this->requests[0][1] );
        $this->assertStringEndsWith( '&offset=itr1%2FrecA', $this->requests[1][1] );
        $records = $this->snapshot( $target );
        $this->assertCount( 2, $records );
        $a = $records[0];
        $this->assertSame( array( 'recA', '2026-09-01T08:00:00.000Z', 'Tasting', 40, true ), array( $a['id'], $a['created_time'], $a['Name'], $a['Seats'], $a['Done'] ) );
        $this->assertSame( array( 'https://dl.example.invalid/a.jpg', 'https://dl.example.invalid/b.png' ), $a['Poster'], 'attachments: their addresses' );
        $this->assertSame( array( 'a.jpg', 'b.png' ), $a['Poster_names'] );
        $this->assertSame( array( 'recV1', 'recV2' ), $a['Venue'], 'linked records: their IDs' );
        $this->assertSame( array( 'Sample Owner', 'owner@example.invalid' ), array( $a['Owner'], $a['Owner_email'] ) );
        $this->assertSame( array( 'Sample B', 'Sample C' ), $a['Team'] );
        $this->assertSame( array( 'b@example.invalid', 'c@example.invalid' ), $a['Team_email'] );
        $this->assertSame( 'https://example.invalid/x', $a['Link'] );
        $this->assertSame( '4006381333931', $a['Code'] );
        $this->assertSame( array( 'Green', 'Japan' ), $a['Tags'] );
        $this->assertSame( 'own id field', $a['field_id'], 'a field called id does not overwrite the record ID' );
        $this->assertArrayHasKey( 'Seats', $records[1], 'a listed field is in every record' );
        $this->assertNull( $records[1]['Seats'] );
        $this->assertFileDoesNotExist( $target . '.part' );
        // the sample: the limit stops before the second page
        $this->script( array( array( 200, array( 'records' => array( array( 'id' => 'recA', 'fields' => array() ), array( 'id' => 'recB', 'fields' => array() ) ), 'offset' => 'x' ) ) ) );
        expImportAirtableSource::fetch( $this->options(), $target, 1 );
        $this->assertCount( 1, $this->requests );
        $this->assertCount( 1, $this->snapshot( $target ) );
    }

    public function testRateLimitAndBackoff(): void
    {
        $page = array( 'records' => array( array( 'id' => 'recA', 'fields' => array( 'Name' => 'A' ) ) ), 'offset' => 'next' );
        $this->script( array(
            array( 200, $page ),
            array( 429, array( 'errors' => array( array( 'error' => 'RATE_LIMIT_REACHED' ) ) ) ),
            array( 429, '', array( 'HTTP/1.1 429 Too Many Requests', 'Retry-After: 3' ) ),
            array( 200, array( 'records' => array( array( 'id' => 'recB', 'fields' => array( 'Name' => 'B' ) ) ) ) ),
        ) );
        $this->slept = array();
        expImportAirtableSource::fetch( $this->options(), $this->dir . '/s.ndjson', 0 );
        $this->assertCount( 4, $this->requests );
        // five a second: a fifth of a second between two requests, minus the time the answer took
        $this->assertSame( 0.19, $this->slept[0] );
        $this->assertContains( 30.0, $this->slept, 'a 429 without Retry-After waits Airtable\'s 30 seconds' );
        $this->assertContains( 3.0, $this->slept, 'a Retry-After is followed' );
        $this->assertCount( 2, $this->snapshot( $this->dir . '/s.ndjson' ) );
        // still 429 after the retries: an error
        $this->script( array_fill( 0, expImportApiHttp::RETRIES + 1, array( 429, '' ) ) );
        try
        {
            expImportAirtableSource::fetch( $this->options(), $this->dir . '/s.ndjson', 0 );
            $this->fail( 'still 429' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'too many requests', $e->getMessage() );
        }
        $this->assertCount( expImportApiHttp::RETRIES + 1, $this->requests );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $this->script( array( array( 200, array( 'records' => array( array( 'id' => 'recA', 'createdTime' => 't', 'fields' => array( 'Name' => 'A', 'Seats' => 3 ) ) ), 'offset' => 'more' ) ) ) );
        $result = expImportAirtableSource::test( $this->options() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( array( 'id', 'created_time', 'Name', 'Seats' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertCount( 1, $this->requests, 'one request, one record' );
        $this->assertStringEndsWith( '?pageSize=1', $this->requests[0][1] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        // a refused token, with an answer that quotes it: masked
        $this->script( array( array( 401, array( 'error' => array( 'type' => 'AUTHENTICATION_REQUIRED', 'message' => 'Invalid token patSampleToken.0123456789abcdef' ) ) ) ) );
        $result = expImportAirtableSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringContainsString( 'AUTHENTICATION_REQUIRED', $result['message'] );
        $this->assertStringNotContainsString( 'patSampleToken', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_AIRTABLE_TOKEN]', $result['message'] );
        // a secret that is not set: named, never a value
        $result = expImportAirtableSource::test( $this->options( array( 'token' => 'no_such_airtable_secret' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'no_such_airtable_secret' is not set", $result['message'] );
        // an empty table
        $this->script( array( array( 200, array( 'records' => array() ) ) ) );
        $result = expImportAirtableSource::test( $this->options() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 0, $result['rows'] );
    }

    public function testTheTokenComesFromTheSettingsByName(): void
    {
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'airtable_test_token' => 'patFromTheSettings.abc' ) );
        try
        {
            $this->script( array( array( 200, array( 'records' => array() ) ) ) );
            expImportAirtableSource::fetch( $this->options( array( 'token' => 'airtable_test_token' ) ), $this->dir . '/s.ndjson', 0 );
            $this->assertContains( 'Authorization: Bearer patFromTheSettings.abc', $this->requests[0][2] );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
    }

    public function testDrivers(): void
    {
        $drivers = expImportAirtableSource::drivers();
        $this->assertNotEmpty( $drivers );
        foreach ( $drivers as $row )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $row );
    }

    public function testAnswersThatAreNotRecords(): void
    {
        $messages = array();
        foreach ( array( array( 404, array( 'error' => 'NOT_FOUND' ) ), array( 422, array( 'error' => array( 'type' => 'INVALID_FILTER_BY_FORMULA', 'message' => 'The formula is not valid' ) ) ),
                         array( 200, 'not json' ), array( 200, array( 'something' => 'else' ) ) ) as $answer )
        {
            $this->script( array( $answer ) );
            $result = expImportAirtableSource::test( $this->options() );
            $this->assertFalse( $result['ok'], json_encode( $answer ) );
            $this->assertStringStartsWith( 'Airtable', $result['message'] );
            $messages[] = $result['message'];
        }
        $this->assertStringContainsString( 'not found', $messages[0] );
        $this->assertStringContainsString( 'INVALID_FILTER_BY_FORMULA: The formula is not valid', $messages[1] );
    }
}
