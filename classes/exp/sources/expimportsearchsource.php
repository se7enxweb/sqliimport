<?php
/**
 * File containing the expImportSearchSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The documents of an Elasticsearch or OpenSearch index (or index pattern), read through the search API over HTTP.
 * One record is one document: its _source flattened to dotted field names (author.name, tags), plus _id and _index.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the search and writes the
 * documents into the run's snapshot, one JSON object per line, which the chunks of the run then read like an NDJSON
 * file.
 *
 * Only reading is possible:
 *   - the source sends only searches: GET / (the version), <index>/_search, and the point in time or scroll requests
 *     that page a search (opening and closing one); no other endpoint is ever built, and the index is a name or a
 *     pattern of letters, digits, _ . - * + and commas (no path, no remote cluster)
 *   - the import's JSON is a query (or a body with query, sort and _source only); checkBody() refuses everything
 *     else, and anywhere in it scripts (script, script_fields, runtime_mappings, scripted_metric ...)
 *   - a password, API key or token is the name of a secret (expImportSecrets), never stored in the import, and masked
 *     in every message
 *   - a server in a private network is refused like an address (sqliimport.ini [SourceSettings] AllowPrivateHosts),
 *     unless an administrator lists it in [SearchSourceSettings] AllowedHosts[]; answers are limited to
 *     [PlatformSettings] MaxResponseMegabytes, the time limit is [ImportSettings] StreamTimeout, TLS certificates are
 *     verified, and redirects to another host lose the credentials (expImportPlatformHttp)
 *
 * Paging: a point in time (Elasticsearch 7.10 and later: _pit; OpenSearch 2.4 and later: _search/point_in_time) with
 * search_after; without one, search_after on a sort whose last field is unique (_id), else a scroll (older servers).
 */
class expImportSearchSource extends expImportJsonSource
{
    /** The keys a body may have at its top. */
    const BODY_KEYS = array( 'query', 'sort', '_source' );

    /** The keys refused anywhere in a query: scripts run code on the server. */
    const FORBIDDEN_KEYS = array( 'script', 'scripts', 'script_fields', 'runtime_mappings', 'scripted_metric', 'script_score', 'stored_script' );

    /** How long a point in time or a scroll stays open between two pages. */
    const KEEP_ALIVE = '2m';

    /**
     * What drivers() sees of this PHP, for the tests: array( 'allow_url_fopen' => bool, 'openssl' => bool,
     * 'curl' => bool ). null: this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    public static function identifier()
    {
        return 'elasticsearch';
    }

    public static function label()
    {
        return 'Elasticsearch or OpenSearch index';
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
        return 'The documents of an Elasticsearch or OpenSearch index (or index pattern), read through the search API: one '
             . 'record is one document, its _source flattened to dotted field names (author.name) with _id and _index. A '
             . 'query in the query DSL selects them; scripts and every endpoint other than search are refused. The '
             . 'password or API key is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three articles of an index "articles" as the source writes it (one document per line)',
                   'file' => 'search-articles.ndjson',
                   'options' => array( 'base_url' => 'https://search.example.org:9200', 'index' => 'articles', 'key' => '_id' ) ),
            array( 'label' => 'The published articles of an Elasticsearch index, newest first, with an API key as the secret search_key',
                   'options' => array( 'base_url' => 'https://search.example.org:9200', 'index' => 'articles', 'auth' => 'api_key',
                                       'api_key_secret' => 'search_key',
                                       'query' => '{"query": {"term": {"status": "published"}}, "sort": [{"published_at": "desc"}]}',
                                       'source_fields' => "title\nbody\nauthor.name\npublished_at\ntags", 'key' => '_id' ) ),
            array( 'label' => 'All documents of the OpenSearch indices logs-2026-*, user reader with the password as the secret os_reader',
                   'options' => array( 'base_url' => 'https://opensearch.example.org', 'index' => 'logs-2026-*', 'auth' => 'basic',
                                       'user' => 'reader', 'password_secret' => 'os_reader', 'page_size' => 1000, 'key' => '_id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'required' => true,
                                 'help' => 'The address of the cluster, e.g. https://search.example.org:9200 (a private network only with [SearchSourceSettings] AllowedHosts[])' ),
            'index' => array( 'type' => 'string', 'label' => 'Index', 'required' => true, 'max' => 255,
                              'pattern' => '/^[A-Za-z0-9*][A-Za-z0-9_.*+,-]*$/',
                              'help' => 'An index, an alias or a pattern: articles, logs-2026-*, news,blog' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'none',
                             'values' => array( 'none' => 'None', 'basic' => 'User and password', 'api_key' => 'API key',
                                                'bearer' => 'Token (bearer)' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'With User and password: the user name' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'With User and password: the name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'api_key_secret' => array( 'type' => 'secret', 'label' => 'API key (name of a secret)',
                                       'help' => 'With API key: the name of the secret that holds the encoded key (the "encoded" value Elasticsearch gives)' ),
            'token_secret' => array( 'type' => 'secret', 'label' => 'Token (name of a secret)', 'help' => 'With Token: the name of the secret with the token' ),
            'query' => array( 'type' => 'json', 'label' => 'Query',
                              'help' => 'Query DSL: a query ({"term": {"status": "published"}}) or a body with query, sort and _source. Empty: all documents. Scripts are refused' ),
            'source_fields' => array( 'type' => 'list', 'label' => 'Fields',
                                      'help' => 'The fields of _source to read, one per line (author.*, title). Empty: all' ),
            'sort' => array( 'type' => 'string', 'label' => 'Sort', 'max' => 500, 'pattern' => '/^[A-Za-z0-9_.@:, -]*$/',
                             'help' => 'Fields with :asc or :desc, comma separated: published_at:desc,_id. Empty: the sort of the query, else the index order' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 1, 'max' => 10000,
                                  'help' => 'Documents per request' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'auto',
                               'values' => array( 'auto' => 'Automatic (by the server version)', 'pit' => 'Point in time and search_after',
                                                  'search_after' => 'search_after on the sort (its last field must be unique)',
                                                  'scroll' => 'Scroll (older servers)' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_id',
                            'help' => 'The field that names a document; _id is its ID in the index' ),
        );
    }

    /** The snapshot is always one document per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether the query of an import only searches: a query clause, or a body with query, sort and _source only, and
     * no script anywhere.
     *
     * @param mixed $query the decoded JSON of the option (an empty array: all documents)
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkBody( $query )
    {
        if ( $query === null || $query === array() || $query === '' )
            return null;
        if ( !is_array( $query ) || array_values( $query ) === $query )
            return 'the query must be a JSON object';
        $body = self::isBody( $query ) ? $query : array( 'query' => $query );
        foreach ( array_keys( $body ) as $key )
            if ( !in_array( (string)$key, self::BODY_KEYS, true ) )
                return "the query may have only query, sort and _source at its top, not \"$key\"";
        $found = self::forbiddenKey( $body );
        if ( $found !== null )
            return "the query contains \"$found\": scripts are not allowed, only searching";
        if ( isset( $body['_source'] ) && !is_bool( $body['_source'] ) && !is_string( $body['_source'] ) && !is_array( $body['_source'] ) )
            return '_source must be true, false, a field or a list of fields';
        return null;
    }

    /** @return bool whether a JSON object is a whole body (has query, sort or _source) rather than a query clause */
    protected static function isBody( array $query )
    {
        foreach ( self::BODY_KEYS as $key )
            if ( array_key_exists( $key, $query ) )
                return true;
        // a key a body has but a query has not (size, aggs, script_fields ...): checked as a body, and refused
        foreach ( array_keys( $query ) as $key )
            if ( in_array( (string)$key, array( 'size', 'from', 'aggs', 'aggregations', 'script_fields', 'runtime_mappings', 'pit',
                                               'search_after', 'stored_fields', 'docvalue_fields', 'suggest', 'rescore', 'highlight',
                                               'collapse', 'post_filter', 'track_total_hits', 'fields', 'knn', 'ext' ), true ) )
                return true;
        return false;
    }

    /** @return string|null the first key of FORBIDDEN_KEYS anywhere in a decoded JSON value */
    protected static function forbiddenKey( $value )
    {
        if ( !is_array( $value ) )
            return null;
        foreach ( $value as $key => $item )
        {
            if ( is_string( $key ) && in_array( strtolower( $key ), self::FORBIDDEN_KEYS, true ) )
                return $key;
            $found = self::forbiddenKey( $item );
            if ( $found !== null )
                return $found;
        }
        return null;
    }

    /**
     * The search body of the options (without size, paging and point in time).
     *
     * @param array $options
     * @return array
     * @throws expImportSourceException
     */
    public static function body( array $options )
    {
        $query = isset( $options['query'] ) ? $options['query'] : array();
        if ( is_string( $query ) )
            $query = trim( $query ) === '' ? array() : json_decode( $query, true );
        $refusal = self::checkBody( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Search: ' . $refusal );
        $body = !$query ? array() : ( self::isBody( $query ) ? $query : array( 'query' => $query ) );
        if ( !isset( $body['query'] ) )
            $body['query'] = array( 'match_all' => new stdClass() );
        $sort = self::sortOf( isset( $options['sort'] ) ? (string)$options['sort'] : '' );
        if ( $sort )
            $body['sort'] = $sort;
        $fields = isset( $options['source_fields'] ) ? array_values( array_filter( array_map( 'trim', (array)$options['source_fields'] ), 'strlen' ) ) : array();
        if ( $fields )
            $body['_source'] = $fields;
        return self::objectify( $body );
    }

    /** The keys of the query DSL whose value is a list, so an empty one stays [] (every other empty value is {}). */
    const LIST_KEYS = array( 'must', 'should', 'filter', 'must_not', 'sort', '_source', 'fields', 'values', 'includes', 'excludes',
                             'search_after', 'like', 'unlike', 'docs', 'queries', 'ids' );

    /**
     * The decoded JSON of an import (assoc arrays) ready to be encoded again: an empty array becomes an empty object
     * ({"match_all": {}}), except under a key whose value is a list.
     *
     * @param array $value
     * @param string $key
     * @return array|stdClass
     */
    protected static function objectify( array $value, $key = '' )
    {
        if ( $value === array() )
            return in_array( $key, self::LIST_KEYS, true ) ? array() : new stdClass();
        foreach ( $value as $k => $item )
            if ( is_array( $item ) )
                $value[$k] = self::objectify( $item, is_string( $k ) ? $k : '' );
        return $value;
    }

    /**
     * The sort of the Sort option: "published_at:desc,_id" as array( array( 'published_at' => array( 'order' => 'desc' ) ), '_id' ).
     *
     * @param string $text
     * @return array
     * @throws expImportSourceException
     */
    public static function sortOf( $text )
    {
        $sort = array();
        foreach ( explode( ',', (string)$text ) as $part )
        {
            $part = trim( $part );
            if ( $part === '' )
                continue;
            if ( !preg_match( '/^([A-Za-z0-9_.@-]+)(?:\s*:\s*(asc|desc))?$/i', $part, $m ) )
                throw new expImportSourceException( "Search: the sort \"$part\" is not of the form field or field:asc or field:desc" );
            $sort[] = isset( $m[2] ) && $m[2] !== '' ? array( $m[1] => array( 'order' => strtolower( $m[2] ) ) ) : $m[1];
        }
        return $sort;
    }

    /** @return bool whether the last field of a sort is unique (_id), so search_after alone pages without a point in time */
    protected static function sortEndsUnique( array $sort )
    {
        if ( !$sort )
            return false;
        $last = end( $sort );
        $field = is_array( $last ) ? (string)key( $last ) : (string)$last;
        return in_array( $field, array( '_id', '_doc', '_shard_doc' ), true );
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The address, headers and trusted hosts of the options, after the checks of address and index.
     *
     * @param array $options
     * @param string[] $used set to the secrets used, for masking
     * @return array the list ( string base address, array headers, string[] trusted hosts, string index )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $base = rtrim( isset( $options['base_url'] ) ? trim( (string)$options['base_url'] ) : '', '/' );
        $parts = parse_url( $base );
        if ( $base === '' || !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            throw new expImportSourceException( 'Search: the server address must be an http or https address' );
        if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) )
            throw new expImportSourceException( 'Search: the server address may not have a query, a fragment or credentials (use Authentication)' );
        $trusted = (array)expImportPlatformHttp::setting( 'SearchSourceSettings', 'AllowedHosts', array() );
        $refusal = expImportSourceFiles::refuseHost( $parts['host'], $trusted );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Search: ' . $refusal . ' (or list it in sqliimport.ini [SearchSourceSettings] AllowedHosts[])' );
        $index = isset( $options['index'] ) ? trim( (string)$options['index'] ) : '';
        if ( !preg_match( '/^[A-Za-z0-9*][A-Za-z0-9_.*+,-]*$/', $index ) || strpos( $index, '..' ) !== false )
            throw new expImportSourceException( 'Search: the index must be a name or a pattern (letters, digits, _ . - * + and commas; no path, no remote cluster)' );
        $headers = array();
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'none';
        if ( $auth === 'api_key' )
        {
            $ref = isset( $options['api_key_secret'] ) ? trim( (string)$options['api_key_secret'] ) : '';
            $used[] = $ref;
            $headers['Authorization'] = 'ApiKey ' . expImportSecrets::required( $ref, 'Search: the API key' );
        }
        elseif ( $auth === 'basic' || $auth === 'bearer' )
            $headers = expImportPlatformHttp::authHeaders( $options, $used );
        return array( $base, $headers, $trusted, $index );
    }

    /**
     * One request to the server, its JSON answer decoded; an error with the server's reason for an HTTP error.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param array|null $body
     * @param string[] $trusted
     * @return array
     * @throws expImportSourceException
     */
    protected static function call( $method, $url, array $headers, $body, array $trusted )
    {
        $headers += array( 'Accept' => 'application/json' );
        if ( $body !== null )
            $headers['Content-Type'] = 'application/json';
        $answer = expImportPlatformHttp::request( $method, $url, $headers,
                                                  $body === null ? null : (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $trusted );
        $status = (int)$answer['status'];
        $data = json_decode( (string)$answer['body'], true );
        $shown = expImportPlatformHttp::shown( $url );
        if ( $status < 200 || $status >= 300 )
        {
            $reason = '';
            if ( is_array( $data ) && isset( $data['error'] ) )
            {
                $error = $data['error'];
                if ( is_array( $error ) )
                {
                    $cause = isset( $error['root_cause'][0] ) && is_array( $error['root_cause'][0] ) ? $error['root_cause'][0] : $error;
                    $reason = trim( ( isset( $cause['type'] ) ? $cause['type'] . ': ' : '' ) . ( isset( $cause['reason'] ) ? (string)$cause['reason'] : '' ), ': ' );
                }
                else
                    $reason = (string)$error;
            }
            if ( $status === 401 || $status === 403 )
                $reason = 'the access was refused (check the authentication and the rights of the user or key)' . ( $reason !== '' ? '; ' . $reason : '' );
            throw new expImportSourceException( "Search: $shown answered $status" . ( $reason !== '' ? ': ' . substr( $reason, 0, 300 ) : '' ) );
        }
        if ( !is_array( $data ) )
            throw new expImportSourceException( "Search: $shown did not answer with JSON" );
        return $data;
    }

    /**
     * The server's distribution and version (GET /), null when the user may not read it (no cluster rights).
     *
     * @return array|null array( 'distribution' => elasticsearch or opensearch, 'version' => string, 'cluster' => string )
     * @throws expImportSourceException
     */
    protected static function info( $base, array $headers, array $trusted )
    {
        try
        {
            $data = self::call( 'GET', $base . '/', $headers, null, $trusted );
        }
        catch ( expImportSourceException $e )
        {
            if ( preg_match( '/answered (401|403)\b/', $e->getMessage() ) )
                return null;
            throw $e;
        }
        $version = isset( $data['version']['number'] ) ? (string)$data['version']['number'] : '';
        $distribution = isset( $data['version']['distribution'] ) && $data['version']['distribution'] === 'opensearch' ? 'opensearch' : 'elasticsearch';
        return array( 'distribution' => $distribution, 'version' => $version, 'cluster' => isset( $data['cluster_name'] ) ? (string)$data['cluster_name'] : '' );
    }

    /**
     * How a search is paged: pit_es, pit_os, search_after or scroll.
     *
     * @param string $paging the option: auto, pit, search_after or scroll
     * @param array|null $info the server (info())
     * @param array $sort the sort of the body
     * @return string
     * @throws expImportSourceException
     */
    public static function pagingMode( $paging, $info, array $sort )
    {
        $os = $info && $info['distribution'] === 'opensearch';
        switch ( $paging )
        {
            case 'pit':
                return $os ? 'pit_os' : 'pit_es';
            case 'search_after':
                if ( !self::sortEndsUnique( $sort ) )
                    throw new expImportSourceException( 'Search: paging with search_after needs a sort whose last field is unique (add _id to the sort)' );
                return 'search_after';
            case 'scroll':
                return 'scroll';
        }
        if ( $info && $info['version'] !== '' )
        {
            if ( !$os && version_compare( $info['version'], '7.10.0', '>=' ) )
                return 'pit_es';
            // OpenSearch has no implicit tie-breaker in a point in time: only with a sort of the import
            if ( $os && version_compare( $info['version'], '2.4.0', '>=' ) && $sort )
                return 'pit_os';
        }
        return self::sortEndsUnique( $sort ) ? 'search_after' : 'scroll';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Searches and writes the documents into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most documents (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::documents( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'Search: the snapshot cannot be written' );
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
     * The flattened documents of the search, page by page; a point in time or a scroll is closed at the end.
     *
     * @param array $options
     * @param int $limit 0: all
     * @param string[] $used the secrets used (for masking messages)
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function documents( array $options, $limit, array &$used )
    {
        list( $base, $headers, $trusted, $index ) = self::connection( $options, $used );
        $body = self::body( $options );
        $size = max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 500 ) );
        if ( $limit > 0 )
            $size = min( $size, $limit );
        $paging = isset( $options['paging'] ) ? (string)$options['paging'] : 'auto';
        $info = $paging === 'auto' || $paging === 'pit' ? self::info( $base, $headers, $trusted ) : null;
        $sort = isset( $body['sort'] ) ? (array)$body['sort'] : array();
        $mode = self::pagingMode( $paging, $info, $sort );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'SearchSourceSettings', 'MaxPages', 1000 ) );
        $body['size'] = $size;
        $body['track_total_hits'] = false;
        $pit = null;
        $scroll = null;
        $n = 0;
        try
        {
            if ( $mode === 'pit_es' )
            {
                $opened = self::call( 'POST', $base . '/' . $index . '/_pit?keep_alive=' . self::KEEP_ALIVE, $headers, null, $trusted );
                $pit = isset( $opened['id'] ) ? (string)$opened['id'] : '';
            }
            elseif ( $mode === 'pit_os' )
            {
                $opened = self::call( 'POST', $base . '/' . $index . '/_search/point_in_time?keep_alive=' . self::KEEP_ALIVE, $headers, null, $trusted );
                $pit = isset( $opened['pit_id'] ) ? (string)$opened['pit_id'] : '';
            }
            if ( $pit === '' )
                throw new expImportSourceException( 'Search: the server opened no point in time (choose the paging search_after or scroll)' );
            if ( $mode === 'scroll' && !$sort )
                $body['sort'] = array( '_doc' );
            $after = null;
            for ( $page = 1; $page <= $maxPages; $page++ )
            {
                $request = $body;
                if ( $pit !== null )
                {
                    $request['pit'] = array( 'id' => $pit, 'keep_alive' => self::KEEP_ALIVE );
                    if ( $mode === 'pit_es' && !$sort )
                        $request['sort'] = array( '_shard_doc' );
                }
                if ( $after !== null )
                    $request['search_after'] = $after;
                if ( $mode === 'scroll' && $scroll !== null )
                    $answer = self::call( 'POST', $base . '/_search/scroll', $headers, array( 'scroll' => self::KEEP_ALIVE, 'scroll_id' => $scroll ), $trusted );
                elseif ( $mode === 'scroll' )
                    $answer = self::call( 'POST', $base . '/' . $index . '/_search?scroll=' . self::KEEP_ALIVE, $headers, $request, $trusted );
                elseif ( $pit !== null )
                    $answer = self::call( 'POST', $base . '/_search', $headers, $request, $trusted );
                else
                    $answer = self::call( 'POST', $base . '/' . $index . '/_search', $headers, $request, $trusted );
                if ( isset( $answer['_scroll_id'] ) )
                    $scroll = (string)$answer['_scroll_id'];
                if ( $pit !== null && isset( $answer['pit_id'] ) && is_string( $answer['pit_id'] ) && $answer['pit_id'] !== '' )
                    $pit = $answer['pit_id'];
                if ( !isset( $answer['hits']['hits'] ) || !is_array( $answer['hits']['hits'] ) )
                    throw new expImportSourceException( 'Search: the answer has no hits (' . substr( (string)json_encode( array_keys( $answer ) ), 0, 100 ) . ')' );
                $hits = $answer['hits']['hits'];
                foreach ( $hits as $hit )
                {
                    yield self::document( $hit );
                    if ( $limit > 0 && ++$n >= $limit )
                        return;
                }
                if ( count( $hits ) < $size )
                    return;
                if ( $mode !== 'scroll' )
                {
                    $last = end( $hits );
                    if ( !isset( $last['sort'] ) || !is_array( $last['sort'] ) )
                        throw new expImportSourceException( 'Search: the documents have no sort values to page with (give a sort)' );
                    $after = $last['sort'];
                }
            }
        }
        finally
        {
            // releasing the server's search context; a failure here changes nothing for the run
            try
            {
                if ( $mode === 'pit_es' && $pit )
                    self::call( 'DELETE', $base . '/_pit', $headers, array( 'id' => $pit ), $trusted );
                elseif ( $mode === 'pit_os' && $pit )
                    self::call( 'DELETE', $base . '/_search/point_in_time', $headers, array( 'pit_id' => array( $pit ) ), $trusted );
                elseif ( $mode === 'scroll' && $scroll )
                    self::call( 'DELETE', $base . '/_search/scroll', $headers, array( 'scroll_id' => array( $scroll ) ), $trusted );
            }
            catch ( Exception $e )
            {
            }
        }
    }

    /**
     * A hit as a record: _id, _index and the _source flattened to dotted names.
     *
     * @param mixed $hit
     * @return array
     */
    public static function document( $hit )
    {
        $hit = is_array( $hit ) ? $hit : array();
        $record = array( '_id' => isset( $hit['_id'] ) ? (string)$hit['_id'] : '', '_index' => isset( $hit['_index'] ) ? (string)$hit['_index'] : '' );
        $source = isset( $hit['_source'] ) && is_array( $hit['_source'] ) ? $hit['_source'] : array();
        foreach ( self::flatten( $source ) as $name => $value )
            if ( !array_key_exists( $name, $record ) )
                $record[$name] = $value;
        return $record;
    }

    /**
     * Nested objects as dotted names: array( 'author' => array( 'name' => 'A' ) ) gives author.name. A list of plain
     * values stays a list (tags); a list of objects is numbered (images.0.url).
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    public static function flatten( array $data, $prefix = '' )
    {
        $out = array();
        foreach ( $data as $key => $value )
        {
            $name = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if ( is_array( $value ) && $value !== array() )
            {
                if ( array_values( $value ) === $value )
                {
                    $plain = true;
                    foreach ( $value as $item )
                        if ( is_array( $item ) )
                            $plain = false;
                    if ( $plain )
                    {
                        $out[$name] = $value;
                        continue;
                    }
                }
                foreach ( self::flatten( $value, $name ) as $inner => $item )
                    $out[$inner] = $item;
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }

    /**
     * The editor's "Test connection": the server's version (GET /) and a search for one document, whose fields are
     * reported, or why it failed (never a password or key: the message is masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'Elasticsearch or OpenSearch';
        try
        {
            list( $base, $headers, $trusted, $index ) = self::connection( $options, $used );
            $body = self::body( $options );
            $info = self::info( $base, $headers, $trusted );
            if ( $info )
                $driver = ( $info['distribution'] === 'opensearch' ? 'OpenSearch' : 'Elasticsearch' ) . ( $info['version'] !== '' ? ' ' . $info['version'] : '' );
            $body['size'] = 1;
            $answer = self::call( 'POST', $base . '/' . $index . '/_search', $headers, $body, $trusted );
            $hits = isset( $answer['hits']['hits'] ) && is_array( $answer['hits']['hits'] ) ? $answer['hits']['hits'] : array();
            $columns = $hits ? array_map( 'strval', array_keys( self::document( reset( $hits ) ) ) ) : array();
            $where = $info ? 'connected to ' . $driver . ( $info['cluster'] !== '' ? ' (cluster ' . $info['cluster'] . ')' : '' )
                           : 'connected (the version is not readable for this user: paging falls back to search_after or scroll unless Paging is set)';
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $hits ), 'driver' => $driver,
                          'message' => $where . ( $hits ? '; the search gives these fields' : '; the search finds no document' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /**
     * How this PHP reaches the server: PHP's http and https streams (which the source uses), with what the host needs
     * for a missing one and what the job queue's workers last had (expImportSqlSource::recordWorkerPhp()).
     *
     * @return array id => array( 'label', 'available' => bool, 'via' => string, 'needs' => string,
     *               'worker' => yes, no or '' (not known), 'worker_php' => string )
     */
    public static function drivers()
    {
        $env = is_array( self::$environment ) ? self::$environment : array();
        $fopen = isset( $env['allow_url_fopen'] ) ? (bool)$env['allow_url_fopen'] : (bool)ini_get( 'allow_url_fopen' );
        $openssl = isset( $env['openssl'] ) ? (bool)$env['openssl'] : extension_loaded( 'openssl' );
        $list = array(
            'http' => array( 'label' => 'http:// addresses', 'via' => $fopen ? 'PHP http stream' : '',
                             'needs' => 'allow_url_fopen=On in the php.ini of the web server and of the job queue\'s workers' ),
            'https' => array( 'label' => 'https:// addresses (certificates verified)', 'via' => $fopen && $openssl ? 'PHP https stream (openssl)' : '',
                              'needs' => 'allow_url_fopen=On and the PHP extension openssl (RHEL and Debian: part of the php package)' ),
        );
        return self::withWorker( $list, 'search' );
    }

    /**
     * Driver rows with 'available' and what the job queue's workers had (noted by expImportSqlSource::recordWorkerPhp()
     * under "<prefix>.<id>").
     *
     * @param array $list id => array( label, via, needs )
     * @param string $prefix
     * @return array
     */
    public static function withWorker( array $list, $prefix )
    {
        $worker = expImportSqlSource::workerPhp();
        foreach ( $list as $id => $row )
        {
            $key = $prefix . '.' . $id;
            $list[$id]['available'] = $row['via'] !== '';
            $list[$id]['worker'] = $worker && isset( $worker['drivers'][$key] ) ? ( $worker['drivers'][$key] !== '' ? 'yes' : 'no' ) : '';
            $list[$id]['worker_php'] = $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '';
        }
        return $list;
    }
}
