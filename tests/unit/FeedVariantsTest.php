<?php

use PHPUnit\Framework\TestCase;

/**
 * Podcast feeds and YouTube / Vimeo channel feeds: the RSS source's episode and video fields.
 */
class FeedVariantsTest extends TestCase
{
    private static function read( string $file ): array
    {
        $out = array();
        foreach ( ( new expImportRssSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $file ) ) )->records() as $r )
            $out[] = $r->data;
        return $out;
    }

    public function testPodcast(): void
    {
        $episodes = self::read( 'podcast.rss' );
        $this->assertCount( 2, $episodes );
        $this->assertSame( 'https://podcast.example.org/audio/episode-2.mp3', $episodes[0]['enclosure'] );
        $this->assertSame( 'audio/mpeg', $episodes[0]['enclosure_type'] );
        $this->assertSame( '18734012', $episodes[0]['enclosure_length'] );
        $this->assertSame( '2301', $episodes[0]['duration'] );
        $this->assertSame( '3723', $episodes[1]['duration'] );
        $this->assertSame( '2', $episodes[0]['episode'] );
        $this->assertFalse( $episodes[0]['explicit'] );
        $this->assertSame( 'https://podcast.example.org/episode-2.jpg', $episodes[0]['image'] );
    }

    public function testYouTubeAndVimeo(): void
    {
        $yt = self::read( 'youtube.xml' )[0];
        $this->assertSame( 'EXAMPLE0001', $yt['video_id'] );
        $this->assertSame( 'https://www.youtube.com/embed/EXAMPLE0001', $yt['embed_url'] );
        $this->assertSame( 'https://www.youtube.com/watch?v=EXAMPLE0001', $yt['link'] );
        $this->assertSame( 'https://i.ytimg.com/vi/EXAMPLE0001/hqdefault.jpg', $yt['image'] );
        $this->assertSame( 'Water at 70 degrees, one minute.', $yt['summary'] );
        $this->assertSame( '4321', $yt['views'] );
        $this->assertSame( 'UCexample0000000000000000', $yt['channel_id'] );
        $vimeo = self::read( 'vimeo.rss' )[0];
        $this->assertSame( '123456789', $vimeo['video_id'] );
        $this->assertSame( 'https://player.vimeo.com/video/123456789', $vimeo['embed_url'] );
    }

    public function testDurations(): void
    {
        $this->assertSame( '90', expImportRssSource::seconds( '90' ) );
        $this->assertSame( '62', expImportRssSource::seconds( '1:02' ) );
        $this->assertSame( '', expImportRssSource::seconds( 'soon' ) );
    }
}
