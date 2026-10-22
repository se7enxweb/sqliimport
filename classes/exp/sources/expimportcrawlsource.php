<?php
/**
 * File containing the expImportCrawlSource class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The common part of the formats that read public web pages (sitemap, webpage). They read their data themselves
 * (origin remote): fetch() crawls once per run, politely (expImportCrawler), and writes a snapshot as NDJSON, which
 * records() then reads like the JSON Lines format, with its cursors. A local file of the format itself (an example)
 * is read by readLocal() instead, without any request.
 */
abstract class expImportCrawlSource extends expImportJsonSource
{
    const NOTICE = 'Only public pages are read, and the terms of the site and its robots.txt apply: pages robots.txt '
                 . 'does not allow are not read (each is a failed row of the run), requests are spaced by the delay or '
                 . 'the Crawl-delay of the site, no cookies or credentials are sent and no JavaScript is run.';

    public function __construct( array $config )
    {
        parent::__construct( array( 'format' => 'ndjson' ) + $config );
    }

    public static function group()
    {
        return 'platform';
    }

    public static function extensions()
    {
        return array( 'ndjson' );
    }

    /** @return array the options both crawl formats have */
    protected static function crawlSchema()
    {
        return array(
            'picks' => array( 'type' => 'list', 'label' => 'Picks',
                              'help' => 'One field=selector per line: CSS (h1, .price, #main, a[rel=next], meta[property=og:image]::attr(content), '
                                      . 'img@src, article .content) or XPath (xpath://h1). A field body keeps the HTML of what it finds.' ),
            'max_pages' => array( 'type' => 'int', 'label' => 'Pages at most', 'min' => 1,
                                  'help' => 'The most pages one run reads (never more than sqliimport.ini [CrawlSettings] MaxPages)' ),
            'delay' => array( 'type' => 'float', 'label' => 'Pause between requests', 'default' => 1.0, 'min' => 0,
                              'help' => 'Seconds between two requests to the site (at least [CrawlSettings] MinDelay; a Crawl-delay of robots.txt is longer)' ),
        );
    }

    /**
     * The records of a local file of the format (no NDJSON snapshot): a list of data arrays.
     *
     * @param string $file
     * @return array
     */
    abstract protected function readLocal( $file );

    /** @return bool whether the file is a snapshot (NDJSON) rather than a file of the format itself */
    protected function isSnapshot( $file )
    {
        return !preg_match( '/\.(html?|xml|txt)$/i', $file );
    }

    public function records( array $cursor = array() )
    {
        $file = $this->file();
        if ( $this->isSnapshot( $file ) )
        {
            yield from parent::records( $cursor );
            return;
        }
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $list = $this->readLocal( $file );
        for ( $i = $index; $i < count( $list ); $i++ )
            yield new expImportRecord( $this->refOf( $list[$i], $i ), $i, $list[$i], array( 'index' => $i + 1 ) );
    }

    public function count()
    {
        $file = $this->file();
        return $this->isSnapshot( $file ) ? parent::count() : count( $this->readLocal( $file ) );
    }

    /**
     * @param array $options
     * @param string $target
     * @return expImportCrawler a crawler with the site's settings, its pages kept next to the snapshot
     */
    protected static function crawler( array $options, $target )
    {
        return expImportCrawler::fromSettings( dirname( (string)$target ) );
    }
}
