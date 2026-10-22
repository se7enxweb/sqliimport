<?php

use PHPUnit\Framework\TestCase;

/**
 * Exponential packages (.ezpkg) as a source: the objects of the example package, their attributes in the fromString()
 * form, the preset made from the package, and archives with entries outside them refused.
 */
class PackageSourceTest extends TestCase
{
    protected static function file(): string
    {
        return dirname( __DIR__, 2 ) . '/share/examples/exponential-package.ezpkg';
    }

    public function testObjectsOfThePackage(): void
    {
        $records = array();
        foreach ( ( new expImportEzpkgSource( array( 'file' => self::file(), 'key' => 'remote_id' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( 'exp-example-folder-news', 'exp-example-article-garden', 'exp-example-article-bikes', 'exp-example-article-market' ),
                           array_keys( $records ) );
        $garden = $records['exp-example-article-garden'];
        $this->assertSame( 'article', $garden['class_identifier'] );
        $this->assertSame( 'A garden in the city', $garden['name'] );
        $this->assertSame( 'eng-GB', $garden['language'] );
        $this->assertSame( 'exp-example-node-news', $garden['parent_node_remote_id'] );
        $this->assertSame( 1790100000, $garden['published'] );
        $this->assertSame( 'A garden in the city', $garden['fields']['title'] );
        $this->assertSame( '1', $garden['fields']['enable_comments'] );
        $this->assertSame( 'source:simplefiles/1a2b3c4d.jpg', $garden['fields']['image'] );
        $this->assertSame( 'A green example garden', $garden['fields']['image_alt'] );
        $this->assertStringContainsString( '<paragraph>The second paragraph.</paragraph>', $garden['fields']['body'] );
        $this->assertStringStartsWith( '<?xml', $garden['fields']['body'] );
        $this->assertSame( 'ezxmltext', $garden['field_types']['body'] );
        $this->assertSame( '', $records['exp-example-article-bikes']['fields']['image'], 'an image attribute without a file is empty' );
        $this->assertStringContainsString( 'Fruit, bread &amp; cheese', $records['exp-example-article-market']['fields']['intro'] );
    }

    public function testClassOptionCursorAndMember(): void
    {
        $source = new expImportEzpkgSource( array( 'file' => self::file(), 'class' => 'article', 'key' => 'remote_id' ) );
        $refs = array();
        $first = null;
        foreach ( $source->records() as $record )
        {
            $refs[] = $record->ref;
            $first = $first ?: $record;
        }
        $this->assertSame( array( 'exp-example-article-garden', 'exp-example-article-bikes', 'exp-example-article-market' ), $refs );
        $rest = array();
        foreach ( $source->records( $first->cursor ) as $record )
            $rest[] = $record->ref;
        $this->assertSame( array_slice( $refs, 1 ), $rest );
        $this->assertSame( 3, $source->count() );
        $image = expImportEzpkgSource::member( self::file(), 'simplefiles/1a2b3c4d.jpg' );
        $this->assertSame( "\xFF\xD8", substr( (string)$image, 0, 2 ), 'the image of the package is a JPEG' );
        $this->assertNull( expImportEzpkgSource::member( self::file(), '../package.xml' ) );
        $this->assertNull( expImportEzpkgSource::member( self::file(), 'no/such/file' ) );
    }

    public function testPresetFromThePackage(): void
    {
        $preset = expImportEzpkgSource::presetFor( array( 'file' => self::file(), 'class' => 'article' ) );
        $this->assertSame( 'article', $preset['class'] );
        $this->assertSame( array( 'type' => 'remote_id', 'pattern' => '{remote_id}' ), $preset['match'] );
        $byAttribute = array();
        foreach ( $preset['fields'] as $field )
            $byAttribute[$field['attribute']] = isset( $byAttribute[$field['attribute']] ) ? $byAttribute[$field['attribute']] : $field;
        $this->assertSame( array( 'title', 'short_title', 'intro', 'body', 'image', 'caption', 'enable_comments' ), array_keys( $byAttribute ) );
        $this->assertSame( 'fields.body', $byAttribute['body']['source'] );
        $this->assertSame( 'image', $byAttribute['image']['transforms'][0]['name'] );
        $relation = expImportEditor::presetFields( $preset, array( 'title' => 'ezstring', 'image' => 'ezobjectrelation' ) );
        $this->assertSame( 'media_object', $relation[1]['transforms'][0]['name'], 'an object relation gets the image as an image object' );
        $this->assertSame( array(), expImportEzpkgSource::presetFor( array( 'file' => self::file() ) ), 'a folder and articles have no common class' );
    }

    public function testNamesOutsideTheArchiveAreRefused(): void
    {
        foreach ( array( '../x', 'a/../../x', '/etc/passwd', 'C:x', "a\0b", '..\\x' ) as $name )
            $this->assertFalse( expImportEzpkgSource::safeName( $name ), $name );
        foreach ( array( 'package.xml', 'ezcontentobject/object-1.xml', 'a/b..c' ) as $name )
            $this->assertTrue( expImportEzpkgSource::safeName( $name ), $name );
    }

    /** @return string a tar block for a file */
    protected static function tarEntry( string $name, string $data, string $type = '0' ): string
    {
        $header = str_pad( $name, 100, "\0" ) . str_pad( '0000644', 8, "\0" ) . str_pad( '0000000', 8, "\0" ) . str_pad( '0000000', 8, "\0" )
                . str_pad( sprintf( '%011o', strlen( $data ) ), 12, "\0" ) . str_pad( sprintf( '%011o', 1790000000 ), 12, "\0" ) . '        ' . $type
                . str_repeat( "\0", 100 ) . "ustar\0" . '00' . str_repeat( "\0", 247 );
        $sum = 0;
        for ( $i = 0; $i < 512; $i++ )
            $sum += ord( $header[$i] );
        $header = substr_replace( $header, sprintf( '%06o', $sum ) . "\0 ", 148, 8 );
        return $header . str_pad( $data, (int)ceil( strlen( $data ) / 512 ) * 512, "\0" );
    }

    public function testArchivesAreCheckedAndReadWithoutThePharWrapper(): void
    {
        $file = tempnam( sys_get_temp_dir(), 'sqliimport-test-' );
        $read = function ( string $tar ) use ( $file )
        {
            file_put_contents( $file, gzencode( $tar . str_repeat( "\0", 1024 ) ) );
            $n = 0;
            foreach ( ( new expImportEzpkgSource( array( 'file' => $file ) ) )->records() as $record )
                $n++;
            return $n;
        };
        $object = (string)file_get_contents( 'compress.zlib://' . self::file() );
        $cases = array(
            'an entry outside the archive' => self::tarEntry( '../evil.xml', '<x/>' ) . self::tarEntry( 'package.xml', '<package/>' ),
            'an absolute entry' => self::tarEntry( '/etc/evil.xml', '<x/>' ),
            'a long name outside the archive' => self::tarEntry( '././@LongLink', '../../evil.xml', 'L' ) . self::tarEntry( 'short', '<x/>' ),
            'a broken header' => substr_replace( self::tarEntry( 'package.xml', '<package/>' ), 'XXXXXXXX', 148, 8 ),
            'a JSON package' => self::tarEntry( 'package.json', '{}' ),
            'a package without objects' => self::tarEntry( 'package.xml', '<package><install/></package>' ),
        );
        $hadPhar = in_array( 'phar', stream_get_wrappers(), true );
        if ( $hadPhar )
            stream_wrapper_unregister( 'phar' );
        try
        {
            foreach ( $cases as $what => $tar )
            {
                try
                {
                    $read( $tar );
                    $this->fail( "$what is refused" );
                }
                catch ( expImportSourceException $e )
                {
                    $this->assertNotSame( '', $e->getMessage(), $what );
                }
            }
            // the example package reads as the kernel's bootstrap leaves PHP: without the phar wrapper
            $this->assertSame( 4, ( new expImportEzpkgSource( array( 'file' => self::file() ) ) )->count() );
            $this->assertNotNull( expImportEzpkgSource::member( self::file(), 'simplefiles/1a2b3c4d.jpg' ) );
            // the same package one directory deeper in the archive (as a hand-made tar.gz often is)
            $nested = '';
            for ( $pos = 0; $pos + 512 <= strlen( $object ); )
            {
                $header = substr( $object, $pos, 512 );
                if ( trim( $header, "\0" ) === '' )
                    break;
                $size = (int)octdec( trim( substr( $header, 124, 12 ), " \0" ) );
                $name = rtrim( substr( $header, 0, 100 ), "\0" );
                if ( $header[156] === '0' || $header[156] === "\0" )
                    $nested .= self::tarEntry( 'exp_example_articles/' . $name, substr( $object, $pos + 512, $size ) );
                $pos += 512 + (int)ceil( $size / 512 ) * 512;
            }
            $this->assertSame( 4, $read( $nested ) );
            $garden = null;
            foreach ( ( new expImportEzpkgSource( array( 'file' => $file, 'class' => 'article' ) ) )->records() as $record )
            {
                $garden = $record->data;
                break;
            }
            $this->assertSame( 'source:exp_example_articles/simplefiles/1a2b3c4d.jpg', $garden['fields']['image'] );
            $this->assertNotNull( expImportEzpkgSource::member( $file, substr( $garden['fields']['image'], 7 ) ) );
        }
        finally
        {
            if ( $hadPhar )
                stream_wrapper_restore( 'phar' );
            unlink( $file );
        }
    }

    public function testNotAPackage(): void
    {
        $this->expectException( expImportSourceException::class );
        foreach ( ( new expImportEzpkgSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/articles.json' ) ) )->records() as $record )
            continue;
    }
}
