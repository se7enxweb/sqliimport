<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CassandraSourceTestDoubles.php';

/**
 * The Cassandra/ScyllaDB source: the query check (a single SELECT), contact points and hosts, paging with the paging
 * state, the record of a row, Test connection with masked errors, secrets by name, the choice of the driver and the
 * driver list (with a scripted connection), and the built-in client of the native protocol v4 against a scripted
 * server over a socket pair: STARTUP, AUTHENTICATE, USE, QUERY with page size and paging state, ERROR, and the
 * decoding of the common types. No Cassandra or ScyllaDB server is reached.
 */
class CassandraSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var CassandraSourceTestTransport */
    protected $transport;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-cassandratest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'AllowedHosts', array( 'cassandra.example.org', 'cassandra2.example.org' ) );
        putenv( 'SQ688_CQL_PASSWORD=cql-Password-Very-45' );
        $this->transport = new CassandraSourceTestTransport();
        for ( $i = 1; $i <= 5; $i++ )
            $this->transport->rows[] = array( 'event_id' => 'e-' . $i, 'title' => 'Event ' . $i, 'seats' => 10 * $i, 'tags' => array( 'tea', 't' . $i ),
                                              'attributes' => (object)array( 'room' => 'Hall ' . $i ), 'venue' => array( 'city' => 'Example City', 'street' => 'Market Street ' . $i ) );
        $transport = $this->transport;
        expImportCassandraSource::$transportFactory = function () use ( $transport ) { return $transport; };
    }

    protected function tearDown(): void
    {
        expImportCassandraSource::$transportFactory = null;
        expImportCassandraSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowedHosts', 'Client', 'MaxPages' ) as $name )
            $ini->removeSetting( 'CassandraSourceSettings', $name );
        putenv( 'SQ688_CQL_PASSWORD' );
    }

    /** @return array validated options */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportCassandraSource::schema(), $given + array(
            'hosts' => 'cassandra.example.org', 'keyspace' => 'shop', 'query' => 'SELECT * FROM events' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot of a fetch, by event_id */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportCassandraSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportCassandraSource( array( 'file' => $file, 'key' => 'event_id' ) ) )->records() as $record )
            $out[$record->ref] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportCassandraSource::schema() ) );
        $this->assertSame( 'nosql', expImportCassandraSource::group() );
        $this->assertSame( array( 'remote' ), expImportCassandraSource::origins() );
        foreach ( expImportCassandraSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportCassandraSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportCassandraSource::checkQuery( $example['options']['query'] ) );
        }
        $records = array();
        foreach ( ( new expImportCassandraSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/cassandra-events.ndjson', 'key' => 'event_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertCount( 3, $records );
        $this->assertSame( 'Example City', $records['5b6e0f2a-1c3d-11f0-8de9-0242ac120002']['venue.city'] );
    }

    public function testOnlyASingleSelect(): void
    {
        $ok = array( 'SELECT * FROM events', "select title, \"update\" from shop.events where kind = 'delete; drop' -- INSERT\n",
                     'SELECT JSON * FROM events WHERE day = ? LIMIT 10 ALLOW FILTERING;', "SELECT \$\$it's DROP\$\$ AS x FROM t // TRUNCATE",
                     "SELECT * FROM t WHERE a = 'it''s' /* BATCH */" );
        foreach ( $ok as $cql )
            $this->assertNull( expImportCassandraSource::checkQuery( $cql ), $cql );
        $refused = array( "INSERT INTO events (id) VALUES ('x')", "UPDATE events SET title = 'x' WHERE id = 'e-1'", "DELETE FROM events WHERE id = 'e-1'",
                          'BEGIN BATCH INSERT INTO t (a) VALUES (1); APPLY BATCH', 'TRUNCATE events', 'CREATE TABLE x (a int PRIMARY KEY)',
                          'ALTER TABLE events ADD x int', 'DROP KEYSPACE shop', 'GRANT ALL ON KEYSPACE shop TO reader', 'USE shop',
                          'SELECT * FROM a; DELETE FROM a WHERE id = 1', "SELECT * FROM t WHERE a = 'unclosed", 'SELECT * FROM t /* unclosed',
                          'LIST ROLES', '', "SELECT * FROM t USING TIMEOUT 5s" );
        foreach ( $refused as $cql )
            $this->assertNotNull( expImportCassandraSource::checkQuery( $cql ), $cql );
        $result = expImportCassandraSource::test( $this->options( array( 'query' => "DELETE FROM events WHERE id = 'e-1'" ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertNull( $this->transport->opened, 'a refused query connects to nothing' );
    }

    public function testContactPointsAndHosts(): void
    {
        $this->assertSame( array( array( 'cassandra.example.org', 9042 ), array( 'cassandra2.example.org', 9142 ) ),
                           expImportCassandraSource::hosts( 'cassandra.example.org, cassandra2.example.org:9142', 9042 ) );
        foreach ( array( '10.1.2.3', '127.0.0.1:9042', '[::1]:9042', 'cassandra.example.org:99999', ' , ' ) as $hosts )
        {
            $result = expImportCassandraSource::test( array( 'hosts' => $hosts, 'query' => 'SELECT * FROM events' ) );
            $this->assertFalse( $result['ok'], $hosts );
        }
        $this->assertNull( $this->transport->opened, 'nothing is opened for a refused host' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'AllowedHosts', array( '10.1.2.3', '::1' ) );
        $this->assertSame( array( array( '10.1.2.3', 9042 ), array( '::1', 9043 ) ), expImportCassandraSource::hosts( '10.1.2.3, [::1]:9043', 9042 ) );
    }

    public function testPagesWithThePagingState(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'user' => 'reader', 'password_secret' => 'env:SQ688_CQL_PASSWORD', 'tls' => true,
                                                          'consistency' => 'LOCAL_QUORUM', 'query' => 'SELECT * FROM events;' ) ) );
        $this->assertSame( array( 'e-1', 'e-2', 'e-3', 'e-4', 'e-5' ), array_keys( $records ) );
        $this->assertSame( array( null, 'state-2', 'state-4' ), array_column( $this->transport->queries, 'paging_state' ) );
        $this->assertSame( 'SELECT * FROM events', $this->transport->queries[0]['cql'], 'without the semicolon' );
        $this->assertSame( 'LOCAL_QUORUM', $this->transport->queries[0]['consistency'] );
        $this->assertSame( array( 'hosts' => array( array( 'cassandra.example.org', 9042 ) ), 'tls' => true, 'user' => 'reader',
                                  'password' => 'cql-Password-Very-45', 'keyspace' => 'shop', 'timeout' => 30 ), $this->transport->opened );
        $this->assertTrue( $this->transport->closed );
        $this->assertSame( array( 'event_id' => 'e-3', 'title' => 'Event 3', 'seats' => 30, 'tags' => '["tea","t3"]', 'attributes' => '{"room":"Hall 3"}',
                                  'venue.city' => 'Example City', 'venue.street' => 'Market Street 3' ), $records['e-3'] );
        // joined lists, the editor's sample, the option limit
        $this->transport->queries = array();
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'collections' => 'join', 'separator' => '|' ) ), 3 );
        $this->assertSame( 'tea|t1', $records['e-1']['tags'] );
        $this->assertCount( 3, $records );
        $this->assertSame( array( 2, 2 ), array_column( $this->transport->queries, 'page_size' ) );
        $this->transport->queries = array();
        $this->assertCount( 1, $this->fetched( $this->options( array( 'limit' => 1 ) ) ) );
        $this->assertSame( 1, $this->transport->queries[0]['page_size'] );
        // MaxPages
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'MaxPages', 2 );
        try
        {
            $this->fetched( $this->options( array( 'page_size' => 1 ) ) );
            $this->fail( 'MaxPages stops a run' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'MaxPages', $e->getMessage() );
        }
        // a value that is not finite, a list of maps
        $record = expImportCassandraSource::row( array( 'f' => NAN, 'i' => -INF, 'l' => array( 1.5, INF ), 'm' => array( (object)array( 'a' => 1 ) ), 'u' => array( 'm' => (object)array( 'k' => 'v' ) ) ) );
        $this->assertSame( 'NaN', $record['f'] );
        $this->assertSame( '-Infinity', $record['i'] );
        $this->assertSame( '[1.5,"Infinity"]', $record['l'] );
        $this->assertSame( '[{"a":1}]', $record['m'] );
        $this->assertSame( '{"k":"v"}', $record['u.m'] );
    }

    public function testTestConnection(): void
    {
        $result = expImportCassandraSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertSame( array( 'event_id', 'title', 'seats', 'tags', 'attributes', 'venue.city', 'venue.street' ), $result['columns'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( 'double', $result['driver'] );
        $this->assertSame( 1, $this->transport->queries[0]['page_size'] );
        // an error that quotes the password: masked
        $this->transport->failOpen = 'Cassandra: bad credentials: Provided username reader and/or password cql-Password-Very-45 are incorrect';
        $result = expImportCassandraSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'env:SQ688_CQL_PASSWORD' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'bad credentials', $result['message'] );
        $this->assertStringNotContainsString( 'cql-Password-Very-45', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_CQL_PASSWORD]', $result['message'] );
        // a secret that is not set: nothing is opened
        $this->transport->opened = null;
        $result = expImportCassandraSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'sq688_no_such_password' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_password' is not set", $result['message'] );
        $this->assertNull( $this->transport->opened );
    }

    public function testDriverChoiceAndList(): void
    {
        expImportCassandraSource::$transportFactory = null;
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'Client', 'socket' );
        $this->assertInstanceOf( 'expImportCassandraClient', expImportCassandraSource::transport() );
        if ( !expImportCassandraExtension::available() )
        {
            eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'Client', 'auto' );
            $this->assertInstanceOf( 'expImportCassandraClient', expImportCassandraSource::transport() );
            eZINI::instance( 'sqliimport.ini' )->setVariable( 'CassandraSourceSettings', 'Client', 'extension' );
            try
            {
                expImportCassandraSource::transport();
                $this->fail( 'Client=extension without the extension' );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'no extension cassandra', $e->getMessage() );
            }
        }
        expImportCassandraSource::$environment = array( 'cassandra' => '', 'socket' => true );
        $drivers = expImportCassandraSource::drivers();
        $this->assertSame( array( 'extension', 'socket' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertFalse( $drivers['extension']['available'] );
        $this->assertTrue( $drivers['socket']['available'] );
        // what the job queue's workers had
        expImportSqlSource::recordWorkerPhp();
        $this->assertArrayHasKey( 'cassandra', expImportSqlSource::workerPhp()['sources'] );
        $this->assertSame( 'no', expImportCassandraSource::drivers()['extension']['worker'] );
        $this->assertSame( 'yes', expImportCassandraSource::drivers()['socket']['worker'] );
    }

    // ---- the built-in client -----------------------------------------------------------------------------------------

    public function testSocketClientHandshakeQueryAndPaging(): void
    {
        $f = 'CassandraTestFrames';
        $client = new CassandraSocketTestClient();
        $client->down = array( 'cassandra.example.org' );
        $client->serve( $f::frame( 0x03, $f::string( 'org.apache.cassandra.auth.PasswordAuthenticator' ) )
                        . $f::frame( 0x10, $f::bytes( null ) )
                        . $f::frame( 0x08, $f::int( 3 ) . $f::string( 'shop' ) )
                        . $f::rows( array( 'id' => $f::type( 0x000D ), 'n' => $f::type( 0x0009 ) ), array( array( 'a', pack( 'N', 1 ) ), array( 'b', null ) ), "\x01\x02state" )
                        // a frame with a warning and a tracing id before its result
                        . $f::frame( 0x08, str_repeat( "\x00", 16 ) . pack( 'n', 1 ) . $f::string( 'Read 2 live rows' )
                                     . $f::int( 2 ) . $f::int( 0x0001 ) . $f::int( 1 ) . $f::string( 'shop' ) . $f::string( 'events' )
                                     . $f::string( 'id' ) . $f::type( 0x000D ) . $f::int( 1 ) . $f::bytes( 'c' ), 0x0A ) );
        $client->open( array( array( 'cassandra.example.org', 9042 ), array( 'cassandra2.example.org', 9142 ) ), false, 'reader', 'pw-1', 'shop', 5 );
        $this->assertSame( array( array( 'cassandra.example.org', 9042, false ), array( 'cassandra2.example.org', 9142, false ) ), $client->connects,
                           'the next contact point when the first does not answer' );
        $page = $client->query( 'SELECT id, n FROM events', 'LOCAL_ONE', 2, null );
        $this->assertSame( array( 'id', 'n' ), $page['columns'] );
        $this->assertSame( array( array( 'id' => 'a', 'n' => 1 ), array( 'id' => 'b', 'n' => null ) ), $page['rows'] );
        $this->assertSame( "\x01\x02state", $page['paging_state'] );
        $page = $client->query( 'SELECT id, n FROM events', 'LOCAL_ONE', 2, $page['paging_state'] );
        $this->assertSame( array( array( 'id' => 'c' ) ), $page['rows'] );
        $this->assertNull( $page['paging_state'] );
        $sent = $client->sent();
        $this->assertSame( array( 0x01, 0x0F, 0x07, 0x07, 0x07 ), array_column( $sent, 1 ), 'STARTUP, AUTH_RESPONSE, USE, and two pages' );
        foreach ( $sent as $frame )
            $this->assertSame( 0x04, $frame[0], 'protocol v4 requests' );
        $this->assertSame( pack( 'n', 1 ) . $f::string( 'CQL_VERSION' ) . $f::string( '3.0.0' ), $sent[0][2] );
        $this->assertSame( $f::bytes( "\0reader\0pw-1" ), $sent[1][2] );
        $this->assertSame( $f::int( 8 ) . 'USE shop' . pack( 'n', 0x01 ) . chr( 0 ), $sent[2][2] );
        $query = 'SELECT id, n FROM events';
        $this->assertSame( $f::int( strlen( $query ) ) . $query . pack( 'n', 0x0A ) . chr( 0x04 ) . $f::int( 2 ), $sent[3][2] );
        $this->assertSame( $f::int( strlen( $query ) ) . $query . pack( 'n', 0x0A ) . chr( 0x0C ) . $f::int( 2 ) . $f::bytes( "\x01\x02state" ), $sent[4][2] );
        // only a SELECT is sent
        try
        {
            $client->query( "DELETE FROM events WHERE id = 'a'", 'ONE', 1, null );
            $this->fail( 'only a SELECT' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'only a SELECT', $e->getMessage() );
        }
        $this->assertSame( array(), $client->sent(), 'nothing was sent' );
        $client->close();
    }

    public function testSocketClientErrors(): void
    {
        $f = 'CassandraTestFrames';
        $client = new CassandraSocketTestClient();
        $client->serve( $f::frame( 0x03, $f::string( 'org.apache.cassandra.auth.PasswordAuthenticator' ) )
                        . $f::frame( 0x00, $f::int( 0x0100 ) . $f::string( 'Provided username reader and/or password are incorrect' ) ) );
        try
        {
            $client->open( array( array( 'cassandra.example.org', 9042 ) ), false, 'reader', 'wrong', '', 5 );
            $this->fail( 'bad credentials' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'bad credentials: Provided username reader', $e->getMessage() );
        }
        $client = new CassandraSocketTestClient();
        $client->serve( $f::frame( 0x03, $f::string( 'org.apache.cassandra.auth.PasswordAuthenticator' ) ) );
        try
        {
            $client->open( array( array( 'cassandra.example.org', 9042 ) ), false, '', '', '', 5 );
            $this->fail( 'authentication needed' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'asks for authentication', $e->getMessage() );
        }
        $client = new CassandraSocketTestClient();
        $client->serve( $f::frame( 0x02, '' ) . $f::frame( 0x00, $f::int( 0x2200 ) . $f::string( 'unconfigured table nope' ) ) );
        $client->open( array( array( 'cassandra.example.org', 9042 ) ), false, '', '', '', 5 );
        try
        {
            $client->query( 'SELECT * FROM nope', 'ONE', 10, null );
            $this->fail( 'invalid query' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'invalid query: unconfigured table nope', $e->getMessage() );
        }
        // a server of another protocol version
        $client = new CassandraSocketTestClient();
        $client->serve( chr( 0x83 ) . chr( 0 ) . pack( 'n', 0 ) . chr( 0x00 ) . pack( 'N', 0 ) );
        try
        {
            $client->open( array( array( 'cassandra.example.org', 9042 ) ), false, '', '', '', 5 );
            $this->fail( 'protocol v3' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'native protocol v4', $e->getMessage() );
        }
    }

    public function testSocketClientDecodesTheCommonTypes(): void
    {
        $f = 'CassandraTestFrames';
        $d = function ( $type, $data ) { return expImportCassandraClient::decode( array( 'id' => $type ) + ( func_num_args() > 2 ? func_get_arg( 2 ) : array() ), $data ); };
        $this->assertSame( 'text', $d( 0x000D, 'text' ) );
        $this->assertSame( 'ascii', $d( 0x0001, 'ascii' ) );
        $this->assertSame( -2, $d( 0x0009, pack( 'N', 0xFFFFFFFE ) ) );
        $this->assertSame( 9007199254740993, $d( 0x0002, pack( 'J', 9007199254740993 ) ) );
        $this->assertSame( -1, $d( 0x0002, str_repeat( "\xFF", 8 ) ) );
        $this->assertSame( -300, $d( 0x0013, pack( 'n', 65236 ) ) );
        $this->assertSame( -128, $d( 0x0014, "\x80" ) );
        $this->assertTrue( $d( 0x0004, "\x01" ) );
        $this->assertFalse( $d( 0x0004, "\x00" ) );
        $this->assertSame( 1.5, $d( 0x0007, pack( 'E', 1.5 ) ) );
        $this->assertSame( 0.25, $d( 0x0008, pack( 'G', 0.25 ) ) );
        $this->assertSame( '0', expImportCassandraClient::varint( "\x00" ) );
        $this->assertSame( '127', expImportCassandraClient::varint( "\x7F" ) );
        $this->assertSame( '128', expImportCassandraClient::varint( "\x00\x80" ) );
        $this->assertSame( '-1', expImportCassandraClient::varint( "\xFF" ) );
        $this->assertSame( '-129', expImportCassandraClient::varint( "\xFF\x7F" ) );
        $this->assertSame( '1208925819614629174706176', expImportCassandraClient::varint( "\x01" . str_repeat( "\x00", 10 ) ), '2^80' );
        $this->assertSame( '-1208925819614629174706176', expImportCassandraClient::varint( "\xFF" . str_repeat( "\x00", 10 ) ) );
        $this->assertSame( '123.45', $d( 0x0006, pack( 'N', 2 ) . "\x30\x39" ) );
        $this->assertSame( '-0.05', $d( 0x0006, pack( 'N', 2 ) . "\xFB" ) );
        $this->assertSame( '1200', $d( 0x0006, pack( 'N', 0xFFFFFFFE ) . "\x0C" ) );
        $this->assertSame( '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', $d( 0x000C, hex2bin( '0f1e2d3c4b5a69788796a5b4c3d2e1f0' ) ) );
        $this->assertSame( '2026-04-02T08:30:00.250Z', $d( 0x000B, pack( 'J', 1775118600250 ) ) );
        $this->assertSame( '2026-04-02T08:30:00Z', $d( 0x000B, pack( 'J', 1775118600000 ) ) );
        $this->assertSame( '1969-12-31T23:59:59.999Z', $d( 0x000B, pack( 'J', -1 ) ) );
        $this->assertSame( '2026-04-02', $d( 0x0011, pack( 'N', 2147483648 + 20545 ) ) );
        $this->assertSame( '1970-01-01', $d( 0x0011, pack( 'N', 2147483648 ) ) );
        $this->assertSame( '08:30:00.5', $d( 0x0012, pack( 'J', 30600500000000 ) ) );
        $this->assertSame( '192.0.2.1', $d( 0x0010, "\xC0\x00\x02\x01" ) );
        $this->assertSame( '2001:db8::1', $d( 0x0010, hex2bin( '20010db8000000000000000000000001' ) ) );
        $this->assertSame( base64_encode( "\x00\xFFblob" ), $d( 0x0003, "\x00\xFFblob" ) );
        $text = array( 'id' => 0x000D );
        $int = array( 'id' => 0x0009 );
        $this->assertSame( array( 'a', 'b' ), $d( 0x0020, $f::collection( array( 'a', 'b' ) ), array( 'of' => array( $text ) ) ) );
        $this->assertSame( array( 1, 2 ), $d( 0x0022, $f::collection( array( pack( 'N', 1 ), pack( 'N', 2 ) ) ), array( 'of' => array( $int ) ) ) );
        $map = $d( 0x0021, $f::int( 2 ) . $f::bytes( 'room' ) . $f::bytes( pack( 'N', 2 ) ) . $f::bytes( 'floor' ) . $f::bytes( null ), array( 'of' => array( $text, $int ) ) );
        $this->assertEquals( (object)array( 'room' => 2, 'floor' => null ), $map );
        $this->assertSame( array( 'city' => 'Example City', 'zip' => 12345 ),
                           $d( 0x0030, $f::bytes( 'Example City' ) . $f::bytes( pack( 'N', 12345 ) ), array( 'fields' => array( 'city', 'zip' ), 'of' => array( $text, $int ) ) ) );
        $this->assertSame( array( 'a', 7 ), $d( 0x0031, $f::bytes( 'a' ) . $f::bytes( pack( 'N', 7 ) ), array( 'of' => array( $text, $int ) ) ) );
        // the types of a result's metadata, nested: map<text, frozen<list<int>>>
        $client = new CassandraSocketTestClient();
        $client->serve( $f::frame( 0x02, '' )
                        . $f::rows( array( 'm' => $f::type( 0x0021, $f::type( 0x000D ), $f::type( 0x0020, $f::type( 0x0009 ) ) ),
                                           'u' => $f::type( 0x0030, $f::string( 'shop' ), $f::string( 'address' ), pack( 'n', 1 ), $f::string( 'city' ), $f::type( 0x000D ) ) ),
                                    array( array( $f::int( 1 ) . $f::bytes( 'k' ) . $f::bytes( $f::collection( array( pack( 'N', 5 ) ) ) ), $f::bytes( 'Example Town' ) ) ) ) );
        $client->open( array( array( 'cassandra.example.org', 9042 ) ), false, '', '', '', 5 );
        $rows = $client->query( 'SELECT m, u FROM t', 'ONE', 10, null )['rows'];
        $this->assertEquals( (object)array( 'k' => array( 5 ) ), $rows[0]['m'] );
        $this->assertSame( array( 'city' => 'Example Town' ), $rows[0]['u'] );
        $this->assertSame( array( 'm' => '{"k":[5]}', 'u.city' => 'Example Town' ), expImportCassandraSource::row( $rows[0] ) );
    }
}
