<?php
/**
 * File containing the expImportHubSpotSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The objects of a HubSpot CRM object type through the CRM API v3: contacts, companies, deals, tickets, products,
 * line items or a custom object (by its type ID, 2-1234567). One record is one object: id, created_at, updated_at,
 * archived, the asked properties by name and, on request, the IDs of associated objects.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() either lists the objects
 * (GET /crm/v3/objects/<type>, paged with "after") or searches them (POST /crm/v3/objects/<type>/search with
 * filterGroups, a sort and "after"; the search is a read, it changes nothing), and writes them into the run's
 * snapshot, one JSON object per line.
 *
 * Only reading is possible: the only requests are the list (GET) and the search (POST to .../search); the search
 * body is built by the source from a checked filterGroups list (checkFilterGroups(): only filters with propertyName,
 * operator, value, highValue and values). The access token of a private app is the name of a secret
 * (expImportSecrets), never stored in the import and masked in every message; give the app only the read scopes of
 * the objects the import needs (crm.objects.contacts.read ...).
 *
 * Rate limits: private apps may make 100 requests in 10 seconds (the source keeps 0.1 s between two requests), the
 * search 5 a second (0.2 s); after a 429 it waits the Retry-After. A search reads at most 10,000 objects.
 *
 * Values (value()): HubSpot gives every property as text: numbers stay text; dates and date-times stay ISO 8601, and a
 * date property given in milliseconds since 1970 (1729591200000) becomes ISO 8601 in UTC; the values of a multiple
 * checkbox stay joined with ";" as HubSpot gives them; an empty property is null. Contacts hold personal data (names,
 * e-mail addresses, phone numbers): map only what the site needs.
 */
class expImportHubSpotSource extends expImportJsonSource
{
    const LABEL = 'HubSpot';

    /** The address of the API. */
    const API = 'https://api.hubapi.com';

    /** The least time between two list requests, in seconds (100 in 10 seconds). */
    const INTERVAL = 0.1;

    /** The least time between two searches, in seconds (5 a second). */
    const SEARCH_INTERVAL = 0.2;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 10.0;

    /** The operators of a search filter. */
    const OPERATORS = array( 'EQ', 'NEQ', 'LT', 'LTE', 'GT', 'GTE', 'BETWEEN', 'IN', 'NOT_IN', 'HAS_PROPERTY', 'NOT_HAS_PROPERTY',
                             'CONTAINS_TOKEN', 'NOT_CONTAINS_TOKEN' );

    /** The properties HubSpot gives as dates, also in milliseconds. */
    const DATE_PROPERTIES = array( 'createdate', 'lastmodifieddate', 'hs_lastmodifieddate', 'closedate', 'hs_createdate', 'notes_last_updated',
                                   'notes_last_contacted', 'notes_next_activity_date', 'hs_last_sales_activity_timestamp', 'lastcontacted' );

    public static function identifier()
    {
        return 'hubspot';
    }

    public static function label()
    {
        return 'HubSpot CRM objects';
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
        return 'The objects of a HubSpot CRM type (contacts, companies, deals, tickets, products, line items or a custom '
             . 'object) through the CRM API v3: listed, or found by a search with filter groups; the asked properties by '
             . 'name and the IDs of associated objects. Read-only; the private app token is the name of a secret. Contacts '
             . 'hold personal data.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three companies with their associated contacts (what a run writes)',
                   'file' => 'hubspot-companies.ndjson',
                   'options' => array( 'object_type' => 'companies', 'token' => 'hubspot_token' ) ),
            array( 'label' => 'The companies with their domain, industry and city, and the IDs of their contacts, the token as the secret hubspot_token',
                   'options' => array( 'object_type' => 'companies', 'properties' => "name\ndomain\nindustry\ncity\ncreatedate",
                                       'associations' => 'contacts', 'token' => 'hubspot_token' ) ),
            array( 'label' => 'A search: the deals won since 2026, the biggest first',
                   'options' => array( 'object_type' => 'deals', 'mode' => 'search', 'properties' => "dealname\namount\nclosedate\ndealstage",
                                       'filter_groups' => '[{"filters": [{"propertyName": "dealstage", "operator": "EQ", "value": "closedwon"}, {"propertyName": "closedate", "operator": "GTE", "value": "2026-01-01"}]}]',
                                       'sort' => 'amount DESCENDING', 'token' => 'hubspot_token' ) ),
        );
    }

    public static function schema()
    {
        return array(
            'object_type' => array( 'type' => 'string', 'label' => 'Object type', 'required' => true, 'max' => 100,
                                    'pattern' => '/^([a-z][a-z_]*|\d-\d+|p_[a-z0-9_]+)$/',
                                    'help' => 'contacts, companies, deals, tickets, products, line_items, or a custom object\'s type ID (2-1234567) or name (p_events)' ),
            'mode' => array( 'type' => 'enum', 'label' => 'Read', 'default' => 'list',
                             'values' => array( 'list' => 'Every object (list)', 'search' => 'A search (filter groups)' ) ),
            'properties' => array( 'type' => 'list', 'label' => 'Properties',
                                   'help' => 'The properties to read, one per line (internal names: firstname, domain, amount); empty: HubSpot\'s default properties' ),
            'associations' => array( 'type' => 'list', 'label' => 'Associations',
                                     'help' => 'List only: the associated object types whose IDs to read, one per line (contacts, companies, deals)' ),
            'filter_groups' => array( 'type' => 'text', 'label' => 'Filter groups (JSON)',
                                      'help' => 'Search: e.g. [{"filters": [{"propertyName": "lifecyclestage", "operator": "EQ", "value": "customer"}]}]; '
                                              . 'groups are combined with OR, the filters of a group with AND (at most 5 groups and 18 filters)' ),
            'search' => array( 'type' => 'string', 'label' => 'Search text', 'max' => 255, 'help' => 'Search: the text HubSpot looks for in the default text properties' ),
            'sort' => array( 'type' => 'string', 'label' => 'Sort', 'max' => 200, 'pattern' => '/^([A-Za-z0-9_]+( (ASCENDING|DESCENDING|ASC|DESC))?)?$/i',
                             'help' => 'Search: one property and the direction, e.g. createdate DESCENDING' ),
            'archived' => array( 'type' => 'bool', 'label' => 'Archived objects', 'default' => false, 'help' => 'List only: read the archived objects instead' ),
            'date_properties' => array( 'type' => 'list', 'label' => 'Date properties',
                                        'help' => 'Properties whose values in milliseconds since 1970 become ISO 8601, besides createdate, closedate and the other dates HubSpot keeps' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 200,
                                  'help' => 'Objects per request (a list at most 100, a search 200)' ),
            'token' => array( 'type' => 'secret', 'label' => 'Private app access token (name of a secret)', 'required' => true,
                              'help' => 'The name of a secret of [SecretSettings] Secrets[] or env:VARIABLE holding the access token of a private app with read scopes only' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names an object; id is its record ID' ),
        );
    }

    /** The snapshot is always one object per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- the checks --------------------------------------------------------------------------------------------------

    /**
     * The filter groups of a search, checked: a list of at most 5 groups (or an object {"filterGroups": [...]}),
     * each {"filters": [...]}, each filter only propertyName (required), operator (of OPERATORS), value, highValue
     * (scalars) and values (a list of scalars); at most 18 filters.
     *
     * @param mixed $value JSON text or the decoded value
     * @return array|string the groups, or the reason they are refused
     */
    public static function checkFilterGroups( $value )
    {
        if ( is_string( $value ) )
        {
            if ( trim( $value ) === '' )
                return array();
            $value = json_decode( $value, true, 16 );
            if ( !is_array( $value ) )
                return 'the filter groups are not valid JSON';
        }
        if ( !is_array( $value ) )
            return 'the filter groups are a JSON list';
        if ( array_values( $value ) !== $value )
        {
            if ( array_keys( $value ) !== array( 'filterGroups' ) || !is_array( $value['filterGroups'] ) )
                return 'the filter groups are a list, or an object with filterGroups only';
            $value = $value['filterGroups'];
        }
        if ( count( $value ) > 5 )
            return 'a search has at most 5 filter groups';
        $count = 0;
        $groups = array();
        foreach ( $value as $group )
        {
            if ( !is_array( $group ) || array_diff( array_keys( $group ), array( 'filters' ) ) || !isset( $group['filters'] ) || !is_array( $group['filters'] )
                 || array_values( $group['filters'] ) !== $group['filters'] || !$group['filters'] )
                return 'a filter group is {"filters": [...]} with at least one filter';
            $filters = array();
            foreach ( $group['filters'] as $filter )
            {
                if ( !is_array( $filter ) || array_diff( array_keys( $filter ), array( 'propertyName', 'operator', 'value', 'highValue', 'values' ) ) )
                    return 'a filter has only propertyName, operator, value, highValue and values';
                if ( !isset( $filter['propertyName'] ) || !is_string( $filter['propertyName'] ) || !preg_match( '/^[A-Za-z0-9_.]+$/', $filter['propertyName'] ) )
                    return 'a filter needs the internal name of a property (propertyName)';
                $operator = isset( $filter['operator'] ) && is_string( $filter['operator'] ) ? strtoupper( $filter['operator'] ) : '';
                if ( !in_array( $operator, self::OPERATORS, true ) )
                    return 'the operator of a filter is one of ' . implode( ', ', self::OPERATORS );
                $filter['operator'] = $operator;
                foreach ( array( 'value', 'highValue' ) as $name )
                    if ( isset( $filter[$name] ) && !is_scalar( $filter[$name] ) )
                        return "the $name of a filter is a text, a number or true/false";
                if ( isset( $filter['values'] ) && ( !is_array( $filter['values'] ) || array_values( $filter['values'] ) !== $filter['values'] || array_filter( $filter['values'], 'is_array' ) ) )
                    return 'the values of a filter are a list of texts or numbers';
                if ( ++$count > 18 )
                    return 'a search has at most 18 filters';
                $filters[] = $filter;
            }
            $groups[] = array( 'filters' => $filters );
        }
        return $groups;
    }

    /** @return string[] the names of a list option */
    protected static function names( array $options, $name )
    {
        $value = isset( $options[$name] ) ? $options[$name] : array();
        if ( is_string( $value ) )
            $value = preg_split( '/[\r\n,]+/', $value );
        return array_values( array_filter( array_map( 'trim', array_map( 'strval', (array)$value ) ), function ( $v ) { return preg_match( '/^[A-Za-z0-9_.-]+$/', $v ) === 1; } ) );
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * The object type of the options, checked.
     *
     * @param array $options
     * @return string
     * @throws expImportSourceException
     */
    protected static function objectType( array $options )
    {
        $type = isset( $options['object_type'] ) ? trim( (string)$options['object_type'] ) : '';
        if ( !preg_match( '/^([a-z][a-z_]*|\d-\d+|p_[a-z0-9_]+)$/', $type ) )
            throw new expImportSourceException( 'HubSpot: the object type is missing or not valid (contacts, companies, 2-1234567 ...)' );
        return $type;
    }

    /**
     * Reads the objects into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most objects (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $ref = isset( $options['token'] ) ? trim( (string)$options['token'] ) : '';
        $token = '';
        try
        {
            $token = expImportSecrets::required( $ref, 'HubSpot token' );
            expImportApiHttp::writeSnapshot( self::objects( $options, $token, (int)$limit ), $target, (int)$limit, self::LABEL );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /**
     * The flattened objects, page by page.
     *
     * @param array $options
     * @param string $token
     * @param int $limit 0: all
     * @param int|null $total set to the total of a search
     * @return Generator array
     * @throws expImportSourceException
     */
    public static function objects( array $options, $token, $limit, &$total = null )
    {
        $type = self::objectType( $options );
        $search = isset( $options['mode'] ) && $options['mode'] === 'search';
        $properties = self::names( $options, 'properties' );
        $dates = array_merge( self::DATE_PROPERTIES, self::names( $options, 'date_properties' ) );
        $size = max( 1, min( $search ? 200 : 100, isset( $options['page_size'] ) ? (int)$options['page_size'] : 100 ) );
        $headers = array( 'Authorization' => 'Bearer ' . $token );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'HubSpotSourceSettings', 'MaxPages', 1000 ) );
        $body = array();
        if ( $search )
        {
            $groups = self::checkFilterGroups( isset( $options['filter_groups'] ) ? $options['filter_groups'] : '' );
            if ( is_string( $groups ) )
                throw new expImportSourceException( 'HubSpot: ' . $groups );
            $body = array( 'filterGroups' => $groups, 'limit' => $size );
            if ( $properties )
                $body['properties'] = $properties;
            $text = isset( $options['search'] ) ? trim( (string)$options['search'] ) : '';
            if ( $text !== '' )
                $body['query'] = $text;
            $sort = isset( $options['sort'] ) ? trim( (string)$options['sort'] ) : '';
            if ( $sort !== '' )
            {
                $parts = preg_split( '/\s+/', $sort );
                $body['sorts'] = array( array( 'propertyName' => $parts[0],
                                               'direction' => isset( $parts[1] ) && stripos( $parts[1], 'DESC' ) === 0 ? 'DESCENDING' : 'ASCENDING' ) );
            }
            $headers['Content-Type'] = 'application/json';
        }
        $after = '';
        $n = 0;
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            if ( $search )
            {
                if ( $after !== '' )
                    $body['after'] = $after;
                $answer = expImportApiHttp::json( self::LABEL, 'POST', self::API . '/crm/v3/objects/' . rawurlencode( $type ) . '/search', $headers,
                                                  (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), self::SEARCH_INTERVAL, self::BACKOFF );
                if ( isset( $answer['total'] ) && is_numeric( $answer['total'] ) )
                    $total = (int)$answer['total'];
            }
            else
                $answer = expImportApiHttp::json( self::LABEL, 'GET', self::listUrl( $options, $size, $after ), $headers, null, self::INTERVAL, self::BACKOFF );
            if ( !isset( $answer['results'] ) || !is_array( $answer['results'] ) )
                throw new expImportSourceException( 'HubSpot: the answer has no list of results' );
            foreach ( $answer['results'] as $object )
            {
                if ( !is_array( $object ) )
                    continue;
                yield self::flatten( $object, $properties, $dates );
                if ( ++$n >= $limit && $limit > 0 )
                    return;
            }
            $after = isset( $answer['paging']['next']['after'] ) && is_scalar( $answer['paging']['next']['after'] ) ? (string)$answer['paging']['next']['after'] : '';
            if ( $after === '' )
                return;
            if ( $search && (int)$after >= 10000 )
                throw new expImportSourceException( 'HubSpot: a search reads at most 10,000 objects; narrow the filter groups or read the list' );
        }
        throw new expImportSourceException( "HubSpot: the objects need more pages than [HubSpotSourceSettings] MaxPages ($maxPages) allows" );
    }

    /**
     * The address of a page of the list.
     *
     * @param array $options
     * @param int $size
     * @param string $after
     * @return string
     * @throws expImportSourceException
     */
    public static function listUrl( array $options, $size, $after = '' )
    {
        $query = array( 'limit=' . (int)$size, 'archived=' . ( !empty( $options['archived'] ) ? 'true' : 'false' ) );
        $properties = self::names( $options, 'properties' );
        if ( $properties )
            $query[] = 'properties=' . rawurlencode( implode( ',', $properties ) );
        $associations = self::names( $options, 'associations' );
        if ( $associations )
            $query[] = 'associations=' . rawurlencode( implode( ',', $associations ) );
        if ( (string)$after !== '' )
            $query[] = 'after=' . rawurlencode( (string)$after );
        return self::API . '/crm/v3/objects/' . rawurlencode( self::objectType( $options ) ) . '?' . implode( '&', $query );
    }

    /**
     * One object as a record: id, created_at, updated_at, archived, the properties by name (every asked one, null
     * when HubSpot left it out) and associations.<type> as the list of the associated IDs.
     *
     * @param array $object
     * @param string[] $properties the asked properties
     * @param string[] $dates the date properties
     * @return array
     */
    public static function flatten( array $object, array $properties = array(), array $dates = self::DATE_PROPERTIES )
    {
        $out = array( 'id' => isset( $object['id'] ) ? (string)$object['id'] : '',
                      'created_at' => isset( $object['createdAt'] ) ? self::value( 'createdAt', $object['createdAt'], array( 'createdAt' ) ) : null,
                      'updated_at' => isset( $object['updatedAt'] ) ? self::value( 'updatedAt', $object['updatedAt'], array( 'updatedAt' ) ) : null,
                      'archived' => !empty( $object['archived'] ) );
        $values = isset( $object['properties'] ) && is_array( $object['properties'] ) ? $object['properties'] : array();
        foreach ( $properties as $name )
            if ( !array_key_exists( $name, $values ) )
                $values[$name] = null;
        $fields = array();
        foreach ( $values as $name => $value )
            $fields[(string)$name] = self::value( (string)$name, $value, $dates );
        $out = expImportApiHttp::merge( $out, $fields );
        if ( isset( $object['associations'] ) && is_array( $object['associations'] ) )
            foreach ( $object['associations'] as $type => $list )
            {
                $ids = array();
                foreach ( isset( $list['results'] ) && is_array( $list['results'] ) ? $list['results'] : array() as $item )
                    if ( is_array( $item ) && isset( $item['id'] ) && !in_array( (string)$item['id'], $ids, true ) )
                        $ids[] = (string)$item['id'];
                $out['associations.' . $type] = $ids;
            }
        return $out;
    }

    /**
     * A property's value: text (numbers too), '' as null, a date given in milliseconds as ISO 8601 in UTC.
     *
     * @param string $name
     * @param mixed $value
     * @param string[] $dates
     * @return string|null
     */
    public static function value( $name, $value, array $dates = self::DATE_PROPERTIES )
    {
        if ( $value === null || $value === '' )
            return null;
        if ( is_bool( $value ) )
            return $value ? 'true' : 'false';
        if ( is_float( $value ) )
            return expImportSalesforceSource::number( $value );
        if ( is_array( $value ) )
            return (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $value = (string)$value;
        if ( preg_match( '/^\d{11,13}$/', $value ) && ( in_array( strtolower( $name ), array_map( 'strtolower', $dates ), true ) ) )
        {
            $ms = (int)$value;
            return gmdate( 'Y-m-d\TH:i:s', intdiv( $ms, 1000 ) ) . sprintf( '.%03dZ', $ms % 1000 );
        }
        return $value;
    }

    /**
     * The editor's "Test connection": reads the first object and reports its fields (and the total of a search), or
     * why it failed (never the token). Nothing is stored.
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
            $token = expImportSecrets::required( $ref, 'HubSpot token' );
            $total = null;
            $first = null;
            foreach ( self::objects( array_merge( $options, array( 'page_size' => 1 ) ), $token, 1, $total ) as $object )
            {
                $first = $object;
                break;
            }
            $counted = $total !== null ? " ($total objects)" : '';
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => 'connected; there is no object' . $counted );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => $total !== null ? $total : 1, 'driver' => 'api',
                          'message' => 'connected, the first object has these fields' . $counted );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api', 'message' => expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'HubSpot CRM API over HTTPS' ) );
    }
}
