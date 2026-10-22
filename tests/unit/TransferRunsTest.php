<?php

use PHPUnit\Framework\TestCase;

/**
 * Imports in the run history of the import and export tools: without the kernel's history nothing changes, the
 * states of the kernel rows, and the figures per tool on the Statistics page.
 */
class TransferRunsTest extends TestCase
{
    public function testWithoutTheKernelHistory(): void
    {
        if ( class_exists( 'expKernelTransferHistory' ) )
            $this->markTestSkipped( 'the kernel history is loaded' );
        $this->assertFalse( expImportTransferRuns::available() );
        $this->assertSame( array(), expImportTransferRuns::toolOptions() );
        $this->assertSame( array(), expImportTransferRuns::otherTools( 'all' ), 'no other tool to read' );
        $this->assertTrue( expImportTransferRuns::includesImports( 'transfer.xrowextract' ), 'without the history the page stays the one of Imports' );
        $this->assertSame( array(), expImportTransferRuns::listRuns( array( 'transfer.xrowextract' ) ) );
    }

    public function testStates(): void
    {
        $cases = array( 'queued' => 'queued', 'running' => 'running', 'completed' => 'done', 'completed_with_errors' => 'done', 'failed' => 'failed',
                        'cancelled' => 'cancelled' );
        foreach ( $cases as $status => $state )
            $this->assertSame( $state, expImportTransferRuns::stateOf( $status ), $status );
        $this->assertTrue( expImportTransferRuns::includesImports( '' ) );
        $this->assertTrue( expImportTransferRuns::includesImports( 'all' ) );
        $this->assertTrue( expImportTransferRuns::includesImports( 'transfer.sqliimport' ) );
    }

    public function testPerTool(): void
    {
        $runs = array(
            expImportStatistics::normalizeRun( array( 'id' => 1, 'tool' => 'transfer.xrowextract', 'run_kind' => 'export', 'status' => 'completed', 'started_at' => 100,
                                                      'finished_at' => 160, 'processed_count' => 500 ) ),
            expImportStatistics::normalizeRun( array( 'id' => 2, 'run_kind' => 'import', 'status' => 'completed_with_errors', 'started_at' => 200, 'finished_at' => 210,
                                                      'created_count' => 9, 'failed_count' => 1 ) ),
            expImportStatistics::normalizeRun( array( 'id' => 3, 'tool' => 'transfer.xrowextract', 'run_kind' => 'export', 'status' => 'failed', 'started_at' => 300,
                                                      'finished_at' => 330, 'processed_count' => 0, 'failed_count' => 2 ) ),
        );
        $this->assertSame( 'transfer.sqliimport', $runs[1]['tool'], 'a run of Imports names its tool' );
        $per = expImportStatistics::perTool( $runs, array( 'transfer.xrowextract' => 'Export (xrowextract)' ) );
        $this->assertSame( array( 'transfer.sqliimport', 'transfer.xrowextract' ), array_map( function ( $t ) { return $t['tool']; }, $per ), 'Imports first' );
        $this->assertSame( 'Export (xrowextract)', $per[1]['name'] );
        $this->assertSame( 2, $per[1]['summary']['runs'] );
        $this->assertContains( 'export', expImportStatistics::SCOPES['production'], 'an export is a production run' );
    }
}
