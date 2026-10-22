<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The Salesforce source against a scripted API: the schema and the example, the SOQL check (a single SELECT, nothing
 * that writes), the JWT bearer flow (an RS256 token verified with the public key) and the refresh token flow, the
 * instance address checked, paging with nextRecordsUrl, queryAll, the values (relationships, addresses, date-times,
 * numbers), Test connection with its masked failure.
 */
class SalesforceSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    /** @var string */
    protected static $pem = '';

    /** @var string */
    protected static $public = '';

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
        openssl_pkey_export( $key, $pem );
        self::$pem = (string)$pem;
        self::$public = (string)openssl_pkey_get_details( $key )['key'];
    }

    protected function setUp(): void
    {
        $this->startTransport( 'salesforce' );
        putenv( 'SQ688_SF_KEY=' . self::$pem );
        putenv( 'SQ688_SF_REFRESH=sf-test-refresh-token-not-real' );
        putenv( 'SQ688_SF_SECRET=SampleConsumerSecret0123' );
        expImportSalesforceSource::$clock = function () { return 1790000000; };
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_SF_KEY' );
        putenv( 'SQ688_SF_REFRESH' );
        putenv( 'SQ688_SF_SECRET' );
        expImportSalesforceSource::$clock = null;
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'SalesforceSourceSettings', 'AllowedHosts' );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportSalesforceSource::schema(), $more + array(
            'client_id' => '3MVG9ExampleConsumerKey', 'user' => 'integration@example.invalid', 'private_key' => 'env:SQ688_SF_KEY',
            'query' => 'SELECT Id, Name, Owner.Name FROM Account' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array the answer of the token endpoint */
    protected function token( string $instance = 'https://example-dev-ed.my.salesforce.com' ): array
    {
        return array( 200, array( 'access_token' => '00DEX0000000001!AccessTokenSample', 'instance_url' => $instance, 'token_type' => 'Bearer' ) );
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportSalesforceSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'platform', expImportSalesforceSource::group() );
        $this->assertStringContainsString( 'personal', expImportSalesforceSource::description() );
        foreach ( expImportSalesforceSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportSalesforceSource::checkQuery( $values['query'] ), $example['label'] );
            if ( !isset( $example['file'] ) )
                continue;
            $records = $this->readExample( 'expImportSalesforceSource', $example );
            $this->assertCount( 3, $records );
            $this->assertSame( '001EX00000000001AA', $records[0]->ref );
            $this->assertSame( 'Sampletown', $records[0]->data['BillingAddress.city'] );
            $this->assertSame( '1250000.5', $records[0]->data['AnnualRevenue'] );
            $this->assertSame( 'Sales Desk', $records[0]->data['Owner.Name'] );
        }
    }

    public function testOnlyASelectThatReads(): void
    {
        foreach ( array( 'SELECT Id FROM Account', "select Id, (SELECT Id FROM Contacts) FROM Account WHERE Name = 'a;b' LIMIT 10;",
                         "SELECT Id FROM Account WHERE Name LIKE 'Update%' AND Description = 'for update'", 'SELECT Update__c FROM Thing__c',
                         "SELECT Id FROM Account WHERE Name = 'it\\'s'" ) as $query )
            $this->assertNull( expImportSalesforceSource::checkQuery( $query ), $query );
        foreach ( array( '', 'DELETE FROM Account', 'UPDATE Account SET Name = 1', 'SELECT Id FROM Account FOR UPDATE', 'SELECT Id FROM Account FOR VIEW',
                         'SELECT Id FROM Account FOR REFERENCE', 'SELECT Id FROM KnowledgeArticleVersion UPDATE TRACKING',
                         'SELECT Id FROM KnowledgeArticleVersion UPDATE  VIEWSTAT', 'SELECT Id FROM Account; SELECT Id FROM Contact',
                         "SELECT Id FROM Account WHERE Name = 'open", 'SELECT Id FROM Account WHERE (Name = 1', 'FIND {tea} IN ALL FIELDS' ) as $query )
            $this->assertIsString( expImportSalesforceSource::checkQuery( $query ), $query );
        $this->script( array() );
        $result = expImportSalesforceSource::test( $this->options( array( 'query' => 'SELECT Id FROM Account FOR UPDATE' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertSame( array(), $this->requests, 'nothing is sent, not even the sign-in' );
    }

    public function testJwtFlowPagingAndValues(): void
    {
        $this->script( array(
            $this->token(),
            array( 200, array( 'totalSize' => 3, 'done' => false, 'nextRecordsUrl' => '/services/data/v61.0/query/01gEX0000000001-2000', 'records' => array(
                array( 'attributes' => array( 'type' => 'Account', 'url' => '/x' ), 'Id' => '001A', 'Name' => 'Example', 'AnnualRevenue' => 1234.5, 'NumberOfEmployees' => 12,
                       'IsActive__c' => true, 'CreatedDate' => '2026-09-01T08:00:00.000+0000', 'Local__c' => '2026-09-01T08:00:00.000+0200', 'Since__c' => '2026-01-31',
                       'Tiny__c' => 0.000012, 'BillingAddress' => array( 'city' => 'Sampletown', 'country' => 'Exampleland', 'latitude' => 51.5 ),
                       'Owner' => array( 'attributes' => array( 'type' => 'User' ), 'Name' => 'Desk', 'Manager' => array( 'attributes' => array(), 'Name' => 'Boss' ) ),
                       'Parent' => null, 'Contacts' => array( 'totalSize' => 1, 'done' => true, 'records' => array( array( 'attributes' => array( 'type' => 'Contact' ), 'Id' => '003A' ) ) ) ),
                array( 'attributes' => array( 'type' => 'Account' ), 'Id' => '001B', 'Name' => 'Sample' ) ) ) ),
            array( 200, array( 'totalSize' => 3, 'done' => true, 'records' => array( array( 'attributes' => array( 'type' => 'Account' ), 'Id' => '001C', 'Name' => 'Demo' ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportSalesforceSource::fetch( $this->options( array( 'query' => "SELECT Id, Name FROM Account WHERE Type = 'Customer'", 'batch_size' => 500 ) ), $target, 0 );
        $this->assertCount( 3, $this->requests );
        // the sign-in: a JWT for the user, signed RS256
        list( $method, $url, $headers, $body ) = $this->requests[0];
        $this->assertSame( array( 'POST', 'https://login.salesforce.com/services/oauth2/token' ), array( $method, $url ) );
        $this->assertContains( 'Content-Type: application/x-www-form-urlencoded', $headers );
        parse_str( (string)$body, $form );
        $this->assertSame( 'urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type'] );
        list( $head, $claims, $signature ) = explode( '.', $form['assertion'] );
        $decode = function ( $part ) { return base64_decode( strtr( $part, '-_', '+/' ) ); };
        $this->assertSame( array( 'alg' => 'RS256' ), json_decode( $decode( $head ), true ) );
        $this->assertSame( array( 'iss' => '3MVG9ExampleConsumerKey', 'sub' => 'integration@example.invalid', 'aud' => 'https://login.salesforce.com', 'exp' => 1790000180 ),
                           json_decode( $decode( $claims ), true ) );
        $this->assertSame( 1, openssl_verify( $head . '.' . $claims, $decode( $signature ), self::$public, OPENSSL_ALGO_SHA256 ) );
        // the query and the next page on the instance of the token answer
        $this->assertSame( 'https://example-dev-ed.my.salesforce.com/services/data/v61.0/query?q=SELECT%20Id%2C%20Name%20FROM%20Account%20WHERE%20Type%20%3D%20%27Customer%27',
                           $this->requests[1][1] );
        $this->assertContains( 'Authorization: Bearer 00DEX0000000001!AccessTokenSample', $this->requests[1][2] );
        $this->assertContains( 'Sforce-Query-Options: batchSize=500', $this->requests[1][2] );
        $this->assertSame( 'https://example-dev-ed.my.salesforce.com/services/data/v61.0/query/01gEX0000000001-2000', $this->requests[2][1] );
        $records = $this->snapshot( $target );
        $this->assertCount( 3, $records );
        $a = $records[0];
        $this->assertArrayNotHasKey( 'attributes', $a );
        $this->assertSame( '1234.5', $a['AnnualRevenue'], 'a number with a fraction as text' );
        $this->assertSame( 12, $a['NumberOfEmployees'] );
        $this->assertTrue( $a['IsActive__c'] );
        $this->assertSame( '2026-09-01T08:00:00.000Z', $a['CreatedDate'] );
        $this->assertSame( '2026-09-01T08:00:00.000+02:00', $a['Local__c'] );
        $this->assertSame( '2026-01-31', $a['Since__c'] );
        $this->assertSame( '0.000012', $a['Tiny__c'] );
        $this->assertSame( array( 'Sampletown', 'Exampleland', '51.5' ), array( $a['BillingAddress.city'], $a['BillingAddress.country'], $a['BillingAddress.latitude'] ) );
        $this->assertSame( array( 'Desk', 'Boss' ), array( $a['Owner.Name'], $a['Owner.Manager.Name'] ) );
        $this->assertArrayHasKey( 'Parent', $a );
        $this->assertNull( $a['Parent'] );
        $this->assertSame( array( array( 'Id' => '003A' ) ), json_decode( $a['Contacts'], true ) );
        // the sample stops after the first page
        $this->script( array( $this->token(), array( 200, array( 'totalSize' => 9, 'done' => false, 'nextRecordsUrl' => '/services/data/v61.0/query/x-1',
                                                                 'records' => array( array( 'Id' => '1' ), array( 'Id' => '2' ) ) ) ) ) );
        expImportSalesforceSource::fetch( $this->options(), $target, 1 );
        $this->assertCount( 2, $this->requests );
        $this->assertCount( 1, $this->snapshot( $target ) );
    }

    public function testRefreshTokenFlowQueryAllAndSandbox(): void
    {
        $this->script( array( $this->token( 'https://example--sandbox.sandbox.my.salesforce.com' ),
                              array( 200, array( 'totalSize' => 0, 'done' => true, 'records' => array() ) ) ) );
        $result = expImportSalesforceSource::test( $this->options( array( 'login_url' => 'https://test.salesforce.com', 'auth' => 'refresh_token',
                                                                          'client_secret' => 'env:SQ688_SF_SECRET', 'refresh_token' => 'env:SQ688_SF_REFRESH',
                                                                          'query_all' => true, 'api_version' => 'v60.0' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 0, $result['rows'] );
        $this->assertSame( 'https://test.salesforce.com/services/oauth2/token', $this->requests[0][1] );
        parse_str( (string)$this->requests[0][3], $form );
        $this->assertSame( array( 'grant_type' => 'refresh_token', 'client_id' => '3MVG9ExampleConsumerKey', 'refresh_token' => 'sf-test-refresh-token-not-real',
                                  'client_secret' => 'SampleConsumerSecret0123' ), $form );
        $this->assertStringStartsWith( 'https://example--sandbox.sandbox.my.salesforce.com/services/data/v60.0/queryAll?q=', $this->requests[1][1] );
    }

    public function testAddressesOfSalesforceOnly(): void
    {
        // an instance address of the token answer that is not Salesforce's: refused before the token is sent there
        $this->script( array( $this->token( 'https://collector.example.invalid' ) ) );
        $result = expImportSalesforceSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'AllowedDomains', $result['message'] );
        $this->assertCount( 1, $this->requests );
        foreach ( array( 'http://login.salesforce.com', 'https://login.salesforce.com.example.invalid', 'https://evil-salesforce.com', 'https://login.salesforce.com/path' ) as $login )
        {
            $this->script( array() );
            $this->assertFalse( expImportSalesforceSource::test( $this->options( array( 'login_url' => $login ) ) )['ok'], $login );
            $this->assertSame( array(), $this->requests, $login );
        }
        $this->assertSame( 'https://acme.my.salesforce.com', expImportSalesforceSource::checkUrl( 'https://acme.my.salesforce.com/', 'x' ) );
        // a next page that is not a query locator
        $this->script( array( $this->token(), array( 200, array( 'done' => false, 'nextRecordsUrl' => '/services/apexrest/run', 'records' => array( array( 'Id' => '1' ) ) ) ) ) );
        try
        {
            expImportSalesforceSource::fetch( $this->options(), $this->dir . '/s.ndjson', 0 );
            $this->fail( 'a next address that is not a query locator' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'query locator', $e->getMessage() );
        }
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SalesforceSourceSettings', 'AllowedHosts', array( 'sf-proxy.example.invalid' ) );
        $this->assertSame( 'https://sf-proxy.example.invalid', expImportSalesforceSource::checkUrl( 'https://sf-proxy.example.invalid', 'x' ) );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $this->script( array( $this->token(), array( 200, array( 'totalSize' => 57, 'done' => false, 'nextRecordsUrl' => '/services/data/v61.0/query/x-200',
                                                                 'records' => array( array( 'attributes' => array(), 'Id' => '1', 'Name' => 'A', 'Owner' => array( 'Name' => 'B' ) ) ) ) ) ) );
        $result = expImportSalesforceSource::test( $this->options() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( array( 'Id', 'Name', 'Owner.Name' ), $result['columns'] );
        $this->assertSame( 57, $result['rows'] );
        $this->assertResultAndDrivers( $result, expImportSalesforceSource::drivers() );
        // a refused sign-in that quotes the refresh token, and an API error quoting the access token: masked
        $this->script( array( array( 400, array( 'error' => 'invalid_grant', 'error_description' => 'expired sf-test-refresh-token-not-real' ) ) ) );
        $result = expImportSalesforceSource::test( $this->options( array( 'auth' => 'refresh_token', 'refresh_token' => 'env:SQ688_SF_REFRESH' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'invalid_grant', $result['message'] );
        $this->assertStringNotContainsString( 'SampleRefreshToken', $result['message'] );
        $this->script( array( $this->token(), array( 400, array( array( 'message' => 'bad session 00DEX0000000001!AccessTokenSample', 'errorCode' => 'MALFORMED_QUERY' ) ) ) ) );
        $result = expImportSalesforceSource::test( $this->options() );
        $this->assertStringContainsString( 'MALFORMED_QUERY', $result['message'] );
        $this->assertStringNotContainsString( 'AccessTokenSample', $result['message'] );
        // a key that is not a PEM, a missing secret, a missing user
        putenv( 'SQ688_SF_KEY=not a key' );
        $this->assertStringContainsString( 'not a valid PEM', expImportSalesforceSource::test( $this->options() )['message'] );
        $this->assertStringContainsString( "'no_such_sf_key' is not set", expImportSalesforceSource::test( $this->options( array( 'private_key' => 'no_such_sf_key' ) ) )['message'] );
        $this->assertStringContainsString( 'user name', expImportSalesforceSource::test( $this->options( array( 'user' => '' ) ) )['message'] );
    }
}
