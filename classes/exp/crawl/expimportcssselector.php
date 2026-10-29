<?php
/**
 * File containing the expImportCssSelector class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The selectors of the "picks" of the crawl sources: a practical subset of CSS turned into XPath, or XPath itself
 * (written "xpath:<expression>"), and the value to take from what it finds.
 *
 * CSS: a tag or *, #id, .class, [attr], [attr=value] (and ~= ^= $= *= |=), :first-child, :last-child, :only-child,
 * :nth-child(n), :first-of-type, :last-of-type, the combinators " " (descendant), ">" (child), "+" (next sibling),
 * "~" (following siblings) and "," (either). At the end: ::text (the text, the default), ::html (the inner HTML),
 * ::attr(name) or @name (an attribute).
 *
 * The XPath is relative to the node it is evaluated on (the document, or one repeating item of a page).
 */
class expImportCssSelector
{
    const NAME = '[A-Za-z_][-A-Za-z0-9_]*';

    /**
     * @param string $selector
     * @return array{xpath: string, extract: string, attr: string, explicit: bool} extract is text, html or attr
     * @throws expImportSourceException
     */
    public static function parse( $selector )
    {
        $selector = trim( (string)$selector );
        if ( $selector === '' )
            throw new expImportSourceException( 'a selector is empty' );
        if ( stripos( $selector, 'xpath:' ) === 0 )
        {
            $xpath = trim( (string)substr( $selector, 6 ) );
            if ( $xpath === '' )
                throw new expImportSourceException( 'the XPath of a selector is empty' );
            return array( 'xpath' => $xpath, 'extract' => 'text', 'attr' => '', 'explicit' => false );
        }
        $extract = 'text';
        $attr = '';
        $explicit = false;
        if ( preg_match( '/(?:::attr\(\s*(' . self::NAME . '(?::' . self::NAME . ')?)\s*\)|\s*@(' . self::NAME . '(?::' . self::NAME . ')?))$/', $selector, $m ) )
        {
            $extract = 'attr';
            $attr = strtolower( isset( $m[2] ) && $m[2] !== '' ? $m[2] : $m[1] );
            $selector = rtrim( (string)substr( $selector, 0, -strlen( $m[0] ) ) );
            $explicit = true;
        }
        elseif ( preg_match( '/::(text|html)$/i', $selector, $m ) )
        {
            $extract = strtolower( $m[1] );
            $selector = rtrim( (string)substr( $selector, 0, -strlen( $m[0] ) ) );
            $explicit = true;
        }
        if ( $selector === '' && $extract === 'attr' )
            // a bare @name: the attribute of the node searched in (one repeating item)
            return array( 'xpath' => 'self::*', 'extract' => $extract, 'attr' => $attr, 'explicit' => $explicit );
        if ( $selector === '' )
            throw new expImportSourceException( 'a selector names no element' );
        return array( 'xpath' => self::toXPath( $selector ), 'extract' => $extract, 'attr' => $attr, 'explicit' => $explicit );
    }

    /**
     * @param string $css
     * @return string the XPath, relative to the context node
     * @throws expImportSourceException
     */
    public static function toXPath( $css )
    {
        $out = array();
        foreach ( self::splitList( (string)$css ) as $one )
        {
            $one = trim( $one );
            if ( $one === '' )
                throw new expImportSourceException( "the selector '$css' has an empty part" );
            $out[] = self::compile( $one );
        }
        return implode( ' | ', $out );
    }

    /** @return string[] the parts of a selector list, split at the commas outside brackets and quotes */
    protected static function splitList( $css )
    {
        $parts = array();
        $depth = 0;
        $quote = '';
        $current = '';
        $len = strlen( $css );
        for ( $i = 0; $i < $len; $i++ )
        {
            $c = $css[$i];
            if ( $quote !== '' )
            {
                if ( $c === $quote )
                    $quote = '';
            }
            elseif ( $c === '"' || $c === "'" )
                $quote = $c;
            elseif ( $c === '[' || $c === '(' )
                $depth++;
            elseif ( $c === ']' || $c === ')' )
                $depth--;
            elseif ( $c === ',' && $depth === 0 )
            {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $parts[] = $current;
        return $parts;
    }

    /** @return string one complex selector (compounds and combinators) as XPath */
    protected static function compile( $css )
    {
        $pos = 0;
        $len = strlen( $css );
        $xpath = '';
        $combinator = '';
        while ( $pos < $len )
        {
            $space = false;
            while ( $pos < $len && ctype_space( $css[$pos] ) )
            {
                $pos++;
                $space = true;
            }
            if ( $pos >= $len )
                break;
            if ( strpos( '>+~', $css[$pos] ) !== false )
            {
                if ( $xpath === '' || $combinator !== '' )
                    throw new expImportSourceException( "the selector '$css' has a combinator without an element before it" );
                $combinator = $css[$pos];
                $pos++;
                continue;
            }
            if ( $xpath !== '' && $combinator === '' )
            {
                if ( !$space )
                    throw new expImportSourceException( "the selector '$css' cannot be read at '" . substr( $css, $pos ) . "'" );
                $combinator = ' ';
            }
            $step = self::compound( $css, $pos );
            switch ( $combinator )
            {
                case '':
                    $xpath = 'descendant-or-self::' . $step;
                    break;
                case ' ':
                    $xpath .= '/descendant::' . $step;
                    break;
                case '>':
                    $xpath .= '/' . $step;
                    break;
                case '+':
                    $xpath .= '/following-sibling::*[1]/self::' . $step;
                    break;
                default:
                    $xpath .= '/following-sibling::' . $step;
            }
            $combinator = '';
        }
        if ( $xpath === '' || $combinator !== '' )
            throw new expImportSourceException( "the selector '$css' is incomplete" );
        return $xpath;
    }

    /** @return string one compound selector (tag, ids, classes, attributes, pseudo-classes) as an XPath step */
    protected static function compound( $css, &$pos )
    {
        $tag = '*';
        $predicates = array();
        $start = $pos;
        if ( $css[$pos] === '*' )
            $pos++;
        elseif ( preg_match( '/\G' . self::NAME . '/', $css, $m, 0, $pos ) )
        {
            $tag = strtolower( $m[0] );
            $pos += strlen( $m[0] );
        }
        $len = strlen( $css );
        while ( $pos < $len )
        {
            $c = $css[$pos];
            if ( ( $c === '#' || $c === '.' ) && preg_match( '/\G' . self::NAME . '/', $css, $m, 0, $pos + 1 ) )
            {
                $pos += 1 + strlen( $m[0] );
                $predicates[] = $c === '#' ? '@id=' . self::literal( $m[0] )
                                           : "contains(concat(' ', normalize-space(@class), ' '), " . self::literal( ' ' . $m[0] . ' ' ) . ')';
            }
            elseif ( $c === '[' )
                $predicates[] = self::attribute( $css, $pos );
            elseif ( $c === ':' && ( $pos + 1 >= $len || $css[$pos + 1] !== ':' ) )
                $predicates[] = self::pseudo( $css, $pos, $tag );
            else
                break;
        }
        if ( $pos === $start )
            throw new expImportSourceException( "the selector '$css' cannot be read at '" . substr( $css, $pos ) . "'" );
        return $tag . ( $predicates ? '[' . implode( '][', $predicates ) . ']' : '' );
    }

    /** @return string an attribute selector [...] as an XPath predicate */
    protected static function attribute( $css, &$pos )
    {
        if ( !preg_match( '/\G\[\s*(' . self::NAME . ')\s*(?:([~^$*|]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]+))\s*)?\]/', $css, $m, PREG_UNMATCHED_AS_NULL, $pos ) )
            throw new expImportSourceException( "the attribute selector in '$css' cannot be read at '" . substr( $css, $pos ) . "'" );
        $pos += strlen( $m[0] );
        $name = '@' . strtolower( $m[1] );
        if ( $m[2] === null )
            return $name;
        $value = $m[3] !== null ? $m[3] : ( $m[4] !== null ? $m[4] : (string)$m[5] );
        $lit = self::literal( $value );
        switch ( $m[2] )
        {
            case '=':
                return "$name=$lit";
            case '~=':
                return "contains(concat(' ', normalize-space($name), ' '), " . self::literal( ' ' . $value . ' ' ) . ')';
            case '^=':
                return "starts-with($name, $lit)";
            case '$=':
                return "substring($name, string-length($name) - " . strlen( $value ) . " + 1)=$lit";
            case '*=':
                return "contains($name, $lit)";
            default:
                return "($name=$lit or starts-with($name, " . self::literal( $value . '-' ) . '))';
        }
    }

    /** @return string a pseudo-class as an XPath predicate */
    protected static function pseudo( $css, &$pos, $tag )
    {
        if ( !preg_match( '/\G:([a-z-]+)(?:\(\s*(\d+)\s*\))?/i', $css, $m, 0, $pos ) )
            throw new expImportSourceException( "the pseudo-class in '$css' cannot be read" );
        $pos += strlen( $m[0] );
        $sibling = $tag === '*' ? '*' : $tag;
        switch ( strtolower( $m[1] ) )
        {
            case 'first-child':
                return 'not(preceding-sibling::*)';
            case 'last-child':
                return 'not(following-sibling::*)';
            case 'only-child':
                return 'not(preceding-sibling::*) and not(following-sibling::*)';
            case 'first-of-type':
                return "not(preceding-sibling::$sibling)";
            case 'last-of-type':
                return "not(following-sibling::$sibling)";
            case 'nth-child':
                if ( !isset( $m[2] ) || (int)$m[2] < 1 )
                    break;
                return 'count(preceding-sibling::*)=' . ( (int)$m[2] - 1 );
        }
        throw new expImportSourceException( "the pseudo-class :{$m[1]} is not supported (:first-child, :last-child, :only-child, :nth-child(n), :first-of-type, :last-of-type)" );
    }

    /** @return string a string as an XPath literal (concat() when it holds both kinds of quotes) */
    public static function literal( $value )
    {
        $value = (string)$value;
        if ( strpos( $value, "'" ) === false )
            return "'$value'";
        if ( strpos( $value, '"' ) === false )
            return '"' . $value . '"';
        return "concat('" . str_replace( "'", "', \"'\", '", $value ) . "')";
    }

    /**
     * The values a parsed selector finds.
     *
     * @param DOMXPath $xpath
     * @param array $parsed of parse()
     * @param DOMNode|null $context the node to search in (the document when null)
     * @return string[] one value per node found
     */
    public static function values( DOMXPath $xpath, array $parsed, ?DOMNode $context = null )
    {
        $nodes = self::nodes( $xpath, $parsed, $context );
        $out = array();
        foreach ( $nodes as $node )
        {
            if ( $parsed['extract'] === 'attr' )
            {
                if ( $node instanceof DOMElement && $node->hasAttribute( $parsed['attr'] ) )
                    $out[] = trim( $node->getAttribute( $parsed['attr'] ) );
            }
            elseif ( $parsed['extract'] === 'html' )
                $out[] = self::innerHtml( $node );
            else
                $out[] = self::text( $node );
        }
        return $out;
    }

    /**
     * @return DOMNode[] the nodes a parsed selector finds
     * @throws expImportSourceException for an XPath that cannot be evaluated
     */
    public static function nodes( DOMXPath $xpath, array $parsed, ?DOMNode $context = null )
    {
        $previous = libxml_use_internal_errors( true );
        $list = $context ? @$xpath->query( $parsed['xpath'], $context ) : @$xpath->query( $parsed['xpath'] );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$list instanceof DOMNodeList )
            throw new expImportSourceException( "the XPath '{$parsed['xpath']}' cannot be evaluated" );
        $out = array();
        foreach ( $list as $node )
            $out[] = $node;
        return $out;
    }

    /** @return string the text of a node, its white space collapsed */
    public static function text( DOMNode $node )
    {
        return trim( (string)preg_replace( '/\s+/u', ' ', self::collect( $node ) ) );
    }

    /** @return string the text of a node, with a space around block elements (two paragraphs are two sentences) */
    protected static function collect( DOMNode $node )
    {
        if ( !$node instanceof DOMElement )
            return $node instanceof DOMText || $node instanceof DOMAttr ? (string)$node->nodeValue : (string)$node->textContent;
        $text = '';
        foreach ( $node->childNodes as $child )
            $text .= self::collect( $child );
        return preg_match( '/^(p|div|br|li|ul|ol|dl|dt|dd|h[1-6]|tr|td|th|table|section|article|aside|header|footer|nav|main|blockquote|pre|figure|figcaption|hr|address)$/', $node->localName )
             ? ' ' . $text . ' ' : $text;
    }

    /** @return string the HTML inside an element (its own tag left out) */
    public static function innerHtml( DOMNode $node )
    {
        if ( !$node instanceof DOMElement )
            return self::text( $node );
        $html = '';
        foreach ( $node->childNodes as $child )
            $html .= $node->ownerDocument->saveHTML( $child );
        return trim( $html );
    }

    /** @return bool whether an element holds elements (HTML, not just text) */
    public static function isHtmlish( DOMNode $node )
    {
        if ( !$node instanceof DOMElement )
            return false;
        foreach ( $node->childNodes as $child )
            if ( $child instanceof DOMElement )
                return true;
        return false;
    }
}
