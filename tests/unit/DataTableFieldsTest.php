<?php

use PHPUnit\Framework\TestCase;

/**
 * The fields of a new data table guessed from a source's sample (the kernel's "Module table" wizard, Exponential
 * 6.0.15): column names, types, lengths, required, unique and a sample value.
 */
class DataTableFieldsTest extends TestCase
{
    public function testTypesAndFlags(): void
    {
        $records = array(
            array( 'SKU' => 'A-1', 'Name' => 'Green tea', 'price' => '4.50', 'stock' => '12', 'active' => 'yes', 'released' => '2026-01-31',
                   'updatedAt' => '2026-01-31T10:00:00Z', 'tags' => array( 'tea', 'green' ), 'notes' => '', 'id' => '7', 'Größe' => 'S' ),
            array( 'SKU' => 'A-2', 'Name' => 'Black tea', 'price' => '5', 'stock' => '0', 'active' => 'no', 'released' => '2026-02-01',
                   'updatedAt' => '2026-02-01 08:30', 'tags' => array(), 'notes' => "line one\nline two", 'id' => '8', 'Größe' => 'M' ),
            array( 'SKU' => 'A-3', 'Name' => 'Green tea', 'price' => '-1.25', 'stock' => '3', 'active' => 'true', 'released' => '2026-02-29x',
                   'updatedAt' => '2026-02-02T08:30:00+01:00', 'notes' => null, 'id' => '9', 'Größe' => 'L' ),
        );
        $fields = array();
        foreach ( expImportDataTableFields::infer( $records ) as $f )
            $fields[$f['label']] = $f;
        $this->assertSame( array( 'SKU', 'Name', 'price', 'stock', 'active', 'released', 'updatedAt', 'tags', 'notes', 'id', 'Größe' ), array_keys( $fields ) );
        $this->assertSame( array( 'sku', 'string', 32, true, true, 'A-1' ),
                           array( $fields['SKU']['name'], $fields['SKU']['type'], $fields['SKU']['length'], $fields['SKU']['required'], $fields['SKU']['unique'], $fields['SKU']['sample'] ) );
        $this->assertFalse( $fields['Name']['unique'], 'a value repeats' );
        $this->assertSame( 'float', $fields['price']['type'] );
        $this->assertSame( 'integer', $fields['stock']['type'] );
        $this->assertSame( 0, $fields['stock']['length'] );
        $this->assertSame( 'boolean', $fields['active']['type'] );
        $this->assertSame( 'string', $fields['released']['type'], 'one value is no date' );
        $this->assertSame( 'datetime', $fields['updatedAt']['type'] );
        $this->assertSame( 'updated_at', $fields['updatedAt']['name'] );
        $this->assertSame( 'json', $fields['tags']['type'] );
        $this->assertFalse( $fields['tags']['required'], 'missing in one record, empty in another' );
        $this->assertSame( 'text', $fields['notes']['type'], 'a value with a line break' );
        $this->assertSame( 'source_id', $fields['id']['name'], 'the generated class has its own id' );
        $this->assertMatchesRegularExpression( '/^gr[a-z0-9_]*$/', $fields['Größe']['name'], 'transliterated to ASCII where iconv can' );
    }

    public function testColumnNames(): void
    {
        $used = array();
        $this->assertSame( 'title', expImportDataTableFields::columnName( 'Title', $used ) );
        $this->assertSame( 'title_2', expImportDataTableFields::columnName( 'title', $used ) );
        $this->assertSame( 'author_name', expImportDataTableFields::columnName( 'author.name', $used ) );
        $this->assertSame( 'f_2026_sales', expImportDataTableFields::columnName( '2026 sales', $used ) );
        $this->assertSame( 'field', expImportDataTableFields::columnName( '***', $used ) );
        $this->assertSame( 'source_select', expImportDataTableFields::columnName( 'SELECT', $used ) );
        $long = expImportDataTableFields::columnName( str_repeat( 'a', 80 ), $used );
        $this->assertSame( 60, strlen( $long ) );
        $this->assertSame( 60, strlen( expImportDataTableFields::columnName( str_repeat( 'a', 80 ), $used ) ) );
        foreach ( array_keys( $used ) as $name )
            $this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]{0,59}$/', $name );
    }

    public function testTypeEdges(): void
    {
        $this->assertSame( 'string', expImportDataTableFields::typeOf( array( null, '', '  ' ) ), 'nothing to go by' );
        $this->assertSame( 'integer', expImportDataTableFields::typeOf( array( 1, '2', '-3' ) ) );
        $this->assertSame( 'string', expImportDataTableFields::typeOf( array( '007' ) ), 'a leading zero is a code, not a number' );
        $this->assertSame( 'float', expImportDataTableFields::typeOf( array( 1, 2.5, '1e3' ) ) );
        $this->assertSame( 'boolean', expImportDataTableFields::typeOf( array( true, 'False' ) ) );
        $this->assertSame( 'date', expImportDataTableFields::typeOf( array( '2024-02-29' ) ) );
        $this->assertSame( 'string', expImportDataTableFields::typeOf( array( '2023-02-29' ) ), 'no such day' );
        $this->assertSame( 'json', expImportDataTableFields::typeOf( array( '{"a":1}', '[1,2]' ) ) );
        $this->assertSame( 'text', expImportDataTableFields::typeOf( array( str_repeat( 'x', 256 ) ) ) );
        $this->assertSame( 255, expImportDataTableFields::length( 200 ) );
        $this->assertSame( 64, expImportDataTableFields::length( 33 ) );
    }

    /** The provider implements the kernel's interface and is registered only on a kernel that has it */
    public function testTheProviderIsGuarded(): void
    {
        $root = dirname( __DIR__, 2 );
        $file = (string)file_get_contents( $root . '/classes/exp/expimportdatatablefieldsource.php' );
        $this->assertStringContainsString( "if ( interface_exists( 'expDataTableFieldSource' ) )", $file );
        $this->assertStringContainsString( 'class expImportDataTableFieldSource extends expImportDataTableFieldSourceBase implements expDataTableFieldSource', $file );
        $this->assertStringContainsString( "[Service_datatable.field_source.sqliimport]\nClass=expImportDataTableFieldSource\nScope=worker\nTags[]=datatable.field_source",
                                           (string)file_get_contents( $root . '/settings/kernelservices.ini.append.php' ) );
        // it loads on a kernel with or without the interface, and has the methods the wizard calls
        $this->assertTrue( class_exists( 'expImportDataTableFieldSource' ) );
        $this->assertSame( interface_exists( 'expDataTableFieldSource' ), is_subclass_of( 'expImportDataTableFieldSource', 'expDataTableFieldSource' ) );
        foreach ( array( 'dataTableSourceName', 'dataTableReferences', 'dataTableFields', 'dataTableSourceLabel' ) as $method )
            $this->assertTrue( method_exists( 'expImportDataTableFieldSource', $method ), $method );
    }

    /**
     * The provider against the kernel's REAL interface (kernel/classes/datatable/expdatatablefieldsource.php of the
     * installation, or of SQ688_KERNEL_ROOT): every method the interface declares is there with a signature PHP
     * accepts, so a change of the contract fails here and not as a fatal error of the wizard.
     */
    public function testTheProviderFitsTheKernelInterface(): void
    {
        $roots = array_filter( array( (string)getenv( 'SQ688_KERNEL_ROOT' ), dirname( __DIR__, 4 ) ) );
        $file = '';
        foreach ( $roots as $root )
            if ( is_file( $root . '/kernel/classes/datatable/expdatatablefieldsource.php' ) )
            {
                $file = $root . '/kernel/classes/datatable/expdatatablefieldsource.php';
                break;
            }
        if ( $file === '' )
            $this->markTestSkipped( 'no kernel with the data tables here (set SQ688_KERNEL_ROOT to an Exponential 6.0.15 installation)' );
        $code = (string)file_get_contents( $file );
        $this->assertSame( 1, preg_match( '/interface\s+expDataTableFieldSource\s*\{(.*)\}/s', $code, $m ), 'the kernel declares the interface' );
        preg_match_all( '/public\s+function\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)/', $m[1], $methods, PREG_SET_ORDER );
        $this->assertNotEmpty( $methods );
        foreach ( $methods as $method )
        {
            $this->assertTrue( method_exists( 'expImportDataTableFieldSourceBase', $method[1] ), "the provider has $method[1]()" );
            $reflection = new ReflectionMethod( 'expImportDataTableFieldSourceBase', $method[1] );
            $kernelParams = trim( $method[2] ) === '' ? 0 : substr_count( $method[2], ',' ) + 1;
            $this->assertSame( $kernelParams, $reflection->getNumberOfParameters(), "$method[1]() takes the kernel's parameters" );
            $this->assertTrue( $reflection->isPublic() && !$reflection->isStatic(), "$method[1]() is a public instance method" );
        }
    }
}
