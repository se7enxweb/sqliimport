<?php
/**
 * File containing the expImportAvro class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A reader of Apache Avro object container files (Avro 1.x specification), in PHP alone: the header (magic Obj1, the
 * metadata with avro.schema and avro.codec, the sync marker), then blocks of records (a count, a size, the data,
 * the sync marker again).
 *
 * Codecs: null, deflate (zlib), snappy (only with the PECL extension snappy; the CRC-32 of each block is checked),
 * zstandard (the PECL extension zstd) and bzip2 (the extension bz2); others are refused with a clear message.
 *
 * Types: null, boolean, int and long (zig-zag varints), float, double, bytes and fixed (as base64), string, record,
 * enum (its symbol), array, map, union (the value of its branch) and named types by name, recursion included.
 * Logical types: date as 2026-10-22; timestamp-millis and timestamp-micros as ISO 8601 in UTC (Z); the local-timestamp
 * ones without the Z; time-millis and time-micros as 10:11:12.345; decimal (bytes or fixed) as a decimal number in
 * text with its scale, never a float; uuid as text. A float or double that is not finite is the text NaN, INF or -INF.
 */
class expImportAvro
{
    /** The largest block, decompressed, in bytes. */
    const MAX_BLOCK = 268435456;

    /** @var resource */
    protected $fp;

    /** @var array the schema (parsed JSON) */
    public $schema;

    /** @var string */
    public $codec = 'null';

    /** @var array the metadata of the header (name => bytes) */
    public $meta = array();

    /** @var string the 16 bytes of the sync marker */
    protected $sync = '';

    /** @var int the offset of the first block */
    public $dataStart = 0;

    /** @var array full name => the definition of a named type */
    protected $named = array();

    /** @var string the data of the block being decoded */
    protected $buf = '';

    /** @var int the position in $buf */
    protected $pos = 0;

    /**
     * Opens a file and reads its header.
     *
     * @param string $file
     * @throws expImportSourceException
     */
    public function __construct( $file )
    {
        $fp = @fopen( (string)$file, 'rb' );
        if ( !$fp )
            throw new expImportSourceException( 'the Avro file cannot be read' );
        $this->fp = $fp;
        if ( fread( $fp, 4 ) !== "Obj\x01" )
            throw new expImportSourceException( 'not an Avro object container file (it does not begin with Obj1)' );
        // the metadata: a map of bytes, in blocks
        while ( true )
        {
            $count = $this->streamLong();
            if ( $count === 0 )
                break;
            if ( $count < 0 )
            {
                $count = -$count;
                $this->streamLong();
            }
            if ( $count > 10000 )
                throw new expImportSourceException( 'the Avro header has too many metadata entries' );
            for ( $i = 0; $i < $count; $i++ )
            {
                $key = $this->streamBytes( 65536 );
                $this->meta[$key] = $this->streamBytes( 16777216 );
            }
        }
        $this->sync = (string)fread( $fp, 16 );
        if ( strlen( $this->sync ) !== 16 )
            throw new expImportSourceException( 'the Avro header is cut off' );
        $this->dataStart = (int)ftell( $fp );
        if ( !isset( $this->meta['avro.schema'] ) )
            throw new expImportSourceException( 'the Avro file has no schema (avro.schema)' );
        $schema = json_decode( $this->meta['avro.schema'], true, 512 );
        if ( $schema === null )
            throw new expImportSourceException( 'the schema of the Avro file is not valid JSON' );
        $this->schema = $schema;
        $this->register( $schema, '' );
        $this->codec = isset( $this->meta['avro.codec'] ) && $this->meta['avro.codec'] !== '' ? $this->meta['avro.codec'] : 'null';
        self::checkCodec( $this->codec );
    }

    public function __destruct()
    {
        if ( is_resource( $this->fp ) )
            fclose( $this->fp );
    }

    /**
     * Whether this PHP can decompress a codec.
     *
     * @param string $codec
     * @throws expImportSourceException
     */
    public static function checkCodec( $codec )
    {
        switch ( $codec )
        {
            case 'null':
                return;
            case 'deflate':
                if ( !function_exists( 'gzinflate' ) )
                    throw new expImportSourceException( 'the Avro codec deflate needs the PHP extension zlib' );
                return;
            case 'snappy':
                if ( !function_exists( 'snappy_uncompress' ) )
                    throw new expImportSourceException( 'the Avro codec snappy needs the PHP extension snappy (PECL snappy; RHEL: php-pecl-snappy of Remi, '
                                                      . 'Debian: pecl install snappy), or write the file with the codec deflate or null' );
                return;
            case 'zstandard':
                if ( !function_exists( 'zstd_uncompress' ) )
                    throw new expImportSourceException( 'the Avro codec zstandard needs the PHP extension zstd (PECL zstd), or write the file with deflate or null' );
                return;
            case 'bzip2':
                if ( !function_exists( 'bzdecompress' ) )
                    throw new expImportSourceException( 'the Avro codec bzip2 needs the PHP extension bz2' );
                return;
        }
        throw new expImportSourceException( "the Avro codec $codec cannot be read (only null, deflate, snappy, zstandard and bzip2)" );
    }

    /** @return array codec => whether this PHP reads it, and how */
    public static function codecs()
    {
        return array( 'null' => 'PHP', 'deflate' => function_exists( 'gzinflate' ) ? 'zlib' : '',
                      'snappy' => function_exists( 'snappy_uncompress' ) ? 'snappy' : '', 'zstandard' => function_exists( 'zstd_uncompress' ) ? 'zstd' : '',
                      'bzip2' => function_exists( 'bzdecompress' ) ? 'bz2' : '' );
    }

    // ---- the schema --------------------------------------------------------------------------------------------------

    /** Notes the named types of a schema (records, enums, fixed) by their full names. */
    protected function register( $schema, $namespace, $depth = 0 )
    {
        if ( $depth > 64 )
            throw new expImportSourceException( 'the Avro schema is nested too deep' );
        if ( is_array( $schema ) && array_values( $schema ) === $schema )
        {
            foreach ( $schema as $branch )
                $this->register( $branch, $namespace, $depth + 1 );
            return;
        }
        if ( !is_array( $schema ) || !isset( $schema['type'] ) )
            return;
        $type = $schema['type'];
        if ( is_array( $type ) )
        {
            $this->register( $type, $namespace, $depth + 1 );
            return;
        }
        if ( in_array( $type, array( 'record', 'error', 'enum', 'fixed' ), true ) )
        {
            if ( !isset( $schema['name'] ) || !is_string( $schema['name'] ) )
                throw new expImportSourceException( "an Avro $type has no name" );
            $name = $schema['name'];
            if ( strpos( $name, '.' ) === false )
            {
                $ns = isset( $schema['namespace'] ) && is_string( $schema['namespace'] ) ? $schema['namespace'] : $namespace;
                $name = $ns !== '' ? $ns . '.' . $name : $name;
            }
            $schema['@namespace'] = strpos( $name, '.' ) !== false ? substr( $name, 0, (int)strrpos( $name, '.' ) ) : '';
            $this->named[$name] = $schema;
            if ( $type === 'record' || $type === 'error' )
                foreach ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : array() as $field )
                    if ( is_array( $field ) && isset( $field['type'] ) )
                        $this->register( $field['type'], $schema['@namespace'], $depth + 1 );
            return;
        }
        if ( $type === 'array' && isset( $schema['items'] ) )
            $this->register( $schema['items'], $namespace, $depth + 1 );
        if ( $type === 'map' && isset( $schema['values'] ) )
            $this->register( $schema['values'], $namespace, $depth + 1 );
    }

    /**
     * The definition of a type name.
     *
     * @param string $name
     * @param string $namespace
     * @return array
     * @throws expImportSourceException
     */
    protected function lookup( $name, $namespace )
    {
        if ( $namespace !== '' && isset( $this->named[$namespace . '.' . $name] ) )
            return $this->named[$namespace . '.' . $name];
        if ( isset( $this->named[$name] ) )
            return $this->named[$name];
        throw new expImportSourceException( "the Avro schema names an unknown type $name" );
    }

    /** @return string[] the names of the fields of a record schema (the columns), or array( 'value' ) */
    public function fieldNames()
    {
        $schema = is_string( $this->schema ) && isset( $this->named[$this->schema] ) ? $this->named[$this->schema] : $this->schema;
        if ( is_array( $schema ) && isset( $schema['type'], $schema['fields'] ) && $schema['type'] === 'record' )
            return array_map( function ( $f ) { return (string)$f['name']; }, (array)$schema['fields'] );
        return array( 'value' );
    }

    // ---- the blocks --------------------------------------------------------------------------------------------------

    /**
     * The records of the file from a block on.
     *
     * @param int $offset the offset of a block (0: the first)
     * @param int $skip records of that block to leave out
     * @return Generator array( 'value' => the record, 'block' => offset of its block, 'row' => its position in it )
     * @throws expImportSourceException
     */
    public function records( $offset = 0, $skip = 0 )
    {
        fseek( $this->fp, $offset > 0 ? (int)$offset : $this->dataStart );
        while ( true )
        {
            $block = (int)ftell( $this->fp );
            $first = fread( $this->fp, 1 );
            if ( $first === '' || $first === false )
                return;
            fseek( $this->fp, -1, SEEK_CUR );
            $count = $this->streamLong();
            $size = $this->streamLong();
            if ( $count < 0 || $size < 0 || $size > self::MAX_BLOCK )
                throw new expImportSourceException( 'an Avro block has a wrong count or size' );
            $data = $size > 0 ? (string)fread( $this->fp, $size ) : '';
            if ( strlen( $data ) !== $size )
                throw new expImportSourceException( 'the Avro file is cut off in a block' );
            if ( fread( $this->fp, 16 ) !== $this->sync )
                throw new expImportSourceException( 'an Avro block does not end with the sync marker of the file (the file is damaged)' );
            if ( $skip >= $count )
            {
                $skip -= $count;
                continue;
            }
            $this->buf = $this->decompress( $data );
            $this->pos = 0;
            $namespace = '';
            for ( $row = 0; $row < $count; $row++ )
            {
                $value = $this->read( $this->schema, $namespace, 0 );
                if ( $row < $skip )
                    continue;
                yield array( 'value' => $value, 'block' => $block, 'row' => $row );
            }
            $skip = 0;
            $here = (int)ftell( $this->fp );
            $this->buf = '';
            fseek( $this->fp, $here );
        }
    }

    /** @return string the data of a block, decompressed */
    protected function decompress( $data )
    {
        switch ( $this->codec )
        {
            case 'null':
                return $data;
            case 'deflate':
                $out = @gzinflate( $data, self::MAX_BLOCK );
                break;
            case 'snappy':
                $out = @call_user_func( 'snappy_uncompress', substr( $data, 0, -4 ) );
                if ( is_string( $out ) && pack( 'N', crc32( $out ) ) !== substr( $data, -4 ) )
                    throw new expImportSourceException( 'the CRC-32 of a snappy block of the Avro file is wrong' );
                break;
            case 'zstandard':
                $out = @call_user_func( 'zstd_uncompress', $data );
                break;
            default:
                $out = @bzdecompress( $data );
        }
        if ( !is_string( $out ) )
            throw new expImportSourceException( "a block of the Avro file cannot be decompressed ({$this->codec})" );
        return $out;
    }

    // ---- decoding ----------------------------------------------------------------------------------------------------

    /**
     * A value of a type from the current block.
     *
     * @param mixed $schema
     * @param string $namespace
     * @param int $depth
     * @return mixed
     * @throws expImportSourceException
     */
    protected function read( $schema, $namespace, $depth )
    {
        if ( $depth > 64 )
            throw new expImportSourceException( 'the Avro data is nested too deep' );
        if ( is_array( $schema ) && array_values( $schema ) === $schema )
        {
            $index = $this->long();
            if ( $index < 0 || $index >= count( $schema ) )
                throw new expImportSourceException( 'an Avro union has a branch that is not in the schema' );
            return $this->read( $schema[$index], $namespace, $depth + 1 );
        }
        if ( is_string( $schema ) )
        {
            switch ( $schema )
            {
                case 'null':
                    return null;
                case 'boolean':
                    return $this->take( 1 ) !== "\x00";
                case 'int':
                case 'long':
                    return $this->long();
                case 'float':
                    return self::finite( unpack( 'g', $this->take( 4 ) )[1] );
                case 'double':
                    return self::finite( unpack( 'e', $this->take( 8 ) )[1] );
                case 'bytes':
                    return base64_encode( $this->take( $this->length() ) );
                case 'string':
                    return $this->take( $this->length() );
            }
            $schema = $this->lookup( $schema, $namespace );
        }
        if ( !is_array( $schema ) || !isset( $schema['type'] ) )
            throw new expImportSourceException( 'the Avro schema has a type it does not define' );
        if ( !isset( $schema['@namespace'] ) && isset( $schema['name'] ) && is_string( $schema['name'] )
             && in_array( $schema['type'], array( 'record', 'error', 'enum', 'fixed' ), true ) )
        {
            // an inline definition: its registered form knows its namespace
            $full = $schema['name'];
            if ( strpos( $full, '.' ) === false )
            {
                $ns = isset( $schema['namespace'] ) && is_string( $schema['namespace'] ) ? $schema['namespace'] : $namespace;
                $full = $ns !== '' ? $ns . '.' . $full : $full;
            }
            if ( isset( $this->named[$full] ) )
                $schema = $this->named[$full];
        }
        $type = $schema['type'];
        $logical = isset( $schema['logicalType'] ) ? (string)$schema['logicalType'] : '';
        if ( is_array( $type ) )
            return $this->read( $type, $namespace, $depth + 1 );
        if ( $logical !== '' && in_array( $type, array( 'int', 'long', 'bytes', 'fixed', 'string' ), true ) )
            return $this->logical( $schema, $logical, $type, $namespace, $depth );
        switch ( $type )
        {
            case 'record':
            case 'error':
                $out = array();
                $ns = isset( $schema['@namespace'] ) ? $schema['@namespace'] : $namespace;
                foreach ( (array)$schema['fields'] as $field )
                    $out[(string)$field['name']] = $this->read( $field['type'], $ns, $depth + 1 );
                return $out;
            case 'enum':
                $index = $this->long();
                if ( !isset( $schema['symbols'][$index] ) )
                    throw new expImportSourceException( 'an Avro enum has a symbol that is not in the schema' );
                return (string)$schema['symbols'][$index];
            case 'array':
                $out = array();
                while ( ( $count = $this->blockCount() ) > 0 )
                    for ( $i = 0; $i < $count; $i++ )
                        $out[] = $this->read( $schema['items'], $namespace, $depth + 1 );
                return $out;
            case 'map':
                $out = array();
                while ( ( $count = $this->blockCount() ) > 0 )
                    for ( $i = 0; $i < $count; $i++ )
                    {
                        $key = $this->take( $this->length() );
                        $out[$key] = $this->read( $schema['values'], $namespace, $depth + 1 );
                    }
                return $out;
            case 'fixed':
                return base64_encode( $this->take( max( 0, (int)$schema['size'] ) ) );
            default:
                return $this->read( $type, $namespace, $depth + 1 );
        }
    }

    /** @return mixed the value of a logical type */
    protected function logical( array $schema, $logical, $type, $namespace, $depth )
    {
        switch ( $type )
        {
            case 'int':
            case 'long':
                $n = $this->long();
                switch ( $logical )
                {
                    case 'date':
                        return gmdate( 'Y-m-d', $n * 86400 );
                    case 'timestamp-millis':
                    case 'local-timestamp-millis':
                        return self::timestamp( $n, 1000, 3, $logical === 'timestamp-millis' );
                    case 'timestamp-micros':
                    case 'local-timestamp-micros':
                        return self::timestamp( $n, 1000000, 6, $logical === 'timestamp-micros' );
                    case 'timestamp-nanos':
                    case 'local-timestamp-nanos':
                        return self::timestamp( $n, 1000000000, 9, $logical === 'timestamp-nanos' );
                    case 'time-millis':
                        return self::time( $n, 1000, 3 );
                    case 'time-micros':
                        return self::time( $n, 1000000, 6 );
                }
                return $n;
            case 'string':
                return $this->take( $this->length() );
            case 'bytes':
            case 'fixed':
                $data = $type === 'bytes' ? $this->take( $this->length() ) : $this->take( max( 0, (int)$schema['size'] ) );
                if ( $logical === 'decimal' )
                    return self::decimal( $data, isset( $schema['scale'] ) ? (int)$schema['scale'] : 0, true );
                if ( $logical === 'uuid' && strlen( $data ) === 16 )
                {
                    $hex = bin2hex( $data );
                    return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
                }
                return base64_encode( $data );
        }
        return null;
    }

    /**
     * A time since 1970 as ISO 8601.
     *
     * @param int $n the count of units
     * @param int $per units in a second
     * @param int $digits of the fraction
     * @param bool $utc with Z (an instant), else a local date and time
     * @return string
     */
    public static function timestamp( $n, $per, $digits, $utc )
    {
        $seconds = intdiv( (int)$n, (int)$per );
        $fraction = (int)$n % (int)$per;
        if ( $fraction < 0 )
        {
            $fraction += $per;
            $seconds--;
        }
        return gmdate( 'Y-m-d\TH:i:s', $seconds ) . ( $digits > 0 ? '.' . str_pad( (string)$fraction, $digits, '0', STR_PAD_LEFT ) : '' ) . ( $utc ? 'Z' : '' );
    }

    /** @return string a time of day as 10:11:12.345 */
    public static function time( $n, $per, $digits )
    {
        $seconds = intdiv( (int)$n, (int)$per );
        return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 )
             . ( $digits > 0 ? '.' . str_pad( (string)( (int)$n % (int)$per ), $digits, '0', STR_PAD_LEFT ) : '' );
    }

    /**
     * A two's complement integer of any length as a decimal number in text with its scale (12345, scale 2: 123.45).
     *
     * @param string $bytes
     * @param int $scale
     * @param bool $bigEndian Avro: big-endian; Arrow: little-endian
     * @return string
     */
    public static function decimal( $bytes, $scale, $bigEndian )
    {
        if ( !$bigEndian )
            $bytes = strrev( (string)$bytes );
        $bytes = (string)$bytes;
        if ( $bytes === '' )
            return '0';
        $negative = ord( $bytes[0] ) >= 128;
        $digits = array_values( unpack( 'C*', $bytes ) );
        if ( $negative )
        {
            // the magnitude: invert and add one
            $carry = 1;
            for ( $i = count( $digits ) - 1; $i >= 0; $i-- )
            {
                $v = ( ~$digits[$i] & 0xFF ) + $carry;
                $digits[$i] = $v & 0xFF;
                $carry = $v >> 8;
            }
        }
        // base 256 to base 10 by repeated division
        $text = '';
        while ( $digits && !( count( $digits ) === 1 && $digits[0] === 0 ) )
        {
            $rest = 0;
            $next = array();
            foreach ( $digits as $d )
            {
                $acc = $rest * 256 + $d;
                $q = intdiv( $acc, 10 );
                $rest = $acc % 10;
                if ( $next || $q > 0 )
                    $next[] = $q;
            }
            $text = $rest . $text;
            $digits = $next;
        }
        if ( $text === '' )
            $text = '0';
        if ( $scale > 0 )
        {
            $text = str_pad( $text, $scale + 1, '0', STR_PAD_LEFT );
            $text = substr( $text, 0, -$scale ) . '.' . substr( $text, -$scale );
        }
        return ( $negative && trim( $text, '0.' ) !== '' ? '-' : '' ) . $text;
    }

    /** @return float|string a number, or NaN, INF, -INF as text (JSON has none) */
    protected static function finite( $value )
    {
        return is_finite( $value ) ? $value : ( is_nan( $value ) ? 'NaN' : ( $value > 0 ? 'INF' : '-INF' ) );
    }

    /** @return string so many bytes of the block */
    protected function take( $n )
    {
        if ( $n < 0 || $this->pos + $n > strlen( $this->buf ) )
            throw new expImportSourceException( 'a record of the Avro file is cut off (the data does not fit the schema)' );
        $out = (string)substr( $this->buf, $this->pos, $n );
        $this->pos += $n;
        return $out;
    }

    /** @return int a length (of bytes or a string) */
    protected function length()
    {
        $n = $this->long();
        if ( $n < 0 )
            throw new expImportSourceException( 'a length in the Avro file is negative' );
        return $n;
    }

    /** @return int the count of a block of an array or map (its byte size, after a negative count, is skipped) */
    protected function blockCount()
    {
        $count = $this->long();
        if ( $count < 0 )
        {
            $count = -$count;
            $this->long();
        }
        return $count;
    }

    /** @return int a zig-zag varint of the block */
    protected function long()
    {
        $n = 0;
        $shift = 0;
        $length = strlen( $this->buf );
        do
        {
            if ( $this->pos >= $length || $shift > 63 )
                throw new expImportSourceException( 'a number of the Avro file is cut off' );
            $byte = ord( $this->buf[$this->pos++] );
            $n |= ( $byte & 0x7F ) << $shift;
            $shift += 7;
        }
        while ( $byte & 0x80 );
        return self::zigzag( $n );
    }

    /** @return int a zig-zag varint of the file */
    protected function streamLong()
    {
        $n = 0;
        $shift = 0;
        do
        {
            $char = fgetc( $this->fp );
            if ( $char === false || $shift > 63 )
                throw new expImportSourceException( 'the Avro file is cut off' );
            $byte = ord( $char );
            $n |= ( $byte & 0x7F ) << $shift;
            $shift += 7;
        }
        while ( $byte & 0x80 );
        return self::zigzag( $n );
    }

    /** @return string bytes of the file with their length before them */
    protected function streamBytes( $max )
    {
        $n = $this->streamLong();
        if ( $n < 0 || $n > $max )
            throw new expImportSourceException( 'the Avro header has a wrong length' );
        $data = $n > 0 ? (string)fread( $this->fp, $n ) : '';
        if ( strlen( $data ) !== $n )
            throw new expImportSourceException( 'the Avro header is cut off' );
        return $data;
    }

    /** @return int the value of a zig-zag encoded number (64 bits) */
    public static function zigzag( $n )
    {
        return ( ( $n >> 1 ) & PHP_INT_MAX ) ^ -( $n & 1 );
    }
}
