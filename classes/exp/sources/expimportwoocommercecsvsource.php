<?php
/**
 * File containing the expImportWooCommerceCsvSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The product CSV export of WooCommerce (Products, Export, with the English column names). One row is one product;
 * the variations of a variable product that follow it are put into its list of variants (option variations), or
 * are records of their own. One record per product:
 *
 *   id                    the ID, else the SKU; wc_id the ID as written
 *   type (simple, variable, variation, grouped, external), types (all of a "simple, virtual")
 *   sku, title (Name), published (1, 0, -1 for a draft), featured, visibility, short_description, description
 *   regular_price, sale_price, price (the regular price), current_price (the sale price when set), currency (option)
 *   sale_from, sale_to, tax_status, tax_class, in_stock, stock, backorders, weight, length, width, height
 *   categories (a list of paths "Clothing > Hoodies"), category (the last part of the first), tags (a list)
 *   shipping_class, images (a list), image (the first), parent, parent_id, grouped, upsells, cross_sells
 *   external_url, button_text, position, purchase_note
 *   attributes            a list of array( name, values (a list), visible, global )
 *   meta                  the columns "Meta: <key>" by key
 *   variants              the variations (with id, sku, regular_price, sale_price, price, stock, image, attributes)
 *   row                   the row as read
 *
 * WooCommerce writes a line break inside a text as \n and a comma inside a list item as \, ; both are read back.
 */
class expImportWooCommerceCsvSource extends expImportShopCsvSource
{
    public static function identifier()
    {
        return 'woocommerce';
    }

    public static function label()
    {
        return 'WooCommerce product CSV';
    }

    public static function extensions()
    {
        return array( 'csv' );
    }

    public static function description()
    {
        return 'The product export of a WooCommerce shop. Every product is a record with SKU, name, short and full '
             . 'description, regular and sale price, tax status and class, stock, categories, tags, images and attributes; '
             . 'the variations of a variable product become its list of variants.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three products: a simple one, a variable one with two variations and one on sale', 'file' => 'products-woocommerce.csv',
                             'options' => array( 'currency' => 'EUR' ) ) );
    }

    public static function preset()
    {
        return expImportCommerce::productPreset( 'woocommerce', 'sku', 'short_description' );
    }

    public static function schema()
    {
        return array(
            'variations' => array( 'type' => 'enum', 'label' => 'Variations', 'default' => 'nested',
                                   'values' => array( 'nested' => 'In the list of variants of their product', 'records' => 'Records of their own' ) ),
        ) + parent::schema();
    }

    protected function checkHeaders( array $headers )
    {
        if ( !in_array( 'name', $headers, true ) || ( !in_array( 'sku', $headers, true ) && !in_array( 'id', $headers, true ) ) )
            throw new expImportSourceException( 'the file is not a WooCommerce product export (no columns ID or SKU and Name)' );
    }

    /** @return array the fields of one row */
    protected function fields( array $row )
    {
        $get = function ( $name ) use ( $row )
        {
            return isset( $row[$name] ) ? $row[$name] : '';
        };
        $text = function ( $name ) use ( $get )
        {
            return str_replace( array( '\\r\\n', '\\n' ), "\n", $get( $name ) );
        };
        $types = expImportCommerce::cellList( strtolower( $get( 'type' ) ) );
        $categories = expImportCommerce::cellList( $get( 'categories' ) );
        $lastPart = '';
        if ( $categories )
        {
            $parts = array_map( 'trim', explode( '>', $categories[0] ) );
            $lastPart = (string)end( $parts );
        }
        $attributes = array();
        $meta = array();
        $dimension = array();
        foreach ( $row as $name => $value )
        {
            if ( preg_match( '/^attribute (\d+) name$/', $name, $m ) && $value !== '' )
                $attributes[] = array( 'name' => $value,
                                       'values' => expImportCommerce::cellList( $get( "attribute {$m[1]} value(s)" ) ),
                                       'visible' => self::flag( $get( "attribute {$m[1]} visible" ) ),
                                       'global' => self::flag( $get( "attribute {$m[1]} global" ) ) );
            elseif ( strpos( $name, 'meta: ' ) === 0 )
                $meta[substr( $name, 6 )] = $value;
            elseif ( preg_match( '/^(weight|length|width|height)\b/', $name, $m ) )
                $dimension[$m[1]] = $value;
        }
        $regular = expImportCommerce::decimal( $get( 'regular price' ) );
        $sale = expImportCommerce::decimal( $get( 'sale price' ) );
        $parent = $get( 'parent' );
        $images = expImportCommerce::cellList( $get( 'images' ) );
        $wcId = $get( 'id' );
        return array(
            'id' => $wcId !== '' ? $wcId : $get( 'sku' ),
            'wc_id' => $wcId,
            'type' => $types ? $types[0] : '',
            'types' => $types,
            'sku' => $get( 'sku' ),
            'title' => $get( 'name' ),
            'published' => $get( 'published' ),
            'featured' => self::flag( $get( 'is featured?' ) ),
            'visibility' => $get( 'visibility in catalog' ),
            'short_description' => $text( 'short description' ),
            'description' => $text( 'description' ),
            'regular_price' => $regular,
            'sale_price' => $sale,
            'price' => $regular !== '' ? $regular : $sale,
            'current_price' => $sale !== '' ? $sale : $regular,
            'currency' => $this->currency(),
            'sale_from' => $get( 'date sale price starts' ),
            'sale_to' => $get( 'date sale price ends' ),
            'tax_status' => $get( 'tax status' ),
            'tax_class' => $get( 'tax class' ),
            'in_stock' => self::flag( $get( 'in stock?' ) ),
            'stock' => $get( 'stock' ),
            'backorders' => $get( 'backorders allowed?' ),
            'weight' => isset( $dimension['weight'] ) ? $dimension['weight'] : '',
            'length' => isset( $dimension['length'] ) ? $dimension['length'] : '',
            'width' => isset( $dimension['width'] ) ? $dimension['width'] : '',
            'height' => isset( $dimension['height'] ) ? $dimension['height'] : '',
            'categories' => $categories,
            'category' => $lastPart,
            'tags' => expImportCommerce::cellList( $get( 'tags' ) ),
            'shipping_class' => $get( 'shipping class' ),
            'images' => $images,
            'image' => $images ? $images[0] : '',
            'parent' => $parent,
            'parent_id' => (string)preg_replace( '/^id:/i', '', $parent ),
            'grouped' => expImportCommerce::cellList( $get( 'grouped products' ) ),
            'upsells' => expImportCommerce::cellList( $get( 'upsells' ) ),
            'cross_sells' => expImportCommerce::cellList( $get( 'cross-sells' ) ),
            'external_url' => $get( 'external url' ),
            'button_text' => $get( 'button text' ),
            'position' => $get( 'position' ),
            'purchase_note' => $text( 'purchase note' ),
            'attributes' => $attributes,
            'meta' => $meta,
            'variants' => array(),
            'row' => $row,
        );
    }

    protected function product( array $row )
    {
        return $this->fields( $row );
    }

    protected function belongs( array $product, array $row )
    {
        $mode = isset( $this->config['variations'] ) ? (string)$this->config['variations'] : 'nested';
        if ( $mode !== 'nested' || $product['type'] !== 'variable' )
            return false;
        $type = expImportCommerce::cellList( strtolower( isset( $row['type'] ) ? $row['type'] : '' ) );
        if ( !$type || $type[0] !== 'variation' )
            return false;
        $parent = isset( $row['parent'] ) ? trim( $row['parent'] ) : '';
        if ( $parent === '' )
            return false;
        if ( stripos( $parent, 'id:' ) === 0 )
            return $product['wc_id'] !== '' && substr( $parent, 3 ) === $product['wc_id'];
        return $product['sku'] !== '' && $parent === $product['sku'];
    }

    protected function add( array &$product, array $row )
    {
        $v = $this->fields( $row );
        $product['variants'][] = array( 'id' => $v['id'], 'sku' => $v['sku'], 'title' => $v['title'], 'regular_price' => $v['regular_price'],
                                        'sale_price' => $v['sale_price'], 'price' => $v['price'], 'stock' => $v['stock'], 'in_stock' => $v['in_stock'],
                                        'image' => $v['image'], 'attributes' => $v['attributes'] );
    }

    protected function finish( array $product )
    {
        // a variable product has no price of its own: the lowest of its variations stands in
        if ( $product['price'] === '' && $product['variants'] )
        {
            $prices = array_filter( array_map( function ( $v ) { return $v['price']; }, $product['variants'] ), 'strlen' );
            if ( $prices )
            {
                usort( $prices, function ( $a, $b ) { return (float)$a <=> (float)$b; } );
                $product['price'] = $prices[0];
                $product['current_price'] = $prices[0];
            }
        }
        return $product;
    }
}
