<?php
/**
 * File containing the expImportCrawler class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The polite reader of public web pages behind the formats sitemap and webpage.
 *
 * Every request goes through get(): the address must be http or https without user name or password, robots.txt of
 * the host is read once and obeyed (Allow and Disallow for our user agent or "*", Crawl-delay), requests to a host
 * are spaced by the delay (at least [CrawlSettings] MinDelay seconds), redirects are followed by hand (three at most,
 * each checked again) and the page itself is fetched by expImportSourceFiles::download(), which refuses private
 * networks unless [SourceSettings] AllowPrivateHosts, keeps to [ImportSettings] StreamTimeout and to
 * [SourceSettings] MaxSourceMegabytes. No cookie and no credential is ever sent, and no JavaScript is executed: a
 * page is parsed as HTML text with DOMDocument (no network access, scripts and event attributes removed).
 *
 * The HTTP fetcher is the property $fetcher, a callable( string $url ): array( 'status' => int, 'headers' =>
 * array( lower-case name => value ), 'body' => string ), so the tests replace it and never touch the network. The
 * pause between requests is the callable $sleeper( float $seconds ).
 */
class expImportCrawler
{
    /** @var callable|null fetches an address: array( 'status', 'headers', 'body' ); null: over HTTP */
    public $fetcher;

    /** @var callable pauses between two requests to a host */
    public $sleeper;

    /** @var string */
    public $userAgent = '7x SQLI Import (Exponential)';

    /** @var float the shortest pause between two requests to a host, in seconds */
    public $minDelay = 0.5;

    /** @var int the most pages a run may read */
    public $maxPages = 2000;

    /** @var string where the HTTP fetcher keeps a page while it reads it */
    public $scratch = '';

    /** @var expImportRobots[] by scheme://host:port */
    protected $robots = array();

    /** @var float[] the time of the last request, by scheme://host:port */
    protected $last = array();

    /** @var int requests made */
    public $requests = 0;

    public function __construct()
    {
        $this->sleeper = function ( $seconds )
        {
            usleep( (int)round( $seconds * 1000000 ) );
        };
    }

    /**
     * A crawler with the site's settings: sqliimport.ini [CrawlSettings] MaxPages, MinDelay and UserAgent (empty:
     * "7x SQLI Import (Exponential; +<the site's address>)").
     *
     * @param string $scratch a directory for the pages being read
     * @return self
     */
    public static function fromSettings( $scratch )
    {
        $crawler = new self();
        $crawler->scratch = (string)$scratch;
        $crawler->maxPages = max( 1, (int)expImport::setting( 'CrawlSettings', 'MaxPages', 2000 ) );
        $crawler->minDelay = max( 0.0, (float)expImport::setting( 'CrawlSettings', 'MinDelay', 0.5 ) );
        $ua = trim( (string)expImport::setting( 'CrawlSettings', 'UserAgent', '' ) );
        if ( $ua === '' )
        {
            $site = class_exists( 'eZINI' ) ? trim( (string)eZINI::instance()->variable( 'SiteSettings', 'SiteURL' ) ) : '';
            $ua = '7x SQLI Import (Exponential' . ( $site !== '' ? '; +' . ( preg_match( '#^https?://#', $site ) ? $site : 'https://' . $site ) : '' ) . ')';
        }
        $crawler->userAgent = $ua;
        return $crawler;
    }

    // ---- requests ---------------------------------------------------------------------------------------------------

    /** @return string scheme://host:port of an address */
    public static function origin( $url )
    {
        $p = parse_url( (string)$url );
        $scheme = isset( $p['scheme'] ) ? strtolower( $p['scheme'] ) : 'http';
        $port = isset( $p['port'] ) ? (int)$p['port'] : ( $scheme === 'https' ? 443 : 80 );
        return $scheme . '://' . ( isset( $p['host'] ) ? strtolower( $p['host'] ) : '' ) . ':' . $port;
    }

    /**
     * @return string the site of an address: its host without a leading "www.", whatever the scheme and port (a
     *                sitemap of https://www.example.org/ that lists http://example.org/ addresses lists its own site)
     */
    public static function site( $url )
    {
        $host = strtolower( (string)parse_url( (string)$url, PHP_URL_HOST ) );
        return strpos( $host, 'www.' ) === 0 ? (string)substr( $host, 4 ) : $host;
    }

    /** @return string|null why an address is not read (another scheme, a user name or password), null when fine */
    public static function refusal( $url )
    {
        $p = parse_url( (string)$url );
        if ( !$p || !isset( $p['scheme'], $p['host'] ) || !in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) )
            return 'only http and https addresses are read';
        if ( isset( $p['user'] ) || isset( $p['pass'] ) )
            return 'addresses with a user name or password are not read (no credentials are ever sent)';
        return null;
    }

    /**
     * The robots.txt of an address's host, read once per host: 2xx its rules, 4xx everything allowed, anything else
     * (5xx, no answer) nothing allowed.
     *
     * @param string $url
     * @return expImportRobots
     */
    public function robotsFor( $url )
    {
        $origin = self::origin( $url );
        if ( isset( $this->robots[$origin] ) )
            return $this->robots[$origin];
        $p = parse_url( (string)$url );
        $robotsUrl = strtolower( $p['scheme'] ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . '/robots.txt';
        try
        {
            $response = $this->request( $robotsUrl );
            for ( $i = 0; $i < 3 && in_array( $response['status'], array( 301, 302, 303, 307, 308 ), true ) && !empty( $response['headers']['location'] ); $i++ )
            {
                $next = self::resolve( $robotsUrl, $response['headers']['location'] );
                if ( self::refusal( $next ) !== null )
                    break;
                $response = $this->request( $next );
            }
            if ( $response['status'] >= 200 && $response['status'] < 300 )
                $robots = new expImportRobots( substr( (string)$response['body'], 0, 512000 ) );
            elseif ( $response['status'] >= 400 && $response['status'] < 500 )
                $robots = expImportRobots::allowAll();
            else
                $robots = expImportRobots::disallowAll();
        }
        catch ( Exception $e )
        {
            $robots = expImportRobots::disallowAll();
        }
        return $this->robots[$origin] = $robots;
    }

    /** @return bool whether robots.txt allows this crawler to read an address */
    public function allowed( $url )
    {
        return $this->robotsFor( $url )->isAllowed( $url, $this->userAgent );
    }

    /**
     * Reads a page: the address checked, robots.txt obeyed, the pause kept, redirects followed (three at most).
     *
     * @param string $url
     * @param float $delay the pause asked for between two requests to a host, in seconds
     * @return array{url: string, status: int, headers: array, body: string} url is the address after redirects
     * @throws expImportSourceException when the address may not or cannot be read
     */
    public function get( $url, $delay = 1.0 )
    {
        for ( $hop = 0; $hop <= 3; $hop++ )
        {
            $refusal = self::refusal( $url );
            if ( $refusal !== null )
                throw new expImportSourceException( $refusal );
            if ( !$this->allowed( $url ) )
                throw new expImportSourceException( 'robots.txt of the site does not allow reading ' . $url );
            $response = $this->request( $url, $delay );
            if ( in_array( $response['status'], array( 301, 302, 303, 307, 308 ), true ) && !empty( $response['headers']['location'] ) )
            {
                $url = self::resolve( $url, $response['headers']['location'] );
                continue;
            }
            $response['url'] = $url;
            return $response;
        }
        throw new expImportSourceException( 'too many redirects at ' . $url );
    }

    /** @return array one request, after the pause the host needs */
    protected function request( $url, $delay = 1.0 )
    {
        $origin = self::origin( $url );
        $wait = max( $this->minDelay, (float)$delay );
        if ( isset( $this->robots[$origin] ) )
            $wait = max( $wait, (float)$this->robots[$origin]->crawlDelay( $this->userAgent ) );
        if ( isset( $this->last[$origin] ) )
        {
            $left = $this->last[$origin] + $wait - microtime( true );
            if ( $left > 0 )
                call_user_func( $this->sleeper, $left );
        }
        $this->requests++;
        try
        {
            $response = $this->fetcher ? call_user_func( $this->fetcher, $url ) : $this->httpGet( $url );
        }
        finally
        {
            $this->last[$origin] = microtime( true );
        }
        $headers = array();
        foreach ( isset( $response['headers'] ) ? (array)$response['headers'] : array() as $name => $value )
            $headers[strtolower( (string)$name )] = is_array( $value ) ? (string)end( $value ) : (string)$value;
        return array( 'status' => isset( $response['status'] ) ? (int)$response['status'] : 0, 'headers' => $headers,
                      'body' => isset( $response['body'] ) ? (string)$response['body'] : '' );
    }

    /** @return array one GET over HTTP, by expImportSourceFiles::download() (its checks and limits), no cookies */
    protected function httpGet( $url )
    {
        $dir = $this->scratch !== '' ? $this->scratch : sys_get_temp_dir();
        $file = rtrim( $dir, '/' ) . '/crawl-' . substr( sha1( $url . microtime() ), 0, 16 ) . '.part';
        $lines = array();
        try
        {
            expImportSourceFiles::download( $url, $file, array(
                'user_agent' => $this->userAgent,
                'follow_location' => 0,
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.5\r\nAccept-Encoding: identity\r\n",
            ), $lines );
            $body = (string)file_get_contents( $file );
        }
        finally
        {
            if ( is_file( $file ) )
                unlink( $file );
        }
        $status = 0;
        $headers = array();
        foreach ( (array)$lines as $line )
        {
            if ( preg_match( '#^HTTP/\S+\s+(\d{3})#i', (string)$line, $m ) )
            {
                $status = (int)$m[1];
                $headers = array();
            }
            elseif ( strpos( (string)$line, ':' ) !== false )
            {
                list( $name, $value ) = explode( ':', (string)$line, 2 );
                $headers[strtolower( trim( $name ) )] = trim( $value );
            }
        }
        return array( 'status' => $status, 'headers' => $headers, 'body' => $body );
    }

    /**
     * An address relative to another (RFC 3986, the common cases).
     *
     * @param string $base
     * @param string $ref
     * @return string
     */
    public static function resolve( $base, $ref )
    {
        $ref = trim( (string)$ref );
        if ( $ref === '' )
            return (string)$base;
        if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $ref ) )
            return $ref;
        $b = parse_url( (string)$base );
        if ( !$b || !isset( $b['scheme'], $b['host'] ) )
            return $ref;
        $root = $b['scheme'] . '://' . $b['host'] . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
        if ( substr( $ref, 0, 2 ) === '//' )
            return $b['scheme'] . ':' . $ref;
        $basePath = isset( $b['path'] ) && $b['path'] !== '' ? $b['path'] : '/';
        if ( $ref[0] === '#' )
            return $root . $basePath . ( isset( $b['query'] ) ? '?' . $b['query'] : '' ) . $ref;
        if ( $ref[0] === '?' )
            return $root . $basePath . $ref;
        $suffix = '';
        if ( preg_match( '/^([^?#]*)([?#].*)$/s', $ref, $m ) )
        {
            $ref = $m[1];
            $suffix = $m[2];
        }
        $path = $ref !== '' && $ref[0] === '/' ? $ref : substr( $basePath, 0, (int)strrpos( $basePath, '/' ) + 1 ) . $ref;
        $out = array();
        foreach ( explode( '/', $path ) as $i => $segment )
        {
            if ( $segment === '..' )
            {
                if ( count( $out ) > 1 )
                    array_pop( $out );
            }
            elseif ( $segment !== '.' )
                $out[] = $segment;
        }
        $last = substr( $path, -3 ) === '/..' || substr( $path, -2 ) === '/.';
        return $root . implode( '/', $out ) . ( $last ? '/' : '' ) . $suffix;
    }

    // ---- URL patterns -----------------------------------------------------------------------------------------------

    /**
     * Whether an address matches a pattern: "regex:<expression>" (with or without delimiters), else a glob with *
     * and ? matched against the whole address or, for a pattern starting with /, against its path and query.
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    public static function patternMatches( $pattern, $url )
    {
        $pattern = trim( (string)$pattern );
        if ( $pattern === '' )
            return false;
        if ( stripos( $pattern, 'regex:' ) === 0 )
        {
            $regex = trim( (string)substr( $pattern, 6 ) );
            // with delimiters when it has valid ones (#...#i), else as the expression itself
            $result = preg_match( '/^([\/#~]).*\1[imsux]*$/s', $regex ) ? @preg_match( $regex, (string)$url ) : false;
            if ( $result === false )
                $result = @preg_match( '#' . str_replace( '#', '\#', $regex ) . '#', (string)$url );
            if ( $result === false )
                throw new expImportSourceException( "the pattern '$pattern' is not a valid regular expression" );
            return $result === 1;
        }
        $subject = $pattern[0] === '/' ? expImportRobots::pathOf( $url ) : (string)$url;
        $regex = '#^' . strtr( preg_quote( $pattern, '#' ), array( '\*' => '.*', '\?' => '.' ) ) . '$#si';
        return (bool)preg_match( $regex, $subject );
    }

    /**
     * @param string $url
     * @param string[] $include none: every address
     * @param string[] $exclude
     * @return bool whether an address is read: it matches an include pattern (if there are any) and no exclude pattern
     */
    public static function urlWanted( $url, array $include, array $exclude )
    {
        foreach ( $exclude as $pattern )
            if ( self::patternMatches( $pattern, $url ) )
                return false;
        if ( !$include )
            return true;
        foreach ( $include as $pattern )
            if ( self::patternMatches( $pattern, $url ) )
                return true;
        return false;
    }

    // ---- sitemaps ---------------------------------------------------------------------------------------------------

    /**
     * Reads a sitemap: an urlset (its pages with lastmod, changefreq and priority), a sitemap index (its sitemaps), or
     * a text sitemap (one address per line); gzip-compressed or not. A DOCTYPE is refused (no entities are read).
     *
     * @param string $body
     * @return array{urls: array, sitemaps: string[]} urls: list of array( loc, lastmod, changefreq, priority )
     * @throws expImportSourceException
     */
    public static function parseSitemap( $body )
    {
        $body = (string)$body;
        if ( substr( $body, 0, 2 ) === "\x1f\x8b" )
        {
            $plain = function_exists( 'gzdecode' ) ? @gzdecode( $body, 52428800 ) : false;
            if ( $plain === false )
                throw new expImportSourceException( 'the compressed sitemap cannot be read' );
            $body = $plain;
        }
        $body = (string)preg_replace( '/^\xEF\xBB\xBF/', '', $body );
        $out = array( 'urls' => array(), 'sitemaps' => array() );
        if ( ltrim( $body ) === '' )
            return $out;
        if ( ltrim( $body )[0] !== '<' )
        {
            foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line )
                if ( preg_match( '#^https?://\S+$#i', trim( $line ) ) )
                    $out['urls'][] = array( 'loc' => trim( $line ), 'lastmod' => '', 'changefreq' => '', 'priority' => '' );
            return $out;
        }
        if ( preg_match( '/<!DOCTYPE/i', $body ) )
            throw new expImportSourceException( 'a sitemap with a DOCTYPE is not read' );
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $ok = $doc->loadXML( $body, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok || !$doc->documentElement )
            throw new expImportSourceException( 'the sitemap is not valid XML' );
        $root = $doc->documentElement->localName;
        if ( $root !== 'urlset' && $root !== 'sitemapindex' )
            throw new expImportSourceException( "the file is no sitemap (its root element is $root)" );
        foreach ( $doc->documentElement->childNodes as $item )
        {
            if ( !$item instanceof DOMElement )
                continue;
            $fields = array( 'loc' => '', 'lastmod' => '', 'changefreq' => '', 'priority' => '' );
            foreach ( $item->childNodes as $child )
                if ( $child instanceof DOMElement && array_key_exists( $child->localName, $fields ) )
                    $fields[$child->localName] = trim( (string)$child->textContent );
            if ( $fields['loc'] === '' )
                continue;
            if ( $root === 'sitemapindex' && $item->localName === 'sitemap' )
                $out['sitemaps'][] = $fields['loc'];
            elseif ( $root === 'urlset' && $item->localName === 'url' )
                $out['urls'][] = $fields;
        }
        return $out;
    }

    /**
     * The sitemaps to start from: the address itself, or for a site's root or its robots.txt the Sitemap lines of
     * robots.txt (else /sitemap.xml).
     *
     * @param string $start
     * @return string[]
     */
    public function sitemapStarts( $start )
    {
        $path = (string)parse_url( (string)$start, PHP_URL_PATH );
        if ( $path !== '' && $path !== '/' && strtolower( basename( $path ) ) !== 'robots.txt' )
            return array( (string)$start );
        $base = rtrim( (string)preg_replace( '#^([a-z]+://[^/?\#]+).*$#is', '$1', (string)$start ), '/' );
        $out = array();
        foreach ( $this->robotsFor( $start )->sitemaps() as $sitemap )
            if ( self::site( $sitemap ) === self::site( $start ) )
                $out[] = $sitemap;
        return $out ? $out : array( $base . '/sitemap.xml' );
    }

    /**
     * The pages a sitemap (or a sitemap index, followed) lists on its own site, filtered by the patterns.
     *
     * @param string $start the sitemap, a sitemap index, a site's root or its robots.txt
     * @param int $max the most pages
     * @param string[] $include
     * @param string[] $exclude
     * @param float $delay
     * @return array list of array( loc, lastmod, changefreq, priority )
     * @throws expImportSourceException when the first sitemap cannot be read
     */
    public function sitemapEntries( $start, $max, array $include = array(), array $exclude = array(), $delay = 1.0 )
    {
        $refusal = self::refusal( $start );
        if ( $refusal !== null )
            throw new expImportSourceException( $refusal );
        $host = self::site( $start );
        $queue = $this->sitemapStarts( $start );
        $seenMaps = array();
        $seen = array();
        $entries = array();
        $first = true;
        while ( $queue && count( $entries ) < $max && count( $seenMaps ) < 100 )
        {
            $map = array_shift( $queue );
            if ( isset( $seenMaps[$map] ) || self::site( $map ) !== $host )
                continue;
            $seenMaps[$map] = true;
            try
            {
                $response = $this->get( $map, $delay );
                if ( $response['status'] !== 200 )
                    throw new expImportSourceException( "the sitemap $map answered HTTP {$response['status']}" );
                $parsed = self::parseSitemap( $response['body'] );
            }
            catch ( expImportSourceException $e )
            {
                if ( $first )
                    throw $e;
                continue;
            }
            $first = false;
            foreach ( $parsed['sitemaps'] as $child )
                $queue[] = self::resolve( $map, $child );
            foreach ( $parsed['urls'] as $entry )
            {
                $loc = self::resolve( $map, $entry['loc'] );
                if ( isset( $seen[$loc] ) || self::site( $loc ) !== $host || !self::urlWanted( $loc, $include, $exclude ) )
                    continue;
                $seen[$loc] = true;
                $entry['loc'] = $loc;
                $entries[] = $entry;
                if ( count( $entries ) >= $max )
                    break;
            }
        }
        return $entries;
    }

    // ---- pages ------------------------------------------------------------------------------------------------------

    /**
     * Reads the "picks" lines (field=selector) into field => parsed selector.
     *
     * @param string[] $lines
     * @return array
     * @throws expImportSourceException
     */
    public static function parsePicks( array $lines )
    {
        $out = array();
        foreach ( $lines as $line )
        {
            $line = trim( (string)$line );
            if ( $line === '' || $line[0] === '#' )
                continue;
            $eq = strpos( $line, '=' );
            if ( $eq === false )
                throw new expImportSourceException( "the pick '$line' is not field=selector" );
            $name = trim( substr( $line, 0, $eq ) );
            if ( !preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $name ) )
                throw new expImportSourceException( "the field name '$name' of a pick has other characters than letters, digits and _" );
            try
            {
                $out[$name] = expImportCssSelector::parse( substr( $line, $eq + 1 ) );
            }
            catch ( expImportSourceException $e )
            {
                throw new expImportSourceException( "pick $name: " . $e->getMessage() );
            }
        }
        return $out;
    }

    /**
     * Parses an HTML page with DOMDocument: in UTF-8 (by the charset of the Content-Type header, else of a meta
     * element, else UTF-8 when the text is valid UTF-8, else Windows-1252), without network access, with scripts,
     * styles and event attributes removed.
     *
     * @param string $html
     * @param string $contentType the Content-Type header, if any
     * @return DOMXPath
     */
    public static function loadHtml( $html, $contentType = '' )
    {
        $html = (string)$html;
        $charset = '';
        if ( preg_match( '/charset\s*=\s*["\']?([A-Za-z0-9_.:-]+)/i', (string)$contentType, $m ) )
            $charset = $m[1];
        elseif ( preg_match( '/<meta[^>]+charset\s*=\s*["\']?([A-Za-z0-9_.:-]+)/i', substr( $html, 0, 4096 ), $m ) )
            $charset = $m[1];
        if ( $charset !== '' && strtoupper( $charset ) !== 'UTF-8' && strtoupper( $charset ) !== 'UTF8' )
        {
            $converted = function_exists( 'mb_convert_encoding' ) ? @mb_convert_encoding( $html, 'UTF-8', $charset ) : @iconv( $charset, 'UTF-8//IGNORE', $html );
            $html = is_string( $converted ) ? $converted : expImportAbstractSource::toUtf8( $html );
        }
        else
            $html = expImportAbstractSource::toUtf8( $html );
        // every character above ASCII as an entity, so no charset declaration of the page can mislead the parser
        if ( function_exists( 'mb_encode_numericentity' ) )
            $html = mb_encode_numericentity( $html, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8' );
        else
            $html = '<?xml encoding="UTF-8">' . $html;
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $doc->loadHTML( $html !== '' ? $html : '<html></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $xpath = new DOMXPath( $doc );
        foreach ( iterator_to_array( $xpath->query( '//script | //style | //noscript | //template | //iframe | //object | //embed' ) ) as $node )
            $node->parentNode->removeChild( $node );
        foreach ( iterator_to_array( $xpath->query( '//@*' ) ) as $attr )
        {
            /** @var DOMAttr $attr */
            $name = strtolower( $attr->nodeName );
            if ( strpos( $name, 'on' ) === 0 || ( in_array( $name, array( 'href', 'src', 'action', 'formaction' ), true ) && preg_match( '/^\s*(javascript|vbscript|data):/i', $attr->value ) ) )
                $attr->ownerElement->removeAttribute( $attr->nodeName );
        }
        return $xpath;
    }

    /**
     * The values of the picks, in a node (the page or one item).
     *
     * @param DOMXPath $xpath
     * @param array $picks of parsePicks()
     * @param DOMNode|null $context
     * @param string $base the page's address, for relative addresses of href and src attributes
     * @return array field => a string, a list of strings for several matches, '' for none (body: its inner HTML when
     *               it holds elements, and body_text its text)
     */
    public static function applyPicks( DOMXPath $xpath, array $picks, ?DOMNode $context = null, $base = '' )
    {
        $out = array();
        foreach ( $picks as $name => $pick )
        {
            if ( $name === 'body' && !$pick['explicit'] )
            {
                $nodes = expImportCssSelector::nodes( $xpath, $pick, $context );
                $html = array();
                $text = array();
                foreach ( $nodes as $node )
                {
                    $html[] = expImportCssSelector::isHtmlish( $node ) ? expImportCssSelector::innerHtml( $node ) : htmlspecialchars( expImportCssSelector::text( $node ), ENT_QUOTES, 'UTF-8' );
                    $text[] = expImportCssSelector::text( $node );
                }
                $out['body'] = implode( "\n", $html );
                $out['body_text'] = implode( "\n", $text );
                continue;
            }
            $values = expImportCssSelector::values( $xpath, $pick, $context );
            // relative addresses of links and images made absolute (a meta content only when it is a path)
            if ( $pick['extract'] === 'attr' && $base !== '' && in_array( $pick['attr'], array( 'href', 'src', 'data-src', 'poster', 'content' ), true ) )
                foreach ( $values as $i => $value )
                    if ( $value !== '' && !preg_match( '#^([a-z][a-z0-9+.-]*:|\#)#i', $value ) && ( $pick['attr'] !== 'content' || $value[0] === '/' ) )
                        $values[$i] = self::resolve( $base, $value );
            $out[$name] = count( $values ) === 0 ? '' : ( count( $values ) === 1 ? $values[0] : $values );
        }
        return $out;
    }

    /**
     * The record of a page of a sitemap crawl: title, description, canonical, language and the picks.
     *
     * @param string $url
     * @param string $html
     * @param string $contentType
     * @param array $picks of parsePicks()
     * @return array
     */
    public static function pageFields( $url, $html, $contentType, array $picks )
    {
        $xpath = self::loadHtml( $html, $contentType );
        $base = self::baseOf( $xpath, $url );
        $first = function ( $query ) use ( $xpath )
        {
            $list = $xpath->query( $query );
            return $list && $list->length ? trim( (string)preg_replace( '/\s+/u', ' ', (string)$list->item( 0 )->nodeValue ) ) : '';
        };
        $lower = "translate(%s, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')";
        $canonical = $first( '//link[' . sprintf( $lower, '@rel' ) . "='canonical']/@href" );
        $fields = array(
            'title' => $first( '//title' ),
            'description' => $first( '//meta[' . sprintf( $lower, '@name' ) . "='description']/@content" ),
            'canonical' => $canonical !== '' ? self::resolve( $base, $canonical ) : '',
            'language' => $first( '/html/@lang' ),
        );
        return array_merge( $fields, self::applyPicks( $xpath, $picks, null, $base ) );
    }

    /** @return string the base of a page's relative addresses (its <base href>, else its address) */
    public static function baseOf( DOMXPath $xpath, $url )
    {
        $list = $xpath->query( '//base/@href' );
        $href = $list && $list->length ? trim( (string)$list->item( 0 )->nodeValue ) : '';
        return $href !== '' ? self::resolve( $url, $href ) : (string)$url;
    }

    /**
     * The records of one page of the scraper: one per "item" found (or the page itself without an item selector).
     *
     * @param DOMXPath $xpath
     * @param string $url
     * @param array|null $item the parsed item selector, or null
     * @param array $picks
     * @return array list of field => value (picks only)
     */
    public static function pageItems( DOMXPath $xpath, $url, $item, array $picks )
    {
        $base = self::baseOf( $xpath, $url );
        if ( !$item )
            return array( self::applyPicks( $xpath, $picks, null, $base ) );
        $out = array();
        foreach ( expImportCssSelector::nodes( $xpath, $item ) as $node )
            $out[] = self::applyPicks( $xpath, $picks, $node, $base );
        return $out;
    }

    /** @return bool whether a Content-Type is HTML (or none was given) */
    public static function isHtml( $contentType )
    {
        $type = strtolower( trim( (string)explode( ';', (string)$contentType )[0] ) );
        return $type === '' || $type === 'text/html' || $type === 'application/xhtml+xml';
    }

    // ---- the two crawls ---------------------------------------------------------------------------------------------

    /** @return int the pages a run may read: the option, within MaxPages and the sample's limit */
    protected function pageLimit( array $options, $default, $limit )
    {
        $max = isset( $options['max_pages'] ) && (int)$options['max_pages'] > 0 ? (int)$options['max_pages'] : $default;
        $max = min( $max, $this->maxPages );
        return $limit > 0 ? min( $max, (int)$limit ) : $max;
    }

    /**
     * The sitemap crawl: reads the pages a sitemap lists and writes one NDJSON line per page (url, url_hash, lastmod,
     * priority, changefreq, status, title, description, canonical, language, the picks), or an error line
     * (url, url_hash, status, _error) for a page that may not or could not be read.
     *
     * @param array $options validated options of expImportSitemapSource
     * @param string $target the NDJSON file
     * @param int $limit 0: the whole run; else the most records (the sample)
     * @return int lines written
     */
    public function crawlSitemap( array $options, $target, $limit = 0 )
    {
        $delay = isset( $options['delay'] ) ? (float)$options['delay'] : 1.0;
        $picks = self::parsePicks( isset( $options['picks'] ) ? (array)$options['picks'] : array() );
        $entries = $this->sitemapEntries( (string)$options['sitemap'], $this->pageLimit( $options, 200, $limit ),
                                          isset( $options['include'] ) ? (array)$options['include'] : array(),
                                          isset( $options['exclude'] ) ? (array)$options['exclude'] : array(), $delay );
        $out = self::open( $target );
        $n = 0;
        try
        {
            foreach ( $entries as $entry )
            {
                $record = array( 'url' => $entry['loc'], 'url_hash' => sha1( $entry['loc'] ), 'lastmod' => $entry['lastmod'],
                                 'priority' => $entry['priority'], 'changefreq' => $entry['changefreq'] );
                try
                {
                    $response = $this->get( $entry['loc'], $delay );
                    $record['status'] = $response['status'];
                    $type = isset( $response['headers']['content-type'] ) ? $response['headers']['content-type'] : '';
                    if ( $response['status'] < 200 || $response['status'] >= 300 )
                        $record['_error'] = 'the page answered HTTP ' . $response['status'];
                    elseif ( !self::isHtml( $type ) )
                        $record['_error'] = 'the page is no HTML page (' . $type . ')';
                    else
                        $record += self::pageFields( $response['url'], $response['body'], $type, $picks );
                }
                catch ( expImportSourceException $e )
                {
                    $record['status'] = 0;
                    $record['_error'] = $e->getMessage();
                }
                self::write( $out, $record );
                $n++;
            }
        }
        finally
        {
            fclose( $out );
        }
        return $n;
    }

    /**
     * The scraper: reads each address (and its "next" pages) and writes one NDJSON line per item (id, url, url_hash,
     * page, position, the picks), or an error line for a page that may not or could not be read.
     *
     * @param array $options validated options of expImportWebPageSource
     * @param string $target the NDJSON file
     * @param int $limit 0: the whole run; else the most records (the sample)
     * @return int lines written
     */
    public function scrapePages( array $options, $target, $limit = 0 )
    {
        $delay = isset( $options['delay'] ) ? (float)$options['delay'] : 1.0;
        $picks = self::parsePicks( isset( $options['picks'] ) ? (array)$options['picks'] : array() );
        $item = isset( $options['item'] ) && trim( (string)$options['item'] ) !== '' ? expImportCssSelector::parse( $options['item'] ) : null;
        $next = isset( $options['next'] ) && trim( (string)$options['next'] ) !== '' ? expImportCssSelector::parse( $options['next'] ) : null;
        if ( $next && $next['extract'] === 'text' && !$next['explicit'] )
            $next = array( 'extract' => 'attr', 'attr' => 'href' ) + $next;
        $maxPages = $this->pageLimit( $options, 20, 0 );
        $out = self::open( $target );
        $n = 0;
        $pages = 0;
        $seen = array();
        try
        {
            foreach ( isset( $options['urls'] ) ? (array)$options['urls'] : array() as $start )
            {
                $url = trim( (string)$start );
                $position = 0;
                $page = 0;
                while ( $url !== '' && $pages < $maxPages && ( $limit <= 0 || $n < $limit ) && !isset( $seen[$url] ) )
                {
                    $seen[$url] = true;
                    $pages++;
                    $page++;
                    $response = null;
                    try
                    {
                        $response = $this->get( $url, $delay );
                        $type = isset( $response['headers']['content-type'] ) ? $response['headers']['content-type'] : '';
                        if ( $response['status'] < 200 || $response['status'] >= 300 )
                            throw new expImportSourceException( 'the page answered HTTP ' . $response['status'] );
                        if ( !self::isHtml( $type ) )
                            throw new expImportSourceException( 'the page is no HTML page (' . $type . ')' );
                    }
                    catch ( expImportSourceException $e )
                    {
                        self::write( $out, array( 'id' => $url, 'url' => $url, 'url_hash' => sha1( $url ), 'page' => $page, 'position' => 0,
                                                  'status' => isset( $response['status'] ) ? $response['status'] : 0, '_error' => $e->getMessage() ) );
                        $n++;
                        break;
                    }
                    $xpath = self::loadHtml( $response['body'], $type );
                    foreach ( self::pageItems( $xpath, $response['url'], $item, $picks ) as $fields )
                    {
                        if ( $limit > 0 && $n >= $limit )
                            break;
                        $position++;
                        $id = $item ? $response['url'] . '#' . $position : $response['url'];
                        self::write( $out, array( 'id' => $id, 'url' => $response['url'], 'url_hash' => sha1( $id ), 'page' => $page,
                                                  'position' => $position, 'status' => $response['status'] ) + $fields );
                        $n++;
                    }
                    $url = '';
                    if ( $next )
                    {
                        $values = expImportCssSelector::values( $xpath, $next );
                        if ( $values && $values[0] !== '' )
                            $url = self::resolve( self::baseOf( $xpath, $response['url'] ), $values[0] );
                    }
                    unset( $response );
                }
            }
        }
        finally
        {
            fclose( $out );
        }
        return $n;
    }

    /** @return resource the NDJSON file, opened for writing */
    protected static function open( $target )
    {
        $out = @fopen( (string)$target, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'the snapshot of the crawl cannot be written' );
        return $out;
    }

    /** Writes one record as a line of JSON. */
    protected static function write( $out, array $record )
    {
        fwrite( $out, json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n" );
    }
}
