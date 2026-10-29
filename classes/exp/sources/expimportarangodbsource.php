<?php
/**
 * File containing the expImportArangoDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The results of an AQL query against ArangoDB, read through its HTTP cursor API. One record is one result: a
 * document with nested objects flattened to dotted names (author.name) and _key, _id and _rev kept; a value that is
 * not an object becomes the field "value".
 *
 * Only reading is possible: checkAql() refuses, outside string literals, quoted names and comments, the operations
 * that write (INSERT, UPDATE, REPLACE, REMOVE, UPSERT) and the functions that call code or wait (CALL, APPLY, user
 * functions NS::NAME(), SLEEP, V8) and forward-tick names (use backticks). Give the user read-only access to the
 * database ("ro") besides. The password or JWT is the name of a secret (expImportSecrets).
 *
 * Paging: POST /_db/<database>/_api/cursor with batchSize, then POST /_api/cursor/<id> (PUT before ArangoDB 3.7)
 * for each next batch while hasMore; a cursor left open by an early stop is deleted.
 */
class expImportArangoDbSource extends expImportHttpQuerySource
{
    const NAME = 'ArangoDB';

    const SETTINGS = 'ArangoDbSourceSettings';

    const WORKER_PREFIX = 'arangodb';

    /** The words of AQL that write or call code. */
    const FORBIDDEN = array( 'INSERT', 'UPDATE', 'REPLACE', 'REMOVE', 'UPSERT', 'CALL', 'APPLY', 'SLEEP', 'V8' );

    public static function identifier()
    {
        return 'arangodb';
    }

    public static function label()
    {
        return 'ArangoDB query (AQL)';
    }

    public static function description()
    {
        return 'The results of an AQL query against ArangoDB, read batch by batch through the cursor API: a document with its '
             . 'nested objects flattened to dotted names and _key, _id and _rev kept. INSERT, UPDATE, REPLACE, REMOVE, UPSERT '
             . 'and calls of code are refused; the password or token is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three documents of a collection "articles" as the source writes it (one document per line)',
                   'file' => 'arangodb-articles.ndjson',
                   'options' => array( 'base_url' => 'https://arango.example.invalid:8529', 'database' => 'news',
                                       'query' => 'FOR a IN articles RETURN a', 'key' => '_key' ) ),
            array( 'label' => 'The published articles, newest first, user reader with the password as the secret arango_reader',
                   'options' => array( 'base_url' => 'https://arango.example.invalid:8529', 'database' => 'news', 'auth' => 'basic',
                                       'user' => 'reader', 'password_secret' => 'arango_reader',
                                       'query' => "FOR a IN articles\n  FILTER a.status == @status\n  SORT a.published_at DESC\n  RETURN a",
                                       'bind_vars' => '{"status": "published"}', 'key' => '_key' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'required' => true,
                                 'help' => 'The address of the server or coordinator, e.g. https://arango.example.org:8529 (a private network only with [ArangoDbSourceSettings] AllowedHosts[])' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'default' => '_system', 'max' => 128,
                                 'pattern' => '/^[A-Za-z0-9_][A-Za-z0-9_-]*$/', 'help' => 'The database name' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'basic',
                             'values' => array( 'none' => 'None', 'basic' => 'User and password', 'bearer' => 'JWT (bearer)' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'With User and password: the user name' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'With User and password: the name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'token_secret' => array( 'type' => 'secret', 'label' => 'JWT (name of a secret)', 'help' => 'With JWT: the name of the secret with the token' ),
            'query' => array( 'type' => 'text', 'label' => 'AQL query', 'required' => true,
                              'help' => 'A reading query: FOR a IN articles FILTER a.status == @status RETURN a' ),
            'bind_vars' => array( 'type' => 'json', 'label' => 'Bind parameters', 'help' => 'A JSON object of the @parameters of the query' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Batch size', 'default' => 1000, 'min' => 1, 'max' => 100000,
                                  'help' => 'Results per batch of the cursor' ),
            'stream' => array( 'type' => 'bool', 'label' => 'Streaming cursor', 'default' => true,
                               'help' => 'Yes: the server computes the results batch by batch (options.stream), which keeps its memory low' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '_key', 'help' => 'The field that names a result' ),
        );
    }

    /**
     * Whether an AQL query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkAql( $query )
    {
        // forward ticks quote names in AQL too; the lexer does not read them, so they are refused
        if ( strpos( (string)$query, "\u{00B4}" ) !== false )
            return 'names quoted with forward ticks are not accepted (use backticks)';
        try
        {
            // strings and names in backticks with backslash escapes (the AQL lexer), comments // and /* */
            list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, false ), '"' => array( 'string', true, false ),
                                                                               '`' => array( 'name', true, false ) ),
                                                             'line' => array( '//' ), 'block' => true ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( trim( $stripped ) === '' )
            return 'the query is empty';
        if ( strpos( $stripped, ';' ) !== false )
            return 'only one query is allowed';
        $word = self::forbiddenWord( $stripped, self::FORBIDDEN );
        if ( $word !== null )
            return "the query contains $word: only reading is allowed";
        if ( preg_match( '/[A-Za-z_][A-Za-z0-9_]*\s*::/', $stripped ) )
            return 'user functions (NAMESPACE::NAME) are not allowed';
        return null;
    }

    /** @return array the list ( string query, array bind variables ) of the options, after the check */
    public static function queryOf( array $options )
    {
        $query = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $refusal = self::checkAql( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( self::NAME . ': ' . $refusal );
        $vars = isset( $options['bind_vars'] ) ? $options['bind_vars'] : array();
        if ( is_string( $vars ) )
            $vars = trim( $vars ) === '' ? array() : json_decode( $vars, true );
        if ( !is_array( $vars ) || ( $vars && array_values( $vars ) === $vars ) )
            throw new expImportSourceException( self::NAME . ': the bind parameters must be a JSON object' );
        return array( $query, $vars );
    }

    /**
     * The address of the database's API and the headers.
     *
     * @return array the list ( string base: .../_db/<database>, array headers, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        list( $base, $trusted ) = self::baseUrl( $options );
        $database = isset( $options['database'] ) && trim( (string)$options['database'] ) !== '' ? trim( (string)$options['database'] ) : '_system';
        if ( !preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_-]*$/', $database ) )
            throw new expImportSourceException( self::NAME . ': the database must be a name' );
        $headers = expImportPlatformHttp::authHeaders( $options + array( 'auth' => 'basic' ), $used );
        return array( $base . '/_db/' . rawurlencode( $database ), $headers, $trusted );
    }

    public static function rows( array $options, $limit, array &$used )
    {
        list( $base, $headers, $trusted ) = self::connection( $options, $used );
        list( $query, $vars ) = self::queryOf( $options );
        $size = max( 1, min( 100000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 1000 ) );
        if ( $limit > 0 )
            $size = min( $size, $limit );
        $body = array( 'query' => $query, 'batchSize' => $size, 'count' => false );
        if ( $vars )
            $body['bindVars'] = $vars;
        if ( !isset( $options['stream'] ) || $options['stream'] )
            $body['options'] = array( 'stream' => true );
        $answer = self::json( 'POST', $base . '/_api/cursor', $headers, $body, $trusted );
        $id = isset( $answer['id'] ) ? (string)$answer['id'] : '';
        $more = !empty( $answer['hasMore'] );
        $n = 0;
        try
        {
            for ( $page = 1; ; $page++ )
            {
                if ( !isset( $answer['result'] ) || !is_array( $answer['result'] ) )
                    throw new expImportSourceException( self::NAME . ': the answer has no result' );
                foreach ( $answer['result'] as $item )
                {
                    yield self::document( $item );
                    if ( $limit > 0 && ++$n >= $limit )
                        return;
                }
                if ( !$more || $id === '' || $page >= self::maxPages() )
                    return;
                $answer = self::next( $base, $id, $headers, $trusted );
                $more = !empty( $answer['hasMore'] );
            }
        }
        finally
        {
            if ( $more && $id !== '' )
            {
                try
                {
                    self::send( 'DELETE', $base . '/_api/cursor/' . rawurlencode( $id ), $headers, null, $trusted );
                }
                catch ( Exception $e )
                {
                    // the server drops the cursor after its time to live anyway
                }
            }
        }
    }

    /**
     * The next batch of a cursor: POST (ArangoDB 3.7 and later), else PUT.
     *
     * @return array
     * @throws expImportSourceException
     */
    protected static function next( $base, $id, array $headers, array $trusted )
    {
        $url = $base . '/_api/cursor/' . rawurlencode( $id );
        try
        {
            return self::json( 'POST', $url, $headers, null, $trusted );
        }
        catch ( expImportSourceException $e )
        {
            if ( !preg_match( '/answered (404|405)\b/', $e->getMessage() ) )
                throw $e;
        }
        return self::json( 'PUT', $url, $headers, null, $trusted );
    }

    /**
     * A result as a record: a document flattened, _key, _id and _rev first; another value as "value".
     *
     * @param mixed $item
     * @return array
     */
    public static function document( $item )
    {
        if ( !is_array( $item ) || ( $item !== array() && array_values( $item ) === $item ) )
            return array( 'value' => $item );
        $record = array();
        foreach ( array( '_key', '_id', '_rev', '_from', '_to' ) as $name )
            if ( array_key_exists( $name, $item ) )
                $record[$name] = $item[$name];
        foreach ( self::flatten( $item ) as $name => $value )
            if ( !array_key_exists( $name, $record ) )
                $record[$name] = $value;
        return $record;
    }

    /** @return string the server's reason of an error answer (errorNum and errorMessage) */
    protected static function reason( $body )
    {
        $data = json_decode( (string)$body, true );
        if ( is_array( $data ) && isset( $data['errorMessage'] ) )
            return ( isset( $data['errorNum'] ) ? 'error ' . $data['errorNum'] . ': ' : '' ) . (string)$data['errorMessage'];
        return parent::reason( $body );
    }

    /**
     * The editor's "Test connection": the version (GET /_api/version) and the first result of the query, or why it
     * failed (masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'ArangoDB';
        try
        {
            list( $base, $headers, $trusted ) = self::connection( $options, $used );
            list( $query, $vars ) = self::queryOf( $options );
            $version = self::json( 'GET', $base . '/_api/version', $headers, null, $trusted );
            if ( isset( $version['version'] ) )
                $driver .= ' ' . $version['version'];
            $body = array( 'query' => $query, 'batchSize' => 1, 'count' => false, 'options' => array( 'stream' => true ) );
            if ( $vars )
                $body['bindVars'] = $vars;
            $answer = self::json( 'POST', $base . '/_api/cursor', $headers, $body, $trusted );
            if ( !empty( $answer['hasMore'] ) && isset( $answer['id'] ) )
            {
                try
                {
                    self::send( 'DELETE', $base . '/_api/cursor/' . rawurlencode( (string)$answer['id'] ), $headers, null, $trusted );
                }
                catch ( Exception $e )
                {
                }
            }
            $results = isset( $answer['result'] ) && is_array( $answer['result'] ) ? array_slice( $answer['result'], 0, 1 ) : array();
            $columns = $results ? array_map( 'strval', array_keys( self::document( $results[0] ) ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $results ), 'driver' => $driver,
                          'message' => 'connected to ' . $driver . ( $results ? '; the query gives these fields' : '; the query finds no result' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }
}
