<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The Baserow source against a scripted API: the schema and the example, the address with view, filters, search and
 * order, paging with page and size while there is a "next", the values (link rows, files, selects, collaborators,
 * dates, booleans), Test connection with its masked failure, the token by its secret and the refusal of private hosts.
 */
class BaserowSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    const TOKEN = 'brwSampleDatabaseToken0123456789';

    protected function setUp(): void
    {
        $this->startTransport( 'baserow' );
        putenv( 'SQ688_BASEROW_TOKEN=' . self::TOKEN );
        // api.baserow.io is listed so that no test looks up a name in the DNS
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'BaserowSourceSettings', 'AllowedHosts', array( 'api.baserow.io', 'baserow.example.invalid' ) );
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_BASEROW_TOKEN' );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'BaserowSourceSettings', 'AllowedHosts' );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportBaserowSource::schema(), $more + array( 'table_id' => 4711, 'token' => 'env:SQ688_BASEROW_TOKEN' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportBaserowSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['token']['type'] );
        $this->assertSame( 'platform', expImportBaserowSource::group() );
        $this->assertStringContainsString( 'personal data', expImportBaserowSource::description() );
        foreach ( expImportBaserowSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            if ( !isset( $example['file'] ) )
                continue;
            $records = $this->readExample( 'expImportBaserowSource', $example );
            $this->assertCount( 3, $records );
            $this->assertSame( '1', $records[0]->ref );
            $this->assertSame( 'Green tea, Japan', $records[0]->data['Topics'] );
            $this->assertSame( array( 'poster-autumn.jpg' ), $records[0]->data['Poster_names'] );
        }
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'table_id' => 'abc', 'token' => 'x' ) );
        $this->assertArrayHasKey( 'table_id', $errors );
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'table_id' => 1, 'token' => 'a token value' ) );
        $this->assertArrayHasKey( 'token', $errors, 'a token itself is refused, only a name' );
    }

    public function testTheAddressAndFilters(): void
    {
        $plan = expImportBaserowSource::plan( $this->options( array( 'view_id' => 815, 'filters' => "Status__single_select_equal=3\nMy field__contains=tea & milk",
                                                                      'filter_type' => 'OR', 'search' => 'green tea', 'order_by' => '-Date,Name',
                                                                      'include' => "Name\nDate", 'page_size' => 50 ) ) );
        $this->assertSame( 'https://api.baserow.io/api/database/rows/table/4711/?user_field_names=true&size=50&view_id=815'
                         . '&filter__Status__single_select_equal=3&filter__My%20field__contains=tea%20%26%20milk&filter_type=OR'
                         . '&search=green%20tea&order_by=-Date%2CName&include=Name%2CDate', $plan['url'] );
        foreach ( array( 'Status=3', 'Status__Equal=3', '__equal=3', 'a&b__equal=1', 'Status__equal' ) as $line )
            $this->assertIsString( expImportBaserowSource::checkFilter( $line ), $line );
        $this->assertSame( array( 'field_12', 'higher_than', '10' ), expImportBaserowSource::checkFilter( 'field_12__higher_than=10' ) );
        $this->assertSame( array( 'a__b', 'equal', 'x=y' ), expImportBaserowSource::checkFilter( 'a__b__equal=x=y' ), 'the last __ separates the type' );
        $this->script( array() );
        $this->assertFalse( expImportBaserowSource::test( $this->options( array( 'filters' => 'Status=3' ) ) )['ok'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testPagingAndValues(): void
    {
        $this->script( array(
            array( 200, array( 'count' => 3, 'next' => 'https://api.baserow.io/api/database/rows/table/4711/?page=2', 'previous' => null, 'results' => array(
                array( 'id' => 1, 'order' => '1.000', 'Name' => 'Tasting', 'Date' => '2026-10-10T18:00:00Z', 'Price' => '12.50', 'Done' => true,
                       'Status' => array( 'id' => 3, 'value' => 'Published', 'color' => 'green' ),
                       'Topics' => array( array( 'id' => 1, 'value' => 'Tea', 'color' => 'blue' ), array( 'id' => 2, 'value' => 'Japan', 'color' => 'red' ) ),
                       'Poster' => array( array( 'url' => 'https://files.example.invalid/a.jpg', 'visible_name' => 'a.jpg', 'name' => 'x_a.jpg', 'mime_type' => 'image/jpeg' ) ),
                       'Venue' => array( array( 'id' => 7, 'value' => 'Tea room' ), array( 'id' => 8, 'value' => 'Garden' ) ),
                       'Hosts' => array( array( 'id' => 1, 'name' => 'Host A' ), array( 'id' => 2, 'name' => 'Host B' ) ),
                       'Created by' => array( 'id' => 1, 'name' => 'Host A' ), 'Empty links' => array(), 'Nothing' => null ),
                array( 'id' => 2, 'order' => '2.000', 'Name' => 'Walk', 'Done' => false ) ) ) ),
            array( 200, array( 'count' => 3, 'next' => null, 'results' => array( array( 'id' => 3, 'Name' => 'Talk' ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportBaserowSource::fetch( $this->options( array( 'page_size' => 2, 'separator' => ' | ' ) ), $target, 0 );
        $this->assertCount( 2, $this->requests );
        $this->assertStringEndsWith( '&page=1', $this->requests[0][1] );
        $this->assertStringEndsWith( '&page=2', $this->requests[1][1] );
        $this->assertSame( 'GET', $this->requests[1][0] );
        $this->assertContains( 'Authorization: Token ' . self::TOKEN, $this->requests[0][2] );
        $records = $this->snapshot( $target );
        $this->assertCount( 3, $records );
        $a = $records[0];
        $this->assertSame( array( 1, '2026-10-10T18:00:00Z', '12.50', true, 'Published' ), array( $a['id'], $a['Date'], $a['Price'], $a['Done'], $a['Status'] ) );
        $this->assertSame( 'Tea | Japan', $a['Topics'] );
        $this->assertSame( array( 'https://files.example.invalid/a.jpg' ), $a['Poster'] );
        $this->assertSame( array( 'a.jpg' ), $a['Poster_names'] );
        $this->assertSame( array( 'Tea room | Garden', '7,8' ), array( $a['Venue'], $a['Venue_ids'] ) );
        $this->assertSame( 'Host A | Host B', $a['Hosts'] );
        $this->assertSame( 'Host A', $a['Created by'] );
        $this->assertSame( array(), $a['Empty links'] );
        $this->assertNull( $a['Nothing'] );
        $this->assertFalse( $records[1]['Done'] );
        // the sample stops before the second page
        $this->script( array( array( 200, array( 'count' => 9, 'next' => 'x', 'results' => array( array( 'id' => 1 ), array( 'id' => 2 ) ) ) ) ) );
        expImportBaserowSource::fetch( $this->options(), $target, 1 );
        $this->assertCount( 1, $this->requests );
        $this->assertCount( 1, $this->snapshot( $target ) );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $this->script( array( array( 200, array( 'count' => 120, 'next' => 'x', 'results' => array( array( 'id' => 1, 'Name' => 'A' ) ) ) ) ) );
        $result = expImportBaserowSource::test( $this->options() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( array( 'id', 'Name' ), $result['columns'] );
        $this->assertSame( 120, $result['rows'] );
        $this->assertStringContainsString( 'size=1&', $this->requests[0][1] );
        $this->assertResultAndDrivers( $result, expImportBaserowSource::drivers() );
        $this->script( array( array( 401, array( 'error' => 'ERROR_INVALID_TOKEN', 'detail' => 'The token ' . self::TOKEN . ' is not valid' ) ) ) );
        $result = expImportBaserowSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'ERROR_INVALID_TOKEN', $result['message'] );
        $this->assertStringContainsString( 'is not valid', $result['message'] );
        $this->assertStringNotContainsString( self::TOKEN, $result['message'] );
        $this->script( array( array( 404, array( 'error' => 'ERROR_TABLE_DOES_NOT_EXIST' ) ) ) );
        $result = expImportBaserowSource::test( $this->options() );
        $this->assertStringContainsString( 'not found', $result['message'] );
        // the token from the settings by its name
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'baserow_test_token' => 'brwFromTheSettings' ) );
        try
        {
            $this->script( array( array( 200, array( 'count' => 0, 'next' => null, 'results' => array() ) ) ) );
            $result = expImportBaserowSource::test( $this->options( array( 'token' => 'baserow_test_token' ) ) );
            $this->assertTrue( $result['ok'] );
            $this->assertSame( 0, $result['rows'] );
            $this->assertContains( 'Authorization: Token brwFromTheSettings', $this->requests[0][2] );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
        $result = expImportBaserowSource::test( $this->options( array( 'token' => 'no_such_baserow_token' ) ) );
        $this->assertStringContainsString( "'no_such_baserow_token' is not set", $result['message'] );
    }

    public function testHosts(): void
    {
        $this->script( array() );
        foreach ( array( 'https://192.168.1.20', 'http://localhost:8000', 'https://baserow.example.invalid/?a=1' ) as $base )
            $this->assertFalse( expImportBaserowSource::test( $this->options( array( 'base_url' => $base ) ) )['ok'], $base );
        $this->assertSame( array(), $this->requests );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'BaserowSourceSettings', 'AllowedHosts', array( '192.168.1.20' ) );
        $this->script( array( array( 200, array( 'count' => 0, 'next' => null, 'results' => array() ) ) ) );
        $this->assertTrue( expImportBaserowSource::test( $this->options( array( 'base_url' => 'https://192.168.1.20' ) ) )['ok'] );
        $this->assertStringStartsWith( 'https://192.168.1.20/api/database/rows/table/4711/?', $this->requests[0][1] );
    }
}
