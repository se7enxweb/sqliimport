<?php
/**
 * File containing the expImportRestApiSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The records of any JSON REST API, read page by page (rest_api): one record is one object of the list of an answer,
 * at its top or at a record path (data.items, $.results[0].hits), nested objects flattened to dotted fields
 * (author.name) as the MongoDB source does.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() requests the address, follows the paging
 * the import chose and writes the records into the run's snapshot, one JSON object per line.
 *
 * Paging: none; a page number; offset and limit; a cursor taken from the answer (a JSON path) and sent as a
 * parameter; the address of the next page taken from the answer (a JSON path) or from the RFC 8288 Link header with
 * rel="next", both on the same site only. The reading stops at an empty page, a page shorter than the page size, the
 * total of a total path, the import's own most pages; more pages than [RestApiSourceSettings] MaxPages is an error.
 *
 * Authentication: none, a bearer token, basic (a user and a password), an API key in a header or in a parameter, or
 * OAuth 2.0 client credentials (the token address on the same host or on [RestApiSourceSettings] AllowedTokenHosts[],
 * the token kept for the run and fetched again shortly before it expires). Every secret is the name of a secret
 * (expImportSecrets), never stored in the import; its value, the basic credentials and the access token are masked in
 * every message, and never written to the snapshot.
 *
 * Read-only: GET, or POST only with a JSON body the import gives (a search or query endpoint that reads; documented).
 * Extra headers only from an allow-list (Accept, Accept-Language, X-...; never Authorization, Cookie, Host, a method
 * override or a forwarding header), redirects only on the same site (expImportRestApiHttp).
 *
 * Rate limits: an answer 429 or 503 is tried again after Retry-After (seconds or an HTTP date), X-RateLimit-Reset or
 * RateLimit-Reset (rateLimit()); a wait longer than [RestApiSourceSettings] MaxWaitSeconds fails clearly. The import may
 * ask for a least time between two requests (min_interval).
 */
class expImportRestApiSource extends expImportJsonSource
{
    const LABEL = 'REST API';

    /** The modes of authentication. */
    const AUTH = array( 'none', 'bearer', 'basic', 'api_key_header', 'api_key_query', 'oauth2' );

    /** The modes of paging. */
    const PAGING = array( 'none', 'page', 'offset', 'cursor', 'next_url', 'link' );

    /** How often a request is tried again after 429, 503 or a gateway error. */
    const RETRIES = 4;

    /** The wait after a 429 or 503 without a hint, in seconds. */
    const BACKOFF = 5.0;

    /** The X- headers the headers option may never set: method overrides, forwarding, path rewriting. */
    const FORBIDDEN_X = '/^x-(http-method(-override)?|method-override|forwarded(-.*)?|real-ip|original-url|rewrite-url|client-ip|cluster-client-ip|host)$/i';

    /** Header and parameter names that hold credentials: they belong to the authentication options. */
    const CREDENTIAL_NAME = '/^key$|(^|[-_]|[a-z])(auth|authorization|token|api[-_]?key|apikey|secret|password|passwd|session|cookie|signature|sig)$/i';

    public static function identifier()
    {
        return 'rest_api';
    }

    public static function label()
    {
        return 'REST API (JSON, paged)';
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

    public static function variants()
    {
        return array();
    }

    public static function description()
    {
        return 'The records of any JSON REST API, read page by page: page number, offset and limit, a cursor, the next '
             . 'address of the answer or the Link header (same site only). Authentication by bearer token, basic, an API key '
             . 'in a header or a parameter, or OAuth 2.0 client credentials, every secret by its name. GET only (POST only '
             . 'with a JSON body for an endpoint that reads); waits on 429 and 503 as the server says.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three records of a paged API (what a run writes): nested objects as dotted fields, tags as a list',
                   'file' => 'rest-api-products.ndjson',
                   'options' => array( 'base_url' => 'https://api.example.invalid/v2/products', 'record_path' => 'data', 'key' => 'id' ) ),
            array( 'label' => 'Products of an API paged by page number, the bearer token as the secret shop_api_token',
                   'options' => array( 'base_url' => 'https://api.example.invalid/v2/products', 'query' => "status=active\nsort=id",
                                       'record_path' => 'data', 'paging' => 'page', 'page_param' => 'page', 'size_param' => 'per_page',
                                       'total_path' => 'meta.total', 'auth' => 'bearer', 'auth_secret' => 'shop_api_token' ) ),
            array( 'label' => 'Events of an API with a cursor in the answer (meta.next_cursor) and an API key in the header X-Api-Key',
                   'options' => array( 'base_url' => 'https://events.example.invalid/api/events', 'record_path' => 'items', 'paging' => 'cursor',
                                       'cursor_path' => 'meta.next_cursor', 'cursor_param' => 'cursor', 'size_param' => 'limit',
                                       'auth' => 'api_key_header', 'api_key_name' => 'X-Api-Key', 'auth_secret' => 'events_api_key',
                                       'headers' => "Accept-Language: en\nX-Client: website-import" ) ),
            array( 'label' => 'Articles of an API with OAuth 2.0 client credentials, paged by the Link header (rel="next")',
                   'options' => array( 'base_url' => 'https://cms.example.invalid/api/articles', 'paging' => 'link', 'size_param' => 'per_page',
                                       'auth' => 'oauth2', 'token_url' => 'https://cms.example.invalid/oauth/token', 'auth_user' => 'website-import',
                                       'auth_secret' => 'cms_client_secret', 'scope' => 'articles:read', 'min_interval' => 0.5 ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Address', 'required' => true,
                                 'help' => 'The address of the list, https (http only for a host of [RestApiSourceSettings] AllowedHosts[]); parameters go into the option below' ),
            'method' => array( 'type' => 'enum', 'label' => 'Method', 'default' => 'GET',
                               'values' => array( 'GET' => 'GET', 'POST' => 'POST with a JSON body (only an endpoint that reads: a search or query)' ) ),
            'body' => array( 'type' => 'text', 'label' => 'JSON body (POST)', 'max' => 65536,
                             'help' => 'POST only: the JSON object sent; the paging parameters are set in it. Only for an endpoint that reads' ),
            'query' => array( 'type' => 'list', 'label' => 'Parameters',
                              'help' => 'One per line as name=value (status=active); never a key or token, they belong to the authentication' ),
            'headers' => array( 'type' => 'list', 'label' => 'Extra headers',
                                'help' => 'One per line as Name: value; only Accept, Accept-Language and X-... (never Authorization, Cookie, Host or a key)' ),
            'record_path' => array( 'type' => 'string', 'label' => 'Record list', 'max' => 500,
                                    'help' => 'The path of the list in an answer: data.items or $.data.items or results[0].hits; empty: the top, or data, items, results, records' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'none',
                             'values' => array( 'none' => 'None', 'bearer' => 'Bearer token', 'basic' => 'Basic (user and password)',
                                                'api_key_header' => 'API key in a header', 'api_key_query' => 'API key in a parameter',
                                                'oauth2' => 'OAuth 2.0 client credentials' ) ),
            'auth_secret' => array( 'type' => 'secret', 'label' => 'Secret (name)',
                                    'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE: the token, the password, the API key or the client secret' ),
            'auth_user' => array( 'type' => 'string', 'label' => 'User or client ID', 'max' => 255,
                                  'help' => 'Basic: the user; OAuth 2.0: the client ID' ),
            'api_key_name' => array( 'type' => 'string', 'label' => 'API key header or parameter', 'max' => 100, 'pattern' => '/^([A-Za-z0-9_.-]+)?$/',
                                     'help' => 'The name of the header (X-Api-Key) or of the parameter (api_key) that carries the key' ),
            'token_url' => array( 'type' => 'url', 'label' => 'OAuth 2.0 token address',
                                  'help' => 'On the host of the address, or on one of [RestApiSourceSettings] AllowedTokenHosts[]' ),
            'scope' => array( 'type' => 'string', 'label' => 'OAuth 2.0 scope', 'max' => 1000, 'help' => 'Optional; a read scope' ),
            'client_auth' => array( 'type' => 'enum', 'label' => 'OAuth 2.0 client authentication', 'default' => 'basic',
                                    'values' => array( 'basic' => 'Basic header (RFC 6749)', 'body' => 'client_id and client_secret in the body' ) ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'none',
                               'values' => array( 'none' => 'None (one request)', 'page' => 'Page number', 'offset' => 'Offset and limit',
                                                  'cursor' => 'Cursor from the answer', 'next_url' => 'Next address from the answer',
                                                  'link' => 'Link header rel="next"' ) ),
            'page_param' => array( 'type' => 'string', 'label' => 'Page parameter', 'default' => 'page', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_.\[\]-]+$/' ),
            'page_start' => array( 'type' => 'int', 'label' => 'First page', 'default' => 1, 'min' => 0, 'max' => 1 ),
            'size_param' => array( 'type' => 'string', 'label' => 'Page size parameter', 'max' => 100, 'pattern' => '/^([A-Za-z0-9_.\[\]-]+)?$/',
                                   'help' => 'Page number, cursor, next address and Link: the parameter of the page size (per_page, limit); empty: none is sent and the server\'s own page size applies' ),
            'offset_param' => array( 'type' => 'string', 'label' => 'Offset parameter', 'default' => 'offset', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_.\[\]-]+$/' ),
            'limit_param' => array( 'type' => 'string', 'label' => 'Limit parameter', 'default' => 'limit', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_.\[\]-]+$/' ),
            'cursor_path' => array( 'type' => 'string', 'label' => 'Cursor path', 'max' => 500, 'help' => 'Cursor: the path of the next cursor in an answer, e.g. meta.next_cursor' ),
            'cursor_param' => array( 'type' => 'string', 'label' => 'Cursor parameter', 'default' => 'cursor', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_.\[\]-]+$/' ),
            'next_path' => array( 'type' => 'string', 'label' => 'Next address path', 'max' => 500, 'help' => 'Next address: its path in an answer, e.g. links.next (same site only)' ),
            'total_path' => array( 'type' => 'string', 'label' => 'Total path', 'max' => 500, 'help' => 'Optional: the path of the total count in an answer, e.g. meta.total' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 10000 ),
            'max_pages' => array( 'type' => 'int', 'label' => 'Most pages', 'default' => 0, 'min' => 0,
                                  'help' => 'Stop after so many pages; 0: up to [RestApiSourceSettings] MaxPages (more is an error)' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most records', 'default' => 0, 'min' => 0, 'help' => '0: every record' ),
            'min_interval' => array( 'type' => 'float', 'label' => 'Least time between requests (seconds)', 'default' => 0.0, 'min' => 0, 'max' => 60 ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of objects stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a record' ),
        );
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the checks --------------------------------------------------------------------------------------------------

    /**
     * Whether an extra header may be sent: Accept, Accept-Language or X-..., not a method override, a forwarding
     * header or one that holds a credential; one line.
     *
     * @param string $line Name: value
     * @return array|string the list ( string name, string value ), or the reason it is refused
     */
    public static function checkHeader( $line )
    {
        $line = trim( (string)$line );
        $at = strpos( $line, ':' );
        if ( $at === false || $at === 0 )
            return "a header is Name: value: $line";
        $name = trim( substr( $line, 0, $at ) );
        $value = trim( substr( $line, $at + 1 ) );
        if ( !preg_match( '/^[A-Za-z0-9-]+$/', $name ) )
            return "a header name has letters, digits and - only: $name";
        if ( strpbrk( $value, "\r\n\0" ) !== false )
            return "the value of the header $name is one line";
        $lower = strtolower( $name );
        if ( $lower !== 'accept' && $lower !== 'accept-language' && strpos( $lower, 'x-' ) !== 0 )
            return "the header $name cannot be set; only Accept, Accept-Language and X-... headers (authentication has options of its own)";
        if ( preg_match( self::FORBIDDEN_X, $name ) )
            return "the header $name cannot be set (it changes the method, the address or who asks)";
        if ( preg_match( self::CREDENTIAL_NAME, $name ) )
            return "the header $name holds a credential: use the authentication options (the value is then a secret, never stored in the import)";
        return array( $name, $value );
    }

    /**
     * Whether a parameter line may be sent: name=value, and not a name that holds a credential.
     *
     * @param string $line
     * @return array|string the list ( string name, string value ), or the reason it is refused
     */
    public static function checkParam( $line )
    {
        $line = trim( (string)$line );
        $at = strpos( $line, '=' );
        if ( $at === false || $at === 0 )
            return "a parameter is name=value: $line";
        $name = trim( substr( $line, 0, $at ) );
        $value = trim( substr( $line, $at + 1 ) );
        if ( !preg_match( '/^[A-Za-z0-9_.\[\]-]+$/', $name ) )
            return "a parameter name has letters, digits, _ . - [ ] only: $name";
        if ( strpbrk( $value, "\r\n\0" ) !== false )
            return "the value of the parameter $name is one line";
        if ( preg_match( self::CREDENTIAL_NAME, $name ) )
            return "the parameter $name holds a credential: use the authentication option API key in a parameter (the value is then a secret)";
        return array( $name, $value );
    }

    /**
     * A JSON path of the subset the source reads, as the dotted path of expImportRecord::pathValue(): a.b.c,
     * $.a.b, a[0].b, a['b c'] (no wildcards, filters or recursion).
     *
     * @param string $path
     * @return string|null the dotted path ('' for the top), null when it is not of the subset
     */
    public static function dottedPath( $path )
    {
        $path = trim( (string)$path );
        if ( $path === '' || $path === '$' )
            return '';
        if ( strpos( $path, '$.' ) === 0 )
            $path = substr( $path, 2 );
        elseif ( strpos( $path, '$[' ) === 0 )
            $path = substr( $path, 1 );
        if ( substr( $path, -1 ) === '.' )
            return null;
        $out = array();
        $length = strlen( $path );
        $i = 0;
        $current = '';
        $bracket = false;
        while ( $i < $length )
        {
            $char = $path[$i];
            if ( $char === '.' )
            {
                // a dot follows a name or a bracket, never another dot
                if ( $current === '' && !$bracket )
                    return null;
                if ( $current !== '' )
                    $out[] = $current;
                $current = '';
                $bracket = false;
                $i++;
                continue;
            }
            $bracket = false;
            if ( $char === '[' )
            {
                if ( $current !== '' )
                    $out[] = $current;
                $current = '';
                $end = strpos( $path, ']', $i );
                if ( $end === false )
                    return null;
                $inner = substr( $path, $i + 1, $end - $i - 1 );
                if ( preg_match( '/^\d+$/', $inner ) )
                    $out[] = $inner;
                elseif ( preg_match( '/^\'([^\'.]+)\'$|^"([^".]+)"$/', $inner, $m ) )
                    $out[] = $m[1] !== '' ? $m[1] : $m[2];
                else
                    return null;
                $i = $end + 1;
                $bracket = true;
                continue;
            }
            if ( strpos( '*?()@$', $char ) !== false )
                return null;
            $current .= $char;
            $i++;
        }
        if ( $current !== '' )
            $out[] = $current;
        return $out ? implode( '.', $out ) : null;
    }

    /** @return mixed the value at a JSON path of the subset in an answer, null when it has none */
    public static function at( array $data, $path )
    {
        $dotted = self::dottedPath( $path );
        if ( $dotted === null )
            return null;
        if ( $dotted === '' )
            return $data;
        $value = $data;
        foreach ( explode( '.', $dotted ) as $part )
        {
            if ( !is_array( $value ) || !array_key_exists( $part, $value ) )
                return null;
            $value = $value[$part];
        }
        return $value;
    }

    /**
     * The address of rel="next" in RFC 8288 Link header lines, resolved against the address of the page.
     *
     * @param string[] $lines the header lines of an answer
     * @param string $url the address of the page
     * @return string '' when there is none
     */
    public static function linkNext( array $lines, $url )
    {
        foreach ( $lines as $line )
        {
            if ( stripos( (string)$line, 'Link:' ) !== 0 )
                continue;
            if ( !preg_match_all( '/<([^>]*)>((?:\s*;\s*[A-Za-z0-9*_-]+\s*=\s*(?:"[^"]*"|[^;,\s]*))*)/', substr( (string)$line, 5 ), $links, PREG_SET_ORDER ) )
                continue;
            foreach ( $links as $link )
                if ( preg_match( '/;\s*rel\s*=\s*(?:"([^"]*)"|([^;,\s]*))/i', $link[2], $rel )
                     && in_array( 'next', preg_split( '/\s+/', strtolower( trim( $rel[1] !== '' ? $rel[1] : ( isset( $rel[2] ) ? $rel[2] : '' ) ) ) ), true ) )
                    return expImportPlatformHttp::resolve( $url, trim( $link[1] ) );
        }
        return '';
    }

    /**
     * How long a server asks to wait before the next request, from an answer 429 or 503: Retry-After (seconds or an
     * HTTP date), else X-RateLimit-Reset or RateLimit-Reset (seconds to wait, or the time as seconds since 1970 when
     * it is larger than 10^9), else the reset of a RateLimit header (reset=30).
     *
     * @param int $status
     * @param string[] $lines the header lines of the answer
     * @param float $now the time, in seconds since 1970
     * @return float|null the seconds to wait, null when the answer is no rate limit or gives no hint
     */
    public static function rateLimit( $status, array $lines, $now )
    {
        if ( (int)$status !== 429 && (int)$status !== 503 )
            return null;
        $after = expImportPlatformHttp::header( $lines, 'Retry-After' );
        if ( $after !== '' )
        {
            if ( preg_match( '/^\d+(\.\d+)?$/', $after ) )
                return (float)$after;
            $time = strtotime( $after );
            if ( $time !== false )
                return max( 0.0, $time - (float)$now );
        }
        foreach ( array( 'X-RateLimit-Reset', 'RateLimit-Reset', 'X-Rate-Limit-Reset' ) as $name )
        {
            $reset = expImportPlatformHttp::header( $lines, $name );
            if ( preg_match( '/^\d+(\.\d+)?$/', $reset ) )
                return (float)$reset > 1000000000 ? max( 0.0, (float)$reset - (float)$now ) : (float)$reset;
        }
        if ( preg_match( '/(?:^|[;,\s])(?:reset|t)\s*=\s*(\d+)/i', expImportPlatformHttp::header( $lines, 'RateLimit' ), $m ) )
            return (float)$m[1];
        return null;
    }

    // ---- the plan ----------------------------------------------------------------------------------------------------

    /**
     * Whether an address may be requested: https, or http for a host of AllowedHosts[]; a host in a private network
     * only from AllowedHosts[]; no credentials or fragment.
     *
     * @param string $url
     * @param string[] $trusted
     * @param string $what for messages
     * @return string|null the reason it is refused
     */
    public static function refuseAddress( $url, array $trusted, $what = 'the address' )
    {
        $parts = parse_url( (string)$url );
        if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) )
            return "$what is not an address";
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) )
            return "$what may not have credentials or a fragment (use the authentication options)";
        $scheme = strtolower( $parts['scheme'] );
        $listed = false;
        foreach ( $trusted as $host )
            if ( strcasecmp( trim( (string)$host, ' []' ), trim( $parts['host'], '[]' ) ) === 0 )
                $listed = true;
        if ( $scheme !== 'https' && !( $scheme === 'http' && $listed ) )
            return "$what must be https (http only for a host of sqliimport.ini [RestApiSourceSettings] AllowedHosts[])";
        $refusal = expImportSourceFiles::refuseHost( $parts['host'], $trusted );
        return $refusal === null ? null : $refusal . ' (or list it in sqliimport.ini [RestApiSourceSettings] AllowedHosts[])';
    }

    /**
     * The request of the options, checked, with the authentication resolved (OAuth 2.0: its token is fetched by
     * token() when the first request needs it).
     *
     * @param array $options
     * @return array url, method, body, params, headers, trusted, refs, hidden, auth, paging options
     * @throws expImportSourceException
     */
    public static function plan( array $options )
    {
        $o = function ( $name, $default = '' ) use ( $options )
        {
            return isset( $options[$name] ) && $options[$name] !== null ? $options[$name] : $default;
        };
        $trusted = (array)expImportPlatformHttp::setting( 'RestApiSourceSettings', 'AllowedHosts', array() );
        $url = trim( (string)$o( 'base_url' ) );
        $refusal = self::refuseAddress( $url, $trusted );
        if ( $refusal !== null )
            throw new expImportSourceException( 'REST API: ' . $refusal );
        if ( parse_url( $url, PHP_URL_QUERY ) !== null )
            throw new expImportSourceException( 'REST API: the address may not have a query; give the parameters one per line in Parameters' );
        $method = strtoupper( (string)$o( 'method', 'GET' ) );
        if ( $method !== 'GET' && $method !== 'POST' )
            throw new expImportSourceException( 'REST API: only GET, or POST with a JSON body, is sent' );
        $body = null;
        if ( $method === 'POST' )
        {
            $body = json_decode( trim( (string)$o( 'body' ) ), true );
            if ( !is_array( $body ) || ( $body && array_values( $body ) === $body ) )
                throw new expImportSourceException( 'REST API: POST needs a JSON object as its body (a search or query that reads)' );
        }
        elseif ( trim( (string)$o( 'body' ) ) !== '' )
            throw new expImportSourceException( 'REST API: a body is sent only with POST' );
        $params = array();
        $lines = $o( 'query', array() );
        foreach ( is_string( $lines ) ? preg_split( '/\r\n|\r|\n/', $lines ) : (array)$lines as $line )
        {
            if ( trim( (string)$line ) === '' )
                continue;
            $param = self::checkParam( (string)$line );
            if ( is_string( $param ) )
                throw new expImportSourceException( 'REST API: ' . $param );
            $params[$param[0]] = $param[1];
        }
        $headers = array();
        $lines = $o( 'headers', array() );
        foreach ( is_string( $lines ) ? preg_split( '/\r\n|\r|\n/', $lines ) : (array)$lines as $line )
        {
            if ( trim( (string)$line ) === '' )
                continue;
            $header = self::checkHeader( (string)$line );
            if ( is_string( $header ) )
                throw new expImportSourceException( 'REST API: ' . $header );
            $headers[$header[0]] = $header[1];
        }
        if ( $method === 'POST' )
            $headers['Content-Type'] = 'application/json';
        foreach ( array( 'record_path', 'cursor_path', 'next_path', 'total_path' ) as $name )
            if ( self::dottedPath( (string)$o( $name ) ) === null && trim( (string)$o( $name ) ) !== '' )
                throw new expImportSourceException( "REST API: $name is not a path of the subset (a.b, \$.a.b, a[0].b, a['b c'])" );
        $plan = array( 'url' => $url, 'method' => $method, 'body' => $body, 'params' => $params, 'headers' => $headers, 'trusted' => $trusted,
                       'refs' => array(), 'hidden' => array(), 'auth' => (string)$o( 'auth', 'none' ), 'token' => null, 'expires' => 0.0,
                       'paging' => in_array( $o( 'paging', 'none' ), self::PAGING, true ) ? (string)$o( 'paging', 'none' ) : 'none',
                       'page_param' => (string)$o( 'page_param', 'page' ), 'page_start' => (int)$o( 'page_start', 1 ),
                       'size_param' => (string)$o( 'size_param', '' ), 'offset_param' => (string)$o( 'offset_param', 'offset' ),
                       'limit_param' => (string)$o( 'limit_param', 'limit' ), 'cursor_path' => trim( (string)$o( 'cursor_path' ) ),
                       'cursor_param' => (string)$o( 'cursor_param', 'cursor' ), 'next_path' => trim( (string)$o( 'next_path' ) ),
                       'total_path' => trim( (string)$o( 'total_path' ) ), 'record_path' => trim( (string)$o( 'record_path' ) ),
                       'page_size' => max( 1, min( 10000, (int)$o( 'page_size', 100 ) ) ), 'max_pages' => max( 0, (int)$o( 'max_pages', 0 ) ),
                       'limit' => max( 0, (int)$o( 'limit', 0 ) ), 'min_interval' => max( 0.0, min( 60.0, (float)$o( 'min_interval', 0.0 ) ) ),
                       'last' => null, 'key_param' => array() );
        if ( $plan['paging'] === 'cursor' && $plan['cursor_path'] === '' )
            throw new expImportSourceException( 'REST API: cursor paging needs the cursor path (where an answer gives the next cursor)' );
        if ( $plan['paging'] === 'next_url' && $plan['next_path'] === '' )
            throw new expImportSourceException( 'REST API: paging by the next address needs its path in an answer' );
        self::authenticate( $plan, $options );
        return $plan;
    }

    /**
     * Resolves the authentication into the plan: headers or a parameter, the secrets used and the values to mask.
     *
     * @param array $plan
     * @param array $options
     * @throws expImportSourceException
     */
    protected static function authenticate( array &$plan, array $options )
    {
        $auth = $plan['auth'];
        if ( !in_array( $auth, self::AUTH, true ) )
            throw new expImportSourceException( "REST API: unknown authentication $auth" );
        if ( $auth === 'none' )
            return;
        $ref = isset( $options['auth_secret'] ) ? trim( (string)$options['auth_secret'] ) : '';
        if ( $ref === '' )
            throw new expImportSourceException( 'REST API: the authentication needs the name of a secret' );
        $plan['refs'][] = $ref;
        $secret = expImportSecrets::required( $ref, 'the REST API secret' );
        $plan['hidden'][] = $secret;
        $user = isset( $options['auth_user'] ) ? trim( (string)$options['auth_user'] ) : '';
        $name = isset( $options['api_key_name'] ) ? trim( (string)$options['api_key_name'] ) : '';
        switch ( $auth )
        {
            case 'bearer':
                $plan['headers']['Authorization'] = 'Bearer ' . $secret;
                break;
            case 'basic':
                if ( $user === '' || strpos( $user, ':' ) !== false )
                    throw new expImportSourceException( 'REST API: basic authentication needs a user (without :)' );
                $plan['headers']['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $secret );
                $plan['hidden'][] = base64_encode( $user . ':' . $secret );
                break;
            case 'api_key_header':
                $name = $name !== '' ? $name : 'X-Api-Key';
                $lower = strtolower( $name );
                if ( !preg_match( '/^[A-Za-z0-9-]+$/', $name ) || in_array( $lower, array( 'cookie', 'host', 'content-type', 'content-length', 'proxy-authorization', 'transfer-encoding' ), true )
                     || preg_match( self::FORBIDDEN_X, $name ) )
                    throw new expImportSourceException( "REST API: the API key cannot be sent in the header $name" );
                $plan['headers'][$name] = $secret;
                break;
            case 'api_key_query':
                $name = $name !== '' ? $name : 'api_key';
                if ( !preg_match( '/^[A-Za-z0-9_.\[\]-]+$/', $name ) )
                    throw new expImportSourceException( "REST API: the API key parameter $name is not a name" );
                $plan['key_param'] = array( $name => $secret );
                break;
            case 'oauth2':
                $tokenUrl = isset( $options['token_url'] ) ? trim( (string)$options['token_url'] ) : '';
                if ( $tokenUrl === '' || $user === '' )
                    throw new expImportSourceException( 'REST API: OAuth 2.0 needs the token address and the client ID' );
                $refusal = self::refuseAddress( $tokenUrl, $plan['trusted'], 'the token address' );
                if ( $refusal !== null )
                    throw new expImportSourceException( 'REST API: ' . $refusal );
                $tokenHost = strtolower( (string)parse_url( $tokenUrl, PHP_URL_HOST ) );
                $hosts = array_map( 'strtolower', array_map( 'trim', (array)expImportPlatformHttp::setting( 'RestApiSourceSettings', 'AllowedTokenHosts', array() ) ) );
                if ( $tokenHost !== strtolower( (string)parse_url( $plan['url'], PHP_URL_HOST ) ) && !in_array( $tokenHost, $hosts, true ) )
                    throw new expImportSourceException( "REST API: the token address is on another host ($tokenHost); list it in sqliimport.ini [RestApiSourceSettings] AllowedTokenHosts[]" );
                $plan['oauth'] = array( 'url' => $tokenUrl, 'client' => $user, 'secret' => $secret,
                                        'scope' => isset( $options['scope'] ) ? trim( (string)$options['scope'] ) : '',
                                        'client_auth' => isset( $options['client_auth'] ) && $options['client_auth'] === 'body' ? 'body' : 'basic' );
                $plan['hidden'][] = base64_encode( rawurlencode( $user ) . ':' . rawurlencode( $secret ) );
                break;
        }
    }

    /**
     * The access token of OAuth 2.0 client credentials, kept in the plan for the run and fetched again a minute
     * before it expires.
     *
     * @param array $plan
     * @throws expImportSourceException
     */
    protected static function token( array &$plan )
    {
        if ( $plan['token'] !== null && ( $plan['expires'] <= 0 || self::now() < $plan['expires'] - 60 ) )
            return;
        $oauth = $plan['oauth'];
        $form = array( 'grant_type' => 'client_credentials' );
        if ( $oauth['scope'] !== '' )
            $form['scope'] = $oauth['scope'];
        $headers = array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json' );
        if ( $oauth['client_auth'] === 'body' )
            $form += array( 'client_id' => $oauth['client'], 'client_secret' => $oauth['secret'] );
        else
            $headers['Authorization'] = 'Basic ' . base64_encode( rawurlencode( $oauth['client'] ) . ':' . rawurlencode( $oauth['secret'] ) );
        list( $data ) = self::request( $plan, 'POST', $oauth['url'], $headers, http_build_query( $form, '', '&', PHP_QUERY_RFC3986 ), 'the token address' );
        $token = isset( $data['access_token'] ) && is_string( $data['access_token'] ) ? $data['access_token'] : '';
        $type = isset( $data['token_type'] ) && is_string( $data['token_type'] ) ? strtolower( $data['token_type'] ) : 'bearer';
        if ( $token === '' || $type !== 'bearer' )
            throw new expImportSourceException( 'REST API: the token address gave no bearer access token' );
        $plan['token'] = $token;
        $plan['hidden'][] = $token;
        $plan['expires'] = isset( $data['expires_in'] ) && is_numeric( $data['expires_in'] ) && $data['expires_in'] > 0 ? self::now() + (float)$data['expires_in'] : 0.0;
        $plan['headers']['Authorization'] = 'Bearer ' . $token;
    }

    // ---- the requests ------------------------------------------------------------------------------------------------

    /** @return float now, in seconds since 1970 (the clock of expImportApiHttp, which tests replace) */
    protected static function now()
    {
        return expImportApiHttp::$clock ? (float)call_user_func( expImportApiHttp::$clock ) : microtime( true );
    }

    /**
     * One request within the rate limit: the least time between two requests, waiting and trying again after 429
     * or 503 (as rateLimit() says, at most MaxWaitSeconds) and after 502 or 504.
     *
     * @param array $plan
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string|null $body
     * @param string $what for messages
     * @return array the list ( array the decoded answer, string[] the header lines )
     * @throws expImportSourceException
     */
    protected static function request( array &$plan, $method, $url, array $headers, $body, $what = '' )
    {
        $maxWait = max( 0.0, (float)expImportPlatformHttp::setting( 'RestApiSourceSettings', 'MaxWaitSeconds', 120 ) );
        $shown = $what !== '' ? $what : expImportPlatformHttp::shown( $url );
        for ( $try = 0; ; $try++ )
        {
            if ( $plan['last'] !== null && $plan['min_interval'] > 0 )
            {
                $since = self::now() - $plan['last'];
                if ( $since < $plan['min_interval'] )
                    expImportApiHttp::sleep( $plan['min_interval'] - $since );
            }
            $plan['last'] = self::now();
            $answer = expImportRestApiHttp::call( $method, $url, $headers + array( 'Accept' => 'application/json' ), $body, $plan['trusted'] );
            $status = (int)$answer['status'];
            $lines = isset( $answer['headers'] ) ? (array)$answer['headers'] : array();
            if ( $status === 429 || $status === 503 || $status === 502 || $status === 504 )
            {
                $wait = self::rateLimit( $status, $lines, self::now() );
                if ( $wait === null )
                    $wait = $status === 429 || $status === 503 ? self::BACKOFF : 2.0 * ( $try + 1 );
                if ( $wait > $maxWait )
                    throw new expImportSourceException( "REST API: $shown answered $status and asks to wait " . (int)ceil( $wait )
                                                        . " seconds, more than sqliimport.ini [RestApiSourceSettings] MaxWaitSeconds ($maxWait); try again later" );
                if ( $try < self::RETRIES )
                {
                    expImportApiHttp::sleep( $wait );
                    continue;
                }
                throw new expImportSourceException( "REST API: $shown answered $status, still after waiting " . self::RETRIES . ' times' );
            }
            $data = json_decode( (string)$answer['body'], true );
            if ( $status >= 200 && $status < 300 )
            {
                if ( !is_array( $data ) )
                    throw new expImportSourceException( "REST API: $shown did not answer with JSON" );
                return array( $data, $lines );
            }
            $reason = expImportDocumentHttp::reason( $data );
            $text = "REST API: $shown answered $status";
            if ( $status === 401 || $status === 403 )
                $text .= ': the access was refused (check the authentication and what it may read)';
            elseif ( $status === 404 )
                $text .= ': not found';
            throw new expImportSourceException( $text . ( $reason !== '' ? ' (' . substr( $reason, 0, 300 ) . ')' : '' ), $status );
        }
    }

    /** @return string an address with parameters added (or replaced) */
    public static function withParams( $url, array $params )
    {
        if ( !$params )
            return $url;
        $query = (string)parse_url( $url, PHP_URL_QUERY );
        $pairs = array();
        foreach ( $query === '' ? array() : explode( '&', $query ) as $pair )
        {
            $name = rawurldecode( (string)strstr( $pair . '=', '=', true ) );
            if ( !array_key_exists( $name, $params ) )
                $pairs[] = $pair;
        }
        foreach ( $params as $name => $value )
            $pairs[] = rawurlencode( (string)$name ) . '=' . rawurlencode( (string)$value );
        $base = strpos( $url, '?' ) !== false ? substr( $url, 0, strpos( $url, '?' ) ) : $url;
        return $base . '?' . str_replace( array( '%5B', '%5D' ), array( '[', ']' ), implode( '&', $pairs ) );
    }

    /**
     * One page: the request with the paging parameters (in the query, or in the body of a POST).
     *
     * @param array $plan
     * @param string $url the address (the base, or a next address of the answer)
     * @param array $paging parameter => value
     * @return array the list ( array the answer, string[] the header lines )
     * @throws expImportSourceException
     */
    protected static function page( array &$plan, $url, array $paging )
    {
        if ( $plan['auth'] === 'oauth2' )
            self::token( $plan );
        $body = null;
        if ( $plan['method'] === 'POST' )
        {
            $body = (string)json_encode( ( $plan['body'] ? $paging + $plan['body'] : $paging ) ?: new stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $paging = array();
        }
        // a next address of the answer carries its own parameters; the API key of a parameter is added again
        $params = ( $url === $plan['url'] ? $plan['params'] : array() ) + $paging + $plan['key_param'];
        return self::request( $plan, $plan['method'], self::withParams( $url, $params ), $plan['headers'], $body );
    }

    /**
     * The list of records of an answer: at the record path, else the answer itself when it is a list, else the
     * first list of data, items, results, records or values.
     *
     * @param array $plan
     * @param array $data
     * @return array
     * @throws expImportSourceException
     */
    public static function recordList( array $plan, array $data )
    {
        if ( $plan['record_path'] !== '' )
        {
            $list = self::at( $data, $plan['record_path'] );
            if ( $list === null )
                return array();
            if ( !is_array( $list ) )
                throw new expImportSourceException( "REST API: the answer has no list at {$plan['record_path']}" );
            return array_values( $list ) === $list ? $list : array( $list );
        }
        if ( array_values( $data ) === $data )
            return $data;
        foreach ( array( 'data', 'items', 'results', 'records', 'values' ) as $name )
            if ( isset( $data[$name] ) && is_array( $data[$name] ) && array_values( $data[$name] ) === $data[$name] )
                return $data[$name];
        throw new expImportSourceException( 'REST API: the answer is an object; give the record list (the path of the list of records, e.g. data.items)' );
    }

    /**
     * The flattened records, page by page.
     *
     * @param array $plan
     * @param array $options arrays, separator
     * @param int $limit 0: all
     * @param int|null $total set to the total of the total path when an answer gives it
     * @param int $pagesAsked the most pages to read (Test connection: 1); 0: as the plan says
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function apiRecords( array &$plan, array $options, $limit, &$total = null, $pagesAsked = 0 )
    {
        $limit = $plan['limit'] > 0 && ( $limit <= 0 || $plan['limit'] < $limit ) ? $plan['limit'] : (int)$limit;
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'RestApiSourceSettings', 'MaxPages', 1000 ) );
        $own = $pagesAsked > 0 ? $pagesAsked : $plan['max_pages'];
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $size = $plan['page_size'];
        $url = $plan['url'];
        $cursor = null;
        $seen = array();
        $n = 0;
        $offset = 0;
        for ( $page = 1; ; $page++ )
        {
            if ( $page > $maxPages )
                throw new expImportSourceException( "REST API: the API has more pages than sqliimport.ini [RestApiSourceSettings] MaxPages ($maxPages) allows" );
            $paging = array();
            switch ( $plan['paging'] )
            {
                case 'page':
                    $paging[$plan['page_param']] = $plan['page_start'] + $page - 1;
                    if ( $plan['size_param'] !== '' )
                        $paging[$plan['size_param']] = $size;
                    break;
                case 'offset':
                    $paging[$plan['offset_param']] = $offset;
                    $paging[$plan['limit_param']] = $size;
                    break;
                case 'cursor':
                    if ( $plan['size_param'] !== '' )
                        $paging[$plan['size_param']] = $size;
                    if ( $cursor !== null )
                        $paging[$plan['cursor_param']] = $cursor;
                    break;
                case 'next_url':
                case 'link':
                    if ( $page === 1 && $plan['size_param'] !== '' )
                        $paging[$plan['size_param']] = $size;
                    break;
            }
            list( $data, $lines ) = self::page( $plan, $url, $paging );
            $list = self::recordList( $plan, $data );
            if ( $plan['total_path'] !== '' )
            {
                $count = self::at( $data, $plan['total_path'] );
                if ( is_numeric( $count ) )
                    $total = (int)$count;
            }
            foreach ( $list as $record )
            {
                if ( !is_array( $record ) )
                    $record = array( 'value' => $record );
                yield self::scrub( expImportMongoDbSource::flatten( $record, $arrays, $separator ), $plan['hidden'] );
                if ( ++$n >= $limit && $limit > 0 )
                    return;
            }
            $offset += count( $list );
            if ( !$list || $plan['paging'] === 'none' || ( $own > 0 && $page >= $own ) || ( $total !== null && $offset >= $total ) )
                return;
            switch ( $plan['paging'] )
            {
                case 'page':
                case 'offset':
                    // a short page is the last one only when the page size was asked for
                    if ( ( $plan['paging'] === 'offset' || $plan['size_param'] !== '' ) && count( $list ) < $size )
                        return;
                    break;
                case 'cursor':
                    $next = self::at( $data, $plan['cursor_path'] );
                    if ( !is_scalar( $next ) || (string)$next === '' || $next === false || isset( $seen[(string)$next] ) )
                        return;
                    $seen[(string)$next] = true;
                    $cursor = (string)$next;
                    break;
                case 'next_url':
                case 'link':
                    $next = $plan['paging'] === 'link' ? self::linkNext( $lines, $url ) : self::at( $data, $plan['next_path'] );
                    if ( !is_string( $next ) || trim( $next ) === '' )
                        return;
                    $next = expImportPlatformHttp::resolve( $url, trim( $next ) );
                    if ( !expImportPlatformHttp::sameSite( $next, $plan['url'] ) )
                        throw new expImportSourceException( 'REST API: the next page is on another site (' . expImportPlatformHttp::shown( $next )
                                                            . '); only addresses on the site of the address are followed' );
                    if ( isset( $seen[$next] ) || $next === $url )
                        return;
                    $seen[$next] = true;
                    $url = $next;
                    break;
            }
        }
    }

    /**
     * A record without any value of a secret, the basic credentials or the access token (an API that echoes the
     * request would otherwise write them into the snapshot).
     *
     * @param array $record
     * @param string[] $hidden
     * @return array
     */
    protected static function scrub( array $record, array $hidden )
    {
        $hidden = array_values( array_filter( $hidden, function ( $value ) { return strlen( (string)$value ) >= 3; } ) );
        if ( !$hidden )
            return $record;
        foreach ( $record as $name => $value )
            if ( is_string( $value ) )
                $record[$name] = str_replace( $hidden, '[secret]', $value );
        return $record;
    }

    /**
     * A message without any secret: by the name of its secret, and the derived values (basic credentials, the
     * access token) as [token].
     *
     * @param string $text
     * @param array|null $plan
     * @param string[] $refs the secrets used when there is no plan yet
     * @return string
     */
    public static function mask( $text, $plan, array $refs = array() )
    {
        $refs = $plan ? $plan['refs'] : $refs;
        $text = expImportSecrets::mask( (string)$text, $refs );
        foreach ( $plan ? $plan['hidden'] : array() as $value )
            if ( strlen( (string)$value ) >= 3 )
                $text = str_replace( array( (string)$value, rawurlencode( (string)$value ) ), '[token]', $text );
        return $text;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /** @return string[] the secret named by the options, for masking before there is a plan */
    protected static function refsOf( array $options )
    {
        $ref = isset( $options['auth_secret'] ) ? trim( (string)$options['auth_secret'] ) : '';
        return $ref !== '' && ( !isset( $options['auth'] ) || $options['auth'] !== 'none' ) ? array( $ref ) : array();
    }

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
        $plan = null;
        try
        {
            $plan = self::plan( $options );
            expImportApiHttp::writeSnapshot( self::apiRecords( $plan, $options, (int)$limit ), $target, (int)$limit, self::LABEL );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::mask( $e->getMessage(), $plan, self::refsOf( $options ) ), $e->getCode() );
        }
    }

    /**
     * The editor's "Test connection": reads the first page and reports how many records it has and their fields (and
     * the total when an answer gives it), or why it failed (never a secret). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $plan = null;
        $driver = expImportRestApiHttp::via() !== '' ? expImportRestApiHttp::via() : 'streams';
        try
        {
            $plan = self::plan( $options );
            $total = null;
            $columns = array();
            $rows = 0;
            foreach ( self::apiRecords( $plan, $options, 0, $total, 1 ) as $record )
            {
                $rows++;
                foreach ( array_keys( $record ) as $name )
                    $columns[(string)$name] = true;
            }
            $counted = $total !== null ? " ($total in all)" : '';
            if ( $rows === 0 )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => $driver, 'message' => 'connected; the first page has no record' . $counted );
            return array( 'ok' => true, 'columns' => array_keys( $columns ), 'rows' => $rows, 'driver' => $driver,
                          'message' => "connected, the first page has $rows " . ( $rows === 1 ? 'record' : 'records' ) . ' with these fields' . $counted );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => self::mask( $e->getMessage(), $plan, self::refsOf( $options ) ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        $curl = function_exists( 'curl_init' );
        return array(
            'streams' => expImportApiHttp::httpsDriver( 'HTTPS with PHP streams' ),
            'curl' => array( 'label' => 'HTTPS with curl (when allow_url_fopen is off)', 'available' => $curl, 'via' => $curl ? 'curl' : '',
                             'needs' => 'the PHP extension curl (RHEL: php-curl, Debian: php-curl); only used when allow_url_fopen is Off',
                             'worker' => '', 'worker_php' => '' ),
        );
    }
}
