<?php
/**
 * File containing the expImportMongoDbTransport interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The connection the MongoDB source reads through: the PHP extension mongodb (expImportMongoDbExtension), or a test
 * double. It offers reading only: a find on one collection, nothing that writes, runs code or aggregates.
 */
interface expImportMongoDbTransport
{
    /**
     * Connects (the driver connects on the first query; errors may come from find()).
     *
     * @param string $uri a mongodb:// or mongodb+srv:// URI without user and password
     * @param array $uriOptions username, password, authSource, tls, the time limits, appname
     * @throws expImportSourceException
     */
    public function open( $uri, array $uriOptions );

    /**
     * The documents of a find, each a PHP array whose subdocuments and arrays are arrays and whose other BSON values
     * are left as they are (ObjectId, UTCDateTime, Decimal128, Binary ...).
     *
     * @param string $database
     * @param string $collection
     * @param string $filter the filter as JSON (MongoDB Extended JSON: {"$oid": ...}, {"$date": ...} are read)
     * @param mixed $after null, or the _id the documents must come after ({"_id": {"$gt": $after}} is added)
     * @param array $options projection, sort (arrays), skip, limit, batchSize, maxTimeMS, readPreference (a mode)
     * @return iterable<array>
     * @throws expImportSourceException
     */
    public function find( $database, $collection, $filter, $after, array $options );

    /** Closes the connection (a no-op where the driver keeps it). */
    public function close();
}
