<?php
/**
 * File containing the expImportShopifyCsvSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The product CSV export of Shopify (Products, Export). The rows that share a Handle are one product: the first row
 * holds the product, further rows add variants and images. One record per product:
 *
 *   id, handle            the Handle
 *   title, description (Body (HTML)), vendor, product_category, type, tags (a list), published, status, gift_card,
 *   seo_title, seo_description
 *   options               the option names ("Size", "Color")
 *   variants              a list of array( sku, price, compare_at_price, option1, option2, option3, grams,
 *                         inventory_qty, barcode, taxable, requires_shipping, weight_unit, tax_code, cost, image )
 *   sku, price, compare_at_price, taxable, tax_code   of the first variant; price_min and price_max of all
 *   currency              the option currency (the export does not name it)
 *   images                the image addresses in their order; image and image_alt the first one
 *   row                   the first row as read
 */
class expImportShopifyCsvSource extends expImportShopCsvSource
{
    public static function identifier()
    {
        return 'shopify';
    }

    public static function label()
    {
        return 'Shopify product CSV';
    }

    public static function extensions()
    {
        return array( 'csv' );
    }

    public static function description()
    {
        return 'The product export of a Shopify store. The rows of one handle become one product with title, description, '
             . 'vendor, type, tags, its variants (SKU, price, compare-at price, options) and its images.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Two products, one with three variants and two images', 'file' => 'products-shopify.csv', 'options' => array( 'currency' => 'USD' ) ) );
    }

    public static function preset()
    {
        return expImportCommerce::productPreset( 'shopify', 'sku' );
    }

    protected function checkHeaders( array $headers )
    {
        if ( !in_array( 'handle', $headers, true ) )
            throw new expImportSourceException( 'the file is not a Shopify product export (no column Handle)' );
    }

    protected function product( array $row )
    {
        $get = function ( $name ) use ( $row )
        {
            return isset( $row[$name] ) ? $row[$name] : '';
        };
        $options = array();
        foreach ( array( 1, 2, 3 ) as $i )
            if ( $get( "option$i name" ) !== '' && $get( "option$i name" ) !== 'Title' )
                $options[] = $get( "option$i name" );
        $product = array(
            'id' => $get( 'handle' ),
            'handle' => $get( 'handle' ),
            'title' => $get( 'title' ),
            'description' => $get( 'body (html)' ),
            'vendor' => $get( 'vendor' ),
            'product_category' => $get( 'product category' ),
            'type' => $get( 'type' ),
            'tags' => expImportCommerce::cellList( $get( 'tags' ) ),
            'published' => self::flag( $get( 'published' ) ),
            'status' => strtolower( $get( 'status' ) ),
            'gift_card' => self::flag( $get( 'gift card' ) ),
            'seo_title' => $get( 'seo title' ),
            'seo_description' => $get( 'seo description' ),
            'options' => $options,
            'variants' => array(),
            'images' => array(),
            'image_alts' => array(),
            'row' => $row,
        );
        $this->add( $product, $row );
        return $product;
    }

    protected function belongs( array $product, array $row )
    {
        $handle = isset( $row['handle'] ) ? $row['handle'] : '';
        return $handle === '' || $handle === $product['handle'];
    }

    protected function add( array &$product, array $row )
    {
        $get = function ( $name ) use ( $row )
        {
            return isset( $row[$name] ) ? $row[$name] : '';
        };
        if ( $get( 'variant sku' ) !== '' || $get( 'variant price' ) !== '' || $get( 'option1 value' ) !== '' )
        {
            $product['variants'][] = array(
                'sku' => $get( 'variant sku' ),
                'price' => expImportCommerce::decimal( $get( 'variant price' ) ),
                'compare_at_price' => expImportCommerce::decimal( $get( 'variant compare at price' ) ),
                'option1' => $get( 'option1 value' ) === 'Default Title' ? '' : $get( 'option1 value' ),
                'option2' => $get( 'option2 value' ),
                'option3' => $get( 'option3 value' ),
                'grams' => $get( 'variant grams' ),
                'inventory_qty' => $get( 'variant inventory qty' ),
                'barcode' => $get( 'variant barcode' ),
                'taxable' => self::flag( $get( 'variant taxable' ) ),
                'requires_shipping' => self::flag( $get( 'variant requires shipping' ) ),
                'weight_unit' => $get( 'variant weight unit' ),
                'tax_code' => $get( 'variant tax code' ),
                'cost' => expImportCommerce::decimal( $get( 'cost per item' ) ),
                'image' => $get( 'variant image' ),
            );
        }
        foreach ( array( $get( 'image src' ), $get( 'variant image' ) ) as $i => $src )
            if ( $src !== '' && !in_array( $src, $product['images'], true ) )
            {
                $product['images'][] = $src;
                $product['image_alts'][] = $i === 0 ? $get( 'image alt text' ) : '';
            }
    }

    protected function finish( array $product )
    {
        $first = $product['variants'] ? $product['variants'][0] : array( 'sku' => '', 'price' => '', 'compare_at_price' => '', 'taxable' => '', 'tax_code' => '' );
        $prices = array_values( array_filter( array_map( function ( $v ) { return $v['price']; }, $product['variants'] ), 'strlen' ) );
        usort( $prices, function ( $a, $b ) { return (float)$a <=> (float)$b; } );
        $data = array(
            'id' => $product['id'], 'handle' => $product['handle'], 'title' => $product['title'], 'description' => $product['description'],
            'vendor' => $product['vendor'], 'product_category' => $product['product_category'], 'type' => $product['type'], 'tags' => $product['tags'],
            'published' => $product['published'], 'status' => $product['status'], 'gift_card' => $product['gift_card'],
            'seo_title' => $product['seo_title'], 'seo_description' => $product['seo_description'],
            'options' => $product['options'],
            'variants' => $product['variants'],
            'sku' => $first['sku'],
            'price' => $first['price'],
            'compare_at_price' => $first['compare_at_price'],
            'price_min' => $prices ? $prices[0] : '',
            'price_max' => $prices ? $prices[count( $prices ) - 1] : '',
            'currency' => $this->currency(),
            'taxable' => $first['taxable'],
            'tax_code' => $first['tax_code'],
            'images' => $product['images'],
            'image' => $product['images'] ? $product['images'][0] : '',
            'image_alt' => $product['image_alts'] ? $product['image_alts'][0] : '',
            'row' => $product['row'],
        );
        return $data;
    }
}
