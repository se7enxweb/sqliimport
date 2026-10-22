<?php
/**
 * File containing the expImportFrontMatter class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Front matter of Markdown files (Jekyll, Hugo, Obsidian, Eleventy ...): YAML between "---" lines or TOML between
 * "+++" lines at the start of the file. Without the PHP extension yaml, the YAML that front matter uses is read:
 * key: value, quoted strings, numbers, true/false/null, dates (kept as text), [a, b] and "- item" lists, one level of
 * nested keys, and "|" / ">" blocks. TOML: key = value, strings, numbers, booleans, arrays, [table] headers.
 */
class expImportFrontMatter
{
    /**
     * Splits a text into its front matter and its body.
     *
     * @param string $text
     * @return array ( array front matter, string body, string format: yaml, toml or '' )
     */
    public static function split( $text )
    {
        $text = (string)$text;
        if ( strncmp( $text, "\xEF\xBB\xBF", 3 ) === 0 )
            $text = substr( $text, 3 );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        if ( preg_match( '/\A---[ \t]*\n(.*?)\n(?:---|\.\.\.)[ \t]*(?:\n|\z)/s', $text, $m ) )
            return array( self::yaml( $m[1] ), (string)substr( $text, strlen( $m[0] ) ), 'yaml' );
        if ( preg_match( '/\A\+\+\+[ \t]*\n(.*?)\n\+\+\+[ \t]*(?:\n|\z)/s', $text, $m ) )
            return array( self::toml( $m[1] ), (string)substr( $text, strlen( $m[0] ) ), 'toml' );
        return array( array(), $text, '' );
    }

    /**
     * The YAML of front matter as an array.
     *
     * @param string $yaml
     * @return array
     */
    public static function yaml( $yaml )
    {
        if ( function_exists( 'yaml_parse' ) )
        {
            $data = @yaml_parse( $yaml );
            if ( is_array( $data ) )
                return self::plain( $data );
        }
        $lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string)$yaml ) );
        $out = array();
        $count = count( $lines );
        for ( $i = 0; $i < $count; $i++ )
        {
            $line = rtrim( $lines[$i] );
            if ( $line === '' || ltrim( $line )[0] === '#' || !preg_match( '/^([A-Za-z0-9_][A-Za-z0-9_ .\-]*?)\s*:(?:\s+(.*))?$/', $line, $m ) )
                continue;
            $key = trim( $m[1] );
            $value = isset( $m[2] ) ? trim( $m[2] ) : '';
            if ( $value === '|' || $value === '>' || $value === '|-' || $value === '>-' )
            {
                $block = array();
                while ( $i + 1 < $count && ( trim( $lines[$i + 1] ) === '' || preg_match( '/^\s+/', $lines[$i + 1] ) ) )
                    $block[] = trim( $lines[++$i] );
                $out[$key] = $value[0] === '|' ? rtrim( implode( "\n", $block ) ) : trim( preg_replace( '/\s+/', ' ', implode( ' ', $block ) ) );
                continue;
            }
            if ( $value !== '' )
            {
                $out[$key] = self::scalar( $value );
                continue;
            }
            // a list ("- item") or a map of one level below the key
            $list = array();
            $map = array();
            while ( $i + 1 < $count && ( trim( $lines[$i + 1] ) === '' || preg_match( '/^(\s+|-\s)/', $lines[$i + 1] ) ) )
            {
                $next = trim( $lines[++$i] );
                if ( $next === '' )
                    continue;
                if ( strpos( $next, '- ' ) === 0 || $next === '-' )
                    $list[] = self::scalar( trim( substr( $next, 1 ) ) );
                elseif ( preg_match( '/^([^:]+):\s*(.*)$/', $next, $n ) )
                    $map[trim( $n[1] )] = self::scalar( trim( $n[2] ) );
            }
            $out[$key] = $list ?: ( $map ?: '' );
        }
        return $out;
    }

    /** @return mixed a YAML scalar or flow list */
    protected static function scalar( $value )
    {
        $value = trim( (string)$value );
        if ( $value === '' || $value === '~' || strtolower( $value ) === 'null' )
            return '';
        if ( $value[0] === '[' && substr( $value, -1 ) === ']' )
        {
            $inner = trim( substr( $value, 1, -1 ) );
            if ( $inner === '' )
                return array();
            preg_match_all( '/\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\']|\'\')*\'|[^,]+)\s*(?:,|$)/', $inner, $m );
            return array_map( array( __CLASS__, 'scalar' ), $m[1] );
        }
        if ( $value[0] === '"' && substr( $value, -1 ) === '"' && strlen( $value ) > 1 )
            return stripcslashes( substr( $value, 1, -1 ) );
        if ( $value[0] === "'" && substr( $value, -1 ) === "'" && strlen( $value ) > 1 )
            return str_replace( "''", "'", substr( $value, 1, -1 ) );
        // a comment after a plain value
        $value = trim( (string)preg_replace( '/\s+#.*$/', '', $value ) );
        $lower = strtolower( $value );
        if ( in_array( $lower, array( 'true', 'yes', 'on' ), true ) )
            return true;
        if ( in_array( $lower, array( 'false', 'no', 'off' ), true ) )
            return false;
        if ( preg_match( '/^-?\d+$/', $value ) )
            return (int)$value;
        if ( preg_match( '/^-?\d+\.\d+$/', $value ) )
            return (float)$value;
        return $value;
    }

    /**
     * The TOML of front matter as an array (tables become nested arrays).
     *
     * @param string $toml
     * @return array
     */
    public static function toml( $toml )
    {
        $out = array();
        $table = null;
        foreach ( explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string)$toml ) ) as $line )
        {
            $line = trim( $line );
            if ( $line === '' || $line[0] === '#' )
                continue;
            if ( preg_match( '/^\[([A-Za-z0-9_.\-]+)\]$/', $line, $m ) )
            {
                $table = $m[1];
                if ( !isset( $out[$table] ) || !is_array( $out[$table] ) )
                    $out[$table] = array();
                continue;
            }
            if ( !preg_match( '/^([A-Za-z0-9_\-]+|"[^"]+")\s*=\s*(.*)$/', $line, $m ) )
                continue;
            $key = trim( $m[1], '"' );
            $value = self::tomlValue( trim( $m[2] ) );
            if ( $table !== null )
                $out[$table][$key] = $value;
            else
                $out[$key] = $value;
        }
        return $out;
    }

    /** @return mixed */
    protected static function tomlValue( $value )
    {
        if ( $value === '' )
            return '';
        if ( $value[0] === '[' && substr( $value, -1 ) === ']' )
            return self::scalar( $value );
        if ( $value[0] === '"' )
        {
            $end = strrpos( $value, '"' );
            return stripcslashes( substr( $value, 1, $end > 0 ? $end - 1 : null ) );
        }
        if ( $value[0] === "'" )
        {
            $end = strrpos( $value, "'" );
            return substr( $value, 1, $end > 0 ? $end - 1 : null );
        }
        return self::scalar( $value );
    }

    /** @return array values of yaml_parse as strings, numbers, booleans and arrays (dates as text) */
    protected static function plain( array $data )
    {
        foreach ( $data as $key => $value )
        {
            if ( is_array( $value ) )
                $data[$key] = self::plain( $value );
            elseif ( $value instanceof DateTimeInterface )
                $data[$key] = $value->format( 'c' );
            elseif ( is_object( $value ) )
                $data[$key] = (string)json_encode( $value );
        }
        return $data;
    }
}
