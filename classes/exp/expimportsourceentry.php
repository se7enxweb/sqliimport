<?php
/**
 * File containing the expImportSourceEntry class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * One entry of the catalogue of formats: a source class, or one variant of it (TSV is the CSV source with a tab,
 * NDJSON the JSON source with one object per line). A format is one source class plus one block in
 * kernelservices.ini:
 *
 *   [Service_sqliimport.source.opml]
 *   Class=expImportSourceEntry
 *   Factory=expImportSourceEntry::of
 *   Arguments[]=expImportOpmlSource
 *   Scope=worker
 *   Tags[]=sqliimport.source
 *
 * and, for a variant, a second argument with the variant's id (Arguments[]=tsv). What the catalogue shows comes from
 * the class (its static methods, with the defaults of expImportAbstractSource):
 *
 *   identifier(), label(), extensions(), schema()   as for every source
 *   description()   a paragraph: what the format is and what one record is
 *   group()         data, content, platform, commerce, media or other
 *   examples()      list of array( 'label', 'file' (in the extension's share/examples) or 'url' )
 *   preset()        a mapping to start from (class, match, fields), or array() when none fits
 *   variants()      id => array( 'label', 'description', 'extensions', 'defaults' (option => value), 'examples', 'preset' )
 *   notice()        a warning the catalogue and the editor show with the format (a scraper against a site's terms)
 *   availability()  '' when the format may be used on this site, else why not: the catalogue then lists it as
 *                   disabled with that reason (an opt-in format switched off in the settings)
 *
 * The entry holds nothing of a request, so one per worker is right.
 */
class expImportSourceEntry
{
    const GROUPS = array( 'data', 'nosql', 'content', 'platform', 'commerce', 'media', 'other' );

    /** @var string */
    protected $class;

    /** @var string */
    protected $variant;

    /**
     * @param string $class a class implementing expImportSourceInterface
     * @param string $variant the id of one of its variants, '' for the class itself
     */
    public function __construct( $class, $variant = '' )
    {
        $this->class = (string)$class;
        $this->variant = (string)$variant;
    }

    /** The factory of the kernel service block. */
    public static function of( $class, $variant = '' )
    {
        return new self( $class, $variant );
    }

    /** @return string */
    public function className()
    {
        return $this->class;
    }

    /** @return bool whether the class exists and is a source */
    public function isValid()
    {
        if ( $this->class === '' || !class_exists( $this->class ) || !is_subclass_of( $this->class, 'expImportSourceInterface' ) )
            return false;
        return $this->variant === '' || isset( self::call( $this->class, 'variants', array() )[$this->variant] );
    }

    /** @return string the id a definition stores */
    public function id()
    {
        return $this->variant !== '' ? $this->variant : (string)call_user_func( array( $this->class, 'identifier' ) );
    }

    /**
     * The catalogue entry: class, variant, label, description, group, extensions, schema (with the variant's
     * defaults), defaults, examples, preset.
     *
     * @return array
     */
    public function toArray()
    {
        $class = $this->class;
        $entry = array(
            'class' => $class,
            'variant' => $this->variant,
            'label' => (string)$class::label(),
            'description' => (string)self::call( $class, 'description', '' ),
            'group' => (string)self::call( $class, 'group', 'data' ),
            'extensions' => (array)$class::extensions(),
            'schema' => (array)$class::schema(),
            'defaults' => array(),
            'examples' => (array)self::call( $class, 'examples', array() ),
            'preset' => (array)self::call( $class, 'preset', array() ),
            // where the data may come from: upload, url, path (a file the engine fetches) or remote (the source reads it itself)
            'origins' => (array)self::call( $class, 'origins', method_exists( $class, 'fetch' ) ? array( 'remote' ) : array( 'upload', 'url', 'path' ) ),
            // a format that can try its connection (Test connection) and that names the drivers it needs
            'can_test' => method_exists( $class, 'test' ),
            'has_drivers' => method_exists( $class, 'drivers' ),
            // a warning shown with the format, and why it cannot be used on this site ('' when it can)
            'notice' => (string)self::call( $class, 'notice', '' ),
            'availability' => (string)self::call( $class, 'availability', '' ),
        );
        if ( $this->variant !== '' )
        {
            $variant = self::call( $class, 'variants', array() )[$this->variant];
            foreach ( array( 'label', 'description', 'group', 'extensions', 'examples', 'preset', 'defaults' ) as $key )
                if ( isset( $variant[$key] ) )
                    $entry[$key] = $variant[$key];
            foreach ( $entry['defaults'] as $option => $value )
                if ( isset( $entry['schema'][$option] ) )
                    $entry['schema'][$option]['default'] = $value;
        }
        if ( !in_array( $entry['group'], self::GROUPS, true ) )
            $entry['group'] = 'other';
        return $entry;
    }

    /** @return mixed a static method of the class when it has it, else the default */
    protected static function call( $class, $method, $default )
    {
        return method_exists( $class, $method ) ? call_user_func( array( $class, $method ) ) : $default;
    }

    /**
     * The entries of the kernel service blocks tagged sqliimport.source, read from kernelservices.ini (for a kernel
     * without the service container).
     *
     * @return expImportSourceEntry[]
     */
    public static function fromSettings()
    {
        $ini = eZINI::instance( 'kernelservices.ini' );
        $out = array();
        foreach ( $ini->groups() as $name => $block )
        {
            if ( strpos( $name, 'Service_' ) !== 0 || empty( $block['Tags'] ) || !in_array( 'sqliimport.source', (array)$block['Tags'], true ) )
                continue;
            if ( isset( $block['Enabled'] ) && $block['Enabled'] === 'false' )
                continue;
            $args = isset( $block['Arguments'] ) ? array_values( (array)$block['Arguments'] ) : array();
            if ( isset( $block['Factory'] ) && $block['Factory'] === 'expImportSourceEntry::of' && $args )
                $out[] = new self( $args[0], isset( $args[1] ) ? $args[1] : '' );
            elseif ( isset( $block['Class'] ) && $block['Class'] !== 'expImportSourceEntry' )
                $out[] = new self( $block['Class'] );
        }
        return $out;
    }
}
