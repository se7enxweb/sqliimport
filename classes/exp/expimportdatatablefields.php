<?php
/**
 * File containing the expImportDataTableFields class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The fields of a new data table, guessed from the sample of a source (the kernel's "Module table" wizard asks for them
 * through expImportDataTableFieldSource, Exponential 6.0.15). One field per column of the flattened records, in their
 * order: a column name (lower case, letters, digits and _), the source's name as label, a type of the wizard
 * (string, text, integer, float, boolean, date, datetime, json), a length for strings, whether every sampled record
 * has a value (required) and whether all values differ (unique, a candidate key), and the first value as a sample.
 * The wizard shows them for review; nothing is created here.
 */
class expImportDataTableFields
{
    /** Names a generated class or table uses itself; a source column of that name gets a prefix */
    const RESERVED = array( 'id', 'definition', 'attribute', 'class', 'table', 'select', 'from', 'where', 'order', 'group',
                            'key', 'index', 'primary', 'user', 'default' );

    /** String lengths offered, the smallest that holds the longest sampled value */
    const LENGTHS = array( 32, 64, 128, 255 );

    /**
     * @param array $records list of flat records (field => scalar or array value), e.g. expImportPreview::sample()'s 'flat'
     * @param int $maxFields
     * @return array list of array( name, label, type, length, required, unique, sample )
     */
    public static function infer( array $records, $maxFields = 200 )
    {
        $columns = array();
        foreach ( $records as $record )
            foreach ( (array)$record as $field => $value )
                if ( !isset( $columns[(string)$field] ) && count( $columns ) < $maxFields )
                    $columns[(string)$field] = true;
        $out = array();
        $used = array();
        foreach ( array_keys( $columns ) as $field )
        {
            $values = array();
            foreach ( $records as $record )
                $values[] = array_key_exists( $field, (array)$record ) ? $record[$field] : null;
            $type = self::typeOf( $values );
            $filled = array_values( array_filter( $values, array( __CLASS__, 'filled' ) ) );
            $strings = array_map( array( __CLASS__, 'text' ), $filled );
            $max = 0;
            foreach ( $strings as $s )
                $max = max( $max, function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s ) );
            $out[] = array( 'name' => self::columnName( $field, $used ),
                            'label' => (string)$field,
                            'type' => $type,
                            'length' => $type === 'string' ? self::length( $max ) : 0,
                            'required' => count( $values ) > 0 && count( $filled ) === count( $values ),
                            'unique' => count( $filled ) >= 2 && count( array_unique( $strings ) ) === count( $strings ),
                            'sample' => $strings ? self::cut( $strings[0], 80 ) : '' );
        }
        return $out;
    }

    /**
     * The type of the wizard that holds every value: the narrowest of integer, float, boolean, date, datetime and json,
     * else string, or text for long or multi-line values. Empty values do not count; no value at all is a string.
     *
     * @param array $values
     * @return string
     */
    public static function typeOf( array $values )
    {
        $filled = array_values( array_filter( $values, array( __CLASS__, 'filled' ) ) );
        if ( !$filled )
            return 'string';
        $all = function ( $test ) use ( $filled )
        {
            foreach ( $filled as $v )
                if ( !$test( $v ) )
                    return false;
            return true;
        };
        if ( $all( function ( $v ) { return is_array( $v ) || is_object( $v ); } ) )
            return 'json';
        if ( $all( function ( $v ) { return is_bool( $v ) || ( is_string( $v ) && in_array( strtolower( trim( $v ) ), array( 'true', 'false', 'yes', 'no' ), true ) ); } ) )
            return 'boolean';
        if ( $all( function ( $v ) { return is_int( $v ) || ( is_string( $v ) && preg_match( '/^-?(0|[1-9][0-9]{0,17})$/', trim( $v ) ) ); } ) )
            return 'integer';
        if ( $all( function ( $v ) { return is_int( $v ) || is_float( $v ) || ( is_string( $v ) && preg_match( '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][-+]?[0-9]+)?$/', trim( $v ) ) ); } ) )
            return 'float';
        if ( $all( function ( $v ) { return is_string( $v ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $v ) ) && self::validDate( trim( $v ) ); } ) )
            return 'date';
        if ( $all( function ( $v ) { return is_string( $v ) && preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', trim( $v ) )
                                            && self::validDate( substr( trim( $v ), 0, 10 ) ); } ) )
            return 'datetime';
        if ( $all( function ( $v ) { return is_string( $v ) && preg_match( '/^\s*[\[{]/', $v ) && json_decode( $v ) !== null; } ) )
            return 'json';
        foreach ( $filled as $v )
        {
            $s = self::text( $v );
            if ( strpos( $s, "\n" ) !== false || ( function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s ) ) > 255 )
                return 'text';
        }
        return 'string';
    }

    /**
     * A column name for a source field: ASCII lower case, letters, digits and _, starting with a letter, at most 60
     * characters, not a reserved word, unique among $used (which it adds to).
     *
     * @param string $field
     * @param array $used name => true
     * @return string
     */
    public static function columnName( $field, array &$used )
    {
        $name = (string)$field;
        if ( function_exists( 'iconv' ) )
        {
            $ascii = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $name );
            if ( is_string( $ascii ) && $ascii !== '' )
                $name = $ascii;
        }
        $name = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $name ) );
        $name = trim( preg_replace( '/[^a-z0-9]+/', '_', $name ), '_' );
        if ( $name === '' )
            $name = 'field';
        if ( !preg_match( '/^[a-z]/', $name ) )
            $name = 'f_' . $name;
        if ( in_array( $name, self::RESERVED, true ) )
            $name = 'source_' . $name;
        $name = substr( $name, 0, 60 );
        $base = $name;
        for ( $i = 2; isset( $used[$name] ); $i++ )
            $name = substr( $base, 0, 60 - strlen( '_' . $i ) ) . '_' . $i;
        $used[$name] = true;
        return $name;
    }

    /** @return int the smallest offered length that holds $max characters */
    public static function length( $max )
    {
        foreach ( self::LENGTHS as $length )
            if ( $max <= $length )
                return $length;
        return 255;
    }

    /** @param mixed $v */
    public static function filled( $v )
    {
        return !( $v === null || $v === '' || ( is_string( $v ) && trim( $v ) === '' ) || $v === array() );
    }

    /** @param mixed $v */
    public static function text( $v )
    {
        if ( is_bool( $v ) )
            return $v ? 'true' : 'false';
        if ( is_array( $v ) || is_object( $v ) )
            return (string)json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return (string)$v;
    }

    protected static function validDate( $ymd )
    {
        $p = explode( '-', $ymd );
        return count( $p ) === 3 && checkdate( (int)$p[1], (int)$p[2], (int)$p[0] );
    }

    protected static function cut( $s, $n )
    {
        if ( function_exists( 'mb_substr' ) )
            return mb_strlen( $s, 'UTF-8' ) > $n ? mb_substr( $s, 0, $n - 1, 'UTF-8' ) . '…' : $s;
        return strlen( $s ) > $n ? substr( $s, 0, $n - 3 ) . '...' : $s;
    }
}
