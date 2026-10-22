<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaasSourceTestTransport.php';

/**
 * The YouTube sources against doubles with invented data, never the network: the Data API source (channel by ID and
 * by @handle, playlist, search with its quota, videos.list in batches of 50, paging with pageToken, MaxPages, the
 * record fields, durations and thumbnails, Test connection with its masked failure, the API key by its secret and
 * only GET requests to www.googleapis.com) and the opt-in page scraper (off by default and disabled in the
 * catalogue, the addresses it accepts, robots.txt, ytInitialData of the older and the newer page forms).
 */
class YouTubeSourceTest extends TestCase
{
    use SaasSourceTestTransport;

    const KEY = 'AIzaExampleKey-0000000000000000sample';

    protected function setUp(): void
    {
        $this->startTransport( 'youtube' );
        putenv( 'SQ688_YOUTUBE_KEY=' . self::KEY );
    }

    protected function tearDown(): void
    {
        $this->stopTransport();
        putenv( 'SQ688_YOUTUBE_KEY' );
        expImportYouTubePageSource::$crawlerFactory = null;
        $ini = eZINI::instance( 'sqliimport.ini' );
        foreach ( array( 'AllowPageScraper', 'MaxPages', 'MaxSearchPages' ) as $name )
            $ini->removeSetting( 'YouTubeSettings', $name );
    }

    protected function options( array $more = array() ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportYouTubeSource::schema(), $more + array( 'channel' => '@examplechannel', 'api_key' => 'env:SQ688_YOUTUBE_KEY' ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array a video resource of videos.list */
    protected static function video( string $id, array $more = array() ): array
    {
        return array_replace_recursive( array(
            'id' => $id,
            'snippet' => array( 'publishedAt' => '2026-09-14T08:00:00Z', 'channelId' => 'UCexampleChannel000000aa', 'title' => 'Video ' . $id,
                                'description' => 'An invented video.', 'channelTitle' => 'Example Tea Channel', 'tags' => array( 'tea' ), 'categoryId' => '26',
                                'liveBroadcastContent' => 'none',
                                'thumbnails' => array( 'default' => array( 'url' => "https://i.ytimg.com/vi/$id/default.jpg", 'width' => 120, 'height' => 90 ),
                                                       'high' => array( 'url' => "https://i.ytimg.com/vi/$id/hqdefault.jpg", 'width' => 480, 'height' => 360 ) ) ),
            'contentDetails' => array( 'duration' => 'PT12M34S' ),
            'statistics' => array( 'viewCount' => '1234', 'likeCount' => '56', 'commentCount' => '7' ),
            'status' => array( 'privacyStatus' => 'public' ),
        ), $more );
    }

    protected static function channelAnswer(): array
    {
        return array( 'items' => array( array( 'id' => 'UCexampleChannel000000aa', 'snippet' => array( 'title' => 'Example Tea Channel' ),
                                               'contentDetails' => array( 'relatedPlaylists' => array( 'uploads' => 'UUexampleChannel000000aa' ) ),
                                               'statistics' => array( 'videoCount' => '3' ) ) ) );
    }

    protected function assertOnlyGetToGoogle(): void
    {
        foreach ( $this->requests as $r )
        {
            $this->assertSame( 'GET', $r[0] );
            $this->assertStringStartsWith( 'https://www.googleapis.com/youtube/v3/', $r[1] );
            $this->assertStringNotContainsString( self::KEY, $r[1], 'the key is never in an address' );
            $this->assertContains( 'X-goog-api-key: ' . self::KEY, $r[2] );
        }
    }

    public function testSchemaExamplesAndCatalogue(): void
    {
        $schema = expImportYouTubeSource::schema();
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertSame( array(), expImportOptionSchema::check( expImportYouTubePageSource::schema() ) );
        $this->assertSame( 'media', expImportYouTubeSource::group() );
        $this->assertSame( 'media', expImportYouTubePageSource::group() );
        $this->assertSame( array( 'remote' ), expImportYouTubeSource::origins() );
        foreach ( array( 'expImportYouTubeSource' => 3, 'expImportYouTubePageSource' => 3 ) as $class => $count )
            foreach ( $class::examples() as $example )
            {
                if ( !isset( $example['file'] ) )
                    continue;
                $records = $this->readExample( $class, $example );
                $this->assertCount( $count, $records );
                $this->assertSame( 'Ex4mpleVid1', $records[0]->ref );
                $this->assertSame( 754, $records[0]->data['duration_seconds'] );
            }
        $this->assertSame( 'link', expImportYouTubeSource::preset()['class'] );
        $kept = expImportEditor::presetFields( expImportYouTubeSource::preset(), array( 'name' => 'ezstring', 'description' => 'ezxmltext', 'location' => 'ezurl' ) );
        $this->assertSame( array( 'title', 'description', 'url' ), array_column( $kept, 'source' ), 'a standard link class: the image and date lines are left out' );
        foreach ( array( '2026-01-31', '2026-01-31T12:00:00Z', '2026-01-31T12:00:00+02:00' ) as $date )
            $this->assertSame( array(), expImportOptionSchema::validate( $schema, array( 'published_after' => $date, 'api_key' => 'x' ) )[1], $date );
        $this->assertArrayHasKey( 'published_after', expImportOptionSchema::validate( $schema, array( 'published_after' => 'yesterday', 'api_key' => 'x' ) )[1] );
        $this->assertArrayHasKey( 'page_size', expImportOptionSchema::validate( $schema, array( 'page_size' => 51, 'api_key' => 'x' ) )[1] );
        $this->assertArrayHasKey( 'api_key', expImportOptionSchema::validate( $schema, array() )[1] );
        // the catalogue entries: the scraper carries its notice and is disabled while switched off
        $api = ( new expImportSourceEntry( 'expImportYouTubeSource' ) )->toArray();
        $this->assertSame( array( '', '' ), array( $api['notice'], $api['availability'] ) );
        $this->assertSame( 'enabled', expImportRegistry::available( array( 'status' => 'enabled' ) + $api )['status'] );
        $page = ( new expImportSourceEntry( 'expImportYouTubePageSource' ) )->toArray();
        $this->assertStringContainsString( 'Terms of Service', $page['notice'] );
        $this->assertStringContainsString( 'AllowPageScraper=enabled', $page['availability'] );
        $this->assertSame( 'disabled', expImportRegistry::available( array( 'status' => 'enabled' ) + $page )['status'] );
        $this->assertStringContainsString( 'Terms of Service', expImportYouTubePageSource::description() );
        $this->assertStringContainsString( 'Terms of Service', expImportYouTubePageSource::schema()['url']['help'] );
    }

    public function testDurationsThumbnailsAndRecord(): void
    {
        foreach ( array( 'PT12M34S' => 754, 'PT1H2M3S' => 3723, 'PT45S' => 45, 'PT1H' => 3600, 'P1DT2H' => 93600, 'P0D' => 0, 'PT0S' => 0 ) as $iso => $seconds )
            $this->assertSame( $seconds, expImportYouTubeSource::durationSeconds( $iso ), $iso );
        foreach ( array( '', 'P', '12:34', 'PT12X' ) as $bad )
            $this->assertNull( expImportYouTubeSource::durationSeconds( $bad ), $bad );
        $this->assertSame( array( '1:02:03', '0:04:05', '0:00:00', '26:00:00' ), array_map( array( 'expImportYouTubeSource', 'clock' ), array( 3723, 245, 0, 93600 ) ) );
        $this->assertSame( 'https://x.invalid/max.jpg', expImportYouTubeSource::largestThumbnail( array(
            'default' => array( 'url' => 'https://x.invalid/d.jpg', 'width' => 120 ), 'maxres' => array( 'url' => 'https://x.invalid/max.jpg', 'width' => 1280 ),
            'high' => array( 'url' => 'https://x.invalid/h.jpg', 'width' => 480 ) ) ) );
        $this->assertSame( 'https://x.invalid/s.jpg', expImportYouTubeSource::largestThumbnail( array(
            'medium' => array( 'url' => 'https://x.invalid/m.jpg' ), 'standard' => array( 'url' => 'https://x.invalid/s.jpg' ) ) ), 'without widths: by name' );
        $this->assertSame( '', expImportYouTubeSource::largestThumbnail( array() ) );
        $r = expImportYouTubeSource::record( self::video( 'Ex4mpleVid1', array( 'statistics' => array( 'likeCount' => null ) ) ) );
        $this->assertSame( expImportYouTubeSource::FIELDS, array_keys( $r ) );
        $this->assertSame( 'https://www.youtube.com/watch?v=Ex4mpleVid1', $r['url'] );
        $this->assertSame( 'https://www.youtube-nocookie.com/embed/Ex4mpleVid1', $r['embed_url'] );
        $this->assertSame( array( 754, '0:12:34', 1234, null, 7 ), array( $r['duration_seconds'], $r['duration'], $r['view_count'], $r['like_count'], $r['comment_count'] ) );
        $this->assertSame( 'https://i.ytimg.com/vi/Ex4mpleVid1/hqdefault.jpg', $r['thumbnail'] );
        $this->assertSame( 480, json_decode( $r['thumbnails'], true )['high']['width'] );
        $this->assertSame( array( 'tea' ), $r['tags'] );
        $this->assertSame( array( 'public', 'none', '26', '2026-09-14T08:00:00Z' ), array( $r['privacy'], $r['live_broadcast_content'], $r['category_id'], $r['published'] ) );
        $bare = expImportYouTubeSource::record( array( 'id' => 'Ex4mpleVid9' ) );
        $this->assertSame( array( array(), null, '', '', null ), array( $bare['tags'], $bare['duration_seconds'], $bare['duration'], $bare['thumbnail'], $bare['view_count'] ) );
    }

    public function testChannelByHandlePagingAndBatches(): void
    {
        $page1 = array();
        for ( $i = 1; $i <= 60; $i++ )
            $page1[] = array( 'contentDetails' => array( 'videoId' => sprintf( 'Ex4mpleV%03d', $i ) ) );
        $page1[] = array( 'contentDetails' => array( 'videoId' => 'Ex4mpleV001' ) );
        $details1 = $details2 = array();
        for ( $i = 1; $i <= 50; $i++ )
            $details1[] = self::video( sprintf( 'Ex4mpleV%03d', $i ) );
        // V055 is private: playlistItems lists it, videos.list does not give it
        for ( $i = 51; $i <= 60; $i++ )
            if ( $i !== 55 )
                $details2[] = self::video( sprintf( 'Ex4mpleV%03d', $i ) );
        $this->script( array(
            array( 200, self::channelAnswer() ),
            array( 200, array( 'items' => $page1, 'nextPageToken' => 'TOKEN2', 'pageInfo' => array( 'totalResults' => 62 ) ) ),
            array( 200, array( 'items' => array_reverse( $details1 ) ) ),
            array( 200, array( 'items' => $details2 ) ),
            array( 200, array( 'items' => array( array( 'snippet' => array( 'resourceId' => array( 'videoId' => 'Ex4mpleV061' ) ) ) ) ) ),
            array( 200, array( 'items' => array( self::video( 'Ex4mpleV061', array( 'contentDetails' => array( 'duration' => 'PT1H2M3S' ) ) ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportYouTubeSource::fetch( $this->options(), $target, 0 );
        $this->assertCount( 6, $this->requests );
        $this->assertOnlyGetToGoogle();
        $this->assertSame( 'https://www.googleapis.com/youtube/v3/channels?part=snippet%2CcontentDetails%2Cstatistics&forHandle=%40examplechannel', $this->requests[0][1] );
        $this->assertSame( 'https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&playlistId=UUexampleChannel000000aa&maxResults=50', $this->requests[1][1] );
        $this->assertStringStartsWith( 'https://www.googleapis.com/youtube/v3/videos?part=snippet%2CcontentDetails%2Cstatistics%2Cstatus&id=Ex4mpleV001%2C', $this->requests[2][1] );
        $this->assertSame( 50, substr_count( urldecode( $this->requests[2][1] ), 'Ex4mpleV' ), 'videos.list in batches of 50' );
        $this->assertStringEndsWith( '&pageToken=TOKEN2', $this->requests[4][1] );
        $this->assertSame( 6, expImportYouTubeSource::$units );
        $records = $this->snapshot( $target );
        $this->assertCount( 60, $records, '61 listed, one twice, one private' );
        $this->assertSame( 'Ex4mpleV001', $records[0]['id'], 'the order of the listing, not of videos.list' );
        $this->assertNotContains( 'Ex4mpleV055', array_column( $records, 'id' ) );
        $this->assertSame( '1:02:03', $records[59]['duration'] );
        // a channel by its ID or address, and the record limit
        $this->script( array( array( 200, self::channelAnswer() ), array( 200, array( 'items' => array_slice( $page1, 0, 2 ), 'nextPageToken' => 'X' ) ),
                              array( 200, array( 'items' => array_slice( $details1, 0, 2 ) ) ) ) );
        expImportYouTubeSource::fetch( $this->options( array( 'channel' => 'https://www.youtube.com/channel/UCexampleChannel000000aa/videos', 'max_records' => 2 ) ), $target, 0 );
        $this->assertStringEndsWith( '&id=UCexampleChannel000000aa', $this->requests[0][1] );
        $this->assertStringContainsString( 'maxResults=2', $this->requests[1][1] );
        $this->assertCount( 3, $this->requests, 'the limit stops before the next page' );
        $this->assertCount( 2, $this->snapshot( $target ) );
        foreach ( array( '@examplechannel' => array( 'forHandle' => '@examplechannel' ), 'examplechannel' => array( 'forHandle' => '@examplechannel' ),
                         'https://youtube.com/@example.channel' => array( 'forHandle' => '@example.channel' ),
                         'UCexampleChannel000000aa' => array( 'id' => 'UCexampleChannel000000aa' ) ) as $given => $param )
            $this->assertSame( $param, expImportYouTubeSource::channelParam( $given ), $given );
        foreach ( array( '', 'a b', '@x', 'https://www.youtube.com/watch?v=Ex4mpleVid1' ) as $bad )
        {
            try
            {
                expImportYouTubeSource::channelParam( $bad );
                $this->fail( "$bad is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'channel', $e->getMessage() );
            }
        }
    }

    public function testPlaylistSearchQuotaAndMaxPages(): void
    {
        $this->script( array(
            array( 200, array( 'items' => array( array( 'contentDetails' => array( 'videoId' => 'Ex4mpleVid1' ) ) ) ) ),
            array( 200, array( 'items' => array( self::video( 'Ex4mpleVid1' ) ) ) ),
        ) );
        $target = $this->dir . '/source.ndjson';
        expImportYouTubeSource::fetch( $this->options( array( 'mode' => 'playlist', 'playlist_id' => 'https://www.youtube.com/playlist?list=PLexample0001' ) ), $target, 0 );
        $this->assertSame( 'https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&playlistId=PLexample0001&maxResults=50', $this->requests[0][1] );
        $this->assertCount( 1, $this->snapshot( $target ) );
        // a search: type=video, the dates as RFC 3339, the channel; 100 units per page
        $this->script( array(
            array( 200, array( 'items' => array( array( 'id' => array( 'kind' => 'youtube#video', 'videoId' => 'Ex4mpleVid2' ) ) ), 'nextPageToken' => 'P2' ) ),
            array( 200, array( 'items' => array( self::video( 'Ex4mpleVid2' ) ) ) ),
            array( 200, array( 'items' => array( array( 'id' => array( 'kind' => 'youtube#video', 'videoId' => 'Ex4mpleVid3' ) ) ) ) ),
            array( 200, array( 'items' => array( self::video( 'Ex4mpleVid3' ) ) ) ),
        ) );
        expImportYouTubeSource::fetch( $this->options( array( 'mode' => 'search', 'query' => 'green tea', 'channel' => 'UCexampleChannel000000aa',
                                                              'published_after' => '2026-01-01', 'published_before' => '2026-06-30T12:00:00+02:00', 'order' => 'viewCount' ) ), $target, 0 );
        $this->assertOnlyGetToGoogle();
        $this->assertSame( 'https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=50&q=green%20tea&order=viewCount'
                           . '&publishedAfter=2026-01-01T00%3A00%3A00Z&publishedBefore=2026-06-30T10%3A00%3A00Z&channelId=UCexampleChannel000000aa', $this->requests[0][1] );
        $this->assertSame( 202, expImportYouTubeSource::$units );
        $this->assertSame( array( 'Ex4mpleVid2', 'Ex4mpleVid3' ), array_column( $this->snapshot( $target ), 'id' ) );
        // MaxSearchPages: the run stops with a clear message rather than an incomplete import
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'YouTubeSettings', 'MaxSearchPages', '1' );
        $this->script( array( array( 200, array( 'items' => array(), 'nextPageToken' => 'P2' ) ) ) );
        try
        {
            expImportYouTubeSource::fetch( $this->options( array( 'mode' => 'search', 'query' => 'tea', 'channel' => '' ) ), $target, 0 );
            $this->fail( 'more pages than allowed' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'MaxSearchPages (1)', $e->getMessage() );
        }
        // a search needs terms or a channel; nothing is sent
        $this->script( array() );
        try
        {
            expImportYouTubeSource::fetch( $this->options( array( 'mode' => 'search', 'channel' => '' ) ), $target, 0 );
            $this->fail( 'an empty search' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'search terms', $e->getMessage() );
        }
        $this->assertSame( array(), $this->requests );
    }

    public function testOnlyTheFourReadMethods(): void
    {
        foreach ( array( 'channels', 'playlistItems', 'search', 'videos' ) as $method )
            $this->assertStringStartsWith( 'https://www.googleapis.com/youtube/v3/' . $method, expImportYouTubeSource::url( $method, array( 'part' => 'id' ) ) );
        foreach ( array( 'playlists', 'commentThreads', 'videos/rate', 'thumbnails/set', 'subscriptions', '../x' ) as $method )
        {
            try
            {
                expImportYouTubeSource::url( $method, array() );
                $this->fail( "$method is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'read-only', $e->getMessage() );
            }
        }
        $this->assertSame( '2026-06-30T10:00:00Z', expImportYouTubeSource::rfc3339( '2026-06-30T12:00:00+02:00' ) );
        $this->assertSame( '', expImportYouTubeSource::rfc3339( '' ) );
    }

    public function testTheTestConnectionQuotaAndMaskedFailure(): void
    {
        $this->script( array( array( 200, self::channelAnswer() ) ) );
        $result = expImportYouTubeSource::test( $this->options() );
        $this->assertResultAndDrivers( $result, expImportYouTubeSource::drivers() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 3, $result['rows'] );
        $this->assertSame( expImportYouTubeSource::FIELDS, $result['columns'] );
        $this->assertStringContainsString( '1 quota unit', $result['message'] );
        $this->assertCount( 1, $this->requests );
        // a search without a channel: one videos.list of 1 unit, never search.list
        $this->script( array( array( 200, array( 'items' => array( array( 'id' => 'Ex4mpleVid1' ) ) ) ) ) );
        $result = expImportYouTubeSource::test( $this->options( array( 'mode' => 'search', 'query' => 'tea', 'channel' => '' ) ) );
        $this->assertTrue( $result['ok'] );
        $this->assertStringStartsWith( 'https://www.googleapis.com/youtube/v3/videos?', $this->requests[0][1] );
        $this->assertStringContainsString( '100 units per page', $result['message'] );
        // a playlist: its total
        $this->script( array( array( 200, array( 'items' => array(), 'pageInfo' => array( 'totalResults' => 42 ) ) ) ) );
        $this->assertSame( 42, expImportYouTubeSource::test( $this->options( array( 'mode' => 'playlist', 'playlist_id' => 'PLexample0001' ) ) )['rows'] );
        // a refused key, the quota, a missing channel: masked
        $this->script( array( array( 400, array( 'error' => array( 'code' => 400, 'message' => 'API key not valid. ' . self::KEY, 'errors' => array( array( 'reason' => 'badRequest' ) ) ) ) ) ) );
        $result = expImportYouTubeSource::test( $this->options() );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'answered 400', $result['message'] );
        $this->assertStringNotContainsString( 'sample', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_YOUTUBE_KEY]', $result['message'] );
        $this->script( array( array( 403, array( 'error' => array( 'code' => 403, 'message' => 'The request cannot be completed because you have exceeded your <a href="/youtube/v3/getting-started#quota">quota</a>.' ) ) ) ) );
        $this->assertStringContainsString( 'midnight Pacific Time', expImportYouTubeSource::test( $this->options() )['message'] );
        $this->script( array( array( 200, array( 'items' => array() ) ) ) );
        $this->assertStringContainsString( 'no channel @examplechannel', expImportYouTubeSource::test( $this->options() )['message'] );
        // the key by the name of a secret in the settings
        $ini = eZINI::instance( 'sqliimport.ini' );
        $ini->setVariable( 'SecretSettings', 'Secrets', array( 'youtube_test_key' => 'AIzaFromTheSettings' ) );
        try
        {
            $this->script( array( array( 200, self::channelAnswer() ) ) );
            $this->assertTrue( expImportYouTubeSource::test( $this->options( array( 'api_key' => 'youtube_test_key' ) ) )['ok'] );
            $this->assertContains( 'X-goog-api-key: AIzaFromTheSettings', $this->requests[0][2] );
        }
        finally
        {
            $ini->removeSetting( 'SecretSettings', 'Secrets' );
        }
        $this->assertStringContainsString( "'no_such_youtube_key' is not set", expImportYouTubeSource::test( $this->options( array( 'api_key' => 'no_such_youtube_key' ) ) )['message'] );
    }

    // ---- the page scraper --------------------------------------------------------------------------------------------

    /** @return array[] the addresses the crawler was asked for */
    protected function pageCrawler( array $site ): array
    {
        $requests = new ArrayObject();
        expImportYouTubePageSource::$crawlerFactory = function ( $target ) use ( $site, $requests )
        {
            $crawler = new expImportCrawler();
            $crawler->fetcher = function ( $url ) use ( $site, $requests )
            {
                $requests[] = $url;
                return isset( $site[$url] ) ? $site[$url] : array( 'status' => 404, 'headers' => array(), 'body' => '' );
            };
            $crawler->sleeper = function ( $seconds )
            {
            };
            return $crawler;
        };
        return array( $requests );
    }

    protected static function page( array $data ): string
    {
        return '<!DOCTYPE html><html><head><title>Example</title></head><body><script nonce="x">var ytInitialData = '
             . json_encode( $data, JSON_UNESCAPED_SLASHES ) . ';</script><script>var other = {"a": "}"};</script></body></html>';
    }

    protected static function channelPageData(): array
    {
        $renderer = function ( $id, $title, $length, $views, $when )
        {
            return array( 'richItemRenderer' => array( 'content' => array( 'videoRenderer' => array(
                'videoId' => $id, 'title' => array( 'runs' => array( array( 'text' => $title ) ) ),
                'thumbnail' => array( 'thumbnails' => array( array( 'url' => "https://i.ytimg.com/vi/$id/default.jpg", 'width' => 168, 'height' => 94 ),
                                                             array( 'url' => "https://i.ytimg.com/vi/$id/hqdefault.jpg", 'width' => 336, 'height' => 188 ) ) ),
                'lengthText' => array( 'simpleText' => $length ), 'viewCountText' => array( 'simpleText' => $views ),
                'publishedTimeText' => array( 'simpleText' => $when ) ) ) ) );
        };
        return array(
            'metadata' => array( 'channelMetadataRenderer' => array( 'title' => 'Example Tea Channel', 'externalId' => 'UCexampleChannel000000aa' ) ),
            'contents' => array( 'twoColumnBrowseResultsRenderer' => array( 'tabs' => array( array( 'tabRenderer' => array( 'content' => array( 'richGridRenderer' => array( 'contents' => array(
                $renderer( 'Ex4mpleVid1', 'Brewing "green" tea {at} the right temperature', '12:34', '1,234 views', '5 weeks ago' ),
                $renderer( 'Ex4mpleVid2', 'A visit to an example tea garden', '1:02:03', '980 views', '7 weeks ago' ),
                $renderer( 'Ex4mpleVid1', 'The same video again', '12:34', '1,234 views', '5 weeks ago' ),
                array( 'richItemRenderer' => array( 'content' => array( 'lockupViewModel' => array(
                    'contentId' => 'Ex4mpleVid4', 'contentType' => 'LOCKUP_CONTENT_TYPE_VIDEO',
                    'contentImage' => array( 'thumbnailViewModel' => array( 'image' => array( 'sources' => array( array( 'url' => 'https://i.ytimg.com/vi/Ex4mpleVid4/hqdefault.jpg', 'width' => 480, 'height' => 270 ) ) ),
                                                                            'overlays' => array( array( 'thumbnailOverlayBadgeViewModel' => array( 'thumbnailBadges' => array( array( 'thumbnailBadgeViewModel' => array( 'text' => '4:05' ) ) ) ) ) ) ) ),
                    'metadata' => array( 'lockupMetadataViewModel' => array( 'title' => array( 'content' => 'Five teas for autumn' ),
                        'metadata' => array( 'contentMetadataViewModel' => array( 'metadataRows' => array( array( 'metadataParts' => array(
                            array( 'text' => array( 'content' => '1.2K views' ) ), array( 'text' => array( 'content' => '2 months ago' ) ) ) ) ) ) ) ) ) ) ) ) ),
                array( 'richItemRenderer' => array( 'content' => array( 'lockupViewModel' => array( 'contentId' => 'PLexample0001', 'contentType' => 'LOCKUP_CONTENT_TYPE_PLAYLIST' ) ) ) ),
                array( 'continuationItemRenderer' => array( 'continuationEndpoint' => array( 'continuationCommand' => array( 'token' => 'NEXT' ) ) ) ),
            ) ) ) ) ) ) ) ),
        );
    }

    public function testThePageScraperIsOffByDefault(): void
    {
        list( $requests ) = $this->pageCrawler( array() );
        $options = expImportOptionSchema::validate( expImportYouTubePageSource::schema(), array( 'url' => 'https://www.youtube.com/@examplechannel/videos' ) )[0];
        $result = expImportYouTubePageSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'AllowPageScraper=enabled', $result['message'] );
        try
        {
            expImportYouTubePageSource::fetch( $options, $this->dir . '/source.ndjson', 0 );
            $this->fail( 'the scraper is off' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'Terms of Service', $e->getMessage() );
        }
        $this->assertCount( 0, $requests, 'nothing is read while it is off' );
        $this->assertFileDoesNotExist( $this->dir . '/source.ndjson' );
    }

    public function testThePageScraperWhenSwitchedOn(): void
    {
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'YouTubeSettings', 'AllowPageScraper', 'enabled' );
        $this->assertSame( '', expImportYouTubePageSource::availability() );
        $this->assertSame( 'enabled', expImportRegistry::available( array( 'status' => 'enabled' ) + ( new expImportSourceEntry( 'expImportYouTubePageSource' ) )->toArray() )['status'] );
        list( $requests ) = $this->pageCrawler( array(
            'https://www.youtube.com/robots.txt' => array( 'status' => 200, 'headers' => array(), 'body' => "User-agent: *\nDisallow: /results\nDisallow: /secret\n" ),
            'https://www.youtube.com/@examplechannel/videos' => array( 'status' => 200, 'headers' => array( 'content-type' => 'text/html' ), 'body' => self::page( self::channelPageData() ) ),
        ) );
        $options = expImportOptionSchema::validate( expImportYouTubePageSource::schema(), array( 'url' => 'https://youtube.com/@examplechannel' ) )[0];
        $target = $this->dir . '/source.ndjson';
        expImportYouTubePageSource::fetch( $options, $target, 0 );
        $this->assertSame( array( 'https://www.youtube.com/robots.txt', 'https://www.youtube.com/@examplechannel/videos' ), $requests->getArrayCopy() );
        $records = $this->snapshot( $target );
        $this->assertSame( array( 'Ex4mpleVid1', 'Ex4mpleVid2', 'Ex4mpleVid4' ), array_column( $records, 'id' ), 'each video once, no playlist' );
        $a = $records[0];
        $this->assertSame( 'Brewing "green" tea {at} the right temperature', $a['title'] );
        $this->assertSame( array( 754, '0:12:34', 1234, '5 weeks ago', null ), array( $a['duration_seconds'], $a['duration'], $a['view_count'], $a['published_text'], $a['published'] ) );
        $this->assertSame( array( 'UCexampleChannel000000aa', 'Example Tea Channel' ), array( $a['channel_id'], $a['channel_title'] ) );
        $this->assertSame( 'https://i.ytimg.com/vi/Ex4mpleVid1/hqdefault.jpg', $a['thumbnail'] );
        $this->assertSame( 'https://www.youtube-nocookie.com/embed/Ex4mpleVid1', $a['embed_url'] );
        $this->assertSame( array( null, null, array() ), array( $a['like_count'], $a['comment_count'], $a['tags'] ) );
        $this->assertSame( '1:02:03', $records[1]['duration'] );
        $d = $records[2];
        $this->assertSame( array( 'Five teas for autumn', 245, null, '2 months ago' ), array( $d['title'], $d['duration_seconds'], $d['view_count'], $d['published_text'] ), '1.2K is not a count' );
        foreach ( expImportYouTubeSource::FIELDS as $field )
            $this->assertArrayHasKey( $field, $a, "the API's field $field" );
        // the record limit, and Test connection
        $options['max_records'] = 1;
        expImportYouTubePageSource::fetch( $options, $target, 0 );
        $this->assertCount( 1, $this->snapshot( $target ) );
        $result = expImportYouTubePageSource::test( $options );
        $this->assertResultAndDrivers( $result, expImportYouTubePageSource::drivers() );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 3, $result['rows'] );
        $this->assertStringContainsString( 'Terms of Service', $result['message'] );
    }

    public function testThePageScraperRefusals(): void
    {
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'YouTubeSettings', 'AllowPageScraper', 'enabled' );
        foreach ( array( 'https://www.youtube.com/@examplechannel' => 'https://www.youtube.com/@examplechannel/videos',
                         'https://m.youtube.com/channel/UCexampleChannel000000aa/featured' => 'https://www.youtube.com/channel/UCexampleChannel000000aa/videos',
                         'https://www.youtube.com/@examplechannel/streams' => 'https://www.youtube.com/@examplechannel/streams',
                         'https://www.youtube.com/playlist?list=PLexample0001&index=2' => 'https://www.youtube.com/playlist?list=PLexample0001' ) as $given => $read )
            $this->assertSame( $read, expImportYouTubePageSource::pageUrl( $given ), $given );
        foreach ( array( 'https://www.youtube.com/watch?v=Ex4mpleVid1', 'https://www.youtube.com/results?search_query=tea', 'https://evil.example.invalid/@x/videos',
                         'https://www.youtube.com.example.invalid/@examplechannel', 'ftp://www.youtube.com/@examplechannel', 'https://user:pw@www.youtube.com/@examplechannel',
                         'https://www.youtube.com:8443/@examplechannel', 'https://www.youtube.com/playlist?list=' ) as $bad )
        {
            try
            {
                expImportYouTubePageSource::pageUrl( $bad );
                $this->fail( "$bad is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'YouTube page', $e->getMessage() );
            }
        }
        // robots.txt that forbids the page: not read
        list( $requests ) = $this->pageCrawler( array(
            'https://www.youtube.com/robots.txt' => array( 'status' => 200, 'headers' => array(), 'body' => "User-agent: *\nDisallow: /@\n" ),
        ) );
        $options = array( 'url' => 'https://www.youtube.com/@examplechannel/videos', 'delay' => 0.0 );
        $result = expImportYouTubePageSource::test( $options );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'robots.txt', $result['message'] );
        $this->assertSame( array( 'https://www.youtube.com/robots.txt' ), $requests->getArrayCopy() );
        // a consent page on another host, and a page without ytInitialData
        $this->pageCrawler( array(
            'https://www.youtube.com/robots.txt' => array( 'status' => 404, 'headers' => array(), 'body' => '' ),
            'https://www.youtube.com/@examplechannel/videos' => array( 'status' => 302, 'headers' => array( 'Location' => 'https://consent.youtube.com/m?continue=x' ), 'body' => '' ),
            'https://consent.youtube.com/robots.txt' => array( 'status' => 404, 'headers' => array(), 'body' => '' ),
            'https://consent.youtube.com/m?continue=x' => array( 'status' => 200, 'headers' => array(), 'body' => '<html><body>Before you continue</body></html>' ),
        ) );
        $this->assertStringContainsString( 'consent page', expImportYouTubePageSource::test( $options )['message'] );
        $this->pageCrawler( array(
            'https://www.youtube.com/robots.txt' => array( 'status' => 404, 'headers' => array(), 'body' => '' ),
            'https://www.youtube.com/@examplechannel/videos' => array( 'status' => 200, 'headers' => array(), 'body' => '<html><script>var ytInitialData = {"broken": </script></html>' ),
        ) );
        $this->assertStringContainsString( 'no ytInitialData', expImportYouTubePageSource::test( $options )['message'] );
        $this->assertNull( expImportYouTubePageSource::initialData( '<html>nothing</html>' ) );
        $this->assertSame( array( 'a' => '}"{' ), expImportYouTubePageSource::initialData( 'window["ytInitialData"] = {"a": "}\"{"};' ) );
        foreach ( array( '1,234 views' => 1234, '1.234 Aufrufe' => 1234, '980 views' => 980, 'No views' => 0, '1 view' => 1, '1.2K views' => null, '3 days ago' => null, '' => null ) as $text => $n )
            $this->assertSame( $n, expImportYouTubePageSource::viewCount( (string)$text ), (string)$text );
    }
}
