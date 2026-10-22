<?php
/**
 * File containing the expImportRedisSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The keys of a Redis database that match a pattern: one record is one key, with _key (its name), _type and _ttl, and
 * its value by type: a hash's fields as fields, a string as value (decoded when it is JSON), the values of a list,
 * set or sorted set (with their scores), the entries of a stream.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() walks the keys with SCAN (MATCH and COUNT,
 * never KEYS) and writes them into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible: after AUTH and SELECT the only commands sent are SCAN, TYPE, TTL, HGETALL, GET, LRANGE,
 * SMEMBERS, ZRANGE WITHSCORES and XRANGE (expImportRedisTransport::READ_COMMANDS; each transport refuses any other).
 * The password is the name of a secret (expImportSecrets) and masked in every message; a server in a private network
 * is refused unless [RedisSourceSettings] AllowedHosts[] lists it. The PHP extension redis is used where it is
 * installed, else a small RESP client over a socket (driver "socket").
 */
class expImportRedisSource extends expImportJsonSource
{
    /** The types the source reads, as TYPE names them. */
    const TYPES = array( 'hash', 'string', 'list', 'set', 'zset', 'stream' );

    /** The fields every record has; a hash field of the same name is left out. */
    const RESERVED = array( '_key', '_type', '_ttl' );

    /**
     * What drivers() sees of this PHP, for the tests: array( 'redis' => string the version of the extension, '' none ).
     * null: this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    /**
     * Makes the connection instead of the extension or the socket client, for the tests: function () returning an
     * expImportRedisTransport. null: by [RedisSourceSettings] Client.
     *
     * @var callable|null
     */
    public static $transportFactory = null;

    public static function identifier()
    {
        return 'redis';
    }

    public static function label()
    {
        return 'Redis keys';
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

    public static function description()
    {
        return 'The keys of a Redis database that match a pattern (a cache of product data, a store of another application): '
             . 'one record is one key with _key, _type and _ttl, a hash\'s fields as fields, a string as value (decoded when '
             . 'it is JSON), the values of a list, set or sorted set, the entries of a stream. The keys are walked with SCAN, '
             . 'only reading commands are sent; the password is the name of a secret in the settings. Uses the PHP extension '
             . 'redis where it is installed, else a small client of its own.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'redis-products.ndjson: what a run reads from the keys product:* (hashes, a JSON string, a set), to try the mapping',
                   'file' => 'redis-products.ndjson' ),
            array( 'label' => 'The product hashes product:* of database 2 on redis.example.org (TLS, port 6380), user reader, the password as the secret cache_redis',
                   'options' => array( 'host' => 'redis.example.org', 'port' => 6380, 'tls' => true, 'database' => 2, 'user' => 'reader',
                                       'password' => 'cache_redis', 'pattern' => 'product:*', 'types' => 'hash', 'key' => '_key' ) ),
            array( 'label' => 'The events of the stream events:site on redis.example.org, the first 1000 entries, no password',
                   'options' => array( 'host' => 'redis.example.org', 'port' => 6379, 'pattern' => 'events:site', 'types' => 'stream',
                                       'max_items' => 1000, 'arrays' => 'keep' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'host' => array( 'type' => 'string', 'label' => 'Redis server', 'required' => true, 'max' => 255,
                             'help' => 'e.g. redis.example.org (a private network only with [RedisSourceSettings] AllowedHosts[])' ),
            'port' => array( 'type' => 'int', 'label' => 'Port', 'default' => 6379, 'min' => 1, 'max' => 65535 ),
            'tls' => array( 'type' => 'bool', 'label' => 'TLS', 'default' => false, 'help' => 'Encrypt the connection (the certificate is verified)' ),
            'database' => array( 'type' => 'int', 'label' => 'Database index', 'default' => 0, 'min' => 0, 'max' => 1024 ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'An ACL user (Redis 6 and later); empty: only the password' ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password; empty: no AUTH' ),
            'pattern' => array( 'type' => 'string', 'label' => 'Key pattern', 'default' => '*', 'max' => 1024, 'raw' => true,
                                'help' => 'The keys to read, as SCAN MATCH reads it: product:*, user:?, news:[0-9]*' ),
            'types' => array( 'type' => 'string', 'label' => 'Types', 'default' => 'hash,string,list,set,zset,stream', 'max' => 100,
                              'pattern' => '/^[a-z, ]*$/', 'help' => 'The types of keys to read, separated by commas: hash, string, list, set, zset, stream' ),
            'scan_count' => array( 'type' => 'int', 'label' => 'Keys per SCAN', 'default' => 100, 'min' => 1, 'max' => 10000,
                                   'help' => 'The COUNT of each SCAN: a hint how many keys the server looks at per step' ),
            'max_items' => array( 'type' => 'int', 'label' => 'Most values per key', 'default' => 1000, 'min' => 1, 'max' => 100000,
                                  'help' => 'Lists, sets, sorted sets and streams: how many values (entries) of a key are read' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most keys', 'default' => 0, 'min' => 0, 'help' => '0: every matching key' ),
            'decode_json' => array( 'type' => 'bool', 'label' => 'Decode JSON strings', 'default' => true,
                                    'help' => 'A string value that is a JSON object or list is read as its fields (value.title)' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Lists', 'default' => 'keep',
                               'values' => array( 'keep' => 'As lists (values.0, values.1 ...)', 'json' => 'As JSON (["a","b"])',
                                                  'join' => 'As a joined list (a, b); stream entries stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_key', 'help' => 'The field that names a record; _key is the key itself' ),
        );
    }

    /** The snapshot is always one key per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * The transport: [RedisSourceSettings] Client (auto: the extension redis where it is installed, else the socket
     * client; phpredis; socket).
     *
     * @return expImportRedisTransport
     * @throws expImportSourceException
     */
    public static function transport()
    {
        if ( self::$transportFactory )
        {
            $transport = call_user_func( self::$transportFactory );
            if ( $transport instanceof expImportRedisTransport )
                return $transport;
        }
        $client = (string)expImport::setting( 'RedisSourceSettings', 'Client', 'auto' );
        if ( $client === 'socket' )
            return new expImportRedisClient();
        if ( self::extensionVersion() !== '' )
            return new expImportRedisExtension();
        if ( $client === 'phpredis' )
            throw new expImportSourceException( 'Redis: [RedisSourceSettings] Client=phpredis, but this PHP (' . PHP_VERSION . ') has no extension redis' );
        return new expImportRedisClient();
    }

    /**
     * Reads the matching keys into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most keys to write (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $out = fopen( $tmp, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'Redis: the snapshot cannot be written' );
        try
        {
            self::keys( $options, $limit, 0, function ( array $row ) use ( $out )
            {
                fwrite( $out, (string)json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n" );
            } );
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'Redis: the snapshot cannot be written' );
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
     * The editor's "Test connection": connects, looks for the first matching key (at most [RedisSourceSettings]
     * TestScanRounds steps of SCAN) and reports its fields, or why it failed (never a password). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $rows = 0;
        $columns = array();
        $driver = '';
        $rounds = max( 1, (int)expImport::setting( 'RedisSourceSettings', 'TestScanRounds', 50 ) );
        try
        {
            $driver = self::keys( $options, 1, $rounds, function ( array $row ) use ( &$rows, &$columns )
            {
                $rows++;
                if ( !$columns )
                    $columns = array_keys( $row );
            } );
            return array( 'ok' => true, 'columns' => array_values( array_map( 'strval', $columns ) ), 'rows' => $rows, 'driver' => $driver,
                          'message' => $rows ? 'connected, the first matching key has these fields'
                                             : "connected; no key of the chosen types matches the pattern (in the first $rounds steps of SCAN)" );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver !== '' ? $driver : self::plannedDriver(), 'message' => $e->getMessage() );
        }
    }

    /**
     * Connects, walks the matching keys with SCAN and hands each one as a record to $each.
     *
     * @param array $options
     * @param int $limit the most keys (with the option limit, the smaller); 0: all
     * @param int $rounds the most steps of SCAN; 0: until the cursor is back at 0
     * @param callable $each function ( array $row )
     * @return string the driver that was used
     * @throws expImportSourceException
     */
    protected static function keys( array $options, $limit, $rounds, callable $each )
    {
        $host = isset( $options['host'] ) ? trim( (string)$options['host'] ) : '';
        $refusal = expImportSourceFiles::refuseHost( $host, (array)expImport::setting( 'RedisSourceSettings', 'AllowedHosts', array() ) );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Redis: ' . $refusal . ' (or list it in sqliimport.ini [RedisSourceSettings] AllowedHosts[])' );
        $host = trim( $host, '[]' );
        $types = self::typesOf( isset( $options['types'] ) ? (string)$options['types'] : '' );
        $pattern = isset( $options['pattern'] ) && (string)$options['pattern'] !== '' ? (string)$options['pattern'] : '*';
        $count = isset( $options['scan_count'] ) ? max( 1, min( 10000, (int)$options['scan_count'] ) ) : 100;
        $max = isset( $options['max_items'] ) ? max( 1, (int)$options['max_items'] ) : 1000;
        $limit = (int)$limit;
        if ( !empty( $options['limit'] ) )
            $limit = $limit > 0 ? min( $limit, (int)$options['limit'] ) : (int)$options['limit'];
        $ref = isset( $options['password'] ) ? trim( (string)$options['password'] ) : '';
        $refs = $ref !== '' ? array( $ref ) : array();
        $password = $ref !== '' ? expImportSecrets::required( $ref, 'Redis password' ) : '';
        $seconds = max( 1, (int)expImport::setting( 'RedisSourceSettings', 'Timeout', 30 ) );
        $maxKeys = max( 1, (int)expImport::setting( 'RedisSourceSettings', 'MaxScannedKeys', 10000000 ) );
        $transport = null;
        try
        {
            $transport = self::transport();
            $transport->open( $host, isset( $options['port'] ) ? (int)$options['port'] : 6379, !empty( $options['tls'] ),
                              isset( $options['user'] ) ? trim( (string)$options['user'] ) : '', $password,
                              isset( $options['database'] ) ? (int)$options['database'] : 0, $seconds );
            $cursor = '0';
            $read = 0;
            $scanned = 0;
            $round = 0;
            $seen = array();
            do
            {
                $answer = $transport->command( array( 'SCAN', $cursor, 'MATCH', $pattern, 'COUNT', (string)$count ) );
                if ( !is_array( $answer ) || count( $answer ) !== 2 || !is_array( $answer[1] ) )
                    throw new expImportSourceException( 'Redis: SCAN gave an answer of an unknown form' );
                $cursor = (string)$answer[0];
                $round++;
                foreach ( $answer[1] as $key )
                {
                    $key = (string)$key;
                    // SCAN may give a key twice
                    $id = strlen( $key ) > 64 ? sha1( $key ) : $key;
                    if ( isset( $seen[$id] ) )
                        continue;
                    $seen[$id] = true;
                    if ( ++$scanned > $maxKeys )
                        throw new expImportSourceException( "Redis: more than $maxKeys matching keys ([RedisSourceSettings] MaxScannedKeys); narrow the pattern" );
                    $record = self::record( $transport, $key, $types, $max, $options );
                    if ( $record === null )
                        continue;
                    $each( $record );
                    $read++;
                    if ( $limit > 0 && $read >= $limit )
                        break 2;
                }
            }
            while ( $cursor !== '0' && ( $rounds <= 0 || $round < $rounds ) );
            return $transport->driver();
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::maskText( $e->getMessage(), $refs, $password ) );
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( self::maskText( 'Redis: ' . $e->getMessage(), $refs, $password ) );
        }
        finally
        {
            if ( $transport )
                $transport->close();
        }
    }

    /**
     * The types of the option (hash, string, list, set, zset, stream); all when empty.
     *
     * @param string $text
     * @return string[]
     * @throws expImportSourceException
     */
    public static function typesOf( $text )
    {
        $types = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( (string)$text ) ) ), 'strlen' ) ) );
        foreach ( $types as $type )
            if ( !in_array( $type, self::TYPES, true ) )
                throw new expImportSourceException( "Redis: the type $type cannot be read (" . implode( ', ', self::TYPES ) . ')' );
        return $types ?: self::TYPES;
    }

    /**
     * One key as a record, or null when it is of another type or gone.
     *
     * @param expImportRedisTransport $transport
     * @param string $key
     * @param string[] $types
     * @param int $max the most values of a list, set, sorted set or stream
     * @param array $options decode_json, arrays, separator
     * @return array|null
     * @throws expImportSourceException
     */
    public static function record( expImportRedisTransport $transport, $key, array $types, $max, array $options )
    {
        $type = strtolower( (string)$transport->command( array( 'TYPE', $key ) ) );
        if ( !in_array( $type, $types, true ) )
            return null;
        $ttl = $transport->command( array( 'TTL', $key ) );
        $record = array( '_key' => self::text( $key ), '_type' => $type, '_ttl' => is_numeric( $ttl ) ? (int)$ttl : -1 );
        $arrays = isset( $options['arrays'] ) && in_array( $options['arrays'], array( 'json', 'join' ), true ) ? (string)$options['arrays'] : 'keep';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $last = (string)( $max - 1 );
        switch ( $type )
        {
            case 'hash':
                $pairs = $transport->command( array( 'HGETALL', $key ) );
                if ( !is_array( $pairs ) || !$pairs )
                    return null;
                for ( $i = 0, $n = count( $pairs ) - 1; $i < $n; $i += 2 )
                {
                    $field = self::text( (string)$pairs[$i] );
                    if ( !in_array( $field, self::RESERVED, true ) )
                        $record[$field] = self::text( (string)$pairs[$i + 1] );
                }
                return $record;
            case 'string':
                $value = $transport->command( array( 'GET', $key ) );
                if ( $value === null )
                    return null;
                $value = (string)$value;
                if ( ( !isset( $options['decode_json'] ) || !empty( $options['decode_json'] ) ) && preg_match( '/^\s*[\[{]/', $value ) )
                {
                    $data = json_decode( $value, true, 64 );
                    if ( is_array( $data ) )
                    {
                        $record['value'] = $data;
                        return $record;
                    }
                }
                $record['value'] = self::text( $value );
                return $record;
            case 'list':
                $values = $transport->command( array( 'LRANGE', $key, '0', $last ) );
                $record['values'] = self::listValue( is_array( $values ) ? array_map( array( __CLASS__, 'text' ), array_map( 'strval', $values ) ) : array(), $arrays, $separator );
                return $record;
            case 'set':
                $values = $transport->command( array( 'SMEMBERS', $key ) );
                $values = is_array( $values ) ? array_map( 'strval', $values ) : array();
                // SMEMBERS has no order: sorted, so two runs give the same record
                sort( $values, SORT_STRING );
                $record['values'] = self::listValue( array_map( array( __CLASS__, 'text' ), array_slice( $values, 0, $max ) ), $arrays, $separator );
                return $record;
            case 'zset':
                $pairs = $transport->command( array( 'ZRANGE', $key, '0', $last, 'WITHSCORES' ) );
                $members = array();
                $scores = array();
                for ( $i = 0, $n = is_array( $pairs ) ? count( $pairs ) - 1 : 0; $i < $n; $i += 2 )
                {
                    $members[] = self::text( (string)$pairs[$i] );
                    $scores[] = is_numeric( $pairs[$i + 1] ) ? (float)$pairs[$i + 1] : (string)$pairs[$i + 1];
                }
                $record['values'] = self::listValue( $members, $arrays, $separator );
                $record['scores'] = self::listValue( $scores, $arrays, $separator );
                return $record;
            case 'stream':
                $entries = $transport->command( array( 'XRANGE', $key, '-', '+', 'COUNT', (string)$max ) );
                $list = array();
                foreach ( is_array( $entries ) ? $entries : array() as $entry )
                {
                    if ( !is_array( $entry ) || count( $entry ) < 2 )
                        continue;
                    $row = array( 'id' => (string)$entry[0] );
                    $fields = is_array( $entry[1] ) ? $entry[1] : array();
                    for ( $i = 0, $n = count( $fields ) - 1; $i < $n; $i += 2 )
                        if ( (string)$fields[$i] !== 'id' )
                            $row[self::text( (string)$fields[$i] )] = self::text( (string)$fields[$i + 1] );
                    $list[] = $row;
                }
                $record['entries'] = self::listValue( $list, $arrays, $separator );
                return $record;
        }
        return null;
    }

    /** @return mixed a list as the option Lists says: the list, JSON, or joined (a list of lists as JSON) */
    protected static function listValue( array $values, $arrays, $separator )
    {
        if ( $arrays === 'keep' )
            return $values;
        if ( $arrays === 'join' && !array_filter( $values, 'is_array' ) )
            return implode( $separator, array_map( 'strval', $values ) );
        return (string)json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }

    /** @return string a value as text: UTF-8 as it is, binary data as base64:... */
    public static function text( $value )
    {
        $value = (string)$value;
        return preg_match( '//u', $value ) ? $value : 'base64:' . base64_encode( $value );
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

    /** @return string the version of this PHP's extension redis, '' when it has none */
    protected static function extensionVersion()
    {
        if ( is_array( self::$environment ) && array_key_exists( 'redis', self::$environment ) )
            return (string)self::$environment['redis'];
        return expImportRedisExtension::available() ? (string)phpversion( 'redis' ) : '';
    }

    /** @return string the driver transport() picks in this PHP: phpredis or socket */
    protected static function plannedDriver()
    {
        $client = (string)expImport::setting( 'RedisSourceSettings', 'Client', 'auto' );
        return $client !== 'socket' && self::extensionVersion() !== '' ? 'phpredis' : 'socket';
    }

    /** @return array driver => the way this PHP has it ('' none), for the job queue's workers (recordWorkerPhp()) */
    public static function localDrivers()
    {
        $version = self::extensionVersion();
        return array( 'phpredis' => $version !== '' ? 'ext-redis ' . $version : '',
                      'socket' => function_exists( 'stream_socket_client' ) ? 'stream_socket_client' : '' );
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
        $rows = array(
            'phpredis' => array( 'label' => 'Redis through the PHP extension redis (phpredis)',
                                 'needs' => 'the PECL extension redis (RHEL: php-pecl-redis of Remi or pecl install redis; Debian: php-redis; Plesk: pecl install redis for its PHP); optional, the built-in client works without it' ),
            'socket' => array( 'label' => 'Redis through the built-in client (RESP over a socket)',
                               'needs' => 'stream_socket_client (part of PHP), with openssl for TLS' ),
        );
        foreach ( $rows as $id => $row )
        {
            $rows[$id]['via'] = $local[$id];
            $rows[$id]['available'] = $local[$id] !== '';
            $rows[$id]['worker'] = $seen !== null && isset( $seen[$id] ) ? ( $seen[$id] !== '' ? 'yes' : 'no' ) : '';
            $rows[$id]['worker_php'] = $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '';
        }
        return $rows;
    }
}
