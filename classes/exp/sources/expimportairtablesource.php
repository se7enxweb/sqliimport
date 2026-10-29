<?php
/**
 * File containing the expImportAirtableSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The records of an Airtable table through the Airtable Web API (v0): one record is one row of the table (of a view,
 * of a formula's filter), its fields the table's fields by name.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() requests
 * https://api.airtable.com/v0/<base>/<table> page by page (pageSize up to 100, following "offset", at most
 * [PlatformSettings] MaxPages pages) and writes the records into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible: the source sends GET requests only. The personal access token is the name of a secret
 * (expImportSecrets), never stored in the import, and masked in every message; give it the scope data.records:read
 * and only the bases the import needs. Airtable allows five requests a second per base: the source keeps a fifth of a
 * second between two requests and waits 30 seconds (Airtable's penalty) or the Retry-After of a 429 before it tries
 * again.
 *
 * Values: text, numbers, checkboxes, dates (ISO 8601) and select options as Airtable gives them; attachments as the
 * list of their addresses (<field>, the mapper can download them) and of their file names (<field>_names); linked
 * records as the list of their record IDs; a collaborator as the name (<field>) and the e-mail address
 * (<field>_email), several as lists; a button as its address, a barcode as its text, an AI text as its value.
 * Collaborators and the content of the table may contain personal data.
 */
class expImportAirtableSource extends expImportJsonSource
{
    /** The address of the API. */
    const API = 'https://api.airtable.com/v0/';

    /** The least time between two requests, in seconds (five a second per base). */
    const INTERVAL = 0.2;

    /** The wait after a 429 without Retry-After, in seconds (Airtable's penalty). */
    const BACKOFF = 30.0;

    public static function identifier()
    {
        return 'airtable';
    }

    public static function label()
    {
        return 'Airtable table';
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

    public static function description()
    {
        return 'The records of an Airtable table (or of one of its views, or those a formula selects) through the Airtable '
             . 'Web API: one record is one row, its fields the table\'s fields by name; attachments come as addresses the '
             . 'mapping can download, linked records as IDs. Read-only; the personal access token is the name of a secret '
             . 'in the settings. The table may contain personal data (collaborators\' names and e-mail addresses).';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three records of a table "Events" (what a run writes): text, a date, a single select, attachments, linked records and a collaborator',
                   'file' => 'airtable-events.ndjson',
                   'options' => array( 'base_id' => 'appExampleBase01', 'table' => 'Events', 'token' => 'airtable_token' ) ),
            array( 'label' => 'The published events of the view "Website" of the base appExampleBase01, the token as the secret airtable_token',
                   'options' => array( 'base_id' => 'appExampleBase01', 'table' => 'Events', 'view' => 'Website',
                                       'formula' => '{Status} = "Published"', 'fields' => array( 'Name', 'Date', 'Status', 'Poster', 'Notes' ),
                                       'token' => 'airtable_token' ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        return array(
            'base_id' => array( 'type' => 'string', 'label' => 'Base ID', 'required' => true, 'max' => 64, 'pattern' => '/^app[A-Za-z0-9]+$/',
                                'help' => 'The ID of the base, app... (in the address of the base: airtable.com/appXXXXXXXXXXXXXX/...)' ),
            'table' => array( 'type' => 'string', 'label' => 'Table', 'required' => true, 'max' => 255,
                              'help' => 'The name of the table or its ID (tbl...); the ID stays right when the table is renamed' ),
            'view' => array( 'type' => 'string', 'label' => 'View', 'max' => 255,
                             'help' => 'Optional: the name or ID (viw...) of a view; its filters and its order apply' ),
            'formula' => array( 'type' => 'text', 'label' => 'Filter formula', 'max' => 4000,
                                'help' => 'Optional: filterByFormula, an Airtable formula a record must make true, e.g. {Status} = "Published"' ),
            'fields' => array( 'type' => 'list', 'label' => 'Fields',
                               'help' => 'Optional: the fields to read, one per line; empty: all. A listed field is in every record, also when it is empty' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 100,
                                  'help' => 'Records per request (Airtable answers at most 100)' ),
            'token' => array( 'type' => 'secret', 'label' => 'Personal access token (name of a secret)', 'required' => true,
                              'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding a personal access token with the scope data.records:read, never the token' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record; id is the record ID (rec...)' ),
        );
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

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
        $ref = isset( $options['token'] ) ? trim( (string)$options['token'] ) : '';
        $token = '';
        try
        {
            $token = expImportSecrets::required( $ref, 'Airtable token' );
            expImportApiHttp::writeSnapshot( self::apiRecords( $options, $token, (int)$limit ), $target, (int)$limit, 'Airtable' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /**
     * The editor's "Test connection": reads the first record and reports its fields, or why it failed (never the
     * token). Nothing is stored.
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
            $token = expImportSecrets::required( $ref, 'Airtable token' );
            $first = null;
            foreach ( self::apiRecords( array_merge( $options, array( 'page_size' => 1 ) ), $token, 1 ) as $record )
            {
                $first = $record;
                break;
            }
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api',
                              'message' => 'connected; the table (view, formula) gives no record' );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => 1, 'driver' => 'api',
                          'message' => 'connected, the first record has these fields' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api',
                          'message' => expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'Airtable Web API over HTTPS' ) );
    }

    /**
     * The address of a page.
     *
     * @param array $options
     * @param string $offset the offset of the previous answer; '' for the first page
     * @return string
     * @throws expImportSourceException
     */
    public static function pageUrl( array $options, $offset = '' )
    {
        $base = isset( $options['base_id'] ) ? trim( (string)$options['base_id'] ) : '';
        $table = isset( $options['table'] ) ? trim( (string)$options['table'] ) : '';
        if ( !preg_match( '/^app[A-Za-z0-9]+$/', $base ) )
            throw new expImportSourceException( 'Airtable: the base ID (app...) is missing or not valid' );
        if ( $table === '' )
            throw new expImportSourceException( 'Airtable: the table is missing' );
        $size = max( 1, min( 100, isset( $options['page_size'] ) ? (int)$options['page_size'] : 100 ) );
        $query = array( 'pageSize=' . $size );
        $view = isset( $options['view'] ) ? trim( (string)$options['view'] ) : '';
        if ( $view !== '' )
            $query[] = 'view=' . rawurlencode( $view );
        $formula = isset( $options['formula'] ) ? trim( (string)$options['formula'] ) : '';
        if ( $formula !== '' )
            $query[] = 'filterByFormula=' . rawurlencode( $formula );
        foreach ( isset( $options['fields'] ) ? (array)$options['fields'] : array() as $field )
            if ( trim( (string)$field ) !== '' )
                $query[] = 'fields%5B%5D=' . rawurlencode( trim( (string)$field ) );
        if ( (string)$offset !== '' )
            $query[] = 'offset=' . rawurlencode( (string)$offset );
        return self::API . $base . '/' . rawurlencode( $table ) . '?' . implode( '&', $query );
    }

    /**
     * The flattened records, page by page.
     *
     * @param array $options
     * @param string $token
     * @param int $limit 0: all
     * @return Generator array
     * @throws expImportSourceException
     */
    protected static function apiRecords( array $options, $token, $limit )
    {
        $headers = array( 'Authorization' => 'Bearer ' . $token );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxPages', 200 ) );
        $fields = isset( $options['fields'] ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', (array)$options['fields'] ) ), 'strlen' ) ) : array();
        $offset = '';
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $answer = expImportApiHttp::json( 'Airtable', 'GET', self::pageUrl( $options, $offset ), $headers, null, self::INTERVAL, self::BACKOFF );
            if ( !isset( $answer['records'] ) || !is_array( $answer['records'] ) )
                throw new expImportSourceException( 'Airtable: the answer has no list of records' );
            foreach ( $answer['records'] as $record )
            {
                if ( !is_array( $record ) )
                    continue;
                yield self::flatten( $record, $fields );
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            $offset = isset( $answer['offset'] ) && is_string( $answer['offset'] ) ? $answer['offset'] : '';
            if ( $offset === '' )
                return;
        }
        throw new expImportSourceException( "Airtable: the table has more pages than [PlatformSettings] MaxPages ($maxPages) allows" );
    }

    /**
     * One record of the API as a record of the import: id, created_time and the fields by name.
     *
     * @param array $record id, createdTime, fields
     * @param string[] $fields the fields asked for (each gets a value, null when Airtable left it out)
     * @return array
     */
    public static function flatten( array $record, array $fields = array() )
    {
        $out = array( 'id' => isset( $record['id'] ) ? (string)$record['id'] : '',
                      'created_time' => isset( $record['createdTime'] ) ? (string)$record['createdTime'] : '' );
        $values = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
        // Airtable leaves empty fields out of an answer
        foreach ( $fields as $name )
            if ( !array_key_exists( $name, $values ) )
                $values[$name] = null;
        $flat = array();
        foreach ( $values as $name => $value )
            foreach ( self::value( (string)$name, $value ) as $key => $v )
                $flat[$key] = $v;
        return expImportApiHttp::merge( $out, $flat );
    }

    /**
     * A field's value as the fields of a record.
     *
     * @param string $name
     * @param mixed $value
     * @return array name => value (the field, and <name>_names or <name>_email beside it)
     */
    public static function value( $name, $value )
    {
        if ( !is_array( $value ) )
            return array( $name => $value );
        $list = array_values( $value ) === $value;
        $items = $list ? $value : array( $value );
        // attachments: a list of objects with url and filename
        if ( $list && $items && self::all( $items, function ( $i ) { return is_array( $i ) && isset( $i['url'] ) && ( isset( $i['filename'] ) || isset( $i['type'] ) ); } ) )
            return array( $name => array_map( function ( $i ) { return (string)$i['url']; }, $items ),
                          $name . '_names' => array_map( function ( $i ) { return isset( $i['filename'] ) ? (string)$i['filename'] : ''; }, $items ) );
        // collaborators: objects with id (usr...), email and name
        if ( $items && self::all( $items, function ( $i ) { return is_array( $i ) && ( isset( $i['email'] ) || ( isset( $i['id'] ) && strpos( (string)$i['id'], 'usr' ) === 0 ) ); } ) )
        {
            $names = array_map( function ( $i ) { return isset( $i['name'] ) ? (string)$i['name'] : ''; }, $items );
            $mails = array_map( function ( $i ) { return isset( $i['email'] ) ? (string)$i['email'] : ''; }, $items );
            return $list ? array( $name => $names, $name . '_email' => $mails ) : array( $name => $names[0], $name . '_email' => $mails[0] );
        }
        if ( !$list )
        {
            // a button, a barcode, an AI text
            if ( isset( $value['url'] ) && isset( $value['label'] ) )
                return array( $name => (string)$value['url'] );
            if ( isset( $value['text'] ) && is_scalar( $value['text'] ) )
                return array( $name => (string)$value['text'] );
            if ( isset( $value['state'] ) && array_key_exists( 'value', $value ) )
                return array( $name => $value['value'] );
            if ( isset( $value['specialValue'] ) )
                return array( $name => null );
            return array( $name => $value );
        }
        // linked records (rec...), lookups and multiple selects: a list as it is
        return array( $name => $value );
    }

    /** @return bool whether every item passes */
    protected static function all( array $items, callable $test )
    {
        foreach ( $items as $item )
            if ( !$test( $item ) )
                return false;
        return true;
    }
}
