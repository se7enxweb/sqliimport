<?php
/**
 * File containing the expImportShopCsvSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The product exports of shop systems as CSV, where one product may span several rows (its variants, its images):
 * the file is read as the CSV source reads it, and the rows of one product become one record. A record's cursor is
 * the byte position of the first row of the next product, so a chunk of a run starts there.
 *
 * A subclass says when a row starts a new product (product()), what a further row adds to it (add()) and what the
 * finished record holds (finish()). Columns are found by their name in any case.
 */
abstract class expImportShopCsvSource extends expImportCsvSource
{
    public static function group()
    {
        return 'commerce';
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        $schema = parent::schema();
        unset( $schema['header'], $schema['key'] );
        return $schema + array(
            'currency' => array( 'type' => 'string', 'label' => 'Currency', 'default' => '', 'max' => 3, 'pattern' => '/^[A-Za-z]{0,3}$/',
                                 'help' => 'The ISO 4217 code of the prices (EUR, USD); the export does not name it' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a product' ),
        );
    }

    /**
     * A new product from its first row.
     * @param array $row column name (lower case) => value
     * @return array the product being built
     */
    abstract protected function product( array $row );

    /**
     * Whether a row belongs to the product being built.
     * @return bool
     */
    abstract protected function belongs( array $product, array $row );

    /** Adds a further row (a variant, an image) to the product. */
    abstract protected function add( array &$product, array $row );

    /** @return array the record of a finished product */
    abstract protected function finish( array $product );

    public function records( array $cursor = array() )
    {
        $fp = $this->openFile();
        try
        {
            $delimiter = $this->delimiter( $fp );
            $encoding = isset( $this->config['encoding'] ) ? (string)$this->config['encoding'] : 'auto';
            $line = $this->readLine( $fp, $delimiter );
            $headers = array_map( 'strtolower', $this->headerNames( is_array( $line ) ? $line : array(), $encoding ) );
            $this->headers = $headers;
            $this->checkHeaders( $headers );
            $index = 0;
            if ( isset( $cursor['pos'] ) && (int)$cursor['pos'] > ftell( $fp ) )
            {
                fseek( $fp, (int)$cursor['pos'] );
                $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
            }
            $product = null;
            while ( true )
            {
                $start = ftell( $fp );
                $line = $this->readLine( $fp, $delimiter );
                if ( $line === false )
                    break;
                if ( $line === array( null ) || ( count( $line ) === 1 && trim( (string)$line[0] ) === '' ) )
                    continue;
                $row = array();
                foreach ( $headers as $i => $name )
                    $row[$name] = isset( $line[$i] ) ? trim( self::toUtf8( (string)$line[$i], $encoding ) ) : '';
                if ( $product !== null && $this->belongs( $product, $row ) )
                {
                    $this->add( $product, $row );
                    continue;
                }
                if ( $product !== null )
                {
                    $data = $this->finish( $product );
                    yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => $start, 'index' => $index + 1 ) );
                    $index++;
                }
                $product = $this->product( $row );
            }
            if ( $product !== null )
            {
                $data = $this->finish( $product );
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => ftell( $fp ), 'index' => $index + 1 ) );
            }
        }
        finally
        {
            fclose( $fp );
        }
    }

    /** Refuses a file without the columns of the export (the names in lower case). */
    protected function checkHeaders( array $headers )
    {
    }

    /** @return string '1' for true, yes, 1; '0' for false, no, 0; '' when empty */
    protected static function flag( $value )
    {
        $s = strtolower( trim( (string)$value ) );
        if ( $s === '' )
            return '';
        return in_array( $s, array( '1', 'true', 'yes', 'y' ), true ) ? '1' : '0';
    }

    /** @return string the currency of the option, upper case */
    protected function currency()
    {
        return isset( $this->config['currency'] ) ? strtoupper( trim( (string)$this->config['currency'] ) ) : '';
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }
}
