<?php
/**
 * Stands for the duckdb program in DuckDbSourceTest (expImportDuckDbSource::$command): reads the statements from its
 * input and answers like duckdb -json. A statement with FAIL makes it fail like DuckDB, one with SLEEP makes it wait,
 * one with HUGE gives a large output; otherwise it gives one row that tells what it was asked.
 */
$script = (string)stream_get_contents( STDIN );
$args = array_slice( $argv, 1 );
if ( strpos( $script, 'FAIL' ) !== false )
{
    fwrite( STDERR, "Binder Error: Referenced column \"FAIL\" not found\n" );
    exit( 1 );
}
if ( strpos( $script, 'SLEEP' ) !== false )
    sleep( 5 );
if ( strpos( $script, 'HUGE' ) !== false )
{
    echo '[', str_repeat( '{"x":"' . str_repeat( 'y', 1000 ) . '"},', 3000 ), '{"x":""}]';
    exit( 0 );
}
$statements = array_values( array_filter( array_map( 'trim', explode( ";\n", $script ) ), 'strlen' ) );
echo json_encode( array( array( 'args' => implode( ' ', $args ), 'statements' => count( $statements ), 'last' => end( $statements ),
                                'home' => (string)getenv( 'HOME' ), 'secret' => (string)getenv( 'SQ688_DUCK_SECRET' ) ) ) ), "\n";
