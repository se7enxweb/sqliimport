<?php
/**
 * File containing the expImportDrupalSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Content of a Drupal site through its JSON:API (core module jsonapi, Drupal 8.7 and later). The source reads the
 * resources itself: once per run it requests <site>/jsonapi/<entity type>/<bundle> page by page, following the
 * links.next of every answer (on the same site only, at most [PlatformSettings] MaxPages pages), and writes the
 * resources as NDJSON into the run's directory. records() reads that snapshot, and also an answer saved to a file
 * (a JSON:API document with "data"), as the example of the catalogue is.
 *
 * A resource becomes a record with friendly fields: id (the UUID), type, internal_id (nid, tid ...), title, body (the
 * processed HTML; body_value, body_summary and body_format besides), intro (the summary, else the first paragraph),
 * created, changed, status, path (the alias), url, langcode and every other attribute by its name. A relationship
 * becomes <name> (the related resource with the attributes of the included one), <name>_id, and for files and media
 * <name>_url; image_url and image_alt name the first image, author the user, tags the names of field_tags.
 */
class expImportDrupalSource extends expImportJsonSource
{
    public static function identifier()
    {
        return 'drupal';
    }

    public static function label()
    {
        return 'Drupal (JSON:API)';
    }

    public static function extensions()
    {
        return array( 'json', 'ndjson', 'jsonl' );
    }

    public static function group()
    {
        return 'platform';
    }

    public static function description()
    {
        return 'Content of a Drupal site through its JSON:API (articles, pages, terms, any entity type and bundle). The '
             . 'source reads the site page by page and includes the related images, authors and tags, so a record has '
             . 'the title, body, summary, dates, path alias, image address and tag names as fields.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A JSON:API page of two articles with their images, authors and tags', 'file' => 'drupal-jsonapi.json',
                             'options' => array( 'base_url' => 'https://drupal.example.org' ) ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'drupal-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title' ),
                array( 'source' => 'intro', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'body', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'created', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
                array( 'source' => 'image_url', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'image' ) ), 'datatypes' => array( 'ezimage' ) ),
                array( 'source' => 'image_url', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'media_object' ) ), 'datatypes' => array( 'ezobjectrelation' ) ),
                array( 'source' => 'tags', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ), 'datatypes' => array( 'ezkeyword', 'eztags' ) ),
            ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Site address', 'required' => true,
                                 'help' => 'The address of the Drupal site, e.g. https://www.example.org (JSON:API at /jsonapi)' ),
            'resource' => array( 'type' => 'string', 'label' => 'Resource type', 'default' => 'node--article', 'pattern' => '/^[a-z0-9_]+--[a-z0-9_]+$/',
                                 'help' => 'The entity type and bundle: node--article, node--page, taxonomy_term--tags, media--image' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 50, 'min' => 1, 'max' => 50,
                                  'help' => 'Resources per request (JSON:API answers at most 50)' ),
            'include' => array( 'type' => 'string', 'label' => 'Include', 'default' => 'field_image,uid,field_tags', 'pattern' => '/^[A-Za-z0-9_.,]*$/',
                                'help' => 'The relationships whose resources come along, comma separated: field_image,uid,field_tags' ),
            'filter' => array( 'type' => 'string', 'label' => 'Filter', 'pattern' => '/^[^\s#]*$/',
                               'help' => 'JSON:API query parameters as they are, e.g. filter[status]=1&sort=-created' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'none',
                             'values' => array( 'none' => 'None (public content)', 'basic' => 'User and password (basic_auth)', 'bearer' => 'Token (OAuth bearer)' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'help' => 'With User and password: the Drupal user name' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (secret)', 'help' => 'With User and password: the name of the secret with the password' ),
            'token_secret' => array( 'type' => 'secret', 'label' => 'Token (secret)', 'help' => 'With Token: the name of the secret with the access token' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a record; id is the UUID of the resource.' ),
        );
    }

    // ---- reading a snapshot or a saved answer ------------------------------------------------------------------

    /** @return array|null the JSON:API document of the file, null when it is a snapshot (NDJSON) */
    protected function document()
    {
        $file = $this->file();
        $fp = fopen( $file, 'rb' );
        $head = ltrim( (string)fread( $fp, 256 ), "\xEF\xBB\xBF \t\r\n" );
        fclose( $fp );
        if ( $head === '' || $head[0] !== '{' || preg_match( '/\.(ndjson|jsonl)$/i', $file ) )
            return null;
        $data = json_decode( (string)file_get_contents( $file ), true );
        if ( !is_array( $data ) || !isset( $data['data'] ) || !is_array( $data['data'] ) )
            return null;
        return $data;
    }

    public function records( array $cursor = array() )
    {
        $document = $this->document();
        if ( $document === null )
        {
            // the snapshot of a run: one flattened resource per line
            $this->config['format'] = 'ndjson';
            foreach ( parent::records( $cursor ) as $record )
                yield $record;
            return;
        }
        $this->detected['format'] = 'jsonapi';
        $base = isset( $this->config['base_url'] ) ? (string)$this->config['base_url'] : '';
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        foreach ( self::flattenDocument( $document, $base ) as $index => $data )
        {
            if ( $index < $skip )
                continue;
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
        }
    }

    public function count()
    {
        $document = $this->document();
        if ( $document === null )
        {
            $this->config['format'] = 'ndjson';
            return parent::count();
        }
        return array_values( $document['data'] ) === $document['data'] ? count( $document['data'] ) : 1;
    }

    // ---- reading the site ----------------------------------------------------------------------------------------

    /**
     * Reads the site and writes its resources as NDJSON (the remote origin, once per run).
     *
     * @param array $options the validated options
     * @param string $target the snapshot file
     * @param int $limit at most so many records (0: all)
     */
    public static function fetch( array $options, $target, $limit )
    {
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::resources( $options, (int)$limit, $used ), $target, (int)$limit );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /** @return string the first address to request */
    public static function firstUrl( array $options )
    {
        $base = rtrim( (string)$options['base_url'], '/' );
        $resource = isset( $options['resource'] ) && $options['resource'] !== '' ? (string)$options['resource'] : 'node--article';
        if ( !preg_match( '/^([a-z0-9_]+)--([a-z0-9_]+)$/', $resource, $m ) )
            throw new expImportSourceException( "the resource type '$resource' is not of the form entity--bundle" );
        $query = 'page%5Blimit%5D=' . max( 1, min( 50, isset( $options['page_size'] ) ? (int)$options['page_size'] : 50 ) );
        $include = isset( $options['include'] ) ? trim( (string)$options['include'], ', ' ) : '';
        if ( $include !== '' )
            $query .= '&include=' . rawurlencode( $include );
        $filter = isset( $options['filter'] ) ? ltrim( trim( (string)$options['filter'] ), '?&' ) : '';
        if ( $filter !== '' )
            $query .= '&' . $filter;
        return $base . '/jsonapi/' . $m[1] . '/' . $m[2] . '?' . $query;
    }

    /**
     * The flattened resources of the site, page by page.
     *
     * @param array $options
     * @param int $limit
     * @param string[] $used the secrets used (for masking messages)
     * @return Generator array
     */
    public static function resources( array $options, $limit, array &$used )
    {
        $base = rtrim( (string)$options['base_url'], '/' );
        $headers = array( 'Accept' => 'application/vnd.api+json' ) + expImportPlatformHttp::authHeaders( $options, $used );
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxPages', 200 ) );
        $url = self::firstUrl( $options );
        $n = 0;
        for ( $page = 1; $url !== '' && $page <= $maxPages; $page++ )
        {
            $document = expImportPlatformHttp::json( 'GET', $url, $headers );
            if ( !isset( $document['data'] ) || !is_array( $document['data'] ) )
                throw new expImportSourceException( 'the answer of ' . expImportPlatformHttp::shown( $url ) . ' is not a JSON:API document'
                                                    . ( isset( $document['errors'][0]['detail'] ) ? ': ' . substr( (string)$document['errors'][0]['detail'], 0, 200 ) : '' ) );
            foreach ( self::flattenDocument( $document, $base ) as $record )
            {
                yield $record;
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            $next = isset( $document['links']['next'] ) ? $document['links']['next'] : '';
            $next = is_array( $next ) ? ( isset( $next['href'] ) ? (string)$next['href'] : '' ) : (string)$next;
            if ( $next !== '' && !expImportPlatformHttp::sameSite( $next, $base ) )
                throw new expImportSourceException( 'the next page is on another site (' . expImportPlatformHttp::shown( $next ) . '); it is not followed' );
            $url = $next;
        }
    }

    /**
     * The resources of a JSON:API document as records, with their relationships resolved from "included".
     *
     * @param array $document
     * @param string $base the site's address, for relative file addresses
     * @return array[]
     */
    public static function flattenDocument( array $document, $base = '' )
    {
        $included = array();
        foreach ( isset( $document['included'] ) && is_array( $document['included'] ) ? $document['included'] : array() as $resource )
            if ( isset( $resource['type'], $resource['id'] ) )
                $included[$resource['type'] . '/' . $resource['id']] = $resource;
        $data = $document['data'];
        if ( isset( $data['type'] ) )
            $data = array( $data );
        $out = array();
        foreach ( $data as $resource )
            if ( is_array( $resource ) && isset( $resource['type'] ) )
                $out[] = self::flatten( $resource, $included, $base );
        return $out;
    }

    /**
     * One resource as a record.
     *
     * @param array $resource
     * @param array $included type/id => resource
     * @param string $base
     * @return array
     */
    public static function flatten( array $resource, array $included = array(), $base = '' )
    {
        $attributes = isset( $resource['attributes'] ) && is_array( $resource['attributes'] ) ? $resource['attributes'] : array();
        $record = array( 'id' => isset( $resource['id'] ) ? (string)$resource['id'] : '', 'type' => (string)$resource['type'] );
        foreach ( $attributes as $name => $value )
        {
            if ( preg_match( '/^drupal_internal__/', $name ) )
            {
                $record['internal_id'] = isset( $record['internal_id'] ) ? $record['internal_id'] : $value;
                $record[substr( $name, 17 )] = $value;
            }
            elseif ( is_array( $value ) && $name === 'path' )
            {
                $record['path'] = isset( $value['alias'] ) ? (string)$value['alias'] : '';
                $record['url'] = $record['path'] !== '' && $base !== '' ? rtrim( $base, '/' ) . $record['path'] : '';
            }
            elseif ( is_array( $value ) && ( array_key_exists( 'processed', $value ) || ( array_key_exists( 'value', $value ) && array_key_exists( 'format', $value ) ) ) )
            {
                // a formatted text field (body): the processed HTML, and its parts
                $record[$name] = isset( $value['processed'] ) && $value['processed'] !== '' ? (string)$value['processed'] : (string)( isset( $value['value'] ) ? $value['value'] : '' );
                $record[$name . '_value'] = isset( $value['value'] ) ? (string)$value['value'] : '';
                $record[$name . '_summary'] = isset( $value['summary'] ) ? (string)$value['summary'] : '';
                $record[$name . '_format'] = isset( $value['format'] ) ? (string)$value['format'] : '';
            }
            else
                $record[$name] = $value;
        }
        if ( !isset( $record['title'] ) && isset( $attributes['name'] ) )
            $record['title'] = $attributes['name'];
        $body = isset( $record['body'] ) ? (string)$record['body'] : '';
        $summary = isset( $record['body_summary'] ) ? trim( (string)$record['body_summary'] ) : '';
        $record['intro'] = $summary !== '' ? $summary : self::firstParagraph( $body );

        foreach ( isset( $resource['relationships'] ) && is_array( $resource['relationships'] ) ? $resource['relationships'] : array() as $name => $relationship )
        {
            if ( !is_array( $relationship ) || !array_key_exists( 'data', $relationship ) || $relationship['data'] === null )
                continue;
            $data = $relationship['data'];
            $single = isset( $data['type'] );
            $items = array();
            foreach ( $single ? array( $data ) : (array)$data as $item )
                if ( is_array( $item ) && isset( $item['type'], $item['id'] ) )
                    $items[] = self::related( $item, $included, $base, 1 );
            if ( !$items && !$single )
            {
                $record[$name] = array();
                $record[$name . '_id'] = array();
                continue;
            }
            if ( !$items )
                continue;
            $record[$name] = $single ? $items[0] : $items;
            $record[$name . '_id'] = $single ? $items[0]['id'] : array_map( function ( $i ) { return $i['id']; }, $items );
            foreach ( $items as $item )
                if ( !empty( $item['url'] ) )
                {
                    $record[$name . '_url'] = $item['url'];
                    break;
                }
            $names = array_values( array_filter( array_map( function ( $i ) { return isset( $i['name'] ) && is_string( $i['name'] ) ? $i['name'] : ''; }, $items ), 'strlen' ) );
            if ( $names && !$single )
                $record[$name . '_names'] = $names;
        }

        // the friendly fields of the usual relationships
        $record['image_url'] = '';
        $record['image_alt'] = '';
        foreach ( array_merge( array( 'field_image', 'field_media_image', 'field_media', 'thumbnail' ), array_keys( isset( $resource['relationships'] ) ? (array)$resource['relationships'] : array() ) ) as $name )
        {
            if ( !empty( $record[$name . '_url'] ) )
            {
                $item = isset( $record[$name]['url'] ) ? $record[$name] : ( isset( $record[$name][0] ) ? $record[$name][0] : array() );
                $record['image_url'] = $record[$name . '_url'];
                $record['image_alt'] = isset( $item['alt'] ) ? (string)$item['alt'] : '';
                break;
            }
        }
        if ( isset( $record['uid']['name'] ) )
            $record['author'] = $record['uid']['name'];
        $record['tags'] = isset( $record['field_tags_names'] ) ? $record['field_tags_names'] : array();
        return $record;
    }

    /**
     * A related resource: its type and id, the meta of the relationship (alt, title, width, height) and the
     * attributes of the included resource; a file's address made absolute, a media's file followed.
     *
     * @param array $item
     * @param array $included
     * @param string $base
     * @param int $depth
     * @return array
     */
    protected static function related( array $item, array $included, $base, $depth )
    {
        $out = array( 'id' => (string)$item['id'], 'type' => (string)$item['type'] );
        foreach ( isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array() as $key => $value )
            if ( is_scalar( $value ) && !preg_match( '/^drupal_internal__/', $key ) )
                $out[$key] = $value;
        $key = $item['type'] . '/' . $item['id'];
        if ( !isset( $included[$key] ) )
            return $out;
        $resource = $included[$key];
        $attributes = isset( $resource['attributes'] ) && is_array( $resource['attributes'] ) ? $resource['attributes'] : array();
        foreach ( $attributes as $name => $value )
        {
            if ( $name === 'uri' && is_array( $value ) )
                $out['url'] = self::absolute( isset( $value['url'] ) ? (string)$value['url'] : ( isset( $value['value'] ) ? (string)$value['value'] : '' ), $base );
            elseif ( is_scalar( $value ) || $value === null )
                $out[$name] = $value;
            elseif ( is_array( $value ) && isset( $value['alias'] ) )
                $out[$name] = $value['alias'];
        }
        if ( !isset( $out['name'] ) && isset( $attributes['display_name'] ) )
            $out['name'] = $attributes['display_name'];
        if ( !isset( $out['name'] ) && isset( $attributes['filename'] ) )
            $out['name'] = $attributes['filename'];
        // a media entity: the address of its file (field_media_image, field_media_file, thumbnail)
        if ( $depth < 2 && empty( $out['url'] ) && isset( $resource['relationships'] ) && is_array( $resource['relationships'] ) )
        {
            foreach ( $resource['relationships'] as $name => $relationship )
            {
                $data = isset( $relationship['data'] ) ? $relationship['data'] : null;
                if ( !is_array( $data ) || !isset( $data['type'] ) || strpos( (string)$data['type'], 'file--' ) !== 0 )
                    continue;
                $file = self::related( $data, $included, $base, $depth + 1 );
                if ( !empty( $file['url'] ) )
                {
                    $out['url'] = $file['url'];
                    if ( !isset( $out['alt'] ) && isset( $file['alt'] ) )
                        $out['alt'] = $file['alt'];
                    break;
                }
            }
        }
        return $out;
    }

    /** @return string an address of the site made absolute (public:// is left as it is) */
    protected static function absolute( $url, $base )
    {
        $url = trim( (string)$url );
        if ( $url === '' || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) || $base === '' )
            return $url;
        return expImportPlatformHttp::resolve( rtrim( $base, '/' ) . '/', $url );
    }

    /** @return string the first paragraph of an HTML text (its first line for plain text) */
    public static function firstParagraph( $html )
    {
        $html = trim( (string)$html );
        if ( $html === '' )
            return '';
        if ( preg_match( '#<p[\s>].*?</p>#is', $html, $m ) )
            return $m[0];
        $text = trim( strip_tags( $html ) );
        $lines = preg_split( '/\R\s*\R/', $text );
        return trim( (string)$lines[0] );
    }
}
