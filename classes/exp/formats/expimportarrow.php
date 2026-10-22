<?php
/**
 * File containing the expImportArrow class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A reader of Apache Arrow IPC data in PHP alone: the file format (ARROW1 ... ARROW1, also Feather version 2, which is
 * the same) and the stream format (.arrows), little-endian, metadata version 4 or 5. The messages are FlatBuffers
 * (read by the few helpers below), the record batches columns of buffers.
 *
 * Types: null, bool, int (8 to 64 bits, signed or not; an unsigned 64-bit number above PHP's range as text),
 * floating point (half, single, double), decimal (32 to 256 bits, as a decimal number in text), utf8 and large utf8,
 * binary, large binary and fixed size binary (as base64), date (as 2026-10-22), time, timestamp (ISO 8601; with a time
 * zone in UTC with Z), duration (the number), struct (nested: the caller flattens it), list, large list, fixed size
 * list and map, and dictionary-encoded columns of any of these. Compressed buffers: LZ4 frame (in PHP) and Zstandard
 * (only with the PECL extension zstd). Unions, intervals, run-end encoded and view types are refused with a message.
 * Feather version 1 (before 2020) is refused too.
 */
class expImportArrow
{
    /** Message header types. */
    const SCHEMA = 1;
    const DICTIONARY = 2;
    const RECORD_BATCH = 3;

    /** The names of the types of Schema.fbs, for messages. */
    const TYPES = array( 0 => 'NONE', 1 => 'Null', 2 => 'Int', 3 => 'FloatingPoint', 4 => 'Binary', 5 => 'Utf8', 6 => 'Bool', 7 => 'Decimal',
                         8 => 'Date', 9 => 'Time', 10 => 'Timestamp', 11 => 'Interval', 12 => 'List', 13 => 'Struct', 14 => 'Union',
                         15 => 'FixedSizeBinary', 16 => 'FixedSizeList', 17 => 'Map', 18 => 'Duration', 19 => 'LargeBinary', 20 => 'LargeUtf8',
                         21 => 'LargeList', 22 => 'RunEndEncoded', 23 => 'BinaryView', 24 => 'Utf8View', 25 => 'ListView', 26 => 'LargeListView' );

    /** @var string the whole file */
    protected $data;

    /** @var string file or stream */
    public $format;

    /** @var array[] the fields of the schema: name, type, params, children, dict */
    public $fields = array();

    /** @var int where the messages after the schema begin */
    protected $first = 0;

    /** @var int where the messages end (the footer of a file) */
    protected $end = 0;

    /** @var array id => the values of a dictionary */
    protected $dictionaries = array();

    /** @var array the batch being decoded: nodes, buffers, body, length, codec, and the next node and buffer */
    protected $batch = array();

    /**
     * Reads the file and its schema.
     *
     * @param string $file
     * @param int $maxBytes the largest file read (it is read whole)
     * @throws expImportSourceException
     */
    public function __construct( $file, $maxBytes = 1073741824 )
    {
        $size = @filesize( (string)$file );
        if ( $size === false )
            throw new expImportSourceException( 'the Arrow file cannot be read' );
        if ( $size > $maxBytes )
            throw new expImportSourceException( 'the Arrow file is larger than ' . (int)( $maxBytes / 1048576 ) . ' MB' );
        $this->data = (string)file_get_contents( (string)$file );
        $length = strlen( $this->data );
        if ( strncmp( $this->data, 'FEA1', 4 ) === 0 )
            throw new expImportSourceException( 'this is a Feather file of version 1 (before 2020), which cannot be read; write it again as Feather version 2 (the Arrow file format)' );
        if ( strncmp( $this->data, 'ARROW1', 6 ) === 0 )
        {
            if ( $length < 18 || substr( $this->data, -6 ) !== 'ARROW1' )
                throw new expImportSourceException( 'the Arrow file is cut off (it has no footer)' );
            $this->format = 'file';
            $footer = $this->i32( $length - 10 );
            $this->end = $length - 10 - $footer;
            if ( $footer <= 0 || $this->end < 8 )
                throw new expImportSourceException( 'the footer of the Arrow file is damaged' );
            $start = 8;
        }
        else
        {
            $this->format = 'stream';
            $this->end = $length;
            $start = 0;
        }
        $message = $this->message( $start );
        if ( $message === null || $message['type'] !== self::SCHEMA )
            throw new expImportSourceException( 'not Arrow IPC data (it does not begin with a schema)' );
        if ( $this->int16( $message['header'], 0, 0 ) !== 0 )
            throw new expImportSourceException( 'the Arrow data is big-endian, which cannot be read' );
        $vector = $this->vector( $message['header'], 1 );
        if ( $vector )
            for ( $i = 0; $i < $vector[0]; $i++ )
                $this->fields[] = $this->field( $this->indirect( $vector[1] + 4 * $i ), 0 );
        $this->first = $message['next'];
    }

    // ---- FlatBuffers -------------------------------------------------------------------------------------------------

    /** @return int an unsigned 32-bit number */
    protected function u32( $pos )
    {
        $this->bounds( $pos, 4 );
        return (int)unpack( 'V', $this->data, $pos )[1];
    }

    /** @return int a signed 32-bit number */
    protected function i32( $pos )
    {
        $v = $this->u32( $pos );
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }

    /** @return int a signed 64-bit number */
    protected function i64( $pos )
    {
        $this->bounds( $pos, 8 );
        return (int)unpack( 'P', $this->data, $pos )[1];
    }

    /** @return int an unsigned 16-bit number */
    protected function u16( $pos )
    {
        $this->bounds( $pos, 2 );
        return (int)unpack( 'v', $this->data, $pos )[1];
    }

    /** Refuses a read outside the data. */
    protected function bounds( $pos, $n )
    {
        if ( (int)$pos < 0 || (int)$pos + (int)$n > strlen( $this->data ) )
            throw new expImportSourceException( 'the Arrow data is damaged (a reference points outside the file)' );
    }

    /** @return int the position a uoffset at a position points to */
    protected function indirect( $pos )
    {
        return $pos + $this->u32( $pos );
    }

    /** @return int the position of a field of a table, 0 when it is not set */
    protected function slot( $table, $index )
    {
        $vtable = $table - $this->i32( $table );
        $size = $this->u16( $vtable );
        $at = 4 + 2 * $index;
        if ( $at + 2 > $size )
            return 0;
        $offset = $this->u16( $vtable + $at );
        return $offset ? $table + $offset : 0;
    }

    /** @return int a byte field */
    protected function int8( $table, $index, $default )
    {
        $pos = $this->slot( $table, $index );
        if ( !$pos )
            return $default;
        $this->bounds( $pos, 1 );
        $v = ord( $this->data[$pos] );
        return $v >= 128 ? $v - 256 : $v;
    }

    /** @return int a 16-bit field */
    protected function int16( $table, $index, $default )
    {
        $pos = $this->slot( $table, $index );
        if ( !$pos )
            return $default;
        $v = $this->u16( $pos );
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    /** @return int a 32-bit field */
    protected function int32( $table, $index, $default )
    {
        $pos = $this->slot( $table, $index );
        return $pos ? $this->i32( $pos ) : $default;
    }

    /** @return int a 64-bit field */
    protected function int64( $table, $index, $default )
    {
        $pos = $this->slot( $table, $index );
        return $pos ? $this->i64( $pos ) : $default;
    }

    /** @return int the table of a field, 0 when it is not set */
    protected function table( $table, $index )
    {
        $pos = $this->slot( $table, $index );
        return $pos ? $this->indirect( $pos ) : 0;
    }

    /** @return string a string field */
    protected function text( $table, $index )
    {
        $pos = $this->slot( $table, $index );
        if ( !$pos )
            return '';
        $at = $this->indirect( $pos );
        $n = $this->u32( $at );
        $this->bounds( $at + 4, $n );
        return (string)substr( $this->data, $at + 4, $n );
    }

    /** @return array|null a vector field: the list ( count, position of the first element ) */
    protected function vector( $table, $index )
    {
        $pos = $this->slot( $table, $index );
        if ( !$pos )
            return null;
        $at = $this->indirect( $pos );
        return array( $this->u32( $at ), $at + 4 );
    }

    // ---- messages and the schema -------------------------------------------------------------------------------------

    /**
     * The message at a position.
     *
     * @param int $pos
     * @return array|null type, header (the table), body (its position), length (of the body), next; null at the end
     * @throws expImportSourceException
     */
    protected function message( $pos )
    {
        if ( $pos + 4 > $this->end )
            return null;
        $length = $this->i32( $pos );
        $pos += 4;
        if ( $length === -1 )
        {
            if ( $pos + 4 > $this->end )
                return null;
            $length = $this->i32( $pos );
            $pos += 4;
        }
        if ( $length === 0 )
            return null;
        if ( $length < 0 || $pos + $length > $this->end )
            throw new expImportSourceException( 'the Arrow data is damaged (a message does not fit)' );
        $root = $this->indirect( $pos );
        $version = $this->int16( $root, 0, 0 );
        if ( $version < 3 )
            throw new expImportSourceException( 'the Arrow data has the metadata version ' . ( $version + 1 ) . '; only version 4 and later (Arrow 0.8, 2017) can be read' );
        $body = $pos + $length;
        $bodyLength = $this->int64( $root, 3, 0 );
        if ( $bodyLength < 0 || $body + $bodyLength > $this->end )
            throw new expImportSourceException( 'the Arrow data is cut off (a message body does not fit)' );
        return array( 'type' => $this->int8( $root, 1, 0 ) & 0xFF, 'header' => $this->table( $root, 2 ), 'body' => $body, 'length' => $bodyLength,
                      'next' => $body + $bodyLength );
    }

    /**
     * A field of the schema.
     *
     * @param int $table
     * @param int $depth
     * @return array name, type, params, children, dict
     */
    protected function field( $table, $depth )
    {
        if ( $depth > 32 )
            throw new expImportSourceException( 'the Arrow schema is nested too deep' );
        $type = $this->int8( $table, 2, 0 ) & 0xFF;
        $t = $this->table( $table, 3 );
        $params = array();
        if ( $t )
            switch ( $type )
            {
                case 2:
                    $params = array( 'bits' => $this->int32( $t, 0, 0 ), 'signed' => (bool)$this->int8( $t, 1, 0 ) );
                    break;
                case 3:
                    $params = array( 'precision' => $this->int16( $t, 0, 0 ) );
                    break;
                case 7:
                    $params = array( 'precision' => $this->int32( $t, 0, 0 ), 'scale' => $this->int32( $t, 1, 0 ), 'bits' => $this->int32( $t, 2, 128 ) );
                    break;
                case 8:
                    $params = array( 'unit' => $this->int16( $t, 0, 1 ) );
                    break;
                case 9:
                    $params = array( 'unit' => $this->int16( $t, 0, 1 ), 'bits' => $this->int32( $t, 1, 32 ) );
                    break;
                case 10:
                    $params = array( 'unit' => $this->int16( $t, 0, 0 ), 'timezone' => $this->text( $t, 1 ) );
                    break;
                case 15:
                    $params = array( 'width' => $this->int32( $t, 0, 0 ) );
                    break;
                case 16:
                    $params = array( 'size' => $this->int32( $t, 0, 0 ) );
                    break;
                case 18:
                    $params = array( 'unit' => $this->int16( $t, 0, 1 ) );
                    break;
            }
        if ( $type === 2 && !$t )
            $params = array( 'bits' => 32, 'signed' => true );
        if ( in_array( $type, array( 8, 9, 10, 18 ), true ) && !$t )
            $params = array( 'unit' => $type === 10 ? 0 : 1, 'bits' => 32, 'timezone' => '' );
        $children = array();
        $vector = $this->vector( $table, 5 );
        if ( $vector )
            for ( $i = 0; $i < $vector[0]; $i++ )
                $children[] = $this->field( $this->indirect( $vector[1] + 4 * $i ), $depth + 1 );
        $dict = null;
        $d = $this->table( $table, 4 );
        if ( $d )
        {
            $index = $this->table( $d, 1 );
            $dict = array( 'id' => $this->int64( $d, 0, 0 ), 'bits' => $index ? $this->int32( $index, 0, 32 ) : 32,
                           'signed' => $index ? (bool)$this->int8( $index, 1, 0 ) : true );
        }
        return array( 'name' => $this->text( $table, 0 ), 'type' => $type, 'params' => $params, 'children' => $children, 'dict' => $dict );
    }

    /** @return string[] the names of the columns */
    public function fieldNames()
    {
        return array_map( function ( $f ) { return (string)$f['name']; }, $this->fields );
    }

    // ---- record batches ----------------------------------------------------------------------------------------------

    /**
     * The rows of the data, from a batch on.
     *
     * @param int $skipBatches record batches to leave out
     * @param int $skipRows rows of the first batch read to leave out
     * @return Generator array( 'value' => the row (name => value), 'batch' => its batch, 'row' => its position in it )
     * @throws expImportSourceException
     */
    public function records( $skipBatches = 0, $skipRows = 0 )
    {
        $this->dictionaries = array();
        $pos = $this->first;
        $number = 0;
        while ( ( $message = $this->message( $pos ) ) !== null )
        {
            $pos = $message['next'];
            if ( $message['type'] === self::DICTIONARY )
            {
                $this->dictionary( $message );
                continue;
            }
            if ( $message['type'] !== self::RECORD_BATCH )
                continue;
            if ( $number < $skipBatches )
            {
                $number++;
                continue;
            }
            list( $columns, $length ) = $this->columns( $message['header'], $message, $this->fields );
            for ( $row = $number === $skipBatches ? $skipRows : 0; $row < $length; $row++ )
            {
                $value = array();
                foreach ( $this->fields as $i => $field )
                    $value[$field['name']] = $columns[$i][$row];
                yield array( 'value' => $value, 'batch' => $number, 'row' => $row );
            }
            $number++;
        }
    }

    /** Reads a dictionary batch into $dictionaries. */
    protected function dictionary( array $message )
    {
        $id = $this->int64( $message['header'], 0, 0 );
        $field = $this->dictionaryField( $this->fields, $id );
        if ( $field === null )
            throw new expImportSourceException( "the Arrow data has a dictionary ($id) no column uses" );
        $field['dict'] = null;
        list( $columns ) = $this->columns( $this->table( $message['header'], 1 ), $message, array( $field ) );
        $delta = (bool)$this->int8( $message['header'], 2, 0 );
        $this->dictionaries[$id] = $delta && isset( $this->dictionaries[$id] ) ? array_merge( $this->dictionaries[$id], $columns[0] ) : $columns[0];
    }

    /** @return array|null the field that uses a dictionary */
    protected function dictionaryField( array $fields, $id )
    {
        foreach ( $fields as $field )
        {
            if ( $field['dict'] !== null && $field['dict']['id'] === $id )
                return $field;
            $found = $this->dictionaryField( $field['children'], $id );
            if ( $found !== null )
                return $found;
        }
        return null;
    }

    /**
     * The columns of a record batch.
     *
     * @param int $table the RecordBatch table
     * @param array $message
     * @param array[] $fields
     * @return array the list ( array[] the values of each column, int the rows )
     */
    protected function columns( $table, array $message, array $fields )
    {
        if ( !$table )
            throw new expImportSourceException( 'the Arrow data is damaged (a batch has no header)' );
        $compression = $this->table( $table, 3 );
        $this->batch = array( 'nodes' => $this->vector( $table, 1 ), 'buffers' => $this->vector( $table, 2 ), 'body' => $message['body'],
                              'length' => $message['length'], 'codec' => $compression ? ( $this->int8( $compression, 0, 0 ) & 0xFF ) : -1,
                              'node' => 0, 'buffer' => 0 );
        $length = $this->int64( $table, 0, 0 );
        if ( $length < 0 || $length > 100000000 )
            throw new expImportSourceException( 'an Arrow batch has a wrong length' );
        $columns = array();
        foreach ( $fields as $field )
            $columns[] = $this->column( $field, 0 );
        return array( $columns, $length );
    }

    /** @return array the list ( int length, int null count ) of the next node */
    protected function node()
    {
        $nodes = $this->batch['nodes'];
        if ( !$nodes || $this->batch['node'] >= $nodes[0] )
            throw new expImportSourceException( 'the Arrow data is damaged (a batch has fewer nodes than the schema has columns)' );
        $at = $nodes[1] + 16 * $this->batch['node']++;
        return array( $this->i64( $at ), $this->i64( $at + 8 ) );
    }

    /** @return string the next buffer of the batch, decompressed */
    protected function buffer()
    {
        $buffers = $this->batch['buffers'];
        if ( !$buffers || $this->batch['buffer'] >= $buffers[0] )
            throw new expImportSourceException( 'the Arrow data is damaged (a batch has fewer buffers than its columns need)' );
        $at = $buffers[1] + 16 * $this->batch['buffer']++;
        $offset = $this->i64( $at );
        $length = $this->i64( $at + 8 );
        if ( $offset < 0 || $length < 0 || $offset + $length > $this->batch['length'] )
            throw new expImportSourceException( 'the Arrow data is damaged (a buffer lies outside its batch)' );
        $data = (string)substr( $this->data, $this->batch['body'] + $offset, $length );
        if ( $this->batch['codec'] < 0 || $length === 0 )
            return $data;
        if ( $length < 8 )
            throw new expImportSourceException( 'the Arrow data is damaged (a compressed buffer is too short)' );
        $size = unpack( 'P', $data )[1];
        if ( $size === -1 )
            return (string)substr( $data, 8 );
        $out = $this->batch['codec'] === 0 ? self::lz4Frame( (string)substr( $data, 8 ) ) : self::zstd( (string)substr( $data, 8 ) );
        if ( strlen( $out ) !== $size )
            throw new expImportSourceException( 'a compressed buffer of the Arrow data has the wrong size' );
        return $out;
    }

    /**
     * The values of a column of $n rows (the node and buffers of the column and its children, in their order).
     *
     * @param array $field
     * @param int $depth
     * @return array
     */
    protected function column( array $field, $depth )
    {
        if ( $depth > 32 )
            throw new expImportSourceException( 'the Arrow data is nested too deep' );
        list( $n, $nulls ) = $this->node();
        $type = $field['type'];
        $p = $field['params'];
        $name = $field['name'];
        if ( $type === 1 )
            return array_fill( 0, $n, null );
        $valid = $this->validity( $this->buffer(), $n, $nulls );
        if ( $field['dict'] !== null )
        {
            $indexes = self::ints( $this->buffer(), $field['dict']['bits'], $field['dict']['signed'], $n );
            $id = $field['dict']['id'];
            if ( !isset( $this->dictionaries[$id] ) )
                throw new expImportSourceException( "the column $name uses a dictionary that comes after it" );
            $out = array();
            foreach ( $indexes as $i => $index )
                $out[] = $valid !== null && !$valid[$i] ? null : ( isset( $this->dictionaries[$id][$index] ) ? $this->dictionaries[$id][$index] : null );
            return $out;
        }
        switch ( $type )
        {
            case 2:
                $values = self::ints( $this->buffer(), $p['bits'], $p['signed'], $n );
                break;
            case 3:
                $values = self::floats( $this->buffer(), $p['precision'], $n );
                break;
            case 6:
                $bits = $this->validity( $this->buffer(), $n, -1 );
                $values = $bits === null ? array_fill( 0, $n, false ) : array_map( 'boolval', $bits );
                break;
            case 4:
            case 5:
            case 19:
            case 20:
                $large = $type >= 19;
                $offsets = self::ints( $this->buffer(), $large ? 64 : 32, true, $n + 1 );
                $data = $this->buffer();
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                {
                    $v = (string)substr( $data, $offsets[$i], $offsets[$i + 1] - $offsets[$i] );
                    $values[] = $type === 5 || $type === 20 ? $v : base64_encode( $v );
                }
                break;
            case 7:
                $width = intdiv( max( 8, (int)$p['bits'] ), 8 );
                $data = $this->buffer();
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                    $values[] = expImportAvro::decimal( (string)substr( $data, $i * $width, $width ), (int)$p['scale'], false );
                break;
            case 8:
                $values = array();
                foreach ( self::ints( $this->buffer(), $p['unit'] === 0 ? 32 : 64, true, $n ) as $v )
                    $values[] = gmdate( 'Y-m-d', $p['unit'] === 0 ? $v * 86400 : intdiv( $v, 1000 ) - ( $v % 1000 < 0 ? 1 : 0 ) );
                break;
            case 9:
                $per = array( 1, 1000, 1000000, 1000000000 );
                $digits = array( 0, 3, 6, 9 );
                $unit = max( 0, min( 3, (int)$p['unit'] ) );
                $values = array();
                foreach ( self::ints( $this->buffer(), $unit <= 1 ? 32 : 64, true, $n ) as $v )
                    $values[] = expImportAvro::time( $v, $per[$unit], $digits[$unit] );
                break;
            case 10:
                $per = array( 1, 1000, 1000000, 1000000000 );
                $digits = array( 0, 3, 6, 9 );
                $unit = max( 0, min( 3, (int)$p['unit'] ) );
                $values = array();
                foreach ( self::ints( $this->buffer(), 64, true, $n ) as $v )
                    $values[] = expImportAvro::timestamp( $v, $per[$unit], $digits[$unit], $p['timezone'] !== '' );
                break;
            case 18:
                $values = self::ints( $this->buffer(), 64, true, $n );
                break;
            case 15:
                $width = max( 0, (int)$p['width'] );
                $data = $this->buffer();
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                    $values[] = base64_encode( (string)substr( $data, $i * $width, $width ) );
                break;
            case 13:
                $children = array();
                foreach ( $field['children'] as $child )
                    $children[$child['name']] = $this->column( $child, $depth + 1 );
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                {
                    $row = array();
                    foreach ( $children as $key => $list )
                        $row[$key] = $list[$i];
                    $values[] = $row;
                }
                break;
            case 12:
            case 21:
            case 17:
                $offsets = self::ints( $this->buffer(), $type === 21 ? 64 : 32, true, $n + 1 );
                if ( !isset( $field['children'][0] ) )
                    throw new expImportSourceException( "the list column $name has no item type" );
                $items = $this->column( $field['children'][0], $depth + 1 );
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                {
                    $slice = array_slice( $items, $offsets[$i], $offsets[$i + 1] - $offsets[$i] );
                    if ( $type === 17 )
                    {
                        // a map: a list of {key, value} structs
                        $map = array();
                        foreach ( $slice as $entry )
                            if ( is_array( $entry ) )
                            {
                                $entry = array_values( $entry );
                                $map[is_scalar( $entry[0] ) ? (string)$entry[0] : ''] = isset( $entry[1] ) ? $entry[1] : null;
                            }
                        $slice = $map;
                    }
                    $values[] = $slice;
                }
                break;
            case 16:
                $size = max( 0, (int)$p['size'] );
                if ( !isset( $field['children'][0] ) )
                    throw new expImportSourceException( "the list column $name has no item type" );
                $items = $this->column( $field['children'][0], $depth + 1 );
                $values = array();
                for ( $i = 0; $i < $n; $i++ )
                    $values[] = array_slice( $items, $i * $size, $size );
                break;
            default:
                $label = isset( self::TYPES[$type] ) ? self::TYPES[$type] : (string)$type;
                throw new expImportSourceException( "the column $name has the Arrow type $label, which cannot be read; cast it to a plain type (text, number, timestamp, list or struct) when writing the file" );
        }
        if ( count( $values ) < $n )
            throw new expImportSourceException( "the column $name of the Arrow data is cut off" );
        if ( $valid !== null )
            foreach ( $valid as $i => $ok )
                if ( !$ok )
                    $values[$i] = null;
        return $values;
    }

    /**
     * A bitmap as a list of booleans.
     *
     * @param string $buffer
     * @param int $n
     * @param int $nulls the null count; 0: no bitmap needed (null returned); -1: always read it
     * @return bool[]|null
     */
    protected function validity( $buffer, $n, $nulls )
    {
        if ( $nulls === 0 || $buffer === '' )
            return null;
        $out = array();
        for ( $i = 0; $i < $n; $i++ )
        {
            $byte = $i >> 3;
            $out[] = $byte < strlen( $buffer ) && ( ( ord( $buffer[$byte] ) >> ( $i & 7 ) ) & 1 ) === 1;
        }
        return $out;
    }

    /**
     * Integers of a buffer.
     *
     * @param string $buffer
     * @param int $bits 8, 16, 32 or 64
     * @param bool $signed
     * @param int $n
     * @return array int, or text for an unsigned 64-bit number above PHP's range
     */
    public static function ints( $buffer, $bits, $signed, $n )
    {
        if ( $n <= 0 )
            return array();
        $bytes = intdiv( (int)$bits, 8 );
        if ( !in_array( $bytes, array( 1, 2, 4, 8 ), true ) )
            throw new expImportSourceException( "an Arrow integer has $bits bits" );
        $buffer = (string)substr( (string)$buffer, 0, $n * $bytes );
        if ( strlen( $buffer ) < $n * $bytes )
            throw new expImportSourceException( 'an Arrow buffer is cut off' );
        $format = array( 1 => 'C*', 2 => 'v*', 4 => 'V*', 8 => 'P*' );
        $values = array_values( unpack( $format[$bytes], $buffer ) );
        if ( $bytes === 8 )
            return $signed ? $values : array_map( function ( $v ) { return $v < 0 ? sprintf( '%u', $v ) : $v; }, $values );
        if ( !$signed )
            return $values;
        $half = 1 << ( $bytes * 8 - 1 );
        return array_map( function ( $v ) use ( $half ) { return $v >= $half ? $v - 2 * $half : $v; }, $values );
    }

    /**
     * Floating point numbers of a buffer (not finite ones as the text NaN, INF, -INF).
     *
     * @param string $buffer
     * @param int $precision 0 half, 1 single, 2 double
     * @param int $n
     * @return array
     */
    public static function floats( $buffer, $precision, $n )
    {
        if ( $n <= 0 )
            return array();
        if ( $precision === 0 )
        {
            $values = array();
            foreach ( self::ints( $buffer, 16, false, $n ) as $h )
            {
                $exp = ( $h >> 10 ) & 0x1F;
                $mant = $h & 0x3FF;
                $sign = $h & 0x8000 ? -1 : 1;
                if ( $exp === 0 )
                    $v = (float)( $sign * $mant * pow( 2, -24 ) );
                elseif ( $exp === 31 )
                    $v = $mant ? NAN : $sign * INF;
                else
                    $v = (float)( $sign * ( 1 + $mant / 1024 ) * pow( 2, $exp - 15 ) );
                $values[] = $v;
            }
        }
        else
        {
            $bytes = $precision === 1 ? 4 : 8;
            $buffer = (string)substr( (string)$buffer, 0, $n * $bytes );
            if ( strlen( $buffer ) < $n * $bytes )
                throw new expImportSourceException( 'an Arrow buffer is cut off' );
            $values = array_values( unpack( $precision === 1 ? 'g*' : 'e*', $buffer ) );
        }
        return array_map( function ( $v ) { return is_finite( $v ) ? $v : ( is_nan( $v ) ? 'NaN' : ( $v > 0 ? 'INF' : '-INF' ) ); }, $values );
    }

    // ---- compression -------------------------------------------------------------------------------------------------

    /**
     * Decompresses an LZ4 frame (the LZ4 frame format: blocks compressed or stored, checksums skipped).
     *
     * @param string $frame
     * @return string
     * @throws expImportSourceException
     */
    public static function lz4Frame( $frame )
    {
        $frame = (string)$frame;
        if ( strlen( $frame ) < 7 || unpack( 'V', $frame )[1] !== 0x184D2204 )
            throw new expImportSourceException( 'a compressed Arrow buffer is not an LZ4 frame' );
        $flags = ord( $frame[4] );
        $pos = 6 + ( $flags & 0x08 ? 8 : 0 ) + ( $flags & 0x01 ? 4 : 0 ) + 1;
        $blockChecksum = (bool)( $flags & 0x10 );
        $out = '';
        $length = strlen( $frame );
        while ( $pos + 4 <= $length )
        {
            $size = unpack( 'V', $frame, $pos )[1];
            $pos += 4;
            if ( $size === 0 )
                break;
            $stored = (bool)( $size & 0x80000000 );
            $size &= 0x7FFFFFFF;
            if ( $pos + $size > $length )
                throw new expImportSourceException( 'an LZ4 frame of the Arrow data is cut off' );
            $block = (string)substr( $frame, $pos, $size );
            $out .= $stored ? $block : self::lz4Block( $block );
            $pos += $size + ( $blockChecksum ? 4 : 0 );
        }
        return $out;
    }

    /**
     * Decompresses an LZ4 block (sequences of literals and matches).
     *
     * @param string $block
     * @return string
     * @throws expImportSourceException
     */
    public static function lz4Block( $block )
    {
        $out = '';
        $pos = 0;
        $length = strlen( $block );
        while ( $pos < $length )
        {
            $token = ord( $block[$pos++] );
            $literals = $token >> 4;
            if ( $literals === 15 )
                do
                {
                    if ( $pos >= $length )
                        throw new expImportSourceException( 'an LZ4 block of the Arrow data is cut off' );
                    $byte = ord( $block[$pos++] );
                    $literals += $byte;
                }
                while ( $byte === 255 );
            if ( $pos + $literals > $length )
                throw new expImportSourceException( 'an LZ4 block of the Arrow data is cut off' );
            $out .= substr( $block, $pos, $literals );
            $pos += $literals;
            if ( $pos >= $length )
                break;
            if ( $pos + 2 > $length )
                throw new expImportSourceException( 'an LZ4 block of the Arrow data is cut off' );
            $offset = ord( $block[$pos] ) | ( ord( $block[$pos + 1] ) << 8 );
            $pos += 2;
            $match = $token & 0x0F;
            if ( $match === 15 )
                do
                {
                    if ( $pos >= $length )
                        throw new expImportSourceException( 'an LZ4 block of the Arrow data is cut off' );
                    $byte = ord( $block[$pos++] );
                    $match += $byte;
                }
                while ( $byte === 255 );
            $match += 4;
            $start = strlen( $out ) - $offset;
            if ( $offset === 0 || $start < 0 )
                throw new expImportSourceException( 'an LZ4 block of the Arrow data is damaged' );
            if ( $offset >= $match )
                $out .= substr( $out, $start, $match );
            else
            {
                // an overlapping match repeats the last $offset bytes
                $piece = substr( $out, $start, $offset );
                $out .= substr( str_repeat( $piece, intdiv( $match, $offset ) + 1 ), 0, $match );
            }
        }
        return $out;
    }

    /** @return string a Zstandard buffer, decompressed (the PECL extension zstd) */
    public static function zstd( $data )
    {
        if ( !function_exists( 'zstd_uncompress' ) )
            throw new expImportSourceException( 'the Arrow data is compressed with Zstandard, which needs the PHP extension zstd (PECL zstd); write it with LZ4 or without compression' );
        $out = @call_user_func( 'zstd_uncompress', (string)$data );
        if ( !is_string( $out ) )
            throw new expImportSourceException( 'a Zstandard buffer of the Arrow data cannot be decompressed' );
        return $out;
    }
}
