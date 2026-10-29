<?php
/**
 * File containing the expImportCosmosDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The items of an Azure Cosmos DB container (the API for NoSQL, formerly the SQL API) that a query selects, read
 * through the REST API. One record is one item, nested objects flattened to dotted names (author.name) as the
 * MongoDB source does. A Cosmos DB account of the API for MongoDB is read with the MongoDB source instead (its
 * connection string), one of the API for Apache Cassandra, Gremlin or Table is not read.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the query with the
 * continuation token of each answer (x-ms-continuation) and writes the items into the run's snapshot, one JSON
 * object per line.
 *
 * Only reading is possible:
 *   - the only request is the query of the container, POST <account>/dbs/<db>/colls/<container>/docs with
 *     x-ms-documentdb-isquery; the database and the container are names, never a path
 *   - checkQuery() lets only one SELECT through, without parameters and without user-defined functions (udf.*,
 *     JavaScript on the server); the API for NoSQL has no statement that writes in its query language
 *   - the key is the name of a secret (expImportSecrets): the account's read-only key (the primary or secondary
 *     read-only key, used to sign each request with HMAC-SHA256, see authorization()) or a resource token with the
 *     permission Read; it is never stored in the import and masked in every message
 *   - an account in a private network (the emulator, a private endpoint) is refused unless
 *     [CosmosDbSourceSettings] AllowedHosts[] lists it
 */
class expImportCosmosDbSource extends expImportJsonSource
{
    /** The version of the REST API the requests name. */
    const API_VERSION = '2018-12-31';

    /** The words refused outside string literals: statements that write in other dialects, calls of code. */
    const FORBIDDEN = array( 'INSERT', 'UPDATE', 'DELETE', 'UPSERT', 'MERGE', 'CREATE', 'DROP', 'ALTER', 'EXEC', 'EXECUTE', 'PATCH' );

    /** The system properties of an item left out unless the import asks for them (_ts is kept). */
    const SYSTEM_FIELDS = array( '_rid', '_self', '_etag', '_attachments', '_lsn' );

    const LABEL = 'Cosmos DB';

    public static function identifier()
    {
        return 'cosmosdb';
    }

    public static function label()
    {
        return 'Azure Cosmos DB container (API for NoSQL)';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'nosql';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function variants()
    {
        return array();
    }

    public static function description()
    {
        return 'The items of an Azure Cosmos DB container (API for NoSQL) that a SELECT query gives, read through the REST API '
             . 'and paged with continuation tokens: one record is one item, nested objects as dotted fields (author.name). '
             . 'The key (a read-only key or a resource token) is the name of a secret in the settings. An account of the '
             . 'API for MongoDB is read with the MongoDB source.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'cosmosdb-orders.ndjson: what a run reads from a container of orders (three items, flattened), to try the mapping',
                   'file' => 'cosmosdb-orders.ndjson',
                   'options' => array( 'endpoint' => 'https://shop-example.documents.azure.com', 'database' => 'shop', 'container' => 'orders', 'key_secret' => 'cosmos_readonly', 'key' => 'id' ) ),
            array( 'label' => 'The open orders of the container orders, signed with the read-only key as the secret cosmos_readonly',
                   'options' => array( 'endpoint' => 'https://shop-example.documents.azure.com', 'database' => 'shop', 'container' => 'orders',
                                       'auth' => 'master_key', 'key_secret' => 'cosmos_readonly',
                                       'query' => "SELECT c.id, c.customer, c.total, c.lines FROM c WHERE c.status = 'open'", 'key' => 'id' ) ),
            array( 'label' => 'The products of one partition (category "tea") with a resource token as the secret cosmos_token',
                   'options' => array( 'endpoint' => 'https://shop-example.documents.azure.com', 'database' => 'shop', 'container' => 'products',
                                       'auth' => 'resource_token', 'key_secret' => 'cosmos_token', 'partition_key' => '"tea"',
                                       'query' => 'SELECT * FROM p ORDER BY p.name', 'cross_partition' => false, 'key' => 'id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'endpoint' => array( 'type' => 'url', 'label' => 'Account endpoint', 'required' => true,
                                 'help' => 'The URI of the account: https://<account>.documents.azure.com (a private network only with [CosmosDbSourceSettings] AllowedHosts[])' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9_.-]+$/' ),
            'container' => array( 'type' => 'string', 'label' => 'Container', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9_.-]+$/' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'master_key',
                             'values' => array( 'master_key' => 'Account key (signs each request; use the read-only key)', 'resource_token' => 'Resource token (permission Read)' ) ),
            'key_secret' => array( 'type' => 'secret', 'label' => 'Key or token (name of a secret)', 'required' => true,
                                   'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE with the read-only key (base64) or the resource token, never the key' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'default' => 'SELECT * FROM c',
                              'help' => 'One SELECT without parameters, e.g. SELECT c.id, c.title FROM c WHERE c.status = \'published\'' ),
            'cross_partition' => array( 'type' => 'bool', 'label' => 'Across partitions', 'default' => true,
                                        'help' => 'Query every partition (without a partition key). ORDER BY, TOP, DISTINCT and aggregates across partitions are refused by the gateway: give a partition key for them' ),
            'partition_key' => array( 'type' => 'string', 'label' => 'Partition key (JSON)', 'max' => 1024,
                                      'help' => 'Only the items of one partition: its key value as JSON ("tea", 42)' ),
            'max_item_count' => array( 'type' => 'int', 'label' => 'Items per page', 'default' => 100, 'min' => 1, 'max' => 1000 ),
            'limit' => array( 'type' => 'int', 'label' => 'Most items', 'default' => 0, 'min' => 0, 'help' => '0: every item' ),
            'system_fields' => array( 'type' => 'bool', 'label' => 'System properties', 'default' => false,
                                      'help' => 'Keep _rid, _self, _etag and _attachments (_ts, the time of the last change, is always kept)' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of objects stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names an item; id is unique in a partition' ),
        );
    }

    /** The snapshot is always one item per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query and the signature ---------------------------------------------------------------------------------

    /**
     * Whether a query only reads: one SELECT, no parameter (@name), no user-defined function (udf.), no word of a
     * statement that writes, outside string literals.
     *
     * @param string $sql
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $sql )
    {
        try
        {
            $blank = rtrim( expImportCouchbaseSource::blank( trim( (string)$sql ) ) );
        }
        catch ( expImportSourceException $e )
        {
            return 'the query has a quote or a comment that is not closed';
        }
        if ( substr( $blank, -1 ) === ';' )
            $blank = rtrim( substr( $blank, 0, -1 ) );
        if ( $blank === '' )
            return 'the query is empty';
        if ( strpos( $blank, ';' ) !== false )
            return 'only one statement is allowed';
        if ( !preg_match( '/^\s*SELECT\b/i', $blank ) )
            return 'only a SELECT is allowed';
        if ( preg_match_all( '/[A-Za-z_][A-Za-z0-9_]*/', $blank, $m ) )
            foreach ( $m[0] as $word )
                if ( in_array( strtoupper( $word ), self::FORBIDDEN, true ) )
                    return 'the query contains ' . strtoupper( $word ) . ': only reading is allowed (a field of that name: c["' . strtolower( $word ) . '"])';
        if ( preg_match( '/@[A-Za-z_]/', $blank ) )
            return 'the query may not have parameters (@name)';
        if ( preg_match( '/\budf\s*\./i', $blank ) )
            return 'user-defined functions (udf.) run code on the server and are not allowed';
        return null;
    }

    /**
     * The authorization header of a request signed with the account key: HMAC-SHA256 with the decoded key over the
     * verb, the resource type, the resource link and the date (each on a line, lowercase but the link).
     *
     * @param string $verb GET, POST
     * @param string $type the resource type: dbs, colls, docs
     * @param string $link the resource link: dbs/<db>/colls/<container> (as the names are, not encoded)
     * @param string $date the x-ms-date (RFC 1123)
     * @param string $key the account key (base64)
     * @return string the value of the header, URL-encoded
     * @throws expImportSourceException a key that is not base64
     */
    public static function authorization( $verb, $type, $link, $date, $key )
    {
        $binary = base64_decode( trim( (string)$key ), true );
        if ( $binary === false || $binary === '' )
            throw new expImportSourceException( self::LABEL . ': the key is not a base64 account key (a resource token needs the authentication Resource token)' );
        $text = strtolower( (string)$verb ) . "\n" . strtolower( (string)$type ) . "\n" . (string)$link . "\n" . strtolower( (string)$date ) . "\n\n";
        $signature = base64_encode( hash_hmac( 'sha256', $text, $binary, true ) );
        return rawurlencode( 'type=master&ver=1.0&sig=' . $signature );
    }

    /** @return string the time of a request as x-ms-date wants it (RFC 1123, GMT) */
    public static function date()
    {
        return gmdate( 'D, d M Y H:i:s', expImport::now() ) . ' GMT';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Everything a read needs, checked.
     *
     * @param array $options
     * @param string[] $used set to the secrets used
     * @param string[] $hidden set to other values to hide (the key or token as it is sent, URL-encoded)
     * @return array
     * @throws expImportSourceException
     */
    public static function plan( array $options, array &$used, array &$hidden )
    {
        $label = self::LABEL;
        list( $base, $trusted ) = expImportDocumentHttp::base( isset( $options['endpoint'] ) ? $options['endpoint'] : '', 'CosmosDbSourceSettings', $label );
        if ( (string)parse_url( $base, PHP_URL_PATH ) !== '' )
            throw new expImportSourceException( "$label: the account endpoint has no path (https://<account>.documents.azure.com)" );
        $names = array();
        foreach ( array( 'database', 'container' ) as $name )
        {
            $value = isset( $options[$name] ) ? trim( (string)$options[$name] ) : '';
            if ( !preg_match( '/^[A-Za-z0-9_.-]{1,255}$/', $value ) || $value === '.' || $value === '..' )
                throw new expImportSourceException( "$label: the $name must be a name (letters, digits, _ . -), not a path" );
            $names[$name] = $value;
        }
        $query = isset( $options['query'] ) && trim( (string)$options['query'] ) !== '' ? trim( (string)$options['query'] ) : 'SELECT * FROM c';
        $refusal = self::checkQuery( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( "$label: $refusal" );
        $partition = isset( $options['partition_key'] ) ? trim( (string)$options['partition_key'] ) : '';
        if ( $partition !== '' )
        {
            $value = json_decode( $partition, true );
            if ( ( $value === null && $partition !== 'null' ) || is_array( $value ) )
                throw new expImportSourceException( "$label: the partition key must be one JSON value, e.g. \"tea\" with the quotes, or 42" );
            $partition = (string)json_encode( array( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }
        $ref = isset( $options['key_secret'] ) ? trim( (string)$options['key_secret'] ) : '';
        $used[] = $ref;
        $secret = expImportSecrets::required( $ref, "$label: the key" );
        $hidden[] = rawurlencode( $secret );
        $auth = isset( $options['auth'] ) && $options['auth'] === 'resource_token' ? 'resource_token' : 'master_key';
        $link = 'dbs/' . $names['database'] . '/colls/' . $names['container'];
        return array( 'url' => $base . '/dbs/' . rawurlencode( $names['database'] ) . '/colls/' . rawurlencode( $names['container'] ) . '/docs',
                      'trusted' => $trusted, 'link' => $link, 'auth' => $auth, 'secret' => $secret, 'query' => $query, 'partition' => $partition,
                      'cross' => !isset( $options['cross_partition'] ) || !empty( $options['cross_partition'] ),
                      'page' => max( 1, min( 1000, isset( $options['max_item_count'] ) ? (int)$options['max_item_count'] : 100 ) ),
                      'limit' => isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0,
                      'system' => !empty( $options['system_fields'] ) );
    }

    /**
     * One page of the query.
     *
     * @param array $plan
     * @param int $size items per page
     * @param string|null $continuation the token of the previous answer
     * @return array the list ( array[] items, string|null the next continuation )
     * @throws expImportSourceException
     */
    protected static function page( array $plan, $size, $continuation )
    {
        $date = self::date();
        $headers = array(
            'Authorization' => $plan['auth'] === 'resource_token'
                             ? ( strpos( $plan['secret'], '%' ) !== false ? $plan['secret'] : rawurlencode( $plan['secret'] ) )
                             : self::authorization( 'POST', 'docs', $plan['link'], $date, $plan['secret'] ),
            'x-ms-date' => $date,
            'x-ms-version' => self::API_VERSION,
            'x-ms-documentdb-isquery' => 'True',
            'Content-Type' => 'application/query+json',
            'x-ms-max-item-count' => (string)(int)$size,
        );
        if ( $plan['partition'] !== '' )
            $headers['x-ms-documentdb-partitionkey'] = $plan['partition'];
        elseif ( $plan['cross'] )
            $headers['x-ms-documentdb-query-enablecrosspartition'] = 'True';
        if ( $continuation !== null )
            $headers['x-ms-continuation'] = $continuation;
        if ( substr( (string)parse_url( $plan['url'], PHP_URL_PATH ), -5 ) !== '/docs' )
            throw new expImportSourceException( self::LABEL . ': only the query of a container is sent' );
        list( $answer, $lines ) = expImportDocumentHttp::call( self::LABEL, 'POST', $plan['url'], $headers,
                                                               (string)json_encode( array( 'query' => $plan['query'], 'parameters' => array() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
                                                               $plan['trusted'] );
        if ( !isset( $answer['Documents'] ) || !is_array( $answer['Documents'] ) )
            throw new expImportSourceException( self::LABEL . ': the answer has no Documents' );
        $next = expImportDocumentHttp::headers( $lines, 'x-ms-continuation' );
        $next = $next && $next[0] !== '' ? $next[0] : null;
        return array( $answer['Documents'], $next );
    }

    /**
     * Reads the items into the snapshot, one flattened JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most items (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        expImportDocumentHttp::snapshot( self::LABEL, function ( array &$used, array &$hidden ) use ( $options, $limit )
        {
            return self::documents( $options, (int)$limit, $used, $hidden );
        }, $target, $limit );
    }

    /**
     * The flattened items, page by page with the continuation tokens.
     *
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function documents( array $options, $limit, array &$used, array &$hidden )
    {
        $plan = self::plan( $options, $used, $hidden );
        if ( $plan['limit'] > 0 )
            $limit = $limit > 0 ? min( $limit, $plan['limit'] ) : $plan['limit'];
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $size = $limit > 0 ? min( $plan['page'], $limit ) : $plan['page'];
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'CosmosDbSourceSettings', 'MaxPages', 10000 ) );
        $continuation = null;
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            list( $items, $continuation ) = self::page( $plan, $size, $continuation );
            foreach ( $items as $item )
            {
                yield self::record( $item, $plan['system'], $arrays, $separator );
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( $continuation === null )
                return;
        }
    }

    /**
     * An item as a record: flattened, without the system properties unless asked for; a plain value (SELECT VALUE)
     * as the field value.
     *
     * @param mixed $item
     * @param bool $system
     * @param string $arrays
     * @param string $separator
     * @return array
     */
    public static function record( $item, $system = false, $arrays = 'json', $separator = ', ' )
    {
        if ( !is_array( $item ) || ( $item !== array() && array_values( $item ) === $item ) )
            return expImportMongoDbSource::flatten( array( 'value' => $item ), $arrays, $separator );
        if ( !$system )
            foreach ( self::SYSTEM_FIELDS as $name )
                unset( $item[$name] );
        return expImportMongoDbSource::flatten( $item, $arrays, $separator );
    }

    // ---- test and drivers --------------------------------------------------------------------------------------------

    /**
     * The editor's "Test connection": the first item of the query, whose fields are reported, or why it failed
     * (masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $hidden = array();
        $driver = 'Azure Cosmos DB (API for NoSQL)';
        try
        {
            $plan = self::plan( $options, $used, $hidden );
            list( $items ) = self::page( $plan, 1, null );
            $columns = $items ? array_map( 'strval', array_keys( self::record( reset( $items ), $plan['system'] ) ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => min( 1, count( $items ) ), 'driver' => $driver,
                          'message' => 'connected' . ( $items ? '; the first item has these fields' : '; the query gives no item (on the first page)' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportDocumentHttp::mask( $e->getMessage(), $used, $hidden ) );
        }
    }

    /**
     * How this PHP reaches the account: PHP's https stream (noted for the job queue's workers).
     *
     * @return array id => array( label, available, via, needs, worker, worker_php )
     */
    public static function drivers()
    {
        return expImportDocumentHttp::drivers();
    }
}
