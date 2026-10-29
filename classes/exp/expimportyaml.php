<?php
/**
 * File containing the expImportYaml class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * YAML and TOML for data files and front matter, without a library and the same on every server (the PHP extension
 * yaml reads YAML 1.1, where y and n are booleans; this reads them as text, as YAML 1.2 does).
 *
 * YAML: block mappings and sequences nested by indentation (a sequence may stand at its key's indentation), "- key:
 * value" items, flow [a, b] and {a: 1}, single and double quoted strings, plain scalars (true/false, null/~, integers,
 * floats; dates stay text), | and > block scalars (with - and +), comments, a leading --- and the first document only.
 * Anchors, aliases and tags are not read (a tag is dropped, an alias stays text).
 *
 * TOML: key = value with strings, numbers, booleans, dates (as text), arrays and inline tables, dotted keys, [table]
 * and [[array of tables]] headers.
 */
class expImportYaml
{
    /** @var array list of array( indent, text ) */
    protected $lines = array();

    /** @var int */
    protected $pos = 0;

    /**
     * @param string $yaml
     * @return mixed the first document
     * @throws expImportSourceException when the text is no YAML this reads
     */
    public static function parse( $yaml )
    {
        $parser = new self();
        $parser->lines = self::lines( (string)$yaml );
        if ( !$parser->lines )
            return null;
        return $parser->node( $parser->lines[0][0] );
    }

    /** @return array the meaningful lines: array( indent, text without comment ), multi-line scalars kept */
    protected static function lines( $yaml )
    {
        $yaml = str_replace( array( "\r\n", "\r" ), "\n", $yaml );
        if ( strncmp( $yaml, "\xEF\xBB\xBF", 3 ) === 0 )
            $yaml = substr( $yaml, 3 );
        $out = array();
        $started = false;
        foreach ( explode( "\n", $yaml ) as $raw )
        {
            if ( preg_match( '/^(---|\.\.\.)(\s|$)/', $raw ) )
            {
                if ( $started || $raw[0] === '.' )
                    break;
                $started = true;
                continue;
            }
            if ( strpos( $raw, "\t" ) !== false && preg_match( '/^\t/', $raw ) )
                $raw = str_replace( "\t", '    ', $raw );
            $text = rtrim( $raw );
            $trim = ltrim( $text );
            // a blank line is kept (a block scalar needs it); a comment line is not
            if ( $trim !== '' && $trim[0] === '#' )
                continue;
            if ( $trim !== '' && $trim[0] !== '%' )
                $started = true;
            $out[] = array( $trim === '' ? -1 : strlen( $text ) - strlen( $trim ), $trim );
        }
        while ( $out && $out[0][0] === -1 )
            array_shift( $out );
        return $out;
    }

    /** @return int|null the indentation of the next meaningful line */
    protected function peekIndent()
    {
        for ( $i = $this->pos; $i < count( $this->lines ); $i++ )
            if ( $this->lines[$i][0] >= 0 )
                return $this->lines[$i][0];
        return null;
    }

    protected function skipBlank()
    {
        while ( $this->pos < count( $this->lines ) && $this->lines[$this->pos][0] < 0 )
            $this->pos++;
    }

    /** @return mixed the node starting at the current line, indented by $indent */
    protected function node( $indent )
    {
        $this->skipBlank();
        if ( $this->pos >= count( $this->lines ) )
            return null;
        $text = $this->lines[$this->pos][1];
        if ( $text === '-' || strpos( $text, '- ' ) === 0 )
            return $this->sequence( $indent );
        if ( self::keyValue( $text ) !== null )
            return $this->mapping( $indent );
        $this->pos++;
        return self::scalar( $text );
    }

    /** @return array */
    protected function sequence( $indent )
    {
        $out = array();
        while ( true )
        {
            $this->skipBlank();
            if ( $this->pos >= count( $this->lines ) )
                break;
            list( $lineIndent, $text ) = $this->lines[$this->pos];
            if ( $lineIndent !== $indent || ( $text !== '-' && strpos( $text, '- ' ) !== 0 ) )
                break;
            $rest = $text === '-' ? '' : ltrim( substr( $text, 2 ) );
            if ( $rest === '' )
            {
                $this->pos++;
                $next = $this->peekIndent();
                $out[] = $next !== null && $next > $indent ? $this->node( $next ) : null;
                continue;
            }
            // "- key: value" opens a mapping indented as the text after the dash
            $inner = $indent + ( strlen( $text ) - strlen( $rest ) );
            if ( self::keyValue( $rest ) !== null || $rest === '-' || strpos( $rest, '- ' ) === 0 )
            {
                $this->lines[$this->pos] = array( $inner, $rest );
                $out[] = $this->node( $inner );
                continue;
            }
            $this->pos++;
            $out[] = $this->value( $rest, $indent );
        }
        return $out;
    }

    /** @return array */
    protected function mapping( $indent )
    {
        $out = array();
        while ( true )
        {
            $this->skipBlank();
            if ( $this->pos >= count( $this->lines ) )
                break;
            list( $lineIndent, $text ) = $this->lines[$this->pos];
            if ( $lineIndent !== $indent )
            {
                if ( $lineIndent > $indent )
                    throw new expImportSourceException( 'the YAML is indented wrongly near "' . substr( $text, 0, 40 ) . '"' );
                break;
            }
            $kv = self::keyValue( $text );
            if ( $kv === null )
                break;
            list( $key, $value ) = $kv;
            $this->pos++;
            if ( $value === '' )
            {
                $next = $this->peekIndent();
                $this->skipBlank();
                $nextText = $this->pos < count( $this->lines ) ? $this->lines[$this->pos][1] : '';
                if ( $next !== null && ( $next > $indent || ( $next === $indent && ( $nextText === '-' || strpos( $nextText, '- ' ) === 0 ) ) ) )
                    $out[$key] = $this->node( $next );
                else
                    $out[$key] = null;
            }
            else
                $out[$key] = $this->value( $value, $indent );
        }
        return $out;
    }

    /** @return mixed a value written after "key:" or "- " (a block scalar reads the lines below it) */
    protected function value( $value, $indent )
    {
        $value = preg_replace( '/^!!?[\w\/:.-]*\s+/', '', $value );
        if ( preg_match( '/^([|>])([+-]?)\d*\s*$/', $value, $m ) )
            return $this->blockScalar( $m[1], $m[2], $indent );
        // a quoted or flow value may go on over the next lines
        if ( ( $value[0] === '"' && !preg_match( '/^"(?:[^"\\\\]|\\\\.)*"\s*$/s', $value ) )
             || ( $value[0] === '[' && substr_count( $value, '[' ) > substr_count( $value, ']' ) )
             || ( $value[0] === '{' && substr_count( $value, '{' ) > substr_count( $value, '}' ) ) )
        {
            while ( $this->pos < count( $this->lines ) )
            {
                $value .= ' ' . $this->lines[$this->pos++][1];
                if ( ( $value[0] === '"' && preg_match( '/^"(?:[^"\\\\]|\\\\.)*"\s*$/s', $value ) )
                     || ( $value[0] === '[' && substr_count( $value, '[' ) <= substr_count( $value, ']' ) )
                     || ( $value[0] === '{' && substr_count( $value, '{' ) <= substr_count( $value, '}' ) ) )
                    break;
            }
        }
        return self::scalar( $value );
    }

    /** @return string */
    protected function blockScalar( $style, $chomp, $indent )
    {
        $lines = array();
        $blockIndent = null;
        while ( $this->pos < count( $this->lines ) )
        {
            list( $lineIndent, $text ) = $this->lines[$this->pos];
            if ( $lineIndent >= 0 && $lineIndent <= $indent )
                break;
            if ( $lineIndent >= 0 && $blockIndent === null )
                $blockIndent = $lineIndent;
            $lines[] = $lineIndent < 0 ? '' : str_repeat( ' ', max( 0, $lineIndent - (int)$blockIndent ) ) . $text;
            $this->pos++;
        }
        $trailing = '';
        while ( $lines && end( $lines ) === '' )
        {
            array_pop( $lines );
            $trailing .= "\n";
        }
        if ( $style === '|' )
            $text = implode( "\n", $lines );
        else
        {
            $text = '';
            // folded: lines join with a space, an empty line is a line break; indented lines keep theirs
            foreach ( $lines as $i => $line )
            {
                if ( $line === '' )
                    $text .= "\n";
                elseif ( $i > 0 && $lines[$i - 1] !== '' )
                    $text .= ( $line[0] === ' ' || $lines[$i - 1][0] === ' ' ? "\n" : ' ' ) . $line;
                else
                    $text .= $line;
            }
        }
        if ( $chomp === '-' )
            return $text;
        return $text . ( $chomp === '+' ? "\n" . $trailing : "\n" );
    }

    /** @return array|null array( key, value text ) of a "key: value" line, null when it is none */
    public static function keyValue( $text )
    {
        if ( $text === '' || $text[0] === '[' || $text[0] === '{' || $text === '-' || strpos( $text, '- ' ) === 0 )
            return null;
        if ( $text[0] === '"' || $text[0] === "'" )
        {
            $q = $text[0];
            if ( !preg_match( $q === '"' ? '/^"((?:[^"\\\\]|\\\\.)*)"\s*:(?:\s+(.*))?$/' : "/^'((?:[^']|'')*)'\\s*:(?:\\s+(.*))?$/", $text, $m ) )
                return null;
            return array( $q === '"' ? stripcslashes( $m[1] ) : str_replace( "''", "'", $m[1] ), isset( $m[2] ) ? self::stripComment( $m[2] ) : '' );
        }
        if ( !preg_match( '/^([^#:][^:]*?|[^#:]*?:[^\s:][^:]*?)\s*:(?:\s+(.*))?$/', $text, $m ) )
            return null;
        return array( trim( $m[1] ), isset( $m[2] ) ? self::stripComment( $m[2] ) : '' );
    }

    /** @return string a value without a comment after it (" #...") that is not inside quotes */
    protected static function stripComment( $value )
    {
        $value = trim( $value );
        if ( $value === '' || $value[0] === '"' || $value[0] === "'" )
        {
            if ( $value !== '' && preg_match( $value[0] === '"' ? '/^("(?:[^"\\\\]|\\\\.)*")\s+#.*$/' : "/^('(?:[^']|'')*')\\s+#.*$/", $value, $m ) )
                return $m[1];
            return $value;
        }
        return trim( (string)preg_replace( '/\s+#.*$/', '', $value ) );
    }

    /**
     * A scalar or a flow collection.
     *
     * @param string $value
     * @return mixed
     */
    public static function scalar( $value )
    {
        $value = trim( (string)$value );
        if ( $value === '' || $value === '~' || strtolower( $value ) === 'null' )
            return null;
        if ( $value[0] === '[' && substr( $value, -1 ) === ']' )
            return array_map( array( __CLASS__, 'scalar' ), self::splitFlow( substr( $value, 1, -1 ) ) );
        if ( $value[0] === '{' && substr( $value, -1 ) === '}' )
        {
            $out = array();
            foreach ( self::splitFlow( substr( $value, 1, -1 ) ) as $part )
            {
                $kv = self::keyValue( $part );
                if ( $kv !== null )
                    $out[$kv[0]] = self::scalar( $kv[1] );
            }
            return $out;
        }
        if ( $value[0] === '"' && substr( $value, -1 ) === '"' && strlen( $value ) > 1 )
            return self::unescape( substr( $value, 1, -1 ) );
        if ( $value[0] === "'" && substr( $value, -1 ) === "'" && strlen( $value ) > 1 )
            return str_replace( "''", "'", substr( $value, 1, -1 ) );
        $lower = strtolower( $value );
        if ( $lower === 'true' )
            return true;
        if ( $lower === 'false' )
            return false;
        if ( preg_match( '/^[-+]?\d+$/', $value ) && strlen( ltrim( $value, '+-' ) ) < 19 )
            return (int)$value;
        if ( preg_match( '/^[-+]?(\d+\.\d*|\.\d+)([eE][-+]?\d+)?$/', $value ) )
            return (float)$value;
        return $value;
    }

    /** @return string[] the items of a flow collection's inside, split at top-level commas */
    protected static function splitFlow( $inner )
    {
        $items = array();
        $depth = 0;
        $quote = '';
        $current = '';
        $length = strlen( $inner );
        for ( $i = 0; $i < $length; $i++ )
        {
            $c = $inner[$i];
            if ( $quote !== '' )
            {
                $current .= $c;
                if ( $c === '\\' && $quote === '"' && $i + 1 < $length )
                    $current .= $inner[++$i];
                elseif ( $c === $quote )
                    $quote = '';
                continue;
            }
            if ( $c === '"' || $c === "'" )
                $quote = $c;
            elseif ( $c === '[' || $c === '{' )
                $depth++;
            elseif ( $c === ']' || $c === '}' )
                $depth--;
            elseif ( $c === ',' && $depth === 0 )
            {
                if ( trim( $current ) !== '' )
                    $items[] = trim( $current );
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if ( trim( $current ) !== '' )
            $items[] = trim( $current );
        return $items;
    }

    /** @return string a double quoted string's escapes read */
    protected static function unescape( $s )
    {
        return (string)preg_replace_callback( '/\\\\(u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2}|.)/', function ( $m )
        {
            $e = $m[1];
            if ( $e[0] === 'u' || $e[0] === 'x' )
                return mb_convert_encoding( pack( 'N', hexdec( substr( $e, 1 ) ) ), 'UTF-8', 'UCS-4BE' );
            $map = array( 'n' => "\n", 't' => "\t", 'r' => "\r", '0' => "\0", '"' => '"', '\\' => '\\', '/' => '/', ' ' => ' ', 'e' => "\x1B" );
            return isset( $map[$e] ) ? $map[$e] : $e;
        }, $s );
    }

    // ---- TOML ----------------------------------------------------------------------------------------------------

    /**
     * @param string $toml
     * @return array
     * @throws expImportSourceException
     */
    public static function toml( $toml )
    {
        $out = array();
        $target = &$out;
        $lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string)$toml ) );
        $count = count( $lines );
        for ( $n = 0; $n < $count; $n++ )
        {
            $line = trim( $lines[$n] );
            if ( $line === '' || $line[0] === '#' )
                continue;
            if ( preg_match( '/^\[\[\s*([^\]]+?)\s*\]\]$/', $line, $m ) )
            {
                unset( $target );
                $target = &self::path( $out, self::keys( $m[1] ), true );
                continue;
            }
            if ( preg_match( '/^\[\s*([^\]]+?)\s*\]$/', $line, $m ) )
            {
                unset( $target );
                $target = &self::path( $out, self::keys( $m[1] ), false );
                continue;
            }
            if ( !preg_match( '/^((?:"[^"]*"|\'[^\']*\'|[A-Za-z0-9_\-]+)(?:\s*\.\s*(?:"[^"]*"|\'[^\']*\'|[A-Za-z0-9_\-]+))*)\s*=\s*(.*)$/', $line, $m ) )
                throw new expImportSourceException( 'the TOML cannot be read at line ' . ( $n + 1 ) );
            $value = $m[2];
            // multi-line strings and arrays
            if ( preg_match( '/^("""|\'\'\')/', $value, $q ) && substr_count( $value, $q[1] ) < 2 )
                while ( $n + 1 < $count && substr_count( $value, $q[1] ) < 2 )
                    $value .= "\n" . $lines[++$n];
            elseif ( $value !== '' && $value[0] === '[' )
                while ( $n + 1 < $count && substr_count( $value, '[' ) > substr_count( $value, ']' ) )
                    $value .= ' ' . trim( $lines[++$n] );
            $keys = self::keys( $m[1] );
            $last = array_pop( $keys );
            $place = &self::path( $target, $keys, false );
            $place[$last] = self::tomlValue( trim( $value ) );
            unset( $place );
        }
        return $out;
    }

    /** @return string[] the parts of a dotted TOML key */
    protected static function keys( $key )
    {
        preg_match_all( '/"([^"]*)"|\'([^\']*)\'|([A-Za-z0-9_\-]+)/', $key, $m, PREG_SET_ORDER );
        return array_map( function ( $p ) { return isset( $p[3] ) && $p[3] !== '' ? $p[3] : ( isset( $p[2] ) && $p[2] !== '' ? $p[2] : $p[1] ); }, $m );
    }

    /** @return array a reference to the table at a path (a new element of the last array for [[...]]) */
    protected static function &path( array &$root, array $keys, $append )
    {
        $node = &$root;
        foreach ( $keys as $i => $key )
        {
            $isLast = $i === count( $keys ) - 1;
            if ( !isset( $node[$key] ) || !is_array( $node[$key] ) )
                $node[$key] = array();
            if ( $isLast && $append )
            {
                $node[$key][] = array();
                end( $node[$key] );
                $node = &$node[$key][key( $node[$key] )];
                return $node;
            }
            $node = &$node[$key];
            // a key naming an array of tables means its last table
            if ( !$isLast && $node && array_keys( $node ) === range( 0, count( $node ) - 1 ) && is_array( end( $node ) ) )
                $node = &$node[count( $node ) - 1];
        }
        return $node;
    }

    /** @return mixed */
    protected static function tomlValue( $value )
    {
        if ( $value === '' )
            return '';
        foreach ( array( '"""', "'''" ) as $q )
            if ( strpos( $value, $q ) === 0 )
            {
                $inner = ltrim( (string)substr( $value, 3, strrpos( $value, $q ) - 3 ), "\n" );
                return $q === '"""' ? self::unescape( $inner ) : $inner;
            }
        if ( $value[0] === '"' )
        {
            preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $m );
            return self::unescape( isset( $m[1] ) ? $m[1] : '' );
        }
        if ( $value[0] === "'" )
        {
            preg_match( "/^'([^']*)'/", $value, $m );
            return isset( $m[1] ) ? $m[1] : '';
        }
        $value = trim( (string)preg_replace( '/\s+#.*$/', '', $value ) );
        if ( $value[0] === '[' && substr( $value, -1 ) === ']' )
            return array_map( array( __CLASS__, 'tomlValue' ), self::splitFlow( substr( $value, 1, -1 ) ) );
        if ( $value[0] === '{' && substr( $value, -1 ) === '}' )
        {
            $out = array();
            foreach ( self::splitFlow( substr( $value, 1, -1 ) ) as $part )
                if ( preg_match( '/^([^=]+?)\s*=\s*(.*)$/', $part, $m ) )
                {
                    $keys = self::keys( $m[1] );
                    $out[end( $keys )] = self::tomlValue( trim( $m[2] ) );
                }
            return $out;
        }
        if ( $value === 'true' )
            return true;
        if ( $value === 'false' )
            return false;
        $plain = str_replace( '_', '', $value );
        if ( preg_match( '/^[-+]?\d+$/', $plain ) )
            return (int)$plain;
        if ( preg_match( '/^[-+]?\d+(\.\d+)?([eE][-+]?\d+)?$/', $plain ) )
            return (float)$plain;
        return $value;
    }
}
