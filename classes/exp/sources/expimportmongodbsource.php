<?php
/**
 * File containing the expImportMongoDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The documents of a MongoDB collection: one record is one document, its nested documents flattened into dotted
 * fields (author.name) and its BSON values written as text (ObjectId as its hex string, UTCDateTime in ISO 8601,
 * Decimal128 as a string, Binary in base64, a UUID as a UUID), arrays as JSON or as a joined list.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() reads the documents page by page (after the
 * last _id, or with skip and limit) and writes them into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible: the only operation is a find (MongoDB\Driver\Query) with a filter, a projection and a sort;
 * no command, no aggregation, no write. checkJson() refuses the operators that run code on the server or write
 * ($where, $function, $accumulator, $out, $merge) wherever they stand. The password is the name of a secret
 * (expImportSecrets), never part of the URI, and masked in every message; a server in a private network is refused
 * unless [MongoDbSourceSettings] AllowedHosts[] lists it; system collections (system.*) are not read.
 */
class expImportMongoDbSource extends expImportJsonSource
{
    /** The operators a filter, projection or sort must not contain anywhere: code on the server, writes. */
    const FORBIDDEN = array( '$where', '$function', '$accumulator', '$out', '$merge' );

    /** The default port of MongoDB. */
    const PORT = 27017;

    /** Nested documents deeper than this are written as JSON. */
    const MAX_DEPTH = 32;

    /**
     * What drivers() sees of this PHP, for the tests: array( 'mongodb' => string the version of the extension, '' none ).
     * null: this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    /**
     * Makes the connection instead of expImportMongoDbExtension, for the tests: function () returning an
     * expImportMongoDbTransport. null: the extension.
     *
     * @var callable|null
     */
    public static $transportFactory = null;

    public static function identifier()
    {
        return 'mongodb';
    }

    public static function label()
    {
        return 'MongoDB collection';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'data';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function description()
    {
        return 'The documents of a MongoDB collection (the database of another application, a product or event store): one '
             . 'record is one document, nested documents as dotted fields (author.name), ObjectId, dates, decimals and '
             . 'binary values as text, arrays as JSON or a joined list. Only a find with a filter, projection and sort is '
             . 'run, read page by page; the password is the name of a secret in the settings. Needs the PHP extension '
             . 'mongodb in the PHP of the job queue\'s workers.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'mongodb-articles.ndjson: what a run reads from a collection of articles (three documents, flattened), to try the mapping',
                   'file' => 'mongodb-articles.ndjson' ),
            array( 'label' => 'The published articles of the collection articles in the database cms on mongo.example.org, newest first, user reader, the password as the secret cms_mongo',
                   'options' => array( 'uri' => 'mongo.example.org', 'port' => 27017, 'tls' => true, 'user' => 'reader', 'password' => 'cms_mongo',
                                       'auth_source' => 'admin', 'database' => 'cms', 'collection' => 'articles',
                                       'filter' => '{"status": "published", "published_at": {"$gte": {"$date": "2026-01-01T00:00:00Z"}}}',
                                       'projection' => '{"title": 1, "intro": 1, "body": 1, "author": 1, "tags": 1, "published_at": 1}',
                                       'sort' => '{"published_at": -1}', 'paging' => 'skip', 'arrays' => 'join', 'key' => '_id' ) ),
            array( 'label' => 'A replica set read from a secondary: mongodb://db1.example.org,db2.example.org/?replicaSet=rs0, collection products, the password as the secret shop_mongo',
                   'options' => array( 'uri' => 'mongodb://db1.example.org:27017,db2.example.org:27017/?replicaSet=rs0', 'user' => 'reader',
                                       'password' => 'shop_mongo', 'database' => 'shop', 'collection' => 'products', 'filter' => '{"active": true}',
                                       'read_preference' => 'secondaryPreferred', 'key' => 'sku' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'uri' => array( 'type' => 'string', 'label' => 'Server or URI', 'required' => true, 'max' => 2048,
                            'help' => 'A host (mongo.example.org), host:port, or a mongodb:// or mongodb+srv:// URI without user and password (a private network only with [MongoDbSourceSettings] AllowedHosts[])' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 0, 'min' => 0, 'max' => 65535,
                             'help' => 'Only with a host: 0 is the default port 27017' ),
            'tls' => array( 'type' => 'bool', 'label' => 'TLS', 'default' => false,
                            'help' => 'Encrypt the connection (certificates are verified); mongodb+srv:// always uses TLS' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255 ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'auth_source' => array( 'type' => 'string', 'label' => 'Authentication database', 'max' => 64, 'pattern' => '/^[A-Za-z0-9_-]*$/',
                                    'help' => 'The database the user is defined in; empty: the database of the URI, else admin' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'required' => true, 'max' => 64, 'pattern' => '/^[A-Za-z0-9_-]+$/' ),
            'collection' => array( 'type' => 'string', 'label' => 'Collection', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/' ),
            'filter' => array( 'type' => 'text', 'label' => 'Filter (JSON)',
                               'help' => 'A query document, e.g. {"status": "published"}; Extended JSON for types: {"$oid": "..."}, {"$date": "2026-01-01T00:00:00Z"}. $where, $function and $accumulator are refused. Empty: every document' ),
            'projection' => array( 'type' => 'text', 'label' => 'Projection (JSON)',
                                   'help' => 'The fields to read, e.g. {"title": 1, "author.name": 1}; empty: all' ),
            'sort' => array( 'type' => 'text', 'label' => 'Sort (JSON)',
                             'help' => 'e.g. {"published_at": -1}; needs the paging Skip and limit (paging by _id reads in _id order)' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'id',
                               'values' => array( 'id' => 'By _id (each page after the last _id; fast, in _id order)', 'skip' => 'Skip and limit (any sort order)' ) ),
            'batch_size' => array( 'type' => 'int', 'label' => 'Documents per page', 'default' => 500, 'min' => 1, 'max' => 10000 ),
            'limit' => array( 'type' => 'int', 'label' => 'Most documents', 'default' => 0, 'min' => 0,
                              'help' => '0: every matching document' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of documents stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10,
                                  'help' => 'Arrays as a joined list: what stands between the values' ),
            'read_preference' => array( 'type' => 'enum', 'label' => 'Read from', 'default' => 'primary',
                                        'values' => array( 'primary' => 'The primary', 'primaryPreferred' => 'The primary, else a secondary',
                                                           'secondaryPreferred' => 'A secondary, else the primary', 'secondary' => 'A secondary',
                                                           'nearest' => 'The nearest member' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_id',
                            'help' => 'The field that names a document; _id is unique. A nested field by its dotted name (meta.sku)' ),
        );
    }

    /** The snapshot is always one document per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * A filter, projection or sort as given: empty, or one JSON object without a forbidden operator at any depth.
     *
     * @param string $json
     * @param string $what filter, projection or sort, for the message
     * @return array the object (array() when empty)
     * @throws expImportSourceException
     */
    public static function checkJson( $json, $what )
    {
        $json = trim( (string)$json );
        if ( $json === '' )
            return array();
        if ( $json[0] !== '{' )
            throw new expImportSourceException( "MongoDB: the $what must be a JSON object ({...}); pipelines and lists are not accepted" );
        $data = json_decode( $json, true, 128 );
        if ( !is_array( $data ) )
            throw new expImportSourceException( "MongoDB: the $what is not valid JSON: " . json_last_error_msg() );
        $found = self::forbiddenIn( $data );
        if ( $found !== null )
            throw new expImportSourceException( "MongoDB: the $what contains $found, only reading is allowed" . ' ($where, $function, $accumulator, $out and $merge are refused)' );
        return $data;
    }

    /** @return string|null the first forbidden operator among the keys of a decoded document, at any depth */
    protected static function forbiddenIn( array $data )
    {
        foreach ( $data as $key => $value )
        {
            if ( is_string( $key ) && in_array( strtolower( trim( $key ) ), self::FORBIDDEN, true ) )
                return trim( $key );
            if ( is_array( $value ) && ( $found = self::forbiddenIn( $value ) ) !== null )
                return $found;
        }
        return null;
    }

    /**
     * What to connect to: the URI (without credentials) and the hosts in it, after the checks.
     *
     * @param array $options
     * @return array the list ( string uri, string[] hosts, bool srv )
     * @throws expImportSourceException
     */
    public static function uriOf( array $options )
    {
        $given = isset( $options['uri'] ) ? trim( (string)$options['uri'] ) : '';
        $port = isset( $options['port'] ) ? (int)$options['port'] : 0;
        if ( $given === '' )
            throw new expImportSourceException( 'MongoDB: the server is missing' );
        if ( preg_match( '/[\s\0]/', $given ) )
            throw new expImportSourceException( 'MongoDB: the server has spaces or characters that are not allowed' );
        if ( !preg_match( '#^mongodb(\+srv)?://(.*)$#i', $given, $m ) )
        {
            // a host or host:port
            if ( preg_match( '/^\[([0-9A-Fa-f:.]+)\](?::(\d{1,5}))?$/', $given, $h ) || preg_match( '/^([A-Za-z0-9._-]+)(?::(\d{1,5}))?$/', $given, $h ) )
            {
                $host = $h[1];
                $p = isset( $h[2] ) && $h[2] !== '' ? (int)$h[2] : ( $port ?: self::PORT );
                $hostText = strpos( $host, ':' ) !== false ? '[' . $host . ']' : $host;
                return array( 'mongodb://' . $hostText . ':' . $p . '/', array( $host ), false );
            }
            throw new expImportSourceException( 'MongoDB: give a host, host:port or a mongodb:// URI' );
        }
        $srv = $m[1] !== '';
        $rest = $m[2];
        $cut = strcspn( $rest, '/?' );
        $hostList = substr( $rest, 0, $cut );
        $tail = (string)substr( $rest, $cut );
        if ( strpos( $hostList, '@' ) !== false || strpos( $hostList, '%40' ) !== false )
            throw new expImportSourceException( 'MongoDB: the URI must not hold a user or password; give the user and the name of a secret as options' );
        $query = '';
        if ( ( $q = strpos( $tail, '?' ) ) !== false )
            $query = (string)substr( $tail, $q + 1 );
        foreach ( $query === '' ? array() : explode( '&', $query ) as $pair )
        {
            $name = strtolower( urldecode( (string)strstr( $pair . '=', '=', true ) ) );
            if ( strpos( $name, 'password' ) !== false || $name === 'username' || $name === 'authmechanismproperties' )
                throw new expImportSourceException( "MongoDB: the URI option $name is not accepted; give the user and the name of a secret as options" );
        }
        $hosts = array();
        foreach ( explode( ',', $hostList ) as $entry )
        {
            if ( preg_match( '/^\[([0-9A-Fa-f:.]+)\](?::(\d{1,5}))?$/', $entry, $h ) || preg_match( '/^([A-Za-z0-9._-]+)(?::(\d{1,5}))?$/', $entry, $h ) )
            {
                if ( $srv && isset( $h[2] ) && $h[2] !== '' )
                    throw new expImportSourceException( 'MongoDB: a mongodb+srv:// URI names one host without a port' );
                $hosts[] = $h[1];
            }
            else
                throw new expImportSourceException( 'MongoDB: the URI has a host that is not a host name or an address' );
        }
        if ( !$hosts || ( $srv && count( $hosts ) > 1 ) )
            throw new expImportSourceException( 'MongoDB: the URI names no host, or a mongodb+srv:// URI more than one' );
        return array( $given, $hosts, $srv );
    }

    /**
     * Refuses a server in a private or reserved network unless [MongoDbSourceSettings] AllowedHosts[] lists it; for
     * mongodb+srv:// the hosts its SRV record names.
     *
     * @param string[] $hosts
     * @param bool $srv
     * @throws expImportSourceException
     */
    protected static function checkHosts( array $hosts, $srv )
    {
        $allowed = (array)expImport::setting( 'MongoDbSourceSettings', 'AllowedHosts', array() );
        foreach ( $hosts as $host )
        {
            $targets = array( $host );
            if ( $srv && !in_array( strtolower( $host ), array_map( 'strtolower', array_map( 'trim', $allowed ) ), true )
                 && expImport::setting( 'SourceSettings', 'AllowPrivateHosts', 'disabled' ) !== 'enabled' )
            {
                $records = function_exists( 'dns_get_record' ) ? @dns_get_record( '_mongodb._tcp.' . $host, DNS_SRV ) : false;
                $targets = array();
                foreach ( is_array( $records ) ? $records : array() as $record )
                    if ( isset( $record['target'] ) )
                        $targets[] = (string)$record['target'];
                if ( !$targets )
                    throw new expImportSourceException( "MongoDB: the SRV record of $host cannot be resolved" );
            }
            foreach ( $targets as $target )
            {
                $refusal = expImportSourceFiles::refuseHost( $target, $allowed );
                if ( $refusal !== null )
                    throw new expImportSourceException( 'MongoDB: ' . $refusal . ' (or list it in sqliimport.ini [MongoDbSourceSettings] AllowedHosts[])' );
            }
        }
    }

    /**
     * Everything a read needs, checked: the URI and its options (credentials from the secret), the names, the filter,
     * projection and sort, the paging.
     *
     * @param array $options
     * @return array uri, uri_options, database, collection, filter (JSON text), projection, sort (arrays), paging,
     *               batch, limit, read_preference, refs (secret names), password
     * @throws expImportSourceException
     */
    public static function plan( array $options )
    {
        list( $uri, $hosts, $srv ) = self::uriOf( $options );
        self::checkHosts( $hosts, $srv );
        $database = isset( $options['database'] ) ? trim( (string)$options['database'] ) : '';
        $collection = isset( $options['collection'] ) ? trim( (string)$options['collection'] ) : '';
        if ( !preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $database ) )
            throw new expImportSourceException( 'MongoDB: the database name is missing or has characters that are not allowed' );
        if ( !preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,254}$/', $collection ) )
            throw new expImportSourceException( 'MongoDB: the collection name is missing or has characters that are not allowed' );
        if ( stripos( $collection, 'system.' ) === 0 )
            throw new expImportSourceException( 'MongoDB: system collections (system.*) are not read' );
        $filter = self::checkJson( isset( $options['filter'] ) ? $options['filter'] : '', 'filter' );
        $projection = self::checkJson( isset( $options['projection'] ) ? $options['projection'] : '', 'projection' );
        $sort = self::checkJson( isset( $options['sort'] ) ? $options['sort'] : '', 'sort' );
        $paging = isset( $options['paging'] ) && $options['paging'] === 'skip' ? 'skip' : 'id';
        if ( $paging === 'id' )
        {
            if ( $sort && $sort !== array( '_id' => 1 ) )
                throw new expImportSourceException( 'MongoDB: a sort needs the paging "Skip and limit" (paging by _id reads in _id order)' );
            if ( array_key_exists( '_id', $projection ) && empty( $projection['_id'] ) )
                throw new expImportSourceException( 'MongoDB: paging by _id needs the _id field (the projection leaves it out)' );
            $sort = array( '_id' => 1 );
        }
        elseif ( !$sort )
            $sort = array( '_id' => 1 );
        $user = isset( $options['user'] ) ? trim( (string)$options['user'] ) : '';
        $ref = isset( $options['password'] ) ? trim( (string)$options['password'] ) : '';
        $refs = $ref !== '' ? array( $ref ) : array();
        $password = $ref !== '' ? expImportSecrets::required( $ref, 'MongoDB password' ) : '';
        $seconds = max( 1, (int)expImport::setting( 'MongoDbSourceSettings', 'Timeout', 30 ) );
        $uriOptions = array( 'appname' => 'Exponential Imports', 'connectTimeoutMS' => min( 60, $seconds ) * 1000,
                             'serverSelectionTimeoutMS' => min( 60, $seconds ) * 1000, 'socketTimeoutMS' => $seconds * 2000 );
        if ( !empty( $options['tls'] ) )
            $uriOptions['tls'] = true;
        if ( $user !== '' )
        {
            $uriOptions['username'] = $user;
            if ( $password !== '' )
                $uriOptions['password'] = $password;
            $source = isset( $options['auth_source'] ) ? trim( (string)$options['auth_source'] ) : '';
            if ( $source !== '' )
                $uriOptions['authSource'] = $source;
        }
        $mode = isset( $options['read_preference'] ) ? (string)$options['read_preference'] : 'primary';
        if ( !in_array( $mode, array( 'primary', 'primaryPreferred', 'secondary', 'secondaryPreferred', 'nearest' ), true ) )
            $mode = 'primary';
        $batch = isset( $options['batch_size'] ) ? (int)$options['batch_size'] : 500;
        $batch = max( 1, min( max( 1, (int)expImport::setting( 'MongoDbSourceSettings', 'MaxBatchSize', 10000 ) ), $batch ) );
        return array(
            'uri' => $uri, 'uri_options' => $uriOptions, 'database' => $database, 'collection' => $collection,
            'filter' => $filter ? trim( (string)$options['filter'] ) : '{}', 'projection' => $projection, 'sort' => $sort,
            'paging' => $paging, 'batch' => $batch, 'limit' => isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0,
            'read_preference' => $mode, 'max_time_ms' => $seconds * 1000, 'refs' => $refs, 'password' => $password,
        );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /** @return expImportMongoDbTransport */
    public static function transport()
    {
        if ( self::$transportFactory )
        {
            $transport = call_user_func( self::$transportFactory );
            if ( $transport instanceof expImportMongoDbTransport )
                return $transport;
        }
        if ( !self::hasExtension() )
            throw new expImportSourceException( self::missingDriverMessage() );
        return new expImportMongoDbExtension();
    }

    /**
     * Reads the documents into the snapshot, one flattened JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most documents to write (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $out = fopen( $tmp, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'MongoDB: the snapshot cannot be written' );
        try
        {
            self::documents( $options, $limit, function ( array $row ) use ( $out )
            {
                fwrite( $out, (string)json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n" );
            } );
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'MongoDB: the snapshot cannot be written' );
        }
        finally
        {
            if ( $out )
                fclose( $out );
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The editor's "Test connection": connects, reads the first matching document and reports its fields, or why it
     * failed (never a password: the message is masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $driver = self::hasExtension() || self::$transportFactory ? 'mongodb' : '';
        $rows = 0;
        $columns = array();
        try
        {
            self::documents( $options, 1, function ( array $row ) use ( &$rows, &$columns )
            {
                $rows++;
                if ( !$columns )
                    $columns = array_keys( $row );
            } );
            return array( 'ok' => true, 'columns' => array_values( array_map( 'strval', $columns ) ), 'rows' => $rows, 'driver' => $driver,
                          'message' => $rows ? 'connected, the first document has these fields' : 'connected; no document matches' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver, 'message' => $e->getMessage() );
        }
    }

    /**
     * Reads the documents page by page and hands each one, flattened, to $each.
     *
     * @param array $options
     * @param int $limit the most documents (with the option limit, the smaller); 0: all
     * @param callable $each function ( array $row )
     * @return int the documents read
     * @throws expImportSourceException
     */
    protected static function documents( array $options, $limit, callable $each )
    {
        $plan = self::plan( $options );
        $limit = (int)$limit;
        if ( $plan['limit'] > 0 )
            $limit = $limit > 0 ? min( $limit, $plan['limit'] ) : $plan['limit'];
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $transport = null;
        $read = 0;
        try
        {
            $transport = self::transport();
            $transport->open( $plan['uri'], $plan['uri_options'] );
            $after = null;
            while ( true )
            {
                $n = $limit > 0 ? min( $plan['batch'], $limit - $read ) : $plan['batch'];
                $query = array( 'projection' => $plan['projection'], 'sort' => $plan['sort'], 'limit' => $n, 'batchSize' => $n,
                                'maxTimeMS' => $plan['max_time_ms'], 'readPreference' => $plan['read_preference'] );
                if ( $plan['paging'] === 'skip' && $read > 0 )
                    $query['skip'] = $read;
                $page = 0;
                foreach ( $transport->find( $plan['database'], $plan['collection'], $plan['filter'], $plan['paging'] === 'id' ? $after : null, $query ) as $document )
                {
                    $document = (array)$document;
                    if ( $plan['paging'] === 'id' )
                    {
                        if ( !array_key_exists( '_id', $document ) )
                            throw new expImportSourceException( 'MongoDB: a document without _id cannot be paged by _id' );
                        $after = $document['_id'];
                    }
                    $each( self::flatten( $document, $arrays, $separator ) );
                    $page++;
                    $read++;
                    if ( $page >= $n )
                        break;
                }
                if ( $page < $n || ( $limit > 0 && $read >= $limit ) )
                    break;
            }
            return $read;
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::maskText( $e->getMessage(), $plan['refs'], $plan['password'] ) );
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( self::maskText( 'MongoDB: ' . $e->getMessage(), $plan['refs'], $plan['password'] ) );
        }
        finally
        {
            if ( $transport )
                $transport->close();
        }
    }

    // ---- flattening and types ----------------------------------------------------------------------------------------

    /**
     * A document as one level of fields: nested documents as dotted names (author.name), arrays as JSON or a joined
     * list, BSON values as text (value()).
     *
     * @param array $document
     * @param string $arrays json or join
     * @param string $separator
     * @return array field => string, int, float, bool or null
     */
    public static function flatten( array $document, $arrays = 'json', $separator = ', ' )
    {
        $out = array();
        foreach ( $document as $key => $value )
            self::flattenInto( $out, (string)$key, $value, $arrays, (string)$separator, 1 );
        return $out;
    }

    /** Adds a value under its dotted name. */
    protected static function flattenInto( array &$out, $name, $value, $arrays, $separator, $depth )
    {
        $value = self::value( $value );
        if ( !is_array( $value ) )
        {
            $out[$name] = $value;
            return;
        }
        if ( self::isList( $value ) || $depth >= self::MAX_DEPTH )
        {
            $plain = self::plain( $value );
            if ( $arrays === 'join' && self::isList( $plain ) && !array_filter( $plain, 'is_array' ) )
                $out[$name] = implode( $separator, array_map( function ( $item )
                {
                    return is_bool( $item ) ? ( $item ? 'true' : 'false' ) : (string)$item;
                }, $plain ) );
            else
                $out[$name] = (string)json_encode( $plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR );
            return;
        }
        foreach ( $value as $key => $item )
            self::flattenInto( $out, $name . '.' . $key, $item, $arrays, $separator, $depth + 1 );
    }

    /** @return mixed a value with its BSON values (at any depth) as text, and documents as arrays */
    public static function plain( $value )
    {
        $value = self::value( $value );
        if ( is_array( $value ) )
            foreach ( $value as $key => $item )
                $value[$key] = self::plain( $item );
        return $value;
    }

    /** @return bool whether an array is a list (0, 1, 2 ...); an empty array counts as one */
    protected static function isList( array $value )
    {
        return $value === array() || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    /**
     * One BSON value as JSON can hold it:
     *   ObjectId     its 24 hex digits
     *   UTCDateTime  ISO 8601 in UTC (2026-10-22T10:11:12Z, with milliseconds when it has them)
     *   Decimal128   its decimal string (12.50), never a float
     *   Binary       base64; a UUID (subtype 3 or 4) as its 8-4-4-4-12 form
     *   Int64        an int (a string where PHP's int is smaller)
     *   Timestamp    ISO 8601 of its seconds
     *   Regex        /pattern/flags; Javascript its code; MinKey, MaxKey their names
     *   Document, PackedArray, stdClass  arrays (flattened by the caller)
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value( $value )
    {
        if ( !is_object( $value ) )
            return is_float( $value ) && !is_finite( $value ) ? (string)$value : $value;
        if ( $value instanceof MongoDB\BSON\ObjectId )
            return (string)$value;
        if ( $value instanceof MongoDB\BSON\UTCDateTime )
            return self::iso( $value->toDateTime() );
        if ( $value instanceof MongoDB\BSON\Decimal128 )
            return (string)$value;
        if ( $value instanceof MongoDB\BSON\Binary )
        {
            $data = (string)$value->getData();
            if ( in_array( (int)$value->getType(), array( 3, 4 ), true ) && strlen( $data ) === 16 )
            {
                $hex = bin2hex( $data );
                return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
            }
            return base64_encode( $data );
        }
        if ( $value instanceof MongoDB\BSON\Int64 )
        {
            $text = (string)$value;
            return (string)(int)$text === $text ? (int)$text : $text;
        }
        if ( $value instanceof MongoDB\BSON\Timestamp )
            return gmdate( 'Y-m-d\TH:i:s\Z', (int)$value->getTimestamp() );
        if ( $value instanceof MongoDB\BSON\Regex )
            return '/' . $value->getPattern() . '/' . $value->getFlags();
        if ( $value instanceof MongoDB\BSON\Javascript )
            return (string)$value->getCode();
        if ( $value instanceof MongoDB\BSON\MinKey )
            return 'MinKey';
        if ( $value instanceof MongoDB\BSON\MaxKey )
            return 'MaxKey';
        if ( ( $value instanceof MongoDB\BSON\Document || $value instanceof MongoDB\BSON\PackedArray ) && method_exists( $value, 'toPHP' ) )
            return (array)$value->toPHP( expImportMongoDbExtension::TYPE_MAP );
        if ( $value instanceof DateTimeInterface )
            return self::iso( $value );
        if ( $value instanceof JsonSerializable )
            return self::plain( $value->jsonSerialize() );
        if ( method_exists( $value, '__toString' ) )
            return (string)$value;
        return get_object_vars( $value );
    }

    /** @return string a date in ISO 8601 UTC, with milliseconds only when it has them */
    protected static function iso( DateTimeInterface $date )
    {
        $utc = new DateTime( '@' . $date->format( 'U' ) );
        $ms = (int)$date->format( 'v' );
        return $utc->format( 'Y-m-d\TH:i:s' ) . ( $ms ? sprintf( '.%03d', $ms ) : '' ) . 'Z';
    }

    /** @return string a message without the password (by its secret name, and the value itself) */
    protected static function maskText( $text, array $refs, $password )
    {
        $text = expImportSecrets::mask( (string)$text, $refs );
        if ( strlen( (string)$password ) >= 3 )
            $text = str_replace( (string)$password, '[password]', $text );
        return $text;
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /** @return string the version of this PHP's extension mongodb, '' when it has none */
    protected static function extensionVersion()
    {
        if ( is_array( self::$environment ) && array_key_exists( 'mongodb', self::$environment ) )
            return (string)self::$environment['mongodb'];
        return expImportMongoDbExtension::available() ? (string)phpversion( 'mongodb' ) : '';
    }

    /** @return bool */
    protected static function hasExtension()
    {
        return self::extensionVersion() !== '';
    }

    /** @return array driver => the way this PHP has it ('' none), for the job queue's workers (recordWorkerPhp()) */
    public static function localDrivers()
    {
        $version = self::extensionVersion();
        return array( 'mongodb' => $version !== '' ? 'ext-mongodb ' . $version : '' );
    }

    /**
     * What this PHP can connect with, and what the job queue's workers last had (the editor's and the catalogue's
     * driver list).
     *
     * @return array driver => array( 'label', 'available' => bool, 'via', 'needs', 'worker' => yes, no or '', 'worker_php' )
     */
    public static function drivers()
    {
        $local = self::localDrivers();
        $worker = expImportSqlSource::workerPhp();
        $seen = $worker && isset( $worker['sources'][self::identifier()] ) && is_array( $worker['sources'][self::identifier()] ) ? $worker['sources'][self::identifier()] : null;
        return array(
            'mongodb' => array(
                'label' => 'MongoDB (3.6 or later)', 'via' => $local['mongodb'], 'available' => $local['mongodb'] !== '',
                'needs' => 'the PECL extension mongodb, 1.15 or later (RHEL: php-pecl-mongodb of Remi or pecl install mongodb; Debian: php-mongodb; '
                         . 'Plesk: pecl install mongodb for its PHP); the composer library mongodb/mongodb is not needed',
                'worker' => $seen !== null && isset( $seen['mongodb'] ) ? ( $seen['mongodb'] !== '' ? 'yes' : 'no' ) : '',
                'worker_php' => $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '',
            ),
        );
    }

    /** @return string why this PHP cannot read MongoDB, and whether the job queue's workers can */
    public static function missingDriverMessage()
    {
        $row = self::drivers()['mongodb'];
        $text = 'MongoDB: this PHP (' . PHP_VERSION . ', ' . PHP_BINARY . ') has no extension mongodb. ';
        if ( $row['worker'] === 'yes' )
            return $text . 'Runs still work: the job queue\'s workers read them, and their PHP (' . $row['worker_php'] . ') has it.';
        if ( $row['worker'] === 'no' )
            return $text . 'The job queue\'s workers (' . $row['worker_php'] . ') have none either; the host needs ' . $row['needs'] . '.';
        return $text . 'Runs are read by the job queue\'s workers, which may have it; otherwise the host needs ' . $row['needs'] . '.';
    }
}
