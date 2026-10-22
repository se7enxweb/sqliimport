<?php

use PHPUnit\Framework\TestCase;

/**
 * The crawl formats (sitemap, webpage) without the network: CSS selectors turned into XPath, the picks on the example
 * page, robots.txt, sitemaps and sitemap indexes, address patterns, and whole crawls through an injected fetcher.
 */
class CrawlTest extends TestCase
{
    protected static function examples(): string
    {
        return dirname( __DIR__, 2 ) . '/share/examples';
    }

    public function testCssToXPath(): void
    {
        $cases = array(
            'h1' => 'descendant-or-self::h1',
            '*' => 'descendant-or-self::*',
            'DIV' => 'descendant-or-self::div',
            '#main' => "descendant-or-self::*[@id='main']",
            '.price' => "descendant-or-self::*[contains(concat(' ', normalize-space(@class), ' '), ' price ')]",
            'p.price.sale' => "descendant-or-self::p[contains(concat(' ', normalize-space(@class), ' '), ' price ')][contains(concat(' ', normalize-space(@class), ' '), ' sale ')]",
            '[data-sku]' => 'descendant-or-self::*[@data-sku]',
            'a[rel=next]' => "descendant-or-self::a[@rel='next']",
            'meta[property="og:image"]' => "descendant-or-self::meta[@property='og:image']",
            "a[title='it\"s']" => "descendant-or-self::a[@title='it\"s']",
            'a[href^=https]' => "descendant-or-self::a[starts-with(@href, 'https')]",
            'a[href$=".pdf"]' => "descendant-or-self::a[substring(@href, string-length(@href) - 4 + 1)='.pdf']",
            'a[href*=shop]' => "descendant-or-self::a[contains(@href, 'shop')]",
            'p[class~=lead]' => "descendant-or-self::p[contains(concat(' ', normalize-space(@class), ' '), ' lead ')]",
            'html[lang|=en]' => "descendant-or-self::html[(@lang='en' or starts-with(@lang, 'en-'))]",
            'article .content' => "descendant-or-self::article/descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' content ')]",
            'ul > li' => 'descendant-or-self::ul/li',
            'ul>li' => 'descendant-or-self::ul/li',
            'h2 + p' => 'descendant-or-self::h2/following-sibling::*[1]/self::p',
            'h2 ~ p' => 'descendant-or-self::h2/following-sibling::p',
            'li:first-child' => 'descendant-or-self::li[not(preceding-sibling::*)]',
            'li:last-child' => 'descendant-or-self::li[not(following-sibling::*)]',
            'li:nth-child(3)' => 'descendant-or-self::li[count(preceding-sibling::*)=2]',
            'p:first-of-type' => 'descendant-or-self::p[not(preceding-sibling::p)]',
            'h1, h2' => 'descendant-or-self::h1 | descendant-or-self::h2',
            'a[title="a, b"]' => "descendant-or-self::a[@title='a, b']",
        );
        foreach ( $cases as $css => $xpath )
            $this->assertSame( $xpath, expImportCssSelector::toXPath( $css ), $css );
        $this->assertSame( "concat('a', \"'\", 'b\"c')", expImportCssSelector::literal( 'a\'b"c' ) );
    }

    public function testSelectorSuffixesAndErrors(): void
    {
        $cases = array(
            'meta[property=og:image]::attr(content)' => array( 'attr', 'content' ),
            'img@src' => array( 'attr', 'src' ),
            'img @src' => array( 'attr', 'src' ),
            'article::html' => array( 'html', '' ),
            'h1::text' => array( 'text', '' ),
            'h1' => array( 'text', '' ),
        );
        foreach ( $cases as $selector => $expected )
        {
            $parsed = expImportCssSelector::parse( $selector );
            $this->assertSame( $expected, array( $parsed['extract'], $parsed['attr'] ), $selector );
        }
        $this->assertSame( '//h1[1]', expImportCssSelector::parse( 'xpath://h1[1]' )['xpath'] );
        foreach ( array( '', '> p', 'p >', 'p:hover', 'a[href', '!x', 'h1,', 'xpath:' ) as $bad )
        {
            try
            {
                expImportCssSelector::parse( $bad );
                $this->fail( "'$bad' is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertNotSame( '', $e->getMessage() );
            }
        }
    }

    public function testPicksOnTheExamplePage(): void
    {
        $xpath = expImportCrawler::loadHtml( (string)file_get_contents( self::examples() . '/page-products.html' ), 'text/html; charset=utf-8' );
        $picks = expImportCrawler::parsePicks( array( 'name=h2', 'price=.price', 'sku=@data-sku', 'image=img::attr(src)', 'link=a.more@href',
                                                      'tags=.tags li', 'first_tag=.tags li:first-child', 'body=.description',
                                                      'title_x=xpath:.//h2', '# a comment', '' ) );
        $items = expImportCrawler::pageItems( $xpath, 'https://example.org/products/', expImportCssSelector::parse( 'article.product' ), $picks );
        $this->assertCount( 3, $items );
        $this->assertSame( 'Sencha Kyoto', $items[0]['name'] );
        $this->assertSame( '12.50 EUR', $items[0]['price'] );
        $this->assertSame( 'T-100', $items[0]['sku'], 'the item itself is searched too' );
        $this->assertSame( 'https://example.org/images/sencha.jpg', $items[0]['image'], 'a relative src is made absolute' );
        $this->assertSame( 'https://example.org/products/sencha-kyoto', $items[0]['link'] );
        $this->assertSame( array( 'green', 'japan' ), $items[0]['tags'], 'several matches are a list' );
        $this->assertSame( 'green', $items[0]['first_tag'] );
        $this->assertSame( '<p>A <strong>fresh</strong> green tea from Kyoto.</p><p>Steep for two minutes.</p>', $items[0]['body'] );
        $this->assertSame( 'A fresh green tea from Kyoto. Steep for two minutes.', $items[0]['body_text'] );
        $this->assertSame( 'Sencha Kyoto', $items[0]['title_x'] );
        $this->assertSame( 'black', $items[1]['tags'] );
        $this->assertSame( 'Caffeine free, with vanilla – über gut.', $items[2]['body'], 'text only stays text, UTF-8 kept' );
        $this->assertSame( 'https://example.org/products/rooibos-vanilla', $items[2]['link'] );
        $page = expImportCrawler::pageFields( 'https://example.org/products/', (string)file_get_contents( self::examples() . '/page-products.html' ), 'text/html',
                                              expImportCrawler::parsePicks( array( 'heading=h1', 'next=a[rel=next]@href', 'missing=.nothing' ) ) );
        $this->assertSame( 'Teas – Example Tea Shop', $page['title'], 'the script never ran' );
        $this->assertSame( 'The teas of a fictitious shop, as an example page for the web page scraper.', $page['description'] );
        $this->assertSame( 'https://example.org/products/', $page['canonical'] );
        $this->assertSame( 'en', $page['language'] );
        $this->assertSame( 'Our teas', $page['heading'] );
        $this->assertSame( 'https://example.org/products/?page=2', $page['next'] );
        $this->assertSame( '', $page['missing'] );
        $html = $xpath->document->saveHTML();
        $this->assertStringNotContainsString( '<script', $html );
        $this->assertStringNotContainsString( 'onclick', $html );
        foreach ( array( array( 'nofield' ), array( 'bad name=h1' ), array( 'x=p:hover' ) ) as $bad )
        {
            try
            {
                expImportCrawler::parsePicks( $bad );
                $this->fail( implode( ',', $bad ) . ' is refused' );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertNotSame( '', $e->getMessage() );
            }
        }
    }

    public function testCharsets(): void
    {
        $latin = "<html><head><meta charset=\"iso-8859-1\"><title>Caf\xE9</title></head><body><p>gr\xFC\xDF</p></body></html>";
        $this->assertSame( 'Café', expImportCrawler::pageFields( 'https://example.org/', $latin, '', array() )['title'] );
        $this->assertSame( 'Café', expImportCrawler::pageFields( 'https://example.org/', str_replace( '<meta charset="iso-8859-1">', '', $latin ), 'text/html; charset=ISO-8859-1', array() )['title'] );
        $utf8 = "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"><title>Grüße</title></head></html>";
        $this->assertSame( 'Grüße', expImportCrawler::pageFields( 'https://example.org/', $utf8, '', array() )['title'] );
    }

    public function testRobots(): void
    {
        $robots = new expImportRobots( implode( "\n", array(
            '# example',
            'User-agent: *',
            'Disallow: /private/',
            'Allow: /private/open',
            'Disallow: /*.pdf$',
            'Disallow: /search?',
            'Crawl-delay: 2',
            '',
            'User-agent: BadBot',
            'User-agent: OtherBot',
            'Disallow: /',
            '',
            'User-agent: 7x SQLI Import',
            'Disallow: /drafts',
            'Allow: /drafts/public$',
            'Crawl-delay: 5',
            'Sitemap: https://example.org/sitemap.xml',
            'Sitemap: https://example.org/news-sitemap.xml',
        ) ) );
        $other = 'SomeCrawler/1.0';
        $cases = array(
            array( '/', true ),
            array( '/private/x', false ),
            array( '/private/open/page', true ),
            array( '/files/a.pdf', false ),
            array( '/files/a.pdf?x=1', true ),
            array( '/search?q=tea', false ),
            array( '/search', true ),
            array( 'https://example.org/private/', false ),
            array( '/robots.txt', true ),
        );
        foreach ( $cases as $case )
            $this->assertSame( $case[1], $robots->isAllowed( $case[0], $other ), $case[0] );
        $this->assertSame( 2.0, $robots->crawlDelay( $other ) );
        $this->assertFalse( $robots->isAllowed( '/anything', 'Mozilla/5.0 (compatible; BadBot/2.0)' ) );
        $this->assertFalse( $robots->isAllowed( '/anything', 'otherbot' ), 'two agents share a group' );
        $ours = '7x SQLI Import (Exponential; +https://example.net)';
        $this->assertTrue( $robots->isAllowed( '/private/x', $ours ), 'our own group replaces *' );
        $this->assertFalse( $robots->isAllowed( '/drafts/a', $ours ) );
        $this->assertTrue( $robots->isAllowed( '/drafts/public', $ours ) );
        $this->assertFalse( $robots->isAllowed( '/drafts/public/x', $ours ) );
        $this->assertSame( 5.0, $robots->crawlDelay( $ours ) );
        $this->assertSame( array( 'https://example.org/sitemap.xml', 'https://example.org/news-sitemap.xml' ), $robots->sitemaps() );
        // equal length: Allow wins; percent escapes compared normalised
        $tie = new expImportRobots( "User-agent: *\nDisallow: /page\nAllow: /page\nDisallow: /a%3cd" );
        $this->assertTrue( $tie->isAllowed( '/page', $other ) );
        $this->assertFalse( $tie->isAllowed( '/a%3Cd', $other ) );
        $this->assertTrue( ( new expImportRobots( "User-agent: *\nDisallow:" ) )->isAllowed( '/x', $other ), 'an empty Disallow allows' );
        $this->assertTrue( ( new expImportRobots( '' ) )->isAllowed( '/x', $other ) );
        $this->assertTrue( expImportRobots::allowAll()->isAllowed( '/x', $other ) );
        $this->assertFalse( expImportRobots::disallowAll()->isAllowed( '/x', $other ) );
        $this->assertTrue( expImportRobots::matches( '/*/edit$', '/a/b/edit' ) );
        $this->assertFalse( expImportRobots::matches( '/*/edit$', '/a/b/edit/x' ) );
        $this->assertTrue( expImportRobots::matches( '/fish*', '/fish.html' ) );
        $this->assertFalse( expImportRobots::matches( '/fish', '/Fish' ), 'paths are case-sensitive' );
    }

    public function testSitemapParsing(): void
    {
        $parsed = expImportCrawler::parseSitemap( (string)file_get_contents( self::examples() . '/sitemap.xml' ) );
        $this->assertCount( 4, $parsed['urls'] );
        $this->assertSame( array(), $parsed['sitemaps'] );
        $this->assertSame( array( 'loc' => 'https://example.org/news/green-tea-harvest', 'lastmod' => '2026-09-28T09:30:00+00:00', 'changefreq' => '', 'priority' => '0.8' ),
                           $parsed['urls'][1] );
        $index = '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
               . '<sitemap><loc>https://example.org/s1.xml</loc><lastmod>2026-01-01</lastmod></sitemap>'
               . '<sitemap><loc> https://example.org/s2.xml.gz </loc></sitemap></sitemapindex>';
        $parsed = expImportCrawler::parseSitemap( $index );
        $this->assertSame( array( 'https://example.org/s1.xml', 'https://example.org/s2.xml.gz' ), $parsed['sitemaps'] );
        $this->assertSame( array(), $parsed['urls'] );
        $this->assertCount( 4, expImportCrawler::parseSitemap( (string)gzencode( (string)file_get_contents( self::examples() . '/sitemap.xml' ) ) )['urls'], 'gzip' );
        $this->assertCount( 2, expImportCrawler::parseSitemap( "https://example.org/a\nnot a url\nhttps://example.org/b\n" )['urls'], 'a text sitemap' );
        foreach ( array( '<!DOCTYPE x [<!ENTITY e "x">]><urlset/>', '<rss><channel/></rss>', '<urlset><url>' ) as $bad )
        {
            try
            {
                expImportCrawler::parseSitemap( $bad );
                $this->fail( "$bad is refused" );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertNotSame( '', $e->getMessage() );
            }
        }
        // the local example reads as the pages it lists
        $source = new expImportSitemapSource( array( 'file' => self::examples() . '/sitemap.xml', 'key' => 'url' ) );
        $refs = array();
        foreach ( $source->records() as $record )
            $refs[] = $record->ref;
        $this->assertSame( 'https://example.org/about/', $refs[3] );
        $this->assertSame( 4, $source->count() );
    }

    public function testUrlPatterns(): void
    {
        $url = 'https://example.org/blog/2026/tea.html?x=1';
        $cases = array(
            array( 'https://example.org/blog/*', true ),
            array( '/blog/*', true ),
            array( '/blog/*.html', false ),
            array( '/blog/*.html?*', true ),
            array( '/BLOG/*', true ),
            array( '/news/*', false ),
            array( 'https://example.org/blog/????/*', true ),
            array( 'regex:#/blog/\d{4}/#', true ),
            array( 'regex:/blog/\d{4}/', true ),
            array( 'regex:^https://example\.org/news', false ),
        );
        foreach ( $cases as $case )
            $this->assertSame( $case[1], expImportCrawler::patternMatches( $case[0], $url ), $case[0] );
        $this->assertTrue( expImportCrawler::urlWanted( $url, array(), array() ) );
        $this->assertTrue( expImportCrawler::urlWanted( $url, array( '/news/*', '/blog/*' ), array() ) );
        $this->assertFalse( expImportCrawler::urlWanted( $url, array( '/blog/*' ), array( '*tea*' ) ), 'exclude wins' );
        $this->assertFalse( expImportCrawler::urlWanted( $url, array( '/news/*' ), array() ) );
        $this->expectException( expImportSourceException::class );
        expImportCrawler::patternMatches( 'regex:(', $url );
    }

    public function testResolve(): void
    {
        $base = 'https://example.org/a/b/c?q=1';
        $cases = array(
            'd' => 'https://example.org/a/b/d',
            '../d' => 'https://example.org/a/d',
            '../../../d' => 'https://example.org/d',
            '/d?x' => 'https://example.org/d?x',
            '?page=2' => 'https://example.org/a/b/c?page=2',
            '#top' => 'https://example.org/a/b/c?q=1#top',
            '//cdn.example.net/x.js' => 'https://cdn.example.net/x.js',
            'http://other.example/' => 'http://other.example/',
            './' => 'https://example.org/a/b/',
        );
        foreach ( $cases as $ref => $expected )
            $this->assertSame( $expected, expImportCrawler::resolve( $base, $ref ), $ref );
        $this->assertNotNull( expImportCrawler::refusal( 'https://user:secret@example.org/' ) );
        $this->assertNotNull( expImportCrawler::refusal( 'ftp://example.org/' ) );
        $this->assertNull( expImportCrawler::refusal( 'https://example.org/' ) );
        $this->assertSame( expImportCrawler::site( 'https://www.example.org/sitemap.xml' ), expImportCrawler::site( 'http://example.org:8080/a' ) );
        $this->assertNotSame( expImportCrawler::site( 'https://example.org/' ), expImportCrawler::site( 'https://shop.example.org/' ) );
    }

    /**
     * A crawler on a fictitious site: the fetcher answers from a table and records every request, the sleeper the
     * pauses.
     *
     * @return array{0: expImportCrawler, 1: ArrayObject, 2: ArrayObject}
     */
    protected function crawler( array $site ): array
    {
        $requests = new ArrayObject();
        $pauses = new ArrayObject();
        $crawler = new expImportCrawler();
        $crawler->userAgent = '7x SQLI Import (Exponential; +https://example.net)';
        $crawler->minDelay = 0.5;
        $crawler->fetcher = function ( $url ) use ( $site, $requests )
        {
            $requests[] = $url;
            if ( !isset( $site[$url] ) )
                return array( 'status' => 404, 'headers' => array( 'Content-Type' => 'text/html' ), 'body' => 'not found' );
            $answer = $site[$url];
            return is_array( $answer ) ? $answer : array( 'status' => 200, 'headers' => array( 'Content-Type' => 'text/html; charset=utf-8' ), 'body' => $answer );
        };
        $crawler->sleeper = function ( $seconds ) use ( $pauses )
        {
            $pauses[] = $seconds;
        };
        return array( $crawler, $requests, $pauses );
    }

    /** @return array[] the lines of an NDJSON file */
    protected static function lines( string $file ): array
    {
        $out = array();
        foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
            $out[] = json_decode( $line, true );
        return $out;
    }

    protected static function target(): string
    {
        return (string)tempnam( sys_get_temp_dir(), 'sqliimport-test-crawl-' );
    }

    public function testSitemapCrawl(): void
    {
        $page = function ( $title, $body ) { return "<html lang=\"en\"><head><title>$title</title><meta name=\"description\" content=\"About $title\"></head><body><article><div class=\"content\">$body</div></article></body></html>"; };
        list( $crawler, $requests, $pauses ) = $this->crawler( array(
            'https://example.org/robots.txt' => array( 'status' => 200, 'headers' => array(), 'body' => "User-agent: *\nDisallow: /news/new-tea-room\nCrawl-delay: 2\nSitemap: https://example.org/sitemap_index.xml" ),
            'https://example.org/sitemap_index.xml' => array( 'status' => 200, 'headers' => array(), 'body' => '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.org/sitemap.xml</loc></sitemap><sitemap><loc>https://elsewhere.example/sitemap.xml</loc></sitemap></sitemapindex>' ),
            'https://example.org/sitemap.xml' => array( 'status' => 200, 'headers' => array(), 'body' => (string)file_get_contents( self::examples() . '/sitemap.xml' ) ),
            'https://example.org/' => $page( 'Home', '<p>Welcome</p>' ),
            'https://example.org/news/green-tea-harvest' => array( 'status' => 301, 'headers' => array( 'Location' => '/news/harvest' ), 'body' => '' ),
            'https://example.org/news/harvest' => $page( 'Harvest', '<p>First <em>flush</em></p>' ),
            'https://example.org/about/' => array( 'status' => 200, 'headers' => array( 'Content-Type' => 'application/pdf' ), 'body' => '%PDF' ),
        ) );
        $target = self::target();
        try
        {
            $n = $crawler->crawlSitemap( array( 'sitemap' => 'https://example.org/', 'include' => array(), 'exclude' => array(), 'max_pages' => 200, 'delay' => 1.0,
                                                'picks' => array( 'body=article .content' ) ), $target );
            $lines = self::lines( $target );
            $this->assertSame( 4, $n );
            $this->assertSame( array( 'https://example.org/', 'https://example.org/news/green-tea-harvest', 'https://example.org/news/new-tea-room', 'https://example.org/about/' ),
                               array_column( $lines, 'url' ) );
            $this->assertSame( 'Home', $lines[0]['title'] );
            $this->assertSame( 'About Home', $lines[0]['description'] );
            $this->assertSame( 'en', $lines[0]['language'] );
            $this->assertSame( '1.0', $lines[0]['priority'] );
            $this->assertSame( sha1( 'https://example.org/' ), $lines[0]['url_hash'] );
            $this->assertSame( '<p>Welcome</p>', $lines[0]['body'] );
            $this->assertSame( 'Harvest', $lines[1]['title'], 'a redirect is followed' );
            $this->assertSame( '<p>First <em>flush</em></p>', $lines[1]['body'] );
            $this->assertStringContainsString( 'robots.txt', $lines[2]['_error'], 'a page robots.txt disallows is an error line' );
            $this->assertStringContainsString( 'no HTML', $lines[3]['_error'] );
            $this->assertNotContains( 'https://example.org/news/new-tea-room', (array)$requests, 'a disallowed page is never requested' );
            $this->assertNotContains( 'https://elsewhere.example/sitemap.xml', (array)$requests, 'only sitemaps of the same host' );
            $this->assertSame( 'https://example.org/robots.txt', $requests[0], 'robots.txt first, once' );
            $this->assertSame( 1, count( array_keys( (array)$requests, 'https://example.org/robots.txt' ) ) );
            foreach ( $pauses as $pause )
                $this->assertGreaterThan( 1.0, $pause, 'the Crawl-delay of 2 seconds is kept' );
            $this->assertGreaterThanOrEqual( count( $requests ) - 2, count( $pauses ) );

            // records() of the source read the snapshot; an error line is a failed row
            $source = new expImportSitemapSource( array( 'file' => $target, 'key' => 'url' ) );
            $records = iterator_to_array( $source->records(), false );
            $this->assertSame( 'https://example.org/', $records[0]->ref );
            $this->assertSame( '', $records[0]->sourceError() );
            $this->assertNotSame( '', $records[2]->sourceError() );
            $rest = iterator_to_array( $source->records( $records[1]->cursor ), false );
            $this->assertSame( 'https://example.org/news/new-tea-room', $rest[0]->ref, 'the cursor resumes' );

            // the limit (the editor's sample) and the patterns
            list( $crawler ) = $this->crawler( array(
                'https://example.org/sitemap.xml' => array( 'status' => 200, 'headers' => array(), 'body' => (string)file_get_contents( self::examples() . '/sitemap.xml' ) ),
            ) );
            $this->assertSame( 1, $crawler->crawlSitemap( array( 'sitemap' => 'https://example.org/sitemap.xml', 'include' => array( '/news/*' ), 'exclude' => array( '*harvest*' ),
                                                                  'max_pages' => 200, 'delay' => 0.0, 'picks' => array() ), $target, 5 ) );
            $this->assertSame( 'https://example.org/news/new-tea-room', self::lines( $target )[0]['url'] );
            $crawler->maxPages = 2;
            $this->assertSame( 2, $crawler->crawlSitemap( array( 'sitemap' => 'https://example.org/sitemap.xml', 'max_pages' => 50, 'delay' => 0.0 ), $target ), 'MaxPages caps' );
        }
        finally
        {
            unlink( $target );
        }
    }

    public function testRobotsThatCannotBeReadAndAFirstSitemapThatFails(): void
    {
        list( $crawler, $requests ) = $this->crawler( array(
            'https://example.org/robots.txt' => array( 'status' => 503, 'headers' => array(), 'body' => '' ),
        ) );
        $this->assertFalse( $crawler->allowed( 'https://example.org/x' ), 'a robots.txt answering 5xx allows nothing' );
        list( $crawler ) = $this->crawler( array() );
        $this->assertTrue( $crawler->allowed( 'https://example.org/x' ), 'a missing robots.txt allows everything' );
        $this->expectException( expImportSourceException::class );
        $crawler->sitemapEntries( 'https://example.org/sitemap.xml', 10 );
    }

    public function testScraper(): void
    {
        $products = (string)file_get_contents( self::examples() . '/page-products.html' );
        $second = '<html><body><article class="product" data-sku="T-400"><h2>Oolong</h2><p class="price">14.00 EUR</p></article></body></html>';
        list( $crawler, $requests ) = $this->crawler( array(
            'https://example.org/robots.txt' => array( 'status' => 200, 'headers' => array(), 'body' => "User-agent: *\nDisallow: /secret" ),
            'https://example.org/products/' => $products,
            'https://example.org/products/?page=2' => $second,
        ) );
        $target = self::target();
        try
        {
            $options = array( 'urls' => array( 'https://example.org/products/', 'https://example.org/secret/list' ), 'item' => '.product', 'next' => 'a[rel=next]',
                              'picks' => array( 'name=h2', 'price=.price', 'sku=@data-sku' ), 'max_pages' => 20, 'delay' => 1.0 );
            $this->assertSame( 5, $crawler->scrapePages( $options, $target ) );
            $lines = self::lines( $target );
            $this->assertSame( array( 'Sencha Kyoto', 'Darjeeling First Flush', 'Rooibos Vanilla', 'Oolong' ), array_column( array_slice( $lines, 0, 4 ), 'name' ) );
            $this->assertSame( array( 1, 2, 3, 4 ), array_column( array_slice( $lines, 0, 4 ), 'position' ) );
            $this->assertSame( 2, $lines[3]['page'] );
            $this->assertSame( 'https://example.org/products/?page=2#4', $lines[3]['id'] );
            $this->assertSame( 'T-400', $lines[3]['sku'] );
            $this->assertStringContainsString( 'robots.txt', $lines[4]['_error'] );
            $this->assertNotContains( 'https://example.org/secret/list', (array)$requests );
            // the sample: the limit counts records
            list( $crawler ) = $this->crawler( array( 'https://example.org/products/' => $products ) );
            $this->assertSame( 2, $crawler->scrapePages( $options, $target, 2 ) );
            // one page, no item: one record of the page
            list( $crawler ) = $this->crawler( array( 'https://example.org/products/' => $products ) );
            $this->assertSame( 1, $crawler->scrapePages( array( 'urls' => array( 'https://example.org/products/' ), 'picks' => array( 'heading=h1', 'names=h2' ) ), $target ) );
            $line = self::lines( $target )[0];
            $this->assertSame( 'Our teas', $line['heading'] );
            $this->assertCount( 3, $line['names'] );
            $this->assertSame( 'https://example.org/products/', $line['id'] );
        }
        finally
        {
            unlink( $target );
        }
        // the example page reads locally through the source, with the example's options
        $example = expImportWebPageSource::examples()[0];
        list( $values, $errors ) = expImportOptionSchema::validate( expImportWebPageSource::schema(), $example['options'] );
        $this->assertSame( array(), $errors );
        $records = iterator_to_array( ( new expImportWebPageSource( $values + array( 'file' => self::examples() . '/page-products.html' ) ) )->records(), false );
        $this->assertCount( 3, $records );
        $this->assertSame( 'https://example.org/products/#2', $records[1]->ref );
        $this->assertSame( 'T-200', $records[1]->data['sku'] );
    }

    public function testCatalogueEntries(): void
    {
        foreach ( array( 'expImportSitemapSource' => 'sitemap', 'expImportWebPageSource' => 'webpage' ) as $class => $id )
        {
            $entry = ( new expImportSourceEntry( $class ) )->toArray();
            $this->assertSame( array( 'remote' ), $entry['origins'], $id );
            $this->assertSame( 'platform', $entry['group'] );
            $this->assertStringContainsString( 'robots.txt', $entry['description'] );
            $this->assertSame( array(), expImportOptionSchema::check( $entry['schema'] ) );
        }
        list( , $errors ) = expImportOptionSchema::validate( expImportSitemapSource::schema(), array() );
        $this->assertArrayHasKey( 'sitemap', $errors, 'a sitemap is required' );
        list( $values ) = expImportOptionSchema::validate( expImportSitemapSource::schema(), array( 'sitemap' => 'https://example.org/' ) );
        $this->assertSame( 200, $values['max_pages'] );
        $this->assertSame( 1.0, $values['delay'] );
        $this->assertSame( 'page-{url_hash}', expImportSitemapSource::preset()['match']['pattern'] );
    }
}
