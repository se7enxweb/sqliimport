<?php
/**
 * File containing the handler interfaces of the 2.x engine.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A handler of the 2.x engine: it turns records into content. The records come from the import's source (CSV, XML,
 * RSS, JSON ...), so a handler does not read files itself; the context gives it the typed options, the target, the
 * log, the dry-run flag and the cancel check.
 *
 * Registered in sqliimport.ini ([HandlerSettings] Handlers[] and a [Handler_<id>] block with Class=) or as a kernel
 * service tagged sqliimport.handler. The no-code handler "mapping" (expImportMappingHandler) is one of them. Handlers
 * of 1.x (ISQLIImportHandler) keep working through expImportLegacyHandlerAdapter.
 *
 * Per run, per chunk: open() once, process() per record, close() once, all in a worker of the job queue.
 */
interface expImportHandlerInterface
{
    /** @return string the id an import stores */
    public static function identifier();

    /** @return string the name in the catalogue */
    public static function label();

    /** @return array the handler's options, as an expImportOptionSchema schema; the admin form is made from it */
    public static function schema();

    /** Prepares a chunk (caches, a publisher). */
    public function open( expImportContext $context );

    /**
     * Processes one record. An exception fails this record only (its message is the row's message); the run goes on.
     *
     * @param expImportRecord $record
     * @param expImportContext $context
     * @return expImportRowResult
     */
    public function process( expImportRecord $record, expImportContext $context );

    /** Ends a chunk. */
    public function close( expImportContext $context );
}

/**
 * A handler that reads its records itself (a 1.x handler through the adapter): the engine takes the records from it
 * instead of from a source.
 */
interface expImportRecordProviderInterface
{
    /**
     * @param expImportContext $context
     * @param array $cursor
     * @return iterable<expImportRecord>
     */
    public function records( expImportContext $context, array $cursor = array() );

    /** @return int|null the number of records, when known */
    public function count( expImportContext $context );

    /**
     * @return bool whether records() can start after a cursor; when not, the whole run is one chunk (a retry starts
     *              over, and the content API's unchanged detection keeps that cheap)
     */
    public function resumable();
}
