<?php
/**
 * Writers of Avro object container files and Arrow IPC data for the tests of the Avro and Arrow sources (and for the
 * example files share/examples/teas.avro and teas.arrow): small, following the specifications, independent of the
 * readers in classes/exp/formats.
 */

/** Writes Avro object container files. */
class AvroTestWriter
{
    /** @var array full name => named schema */
    protected $named = array();

    /** @var bool write array and map blocks with a negative count and their size */
    public $negativeBlocks = false;

    /**
     * An Avro object container file.
     *
     * @param mixed $schema
     * @param array $records
     * @param string $codec null or deflate
     * @param int $perBlock records per block
     * @param array $meta more metadata
     * @return string
     */
    public function file( $schema, array $records, $codec = 'null', $perBlock = 100, array $meta = array() )
    {
        $this->named = array();
        $this->register( $schema, '' );
        $sync = substr( md5( 'sync' . json_encode( $schema ), true ), 0, 16 );
        $out = "Obj\x01";
        $meta = array( 'avro.schema' => json_encode( $schema, JSON_UNESCAPED_SLASHES ), 'avro.codec' => $codec ) + $meta;
        $out .= self::long( count( $meta ) );
        foreach ( $meta as $key => $value )
            $out .= self::bytes( (string)$key ) . self::bytes( (string)$value );
        $out .= self::long( 0 ) . $sync;
        foreach ( array_chunk( $records, max( 1, $perBlock ) ) as $chunk )
        {
            $data = '';
            foreach ( $chunk as $record )
                $data .= $this->encode( $schema, $record, '' );
            if ( $codec === 'deflate' )
                $data = (string)gzdeflate( $data );
            $out .= self::long( count( $chunk ) ) . self::long( strlen( $data ) ) . $data . $sync;
        }
        return $out;
    }

    protected function register( $schema, $ns )
    {
        if ( is_array( $schema ) && array_values( $schema ) === $schema )
        {
            foreach ( $schema as $s )
                $this->register( $s, $ns );
            return;
        }
        if ( !is_array( $schema ) )
            return;
        if ( in_array( $schema['type'], array( 'record', 'enum', 'fixed' ), true ) )
        {
            $name = strpos( $schema['name'], '.' ) === false && ( isset( $schema['namespace'] ) ? $schema['namespace'] : $ns ) !== ''
                  ? ( isset( $schema['namespace'] ) ? $schema['namespace'] : $ns ) . '.' . $schema['name'] : $schema['name'];
            $this->named[$name] = $schema;
            $this->named[$schema['name']] = $schema;
            $inner = strpos( $name, '.' ) !== false ? substr( $name, 0, strrpos( $name, '.' ) ) : '';
            if ( $schema['type'] === 'record' )
                foreach ( $schema['fields'] as $f )
                    $this->register( $f['type'], $inner );
        }
        if ( $schema['type'] === 'array' )
            $this->register( $schema['items'], $ns );
        if ( $schema['type'] === 'map' )
            $this->register( $schema['values'], $ns );
    }

    /** @return string a value encoded by a schema */
    public function encode( $schema, $value, $ns )
    {
        if ( is_array( $schema ) && array_values( $schema ) === $schema )
        {
            // a union: null for null, else the first branch that is not null
            foreach ( $schema as $i => $branch )
                if ( ( $value === null ) === ( $branch === 'null' ) )
                    return self::long( $i ) . $this->encode( $branch, $value, $ns );
        }
        if ( is_string( $schema ) )
        {
            switch ( $schema )
            {
                case 'null':
                    return '';
                case 'boolean':
                    return $value ? "\x01" : "\x00";
                case 'int':
                case 'long':
                    return self::long( (int)$value );
                case 'float':
                    return pack( 'g', $value );
                case 'double':
                    return pack( 'e', $value );
                case 'bytes':
                case 'string':
                    return self::bytes( (string)$value );
            }
            $schema = isset( $this->named[$schema] ) ? $this->named[$schema] : $this->named[$ns . '.' . $schema];
        }
        $logical = isset( $schema['logicalType'] ) ? $schema['logicalType'] : '';
        if ( $logical === 'decimal' )
        {
            $bytes = self::twos( (int)$value );
            if ( $schema['type'] === 'fixed' )
                return str_pad( $bytes, $schema['size'], (int)$value < 0 ? "\xFF" : "\x00", STR_PAD_LEFT );
            return self::bytes( $bytes );
        }
        switch ( $schema['type'] )
        {
            case 'record':
                $out = '';
                foreach ( $schema['fields'] as $f )
                    $out .= $this->encode( $f['type'], isset( $value[$f['name']] ) ? $value[$f['name']] : null, $ns );
                return $out;
            case 'enum':
                return self::long( (int)array_search( $value, $schema['symbols'], true ) );
            case 'fixed':
                return (string)$value;
            case 'array':
            case 'map':
                if ( !$value )
                    return self::long( 0 );
                $items = '';
                foreach ( $value as $k => $v )
                    $items .= ( $schema['type'] === 'map' ? self::bytes( (string)$k ) : '' ) . $this->encode( $schema['type'] === 'map' ? $schema['values'] : $schema['items'], $v, $ns );
                if ( $this->negativeBlocks )
                    return self::long( -count( $value ) ) . self::long( strlen( $items ) ) . $items . self::long( 0 );
                return self::long( count( $value ) ) . $items . self::long( 0 );
        }
        return $this->encode( $schema['type'], $value, $ns );
    }

    /** @return string a zig-zag varint */
    public static function long( $n )
    {
        $n = (int)$n;
        $z = ( $n << 1 ) ^ ( $n >> 63 );
        $out = '';
        do
        {
            $byte = $z & 0x7F;
            $z = ( $z >> 7 ) & ( PHP_INT_MAX >> 6 );
            $out .= chr( $z ? $byte | 0x80 : $byte );
        }
        while ( $z );
        return $out;
    }

    /** @return string bytes with their length */
    public static function bytes( $data )
    {
        return self::long( strlen( $data ) ) . $data;
    }

    /** @return string a number as big-endian two's complement, as short as it goes */
    public static function twos( $n )
    {
        $bytes = pack( 'J', $n );
        while ( strlen( $bytes ) > 1 && ( ( $bytes[0] === "\x00" && ord( $bytes[1] ) < 128 ) || ( $bytes[0] === "\xFF" && ord( $bytes[1] ) >= 128 ) ) )
            $bytes = substr( $bytes, 1 );
        return $bytes;
    }
}

/** Builds FlatBuffers from a tree, front to back (every reference points forward). */
class FlatBufferTestBuilder
{
    /** @var string */
    protected $buf = '';

    /**
     * A FlatBuffer whose root is a table.
     *
     * @param array $root array( 'table', array( index => array( kind, value ) ) )
     * @return string padded to 8 bytes
     */
    public function build( array $root )
    {
        $this->buf = "\0\0\0\0";
        $pos = $this->write( $root );
        $this->patch( 0, $pos );
        $this->align( 8 );
        return $this->buf;
    }

    protected function align( $n, $extra = 0 )
    {
        while ( ( strlen( $this->buf ) + $extra ) % $n )
            $this->buf .= "\0";
    }

    protected function patch( $at, $target )
    {
        $this->buf = substr_replace( $this->buf, pack( 'V', $target - $at ), $at, 4 );
    }

    const SIZES = array( 'u8' => 1, 'bool' => 1, 'i16' => 2, 'i32' => 4, 'i64' => 8, 'off' => 4 );

    /** @return int the position of a node */
    protected function write( array $node )
    {
        switch ( $node[0] )
        {
            case 'string':
                $this->align( 4 );
                $pos = strlen( $this->buf );
                $this->buf .= pack( 'V', strlen( $node[1] ) ) . $node[1] . "\0";
                return $pos;
            case 'structs':
                $this->align( 8, 4 );
                $pos = strlen( $this->buf );
                $this->buf .= pack( 'V', $node[2] ) . $node[1];
                return $pos;
            case 'tables':
                $this->align( 4 );
                $pos = strlen( $this->buf );
                $this->buf .= pack( 'V', count( $node[1] ) ) . str_repeat( "\0", 4 * count( $node[1] ) );
                foreach ( $node[1] as $i => $child )
                    $this->patch( $pos + 4 + 4 * $i, $this->write( $child ) );
                return $pos;
        }
        // a table: the vtable, then the table, then what it refers to
        $fields = $node[1];
        ksort( $fields );
        $count = $fields ? max( array_keys( $fields ) ) + 1 : 0;
        $this->align( 2 );
        $vtPos = strlen( $this->buf );
        $vtSize = 4 + 2 * $count;
        $tablePos = $vtPos + $vtSize;
        while ( $tablePos % 8 )
            $tablePos++;
        $cur = $tablePos + 4;
        $offsets = array();
        foreach ( $fields as $index => $field )
        {
            $size = self::SIZES[$field[0]];
            while ( $cur % $size )
                $cur++;
            $offsets[$index] = $cur - $tablePos;
            $cur += $size;
        }
        $vt = pack( 'vv', $vtSize, $cur - $tablePos );
        for ( $i = 0; $i < $count; $i++ )
            $vt .= pack( 'v', isset( $offsets[$i] ) ? $offsets[$i] : 0 );
        $this->buf .= $vt;
        $this->buf .= str_repeat( "\0", $tablePos - strlen( $this->buf ) );
        $table = pack( 'V', $tablePos - $vtPos );
        $refs = array();
        foreach ( $fields as $index => $field )
        {
            while ( strlen( $table ) < $offsets[$index] )
                $table .= "\0";
            switch ( $field[0] )
            {
                case 'u8':
                case 'bool':
                    $table .= chr( (int)$field[1] & 0xFF );
                    break;
                case 'i16':
                    $table .= pack( 'v', $field[1] & 0xFFFF );
                    break;
                case 'i32':
                    $table .= pack( 'V', $field[1] & 0xFFFFFFFF );
                    break;
                case 'i64':
                    $table .= pack( 'P', $field[1] );
                    break;
                case 'off':
                    $refs[$tablePos + $offsets[$index]] = $field[1];
                    $table .= "\0\0\0\0";
            }
        }
        $this->buf .= $table;
        foreach ( $refs as $at => $child )
            $this->patch( $at, $this->write( $child ) );
        return $tablePos;
    }
}

/**
 * Writes Arrow IPC files and streams. A column: array( 'name', 'type' => int|utf8|..., 'values' => list, ... ):
 *   int (bits, signed), float (precision 1 or 2), utf8, large_utf8, binary, bool, date32, timestamp (unit, tz),
 *   decimal (precision, scale; values are the unscaled integers), struct (children), list (child: one column whose
 *   values are the flattened items), dict (values: the dictionary, indexes), null.
 */
class ArrowTestWriter
{
    /** @var string none, lz4 (stored blocks), lz4literal (compressed blocks of literals only) */
    public $compression = 'none';

    /** @var string the body being written */
    protected $body = '';

    /** @var string FieldNode structs */
    protected $nodes = '';

    /** @var string Buffer structs */
    protected $buffers = '';

    /** @var int */
    protected $nodeCount = 0;

    /** @var int */
    protected $bufferCount = 0;

    /**
     * Arrow data.
     *
     * @param array[] $columns
     * @param int[] $batches the row counts of the batches (each batch takes the next rows of every column)
     * @param bool $file the file format (else the stream format)
     * @return string
     */
    public function write( array $columns, array $batches, $file = true )
    {
        $fb = new FlatBufferTestBuilder();
        $schema = array( 'table', array( 0 => array( 'i16', 0 ), 1 => array( 'off', array( 'tables', array_map( array( $this, 'field' ), $columns ) ) ) ) );
        $out = $file ? "ARROW1\0\0" : '';
        $out .= $this->message( $fb, 1, $schema, '' );
        $blocks = '';
        $count = 0;
        // the dictionaries
        foreach ( $columns as $column )
            if ( $column['type'] === 'dict' )
            {
                $this->reset();
                $dict = array( 'name' => 'd', 'type' => 'utf8', 'values' => $column['dictionary'] );
                $this->column( $dict, 0, count( $column['dictionary'] ) );
                $rb = $this->recordBatch( count( $column['dictionary'] ) );
                $out .= $this->message( $fb, 2, array( 'table', array( 0 => array( 'i64', $column['id'] ), 1 => array( 'off', $rb ) ) ), $this->body );
            }
        $start = 0;
        foreach ( $batches as $rows )
        {
            $this->reset();
            foreach ( $columns as $column )
                $this->column( $column, $start, $rows );
            $offset = strlen( $out );
            $message = $this->message( $fb, 3, $this->recordBatch( $rows ), $this->body );
            $blocks .= pack( 'PVVP', $offset - 0, strlen( $message ) - strlen( $this->body ), 0, strlen( $this->body ) );
            $count++;
            $out .= $message;
            $start += $rows;
        }
        $out .= "\xFF\xFF\xFF\xFF\0\0\0\0";
        if ( !$file )
            return $out;
        $footer = $fb->build( array( 'table', array( 0 => array( 'i16', 4 ), 1 => array( 'off', $schema ),
                                                     3 => array( 'off', array( 'structs', $blocks, $count ) ) ) ) );
        return $out . $footer . pack( 'V', strlen( $footer ) ) . 'ARROW1';
    }

    protected function reset()
    {
        $this->body = '';
        $this->nodes = '';
        $this->buffers = '';
        $this->nodeCount = 0;
        $this->bufferCount = 0;
    }

    /** @return array the RecordBatch table of the nodes and buffers written */
    protected function recordBatch( $rows )
    {
        $fields = array( 0 => array( 'i64', $rows ), 1 => array( 'off', array( 'structs', $this->nodes, $this->nodeCount ) ),
                         2 => array( 'off', array( 'structs', $this->buffers, $this->bufferCount ) ) );
        if ( $this->compression !== 'none' )
            $fields[3] = array( 'off', array( 'table', array( 0 => array( 'u8', 0 ) ) ) );
        return array( 'table', $fields );
    }

    /** @return string an encapsulated message */
    protected function message( FlatBufferTestBuilder $fb, $type, array $header, $body )
    {
        $meta = $fb->build( array( 'table', array( 0 => array( 'i16', 4 ), 1 => array( 'u8', $type ), 2 => array( 'off', $header ),
                                                   3 => array( 'i64', strlen( $body ) ) ) ) );
        return "\xFF\xFF\xFF\xFF" . pack( 'V', strlen( $meta ) ) . $meta . $body;
    }

    /** @return array the Field table of a column */
    public function field( array $column )
    {
        $types = array( 'null' => 1, 'int' => 2, 'float' => 3, 'binary' => 4, 'utf8' => 5, 'bool' => 6, 'decimal' => 7, 'date32' => 8, 'timestamp' => 10,
                        'list' => 12, 'struct' => 13, 'union' => 14, 'large_utf8' => 20, 'dict' => 5 );
        $type = $column['type'];
        $params = array();
        if ( $type === 'int' )
            $params = array( 0 => array( 'i32', $column['bits'] ), 1 => array( 'bool', $column['signed'] ? 1 : 0 ) );
        elseif ( $type === 'float' )
            $params = array( 0 => array( 'i16', $column['precision'] ) );
        elseif ( $type === 'decimal' )
            $params = array( 0 => array( 'i32', $column['precision'] ), 1 => array( 'i32', $column['scale'] ), 2 => array( 'i32', 128 ) );
        elseif ( $type === 'date32' )
            $params = array( 0 => array( 'i16', 0 ) );
        elseif ( $type === 'timestamp' )
            $params = array( 0 => array( 'i16', $column['unit'] ) ) + ( $column['tz'] !== '' ? array( 1 => array( 'off', array( 'string', $column['tz'] ) ) ) : array() );
        $fields = array( 0 => array( 'off', array( 'string', $column['name'] ) ), 1 => array( 'bool', 1 ), 2 => array( 'u8', $types[$type] ),
                         3 => array( 'off', array( 'table', $params ) ) );
        if ( $type === 'dict' )
            $fields[4] = array( 'off', array( 'table', array( 0 => array( 'i64', $column['id'] ),
                                                              1 => array( 'off', array( 'table', array( 0 => array( 'i32', 32 ), 1 => array( 'bool', 1 ) ) ) ) ) ) );
        $children = array();
        if ( $type === 'struct' )
            $children = $column['children'];
        if ( $type === 'list' )
            $children = array( $column['child'] );
        if ( $children )
            $fields[5] = array( 'off', array( 'tables', array_map( array( $this, 'field' ), $children ) ) );
        return array( 'table', $fields );
    }

    /** Writes the node and buffers of rows [$start, $start + $rows) of a column. */
    protected function column( array $column, $start, $rows )
    {
        $values = array_slice( $column['values'], $start, $rows );
        $nulls = count( array_filter( $values, 'is_null' ) );
        $this->nodes .= pack( 'PP', $rows, $column['type'] === 'null' ? $rows : $nulls );
        $this->nodeCount++;
        if ( $column['type'] === 'null' )
            return;
        $bitmap = '';
        if ( $nulls )
            $bitmap = self::bitmap( array_map( function ( $v ) { return $v !== null; }, $values ) );
        $this->buffer( $bitmap );
        switch ( $column['type'] )
        {
            case 'int':
                $format = array( 8 => 'C', 16 => 'v', 32 => 'V', 64 => 'P' );
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( $format[$column['bits']], (int)$v );
                $this->buffer( $data );
                return;
            case 'float':
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( $column['precision'] === 1 ? 'g' : 'e', (float)$v );
                $this->buffer( $data );
                return;
            case 'bool':
                $this->buffer( self::bitmap( array_map( 'boolval', $values ) ) );
                return;
            case 'date32':
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( 'V', (int)$v & 0xFFFFFFFF );
                $this->buffer( $data );
                return;
            case 'timestamp':
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( 'P', (int)$v );
                $this->buffer( $data );
                return;
            case 'decimal':
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( 'P', (int)$v ) . ( (int)$v < 0 ? str_repeat( "\xFF", 8 ) : str_repeat( "\0", 8 ) );
                $this->buffer( $data );
                return;
            case 'utf8':
            case 'binary':
            case 'large_utf8':
                $offsets = pack( $column['type'] === 'large_utf8' ? 'P' : 'V', 0 );
                $data = '';
                foreach ( $values as $v )
                {
                    $data .= (string)$v;
                    $offsets .= pack( $column['type'] === 'large_utf8' ? 'P' : 'V', strlen( $data ) );
                }
                $this->buffer( $offsets );
                $this->buffer( $data );
                return;
            case 'dict':
                $data = '';
                foreach ( $values as $v )
                    $data .= pack( 'V', $v === null ? 0 : (int)$v );
                $this->buffer( $data );
                return;
            case 'struct':
                foreach ( $column['children'] as $child )
                    $this->column( $child, $start, $rows );
                return;
            case 'list':
                // values: the lengths of the lists (null: a null list); the child column holds every item
                $offsets = pack( 'V', 0 );
                $before = 0;
                foreach ( array_slice( $column['values'], 0, $start ) as $v )
                    $before += (int)$v;
                $total = 0;
                foreach ( $values as $v )
                {
                    $total += (int)$v;
                    $offsets .= pack( 'V', $total );
                }
                $this->buffer( $offsets );
                $this->column( $column['child'], $before, $total );
                return;
        }
    }

    /** Appends a buffer to the body (compressed when asked), padded to 8 bytes. */
    protected function buffer( $data )
    {
        if ( $this->compression !== 'none' && $data !== '' )
        {
            $frame = "\x04\x22\x4D\x18\x60\x40\x00";
            if ( $this->compression === 'lz4' )
                $frame .= pack( 'V', strlen( $data ) | 0x80000000 ) . $data;
            else
            {
                $n = strlen( $data );
                $block = $n >= 15 ? "\xF0" . str_repeat( "\xFF", intdiv( $n - 15, 255 ) ) . chr( ( $n - 15 ) % 255 ) : chr( $n << 4 );
                $block .= $data;
                $frame .= pack( 'V', strlen( $block ) ) . $block;
            }
            $data = pack( 'P', strlen( $data ) ) . $frame . "\0\0\0\0";
        }
        $this->buffers .= pack( 'PP', strlen( $this->body ), strlen( $data ) );
        $this->bufferCount++;
        $this->body .= $data;
        while ( strlen( $this->body ) % 8 )
            $this->body .= "\0";
    }

    /** @return string a bitmap of booleans, least significant bit first */
    public static function bitmap( array $bits )
    {
        $out = '';
        foreach ( array_chunk( array_values( $bits ), 8 ) as $chunk )
        {
            $byte = 0;
            foreach ( $chunk as $i => $bit )
                if ( $bit )
                    $byte |= 1 << $i;
            $out .= chr( $byte );
        }
        return $out;
    }
}
