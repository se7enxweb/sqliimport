<?php
/**
 * File containing the expImportNotionSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The pages of a Notion database through the Notion API (version 2022-06-28): one record is one page (one row of the
 * database), its fields the database's properties by name, as plain values.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() queries
 * POST https://api.notion.com/v1/databases/<id>/query page by page (page_size up to 100, start_cursor while has_more,
 * at most [PlatformSettings] MaxPages requests) with the import's filter and sorts, and writes the pages into the run's
 * snapshot, one JSON object per line. On request it also reads the content of every page (the blocks of its first
 * level) as Markdown or HTML into the field content.
 *
 * Only reading is possible: the query endpoint and GET requests are the only ones made. The integration token is the
 * name of a secret (expImportSecrets), never stored in the import, and masked in every message; share only the
 * databases the import needs with the integration, and give it the capability "Read content" only. Notion allows about
 * three requests a second: the source keeps a third of a second between two requests and waits for the Retry-After of
 * a 429 before it tries again.
 *
 * Property values: title and rich_text as plain text; number; select and status as the option's name; multi_select as
 * the names joined by ", "; date as its start (<name>) and end (<name>_end) in ISO 8601; checkbox as true or false;
 * url, email and phone_number as they are; people as the list of names; files as the list of addresses (and
 * <name>_names); relation as the list of page IDs; formula and rollup as their value; created_time, last_edited_time,
 * created_by, last_edited_by, unique_id and verification as text. People may be personal data.
 */
class expImportNotionSource extends expImportJsonSource
{
    /** The address of the API. */
    const API = 'https://api.notion.com/v1/';

    /** The version of the API the source speaks (header Notion-Version). */
    const VERSION = '2022-06-28';

    /** The least time between two requests, in seconds (about three a second). */
    const INTERVAL = 0.34;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 2.0;

    public static function identifier()
    {
        return 'notion';
    }

    public static function label()
    {
        return 'Notion database';
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
        return 'The pages of a Notion database through the Notion API: one record is one page, its fields the properties of '
             . 'the database as plain values (text, numbers, dates in ISO 8601, select options, people\'s names, file '
             . 'addresses, related page IDs), optionally with the content of the page as Markdown or HTML. Read-only; the '
             . 'integration token is the name of a secret in the settings. People are personal data.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of two pages of a database "Articles" (what a run writes): title, rich text, a date, select options, people, files, a relation and the content as Markdown',
                   'file' => 'notion-articles.ndjson',
                   'options' => array( 'database_id' => '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0', 'token' => 'notion_token' ) ),
            array( 'label' => 'The published articles of a database, newest first, with their content as HTML, the token as the secret notion_token',
                   'options' => array( 'database_id' => '0f1e2d3c4b5a69788796a5b4c3d2e1f0', 'token' => 'notion_token',
                                       'filter' => '{"property": "Status", "status": {"equals": "Published"}}',
                                       'sorts' => '[{"property": "Date", "direction": "descending"}]', 'content' => 'html' ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        return array(
            'database_id' => array( 'type' => 'string', 'label' => 'Database ID', 'required' => true, 'max' => 500,
                                    'help' => 'The ID of the database (32 characters, with or without dashes) or its link (Share, Copy link)' ),
            'filter' => array( 'type' => 'json', 'label' => 'Filter',
                               'help' => 'Optional: a filter object of the Notion API, e.g. {"property": "Status", "status": {"equals": "Published"}}' ),
            'sorts' => array( 'type' => 'json', 'label' => 'Sorts',
                              'help' => 'Optional: a list of sorts of the Notion API, e.g. [{"property": "Date", "direction": "descending"}]' ),
            'content' => array( 'type' => 'enum', 'label' => 'Page content', 'default' => 'none',
                                'values' => array( 'none' => 'No (the properties only)', 'markdown' => 'As Markdown (field content)', 'html' => 'As HTML (field content)' ),
                                'help' => 'The blocks of the first level of every page (paragraphs, headings, lists, quotes, code, images); one more request per page' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 100, 'min' => 1, 'max' => 100,
                                  'help' => 'Pages per request (Notion answers at most 100)' ),
            'token' => array( 'type' => 'secret', 'label' => 'Integration token (name of a secret)', 'required' => true,
                              'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding the token of an internal integration the database is shared with, never the token' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record; id is the ID of the page' ),
        );
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the pages into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most pages (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $ref = isset( $options['token'] ) ? trim( (string)$options['token'] ) : '';
        $token = '';
        try
        {
            $token = expImportSecrets::required( $ref, 'Notion token' );
            expImportApiHttp::writeSnapshot( self::pages( $options, $token, (int)$limit ), $target, (int)$limit, 'Notion' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportApiHttp::mask( $e->getMessage(), $ref, $token ) );
        }
    }

    /**
     * The editor's "Test connection": queries the first page and reports its fields, or why it failed (never the
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
            $token = expImportSecrets::required( $ref, 'Notion token' );
            $first = null;
            foreach ( self::pages( array_merge( $options, array( 'page_size' => 1 ) ), $token, 1 ) as $page )
            {
                $first = $page;
                break;
            }
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'api',
                              'message' => 'connected; the database (filter) gives no page' );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => 1, 'driver' => 'api',
                          'message' => 'connected, the first page has these fields' );
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
        return array( 'api' => expImportApiHttp::httpsDriver( 'Notion API over HTTPS' ) );
    }

    /**
     * The ID of a database in the form of the API (8-4-4-4-12), from an ID with or without dashes or a link.
     *
     * @param string $given
     * @return string
     * @throws expImportSourceException
     */
    public static function databaseId( $given )
    {
        $text = trim( (string)$given );
        // a link: the ID is the last 32 hexadecimal characters of its path (the query's ?v= names a view)
        $path = preg_replace( '/[?#].*$/s', '', $text );
        if ( !preg_match_all( '/[0-9a-f]{32}/i', str_replace( '-', '', (string)$path ), $m ) )
            throw new expImportSourceException( 'Notion: the database ID (32 hexadecimal characters) or its link is expected' );
        $hex = strtolower( (string)end( $m[0] ) );
        return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
    }

    /** @return array the headers of a request */
    protected static function headers( $token )
    {
        return array( 'Authorization' => 'Bearer ' . $token, 'Notion-Version' => self::VERSION, 'Content-Type' => 'application/json' );
    }

    /**
     * The body of a query.
     *
     * @param array $options
     * @param string $cursor the next_cursor of the previous answer; '' for the first
     * @return array
     * @throws expImportSourceException
     */
    public static function queryBody( array $options, $cursor = '' )
    {
        $body = array( 'page_size' => max( 1, min( 100, isset( $options['page_size'] ) ? (int)$options['page_size'] : 100 ) ) );
        $filter = isset( $options['filter'] ) ? $options['filter'] : array();
        if ( is_string( $filter ) )
            $filter = trim( $filter ) === '' ? array() : json_decode( $filter, true );
        if ( !is_array( $filter ) || ( $filter && array_values( $filter ) === $filter ) )
            throw new expImportSourceException( 'Notion: the filter must be a JSON object' );
        if ( $filter )
            $body['filter'] = $filter;
        $sorts = isset( $options['sorts'] ) ? $options['sorts'] : array();
        if ( is_string( $sorts ) )
            $sorts = trim( $sorts ) === '' ? array() : json_decode( $sorts, true );
        if ( !is_array( $sorts ) )
            throw new expImportSourceException( 'Notion: the sorts must be a JSON list' );
        if ( $sorts && array_values( $sorts ) !== $sorts )
            $sorts = array( $sorts );
        if ( $sorts )
            $body['sorts'] = $sorts;
        if ( (string)$cursor !== '' )
            $body['start_cursor'] = (string)$cursor;
        return $body;
    }

    /**
     * The flattened pages of the database, request by request.
     *
     * @param array $options
     * @param string $token
     * @param int $limit 0: all
     * @return Generator array
     * @throws expImportSourceException
     */
    protected static function pages( array $options, $token, $limit )
    {
        $url = self::API . 'databases/' . self::databaseId( isset( $options['database_id'] ) ? $options['database_id'] : '' ) . '/query';
        $content = isset( $options['content'] ) ? (string)$options['content'] : 'none';
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxPages', 200 ) );
        $cursor = '';
        $n = 0;
        for ( $request = 1; $request <= $maxPages; $request++ )
        {
            $body = (string)json_encode( self::queryBody( $options, $cursor ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $answer = expImportApiHttp::json( 'Notion', 'POST', $url, self::headers( $token ), $body, self::INTERVAL, self::BACKOFF );
            if ( !isset( $answer['results'] ) || !is_array( $answer['results'] ) )
                throw new expImportSourceException( 'Notion: the answer has no list of results' );
            foreach ( $answer['results'] as $page )
            {
                if ( !is_array( $page ) || ( isset( $page['object'] ) && $page['object'] !== 'page' ) )
                    continue;
                $record = self::flatten( $page );
                if ( $content === 'markdown' || $content === 'html' )
                    $record = expImportApiHttp::merge( $record, array( 'content' => self::content( (string)$page['id'], $token, $content ) ) );
                yield $record;
                if ( $limit > 0 && ++$n >= $limit )
                    return;
            }
            $cursor = !empty( $answer['has_more'] ) && isset( $answer['next_cursor'] ) && is_string( $answer['next_cursor'] ) ? $answer['next_cursor'] : '';
            if ( $cursor === '' )
                return;
        }
        throw new expImportSourceException( "Notion: the database has more pages than [PlatformSettings] MaxPages ($maxPages) requests read" );
    }

    /**
     * One page of the API as a record: id, url, created_time, last_edited_time, archived, title and the properties.
     *
     * @param array $page
     * @return array
     */
    public static function flatten( array $page )
    {
        $out = array( 'id' => isset( $page['id'] ) ? (string)$page['id'] : '', 'url' => isset( $page['url'] ) ? (string)$page['url'] : '',
                      'created_time' => isset( $page['created_time'] ) ? (string)$page['created_time'] : '',
                      'last_edited_time' => isset( $page['last_edited_time'] ) ? (string)$page['last_edited_time'] : '',
                      'archived' => !empty( $page['archived'] ) || !empty( $page['in_trash'] ), 'title' => '' );
        $fields = array();
        foreach ( isset( $page['properties'] ) && is_array( $page['properties'] ) ? $page['properties'] : array() as $name => $property )
        {
            if ( !is_array( $property ) )
                continue;
            if ( isset( $property['type'] ) && $property['type'] === 'title' )
                $out['title'] = self::plainText( isset( $property['title'] ) ? $property['title'] : array() );
            foreach ( self::property( (string)$name, $property ) as $key => $value )
                $fields[$key] = $value;
        }
        return expImportApiHttp::merge( $out, $fields );
    }

    /**
     * A property's value as the fields of a record.
     *
     * @param string $name
     * @param array $property a property value of the API (type and the value under the type's name)
     * @return array name => value (date: also <name>_end; files: also <name>_names)
     */
    public static function property( $name, array $property )
    {
        $type = isset( $property['type'] ) ? (string)$property['type'] : '';
        $value = $type !== '' && array_key_exists( $type, $property ) ? $property[$type] : null;
        switch ( $type )
        {
            case 'date':
                return array( $name => is_array( $value ) && isset( $value['start'] ) ? (string)$value['start'] : null,
                              $name . '_end' => is_array( $value ) && isset( $value['end'] ) ? (string)$value['end'] : null );
            case 'files':
                $files = is_array( $value ) ? $value : array();
                return array( $name => array_values( array_map( array( __CLASS__, 'fileUrl' ), $files ) ),
                              $name . '_names' => array_values( array_map( function ( $f ) { return is_array( $f ) && isset( $f['name'] ) ? (string)$f['name'] : ''; }, $files ) ) );
        }
        return array( $name => self::value( $type, $value ) );
    }

    /**
     * A plain value of a type.
     *
     * @param string $type
     * @param mixed $value the value under the type's name
     * @return mixed
     */
    public static function value( $type, $value )
    {
        switch ( $type )
        {
            case 'title':
            case 'rich_text':
                return self::plainText( is_array( $value ) ? $value : array() );
            case 'number':
            case 'checkbox':
            case 'url':
            case 'email':
            case 'phone_number':
            case 'created_time':
            case 'last_edited_time':
            case 'string':
            case 'boolean':
                return $value;
            case 'select':
            case 'status':
                return is_array( $value ) && isset( $value['name'] ) ? (string)$value['name'] : null;
            case 'multi_select':
                return implode( ', ', array_map( function ( $o ) { return is_array( $o ) && isset( $o['name'] ) ? (string)$o['name'] : ''; }, is_array( $value ) ? $value : array() ) );
            case 'date':
                return is_array( $value ) && isset( $value['start'] ) ? (string)$value['start'] : null;
            case 'people':
                return array_values( array_map( array( __CLASS__, 'userName' ), is_array( $value ) ? $value : array() ) );
            case 'created_by':
            case 'last_edited_by':
                return is_array( $value ) ? self::userName( $value ) : null;
            case 'files':
                return array_values( array_map( array( __CLASS__, 'fileUrl' ), is_array( $value ) ? $value : array() ) );
            case 'relation':
                return array_values( array_map( function ( $r ) { return is_array( $r ) && isset( $r['id'] ) ? (string)$r['id'] : ''; }, is_array( $value ) ? $value : array() ) );
            case 'formula':
                if ( !is_array( $value ) || !isset( $value['type'] ) )
                    return null;
                return self::value( (string)$value['type'], array_key_exists( $value['type'], $value ) ? $value[$value['type']] : null );
            case 'rollup':
                if ( !is_array( $value ) || !isset( $value['type'] ) )
                    return null;
                if ( $value['type'] === 'array' )
                {
                    $items = array();
                    foreach ( isset( $value['array'] ) && is_array( $value['array'] ) ? $value['array'] : array() as $item )
                        if ( is_array( $item ) && isset( $item['type'] ) )
                            $items[] = self::value( (string)$item['type'], array_key_exists( $item['type'], $item ) ? $item[$item['type']] : null );
                    return $items;
                }
                return self::value( (string)$value['type'], array_key_exists( $value['type'], $value ) ? $value[$value['type']] : null );
            case 'unique_id':
                if ( !is_array( $value ) || !isset( $value['number'] ) )
                    return null;
                return isset( $value['prefix'] ) && $value['prefix'] !== null && $value['prefix'] !== '' ? $value['prefix'] . '-' . $value['number'] : (string)$value['number'];
            case 'verification':
                return is_array( $value ) && isset( $value['state'] ) ? (string)$value['state'] : null;
        }
        return is_scalar( $value ) ? $value : null;
    }

    /** @return string the text of a list of rich text objects */
    public static function plainText( array $richText )
    {
        $text = '';
        foreach ( $richText as $part )
            if ( is_array( $part ) )
                $text .= isset( $part['plain_text'] ) ? (string)$part['plain_text'] : ( isset( $part['text']['content'] ) ? (string)$part['text']['content'] : '' );
        return $text;
    }

    /** @return string the name of a user (a person or a bot), else its ID */
    public static function userName( $user )
    {
        if ( !is_array( $user ) )
            return '';
        return isset( $user['name'] ) && $user['name'] !== null ? (string)$user['name'] : ( isset( $user['id'] ) ? (string)$user['id'] : '' );
    }

    /** @return string the address of a file object (a file of Notion, valid for an hour, or an external one) */
    public static function fileUrl( $file )
    {
        if ( !is_array( $file ) )
            return '';
        if ( isset( $file['file']['url'] ) )
            return (string)$file['file']['url'];
        if ( isset( $file['external']['url'] ) )
            return (string)$file['external']['url'];
        return '';
    }

    // ---- the content of a page ---------------------------------------------------------------------------------------

    /**
     * The content of a page: the blocks of its first level as Markdown or HTML.
     *
     * @param string $pageId
     * @param string $token
     * @param string $format markdown or html
     * @return string
     * @throws expImportSourceException
     */
    protected static function content( $pageId, $token, $format )
    {
        if ( !preg_match( '/^[0-9a-f-]{32,36}$/i', $pageId ) )
            return '';
        $blocks = array();
        $cursor = '';
        $maxPages = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxPages', 200 ) );
        for ( $request = 1; $request <= $maxPages; $request++ )
        {
            $url = self::API . 'blocks/' . $pageId . '/children?page_size=100' . ( $cursor !== '' ? '&start_cursor=' . rawurlencode( $cursor ) : '' );
            $answer = expImportApiHttp::json( 'Notion', 'GET', $url, self::headers( $token ), null, self::INTERVAL, self::BACKOFF );
            foreach ( isset( $answer['results'] ) && is_array( $answer['results'] ) ? $answer['results'] : array() as $block )
                if ( is_array( $block ) )
                    $blocks[] = $block;
            $cursor = !empty( $answer['has_more'] ) && isset( $answer['next_cursor'] ) && is_string( $answer['next_cursor'] ) ? $answer['next_cursor'] : '';
            if ( $cursor === '' )
                break;
        }
        return $format === 'html' ? self::blocksToHtml( $blocks ) : self::blocksToMarkdown( $blocks );
    }

    /** @return string rich text as Markdown (bold, italic, strikethrough, code, links) */
    public static function richMarkdown( array $richText )
    {
        $out = '';
        foreach ( $richText as $part )
        {
            if ( !is_array( $part ) )
                continue;
            $text = isset( $part['plain_text'] ) ? (string)$part['plain_text'] : '';
            if ( $text === '' )
                continue;
            $a = isset( $part['annotations'] ) && is_array( $part['annotations'] ) ? $part['annotations'] : array();
            if ( !empty( $a['code'] ) )
                $text = '`' . $text . '`';
            if ( !empty( $a['bold'] ) )
                $text = '**' . $text . '**';
            if ( !empty( $a['italic'] ) )
                $text = '*' . $text . '*';
            if ( !empty( $a['strikethrough'] ) )
                $text = '~~' . $text . '~~';
            $href = isset( $part['href'] ) && is_string( $part['href'] ) ? $part['href'] : '';
            if ( $href !== '' && preg_match( '#^(https?://|mailto:|/)#i', $href ) )
                $text = '[' . $text . '](' . str_replace( array( '(', ')', ' ' ), array( '%28', '%29', '%20' ), $href ) . ')';
            $out .= $text;
        }
        return $out;
    }

    /** @return string rich text as HTML (strong, em, s, u, code, links) */
    public static function richHtml( array $richText )
    {
        $out = '';
        foreach ( $richText as $part )
        {
            if ( !is_array( $part ) )
                continue;
            $text = htmlspecialchars( isset( $part['plain_text'] ) ? (string)$part['plain_text'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
            if ( $text === '' )
                continue;
            $text = str_replace( "\n", '<br />', $text );
            $a = isset( $part['annotations'] ) && is_array( $part['annotations'] ) ? $part['annotations'] : array();
            foreach ( array( 'code' => 'code', 'bold' => 'strong', 'italic' => 'em', 'strikethrough' => 's', 'underline' => 'u' ) as $flag => $tag )
                if ( !empty( $a[$flag] ) )
                    $text = '<' . $tag . '>' . $text . '</' . $tag . '>';
            $href = isset( $part['href'] ) && is_string( $part['href'] ) ? $part['href'] : '';
            if ( $href !== '' && preg_match( '#^(https?://|mailto:|/)#i', $href ) )
                $text = '<a href="' . htmlspecialchars( $href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '">' . $text . '</a>';
            $out .= $text;
        }
        return $out;
    }

    /** @return array the rich text of a block and its caption (image, code) */
    protected static function blockParts( array $block )
    {
        $type = isset( $block['type'] ) ? (string)$block['type'] : '';
        $data = isset( $block[$type] ) && is_array( $block[$type] ) ? $block[$type] : array();
        $rich = isset( $data['rich_text'] ) && is_array( $data['rich_text'] ) ? $data['rich_text'] : array();
        $caption = isset( $data['caption'] ) && is_array( $data['caption'] ) ? $data['caption'] : array();
        $url = '';
        if ( isset( $data['file']['url'] ) )
            $url = (string)$data['file']['url'];
        elseif ( isset( $data['external']['url'] ) )
            $url = (string)$data['external']['url'];
        elseif ( isset( $data['url'] ) && is_string( $data['url'] ) )
            $url = $data['url'];
        return array( $type, $data, $rich, $caption, $url );
    }

    /**
     * Blocks of the first level as Markdown: paragraphs, headings, lists, to-dos, quotes and callouts, code, dividers,
     * images and links of bookmarks, embeds, videos and files; a toggle as its text. Other blocks are left out.
     *
     * @param array[] $blocks
     * @return string
     */
    public static function blocksToMarkdown( array $blocks )
    {
        $lines = array();
        $previous = '';
        $number = 0;
        foreach ( $blocks as $block )
        {
            list( $type, $data, $rich, $caption, $url ) = self::blockParts( $block );
            $text = self::richMarkdown( $rich );
            $number = $type === 'numbered_list_item' ? ( $previous === 'numbered_list_item' ? $number + 1 : 1 ) : 0;
            switch ( $type )
            {
                case 'paragraph':
                case 'toggle':
                    $line = $text;
                    break;
                case 'heading_1':
                case 'heading_2':
                case 'heading_3':
                    $line = str_repeat( '#', (int)substr( $type, -1 ) ) . ' ' . $text;
                    break;
                case 'bulleted_list_item':
                    $line = '- ' . $text;
                    break;
                case 'numbered_list_item':
                    $line = $number . '. ' . $text;
                    break;
                case 'to_do':
                    $line = '- [' . ( !empty( $data['checked'] ) ? 'x' : ' ' ) . '] ' . $text;
                    break;
                case 'quote':
                case 'callout':
                    $line = '> ' . str_replace( "\n", "\n> ", $text );
                    break;
                case 'code':
                    $language = isset( $data['language'] ) && is_string( $data['language'] ) && $data['language'] !== 'plain text' ? $data['language'] : '';
                    $line = '```' . $language . "\n" . self::plainText( $rich ) . "\n```";
                    break;
                case 'divider':
                    $line = '---';
                    break;
                case 'image':
                    $line = $url !== '' ? '![' . str_replace( array( '[', ']' ), '', self::plainText( $caption ) ) . '](' . str_replace( array( '(', ')', ' ' ), array( '%28', '%29', '%20' ), $url ) . ')' : '';
                    break;
                case 'bookmark':
                case 'embed':
                case 'video':
                case 'file':
                case 'pdf':
                case 'link_preview':
                    $label = self::plainText( $caption );
                    $line = $url !== '' ? '[' . ( $label !== '' ? $label : $url ) . '](' . str_replace( array( '(', ')', ' ' ), array( '%28', '%29', '%20' ), $url ) . ')' : '';
                    break;
                default:
                    $line = '';
            }
            if ( $line === '' )
                continue;
            $list = in_array( $type, array( 'bulleted_list_item', 'numbered_list_item', 'to_do' ), true );
            $lines[] = ( $lines && !( $list && $previous === $type ) ? "\n" : '' ) . $line;
            $previous = $type;
        }
        return trim( implode( "\n", $lines ) );
    }

    /**
     * Blocks of the first level as HTML, the same blocks as blocksToMarkdown(); list items grouped in <ul> and <ol>.
     *
     * @param array[] $blocks
     * @return string
     */
    public static function blocksToHtml( array $blocks )
    {
        $out = array();
        $open = '';
        foreach ( $blocks as $block )
        {
            list( $type, $data, $rich, $caption, $url ) = self::blockParts( $block );
            $text = self::richHtml( $rich );
            $list = $type === 'bulleted_list_item' || $type === 'to_do' ? 'ul' : ( $type === 'numbered_list_item' ? 'ol' : '' );
            if ( $open !== '' && $list !== $open )
            {
                $out[] = '</' . $open . '>';
                $open = '';
            }
            if ( $list !== '' && $open === '' )
            {
                $out[] = '<' . $list . '>';
                $open = $list;
            }
            $u = htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
            $safe = $url !== '' && preg_match( '#^https?://#i', $url );
            switch ( $type )
            {
                case 'paragraph':
                case 'toggle':
                    $out[] = '<p>' . $text . '</p>';
                    break;
                case 'heading_1':
                case 'heading_2':
                case 'heading_3':
                    $level = (int)substr( $type, -1 );
                    $out[] = '<h' . $level . '>' . $text . '</h' . $level . '>';
                    break;
                case 'bulleted_list_item':
                case 'numbered_list_item':
                    $out[] = '<li>' . $text . '</li>';
                    break;
                case 'to_do':
                    $out[] = '<li>' . ( !empty( $data['checked'] ) ? '[x] ' : '[ ] ' ) . $text . '</li>';
                    break;
                case 'quote':
                case 'callout':
                    $out[] = '<blockquote>' . $text . '</blockquote>';
                    break;
                case 'code':
                    $out[] = '<pre><code>' . htmlspecialchars( self::plainText( $rich ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code></pre>';
                    break;
                case 'divider':
                    $out[] = '<hr />';
                    break;
                case 'image':
                    if ( $safe )
                        $out[] = '<img src="' . $u . '" alt="' . htmlspecialchars( self::plainText( $caption ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '" />';
                    break;
                case 'bookmark':
                case 'embed':
                case 'video':
                case 'file':
                case 'pdf':
                case 'link_preview':
                    if ( $safe )
                    {
                        $label = self::plainText( $caption );
                        $out[] = '<p><a href="' . $u . '">' . ( $label !== '' ? htmlspecialchars( $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) : $u ) . '</a></p>';
                    }
                    break;
            }
        }
        if ( $open !== '' )
            $out[] = '</' . $open . '>';
        return implode( "\n", $out );
    }
}
