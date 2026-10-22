<?php
/**
 * File containing the expImportYouTubePageSource class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The videos listed on a youtube.com page (a channel's /videos page or a playlist page), read from the page's
 * ytInitialData without an API key. OPT-IN: off unless sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled;
 * while it is off the format is disabled in the catalogue, and fetch() and test() refuse.
 *
 * Reading youtube.com pages with a program is against YouTube's Terms of Service, and the source breaks whenever
 * YouTube changes its pages (the data of a page is not a published interface). Prefer the Data API (youtube) or the
 * channel feed (video_feed).
 *
 * The page is read once per run through expImportCrawler: robots.txt of www.youtube.com is obeyed, the address
 * checks and size limits of expImportSourceFiles apply, no cookie and no credential is sent, and no script is run:
 * the JSON object assigned to ytInitialData is cut out of the HTML text and decoded. Only the first batch of videos a
 * page holds (about 30 on a channel, 100 on a playlist) is read: more needs YouTube's internal API (POST requests),
 * which this source never calls. The records have the fields of the youtube source as far as the page gives them;
 * published is the page's relative text (published_text, "3 days ago"), likes, comments, tags and the category are
 * not on the page (null or empty).
 */
class expImportYouTubePageSource extends expImportJsonSource
{
    /** The hosts the source reads; an address on another host is refused. */
    const HOSTS = array( 'www.youtube.com', 'youtube.com', 'm.youtube.com' );

    /** The warning shown in the catalogue, the editor and the documentation. */
    const NOTICE = 'Reading youtube.com pages is against YouTube\'s Terms of Service and may break whenever YouTube changes its '
                 . 'pages. Use it only where you may, and prefer the YouTube Data API (youtube) or the channel feed (video_feed).';

    /** @var callable|null makes the crawler (tests): function ( string $target ): expImportCrawler */
    public static $crawlerFactory = null;

    public static function identifier()
    {
        return 'youtube_page';
    }

    public static function label()
    {
        return 'YouTube channel or playlist page (scraper, opt-in)';
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
        return 'The videos listed on a youtube.com channel page (/videos) or playlist page, read from the page itself without '
             . 'an API key: ID, title, duration, thumbnail, view count and the addresses to watch and to embed. Only the first '
             . 'batch of videos of a page (about 30). Off unless sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled. '
             . self::NOTICE;
    }

    /** @return string the warning the catalogue and the editor show with the format */
    public static function notice()
    {
        return self::NOTICE;
    }

    /**
     * Whether the format may be used on this site.
     *
     * @return string '' when it may, else why not (the catalogue shows the format as disabled with it)
     */
    public static function availability()
    {
        $allow = strtolower( trim( (string)expImportPlatformHttp::setting( 'YouTubeSettings', 'AllowPageScraper', 'disabled' ) ) );
        if ( in_array( $allow, array( 'enabled', 'true', '1', 'yes' ), true ) )
            return '';
        return 'Switched off: reading youtube.com pages is against YouTube\'s Terms of Service. An administrator may switch it on '
             . 'with sqliimport.ini [YouTubeSettings] AllowPageScraper=enabled.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three videos (what a run writes; the scraper gives fewer fields than the API)',
                   'file' => 'youtube-page-videos.ndjson',
                   'options' => array( 'url' => 'https://www.youtube.com/@examplechannel/videos' ) ),
            array( 'label' => 'The videos on the page of the channel @examplechannel',
                   'options' => array( 'url' => 'https://www.youtube.com/@examplechannel/videos' ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function preset()
    {
        return expImportYouTubeSource::preset();
    }

    public static function schema()
    {
        return array(
            'url' => array( 'type' => 'url', 'label' => 'Page', 'required' => true, 'max' => 500,
                            'help' => 'A channel\'s videos page (https://www.youtube.com/@name/videos, /channel/UC.../videos) or a playlist (https://www.youtube.com/playlist?list=...). ' . self::NOTICE ),
            'max_records' => array( 'type' => 'int', 'label' => 'Most videos', 'default' => 0, 'min' => 0, 'max' => 1000,
                                    'help' => 'The most videos one run reads; 0: all the page lists' ),
            'delay' => array( 'type' => 'float', 'label' => 'Pause (seconds)', 'default' => 2.0, 'min' => 0, 'max' => 60,
                              'help' => 'The pause before reading the page (a longer Crawl-delay of robots.txt wins)' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record; id is the video ID' ),
        );
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /**
     * The address to read: a youtube.com channel page (its /videos tab) or a playlist page, on https://www.youtube.com.
     *
     * @param string $url
     * @return string
     * @throws expImportSourceException
     */
    public static function pageUrl( $url )
    {
        $url = trim( (string)$url );
        $p = parse_url( $url );
        if ( !$p || !isset( $p['host'] ) || !in_array( strtolower( $p['host'] ), self::HOSTS, true )
             || !in_array( strtolower( isset( $p['scheme'] ) ? $p['scheme'] : '' ), array( 'http', 'https' ), true ) || isset( $p['user'] ) || isset( $p['port'] ) )
            throw new expImportSourceException( 'YouTube page: only addresses on https://www.youtube.com are read' );
        $path = isset( $p['path'] ) ? rtrim( $p['path'], '/' ) : '';
        if ( $path === '/playlist' )
        {
            parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );
            if ( !isset( $q['list'] ) || !is_string( $q['list'] ) || !preg_match( '/^[A-Za-z0-9_-]{2,100}$/', $q['list'] ) )
                throw new expImportSourceException( 'YouTube page: the playlist address has no valid list=' );
            return 'https://www.youtube.com/playlist?list=' . $q['list'];
        }
        if ( preg_match( '#^/(@[A-Za-z0-9._-]{3,100}|channel/UC[A-Za-z0-9_-]{22}|c/[A-Za-z0-9._-]{1,100}|user/[A-Za-z0-9._-]{1,100})(?:/(videos|streams|shorts|featured))?$#', $path, $m ) )
            return 'https://www.youtube.com/' . $m[1] . '/' . ( isset( $m[2] ) && in_array( $m[2], array( 'streams', 'shorts' ), true ) ? $m[2] : 'videos' );
        throw new expImportSourceException( 'YouTube page: the address is not a channel page (/@name/videos, /channel/UC.../videos) or a playlist (/playlist?list=...)' );
    }

    /** @return expImportCrawler the crawler of a run, its pages kept next to the snapshot */
    protected static function crawler( $target )
    {
        if ( self::$crawlerFactory )
            return call_user_func( self::$crawlerFactory, (string)$target );
        return expImportCrawler::fromSettings( dirname( (string)$target ) );
    }

    /**
     * Reads the page into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most records (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $why = self::availability();
        if ( $why !== '' )
            throw new expImportSourceException( 'YouTube page: ' . $why );
        $limit = (int)$limit;
        $most = isset( $options['max_records'] ) ? (int)$options['max_records'] : 0;
        if ( $most > 0 && ( $limit <= 0 || $most < $limit ) )
            $limit = $most;
        $records = self::read( $options, $target );
        expImportApiHttp::writeSnapshot( $records, $target, $limit, 'YouTube page' );
    }

    /**
     * The editor's "Test connection": reads the page and reports how many videos it lists.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $why = self::availability();
        if ( $why !== '' )
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'page', 'message' => $why );
        try
        {
            $records = self::read( $options, '' );
            return array( 'ok' => true, 'columns' => $records ? array_keys( $records[0] ) : array(), 'rows' => count( $records ), 'driver' => 'page',
                          'message' => 'read, the page lists ' . count( $records ) . ' videos. ' . self::NOTICE );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'page', 'message' => $e->getMessage() );
        }
    }

    /** @return array id => driver row (label, available, via, worker, worker_php, needs) */
    public static function drivers()
    {
        return array( 'page' => expImportApiHttp::httpsDriver( 'youtube.com pages over HTTPS (robots.txt obeyed)' ) );
    }

    /**
     * The videos of the page.
     *
     * @param array $options
     * @param string $target the snapshot ('' for a test: the crawler keeps its pages in the temporary directory)
     * @return array[]
     * @throws expImportSourceException
     */
    protected static function read( array $options, $target )
    {
        $url = self::pageUrl( isset( $options['url'] ) ? $options['url'] : '' );
        $crawler = self::crawler( $target !== '' ? $target : sys_get_temp_dir() . '/youtube-page' );
        $delay = isset( $options['delay'] ) ? max( 0.0, (float)$options['delay'] ) : 2.0;
        $response = $crawler->get( $url, $delay );
        if ( !in_array( strtolower( (string)parse_url( $response['url'], PHP_URL_HOST ) ), self::HOSTS, true ) )
            throw new expImportSourceException( 'YouTube page: the page was sent on to another site (' . expImportPlatformHttp::shown( $response['url'] )
                                                . '), probably a consent page; nothing is read' );
        if ( $response['status'] < 200 || $response['status'] >= 300 )
            throw new expImportSourceException( 'YouTube page: ' . expImportPlatformHttp::shown( $url ) . ' answered ' . $response['status'] );
        $data = self::initialData( $response['body'] );
        if ( $data === null )
            throw new expImportSourceException( 'YouTube page: the page has no ytInitialData (YouTube may have changed its pages, or shown a consent page)' );
        return self::pageRecords( $data );
    }

    /**
     * The JSON object assigned to ytInitialData in a page's scripts, decoded; the object is cut out by counting braces
     * outside strings, nothing of the page is run.
     *
     * @param string $html
     * @return array|null null when the page has none
     */
    public static function initialData( $html )
    {
        $html = (string)$html;
        if ( !preg_match( '/(?:var\s+ytInitialData|window\s*\[\s*["\']ytInitialData["\']\s*\]|ytInitialData)\s*=\s*\{/', $html, $m, PREG_OFFSET_CAPTURE ) )
            return null;
        $start = $m[0][1] + strlen( $m[0][0] ) - 1;
        $depth = 0;
        $inString = false;
        $length = strlen( $html );
        for ( $i = $start; $i < $length; $i++ )
        {
            $c = $html[$i];
            if ( $inString )
            {
                if ( $c === '\\' )
                    $i++;
                elseif ( $c === '"' )
                    $inString = false;
                continue;
            }
            if ( $c === '"' )
                $inString = true;
            elseif ( $c === '{' )
                $depth++;
            elseif ( $c === '}' && --$depth === 0 )
            {
                $data = json_decode( substr( $html, $start, $i - $start + 1 ), true, 512, JSON_BIGINT_AS_STRING );
                return is_array( $data ) ? $data : null;
            }
        }
        return null;
    }

    /**
     * The videos of a page's data: every videoRenderer, gridVideoRenderer, playlistVideoRenderer and
     * compactVideoRenderer, and the newer lockupViewModel of a video, in the page's order, each once.
     *
     * @param array $data the decoded ytInitialData
     * @return array[] records
     */
    public static function pageRecords( array $data )
    {
        $channelId = $channelTitle = '';
        if ( isset( $data['metadata']['channelMetadataRenderer'] ) && is_array( $data['metadata']['channelMetadataRenderer'] ) )
        {
            $meta = $data['metadata']['channelMetadataRenderer'];
            $channelId = isset( $meta['externalId'] ) && is_string( $meta['externalId'] ) ? $meta['externalId'] : '';
            $channelTitle = isset( $meta['title'] ) && is_string( $meta['title'] ) ? $meta['title'] : '';
        }
        $found = array();
        self::collect( $data, $found );
        $out = array();
        foreach ( $found as $item )
        {
            $record = $item[0] === 'lockupViewModel' ? self::lockupRecord( $item[1] ) : self::rendererRecord( $item[1] );
            if ( $record === null || isset( $out[$record['id']] ) )
                continue;
            if ( $record['channel_id'] === '' )
                $record['channel_id'] = $channelId;
            if ( $record['channel_title'] === '' )
                $record['channel_title'] = $channelTitle;
            $out[$record['id']] = $record;
        }
        return array_values( $out );
    }

    /** Collects the video renderers of a tree, depth first (the page's order). */
    protected static function collect( array $node, array &$found, $depth = 0 )
    {
        if ( $depth > 64 )
            return;
        foreach ( $node as $key => $value )
        {
            if ( !is_array( $value ) )
                continue;
            if ( in_array( $key, array( 'videoRenderer', 'gridVideoRenderer', 'playlistVideoRenderer', 'compactVideoRenderer' ), true ) && isset( $value['videoId'] ) )
            {
                $found[] = array( $key, $value );
                continue;
            }
            if ( $key === 'lockupViewModel' && isset( $value['contentId'] ) && ( !isset( $value['contentType'] ) || $value['contentType'] === 'LOCKUP_CONTENT_TYPE_VIDEO' ) )
            {
                $found[] = array( $key, $value );
                continue;
            }
            self::collect( $value, $found, $depth + 1 );
        }
    }

    /** @return string the text of a runs or simpleText object */
    protected static function text( $value )
    {
        if ( is_string( $value ) )
            return $value;
        if ( !is_array( $value ) )
            return '';
        if ( isset( $value['simpleText'] ) && is_string( $value['simpleText'] ) )
            return $value['simpleText'];
        if ( isset( $value['content'] ) && is_string( $value['content'] ) )
            return $value['content'];
        $out = '';
        foreach ( isset( $value['runs'] ) && is_array( $value['runs'] ) ? $value['runs'] : array() as $run )
            if ( is_array( $run ) && isset( $run['text'] ) && is_string( $run['text'] ) )
                $out .= $run['text'];
        return $out;
    }

    /** @return int|null the number of a view count text ("1,234 views", "1.234 Aufrufe"), null for "1.2K" or none */
    public static function viewCount( $text )
    {
        $text = trim( (string)$text );
        if ( $text === '' )
            return null;
        if ( preg_match( '/^no views?$/i', $text ) )
            return 0;
        if ( !preg_match( '/^([\d][\d,.\s\x{00A0}\x{202F}\']*)\s*\p{L}*\.?\s*$/u', $text, $m ) )
            return null;
        $digits = preg_replace( '/\D/', '', $m[1] );
        return $digits === '' ? null : (int)$digits;
    }

    /**
     * The common fields of a record.
     *
     * @return array
     */
    protected static function base( $id, $title, $seconds, array $thumbnails, $views, $published, $channelId, $channelTitle, $live, $description = '' )
    {
        $best = '';
        $bestWidth = -1;
        foreach ( $thumbnails as $t )
            if ( is_array( $t ) && isset( $t['url'] ) && is_string( $t['url'] ) && ( isset( $t['width'] ) ? (int)$t['width'] : 0 ) > $bestWidth )
            {
                $best = $t['url'];
                $bestWidth = isset( $t['width'] ) ? (int)$t['width'] : 0;
            }
        if ( strpos( $best, '//' ) === 0 )
            $best = 'https:' . $best;
        return array(
            'id' => $id,
            'url' => 'https://www.youtube.com/watch?v=' . rawurlencode( $id ),
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ),
            'title' => $title,
            'description' => $description,
            'published' => null,
            'published_text' => $published,
            'channel_id' => $channelId,
            'channel_title' => $channelTitle,
            'tags' => array(),
            'category_id' => '',
            'duration_seconds' => $seconds,
            'duration' => $seconds === null ? '' : expImportYouTubeSource::clock( $seconds ),
            'thumbnail' => $best,
            'thumbnails' => $thumbnails ? (string)json_encode( array_values( $thumbnails ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '',
            'view_count' => $views,
            'like_count' => null,
            'comment_count' => null,
            'privacy' => '',
            'live_broadcast_content' => $live,
        );
    }

    /** @return int|null "1:02:03" or "4:05" in seconds */
    protected static function seconds( $clock )
    {
        $s = expImportRssSource::seconds( $clock );
        return $s === '' ? null : (int)$s;
    }

    /** @return array|null a record of a videoRenderer (and its grid, playlist and compact forms) */
    protected static function rendererRecord( array $r )
    {
        $id = is_string( $r['videoId'] ) ? $r['videoId'] : '';
        if ( !preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) )
            return null;
        $length = self::text( isset( $r['lengthText'] ) ? $r['lengthText'] : '' );
        foreach ( isset( $r['thumbnailOverlays'] ) && is_array( $r['thumbnailOverlays'] ) ? $r['thumbnailOverlays'] : array() as $o )
            if ( $length === '' && isset( $o['thumbnailOverlayTimeStatusRenderer']['text'] ) )
                $length = self::text( $o['thumbnailOverlayTimeStatusRenderer']['text'] );
        $seconds = isset( $r['lengthSeconds'] ) && is_numeric( $r['lengthSeconds'] ) ? (int)$r['lengthSeconds'] : self::seconds( $length );
        $owner = isset( $r['ownerText'] ) ? $r['ownerText'] : ( isset( $r['shortBylineText'] ) ? $r['shortBylineText'] : array() );
        $channelId = isset( $owner['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'] ) ? (string)$owner['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'] : '';
        $live = 'none';
        if ( isset( $r['upcomingEventData'] ) )
            $live = 'upcoming';
        else
            foreach ( isset( $r['badges'] ) && is_array( $r['badges'] ) ? $r['badges'] : array() as $badge )
                if ( isset( $badge['metadataBadgeRenderer']['style'] ) && $badge['metadataBadgeRenderer']['style'] === 'BADGE_STYLE_TYPE_LIVE_NOW' )
                    $live = 'live';
        $description = self::text( isset( $r['descriptionSnippet'] ) ? $r['descriptionSnippet'] : ( isset( $r['detailedMetadataSnippets'][0]['snippetText'] ) ? $r['detailedMetadataSnippets'][0]['snippetText'] : '' ) );
        return self::base( $id, self::text( isset( $r['title'] ) ? $r['title'] : '' ), $seconds,
                           isset( $r['thumbnail']['thumbnails'] ) && is_array( $r['thumbnail']['thumbnails'] ) ? $r['thumbnail']['thumbnails'] : array(),
                           self::viewCount( self::text( isset( $r['viewCountText'] ) ? $r['viewCountText'] : '' ) ),
                           self::text( isset( $r['publishedTimeText'] ) ? $r['publishedTimeText'] : '' ),
                           strpos( $channelId, 'UC' ) === 0 ? $channelId : '', self::text( $owner ), $live, $description );
    }

    /** @return array|null a record of a lockupViewModel (the newer form of a channel's video grid) */
    protected static function lockupRecord( array $l )
    {
        $id = is_string( $l['contentId'] ) ? $l['contentId'] : '';
        if ( !preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) )
            return null;
        $meta = isset( $l['metadata']['lockupMetadataViewModel'] ) && is_array( $l['metadata']['lockupMetadataViewModel'] ) ? $l['metadata']['lockupMetadataViewModel'] : array();
        $title = self::text( isset( $meta['title'] ) ? $meta['title'] : '' );
        // the rows of the metadata: "1,234 views", "3 days ago"
        $parts = array();
        foreach ( isset( $meta['metadata']['contentMetadataViewModel']['metadataRows'] ) && is_array( $meta['metadata']['contentMetadataViewModel']['metadataRows'] ) ? $meta['metadata']['contentMetadataViewModel']['metadataRows'] : array() as $row )
            foreach ( isset( $row['metadataParts'] ) && is_array( $row['metadataParts'] ) ? $row['metadataParts'] : array() as $part )
                if ( isset( $part['text'] ) )
                    $parts[] = self::text( $part['text'] );
        $views = null;
        $published = '';
        foreach ( $parts as $part )
        {
            if ( $views === null && ( $v = self::viewCount( $part ) ) !== null )
                $views = $v;
            elseif ( $published === '' && preg_match( '/\d/', $part ) && self::viewCount( $part ) === null && !preg_match( '/views?\b/i', $part ) )
                $published = $part;
        }
        $image = isset( $l['contentImage']['thumbnailViewModel'] ) && is_array( $l['contentImage']['thumbnailViewModel'] ) ? $l['contentImage']['thumbnailViewModel'] : array();
        $thumbnails = isset( $image['image']['sources'] ) && is_array( $image['image']['sources'] ) ? $image['image']['sources'] : array();
        $length = '';
        foreach ( isset( $image['overlays'] ) && is_array( $image['overlays'] ) ? $image['overlays'] : array() as $o )
            foreach ( isset( $o['thumbnailOverlayBadgeViewModel']['thumbnailBadges'] ) && is_array( $o['thumbnailOverlayBadgeViewModel']['thumbnailBadges'] ) ? $o['thumbnailOverlayBadgeViewModel']['thumbnailBadges'] : array() as $b )
                if ( $length === '' && isset( $b['thumbnailBadgeViewModel']['text'] ) && is_string( $b['thumbnailBadgeViewModel']['text'] ) )
                    $length = $b['thumbnailBadgeViewModel']['text'];
        $live = preg_match( '/^live$/i', $length ) ? 'live' : 'none';
        return self::base( $id, $title, self::seconds( $length ), $thumbnails, $views, $published, '', '', $live );
    }
}
