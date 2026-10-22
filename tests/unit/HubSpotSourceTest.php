<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The HubSpot source against a scripted API: the schema and the example, the list (GET, paged with after,
 * properties, associations, archived=false) and the search (POST with checked filter groups, a sort and after), the
 * check of the filter groups, the rate limits and Retry-After, the values (dates in milliseconds, numbers, multiple
 * checkboxes), Test connection with its masked failure and the token by its secret.
 */
class HubSpotSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    const TOKEN = 'pat-eu1-00000000-sample-token-0123';

    protected function setUp(): void
    {
        $this->startTransport( 'hubspot' );
        putenv( 'SQ688_HUBSPOT_TOKEN=' . self::TOKEN );
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_HUBSPOT_TOKEN' );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportHubSpotSource::schema(), $more + array( 'object_type' => 'contacts', 'token' => 'env:SQ688_HUBSPOT_TOKEN' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportHubSpotSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'platform', expImportHubSpotSource::group() );
        $this->assertStringContainsString( 'personal data', expImportHubSpotSource::description() );
        foreach ( expImportHubSpotSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            if ( $values['filter_groups'] !== '' )
                $this->assertIsArray( expImportHubSpotSource::checkFilterGroups( $values['filter_groups'] ) );
            if ( !isset( $example['file'] ) )
                continue;
            $records = $this->readExample( 'expImportHubSpotSource', $example );
            $this->assertCount( 3, $records );
            $this->assertSame( '9001', $records[0]->ref );
            $this->assertSame( array( '501', '502' ), $records[0]->data['associations.contacts'] );
        }
        foreach ( array( 'contacts', 'line_items', '2-1234567', 'p_events' ) as $type )
            $this->assertSame( array(), expImportOptionSchema::validate( $schema, array( 'object_type' => $type, 'token' => 'x' ) )[1], $type );
        foreach ( array( 'Contacts', '../owners', 'contacts/1', '' ) as $type )
            $this->assertArrayHasKey( 'object_type', expImportOptionSchema::validate( $schema, array( 'object_type' => $type, 'token' => 'x' ) )[1], $type );
    }

    public function testFilterGroupsAreChecked(): void
    {
        $ok = expImportHubSpotSource::checkFilterGroups( '{"filterGroups": [{"filters": [{"propertyName": "email", "operator": "contains_token", "value": "*@example.invalid"}]},'
                                                       . '{"filters": [{"propertyName": "amount", "operator": "BETWEEN", "value": 1, "highValue": 9}, {"propertyName": "dealstage", "operator": "IN", "values": ["a", "b"]}]}]}' );
        $this->assertIsArray( $ok );
        $this->assertSame( 'CONTAINS_TOKEN', $ok[0]['filters'][0]['operator'] );
        $this->assertSame( array(), expImportHubSpotSource::checkFilterGroups( '' ) );
        $six = array_fill( 0, 6, array( 'filters' => array( array( 'propertyName' => 'a', 'operator' => 'EQ', 'value' => 1 ) ) ) );
        $many = array( array( 'filters' => array_fill( 0, 19, array( 'propertyName' => 'a', 'operator' => 'EQ', 'value' => 1 ) ) ) );
        foreach ( array( 'not json', '{"filterGroups": [], "properties": ["x"]}', '{"limit": 5}', '[{"filters": []}]', '[{"filters": [{"operator": "EQ"}]}]',
                         '[{"filters": [{"propertyName": "a", "operator": "REGEX", "value": "x"}]}]', '[{"filters": [{"propertyName": "a", "operator": "EQ", "value": {"$x": 1}}]}]',
                         '[{"filters": [{"propertyName": "a", "operator": "IN", "values": [["x"]]}]}]', '[{"filters": [{"propertyName": "a b", "operator": "EQ", "value": 1}]}]',
                         '[{"filters": [{"propertyName": "a", "operator": "EQ", "value": 1, "script": "x"}]}]', '[{"filters": [], "after": 5}]',
                         json_encode( $six ), json_encode( $many ) ) as $groups )
            $this->assertIsString( expImportHubSpotSource::checkFilterGroups( $groups ), $groups );
        $this->script( array() );
        $this->assertFalse( expImportHubSpotSource::test( $this->options( array( 'mode' => 'search', 'filter_groups' => '{"limit": 5}' ) ) )['ok'] );
        $this->assertSame( array(), $this->requests, 'nothing is sent with refused filter groups' );
    }

    public function testListPagingAndValues(): void
    {
        $this->script( array(
            array( 200, array( 'results' => array(
                array( 'id' => '501', 'createdAt' => '2026-08-01T10:00:00.000Z', 'updatedAt' => '2026-09-01T10:00:00.000Z', 'archived' => false,
                       'properties' => array( 'firstname' => 'Sample', 'email' => 'sample@example.invalid', 'num_employees' => '12', 'hs_interests' => 'tea;coffee',
                                              'closedate' => '1790000000123', 'lastmodifieddate' => '2026-09-01T10:00:00.000Z', 'last_order_date' => '1790000000000',
                                              'empty' => '', 'score' => 3.5 ),
                       'associations' => array( 'companies' => array( 'results' => array( array( 'id' => '9001', 'type' => 'contact_to_company' ),
                                                                                           array( 'id' => '9001', 'type' => 'contact_to_company_unlabeled' ) ) ) ) ) ),
                'paging' => array( 'next' => array( 'after' => '502', 'link' => 'https://api.hubapi.com/x' ) ) ) ),
            array( 200, array( 'results' => array( array( 'id' => '502', 'properties' => array( 'firstname' => 'Demo' ) ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportHubSpotSource::fetch( $this->options( array( 'properties' => "firstname\nemail\nphone", 'associations' => 'companies', 'page_size' => 1,
                                                               'date_properties' => 'last_order_date' ) ), $target, 0 );
        $this->assertCount( 2, $this->requests );
        $this->assertSame( 'GET', $this->requests[0][0] );
        $this->assertSame( 'https://api.hubapi.com/crm/v3/objects/contacts?limit=1&archived=false&properties=firstname%2Cemail%2Cphone&associations=companies',
                           $this->requests[0][1] );
        $this->assertStringEndsWith( '&after=502', $this->requests[1][1] );
        $this->assertContains( 'Authorization: Bearer ' . self::TOKEN, $this->requests[0][2] );
        $this->assertSame( 0.09, $this->slept[0], 'a tenth of a second between two requests (100 in 10 seconds)' );
        $records = $this->snapshot( $target );
        $this->assertCount( 2, $records );
        $a = $records[0];
        $this->assertSame( array( '501', '2026-08-01T10:00:00.000Z', false ), array( $a['id'], $a['created_at'], $a['archived'] ) );
        $this->assertSame( '12', $a['num_employees'], 'numbers stay text' );
        $this->assertSame( 'tea;coffee', $a['hs_interests'], 'multiple checkboxes keep ;' );
        $this->assertSame( '2026-09-21T14:13:20.123Z', $a['closedate'], 'a date in milliseconds becomes ISO 8601' );
        $this->assertSame( '2026-09-21T14:13:20.000Z', $a['last_order_date'] );
        $this->assertSame( '2026-09-01T10:00:00.000Z', $a['lastmodifieddate'] );
        $this->assertNull( $a['empty'] );
        $this->assertSame( '3.5', $a['score'] );
        $this->assertNull( $a['phone'], 'an asked property is in every record' );
        $this->assertSame( array( '9001' ), $a['associations.companies'] );
        $this->assertNull( $records[1]['email'] );
        // archived objects
        $this->script( array( array( 200, array( 'results' => array() ) ) ) );
        expImportHubSpotSource::fetch( $this->options( array( 'archived' => true, 'object_type' => '2-1234567' ) ), $target, 0 );
        $this->assertStringStartsWith( 'https://api.hubapi.com/crm/v3/objects/2-1234567?limit=100&archived=true', $this->requests[0][1] );
    }

    public function testSearchRateLimitAndRetryAfter(): void
    {
        $this->script( array(
            array( 200, array( 'total' => 3, 'results' => array( array( 'id' => '1', 'properties' => array( 'dealname' => 'A' ) ), array( 'id' => '2', 'properties' => array( 'dealname' => 'B' ) ) ),
                               'paging' => array( 'next' => array( 'after' => '2' ) ) ) ),
            array( 429, array( 'status' => 'error', 'message' => 'You have reached your secondly limit.', 'category' => 'RATE_LIMITS' ), array( 'HTTP/1.1 429', 'Retry-After: 1' ) ),
            array( 200, array( 'total' => 3, 'results' => array( array( 'id' => '3', 'properties' => array( 'dealname' => 'C' ) ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportHubSpotSource::fetch( $this->options( array( 'object_type' => 'deals', 'mode' => 'search', 'properties' => "dealname\namount", 'page_size' => 2,
                                                               'filter_groups' => '[{"filters": [{"propertyName": "dealstage", "operator": "EQ", "value": "closedwon"}]}]',
                                                               'sort' => 'amount desc', 'search' => 'tea' ) ), $target, 0 );
        $this->assertCount( 3, $this->requests );
        $this->assertSame( array( 'POST', 'https://api.hubapi.com/crm/v3/objects/deals/search' ), array( $this->requests[0][0], $this->requests[0][1] ) );
        $this->assertContains( 'Content-Type: application/json', $this->requests[0][2] );
        $this->assertSame( array( 'filterGroups' => array( array( 'filters' => array( array( 'propertyName' => 'dealstage', 'operator' => 'EQ', 'value' => 'closedwon' ) ) ) ),
                                  'limit' => 2, 'properties' => array( 'dealname', 'amount' ), 'query' => 'tea',
                                  'sorts' => array( array( 'propertyName' => 'amount', 'direction' => 'DESCENDING' ) ) ), json_decode( $this->requests[0][3], true ) );
        $this->assertSame( '2', json_decode( $this->requests[1][3], true )['after'] );
        $this->assertContains( 1.0, $this->slept, 'the Retry-After of a 429 is followed' );
        $this->assertSame( 0.19, $this->slept[0], 'a fifth of a second between two searches' );
        $this->assertCount( 3, $this->snapshot( $target ) );
        // the search stops at 10,000
        $this->script( array( array( 200, array( 'results' => array( array( 'id' => '1' ) ), 'paging' => array( 'next' => array( 'after' => '10000' ) ) ) ) ) );
        $this->expectException( expImportSourceException::class );
        $this->expectExceptionMessage( '10,000' );
        expImportHubSpotSource::fetch( $this->options( array( 'mode' => 'search' ) ), $target, 0 );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $this->script( array( array( 200, array( 'total' => 812, 'results' => array( array( 'id' => '1', 'properties' => array( 'email' => 'a@example.invalid' ) ) ) ) ) ) );
        $result = expImportHubSpotSource::test( $this->options( array( 'mode' => 'search' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 812, $result['rows'] );
        $this->assertSame( array( 'id', 'created_at', 'updated_at', 'archived', 'email' ), $result['columns'] );
        $this->assertSame( 1, json_decode( $this->requests[0][3], true )['limit'] );
        $this->assertResultAndDrivers( $result, expImportHubSpotSource::drivers() );
        $this->script( array( array( 401, array( 'status' => 'error', 'message' => 'Authentication credentials not found. ' . self::TOKEN, 'category' => 'INVALID_AUTHENTICATION' ) ) ) );
        $result = expImportHubSpotSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 401', $result['message'] );
        $this->assertStringNotContainsString( 'sample-token', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_HUBSPOT_TOKEN]', $result['message'] );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'hubspot_test_token' => 'pat-from-the-settings' ) );
        try
        {
            $this->script( array( array( 200, array( 'results' => array() ) ) ) );
            $result = expImportHubSpotSource::test( $this->options( array( 'token' => 'hubspot_test_token' ) ) );
            $this->assertTrue( $result['ok'] );
            $this->assertSame( 0, $result['rows'] );
            $this->assertContains( 'Authorization: Bearer pat-from-the-settings', $this->requests[0][2] );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
        $this->assertStringContainsString( "'no_such_hubspot_token' is not set", expImportHubSpotSource::test( $this->options( array( 'token' => 'no_such_hubspot_token' ) ) )['message'] );
    }
}
