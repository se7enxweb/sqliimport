<?php
/**
 * File containing the expImportSupabaseSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a table or view of a Supabase project through its REST API (PostgREST), or of any other PostgREST
 * server: one record is one row, its fields the columns of the select, embedded resources flattened to dotted names
 * (author.name) as the MongoDB source does.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() requests <base>/<table> page by page,
 * with a Range header (Range-Unit: items; the total from Content-Range when a count is asked for) or with limit and
 * offset, and writes the rows into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible:
 *   - the only requests are GET <base>/<table>; the table is a name, never a path, and never rpc (functions)
 *   - select, filters and order are checked before they are sent: a select holds only column names, aliases, casts,
 *     JSON paths and embedded resources; a filter only the operators eq, neq, gt, gte, lt, lte, like, ilike, is, in,
 *     cs and cd, also inside and/or/not at any depth (checkFilter())
 *   - the key (the anon key, or a service key) is the name of a secret (expImportSecrets), sent as apikey and as the
 *     bearer token, never stored in the import and masked in every message. The anon key with a read-only role and
 *     row level security is the right choice; a service key passes row level security and should not be used
 *   - a server in a private network (a self-hosted Supabase or PostgREST) is refused unless
 *     [SupabaseSourceSettings] AllowedHosts[] lists it
 *
 * Values: as PostgREST gives them (JSON): text, numbers, booleans, timestamps in ISO 8601 as PostgreSQL writes them,
 * json and jsonb columns and embedded resources as dotted fields, arrays and to-many embeddings as JSON or a joined
 * list, bytea as \x hex text.
 */
class expImportSupabaseSource extends expImportJsonSource
{
    const LABEL = 'Supabase';

    /** The operators a filter may use (PostgREST's comparison operators that only compare). */
    const OPERATORS = array( 'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'ilike', 'is', 'in', 'cs', 'cd' );

    /** The parameters of PostgREST a filter line may not set (select, order and paging are options of their own). */
    const RESERVED = array( 'select', 'order', 'limit', 'offset', 'on_conflict', 'columns', 'and', 'or' );

    /** The least time between two requests, in seconds. */
    const INTERVAL = 0.05;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 5.0;

    public static function identifier()
    {
        return 'supabase';
    }

    public static function label()
    {
        return 'Supabase or PostgREST table';
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
        return 'The rows of a table or view of a Supabase project (or of any PostgREST server) through its REST API: one '
             . 'record is one row, embedded resources as dotted fields (author.name). Select, filters (eq, gt, in, like ... '
             . 'also in and/or) and order are checked before they are sent; only GET requests. The key is the name of a '
             . 'secret; the anon key with a read-only role and row level security is the right one.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three rows of a table "articles" with the embedded author (what a run writes)',
                   'file' => 'supabase-articles.ndjson',
                   'options' => array( 'base_url' => 'https://abcdefghijklmnop.supabase.co', 'table' => 'articles', 'api_key' => 'supabase_anon_key' ) ),
            array( 'label' => 'The published articles with their author, newest first, the anon key as the secret supabase_anon_key',
                   'options' => array( 'base_url' => 'https://abcdefghijklmnop.supabase.co', 'table' => 'articles',
                                       'select' => 'id,title,slug,published_at,tags,author:authors(name,email)',
                                       'filters' => "status=eq.published\npublished_at=gte.2026-01-01",
                                       'order' => 'published_at.desc,id', 'api_key' => 'supabase_anon_key' ) ),
            array( 'label' => 'A self-hosted PostgREST server (listed in [SupabaseSourceSettings] AllowedHosts[]), the view products_public in the schema shop, paged with limit and offset',
                   'options' => array( 'base_url' => 'https://postgrest.example.invalid', 'table' => 'products_public', 'schema' => 'shop',
                                       'filters' => 'or=(stock.gt.0,preorder.is.true)', 'order' => 'sku', 'paging' => 'offset', 'key' => 'sku' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Project or server address', 'required' => true,
                                 'help' => 'The address of the Supabase project (https://<ref>.supabase.co; /rest/v1 is added) or of the PostgREST server '
                                         . '(a private network only with [SupabaseSourceSettings] AllowedHosts[])' ),
            'table' => array( 'type' => 'string', 'label' => 'Table or view', 'required' => true, 'max' => 63, 'pattern' => '/^[A-Za-z_][A-Za-z0-9_$-]*$/',
                              'help' => 'The name of the table or view, no path' ),
            'schema' => array( 'type' => 'string', 'label' => 'Schema', 'max' => 63, 'pattern' => '/^([A-Za-z_][A-Za-z0-9_$]*)?$/',
                               'help' => 'Optional: a schema the API exposes besides public (header Accept-Profile)' ),
            'select' => array( 'type' => 'text', 'label' => 'Select', 'default' => '*',
                               'help' => 'Columns and embedded resources, e.g. id,title,author:authors(name,email); empty: *' ),
            'filters' => array( 'type' => 'list', 'label' => 'Filters',
                                'help' => 'One per line as a PostgREST query parameter: status=eq.published, price=lt.10, tags=cs.{tea}, '
                                        . 'or=(stock.gt.0,preorder.is.true); operators eq, neq, gt, gte, lt, lte, like, ilike, is, in, cs, cd and not.' ),
            'order' => array( 'type' => 'string', 'label' => 'Order', 'max' => 1000,
                              'help' => 'e.g. published_at.desc,id; an order keeps the pages stable' ),
            'paging' => array( 'type' => 'enum', 'label' => 'Paging', 'default' => 'range',
                               'values' => array( 'range' => 'Range header (Range-Unit: items)', 'offset' => 'limit and offset parameters' ) ),
            'count' => array( 'type' => 'enum', 'label' => 'Count', 'default' => 'none',
                              'values' => array( 'none' => 'No count (fastest)', 'exact' => 'Exact (Prefer: count=exact)',
                                                 'planned' => 'Planned (the planner\'s estimate)', 'estimated' => 'Estimated' ),
                              'help' => 'Whether the server tells the total (Content-Range); Test connection shows it' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 1000, 'min' => 1, 'max' => 10000,
                                  'help' => 'Rows per request (Supabase answers at most its max rows setting, 1000 by default)' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most rows', 'default' => 0, 'min' => 0, 'help' => '0: every row' ),
            'api_key' => array( 'type' => 'secret', 'label' => 'API key (name of a secret)',
                                'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding the anon key (sent as apikey and bearer token); '
                                        . 'empty for a PostgREST server without authentication' ),
            'bearer' => array( 'type' => 'secret', 'label' => 'Bearer token (name of a secret)',
                               'help' => 'Optional: a JWT of a read-only role sent as the bearer token instead of the API key' ),
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of objects stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The column that names a row' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the checks --------------------------------------------------------------------------------------------------

    /**
     * Whether a select only names columns: names, aliases (alias:column), casts (::text), JSON paths (->, ->>), *,
     * embedded resources (authors(name), author:authors!fk(name)) and the aggregates count(), sum() ...; nothing that
     * ends the parameter or opens another one.
     *
     * @param string $select
     * @return string|null the reason it is refused, null when it may be sent
     */
    public static function checkSelect( $select )
    {
        $select = preg_replace( '/\s+/', '', (string)$select );
        if ( $select === '' )
            return null;
        if ( !preg_match( '/^[A-Za-z0-9_$*,:().!>\-"]+$/', $select ) )
            return 'the select may hold only column names, aliases, casts, JSON paths and embedded resources';
        $depth = 0;
        foreach ( str_split( $select ) as $char )
        {
            $depth += $char === '(' ? 1 : ( $char === ')' ? -1 : 0 );
            if ( $depth < 0 || $depth > 8 )
                return 'the parentheses of the select do not match';
        }
        return $depth === 0 ? null : 'the parentheses of the select do not match';
    }

    /**
     * Whether a filter line only compares: column=[not.]operator.value with an operator of OPERATORS, or
     * [not.]and|or=(...) whose conditions do the same at any depth. The column may be dotted for an embedded
     * resource (author.name=eq.x, author.or=(...)).
     *
     * @param string $line
     * @return array|string the list ( string name, string value ) to send, or the reason it is refused
     */
    public static function checkFilter( $line )
    {
        $line = trim( (string)$line );
        $at = strpos( $line, '=' );
        if ( $at === false || $at === 0 )
            return "a filter is column=operator.value: $line";
        $name = substr( $line, 0, $at );
        $value = substr( $line, $at + 1 );
        if ( !preg_match( '/^([A-Za-z_][A-Za-z0-9_$]*\.)*(not\.)?[A-Za-z_][A-Za-z0-9_$]*$/', $name ) )
            return "the column of a filter is a name (author.name for an embedded resource): $name";
        $parts = explode( '.', $name );
        $last = strtolower( (string)end( $parts ) );
        if ( in_array( $last, array( 'and', 'or' ), true ) )
        {
            $refusal = self::checkLogic( $value, 1 );
            return $refusal === null ? array( $name, $value ) : $refusal;
        }
        if ( in_array( strtolower( $name ), self::RESERVED, true ) || strpos( $name, 'not.' ) !== false )
            return "$name cannot be set as a filter" . ( in_array( strtolower( $name ), array( 'select', 'order', 'limit', 'offset' ), true ) ? ' (it is an option of its own)' : '' );
        $refusal = self::checkCondition( $value );
        return $refusal === null ? array( $name, $value ) : $refusal;
    }

    /** @return string|null the reason an operator.value is refused (not.op.value, op(any).{a,b}, op.value) */
    protected static function checkCondition( $value )
    {
        if ( !preg_match( '/^(not\.)?([a-z]+)(\((any|all)\))?\.(.*)$/s', (string)$value, $m ) )
            return "a filter value is operator.value (eq.published): $value";
        if ( !in_array( $m[2], self::OPERATORS, true ) )
            return "the operator {$m[2]} is not allowed; only " . implode( ', ', self::OPERATORS ) . ' (and not.)';
        if ( $m[2] === 'is' && !in_array( strtolower( $m[5] ), array( 'null', 'not_null', 'true', 'false', 'unknown' ), true ) )
            return 'is compares with null, not_null, true, false or unknown';
        if ( $m[2] === 'in' && !preg_match( '/^\(.*\)$/s', $m[5] ) )
            return 'in needs a list in parentheses: in.(a,b)';
        if ( strpbrk( $m[5], "\r\n\0" ) !== false )
            return 'a filter value is one line';
        return null;
    }

    /**
     * The conditions of and=(...) or or=(...): a comma separated list (commas inside parentheses, braces and double
     * quotes do not count) of column.[not.]operator.value or [not.]and(...)/or(...).
     *
     * @param string $value
     * @param int $depth
     * @return string|null the reason it is refused
     */
    protected static function checkLogic( $value, $depth )
    {
        if ( $depth > 8 )
            return 'and/or are nested too deep';
        $value = trim( (string)$value );
        if ( strlen( $value ) < 2 || $value[0] !== '(' || substr( $value, -1 ) !== ')' )
            return 'and/or need their conditions in parentheses: or=(a.eq.1,b.eq.2)';
        $items = self::splitTop( substr( $value, 1, -1 ) );
        if ( $items === null )
            return 'the parentheses or quotes of and/or do not match';
        foreach ( $items as $item )
        {
            if ( preg_match( '/^(not\.)?(and|or)(\(.*\))$/s', $item, $m ) )
            {
                $refusal = self::checkLogic( $m[3], $depth + 1 );
                if ( $refusal !== null )
                    return $refusal;
                continue;
            }
            if ( !preg_match( '/^[A-Za-z_][A-Za-z0-9_$]*(->>?[A-Za-z0-9_]+)*\.(.*)$/s', $item, $m ) )
                return "a condition of and/or is column.operator.value: $item";
            $refusal = self::checkCondition( $m[2] );
            if ( $refusal !== null )
                return $refusal;
        }
        return null;
    }

    /** @return string[]|null the items of a comma separated list at the top level, null when the brackets do not match */
    protected static function splitTop( $text )
    {
        $items = array();
        $depth = 0;
        $quoted = false;
        $current = '';
        $length = strlen( $text );
        for ( $i = 0; $i < $length; $i++ )
        {
            $char = $text[$i];
            if ( $quoted )
            {
                if ( $char === '\\' && $i + 1 < $length )
                {
                    $current .= $char . $text[++$i];
                    continue;
                }
                if ( $char === '"' )
                    $quoted = false;
                $current .= $char;
                continue;
            }
            if ( $char === '"' )
                $quoted = true;
            elseif ( $char === '(' || $char === '{' )
                $depth++;
            elseif ( $char === ')' || $char === '}' )
                $depth--;
            if ( $depth < 0 )
                return null;
            if ( $char === ',' && $depth === 0 )
            {
                $items[] = trim( $current );
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if ( $depth !== 0 || $quoted )
            return null;
        $items[] = trim( $current );
        return $items;
    }

    /**
     * Whether an order is a list of column[.asc|.desc][.nullsfirst|.nullslast] (an embedded resource's column too).
     *
     * @param string $order
     * @return string|null the reason it is refused
     */
    public static function checkOrder( $order )
    {
        $order = trim( (string)$order );
        if ( $order === '' )
            return null;
        foreach ( explode( ',', $order ) as $item )
            if ( !preg_match( '/^([A-Za-z_][A-Za-z0-9_$]*\()?[A-Za-z_][A-Za-z0-9_$]*(->>?[A-Za-z0-9_]+)*\)?(\.(asc|desc))?(\.(nullsfirst|nullslast))?$/', trim( $item ) ) )
                return 'the order is a list of column.asc or column.desc (nullsfirst, nullslast): ' . trim( $item );
        return null;
    }

    // ---- the plan ----------------------------------------------------------------------------------------------------

    /**
     * The request of the options, checked: the address of the table without paging, the headers and what paging needs.
     *
     * @param array $options
     * @param string[] $used the secrets used, for masking
     * @return array url, trusted, headers, paging, page, limit
     * @throws expImportSourceException
     */
    public static function plan( array $options, array &$used )
    {
        list( $base, $trusted ) = expImportDocumentHttp::base( isset( $options['base_url'] ) ? $options['base_url'] : '', 'SupabaseSourceSettings', self::LABEL );
        $host = strtolower( (string)parse_url( $base, PHP_URL_HOST ) );
        $path = (string)parse_url( $base, PHP_URL_PATH );
        if ( ( $path === '' || $path === '/' ) && preg_match( '/\.supabase\.(co|in|net)$/', $host ) )
            $base .= '/rest/v1';
        $table = isset( $options['table'] ) ? trim( (string)$options['table'] ) : '';
        if ( !preg_match( '/^[A-Za-z_][A-Za-z0-9_$-]*$/', $table ) )
            throw new expImportSourceException( 'Supabase: the table is missing or not a name' );
        if ( strtolower( $table ) === 'rpc' )
            throw new expImportSourceException( 'Supabase: functions (rpc) are not read, only tables and views' );
        $query = array();
        $select = isset( $options['select'] ) ? preg_replace( '/\s+/', '', (string)$options['select'] ) : '';
        $refusal = self::checkSelect( $select );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Supabase: ' . $refusal );
        $query[] = 'select=' . self::encode( $select !== '' ? $select : '*' );
        $filters = isset( $options['filters'] ) ? $options['filters'] : array();
        if ( is_string( $filters ) )
            $filters = preg_split( '/\r\n|\r|\n/', $filters );
        foreach ( (array)$filters as $line )
        {
            if ( trim( (string)$line ) === '' )
                continue;
            $filter = self::checkFilter( (string)$line );
            if ( is_string( $filter ) )
                throw new expImportSourceException( 'Supabase: ' . $filter );
            $query[] = self::encode( $filter[0] ) . '=' . self::encode( $filter[1] );
        }
        $order = isset( $options['order'] ) ? trim( (string)$options['order'] ) : '';
        $refusal = self::checkOrder( $order );
        if ( $refusal !== null )
            throw new expImportSourceException( 'Supabase: ' . $refusal );
        if ( $order !== '' )
            $query[] = 'order=' . self::encode( preg_replace( '/\s+/', '', $order ) );
        $headers = array();
        $key = isset( $options['api_key'] ) ? trim( (string)$options['api_key'] ) : '';
        $bearer = isset( $options['bearer'] ) ? trim( (string)$options['bearer'] ) : '';
        if ( $key !== '' )
        {
            $used[] = $key;
            $value = expImportSecrets::required( $key, 'Supabase API key' );
            $headers['apikey'] = $value;
            $headers['Authorization'] = 'Bearer ' . $value;
        }
        if ( $bearer !== '' )
        {
            $used[] = $bearer;
            $headers['Authorization'] = 'Bearer ' . expImportSecrets::required( $bearer, 'Supabase bearer token' );
        }
        $schema = isset( $options['schema'] ) ? trim( (string)$options['schema'] ) : '';
        if ( $schema !== '' )
            $headers['Accept-Profile'] = $schema;
        $count = isset( $options['count'] ) && in_array( $options['count'], array( 'exact', 'planned', 'estimated' ), true ) ? (string)$options['count'] : '';
        if ( $count !== '' )
            $headers['Prefer'] = 'count=' . $count;
        return array( 'url' => $base . '/' . rawurlencode( $table ) . '?' . implode( '&', $query ), 'trusted' => $trusted, 'headers' => $headers,
                      'paging' => isset( $options['paging'] ) && $options['paging'] === 'offset' ? 'offset' : 'range',
                      'page' => max( 1, min( 10000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 1000 ) ),
                      'limit' => max( 0, isset( $options['limit'] ) ? (int)$options['limit'] : 0 ) );
    }

    /** @return string a query value encoded as PostgREST reads it (the reserved characters of the syntax kept) */
    protected static function encode( $value )
    {
        return strtr( rawurlencode( (string)$value ), array( '%2C' => ',', '%28' => '(', '%29' => ')', '%3A' => ':', '%2A' => '*', '%21' => '!' ) );
    }

    /**
     * The request of one page.
     *
     * @param array $plan
     * @param int $offset
     * @param int $size
     * @return array the list ( string url, array headers )
     */
    public static function pageRequest( array $plan, $offset, $size )
    {
        $headers = $plan['headers'];
        if ( $plan['paging'] === 'offset' )
            return array( $plan['url'] . '&limit=' . (int)$size . '&offset=' . (int)$offset, $headers );
        $headers['Range-Unit'] = 'items';
        $headers['Range'] = (int)$offset . '-' . ( (int)$offset + (int)$size - 1 );
        return array( $plan['url'], $headers );
    }

    /** @return int|null the total of a Content-Range header (0-999/1234), null when the server did not count */
    public static function total( array $lines )
    {
        $range = expImportPlatformHttp::header( $lines, 'Content-Range' );
        return preg_match( '#/(\d+)\s*$#', $range, $m ) ? (int)$m[1] : null;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the rows into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most rows (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $used = array();
        try
        {
            $plan = self::plan( $options, $used );
            expImportApiHttp::writeSnapshot( self::rows( $plan, $options, (int)$limit ), $target, (int)$limit, self::LABEL );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /**
     * The flattened rows, page by page.
     *
     * @param array $plan
     * @param array $options arrays, separator
     * @param int $limit 0: all
     * @param int|null $total set to the server's total when it counted
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function rows( array $plan, array $options, $limit, &$total = null )
    {
        $limit = $plan['limit'] > 0 && ( $limit <= 0 || $plan['limit'] < $limit ) ? $plan['limit'] : (int)$limit;
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'SupabaseSourceSettings', 'MaxPages', 1000 ) );
        $arrays = isset( $options['arrays'] ) && $options['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : ', ';
        $offset = 0;
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $size = $limit > 0 ? min( $plan['page'], $limit - $n ) : $plan['page'];
            list( $url, $headers ) = self::pageRequest( $plan, $offset, $size );
            try
            {
                list( $data, $lines ) = expImportApiHttp::answer( self::LABEL, 'GET', $url, $headers, null, self::INTERVAL, self::BACKOFF, $plan['trusted'] );
            }
            catch ( expImportSourceException $e )
            {
                // 416: the range starts after the last row (the previous page was full and the last one)
                if ( $e->getCode() === 416 )
                    return;
                throw $e;
            }
            $count = self::total( $lines );
            if ( $count !== null )
                $total = $count;
            if ( array_values( $data ) !== $data )
                throw new expImportSourceException( 'Supabase: the answer is not a list of rows' );
            foreach ( $data as $row )
            {
                if ( !is_array( $row ) )
                    continue;
                yield expImportMongoDbSource::flatten( $row, $arrays, $separator );
                if ( ++$n >= $limit && $limit > 0 )
                    return;
            }
            $offset += count( $data );
            if ( count( $data ) < $size || ( $total !== null && $offset >= $total ) )
                return;
        }
        throw new expImportSourceException( "Supabase: the table has more pages than [SupabaseSourceSettings] MaxPages ($maxPages) allows" );
    }

    /**
     * The editor's "Test connection": reads the first row and reports its columns (and the total when the server
     * counts), or why it failed (never the key). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $used = array();
        try
        {
            $plan = self::plan( $options, $used );
            $plan['page'] = 1;
            $total = null;
            $first = null;
            foreach ( self::rows( $plan, $options, 1, $total ) as $row )
            {
                $first = $row;
                break;
            }
            $counted = $total !== null ? " ($total rows)" : '';
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => 'connected; the table (filters) gives no row' . $counted );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => $total !== null ? $total : 1, 'driver' => 'api',
                          'message' => 'connected, the first row has these columns' . $counted );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'PostgREST API over HTTPS' ) );
    }
}
