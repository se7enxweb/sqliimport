<?php
/**
 * File containing the expImportBigQuerySource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a Google BigQuery query (GoogleSQL), read through the BigQuery REST API v2 with a service account
 * (expImportGoogleAuth). One record is one row: its columns by the schema of the result, RECORD columns flattened to
 * dotted names, REPEATED columns as JSON.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() runs the query (jobs.query, useLegacySql
 * false) and pages through its result (getQueryResults with pageToken), writing the rows into the run's snapshot,
 * one JSON object per line.
 *
 * Only reading is possible:
 *   - checkQuery() accepts a single SELECT or WITH ... SELECT and refuses, outside string literals, comments and
 *     quoted names, every word of DML (INSERT, UPDATE, DELETE, MERGE, TRUNCATE), DDL (CREATE, ALTER, DROP, UNDROP),
 *     scripting (DECLARE, SET, BEGIN, LOOP, WHILE, EXECUTE IMMEDIATE ...), EXPORT DATA, LOAD DATA, CALL, GRANT and
 *     REVOKE, EXTERNAL_QUERY, and a second statement after a semicolon
 *   - the token's scope is "bigquery" (the scope bigquery.readonly does not allow running a query job); the account
 *     should have the IAM roles roles/bigquery.jobUser on the project that runs the job and
 *     roles/bigquery.dataViewer on the datasets it reads, nothing more
 *   - every query has maximumBytesBilled (the option, else [GoogleCloudSourceSettings] MaximumBytesBilled), so a
 *     query that would scan more fails instead of costing more; Test connection is a dry run that reports the bytes
 *     the query would process and costs nothing
 *   - the service account key is the name of a secret, never stored in the import, and masked with the token in every
 *     message
 */
class expImportBigQuerySource extends expImportJsonSource
{
    const API = 'https://bigquery.googleapis.com/bigquery/v2';

    /** The scope of the token (bigquery.readonly cannot run query jobs; the IAM roles keep the account read-only). */
    const SCOPE = 'https://www.googleapis.com/auth/bigquery';

    /** The words refused outside literals and quoted names: DML, DDL, scripting, export, load, procedures, rights. */
    const FORBIDDEN = array( 'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'TRUNCATE', 'CREATE', 'ALTER', 'DROP', 'UNDROP',
                             'EXPORT', 'LOAD', 'CALL', 'DECLARE', 'SET', 'BEGIN', 'COMMIT', 'ROLLBACK', 'TRANSACTION',
                             'EXECUTE', 'IMMEDIATE', 'LOOP', 'WHILE', 'GRANT', 'REVOKE', 'ASSERT', 'RAISE', 'RETURN', 'EXTERNAL_QUERY' );

    /** @var array|null what drivers() sees of this PHP (tests): allow_url_fopen, openssl */
    public static $environment = null;

    public static function identifier()
    {
        return 'bigquery';
    }

    public static function label()
    {
        return 'Google BigQuery query';
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
        return 'The rows of a BigQuery query (GoogleSQL) through the BigQuery REST API with a service account: one record '
             . 'is one row, RECORD columns flattened to dotted names, REPEATED as JSON. Only a single SELECT or WITH is '
             . 'accepted, every query has a bytes billed limit, and Test connection is a free dry run. The service '
             . 'account key is the name of a secret in the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three orders as the source writes it from a query (one row per line)',
                   'file' => 'bigquery-orders.ndjson',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader',
                                       'query' => 'SELECT order_id, customer, total, created_at, items FROM shop.orders', 'key' => 'order_id' ) ),
            array( 'label' => 'The orders of the last 30 days, at most 1 GB scanned, in the EU location',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader', 'location' => 'EU',
                                       'query' => "SELECT order_id, customer.name AS customer, total, created_at\nFROM `example-project.shop.orders`\nWHERE created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)",
                                       'maximum_bytes_billed' => 1000000000, 'key' => 'order_id' ) ),
            array( 'label' => 'A public dataset, with WITH (the job runs in the project of the service account)',
                   'options' => array( 'project' => 'example-project', 'credentials_secret' => 'gcp_reader',
                                       'query' => "WITH words AS (SELECT word, SUM(word_count) AS n FROM `bigquery-public-data.samples.shakespeare` GROUP BY word)\nSELECT * FROM words ORDER BY n DESC LIMIT 100",
                                       'key' => 'word' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'project' => array( 'type' => 'string', 'label' => 'Project ID', 'required' => true, 'max' => 100,
                                'pattern' => '/^[a-z0-9][a-z0-9.:-]{2,99}$/', 'help' => 'The project that runs (and pays for) the query job' ),
            'credentials_secret' => array( 'type' => 'secret', 'label' => 'Service account key (name of a secret)', 'required' => true,
                                           'help' => 'The name of a secret whose value is the JSON key file of a service account with roles/bigquery.jobUser and roles/bigquery.dataViewer' ),
            'location' => array( 'type' => 'string', 'label' => 'Location', 'max' => 40, 'pattern' => '/^[A-Za-z0-9-]*$/',
                                 'help' => 'The location of the datasets (EU, US, europe-west3). Empty: BigQuery finds it' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'One SELECT or WITH in GoogleSQL (standard SQL); DML, DDL, scripts, EXPORT DATA and CALL are refused' ),
            'maximum_bytes_billed' => array( 'type' => 'int', 'label' => 'Most bytes billed', 'default' => 0, 'min' => 0,
                                             'help' => 'A query that would scan more fails. 0: [GoogleCloudSourceSettings] MaximumBytesBilled' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 1000, 'min' => 1, 'max' => 100000, 'help' => 'Rows per request (maxResults)' ),
            'limit' => array( 'type' => 'int', 'label' => 'Most rows', 'default' => 0, 'min' => 0, 'help' => '0: all' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => '', 'help' => 'The column that names a row. Empty: the position' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * The query with its string literals, comments and quoted names replaced by blanks, so its words can be checked.
     *
     * @param string $sql
     * @return string|null null when a literal, a quoted name or a comment is not closed
     */
    public static function strip( $sql )
    {
        $sql = (string)$sql;
        $out = '';
        $n = strlen( $sql );
        for ( $i = 0; $i < $n; )
        {
            $c = $sql[$i];
            $two = substr( $sql, $i, 2 );
            if ( $two === '--' || $c === '#' )
            {
                $end = strpos( $sql, "\n", $i );
                $i = $end === false ? $n : $end;
                $out .= ' ';
                continue;
            }
            if ( $two === '/*' )
            {
                $end = strpos( $sql, '*/', $i + 2 );
                if ( $end === false )
                    return null;
                $i = $end + 2;
                $out .= ' ';
                continue;
            }
            if ( $c === '\'' || $c === '"' || $c === '`' )
            {
                $quote = substr( $sql, $i, 3 ) === str_repeat( $c, 3 ) && $c !== '`' ? str_repeat( $c, 3 ) : $c;
                $j = $i + strlen( $quote );
                $closed = false;
                // a raw string (r'...', rb"..."): a backslash before its quote is read differently by different
                // parsers, so such a query is refused rather than guessed
                $raw = $c !== '`' && preg_match( '/(^|[^A-Za-z0-9_])[bB]?[rR][bB]?$/', substr( $sql, max( 0, $i - 4 ), $i - max( 0, $i - 4 ) ) );
                while ( $j < $n )
                {
                    if ( $sql[$j] === '\\' )
                    {
                        if ( $raw && $j + 1 < $n && $sql[$j + 1] === $c )
                            return null;
                        $j += 2;
                        continue;
                    }
                    if ( substr( $sql, $j, strlen( $quote ) ) === $quote )
                    {
                        $j += strlen( $quote );
                        $closed = true;
                        break;
                    }
                    $j++;
                }
                if ( !$closed )
                    return null;
                $out .= $c === '`' ? ' x ' : ' 0 ';
                $i = $j;
                continue;
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }

    /**
     * Whether a query only reads: a single SELECT or WITH, without a forbidden word outside literals.
     *
     * @param string $sql
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkQuery( $sql )
    {
        $stripped = self::strip( $sql );
        if ( $stripped === null )
            return 'a string, a quoted name or a comment is not closed (or a raw string has a backslash before a quote)';
        $stripped = rtrim( trim( $stripped ), "; \t\r\n" );
        if ( $stripped === '' )
            return 'the query is empty';
        if ( strpos( $stripped, ';' ) !== false )
            return 'only a single statement is accepted (no semicolon between statements, no script)';
        if ( !preg_match( '/^\(*\s*(SELECT|WITH)\b/i', $stripped ) )
            return 'the query must start with SELECT or WITH';
        if ( preg_match_all( '/\b[A-Za-z_][A-Za-z0-9_]*\b/', $stripped, $m ) )
            foreach ( $m[0] as $word )
                if ( in_array( strtoupper( $word ), self::FORBIDDEN, true ) )
                    return 'the word ' . strtoupper( $word ) . ' is not allowed: the source only reads (a single SELECT)';
        return null;
    }

    // ---- the connection ----------------------------------------------------------------------------------------------

    /**
     * The project's API address and the Authorization header.
     *
     * @param array $options
     * @param string[] $used
     * @return array ( string address of the project, array headers )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        $project = isset( $options['project'] ) ? trim( (string)$options['project'] ) : '';
        if ( !preg_match( '/^[a-z0-9][a-z0-9.:-]{2,99}$/', $project ) )
            throw new expImportSourceException( 'BigQuery: the project ID is required' );
        $ref = isset( $options['credentials_secret'] ) ? trim( (string)$options['credentials_secret'] ) : '';
        $used[] = $ref;
        $token = expImportGoogleAuth::accessToken( $ref, self::SCOPE, 'BigQuery' );
        return array( self::API . '/projects/' . rawurlencode( $project ), array( 'Authorization' => 'Bearer ' . $token ) );
    }

    /**
     * The body of jobs.query.
     *
     * @param array $options
     * @param int $size maxResults
     * @param bool $dryRun
     * @return array
     * @throws expImportSourceException
     */
    public static function request( array $options, $size, $dryRun )
    {
        $sql = isset( $options['query'] ) ? (string)$options['query'] : '';
        $refusal = self::checkQuery( $sql );
        if ( $refusal !== null )
            throw new expImportSourceException( 'BigQuery: ' . $refusal );
        $location = isset( $options['location'] ) ? trim( (string)$options['location'] ) : '';
        if ( $location !== '' && !preg_match( '/^[A-Za-z0-9-]{2,40}$/', $location ) )
            throw new expImportSourceException( 'BigQuery: the location is a name such as EU or europe-west3' );
        $bytes = isset( $options['maximum_bytes_billed'] ) ? (int)$options['maximum_bytes_billed'] : 0;
        if ( $bytes <= 0 )
            $bytes = (int)expImportPlatformHttp::setting( 'GoogleCloudSourceSettings', 'MaximumBytesBilled', 1073741824 );
        $body = array( 'query' => rtrim( trim( $sql ), ';' ), 'useLegacySql' => false, 'maxResults' => (int)$size, 'timeoutMs' => 10000,
                       'useQueryCache' => true, 'formatOptions' => array( 'useInt64Timestamp' => true ) );
        if ( $bytes > 0 )
            $body['maximumBytesBilled'] = (string)$bytes;
        if ( $location !== '' )
            $body['location'] = $location;
        if ( $dryRun )
            $body['dryRun'] = true;
        return $body;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Runs the query and writes the rows into the snapshot, one JSON object per line.
     *
     * @param array $options
     * @param string $target
     * @param int $limit the editor's sample; 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::rows( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'BigQuery: the snapshot cannot be written' );
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
     * The rows of the query, page by page.
     *
     * @param array $options
     * @param int $limit 0: all (else also the option limit)
     * @param string[] $used
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function rows( array $options, $limit, array &$used )
    {
        $max = isset( $options['limit'] ) ? max( 0, (int)$options['limit'] ) : 0;
        if ( $limit > 0 && ( $max === 0 || $limit < $max ) )
            $max = (int)$limit;
        $size = max( 1, min( 100000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 1000 ) );
        if ( $max > 0 )
            $size = min( $size, $max );
        $body = self::request( $options, $size, false );
        list( $base, $headers ) = self::connection( $options, $used );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'GoogleCloudSourceSettings', 'MaxPages', 1000 ) );
        $answer = expImportCloudHttp::json( 'BigQuery', 'POST', $base . '/queries', $headers, $body );
        $job = isset( $answer['jobReference']['jobId'] ) ? (string)$answer['jobReference']['jobId'] : '';
        $location = isset( $answer['jobReference']['location'] ) ? (string)$answer['jobReference']['location'] : ( isset( $body['location'] ) ? $body['location'] : '' );
        $results = function ( $token ) use ( $base, $headers, $job, $location, $size )
        {
            if ( !preg_match( '/^[A-Za-z0-9_-]+$/', $job ) )
                throw new expImportSourceException( 'BigQuery: the answer names no job to read the result of' );
            $url = $base . '/queries/' . rawurlencode( $job ) . '?maxResults=' . $size . '&timeoutMs=10000&formatOptions.useInt64Timestamp=true'
                 . ( $location !== '' ? '&location=' . rawurlencode( $location ) : '' ) . ( $token !== null ? '&pageToken=' . rawurlencode( $token ) : '' );
            return expImportCloudHttp::json( 'BigQuery', 'GET', $url, $headers );
        };
        for ( $poll = 0; empty( $answer['jobComplete'] ); $poll++ )
        {
            if ( $poll >= $maxPages )
                throw new expImportSourceException( 'BigQuery: the query job did not finish' );
            $answer = $results( null );
        }
        $fields = isset( $answer['schema']['fields'] ) && is_array( $answer['schema']['fields'] ) ? $answer['schema']['fields'] : array();
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            foreach ( isset( $answer['rows'] ) && is_array( $answer['rows'] ) ? $answer['rows'] : array() as $row )
            {
                yield self::row( $fields, (array)$row );
                $n++;
                if ( $max > 0 && $n >= $max )
                    return;
            }
            $token = isset( $answer['pageToken'] ) && is_string( $answer['pageToken'] ) && $answer['pageToken'] !== '' ? $answer['pageToken'] : null;
            if ( $token === null )
                return;
            $answer = $results( $token );
        }
        throw new expImportSourceException( 'BigQuery: the result has more pages than [GoogleCloudSourceSettings] MaxPages (' . $maxPages . ')' );
    }

    /**
     * A row of the API (f: [ {v: ...} ]) as a record by the schema: RECORD columns flattened, REPEATED as JSON.
     *
     * @param array $fields the schema's fields
     * @param array $row
     * @return array
     */
    public static function row( array $fields, array $row )
    {
        return expImportMongoDbSource::flatten( self::struct( $fields, $row ), 'json' );
    }

    /** @return array the cells of a row or a RECORD by the names of its fields */
    protected static function struct( array $fields, $row )
    {
        $out = array();
        $cells = is_array( $row ) && isset( $row['f'] ) && is_array( $row['f'] ) ? array_values( $row['f'] ) : array();
        foreach ( array_values( $fields ) as $i => $field )
        {
            $name = isset( $field['name'] ) ? (string)$field['name'] : 'f' . $i;
            $out[$name] = self::cell( (array)$field, isset( $cells[$i] ) && is_array( $cells[$i] ) && array_key_exists( 'v', $cells[$i] ) ? $cells[$i]['v'] : null );
        }
        return $out;
    }

    /**
     * The value of a cell by its field: INT64 and NUMERIC as text, FLOAT64 a number, BOOL, TIMESTAMP ISO 8601 in UTC,
     * RECORD an array (flattened), REPEATED a JSON list, everything else its text.
     *
     * @param array $field name, type, mode, fields
     * @param mixed $v
     * @return mixed
     */
    public static function cell( array $field, $v )
    {
        if ( $v === null )
            return null;
        if ( isset( $field['mode'] ) && $field['mode'] === 'REPEATED' )
        {
            $inner = $field;
            $inner['mode'] = 'NULLABLE';
            $list = array();
            foreach ( is_array( $v ) ? $v : array() as $item )
                $list[] = self::plain( self::cell( $inner, is_array( $item ) && array_key_exists( 'v', $item ) ? $item['v'] : null ) );
            return (string)json_encode( $list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
        }
        $type = strtoupper( isset( $field['type'] ) ? (string)$field['type'] : 'STRING' );
        switch ( $type )
        {
            case 'RECORD':
            case 'STRUCT':
                $struct = self::struct( isset( $field['fields'] ) && is_array( $field['fields'] ) ? $field['fields'] : array(), $v );
                return $struct ? $struct : '{}';
            case 'FLOAT':
            case 'FLOAT64':
                return is_numeric( $v ) ? (float)$v : (string)$v;
            case 'BOOLEAN':
            case 'BOOL':
                return $v === true || strtolower( (string)$v ) === 'true';
            case 'TIMESTAMP':
                return self::timestamp( (string)$v );
        }
        return is_array( $v ) ? (string)json_encode( $v ) : (string)$v;
    }

    /** @return mixed a value with its arrays from cell() kept (for JSON lists of records) */
    protected static function plain( $value )
    {
        return $value === '{}' ? new stdClass() : $value;
    }

    /**
     * A TIMESTAMP cell as ISO 8601 in UTC: microseconds since the epoch (useInt64Timestamp) or seconds as a float.
     *
     * @param string $v
     * @return string
     */
    public static function timestamp( $v )
    {
        if ( preg_match( '/^-?\d+$/', $v ) )
        {
            $micros = (int)$v;
            $seconds = intdiv( $micros, 1000000 );
            $fraction = $micros - $seconds * 1000000;
            if ( $fraction < 0 )
            {
                $seconds--;
                $fraction += 1000000;
            }
        }
        elseif ( is_numeric( $v ) )
        {
            $float = (float)$v;
            $seconds = (int)floor( $float );
            $fraction = (int)round( ( $float - $seconds ) * 1000000 );
            if ( $fraction >= 1000000 )
            {
                $seconds++;
                $fraction -= 1000000;
            }
        }
        else
            return $v;
        return gmdate( 'Y-m-d\TH:i:s', $seconds ) . ( $fraction ? '.' . rtrim( sprintf( '%06d', $fraction ), '0' ) : '' ) . 'Z';
    }

    /** @return string[] the column names of a schema as row() writes them (RECORD fields dotted) */
    public static function columns( array $fields, $prefix = '' )
    {
        $out = array();
        foreach ( $fields as $field )
        {
            $name = $prefix . ( isset( $field['name'] ) ? (string)$field['name'] : '' );
            if ( isset( $field['type'] ) && in_array( strtoupper( (string)$field['type'] ), array( 'RECORD', 'STRUCT' ), true )
                 && ( !isset( $field['mode'] ) || $field['mode'] !== 'REPEATED' ) && !empty( $field['fields'] ) )
                $out = array_merge( $out, self::columns( (array)$field['fields'], $name . '.' ) );
            else
                $out[] = $name;
        }
        return $out;
    }

    /**
     * The editor's "Test connection": a dry run of the query (free), which reports the columns and the bytes the query
     * would process, or why it failed (the key and the token masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $driver = 'BigQuery REST API v2';
        try
        {
            $body = self::request( $options, 1, true );
            list( $base, $headers ) = self::connection( $options, $used );
            $answer = expImportCloudHttp::json( 'BigQuery', 'POST', $base . '/queries', $headers, $body );
            $fields = isset( $answer['schema']['fields'] ) && is_array( $answer['schema']['fields'] ) ? $answer['schema']['fields'] : array();
            $bytes = isset( $answer['totalBytesProcessed'] ) ? (float)$answer['totalBytesProcessed'] : 0.0;
            $limit = isset( $body['maximumBytesBilled'] ) ? (float)$body['maximumBytesBilled'] : 0.0;
            $message = 'dry run: the query would process ' . self::bytes( $bytes ) . ( $limit > 0 ? ' (limit ' . self::bytes( $limit ) . ')' : '' );
            if ( $limit > 0 && $bytes > $limit )
                return array( 'ok' => false, 'columns' => self::columns( $fields ), 'rows' => 0, 'driver' => $driver,
                              'message' => $message . '; a run would fail: raise Most bytes billed or narrow the query' );
            return array( 'ok' => true, 'columns' => self::columns( $fields ), 'rows' => 0, 'driver' => $driver, 'message' => $message . '; these are its columns' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportGoogleAuth::mask( $e->getMessage(), $used ) );
        }
    }

    /** @return string bytes as 12.3 MB */
    public static function bytes( $bytes )
    {
        $units = array( 'bytes', 'KB', 'MB', 'GB', 'TB', 'PB' );
        $i = 0;
        while ( $bytes >= 1000 && $i < count( $units ) - 1 )
        {
            $bytes /= 1000;
            $i++;
        }
        return ( $i === 0 ? (string)(int)$bytes : number_format( $bytes, 1, '.', '' ) ) . ' ' . $units[$i];
    }

    /** @return array https => array( 'label', 'available', 'via', 'needs', 'worker', 'worker_php' ) */
    public static function drivers()
    {
        $list = expImportCloudHttp::drivers( self::$environment, 'BigQuery REST API' );
        $list['https']['needs'] .= '; openssl also signs the service account\'s token request';
        return $list;
    }
}
