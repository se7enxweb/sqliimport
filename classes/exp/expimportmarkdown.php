<?php
/**
 * File containing the expImportMarkdown class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Markdown to HTML for imported content, without a library: the Markdown that notes, blogs and documentation use.
 *
 *   blocks   ATX headings (#) and setext headings (=== / ---), paragraphs, fenced code (``` and ~~~) and indented
 *            code, block quotes, unordered (-, *, +) and ordered lists (one level of nesting by indent), horizontal
 *            rules, GitHub tables, raw HTML blocks kept as they are
 *   inline   `code`, **strong**, __strong__, *em*, _em_, ~~del~~, [text](url "title"), ![alt](src), <https://autolinks>,
 *            hard line breaks (two spaces or a backslash at the end), backslash escapes, Obsidian [[Page]] and
 *            [[Page|text]] links (to text, or to a link with a base address) and ![[image.png]] embeds
 *
 * Text is escaped; link addresses other than http, https, mailto, relative and anchors are dropped (no javascript:).
 */
class expImportMarkdown
{
    /** @var string the address Obsidian [[links]] point to ("" keeps their text only) */
    protected $wikiBase = '';

    /** @var string[] [[links]] and image paths met while converting */
    public $wikiLinks = array();

    /** @var string[] */
    public $images = array();

    public function __construct( $wikiBase = '' )
    {
        $this->wikiBase = (string)$wikiBase;
    }

    /** @return string HTML of a Markdown text */
    public static function toHtml( $markdown, $wikiBase = '' )
    {
        return ( new self( $wikiBase ) )->convert( $markdown );
    }

    /** @return string */
    public function convert( $markdown )
    {
        $lines = explode( "\n", str_replace( array( "\r\n", "\r", "\t" ), array( "\n", "\n", '    ' ), (string)$markdown ) );
        return trim( $this->blocks( $lines ) );
    }

    /** @return string */
    protected function blocks( array $lines )
    {
        $html = array();
        $paragraph = array();
        $flush = function () use ( &$paragraph, &$html )
        {
            if ( $paragraph )
                $html[] = '<p>' . $this->inline( implode( "\n", $paragraph ) ) . '</p>';
            $paragraph = array();
        };
        $n = count( $lines );
        for ( $i = 0; $i < $n; $i++ )
        {
            $line = $lines[$i];
            $trim = trim( $line );
            if ( $trim === '' )
            {
                $flush();
                continue;
            }
            // fenced code
            if ( preg_match( '/^\s{0,3}(```+|~~~+)\s*([\w+-]*)/', $line, $m ) )
            {
                $flush();
                $code = array();
                for ( $i++; $i < $n && strpos( ltrim( $lines[$i] ), $m[1] ) !== 0; $i++ )
                    $code[] = $lines[$i];
                $html[] = '<pre><code' . ( $m[2] !== '' ? ' class="language-' . self::e( $m[2] ) . '"' : '' ) . '>' . self::e( implode( "\n", $code ) ) . '</code></pre>';
                continue;
            }
            // indented code (not inside a paragraph)
            if ( !$paragraph && preg_match( '/^ {4}/', $line ) )
            {
                $code = array();
                for ( ; $i < $n && ( preg_match( '/^ {4}/', $lines[$i] ) || trim( $lines[$i] ) === '' ); $i++ )
                    $code[] = (string)substr( $lines[$i], 4 );
                $i--;
                $html[] = '<pre><code>' . self::e( rtrim( implode( "\n", $code ) ) ) . '</code></pre>';
                continue;
            }
            if ( preg_match( '/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m ) )
            {
                $flush();
                $level = strlen( $m[1] );
                $html[] = "<h$level>" . $this->inline( $m[2] ) . "</h$level>";
                continue;
            }
            if ( $paragraph && preg_match( '/^\s{0,3}(=+|-+)\s*$/', $line, $m ) )
            {
                $level = $m[1][0] === '=' ? 1 : 2;
                $text = implode( "\n", $paragraph );
                $paragraph = array();
                $html[] = "<h$level>" . $this->inline( $text ) . "</h$level>";
                continue;
            }
            if ( preg_match( '/^\s{0,3}([-*_])(\s*\1){2,}\s*$/', $line ) )
            {
                $flush();
                $html[] = '<hr />';
                continue;
            }
            if ( preg_match( '/^\s{0,3}>/', $line ) )
            {
                $flush();
                $quote = array();
                for ( ; $i < $n && preg_match( '/^\s{0,3}>\s?(.*)$/', $lines[$i], $q ); $i++ )
                    $quote[] = $q[1];
                $i--;
                $html[] = '<blockquote>' . $this->blocks( $quote ) . '</blockquote>';
                continue;
            }
            if ( preg_match( '/^\s{0,3}([-*+]|\d+[.)])\s+/', $line, $m ) )
            {
                $flush();
                $ordered = ctype_digit( substr( $m[1], 0, 1 ) );
                $items = array();
                $base = strlen( $line ) - strlen( ltrim( $line ) );
                for ( ; $i < $n; $i++ )
                {
                    $l = $lines[$i];
                    $indent = strlen( $l ) - strlen( ltrim( $l ) );
                    // indented by two or more below the list's own items: part of the item before (a nested list too)
                    if ( $items && trim( $l ) !== '' && $indent >= $base + 2 )
                        $items[count( $items ) - 1][] = (string)substr( $l, min( $indent, $base + 2 ) );
                    elseif ( preg_match( '/^\s{0,3}([-*+]|\d+[.)])\s+(.*)$/', $l, $im ) && ctype_digit( substr( $im[1], 0, 1 ) ) === $ordered )
                        $items[] = array( $im[2] );
                    elseif ( $items && trim( $l ) === '' && $i + 1 < $n && preg_match( '/^(\s{2,}|\s{0,3}([-*+]|\d+[.)])\s)/', $lines[$i + 1] ) )
                        continue;
                    else
                        break;
                }
                $i--;
                $tag = $ordered ? 'ol' : 'ul';
                $out = "<$tag>";
                foreach ( $items as $item )
                {
                    $first = array_shift( $item );
                    $task = '';
                    if ( preg_match( '/^\[( |x|X)\]\s+(.*)$/', $first, $t ) )
                    {
                        $task = $t[1] === ' ' ? '&#9744; ' : '&#9745; ';
                        $first = $t[2];
                    }
                    $out .= '<li>' . $task . $this->inline( $first ) . ( $item ? $this->blocks( $item ) : '' ) . '</li>';
                }
                $html[] = $out . "</$tag>";
                continue;
            }
            // a GitHub table: a header line, a separator line of |---|
            if ( strpos( $line, '|' ) !== false && $i + 1 < $n && preg_match( '/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/', $lines[$i + 1] ) )
            {
                $flush();
                $cells = function ( $row )
                {
                    return array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) );
                };
                $out = '<table><thead><tr>';
                foreach ( $cells( $line ) as $c )
                    $out .= '<th>' . $this->inline( $c ) . '</th>';
                $out .= '</tr></thead><tbody>';
                for ( $i += 2; $i < $n && strpos( $lines[$i], '|' ) !== false && trim( $lines[$i] ) !== ''; $i++ )
                {
                    $out .= '<tr>';
                    foreach ( $cells( $lines[$i] ) as $c )
                        $out .= '<td>' . $this->inline( $c ) . '</td>';
                    $out .= '</tr>';
                }
                $i--;
                $html[] = $out . '</tbody></table>';
                continue;
            }
            // raw HTML blocks stay (the rich text transform cleans them)
            if ( !$paragraph && preg_match( '/^\s{0,3}<(div|table|pre|p|ul|ol|h[1-6]|blockquote|figure|section|iframe|details)[\s>]/i', $line ) )
            {
                $block = array();
                for ( ; $i < $n && trim( $lines[$i] ) !== ''; $i++ )
                    $block[] = $lines[$i];
                $i--;
                $html[] = implode( "\n", $block );
                continue;
            }
            $paragraph[] = $trim === $line ? $line : rtrim( ltrim( $line ) ) . ( preg_match( '/ {2,}$/', $line ) ? '  ' : '' );
        }
        $flush();
        return implode( "\n", $html );
    }

    /** @return string the inline Markdown of a text, as HTML */
    protected function inline( $text )
    {
        $slots = array();
        $keep = function ( $html ) use ( &$slots )
        {
            $slots[] = $html;
            return "\x1A" . ( count( $slots ) - 1 ) . "\x1A";
        };
        // code spans and escapes first: nothing inside them is markup
        $text = preg_replace_callback( '/(`+)(.+?)\1/s', function ( $m ) use ( $keep ) { return $keep( '<code>' . self::e( trim( $m[2] ) ) . '</code>' ); }, $text );
        $text = preg_replace_callback( '/\\\\([\\\\`*_{}\[\]()#+\-.!|~<>])/', function ( $m ) use ( $keep ) { return $keep( self::e( $m[1] ) ); }, $text );
        // Obsidian embeds and links
        $text = preg_replace_callback( '/!\[\[([^\]|]+)(?:\|([^\]]*))?\]\]/', function ( $m ) use ( $keep )
        {
            $this->images[] = trim( $m[1] );
            return $keep( '<img src="' . self::e( trim( $m[1] ) ) . '" alt="' . self::e( isset( $m[2] ) ? $m[2] : '' ) . '" />' );
        }, $text );
        $text = preg_replace_callback( '/\[\[([^\]|#]+)(#[^\]|]*)?(?:\|([^\]]+))?\]\]/', function ( $m ) use ( $keep )
        {
            $page = trim( $m[1] );
            $this->wikiLinks[] = $page;
            $label = isset( $m[3] ) && $m[3] !== '' ? $m[3] : $page;
            if ( $this->wikiBase === '' )
                return $keep( self::e( $label ) );
            $slug = trim( (string)preg_replace( '/[^a-z0-9]+/', '-', strtolower( $page ) ), '-' );
            return $keep( '<a href="' . self::e( rtrim( $this->wikiBase, '/' ) . '/' . $slug ) . '">' . self::e( $label ) . '</a>' );
        }, $text );
        // images and links
        $text = preg_replace_callback( '/!\[([^\]]*)\]\(\s*<?((?:[^()\s>]|\([^()\s]*\))+)>?(?:\s+"([^"]*)")?\s*\)/', function ( $m ) use ( $keep )
        {
            $src = self::url( $m[2] );
            if ( $src === '' )
                return $keep( self::e( $m[1] ) );
            $this->images[] = $m[2];
            return $keep( '<img src="' . self::e( $src ) . '" alt="' . self::e( $m[1] ) . '"' . ( isset( $m[3] ) && $m[3] !== '' ? ' title="' . self::e( $m[3] ) . '"' : '' ) . ' />' );
        }, $text );
        $text = preg_replace_callback( '/\[([^\]]+)\]\(\s*<?((?:[^()\s>]|\([^()\s]*\))*)>?(?:\s+"([^"]*)")?\s*\)/', function ( $m ) use ( $keep )
        {
            $href = self::url( $m[2] );
            $label = $this->inline( $m[1] );
            if ( $href === '' )
                return $keep( $label );
            return $keep( '<a href="' . self::e( $href ) . '"' . ( isset( $m[3] ) && $m[3] !== '' ? ' title="' . self::e( $m[3] ) . '"' : '' ) . '>' . $label . '</a>' );
        }, $text );
        $text = preg_replace_callback( '/<(https?:\/\/[^>\s]+|mailto:[^>\s]+)>/', function ( $m ) use ( $keep )
        {
            return $keep( '<a href="' . self::e( $m[1] ) . '">' . self::e( preg_replace( '/^mailto:/', '', $m[1] ) ) . '</a>' );
        }, $text );
        $text = self::e( $text );
        $text = preg_replace( '/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/s', '<strong>$2</strong>', $text );
        $text = preg_replace( '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?!\*)/s', '<em>$1</em>', $text );
        $text = preg_replace( '/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/s', '<em>$1</em>', $text );
        $text = preg_replace( '/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text );
        $text = preg_replace( '/( {2,}|\\\\)\n/', "<br />\n", $text );
        // the kept pieces, inside out
        while ( preg_match( '/\x1A(\d+)\x1A/', $text ) )
            $text = preg_replace_callback( '/\x1A(\d+)\x1A/', function ( $m ) use ( $slots ) { return $slots[(int)$m[1]]; }, $text );
        return $text;
    }

    /** @return string an address that may be linked ('' for javascript:, data: and the like) */
    public static function url( $url )
    {
        $url = trim( (string)$url );
        if ( $url === '' )
            return '';
        if ( preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $url, $m ) && !in_array( strtolower( $m[1] ), array( 'http', 'https', 'mailto' ), true ) )
            return '';
        return $url;
    }

    /** @return string */
    protected static function e( $text )
    {
        return htmlspecialchars( (string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}
