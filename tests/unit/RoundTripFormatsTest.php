<?php

use PHPUnit\Framework\TestCase;

/**
 * The round trips of the transfer registry: export files of Export (xrowextract) with their manifest, and the form
 * submissions CIE (bccie) exports.
 */
class RoundTripFormatsTest extends TestCase
{
    private static function file( string $name ): string
    {
        return dirname( __DIR__, 2 ) . '/share/examples/' . $name;
    }

    public function testXrowextractFilesWithTheirManifest(): void
    {
        foreach ( array( 'xrowextract-folders.json', 'xrowextract-folders.xml' ) as $name )
        {
            $preset = expImportXrowextractSource::presetFor( array( 'file' => self::file( $name ) ) );
            $this->assertSame( 'folder', $preset['class'], $name );
            $this->assertSame( array( 'type' => 'remote_id', 'pattern' => '{remote_id}' ), $preset['match'], $name . ': a re-import updates' );
            $this->assertSame( 'name', $preset['fields'][0]['attribute'], $name );
            $rows = array();
            foreach ( ( new expImportXrowextractSource( array( 'file' => self::file( $name ), 'key' => 'remote_id' ) ) )->records() as $r )
                $rows[$r->ref] = $r->data;
            $this->assertSame( array( 'xrx-example-1', 'xrx-example-2' ), array_keys( $rows ), $name );
            $this->assertSame( 'Teas', $rows['xrx-example-1']['name'], $name );
        }
        $this->assertSame( 'Accessories & cups', iterator_to_array( ( new expImportXrowextractSource( array( 'file' => self::file( 'xrowextract-folders.xml' ) ) ) )->records(), false )[1]->data['name'] );
    }

    public function testXrowextractCsvWithoutManifest(): void
    {
        $this->assertSame( array(), expImportXrowextractSource::presetFor( array( 'file' => self::file( 'xrowextract-folders.csv' ) ) ), 'no manifest and no class: no preset' );
        $preset = expImportXrowextractSource::presetFor( array( 'file' => self::file( 'xrowextract-folders.csv' ), 'class' => 'folder' ) );
        $this->assertSame( array( 'type' => 'remote_id', 'pattern' => '{remote_id}' ), $preset['match'] );
        $this->assertSame( array( 'name', 'short_name' ), array_map( function ( $f ) { return $f['attribute']; }, $preset['fields'] ) );
    }

    public function testCieUtf16(): void
    {
        $rows = array();
        foreach ( ( new expImportCieSource( array( 'file' => self::file( 'cie-testimonials.csv' ), 'key' => 'ID', 'delimiter' => 'auto', 'encoding' => 'auto', 'header' => true ) ) )->records() as $r )
            $rows[$r->ref] = $r->data;
        $this->assertSame( array( '101', '102', '103' ), array_map( 'strval', array_keys( $rows ) ) );
        $this->assertSame( 'Good; the sencha is great', $rows['102']['Testimonial'], 'UTF-16 read, ";" separated, quotes kept' );
        $this->assertSame( '2026-04-02 10:12', $rows['101']['Created'] );
        $fields = expImportCieSource::matchColumns( array( 'Name', 'E-mail', 'Testimonial', 'Rating', 'Created', 'ID' ),
                                                    array( 'title' => 'Name', 'email' => 'E-mail', 'text' => 'Testimonial' ) );
        $this->assertSame( array( array( 'source' => 'Name', 'attribute' => 'title' ), array( 'source' => 'E-mail', 'attribute' => 'email' ),
                                  array( 'source' => 'Testimonial', 'attribute' => 'text' ) ), $fields, 'by name when the identifier differs' );
    }
}
