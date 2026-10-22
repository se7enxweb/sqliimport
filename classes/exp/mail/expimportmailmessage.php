<?php
/**
 * File containing the expImportMailMessage class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * An e-mail message (RFC 5322 with MIME) as a record: the message ID, sender and recipients, the subject and the
 * names of the headers decoded to UTF-8 (RFC 2047 encoded words, RFC 2231 parameters), the date in ISO 8601, the
 * text and HTML bodies (quoted-printable and base64 decoded, converted from their charset) and the list of the
 * attachments with name, type and size. The content of an attachment is never kept.
 */
class expImportMailMessage
{
    /** The deepest nesting of multipart parts that is read. */
    const MAX_DEPTH = 12;

    /**
     * The fields of a message.
     *
     * @param string $raw the whole message as the server sends it
     * @return array the keys message_id, in_reply_to, from (name, address), to, cc, reply_to (lists of name, address),
     *               subject, date (ISO 8601, '' when it has none), text, html, summary (the first paragraph of the text),
     *               body (the HTML, or the text as HTML paragraphs), attachments (list of name, mime, size),
     *               attachment_count, size
     */
    public static function parse( $raw )
    {
        $raw = (string)$raw;
        list( $headers, $body ) = self::split( $raw );
        $parts = array( 'text' => '', 'html' => '', 'attachments' => array() );
        self::walk( $headers, $body, $parts, 0 );
        $text = trim( $parts['text'] );
        $html = trim( $parts['html'] );
        if ( $text === '' && $html !== '' )
            $text = self::htmlToText( $html );
        $from = self::addresses( self::header( $headers, 'from' ) );
        $date = self::date( self::header( $headers, 'date' ) );
        $id = trim( self::header( $headers, 'message-id' ), " <>\t" );
        if ( $id === '' )
            $id = 'sha1-' . sha1( self::header( $headers, 'from' ) . "\n" . self::header( $headers, 'date' ) . "\n" . self::header( $headers, 'subject' ) . "\n" . $text );
        return array(
            'message_id' => $id,
            'in_reply_to' => trim( self::header( $headers, 'in-reply-to' ), " <>\t" ),
            'from' => $from ? $from[0] : array( 'name' => '', 'address' => '' ),
            'to' => self::addresses( self::header( $headers, 'to' ) ),
            'cc' => self::addresses( self::header( $headers, 'cc' ) ),
            'reply_to' => self::addresses( self::header( $headers, 'reply-to' ) ),
            'subject' => self::decodeHeader( self::header( $headers, 'subject' ) ),
            'date' => $date,
            'text' => $text,
            'html' => $html,
            'summary' => self::firstParagraph( $text ),
            'body' => $html !== '' ? $html : self::textToHtml( $text ),
            'attachments' => $parts['attachments'],
            'attachment_count' => count( $parts['attachments'] ),
            'size' => strlen( $raw ),
        );
    }

    /**
     * The headers and the body of a message or a part.
     *
     * @param string $raw
     * @return array the list ( array name (lower case) => list of unfolded values, string body )
     */
    public static function split( $raw )
    {
        $raw = (string)$raw;
        if ( preg_match( '/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE ) )
        {
            $head = substr( $raw, 0, $m[0][1] );
            $body = (string)substr( $raw, $m[0][1] + strlen( $m[0][0] ) );
        }
        else
        {
            $head = $raw;
            $body = '';
        }
        $headers = array();
        $head = preg_replace( '/\r?\n[ \t]+/', ' ', str_replace( "\r\n", "\n", $head ) );
        foreach ( explode( "\n", (string)$head ) as $line )
        {
            $colon = strpos( $line, ':' );
            if ( $colon === false || $colon === 0 )
                continue;
            $name = strtolower( trim( substr( $line, 0, $colon ) ) );
            $headers[$name][] = trim( substr( $line, $colon + 1 ) );
        }
        return array( $headers, $body );
    }

    /** @return string the first value of a header, '' when there is none */
    protected static function header( array $headers, $name )
    {
        return isset( $headers[$name][0] ) ? (string)$headers[$name][0] : '';
    }

    /**
     * A header value decoded to UTF-8: RFC 2047 encoded words (=?charset?B|Q?...?=), and 8-bit text that is not
     * UTF-8 taken as Windows-1252.
     *
     * @param string $value
     * @return string
     */
    public static function decodeHeader( $value )
    {
        $value = trim( (string)$value );
        if ( $value === '' )
            return '';
        if ( strpos( $value, '=?' ) === false )
            return expImportAbstractSource::toUtf8( $value );
        // whitespace between two encoded words is not part of the text (RFC 2047 6.2)
        $value = preg_replace( '/(=\?[^?\s]+\?[BbQq]\?[^?\s]*\?=)\s+(?==\?)/', '$1', $value );
        $out = preg_replace_callback( '/=\?([^?\s]+)\?([BbQq])\?([^?\s]*)\?=/', function ( $m )
        {
            $charset = preg_replace( '/\*.*$/', '', $m[1] );
            $text = strtoupper( $m[2] ) === 'B' ? (string)base64_decode( $m[3] )
                  : quoted_printable_decode( str_replace( '_', ' ', $m[3] ) );
            return self::convert( $text, $charset );
        }, $value );
        return expImportAbstractSource::toUtf8( (string)$out );
    }

    /**
     * A text in a charset converted to UTF-8.
     *
     * @param string $text
     * @param string $charset
     * @return string
     */
    public static function convert( $text, $charset )
    {
        $charset = strtoupper( trim( (string)$charset, " \"'" ) );
        if ( $charset === '' || $charset === 'US-ASCII' || $charset === 'UTF-8' || $charset === 'UTF8' )
            return expImportAbstractSource::toUtf8( (string)$text );
        $out = false;
        if ( function_exists( 'mb_convert_encoding' ) && in_array( $charset, array_map( 'strtoupper', mb_list_encodings() ), true ) )
            $out = @mb_convert_encoding( (string)$text, 'UTF-8', $charset );
        if ( $out === false && function_exists( 'iconv' ) )
            $out = @iconv( $charset, 'UTF-8//IGNORE', (string)$text );
        return $out === false ? expImportAbstractSource::toUtf8( (string)$text ) : (string)$out;
    }

    /**
     * The addresses of a header (From, To, Cc): "Name" <address>, Name <address>, address, groups.
     *
     * @param string $value
     * @return array list of array( 'name', 'address' )
     */
    public static function addresses( $value )
    {
        $out = array();
        foreach ( self::splitList( (string)$value ) as $item )
        {
            $item = trim( $item );
            // a group: "Team: a@example.org, b@example.org;" is its members
            if ( preg_match( '/^[^"<@]*:(.*)$/s', $item, $m ) && !preg_match( '/<[^>]*:/', $item ) )
                $item = trim( $m[1] );
            $item = trim( rtrim( $item, '; ' ) );
            if ( $item === '' )
                continue;
            if ( preg_match( '/^(.*)<([^<>]*)>\s*$/s', $item, $m ) )
            {
                $name = trim( $m[1] );
                $address = trim( $m[2] );
            }
            else
            {
                $name = '';
                $address = trim( preg_replace( '/\(.*\)/s', '', $item ) );
                if ( preg_match( '/\((.*)\)/s', $item, $c ) )
                    $name = trim( $c[1] );
            }
            if ( strlen( $name ) > 1 && $name[0] === '"' && substr( $name, -1 ) === '"' )
                $name = stripcslashes( substr( $name, 1, -1 ) );
            if ( $address === '' && $name === '' )
                continue;
            $out[] = array( 'name' => self::decodeHeader( $name ), 'address' => $address );
        }
        return $out;
    }

    /** @return string[] the items of a comma separated list, commas in quotes, brackets and comments not counted */
    protected static function splitList( $value )
    {
        $items = array();
        $current = '';
        $quote = false;
        $angle = 0;
        $paren = 0;
        $n = strlen( $value );
        for ( $i = 0; $i < $n; $i++ )
        {
            $c = $value[$i];
            if ( $quote )
            {
                if ( $c === '\\' && $i + 1 < $n )
                {
                    $current .= $c . $value[++$i];
                    continue;
                }
                if ( $c === '"' )
                    $quote = false;
            }
            elseif ( $c === '"' )
                $quote = true;
            elseif ( $c === '<' )
                $angle++;
            elseif ( $c === '>' )
                $angle = max( 0, $angle - 1 );
            elseif ( $c === '(' )
                $paren++;
            elseif ( $c === ')' )
                $paren = max( 0, $paren - 1 );
            elseif ( $c === ',' && !$angle && !$paren )
            {
                $items[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $items[] = $current;
        return array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
    }

    /** @return string the Date header in ISO 8601 (with its offset), '' when there is none or it cannot be read */
    public static function date( $value )
    {
        $value = trim( preg_replace( '/\([^)]*\)/', '', (string)$value ) );
        if ( $value === '' )
            return '';
        try
        {
            $date = new DateTime( $value );
        }
        catch ( Exception $e )
        {
            return '';
        }
        return $date->format( 'Y-m-d\TH:i:sP' );
    }

    /**
     * A header value with parameters: type; name="value"; name*=charset''percent-encoded; name*0=...
     *
     * @param string $value
     * @return array the list ( string value (lower case), array parameter (lower case) => decoded value )
     */
    public static function parameters( $value )
    {
        $value = (string)$value;
        $semi = strpos( $value, ';' );
        $main = strtolower( trim( $semi === false ? $value : substr( $value, 0, $semi ) ) );
        $params = array();
        $continued = array();
        if ( $semi !== false && preg_match_all( '/;\s*([A-Za-z0-9_.!#$%&\'+^`|~-]+?)(\*(\d+))?(\*)?\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', $value, $all, PREG_SET_ORDER ) )
        {
            foreach ( $all as $m )
            {
                $name = strtolower( $m[1] );
                $text = trim( $m[5] );
                if ( strlen( $text ) > 1 && $text[0] === '"' )
                    $text = stripcslashes( substr( $text, 1, -1 ) );
                $extended = !empty( $m[4] );
                if ( $m[3] !== '' )
                    $continued[$name][(int)$m[3]] = array( $text, $extended );
                elseif ( $extended )
                    $params[$name] = self::extendedValue( $text );
                else
                    $params[$name] = self::decodeHeader( $text );
            }
        }
        foreach ( $continued as $name => $pieces )
        {
            ksort( $pieces );
            $charset = '';
            $text = '';
            foreach ( $pieces as $index => $piece )
            {
                if ( $piece[1] )
                {
                    if ( $index === 0 && preg_match( "/^([^']*)'[^']*'(.*)$/s", $piece[0], $m ) )
                    {
                        $charset = $m[1];
                        $piece[0] = $m[2];
                    }
                    $text .= rawurldecode( $piece[0] );
                }
                else
                    $text .= $piece[0];
            }
            $params[$name] = self::convert( $text, $charset );
        }
        return array( $main, $params );
    }

    /** @return string an RFC 2231 value charset'language'percent-encoded, in UTF-8 */
    protected static function extendedValue( $text )
    {
        if ( preg_match( "/^([^']*)'[^']*'(.*)$/s", (string)$text, $m ) )
            return self::convert( rawurldecode( $m[2] ), $m[1] );
        return self::convert( rawurldecode( (string)$text ), '' );
    }

    /**
     * Reads a part: the first text/plain and text/html parts that are no attachment are the bodies, every other leaf
     * (and every part with a file name) is an attachment.
     *
     * @param array $headers
     * @param string $body
     * @param array $parts collects text, html, attachments
     * @param int $depth
     */
    protected static function walk( array $headers, $body, array &$parts, $depth )
    {
        list( $type, $params ) = self::parameters( self::header( $headers, 'content-type' ) ?: 'text/plain; charset=us-ascii' );
        list( $disposition, $dparams ) = self::parameters( self::header( $headers, 'content-disposition' ) );
        if ( strpos( $type, 'multipart/' ) === 0 && isset( $params['boundary'] ) && $params['boundary'] !== '' && $depth < self::MAX_DEPTH )
        {
            foreach ( self::multipart( $body, $params['boundary'] ) as $part )
            {
                list( $h, $b ) = self::split( $part );
                self::walk( $h, $b, $parts, $depth + 1 );
            }
            return;
        }
        $name = isset( $dparams['filename'] ) ? $dparams['filename'] : ( isset( $params['name'] ) ? $params['name'] : '' );
        $decoded = self::decodeBody( $body, self::header( $headers, 'content-transfer-encoding' ) );
        $isAttachment = $disposition === 'attachment' || $name !== '' || ( $type !== 'text/plain' && $type !== 'text/html' );
        if ( !$isAttachment && $type === 'text/plain' && $parts['text'] === '' )
        {
            $parts['text'] = str_replace( array( "\r\n", "\r" ), "\n", self::convert( $decoded, isset( $params['charset'] ) ? $params['charset'] : '' ) );
            return;
        }
        if ( !$isAttachment && $type === 'text/html' && $parts['html'] === '' )
        {
            $parts['html'] = self::convert( $decoded, isset( $params['charset'] ) ? $params['charset'] : '' );
            return;
        }
        if ( !$isAttachment )
            return;
        if ( $name === '' )
            $name = $type === 'message/rfc822' ? 'message.eml' : 'attachment-' . ( count( $parts['attachments'] ) + 1 );
        $parts['attachments'][] = array( 'name' => basename( str_replace( '\\', '/', $name ) ), 'mime' => $type, 'size' => strlen( $decoded ) );
    }

    /** @return string[] the parts of a multipart body (without preamble and epilogue) */
    protected static function multipart( $body, $boundary )
    {
        $out = array();
        $current = null;
        $open = '--' . $boundary;
        $close = '--' . $boundary . '--';
        foreach ( preg_split( '/\r?\n/', (string)$body ) as $line )
        {
            $bare = rtrim( $line, " \t" );
            if ( $bare === $close )
                break;
            if ( $bare === $open )
            {
                if ( $current !== null )
                    $out[] = implode( "\n", $current );
                $current = array();
                continue;
            }
            // lines before the first boundary are the preamble
            if ( $current !== null )
                $current[] = $line;
        }
        if ( $current !== null )
            $out[] = implode( "\n", $current );
        return array_values( array_filter( $out, function ( $p ) { return trim( $p ) !== ''; } ) );
    }

    /** @return string a body decoded from its transfer encoding */
    public static function decodeBody( $body, $encoding )
    {
        switch ( strtolower( trim( (string)$encoding ) ) )
        {
            case 'base64':
                return (string)base64_decode( preg_replace( '/[^A-Za-z0-9+\/=]/', '', (string)$body ) );
            case 'quoted-printable':
                return quoted_printable_decode( str_replace( "\r\n", "\n", (string)$body ) );
        }
        return (string)$body;
    }

    /** @return string HTML as plain text: lines for paragraphs and line breaks, no tags, entities decoded */
    public static function htmlToText( $html )
    {
        $html = preg_replace( '#<(script|style|head)\b[^>]*>.*?</\1\s*>#is', '', (string)$html );
        $html = preg_replace( '#<br\s*/?>#i', "\n", (string)$html );
        $html = preg_replace( '#</(p|div|h[1-6]|li|tr|blockquote)\s*>#i', "\n\n", (string)$html );
        $text = html_entity_decode( strip_tags( (string)$html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text );
        $text = preg_replace( '/ *\n */', "\n", (string)$text );
        return trim( (string)preg_replace( '/\n{3,}/', "\n\n", (string)$text ) );
    }

    /** @return string the first paragraph of a text (up to the first empty line), its lines joined */
    public static function firstParagraph( $text )
    {
        $text = trim( (string)$text );
        if ( $text === '' )
            return '';
        $first = preg_split( '/\n\s*\n/', $text, 2 );
        return trim( preg_replace( '/\s*\n\s*/', ' ', $first[0] ) );
    }

    /** @return string a plain text as HTML paragraphs */
    public static function textToHtml( $text )
    {
        $out = array();
        foreach ( preg_split( '/\n\s*\n/', trim( (string)$text ) ) as $paragraph )
            if ( trim( $paragraph ) !== '' )
                $out[] = '<p>' . nl2br( htmlspecialchars( trim( $paragraph ), ENT_QUOTES, 'UTF-8' ), false ) . '</p>';
        return implode( "\n", $out );
    }
}
