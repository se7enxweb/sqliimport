<?php
/**
 * File containing the expImportYouTubeSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The videos of a YouTube channel, of a playlist or of a search through the YouTube Data API v3: one record is one
 * video with its details (title, description, tags, duration, thumbnails, statistics, privacy).
 *
 * The source reads its data itself (origin "remote"): once per run fetch() lists the video IDs page by page
 * (pageToken, at most [YouTubeSettings] MaxPages pages, a search at most MaxSearchPages) and asks videos.list for
 * the details of each page's videos (50 at most per request), then writes the records into the run's snapshot, one
 * JSON object per line.
 *
 *   channel   channels.list (by the channel ID UC... or the @handle) gives the playlist of its uploads
 *             (contentDetails.relatedPlaylists.uploads), read like a playlist
 *   playlist  playlistItems.list
 *   search    search.list with q, channelId, publishedAfter, publishedBefore, order and type=video
 *
 * Only reading is possible: the source sends GET requests to https://www.googleapis.com/youtube/v3/ and to the four
 * methods channels, playlistItems, search and videos only. The API key is the name of a secret (expImportSecrets),
 * sent in the header X-goog-api-key (never in an address) and masked in every message; restrict the key to the
 * YouTube Data API v3 in the Google Cloud console. A service account does not help: the Data API reads public
 * videos with an API key, and private ones only with the channel owner's OAuth consent, which this source does not ask.
 *
 * Quota: a project has 10,000 units a day by default. channels.list, playlistItems.list and videos.list cost 1 unit
 * per request, search.list 100: a channel of 500 videos costs about 21 units a run, a search of 5 pages 505.
 */
class expImportYouTubeSource extends expImportJsonSource
{
    /** The address of the API (fixed: no other host is ever asked). */
    const API = 'https://www.googleapis.com/youtube/v3/';

    /** The methods of the API the source calls, all with GET, and their cost in quota units. */
    const METHODS = array( 'channels' => 1, 'playlistItems' => 1, 'search' => 100, 'videos' => 1 );

    /** The least time between two requests, in seconds. */
    const INTERVAL = 0.1;

    /** The wait after a 429 without Retry-After, in seconds. */
    const BACKOFF = 10.0;

    /** The fields of a record, in their order. */
    const FIELDS = array( 'id', 'url', 'embed_url', 'title', 'description', 'published', 'channel_id', 'channel_title', 'tags', 'category_id',
                          'duration_seconds', 'duration', 'thumbnail', 'thumbnails', 'view_count', 'like_count', 'comment_count', 'privacy',
                          'live_broadcast_content' );

    /** @var int the quota units the last fetch() or test() used */
    public static $units = 0;

    public static function identifier()
    {
        return 'youtube';
    }

    public static function label()
    {
        return 'YouTube channel, playlist or search (Data API)';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'media';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function description()
    {
        return 'The videos of a YouTube channel (by its ID or @handle), of a playlist or of a search through the YouTube Data '
             . 'API v3: one record is one video with title, description, tags, duration, the largest thumbnail, view, like and '
             . 'comment counts, privacy and the addresses to watch and to embed. Read-only (GET only); the API key is the name '
             . 'of a secret in the settings. A search costs 100 quota units per page of the 10,000 a day a project has.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three videos of a channel (what a run writes): durations, thumbnails, statistics and tags',
                   'file' => 'youtube-videos.ndjson',
                   'options' => array( 'mode' => 'channel', 'channel' => '@examplechannel', 'api_key' => 'youtube_api_key' ) ),
            array( 'label' => 'The uploads of the channel @examplechannel, the API key as the secret youtube_api_key',
                   'options' => array( 'mode' => 'channel', 'channel' => '@examplechannel', 'api_key' => 'youtube_api_key' ) ),
            array( 'label' => 'The videos about tea of a channel published in 2026, newest first (100 quota units per page)',
                   'options' => array( 'mode' => 'search', 'query' => 'tea', 'channel' => 'UCexampleChannel000000aa', 'published_after' => '2026-01-01',
                                       'order' => 'date', 'api_key' => 'youtube_api_key' ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function preset()
    {
        return array(
            'class' => 'link',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'youtube-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'name', 'transforms' => array( array( 'name' => 'trim' ) ) ),
                array( 'source' => 'description', 'attribute' => 'description', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'url', 'attribute' => 'location', 'datatypes' => array( 'ezurl' ) ),
                array( 'source' => 'thumbnail', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'image' ) ), 'datatypes' => array( 'ezimage' ) ),
                array( 'source' => 'published', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'mode' => array( 'type' => 'enum', 'label' => 'Read', 'default' => 'channel',
                             'values' => array( 'channel' => 'The uploads of a channel', 'playlist' => 'A playlist', 'search' => 'A search (100 quota units per page)' ),
                             'help' => 'channel: every video the channel uploaded; playlist: the videos of a playlist; search: the videos a search finds' ),
            'channel' => array( 'type' => 'string', 'label' => 'Channel', 'max' => 255,
                                'help' => 'Channel and search: the channel ID (UC...) or @handle, or its address (youtube.com/@name, youtube.com/channel/UC...); a search without it searches all of YouTube' ),
            'playlist_id' => array( 'type' => 'string', 'label' => 'Playlist', 'max' => 255,
                                    'help' => 'Playlist: the playlist ID (PL...) or its address (youtube.com/playlist?list=...)' ),
            'query' => array( 'type' => 'string', 'label' => 'Search terms', 'max' => 500,
                              'help' => 'Search: the terms (q); | means or, - excludes a term' ),
            'published_after' => array( 'type' => 'string', 'label' => 'Published after', 'max' => 40, 'pattern' => self::DATE_PATTERN,
                                        'help' => 'Search: only videos published at or after this date (2026-01-31 or 2026-01-31T12:00:00Z)' ),
            'published_before' => array( 'type' => 'string', 'label' => 'Published before', 'max' => 40, 'pattern' => self::DATE_PATTERN,
                                         'help' => 'Search: only videos published before this date' ),
            'order' => array( 'type' => 'enum', 'label' => 'Order', 'default' => 'date',
                              'values' => array( 'date' => 'Newest first', 'rating' => 'Rating', 'relevance' => 'Relevance', 'title' => 'Title', 'viewCount' => 'Views' ),
                              'help' => 'Search: the order of the results' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 50, 'min' => 1, 'max' => 50,
                                  'help' => 'Videos per request (YouTube answers at most 50)' ),
            'max_records' => array( 'type' => 'int', 'label' => 'Most videos', 'default' => 0, 'min' => 0, 'max' => 100000,
                                    'help' => 'The most videos one run reads; 0: all (within [YouTubeSettings] MaxPages)' ),
            'api_key' => array( 'type' => 'secret', 'label' => 'API key (name of a secret)', 'required' => true,
                                'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE holding an API key restricted to the YouTube Data API v3, never the key' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record; id is the video ID' ),
        );
    }

    /** A date (2026-01-31) or a date and time (2026-01-31T12:00:00Z, +02:00). */
    const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * Reads the videos into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most records (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $ref = isset( $options['api_key'] ) ? trim( (string)$options['api_key'] ) : '';
        $key = '';
        self::$units = 0;
        $limit = (int)$limit;
        $most = isset( $options['max_records'] ) ? (int)$options['max_records'] : 0;
        if ( $most > 0 && ( $limit <= 0 || $most < $limit ) )
            $limit = $most;
        try
        {
            $key = expImportSecrets::required( $ref, 'YouTube API key' );
            expImportApiHttp::writeSnapshot( self::videos( $options, $key, $limit ), $target, $limit, 'YouTube' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportApiHttp::mask( $e->getMessage(), $ref, $key ) );
        }
    }

    /**
     * The editor's "Test connection": one request of 1 quota unit (channels.list for a channel, playlistItems.list for
     * a playlist; for a search channels.list when it names a channel, else videos.list of one popular video, so a
     * test never costs the 100 units of a search). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $ref = isset( $options['api_key'] ) ? trim( (string)$options['api_key'] ) : '';
        $key = '';
        self::$units = 0;
        $mode = self::mode( $options );
        try
        {
            $key = expImportSecrets::required( $ref, 'YouTube API key' );
            $channel = isset( $options['channel'] ) ? trim( (string)$options['channel'] ) : '';
            $rows = 0;
            if ( $mode === 'playlist' )
            {
                $answer = self::call( 'playlistItems', array( 'part' => 'id', 'playlistId' => self::playlistId( $options ), 'maxResults' => 1 ), $key );
                $rows = isset( $answer['pageInfo']['totalResults'] ) ? (int)$answer['pageInfo']['totalResults'] : 0;
                $what = 'the playlist has ' . $rows . ' videos';
            }
            elseif ( $mode === 'channel' || $channel !== '' )
            {
                $found = self::channel( $channel, $key );
                $rows = isset( $found['statistics']['videoCount'] ) ? (int)$found['statistics']['videoCount'] : 0;
                $title = isset( $found['snippet']['title'] ) ? (string)$found['snippet']['title'] : '';
                $what = 'the channel ' . ( $title !== '' ? '"' . $title . '" ' : '' ) . 'has ' . $rows . ' videos';
            }
            else
            {
                self::call( 'videos', array( 'part' => 'id', 'chart' => 'mostPopular', 'maxResults' => 1 ), $key );
                $what = 'the API key works';
            }
            $cost = $mode === 'search' ? '; a run costs 100 units per page of the search and 1 per 50 videos' : '; a run costs 1 unit per page of 50 videos and 1 per 50 videos';
            return array( 'ok' => true, 'columns' => self::FIELDS, 'rows' => $rows, 'driver' => 'api',
                          'message' => 'connected, ' . $what . ' (this test used ' . self::$units . ' quota unit' . $cost . ')' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'api',
                          'message' => expImportApiHttp::mask( $e->getMessage(), $ref, $key ) );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'api' => expImportApiHttp::httpsDriver( 'YouTube Data API v3 over HTTPS' ) );
    }

    /** @return string channel, playlist or search */
    protected static function mode( array $options )
    {
        $mode = isset( $options['mode'] ) ? (string)$options['mode'] : 'channel';
        return in_array( $mode, array( 'channel', 'playlist', 'search' ), true ) ? $mode : 'channel';
    }

    /**
     * The address of a request: one of the four methods, GET parameters only.
     *
     * @param string $method channels, playlistItems, search or videos
     * @param array $params name => value ('' and null left out)
     * @return string
     * @throws expImportSourceException for any other method
     */
    public static function url( $method, array $params )
    {
        if ( !isset( self::METHODS[$method] ) )
            throw new expImportSourceException( "YouTube: the method $method is not one the source may call (only channels, playlistItems, search and videos, read-only)" );
        $query = array();
        foreach ( $params as $name => $value )
            if ( $value !== null && (string)$value !== '' )
                $query[] = rawurlencode( (string)$name ) . '=' . rawurlencode( (string)$value );
        return self::API . $method . ( $query ? '?' . implode( '&', $query ) : '' );
    }

    /**
     * One GET request, its quota units counted.
     *
     * @return array the decoded answer
     * @throws expImportSourceException
     */
    protected static function call( $method, array $params, $key )
    {
        $url = self::url( $method, $params );
        self::$units += self::METHODS[$method];
        try
        {
            return expImportApiHttp::json( 'YouTube', 'GET', $url, array( 'X-goog-api-key' => $key ), null, self::INTERVAL, self::BACKOFF );
        }
        catch ( expImportSourceException $e )
        {
            $text = $e->getMessage();
            if ( stripos( $text, 'quota' ) !== false )
                $text .= ' (the daily quota of the Google Cloud project is used up; it is reset at midnight Pacific Time)';
            throw new expImportSourceException( $text, $e->getCode() );
        }
    }

    /**
     * The channel ID (UC...) or handle (@name) of an option, also from an address.
     *
     * @param string $channel
     * @return array array( 'id' => 'UC...' ) or array( 'forHandle' => '@name' )
     * @throws expImportSourceException
     */
    public static function channelParam( $channel )
    {
        $channel = trim( (string)$channel );
        if ( preg_match( '#^(?:https?://)?(?:www\.|m\.)?youtube\.com/(?:channel/(UC[A-Za-z0-9_-]{22})|(@[A-Za-z0-9._-]{3,100}))(?:[/?\#]|$)#i', $channel, $m ) )
            $channel = $m[1] !== '' ? $m[1] : $m[2];
        if ( preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $channel ) )
            return array( 'id' => $channel );
        if ( preg_match( '/^@?[A-Za-z0-9._-]{3,100}$/', $channel ) && $channel !== '' )
            return array( 'forHandle' => $channel[0] === '@' ? $channel : '@' . $channel );
        throw new expImportSourceException( 'YouTube: the channel is missing or not a channel ID (UC...) or @handle' );
    }

    /**
     * The playlist ID of the options, also from an address.
     *
     * @throws expImportSourceException
     */
    public static function playlistId( array $options )
    {
        $list = isset( $options['playlist_id'] ) ? trim( (string)$options['playlist_id'] ) : '';
        if ( preg_match( '#[?&]list=([A-Za-z0-9_-]+)#', $list, $m ) )
            $list = $m[1];
        if ( !preg_match( '/^[A-Za-z0-9_-]{2,100}$/', $list ) )
            throw new expImportSourceException( 'YouTube: the playlist ID is missing or not valid' );
        return $list;
    }

    /**
     * A channel's resource (snippet, contentDetails, statistics).
     *
     * @throws expImportSourceException when there is no such channel
     */
    protected static function channel( $channel, $key )
    {
        $answer = self::call( 'channels', array( 'part' => 'snippet,contentDetails,statistics' ) + self::channelParam( $channel ), $key );
        if ( empty( $answer['items'][0] ) || !is_array( $answer['items'][0] ) )
            throw new expImportSourceException( 'YouTube: there is no channel ' . trim( (string)$channel ) );
        return $answer['items'][0];
    }

    /** @return int the most pages of the listing (MaxPages, a search MaxSearchPages) */
    protected static function maxPages( $mode )
    {
        if ( $mode === 'search' )
            return max( 1, (int)expImportPlatformHttp::setting( 'YouTubeSettings', 'MaxSearchPages', 5 ) );
        return max( 1, (int)expImportPlatformHttp::setting( 'YouTubeSettings', 'MaxPages', 100 ) );
    }

    /**
     * The records, page by page: a page of video IDs, then their details.
     *
     * @param array $options
     * @param string $key
     * @param int $limit 0: all
     * @return Generator array
     * @throws expImportSourceException
     */
    protected static function videos( array $options, $key, $limit )
    {
        $mode = self::mode( $options );
        $size = max( 1, min( 50, isset( $options['page_size'] ) ? (int)$options['page_size'] : 50 ) );
        if ( $limit > 0 && $limit < $size )
            $size = $limit;
        $channel = isset( $options['channel'] ) ? trim( (string)$options['channel'] ) : '';
        if ( $mode === 'search' )
        {
            $method = 'search';
            $params = array( 'part' => 'id', 'type' => 'video', 'maxResults' => $size,
                             'q' => isset( $options['query'] ) ? trim( (string)$options['query'] ) : '',
                             'order' => isset( $options['order'] ) && in_array( $options['order'], array( 'date', 'rating', 'relevance', 'title', 'viewCount' ), true ) ? $options['order'] : 'date',
                             'publishedAfter' => self::rfc3339( isset( $options['published_after'] ) ? $options['published_after'] : '' ),
                             'publishedBefore' => self::rfc3339( isset( $options['published_before'] ) ? $options['published_before'] : '' ) );
            if ( $channel !== '' )
            {
                $param = self::channelParam( $channel );
                $params['channelId'] = isset( $param['id'] ) ? $param['id'] : (string)self::channel( $channel, $key )['id'];
            }
            if ( $params['q'] === '' && $channel === '' )
                throw new expImportSourceException( 'YouTube: a search needs search terms or a channel' );
        }
        else
        {
            $method = 'playlistItems';
            if ( $mode === 'channel' )
            {
                $found = self::channel( $channel, $key );
                $uploads = isset( $found['contentDetails']['relatedPlaylists']['uploads'] ) ? (string)$found['contentDetails']['relatedPlaylists']['uploads'] : '';
                if ( $uploads === '' )
                    throw new expImportSourceException( 'YouTube: the channel has no playlist of uploads' );
                $playlist = $uploads;
            }
            else
                $playlist = self::playlistId( $options );
            $params = array( 'part' => 'contentDetails', 'playlistId' => $playlist, 'maxResults' => $size );
        }
        $maxPages = self::maxPages( $mode );
        $token = '';
        $n = 0;
        $seen = array();
        for ( $page = 1; $page <= $maxPages; $page++ )
        {
            $answer = self::call( $method, $params + array( 'pageToken' => $token ), $key );
            if ( !isset( $answer['items'] ) || !is_array( $answer['items'] ) )
                throw new expImportSourceException( 'YouTube: the answer has no list of items' );
            $ids = array();
            foreach ( $answer['items'] as $item )
            {
                $id = self::itemVideoId( $item );
                if ( $id !== '' && !isset( $seen[$id] ) )
                {
                    $seen[$id] = true;
                    $ids[] = $id;
                }
            }
            foreach ( array_chunk( $ids, 50 ) as $chunk )
            {
                $details = self::call( 'videos', array( 'part' => 'snippet,contentDetails,statistics,status', 'id' => implode( ',', $chunk ), 'maxResults' => 50 ), $key );
                $byId = array();
                foreach ( isset( $details['items'] ) && is_array( $details['items'] ) ? $details['items'] : array() as $video )
                    if ( is_array( $video ) && isset( $video['id'] ) )
                        $byId[(string)$video['id']] = $video;
                // the listing's order; a private or deleted video of a playlist has no details and is left out
                foreach ( $chunk as $id )
                {
                    if ( !isset( $byId[$id] ) )
                        continue;
                    yield self::record( $byId[$id] );
                    if ( $limit > 0 && ++$n >= $limit )
                        return;
                }
            }
            $token = isset( $answer['nextPageToken'] ) && is_string( $answer['nextPageToken'] ) ? $answer['nextPageToken'] : '';
            if ( $token === '' )
                return;
        }
        $setting = $mode === 'search' ? 'MaxSearchPages' : 'MaxPages';
        throw new expImportSourceException( "YouTube: there are more pages than [YouTubeSettings] $setting ($maxPages) allows; set Most videos, or raise the setting (mind the quota)" );
    }

    /** @return string the video ID of an item of playlistItems.list or search.list, '' for none */
    protected static function itemVideoId( $item )
    {
        if ( !is_array( $item ) )
            return '';
        foreach ( array( array( 'contentDetails', 'videoId' ), array( 'snippet', 'resourceId', 'videoId' ), array( 'id', 'videoId' ) ) as $path )
        {
            $v = $item;
            foreach ( $path as $step )
                $v = is_array( $v ) && isset( $v[$step] ) ? $v[$step] : null;
            if ( is_string( $v ) && preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $v ) )
                return $v;
        }
        return '';
    }

    /** @return string a date of the options as RFC 3339 (what the API wants), '' for none */
    public static function rfc3339( $date )
    {
        $date = trim( (string)$date );
        if ( $date === '' )
            return '';
        if ( !preg_match( self::DATE_PATTERN, $date ) )
            throw new expImportSourceException( 'YouTube: the date ' . $date . ' is not valid (2026-01-31 or 2026-01-31T12:00:00Z)' );
        $time = strtotime( strlen( $date ) === 10 ? $date . 'T00:00:00Z' : $date );
        if ( $time === false )
            throw new expImportSourceException( 'YouTube: the date ' . $date . ' is not valid' );
        return gmdate( 'Y-m-d\TH:i:s\Z', $time );
    }

    /**
     * A video resource of videos.list as a record.
     *
     * @param array $video id, snippet, contentDetails, statistics, status
     * @return array
     */
    public static function record( array $video )
    {
        $id = isset( $video['id'] ) ? (string)$video['id'] : '';
        $s = isset( $video['snippet'] ) && is_array( $video['snippet'] ) ? $video['snippet'] : array();
        $d = isset( $video['contentDetails'] ) && is_array( $video['contentDetails'] ) ? $video['contentDetails'] : array();
        $st = isset( $video['statistics'] ) && is_array( $video['statistics'] ) ? $video['statistics'] : array();
        $status = isset( $video['status'] ) && is_array( $video['status'] ) ? $video['status'] : array();
        $text = function ( array $a, $k )
        {
            return isset( $a[$k] ) && is_scalar( $a[$k] ) ? (string)$a[$k] : '';
        };
        $count = function ( $k ) use ( $st )
        {
            // a hidden count (likes, comments switched off) is missing from the answer: null, not 0
            return isset( $st[$k] ) && is_numeric( $st[$k] ) ? (int)$st[$k] : null;
        };
        $thumbnails = isset( $s['thumbnails'] ) && is_array( $s['thumbnails'] ) ? $s['thumbnails'] : array();
        $seconds = self::durationSeconds( $text( $d, 'duration' ) );
        return array(
            'id' => $id,
            'url' => 'https://www.youtube.com/watch?v=' . rawurlencode( $id ),
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ),
            'title' => $text( $s, 'title' ),
            'description' => $text( $s, 'description' ),
            'published' => $text( $s, 'publishedAt' ),
            'channel_id' => $text( $s, 'channelId' ),
            'channel_title' => $text( $s, 'channelTitle' ),
            'tags' => isset( $s['tags'] ) && is_array( $s['tags'] ) ? array_values( array_map( 'strval', array_filter( $s['tags'], 'is_scalar' ) ) ) : array(),
            'category_id' => $text( $s, 'categoryId' ),
            'duration_seconds' => $seconds,
            'duration' => $seconds === null ? '' : self::clock( $seconds ),
            'thumbnail' => self::largestThumbnail( $thumbnails ),
            'thumbnails' => $thumbnails ? (string)json_encode( $thumbnails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '',
            'view_count' => $count( 'viewCount' ),
            'like_count' => $count( 'likeCount' ),
            'comment_count' => $count( 'commentCount' ),
            'privacy' => $text( $status, 'privacyStatus' ),
            'live_broadcast_content' => $text( $s, 'liveBroadcastContent' ),
        );
    }

    /**
     * An ISO 8601 duration of the API (PT1H2M3S, P1DT2H, P0D) in seconds.
     *
     * @param string $duration
     * @return int|null null when there is none or it is not a duration
     */
    public static function durationSeconds( $duration )
    {
        if ( !preg_match( '/^P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?)?$/', trim( (string)$duration ), $m ) || trim( (string)$duration ) === 'P' )
            return null;
        $part = function ( $i ) use ( $m )
        {
            return isset( $m[$i] ) && $m[$i] !== '' ? (int)$m[$i] : 0;
        };
        return $part( 1 ) * 604800 + $part( 2 ) * 86400 + $part( 3 ) * 3600 + $part( 4 ) * 60 + $part( 5 );
    }

    /** @return string seconds as H:MM:SS (3723 is 1:02:03, 245 is 0:04:05) */
    public static function clock( $seconds )
    {
        $seconds = max( 0, (int)$seconds );
        return sprintf( '%d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
    }

    /**
     * The address of the largest thumbnail: maxres, standard, high, medium, default (by its width when given).
     *
     * @param array $thumbnails name => array( url, width, height )
     * @return string '' for none
     */
    public static function largestThumbnail( array $thumbnails )
    {
        $best = '';
        $bestWidth = -1;
        $rank = array( 'maxres' => 5, 'standard' => 4, 'high' => 3, 'medium' => 2, 'default' => 1 );
        foreach ( $thumbnails as $name => $t )
        {
            if ( !is_array( $t ) || !isset( $t['url'] ) || !is_string( $t['url'] ) )
                continue;
            $width = isset( $t['width'] ) ? (int)$t['width'] * 10 : ( isset( $rank[$name] ) ? $rank[$name] : 0 );
            if ( $width > $bestWidth )
            {
                $best = $t['url'];
                $bestWidth = $width;
            }
        }
        return $best;
    }
}
