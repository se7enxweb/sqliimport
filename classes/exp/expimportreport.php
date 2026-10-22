<?php
/**
 * File containing the expImportReport class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The CSV report of a run: one line per record with what was done to which object. It is read in pages, so a run of
 * a million records is written with the memory of one page (sqliimport/report/<run id> and ./console
 * ext:sqliimport:report).
 *
 * A cell that a spreadsheet would read as a formula (it starts with =, +, -, @, a tab or a carriage return) gets a
 * leading apostrophe: the values come from the source of the import, and a report is opened in spreadsheets.
 */
class expImportReport
{
    /** The rows read from the database at a time */
    const PAGE = 500;

    /** @return string[] the columns of the report */
    public static function columns()
    {
        return array( 'run_id', 'row', 'ref', 'outcome', 'status', 'object_id', 'remote_id', 'version_before', 'version_after',
                      'changed_fields', 'message', 'time' );
    }

    /**
     * One cell, safe to open in a spreadsheet.
     *
     * @param mixed $value
     * @return string
     */
    public static function cell( $value )
    {
        if ( is_bool( $value ) )
            $value = $value ? '1' : '0';
        $text = is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string)$value;
        if ( $text !== '' && strpbrk( $text[0], "=+-@\t\r" ) !== false && !preg_match( '/^-?\d+(\.\d+)?$/', $text ) )
            $text = "'" . $text;
        return $text;
    }

    /**
     * One CSV line (RFC 4180: quotes doubled, a cell with a separator, a quote or a line break in quotes; CRLF).
     *
     * @param array $cells
     * @param string $separator
     * @return string
     */
    public static function line( array $cells, $separator = ',' )
    {
        $out = array();
        foreach ( $cells as $cell )
        {
            $text = self::cell( $cell );
            if ( strpbrk( $text, $separator . "\"\r\n" ) !== false )
                $text = '"' . str_replace( '"', '""', $text ) . '"';
            $out[] = $text;
        }
        return implode( $separator, $out ) . "\r\n";
    }

    /**
     * The cells of one record of a run, in the order of columns().
     *
     * @param array $row the attributes of a sqliimport_run_row
     * @return array
     */
    public static function rowCells( array $row )
    {
        $nodes = isset( $row['nodes_json'] ) ? json_decode( (string)$row['nodes_json'], true ) : null;
        $changed = is_array( $nodes ) && isset( $nodes['diff'] ) && is_array( $nodes['diff'] ) ? array_keys( $nodes['diff'] ) : array();
        $created = isset( $row['created'] ) ? (int)$row['created'] : 0;
        return array(
            (int)$row['run_id'],
            (int)$row['row_index'],
            (string)$row['row_ref'],
            (string)$row['outcome'],
            (string)$row['status'],
            (int)$row['object_id'] ?: '',
            (string)$row['remote_id'],
            (int)$row['version_before'] ?: '',
            (int)$row['version_after'] ?: '',
            implode( ' ', $changed ),
            (string)$row['message'],
            $created ? gmdate( 'Y-m-d\TH:i:s\Z', $created ) : '',
        );
    }

    /** @return string the file name of the report of a run */
    public static function fileName( expImportRun $run, $outcome = '' )
    {
        $import = $run->import();
        $name = $import ? (string)$import->attribute( 'identifier' ) : 'import';
        $name = preg_replace( '/[^a-z0-9_-]+/i', '-', $name );
        return 'sqliimport-' . $name . '-run-' . (int)$run->attribute( 'id' ) . ( $outcome !== '' ? '-' . $outcome : '' ) . '.csv';
    }

    /**
     * Writes the report of a run to a stream.
     *
     * @param expImportRun $run
     * @param resource $handle
     * @param string $outcome only the records with this outcome ('' for all)
     * @param bool $bom a UTF-8 byte order mark first, so spreadsheets read the accents right
     * @return int the records written
     */
    public static function write( expImportRun $run, $handle, $outcome = '', $bom = true )
    {
        if ( $bom )
            fwrite( $handle, "\xEF\xBB\xBF" );
        fwrite( $handle, self::line( self::columns() ) );
        $conds = $outcome !== '' ? array( 'outcome' => $outcome ) : array();
        $count = 0;
        for ( $offset = 0; ; $offset += self::PAGE )
        {
            $rows = expImportRunRow::fetchByRun( $run->attribute( 'id' ), $conds, $offset, self::PAGE );
            foreach ( $rows as $row )
            {
                $data = array();
                foreach ( array( 'run_id', 'row_index', 'row_ref', 'outcome', 'status', 'object_id', 'remote_id', 'version_before',
                                 'version_after', 'nodes_json', 'message', 'created' ) as $name )
                    $data[$name] = $row->attribute( $name );
                fwrite( $handle, self::line( self::rowCells( $data ) ) );
                $count++;
            }
            if ( count( $rows ) < self::PAGE )
                break;
        }
        return $count;
    }
}
