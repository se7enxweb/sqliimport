<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/LdapSourceTestDoubles.php';

/**
 * The LDAP source against a directory in memory (LdapSourceTestTransport): the schema and examples, the filter check
 * of RFC 4515, attribute names, the encryption rule (ldaps://, StartTLS, unencrypted only to localhost when allowed),
 * the host check, paging, binary and multi-valued attributes, password attributes left out, Test connection with its
 * masked failure, the password by its secret and the driver rows with what the job queue's workers have.
 */
class LdapSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var LdapSourceTestTransport */
    protected $transport;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-ldaptest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        putenv( 'SQ688_LDAP_PASSWORD=pw-of-the-ldap-test' );
        // documentation addresses and names stand for public servers, whatever this PHP resolves
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'LdapSourceSettings', 'AllowedHosts', array( 'ldap.example.org', '203.0.113.7', 'localhost' ) );
        expImportLdapSource::$workerFile = $this->dir . '/worker.json';
        $this->transport = new LdapSourceTestTransport( $this->directory() );
        expImportLdapSource::$transportFactory = function ()
        {
            return $this->transport;
        };
    }

    protected function tearDown(): void
    {
        expImportLdapSource::$transportFactory = null;
        expImportLdapSource::$environment = null;
        expImportLdapSource::$workerFile = null;
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'LdapSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_LDAP_PASSWORD' );
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
    }

    /** @return array[] five entries of an invented directory */
    protected function directory(): array
    {
        $out = array();
        for ( $i = 1; $i <= 5; $i++ )
            $out[] = array( 'dn' => "uid=sample$i,ou=Staff,dc=example,dc=org",
                            'attributes' => array( 'uid' => array( "sample$i" ), 'cn' => array( "Sample $i" ), 'mail' => array( "sample$i@example.invalid" ),
                                                   'telephoneNumber' => $i === 1 ? array( '+1 555 0100', '+1 555 0101' ) : array( '+1 555 010' . ( $i + 1 ) ) ) );
        return $out;
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportLdapSource::schema(), $more + array(
            'uri' => 'ldaps://ldap.example.org', 'bind_dn' => 'cn=reader,ou=Services,dc=example,dc=org', 'password' => 'env:SQ688_LDAP_PASSWORD',
            'base_dn' => 'ou=Staff,dc=example,dc=org' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    protected function snapshot( string $file ): array
    {
        return array_map( function ( $line ) { return json_decode( $line, true ); }, file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
    }

    public function testSchemaAndExamples(): void
    {
        $schema = expImportLdapSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['password']['type'] );
        $this->assertSame( '(objectClass=*)', $schema['filter']['default'] );
        $this->assertSame( array( 'sub', 'one', 'base' ), array_keys( $schema['scope']['values'] ) );
        $this->assertSame( 'data', expImportLdapSource::group() );
        $this->assertSame( array( 'remote' ), expImportLdapSource::origins() );
        foreach ( expImportLdapSource::examples() as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportLdapSource::checkFilter( $values['filter'] ), $example['label'] );
            if ( $values['password'] !== '' )
                $this->assertNull( expImportSecrets::get( $values['password'] ) );
            if ( isset( $example['file'] ) )
            {
                $source = new expImportLdapSource( $values + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'] ) );
                $records = iterator_to_array( $source->records(), false );
                $this->assertCount( 3, $records );
                $this->assertSame( 'uid=sample.one,ou=Staff,dc=example,dc=org', $records[0]->ref );
            }
        }
    }

    public function testFilterValidation(): void
    {
        foreach ( array( '(objectClass=*)', 'objectClass=*', '(&(objectClass=inetOrgPerson)(mail=*))', '(|(cn=Sample*)(sn=*One))', '(!(employeeType=former))',
                         '(cn=a*b*c)', '(cn~=sampel)', '(createTimestamp>=20260101000000Z)', '(uidNumber<=1000)', '(cn=a\\28b\\29)', '(cn=\\2a)',
                         '(memberOf:1.2.840.113556.1.4.1941:=cn=Press,ou=Groups,dc=example,dc=org)', '(cn:dn:=Sample)', '(:caseExactMatch:=Sample)',
                         '(2.5.4.3=Sample)', '(cn;lang-de=Probe)', '(&(a=1)(|(b=2)(!(c=3))))', '(cn=)', '(&(a=1) (b=2))' ) as $filter )
            $this->assertNull( expImportLdapSource::checkFilter( $filter ), $filter );
        foreach ( array( '' => 'empty', '(cn=a' => ')', '(cn=a)(sn=b)' => 'after its last', '(cn=a(b)' => '\\28', '(&)' => 'at least one',
                         '(!(a=1)(b=2))' => 'exactly one', '(cn>=a*)' => 'only works with =', '(cn=a\\zz)' => 'two hexadecimal', '(=a)' => 'is not attribute',
                         '(1cn=a)' => 'is not attribute', "(cn=a\n)" => 'control', '((cn=a))' => '\\28', '(cn:=a*)' => 'no *',
                         '(cn~=)' => 'a value is expected' ) as $filter => $reason )
        {
            $error = expImportLdapSource::checkFilter( $filter );
            $this->assertNotNull( $error, $filter );
            $this->assertStringContainsString( $reason, $error, $filter );
        }
        $this->assertStringContainsString( '32 deep', (string)expImportLdapSource::checkFilter( str_repeat( '(!', 40 ) . '(a=1)' . str_repeat( ')', 40 ) ) );
        $this->assertSame( '(objectClass=*)', expImportLdapSource::normalizeFilter( '' ) );
        $this->assertSame( '(cn=x)', expImportLdapSource::normalizeFilter( 'cn=x' ) );
        // a refused filter is never sent
        $result = expImportLdapSource::test( $this->options( array( 'filter' => '(cn=a)(|(uid=*))' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertSame( array(), $this->transport->calls );
        $this->assertNull( expImportLdapSource::checkAttribute( 'jpegPhoto' ) );
        $this->assertNull( expImportLdapSource::checkAttribute( 'userCertificate;binary' ) );
        $this->assertNull( expImportLdapSource::checkAttribute( '+' ) );
        $this->assertNotNull( expImportLdapSource::checkAttribute( 'cn)(uid=*' ) );
        $this->assertFalse( expImportLdapSource::test( $this->options( array( 'attributes' => "cn\nmail,uid" ) ) )['ok'] );
    }

    public function testEncryptionIsRequired(): void
    {
        $this->assertSame( array( 'ldaps://ldap.example.org:636', 'ldap.example.org', false ), expImportLdapSource::server( array( 'uri' => 'ldaps://ldap.example.org' ) ) );
        $this->assertSame( array( 'ldaps://ldap.example.org:636', 'ldap.example.org', false ), expImportLdapSource::server( array( 'uri' => 'ldap.example.org' ) ), 'no scheme: ldaps' );
        $this->assertSame( array( 'ldap://ldap.example.org:389', 'ldap.example.org', true ), expImportLdapSource::server( array( 'uri' => 'ldap://ldap.example.org' ) ), 'ldap://: StartTLS' );
        $this->assertSame( array( 'ldap://203.0.113.7:3389', '203.0.113.7', true ), expImportLdapSource::server( array( 'uri' => 'ldap://203.0.113.7:3389', 'starttls' => true ) ) );
        $refused = array(
            array( 'uri' => 'ldap://ldap.example.org', 'starttls' => false ),
            array( 'uri' => 'ldap://ldap.example.org', 'starttls' => false, 'allow_plain' => true ),
            array( 'uri' => 'ldap://localhost', 'starttls' => false ),
        );
        foreach ( $refused as $options )
        {
            try
            {
                expImportLdapSource::server( $options );
                $this->fail( 'refused: ' . json_encode( $options ) );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'must be encrypted', $e->getMessage() );
            }
        }
        // unencrypted only to localhost, when the import allows it
        $this->assertSame( array( 'ldap://localhost:389', 'localhost', false ), expImportLdapSource::server( array( 'uri' => 'ldap://localhost', 'starttls' => false, 'allow_plain' => true ) ) );
        foreach ( array( 'ldaps://ldap.example.org/dc=x', 'ldaps://a ldaps://b', 'http://ldap.example.org', 'ldaps://ldap.example.org:99999' ) as $uri )
        {
            try
            {
                expImportLdapSource::server( array( 'uri' => $uri ) );
                $this->fail( "refused: $uri" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringStartsWith( 'LDAP: ', $e->getMessage() );
            }
        }
        // a private network only when listed
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'LdapSourceSettings', 'AllowedHosts', array() );
        try
        {
            expImportLdapSource::server( array( 'uri' => 'ldaps://10.0.0.12' ) );
            $this->fail( 'a private address is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'AllowedHosts', $e->getMessage() );
        }
        // StartTLS is asked for before the bind
        expImportLdapSource::test( $this->options( array( 'uri' => 'ldap://203.0.113.7', 'starttls' => true ) ) );
    }

    public function testStartTlsComesBeforeTheBind(): void
    {
        expImportLdapSource::test( $this->options( array( 'uri' => 'ldap://ldap.example.org' ) ) );
        $this->assertSame( array( 'connect ldap://ldap.example.org:389', 'starttls', 'bind cn=reader,ou=Services,dc=example,dc=org pw-of-the-ldap-test' ),
                           $this->transport->calls );
        $this->assertTrue( $this->transport->closed );
    }

    public function testPagingAndValues(): void
    {
        $target = $this->dir . '/source.ndjson';
        expImportLdapSource::fetch( $this->options( array( 'page_size' => 2, 'filter' => '(objectClass=inetOrgPerson)', 'scope' => 'one',
                                                           'attributes' => "uid\ncn\nmail\ntelephoneNumber\ntitle" ) ), $target, 0 );
        $this->assertCount( 3, $this->transport->searches, 'three pages of two' );
        $this->assertSame( array( 'ou=Staff,dc=example,dc=org', '(objectClass=inetOrgPerson)', array( 'uid', 'cn', 'mail', 'telephoneNumber', 'title' ), 'one', 0, 2, '' ),
                           $this->transport->searches[0] );
        $this->assertSame( array( '', '2', '4' ), array_column( $this->transport->searches, 6 ), 'each page with the cookie of the one before' );
        $records = $this->snapshot( $target );
        $this->assertCount( 5, $records );
        $this->assertSame( 'uid=sample1,ou=Staff,dc=example,dc=org', $records[0]['dn'] );
        $this->assertSame( array( '+1 555 0100', '+1 555 0101' ), $records[0]['telephoneNumber'], 'several values: a list' );
        $this->assertSame( '+1 555 0103', $records[1]['telephoneNumber'], 'one value: the value' );
        $this->assertArrayHasKey( 'title', $records[0], 'a listed attribute is in every record' );
        $this->assertNull( $records[0]['title'] );
        $this->assertFileDoesNotExist( $target . '.part' );
        // the size limit and the sample stop the search
        $this->transport->searches = array();
        expImportLdapSource::fetch( $this->options( array( 'page_size' => 2, 'size_limit' => 3 ) ), $target, 0 );
        $this->assertCount( 3, $this->snapshot( $target ) );
        $this->assertCount( 2, $this->transport->searches );
        expImportLdapSource::fetch( $this->options( array( 'page_size' => 0 ) ), $target, 2 );
        $this->assertCount( 2, $this->snapshot( $target ) );
        // without paging: one search
        $this->transport->searches = array();
        expImportLdapSource::fetch( $this->options( array( 'page_size' => 0 ) ), $target, 0 );
        $this->assertCount( 1, $this->transport->searches );
        $this->assertCount( 5, $this->snapshot( $target ) );
        // joined
        $this->assertSame( '+1 555 0100 | +1 555 0101', expImportLdapSource::record( $this->directory()[0], array( 'multi_valued' => 'join', 'separator' => ' | ' ) )['telephoneNumber'] );
    }

    public function testBinaryAndPasswordAttributes(): void
    {
        $guid = pack( 'H*', '78563412bc9a0ef0123456789abcdef0' );
        $photo = "\xFF\xD8\xFF\xE0" . 'JFIF' . "\x00\x01";
        $entry = array( 'dn' => 'cn=Sample,dc=example,dc=org', 'attributes' => array(
            'cn' => array( 'Sample' ), 'jpegPhoto' => array( $photo ), 'objectGUID' => array( $guid ), 'objectSid' => array( "\x01\x05\x00\x00" ),
            'description' => array( "not text \xC3\x28" ), 'userCertificate;binary' => array( 'abc' ), 'userPassword' => array( '{SSHA}abcdef' ),
            'unicodePwd' => array( 'x' ) ) );
        $record = expImportLdapSource::record( $entry );
        $this->assertSame( 'base64:' . base64_encode( $photo ), $record['jpegPhoto'] );
        $this->assertSame( 'base64:' . base64_encode( $guid ), $record['objectGUID'] );
        $this->assertSame( '12345678-9abc-f00e-1234-56789abcdef0', $record['objectGUID_text'] );
        $this->assertSame( 'base64:' . base64_encode( "not text \xC3\x28" ), $record['description'], 'a value that is not UTF-8 is binary' );
        $this->assertSame( 'base64:' . base64_encode( 'abc' ), $record['userCertificate;binary'] );
        $this->assertArrayNotHasKey( 'userPassword', $record, 'a password attribute is never read' );
        $this->assertArrayNotHasKey( 'unicodePwd', $record );
        $skipped = expImportLdapSource::record( $entry, array( 'binary' => 'skip' ), array( 'cn', 'jpegPhoto', 'userPassword' ) );
        $this->assertSame( array( 'dn', 'cn', 'objectGUID_text' ), array_keys( $skipped ) );
        // the whole record is JSON
        $this->assertNotFalse( json_encode( $record ) );
    }

    public function testTheTestConnectionAndItsMaskedFailure(): void
    {
        $result = expImportLdapSource::test( $this->options() );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( array( 'dn', 'uid', 'cn', 'mail', 'telephoneNumber' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( 'ldap', $result['driver'] );
        $this->assertSame( array( 1, 0 ), array( $this->transport->searches[0][4], $this->transport->searches[0][5] ), 'one entry, no paging' );
        // a refused bind whose message quotes the password: masked
        $this->transport = new LdapSourceTestTransport( $this->directory() );
        $this->transport->failBind = 'LDAP: the bind failed: Invalid credentials (password pw-of-the-ldap-test)';
        $result = expImportLdapSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Invalid credentials', $result['message'] );
        $this->assertStringNotContainsString( 'pw-of-the-ldap-test', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_LDAP_PASSWORD]', $result['message'] );
        $this->assertTrue( $this->transport->closed, 'the connection is closed after an error too' );
        // anonymous
        $this->transport = new LdapSourceTestTransport( array() );
        $result = expImportLdapSource::test( $this->options( array( 'bind_dn' => '', 'password' => '' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 0, $result['rows'] );
        $this->assertSame( 'bind (anonymous)', $this->transport->calls[1] );
        // a bind DN without a password, and a secret that is not set
        $this->assertStringContainsString( 'needs the name of the secret', expImportLdapSource::test( $this->options( array( 'password' => '' ) ) )['message'] );
        $this->assertStringContainsString( "'no_such_ldap_secret' is not set", expImportLdapSource::test( $this->options( array( 'password' => 'no_such_ldap_secret' ) ) )['message'] );
        $this->assertStringContainsString( 'not a DN', expImportLdapSource::test( $this->options( array( 'base_dn' => 'Staff' ) ) )['message'] );
    }

    public function testThePasswordComesFromTheSettingsByName(): void
    {
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'ldap_test_reader' => 'pw-from-the-settings' ) );
        try
        {
            expImportLdapSource::test( $this->options( array( 'password' => 'ldap_test_reader' ) ) );
            $this->assertSame( 'bind cn=reader,ou=Services,dc=example,dc=org pw-from-the-settings', $this->transport->calls[1] );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
    }

    public function testDriversAndTheWorkers(): void
    {
        expImportLdapSource::$environment = array( 'ldap' => false, 'paging' => false );
        $drivers = expImportLdapSource::drivers();
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['ldap'] );
        $this->assertFalse( $drivers['ldap']['available'] );
        $this->assertSame( '', $drivers['ldap']['worker'] );
        $this->assertStringContainsString( 'php-ldap', $drivers['ldap']['needs'] );
        // the workers have it
        expImportLdapSource::$environment = array( 'ldap' => true, 'paging' => true );
        expImportLdapSource::recordWorkerPhp();
        $this->assertFileExists( $this->dir . '/worker.json' );
        expImportLdapSource::$environment = array( 'ldap' => false, 'paging' => false );
        $drivers = expImportLdapSource::drivers();
        $this->assertSame( array( false, 'yes' ), array( $drivers['ldap']['available'], $drivers['ldap']['worker'] ) );
        $this->assertStringContainsString( PHP_VERSION, $drivers['ldap']['worker_php'] );
        expImportLdapSource::$environment = array( 'ldap' => true, 'paging' => true );
        $this->assertSame( 'ldap (paged results)', expImportLdapSource::drivers()['ldap']['via'] );
    }
}
