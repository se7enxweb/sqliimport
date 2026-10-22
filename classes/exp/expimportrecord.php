<?php
/**
 * File containing the expImportRecord class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * One record read from a source: its stable reference (a key or a line number), its position, its data and the
 * cursor that resumes the source right after it.
 *
 * The data is a flat or nested array (a CSV line keyed by its header, an XML element turned into an array, a JSON
 * object). A path into it is written with dots: "author.name", "items.0.title"; get() reads one.
 */
class expImportRecord
{
    /** @var string a reference that names the record in this source: a key column, a GUID, or "line 12" */
    public $ref;

    /** @var int the 0-based position of the record in the source */
    public $index;

    /** @var array */
    public $data;

    /** @var array where the source continues after this record (offset, byte position ...) */
    public $cursor;

    /**
     * @param string $ref
     * @param int $index
     * @param array $data
     * @param array $cursor
     */
    public function __construct( $ref, $index, array $data, array $cursor = array() )
    {
        $this->ref = (string)$ref;
        $this->index = (int)$index;
        $this->data = $data;
        $this->cursor = $cursor;
    }

    /**
     * A value by its path ("title", "author.name", "@attributes.id").
     *
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public function get( $path, $default = null )
    {
        return self::pathValue( $this->data, $path, $default );
    }

    /**
     * A value of an array by its dotted path. A key that itself contains a dot is found as written first.
     *
     * @param array $data
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public static function pathValue( array $data, $path, $default = null )
    {
        $path = (string)$path;
        if ( $path === '' )
            return $default;
        if ( array_key_exists( $path, $data ) )
            return $data[$path];
        $value = $data;
        foreach ( explode( '.', $path ) as $part )
        {
            if ( is_array( $value ) && array_key_exists( $part, $value ) )
                $value = $value[$part];
            else
                return $default;
        }
        return $value;
    }

    /**
     * The paths of the leaves of the data ("title", "author.name"), for the mapping editor and the auto-match.
     *
     * @param array $data
     * @param string $prefix
     * @param int $depth
     * @return string[]
     */
    public static function paths( array $data, $prefix = '', $depth = 0 )
    {
        $out = array();
        foreach ( $data as $key => $value )
        {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if ( is_array( $value ) && $value && $depth < 4 )
            {
                $out = array_merge( $out, self::paths( $value, $path, $depth + 1 ) );
                // a list of scalars (tags, categories) can also be mapped as a whole
                if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) && !is_array( reset( $value ) ) )
                    $out[] = $path;
            }
            else
                $out[] = $path;
        }
        return array_values( array_unique( $out ) );
    }

    /** @return array */
    public function toArray()
    {
        return array( 'ref' => $this->ref, 'index' => $this->index, 'data' => $this->data, 'cursor' => $this->cursor );
    }

    /** @return expImportRecord */
    public static function fromArray( array $row )
    {
        return new self( isset( $row['ref'] ) ? $row['ref'] : '', isset( $row['index'] ) ? $row['index'] : 0,
                         isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array(),
                         isset( $row['cursor'] ) && is_array( $row['cursor'] ) ? $row['cursor'] : array() );
    }
}
