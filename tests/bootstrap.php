<?php
/**
 * The bootstrap of the unit tests that need no database and no running installation: an autoloader for the classes
 * of the extension (every class and interface declared under classes/), so a test can use the pure parts of the
 * engine (sources, transforms, mapper, schedules, option schemas) on their own. PHPUnit 9.6 (PHP 8.0) and later.
 */
spl_autoload_register( function ( $class )
{
    static $map = null;
    if ( $map === null )
    {
        $map = array();
        $root = dirname( __DIR__ ) . '/classes';
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file )
        {
            if ( substr( $file->getFilename(), -4 ) !== '.php' )
                continue;
            if ( preg_match_all( '/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/mi', (string)file_get_contents( $file->getPathname() ), $m ) )
                foreach ( $m[1] as $name )
                    $map[strtolower( $name )] = $file->getPathname();
        }
    }
    $key = strtolower( $class );
    if ( isset( $map[$key] ) )
        require_once $map[$key];
} );

// Without a kernel (the CI of this repository): the base classes the extension's classes extend, so a test can load
// them. Their kernel behaviour is never used by the unit tests.
if ( !class_exists( 'eZPersistentObject' ) )
{
    #[AllowDynamicProperties]
    class eZPersistentObject
    {
        public function __construct( $row = null )
        {
        }
    }
}
