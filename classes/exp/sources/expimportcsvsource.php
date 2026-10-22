<?php
/**
 * File containing the expImportCsvSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * CSV and TSV files, read as a stream: one record per line (a quoted field may span lines), keyed by the header line.
 * The delimiter (comma, semicolon, tab, pipe) and the encoding (UTF-8, else Windows-1252) are detected when they are
 * left on "auto". A record's cursor is the byte position after it, so a chunk of a run starts where the last one
 * ended without reading the file from the beginning.
 */
class expImportCsvSource extends expImportAbstractSource
{
    /** @var string[]|null */
    protected $headers = null;

    public static function identifier()
    {
        return 'csv';
    }

    public static function label()
    {
        return 'CSV / TSV file';
    }

    public static function extensions()
    {
        return array( 'csv', 'tsv', 'txt' );
    }

    public static function description()
    {
        return 'Comma, semicolon, tab or pipe separated text, as spreadsheets and databases export it. The first line names '
             . 'the columns, every further line is a record. Delimiter and encoding (UTF-8, Windows-1252) are detected.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three products with title, price and an active flag', 'file' => 'products.csv' ) );
    }

    public static function variants()
    {
        return array(
            'tsv' => array(
                'label' => 'TSV file (tab separated)',
                'description' => 'Tab separated values: what a copy from a spreadsheet, many databases and analytics tools give. '
                               . 'The first line names the columns. Quotes are read as in CSV.',
                'extensions' => array( 'tsv', 'tab', 'txt' ),
                'defaults' => array( 'delimiter' => 'tab' ),
                'examples' => array( array( 'label' => 'Three products, tab separated', 'file' => 'products.tsv' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'delimiter' => array( 'type' => 'enum', 'label' => 'Delimiter', 'default' => 'auto',
                                  'values' => array( 'auto' => 'Detect', ',' => 'Comma', ';' => 'Semicolon', 'tab' => 'Tab', '|' => 'Pipe' ) ),
            'enclosure' => array( 'type' => 'string', 'label' => 'Enclosure', 'default' => '"', 'max' => 1 ),
            'encoding' => array( 'type' => 'enum', 'label' => 'Encoding', 'default' => 'auto',
                                 'values' => array( 'auto' => 'Detect', 'UTF-8' => 'UTF-8', 'WINDOWS-1252' => 'Windows-1252', 'ISO-8859-1' => 'ISO-8859-1', 'ISO-8859-15' => 'ISO-8859-15' ) ),
            'header' => array( 'type' => 'bool', 'label' => 'The first line names the columns', 'default' => true ),
        ) + parent::commonSchema();
    }

    /** @return string the delimiter character */
    protected function delimiter( $fp )
    {
        $delimiter = isset( $this->config['delimiter'] ) ? (string)$this->config['delimiter'] : 'auto';
        if ( $delimiter === 'tab' )
            return "\t";
        if ( $delimiter !== '' && $delimiter !== 'auto' )
            return $delimiter;
        $pos = ftell( $fp );
        rewind( $fp );
        $line = (string)fgets( $fp, 65536 );
        fseek( $fp, $pos );
        $line = preg_replace( '/"[^"]*"/', '', $line );
        $best = ',';
        $max = 0;
        foreach ( array( ',', ';', "\t", '|' ) as $candidate )
        {
            $n = substr_count( $line, $candidate );
            if ( $n > $max )
            {
                $max = $n;
                $best = $candidate;
            }
        }
        $this->detected['delimiter'] = $best === "\t" ? 'tab' : $best;
        return $best;
    }

    /** @return resource */
    protected function openFile()
    {
        $fp = @fopen( $this->file(), 'rb' );
        if ( !$fp )
            throw new expImportSourceException( 'the CSV file cannot be opened' );
        // a byte order mark is not part of the first column's name
        if ( fread( $fp, 3 ) !== "\xEF\xBB\xBF" )
            rewind( $fp );
        return $fp;
    }

    /** @return array|false */
    protected function readLine( $fp, $delimiter )
    {
        $enclosure = isset( $this->config['enclosure'] ) && $this->config['enclosure'] !== '' ? (string)$this->config['enclosure'] : '"';
        return fgetcsv( $fp, 0, $delimiter, $enclosure, '' );
    }

    public function records( array $cursor = array() )
    {
        $fp = $this->openFile();
        try
        {
            $delimiter = $this->delimiter( $fp );
            $encoding = isset( $this->config['encoding'] ) ? (string)$this->config['encoding'] : 'auto';
            $useHeader = !isset( $this->config['header'] ) || $this->config['header'];
            $headers = array();
            if ( $useHeader )
            {
                $line = $this->readLine( $fp, $delimiter );
                $headers = $this->headerNames( is_array( $line ) ? $line : array(), $encoding );
            }
            $this->headers = $headers;
            $index = 0;
            if ( isset( $cursor['pos'] ) && (int)$cursor['pos'] > ftell( $fp ) )
            {
                fseek( $fp, (int)$cursor['pos'] );
                $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
            }
            while ( ( $line = $this->readLine( $fp, $delimiter ) ) !== false )
            {
                if ( $line === array( null ) || ( count( $line ) === 1 && trim( (string)$line[0] ) === '' ) )
                    continue;
                $data = array();
                foreach ( $line as $i => $value )
                {
                    $name = isset( $headers[$i] ) ? $headers[$i] : ( $useHeader ? 'col_' . ( $i + 1 ) : (string)$i );
                    $data[$name] = self::toUtf8( (string)$value, $encoding );
                }
                foreach ( $headers as $name )
                    if ( !array_key_exists( $name, $data ) )
                        $data[$name] = '';
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => ftell( $fp ), 'index' => $index + 1 ) );
                $index++;
            }
        }
        finally
        {
            fclose( $fp );
        }
    }

    /** @return string[] the column names: trimmed, empty and repeated names made unique */
    protected function headerNames( array $line, $encoding )
    {
        $names = array();
        foreach ( $line as $i => $name )
        {
            $name = trim( self::toUtf8( (string)$name, $encoding ) );
            if ( $name === '' )
                $name = 'col_' . ( $i + 1 );
            $base = $name;
            $n = 2;
            while ( in_array( $name, $names, true ) )
                $name = $base . '_' . $n++;
            $names[] = $name;
        }
        return $names;
    }

    public function count()
    {
        $fp = $this->openFile();
        $delimiter = $this->delimiter( $fp );
        $n = 0;
        while ( ( $line = $this->readLine( $fp, $delimiter ) ) !== false )
        {
            if ( $line === array( null ) || ( count( $line ) === 1 && trim( (string)$line[0] ) === '' ) )
                continue;
            $n++;
        }
        fclose( $fp );
        $useHeader = !isset( $this->config['header'] ) || $this->config['header'];
        return max( 0, $useHeader ? $n - 1 : $n );
    }
}
