<?php
/**
 * File containing the expImportSpreadsheetSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Spreadsheets: Excel (XLSX) and OpenDocument (ODS) files, read without a library: both are ZIP files of XML, read
 * as streams (ext-zip and XMLReader). One record per row of the chosen sheet, keyed by the header row. Numbers come
 * as they are stored; an Excel date is a number of days (the transform date reads it), an ODS date its ISO value.
 * Formulas give their last computed value. Empty rows are left out.
 */
class expImportSpreadsheetSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'spreadsheet';
    }

    public static function label()
    {
        return 'Spreadsheet (XLSX, ODS)';
    }

    public static function extensions()
    {
        return array( 'xlsx', 'ods' );
    }

    public static function schema()
    {
        return array(
            'sheet' => array( 'type' => 'string', 'label' => 'Sheet', 'default' => '1',
                              'help' => 'The name of the sheet, or its number (1 is the first)' ),
            'header' => array( 'type' => 'bool', 'label' => 'The first row names the columns', 'default' => true ),
        ) + parent::commonSchema();
    }

    /** @return string xlsx or ods */
    protected function format()
    {
        $zip = $this->zip();
        $format = $zip->locateName( 'xl/workbook.xml' ) !== false ? 'xlsx' : ( $zip->locateName( 'content.xml' ) !== false ? 'ods' : '' );
        $zip->close();
        if ( $format === '' )
            throw new expImportSourceException( 'the file is neither an XLSX nor an ODS spreadsheet' );
        $this->detected['format'] = $format;
        return $format;
    }

    /** @return ZipArchive */
    protected function zip()
    {
        if ( !class_exists( 'ZipArchive' ) )
            throw new expImportSourceException( 'reading spreadsheets needs the PHP extension zip' );
        $zip = new ZipArchive();
        if ( $zip->open( $this->file() ) !== true )
            throw new expImportSourceException( 'the spreadsheet cannot be opened (not a ZIP file)' );
        return $zip;
    }

    public function records( array $cursor = array() )
    {
        $rows = $this->format() === 'xlsx' ? $this->xlsxRows() : $this->odsRows();
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $useHeader = !isset( $this->config['header'] ) || $this->config['header'];
        $headers = null;
        $index = 0;
        foreach ( $rows as $cells )
        {
            if ( !array_filter( $cells, function ( $v ) { return trim( (string)$v ) !== ''; } ) )
                continue;
            if ( $useHeader && $headers === null )
            {
                $headers = array();
                foreach ( $cells as $i => $name )
                {
                    $name = trim( (string)$name );
                    $base = $name !== '' ? $name : 'col_' . ( $i + 1 );
                    $name = $base;
                    $n = 2;
                    while ( in_array( $name, $headers, true ) )
                        $name = $base . '_' . $n++;
                    $headers[$i] = $name;
                }
                continue;
            }
            if ( $index < $skip )
            {
                $index++;
                continue;
            }
            $data = array();
            $width = max( count( $cells ), $headers ? max( array_keys( $headers ) ) + 1 : 0 );
            for ( $i = 0; $i < $width; $i++ )
            {
                $name = $headers !== null ? ( isset( $headers[$i] ) ? $headers[$i] : 'col_' . ( $i + 1 ) ) : (string)$i;
                $data[$name] = isset( $cells[$i] ) ? (string)$cells[$i] : '';
            }
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
            $index++;
        }
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }

    // ---- XLSX ----------------------------------------------------------------------------------------------------

    /** @return string the path of the chosen sheet inside the ZIP file */
    protected function xlsxSheetPath( ZipArchive $zip )
    {
        $want = isset( $this->config['sheet'] ) ? trim( (string)$this->config['sheet'] ) : '1';
        $workbook = simplexml_load_string( (string)$zip->getFromName( 'xl/workbook.xml' ), 'SimpleXMLElement', LIBXML_NONET );
        $rels = simplexml_load_string( (string)$zip->getFromName( 'xl/_rels/workbook.xml.rels' ), 'SimpleXMLElement', LIBXML_NONET );
        if ( !$workbook || !$rels )
            throw new expImportSourceException( 'the workbook of the XLSX file cannot be read' );
        $targets = array();
        foreach ( $rels->Relationship as $rel )
            $targets[(string)$rel['Id']] = (string)$rel['Target'];
        $sheets = array();
        foreach ( $workbook->sheets->sheet as $sheet )
        {
            $rid = (string)$sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
            $sheets[] = array( 'name' => (string)$sheet['name'], 'target' => isset( $targets[$rid] ) ? $targets[$rid] : '' );
        }
        if ( !$sheets )
            throw new expImportSourceException( 'the XLSX file has no sheet' );
        $this->detected['sheets'] = implode( ', ', array_map( function ( $s ) { return $s['name']; }, $sheets ) );
        $chosen = null;
        foreach ( $sheets as $i => $s )
            if ( $s['name'] === $want || ( ctype_digit( $want ) && (int)$want === $i + 1 ) )
                $chosen = $s;
        if ( !$chosen )
            throw new expImportSourceException( "the spreadsheet has no sheet '$want' (it has: {$this->detected['sheets']})" );
        $target = ltrim( $chosen['target'], '/' );
        return strpos( $target, 'xl/' ) === 0 ? $target : 'xl/' . $target;
    }

    /** @return string[] the shared strings of the workbook */
    protected function xlsxSharedStrings( ZipArchive $zip )
    {
        $xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $xml === false )
            return array();
        $reader = new XMLReader();
        $reader->XML( $xml, null, LIBXML_NONET | LIBXML_PARSEHUGE );
        $strings = array();
        $current = null;
        while ( $reader->read() )
        {
            if ( $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si' )
                $current = '';
            elseif ( $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $current !== null )
                $current .= $reader->readString();
            elseif ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' )
            {
                $strings[] = $current;
                $current = null;
            }
        }
        $reader->close();
        return $strings;
    }

    /** @return Generator rows of cell values, by 0-based column */
    protected function xlsxRows()
    {
        $zip = $this->zip();
        $path = $this->xlsxSheetPath( $zip );
        $strings = $this->xlsxSharedStrings( $zip );
        $zip->close();
        $reader = new XMLReader();
        if ( !@$reader->open( 'zip://' . $this->file() . '#' . $path, null, LIBXML_NONET | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the sheet of the XLSX file cannot be read' );
        $row = null;
        $col = 0;
        $type = '';
        try
        {
            while ( $reader->read() )
            {
                if ( $reader->nodeType === XMLReader::ELEMENT )
                {
                    if ( $reader->localName === 'row' )
                    {
                        $row = array();
                        $col = 0;
                        if ( $reader->isEmptyElement )
                        {
                            yield array();
                            $row = null;
                        }
                    }
                    elseif ( $reader->localName === 'c' && $row !== null )
                    {
                        $ref = (string)$reader->getAttribute( 'r' );
                        $col = $ref !== '' ? self::columnIndex( $ref ) : count( $row );
                        $type = (string)$reader->getAttribute( 't' );
                        if ( $reader->isEmptyElement )
                            $row[$col] = '';
                    }
                    elseif ( $reader->localName === 'v' && $row !== null )
                    {
                        $value = $reader->readString();
                        if ( $type === 's' )
                            $value = isset( $strings[(int)$value] ) ? $strings[(int)$value] : '';
                        elseif ( $type === 'b' )
                            $value = $value === '1' ? '1' : '0';
                        $row[$col] = $value;
                    }
                    elseif ( $reader->localName === 'is' && $row !== null )
                        $row[$col] = $reader->readString();
                }
                elseif ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row' && $row !== null )
                {
                    yield self::fill( $row );
                    $row = null;
                }
            }
        }
        finally
        {
            $reader->close();
        }
    }

    /** @return int the 0-based column of a cell reference ("C7" is 2) */
    public static function columnIndex( $ref )
    {
        if ( !preg_match( '/^([A-Z]+)/i', (string)$ref, $m ) )
            return 0;
        $n = 0;
        foreach ( str_split( strtoupper( $m[1] ) ) as $c )
            $n = $n * 26 + ( ord( $c ) - 64 );
        return $n - 1;
    }

    /** @return array a row with the columns before the last one filled ('' for an empty cell) */
    protected static function fill( array $row )
    {
        if ( !$row )
            return array();
        $out = array();
        for ( $i = 0, $max = max( array_keys( $row ) ); $i <= $max; $i++ )
            $out[$i] = isset( $row[$i] ) ? $row[$i] : '';
        return $out;
    }

    // ---- ODS -----------------------------------------------------------------------------------------------------

    /** @return Generator rows of cell values, by 0-based column */
    protected function odsRows()
    {
        $want = isset( $this->config['sheet'] ) ? trim( (string)$this->config['sheet'] ) : '1';
        $reader = new XMLReader();
        if ( !@$reader->open( 'zip://' . $this->file() . '#content.xml', null, LIBXML_NONET | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the content of the ODS file cannot be read' );
        $table = 0;
        $inTable = false;
        try
        {
            // after next() the reader already stands on the following node: it is looked at without read()
            $ok = $reader->read();
            while ( $ok )
            {
                if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'table:table' )
                {
                    $table++;
                    $name = (string)$reader->getAttribute( 'table:name' );
                    $inTable = $name === $want || ( ctype_digit( $want ) && (int)$want === $table );
                    if ( !$inTable )
                    {
                        $ok = $reader->next();
                        continue;
                    }
                }
                elseif ( $inTable && $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'table:table' )
                    return;
                elseif ( $inTable && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'table:table-row' )
                {
                    $repeat = max( 1, min( 1000, (int)$reader->getAttribute( 'table:number-rows-repeated' ) ) );
                    $row = self::odsRow( $reader->readOuterXml() );
                    for ( $i = 0; $i < $repeat; $i++ )
                    {
                        yield $row;
                        // a run of empty rows (the end of the sheet) is one row
                        if ( !array_filter( $row, 'strlen' ) )
                            break;
                    }
                    $ok = $reader->next();
                    continue;
                }
                $ok = $reader->read();
            }
            if ( !$table || !$inTable )
                throw new expImportSourceException( "the spreadsheet has no sheet '$want'" );
        }
        finally
        {
            $reader->close();
        }
    }

    /**
     * The cells of a row.
     * @param string $xml the row element
     * @return array
     */
    protected static function odsRow( $xml )
    {
        $doc = new DOMDocument();
        if ( !@$doc->loadXML( $xml, LIBXML_NONET ) )
            return array();
        $cells = array();
        $pending = array();
        $office = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
        $tablens = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
        foreach ( $doc->documentElement->childNodes as $cell )
        {
            if ( !$cell instanceof DOMElement || ( $cell->localName !== 'table-cell' && $cell->localName !== 'covered-table-cell' ) )
                continue;
            $repeat = max( 1, (int)$cell->getAttributeNS( $tablens, 'number-columns-repeated' ) );
            $type = $cell->getAttributeNS( $office, 'value-type' );
            if ( $type === 'float' || $type === 'percentage' || $type === 'currency' )
                $value = $cell->getAttributeNS( $office, 'value' );
            elseif ( $type === 'date' )
                $value = $cell->getAttributeNS( $office, 'date-value' );
            elseif ( $type === 'boolean' )
                $value = $cell->getAttributeNS( $office, 'boolean-value' ) === 'true' ? '1' : '0';
            else
            {
                $lines = array();
                foreach ( $cell->childNodes as $p )
                    if ( $p instanceof DOMElement && $p->localName === 'p' )
                        $lines[] = $p->textContent;
                $value = implode( "\n", $lines );
            }
            // empty cells repeated at the end of a row are not columns
            if ( $value === '' )
            {
                $pending[] = min( $repeat, 1000 );
                continue;
            }
            foreach ( $pending as $n )
                for ( $i = 0; $i < $n; $i++ )
                    $cells[] = '';
            $pending = array();
            for ( $i = 0; $i < min( $repeat, 1000 ); $i++ )
                $cells[] = $value;
        }
        return $cells;
    }
}
