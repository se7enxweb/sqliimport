<?php
/**
 * File containing the expImportWebPageSource class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The web page scraper: one page or a list of addresses, optionally an "item" selector that repeats on a page (a
 * product of a list), so one page gives many records whose picks are relative to the item, and a "next" selector
 * followed up to the page limit. One record per item (or per page):
 *   id (the key: the address, with #position for an item), url, url_hash, page, position, status and the picks
 * A page that may not or could not be read is a failed row with the reason.
 */
class expImportWebPageSource extends expImportCrawlSource
{
    public static function identifier()
    {
        return 'webpage';
    }

    public static function label()
    {
        return 'Web page scraper';
    }

    public static function description()
    {
        return 'Fields picked by CSS or XPath selectors from public web pages: one record per page, or per repeating item '
             . '(a product, an event of a list) with the picks relative to it, following a "next page" link if asked. '
             . self::NOTICE;
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A product list of three items', 'file' => 'page-products.html',
                   'options' => array( 'urls' => 'https://example.org/products/', 'item' => '.product',
                                       'picks' => "name=h2\nprice=.price\nsku=@data-sku\nimage=img::attr(src)\nlink=a.more@href\nbody=.description" ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        $crawl = parent::crawlSchema();
        $crawl['max_pages']['default'] = 20;
        return array(
            'urls' => array( 'type' => 'list', 'label' => 'Addresses', 'required' => true,
                             'help' => 'One address (http or https) per line' ),
            'item' => array( 'type' => 'string', 'label' => 'Repeating item',
                             'help' => 'A selector of the element that repeats on a page (.product, article.event); empty: the page is one record' ),
            'next' => array( 'type' => 'string', 'label' => 'Next page',
                             'help' => 'A selector of the link to the next page (a[rel=next], .pager .next a); its href is followed' ),
        ) + $crawl + array(
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a record; id is its address' ),
        );
    }

    /**
     * Reads the pages into the snapshot (origin remote).
     *
     * @param array $options the validated options
     * @param string $target the NDJSON file to write
     * @param int $limit 0: the run; else the most records (the editor's sample)
     */
    public static function fetch( array $options, $target, $limit )
    {
        foreach ( isset( $options['urls'] ) ? (array)$options['urls'] : array() as $url )
            if ( ( $refusal = expImportCrawler::refusal( $url ) ) !== null )
                throw new expImportSourceException( "$url: $refusal" );
        self::crawler( $options, $target )->scrapePages( $options, $target, (int)$limit );
    }

    /** A local HTML file (an example): its items, as if it were the first address. */
    protected function readLocal( $file )
    {
        $urls = isset( $this->config['urls'] ) ? (array)$this->config['urls'] : array();
        $url = $urls ? (string)reset( $urls ) : 'https://example.org/' . basename( $file );
        $picks = expImportCrawler::parsePicks( isset( $this->config['picks'] ) ? (array)$this->config['picks'] : array() );
        $item = isset( $this->config['item'] ) && trim( (string)$this->config['item'] ) !== '' ? expImportCssSelector::parse( $this->config['item'] ) : null;
        $out = array();
        $position = 0;
        foreach ( expImportCrawler::pageItems( expImportCrawler::loadHtml( (string)file_get_contents( $file ) ), $url, $item, $picks ) as $fields )
        {
            $position++;
            $id = $item ? $url . '#' . $position : $url;
            $out[] = array( 'id' => $id, 'url' => $url, 'url_hash' => sha1( $id ), 'page' => 1, 'position' => $position, 'status' => 200 ) + $fields;
        }
        return $out;
    }
}
