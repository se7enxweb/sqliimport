<?php

use PHPUnit\Framework\TestCase;

/**
 * The catalogue of formats: every block of the extension's kernelservices.ini tagged sqliimport.source is a valid
 * entry with a description, a group and examples, and every example file reads with its format's defaults.
 */
class CatalogueTest extends TestCase
{
    /** @return expImportSourceEntry[] id => entry, from settings/kernelservices.ini.append.php */
    public static function entries(): array
    {
        $text = (string)file_get_contents( dirname( __DIR__, 2 ) . '/settings/kernelservices.ini.append.php' );
        $out = array();
        foreach ( preg_split( '/^\[/m', $text ) as $block )
        {
            if ( strpos( $block, 'Tags[]=sqliimport.source' ) === false )
                continue;
            preg_match_all( '/^Arguments\[\]=(.*)$/m', $block, $m );
            $entry = new expImportSourceEntry( trim( $m[1][0] ), isset( $m[1][1] ) ? trim( $m[1][1] ) : '' );
            $out[$entry->id()] = $entry;
        }
        return $out;
    }

    public function testEveryFormatIsAValidEntry(): void
    {
        $entries = self::entries();
        $this->assertGreaterThanOrEqual( 8, count( $entries ) );
        foreach ( array( 'csv', 'tsv', 'json', 'ndjson', 'opml' ) as $id )
            $this->assertArrayHasKey( $id, $entries );
        foreach ( $entries as $id => $entry )
        {
            $this->assertTrue( $entry->isValid(), $id );
            $data = $entry->toArray();
            $this->assertNotSame( '', $data['description'], "$id has a description" );
            $this->assertContains( $data['group'], expImportSourceEntry::GROUPS, $id );
            $this->assertNotEmpty( $data['examples'], "$id has an example" );
            $this->assertSame( array(), expImportOptionSchema::check( $data['schema'] ), $id );
            if ( $data['preset'] )
                $this->assertNotEmpty( $data['preset']['fields'], "$id: a preset maps fields" );
        }
    }

    public function testVariantsCarryTheirDefaults(): void
    {
        $entries = self::entries();
        $this->assertSame( 'tab', $entries['tsv']->toArray()['schema']['delimiter']['default'] );
        $this->assertSame( 'ndjson', $entries['ndjson']->toArray()['schema']['format']['default'] );
        $this->assertSame( 'auto', $entries['csv']->toArray()['schema']['delimiter']['default'], 'the class itself keeps its own defaults' );
    }

    public function testEveryExampleReads(): void
    {
        foreach ( self::entries() as $id => $entry )
        {
            $data = $entry->toArray();
            foreach ( $data['examples'] as $example )
            {
                if ( !isset( $example['file'] ) )
                    continue;
                $file = dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'];
                $this->assertFileExists( $file );
                list( $values, $errors ) = expImportOptionSchema::validate( $data['schema'], isset( $example['options'] ) ? $example['options'] : array() );
                $this->assertSame( array(), $errors, "$id {$example['file']}" );
                $class = $data['class'];
                $source = new $class( $values + array( 'file' => $file ) );
                $n = 0;
                foreach ( $source->records() as $record )
                    $n++;
                $this->assertGreaterThan( 0, $n, "$id {$example['file']} has records" );
            }
        }
    }

    public function testRemoteFormatsReadTheirSnapshot(): void
    {
        // a format that reads a site itself (origin remote) has fetch() and an example of what it reads, which
        // records() reads like the snapshot of a run (testEveryExampleReads reads it)
        $remote = array();
        foreach ( self::entries() as $id => $entry )
        {
            $data = $entry->toArray();
            if ( !in_array( 'remote', $data['origins'], true ) )
                continue;
            $remote[] = $id;
            $this->assertTrue( method_exists( $data['class'], 'fetch' ), "$id reads its data with fetch()" );
            foreach ( $data['examples'] as $example )
                $this->assertArrayHasKey( 'file', $example, "$id: its example is a snapshot file" );
        }
        foreach ( array( 'drupal', 'exponential_site' ) as $id )
            $this->assertContains( $id, $remote );
    }

    public function testOpmlOutlines(): void
    {
        $file = dirname( __DIR__, 2 ) . '/share/examples/feeds.opml';
        $read = function ( $mode ) use ( $file )
        {
            $out = array();
            foreach ( ( new expImportOpmlSource( array( 'file' => $file, 'outlines' => $mode, 'key' => 'id' ) ) )->records() as $r )
                $out[] = $r;
            return $out;
        };
        $feeds = $read( 'feeds' );
        $this->assertSame( array( 'https://example.org/feed.rss', 'https://example.org/blog/atom.xml', 'https://example.net/releases.rss' ),
                           array_map( function ( $r ) { return $r->ref; }, $feeds ) );
        $this->assertSame( 'News', $feeds[0]->data['category'], 'the category is the enclosing outline' );
        $this->assertSame( 'Tech/Releases', $feeds[2]->data['category'], 'a category attribute wins' );
        $this->assertSame( 'Example blog', $feeds[1]->data['title'], 'the title falls back to the text' );
        $this->assertSame( 2, $feeds[0]->data['depth'] );
        $this->assertSame( '1.1', $feeds[0]->data['position'] );
        $this->assertCount( 4, $read( 'leaves' ) );
        $all = $read( 'all' );
        $this->assertCount( 6, $all );
        $this->assertSame( '2', $all[3]->ref, 'an outline without addresses is named by its position' );
        $this->assertSame( 'https://example.net/docs/', $all[5]->data['html_url'] );
        $rest = array();
        foreach ( ( new expImportOpmlSource( array( 'file' => $file, 'outlines' => 'feeds', 'key' => 'id' ) ) )->records( $feeds[0]->cursor ) as $r )
            $rest[] = $r->ref;
        $this->assertSame( array( 'https://example.org/blog/atom.xml', 'https://example.net/releases.rss' ), $rest );
    }

    public function testPresetLinesFitTheClass(): void
    {
        $preset = expImportWxrSource::preset();
        // an article whose image is an object relation and which has no publish date or tags (as on many sites)
        $fields = expImportEditor::presetFields( $preset, array( 'title' => 'ezstring', 'intro' => 'ezxmltext', 'body' => 'ezxmltext',
                                                                  'image' => 'ezobjectrelation' ) );
        $this->assertSame( array( 'title', 'intro', 'body', 'image' ), array_map( function ( $f ) { return $f['attribute']; }, $fields ) );
        $this->assertSame( 'media_object', $fields[3]['transforms'][0]['name'], 'an object relation gets the image as a media object' );
        $this->assertArrayNotHasKey( 'datatypes', $fields[0] );
        $fields = expImportEditor::presetFields( $preset, array( 'title' => 'ezstring', 'image' => 'ezimage', 'tags' => 'ezkeyword',
                                                                  'publish_date' => 'ezdatetime' ) );
        $this->assertCount( 4, $fields );
    }

    public function testNotOpml(): void
    {
        $this->expectException( expImportSourceException::class );
        foreach ( ( new expImportOpmlSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/books.xml' ) ) )->records() as $r )
            continue;
    }
}
