<?php
/**
 * File containing the expImportCouchbaseSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The results of a SQL++ (N1QL) query of Couchbase Server or Capella, read through the REST API of the Query service
 * (/query/service, port 8093, with TLS 18093). One record is one result: an object flattened to dotted names
 * (author.name) as the MongoDB source does, a plain value (SELECT RAW) as the field value.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() pages through the results and writes them
 * into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible, twice over:
 *   - every request carries readonly=true, so the Query service itself refuses any statement that changes data
 *   - checkStatement() lets only a single SELECT (or WITH ... SELECT) through, without parameters, and refuses the
 *     words of every other statement outside string literals and comments: INSERT, UPSERT, UPDATE, DELETE, MERGE,
 *     CREATE, DROP, ALTER, GRANT, REVOKE, EXECUTE (FUNCTION), INFER, PREPARE, BUILD, ADVISE, EXPLAIN, the transaction
 *     statements, CURL() (a request from the server) and the system: keyspaces
 *   - the only request is POST <server>/query/service
 *   - the password is the name of a secret (expImportSecrets), never stored in the import, and masked in every message
 *   - a server in a private network is refused unless [CouchbaseSourceSettings] AllowedHosts[] lists it
 *
 * Paging: the statement is wrapped as SELECT RAW of a subquery with LIMIT and OFFSET per page (offset), or with
 * WHERE field > $after ORDER BY field LIMIT (keyset, on a unique field of the results), or sent as it is (none).
 */
class expImportCouchbaseSource extends expImportJsonSource
{
    /** The words refused outside string literals and comments: other statements, transactions, requests, code. */
    const FORBIDDEN = array( 'INSERT', 'UPSERT', 'UPDATE', 'DELETE', 'MERGE', 'CREATE', 'DROP', 'ALTER', 'GRANT', 'REVOKE',
                             'EXECUTE', 'INFER', 'PREPARE', 'BUILD', 'ADVISE', 'EXPLAIN', 'TRANSACTION', 'BEGIN', 'COMMIT',
                             'ROLLBACK', 'SAVEPOINT', 'SET', 'UNSET', 'CURL', 'FUNCTION', 'TRUNCATE', 'FLUSH' );

    /** The alias of the subquery the paging wraps the statement in. */
    const ALIAS = 'exp_page';

    const LABEL = 'Couchbase';

    public static function identifier()
    {
        return 'couchbase';
    }

    public static function label()
    {
        return 'Couchbase query (SQL++)';
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
        return 'The results of a SQL++ (N1QL) SELECT of Couchbase Server or Capella, read through the Query service (port '
             . '8093, TLS 18093) with readonly=true: one record is one result, nested objects as dotted fields '
             . '(author.name). Every other statement is refused; the password is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'couchbase-products.ndjson: what a run reads from a query of products (three results, flattened), to try the mapping',
                   'file' => 'couchbase-products.ndjson', 'options' => array( 'base_url' => 'https://cb.example.org:18093', 'statement' => 'SELECT p.* FROM shop p', 'key' => 'sku' ) ),
            array( 'label' => 'The active products of the collection shop.inventory.products, user reader, the password as the secret cb_reader, paged by sku',
                   'options' => array( 'base_url' => 'https://cb.example.org:18093', 'user' => 'reader', 'password_secret' => 'cb_reader',
                                       'statement' => 'SELECT p.sku, p.name, p.price, p.tags FROM shop.inventory.products p WHERE p.active = true',
                                       'paging' => 'keyset', 'keyset_field' => 'sku', 'key' => 'sku' ) ),
            array( 'label' => 'Articles with their authors (WITH ... SELECT) in the scope cms.content, newest first',
                   'options' => array( 'base_url' => 'https://cb.example.org:18093', 'user' => 'reader', 'password_secret' => 'cb_reader',
                                       'query_context' => 'default:cms.content',
                                       'statement' => "WITH editors AS (SELECT RAW e.id FROM people e WHERE e.role = 'editor')\nSELECT META(a).id AS id, a.title, a.author FROM articles a WHERE a.author IN editors ORDER BY a.published DESC, META(a).id",
                                       'key' => 'id' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Query service', 'required' => true,
                                 'help' => 'The address of the Query service: https://cb.example.org:18093 (TLS) or http://cb.example.org:8093 (a private network only with [CouchbaseSourceSettings] AllowedHosts[])' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'A user with the role Query Select on the keyspaces' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                        'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE, never the password' ),
            'statement' => array( 'type' => 'text', 'label' => 'Query (SQL++)', 'required' => true,
                                  'help' => 'One SELECT (or WITH ... SELECT) without parameters, e.g. SELECT p.* FROM shop p WHERE p.active = true ORDER BY META(p).id' ),
            'query_context' => array( 'type' => 'string', 'label' => 'Query context', 'max' => 255, 'pattern' => '/^((default:)?[A-Za-z0-9_%-]+(\.[A-Za-z0-9_%-]+)?)?$/',
                                      'help' => 'The bucket and scope the names of the query are read in (Couchbase 7): default:shop.inventory. Empty: none' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'offset',
                               'values' => array( 'offset' => 'LIMIT and OFFSET (give the query an ORDER BY)', 'keyset' => 'After the last value of a unique field (fast)',
                                                  'none' => 'One request (the query\'s own LIMIT)' ) ),
            'keyset_field' => array( 'type' => 'string', 'label' => 'Keyset field', 'max' => 255, 'pattern' => '/^([A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*)?$/',
                                     'help' => 'With the keyset paging: a unique field of the results (id, sku, meta.id)' ),
            'scan_consistency' => array( 'type' => 'enum', 'label' => 'Consistency', 'default' => 'not_bounded',
                                         'values' => array( 'not_bounded' => 'As the index stands (fast)', 'request_plus' => 'Every change before the run (waits for the index)' ) ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 1, 'max' => 10000, 'help' => 'Results per request' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most results', 'default' => 0, 'min' => 0, 'help' => '0: every result' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of objects stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a result (select META(x).id AS id for the document ID)' ),
        );
    }

    /** The snapshot is always one result per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the statement -----------------------------------------------------------------------------------------------

    /**
     * A statement with its string literals, quoted identifiers and comments blanked out, of the same length (so a
     * position in it is a position in the statement): '...' and "..." become spaces, `...` becomes x's.
     *
     * @param string $sql
     * @return string
     * @throws expImportSourceException an unclosed literal or comment
     */
    public static function blank( $sql )
    {
        $sql = (string)$sql;
        $out = '';
        $n = strlen( $sql );
        for ( $i = 0; $i < $n; )
        {
            $c = $sql[$i];
            $two = substr( $sql, $i, 2 );
            if ( $two === '--' || $two === '/*' )
            {
                $end = $two === '--' ? strpos( $sql, "\n", $i ) : strpos( $sql, '*/', $i + 2 );
                if ( $end === false && $two === '/*' )
                    throw new expImportSourceException( self::LABEL . ': the query has a comment that is not closed' );
                $end = $end === false ? $n : ( $two === '--' ? $end : $end + 2 );
                $out .= str_repeat( ' ', $end - $i );
                $i = $end;
                continue;
            }
            if ( $c === "'" || $c === '"' || $c === '`' )
            {
                $j = $i + 1;
                while ( true )
                {
                    if ( $j >= $n )
                        throw new expImportSourceException( self::LABEL . ': the query has a quote that is not closed' );
                    if ( $sql[$j] === '\\' && $c !== '`' )
                    {
                        $j += 2;
                        continue;
                    }
                    if ( $sql[$j] === $c )
                    {
                        if ( $j + 1 < $n && $sql[$j + 1] === $c )
                        {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $out .= $c === '`' ? str_repeat( 'x', $j + 1 - $i ) : str_repeat( ' ', $j + 1 - $i );
                $i = $j + 1;
                continue;
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }

    /**
     * Whether a statement only reads: one SELECT or WITH ... SELECT, no parameter, no word of another statement and
     * no system keyspace outside literals and comments.
     *
     * @param string $sql
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkStatement( $sql )
    {
        try
        {
            $blank = rtrim( self::blank( trim( (string)$sql ) ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::LABEL ) + 2 );
        }
        if ( substr( $blank, -1 ) === ';' )
            $blank = rtrim( substr( $blank, 0, -1 ) );
        if ( trim( $blank ) === '' )
            return 'the query is empty';
        if ( strpos( $blank, ';' ) !== false )
            return 'only one statement is allowed';
        if ( !preg_match( '/^\s*(SELECT|WITH)\b/i', $blank ) )
            return 'only a SELECT (or WITH ... SELECT) is allowed';
        if ( preg_match_all( '/[A-Za-z_][A-Za-z0-9_]*/', $blank, $m ) )
            foreach ( $m[0] as $word )
                if ( in_array( strtoupper( $word ), self::FORBIDDEN, true ) )
                    return 'the query contains ' . strtoupper( $word ) . ': only reading is allowed';
        if ( preg_match( '/\bsystem\s*:/i', $blank ) )
            return 'the system keyspaces (system:...) are not read';
        if ( preg_match( '/\$[A-Za-z0-9_]|\?/', $blank ) )
            return 'the query may not have parameters ($name or ?)';
        if ( preg_match( '/^\s*WITH\b/i', $blank ) && self::topSelect( $blank ) === null )
            return 'a WITH clause must be followed by a SELECT';
        return null;
    }

    /** @return int|null the position of the SELECT after the WITH clause (outside parentheses), null when there is none */
    protected static function topSelect( $blank )
    {
        $depth = 0;
        $n = strlen( $blank );
        for ( $i = 4; $i < $n; $i++ )
        {
            $c = $blank[$i];
            if ( $c === '(' || $c === '[' || $c === '{' )
                $depth++;
            elseif ( $c === ')' || $c === ']' || $c === '}' )
                $depth--;
            elseif ( $depth === 0 && ( $c === 'S' || $c === 's' ) && preg_match( '/\GSELECT\b/i', $blank, $m, 0, $i )
                     && ( $i === 0 || !preg_match( '/[A-Za-z0-9_]/', $blank[$i - 1] ) ) )
                return $i;
        }
        return null;
    }

    /**
     * The statement of one page.
     *
     * @param string $sql the checked statement
     * @param string $paging offset, keyset or none
     * @param int $size results per page
     * @param int $offset offset paging: the results before the page
     * @param string $field keyset paging: the field (dotted)
     * @param bool $after keyset paging: whether the page comes after a value ($after)
     * @return string
     */
    public static function pageStatement( $sql, $paging, $size, $offset = 0, $field = '', $after = false )
    {
        $sql = rtrim( trim( (string)$sql ) );
        $blank = self::blank( $sql );
        if ( substr( rtrim( $blank ), -1 ) === ';' )
        {
            $sql = rtrim( substr( $sql, 0, strlen( rtrim( $blank ) ) - 1 ) );
            $blank = rtrim( substr( $blank, 0, strlen( $sql ) ) );
        }
        if ( $paging === 'none' )
            return $sql;
        $prefix = '';
        if ( preg_match( '/^\s*WITH\b/i', $blank ) )
        {
            $at = (int)self::topSelect( $blank );
            $prefix = substr( $sql, 0, $at );
            $sql = substr( $sql, $at );
        }
        $alias = self::ALIAS;
        $wrapped = $prefix . "SELECT RAW $alias FROM (" . $sql . "\n) AS $alias";
        if ( $paging === 'keyset' )
        {
            $path = $alias . '.' . implode( '.', array_map( function ( $part ) { return '`' . $part . '`'; }, explode( '.', (string)$field ) ) );
            return $wrapped . ( $after ? " WHERE $path > \$after" : '' ) . " ORDER BY $path LIMIT " . (int)$size;
        }
        return $wrapped . ' LIMIT ' . (int)$size . ' OFFSET ' . (int)$offset;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Everything a read needs, checked.
     *
     * @param array $options
     * @param string[] $used
     * @param string[] $hidden
     * @return array
     * @throws expImportSourceException
     */
    public static function plan( array $options, array &$used, array &$hidden )
    {
        $label = self::LABEL;
        list( $base, $trusted ) = expImportDocumentHttp::base( isset( $options['base_url'] ) ? $options['base_url'] : '', 'CouchbaseSourceSettings', $label );
        $sql = isset( $options['statement'] ) ? trim( (string)$options['statement'] ) : '';
        $refusal = self::checkStatement( $sql );
        if ( $refusal !== null )
            throw new expImportSourceException( "$label: $refusal" );
        $paging = isset( $options['paging'] ) && in_array( $options['paging'], array( 'keyset', 'none' ), true ) ? (string)$options['paging'] : 'offset';
        $field = isset( $options['keyset_field'] ) ? trim( (string)$options['keyset_field'] ) : '';
        if ( $paging === 'keyset' && !preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $field ) )
            throw new expImportSourceException( "$label: the keyset paging needs a field of the results (id, sku, meta.id)" );
        $context = isset( $options['query_context'] ) ? trim( (string)$options['query_context'] ) : '';
        if ( $context !== '' && !preg_match( '/^(default:)?[A-Za-z0-9_%-]+(\.[A-Za-z0-9_%-]+)?$/', $context ) )
            throw new expImportSourceException( "$label: the query context must be default:bucket.scope" );
        $headers = array( 'Content-Type' => 'application/json' );
        $user = isset( $options['user'] ) ? (string)$options['user'] : '';
        $ref = isset( $options['password_secret'] ) ? trim( (string)$options['password_secret'] ) : '';
        if ( $user !== '' || $ref !== '' )
        {
            $used[] = $ref;
            $basic = base64_encode( $user . ':' . expImportSecrets::required( $ref, "$label: the password" ) );
            $hidden[] = $basic;
            $headers['Authorization'] = 'Basic ' . $basic;
        }
        return array( 'url' => $base . '/query/service', 'trusted' => $trusted, 'headers' => $headers, 'statement' => $sql, 'paging' => $paging,
                      'field' => $field, 'context' => $context === '' ? '' : ( strpos( $context, 'default:' ) === 0 ? $context : 'default:' . $context ),
                      'consistency' => isset( $options['scan_consistency'] ) && $options['scan_consistency'] === 'request_plus' ? 'request_plus' : 'not_bounded',
                      'page' => max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 500 ) ),
                      'limit' => isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0 );
    }

    /**
     * Runs one statement on the Query service (readonly=true) and gives its results.
     *
     * @param array $plan
     * @param string $statement
     * @param array $named named parameters (after => value)
     * @return array the results
     * @throws expImportSourceException
     */
    protected static function query( array $plan, $statement, array $named = array() )
    {
        $parts = parse_url( $plan['url'] );
        if ( !isset( $parts['path'] ) || substr( $parts['path'], -14 ) !== '/query/service' )
            throw new expImportSourceException( self::LABEL . ': only the Query service is asked' );
        $timeout = max( 5, (int)expImportPlatformHttp::setting( 'CouchbaseSourceSettings', 'QueryTimeout', 30 ) );
        $body = array( 'statement' => $statement, 'readonly' => true, 'scan_consistency' => $plan['consistency'], 'timeout' => $timeout . 's',
                       'client_context_id' => 'exponential-import' );
        if ( $plan['context'] !== '' )
            $body['query_context'] = $plan['context'];
        foreach ( $named as $name => $value )
            $body['$' . $name] = $value;
        list( $answer ) = expImportDocumentHttp::call( self::LABEL, 'POST', $plan['url'], $plan['headers'],
                                                       (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $plan['trusted'] );
        if ( isset( $answer['errors'] ) && $answer['errors'] )
            throw new expImportSourceException( self::LABEL . ': ' . substr( expImportDocumentHttp::reason( $answer ), 0, 300 ) );
        if ( isset( $answer['status'] ) && $answer['status'] !== 'success' )
            throw new expImportSourceException( self::LABEL . ': the query ended with the status ' . (string)$answer['status'] );
        if ( !isset( $answer['results'] ) || !is_array( $answer['results'] ) )
            return array();
        return $answer['results'];
    }

    /**
     * Reads the results into the snapshot, one flattened JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most results (the editor's sample); 0: all
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
     * The flattened results, page by page.
     *
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
        $size = $limit > 0 ? min( $plan['page'], $limit ) : $plan['page'];
        $maxPages = $plan['paging'] === 'none' ? 1 : max( 1, (int)expImportPlatformHttp::setting( 'CouchbaseSourceSettings', 'MaxPages', 10000 ) );
        $n = 0;
        $after = null;
        for ( $page = 0; $page < $maxPages; $page++ )
        {
            $statement = self::pageStatement( $plan['statement'], $plan['paging'], $size, $page * $size, $plan['field'], $after !== null );
            $results = self::query( $plan, $statement, $after !== null ? array( 'after' => $after ) : array() );
            foreach ( $results as $result )
            {
                yield self::record( $result, $arrays, $separator );
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( $plan['paging'] === 'none' || count( $results ) < $size )
                return;
            if ( $plan['paging'] === 'keyset' )
            {
                $last = end( $results );
                foreach ( explode( '.', $plan['field'] ) as $part )
                    $last = is_array( $last ) && array_key_exists( $part, $last ) ? $last[$part] : null;
                if ( $last === null || is_array( $last ) )
                    throw new expImportSourceException( self::LABEL . ': the results have no value of the keyset field ' . $plan['field'] . ' to page with' );
                $after = $last;
            }
        }
    }

    /**
     * A result as a record: an object flattened, a plain value or a list as the field value.
     *
     * @param mixed $result
     * @param string $arrays
     * @param string $separator
     * @return array
     */
    public static function record( $result, $arrays = 'json', $separator = ', ' )
    {
        if ( is_array( $result ) && ( $result === array() || array_values( $result ) !== $result ) )
            return expImportMongoDbSource::flatten( $result, $arrays, $separator );
        return expImportMongoDbSource::flatten( array( 'value' => $result ), $arrays, $separator );
    }

    // ---- test and drivers --------------------------------------------------------------------------------------------

    /**
     * The editor's "Test connection": the first result of the query, whose fields are reported, or why it failed
     * (masked). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        $hidden = array();
        $driver = 'Couchbase Query service';
        try
        {
            $plan = self::plan( $options, $used, $hidden );
            $results = self::query( $plan, self::pageStatement( $plan['statement'], 'offset', 1 ) );
            $columns = $results ? array_map( 'strval', array_keys( self::record( reset( $results ) ) ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $results ), 'driver' => $driver,
                          'message' => 'connected (read-only)' . ( $results ? '; the first result has these fields' : '; the query gives no result' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportDocumentHttp::mask( $e->getMessage(), $used, $hidden ) );
        }
    }

    /**
     * How this PHP reaches the Query service: PHP's http and https streams (noted for the job queue's workers).
     *
     * @return array id => array( label, available, via, needs, worker, worker_php )
     */
    public static function drivers()
    {
        return expImportDocumentHttp::drivers();
    }
}
