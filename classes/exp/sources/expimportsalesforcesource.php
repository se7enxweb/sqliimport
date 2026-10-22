<?php
/**
 * File containing the expImportSalesforceSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The records of a SOQL query through the Salesforce REST API: one record is one row of the answer, relationship
 * fields flattened to dotted names (Account.Name), the "attributes" of every record dropped.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() signs in (OAuth 2.0), runs
 * GET <instance>/services/data/<version>/query (or queryAll, which also gives deleted and archived records) and
 * follows nextRecordsUrl, writing the records into the run's snapshot, one JSON object per line.
 *
 * Signing in, with a connected app:
 *   - JWT bearer flow (jwt): a JSON Web Token for the user, signed RS256 (openssl_sign) with the private key of the
 *     connected app's certificate; the key is the name of a secret holding the PEM
 *   - refresh token flow (refresh_token): the consumer key, the consumer secret and a refresh token, both secrets by
 *     their names
 * The instance address comes from the token answer; it and the login address must be https addresses on a domain of
 * [SalesforceSourceSettings] AllowedDomains[] (salesforce.com, force.com ...), or listed in AllowedHosts[].
 *
 * Only reading is possible: a query is a single SELECT (checkQuery()); FOR UPDATE (it locks records), FOR VIEW and
 * FOR REFERENCE (they write the last viewed dates), UPDATE TRACKING and UPDATE VIEWSTAT (they write statistics) are
 * refused, and so is anything that is not a SELECT. Give the integration user a profile or permission set that may
 * only read the objects the import needs ("API Enabled", read on those objects).
 *
 * Values (value()): text as it is; dates (2026-10-22) as they are; date-times in ISO 8601 with the offset written
 * +00:00 (Salesforce writes +0000; UTC as Z); currency, percent and double values (JSON numbers with a fraction) as a
 * number in text, so no digit is lost in a mapping; whole numbers as numbers; booleans as true and false; address
 * and geolocation fields flattened (BillingAddress.city, Location__c.latitude); a child relationship (a subquery) as
 * the JSON list of its records. Records of people (Contact, Lead, User) are personal data.
 */
class expImportSalesforceSource extends expImportJsonSource
{
    const LABEL = 'Salesforce';

    /** The API version when the import names none. */
    const VERSION = 'v61.0';

    /** The least time between two requests, in seconds. */
    const INTERVAL = 0.05;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 10.0;

    /** The clauses of SOQL that write something although they are part of a SELECT, and the words of DML. */
    const FORBIDDEN = array( 'FOR UPDATE', 'FOR VIEW', 'FOR REFERENCE', 'UPDATE TRACKING', 'UPDATE VIEWSTAT', 'INSERT', 'UPDATE', 'UPSERT',
                             'DELETE', 'UNDELETE', 'MERGE' );

    /** @var callable|null replaces time() for the JWT (tests) */
    public static $clock = null;

    public static function identifier()
    {
        return 'salesforce';
    }

    public static function label()
    {
        return 'Salesforce SOQL query';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'platform';
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
        return 'The records of a SOQL query through the Salesforce REST API (a single SELECT; also the deleted records with '
             . 'queryAll): relationship fields as dotted names (Account.Name), addresses flattened. Signs in with a connected '
             . 'app by the JWT bearer flow or a refresh token; keys and tokens are names of secrets. Contacts and leads are '
             . 'personal data.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three accounts with their owner and billing address (what a run writes)',
                   'file' => 'salesforce-accounts.ndjson',
                   'options' => array( 'client_id' => '3MVG9ExampleConsumerKey', 'user' => 'integration@example.invalid',
                                       'private_key' => 'salesforce_jwt_key', 'query' => 'SELECT Id, Name FROM Account' ) ),
            array( 'label' => 'The customer accounts changed this year with their owner, JWT bearer flow, the private key as the secret salesforce_jwt_key',
                   'options' => array( 'auth' => 'jwt', 'client_id' => '3MVG9ExampleConsumerKey', 'user' => 'integration@example.invalid',
                                       'private_key' => 'salesforce_jwt_key',
                                       'query' => "SELECT Id, Name, Type, Industry, AnnualRevenue, BillingAddress, Owner.Name, LastModifiedDate FROM Account WHERE Type = 'Customer' AND LastModifiedDate = THIS_YEAR ORDER BY Name",
                                       'key' => 'Id' ) ),
            array( 'label' => 'The products of a sandbox, also the deleted ones (queryAll), refresh token flow',
                   'options' => array( 'login_url' => 'https://test.salesforce.com', 'auth' => 'refresh_token', 'client_id' => '3MVG9ExampleConsumerKey',
                                       'client_secret' => 'salesforce_client_secret', 'refresh_token' => 'salesforce_refresh_token',
                                       'query' => 'SELECT Id, Name, ProductCode, IsActive, IsDeleted FROM Product2', 'query_all' => true, 'key' => 'Id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'login_url' => array( 'type' => 'url', 'label' => 'Login address', 'default' => 'https://login.salesforce.com',
                                  'help' => 'https://login.salesforce.com, https://test.salesforce.com for a sandbox, or the My Domain address' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Sign in with', 'default' => 'jwt',
                             'values' => array( 'jwt' => 'JWT bearer flow (consumer key, user, private key)', 'refresh_token' => 'Refresh token (consumer key and secret, refresh token)' ) ),
            'client_id' => array( 'type' => 'string', 'label' => 'Consumer key', 'required' => true, 'max' => 255, 'pattern' => '/^[A-Za-z0-9._-]+$/',
                                  'help' => 'The consumer key of the connected app' ),
            'user' => array( 'type' => 'string', 'label' => 'User (JWT)', 'max' => 255, 'help' => 'JWT bearer flow: the user name of the integration user' ),
            'private_key' => array( 'type' => 'secret', 'label' => 'Private key (name of a secret)',
                                    'help' => 'JWT bearer flow: the name of a secret holding the PEM of the private key of the connected app\'s certificate' ),
            'client_secret' => array( 'type' => 'secret', 'label' => 'Consumer secret (name of a secret)', 'help' => 'Refresh token flow' ),
            'refresh_token' => array( 'type' => 'secret', 'label' => 'Refresh token (name of a secret)', 'help' => 'Refresh token flow' ),
            'query' => array( 'type' => 'text', 'label' => 'SOQL query', 'required' => true,
                              'help' => 'A single SELECT, e.g. SELECT Id, Name, Owner.Name FROM Account WHERE Type = \'Customer\'' ),
            'query_all' => array( 'type' => 'bool', 'label' => 'Also deleted and archived records (queryAll)', 'default' => false ),
            'api_version' => array( 'type' => 'string', 'label' => 'API version', 'default' => self::VERSION, 'pattern' => '/^v\d{2,3}\.\d$/' ),
            'batch_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 2000, 'min' => 200, 'max' => 2000,
                                   'help' => 'Records per answer (Sforce-Query-Options batchSize; Salesforce may give fewer)' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'Id', 'help' => 'The field that names a record' ),
        );
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the checks --------------------------------------------------------------------------------------------------

    /**
     * Whether a SOQL query only reads: one statement beginning with SELECT, no FOR UPDATE, FOR VIEW, FOR REFERENCE,
     * UPDATE TRACKING or UPDATE VIEWSTAT, and no word of DML, outside its strings.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $query )
    {
        $query = trim( (string)$query );
        if ( $query === '' )
            return 'the query is empty';
        $code = self::codeOf( $query );
        if ( $code === null )
            return 'a string of the query is not closed';
        $code = rtrim( trim( $code ), ';' );
        if ( strpos( $code, ';' ) !== false )
            return 'only one statement is allowed';
        if ( !preg_match( '/^SELECT\b/i', ltrim( $code ) ) )
            return 'only a SELECT is allowed';
        $flat = preg_replace( '/\s+/', ' ', $code );
        foreach ( self::FORBIDDEN as $word )
            if ( preg_match( '/\b' . str_replace( ' ', '\s+', $word ) . '\b/i', $flat ) )
                return "the query contains $word: only reading is allowed";
        $depth = 0;
        foreach ( str_split( $code ) as $char )
        {
            $depth += $char === '(' ? 1 : ( $char === ')' ? -1 : 0 );
            if ( $depth < 0 )
                return 'the parentheses of the query do not match';
        }
        return $depth === 0 ? null : 'the parentheses of the query do not match';
    }

    /** @return string|null the query with its strings ('...' with \ escapes) emptied, null when one is not closed */
    protected static function codeOf( $query )
    {
        $out = '';
        $length = strlen( $query );
        for ( $i = 0; $i < $length; $i++ )
        {
            $char = $query[$i];
            if ( $char !== "'" )
            {
                $out .= $char;
                continue;
            }
            $out .= "''";
            for ( $i++; $i < $length; $i++ )
            {
                if ( $query[$i] === '\\' )
                {
                    $i++;
                    continue;
                }
                if ( $query[$i] === "'" )
                    continue 2;
            }
            return null;
        }
        return $out;
    }

    /**
     * Whether an address is one of Salesforce's (an https address on a domain of AllowedDomains[], or a host of
     * AllowedHosts[]).
     *
     * @param string $url
     * @param string $what for the message
     * @return string the address without a trailing slash
     * @throws expImportSourceException
     */
    public static function checkUrl( $url, $what )
    {
        $url = rtrim( trim( (string)$url ), '/' );
        $parts = parse_url( $url );
        if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) || strtolower( $parts['scheme'] ) !== 'https'
             || isset( $parts['user'] ) || isset( $parts['query'] ) || ( isset( $parts['path'] ) && $parts['path'] !== '' ) )
            throw new expImportSourceException( "Salesforce: the $what must be an https address without a path" );
        $host = strtolower( $parts['host'] );
        foreach ( (array)expImportPlatformHttp::setting( 'SalesforceSourceSettings', 'AllowedHosts', array() ) as $allowed )
            if ( strcasecmp( trim( (string)$allowed ), $host ) === 0 )
                return $url;
        foreach ( (array)expImportPlatformHttp::setting( 'SalesforceSourceSettings', 'AllowedDomains', array( 'salesforce.com', 'force.com', 'cloudforce.com', 'salesforce.mil' ) ) as $domain )
        {
            $domain = strtolower( trim( (string)$domain, " ." ) );
            if ( $domain !== '' && ( $host === $domain || substr( $host, -strlen( $domain ) - 1 ) === '.' . $domain ) )
                return $url;
        }
        throw new expImportSourceException( "Salesforce: the $what $host is not on a domain of [SalesforceSourceSettings] AllowedDomains[]" );
    }

    /** @return string[] the hosts of AllowedHosts[] (also allowed in a private network) */
    protected static function trusted()
    {
        return (array)expImportPlatformHttp::setting( 'SalesforceSourceSettings', 'AllowedHosts', array() );
    }

    // ---- signing in --------------------------------------------------------------------------------------------------

    /**
     * A JSON Web Token for the JWT bearer flow, signed RS256.
     *
     * @param string $clientId the consumer key (iss)
     * @param string $user the user name (sub)
     * @param string $audience the login address (aud)
     * @param string $pem the private key
     * @param int $now
     * @return string
     * @throws expImportSourceException
     */
    public static function jwt( $clientId, $user, $audience, $pem, $now )
    {
        if ( !function_exists( 'openssl_sign' ) )
            throw new expImportSourceException( 'Salesforce: the JWT bearer flow needs the PHP extension openssl' );
        $key = @openssl_pkey_get_private( (string)$pem );
        if ( $key === false )
            throw new expImportSourceException( 'Salesforce: the private key is not a valid PEM private key' );
        $b64 = function ( $data ) { return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); };
        $input = $b64( (string)json_encode( array( 'alg' => 'RS256' ) ) ) . '.'
               . $b64( (string)json_encode( array( 'iss' => (string)$clientId, 'sub' => (string)$user, 'aud' => (string)$audience, 'exp' => (int)$now + 180 ), JSON_UNESCAPED_SLASHES ) );
        $signature = '';
        if ( !openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) )
            throw new expImportSourceException( 'Salesforce: the token could not be signed with the private key' );
        return $input . '.' . $b64( $signature );
    }

    /**
     * Signs in: the access token and the instance address.
     *
     * @param array $options
     * @param string[] $used the secrets used, for masking
     * @param string[] $hidden other values to hide (the access token)
     * @return array the list ( string access token, string instance address )
     * @throws expImportSourceException
     */
    public static function signIn( array $options, array &$used, array &$hidden )
    {
        $login = self::checkUrl( isset( $options['login_url'] ) && trim( (string)$options['login_url'] ) !== '' ? $options['login_url'] : 'https://login.salesforce.com', 'login address' );
        $clientId = isset( $options['client_id'] ) ? trim( (string)$options['client_id'] ) : '';
        if ( $clientId === '' )
            throw new expImportSourceException( 'Salesforce: the consumer key is missing' );
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'jwt';
        if ( $auth === 'refresh_token' )
        {
            $refreshRef = isset( $options['refresh_token'] ) ? trim( (string)$options['refresh_token'] ) : '';
            $used[] = $refreshRef;
            $form = array( 'grant_type' => 'refresh_token', 'client_id' => $clientId,
                           'refresh_token' => expImportSecrets::required( $refreshRef, 'Salesforce refresh token' ) );
            $secretRef = isset( $options['client_secret'] ) ? trim( (string)$options['client_secret'] ) : '';
            if ( $secretRef !== '' )
            {
                $used[] = $secretRef;
                $form['client_secret'] = expImportSecrets::required( $secretRef, 'Salesforce consumer secret' );
            }
        }
        else
        {
            $user = isset( $options['user'] ) ? trim( (string)$options['user'] ) : '';
            if ( $user === '' )
                throw new expImportSourceException( 'Salesforce: the JWT bearer flow needs the user name' );
            $keyRef = isset( $options['private_key'] ) ? trim( (string)$options['private_key'] ) : '';
            $used[] = $keyRef;
            $pem = expImportSecrets::required( $keyRef, 'Salesforce private key' );
            $now = self::$clock ? (int)call_user_func( self::$clock ) : time();
            $audience = preg_match( '#^https://test\.salesforce\.com$#i', $login ) ? 'https://test.salesforce.com' : 'https://login.salesforce.com';
            $assertion = self::jwt( $clientId, $user, $audience, $pem, $now );
            $hidden[] = $assertion;
            $form = array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion );
        }
        $answer = expImportApiHttp::json( self::LABEL, 'POST', $login . '/services/oauth2/token', array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
                                          http_build_query( $form, '', '&', PHP_QUERY_RFC3986 ), self::INTERVAL, self::BACKOFF, self::trusted() );
        if ( !isset( $answer['access_token'], $answer['instance_url'] ) || !is_string( $answer['access_token'] ) || !is_string( $answer['instance_url'] ) )
            throw new expImportSourceException( 'Salesforce: the token answer has no access_token and instance_url' );
        $hidden[] = $answer['access_token'];
        return array( $answer['access_token'], self::checkUrl( $answer['instance_url'], 'instance address' ) );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the records into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most records (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $used = array();
        $hidden = array();
        try
        {
            expImportApiHttp::writeSnapshot( self::queryRecords( $options, (int)$limit, $used, $hidden ), $target, (int)$limit, self::LABEL );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportDocumentHttp::mask( $e->getMessage(), $used, $hidden ) );
        }
    }

    /**
     * The flattened records of the query, page by page.
     *
     * @param array $options
     * @param int $limit 0: all
     * @param string[] $used
     * @param string[] $hidden
     * @param int|null $total set to the totalSize of the answer
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function queryRecords( array $options, $limit, array &$used, array &$hidden, &$total = null )
    {
        $query = isset( $options['query'] ) ? trim( (string)$options['query'] ) : '';
        $refusal = self::checkQuery( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Salesforce: ' . $refusal );
        $version = isset( $options['api_version'] ) && preg_match( '/^v\d{2,3}\.\d$/', (string)$options['api_version'] ) ? (string)$options['api_version'] : self::VERSION;
        list( $token, $instance ) = self::signIn( $options, $used, $hidden );
        $batch = max( 200, min( 2000, isset( $options['batch_size'] ) ? (int)$options['batch_size'] : 2000 ) );
        $headers = array( 'Authorization' => 'Bearer ' . $token, 'Sforce-Query-Options' => 'batchSize=' . $batch );
        $url = $instance . '/services/data/' . $version . '/' . ( !empty( $options['query_all'] ) ? 'queryAll' : 'query' ) . '?q=' . rawurlencode( rtrim( $query, "; \n" ) );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'SalesforceSourceSettings', 'MaxPages', 1000 ) );
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $answer = expImportApiHttp::json( self::LABEL, 'GET', $url, $headers, null, self::INTERVAL, self::BACKOFF, self::trusted() );
            if ( !isset( $answer['records'] ) || !is_array( $answer['records'] ) )
                throw new expImportSourceException( 'Salesforce: the answer has no list of records' );
            if ( isset( $answer['totalSize'] ) && is_numeric( $answer['totalSize'] ) )
                $total = (int)$answer['totalSize'];
            foreach ( $answer['records'] as $record )
            {
                if ( !is_array( $record ) )
                    continue;
                yield self::flatten( $record );
                if ( ++$n >= $limit && $limit > 0 )
                    return;
            }
            $next = isset( $answer['nextRecordsUrl'] ) && is_string( $answer['nextRecordsUrl'] ) ? $answer['nextRecordsUrl'] : '';
            if ( !empty( $answer['done'] ) || $next === '' )
                return;
            if ( !preg_match( '#^/services/data/v\d{2,3}\.\d/query(All)?/[A-Za-z0-9-]+$#', $next ) )
                throw new expImportSourceException( 'Salesforce: the address of the next records is not a query locator' );
            $url = $instance . $next;
        }
        throw new expImportSourceException( "Salesforce: the query has more pages than [SalesforceSourceSettings] MaxPages ($maxPages) allows" );
    }

    /**
     * One record as one level of fields: attributes dropped, relationships and compound fields (addresses,
     * geolocations) as dotted names, a child relationship as the JSON list of its records.
     *
     * @param array $record
     * @param string $prefix
     * @return array
     */
    public static function flatten( array $record, $prefix = '' )
    {
        $out = array();
        foreach ( $record as $name => $value )
        {
            if ( $name === 'attributes' )
                continue;
            $key = $prefix . $name;
            if ( is_array( $value ) && isset( $value['records'] ) && is_array( $value['records'] ) && array_key_exists( 'totalSize', $value ) )
            {
                $children = array();
                foreach ( $value['records'] as $child )
                    if ( is_array( $child ) )
                        $children[] = self::flatten( $child );
                $out[$key] = (string)json_encode( $children, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }
            elseif ( is_array( $value ) && array_values( $value ) !== $value )
                $out += self::flatten( $value, $key . '.' );
            elseif ( is_array( $value ) )
                $out[$key] = (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            else
                $out[$key] = self::value( $value );
        }
        return $out;
    }

    /**
     * A value of a field: date-times in ISO 8601 with +00:00 (Z for UTC), numbers with a fraction as text.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value( $value )
    {
        if ( is_float( $value ) )
            return self::number( $value );
        if ( is_string( $value ) && preg_match( '/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?)([+-])(\d\d)(\d\d)$/', $value, $m ) )
            return $m[1] . ( $m[4] . $m[5] === '0000' ? 'Z' : $m[3] . $m[4] . ':' . $m[5] );
        return $value;
    }

    /** @return string a float as a plain number in text (1234.5, 0.000012), never with an exponent */
    public static function number( $value )
    {
        if ( !is_finite( (float)$value ) )
            return (string)$value;
        $text = (string)$value;
        if ( stripos( $text, 'e' ) === false )
            return $text;
        $text = rtrim( rtrim( sprintf( '%.20F', (float)$value ), '0' ), '.' );
        return $text === '-0' ? '0' : $text;
    }

    /**
     * The editor's "Test connection": signs in, reads the first record and reports its fields and the total, or why
     * it failed (never a key or token). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $hidden = array();
        try
        {
            $total = null;
            $first = null;
            foreach ( self::queryRecords( array_merge( $options, array( 'batch_size' => 200 ) ), 1, $used, $hidden, $total ) as $record )
            {
                $first = $record;
                break;
            }
            $counted = $total !== null ? " ($total records)" : '';
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => 'signed in; the query gives no record' );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => $total !== null ? $total : 1, 'driver' => 'api',
                          'message' => 'signed in, the first record has these fields' . $counted );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => expImportDocumentHttp::mask( $e->getMessage(), $used, $hidden ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'Salesforce REST API over HTTPS (openssl also signs the JWT)' ) );
    }
}
