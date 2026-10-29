<?php
/**
 * File containing the expImportRssSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
            ),
        );
    }

    public static function variants()
    {
        return array(
            'exponential_rss' => array(
                'label' => 'RSS export of another Exponential site',
                'description' => 'The RSS export of a subtree of another Exponential site (its address is rss/feed/<name>): every item '
                               . 'is an article there, with title, link, summary, date and author. The preset brings them in as articles, '
                               . 'so content moves from one installation to another and keeps following it at every run.',
                'group' => 'platform',
                'examples' => array( array( 'label' => 'An RSS feed of two items', 'file' => 'feed.rss' ) ),
            ),
            'podcast' => array(
                'label' => 'Podcast feed',
                'description' => 'The RSS feed of a podcast: every episode is a record with its audio or video file (enclosure, type, length), '
                               . 'duration in seconds, episode and season, cover image and the iTunes fields. The preset imports the episode '
                               . 'file into a file object.',
                'group' => 'media',
                'extensions' => array( 'xml', 'rss' ),
                'examples' => array( array( 'label' => 'A podcast feed of two episodes', 'file' => 'podcast.rss' ) ),
                'preset' => array(
                    'class' => 'file',
                    'match' => array( 'type' => 'remote_id', 'pattern' => 'podcast-{id}' ),
                    'fields' => array(
                        array( 'source' => 'title', 'attribute' => 'name' ),
                        array( 'source' => 'summary', 'attribute' => 'description', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                        array( 'source' => 'enclosure', 'attribute' => 'file', 'transforms' => array( array( 'name' => 'file' ) ), 'datatypes' => array( 'ezbinaryfile', 'ezmedia' ) ),
                    ),
                ),
            ),
            'video_feed' => array(
                'label' => 'YouTube or Vimeo channel feed',
                'description' => 'The feed of a YouTube channel or playlist (youtube.com/feeds/videos.xml?channel_id=...) or of a Vimeo user '
                               . '(vimeo.com/<user>/videos/rss): every video is a record with its ID, embed address, thumbnail, '
                               . 'description and, on YouTube, the view count. The preset stores a video as a link.',
                'group' => 'media',
                'extensions' => array( 'xml', 'rss', 'atom' ),
                'examples' => array( array( 'label' => 'A YouTube channel feed (Atom)', 'file' => 'youtube.xml' ),
                                     array( 'label' => 'A Vimeo user feed (RSS)', 'file' => 'vimeo.rss' ) ),
                'preset' => array(
                    'class' => 'link',
                    'match' => array( 'type' => 'remote_id', 'pattern' => 'video-{video_id}' ),
                    'fields' => array(
                        array( 'source' => 'title', 'attribute' => 'name' ),
                        array( 'source' => 'summary', 'attribute' => 'description', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                        array( 'source' => 'link', 'attribute' => 'location', 'datatypes' => array( 'ezurl' ) ),
                    ),
                ),
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
        $one = function ( $v )
        {
            return is_array( $v ) && array_key_exists( 0, $v ) ? $v[0] : $v;
        };
        // podcasts: the enclosure's type and length, and the iTunes tags
        $enclosureType = $enclosureLength = '';
        $e = isset( $raw['enclosure'] ) ? $one( $raw['enclosure'] ) : null;
        if ( is_array( $e ) )
        {
            $enclosureType = isset( $e['@type'] ) ? (string)$e['@type'] : '';
            $enclosureLength = isset( $e['@length'] ) ? (string)$e['@length'] : '';
        }
        // YouTube (Atom with yt: and media:group) and Vimeo (RSS with media:content)
        $group = isset( $raw['media:group'] ) && is_array( $raw['media:group'] ) ? $one( $raw['media:group'] ) : array();
        $image = '';
        foreach ( array( isset( $raw['itunes:image'] ) ? $one( $raw['itunes:image'] ) : null, isset( $raw['media:thumbnail'] ) ? $one( $raw['media:thumbnail'] ) : null,
                         isset( $group['media:thumbnail'] ) ? $one( $group['media:thumbnail'] ) : null ) as $candidate )
        {
            if ( is_array( $candidate ) && ( isset( $candidate['@href'] ) || isset( $candidate['@url'] ) ) )
            {
                $image = isset( $candidate['@href'] ) ? $candidate['@href'] : $candidate['@url'];
                break;
            }
        }
        $videoID = $first( array( 'yt:videoId' ) );
        $embed = '';
        if ( $videoID !== '' )
            $embed = 'https://www.youtube.com/embed/' . rawurlencode( $videoID );
        elseif ( preg_match( '#^https?://(?:www\.)?vimeo\.com/(?:.*/)?(\d+)(?:[/?\#]|$)#', $link, $v ) )
        {
            $videoID = $v[1];
            $embed = 'https://player.vimeo.com/video/' . $v[1];
        }
        $views = '';
        if ( isset( $group['media:community']['media:statistics']['@views'] ) )
            $views = (string)$group['media:community']['media:statistics']['@views'];
        $mediaDescription = isset( $group['media:description'] ) ? trim( $text( $one( $group['media:description'] ) ) ) : $first( array( 'media:description' ) );
        $summary = $first( array( 'description', 'summary', 'itunes:summary' ) );
        return array(
            'id' => $id !== '' ? $id : $link,
            'title' => $first( array( 'title' ) ),
            'link' => $link,
            'summary' => $summary !== '' ? $summary : $mediaDescription,
            'content' => $first( array( 'content:encoded', 'content' ) ),
            'date' => $first( array( 'pubDate', 'updated', 'published', 'dc:date' ) ),
            'author' => $author !== '' ? $author : $first( array( 'itunes:author' ) ),
            'categories' => $categories,
            'enclosure' => $enclosure,
            'enclosure_type' => $enclosureType,
            'enclosure_length' => $enclosureLength,
            'duration' => self::seconds( $first( array( 'itunes:duration' ) ) ),
            'episode' => $first( array( 'itunes:episode' ) ),
            'season' => $first( array( 'itunes:season' ) ),
            'explicit' => in_array( strtolower( $first( array( 'itunes:explicit' ) ) ), array( 'yes', 'true', 'explicit' ), true ),
            'image' => $image,
            'video_id' => $videoID,
            'channel_id' => $first( array( 'yt:channelId' ) ),
            'embed_url' => $embed,
            'views' => $views,
            'media_description' => $mediaDescription,
            'raw' => $raw,
        );
    }

    /** @return string a duration (3600, 1:02:03, 62:03) in seconds, '' for none */
    public static function seconds( $duration )
    {
        $duration = trim( (string)$duration );
        if ( $duration === '' )
            return '';
        if ( ctype_digit( $duration ) )
            return $duration;
        if ( !preg_match( '/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/', $duration, $m ) )
            return '';
        return (string)( (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] );
    }
}
