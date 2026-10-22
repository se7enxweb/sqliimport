<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RedisSourceTestDoubles.php';

/**
 * The Redis source without a server: the schema and examples, the records of each type (hash fields, JSON strings,
 * lists, sets, sorted sets, streams, binary values), only read-only commands, SCAN paging with duplicates and the
 * limit, the test connection with a masked error, the driver list, the password read from a secret by its name, and
 * the socket client against a scripted server (RESP, AUTH and SELECT, refused commands, server errors).
 */
class RedisSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-redistest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RedisSourceSettings', 'AllowedHosts', array( '203.0.113.9' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
    }

    protected function tearDown(): void
    {
        expImportRedisSource::$transportFactory = null;
        expImportRedisSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowedHosts', 'Client', 'TestScanRounds' ) as $name )
            $ini->removeSetting( 'RedisSourceSettings', $name );
        $ini->removeSetting( 'SecretSettings', 'Secrets' );
        putenv( 'SQ688_REDIS_PASSWORD' );
    }

    /** @return array a database with a key of each type */
    protected function data(): array
    {
        return array(
            'product:1' => array( 'type' => 'hash', 'value' => array( 'sku' => 'TEA-1', 'name' => 'Sencha', '_key' => 'not a field', 'price' => '9.90' ) ),
            'product:2' => array( 'type' => 'string', 'value' => '{"sku":"TEA-2","name":"Oolong","sizes":[50,100]}', 'ttl' => 3600 ),
            'product:3' => array( 'type' => 'string', 'value' => 'plain text' ),
            'product:4' => array( 'type' => 'list', 'value' => array( 'a', 'b', 'c', 'd' ) ),
            'product:5' => array( 'type' => 'set', 'value' => array( 'zeta', 'alpha', 'mu' ) ),
            'product:6' => array( 'type' => 'zset', 'value' => array( 'low' => 1, 'mid' => 2.5, 'high' => 10 ) ),
            'product:7' => array( 'type' => 'stream', 'value' => array( array( '1700000000000-0', array( 'event', 'view', 'n', '1' ) ),
                                                                         array( '1700000000001-0', array( 'event', 'buy', 'n', '2' ) ) ) ),
            'product:8' => array( 'type' => 'string', 'value' => "\x89PNG\x00\xff" ),
            'session:1' => array( 'type' => 'string', 'value' => 'not matched' ),
            'product:9' => array( 'type' => 'ReJSON-RL', 'value' => '' ),
        );
    }

    protected function useTransport( RedisSourceTestTransport $transport ): void
    {
        expImportRedisSource::$transportFactory = function () use ( $transport )
        {
            return $transport;
        };
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportRedisSource::schema(), $more + array( 'host' => '203.0.113.9', 'pattern' => 'product:*' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the lines of a snapshot, by key */
    protected function snapshot( string $file ): array
    {
        $out = array();
        foreach ( array_filter( explode( "\n", (string)file_get_contents( $file ) ), 'strlen' ) as $line )
        {
            $row = json_decode( $line, true );
            $out[$row['_key']] = $row;
        }
        return $out;
    }

    public function testTheSchemaAndExamples(): void
    {
        $schema = expImportRedisSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['password']['type'] );
        $this->assertSame( '*', $schema['pattern']['default'] );
        $this->assertSame( '_key', $schema['key']['default'] );
        $this->assertSame( 'data', expImportRedisSource::group() );
        $this->assertSame( array( 'remote' ), expImportRedisSource::origins() );
        $examples = expImportRedisSource::examples();
        $source = new expImportRedisSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $examples[0]['file'], 'key' => '_key' ) );
        $records = iterator_to_array( $source->records(), false );
        $this->assertCount( 4, $records );
        $this->assertSame( 'product:1001', $records[0]->ref );
        $this->assertSame( 'Darjeeling', $records[2]->data['value']['name'] );
        foreach ( array_slice( $examples, 1 ) as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            expImportRedisSource::typesOf( $values['types'] );
            if ( isset( $example['options']['password'] ) )
                $this->assertNull( expImportSecrets::get( $example['options']['password'] ), 'an example names a secret, it has none' );
        }
        $this->assertSame( expImportRedisSource::TYPES, expImportRedisSource::typesOf( '' ) );
        $this->assertSame( array( 'hash', 'set' ), expImportRedisSource::typesOf( 'hash, set,hash' ) );
        $this->expectException( expImportSourceException::class );
        expImportRedisSource::typesOf( 'hash,module' );
    }

    public function testEachTypeIsReadWithReadOnlyCommands(): void
    {
        $transport = new RedisSourceTestTransport( $this->data() );
        $this->useTransport( $transport );
        $target = $this->dir . '/snapshot.ndjson';
        expImportRedisSource::fetch( $this->options( array( 'max_items' => 3 ) ), $target, 0 );
        $rows = $this->snapshot( $target );
        $this->assertSame( array( 'product:1', 'product:2', 'product:3', 'product:4', 'product:5', 'product:6', 'product:7', 'product:8' ), array_keys( $rows ),
                           'session:1 does not match, a module type is left out' );
        $this->assertSame( array( '_key' => 'product:1', '_type' => 'hash', '_ttl' => -1, 'sku' => 'TEA-1', 'name' => 'Sencha', 'price' => '9.90' ), $rows['product:1'],
                           'a hash field of a reserved name is left out' );
        $this->assertSame( array( 'sku' => 'TEA-2', 'name' => 'Oolong', 'sizes' => array( 50, 100 ) ), $rows['product:2']['value'] );
        $this->assertSame( 3600, $rows['product:2']['_ttl'] );
        $this->assertSame( 'plain text', $rows['product:3']['value'] );
        $this->assertSame( array( 'a', 'b', 'c' ), $rows['product:4']['values'], 'at most max_items values' );
        $this->assertSame( array( 'alpha', 'mu', 'zeta' ), $rows['product:5']['values'], 'set members sorted' );
        $this->assertSame( array( 'low', 'mid', 'high' ), $rows['product:6']['values'] );
        $this->assertEquals( array( 1, 2.5, 10 ), $rows['product:6']['scores'] );
        $this->assertSame( array( array( 'id' => '1700000000000-0', 'event' => 'view', 'n' => '1' ), array( 'id' => '1700000000001-0', 'event' => 'buy', 'n' => '2' ) ),
                           $rows['product:7']['entries'] );
        $this->assertSame( 'base64:' . base64_encode( "\x89PNG\x00\xff" ), $rows['product:8']['value'] );
        $sent = array_unique( array_map( function ( $c ) { return strtoupper( $c[0] ); }, $transport->commands ) );
        $this->assertSame( array(), array_diff( $sent, expImportRedisTransport::READ_COMMANDS ), 'only read-only commands' );
        $this->assertContains( array( 'ZRANGE', 'product:6', '0', '2', 'WITHSCORES' ), $transport->commands );
        $this->assertContains( array( 'XRANGE', 'product:7', '-', '+', 'COUNT', '3' ), $transport->commands );
        $this->assertContains( array( 'LRANGE', 'product:4', '0', '2' ), $transport->commands );
        $this->assertTrue( $transport->closed );
        // only some types, lists as JSON or joined, JSON strings left as text
        expImportRedisSource::fetch( $this->options( array( 'types' => 'list,set,string', 'arrays' => 'join', 'separator' => '|', 'decode_json' => false ) ), $target, 0 );
        $rows = $this->snapshot( $target );
        $this->assertSame( array( 'product:2', 'product:3', 'product:4', 'product:5', 'product:8' ), array_keys( $rows ) );
        $this->assertSame( 'a|b|c|d', $rows['product:4']['values'] );
        $this->assertSame( '{"sku":"TEA-2","name":"Oolong","sizes":[50,100]}', $rows['product:2']['value'] );
        expImportRedisSource::fetch( $this->options( array( 'types' => 'stream,zset', 'arrays' => 'join' ) ), $target, 0 );
        $rows = $this->snapshot( $target );
        $this->assertSame( 'low, mid, high', $rows['product:6']['values'] );
        $this->assertStringStartsWith( '[{"id":"1700000000000-0"', $rows['product:7']['entries'], 'stream entries stay JSON' );
        expImportRedisSource::fetch( $this->options( array( 'types' => 'set', 'arrays' => 'json' ) ), $target, 0 );
        $this->assertSame( '["alpha","mu","zeta"]', $this->snapshot( $target )['product:5']['values'] );
    }

    public function testScanPagingAndTheLimit(): void
    {
        $transport = new RedisSourceTestTransport( $this->data() );
        $transport->duplicates = true;
        $this->useTransport( $transport );
        $target = $this->dir . '/snapshot.ndjson';
        expImportRedisSource::fetch( $this->options( array( 'scan_count' => 3 ) ), $target, 0 );
        $this->assertCount( 8, $this->snapshot( $target ), 'a key SCAN gives twice is read once' );
        $scans = array_values( array_filter( $transport->commands, function ( $c ) { return $c[0] === 'SCAN'; } ) );
        $this->assertSame( array( 'SCAN', '0', 'MATCH', 'product:*', 'COUNT', '3' ), $scans[0] );
        $this->assertSame( array( '0', '3', '6' ), array_column( $scans, 1 ), 'each SCAN goes on from the cursor of the last' );
        // the sample of the editor and the option limit: the smaller wins
        $transport->commands = array();
        expImportRedisSource::fetch( $this->options( array( 'scan_count' => 3, 'limit' => 5 ) ), $target, 2 );
        $this->assertSame( array( 'product:1', 'product:2' ), array_keys( $this->snapshot( $target ) ) );
        expImportRedisSource::fetch( $this->options( array( 'scan_count' => 2, 'limit' => 3 ) ), $target, 0 );
        $this->assertCount( 3, $this->snapshot( $target ) );
        // the test connection stops after TestScanRounds steps of SCAN
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RedisSourceSettings', 'TestScanRounds', 2 );
        $transport->commands = array();
        $result = expImportRedisSource::test( $this->options( array( 'scan_count' => 1, 'types' => 'stream' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 0, $result['rows'] );
        $this->assertStringContainsString( 'in the first 2 steps of SCAN', $result['message'] );
        $this->assertCount( 2, array_filter( $transport->commands, function ( $c ) { return $c[0] === 'SCAN'; } ) );
    }

    public function testTheTestConnectionAndAMaskedFailure(): void
    {
        putenv( 'SQ688_REDIS_PASSWORD=pw-of-the-redis-test' );
        $transport = new RedisSourceTestTransport( $this->data() );
        $this->useTransport( $transport );
        $options = $this->options( array( 'user' => 'reader', 'password' => 'env:SQ688_REDIS_PASSWORD', 'database' => 2, 'tls' => true, 'port' => 6380 ) );
        $result = expImportRedisSource::test( $options );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( '_key', '_type', '_ttl', 'sku', 'name', 'price' ), $result['columns'] );
        $this->assertSame( 'memory', $result['driver'] );
        $this->assertSame( array( '203.0.113.9', 6380, true, 'reader', 'pw-of-the-redis-test', 2, 30 ), $transport->opened, 'the secret by its name' );
        $transport->failOpen = 'Redis: WRONGPASS invalid username-password pair (pw-of-the-redis-test)';
        $result = expImportRedisSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'WRONGPASS', $result['message'] );
        $this->assertStringNotContainsString( 'pw-of-the-redis-test', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_REDIS_PASSWORD]', $result['message'] );
        // a secret of the settings, and one that is not set
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SecretSettings', 'Secrets', array( 'cache_redis' => 'from-the-settings' ) );
        $transport->failOpen = null;
        $this->assertTrue( expImportRedisSource::test( array_merge( $options, array( 'password' => 'cache_redis' ) ) )['ok'] );
        $this->assertSame( 'from-the-settings', $transport->opened[4] );
        $result = expImportRedisSource::test( array_merge( $options, array( 'password' => 'not_set' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "the secret 'not_set' is not set", $result['message'] );
        // a private server is refused unless it is listed
        $transport->opened = null;
        $result = expImportRedisSource::test( $this->options( array( 'host' => '127.0.0.1' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'RedisSourceSettings', $result['message'] );
        $this->assertNull( $transport->opened );
    }

    public function testTheDriverListAndTheWorkers(): void
    {
        expImportRedisSource::$environment = array( 'redis' => '' );
        $drivers = expImportRedisSource::drivers();
        $this->assertSame( array( 'phpredis', 'socket' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertFalse( $drivers['phpredis']['available'] );
        $this->assertTrue( $drivers['socket']['available'], 'the built-in client needs nothing' );
        $this->assertInstanceOf( expImportRedisClient::class, expImportRedisSource::transport() );
        expImportRedisSource::$environment = array( 'redis' => '6.2.0' );
        expImportSqlSource::recordWorkerPhp();
        $this->assertSame( 'ext-redis 6.2.0', expImportSqlSource::workerPhp()['sources']['redis']['phpredis'] );
        expImportRedisSource::$environment = array( 'redis' => '' );
        $this->assertSame( 'yes', expImportRedisSource::drivers()['phpredis']['worker'] );
        // Client=socket even where the extension is installed; Client=phpredis without it fails with the reason
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RedisSourceSettings', 'Client', 'socket' );
        expImportRedisSource::$environment = array( 'redis' => '6.2.0' );
        $this->assertInstanceOf( expImportRedisClient::class, expImportRedisSource::transport() );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'RedisSourceSettings', 'Client', 'phpredis' );
        expImportRedisSource::$environment = array( 'redis' => '' );
        $this->expectException( expImportSourceException::class );
        $this->expectExceptionMessage( 'no extension redis' );
        expImportRedisSource::transport();
    }

    public function testTheSocketClientSpeaksResp(): void
    {
        $client = new RedisSourceTestScriptedClient( array(
            "+OK\r\n",                                              // AUTH
            "+OK\r\n",                                              // SELECT
            "*2\r\n\$1\r\n0\r\n*2\r\n\$5\r\nkey:1\r\n\$5\r\nkey:2\r\n", // SCAN
            "+hash\r\n",                                            // TYPE
            ":-1\r\n",                                              // TTL
            "*4\r\n\$4\r\nname\r\n\$6\r\nSencha\r\n\$4\r\nnote\r\n\$6\r\na\r\nb c\r\n", // HGETALL, a value with CRLF in it
            "\$-1\r\n",                                             // GET of a key that is gone
            "-WRONGTYPE Operation against a key holding the wrong kind of value\r\n",
        ) );
        $client->open( 'redis.test.invalid', 6380, true, 'reader', 'pw-of-the-client', 3, 5 );
        $this->assertSame( array( 'redis.test.invalid', 6380, true, 5 ), $client->connected );
        $this->assertSame( "*3\r\n\$4\r\nAUTH\r\n\$6\r\nreader\r\n\$16\r\npw-of-the-client\r\n", $client->sent[0] );
        $this->assertSame( "*2\r\n\$6\r\nSELECT\r\n\$1\r\n3\r\n", $client->sent[1] );
        $this->assertSame( array( '0', array( 'key:1', 'key:2' ) ), $client->command( array( 'SCAN', '0', 'MATCH', 'key:*', 'COUNT', '10' ) ) );
        $this->assertSame( 'hash', $client->command( array( 'TYPE', 'key:1' ) ) );
        $this->assertSame( -1, $client->command( array( 'TTL', 'key:1' ) ) );
        $this->assertSame( array( 'name', 'Sencha', 'note', "a\r\nb c" ), $client->command( array( 'HGETALL', 'key:1' ) ) );
        $this->assertNull( $client->command( array( 'GET', 'key:2' ) ) );
        try
        {
            $client->command( array( 'GET', 'key:1' ) );
            $this->fail( 'a server error is an exception' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'WRONGTYPE', $e->getMessage() );
        }
        $count = count( $client->sent );
        foreach ( array( 'SET', 'DEL', 'FLUSHALL', 'CONFIG', 'EVAL', 'KEYS', 'set', 'MULTI' ) as $command )
        {
            try
            {
                $client->command( array( $command, 'key:1', 'x' ) );
                $this->fail( "refused: $command" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'not one of the read-only commands', $e->getMessage() );
            }
        }
        $this->assertCount( $count, $client->sent, 'a refused command is never sent' );
        // without a user: AUTH with the password only; database 0: no SELECT
        $client = new RedisSourceTestScriptedClient( array( "+OK\r\n" ) );
        $client->open( 'redis.test.invalid', 6379, false, '', 'pw', 0, 5 );
        $this->assertSame( array( "*2\r\n\$4\r\nAUTH\r\n\$2\r\npw\r\n" ), $client->sent );
    }
}
