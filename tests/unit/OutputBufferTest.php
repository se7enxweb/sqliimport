<?php

use PHPUnit\Framework\TestCase;

/**
 * A view that answers with its own body (a download, JSON) throws away the page's output with expImport::discardOutput(),
 * never with a bare loop on ob_get_level(): under Exponential Velocity a persistent worker keeps a buffer that cannot be
 * removed, the loop never ends and the request is answered 504 (the export of definitions did so on :8080).
 */
class OutputBufferTest extends TestCase
{
    public function testNoBareOutputBufferLoop(): void
    {
        $root = dirname( __DIR__, 2 );
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        $checked = 0;
        foreach ( $files as $file )
        {
            $path = (string)$file;
            if ( substr( $path, -4 ) !== '.php' || strpos( $path, '/tests/' ) !== false || strpos( $path, '/vendor/' ) !== false )
                continue;
            $code = (string)file_get_contents( $path );
            $checked++;
            if ( preg_match_all( '/while\s*\(\s*ob_get_level\(\)\s*>\s*0\s*\)/', $code, $m ) )
                $this->fail( substr( $path, strlen( $root ) + 1 ) . ': a bare loop on ob_get_level(); call expImport::discardOutput()' );
        }
        $this->assertGreaterThan( 100, $checked );
        $helper = (string)file_get_contents( $root . '/classes/exp/expimport.php' );
        $this->assertStringContainsString( "method_exists( 'eZExecution', 'discardOutputBuffers' )", $helper );
    }
}
