<?php
/**
 * File containing the expImportContentLineSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The common part of the text formats made of "content lines" (iCalendar, RFC 5545, and vCard, RFC 6350 and 2426):
 *
 *   NAME;PARAM=value;PARAM="quoted, value":the value
 *
 * A long line is folded: a line that starts with a space or a tab continues the one before it. Text values escape
 * the backslash, the semicolon, the comma and the line break (\\ \; \, \n). Components are framed by BEGIN:x and END:x.
 *
 * The file is read as a stream, line by line. A record's cursor is the byte position after its END line, so a chunk
 * of a run continues there without reading the file from the beginning.
 */
abstract class expImportContentLineSource extends expImportAbstractSource
{
    /** The longest logical (unfolded) line read; a longer one is an error rather than a memory problem. */
    const MAX_LINE_BYTES = 4194304;

    /** @return resource the file, opened, after a byte order mark */
    protected function openFile()
    {
        $fp = @fopen( $this->file(), 'rb' );
        if ( !$fp )
            throw new expImportSourceException( 'the file cannot be opened' );
        if ( fread( $fp, 3 ) !== "\xEF\xBB\xBF" )
            rewind( $fp );
        return $fp;
    }

    /**
     * The logical lines of a file from where it stands: folded lines joined.
     *
     * @param resource $fp
     * @return Generator of array( string line, int the byte position after it )
     */
    protected static function logicalLines( $fp )
    {
        $pending = null;
        $pendingEnd = 0;
        while ( ( $raw = fgets( $fp ) ) !== false )
        {
            $end = ftell( $fp );
            $line = rtrim( $raw, "\r\n" );
            if ( $pending !== null && $line !== '' && ( $line[0] === ' ' || $line[0] === "\t" ) )
            {
                $pending .= substr( $line, 1 );
                $pendingEnd = $end;
                if ( strlen( $pending ) > self::MAX_LINE_BYTES )
                    throw new expImportSourceException( 'a line of the file is longer than ' . self::MAX_LINE_BYTES . ' bytes' );
                continue;
            }
            if ( $pending !== null )
                yield array( $pending, $pendingEnd );
            $pending = $line;
            $pendingEnd = $end;
        }
        if ( $pending !== null )
            yield array( $pending, $pendingEnd );
    }

    /**
     * One content line in its parts. The name is upper case and without a vCard group ("item1.EMAIL" is EMAIL);
     * parameter names are upper case, their values unquoted (a list stays as written, "WORK,VOICE").
     *
     * @param string $line
     * @return array|null array( 'name', 'params' => name => value, 'value' (raw, still escaped) ), null for no line
     */
    public static function parseLine( $line )
    {
        $line = (string)$line;
        $length = strlen( $line );
        $quoted = false;
        $colon = -1;
        for ( $i = 0; $i < $length; $i++ )
        {
            $c = $line[$i];
            if ( $c === '"' )
                $quoted = !$quoted;
            elseif ( $c === ':' && !$quoted )
            {
                $colon = $i;
                break;
            }
        }
        if ( $colon <= 0 )
            return null;
        $head = substr( $line, 0, $colon );
        $value = (string)substr( $line, $colon + 1 );
        $parts = array();
        $current = '';
        $quoted = false;
        for ( $i = 0, $n = strlen( $head ); $i < $n; $i++ )
        {
            $c = $head[$i];
            if ( $c === '"' )
                $quoted = !$quoted;
            if ( $c === ';' && !$quoted )
            {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $parts[] = $current;
        $name = strtoupper( trim( (string)array_shift( $parts ) ) );
        if ( ( $dot = strrpos( $name, '.' ) ) !== false )
            $name = substr( $name, $dot + 1 );
        if ( $name === '' || !preg_match( '/^[A-Z0-9-]+$/', $name ) )
            return null;
        $params = array();
        foreach ( $parts as $part )
        {
            $eq = strpos( $part, '=' );
            // vCard 2.1 writes bare types: TEL;WORK;VOICE:...
            $pname = strtoupper( trim( $eq === false ? 'TYPE' : substr( $part, 0, $eq ) ) );
            $pvalue = $eq === false ? trim( $part ) : trim( substr( $part, $eq + 1 ) );
            $pvalue = str_replace( '"', '', $pvalue );
            $params[$pname] = isset( $params[$pname] ) && $params[$pname] !== '' ? $params[$pname] . ',' . $pvalue : $pvalue;
        }
        return array( 'name' => $name, 'params' => $params, 'value' => $value );
    }

    /** @return string a text value unescaped: \\ \; \, \n and \N */
    public static function unescape( $value )
    {
        return strtr( (string)$value, array( '\\\\' => '\\', '\\;' => ';', '\\,' => ',', '\\n' => "\n", '\\N' => "\n" ) );
    }

    /**
     * A value split at a separator that is not escaped, each part unescaped (structured values such as N and ADR use
     * ";", lists such as CATEGORIES ",").
     *
     * @param string $value
     * @param string $separator
     * @return string[]
     */
    public static function split( $value, $separator )
    {
        $out = array();
        $current = '';
        $value = (string)$value;
        for ( $i = 0, $n = strlen( $value ); $i < $n; $i++ )
        {
            $c = $value[$i];
            if ( $c === '\\' && $i + 1 < $n )
            {
                $current .= $c . $value[++$i];
                continue;
            }
            if ( $c === $separator )
            {
                $out[] = self::unescape( $current );
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $out[] = self::unescape( $current );
        return $out;
    }

    /**
     * The text of a property: unescaped, decoded from quoted-printable (vCard 2.1) and converted to UTF-8.
     *
     * @param array $property a parsed line
     * @return string
     */
    protected static function text( array $property )
    {
        return self::unescape( self::decoded( $property ) );
    }

    /** @return string the value with its ENCODING and CHARSET applied, still escaped */
    protected static function decoded( array $property )
    {
        $value = $property['value'];
        $params = $property['params'];
        if ( isset( $params['ENCODING'] ) && strtoupper( $params['ENCODING'] ) === 'QUOTED-PRINTABLE' )
            $value = quoted_printable_decode( $value );
        $charset = isset( $params['CHARSET'] ) ? $params['CHARSET'] : 'auto';
        return self::toUtf8( $value, $charset );
    }

    /** @return string[] the TYPE values of a property, lower case ("work", "voice") */
    protected static function types( array $property )
    {
        $types = isset( $property['params']['TYPE'] ) ? $property['params']['TYPE'] : '';
        return array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $types ) ) ), 'strlen' ) );
    }

    /**
     * The components of a file, framed by BEGIN:<name> and END:<name>, as lists of parsed lines; nested components
     * (an alarm inside an event) are left out of the outer one. Lines outside the wanted components are passed to
     * $outside (the calendar's own properties).
     *
     * @param array $cursor where to continue ('pos')
     * @param string[] $names the components wanted, upper case
     * @param callable|null $outside function ( array $property ) for the lines outside a component
     * @return Generator of array( string name, array lines, int the byte position after END )
     */
    protected function components( array $cursor, array $names, $outside = null )
    {
        $fp = $this->openFile();
        try
        {
            if ( isset( $cursor['pos'] ) && (int)$cursor['pos'] > 0 )
                fseek( $fp, (int)$cursor['pos'] );
            $current = null;
            $lines = array();
            $nested = array();
            foreach ( self::logicalLines( $fp ) as $item )
            {
                list( $line, $end ) = $item;
                if ( trim( $line ) === '' )
                    continue;
                $property = self::parseLine( $line );
                if ( $property === null )
                    continue;
                $value = strtoupper( trim( $property['value'] ) );
                if ( $property['name'] === 'BEGIN' )
                {
                    if ( $current === null && in_array( $value, $names, true ) )
                    {
                        $current = $value;
                        $lines = array();
                        $nested = array();
                    }
                    elseif ( $current !== null )
                        $nested[] = $value;
                    continue;
                }
                if ( $property['name'] === 'END' )
                {
                    if ( $nested )
                        array_pop( $nested );
                    elseif ( $current !== null && $value === $current )
                    {
                        yield array( $current, $lines, $end );
                        $current = null;
                    }
                    continue;
                }
                if ( $current === null )
                {
                    if ( $outside )
                        $outside( $property );
                    continue;
                }
                if ( !$nested )
                    $lines[] = $property;
            }
        }
        finally
        {
            fclose( $fp );
        }
    }

    /**
     * The properties of a component by name: name => list of parsed lines.
     *
     * @param array $lines
     * @return array
     */
    protected static function byName( array $lines )
    {
        $out = array();
        foreach ( $lines as $line )
            $out[$line['name']][] = $line;
        return $out;
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }
}
