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
            // a format that reads its data itself (a database, a mailbox) has no file to read: testRemoteFormats
            if ( $data['origins'] === array( 'remote' ) )
                continue;
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

    public function testRemoteFormats(): void
    {
        // a format that reads its data itself (origin remote) has fetch(); its example is either a snapshot of what it
        // reads (a file, which testEveryExampleReads reads like a run's snapshot) or a configuration: a label and
        // options the schema accepts, naming secrets that do not exist
        $remote = array();
        foreach ( self::entries() as $id => $entry )
        {
            $data = $entry->toArray();
            if ( !in_array( 'remote', $data['origins'], true ) )
                continue;
            $remote[] = $id;
            $this->assertTrue( method_exists( $data['class'], 'fetch' ), "$id reads its data itself" );
            $this->assertNotEmpty( $data['examples'], "$id has an example" );
            foreach ( $data['examples'] as $example )
            {
                $this->assertNotSame( '', trim( (string)( $example['label'] ?? '' ) ), "$id: an example says what it is" );
                if ( isset( $example['file'] ) )
                    $this->assertFileExists( dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'] );
                else
                    $this->assertNotEmpty( $example['options'] ?? array(), "$id: an example without a file gives its options" );
                if ( !empty( $example['options'] ) )
                {
                    list( , $errors ) = expImportOptionSchema::validate( $data['schema'], $example['options'] );
                    $this->assertSame( array(), $errors, "$id: the options of an example are valid" );
                }
                foreach ( $data['schema'] as $name => $def )
                    if ( ( $def['type'] ?? '' ) === 'secret' && isset( $example['options'][$name] ) )
                        $this->assertNull( expImportSecrets::get( $example['options'][$name] ), "$id: an example names a secret, it has none" );
            }
        }
        foreach ( array( 'sql', 'imap', 'sitemap', 'webpage', 'drupal', 'exponential_site', 'airtable', 'notion', 'ldap' ) as $id )
            $this->assertContains( $id, $remote, "$id reads its data itself" );
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

    /**
     * A format with a static test() gets the "Test connection" button of the source step, and one with a static
     * drivers() lists them in the catalogue and next to that button; every driver row has what the template reads.
     */
    public function testConnectionTestsAndDrivers(): void
    {
        $tested = array();
        foreach ( self::entries() as $id => $entry )
        {
            $data = $entry->toArray();
            $this->assertSame( method_exists( $data['class'], 'test' ), $data['can_test'], $id );
            $this->assertSame( method_exists( $data['class'], 'drivers' ), $data['has_drivers'], $id );
            if ( $data['can_test'] )
                $tested[] = $id;
            if ( !$data['has_drivers'] )
                continue;
            foreach ( (array)call_user_func( array( $data['class'], 'drivers' ) ) as $driver )
                foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                    $this->assertArrayHasKey( $key, $driver, "$id: a driver has $key" );
        }
        $this->assertContains( 'sql', $tested );
        $root = dirname( __DIR__, 2 ) . '/design/admin/templates/sqliimport/';
        $this->assertFileExists( $root . 'edit/source_test.tpl' );
        $this->assertStringContainsString( 'source_test.tpl', (string)file_get_contents( $root . 'edit/source.tpl' ) );
    }
}
