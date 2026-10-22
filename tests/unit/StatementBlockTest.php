<?php

use PHPUnit\Framework\TestCase;

/**
 * An if, elseif, else, foreach, for or while without braces controls one statement only. A second statement indented
 * like the first looks controlled but runs always: the bug of sqliimport/edit/new (a node's name read when there was
 * no node). Every such place in the PHP of the extension is reported.
 */
class StatementBlockTest extends TestCase
{
    /**
     * The places in a PHP text where an unbraced control is followed by two statements at the same deeper indentation.
     *
     * @param string $text
     * @return int[] line numbers (1-based) of the control
     */
    public static function suspicious( string $text ): array
    {
        $lines = explode( "\n", str_replace( "\r\n", "\n", $text ) );
        $indent = function ( $line )
        {
            return strlen( $line ) - strlen( ltrim( $line, ' ' ) );
        };
        $out = array();
        for ( $i = 0; $i + 2 < count( $lines ); $i++ )
        {
            $line = rtrim( $lines[$i] );
            $trim = ltrim( $line );
            $isControl = preg_match( '/^(}\s*)?(if|elseif|else\s+if|foreach|for|while)\s*\(.*\)$/', $trim ) || preg_match( '/^(}\s*)?else$/', $trim );
            if ( !$isControl || substr_count( $trim, '(' ) !== substr_count( $trim, ')' ) )
                continue;
            $first = rtrim( $lines[$i + 1] );
            $second = rtrim( $lines[$i + 2] );
            if ( trim( $first ) === '' || ltrim( $first )[0] === '{' || $indent( $first ) <= $indent( $line ) || substr( $first, -1 ) !== ';' )
                continue;
            if ( trim( $second ) === '' || $indent( $second ) !== $indent( $first ) )
                continue;
            $s = ltrim( $second );
            if ( preg_match( '/^(\/\/|\*|\/\*|#|->|\.|&&|\|\||\?|:|\)|\]|\})/', $s ) )
                continue;
            $out[] = $i + 1;
        }
        return $out;
    }

    public function testTheCheckFindsTheBug(): void
    {
        $bug = "<?php\nif ( \$node )\n    \$a = 1;\n    \$b = \$node->x;\nfoo();\n";
        $this->assertSame( array( 2 ), self::suspicious( $bug ) );
        $fine = "<?php\nif ( \$node )\n    \$a = 1;\n\$b = 2;\nif ( \$x )\n{\n    \$a = 1;\n    \$b = 2;\n}\nforeach ( \$l as \$v )\n    if ( \$v )\n        \$n++;\n";
        $this->assertSame( array(), self::suspicious( $fine ) );
    }

    public function testNoUnbracedBlockOfTwoStatements(): void
    {
        $root = dirname( __DIR__, 2 );
        $files = array();
        foreach ( array( 'classes', 'modules', 'bin', 'cronjobs' ) as $dir )
        {
            if ( !is_dir( $root . '/' . $dir ) )
                continue;
            $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $it as $file )
                if ( substr( (string)$file, -4 ) === '.php' )
                    $files[] = (string)$file;
        }
        $this->assertNotEmpty( $files );
        $found = array();
        foreach ( $files as $file )
            foreach ( self::suspicious( (string)file_get_contents( $file ) ) as $line )
                $found[] = substr( $file, strlen( $root ) + 1 ) . ':' . $line;
        $this->assertSame( array(), $found, 'an unbraced control followed by two statements at the same indentation' );
    }
}
