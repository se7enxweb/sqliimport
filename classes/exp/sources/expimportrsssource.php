<?php
/**
 * File containing the expImportRssSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * RSS 2.0 and Atom feeds, from a file or a URL (the engine downloads a URL into the run's snapshot first). Every item
 * or entry is one record with the same fields whatever the feed's format, so one mapping fits both:
 *   id (guid or id), title, link, summary (description or summary), content (content:encoded or content), date
 *   (pubDate, updated or published, as given), author, categories (a list), enclosure (the URL of the first
 *   enclosure or media:content, for an image transform), and the raw element as raw.*
 * It replaces the RSS sample handler of 1.x (SQLIRSSImportHandler, which stays for compatibility).
 */
class expImportRssSource extends expImportXmlSource
{
    public static function identifier()
    {
        return 'rss';
    }

    public static function label()
    {
        return 'RSS / Atom feed';
    }

    public static function extensions()
    {
        return array( 'xml', 'rss', 'atom' );
    }

    public static function description()
    {
        return 'RSS 2.0 and Atom feeds of a site, a blog or a news service, from an address or a file. Every item is a '
             . 'record with id, title, link, summary, content, date, author, categories and enclosure.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'An RSS feed of two items', 'file' => 'feed.rss' ),
                      array( 'label' => 'An Atom feed', 'file' => 'feed.atom' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'feed-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title', 'transforms' => array( array( 'name' => 'strip_tags' ), array( 'name' => 'trim' ) ) ),
                array( 'source' => 'summary', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'content', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names an item; id is the guid of RSS and the id of Atom' ),
        );
    }

    /** @return string[] item for RSS, entry for Atom (looked up in the file's first bytes) */
    protected function recordPath()
    {
        $head = (string)@file_get_contents( $this->file(), false, null, 0, 4096 );
        $atom = preg_match( '/<(\w+:)?feed\b/', $head ) && !preg_match( '/<rss\b/', $head );
        $this->detected['format'] = $atom ? 'atom' : 'rss';
        return array( $atom ? 'entry' : 'item' );
    }

    public function records( array $cursor = array() )
    {
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        foreach ( parent::records( $cursor ) as $record )
        {
            $data = self::normalise( $record->data );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, $record->cursor );
            $index++;
        }
    }

    /**
     * An RSS item or Atom entry as the common fields.
     * @param array $raw
     * @return array
     */
    public static function normalise( array $raw )
    {
        $text = function ( $v )
        {
            if ( is_array( $v ) )
                return isset( $v['#text'] ) ? (string)$v['#text'] : '';
            return (string)$v;
        };
        $first = function ( array $keys ) use ( $raw, $text )
        {
            foreach ( $keys as $key )
                if ( isset( $raw[$key] ) )
                {
                    $v = $raw[$key];
                    if ( is_array( $v ) && array_key_exists( 0, $v ) )
                        $v = $v[0];
                    $t = trim( $text( $v ) );
                    if ( $t !== '' )
                        return $t;
                }
            return '';
        };
        $link = $first( array( 'link' ) );
        if ( $link === '' && isset( $raw['link'] ) )
        {
            // Atom: <link href="..." rel="alternate"/>, possibly several
            $links = is_array( $raw['link'] ) && array_key_exists( 0, $raw['link'] ) ? $raw['link'] : array( $raw['link'] );
            foreach ( $links as $l )
                if ( is_array( $l ) && isset( $l['@href'] ) && ( !isset( $l['@rel'] ) || $l['@rel'] === 'alternate' ) )
                {
                    $link = $l['@href'];
                    break;
                }
        }
        $author = $first( array( 'author', 'dc:creator' ) );
        if ( isset( $raw['author'] ) && is_array( $raw['author'] ) && isset( $raw['author']['name'] ) )
            $author = $text( $raw['author']['name'] );
        $categories = array();
        if ( isset( $raw['category'] ) )
        {
            $list = is_array( $raw['category'] ) && array_key_exists( 0, $raw['category'] ) ? $raw['category'] : array( $raw['category'] );
            foreach ( $list as $c )
            {
                $name = is_array( $c ) ? ( isset( $c['@term'] ) ? $c['@term'] : $text( $c ) ) : (string)$c;
                if ( trim( $name ) !== '' )
                    $categories[] = trim( $name );
            }
        }
        $enclosure = '';
        foreach ( array( 'enclosure', 'media:content', 'media:thumbnail' ) as $key )
        {
            if ( !isset( $raw[$key] ) )
                continue;
            $e = is_array( $raw[$key] ) && array_key_exists( 0, $raw[$key] ) ? $raw[$key][0] : $raw[$key];
            if ( is_array( $e ) && ( isset( $e['@url'] ) || isset( $e['@href'] ) ) )
            {
                $enclosure = isset( $e['@url'] ) ? $e['@url'] : $e['@href'];
                break;
            }
        }
        $id = $first( array( 'guid', 'id' ) );
        return array(
            'id' => $id !== '' ? $id : $link,
            'title' => $first( array( 'title' ) ),
            'link' => $link,
            'summary' => $first( array( 'description', 'summary' ) ),
            'content' => $first( array( 'content:encoded', 'content' ) ),
            'date' => $first( array( 'pubDate', 'updated', 'published', 'dc:date' ) ),
            'author' => $author,
            'categories' => $categories,
            'enclosure' => $enclosure,
            'raw' => $raw,
        );
    }
}
