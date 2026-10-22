<?php

use PHPUnit\Framework\TestCase;

/**
 * The file sources read their fixtures as the engine does: every record, its reference, and a resume from the
 * cursor of a record, which is what a chunk of a run does.
 */
class SourcesTest extends TestCase
{
    private static function fixture( string $name ): string
    {
        return dirname( __DIR__ ) . '/fixtures/' . $name;
    }

    /** @return expImportRecord[] */
    private static function all( expImportSourceInterface $source, array $cursor = array() ): array
    {
        $out = array();
        foreach ( $source->records( $cursor ) as $record )
            $out[] = $record;
        return $out;
    }

    public function testCsvDetectsTheDelimiterAndReadsQuotedFields(): void
    {
        $source = new expImportCsvSource( array( 'file' => self::fixture( 'products.csv' ), 'key' => 'id' ) );
        $records = self::all( $source );
        $this->assertCount( 3, $records );
        $this->assertSame( array( 'id', 'title', 'body', 'price' ), array_keys( $records[0]->data ), 'the byte order mark is not part of the first column' );
        $this->assertSame( 'First; with semicolon', $records[0]->data['title'] );
        $this->assertSame( "line one\nline two", $records[0]->data['body'] );
        $this->assertSame( '1', $records[0]->ref );
        $this->assertSame( 'say "hi"', $records[2]->data['body'] );
        $this->assertSame( 3, $source->count() );
        $sample = $source->sample( 2 );
        $this->assertSame( ';', $sample['detected']['delimiter'] );
    }

    public function testCsvKeepsAnEqualsSignInAValue(): void
    {
        // upstream PR #7: "=" in option and field values
        $records = self::all( new expImportCsvSource( array( 'file' => self::fixture( 'products.csv' ) ) ) );
        $this->assertSame( 'a=b=c', $records[1]->data['body'] );
    }

    public function testCsvResumesAfterTheCursorOfARecord(): void
    {
        $source = new expImportCsvSource( array( 'file' => self::fixture( 'products.csv' ), 'key' => 'id' ) );
        $records = self::all( $source );
        $rest = self::all( $source, $records[0]->cursor );
        $this->assertSame( array( '2', '3' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
        $this->assertSame( 1, $rest[0]->index );
    }

    public function testCsvConvertsWindows1252ToUtf8(): void
    {
        $records = self::all( new expImportCsvSource( array( 'file' => self::fixture( 'latin1.csv' ) ) ) );
        $this->assertSame( 'Café', $records[0]->data['name'] );
        $this->assertSame( '#1', $records[0]->ref, 'without a key field the reference is the position' );
    }

    public function testAnEmptySourceHasNoRecords(): void
    {
        // upstream PR #11: an empty source completes with 0 rows
        $source = new expImportCsvSource( array( 'file' => self::fixture( 'empty.csv' ) ) );
        $this->assertSame( array(), self::all( $source ) );
        $this->assertSame( 0, $source->count() );
    }

    public function testAMissingFileIsASourceError(): void
    {
        $this->expectException( expImportSourceException::class );
        self::all( new expImportCsvSource( array( 'file' => self::fixture( 'nope.csv' ) ) ) );
    }

    public function testXmlReadsTheRecordElementsAsArrays(): void
    {
        $source = new expImportXmlSource( array( 'file' => self::fixture( 'books.xml' ), 'record_path' => 'catalog/book', 'key' => '@id' ) );
        $records = self::all( $source );
        $this->assertCount( 3, $records );
        $this->assertSame( 'b1', $records[0]->ref );
        $this->assertSame( 'Dune', $records[0]->get( 'title' ) );
        $this->assertSame( 'Frank Herbert', $records[0]->get( 'dc:creator' ) );
        $this->assertSame( array( 'sf', 'classic' ), $records[0]->get( 'tag' ) );
        $this->assertSame( 'EUR', $records[0]->get( 'price.@currency' ) );
        $this->assertSame( '9.90', $records[0]->get( 'price.#text' ) );
        $this->assertSame( 'Neuromancer & co', $records[1]->get( 'title' ) );
        $this->assertSame( array( '@id' => 'b3' ), $records[2]->data );
        $rest = self::all( $source, $records[1]->cursor );
        $this->assertSame( array( 'b3' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
        $this->assertSame( 3, $source->count() );
    }

    public function testXmlWithABrokenFileFails(): void
    {
        $dir = sys_get_temp_dir() . '/sqliimport-test-' . getmypid();
        @mkdir( $dir );
        file_put_contents( "$dir/bad.xml", '<a><b>1</b><b>2</a>' );
        $this->expectException( expImportSourceException::class );
        try
        {
            self::all( new expImportXmlSource( array( 'file' => "$dir/bad.xml", 'record_path' => 'b' ) ) );
        }
        finally
        {
            unlink( "$dir/bad.xml" );
            rmdir( $dir );
        }
    }

    public function testRssItemsHaveTheCommonFields(): void
    {
        $records = self::all( new expImportRssSource( array( 'file' => self::fixture( 'feed.rss' ), 'key' => 'id' ) ) );
        $this->assertCount( 2, $records );
        $this->assertSame( 'n-1', $records[0]->ref );
        $this->assertSame( 'Hello', $records[0]->data['title'] );
        $this->assertSame( array( 'a', 'b' ), $records[0]->data['categories'] );
        $this->assertSame( 'https://example.invalid/1.jpg', $records[0]->data['enclosure'] );
        $this->assertSame( 'Editor', $records[0]->data['author'] );
        $this->assertStringContainsString( 'mailto:x@example.invalid?subject=Hi', $records[0]->data['content'] );
        $this->assertSame( 'https://example.invalid/2', $records[1]->ref, 'an item without a guid is named by its link' );
    }

    public function testAtomEntriesHaveTheSameFields(): void
    {
        $records = self::all( new expImportRssSource( array( 'file' => self::fixture( 'feed.atom' ), 'key' => 'id' ) ) );
        $this->assertCount( 1, $records );
        $this->assertSame( 'urn:uuid:1', $records[0]->ref );
        $this->assertSame( 'https://example.invalid/e1', $records[0]->data['link'] );
        $this->assertSame( 'Ann', $records[0]->data['author'] );
        $this->assertSame( array( 'news' ), $records[0]->data['categories'] );
        $this->assertSame( '2026-10-20T08:00:00Z', $records[0]->data['date'] );
    }

    public function testJsonReadsTheListAtARecordPath(): void
    {
        $source = new expImportJsonSource( array( 'file' => self::fixture( 'items.json' ), 'record_path' => 'data.items', 'key' => 'id' ) );
        $records = self::all( $source );
        $this->assertSame( array( '1', '2' ), array_map( function ( $r ) { return $r->ref; }, $records ) );
        $this->assertSame( 'Bo', $records[1]->get( 'author.name' ) );
        $this->assertSame( 2, $source->count() );
        $this->assertContains( 'tags', expImportRecord::paths( $records[0]->data ) );
    }

    public function testNdjsonIsReadAsAStreamAndResumes(): void
    {
        $source = new expImportJsonSource( array( 'file' => self::fixture( 'items.ndjson' ), 'key' => 'id' ) );
        $records = self::all( $source );
        $this->assertSame( array( 'a', 'b', 'c' ), array_map( function ( $r ) { return $r->ref; }, $records ) );
        $rest = self::all( $source, $records[0]->cursor );
        $this->assertSame( array( 'b', 'c' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
        $this->assertSame( 3, $source->count() );
    }
}
