<?php

use PHPUnit\Framework\TestCase;

/**
 * When the end of a run is told, by the import's setting.
 */
class NotifierTest extends TestCase
{
    public function testWhen(): void
    {
        $cases = array(
            array( 'errors', 'failed', true ), array( 'errors', 'completed_with_errors', true ), array( 'errors', 'completed', false ),
            array( 'failed', 'failed', true ), array( 'failed', 'completed_with_errors', false ),
            array( 'always', 'completed', true ), array( 'always', 'cancelled', true ), array( 'always', 'running', false ),
            array( 'never', 'failed', false ), array( 'unknown', 'failed', true ),
        );
        foreach ( $cases as $c )
            $this->assertSame( $c[2], expImportNotifier::shouldNotify( $c[0], $c[1] ), $c[0] . ' / ' . $c[1] );
        $this->assertSame( 'errors', expImportNotifier::kindOf( 'completed_with_errors' ) );
        $this->assertSame( 'failed', expImportNotifier::kindOf( 'failed' ) );
    }
}
