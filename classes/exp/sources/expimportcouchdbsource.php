<?php
/**
 * File containing the expImportCouchDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The documents of a CouchDB database (also PouchDB servers and IBM Cloudant, which speak the same API), read over
 * HTTP: all documents (_all_docs), the rows of a view (_design/<doc>/_view/<view>), or the answer of a Mango query
 * (_find). One record is one document, nested objects flattened to dotted names (author.name) as the MongoDB source
 * does, with _id and _rev kept.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the documents and writes
 * them into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible:
 *   - the only requests are GET / (the version), POST /_session (cookie authentication), GET or POST
 *     <db>/_all_docs, POST <db>/_find and GET or POST <db>/_design/<doc>/_view/<view>; allowedRequest() checks every
 *     request before it is sent and refuses anything else (_bulk_docs, _update, _design writes, PUT, DELETE ...)
 *   - the database, design document and view are names, never a path; a Mango selector may only use the query
 *     operators of Mango (checkSelector(), at any depth)
 *   - the password is the name of a secret (expImportSecrets), never stored in the import, and masked in every
 *     message, as is a session cookie
 *   - a server in a private network is refused unless [CouchDbSourceSettings] AllowedHosts[] lists it
 *
 * Paging: _all_docs and views ask for one row more than a page and start the next page at that row (startkey, and
 * startkey_docid for a view); Mango pages with the bookmark of each answer; keys are sent in slices of a page.
 */
class expImportCouchDbSource extends expImportJsonSource
{
    /** The operators a Mango selector may use (the combination and condition operators of CouchDB 3). */
    const MANGO_OPERATORS = array( '$and', '$or', '$not', '$nor', '$all', '$elemmatch', '$allmatch', '$keymapmatch', '$lt', '$lte',
                                   '$eq', '$ne', '$gte', '$gt', '$exists', '$type', '$in', '$nin', '$size', '$mod', '$regex',
                                   '$beginswith' );

    const LABEL = 'CouchDB';

    public static function identifier()
    {
        return 'couchdb';
    }

    public static function label()
    {
        return 'CouchDB database';
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
        return 'The documents of a CouchDB database (or Cloudant), read over HTTP: all documents, the rows of a view or the '
             . 'answer of a Mango query. One record is one document with _id and _rev, nested objects as dotted fields '
             . '(author.name). Only reading requests are sent; the password is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'couchdb-articles.ndjson: what a run reads from a database of articles (three documents, flattened), to try the mapping',
                   'file' => 'couchdb-articles.ndjson', 'options' => array( 'base_url' => 'https://couch.example.org:6984', 'database' => 'articles', 'key' => '_id' ) ),
            array( 'label' => 'The published articles of the database articles on couch.example.org by a Mango query, user reader, the password as the secret couch_reader',
                   'options' => array( 'base_url' => 'https://couch.example.org:6984', 'database' => 'articles', 'auth' => 'basic', 'user' => 'reader',
                                       'password_secret' => 'couch_reader', 'mode' => 'mango', 'selector' => '{"type": "article", "status": {"$eq": "published"}}',
                                       'fields' => "_id\n_rev\ntitle\nauthor\ntags", 'key' => '_id' ) ),
            array( 'label' => 'The rows of the view by_date of the design document news, with their documents, cookie authentication',
                   'options' => array( 'base_url' => 'https://couch.example.org:6984', 'database' => 'news', 'auth' => 'cookie', 'user' => 'reader',
                                       'password_secret' => 'couch_reader', 'mode' => 'view', 'design' => 'news', 'view' => 'by_date',
                                       'startkey' => '"2026-01-01"', 'include_docs' => true, 'key' => '_id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'required' => true,
                                 'help' => 'The address of CouchDB, e.g. https://couch.example.org:6984 (a private network only with [CouchDbSourceSettings] AllowedHosts[])' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'required' => true, 'max' => 238, 'pattern' => '/^[a-z][a-z0-9_$()+-]*$/',
                                 'help' => 'The name of the database: small letters, digits and _ $ ( ) + -; no path' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'none',
                             'values' => array( 'none' => 'None', 'basic' => 'User and password (basic)', 'cookie' => 'User and password (cookie session)' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255 ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'mode' => array( 'type' => 'enum', 'label' => 'Read', 'default' => 'all_docs',
                             'values' => array( 'all_docs' => 'All documents (_all_docs)', 'view' => 'A view (_design/<doc>/_view/<view>)', 'mango' => 'A Mango query (_find)' ) ),
            'design' => array( 'type' => 'string', 'label' => 'Design document', 'max' => 255, 'pattern' => '#^(_design/)?[A-Za-z0-9_][A-Za-z0-9_.-]*$|^$#',
                               'help' => 'With a view: the name of the design document (news or _design/news)' ),
            'view' => array( 'type' => 'string', 'label' => 'View', 'max' => 255, 'pattern' => '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$|^$/', 'help' => 'With a view: its name' ),
            'include_docs' => array( 'type' => 'bool', 'label' => 'With the documents', 'default' => true,
                                     'help' => 'All documents and views: read each document (else only the ID, the revision or the key and value of the row)' ),
            'reduce' => array( 'type' => 'bool', 'label' => 'Reduce', 'default' => false,
                               'help' => 'A view with a reduce function: read its results grouped by key (no documents)' ),
            'keys' => array( 'type' => 'text', 'label' => 'Keys (JSON list)', 'help' => 'All documents and views: only these keys (IDs), e.g. ["a-1", "a-2"]' ),
            'startkey' => array( 'type' => 'text', 'label' => 'Start key (JSON)', 'help' => 'All documents and views: the first key, as JSON ("2026-01-01", ["news", 1])' ),
            'endkey' => array( 'type' => 'text', 'label' => 'End key (JSON)', 'help' => 'All documents and views: the last key, as JSON' ),
            'selector' => array( 'type' => 'text', 'label' => 'Selector (JSON)',
                                 'help' => 'Mango: a selector, e.g. {"type": "article", "year": {"$gte": 2025}}; empty: every document' ),
            'fields' => array( 'type' => 'list', 'label' => 'Fields', 'help' => 'Mango: the fields to read, one per line (author.name); empty: all' ),
            'sort' => array( 'type' => 'text', 'label' => 'Sort (JSON)', 'help' => 'Mango: e.g. [{"published_at": "desc"}] (needs an index on the fields)' ),
            'include_design' => array( 'type' => 'bool', 'label' => 'Design documents too', 'default' => false,
                                       'help' => 'All documents: read the design documents (_design/...) as well' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 1, 'max' => 10000, 'help' => 'Documents per request' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most documents', 'default' => 0, 'min' => 0, 'help' => '0: every document' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of objects stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_id', 'help' => 'The field that names a document; _id is its ID' ),
        );
    }

    /** The snapshot is always one document per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the checks --------------------------------------------------------------------------------------------------

    /**
     * Whether a request may be sent: only the reading requests of the source, by method and path (after the server
     * address).
     *
     * @param string $method
     * @param string $path e.g. /articles/_all_docs (without the query)
     * @return bool
     */
    public static function allowedRequest( $method, $path )
    {
        $method = strtoupper( (string)$method );
        $path = (string)$path;
        $db = '[a-z][a-z0-9_%+-]*';
        $name = '[A-Za-z0-9_][A-Za-z0-9_.-]*';
        if ( $method === 'GET' && ( $path === '' || $path === '/' ) )
            return true;
        if ( $method === 'POST' && $path === '/_session' )
            return true;
        if ( !in_array( $method, array( 'GET', 'POST' ), true ) )
            return false;
        if ( preg_match( "#^/$db/_all_docs$#", $path ) || preg_match( "#^/$db/_design/$name/_view/$name$#", $path ) )
            return true;
        return $method === 'POST' && preg_match( "#^/$db/_find$#", $path ) === 1;
    }

    /**
     * Whether a Mango selector only uses Mango's query operators, at any depth.
     *
     * @param mixed $selector
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkSelector( $selector )
    {
        if ( $selector === null || $selector === array() )
            return null;
        if ( !is_array( $selector ) || ( array_values( $selector ) === $selector ) )
            return 'the selector must be a JSON object';
        return self::checkOperators( $selector );
    }

    /** @return string|null the first key that is an operator Mango does not have */
    protected static function checkOperators( array $value )
    {
        foreach ( $value as $key => $item )
        {
            if ( is_string( $key ) && $key !== '' && $key[0] === '$' && !in_array( strtolower( $key ), self::MANGO_OPERATORS, true ) )
                return "the selector uses $key, which is not a query operator of Mango";
            if ( is_array( $item ) && ( $found = self::checkOperators( $item ) ) !== null )
                return $found;
        }
        return null;
    }

    /**
     * Everything a read needs, checked.
     *
     * @param array $options
     * @param string[] $used set to the secrets used
     * @param string[] $hidden set to other values to hide (the session cookie)
     * @return array base, trusted, headers, db (encoded), mode, page, limit, and per mode keys, startkey, endkey, selector, fields, sort
     * @throws expImportSourceException
     */
    public static function plan( array $options, array &$used, array &$hidden )
    {
        $label = self::LABEL;
        list( $base, $trusted ) = expImportDocumentHttp::base( isset( $options['base_url'] ) ? $options['base_url'] : '', 'CouchDbSourceSettings', $label );
        $database = isset( $options['database'] ) ? trim( (string)$options['database'] ) : '';
        if ( !preg_match( '/^[a-z][a-z0-9_$()+-]{0,237}$/', $database ) )
            throw new expImportSourceException( "$label: the database must be a name (small letters, digits and _ \$ ( ) + -), not a path" );
        $mode = isset( $options['mode'] ) && in_array( $options['mode'], array( 'view', 'mango' ), true ) ? (string)$options['mode'] : 'all_docs';
        $plan = array( 'base' => $base, 'trusted' => $trusted, 'db' => rawurlencode( $database ), 'mode' => $mode,
                       'page' => max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 500 ) ),
                       'limit' => isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0,
                       'include_docs' => !isset( $options['include_docs'] ) || !empty( $options['include_docs'] ),
                       'reduce' => !empty( $options['reduce'] ), 'include_design' => !empty( $options['include_design'] ) );
        if ( $mode === 'view' )
        {
            foreach ( array( 'design', 'view' ) as $name )
            {
                $value = isset( $options[$name] ) ? trim( (string)$options[$name] ) : '';
                if ( strpos( $value, '_design/' ) === 0 )
                    $value = substr( $value, 8 );
                if ( !preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,254}$/', $value ) )
                    throw new expImportSourceException( "$label: a view needs the name of its design document and its own name (letters, digits, _ . -)" );
                $plan[$name] = $value;
            }
            if ( $plan['reduce'] )
                $plan['include_docs'] = false;
        }
        if ( $mode === 'mango' )
        {
            $selector = expImportDocumentHttp::jsonOption( isset( $options['selector'] ) ? $options['selector'] : '', $label, 'selector' );
            $refusal = self::checkSelector( $selector );
            if ( $refusal !== null )
                throw new expImportSourceException( "$label: $refusal" );
            $sort = expImportDocumentHttp::jsonOption( isset( $options['sort'] ) ? $options['sort'] : '', $label, 'sort' );
            if ( $sort !== null && ( !is_array( $sort ) || array_values( $sort ) !== $sort ) )
                throw new expImportSourceException( "$label: the sort must be a JSON list, e.g. [{\"published_at\": \"desc\"}]" );
            $plan['selector'] = $selector ?: new stdClass();
            $plan['sort'] = $sort;
            $plan['fields'] = isset( $options['fields'] ) ? array_values( array_filter( array_map( 'trim', (array)$options['fields'] ), 'strlen' ) ) : array();
        }
        else
        {
            $keys = expImportDocumentHttp::jsonOption( isset( $options['keys'] ) ? $options['keys'] : '', $label, 'keys' );
            if ( $keys !== null && ( !is_array( $keys ) || array_values( $keys ) !== $keys ) )
                throw new expImportSourceException( "$label: the keys must be a JSON list, e.g. [\"a-1\", \"a-2\"]" );
            $plan['keys'] = $keys;
            foreach ( array( 'startkey', 'endkey' ) as $name )
            {
                $text = isset( $options[$name] ) ? trim( (string)$options[$name] ) : '';
                if ( $text !== '' && json_decode( $text ) === null && $text !== 'null' )
                    throw new expImportSourceException( "$label: the $name must be JSON, e.g. \"2026-01-01\" with the quotes" );
                $plan[$name] = $text;
            }
        }
        $plan['headers'] = self::authHeaders( $options, $plan, $used, $hidden );
        return $plan;
    }

    /**
     * The headers of the authentication: basic, or the cookie of a session opened with POST /_session.
     *
     * @param array $options
     * @param array $plan
     * @param string[] $used
     * @param string[] $hidden
     * @return array name => value
     * @throws expImportSourceException
     */
    protected static function authHeaders( array $options, array $plan, array &$used, array &$hidden )
    {
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'none';
        if ( $auth !== 'basic' && $auth !== 'cookie' )
            return array();
        $user = isset( $options['user'] ) ? (string)$options['user'] : '';
        $ref = isset( $options['password_secret'] ) ? trim( (string)$options['password_secret'] ) : '';
        $used[] = $ref;
        $password = expImportSecrets::required( $ref, self::LABEL . ': the password' );
        if ( $auth === 'basic' )
        {
            $header = base64_encode( $user . ':' . $password );
            $hidden[] = $header;
            return array( 'Authorization' => 'Basic ' . $header );
        }
        list( , $lines ) = self::request( $plan, 'POST', '/_session', array(), array( 'Content-Type' => 'application/json' ),
                                          array( 'name' => $user, 'password' => $password ) );
        foreach ( expImportDocumentHttp::headers( $lines, 'Set-Cookie' ) as $cookie )
            if ( preg_match( '/^AuthSession=([^;]*)/', $cookie, $m ) && $m[1] !== '' )
            {
                $hidden[] = $m[1];
                return array( 'Cookie' => 'AuthSession=' . $m[1] );
            }
        throw new expImportSourceException( self::LABEL . ': the server opened no session (no AuthSession cookie); choose the basic authentication' );
    }

    /**
     * One request of the source, after allowedRequest().
     *
     * @param array $plan base, trusted (and headers when there are)
     * @param string $method
     * @param string $path
     * @param array $query name => value
     * @param array $headers
     * @param mixed $body encoded as JSON (null: none)
     * @return array ( answer, header lines )
     * @throws expImportSourceException
     */
    protected static function request( array $plan, $method, $path, array $query, array $headers, $body = null )
    {
        if ( !self::allowedRequest( $method, $path ) )
            throw new expImportSourceException( self::LABEL . ": the request $method $path is not one of the reading requests of the source" );
        $url = $plan['base'] . $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
        if ( $body !== null )
            $headers['Content-Type'] = 'application/json';
        return expImportDocumentHttp::call( self::LABEL, $method, $url, $headers + ( isset( $plan['headers'] ) ? $plan['headers'] : array() ),
                                            $body === null ? null : (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $plan['trusted'] );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the documents into the snapshot, one flattened JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most documents (the editor's sample); 0: all
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
     * The flattened documents, page by page.
     *
     * @param array $options
     * @param int $limit 0: all
     * @param string[] $used
     * @param string[] $hidden
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
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'CouchDbSourceSettings', 'MaxPages', 10000 ) );
        $n = 0;
        $rows = $plan['mode'] === 'mango' ? self::mangoRows( $plan, $limit, $maxPages ) : self::rows( $plan, $limit, $maxPages );
        foreach ( $rows as $document )
        {
            yield expImportMongoDbSource::flatten( $document, $arrays, $separator );
            if ( $limit > 0 && ++$n >= $limit )
                return;
        }
    }

    /**
     * The documents (or rows) of _all_docs or a view: limit+1 per page, the next page from the extra row; with keys,
     * the keys in slices of a page.
     *
     * @return Generator array
     */
    protected static function rows( array $plan, $limit, $maxPages )
    {
        $page = $limit > 0 ? min( $plan['page'], $limit ) : $plan['page'];
        $path = $plan['mode'] === 'view' ? '/' . $plan['db'] . '/_design/' . rawurlencode( $plan['design'] ) . '/_view/' . rawurlencode( $plan['view'] )
                                         : '/' . $plan['db'] . '/_all_docs';
        $query = array();
        if ( $plan['include_docs'] )
            $query['include_docs'] = 'true';
        if ( $plan['mode'] === 'view' )
        {
            $query['reduce'] = $plan['reduce'] ? 'true' : 'false';
            if ( $plan['reduce'] )
                $query['group'] = 'true';
        }
        if ( $plan['endkey'] !== '' )
            $query['endkey'] = $plan['endkey'];
        if ( $plan['keys'] !== null )
        {
            $slices = array_chunk( $plan['keys'], $page );
            foreach ( array_slice( $slices, 0, $maxPages ) as $slice )
            {
                list( $answer ) = self::request( $plan, 'POST', $path, $query, array(), array( 'keys' => $slice ) );
                foreach ( self::answerRows( $answer ) as $row )
                    if ( ( $document = self::rowDocument( $plan, $row ) ) !== null )
                        yield $document;
            }
            return;
        }
        $startkey = $plan['startkey'];
        $startDoc = null;
        for ( $p = 1; $p <= $maxPages; $p++ )
        {
            $q = $query + array( 'limit' => (string)( $page + 1 ) );
            if ( $startkey !== '' )
                $q['startkey'] = $startkey;
            if ( $startDoc !== null )
                $q['startkey_docid'] = $startDoc;
            list( $answer ) = self::request( $plan, 'GET', $path, $q, array() );
            $rows = self::answerRows( $answer );
            $next = count( $rows ) > $page ? $rows[$page] : null;
            foreach ( array_slice( $rows, 0, $page ) as $row )
                if ( ( $document = self::rowDocument( $plan, $row ) ) !== null )
                    yield $document;
            if ( !is_array( $next ) || !array_key_exists( 'key', $next ) )
                return;
            $startkey = (string)json_encode( $next['key'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            // a view may emit a key many times: the document ID tells the rows of one key apart (not for a reduce)
            $startDoc = $plan['mode'] === 'view' && !$plan['reduce'] && isset( $next['id'] ) ? (string)$next['id'] : null;
        }
    }

    /** @return array[] the rows of an answer of _all_docs or a view */
    protected static function answerRows( array $answer )
    {
        if ( !isset( $answer['rows'] ) || !is_array( $answer['rows'] ) )
            throw new expImportSourceException( self::LABEL . ': the answer has no rows (' . substr( (string)json_encode( array_keys( $answer ) ), 0, 100 ) . ')' );
        return array_values( array_filter( $answer['rows'], 'is_array' ) );
    }

    /**
     * A row of _all_docs or a view as a document: the document itself (include_docs), else the ID and revision
     * (_all_docs) or the ID, key and value (a view); null for a deleted or missing document and a design document.
     *
     * @return array|null
     */
    public static function rowDocument( array $plan, array $row )
    {
        if ( isset( $row['error'] ) || ( isset( $row['value']['deleted'] ) && $row['value']['deleted'] ) )
            return null;
        $id = isset( $row['id'] ) ? (string)$row['id'] : '';
        if ( $plan['mode'] === 'all_docs' && !$plan['include_design'] && ( strpos( $id, '_design/' ) === 0 || strpos( $id, '_local/' ) === 0 ) )
            return null;
        if ( $plan['include_docs'] )
            return isset( $row['doc'] ) && is_array( $row['doc'] ) ? $row['doc'] : null;
        if ( $plan['mode'] === 'all_docs' )
            return array( '_id' => $id, '_rev' => isset( $row['value']['rev'] ) ? (string)$row['value']['rev'] : '' );
        $document = $id !== '' ? array( '_id' => $id ) : array();
        $document['key'] = isset( $row['key'] ) ? $row['key'] : null;
        $document['value'] = isset( $row['value'] ) ? $row['value'] : null;
        return $document;
    }

    /**
     * The documents of a Mango query, paged with the bookmark of each answer.
     *
     * @return Generator array
     */
    protected static function mangoRows( array $plan, $limit, $maxPages )
    {
        $page = $limit > 0 ? min( $plan['page'], $limit ) : $plan['page'];
        $body = array( 'selector' => $plan['selector'], 'limit' => $page );
        if ( $plan['fields'] )
            $body['fields'] = $plan['fields'];
        if ( $plan['sort'] )
            $body['sort'] = $plan['sort'];
        $bookmark = null;
        for ( $p = 1; $p <= $maxPages; $p++ )
        {
            if ( $bookmark !== null )
                $body['bookmark'] = $bookmark;
            list( $answer ) = self::request( $plan, 'POST', '/' . $plan['db'] . '/_find', array(), array(), $body );
            if ( !isset( $answer['docs'] ) || !is_array( $answer['docs'] ) )
                throw new expImportSourceException( self::LABEL . ': the answer of _find has no docs' );
            foreach ( $answer['docs'] as $document )
                if ( is_array( $document ) )
                    yield $document;
            $next = isset( $answer['bookmark'] ) && is_string( $answer['bookmark'] ) && $answer['bookmark'] !== '' && $answer['bookmark'] !== 'nil' ? $answer['bookmark'] : null;
            if ( count( $answer['docs'] ) < $page || $next === null || $next === $bookmark )
                return;
            $bookmark = $next;
        }
    }

    // ---- test and drivers --------------------------------------------------------------------------------------------

    /**
     * The editor's "Test connection": the version (GET /) and the first document, whose fields are reported, or why
     * it failed (masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $hidden = array();
        $driver = self::LABEL;
        try
        {
            $plan = self::plan( $options, $used, $hidden );
            list( $info ) = self::request( $plan, 'GET', '/', array(), array() );
            if ( isset( $info['version'] ) )
                $driver = ( isset( $info['vendor']['name'] ) && stripos( (string)$info['vendor']['name'], 'cloudant' ) !== false ? 'Cloudant ' : 'CouchDB ' ) . (string)$info['version'];
            $rows = 0;
            $columns = array();
            foreach ( self::documents( array_merge( $options, array( 'page_size' => 1 ) ), 1, $used, $hidden ) as $row )
            {
                $rows++;
                $columns = array_map( 'strval', array_keys( $row ) );
            }
            return array( 'ok' => true, 'columns' => $columns, 'rows' => $rows, 'driver' => $driver,
                          'message' => 'connected to ' . $driver . ( $rows ? '; the first document has these fields' : '; no document found' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportDocumentHttp::mask( $e->getMessage(), $used, $hidden ) );
        }
    }

    /**
     * How this PHP reaches the server: PHP's http and https streams (noted for the job queue's workers).
     *
     * @return array id => array( label, available, via, needs, worker, worker_php )
     */
    public static function drivers()
    {
        return expImportDocumentHttp::drivers();
    }
}
