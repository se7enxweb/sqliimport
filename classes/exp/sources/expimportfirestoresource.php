<?php
/**
 * File containing the expImportFirestoreSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The documents of a Google Cloud Firestore collection (or collection group), read through the Firestore REST API v1
 * with a service account (expImportGoogleAuth). One record is one document: _id (the document ID), _path (its path
 * below the database), _create_time and _update_time, and its fields with maps flattened to dotted names.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through a structured query
 * (documents:runQuery) or the list of a collection and writes the documents into the run's snapshot, one JSON object
 * per line.
 *
 * Only reading is possible:
 *   - the only requests are documents:runQuery (POST, a structured query: from, where, orderBy, select, limit, offset,
 *     startAt) and the list of a collection (GET); no other method is ever built (no commit, write, batchWrite or
 *     delete, no transaction)
 *   - the import's where is checked before it is sent: only fieldFilter, compositeFilter and unaryFilter with their
 *     known operators and typed values
 *   - the token asks for the read-only scope of Google Cloud (cloud-platform.read-only) by default; the account should
 *     have the IAM role roles/datastore.viewer only (the scope "datastore", for a project whose API refuses the
 *     read-only scope, is not read-only by itself, the role is)
 *   - the service account key is the name of a secret, never stored in the import, and masked with the token in every
 *     message
 *
 * Paging: a query pages with a cursor (startAt the values of the last document of a page, ordered by the import's
 * order and the document name last) or with offset; a list pages with pageToken.
 */
class expImportFirestoreSource extends expImportJsonSource
{
    const API = 'https://firestore.googleapis.com/v1';

    /** The scopes of the option scope. */
    const SCOPES = array( 'read_only' => 'https://www.googleapis.com/auth/cloud-platform.read-only',
                          'datastore' => 'https://www.googleapis.com/auth/datastore' );

    /** The operators of a field filter. */
    const FIELD_OPS = array( 'LESS_THAN', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN', 'GREATER_THAN_OR_EQUAL', 'EQUAL', 'NOT_EQUAL',
                             'ARRAY_CONTAINS', 'IN', 'ARRAY_CONTAINS_ANY', 'NOT_IN' );

    /** The operators of a unary filter. */
    const UNARY_OPS = array( 'IS_NAN', 'IS_NULL', 'IS_NOT_NAN', 'IS_NOT_NULL' );

    /** The kinds of a typed value. */
    const VALUE_KINDS = array( 'nullValue', 'booleanValue', 'integerValue', 'doubleValue', 'timestampValue', 'stringValue', 'bytesValue',
                               'referenceValue', 'geoPointValue', 'arrayValue', 'mapValue' );

    /** @var array|null what drivers() sees of this PHP (tests): allow_url_fopen, openssl */
    public static $environment = null;

    public static function identifier()
    {
        return 'firestore';
    }

    public static function label()
    {
        return 'Google Cloud Firestore collection';
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
        return 'The documents of a Firestore collection or collection group, read through the Firestore REST API with a '
             . 'service account: one record is one document, _id and _path with its fields, maps flattened to dotted '
             . 'names. A structured query (where, orderBy, select) or the list of the collection; nothing is written. '
             . 'The service account key is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three articles of a collection "articles" as the source writes it (one document per line)',
                   'file' => 'firestore-articles.ndjson',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader', 'collection' => 'articles' ) ),
            array( 'label' => 'The published articles, newest first, with the fields title, body and author',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader', 'collection' => 'articles',
                                       'where' => '{"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}',
                                       'order_by' => 'published_at:desc', 'fields' => "title\nbody\nauthor\npublished_at" ) ),
            array( 'label' => 'Every order of every customer (the collection group "orders" below customers/*)',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader', 'collection' => 'orders',
                                       'all_descendants' => true, 'page_size' => 500 ) ),
        );
    }

    public static function schema()
    {
        return array(
            'project' => array( 'type' => 'string', 'label' => 'Project ID', 'required' => true, 'max' => 100,
                                'pattern' => '/^[a-z0-9][a-z0-9.:-]{2,99}$/', 'help' => 'The Google Cloud project of the database (example-project)' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'default' => '(default)', 'max' => 63,
                                 'pattern' => '/^(\(default\)|[a-z][a-z0-9-]{2,62})$/', 'help' => 'The database ID; (default) for the default database' ),
            'credentials_secret' => array( 'type' => 'secret', 'label' => 'Service account key (name of a secret)', 'required' => true,
                                           'help' => 'The name of a secret whose value is the JSON key file of a service account with the role roles/datastore.viewer' ),
            'scope' => array( 'type' => 'enum', 'label' => 'Scope of the token', 'default' => 'read_only',
                              'values' => array( 'read_only' => 'cloud-platform.read-only', 'datastore' => 'datastore (when the read-only scope is refused; the role keeps it read-only)' ) ),
            'mode' => array( 'type' => 'enum', 'label' => 'Reading', 'default' => 'query',
                             'values' => array( 'query' => 'A structured query (runQuery)', 'list' => 'The list of the collection (no filter)' ) ),
            'collection' => array( 'type' => 'string', 'label' => 'Collection', 'required' => true, 'max' => 1000,
                                   'pattern' => '/^[A-Za-z0-9_.~%@ -]+(\/[A-Za-z0-9_.~%@ -]+)*$/',
                                   'help' => 'A collection (articles) or a subcollection by its path (customers/c-1001/orders)' ),
            'all_descendants' => array( 'type' => 'bool', 'label' => 'Collection group', 'default' => false,
                                        'help' => 'Query only: every collection of this name below the path (allDescendants)' ),
            'where' => array( 'type' => 'json', 'label' => 'Filter',
                              'help' => 'Query only: the where of a structured query, e.g. {"fieldFilter": {"field": {"fieldPath": "status"}, "op": "EQUAL", "value": {"stringValue": "published"}}}' ),
            'order_by' => array( 'type' => 'string', 'label' => 'Order', 'max' => 500, 'pattern' => '/^[A-Za-z0-9_.`@:, -]*$/',
                                 'help' => 'Fields with :asc or :desc, comma separated: published_at:desc. The document name is always the last order' ),
            'fields' => array( 'type' => 'list', 'label' => 'Fields', 'help' => 'The fields to read, one per line (select). Empty: all' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'cursor',
                               'values' => array( 'cursor' => 'Cursor (startAt the last document)', 'offset' => 'Offset (slower for large collections)' ) ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 300, 'min' => 1, 'max' => 1000, 'help' => 'Documents per request' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most documents', 'default' => 0, 'min' => 0, 'help' => '0: all' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'JSON (["a","b"])', 'join' => 'A joined list (a, b); an array of maps stays JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'Separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_id',
                            'help' => '_id is the document ID; _path its full path (unique also in a collection group)' ),
        );
    }

    /** The snapshot is always one document per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether a where only filters: fieldFilter, compositeFilter (AND, OR) and unaryFilter with their operators and
     * typed values, at any depth.
     *
     * @param mixed $where
     * @return string|null the reason it is refused, null when it may be sent
     */
    public static function checkWhere( $where, $depth = 0 )
    {
        if ( $where === null || $where === array() )
            return null;
        if ( $depth > 20 )
            return 'the filter is nested too deeply';
        if ( !is_array( $where ) || count( $where ) !== 1 )
            return 'a filter is an object with one of fieldFilter, compositeFilter or unaryFilter';
        $kind = (string)key( $where );
        $filter = reset( $where );
        if ( !is_array( $filter ) )
            return "$kind must be an object";
        switch ( $kind )
        {
            case 'fieldFilter':
                if ( array_diff( array_keys( $filter ), array( 'field', 'op', 'value' ) ) || !isset( $filter['field']['fieldPath'], $filter['op'] ) || !array_key_exists( 'value', $filter ) )
                    return 'a fieldFilter has field (with fieldPath), op and value only';
                if ( !in_array( $filter['op'], self::FIELD_OPS, true ) )
                    return 'the operator ' . substr( (string)json_encode( $filter['op'] ), 0, 40 ) . ' is not one of ' . implode( ', ', self::FIELD_OPS );
                return self::checkValue( $filter['value'], 0 );
            case 'unaryFilter':
                if ( array_diff( array_keys( $filter ), array( 'field', 'op' ) ) || !isset( $filter['field']['fieldPath'], $filter['op'] ) )
                    return 'a unaryFilter has field (with fieldPath) and op only';
                if ( !in_array( $filter['op'], self::UNARY_OPS, true ) )
                    return 'the operator of a unaryFilter is one of ' . implode( ', ', self::UNARY_OPS );
                return null;
            case 'compositeFilter':
                if ( array_diff( array_keys( $filter ), array( 'op', 'filters' ) ) || !isset( $filter['op'], $filter['filters'] ) || !is_array( $filter['filters'] ) )
                    return 'a compositeFilter has op and filters only';
                if ( !in_array( $filter['op'], array( 'AND', 'OR' ), true ) )
                    return 'the operator of a compositeFilter is AND or OR';
                foreach ( $filter['filters'] as $inner )
                {
                    $refusal = self::checkWhere( $inner, $depth + 1 );
                    if ( $refusal !== null )
                        return $refusal;
                }
                return null;
        }
        return "\"$kind\" is not a filter (fieldFilter, compositeFilter or unaryFilter)";
    }

    /** @return string|null why a typed value of a filter is refused */
    protected static function checkValue( $value, $depth )
    {
        if ( $depth > 20 )
            return 'a value is nested too deeply';
        if ( !is_array( $value ) || count( $value ) !== 1 || !in_array( (string)key( $value ), self::VALUE_KINDS, true ) )
            return 'a value is an object with one of ' . implode( ', ', self::VALUE_KINDS ) . ' ({"stringValue": "published"})';
        $kind = (string)key( $value );
        $inner = reset( $value );
        if ( $kind === 'arrayValue' && isset( $inner['values'] ) )
            foreach ( (array)$inner['values'] as $item )
                if ( ( $refusal = self::checkValue( $item, $depth + 1 ) ) !== null )
                    return $refusal;
        if ( $kind === 'mapValue' && isset( $inner['fields'] ) )
            foreach ( (array)$inner['fields'] as $item )
                if ( ( $refusal = self::checkValue( $item, $depth + 1 ) ) !== null )
                    return $refusal;
        return null;
    }

    /**
     * The order of the option: "published_at:desc,title" as orderBy entries.
     *
     * @param string $text
     * @return array
     * @throws expImportSourceException
     */
    public static function orderOf( $text )
    {
        $order = array();
        foreach ( explode( ',', (string)$text ) as $part )
        {
            $part = trim( $part );
            if ( $part === '' )
                continue;
            if ( !preg_match( '/^([A-Za-z0-9_.`@-]+)(?:\s*:\s*(asc|desc))?$/i', $part, $m ) )
                throw new expImportSourceException( "Firestore: the order \"$part\" is not of the form field or field:asc or field:desc" );
            $order[] = array( 'field' => array( 'fieldPath' => $m[1] ),
                              'direction' => isset( $m[2] ) && strtolower( $m[2] ) === 'desc' ? 'DESCENDING' : 'ASCENDING' );
        }
        return $order;
    }

    /**
     * The parent (the documents path) and the collection ID of a collection path.
     *
     * @param string $path articles or customers/c-1001/orders
     * @return array ( string parent below documents ('' for the root), string collection ID )
     * @throws expImportSourceException
     */
    public static function collectionOf( $path )
    {
        $path = trim( (string)$path, " /" );
        $segments = $path === '' ? array() : explode( '/', $path );
        if ( !$segments || count( $segments ) % 2 !== 1 )
            throw new expImportSourceException( 'Firestore: the collection is a collection name or a path of collection/document/collection' );
        foreach ( $segments as $segment )
            if ( !preg_match( '/^[A-Za-z0-9_.~%@ -]+$/', $segment ) || $segment === '.' || $segment === '..' || preg_match( '/^__.*__$/', $segment ) )
                throw new expImportSourceException( 'Firestore: a part of the collection path is not a collection or document ID' );
        $id = (string)array_pop( $segments );
        return array( implode( '/', $segments ), $id );
    }

    /**
     * The structured query of the options (without limit, offset and startAt).
     *
     * @param array $options
     * @return array
     * @throws expImportSourceException
     */
    public static function structuredQuery( array $options )
    {
        list( , $id ) = self::collectionOf( isset( $options['collection'] ) ? (string)$options['collection'] : '' );
        $query = array( 'from' => array( array( 'collectionId' => $id, 'allDescendants' => !empty( $options['all_descendants'] ) ) ) );
        $where = expImportCloudHttp::jsonOption( isset( $options['where'] ) ? $options['where'] : null, 'Firestore: the filter' );
        $refusal = self::checkWhere( $where );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Firestore: ' . $refusal );
        if ( $where )
            $query['where'] = $where;
        $order = self::orderOf( isset( $options['order_by'] ) ? (string)$options['order_by'] : '' );
        $hasName = false;
        foreach ( $order as $entry )
            if ( $entry['field']['fieldPath'] === '__name__' )
                $hasName = true;
        if ( !$hasName )
        {
            $last = end( $order );
            $order[] = array( 'field' => array( 'fieldPath' => '__name__' ), 'direction' => $last ? $last['direction'] : 'ASCENDING' );
        }
        $query['orderBy'] = $order;
        $fields = isset( $options['fields'] ) ? array_values( array_filter( array_map( 'trim', (array)$options['fields'] ), 'strlen' ) ) : array();
        if ( $fields )
            $query['select'] = array( 'fields' => array_map( function ( $field ) { return array( 'fieldPath' => $field ); }, $fields ) );
        return $query;
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The documents address of the database and the Authorization header.
     *
     * @param array $options
     * @param string[] $used the secrets used
     * @return array ( string documents address, array headers )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $project = isset( $options['project'] ) ? trim( (string)$options['project'] ) : '';
        if ( !preg_match( '/^[a-z0-9][a-z0-9.:-]{2,99}$/', $project ) )
            throw new expImportSourceException( 'Firestore: the project ID is required' );
        $database = isset( $options['database'] ) && trim( (string)$options['database'] ) !== '' ? trim( (string)$options['database'] ) : '(default)';
        if ( !preg_match( '/^(\(default\)|[a-z][a-z0-9-]{2,62})$/', $database ) )
            throw new expImportSourceException( 'Firestore: the database is (default) or a database ID' );
        $ref = isset( $options['credentials_secret'] ) ? trim( (string)$options['credentials_secret'] ) : '';
        $used[] = $ref;
        $scope = self::SCOPES[isset( $options['scope'] ) && isset( self::SCOPES[$options['scope']] ) ? $options['scope'] : 'read_only'];
        $token = expImportGoogleAuth::accessToken( $ref, $scope, 'Firestore' );
        return array( self::API . '/projects/' . $project . '/databases/' . $database . '/documents', array( 'Authorization' => 'Bearer ' . $token ) );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the documents and writes them into the snapshot, one JSON object per line.
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
                throw new expImportSourceException( 'Firestore: the snapshot cannot be written' );
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
     * The documents as records, page by page.
     *
     * @param array $options
     * @param int $limit 0: all (else also the option limit)
     * @param string[] $used
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function documents( array $options, $limit, array &$used )
    {
        $mode = isset( $options['mode'] ) && $options['mode'] === 'list' ? 'list' : 'query';
        list( $parent, $id ) = self::collectionOf( isset( $options['collection'] ) ? (string)$options['collection'] : '' );
        $query = $mode === 'query' ? self::structuredQuery( $options ) : array();
        if ( $mode === 'list' && ( !empty( $options['where'] ) || !empty( $options['all_descendants'] ) ) )
            throw new expImportSourceException( 'Firestore: a filter and a collection group need the reading "structured query"' );
        list( $base, $headers ) = self::connection( $options, $used );
        $max = isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0;
        if ( $limit > 0 && ( $max === 0 || $limit < $max ) )
            $max = (int)$limit;
        $size = max( 1, min( 1000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 300 ) );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'GoogleCloudSourceSettings', 'MaxPages', 1000 ) );
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $offsetPaging = isset( $options['paging'] ) && $options['paging'] === 'offset';
        $n = 0;
        $token = null;
        $last = null;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $want = $max > 0 ? min( $size, $max - $n ) : $size;
            $docs = array();
            if ( $mode === 'list' )
            {
                $url = $base . '/' . ( $parent !== '' ? self::encodePath( $parent ) . '/' : '' ) . rawurlencode( $id ) . '?pageSize=' . $want;
                if ( $token !== null )
                    $url .= '&pageToken=' . rawurlencode( $token );
                foreach ( self::orderOf( isset( $options['order_by'] ) ? (string)$options['order_by'] : '' ) as $i => $entry )
                    $url .= ( $i === 0 ? '&orderBy=' : '%2C' ) . rawurlencode( $entry['field']['fieldPath'] . ( $entry['direction'] === 'DESCENDING' ? ' desc' : '' ) );
                foreach ( isset( $options['fields'] ) ? array_filter( array_map( 'trim', (array)$options['fields'] ), 'strlen' ) : array() as $field )
                    $url .= '&mask.fieldPaths=' . rawurlencode( $field );
                $answer = expImportCloudHttp::json( 'Firestore', 'GET', $url, $headers );
                $docs = isset( $answer['documents'] ) && is_array( $answer['documents'] ) ? $answer['documents'] : array();
                $token = isset( $answer['nextPageToken'] ) && is_string( $answer['nextPageToken'] ) && $answer['nextPageToken'] !== '' ? $answer['nextPageToken'] : null;
            }
            else
            {
                $request = $query;
                $request['limit'] = $want;
                if ( $offsetPaging && $n > 0 )
                    $request['offset'] = $n;
                elseif ( $last !== null )
                    $request['startAt'] = array( 'values' => self::cursorValues( $last, $query['orderBy'] ), 'before' => false );
                $url = $base . ( $parent !== '' ? '/' . self::encodePath( $parent ) : '' ) . ':runQuery';
                $answer = expImportCloudHttp::json( 'Firestore', 'POST', $url, $headers, array( 'structuredQuery' => $request ) );
                foreach ( $answer as $entry )
                    if ( is_array( $entry ) && isset( $entry['document'] ) && is_array( $entry['document'] ) )
                        $docs[] = $entry['document'];
            }
            foreach ( $docs as $doc )
            {
                yield self::document( (array)$doc, $arrays, $separator );
                $last = $doc;
                $n++;
                if ( $max > 0 && $n >= $max )
                    return;
            }
            if ( $mode === 'list' ? $token === null : count( $docs ) < $want )
                return;
        }
        throw new expImportSourceException( 'Firestore: the collection has more pages than [GoogleCloudSourceSettings] MaxPages (' . $maxPages . ')' );
    }

    /** @return string a documents path with each segment encoded */
    protected static function encodePath( $path )
    {
        return implode( '/', array_map( 'rawurlencode', explode( '/', (string)$path ) ) );
    }

    /**
     * The cursor after a document: its values of the order fields, the document name for __name__.
     *
     * @param array $doc
     * @param array $order orderBy entries
     * @return array typed values
     */
    public static function cursorValues( array $doc, array $order )
    {
        $values = array();
        foreach ( $order as $entry )
        {
            $path = (string)$entry['field']['fieldPath'];
            if ( $path === '__name__' )
            {
                $values[] = array( 'referenceValue' => isset( $doc['name'] ) ? (string)$doc['name'] : '' );
                continue;
            }
            $fields = isset( $doc['fields'] ) && is_array( $doc['fields'] ) ? $doc['fields'] : array();
            $value = array( 'nullValue' => null );
            foreach ( explode( '.', str_replace( '`', '', $path ) ) as $i => $part )
            {
                if ( !isset( $fields[$part] ) )
                {
                    $value = array( 'nullValue' => null );
                    break;
                }
                $value = $fields[$part];
                $fields = isset( $value['mapValue']['fields'] ) && is_array( $value['mapValue']['fields'] ) ? $value['mapValue']['fields'] : array();
            }
            $values[] = $value;
        }
        return $values;
    }

    /**
     * A document as a record: _id, _path, _create_time, _update_time and the fields.
     *
     * @param array $doc a Document of the API
     * @param string $arrays json or join
     * @param string $separator
     * @return array
     */
    public static function document( array $doc, $arrays = 'json', $separator = ', ' )
    {
        $name = isset( $doc['name'] ) ? (string)$doc['name'] : '';
        $path = self::relativePath( $name );
        $record = array( '_id' => (string)substr( $path, (int)strrpos( '/' . $path, '/' ) ), '_path' => $path,
                         '_create_time' => isset( $doc['createTime'] ) ? (string)$doc['createTime'] : null,
                         '_update_time' => isset( $doc['updateTime'] ) ? (string)$doc['updateTime'] : null );
        $plain = array();
        foreach ( isset( $doc['fields'] ) && is_array( $doc['fields'] ) ? $doc['fields'] : array() as $field => $value )
            $plain[(string)$field] = self::value( $value );
        foreach ( expImportMongoDbSource::flatten( $plain, $arrays, $separator ) as $field => $value )
            if ( !array_key_exists( $field, $record ) )
                $record[$field] = $value;
        return $record;
    }

    /** @return string the path of a document name below its database's documents (articles/a-1) */
    public static function relativePath( $name )
    {
        $at = strpos( (string)$name, '/documents/' );
        return $at === false ? (string)$name : (string)substr( (string)$name, $at + 11 );
    }

    /**
     * The PHP value of a typed value: stringValue, integerValue (an int, or its text beyond PHP's range),
     * doubleValue, booleanValue, timestampValue (ISO 8601 as Firestore gives it), mapValue (an array, flattened),
     * arrayValue (a list, JSON or joined), referenceValue (the document path), geoPointValue ("lat,lng"), bytesValue
     * (base64), nullValue.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value( $value )
    {
        if ( !is_array( $value ) || !$value )
            return null;
        $kind = (string)key( $value );
        $data = reset( $value );
        switch ( $kind )
        {
            case 'stringValue':
            case 'timestampValue':
            case 'bytesValue':
                return (string)$data;
            case 'integerValue':
                $text = (string)$data;
                return (string)(int)$text === $text ? (int)$text : $text;
            case 'doubleValue':
                return is_numeric( $data ) ? (float)$data : (string)$data;
            case 'booleanValue':
                return (bool)$data;
            case 'nullValue':
                return null;
            case 'referenceValue':
                return self::relativePath( (string)$data );
            case 'geoPointValue':
                return ( isset( $data['latitude'] ) ? (float)$data['latitude'] : 0.0 ) . ',' . ( isset( $data['longitude'] ) ? (float)$data['longitude'] : 0.0 );
            case 'mapValue':
                $out = array();
                foreach ( isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array() as $name => $item )
                    $out[(string)$name] = self::value( $item );
                return $out ? $out : '{}';
            case 'arrayValue':
                $list = array();
                foreach ( isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array() as $item )
                    $list[] = self::value( $item );
                return $list;
        }
        return null;
    }

    /**
     * The editor's "Test connection": the token of the service account and one document of the query or list, whose
     * fields are reported, or why it failed (the key and the token masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'Firestore REST API v1';
        try
        {
            $records = array();
            foreach ( self::documents( array( 'limit' => 1, 'page_size' => 1 ) + $options, 1, $used ) as $record )
                $records[] = $record;
            $columns = $records ? array_map( 'strval', array_keys( $records[0] ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $records ), 'driver' => $driver,
                          'message' => 'connected with the service account' . ( $records ? '; the first document gives these fields' : '; no document matches' ) );
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
        $list = expImportCloudHttp::drivers( self::$environment, 'Firestore REST API' );
        $list['https']['needs'] .= '; openssl also signs the service account\'s token request';
        return $list;
    }
}
