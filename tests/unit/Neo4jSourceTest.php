<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Neo4jSourceTestDoubles.php';

/**
 * The Neo4j and Memgraph source against scripted servers: the HTTP transactional endpoint and the Query API
 * (expImportPlatformHttp::$transport) and Bolt (expImportBoltClient::$connector with a server in memory). The Cypher
 * check, paging by SKIP and LIMIT and by PULL n, the mapping of nodes, relationships, paths, temporal values and
 * points, Test connection with masked errors, secrets by name, hosts, the lexer of the shared base and drivers().
 */
class Neo4jSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the HTTP requests: method, url, headers, body (decoded) */
    protected $requests = array();

    /** @var int the articles of the scripted HTTP server */
    protected $articles = 5;

    /** @var Neo4jSourceTestBoltServer */
    protected $bolt;

    /** @var array[] the arguments of the Bolt connections */
    protected $connections = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-neo4jtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'Neo4jSourceSettings', 'AllowedHosts', array( 'graph.example.invalid', 'memgraph.example.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_NEO4J_PASSWORD=pw-Graph-Secret-77' );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
        $this->bolt = new Neo4jSourceTestBoltServer();
        expImportBoltClient::$connector = function ( $host, $port, $tls, $timeout ) {
            $this->connections[] = array( $host, $port, $tls, $timeout );
            return $this->bolt;
        };
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportBoltClient::$connector = null;
        expImportNeo4jSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'Neo4jSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_NEO4J_PASSWORD' );
    }

    /** The scripted HTTP server: the transactional endpoint and the Query API over a list of article nodes. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $path = (string)parse_url( $url, PHP_URL_PATH );
        $json = function ( $status, $value ) { return array( 'status' => $status, 'headers' => array(), 'body' => json_encode( $value ) ); };
        if ( $path === '/db/neo4j/tx/commit' )
        {
            $statement = $data['statements'][0];
            if ( strpos( $statement['statement'], 'fail' ) !== false )
                return $json( 200, array( 'results' => array(), 'errors' => array( array( 'code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => 'Invalid input near pw-Graph-Secret-77' ) ) ) );
            $skip = isset( $statement['parameters']['sqliimport_skip'] ) ? $statement['parameters']['sqliimport_skip'] : 0;
            $limit = isset( $statement['parameters']['sqliimport_limit'] ) ? $statement['parameters']['sqliimport_limit'] : $this->articles;
            $rows = array();
            for ( $i = $skip + 1; $i <= min( $this->articles, $skip + $limit ); $i++ )
                $rows[] = array(
                    'row' => array( array( 'id' => 'a' . $i, 'title' => 'Article ' . $i ), array( 'since' => 2020 + $i ), array( 'type' => 'Point', 'coordinates' => array( 12.5, 41.9 ), 'crs' => array( 'srid' => 4326 ) ), '2026-03-0' . $i . 'T10:00:00Z' ),
                    'meta' => array( array( 'id' => $i, 'elementId' => '4:db:' . $i, 'type' => 'node', 'deleted' => false ),
                                     array( 'id' => 100 + $i, 'elementId' => '5:db:' . ( 100 + $i ), 'type' => 'relationship', 'deleted' => false ),
                                     array( 'type' => 'point' ), array( 'type' => 'datetime' ) ),
                    'graph' => array( 'nodes' => array( array( 'id' => (string)$i, 'elementId' => '4:db:' . $i, 'labels' => array( 'Article' ), 'properties' => array() ) ),
                                      'relationships' => array( array( 'id' => (string)( 100 + $i ), 'elementId' => '5:db:' . ( 100 + $i ), 'type' => 'WRITTEN_BY',
                                                                       'startNodeElementId' => '4:db:' . $i, 'endNodeElementId' => '4:db:900', 'properties' => array() ) ) ) );
            return $json( 200, array( 'results' => array( array( 'columns' => array( 'a', 'r', 'place', 'at' ), 'data' => $rows ) ), 'errors' => array() ) );
        }
        if ( $path === '/db/neo4j/query/v2' )
            return $json( 202, array( 'data' => array( 'fields' => array( 'n', 'count', 'p' ), 'values' => array( array(
                array( '$type' => 'Node', '_value' => array( '_element_id' => '4:db:7', '_labels' => array( 'Person' ),
                                                             '_properties' => array( 'name' => array( '$type' => 'String', '_value' => 'Tea desk' ),
                                                                                     'born' => array( '$type' => 'Date', '_value' => '1990-05-01' ) ) ) ),
                array( '$type' => 'Integer', '_value' => '42' ),
                array( '$type' => 'Point', '_value' => 'SRID=4326;POINT (13.4 52.5)' ) ) ) ), 'bookmarks' => array( 'b1' ) ) );
        return $json( 404, array( 'errors' => array( array( 'code' => 'Neo.ClientError.Request.Invalid', 'message' => 'no route ' . $path ) ) ) );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportNeo4jSource::schema(), $given + array( 'address' => 'https://graph.example.invalid:7473',
                                                                                                                     'query' => 'MATCH (a:Article)-[r:WRITTEN_BY]->(p) RETURN a, r, a.place AS place, a.at AS at ORDER BY a.id' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0, $key = '' ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportNeo4jSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportNeo4jSource( array( 'file' => $file, 'key' => $key ) ) )->records() as $record )
            $out[] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportNeo4jSource::schema() ) );
        $this->assertSame( 'nosql', expImportNeo4jSource::group() );
        $this->assertSame( array( 'remote' ), expImportNeo4jSource::origins() );
        foreach ( expImportNeo4jSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportNeo4jSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportNeo4jSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/neo4j-articles.ndjson', 'key' => 'id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'art-11', 'art-12', 'art-13' ), array_keys( $records ) );
        $this->assertSame( array( 'Article', 'Featured' ), $records['art-12']['_labels'] );
    }

    public function testOnlyReadingCypherPasses(): void
    {
        $ok = array(
            'MATCH (a:Article) RETURN a',
            "MATCH (a:Article) WHERE a.title = 'CREATE something; DELETE' RETURN a // DETACH DELETE a",
            'MATCH (n) WHERE n.set = 1 AND n.delete IS NULL RETURN n.create AS c /* SET n.x = 1 */',
            'MATCH p = (a)-[:LINKS*1..3]->(b) RETURN p, length(p), labels(a), toUpper(a.title)',
            'CALL db.labels() YIELD label RETURN label',
            'CALL { MATCH (a:Article) RETURN a } RETURN a',
            'USE news MATCH (a) RETURN a',
            'MATCH (a) RETURN apoc.text.join(a.tags, ",") AS tags',
            'SHOW INDEXES',
            "MATCH (a) WHERE a.x = \"it\\\"s CREATE\" RETURN a",
        );
        foreach ( $ok as $query )
            $this->assertNull( expImportNeo4jSource::checkCypher( $query ), $query );
        $refused = array(
            'CREATE (a:Article {title: "x"})',
            'MATCH (a) SET a.title = "x" RETURN a',
            'MATCH (a) DETACH DELETE a',
            'MATCH (a) REMOVE a.title RETURN a',
            'MERGE (a:Tag {name: "x"}) RETURN a',
            'LOAD CSV FROM "https://example.invalid/x.csv" AS line RETURN line',
            'MATCH (a) FOREACH (x IN [1] | SET a.x = x)',
            'MATCH (a) CALL { WITH a RETURN a.id AS id } IN TRANSACTIONS OF 10 ROWS RETURN id',
            'MATCH (a) CALL { WITH a RETURN 1 AS x } IN 4 CONCURRENT TRANSACTIONS RETURN x',
            'CALL dbms.security.createUser("x", "y", false)',
            'CALL db.createLabel("X")',
            'CALL db.index.fulltext.createNodeIndex("x", ["A"], ["b"])',
            'CALL apoc.create.node(["A"], {}) YIELD node RETURN node',
            'CALL `apoc`.`create`.node(["A"], {}) YIELD node RETURN node',
            'CALL apoc.periodic.iterate("MATCH (a) RETURN a", "SET a.x = 1", {})',
            'CALL apoc.cypher.runMany("CREATE (a)", {})',
            'CALL apoc.load.json("https://example.invalid/x.json") YIELD value RETURN value',
            'MATCH (a) RETURN apoc.do.when(true, "CREATE (x)", "", {}) AS x',
            'CALL gds.pageRank.write("g", {writeProperty: "rank"})',
            'CALL apoc.refactor.mergeNodes([], {})',
            'CALL apoc.export.json.all("x.json", {})',
            'CALL apoc.trigger.add("x", "RETURN 1", {})',
            'USE system SHOW USERS',
            'USE `system` MATCH (n) RETURN n',
            'MATCH (a) RETURN a; MATCH (b) DELETE b',
            'DROP INDEX x',
            "MATCH (a) WHERE a.x = 'unclosed RETURN a",
            "MATCH (a) /* SET a.x = 1",
            'CALL migrate.mysql("SELECT 1", {}) YIELD row RETURN row',
            'CALL mg.load("x")',
            '',
        );
        foreach ( $refused as $query )
            $this->assertNotNull( expImportNeo4jSource::checkCypher( $query ), $query );
        try
        {
            $this->fetched( $this->options( array( 'query' => 'MATCH (a) SET a.x = 1 RETURN a' ) ) );
            $this->fail( 'a write is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'SET', $e->getMessage() );
        }
        $this->assertSame( array(), $this->requests, 'a refused query sends nothing' );
    }

    public function testTheLexerOfTheSharedBase(): void
    {
        list( $stripped, $literals ) = expImportNeo4jSource::strip( "MATCH (a) WHERE a.t = 'x''s' AND `a``b` = 1 // c\nRETURN a", expImportNeo4jSource::LEXER );
        $this->assertStringNotContainsString( 'c', str_replace( array( 'MATCH', 'RETURN' ), '', $stripped ), 'the comment is gone' );
        $this->assertContains( 'a`b', $literals, 'a doubled backtick is one' );
        $this->assertSame( array( 'MATCH', 'A', 'WHERE', 'A', 'AND', 'RETURN', 'A' ), expImportNeo4jSource::words( $stripped ) );
        // ClickHouse heredocs and Snowflake $$ strings
        list( $stripped ) = expImportClickHouseSource::strip( "SELECT \$x\$'\$x\$, 1", array( 'quotes' => array( "'" => array( 'string', true, true ) ), 'dollar' => true ) );
        $this->assertSame( "SELECT \x000\x00, 1", $stripped, 'a quote inside a heredoc is part of it' );
        $this->assertTrue( expImportNeo4jSource::hasSecondStatement( 'MATCH (a) RETURN a; MATCH (b) RETURN b' ) );
        $this->assertFalse( expImportNeo4jSource::hasSecondStatement( "MATCH (a) RETURN a;\n" ) );
        $this->assertSame( 9223372036854775807, expImportNeo4jSource::integer( '9223372036854775807' ) );
        $this->assertSame( '9223372036854775808', expImportNeo4jSource::integer( '9223372036854775808' ), 'larger than PHP: text' );
    }

    public function testTransactionalEndpointPagesWithSkipAndLimit(): void
    {
        $records = $this->fetched( $this->options( array( 'page_size' => 2, 'user' => 'reader', 'password_secret' => 'env:SQ688_NEO4J_PASSWORD' ) ) );
        $this->assertCount( 5, $records );
        $this->assertCount( 3, $this->requests, 'pages of 2, 2 and 1' );
        $first = $this->requests[0];
        $this->assertSame( 'https://graph.example.invalid:7473/db/neo4j/tx/commit', $first['url'] );
        $this->assertContains( 'access-mode: READ', $first['headers'] );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:pw-Graph-Secret-77' ), $first['headers'] );
        $statement = $first['body']['statements'][0];
        $this->assertStringEndsWith( "ORDER BY a.id\nSKIP \$sqliimport_skip LIMIT \$sqliimport_limit", $statement['statement'] );
        $this->assertSame( array( 'sqliimport_skip' => 0, 'sqliimport_limit' => 2 ), $statement['parameters'] );
        $this->assertSame( 4, $this->requests[2]['body']['statements'][0]['parameters']['sqliimport_skip'] );
        $this->assertSame( array( 'row', 'graph' ), $statement['resultDataContents'] );
        // a node with its labels from the graph, a relationship with its type and ends, a point, a temporal value
        $row = $records[2];
        $this->assertSame( '4:db:3', $row['a._element_id'] );
        $this->assertSame( array( 'Article' ), $row['a._labels'] );
        $this->assertSame( 'Article 3', $row['a.title'] );
        $this->assertSame( 'WRITTEN_BY', $row['r._type'] );
        $this->assertSame( '4:db:3', $row['r._start'] );
        $this->assertSame( '4:db:900', $row['r._end'] );
        $this->assertSame( 2023, $row['r.since'] );
        $this->assertSame( '12.5,41.9', $row['place'] );
        $this->assertSame( '2026-03-03T10:00:00Z', $row['at'] );
        // the editor's sample stops at its limit; a query with its own LIMIT is not paged
        $this->requests = array();
        $this->assertCount( 3, $this->fetched( $this->options( array( 'page_size' => 2 ) ), 3 ) );
        $this->requests = array();
        $this->fetched( $this->options( array( 'query' => 'MATCH (a:Article) RETURN a LIMIT 10' ) ) );
        $this->assertCount( 1, $this->requests );
        $this->assertStringEndsWith( 'LIMIT 10', $this->requests[0]['body']['statements'][0]['statement'] );
        $this->assertFalse( expImportNeo4jSource::pageable( 'MATCH (a) RETURN a UNION MATCH (b) RETURN b AS a' ) );
        $this->assertFalse( expImportNeo4jSource::pageable( 'CALL db.labels()' ) );
        $this->assertTrue( expImportNeo4jSource::pageable( 'MATCH (a) WHERE a.x = "LIMIT 3" RETURN a' ) );
    }

    public function testQueryApiTypedValuesAndPaths(): void
    {
        $records = $this->fetched( $this->options( array( 'api' => 'query', 'paging' => 'none', 'query' => 'MATCH (n:Person) RETURN n, 42 AS count, n.home AS p' ) ) );
        $this->assertSame( 'https://graph.example.invalid:7473/db/neo4j/query/v2', $this->requests[0]['url'] );
        $this->assertSame( 'READ', $this->requests[0]['body']['accessMode'] );
        $this->assertContains( 'Accept: application/vnd.neo4j.query', $this->requests[0]['headers'] );
        $this->assertSame( array( 'n._element_id' => '4:db:7', 'n._labels' => array( 'Person' ), 'n.name' => 'Tea desk', 'n.born' => '1990-05-01',
                                  'count' => 42, 'p' => '13.4,52.5' ), $records[0] );
        $path = expImportNeo4jSource::typed( array( '$type' => 'Path', '_value' => array(
            array( '$type' => 'Node', '_value' => array( '_element_id' => 'n1', '_labels' => array( 'A' ), '_properties' => array() ) ),
            array( '$type' => 'Relationship', '_value' => array( '_element_id' => 'r1', '_type' => 'LINKS', '_start_node_element_id' => 'n1',
                                                                 '_end_node_element_id' => 'n2', '_properties' => array() ) ),
            array( '$type' => 'Node', '_value' => array( '_element_id' => 'n2', '_labels' => array( 'B' ), '_properties' => array() ) ) ) ) );
        $this->assertSame( array( 'n1', 'r1', 'n2' ), array_column( json_decode( $path, true ), '_element_id' ), 'a path is JSON' );
        $this->assertSame( 'base64:AAE=', expImportNeo4jSource::typed( array( '$type' => 'Base64', '_value' => 'AAE=' ) ) );
        // a path in the row format of the transactional endpoint
        $meta = array( array( 'id' => 1, 'type' => 'node' ), array( 'id' => 9, 'type' => 'relationship' ), array( 'id' => 2, 'type' => 'node' ) );
        $json = expImportNeo4jSource::txValue( array( array( 'x' => 1 ), array(), array( 'x' => 2 ) ), $meta, array(), array() );
        $this->assertSame( array( '1', '9', '2' ), array_column( json_decode( $json, true ), '_element_id' ) );
    }

    public function testBoltPagesWithPullAndMapsItsTypes(): void
    {
        $node = function ( $id, $title ) { return new expImportBoltStructure( 0x4E, array( $id, array( 'Article' ), array( 'title' => $title ), '4:db:' . $id ) ); };
        $this->bolt->fields = array( 'a', 'published', 'at', 'local', 'where', 'took', 'path' );
        $this->bolt->password = 'pw-Graph-Secret-77';
        $path = new expImportBoltStructure( 0x50, array( array( $node( 1, 'x' ), $node( 2, 'y' ) ),
                                                         array( new expImportBoltStructure( 0x72, array( 9, 'LINKS', array(), '5:db:9' ) ) ), array( 1, 1 ) ) );
        for ( $i = 1; $i <= 5; $i++ )
            $this->bolt->records[] = array( $node( $i, 'Article ' . $i ), new expImportBoltStructure( 0x44, array( 20514 ) ),
                                            new expImportBoltStructure( 0x46, array( 1772445600, 500000000, 3600 ) ),
                                            new expImportBoltStructure( 0x64, array( 1772445600, 0 ) ),
                                            new expImportBoltStructure( 0x58, array( 4326, 13.4, 52.5 ) ),
                                            new expImportBoltStructure( 0x45, array( 1, 2, 30, 0 ) ), $path );
        $options = $this->options( array( 'address' => 'bolt+s://graph.example.invalid', 'user' => 'reader', 'password_secret' => 'env:SQ688_NEO4J_PASSWORD',
                                          'database' => 'news', 'page_size' => 2, 'query' => 'MATCH (a:Article) RETURN a' ) );
        $records = $this->fetched( $options );
        $this->assertCount( 5, $records );
        $this->assertSame( array( 'graph.example.invalid', 7687, true, 30 ), $this->connections[0] );
        $this->assertSame( array(), $this->requests, 'Bolt sends no HTTP request' );
        $signatures = array_column( $this->bolt->messages, 0 );
        $this->assertSame( array( 0x01, 0x10, 0x3F, 0x3F, 0x3F, 0x02 ), $signatures, 'HELLO, RUN, three PULLs, GOODBYE' );
        $this->assertSame( array( 'mode' => 'r', 'db' => 'news' ), $this->bolt->messages[1][1][2] );
        $this->assertSame( array( 'n' => 2 ), $this->bolt->messages[2][1][0] );
        $this->assertSame( 'basic', $this->bolt->messages[0][1][0]['scheme'] );
        $row = $records[1];
        $this->assertSame( '4:db:2', $row['a._element_id'] );
        $this->assertSame( 'Article 2', $row['a.title'] );
        $this->assertSame( '2026-03-02', $row['published'] );
        $this->assertSame( '2026-03-02T10:00:00.5+01:00', $row['at'] );
        $this->assertSame( '2026-03-02T10:00:00', $row['local'] );
        $this->assertSame( '13.4,52.5', $row['where'] );
        $this->assertSame( 'P1M2DT30S', $row['took'] );
        $this->assertSame( array( '4:db:1', '5:db:9', '4:db:2' ), array_column( json_decode( $row['path'], true ), '_element_id' ) );
        $this->assertTrue( $this->bolt->closed );
        // a single node column is the record itself
        $this->bolt->fields = array( 'a' );
        $this->bolt->records = array( array( $node( 7, 'Seven' ) ) );
        $this->bolt->messages = array();
        $records = $this->fetched( $options );
        $this->assertSame( array( '_element_id' => '4:db:7', '_labels' => array( 'Article' ), 'title' => 'Seven' ), $records[0] );
        // PackStream round trip of the sizes and markers the client sends
        foreach ( array( null, true, false, 0, -16, -17, 127, 128, -129, 40000, -40000, 3000000000, 1.5, '', str_repeat( 'x', 300 ), array( 1, 2 ), array( 'k' => 'v' ) ) as $value )
        {
            $pos = 0;
            $this->assertSame( $value, expImportBoltClient::unpack( expImportBoltClient::pack( $value ), $pos ), json_encode( $value ) );
        }
    }

    public function testMemgraphAndAddresses(): void
    {
        $result = expImportNeo4jSource::test( $this->options( array( 'server' => 'memgraph', 'address' => 'https://memgraph.example.invalid:7444' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Bolt', $result['message'] );
        $this->bolt->fields = array( 'n' );
        $this->bolt->records = array( array( 1 ), array( 2 ) );
        $result = expImportNeo4jSource::test( $this->options( array( 'server' => 'memgraph', 'address' => 'bolt://memgraph.example.invalid', 'query' => 'MATCH (n) RETURN count(n) AS n' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( array( 'n' ), $result['columns'] );
        $this->assertStringContainsString( 'over Bolt 4.4', $result['driver'] );
        $this->assertSame( array( 0x01, 0x10, 0x3F, 0x2F, 0x02 ), array_column( $this->bolt->messages, 0 ), 'Test connection pulls one and discards the rest' );
        $this->assertSame( 'none', $this->bolt->messages[0][1][0]['scheme'] );
        foreach ( array( array( 'address' => 'https://10.1.2.3:7473' ), array( 'address' => 'bolt://127.0.0.1:7687' ), array( 'database' => 'system' ),
                         array( 'address' => 'bolt://graph.example.invalid', 'api' => 'tx' ) ) as $given )
        {
            $result = expImportNeo4jSource::test( $this->options( $given ) );
            $this->assertFalse( $result['ok'], json_encode( $given ) );
        }
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
        $this->assertCount( 1, $this->connections );
        list( $values, $errors ) = expImportOptionSchema::validate( expImportNeo4jSource::schema(), array( 'address' => 'bolt://reader:pw@graph.example.invalid', 'query' => 'RETURN 1' ) );
        $this->assertArrayHasKey( 'address', $errors, 'no credentials in the address' );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'Neo4jSourceSettings', 'AllowedHosts', array( '10.1.2.3' ) );
        $this->assertTrue( expImportNeo4jSource::test( $this->options( array( 'address' => 'https://10.1.2.3:7473' ) ) )['ok'], 'an allowed intranet server' );
    }

    public function testTestConnectionAndMaskedErrors(): void
    {
        $result = expImportNeo4jSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'env:SQ688_NEO4J_PASSWORD' ) ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertSame( 1, $result['rows'] );
        $this->assertContains( 'a.title', $result['columns'] );
        $this->assertStringEndsWith( 'SKIP 0 LIMIT 1', $this->requests[0]['body']['statements'][0]['statement'] );
        // an error that quotes the password: masked
        $result = expImportNeo4jSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'env:SQ688_NEO4J_PASSWORD',
                                                                     'query' => 'MATCH (a:fail) RETURN a' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'SyntaxError', $result['message'] );
        $this->assertStringNotContainsString( 'pw-Graph-Secret-77', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_NEO4J_PASSWORD]', $result['message'] );
        // over Bolt: a refused login, masked too
        $this->bolt->password = 'other';
        $result = expImportNeo4jSource::test( $this->options( array( 'address' => 'bolt://graph.example.invalid', 'user' => 'reader', 'password_secret' => 'env:SQ688_NEO4J_PASSWORD' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'Unauthorized', $result['message'] );
        // a secret that is not set is named, nothing is sent
        $this->requests = array();
        $result = expImportNeo4jSource::test( $this->options( array( 'user' => 'reader', 'password_secret' => 'sq688_no_such_secret' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( "'sq688_no_such_secret' is not set", $result['message'] );
        $this->assertSame( array(), $this->requests );
    }

    public function testDrivers(): void
    {
        expImportNeo4jSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => false, 'sockets' => true );
        $drivers = expImportNeo4jSource::drivers();
        $this->assertSame( array( 'http', 'https', 'bolt', 'bolt_tls' ), array_keys( $drivers ) );
        foreach ( $drivers as $driver )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $driver );
        $this->assertTrue( $drivers['bolt']['available'] );
        $this->assertFalse( $drivers['bolt_tls']['available'] );
        expImportSqlSource::recordWorkerPhp();
        $worker = expImportSqlSource::workerPhp();
        $this->assertArrayHasKey( 'neo4j.bolt', $worker['drivers'] );
        $this->assertArrayHasKey( 'clickhouse.https', $worker['drivers'] );
        $this->assertSame( 'no', expImportNeo4jSource::drivers()['bolt_tls']['worker'] );
    }
}
