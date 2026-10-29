<?php
/**
 * File containing the expImportLegacyHandlerAdapter class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Runs a handler of sqliimport 1.x (ISQLIImportHandler extending SQLIImportAbstractHandler) in the 2.x engine,
 * untouched: the handler is built with its SQLIImportHandlerOptions (filled from the import's options), gets its
 * handlerConfArray from its [<id>-HandlerSettings] block of sqliimport.ini as before, and its initialize(),
 * getProcessLength(), getNextRow(), process() and cleanup() are called in that order. getProgressionNotes() goes to
 * the run's log.
 *
 * A 1.x handler reads its own source and cannot start in the middle, so its run is one chunk: a retry starts over,
 * and the content API's unchanged detection keeps that cheap. What process() did to the content is not known to the
 * engine, so its rows have the outcome "processed" (or "failed" with the exception's message).
 */
class expImportLegacyHandlerAdapter implements expImportHandlerInterface, expImportRecordProviderInterface
{
    /** @var string the handler's id in sqliimport.ini */
    protected $id;

    /** @var ISQLIImportHandler|SQLIImportAbstractHandler|null */
    protected $handler = null;

    /** @var array index => the row object getNextRow() returned */
    protected $rows = array();

    /** @var string */
    protected $lastNotes = '';

    /** @var bool */
    protected $initialized = false;

    public function __construct( $id )
    {
        $this->id = (string)$id;
    }

    public static function identifier()
    {
        return 'legacy';
    }

    public static function label()
    {
        return 'Handler of sqliimport 1.x';
    }

    public static function schema()
    {
        // free key=value options, as in 1.x
        return array();
    }

    /** @return string */
    public function handlerID()
    {
        return $this->id;
    }

    /**
     * The 1.x handlers of sqliimport.ini [ImportSettings] AvailableSourceHandlers[]: id => array( name, class,
     * enabled ).
     * @return array
     */
    public static function available()
    {
        $ini = expImport::ini();
        $out = array();
        foreach ( (array)$ini->variable( 'ImportSettings', 'AvailableSourceHandlers' ) as $id )
        {
            $section = $id . '-HandlerSettings';
            if ( !$ini->hasSection( $section ) )
                continue;
            $out[$id] = array(
                'name' => $ini->hasVariable( $section, 'Name' ) ? $ini->variable( $section, 'Name' ) : $id,
                'class' => $ini->hasVariable( $section, 'ClassName' ) ? $ini->variable( $section, 'ClassName' ) : '',
                'enabled' => !$ini->hasVariable( $section, 'Enabled' ) || $ini->variable( $section, 'Enabled' ) === 'true',
            );
        }
        return $out;
    }

    /** @return ISQLIImportHandler */
    protected function build( expImportContext $context )
    {
        if ( $this->handler )
            return $this->handler;
        $ini = expImport::ini();
        $section = $this->id . '-HandlerSettings';
        if ( !$ini->hasSection( $section ) || !$ini->hasVariable( $section, 'ClassName' ) )
            throw new expImportException( "the 1.x handler '{$this->id}' has no [$section] block with ClassName in sqliimport.ini" );
        $class = $ini->variable( $section, 'ClassName' );
        if ( !class_exists( $class ) )
            throw new expImportException( "the class $class of the 1.x handler '{$this->id}' does not exist (regenerate the autoloads)" );
        $options = new SQLIImportHandlerOptions();
        foreach ( $context->options as $key => $value )
            $options->$key = is_scalar( $value ) ? (string)$value : '';
        $handler = new $class( $options );
        if ( !$handler instanceof ISQLIImportHandler || !$handler instanceof SQLIImportAbstractHandler )
            throw new expImportException( "$class must implement ISQLIImportHandler and extend SQLIImportAbstractHandler" );
        $handler->handlerConfArray = $ini->group( $section );
        return $this->handler = $handler;
    }

    public function resumable()
    {
        return false;
    }

    public function count( expImportContext $context )
    {
        $handler = $this->build( $context );
        if ( !$this->initialized )
        {
            $handler->initialize();
            $this->initialized = true;
        }
        $length = (int)$handler->getProcessLength();
        return $length > 0 ? $length : null;
    }

    public function records( expImportContext $context, array $cursor = array() )
    {
        $handler = $this->build( $context );
        if ( !$this->initialized )
        {
            $handler->initialize();
            $this->initialized = true;
        }
        $index = 0;
        while ( $row = $handler->getNextRow() )
        {
            $this->rows = array( $index => $row );
            $data = is_array( $row ) ? $row : self::describeRow( $row );
            yield new expImportRecord( '#' . ( $index + 1 ), $index, $data, array( 'index' => $index + 1 ) );
            $index++;
        }
    }

    /** @return array a readable copy of a row object (for the log and the row list; never processed) */
    protected static function describeRow( $row )
    {
        if ( $row instanceof SimpleXMLElement )
            return array( 'xml' => mb_substr( (string)$row->asXML(), 0, 2000 ) );
        if ( $row instanceof Traversable )
        {
            $out = array();
            foreach ( $row as $k => $v )
                if ( is_scalar( $v ) )
                    $out[(string)$k] = $v;
            return $out;
        }
        return is_scalar( $row ) ? array( 'value' => $row ) : array( 'type' => is_object( $row ) ? get_class( $row ) : gettype( $row ) );
    }

    public function open( expImportContext $context )
    {
        $this->build( $context );
    }

    public function process( expImportRecord $record, expImportContext $context )
    {
        $row = isset( $this->rows[$record->index] ) ? $this->rows[$record->index] : $record->data;
        if ( $context->dryRun )
            return new expImportRowResult( expImportRowResult::SKIPPED, 'dry run: a 1.x handler cannot run without publishing' );
        $this->handler->process( $row );
        $notes = (string)$this->handler->getProgressionNotes();
        if ( $notes !== '' && $notes !== $this->lastNotes )
        {
            $context->log->info( $notes, $record->ref );
            $this->lastNotes = $notes;
        }
        return new expImportRowResult( expImportRowResult::PROCESSED );
    }

    public function close( expImportContext $context )
    {
        if ( $this->handler )
        {
            $this->handler->cleanup();
            $this->handler = null;
        }
        $this->rows = array();
        $this->initialized = false;
    }
}
