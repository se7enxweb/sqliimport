<?php
/**
 * File containing the expImportGoogleSheetsSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A Google Sheets spreadsheet by its address: the link of the sheet (shared so that anyone with the link can view) or
 * its "Publish to the web" link. The address is turned into the sheet's CSV export (downloadUrl()), which is read as
 * a CSV file with a header line. The tab is the one in the link (#gid=...), else the first.
 */
class expImportGoogleSheetsSource extends expImportCsvSource
{
    public static function identifier()
    {
        return 'google_sheets';
    }

    public static function label()
    {
        return 'Google Sheets (by its link)';
    }

    public static function extensions()
    {
        return array( 'csv' );
    }

    public static function description()
    {
        return 'A spreadsheet in Google Sheets, read at every run from its link: share it so that anyone with the link can '
             . 'view it, or publish it to the web, and give the link as the address. The tab in the link is read, its first '
             . 'row names the columns.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'What a sheet with three products gives (its CSV export)', 'file' => 'products.csv' ) );
    }

    public static function schema()
    {
        $schema = parent::schema();
        $schema['delimiter']['default'] = ',';
        $schema['encoding']['default'] = 'UTF-8';
        return $schema;
    }

    /**
     * The address to download: the CSV export of a sheet's link.
     *
     *   .../spreadsheets/d/<id>/edit#gid=<gid>          > .../spreadsheets/d/<id>/export?format=csv&gid=<gid>
     *   .../spreadsheets/d/e/<pub id>/pubhtml?gid=<gid> > .../spreadsheets/d/e/<pub id>/pub?output=csv&gid=<gid>
     *
     * @param string $url
     * @return string
     */
    public static function downloadUrl( $url )
    {
        $url = trim( (string)$url );
        if ( !preg_match( '#^https://docs\.google\.com/spreadsheets/d/(e/)?([A-Za-z0-9_-]+)#', $url, $m ) )
            return $url;
        $gid = preg_match( '/[#?&]gid=(\d+)/', $url, $g ) ? $g[1] : '';
        if ( $m[1] === 'e/' )
            return 'https://docs.google.com/spreadsheets/d/e/' . $m[2] . '/pub?output=csv' . ( $gid !== '' ? '&gid=' . $gid : '' );
        return 'https://docs.google.com/spreadsheets/d/' . $m[2] . '/export?format=csv' . ( $gid !== '' ? '&gid=' . $gid : '' );
    }
}
