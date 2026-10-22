<?php
/**
 * File containing the expImportSitemapSource class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The sitemap crawl: the pages a site's sitemap lists (a sitemap, a sitemap index, or the Sitemap lines of
 * robots.txt when a site's root is given), filtered by address patterns, read politely, one record per page:
 *   url (the key), url_hash (sha1 of the address, for the remote ID), lastmod, priority and changefreq of the
 *   sitemap, status, title, description, canonical, language, and every pick (body: the inner HTML, body_text its text)
 * A page that may not or could not be read is a failed row with the reason.
 */
class expImportSitemapSource extends expImportCrawlSource
{
    public static function identifier()
    {
        return 'sitemap';
    }

    public static function label()
    {
        return 'Pages of a site by its sitemap';
    }

    public static function description()
    {
        return 'The public pages of a site, found by its sitemap.xml (or a sitemap index, or the Sitemap lines of its robots.txt): '
             . 'one record per page with address, last change and priority of the sitemap, title, description, canonical address, '
             . 'language and the fields picked by CSS or XPath selectors. ' . self::NOTICE;
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'The snapshot of a crawl: three pages read, one refused by robots.txt', 'file' => 'sitemap-crawl.ndjson',
                   'options' => array( 'sitemap' => 'https://example.org/sitemap.xml' ) ),
            array( 'label' => 'A sitemap of four pages (its pages are listed, not read)', 'file' => 'sitemap.xml',
                   'options' => array( 'sitemap' => 'https://example.org/sitemap.xml' ) ),
        );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'page-{url_hash}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title', 'transforms' => array( array( 'name' => 'strip_tags' ), array( 'name' => 'trim' ) ) ),
                array( 'source' => 'description', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'body', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
            ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        $crawl = parent::crawlSchema();
        $crawl['max_pages']['default'] = 200;
        return array(
            'sitemap' => array( 'type' => 'url', 'label' => 'Sitemap', 'required' => true,
                                'help' => 'The address of sitemap.xml or a sitemap index; a site\'s root (https://example.org/) reads the Sitemap lines of its robots.txt' ),
            'include' => array( 'type' => 'list', 'label' => 'Only addresses like',
                                'help' => 'One pattern per line: a glob (https://example.org/blog/*, or /blog/* for the path) or regex:<expression>; empty: every page' ),
            'exclude' => array( 'type' => 'list', 'label' => 'Never addresses like', 'help' => 'Patterns as above' ),
        ) + $crawl + array(
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'url', 'help' => 'The field that names a page; url is its address' ),
        );
    }

    /**
     * Crawls the pages of the sitemap into the snapshot (origin remote).
     *
     * @param array $options the validated options
     * @param string $target the NDJSON file to write
     * @param int $limit 0: the run; else the most pages (the editor's sample)
     */
    public static function fetch( array $options, $target, $limit )
    {
        self::crawler( $options, $target )->crawlSitemap( $options, $target, (int)$limit );
    }

    /** A local sitemap file: its pages, listed and not read. */
    protected function readLocal( $file )
    {
        $out = array();
        foreach ( expImportCrawler::parseSitemap( (string)file_get_contents( $file ) )['urls'] as $entry )
            $out[] = array( 'url' => $entry['loc'], 'url_hash' => sha1( $entry['loc'] ), 'lastmod' => $entry['lastmod'],
                            'priority' => $entry['priority'], 'changefreq' => $entry['changefreq'] );
        return $out;
    }
}
