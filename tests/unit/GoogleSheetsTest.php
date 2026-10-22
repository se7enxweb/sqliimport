<?php

use PHPUnit\Framework\TestCase;

/**
 * The link of a Google Sheets spreadsheet turned into its CSV export.
 */
class GoogleSheetsTest extends TestCase
{
    public function testLinks(): void
    {
        $this->assertSame( 'https://docs.google.com/spreadsheets/d/AbC-123_x/export?format=csv&gid=42',
                           expImportGoogleSheetsSource::downloadUrl( 'https://docs.google.com/spreadsheets/d/AbC-123_x/edit#gid=42' ) );
        $this->assertSame( 'https://docs.google.com/spreadsheets/d/AbC-123_x/export?format=csv',
                           expImportGoogleSheetsSource::downloadUrl( 'https://docs.google.com/spreadsheets/d/AbC-123_x/edit?usp=sharing' ) );
        $this->assertSame( 'https://docs.google.com/spreadsheets/d/e/2PACX-1v/pub?output=csv&gid=0',
                           expImportGoogleSheetsSource::downloadUrl( 'https://docs.google.com/spreadsheets/d/e/2PACX-1v/pubhtml?gid=0&single=true' ) );
        $this->assertSame( 'https://example.org/data.csv', expImportGoogleSheetsSource::downloadUrl( 'https://example.org/data.csv' ), 'other addresses stay' );
    }
}
