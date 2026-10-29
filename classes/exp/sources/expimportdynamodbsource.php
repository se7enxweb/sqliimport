<?php
/**
 * File containing the expImportDynamoDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The items of an Amazon DynamoDB table or index, read through the DynamoDB HTTPS JSON API (protocol version
 * 20120810) with AWS Signature Version 4 (expImportAwsSigV4; no SDK). One record is one item: its attributes with
 * maps flattened to dotted names (address.city).
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through a Scan or a Query and writes
 * the items into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible:
 *   - the only operations ever sent are Scan and Query (and DescribeTable for Test connection); target() refuses
 *     every other name before a request is built, so no PutItem, UpdateItem, DeleteItem, BatchWriteItem,
 *     TransactWriteItems or ExecuteStatement (PartiQL) can be sent
 *   - the import gives expressions (KeyConditionExpression, FilterExpression, ProjectionExpression) and their names
 *     and values, which DynamoDB evaluates only to choose and shape items; an UpdateExpression or a
 *     ConditionExpression is never sent
 *   - the IAM policy of the access key should allow dynamodb:Scan, dynamodb:Query and dynamodb:DescribeTable on the
 *     table (and its indexes) only
 *   - the secret access key and the session token are names of secrets (expImportSecrets), never stored in the
 *     import, never sent (only the signature is), and masked in every message
 *   - a custom endpoint (DynamoDB Local, a compatible service) in a private network is refused unless it is listed in
 *     [DynamoDbSourceSettings] AllowedHosts[]
 *
 * Paging: Limit items per request, and ExclusiveStartKey with the LastEvaluatedKey of the answer until there is none.
 */
class expImportDynamoDbSource extends expImportJsonSource
{
    /** The operations the source may send; every other one is refused. */
    const OPERATIONS = array( 'Scan', 'Query', 'DescribeTable' );

    /** The API version of the X-Amz-Target header. */
    const TARGET_PREFIX = 'DynamoDB_20120810.';

    /**
     * What drivers() sees of this PHP, for the tests: array( 'allow_url_fopen' => bool, 'openssl' => bool ). null:
     * this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    public static function identifier()
    {
        return 'dynamodb';
    }

    public static function label()
    {
        return 'Amazon DynamoDB table';
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
        return 'The items of an Amazon DynamoDB table or index, read with Scan or Query through the DynamoDB API (signed '
             . 'with AWS Signature Version 4, no SDK): one record is one item, maps flattened to dotted names '
             . '(address.city). Only Scan and Query are sent; the secret access key is the name of a secret in the '
             . 'settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three products of a table "products" as the source writes it (one item per line)',
                   'file' => 'dynamodb-products.ndjson',
                   'options' => array( 'region' => 'eu-central-1', 'access_key_id' => 'AKIAEXAMPLEEXAMPLE01', 'secret_key_secret' => 'aws_reader',
                                       'table' => 'products', 'key' => 'sku' ) ),
            array( 'label' => 'The orders of one customer, newest first, through the index by_customer (Query)',
                   'options' => array( 'region' => 'eu-central-1', 'access_key_id' => 'AKIAEXAMPLEEXAMPLE01', 'secret_key_secret' => 'aws_reader',
                                       'table' => 'orders', 'operation' => 'query', 'index_name' => 'by_customer',
                                       'key_condition' => 'customer_id = :c', 'values' => '{":c": {"S": "c-1001"}}',
                                       'scan_forward' => false, 'key' => 'order_id' ) ),
            array( 'label' => 'The published articles of a table on DynamoDB Local (a custom endpoint, listed in AllowedHosts)',
                   'options' => array( 'region' => 'us-east-1', 'endpoint' => 'http://localhost:8000', 'access_key_id' => 'local',
                                       'secret_key_secret' => 'dynamodb_local', 'table' => 'articles',
                                       'filter' => '#s = :p', 'names' => '{"#s": "status"}', 'values' => '{":p": {"S": "published"}}',
                                       'key' => 'id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'region' => array( 'type' => 'string', 'label' => 'Region', 'required' => true, 'max' => 40,
                               'pattern' => '/^[a-z]{2}(-[a-z]+)+-\d+$/', 'help' => 'The AWS region of the table: eu-central-1, us-east-1' ),
            'endpoint' => array( 'type' => 'url', 'label' => 'Endpoint',
                                 'help' => 'Empty: https://dynamodb.<region>.amazonaws.com. Else DynamoDB Local or a compatible service (http://localhost:8000, a private network only with [DynamoDbSourceSettings] AllowedHosts[])' ),
            'access_key_id' => array( 'type' => 'string', 'label' => 'Access key ID', 'required' => true, 'max' => 128,
                                      'pattern' => '/^[A-Za-z0-9]+$/', 'help' => 'The access key ID of an IAM user or role that may only read the table (AKIA...)' ),
            'secret_key_secret' => array( 'type' => 'secret', 'label' => 'Secret access key (name of a secret)', 'required' => true,
                                          'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the key itself' ),
            'session_token_secret' => array( 'type' => 'secret', 'label' => 'Session token (name of a secret)',
                                             'help' => 'Only with temporary credentials (STS): the name of the secret with the session token' ),
            'table' => array( 'type' => 'string', 'label' => 'Table', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9_.-]{3,255}$/',
                              'help' => 'The name of the table' ),
            'operation' => array( 'type' => 'enum', 'label' => 'Operation', 'default' => 'scan',
                                  'values' => array( 'scan' => 'Scan (every item, or those of the filter)', 'query' => 'Query (the items of a partition key)' ) ),
            'index_name' => array( 'type' => 'string', 'label' => 'Index', 'max' => 255, 'pattern' => '/^[A-Za-z0-9_.-]{3,255}$/',
                                   'help' => 'A global or local secondary index to read instead of the table (IndexName)' ),
            'key_condition' => array( 'type' => 'string', 'label' => 'Key condition', 'max' => 4000,
                                      'help' => 'Query only (required there): KeyConditionExpression, e.g. customer_id = :c AND created_at > :d' ),
            'filter' => array( 'type' => 'string', 'label' => 'Filter', 'max' => 4000,
                               'help' => 'FilterExpression, e.g. #s = :p AND price < :max' ),
            'names' => array( 'type' => 'json', 'label' => 'Attribute names',
                              'help' => 'ExpressionAttributeNames as JSON: {"#s": "status"}' ),
            'values' => array( 'type' => 'json', 'label' => 'Attribute values',
                               'help' => 'ExpressionAttributeValues as JSON in the DynamoDB form: {":p": {"S": "published"}, ":max": {"N": "20"}}' ),
            'projection' => array( 'type' => 'string', 'label' => 'Attributes', 'max' => 4000,
                                   'help' => 'ProjectionExpression: the attributes to read, e.g. sku, title, price, #s. Empty: all' ),
            'consistent_read' => array( 'type' => 'bool', 'label' => 'Strongly consistent read', 'default' => false,
                                        'help' => 'ConsistentRead (not on a global secondary index; costs twice the read capacity)' ),
            'scan_forward' => array( 'type' => 'bool', 'label' => 'Ascending sort key', 'default' => true,
                                     'help' => 'Query only: ScanIndexForward; no gives the items by descending sort key' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 1000,
                                  'help' => 'Limit: the items DynamoDB evaluates per request (an answer is also at most 1 MB)' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most items', 'default' => 0, 'min' => 0, 'help' => '0: all' ),
            'sets' => array( 'type' => 'enum', 'label' => 'Sets (SS, NS, BS)', 'default' => 'join',
                             'values' => array( 'join' => 'A joined list (a, b)', 'json' => 'JSON (["a","b"])' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'Separator', 'default' => ', ', 'raw' => true, 'max' => 10,
                                  'help' => 'What stands between the values of a joined set' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '',
                            'help' => 'The attribute that names an item (its partition key, or partition and sort key joined in a transform). Empty: the position' ),
        );
    }

    /** The snapshot is always one item per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the request -------------------------------------------------------------------------------------------------

    /**
     * The X-Amz-Target of an operation; any operation other than Scan, Query and DescribeTable is refused.
     *
     * @param string $operation
     * @return string
     * @throws expImportSourceException
     */
    public static function target( $operation )
    {
        if ( !in_array( (string)$operation, self::OPERATIONS, true ) )
            throw new expImportSourceException( "DynamoDB: the operation $operation is refused: the source only reads (Scan, Query, DescribeTable)" );
        return self::TARGET_PREFIX . $operation;
    }

    /**
     * The request body of a Scan or Query from the options (without Limit and ExclusiveStartKey).
     *
     * @param array $options
     * @return array ( string operation Scan or Query, array body )
     * @throws expImportSourceException
     */
    public static function body( array $options )
    {
        $operation = isset( $options['operation'] ) && $options['operation'] === 'query' ? 'Query' : 'Scan';
        $table = isset( $options['table'] ) ? trim( (string)$options['table'] ) : '';
        if ( !preg_match( '/^[A-Za-z0-9_.-]{3,255}$/', $table ) )
            throw new expImportSourceException( 'DynamoDB: the table must be a table name (3 to 255 letters, digits, _ . -)' );
        $body = array( 'TableName' => $table );
        $index = isset( $options['index_name'] ) ? trim( (string)$options['index_name'] ) : '';
        if ( $index !== '' )
        {
            if ( !preg_match( '/^[A-Za-z0-9_.-]{3,255}$/', $index ) )
                throw new expImportSourceException( 'DynamoDB: the index must be an index name' );
            $body['IndexName'] = $index;
        }
        $condition = isset( $options['key_condition'] ) ? trim( (string)$options['key_condition'] ) : '';
        if ( $operation === 'Query' )
        {
            if ( $condition === '' )
                throw new expImportSourceException( 'DynamoDB: a Query needs a key condition (KeyConditionExpression)' );
            $body['KeyConditionExpression'] = $condition;
            $body['ScanIndexForward'] = !isset( $options['scan_forward'] ) || (bool)$options['scan_forward'];
        }
        elseif ( $condition !== '' )
            throw new expImportSourceException( 'DynamoDB: a key condition is only for the operation Query (a Scan takes a filter)' );
        foreach ( array( 'filter' => 'FilterExpression', 'projection' => 'ProjectionExpression' ) as $name => $field )
        {
            $text = isset( $options[$name] ) ? trim( (string)$options[$name] ) : '';
            if ( $text !== '' )
                $body[$field] = $text;
        }
        $names = expImportCloudHttp::jsonOption( isset( $options['names'] ) ? $options['names'] : null, 'DynamoDB: the attribute names' );
        foreach ( $names as $alias => $name )
            if ( !preg_match( '/^#[A-Za-z0-9_]+$/', (string)$alias ) || !is_string( $name ) || $name === '' )
                throw new expImportSourceException( 'DynamoDB: the attribute names must be an object of #alias: "attribute name"' );
        if ( $names )
            $body['ExpressionAttributeNames'] = $names;
        $values = expImportCloudHttp::jsonOption( isset( $options['values'] ) ? $options['values'] : null, 'DynamoDB: the attribute values' );
        foreach ( $values as $alias => $value )
            if ( !preg_match( '/^:[A-Za-z0-9_]+$/', (string)$alias ) || !self::isAttributeValue( $value ) )
                throw new expImportSourceException( 'DynamoDB: the attribute values must be an object of :name: {"S": "text"} or {"N": "12"} (the DynamoDB form)' );
        if ( $values )
            $body['ExpressionAttributeValues'] = $values;
        if ( !empty( $options['consistent_read'] ) )
            $body['ConsistentRead'] = true;
        return array( $operation, $body );
    }

    /** @return bool whether a decoded value is an AttributeValue: an object with one key of the DynamoDB types */
    protected static function isAttributeValue( $value )
    {
        if ( !is_array( $value ) || count( $value ) !== 1 )
            return false;
        $type = (string)key( $value );
        return in_array( $type, array( 'S', 'N', 'B', 'BOOL', 'NULL', 'M', 'L', 'SS', 'NS', 'BS' ), true );
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The endpoint, the credentials and the trusted hosts of the options, after the checks.
     *
     * @param array $options
     * @param string[] $used set to the secrets used, for masking
     * @return array ( string endpoint, string region, string access key id, string secret key, string session token, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $region = isset( $options['region'] ) ? trim( (string)$options['region'] ) : '';
        if ( !preg_match( '/^[a-z]{2}(-[a-z]+)+-\d+$/', $region ) )
            throw new expImportSourceException( 'DynamoDB: the region must be an AWS region such as eu-central-1' );
        $trusted = (array)expImportPlatformHttp::setting( 'DynamoDbSourceSettings', 'AllowedHosts', array() );
        $endpoint = rtrim( isset( $options['endpoint'] ) ? trim( (string)$options['endpoint'] ) : '', '/' );
        if ( $endpoint === '' )
            $endpoint = 'https://dynamodb.' . $region . '.amazonaws.com';
        else
        {
            $parts = parse_url( $endpoint );
            if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
                throw new expImportSourceException( 'DynamoDB: the endpoint must be an http or https address' );
            if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['path'] ) && $parts['path'] !== '/' ) )
                throw new expImportSourceException( 'DynamoDB: the endpoint is a server address without a path, a query or credentials' );
            $refusal = expImportSourceFiles::refuseHost( $parts['host'], $trusted );
            if ( $refusal !== null )
                throw new expImportSourceException( 'DynamoDB: ' . $refusal . ' (or list it in sqliimport.ini [DynamoDbSourceSettings] AllowedHosts[])' );
        }
        $accessKey = isset( $options['access_key_id'] ) ? trim( (string)$options['access_key_id'] ) : '';
        if ( !preg_match( '/^[A-Za-z0-9]+$/', $accessKey ) )
            throw new expImportSourceException( 'DynamoDB: the access key ID is required' );
        $ref = isset( $options['secret_key_secret'] ) ? trim( (string)$options['secret_key_secret'] ) : '';
        $used[] = $ref;
        $secret = expImportSecrets::required( $ref, 'DynamoDB: the secret access key' );
        $token = '';
        $tokenRef = isset( $options['session_token_secret'] ) ? trim( (string)$options['session_token_secret'] ) : '';
        if ( $tokenRef !== '' )
        {
            $used[] = $tokenRef;
            $token = expImportSecrets::required( $tokenRef, 'DynamoDB: the session token' );
        }
        return array( $endpoint, $region, $accessKey, $secret, $token, $trusted );
    }

    /**
     * One signed call of an operation.
     *
     * @param array $connection connection()
     * @param string $operation Scan, Query or DescribeTable
     * @param array $body
     * @return array the decoded answer
     * @throws expImportSourceException
     */
    public static function call( array $connection, $operation, array $body )
    {
        list( $endpoint, $region, $accessKey, $secret, $token, $trusted ) = $connection;
        $headers = array( 'Content-Type' => 'application/x-amz-json-1.0', 'X-Amz-Target' => self::target( $operation ) );
        $sign = function ( $method, $url, array $headers, $text ) use ( $accessKey, $secret, $token, $region )
        {
            return expImportAwsSigV4::sign( $method, $url, $headers, $text, $accessKey, $secret, $token, $region, 'dynamodb' );
        };
        return expImportCloudHttp::json( 'DynamoDB', 'POST', $endpoint . '/', $headers, $body, $trusted, $sign );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the items and writes them into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most items (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::items( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'DynamoDB: the snapshot cannot be written' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The flattened items, page by page.
     *
     * @param array $options
     * @param int $limit 0: all (else also the option limit)
     * @param string[] $used the secrets used
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function items( array $options, $limit, array &$used )
    {
        list( $operation, $body ) = self::body( $options );
        $connection = self::connection( $options, $used );
        $max = isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0;
        if ( $limit > 0 && ( $max === 0 || $limit < $max ) )
            $max = (int)$limit;
        $size = max( 1, min( 1000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 100 ) );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'DynamoDbSourceSettings', 'MaxPages', 1000 ) );
        $sets = isset( $options['sets'] ) && $options['sets'] === 'json' ? 'json' : 'join';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $n = 0;
        $start = null;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $request = $body;
            $request['Limit'] = $max > 0 ? min( $size, $max - $n ) : $size;
            if ( $start !== null )
                $request['ExclusiveStartKey'] = $start;
            $answer = self::call( $connection, $operation, $request );
            $items = isset( $answer['Items'] ) && is_array( $answer['Items'] ) ? $answer['Items'] : array();
            foreach ( $items as $item )
            {
                yield self::item( is_array( $item ) ? $item : array(), $sets, $separator );
                if ( $max > 0 && ++$n >= $max )
                    return;
            }
            if ( !isset( $answer['LastEvaluatedKey'] ) || !is_array( $answer['LastEvaluatedKey'] ) || !$answer['LastEvaluatedKey'] )
                return;
            $start = $answer['LastEvaluatedKey'];
        }
        throw new expImportSourceException( 'DynamoDB: the table has more pages than [DynamoDbSourceSettings] MaxPages (' . $maxPages . '); narrow the filter or raise the setting' );
    }

    /**
     * An item as a record: maps flattened to dotted names, lists as JSON, sets joined or as JSON.
     *
     * @param array $item attribute => AttributeValue
     * @param string $sets join or json
     * @param string $separator
     * @return array
     */
    public static function item( array $item, $sets = 'join', $separator = ', ' )
    {
        $plain = array();
        foreach ( $item as $name => $value )
            $plain[(string)$name] = self::value( $value, $sets, $separator );
        return expImportMongoDbSource::flatten( $plain, 'json' );
    }

    /**
     * The PHP value of an AttributeValue: S text, N its decimal string, B base64 (as DynamoDB sends it), BOOL, NULL,
     * M an array of its attributes (flattened by item()), L a list (written as JSON), SS, NS and BS a joined text or a
     * list.
     *
     * @param mixed $value
     * @param string $sets join or json
     * @param string $separator
     * @return mixed
     */
    public static function value( $value, $sets = 'join', $separator = ', ' )
    {
        if ( !is_array( $value ) || !$value )
            return null;
        $type = (string)key( $value );
        $data = reset( $value );
        switch ( $type )
        {
            case 'S':
            case 'N':
            case 'B':
                return (string)$data;
            case 'BOOL':
                return (bool)$data;
            case 'NULL':
                return null;
            case 'M':
                $out = array();
                foreach ( (array)$data as $name => $item )
                    $out[(string)$name] = self::value( $item, $sets, $separator );
                // an empty map stays an empty object rather than a list
                return $out ? $out : '{}';
            case 'L':
                $list = array();
                foreach ( (array)$data as $item )
                    $list[] = self::value( $item, $sets, $separator );
                // a list is written as JSON, also when it holds maps
                return (string)json_encode( $list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
            case 'SS':
            case 'NS':
            case 'BS':
                $list = array_map( 'strval', array_values( (array)$data ) );
                return $sets === 'json' ? (string)json_encode( $list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : implode( (string)$separator, $list );
        }
        return null;
    }

    /**
     * The editor's "Test connection": DescribeTable (status and item count; skipped when the key may not describe the
     * table) and one page of one item of the Scan or Query, whose fields are reported, or why it failed (masked).
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'DynamoDB API (Signature Version 4)';
        try
        {
            list( $operation, $body ) = self::body( $options );
            $connection = self::connection( $options, $used );
            $where = 'connected to ' . expImportPlatformHttp::shown( $connection[0] );
            try
            {
                $table = self::call( $connection, 'DescribeTable', array( 'TableName' => $body['TableName'] ) );
                if ( isset( $table['Table'] ) && is_array( $table['Table'] ) )
                    $where .= '; table ' . $body['TableName'] . ' ' . ( isset( $table['Table']['TableStatus'] ) ? (string)$table['Table']['TableStatus'] : '' )
                            . ( isset( $table['Table']['ItemCount'] ) ? ', about ' . (int)$table['Table']['ItemCount'] . ' items' : '' );
            }
            catch ( expImportSourceException $e )
            {
                if ( strpos( $e->getMessage(), 'AccessDenied' ) === false )
                    throw $e;
                $where .= '; DescribeTable is not allowed for this key (only Test connection uses it)';
            }
            $body['Limit'] = 1;
            $answer = self::call( $connection, $operation, $body );
            $items = isset( $answer['Items'] ) && is_array( $answer['Items'] ) ? $answer['Items'] : array();
            $columns = $items ? array_map( 'strval', array_keys( self::item( (array)reset( $items ) ) ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $items ), 'driver' => $driver,
                          'message' => $where . ( $items ? '; the ' . $operation . ' gives these fields'
                                                         : '; the first page of the ' . $operation . ' has no item (a filter may still match later pages)' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /**
     * How this PHP reaches DynamoDB: the https stream of PHP with openssl (also for the signature's HMAC, which needs
     * only hash), with what the job queue's workers last had.
     *
     * @return array id => array( 'label', 'available', 'via', 'needs', 'worker', 'worker_php' )
     */
    public static function drivers()
    {
        return expImportCloudHttp::drivers( self::$environment, 'DynamoDB HTTPS API' );
    }
}
