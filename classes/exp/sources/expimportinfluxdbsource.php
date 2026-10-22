<?php
/**
 * File containing the expImportInfluxDbSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a query against InfluxDB: Flux through the API of InfluxDB 2 and Cloud (POST /api/v2/query, the
 * answer in annotated CSV), or InfluxQL through the API of InfluxDB 1 and the 1.x compatibility API (GET /query, the
 * answer in JSON). One record is one row: _time in ISO 8601 (RFC 3339), the tags and fields as columns, _measurement,
 * and with Flux _field and _value unless the query pivots (the option "pivot" appends one).
 *
 * Only reading is possible:
 *   - Flux: checkFlux() accepts only the packages of ALLOWED_PACKAGES (no http, sql, csv from a file or an address,
 *     secrets, socket, mqtt, kafka, notification endpoints such as slack, pagerduty, telegram, opsgenie, the contrib
 *     packages ...), and refuses to(), wideTo() and every other X.to() anywhere, string interpolation, and sleep()
 *   - InfluxQL: checkInfluxQl() accepts a single SELECT or SHOW and refuses INTO and every statement that writes or
 *     administers (DROP, DELETE, CREATE, ALTER, GRANT, REVOKE, KILL, SET PASSWORD)
 *   - give the token read access to the bucket only (an InfluxDB 2 "read" authorization); it is the name of a secret
 *     (expImportSecrets)
 *
 * Paging: InfluxQL with LIMIT and OFFSET appended (per series, as InfluxQL counts them) when the query has none;
 * Flux in one answer (narrow it with range() and filter(); answers are limited to [PlatformSettings]
 * MaxResponseMegabytes).
 */
class expImportInfluxDbSource extends expImportHttpQuerySource
{
    const NAME = 'InfluxDB';

    const SETTINGS = 'InfluxDbSourceSettings';

    const WORKER_PREFIX = 'influxdb';

    /** The Flux packages an import may import (universe needs no import). */
    const ALLOWED_PACKAGES = array(
        'array', 'strings', 'date', 'math', 'regexp', 'json', 'dict', 'types', 'join', 'interpolate', 'timezone', 'runtime', 'system',
        'internal/debug', 'profiler', 'influxdata/influxdb/schema', 'influxdata/influxdb/v1', 'influxdata/influxdb',
        'influxdata/influxdb/sample', 'sampledata', 'experimental', 'experimental/aggregate', 'experimental/array',
        'experimental/date/boundaries', 'experimental/geo', 'experimental/oee', 'experimental/query', 'experimental/table',
        'experimental/json', 'experimental/polyline', 'experimental/record', 'experimental/dynamic',
    );

    /** The InfluxQL words that write or administer. */
    const INFLUXQL_FORBIDDEN = array( 'INTO', 'DROP', 'DELETE', 'CREATE', 'ALTER', 'GRANT', 'REVOKE', 'KILL', 'SET', 'INSERT' );

    public static function identifier()
    {
        return 'influxdb';
    }

    public static function label()
    {
        return 'InfluxDB query (Flux or InfluxQL)';
    }

    public static function description()
    {
        return 'The rows of a Flux query (InfluxDB 2, Cloud) or an InfluxQL query (InfluxDB 1 and the 1.x API): one record per '
             . 'row with _time in ISO 8601 and the tags and fields as columns. Flux that writes (to(), wideTo()) or reaches '
             . 'out (http, sql, notification endpoints) and InfluxQL INTO are refused; the token is the name of a secret in '
             . 'the settings.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three readings of a measurement "climate" as the source writes it (pivoted, one row per line)',
                   'file' => 'influxdb-climate.ndjson',
                   'options' => array( 'base_url' => 'https://influx.example.invalid:8086', 'language' => 'flux', 'org' => 'example',
                                       'token_secret' => 'influx_read', 'pivot' => true, 'key' => '_time',
                                       'query' => "from(bucket: \"sensors\")\n  |> range(start: -1d)\n  |> filter(fn: (r) => r._measurement == \"climate\")" ) ),
            array( 'label' => 'The hourly mean temperature of the last week with InfluxQL (InfluxDB 1)',
                   'options' => array( 'base_url' => 'https://influx.example.invalid:8086', 'language' => 'influxql', 'database' => 'sensors',
                                       'auth' => 'basic', 'user' => 'reader', 'password_secret' => 'influx_reader',
                                       'query' => 'SELECT mean("temperature") FROM "climate" WHERE time > now() - 7d GROUP BY time(1h), "room"' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'required' => true,
                                 'help' => 'e.g. https://influx.example.org:8086 or the address of InfluxDB Cloud (a private network only with [InfluxDbSourceSettings] AllowedHosts[])' ),
            'language' => array( 'type' => 'enum', 'label' => 'Query language', 'default' => 'flux',
                                 'values' => array( 'flux' => 'Flux (InfluxDB 2, Cloud)', 'influxql' => 'InfluxQL (InfluxDB 1, the 1.x API)' ) ),
            'org' => array( 'type' => 'string', 'label' => 'Organization', 'max' => 255, 'help' => 'Flux: the organization name or ID' ),
            'database' => array( 'type' => 'string', 'label' => 'Database', 'max' => 255, 'help' => 'InfluxQL: the database (or the bucket mapped to it)' ),
            'retention_policy' => array( 'type' => 'string', 'label' => 'Retention policy', 'max' => 255, 'help' => 'InfluxQL: empty for the default' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'token',
                             'values' => array( 'token' => 'Token', 'basic' => 'User and password', 'none' => 'None' ) ),
            'token_secret' => array( 'type' => 'secret', 'label' => 'Token (name of a secret)',
                                     'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE with a token that may read the bucket, never the token' ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'max' => 255, 'help' => 'With User and password' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)', 'help' => 'With User and password' ),
            'query' => array( 'type' => 'text', 'label' => 'Query', 'required' => true,
                              'help' => 'Flux: from(bucket: "sensors") |> range(start: -1d) ...; InfluxQL: a single SELECT or SHOW' ),
            'pivot' => array( 'type' => 'bool', 'label' => 'One row per time (Flux)', 'default' => false,
                              'help' => 'Yes: appends pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value"), so each field is a column' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size (InfluxQL)', 'default' => 10000, 'min' => 1, 'max' => 1000000,
                                  'help' => 'Points per series and request (LIMIT ... OFFSET ...)' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'help' => 'The column that names a row; empty: the position' ),
        );
    }

    // ---- the query ---------------------------------------------------------------------------------------------------

    /**
     * Whether a Flux query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkFlux( $query )
    {
        try
        {
            list( $stripped, $literals ) = self::strip( $query, array( 'quotes' => array( '"' => array( 'string', true, false ) ),
                                                                         'line' => array( '//' ), 'regex' => true ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( trim( $stripped ) === '' )
            return 'the query is empty';
        foreach ( $literals as $literal )
            if ( strpos( $literal, '${' ) !== false )
                return 'string interpolation ${...} is not allowed';
        preg_match_all( '/(?<![\w.])import\s+(?:[A-Za-z_]\w*\s+)?\x00(\d+)\x00/', $stripped, $imports );
        $packages = array();
        foreach ( $imports[1] as $index )
        {
            $package = isset( $literals[(int)$index] ) ? $literals[(int)$index] : '';
            if ( !in_array( $package, self::ALLOWED_PACKAGES, true ) )
                return "the package \"$package\" is not allowed (it writes, sends or reads from outside the database)";
            $packages[] = $package;
        }
        if ( preg_match( '/(?<![\w.])import\b/', (string)preg_replace( '/(?<![\w.])import\s+(?:[A-Za-z_]\w*\s+)?\x00\d+\x00/', '', $stripped ) ) )
            return 'an import must name its package as a string';
        if ( preg_match( '/(?<![\w])(to|wideTo|sleep)\b(?!\s*:)/', $stripped, $m ) )
            return "the query contains {$m[1]}: only reading is allowed";
        return null;
    }

    /**
     * Whether an InfluxQL query only reads.
     *
     * @param string $query
     * @return string|null the reason it is refused, null when it may run
     */
    public static function checkInfluxQl( $query )
    {
        try
        {
            list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, false ), '"' => array( 'name', true, false ) ),
                                                             'line' => array( '--' ), 'block' => true ) );
        }
        catch ( expImportSourceException $e )
        {
            return substr( $e->getMessage(), strlen( self::NAME ) + 2 );
        }
        if ( self::hasSecondStatement( $stripped ) )
            return 'only one statement is allowed';
        $words = self::words( $stripped );
        if ( !$words || !in_array( $words[0], array( 'SELECT', 'SHOW' ), true ) )
            return 'only a SELECT or SHOW is allowed';
        $word = self::forbiddenWord( $stripped, self::INFLUXQL_FORBIDDEN );
        if ( $word !== null )
            return "the query contains $word: only reading is allowed";
        return null;
    }

    /** @return string the query of the options after its check, without a trailing ; */
    public static function queryOf( array $options )
    {
        $query = trim( isset( $options['query'] ) ? (string)$options['query'] : '' );
        $flux = !isset( $options['language'] ) || $options['language'] !== 'influxql';
        $refusal = $flux ? self::checkFlux( $query ) : self::checkInfluxQl( $query );
        if ( $refusal !== null )
            throw new expImportSourceException( self::NAME . ': ' . $refusal );
        return rtrim( $query, " \t\r\n;" );
    }

    /**
     * The address and the headers.
     *
     * @return array the list ( string base, array headers, string[] trusted )
     * @throws expImportSourceException
     */
    public static function connection( array $options, array &$used )
    {
        list( $base, $trusted ) = self::baseUrl( $options );
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'token';
        $headers = array();
        if ( $auth === 'token' )
        {
            $ref = isset( $options['token_secret'] ) ? trim( (string)$options['token_secret'] ) : '';
            $used[] = $ref;
            $headers['Authorization'] = 'Token ' . expImportSecrets::required( $ref, self::NAME . ': the token' );
        }
        elseif ( $auth === 'basic' )
            $headers = expImportPlatformHttp::authHeaders( $options, $used );
        return array( $base, $headers, $trusted );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    public static function rows( array $options, $limit, array &$used )
    {
        list( $base, $headers, $trusted ) = self::connection( $options, $used );
        $query = self::queryOf( $options );
        if ( isset( $options['language'] ) && $options['language'] === 'influxql' )
        {
            foreach ( self::influxQlRows( $base, $headers, $trusted, $options, $query, $limit ) as $row )
                yield $row;
            return;
        }
        list( $rows ) = self::flux( $base, $headers, $trusted, $options, $query, $limit );
        $n = 0;
        foreach ( $rows as $row )
        {
            yield $row;
            if ( $limit > 0 && ++$n >= $limit )
                return;
        }
    }

    /**
     * A Flux query and its rows.
     *
     * @return array the list ( array[] rows, string the server's version or '' )
     * @throws expImportSourceException
     */
    protected static function flux( $base, array $headers, array $trusted, array $options, $query, $limit )
    {
        if ( !empty( $options['pivot'] ) )
            $query .= "\n  |> pivot(rowKey: [\"_time\"], columnKey: [\"_field\"], valueColumn: \"_value\")";
        if ( $limit > 0 )
            $query .= "\n  |> limit(n: " . (int)$limit . ')';
        $org = isset( $options['org'] ) ? trim( (string)$options['org'] ) : '';
        $body = array( 'query' => $query, 'type' => 'flux',
                       'dialect' => array( 'header' => true, 'annotations' => array( 'datatype', 'group', 'default' ), 'delimiter' => ',' ) );
        $answer = self::send( 'POST', $base . '/api/v2/query' . ( $org !== '' ? '?org=' . rawurlencode( $org ) : '' ),
                              $headers + array( 'Accept' => 'application/csv', 'Content-Type' => 'application/json' ),
                              (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $trusted );
        return array( self::annotatedCsv( (string)$answer['body'] ), expImportPlatformHttp::header( (array)$answer['headers'], 'X-Influxdb-Version' ) );
    }

    /**
     * The rows of annotated CSV (one or more tables, each with its #datatype, #group and #default annotations and a
     * header), typed by #datatype; the columns result and table are left out.
     *
     * @param string $csv
     * @return array[]
     * @throws expImportSourceException an error table of the server
     */
    public static function annotatedCsv( $csv )
    {
        $in = fopen( 'php://temp', 'w+b' );
        fwrite( $in, (string)$csv );
        rewind( $in );
        $rows = array();
        $types = array();
        $defaults = array();
        $header = null;
        while ( ( $line = fgetcsv( $in, 0, ',', '"', '' ) ) !== false )
        {
            if ( $line === array( null ) || ( count( $line ) === 1 && trim( (string)$line[0] ) === '' ) )
            {
                // a blank line: the next table starts with its own annotations
                $header = null;
                $types = array();
                $defaults = array();
                continue;
            }
            $first = (string)$line[0];
            if ( $first === '#datatype' )
            {
                $types = $line;
                $header = null;
                continue;
            }
            if ( $first === '#default' )
            {
                $defaults = $line;
                continue;
            }
            if ( $first !== '' && $first[0] === '#' )
                continue;
            if ( $header === null )
            {
                $header = array_map( 'strval', $line );
                if ( in_array( 'error', $header, true ) && in_array( 'reference', $header, true ) )
                {
                    $error = fgetcsv( $in, 0, ',', '"', '' );
                    $at = array_search( 'error', $header, true );
                    fclose( $in );
                    throw new expImportSourceException( self::NAME . ': ' . ( is_array( $error ) && isset( $error[$at] ) ? (string)$error[$at] : 'the query failed' ) );
                }
                continue;
            }
            $row = array();
            foreach ( $header as $i => $name )
            {
                if ( $i === 0 || $name === 'result' || $name === 'table' || $name === '' )
                    continue;
                $value = isset( $line[$i] ) ? (string)$line[$i] : '';
                if ( $value === '' && isset( $defaults[$i] ) && (string)$defaults[$i] !== '' )
                    $value = (string)$defaults[$i];
                $row[$name] = self::fluxValue( $value, isset( $types[$i] ) ? (string)$types[$i] : 'string' );
            }
            $rows[] = $row;
        }
        fclose( $in );
        return $rows;
    }

    /**
     * A value of annotated CSV by its #datatype: long an integer, unsignedLong an integer (text when it is larger than
     * PHP's), double a float (NaN and infinities as text), boolean a boolean, dateTime RFC 3339 text, the rest text;
     * an empty value null.
     *
     * @param string $value
     * @param string $type
     * @return mixed
     */
    public static function fluxValue( $value, $type )
    {
        if ( $value === '' )
            return null;
        switch ( $type )
        {
            case 'long':
            case 'unsignedLong':
                return self::integer( $value );
            case 'double':
                return is_numeric( $value ) ? (float)$value : $value;
            case 'boolean':
                return $value === 'true';
        }
        return $value;
    }

    /**
     * The rows of an InfluxQL query, paged with LIMIT and OFFSET when the query has neither.
     *
     * @return Generator array
     * @throws expImportSourceException
     */
    protected static function influxQlRows( $base, array $headers, array $trusted, array $options, $query, $limit )
    {
        $size = max( 1, min( 1000000, isset( $options['page_size'] ) ? (int)$options['page_size'] : 10000 ) );
        if ( $limit > 0 )
            $size = min( $size, $limit );
        list( $stripped ) = self::strip( $query, array( 'quotes' => array( "'" => array( 'string', true, false ), '"' => array( 'name', true, false ) ),
                                                         'line' => array( '--' ), 'block' => true ) );
        $words = self::words( $stripped );
        $page = $words[0] === 'SELECT' && !array_intersect( $words, array( 'LIMIT', 'OFFSET', 'SLIMIT', 'SOFFSET' ) );
        $n = 0;
        for ( $p = 0; $p < self::maxPages(); $p++ )
        {
            $q = $page ? $query . ' LIMIT ' . $size . ' OFFSET ' . ( $p * $size ) : $query;
            list( $rows, $most ) = self::influxQl( $base, $headers, $trusted, $options, $q );
            foreach ( $rows as $row )
            {
                yield $row;
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            if ( !$page || $most < $size )
                return;
        }
    }

    /**
     * One InfluxQL request.
     *
     * @return array the list ( array[] rows, int the most points of one series, string version )
     * @throws expImportSourceException
     */
    protected static function influxQl( $base, array $headers, array $trusted, array $options, $query )
    {
        $params = array( 'q' => $query );
        foreach ( array( 'database' => 'db', 'retention_policy' => 'rp' ) as $option => $param )
            if ( isset( $options[$option] ) && trim( (string)$options[$option] ) !== '' )
                $params[$param] = trim( (string)$options[$option] );
        $answer = self::send( 'GET', $base . '/query?' . http_build_query( $params ), $headers + array( 'Accept' => 'application/json' ), null, $trusted );
        $data = json_decode( (string)$answer['body'], true );
        if ( !is_array( $data ) )
            throw new expImportSourceException( self::NAME . ': the answer is not JSON' );
        if ( isset( $data['error'] ) )
            throw new expImportSourceException( self::NAME . ': ' . (string)$data['error'] );
        $rows = array();
        $most = 0;
        foreach ( isset( $data['results'] ) ? (array)$data['results'] : array() as $result )
        {
            if ( isset( $result['error'] ) )
                throw new expImportSourceException( self::NAME . ': ' . (string)$result['error'] );
            foreach ( isset( $result['series'] ) ? (array)$result['series'] : array() as $series )
            {
                $columns = isset( $series['columns'] ) ? array_map( 'strval', (array)$series['columns'] ) : array();
                $values = isset( $series['values'] ) ? (array)$series['values'] : array();
                $most = max( $most, count( $values ) );
                foreach ( $values as $point )
                {
                    $row = array();
                    if ( isset( $series['name'] ) )
                        $row['_measurement'] = (string)$series['name'];
                    foreach ( isset( $series['tags'] ) ? (array)$series['tags'] : array() as $tag => $value )
                        $row[(string)$tag] = $value;
                    foreach ( $columns as $i => $column )
                        $row[$column === 'time' ? '_time' : $column] = isset( $point[$i] ) ? $point[$i] : null;
                    $rows[] = $row;
                }
            }
        }
        return array( $rows, $most, expImportPlatformHttp::header( (array)$answer['headers'], 'X-Influxdb-Version' ) );
    }

    /**
     * The editor's "Test connection": the first row of the query, or why it failed (the token is masked).
     *
     * @param array $options
     * @return array array( 'ok', 'columns', 'rows', 'driver', 'message' )
     */
    public static function test( array $options )
    {
        $used = array();
        $flux = !isset( $options['language'] ) || $options['language'] !== 'influxql';
        $driver = 'InfluxDB ' . ( $flux ? '(Flux)' : '(InfluxQL)' );
        try
        {
            list( $base, $headers, $trusted ) = self::connection( $options, $used );
            $query = self::queryOf( $options );
            $version = '';
            $rows = array();
            if ( $flux )
                list( $rows, $version ) = self::flux( $base, $headers, $trusted, $options, $query, 1 );
            else
                foreach ( self::influxQlRows( $base, $headers, $trusted, $options, $query, 1 ) as $row )
                    $rows[] = $row;
            $rows = array_slice( $rows, 0, 1 );
            if ( $version !== '' )
                $driver = 'InfluxDB ' . $version . ( $flux ? ' (Flux)' : ' (InfluxQL)' );
            $columns = $rows ? array_map( 'strval', array_keys( $rows[0] ) ) : array();
            return array( 'ok' => true, 'columns' => $columns, 'rows' => count( $rows ), 'driver' => $driver,
                          'message' => 'connected to ' . $driver . ( $rows ? '; the query gives these columns' : '; the query finds no row' ) );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => $driver,
                          'message' => expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }
}
