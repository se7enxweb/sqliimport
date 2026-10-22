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

// Without a kernel: settings a test sets itself (eZINI::instance( 'sqliimport.ini' )->setVariable( ... )), so the parts
// that read sqliimport.ini (allowed paths and hosts, secrets) can be tested; nothing is read from a file.
if ( !class_exists( 'eZINI' ) )
{
    class eZINI
    {
        /** @var eZINI[] */
        protected static $instances = array();

        /** @var array section => name => value */
        protected $values = array();

        public static function instance( $fileName = 'site.ini' )
        {
            if ( !isset( self::$instances[$fileName] ) )
                self::$instances[$fileName] = new self();
            return self::$instances[$fileName];
        }

        public function hasVariable( $section, $name )
        {
            return isset( $this->values[$section] ) && array_key_exists( $name, $this->values[$section] );
        }

        public function variable( $section, $name )
        {
            return $this->hasVariable( $section, $name ) ? $this->values[$section][$name] : false;
        }

        public function setVariable( $section, $name, $value )
        {
            $this->values[$section][$name] = $value;
        }

        /** Forgets a setting again (tests clean up after themselves). */
        public function removeSetting( $section, $name )
        {
            unset( $this->values[$section][$name] );
        }
    }
}
