<?php
/**
 * File containing the expImportRobots class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The rules of one host's robots.txt (RFC 9309): groups of User-agent lines with their Allow and Disallow rules and
 * Crawl-delay, and the Sitemap lines.
 *
 * The group of a crawler is the one whose user agent is the longest that occurs in the crawler's user agent (case
 * does not matter), else the group "*"; groups naming the same agent are merged. For a path, the rule with the
 * longest pattern that matches wins, and Allow wins over Disallow of the same length. Patterns may hold * (any
 * characters) and end in $ (the end of the path). /robots.txt itself is always allowed.
 */
class expImportRobots
{
    /** @var array<int, array{agents: string[], rules: array, delay: float|null}> */
    protected $groups = array();

    /** @var string[] */
    protected $sitemaps = array();

    /** @var bool|null true: everything allowed, false: nothing allowed, null: the rules decide */
    protected $all = null;

    /** @param string $text the robots.txt */
    public function __construct( $text = '' )
    {
        $this->parse( (string)$text );
    }

    /** @return self a host without robots.txt (404 and other 4xx answers): everything allowed */
    public static function allowAll()
    {
        $robots = new self( '' );
        $robots->all = true;
        return $robots;
    }

    /** @return self a host whose robots.txt cannot be read (5xx, no answer): nothing allowed */
    public static function disallowAll()
    {
        $robots = new self( '' );
        $robots->all = false;
        return $robots;
    }

    protected function parse( $text )
    {
        $text = (string)preg_replace( '/^\xEF\xBB\xBF/', '', $text );
        $current = null;
        $lastWasAgent = false;
        foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line )
        {
            $hash = strpos( $line, '#' );
            if ( $hash !== false )
                $line = substr( $line, 0, $hash );
            $line = trim( $line );
            if ( $line === '' || strpos( $line, ':' ) === false )
                continue;
            list( $field, $value ) = explode( ':', $line, 2 );
            $field = strtolower( trim( $field ) );
            $value = trim( $value );
            if ( $field === 'sitemap' )
            {
                if ( $value !== '' )
                    $this->sitemaps[] = $value;
                continue;
            }
            if ( $field === 'user-agent' )
            {
                if ( !$lastWasAgent || $current === null )
                {
                    $this->groups[] = array( 'agents' => array(), 'rules' => array(), 'delay' => null );
                    $current = count( $this->groups ) - 1;
                }
                $this->groups[$current]['agents'][] = strtolower( $value );
                $lastWasAgent = true;
                continue;
            }
            $lastWasAgent = false;
            if ( $current === null )
                continue;
            if ( $field === 'allow' || $field === 'disallow' )
            {
                // an empty Disallow allows everything: no rule
                if ( $value !== '' )
                    $this->groups[$current]['rules'][] = array( $field === 'allow', $value );
            }
            elseif ( $field === 'crawl-delay' && is_numeric( $value ) && (float)$value >= 0 )
                $this->groups[$current]['delay'] = (float)$value;
        }
    }

    /** @return string[] the Sitemap addresses the file lists */
    public function sitemaps()
    {
        return $this->sitemaps;
    }

    /**
     * The rules and the crawl delay for a user agent.
     *
     * @param string $userAgent
     * @return array{rules: array, delay: float|null}
     */
    protected function groupFor( $userAgent )
    {
        $ua = strtolower( (string)$userAgent );
        $best = '';
        foreach ( $this->groups as $group )
            foreach ( $group['agents'] as $agent )
                if ( $agent !== '*' && $agent !== '' && strlen( $agent ) > strlen( $best ) && strpos( $ua, $agent ) !== false )
                    $best = $agent;
        $name = $best !== '' ? $best : '*';
        $out = array( 'rules' => array(), 'delay' => null );
        foreach ( $this->groups as $group )
            if ( in_array( $name, $group['agents'], true ) )
            {
                $out['rules'] = array_merge( $out['rules'], $group['rules'] );
                if ( $group['delay'] !== null && ( $out['delay'] === null || $group['delay'] > $out['delay'] ) )
                    $out['delay'] = $group['delay'];
            }
        return $out;
    }

    /**
     * @param string $url an address or a path (with its query)
     * @param string $userAgent
     * @return bool whether the user agent may read it
     */
    public function isAllowed( $url, $userAgent )
    {
        $path = self::pathOf( $url );
        if ( $path === '/robots.txt' || $this->all === true )
            return true;
        if ( $this->all === false )
            return false;
        $best = -1;
        $allowed = true;
        foreach ( $this->groupFor( $userAgent )['rules'] as $rule )
        {
            list( $allow, $pattern ) = $rule;
            if ( !self::matches( $pattern, $path ) )
                continue;
            $length = strlen( $pattern );
            if ( $length > $best || ( $length === $best && $allow ) )
            {
                $best = $length;
                $allowed = $allow;
            }
        }
        return $allowed;
    }

    /** @return float|null the Crawl-delay for the user agent, in seconds */
    public function crawlDelay( $userAgent )
    {
        return $this->all === null ? $this->groupFor( $userAgent )['delay'] : null;
    }

    /** @return bool whether a robots.txt pattern (with * and a final $) matches a path from its start */
    public static function matches( $pattern, $path )
    {
        $pattern = self::normalise( (string)$pattern );
        $anchored = substr( $pattern, -1 ) === '$';
        if ( $anchored )
            $pattern = substr( $pattern, 0, -1 );
        $regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . ( $anchored ? '$' : '' ) . '#s';
        return (bool)preg_match( $regex, self::normalise( (string)$path ) );
    }

    /** @return string a path with its percent escapes in upper case and the unreserved ones decoded */
    protected static function normalise( $path )
    {
        return (string)preg_replace_callback( '/%([0-9A-Fa-f]{2})/', function ( $m )
        {
            $char = chr( hexdec( $m[1] ) );
            return preg_match( '/[A-Za-z0-9._~-]/', $char ) ? $char : '%' . strtoupper( $m[1] );
        }, $path );
    }

    /** @return string the path and query of an address ("/" when it has none) */
    public static function pathOf( $url )
    {
        $url = (string)$url;
        if ( !preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) )
            return $url === '' ? '/' : $url;
        $parts = parse_url( $url );
        $path = isset( $parts['path'] ) && $parts['path'] !== '' ? $parts['path'] : '/';
        return $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
    }
}
