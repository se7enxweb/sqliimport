<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/MongoDbSourceTestDoubles.php';

/**
 * The MongoDB source without a server: the schema and examples, the refusal of operators that run code or write, the
 * URI checks (no credentials in it, private hosts), flattening and the BSON type mapping, paging by _id and by skip,
 * the test connection with a masked error, the driver list, and the password read from a secret by its name.
 */
class MongoDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-mongotest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        // 203.0.113.7 (a documentation address) stands for a public server, whatever this PHP counts as reserved
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'MongoDbSourceSettings', 'AllowedHosts', array( '203.0.113.7', 'mongo.test.invalid', 'db2.test.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
    }

    protected function tearDown(): void
    {
        expImportMongoDbSource::$transportFactory = null;
        expImportMongoDbSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->removeSetting( 'MongoDbSourceSettings', 'AllowedHosts' );
        $ini->removeSetting( 'SecretSettings', 'Secrets' );
        putenv( 'SQ688_MONGO_PASSWORD' );
    }

    /** @return array[] five documents with nested documents, arrays and BSON values */
    protected function documents(): array
    {
        $out = array();
        foreach ( array( 1, 2, 3, 4, 5 ) as $i )
            $out[] = array(
                '_id' => new MongoDB\BSON\ObjectId( '6717a1f0c2d4e5f60123456' . $i ),
                'title' => "Tea $i",
                'author' => array( 'name' => 'Desk', 'address' => array( 'city' => 'Town' ) ),
                'tags' => array( 'tea', "t$i" ),
                'published_at' => new MongoDB\BSON\UTCDateTime( 1775118600000 + $i ),
                'price' => new MongoDB\BSON\Decimal128( '12.5' . $i ),
            );
        return $out;
    }

    protected function useTransport( MongoDbSourceTestTransport $transport ): void
    {
        expImportMongoDbSource::$transportFactory = function () use ( $transport )
        {
            return $transport;
        };
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportMongoDbSource::schema(),
                                                                    $more + array( 'uri' => '203.0.113.7', 'database' => 'cms', 'collection' => 'articles' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the lines of a snapshot */
    protected function snapshot( string $file ): array
    {
        return array_map( function ( $line ) { return json_decode( $line, true ); }, array_filter( explode( "\n", (string)file_get_contents( $file ) ), 'strlen' ) );
    }

    public function testTheSchemaAndExamples(): void
    {
        $schema = expImportMongoDbSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( 'secret', $schema['password']['type'] );
        $this->assertSame( 'id', $schema['paging']['default'] );
        $this->assertSame( '_id', $schema['key']['default'] );
        $this->assertSame( array( 'json', 'join' ), array_keys( $schema['arrays']['values'] ) );
        $this->assertSame( 'data', expImportMongoDbSource::group() );
        $this->assertSame( array( 'remote' ), expImportMongoDbSource::origins() );
        $examples = expImportMongoDbSource::examples();
        $this->assertFileExists( dirname( __DIR__, 2 ) . '/share/examples/' . $examples[0]['file'] );
        // the snapshot sample reads like a run's snapshot
        $source = new expImportMongoDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $examples[0]['file'], 'key' => '_id' ) );
        $records = iterator_to_array( $source->records(), false );
        $this->assertCount( 3, $records );
        $this->assertSame( '6717a1f0c2d4e5f601234561', $records[0]->ref );
        $this->assertSame( 'Editorial team', $records[0]->data['author.name'] );
        foreach ( array_slice( $examples, 1 ) as $example )
        {
            list( $values, $errors ) = expImportOptionSchema::validate( $schema, $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
            $this->assertNull( expImportSecrets::get( $example['options']['password'] ), 'an example names a secret, it has none' );
            foreach ( array( 'filter', 'projection', 'sort' ) as $name )
                expImportMongoDbSource::checkJson( isset( $values[$name] ) ? $values[$name] : '', $name );
        }
    }

    public function testOperatorsThatRunCodeOrWriteAreRefused(): void
    {
        $this->assertSame( array(), expImportMongoDbSource::checkJson( '', 'filter' ) );
        $this->assertSame( array( 'status' => 'published', 'n' => array( '$gte' => 3 ) ), expImportMongoDbSource::checkJson( '{"status": "published", "n": {"$gte": 3}}', 'filter' ) );
        $this->assertSame( array( 'a' => array( '$oid' => '6717a1f0c2d4e5f601234561' ) ), expImportMongoDbSource::checkJson( '{"a": {"$oid": "6717a1f0c2d4e5f601234561"}}', 'filter' ) );
        $refused = array(
            '{"$where": "this.a > 1"}',
            '{"a": 1, "$WHERE": "sleep(1000)"}',
            '{"$expr": {"$function": {"body": "function() { return true }", "args": [], "lang": "js"}}}',
            '{"$expr": {"$gt": [{"$accumulator": {}}, 1]}}',
            '{"$and": [{"a": 1}, {"$where": "1"}]}',
            '{"x": {"$out": "copy"}}',
            '{"x": [{"y": {"$merge": {"into": "other"}}}]}',
        );
        foreach ( $refused as $json )
        {
            try
            {
                expImportMongoDbSource::checkJson( $json, 'filter' );
                $this->fail( "refused: $json" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'only reading is allowed', $e->getMessage(), $json );
            }
        }
        foreach ( array( '[{"$match": {}}, {"$out": "x"}]', 'not json', '{"a": ' ) as $json )
        {
            try
            {
                expImportMongoDbSource::checkJson( $json, 'filter' );
                $this->fail( "refused: $json" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringStartsWith( 'MongoDB: the filter', $e->getMessage() );
            }
        }
        // a refused filter never reaches the server
        $transport = new MongoDbSourceTestTransport( $this->documents() );
        $this->useTransport( $transport );
        $result = expImportMongoDbSource::test( $this->options( array( 'projection' => '{"x": {"$function": {}}}' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( '$function', $result['message'] );
        $this->assertSame( array(), $transport->finds );
    }

    public function testTheUriAndItsHosts(): void
    {
        $this->assertSame( array( 'mongodb://203.0.113.7:27017/', array( '203.0.113.7' ), false ), expImportMongoDbSource::uriOf( array( 'uri' => '203.0.113.7' ) ) );
        $this->assertSame( 'mongodb://203.0.113.7:27018/', expImportMongoDbSource::uriOf( array( 'uri' => '203.0.113.7', 'port' => 27018 ) )[0] );
        $this->assertSame( 'mongodb://mongo.test.invalid:27019/', expImportMongoDbSource::uriOf( array( 'uri' => 'mongo.test.invalid:27019', 'port' => 1 ) )[0] );
        $this->assertSame( 'mongodb://[2001:db8::7]:27017/', expImportMongoDbSource::uriOf( array( 'uri' => '[2001:db8::7]' ) )[0] );
        list( $uri, $hosts ) = expImportMongoDbSource::uriOf( array( 'uri' => 'mongodb://mongo.test.invalid:27017,db2.test.invalid/?replicaSet=rs0' ) );
        $this->assertSame( array( 'mongo.test.invalid', 'db2.test.invalid' ), $hosts );
        foreach ( array( 'mongodb://reader:pw-in-uri@mongo.test.invalid/', 'mongodb://reader%40x@mongo.test.invalid/', 'mongodb://mongo.test.invalid/?password=x',
                         'mongodb://mongo.test.invalid/?tlsCertificateKeyFilePassword=x', 'mongodb+srv://a.test.invalid:27017/', 'mongodb+srv://a.test.invalid,b.test.invalid/',
                         'mongo.test.invalid/../x', 'http://mongo.test.invalid/', '', 'host with space' ) as $bad )
        {
            try
            {
                expImportMongoDbSource::uriOf( array( 'uri' => $bad ) );
                $this->fail( "refused: $bad" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringStartsWith( 'MongoDB:', $e->getMessage() );
                $this->assertStringNotContainsString( 'pw-in-uri', $e->getMessage() );
            }
        }
        // a private server is refused unless it is listed
        $transport = new MongoDbSourceTestTransport( array() );
        $this->useTransport( $transport );
        $result = expImportMongoDbSource::test( $this->options( array( 'uri' => '127.0.0.1' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'MongoDbSourceSettings', $result['message'] );
        $this->assertNull( $transport->opened, 'nothing connected' );
        // system collections and bad names
        foreach ( array( array( 'collection' => 'system.users' ), array( 'database' => 'a.b' ), array( 'collection' => '$cmd' ) ) as $change )
        {
            try
            {
                expImportMongoDbSource::plan( array_merge( array( 'uri' => '203.0.113.7', 'database' => 'cms', 'collection' => 'articles' ), $change ) );
                $this->fail( 'refused: ' . json_encode( $change ) );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringStartsWith( 'MongoDB:', $e->getMessage() );
            }
        }
    }

    public function testFlatteningAndTypeMapping(): void
    {
        $uuid = hex2bin( '0f1e2d3c4b5a69788796a5b4c3d2e1f0' );
        $document = array(
            '_id' => new MongoDB\BSON\ObjectId( '6717a1f0c2d4e5f601234561' ),
            'title' => 'Sencha',
            'meta' => array( 'sku' => 'TEA-1', 'size' => array( 'grams' => 100, 'unit' => 'g' ), 'empty' => array() ),
            'tags' => array( 'green', 'japan' ),
            'variants' => array( array( 'grams' => 50 ), array( 'grams' => 100 ) ),
            'at' => new MongoDB\BSON\UTCDateTime( 1775118600000 ),
            'at_ms' => new MongoDB\BSON\UTCDateTime( 1775118600250 ),
            'price' => new MongoDB\BSON\Decimal128( '12.50' ),
            'thumb' => new MongoDB\BSON\Binary( "\x89PNG\x00\xff", 0 ),
            'uuid' => new MongoDB\BSON\Binary( $uuid, 4 ),
            'pattern' => new MongoDB\BSON\Regex( '^tea', 'i' ),
            'changed' => new MongoDB\BSON\Timestamp( 1, 1775118600 ),
            'dates' => array( new MongoDB\BSON\UTCDateTime( 0 ) ),
            'flag' => true,
            'none' => null,
            'n' => 3,
            'f' => 1.5,
        );
        $flat = expImportMongoDbSource::flatten( $document );
        $this->assertSame( '6717a1f0c2d4e5f601234561', $flat['_id'] );
        $this->assertSame( 'TEA-1', $flat['meta.sku'] );
        $this->assertSame( 100, $flat['meta.size.grams'] );
        $this->assertSame( 'g', $flat['meta.size.unit'] );
        $this->assertSame( '[]', $flat['meta.empty'] );
        $this->assertArrayNotHasKey( 'meta', $flat );
        $this->assertSame( '["green","japan"]', $flat['tags'] );
        $this->assertSame( '[{"grams":50},{"grams":100}]', $flat['variants'] );
        $this->assertSame( '2026-04-02T08:30:00Z', $flat['at'] );
        $this->assertSame( '2026-04-02T08:30:00.250Z', $flat['at_ms'] );
        $this->assertSame( '12.50', $flat['price'] );
        $this->assertSame( base64_encode( "\x89PNG\x00\xff" ), $flat['thumb'] );
        $this->assertSame( '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', $flat['uuid'] );
        $this->assertSame( '/^tea/i', $flat['pattern'] );
        $this->assertSame( '2026-04-02T08:30:00Z', $flat['changed'] );
        $this->assertSame( '["1970-01-01T00:00:00Z"]', $flat['dates'], 'BSON values inside an array are mapped too' );
        $this->assertSame( array( true, null, 3, 1.5 ), array( $flat['flag'], $flat['none'], $flat['n'], $flat['f'] ) );
        $joined = expImportMongoDbSource::flatten( $document, 'join', ' | ' );
        $this->assertSame( 'green | japan', $joined['tags'] );
        $this->assertSame( '[{"grams":50},{"grams":100}]', $joined['variants'], 'an array of documents stays JSON' );
        $this->assertSame( '1970-01-01T00:00:00Z', $joined['dates'] );
        $this->assertSame( '', $joined['meta.empty'] );
        // a very deep document ends in JSON
        $deep = array( 'v' => 1 );
        for ( $i = 0; $i < 40; $i++ )
            $deep = array( 'd' => $deep );
        $flat = expImportMongoDbSource::flatten( array( 'x' => $deep ) );
        $this->assertCount( 1, $flat );
        $this->assertStringStartsWith( '{"d":', (string)reset( $flat ) );
    }

    public function testPagingByIdAndBySkip(): void
    {
        $transport = new MongoDbSourceTestTransport( $this->documents() );
        $this->useTransport( $transport );
        $target = $this->dir . '/snapshot.ndjson';
        expImportMongoDbSource::fetch( $this->options( array( 'batch_size' => 2, 'filter' => '{"status": "published"}' ) ), $target, 0 );
        $rows = $this->snapshot( $target );
        $this->assertSame( array( 'Tea 1', 'Tea 2', 'Tea 3', 'Tea 4', 'Tea 5' ), array_column( $rows, 'title' ) );
        $this->assertSame( 'Desk', $rows[0]['author.name'] );
        $this->assertSame( 'Town', $rows[0]['author.address.city'] );
        $this->assertSame( '12.51', $rows[0]['price'] );
        $this->assertCount( 3, $transport->finds, 'three pages of two' );
        $this->assertNull( $transport->finds[0][3] );
        $this->assertSame( '6717a1f0c2d4e5f601234562', (string)$transport->finds[1][3], 'the next page starts after the last _id' );
        $this->assertSame( '{"status": "published"}', $transport->finds[0][2] );
        $this->assertSame( array( '_id' => 1 ), $transport->finds[0][4]['sort'] );
        $this->assertSame( 2, $transport->finds[0][4]['limit'] );
        $this->assertSame( 'primary', $transport->finds[0][4]['readPreference'] );
        $this->assertTrue( $transport->closed );
        $this->assertFileDoesNotExist( $target . '.part' );
        // the editor's sample: the limit of the call
        $transport->finds = array();
        expImportMongoDbSource::fetch( $this->options( array( 'batch_size' => 2 ) ), $target, 3 );
        $this->assertCount( 3, $this->snapshot( $target ) );
        $this->assertSame( array( 2, 1 ), array( $transport->finds[0][4]['limit'], $transport->finds[1][4]['limit'] ) );
        // skip and limit, with a sort of its own and the option limit
        $transport->finds = array();
        expImportMongoDbSource::fetch( $this->options( array( 'paging' => 'skip', 'batch_size' => 2, 'sort' => '{"title": -1}', 'limit' => 4,
                                                              'read_preference' => 'secondaryPreferred' ) ), $target, 0 );
        $this->assertCount( 4, $this->snapshot( $target ) );
        $this->assertSame( array( null, 2 ), array( $transport->finds[0][4]['skip'] ?? null, $transport->finds[1][4]['skip'] ) );
        $this->assertSame( array( 'title' => -1 ), $transport->finds[0][4]['sort'] );
        $this->assertNull( $transport->finds[1][3], 'skip paging never filters by _id' );
        $this->assertSame( 'secondaryPreferred', $transport->finds[0][4]['readPreference'] );
        // paging by _id cannot follow another sort, nor read without _id
        foreach ( array( array( 'sort' => '{"title": 1}' ), array( 'projection' => '{"_id": 0, "title": 1}' ) ) as $change )
        {
            $result = expImportMongoDbSource::test( $this->options( $change ) );
            $this->assertFalse( $result['ok'] );
            $this->assertStringContainsString( '_id', $result['message'] );
        }
    }

    public function testTheTestConnectionAndAMaskedFailure(): void
    {
        putenv( 'SQ688_MONGO_PASSWORD=pw-of-the-mongo-test' );
        $transport = new MongoDbSourceTestTransport( $this->documents() );
        $this->useTransport( $transport );
        $options = $this->options( array( 'user' => 'reader', 'password' => 'env:SQ688_MONGO_PASSWORD', 'auth_source' => 'admin', 'tls' => true ) );
        $result = expImportMongoDbSource::test( $options );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( array( '_id', 'title', 'author.name', 'author.address.city', 'tags', 'published_at', 'price' ), $result['columns'] );
        $this->assertSame( 'mongodb', $result['driver'] );
        $this->assertSame( 1, $transport->finds[0][4]['limit'], 'only the first document is read' );
        // the secret by its name: the password reaches the driver as an option, never in the URI
        $this->assertSame( 'mongodb://203.0.113.7:27017/', $transport->opened[0] );
        $this->assertSame( 'reader', $transport->opened[1]['username'] );
        $this->assertSame( 'pw-of-the-mongo-test', $transport->opened[1]['password'] );
        $this->assertSame( 'admin', $transport->opened[1]['authSource'] );
        $this->assertTrue( $transport->opened[1]['tls'] );
        // a server error that quotes the password: masked
        $transport->fail = 'MongoDB: Authentication failed for reader with pw-of-the-mongo-test';
        $result = expImportMongoDbSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Authentication failed', $result['message'] );
        $this->assertStringNotContainsString( 'pw-of-the-mongo-test', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_MONGO_PASSWORD]', $result['message'] );
        // a secret that is not set: named, never guessed
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'SecretSettings', 'Secrets', array( 'cms_mongo' => 'from-the-settings' ) );
        $transport->fail = null;
        $this->assertTrue( expImportMongoDbSource::test( array_merge( $options, array( 'password' => 'cms_mongo' ) ) )['ok'] );
        $this->assertSame( 'from-the-settings', $transport->opened[1]['password'] );
        $result = expImportMongoDbSource::test( array_merge( $options, array( 'password' => 'not_set' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "the secret 'not_set' is not set", $result['message'] );
        // no transport and no extension: what the host needs
        expImportMongoDbSource::$transportFactory = null;
        expImportMongoDbSource::$environment = array( 'mongodb' => '' );
        $result = expImportMongoDbSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'no extension mongodb', $result['message'] );
        $this->assertStringContainsString( 'PECL extension mongodb', $result['message'] );
    }

    public function testTheDriverListAndTheWorkers(): void
    {
        expImportMongoDbSource::$environment = array( 'mongodb' => '' );
        $drivers = expImportMongoDbSource::drivers();
        $this->assertSame( array( 'mongodb' ), array_keys( $drivers ) );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['mongodb'] );
        $this->assertFalse( $drivers['mongodb']['available'] );
        $this->assertSame( '', $drivers['mongodb']['worker'], 'the workers have not run yet' );
        // a worker with the extension notes it
        expImportMongoDbSource::$environment = array( 'mongodb' => '2.3.3' );
        expImportSqlSource::recordWorkerPhp();
        $worker = expImportSqlSource::workerPhp();
        $this->assertSame( 'ext-mongodb 2.3.3', $worker['sources']['mongodb']['mongodb'] );
        expImportMongoDbSource::$environment = array( 'mongodb' => '' );
        $drivers = expImportMongoDbSource::drivers();
        $this->assertSame( array( false, 'yes' ), array( $drivers['mongodb']['available'], $drivers['mongodb']['worker'] ) );
        $this->assertStringContainsString( 'Runs still work', expImportMongoDbSource::missingDriverMessage() );
    }
}
