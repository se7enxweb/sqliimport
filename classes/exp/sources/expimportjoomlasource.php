<?php
/**
 * File containing the expImportJoomlaSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Joomla articles (com_content) exported as CSV (the columns of the content table: id, title, alias, introtext,
 * fulltext, catid or category, state, created, publish_up, language, metadesc, images ...) or as XML (J2XML, whose
 * articles are <content> elements, or an export with <article> elements and the fields as child elements). The
 * variant is detected from the file's first bytes.
 *
 * Every article is a record with friendly fields: title, alias, intro (introtext), fulltext, body (introtext and
 * fulltext joined, as Joomla shows the article), category (its title when the export has the categories or a
 * category column, else the last part of its path), published, the dates, language (empty for "*", all languages),
 * metadesc, keywords (metakey as a list), and the images of the images JSON: image_intro, image_fulltext and
 * image_url (the full text image, else the intro image), made absolute with the option site_url.
 */
class expImportJoomlaSource extends expImportAbstractSource
{
    /** @var string|null csv or xml */
    protected $variant = null;

    /** @var array|null category id or path => title, from the XML's <category> elements */
    protected $categories = null;

    public static function identifier()
    {
        return 'joomla';
    }

    public static function label()
    {
        return 'Joomla articles (CSV or XML export)';
    }

    public static function extensions()
    {
        return array( 'csv', 'xml' );
    }

    public static function group()
    {
        return 'platform';
    }

    public static function description()
    {
        return 'Articles of a Joomla site, exported as CSV (the columns of the articles table) or as XML (J2XML or an '
             . 'export with one <article> per article). Intro and full text are joined into the body, the images JSON '
             . 'gives the intro and full text image, and categories are named by their title.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three articles as CSV', 'file' => 'joomla-articles.csv',
                             'options' => array( 'site_url' => 'https://www.example.org/' ) ),
                      array( 'label' => 'The same articles as J2XML with their categories', 'file' => 'joomla-articles.xml',
                             'options' => array( 'site_url' => 'https://www.example.org/' ) ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'joomla-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title' ),
                array( 'source' => 'intro', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'body', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
                array( 'source' => 'image_url', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'image' ) ), 'datatypes' => array( 'ezimage' ) ),
                array( 'source' => 'image_url', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'media_object' ) ), 'datatypes' => array( 'ezobjectrelation' ) ),
                array( 'source' => 'keywords', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ), 'datatypes' => array( 'ezkeyword', 'eztags' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'variant' => array( 'type' => 'enum', 'label' => 'Export', 'default' => 'auto',
                                'values' => array( 'auto' => 'Detect', 'csv' => 'CSV', 'xml' => 'XML (J2XML or <article> elements)' ) ),
            'site_url' => array( 'type' => 'url', 'label' => 'Site address',
                                 'help' => 'The address of the Joomla site, so the images (images/...) can be downloaded' ),
            'status' => array( 'type' => 'enum', 'label' => 'Articles', 'default' => 'not_trashed',
                               'values' => array( 'published' => 'Only published', 'not_trashed' => 'Published, unpublished and archived', 'all' => 'All, also trashed' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a record; id is the Joomla article ID.' ),
        );
    }

    /** @return string csv or xml */
    protected function variant()
    {
        if ( $this->variant !== null )
            return $this->variant;
        $variant = isset( $this->config['variant'] ) ? (string)$this->config['variant'] : 'auto';
        if ( $variant !== 'csv' && $variant !== 'xml' )
        {
            $fp = fopen( $this->file(), 'rb' );
            $head = ltrim( (string)fread( $fp, 512 ), "\xEF\xBB\xBF \t\r\n" );
            fclose( $fp );
            $variant = $head !== '' && $head[0] === '<' ? 'xml' : 'csv';
        }
        $this->detected['format'] = $variant;
        return $this->variant = $variant;
    }

    public function records( array $cursor = array() )
    {
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        if ( $this->variant() === 'csv' )
        {
            $csv = new expImportCsvSource( array( 'file' => $this->file(), 'delimiter' => 'auto', 'enclosure' => '"', 'encoding' => 'auto', 'header' => true ) );
            $from = isset( $cursor['pos'] ) ? array( 'pos' => (int)$cursor['pos'], 'index' => $index ) : array();
            foreach ( $csv->records( $from ) as $row )
            {
                $data = $this->article( $row->data );
                if ( $data === null )
                    continue;
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => $row->cursor['pos'], 'index' => $index + 1 ) );
                $index++;
            }
            return;
        }
        $skip = $index;
        $n = 0;
        foreach ( $this->xmlElements( array( 'article', 'content' ) ) as $row )
        {
            $data = $this->article( $row );
            if ( $data === null )
                continue;
            if ( $n >= $skip )
                yield new expImportRecord( $this->refOf( $data, $n ), $n, $data, array( 'index' => $n + 1 ) );
            $n++;
        }
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }

    /**
     * The elements of the XML export with one of the names, as arrays of their child elements' texts (a child with
     * children of its own, such as <images>, as an array of them).
     *
     * @param string[] $names
     * @return Generator array
     */
    protected function xmlElements( array $names )
    {
        $reader = new XMLReader();
        if ( !@$reader->open( $this->file(), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the Joomla XML cannot be opened' );
        $previous = libxml_use_internal_errors( true );
        $found = false;
        try
        {
            $ok = @$reader->read();
            while ( $ok )
            {
                if ( $reader->nodeType !== XMLReader::ELEMENT || !in_array( $reader->localName, $names, true ) )
                {
                    $ok = @$reader->read();
                    continue;
                }
                $doc = new DOMDocument();
                if ( @$doc->loadXML( $reader->readOuterXml(), LIBXML_NONET ) )
                {
                    $row = array();
                    foreach ( $doc->documentElement->childNodes as $child )
                    {
                        if ( !$child instanceof DOMElement )
                            continue;
                        $nested = array();
                        foreach ( $child->childNodes as $grand )
                            if ( $grand instanceof DOMElement )
                                $nested[$grand->localName] = $grand->textContent;
                        $row[$child->localName] = $nested ? $nested : $child->textContent;
                    }
                    // an element of the list (<article>) without a title is a container, not an article
                    if ( isset( $row['title'] ) )
                    {
                        $found = true;
                        yield $row;
                    }
                }
                $ok = @$reader->next();
            }
            $error = libxml_get_last_error();
            if ( $error && $error->level >= LIBXML_ERR_ERROR )
                throw new expImportSourceException( 'the Joomla XML is not well-formed at line ' . $error->line . ': ' . trim( $error->message ) );
            if ( !$found && in_array( 'article', $names, true ) )
                throw new expImportSourceException( 'the XML has no Joomla articles (<article> or <content> elements with a <title>)' );
        }
        finally
        {
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
            $reader->close();
        }
    }

    /** @return array category id and path => title, from the <category> elements of an XML export */
    protected function categories()
    {
        if ( $this->categories !== null )
            return $this->categories;
        $this->categories = array();
        if ( $this->variant() !== 'xml' )
            return $this->categories;
        foreach ( $this->xmlElements( array( 'category' ) ) as $row )
        {
            $title = is_string( $row['title'] ) ? trim( $row['title'] ) : '';
            foreach ( array( 'id', 'path', 'alias' ) as $key )
                if ( isset( $row[$key] ) && is_string( $row[$key] ) && trim( $row[$key] ) !== '' )
                    $this->categories[trim( $row[$key] )] = $title;
        }
        return $this->categories;
    }

    /**
     * One article of the export as a record, or null when the status option leaves it out.
     *
     * @param array $row the export's fields
     * @return array|null
     */
    public function article( array $row )
    {
        $get = function ( $key ) use ( $row )
        {
            return isset( $row[$key] ) && is_string( $row[$key] ) ? trim( $row[$key] ) : '';
        };
        $state = $get( 'state' ) !== '' ? (int)$get( 'state' ) : ( $get( 'published' ) !== '' ? (int)$get( 'published' ) : 1 );
        $status = isset( $this->config['status'] ) ? (string)$this->config['status'] : 'not_trashed';
        if ( ( $status === 'published' && $state !== 1 ) || ( $status === 'not_trashed' && $state === -2 ) )
            return null;
        $intro = isset( $row['introtext'] ) && is_string( $row['introtext'] ) ? $row['introtext'] : '';
        $full = isset( $row['fulltext'] ) && is_string( $row['fulltext'] ) ? $row['fulltext'] : '';
        $data = $row;
        // the category: a title column, the categories of the export, or the last part of the path
        $catid = $get( 'catid' );
        $category = $get( 'category_title' ) !== '' ? $get( 'category_title' ) : $get( 'category' );
        $categories = $this->categories();
        if ( $category === '' && $catid !== '' )
            $category = isset( $categories[$catid] ) ? $categories[$catid]
                      : ( ctype_digit( $catid ) ? '' : ucfirst( str_replace( array( '-', '_' ), ' ', basename( $catid ) ) ) );
        elseif ( $category !== '' && isset( $categories[$category] ) )
            $category = $categories[$category];
        $images = isset( $row['images'] ) ? $row['images'] : '';
        if ( is_string( $images ) )
            $images = trim( $images ) !== '' ? json_decode( $images, true ) : array();
        $images = is_array( $images ) ? $images : array();
        $image = function ( $key ) use ( $images )
        {
            $value = isset( $images[$key] ) && is_string( $images[$key] ) ? trim( $images[$key] ) : '';
            // Joomla 4 adds the media manager's address after a #
            $value = (string)preg_replace( '/#.*$/', '', $value );
            return $value === '' ? '' : $this->absolute( $value );
        };
        $language = $get( 'language' );
        $data = array(
            'id' => $get( 'id' ),
            'title' => $get( 'title' ),
            'alias' => $get( 'alias' ),
            'intro' => $intro,
            'fulltext' => $full,
            'body' => trim( $full ) !== '' ? rtrim( $intro ) . "\n" . ltrim( $full ) : $intro,
            'category' => $category,
            'category_id' => ctype_digit( $catid ) ? $catid : ( $get( 'category_id' ) !== '' ? $get( 'category_id' ) : '' ),
            'category_path' => ctype_digit( $catid ) ? '' : $catid,
            'state' => $state,
            'published' => $state === 1,
            'featured' => $get( 'featured' ) === '1',
            'created' => $get( 'created' ),
            'modified' => $get( 'modified' ),
            'publish_up' => $get( 'publish_up' ),
            'publish_down' => $get( 'publish_down' ),
            'date' => $get( 'publish_up' ) !== '' && strpos( $get( 'publish_up' ), '0000' ) !== 0 ? $get( 'publish_up' ) : $get( 'created' ),
            'language' => $language === '*' ? '' : $language,
            'metadesc' => $get( 'metadesc' ),
            'keywords' => array_values( array_filter( array_map( 'trim', explode( ',', $get( 'metakey' ) ) ), 'strlen' ) ),
            'author' => $get( 'created_by_alias' ) !== '' ? $get( 'created_by_alias' ) : $get( 'created_by' ),
            'hits' => $get( 'hits' ) !== '' ? (int)$get( 'hits' ) : 0,
            'image_intro' => $image( 'image_intro' ),
            'image_intro_alt' => isset( $images['image_intro_alt'] ) ? (string)$images['image_intro_alt'] : '',
            'image_fulltext' => $image( 'image_fulltext' ),
            'image_fulltext_alt' => isset( $images['image_fulltext_alt'] ) ? (string)$images['image_fulltext_alt'] : '',
        ) + array_diff_key( $row, array_flip( array( 'introtext', 'images', 'metakey' ) ) );
        // the address to download: only an address (a path of the site needs the option site_url)
        $full = preg_match( '#^https?://#i', $data['image_fulltext'] ) ? $data['image_fulltext'] : '';
        $intro = preg_match( '#^https?://#i', $data['image_intro'] ) ? $data['image_intro'] : '';
        $data['image_url'] = $full !== '' ? $full : $intro;
        $data['image_alt'] = $full !== '' ? $data['image_fulltext_alt'] : ( $intro !== '' ? $data['image_intro_alt'] : '' );
        return $data;
    }

    /** @return string an image path of the site as an address (with the option site_url) */
    protected function absolute( $path )
    {
        if ( preg_match( '#^https?://#i', $path ) )
            return $path;
        $site = isset( $this->config['site_url'] ) ? trim( (string)$this->config['site_url'] ) : '';
        return $site === '' ? $path : rtrim( $site, '/' ) . '/' . ltrim( $path, '/' );
    }
}
