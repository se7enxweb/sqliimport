<?php
/**
 * File containing the expImportBaserowSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rows of a Baserow table through its REST API (baserow.io or a self-hosted Baserow): one record is one row, its
 * fields the table's fields by name (user_field_names=true).
 *
 * The source reads its data itself (origin "remote"): once per run fetch() requests
 * <base>/api/database/rows/table/<table id>/ page by page (page and size, up to 200 rows, while the answer has a
 * "next") and writes the rows into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible: the source sends GET requests to the list of rows only. The database token is the name
 * of a secret (expImportSecrets), never stored in the import and masked in every message; give it the permission
 * "read" only, on the tables the import needs. A self-hosted Baserow in a private network is refused unless
 * [BaserowSourceSettings] AllowedHosts[] lists it.
 *
 * Values (value()): link rows as their values joined (<field>) and their row IDs (<field>_ids); files as the list of
 * their addresses (<field>, the mapping can download them) and of their names (<field>_names); single selects as the
 * option's value, multiple selects and collaborators as the names joined; a user (created by, last modified by) as the
 * name; dates in ISO 8601 as Baserow gives them; booleans as true and false; numbers and decimals as Baserow gives them
 * (decimals as text, so no digit is lost). The table may contain personal data (collaborators, e-mail fields).
 */
class expImportBaserowSource extends expImportJsonSource
{
    const LABEL = 'Baserow';

    /** The address of baserow.io's API. */
    const API = 'https://api.baserow.io';

    /** The least time between two requests, in seconds. */
    const INTERVAL = 0.1;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 10.0;

    public static function identifier()
    {
        return 'baserow';
    }

    public static function label()
    {
        return 'Baserow table';
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
        return 'The rows of a Baserow table (baserow.io or self-hosted, also of one of its views) through the REST API: one '
             . 'record is one row with the fields by name; link rows as values and IDs, files as addresses the mapping can '
             . 'download, selects as their values. Read-only; the database token is the name of a secret. The table may '
             . 'contain personal data.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three rows of a table "Events" (what a run writes): a date, a single and a multiple select, a file and link rows',
                   'file' => 'baserow-events.ndjson',
                   'options' => array( 'table_id' => 4711, 'token' => 'baserow_token' ) ),
            array( 'label' => 'The published events of the view 815 of the table 4711 on baserow.io, newest first, the token as the secret baserow_token',
                   'options' => array( 'table_id' => 4711, 'view_id' => 815, 'filters' => 'Status__single_select_equal=3',
                                       'order_by' => '-Date', 'token' => 'baserow_token' ) ),
            array( 'label' => 'A self-hosted Baserow (listed in [BaserowSourceSettings] AllowedHosts[]): the rows whose name contains "tea"',
                   'options' => array( 'base_url' => 'https://baserow.example.invalid', 'table_id' => 12, 'search' => 'tea', 'token' => 'baserow_token' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Server address', 'default' => self::API,
                                 'help' => 'https://api.baserow.io, or the address of a self-hosted Baserow (a private network only with [BaserowSourceSettings] AllowedHosts[])' ),
            'table_id' => array( 'type' => 'int', 'label' => 'Table ID', 'required' => true, 'min' => 1,
                                 'help' => 'The ID of the table (the number in the address of the table, and in the API docs of the database)' ),
            'view_id' => array( 'type' => 'int', 'label' => 'View ID', 'default' => 0, 'min' => 0,
                                'help' => 'Optional: a view whose filters and order apply; 0: none' ),
            'filters' => array( 'type' => 'list', 'label' => 'Filters',
                                'help' => 'One per line as <field>__<type>=<value>, e.g. Status__single_select_equal=3 or Price__higher_than=10 (sent as filter__<field>__<type>)' ),
            'filter_type' => array( 'type' => 'enum', 'label' => 'Filters combined with', 'default' => 'AND',
                                    'values' => array( 'AND' => 'AND (every filter)', 'OR' => 'OR (any filter)' ) ),
            'search' => array( 'type' => 'string', 'label' => 'Search', 'max' => 255, 'help' => 'Optional: only rows that contain this text' ),
            'order_by' => array( 'type' => 'string', 'label' => 'Order', 'max' => 1000, 'pattern' => '/^[^&#?=]*$/',
                                 'help' => 'Fields by name, comma separated, - in front for descending: -Date,Name' ),
            'include' => array( 'type' => 'list', 'label' => 'Fields', 'help' => 'Optional: the fields to read, one per line; empty: all' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 200,
                                  'help' => 'Rows per request (Baserow answers at most 200)' ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10,
                                  'help' => 'Between the values of link rows, multiple selects and collaborators' ),
            'token' => array( 'type' => 'secret', 'label' => 'Database token (name of a secret)', 'required' => true,
                              'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding a database token with the permission read, never the token' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a row; id is the row ID' ),
        );
    }

    /** The snapshot is always one row per line. */
    protected function format()
    {
        return 'ndjson';
    }

    /**
     * The request of the options, checked.
     *
     * @param array $options
     * @return array url (without page), trusted, page, separator
     * @throws expImportSourceException
     */
    public static function plan( array $options )
    {
        $url = isset( $options['base_url'] ) && trim( (string)$options['base_url'] ) !== '' ? (string)$options['base_url'] : self::API;
        list( $base, $trusted ) = expImportDocumentHttp::base( $url, 'BaserowSourceSettings', self::LABEL );
        $table = isset( $options['table_id'] ) ? (int)$options['table_id'] : 0;
        if ( $table < 1 )
            throw new expImportSourceException( 'Baserow: the table ID is missing' );
        $size = max( 1, min( 200, isset( $options['page_size'] ) ? (int)$options['page_size'] : 100 ) );
        $query = array( 'user_field_names=true', 'size=' . $size );
        $view = isset( $options['view_id'] ) ? (int)$options['view_id'] : 0;
        if ( $view > 0 )
            $query[] = 'view_id=' . $view;
        $filters = isset( $options['filters'] ) ? $options['filters'] : array();
        if ( is_string( $filters ) )
            $filters = preg_split( '/\r\n|\r|\n/', $filters );
        $any = false;
        foreach ( (array)$filters as $line )
        {
            $line = trim( (string)$line );
            if ( $line === '' )
                continue;
            $filter = self::checkFilter( $line );
            if ( is_string( $filter ) )
                throw new expImportSourceException( 'Baserow: ' . $filter );
            $query[] = 'filter__' . rawurlencode( $filter[0] ) . '__' . $filter[1] . '=' . rawurlencode( $filter[2] );
            $any = true;
        }
        if ( $any )
            $query[] = 'filter_type=' . ( isset( $options['filter_type'] ) && $options['filter_type'] === 'OR' ? 'OR' : 'AND' );
        $search = isset( $options['search'] ) ? trim( (string)$options['search'] ) : '';
        if ( $search !== '' )
            $query[] = 'search=' . rawurlencode( $search );
        $order = isset( $options['order_by'] ) ? trim( (string)$options['order_by'] ) : '';
        if ( $order !== '' )
            $query[] = 'order_by=' . rawurlencode( $order );
        $include = isset( $options['include'] ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', (array)$options['include'] ) ), 'strlen' ) ) : array();
        if ( $include )
            $query[] = 'include=' . rawurlencode( implode( ',', $include ) );
        return array( 'url' => $base . '/api/database/rows/table/' . $table . '/?' . implode( '&', $query ), 'trusted' => $trusted, 'page' => $size,
                      'separator' => isset( $options['separator'] ) ? (string)$options['separator'] : ', ' );
    }

    /**
     * A filter line: <field>__<type>=<value>, the field a name or field_<id>, the type one of Baserow's filter types
     * (small letters and _). The last "__" before the "=" separates the field from the type.
     *
     * @param string $line
     * @return array|string the list ( field, type, value ), or the reason it is refused
     */
    public static function checkFilter( $line )
    {
        $at = strpos( (string)$line, '=' );
        if ( $at === false )
            return "a filter is <field>__<type>=<value>: $line";
        $left = substr( (string)$line, 0, $at );
        $cut = strrpos( $left, '__' );
        if ( $cut === false || $cut === 0 )
            return "a filter is <field>__<type>=<value>: $line";
        $field = trim( substr( $left, 0, $cut ) );
        $type = trim( substr( $left, $cut + 2 ) );
        if ( !preg_match( '/^[a-z][a-z0-9_]*$/', $type ) )
            return "the filter type is one of Baserow's (equal, contains, higher_than, single_select_equal ...): $type";
        if ( $field === '' || strpbrk( $field, "&#?=\r\n" ) !== false )
            return "the field of a filter is its name or field_<id>: $field";
        return array( $field, $type, (string)substr( (string)$line, $at + 1 ) );
    }

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
        $ref = isset( $options['token'] ) ? trim( (string)$options['token'] ) : '';
        $token = '';
        try
        {
            $plan = self::plan( $options );
            $token = expImportSecrets::required( $ref, 'Baserow token' );
            expImportApiHttp::writeSnapshot( self::rows( $plan, $token, (int)$limit ), $target, (int)$limit, self::LABEL );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /**
     * The rows, page by page.
     *
     * @param array $plan
     * @param string $token
     * @param int $limit 0: all
     * @param int|null $total set to the count of the answer
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function rows( array $plan, $token, $limit, &$total = null )
    {
        $headers = array( 'Authorization' => 'Token ' . $token );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'BaserowSourceSettings', 'MaxPages', 1000 ) );
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $answer = expImportApiHttp::json( self::LABEL, 'GET', $plan['url'] . '&page=' . $page, $headers, null, self::INTERVAL, self::BACKOFF, $plan['trusted'] );
            if ( !isset( $answer['results'] ) || !is_array( $answer['results'] ) )
                throw new expImportSourceException( 'Baserow: the answer has no list of rows' );
            if ( isset( $answer['count'] ) && is_numeric( $answer['count'] ) )
                $total = (int)$answer['count'];
            foreach ( $answer['results'] as $row )
            {
                if ( !is_array( $row ) )
                    continue;
                yield self::flatten( $row, $plan['separator'] );
                if ( ++$n >= $limit && $limit > 0 )
                    return;
            }
            if ( empty( $answer['next'] ) || !$answer['results'] )
                return;
        }
        throw new expImportSourceException( "Baserow: the table has more pages than [BaserowSourceSettings] MaxPages ($maxPages) allows" );
    }

    /**
     * One row of the API as a record: id, order and every field by name (value()).
     *
     * @param array $row
     * @param string $separator
     * @return array
     */
    public static function flatten( array $row, $separator = ', ' )
    {
        $out = array();
        foreach ( $row as $name => $value )
            foreach ( self::value( (string)$name, $value, $separator ) as $key => $v )
                $out[$key] = $v;
        return $out;
    }

    /**
     * A field's value as the fields of a record.
     *
     * @param string $name
     * @param mixed $value
     * @param string $separator
     * @return array name => value
     */
    public static function value( $name, $value, $separator = ', ' )
    {
        if ( !is_array( $value ) )
            return array( $name => $value );
        $list = array_values( $value ) === $value;
        if ( !$list )
        {
            // a single select ({id, value, color}), a user ({id, name}), a formula's or a lookup's single value
            if ( array_key_exists( 'value', $value ) && !is_array( $value['value'] ) )
                return array( $name => $value['value'] );
            if ( isset( $value['name'] ) && isset( $value['id'] ) && count( $value ) <= 3 )
                return array( $name => (string)$value['name'] );
            if ( isset( $value['url'] ) )
                return array( $name => (string)$value['url'] );
            return array( $name => (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        }
        if ( !$value )
            return array( $name => array() );
        $items = array_filter( $value, 'is_array' );
        if ( count( $items ) !== count( $value ) )
            return array( $name => implode( $separator, array_map( function ( $v ) { return is_bool( $v ) ? ( $v ? 'true' : 'false' ) : (string)$v; }, $value ) ) );
        // files: url, name, visible_name, size, mime_type, thumbnails
        if ( self::all( $value, function ( $i ) { return isset( $i['url'] ) && ( isset( $i['visible_name'] ) || isset( $i['mime_type'] ) ); } ) )
            return array( $name => array_map( function ( $i ) { return (string)$i['url']; }, $value ),
                          $name . '_names' => array_map( function ( $i ) { return isset( $i['visible_name'] ) ? (string)$i['visible_name'] : (string)( isset( $i['name'] ) ? $i['name'] : '' ); }, $value ) );
        // multiple selects: id, value, color
        if ( self::all( $value, function ( $i ) { return array_key_exists( 'value', $i ) && isset( $i['color'] ); } ) )
            return array( $name => implode( $separator, array_map( function ( $i ) { return (string)$i['value']; }, $value ) ) );
        // link rows, lookups: id, value (the primary field of the linked row)
        if ( self::all( $value, function ( $i ) { return array_key_exists( 'value', $i ); } ) )
            return array( $name => implode( $separator, array_map( function ( $i ) { return is_array( $i['value'] ) ? (string)json_encode( $i['value'] ) : (string)$i['value']; }, $value ) ),
                          $name . '_ids' => implode( ',', array_map( function ( $i ) { return isset( $i['id'] ) ? (string)$i['id'] : ''; }, $value ) ) );
        // collaborators: id, name
        if ( self::all( $value, function ( $i ) { return isset( $i['name'] ); } ) )
            return array( $name => implode( $separator, array_map( function ( $i ) { return (string)$i['name']; }, $value ) ) );
        return array( $name => (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    /** @return bool whether every item passes */
    protected static function all( array $items, callable $test )
    {
        foreach ( $items as $item )
            if ( !$test( $item ) )
                return false;
        return true;
    }

    /**
     * The editor's "Test connection": reads the first row and reports its fields and the count of rows, or why it
     * failed (never the token). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $ref = isset( $options['token'] ) ? trim( (string)$options['token'] ) : '';
        $token = '';
        try
        {
            $plan = self::plan( array_merge( $options, array( 'page_size' => 1 ) ) );
            $token = expImportSecrets::required( $ref, 'Baserow token' );
            $total = null;
            $first = null;
            foreach ( self::rows( $plan, $token, 1, $total ) as $row )
            {
                $first = $row;
                break;
            }
            $counted = $total !== null ? " ($total rows)" : '';
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => 'connected; the table (view, filters) gives no row' );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => $total !== null ? $total : 1, 'driver' => 'api',
                          'message' => 'connected, the first row has these fields' . $counted );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'Baserow REST API over HTTPS' ) );
    }
}
