<?php
/**
 * File containing the expImportOptionSchema class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Typed options. A source, a handler or a transform declares its options as a schema, and every value that reaches it
 * went through validate() on the server: the admin form is generated from the same schema, so the browser never is
 * the only check (the idea of upstream PR #6, done with a server-side validation).
 *
 * A schema is an array name => definition:
 *   type      string (one line), text (several lines), int, float, bool, enum, path (a server path, checked against
 *             an allowlist by the caller), url (http or https), node (a node ID), class (a class identifier),
 *             list (strings, one per line or an array), json (an array or a JSON text), secret (a reference to a
 *             secret in the settings, never the secret itself)
 *   label     the name in the form
 *   help      one line under the field
 *   required  true: an empty value is an error
 *   default   the value when nothing is given
 *   values    enum: value => label
 *   min, max  int and float: the bounds; string: the length
 *   pattern   string: a regular expression the value must match
 */
class expImportOptionSchema
{
    const TYPES = array( 'string', 'text', 'int', 'float', 'bool', 'enum', 'path', 'url', 'node', 'class', 'list', 'json', 'secret' );

    /**
     * Checks a schema itself (for registries and tests).
     *
     * @param array $schema
     * @return string[] errors
     */
    public static function check( array $schema )
    {
        $errors = array();
        foreach ( $schema as $name => $def )
        {
            if ( !is_string( $name ) || !preg_match( '/^[a-z][a-z0-9_]*$/', $name ) )
                $errors[] = "option name '$name' must be lower case letters, digits and _";
            if ( !is_array( $def ) )
            {
                $errors[] = "$name: the definition must be an array";
                continue;
            }
            $type = isset( $def['type'] ) ? $def['type'] : 'string';
            if ( !in_array( $type, self::TYPES, true ) )
                $errors[] = "$name: unknown type '$type'";
            if ( $type === 'enum' && ( !isset( $def['values'] ) || !is_array( $def['values'] ) || !$def['values'] ) )
                $errors[] = "$name: an enum needs values";
        }
        return $errors;
    }

    /**
     * Validates and normalises input against a schema. Unknown keys are dropped.
     *
     * @param array $schema
     * @param array $input
     * @return array ( array normalised values, array name => error )
     */
    public static function validate( array $schema, array $input )
    {
        $values = array();
        $errors = array();
        foreach ( $schema as $name => $def )
        {
            $type = isset( $def['type'] ) ? $def['type'] : 'string';
            $label = isset( $def['label'] ) ? $def['label'] : $name;
            $given = array_key_exists( $name, $input ) ? $input[$name] : null;
            $empty = $given === null || $given === '' || ( is_array( $given ) && !$given );
            if ( $empty && $type === 'bool' && array_key_exists( $name, $input ) )
                $empty = false;
            if ( $empty )
            {
                if ( !empty( $def['required'] ) )
                {
                    $errors[$name] = "$label is required";
                    continue;
                }
                $values[$name] = array_key_exists( 'default', $def ) ? $def['default'] : self::emptyValue( $type );
                continue;
            }
            list( $value, $error ) = self::convert( $type, $given, $def );
            if ( $error !== null )
                $errors[$name] = "$label: $error";
            else
                $values[$name] = $value;
        }
        return array( $values, $errors );
    }

    /** @return mixed the value of a type when nothing is given and there is no default */
    public static function emptyValue( $type )
    {
        switch ( $type )
        {
            case 'int':
            case 'node':
                return 0;
            case 'float':
                return 0.0;
            case 'bool':
                return false;
            case 'list':
            case 'json':
                return array();
            default:
                return '';
        }
    }

    /**
     * @return array ( mixed value, string|null error )
     */
    protected static function convert( $type, $given, array $def )
    {
        switch ( $type )
        {
            case 'int':
            case 'node':
                if ( is_bool( $given ) || !preg_match( '/^-?\d+$/', trim( (string)$given ) ) )
                    return array( null, 'a whole number is expected' );
                $value = (int)trim( (string)$given );
                if ( $type === 'node' && $value < 1 )
                    return array( null, 'a node ID is expected' );
                return self::bounds( $value, $def );
            case 'float':
                $text = str_replace( ',', '.', trim( (string)$given ) );
                if ( !is_numeric( $text ) )
                    return array( null, 'a number is expected' );
                return self::bounds( (float)$text, $def );
            case 'bool':
                if ( is_bool( $given ) )
                    return array( $given, null );
                $text = strtolower( trim( (string)$given ) );
                if ( in_array( $text, array( '1', 'true', 'yes', 'on', 'enabled' ), true ) )
                    return array( true, null );
                if ( in_array( $text, array( '0', 'false', 'no', 'off', 'disabled', '' ), true ) )
                    return array( false, null );
                return array( null, 'yes or no is expected' );
            case 'enum':
                $text = (string)$given;
                if ( !array_key_exists( $text, $def['values'] ) )
                    return array( null, 'one of ' . implode( ', ', array_keys( $def['values'] ) ) . ' is expected' );
                return array( $text, null );
            case 'url':
                $text = trim( (string)$given );
                if ( !preg_match( '#^https?://[^\s/$.?\#][^\s]*$#i', $text ) )
                    return array( null, 'an http or https address is expected' );
                return array( $text, null );
            case 'path':
                $text = trim( (string)$given );
                if ( strpos( $text, "\0" ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $text ) )
                    return array( null, 'a path without ".." is expected' );
                return array( $text, null );
            case 'class':
                $text = trim( (string)$given );
                if ( !preg_match( '/^[a-z0-9_]+$/i', $text ) )
                    return array( null, 'a class identifier is expected' );
                return array( $text, null );
            case 'list':
                $items = is_array( $given ) ? $given : preg_split( '/\r\n|\r|\n/', (string)$given );
                $items = array_values( array_filter( array_map( function ( $item ) { return trim( (string)$item ); }, $items ), 'strlen' ) );
                return array( $items, null );
            case 'json':
                if ( is_array( $given ) )
                    return array( $given, null );
                $data = json_decode( (string)$given, true );
                if ( !is_array( $data ) )
                    return array( null, 'a JSON object or list is expected' );
                return array( $data, null );
            case 'secret':
                $text = trim( (string)$given );
                if ( !preg_match( '/^[A-Za-z0-9_.:-]+$/', $text ) )
                    return array( null, 'the name of a secret in the settings is expected, never the secret itself' );
                return array( $text, null );
            case 'text':
                return array( str_replace( array( "\r\n", "\r" ), "\n", (string)$given ), null );
            default:
                // raw: spaces at the ends count (a prefix, a separator, a text to replace)
                $text = !empty( $def['raw'] ) ? (string)$given : trim( (string)$given );
                if ( strpos( $text, "\n" ) !== false )
                    return array( null, 'one line is expected' );
                if ( isset( $def['pattern'] ) && !preg_match( $def['pattern'], $text ) )
                    return array( null, 'the value has not the expected form' );
                return self::bounds( $text, $def );
        }
    }

    /** @return array ( mixed, string|null ) */
    protected static function bounds( $value, array $def )
    {
        $measure = is_string( $value ) ? ( function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value ) ) : $value;
        if ( isset( $def['min'] ) && $measure < $def['min'] )
            return array( null, is_string( $value ) ? "at least {$def['min']} characters" : "at least {$def['min']}" );
        if ( isset( $def['max'] ) && $measure > $def['max'] )
            return array( null, is_string( $value ) ? "at most {$def['max']} characters" : "at most {$def['max']}" );
        return array( $value, null );
    }

    /**
     * Reads the k=v text of sqliimport 1.x options (one per line, or "|" separated) into an array. Only the first "="
     * separates, so a value may contain "=" (upstream PR #7).
     *
     * @param string $text
     * @return array
     */
    public static function fromLegacyText( $text )
    {
        $out = array();
        foreach ( preg_split( '/\r\n|\r|\n|\|/', (string)$text ) as $line )
        {
            $line = trim( $line );
            if ( $line === '' || strpos( $line, '=' ) === false )
                continue;
            list( $key, $value ) = explode( '=', $line, 2 );
            $key = trim( $key );
            if ( $key !== '' )
                $out[$key] = trim( $value );
        }
        return $out;
    }
}
