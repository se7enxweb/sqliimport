<?php
/**
 * File containing the expImportSnowflakeSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a query against Snowflake, read through the SQL API v2 (POST /api/v2/statements) with key-pair
 * authentication: a JWT signed with the user's private key (RS256, openssl), whose issuer names the fingerprint of the
 * public key. One record is one row; the values are typed by the result's rowType (see value()).
 *
 * Only reading is possible as far as the source can see: checkQuery() accepts a single SELECT, WITH, SHOW or
 * DESCRIBE and refuses, outside string literals, quoted names and comments, every statement that writes or changes
 * the session (INSERT, UPDATE, DELETE, MERGE, CREATE, ALTER, DROP, COPY, CALL, EXECUTE, USE, SET ...) and the
 * SYSTEM$ functions; the request allows one statement (MULTI_STATEMENT_COUNT 1). Snowflake has no read-only session:
 * give the user a role with SELECT on what the import reads and nothing more. The private key and its passphrase are
 * names of secrets (expImportSecrets).
 *
 * Paging: the partitions of the result (resultSetMetaData.partitionInfo), the first in the answer, the others with
 * GET /api/v2/statements/<handle>?partition=<n>. A statement still running (202) is asked for again until it ends or
 * [SnowflakeSourceSettings] StatementTimeout passes.
 */
class expImportSnowflakeSource extends expImportHttpQuerySource
{
    const NAME = 'Snowflake';

    const SETTINGS = 'SnowflakeSourceSettings';

    const WORKER_PREFIX = 'snowflake';

    /** The statements and words that write, change the session or run code. */
    const FORBIDDEN = array(
        'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'GRANT', 'REVOKE', 'COPY', 'CALL', 'EXECUTE',
        'EXEC', 'USE', 'SET', 'UNSET', 'UNDROP', 'COMMIT', 'ROLLBACK', 'BEGIN', 'DECLARE', 'INTO', 'PUT', 'REMOVE', 'RM',
        'ABORT', 'KILL', 'UNLOAD', 'IMMEDIATE',
    );

    /**
     * Replaces sleeping between two polls of a running statement (tests): a callable( int $seconds ).
     *
     * @var callable|null
     */
    public static $sleeper = null;

    /** @var int|null the time of the JWT (tests); null: now */
    public static $now = null;

    public static function identifier()
    {
        return 'snowflake';
    }

    public static function label()
    {
        return 'Snowflake query';
    }

    public static function description()
    {
        return 'The rows of a SELECT (or SHOW, DESCRIBE) against Snowflake through its SQL API with key-pair authentication, '
             . 'read partition by partition, values typed by the result (numbers of FIXED as text, timestamps in ISO 8601, '
             . 'VARIANT, OBJECT and ARRAY decoded). Statements that write are refused; the private key is the name of a '
             . 'secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three orders as the source writes it (one row per line)',
                   'file' => 'snowflake-orders.ndjson',
                   'options' => array( 'account' => 'exampleorg-shop', 'user' => 'IMPORT_READER', 'private_key_secret' => 'snowflake_key',
                                       'query' => 'SELECT ORDER_ID, CUSTOMER_REF, TOTAL, ORDERED_AT, ITEMS FROM ORDERS ORDER BY ORDER_ID',
                                       'key' => 'ORDER_ID' ) ),
            array( 'label' => 'The orders of the last 30 days with warehouse, database, schema and role',
                   'options' => array( 'account' => 'exampleorg-shop', 'user' => 'IMPORT_READER', 'private_key_secret' => 'snowflake_key',
                                       'warehouse' => 'REPORTING_WH', 'database' => 'SHOP', 'schema' => 'PUBLIC', 'role' => 'IMPORT_READ',
                                       'query' => "SELECT ORDER_ID, TOTAL, ORDERED_AT\nFROM ORDERS\nWHERE ORDERED_AT >= DATEADD(day, -30, CURRENT_TIMESTAMP())\nORDER BY ORDER_ID",
                                       'key' => 'ORDER_ID' ) ),
        );
    }

    public static function schema()
    {
        $name = '/^([A-Za-z_][A-Za-z0-9_$]*|"[^"]+")?$/';
        return array(
            'account' => array( 'type' => 'string', 'label' => 'Account identifier', 'required' => true, 'max' => 255,
                                'pattern' => '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/',
                                'help' => 'orgname-accountname (or the account locator with its region, xy12345.eu-central-1)' ),
            'base_url' => array( 'type' => 'url', 'label' => 'Server address',
                                 'help' => 'Empty: https://<account>.snowflakecomputing.com; a private link address only with [SnowflakeSourceSettings] AllowedHosts[]' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9_.@$-]+$/' ),
            'private_key_secret' => array( 'type' => 'secret', 'label' => 'Private key (name of a secret)', 'required' => true,
                                           'help' => 'The name of the secret that holds the PEM private key (-----BEGIN PRIVATE KEY----- or ENCRYPTED PRIVATE KEY), never the key' ),
            'passphrase_secret' => array( 'type' => 'secret', 'label' => 'Passphrase of the key (name of a secret)',
                                          'help' => 'For an encrypted key: the name of the secret with its passphrase' ),
            'warehouse' => array( 'type' => 'string', 'label' => 'Warehouse', 'max' => 255, 'pattern' => $name ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'max' => 255, 'pattern' => $name ),
            'schema' => array( 'type' => 'string', 'label' => 'Schema', 'max' => 255, 'pattern' => $name ),
            'role' => array( 'type' => 'string', 'label' => 'Role', 'max' => 255, 'pattern' => $name,
                             'help' => 'A role with SELECT on what the import reads and nothing more' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'A single SELECT, WITH ... SELECT, SHOW or DESCRIBE' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'help' => 'The column that names a row (Snowflake names columns in upper case)' ),
        );
    }

    /**
     * Whether a query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $query )
    {
        try
        {
            list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, true ), '"' => array( 'name', false, true ) ),
                                                             'line' => array( '--', '//' ), 'block' => true, 'dollar' => 'plain' ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( self::hasSecondStatement( $stripped ) )
            return 'only one statement is allowed';
        $words = self::words( $stripped );
        if ( !$words || !in_array( $words[0], array( 'SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC' ), true ) )
            return 'only a SELECT, WITH ... SELECT, SHOW or DESCRIBE is allowed';
        $word = self::forbiddenWord( $stripped, self::FORBIDDEN );
        if ( $word !== null )
            return "the query contains $word: only reading is allowed";
        if ( preg_match( '/(?<![\w.])SYSTEM\s*\$/i', $stripped ) )
            return 'the SYSTEM$ functions are not allowed';
        return null;
    }

    /** @return string the query of the options, after the check, without a trailing ; */
    public static function queryOf( array $options )
    {
        $query = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $refusal = self::checkQuery( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( self::NAME . ': ' . $refusal );
        return rtrim( $query, " \t\r\n;" );
    }

    // ---- key-pair authentication -------------------------------------------------------------------------------------

    /**
     * The JWT of key-pair authentication.
     *
     * @param string $account the account identifier
     * @param string $user
     * @param string $pem the private key
     * @param string|null $passphrase
     * @return string
     * @throws expImportSourceException
     */
    public static function jwt( $account, $user, $pem, $passphrase = null )
    {
        if ( !function_exists( 'openssl_sign' ) )
            throw new expImportSourceException( self::NAME . ': the PHP extension openssl is needed to sign the key-pair token' );
        // a key kept on one line (an environment variable) with \n for its line breaks
        $pem = strpos( (string)$pem, "\n" ) === false ? str_replace( '\n', "\n", (string)$pem ) : (string)$pem;
        $key = @openssl_pkey_get_private( $pem, $passphrase === null ? '' : (string)$passphrase );
        if ( !$key )
            throw new expImportSourceException( self::NAME . ': the private key cannot be read (a PEM key, and with an encrypted key its passphrase)' );
        $details = openssl_pkey_get_details( $key );
        if ( !$details || !isset( $details['type'] ) || $details['type'] !== OPENSSL_KEYTYPE_RSA )
            throw new expImportSourceException( self::NAME . ': the private key must be an RSA key' );
        $qualified = self::accountOf( $account ) . '.' . strtoupper( (string)$user );
        $now = self::$now !== null ? (int)self::$now : time();
        $payload = array( 'iss' => $qualified . '.' . self::fingerprint( (string)$details['key'] ), 'sub' => $qualified,
                          'iat' => $now, 'exp' => $now + 3540 );
        $input = self::base64url( (string)json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . self::base64url( (string)json_encode( $payload ) );
        $signature = '';
        if ( !openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) )
            throw new expImportSourceException( self::NAME . ': the token cannot be signed' );
        return $input . '.' . self::base64url( $signature );
    }

    /**
     * The fingerprint of a public key as Snowflake shows it (DESC USER: RSA_PUBLIC_KEY_FP): SHA256: and the base64 of
     * the SHA-256 of the key's DER.
     *
     * @param string $publicPem
     * @return string
     */
    public static function fingerprint( $publicPem )
    {
        $der = base64_decode( (string)preg_replace( '/-----[^-]+-----|\s+/', '', (string)$publicPem ) );
        return 'SHA256:' . base64_encode( hash( 'sha256', (string)$der, true ) );
    }

    /** @return string the account as the token names it: upper case, without the region of a locator */
    public static function accountOf( $account )
    {
        $account = trim( (string)$account );
        $dot = strpos( $account, '.' );
        return strtoupper( $dot === false ? $account : substr( $account, 0, $dot ) );
    }

    /** @return string base64url without padding */
    protected static function base64url( $data )
    {
        return rtrim( strtr( base64_encode( (string)$data ), '+/', '-_' ), '=' );
    }

    /**
     * The address and the headers (with a new token).
     *
     * @return array the list ( string base, array headers, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $account = trim( isset( $options['account'] ) ? (string)$options['account'] : '' );
        if ( !preg_match( '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $account ) )
            throw new expImportSourceException( self::NAME . ': the account identifier must be orgname-accountname or a locator' );
        if ( !isset( $options['base_url'] ) || trim( (string)$options['base_url'] ) === '' )
            $options['base_url'] = 'https://' . strtolower( str_replace( '_', '-', $account ) ) . '.snowflakecomputing.com';
        list( $base, $trusted ) = self::baseUrl( $options );
        $user = trim( isset( $options['user'] ) ? (string)$options['user'] : '' );
        if ( $user === '' )
            throw new expImportSourceException( self::NAME . ': the user is required' );
        $ref = isset( $options['private_key_secret'] ) ? trim( (string)$options['private_key_secret'] ) : '';
        $used[] = $ref;
        $pem = expImportSecrets::required( $ref, self::NAME . ': the private key' );
        $passphrase = null;
        $pref = isset( $options['passphrase_secret'] ) ? trim( (string)$options['passphrase_secret'] ) : '';
        if ( $pref !== '' )
        {
            $used[] = $pref;
            $passphrase = expImportSecrets::required( $pref, self::NAME . ': the passphrase' );
        }
        $headers = array( 'Authorization' => 'Bearer ' . self::jwt( $account, $user, $pem, $passphrase ),
                          'X-Snowflake-Authorization-Token-Type' => 'KEYPAIR_JWT' );
        return array( $base, $headers, $trusted );
    }

    /**
     * Runs a statement and waits for its result.
     *
     * @return array the answer with resultSetMetaData, data and statementHandle
     * @throws expImportSourceException
     */
    protected static function execute( $base, array $headers, array $options, $sql, array $trusted )
    {
        $timeout = max( 1, (int)self::sourceSetting( 'StatementTimeout', 300 ) );
        $body = array( 'statement' => $sql, 'timeout' => $timeout, 'parameters' => array( 'MULTI_STATEMENT_COUNT' => '1' ) );
        foreach ( array( 'warehouse', 'database', 'schema', 'role' ) as $name )
            if ( isset( $options[$name] ) && trim( (string)$options[$name] ) !== '' )
                $body[$name] = trim( (string)$options[$name] );
        $request = 'requestId=' . self::uuid() . '&nullable=true';
        $answer = self::json( 'POST', $base . '/api/v2/statements?' . $request, $headers, $body, $trusted );
        $started = time();
        while ( !isset( $answer['resultSetMetaData'] ) )
        {
            $handle = isset( $answer['statementHandle'] ) ? (string)$answer['statementHandle'] : '';
            if ( $handle === '' || !preg_match( '/^[A-Za-z0-9-]+$/', $handle ) )
                throw new expImportSourceException( self::NAME . ': the answer has neither a result nor a statement handle' );
            if ( time() - $started > $timeout )
                throw new expImportSourceException( self::NAME . ': the statement did not end within [SnowflakeSourceSettings] StatementTimeout' );
            if ( self::$sleeper )
                call_user_func( self::$sleeper, 1 );
            else
                sleep( 1 );
            $answer = self::json( 'GET', $base . '/api/v2/statements/' . $handle, $headers, null, $trusted );
        }
        return $answer;
    }

    /** @return string a random UUID for requestId (a retried request is not run twice) */
    protected static function uuid()
    {
        $b = random_bytes( 16 );
        $b[6] = chr( ord( $b[6] ) & 0x0F | 0x40 );
        $b[8] = chr( ord( $b[8] ) & 0x3F | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
    }

    public static function rows( array $options, $limit, array &$used )
    {
        list( $base, $headers, $trusted ) = self::connection( $options, $used );
        $query = self::queryOf( $options );
        $answer = self::execute( $base, $headers, $options, $query, $trusted );
        $meta = (array)$answer['resultSetMetaData'];
        $types = isset( $meta['rowType'] ) ? (array)$meta['rowType'] : array();
        $partitions = isset( $meta['partitionInfo'] ) && is_array( $meta['partitionInfo'] ) ? count( $meta['partitionInfo'] ) : 1;
        $handle = isset( $answer['statementHandle'] ) ? (string)$answer['statementHandle'] : '';
        $n = 0;
        for ( $p = 0; $p < max( 1, $partitions ) && $p < self::maxPages(); $p++ )
        {
            if ( $p > 0 )
            {
                if ( !preg_match( '/^[A-Za-z0-9-]+$/', $handle ) )
                    throw new expImportSourceException( self::NAME . ': the answer has no statement handle for its other partitions' );
                $answer = self::json( 'GET', $base . '/api/v2/statements/' . $handle . '?partition=' . $p, $headers, null, $trusted );
            }
            foreach ( isset( $answer['data'] ) ? (array)$answer['data'] : array() as $values )
            {
                yield self::record( $types, (array)$values );
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
        }
    }

    /**
     * A row as a record: the values of its columns typed, an OBJECT flattened under its column name.
     *
     * @param array $types the rowType of the result
     * @param array $values
     * @return array
     */
    public static function record( array $types, array $values )
    {
        $row = array();
        foreach ( array_values( $values ) as $i => $value )
        {
            $type = isset( $types[$i] ) && is_array( $types[$i] ) ? $types[$i] : array();
            $name = isset( $type['name'] ) ? (string)$type['name'] : 'COLUMN' . ( $i + 1 );
            $row[$name] = self::value( $value, isset( $type['type'] ) ? (string)$type['type'] : 'text' );
        }
        return self::flatten( $row );
    }

    /**
     * A value of the SQL API (always text) by its type: FIXED stays text (no rounding of large numbers or decimals),
     * REAL a float, BOOLEAN a boolean, DATE Y-m-d, TIME H:i:s(.f), TIMESTAMP_NTZ local, TIMESTAMP_LTZ in UTC (Z),
     * TIMESTAMP_TZ with its offset, VARIANT, OBJECT and ARRAY decoded from JSON, everything else text.
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public static function value( $value, $type )
    {
        if ( $value === null )
            return null;
        $text = (string)$value;
        switch ( strtolower( $type ) )
        {
            case 'real':
                return is_numeric( $text ) ? (float)$text : $text;
            case 'boolean':
                return in_array( strtolower( $text ), array( 'true', '1' ), true );
            case 'date':
                return is_numeric( $text ) ? gmdate( 'Y-m-d', (int)$text * 86400 ) : $text;
            case 'time':
                if ( !is_numeric( $text ) )
                    return $text;
                list( $seconds, $fraction ) = self::split( $text );
                return gmdate( 'H:i:s', $seconds ) . $fraction;
            case 'timestamp_ntz':
            case 'timestamp_ltz':
                if ( !is_numeric( $text ) )
                    return $text;
                list( $seconds, $fraction ) = self::split( $text );
                return gmdate( 'Y-m-d\TH:i:s', $seconds ) . $fraction . ( strtolower( $type ) === 'timestamp_ltz' ? 'Z' : '' );
            case 'timestamp_tz':
                // "<epoch seconds>.<fraction> <offset minutes + 1440>"
                $parts = preg_split( '/\s+/', trim( $text ) );
                if ( count( $parts ) !== 2 || !is_numeric( $parts[0] ) || !is_numeric( $parts[1] ) )
                    return $text;
                list( $seconds, $fraction ) = self::split( $parts[0] );
                $offset = ( (int)$parts[1] - 1440 ) * 60;
                $abs = abs( $offset );
                return gmdate( 'Y-m-d\TH:i:s', $seconds + $offset ) . $fraction
                     . ( $offset === 0 ? 'Z' : ( $offset < 0 ? '-' : '+' ) . sprintf( '%02d:%02d', intdiv( $abs, 3600 ), intdiv( $abs % 3600, 60 ) ) );
            case 'variant':
            case 'object':
            case 'array':
                $data = json_decode( $text, true );
                return json_last_error() === JSON_ERROR_NONE ? $data : $text;
        }
        // fixed, text, binary (hex), geography, geometry, vector ...
        return $text;
    }

    /** @return array the list ( int seconds, string .fraction without trailing zeros or '' ) of "1714000000.123000000" */
    protected static function split( $text )
    {
        $parts = explode( '.', (string)$text, 2 );
        $fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
        return array( (int)$parts[0], $fraction !== '' ? '.' . $fraction : '' );
    }

    /** @return string the reason of an error answer: code and message */
    protected static function reason( $body )
    {
        $data = json_decode( (string)$body, true );
        if ( is_array( $data ) && isset( $data['message'] ) )
            return ( isset( $data['code'] ) ? $data['code'] . ': ' : '' ) . (string)$data['message'];
        return parent::reason( $body );
    }

    /**
     * The editor's "Test connection": the first row of the query, or why it failed (the key and its passphrase never
     * appear: the message is masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'Snowflake SQL API v2 (key pair)';
        try
        {
            list( $base, $headers, $trusted ) = self::connection( $options, $used );
            $query = self::queryOf( $options );
            list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, true ), '"' => array( 'name', false, true ) ),
                                                             'line' => array( '--', '//' ), 'block' => true, 'dollar' => 'plain' ) );
            $words = self::words( $stripped );
            $sql = in_array( $words[0], array( 'SELECT', 'WITH' ), true ) ? "SELECT * FROM (\n" . $query . "\n) LIMIT 1" : $query;
            $answer = self::execute( $base, $headers, $options, $sql, $trusted );
            $types = isset( $answer['resultSetMetaData']['rowType'] ) ? (array)$answer['resultSetMetaData']['rowType'] : array();
            $data = isset( $answer['data'] ) ? array_slice( (array)$answer['data'], 0, 1 ) : array();
            $columns = $data ? array_map( 'strval', array_keys( self::record( $types, (array)$data[0] ) ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $data ), 'driver' => $driver,
                          'message' => 'connected to Snowflake' . ( $data ? '; the query gives these columns' : '; the query finds no row' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /** https (with openssl, which also signs the token). */
    public static function localDrivers()
    {
        $drivers = parent::localDrivers();
        unset( $drivers['http'] );
        $drivers['https']['needs'] = 'allow_url_fopen=On and the PHP extension openssl (it also signs the key-pair token)';
        return $drivers;
    }
}
