<?php
/**
 * File containing the expImportAvroSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Apache Avro object container files (.avro), as Kafka connectors, Hadoop and data pipelines write them: one record is
 * one Avro record, nested records and maps flattened to dotted names (address.city) as the MongoDB source does,
 * arrays as JSON or a joined list. Read by expImportAvro, in PHP alone (codecs null and deflate; snappy and zstandard
 * with their PECL extensions). Logical types: dates as 2026-10-22, timestamps as ISO 8601, decimals as text.
 *
 * Parquet files are read by the DuckDB source (duckdb), which needs the duckdb program or libduckdb.
 */
class expImportAvroSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'avro';
    }

    public static function label()
    {
        return 'Apache Avro file';
    }

    public static function extensions()
    {
        return array( 'avro' );
    }

    public static function description()
    {
        return 'An Apache Avro object container file (.avro), as Kafka connectors and data pipelines write it: one record is one '
             . 'Avro record, nested records as dotted fields (address.city), dates and timestamps as ISO 8601, decimals as text. '
             . 'Read in PHP (codecs null and deflate; snappy and zstandard need their PHP extensions). For Parquet use the '
             . 'DuckDB format.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Five teas with a nested origin, tags, a price as a decimal and a date (deflate)', 'file' => 'teas.avro',
                             'options' => array( 'key' => 'id' ) ) );
    }

    public static function schema()
    {
        return array(
            'arrays' => array( 'type' => 'enum', 'label' => 'Arrays', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); arrays of records stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
        ) + parent::commonSchema();
    }

    public function records( array $cursor = array() )
    {
        $reader = new expImportAvro( $this->file() );
        $this->detected['codec'] = $reader->codec;
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $arrays = isset( $this->config['arrays'] ) && $this->config['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $this->config['separator'] ) ? (string)$this->config['separator'] : ', ';
        $block = isset( $cursor['block'] ) ? (int)$cursor['block'] : 0;
        $skip = isset( $cursor['row'] ) ? (int)$cursor['row'] : 0;
        foreach ( $reader->records( $block, $skip ) as $item )
        {
            $value = $item['value'];
            $data = is_array( $value ) && array_values( $value ) !== $value ? expImportMongoDbSource::flatten( $value, $arrays, $separator )
                  : expImportMongoDbSource::flatten( array( 'value' => $value ), $arrays, $separator );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data,
                                       array( 'block' => $item['block'], 'row' => $item['row'] + 1, 'index' => $index + 1 ) );
            $index++;
        }
    }

    /** @return array codec => the way this PHP reads it ('' none), for the job queue's workers (recordWorkerPhp()) */
    public static function localDrivers()
    {
        return expImportAvro::codecs();
    }

    /**
     * The codecs this PHP decompresses, and what the job queue's workers last had.
     *
     * @return array codec => array( 'label', 'available', 'via', 'needs', 'worker', 'worker_php' )
     */
    public static function drivers()
    {
        $local = self::localDrivers();
        $worker = expImportSqlSource::workerPhp();
        $seen = $worker && isset( $worker['sources']['avro'] ) && is_array( $worker['sources']['avro'] ) ? $worker['sources']['avro'] : null;
        $needs = array( 'null' => 'nothing', 'deflate' => 'the PHP extension zlib (part of the PHP packages)',
                        'snappy' => 'the PECL extension snappy (RHEL: php-pecl-snappy of Remi; Debian: pecl install snappy)',
                        'zstandard' => 'the PECL extension zstd (RHEL: php-pecl-zstd of Remi; Debian: php-zstd)', 'bzip2' => 'the PHP extension bz2' );
        $out = array();
        foreach ( $local as $codec => $via )
            $out[$codec] = array( 'label' => 'Avro codec ' . $codec, 'available' => $via !== '', 'via' => $via, 'needs' => $needs[$codec],
                                  'worker' => $seen !== null && isset( $seen[$codec] ) ? ( $seen[$codec] !== '' ? 'yes' : 'no' ) : '',
                                  'worker_php' => $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '' );
        return $out;
    }
}
