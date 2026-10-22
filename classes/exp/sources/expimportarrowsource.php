<?php
/**
 * File containing the expImportArrowSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Apache Arrow IPC files (.arrow), Feather version 2 files (.feather, the same format) and Arrow IPC streams (.arrows),
 * as pandas, Polars, R and DuckDB write them: one record is one row, struct columns flattened to dotted names as the
 * MongoDB source does, lists as JSON or a joined list. Read by expImportArrow, in PHP alone (LZ4 compression too;
 * Zstandard needs the PECL extension zstd). A file is read whole: very large data is better split.
 *
 * DuckDB reads Arrow files only through its community extension "arrow", which the DuckDB source does not load (it
 * refuses INSTALL and LOAD), so Arrow is read here. Parquet files are read by the DuckDB source.
 */
class expImportArrowSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'arrow';
    }

    public static function label()
    {
        return 'Apache Arrow or Feather file';
    }

    public static function extensions()
    {
        return array( 'arrow', 'feather', 'arrows', 'ipc' );
    }

    public static function description()
    {
        return 'An Apache Arrow IPC file (.arrow), a Feather v2 file (.feather) or an Arrow stream (.arrows), as pandas, Polars '
             . 'and R write them: one record is one row, struct columns as dotted fields, timestamps as ISO 8601, decimals as '
             . 'text. Read in PHP (LZ4 compression too; Zstandard needs the PHP extension zstd). For Parquet use the DuckDB format.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Five teas with a struct origin, a list of tags, a decimal price, a date and a timestamp (Arrow file format)',
                             'file' => 'teas.arrow', 'options' => array( 'key' => 'id' ) ) );
    }

    public static function schema()
    {
        return array(
            'arrays' => array( 'type' => 'enum', 'label' => 'Lists', 'default' => 'json',
                               'values' => array( 'json' => 'As JSON (["a","b"])', 'join' => 'As a joined list (a, b); lists of structs stay JSON' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'List separator', 'default' => ', ', 'raw' => true, 'max' => 10 ),
        ) + parent::commonSchema();
    }

    public function records( array $cursor = array() )
    {
        $max = max( 1, (int)expImportPlatformHttp::setting( 'SourceSettings', 'MaxSourceMegabytes', 200 ) ) * 1048576;
        $reader = new expImportArrow( $this->file(), max( $max, 1048576 ) );
        $this->detected['format'] = $reader->format;
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $arrays = isset( $this->config['arrays'] ) && $this->config['arrays'] === 'join' ? 'join' : 'json';
        $separator = isset( $this->config['separator'] ) ? (string)$this->config['separator'] : ', ';
        $batch = isset( $cursor['batch'] ) ? (int)$cursor['batch'] : 0;
        $skip = isset( $cursor['row'] ) ? (int)$cursor['row'] : 0;
        foreach ( $reader->records( $batch, $skip ) as $item )
        {
            $data = expImportMongoDbSource::flatten( $item['value'], $arrays, $separator );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data,
                                       array( 'batch' => $item['batch'], 'row' => $item['row'] + 1, 'index' => $index + 1 ) );
            $index++;
        }
    }

    /** @return array compression => the way this PHP reads it ('' none), for the job queue's workers (recordWorkerPhp()) */
    public static function localDrivers()
    {
        return array( 'none' => 'PHP', 'lz4' => 'PHP', 'zstd' => function_exists( 'zstd_uncompress' ) ? 'zstd' : '' );
    }

    /**
     * The compressions this PHP reads, and what the job queue's workers last had.
     *
     * @return array id => array( 'label', 'available', 'via', 'needs', 'worker', 'worker_php' )
     */
    public static function drivers()
    {
        $local = self::localDrivers();
        $worker = expImportSqlSource::workerPhp();
        $seen = $worker && isset( $worker['sources']['arrow'] ) && is_array( $worker['sources']['arrow'] ) ? $worker['sources']['arrow'] : null;
        $labels = array( 'none' => 'Arrow without compression', 'lz4' => 'Arrow with LZ4 frames (the default of pyarrow\'s Feather)', 'zstd' => 'Arrow with Zstandard' );
        $needs = array( 'none' => 'nothing', 'lz4' => 'nothing (read in PHP)', 'zstd' => 'the PECL extension zstd (RHEL: php-pecl-zstd of Remi; Debian: php-zstd)' );
        $out = array();
        foreach ( $local as $id => $via )
            $out[$id] = array( 'label' => $labels[$id], 'available' => $via !== '', 'via' => $via, 'needs' => $needs[$id],
                               'worker' => $seen !== null && isset( $seen[$id] ) ? ( $seen[$id] !== '' ? 'yes' : 'no' ) : '',
                               'worker_php' => $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '' );
        return $out;
    }
}
