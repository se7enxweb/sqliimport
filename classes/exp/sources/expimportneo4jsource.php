<?php
/**
 * File containing the expImportNeo4jSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a Cypher query against Neo4j or Memgraph. One record is one row: a node as its properties with
 * _labels and _element_id, a relationship as its properties with _type, _element_id, _start and _end, a path as JSON,
 * temporal values in ISO 8601, points as "x,y"; a row of several columns has them under the column names (a.title).
 *
 * Three ways to the server:
 *   - tx: Neo4j's HTTP transactional endpoint, POST /db/<database>/tx/commit with the header access-mode: READ
 *   - query: Neo4j's Query API (5.19 and later), POST /db/<database>/query/v2 with "accessMode": "READ"
 *   - bolt: the Bolt protocol (bolt://, bolt+s://, neo4j://, neo4j+s://), with the small client of its own
 *     (expImportBoltClient, Bolt 4.0 to 4.4): RUN in read mode and PULL page by page. Memgraph has no HTTP API and is
 *     read only this way.
 *
 * Only reading is possible: checkCypher() refuses, outside string literals and comments, every clause that writes
 * (CREATE, MERGE, SET, DELETE, DETACH, REMOVE, DROP, FOREACH, LOAD CSV, CALL { } IN TRANSACTIONS, the administration
 * commands), USE of the system database, and every procedure or function that writes, loads or runs code (dbms.*,
 * db.* outside a list of reading ones, apoc.* create/merge/load/periodic/cypher..., gds.* write/mutate/drop ...);
 * the server is told the transaction only reads besides (access-mode READ, mode "r"). The password is the name of a
 * secret (expImportSecrets).
 *
 * Paging: over HTTP the query gets SKIP $sqliimport_skip LIMIT $sqliimport_limit appended (when it ends in a RETURN
 * without its own SKIP or LIMIT and has no UNION; give it an ORDER BY for a stable order); over Bolt one RUN and a
 * PULL of n records per page.
 */
class expImportNeo4jSource extends expImportHttpQuerySource
{
    const NAME = 'Neo4j';

    const SETTINGS = 'Neo4jSourceSettings';

    const WORKER_PREFIX = 'neo4j';

    /** How Cypher quotes (strings with backslash escapes, names in backticks doubled) and comments. */
    const LEXER = array( 'quotes' => array( "'" => array( 'string', true, false ), '"' => array( 'string', true, false ), '`' => array( 'name', false, true ) ),
                         'line' => array( '//' ), 'block' => true );

    /** The clauses and commands that write or administer. */
    const FORBIDDEN = array(
        'CREATE', 'MERGE', 'SET', 'DELETE', 'DETACH', 'REMOVE', 'DROP', 'FOREACH', 'LOAD', 'ALTER', 'GRANT', 'DENY', 'REVOKE',
        'RENAME', 'START', 'STOP', 'TERMINATE', 'ENABLE', 'DEALLOCATE', 'IMPORT', 'ANALYZE', 'FREE', 'STORAGE', 'REPLICATION',
        'REGISTER', 'UNREGISTER', 'TRANSACTIONS',
    );

    /** The procedures of db.* that only read. */
    const DB_READ = array(
        'db.labels', 'db.relationshiptypes', 'db.propertykeys', 'db.indexes', 'db.indexdetails', 'db.constraints', 'db.info',
        'db.ping', 'db.schema.visualization', 'db.schema.nodetypeproperties', 'db.schema.reltypeproperties',
        'db.index.fulltext.querynodes', 'db.index.fulltext.queryrelationships', 'db.index.vector.querynodes',
        'db.index.vector.queryrelationships', 'db.nameFromElementId',
    );

    /** A segment of a procedure or function name that writes, loads or runs code (apoc.create.node, gds.pageRank.write). */
    const FORBIDDEN_SEGMENTS = array(
        'write', 'mutate', 'drop', 'periodic', 'trigger', 'refactor', 'cypher', 'do', 'lock', 'atomic', 'systemdb', 'custom',
        'uuid', 'ttl', 'migrate', 'merge', 'create', 'delete', 'remove', 'load', 'import', 'export', 'project', 'kafka', 'pulsar',
        'bolt', 'jdbc', 'es', 'mongo', 'mongodb', 'couchbase', 'redis', 'log', 'static', 'warmup', 'generate', 'config', 'set',
        'restore', 'backup', 'store', 'publish', 'json_util', 'import_util', 'export_util', 'graph_util', 'mgps', 'llm', 'llm_util',
        'nxalg', 'run', 'execute', 'exec', 'trigger', 'schema_assert', 'assert', 'clear',
    );

    /** The beginnings of a segment that writes (setProperty, createNode, deleteAll, loadJson, mergeNodes ...). */
    const FORBIDDEN_PREFIXES = array( 'set', 'create', 'delete', 'remove', 'merge', 'load', 'import', 'export', 'write', 'drop', 'run' );

    public static function identifier()
    {
        return 'neo4j';
    }

    public static function label()
    {
        return 'Neo4j or Memgraph graph (Cypher)';
    }

    public static function description()
    {
        return 'The rows of a Cypher query against Neo4j (HTTP or Bolt) or Memgraph (Bolt): a node as its properties with '
             . '_labels and _element_id, a relationship with _type, paths as JSON, temporal values in ISO 8601. Only reading '
             . 'clauses and procedures are accepted and the transaction is opened read-only; the password is the name of a '
             . 'secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three article nodes as the source writes it (one row per line)',
                   'file' => 'neo4j-articles.ndjson',
                   'options' => array( 'address' => 'https://graph.example.invalid:7473', 'query' => 'MATCH (a:Article) RETURN a ORDER BY a.id',
                                       'key' => 'id' ) ),
            array( 'label' => 'The articles with their authors from Neo4j over HTTP, user reader with the password as the secret graph_reader',
                   'options' => array( 'address' => 'https://graph.example.invalid:7473', 'database' => 'neo4j', 'user' => 'reader',
                                       'password_secret' => 'graph_reader',
                                       'query' => "MATCH (a:Article)-[:WRITTEN_BY]->(p:Person)\nWHERE a.status = \$status\nRETURN a.id AS id, a.title AS title, p.name AS author\nORDER BY a.id",
                                       'parameters' => '{"status": "published"}', 'key' => 'id' ) ),
            array( 'label' => 'The products of a Memgraph database over Bolt',
                   'options' => array( 'server' => 'memgraph', 'address' => 'bolt://memgraph.example.invalid:7687', 'user' => 'reader',
                                       'password_secret' => 'memgraph_reader', 'query' => 'MATCH (p:Product) RETURN p ORDER BY p.sku',
                                       'key' => 'sku' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'server' => array( 'type' => 'enum', 'label' => 'Server', 'default' => 'neo4j',
                               'values' => array( 'neo4j' => 'Neo4j', 'memgraph' => 'Memgraph (Bolt only)' ) ),
            'address' => array( 'type' => 'string', 'label' => 'Address', 'required' => true, 'max' => 500,
                                'pattern' => '#^(https?|bolt|bolt\+s|neo4j|neo4j\+s)://[^\s/?\#@]+/?$#i',
                                'help' => 'https://graph.example.org:7473 (HTTP) or bolt://graph.example.org:7687, bolt+s://, neo4j://, neo4j+s:// (Bolt); a private network only with [Neo4jSourceSettings] AllowedHosts[]' ),
            'api' => array( 'type' => 'enum', 'label' => 'Interface', 'default' => 'auto',
                            'values' => array( 'auto' => 'By the address (http: transactional endpoint, bolt: Bolt)',
                                               'tx' => 'HTTP transactional endpoint (/db/<database>/tx/commit)',
                                               'query' => 'Query API (/db/<database>/query/v2, Neo4j 5.19 and later)',
                                               'bolt' => 'Bolt' ) ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'max' => 63, 'pattern' => '/^([A-Za-z0-9][A-Za-z0-9._-]*)?$/',
                                 'help' => 'The database; empty: neo4j over HTTP, the default database over Bolt. Never system' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'Empty: no authentication' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'query' => array( 'type' => 'text', 'label' => 'Cypher query', 'required' => true,
                              'help' => 'A reading query: MATCH (a:Article) RETURN a ORDER BY a.id. Clauses and procedures that write are refused' ),
            'parameters' => array( 'type' => 'json', 'label' => 'Parameters', 'help' => 'A JSON object of the $parameters of the query' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 1000, 'min' => 1, 'max' => 100000,
                                  'help' => 'Rows per request (HTTP: LIMIT, Bolt: PULL n)' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'auto',
                               'values' => array( 'auto' => 'Automatic (SKIP and LIMIT over HTTP when the query allows it)',
                                                  'none' => 'None (one request, the query as it is)' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'help' => 'The field that names a row (id, _element_id)' ),
        );
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether a Cypher query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkCypher( $query )
    {
        try
        {
            list( $stripped, $literals ) = self::strip( $query, self::LEXER );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( trim( $stripped ) === '' )
            return 'the query is empty';
        if ( self::hasSecondStatement( $stripped ) )
            return 'only one statement is allowed';
        $word = self::forbiddenWord( $stripped, self::FORBIDDEN );
        if ( $word !== null )
            return "the query contains $word: only reading clauses are allowed";
        // a quoted name as the code sees it (`apoc`.`create`.node)
        $code = preg_replace_callback( '/\x01(\d+)\x01/', function ( $m ) use ( $literals ) {
            $name = isset( $literals[(int)$m[1]] ) ? $literals[(int)$m[1]] : '';
            return preg_match( '/^[A-Za-z0-9_.]+$/', $name ) ? $name : 'quoted';
        }, $stripped );
        // USE only with a plain database name, never the system database (nor graph.byName(...))
        preg_match_all( '/(?<![\w.$])USE\s+([^\s]*)/i', $code, $uses );
        foreach ( $uses[1] as $target )
        {
            if ( !preg_match( '/^[A-Za-z][A-Za-z0-9_.-]*$/', $target ) || preg_match( '/\(/', $target ) )
                return 'USE is allowed with the name of a database only';
            if ( strtolower( $target ) === 'system' || strpos( strtolower( $target ), 'system.' ) === 0 )
                return 'the system database is not read';
        }
        preg_match_all( '/(?<![\w.$])([A-Za-z_][A-Za-z0-9_]*(?:\s*\.\s*[A-Za-z_][A-Za-z0-9_]*)*)\s*\(/', $code, $calls );
        foreach ( $calls[1] as $call )
        {
            $name = strtolower( (string)preg_replace( '/\s+/', '', $call ) );
            $refusal = self::checkRoutine( $name );
            if ( $refusal !== null )
                return $refusal;
        }
        return null;
    }

    /**
     * Whether a procedure or function may be called.
     *
     * @param string $name lower case, dotted
     * @return string|null the reason it is refused
     */
    public static function checkRoutine( $name )
    {
        if ( strpos( $name, '.' ) === false )
            return null;
        if ( strpos( $name, 'dbms.' ) === 0 )
            return "the procedure $name is not allowed (dbms.* administers the server)";
        if ( strpos( $name, 'db.' ) === 0 )
            return in_array( $name, array_map( 'strtolower', self::DB_READ ), true ) ? null : "the procedure $name is not one of the reading db.* procedures";
        $segments = explode( '.', $name );
        foreach ( $segments as $segment )
        {
            if ( in_array( $segment, self::FORBIDDEN_SEGMENTS, true ) )
                return "the procedure or function $name is not allowed: it writes, loads or runs code";
            foreach ( self::FORBIDDEN_PREFIXES as $prefix )
                if ( strpos( $segment, $prefix ) === 0 )
                    return "the procedure or function $name is not allowed: it writes, loads or runs code";
        }
        return null;
    }

    /**
     * Whether a checked query can be paged with SKIP and LIMIT appended: it ends in a RETURN without its own SKIP or
     * LIMIT and has no UNION.
     *
     * @param string $query
     * @return bool
     */
    public static function pageable( $query )
    {
        list( $stripped ) = self::strip( $query, self::LEXER );
        $stripped = rtrim( $stripped, " \t\r\n;" );
        $words = self::words( $stripped );
        if ( in_array( 'UNION', $words, true ) || !in_array( 'RETURN', $words, true ) )
            return false;
        $last = array_search( 'RETURN', array_reverse( $words, true ), true );
        $tail = array_slice( $words, (int)$last );
        return !in_array( 'SKIP', $tail, true ) && !in_array( 'LIMIT', $tail, true ) && !in_array( 'OFFSET', $tail, true )
            && !preg_match( '/}\s*$/', $stripped );
    }

    /**
     * The query and its parameters of the options, after the check.
     *
     * @param array $options
     * @return array the list ( string query, array parameters )
     * @throws expImportSourceException
     */
    public static function queryOf( array $options )
    {
        $query = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $refusal = self::checkCypher( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( self::NAME . ': ' . $refusal );
        $parameters = isset( $options['parameters'] ) ? $options['parameters'] : array();
        if ( is_string( $parameters ) )
            $parameters = trim( $parameters ) === '' ? array() : json_decode( $parameters, true );
        if ( !is_array( $parameters ) || ( $parameters && array_values( $parameters ) === $parameters ) )
            throw new expImportSourceException( self::NAME . ': the parameters must be a JSON object' );
        return array( rtrim( $query, " \t\r\n;" ), $parameters );
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The interface, address and database of the options.
     *
     * @param array $options
     * @return array the list ( string api: tx, query or bolt, array address parts: scheme, host, port, tls, base,
     *               string database, string[] trusted hosts )
     * @throws expImportSourceException
     */
    public static function connection( array $options )
    {
        $address = rtrim( trim( isset( $options['address'] ) ? (string)$options['address'] : '' ), '/' );
        $parts = parse_url( $address );
        if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) )
            throw new expImportSourceException( self::NAME . ': the address must be http(s)://host:port or bolt://host:port' );
        $scheme = strtolower( $parts['scheme'] );
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['path'] ) && $parts['path'] !== '' && $parts['path'] !== '/' ) )
            throw new expImportSourceException( self::NAME . ': the address may have no path, query or credentials (give the user and the password secret)' );
        $http = in_array( $scheme, array( 'http', 'https' ), true );
        if ( !$http && !in_array( $scheme, array( 'bolt', 'bolt+s', 'neo4j', 'neo4j+s' ), true ) )
            throw new expImportSourceException( self::NAME . ": the scheme $scheme is not supported (http, https, bolt, bolt+s, neo4j, neo4j+s; for a self-signed certificate install it as a trusted CA)" );
        $api = isset( $options['api'] ) ? (string)$options['api'] : 'auto';
        if ( $api === 'auto' )
            $api = $http ? 'tx' : 'bolt';
        if ( $api === 'bolt' && $http || $api !== 'bolt' && !$http )
            throw new expImportSourceException( self::NAME . ': the interface ' . $api . ' does not go with a ' . $scheme . ':// address' );
        $memgraph = isset( $options['server'] ) && $options['server'] === 'memgraph';
        if ( $memgraph && $api !== 'bolt' )
            throw new expImportSourceException( self::NAME . ': Memgraph has no HTTP API; give its Bolt address (bolt://host:7687)' );
        $database = trim( isset( $options['database'] ) ? (string)$options['database'] : '' );
        if ( $database !== '' && !preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $database ) )
            throw new expImportSourceException( self::NAME . ': the database must be a name (letters, digits, . _ -)' );
        if ( strtolower( $database ) === 'system' )
            throw new expImportSourceException( self::NAME . ': the system database is not read' );
        if ( $database === '' && $api !== 'bolt' )
            $database = 'neo4j';
        $trusted = self::checkHost( $parts['host'] );
        $tls = in_array( $scheme, array( 'https', 'bolt+s', 'neo4j+s' ), true );
        $port = isset( $parts['port'] ) ? (int)$parts['port'] : ( $http ? ( $tls ? 7473 : 7474 ) : 7687 );
        $base = $scheme . '://' . $parts['host'] . ':' . $port;
        return array( $api, array( 'scheme' => $scheme, 'host' => trim( $parts['host'], '[]' ), 'port' => $port, 'tls' => $tls, 'base' => $base ),
                      $database, $trusted );
    }

    /**
     * The Authorization header of HTTP (basic, with the password secret), or none without a user.
     *
     * @param array $options
     * @param string[] $used
     * @return array
     * @throws expImportSourceException
     */
    protected static function credentials( array $options, array &$used )
    {
        $user = isset( $options['user'] ) ? trim( (string)$options['user'] ) : '';
        if ( $user === '' )
            return array( '', '' );
        $ref = isset( $options['password_secret'] ) ? trim( (string)$options['password_secret'] ) : '';
        $used[] = $ref;
        return array( $user, expImportSecrets::required( $ref, self::NAME . ': the password' ) );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    public static function rows( array $options, $limit, array &$used )
    {
        list( $api, $address, $database, $trusted ) = self::connection( $options );
        list( $query, $parameters ) = self::queryOf( $options );
        list( $user, $password ) = self::credentials( $options, $used );
        $size = max( 1, min( 100000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 1000 ) );
        if ( $limit > 0 )
            $size = min( $size, $limit );
        if ( $api === 'bolt' )
        {
            foreach ( self::boltRows( $address, $database, $user, $password, $query, $parameters, $size, $limit ) as $record )
                yield $record;
            return;
        }
        $page = ( !isset( $options['paging'] ) || $options['paging'] !== 'none' ) && self::pageable( $query );
        $n = 0;
        for ( $p = 0; $p < self::maxPages(); $p++ )
        {
            $q = $query;
            $params = $parameters;
            if ( $page )
            {
                $q .= "\nSKIP \$sqliimport_skip LIMIT \$sqliimport_limit";
                $params['sqliimport_skip'] = $p * $size;
                $params['sqliimport_limit'] = $size;
            }
            list( , $rows ) = self::httpQuery( $api, $address['base'], $database, $user, $password, $q, $params, $trusted );
            foreach ( $rows as $row )
            {
                yield $row;
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( !$page || count( $rows ) < $size )
                return;
        }
    }

    /**
     * One query over HTTP.
     *
     * @return array the list ( string[] columns, array[] records )
     * @throws expImportSourceException
     */
    protected static function httpQuery( $api, $base, $database, $user, $password, $query, array $parameters, array $trusted )
    {
        $headers = array();
        if ( $user !== '' )
            $headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $password );
        $params = $parameters ? $parameters : new stdClass();
        if ( $api === 'query' )
        {
            $headers['Accept'] = 'application/vnd.neo4j.query';
            $data = self::json( 'POST', $base . '/db/' . rawurlencode( $database ) . '/query/v2', $headers,
                                array( 'statement' => $query, 'parameters' => $params, 'accessMode' => 'READ' ), $trusted );
            if ( !empty( $data['errors'] ) )
                throw new expImportSourceException( self::NAME . ': ' . self::errorOf( $data['errors'] ) );
            $fields = isset( $data['data']['fields'] ) ? array_map( 'strval', (array)$data['data']['fields'] ) : array();
            $rows = array();
            foreach ( isset( $data['data']['values'] ) ? (array)$data['data']['values'] : array() as $values )
                $rows[] = self::record( $fields, array_map( array( 'expImportNeo4jSource', 'typed' ), (array)$values ) );
            return array( $fields, $rows );
        }
        $headers['access-mode'] = 'READ';
        $headers['Accept'] = 'application/json;charset=UTF-8';
        $data = self::json( 'POST', $base . '/db/' . rawurlencode( $database ) . '/tx/commit', $headers,
                            array( 'statements' => array( array( 'statement' => $query, 'parameters' => $params,
                                                                 'resultDataContents' => array( 'row', 'graph' ) ) ) ), $trusted );
        if ( !empty( $data['errors'] ) )
            throw new expImportSourceException( self::NAME . ': ' . self::errorOf( $data['errors'] ) );
        $result = isset( $data['results'][0] ) && is_array( $data['results'][0] ) ? $data['results'][0] : array();
        $fields = isset( $result['columns'] ) ? array_map( 'strval', (array)$result['columns'] ) : array();
        $rows = array();
        foreach ( isset( $result['data'] ) ? (array)$result['data'] : array() as $entry )
        {
            $graph = isset( $entry['graph'] ) && is_array( $entry['graph'] ) ? $entry['graph'] : array();
            $nodes = array();
            foreach ( isset( $graph['nodes'] ) ? (array)$graph['nodes'] : array() as $node )
                $nodes[self::idOf( $node )] = $node;
            $rels = array();
            foreach ( isset( $graph['relationships'] ) ? (array)$graph['relationships'] : array() as $rel )
                $rels[self::idOf( $rel )] = $rel;
            $row = isset( $entry['row'] ) ? (array)$entry['row'] : array();
            $meta = isset( $entry['meta'] ) ? (array)$entry['meta'] : array();
            $values = array();
            foreach ( $fields as $i => $field )
                $values[] = self::txValue( isset( $row[$i] ) ? $row[$i] : null, isset( $meta[$i] ) ? $meta[$i] : null, $nodes, $rels );
            $rows[] = self::record( $fields, $values );
        }
        return array( $fields, $rows );
    }

    /** @return string the first error of an answer: code: message */
    protected static function errorOf( $errors )
    {
        $error = is_array( $errors ) && isset( $errors[0] ) && is_array( $errors[0] ) ? $errors[0] : array();
        return trim( ( isset( $error['code'] ) ? $error['code'] . ': ' : '' ) . ( isset( $error['message'] ) ? (string)$error['message'] : 'the query failed' ) );
    }

    /** @return string the element ID of a node or relationship of the graph format (the numeric ID before Neo4j 5) */
    protected static function idOf( $item )
    {
        if ( is_array( $item ) && isset( $item['elementId'] ) )
            return (string)$item['elementId'];
        return is_array( $item ) && isset( $item['id'] ) ? (string)$item['id'] : '';
    }

    /**
     * A value of the transactional endpoint's row format with its meta: nodes and relationships get their labels or
     * type from the graph format.
     *
     * @param mixed $row
     * @param mixed $meta
     * @param array $nodes element ID => node of the graph format
     * @param array $rels element ID => relationship of the graph format
     * @return mixed
     */
    public static function txValue( $row, $meta, array $nodes, array $rels )
    {
        if ( is_array( $meta ) && isset( $meta['type'] ) && is_string( $meta['type'] ) )
        {
            $id = self::idOf( $meta );
            $props = is_array( $row ) ? $row : array();
            switch ( $meta['type'] )
            {
                case 'node':
                    return array( '_element_id' => $id, '_labels' => isset( $nodes[$id]['labels'] ) ? array_values( (array)$nodes[$id]['labels'] ) : array() ) + $props;
                case 'relationship':
                    $rel = isset( $rels[$id] ) ? $rels[$id] : array();
                    $start = isset( $rel['startNodeElementId'] ) ? $rel['startNodeElementId'] : ( isset( $rel['startNode'] ) ? $rel['startNode'] : '' );
                    $end = isset( $rel['endNodeElementId'] ) ? $rel['endNodeElementId'] : ( isset( $rel['endNode'] ) ? $rel['endNode'] : '' );
                    return array( '_element_id' => $id, '_type' => isset( $rel['type'] ) ? (string)$rel['type'] : '', '_start' => (string)$start, '_end' => (string)$end ) + $props;
                case 'point':
                    return is_array( $row ) && isset( $row['coordinates'] ) ? implode( ',', (array)$row['coordinates'] ) : $row;
            }
            return $row;
        }
        if ( is_array( $meta ) && is_array( $row ) && array_values( $meta ) === $meta && array_values( $row ) === $row && count( $meta ) === count( $row ) && $meta )
        {
            $out = array();
            foreach ( $row as $i => $item )
                $out[] = self::txValue( $item, $meta[$i], $nodes, $rels );
            return self::isPath( $meta ) ? self::pathJson( $out ) : $out;
        }
        if ( is_array( $row ) && array_values( $row ) !== $row && is_array( $meta ) )
        {
            foreach ( $row as $key => $item )
                $row[$key] = self::txValue( $item, isset( $meta[$key] ) ? $meta[$key] : null, $nodes, $rels );
        }
        return $row;
    }

    /** @return bool whether a list of metas is a path: node, relationship, node ... */
    protected static function isPath( array $meta )
    {
        if ( count( $meta ) < 3 || count( $meta ) % 2 === 0 )
            return false;
        foreach ( $meta as $i => $item )
            if ( !is_array( $item ) || !isset( $item['type'] ) || $item['type'] !== ( $i % 2 === 0 ? 'node' : 'relationship' ) )
                return false;
        return true;
    }

    /** @return string a path (its nodes and relationships in order) as JSON */
    protected static function pathJson( array $elements )
    {
        return (string)json_encode( array_values( $elements ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }

    /**
     * A value of the Query API's typed JSON ({"$type": "Integer", "_value": "42"}) as a plain value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function typed( $value )
    {
        if ( !is_array( $value ) || !isset( $value['$type'] ) || !array_key_exists( '_value', $value ) )
            return $value;
        $v = $value['_value'];
        switch ( (string)$value['$type'] )
        {
            case 'Null':
                return null;
            case 'Boolean':
                return (bool)$v;
            case 'Integer':
                return self::integer( (string)$v );
            case 'Float':
                return is_numeric( $v ) ? (float)$v : (string)$v;
            case 'String':
                return (string)$v;
            case 'Base64':
                return 'base64:' . (string)$v;
            case 'List':
                return array_map( array( 'expImportNeo4jSource', 'typed' ), (array)$v );
            case 'Map':
                return array_map( array( 'expImportNeo4jSource', 'typed' ), (array)$v );
            case 'Node':
                return array( '_element_id' => isset( $v['_element_id'] ) ? (string)$v['_element_id'] : '',
                              '_labels' => isset( $v['_labels'] ) ? array_values( (array)$v['_labels'] ) : array() )
                     + array_map( array( 'expImportNeo4jSource', 'typed' ), isset( $v['_properties'] ) ? (array)$v['_properties'] : array() );
            case 'Relationship':
                return array( '_element_id' => isset( $v['_element_id'] ) ? (string)$v['_element_id'] : '',
                              '_type' => isset( $v['_type'] ) ? (string)$v['_type'] : '',
                              '_start' => isset( $v['_start_node_element_id'] ) ? (string)$v['_start_node_element_id'] : '',
                              '_end' => isset( $v['_end_node_element_id'] ) ? (string)$v['_end_node_element_id'] : '' )
                     + array_map( array( 'expImportNeo4jSource', 'typed' ), isset( $v['_properties'] ) ? (array)$v['_properties'] : array() );
            case 'Path':
                return self::pathJson( array_map( array( 'expImportNeo4jSource', 'typed' ), (array)$v ) );
            case 'Point':
                // SRID=4326;POINT (12.5 41.9) or POINT Z (1 2 3)
                if ( preg_match( '/POINT\s*Z?\s*\(([^)]*)\)/i', (string)$v, $m ) )
                    return implode( ',', preg_split( '/\s+/', trim( $m[1] ) ) );
                return (string)$v;
        }
        // Date, Time, LocalTime, LocalDateTime, OffsetDateTime, ZonedDateTime, Duration: ISO 8601 already
        return is_array( $v ) ? $v : (string)$v;
    }

    /**
     * A row as a record: a single column holding a node or relationship is the record itself; else every column
     * under its name, nested values flattened (a.title).
     *
     * @param string[] $fields
     * @param array $values
     * @return array
     */
    public static function record( array $fields, array $values )
    {
        if ( count( $fields ) === 1 && isset( $values[0] ) && is_array( $values[0] ) && array_key_exists( '_element_id', $values[0] ) )
            return self::flatten( $values[0] );
        $row = array();
        foreach ( $fields as $i => $field )
            $row[$field] = array_key_exists( $i, $values ) ? $values[$i] : null;
        return self::flatten( $row );
    }

    /**
     * The rows over Bolt: one RUN in read mode, then PULL n per page.
     *
     * @return Generator array
     * @throws expImportSourceException
     */
    protected static function boltRows( array $address, $database, $user, $password, $query, array $parameters, $size, $limit )
    {
        $client = self::bolt( $address, $user, $password );
        try
        {
            $fields = $client->run( $query, $parameters, $database );
            $n = 0;
            for ( $p = 0; $p < self::maxPages(); $p++ )
            {
                list( $records, $more ) = $client->pull( $size );
                foreach ( $records as $values )
                {
                    yield self::record( $fields, array_map( array( 'expImportNeo4jSource', 'boltValue' ), $values ) );
                    if ( $limit > 0 && ++$n >= $limit )
                        return;
                }
                if ( !$more )
                    return;
            }
        }
        finally
        {
            $client->close();
        }
    }

    /** @return expImportBoltClient a connection, authenticated */
    protected static function bolt( array $address, $user, $password )
    {
        $timeout = max( 1, (int)self::sourceSetting( 'Timeout', 30 ) );
        $max = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxResponseMegabytes', 20 ) ) * 1048576;
        return expImportBoltClient::open( $address['host'], $address['port'], $address['tls'], $timeout, $user, $password, $max );
    }

    /**
     * A value of Bolt as a plain value: nodes, relationships, paths, temporal values and points mapped.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function boltValue( $value )
    {
        if ( is_array( $value ) )
            return array_map( array( 'expImportNeo4jSource', 'boltValue' ), $value );
        if ( !$value instanceof expImportBoltStructure )
            return $value;
        $f = $value->fields;
        switch ( $value->signature )
        {
            case 0x4E: // Node: id, labels, properties (, element_id)
                return array( '_element_id' => isset( $f[3] ) ? (string)$f[3] : (string)$f[0], '_labels' => array_values( (array)$f[1] ) )
                     + self::boltValue( (array)$f[2] );
            case 0x52: // Relationship: id, start, end, type, properties (, element_id, start_element_id, end_element_id)
                return array( '_element_id' => isset( $f[5] ) ? (string)$f[5] : (string)$f[0], '_type' => (string)$f[3],
                              '_start' => isset( $f[6] ) ? (string)$f[6] : (string)$f[1], '_end' => isset( $f[7] ) ? (string)$f[7] : (string)$f[2] )
                     + self::boltValue( (array)$f[4] );
            case 0x72: // UnboundRelationship: id, type, properties (, element_id)
                return array( '_element_id' => isset( $f[3] ) ? (string)$f[3] : (string)$f[0], '_type' => (string)$f[1] ) + self::boltValue( (array)$f[2] );
            case 0x50: // Path: nodes, relationships, indices
                $nodes = array_map( array( 'expImportNeo4jSource', 'boltValue' ), (array)$f[0] );
                $rels = array_map( array( 'expImportNeo4jSource', 'boltValue' ), (array)$f[1] );
                $out = $nodes ? array( $nodes[0] ) : array();
                $indices = array_values( (array)$f[2] );
                for ( $i = 0; $i + 1 < count( $indices ); $i += 2 )
                {
                    $r = (int)$indices[$i];
                    $rel = $r > 0 ? ( isset( $rels[$r - 1] ) ? $rels[$r - 1] : null ) : ( isset( $rels[-$r - 1] ) ? $rels[-$r - 1] : null );
                    $out[] = $rel;
                    $out[] = isset( $nodes[(int)$indices[$i + 1]] ) ? $nodes[(int)$indices[$i + 1]] : null;
                }
                return self::pathJson( $out );
            case 0x44: // Date: days since 1970-01-01
                return gmdate( 'Y-m-d', (int)$f[0] * 86400 );
            case 0x54: // Time: nanoseconds since midnight, offset seconds
                return self::clock( (int)$f[0] ) . self::offset( (int)$f[1] );
            case 0x74: // LocalTime
                return self::clock( (int)$f[0] );
            case 0x46: // DateTime (Bolt 4): local seconds, nanoseconds, offset seconds
                return gmdate( 'Y-m-d\TH:i:s', (int)$f[0] ) . self::fraction( (int)$f[1] ) . self::offset( (int)$f[2] );
            case 0x49: // DateTime (UTC seconds)
                return gmdate( 'Y-m-d\TH:i:s', (int)$f[0] + (int)$f[2] ) . self::fraction( (int)$f[1] ) . self::offset( (int)$f[2] );
            case 0x66: // DateTimeZoneId (Bolt 4): local seconds, nanoseconds, zone
            case 0x69: // DateTimeZoneId (UTC seconds)
                try
                {
                    $zone = new DateTimeZone( (string)$f[2] );
                    $utc = (int)$f[0];
                    if ( $value->signature === 0x66 )
                        $utc -= $zone->getOffset( new DateTime( '@' . (int)$f[0] ) );
                    $offset = $zone->getOffset( new DateTime( '@' . $utc ) );
                }
                catch ( Exception $e )
                {
                    $offset = 0;
                    $utc = (int)$f[0];
                }
                return gmdate( 'Y-m-d\TH:i:s', $utc + $offset ) . self::fraction( (int)$f[1] ) . self::offset( $offset ) . '[' . (string)$f[2] . ']';
            case 0x64: // LocalDateTime: seconds, nanoseconds
                return gmdate( 'Y-m-d\TH:i:s', (int)$f[0] ) . self::fraction( (int)$f[1] );
            case 0x45: // Duration: months, days, seconds, nanoseconds
                return 'P' . (int)$f[0] . 'M' . (int)$f[1] . 'DT' . (int)$f[2] . rtrim( rtrim( '.' . sprintf( '%09d', abs( (int)$f[3] ) ), '0' ), '.' ) . 'S';
            case 0x58: // Point2D: srid, x, y
                return $f[1] . ',' . $f[2];
            case 0x59: // Point3D: srid, x, y, z
                return $f[1] . ',' . $f[2] . ',' . $f[3];
        }
        return array( '_structure' => sprintf( '0x%02X', $value->signature ), '_fields' => self::boltValue( $f ) );
    }

    /** @return string HH:MM:SS(.fraction) of nanoseconds since midnight */
    protected static function clock( $nanos )
    {
        $seconds = intdiv( $nanos, 1000000000 );
        return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 ) . self::fraction( $nanos % 1000000000 );
    }

    /** @return string .123 of nanoseconds, '' for none */
    protected static function fraction( $nanos )
    {
        return $nanos ? rtrim( '.' . sprintf( '%09d', $nanos ), '0' ) : '';
    }

    /** @return string Z or +01:00 */
    protected static function offset( $seconds )
    {
        if ( $seconds === 0 )
            return 'Z';
        $abs = abs( $seconds );
        return ( $seconds < 0 ? '-' : '+' ) . sprintf( '%02d:%02d', intdiv( $abs, 3600 ), intdiv( $abs % 3600, 60 ) );
    }

    /**
     * The editor's "Test connection": the query for its first row, whose fields are reported, or why it failed (never
     * a password: the message is masked). Nothing is stored.
     *
     * @param array $options
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = isset( $options['server'] ) && $options['server'] === 'memgraph' ? 'Memgraph' : 'Neo4j';
        try
        {
            list( $api, $address, $database, $trusted ) = self::connection( $options );
            list( $query, $parameters ) = self::queryOf( $options );
            list( $user, $password ) = self::credentials( $options, $used );
            $rows = array();
            if ( $api === 'bolt' )
            {
                $client = self::bolt( $address, $user, $password );
                try
                {
                    $driver = ( $client->server() !== '' ? $client->server() : $driver ) . ' over Bolt ' . $client->version();
                    $fields = $client->run( $query, $parameters, $database );
                    list( $records, $more ) = $client->pull( 1 );
                    if ( $more )
                        $client->discard();
                    foreach ( $records as $values )
                        $rows[] = self::record( $fields, array_map( array( 'expImportNeo4jSource', 'boltValue' ), $values ) );
                }
                finally
                {
                    $client->close();
                }
            }
            else
            {
                $driver .= $api === 'query' ? ' Query API' : ' HTTP transactional endpoint';
                if ( self::pageable( $query ) )
                {
                    $query .= "\nSKIP 0 LIMIT 1";
                }
                list( , $rows ) = self::httpQuery( $api, $address['base'], $database, $user, $password, $query, $parameters, $trusted );
                $rows = array_slice( $rows, 0, 1 );
            }
            $columns = $rows ? array_map( 'strval', array_keys( $rows[0] ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $rows ), 'driver' => $driver,
                          'message' => 'connected to ' . $driver . ( $rows ? '; the query gives these fields' : '; the query finds no row' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /**
     * http and https (the HTTP interfaces) and bolt (the client of its own over a socket; TLS with openssl).
     *
     * @return array
     */
    public static function localDrivers()
    {
        $env = is_array( self::$environment ) ? self::$environment : array();
        $socket = isset( $env['sockets'] ) ? (bool)$env['sockets'] : function_exists( 'stream_socket_client' );
        $openssl = isset( $env['openssl'] ) ? (bool)$env['openssl'] : extension_loaded( 'openssl' );
        return parent::localDrivers() + array(
            'bolt' => array( 'label' => 'bolt:// and neo4j:// (Neo4j, Memgraph; built-in Bolt 4.x client)', 'via' => $socket ? 'stream_socket_client' : '',
                             'needs' => 'stream_socket_client (part of PHP)' ),
            'bolt_tls' => array( 'label' => 'bolt+s:// and neo4j+s:// (certificates verified)', 'via' => $socket && $openssl ? 'stream_socket_client with openssl' : '',
                                 'needs' => 'the PHP extension openssl' ),
        );
    }
}
