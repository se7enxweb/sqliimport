<?php
/**
 * File containing the expImportJsonSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * JSON and NDJSON files. A JSON file holds a list of objects, at its top or at a record path ("data.items"); an NDJSON
 * file (JSON Lines) holds one object per line and is read as a stream, its cursor being the byte position after a
 * record. A JSON file is read whole, so very large exports are better delivered as NDJSON.
 */
class expImportJsonSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'json';
    }

    public static function label()
    {
        return 'JSON / NDJSON file';
    }

    public static function extensions()
    {
        return array( 'json', 'ndjson', 'jsonl' );
    }

    public static function schema()
    {
        return array(
            'format' => array( 'type' => 'enum', 'label' => 'Format', 'default' => 'auto',
                               'values' => array( 'auto' => 'Detect', 'json' => 'JSON (a list of objects)', 'ndjson' => 'NDJSON (one object per line)' ) ),
            'record_path' => array( 'type' => 'string', 'label' => 'Record list',
                                    'help' => 'JSON: the path of the list of records, e.g. data.items; empty: the top' ),
        ) + parent::commonSchema();
    }

    /** @return string json or ndjson */
    protected function format()
    {
        $format = isset( $this->config['format'] ) ? (string)$this->config['format'] : 'auto';
        if ( $format === 'json' || $format === 'ndjson' )
            return $format;
        $file = $this->file();
        $path = isset( $this->config['record_path'] ) ? trim( (string)$this->config['record_path'] ) : '';
        if ( preg_match( '/\.(ndjson|jsonl)$/i', $file ) )
            $format = 'ndjson';
        elseif ( $path !== '' || ( filesize( $file ) < 50 * 1048576 && is_array( json_decode( (string)file_get_contents( $file ), true ) ) ) )
            $format = 'json';
        else
        {
            $fp = fopen( $file, 'rb' );
            $first = trim( (string)fgets( $fp, 1048576 ) );
            $second = trim( (string)fgets( $fp, 1048576 ) );
            fclose( $fp );
            $format = ( $first !== '' && $first[0] === '{' && is_array( json_decode( $first, true ) ) && ( $second === '' || $second[0] === '{' ) )
                    ? 'ndjson' : 'json';
        }
        $this->detected['format'] = $format;
        return $format;
    }

    public function records( array $cursor = array() )
    {
        if ( $this->format() === 'ndjson' )
        {
            $fp = fopen( $this->file(), 'rb' );
            $index = 0;
            if ( isset( $cursor['pos'] ) )
            {
                fseek( $fp, (int)$cursor['pos'] );
                $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
            }
            try
            {
                $line = 0;
                while ( ( $text = fgets( $fp ) ) !== false )
                {
                    $line++;
                    $text = trim( $text );
                    if ( $text === '' )
                        continue;
                    $data = json_decode( $text, true );
                    if ( !is_array( $data ) )
                        throw new expImportSourceException( 'record ' . ( $index + 1 ) . ' is not a JSON object: ' . json_last_error_msg() );
                    yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => ftell( $fp ), 'index' => $index + 1 ) );
                    $index++;
                }
            }
            finally
            {
                fclose( $fp );
            }
            return;
        }
        $list = $this->decodedList();
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $index = 0;
        foreach ( $list as $data )
        {
            if ( $index < $skip )
            {
                $index++;
                continue;
            }
            if ( !is_array( $data ) )
                $data = array( 'value' => $data );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
            $index++;
        }
    }

    /** @return array the list of records of a JSON file */
    protected function decodedList()
    {
        $data = json_decode( (string)file_get_contents( $this->file() ), true );
        if ( !is_array( $data ) )
            throw new expImportSourceException( 'the file is not valid JSON: ' . json_last_error_msg() );
        $path = isset( $this->config['record_path'] ) ? trim( (string)$this->config['record_path'] ) : '';
        if ( $path !== '' )
        {
            $data = expImportRecord::pathValue( $data, $path, null );
            if ( !is_array( $data ) )
                throw new expImportSourceException( "the JSON has no list at '$path'" );
        }
        // an object of objects ("id" => {...}) is a list too
        return array_values( $data ) === $data ? $data : array_values( $data );
    }

    public function count()
    {
        if ( $this->format() === 'ndjson' )
        {
            $n = 0;
            $fp = fopen( $this->file(), 'rb' );
            while ( ( $text = fgets( $fp ) ) !== false )
                if ( trim( $text ) !== '' )
                    $n++;
            fclose( $fp );
            return $n;
        }
        return count( $this->decodedList() );
    }
}
