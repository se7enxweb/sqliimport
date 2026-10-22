<?php

use PHPUnit\Framework\TestCase;

/**
 * The target "A data table" (expImportDataTableHandler) on the kernel's expDataTableWriter, replaced here by a fake:
 * the columns (lines with transforms, or the fields named like columns), the writer's options, the outcome of each
 * record, the dry run; and the addresses of the catalogue's group "Data tables" (expImportDataTables).
 */
class DataTableTargetTest extends TestCase
{
    /** @var array */
    public static $written = array();

    /** @var array */
    public static $writerOptions = array();

    protected function setUp(): void
    {
        self::$written = array();
        expImportDataTableHandler::$catalogue = function ()
        {
            return array( 'shopArchiveRow' => array( 'class' => 'shopArchiveRow', 'table' => 'shop_archive', 'origin' => 'shop', 'error' => '',
                                                     'fields' => array( 'id' => array(), 'sku' => array(), 'title' => array(), 'price' => array(), 'node' => array() ) ) );
        };
        expImportDataTableHandler::$writerFactory = function ( $class, array $options )
        {
            DataTableTargetTest::$writerOptions = array( $class, $options );
            return new class {
                public function write( array $rows )
                {
                    DataTableTargetTest::$written[] = $rows[0];
                    $row = $rows[0];
                    $result = array( 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => array(), 'ids' => array() );
                    if ( isset( $row['sku'] ) && $row['sku'] === 'BAD' )
                    {
                        $result['failed'] = 1;
                        $result['errors'][] = 'row 1: price must be a number';
                    }
                    elseif ( isset( $row['sku'] ) && $row['sku'] === 'A-2' )
                    {
                        $result['updated'] = 1;
                        $result['ids'][0] = 7;
                    }
                    else
                    {
                        $result['inserted'] = 1;
                        $result['ids'][0] = 8;
                    }
                    return $result;
                }
            };
        };
    }

    protected function tearDown(): void
    {
        expImportDataTableHandler::$catalogue = null;
        expImportDataTableHandler::$writerFactory = null;
        expImportDataTables::$kernel = null;
    }

    private function context( array $options, $dryRun = false )
    {
        $context = ( new ReflectionClass( 'expImportContext' ) )->newInstanceWithoutConstructor();
        $context->options = $options;
        $context->dryRun = $dryRun;
        $context->target = array();
        return $context;
    }

    public function testColumnLinesWithTransforms(): void
    {
        $handler = new expImportDataTableHandler();
        $context = $this->context( array( 'class' => 'shopArchiveRow', 'mode' => 'upsert', 'keys' => array( 'sku' ),
                                          'columns' => "sku = id\ntitle = name | trim\n# a note\nprice = price | number" ) );
        $handler->open( $context );
        $this->assertSame( array( 'shopArchiveRow', array( 'mode' => 'upsert', 'dry_run' => false, 'chunk' => 1, 'unknown' => 'refuse', 'keys' => array( 'sku' ) ) ),
                           self::$writerOptions );
        $r = $handler->process( new expImportRecord( 'r1', 0, array( 'id' => 'A-1', 'name' => '  Green tea ', 'price' => '4,50' ) ), $context );
        $this->assertSame( expImportRowResult::CREATED, $r->outcome );
        $this->assertSame( '8', $r->remoteID );
        $this->assertSame( 'A-1', self::$written[0]['sku'] );
        $this->assertSame( 'Green tea', self::$written[0]['title'] );
        $this->assertEquals( 4.5, self::$written[0]['price'] );
        $r = $handler->process( new expImportRecord( 'r2', 1, array( 'id' => 'A-2', 'name' => 'Black', 'price' => '3' ) ), $context );
        $this->assertSame( expImportRowResult::UPDATED, $r->outcome );
        try
        {
            $handler->process( new expImportRecord( 'r4', 3, array( 'id' => 'C-4', 'name' => 'x', 'price' => 'x' ) ), $context );
            $this->fail( 'a transform that fails fails the record (the engine catches it per record)' );
        }
        catch ( expImportTransformException $e )
        {
            $this->assertStringContainsString( 'not a number', $e->getMessage() );
        }
        $r = $handler->process( new expImportRecord( 'r3', 2, array( 'id' => 'BAD', 'name' => 'x', 'price' => '1' ) ), $context );
        $this->assertSame( expImportRowResult::FAILED, $r->outcome );
        $this->assertStringContainsString( 'price must be a number', $r->message );
        $handler->close( $context );
    }

    public function testFieldsNamedLikeColumnsAndTheDryRun(): void
    {
        $handler = new expImportDataTableHandler();
        $context = $this->context( array( 'class' => 'shopArchiveRow', 'mode' => 'insert', 'keys' => array(), 'columns' => '' ), true );
        $handler->open( $context );
        $this->assertTrue( self::$writerOptions[1]['dry_run'] );
        $this->assertArrayNotHasKey( 'keys', self::$writerOptions[1], 'no keys: the class\'s own' );
        $r = $handler->process( new expImportRecord( 'r1', 0, array( 'sku' => 'C-3', 'title' => 'Oolong', 'colour' => 'amber', 'tags' => array( 'a', 'b' ) ) ), $context );
        $this->assertSame( array( 'sku' => 'C-3', 'title' => 'Oolong' ), self::$written[0], 'only fields that are columns' );
        $this->assertStringStartsWith( 'dry run: ', $r->message );
        $r = $handler->process( new expImportRecord( 'r2', 1, array( 'colour' => 'green' ) ), $context );
        $this->assertSame( expImportRowResult::SKIPPED, $r->outcome );
    }

    public function testRefusals(): void
    {
        foreach ( array( array( 'class' => 'noSuchClass' ), array( 'class' => '' ), array( 'class' => 'shopArchiveRow', 'columns' => 'colour = colour' ),
                         array( 'class' => 'shopArchiveRow', 'columns' => 'this is no line' ) ) as $options )
        {
            try
            {
                ( new expImportDataTableHandler() )->open( $this->context( $options + array( 'mode' => 'upsert', 'keys' => array(), 'columns' => '' ) ) );
                $this->fail( 'refused: ' . json_encode( $options ) );
            }
            catch ( expImportException $e )
            {
                $this->assertNotSame( '', $e->getMessage() );
            }
        }
        $schema = expImportDataTableHandler::schema();
        $this->assertSame( 'enum', $schema['class']['type'], 'a list of the data tables when the kernel has them' );
        $this->assertArrayHasKey( 'shopArchiveRow', $schema['class']['values'] );
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        $this->assertTrue( expImportDataTableHandler::available() );
    }

    public function testTheAddressesOfTheGroup(): void
    {
        $this->assertSame( 'setup/moduletable/(source)/sqliimport/(ref)/12', expImportDataTables::wizardUrl( 12 ) );
        $this->assertSame( 'setup/moduletable/(source)/sqliimport', expImportDataTables::wizardUrl() );
        $this->assertSame( 'datatable/sync/new/(class)/shopArchiveRow/(content_class)/product', expImportDataTables::syncUrl( 'shopArchiveRow', 'product' ) );
        $this->assertSame( 'datatable/sync/new', expImportDataTables::syncUrl( '../x', 'Bad Class' ), 'only names' );
        expImportDataTables::$kernel = array( 'writer' => false, 'wizard' => false, 'sync' => false );
        $this->assertFalse( expImportDataTables::available(), 'nothing on an older kernel' );
        expImportDataTables::$kernel = array( 'sync' => true );
        $this->assertTrue( expImportDataTables::available() );
        $services = (string)file_get_contents( dirname( __DIR__, 2 ) . '/settings/kernelservices.ini.append.php' );
        $this->assertStringContainsString( "[Service_sqliimport.handler.datatable]\nClass=expImportDataTableHandler\nScope=worker\nTags[]=sqliimport.handler", $services );
        $this->assertStringContainsString( "RoundTrip[]=transfer.datatable", $services );
    }
}
