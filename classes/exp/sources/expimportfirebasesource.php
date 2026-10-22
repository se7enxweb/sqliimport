<?php
/**
 * File containing the expImportFirebaseSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The children of a path of a Firebase Realtime Database, read through its REST API (GET <path>.json). One record is
 * one child: _key (the child's key) and its value, nested objects flattened to dotted names (author.name); a child
 * that is a plain value gives _key and value.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the children in key order
 * (orderBy="$key" with limitToFirst and startAt) and writes them into the run's snapshot, one JSON object per line.
 * For a big tree it first asks for the keys only (shallow=true) and then reads the children a page of keys at a time
 * (startAt and endAt), so no request downloads the whole tree.
 *
 * Only reading is possible:
 *   - the only method is GET, and the only query parameters are orderBy, limitToFirst, startAt, endAt, shallow and the
 *     authentication; no PUT, PATCH, POST or DELETE is ever built
 *   - the database address must be a Firebase database (https://<name>.firebaseio.com or
 *     https://<name>.<region>.firebasedatabase.app); another host (the emulator) only when it is listed in
 *     [GoogleCloudSourceSettings] AllowedHosts[]
 *   - authentication by a database secret (legacy, the name of a secret) or a service account (expImportGoogleAuth)
 *     with the IAM role roles/firebasedatabase.viewer; the secret, the key and the token are masked in every message
 */
class expImportFirebaseSource extends expImportJsonSource
{
    /** The scopes of a service account's token for the Realtime Database. */
    const SCOPE = 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email';

    /** @var array|null what drivers() sees of this PHP (tests): allow_url_fopen, openssl */
    public static $environment = null;

    public static function identifier()
    {
        return 'firebase_rtdb';
    }

    public static function label()
    {
        return 'Firebase Realtime Database path';
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
        return 'The children of a path of a Firebase Realtime Database, read through its REST API in key order: one record '
             . 'is one child, _key with its value, nested objects flattened to dotted names. Only GET requests; a '
             . 'database secret or a service account key is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three articles under /articles as the source writes it (one child per line)',
                   'file' => 'firebase-articles.ndjson',
                   'options' => array( 'database_url' => 'https://example-app-default-rtdb.firebaseio.com', 'path' => 'articles',
                                       'auth' => 'service_account', 'credentials_secret' => 'firebase_reader' ) ),
            array( 'label' => 'The products of a European database, read with a legacy database secret, keys first (shallow)',
                   'options' => array( 'database_url' => 'https://example-shop.europe-west1.firebasedatabase.app', 'path' => 'catalog/products',
                                       'auth' => 'database_secret', 'database_secret' => 'rtdb_secret', 'shallow' => true, 'page_size' => 1000 ) ),
        );
    }

    public static function schema()
    {
        return array(
            'database_url' => array( 'type' => 'url', 'label' => 'Database address', 'required' => true,
                                     'help' => 'https://<name>.firebaseio.com or https://<name>.<region>.firebasedatabase.app (the emulator only with [GoogleCloudSourceSettings] AllowedHosts[])' ),
            'path' => array( 'type' => 'string', 'label' => 'Path', 'default' => '', 'max' => 768, 'pattern' => '/^[A-Za-z0-9_\/ :@+-]*$/',
                             'help' => 'The node whose children are the records: articles, catalog/products. Empty: the root' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'service_account',
                             'values' => array( 'service_account' => 'Service account (Google access token)', 'database_secret' => 'Database secret (legacy)',
                                                'none' => 'None (public read rules)' ) ),
            'credentials_secret' => array( 'type' => 'secret', 'label' => 'Service account key (name of a secret)',
                                           'help' => 'With a service account: the name of a secret whose value is its JSON key file (role roles/firebasedatabase.viewer)' ),
            'database_secret' => array( 'type' => 'secret', 'label' => 'Database secret (name of a secret)',
                                        'help' => 'With a database secret: the name of the secret that holds it' ),
            'shallow' => array( 'type' => 'bool', 'label' => 'Keys first (shallow)', 'default' => false,
                                'help' => 'Read the keys first (shallow=true), then the children a page at a time: for a big tree' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 1, 'max' => 10000, 'help' => 'Children per request' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most children', 'default' => 0, 'min' => 0, 'help' => '0: all' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'JSON (["a","b"])', 'join' => 'A joined list (a, b)' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'Separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_key', 'help' => '_key is the key of the child' ),
        );
    }

    /** The snapshot is always one child per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The address of the path, the query parameters and headers of the authentication, and the trusted hosts.
     *
     * @param array $options
     * @param string[] $used the secrets used
     * @return array ( string address up to .json, array query parameters, array headers, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $url = rtrim( isset( $options['database_url'] ) ? trim( (string)$options['database_url'] ) : '', '/' );
        $parts = parse_url( $url );
        if ( $url === '' || !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            throw new expImportSourceException( 'Firebase: the database address must be an https address' );
        if ( isset( $parts['path'] ) && $parts['path'] !== '' && $parts['path'] !== '/' || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) )
            throw new expImportSourceException( 'Firebase: the database address is the address of the database only (the path is an option of its own)' );
        $params = array();
        if ( isset( $parts['query'] ) )
        {
            // the emulator names the database with ?ns=<name>
            if ( !preg_match( '/^ns=([a-z0-9-]{1,100})$/', (string)$parts['query'], $m ) )
                throw new expImportSourceException( 'Firebase: the database address may only have the query ns=<name> (the emulator)' );
            $params['ns'] = $m[1];
        }
        $host = strtolower( (string)$parts['host'] );
        $trusted = (array)expImportPlatformHttp::setting( 'GoogleCloudSourceSettings', 'AllowedHosts', array() );
        $listed = false;
        foreach ( $trusted as $allowed )
            if ( strcasecmp( trim( (string)$allowed, ' []' ), $host ) === 0 )
                $listed = true;
        if ( !$listed )
        {
            if ( !preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)?\.(firebaseio\.com|firebasedatabase\.app)$/', $host ) || strtolower( $parts['scheme'] ) !== 'https' )
                throw new expImportSourceException( 'Firebase: the address is not a Firebase Realtime Database (https://<name>.firebaseio.com or https://<name>.<region>.firebasedatabase.app; another host only from [GoogleCloudSourceSettings] AllowedHosts[])' );
            // a Firebase host: every request still goes through the address check of expImportPlatformHttp
        }
        $path = trim( isset( $options['path'] ) ? (string)$options['path'] : '', ' /' );
        if ( !preg_match( '/^[A-Za-z0-9_\/ :@+-]*$/', $path ) || preg_match( '#(^|/)\.\.?(/|$)#', $path ) )
            throw new expImportSourceException( 'Firebase: the path is keys separated by / (no . $ # [ ])' );
        $address = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . '/'
                 . ( $path === '' ? '' : implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) ) . '.json';
        $headers = array();
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'service_account';
        if ( $auth === 'database_secret' )
        {
            $ref = isset( $options['database_secret'] ) ? trim( (string)$options['database_secret'] ) : '';
            $used[] = $ref;
            $params['auth'] = expImportSecrets::required( $ref, 'Firebase: the database secret' );
        }
        elseif ( $auth === 'service_account' )
        {
            $ref = isset( $options['credentials_secret'] ) ? trim( (string)$options['credentials_secret'] ) : '';
            $used[] = $ref;
            $headers['Authorization'] = 'Bearer ' . expImportGoogleAuth::accessToken( $ref, self::SCOPE, 'Firebase' );
        }
        return array( $address, $params, $headers, $trusted );
    }

    /**
     * One GET of the path with query parameters.
     *
     * @param array $connection connection()
     * @param array $query name => value (JSON values already encoded: orderBy => "\"$key\"")
     * @return mixed the decoded answer (an array, or a plain value of a leaf)
     * @throws expImportSourceException
     */
    protected static function get( array $connection, array $query )
    {
        list( $address, $params, $headers, $trusted ) = $connection;
        $all = $query + $params;
        foreach ( array_keys( $all ) as $name )
            if ( !in_array( $name, array( 'orderBy', 'limitToFirst', 'startAt', 'endAt', 'shallow', 'auth', 'ns' ), true ) )
                throw new expImportSourceException( "Firebase: the parameter $name is not one the source sends" );
        $url = $address . ( $all ? '?' . http_build_query( $all, '', '&', PHP_QUERY_RFC3986 ) : '' );
        $answer = expImportPlatformHttp::request( 'GET', $url, $headers + array( 'Accept' => 'application/json' ), null, $trusted );
        $status = (int)$answer['status'];
        $data = json_decode( (string)$answer['body'], true );
        if ( $status < 200 || $status >= 300 )
        {
            $reason = expImportCloudHttp::reason( $data );
            if ( $status === 401 || $status === 403 )
                $reason = 'the access was refused (check the authentication and the database rules or the role of the account)' . ( $reason !== '' ? '; ' . $reason : '' );
            throw new expImportSourceException( 'Firebase: ' . expImportPlatformHttp::shown( $url ) . " answered $status" . ( $reason !== '' ? ': ' . substr( $reason, 0, 300 ) : '' ) );
        }
        if ( $data === null && strtolower( trim( (string)$answer['body'] ) ) !== 'null' )
            throw new expImportSourceException( 'Firebase: ' . expImportPlatformHttp::shown( $url ) . ' did not answer with JSON' );
        return $data;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the children and writes them into the snapshot, one JSON object per line.
     *
     * @param array $options
     * @param string $target
     * @param int $limit the editor's sample; 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::children( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'Firebase: the snapshot cannot be written' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportGoogleAuth::mask( $e->getMessage(), $used ) );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The children of the path as records, in key order, page by page.
     *
     * @param array $options
     * @param int $limit 0: all (else also the option limit)
     * @param string[] $used
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function children( array $options, $limit, array &$used )
    {
        $connection = self::connection( $options, $used );
        $max = isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0;
        if ( $limit > 0 && ( $max === 0 || $limit < $max ) )
            $max = (int)$limit;
        $size = max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 500 ) );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'GoogleCloudSourceSettings', 'MaxPages', 1000 ) );
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $orderBy = json_encode( '$key' );
        $n = 0;
        if ( !empty( $options['shallow'] ) )
        {
            $keys = self::get( $connection, array( 'shallow' => 'true' ) );
            if ( !is_array( $keys ) )
            {
                if ( $keys !== null )
                    yield self::record( self::leafKey( $options ), $keys, $arrays, $separator );
                return;
            }
            $keys = array_map( 'strval', array_keys( $keys ) );
            usort( $keys, array( __CLASS__, 'compareKeys' ) );
            if ( $max > 0 )
                $keys = array_slice( $keys, 0, $max );
            $pages = array_chunk( $keys, $size );
            if ( count( $pages ) > $maxPages )
                throw new expImportSourceException( 'Firebase: the path has more pages than [GoogleCloudSourceSettings] MaxPages (' . $maxPages . ')' );
            foreach ( $pages as $page )
            {
                $data = self::get( $connection, array( 'orderBy' => $orderBy, 'startAt' => json_encode( (string)reset( $page ) ),
                                                       'endAt' => json_encode( (string)end( $page ) ) ) );
                $data = is_array( $data ) ? $data : array();
                foreach ( $page as $key )
                {
                    // a child removed between the two requests is left out
                    if ( !isset( $data[$key] ) )
                        continue;
                    yield self::record( $key, $data[$key], $arrays, $separator );
                    if ( $max > 0 && ++$n >= $max )
                        return;
                }
            }
            return;
        }
        $last = null;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $want = $max > 0 ? min( $size, $max - $n ) : $size;
            $query = array( 'orderBy' => $orderBy, 'limitToFirst' => (string)( $last === null ? $want : $want + 1 ) );
            if ( $last !== null )
                $query['startAt'] = json_encode( $last );
            $data = self::get( $connection, $query );
            if ( !is_array( $data ) )
            {
                if ( $data !== null && $last === null )
                    yield self::record( self::leafKey( $options ), $data, $arrays, $separator );
                return;
            }
            $keys = array_map( 'strval', array_keys( $data ) );
            usort( $keys, array( __CLASS__, 'compareKeys' ) );
            $got = 0;
            foreach ( $keys as $key )
            {
                if ( $last !== null && $key === $last )
                    continue;
                $got++;
                $value = $data[$key];
                $last = $key;
                // an answer as a JSON array (keys 0, 1, 2 ...) has null where a key is missing
                if ( $value === null )
                    continue;
                yield self::record( $key, $value, $arrays, $separator );
                if ( $max > 0 && ++$n >= $max )
                    return;
            }
            if ( $got < $want )
                return;
        }
        throw new expImportSourceException( 'Firebase: the path has more pages than [GoogleCloudSourceSettings] MaxPages (' . $maxPages . ')' );
    }

    /** @return string the key of the path itself (its last part), for a path that holds a plain value */
    protected static function leafKey( array $options )
    {
        $path = trim( isset( $options['path'] ) ? (string)$options['path'] : '', ' /' );
        $at = strrpos( $path, '/' );
        return $at === false ? $path : (string)substr( $path, $at + 1 );
    }

    /**
     * The order of keys of orderBy="$key": keys that are 32-bit integers first, by their value, then the others by
     * their text.
     *
     * @return int
     */
    public static function compareKeys( $a, $b )
    {
        $a = (string)$a;
        $b = (string)$b;
        $ia = preg_match( '/^-?(0|[1-9]\d{0,9})$/', $a ) && (int)$a >= -2147483648 && (int)$a <= 2147483647;
        $ib = preg_match( '/^-?(0|[1-9]\d{0,9})$/', $b ) && (int)$b >= -2147483648 && (int)$b <= 2147483647;
        if ( $ia && $ib )
            return (int)$a <=> (int)$b;
        if ( $ia !== $ib )
            return $ia ? -1 : 1;
        return strcmp( $a, $b );
    }

    /**
     * A child as a record: _key, and its value flattened (an object), or value (a plain value).
     *
     * @param string $key
     * @param mixed $value
     * @param string $arrays json or join
     * @param string $separator
     * @return array
     */
    public static function record( $key, $value, $arrays = 'json', $separator = ', ' )
    {
        $record = array( '_key' => (string)$key );
        $fields = is_array( $value ) ? $value : array( 'value' => $value );
        foreach ( expImportMongoDbSource::flatten( $fields, $arrays, $separator ) as $name => $item )
            if ( !array_key_exists( $name, $record ) )
                $record[$name] = $item;
        return $record;
    }

    /**
     * The editor's "Test connection": the number of children (shallow) and the first child, whose fields are
     * reported, or why it failed (the secret, the key and the token masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'Firebase Realtime Database REST API';
        try
        {
            $connection = self::connection( $options, $used );
            $keys = self::get( $connection, array( 'shallow' => 'true' ) );
            $count = is_array( $keys ) ? count( $keys ) : ( $keys === null ? 0 : 1 );
            $records = array();
            foreach ( self::children( array( 'limit' => 1, 'page_size' => 1, 'shallow' => false ) + $options, 1, $used ) as $record )
                $records[] = $record;
            $columns = $records ? array_map( 'strval', array_keys( $records[0] ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $records ), 'driver' => $driver,
                          'message' => 'connected; the path has ' . $count . ' children' . ( $records ? '; the first gives these fields' : '' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportGoogleAuth::mask( $e->getMessage(), $used ) );
        }
    }

    /** @return array https => array( 'label', 'available', 'via', 'needs', 'worker', 'worker_php' ) */
    public static function drivers()
    {
        return expImportCloudHttp::drivers( self::$environment, 'Firebase Realtime Database REST API' );
    }
}
