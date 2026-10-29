<?php
/**
 * File containing the expImportWxrSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * WordPress export files (WXR, Tools > Export in WordPress): RSS 2.0 with the wp: namespace. What a record is depends on
 * the option "records":
 *
 *   posts        posts and pages (the post types of the option "post_types"): id, type, title, slug, link, date (UTC,
 *                ISO 8601), author (login), author_name, status, parent_id, menu_order, content (as written),
 *                content_html (paragraphs made from blank lines, as WordPress shows it), excerpt, categories, tags
 *                (names), category_slugs, tag_slugs, featured_image (the address of the attachment set as featured
 *                image), images (the addresses of the images in the content), comment_count, meta (key => value,
 *                without WordPress's own keys starting with _)
 *   attachments  the media: id, title, url, mime (from the extension), parent_id (the post it belongs to), date, alt,
 *                caption (the excerpt), description (the content)
 *   categories   id, name, slug, parent (slug), description
 *   tags         id, name, slug, description
 *   authors      id, login, email, display_name, first_name, last_name
 *
 * The file is read as a stream with XMLReader (external entities never load); attachments and authors are collected
 * in a first pass when posts need them. The cursor is the position of the record.
 */
class expImportWxrSource extends expImportAbstractSource
{
    /** @var array|null attachment id => url */
    protected $attachments = null;

    /** @var array|null author login => display name */
    protected $authors = null;

    public static function identifier()
    {
        return 'wxr';
    }

    public static function label()
    {
        return 'WordPress export (WXR)';
    }

    public static function extensions()
    {
        return array( 'xml', 'wxr' );
    }

    public static function description()
    {
        return 'The export file of WordPress (Tools, Export): posts and pages with their author, date, categories, tags, '
             . 'excerpt and featured image, or the media, categories, tags and authors of the site. Import the media first '
             . 'and the posts after it to keep the images.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A WordPress export with two posts, a page, an image and their categories and tags',
                             'file' => 'wordpress.xml' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'wp-{type}-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title' ),
                array( 'source' => 'excerpt', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'content_html', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
                array( 'source' => 'featured_image', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'image' ) ), 'datatypes' => array( 'ezimage' ) ),
                array( 'source' => 'featured_image', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'media_object' ) ), 'datatypes' => array( 'ezobjectrelation' ) ),
                array( 'source' => 'tags', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ), 'datatypes' => array( 'ezkeyword', 'eztags' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'records' => array( 'type' => 'enum', 'label' => 'Records', 'default' => 'posts',
                                'values' => array( 'posts' => 'Posts and pages', 'attachments' => 'Media (attachments)', 'categories' => 'Categories',
                                                   'tags' => 'Tags', 'authors' => 'Authors' ) ),
            'post_types' => array( 'type' => 'list', 'label' => 'Post types', 'default' => array( 'post', 'page' ),
                                   'help' => 'With Records "posts": the post types to read, one per line (post, page, or a custom type)' ),
            'status' => array( 'type' => 'enum', 'label' => 'Status', 'default' => 'publish',
                               'values' => array( 'publish' => 'Only published', 'all' => 'Every status (drafts, private, pending too)' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record; id is the WordPress ID (slug for categories and tags).' ),
        );
    }

    /** @return string */
    protected function mode()
    {
        $mode = isset( $this->config['records'] ) ? (string)$this->config['records'] : 'posts';
        return in_array( $mode, array( 'posts', 'attachments', 'categories', 'tags', 'authors' ), true ) ? $mode : 'posts';
    }

    /**
     * The elements of the file with a name, as arrays, in the order of the file.
     *
     * @param string[] $names element names (item, wp:category, wp:tag, wp:author)
     * @return Generator name => array
     */
    protected function elements( array $names )
    {
        $reader = new XMLReader();
        if ( !@$reader->open( $this->file(), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the WordPress export cannot be opened' );
        $previous = libxml_use_internal_errors( true );
        $seenChannel = false;
        try
        {
            $ok = @$reader->read();
            while ( $ok )
            {
                if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'channel' )
                    $seenChannel = true;
                if ( $reader->nodeType === XMLReader::ELEMENT && in_array( $reader->name, $names, true ) && $reader->depth === 2 )
                {
                    $name = $reader->name;
                    $data = self::itemToArray( $reader->readOuterXml() );
                    yield $name => $data;
                    $ok = @$reader->next();
                    continue;
                }
                $ok = @$reader->read();
            }
            if ( !$seenChannel )
                throw new expImportSourceException( 'the file is not a WordPress export (no <channel>)' );
            $error = libxml_get_last_error();
            if ( $error && $error->level >= LIBXML_ERR_ERROR )
                throw new expImportSourceException( 'the export is not well-formed at line ' . $error->line . ': ' . trim( $error->message ) );
        }
        finally
        {
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
            $reader->close();
        }
    }

    /**
     * An element of the export as an array: child elements by their name with prefix (wp:post_id), repeated ones as a
     * list, <category domain="..." nicename="..."> kept with its attributes.
     *
     * @param string $xml
     * @return array
     */
    public static function itemToArray( $xml )
    {
        $previous = libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $ok = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_NOCDATA );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok || !$doc->documentElement )
            return array();
        $out = array();
        foreach ( $doc->documentElement->childNodes as $child )
        {
            if ( !$child instanceof DOMElement )
                continue;
            if ( $child->nodeName === 'category' )
            {
                $out['category'][] = array( 'domain' => $child->getAttribute( 'domain' ), 'slug' => $child->getAttribute( 'nicename' ),
                                            'name' => trim( $child->textContent ) );
                continue;
            }
            if ( $child->nodeName === 'wp:postmeta' || $child->nodeName === 'wp:comment' )
            {
                $sub = array();
                foreach ( $child->childNodes as $c )
                    if ( $c instanceof DOMElement )
                        $sub[$c->nodeName] = $c->textContent;
                $out[$child->nodeName][] = $sub;
                continue;
            }
            $out[$child->nodeName] = $child->textContent;
        }
        return $out;
    }

    /** @return array attachment id => url, and author login => display name (one pass, kept for this source) */
    protected function lookups()
    {
        if ( $this->attachments !== null )
            return array( $this->attachments, $this->authors );
        $this->attachments = array();
        $this->authors = array();
        foreach ( $this->elements( array( 'item', 'wp:author' ) ) as $name => $data )
        {
            if ( $name === 'wp:author' )
            {
                $login = isset( $data['wp:author_login'] ) ? trim( $data['wp:author_login'] ) : '';
                if ( $login !== '' )
                    $this->authors[$login] = isset( $data['wp:author_display_name'] ) ? trim( $data['wp:author_display_name'] ) : $login;
            }
            elseif ( isset( $data['wp:post_type'] ) && $data['wp:post_type'] === 'attachment' && !empty( $data['wp:attachment_url'] ) )
                $this->attachments[trim( (string)$data['wp:post_id'] )] = trim( $data['wp:attachment_url'] );
        }
        return array( $this->attachments, $this->authors );
    }

    public function records( array $cursor = array() )
    {
        $mode = $this->mode();
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $names = array( 'posts' => array( 'item' ), 'attachments' => array( 'item' ), 'categories' => array( 'wp:category' ),
                        'tags' => array( 'wp:tag' ), 'authors' => array( 'wp:author' ) );
        $types = isset( $this->config['post_types'] ) && $this->config['post_types'] ? array_map( 'strval', (array)$this->config['post_types'] ) : array( 'post', 'page' );
        $all = isset( $this->config['status'] ) && $this->config['status'] === 'all';
        $lookups = $mode === 'posts' ? $this->lookups() : array( array(), array() );
        $index = 0;
        foreach ( $this->elements( $names[$mode] ) as $name => $raw )
        {
            if ( $mode === 'posts' )
            {
                $type = isset( $raw['wp:post_type'] ) ? $raw['wp:post_type'] : '';
                $status = isset( $raw['wp:status'] ) ? $raw['wp:status'] : '';
                if ( !in_array( $type, $types, true ) || ( !$all && $status !== 'publish' ) )
                    continue;
                $data = self::post( $raw, $lookups[0], $lookups[1] );
            }
            elseif ( $mode === 'attachments' )
            {
                if ( !isset( $raw['wp:post_type'] ) || $raw['wp:post_type'] !== 'attachment' )
                    continue;
                $data = self::attachment( $raw );
            }
            else
                $data = self::term( $mode, $raw );
            if ( $index >= $skip )
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
            $index++;
        }
    }

    /** @return string a value of an exported element, trimmed */
    protected static function v( array $raw, $name )
    {
        return isset( $raw[$name] ) && is_string( $raw[$name] ) ? trim( $raw[$name] ) : '';
    }

    /** @return string a WordPress date (GMT, else local) as ISO 8601, '' for none */
    public static function date( array $raw )
    {
        $gmt = self::v( $raw, 'wp:post_date_gmt' );
        if ( $gmt !== '' && $gmt !== '0000-00-00 00:00:00' )
            return str_replace( ' ', 'T', $gmt ) . 'Z';
        $pub = self::v( $raw, 'pubDate' );
        $ts = $pub !== '' ? strtotime( $pub ) : false;
        if ( $ts )
            return gmdate( 'Y-m-d\TH:i:s\Z', $ts );
        $local = self::v( $raw, 'wp:post_date' );
        return $local !== '' && $local !== '0000-00-00 00:00:00' ? str_replace( ' ', 'T', $local ) : '';
    }

    /** @return array the record of a post or page */
    public static function post( array $raw, array $attachments, array $authors )
    {
        $categories = $tags = $categorySlugs = $tagSlugs = array();
        foreach ( isset( $raw['category'] ) ? $raw['category'] : array() as $c )
        {
            if ( $c['domain'] === 'post_tag' )
            {
                $tags[] = $c['name'];
                $tagSlugs[] = $c['slug'];
            }
            elseif ( $c['domain'] === 'category' )
            {
                $categories[] = $c['name'];
                $categorySlugs[] = $c['slug'];
            }
        }
        $meta = array();
        $thumbnail = '';
        foreach ( isset( $raw['wp:postmeta'] ) ? $raw['wp:postmeta'] : array() as $m )
        {
            $key = isset( $m['wp:meta_key'] ) ? trim( $m['wp:meta_key'] ) : '';
            $value = isset( $m['wp:meta_value'] ) ? (string)$m['wp:meta_value'] : '';
            if ( $key === '_thumbnail_id' )
                $thumbnail = trim( $value );
            elseif ( $key !== '' && $key[0] !== '_' )
                $meta[$key] = $value;
        }
        $content = isset( $raw['content:encoded'] ) ? (string)$raw['content:encoded'] : '';
        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $images );
        $login = self::v( $raw, 'dc:creator' );
        return array(
            'id' => self::v( $raw, 'wp:post_id' ),
            'type' => self::v( $raw, 'wp:post_type' ),
            'title' => self::v( $raw, 'title' ),
            'slug' => self::v( $raw, 'wp:post_name' ),
            'link' => self::v( $raw, 'link' ),
            'date' => self::date( $raw ),
            'author' => $login,
            'author_name' => isset( $authors[$login] ) ? $authors[$login] : $login,
            'status' => self::v( $raw, 'wp:status' ),
            'parent_id' => self::v( $raw, 'wp:post_parent' ),
            'menu_order' => self::v( $raw, 'wp:menu_order' ),
            'content' => $content,
            'content_html' => self::autop( $content ),
            'excerpt' => isset( $raw['excerpt:encoded'] ) ? trim( (string)$raw['excerpt:encoded'] ) : '',
            'categories' => $categories,
            'tags' => $tags,
            'category_slugs' => $categorySlugs,
            'tag_slugs' => $tagSlugs,
            'featured_image' => $thumbnail !== '' && isset( $attachments[$thumbnail] ) ? $attachments[$thumbnail] : '',
            'images' => array_values( array_unique( $images[1] ) ),
            'comment_count' => isset( $raw['wp:comment'] ) ? count( $raw['wp:comment'] ) : 0,
            'meta' => $meta,
        );
    }

    /** @return array the record of an attachment */
    public static function attachment( array $raw )
    {
        $url = self::v( $raw, 'wp:attachment_url' );
        $alt = '';
        foreach ( isset( $raw['wp:postmeta'] ) ? $raw['wp:postmeta'] : array() as $m )
            if ( isset( $m['wp:meta_key'] ) && trim( $m['wp:meta_key'] ) === '_wp_attachment_image_alt' )
                $alt = trim( (string)$m['wp:meta_value'] );
        $types = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
                        'svg' => 'image/svg+xml', 'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4' );
        $extension = strtolower( pathinfo( (string)parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        return array(
            'id' => self::v( $raw, 'wp:post_id' ),
            'title' => self::v( $raw, 'title' ),
            'url' => $url,
            'mime' => isset( $types[$extension] ) ? $types[$extension] : '',
            'parent_id' => self::v( $raw, 'wp:post_parent' ),
            'date' => self::date( $raw ),
            'alt' => $alt,
            'caption' => isset( $raw['excerpt:encoded'] ) ? trim( (string)$raw['excerpt:encoded'] ) : '',
            'description' => isset( $raw['content:encoded'] ) ? trim( (string)$raw['content:encoded'] ) : '',
        );
    }

    /** @return array the record of a category, a tag or an author */
    public static function term( $mode, array $raw )
    {
        if ( $mode === 'authors' )
            return array( 'id' => self::v( $raw, 'wp:author_id' ), 'login' => self::v( $raw, 'wp:author_login' ), 'email' => self::v( $raw, 'wp:author_email' ),
                          'display_name' => self::v( $raw, 'wp:author_display_name' ), 'first_name' => self::v( $raw, 'wp:author_first_name' ),
                          'last_name' => self::v( $raw, 'wp:author_last_name' ) );
        if ( $mode === 'categories' )
            return array( 'id' => self::v( $raw, 'wp:category_nicename' ), 'term_id' => self::v( $raw, 'wp:term_id' ), 'name' => self::v( $raw, 'wp:cat_name' ),
                          'slug' => self::v( $raw, 'wp:category_nicename' ), 'parent' => self::v( $raw, 'wp:category_parent' ),
                          'description' => self::v( $raw, 'wp:category_description' ) );
        return array( 'id' => self::v( $raw, 'wp:tag_slug' ), 'term_id' => self::v( $raw, 'wp:term_id' ), 'name' => self::v( $raw, 'wp:tag_name' ),
                      'slug' => self::v( $raw, 'wp:tag_slug' ), 'description' => self::v( $raw, 'wp:tag_description' ) );
    }

    /**
     * Paragraphs from blank lines, as WordPress shows a post written in its classic editor: text between block
     * elements becomes <p>, a single line break <br />. Content that already has paragraphs (the block editor's) stays.
     *
     * @param string $text
     * @return string
     */
    public static function autop( $text )
    {
        $text = str_replace( array( "\r\n", "\r" ), "\n", trim( (string)$text ) );
        if ( $text === '' )
            return '';
        // the block editor's comments are markup for WordPress only
        $text = trim( (string)preg_replace( '/<!--\s*\/?wp:[^>]*-->\n?/', '', $text ) );
        if ( preg_match( '/<p[\s>]/i', $text ) )
            return $text;
        $blocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
        $text = preg_replace( '!(<' . $blocks . '[\s/>])!i', "\n\n$1", $text );
        $text = preg_replace( '!(</' . $blocks . '>)!i', "$1\n\n", $text );
        $out = array();
        foreach ( preg_split( '/\n\s*\n/', $text ) as $chunk )
        {
            $chunk = trim( $chunk );
            if ( $chunk === '' )
                continue;
            if ( preg_match( '!^</?' . $blocks . '[\s/>]!i', $chunk ) )
                $out[] = $chunk;
            else
                $out[] = '<p>' . str_replace( "\n", "<br />\n", $chunk ) . '</p>';
        }
        return implode( "\n", $out );
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }
}
