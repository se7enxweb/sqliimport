<?php

use PHPUnit\Framework\TestCase;

/**
 * The CSV report of a run: RFC 4180 lines and cells that a spreadsheet does not read as formulas.
 */
class ReportTest extends TestCase
{
    public function testLinesAreQuotedOnlyWhereNeeded(): void
    {
        $this->assertSame( "a,b,c\r\n", expImportReport::line( array( 'a', 'b', 'c' ) ) );
        $this->assertSame( "\"a,b\",\"say \"\"hi\"\"\",\"two\nlines\"\r\n", expImportReport::line( array( 'a,b', 'say "hi"', "two\nlines" ) ) );
        $this->assertSame( "1,,x\r\n", expImportReport::line( array( 1, '', 'x' ) ) );
    }

    public function testFormulasAreDefused(): void
    {
        $this->assertSame( "'=HYPERLINK(\"x\")", expImportReport::cell( '=HYPERLINK("x")' ) );
        $this->assertSame( "'+1+2", expImportReport::cell( '+1+2' ) );
        $this->assertSame( "'@SUM(A1)", expImportReport::cell( '@SUM(A1)' ) );
        $this->assertSame( '-12.5', expImportReport::cell( '-12.5' ), 'a negative number stays a number' );
        $this->assertSame( "'-x", expImportReport::cell( '-x' ) );
    }

    public function testRowCells(): void
    {
        $cells = expImportReport::rowCells( array( 'run_id' => 7, 'row_index' => 3, 'row_ref' => 'A-1', 'outcome' => 'updated', 'status' => 'done',
            'object_id' => 42, 'remote_id' => 'sq-A-1', 'version_before' => 2, 'version_after' => 3,
            'nodes_json' => '{"diff":{"title":["a","b"],"price":["1","2"]}}', 'message' => '', 'created' => 1767225600 ) );
        $this->assertSame( array( 7, 3, 'A-1', 'updated', 'done', 42, '', 'sq-A-1', 2, 3, 'title price', '', '2026-01-01T00:00:00Z' ), $cells );
        $this->assertSame( 99, expImportReport::rowCells( array( 'run_id' => 7, 'row_index' => 0, 'row_ref' => 'x', 'outcome' => 'created', 'status' => 'ok', 'object_id' => 5,
            'remote_id' => '', 'version_before' => 0, 'version_after' => 1, 'nodes_json' => '{"added":[99,100]}', 'message' => '', 'created' => 0 ) )[6], 'the node a create run added' );
        $this->assertCount( count( expImportReport::columns() ), $cells );
    }
}
