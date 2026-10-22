<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AvroArrowTestWriters.php';

/**
 * The Avro and Arrow sources and their readers: the examples, every Avro type and logical type, codecs, damaged
 * files, the cursor of a chunk; the Arrow file and stream formats, its types, nulls, dictionaries, lists and structs,
 * LZ4 compression, refused types and Feather version 1, and the drivers of both.
 */
class AvroArrowSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-avroarrowtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
    }

    protected function tearDown(): void
    {
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
    }

    /** @return array[] the data of the records of a source over a file */
    protected function read( string $class, string $bytes, array $options = array(), array $cursor = array() ): array
    {
        $file = $this->dir . '/data.bin';
        file_put_contents( $file, $bytes );
        $source = new $class( $options + array( 'file' => $file ) );
        $out = array();
        foreach ( $source->records( $cursor ) as $record )
            $out[] = $record;
        return $out;
    }

    public function testTheExamples(): void
    {
        foreach ( array( 'expImportAvroSource', 'expImportArrowSource' ) as $class )
        {
            $this->assertSame( 'data', call_user_func( array( $class, 'group' ) ) );
            $this->assertSame( array(), expImportOptionSchema::check( call_user_func( array( $class, 'schema' ) ) ) );
            $example = call_user_func( array( $class, 'examples' ) )[0];
            $source = new $class( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'], 'key' => 'id' ) );
            $records = iterator_to_array( $source->records(), false );
            $this->assertCount( 5, $records, $class );
            $first = $records[0]->data;
            $this->assertSame( '1', $records[0]->ref );
            $this->assertSame( array( 'Sencha Asamushi', 'Japan', 'Shizuoka', 'green', '["steamed","grassy"]', '12.50', '2026-05-02', '2026-09-14T08:30:00.000Z', true, 4.5 ),
                               array( $first['name'], $first['origin.country'], $first['origin.region'], $first['kind'], $first['tags'], $first['price'],
                                      $first['harvested'], $first['updated'], $first['organic'], $first['rating'] ), $class );
            $this->assertNull( $records[2]->data['origin.region'] );
            $this->assertNull( $records[2]->data['rating'] );
            // a chunk goes on from the cursor of the record before it
            $rest = iterator_to_array( $source->records( $records[2]->cursor ), false );
            $this->assertSame( array( '4', '5' ), array_map( function ( $r ) { return $r->ref; }, $rest ), $class );
            $this->assertSame( 3, $rest[0]->index );
            $rest = iterator_to_array( $source->records( $records[1]->cursor ), false );
            $this->assertSame( array( '3', '4', '5' ), array_map( function ( $r ) { return $r->ref; }, $rest ), $class );
            $joined = new $class( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'], 'arrays' => 'join', 'separator' => '|' ) );
            $this->assertSame( 'steamed|grassy', iterator_to_array( $joined->records(), false )[0]->data['tags'] );
        }
        $this->assertSame( array( 'avro' ), expImportAvroSource::extensions() );
        $this->assertSame( array( 'arrow', 'feather', 'arrows', 'ipc' ), expImportArrowSource::extensions() );
    }

    public function testAvroVarintsOfTheSpecification(): void
    {
        // the examples of the Avro specification: 0 -> 00, -1 -> 01, 1 -> 02, -64 -> 7f, 64 -> 80 01
        foreach ( array( 0 => "\x00", -1 => "\x01", 1 => "\x02", -64 => "\x7f", 64 => "\x80\x01" ) as $n => $bytes )
            $this->assertSame( $bytes, AvroTestWriter::long( $n ) );
        $this->assertSame( PHP_INT_MAX, expImportAvro::zigzag( -2 ) );
        $this->assertSame( PHP_INT_MIN, expImportAvro::zigzag( -1 ) );
        foreach ( array( PHP_INT_MAX, PHP_INT_MIN, -123456789012, 300 ) as $n )
        {
            $writer = new AvroTestWriter();
            $records = $this->read( 'expImportAvroSource', $writer->file( 'long', array( $n ) ) );
            $this->assertSame( $n, $records[0]->data['value'] );
        }
    }

    public function testEveryAvroType(): void
    {
        $schema = array( 'type' => 'record', 'name' => 'Node', 'namespace' => 'example.test', 'fields' => array(
            array( 'name' => 'flag', 'type' => 'boolean' ),
            array( 'name' => 'small', 'type' => 'int' ),
            array( 'name' => 'f', 'type' => 'float' ),
            array( 'name' => 'd', 'type' => 'double' ),
            array( 'name' => 'raw', 'type' => 'bytes' ),
            array( 'name' => 'hash', 'type' => array( 'type' => 'fixed', 'name' => 'Hash', 'size' => 4 ) ),
            array( 'name' => 'again', 'type' => 'Hash' ),
            array( 'name' => 'color', 'type' => array( 'type' => 'enum', 'name' => 'Color', 'symbols' => array( 'RED', 'GREEN' ) ) ),
            array( 'name' => 'props', 'type' => array( 'type' => 'map', 'values' => 'long' ) ),
            array( 'name' => 'list', 'type' => array( 'type' => 'array', 'items' => 'string' ) ),
            array( 'name' => 'choice', 'type' => array( 'null', 'string', 'long' ) ),
            array( 'name' => 'child', 'type' => array( 'null', 'example.test.Node' ) ),
            array( 'name' => 'amount', 'type' => array( 'type' => 'fixed', 'name' => 'Amount', 'size' => 8, 'logicalType' => 'decimal', 'precision' => 12, 'scale' => 3 ) ),
            array( 'name' => 'debt', 'type' => array( 'type' => 'bytes', 'logicalType' => 'decimal', 'precision' => 30, 'scale' => 2 ) ),
            array( 'name' => 'at_us', 'type' => array( 'type' => 'long', 'logicalType' => 'timestamp-micros' ) ),
            array( 'name' => 'local', 'type' => array( 'type' => 'long', 'logicalType' => 'local-timestamp-millis' ) ),
            array( 'name' => 'time', 'type' => array( 'type' => 'int', 'logicalType' => 'time-millis' ) ),
            array( 'name' => 'id', 'type' => array( 'type' => 'string', 'logicalType' => 'uuid' ) ),
        ) );
        $leaf = array( 'flag' => false, 'small' => -5, 'f' => 1.5, 'd' => -0.25, 'raw' => "\x00\xFF", 'hash' => 'abcd', 'again' => 'wxyz', 'color' => 'RED',
                       'props' => array(), 'list' => array(), 'choice' => null, 'child' => null, 'amount' => 0, 'debt' => -1, 'at_us' => -1, 'local' => 0, 'time' => 0,
                       'id' => '00000000-0000-4000-8000-000000000000' );
        $root = array( 'flag' => true, 'small' => 2147483647, 'f' => 0.5, 'd' => 3.25, 'raw' => 'hi', 'hash' => "\x01\x02\x03\x04", 'again' => 'abcd', 'color' => 'GREEN',
                       'props' => array( 'a' => 1, 'b' => -2 ), 'list' => array( 'x', 'y' ), 'choice' => 'text', 'child' => $leaf, 'amount' => 1234567, 'debt' => -1234567890123,
                       'at_us' => 1790000000123456, 'local' => 1790000000000, 'time' => 37230500, 'id' => '6f9619ff-8b86-d011-b42d-00cf4fc964ff' );
        foreach ( array( false, true ) as $negative )
            foreach ( array( 'null', 'deflate' ) as $codec )
            {
                $writer = new AvroTestWriter();
                $writer->negativeBlocks = $negative;
                $records = $this->read( 'expImportAvroSource', $writer->file( $schema, array( $root, $leaf ), $codec, 1 ) );
                $this->assertCount( 2, $records );
                $a = $records[0]->data;
                $this->assertSame( array( true, 2147483647, 0.5, 3.25, base64_encode( 'hi' ), base64_encode( "\x01\x02\x03\x04" ), base64_encode( 'abcd' ), 'GREEN' ),
                                   array( $a['flag'], $a['small'], $a['f'], $a['d'], $a['raw'], $a['hash'], $a['again'], $a['color'] ) );
                $this->assertSame( array( 1, -2 ), array( $a['props.a'], $a['props.b'] ), 'a map is flattened like a record' );
                $this->assertSame( '["x","y"]', $a['list'] );
                $this->assertSame( 'text', $a['choice'] );
                $this->assertSame( '1234.567', $a['amount'] );
                $this->assertSame( '-12345678901.23', $a['debt'] );
                $this->assertSame( '2026-09-21T14:13:20.123456Z', $a['at_us'] );
                $this->assertSame( '2026-09-21T14:13:20.000', $a['local'], 'a local timestamp has no Z' );
                $this->assertSame( '10:20:30.500', $a['time'] );
                $this->assertSame( '6f9619ff-8b86-d011-b42d-00cf4fc964ff', $a['id'] );
                $this->assertSame( 'RED', $a['child.color'], 'a recursive record by its name' );
                $this->assertSame( '-0.01', $a['child.debt'] );
                $this->assertSame( '1969-12-31T23:59:59.999999Z', $a['child.at_us'] );
                $this->assertSame( '0.000', $records[1]->data['amount'] );
                $this->assertNull( $records[1]->data['child'] );
            }
        // a list at the top
        $records = $this->read( 'expImportAvroSource', ( new AvroTestWriter() )->file( array( 'type' => 'array', 'items' => 'int' ), array( array( 1, 2 ) ) ) );
        $this->assertSame( array( 'value' => '[1,2]' ), $records[0]->data );
        // before 1970, and decimals of every size
        $this->assertSame( '1969-12-31T00:00:00', expImportAvro::timestamp( -86400, 1, 0, false ) );
        $this->assertSame( '-0.50', expImportAvro::decimal( "\xCE", 2, true ) );
        $this->assertSame( '170141183460469231731687303715884105727', expImportAvro::decimal( "\x7F" . str_repeat( "\xFF", 15 ), 0, true ) );
        $this->assertSame( '-170141183460469231731687303715884105728', expImportAvro::decimal( "\x80" . str_repeat( "\x00", 15 ), 0, true ) );
    }

    public function testAvroCodecsAndDamagedFiles(): void
    {
        $writer = new AvroTestWriter();
        $good = $writer->file( 'string', array( 'a', 'b', 'c' ), 'null', 2 );
        foreach ( array( 'snappy' => 'snappy_uncompress', 'zstandard' => 'zstd_uncompress' ) as $codec => $function )
        {
            if ( function_exists( $function ) )
                continue;
            try
            {
                $this->read( 'expImportAvroSource', $writer->file( 'string', array( 'a' ), $codec ) );
                $this->fail( $codec );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( "codec $codec needs the PHP extension", $e->getMessage() );
            }
        }
        $cases = array(
            'not an Avro' => 'PK' . substr( $good, 2 ),
            'cannot be read (only null' => $writer->file( 'string', array( 'a' ), 'lzma' ),
            'sync marker' => substr( $good, 0, -1 ) . chr( ord( substr( $good, -1 ) ) ^ 1 ),
            'cut off in a block' => substr( $good, 0, -18 ),
            'no schema' => "Obj\x01" . AvroTestWriter::long( 1 ) . AvroTestWriter::bytes( 'avro.codec' ) . AvroTestWriter::bytes( 'null' ) . AvroTestWriter::long( 0 ) . str_repeat( 'x', 16 ),
            'unknown type' => str_replace( '"type":"string"', '"type":"Mising"', $writer->file( array( 'type' => 'record', 'name' => 'R',
                                                                                                    'fields' => array( array( 'name' => 'a', 'type' => 'string' ) ) ), array( array( 'a' => 'x' ) ) ) ),
        );
        foreach ( $cases as $message => $bytes )
        {
            try
            {
                $this->read( 'expImportAvroSource', $bytes );
                $this->fail( $message );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( $message, $e->getMessage() );
            }
        }
        $this->assertCount( 3, $this->read( 'expImportAvroSource', $good ) );
    }

    /** @return array[] the columns of an Arrow test file */
    protected function columns(): array
    {
        return array(
            array( 'name' => 'i8', 'type' => 'int', 'bits' => 8, 'signed' => true, 'values' => array( -128, 127, null, 5 ) ),
            array( 'name' => 'u16', 'type' => 'int', 'bits' => 16, 'signed' => false, 'values' => array( 65535, 0, 1, 2 ) ),
            array( 'name' => 'i32', 'type' => 'int', 'bits' => 32, 'signed' => true, 'values' => array( -2147483648, 2147483647, 0, -1 ) ),
            array( 'name' => 'u64', 'type' => 'int', 'bits' => 64, 'signed' => false, 'values' => array( -1, 1, 2, 3 ) ),
            array( 'name' => 'f32', 'type' => 'float', 'precision' => 1, 'values' => array( 1.5, -0.25, null, 2.0 ) ),
            array( 'name' => 'f64', 'type' => 'float', 'precision' => 2, 'values' => array( NAN, INF, 0.1, -3.5 ) ),
            array( 'name' => 'big', 'type' => 'large_utf8', 'values' => array( 'große', '', null, 'z' ) ),
            array( 'name' => 'bin', 'type' => 'binary', 'values' => array( "\x00\x01", 'ab', null, '' ) ),
            array( 'name' => 'nothing', 'type' => 'null', 'values' => array( null, null, null, null ) ),
            array( 'name' => 'ts', 'type' => 'timestamp', 'unit' => 2, 'tz' => '', 'values' => array( 1790000000000001, 0, null, -1 ) ),
            array( 'name' => 'secs', 'type' => 'timestamp', 'unit' => 0, 'tz' => 'Europe/Berlin', 'values' => array( 1790000000, 0, 1, 2 ) ),
            array( 'name' => 'neg', 'type' => 'decimal', 'precision' => 10, 'scale' => 3, 'values' => array( -1500, 1, null, 0 ) ),
            array( 'name' => 'kind', 'type' => 'dict', 'id' => 7, 'dictionary' => array( 'green', 'black' ), 'values' => array( 1, 0, null, 1 ) ),
            array( 'name' => 'tags', 'type' => 'list', 'values' => array( 2, 0, null, 1 ),
                   'child' => array( 'name' => 'item', 'type' => 'int', 'bits' => 32, 'signed' => true, 'values' => array( 10, 20, 30 ) ) ),
            array( 'name' => 'st', 'type' => 'struct', 'values' => array( 1, null, 1, 1 ), 'children' => array(
                array( 'name' => 'a', 'type' => 'utf8', 'values' => array( 'x', 'y', null, 'w' ) ),
                array( 'name' => 'b', 'type' => 'bool', 'values' => array( true, false, true, false ) ) ) ),
        );
    }

    public function testArrowTypesNullsAndFormats(): void
    {
        foreach ( array( array( 'none', true ), array( 'lz4', true ), array( 'lz4literal', false ) ) as list( $compression, $file ) )
        {
            $writer = new ArrowTestWriter();
            $writer->compression = $compression;
            $records = $this->read( 'expImportArrowSource', $writer->write( $this->columns(), array( 1, 3 ), $file ) );
            $this->assertCount( 4, $records, $compression );
            $a = $records[0]->data;
            $this->assertSame( array( -128, 65535, -2147483648, '18446744073709551615', 1.5, 'NaN', 'große', base64_encode( "\x00\x01" ), null ),
                               array( $a['i8'], $a['u16'], $a['i32'], $a['u64'], $a['f32'], $a['f64'], $a['big'], $a['bin'], $a['nothing'] ), $compression );
            $this->assertSame( array( '2026-09-21T14:13:20.000001', '2026-09-21T14:13:20Z', '-1.500', 'black', '[10,20]', 'x', true ),
                               array( $a['ts'], $a['secs'], $a['neg'], $a['kind'], $a['tags'], $a['st.a'], $a['st.b'] ), $compression );
            $b = $records[1]->data;
            $this->assertSame( array( 127, 'INF', '', 'green', '[]', null ), array( $b['i8'], $b['f64'], $b['big'], $b['kind'], $b['tags'], $b['st'] ) );
            $c = $records[2]->data;
            foreach ( array( 'i8', 'f32', 'big', 'bin', 'ts', 'neg', 'kind', 'tags', 'st.a' ) as $name )
                $this->assertNull( $c[$name], "$compression $name" );
            $this->assertTrue( $c['st.b'] );
            $d = $records[3]->data;
            $this->assertSame( array( '1969-12-31T23:59:59.999999', '0.000', '[30]', 'w' ), array( $d['ts'], $d['neg'], $d['tags'], $d['st.a'] ) );
            // the cursor of a chunk inside the second batch
            $rest = $this->read( 'expImportArrowSource', $writer->write( $this->columns(), array( 1, 3 ), $file ), array(), $records[1]->cursor );
            $this->assertCount( 2, $rest );
            $this->assertSame( -1, $rest[1]->data['i32'] );
        }
        $this->assertSame( 'stream', ( new expImportArrow( $this->dir . '/data.bin' ) )->format );
    }

    public function testLz4AndRefusals(): void
    {
        // literals "abc", a match of 9 bytes at offset 3 (overlapping), the last literal "x"
        $this->assertSame( 'abcabcabcabcx', expImportArrow::lz4Block( "\x35abc\x03\x00\x10x" ) );
        $this->assertSame( str_repeat( 'ab', 20 ), expImportArrow::lz4Block( "\x2Fab\x02\x00\x13" ), 'a match length with an extra byte' );
        try
        {
            expImportArrow::lz4Frame( 'not a frame' );
            $this->fail( 'not a frame' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'LZ4', $e->getMessage() );
        }
        $this->assertSame( array( 1.5, -2.0, 'INF', 65504.0 ), expImportArrow::floats( pack( 'v*', 0x3E00, 0xC000, 0x7C00, 0x7BFF ), 0, 4 ), 'half floats' );
        $cases = array(
            'Feather file of version 1' => 'FEA1' . str_repeat( "\0", 40 ),
            'does not begin with a schema' => "\xFF\xFF\xFF\xFF\0\0\0\0",
            'has no footer' => "ARROW1\0\0abc",
            'Arrow type Union' => ( new ArrowTestWriter() )->write( array( array( 'name' => 'u', 'type' => 'union', 'values' => array( 1 ) ) ), array( 1 ) ),
        );
        if ( !function_exists( 'zstd_uncompress' ) )
            $this->assertStringContainsString( 'zstd', $this->exceptionOf( function () { expImportArrow::zstd( 'x' ); } ) );
        foreach ( $cases as $message => $bytes )
        {
            try
            {
                $this->read( 'expImportArrowSource', $bytes );
                $this->fail( $message );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( $message, $e->getMessage() );
            }
        }
    }

    /** @return string the message of the exception a function throws */
    protected function exceptionOf( callable $code ): string
    {
        try
        {
            $code();
        }
        catch ( expImportSourceException $e )
        {
            return $e->getMessage();
        }
        return '';
    }

    public function testDriversAndTheWorkers(): void
    {
        foreach ( array( 'expImportAvroSource' => array( 'null', 'deflate', 'snappy', 'zstandard', 'bzip2' ), 'expImportArrowSource' => array( 'none', 'lz4', 'zstd' ) ) as $class => $ids )
        {
            $drivers = call_user_func( array( $class, 'drivers' ) );
            $this->assertSame( $ids, array_keys( $drivers ) );
            foreach ( $drivers as $row )
                foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                    $this->assertArrayHasKey( $key, $row );
            $this->assertTrue( $drivers[$ids[0]]['available'] );
            $this->assertSame( '', $drivers[$ids[0]]['worker'], 'nothing known of the workers yet' );
        }
        expImportSqlSource::recordWorkerPhp();
        $this->assertSame( 'yes', expImportAvroSource::drivers()['deflate']['worker'] );
        $this->assertSame( 'yes', expImportArrowSource::drivers()['lz4']['worker'] );
    }
}
