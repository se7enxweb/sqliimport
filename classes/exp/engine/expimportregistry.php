<?php
/**
 * File containing the expImportRegistry class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The catalogue of a site: its sources, handlers and transforms.
 *
 *   sources     sqliimport.ini [SourceSettings] Sources[] with a [Source_<id>] block (Class, Status), and kernel
 *               services tagged sqliimport.source
 *   handlers    "mapping" (no code), sqliimport.ini [HandlerSettings] Handlers[] with a [Handler_<id>] block, kernel
 *               services tagged sqliimport.handler, and the 1.x handlers of [ImportSettings] AvailableSourceHandlers[]
 *               (marked legacy)
 *   transforms  the built-in ones and kernel services tagged sqliimport.transform
 *
 * Status is enabled, example (shown, marked as an example, cannot be chosen for a new import) or disabled. The kernel
 * service sqliimport.registry is one instance per worker; it holds nothing of a request.
 */
class expImportRegistry
{
    /** @var array|null */
    protected $sources = null;

    /** @var array|null */
    protected $handlers = null;

    /** @var bool */
    protected $transformsRegistered = false;

    /** @return expImportRegistry the kernel service, or a new one where the kernel has no service container */
    public static function instance()
    {
        if ( class_exists( 'expKernelServices' ) && expKernelServices::has( 'sqliimport.registry' ) )
            return expKernelServices::get( 'sqliimport.registry' );
        static $local = null;
        return $local ?: ( $local = new self() );
    }

    /** @return array kernel services carrying a tag, id => object (none without a container) */
    protected static function tagged( $tag )
    {
        if ( !class_exists( 'expKernelServices' ) )
            return array();
        try
        {
            return expKernelServices::tagged( $tag );
        }
        catch ( Exception $e )
        {
            eZDebug::writeWarning( "services tagged $tag: " . $e->getMessage(), __METHOD__ );
            return array();
        }
    }

    /**
     * The source types: id => array( 'class', 'label', 'status', 'extensions', 'schema' ).
     * @return array
     */
    public function sources()
    {
        if ( $this->sources !== null )
            return $this->sources;
        $ini = expImport::ini();
        $out = array();
        $ids = $ini->hasVariable( 'SourceSettings', 'Sources' ) ? (array)$ini->variable( 'SourceSettings', 'Sources' ) : array();
        foreach ( $ids as $id )
        {
            $section = 'Source_' . $id;
            $class = $ini->hasVariable( $section, 'Class' ) ? $ini->variable( $section, 'Class' ) : '';
            $status = $ini->hasVariable( $section, 'Status' ) ? $ini->variable( $section, 'Status' ) : 'enabled';
            $this->addSource( $out, $id, $class, $status );
        }
        $entries = class_exists( 'expKernelServices' ) ? self::tagged( 'sqliimport.source' ) : expImportSourceEntry::fromSettings();
        foreach ( $entries as $service )
        {
            $entry = $service instanceof expImportSourceEntry ? $service : new expImportSourceEntry( get_class( $service ) );
            if ( !$entry->isValid() )
            {
                eZDebug::writeWarning( 'source ' . $entry->className() . ' is not an expImportSourceInterface', __METHOD__ );
                continue;
            }
            $id = $entry->id();
            // sqliimport.ini [Source_<id>] Status= switches a format of the service container off or to "example"
            $section = 'Source_' . $id;
            $status = $ini->hasVariable( $section, 'Status' ) ? $ini->variable( $section, 'Status' ) : 'enabled';
            $out[$id] = self::available( array( 'id' => $id, 'status' => $status ) + $entry->toArray() );
        }
        return $this->sources = self::sortByGroup( $out );
    }

    /** @return array the entries ordered by group (data first), the order within a group kept */
    public static function sortByGroup( array $entries )
    {
        $out = array();
        foreach ( expImportSourceEntry::GROUPS as $group )
            foreach ( $entries as $id => $entry )
                if ( $entry['group'] === $group )
                    $out[$id] = $entry;
        return $out;
    }

    protected function addSource( array &$out, $id, $class, $status )
    {
        $entry = new expImportSourceEntry( $class );
        if ( !$entry->isValid() )
        {
            eZDebug::writeWarning( "source $id: $class is not an expImportSourceInterface", __METHOD__ );
            return;
        }
        $out[$id] = self::available( array( 'id' => $id, 'status' => $status ) + $entry->toArray() );
    }

    /**
     * An entry whose format cannot be used on this site (its availability() gives a reason: an opt-in format switched
     * off in the settings) is disabled, whatever [Source_<id>] Status says.
     *
     * @param array $entry
     * @return array
     */
    public static function available( array $entry )
    {
        if ( isset( $entry['availability'] ) && $entry['availability'] !== '' )
            $entry['status'] = 'disabled';
        return $entry;
    }

    /**
     * The formats of the catalogue by group: group => array( label, sources: id => entry ), variants included.
     * @return array
     */
    public function sourcesByGroup()
    {
        $out = array();
        foreach ( $this->sources() as $id => $source )
        {
            if ( !isset( $out[$source['group']] ) )
                $out[$source['group']] = array( 'label' => expImportAdmin::label( 'group', $source['group'] ), 'sources' => array() );
            $out[$source['group']]['sources'][$id] = $source;
        }
        return $out;
    }

    /**
     * The handlers: id => array( 'class', 'label', 'status', 'legacy' (bool), 'schema' ).
     * @return array
     */
    public function handlers()
    {
        if ( $this->handlers !== null )
            return $this->handlers;
        $ini = expImport::ini();
        $out = array( 'mapping' => array( 'class' => 'expImportMappingHandler', 'label' => expImportMappingHandler::label(), 'status' => 'enabled',
                                          'legacy' => false, 'schema' => array() ) );
        $ids = $ini->hasVariable( 'HandlerSettings', 'Handlers' ) ? (array)$ini->variable( 'HandlerSettings', 'Handlers' ) : array();
        foreach ( $ids as $id )
        {
            $section = 'Handler_' . $id;
            $class = $ini->hasVariable( $section, 'Class' ) ? $ini->variable( $section, 'Class' ) : '';
            if ( $class === '' || !class_exists( $class ) || !is_subclass_of( $class, 'expImportHandlerInterface' ) )
            {
                eZDebug::writeWarning( "handler $id: $class is not an expImportHandlerInterface", __METHOD__ );
                continue;
            }
            $out[$id] = array( 'class' => $class, 'label' => $class::label(), 'legacy' => false, 'schema' => $class::schema(),
                               'status' => $ini->hasVariable( $section, 'Status' ) ? $ini->variable( $section, 'Status' ) : 'enabled' );
        }
        foreach ( self::tagged( 'sqliimport.handler' ) as $service )
        {
            $class = get_class( $service );
            if ( $service instanceof expImportHandlerInterface )
                $out[$service::identifier()] = array( 'class' => $class, 'label' => $service::label(), 'legacy' => false, 'schema' => $service::schema(), 'status' => 'enabled' );
        }
        foreach ( expImportLegacyHandlerAdapter::available() as $id => $info )
        {
            if ( isset( $out[$id] ) )
                continue;
            $out[$id] = array( 'class' => $info['class'], 'label' => $info['name'], 'legacy' => true, 'schema' => array(),
                               'status' => $info['enabled'] ? 'enabled' : 'disabled' );
        }
        return $this->handlers = $out;
    }

    /** Forgets the catalogue read so far (it is read again from the settings on the next use). */
    public function reset()
    {
        $this->sources = null;
        $this->handlers = null;
    }

    /** Puts the transforms of kernel services into expImportTransforms (once). */
    public function registerTransforms()
    {
        if ( $this->transformsRegistered )
            return;
        foreach ( self::tagged( 'sqliimport.transform' ) as $service )
            if ( $service instanceof expImportTransformInterface )
                expImportTransforms::register( $service );
        $this->transformsRegistered = true;
    }

    /**
     * A source for a configuration.
     *
     * @param string $id
     * @param array $config validated options plus 'file'
     * @return expImportSourceInterface
     */
    public function source( $id, array $config )
    {
        $sources = $this->sources();
        if ( !isset( $sources[$id] ) )
            throw new expImportException( "unknown source type '$id'" );
        $class = $sources[$id]['class'];
        return new $class( $config );
    }

    /**
     * The handler of an import.
     *
     * @param expImportDefinition $import
     * @return expImportHandlerInterface
     */
    public function handlerFor( expImportDefinition $import )
    {
        $id = (string)$import->attribute( 'handler' );
        $handlers = $this->handlers();
        if ( !isset( $handlers[$id] ) )
        {
            // the settings may have changed since this (worker-scoped) catalogue was read
            $this->reset();
            $handlers = $this->handlers();
        }
        if ( !isset( $handlers[$id] ) )
            throw new expImportException( "unknown handler '$id'" );
        if ( $handlers[$id]['legacy'] )
            return new expImportLegacyHandlerAdapter( $id );
        $class = $handlers[$id]['class'];
        return new $class();
    }
}
