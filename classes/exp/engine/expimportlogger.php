<?php
/**
 * File containing the expImportLogger class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The log of a run (sqliimport_run_log), what upstream PR #13 asked for: every warning, error and notice of a run,
 * with the record it is about and a context, shown and filtered on the run page and tailed while the run goes on.
 * Lines are written in small batches; flush() writes the rest (the engine calls it at the end of every chunk).
 */
class expImportLogger
{
    /** @var int */
    protected $runID;

    /** @var array pending rows */
    protected $buffer = array();

    /** @var int the lowest severity that is written (index in expImportRunLog::SEVERITIES) */
    protected $threshold;

    /** @var int lines written by this logger */
    public $written = 0;

    /**
     * @param int $runID
     * @param string $minimum the lowest severity kept (debug, info, notice, warning, error)
     */
    public function __construct( $runID, $minimum = 'info' )
    {
        $this->runID = (int)$runID;
        $index = array_search( $minimum, expImportRunLog::SEVERITIES, true );
        $this->threshold = $index === false ? 1 : (int)$index;
    }

    public function debug( $message, $ref = '', array $context = array() )
    {
        $this->log( 'debug', $message, $ref, $context );
    }

    public function info( $message, $ref = '', array $context = array() )
    {
        $this->log( 'info', $message, $ref, $context );
    }

    public function notice( $message, $ref = '', array $context = array() )
    {
        $this->log( 'notice', $message, $ref, $context );
    }

    public function warning( $message, $ref = '', array $context = array() )
    {
        $this->log( 'warning', $message, $ref, $context );
    }

    public function error( $message, $ref = '', array $context = array() )
    {
        $this->log( 'error', $message, $ref, $context );
    }

    /**
     * @param string $severity
     * @param string $message
     * @param string $ref the record's reference
     * @param array $context
     */
    public function log( $severity, $message, $ref = '', array $context = array() )
    {
        $index = array_search( $severity, expImportRunLog::SEVERITIES, true );
        if ( $index === false )
            $severity = 'info';
        elseif ( $index < $this->threshold )
            return;
        $this->buffer[] = array( $severity, (string)$message, (string)$ref, $context, expImport::now() );
        if ( count( $this->buffer ) >= 20 || $severity === 'error' )
            $this->flush();
    }

    /** Writes the pending lines. */
    public function flush()
    {
        if ( !$this->buffer || !$this->runID )
        {
            $this->buffer = array();
            return;
        }
        foreach ( $this->buffer as $line )
        {
            list( $severity, $message, $ref, $context, $time ) = $line;
            $row = new expImportRunLog( array(
                'run_id' => $this->runID,
                'severity' => $severity,
                'row_ref' => substr( $ref, 0, 255 ),
                'message' => mb_substr( $message, 0, 4000 ),
                'context_json' => $context ? expImport::json( $context ) : '',
                'created' => $time,
            ) );
            $row->store();
            $this->written++;
        }
        $this->buffer = array();
    }
}
