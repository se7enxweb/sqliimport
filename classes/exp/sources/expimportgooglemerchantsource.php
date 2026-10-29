<?php
/**
 * File containing the expImportGoogleMerchantSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Google Merchant Center product feeds: RSS 2.0 (or Atom) with the g: namespace, as shops export their catalogue for
 * Google Shopping and many price comparison sites read it. Every item is one product:
 *
 *   id, item_group_id, title, description, link, mobile_link
 *   image_link (also as image), additional_image_links (a list), images (all of them)
 *   price, currency       the amount as a decimal string ("1299.00") and the ISO 4217 code; price_text as written
 *   sale_price, sale_price_currency, sale_price_effective_date
 *   availability, availability_date, condition, brand, gtin, mpn, identifier_exists
 *   product_type (as written), categories (product_type split at ">"), google_product_category
 *   color, size, gender, age_group, material, pattern
 *   shipping              a list of array( country, region, service, price, currency ); shipping_weight
 *   tax                   a list of array( country, region, rate, tax_ship ); vat the rate of the first one
 *   tags                  the custom labels (custom_label_0 to custom_label_4) that are set
 *   raw                   the raw element
 *
 * The fields are found with the prefix g: and also without one (some feeds write <price> instead of <g:price>).
 */
class expImportGoogleMerchantSource extends expImportXmlSource
{
    public static function identifier()
    {
        return 'google_merchant';
    }

    public static function label()
    {
        return 'Google Merchant product feed';
    }

    public static function extensions()
    {
        return array( 'xml', 'rss' );
    }

    public static function description()
    {
        return 'The product feed shops make for Google Merchant Center and price comparison sites (RSS 2.0 with the g: '
             . 'namespace). Every item is a product with ID, title, description, link, images, price and sale price with '
             . 'currency, availability, brand, GTIN, MPN, categories, shipping and tax.';
    }

    public static function group()
    {
        return 'commerce';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A feed of three products with prices, a sale price, shipping and tax', 'file' => 'products-google.xml' ) );
    }

    public static function preset()
    {
        return expImportCommerce::productPreset( 'gmc', 'id' );
    }

    public static function schema()
    {
        return array(
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a product; id is g:id' ),
        );
    }

    /** @return string[] item for RSS, entry for Atom */
    protected function recordPath()
    {
        $head = (string)@file_get_contents( $this->file(), false, null, 0, 4096 );
        $atom = preg_match( '/<(\w+:)?feed\b/', $head ) && !preg_match( '/<rss\b/', $head );
        $this->detected['format'] = $atom ? 'atom' : 'rss';
        return array( $atom ? 'entry' : 'item' );
    }

    public function records( array $cursor = array() )
    {
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        foreach ( parent::records( $cursor ) as $record )
        {
            $data = self::normalise( $record->data );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, $record->cursor );
            $index++;
        }
    }

    /**
     * A feed item as the product fields.
     * @param array $raw
     * @return array
     */
    public static function normalise( array $raw )
    {
        $text = function ( $v )
        {
            if ( is_array( $v ) )
            {
                if ( array_key_exists( 0, $v ) )
                    $v = $v[0];
                if ( is_array( $v ) )
                    return isset( $v['#text'] ) ? trim( (string)$v['#text'] ) : ( isset( $v['@href'] ) ? trim( (string)$v['@href'] ) : '' );
            }
            return trim( (string)$v );
        };
        $node = function ( $name ) use ( $raw )
        {
            if ( isset( $raw['g:' . $name] ) )
                return $raw['g:' . $name];
            return isset( $raw[$name] ) ? $raw[$name] : null;
        };
        $get = function ( $name ) use ( $node, $text )
        {
            $v = $node( $name );
            return $v === null ? '' : $text( $v );
        };
        $all = function ( $name ) use ( $node, $text )
        {
            $v = $node( $name );
            if ( $v === null )
                return array();
            $list = is_array( $v ) && array_key_exists( 0, $v ) ? $v : array( $v );
            return array_values( array_filter( array_map( $text, $list ), 'strlen' ) );
        };
        $groups = function ( $name, array $parts ) use ( $node, $text )
        {
            $v = $node( $name );
            if ( $v === null )
                return array();
            $list = is_array( $v ) && array_key_exists( 0, $v ) ? $v : array( $v );
            $out = array();
            foreach ( $list as $item )
            {
                $row = array();
                foreach ( $parts as $part )
                {
                    $value = '';
                    if ( is_array( $item ) )
                        $value = isset( $item['g:' . $part] ) ? $text( $item['g:' . $part] ) : ( isset( $item[$part] ) ? $text( $item[$part] ) : '' );
                    $row[$part] = $value;
                }
                $out[] = $row;
            }
            return $out;
        };
        $price = expImportCommerce::money( $get( 'price' ) );
        $sale = expImportCommerce::money( $get( 'sale_price' ), $price['currency'] );
        $shipping = array();
        foreach ( $groups( 'shipping', array( 'country', 'region', 'service', 'price' ) ) as $row )
        {
            $money = expImportCommerce::money( $row['price'], $price['currency'] );
            $row['price'] = $money['amount'];
            $row['currency'] = $money['currency'];
            $shipping[] = $row;
        }
        $tax = $groups( 'tax', array( 'country', 'region', 'rate', 'tax_ship' ) );
        foreach ( $tax as $i => $row )
            $tax[$i]['rate'] = expImportCommerce::decimal( $row['rate'] );
        $image = $get( 'image_link' );
        $additional = $all( 'additional_image_link' );
        $productType = $get( 'product_type' );
        $tags = array();
        for ( $i = 0; $i <= 4; $i++ )
            if ( ( $label = $get( 'custom_label_' . $i ) ) !== '' )
                $tags[] = $label;
        $link = $get( 'link' );
        return array(
            'id' => $get( 'id' ) !== '' ? $get( 'id' ) : $link,
            'item_group_id' => $get( 'item_group_id' ),
            'title' => $get( 'title' ),
            'description' => $get( 'description' ),
            'link' => $link,
            'mobile_link' => $get( 'mobile_link' ),
            'image_link' => $image,
            'image' => $image,
            'additional_image_links' => $additional,
            'images' => array_values( array_filter( array_merge( array( $image ), $additional ), 'strlen' ) ),
            'price' => $price['amount'],
            'currency' => $price['currency'],
            'price_text' => $get( 'price' ),
            'sale_price' => $sale['amount'],
            'sale_price_currency' => $sale['amount'] !== '' ? $sale['currency'] : '',
            'sale_price_effective_date' => $get( 'sale_price_effective_date' ),
            'availability' => $get( 'availability' ),
            'availability_date' => $get( 'availability_date' ),
            'condition' => $get( 'condition' ),
            'brand' => $get( 'brand' ),
            'gtin' => $get( 'gtin' ),
            'mpn' => $get( 'mpn' ),
            'identifier_exists' => $get( 'identifier_exists' ),
            'product_type' => $productType,
            'categories' => array_values( array_filter( array_map( 'trim', explode( '>', $productType ) ), 'strlen' ) ),
            'google_product_category' => $get( 'google_product_category' ),
            'color' => $get( 'color' ),
            'size' => $get( 'size' ),
            'gender' => $get( 'gender' ),
            'age_group' => $get( 'age_group' ),
            'material' => $get( 'material' ),
            'pattern' => $get( 'pattern' ),
            'shipping' => $shipping,
            'shipping_weight' => $get( 'shipping_weight' ),
            'tax' => $tax,
            'vat' => $tax ? $tax[0]['rate'] : '',
            'tags' => $tags,
            'raw' => $raw,
        );
    }
}
