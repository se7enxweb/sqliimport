<?php

use PHPUnit\Framework\TestCase;

/**
 * The extension needs no zetacomponents/configuration: the kernel package is dropping it. A handler without a config
 * section or without ClassName is refused with SQLIImportConfigException, which runImport() catches as an Exception;
 * a class of the missing component would end the run with an Error instead.
 */
class ZetaConfigurationNotUsedTest extends TestCase
{
    public function testNoFileUsesAClassOfZetaConfiguration(): void
    {
        $root = dirname( __DIR__, 2 );
        $found = array();
        foreach ( array( 'bin', 'classes', 'cronjobs', 'modules', 'update' ) as $dir )
        {
            if ( !is_dir( "$root/$dir" ) )
                continue;
            $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$dir", FilesystemIterator::SKIP_DOTS ) );
            foreach ( $it as $file )
            {
                if ( substr( $file->getFilename(), -4 ) === '.php'
                     && preg_match( '/\bezcConfiguration\w*/', (string)file_get_contents( $file->getPathname() ) ) )
                    $found[] = substr( $file->getPathname(), strlen( $root ) + 1 );
            }
        }
        $this->assertSame( array(), $found );
    }

    public function testTheMissingHandlerConfigurationIsRefusedWithTheExtensionsOwnException(): void
    {
        $code = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/sqliimportfactory.php' );
        $this->assertSame( 2, substr_count( $code, 'throw new SQLIImportConfigException(' ) );
    }
}
