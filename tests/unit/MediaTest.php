<?php

use PHPUnit\Framework\TestCase;

/**
 * The media source: files of a ZIP (or one file) with what their EXIF and IPTC say, and a reference to the file for
 * the transforms image and file.
 */
class MediaTest extends TestCase
{
    private static function read( array $config = array(), string $file = 'media.zip' ): array
    {
        $out = array();
        $source = new expImportMediaSource( $config + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $file, 'key' => 'path' ) );
        foreach ( $source->records() as $r )
            $out[$r->ref] = $r;
        return $out;
    }

    public function testPhotosWithTheirMetadata(): void
    {
        if ( !function_exists( 'exif_read_data' ) || !class_exists( 'ZipArchive' ) )
            $this->markTestSkipped( 'needs the PHP extensions exif and zip' );
        $records = self::read( array( 'types' => 'images' ) );
        $this->assertSame( array( 'media/Shop/storefront.jpg', 'media/Shop/teapot.jpg', 'media/leaves.jpg' ), array_keys( $records ), 'images only, sorted' );
        $tea = $records['media/Shop/teapot.jpg']->data;
        $this->assertSame( 'Teapot', $tea['name'], 'the IPTC object name' );
        $this->assertSame( 'Sencha brewed in a cast iron teapot. Café-style tasting on Saturday.', $tea['caption'] );
        $this->assertSame( array( 'tea', 'sencha', 'teapot' ), $tea['keywords'] );
        $this->assertSame( 'Example Photographer', $tea['author'] );
        $this->assertSame( 'Hamburg', $tea['city'] );
        $this->assertSame( '2026-04-02T16:40:12', $tea['date_taken'] );
        $this->assertSame( 'ExampleCam X100', $tea['camera'] );
        $this->assertSame( 320, $tea['width'] );
        $this->assertSame( 'image/jpeg', $tea['mime'] );
        $this->assertEqualsWithDelta( 53.5503, $tea['gps_lat'], 0.0001 );
        $this->assertEqualsWithDelta( 9.9921, $tea['gps_lon'], 0.0001 );
        $this->assertSame( 'media/Shop', $tea['folder'] );
        $this->assertSame( 'source:media/Shop/teapot.jpg', $tea['media'] );
        $this->assertSame( 40, strlen( $tea['sha1'] ) );
        $this->assertCount( 4, self::read( array( 'types' => 'all' ) ), 'every file' );
        $this->assertSame( 'Price list', self::read( array( 'types' => 'all' ) )['media/Shop/price-list.txt']->data['name'], 'a file without metadata is named by its file name' );
        $this->assertCount( 2, self::read( array( 'types' => 'images', 'folder' => 'Shop' ) ) );
    }

    public function testOnePhotoAndResume(): void
    {
        if ( !function_exists( 'exif_read_data' ) || !class_exists( 'ZipArchive' ) )
            $this->markTestSkipped( 'needs the PHP extensions exif and zip' );
        $one = array_values( self::read( array( 'original_name' => 'teapot.jpg' ), 'photo.jpg' ) );
        $this->assertCount( 1, $one );
        $this->assertSame( 'teapot.jpg', $one[0]->ref );
        $this->assertSame( 'source:', $one[0]->data['media'], 'a single file is the source itself' );
        $records = array_values( self::read() );
        $rest = array();
        foreach ( ( new expImportMediaSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/media.zip', 'key' => 'path' ) ) )->records( $records[0]->cursor ) as $r )
            $rest[] = $r->ref;
        $this->assertSame( array( 'media/Shop/teapot.jpg', 'media/leaves.jpg' ), $rest );
    }

    public function testDegrees(): void
    {
        $this->assertEqualsWithDelta( 53.5511, expImportMediaSource::degrees( array( '53/1', '33/1', '403/100' ) ), 0.0001 );
        $this->assertNull( expImportMediaSource::degrees( array( '1/0', '0/1', '0/1' ) ) );
    }
}
