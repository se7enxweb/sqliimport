<?php
/**
 * File containing the expImportSourceInterface interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A source type: it reads records from a file (or, for later source types, an endpoint). The engine resolves where
 * the data comes from (an upload, a URL, a server path inside the allowlist) and hands the source a local file in
 * its configuration as 'file', so a source never fetches anything itself and every chunk of a run reads the same
 * snapshot.
 *
 * Registered in sqliimport.ini [SourceSettings] Sources[] with a [Source_<id>] block, or as a kernel service tagged
 * sqliimport.source.
 */
interface expImportSourceInterface
{
    /** @return string the id a definition stores (csv, xml, rss, json ...) */
    public static function identifier();

    /** @return string the name in the catalogue */
    public static function label();

    /** @return array the options of the source, as an expImportOptionSchema schema (without 'file') */
    public static function schema();

    /** @return string[] the file name extensions an upload of this source may have */
    public static function extensions();

    /**
     * @param array $config validated options plus 'file' (a local path)
     */
    public function __construct( array $config );

    /**
     * The records, from the start or right after a cursor an earlier record carried.
     *
     * @param array $cursor
     * @return iterable<expImportRecord>
     * @throws expImportSourceException
     */
    public function records( array $cursor = array() );

    /**
     * The number of records, when the source can tell it cheaply (for the progress and the ETA).
     * @return int|null
     */
    public function count();

    /**
     * The first records and what the source found out (columns, detected delimiter, encoding ...).
     *
     * @param int $limit
     * @return array ( 'records' => expImportRecord[], 'fields' => string[], 'detected' => array )
     */
    public function sample( $limit = 20 );
}
