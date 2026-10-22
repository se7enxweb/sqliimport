<?php

use PHPUnit\Framework\TestCase;

/**
 * The spreadsheet source (XLSX and ODS, read without a library) and the one-line text of a transform chain that the
 * mapping editor shows and reads.
 */
class SpreadsheetAndChainTest extends TestCase
{
    private static function rows( string $file, array $config = array() ): array
    {
        $source = new expImportSpreadsheetSource( $config + array( 'file' => dirname( __DIR__ ) . '/fixtures/' . $file, 'key' => 'id' ) );
        $out = array();
        foreach ( $source->records() as $record )
            $out[] = $record;
        return $out;
    }


    public function testRowsOfTheFirstSheetKeyedByTheHeader(): void
    {
        foreach ( array( 'products.xlsx', 'products.ods' ) as $file )
        {
        $rows = self::rows( $file );
        $this->assertSame( array( '1', '2', '3' ), array_map( function ( $r ) { return $r->ref; }, $rows ), 'empty rows are left out' );
        $this->assertSame( 'Café crème', $rows[0]->data['title'] );
        $this->assertSame( '9.5', $rows[0]->data['price'] );
        $this->assertSame( '1', $rows[0]->data['active'] );
        $this->assertSame( '', $rows[1]->data['price'], 'an empty cell between others is an empty value' );
        $this->assertSame( array( 'id', 'title', 'price', 'active' ), array_keys( $rows[0]->data ), $file );
        }
    }

    public function testAnotherSheetByNameAndResumeAfterACursor(): void
    {
        foreach ( array( 'products.xlsx', 'products.ods' ) as $file )
        {
        $other = self::rows( $file, array( 'sheet' => 'Other', 'key' => 'code' ) );
        $this->assertSame( $file === 'products.xlsx' ? array( 'X' ) : array(), array_map( function ( $r ) { return $r->ref; }, $other ), 'the sheet Other' );
        $all = self::rows( $file );
        $rest = array();
        foreach ( ( new expImportSpreadsheetSource( array( 'file' => dirname( __DIR__ ) . '/fixtures/' . $file, 'key' => 'id' ) ) )->records( $all[0]->cursor ) as $r )
            $rest[] = $r->ref;
        $this->assertSame( array( '2', '3' ), $rest, $file );
        }
    }

    public function testAMissingSheetIsASourceError(): void
    {
        $this->expectException( expImportSourceException::class );
        self::rows( 'products.xlsx', array( 'sheet' => 'Nope' ) );
    }

    public function testSpreadsheetDates(): void
    {
        $ts = expImportTransforms::applyChain( '45658.5', array( array( 'name' => 'date', 'args' => array( 'format' => 'excel' ) ) ) );
        $this->assertSame( '2025-01-01 12:00', gmdate( 'Y-m-d H:i', (int)$ts ) );
    }

    public function testColumnLetters(): void
    {
        $this->assertSame( 0, expImportSpreadsheetSource::columnIndex( 'A1' ) );
        $this->assertSame( 25, expImportSpreadsheetSource::columnIndex( 'Z9' ) );
        $this->assertSame( 27, expImportSpreadsheetSource::columnIndex( 'AB3' ) );
    }

    public function testChainTextRoundTrip(): void
    {
        $chain = array(
            array( 'name' => 'trim' ),
            array( 'name' => 'date', 'args' => array( 'format' => 'd.m.Y', 'timezone' => 'Europe/Berlin' ) ),
            array( 'name' => 'map', 'args' => array( 'table' => array( 'D' => 'Germany', 'F' => 'France, the country' ) ) ),
            array( 'name' => 'replace', 'args' => array( 'search' => ',', 'replace' => ' | ' ) ),
        );
        $text = expImportTransforms::formatChain( $chain );
        $this->assertSame( 'trim | date(format=d.m.Y, timezone=Europe/Berlin) | map(table={"D":"Germany","F":"France, the country"}) | replace(search=",", replace=" | ")', $text );
        $this->assertSame( $chain, expImportTransforms::parseChain( $text ) );
        $this->assertSame( array(), expImportTransforms::parseChain( '  ' ) );
    }

    public function testSpacesInTextArgumentsCount(): void
    {
        $chain = expImportTransforms::parseChain( 'replace(search=" GmbH", replace="") | prefix(text="Firm: ")' );
        $this->assertSame( 'Firm: Acme', expImportTransforms::applyChain( 'Acme GmbH', $chain ) );
    }

    public function testChainTextErrors(): void
    {
        $this->expectException( expImportValidationException::class );
        expImportTransforms::parseChain( 'trim | date(format)' );
    }
}
